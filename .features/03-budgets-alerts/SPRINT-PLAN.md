# Sprint Plan: Budgets & Alerts Feature

**Feature ID**: 03
**Task Prefix**: `BUD-`
**Date**: 2026-02-06
**Status**: Planning Complete
**PRD Reference**: `/home/keven/Documents/solidtime-analysis/.features/03-budgets-alerts/PRD.md`
**Architecture Reference**: `/home/keven/Documents/solidtime-analysis/.features/03-budgets-alerts/ARCHITECTURE.md`

---

## 1. Executive Summary

The Budgets & Alerts feature adds project-level budget tracking (hours, cost, fixed-fee), configurable threshold alerts with email and in-app notifications, a dashboard overview widget, burn-rate forecasting, and budget-vs-actual reporting to Solidtime.

**Total Effort**: 88 story points / ~165 developer-hours (sum of individual task hours from sprint details)
**Number of Sprints**: 3 (2-week sprints, 6 weeks total)
**Team Size Assumption**: 2 developers (1 backend-focused, 1 frontend-focused, both capable of fullstack work)
**Sprint Capacity**: ~40 SP per sprint per team (assumes ~80h productive time per developer per 2-week sprint, with 20% overhead for reviews, meetings, and context switching, yielding ~128 productive hours = 64 SP theoretical max, budgeted at ~35-40 SP to account for risk)

The feature is organized into three phases:
- **Sprint 1 (Foundation)**: Data model, core services, CRUD API, and backend tests -- 33 SP
- **Sprint 2 (Alerts & Dashboard)**: Alert engine, notifications, frontend core components, and dashboard widget -- 31 SP
- **Sprint 3 (Forecasting, Reports & Polish)**: Forecasting service, report page, project modal integration, E2E tests, and documentation -- 30 SP (including BUD-034)

A prerequisite **Phase 0** consisting of Shared Foundation tasks (FOUND-001 through FOUND-005, FOUND-007) must be completed before Sprint 2 can begin. FOUND-007 is required before Sprint 1. Total Phase 0 effort: 28 hours (14 SP).

---

## 2. Sprint Overview Table

| Sprint # | Name | Duration | Story Points | Key Deliverables |
|:---:|------|:---:|:---:|------------------|
| 0 | Shared Foundations | 1 week (pre-sprint) | 14 SP (28h) | Notification infrastructure (FOUND-001 to FOUND-005), modular permissions (FOUND-007) |
| 1 | Foundation | 2 weeks | 33 SP (66h) | BudgetType/BudgetPeriod enums, DB migrations, BudgetAlert model, BudgetService, ProjectController extensions, API routes, permissions, unit + endpoint tests |
| 2 | Alerts & Dashboard | 2 weeks | 31 SP (62h) | BudgetAlertService, notification classes, TimeEntry integration, monthly reset command, TypeScript types, Pinia store, BudgetProgress component, project detail budget section, dashboard overview card, OpenAPI spec update |
| 3 | Forecasting, Reports & Polish | 2 weeks | 30 SP (60h) | BudgetForecastService, BudgetReportService, report API endpoint, budget report page + web route, project create/edit modal budget config, frontend component tests, E2E Playwright tests |
| **Total** | | **7 weeks** | **108 SP** | Full Budgets & Alerts feature (88 SP feature + 14 SP shared + 6 SP buffer) |

---

## 3. Dependency Map

### 3.1 Shared Foundation Dependencies

```
FOUND-007 (Modular Permissions)
    |
    +---> BUD-010 (Register budget permissions)
    |         Must complete before BUD-008, BUD-013
    |
FOUND-001 (Notification migration)
    |
    +---> FOUND-002 (Base notification classes)
              |
              +---> FOUND-003 (Notification bell UI)
              |         Needed for Sprint 2 frontend
              |
              +---> FOUND-004 (Notification API endpoints)
              |         Needed for Sprint 2 frontend
              |
              +---> BUD-015 (Budget notification classes)
                        Needed for BUD-016 (TimeEntry integration)

FOUND-005 (Notification preferences)
    |
    +---> BUD-015 (Budget notification classes)
              Respects per-member notification preferences
```

**Hard dependencies on Shared Foundations**:

| Foundation Task | Blocks (Feature Tasks) | Required By Sprint |
|----------------|----------------------|-------------------|
| FOUND-007 | BUD-010 (permissions registration) | Sprint 1 |
| FOUND-001 | FOUND-002, FOUND-003, FOUND-004 | Sprint 2 |
| FOUND-002 | BUD-015 (notification classes) | Sprint 2 |
| FOUND-003 | BUD-024 (dashboard card -- notification bell needed for full UX) | Sprint 2 |
| FOUND-004 | BUD-024 (notification API for bell) | Sprint 2 |
| FOUND-005 | BUD-015 (notification preferences) | Sprint 2 |

### 3.2 Inter-Task Dependencies (Feature Internal)

```
Wave 1 (No dependencies):
  BUD-001 (Enums)
  BUD-010 (Permissions)

Wave 2 (depends on Wave 1):
  BUD-002 (Projects migration) <-- BUD-001
  BUD-007 (Request validation) <-- BUD-001

Wave 3 (depends on Wave 2):
  BUD-003 (Alerts migration) <-- BUD-002

Wave 4 (depends on Wave 3):
  BUD-004 (BudgetAlert model) <-- BUD-003
  BUD-005 (Extend Project model) <-- BUD-001, BUD-002

Wave 5 (depends on Wave 4):
  BUD-006 (BudgetService) <-- BUD-005

Wave 6 (depends on Wave 5):                  [--- SPRINT 1 BOUNDARY ---]
  BUD-008 (Controllers) <-- BUD-005, BUD-006, BUD-007
  BUD-012 (BudgetService tests) <-- BUD-006
  BUD-014 (BudgetAlertService) <-- BUD-004, BUD-006
  BUD-019 (Chart endpoint) <-- BUD-006
  BUD-025 (ForecastService) <-- BUD-006

Wave 7 (depends on Wave 6):
  BUD-009 (ProjectResource) <-- BUD-006, BUD-008
  BUD-011 (Routes) <-- BUD-008
  BUD-015 (Notifications) <-- BUD-014, FOUND-002
  BUD-016 (TimeEntry integration) <-- BUD-014
  BUD-017 (Monthly reset) <-- BUD-014
  BUD-026 (Forecast tests) <-- BUD-025
  BUD-027 (ReportService) <-- BUD-006, BUD-025

Wave 8 (depends on Wave 7):                  [--- SPRINT 2 BOUNDARY ---]
  BUD-013 (Endpoint tests) <-- BUD-008, BUD-011
  BUD-018 (Alert tests) <-- BUD-014, BUD-016
  BUD-020 (TS types) <-- BUD-009
  BUD-028 (Report endpoint) <-- BUD-027
  BUD-033 (OpenAPI) <-- BUD-008, BUD-009, BUD-011

Wave 9 (depends on Wave 8):
  BUD-021 (Pinia store) <-- BUD-020
  BUD-022 (Progress bar) <-- BUD-020

Wave 10 (depends on Wave 9):
  BUD-023 (Project page budget) <-- BUD-021, BUD-022
  BUD-024 (Dashboard card) <-- BUD-019, BUD-021, BUD-022
  BUD-029 (Report page) <-- BUD-020, BUD-021, BUD-028
  BUD-030 (Project modal config) <-- BUD-020, BUD-021

Wave 11 (depends on Wave 10):                [--- SPRINT 3 BOUNDARY ---]
  BUD-031 (Component tests) <-- BUD-022, BUD-023, BUD-024
  BUD-032 (E2E tests) <-- BUD-029, BUD-030
  BUD-034 (Web route + nav) <-- BUD-029
```

### 3.3 External Feature Dependencies

| External Feature | Dependency Type | Impact |
|-----------------|----------------|--------|
| 09 Advanced Reporting | Soft (enhanced by) | Budget data feeds into advanced reports. Feature 09 is scheduled for Phase 2b -- no blocking impact. |
| 04 Invoicing System | Soft (enhanced by) | Budget status could inform invoice generation. No blocking impact. |
| 10 Teams & Groups | Soft (enhanced by) | Team-scoped budget views. Not required for initial implementation. |

There are **no hard external feature dependencies**. Budgets & Alerts depends only on the Shared Foundations (Phase 0).

---

## 4. Sprint Details

### Sprint 0: Shared Foundations (Pre-Sprint, 1 Week)

**Sprint Goal**: Establish the cross-cutting notification infrastructure and modular permissions pattern required by Budgets & Alerts and all subsequent features.

**Note**: This sprint is shared across multiple features. If another feature team has already completed these tasks, Sprint 0 can be skipped.

| Task ID | Description | Effort | SP | Dependencies | Assignee Role |
|---------|-------------|:---:|:---:|-------------|:---:|
| FOUND-007 | Create modular permissions infrastructure (`app/Permissions/` directory, refactor existing permissions) | 4h | 2 | None | Backend |
| FOUND-001 | Create notification infrastructure migration (`notifications` table, `notification_preferences` on members) | 2h | 1 | None | Backend |
| FOUND-002 | Create `BaseNotification` class with `database` + `mail` channels, respecting per-member preferences | 4h | 2 | FOUND-001 | Backend |
| FOUND-004 | Create notification API endpoints (list, mark read, mark all read, unread count) | 6h | 3 | FOUND-002 | Backend |
| FOUND-003 | Create `NotificationBell.vue` component in AppLayout header (polls every 60s, mark-as-read) | 8h | 4 | FOUND-004 | Frontend |
| FOUND-005 | Add notification preferences to organization settings (per-member email toggle) | 4h | 2 | FOUND-002 | Fullstack |
| **Total** | | **28h** | **14** | | |

**Acceptance Criteria**:
- [ ] `notifications` table exists and migration runs cleanly
- [ ] `notification_preferences` JSON column exists on `members` table
- [ ] `BaseNotification` class exists at `app/Notifications/BaseNotification.php` with configurable channels
- [ ] Notification API endpoints return correct responses (paginated list, mark read, unread count)
- [ ] Notification bell renders in AppLayout, shows unread count badge, polls every 60s
- [ ] Per-member email notification toggle works in organization settings
- [ ] `app/Permissions/` directory exists with modular registration pattern
- [ ] Existing permissions refactored to new pattern without regressions

**Deliverables**:
- `database/migrations/2026_02_28_000003_create_notifications_table.php`
- `database/migrations/2026_02_28_000004_add_notification_preferences_to_members.php`
- `app/Notifications/BaseNotification.php`
- `app/Http/Controllers/Api/V1/NotificationController.php`
- `resources/js/packages/ui/src/Notification/NotificationBell.vue`
- `app/Permissions/` directory with refactored permission files

**Risk Factors**:
- Notification bell polling interval may need tuning for performance
- Existing permission tests may break during refactoring -- run full test suite

---

### Sprint 1: Foundation (Weeks 1-2)

**Sprint Goal**: Build the complete backend data model, budget consumption service, CRUD API, and comprehensive backend test coverage for the budget feature.

| Task ID | Description | Effort | SP | Dependencies | Assignee Role |
|---------|-------------|:---:|:---:|-------------|:---:|
| BUD-001 | Create `BudgetType` and `BudgetPeriod` enums (`app/Enums/`) | 2h | 1 | None | Backend |
| BUD-010 | Register budget permissions via `BudgetPermissions::register()` (modular pattern per SF-08) | 2h | 1 | FOUND-007 | Backend |
| BUD-002 | Migration: Add `budget_type`, `budget_amount`, `budget_currency_code`, `budget_period` columns to `projects` table with CHECK constraints | 4h | 2 | BUD-001 | Backend |
| BUD-007 | Extend `ProjectStoreRequest` and `ProjectUpdateRequest` with budget field validation rules | 4h | 2 | BUD-001 | Backend |
| BUD-003 | Migration: Create `budget_alerts` table with UUID PK, foreign keys, unique constraint on `(project_id, threshold_percentage)`, indexes, `organization_id` (AMD-06) | 4h | 2 | BUD-002 | Backend |
| BUD-004 | Create `BudgetAlert` model with factory (`BudgetAlertFactory`), relationships, `isTriggered()`, `trigger()`, `reset()` methods | 3h | 2 | BUD-003 | Backend |
| BUD-005 | Extend `Project` model: add budget property annotations, casts (`BudgetType`, `BudgetPeriod`), `hasBudget()` method, `budgetAlerts()` relationship | 3h | 2 | BUD-001, BUD-002 | Backend |
| BUD-006 | Create `BudgetService`: `getConsumption()` (hours/cost/fixed-fee), `getConsumptionBatch()` (AMD-09), `createDefaultAlerts()`, monthly period scoping with `TimezoneService` | 8h | 5 | BUD-005 | Backend |
| BUD-008 | Extend `ProjectController` for budget CRUD + create `BudgetController` for budget status and alert management endpoints | 6h | 3 | BUD-005, BUD-006, BUD-007 | Backend |
| BUD-009 | Extend `ProjectResource` with nested `budget` object (consumption data from `BudgetService`) | 3h | 2 | BUD-006, BUD-008 | Backend |
| BUD-011 | Add API routes for budget endpoints in `routes/api.php`: budget status, alert management, chart overview, report | 2h | 1 | BUD-008 | Backend |
| BUD-012 | `BudgetService` unit tests: hours/cost/fixed-fee consumption, monthly/total periods, running entries, batch calculation, edge cases | 8h | 5 | BUD-006 | Backend |
| BUD-013 | Budget endpoint tests: CRUD, permission checks, validation, 404 on no budget, alert management | 8h | 5 | BUD-008, BUD-011 | Backend |
| **Total** | | **57h** | **33** | | |

**Acceptance Criteria**:
- [ ] `BudgetType` enum has values: `hours`, `cost`, `fixed_fee`
- [ ] `BudgetPeriod` enum has values: `total`, `monthly`
- [ ] `projects` table has `budget_type`, `budget_amount`, `budget_currency_code`, `budget_period` columns with CHECK constraints
- [ ] `budget_alerts` table exists with correct schema, foreign keys, indexes, and unique constraint
- [ ] `BudgetAlert` model has factory, relationships, and trigger/reset methods
- [ ] `Project` model extended with budget casts, `hasBudget()`, and `budgetAlerts()` relationship
- [ ] `BudgetService.getConsumption()` returns correct values for all budget types and periods
- [ ] `BudgetService.getConsumptionBatch()` executes max 2 queries (one for hours, one for cost budgets)
- [ ] Default alerts (50%, 80%, 100%) are created when a budget is first set
- [ ] Budget CRUD via ProjectController works with premium feature gating
- [ ] `ProjectResource` includes nested `budget` object when budget exists, `null` when not
- [ ] API routes registered and accessible with correct middleware
- [ ] Budget permissions (`budgets:view`, `budgets:update`, `budget-alerts:manage`) registered for all roles
- [ ] All `BudgetService` unit tests pass (minimum 15 test cases)
- [ ] All endpoint tests pass (minimum 20 test cases covering CRUD, auth, validation)
- [ ] `composer analyse` passes at PHPStan level 9
- [ ] `composer fix` produces no changes

**Deliverables**:
- `app/Enums/BudgetType.php`
- `app/Enums/BudgetPeriod.php`
- `database/migrations/2026_03_03_000001_add_budget_columns_to_projects_table.php`
- `database/migrations/2026_03_03_000002_create_budget_alerts_table.php`
- `app/Models/BudgetAlert.php`
- `database/factories/BudgetAlertFactory.php`
- `app/Service/BudgetService.php`
- `app/Http/Controllers/Api/V1/BudgetController.php`
- `app/Permissions/BudgetPermissions.php`
- `app/Http/Resources/V1/Budget/BudgetResource.php`
- `app/Http/Resources/V1/Budget/BudgetAlertResource.php`
- `tests/Unit/Service/BudgetServiceTest.php`
- `tests/Unit/Endpoint/Api/V1/BudgetEndpointTest.php`
- Modified: `app/Models/Project.php`, `app/Http/Controllers/Api/V1/ProjectController.php`, `app/Http/Resources/V1/Project/ProjectResource.php`, `app/Http/Requests/V1/Project/ProjectStoreRequest.php`, `app/Http/Requests/V1/Project/ProjectUpdateRequest.php`, `routes/api.php`, `app/Providers/JetstreamServiceProvider.php`

**Risk Factors**:
- **BUD-006 is the critical path bottleneck**: 8 downstream tasks depend on it. Assign the strongest backend developer. If delayed, frontend can scaffold component structure with mock data.
- **PostgreSQL-specific SQL**: The `EXTRACT(epoch FROM ...)` syntax is PostgreSQL-specific. Ensure test database uses PostgreSQL (not SQLite).
- **CHECK constraint compatibility**: Verify CHECK constraints work correctly with the project's database setup. Consider using application-level validation as fallback.

---

### Sprint 2: Alerts & Dashboard (Weeks 3-4)

**Sprint Goal**: Implement the alert engine with notifications, build all core frontend components (TypeScript types, Pinia store, progress bar, project detail section, dashboard card), and update the OpenAPI specification.

| Task ID | Description | Effort | SP | Dependencies | Assignee Role |
|---------|-------------|:---:|:---:|-------------|:---:|
| BUD-014 | Create `BudgetAlertService`: `evaluateAlerts()` with `lockForUpdate()`, threshold crossing logic, `resetMonthlyAlerts()` | 8h | 5 | BUD-004, BUD-006 | Backend |
| BUD-015 | Create `BudgetThresholdNotification`, `BudgetExceededNotification` extending `BaseNotification`, and `SendBudgetAlertNotification` queued job | 4h | 2 | BUD-014, FOUND-002 | Backend |
| BUD-016 | Integrate alert evaluation with time entry pipeline: create `TimeEntryObserver` or extend `RecalculateSpentTimeForProject` job, register in `EventServiceProvider` | 4h | 2 | BUD-014 | Backend |
| BUD-017 | Create `BudgetAlertResetMonthlyCommand` (`budget:reset-monthly-alerts`), register in `Kernel.php` with config gate, add `--dry-run` option | 3h | 2 | BUD-014 | Backend |
| BUD-018 | `BudgetAlertService` unit tests: threshold crossing up/down, reset logic, archived projects, concurrent writes, monthly reset | 6h | 3 | BUD-014, BUD-016 | Backend |
| BUD-019 | Budget dashboard chart API endpoint (`GET /charts/budget-overview`): batch consumption, top 5 by percentage, exclude archived | 4h | 2 | BUD-006 | Backend |
| BUD-033 | Update OpenAPI spec and regenerate TypeScript client | 3h | 2 | BUD-008, BUD-009, BUD-011 | Backend |
| BUD-020 | Create TypeScript types (`resources/js/types/budget.d.ts`): `Budget`, `BudgetForecast`, `BudgetAlert`, `BudgetOverviewProject` interfaces; extend `Project` type | 3h | 2 | BUD-009 | Frontend |
| BUD-021 | Create `useBudgetStore` Pinia store (`resources/js/utils/useBudget.ts`): `useBudgetStatus()`, `useUpdateBudgetAlerts()`, `useBudgetOverview()` with `@tanstack/vue-query` | 6h | 3 | BUD-020 | Frontend |
| BUD-022 | Create `BudgetProgress.vue` component: 4-color progress bar (green/yellow/orange/red), amount formatting (hours/currency with `Intl.NumberFormat`), forecast display | 4h | 2 | BUD-020 | Frontend |
| BUD-023 | Add budget section to project detail page: `BudgetProgress` component, alert configuration button (for admins/managers), coexistence with `EstimatedTimeProgress` (AMD-07) | 6h | 3 | BUD-021, BUD-022 | Frontend |
| BUD-024 | Create `BudgetOverviewCard.vue` for Dashboard: top 5 projects, color-coded progress bars, click-to-navigate, conditional rendering based on permissions | 6h | 3 | BUD-019, BUD-021, BUD-022 | Frontend |
| **Total** | | **57h** | **31** | | |

**Acceptance Criteria**:
- [ ] `BudgetAlertService.evaluateAlerts()` triggers alerts when threshold crossed upward
- [ ] `BudgetAlertService.evaluateAlerts()` resets alerts when consumption drops below threshold
- [ ] `lockForUpdate()` prevents duplicate notifications on concurrent writes
- [ ] `BudgetThresholdNotification` sends via `database` + `mail` channels, respects `notify_email` flag
- [ ] `BudgetExceededNotification` sends with urgency messaging for 100% threshold
- [ ] `SendBudgetAlertNotification` job dispatches to all users with `budgets:view` permission
- [ ] Time entry create/update/delete triggers alert evaluation for affected project(s)
- [ ] Monthly reset command resets `triggered_at` for all monthly-period budget alerts
- [ ] Monthly reset command is idempotent (safe to run multiple times)
- [ ] Budget overview API returns top 5 projects sorted by consumption percentage (descending)
- [ ] Budget overview uses batch consumption calculation (max 2 SQL queries)
- [ ] OpenAPI spec updated with all new endpoints
- [ ] TypeScript types match API response shapes
- [ ] Pinia store fetches budget status, updates alerts, fetches overview
- [ ] `BudgetProgress.vue` displays correct color bands: green (<50%), yellow (50-79%), orange (80-99%), red (100%+)
- [ ] `BudgetProgress.vue` formats hours (Xh) and currency (locale-aware) correctly
- [ ] Project detail page shows budget section when budget exists, shows `EstimatedTimeProgress` when only `estimated_time` exists
- [ ] Dashboard `BudgetOverviewCard` renders, navigates to project on click
- [ ] All alert service unit tests pass (minimum 12 test cases)
- [ ] `npm run lint:fix && npm run format` produces no changes
- [ ] `composer fix && composer analyse` produces no changes

**Deliverables**:
- `app/Service/BudgetAlertService.php`
- `app/Notifications/BudgetThresholdNotification.php`
- `app/Notifications/BudgetExceededNotification.php`
- `app/Jobs/SendBudgetAlertNotification.php`
- `app/Observers/TimeEntryObserver.php`
- `app/Console/Commands/Budget/BudgetAlertResetMonthlyCommand.php`
- `tests/Unit/Service/BudgetAlertServiceTest.php`
- `resources/js/types/budget.d.ts`
- `resources/js/utils/useBudget.ts`
- `resources/js/packages/ui/src/Budget/BudgetProgress.vue`
- `resources/js/packages/ui/src/Dashboard/BudgetOverviewCard.vue`
- Modified: `app/Console/Kernel.php`, `app/Providers/EventServiceProvider.php`, `resources/js/Pages/Dashboard.vue`, `resources/js/Pages/ProjectDetail.vue` (or equivalent project show page), `resources/js/Components/Common/Project/ProjectTableRow.vue`

**Risk Factors**:
- **FOUND-001 through FOUND-005 must be complete**: If notification infrastructure is delayed, BUD-015/BUD-016 are blocked. Mitigation: BUD-014 (the alert evaluation logic) can proceed without notifications -- only the notification dispatch code depends on FOUND tasks. The alert trigger/reset logic is independent.
- **BUD-014 is Sprint 2's bottleneck**: 4 tasks depend on it (BUD-015, BUD-016, BUD-017, BUD-018). Assign strongest backend developer; start Day 1 of sprint.
- **Concurrent frontend/backend work**: Frontend developer can start BUD-020 and BUD-022 using the API contract from the PRD/Architecture docs while backend completes BUD-014/BUD-019. The TypeScript types should be defined from the API contract specification, not from runtime API responses.
- **`RecalculateSpentTimeForProject` modification risk**: The `updateMultiple` endpoint dispatches this job per entry without deduplication. Adding alert evaluation increases job processing time. Consider adding `ShouldBeUnique` keyed by `project_id` to prevent redundant evaluations.

---

### Sprint 3: Forecasting, Reports & Polish (Weeks 5-6)

**Sprint Goal**: Deliver budget forecasting, the budget report page with full filtering and CSV export, project modal budget configuration, comprehensive frontend tests, and end-to-end Playwright tests.

| Task ID | Description | Effort | SP | Dependencies | Assignee Role |
|---------|-------------|:---:|:---:|-------------|:---:|
| BUD-025 | Create `BudgetForecastService`: daily burn rate calculation, exhaustion date projection, monthly vs total handling | 6h | 3 | BUD-006 | Backend |
| BUD-026 | `BudgetForecastService` unit tests: valid forecast, no entries, zero burn rate, monthly budget scoping | 4h | 2 | BUD-025 | Backend |
| BUD-027 | Create `BudgetReportService`: `generateReport()` with project/client grouping, filters (date range, client, project, budget type, over-budget), batch consumption, meta totals | 8h | 5 | BUD-006, BUD-025 | Backend |
| BUD-028 | Budget report API endpoint (`GET /budgets/report`) + `BudgetReportRequest` validation class | 4h | 2 | BUD-027 | Backend |
| BUD-034 | Register Inertia web route for Budget Report page in `routes/web.php`, add `NavigationSidebarItem` in `AppLayout.vue` | 1h | 1 | BUD-029 | Fullstack |
| BUD-029 | Create `BudgetReport.vue` page: filter controls (group_by, client, project, type, over-budget, date range), report table, summary card, CSV export | 8h | 5 | BUD-020, BUD-021, BUD-028 | Frontend |
| BUD-030 | Add budget configuration section to Project Create/Edit modals: budget type select, amount input (hours/currency), period select, currency override select, premium gating | 6h | 3 | BUD-020, BUD-021 | Frontend |
| BUD-031 | Frontend component tests (Vitest): `BudgetProgress.vue`, budget section on project detail, `BudgetOverviewCard.vue`, project modal budget fields | 6h | 3 | BUD-022, BUD-023, BUD-024 | Frontend |
| BUD-032 | E2E Playwright tests: full budget workflow (create budget, log time, verify alerts, check dashboard, view report), monthly reset simulation | 8h | 5 | BUD-029, BUD-030 | QA / Frontend |
| **Total** | | **51h** | **30** (including 1 SP for BUD-034) | | |

**Acceptance Criteria**:
- [ ] `BudgetForecastService.getForecast()` returns correct daily burn rate and exhaustion date
- [ ] Forecast returns `null` when insufficient data (no entries, zero burn rate)
- [ ] Monthly budget forecast uses current month start date for calculation
- [ ] `BudgetReportService.generateReport()` returns correct data with all filter combinations
- [ ] Report supports grouping by project (default) and client
- [ ] Over-budget filter correctly filters projects at >100% consumption
- [ ] Report meta totals (total_budget, total_consumed, total_remaining, projects_over_budget) calculated correctly
- [ ] Budget report API endpoint returns paginated, filtered data
- [ ] Budget Report page renders with all filter controls functional
- [ ] CSV export produces valid CSV file with correct data
- [ ] Budget report accessible via sidebar navigation (Budget Report link with `ChartBarIcon`)
- [ ] Web route `budgets.report` registered in `routes/web.php`
- [ ] Project create modal includes budget type, amount, period, currency fields (premium gated)
- [ ] Project edit modal includes budget type, amount, period, currency fields (premium gated)
- [ ] Hours input converts to seconds before API submission
- [ ] Currency input converts to cents before API submission
- [ ] All Vitest component tests pass (minimum 15 test cases)
- [ ] All E2E tests pass (minimum 5 workflow scenarios)
- [ ] Budget report service unit tests pass
- [ ] Forecast service unit tests pass
- [ ] Full regression test suite passes (no regressions in existing features)
- [ ] `npm run lint:fix && npm run format` produces no changes
- [ ] `composer fix && composer analyse` produces no changes

**Deliverables**:
- `app/Service/BudgetForecastService.php`
- `app/Service/BudgetReportService.php`
- `app/Http/Requests/V1/Budget/BudgetReportRequest.php`
- `tests/Unit/Service/BudgetForecastServiceTest.php`
- `tests/Unit/Service/BudgetReportServiceTest.php`
- `resources/js/Pages/BudgetReport.vue`
- `e2e/budget-workflow.spec.ts`
- Modified: `resources/js/Components/Common/Project/ProjectEditModal.vue`, `resources/js/Components/Common/Project/ProjectCreateModal.vue`, `resources/js/Layouts/AppLayout.vue`, `routes/web.php`

**Risk Factors**:
- **Report performance with large datasets**: `BudgetReportService` calls `getConsumptionBatch()` which is optimized, but also calls `getForecast()` per project (N queries for days-with-activity). Mitigation: If performance target (< 500ms for 100 projects) is not met, batch the forecast day-count query as well.
- **CSV export complexity**: CSV generation in the frontend requires careful handling of currency formatting and Unicode characters. Mitigation: Use a well-tested CSV library or generate CSV server-side.
- **E2E test flakiness**: Notification timing and dashboard polling can cause race conditions in E2E tests. Mitigation: Use Playwright's `waitForResponse()` to synchronize on API calls rather than fixed timeouts.
- **BUD-034 (web route) is low-effort but must not be forgotten**: Without it, the Budget Report page is inaccessible. Schedule it immediately after BUD-029.

---

## 5. Testing Strategy Per Sprint

### Sprint 0: Shared Foundations
| Test Type | Scope | Tasks |
|-----------|-------|-------|
| Unit Tests | `BaseNotification` channels logic, notification preferences | Part of FOUND-002, FOUND-005 |
| Endpoint Tests | Notification API: list, mark read, unread count | Part of FOUND-004 |
| Component Tests | `NotificationBell.vue` rendering, badge count, polling | Part of FOUND-003 |

### Sprint 1: Foundation
| Test Type | Scope | Tasks |
|-----------|-------|-------|
| Unit Tests | `BudgetService`: all budget types (hours, cost, fixed_fee), both periods (total, monthly), running entries, batch calculation, edge cases (no budget, over budget, zero amount) | BUD-012 (8h / 5 SP) |
| Endpoint Tests | Budget CRUD: create/update/delete budget via project endpoints, budget status endpoint, alert management endpoint, permission checks (employee cannot update), validation errors, premium gating, 404 on no budget | BUD-013 (8h / 5 SP) |
| Integration Tests | Verify migrations run and rollback cleanly, verify CHECK constraints enforce data integrity | Part of BUD-002, BUD-003 |

### Sprint 2: Alerts & Dashboard
| Test Type | Scope | Tasks |
|-----------|-------|-------|
| Unit Tests | `BudgetAlertService`: threshold crossing upward (trigger), threshold crossing downward (reset), duplicate trigger prevention (`triggered_at` check), archived project skipping, `lockForUpdate()` behavior, monthly alert reset, idempotency | BUD-018 (6h / 3 SP) |
| Integration Tests | Time entry create/update/delete triggers alert evaluation, both old and new projects evaluated on project change, `RecalculateSpentTimeForProject` job includes alert logic, notification job dispatched correctly | Part of BUD-016, BUD-018 |

### Sprint 3: Forecasting, Reports & Polish
| Test Type | Scope | Tasks |
|-----------|-------|-------|
| Unit Tests | `BudgetForecastService`: valid forecast, no entries, zero burn rate, monthly scoping | BUD-026 (4h / 2 SP) |
| Unit Tests | `BudgetReportService`: all filter combinations, grouping by project/client, over-budget filter, meta totals | Part of BUD-027 |
| Component Tests (Vitest) | `BudgetProgress.vue` (color bands, formatting), `BudgetOverviewCard.vue` (rendering, navigation), project modal budget fields (validation, conversion) | BUD-031 (6h / 3 SP) |
| E2E Tests (Playwright) | Full workflow: create project with budget -> log time -> verify progress -> check alerts -> view dashboard -> view report -> export CSV. Monthly reset simulation. Permission-based visibility. | BUD-032 (8h / 5 SP) |

### Testing Summary Timeline

```
Sprint 0:  [Foundation tests: notifications, permissions]
Sprint 1:  [BudgetService unit tests] [Endpoint tests]
Sprint 2:  [AlertService unit tests] [Integration tests]
Sprint 3:  [Forecast tests] [Report tests] [Component tests] [E2E tests]
           ^                                                    ^
           Backend test coverage complete                       Full test coverage
```

---

## 6. Definition of Done

### 6.1 Per-Task DoD Checklist

- [ ] Code compiles without errors
- [ ] `declare(strict_types=1)` at top of every PHP file
- [ ] PHP code style: `composer fix` produces no changes
- [ ] PHP static analysis: `composer analyse` passes at current PHPStan level
- [ ] JS/TS code style: `npm run lint:fix && npm run format` produces no changes
- [ ] All new public methods have PHPDoc or JSDoc comments
- [ ] Unit test(s) written for new business logic (minimum 1 test per method)
- [ ] No `TODO` or `FIXME` comments left in committed code (unless explicitly tracked as tech debt)
- [ ] Code reviewed by at least one other developer
- [ ] Task-specific acceptance criteria met (as documented in sprint details)

### 6.2 Per-Sprint DoD Checklist

- [ ] All sprint tasks completed and merged to feature branch
- [ ] Full backend test suite passes (`php artisan test`)
- [ ] Full frontend test suite passes (`npm run test`)
- [ ] No regressions in existing features (existing test suite passes 100%)
- [ ] Database migrations run and rollback cleanly on fresh database
- [ ] API endpoints respond within performance targets
- [ ] Sprint demo conducted with stakeholders
- [ ] Sprint retrospective notes captured
- [ ] Known issues documented in sprint review

### 6.3 Feature-Level DoD Checklist

- [ ] All 34 tasks (BUD-001 through BUD-034) completed
- [ ] All Shared Foundation dependencies (FOUND-001 through FOUND-005, FOUND-007) satisfied
- [ ] Full E2E test suite passes (budget workflow, monthly reset, permissions)
- [ ] OpenAPI specification updated and TypeScript client regenerated
- [ ] Performance targets met:
  - Budget consumption calculation (single project): < 100ms
  - Alert evaluation on time entry write: < 50ms (synchronous portion)
  - Dashboard budget overview (5 projects): < 200ms (95th percentile)
  - Budget report (100 projects): < 500ms
  - Monthly alert reset command: < 5 seconds (500 projects)
- [ ] Security review: all endpoints have permission checks, input validation, premium gating
- [ ] No N+1 queries (batch consumption calculation verified)
- [ ] `estimated_time` coexistence works correctly (AMD-07)
- [ ] Feature branch merged to main branch
- [ ] Deployment to staging environment successful
- [ ] Smoke test on staging passed

---

## 7. Risk Register

### 7.1 Technical Risks

| # | Risk | Probability | Impact | Severity | Mitigation |
|---|------|:-----------:|:------:|:--------:|------------|
| T1 | `BudgetService` performance degrades with large time entry datasets (>100k entries per project) | Low | High | Medium | Use database indexes on `(project_id, start)`. Fall back to caching with 60s TTL if needed. Monitor query execution times via Laravel Telescope. |
| T2 | `lockForUpdate()` on `budget_alerts` causes lock contention under high concurrency | Low | Medium | Low | Row-level locks are per-project. Concurrent writes to the same project are rare. If contention observed, switch to optimistic locking with version column. |
| T3 | PostgreSQL-specific SQL (`EXTRACT(epoch FROM ...)`) prevents database portability | Medium | Low | Low | Solidtime is PostgreSQL-only. Document this constraint. If portability needed in future, abstract into a DB-specific service. |
| T4 | Rate changes on projects do not trigger budget re-evaluation (gap identified in CODEBASE-ANALYSIS) | High | Medium | High | Add `RecalculateSpentTimeForProject` dispatch after `BillableRateService::updateTimeEntriesBillableRateForProject()` calls in `ProjectController::update()`. Implement in BUD-016. |
| T5 | Bulk time entry updates dispatch redundant `RecalculateSpentTimeForProject` jobs (no deduplication) | High | Low | Medium | Add `ShouldBeUnique` interface to the job keyed by `project_id`. The alert evaluation is already idempotent via `triggered_at` check, so correctness is not affected -- only performance. |
| T6 | Monthly timezone handling edge cases (DST transitions, organizations spanning multiple timezones) | Medium | Medium | Medium | Always use `organization.timezone` for month boundary calculation (not user timezone). Budget is an org-level concept. Test with UTC+12 and UTC-12 extremes. |

### 7.2 Dependency Risks

| # | Risk | Probability | Impact | Severity | Mitigation |
|---|------|:-----------:|:------:|:--------:|------------|
| D1 | Shared Foundation tasks (FOUND-001 through FOUND-005) not completed before Sprint 2 | Medium | High | High | Sprint 2 backend alert logic (BUD-014) can proceed without notification infrastructure. Only BUD-015 (notification classes) is blocked. Frontend work (BUD-020 through BUD-024) is independent of FOUND tasks. Worst case: BUD-015 and BUD-016 slip to Sprint 3. |
| D2 | FOUND-007 (modular permissions) not completed before Sprint 1 | Low | Medium | Medium | BUD-010 can temporarily register permissions directly in `JetstreamServiceProvider` (old pattern) and refactor later. One-time migration cost of ~2h. |
| D3 | Changes to `Project` model by other feature branches cause merge conflicts | Medium | Low | Low | Budget columns are additive (new columns only). Merge conflicts will be limited to the `$casts` array, `$fillable`, and relationship methods. Resolve via simple concatenation. |

### 7.3 Capacity Risks

| # | Risk | Probability | Impact | Severity | Mitigation |
|---|------|:-----------:|:------:|:--------:|------------|
| C1 | Single backend developer becomes a bottleneck (Sprint 1 is 90%+ backend work) | Medium | High | High | Frontend developer can assist with backend tasks after Sprint 1 ramp-up. BUD-012 and BUD-013 (test writing) can be parallelized with a QA engineer if available. |
| C2 | Frontend developer idle during Sprint 1 | Medium | Medium | Medium | Frontend developer works on Sprint 0 (FOUND-003 notification bell) and prepares Sprint 2 frontend: component scaffolding, TypeScript types from API contract docs, mock data setup. |
| C3 | Sprint 2 has highest parallelism requirement (backend + frontend simultaneously) | Medium | Medium | Medium | Clearly define API contract before sprint starts. Frontend developer uses mock API responses until backend endpoints are ready. Daily standups to identify blockers. |

---

## 8. Milestone Timeline

```
Week 0      Week 1       Week 2       Week 3       Week 4       Week 5       Week 6       Week 7
  |           |            |            |            |            |            |            |
  +--Sprint 0-+            |            |            |            |            |            |
  | Shared    |            |            |            |            |            |            |
  | Found.    |            |            |            |            |            |            |
  |           +------- Sprint 1 --------+            |            |            |            |
  |           | Data Model, Services,   |            |            |            |            |
  |           | API, Backend Tests      |            |            |            |            |
  |           |            |            +------- Sprint 2 --------+            |            |
  |           |            |            | Alerts, Notifications,  |            |            |
  |           |            |            | Frontend Core, Dashboard|            |            |
  |           |            |            |            |            +------- Sprint 3 --------+
  |           |            |            |            |            | Forecast, Reports,      |
  |           |            |            |            |            | E2E Tests, Polish       |
  |           |            |            |            |            |            |            |
  *           *            *            *            *            *            *            *
  |           |            |            |            |            |            |            |
  M0          M1           M2           M3           M4           M5           M6          M7
```

### Key Milestones

| Milestone | Week | Description | Go/No-Go Criteria |
|:---------:|:----:|-------------|-------------------|
| **M0** | 0 | Sprint 0 kickoff | Shared Foundations tasks assigned. FOUND-007 must begin immediately (blocks Sprint 1). |
| **M1** | 1 | Sprint 0 complete / Sprint 1 kickoff | FOUND-007 complete. FOUND-001 through FOUND-005 in progress (can complete during Sprint 1). BUD-001 and BUD-010 can begin. |
| **M2** | 2 | Sprint 1 midpoint checkpoint | BUD-006 (BudgetService) must be complete or near-complete. If delayed, escalate -- this is the critical path. |
| **M3** | 3 | Sprint 1 complete / Sprint 2 kickoff | **GO/NO-GO**: All Sprint 1 tasks complete. Backend test suite passing. FOUND-001 through FOUND-005 complete (required for BUD-015). If FOUND tasks delayed, BUD-015/016 deferred to Sprint 3 and Sprint 2 scope adjusted. |
| **M4** | 4 | Sprint 2 midpoint checkpoint | BUD-014 (BudgetAlertService) must be complete. Frontend components (BUD-020 through BUD-022) should be in progress or complete. |
| **M5** | 5 | Sprint 2 complete / Sprint 3 kickoff | **GO/NO-GO**: Alert engine functional. Dashboard widget rendering. Frontend core components working with live API. If Sprint 2 overruns, deprioritize BUD-025/BUD-026 (forecasting) from Sprint 3 -- can be delivered as fast-follow. |
| **M6** | 6 | Sprint 3 midpoint checkpoint | Budget report page functional. E2E test writing in progress. Project modal budget config complete. |
| **M7** | 7 | Sprint 3 complete / Feature complete | **GO/NO-GO for release**: All 34 tasks complete. Full test suite passing. Performance targets met. Feature branch ready for merge to main. |

### Decision Points

| Decision Point | Trigger | Options |
|----------------|---------|---------|
| **Sprint 1 delayed** | BUD-006 not complete by Week 2 | (a) Extend Sprint 1 by 3 days, compress Sprint 3. (b) Move BUD-012/BUD-013 to Sprint 2. |
| **FOUND tasks delayed** | FOUND-001 through FOUND-005 not complete by Week 3 | (a) Defer BUD-015/BUD-016 to Sprint 3, replace with BUD-025/BUD-026 in Sprint 2. (b) Implement notifications without shared infra (create feature-local notification system, refactor later). |
| **Performance targets missed** | Dashboard widget > 200ms or report > 500ms | (a) Add Redis caching for consumption data (60s TTL). (b) Add database indexes. (c) Accept higher latency for MVP, optimize in next release. |
| **Sprint 3 scope at risk** | More than 2 tasks from Sprint 2 spill over | (a) Cut BUD-032 (E2E tests) -- deliver as fast-follow. (b) Cut BUD-025/BUD-026 (forecasting) -- deliver as v1.1. Forecasting is P2 priority. |

---

## Appendix A: Task-to-Sprint Mapping Quick Reference

| Task ID | Sprint | Wave | Description | SP |
|---------|:------:|:----:|-------------|:--:|
| FOUND-001 | 0 | - | Notification infrastructure migration | 1 |
| FOUND-002 | 0 | - | Base notification classes | 2 |
| FOUND-003 | 0 | - | Notification bell UI | 4 |
| FOUND-004 | 0 | - | Notification API endpoints | 3 |
| FOUND-005 | 0 | - | Notification preferences | 2 |
| FOUND-007 | 0 | - | Modular permissions infrastructure | 2 |
| BUD-001 | 1 | 1 | BudgetType and BudgetPeriod enums | 1 |
| BUD-002 | 1 | 2 | Migration: budget columns on projects | 2 |
| BUD-003 | 1 | 3 | Migration: budget_alerts table | 2 |
| BUD-004 | 1 | 4 | BudgetAlert model + factory | 2 |
| BUD-005 | 1 | 4 | Extend Project model | 2 |
| BUD-006 | 1 | 5 | BudgetService (consumption) | 5 |
| BUD-007 | 1 | 2 | Extend request validation | 2 |
| BUD-008 | 1 | 6 | Controllers (Project + Budget) | 3 |
| BUD-009 | 1 | 7 | Extend ProjectResource | 2 |
| BUD-010 | 1 | 1 | Register budget permissions | 1 |
| BUD-011 | 1 | 7 | API routes | 1 |
| BUD-012 | 1 | 6 | BudgetService unit tests | 5 |
| BUD-013 | 1 | 8 | Budget endpoint tests | 5 |
| BUD-014 | 2 | 6 | BudgetAlertService | 5 |
| BUD-015 | 2 | 7 | Notification classes + job | 2 |
| BUD-016 | 2 | 7 | TimeEntry integration | 2 |
| BUD-017 | 2 | 7 | Monthly reset command | 2 |
| BUD-018 | 2 | 8 | BudgetAlertService tests | 3 |
| BUD-019 | 2 | 6 | Budget chart endpoint | 2 |
| BUD-020 | 2 | 8 | TypeScript types | 2 |
| BUD-021 | 2 | 9 | Pinia store | 3 |
| BUD-022 | 2 | 9 | BudgetProgress component | 2 |
| BUD-023 | 2 | 10 | Project detail budget section | 3 |
| BUD-024 | 2 | 10 | Dashboard overview card | 3 |
| BUD-025 | 3 | 6 | BudgetForecastService | 3 |
| BUD-026 | 3 | 7 | Forecast tests | 2 |
| BUD-027 | 3 | 7 | BudgetReportService | 5 |
| BUD-028 | 3 | 8 | Report API endpoint | 2 |
| BUD-029 | 3 | 10 | Budget report page | 5 |
| BUD-030 | 3 | 10 | Project modal budget config | 3 |
| BUD-031 | 3 | 11 | Frontend component tests | 3 |
| BUD-032 | 3 | 11 | E2E Playwright tests | 5 |
| BUD-033 | 2 | 8 | OpenAPI spec update | 2 |
| BUD-034 | 3 | 10 | Web route + nav for report page | 1 |

---

## Appendix B: File Creation/Modification Manifest by Sprint

### Sprint 0 Files
**Create**:
- `database/migrations/2026_02_28_000003_create_notifications_table.php`
- `database/migrations/2026_02_28_000004_add_notification_preferences_to_members.php`
- `app/Notifications/BaseNotification.php`
- `app/Http/Controllers/Api/V1/NotificationController.php`
- `resources/js/packages/ui/src/Notification/NotificationBell.vue`
- `app/Permissions/` directory (with refactored permission files)

**Modify**:
- `app/Providers/JetstreamServiceProvider.php`
- `resources/js/Layouts/AppLayout.vue`
- `routes/api.php`

### Sprint 1 Files
**Create**:
- `app/Enums/BudgetType.php`
- `app/Enums/BudgetPeriod.php`
- `database/migrations/2026_03_03_000001_add_budget_columns_to_projects_table.php`
- `database/migrations/2026_03_03_000002_create_budget_alerts_table.php`
- `app/Models/BudgetAlert.php`
- `database/factories/BudgetAlertFactory.php`
- `app/Service/BudgetService.php`
- `app/Http/Controllers/Api/V1/BudgetController.php`
- `app/Permissions/BudgetPermissions.php`
- `app/Http/Resources/V1/Budget/BudgetResource.php`
- `app/Http/Resources/V1/Budget/BudgetAlertResource.php`
- `tests/Unit/Service/BudgetServiceTest.php`
- `tests/Unit/Endpoint/Api/V1/BudgetEndpointTest.php`

**Modify**:
- `app/Models/Project.php`
- `app/Http/Controllers/Api/V1/ProjectController.php`
- `app/Http/Resources/V1/Project/ProjectResource.php`
- `app/Http/Requests/V1/Project/ProjectStoreRequest.php`
- `app/Http/Requests/V1/Project/ProjectUpdateRequest.php`
- `app/Providers/JetstreamServiceProvider.php`
- `routes/api.php`

### Sprint 2 Files
**Create**:
- `app/Service/BudgetAlertService.php`
- `app/Notifications/BudgetThresholdNotification.php`
- `app/Notifications/BudgetExceededNotification.php`
- `app/Jobs/SendBudgetAlertNotification.php`
- `app/Observers/TimeEntryObserver.php`
- `app/Console/Commands/Budget/BudgetAlertResetMonthlyCommand.php`
- `tests/Unit/Service/BudgetAlertServiceTest.php`
- `resources/js/types/budget.d.ts`
- `resources/js/utils/useBudget.ts`
- `resources/js/packages/ui/src/Budget/BudgetProgress.vue`
- `resources/js/packages/ui/src/Dashboard/BudgetOverviewCard.vue`

**Modify**:
- `app/Console/Kernel.php`
- `app/Providers/EventServiceProvider.php`
- `app/Jobs/RecalculateSpentTimeForProject.php`
- `app/Http/Controllers/Api/V1/ChartController.php` (or create `BudgetChartController.php`)
- `resources/js/Pages/Dashboard.vue`
- `resources/js/Pages/ProjectDetail.vue` (or equivalent)
- `resources/js/Components/Common/Project/ProjectTableRow.vue`
- `resources/js/types/project.d.ts`

### Sprint 3 Files
**Create**:
- `app/Service/BudgetForecastService.php`
- `app/Service/BudgetReportService.php`
- `app/Http/Requests/V1/Budget/BudgetReportRequest.php`
- `tests/Unit/Service/BudgetForecastServiceTest.php`
- `tests/Unit/Service/BudgetReportServiceTest.php`
- `resources/js/Pages/BudgetReport.vue`
- `e2e/budget-workflow.spec.ts`

**Modify**:
- `resources/js/Components/Common/Project/ProjectEditModal.vue`
- `resources/js/Components/Common/Project/ProjectCreateModal.vue`
- `resources/js/Layouts/AppLayout.vue`
- `routes/web.php`

---

Last updated: 2026-02-06
