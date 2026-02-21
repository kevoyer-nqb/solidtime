# Feature 00: Weekly Timesheet Grid — Technical Architecture

**Date**: 2026-02-06
**Status**: Draft
**Feature Branch**: `feature/weekly-timesheet-grid` (from `main`)
**Task Prefix**: `TSG-` (per task assignments)

---

## Executive Summary

This document provides the complete technical architecture for the **Weekly Timesheet Grid** feature. The feature adds a spreadsheet-like weekly view to Solidtime, allowing users to enter hours in a grid with project/task rows and day columns, organized in an accordion layout showing multiple weeks.

**Key Architectural Decisions**:
- **No schema changes** — operates entirely on the existing `time_entries` table
- **New `TimesheetService`** handles aggregation, cell updates, and week management
- **New `TimesheetController`** with 4 endpoints following existing API patterns
- **Frontend**: Pinia store + 6 Vue components + Inertia.js page
- **Existing permissions** — reuses `time-entries:view:own/all` and `time-entries:create:own/all`
- **Lazy-loaded accordion** — grids fetched per-week on expand, cached client-side

---

## Table of Contents

1. [Data Model Design](#1-data-model-design)
2. [API Contract](#2-api-contract)
3. [Service Layer](#3-service-layer)
4. [Controller Layer](#4-controller-layer)
5. [Request Validation](#5-request-validation)
6. [Frontend Architecture](#6-frontend-architecture)
7. [Permission Matrix](#7-permission-matrix)
8. [Performance Strategy](#8-performance-strategy)
9. [Integration Points](#9-integration-points)
10. [File Manifest](#10-file-manifest)

---

## 1. Data Model Design

### 1.1 No New Models

This feature does **not** introduce any new Eloquent models or database tables. All data comes from the existing `time_entries` table via aggregation queries in `TimesheetService`.

### 1.2 Existing Model Usage

**`TimeEntry`** (read + write):
```php
// Key columns used:
'id'              // UUID primary key
'start'           // Carbon datetime (UTC) — used for day assignment
'end'             // Carbon datetime (UTC) — null = running timer (excluded)
'project_id'      // nullable UUID — groups into grid rows
'task_id'         // nullable UUID — groups into grid rows
'user_id'         // UUID — scoped to current user
'member_id'       // UUID — organization membership
'organization_id' // UUID — organization scoping
'billable'        // boolean — inherited from project default
'description'     // string — set to '' for grid-created entries
'tags'            // array — set to [] for grid-created entries
'client_id'       // nullable UUID — inherited from project
```

**Relationships used**: `project`, `task` (eager loaded for grid display)

### 1.3 Composite Index (Performance)

**File**: New migration (TSG-012)

```php
// Add composite index for the primary timesheet query pattern
Schema::table('time_entries', function (Blueprint $table) {
    $table->index(
        ['organization_id', 'user_id', 'start', 'end'],
        'time_entries_timesheet_lookup_index'
    );
});
```

This index covers the `WHERE organization_id = ? AND user_id = ? AND end IS NOT NULL AND start >= ? AND start <= ?` pattern used by both `getWeekGrid()` and `getWeekTotalSeconds()`.

---

## 2. API Contract

### 2.1 Route Registration

**File**: `routes/api.php` (inside existing `auth:api` + `verified` middleware group)

```php
Route::name('timesheet.')->prefix('/organizations/{organization}')->group(static function (): void {
    Route::get('/timesheet/weeks', [TimesheetController::class, 'weeks'])->name('weeks');
    Route::get('/timesheet', [TimesheetController::class, 'index'])->name('index');
    Route::put('/timesheet/cell', [TimesheetController::class, 'updateCell'])
        ->name('update-cell')
        ->middleware('check-organization-blocked');
    Route::get('/timesheet/recent-tasks', [TimesheetController::class, 'recentTasks'])
        ->name('recent-tasks');
});
```

Route names resolve to:
- `api.v1.timesheet.weeks`
- `api.v1.timesheet.index`
- `api.v1.timesheet.update-cell`
- `api.v1.timesheet.recent-tasks`

Only `updateCell` (write endpoint) uses `check-organization-blocked` middleware.

### 2.2 Endpoint Signatures

| Method | Path | Controller Method | Request Class | Permission |
|--------|------|-------------------|---------------|------------|
| GET | `/timesheet/weeks` | `weeks()` | `TimesheetWeeksRequest` | `time-entries:view:own` or `view:all` |
| GET | `/timesheet` | `index()` | `TimesheetIndexRequest` | `time-entries:view:own` or `view:all` |
| PUT | `/timesheet/cell` | `updateCell()` | `TimesheetCellUpdateRequest` | `time-entries:create:own` or `create:all` |
| GET | `/timesheet/recent-tasks` | `recentTasks()` | `TimesheetRecentTasksRequest` | `time-entries:view:own` or `view:all` |

### 2.3 Response Shapes

**GET /timesheet/weeks** → `JsonResponse`:
```json
{
  "data": [
    {
      "week_start": "2026-02-02",
      "week_end": "2026-02-08",
      "label": "This Week",
      "total_seconds": 144000
    }
  ]
}
```

**GET /timesheet** → `JsonResponse`:
```json
{
  "data": {
    "week_start": "2026-02-02",
    "week_end": "2026-02-08",
    "rows": [
      {
        "id": "uuid:uuid",
        "project": { "id": "uuid", "name": "Website", "color": "#3B82F6" },
        "task": { "id": "uuid", "name": "Frontend" },
        "cells": [
          { "date": "2026-02-02", "hours": 2.5, "time_entry_ids": ["uuid"] },
          ...
        ],
        "total_hours": 16.5
      }
    ],
    "day_totals": [2.5, 4.0, 8.0, 6.0, 5.5, 0, 0],
    "week_total": 26.0
  }
}
```

**PUT /timesheet/cell** → `JsonResponse`:
```json
{
  "data": {
    "date": "2026-02-03",
    "hours": 4.0,
    "time_entry_ids": ["uuid"]
  }
}
```

**GET /timesheet/recent-tasks** → `JsonResponse`:
```json
{
  "data": [
    {
      "project": { "id": "uuid", "name": "Website", "color": "#3B82F6" },
      "task": { "id": "uuid", "name": "Frontend" }
    }
  ]
}
```

---

## 3. Service Layer

### 3.1 TimesheetService

**File**: `app/Service/TimesheetService.php`

Stateless service class with 6 methods (4 public, 2 private).

#### `getWeekList(Organization, Member, string $timezone, int $weekStartDay, int $limit, int $offset): array`

1. Calculate `$now` in user's timezone
2. Loop from `$offset` to `$offset + $limit`, computing `$weekStart` by subtracting `$i` weeks
3. For each week: query `getWeekTotalSeconds()` and `getWeekLabel()`
4. Return array of `{week_start, week_end, label, total_seconds}`

**Performance note**: Each week's total uses a single `SUM(EXTRACT(EPOCH FROM ...))` SQL query — no PHP-side iteration.

#### `getWeekGrid(Organization, Member, Carbon $weekStart, Carbon $weekEnd, string $timezone): array`

1. Generate 7 `$days` array from `$weekStart`
2. Query all `TimeEntry` records for the member in the week range (UTC-converted), eager-load `project` and `task`
3. Group entries by `project_id:task_id` composite key
4. For each group, iterate 7 days: filter entries to that day, sum seconds, collect entry IDs
5. Convert seconds to hours (`round($seconds / 3600, 2)`)
6. Calculate day totals and week total
7. Return structured array

**Key detail**: Entries are assigned to days by comparing their UTC `start` against the day's UTC start/end boundaries.

#### `updateCell(Organization, Member, string $date, ?string $projectId, ?string $taskId, float $hours, string $timezone): array`

Three-path logic:

1. **hours <= 0**: Find matching entries → delete all → return empty `time_entry_ids`
2. **hours > 0 and entries exist**: Update first entry's `end` → delete extras → return single ID
3. **hours > 0 and no entries**: Create new `TimeEntry` with 9:00 AM start in user's timezone → return new ID

New entries inherit: `billable` from project's `is_billable`, `client_id` from project, empty `description`, empty `tags`.

#### `getRecentTasks(Organization, Member, int $limit): array`

1. Query `TimeEntry` grouped by `project_id, task_id`
2. Order by `MAX(start) DESC`
3. Limit to `$limit` results
4. For each, look up `Project` and `Task` models
5. Return `{project: {id, name, color} | null, task: {id, name} | null}`

#### Private: `getWeekTotalSeconds()` and `getWeekLabel()`

Utility methods for `getWeekList()`. The total uses PostgreSQL `EXTRACT(EPOCH FROM ...)` for efficient server-side calculation.

---

## 4. Controller Layer

### 4.1 TimesheetController

**File**: `app/Http/Controllers/Api/V1/TimesheetController.php`

Extends `App\Http\Controllers\Api\V1\Controller` (which provides `$this->checkPermission()`, `$this->user()`, `$this->member()`).

**Dependency injection**: `TimesheetService` injected via constructor.

```php
class TimesheetController extends Controller
{
    public function __construct(
        private readonly TimesheetService $timesheetService
    ) {}
}
```

**Permission strategy**:
- Read endpoints: `checkAnyPermission($organization, ['time-entries:view:own', 'time-entries:view:all'])`
- Write endpoint: Checks `time-entries:create:own` (own entries) or `time-entries:create:all` (any member's entries)

**Member resolution**: All methods operate on the currently authenticated user's member. The `$this->member($organization)` helper returns the `Member` for the current user in the given organization.

**Timezone**: Retrieved from `$this->user()->timezone` (user setting).

**Week start day**: Retrieved from `$this->user()->week_start` (user setting, integer 0-6).

---

## 5. Request Validation

### 5.1 Request Classes

All extend `App\Http\Requests\V1\BaseFormRequest`.

**TimesheetWeeksRequest**:
```php
public function rules(): array
{
    return [
        'limit' => ['sometimes', 'integer', 'min:1', 'max:52'],
        'offset' => ['sometimes', 'integer', 'min:0'],
    ];
}
```

**TimesheetIndexRequest**:
```php
public function rules(): array
{
    return [
        'week_start' => ['required', 'date_format:Y-m-d'],
    ];
}
```

**TimesheetCellUpdateRequest**:
```php
public function rules(): array
{
    return [
        'date' => ['required', 'date_format:Y-m-d'],
        'project_id' => ['nullable', 'string', 'uuid'],
        'task_id' => ['nullable', 'string', 'uuid'],
        'hours' => ['required', 'numeric', 'min:0', 'max:24'],
    ];
}
```

**TimesheetRecentTasksRequest**:
```php
public function rules(): array
{
    return [
        'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
    ];
}
```

Project/task ID existence is validated at the service layer (via `Project::find()` / `Task::find()`) rather than in request validation, to keep the request classes simple and avoid cross-model coupling.

---

## 6. Frontend Architecture

### 6.1 Component Hierarchy

```
Timesheet.vue (Page)
└── TimesheetWeekAccordion.vue × N (one per week in weekList)
    ├── Accordion Header (button: toggle expand/collapse)
    │   ├── Chevron icon (expanded/collapsed state)
    │   ├── Week label ("This Week", "Last Week", date range)
    │   └── Total hours badge
    └── Accordion Content (v-if="isExpanded")
        ├── LoadingSpinner (while grid loading)
        ├── TimesheetGrid.vue
        │   ├── <thead> Day columns (Mon-Sun with today highlight)
        │   ├── <tbody> TimesheetRow × N
        │   │   ├── <td> TimesheetRowHeader.vue
        │   │   │   ├── Project color dot + name
        │   │   │   ├── Task name (if present)
        │   │   │   └── Remove button (if isNew)
        │   │   └── <td> TimesheetCell.vue × 7
        │   │       ├── Display mode (click to edit)
        │   │       ├── Edit mode (input field)
        │   │       └── Loading/error states
        │   └── <tfoot> Day totals + week total
        ├── TimesheetAddTask.vue
        │   ├── "Add Task" dropdown (recent tasks from API)
        │   └── Search/filter
        └── "Add Last Week's Tasks" button
```

### 6.2 Pinia Store: `useTimesheetStore`

**File**: `resources/js/utils/useTimesheet.ts`

**State**:
```typescript
const weekList = ref<WeekSummary[]>([]);
const expandedWeeks = ref<Set<string>>(new Set());
const weekDataMap = ref<Map<string, TimesheetWeekData>>(new Map());
const loadingWeeks = ref<Set<string>>(new Set());
const recentTasks = ref<RecentTask[]>([]);
const isLoadingList = ref(false);
const hasMoreWeeks = ref(true);
```

**Key Actions**:

| Action | Description |
|--------|-------------|
| `loadWeekList()` | Fetch week summaries (GET /weeks), expand current week, load its grid |
| `toggleWeek(weekStart)` | Toggle expand/collapse, lazy-load grid on first expand |
| `loadWeekGrid(weekStart)` | Fetch grid data (GET /timesheet), cache in `weekDataMap` |
| `updateCell(weekStart, rowIndex, dayIndex, hours)` | Optimistic update → PUT /cell → rollback on error |
| `addTaskRow(weekStart, projectId, taskId)` | Add empty row to specific week's grid (client-only) |
| `addLastWeekTasks(weekStart)` | Find previous week's grid, add missing rows to current |
| `loadRecentTasks()` | Fetch recent tasks (GET /recent-tasks) |
| `loadMoreWeeks()` | Paginate: increase offset, fetch next batch (collapsed) |

**Optimistic Update Flow**:
```
1. Store previous cell state
2. Update cell value + totals in store immediately
3. Send PUT /cell API request
4. On success: update time_entry_ids from response
5. On failure: restore previous cell state, set hasError = true
```

### 6.3 TypeScript Types

**File**: `resources/js/types/timesheet.d.ts`

Defines: `TimesheetProjectInfo`, `TimesheetTaskInfo`, `WeekSummary`, `RecentTask`, `TimesheetCell`, `TimesheetRow`, `TimesheetWeekData`

Cell UI state fields (`isEditing`, `isLoading`, `hasError`) are added client-side when transforming API responses — they are not returned by the API.

### 6.4 Page Registration

**File**: `routes/web.php`
```php
Route::get('/timesheet', function () {
    return Inertia::render('Timesheet');
})->name('timesheet');
```

**File**: `resources/js/Layouts/AppLayout.vue`
```vue
<NavigationSidebarItem
    title="Timesheet"
    :icon="TableCellsIcon"
    :current="route().current('timesheet')"
    :href="route('timesheet')">
</NavigationSidebarItem>
```

Uses `TableCellsIcon` from `@heroicons/vue/20/solid`.

---

## 7. Permission Matrix

No new permissions. The feature reuses existing `time-entries` permissions:

| Endpoint | Required Permission | Notes |
|----------|-------------------|-------|
| GET /timesheet/weeks | `time-entries:view:own` or `time-entries:view:all` | Own timesheet only |
| GET /timesheet | `time-entries:view:own` or `time-entries:view:all` | Own timesheet only |
| PUT /timesheet/cell | `time-entries:create:own` or `time-entries:create:all` | Own entries only |
| GET /timesheet/recent-tasks | `time-entries:view:own` or `time-entries:view:all` | Own history only |

**Role access**:
| Role | Can use timesheet? |
|------|:------------------:|
| Owner | Yes |
| Admin | Yes |
| Manager | Yes |
| Employee | Yes |

All roles have at least `time-entries:view:own` and `time-entries:create:own`.

---

## 8. Performance Strategy

### 8.1 Database

- **Composite index** on `(organization_id, user_id, start, end)` for the primary query pattern
- **Server-side aggregation**: `SUM(EXTRACT(EPOCH FROM ...))` for week totals (avoids loading entries into PHP)
- **Eager loading**: `with(['project', 'task'])` to prevent N+1 on grid display

### 8.2 Frontend

- **Lazy loading**: Grid data fetched only when week is expanded
- **Client-side caching**: `weekDataMap` retains fetched grids across accordion toggles
- **Optimistic updates**: Cell changes reflected immediately, API call in background
- **Pagination**: Week list loaded in batches of 8 via "Load More"

### 8.3 Targets

| Metric | Target |
|--------|--------|
| GET /timesheet/weeks (8 weeks) | < 200ms |
| GET /timesheet (single week) | < 500ms |
| PUT /timesheet/cell | < 300ms |
| UI: cell save perceived latency | < 100ms (optimistic) |

---

## 9. Integration Points

### 9.1 Existing Features — No Conflicts

| Feature | Interaction |
|---------|-------------|
| Timer (running entries) | Excluded from grid via `whereNotNull('end')` |
| Time page (entry list) | Independent — same data, different view |
| Reporting | Same `time_entries` table — consistent data |
| Calendar view | Complementary view — no shared components |

### 9.2 Future Features — Designed for Extension

| Feature | Integration Point |
|---------|-------------------|
| **Timesheet Approvals** (01) | `TimesheetService` methods will gain lock checks; `TimesheetWeekAccordion` will add submit/status UI |
| **Calendar Enhanced** (05) | Navigation link between calendar day and timesheet day |

---

## 10. File Manifest

### 10.1 New Files (17)

| File | Type | Task |
|------|------|------|
| `app/Http/Controllers/Api/V1/TimesheetController.php` | Controller | TSG-001, TSG-005 |
| `app/Service/TimesheetService.php` | Service | TSG-002 |
| `app/Http/Requests/V1/Timesheet/TimesheetIndexRequest.php` | Request | TSG-004 |
| `app/Http/Requests/V1/Timesheet/TimesheetCellUpdateRequest.php` | Request | TSG-004 |
| `app/Http/Requests/V1/Timesheet/TimesheetWeeksRequest.php` | Request | TSG-004 |
| `app/Http/Requests/V1/Timesheet/TimesheetRecentTasksRequest.php` | Request | TSG-004 |
| `resources/js/Pages/Timesheet.vue` | Page | TSG-007 |
| `resources/js/packages/ui/src/Timesheet/TimesheetGrid.vue` | Component | TSG-008 |
| `resources/js/packages/ui/src/Timesheet/TimesheetCell.vue` | Component | TSG-008 |
| `resources/js/packages/ui/src/Timesheet/TimesheetWeekAccordion.vue` | Component | TSG-008 |
| `resources/js/packages/ui/src/Timesheet/TimesheetAddTask.vue` | Component | TSG-008 |
| `resources/js/packages/ui/src/Timesheet/TimesheetRowHeader.vue` | Component | TSG-008 |
| `resources/js/utils/useTimesheet.ts` | Store | TSG-009 |
| `resources/js/types/timesheet.d.ts` | Types | TSG-009 |
| `tests/Unit/Endpoint/Api/V1/TimesheetEndpointTest.php` | Test | TSG-013 |
| `resources/js/packages/ui/src/Timesheet/__tests__/TimesheetRowHeader.test.ts` | Test | TSG-015 |
| `e2e/timesheet.spec.ts` | Test | TSG-016 |

### 10.2 Modified Files (5)

| File | Change | Task |
|------|--------|------|
| `routes/api.php` | Add timesheet route group | TSG-003 |
| `routes/web.php` | Add Inertia page route | TSG-010 |
| `resources/js/Layouts/AppLayout.vue` | Add sidebar nav item | TSG-010 |
| `openapi.json` | Add 4 endpoint definitions | TSG-006 |
| `resources/js/packages/api/src/openapi.json.client.ts` | Regenerate from OpenAPI | TSG-006 |
| `vite.config.js` | Vitest configuration for component tests | TSG-015 |
