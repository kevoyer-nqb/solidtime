# Codebase Analysis: Feature 10 -- Teams & Groups

**Generated**: 2026-02-06
**Source**: Explorer agent analysis (session `a7f8ee3`) + manual verification
**PRD Reference**: `.features/10-teams-groups/PRD.md` (with amendments)
**Shared Foundations**: `.features/SHARED-FOUNDATIONS.md`

---

## Table of Contents

1. [Jetstream Team to Organization Mapping](#1-jetstream-team--organization-mapping)
2. [Project Model Analysis](#2-project-model-analysis)
3. [Client Model Analysis](#3-client-model-analysis)
4. [Task Model Analysis](#4-task-model-analysis)
5. [TimeEntryFilter Analysis](#5-timeentryfilter-analysis)
6. [Controllers Requiring Modification](#6-controllers-requiring-modification)
7. [Organization Settings Pattern](#7-organization-settings-pattern)
8. [Pivot Table Patterns](#8-pivot-table-patterns)
9. [Dashboard and Chart Query Paths](#9-dashboard-and-chart-query-paths)
10. [Data Migration Patterns](#10-data-migration-patterns)
11. [Blast Radius Assessment](#11-blast-radius-assessment)
12. [Risk Assessment](#12-risk-assessment)

---

## 1. Jetstream Team <-> Organization Mapping

### 1.1 How Jetstream's Team Becomes Organization

Solidtime uses Jetstream's team concept but renames it to "Organization" throughout the codebase. The binding is established in a single file.

**File**: `/home/keven/Documents/solidtime-analysis/app/Providers/JetstreamServiceProvider.php`

**Lines 51-62** -- The critical model binding section:

```php
Jetstream::createTeamsUsing(CreateOrganization::class);
Jetstream::updateTeamNamesUsing(UpdateOrganization::class);
Jetstream::addTeamMembersUsing(AddOrganizationMember::class);
Jetstream::inviteTeamMembersUsing(InviteOrganizationMember::class);
Jetstream::removeTeamMembersUsing(RemoveOrganizationMember::class);
Jetstream::deleteTeamsUsing(DeleteOrganization::class);
Jetstream::deleteUsersUsing(DeleteUser::class);
Jetstream::useTeamModel(Organization::class);              // <-- KEY LINE
Jetstream::useMembershipModel(Member::class);
Jetstream::useTeamInvitationModel(OrganizationInvitation::class);
app()->singleton(UpdateTeamMemberRole::class, UpdateMemberRole::class);
app()->singleton(ValidateTeamDeletion::class, ValidateOrganizationDeletion::class);
```

**Line 58** is the single most important line: `Jetstream::useTeamModel(Organization::class)` tells Jetstream to use the `Organization` model wherever it would normally reference a `Team`.

**File**: `/home/keven/Documents/solidtime-analysis/app/Models/Organization.php`

**Lines 27, 55** -- The Organization model extends Jetstream's Team:

```php
use Laravel\Jetstream\Team as JetstreamTeam;
// ...
class Organization extends JetstreamTeam implements AuditableContract
```

**Lines 98-102** -- It dispatches Jetstream's Team events:

```php
protected $dispatchesEvents = [
    'created' => TeamCreated::class,
    'updated' => TeamUpdated::class,
    'deleted' => TeamDeleted::class,
];
```

### 1.2 Database Mapping

The database table is `organizations` (not `teams`). Jetstream's internal references to "teams" are resolved through the model binding above. There is no `teams` table in the current schema.

### 1.3 Implications for the New Team Model

Creating a new `App\Models\Team` with a `teams` database table is **safe** because:

1. Jetstream's `Team` model is only referenced via `Jetstream::teamModel()`, which returns `Organization::class`
2. Nowhere in the codebase does code directly import `Laravel\Jetstream\Team` except the Organization model's `extends` clause
3. The `teams` table name does not conflict with any existing table

However, the naming overlap requires care:
- All imports should use fully-qualified class names or explicit aliases to avoid IDE confusion
- A code comment should be placed in the new `Team.php` model: `// Note: This is solidtime's Team (sub-org group), not Jetstream's Team (which maps to Organization)`

### 1.4 Jetstream's Inertia Page for Organization Settings

**File**: `/home/keven/Documents/solidtime-analysis/app/Providers/JetstreamServiceProvider.php`

**Lines 292-335** -- The `Teams/Show` Inertia page renders organization settings:

```php
->whenRendering(
    'Teams/Show',
    function (Request $request, array $data): array {
        /** @var Organization $teamModel */
        $teamModel = $data['team'];
        $owner = $teamModel->owner;

        return array_merge($data, [
            'team' => [
                'id' => $teamModel->getKey(),
                'name' => $teamModel->name,
                'currency' => $teamModel->currency,
                // ... other fields ...
            ],
            // ...
        ]);
    }
)
```

This is where `enable_team_scoping` will need to be added to the Inertia props.

---

## 2. Project Model Analysis

**File**: `/home/keven/Documents/solidtime-analysis/app/Models/Project.php` (201 lines)

### 2.1 Schema (Lines 22-44)

```php
/**
 * @property string $id
 * @property string $name
 * @property string $color
 * @property string $organization_id
 * @property string $client_id
 * @property int|null $billable_rate
 * @property bool $is_public
 * @property bool $is_billable
 * @property-read bool $is_archived
 * @property int|null $estimated_time
 * @property int $spent_time
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read Client|null $client
 * @property-read Collection<int, Task> $tasks
 * @property-read Collection<int, ProjectMember> $members
 */
```

### 2.2 Relationships (Lines 141-177)

```php
public function organization(): BelongsTo    // line 142
public function client(): BelongsTo          // line 150
public function members(): HasMany           // line 158 -- ProjectMember pivot
public function tasks(): HasMany             // line 166
public function timeEntries(): HasMany       // line 174
```

**New relationships to add**:
```php
public function teamProjects(): HasMany      // via team_projects pivot
public function teams(): BelongsToMany       // via team_projects pivot
```

### 2.3 Critical Scope: `visibleByEmployee` (Lines 182-190)

This is the most important method for team scoping integration:

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

**Current logic**: A project is visible to an employee if `is_public = true` OR the employee is a `ProjectMember`.

**Required modification for team scoping**: When `enable_team_scoping = true`, add an additional constraint: the project must also belong to one of the user's teams. The new logic becomes:

```
visible = (is_public OR ProjectMember) AND (in_user_teams)
```

This is where `TeamScopeService::applyTeamScopeToProjects()` must integrate. The scope itself should NOT be modified directly; instead, team scoping is applied as a separate query constraint in controllers, wrapping around the existing `visibleByEmployee` scope.

### 2.4 Traits and Interfaces

```php
class Project extends Model implements AuditableContract
{
    use ComputedAttributes;      // For spent_time computed attribute
    use CustomAuditable;         // Audit trail
    use HasFactory;              // Testing
    use HasUuids;                // UUID primary key
```

---

## 3. Client Model Analysis

**File**: `/home/keven/Documents/solidtime-analysis/app/Models/Client.php` (87 lines)

### 3.1 Schema (Lines 19-30)

```php
/**
 * @property string $id
 * @property string $name
 * @property string $organization_id
 * @property-read bool $is_archived
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 */
```

The Client model is simpler than Project -- it has `name`, `organization_id`, and `archived_at`.

### 3.2 Relationships (Lines 50-64)

```php
public function organization(): BelongsTo    // line 53
public function projects(): HasMany          // line 61 -- Client has many Projects
```

**New relationships to add**:
```php
public function teamClients(): HasMany       // via team_clients pivot
public function teams(): BelongsToMany       // via team_clients pivot
```

### 3.3 Critical Scope: `visibleByEmployee` (Lines 70-76)

```php
public function scopeVisibleByEmployee(Builder $builder, User $user): Builder
{
    return $builder->whereHas('projects', function (Builder $builder) use ($user): Builder {
        /** @var Builder<Project> $builder */
        return $builder->visibleByEmployee($user);
    });
}
```

**Current logic**: A client is visible to an employee if the client has at least one project that is visible to that employee (via `Project::visibleByEmployee`).

**Key insight**: Client visibility is derived from project visibility. This creates two possible approaches for team scoping:

1. **Direct team scoping** via `team_clients` pivot (PRD approach) -- apply `whereHas('teamClients', ...)` separately
2. **Inherited scoping** via projects -- if projects are team-scoped, clients automatically become scoped through the existing `visibleByEmployee` chain

The PRD specifies option 1 (direct `team_clients` pivot), which gives more granular control. However, this means a client could be assigned to a team even if its projects are not, creating a visibility inconsistency. The `TeamScopeService` should handle this.

### 3.4 Traits

```php
class Client extends Model implements AuditableContract
{
    use CustomAuditable;
    use HasFactory;
    use HasUuids;
```

Same pattern as Project (minus `ComputedAttributes`).

---

## 4. Task Model Analysis

**File**: `/home/keven/Documents/solidtime-analysis/app/Models/Task.php` (167 lines)

### 4.1 Scope: `visibleByEmployee` (Lines 150-156)

```php
public function scopeVisibleByEmployee(Builder $builder, User $user): Builder
{
    return $builder->whereHas('project', function (Builder $builder) use ($user): Builder {
        /** @var Builder<Project> $builder */
        return $builder->visibleByEmployee($user);
    });
}
```

**Current logic**: A task is visible if its parent project is visible.

**Team scoping impact**: Per AMD-07 in the PRD, tasks inherit team scoping through their parent project. No separate `team_tasks` pivot table is needed. When `TeamScopeService` filters projects by team, tasks automatically become filtered because `Task::visibleByEmployee` delegates to `Project::visibleByEmployee`.

However, the `TaskController::index()` method (lines 60-77) has its own `visibleByEmployee` call:

```php
// TaskController.php lines 68-77
$query = Task::query()
    ->whereBelongsTo($organization, 'organization');
if ($projectId !== null) {
    $query->where('project_id', '=', $projectId);
}
if (! $canViewAllTasks) {
    $query->visibleByEmployee($user);
}
```

If team scoping is applied at the project level (via `TeamScopeService::applyTeamScopeToProjects()`), and `Task::visibleByEmployee` checks project visibility, the team scoping will cascade through the existing chain. No direct modification of `TaskController` is needed beyond verifying this chain works correctly.

### 4.2 Relationships (Lines 123-143)

```php
public function project(): BelongsTo         // line 125
public function organization(): BelongsTo    // line 133
public function timeEntries(): HasMany       // line 141
```

No new relationships needed on the Task model.

---

## 5. TimeEntryFilter Analysis

**File**: `/home/keven/Documents/solidtime-analysis/app/Service/TimeEntryFilter.php` (207 lines)

### 5.1 Architecture Pattern

TimeEntryFilter uses a **builder/fluent pattern** that wraps an Eloquent query builder:

```php
class TimeEntryFilter
{
    private Builder $builder;  // Builder<TimeEntry>

    public function __construct(Builder $builder)
    {
        $this->builder = $builder;
    }

    // Every filter method returns $this for chaining
    public function addSomeFilter(?SomeType $value): self
    {
        if ($value === null) {
            return $this;  // Skip if null
        }
        $this->builder->where(...);
        return $this;
    }

    // Terminal method to get the query builder back
    public function get(): Builder
    {
        return $this->builder;
    }
}
```

### 5.2 Complete List of Filter Methods

| Method | Lines | Filter Type | SQL Behavior |
|--------|-------|-------------|-------------|
| `addEndFilter(?string)` | 28-36 | String -> Carbon parse | `WHERE start < $end` |
| `addEnd(?Carbon)` | 38-46 | Carbon | `WHERE start < $end` |
| `addStartFilter(?string)` | 48-56 | String -> Carbon parse | `WHERE start > $start` |
| `addStart(?Carbon)` | 58-66 | Carbon | `WHERE start > $start` |
| `addActiveFilter(?string)` | 68-82 | String 'true'/'false' | `WHERE end IS NULL` / `WHERE end IS NOT NULL` |
| `addActive(?bool)` | 84-93 | Bool | Same as above |
| `addMemberIdFilter(?Member)` | 95-103 | Single member | `WHERE member_id = $id` |
| `addMemberIdsFilter(?array)` | 108-116 | Array of UUIDs | `WHERE member_id IN (...)` |
| `addBillableFilter(?string)` | 118-132 | String 'true'/'false' | `WHERE billable = $val` |
| `addBillable(?bool)` | 134-142 | Bool | Same as above |
| `addClientIdsFilter(?array)` | 147-155 | Array of UUIDs | `WHERE client_id IN (...)` |
| `addProjectIdsFilter(?array)` | 160-168 | Array of UUIDs | `WHERE project_id IN (...)` |
| `addTagIdsFilter(?array)` | 173-185 | Array of UUIDs | `WHERE tags @> $tagId` (JSONB) |
| `addTaskIdsFilter(?array)` | 190-198 | Array of UUIDs | `WHERE task_id IN (...)` |

### 5.3 New Method: `addTeamIdsFilter`

Per AMD-10 in the PRD, the new filter should follow the exact same pattern:

```php
/**
 * @param  array<string>|null  $teamIds
 */
public function addTeamIdsFilter(?array $teamIds): self
{
    if ($teamIds === null) {
        return $this;
    }
    // Filter time entries where the project is assigned to one of the specified teams
    $this->builder->whereHas('project.teamProjects', function (Builder $builder) use ($teamIds): void {
        $builder->whereIn('team_id', $teamIds);
    });

    return $this;
}
```

**Insertion point**: After `addTaskIdsFilter()` at line 198, before `get()` at line 203.

**Note**: This filter works through the `project` relationship on TimeEntry, then through the `teamProjects` relationship on Project. It requires:
- TimeEntry has `project()` relationship (already exists)
- Project has `teamProjects()` relationship (new, to be added)

### 5.4 Usage Sites

TimeEntryFilter is instantiated in **two methods** in TimeEntryController:

1. **`getTimeEntriesQuery()`** -- Lines 183-211
2. **`getTimeEntriesAggregateQuery()`** -- Lines 549-567

Both follow the same pattern:

```php
$filter = new TimeEntryFilter($timeEntriesQuery);
$filter->addStartFilter($request->input('start'));
$filter->addEndFilter($request->input('end'));
$filter->addActiveFilter($request->input('active'));
$filter->addMemberIdFilter($member);
$filter->addMemberIdsFilter($request->input('member_ids'));
$filter->addProjectIdsFilter($request->input('project_ids'));
$filter->addTagIdsFilter($request->input('tag_ids'));
$filter->addTaskIdsFilter($request->input('task_ids'));
$filter->addClientIdsFilter($request->input('client_ids'));
$filter->addBillableFilter($request->input('billable'));
// ADD: $filter->addTeamIdsFilter($request->input('team_ids'));
return $filter->get();
```

Both methods need the new `addTeamIdsFilter()` call added after `addBillableFilter()`.

---

## 6. Controllers Requiring Modification

### 6.1 ProjectController

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/ProjectController.php`

**Method**: `index()` (Lines 44-68)

```php
public function index(Organization $organization, ProjectIndexRequest $request): ProjectCollection
{
    $this->checkPermission($organization, 'projects:view');
    $canViewAllProjects = $this->hasPermission($organization, 'projects:view:all');
    $user = $this->user();

    $projectsQuery = Project::query()
        ->whereBelongsTo($organization, 'organization');

    if (! $canViewAllProjects) {
        $projectsQuery->visibleByEmployee($user);    // <-- line 54
    }
    // ... archived filter ...
    $projects = $projectsQuery->paginate(config('app.pagination_per_page_default'));
    // ...
}
```

**Modification point**: After line 55 (the `visibleByEmployee` call), inject team scoping:

```php
if ($organization->enable_team_scoping && !$canViewAllProjects) {
    $teamScopeService->applyTeamScopeToProjects($projectsQuery, $this->member($organization));
}
```

Note that `$canViewAllProjects` already provides the Admin/Owner bypass. If an admin has `projects:view:all`, team scoping is not applied.

### 6.2 ClientController

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/ClientController.php`

**Method**: `index()` (Lines 38-62)

```php
public function index(Organization $organization, ClientIndexRequest $request): ClientCollection
{
    $this->checkPermission($organization, 'clients:view');
    $canViewAllClients = $this->hasPermission($organization, 'clients:view:all');
    $user = $this->user();

    $clientsQuery = Client::query()
        ->whereBelongsTo($organization, 'organization')
        ->orderBy('created_at', 'desc');

    if (! $canViewAllClients) {
        $clientsQuery->visibleByEmployee($user);    // <-- line 49
    }
    // ... archived filter ...
}
```

**Modification point**: After line 50, inject team scoping:

```php
if ($organization->enable_team_scoping && !$canViewAllClients) {
    $teamScopeService->applyTeamScopeToClients($clientsQuery, $this->member($organization));
}
```

### 6.3 TimeEntryController

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/TimeEntryController.php`

**Method 1**: `getTimeEntriesQuery()` (Lines 183-211)

Used by: `index()` (line 129), `indexExport()` (line 220+)

```php
private function getTimeEntriesQuery(Organization $organization, TimeEntryIndexRequest|TimeEntryIndexExportRequest $request, ?Member $member, bool $canAccessPremiumFeatures): Builder
{
    // ... setup ...
    $filter = new TimeEntryFilter($timeEntriesQuery);
    $filter->addStartFilter($request->input('start'));
    // ... all current filters ...
    $filter->addBillableFilter($request->input('billable'));
    // ADD: $filter->addTeamIdsFilter($request->input('team_ids'));

    return $filter->get();
}
```

**Method 2**: `getTimeEntriesAggregateQuery()` (Lines 549-567)

Used by: `aggregate()`, `aggregateExport()`

```php
private function getTimeEntriesAggregateQuery(Organization $organization, TimeEntryAggregateRequest|TimeEntryAggregateExportRequest|TimeEntryIndexExportRequest $request, ?Member $member): Builder
{
    // ... setup ...
    $filter = new TimeEntryFilter($timeEntriesQuery);
    // ... all current filters ...
    $filter->addBillableFilter($request->input('billable'));
    // ADD: $filter->addTeamIdsFilter($request->input('team_ids'));

    return $filter->get();
}
```

**Request classes requiring `team_ids` validation**:
- `app/Http/Requests/V1/TimeEntry/TimeEntryIndexRequest.php`
- `app/Http/Requests/V1/TimeEntry/TimeEntryAggregateRequest.php`
- `app/Http/Requests/V1/TimeEntry/TimeEntryAggregateExportRequest.php`
- `app/Http/Requests/V1/TimeEntry/TimeEntryIndexExportRequest.php`

### 6.4 TaskController

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/TaskController.php`

**Method**: `index()` (Lines 60-80+)

```php
public function index(Organization $organization, TaskIndexRequest $request): TaskCollection
{
    $this->checkPermission($organization, 'tasks:view');
    $canViewAllTasks = $this->hasPermission($organization, 'tasks:view:all');
    $user = $this->user();

    $query = Task::query()
        ->whereBelongsTo($organization, 'organization');
    if ($projectId !== null) {
        $query->where('project_id', '=', $projectId);
    }
    if (! $canViewAllTasks) {
        $query->visibleByEmployee($user);       // <-- delegates to Project::visibleByEmployee
    }
}
```

**Analysis**: Per AMD-07, tasks inherit team scoping through their parent project. Since `Task::visibleByEmployee()` (line 150-156) calls `Project::visibleByEmployee()`, if the `TeamScopeService` wraps the project visibility check, tasks will be automatically filtered.

**However**, if the team scoping is applied separately from `visibleByEmployee` (as an additional WHERE clause in controllers), then the TaskController also needs a separate team scoping call:

```php
if ($organization->enable_team_scoping && !$canViewAllTasks) {
    // Apply team scoping through the project relationship
    $query->whereHas('project.teamProjects', function (Builder $builder) use ($member) {
        $builder->whereIn('team_id', $teamScopeService->getUserTeamIds($member));
    });
}
```

### 6.5 ChartController

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/ChartController.php` (190 lines)

All chart methods delegate to `DashboardService`. The controller itself does not need modification; the changes go into `DashboardService`.

| Method | Lines | Permission | Delegates To | Team Scoping Needed |
|--------|-------|-----------|--------------|---------------------|
| `weeklyProjectOverview()` | 25-33 | `charts:view:own` | `DashboardService::weeklyProjectOverview()` | No (user-specific) |
| `latestTasks()` | 44-52 | `charts:view:own` | `DashboardService::latestTasks()` | No (user-specific) |
| `lastSevenDays()` | 63-71 | `charts:view:own` | `DashboardService::lastSevenDays()` | No (user-specific) |
| `latestTeamActivity()` | 82-89 | `charts:view:all` | `DashboardService::latestTeamActivity()` | **YES** |
| `dailyTrackedHours()` | 100-108 | `charts:view:own` | `DashboardService::getDailyTrackedHours()` | No (user-specific) |
| `totalWeeklyTime()` | 119-127 | `charts:view:own` | `DashboardService::totalWeeklyTime()` | No (user-specific) |
| `totalWeeklyBillableTime()` | 138-146 | `charts:view:own` | `DashboardService::totalWeeklyBillableTime()` | No (user-specific) |
| `totalWeeklyBillableAmount()` | 157-170 | `charts:view:own` | `DashboardService::totalWeeklyBillableAmount()` | No (user-specific) |
| `weeklyHistory()` | 181-189 | `charts:view:own` | `DashboardService::getWeeklyHistory()` | No (user-specific) |

Only `latestTeamActivity()` requires team scoping because it shows data across all members in the organization.

### 6.6 ReportController

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/ReportController.php`

**Method**: `store()` (Lines 76-124)

The report store method builds a `ReportPropertiesDto` from request input:

```php
$properties = new ReportPropertiesDto;
// ... existing property assignments ...
$properties->setMemberIds($request->input('properties.member_ids', null));
$properties->setClientIds($request->input('properties.client_ids', null));
$properties->setProjectIds($request->input('properties.project_ids', null));
$properties->setTagIds($request->input('properties.tag_ids', null));
$properties->setTaskIds($request->input('properties.task_ids', null));
// ADD: $properties->setTeamIds($request->input('properties.team_ids', null));
```

This requires changes to:
- `ReportPropertiesDto` -- add `$teamIds` property and `setTeamIds()` method
- `ReportStoreRequest` -- add `properties.team_ids` validation rule

### 6.7 OrganizationController

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/OrganizationController.php`

**Method**: `update()` (Lines 39-82) -- where `enable_team_scoping` toggle is added:

```php
public function update(Organization $organization, OrganizationUpdateRequest $request, BillableRateService $billableRateService): OrganizationResource
{
    $this->checkPermission($organization, 'organizations:update');

    if ($request->getName() !== null) {
        $organization->name = $request->getName();
    }
    if ($request->getEmployeesCanSeeBillableRates() !== null) {
        $organization->employees_can_see_billable_rates = $request->getEmployeesCanSeeBillableRates();
    }
    // ... other boolean flags ...
    if ($request->getPreventOverlappingTimeEntries() !== null) {
        $organization->prevent_overlapping_time_entries = $request->getPreventOverlappingTimeEntries();
    }
    // ADD:
    // if ($request->getEnableTeamScoping() !== null) {
    //     $organization->enable_team_scoping = $request->getEnableTeamScoping();
    // }

    $organization->save();
    // ...
}
```

### 6.8 Summary of All Controller Changes

| Controller | Method | File Line | Change Type |
|-----------|--------|-----------|-------------|
| ProjectController | `index()` | 44-68 | Add team scoping after `visibleByEmployee` |
| ClientController | `index()` | 38-62 | Add team scoping after `visibleByEmployee` |
| TimeEntryController | `getTimeEntriesQuery()` | 183-211 | Add `addTeamIdsFilter()` call |
| TimeEntryController | `getTimeEntriesAggregateQuery()` | 549-567 | Add `addTeamIdsFilter()` call |
| TaskController | `index()` | 60-80 | Add team scoping via project relationship |
| ChartController | `latestTeamActivity()` | 82-89 | Indirect (via DashboardService) |
| ReportController | `store()` | 76-124 | Add `team_ids` to DTO construction |
| OrganizationController | `update()` | 39-82 | Add `enable_team_scoping` field |
| **TeamController** | All CRUD | **NEW FILE** | New controller for team management |

---

## 7. Organization Settings Pattern

### 7.1 How Boolean Flags Are Added

The pattern for adding a new boolean organization setting has four layers.

**Layer 1: Migration**

**File**: `/home/keven/Documents/solidtime-analysis/database/migrations/2024_10_01_143608_add_employees_can_see_billable_rates_to_organizations_table.php`

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->boolean('employees_can_see_billable_rates')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn('employees_can_see_billable_rates');
        });
    }
};
```

For `enable_team_scoping`, the migration follows the same pattern with `->default(false)`.

**Layer 2: Model Cast**

**File**: `/home/keven/Documents/solidtime-analysis/app/Models/Organization.php` (Lines 69-81)

```php
protected $casts = [
    'name' => 'string',
    'personal_team' => 'boolean',
    'currency' => 'string',
    'employees_can_see_billable_rates' => 'boolean',
    'employees_can_manage_tasks' => 'boolean',
    'prevent_overlapping_time_entries' => 'boolean',
    // ADD: 'enable_team_scoping' => 'boolean',
    // ...
];
```

Also add to the PHPDoc block (lines 30-53):

```php
/**
 * ...
 * @property bool $enable_team_scoping
 * ...
 */
```

**Layer 3: Request Validation**

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/Organization/OrganizationUpdateRequest.php`

```php
// Add to rules() (after line 47):
'enable_team_scoping' => [
    'boolean',
],

// Add getter method (after line 116):
public function getEnableTeamScoping(): ?bool
{
    return $this->has('enable_team_scoping') ? $this->boolean('enable_team_scoping') : null;
}
```

**Layer 4: API Resource**

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Resources/V1/Organization/OrganizationResource.php` (Lines 41-75)

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->resource->id,
        'name' => $this->resource->name,
        // ...
        'employees_can_see_billable_rates' => $this->resource->employees_can_see_billable_rates,
        'employees_can_manage_tasks' => $this->resource->employees_can_manage_tasks,
        'prevent_overlapping_time_entries' => $this->resource->prevent_overlapping_time_entries,
        // ADD: 'enable_team_scoping' => $this->resource->enable_team_scoping,
        // ...
    ];
}
```

### 7.2 How Settings Reach the Frontend

Two paths exist for settings data to reach Vue components:

1. **API response** -- When the frontend calls `GET /api/v1/organizations/{organization}`, `OrganizationResource::toArray()` serializes the data. The Pinia store stores this.

2. **Inertia props** -- For the `Teams/Show` page (Organization Settings), data is passed via Inertia rendering in `JetstreamServiceProvider.php` lines 292-335. The `enable_team_scoping` field must be added to this Inertia data array as well.

---

## 8. Pivot Table Patterns

### 8.1 Existing Pivot: ProjectMember

**File**: `/home/keven/Documents/solidtime-analysis/app/Models/ProjectMember.php` (85 lines)

This is the template for all new team pivot models.

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\CustomAuditable;
use App\Models\Concerns\HasUuids;
use Database\Factories\ProjectMemberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property string $id
 * @property int|null $billable_rate
 * @property string $project_id
 * @property string $member_id
 * @property string $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project $project
 * @property-read Member $member
 * @property-read User $user
 *
 * @method static Builder<ProjectMember> whereBelongsToOrganization(Organization $organization)
 * @method static ProjectMemberFactory factory()
 */
class ProjectMember extends Model implements AuditableContract
{
    use CustomAuditable;

    /** @use HasFactory<ProjectMemberFactory> */
    use HasFactory;

    use HasUuids;

    protected $casts = [
        'billable_rate' => 'int',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /** @deprecated Use member relationship instead */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function scopeWhereBelongsToOrganization(Builder $builder, Organization $organization): void
    {
        $builder->whereHas('project', static function (Builder $query) use ($organization): void {
            $query->whereBelongsTo($organization, 'organization');
        });
    }
}
```

### 8.2 Key Conventions Observed

From `ProjectMember`, the pivot model conventions are:

1. **UUID primary key** via `HasUuids` trait
2. **Timestamps** (`created_at`, `updated_at`) included
3. **CustomAuditable** trait for audit logging
4. **HasFactory** trait for testing
5. **BelongsTo** relationships to both sides of the pivot
6. **Organization scope** via `scopeWhereBelongsToOrganization` (optional, for querying pivots by org)
7. **PHPDoc annotations** for all properties
8. **No $fillable/$guarded** -- uses explicit property setting

### 8.3 Template for TeamMember Pivot

Following the pattern:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\CustomAuditable;
use App\Models\Concerns\HasUuids;
use Database\Factories\TeamMemberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Note: This is solidtime's Team (sub-org group), not Jetstream's Team (which maps to Organization)
 *
 * @property string $id
 * @property string $team_id
 * @property string $member_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Member $member
 *
 * @method static TeamMemberFactory factory()
 */
class TeamMember extends Model implements AuditableContract
{
    use CustomAuditable;

    /** @use HasFactory<TeamMemberFactory> */
    use HasFactory;

    use HasUuids;

    protected $casts = [];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function scopeWhereBelongsToOrganization(Builder $builder, Organization $organization): void
    {
        $builder->whereHas('team', static function (Builder $query) use ($organization): void {
            $query->whereBelongsTo($organization, 'organization');
        });
    }
}
```

The same pattern applies to `TeamProject` (`team_id`, `project_id`) and `TeamClient` (`team_id`, `client_id`).

### 8.4 Member Model -- Relationship Addition Point

**File**: `/home/keven/Documents/solidtime-analysis/app/Models/Member.php` (80 lines)

The Member model extends `JetstreamMembership` and currently has:

```php
public function user(): BelongsTo            // line 52
public function organization(): BelongsTo    // line 60
public function timeEntries(): HasMany       // line 68
public function projectMembers(): HasMany    // line 76
```

**Add after line 79**:

```php
/**
 * @return HasMany<TeamMember, $this>
 */
public function teamMembers(): HasMany
{
    return $this->hasMany(TeamMember::class, 'member_id');
}

/**
 * @return BelongsToMany<Team, $this>
 */
public function teams(): BelongsToMany
{
    return $this->belongsToMany(Team::class, 'team_members', 'member_id', 'team_id')
        ->withTimestamps();
}
```

---

## 9. Dashboard and Chart Query Paths

### 9.1 DashboardService Overview

**File**: `/home/keven/Documents/solidtime-analysis/app/Service/DashboardService.php`

All chart data is produced by `DashboardService`, which is called from `ChartController`.

### 9.2 Method-by-Method Analysis

| Method | Lines | Query Pattern | User-Specific | Team Scoping |
|--------|-------|---------------|---------------|-------------|
| `getDailyTrackedHours()` | ~138-174 | `WHERE user_id = $user->getKey()` | Yes | Not needed |
| `getWeeklyHistory()` | ~181-215 | `WHERE user_id = $user->getKey()` | Yes | Not needed |
| `totalWeeklyTime()` | ~217-232 | `WHERE user_id = $user->getKey()` | Yes | Not needed |
| `totalWeeklyBillableTime()` | ~234-250 | `WHERE user_id = $user->getKey()` | Yes | Not needed |
| `totalWeeklyBillableAmount()` | ~255-280 | `WHERE user_id = $user->getKey()` | Yes | Not needed |
| `weeklyProjectOverview()` | 285-338 | `WHERE user_id = $user->getKey()` | Yes | Optional (P2) |
| **`latestTeamActivity()`** | **345-376** | `WHERE organization_id = $org` | **No** | **REQUIRED (P0)** |
| `latestTasks()` | 383-416 | `WHERE user_id = $user->getKey()` | Yes | Not needed |
| `lastSevenDays()` | ~423-465 | `WHERE user_id = $user->getKey()` | Yes | Not needed |

### 9.3 Critical Method: `latestTeamActivity()` (Lines 345-376)

```php
public function latestTeamActivity(Organization $organization): array
{
    $timeEntries = TimeEntry::query()
        ->select(DB::raw('distinct on (member_id) member_id, description, id, task_id, start, "end"'))
        ->whereBelongsTo($organization, 'organization')
        ->orderBy('member_id')
        ->orderBy('start', 'desc')
        ->with([
            'member' => [
                'user',
            ],
        ])
        ->get()
        ->sortByDesc('start')
        ->slice(0, 4);
    // ...
}
```

**Why this needs team scoping**: This method returns the 4 most recently active members across the entire organization. When team scoping is enabled, managers should only see activity from members in their own teams.

**Required modification**: The method signature needs to accept a `Member` parameter (or the `TeamScopeService` should be injected), and the query should filter by team membership:

```php
public function latestTeamActivity(Organization $organization, ?Member $currentMember = null, ?TeamScopeService $teamScopeService = null): array
{
    $query = TimeEntry::query()
        ->select(DB::raw('distinct on (member_id) member_id, description, id, task_id, start, "end"'))
        ->whereBelongsTo($organization, 'organization');

    // Apply team scoping if enabled
    if ($teamScopeService !== null && $currentMember !== null) {
        if ($organization->enable_team_scoping && !$teamScopeService->hasViewAllPermission($currentMember)) {
            $teamIds = $teamScopeService->getUserTeamIds($currentMember);
            $query->whereIn('member_id', function ($subquery) use ($teamIds) {
                $subquery->select('member_id')
                    ->from('team_members')
                    ->whereIn('team_id', $teamIds);
            });
        }
    }

    $timeEntries = $query
        ->orderBy('member_id')
        ->orderBy('start', 'desc')
        ->with(['member' => ['user']])
        ->get()
        ->sortByDesc('start')
        ->slice(0, 4);
    // ...
}
```

### 9.4 Optional: `weeklyProjectOverview()` (Lines 285-338)

This method is user-specific (filtered by `user_id`), so time entries are already scoped to the current user. However, the project lookup at lines 300-305 fetches projects by ID from the organization without team filtering:

```php
$projectsMap = Project::query()
    ->select(['id', 'name', 'color'])
    ->whereBelongsTo($organization, 'organization')
    ->whereIn('id', $projectIds)
    ->get()
    ->keyBy('id');
```

Since the `$projectIds` come from the user's own time entries, this is safe -- the user already has time entries on these projects. Team scoping here is a P2 enhancement, not a correctness requirement.

---

## 10. Data Migration Patterns

### 10.1 Existing Migrations

No existing data migrations (that bulk-transform data) were found in the codebase. All migrations in `database/migrations/` are schema migrations (CREATE TABLE, ALTER TABLE, ADD COLUMN). There are no examples of `chunkById()` or `chunk()` in existing migrations.

### 10.2 Recommended Pattern for Default Team Migration

Based on the codebase conventions and the PRD (TASK-002), the data migration should:

**File**: `database/migrations/2026_03_10_100001_seed_default_teams_for_existing_organizations.php`

```php
<?php

declare(strict_types=1);

use App\Models\Organization;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Only process organizations that do not already have a "Default" team
        Organization::query()
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('teams')
                    ->whereColumn('teams.organization_id', 'organizations.id')
                    ->where('teams.name', 'Default');
            })
            ->chunkById(100, function ($organizations) {
                foreach ($organizations as $organization) {
                    $teamId = Str::uuid()->toString();
                    $now = now();

                    DB::table('teams')->insert([
                        'id' => $teamId,
                        'name' => 'Default',
                        'color' => '#3B82F6',
                        'organization_id' => $organization->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    // Assign all members
                    $members = DB::table('members')
                        ->where('organization_id', $organization->id)
                        ->pluck('id');
                    if ($members->isNotEmpty()) {
                        $teamMembers = $members->map(fn ($memberId) => [
                            'id' => Str::uuid()->toString(),
                            'team_id' => $teamId,
                            'member_id' => $memberId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])->toArray();
                        DB::table('team_members')->insert($teamMembers);
                    }

                    // Assign all projects
                    $projects = DB::table('projects')
                        ->where('organization_id', $organization->id)
                        ->pluck('id');
                    if ($projects->isNotEmpty()) {
                        $teamProjects = $projects->map(fn ($projectId) => [
                            'id' => Str::uuid()->toString(),
                            'team_id' => $teamId,
                            'project_id' => $projectId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])->toArray();
                        DB::table('team_projects')->insert($teamProjects);
                    }

                    // Assign all clients
                    $clients = DB::table('clients')
                        ->where('organization_id', $organization->id)
                        ->pluck('id');
                    if ($clients->isNotEmpty()) {
                        $teamClients = $clients->map(fn ($clientId) => [
                            'id' => Str::uuid()->toString(),
                            'team_id' => $teamId,
                            'client_id' => $clientId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])->toArray();
                        DB::table('team_clients')->insert($teamClients);
                    }
                }
            });
    }

    public function down(): void
    {
        // Delete all teams named "Default" and cascade to pivot tables
        DB::table('teams')->where('name', 'Default')->delete();
    }
};
```

### 10.3 Key Design Decisions in the Migration

1. **`chunkById(100)`** -- Processes organizations in batches of 100 to avoid memory exhaustion
2. **Raw `DB::table()` inserts** -- Avoids Eloquent overhead for bulk operations
3. **Idempotency** -- Checks for existing "Default" team before creating (using `whereNotExists`)
4. **No transaction per organization** -- Individual org failures should not block others; consider wrapping each org in a try/catch
5. **Batch inserts for members/projects/clients** -- Collects all insert data, then does a single `insert()` per entity type per org

### 10.4 Performance Considerations

For organizations with many members/projects/clients (e.g., 10,000+), the `$members->map(...)` pattern could create a very large array. Consider chunking the insert arrays:

```php
foreach ($members->chunk(500) as $memberChunk) {
    $teamMembers = $memberChunk->map(fn ($memberId) => [...])->toArray();
    DB::table('team_members')->insert($teamMembers);
}
```

---

## 11. Blast Radius Assessment

### 11.1 New Files (to be created)

#### Backend -- Models (4 files)

| File | Description |
|------|-------------|
| `app/Models/Team.php` | Team model with org relationship |
| `app/Models/TeamMember.php` | Pivot: team <-> member |
| `app/Models/TeamProject.php` | Pivot: team <-> project |
| `app/Models/TeamClient.php` | Pivot: team <-> client |

#### Backend -- Migrations (6 files)

| File | Description |
|------|-------------|
| `database/migrations/2026_03_10_000001_create_teams_table.php` | Teams table |
| `database/migrations/2026_03_10_000002_create_team_members_table.php` | Team-member pivot |
| `database/migrations/2026_03_10_000003_create_team_projects_table.php` | Team-project pivot |
| `database/migrations/2026_03_10_000004_create_team_clients_table.php` | Team-client pivot |
| `database/migrations/2026_03_10_000005_add_enable_team_scoping_to_organizations_table.php` | Feature flag column |
| `database/migrations/2026_03_10_100001_seed_default_teams_for_existing_organizations.php` | Data migration |

#### Backend -- Factories (4 files)

| File | Description |
|------|-------------|
| `database/factories/TeamFactory.php` | |
| `database/factories/TeamMemberFactory.php` | |
| `database/factories/TeamProjectFactory.php` | |
| `database/factories/TeamClientFactory.php` | |

#### Backend -- Controllers (1 file)

| File | Description |
|------|-------------|
| `app/Http/Controllers/Api/V1/TeamController.php` | Full CRUD + assignment endpoints |

#### Backend -- Request Validators (6 files)

| File | Description |
|------|-------------|
| `app/Http/Requests/V1/Team/TeamStoreRequest.php` | |
| `app/Http/Requests/V1/Team/TeamUpdateRequest.php` | |
| `app/Http/Requests/V1/Team/TeamIndexRequest.php` | |
| `app/Http/Requests/V1/TeamMember/TeamMemberStoreRequest.php` | |
| `app/Http/Requests/V1/TeamProject/TeamProjectStoreRequest.php` | |
| `app/Http/Requests/V1/TeamClient/TeamClientStoreRequest.php` | |

#### Backend -- Resources (2 files)

| File | Description |
|------|-------------|
| `app/Http/Resources/V1/Team/TeamResource.php` | |
| `app/Http/Resources/V1/Team/TeamCollection.php` | |

#### Backend -- Services (1 file)

| File | Description |
|------|-------------|
| `app/Service/TeamScopeService.php` | Central team scoping logic |

#### Backend -- Permissions (1 file)

| File | Description |
|------|-------------|
| `app/Permissions/TeamPermissions.php` | Modular permissions per SF-08 |

#### Backend -- Tests (6+ files)

| File | Description |
|------|-------------|
| `tests/Unit/Endpoint/Api/V1/TeamEndpointTest.php` | CRUD endpoint tests |
| `tests/Unit/Service/TeamScopeServiceTest.php` | Scoping logic tests |
| `tests/Unit/Model/TeamModelTest.php` | Model tests |
| `tests/Unit/Model/TeamMemberModelTest.php` | Pivot model tests |
| `tests/Unit/Model/TeamProjectModelTest.php` | Pivot model tests |
| `tests/Unit/Model/TeamClientModelTest.php` | Pivot model tests |

#### Frontend (8+ files)

| File | Description |
|------|-------------|
| `resources/js/Pages/Teams.vue` | Teams list page (Inertia page) |
| `resources/js/packages/ui/src/Team/TeamList.vue` | Team list component |
| `resources/js/packages/ui/src/Team/TeamForm.vue` | Create/edit form |
| `resources/js/packages/ui/src/Team/TeamMemberAssignment.vue` | Member assignment UI |
| `resources/js/packages/ui/src/Team/TeamProjectAssignment.vue` | Project assignment UI |
| `resources/js/packages/ui/src/Team/TeamClientAssignment.vue` | Client assignment UI |
| `resources/js/utils/useTeam.ts` | Pinia store |
| `resources/js/types/team.d.ts` | TypeScript types |

**Total new files: ~39+**

### 11.2 Modified Files (existing)

#### Backend -- Models (4 files)

| File | Change |
|------|--------|
| `app/Models/Organization.php` | Add `enable_team_scoping` cast, `teams()` relationship, PHPDoc |
| `app/Models/Project.php` | Add `teamProjects()`, `teams()` relationships |
| `app/Models/Client.php` | Add `teamClients()`, `teams()` relationships |
| `app/Models/Member.php` | Add `teamMembers()`, `teams()` relationships |

#### Backend -- Controllers (5 files)

| File | Change |
|------|--------|
| `app/Http/Controllers/Api/V1/ProjectController.php` | Team scoping in `index()` |
| `app/Http/Controllers/Api/V1/ClientController.php` | Team scoping in `index()` |
| `app/Http/Controllers/Api/V1/TimeEntryController.php` | `addTeamIdsFilter()` in 2 methods |
| `app/Http/Controllers/Api/V1/OrganizationController.php` | `enable_team_scoping` in `update()` |
| `app/Http/Controllers/Api/V1/TaskController.php` | Verify team scoping via project chain |

#### Backend -- Services (2 files)

| File | Change |
|------|--------|
| `app/Service/TimeEntryFilter.php` | Add `addTeamIdsFilter()` method |
| `app/Service/DashboardService.php` | Team scoping in `latestTeamActivity()` |

#### Backend -- DTOs (1 file)

| File | Change |
|------|--------|
| `app/Service/Dto/ReportPropertiesDto.php` | Add `$teamIds` property, setter, serialization |

#### Backend -- Requests (5 files)

| File | Change |
|------|--------|
| `app/Http/Requests/V1/Organization/OrganizationUpdateRequest.php` | Add `enable_team_scoping` rule |
| `app/Http/Requests/V1/TimeEntry/TimeEntryIndexRequest.php` | Add `team_ids` validation |
| `app/Http/Requests/V1/TimeEntry/TimeEntryAggregateRequest.php` | Add `team_ids` validation |
| `app/Http/Requests/V1/TimeEntry/TimeEntryAggregateExportRequest.php` | Add `team_ids` validation |
| `app/Http/Requests/V1/TimeEntry/TimeEntryIndexExportRequest.php` | Add `team_ids` validation |

#### Backend -- Resources (4 files)

| File | Change |
|------|--------|
| `app/Http/Resources/V1/Organization/OrganizationResource.php` | Add `enable_team_scoping` field |
| `app/Http/Resources/V1/Member/MemberResource.php` | Add `teams` relationship (optional) |
| `app/Http/Resources/V1/Project/ProjectResource.php` | Add `teams` relationship (optional) |
| `app/Http/Resources/V1/Client/ClientResource.php` | Add `teams` relationship (optional) |

#### Backend -- Providers (1 file)

| File | Change |
|------|--------|
| `app/Providers/JetstreamServiceProvider.php` | Add team permissions, Inertia prop |

#### Backend -- Routes (1 file)

| File | Change |
|------|--------|
| `routes/api.php` | Add team CRUD and assignment routes |

#### Backend -- Tests (3+ files)

| File | Change |
|------|--------|
| `tests/Unit/Endpoint/Api/V1/ProjectEndpointTest.php` | Add team scoping assertions |
| `tests/Unit/Endpoint/Api/V1/ClientEndpointTest.php` | Add team scoping assertions |
| `tests/Unit/Endpoint/Api/V1/TimeEntryEndpointTest.php` | Add `team_ids` filter tests |

#### Frontend (2 files)

| File | Change |
|------|--------|
| `resources/js/Layouts/AppLayout.vue` | Add "Teams" navigation sidebar item |
| `resources/js/Pages/Teams/Show.vue` | Add `enable_team_scoping` toggle |

**Total modified files: ~27+**

### 11.3 Grand Total

| Category | New | Modified | Total |
|----------|-----|----------|-------|
| Backend Models | 4 | 4 | 8 |
| Backend Migrations | 6 | 0 | 6 |
| Backend Factories | 4 | 0 | 4 |
| Backend Controllers | 1 | 5 | 6 |
| Backend Requests | 6 | 5 | 11 |
| Backend Resources | 2 | 4 | 6 |
| Backend Services | 1 | 2 | 3 |
| Backend DTOs | 0 | 1 | 1 |
| Backend Providers | 0 | 1 | 1 |
| Backend Permissions | 1 | 0 | 1 |
| Backend Routes | 0 | 1 | 1 |
| Backend Tests | 6+ | 3+ | 9+ |
| Frontend Pages/Components | 7+ | 2 | 9+ |
| Frontend Store/Types | 2 | 0 | 2 |
| **Total** | **~40** | **~28** | **~68+** |

---

## 12. Risk Assessment

### 12.1 Performance Risks

**Risk: Query Performance Degradation**
- **Impact**: High
- **Likelihood**: Medium
- **Details**: Every team-scoped query adds a JOIN through pivot tables (`team_members`, `team_projects`, `team_clients`). The current `visibleByEmployee` scope already uses `whereHas` (which generates EXISTS subqueries), and team scoping adds another `whereHas` layer.
- **Mitigation**:
  - Composite indexes on all pivot tables: `(team_id, member_id)`, `(team_id, project_id)`, `(team_id, client_id)`
  - Per-request caching of team IDs in `TeamScopeService` using `Cache::store('array')`
  - Benchmark target: `<10% additional latency` with 100+ teams, 1000+ projects
  - Consider converting `whereHas` to `whereIn` with a subquery for better index utilization

**Risk: N+1 Queries on Team Relationships**
- **Impact**: Medium
- **Likelihood**: Medium
- **Details**: If `teams` relationships are eagerly loaded in list endpoints, each project/client/member will trigger additional queries.
- **Mitigation**: Use `withCount('teams')` for list views instead of eager-loading full team data. Only load full team data on detail views.

### 12.2 Data Visibility Risks

**Risk: Team Scoping Not Applied Consistently**
- **Impact**: Critical
- **Likelihood**: Medium
- **Details**: If a single controller endpoint forgets to apply team scoping, employees could see data from other teams. The blast radius analysis identified 5+ controllers that need modification.
- **Mitigation**:
  - Centralize team scoping in `TeamScopeService` (single point of logic)
  - Integration tests that verify every list endpoint respects team boundaries
  - Consider a middleware or query scope macro that auto-applies team scoping
  - Code review checklist for any new endpoint that queries projects/clients/time entries

**Risk: Cross-Organization Team ID Leakage**
- **Impact**: Critical
- **Likelihood**: Low
- **Details**: If `team_ids` filter parameters are not validated against the current organization, a user could pass team IDs from another organization.
- **Mitigation**:
  - Validate all `team_ids` filter parameters to ensure teams belong to the current organization
  - Add `whereHas('team', fn($q) => $q->where('organization_id', $org->id))` to team filter queries

**Risk: Data Migration Creates Inconsistent State**
- **Impact**: High
- **Likelihood**: Medium
- **Details**: If the data migration partially fails (e.g., creates default teams but fails to assign members), some organizations will have teams with no members.
- **Mitigation**:
  - Idempotency checks (skip orgs that already have a "Default" team)
  - Consider wrapping each organization's migration in a DB transaction
  - Validation query after migration: `SELECT o.id FROM organizations o WHERE NOT EXISTS (SELECT 1 FROM teams t WHERE t.organization_id = o.id)`

### 12.3 Backward Compatibility Risks

**Risk: Enabling Team Scoping Breaks Existing Workflows**
- **Impact**: High
- **Likelihood**: Low
- **Details**: When an admin enables `enable_team_scoping`, members who are not assigned to teams will suddenly lose access to all data.
- **Mitigation**:
  - Data migration assigns all existing entities to "Default" team
  - AMD-06 requires new members to be auto-assigned to "Default" team when scoping is enabled
  - UI warning when toggling the feature flag: "All members must be assigned to at least one team"
  - Validation in the toggle handler: ensure all members have at least one team before enabling

**Risk: Existing API Consumers Break on New Required Fields**
- **Impact**: Low
- **Likelihood**: Low
- **Details**: No new fields are required in existing endpoints. `team_ids` is optional in all filter endpoints. `enable_team_scoping` defaults to `false`.
- **Mitigation**: The feature flag approach (SF-07) ensures zero behavioral change until explicitly enabled.

### 12.4 Developer Experience Risks

**Risk: Jetstream Team vs App Team Naming Confusion**
- **Impact**: Low
- **Likelihood**: High
- **Details**: Developers may confuse `App\Models\Team` (sub-org group) with Jetstream's `Team` concept (which maps to Organization). IDE autocompletion may suggest the wrong `Team` class.
- **Mitigation**:
  - Clear PHPDoc comments on the Team model
  - Full namespace imports everywhere: `use App\Models\Team;`
  - Code convention: never `use Laravel\Jetstream\Team` directly
  - AMD-13 in the PRD documents this decision

**Risk: Cross-Feature Dependencies**
- **Impact**: Medium
- **Likelihood**: Medium
- **Details**: Per AMD-12, Features 07 (PTO), 08 (Scheduling), and 09 (Reporting) will need team scoping integration. If the `TeamScopeService` API changes, these features need updates.
- **Mitigation**:
  - Design `TeamScopeService` with a stable public API
  - Document the service interface before other features begin development
  - Teams feature is in Phase 1a (per SF-09), giving time to stabilize before dependent features

### 12.5 ReportPropertiesDto Backward Compatibility

**Risk: Existing persisted reports break when `teamIds` is missing**
- **Impact**: Medium
- **Likelihood**: High
- **Details**: The `ReportPropertiesDto` is serialized as JSON in the `reports` table. Existing reports will not have `teamIds` in their JSON. The `REQUIRED_PROPERTIES` constant (line 77-92) will cause deserialization to fail if `teamIds` is added to it.
- **Mitigation**:
  - Do NOT add `teamIds` to `REQUIRED_PROPERTIES`
  - Handle it as optional in the getter (like `roundingType` and `roundingMinutes` at lines 123-126):
    ```php
    // Note: teamIds was added later so it may be missing in persisted reports
    $dto->teamIds = isset($data->teamIds) ? ReportPropertiesDto::idArrayToCollection($data->teamIds) : null;
    ```

### 12.6 Rollback Plan

If the feature needs to be rolled back after deployment:

1. **Immediate**: Set `enable_team_scoping = false` for all organizations:
   ```sql
   UPDATE organizations SET enable_team_scoping = false;
   ```
   This instantly disables all team scoping logic without removing data.

2. **Code rollback**: Revert to previous commit, re-deploy. The `enable_team_scoping` column remains but is unused.

3. **Full data rollback** (if needed):
   ```sql
   TRUNCATE team_members, team_projects, team_clients CASCADE;
   DELETE FROM teams;
   ALTER TABLE organizations DROP COLUMN IF EXISTS enable_team_scoping;
   ```

---

## Appendix A: Essential Files Quick Reference

### Files to Read Before Implementation

| Priority | File | Why |
|----------|------|-----|
| P0 | `app/Providers/JetstreamServiceProvider.php` (lines 51-62, 78-281, 292-335) | Jetstream binding, permissions, Inertia props |
| P0 | `app/Models/Project.php` (lines 182-190) | `visibleByEmployee` scope -- the primary integration point |
| P0 | `app/Service/TimeEntryFilter.php` (entire file) | Filter builder pattern to extend |
| P0 | `app/Http/Controllers/Api/V1/ProjectController.php` (lines 44-68) | Controller integration pattern |
| P0 | `app/Models/ProjectMember.php` (entire file) | Pivot model template |
| P1 | `app/Models/Client.php` (lines 70-76) | Client visibility scope |
| P1 | `app/Models/Task.php` (lines 150-156) | Task visibility delegation |
| P1 | `app/Http/Controllers/Api/V1/ClientController.php` (lines 38-62) | Client controller pattern |
| P1 | `app/Http/Controllers/Api/V1/TimeEntryController.php` (lines 183-211, 549-567) | Filter usage sites |
| P1 | `app/Service/DashboardService.php` (lines 345-376) | `latestTeamActivity()` method |
| P1 | `app/Http/Resources/V1/Organization/OrganizationResource.php` (lines 41-75) | Settings serialization |
| P2 | `app/Http/Controllers/Api/V1/OrganizationController.php` (lines 39-82) | Settings update pattern |
| P2 | `app/Http/Requests/V1/Organization/OrganizationUpdateRequest.php` (entire file) | Validation pattern |
| P2 | `app/Service/Dto/ReportPropertiesDto.php` (entire file) | Report DTO extension pattern |
| P2 | `app/Models/Organization.php` (entire file) | Organization model structure |
| P2 | `app/Models/Member.php` (entire file) | Member model structure |

---

**Analysis Complete**: 2026-02-06
**Total Source Files Analyzed**: 25+
**Estimated Blast Radius**: ~68+ files (40 new, 28 modified)
