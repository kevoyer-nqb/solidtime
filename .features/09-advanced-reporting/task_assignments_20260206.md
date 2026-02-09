# Task Assignments -- Advanced Reporting Feature

Generated: 2026-02-06
PRD Reference: `/home/keven/Documents/solidtime-analysis/.features/09-advanced-reporting/PRD.md`

---

## Task Assignment Table

| Task ID  | Description | Type | Assigned Sub-Agent | Dependencies | Effort | Sprint | Status |
|----------|------------|------|-------------------|-------------|--------|--------|--------|
| TASK-001 | Migration: Add cost_rate and weekly_capacity to members table | Backend / Database | Backend Dev | None | 4h (3 SP) | 1 | To Do |
| TASK-002 | Migration: Add cost_rate to project_members table | Backend / Database | Backend Dev | None | 2h (2 SP) | 1 | To Do |
| TASK-003 | Migration: Add default_cost_rate to organizations table | Backend / Database | Backend Dev | None | 2h (2 SP) | 1 | To Do |
| TASK-004 | Migration: Add cost_rate to time_entries table | Backend / Database | Backend Dev | None | 2h (2 SP) | 1 | To Do |
| TASK-005 | Migration: Add budget_amount to projects table | Backend / Database | Backend Dev | None | 2h (2 SP) | 1 | To Do |
| TASK-006 | Migration: Create report_templates table | Backend / Database | Backend Dev | None | 4h (3 SP) | 1 | To Do |
| TASK-007 | Migration: Create report_schedules table | Backend / Database | Backend Dev | None | 4h (3 SP) | 1 | To Do |
| TASK-008 | Migration: Create expenses table | Backend / Database | Backend Dev | None | 4h (3 SP) | 1 | To Do |
| TASK-009 | Update existing Eloquent models for new columns | Backend | Backend Dev | TASK-001, TASK-002, TASK-003, TASK-004, TASK-005 | 8h (5 SP) | 1 | To Do |
| TASK-010 | Create new Eloquent models (ReportTemplate, ReportSchedule, Expense) | Backend | Backend Dev | TASK-006, TASK-007, TASK-008 | 8h (5 SP) | 1 | To Do |
| TASK-011 | Create CostRateService | Backend | Backend Dev | TASK-009 | 8h (5 SP) | 1 | To Do |
| TASK-012 | Create ExpenseCategory enum | Backend | Backend Dev | None | 1h (1 SP) | 1 | To Do |
| TASK-013 | Create ReportScheduleFrequency and ReportScheduleStatus enums | Backend | Backend Dev | None | 1h (1 SP) | 1 | To Do |
| TASK-014 | Create ProfitabilityReportService | Backend | Backend Dev | TASK-011, TASK-009 | 16h (8 SP) | 2 | To Do |
| TASK-015 | Create UtilizationReportService | Backend | Backend Dev | TASK-009 | 16h (8 SP) | 2 | To Do |
| TASK-016 | Create ReportScheduleService | Backend | Backend Dev | TASK-010, TASK-013 | 16h (8 SP) | 2 | To Do |
| TASK-017 | Create SendScheduledReportsCommand and ScheduledReportMail | Backend | Backend Dev | TASK-016, TASK-014 | 8h (5 SP) | 2 | To Do |
| TASK-018 | Create ReportExportService (Enhanced Export) | Backend | Backend Dev | TASK-014, TASK-015 | 16h (8 SP) | 2 | To Do |
| TASK-019 | Profitability Report API endpoint | Backend | Backend Dev | TASK-014 | 8h (5 SP) | 3 | To Do |
| TASK-020 | Utilization Report API endpoint | Backend | Backend Dev | TASK-015 | 8h (5 SP) | 3 | To Do |
| TASK-021 | Report Templates CRUD API | Backend | Backend Dev | TASK-010 | 8h (5 SP) | 3 | To Do |
| TASK-022 | Report Schedules CRUD API | Backend | Backend Dev | TASK-016 | 8h (5 SP) | 3 | To Do |
| TASK-023 | Expenses CRUD API (with receipt upload) | Backend | Backend Dev | TASK-010, TASK-012 | 12h (8 SP) | 3 | To Do |
| TASK-024 | Budget Report API endpoint | Backend | Backend Dev | TASK-014, TASK-005, TASK-023 | 8h (5 SP) | 3 | To Do |
| TASK-025 | Update Organization/Member/Project API endpoints for new fields | Backend | Backend Dev | TASK-009 | 8h (5 SP) | 3 | To Do |
| TASK-026 | Frontend: Reporting navigation and tab updates | Frontend | Frontend Dev | TASK-019, TASK-020, TASK-024 | 4h (3 SP) | 4 | To Do |
| TASK-027 | Frontend: Profitability Report page | Frontend | Frontend Dev | TASK-026, TASK-019 | 16h (8 SP) | 4 | To Do |
| TASK-028 | Frontend: Utilization Report page | Frontend | Frontend Dev | TASK-026, TASK-020 | 16h (8 SP) | 4 | To Do |
| TASK-029 | Frontend: Budget Report page | Frontend | Frontend Dev | TASK-026, TASK-024 | 8h (5 SP) | 4 | To Do |
| TASK-030 | Frontend: Report Templates UI | Frontend | Frontend Dev | TASK-021 | 8h (5 SP) | 4 | To Do |
| TASK-031 | Frontend: Report Schedule Management UI | Frontend | Frontend Dev | TASK-022 | 8h (5 SP) | 4 | To Do |
| TASK-032 | Frontend: Expenses page and CRUD | Frontend | Frontend Dev | TASK-023 | 16h (8 SP) | 5 | To Do |
| TASK-033 | Frontend: Enhanced Export Modal | Frontend | Frontend Dev | TASK-018 | 8h (5 SP) | 5 | To Do |
| TASK-034 | Frontend: Cost rate and capacity management UI | Frontend | Frontend Dev | TASK-025 | 8h (5 SP) | 5 | To Do |
| TASK-035 | Backfill command for cost_rate on existing time entries | Backend | Backend Dev | TASK-011 | 4h (3 SP) | 6 | To Do |
| TASK-036 | OpenAPI specification update and TS client regeneration | Backend / Docs | Backend Dev | TASK-019, TASK-020, TASK-021, TASK-022, TASK-023, TASK-024, TASK-025 | 6h (3 SP) | 6 | To Do |
| TASK-037 | E2E Playwright tests for all new reporting pages | QA / Frontend | QA Dev | TASK-027, TASK-028, TASK-029, TASK-030, TASK-031, TASK-032 | 16h (8 SP) | 6 | To Do |
| TASK-038 | Vitest component tests for new Vue components | QA / Frontend | QA Dev | TASK-027, TASK-028, TASK-029 | 8h (5 SP) | 6 | To Do |
| TASK-039 | Performance optimization and query indexing | Backend / Database | Backend Dev | TASK-014, TASK-015, TASK-024 | 6h (3 SP) | 6 | To Do |

---

## Summary Statistics

| Metric | Value |
|--------|-------|
| **Total Tasks** | 39 |
| **Total Story Points** | ~195 |
| **Total Effort** | ~310 hours |
| **Duration** | 12 weeks (6 sprints x 2 weeks) |
| **Backend Tasks** | 28 tasks |
| **Frontend Tasks** | 9 tasks |
| **QA Tasks** | 2 tasks |

---

## Sprint Breakdown

### Sprint 1 (Week 1-2): Database Foundation and Models
**Tasks**: TASK-001 through TASK-013
**Story Points**: 32
**Focus**: All migrations, model updates, enums, CostRateService

**Execution Order**:
1. TASK-001, TASK-002, TASK-003, TASK-004, TASK-005, TASK-006, TASK-007, TASK-008, TASK-012, TASK-013 (all parallel, no dependencies)
2. TASK-009 (depends on TASK-001..005)
3. TASK-010 (depends on TASK-006..008)
4. TASK-011 (depends on TASK-009)

### Sprint 2 (Week 3-4): Core Services
**Tasks**: TASK-014, TASK-015, TASK-016, TASK-017, TASK-018
**Story Points**: 37
**Focus**: All report calculation services, scheduled command, export service

**Execution Order**:
1. TASK-014 and TASK-015 (parallel, both depend on TASK-009/011)
2. TASK-016 (depends on TASK-010, TASK-013)
3. TASK-017 (depends on TASK-016, TASK-014)
4. TASK-018 (depends on TASK-014, TASK-015)

### Sprint 3 (Week 5-6): API Layer
**Tasks**: TASK-019 through TASK-025
**Story Points**: 38
**Focus**: All API endpoints for new features

**Execution Order**:
1. TASK-019, TASK-020, TASK-021, TASK-025 (parallel)
2. TASK-022 (depends on TASK-016)
3. TASK-023 (depends on TASK-010, TASK-012)
4. TASK-024 (depends on TASK-014, TASK-005, TASK-023)

### Sprint 4 (Week 7-8): Frontend Report Pages
**Tasks**: TASK-026 through TASK-031
**Story Points**: 34
**Focus**: All new Vue pages and report UIs

**Execution Order**:
1. TASK-026 (depends on TASK-019, TASK-020, TASK-024)
2. TASK-027, TASK-028, TASK-029, TASK-030, TASK-031 (all parallel after TASK-026)

### Sprint 5 (Week 9-10): Expenses and Enhanced Export
**Tasks**: TASK-032, TASK-033, TASK-034
**Story Points**: 18
**Focus**: Expenses UI, export enhancements, cost rate management UI

**Execution Order**:
1. TASK-032, TASK-033, TASK-034 (all parallel)

### Sprint 6 (Week 11-12): Integration and Testing
**Tasks**: TASK-035, TASK-036, TASK-037, TASK-038, TASK-039
**Story Points**: 22
**Focus**: Backfill, OpenAPI update, E2E tests, component tests, performance tuning

**Execution Order**:
1. TASK-035, TASK-036, TASK-039 (parallel)
2. TASK-037, TASK-038 (parallel, after frontend pages complete)

---

## Dependency Risk Assessment

### High-Risk Dependencies

| Task | Depends On | Risk | Mitigation |
|------|-----------|------|------------|
| TASK-009 | TASK-001..005 | 5 parallel migrations must all complete first. Delay in any blocks model updates. | Migrations are simple column additions; low individual risk. Assign all to same developer for coordination. |
| TASK-014 | TASK-011, TASK-009 | Core profitability service depends on two sequential prerequisites. On the critical path. | Prioritize TASK-001 -> TASK-009 -> TASK-011 in Sprint 1. Start TASK-014 at Sprint 2 day 1. |
| TASK-024 | TASK-014, TASK-005, TASK-023 | Budget report depends on 3 tasks across different sprints. | TASK-005 (migration) completes in Sprint 1. TASK-014 completes in Sprint 2. TASK-023 can be fast-tracked in early Sprint 3. |
| TASK-026 | TASK-019, TASK-020, TASK-024 | Frontend navigation blocked until all 3 API endpoints ready. | APIs are independent of each other; can mock while APIs are being built. Start frontend with mocked data. |

### Tasks With Multiple Dependents (Bottleneck Tasks)

| Task | Depended On By | Impact of Delay |
|------|---------------|-----------------|
| TASK-009 | TASK-011, TASK-014, TASK-015, TASK-025 | Blocks all core services and API updates |
| TASK-014 | TASK-017, TASK-018, TASK-019, TASK-024, TASK-039 | Blocks scheduled reports, export, profitability API, budget API, and performance tuning |
| TASK-010 | TASK-016, TASK-021, TASK-023 | Blocks schedule service, templates API, expenses API |
| TASK-026 | TASK-027, TASK-028, TASK-029 | Blocks all three main report frontend pages |

---

## Notes

- All status values are initialized to "To Do" since no work has started.
- Status transitions: To Do -> In Progress -> Completed (or To Do -> Blocked if external dependency is unavailable).
- No tasks are currently Blocked because all dependencies are internal and all are in "To Do" state.
- The critical path (TASK-001 -> TASK-009 -> TASK-011 -> TASK-014 -> TASK-019 -> TASK-026 -> TASK-027 -> TASK-037) totals approximately 80 hours of sequential work.
