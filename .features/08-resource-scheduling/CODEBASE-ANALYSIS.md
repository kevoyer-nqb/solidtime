Now let me compile this analysis into a comprehensive report.

## Deep Codebase Analysis: Feature 08 - Resource Scheduling

Based on my comprehensive analysis of the Solidtime codebase, here is a detailed breakdown of all critical components relevant to implementing the Resource Scheduling feature:

---

## 1. MEMBER MODEL - Full Analysis

**Location**: `/home/keven/Documents/solidtime-analysis/app/Models/Member.php` (lines 1-81)

### Schema Structure
The `Member` model represents the pivot between User and Organization (extends `JetstreamMembership`):

**Columns** (from migration `2020_05_21_200000_create_organization_user_table.php`):
- `id` - UUID primary key
- `organization_id` - UUID foreign key
- `user_id` - UUID foreign key
- `role` - string (enum: owner, admin, manager, employee, placeholder)
- `billable_rate` - integer (unsigned, nullable) - cents per hour
- `created_at` / `updated_at` - timestamps
- Unique constraint: `['organization_id', 'user_id']`

**Missing Fields** (to be added by FOUND-006):
- `weekly_capacity` - integer (default 144000 = 40 hours in seconds)

### Role Enum
**Location**: `/home/keven/Documents/solidtime-analysis/app/Enums/Role.php` (lines 7-14)
```php
enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Manager = 'manager';
    case Employee = 'employee';
    case Placeholder = 'placeholder';
}
```

### Relationships
1. `belongsTo(User::class, 'user_id')` - line 52
2. `belongsTo(Organization::class, 'organization_id')` - line 60
3. `hasMany(TimeEntry::class, 'member_id')` - line 68
4. `hasMany(ProjectMember::class, 'member_id')` - line 76

### Traits Used
- `CustomAuditable` - enables audit logging
- `HasFactory` - factory support
- `HasUuids` - UUID primary keys

**Critical for Scheduling**: The Member model is the central entity for resource scheduling. Each member will have assignments, and their `weekly_capacity` will determine available hours.

---

## 2. PROJECT/TASK MODELS - Schema & Relationships

### Project Model
**Location**: `/home/keven/Documents/solidtime-analysis/app/Models/Project.php` (lines 1-202)

**Key Fields**:
- `id`, `name`, `color`, `organization_id`, `client_id`
- `billable_rate` - integer nullable (cents/hour)
- `is_public`, `is_billable` - booleans
- `estimated_time` - integer nullable (seconds) - line 64
- `spent_time` - integer computed attribute (sum of time entry durations) - line 65
- `archived_at` - timestamp nullable
- `is_archived` - computed accessor (line 195)

**Spent Time Computation** (lines 96-109):
```php
public function getSpentTimeComputed(): ?int
{
    if ($this->hasAttribute('spent_time_computed')) {
        return $this->attributes['spent_time_computed'] === null ? 0 : (int) $this->attributes['spent_time_computed'];
    } else {
        $result = $this->timeEntries()
            ->whereNotNull('end')
            ->selectRaw('sum(extract(epoch from ("end" - start))) as spent_time')
            ->first();
        return (int) $result->spent_time;
    }
}
```

Uses PostgreSQL `extract(epoch from ...)` pattern for duration calculation.

**Relationships**:
- `hasMany(ProjectMember::class, 'project_id')` - members relationship (line 158)
- `hasMany(Task::class)` - line 166
- `hasMany(TimeEntry::class, 'project_id')` - line 174
- `belongsTo(Client::class, 'client_id')` - line 150

**Scope for Employee Visibility** (lines 182-190):
```php
public function scopeVisibleByEmployee(Builder $builder, User $user): void
{
    $builder->where(function (Builder $builder) use ($user): Builder {
        return $builder->where('is_public', '=', true)
            ->orWhereHas('members', function (Builder $builder) use ($user): Builder {
                return $builder->whereBelongsTo($user, 'user');
            });
    });
}
```

### Task Model
**Location**: `/home/keven/Documents/solidtime-analysis/app/Models/Task.php` (lines 1-168)

**Key Fields**:
- `id`, `name`, `project_id`, `organization_id`
- `estimated_time` - integer nullable (seconds) - line 56
- `spent_time` - integer computed attribute - line 67
- `done_at` - timestamp nullable
- `is_done` - computed accessor (lines 161-166)

**Spent Time Pattern**: Same as Project (lines 79-92), uses `extract(epoch from ...)`.

---

## 3. PROJECT MEMBER MODEL - Relationship Bridge

**Location**: `/home/keven/Documents/solidtime-analysis/app/Models/ProjectMember.php` (lines 1-86)

**Schema**:
- `id` - UUID
- `project_id` - UUID foreign key
- `member_id` - UUID foreign key
- `user_id` - UUID (legacy, deprecated) - line 22
- `billable_rate` - integer nullable (overrides project rate)
- `created_at` / `updated_at`

**Relationships**:
- `belongsTo(Project::class, 'project_id')` - line 53
- `belongsTo(Member::class, 'member_id')` - line 71
- `belongsTo(User::class, 'user_id')` - deprecated (line 63)

**Organization Scope** (lines 79-84):
```php
public function scopeWhereBelongsToOrganization(Builder $builder, Organization $organization): void
{
    $builder->whereHas('project', static function (Builder $query) use ($organization): void {
        $query->whereBelongsTo($organization, 'organization');
    });
}
```

**Critical for Scheduling**: 
- The PRD states that an Assignment cannot be created unless the member is already a ProjectMember
- Separates access control (ProjectMember) from scheduling (Assignment)
- This is the validation point for assignment creation

---

## 4. TIME ENTRY PATTERNS - Query & Aggregation

### TimeEntry Model
**Location**: `/home/keven/Documents/solidtime-analysis/app/Models/TimeEntry.php` (lines 1-236)

**Schema**:
- `id`, `description`, `start`, `end` (nullable for running entries)
- `billable_rate`, `billable`, `tags` (array)
- `user_id`, `member_id`, `organization_id`
- `project_id`, `client_id`, `task_id` (all nullable)
- `is_imported`, `still_active_email_sent_at`

**Date Range Query Pattern**: Not explicitly scoped in model, but used throughout controllers with `whereBetween('start', [$startDate, $endDate])`.

### TimeEntryAggregationService
**Location**: `/home/keven/Documents/solidtime-analysis/app/Service/TimeEntryAggregationService.php` (lines 1-552)

**Core Method** (lines 47-199):
```php
public function getAggregatedTimeEntries(
    Builder $timeEntriesQuery,
    ?TimeEntryAggregationType $group1Type,
    ?TimeEntryAggregationType $group2Type,
    string $timezone,
    Weekday $startOfWeek,
    bool $fillGapsInTimeGroups,
    ?Carbon $start,
    ?Carbon $end,
    bool $showBillableRate,
    ?TimeEntryRoundingType $roundingType,
    ?int $roundingMinutes
): array
```

**Key Features**:
- Supports two-level grouping (User, Project, Task, Client, Day, Week, Month, Year, Tag, Description, Billable)
- PostgreSQL-specific: `extract(epoch from ...)` for duration calculation (line 80)
- Timezone-aware aggregation (lines 480-512)
- Gap-filling for time-based groups (lines 400-476)
- Cost calculation: `duration * (billable_rate/60/60)` (line 81)

**Grouping Patterns**:
```php
// Group by User
'user_id'
// Group by Week with start-of-week configuration
"to_char(date_bin('7 days', {$dateWithTimeZone}, timestamp '{$startOfWeek}'), 'YYYY-MM-DD')"
// Group by Month
'to_char('.$dateWithTimeZone.', \'YYYY-MM\')'
```

**Critical for Scheduling**:
- Reuse this service for "scheduled vs tracked" comparison
- Aggregation by member + project + week will provide actual tracked hours
- Compare against Assignment.planned_seconds

---

## 5. CALENDAR/TIMELINE UI - FullCalendar Setup

### TimeEntryCalendar Component
**Location**: `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/FullCalendar/TimeEntryCalendar.vue` (lines 1-766)

**FullCalendar Plugins Used** (line 280):
- `dayGridPlugin` - month/day grid view
- `timeGridPlugin` - week/day timeline
- `interactionPlugin` - drag & drop
- `activityStatusPlugin` - custom plugin for idle status visualization

**Key Configuration** (lines 279-313):
```javascript
{
    plugins: [...],
    initialView: 'timeGridWeek',
    slotMinTime: '00:00:00',
    slotMaxTime: '24:00:00',
    slotDuration: '00:15:00',
    firstDay: getFirstDay(), // respects week_start setting
    selectable: true,
    editable: true,
    eventResizableFromStart: true,
    eventDurationEditable: true,
    timeZone: getUserTimezone(),
}
```

**Drag & Drop Patterns**:
- `handleEventDrop` (lines 230-251) - updates time entry on drag
- `handleEventResize` (lines 253-277) - updates time entry on resize
- `handleDateSelect` (lines 206-218) - creates new entry on click/drag

**Event Data Structure** (lines 125-175):
```javascript
{
    id: timeEntry.id,
    start: startTime.format(),
    end: endTime.format(),
    title,
    backgroundColor,
    borderColor,
    textColor,
    startEditable: !isRunning,
    classNames: isRunning ? ['running-entry'] : [],
    extendedProps: {
        timeEntry,
        project,
        client,
        task,
        duration,
        isRunning,
    }
}
```

**Daily Totals Calculation** (lines 178-198):
```javascript
const dailyTotals = computed(() => {
    const totals: Record<string, number> = {};
    props.timeEntries.forEach((entry) => {
        const date = getDayJsInstance()(entry.start).format('YYYY-MM-DD');
        let durationSeconds: number;
        if (entry.end !== null) {
            durationSeconds = getDayJsInstance()(entry.end).diff(getDayJsInstance()(entry.start), 'seconds');
        } else {
            // Running entry - use current time
            durationSeconds = currentTime.value.diff(getDayJsInstance()(entry.start), 'seconds');
        }
        totals[date] = (totals[date] || 0) + durationSeconds;
    });
    return totals;
});
```

**Date Range Handling** (Calendar.vue page, lines 34-56):
```javascript
const expandedDateRange = computed(() => {
    if (!calendarStart.value || !calendarEnd.value) {
        return { start: null, end: null };
    }
    const dayjs = getDayJsInstance();
    const duration = dayjs(calendarEnd.value).diff(dayjs(calendarStart.value), 'milliseconds');
    
    // Expand to include previous and next periods for smooth navigation
    const previousStart = dayjs(calendarStart.value).subtract(duration, 'milliseconds');
    const nextEnd = dayjs(calendarEnd.value).add(duration, 'milliseconds');
    
    const formattedStart = previousStart.utc().tz(getUserTimezone(), true).utc().format();
    const formattedEnd = nextEnd.utc().tz(getUserTimezone(), true).utc().format();
    
    return { start: formattedStart, end: formattedEnd };
});
```

**Critical for Scheduling**:
- FullCalendar is already integrated and working
- Drag & drop patterns are well-established
- Timeline can be adapted for Gantt-style assignment bars
- Week start configuration respects organization settings
- Activity status plugin shows custom visual overlays are possible

---

## 6. DASHBOARD WIDGETS - Aggregation Patterns

### DashboardService
**Location**: `/home/keven/Documents/solidtime-analysis/app/Service/DashboardService.php` (lines 1-467)

**Weekly/Monthly Aggregation Pattern** (lines 138-174):
```php
public function getDailyTrackedHours(User $user, Organization $organization, int $days): array
{
    $timezone = $this->timezoneService->getTimezoneFromUser($user);
    $timezoneShift = $this->timezoneService->getShiftFromUtc($timezone);
    
    // Timezone adjustment for date grouping
    if ($timezoneShift > 0) {
        $dateWithTimeZone = 'start + INTERVAL \''.$timezoneShift.' second\'';
    } elseif ($timezoneShift < 0) {
        $dateWithTimeZone = 'start - INTERVAL \''.abs($timezoneShift).' second\'';
    } else {
        $dateWithTimeZone = 'start';
    }
    
    $possibleDays = $this->lastDays($days, $timezone);
    
    $query = TimeEntry::query()
        ->select(DB::raw('DATE('.$dateWithTimeZone.') as date, round(sum(extract(epoch from (coalesce("end", now()) - start)))) as aggregate'))
        ->where('user_id', '=', $user->getKey())
        ->where('organization_id', '=', $organization->getKey())
        ->groupBy(DB::raw('DATE('.$dateWithTimeZone.')'))
        ->orderBy('date');
    
    $query = $this->constrainDateByPossibleDates($query, $possibleDays, $timezone);
    $resultDb = $query->get()->pluck('aggregate', 'date');
    
    $result = [];
    foreach ($possibleDays as $possibleDay) {
        $result[] = [
            'date' => $possibleDay,
            'duration' => (int) ($resultDb->get($possibleDay) ?? 0),
        ];
    }
    
    return $result;
}
```

**Key Patterns**:
- Timezone-aware date grouping using PostgreSQL `INTERVAL` arithmetic
- `coalesce("end", now())` - handles running time entries
- Gap-filling: iterate through expected dates and default to 0
- `extract(epoch from ...)` for duration in seconds

**Weekly History** (lines 181-215):
```php
public function getWeeklyHistory(User $user, Organization $organization): array
{
    // ... similar pattern ...
    $possibleDays = $this->daysOfThisWeek($timezone, $user->week_start);
    // Query grouped by DATE()
    // Fill gaps with 0
}
```

**Week Constraint** (lines 123-129):
```php
private function constrainDateByCurrentWeek(Builder $builder, CarbonTimeZone $timeZone, Weekday $startOfWeek): Builder
{
    return $builder->whereBetween('start', [
        Carbon::now($timeZone)->startOfWeek($startOfWeek->carbonWeekDay())->utc(),
        Carbon::now($timeZone)->endOfWeek($startOfWeek->toEndOfWeek()->carbonWeekDay())->utc(),
    ]);
}
```

**Critical for Scheduling**:
- Weekly capacity calculation can follow the same pattern
- Date range queries with timezone adjustment are well-established
- Gap-filling pattern ensures every week in the timeline has data
- `week_start` configuration is respected throughout

---

## 7. ORGANIZATION SETTINGS - Configuration Storage

### Organization Model
**Location**: `/home/keven/Documents/solidtime-analysis/app/Models/Organization.php` (lines 1-190)

**Existing Columns**:
- `id`, `name`, `personal_team`, `currency`, `user_id` (owner)
- `billable_rate` - integer nullable
- `employees_can_see_billable_rates` - boolean
- `employees_can_manage_tasks` - boolean
- `prevent_overlapping_time_entries` - boolean
- Format settings: `number_format`, `currency_format`, `date_format`, `interval_format`, `time_format`

**Missing Columns** (to be added by FOUND-006):
- `default_weekly_capacity` - integer (default 144000 = 40h in seconds)

**Relationships**:
- `belongsToMany(User::class, Member::class)` with pivot `membership` (line 136)
- `hasMany(Member::class)` - line 159
- `belongsTo(User::class, 'user_id')` - owner (line 152)

**Critical for Scheduling**:
- Organization-level default capacity will be the fallback for members without individual capacity set
- Follow existing boolean flag patterns (e.g., `employees_can_see_billable_rates`)
- Potential future: `enable_team_scoping` flag (SF-07 from SHARED-FOUNDATIONS.md)

---

## 8. SCHEDULED COMMANDS - Kernel Patterns

### Console Kernel
**Location**: `/home/keven/Documents/solidtime-analysis/app/Console/Kernel.php` (lines 1-60)

**Existing Scheduled Commands** (lines 15-50):
```php
protected function schedule(Schedule $schedule): void
{
    // Every 10 minutes
    $schedule->command('time-entry:send-still-running-mails')
        ->when(fn (): bool => config('scheduling.tasks.time_entry_send_still_running_mails'))
        ->everyTenMinutes();
    
    // Every 10 minutes
    $schedule->command('auth:send-mails-expiring-api-tokens')
        ->when(fn (): bool => config('scheduling.tasks.auth_send_mails_expiring_api_tokens'))
        ->everyTenMinutes();
    
    // Every 6 hours
    $schedule->command('self-host:database-consistency')
        ->when(fn (): bool => config('scheduling.tasks.self_hosting_database_consistency'))
        ->everySixHours();
    
    // Twice daily with random offset based on APP_KEY
    $schedule->command('self-host:check-for-update')
        ->twiceDailyAt($firstHour, $secondHour, $minuteOffset);
}
```

**Pattern**: 
- Commands are gated by config flags
- Use `->when(fn() => config(...))` for feature flags
- Commands are stored in `app/Console/Commands/`

**Critical for Scheduling**:
- Assignment reminder notifications could run daily
- Capacity alerts could run weekly
- Pattern: Create `SchedulingReminderCommand.php` and register here

---

## 9. FRONTEND COMPONENT PATTERNS - UI Composition

### Table Components
**Pattern**: Feature-specific Table + TableRow + TableHeading components

**Example**: `/home/keven/Documents/solidtime-analysis/resources/js/Components/Common/Project/ProjectTable.vue`
- Uses TanStack Table (`@tanstack/vue-table`) for sorting (line 83)
- Integrates with Pinia stores for CRUD operations (line 14)
- Modal-based create/edit patterns (line 6)

**Table Structure**:
```javascript
// Define columns with accessors
const columns = [
    { id: 'name', accessorFn: (row: Project) => row.name.toLowerCase() },
    { id: 'client_name', accessorFn: (row: Project) => clientNameMap.value.get(row.client_id) },
    { id: 'spent_time', accessorFn: (row: Project) => row.spent_time ?? 0 },
];

// TanStack table instance
const table = useVueTable({
    get data() { return props.projects; },
    columns,
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
});
```

### Modal Components
**Pattern**: Feature-specific CreateModal + EditModal

**Examples**:
- `ProjectMemberCreateModal.vue`, `ProjectMemberEditModal.vue`
- `TaskCreateModal.vue`, `TaskEditModal.vue`
- `MemberInviteModal.vue`, `MemberEditModal.vue`

**Typical Structure**:
- `v-model:show` for visibility control
- Props for data and CRUD callbacks
- Form validation via Vuelidate or similar
- Success/error notifications via `useNotificationsStore()`

### Pinia Store Pattern
**Location**: `/home/keven/Documents/solidtime-analysis/resources/js/utils/useProjects.ts` (lines 1-100)

**Structure**:
```typescript
export const useProjectsStore = defineStore('projects', () => {
    const projectResponse = ref<ProjectResponse | null>(null);
    const { handleApiRequestNotifications } = useNotificationsStore();
    const queryClient = useQueryClient();
    
    function invalidateProjectsQuery() {
        queryClient.invalidateQueries({ queryKey: ['projects'] });
    }
    
    async function fetchProjects() {
        const organization = getCurrentOrganizationId();
        if (organization) {
            projectResponse.value = await handleApiRequestNotifications(
                () => api.getProjects({ params: { organization }, queries: { archived: 'all' } }),
                undefined,
                'Failed to fetch projects'
            );
        }
    }
    
    async function createProject(projectBody: CreateProjectBody) {
        // ... create logic ...
        await fetchProjects();
        invalidateProjectsQuery();
        return response['data'];
    }
    
    const projects = computed<Project[]>(() => projectResponse.value?.data || []);
    
    return { projects, fetchProjects, createProject, updateProject, deleteProject };
});
```

**Critical for Scheduling**:
- Follow this pattern for `useScheduling.ts` store
- Use `@tanstack/vue-query` for server state
- Pinia for client state
- Invalidate queries after mutations

---

## 10. PERMISSION SYSTEM - Role-Based Access

### JetstreamServiceProvider
**Location**: `/home/keven/Documents/solidtime-analysis/app/Providers/JetstreamServiceProvider.php` (lines 78-336)

**Permission Definition Pattern** (lines 82-147):
```php
Jetstream::role(Role::Owner->value, 'Owner', [
    'charts:view:own',
    'charts:view:all',
    'projects:view',
    'projects:view:all',
    'projects:create',
    'projects:update',
    'projects:delete',
    // ... many more ...
])->description('Owner users can perform any action...');
```

**Role Hierarchy**:
1. **Owner**: All permissions + billing + ownership transfer
2. **Admin**: All permissions except billing
3. **Manager**: Full CRUD on projects/tasks/time-entries, view members, cannot manage organization
4. **Employee**: View own data, create/update/delete own time entries, view projects they're members of
5. **Placeholder**: No permissions (for imports)

**Permission Naming Convention**:
- Pattern: `{entity}:{action}` or `{entity}:{action}:{scope}`
- Scopes: `:own` (user's own data), `:all` (org-wide data)
- Examples:
  - `time-entries:view:own` - Employee can view their own
  - `time-entries:view:all` - Manager/Admin/Owner can view all
  - `projects:create` - Manager+ can create projects

**Controller Permission Check** (from `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/Controller.php`):
```php
protected function checkPermission(Organization $organization, string $permission): void
{
    if (! $this->permissionStore->has($organization, $permission)) {
        throw new AuthorizationException;
    }
}

protected function checkAnyPermission(Organization $organization, array $permissions): void
{
    foreach ($permissions as $permission) {
        if ($this->permissionStore->has($organization, $permission)) {
            return;
        }
    }
    throw new AuthorizationException;
}
```

**Critical for Scheduling**:
- New permissions needed (as per PRD):
  - `assignments:view`, `assignments:view:own`, `assignments:create`, `assignments:update`, `assignments:delete`
  - `milestones:view`, `milestones:create`, `milestones:update`, `milestones:delete`
  - `scheduling:view`, `scheduling:view:all`
- Owner/Admin/Manager get full permissions
- Employee gets `:view:own` for assignments and `milestones:view`
- Placeholder gets none

**Modular Permissions** (SF-08 from SHARED-FOUNDATIONS):
- Create `app/Permissions/SchedulingPermissions.php`
- Register in `JetstreamServiceProvider::configurePermissions()`
- Avoids merge conflicts with other features

---

## 11. CROSS-FEATURE INTEGRATION POINTS

### Feature 07: PTO & Time Off (AMD-08 from PRD)
**Integration Point**: Capacity calculation should account for approved time-off

**Recommended Approach**:
```php
// In SchedulingService::getMemberWeeklyCapacity()
public function getMemberWeeklyCapacity(Member $member, Organization $organization, Carbon $weekStart): int
{
    $baseCapacity = $member->weekly_capacity ?? $organization->default_weekly_capacity;
    
    // If PTO feature is deployed, reduce capacity by approved time-off
    if (class_exists('App\\Models\\TimeOffRequest')) {
        $approvedPtoSeconds = TimeOffRequest::query()
            ->where('member_id', $member->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $weekStart->endOfWeek())
            ->whereDate('end_date', '>=', $weekStart->startOfWeek())
            ->sum('duration_seconds'); // hypothetical column
        
        $baseCapacity -= $approvedPtoSeconds;
    }
    
    return max(0, $baseCapacity);
}
```

**Status**: PTO feature not yet implemented. Design with optional integration in mind.

### Feature 10: Teams & Groups (AMD-08 from PRD)
**Integration Point**: Scheduling views should support `team_ids` filtering

**Recommended Approach**:
```php
// In SchedulingService::getTimelineData()
public function getTimelineData(
    Organization $organization,
    Carbon $start,
    Carbon $end,
    ?array $memberIds = null,
    ?array $projectIds = null,
    ?array $teamIds = null // NEW parameter
): array
{
    $membersQuery = Member::query()
        ->whereBelongsTo($organization, 'organization')
        ->where('role', '!=', Role::Placeholder->value);
    
    // If team scoping is enabled and teams are provided
    if ($teamIds !== null && $organization->enable_team_scoping) {
        $membersQuery->whereHas('teams', function($q) use ($teamIds) {
            $q->whereIn('team_id', $teamIds);
        });
    }
    
    if ($memberIds !== null) {
        $membersQuery->whereIn('id', $memberIds);
    }
    
    $members = $membersQuery->get();
    // ... rest of logic ...
}
```

**Status**: Teams feature not yet implemented. API should accept `team_ids[]` as optional query param.

### Weekly Capacity Shared Column (SF-06 from SHARED-FOUNDATIONS)
**Critical**: The `weekly_capacity` column on the `members` table is owned by a shared foundation migration:

**Migration**: `2026_02_28_000001_add_weekly_capacity_to_members.php`
```php
Schema::table('members', function (Blueprint $table) {
    $table->unsignedInteger('weekly_capacity')
        ->default(144000)  // 40 hours in seconds
        ->after('billable_rate')
        ->comment('Weekly work capacity in seconds. Default 40h = 144000s.');
});
```

**Migration**: `2026_02_28_000002_add_default_weekly_capacity_to_organizations.php`
```php
Schema::table('organizations', function (Blueprint $table) {
    $table->unsignedInteger('default_weekly_capacity')
        ->default(144000)
        ->after('billable_rate')
        ->comment('Default weekly capacity for new members in seconds.');
});
```

**Task**: FOUND-006 (prerequisite for SCHED-003)

---

## 12. RISK ASSESSMENT

### Performance Risks

**Risk 1: Timeline query with large datasets**
- **Scenario**: Organization with 50 members, 200 active assignments, 12-week timeline window
- **Query Pattern**:
  ```php
  // Fetching assignments
  Assignment::query()
      ->with(['member.user', 'project'])
      ->whereBelongsTo($organization, 'organization')
      ->where('start_date', '<=', $end)
      ->where('end_date', '>=', $start)
      ->get();
  ```
- **Estimated Rows**: ~500 assignments per query
- **Mitigation**:
  - Composite index: `(organization_id, start_date, end_date)` - SCHED-001 migration
  - Composite index: `(member_id, start_date, end_date)` - SCHED-001 migration
  - Pagination: limit to 50 members per request, client-side virtual scrolling
  - Caching: Cache timeline data for 5 minutes with Redis

**Risk 2: Weekly utilization calculation**
- **Scenario**: Calculate utilization for every week for every member
- **Complexity**: O(members × weeks × assignments)
- **Mitigation**:
  - Precompute utilization during query assembly
  - Use PostgreSQL window functions for efficient aggregation
  - Example optimized query:
    ```sql
    SELECT 
        m.id as member_id,
        week_series.week_start,
        COALESCE(SUM(
            EXTRACT(EPOCH FROM (
                LEAST(a.end_date, week_series.week_end) - 
                GREATEST(a.start_date, week_series.week_start) + INTERVAL '1 day'
            )) * a.planned_seconds / 
            EXTRACT(EPOCH FROM (a.end_date - a.start_date + INTERVAL '1 day'))
        ), 0) as planned_seconds
    FROM members m
    CROSS JOIN generate_series(:start, :end, '7 days') AS week_series(week_start)
    LEFT JOIN assignments a ON a.member_id = m.id
        AND a.start_date <= week_series.week_end
        AND a.end_date >= week_series.week_start
    WHERE m.organization_id = :org_id
    GROUP BY m.id, week_series.week_start
    ```

**Risk 3: Rendering complexity in timeline component**
- **Scenario**: 50 members × 12 weeks = 600 cells + 200 assignment bars
- **DOM Nodes**: ~1000+ elements
- **Mitigation**:
  - Use Vue virtual scrolling library (e.g., `vue-virtual-scroller`)
  - Render only visible weeks (viewport window)
  - CSS transform for bar positioning (avoid layout thrashing)
  - Debounce scroll events

**Risk 4: Capacity calculation accuracy**
- **Scenario**: Assignment spans partial weeks
- **Problem**: How to distribute planned hours across weeks?
- **Solution**:
  - Option A: Divide total planned hours evenly across all weeks
  - Option B: Assume constant weekly allocation (planned_seconds per week)
  - **Recommendation**: Option B (as per PRD: `planned_seconds` is per-week allocation)

### Data Integrity Risks

**Risk 5: Orphaned assignments when ProjectMember is deleted**
- **Mitigation**: Cascade delete in migration (SCHED-001):
  ```php
  $table->foreign('member_id')
      ->references('id')->on('members')
      ->onDelete('cascade');
  $table->foreign('project_id')
      ->references('id')->on('projects')
      ->onDelete('cascade');
  ```

**Risk 6: Validation: Member must be ProjectMember before assignment**
- **Mitigation**: Form request validation (SCHED-009):
  ```php
  'member_id' => [
      'required',
      new ExistsEloquent(Member::class, null, function ($query) {
          $query->where('organization_id', $this->organization->id);
      }),
      function ($attribute, $value, $fail) {
          $exists = ProjectMember::where('project_id', $this->project_id)
              ->where('member_id', $value)
              ->exists();
          if (!$exists) {
              $fail('The member must be a member of the project before assignment.');
          }
      },
  ],
  ```

**Risk 7: Placeholder member assignments (AMD-06 from PRD)**
- **Problem**: Placeholders should not be assignable
- **Mitigation**: Validation rule:
  ```php
  'member_id' => [
      'required',
      new ExistsEloquent(Member::class, null, function ($query) {
          $query->where('organization_id', $this->organization->id)
                ->where('role', '!=', Role::PLACEHOLDER->value);
      }),
  ],
  ```

---

## 13. ESSENTIAL FILE REFERENCE

### Core Models (Backend)
1. `/home/keven/Documents/solidtime-analysis/app/Models/Member.php` (lines 1-81) - Central scheduling entity
2. `/home/keven/Documents/solidtime-analysis/app/Models/Project.php` (lines 1-202) - Assignment target, estimated_time, spent_time
3. `/home/keven/Documents/solidtime-analysis/app/Models/ProjectMember.php` (lines 1-86) - Access control, validation prerequisite
4. `/home/keven/Documents/solidtime-analysis/app/Models/Organization.php` (lines 1-190) - Default capacity, org settings
5. `/home/keven/Documents/solidtime-analysis/app/Models/TimeEntry.php` (lines 1-236) - Actual tracked time for comparison

### Services (Backend)
6. `/home/keven/Documents/solidtime-analysis/app/Service/TimeEntryAggregationService.php` (lines 1-552) - Reuse for scheduled-vs-tracked
7. `/home/keven/Documents/solidtime-analysis/app/Service/DashboardService.php` (lines 1-467) - Weekly aggregation patterns

### Controllers (Backend)
8. `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/Controller.php` (lines 1-53) - Base controller with permission checks
9. `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/ChartController.php` (lines 1-100+) - Example chart endpoint patterns

### Validation (Backend)
10. `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/BaseFormRequest.php` (lines 1-29) - Base form request class

### Permissions (Backend)
11. `/home/keven/Documents/solidtime-analysis/app/Providers/JetstreamServiceProvider.php` (lines 78-336) - Permission definitions
12. `/home/keven/Documents/solidtime-analysis/app/Enums/Role.php` (lines 7-14) - Role enum

### Migrations (Backend)
13. `/home/keven/Documents/solidtime-analysis/database/migrations/2020_05_21_200000_create_organization_user_table.php` (lines 1-36) - Member table structure

### Routes (Backend)
14. `/home/keven/Documents/solidtime-analysis/routes/api.php` (lines 1-200) - API routing patterns

### Scheduled Commands (Backend)
15. `/home/keven/Documents/solidtime-analysis/app/Console/Kernel.php` (lines 1-60) - Command scheduling patterns

### Calendar UI (Frontend)
16. `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/FullCalendar/TimeEntryCalendar.vue` (lines 1-766) - FullCalendar integration, drag-and-drop
17. `/home/keven/Documents/solidtime-analysis/resources/js/Pages/Calendar.vue` (lines 1-146) - Calendar page, date range expansion

### Table Components (Frontend)
18. `/home/keven/Documents/solidtime-analysis/resources/js/Components/Common/Project/ProjectTable.vue` (lines 1-100+) - TanStack Table pattern

### Pinia Stores (Frontend)
19. `/home/keven/Documents/solidtime-analysis/resources/js/utils/useProjects.ts` (lines 1-100+) - Store pattern with API integration

---

## ARCHITECTURE RECOMMENDATIONS

### 1. Database Design
- **Indexes are critical**: Composite indexes on `(organization_id, member_id, start_date, end_date)` and `(organization_id, project_id)` for assignments
- **Cascade deletes**: Ensure orphaned assignments are cleaned up when projects/members are deleted
- **Partial weeks**: Store `planned_seconds` as a per-week value, not total for entire assignment

### 2. Backend Service Layer
- **SchedulingService**: Central service for capacity calculations, timeline assembly, comparison logic
- **Reuse TimeEntryAggregationService**: Leverage existing aggregation for actual hours
- **Timezone awareness**: Use the same `TimezoneService` pattern as DashboardService
- **Query optimization**: Use eager loading (`with()`) to avoid N+1 queries

### 3. API Design
- **Timeline endpoint**: `/api/v1/organizations/{org}/scheduling/timeline?start=2026-01-01&end=2026-03-31&member_ids[]=...`
- **Capacity endpoint**: `/api/v1/organizations/{org}/scheduling/capacity?start=2026-01-01&end=2026-03-31`
- **Pagination**: Consider cursor-based pagination for large member lists
- **Response caching**: Use Redis with 5-minute TTL for timeline queries

### 4. Frontend Architecture
- **Timeline Component**: Evaluate `vue-gantt` or build custom with SVG/canvas (AMD-07 from PRD)
- **Virtual scrolling**: Use `vue-virtual-scroller` for member list
- **Pinia store**: `useScheduling.ts` for assignment CRUD, `useSchedulingTimeline.ts` for timeline state
- **TanStack Query**: Manage server state with automatic refetching and caching

### 5. Permission Strategy
- **Modular registration**: Create `app/Permissions/SchedulingPermissions.php` (SF-08 from SHARED-FOUNDATIONS)
- **Employee restrictions**: Employees can view own assignments via `:view:own` scope
- **Validation layer**: Check permissions in controller AND form request

### 6. Cross-Feature Integration
- **PTO integration**: Design capacity calculation to optionally reduce for approved time-off
- **Teams integration**: Accept `team_ids[]` filter in timeline queries
- **Notification infrastructure**: Depend on FOUND-001 through FOUND-005 for reminder notifications

---

## SUMMARY

The Solidtime codebase is well-structured for adding the Resource Scheduling feature. Key strengths:

1. **Solid foundation**: UUID-based models, audit logging, timezone-aware date handling
2. **Proven patterns**: TimeEntryAggregationService, DashboardService aggregation, FullCalendar integration
3. **Permission system**: Role-based access control with `:own` / `:all` scoping
4. **Frontend patterns**: Pinia stores, TanStack Query, modal-based CRUD, table components

Key risks to address:

1. **Performance**: Timeline queries, utilization calculation, rendering 1000+ DOM nodes
2. **Data integrity**: Cascade deletes, ProjectMember validation, placeholder rejection
3. **Cross-feature dependencies**: PTO capacity reduction (optional), Teams filtering (optional), shared `weekly_capacity` column (required - FOUND-006)

The 20-hour estimate for the Gantt timeline component (SCHED-023) should be validated during Architecture phase after library evaluation. The rest of the implementation is straightforward given existing patterns.

All file paths provided are absolute and can be used directly for implementation reference.