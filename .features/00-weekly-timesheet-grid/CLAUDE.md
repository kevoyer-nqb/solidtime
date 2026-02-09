# Feature 00: Weekly Timesheet Grid

## Branch
`feature/weekly-timesheet-grid`

## Task Prefix
`TSG-` (TSG-001 through TSG-020)

## Migration Date Prefix
None required — no schema changes (uses existing `time_entries` table)

## Execution Phase
Phase 0 — prerequisite for all other features (01-Approvals depends on this)

## Sprint Summary
| Sprint | Focus | Story Points |
|--------|-------|-------------|
| Sprint 1 | Backend: Controller, Service, Routes, Validation, OpenAPI | ~28 SP |
| Sprint 2 | Frontend: Page, Grid, Cell, Accordion, Add Task, Store | ~38 SP |
| Sprint 3 | Polish: Keyboard nav, Indexes, E2E tests, Component tests, JSDoc | ~25 SP |

**Total**: ~91 SP / ~137h across 3 sprints (6 weeks)

## Shared Foundation Dependencies
- None — this is the foundational feature that others depend on

## Key Architecture Decisions
- No new database tables or migrations (operates on existing `time_entries` table)
- New `TimesheetService` provides aggregation and cell update logic
- New `TimesheetController` with 4 endpoints (weeks, index, updateCell, recentTasks)
- Frontend accordion layout: week list with lazy-loaded grids per week
- Inline cell editing with optimistic updates
- Pinia store (`useTimesheetStore`) manages all timesheet state
- Uses existing `time-entries:view:own` / `time-entries:create:own` permissions (no new permissions needed)
- Time entries grouped by project_id + task_id combination for grid rows

## New Files to Create
- `app/Http/Controllers/Api/V1/TimesheetController.php`
- `app/Service/TimesheetService.php`
- `app/Http/Requests/V1/Timesheet/TimesheetIndexRequest.php`
- `app/Http/Requests/V1/Timesheet/TimesheetCellUpdateRequest.php`
- `app/Http/Requests/V1/Timesheet/TimesheetWeeksRequest.php`
- `app/Http/Requests/V1/Timesheet/TimesheetRecentTasksRequest.php`
- `resources/js/Pages/Timesheet.vue`
- `resources/js/packages/ui/src/Timesheet/TimesheetGrid.vue`
- `resources/js/packages/ui/src/Timesheet/TimesheetCell.vue`
- `resources/js/packages/ui/src/Timesheet/TimesheetWeekAccordion.vue`
- `resources/js/packages/ui/src/Timesheet/TimesheetAddTask.vue`
- `resources/js/packages/ui/src/Timesheet/TimesheetRowHeader.vue`
- `resources/js/utils/useTimesheet.ts`
- `resources/js/types/timesheet.d.ts`
- `tests/Unit/Endpoint/Api/V1/TimesheetEndpointTest.php`
- `resources/js/packages/ui/src/Timesheet/__tests__/TimesheetRowHeader.test.ts`
- `e2e/timesheet.spec.ts`

## Files to Modify
- `routes/api.php` (add timesheet routes)
- `routes/web.php` (add Inertia page route)
- `resources/js/Layouts/AppLayout.vue` (add sidebar nav item)
- `openapi.json` (add timesheet endpoint definitions)
- `resources/js/packages/api/src/openapi.json.client.ts` (regenerate)
- `vite.config.js` (Vitest config if adding component tests)

## Quality Gates
- [ ] `composer fix && composer analyse` passes
- [ ] `npm run lint:fix && npm run format` passes
- [ ] All 4 API endpoints have endpoint tests
- [ ] Service layer has unit tests
- [ ] Frontend components have Vitest tests
- [ ] E2E tests cover core workflow (load page, edit cell, add task)

## Planning Docs
- `PRD.md` — Product requirements
- `task_assignments_20260206.md` — Task breakdown
- `ARCHITECTURE.md` — Technical architecture
- `CODEBASE-ANALYSIS.md` — Integration points
- `SPRINT-PLAN.md` — Sprint-by-sprint implementation plan
