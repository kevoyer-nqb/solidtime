# PRD: Weekly Timesheet Grid

Generated: 2026-02-06
Version: 1.0
Feature Branch: `feature/weekly-timesheet-grid` (from `main`)

---

## Table of Contents

1. [Source & Context](#1-source--context)
2. [Technical Interpretation](#2-technical-interpretation)
3. [Functional Specifications](#3-functional-specifications)
4. [Technical Requirements & Constraints](#4-technical-requirements--constraints)
5. [User Stories with Acceptance Criteria](#5-user-stories-with-acceptance-criteria)
6. [Task Breakdown Structure](#6-task-breakdown-structure)
7. [Dependencies & Integration Points](#7-dependencies--integration-points)
8. [Risk Assessment & Mitigation](#8-risk-assessment--mitigation)
9. [Testing & Validation Requirements](#9-testing--validation-requirements)
10. [Monitoring & Observability](#10-monitoring--observability)
11. [Success Metrics & Definition of Done](#11-success-metrics--definition-of-done)
12. [Technical Debt & Future Considerations](#12-technical-debt--future-considerations)
13. [Appendices](#13-appendices)

---

## 1. Source & Context

### 1.1 Problem Statement

Solidtime currently offers time tracking only through a running timer and manual entry via the Time page. While effective for real-time tracking, many organizations and individual users prefer a **weekly spreadsheet-like view** for fast backfilling, weekly review, and bulk time entry. This mode is the dominant time capture method in competing platforms — offered by Clockify, Everhour, Nutcache, TimeCamp, Harvest, Hubstaff, QuickBooks Time, and Replicon.

Without a weekly timesheet grid:
- Users who track time retroactively must create individual entries one by one
- There is no overview of the entire week's hours at a glance
- Organizations that require weekly time submission have no efficient workflow
- Solidtime falls behind competitors on a feature that has "high adoption for organizations that require weekly submission" (competitive analysis, Section 3.3)

### 1.2 Competitive Analysis

From **features.txt** Section 3.3 — "Weekly timesheet grid":

> **What**: Spreadsheet-like weekly entry for fast backfilling.
> **Why important**: High adoption for organizations that require weekly submission.
> **User flow**:
> 1. Member opens weekly timesheet.
> 2. Adds rows (project/task).
> 3. Enters daily hours quickly (templates help).
> 4. Submits for approval at end of week.

Platforms offering this feature: Clockify, Nutcache, TimeCamp, Everhour, Harvest, Hubstaff, QuickBooks Time, Replicon (8/13 analyzed platforms).

### 1.3 Reference Implementation

Based on **Everhour's timesheet interface**:
- Grid layout with project/task rows and weekday columns
- Direct cell editing for quick time entry (click to edit, type hours)
- Accordion layout showing multiple weeks (current expanded, past collapsed)
- "Add Task" and "Add Recent/Last Week's Tasks" functionality
- Daily column totals and row totals
- Running timer integration (excluded from grid totals)

### 1.4 Current System State

**Existing infrastructure on `main`:**
- `TimeEntry` model with `start`, `end`, `project_id`, `task_id`, `member_id`, `user_id`, `organization_id`, `billable`, `description`, `tags`
- `TimeEntryController` for CRUD operations on individual time entries
- `Project`, `Task`, `Client`, `Member`, `Organization` models
- Existing permission system: `time-entries:view:own`, `time-entries:view:all`, `time-entries:create:own`, `time-entries:create:all`
- Frontend: Vue 3 + TypeScript + Pinia + Inertia.js + TailwindCSS
- API: Laravel Passport authentication, JSON API at `/api/v1/`
- No schema changes required — the feature operates on the existing `time_entries` table

---

## 2. Technical Interpretation

### Business to Technical Translation

| Business Requirement | Technical Implementation |
|---------------------|-------------------------|
| Weekly grid with days as columns | Vue component with 7-column table (configurable week start day) |
| Projects/tasks as rows | Aggregated view grouping `time_entries` by `project_id` + `task_id` |
| Enter hours directly in cells | Inline editable cells that create/update/delete `TimeEntry` records |
| See totals per day and per project | Real-time computed totals from time entry data |
| Navigate between weeks (accordion) | Collapsible week sections with lazy-loaded grid data |
| Quick time entry without timer | Create completed `TimeEntry` records with calculated `start`/`end` |
| Add task to timesheet | "Add Task" dropdown + "Add Last Week's Tasks" button |

### No-Change Boundary

This feature does **NOT**:
- Add new database tables or modify existing schemas
- Introduce new permissions (uses existing `time-entries:*` permissions)
- Modify existing `TimeEntryController` or `TimeEntry` model behavior
- Affect the running timer, existing Time page, or Calendar view
- Require any third-party dependencies beyond what's already in the project

---

## 3. Functional Specifications

### 3.1 Core Requirements

#### REQ-001: Weekly Grid Display
- **Description**: Display a grid with 7 day columns (configurable week start day from user settings) and project/task rows
- **Priority**: P0
- **Edge Cases**:
  - User has no time entries for selected week (show empty grid with "Add Task" option)
  - User has entries for multiple projects (show each as a separate row)
  - Time entries without project assignment (grouped under "No Project")
  - Time entries crossing midnight (assigned to the day they started)
- **Error Scenarios**:
  - API failure loading time entries (show error state with retry button)
  - Network timeout (show loading skeleton, then error)

#### REQ-002: Inline Cell Editing
- **Description**: Click on any cell to edit hours directly
- **Priority**: P0
- **Interaction Flow**:
  1. User clicks on empty cell or existing hours
  2. Cell transforms to input field with focus and text selected
  3. User enters hours (decimal: 1.5, 2.25, 0.5)
  4. On blur or Enter, save changes via API
  5. On Escape, cancel changes
- **Edge Cases**:
  - Invalid input (letters, negative numbers) — show validation error tooltip
  - Zero hours — delete existing time entry(ies)
  - Multiple time entries for same day/project/task — consolidate into single entry on update

#### REQ-003: Row Totals (Per Project/Task)
- **Description**: Show total hours per row on the right side
- **Priority**: P0
- **Calculation**: Sum of all 7 day cells for that row
- **Format**: Display as decimal hours (e.g., "8.5")

#### REQ-004: Column Totals (Per Day)
- **Description**: Show total hours per day at the bottom
- **Priority**: P0
- **Calculation**: Sum of all rows for that day column
- **Format**: Display as decimal hours

#### REQ-005: Accordion Week Layout
- **Description**: Display multiple weeks as collapsible accordion sections with lazy-loaded grids
- **Priority**: P0
- **Features**:
  - Each week is a collapsible section with header showing date range and total hours
  - Current week expanded by default on page load
  - Multiple weeks can be expanded simultaneously
  - "Load More" button at bottom paginates through historical weeks (new weeks loaded collapsed)
  - Grid data lazy-loaded on first expand and cached in store
- **Week Header Format**: "This Week", "Last Week", or date range (e.g., "Jan 27 - Feb 2, 2026")

#### REQ-006: Add Task to Timesheet
- **Description**: Add project/task combinations to the timesheet grid
- **Priority**: P0
- **Methods**:
  1. "Add Task" button opens project/task selector dropdown
  2. "Add Last Week's Tasks" copies rows from the previous week (without hours)
- **Behavior**:
  - Added tasks appear as new rows with 0 hours across all days
  - Duplicate prevention (cannot add same project/task twice in the same week)
  - New rows marked with `isNew: true` and have a remove button
  - Recent tasks dropdown shows project+task combos from recent time entries

### 3.2 User Workflows

```
User opens Timesheet page
    → Load week list (8 weeks, current expanded)
    → Display accordion: current week shows grid

User clicks a cell
    → Cell becomes editable input
    → User types hours (e.g., "2.5")
    → Press Enter or click away → Save via PUT /timesheet/cell
    → Optimistic update: cell shows new value immediately
    → On success: update totals
    → On failure: rollback cell, show error

User clicks "Add Task"
    → Show dropdown with recent tasks
    → Select project/task
    → New row appears with 0 hours

User clicks "Add Last Week's Tasks"
    → Load previous week's grid data
    → Add all project/task combinations as new empty rows
    → Skip duplicates already in current week

User clicks collapsed week header
    → Expand section, lazy-load grid data
    → Show loading spinner while fetching
    → Display grid when data arrives

User clicks "Load More"
    → Fetch next batch of weeks (collapsed)
    → Append to week list
```

### 3.3 Business Rules

#### Time Entry Creation from Cell
1. When user enters hours in an empty cell:
   - Create new `TimeEntry` with `start` = 9:00 AM (user timezone), `end` = start + hours
   - Inherit `project_id`, `task_id` from row
   - Inherit `billable` from project default (`is_billable`)
   - Set `description` to empty string
   - Set `tags` to empty array
   - Set `client_id` from project's client

2. When user edits existing cell:
   - If single entry exists: update `end` time to reflect new duration
   - If multiple entries exist: update first entry's `end`, delete extras (consolidate)
   - If hours set to 0: delete all entries for that cell

#### Row Management
1. Rows with existing time entries are shown automatically
2. Rows added via "Add Task" are marked `isNew: true` and have a remove button
3. Rows are ordered by: project name, then task name
4. "No Project" rows appear last

#### Data Aggregation
1. Time entries grouped by: `project_id` + `task_id` for each day
2. Running timers (`end IS NULL`) excluded from grid
3. Duration calculated as `end - start` in seconds, displayed as decimal hours

---

## 4. Technical Requirements & Constraints

### 4.1 System Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                        Frontend (Vue.js 3)                          │
├─────────────────────────────────────────────────────────────────────┤
│  ┌──────────────┐  ┌────────────────────┐  ┌────────────────────┐  │
│  │ Timesheet.vue│──│ WeekAccordion.vue  │──│ TimesheetGrid.vue  │  │
│  │ (Page)       │  │ (Collapsible week) │  │ ├─ Cell.vue        │  │
│  │ - Week list  │  │ - Header + toggle  │  │ ├─ RowHeader.vue   │  │
│  │ - Load More  │  │ - Lazy load grid   │  │ └─ AddTask.vue     │  │
│  └──────┬───────┘  └────────────────────┘  └────────────────────┘  │
│         │                                                           │
│         ▼                                                           │
│  ┌─────────────────────────────────────┐                           │
│  │ useTimesheetStore.ts (Pinia)        │                           │
│  │ - weekList: WeekSummary[]           │                           │
│  │ - expandedWeeks: Set<string>        │                           │
│  │ - weekDataMap: Map<string, Data>    │                           │
│  │ - loadWeekList() / toggleWeek()     │                           │
│  │ - updateCell() / addTaskRow()       │                           │
│  │ - addLastWeekTasks()                │                           │
│  └──────────────┬──────────────────────┘                           │
│                 │ HTTP/JSON                                         │
└─────────────────│──────────────────────────────────────────────────┘
                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│                        Backend (Laravel 11)                         │
├─────────────────────────────────────────────────────────────────────┤
│  ┌─────────────────────────────────────┐                           │
│  │ TimesheetController.php             │                           │
│  │ - weeks()    GET /timesheet/weeks   │                           │
│  │ - index()    GET /timesheet         │                           │
│  │ - updateCell() PUT /timesheet/cell  │                           │
│  │ - recentTasks() GET /recent-tasks   │                           │
│  └──────────────┬──────────────────────┘                           │
│                 │                                                   │
│                 ▼                                                   │
│  ┌─────────────────────────────────────┐                           │
│  │ TimesheetService.php                │                           │
│  │ - getWeekList()                     │                           │
│  │ - getWeekGrid()                     │                           │
│  │ - updateCell()                      │                           │
│  │ - getRecentTasks()                  │                           │
│  └──────────────┬──────────────────────┘                           │
│                 │                                                   │
│                 ▼                                                   │
│  ┌─────────────────────────────────────┐                           │
│  │ TimeEntry Model (existing)          │                           │
│  │ (No schema changes)                 │                           │
│  └─────────────────────────────────────┘                           │
└─────────────────────────────────────────────────────────────────────┘
```

### 4.2 Data Models

#### Frontend Types (TypeScript)

```typescript
interface TimesheetProjectInfo {
  id: string;
  name: string;
  color: string;
}

interface TimesheetTaskInfo {
  id: string;
  name: string;
}

interface WeekSummary {
  week_start: string;   // YYYY-MM-DD
  week_end: string;     // YYYY-MM-DD
  label: string;        // "This Week", "Last Week", or date range
  total_seconds: number;
}

interface RecentTask {
  project: TimesheetProjectInfo | null;
  task: TimesheetTaskInfo | null;
}

interface TimesheetCell {
  date: string;           // YYYY-MM-DD
  hours: number;
  time_entry_ids: string[];
  isEditing: boolean;     // UI state
  isLoading: boolean;     // UI state
  hasError: boolean;      // UI state
}

interface TimesheetRow {
  id: string;                         // "project_id:task_id" composite key
  project: TimesheetProjectInfo | null;
  task: TimesheetTaskInfo | null;
  cells: TimesheetCell[];             // Always 7 cells
  total_hours: number;
  isNew: boolean;                     // Added this session
}

interface TimesheetWeekData {
  week_start: string;
  week_end: string;
  rows: TimesheetRow[];
  day_totals: number[];               // 7 daily totals
  week_total: number;
}
```

### 4.3 API Contracts

#### GET /api/v1/organizations/{organization}/timesheet/weeks
Fetch list of weeks with totals for accordion display.

```yaml
Parameters:
  organization: string (path, required)
  limit: int (query, optional, default: 8, max: 52)
  offset: int (query, optional, default: 0)
Request Headers:
  Authorization: Bearer {token}
Response 200:
  data: Array<{
    week_start: string,      # YYYY-MM-DD
    week_end: string,        # YYYY-MM-DD
    label: string,           # "This Week", "Last Week", or "Jan 20 - Jan 26, 2026"
    total_seconds: int       # Sum of all time entries for week
  }>
Permission: time-entries:view:own OR time-entries:view:all
```

#### GET /api/v1/organizations/{organization}/timesheet
Fetch timesheet grid data for a specific week.

```yaml
Parameters:
  organization: string (path, required)
  week_start: string (query, required, YYYY-MM-DD)
Request Headers:
  Authorization: Bearer {token}
Response 200:
  data: {
    week_start: string,
    week_end: string,
    rows: Array<{
      id: string,
      project: { id, name, color } | null,
      task: { id, name } | null,
      cells: Array<{ date, hours, time_entry_ids }>,
      total_hours: float
    }>,
    day_totals: float[7],
    week_total: float
  }
Permission: time-entries:view:own OR time-entries:view:all
```

#### PUT /api/v1/organizations/{organization}/timesheet/cell
Update a single timesheet cell (create/update/delete time entries).

```yaml
Parameters:
  organization: string (path, required)
Request Body:
  date: string (required, YYYY-MM-DD)
  project_id: string|null (required)
  task_id: string|null (required)
  hours: float (required, 0-24)
Response 200:
  data: { date, hours, time_entry_ids }
Middleware: check-organization-blocked
Permission: time-entries:create:own OR time-entries:create:all
```

#### GET /api/v1/organizations/{organization}/timesheet/recent-tasks
Get recently used project+task combinations.

```yaml
Parameters:
  organization: string (path, required)
  limit: int (query, optional, default: 10)
Response 200:
  data: Array<{
    project: { id, name, color } | null,
    task: { id, name } | null
  }>
Permission: time-entries:view:own OR time-entries:view:all
```

### 4.4 Performance Requirements

| Metric | Target | Measurement |
|--------|--------|-------------|
| Initial page load | < 500ms | Time to first week list render |
| Grid data fetch | < 1s | API response time for single week |
| Cell save latency | < 300ms | API response time for cell update |
| Week expand | < 500ms | Time from click to grid display (cached) |
| Memory usage | < 50MB | Frontend with 8 weeks loaded |

### 4.5 Security Requirements

1. **Authorization**: Users can only view/edit their own timesheet unless they have `time-entries:view:all` / `time-entries:create:all`
2. **Input Validation**: Hours 0-24, valid date format, existing project/task IDs within organization
3. **Organization Scoping**: All queries scoped to the current organization
4. **Write Protection**: Cell update endpoint uses `check-organization-blocked` middleware
5. **Audit Logging**: Time entry modifications logged via existing `CustomAuditable` trait

---

## 5. User Stories with Acceptance Criteria

### USR-001: View Weekly Timesheet
**As a** Solidtime user
**I want to** see my time entries in a weekly grid format
**So that** I can quickly understand how I spent my time

**Priority**: P0 | **Effort**: 8 SP | **Sprint**: 1-2

**Acceptance Criteria**:
- [ ] Grid displays 7 columns for days of the week
- [ ] Week starts on user's configured `week_start` day
- [ ] Each row shows project name (with color dot) and task name
- [ ] Cells display hours in decimal format (e.g., "2.5")
- [ ] Row totals appear on the right side
- [ ] Day totals appear at the bottom
- [ ] Week total appears in bottom-right corner
- [ ] Loading spinner shown while data loads
- [ ] Empty state shown when no entries exist

### USR-002: Edit Time in Cell
**As a** Solidtime user
**I want to** click on a cell and enter hours directly
**So that** I can quickly log my time without using the timer

**Priority**: P0 | **Effort**: 5 SP | **Sprint**: 2

**Acceptance Criteria**:
- [ ] Clicking a cell activates inline edit mode
- [ ] Input accepts decimal numbers (0.25, 1.5, 8)
- [ ] Enter key saves and exits edit mode
- [ ] Escape key cancels edit without saving
- [ ] Invalid input shows error tooltip
- [ ] Brief loading indicator during save
- [ ] Successful save updates cell and totals immediately
- [ ] Setting hours to 0 deletes the time entry

### USR-003: Browse Weeks via Accordion
**As a** Solidtime user
**I want to** see my weeks in a collapsible accordion layout
**So that** I can quickly browse and compare time across multiple weeks

**Priority**: P0 | **Effort**: 5 SP | **Sprint**: 2

**Acceptance Criteria**:
- [ ] Page loads with a list of recent weeks as collapsible sections
- [ ] Current week expanded by default, others collapsed
- [ ] Each week header shows date range and total hours (formatted as "Xh Ym")
- [ ] Clicking a week header toggles expand/collapse
- [ ] Multiple weeks can be expanded simultaneously
- [ ] "Load More" button at bottom fetches older weeks (collapsed)
- [ ] Grid data lazy-loaded on first expand and cached

### USR-004: Add Task to Timesheet
**As a** Solidtime user
**I want to** add project/task combinations to my timesheet
**So that** I can log time for tasks I haven't worked on yet this week

**Priority**: P0 | **Effort**: 5 SP | **Sprint**: 2

**Acceptance Criteria**:
- [ ] "Add Task" button visible below the grid
- [ ] Clicking opens a dropdown with recent tasks (from API)
- [ ] Selected task appears as new row with 0 hours
- [ ] Cannot add duplicate project/task combination
- [ ] New rows have a remove button (X)
- [ ] "Add Last Week's Tasks" button copies rows from previous week

### USR-005: View Daily and Weekly Totals
**As a** Solidtime user
**I want to** see my total hours per day and for the week
**So that** I can ensure I'm meeting my time tracking goals

**Priority**: P0 | **Effort**: 2 SP | **Sprint**: 2

**Acceptance Criteria**:
- [ ] Daily totals shown in footer row
- [ ] Weekly total shown in footer corner
- [ ] Totals update immediately when cells change
- [ ] Totals exclude running timers

### USR-006: Keyboard Navigation
**As a** power user
**I want to** navigate the timesheet using only my keyboard
**So that** I can enter time quickly without using my mouse

**Priority**: P1 | **Effort**: 3 SP | **Sprint**: 3

**Acceptance Criteria**:
- [ ] Tab moves focus to next cell (right, then next row)
- [ ] Enter confirms edit
- [ ] Escape cancels current edit
- [ ] Focus indicator clearly visible on current cell
- [ ] ARIA attributes for screen reader support

---

## 6. Task Breakdown Structure

See `task_assignments_20260206.md` for the full task table.

| Task ID | Description | Type | Effort | Dependencies |
|---------|-------------|------|--------|--------------|
| TSG-001 | Create TimesheetController with endpoint stubs | Backend | 4h | None |
| TSG-002 | Create TimesheetService with business logic | Backend | 12h | None |
| TSG-003 | Register API routes | Backend | 1h | TSG-001 |
| TSG-004 | Create request validation classes | Backend | 4h | TSG-001 |
| TSG-005 | Wire up controller to service | Backend | 4h | TSG-001, TSG-002, TSG-004 |
| TSG-006 | Update OpenAPI spec + regenerate client | Backend | 4h | TSG-003, TSG-005 |
| TSG-007 | Create Timesheet.vue page | Frontend | 4h | TSG-006 |
| TSG-008 | Create Grid/Cell/RowHeader/Accordion/AddTask | Frontend | 16h | TSG-007 |
| TSG-009 | Create useTimesheetStore + TS types | Frontend | 8h | TSG-006 |
| TSG-010 | Add web route + sidebar nav | Frontend | 2h | TSG-007 |
| TSG-011 | Keyboard navigation + ARIA | Frontend | 6h | TSG-008 |
| TSG-012 | Database indexes | Backend | 2h | TSG-002 |
| TSG-013 | Backend endpoint tests | Testing | 8h | TSG-005, TSG-003 |
| TSG-014 | Vitest infrastructure | Testing | 4h | None |
| TSG-015 | Frontend component tests | Testing | 6h | TSG-008, TSG-014 |
| TSG-016 | E2E Playwright tests | Testing | 8h | TSG-010, TSG-008 |
| TSG-017 | JSDoc comments on store | Docs | 2h | TSG-009 |
| TSG-018 | "Add Last Week's Tasks" | Frontend | 4h | TSG-008, TSG-009 |
| TSG-019 | Optimistic cell updates | Frontend | 4h | TSG-009, TSG-008 |
| TSG-020 | "Load More" pagination | Full-stack | 4h | TSG-007, TSG-009 |

**Total Effort**: 137 hours (~91 SP across 3 sprints)

---

## 7. Dependencies & Integration Points

### 7.1 Internal Dependencies

| Dependency | Description | Impact |
|------------|-------------|--------|
| `TimeEntry` Model | Existing model for data storage | Read + Write (no schema changes) |
| `Project` Model | Referenced for project info display | Read-only |
| `Task` Model | Referenced for task info display | Read-only |
| `Member` Model | Used for permission checks and user scoping | Read-only |
| `Organization` Model | Route model binding, scoping | Read-only |
| `PermissionStore` | Existing permission cache in base Controller | Read-only |

### 7.2 External Dependencies

| Dependency | Version | Purpose |
|------------|---------|---------|
| dayjs | ^1.11.x | Date manipulation (already in project) |
| @heroicons/vue | ^2.x | Icons for UI (already in project) |
| pinia | ^2.x | State management (already in project) |
| TailwindCSS | ^3.x | Styling (already in project) |

No new dependencies required.

### 7.3 Downstream Features

This feature is a **prerequisite** for:
- **Feature 01 (Timesheet Approvals)**: Approval workflow wraps around the weekly grid — submit/approve/lock weeks
- **Feature 05 (Calendar Enhanced)**: Complementary view; users navigate between calendar and timesheet

---

## 8. Risk Assessment & Mitigation

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| Performance with many time entries per week | Medium | High | Composite database indexes, server-side aggregation, pagination |
| Timezone edge cases (entries crossing midnight) | Medium | Medium | Consistent UTC storage, user timezone for display, assign entry to day of `start` |
| Multiple entries per cell consolidation | Medium | Medium | Clear consolidation strategy: update first, delete extras |
| Mobile responsiveness | Low | Medium | Horizontal scroll on table, min-width on cells |
| Optimistic update rollback complexity | Low | Medium | Store previous cell state, restore on API failure |

---

## 9. Testing & Validation Requirements

### 9.1 Test Strategy

| Type | Coverage Target | Tools |
|------|-----------------|-------|
| Backend Unit Tests | All service methods | PHPUnit |
| API Endpoint Tests | All 4 endpoints | PHPUnit (ApiEndpointTestAbstract) |
| Frontend Component Tests | Core components | Vitest + @vue/test-utils |
| E2E Tests | Critical user paths | Playwright |

### 9.2 Key Test Scenarios

**Backend**:
- Week list returns correct totals and labels
- Grid data correctly groups entries by project+task
- Cell update creates new entry when none exist
- Cell update modifies existing entry duration
- Cell update deletes entries when hours = 0
- Cell update consolidates multiple entries
- Permission checks enforced on all endpoints
- Timezone handling for different user timezones

**Frontend**:
- Grid renders correct number of rows and columns
- Cell editing activates and saves correctly
- Validation rejects invalid input
- Totals update when cells change
- Accordion expand/collapse works
- Add Task adds new row
- Remove row removes new rows
- Loading and error states display correctly

**E2E**:
- Navigate to timesheet page from sidebar
- View week grid with existing data
- Edit a cell and verify save persists
- Add a task row and enter hours
- Expand/collapse weeks
- "Load More" fetches additional weeks

---

## 10. Monitoring & Observability

### 10.1 Metrics to Track

| Metric | Type | Alert Threshold |
|--------|------|-----------------|
| Timesheet page load time | Performance | > 2s |
| Cell save latency (P95) | Performance | > 1s |
| API error rate for timesheet endpoints | Error | > 1% |
| Timesheet page daily active users | Business | — |

### 10.2 Logging

All time entry mutations via `updateCell()` are automatically logged by the existing `CustomAuditable` trait on the `TimeEntry` model. No additional logging infrastructure needed.

---

## 11. Success Metrics & Definition of Done

### 11.1 Success Metrics

| Metric | Target | Measurement |
|--------|--------|-------------|
| Feature adoption | 30% of active users within 30 days | Analytics (page visits) |
| Time entry efficiency | 40% faster than individual entry creation | User research |
| Cell save error rate | < 1% | Error tracking |

### 11.2 Definition of Done

- [ ] All 6 core requirements (REQ-001 through REQ-006) implemented
- [ ] All 4 API endpoints working with proper validation and permissions
- [ ] Backend endpoint tests passing
- [ ] Frontend component tests passing
- [ ] E2E tests passing for critical paths
- [ ] `composer fix && composer analyse` passes
- [ ] `npm run lint:fix && npm run format` passes
- [ ] OpenAPI spec updated and TS client regenerated
- [ ] Sidebar navigation item added
- [ ] Keyboard navigation functional
- [ ] Loading and error states handled

---

## 12. Technical Debt & Future Considerations

### 12.1 Known Simplifications

1. **Single-entry consolidation**: When a cell has multiple time entries (e.g., morning + afternoon on same project/task), updating the cell consolidates them into one entry. Future enhancement: show/edit individual entries within a cell.

2. **No row sorting UI**: Rows are auto-sorted by project name + task name. Future: user-draggable row ordering.

3. **No description editing**: Cell editing only changes duration. Descriptions must be edited via the existing Time page. Future: expandable row detail showing descriptions.

### 12.2 Future Enhancements

| Enhancement | Priority | Description |
|-------------|----------|-------------|
| Timesheet approval workflow | P1 | Submit weeks for manager approval (Feature 01) |
| Multi-member view | P2 | Managers view team timesheets side by side |
| Week templates | P3 | Copy a week's row structure to another week |
| Bulk entry patterns | P3 | Fill entire week with 8h/day pattern |
| CSV/PDF export | P3 | Export timesheet to file |

---

## 13. Appendices

### 13.1 File Structure Summary

```
solidtime/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   │   └── TimesheetController.php              # NEW
│   │   └── Requests/V1/Timesheet/
│   │       ├── TimesheetIndexRequest.php             # NEW
│   │       ├── TimesheetCellUpdateRequest.php        # NEW
│   │       ├── TimesheetWeeksRequest.php             # NEW
│   │       └── TimesheetRecentTasksRequest.php       # NEW
│   └── Service/
│       └── TimesheetService.php                      # NEW
├── routes/
│   ├── api.php                                       # MODIFIED (add timesheet routes)
│   └── web.php                                       # MODIFIED (add Inertia route)
├── resources/js/
│   ├── Pages/
│   │   └── Timesheet.vue                             # NEW
│   ├── packages/ui/src/Timesheet/
│   │   ├── TimesheetWeekAccordion.vue                # NEW
│   │   ├── TimesheetGrid.vue                         # NEW
│   │   ├── TimesheetCell.vue                         # NEW
│   │   ├── TimesheetAddTask.vue                      # NEW
│   │   ├── TimesheetRowHeader.vue                    # NEW
│   │   └── __tests__/
│   │       └── TimesheetRowHeader.test.ts            # NEW
│   ├── utils/
│   │   └── useTimesheet.ts                           # NEW
│   ├── types/
│   │   └── timesheet.d.ts                            # NEW
│   └── Layouts/
│       └── AppLayout.vue                             # MODIFIED (add sidebar nav)
├── tests/
│   └── Unit/Endpoint/Api/V1/
│       └── TimesheetEndpointTest.php                 # NEW
└── e2e/
    └── timesheet.spec.ts                             # NEW
```

### 13.2 API Endpoint Summary

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/organizations/{org}/timesheet/weeks` | Get week list with totals |
| GET | `/api/v1/organizations/{org}/timesheet` | Get week grid data |
| PUT | `/api/v1/organizations/{org}/timesheet/cell` | Update cell (create/update/delete entries) |
| GET | `/api/v1/organizations/{org}/timesheet/recent-tasks` | Get recent tasks |

### 13.3 Competitive Feature Matrix (Section 3.3 context)

| Platform | Weekly Timesheet Grid | Accordion/Multi-week | Add Recent Tasks | Import Last Week |
|----------|:--------------------:|:--------------------:|:----------------:|:----------------:|
| Everhour | Yes | Yes | Yes | Yes |
| Clockify | Yes | Yes | Yes | Yes |
| Nutcache | Yes | No | No | Yes (duplicate) |
| TimeCamp | Yes | No | No | No |
| Harvest | Yes | No | No | No |
| **Solidtime (this PRD)** | **Yes** | **Yes** | **Yes** | **Yes** |
