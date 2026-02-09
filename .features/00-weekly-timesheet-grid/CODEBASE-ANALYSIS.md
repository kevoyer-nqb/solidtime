# Codebase Analysis: Feature 00 — Weekly Timesheet Grid

**Date**: 2026-02-06
**Branch analyzed**: `main`
**Target feature branch**: `feature/weekly-timesheet-grid`
**PRD reference**: `.features/00-weekly-timesheet-grid/PRD.md`

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Existing Time Entry Infrastructure](#2-existing-time-entry-infrastructure)
3. [Controller Patterns](#3-controller-patterns)
4. [Service Layer Patterns](#4-service-layer-patterns)
5. [Request Validation Patterns](#5-request-validation-patterns)
6. [Permission System](#6-permission-system)
7. [Frontend Store Patterns](#7-frontend-store-patterns)
8. [Frontend Component Patterns](#8-frontend-component-patterns)
9. [Route Registration Patterns](#9-route-registration-patterns)
10. [Navigation Sidebar](#10-navigation-sidebar)
11. [Data Flow Diagram](#11-data-flow-diagram)
12. [File Modification Risk Assessment](#12-file-modification-risk-assessment)

---

## 1. Executive Summary

The Solidtime codebase provides a solid, consistent foundation for the Weekly Timesheet Grid. Every pattern needed (controllers, services, request validation, Pinia stores, Vue components, route registration, navigation) is already established and well-documented in CLAUDE.md.

**Key findings**:

- **No schema changes needed** — the existing `time_entries` table has all required columns
- **Permission system already covers the use case** — `time-entries:view:own/all` and `time-entries:create:own/all` are sufficient
- **All architectural patterns are well-established** — the feature follows existing conventions exactly
- **No merge conflict risk** — the feature creates only new files and makes minor additions to shared files (routes, navigation)
- **Database query pattern** is straightforward: `WHERE organization_id = ? AND user_id = ? AND start >= ? AND start <= ? AND end IS NOT NULL`

---

## 2. Existing Time Entry Infrastructure

### 2.1 TimeEntry Model

**File**: `app/Models/TimeEntry.php`

Key characteristics:
- Uses `HasUuids` trait (UUID primary keys)
- Uses `CustomAuditable` trait (audit logging on all mutations)
- Uses `HasFactory` trait
- Relationships: `belongsTo` Project, Task, User, Member, Organization, Client
- `start` and `end` are Carbon datetime columns (stored in UTC)
- `end` is nullable (null = running timer)
- `billable` is boolean
- `tags` is cast to array
- `project_id`, `task_id`, `client_id` are nullable UUIDs

### 2.2 TimeEntry Database Schema

```sql
-- Relevant columns for timesheet feature:
id              UUID PRIMARY KEY
start           TIMESTAMP NOT NULL    -- UTC datetime
end             TIMESTAMP NULL        -- NULL = running timer
project_id      UUID NULL             -- FK to projects
task_id         UUID NULL             -- FK to tasks
user_id         UUID NOT NULL         -- FK to users
member_id       UUID NOT NULL         -- FK to members
organization_id UUID NOT NULL         -- FK to organizations
billable        BOOLEAN NOT NULL
description     TEXT NOT NULL
tags            JSONB NOT NULL        -- Array of tag strings
client_id       UUID NULL             -- FK to clients
is_imported     BOOLEAN NOT NULL DEFAULT false
```

### 2.3 Existing Query Patterns

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
    ->selectRaw('SUM(EXTRACT(EPOCH FROM ("end" - start))) as total_seconds')
    ->whereBelongsTo($organization, 'organization')
    ->where('user_id', $member->user_id)
    ->whereNotNull('end')
    ->first();
```

The timesheet service should follow the aggregation service pattern for week totals (PostgreSQL `EXTRACT(EPOCH FROM ...)` rather than PHP-side calculation).

---

## 3. Controller Patterns

### 3.1 Base Controller

**File**: `app/Http/Controllers/Api/V1/Controller.php`

All API controllers extend this base, which provides:
```php
protected PermissionStore $permissionStore;  // Injected via constructor

protected function checkPermission(Organization $organization, string $permission): void
protected function checkAnyPermission(Organization $organization, array $permissions): void
protected function user(): User
protected function member(Organization $organization): Member
```

### 3.2 Existing Controller Pattern

**Example from ChartController** (similar aggregation endpoint):
```php
class ChartController extends Controller
{
    public function __construct(
        private readonly TimeEntryAggregationService $aggregationService
    ) {}

    public function index(Organization $organization, ChartRequest $request): JsonResponse
    {
        $this->checkAnyPermission($organization, [
            'time-entries:view:own',
            'time-entries:view:all',
        ]);

        $member = $this->member($organization);
        // ... use service to aggregate data
        return response()->json(['data' => $result]);
    }
}
```

The `TimesheetController` should follow this exact pattern: constructor DI of service, permission checks, member resolution, JSON response.

### 3.3 Organization Injection

Organization is automatically resolved via route model binding from the `{organization}` route parameter. No manual lookup needed.

---

## 4. Service Layer Patterns

### 4.1 Service Conventions

- Location: `app/Service/`
- Stateless classes (no constructor state)
- Methods accept model instances (Organization, Member) not IDs
- Return plain arrays (not Eloquent collections or resources)
- Injected into controllers via constructor type-hints

### 4.2 Existing Service Example

**TimeEntryAggregationService**:
```php
class TimeEntryAggregationService
{
    public function getAggregatedTimeEntries(
        Organization $organization,
        Member $member,
        Carbon $start,
        Carbon $end,
        string $groupBy
    ): array {
        // Query, aggregate, return structured array
    }
}
```

The `TimesheetService` follows this pattern exactly — accept Organization + Member, query TimeEntry, return arrays.

---

## 5. Request Validation Patterns

### 5.1 Base Request

**File**: `app/Http/Requests/V1/BaseFormRequest.php`

All request classes extend this. It provides:
- Access to `$this->organization` via route model binding
- Standard authorization logic

### 5.2 Existing Validation Pattern

**Example from TimeEntryStoreRequest**:
```php
class TimeEntryStoreRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'project_id' => [
                'nullable', 'string', 'uuid',
                new ExistsEloquent(Project::class, null, function ($builder) {
                    $builder->whereBelongsTo($this->organization, 'organization');
                }),
            ],
            'task_id' => ['nullable', 'string', 'uuid'],
            'start' => ['required', 'date'],
            // ...
        ];
    }
}
```

**Note**: The timesheet request classes use simpler validation (no `ExistsEloquent` for project/task) since the service handles lookup. This is acceptable for the cell update endpoint where missing project/task is a valid state (null = "No Project").

---

## 6. Permission System

### 6.1 Permission Registration

**File**: `app/Providers/JetstreamServiceProvider.php`

Permissions are registered per-role:
```php
Jetstream::role('employee', 'Employee', [
    'time-entries:view:own',
    'time-entries:create:own',
    'time-entries:update:own',
    'time-entries:delete:own',
    // ...
])->description('...');
```

All roles (Employee, Manager, Admin, Owner) have at least:
- `time-entries:view:own`
- `time-entries:create:own`

This means **all authenticated users** can use the timesheet feature.

### 6.2 Permission Check Pattern

The controller uses `checkAnyPermission` which accepts an array:
```php
$this->checkAnyPermission($organization, [
    'time-entries:view:own',
    'time-entries:view:all',
]);
```

For the write endpoint (`updateCell`), the controller checks:
```php
$this->checkAnyPermission($organization, [
    'time-entries:create:own',
    'time-entries:create:all',
]);
```

No new permissions need to be registered.

---

## 7. Frontend Store Patterns

### 7.1 Existing Pinia Store Pattern

**Example from `useTimeEntries.ts`**:
```typescript
export const useTimeEntriesStore = defineStore('timeEntries', () => {
    const items = ref<TimeEntry[]>([]);
    const isLoading = ref(false);

    async function fetchEntries() {
        isLoading.value = true;
        try {
            const response = await api.get('/time-entries', { params: { ... } });
            items.value = response.data.data;
        } finally {
            isLoading.value = false;
        }
    }

    return { items, isLoading, fetchEntries };
});
```

The `useTimesheetStore` follows this pattern but uses `Map` and `Set` for more complex state (week data cache, expanded weeks tracking).

### 7.2 API Client Pattern

**File**: `resources/js/packages/api/src/openapi.json.client.ts`

Auto-generated client from OpenAPI spec. After adding timesheet endpoints to `openapi.json` and regenerating, the client provides typed methods:
```typescript
api.getTimesheetWeeks({ organization: orgId, limit: 8, offset: 0 })
api.getTimesheetData({ organization: orgId, week_start: '2026-02-02' })
api.updateTimesheetCell({ organization: orgId, ... })
api.getTimesheetRecentTasks({ organization: orgId, limit: 10 })
```

### 7.3 Organization Context

**File**: `resources/js/utils/useUser.ts`

`getCurrentOrganizationId()` returns the current organization UUID for API calls.

---

## 8. Frontend Component Patterns

### 8.1 Page Component Pattern

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

The `Timesheet.vue` page follows this exact pattern with `AppLayout` + `MainContainer`.

### 8.2 UI Component Location

Feature-specific components live in `resources/js/packages/ui/src/{Feature}/`. Tests go in `__tests__/` subdirectory.

### 8.3 Icon Usage

Icons imported from `@heroicons/vue/20/solid`:
```typescript
import { TableCellsIcon } from '@heroicons/vue/20/solid';
```

Used in sidebar navigation and within components.

---

## 9. Route Registration Patterns

### 9.1 API Routes

**File**: `routes/api.php`

API routes are registered inside nested middleware groups:
```php
Route::middleware(['auth:api', 'verified'])->group(function () {
    Route::name('v1.')->prefix('v1')->group(function () {
        // Feature route groups here
        Route::name('timesheet.')->prefix('/organizations/{organization}')->group(static function (): void {
            // Timesheet endpoints
        });
    });
});
```

### 9.2 Web Routes

**File**: `routes/web.php`

Inertia page routes registered inside `auth:web` middleware:
```php
Route::middleware(['auth:web', 'verified'])->group(function () {
    Route::get('/timesheet', function () {
        return Inertia::render('Timesheet');
    })->name('timesheet');
});
```

---

## 10. Navigation Sidebar

**File**: `resources/js/Layouts/AppLayout.vue`

The sidebar uses `NavigationSidebarItem` components:
```vue
<NavigationSidebarItem
    title="Time"
    :icon="ClockIcon"
    :current="route().current('time')"
    :href="route('time')">
</NavigationSidebarItem>
```

The Timesheet item should be placed after the Time item in the navigation order, using `TableCellsIcon`:
```vue
<NavigationSidebarItem
    title="Timesheet"
    :icon="TableCellsIcon"
    :current="route().current('timesheet')"
    :href="route('timesheet')">
</NavigationSidebarItem>
```

---

## 11. Data Flow Diagram

### 11.1 Page Load Flow

```
User clicks "Timesheet" in sidebar
    → Inertia navigates to /timesheet
    → Timesheet.vue mounts
    → useTimesheetStore.loadWeekList()
        → GET /api/v1/organizations/{org}/timesheet/weeks?limit=8&offset=0
        → Backend: TimesheetController.weeks()
            → TimesheetService.getWeekList()
                → 8 × getWeekTotalSeconds() SQL queries
                → 8 × getWeekLabel() calculations
            → Return JSON array of week summaries
        → Store: set weekList, expand current week
    → useTimesheetStore.loadWeekGrid(currentWeek)
        → GET /api/v1/organizations/{org}/timesheet?week_start=2026-02-02
        → Backend: TimesheetController.index()
            → TimesheetService.getWeekGrid()
                → Query all time entries for week
                → Group by project+task
                → Calculate hours per day per group
            → Return JSON grid data
        → Store: cache in weekDataMap, render grid
```

### 11.2 Cell Update Flow

```
User clicks cell → types "2.5" → presses Enter
    → TimesheetCell emits 'update' event with hours=2.5
    → TimesheetGrid emits 'update-cell' with rowIndex, dayIndex, hours
    → TimesheetWeekAccordion calls store.updateCell()
    → useTimesheetStore.updateCell(weekStart, rowIndex, dayIndex, 2.5):
        1. Save previous cell state (for rollback)
        2. Optimistically update cell.hours, recalculate totals
        3. PUT /api/v1/organizations/{org}/timesheet/cell
            { date: "2026-02-03", project_id: "uuid", task_id: "uuid", hours: 2.5 }
        4. Backend: TimesheetController.updateCell()
            → TimesheetService.updateCell()
                → Find existing entries for this cell
                → Create/update/delete as appropriate
            → Return { date, hours, time_entry_ids }
        5. On success: update cell.time_entry_ids
        6. On failure: rollback cell state, set cell.hasError = true
```

---

## 12. File Modification Risk Assessment

### 12.1 Risk Matrix

| File | Change Type | Risk | Rationale |
|------|------------|:----:|-----------|
| `routes/api.php` | Add route group | Low | Appending new group, no existing code modified |
| `routes/web.php` | Add Inertia route | Low | Appending single route, no existing code modified |
| `resources/js/Layouts/AppLayout.vue` | Add nav item | Low | Adding one `NavigationSidebarItem`, no existing items modified |
| `openapi.json` | Add endpoint definitions | Low | Appending new paths, no existing paths modified |

### 12.2 Merge Conflict Assessment

**Risk: NONE** — This feature creates only new files for the core implementation. The 4 modified files receive simple additions (new route groups, new nav item, new OpenAPI paths) that are appended rather than modifying existing content.

### 12.3 Downstream Conflict Warning

After this feature is merged to `main`, **Feature 01 (Timesheet Approvals)** will modify several of these files:
- `app/Service/TimesheetService.php` — adding lock checks (HIGH conflict risk)
- `resources/js/utils/useTimesheet.ts` — adding approval state (HIGH conflict risk)
- `resources/js/packages/ui/src/Timesheet/TimesheetWeekAccordion.vue` — adding submit/status UI (MEDIUM conflict risk)

This is documented in Feature 01's codebase analysis and sprint plan. The mitigation is: **merge this feature to main before starting Feature 01**.
