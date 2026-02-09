# Feature 12: Punch-Only / Time-Clock Mode -- Technical Architecture

**Date**: 2026-02-09
**Status**: Draft
**Feature Branch**: `feature/punch-clock-mode` (from `main`)
**Task Prefix**: `PCM-` (per task assignments)

---

## Executive Summary

This document provides the complete technical architecture for the **Punch-Only / Time-Clock Mode** feature. The feature adds a server-enforced restriction that limits designated members to a simple punch-in/punch-out workflow, preventing them from creating, editing, or deleting time entries through any other mechanism (manual entry, timer, timesheet grid, or direct API calls).

**Key Architectural Decisions**:
- **Three schema changes** -- new boolean columns on `organizations` and `members`, plus a new `time_entry_source` column on `time_entries`
- **New `TimeEntrySource` enum** -- tracks how each time entry was created (`manual`, `timer`, `punch_clock`, `kiosk`, `timesheet_grid`)
- **New `PunchClockService`** handles restriction checking, punch-in, punch-out, and status retrieval
- **New `PunchClockController`** with 3 endpoints (`punchIn`, `punchOut`, `status`)
- **New `PunchClockGuard` middleware** blocks 6 existing write endpoints for restricted members
- **Frontend**: Pinia store + 7 Vue components + conditional page rendering
- **New permissions** -- `punch-clock:configure` (Owner/Admin) and `punch-clock:view:all` (Owner/Admin/Manager)
- **Enforcement is server-side** -- UI adaptations are a convenience; the guard middleware is the authoritative enforcement layer

---

## Table of Contents

1. [Data Model Design](#1-data-model-design)
2. [API Contract](#2-api-contract)
3. [Service Layer](#3-service-layer)
4. [Controller Layer](#4-controller-layer)
5. [Middleware / Guard Design](#5-middleware--guard-design)
6. [Request Validation](#6-request-validation)
7. [Frontend Architecture](#7-frontend-architecture)
8. [Permission Matrix](#8-permission-matrix)
9. [Performance Strategy](#9-performance-strategy)
10. [Integration Points](#10-integration-points)
11. [File Manifest](#11-file-manifest)

---

## 1. Data Model Design

### 1.1 New Columns (3 Migrations)

This feature introduces 3 new database columns across 3 migrations. No new tables are required.

**Migration 1: `2026_03_12_000001_add_punch_clock_mode_to_organizations_table.php`**

```php
Schema::table('organizations', function (Blueprint $table): void {
    $table->boolean('punch_clock_mode_enabled')->default(false);
});
```

- **Column**: `punch_clock_mode_enabled` (BOOLEAN, NOT NULL, DEFAULT FALSE)
- **Purpose**: Organization-level feature flag. When `false`, no member can be punch-clock restricted, and the guard middleware has no effect.
- **Rollback**: Drop column

**Migration 2: `2026_03_12_000002_add_punch_clock_restriction_to_members_table.php`**

```php
Schema::table('members', function (Blueprint $table): void {
    $table->boolean('is_punch_clock_restricted')->default(false);
});
```

- **Column**: `is_punch_clock_restricted` (BOOLEAN, NOT NULL, DEFAULT FALSE)
- **Purpose**: Per-member restriction flag. Only effective when `organization.punch_clock_mode_enabled = true` AND the member's role is not `owner` or `admin`.
- **Rollback**: Drop column

**Migration 3: `2026_03_12_000003_add_source_to_time_entries_table.php`**

```php
Schema::table('time_entries', function (Blueprint $table): void {
    $table->string('time_entry_source', 20)->nullable();
    $table->index('time_entry_source');
});
```

- **Column**: `time_entry_source` (VARCHAR(20), NULLABLE)
- **Purpose**: Tracks how each time entry was created. Nullable to preserve backward compatibility with existing entries (which will have `null` source).
- **Index**: Single-column index for filter queries (`WHERE time_entry_source = 'punch_clock'`)
- **Rollback**: Drop index, drop column

### 1.2 New Enum

**File**: `app/Enums/TimeEntrySource.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum TimeEntrySource: string
{
    case Manual = 'manual';
    case Timer = 'timer';
    case PunchClock = 'punch_clock';
    case Kiosk = 'kiosk';
    case TimesheetGrid = 'timesheet_grid';
}
```

- Follows the existing enum pattern in `app/Enums/` (e.g., `Role.php`, `ExportFormat.php`)
- Backed by string values matching the `VARCHAR(20)` column
- `Kiosk` value is reserved for Feature 06 (not used in this feature)

### 1.3 Model Changes

**`app/Models/Organization.php`** -- add to `$casts`:
```php
'punch_clock_mode_enabled' => 'boolean',
```

**`app/Models/Member.php`** -- add to `$casts`:
```php
'is_punch_clock_restricted' => 'boolean',
```

**`app/Models/TimeEntry.php`** -- add to `$casts` and `SELECT_COLUMNS`:
```php
// In $casts:
'time_entry_source' => TimeEntrySource::class,

// In SELECT_COLUMNS (add to the const array):
'time_entry_source',
```

### 1.4 Factory Updates

**`database/factories/OrganizationFactory.php`**:
```php
'punch_clock_mode_enabled' => false,
```

**`database/factories/MemberFactory.php`**:
```php
'is_punch_clock_restricted' => false,
```

### 1.5 Entity Relationship Context

```
Organization (1) ----< Member (N) ----< TimeEntry (N)
     |                    |                   |
     | punch_clock_       | is_punch_clock_   | time_entry_source
     | mode_enabled       | restricted        | (punch_clock | manual | timer | ...)
     |                    |                   |
     +--------------------+------- Combined determine restriction state
```

A member is considered **punch-clock restricted** when ALL three conditions are met:
1. `organization.punch_clock_mode_enabled = true`
2. `member.is_punch_clock_restricted = true`
3. `member.role` is NOT `owner` or `admin`

---

## 2. API Contract

### 2.1 Route Registration

**File**: `routes/api.php` (inside existing `auth:api` + `verified` middleware group)

```php
// New punch-clock routes
Route::name('punch-clock.')->prefix('/organizations/{organization}')->group(static function (): void {
    Route::post('/punch-clock/in', [PunchClockController::class, 'punchIn'])
        ->name('in')
        ->middleware('check-organization-blocked');
    Route::post('/punch-clock/out', [PunchClockController::class, 'punchOut'])
        ->name('out')
        ->middleware('check-organization-blocked');
    Route::get('/punch-clock', [PunchClockController::class, 'status'])
        ->name('status');
});
```

Additionally, the `punch-clock-guard` middleware alias is applied to existing write routes:
```php
// Modified existing time-entry write routes (middleware added):
Route::post('/time-entries', ...)->middleware(['check-organization-blocked', 'punch-clock-guard']);
Route::put('/time-entries/{timeEntry}', ...)->middleware(['check-organization-blocked', 'punch-clock-guard']);
Route::patch('/time-entries', ...)->middleware(['check-organization-blocked', 'punch-clock-guard']);
Route::delete('/time-entries/{timeEntry}', ...)->middleware('punch-clock-guard');
Route::delete('/time-entries', ...)->middleware('punch-clock-guard');

// Modified existing timesheet write route:
Route::put('/timesheet/cell', ...)->middleware(['check-organization-blocked', 'punch-clock-guard']);
```

Route names resolve to:
- `api.v1.punch-clock.in`
- `api.v1.punch-clock.out`
- `api.v1.punch-clock.status`

### 2.2 Endpoint Signatures

| Method | Path | Controller Method | Request Class | Permission |
|--------|------|-------------------|---------------|------------|
| POST | `/punch-clock/in` | `punchIn()` | `PunchInRequest` | `time-entries:create:own` |
| POST | `/punch-clock/out` | `punchOut()` | -- (no body) | `time-entries:update:own` |
| GET | `/punch-clock` | `status()` | -- (no body) | `time-entries:view:own` |

### 2.3 Response Shapes

**POST /punch-clock/in** (201 Created):
```json
{
  "data": {
    "id": "uuid",
    "start": "2026-03-12T09:00:00Z",
    "end": null,
    "duration": null,
    "description": "",
    "task_id": "uuid|null",
    "project_id": "uuid|null",
    "organization_id": "uuid",
    "user_id": "uuid",
    "tags": [],
    "billable": true,
    "time_entry_source": "punch_clock"
  }
}
```

**POST /punch-clock/in** (409 Conflict -- already punched in):
```json
{
  "error": {
    "type": "already_punched_in",
    "message": "Member already has an active time entry",
    "time_entry_id": "uuid"
  }
}
```

**POST /punch-clock/in** (403 Forbidden -- not restricted):
```json
{
  "error": {
    "type": "not_punch_clock_restricted",
    "message": "This endpoint is only available for punch-clock restricted members"
  }
}
```

**POST /punch-clock/out** (200 OK):
```json
{
  "data": {
    "id": "uuid",
    "start": "2026-03-12T09:00:00Z",
    "end": "2026-03-12T17:30:00Z",
    "duration": 30600,
    "description": "",
    "task_id": "uuid|null",
    "project_id": "uuid|null",
    "organization_id": "uuid",
    "user_id": "uuid",
    "tags": [],
    "billable": true,
    "time_entry_source": "punch_clock"
  }
}
```

**POST /punch-clock/out** (404 -- not punched in):
```json
{
  "error": {
    "type": "not_punched_in",
    "message": "No active time entry found"
  }
}
```

**GET /punch-clock** (200 OK):
```json
{
  "data": {
    "is_restricted": true,
    "is_punched_in": true,
    "active_entry": {
      "id": "uuid",
      "start": "2026-03-12T09:00:00Z",
      "end": null,
      "duration": null,
      "project_id": "uuid|null",
      "task_id": "uuid|null",
      "time_entry_source": "punch_clock"
    }
  }
}
```

**Guard middleware rejection** (403 -- on existing write endpoints):
```json
{
  "error": {
    "type": "punch_clock_restricted",
    "message": "Time entry modification is not permitted in punch-clock mode."
  }
}
```

---

## 3. Service Layer

### 3.1 PunchClockService

**File**: `app/Service/PunchClockService.php`

Stateless service class with 4 public methods.

#### `isRestricted(Member $member, Organization $organization): bool`

Determines if a member is currently punch-clock restricted.

```php
public function isRestricted(Member $member, Organization $organization): bool
{
    // 1. Check org-level flag
    if (! $organization->punch_clock_mode_enabled) {
        return false;
    }

    // 2. Check member-level flag
    if (! $member->is_punch_clock_restricted) {
        return false;
    }

    // 3. Owner and Admin roles are never restricted
    $role = $member->role;
    if ($role === Role::Owner->value || $role === Role::Admin->value) {
        return false;
    }

    return true;
}
```

**Key design decision**: This method is called by both the `PunchClockGuard` middleware and the `PunchClockController`. It is the single source of truth for restriction state. The method checks the *acting member* (the authenticated user's member record in the organization), not the owner of a time entry.

#### `punchIn(Organization $organization, Member $member, ?string $projectId, ?string $taskId): TimeEntry`

Creates a new running time entry via punch-in.

```php
public function punchIn(
    Organization $organization,
    Member $member,
    ?string $projectId,
    ?string $taskId
): TimeEntry {
    // 1. Verify no active entry exists
    $activeEntry = TimeEntry::query()
        ->where('organization_id', $organization->getKey())
        ->where('user_id', $member->user_id)
        ->whereNull('end')
        ->first();

    if ($activeEntry !== null) {
        throw new AlreadyPunchedInApiException($activeEntry->getKey());
    }

    // 2. Resolve project and task
    $project = $projectId !== null ? Project::findOrFail($projectId) : null;
    $task = $taskId !== null ? Task::findOrFail($taskId) : null;

    // 3. Check overlap if org has prevent_overlapping_time_entries
    $start = Carbon::now('UTC');
    // Uses same assertNoOverlap pattern as TimeEntryController

    // 4. Create TimeEntry
    $timeEntry = new TimeEntry;
    $timeEntry->description = '';
    $timeEntry->start = $start;
    $timeEntry->end = null;
    $timeEntry->billable = $project?->is_billable ?? false;
    $timeEntry->user_id = $member->user_id;
    $timeEntry->member_id = $member->getKey();
    $timeEntry->organization_id = $organization->getKey();
    $timeEntry->project_id = $projectId;
    $timeEntry->task_id = $taskId;
    $timeEntry->client_id = $project?->client_id;
    $timeEntry->tags = [];
    $timeEntry->is_imported = false;
    $timeEntry->time_entry_source = TimeEntrySource::PunchClock;
    $timeEntry->setComputedAttributeValue('billable_rate');
    $timeEntry->save();

    Log::info('Punch clock: member punched in', [
        'member_id' => $member->getKey(),
        'organization_id' => $organization->getKey(),
        'project_id' => $projectId,
        'task_id' => $taskId,
        'time_entry_id' => $timeEntry->getKey(),
    ]);

    return $timeEntry;
}
```

**Key design decisions**:
- Follows the same time entry creation pattern as `TimeEntryController::store` and `TimesheetService::updateCell`
- Sets `time_entry_source = PunchClock` to distinguish from manual/timer entries
- Inherits `billable` from project's `is_billable` (consistent with existing behavior)
- Computes `billable_rate` and `client_id` via the existing computed attribute system

#### `punchOut(Organization $organization, Member $member): TimeEntry`

Closes the active time entry via punch-out.

```php
public function punchOut(Organization $organization, Member $member): TimeEntry
{
    // 1. Find active entry
    $activeEntry = TimeEntry::query()
        ->where('organization_id', $organization->getKey())
        ->where('user_id', $member->user_id)
        ->whereNull('end')
        ->first();

    if ($activeEntry === null) {
        throw new NotPunchedInApiException;
    }

    // 2. Set end time
    $activeEntry->end = Carbon::now('UTC');
    $activeEntry->setComputedAttributeValue('billable_rate');
    $activeEntry->save();

    // 3. Dispatch recalculation jobs
    if ($activeEntry->project !== null) {
        RecalculateSpentTimeForProject::dispatch($activeEntry->project);
    }
    if ($activeEntry->task !== null) {
        RecalculateSpentTimeForTask::dispatch($activeEntry->task);
    }

    Log::info('Punch clock: member punched out', [
        'member_id' => $member->getKey(),
        'organization_id' => $organization->getKey(),
        'time_entry_id' => $activeEntry->getKey(),
        'duration_seconds' => $activeEntry->end->diffInSeconds($activeEntry->start),
    ]);

    return $activeEntry;
}
```

**Key design decisions**:
- Dispatches `RecalculateSpentTimeForProject` and `RecalculateSpentTimeForTask` jobs, consistent with `TimeEntryController::update`
- Recomputes `billable_rate` on close (in case rate changed during the session)

#### `getStatus(Organization $organization, Member $member): array`

Returns the current punch-clock status for a member.

```php
public function getStatus(Organization $organization, Member $member): array
{
    $isRestricted = $this->isRestricted($member, $organization);

    $activeEntry = TimeEntry::query()
        ->where('organization_id', $organization->getKey())
        ->where('user_id', $member->user_id)
        ->whereNull('end')
        ->first();

    return [
        'is_restricted' => $isRestricted,
        'is_punched_in' => $activeEntry !== null,
        'active_entry' => $activeEntry,
    ];
}
```

### 3.2 Existing Service Modifications

#### `TimesheetService::updateCell()` -- add source tracking

When `updateCell()` creates a new `TimeEntry`, set the source:

```php
$timeEntry->time_entry_source = TimeEntrySource::TimesheetGrid;
```

This is added to the existing `new TimeEntry` block in the `updateCell()` method.

#### `TimeEntryFilter` -- add source filter method

```php
public function addSourceFilter(?string $source): self
{
    if ($source === null) {
        return $this;
    }
    $this->builder->where('time_entry_source', $source);

    return $this;
}
```

---

## 4. Controller Layer

### 4.1 PunchClockController

**File**: `app/Http/Controllers/Api/V1/PunchClockController.php`

Extends `App\Http\Controllers\Api\V1\Controller` (which provides `$this->checkPermission()`, `$this->user()`, `$this->member()`).

**Dependency injection**: `PunchClockService` injected via method type-hints (not constructor).

```php
class PunchClockController extends Controller
{
    public function punchIn(
        Organization $organization,
        PunchInRequest $request,
        PunchClockService $punchClockService
    ): JsonResource {
        $this->checkPermission($organization, 'time-entries:create:own');
        $member = $this->member($organization);

        // Verify member is restricted (punch-in is ONLY for restricted members)
        if (! $punchClockService->isRestricted($member, $organization)) {
            return response()->json([
                'error' => [
                    'type' => 'not_punch_clock_restricted',
                    'message' => 'This endpoint is only available for punch-clock restricted members',
                ],
            ], 403);
        }

        $timeEntry = $punchClockService->punchIn(
            $organization,
            $member,
            $request->input('project_id'),
            $request->input('task_id')
        );

        return (new TimeEntryResource($timeEntry))
            ->response()
            ->setStatusCode(201);
    }

    public function punchOut(
        Organization $organization,
        PunchClockService $punchClockService
    ): JsonResource {
        $this->checkPermission($organization, 'time-entries:update:own');
        $member = $this->member($organization);

        if (! $punchClockService->isRestricted($member, $organization)) {
            return response()->json([
                'error' => [
                    'type' => 'not_punch_clock_restricted',
                    'message' => 'This endpoint is only available for punch-clock restricted members',
                ],
            ], 403);
        }

        $timeEntry = $punchClockService->punchOut($organization, $member);

        return new TimeEntryResource($timeEntry);
    }

    public function status(
        Organization $organization,
        PunchClockService $punchClockService
    ): JsonResponse {
        $this->checkPermission($organization, 'time-entries:view:own');
        $member = $this->member($organization);

        $status = $punchClockService->getStatus($organization, $member);

        return response()->json([
            'data' => [
                'is_restricted' => $status['is_restricted'],
                'is_punched_in' => $status['is_punched_in'],
                'active_entry' => $status['active_entry'] !== null
                    ? new TimeEntryResource($status['active_entry'])
                    : null,
            ],
        ]);
    }
}
```

**Permission strategy**:
- `punchIn()` checks `time-entries:create:own` (same as normal time entry creation)
- `punchOut()` checks `time-entries:update:own` (same as normal time entry update)
- `status()` checks `time-entries:view:own` (read-only)

**Additional guard**: All three endpoints verify that the member IS punch-clock restricted. This is the inverse of the `PunchClockGuard` middleware (which blocks restricted members from normal endpoints). Punch-clock endpoints are exclusively for restricted members.

### 4.2 Existing Controller Modifications

#### `TimeEntryController::store()` -- add source tracking

In the `store` method, after creating the `TimeEntry`, set the source based on whether `end` is null:

```php
$timeEntry->time_entry_source = $request->input('end') === null
    ? TimeEntrySource::Timer
    : TimeEntrySource::Manual;
```

This is added before `$timeEntry->save()`.

---

## 5. Middleware / Guard Design

### 5.1 PunchClockGuard Middleware

**File**: `app/Http/Middleware/PunchClockGuard.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Member;
use App\Models\Organization;
use App\Service\PunchClockService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PunchClockGuard
{
    public function __construct(
        private readonly PunchClockService $punchClockService
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $organization = $request->route('organization');

        if (! ($organization instanceof Organization)) {
            return $next($request);
        }

        // Quick bail: if org doesn't have punch-clock mode enabled, pass through
        if (! $organization->punch_clock_mode_enabled) {
            return $next($request);
        }

        // Resolve the acting member
        $user = Auth::user();
        if ($user === null) {
            return $next($request);
        }

        $member = Member::query()
            ->where('organization_id', $organization->getKey())
            ->where('user_id', $user->getKey())
            ->first();

        if ($member === null) {
            return $next($request);
        }

        if ($this->punchClockService->isRestricted($member, $organization)) {
            return response()->json([
                'error' => [
                    'type' => 'punch_clock_restricted',
                    'message' => 'Time entry modification is not permitted in punch-clock mode.',
                ],
            ], 403);
        }

        return $next($request);
    }
}
```

**Design decisions**:
- Early bail-out: If `punch_clock_mode_enabled` is `false`, the middleware passes through immediately with zero DB queries
- Checks the *acting user's* member record, not the entry owner. This means a manager with `time-entries:update:all` can still edit a restricted member's entries (because the manager's own member record is not restricted)
- Returns a specific `punch_clock_restricted` error type for frontend detection
- Uses constructor DI for `PunchClockService` (consistent with Laravel middleware patterns)

### 5.2 Middleware Registration

**File**: `app/Http/Kernel.php` (or `bootstrap/app.php` for Laravel 11)

Register the middleware alias:
```php
'punch-clock-guard' => \App\Http\Middleware\PunchClockGuard::class,
```

### 5.3 Routes with Guard Applied

The guard is applied to exactly 6 existing write routes:

| Route | Controller Method | Effect on Restricted Members |
|-------|-------------------|------------------------------|
| `POST /time-entries` | `TimeEntryController::store` | Blocked (403) |
| `PUT /time-entries/{timeEntry}` | `TimeEntryController::update` | Blocked (403) |
| `PATCH /time-entries` | `TimeEntryController::updateMultiple` | Blocked (403) |
| `DELETE /time-entries/{timeEntry}` | `TimeEntryController::destroy` | Blocked (403) |
| `DELETE /time-entries` | `TimeEntryController::destroyMultiple` | Blocked (403) |
| `PUT /timesheet/cell` | `TimesheetController::updateCell` | Blocked (403) |

Read endpoints (GET) are never affected by the guard.

---

## 6. Request Validation

### 6.1 New Request Classes

All extend `App\Http\Requests\V1\BaseFormRequest`.

**PunchInRequest**:

**File**: `app/Http/Requests/V1/PunchClock/PunchInRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\PunchClock;

use App\Http\Requests\V1\BaseFormRequest;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Korridor\LaravelModelValidationRules\Rules\ExistsEloquent;

/**
 * @property Organization $organization
 */
class PunchInRequest extends BaseFormRequest
{
    /**
     * @return array<string, array<string|\Illuminate\Contracts\Validation\ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'project_id' => [
                'nullable',
                'string',
                new ExistsEloquent(Project::class, null, function (Builder $builder): Builder {
                    return $builder->whereBelongsTo($this->organization, 'organization');
                }),
            ],
            'task_id' => [
                'nullable',
                'string',
                // task_id is optional but if provided, project_id must also be provided
            ],
        ];
    }
}
```

### 6.2 Modified Request Classes

**OrganizationUpdateRequest** -- add rule:

```php
'punch_clock_mode_enabled' => [
    'boolean',
],
```

Add getter:
```php
public function getPunchClockModeEnabled(): ?bool
{
    return $this->has('punch_clock_mode_enabled') ? $this->boolean('punch_clock_mode_enabled') : null;
}
```

**MemberUpdateRequest** -- add rule:

```php
'is_punch_clock_restricted' => [
    'boolean',
],
```

Add custom validation in the `rules()` or via `withValidator()`:
- Organization must have `punch_clock_mode_enabled = true`
- Member role must not be `owner` or `admin`

Add getter:
```php
public function getIsPunchClockRestricted(): ?bool
{
    return $this->has('is_punch_clock_restricted') ? $this->boolean('is_punch_clock_restricted') : null;
}
```

---

## 7. Frontend Architecture

### 7.1 Component Hierarchy

```
Time.vue (existing Page -- conditionally renders)
|
+-- [if punchClockStore.isRestricted]
|   PunchClockRestrictedView.vue
|   +-- Info Banner (restriction explanation)
|   +-- PunchClockButton.vue
|   |   +-- [if !isPunchedIn] PunchClockProjectSelector.vue
|   |   +-- [if isPunchedIn] PunchClockDuration.vue
|   +-- Time Entry List (read-only, no edit/delete actions)
|
+-- [else]
    Normal Time Tracking View (unchanged)

Organization Settings Page (existing)
+-- PunchClockSettings.vue (new section)
    +-- Toggle: "Enable Punch-Clock Mode"
    +-- [if enabled] PunchClockMemberList.vue
        +-- Table of eligible members (employee + manager only)
        +-- Per-member restriction toggle

Time Entry List (existing, modified)
+-- TimeEntrySourceBadge.vue (new, per entry row)

Timesheet Grid (existing, modified)
+-- TimesheetCell.vue (add readonly prop when restricted)
+-- TimesheetAddTask.vue (hidden when restricted)
```

### 7.2 Pinia Store: `usePunchClockStore`

**File**: `resources/js/utils/usePunchClock.ts`

**State**:
```typescript
const isRestricted = ref(false);
const isPunchedIn = ref(false);
const activeEntry = ref<TimeEntryResource | null>(null);
const isLoading = ref(false);
const error = ref<string | null>(null);
```

**Key Actions**:

| Action | Description |
|--------|-------------|
| `fetchStatus()` | GET /punch-clock -- updates `isRestricted`, `isPunchedIn`, `activeEntry` |
| `punchIn(projectId?, taskId?)` | POST /punch-clock/in -- creates entry, updates state |
| `punchOut()` | POST /punch-clock/out -- closes entry, updates state |

**Lifecycle**:
- `fetchStatus()` is called on app initialization (or on Time page mount) to determine the member's restriction state
- The `isRestricted` flag is used by `Time.vue` to conditionally render the normal view or the restricted view
- The `isRestricted` flag is used by `TimesheetGrid.vue` to set the `readonly` prop

### 7.3 TypeScript Types

**File**: `resources/js/types/punch-clock.d.ts`

```typescript
type TimeEntrySource = 'manual' | 'timer' | 'punch_clock' | 'kiosk' | 'timesheet_grid';

interface PunchClockStatus {
    is_restricted: boolean;
    is_punched_in: boolean;
    active_entry: TimeEntryResource | null;
}

interface PunchInRequest {
    project_id?: string | null;
    task_id?: string | null;
}

interface PunchClockOrganizationSettings {
    punch_clock_mode_enabled: boolean;
}

interface PunchClockMemberSettings {
    is_punch_clock_restricted: boolean;
}
```

### 7.4 Component Details

**PunchClockButton.vue**:
- Large, centered button with two states
- "Punch In" state: green background, clock-in icon, optional project/task selector below
- "Punch Out" state: red background, clock-out icon, running duration display (PunchClockDuration)
- Loading spinner overlay during API calls
- Error toast on failure
- Keyboard accessible (Enter/Space to toggle)
- ARIA attributes: `aria-label`, `aria-pressed`

**PunchClockDuration.vue**:
- Real-time running clock updated via `setInterval` every second
- Displays `HH:MM:SS` format
- Shows project name and task name if set
- Cleans up interval on unmount

**PunchClockProjectSelector.vue**:
- Dropdown with recent projects/tasks (reuses existing project/task API)
- Optional -- member can punch in without selecting a project
- Uses existing project store data

**PunchClockRestrictedView.vue**:
- Wrapper component for the restricted Time page
- Contains: info banner, PunchClockButton, read-only entry list
- Info banner text: "Your organization has restricted your account to punch-in/punch-out time tracking. Contact your manager if you need to modify a time entry."
- Entry list shows recent entries in descending order without edit/delete actions

**PunchClockSettings.vue**:
- Section within organization settings page
- Header: "Punch-Clock Mode"
- Description text explaining the feature
- Toggle switch for `punch_clock_mode_enabled`
- Calls `PUT /organizations/{org}` with `punch_clock_mode_enabled`

**PunchClockMemberList.vue**:
- Table shown below the settings toggle when mode is enabled
- Columns: Name, Email, Role, Restricted (toggle)
- Filters: only `employee` and `manager` roles shown (Owner/Admin excluded)
- Per-member toggle calls `PUT /members/{member}` with `is_punch_clock_restricted`
- Visual indicator (badge) for currently restricted members

**TimeEntrySourceBadge.vue**:
- Small inline badge showing the time entry source
- Color coding: `punch_clock` = blue, `manual` = gray, `timer` = green, `timesheet_grid` = purple
- No badge for `null` source (legacy entries)
- Used in time entry list items and detail views

---

## 8. Permission Matrix

### 8.1 New Permissions

| Permission | Owner | Admin | Manager | Employee |
|------------|:-----:|:-----:|:-------:|:--------:|
| `punch-clock:configure` | Yes | Yes | No | No |
| `punch-clock:view:all` | Yes | Yes | Yes | No |

**Registration**: Via a new `PunchClockPermissions` class following the modular pattern.

### 8.2 Existing Permissions Used

| Endpoint | Required Permission | Notes |
|----------|-------------------|-------|
| POST /punch-clock/in | `time-entries:create:own` | All roles have this |
| POST /punch-clock/out | `time-entries:update:own` | All roles have this |
| GET /punch-clock | `time-entries:view:own` | All roles have this |
| PUT /organizations/{org} (mode toggle) | `organizations:update` | Owner/Admin only |
| PUT /members/{member} (restriction) | `members:update` | Owner/Admin/Manager |

### 8.3 Restriction Eligibility

| Role | Can be restricted? | Reason |
|------|:-----------------:|--------|
| Owner | No | Owner must always have full control |
| Admin | No | Admin must always have full control |
| Manager | Yes | May be restricted in compliance scenarios |
| Employee | Yes | Primary target for punch-clock restriction |
| Placeholder | No | Not a real user |

---

## 9. Performance Strategy

### 9.1 Guard Middleware Performance

The `PunchClockGuard` middleware is applied to 6 existing write routes. Performance overhead must be minimal.

**Fast path** (when punch-clock mode is disabled):
- Single boolean check on `organization.punch_clock_mode_enabled` (already loaded via route model binding)
- Zero additional DB queries
- Expected overhead: < 1ms

**Slow path** (when punch-clock mode is enabled):
- One DB query to resolve the member record (`WHERE organization_id = ? AND user_id = ?`)
- Two boolean checks (`is_punch_clock_restricted`, role check)
- Expected overhead: < 5ms
- The member query result can be cached on the request for the duration of the request lifecycle

### 9.2 Punch-In / Punch-Out Performance

| Operation | Target | Bottleneck |
|-----------|--------|------------|
| Punch-in | < 200ms | Single INSERT + optional overlap check query |
| Punch-out | < 200ms | Single UPDATE + 2 job dispatches |
| Status check | < 100ms | Single SELECT query |

### 9.3 Database

- **`time_entry_source` index**: Supports `WHERE time_entry_source = 'punch_clock'` filter queries. Single-column B-tree index.
- **No impact on existing indexes**: The new columns on `organizations` and `members` are not indexed (they are looked up via primary key, which is already indexed).

### 9.4 Frontend

- **Status check on page load**: Single API call to `/punch-clock` determines the restricted state. Result cached in Pinia store.
- **Real-time duration**: Client-side `setInterval` (no API polling). Duration computed from `activeEntry.start` and `Date.now()`.
- **Conditional rendering**: `v-if` directive on the Time page. No additional component loading for unrestricted users.

---

## 10. Integration Points

### 10.1 Existing Features -- Compatibility

| Feature | Interaction | Impact |
|---------|-------------|--------|
| Timer (running entries) | Punch-in creates a running entry (same `end = null` pattern) | Compatible. The existing `myActive` endpoint returns the punch-clock entry. |
| Time page (entry list) | Restricted view shows read-only list | Modified. Conditional rendering based on restriction state. |
| Timesheet grid (Feature 00) | Readonly mode for restricted members | Modified. `readonly` prop propagated to cells. |
| Reporting | No change | Compatible. Punch-clock entries flow through existing reports. |
| Dashboard | No change | Compatible. Read-only data display. |
| Export | Source field included in exports | Compatible. New field appended. |

### 10.2 Future Features -- Designed for Extension

| Feature | Integration Point |
|---------|-------------------|
| **Feature 06 (Kiosk)** | `time_entry_source = kiosk` value is reserved. Kiosk entries are independent of punch-clock restriction. |
| **Feature 01 (Approvals)** | Punch-clock entries flow through the same approval pipeline. No special handling needed. |
| **Feature 13 (Audit Trail)** | Existing `CustomAuditable` trait on `TimeEntry` covers all punch-clock mutations. |
| **Feature 15 (Attendance)** | `time_entry_source = punch_clock` filter enables attendance reports. Punch-clock entries are the primary data source. |

### 10.3 Downstream Consumers

This feature provides foundational data for:
- **Attendance reports**: Filter by `time_entry_source = punch_clock`
- **Overtime calculations**: Precise `start`/`end` from punch-clock entries
- **Compliance exports**: Source-tagged entries support auditable reports

---

## 11. File Manifest

### 11.1 New Files (18)

| File | Type | Task |
|------|------|------|
| `app/Enums/TimeEntrySource.php` | Enum | PCM-002 |
| `app/Http/Controllers/Api/V1/PunchClockController.php` | Controller | PCM-005 |
| `app/Http/Middleware/PunchClockGuard.php` | Middleware | PCM-006 |
| `app/Http/Requests/V1/PunchClock/PunchInRequest.php` | Request | PCM-009 |
| `app/Permissions/PunchClockPermissions.php` | Permissions | PCM-010 |
| `app/Service/PunchClockService.php` | Service | PCM-004 |
| `database/migrations/2026_03_12_000001_add_punch_clock_mode_to_organizations_table.php` | Migration | PCM-001 |
| `database/migrations/2026_03_12_000002_add_punch_clock_restriction_to_members_table.php` | Migration | PCM-001 |
| `database/migrations/2026_03_12_000003_add_source_to_time_entries_table.php` | Migration | PCM-001 |
| `resources/js/packages/ui/src/PunchClock/PunchClockButton.vue` | Component | PCM-014 |
| `resources/js/packages/ui/src/PunchClock/PunchClockDuration.vue` | Component | PCM-014 |
| `resources/js/packages/ui/src/PunchClock/PunchClockProjectSelector.vue` | Component | PCM-014 |
| `resources/js/packages/ui/src/PunchClock/PunchClockRestrictedView.vue` | Component | PCM-015 |
| `resources/js/packages/ui/src/PunchClock/PunchClockSettings.vue` | Component | PCM-016 |
| `resources/js/packages/ui/src/PunchClock/PunchClockMemberList.vue` | Component | PCM-016 |
| `resources/js/packages/ui/src/PunchClock/TimeEntrySourceBadge.vue` | Component | PCM-018 |
| `resources/js/utils/usePunchClock.ts` | Store | PCM-013 |
| `resources/js/types/punch-clock.d.ts` | Types | PCM-013 |

### 11.2 New Test Files (5)

| File | Type | Task |
|------|------|------|
| `tests/Unit/Endpoint/Api/V1/PunchClockEndpointTest.php` | Test | PCM-019 |
| `tests/Unit/Service/PunchClockServiceTest.php` | Test | PCM-020 |
| `resources/js/packages/ui/src/PunchClock/__tests__/PunchClockButton.test.ts` | Test | PCM-021 |
| `resources/js/packages/ui/src/PunchClock/__tests__/PunchClockRestrictedView.test.ts` | Test | PCM-021 |
| `resources/js/packages/ui/src/PunchClock/__tests__/PunchClockSettings.test.ts` | Test | PCM-021 |
| `e2e/punch-clock.spec.ts` | Test | PCM-022 |

### 11.3 Modified Files (14)

| File | Change | Task |
|------|--------|------|
| `app/Models/Organization.php` | Add `punch_clock_mode_enabled` to `$casts` | PCM-003 |
| `app/Models/Member.php` | Add `is_punch_clock_restricted` to `$casts` | PCM-003 |
| `app/Models/TimeEntry.php` | Add `time_entry_source` to `$casts` and `SELECT_COLUMNS` | PCM-002 |
| `app/Http/Controllers/Api/V1/TimeEntryController.php` | Set `time_entry_source` in `store()` | PCM-008 |
| `app/Service/TimesheetService.php` | Set `time_entry_source` in `updateCell()` | PCM-008 |
| `app/Service/TimeEntryFilter.php` | Add `addSourceFilter()` method | PCM-023 |
| `app/Http/Resources/V1/TimeEntry/TimeEntryResource.php` | Add `time_entry_source` to output | PCM-011 |
| `app/Http/Requests/V1/Organization/OrganizationUpdateRequest.php` | Add `punch_clock_mode_enabled` rule | PCM-009 |
| `app/Http/Requests/V1/Member/MemberUpdateRequest.php` | Add `is_punch_clock_restricted` rule | PCM-009 |
| `routes/api.php` | Add punch-clock routes + guard middleware on write routes | PCM-007, PCM-006 |
| `openapi.json` | Add 3 new endpoints + update schemas | PCM-012 |
| `resources/js/packages/api/src/openapi.json.client.ts` | Regenerate from OpenAPI | PCM-012 |
| `resources/js/packages/ui/src/Timesheet/TimesheetCell.vue` | Add `readonly` prop | PCM-017 |
| `resources/js/packages/ui/src/Timesheet/TimesheetGrid.vue` | Pass `readonly` from store | PCM-017 |
| `resources/js/packages/ui/src/Timesheet/TimesheetAddTask.vue` | Hide when readonly | PCM-017 |
| `resources/js/Pages/Time.vue` | Conditional rendering for restricted mode | PCM-015 |
| `database/factories/OrganizationFactory.php` | Add `punch_clock_mode_enabled` default | PCM-003 |
| `database/factories/MemberFactory.php` | Add `is_punch_clock_restricted` default | PCM-003 |
