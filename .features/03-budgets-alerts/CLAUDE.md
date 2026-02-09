# Feature 03: Budgets & Alerts

## Branch
`feature/budgets-alerts`

## Task Prefix
`BUD-` (BUD-001 through BUD-034)

## Migration Date Prefix
`2026_03_03_`

## Execution Phase
Phase 1b (parallel with 01-Approvals, 02-Expenses)

## Sprint Summary
| Sprint | Focus | Story Points |
|--------|-------|-------------|
| Sprint 1 | Data model, core budget service, CRUD API, backend tests | ~33 SP |
| Sprint 2 | Alert engine, notifications, frontend core, dashboard widget | ~31 SP |
| Sprint 3 | Forecasting, reports page, project modal, E2E tests, docs | ~30 SP |

**Total**: ~108 SP / ~176h across 3 sprints (6 weeks + 1 week Phase 0)

## Shared Foundation Dependencies
- **FOUND-001..005**: Notification infrastructure (required for Sprint 2 alerts)
- **FOUND-007**: Modular permissions (required for Sprint 1)

## Key Architecture Decisions
- `Budget` model tied to project or organization level
- Budget types: time-based (hours) and cost-based (currency)
- `BudgetAlert` model for threshold-based notifications
- Alert engine runs on schedule (Laravel scheduler) + real-time on time entry save
- Forecasting uses linear regression on historical data
- Permissions: `budgets:{action}:{scope}` via `BudgetPermissions::register()`

## New Files to Create
- `app/Models/Budget.php`
- `app/Models/BudgetAlert.php`
- `app/Service/BudgetService.php`
- `app/Service/BudgetAlertService.php`
- `app/Service/BudgetForecastService.php`
- `app/Http/Controllers/Api/V1/BudgetController.php`
- `app/Http/Controllers/Api/V1/BudgetAlertController.php`
- `app/Http/Requests/V1/Budget/*.php`
- `app/Permissions/BudgetPermissions.php`
- `app/Notifications/BudgetAlert*.php`
- `app/Console/Commands/CheckBudgetAlerts.php`
- `resources/js/packages/ui/src/Budget/*.vue`
- `resources/js/utils/useBudget.ts`
- `tests/Unit/Endpoint/Api/V1/BudgetEndpointTest.php`
- `tests/Unit/Service/BudgetServiceTest.php`

## Files to Modify
- `app/Providers/JetstreamServiceProvider.php` (register permissions)
- `routes/api.php` (add budget routes)
- `app/Console/Kernel.php` (schedule alert checks)
- `resources/js/Layouts/AppLayout.vue` (add nav item if needed)

## Quality Gates
- [ ] `composer fix && composer analyse` passes
- [ ] `npm run lint:fix && npm run format` passes
- [ ] All new endpoints have API tests
- [ ] Alert threshold logic has unit tests
- [ ] Forecasting accuracy tested with fixtures
- [ ] E2E tests cover budget creation and alert triggers

## Planning Docs
- `PRD.md` — Product requirements
- `task_assignments_20260206.md` — Task breakdown
- `ARCHITECTURE.md` — Technical architecture
- `CODEBASE-ANALYSIS.md` — Integration points
- `SPRINT-PLAN.md` — Sprint-by-sprint implementation plan
