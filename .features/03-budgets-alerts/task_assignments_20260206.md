# Task Assignments: Budgets & Alerts Feature

Generated: 2026-02-06
PRD Reference: `/home/keven/Documents/solidtime-analysis/.features/03-budgets-alerts/PRD.md`

---

## Task Assignment Table

| Task ID   | Description                                         | Type                | Assigned Sub-Agent  | Dependencies          | Effort  | Status |
|-----------|-----------------------------------------------------|---------------------|---------------------|-----------------------|---------|--------|
| BUD-001  | Create BudgetType and BudgetPeriod Enums            | Backend             | Backend Dev         | None                  | 2h (1 SP)  | To Do  |
| BUD-002  | Migration: Add budget columns to projects table     | Database / Backend  | Backend Dev         | BUD-001              | 4h (2 SP)  | To Do  |
| BUD-003  | Migration: Create budget_alerts table               | Database / Backend  | Backend Dev         | BUD-002              | 4h (2 SP)  | To Do  |
| BUD-004  | Create BudgetAlert Model + Factory                  | Backend             | Backend Dev         | BUD-003              | 3h (2 SP)  | To Do  |
| BUD-005  | Extend Project Model with budget fields             | Backend             | Backend Dev         | BUD-001, BUD-002, BUD-004 | 3h (2 SP)  | To Do  |
| BUD-006  | Create BudgetService (consumption calculation)      | Backend             | Backend Dev         | BUD-005              | 8h (5 SP)  | To Do  |
| BUD-007  | Extend ProjectStoreRequest + ProjectUpdateRequest   | Backend             | Backend Dev         | BUD-001              | 4h (2 SP)  | To Do  |
| BUD-008  | Extend ProjectController + Create BudgetController  | Backend             | Backend Dev         | BUD-005, BUD-006, BUD-007 | 6h (3 SP)  | To Do  |
| BUD-009  | Extend ProjectResource with budget data             | Backend             | Backend Dev         | BUD-006, BUD-008    | 3h (2 SP)  | To Do  |
| BUD-010  | Register budget permissions in JetstreamServiceProvider | Backend         | Backend Dev         | None                  | 2h (1 SP)  | To Do  |
| BUD-011  | Add API routes for budget endpoints                 | Backend             | Backend Dev         | BUD-008              | 2h (1 SP)  | To Do  |
| BUD-012  | BudgetService unit tests                            | Testing             | Backend Dev / QA    | BUD-006              | 8h (5 SP)  | To Do  |
| BUD-013  | Budget endpoint tests                               | Testing             | Backend Dev / QA    | BUD-008, BUD-011    | 8h (5 SP)  | To Do  |
| BUD-014  | Create BudgetAlertService                           | Backend             | Backend Dev         | BUD-004, BUD-006    | 8h (5 SP)  | To Do  |
| BUD-015  | Create Budget Notification + Job classes            | Backend             | Backend Dev         | BUD-014              | 4h (2 SP)  | To Do  |
| BUD-016  | Integrate alert evaluation with TimeEntryService    | Backend             | Backend Dev         | BUD-014              | 4h (2 SP)  | To Do  |
| BUD-017  | Monthly alert reset scheduled command               | Backend             | Backend Dev         | BUD-014              | 3h (2 SP)  | To Do  |
| BUD-018  | BudgetAlertService unit tests                       | Testing             | Backend Dev / QA    | BUD-014, BUD-016    | 6h (3 SP)  | To Do  |
| BUD-019  | Budget dashboard chart API endpoint                 | Backend             | Backend Dev         | BUD-006              | 4h (2 SP)  | To Do  |
| BUD-020  | TypeScript types for budget API                     | Frontend            | Frontend Dev        | BUD-009              | 3h (2 SP)  | To Do  |
| BUD-021  | Budget Pinia store (useBudgets.ts)                  | Frontend            | Frontend Dev        | BUD-020              | 6h (3 SP)  | To Do  |
| BUD-022  | BudgetProgressBar Vue component                     | Frontend            | Frontend Dev        | BUD-020              | 4h (2 SP)  | To Do  |
| BUD-023  | Budget section on ProjectShow page                  | Frontend            | Frontend Dev        | BUD-021, BUD-022    | 6h (3 SP)  | To Do  |
| BUD-024  | Budget overview dashboard card                      | Frontend            | Frontend Dev        | BUD-019, BUD-021, BUD-022 | 6h (3 SP)  | To Do  |
| BUD-025  | Create BudgetForecastService                        | Backend             | Backend Dev         | BUD-006              | 6h (3 SP)  | To Do  |
| BUD-026  | BudgetForecastService unit tests                    | Testing             | Backend Dev / QA    | BUD-025              | 4h (2 SP)  | To Do  |
| BUD-027  | Create BudgetReportService                          | Backend             | Backend Dev         | BUD-006, BUD-025    | 8h (5 SP)  | To Do  |
| BUD-028  | Budget report API endpoint + request validation     | Backend             | Backend Dev         | BUD-027              | 4h (2 SP)  | To Do  |
| BUD-029  | Budget report frontend page                         | Frontend            | Frontend Dev        | BUD-020, BUD-021, BUD-028 | 8h (5 SP)  | To Do  |
| BUD-030  | Budget config in Project Create/Edit modals         | Frontend            | Frontend Dev        | BUD-020, BUD-021    | 6h (3 SP)  | To Do  |
| BUD-031  | Frontend component tests (Vitest)                   | Testing             | Frontend Dev / QA   | BUD-022, BUD-023, BUD-024 | 6h (3 SP)  | To Do  |
| BUD-032  | E2E Playwright tests for budget workflows           | Testing             | QA                  | BUD-029, BUD-030    | 8h (5 SP)  | To Do  |
| BUD-033  | Update OpenAPI spec + regenerate TS client          | Tooling             | Backend Dev         | BUD-008, BUD-009, BUD-011 | 3h (2 SP)  | To Do  |
| BUD-034  | Register Inertia web route for Budget Report page in `routes/web.php`, add `NavigationSidebarItem` in `AppLayout.vue` | Fullstack           | Backend Dev         | BUD-029                   | 1h (1 SP)  | To Do  |

---

## Summary

| Category           | Tasks | Total Effort  | Story Points |
|--------------------|-------|---------------|-------------|
| Backend            | 19    | 79h           | 44 SP       |
| Frontend           | 7     | 39h           | 21 SP       |
| Testing            | 6     | 40h           | 21 SP       |
| Tooling            | 1     | 3h            | 2 SP        |
| Fullstack          | 1     | 1h            | 1 SP        |
| **Total**          | **34**| **~166h**     | **89 SP**   |

**Note**: Testing tasks overlap with Backend/Frontend categories; some developers will handle both implementation and tests.

---

## Sprint Allocation

### Sprint 1 (Weeks 1-2) - Foundation
| Task IDs | Focus |
|---|---|
| BUD-001 through BUD-013, BUD-010 | Data model, CRUD, consumption service, API, tests |

### Sprint 2 (Weeks 3-4) - Alerts & Dashboard
| Task IDs | Focus |
|---|---|
| BUD-014 through BUD-024, BUD-033 | Alert system, notifications, frontend core, dashboard |

### Sprint 3 (Weeks 5-6) - Forecasting, Reports & Polish
| Task IDs | Focus |
|---|---|
| BUD-025 through BUD-032, BUD-034 | Forecasting, reports page, web route + nav, project modal budget config, E2E tests |

---

## Execution Order (Dependency-Respecting)

The following is the recommended execution order considering all dependencies:

**Wave 1 (No dependencies - can start immediately):**
- BUD-001 (Enums)
- BUD-010 (Permissions)

**Wave 2 (Depends on Wave 1):**
- BUD-002 (Projects migration) -- depends on BUD-001
- BUD-007 (Request validation) -- depends on BUD-001

**Wave 3 (Depends on Wave 2):**
- BUD-003 (Alerts migration) -- depends on BUD-002

**Wave 4 (Depends on Wave 3):**
- BUD-004 (BudgetAlert model) -- depends on BUD-003
- BUD-005 (Extend Project model) -- depends on BUD-001, BUD-002, BUD-004

**Wave 5 (Depends on Wave 4):**
- BUD-006 (BudgetService) -- depends on BUD-005

**Wave 6 (Depends on Wave 5):**
- BUD-008 (Controllers) -- depends on BUD-005, BUD-006, BUD-007
- BUD-012 (BudgetService tests) -- depends on BUD-006
- BUD-014 (BudgetAlertService) -- depends on BUD-004, BUD-006
- BUD-019 (Chart endpoint) -- depends on BUD-006
- BUD-025 (ForecastService) -- depends on BUD-006

**Wave 7 (Depends on Wave 6):**
- BUD-009 (ProjectResource) -- depends on BUD-006, BUD-008
- BUD-011 (Routes) -- depends on BUD-008
- BUD-015 (Notifications) -- depends on BUD-014
- BUD-016 (TimeEntry integration) -- depends on BUD-014
- BUD-017 (Monthly reset) -- depends on BUD-014
- BUD-026 (Forecast tests) -- depends on BUD-025
- BUD-027 (ReportService) -- depends on BUD-006, BUD-025

**Wave 8 (Depends on Wave 7):**
- BUD-013 (Endpoint tests) -- depends on BUD-008, BUD-011
- BUD-018 (Alert tests) -- depends on BUD-014, BUD-016
- BUD-020 (TS types) -- depends on BUD-009
- BUD-028 (Report endpoint) -- depends on BUD-027
- BUD-033 (OpenAPI) -- depends on BUD-008, BUD-009, BUD-011

**Wave 9 (Depends on Wave 8):**
- BUD-021 (Pinia store) -- depends on BUD-020
- BUD-022 (Progress bar) -- depends on BUD-020

**Wave 10 (Depends on Wave 9):**
- BUD-023 (Project page budget) -- depends on BUD-021, BUD-022
- BUD-024 (Dashboard card) -- depends on BUD-019, BUD-021, BUD-022
- BUD-029 (Report page) -- depends on BUD-020, BUD-021, BUD-028
- BUD-030 (Project modal config) -- depends on BUD-020, BUD-021

**Wave 11 (Depends on Wave 10):**
- BUD-031 (Component tests) -- depends on BUD-022, BUD-023, BUD-024
- BUD-032 (E2E tests) -- depends on BUD-029, BUD-030

---

## Dependency Risk Assessment

| Risk | Affected Tasks | Mitigation |
|---|---|---|
| BUD-006 (BudgetService) is a bottleneck: 8 downstream tasks depend on it | BUD-008, BUD-009, BUD-012, BUD-014, BUD-019, BUD-025, BUD-027 | Prioritize BUD-006 completion. Frontend devs can work on BUD-020 type definitions from API contract spec (not runtime) while BUD-006 is in progress. |
| BUD-014 (BudgetAlertService) blocks multiple Sprint 2 tasks | BUD-015, BUD-016, BUD-017, BUD-018 | Assign strongest backend developer. Start with core evaluation logic, then parallelize notification and integration work. |
| BUD-008 (Controllers) has 3 upstream dependencies | Blocked until BUD-005, BUD-006, BUD-007 all complete | BUD-007 (validation) can be done in parallel with BUD-005/006 since it only depends on BUD-001. |
| Frontend cannot start until BUD-009 (ProjectResource) is done | BUD-020 through BUD-024 | Frontend dev can start on component scaffolding and mock data while waiting. BUD-020 (types) can be written from API contract documentation before backend is complete. |

---

## Status Assignment Notes

All tasks are currently assigned **To Do** status. Status transitions:

- **To Do** -> **In Progress**: When a developer starts work on the task and all dependencies are either In Progress or Completed.
- **In Progress** -> **Completed**: When all acceptance criteria are met and code is merged.
- **To Do** -> **Blocked**: Only if an external constraint prevents progress (e.g., third-party API unavailability). Dependency on another To Do task does NOT constitute Blocked status.
