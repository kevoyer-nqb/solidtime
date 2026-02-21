---
phase: 02-weekly-timesheet-grid
plan: 01
subsystem: api, ui
tags: [laravel, pinia, tanstack-query, timezone, carbon, dayjs, timesheet, grid]

# Dependency graph
requires:
  - phase: 01-shared-foundations
    provides: DateBoundaryService with getWeekDates/getWeekBoundariesUtc, cookie auth pattern with X-XSRF-TOKEN
provides:
  - GET /api/v1/organizations/{org}/timesheet-grid endpoint returning grid-structured JSON
  - TimesheetGridService for project+task grouping with timezone-aware date assignment
  - useTimesheetGridStore Pinia store with week navigation and cell operations
  - useTimesheetGridQuery TanStack Query composable for grid data fetching
  - /timesheet web route and sidebar navigation item
affects: [02-02-grid-ui-components, weekly-timesheet-grid]

# Tech tracking
tech-stack:
  added: []
  patterns: [grid-data-structure, timezone-aware-date-grouping, direct-TimeEntry-query-for-freshness]

key-files:
  created:
    - app/Http/Controllers/Api/V1/TimesheetGridController.php
    - app/Service/TimesheetGridService.php
    - app/Http/Requests/V1/TimesheetGrid/TimesheetGridIndexRequest.php
    - app/Http/Resources/V1/TimesheetGrid/TimesheetGridResource.php
    - resources/js/utils/useTimesheetGrid.ts
  modified:
    - routes/api.php
    - routes/web.php
    - resources/js/Layouts/AppLayout.vue

key-decisions:
  - "Direct TimeEntry query (not DailyTimeSummary) for grid data -- avoids staleness for single-member weekly view"
  - "Organization timezone used for date assignment -- entries near midnight placed in correct local day"
  - "Grid API uses existing time-entries:view:own permission -- no new permissions needed"
  - "fetchJson helper with X-XSRF-TOKEN for grid data fetching, Zodios api client for time entry CRUD"

patterns-established:
  - "TimesheetGridService stateless service pattern: accepts Organization, Member, Carbon date, DateBoundaryService"
  - "Grid response structure: rows (project+task), cells (7 days), daily_totals, weekly_total"
  - "Composite grouping key: project_id|task_id for TimeEntry grouping"
  - "Cell update via delta adjustment: modify last entry's end time by (newTotal - currentTotal)"

# Metrics
duration: 5min
completed: 2026-02-11
---

# Phase 2 Plan 1: Timesheet Grid Data Layer Summary

**Backend API endpoint with timezone-aware project+task grouping, Pinia store with week navigation and TanStack Query, /timesheet route with sidebar nav**

## Performance

- **Duration:** 5 min
- **Started:** 2026-02-11T03:17:57Z
- **Completed:** 2026-02-11T03:22:48Z
- **Tasks:** 2/2
- **Files modified:** 8

## Accomplishments
- Backend API endpoint at GET /api/v1/organizations/{org}/timesheet-grid returning grid data grouped by project+task with 7-day cells, daily totals, and weekly total
- TimesheetGridService queries TimeEntry directly with timezone-aware local date assignment using DateBoundaryService
- Pinia store with week navigation (prev/next/current), TanStack Query data fetching, and cell update/create methods
- /timesheet web route and sidebar navigation item with TableCellsIcon after Calendar

## Task Commits

Each task was committed atomically:

1. **Task 1: Backend API endpoint for timesheet grid data** - `740a7e4` (feat)
2. **Task 2: Pinia store, web route, and sidebar navigation** - `73c5708` (feat)

## Files Created/Modified
- `app/Http/Controllers/Api/V1/TimesheetGridController.php` - Grid API controller with permission check, DateBoundaryService and TimesheetGridService injection
- `app/Service/TimesheetGridService.php` - Stateless service building grid data: TimeEntry query, timezone-aware grouping, row/cell construction
- `app/Http/Requests/V1/TimesheetGrid/TimesheetGridIndexRequest.php` - Validates optional date parameter (Y-m-d format)
- `app/Http/Resources/V1/TimesheetGrid/TimesheetGridResource.php` - Placeholder resource for future use
- `resources/js/utils/useTimesheetGrid.ts` - Pinia store with week navigation, TanStack Query, cell update/create methods
- `routes/api.php` - Added timesheet-grid route group with GET endpoint
- `routes/web.php` - Added /timesheet route rendering Timesheet page
- `resources/js/Layouts/AppLayout.vue` - Added Timesheet nav item with TableCellsIcon after Calendar

## Decisions Made
- **Direct TimeEntry query over DailyTimeSummary**: Per research recommendation, query TimeEntry directly for freshness. Single member + single week is at most ~50 entries, well within performance limits. DailyTimeSummary reserved for multi-member reporting.
- **Organization timezone for date assignment**: Entries converted to org timezone before assigning to day column, ensuring 11pm EST Monday appears in Monday's column (not Tuesday UTC).
- **Reuse existing permission**: Uses `time-entries:view:own` -- no new permission model changes needed.
- **Dual fetch pattern**: fetchJson with X-XSRF-TOKEN for the new grid endpoint (consistent with Phase 1 useNotificationBell.ts pattern), Zodios api client for existing time entry CRUD operations.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Grid data layer complete: API endpoint returns structured data, Pinia store manages state
- Plan 02-02 (Grid UI Components) can build on this foundation: TimesheetGrid.vue, cells, rows, navigation
- The Timesheet.vue page file does not exist yet -- it will be created in Plan 02-02 as the page that renders the grid components

## Self-Check: PASSED

All 6 key files verified present on disk. Both task commits (740a7e4, 73c5708) verified in git log. vue-tsc type check passed with 0 errors. ESLint passed with 0 errors (2 pre-existing warnings in AppLayout.vue unrelated to changes).

---
*Phase: 02-weekly-timesheet-grid*
*Completed: 2026-02-11*
