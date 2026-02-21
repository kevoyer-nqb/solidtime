# Codebase Analysis: Feature 15 -- Attendance & Overtime Tracking

**Date**: 2026-02-09
**Branch analyzed**: `main`
**Target feature branch**: `feature/attendance-overtime`
**PRD reference**: `.features/15-attendance-overtime/PRD.md`

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Existing Time Entry Infrastructure](#2-existing-time-entry-infrastructure)
3. [Existing Member and Organization Models](#3-existing-member-and-organization-models)
4. [Existing Enum Patterns](#4-existing-enum-patterns)
5. [Existing Scheduled Commands Pattern](#5-existing-scheduled-commands-pattern)
6. [Controller Patterns](#6-controller-patterns)
7. [Service Layer Patterns](#7-service-layer-patterns)
8. [Request Validation Patterns](#8-request-validation-patterns)
9. [Permission System](#9-permission-system)
10. [Frontend Store Patterns](#10-frontend-store-patterns)
11. [Frontend Component Patterns](#11-frontend-component-patterns)
12. [Route Registration Patterns](#12-route-registration-patterns)
13. [Navigation Sidebar](#13-navigation-sidebar)
14. [Feature 06 (Kiosk) Integration Analysis](#14-feature-06-kiosk-integration-analysis)
15. [Feature 07 (PTO) Integration Analysis](#15-feature-07-pto-integration-analysis)
16. [Data Flow Diagrams](#16-data-flow-diagrams)
17. [File Modification Risk Assessment](#17-file-modification-risk-assessment)

---

## 1. Executive Summary

The Solidtime codebase provides a well-established foundation for the Attendance & Overtime feature. All architectural patterns needed (models, controllers, services, request validation, enums, Pinia stores, Vue components, route registration, navigation, scheduled commands) already exist in the codebase and are documented in CLAUDE.md.

**Key findings**:

- **4 new database tables required** -- `work_schedule_policies`, `member_work_schedules`, `overtime_rules`, `attendance_records`. All reference only existing `members` and `organizations` tables.
- **No modifications to existing tables** -- attendance computation reads from `time_entries` but does not alter its schema.
- **Existing enum pattern is well-established** -- `AttendanceStatus` and `OvertimeRuleType` follow the string-backed enum pattern used by `Role`, `ApprovalStatus`, and `Weekday`.
- **Scheduled command infrastructure exists** -- `app/Console/Kernel.php` registers commands with `when()` guards and various schedule frequencies. The `attendance:compute` command follows this pattern.
- **Permission system supports modular registration** -- `app/Permissions/CorePermissions.php` follows the SF-08 modular pattern. `AttendancePermissions.php` will follow the same approach.
- **Service layer pattern is consistent** -- stateless services accept model instances, return plain arrays, are injected via constructor DI. `AttendanceService` and `WorkScheduleService` follow this exactly.
- **TimeEntry query patterns are well-documented** -- `TimeEntryAggregationService` demonstrates the PostgreSQL `EXTRACT(EPOCH FROM ...)` aggregation pattern that attendance computation will use.
- **Feature 06 and Feature 07 are not yet on `main`** -- soft integration via `class_exists()` ensures graceful degradation.
- **Merge conflict risk is LOW** -- the feature creates primarily new files and appends to shared files.

---

## 2. Existing Time Entry Infrastructure

### 2.1 TimeEntry Model

**File**: `app/Models/TimeEntry.php`

Key characteristics relevant to attendance computation:

- Uses `HasUuids` trait (UUID primary keys)
- Uses `CustomAuditable` trait (audit logging on all mutations)
- `start` and `end` are Carbon datetime columns (stored in UTC)
- `end` is nullable (`null` = running timer, excluded from attendance computation)
- `member_id` (UUID FK) links to the member whose attendance is tracked
- `organization_id` (UUID FK) used for scoping
- Relationships: `belongsTo` Member, Organization, Project, Task, User, Client

**Attendance-relevant columns**:
```
start           TIMESTAMP NOT NULL    -- UTC datetime, used to assign entry to a calendar day
end             TIMESTAMP NULL        -- NULL = running timer (excluded from attendance)
member_id       UUID NOT NULL         -- FK to members (target of attendance tracking)
organization_id UUID NOT NULL         -- FK to organizations (scoping)
```

**Model events**: The `TimeEntry` model uses `CustomAuditable` for audit logging. Attendance will add a `saved` and `deleted` observer/hook to mark `AttendanceRecord` rows as stale when entries change. This can be implemented via the model's `boot()` method or a dedicated `TimeEntryObserver`, depending on the team's preference. The model currently does not have a `boot()` override, so either approach is viable.

### 2.2 Existing Query Patterns

**TimeEntryController** (`app/Http/Controllers/Api/V1/TimeEntryController.php`):
```php
// Standard time entry listing with filters
$timeEntries = TimeEntry::query()
    ->whereBelongsTo($organization, 'organization')
    ->where('user_id', $member->user_id)
    ->whereNotNull('end')
    ->orderBy('start', 'desc')
    ->paginate();
```

**TimeEntryAggregationService** (`app/Service/TimeEntryAggregationService.php`):
```php
// Aggregation pattern using raw SQL for performance
$result = TimeEntry::query()
    ->selectRaw('round(sum(extract(epoch from ("end" - start)))) as aggregate')
    ->whereBelongsTo($organization, 'organization')
    ->whereNotNull('end')
    ->first();
```

The attendance service should follow the aggregation service pattern for computing actual seconds worked per day: use PostgreSQL `EXTRACT(EPOCH FROM ...)` server-side rather than loading all entries into PHP.

### 2.3 Key Query Pattern for Attendance

The attendance computation needs to query time entries for a specific member on a specific calendar day, respecting the policy's timezone:

```sql
SELECT *
FROM time_entries
WHERE member_id = ?
  AND end IS NOT NULL
  AND date(start AT TIME ZONE ?) = ?
ORDER BY start ASC
```

This pattern is consistent with how the existing `TimeEntryAggregationService` handles timezone offsets:
```php
$timezoneShift = app(TimezoneService::class)->getShiftFromUtc(new CarbonTimeZone($timezone));
if ($timezoneShift > 0) {
    $dateWithTimeZone = 'start + INTERVAL \'' . $timezoneShift . ' second\'';
}
```

The attendance service should use the same `TimezoneService` approach for consistency.

### 2.4 Existing Index Analysis

The `time_entries` table currently has indexes on:
- `id` (primary key)
- `organization_id` (FK index)
- `user_id` (FK index)
- `member_id` (FK index)
- `project_id` (FK index)
- `task_id` (FK index)

The Feature 00 (Timesheet Grid) added a composite index:
```
time_entries_timesheet_lookup_index (organization_id, user_id, start, end)
```

For attendance queries, the existing `member_id` index is sufficient for the per-member daily query. A composite index `(member_id, start)` could improve performance for date-range queries, but this should be assessed via `EXPLAIN ANALYZE` during ATT-054 (index optimization task).

---

## 3. Existing Member and Organization Models

### 3.1 Member Model

**File**: `app/Models/Member.php`

Key properties for attendance:
- `id` (UUID PK) -- target of attendance tracking
- `organization_id` (UUID FK) -- scoping
- `user_id` (UUID FK) -- links to user account
- `role` (string) -- maps to `Role` enum, determines permission level
- `weekly_capacity` (integer, seconds, default 144,000 = 40h) -- fallback for expected hours when no work schedule policy is configured
- `billable_rate` (integer, cents/hour) -- used for overtime cost calculations in reports

**Relationships**:
- `organization()` -- BelongsTo Organization
- `user()` -- BelongsTo User
- `timeEntries()` -- HasMany TimeEntry

The attendance feature will read `member.weekly_capacity` as a fallback to derive a system-default work schedule when no `WorkSchedulePolicy` is configured. The formula is: `daily_expected = weekly_capacity / 5` (assuming 5-day work week).

New relationships to add (on the `Member` model or via the new models):
- `memberWorkSchedules()` -- HasMany MemberWorkSchedule
- `attendanceRecords()` -- HasMany AttendanceRecord

**Impact**: The `Member` model file will NOT be modified. The reverse relationships are defined on the new models (`MemberWorkSchedule::member()`, `AttendanceRecord::member()`).

### 3.2 Organization Model

**File**: `app/Models/Organization.php`

Key properties for attendance:
- `id` (UUID PK) -- scoping for all attendance tables
- `default_weekly_capacity` (integer, seconds, default 144,000 = 40h) -- org-level fallback
- `name` -- displayed in reports

The organization model already supports the concept of weekly capacity at both the organization and member level. The attendance feature layers work schedule policies on top of this, using `default_weekly_capacity` as the ultimate fallback.

**Impact**: The `Organization` model file will NOT be modified.

---

## 4. Existing Enum Patterns

### 4.1 String-Backed Enums

The codebase uses PHP 8.1+ string-backed enums consistently:

**`app/Enums/Role.php`**:
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

**`app/Enums/ApprovalStatus.php`**:
```php
enum ApprovalStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case ChangesRequested = 'changes_requested';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
}
```

**`app/Enums/Weekday.php`**:
```php
enum Weekday: string
{
    use LaravelEnumHelper;

    case Monday = 'monday';
    // ... through Sunday

    public function carbonWeekDay(): int { /* Carbon day constant mapping */ }
}
```

### 4.2 Pattern for New Enums

Both `AttendanceStatus` and `OvertimeRuleType` follow this exact pattern:
- `declare(strict_types=1)` at top
- String-backed values (snake_case)
- Located in `app/Enums/`
- Used as Eloquent `$casts` values

The `Weekday` enum is especially relevant because it provides the `carbonWeekDay()` helper that maps enum values to Carbon day constants. The `AttendanceService` will use this helper to determine which day's expected seconds to look up from a `WorkSchedulePolicy`.

### 4.3 Enum Usage in Models

Enums are cast in model `$casts` arrays:
```php
// From Organization model
protected $casts = [
    'number_format' => NumberFormat::class,
    'currency_format' => CurrencyFormat::class,
];
```

The `AttendanceRecord` model will cast `status` to `AttendanceStatus::class`, and the `OvertimeRule` model will cast `rule_type` to `OvertimeRuleType::class`.

---

## 5. Existing Scheduled Commands Pattern

### 5.1 Console Kernel

**File**: `app/Console/Kernel.php`

The kernel registers scheduled commands with configuration-based `when()` guards:

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('time-entry:send-still-running-mails')
        ->when(fn (): bool => config('scheduling.tasks.time_entry_send_still_running_mails'))
        ->everyTenMinutes();

    $schedule->command('auth:send-mails-expiring-api-tokens')
        ->when(fn (): bool => config('scheduling.tasks.auth_send_mails_expiring_api_tokens'))
        ->everyTenMinutes();

    $schedule->command('self-host:database-consistency')
        ->when(fn (): bool => config('scheduling.tasks.self_hosting_database_consistency'))
        ->everySixHours();
}
```

**Pattern for `attendance:compute`**:
```php
$schedule->command('attendance:compute')
    ->when(fn (): bool => config('scheduling.tasks.attendance_compute'))
    ->dailyAt('02:00');

$schedule->command('attendance:compute --recompute')
    ->when(fn (): bool => config('scheduling.tasks.attendance_compute'))
    ->everyThirtyMinutes();
```

### 5.2 Command Autoloading

Commands are autoloaded from `app/Console/Commands/`:
```php
protected function commands(): void
{
    $this->load(__DIR__.'/Commands');
}
```

The `ComputeAttendanceCommand` will be placed in `app/Console/Commands/ComputeAttendanceCommand.php` and automatically discovered.

### 5.3 Configuration

A new config key `scheduling.tasks.attendance_compute` needs to be added (defaulting to `true`). This follows the pattern of `scheduling.tasks.time_entry_send_still_running_mails`.

---

## 6. Controller Patterns

### 6.1 Base Controller

**File**: `app/Http/Controllers/Api/V1/Controller.php`

All API controllers extend this base, which provides:
```php
protected PermissionStore $permissionStore;  // Injected via constructor

protected function checkPermission(Organization $organization, string $permission): void
protected function checkAnyPermission(Organization $organization, array $permissions): void
protected function hasPermission(Organization $organization, string $permission): bool
protected function canAccessPremiumFeatures(Organization $organization): bool
```

The `user()` and `member()` helpers are defined in a higher-level controller. The attendance controllers will use `checkPermission()` for write endpoints and `checkAnyPermission()` for read endpoints, consistent with existing patterns.

### 6.2 CRUD Controller Pattern

**Example from `TagController`** (representative CRUD pattern):
```php
class TagController extends Controller
{
    public function index(Organization $organization): JsonResponse
    {
        $this->checkPermission($organization, 'tags:view');
        $tags = Tag::query()
            ->whereBelongsTo($organization, 'organization')
            ->orderBy('name')
            ->get();
        return response()->json(['data' => TagResource::collection($tags)]);
    }

    public function store(Organization $organization, TagStoreRequest $request): JsonResponse
    {
        $this->checkPermission($organization, 'tags:create');
        $tag = new Tag();
        $tag->name = $request->input('name');
        $tag->organization()->associate($organization);
        $tag->save();
        return response()->json(['data' => new TagResource($tag)], 201);
    }

    public function update(Organization $organization, Tag $tag, TagUpdateRequest $request): JsonResponse
    {
        $this->checkPermission($organization, 'tags:update');
        $tag->name = $request->input('name');
        $tag->save();
        return response()->json(['data' => new TagResource($tag)]);
    }

    public function destroy(Organization $organization, Tag $tag): JsonResponse
    {
        $this->checkPermission($organization, 'tags:delete');
        $tag->delete();
        return response()->noContent();
    }
}
```

The `WorkScheduleController` and `OvertimeRuleController` follow this exact CRUD pattern. The `AttendanceController` follows the reporting pattern (similar to `ChartController` -- service injection, read-only query methods).

### 6.3 Organization Scoping

Organization is automatically resolved via route model binding from the `{organization}` route parameter. All queries must include `whereBelongsTo($organization, 'organization')` or equivalent `where('organization_id', $organization->id)` clause.

### 6.4 Route Model Binding for Nested Resources

For endpoints like `GET /work-schedules/{workSchedule}`, Laravel's route model binding automatically resolves the `WorkSchedulePolicy` model. The controller must verify the policy belongs to the organization:

```php
if ($workSchedule->organization_id !== $organization->id) {
    throw new AuthorizationException();
}
```

This pattern is used by existing controllers like `ProjectController::show()`.

---

## 7. Service Layer Patterns

### 7.1 Service Conventions

- **Location**: `app/Service/`
- **Stateless classes** (no constructor state beyond injected dependencies)
- **Methods accept model instances** (Organization, Member) not IDs
- **Return plain arrays** (not Eloquent collections or resources)
- **Injected into controllers** via constructor type-hints

### 7.2 Existing Service Examples

**`TimeEntryAggregationService`**: Complex aggregation queries with PostgreSQL raw SQL. The `AttendanceService` follows this pattern for computing actual hours from time entries.

**`MemberService`**: Simple CRUD operations on the `Member` model. The `WorkScheduleService` follows this pattern for policy resolution logic.

**`PermissionStore`**: Caching pattern for frequently accessed data. The `WorkScheduleService` could cache resolved policies per-request if performance becomes an issue.

### 7.3 Service Injection Pattern

```php
class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService
    ) {
        parent::__construct(app(PermissionStore::class));
    }
}
```

Note: The base `Controller` constructor requires `PermissionStore`, so child controllers that inject additional services must call `parent::__construct()` explicitly with the `PermissionStore` instance.

---

## 8. Request Validation Patterns

### 8.1 Base Request

**File**: `app/Http/Requests/V1/BaseFormRequest.php`

All request classes extend this. It provides:
- Access to `$this->organization` via route model binding
- Standard authorization logic

### 8.2 Existing Validation Pattern

**`ExistsEloquent` rule** for relationship validation:
```php
'project_id' => [
    'nullable', 'string', 'uuid',
    new ExistsEloquent(Project::class, null, function ($builder) {
        $builder->whereBelongsTo($this->organization, 'organization');
    }),
],
```

The `WorkScheduleAssignRequest` uses this pattern to validate that `member_id` and `work_schedule_policy_id` belong to the current organization.

### 8.3 Date Format Validation

Existing patterns use `'date_format:Y-m-d'` for date-only fields and `'date'` for datetime fields. The attendance feature uses `'date_format:Y-m-d'` consistently for all date inputs (attendance dates, effective dates, date ranges).

### 8.4 Request File Organization

Requests are organized in subdirectories per feature:
```
app/Http/Requests/V1/
  Timesheet/
    TimesheetIndexRequest.php
    TimesheetCellUpdateRequest.php
  WorkSchedule/        <-- NEW
    WorkScheduleStoreRequest.php
    WorkScheduleUpdateRequest.php
    WorkScheduleAssignRequest.php
  OvertimeRule/        <-- NEW
    OvertimeRuleStoreRequest.php
    OvertimeRuleUpdateRequest.php
  Attendance/          <-- NEW
    AttendanceDailySummaryRequest.php
    AttendanceMemberDetailRequest.php
    AttendanceOvertimeReportRequest.php
    AttendanceExportRequest.php
    AttendanceRecomputeRequest.php
```

---

## 9. Permission System

### 9.1 Modular Registration (SF-08)

**File**: `app/Permissions/CorePermissions.php`

Permissions are registered via a static `register()` method that calls `Jetstream::role()` for each role:

```php
class CorePermissions
{
    public static function register(): void
    {
        Jetstream::role(Role::Owner->value, 'Owner', [
            'charts:view:own',
            'charts:view:all',
            'projects:view',
            // ... 60+ permissions
        ])->description('Owner users can perform any action.');

        Jetstream::role(Role::Admin->value, 'Administrator', [
            // ... similar list
        ])->description('...');

        Jetstream::role(Role::Manager->value, 'Manager', [
            // ... subset
        ])->description('...');

        Jetstream::role(Role::Employee->value, 'Employee', [
            'charts:view:own',
            'projects:view',
            'tags:view',
            'tasks:view',
            'clients:view',
            'time-entries:view:own',
            'time-entries:create:own',
            'time-entries:update:own',
            'time-entries:delete:own',
            'organizations:view',
            'notifications:view',
        ])->description('...');
    }
}
```

### 9.2 Pattern for Attendance Permissions

**Challenge**: The `Jetstream::role()` method _replaces_ the entire permission list for a role. The modular pattern (FOUND-007) needs a way to _append_ permissions to existing role definitions.

**Approach**: The `AttendancePermissions::register()` method must be called _after_ `CorePermissions::register()` and re-register each role with the combined permission list. Alternatively, FOUND-007 may provide a helper that merges permissions. If FOUND-007 is not yet implemented, the temporary approach is to add attendance permissions directly to `CorePermissions.php`.

```php
// app/Permissions/AttendancePermissions.php
class AttendancePermissions
{
    public const PERMISSIONS = [
        Role::Owner->value => [
            'attendance:configure',
            'attendance:view:all',
            'attendance:view:own',
            'attendance:export',
            'attendance:recompute',
        ],
        Role::Admin->value => [
            'attendance:configure',
            'attendance:view:all',
            'attendance:view:own',
            'attendance:export',
            'attendance:recompute',
        ],
        Role::Manager->value => [
            'attendance:view:all',
            'attendance:view:own',
            'attendance:export',
        ],
        Role::Employee->value => [
            'attendance:view:own',
        ],
    ];
}
```

### 9.3 Permission Check Patterns in Controllers

The existing codebase uses two patterns:

1. **Single permission**: `$this->checkPermission($organization, 'tags:create');`
2. **Any of multiple**: `$this->checkAnyPermission($organization, ['time-entries:view:own', 'time-entries:view:all']);`

Attendance endpoints use pattern 2 for read endpoints (supporting both `view:own` and `view:all`) and pattern 1 for write/configure endpoints.

---

## 10. Frontend Store Patterns

### 10.1 Existing Pinia Store Pattern

**Example from `useTimesheet.ts`** (Feature 00):
```typescript
export const useTimesheetStore = defineStore('timesheet', () => {
    const weekList = ref<WeekSummary[]>([]);
    const isLoadingList = ref(false);

    async function loadWeekList() {
        isLoadingList.value = true;
        try {
            const orgId = getCurrentOrganizationId();
            const response = await api.getTimesheetWeeks({ organization: orgId, limit: 8 });
            weekList.value = response.data.data;
        } finally {
            isLoadingList.value = false;
        }
    }

    return { weekList, isLoadingList, loadWeekList };
});
```

The `useAttendanceStore` follows this exact pattern: composition API style, reactive refs, async action methods, `getCurrentOrganizationId()` for API calls.

### 10.2 API Client Pattern

**File**: `resources/js/packages/api/src/openapi.json.client.ts`

Auto-generated client from OpenAPI spec. After adding attendance endpoints to `openapi.json` and regenerating (ATT-027, ATT-028), the client provides typed methods:
```typescript
api.getWorkSchedules({ organization: orgId })
api.createWorkSchedule({ organization: orgId, ...data })
api.getAttendanceDailySummary({ organization: orgId, date: '2026-02-08' })
api.getAttendanceMemberDetail({ organization: orgId, member: memberId, date_from, date_to })
api.getAttendanceOvertimeReport({ organization: orgId, date_from, date_to, aggregation: 'weekly' })
api.exportAttendance({ organization: orgId, type: 'attendance', format: 'csv', date_from, date_to })
```

### 10.3 Organization Context

**File**: `resources/js/utils/useUser.ts`

`getCurrentOrganizationId()` returns the current organization UUID for API calls.

---

## 11. Frontend Component Patterns

### 11.1 Page Component Pattern

**Example from `Time.vue`**:
```vue
<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import MainContainer from '@/packages/ui/src/MainContainer.vue';
// ...
onMounted(async () => {
    await store.fetchData();
});
</script>

<template>
    <AppLayout title="Time" data-testid="time_view">
        <MainContainer>
            <!-- Page content -->
        </MainContainer>
    </AppLayout>
</template>
```

The `Attendance.vue` page follows this pattern with `AppLayout` + `MainContainer`, adding tab navigation at the top.

### 11.2 UI Component Location

Feature-specific components live in `resources/js/packages/ui/src/{Feature}/`. Tests go in `__tests__/` subdirectory.

For attendance:
```
resources/js/packages/ui/src/Attendance/
  AttendanceGrid.vue
  AttendanceStatusBadge.vue
  AttendanceMemberDetail.vue
  AttendanceCalendar.vue
  AttendanceSummaryBar.vue
  AttendanceDayTooltip.vue
  AttendanceExportButton.vue
  OvertimeReport.vue
  WorkScheduleSettings.vue
  MemberScheduleAssignment.vue
  OvertimeRuleSettings.vue
  __tests__/
    AttendanceGrid.test.ts
    AttendanceStatusBadge.test.ts
    OvertimeReport.test.ts
```

### 11.3 Icon Usage

Icons are imported from `@heroicons/vue/20/solid`:
```typescript
import { ClipboardDocumentCheckIcon } from '@heroicons/vue/20/solid';
```

Used in sidebar navigation. Status badges in the attendance grid may use additional icons:
- `CheckCircleIcon` (present)
- `XCircleIcon` (absent)
- `ClockIcon` (late)
- `ExclamationCircleIcon` (half day)
- `CalendarIcon` (on leave / holiday / rest day)

All available in the existing `@heroicons/vue` package (already installed).

### 11.4 Tab Navigation Pattern

The attendance page uses a tab layout (Attendance | Overtime | Settings). The existing codebase does not have a prominent example of tab navigation within a page, but TailwindCSS tab components are straightforward. The tabs will be rendered as buttons with `aria-selected` attributes, switching which content section is visible.

```vue
<div class="border-b border-gray-200">
  <nav class="-mb-px flex space-x-8">
    <button v-for="tab in tabs" :key="tab.id"
      :class="[activeTab === tab.id ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500']"
      class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium"
      @click="activeTab = tab.id">
      {{ tab.name }}
    </button>
  </nav>
</div>
```

---

## 12. Route Registration Patterns

### 12.1 API Routes

**File**: `routes/api.php`

API routes are registered inside nested middleware groups:
```php
Route::prefix('v1')->name('v1.')->group(static function (): void {
    Route::middleware(['auth:api', 'verified'])->group(static function (): void {
        // Feature route groups here

        // Example: Tag routes
        Route::name('tags.')->prefix('/organizations/{organization}')->group(static function (): void {
            Route::get('/tags', [TagController::class, 'index'])->name('index');
            Route::post('/tags', [TagController::class, 'store'])->name('store')
                ->middleware('check-organization-blocked');
            // ...
        });
    });
});
```

The attendance feature adds 3 route groups following this pattern:
1. `Route::name('work-schedules.')->prefix('/organizations/{organization}')->group(...)`
2. `Route::name('overtime-rules.')->prefix('/organizations/{organization}')->group(...)`
3. `Route::name('attendance.')->prefix('/organizations/{organization}')->group(...)`

**Convention**: Write endpoints (POST, PUT, DELETE) use `->middleware('check-organization-blocked')`. Read endpoints (GET) do not.

### 12.2 Web Routes

**File**: `routes/web.php`

Inertia page routes are registered inside the `auth:web` + `verified` middleware group:
```php
Route::middleware(['auth:web', config('jetstream.auth_session'), 'verified'])->group(function (): void {
    Route::get('/time', function () { return Inertia::render('Time'); })->name('time');
    Route::get('/timesheet', function () { return Inertia::render('Timesheet'); })->name('timesheet');
    // ...
});
```

The attendance page follows this pattern:
```php
Route::get('/attendance', function () {
    return Inertia::render('Attendance');
})->name('attendance');
```

---

## 13. Navigation Sidebar

**File**: `resources/js/Layouts/AppLayout.vue`

The sidebar uses `NavigationSidebarItem` components in this order:
1. Dashboard (`DashboardIcon`)
2. Time (`ClockIcon`)
3. Timesheet (`TableCellsIcon`)
4. Calendar (`CalendarIcon`)
5. Reporting (expandable submenu)
6. Projects (`FolderIcon`)
7. Clients (`UsersIcon`)
8. Members (`UserGroupIcon`)
9. Tags (`TagIcon`)
10. Invoices (`DocumentTextIcon`)
11. Billing (`CreditCardIcon`)
12. Import (`ArrowUpTrayIcon`)

The Attendance item should be placed after Calendar (position 5) and before Reporting (position 6), making it easily accessible for daily use:

```vue
<NavigationSidebarItem
    title="Attendance"
    :icon="ClipboardDocumentCheckIcon"
    :current="route().current('attendance')"
    :href="route('attendance')">
</NavigationSidebarItem>
```

---

## 14. Feature 06 (Kiosk) Integration Analysis

### 14.1 Current State

Feature 06 (Kiosk & Clock Mode) is **not yet on `main`**. It is a planned feature that adds clock-in/clock-out kiosk terminals and explicit break tracking.

### 14.2 Expected Models (When Available)

Based on the features analysis, Feature 06 is expected to introduce:
- `KioskSession` model with `on_break_since` and `break_ended_at` columns
- Break events explicitly tied to a member and timestamp

### 14.3 Integration Strategy

The `AttendanceService::computeBreaks()` method uses a `class_exists()` check:

```php
if (class_exists(\App\Models\KioskSession::class)) {
    // Query kiosk break events and add to total break seconds
}
```

**When Feature 06 is absent**: Break detection relies solely on gap analysis between consecutive `TimeEntry` records. This is the default behavior for v1.

**When Feature 06 is present**: Explicit kiosk breaks are included in the break total, regardless of gap analysis. Kiosk breaks take priority as they represent the member's deliberate "start break" / "end break" actions.

### 14.4 No Migration Dependencies

The attendance tables have no foreign keys to Feature 06 tables. Integration is purely at the service/query layer.

---

## 15. Feature 07 (PTO) Integration Analysis

### 15.1 Current State

Feature 07 (PTO & Time Off) is **not yet on `main`**. It is a planned feature that adds time-off request management and holiday calendars.

### 15.2 Expected Models (When Available)

Based on the features analysis, Feature 07 is expected to introduce:
- `TimeOffRequest` model with `member_id`, `status` (approved/pending/rejected), `start_date`, `end_date`
- `Holiday` model with `organization_id`, `date`, `name`, `is_recurring`

### 15.3 Integration Strategy

Two methods in `AttendanceService` use `class_exists()` checks:

```php
public function isOnLeave(string $memberId, Carbon $date): bool
{
    if (!class_exists(\App\Models\TimeOffRequest::class)) {
        return false; // Feature 07 not installed
    }
    return \App\Models\TimeOffRequest::query()
        ->where('member_id', $memberId)
        ->where('status', 'approved')
        ->where('start_date', '<=', $date)
        ->where('end_date', '>=', $date)
        ->exists();
}

public function isHoliday(string $organizationId, Carbon $date): bool
{
    if (!class_exists(\App\Models\Holiday::class)) {
        return false; // Feature 07 not installed
    }
    return \App\Models\Holiday::query()
        ->where('organization_id', $organizationId)
        ->where(function ($q) use ($date) {
            $q->where('date', $date->toDateString())
              ->orWhere(function ($q2) use ($date) {
                  $q2->where('is_recurring', true)
                     ->whereMonth('date', $date->month)
                     ->whereDay('date', $date->day);
              });
        })
        ->exists();
}
```

**When Feature 07 is absent**:
- `isOnLeave()` always returns `false` -- no days are marked as "on leave"
- `isHoliday()` always returns `false` -- no days are marked as "holiday"
- All expected work days (per schedule policy) are treated as regular work days
- Members working on what would be holidays see their hours counted normally (no overtime multiplier for holiday work)

**When Feature 07 is present**:
- Approved PTO marks days as `on_leave` status (expected hours = 0, actual hours ignored)
- Organization holidays mark days as `holiday` status
- Holiday work overtime rules can apply

### 15.4 No Migration Dependencies

The attendance tables have no foreign keys to Feature 07 tables. Integration is purely at the service/query layer.

---

## 16. Data Flow Diagrams

### 16.1 Attendance Computation Flow (Scheduled)

```
Daily at 02:00 UTC
    -> ComputeAttendanceCommand runs
    -> For each organization with attendance enabled:
       -> Query all active members
       -> For each member (chunked, 50 at a time):
          -> AttendanceService::computeAttendanceForMemberDate(member, yesterday)
             -> WorkScheduleService::getEffectivePolicy(member, date)
                -> Check member_work_schedules (most recent effective)
                -> Fallback to org default policy
                -> Fallback to system default (8h Mon-Fri)
             -> WorkScheduleService::getExpectedSecondsForDate(member, date)
                -> Policy weekday -> expected seconds
             -> Check holiday (Feature 07 class_exists)
             -> Check rest day (expected seconds == 0)
             -> Check on leave (Feature 07 class_exists)
             -> Query time_entries for member+date
                -> WHERE member_id = ? AND date(start AT TZ) = ? AND end IS NOT NULL
             -> Sum actual_seconds
             -> Determine status (present/absent/late/half_day)
             -> computeOvertime(member, date, actual, expected, status)
                -> Query active overtime_rules for org+date
                -> Apply daily threshold, double-time, rest day, holiday rules
             -> computeBreaks(entries, policy, actual)
                -> Gap analysis between consecutive entries
                -> Feature 06 kiosk breaks (class_exists)
                -> Compliance check against policy requirements
             -> Upsert attendance_records (ON CONFLICT member_id, date)
    -> Log summary
```

### 16.2 Stale Record Flow

```
User creates/updates/deletes a TimeEntry
    -> TimeEntry model event (saved/deleted)
    -> Mark attendance_records WHERE member_id AND date(start) as is_stale = true
    -> Next scheduled run (every 30 min):
       -> ComputeAttendanceCommand --recompute
       -> Query attendance_records WHERE is_stale = true
       -> For each stale record:
          -> AttendanceService::computeAttendanceForMemberDate(member, date)
          -> Record updated, is_stale set to false
```

### 16.3 Attendance Page Load Flow

```
User clicks "Attendance" in sidebar
    -> Inertia navigates to /attendance
    -> Attendance.vue mounts
    -> useAttendanceStore.loadDailySummary(today)
       -> GET /api/v1/organizations/{org}/attendance?date=2026-02-09
       -> Backend: AttendanceController.dailySummary()
          -> AttendanceService.getDailySummary()
             -> Query attendance_records WHERE org AND date
             -> Join member names
             -> Compute totals (present/absent/late/leave/holiday counts)
          -> Return JSON
       -> Store: set dailySummary, render grid
    -> User clicks a member row
    -> useAttendanceStore.loadMemberDetail(memberId, dateFrom, dateTo)
       -> GET /api/v1/organizations/{org}/attendance/member/{member}?date_from=...&date_to=...
       -> Backend: AttendanceController.memberDetail()
          -> AttendanceService.getMemberDetail()
             -> Query attendance_records for member in date range
             -> Compute totals
          -> Return JSON
       -> Store: set memberDetail, render detail view
```

### 16.4 Configuration Flow

```
Admin navigates to Attendance > Settings tab
    -> useAttendanceStore.loadWorkSchedulePolicies()
       -> GET /api/v1/organizations/{org}/work-schedules
       -> Store: set workSchedulePolicies
    -> useAttendanceStore.loadOvertimeRules()
       -> GET /api/v1/organizations/{org}/overtime-rules
       -> Store: set overtimeRules
    -> Admin fills out policy form and clicks Save
    -> useAttendanceStore.createWorkSchedulePolicy(data)
       -> POST /api/v1/organizations/{org}/work-schedules
       -> Backend: WorkScheduleController.store()
          -> Validate, create policy, handle is_default toggle
       -> Store: add to workSchedulePolicies list
```

---

## 17. File Modification Risk Assessment

### 17.1 Risk Matrix

| File | Change Type | Risk | Rationale |
|------|------------|:----:|-----------|
| `routes/api.php` | Add 3 route groups | **Low** | Appending new groups after existing groups. No existing routes modified. |
| `routes/web.php` | Add 1 Inertia route | **Low** | Appending single route. No existing routes modified. |
| `resources/js/Layouts/AppLayout.vue` | Add 1 nav item | **Low** | Adding one `NavigationSidebarItem`. No existing items modified. |
| `app/Console/Kernel.php` | Add 2 scheduled commands | **Low** | Appending to `schedule()` method. No existing commands modified. |
| `openapi.json` | Add 15 endpoint definitions | **Low** | Appending new paths. No existing paths modified. |
| `app/Permissions/CorePermissions.php` | Append attendance permissions to role arrays | **Medium** | Modifying existing role permission arrays. Must be careful not to remove existing permissions. If FOUND-007 modular pattern is available, this file is NOT modified (separate `AttendancePermissions.php` instead). |

### 17.2 Merge Conflict Assessment

**Risk: LOW** -- This feature creates primarily new files (47 new files). The 5 modified files receive simple additions (new route groups, new nav item, new scheduled commands, new OpenAPI paths) that are appended rather than modifying existing content.

**Potential conflict points**:
1. `routes/api.php` -- If another feature branch also adds routes to the same location. Mitigation: Routes are added as independent groups and can be reordered without conflict.
2. `app/Console/Kernel.php` -- If another feature branch also adds scheduled commands. Mitigation: Commands are appended to the `schedule()` method.
3. `resources/js/Layouts/AppLayout.vue` -- If another feature branch also adds navigation items. Mitigation: Items are independent; order can be adjusted during review.

### 17.3 Cross-Feature Dependency Warning

- **Feature 06 (Kiosk)** and **Feature 07 (PTO)** are soft dependencies. If either ships to `main` before or after this feature, no merge conflicts occur. The `class_exists()` checks handle both scenarios.
- **Feature 01 (Timesheet Approvals)**: If approved timesheets lock attendance records, a small integration hook is needed in `AttendanceService::computeAttendanceForMemberDate()` to skip records in approved periods. This can be added as a follow-up task after Feature 01 ships.

### 17.4 No Downstream Conflicts for Existing Features

This feature does not modify any files owned by existing features (Time page, Timesheet Grid, Reports, etc.). All new functionality is additive.
