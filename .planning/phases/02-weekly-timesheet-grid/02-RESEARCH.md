# Phase 2: Weekly Timesheet Grid - Research

**Researched:** 2026-02-10
**Domain:** Vue 3 data grid UI + Laravel API for weekly time entry aggregation and inline editing
**Confidence:** HIGH

## Summary

Phase 2 builds a weekly timesheet grid that lets users view and manage time entries in a Mon-Sun (or configurable week start) grid organized by project/task rows, with day columns showing durations and daily/weekly totals. This is primarily a frontend-heavy phase leveraging the existing `DailyTimeSummary` pre-aggregation table (built in Phase 1) for read performance and the existing `TimeEntry` API endpoints for create/update operations.

The solidtime codebase already has all the backend foundations needed: `DateBoundaryService` (Phase 1) for timezone-safe week boundary calculations, `DailyTimeSummaryService` for pre-aggregated daily totals, existing time entry CRUD API endpoints, Pinia stores for projects/tasks/time entries, the `parseTimeInput()` utility for human-readable duration parsing (supporting "1h 30m", "1:30", decimal hours, etc.), and `formatHumanReadableDuration()` for display formatting. The Organization model already has `week_start_day` (Weekday enum) and `timezone` properties. The User model already has `timezone` and `week_start` properties. The frontend already configures dayjs with `weekStart` from the user's settings.

The grid needs two backend additions: (1) a dedicated API endpoint that returns time entry data structured for the grid (grouped by project+task, with daily totals per cell), and (2) potentially leveraging the `DailyTimeSummary` table for fast grid rendering. The frontend needs a new Pinia store (`useTimesheetGrid.ts`), a new page (`Timesheet.vue`), and a set of grid components. No new npm packages are needed -- the grid is a custom Vue component using Tailwind CSS for layout (not a third-party data grid library) since the requirements describe a specific time-tracking UI pattern, not a generic spreadsheet.

**Primary recommendation:** Build a custom Vue 3 grid component using Tailwind CSS table layout, powered by a new backend API endpoint that queries `DailyTimeSummary` for display and falls back to `TimeEntry` queries for inline editing. Use the existing `parseTimeInput()` and `formatHumanReadableDuration()` utilities for cell editing. Add a new `/timesheet` web route and a `Timesheet.vue` page. The backend endpoint should accept `week_of` date, member_id, and return data pre-structured for the grid.

## Standard Stack

### Core (already in codebase -- no new dependencies)
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Vue 3 | ^3.5.0 | Frontend framework | Already installed |
| Pinia | ^2.1.7 | State management for grid data | Already used for all stores |
| TanStack Vue Query | ^5.56.2 | Server-state caching for grid data | Already used (Calendar page uses it for time entry fetching) |
| dayjs | ^1.11.11 | Date manipulation (week navigation, day formatting) | Already installed with utc, timezone, weekOfYear plugins |
| Tailwind CSS | ^3.4.13 | Grid layout and styling | Already used throughout |
| @heroicons/vue | ^2.1.1 | Navigation arrows (prev/next week) | Already installed |
| parse-duration | ^2.0.1 | Natural language duration parsing | Already installed, used by `parseTimeInput()` |
| Laravel Framework | ^12.19.3 | Backend API | Already installed |
| Carbon | Built-in | Week boundary calculations | Already used via DateBoundaryService |

### Supporting (already in codebase)
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| @vueuse/core | ^12.8.2 | Keyboard shortcuts, element visibility | Optional for keyboard nav in grid |
| reka-ui | ^2.2.0 | Headless UI (Popover for project/task picker in new rows) | If adding new project/task rows needs a picker dropdown |
| class-variance-authority | ^0.7.1 | Variant-based component styling | For cell states (empty, filled, editing, error) |
| @tanstack/vue-table | ^8.21.2 | Headless table utilities | Already installed but overkill for this fixed 7-column grid |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Custom grid component | @tanstack/vue-table | vue-table is installed but designed for dynamic columns/sorting/filtering; a timesheet grid has fixed 7-day columns with custom cell editing -- custom is simpler |
| Custom grid component | AG Grid / Handsontable | Would add a large dependency for a straightforward fixed-column table; the grid is simple enough to build with Tailwind CSS |
| DailyTimeSummary reads | Direct TimeEntry queries | DailyTimeSummary is pre-aggregated and indexed, much faster for grid display; TimeEntry queries needed only for inline editing |

### No New Dependencies Needed
This phase requires zero new npm or composer packages.

## Architecture Patterns

### Backend Directory Structure (new additions marked with +)
```
app/
├── Http/Controllers/Api/V1/
│   └── TimesheetGridController.php              (+)
├── Http/Requests/V1/TimesheetGrid/
│   └── TimesheetGridIndexRequest.php            (+)
├── Http/Resources/V1/TimesheetGrid/
│   └── TimesheetGridResource.php                (+)
├── Service/
│   ├── DateBoundaryService.php                  (existing, Phase 1)
│   ├── DailyTimeSummaryService.php              (existing, Phase 1)
│   └── TimesheetGridService.php                 (+)
routes/
├── api.php                                      (modified - add timesheet-grid routes)
├── web.php                                      (modified - add /timesheet route)
```

### Frontend Directory Structure (new additions marked with +)
```
resources/js/
├── Pages/
│   └── Timesheet.vue                            (+)
├── Components/
│   └── TimesheetGrid/                           (+)
│       ├── TimesheetGrid.vue                    (+)
│       ├── TimesheetGridHeader.vue              (+)
│       ├── TimesheetGridRow.vue                 (+)
│       ├── TimesheetGridCell.vue                (+)
│       ├── TimesheetGridTotals.vue              (+)
│       └── TimesheetWeekNavigation.vue          (+)
├── utils/
│   └── useTimesheetGrid.ts                      (+)
├── Layouts/
│   └── AppLayout.vue                            (modified - add sidebar nav item)
```

### Pattern 1: Grid Data Structure
**What:** The API returns data structured for direct grid rendering -- a flat array of rows (project+task combos) with 7 cells (one per day), each cell containing total seconds and the underlying time entry IDs.
**When to use:** The timesheet grid API endpoint response.
**Key design:**
```typescript
// Source: Derived from existing TimeEntry and DailyTimeSummary models
interface TimesheetGridResponse {
    week_start: string;        // ISO date of week start (e.g., "2026-02-09")
    week_end: string;          // ISO date of week end (e.g., "2026-02-15")
    days: string[];            // Array of 7 ISO date strings
    rows: TimesheetGridRow[];  // Project/task rows
    daily_totals: number[];    // 7 numbers (total seconds per day)
    weekly_total: number;      // Total seconds for the week
}

interface TimesheetGridRow {
    project_id: string | null;
    project_name: string;
    project_color: string;
    task_id: string | null;
    task_name: string | null;
    cells: TimesheetGridCell[];  // 7 cells, one per day
    row_total: number;           // Total seconds for this row
}

interface TimesheetGridCell {
    date: string;                // ISO date (e.g., "2026-02-10")
    total_seconds: number;       // Aggregated duration for this day/project/task
    time_entry_ids: string[];    // Underlying time entry IDs for editing
}
```

### Pattern 2: Dedicated Timesheet Grid API Endpoint
**What:** A new controller that queries DailyTimeSummary for grid display data, joining with projects/tasks for names and colors.
**When to use:** Loading the grid view.
**Key design:**
```php
// Source: Existing patterns from TimeEntryController + DailyTimeSummaryService
class TimesheetGridController extends Controller
{
    public function index(
        Organization $organization,
        TimesheetGridIndexRequest $request,
        DateBoundaryService $dateBoundaryService
    ): JsonResponse {
        $this->checkPermission($organization, 'time-entries:view:own');

        $user = $this->user();
        $member = $this->member($organization);
        $timezone = $organization->getEffectiveTimezone();
        $weekStartDay = $organization->week_start_day;

        // Parse the requested date (defaults to today)
        $date = $request->has('date')
            ? Carbon::parse($request->input('date'))
            : Carbon::now();

        // Get week boundaries using Phase 1's DateBoundaryService
        $weekDates = $dateBoundaryService->getWeekDates($date, $timezone, $weekStartDay);
        $weekBoundariesUtc = $dateBoundaryService->getWeekBoundariesUtc(
            $date, $timezone, $weekStartDay
        );

        // Query time entries for this member's week
        // Group by project_id + task_id, aggregate by day
        $rows = $this->buildGridRows(
            $organization, $member, $weekBoundariesUtc, $weekDates, $timezone
        );

        return response()->json([
            'data' => [
                'week_start' => $weekDates[0]->format('Y-m-d'),
                'week_end' => $weekDates[6]->format('Y-m-d'),
                'days' => array_map(fn($d) => $d->format('Y-m-d'), $weekDates),
                'rows' => $rows,
                'daily_totals' => $this->calculateDailyTotals($rows),
                'weekly_total' => $this->calculateWeeklyTotal($rows),
            ],
        ]);
    }
}
```

### Pattern 3: Pinia Store with TanStack Query for Grid Data
**What:** A Pinia store that manages the current week, fetches grid data via TanStack Query, and handles cell editing through the existing time entry API.
**When to use:** The `Timesheet.vue` page.
**Key design:**
```typescript
// Source: Existing patterns from useTimeEntries.ts, Calendar.vue
export const useTimesheetGridStore = defineStore('timesheetGrid', () => {
    const currentWeekDate = ref<string>(dayjs().format('YYYY-MM-DD'));
    const { handleApiRequestNotifications } = useNotificationsStore();
    const queryClient = useQueryClient();

    // Navigate weeks
    function goToPreviousWeek() {
        currentWeekDate.value = dayjs(currentWeekDate.value)
            .subtract(7, 'day')
            .format('YYYY-MM-DD');
    }
    function goToNextWeek() {
        currentWeekDate.value = dayjs(currentWeekDate.value)
            .add(7, 'day')
            .format('YYYY-MM-DD');
    }
    function goToCurrentWeek() {
        currentWeekDate.value = dayjs().format('YYYY-MM-DD');
    }

    // Invalidate grid data after edits
    function invalidateGrid() {
        queryClient.invalidateQueries({
            queryKey: ['timesheet-grid'],
        });
    }

    // Cell editing: update existing time entry duration
    async function updateCellDuration(
        timeEntryId: string,
        newDurationSeconds: number
    ) { /* ... uses existing api.updateTimeEntry ... */ }

    // Cell creation: create new time entry for empty cell
    async function createCellEntry(
        projectId: string | null,
        taskId: string | null,
        date: string,
        durationSeconds: number
    ) { /* ... uses existing api.createTimeEntry ... */ }

    return {
        currentWeekDate,
        goToPreviousWeek, goToNextWeek, goToCurrentWeek,
        invalidateGrid,
        updateCellDuration,
        createCellEntry,
    };
});
```

### Pattern 4: Cell Inline Editing using Existing Duration Parsing
**What:** Each grid cell is an editable input that uses the existing `parseTimeInput()` utility (which already supports "1h 30m", "1:30", "2.5", decimal hours, etc.) and `formatHumanReadableDuration()` for display.
**When to use:** Clicking on a grid cell to edit duration.
**Key design:**
```typescript
// Source: resources/js/packages/ui/src/utils/time.ts (existing)
// parseTimeInput() already handles:
//   "90" → 5400s (90 minutes by default)
//   "1:30" → 5400s (HH:MM)
//   "1:30:00" → 5400s (HH:MM:SS)
//   "1.5" → 5400s (decimal hours)
//   "1h 30m" → 5400s (natural language)
// formatHumanReadableDuration() formats back to display string

// The grid cell component reuses the same pattern as TimeEntryRowDurationInput.vue
```

### Pattern 5: Week Navigation with Organization Week Start
**What:** Navigation controls that respect the organization's configurable `week_start_day` setting, using the existing `getWeekStart()` settings utility and dayjs `weekStart` configuration.
**When to use:** The week navigation bar above the grid.
**Key design:**
```typescript
// Source: resources/js/packages/ui/src/utils/time.ts (existing)
// getDayJsInstance() already configures weekStart from organization settings
// firstDayIndex computed already maps org weekday to dayjs index

// Week display: "Feb 9 - Feb 15, 2026" with prev/next arrows
// dayjs().startOf('week') already respects the configured firstDayIndex
```

### Anti-Patterns to Avoid
- **Loading all time entries then aggregating client-side:** The grid should use the pre-aggregated `DailyTimeSummary` table or a server-side GROUP BY, not fetch raw time entries and sum them in JavaScript. The existing Time.vue page does this for the chronological list, but a grid needs per-day-per-project aggregation which is expensive client-side.
- **Creating a full start/end time entry from a duration-only cell:** When a user enters "2h" in an empty cell, the backend should create a time entry with a sensible start time (e.g., start of the business day 09:00 in the user's timezone) and end = start + duration. Do NOT prompt the user for start/end times in the grid -- that defeats the purpose of the quick-entry grid pattern.
- **Re-fetching the entire grid after each cell edit:** Use optimistic updates -- update the cell value immediately in the UI, then sync to backend. Only re-fetch the full grid if the mutation fails (rollback).
- **Using @tanstack/vue-table for the grid:** While it is installed in the codebase, TanStack Table is designed for dynamic data tables with sorting, filtering, and pagination. The timesheet grid has exactly 7 fixed day columns plus a totals column, with custom cell editing. A simple HTML table with Tailwind CSS is more appropriate and easier to maintain.
- **Ignoring timezone in day columns:** The grid day columns must represent local dates in the organization's timezone (or the user's timezone), not UTC dates. A time entry at 11pm EST on Monday should appear in Monday's column, not Tuesday's (UTC).

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Duration parsing | Custom regex parser | Existing `parseTimeInput()` from `resources/js/packages/ui/src/utils/time.ts` | Already handles "1h 30m", "1:30", decimal hours, plain numbers; tested in production |
| Duration formatting | Custom formatter | Existing `formatHumanReadableDuration()` | Respects org interval_format (decimal, hours-minutes, colon-separated) and number_format |
| Week boundary calculations | Custom date math | Phase 1's `DateBoundaryService` (PHP) and `getDayJsInstance().startOf('week')` (JS) | DST-safe, handles configurable week start day |
| Time entry CRUD | New endpoints | Existing `TimeEntryController` store/update/destroy | Already handles overlap checking, billable_rate computation, permission checks, project recalculation |
| Project/task list | Custom fetch | Existing `useProjectsStore` and `useTasksStore` Pinia stores | Already cached and shared across the app |
| Organization timezone/weekstart | Custom lookup | `Organization.getEffectiveTimezone()`, `Organization.week_start_day` (PHP); `getWeekStart()`, `getUserTimezone()` (JS) | Already available and tested |
| API client types | Manual TypeScript types | Regenerate via `npm run zod:generate` after adding OpenAPI annotations | Zodios client auto-generates types from OpenAPI spec |

**Key insight:** Nearly all the data layer foundations exist from Phase 1 and the existing codebase. This phase is primarily about building the grid UI and one new API endpoint that structures existing data for grid consumption. Resist the urge to rebuild time entry management -- use the existing API endpoints for CRUD operations.

## Common Pitfalls

### Pitfall 1: Timezone Mismatch Between Grid Columns and Time Entry Dates
**What goes wrong:** Grid shows "Monday" column but a time entry created at 11pm local on Monday shows up in Tuesday's column because the backend groups by UTC date instead of local date.
**Why it happens:** Time entries store `start` and `end` as UTC timestamps. Grouping by date without timezone conversion puts entries in the wrong day.
**How to avoid:** The grid API endpoint must convert UTC timestamps to the organization's timezone before grouping by date. Use `DateBoundaryService::getDayBoundaries()` to get UTC boundaries for each local date, then query entries overlapping those UTC ranges.
**Warning signs:** Time entries near midnight appearing in the wrong day column; users in non-UTC timezones seeing mismatched totals.

### Pitfall 2: Multiple Time Entries Per Cell Creating Confusion
**What goes wrong:** A project/task row for "ProjectA / TaskB" on Monday has 3 time entries (30min + 45min + 15min). The cell shows "1h 30min". User edits to "2h". Which entry gets updated? All of them? One of them?
**Why it happens:** The grid aggregates multiple time entries into a single cell value, but editing needs to work on individual entries.
**How to avoid:** Define clear editing semantics:
- **If cell has 1 entry:** Edit updates that entry's duration (adjust end time to match new duration).
- **If cell has multiple entries:** Edit changes the total by adjusting the LAST entry's duration to make the sum match. Alternatively, show a "2 entries" indicator and open a detailed view on click.
- **If cell is empty:** Create a new time entry with the entered duration.
Document this behavior clearly in the UI (e.g., tooltip showing "3 entries, total: 1h 30min").
**Warning signs:** Users editing a cell and seeing unexpected results; duration math not adding up.

### Pitfall 3: Stale DailyTimeSummary Data
**What goes wrong:** User edits a time entry via the grid, but the grid still shows the old aggregated value because `DailyTimeSummary` hasn't been re-aggregated.
**Why it happens:** `DailyTimeSummary` is pre-aggregated by a scheduled command (Phase 1). If the grid reads from this table, edits won't be reflected until the next aggregation run.
**How to avoid:** Two strategies:
1. **Preferred:** The grid endpoint queries `TimeEntry` directly with a GROUP BY (not DailyTimeSummary) for the current member's grid view. DailyTimeSummary is for reporting across many members; the single-member grid is fast enough with a direct query.
2. **Alternative:** Use DailyTimeSummary for initial load but trigger re-aggregation after edits (adds complexity).
The first approach is simpler and avoids staleness entirely.
**Warning signs:** Grid shows different totals than the Time page for the same day.

### Pitfall 4: Time Entry Creation Without Start/End Times
**What goes wrong:** The grid cell only has a duration, but `TimeEntry.start` is required and `TimeEntryStoreRequest` validates `start` as required with format `Y-m-d\TH:i:s\Z`.
**Why it happens:** The grid is duration-based, not start/end based. But the backend requires start/end.
**How to avoid:** When creating a time entry from an empty grid cell, compute:
- `start`: The beginning of the target date in the user's timezone, converted to UTC. Or use a default business hour (e.g., 09:00 local).
- `end`: `start + duration` (null if duration is 0).
- The frontend should synthesize these before calling the existing `createTimeEntry` API.
**Warning signs:** API validation errors when creating entries from the grid.

### Pitfall 5: Configurable Week Start Day Breaking Grid Layout
**What goes wrong:** An organization with `week_start_day: saturday` (e.g., retail) has the grid showing Mon-Sun instead of Sat-Fri.
**Why it happens:** Hardcoding Mon-Sun column headers instead of dynamically generating them from the organization's `week_start_day`.
**How to avoid:** Generate day column headers dynamically:
```typescript
const dayHeaders = computed(() => {
    const dayjs = getDayJsInstance(); // Already respects org weekStart
    const start = dayjs(currentWeekDate.value).startOf('week');
    return Array.from({ length: 7 }, (_, i) => start.add(i, 'day'));
});
```
**Warning signs:** Day headers don't match the organization's configured week start; totals for the week are wrong because boundaries are offset.

### Pitfall 6: Overlap Checking on Grid Cell Creation
**What goes wrong:** User adds "2h" to Monday for ProjectA, but they already have a time entry for that time slot from the regular time tracker. The API returns an overlap error.
**Why it happens:** The existing `TimeEntryController::store()` checks for overlapping entries. Grid-created entries with synthesized start/end times may overlap with manually tracked entries.
**How to avoid:** When synthesizing start/end times for grid-created entries, check for existing entries on that date and choose a non-overlapping time slot. Alternatively, if the organization has `prevent_overlapping_time_entries: false`, this is not an issue. If overlap prevention is enabled, the grid should pick a time slot that doesn't overlap (e.g., find gaps in the day).
**Warning signs:** "Overlapping time entry" error when creating entries from the grid in orgs with overlap prevention enabled.

## Code Examples

Verified patterns from the existing codebase:

### Fetching Grid Data with TanStack Query (following Calendar.vue pattern)
```typescript
// Source: resources/js/Pages/Calendar.vue (existing pattern)
import { useQuery, useQueryClient } from '@tanstack/vue-query';
import { getCurrentOrganizationId, getCurrentMembershipId } from '@/utils/useUser';

const { data: gridData, isLoading } = useQuery({
    queryKey: ['timesheet-grid', getCurrentOrganizationId(), currentWeekDate],
    queryFn: () =>
        fetchTimesheetGrid(getCurrentOrganizationId()!, currentWeekDate.value),
    enabled: !!getCurrentOrganizationId(),
});

async function fetchTimesheetGrid(orgId: string, date: string) {
    // Uses direct fetch with X-XSRF-TOKEN (Phase 1 pattern)
    const response = await fetch(
        `/api/v1/organizations/${orgId}/timesheet-grid?date=${date}`,
        {
            headers: {
                'Accept': 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
        }
    );
    return response.json();
}
```

### Duration Parsing in Grid Cell (reusing existing utility)
```typescript
// Source: resources/js/packages/ui/src/utils/time.ts (existing)
import { parseTimeInput, formatHumanReadableDuration } from '@/packages/ui/src/utils/time';

// On cell blur/enter, parse user input:
const seconds = parseTimeInput(userInput, defaultUnit); // defaultUnit from org settings
// "1:30" => 5400, "90" => 5400 (minutes), "1.5" => 5400 (decimal hours), "1h 30m" => 5400

// Display formatted:
const display = formatHumanReadableDuration(
    seconds,
    organization.interval_format,  // e.g., 'hours-minutes'
    organization.number_format     // e.g., 'point'
);
// => "1h 30min" or "1:30" or "1.50 h" depending on org settings
```

### Creating Time Entry from Grid Cell (reusing existing API)
```typescript
// Source: resources/js/utils/useTimeEntries.ts (existing pattern)
import { api } from '@/packages/api/src';
import { getCurrentOrganizationId, getCurrentMembershipId } from '@/utils/useUser';
import { getUserTimezone } from '@/packages/ui/src/utils/settings';
import dayjs from 'dayjs';

async function createCellEntry(
    projectId: string | null,
    taskId: string | null,
    date: string,         // ISO date "2026-02-10"
    durationSeconds: number
) {
    const timezone = getUserTimezone();
    // Synthesize start time: 09:00 in user's timezone on the target date
    const localStart = dayjs.tz(`${date} 09:00:00`, timezone);
    const utcStart = localStart.utc().format('YYYY-MM-DDTHH:mm:ss[Z]');
    const utcEnd = localStart.add(durationSeconds, 'second').utc().format('YYYY-MM-DDTHH:mm:ss[Z]');

    const orgId = getCurrentOrganizationId()!;
    const memberId = getCurrentMembershipId()!;

    await api.createTimeEntry(
        {
            member_id: memberId,
            project_id: projectId,
            task_id: taskId,
            start: utcStart,
            end: utcEnd,
            billable: false, // Default; user can change later
            description: '',
            tags: [],
        },
        { params: { organization: orgId } }
    );
}
```

### Backend Grid Query (grouping time entries by project/task/date)
```php
// Source: Existing TimeEntry model + DailyTimeSummaryService aggregation pattern
// This queries time entries directly (not DailyTimeSummary) for freshness
$weekDates = $dateBoundaryService->getWeekDates($date, $timezone, $weekStartDay);

$rows = TimeEntry::query()
    ->where('organization_id', $organization->getKey())
    ->where('member_id', $member->getKey())
    ->where('start', '>=', $weekBoundariesUtc['start'])
    ->where(function ($q) use ($weekBoundariesUtc) {
        $q->where('end', '<=', $weekBoundariesUtc['end'])
          ->orWhereNull('end');
    })
    ->whereNotNull('end')
    ->with(['project:id,name,color', 'task:id,name'])
    ->get()
    ->groupBy(fn ($entry) => $entry->project_id . '|' . $entry->task_id);

// Then map each group into a grid row with 7 cells,
// assigning each entry to its local date using timezone conversion
```

### Web Route Registration (following existing pattern)
```php
// Source: routes/web.php (existing pattern)
Route::get('/timesheet', function () {
    return Inertia::render('Timesheet');
})->name('timesheet');
```

### Sidebar Navigation Addition (following AppLayout.vue pattern)
```vue
<!-- Source: resources/js/Layouts/AppLayout.vue (existing) -->
<!-- Add after Calendar nav item -->
<NavigationSidebarItem
    title="Timesheet"
    :icon="TableCellsIcon"
    :current="route().current('timesheet')"
    :href="route('timesheet')"
></NavigationSidebarItem>
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Heavy third-party data grid (AG Grid) | Custom lightweight grid component | Industry trend for time-tracking apps | Avoids large dependency, tailored UX |
| Fetch all entries + client-side aggregation | Server-side GROUP BY + dedicated endpoint | Best practice | Better performance for large datasets |
| Fixed UTC date grouping | Timezone-aware local date grouping | Phase 1 (DateBoundaryService) | Correct day assignment for all timezones |
| Fixed Monday start | Configurable week start day | Phase 1 (Organization.week_start_day) | Supports retail/healthcare schedules |

**Deprecated/outdated:**
- The existing `TimeEntryGroupedTable.vue` groups time entries by date and shows them as rows -- this is not a grid pattern. The timesheet grid is a fundamentally different layout (projects as rows, dates as columns). Do NOT try to adapt the existing grouped table component.

## Open Questions

1. **Billable default for grid-created entries**
   - What we know: The existing `TimeEntryStoreRequest` requires `billable` (boolean). The `Project.is_billable` property determines the project-level default.
   - What's unclear: Should grid-created entries inherit the project's `is_billable` setting, or always default to false?
   - Recommendation: Default to the project's `is_billable` property when `project_id` is set, false otherwise. This matches the existing behavior in the time tracker.

2. **Handling running (active) time entries in the grid**
   - What we know: A time entry with `end: null` is currently running. The existing Time page shows it separately with a live timer.
   - What's unclear: Should running entries appear in the grid? They have no end time, so no duration to display.
   - Recommendation: Do NOT include running entries in the grid (filter them out with `whereNotNull('end')`). The running entry is already shown in the sidebar timer (`CurrentSidebarTimer.vue`). Users stop entries before they appear in the grid.

3. **Adding new project/task rows to the grid**
   - What we know: The grid shows rows for project/task combos that have entries in the current week. TSG-04 says "User can add new time entries by clicking empty grid cells."
   - What's unclear: How does a user create an entry for a project/task combo that doesn't have any entries this week? Do they need an "Add Row" button?
   - Recommendation: Add an "Add Row" button at the bottom of the grid that opens a project/task picker (reusing existing ProjectDropdown/TaskDropdown components). Once selected, the new row appears in the grid with all cells empty, ready for duration entry.

4. **Whether to use DailyTimeSummary or direct TimeEntry queries for the grid**
   - What we know: DailyTimeSummary is pre-aggregated and fast. But it may be stale if entries were just edited.
   - What's unclear: Whether the staleness latency is acceptable for the grid.
   - Recommendation: Use direct TimeEntry queries for the single-member grid view. The query is simple (one member, one week, ~50 entries max) and will be fast with existing indexes. Reserve DailyTimeSummary for the multi-member reporting features in later phases. This avoids the staleness problem entirely.

5. **Multi-entry cells edit behavior**
   - What we know: A cell may contain multiple time entries (e.g., user tracked 30min in the morning and 45min in the afternoon for the same project/task).
   - What's unclear: When the user edits such a cell, which entry should be modified?
   - Recommendation: For cells with multiple entries, adjust the last entry to match the new total. Example: cell has [30min, 45min] = 1h15min total. User edits to "2h". Last entry changes from 45min to 1h30min (so total = 30min + 1h30min = 2h). This is intuitive and non-destructive. Display a small indicator (e.g., "2 entries") for transparency.

## Sources

### Primary (HIGH confidence)
- **Codebase analysis** - Direct reading of solidtime source code:
  - `app/Service/DateBoundaryService.php` - Phase 1 timezone-safe boundary calculations, includes `getWeekDates()` method
  - `app/Service/DailyTimeSummaryService.php` - Phase 1 pre-aggregation with LEAST/GREATEST pattern
  - `app/Models/TimeEntry.php` - Time entry model with project/task relationships
  - `app/Models/DailyTimeSummary.php` - Pre-aggregated table with org/member/project/task/date fields
  - `app/Models/Organization.php` - `week_start_day` (Weekday enum), `timezone`, `getEffectiveTimezone()`
  - `app/Http/Controllers/Api/V1/TimeEntryController.php` - Existing CRUD with overlap checking, permission patterns
  - `app/Http/Controllers/Api/V1/Controller.php` - Base controller with `checkPermission()` and `PermissionStore`
  - `app/Http/Requests/V1/TimeEntry/TimeEntryStoreRequest.php` - Validation rules for creating entries
  - `resources/js/utils/useTimeEntries.ts` - Pinia store pattern for time entries
  - `resources/js/Pages/Time.vue` - Current time entry list page
  - `resources/js/Pages/Calendar.vue` - TanStack Query pattern for time entry fetching with date ranges
  - `resources/js/packages/ui/src/utils/time.ts` - Duration parsing, formatting, timezone utilities
  - `resources/js/packages/ui/src/utils/settings.ts` - `getWeekStart()`, `getUserTimezone()`
  - `resources/js/packages/ui/src/TimeEntry/TimeEntryRowDurationInput.vue` - Existing inline duration editing component
  - `resources/js/packages/ui/src/Input/DurationInput.vue` - Simpler duration input component
  - `resources/js/utils/useUser.ts` - Organization/member ID retrieval
  - `resources/js/utils/permissions.ts` - Permission checking utilities
  - `resources/js/Layouts/AppLayout.vue` - Navigation sidebar structure
  - `routes/web.php` - Page route registration pattern
  - `routes/api.php` - API route organization pattern
  - `app/Enums/Weekday.php` - Week start day enum with `carbonWeekDay()`
  - `resources/js/types/models.ts` - User model with `timezone` and `week_start` properties

### Secondary (MEDIUM confidence)
- **Phase 1 Research** - `.planning/phases/01-shared-foundations/01-RESEARCH.md` - Verified patterns for DateBoundaryService, DailyTimeSummary, notification system, and permission architecture
- **Phase 1 Plans** - `01-01-PLAN.md`, `01-02-PLAN.md` - Confirmed Phase 1 deliverables including DateBoundaryService.getWeekDates() and DailyTimeSummary schema

### Tertiary (LOW confidence)
- None. All findings verified against actual codebase files.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All libraries verified in package.json/composer.json; zero new dependencies needed
- Architecture: HIGH - All patterns derived from existing codebase conventions (Calendar.vue for TanStack Query, TimeEntryController for API, Pinia stores for state)
- Grid data structure: HIGH - Derived from existing TimeEntry and DailyTimeSummary models; grid rows/cells map directly to the data model
- Duration parsing/formatting: HIGH - Existing `parseTimeInput()` and `formatHumanReadableDuration()` in codebase handle all required formats
- Week navigation: HIGH - Existing `getDayJsInstance()` with weekStart config and `DateBoundaryService.getWeekDates()` handle this
- Cell editing semantics: MEDIUM - Multi-entry cell editing behavior is a design decision; recommendation is based on common timesheet app patterns
- Pitfalls: HIGH - Timezone, overlap, and staleness issues identified from actual codebase analysis

**Research date:** 2026-02-10
**Valid until:** 2026-03-10 (30 days -- stable existing stack, no fast-moving dependencies)
