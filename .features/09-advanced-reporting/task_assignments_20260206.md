# Task Assignments -- Advanced Reporting Feature

Generated: 2026-02-06
PRD Reference: `/home/keven/Documents/solidtime-analysis/.features/09-advanced-reporting/PRD.md`

---

## Task Assignment Table

| Task ID  | Description | Type | Assigned Sub-Agent | Dependencies | Effort | Sprint | Status |
|----------|------------|------|-------------------|-------------|--------|--------|--------|
| RPT-001 | Migration: Add cost_rate and weekly_capacity to members table | Backend / Database | Backend Dev | None | 4h (3 SP) | 1 | To Do |
| RPT-002 | Migration: Add cost_rate to project_members table | Backend / Database | Backend Dev | None | 2h (2 SP) | 1 | To Do |
| RPT-003 | Migration: Add default_cost_rate to organizations table | Backend / Database | Backend Dev | None | 2h (2 SP) | 1 | To Do |
| RPT-004 | Migration: Add cost_rate to time_entries table | Backend / Database | Backend Dev | None | 2h (2 SP) | 1 | To Do |
| RPT-005 | Migration: Add budget_amount to projects table | Backend / Database | Backend Dev | None | 2h (2 SP) | 1 | To Do |
| RPT-006 | Migration: Create report_templates table | Backend / Database | Backend Dev | None | 4h (3 SP) | 1 | To Do |
| RPT-007 | Migration: Create report_schedules table | Backend / Database | Backend Dev | None | 4h (3 SP) | 1 | To Do |
| RPT-008 | Migration: Create expenses table | Backend / Database | Backend Dev | None | 4h (3 SP) | 1 | To Do |
| RPT-009 | Update existing Eloquent models for new columns | Backend | Backend Dev | RPT-001, RPT-002, RPT-003, RPT-004, RPT-005 | 8h (5 SP) | 1 | To Do |
| RPT-010 | Create new Eloquent models (ReportTemplate, ReportSchedule, Expense) | Backend | Backend Dev | RPT-006, RPT-007, RPT-008 | 8h (5 SP) | 1 | To Do |
| RPT-011 | Create CostRateService | Backend | Backend Dev | RPT-009 | 8h (5 SP) | 1 | To Do |
| RPT-012 | Create ExpenseCategory enum | Backend | Backend Dev | None | 1h (1 SP) | 1 | To Do |
| RPT-013 | Create ReportScheduleFrequency and ReportScheduleStatus enums | Backend | Backend Dev | None | 1h (1 SP) | 1 | To Do |
| RPT-014 | Create ProfitabilityReportService | Backend | Backend Dev | RPT-011, RPT-009 | 16h (8 SP) | 2 | To Do |
| RPT-015 | Create UtilizationReportService | Backend | Backend Dev | RPT-009 | 16h (8 SP) | 2 | To Do |
| RPT-016 | Create ReportScheduleService | Backend | Backend Dev | RPT-010, RPT-013 | 16h (8 SP) | 2 | To Do |
| RPT-017 | Create SendScheduledReportsCommand and ScheduledReportMail | Backend | Backend Dev | RPT-016, RPT-014 | 8h (5 SP) | 2 | To Do |
| RPT-018 | Create ReportExportService (Enhanced Export) | Backend | Backend Dev | RPT-014, RPT-015 | 16h (8 SP) | 2 | To Do |
| RPT-019 | Profitability Report API endpoint | Backend | Backend Dev | RPT-014 | 8h (5 SP) | 3 | To Do |
| RPT-020 | Utilization Report API endpoint | Backend | Backend Dev | RPT-015 | 8h (5 SP) | 3 | To Do |
| RPT-021 | Report Templates CRUD API | Backend | Backend Dev | RPT-010 | 8h (5 SP) | 3 | To Do |
| RPT-022 | Report Schedules CRUD API | Backend | Backend Dev | RPT-016 | 8h (5 SP) | 3 | To Do |
| RPT-023 | Expenses CRUD API (with receipt upload) | Backend | Backend Dev | RPT-010, RPT-012 | 12h (8 SP) | 3 | To Do |
| RPT-024 | Budget Report API endpoint | Backend | Backend Dev | RPT-014, RPT-005, RPT-023 | 8h (5 SP) | 3 | To Do |
| RPT-025 | Update Organization/Member/Project API endpoints for new fields | Backend | Backend Dev | RPT-009 | 8h (5 SP) | 3 | To Do |
| RPT-026 | Frontend: Reporting navigation and tab updates | Frontend | Frontend Dev | RPT-019, RPT-020, RPT-024 | 4h (3 SP) | 4 | To Do |
| RPT-027 | Frontend: Profitability Report page | Frontend | Frontend Dev | RPT-026, RPT-019 | 16h (8 SP) | 4 | To Do |
| RPT-028 | Frontend: Utilization Report page | Frontend | Frontend Dev | RPT-026, RPT-020 | 16h (8 SP) | 4 | To Do |
| RPT-029 | Frontend: Budget Report page | Frontend | Frontend Dev | RPT-026, RPT-024 | 8h (5 SP) | 4 | To Do |
| RPT-030 | Frontend: Report Templates UI | Frontend | Frontend Dev | RPT-021 | 8h (5 SP) | 4 | To Do |
| RPT-031 | Frontend: Report Schedule Management UI | Frontend | Frontend Dev | RPT-022 | 8h (5 SP) | 4 | To Do |
| RPT-032 | Frontend: Expenses page and CRUD | Frontend | Frontend Dev | RPT-023 | 16h (8 SP) | 5 | To Do |
| RPT-033 | Frontend: Enhanced Export Modal | Frontend | Frontend Dev | RPT-018 | 8h (5 SP) | 5 | To Do |
| RPT-034 | Frontend: Cost rate and capacity management UI | Frontend | Frontend Dev | RPT-025 | 8h (5 SP) | 5 | To Do |
| RPT-035 | Backfill command for cost_rate on existing time entries | Backend | Backend Dev | RPT-011 | 4h (3 SP) | 6 | To Do |
| RPT-036 | OpenAPI specification update and TS client regeneration | Backend / Docs | Backend Dev | RPT-019, RPT-020, RPT-021, RPT-022, RPT-023, RPT-024, RPT-025 | 6h (3 SP) | 6 | To Do |
| RPT-037 | E2E Playwright tests for all new reporting pages | QA / Frontend | QA Dev | RPT-027, RPT-028, RPT-029, RPT-030, RPT-031, RPT-032 | 16h (8 SP) | 6 | To Do |
| RPT-038 | Vitest component tests for new Vue components | QA / Frontend | QA Dev | RPT-027, RPT-028, RPT-029 | 8h (5 SP) | 6 | To Do |
| RPT-039 | Performance optimization and query indexing | Backend / Database | Backend Dev | RPT-014, RPT-015, RPT-024 | 6h (3 SP) | 6 | To Do |

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
**Tasks**: RPT-001 through RPT-013
**Story Points**: 32
**Focus**: All migrations, model updates, enums, CostRateService

**Execution Order**:
1. RPT-001, RPT-002, RPT-003, RPT-004, RPT-005, RPT-006, RPT-007, RPT-008, RPT-012, RPT-013 (all parallel, no dependencies)
2. RPT-009 (depends on RPT-001..005)
3. RPT-010 (depends on RPT-006..008)
4. RPT-011 (depends on RPT-009)

### Sprint 2 (Week 3-4): Core Services
**Tasks**: RPT-014, RPT-015, RPT-016, RPT-017, RPT-018
**Story Points**: 37
**Focus**: All report calculation services, scheduled command, export service

**Execution Order**:
1. RPT-014 and RPT-015 (parallel, both depend on RPT-009/011)
2. RPT-016 (depends on RPT-010, RPT-013)
3. RPT-017 (depends on RPT-016, RPT-014)
4. RPT-018 (depends on RPT-014, RPT-015)

### Sprint 3 (Week 5-6): API Layer
**Tasks**: RPT-019 through RPT-025
**Story Points**: 38
**Focus**: All API endpoints for new features

**Execution Order**:
1. RPT-019, RPT-020, RPT-021, RPT-025 (parallel)
2. RPT-022 (depends on RPT-016)
3. RPT-023 (depends on RPT-010, RPT-012)
4. RPT-024 (depends on RPT-014, RPT-005, RPT-023)

### Sprint 4 (Week 7-8): Frontend Report Pages
**Tasks**: RPT-026 through RPT-031
**Story Points**: 34
**Focus**: All new Vue pages and report UIs

**Execution Order**:
1. RPT-026 (depends on RPT-019, RPT-020, RPT-024)
2. RPT-027, RPT-028, RPT-029, RPT-030, RPT-031 (all parallel after RPT-026)

### Sprint 5 (Week 9-10): Expenses and Enhanced Export
**Tasks**: RPT-032, RPT-033, RPT-034
**Story Points**: 18
**Focus**: Expenses UI, export enhancements, cost rate management UI

**Execution Order**:
1. RPT-032, RPT-033, RPT-034 (all parallel)

### Sprint 6 (Week 11-12): Integration and Testing
**Tasks**: RPT-035, RPT-036, RPT-037, RPT-038, RPT-039
**Story Points**: 22
**Focus**: Backfill, OpenAPI update, E2E tests, component tests, performance tuning

**Execution Order**:
1. RPT-035, RPT-036, RPT-039 (parallel)
2. RPT-037, RPT-038 (parallel, after frontend pages complete)

---

## Dependency Risk Assessment

### High-Risk Dependencies

| Task | Depends On | Risk | Mitigation |
|------|-----------|------|------------|
| RPT-009 | RPT-001..005 | 5 parallel migrations must all complete first. Delay in any blocks model updates. | Migrations are simple column additions; low individual risk. Assign all to same developer for coordination. |
| RPT-014 | RPT-011, RPT-009 | Core profitability service depends on two sequential prerequisites. On the critical path. | Prioritize RPT-001 -> RPT-009 -> RPT-011 in Sprint 1. Start RPT-014 at Sprint 2 day 1. |
| RPT-024 | RPT-014, RPT-005, RPT-023 | Budget report depends on 3 tasks across different sprints. | RPT-005 (migration) completes in Sprint 1. RPT-014 completes in Sprint 2. RPT-023 can be fast-tracked in early Sprint 3. |
| RPT-026 | RPT-019, RPT-020, RPT-024 | Frontend navigation blocked until all 3 API endpoints ready. | APIs are independent of each other; can mock while APIs are being built. Start frontend with mocked data. |

### Tasks With Multiple Dependents (Bottleneck Tasks)

| Task | Depended On By | Impact of Delay |
|------|---------------|-----------------|
| RPT-009 | RPT-011, RPT-014, RPT-015, RPT-025 | Blocks all core services and API updates |
| RPT-014 | RPT-017, RPT-018, RPT-019, RPT-024, RPT-039 | Blocks scheduled reports, export, profitability API, budget API, and performance tuning |
| RPT-010 | RPT-016, RPT-021, RPT-023 | Blocks schedule service, templates API, expenses API |
| RPT-026 | RPT-027, RPT-028, RPT-029 | Blocks all three main report frontend pages |

---

## Notes

- All status values are initialized to "To Do" since no work has started.
- Status transitions: To Do -> In Progress -> Completed (or To Do -> Blocked if external dependency is unavailable).
- No tasks are currently Blocked because all dependencies are internal and all are in "To Do" state.
- The critical path (RPT-001 -> RPT-009 -> RPT-011 -> RPT-014 -> RPT-019 -> RPT-026 -> RPT-027 -> RPT-037) totals approximately 80 hours of sequential work.
