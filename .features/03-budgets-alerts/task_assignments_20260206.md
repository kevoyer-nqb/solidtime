# Task Assignments: Budgets & Alerts Feature

Generated: 2026-02-06
PRD Reference: `/home/keven/Documents/solidtime-analysis/.features/03-budgets-alerts/PRD.md`

---

## Task Assignment Table

| Task ID   | Description                                         | Type                | Assigned Sub-Agent  | Dependencies          | Effort  | Status |
|-----------|-----------------------------------------------------|---------------------|---------------------|-----------------------|---------|--------|
| TASK-001  | Create BudgetType and BudgetPeriod Enums            | Backend             | Backend Dev         | None                  | 2h (1 SP)  | To Do  |
| TASK-002  | Migration: Add budget columns to projects table     | Database / Backend  | Backend Dev         | TASK-001              | 4h (2 SP)  | To Do  |
| TASK-003  | Migration: Create budget_alerts table               | Database / Backend  | Backend Dev         | TASK-002              | 4h (2 SP)  | To Do  |
| TASK-004  | Create BudgetAlert Model + Factory                  | Backend             | Backend Dev         | TASK-003              | 3h (2 SP)  | To Do  |
| TASK-005  | Extend Project Model with budget fields             | Backend             | Backend Dev         | TASK-001, TASK-002, TASK-004 | 3h (2 SP)  | To Do  |
| TASK-006  | Create BudgetService (consumption calculation)      | Backend             | Backend Dev         | TASK-005              | 8h (5 SP)  | To Do  |
| TASK-007  | Extend ProjectStoreRequest + ProjectUpdateRequest   | Backend             | Backend Dev         | TASK-001              | 4h (2 SP)  | To Do  |
| TASK-008  | Extend ProjectController + Create BudgetController  | Backend             | Backend Dev         | TASK-005, TASK-006, TASK-007 | 6h (3 SP)  | To Do  |
| TASK-009  | Extend ProjectResource with budget data             | Backend             | Backend Dev         | TASK-006, TASK-008    | 3h (2 SP)  | To Do  |
| TASK-010  | Register budget permissions in JetstreamServiceProvider | Backend         | Backend Dev         | None                  | 2h (1 SP)  | To Do  |
| TASK-011  | Add API routes for budget endpoints                 | Backend             | Backend Dev         | TASK-008              | 2h (1 SP)  | To Do  |
| TASK-012  | BudgetService unit tests                            | Testing             | Backend Dev / QA    | TASK-006              | 8h (5 SP)  | To Do  |
| TASK-013  | Budget endpoint tests                               | Testing             | Backend Dev / QA    | TASK-008, TASK-011    | 8h (5 SP)  | To Do  |
| TASK-014  | Create BudgetAlertService                           | Backend             | Backend Dev         | TASK-004, TASK-006    | 8h (5 SP)  | To Do  |
| TASK-015  | Create Budget Notification + Job classes            | Backend             | Backend Dev         | TASK-014              | 4h (2 SP)  | To Do  |
| TASK-016  | Integrate alert evaluation with TimeEntryService    | Backend             | Backend Dev         | TASK-014              | 4h (2 SP)  | To Do  |
| TASK-017  | Monthly alert reset scheduled command               | Backend             | Backend Dev         | TASK-014              | 3h (2 SP)  | To Do  |
| TASK-018  | BudgetAlertService unit tests                       | Testing             | Backend Dev / QA    | TASK-014, TASK-016    | 6h (3 SP)  | To Do  |
| TASK-019  | Budget dashboard chart API endpoint                 | Backend             | Backend Dev         | TASK-006              | 4h (2 SP)  | To Do  |
| TASK-020  | TypeScript types for budget API                     | Frontend            | Frontend Dev        | TASK-009              | 3h (2 SP)  | To Do  |
| TASK-021  | Budget Pinia store (useBudgets.ts)                  | Frontend            | Frontend Dev        | TASK-020              | 6h (3 SP)  | To Do  |
| TASK-022  | BudgetProgressBar Vue component                     | Frontend            | Frontend Dev        | TASK-020              | 4h (2 SP)  | To Do  |
| TASK-023  | Budget section on ProjectShow page                  | Frontend            | Frontend Dev        | TASK-021, TASK-022    | 6h (3 SP)  | To Do  |
| TASK-024  | Budget overview dashboard card                      | Frontend            | Frontend Dev        | TASK-019, TASK-021, TASK-022 | 6h (3 SP)  | To Do  |
| TASK-025  | Create BudgetForecastService                        | Backend             | Backend Dev         | TASK-006              | 6h (3 SP)  | To Do  |
| TASK-026  | BudgetForecastService unit tests                    | Testing             | Backend Dev / QA    | TASK-025              | 4h (2 SP)  | To Do  |
| TASK-027  | Create BudgetReportService                          | Backend             | Backend Dev         | TASK-006, TASK-025    | 8h (5 SP)  | To Do  |
| TASK-028  | Budget report API endpoint + request validation     | Backend             | Backend Dev         | TASK-027              | 4h (2 SP)  | To Do  |
| TASK-029  | Budget report frontend page                         | Frontend            | Frontend Dev        | TASK-020, TASK-021, TASK-028 | 8h (5 SP)  | To Do  |
| TASK-030  | Budget config in Project Create/Edit modals         | Frontend            | Frontend Dev        | TASK-020, TASK-021    | 6h (3 SP)  | To Do  |
| TASK-031  | Frontend component tests (Vitest)                   | Testing             | Frontend Dev / QA   | TASK-022, TASK-023, TASK-024 | 6h (3 SP)  | To Do  |
| TASK-032  | E2E Playwright tests for budget workflows           | Testing             | QA                  | TASK-029, TASK-030    | 8h (5 SP)  | To Do  |
| TASK-033  | Update OpenAPI spec + regenerate TS client          | Tooling             | Backend Dev         | TASK-008, TASK-009, TASK-011 | 3h (2 SP)  | To Do  |

---

## Summary

| Category           | Tasks | Total Effort  | Story Points |
|--------------------|-------|---------------|-------------|
| Backend            | 19    | 79h           | 44 SP       |
| Frontend           | 7     | 39h           | 21 SP       |
| Testing            | 6     | 40h           | 21 SP       |
| Tooling            | 1     | 3h            | 2 SP        |
| **Total**          | **33**| **~164h**     | **88 SP**   |

**Note**: Testing tasks overlap with Backend/Frontend categories; some developers will handle both implementation and tests.

---

## Sprint Allocation

### Sprint 1 (Weeks 1-2) - Foundation
| Task IDs | Focus |
|---|---|
| TASK-001 through TASK-013, TASK-010 | Data model, CRUD, consumption service, API, tests |

### Sprint 2 (Weeks 3-4) - Alerts & Dashboard
| Task IDs | Focus |
|---|---|
| TASK-014 through TASK-024, TASK-033 | Alert system, notifications, frontend core, dashboard |

### Sprint 3 (Weeks 5-6) - Forecasting, Reports & Polish
| Task IDs | Focus |
|---|---|
| TASK-025 through TASK-032 | Forecasting, reports page, project modal budget config, E2E tests |

---

## Execution Order (Dependency-Respecting)

The following is the recommended execution order considering all dependencies:

**Wave 1 (No dependencies - can start immediately):**
- TASK-001 (Enums)
- TASK-010 (Permissions)

**Wave 2 (Depends on Wave 1):**
- TASK-002 (Projects migration) -- depends on TASK-001
- TASK-007 (Request validation) -- depends on TASK-001

**Wave 3 (Depends on Wave 2):**
- TASK-003 (Alerts migration) -- depends on TASK-002

**Wave 4 (Depends on Wave 3):**
- TASK-004 (BudgetAlert model) -- depends on TASK-003
- TASK-005 (Extend Project model) -- depends on TASK-001, TASK-002, TASK-004

**Wave 5 (Depends on Wave 4):**
- TASK-006 (BudgetService) -- depends on TASK-005

**Wave 6 (Depends on Wave 5):**
- TASK-008 (Controllers) -- depends on TASK-005, TASK-006, TASK-007
- TASK-012 (BudgetService tests) -- depends on TASK-006
- TASK-014 (BudgetAlertService) -- depends on TASK-004, TASK-006
- TASK-019 (Chart endpoint) -- depends on TASK-006
- TASK-025 (ForecastService) -- depends on TASK-006

**Wave 7 (Depends on Wave 6):**
- TASK-009 (ProjectResource) -- depends on TASK-006, TASK-008
- TASK-011 (Routes) -- depends on TASK-008
- TASK-015 (Notifications) -- depends on TASK-014
- TASK-016 (TimeEntry integration) -- depends on TASK-014
- TASK-017 (Monthly reset) -- depends on TASK-014
- TASK-026 (Forecast tests) -- depends on TASK-025
- TASK-027 (ReportService) -- depends on TASK-006, TASK-025

**Wave 8 (Depends on Wave 7):**
- TASK-013 (Endpoint tests) -- depends on TASK-008, TASK-011
- TASK-018 (Alert tests) -- depends on TASK-014, TASK-016
- TASK-020 (TS types) -- depends on TASK-009
- TASK-028 (Report endpoint) -- depends on TASK-027
- TASK-033 (OpenAPI) -- depends on TASK-008, TASK-009, TASK-011

**Wave 9 (Depends on Wave 8):**
- TASK-021 (Pinia store) -- depends on TASK-020
- TASK-022 (Progress bar) -- depends on TASK-020

**Wave 10 (Depends on Wave 9):**
- TASK-023 (Project page budget) -- depends on TASK-021, TASK-022
- TASK-024 (Dashboard card) -- depends on TASK-019, TASK-021, TASK-022
- TASK-029 (Report page) -- depends on TASK-020, TASK-021, TASK-028
- TASK-030 (Project modal config) -- depends on TASK-020, TASK-021

**Wave 11 (Depends on Wave 10):**
- TASK-031 (Component tests) -- depends on TASK-022, TASK-023, TASK-024
- TASK-032 (E2E tests) -- depends on TASK-029, TASK-030

---

## Dependency Risk Assessment

| Risk | Affected Tasks | Mitigation |
|---|---|---|
| TASK-006 (BudgetService) is a bottleneck: 8 downstream tasks depend on it | TASK-008, TASK-009, TASK-012, TASK-014, TASK-019, TASK-025, TASK-027 | Prioritize TASK-006 completion. Frontend devs can work on TASK-020 type definitions from API contract spec (not runtime) while TASK-006 is in progress. |
| TASK-014 (BudgetAlertService) blocks multiple Sprint 2 tasks | TASK-015, TASK-016, TASK-017, TASK-018 | Assign strongest backend developer. Start with core evaluation logic, then parallelize notification and integration work. |
| TASK-008 (Controllers) has 3 upstream dependencies | Blocked until TASK-005, TASK-006, TASK-007 all complete | TASK-007 (validation) can be done in parallel with TASK-005/006 since it only depends on TASK-001. |
| Frontend cannot start until TASK-009 (ProjectResource) is done | TASK-020 through TASK-024 | Frontend dev can start on component scaffolding and mock data while waiting. TASK-020 (types) can be written from API contract documentation before backend is complete. |

---

## Status Assignment Notes

All tasks are currently assigned **To Do** status. Status transitions:

- **To Do** -> **In Progress**: When a developer starts work on the task and all dependencies are either In Progress or Completed.
- **In Progress** -> **Completed**: When all acceptance criteria are met and code is merged.
- **To Do** -> **Blocked**: Only if an external constraint prevents progress (e.g., third-party API unavailability). Dependency on another To Do task does NOT constitute Blocked status.
