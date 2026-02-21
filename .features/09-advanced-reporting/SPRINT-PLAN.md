# SPRINT-PLAN.md -- Feature 09: Advanced Reporting

**Generated**: 2026-02-06
**Feature Branch**: `feature/advanced-reporting`
**PRD Reference**: `/home/keven/Documents/solidtime-analysis/.features/09-advanced-reporting/PRD.md`
**Architecture Reference**: `/home/keven/Documents/solidtime-analysis/.features/09-advanced-reporting/ARCHITECTURE.md`
**Task Assignments**: `/home/keven/Documents/solidtime-analysis/.features/09-advanced-reporting/task_assignments_20260206.md`
**Shared Foundations**: `/home/keven/Documents/solidtime-analysis/.features/SHARED-FOUNDATIONS.md`

---

## 1. Executive Summary

### Feature Overview

Advanced Reporting extends Solidtime's existing reporting infrastructure with profitability analytics (revenue vs. cost with margin tracking), utilization reporting (actual hours vs. weekly capacity per member), cost rate management (hierarchical cost rates mirroring the existing billable rate pattern), scheduled email report delivery, reusable report templates, expense tracking with receipt uploads, budget vs. actual reporting, and enhanced multi-format export.

### Effort Estimates

| Metric | Value |
|--------|-------|
| **Total Tasks** | 39 feature tasks + prerequisite FOUND tasks |
| **Total Story Points** | ~195 SP |
| **Total Effort** | ~310 hours |
| **Duration** | 12 weeks (6 sprints x 2 weeks each) |
| **Team Size Assumption** | 2 developers (1 backend, 1 frontend/fullstack) + 1 QA (part-time in Sprint 6) |
| **Velocity Assumption** | ~32-38 SP per sprint (2-week sprint, 2 developers) |
| **Story Point Ratio** | 2.0 hours per story point (per SF-10) |

### Phase Structure

The feature is split into two independently valuable delivery phases:

- **Phase A -- Core Analytics (Sprints 1-3, ~140h)**: Cost rate infrastructure, ProfitabilityReportService, UtilizationReportService, API endpoints, and initial API-layer testing. Phase A delivers margin analysis and capacity planning without Phase B.
- **Phase B -- Extended Reporting (Sprints 4-6, ~170h)**: Frontend report pages, scheduled reports, report templates, expense management, budget reports, enhanced export, E2E tests, and performance optimization.

### Prerequisites

Shared Foundation task **FOUND-006** (shared `weekly_capacity` migrations) and **FOUND-007** (modular permissions infrastructure) must be completed before Sprint 1 begins. Other FOUND tasks (FOUND-001 through FOUND-005, notification infrastructure) are not required by this feature.

---

## 2. Sprint Overview Table

| Sprint # | Name | Weeks | Story Points | Effort (hrs) | Key Deliverables |
|----------|------|-------|:------------:|:------------:|-----------------|
| 0 | Shared Foundations (prerequisite) | Pre-sprint | 3 SP | 6h | FOUND-006, FOUND-007 completed |
| 1 | Database Foundation & Models | 1-2 | 32 SP | ~50h | 8 migrations, 3 new models, 5 model updates, CostRateService, 3 enums |
| 2 | Core Services & Backfill | 3-4 | 39 SP | ~76h | ProfitabilityReportService, UtilizationReportService, ReportScheduleService, SendScheduledReportsCommand, ReportExportService, backfill command |
| 3 | API Layer & Early Frontend | 5-6 | 38 SP | ~60h | 7 API endpoint groups (profitability, utilization, templates, schedules, expenses, budget, field updates), OpenAPI spec update |
| 4 | Frontend Report Pages | 7-8 | 34 SP | ~60h | Reporting navigation, Profitability page, Utilization page, Budget page, Templates UI, Schedules UI |
| 5 | Expenses, Export & Settings UI | 9-10 | 18 SP | ~32h | Expenses CRUD page, Enhanced Export modal, Cost rate/capacity management UI |
| 6 | Testing, Performance & Polish | 11-12 | 22 SP | ~40h | E2E Playwright tests, Vitest component tests, query performance optimization, backfill verification |
| **Total** | | **12 weeks** | **~186 SP** | **~324h** | |

Note: Sprint 0 runs in parallel with or before the feature sprints. Its effort is not counted toward the feature total since it is shared infrastructure.

---

## 3. Dependency Map

### 3.1 Shared Foundation Prerequisites

```
FOUND-006 (weekly_capacity migrations, 2h)
  |
  +---> RPT-001 (cost_rate migration on members)
  |       |
  |       +---> RPT-009 (update Eloquent models)
  |               |
  |               +---> RPT-011 (CostRateService)
  |               |       |
  |               |       +---> RPT-014 (ProfitabilityReportService) [Sprint 2]
  |               |       +---> RPT-035 (Backfill command) [Sprint 2]
  |               |
  |               +---> RPT-015 (UtilizationReportService) [Sprint 2]
  |               +---> RPT-025 (Update org/member/project APIs) [Sprint 3]
  |
FOUND-007 (modular permissions, 4h)
  |
  +---> RPT-019..024 (all API endpoints require permission checks)
```

| FOUND Task | Description | Required Before | Effort |
|-----------|-------------|----------------|--------|
| FOUND-006 | Shared `weekly_capacity` migration on members + `default_weekly_capacity` on organizations | RPT-001 (Sprint 1, Day 1) | 2h |
| FOUND-007 | Modular permissions infrastructure (`app/Permissions/`) | RPT-019 (Sprint 3, Day 1) | 4h |

FOUND-001 through FOUND-005 (notification infrastructure) are **not required** for Advanced Reporting. This feature does not emit notifications.

### 3.2 Intra-Feature Dependency Chain (Critical Path)

The critical path determines the minimum elapsed time to deliver the feature. It flows through the following sequential chain:

```
RPT-001 (4h) --> RPT-009 (8h) --> RPT-011 (8h) --> RPT-014 (16h)
  --> RPT-019 (8h) --> RPT-026 (4h) --> RPT-027 (16h) --> RPT-037 (16h)

Total critical path: ~80 hours of sequential work
```

### 3.3 Full Dependency Graph

```
Sprint 1:
  RPT-001..008 (parallel, no deps)  ────> RPT-009 (depends on 001..005)
                                     ────> RPT-010 (depends on 006..008)
  RPT-012, RPT-013 (parallel, no deps)
  RPT-009 ────> RPT-011 (CostRateService)

Sprint 2:
  RPT-011 + RPT-009 ────> RPT-014 (ProfitabilityReportService)
  RPT-009 ────> RPT-015 (UtilizationReportService)
  RPT-010 + RPT-013 ────> RPT-016 (ReportScheduleService)
  RPT-016 + RPT-014 ────> RPT-017 (SendScheduledReportsCommand)
  RPT-014 + RPT-015 ────> RPT-018 (ReportExportService)
  RPT-011 ────> RPT-035 (Backfill command, moved to Sprint 2 per AMD-07)

Sprint 3:
  RPT-014 ────> RPT-019 (Profitability API)
  RPT-015 ────> RPT-020 (Utilization API)
  RPT-010 ────> RPT-021 (Templates CRUD API)
  RPT-016 ────> RPT-022 (Schedules CRUD API)
  RPT-010 + RPT-012 ────> RPT-023 (Expenses CRUD API)
  RPT-014 + RPT-005 + RPT-023 ────> RPT-024 (Budget API)
  RPT-009 ────> RPT-025 (Update existing APIs)
  RPT-019..025 ────> RPT-036 (OpenAPI spec update)

Sprint 4:
  RPT-019 + RPT-020 + RPT-024 ────> RPT-026 (Nav updates)
  RPT-026 ────> RPT-027 (Profitability page)
  RPT-026 ────> RPT-028 (Utilization page)
  RPT-026 ────> RPT-029 (Budget page)
  RPT-021 ────> RPT-030 (Templates UI)
  RPT-022 ────> RPT-031 (Schedules UI)

Sprint 5:
  RPT-023 ────> RPT-032 (Expenses page)
  RPT-018 ────> RPT-033 (Export modal)
  RPT-025 ────> RPT-034 (Cost rate management UI)

Sprint 6:
  RPT-027..032 ────> RPT-037 (E2E tests)
  RPT-027..029 ────> RPT-038 (Component tests)
  RPT-014 + RPT-015 + RPT-024 ────> RPT-039 (Performance optimization)
```

### 3.4 External Feature Dependencies (Soft)

These are soft dependencies. The feature works without them; it is enhanced when they ship.

| External Feature | Dependency Type | Impact |
|-----------------|----------------|--------|
| Feature 02: Expense Management | Soft | If Feature 02 deploys first, RPT-008 migration becomes a no-op (expenses table already exists). Expense reports gain richer data. |
| Feature 03: Budgets & Alerts | Soft | If Feature 03 deploys first, RPT-005 migration becomes a no-op (budget_amount already exists). Budget reports gain alerts integration. |
| Feature 10: Teams & Groups | Soft | When team scoping is enabled, all report endpoints auto-filter by team membership. Report endpoints accept optional `team_ids` filter. |

---

## 4. Sprint Details

---

### Sprint 0: Shared Foundations (Prerequisite)

**Sprint Goal**: Complete the shared infrastructure tasks that Advanced Reporting depends on.

**Duration**: Completed before Sprint 1 begins (may overlap with other features' Sprint 0 work).

| Task ID | Description | Effort | SP | Dependencies | Assignee Role |
|---------|-------------|:------:|:--:|:------------:|---------------|
| FOUND-006 | Create shared `weekly_capacity` migration on members + `default_weekly_capacity` on organizations | 2h | 1 | None | Backend |
| FOUND-007 | Create modular permissions infrastructure (`app/Permissions/` directory, refactor existing permissions) | 4h | 2 | None | Backend |

**Acceptance Criteria**:
- `members.weekly_capacity` column exists with default 144000 (40 hours in seconds)
- `organizations.default_weekly_capacity` column exists with default 144000
- `app/Permissions/` directory created with base pattern
- Existing permissions refactored into modular files
- `composer analyse` passes
- All existing tests still pass

**Deliverables**:
- `database/migrations/2026_02_28_000001_add_weekly_capacity_to_members.php`
- `database/migrations/2026_02_28_000002_add_default_weekly_capacity_to_organizations.php`
- `app/Permissions/ReportingPermissions.php` (skeleton)

**Risk Factors**:
- Other features (PRD 08: Resource Scheduling) also depend on FOUND-006. Coordinate timing.
- FOUND-007 refactors existing permissions. Requires careful merge to avoid breaking existing role checks.

---

### Sprint 1: Database Foundation & Models (Weeks 1-2)

**Sprint Goal**: Establish the complete database schema, update all Eloquent models, create new models and enums, and implement the CostRateService.

| Task ID | Description | Effort | SP | Dependencies | Assignee Role |
|---------|-------------|:------:|:--:|:------------:|---------------|
| RPT-001 | Migration: Add `cost_rate` to members table | 4h | 3 | FOUND-006 | Backend |
| RPT-002 | Migration: Add `cost_rate` to project_members table | 2h | 2 | None | Backend |
| RPT-003 | Migration: Add `default_cost_rate` to organizations table | 2h | 2 | None | Backend |
| RPT-004 | Migration: Add `cost_rate` to time_entries table (with index) | 2h | 2 | None | Backend |
| RPT-005 | Migration: Add `budget_amount` to projects table | 2h | 2 | None | Backend |
| RPT-006 | Migration: Create `report_templates` table | 4h | 3 | None | Backend |
| RPT-007 | Migration: Create `report_schedules` table | 4h | 3 | None | Backend |
| RPT-008 | Migration: Create `expenses` table (with idempotent check for Feature 02) | 4h | 3 | None | Backend |
| RPT-009 | Update existing Eloquent models (Member, ProjectMember, Organization, TimeEntry, Project) | 8h | 5 | RPT-001..005 | Backend |
| RPT-010 | Create new Eloquent models (ReportTemplate, ReportSchedule, Expense) + factories | 8h | 5 | RPT-006..008 | Backend |
| RPT-011 | Create CostRateService (mirrors BillableRateService) | 8h | 5 | RPT-009 | Backend |
| RPT-012 | Create ExpenseCategory enum | 1h | 1 | None | Backend |
| RPT-013 | Create ReportScheduleFrequency and ReportScheduleStatus enums | 1h | 1 | None | Backend |
| | **Sprint 1 Total** | **50h** | **37** | | |

**Execution Order**:

```
Day 1-2:  RPT-001, 002, 003, 004, 005, 006, 007, 008, 012, 013
          (All parallel -- migrations and enums have no internal deps)

Day 3-5:  RPT-009 (depends on 001..005)
          RPT-010 (depends on 006..008)
          (These two can run in parallel)

Day 6-10: RPT-011 (depends on 009)
          (Write CostRateService + full unit test suite)
```

**Acceptance Criteria**:
- All 8 migrations run and roll back cleanly on a fresh database
- RPT-001 migration verifies FOUND-006 ran first (schema check)
- RPT-008 migration is idempotent (skips if `expenses` table already exists from Feature 02)
- All model `$casts`, PHPDoc `@property` annotations, and relationships are correct
- `TimeEntry::$computed` includes `cost_rate` with `getCostRateComputed()` method
- CostRateService hierarchy: ProjectMember > Member > Organization > null
- CostRateService cascade updates work correctly when rates change
- All 3 factories produce valid model instances
- `composer analyse` passes with zero errors
- CostRateService has 7+ unit test scenarios covering all hierarchy levels and edge cases

**Deliverables**:
- 8 migration files in `database/migrations/2026_03_09_*`
- 5 modified model files (`Member.php`, `ProjectMember.php`, `Organization.php`, `TimeEntry.php`, `Project.php`)
- 3 new model files (`ReportTemplate.php`, `ReportSchedule.php`, `Expense.php`)
- 3 factory files (`ReportTemplateFactory.php`, `ReportScheduleFactory.php`, `ExpenseFactory.php`)
- 3 enum files (`ExpenseCategory.php`, `ReportScheduleFrequency.php`, `ReportScheduleStatus.php`)
- `app/Service/CostRateService.php`
- `tests/Unit/Service/CostRateServiceTest.php`

**Risk Factors**:
- **FOUND-006 not complete**: If `weekly_capacity` migration has not run, RPT-001 will fail. Mitigation: Sprint 0 must be verified complete before Sprint 1 begins.
- **Feature 02 conflict on expenses table**: If Feature 02 ships first and creates `expenses` with a different schema, RPT-008 skip logic must handle this gracefully. Mitigation: Expenses table schema is defined in SHARED-FOUNDATIONS and both features conform to it.
- **Model change merge conflicts**: 5 existing models are modified. If other features also modify these models concurrently, merges will be needed. Mitigation: Changes are additive (new columns/casts only).

---

### Sprint 2: Core Services & Backfill (Weeks 3-4)

**Sprint Goal**: Implement all business logic services for profitability, utilization, scheduling, export, and backfill existing time entries with cost rates.

| Task ID | Description | Effort | SP | Dependencies | Assignee Role |
|---------|-------------|:------:|:--:|:------------:|---------------|
| RPT-014 | Create ProfitabilityReportService (SQL aggregation, grouping, margin calc) | 16h | 8 | RPT-011, RPT-009 | Backend |
| RPT-015 | Create UtilizationReportService (capacity calc, daily breakdown) | 16h | 8 | RPT-009 | Backend |
| RPT-016 | Create ReportScheduleService (next-run calc, lifecycle management) | 16h | 8 | RPT-010, RPT-013 | Backend |
| RPT-017 | Create SendScheduledReportsCommand + ScheduledReportMail | 8h | 5 | RPT-016, RPT-014 | Backend |
| RPT-018 | Create ReportExportService (enhanced export with custom columns) | 16h | 8 | RPT-014, RPT-015 | Backend |
| RPT-035 | Backfill command for `cost_rate` on existing time entries (moved from Sprint 6 per AMD-07) | 4h | 2 | RPT-011 | Backend |
| | **Sprint 2 Total** | **76h** | **39** | | |

**Execution Order**:

```
Day 1-3:  RPT-014 (ProfitabilityReportService)
          RPT-015 (UtilizationReportService)
          RPT-035 (Backfill command)
          (014 and 015 can run in parallel; 035 depends only on 011 from Sprint 1)

Day 4-6:  RPT-016 (ReportScheduleService)
          (Can start Day 1 if separate dev, depends on 010+013 from Sprint 1)

Day 7-8:  RPT-017 (SendScheduledReportsCommand)
          (Depends on 016 and 014)

Day 9-10: RPT-018 (ReportExportService)
          (Depends on 014 and 015)
```

**Acceptance Criteria**:
- ProfitabilityReportService returns `total_seconds`, `revenue`, `cost`, `margin`, `margin_percent` per group
- Revenue calculated only from billable entries; cost from all entries
- Zero revenue case: `margin_percent` is null (not divide-by-zero)
- Supports all `TimeEntryAggregationType` groupings (Client, Project, User, Month, Week, Day)
- Performance: profitability report < 500ms for 100K time entries
- UtilizationReportService returns per-member capacity, actual, billable, utilization %, billable utilization %
- Capacity pro-rated for partial weeks
- Zero capacity: utilization is null
- Performance: utilization report < 300ms for 500 members
- ReportScheduleService calculates correct `next_run_at` for daily/weekly/monthly in any timezone
- Monthly schedule on day 31 falls back to last day of short months
- Failed schedules paused after 3 consecutive failures
- SendScheduledReportsCommand uses `withoutOverlapping()` lock
- SendScheduledReportsCommand is config-gated: `config('scheduling.tasks.send_scheduled_reports')`
- ReportExportService supports custom column selection and all 4 formats (CSV, XLSX, PDF, ODS)
- Export completes within 30 seconds for 50K rows
- Backfill command processes entries in chunks (default 1000)
- Backfill command has `--chunk` option for tuning
- All services have comprehensive unit test suites (10+ scenarios each)
- `composer analyse` passes

**Deliverables**:
- `app/Service/ProfitabilityReportService.php` + `tests/Unit/Service/ProfitabilityReportServiceTest.php`
- `app/Service/UtilizationReportService.php` + `tests/Unit/Service/UtilizationReportServiceTest.php`
- `app/Service/ReportScheduleService.php` + `tests/Unit/Service/ReportScheduleServiceTest.php`
- `app/Service/ReportExportService.php` + `tests/Unit/Service/ReportExportServiceTest.php`
- `app/Service/Export/ProfitabilityReportExport.php`
- `app/Service/Export/UtilizationReportExport.php`
- `app/Service/Export/DetailedReportExport.php`
- `app/Console/Commands/SendScheduledReportsCommand.php` + `tests/Unit/Console/SendScheduledReportsCommandTest.php`
- `app/Console/Commands/BackfillCostRates.php`
- `app/Mail/ScheduledReportMail.php`
- `resources/views/emails/scheduled-report.blade.php`

**Risk Factors**:
- **Sprint 2 is the highest-effort sprint (76h, 39 SP)**. If the team velocity is lower than estimated, RPT-018 (ReportExportService) can be deferred to Sprint 3 as it is not on the critical path for the frontend.
- **SQL aggregation complexity**: ProfitabilityReportService extends the existing `TimeEntryAggregationService` pattern with cost rate calculations. The tag cross-join (LATERAL) could cause performance issues. Mitigation: Test with realistic dataset sizes early; add composite index on `(organization_id, start, end, project_id, billable, cost_rate, billable_rate)` in RPT-039.
- **Timezone edge cases in schedule calculation**: DST transitions and month-end fallback logic require thorough testing. Mitigation: Unit tests cover at least 3 timezone scenarios (UTC, US/Eastern with DST, Asia/Kolkata half-hour offset).

---

### Sprint 3: API Layer & OpenAPI Update (Weeks 5-6)

**Sprint Goal**: Expose all backend services through validated, tested, rate-limited API endpoints, and update the OpenAPI specification.

| Task ID | Description | Effort | SP | Dependencies | Assignee Role |
|---------|-------------|:------:|:--:|:------------:|---------------|
| RPT-019 | Profitability Report API endpoint (controller, request, resource, route, endpoint test) | 8h | 5 | RPT-014 | Backend |
| RPT-020 | Utilization Report API endpoint | 8h | 5 | RPT-015 | Backend |
| RPT-021 | Report Templates CRUD API (5 endpoints) | 8h | 5 | RPT-010 | Backend |
| RPT-022 | Report Schedules CRUD API (4 endpoints, nested under reports) | 8h | 5 | RPT-016 | Backend |
| RPT-023 | Expenses CRUD API (6 endpoints, including receipt upload/download) | 12h | 8 | RPT-010, RPT-012 | Backend |
| RPT-024 | Budget Report API endpoint | 8h | 5 | RPT-014, RPT-005, RPT-023 | Backend |
| RPT-025 | Update Organization/Member/Project API endpoints for new fields (cost_rate, weekly_capacity, budget_amount) | 8h | 5 | RPT-009 | Backend |
| RPT-036 | OpenAPI specification update and TypeScript client regeneration | 6h | 3 | RPT-019..025 | Backend |
| | **Sprint 3 Total** | **66h** | **41** | | |

**Execution Order**:

```
Day 1-3:  RPT-019, RPT-020, RPT-021, RPT-025 (all parallel -- each depends on a different Sprint 2 service)

Day 4-5:  RPT-022 (depends on RPT-016 from Sprint 2)
          RPT-023 (depends on RPT-010, RPT-012 from Sprint 1)

Day 6-8:  RPT-024 (depends on RPT-014, RPT-005, RPT-023)
          (Budget API is last because it depends on Expenses API being available)

Day 9-10: RPT-036 (OpenAPI spec update -- requires all endpoints to be finalized)
```

**Acceptance Criteria**:
- All endpoints require Passport authentication and organization membership
- Profitability and utilization endpoints: `reports:view` permission required
- Profitability and utilization endpoints: rate limited to 10 requests/minute per organization (AMD-11)
- Cost/margin data hidden from Employee role in profitability response
- Templates CRUD: `reports:create`/`view`/`update`/`delete` permissions
- Schedules CRUD: `reports:create` permission; max 5 schedules per report enforced
- Expenses CRUD: `expenses:create`, `expenses:view:own`/`expenses:view:all` permissions
- Receipt upload: max 10MB, accepts PDF/PNG/JPG
- Receipt download: requires `expenses:view:all` permission; returns file from private storage
- Budget report: only projects with `budget_amount` or `estimated_time` shown
- Updated API endpoints expose `cost_rate`, `weekly_capacity`, `default_cost_rate`, `budget_amount` fields
- OpenAPI spec reflects all new endpoints with request/response schemas
- TypeScript client regenerated and compiles without errors
- Each endpoint has a dedicated test file with: permission denied, valid request, empty result, edge case scenarios
- `composer analyse` passes
- `npm run type-check` passes after TS client regeneration

**Deliverables**:
- 6 controller files in `app/Http/Controllers/Api/V1/`
- ~10 request validation files in `app/Http/Requests/V1/`
- ~9 resource files in `app/Http/Resources/V1/`
- `app/Permissions/ReportingPermissions.php` (finalized with all permissions)
- `routes/api.php` (updated with all new route groups)
- 6 endpoint test files in `tests/Unit/Endpoint/Api/V1/`
- Updated OpenAPI spec
- Regenerated TypeScript client
- `resources/js/types/reporting.d.ts`

**Risk Factors**:
- **Sprint 3 is the second-highest effort sprint (66h, 41 SP)**. If Sprint 2 services are delayed, API endpoints cannot be fully tested. Mitigation: API controllers can be scaffolded with mocked service responses while waiting for service completion.
- **RPT-024 (Budget API) has a 3-task dependency chain spanning Sprints 1-3**. If RPT-023 (Expenses API) slips, RPT-024 is blocked. Mitigation: Budget report can initially exclude expense integration and add it later.
- **OpenAPI spec update (RPT-036) is a bottleneck for frontend Sprint 4**. If deferred, frontend must mock API responses. Mitigation: Schedule RPT-036 no later than Sprint 3 Day 9 to give frontend a full day of buffer.

---

### Sprint 4: Frontend Report Pages (Weeks 7-8)

**Sprint Goal**: Build all primary report viewing pages and management UIs, connecting to the Sprint 3 API layer.

| Task ID | Description | Effort | SP | Dependencies | Assignee Role |
|---------|-------------|:------:|:--:|:------------:|---------------|
| RPT-026 | Reporting navigation and tab updates (add Profitability, Utilization, Budget tabs) | 4h | 3 | RPT-019, RPT-020, RPT-024 | Frontend |
| RPT-027 | Profitability Report page (filters, summary cards, bar chart, line chart, data table) | 16h | 8 | RPT-026 | Frontend |
| RPT-028 | Utilization Report page (filters, horizontal bar chart, data table, daily breakdown) | 16h | 8 | RPT-026 | Frontend |
| RPT-029 | Budget Report page (project table, progress bars, warning indicators) | 8h | 5 | RPT-026 | Frontend |
| RPT-030 | Report Templates UI (template list, create/edit modal, template picker in report builder) | 8h | 5 | RPT-021 | Frontend |
| RPT-031 | Report Schedule Management UI (schedule modal, status display, pause/resume controls) | 8h | 5 | RPT-022 | Frontend |
| | **Sprint 4 Total** | **60h** | **34** | | |

**Execution Order**:

```
Day 1-2:  RPT-026 (Navigation updates -- gates all other frontend work)
          RPT-030 (Templates UI -- independent of navigation, depends on RPT-021 API)
          RPT-031 (Schedules UI -- independent of navigation, depends on RPT-022 API)

Day 3-7:  RPT-027 (Profitability page -- largest frontend task, includes charts)
          RPT-028 (Utilization page -- parallel with 027 if two developers)

Day 8-10: RPT-029 (Budget page)
```

**Acceptance Criteria**:
- "Profitability", "Utilization", and "Budget" tabs appear in Reporting navigation for users with `reports:view` permission
- Profitability page: filter by date range, members, clients, projects, billable status; displays summary cards (Hours, Revenue, Cost, Margin), stacked bar chart, margin trend line, and grouped data table; negative margins highlighted in red
- Utilization page: filter by date range and members; horizontal bar chart per member; over-utilized members (>100%) shown with distinct indicator; daily/weekly breakdown available
- Budget page: per-project rows with time and money budget vs. actual; progress bar visualization; projects exceeding budget shown with warning color
- Templates UI: "Save as Template" and "Load Template" work end-to-end; template CRUD fully functional
- Schedules UI: schedule modal with frequency/time/format/recipients; pause/resume/delete; last delivery status and next run displayed
- All pages use the organization's currency format settings
- Empty states shown when no data matches filters
- Loading states shown during API calls
- Error states shown on API failure
- Responsive layout for desktop and tablet (min 768px width)

**Deliverables**:
- `resources/js/Pages/Reporting/ProfitabilityReport.vue`
- `resources/js/Pages/Reporting/UtilizationReport.vue`
- `resources/js/Pages/Reporting/BudgetReport.vue`
- `resources/js/Pages/Reporting/ReportTemplates.vue`
- `resources/js/packages/ui/src/Reporting/ReportFilters.vue`
- `resources/js/packages/ui/src/Reporting/SummaryCard.vue`
- `resources/js/packages/ui/src/Reporting/ProfitabilityTable.vue`
- `resources/js/packages/ui/src/Reporting/UtilizationTable.vue`
- `resources/js/packages/ui/src/Reporting/BudgetTable.vue`
- `resources/js/packages/ui/src/Reporting/BarChart.vue`
- `resources/js/packages/ui/src/Reporting/LineChart.vue`
- `resources/js/packages/ui/src/Reporting/HeatmapCalendar.vue`
- `resources/js/packages/ui/src/Reporting/ReportTemplateModal.vue`
- `resources/js/packages/ui/src/Reporting/ReportScheduleModal.vue`
- `resources/js/utils/useReporting.ts` (Pinia store)
- Updated `resources/js/Layouts/AppLayout.vue` (navigation)

**Risk Factors**:
- **Frontend bottleneck (AMD-10)**: 6 frontend tasks totaling 60h in one sprint. Two parallel frontend developers needed, or backend developer assists with simpler table components. Mitigation: RPT-030 and RPT-031 (Templates/Schedules UI) are modal-based and simpler; a fullstack developer can handle these while the dedicated frontend developer tackles RPT-027 and RPT-028.
- **Chart library integration**: The codebase uses `vue-echarts` (Apache ECharts) for dashboard charts but the architecture spec references Chart.js. Mitigation: Verify which library to use during Sprint 4 Day 1; prefer ECharts for consistency with existing dashboard components.
- **TypeScript client not ready**: If RPT-036 (OpenAPI update) slipped from Sprint 3, frontend must mock API types. Mitigation: Define TypeScript interfaces in `reporting.d.ts` manually as fallback.

---

### Sprint 5: Expenses, Export & Settings UI (Weeks 9-10)

**Sprint Goal**: Complete the remaining frontend pages for expenses, enhanced export, and cost rate/capacity management settings.

| Task ID | Description | Effort | SP | Dependencies | Assignee Role |
|---------|-------------|:------:|:--:|:------------:|---------------|
| RPT-032 | Expenses page and CRUD (list, create/edit form, receipt upload, filtering) | 16h | 8 | RPT-023 | Frontend |
| RPT-033 | Enhanced Export Modal (custom column checkboxes, format selector, grouped export) | 8h | 5 | RPT-018 | Frontend |
| RPT-034 | Cost rate and capacity management UI (in organization settings, member settings, project member settings) | 8h | 5 | RPT-025 | Frontend |
| | **Sprint 5 Total** | **32h** | **18** | | |

**Execution Order**:

```
Day 1-7:  RPT-032 (Expenses page -- largest task, includes receipt upload)

Day 1-5:  RPT-033 (Export modal -- parallel with 032)
          RPT-034 (Cost rate management -- parallel with 032)
          (All three tasks are independent and can run fully in parallel)

Day 8-10: Buffer / bug fixes from Sprint 4 pages / integration testing
```

**Acceptance Criteria**:
- Expenses page: CRUD interface with amount, category, date, description, project, billable flag; receipt upload (PDF/PNG/JPG, max 10MB); receipt preview/download; expense list with filtering by date range, project, category, member; export expenses as CSV/XLSX
- Enhanced export modal: checkboxes for all 15 available columns; column selection saved in session storage; grouped exports include subtotal rows; PDF exports have styled headers, alternating row colors, page numbers
- Cost rate management: organization `default_cost_rate` editable in org settings; member `cost_rate` editable in member profile (visible to Admin/Owner/Manager only); project member `cost_rate` override editable in project settings; `weekly_capacity` editable per member; Employee role cannot see cost rate fields
- Validation: negative cost rate values rejected; `weekly_capacity` accepts positive integers only

**Deliverables**:
- `resources/js/Pages/Reporting/Expenses.vue`
- `resources/js/packages/ui/src/Reporting/ExpenseForm.vue`
- `resources/js/packages/ui/src/Reporting/ExpenseTable.vue`
- `resources/js/packages/ui/src/Reporting/ReceiptUpload.vue`
- `resources/js/packages/ui/src/Reporting/ExportModal.vue`
- Updated organization settings page (cost rate fields)
- Updated member settings page (cost rate, weekly capacity fields)
- Updated project member settings (cost rate override)

**Risk Factors**:
- **Sprint 5 has lower effort (32h, 18 SP)** compared to other sprints. This is intentional to provide buffer for Sprint 4 overflow or integration issues. If Sprint 4 goes smoothly, Sprint 5 can absorb additional polish work.
- **Receipt upload complexity**: File upload in Vue with progress indicator, preview, and private storage retrieval requires careful implementation. Mitigation: Follow existing file upload patterns in the codebase (if any) or use a well-tested Vue file upload component.

---

### Sprint 6: Testing, Performance & Polish (Weeks 11-12)

**Sprint Goal**: Achieve comprehensive test coverage, optimize query performance, and resolve any remaining integration issues.

| Task ID | Description | Effort | SP | Dependencies | Assignee Role |
|---------|-------------|:------:|:--:|:------------:|---------------|
| RPT-037 | E2E Playwright tests for all new reporting pages (profitability, utilization, budget, templates, schedules, expenses) | 16h | 8 | RPT-027..032 | QA/Frontend |
| RPT-038 | Vitest component tests for new Vue components (tables, charts, filters, forms) | 8h | 5 | RPT-027..029 | QA/Frontend |
| RPT-039 | Performance optimization and query indexing (composite indexes, query analysis, EXPLAIN plans) | 6h | 3 | RPT-014, RPT-015, RPT-024 | Backend |
| | **Sprint 6 Total** | **30h** | **16** | | |

Note: RPT-035 (backfill command) and RPT-036 (OpenAPI update) were moved to earlier sprints per AMD-07 and to unblock frontend.

**Execution Order**:

```
Day 1-2:  RPT-039 (Performance optimization -- backend, independent)

Day 1-7:  RPT-037 (E2E tests -- QA/frontend, runs for most of the sprint)

Day 3-6:  RPT-038 (Component tests -- parallel with E2E tests)

Day 8-10: Integration testing, regression testing, bug fixes
```

**Acceptance Criteria**:
- E2E tests cover: navigation to each report page, filter application, data rendering, export trigger, template create/load/delete, schedule create/edit/delete, expense CRUD with receipt upload
- E2E tests run in CI pipeline with seeded test data
- Component tests cover: ProfitabilityTable, UtilizationTable, BudgetTable, BarChart, ReportFilters, ExpenseForm, SummaryCard
- Component tests verify: data rendering, empty states, error states, user interaction
- Performance: Profitability report p95 < 500ms for 100K time entries (measured with EXPLAIN ANALYZE)
- Performance: Utilization report p95 < 300ms for 500 members
- Performance: Budget report p95 < 300ms
- Composite index added: `(organization_id, start, end, project_id, billable, cost_rate, billable_rate)` on `time_entries`
- All tests (PHP unit + endpoint, Vitest, Playwright) pass in CI
- `composer fix && composer analyse` clean
- `npm run lint:fix && npm run format` clean

**Deliverables**:
- 5 E2E test files in `e2e/` (profitability, utilization, budget, templates, expenses)
- 7 component test files in `resources/js/packages/ui/src/Reporting/__tests__/`
- Performance index migration: `2026_03_09_000009_add_reporting_performance_indexes.php`
- Performance benchmark results documented

**Risk Factors**:
- **E2E test environment setup**: Playwright tests require a running application with seeded data. If Docker/test environment is not properly configured, E2E tests may be flaky. Mitigation: Use the existing E2E setup from the timesheet feature (`e2e/` directory already exists with Playwright config).
- **Performance issues discovered late**: If profitability queries are slow with large datasets, index optimization may not be sufficient. Mitigation: Consider materialized views for monthly rollups as a Phase B+ optimization (out of scope for this sprint, but document as future work).

---

## 5. Testing Strategy Per Sprint

### Test Coverage Timeline

| Sprint | Unit Tests | Endpoint Tests | Component Tests | E2E Tests | Integration Testing |
|--------|-----------|---------------|----------------|----------|-------------------|
| 1 | CostRateServiceTest (7+ scenarios) | -- | -- | -- | Manual: verify migrations run |
| 2 | ProfitabilityReportServiceTest (10+), UtilizationReportServiceTest (7+), ReportScheduleServiceTest (8+), ReportExportServiceTest (6+), SendScheduledReportsCommandTest (5+) | -- | -- | -- | Manual: run backfill on test data |
| 3 | -- | ProfitabilityReportEndpointTest, UtilizationReportEndpointTest, BudgetReportEndpointTest, ReportTemplateEndpointTest, ReportScheduleEndpointTest, ExpenseEndpointTest (5+ scenarios each) | -- | -- | API-level integration: full request/response cycle |
| 4 | -- | -- | -- | -- | Manual: click-through all pages, verify data flow |
| 5 | -- | -- | -- | -- | Manual: expenses workflow, export workflow |
| 6 | -- | -- | Vitest: 7 component test files (RPT-038) | Playwright: 5 E2E test files (RPT-037) | Full regression: all features end-to-end |

### Test Counts Per Sprint

| Sprint | New Test Files | Estimated Test Scenarios |
|--------|:-------------:|:------------------------:|
| 1 | 1 | ~10 |
| 2 | 5 | ~40 |
| 3 | 6 | ~35 |
| 4 | 0 | 0 (manual testing) |
| 5 | 0 | 0 (manual testing) |
| 6 | 12 | ~50 |
| **Total** | **24** | **~135** |

### Testing Principles

1. **Service tests in the same sprint as the service**: Every service (RPT-011, RPT-014..018) ships with its unit test suite in the same sprint. No service is "done" without tests.
2. **Endpoint tests in the same sprint as the endpoint**: Every API controller (RPT-019..025) ships with its endpoint test in Sprint 3.
3. **Frontend tests are deferred to Sprint 6**: Component tests (Vitest) and E2E tests (Playwright) run after all pages are implemented. This avoids writing tests against unstable UI during rapid iteration.
4. **Performance testing in Sprint 6**: SQL EXPLAIN ANALYZE benchmarks run against realistic dataset sizes (100K entries, 500 members) in a staging environment.

---

## 6. Definition of Done

### 6.1 Per-Task DoD Checklist

- [ ] Code implements all acceptance criteria listed in the task description
- [ ] `declare(strict_types=1)` at top of every PHP file
- [ ] PHPDoc `@property` annotations on all models
- [ ] `$casts` array entries for all new/modified columns
- [ ] `composer fix` (PHP CS Fixer) passes with zero changes needed
- [ ] `composer analyse` (PHPStan/Larastan) passes at configured level
- [ ] `npm run lint:fix && npm run format` passes for any JS/TS/Vue changes
- [ ] Unit tests written and passing (for services)
- [ ] Endpoint tests written and passing (for controllers)
- [ ] No new PHPStan errors introduced
- [ ] No new ESLint errors introduced
- [ ] PR submitted with clear description referencing task ID

### 6.2 Per-Sprint DoD Checklist

- [ ] All sprint tasks completed to Per-Task DoD standard
- [ ] Full test suite passes (`composer test`, `npm run test`)
- [ ] No regressions in existing functionality
- [ ] Sprint demo conducted (show working features to stakeholders)
- [ ] Known issues documented with severity and sprint-to-fix assignment
- [ ] Code merged to feature branch (`feature/advanced-reporting`)
- [ ] Sprint retrospective notes captured

### 6.3 Feature-Level DoD Checklist

- [ ] All 39 tasks (RPT-001 through RPT-039) completed
- [ ] All shared foundation dependencies (FOUND-006, FOUND-007) satisfied
- [ ] OpenAPI specification updated and TypeScript client regenerated
- [ ] Full PHP test suite passes (unit + endpoint tests)
- [ ] Vitest component tests pass (7 files)
- [ ] Playwright E2E tests pass (5 files)
- [ ] Performance benchmarks met:
  - Profitability report p95 < 500ms (100K entries)
  - Utilization report p95 < 300ms (500 members)
  - Budget report p95 < 300ms
  - Export generation < 30 seconds (50K rows)
- [ ] `composer fix && composer analyse` clean
- [ ] `npm run lint:fix && npm run format` clean
- [ ] All permissions registered via `ReportingPermissions::register()`
- [ ] Employee role cannot access reports or see cost rate data
- [ ] Receipt uploads stored in private storage, served via download endpoint
- [ ] Scheduled reports config-gated via `config('scheduling.tasks.send_scheduled_reports')`
- [ ] Feature branch rebased on main and merge conflicts resolved
- [ ] Product owner sign-off on all user stories (USR-001 through USR-008)

---

## 7. Risk Register

### 7.1 Technical Risks

| Risk ID | Risk | Probability | Impact | Severity | Mitigation Strategy |
|---------|------|:-----------:|:------:|:--------:|-------------------|
| TR-01 | Profitability SQL aggregation is slow (>500ms) for large orgs | Medium | High | **High** | Add composite index on `(organization_id, start, end, project_id, billable, cost_rate, billable_rate)` in RPT-039. Test with 100K+ entries in Sprint 2. Consider materialized views as fallback. |
| TR-02 | Tag cross-join (LATERAL) causes row explosion in profitability queries | Medium | Medium | **Medium** | Tag-based grouping in profitability reports is lower priority. If problematic, exclude tag grouping from profitability (support it only in the existing aggregation service). |
| TR-03 | Cost rate backfill (RPT-035) is slow for large datasets (500K+ entries) | Medium | Low | **Low** | Use chunked processing with `--chunk` option. Run as background job. Add `--organization` flag to process one org at a time. |
| TR-04 | Chart library inconsistency (ECharts vs Chart.js) | Low | Medium | **Low** | Verify in Sprint 4 Day 1 which library the existing dashboard uses. Codebase analysis shows `vue-echarts`. Use ECharts for consistency. |
| TR-05 | PDF export (Gotenberg/mPDF) styling is difficult to get right | Medium | Low | **Low** | PDF styling is P2 priority. Ship basic PDF export first; add styled headers/alternating rows as polish in Sprint 6. |
| TR-06 | Scheduled report email delivery fails silently | Low | High | **Medium** | Implement 3-retry with exponential backoff. Log all failures. Auto-pause after 3 consecutive failures. Consider notification to admin on schedule failure. |

### 7.2 Dependency Risks

| Risk ID | Risk | Probability | Impact | Severity | Mitigation Strategy |
|---------|------|:-----------:|:------:|:--------:|-------------------|
| DR-01 | FOUND-006 not completed before Sprint 1 | Low | High | **Medium** | Track FOUND-006 completion in Sprint 0 checklist. Block Sprint 1 kickoff until verified. Fallback: temporarily add `weekly_capacity` in RPT-001 migration and remove when FOUND-006 ships. |
| DR-02 | Feature 02 (Expenses) creates incompatible expenses table schema | Low | Medium | **Low** | Both features reference the SHARED-FOUNDATIONS schema. RPT-008 migration includes `Schema::hasTable('expenses')` check. |
| DR-03 | Feature 03 (Budgets) creates incompatible budget_amount column | Low | Medium | **Low** | RPT-005 migration includes `Schema::hasColumn('projects', 'budget_amount')` check. |
| DR-04 | Concurrent model modifications from other features cause merge conflicts | Medium | Medium | **Medium** | All changes are additive (new columns, new casts). Use feature branches with regular rebasing. Schedule merge windows between feature teams. |
| DR-05 | OpenAPI spec update (RPT-036) conflicts with other features' spec changes | Medium | Low | **Low** | Regenerate spec after all Sprint 3 endpoints are finalized. Coordinate spec update timing with other feature teams. |

### 7.3 Capacity Risks

| Risk ID | Risk | Probability | Impact | Severity | Mitigation Strategy |
|---------|------|:-----------:|:------:|:--------:|-------------------|
| CR-01 | Sprint 2 exceeds capacity (76h/39 SP is highest) | Medium | Medium | **Medium** | RPT-018 (ReportExportService) can be deferred to early Sprint 3 without blocking the critical path. This reduces Sprint 2 to 60h/31 SP. |
| CR-02 | Sprint 3 also high effort (66h/41 SP) | Medium | Medium | **Medium** | If RPT-018 moves to Sprint 3, move RPT-036 (OpenAPI, 6h) to Sprint 4 Day 1 as buffer. Frontend can start with manual TypeScript types. |
| CR-03 | Frontend bottleneck in Sprint 4 (6 tasks, all frontend) | High | Medium | **High** | AMD-10 mitigation: backend developer assists with simpler frontend tasks (RPT-030 Templates UI, RPT-031 Schedules UI) while frontend developer handles report pages. |
| CR-04 | QA availability in Sprint 6 | Medium | Medium | **Medium** | E2E tests (RPT-037) and component tests (RPT-038) can be written by frontend developer if QA is unavailable. Budget 2 extra days if needed. |
| CR-05 | Developer unfamiliar with ECharts or Maatwebsite Excel | Low | Low | **Low** | Budget 4h for learning curve in Sprint 2 (Excel) and Sprint 4 (ECharts). Both libraries are well-documented. |

---

## 8. Milestone Timeline

### 8.1 Visual Timeline

```
Week:  1    2    3    4    5    6    7    8    9    10   11   12
       |----|----|----|----|----|----|----|----|----|----|----|----|
       |  Sprint 1  |  Sprint 2  |  Sprint 3  |  Sprint 4  |  Sprint 5  |  Sprint 6  |
       | DB + Models| Services   | API Layer  | Frontend   | Expenses   | Testing    |
       |            |            |            | Pages      | Export     | Perf       |
       |            |            |            |            | Settings   | Polish     |
       |            |            |            |            |            |            |
       M1           M2           M3           M4           M5           M6
```

### 8.2 Key Milestones

| Milestone | Sprint | Week | Description | Success Criteria |
|-----------|:------:|:----:|-------------|-----------------|
| **M0** | 0 | Pre | Shared Foundations complete | FOUND-006 + FOUND-007 merged to main |
| **M1** | 1 | 2 | Database Foundation complete | All migrations run; CostRateService passes tests; `composer analyse` clean |
| **M2** | 2 | 4 | Core Services complete (Phase A backend done) | Profitability + Utilization services pass tests; backfill command works; all service tests green |
| **M3** | 3 | 6 | API Layer complete (Phase A fully functional via API) | All endpoints return correct data; endpoint tests green; OpenAPI spec updated |
| **M4** | 4 | 8 | Frontend Pages complete | All 5 report pages render with live data; charts display correctly; templates/schedules UI working |
| **M5** | 5 | 10 | Feature Complete (Phase B UI done) | Expenses CRUD, export modal, cost rate settings all functional end-to-end |
| **M6** | 6 | 12 | Release Candidate | All tests pass (PHP, Vitest, Playwright); performance benchmarks met; no P0/P1 bugs |

### 8.3 Go/No-Go Decision Points

| Decision Point | When | Criteria | Action if No-Go |
|---------------|------|----------|----------------|
| **Phase A Go/No-Go** | End of Sprint 3 (Week 6) | M1 + M2 + M3 achieved. API endpoints return correct profitability and utilization data. | Defer Phase B (Sprints 4-6) until Phase A issues are resolved. Phase A can ship independently. |
| **Frontend Readiness** | Start of Sprint 4 (Week 7) | OpenAPI spec updated (RPT-036 complete). TypeScript client regenerated. | Frontend starts with manual type definitions and mocked data. RPT-036 must complete by Sprint 4 Day 3 at latest. |
| **Release Candidate** | End of Sprint 6 (Week 12) | M6 achieved. All tests pass. Performance benchmarks met. No P0/P1 bugs open. | Extend Sprint 6 by 1 week for bug fixing. If still not ready, ship Phase A only and defer Phase B to next cycle. |

### 8.4 Phase A Independent Release

Phase A (Sprints 1-3) delivers:
- Cost rate infrastructure with hierarchical cascade
- Profitability report (API + backend)
- Utilization report (API + backend)
- Backfill command for existing data
- Updated APIs for cost rate/capacity management

If Phase B is deferred, Phase A can ship with a minimal frontend (data accessible via API, existing report pages show revenue data). This provides immediate value for organizations that need margin analysis and capacity planning.

---

## Appendix A: Task ID Cross-Reference

The task_assignments file uses `RPT-xxx` identifiers. Per AMD-01 in the PRD, all task IDs are officially prefixed `RPT-`. This sprint plan uses `RPT-` throughout. The mapping is:

| RPT ID | Legacy ID | Description |
|--------|-----------|-------------|
| RPT-001 | RPT-001 | Migration: cost_rate on members |
| RPT-002 | RPT-002 | Migration: cost_rate on project_members |
| RPT-003 | RPT-003 | Migration: default_cost_rate on organizations |
| RPT-004 | RPT-004 | Migration: cost_rate on time_entries |
| RPT-005 | RPT-005 | Migration: budget_amount on projects |
| RPT-006 | RPT-006 | Migration: report_templates table |
| RPT-007 | RPT-007 | Migration: report_schedules table |
| RPT-008 | RPT-008 | Migration: expenses table |
| RPT-009 | RPT-009 | Update Eloquent models for new columns |
| RPT-010 | RPT-010 | Create new Eloquent models |
| RPT-011 | RPT-011 | Create CostRateService |
| RPT-012 | RPT-012 | Create ExpenseCategory enum |
| RPT-013 | RPT-013 | Create ReportScheduleFrequency/Status enums |
| RPT-014 | RPT-014 | Create ProfitabilityReportService |
| RPT-015 | RPT-015 | Create UtilizationReportService |
| RPT-016 | RPT-016 | Create ReportScheduleService |
| RPT-017 | RPT-017 | Create SendScheduledReportsCommand |
| RPT-018 | RPT-018 | Create ReportExportService |
| RPT-019 | RPT-019 | Profitability Report API endpoint |
| RPT-020 | RPT-020 | Utilization Report API endpoint |
| RPT-021 | RPT-021 | Report Templates CRUD API |
| RPT-022 | RPT-022 | Report Schedules CRUD API |
| RPT-023 | RPT-023 | Expenses CRUD API |
| RPT-024 | RPT-024 | Budget Report API endpoint |
| RPT-025 | RPT-025 | Update existing API endpoints |
| RPT-026 | RPT-026 | Frontend: reporting navigation |
| RPT-027 | RPT-027 | Frontend: profitability page |
| RPT-028 | RPT-028 | Frontend: utilization page |
| RPT-029 | RPT-029 | Frontend: budget page |
| RPT-030 | RPT-030 | Frontend: templates UI |
| RPT-031 | RPT-031 | Frontend: schedules UI |
| RPT-032 | RPT-032 | Frontend: expenses page |
| RPT-033 | RPT-033 | Frontend: export modal |
| RPT-034 | RPT-034 | Frontend: cost rate management UI |
| RPT-035 | RPT-035 | Backfill cost_rate command |
| RPT-036 | RPT-036 | OpenAPI spec update |
| RPT-037 | RPT-037 | E2E Playwright tests |
| RPT-038 | RPT-038 | Vitest component tests |
| RPT-039 | RPT-039 | Performance optimization |

---

## Appendix B: Sprint Load Balancing Analysis

### Story Point Distribution by Sprint

```
Sprint 1:  ████████████████████████████████  32 SP
Sprint 2:  ███████████████████████████████████████  39 SP  <-- highest
Sprint 3:  █████████████████████████████████████████  41 SP  <-- highest (with RPT-036)
Sprint 4:  ██████████████████████████████████  34 SP
Sprint 5:  ██████████████████  18 SP  <-- buffer sprint
Sprint 6:  ████████████████  16 SP  <-- (was 22 before AMD-07 moves)
```

### Load Balancing Adjustments Made

1. **AMD-07**: Moved RPT-035 (backfill, 4h/2SP) from Sprint 6 to Sprint 2 to ensure cost rate data is available for testing profitability reports.
2. **RPT-036** (OpenAPI, 6h/3SP): Moved from Sprint 6 to Sprint 3 to unblock frontend development in Sprint 4.
3. **Sprint 5 as buffer**: Intentionally light (18 SP) to absorb Sprint 4 overflow from the frontend bottleneck.
4. **Sprint 3 absorbs RPT-036**: Increases Sprint 3 from 38 SP to 41 SP, but the task is mechanical (spec generation) and low-risk.

### Recommended Rebalancing if Sprints Overflow

| If Sprint X overflows... | Move these tasks to... | Impact |
|--------------------------|----------------------|--------|
| Sprint 2 (39 SP) | RPT-018 to Sprint 3 | Sprint 3 gains 8 SP. No frontend impact (export service not needed until Sprint 5). |
| Sprint 3 (41 SP) | RPT-036 to Sprint 4 Day 1 | Frontend uses manual types for first 1-2 days. Minimal impact. |
| Sprint 4 (34 SP) | RPT-029 to Sprint 5 | Budget page deferred. Sprint 5 gains 5 SP (total 23 SP). Manageable. |

---

Last updated: 2026-02-06
