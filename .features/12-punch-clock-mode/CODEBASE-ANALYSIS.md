# Codebase Analysis: Feature 12 -- Punch-Only / Time-Clock Mode

**Date**: 2026-02-09
**Branch analyzed**: `main` (via `feature/weekly-timesheet-grid`)
**Target feature branch**: `feature/punch-clock-mode`
**PRD reference**: `.features/12-punch-clock-mode/PRD.md`

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Existing TimeEntry Infrastructure](#2-existing-timeentry-infrastructure)
3. [Existing TimeEntryController Write Endpoints](#3-existing-timeentrycontroller-write-endpoints)
4. [Existing TimesheetController Write Endpoints](#4-existing-timesheetcontroller-write-endpoints)
5. [Organization Model and Settings Pattern](#5-organization-model-and-settings-pattern)
6. [Member Model and Role System](#6-member-model-and-role-system)
7. [Permission System Patterns](#7-permission-system-patterns)
8. [Middleware Patterns](#8-middleware-patterns)
9. [Enum Patterns](#9-enum-patterns)
10. [Service Layer Patterns](#10-service-layer-patterns)
11. [Frontend Store Patterns](#11-frontend-store-patterns)
12. [Route Registration Patterns](#12-route-registration-patterns)
13. [Request Validation Patterns](#13-request-validation-patterns)
14. [API Resource Patterns](#14-api-resource-patterns)
15. [Data Flow Diagrams](#15-data-flow-diagrams)
16. [Feature 06 Kiosk Compatibility](#16-feature-06-kiosk-compatibility)
17. [File Modification Risk Assessment](#17-file-modification-risk-assessment)

---

## 1. Executive Summary

The Solidtime codebase provides a well-established set of patterns for the Punch-Clock Mode feature to follow. The key integration points are the existing `TimeEntryController` write endpoints (which need guard middleware), the `Organization` and `Member` models (which need new boolean columns), and the `TimeEntry` model (which needs a new source column).

**Key findings**:

- **Three schema changes required** -- new boolean columns on `organizations` and `members`, new varchar column on `time_entries`
- **Six write endpoints to guard** -- all identified in `routes/api.php` with consistent route naming and middleware patterns
- **Middleware pattern is well-established** -- `CheckOrganizationBlocked` provides an exact template for `PunchClockGuard`
- **Organization settings pattern exists** -- `prevent_overlapping_time_entries` is an identical boolean toggle pattern on the Organization model
- **Member role checking is straightforward** -- the `Role` enum has `Owner`, `Admin`, `Manager`, `Employee`, `Placeholder` values
- **No existing kiosk feature code found** -- Feature 06 is planned but not yet implemented, so there is no conflict
- **Permission system uses Jetstream roles** -- permissions are registered per-role via `JetstreamServiceProvider`
- **Frontend follows Pinia + Vue 3 patterns** -- stores in `resources/js/utils/`, components in `resources/js/packages/ui/src/`
- **Merge conflict risk is LOW** -- most changes are new files; modifications to shared files are additive

---

## 2. Existing TimeEntry Infrastructure

### 2.1 TimeEntry Model

**File**: `app/Models/TimeEntry.php`

Key characteristics relevant to this feature:
- Uses `HasUuids` trait (UUID primary keys)
- Uses `CustomAuditable` trait (audit logging on all mutations -- punch-in/punch-out will be audited automatically)
- `start` and `end` are Carbon datetime columns (stored in UTC)
- `end` is nullable (`null` = running timer; same pattern used for punch-in)
- `billable` is boolean, `tags` is cast to array
- `project_id`, `task_id`, `client_id` are nullable UUIDs
- `billable_rate` is a computed attribute via `BillableRateService`
- `client_id` is a computed attribute derived from `project.client_id`

**Current `$casts`**:
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

**Action needed**: Add `'time_entry_source' => TimeEntrySource::class` to `$casts`.

**Current `SELECT_COLUMNS`**:
```php
public const array SELECT_COLUMNS = [
    'id', 'description', 'start', 'end', 'billable_rate', 'billable',
    'user_id', 'organization_id', 'project_id', 'task_id', 'tags',
    'created_at', 'updated_at', 'member_id', 'client_id', 'is_imported',
    'still_active_email_sent_at',
];
```

**Action needed**: Add `'time_entry_source'` to the array.

### 2.2 TimeEntry Database Schema

```sql
-- Relevant columns (from existing migrations):
id              UUID PRIMARY KEY
start           TIMESTAMP NOT NULL    -- UTC datetime
end             TIMESTAMP NULL        -- NULL = running timer / punched in
project_id      UUID NULL             -- FK to projects
task_id         UUID NULL             -- FK to tasks
user_id         UUID NOT NULL         -- FK to users
member_id       UUID NOT NULL         -- FK to members
organization_id UUID NOT NULL         -- FK to organizations
billable        BOOLEAN NOT NULL
description     TEXT NOT NULL
tags            JSONB NOT NULL        -- Array of tag IDs
client_id       UUID NULL             -- FK to clients (computed)
billable_rate   INTEGER NULL          -- Cents (computed)
is_imported     BOOLEAN NOT NULL DEFAULT false
```

**Action needed**: Add `time_entry_source VARCHAR(20) NULL` with index.

### 2.3 Running Timer Pattern

The "running timer" concept (entry with `end = null`) is the same mechanism used for punch-in. Existing code that checks for running timers:

**`TimeEntryController::store()`** (line 587-588):
```php
if ($request->input('end') === null && TimeEntry::query()
    ->whereBelongsTo($member, 'member')
    ->where('end', null)
    ->exists()) {
    throw new TimeEntryStillRunningApiException;
}
```

**`UserTimeEntryController::myActive()`** -- returns the user's running entry:
```php
Route::get('/users/me/time-entries/active', [UserTimeEntryController::class, 'myActive'])->name('my-active');
```

**Implication**: Punch-in creates entries using the exact same `end = null` pattern. The existing `myActive` endpoint will return the punch-clock entry, which is the desired behavior.

### 2.4 Overlap Prevention

**`TimeEntryController::assertNoOverlap()`** (lines 61-96):

This private method checks for overlapping time entries when `organization.prevent_overlapping_time_entries` is `true`. The `PunchClockService::punchIn()` method needs to reuse this logic.

**Options**:
1. Extract `assertNoOverlap` to a shared service (e.g., `TimeEntryService`)
2. Duplicate the logic in `PunchClockService`
3. Call it via `TimeEntryController` (not practical -- it's a private method)

**Recommended approach**: Option 1 -- extract to `TimeEntryService` or create a trait. However, for minimal impact to existing code, Option 2 (duplication with the same logic) is acceptable for V1. The overlap check for punch-in is simpler because punch-in always creates an open-ended entry (`end = null`), so only the `start` needs to be checked against existing entries' `start..end` ranges.

### 2.5 Recalculation Jobs

When time entries are created or updated, the following jobs are dispatched:
- `RecalculateSpentTimeForProject` -- updates `spent_time` on the project
- `RecalculateSpentTimeForTask` -- updates `spent_time` on the task

These are dispatched in both `TimeEntryController::store()` (line 609-614) and `TimeEntryController::update()` (line 666-677). The `PunchClockService::punchOut()` must dispatch these same jobs.

---

## 3. Existing TimeEntryController Write Endpoints

**File**: `app/Http/Controllers/Api/V1/TimeEntryController.php`

The following write endpoints need the `PunchClockGuard` middleware applied:

### 3.1 `store()` -- Create time entry (POST /time-entries)

- Line 577-617
- Permission: `time-entries:create:own` or `time-entries:create:all`
- Creates a new `TimeEntry`, checks for running timers, checks overlaps
- **Guard needed**: Block restricted members from creating entries via this endpoint
- **Source tracking needed**: Set `time_entry_source` based on `end` being null (timer) or not (manual)

### 3.2 `update()` -- Update time entry (PUT /time-entries/{timeEntry})

- Line 626-680
- Permission: `time-entries:update:own` or `time-entries:update:all`
- Updates an existing `TimeEntry`, checks overlaps
- **Guard needed**: Block restricted members from editing their entries

### 3.3 `updateMultiple()` -- Bulk update (PATCH /time-entries)

- Line 689-780
- Permission: `time-entries:update:all` or `time-entries:update:own`
- Bulk updates multiple entries
- **Guard needed**: Block restricted members from bulk editing

### 3.4 `destroy()` -- Delete time entry (DELETE /time-entries/{timeEntry})

- Line 789-811
- Permission: `time-entries:delete:own` or `time-entries:delete:all`
- Deletes a single entry
- **Guard needed**: Block restricted members from deleting entries

### 3.5 `destroyMultiple()` -- Bulk delete (DELETE /time-entries)

- Line 820-873
- Permission: `time-entries:delete:all` or `time-entries:delete:own`
- Bulk deletes multiple entries
- **Guard needed**: Block restricted members from bulk deleting

**Important**: The guard checks the *acting user* (the one making the API call), NOT the owner of the time entry. This means a manager with `time-entries:update:all` who is NOT punch-clock restricted can still edit a restricted member's entries. This is the intended behavior -- the restriction applies to the restricted member's own actions only.

---

## 4. Existing TimesheetController Write Endpoints

**File**: `app/Http/Controllers/Api/V1/TimesheetController.php` (from Feature 00)

### 4.1 `updateCell()` -- Update timesheet cell (PUT /timesheet/cell)

- The only write endpoint on the timesheet controller
- Permission: `time-entries:create:own` or `time-entries:create:all`
- Creates, updates, or deletes time entries for a grid cell
- **Guard needed**: Block restricted members from editing the timesheet grid
- **Source tracking needed**: Set `time_entry_source = timesheet_grid` when creating new entries

The guard middleware is applied at the route level, so the controller code itself does not change.

---

## 5. Organization Model and Settings Pattern

**File**: `app/Models/Organization.php`

The Organization model already has several boolean settings that follow the exact pattern needed for `punch_clock_mode_enabled`:

**Existing boolean settings**:
```php
protected $casts = [
    'employees_can_see_billable_rates' => 'boolean',
    'employees_can_manage_tasks' => 'boolean',
    'prevent_overlapping_time_entries' => 'boolean',
    // ... other casts
];
```

**Pattern to follow**: `prevent_overlapping_time_entries` is the closest analog. It is:
- A boolean column on `organizations` with `DEFAULT FALSE`
- Cast to boolean in the model
- Included in `OrganizationUpdateRequest` rules
- Accessible via `$organization->prevent_overlapping_time_entries`
- Toggled via `PUT /organizations/{organization}` endpoint

**Action needed**: Add `'punch_clock_mode_enabled' => 'boolean'` to `$casts` and add the corresponding rule in `OrganizationUpdateRequest`.

### 5.1 OrganizationController::update()

**File**: `app/Http/Controllers/Api/V1/OrganizationController.php`

The update method handles all organization settings. The new `punch_clock_mode_enabled` field will be handled automatically since it is included in the request validation and the Organization model is updated via `fill()` or direct attribute assignment.

---

## 6. Member Model and Role System

**File**: `app/Models/Member.php`

### 6.1 Member Model Structure

- Extends `JetstreamMembership` (which is a pivot model between users and organizations)
- Has `role` string column (matches `Role` enum values: `owner`, `admin`, `manager`, `employee`, `placeholder`)
- Has `billable_rate` and `weekly_capacity` columns
- Has relationships: `user()`, `organization()`, `timeEntries()`, `projectMembers()`

**Current `$casts`**:
```php
protected $casts = [
    'weekly_capacity' => 'integer',
    'notification_preferences' => 'array',
];
```

**Action needed**: Add `'is_punch_clock_restricted' => 'boolean'` to `$casts`.

### 6.2 Role Enum

**File**: `app/Enums/Role.php`

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

The `PunchClockService::isRestricted()` method needs to check:
```php
$role = $member->role;
if ($role === Role::Owner->value || $role === Role::Admin->value) {
    return false;
}
```

### 6.3 MemberController::update()

**File**: `app/Http/Controllers/Api/V1/MemberController.php`

The existing `update()` method (line 79-96) handles `billable_rate` and `role` updates. It:
- Checks `members:update` permission
- Validates the member belongs to the organization
- Handles role changes via `MemberService::changeRole()`

**Action needed**: Add handling for `is_punch_clock_restricted` in the `update()` method. The logic should:
1. Check that `organization.punch_clock_mode_enabled` is `true`
2. Check that the member's role is not `owner` or `admin`
3. Return 422 if either validation fails

This can be added as an additional block after the existing `role` change handling:
```php
if ($request->has('is_punch_clock_restricted')) {
    if (! $organization->punch_clock_mode_enabled) {
        // Return 422: punch-clock mode is not enabled
    }
    if (in_array($member->role, [Role::Owner->value, Role::Admin->value], true)) {
        // Return 422: cannot restrict Owner or Admin
    }
    $member->is_punch_clock_restricted = $request->getIsPunchClockRestricted();
}
```

---

## 7. Permission System Patterns

### 7.1 Permission Registration

**File**: `app/Service/PermissionStore.php`

Permissions are resolved per-user per-organization by:
1. Looking up the user's membership role in the organization
2. Finding the corresponding Jetstream role definition
3. Returning the role's permission array

The `PermissionStore` also adds dynamic permissions (e.g., `tasks:create` for employees when `employees_can_manage_tasks` is true).

**Pattern for new permissions**: The punch-clock permissions (`punch-clock:configure`, `punch-clock:view:all`) need to be added to the appropriate Jetstream role definitions. This is done in the service provider boot method.

### 7.2 Permission Checking in Controllers

The base `Controller` class provides:
```php
protected function checkPermission(Organization $organization, string $permission): void
protected function checkAnyPermission(Organization $organization, array $permissions): void
protected function hasPermission(Organization $organization, string $permission): bool
```

All three methods use `PermissionStore::has()` internally.

### 7.3 Existing Permission Pattern

Existing permission naming follows `{entity}:{action}:{scope}`:
- `time-entries:view:own`
- `time-entries:create:own`
- `time-entries:update:all`
- `members:update`
- `organizations:update`

New permissions follow the same pattern:
- `punch-clock:configure` (no scope -- org-level action)
- `punch-clock:view:all` (view all members' restriction status)

---

## 8. Middleware Patterns

### 8.1 CheckOrganizationBlocked

**File**: `app/Http/Middleware/CheckOrganizationBlocked.php`

This is the exact pattern for `PunchClockGuard`:

```php
class CheckOrganizationBlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        $organization = $request->route('organization');

        if (! ($organization instanceof Organization)) {
            throw new \LogicException('The organization must be loaded before this middleware.');
        }

        /** @var BillingContract $billing */
        $billing = app(BillingContract::class);

        if ($billing->isBlocked($organization)) {
            throw new OrganizationHasNoSubscriptionButMultipleMembersException;
        }

        return $next($request);
    }
}
```

**Key patterns to follow**:
1. Resolve `$organization` from route model binding via `$request->route('organization')`
2. Type check the organization (defensive coding)
3. Perform the check and return 403 or call `$next($request)`
4. The middleware is registered as an alias and applied per-route

### 8.2 Middleware Registration

**File**: `app/Http/Kernel.php` (or `bootstrap/app.php` for Laravel 11)

The `check-organization-blocked` alias is registered in the middleware aliases section:
```php
'check-organization-blocked' => \App\Http\Middleware\CheckOrganizationBlocked::class,
```

**Action needed**: Add `'punch-clock-guard' => \App\Http\Middleware\PunchClockGuard::class` to the aliases.

### 8.3 Route-Level Middleware Application

Middleware is applied per-route in `routes/api.php`:
```php
Route::post('/time-entries', [TimeEntryController::class, 'store'])
    ->name('store')
    ->middleware('check-organization-blocked');
```

**Action needed**: Add `'punch-clock-guard'` to the middleware chain on the 6 affected write routes:
```php
Route::post('/time-entries', [TimeEntryController::class, 'store'])
    ->name('store')
    ->middleware(['check-organization-blocked', 'punch-clock-guard']);
```

---

## 9. Enum Patterns

### 9.1 Existing Enums

**Directory**: `app/Enums/`

Existing enums include:
- `Role.php` -- `Owner`, `Admin`, `Manager`, `Employee`, `Placeholder`
- `ExportFormat.php` -- `CSV`, `PDF`, `XLSX`, `ODS`
- `Weekday.php` -- `Monday` through `Sunday`
- `TimeEntryRoundingType.php`
- `TimeEntryAggregationType.php`

All are PHP 8.1 backed enums with string values:
```php
enum Role: string
{
    case Owner = 'owner';
    // ...
}
```

**Pattern to follow**: `TimeEntrySource` follows the exact same pattern.

### 9.2 Enum Casting in Models

Existing pattern for casting enums in models:
```php
// In Organization model:
'number_format' => NumberFormat::class,
'currency_format' => CurrencyFormat::class,
```

**Pattern to follow**: `'time_entry_source' => TimeEntrySource::class` in the `TimeEntry` model.

---

## 10. Service Layer Patterns

### 10.1 Service Conventions

**Directory**: `app/Service/`

- Stateless classes (no constructor state, or constructor DI only for other services)
- Methods accept model instances (Organization, Member) not IDs
- Return plain arrays or models (not Eloquent collections or resources)
- Injected into controllers via constructor or method type-hints

### 10.2 Relevant Existing Services

**`TimesheetService`** (`app/Service/TimesheetService.php`):
- Closest pattern to `PunchClockService`
- Stateless, accepts Organization + Member parameters
- Creates/updates/deletes `TimeEntry` records
- Returns structured arrays

**`TimeEntryFilter`** (`app/Service/TimeEntryFilter.php`):
- Builder pattern for query filtering
- Each filter method returns `$this` for chaining
- `get()` returns the builder
- **Action needed**: Add `addSourceFilter(?string $source)` method

**`BillableRateService`** (`app/Service/BillableRateService.php`):
- Used via `$timeEntry->setComputedAttributeValue('billable_rate')`
- Must be called in `PunchClockService::punchIn()` and `punchOut()`

**`PermissionStore`** (`app/Service/PermissionStore.php`):
- Caches permissions per-user per-organization
- Used by the base `Controller` class
- Not directly used by middleware (middleware resolves its own member)

---

## 11. Frontend Store Patterns

### 11.1 Existing Pinia Store Pattern

**Location**: `resources/js/utils/`

Stores export a `defineStore` with composition API (setup function):
```typescript
export const useTimesheetStore = defineStore('timesheet', () => {
    const weekList = ref<WeekSummary[]>([]);
    const isLoadingList = ref(false);

    async function loadWeekList() {
        isLoadingList.value = true;
        try {
            const response = await api.getTimesheetWeeks({...});
            weekList.value = response.data.data;
        } finally {
            isLoadingList.value = false;
        }
    }

    return { weekList, isLoadingList, loadWeekList };
});
```

**Pattern to follow**: `usePunchClockStore` follows the exact same pattern with `ref()` state, async actions, and exported getters.

### 11.2 API Client Pattern

**File**: `resources/js/packages/api/src/openapi.json.client.ts`

Auto-generated TypeScript client from OpenAPI spec. After updating `openapi.json` and regenerating, the client provides typed methods for the new endpoints.

### 11.3 Organization Context

**File**: `resources/js/utils/useUser.ts`

`getCurrentOrganizationId()` returns the current organization UUID for API calls.

---

## 12. Route Registration Patterns

### 12.1 API Routes

**File**: `routes/api.php`

API routes are registered inside nested middleware groups:
```php
Route::prefix('v1')->name('v1.')->group(static function (): void {
    Route::middleware(['auth:api', 'verified'])->group(static function (): void {
        // Feature route groups here
    });
});
```

Feature routes are grouped by prefix and name:
```php
Route::name('time-entries.')->prefix('/organizations/{organization}')->group(static function (): void {
    Route::post('/time-entries', [TimeEntryController::class, 'store'])
        ->name('store')
        ->middleware('check-organization-blocked');
    // ...
});
```

**Pattern to follow**: Punch-clock routes follow the same group structure:
```php
Route::name('punch-clock.')->prefix('/organizations/{organization}')->group(static function (): void {
    Route::post('/punch-clock/in', [PunchClockController::class, 'punchIn'])
        ->name('in')
        ->middleware('check-organization-blocked');
    // ...
});
```

### 12.2 Existing Write Routes Needing Guard

From `routes/api.php` (lines 105-115, 118-123):

```php
// Time entry write routes (6 total):
Route::post('/time-entries', ...)->name('store')->middleware('check-organization-blocked');
Route::put('/time-entries/{timeEntry}', ...)->name('update')->middleware('check-organization-blocked');
Route::patch('/time-entries', ...)->name('update-multiple')->middleware('check-organization-blocked');
Route::delete('/time-entries/{timeEntry}', ...)->name('destroy');
Route::delete('/time-entries', ...)->name('destroy-multiple');

// Timesheet write route (1 total):
Route::put('/timesheet/cell', ...)->name('update-cell')->middleware('check-organization-blocked');
```

**Action needed**: Add `'punch-clock-guard'` middleware to all 6 routes.

Note: The `destroy` and `destroy-multiple` routes currently do NOT have `check-organization-blocked` middleware. The `punch-clock-guard` middleware should still be added to these routes (blocking restricted members from deleting entries regardless of billing status).

---

## 13. Request Validation Patterns

### 13.1 Base Request

**File**: `app/Http/Requests/V1/BaseFormRequest.php`

All request classes extend this. It provides:
- Access to `$this->organization` via route model binding
- Standard authorization logic
- `moneyRules()` helper for integer money validation

### 13.2 ExistsEloquent Validation

**Package**: `korridor/laravel-model-validation-rules`

Used to validate that a referenced entity exists and belongs to the correct organization:
```php
'project_id' => [
    'nullable', 'string',
    new ExistsEloquent(Project::class, null, function (Builder $builder): Builder {
        return $builder->whereBelongsTo($this->organization, 'organization');
    }),
],
```

**Pattern to follow**: `PunchInRequest` uses the same pattern for `project_id` validation.

### 13.3 OrganizationUpdateRequest

**File**: `app/Http/Requests/V1/Organization/OrganizationUpdateRequest.php`

Existing boolean rules:
```php
'employees_can_see_billable_rates' => ['boolean'],
'employees_can_manage_tasks' => ['boolean'],
'prevent_overlapping_time_entries' => ['boolean'],
```

**Action needed**: Add `'punch_clock_mode_enabled' => ['boolean']`.

### 13.4 MemberUpdateRequest

**File**: `app/Http/Requests/V1/Member/MemberUpdateRequest.php`

Current rules:
```php
'role' => ['string', Rule::enum(Role::class)],
'billable_rate' => ['nullable', ...$this->moneyRules()],
```

**Action needed**: Add `'is_punch_clock_restricted' => ['boolean']` with custom validation logic in `withValidator()`:
- Organization must have `punch_clock_mode_enabled = true`
- Member role must not be `owner` or `admin`

---

## 14. API Resource Patterns

### 14.1 TimeEntryResource

**File**: `app/Http/Resources/V1/TimeEntry/TimeEntryResource.php`

Current output:
```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->resource->id,
        'start' => $this->formatDateTime($this->resource->start),
        'end' => $this->formatDateTime($this->resource->end),
        'duration' => (int) $this->resource->getDuration()?->totalSeconds,
        'description' => $this->resource->description,
        'task_id' => $this->resource->task_id,
        'project_id' => $this->resource->project_id,
        'organization_id' => $this->resource->organization_id,
        'user_id' => $this->resource->user_id,
        'tags' => $this->resource->tags ?? [],
        'billable' => $this->resource->billable,
    ];
}
```

**Action needed**: Add `'time_entry_source' => $this->resource->time_entry_source?->value` to the output array. The `?->value` handles the nullable enum (existing entries have `null` source).

---

## 15. Data Flow Diagrams

### 15.1 Punch-In Flow

```
Restricted member clicks "Punch In" in UI
    -> usePunchClockStore.punchIn(projectId?, taskId?)
    -> POST /api/v1/organizations/{org}/punch-clock/in
        { project_id: "uuid", task_id: null }
    -> Middleware: auth:api, verified, check-organization-blocked
    -> PunchClockController::punchIn()
        -> checkPermission('time-entries:create:own')
        -> $member = $this->member($organization)
        -> punchClockService->isRestricted($member, $organization) -- must be TRUE
        -> punchClockService->punchIn($organization, $member, $projectId, $taskId)
            -> Check no active entry (WHERE end IS NULL)
            -> Create TimeEntry { start: now(), end: null, source: punch_clock }
            -> setComputedAttributeValue('billable_rate')
            -> save()
            -> Log punch-in
        -> Return TimeEntryResource (201)
    -> Store: update isPunchedIn = true, activeEntry = response.data
    -> UI: Switch to "Punch Out" button with running duration
```

### 15.2 Punch-Out Flow

```
Restricted member clicks "Punch Out" in UI
    -> usePunchClockStore.punchOut()
    -> POST /api/v1/organizations/{org}/punch-clock/out
    -> Middleware: auth:api, verified, check-organization-blocked
    -> PunchClockController::punchOut()
        -> checkPermission('time-entries:update:own')
        -> $member = $this->member($organization)
        -> punchClockService->isRestricted($member, $organization) -- must be TRUE
        -> punchClockService->punchOut($organization, $member)
            -> Find active entry (WHERE end IS NULL)
            -> Set end = now()
            -> setComputedAttributeValue('billable_rate')
            -> save()
            -> Dispatch RecalculateSpentTimeForProject
            -> Dispatch RecalculateSpentTimeForTask
            -> Log punch-out
        -> Return TimeEntryResource (200)
    -> Store: update isPunchedIn = false, activeEntry = null
    -> UI: Switch back to "Punch In" button
```

### 15.3 Guard Middleware Flow (Existing Endpoint)

```
Restricted member attempts POST /time-entries (via API or UI)
    -> Middleware chain: auth:api, verified, check-organization-blocked, punch-clock-guard
    -> PunchClockGuard::handle()
        -> $organization = $request->route('organization')
        -> $organization->punch_clock_mode_enabled == true? (fast bail if false)
        -> Resolve member from Auth::user() + organization
        -> punchClockService->isRestricted($member, $organization)
            -> Returns TRUE (org enabled, member restricted, role is employee)
        -> Return 403 JSON { error: { type: 'punch_clock_restricted', message: '...' } }
    -> Request NEVER reaches TimeEntryController::store()

Manager attempts POST /time-entries for restricted member
    -> PunchClockGuard::handle()
        -> Resolve MANAGER's member record (not the entry owner)
        -> punchClockService->isRestricted(managerMember, $organization)
            -> Returns FALSE (manager is not restricted, even if the entry is for a restricted member)
        -> $next($request) -- PASS THROUGH
    -> TimeEntryController::store() executes normally
```

### 15.4 Restriction State Detection (Page Load)

```
User navigates to Time page
    -> Time.vue onMounted()
    -> usePunchClockStore.fetchStatus()
    -> GET /api/v1/organizations/{org}/punch-clock
    -> PunchClockController::status()
        -> punchClockService->getStatus($organization, $member)
        -> Return { is_restricted: true/false, is_punched_in: true/false, active_entry: ... }
    -> Store: set isRestricted, isPunchedIn, activeEntry
    -> Time.vue:
        if isRestricted: render PunchClockRestrictedView
        else: render normal Time tracking view
```

---

## 16. Feature 06 Kiosk Compatibility

### 16.1 Current State

Feature 06 (Kiosk & Clock Mode) is planned but **not yet implemented** in the codebase. There is no kiosk controller, kiosk service, or kiosk-related code on the `main` branch.

### 16.2 Compatibility Design

The `TimeEntrySource` enum reserves the `kiosk` value for Feature 06:
```php
case Kiosk = 'kiosk';
```

When Feature 06 is implemented, kiosk entries will set `time_entry_source = kiosk`, which is distinct from `punch_clock`. This allows filtering and reporting to distinguish between:
- **Punch-clock entries**: Created by restricted members via their own device/browser
- **Kiosk entries**: Created via shared-device PIN/QR authentication

### 16.3 No Conflict Areas

- Feature 06 operates on a separate endpoint namespace (e.g., `/kiosk/...`)
- Feature 06 uses PIN/QR authentication, not standard Passport/Jetstream auth
- The `PunchClockGuard` middleware has no effect on kiosk routes (kiosk uses a different auth mechanism)
- Both features create standard `TimeEntry` records -- they are compatible at the data layer

---

## 17. File Modification Risk Assessment

### 17.1 Risk Matrix

| File | Change Type | Risk | Rationale |
|------|------------|:----:|-----------|
| `routes/api.php` | Add route group + middleware to existing routes | **Medium** | Adding a new route group is low risk, but adding middleware to 6 existing routes could affect behavior if the middleware has bugs. Thorough testing mitigates this. |
| `app/Models/Organization.php` | Add one line to `$casts` | Low | Additive change, no existing code modified |
| `app/Models/Member.php` | Add one line to `$casts` | Low | Additive change, no existing code modified |
| `app/Models/TimeEntry.php` | Add to `$casts` and `SELECT_COLUMNS` | Low | Additive change. The nullable enum cast handles existing `null` values gracefully. |
| `app/Http/Controllers/Api/V1/TimeEntryController.php` | Add source tracking in `store()` | Low | Single line addition before `save()`, no existing logic changed |
| `app/Service/TimesheetService.php` | Add source tracking in `updateCell()` | Low | Single line addition before `save()`, no existing logic changed |
| `app/Service/TimeEntryFilter.php` | Add one new method | Low | New method appended, no existing methods modified |
| `app/Http/Resources/V1/TimeEntry/TimeEntryResource.php` | Add one field to output | Low | Additive change. Existing consumers ignore unknown fields. |
| `app/Http/Requests/V1/Organization/OrganizationUpdateRequest.php` | Add one rule | Low | Additive, no existing rules modified |
| `app/Http/Requests/V1/Member/MemberUpdateRequest.php` | Add one rule + validation logic | Low-Medium | New rule is additive, but custom validation logic in `withValidator()` could interact with existing role validation |
| `app/Http/Controllers/Api/V1/MemberController.php` | Add restriction handling in `update()` | Low-Medium | New conditional block after existing `role` handling. Must not interfere with existing billable_rate or role changes. |
| `openapi.json` | Add 3 endpoints + update schemas | Low | Additive, no existing paths modified |
| `resources/js/packages/ui/src/Timesheet/TimesheetCell.vue` | Add `readonly` prop | Low | New prop with default value, no existing behavior changed when prop is not passed |
| `resources/js/packages/ui/src/Timesheet/TimesheetGrid.vue` | Pass `readonly` to cells | Low | Additive prop passing |
| `resources/js/Pages/Time.vue` | Conditional rendering | Low-Medium | Wraps existing content in `v-else`, adds `v-if` for restricted view |

### 17.2 Merge Conflict Assessment

**Risk: LOW** -- The majority of changes are new files. Modifications to shared files are additive (new casts, new rules, new routes, new middleware). The main area of potential conflict is `routes/api.php` where the middleware addition to existing routes must be done carefully.

### 17.3 Rollback Strategy

If the feature needs to be rolled back:
1. Remove the 3 migrations (columns are all additive with defaults -- `ALTER TABLE DROP COLUMN` is safe)
2. Remove the `punch-clock-guard` middleware from existing routes
3. Remove the new route group
4. Remove the enum cast from `TimeEntry` (existing `null` values are unaffected)
5. The `time_entry_source` values set on entries created during the feature's lifetime will remain as `null`-equivalent (the column is dropped)

### 17.4 Feature Flag Strategy

The feature is inherently feature-flagged via `organization.punch_clock_mode_enabled`:
- When `false` (default): Zero behavioral change for any user or endpoint
- The guard middleware fast-paths when the flag is `false` (zero DB queries)
- The frontend only renders the restricted view when `isRestricted = true`
- This allows progressive rollout on a per-organization basis
