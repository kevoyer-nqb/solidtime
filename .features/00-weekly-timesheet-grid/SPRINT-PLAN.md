# Sprint Plan: Weekly Timesheet Grid

**Date**: 2026-02-06
**Feature**: 00 - Weekly Timesheet Grid
**Branch**: `feature/weekly-timesheet-grid` (from `main`)
**Task Prefix**: `TSG-`
**PRD Reference**: `.features/00-weekly-timesheet-grid/PRD.md`
**Architecture Reference**: `.features/00-weekly-timesheet-grid/ARCHITECTURE.md`

---

## 1. Executive Summary

The Weekly Timesheet Grid adds a spreadsheet-like weekly view to Solidtime, inspired by Everhour's timesheet interface. Users see an accordion of weeks, each expandable to a grid with project/task rows and day columns. Cells are editable inline for fast time entry. The feature includes "Add Task", "Add Last Week's Tasks", keyboard navigation, and optimistic updates.

**Total effort estimate**: 137 hours

**Total story points**: ~75 SP

**Number of sprints**: **3 sprints** (6 weeks)

**Team size assumptions**:
- 1 Backend Developer (senior, ~30 productive hours/sprint)
- 1 Frontend Developer (senior, ~30 productive hours/sprint)
- Concurrent work where dependency graph allows

**Key constraints**:
- No schema changes (uses existing `time_entries` table)
- No new permissions (reuses existing `time-entries:*` permissions)
- Backend must be completed before frontend can consume APIs (TSG-006 is the bridge)
- This feature must be merged to `main` before Feature 01 (Timesheet Approvals) can start

---

## 2. Sprint Overview Table

| Sprint | Name | Duration | Story Points | Key Deliverables |
|--------|------|----------|:------------:|------------------|
| **1** | Backend Foundation | 2 weeks | 28 SP | Controller, Service, Routes, Validation, OpenAPI, Indexes, Endpoint Tests |
| **2** | Frontend Implementation | 2 weeks | 31 SP | Timesheet page, Grid/Cell/Accordion/AddTask components, Pinia store, Navigation, Last Week's Tasks, Optimistic Updates, Load More |
| **3** | Polish & Testing | 2 weeks | 16 SP | Keyboard navigation, ARIA, Component tests, E2E tests, JSDoc docs |

**Total**: ~75 SP across 6 weeks (Sprint 3 testing/polish can be parallelized with Sprint 2)

---

## 3. Dependency Map

### 3.1 Task Dependencies

```
Wave 1 (No dependencies — Sprint 1 start):
    TSG-001 (Controller stubs, 4h)
    TSG-002 (Service logic, 12h)
    TSG-014 (Vitest setup, 4h)

Wave 2 (after Wave 1):
    TSG-003 (Routes, 1h)         ← TSG-001
    TSG-004 (Validation, 4h)     ← TSG-001
    TSG-012 (DB indexes, 2h)     ← TSG-002

Wave 3 (after Wave 2):
    TSG-005 (Wire controller, 4h)  ← TSG-001, TSG-002, TSG-004
    TSG-013 (Endpoint tests, 8h)   ← TSG-003, TSG-005

Wave 4 (after Wave 3 — Sprint 2 start):
    TSG-006 (OpenAPI + TS client, 4h)  ← TSG-003, TSG-005

Wave 5 (after Wave 4):
    TSG-007 (Timesheet.vue page, 4h)  ← TSG-006
    TSG-009 (Pinia store + types, 8h)  ← TSG-006

Wave 6 (after Wave 5):
    TSG-008 (5 UI components, 16h)     ← TSG-007
    TSG-010 (Web route + nav, 2h)      ← TSG-007
    TSG-020 (Load More, 4h)            ← TSG-007, TSG-009
    TSG-017 (JSDoc, 2h)                ← TSG-009

Wave 7 (after Wave 6):
    TSG-011 (Keyboard nav, 6h)         ← TSG-008
    TSG-015 (Component tests, 6h)      ← TSG-008, TSG-014
    TSG-016 (E2E tests, 8h)            ← TSG-010, TSG-008
    TSG-018 (Last Week's Tasks, 4h)    ← TSG-008, TSG-009
    TSG-019 (Optimistic updates, 4h)   ← TSG-009, TSG-008
```

### 3.2 Critical Path

```
TSG-001 (4h) → TSG-004 (4h) → TSG-005 (4h) → TSG-006 (4h) → TSG-007 (4h) → TSG-008 (16h) → TSG-011 (6h)
```

**Critical path duration**: 42 hours of sequential work

### 3.3 Parallelism Opportunities

| Wave | Backend | Frontend | Can run in parallel? |
|------|---------|----------|:--------------------:|
| 1 | TSG-001, TSG-002 | TSG-014 (Vitest setup) | Yes |
| 2 | TSG-003, TSG-004, TSG-012 | — | — |
| 3 | TSG-005, TSG-013 | — | Yes (tests parallel to wiring) |
| 4 | TSG-006 | — | — |
| 5 | — | TSG-007, TSG-009 | Yes (page + store in parallel) |
| 6 | — | TSG-008, TSG-010, TSG-020 | Yes (components + route + pagination) |
| 7 | — | TSG-011, TSG-015, TSG-016, TSG-018, TSG-019 | Yes (all can run in parallel) |

---

## 4. Sprint Details

### Sprint 1 (Weeks 1-2): Backend Foundation

**Goal**: All 4 API endpoints fully functional and tested, database indexes added.

| Task ID | Description | Assignee | SP | Dependencies | Day |
|---------|-------------|----------|:--:|--------------|:---:|
| TSG-001 | Create TimesheetController with endpoint stubs | Backend | 3 | None | 1 |
| TSG-002 | Create TimesheetService with business logic | Backend | 8 | None | 1-3 |
| TSG-003 | Register API routes | Backend | 1 | TSG-001 | 2 |
| TSG-004 | Create request validation classes | Backend | 3 | TSG-001 | 2-3 |
| TSG-005 | Wire up controller to service | Backend | 3 | TSG-001,002,004 | 4 |
| TSG-006 | Update OpenAPI spec + regenerate client | Backend | 3 | TSG-003,005 | 5 |
| TSG-012 | Add composite database indexes | Backend | 2 | TSG-002 | 4 |
| TSG-013 | Create backend endpoint tests | Backend QA | 5 | TSG-003,005 | 5-7 |

**Sprint 1 Total**: 28 SP

**Deliverables**:
- [ ] `TimesheetController` with 4 methods (weeks, index, updateCell, recentTasks)
- [ ] `TimesheetService` with all business logic (getWeekList, getWeekGrid, updateCell, getRecentTasks)
- [ ] 4 request validation classes
- [ ] Routes registered in `api.php`
- [ ] OpenAPI spec updated, TypeScript client regenerated
- [ ] Composite index migration
- [ ] Endpoint tests covering all 4 endpoints

**QA Gate**:
```bash
./vendor/bin/sail exec laravel.test composer fix
./vendor/bin/sail exec laravel.test composer analyse
./vendor/bin/sail exec laravel.test php artisan test --filter=TimesheetEndpointTest
```

---

### Sprint 2 (Weeks 3-4): Frontend Implementation

**Goal**: Fully functional timesheet page with grid, cell editing, add task, accordion, and navigation.

| Task ID | Description | Assignee | SP | Dependencies | Day |
|---------|-------------|----------|:--:|--------------|:---:|
| TSG-007 | Create Timesheet.vue page | Frontend | 3 | TSG-006 | 1 |
| TSG-009 | Create useTimesheetStore + TypeScript types | Frontend | 5 | TSG-006 | 1-2 |
| TSG-008 | Create Grid, Cell, RowHeader, Accordion, AddTask | Frontend | 10 | TSG-007 | 2-5 |
| TSG-010 | Add web route + sidebar navigation | Frontend | 1 | TSG-007 | 2 |
| TSG-014 | Set up Vitest infrastructure | Frontend | 3 | None | 1 |
| TSG-018 | "Add Last Week's Tasks" functionality | Frontend | 3 | TSG-008,009 | 6 |
| TSG-019 | Optimistic cell updates with error rollback | Frontend | 3 | TSG-009,008 | 6-7 |
| TSG-020 | "Load More" week pagination | Frontend | 3 | TSG-007,009 | 5 |

**Sprint 2 Total**: 31 SP

**Deliverables**:
- [ ] `Timesheet.vue` page with AppLayout
- [ ] 5 UI components (Grid, Cell, RowHeader, WeekAccordion, AddTask)
- [ ] `useTimesheetStore` Pinia store with all actions
- [ ] TypeScript type definitions
- [ ] Web route at `/timesheet`
- [ ] Sidebar navigation item
- [ ] "Add Last Week's Tasks" button
- [ ] Optimistic cell updates with rollback
- [ ] "Load More" pagination
- [ ] Vitest infrastructure configured

**QA Gate**:
```bash
npm run lint:fix && npm run format
npm run build  # Verify no build errors
```

**Manual Testing**:
- Navigate to `/timesheet` via sidebar
- Verify week list loads with correct totals
- Expand/collapse weeks
- Edit cells (create, update, delete)
- Add task via dropdown
- Add last week's tasks
- Load more weeks

---

### Sprint 3 (Weeks 5-6): Polish & Testing

**Goal**: Keyboard accessibility, comprehensive test coverage, documentation.

| Task ID | Description | Assignee | SP | Dependencies | Day |
|---------|-------------|----------|:--:|--------------|:---:|
| TSG-011 | Keyboard navigation + ARIA attributes | Frontend | 5 | TSG-008 | 1-2 |
| TSG-015 | Frontend component tests (Vitest) | Frontend QA | 5 | TSG-008,014 | 1-3 |
| TSG-016 | E2E Playwright tests | QA | 5 | TSG-010,008 | 3-5 |
| TSG-017 | JSDoc comments on Pinia store | Frontend | 1 | TSG-009 | 1 |

**Sprint 3 Total**: 16 SP

**Deliverables**:
- [ ] Tab/Enter/Escape keyboard navigation
- [ ] ARIA attributes (aria-expanded, aria-label)
- [ ] Component tests for TimesheetRowHeader (and others)
- [ ] E2E tests covering core user flows
- [ ] JSDoc documentation on store methods

**QA Gate**:
```bash
npm run lint:fix && npm run format
npx vitest run
npx playwright test
./vendor/bin/sail exec laravel.test composer fix
./vendor/bin/sail exec laravel.test composer analyse
```

---

## 5. Risk Register

| Risk | Sprint | Mitigation |
|------|--------|------------|
| TimesheetService aggregation performance | 1 | Composite index (TSG-012), PostgreSQL-side SUM |
| Timezone edge cases in date grouping | 1 | Convert to UTC for queries, user TZ for display |
| Cell editing UX complexity | 2 | Optimistic updates (TSG-019), clear loading/error states |
| Large number of rows per week | 2 | Virtual scroll if needed (not in v1) |
| E2E test flakiness | 3 | Seed predictable data, use explicit waits |

---

## 6. Definition of Done (Feature Complete)

- [ ] All 20 tasks (TSG-001 through TSG-020) completed
- [ ] `composer fix && composer analyse` passes with 0 new errors
- [ ] `npm run lint:fix && npm run format` passes
- [ ] `npm run build` succeeds
- [ ] Backend endpoint tests pass
- [ ] Frontend component tests pass
- [ ] E2E Playwright tests pass
- [ ] All 4 API endpoints documented in OpenAPI spec
- [ ] TypeScript client regenerated
- [ ] Sidebar navigation item functional
- [ ] Feature ready for merge to `main`

---

## 7. Post-Sprint: Merge Strategy

After all 3 sprints complete and QA passes:

1. Rebase `feature/weekly-timesheet-grid` on latest `main`
2. Run full test suite (PHP + JS + E2E)
3. Run `composer fix && composer analyse`
4. Run `npm run lint:fix && npm run format`
5. Create PR targeting `main`
6. After merge: **Feature 01 (Timesheet Approvals)** can begin from `main`
