# Feature 02: Expense Management

## Branch
`feature/expense-management`

## Task Prefix
`EXP-` (EXP-001 through EXP-030)

## Migration Date Prefix
`2026_03_02_`

## Execution Phase
Phase 1b (parallel with 01-Approvals, 03-Budgets)

## Sprint Summary
| Sprint | Focus | Story Points |
|--------|-------|-------------|
| Sprint 0 | Shared foundations (if not yet done) | ~14 SP |
| Sprint 1 | Data model, file upload, core CRUD | ~35 SP |
| Sprint 2 | Approval workflow, policies, receipt management | ~30 SP |
| Sprint 3 | Frontend UI, reporting, export, E2E tests | ~33 SP |

**Total**: ~112 SP / ~224h across 4 sprints (8 weeks)

## Shared Foundation Dependencies
- **FOUND-001..005**: Notification infrastructure (required for Sprint 2)
- **FOUND-007**: Modular permissions (required for Sprint 1)
- **SF-05**: `ApprovalStatus` enum + `HasApprovalWorkflow` trait (already on main)

## Key Architecture Decisions
- `Expense` model with `HasApprovalWorkflow` trait
- File storage via Laravel's filesystem (S3/local configurable)
- `ExpenseCategory` model for categorization
- Expense policies tied to organization settings
- Permissions: `expenses:{action}:{scope}` via `ExpensePermissions::register()`
- Receipt uploads stored in `storage/expenses/{org_id}/{expense_id}/`

## New Files to Create
- `app/Models/Expense.php`
- `app/Models/ExpenseCategory.php`
- `app/Service/ExpenseService.php`
- `app/Http/Controllers/Api/V1/ExpenseController.php`
- `app/Http/Controllers/Api/V1/ExpenseCategoryController.php`
- `app/Http/Requests/V1/Expense/*.php`
- `app/Permissions/ExpensePermissions.php`
- `app/Notifications/Expense*.php`
- `resources/js/packages/ui/src/Expense/*.vue`
- `resources/js/utils/useExpense.ts`
- `tests/Unit/Endpoint/Api/V1/ExpenseEndpointTest.php`
- `tests/Unit/Service/ExpenseServiceTest.php`

## Files to Modify
- `app/Providers/JetstreamServiceProvider.php` (register permissions)
- `routes/api.php` (add expense routes)
- `resources/js/Layouts/AppLayout.vue` (add nav item)

## Quality Gates
- [ ] `composer fix && composer analyse` passes
- [ ] `npm run lint:fix && npm run format` passes
- [ ] All new endpoints have API tests
- [ ] Service layer has unit tests
- [ ] File upload tested with various file types
- [ ] E2E tests cover expense submission and approval

## Planning Docs
- `PRD.md` — Product requirements
- `task_assignments_20260206.md` — Task breakdown
- `ARCHITECTURE.md` — Technical architecture
- `CODEBASE-ANALYSIS.md` — Integration points
- `SPRINT-PLAN.md` — Sprint-by-sprint implementation plan
