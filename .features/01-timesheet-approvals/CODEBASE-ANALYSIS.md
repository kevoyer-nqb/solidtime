# Codebase Analysis: Feature 01 -- Timesheet Approvals

**Date**: 2026-02-06
**Branch analyzed**: `feature/weekly-timesheet-grid`
**Target feature branch**: `feature/timesheet-approvals`
**PRD reference**: `.features/01-timesheet-approvals/PRD.md` (with amendments AMD-01 through AMD-10)

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Existing Timesheet Implementation Trace](#2-existing-timesheet-implementation-trace)
3. [Time Entry Mutation Points (7 Total)](#3-time-entry-mutation-points-7-total)
4. [Permission System Flow](#4-permission-system-flow)
5. [Mail and Notification Infrastructure](#5-mail-and-notification-infrastructure)
6. [Enum Patterns](#6-enum-patterns)
7. [Model Traits and Patterns](#7-model-traits-and-patterns)
8. [Request Validation Patterns](#8-request-validation-patterns)
9. [Frontend Store Patterns](#9-frontend-store-patterns)
10. [Data Flow Diagrams](#10-data-flow-diagrams)
11. [Merge Conflict Risk Assessment](#11-merge-conflict-risk-assessment)
12. [File Modification List](#12-file-modification-list)

---

## 1. Executive Summary

The solidtime codebase provides a strong foundation for the Timesheet Approvals feature. Key architectural patterns (services, controllers, permissions, audit trailing) are well-established and consistent. The weekly timesheet grid on `feature/weekly-timesheet-grid` is fully functional with a `TimesheetController`, `TimesheetService`, Pinia store, and Vue components.

**Key findings**:

- **7 time entry mutation points** require approval-lock enforcement (see Section 3)
- **No Laravel Notification infrastructure exists** -- only standalone Mailable classes. This is the single largest infrastructure gap (blocks APPR-012 and APPR-013 per PRD AMD-04)
- **Permission system** is centralized in `JetstreamServiceProvider` with a single `PermissionStore` cache layer -- straightforward to extend
- **All 11 enums** are string-backed PHP 8.1 enums following a consistent pattern
- **Model traits** are standardized: `HasUuids`, `CustomAuditable`, `HasFactory` on every model
- **Merge conflict risk is HIGH** on `TimesheetService.php` and `useTimesheet.ts` because both the grid branch and approval feature modify the same methods/store

---

## 2. Existing Timesheet Implementation Trace

### 2.1 Route Registration

**File**: `/home/keven/Documents/solidtime-analysis/routes/api.php` (lines 117-122)

```php
Route::name('timesheet.')->prefix('/organizations/{organization}')->group(static function (): void {
    Route::get('/timesheet/weeks', [TimesheetController::class, 'weeks'])->name('weeks');
    Route::get('/timesheet', [TimesheetController::class, 'index'])->name('index');
    Route::put('/timesheet/cell', [TimesheetController::class, 'updateCell'])->name('update-cell')->middleware('check-organization-blocked');
    Route::get('/timesheet/recent-tasks', [TimesheetController::class, 'recentTasks'])->name('recent-tasks');
});
```

Route names resolve to `api.v1.timesheet.weeks`, `api.v1.timesheet.index`, `api.v1.timesheet.update-cell`, `api.v1.timesheet.recent-tasks`. All are inside the `auth:api` + `verified` middleware group. Only the `updateCell` write endpoint uses the `check-organization-blocked` middleware.

### 2.2 Controller Methods

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/TimesheetController.php`

| Method | Line | Route | Permission Check | Description |
|--------|------|-------|-----------------|-------------|
| `weeks()` | 32 | GET `/timesheet/weeks` | `checkAnyPermission(['time-entries:view:own', 'time-entries:view:all'])` | Returns week list with totals |
| `index()` | 61 | GET `/timesheet` | Same as above | Returns grid data for a specific week |
| `updateCell()` | 90 | PUT `/timesheet/cell` | Checks `time-entries:create:own` or `time-entries:create:all` based on member ownership | Creates/updates/deletes time entries |
| `recentTasks()` | 128 | GET `/timesheet/recent-tasks` | Same as `weeks()` | Returns recent project+task combos |

### 2.3 Service Layer Methods

**File**: `/home/keven/Documents/solidtime-analysis/app/Service/TimesheetService.php`

| Method | Line | Signature | Returns |
|--------|------|-----------|---------|
| `getWeekList()` | 24 | `(Organization, Member, string $timezone, int $weekStartDay, int $limit, int $offset): array` | `array<{week_start, week_end, label, total_seconds}>` |
| `getWeekGrid()` | 57 | `(Organization, Member, Carbon $weekStart, Carbon $weekEnd, string $timezone): array` | `{week_start, week_end, rows[], day_totals[], week_total}` |
| `updateCell()` | 168 | `(Organization, Member, string $date, ?string $projectId, ?string $taskId, float $hours, string $timezone): array` | `{date, hours, time_entry_ids[]}` |
| `getRecentTasks()` | 272 | `(Organization, Member, int $limit): array` | `array<{project, task}>` |
| `getWeekTotalSeconds()` | 308 | `(Organization, Member, Carbon $weekStart, Carbon $weekEnd, string $timezone): int` | Total seconds as integer |
| `getWeekLabel()` | 328 | `(Carbon $weekStart, Carbon $now): string` | "This Week", "Last Week", or date range |

### 2.4 updateCell() Detailed Trace

**File**: `/home/keven/Documents/solidtime-analysis/app/Service/TimesheetService.php` (lines 168-265)

This is the primary mutation point in the timesheet grid. The method:

1. Parses the date string to Carbon in the user's timezone (line 177)
2. Converts to UTC for database queries (lines 178-179)
3. Finds existing entries matching org + user + date + project + task (lines 182-199)
4. **When hours <= 0**: Deletes all matching entries (lines 201-211)
5. **When hours > 0 and entries exist**: Updates first entry's `end` time, deletes extras (lines 221-240)
6. **When hours > 0 and no entries**: Creates a new `TimeEntry` with 9:00 AM start time (lines 243-264)

```php
// Line 168-176
public function updateCell(
    Organization $organization,
    Member $member,
    string $date,
    ?string $projectId,
    ?string $taskId,
    float $hours,
    string $timezone
): array {
```

**Approval lock insertion point**: A lock check must be added at the top of this method (before line 177), querying `TimesheetApproval` for the member + date range. If `status` is `submitted` or `approved`, the method should throw a `TimesheetLockedException` (or return HTTP 423).

### 2.5 getWeekList() Enhancement Point

**File**: `/home/keven/Documents/solidtime-analysis/app/Service/TimesheetService.php` (lines 24-47)

Currently returns `{week_start, week_end, label, total_seconds}` per week. For the approval feature, each week entry needs two additional fields:

- `approval_status: string|null` -- the `TimesheetApproval.status` value for this member+week, or null if no record exists (implying `draft`)
- `approval_id: string|null` -- the UUID of the `TimesheetApproval` record, if one exists

This requires a LEFT JOIN or subquery against `timesheet_approvals` matching on `member_id`, `start_date`, and `end_date`.

---

## 3. Time Entry Mutation Points (7 Total)

Every location where time entries are created, updated, or deleted must enforce approval-period locking. These are the 7 mutation entry points identified:

### Mutation Point 1: TimesheetService::updateCell()

**File**: `/home/keven/Documents/solidtime-analysis/app/Service/TimesheetService.php`
**Line**: 168
**Operation**: Create, update, or delete time entries for a single cell in the weekly grid
**Lock enforcement**: Add check at top of method before any database operations

```php
// Line 168
public function updateCell(
    Organization $organization,
    Member $member,
    string $date,
    // ...
```

### Mutation Point 2: TimeEntryController::store()

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/TimeEntryController.php`
**Line**: 577
**Route**: `POST /api/v1/organizations/{organization}/time-entries` (line 109 in routes/api.php)
**Operation**: Creates a single time entry with full field control
**Lock enforcement**: After member resolution (line 580), before overlap check (line 592)

```php
// Line 577-585
public function store(Organization $organization, TimeEntryStoreRequest $request): JsonResource
{
    /** @var Member $member */
    $member = Member::query()->findOrFail($request->input('member_id'));
    if ($member->user_id === Auth::id()) {
        $this->checkPermission($organization, 'time-entries:create:own');
    } else {
        $this->checkPermission($organization, 'time-entries:create:all');
    }
```

### Mutation Point 3: TimeEntryController::update()

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/TimeEntryController.php`
**Line**: 626
**Route**: `PUT /api/v1/organizations/{organization}/time-entries/{timeEntry}` (line 110 in routes/api.php)
**Operation**: Updates a single time entry (start, end, project, task, description, etc.)
**Lock enforcement**: After permission check (line 633), check both the existing entry's date range AND the new date range if start/end are being changed

```php
// Line 626
public function update(Organization $organization, TimeEntry $timeEntry, TimeEntryUpdateRequest $request): JsonResource
{
```

### Mutation Point 4: TimeEntryController::updateMultiple()

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/TimeEntryController.php`
**Line**: 689
**Route**: `PATCH /api/v1/organizations/{organization}/time-entries` (line 111 in routes/api.php)
**Operation**: Bulk update multiple time entries with shared changes
**Lock enforcement**: Inside the `foreach` loop (line 732), check each entry's date against approval status before applying changes. Locked entries should be added to the `error` collection.

```php
// Lines 689, 732
public function updateMultiple(Organization $organization, TimeEntryUpdateMultipleRequest $request): JsonResponse
{
    // ...
    foreach ($ids as $id) {
        /** @var TimeEntry|null $timeEntry */
        $timeEntry = $timeEntries->firstWhere('id', $id);
```

### Mutation Point 5: TimeEntryController::destroy()

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/TimeEntryController.php`
**Line**: 789
**Route**: `DELETE /api/v1/organizations/{organization}/time-entries/{timeEntry}` (line 112 in routes/api.php)
**Operation**: Deletes a single time entry
**Lock enforcement**: After permission check (line 794), before `$timeEntry->delete()` (line 800)

```php
// Line 789-800
public function destroy(Organization $organization, TimeEntry $timeEntry): JsonResponse
{
    if ($timeEntry->member->user_id === Auth::id()) {
        $this->checkPermission($organization, 'time-entries:delete:own', $timeEntry);
    } else {
        $this->checkPermission($organization, 'time-entries:delete:all', $timeEntry);
    }

    $project = $timeEntry->project;
    $task = $timeEntry->task;

    $timeEntry->delete();
```

### Mutation Point 6: TimeEntryController::destroyMultiple()

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/TimeEntryController.php`
**Line**: 820
**Route**: `DELETE /api/v1/organizations/{organization}/time-entries` (line 113 in routes/api.php)
**Operation**: Bulk delete multiple time entries
**Lock enforcement**: Inside the `foreach` loop (line 838), check each entry's date before deleting. Locked entries should be added to the `error` collection.

```php
// Lines 820, 838
public function destroyMultiple(Organization $organization, TimeEntryDestroyMultipleRequest $request): JsonResponse
{
    // ...
    foreach ($ids as $id) {
        /** @var TimeEntry|null $timeEntry */
        $timeEntry = $timeEntries->firstWhere('id', $id);
```

### Mutation Point 7: ImportService::import()

**File**: `/home/keven/Documents/solidtime-analysis/app/Service/Import/ImportService.php`
**Line**: 23
**Route**: `POST /api/v1/organizations/{organization}/import` (line 178 in routes/api.php)
**Operation**: Bulk imports time entries from external services (Toggl, Clockify, Harvest, generic CSV, solidtime)
**Downstream creation**: The actual `new TimeEntry` calls happen inside individual importer classes:

| Importer | File | Line |
|----------|------|------|
| `ClockifyTimeEntriesImporter` | `app/Service/Import/Importers/ClockifyTimeEntriesImporter.php` | 107 |
| `HarvestTimeEntriesImporter` | `app/Service/Import/Importers/HarvestTimeEntriesImporter.php` | 102 |
| `TogglTimeEntriesImporter` | `app/Service/Import/Importers/TogglTimeEntriesImporter.php` | 107 |
| `SolidtimeImporter` | `app/Service/Import/Importers/SolidtimeImporter.php` | 242 |
| `GenericTimeEntriesImporter` | `app/Service/Import/Importers/GenericTimeEntriesImporter.php` | 123 |

**Lock enforcement strategy**: Rather than modifying all 5 importers, add a check in `ImportService::import()` at line 23. After the importer runs inside the transaction (line 36), validate that no imported entries fall within a locked approval period. Alternatively, add a pre-import validation step that checks date ranges before calling `importData()`.

### Lock Check Query Pattern

The following query pattern should be extracted into a shared service method (e.g., `TimesheetApprovalService::isLocked()`) and reused across all 7 mutation points:

```php
// Proposed shared method
public function isLocked(string $memberId, Carbon $date): ?TimesheetApproval
{
    return TimesheetApproval::query()
        ->where('member_id', $memberId)
        ->where('start_date', '<=', $date->toDateString())
        ->where('end_date', '>=', $date->toDateString())
        ->whereIn('status', [
            ApprovalStatus::Submitted->value,
            ApprovalStatus::Approved->value,
        ])
        ->first();
}
```

This query benefits from the composite index `idx_timesheet_approvals_member` on `(member_id, start_date)` specified in the PRD migration.

---

## 4. Permission System Flow

### 4.1 Permission Definition

**File**: `/home/keven/Documents/solidtime-analysis/app/Providers/JetstreamServiceProvider.php` (lines 78-281)

Permissions are registered in `configurePermissions()` via `Jetstream::role()` calls. Each role receives a flat array of permission strings:

```php
// Line 82-147 (Owner role, showing relevant time-entry permissions)
Jetstream::role(Role::Owner->value, 'Owner', [
    // ...
    'time-entries:view:all',      // line 102
    'time-entries:create:all',    // line 103
    'time-entries:update:all',    // line 104
    'time-entries:delete:all',    // line 105
    'time-entries:view:own',      // line 106
    'time-entries:create:own',    // line 107
    'time-entries:update:own',    // line 108
    'time-entries:delete:own',    // line 109
    // ...
])->description('Owner users can perform any action...');
```

The five roles defined at lines 82, 149, 213, 266, 279:
- **Owner** (line 82): 47 permissions -- full access including billing
- **Admin** (line 149): 44 permissions -- everything except billing and ownership
- **Manager** (line 213): 40 permissions -- full project/time access, no org management
- **Employee** (line 266): 11 permissions -- own time entries, view projects/tags/clients
- **Placeholder** (line 279): 0 permissions -- cannot log in

### 4.2 Permission Checking Flow

```
Controller method
    |
    v
$this->checkPermission($organization, 'permission-name')
    |  (defined in app/Http/Controllers/Api/V1/Controller.php, line 21)
    v
$this->permissionStore->has($organization, $permission)
    |  (injected via constructor at line 14)
    v
PermissionStore::has() (app/Service/PermissionStore.php, line 25)
    |
    v
Auth::user() -> get current user
    |
    v
PermissionStore::userHas() (line 36)
    |
    v
Check cache: $this->permissionCache[$userId.'|'.$orgId]
    |
    +-- Cache miss: call getPermissionsByUser() (line 55)
    |       |
    |       v
    |   $user->belongsToTeam($organization) -> verify membership
    |       |
    |       v
    |   Get role from: $organization->users->where('id', $userId)->first()->membership->role
    |       |
    |       v
    |   Jetstream::findRole($role) -> get Role object with permissions array
    |       |
    |       v
    |   Special case (line 78): If role === 'employee' and org has employees_can_manage_tasks,
    |   merge in ['tasks:create', 'tasks:update', 'tasks:delete']
    |       |
    |       v
    |   Return permissions array, store in cache
    |
    v
in_array($permission, $permissions, true) -> bool
    |
    v
If false: throw AuthorizationException
```

**File**: `/home/keven/Documents/solidtime-analysis/app/Service/PermissionStore.php`

Key code at line 36-49:

```php
public function userHas(Organization $organization, User $user, string $permission): bool
{
    if (! isset($this->permissionCache[$user->getKey().'|'.$organization->getKey()])) {
        if (! $user->belongsToTeam($organization)) {
            return false;
        }

        $permissions = $this->getPermissionsByUser($organization, $user);
        $this->permissionCache[$user->getKey().'|'.$organization->getKey()] = $permissions;
    } else {
        $permissions = $this->permissionCache[$user->getKey().'|'.$organization->getKey()];
    }

    return in_array($permission, $permissions, true);
}
```

### 4.3 Controller Helper Methods

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/Controller.php`

Three permission-related helpers available on all API controllers:

| Method | Line | Signature | Behavior |
|--------|------|-----------|----------|
| `checkPermission()` | 21 | `(Organization, string): void` | Throws `AuthorizationException` if user lacks permission |
| `checkAnyPermission()` | 33 | `(Organization, array<string>): void` | Throws if user lacks ALL listed permissions |
| `hasPermission()` | 43 | `(Organization, string): bool` | Returns boolean without throwing |

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Controller.php`

Base controller helpers available on all controllers:

| Method | Line | Signature | Returns |
|--------|------|-----------|---------|
| `user()` | 25 | `(): User` | Authenticated user, throws if not authenticated |
| `member()` | 40 | `(Organization): Member` | Current user's membership in the given org |
| `currentOrganization()` | 59 | `(): Organization` | User's current team/org |

### 4.4 New Permissions to Register

Per PRD AMD-02 and AMD-06, the following permissions must be registered. Per AMD-06, these should use `App\Permissions\TimesheetApprovalPermissions::register()` rather than directly modifying JetstreamServiceProvider. However, per the current codebase pattern, permissions are embedded directly in `configurePermissions()`.

| Permission | Owner | Admin | Manager | Employee |
|-----------|-------|-------|---------|----------|
| `timesheet-approvals:view` | Yes | Yes | Yes | No |
| `timesheet-approvals:submit:own` | Yes | Yes | Yes | Yes |
| `timesheet-approvals:approve` | Yes | Yes | Yes | No |
| `timesheet-approvals:approve:all` | Yes | Yes | No | No |
| `timesheet-approvals:reopen` | Yes | Yes | No | No |
| `timesheet-approvals:configure` | Yes | Yes | No | No |

---

## 5. Mail and Notification Infrastructure

### 5.1 Current State: Mailable Classes Only

**Directory**: `/home/keven/Documents/solidtime-analysis/app/Mail/`

Four Mailable classes exist. Zero Laravel Notification classes exist (`app/Notifications/` directory does not exist).

| Class | File |
|-------|------|
| `TimeEntryStillRunningMail` | `app/Mail/TimeEntryStillRunningMail.php` |
| `OrganizationInvitationMail` | `app/Mail/OrganizationInvitationMail.php` |
| `AuthApiTokenExpiredMail` | `app/Mail/AuthApiTokenExpiredMail.php` |
| `AuthApiTokenExpirationReminderMail` | `app/Mail/AuthApiTokenExpirationReminderMail.php` |

### 5.2 Mailable Pattern (Reference Implementation)

**File**: `/home/keven/Documents/solidtime-analysis/app/Mail/TimeEntryStillRunningMail.php`

```php
class TimeEntryStillRunningMail extends Mailable
{
    use Queueable, SerializesModels;

    public TimeEntry $timeEntry;
    public User $user;

    public function __construct(TimeEntry $timeEntry, User $user)
    {
        $this->timeEntry = $timeEntry;
        $this->user = $user;
    }

    public function build(): self
    {
        return $this->markdown('emails.time-entry-still-running', [
            'dashboardUrl' => URL::route('dashboard'),
        ])
            ->subject(__('Your Time Tracker is still running!'));
    }
}
```

Key observations:
- Uses `Queueable` trait for async dispatch via queues
- Uses `SerializesModels` for safe model serialization in queue jobs
- Uses Blade markdown templates in `resources/views/emails/`
- Uses `__()` for translatable subjects
- Constructor receives model instances directly

### 5.3 Notification Infrastructure Gap (CRITICAL)

**Impact**: Blocks PRD tasks APPR-012 (notifications on state transitions) and APPR-013 (reminder notifications).

Per PRD AMD-04, the approval feature now requires **proper Laravel Notification classes** with `database` + `mail` channels, replacing the original PRD's ADR-4 (mail-only). This is a shared infrastructure dependency (FOUND-001 through FOUND-005 from `.features/SHARED-FOUNDATIONS.md`).

**What must be built before approval notifications**:

1. `php artisan notifications:table` migration for the `notifications` table
2. `App\Notifications\BaseNotification` extending `Illuminate\Notifications\Notification`
3. `User` model must implement `Illuminate\Notifications\Notifiable` trait (if not already)
4. `via()` method returns `['database', 'mail']`
5. Four notification classes:
   - `TimesheetSubmittedNotification`
   - `TimesheetApprovedNotification`
   - `TimesheetChangesRequestedNotification`
   - `TimesheetReminderNotification`

---

## 6. Enum Patterns

### 6.1 Inventory of All Enums

**Directory**: `/home/keven/Documents/solidtime-analysis/app/Enums/`

All 11 enums are PHP 8.1 string-backed enums with `declare(strict_types=1)`:

| Enum | Backing Type | Has LaravelEnumHelper | Has Custom Methods | Line Count |
|------|-------------|----------------------|-------------------|------------|
| `Role` | `string` | No | No | 14 |
| `ExportFormat` | `string` | No | `getFileExtension()`, `getExportPackageType()` | 35 |
| `Weekday` | `string` | Yes | `toEndOfWeek()`, `carbonWeekDay()`, `toSelectArray()` | 63 |
| `CurrencyFormat` | `string` | Yes | Format methods | -- |
| `DateFormat` | `string` | Yes | Format methods | -- |
| `IntervalFormat` | `string` | Yes | -- | -- |
| `NumberFormat` | `string` | Yes | -- | -- |
| `TimeFormat` | `string` | Yes | -- | -- |
| `TimeEntryAggregationType` | `string` | Yes | -- | -- |
| `TimeEntryAggregationTypeInterval` | `string` | No | No | 13 |
| `TimeEntryRoundingType` | `string` | Yes | -- | -- |

### 6.2 Two Patterns Observed

**Pattern A -- Simple enum (no traits, no methods)**:

```php
// app/Enums/Role.php
enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Manager = 'manager';
    case Employee = 'employee';
    case Placeholder = 'placeholder';
}
```

**Pattern B -- Enum with LaravelEnumHelper trait and custom methods**:

```php
// app/Enums/Weekday.php
enum Weekday: string
{
    use LaravelEnumHelper;

    case Monday = 'monday';
    // ...

    public function carbonWeekDay(): int
    {
        return match ($this) {
            Weekday::Monday => Carbon::MONDAY,
            // ...
        };
    }
}
```

**Pattern C -- Enum with custom methods but no LaravelEnumHelper**:

```php
// app/Enums/ExportFormat.php
enum ExportFormat: string
{
    case CSV = 'csv';
    case PDF = 'pdf';
    case XLSX = 'xlsx';
    case ODS = 'ods';

    public function getFileExtension(): string
    {
        return match ($this) {
            self::CSV => 'csv',
            // ...
        };
    }
}
```

### 6.3 New Enum: ApprovalStatus

Per PRD AMD-05, use a shared `App\Enums\ApprovalStatus` enum (not `TimesheetApprovalStatus`). Follow Pattern C (custom methods, no LaravelEnumHelper) since it needs `isLocked()` and `isPermanentlyLocked()` methods but does not need translation support for select arrays:

```php
// Proposed: app/Enums/ApprovalStatus.php
enum ApprovalStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case ChangesRequested = 'changes_requested';
    case Reopened = 'reopened';

    public function isLockedForMember(): bool
    {
        return match ($this) {
            self::Submitted, self::Approved => true,
            default => false,
        };
    }

    public function isPermanentlyLocked(): bool
    {
        return $this === self::Approved;
    }
}
```

---

## 7. Model Traits and Patterns

### 7.1 Standard Trait Stack

Every model in the codebase uses these three traits:

| Trait | Source | Purpose |
|-------|--------|---------|
| `HasUuids` | `App\Models\Concerns\HasUuids` | Wraps Laravel's `HasUuids`, generates Ramsey UUID v4 |
| `CustomAuditable` | `App\Models\Concerns\CustomAuditable` | Wraps `OwenIt\Auditing\Auditable`, adds `disableAuditing()` |
| `HasFactory` | `Illuminate\Database\Eloquent\Factories\HasFactory` | Standard Laravel factory support |

### 7.2 HasUuids Trait

**File**: `/home/keven/Documents/solidtime-analysis/app/Models/Concerns/HasUuids.php`

```php
trait HasUuids
{
    use \Illuminate\Database\Eloquent\Concerns\HasUuids;

    public function newUniqueId(): string
    {
        return (string) Uuid::uuid4();
    }
}
```

### 7.3 CustomAuditable Trait

**File**: `/home/keven/Documents/solidtime-analysis/app/Models/Concerns/CustomAuditable.php`

```php
trait CustomAuditable
{
    use Auditable;

    protected ?array $auditEvents = null;

    public function disableAuditing(): void
    {
        $this->auditEvents = [];
    }
}
```

Models using this trait must implement `OwenIt\Auditing\Contracts\Auditable` (the contract interface):

```php
class TimeEntry extends Model implements AuditableContract
{
    use CustomAuditable;
    // ...
}
```

### 7.4 TimeEntry Model Analysis

**File**: `/home/keven/Documents/solidtime-analysis/app/Models/TimeEntry.php`

Key patterns to replicate in `TimesheetApproval`:

**Traits** (lines 55-62):
```php
class TimeEntry extends Model implements AuditableContract
{
    use ComputedAttributes;  // Specific to TimeEntry
    use CustomAuditable;
    use HasFactory;
    use HasJsonRelationships;  // Specific to TimeEntry (for tags JSON column)
    use HasUuids;
```

**Casts** (lines 69-78):
```php
protected $casts = [
    'description' => 'string',
    'start' => 'datetime',
    'end' => 'datetime',
    'billable' => 'bool',
    'tags' => 'array',
    'billable_rate' => 'int',
    'is_imported' => 'bool',
    'still_active_email_sent_at' => 'datetime',
];
```

**Audit exclusion** (lines 116-118):
```php
protected array $auditExclude = [
    'billable_rate',
];
```

**Relationships** (lines 179-234): All use `BelongsTo` with explicit foreign key and return type annotations.

### 7.5 Member Model Analysis

**File**: `/home/keven/Documents/solidtime-analysis/app/Models/Member.php`

```php
class Member extends JetstreamMembership implements AuditableContract
{
    use CustomAuditable;
    use HasFactory;
    use HasUuids;

    protected $table = 'members';
```

Notable: Member extends `JetstreamMembership` (not `Model`), has `$table = 'members'` explicitly set, and defines relationships to `User`, `Organization`, `TimeEntry`, and `ProjectMember`.

### 7.6 Organization Model Analysis

**File**: `/home/keven/Documents/solidtime-analysis/app/Models/Organization.php`

```php
class Organization extends JetstreamTeam implements AuditableContract
{
    use CustomAuditable;
    use HasFactory;
    use HasUuids;
```

Uses enum casts for formatting preferences (lines 76-80):
```php
'number_format' => NumberFormat::class,
'currency_format' => CurrencyFormat::class,
'date_format' => DateFormat::class,
'interval_format' => IntervalFormat::class,
'time_format' => TimeFormat::class,
```

This same enum cast pattern should be used for the `status` field on `TimesheetApproval`:
```php
'status' => ApprovalStatus::class,
```

### 7.7 Proposed TimesheetApproval Model Structure

```php
class TimesheetApproval extends Model implements AuditableContract
{
    use CustomAuditable;
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'member_id', 'organization_id', 'start_date', 'end_date',
        'status', 'submitted_at', 'reviewed_by', 'reviewed_at',
        'rejection_reason', 'total_seconds', 'entry_count',
    ];

    protected $casts = [
        'status' => ApprovalStatus::class,
        'start_date' => 'date',
        'end_date' => 'date',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'total_seconds' => 'integer',
        'entry_count' => 'integer',
    ];

    // Relationships
    public function member(): BelongsTo       // submitter
    public function reviewer(): BelongsTo     // reviewed_by -> members.id
    public function organization(): BelongsTo
}
```

---

## 8. Request Validation Patterns

### 8.1 BaseFormRequest

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/BaseFormRequest.php`

Minimal base class extending `FormRequest` with a single helper:

```php
class BaseFormRequest extends FormRequest
{
    protected function moneyRules(bool $bigInt = false): array
    {
        $rules = ['integer', 'min:0'];
        if ($bigInt) {
            $rules[] = 'max:9223372036854775807';
        } else {
            $rules[] = 'max:2147483647';
        }
        return $rules;
    }
}
```

### 8.2 TimesheetCellUpdateRequest (Reference Pattern)

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/Timesheet/TimesheetCellUpdateRequest.php`

This is the most relevant request validation class for the approval feature because it demonstrates:

1. **Organization scoping via route model binding** (line 20):
```php
/**
 * @property Organization $organization Organization from model binding
 */
class TimesheetCellUpdateRequest extends BaseFormRequest
```

2. **ExistsEloquent with organization scoping** (lines 35-38):
```php
'member_id' => [
    'required',
    'string',
    ExistsEloquent::make(Member::class, null, function (Builder $builder): Builder {
        /** @var Builder<Member> $builder */
        return $builder->whereBelongsTo($this->organization, 'organization');
    })->uuid(),
],
```

3. **Permission-aware validation** (lines 54-58):
```php
$permissionStore = app(PermissionStore::class);
if (! $permissionStore->has($this->organization, 'time-entries:create:all')
    && ! $permissionStore->has($this->organization, 'projects:view:all')) {
    $builder = $builder->visibleByEmployee(Auth::user());
}
```

4. **Cross-field validation** (lines 71-75):
```php
ExistsEloquent::make(Task::class, null, function (Builder $builder): Builder {
    return $builder->whereBelongsTo($this->organization, 'organization')
        ->where('project_id', $this->input('project_id'));
})->uuid()->withMessage(__('validation.task_belongs_to_project')),
```

### 8.3 Patterns for New Approval Requests

New form requests for the approval feature should follow the same patterns:

- **TimesheetApprovalSubmitRequest**: Validate `week_start` (required, date_format:Y-m-d), `week_end` (required, date_format:Y-m-d)
- **TimesheetApprovalRequestChangesRequest**: Validate `rejection_reason` (required, string, max:2000)
- **TimesheetApprovalReopenRequest**: Validate `reason` (required, string, max:2000)
- **TimesheetApprovalIndexRequest**: Validate filter parameters (status, member_id with ExistsEloquent, date ranges, limit, offset)

All should extend `BaseFormRequest` and use `$this->organization` from route model binding.

---

## 9. Frontend Store Patterns

### 9.1 Current Pinia Store Structure

**File**: `/home/keven/Documents/solidtime-analysis/resources/js/utils/useTimesheet.ts`

Uses the **Composition API pattern** with `defineStore`:

```typescript
// Line 19
export const useTimesheetStore = defineStore('timesheet', () => {
    // --- State (refs) ---
    const weekList = ref<WeekSummary[]>([]);           // line 21
    const expandedWeeks = ref<Set<string>>(new Set());  // line 23
    const weekDataMap = ref<Map<string, TimesheetWeekData>>(new Map()); // line 25
    const hasMoreWeeks = ref(true);                     // line 26
    const isLoadingList = ref(false);                   // line 27
    const loadingWeeks = ref<Set<string>>(new Set());   // line 29
    const compactView = ref(false);                     // line 30
    const error = ref<string | null>(null);             // line 31
    const recentTasks = ref<RecentTask[]>([]);          // line 33

    // --- Notification integration ---
    const { handleApiRequestNotifications } = useNotificationsStore(); // line 35

    // --- Actions (async functions) ---
    async function loadWeekList() { ... }       // line 38
    async function loadMoreWeeks() { ... }      // line 75
    async function loadWeekGrid(weekStart) { ... } // line 103
    async function toggleWeek(weekStart) { ... }   // line 157
    function toggleCompactView() { ... }            // line 169
    async function updateCell(weekStart, rowIndex, dayIndex, hours) { ... } // line 177
    function addTaskRow(weekStart, projectId, taskId) { ... }               // line 242
    async function addLastWeekTasks(weekStart) { ... }                      // line 287
    async function loadRecentTasks() { ... }                                // line 346
    function recalculateTotals(weekStart) { ... }                           // line 369
    const grandTotal = computed(() => { ... });                             // line 392

    // --- Public interface ---
    return {
        weekList, expandedWeeks, weekDataMap, hasMoreWeeks,
        isLoadingList, loadingWeeks, compactView, error, recentTasks,
        grandTotal, loadWeekList, loadMoreWeeks, loadWeekGrid,
        toggleWeek, toggleCompactView, updateCell, addTaskRow,
        addLastWeekTasks, loadRecentTasks,
    };
});
```

### 9.2 API Call Pattern

All API calls use the `handleApiRequestNotifications` wrapper from `useNotificationsStore`:

```typescript
// Line 46-54 (example from loadWeekList)
const response = await handleApiRequestNotifications(
    () =>
        api.getTimesheetWeeks({
            params: { organization: organizationId },
            queries: { limit: 8, offset: 0 },
        }),
    undefined,       // success message (none for reads)
    'Failed to load week list'  // error message
);
```

### 9.3 Optimistic Update Pattern

**File**: `/home/keven/Documents/solidtime-analysis/resources/js/utils/useTimesheet.ts` (lines 177-239)

The `updateCell()` method implements optimistic UI:

1. Save previous value (line 191)
2. Set loading state and update cell immediately (lines 194-196)
3. Recalculate totals (line 197)
4. Send API request (lines 209-222)
5. On success: update with server response (lines 228-229)
6. On failure: rollback to previous value (lines 232-234)
7. Clear loading state (line 236)

```typescript
// Optimistic update (line 194-197)
cell.isLoading = true;
cell.hours = hours;
cell.hasError = false;
recalculateTotals(weekStart);

// ... API call ...

// Rollback on failure (line 232-234)
cell.hours = previousHours;
cell.hasError = true;
```

### 9.4 TypeScript Type Definitions

**File**: `/home/keven/Documents/solidtime-analysis/resources/js/types/timesheet.d.ts`

```typescript
export interface WeekSummary {
    week_start: string;
    week_end: string;
    label: string;
    total_seconds: number;
}

export interface TimesheetCell {
    date: string;
    hours: number;
    time_entry_ids: string[];
    isEditing: boolean;   // client-side only
    isLoading: boolean;   // client-side only
    hasError: boolean;    // client-side only
}

export interface TimesheetRow {
    id: string;
    project: TimesheetProjectInfo | null;
    task: TimesheetTaskInfo | null;
    cells: TimesheetCell[];
    total_hours: number;
    isNew: boolean;        // client-side only
}

export interface TimesheetWeekData {
    week_start: string;
    week_end: string;
    rows: TimesheetRow[];
    day_totals: number[];
    week_total: number;
}
```

### 9.5 Additions Required for Approval Feature

**`WeekSummary` interface needs two new fields**:
```typescript
export interface WeekSummary {
    week_start: string;
    week_end: string;
    label: string;
    total_seconds: number;
    approval_status: string | null;    // NEW: 'draft' | 'submitted' | 'approved' | 'changes_requested' | 'reopened' | null
    approval_id: string | null;        // NEW: UUID of TimesheetApproval record
}
```

**New store actions needed in `useTimesheetStore`**:
```typescript
async function submitWeek(weekStart: string): Promise<void>
async function withdrawSubmission(weekStart: string): Promise<void>
function canEditCell(weekStart: string): boolean  // checks approval_status
```

**New `useTimesheetApprovalStore` (separate store for manager approval page)**:
```typescript
// Manages the list of pending approvals, approval actions, filtering
export const useTimesheetApprovalStore = defineStore('timesheetApproval', () => {
    // State for approval list page
    // Actions: loadApprovals(), approve(), requestChanges(), reopen()
});
```

---

## 10. Data Flow Diagrams

### 10.1 Week List Flow (Current)

```
Browser
  |
  v
GET /api/v1/organizations/{org}/timesheet/weeks?limit=8&offset=0
  |
  v
[auth:api middleware] -> [verified middleware]
  |
  v
TimesheetController::weeks()  (line 32)
  |-- checkAnyPermission(['time-entries:view:own', 'time-entries:view:all'])
  |-- $member = $this->member($organization)
  |-- $timezone = timezoneService->getTimezoneFromUser($user)
  |-- $weekStartDay = $user->week_start->carbonWeekDay()
  |
  v
TimesheetService::getWeekList($org, $member, $tz, $weekStartDay, 8, 0)  (line 24)
  |-- for i = 0..7:
  |     |-- Calculate weekStart = now - i weeks, startOfWeek
  |     |-- Calculate weekEnd = weekStart + 6 days
  |     |-- getWeekTotalSeconds() (line 308)
  |     |     |-- SELECT COALESCE(SUM(EXTRACT(EPOCH FROM (end - start))), 0)
  |     |     |-- WHERE organization_id AND user_id AND end IS NOT NULL
  |     |     |-- WHERE start >= weekStart_utc AND start <= weekEnd_utc
  |     |-- getWeekLabel() -> "This Week" / "Last Week" / date range
  |     |-- Append {week_start, week_end, label, total_seconds}
  |
  v
JSON Response: { data: [{week_start, week_end, label, total_seconds}, ...] }
  |
  v
useTimesheetStore.loadWeekList()  (line 38)
  |-- weekList.value = response.data
  |-- Auto-expand first week: toggleWeek(weekList[0].week_start)
  |
  v
TimesheetWeekAccordion renders each week header
```

### 10.2 Grid Load Flow (Current)

```
User clicks week header to expand
  |
  v
toggleWeek(weekStart)  (useTimesheet.ts, line 157)
  |-- expandedWeeks.add(weekStart)
  |-- if (!weekDataMap.has(weekStart)): loadWeekGrid(weekStart)
  |
  v
GET /api/v1/organizations/{org}/timesheet?week_start=...&week_end=...
  |
  v
TimesheetController::index()  (line 61)
  |
  v
TimesheetService::getWeekGrid()  (line 57)
  |-- Query TimeEntry WHERE org AND user AND end NOT NULL AND start in week range
  |-- WITH project, task (eager load)
  |-- Group by project_id:task_id
  |-- For each group, for each of 7 days: sum seconds, collect entry IDs
  |-- Calculate row totals, day totals, week total
  |
  v
JSON: { data: { week_start, week_end, rows: [...], day_totals: [...], week_total } }
  |
  v
Store in weekDataMap.set(weekStart, data)
  |
  v
TimesheetGrid component renders rows and cells
```

### 10.3 Cell Update Flow (Current)

```
User types hours into cell, presses Enter/Tab
  |
  v
TimesheetCell emits update(rowIndex, dayIndex, hours)
  |
  v
TimesheetWeekAccordion.handleCellUpdate()  (line 42)
  |
  v
timesheetStore.updateCell(weekStart, rowIndex, dayIndex, hours)  (line 177)
  |-- OPTIMISTIC: cell.hours = hours; recalculateTotals();
  |
  v
PUT /api/v1/organizations/{org}/timesheet/cell
  Body: { member_id, date, project_id, task_id, hours }
  |
  v
[check-organization-blocked middleware]
  |
  v
TimesheetController::updateCell()  (line 90)
  |-- Resolve member, check permission
  |
  v
TimesheetService::updateCell()  (line 168)
  |-- Find existing entries for cell
  |-- hours <= 0: delete all
  |-- hours > 0, existing: update first, delete rest
  |-- hours > 0, none: create new TimeEntry (9 AM start)
  |
  v
JSON: { data: { date, hours, time_entry_ids } }
  |
  v
Store updates cell with server response (or rolls back on error)
```

### 10.4 Proposed Approval Submission Flow

```
User clicks "Submit Week" button on week header
  |
  v
useTimesheetStore.submitWeek(weekStart)
  |-- Optimistic: set UI to "submitting" state
  |
  v
POST /api/v1/organizations/{org}/timesheet-approvals/submit
  Body: { week_start, week_end }
  |
  v
[auth:api] -> [verified] -> [check-organization-blocked]
  |
  v
TimesheetApprovalController::submit()
  |-- checkPermission('timesheet-approvals:submit:own')
  |-- $member = $this->member($organization)
  |
  v
TimesheetApprovalService::submit($org, $member, $startDate, $endDate, $tz)
  |-- Validate: entries exist for this member+week
  |-- Validate: no running timers (end IS NULL) in the period
  |-- Validate: no existing submitted/approved record for this period
  |-- Calculate total_seconds and entry_count snapshot
  |-- Create/update TimesheetApproval: status='submitted', submitted_at=now
  |-- Dispatch TimesheetSubmittedNotification to managers
  |
  v
JSON 201: { data: { id, member_id, status: "submitted", ... } }
  |
  v
Store updates weekList[i].approval_status = 'submitted'
  |-- Cells become read-only in UI
  |-- Week header shows "Submitted" badge
```

---

## 11. Merge Conflict Risk Assessment

The `feature/weekly-timesheet-grid` branch has modifications to several files that the approval feature also needs to modify. These are based on the current `git status` showing modified files on the feature branch.

### 11.1 HIGH Risk Files

| File | Branch Modifications | Approval Modifications Needed |
|------|---------------------|------------------------------|
| `app/Service/TimesheetService.php` | Core service with `getWeekList()`, `getWeekGrid()`, `updateCell()` methods | Add lock check in `updateCell()`, add `approval_status` to `getWeekList()` response |
| `resources/js/utils/useTimesheet.ts` | Full Pinia store implementation | Add `submitWeek()`, `withdrawSubmission()`, `canEditCell()` actions; add approval state |
| `resources/js/types/timesheet.d.ts` | Type definitions for `WeekSummary`, `TimesheetCell`, etc. | Add `approval_status` and `approval_id` to `WeekSummary` |
| `resources/js/packages/ui/src/Timesheet/TimesheetWeekAccordion.vue` | Full accordion component implementation | Add submit/withdraw buttons, approval badges, cell locking UI |

### 11.2 MEDIUM Risk Files

| File | Risk |
|------|------|
| `tests/Unit/Endpoint/Api/V1/TimesheetEndpointTest.php` | Tests may need updates if response shapes change (adding approval_status) |
| `resources/js/packages/ui/src/Timesheet/__tests__/TimesheetRowHeader.test.ts` | Component tests may need updates for approval UI state |

### 11.3 LOW Risk Files (New Files, No Conflict)

| File | Type |
|------|------|
| `app/Enums/ApprovalStatus.php` | New file |
| `app/Models/TimesheetApproval.php` | New file |
| `app/Service/TimesheetApprovalService.php` | New file |
| `app/Http/Controllers/Api/V1/TimesheetApprovalController.php` | New file |
| `app/Http/Requests/V1/TimesheetApproval/*.php` | New files |
| `app/Notifications/*.php` | New files |
| `database/migrations/2026_03_01_*` | New files |
| `database/factories/TimesheetApprovalFactory.php` | New file |
| `tests/Unit/Endpoint/Api/V1/TimesheetApprovalEndpointTest.php` | New file |
| `routes/api.php` | Append only -- low conflict risk |
| `app/Providers/JetstreamServiceProvider.php` | Append permissions to existing arrays -- low conflict risk |

### 11.4 Mitigation Strategy

1. **Branch from `feature/weekly-timesheet-grid`**, not from `main`, to start with the grid already in place
2. **Coordinate with grid branch**: ensure the grid branch is merged to main before starting significant approval work on shared files
3. **Separate commits per layer**: model/migration first (no conflicts), then service (moderate risk), then controller, then frontend (highest risk)
4. **Run existing tests after every commit**: `php artisan test --filter=TimesheetEndpointTest` to catch regressions

---

## 12. File Modification List

### 12.1 New Files to Create

| File | Layer | Description |
|------|-------|-------------|
| `app/Enums/ApprovalStatus.php` | Backend | Shared approval status enum (AMD-05) |
| `app/Models/TimesheetApproval.php` | Backend | Eloquent model with CustomAuditable, HasUuids, HasFactory |
| `database/factories/TimesheetApprovalFactory.php` | Backend | Factory with draft/submitted/approved/changesRequested/reopened states |
| `database/migrations/2026_03_01_000001_create_timesheet_approvals_table.php` | Backend | Create table with indexes and constraints (AMD-03) |
| `database/migrations/2026_03_01_000002_add_timesheet_approval_settings_to_organizations.php` | Backend | Add reminder/approval settings columns to organizations |
| `database/migrations/2026_03_01_000003_create_notifications_table.php` | Backend | Laravel notifications table (shared infra, AMD-04) |
| `app/Service/TimesheetApprovalService.php` | Backend | State machine logic, validation, lock checking |
| `app/Http/Controllers/Api/V1/TimesheetApprovalController.php` | Backend | submit, withdraw, approve, requestChanges, reopen, index, my |
| `app/Http/Requests/V1/TimesheetApproval/SubmitRequest.php` | Backend | Validate week_start, week_end |
| `app/Http/Requests/V1/TimesheetApproval/RequestChangesRequest.php` | Backend | Validate rejection_reason |
| `app/Http/Requests/V1/TimesheetApproval/ReopenRequest.php` | Backend | Validate reason |
| `app/Http/Requests/V1/TimesheetApproval/IndexRequest.php` | Backend | Validate filter/pagination params |
| `app/Notifications/BaseNotification.php` | Backend | Shared base notification class (AMD-04) |
| `app/Notifications/TimesheetSubmittedNotification.php` | Backend | Notify managers of submission |
| `app/Notifications/TimesheetApprovedNotification.php` | Backend | Notify member of approval |
| `app/Notifications/TimesheetChangesRequestedNotification.php` | Backend | Notify member of rejection with reason |
| `app/Notifications/TimesheetReminderNotification.php` | Backend | Missing time / unsubmitted reminder |
| `app/Console/Commands/SendTimesheetRemindersCommand.php` | Backend | Artisan command for scheduled reminders |
| `resources/js/utils/useTimesheetApproval.ts` | Frontend | New Pinia store for approval list page |
| `resources/js/Pages/Approvals.vue` | Frontend | New Inertia page for manager approval queue |
| `resources/js/packages/ui/src/TimesheetApproval/*.vue` | Frontend | Approval-specific UI components |
| `tests/Unit/Endpoint/Api/V1/TimesheetApprovalEndpointTest.php` | Test | Endpoint tests for all approval actions |
| `tests/Unit/Service/TimesheetApprovalServiceTest.php` | Test | Unit tests for state machine logic |

### 12.2 Existing Files to Modify

| File | Layer | Change Description | Conflict Risk |
|------|-------|-------------------|---------------|
| `app/Service/TimesheetService.php` | Backend | Add lock check in `updateCell()` (line 168); add `approval_status`/`approval_id` to `getWeekList()` return (line 38) | **HIGH** |
| `app/Http/Controllers/Api/V1/TimeEntryController.php` | Backend | Add lock checks in `store()` (line 577), `update()` (line 626), `updateMultiple()` (line 689), `destroy()` (line 789), `destroyMultiple()` (line 820) | **HIGH** |
| `app/Providers/JetstreamServiceProvider.php` | Backend | Add 6 new permissions to Owner (line 82), Admin (line 149), Manager (line 213), Employee (line 266) role arrays | MEDIUM |
| `routes/api.php` | Backend | Append new route group for `timesheet-approvals` after line 122 | LOW |
| `app/Models/Organization.php` | Backend | Add casts for new `timesheet_*` columns | LOW |
| `app/Service/Import/ImportService.php` | Backend | Add lock validation after import completes (line 36) | LOW |
| `resources/js/utils/useTimesheet.ts` | Frontend | Add `submitWeek()`, `withdrawSubmission()`, `canEditCell()` actions; update `loadWeekList()` to handle approval_status | **HIGH** |
| `resources/js/types/timesheet.d.ts` | Frontend | Add `approval_status` and `approval_id` to `WeekSummary` interface | MEDIUM |
| `resources/js/packages/ui/src/Timesheet/TimesheetWeekAccordion.vue` | Frontend | Add submit/withdraw buttons, approval status badges, cell read-only states | **HIGH** |
| `resources/js/Layouts/AppLayout.vue` | Frontend | Add "Approvals" navigation sidebar item for managers/admins | LOW |
| `app/Console/Kernel.php` (or `routes/console.php`) | Backend | Register `SendTimesheetRemindersCommand` schedule | LOW |
| `tests/Unit/Endpoint/Api/V1/TimesheetEndpointTest.php` | Test | Update assertions if `weeks` response shape changes | MEDIUM |

### 12.3 Files Read-Only Referenced (No Modifications)

These files are architectural references that inform the implementation but do not need to be changed:

| File | Relevance |
|------|-----------|
| `app/Http/Controllers/Api/V1/Controller.php` | Base controller pattern (checkPermission, user, member helpers) |
| `app/Http/Controllers/Controller.php` | Root controller with user()/member() helpers |
| `app/Service/PermissionStore.php` | Permission caching and resolution logic |
| `app/Http/Requests/V1/BaseFormRequest.php` | Base form request to extend |
| `app/Models/Concerns/HasUuids.php` | UUID trait for new model |
| `app/Models/Concerns/CustomAuditable.php` | Audit trait for new model |
| `app/Models/TimeEntry.php` | Model pattern reference (traits, casts, relationships) |
| `app/Models/Member.php` | Model that TimesheetApproval relates to |
| `app/Mail/TimeEntryStillRunningMail.php` | Mailable pattern reference |
| `app/Http/Middleware/CheckOrganizationBlocked.php` | Middleware to apply on write endpoints |
| `tests/TestCaseWithDatabase.php` | Test helper methods (createUserWithPermission, createUserWithRole) |
| `tests/Unit/Endpoint/Api/V1/ApiEndpointTestAbstract.php` | Base test class for endpoint tests |

---

## Appendix A: Test Infrastructure Reference

### A.1 Test Setup Pattern

**File**: `/home/keven/Documents/solidtime-analysis/tests/TestCaseWithDatabase.php`

```php
// Line 24 - creates user with custom permission set
protected function createUserWithPermission(array $permissions = [], bool $isOwner = false): object
{
    // Returns: object{user, organization, member, owner, ownerMember}
    $roleName = 'custom-test-'.Str::uuid();
    Jetstream::role($roleName, 'Custom Test', $permissions)->description('Role custom for testing');
    $user = User::factory()->create();
    // ... creates org, member, associates ...
    return (object) ['user' => $user, 'organization' => $organization, 'member' => $member, ...];
}

// Line 59 - creates user with a real role
public function createUserWithRole(Role $role, bool $employeesCanSeeBillableRates = false): object
{
    // Returns: same shape as above
}
```

### A.2 Endpoint Test Pattern

**File**: `/home/keven/Documents/solidtime-analysis/tests/Unit/Endpoint/Api/V1/TimesheetEndpointTest.php`

```php
#[UsesClass(TimesheetController::class)]
class TimesheetEndpointTest extends ApiEndpointTestAbstract
{
    public function test_weeks_endpoint_fails_if_user_has_no_permission(): void
    {
        // Arrange
        $data = $this->createUserWithPermission();  // no permissions
        Passport::actingAs($data->user);

        // Act
        $response = $this->getJson(route('api.v1.timesheet.weeks', [$data->organization->getKey()]));

        // Assert
        $response->assertForbidden();
    }

    public function test_weeks_endpoint_returns_week_list_with_totals(): void
    {
        // Arrange
        $data = $this->createUserWithPermission(['time-entries:view:own']);
        TimeEntry::factory()
            ->forOrganization($data->organization)
            ->forMember($data->member)
            ->startWithDuration($weekStart->copy()->addHours(9), 3600)
            ->create();
        Passport::actingAs($data->user);

        // Act
        $response = $this->getJson(route('api.v1.timesheet.weeks', [...]));

        // Assert
        $this->assertResponseCode($response, 200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['week_start', 'week_end', 'label', 'total_seconds'],
            ],
        ]);
    }
}
```

Key observations:
- Uses `Passport::actingAs()` for authentication
- Uses `route('api.v1.timesheet.weeks', [...])` for URL generation
- Uses `$this->assertResponseCode()` from `ApiEndpointTestAbstract` (dumps response body on failure for debugging)
- Factory methods like `forOrganization()`, `forMember()`, `startWithDuration()` for test data setup

### A.3 New Approval Endpoint Tests Should Cover

1. Permission checks (forbidden without correct permission)
2. Submit: success, empty week (422), running timer (422), already submitted (409)
3. Withdraw: success, wrong state (403)
4. Approve: success, self-approval blocked, wrong state (422)
5. Request changes: success with reason, missing reason (422)
6. Reopen: success (admin only), forbidden for manager
7. Lock enforcement: updateCell returns 423 when week is submitted/approved
8. Lock enforcement: TimeEntry CRUD returns 423 when in locked period
9. Index: filtering by status, member, date range; pagination; manager team scope

---

## Appendix B: Middleware Chain Reference

All API requests pass through this middleware stack (defined in `routes/api.php`):

```
Route::prefix('v1')->name('v1.')->group(function () {
    Route::middleware([
        'auth:api',        // Laravel Passport authentication
        'verified',        // Email verification check
    ])->group(function () {
        // All authenticated routes here
        // Write endpoints additionally use:
        //   ->middleware('check-organization-blocked')
    });
});
```

The `check-organization-blocked` middleware (`app/Http/Middleware/CheckOrganizationBlocked.php`) verifies the organization has an active subscription (or is not blocked) before allowing write operations. All new approval write endpoints (submit, withdraw, approve, requestChanges, reopen) must include this middleware.

---

## Appendix C: Route Naming Convention

Per `CLAUDE.md` and the existing `routes/api.php` structure:

```
Route::name('v1.{feature}.')->prefix('/organizations/{organization}')->group(...)
```

New timesheet approval routes should be registered as:

```php
Route::name('timesheet-approvals.')->prefix('/organizations/{organization}')->group(static function (): void {
    Route::post('/timesheet-approvals/submit', [..., 'submit'])->name('submit')->middleware('check-organization-blocked');
    Route::post('/timesheet-approvals/{timesheetApproval}/withdraw', [..., 'withdraw'])->name('withdraw')->middleware('check-organization-blocked');
    Route::post('/timesheet-approvals/{timesheetApproval}/approve', [..., 'approve'])->name('approve')->middleware('check-organization-blocked');
    Route::post('/timesheet-approvals/{timesheetApproval}/request-changes', [..., 'requestChanges'])->name('request-changes')->middleware('check-organization-blocked');
    Route::post('/timesheet-approvals/{timesheetApproval}/reopen', [..., 'reopen'])->name('reopen')->middleware('check-organization-blocked');
    Route::get('/timesheet-approvals', [..., 'index'])->name('index');
    Route::get('/timesheet-approvals/my', [..., 'my'])->name('my');
});
```

This produces route names like `api.v1.timesheet-approvals.submit`, `api.v1.timesheet-approvals.approve`, etc. (per AMD-07).
