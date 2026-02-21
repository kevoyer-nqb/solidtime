# Feature 07: PTO & Time Off

## Branch
`feature/pto-time-off`

## Task Prefix
`PTO-` (PTO-001 through PTO-033)

## Migration Date Prefix
`2026_03_07_`

## Execution Phase
Phase 2a (parallel with 04-Invoicing)

## Sprint Summary
| Sprint | Focus | Story Points |
|--------|-------|-------------|
| Sprint 1 | Leave types, policies, entitlement model, core service | ~33 SP |
| Sprint 2 | Request workflow, approval, balance calculations | ~33 SP |
| Sprint 3 | Calendar integration, team view, conflict detection | ~33 SP |
| Sprint 4 | Notifications, accrual engine, reporting, E2E tests | ~33 SP |

**Total**: ~132 SP / ~260h across 4 sprints (8 weeks)

## Shared Foundation Dependencies
- **FOUND-001..005**: Notification infrastructure (required for Sprint 2 approval flow)
- **FOUND-006**: `weekly_capacity` on members (already on main)
- **FOUND-007**: Modular permissions (required for Sprint 1)
- **SF-05**: `ApprovalStatus` enum + `HasApprovalWorkflow` trait (already on main)

## Key Architecture Decisions
- `LeaveType` model (vacation, sick, personal, etc.)
- `LeavePolicy` model tied to organization with accrual rules
- `LeaveEntitlement` model per member per leave type per year
- `LeaveRequest` model with `HasApprovalWorkflow` trait
- Balance = entitlement - approved requests + accruals
- Accrual engine runs on schedule (monthly/bi-weekly)
- Calendar integration shows leave alongside time entries
- Permissions: `pto:{action}:{scope}` via `PtoPermissions::register()`

## New Files to Create
- `app/Models/LeaveType.php`
- `app/Models/LeavePolicy.php`
- `app/Models/LeaveEntitlement.php`
- `app/Models/LeaveRequest.php`
- `app/Service/LeaveService.php`
- `app/Service/LeaveBalanceService.php`
- `app/Service/LeaveAccrualService.php`
- `app/Http/Controllers/Api/V1/LeaveController.php`
- `app/Http/Controllers/Api/V1/LeaveTypeController.php`
- `app/Http/Controllers/Api/V1/LeavePolicyController.php`
- `app/Http/Requests/V1/Leave/*.php`
- `app/Permissions/PtoPermissions.php`
- `app/Notifications/LeaveRequest*.php`
- `app/Console/Commands/ProcessLeaveAccruals.php`
- `resources/js/packages/ui/src/Leave/*.vue`
- `resources/js/utils/useLeave.ts`
- `tests/Unit/Endpoint/Api/V1/LeaveEndpointTest.php`
- `tests/Unit/Service/LeaveServiceTest.php`

## Files to Modify
- `app/Providers/JetstreamServiceProvider.php` (register permissions)
- `routes/api.php` (add leave routes)
- `app/Console/Kernel.php` (schedule accrual engine)
- `resources/js/Layouts/AppLayout.vue` (add nav item)
- `resources/js/Pages/Calendar.vue` (show leave on calendar)

## Quality Gates
- [ ] `composer fix && composer analyse` passes
- [ ] `npm run lint:fix && npm run format` passes
- [ ] Balance calculations have comprehensive unit tests
- [ ] Accrual engine tested with various policy configurations
- [ ] Approval workflow tested end-to-end
- [ ] E2E tests cover leave request lifecycle

## Planning Docs
- `PRD.md` — Product requirements
- `task_assignments_20260206.md` — Task breakdown
- `ARCHITECTURE.md` — Technical architecture
- `CODEBASE-ANALYSIS.md` — Integration points
- `SPRINT-PLAN.md` — Sprint-by-sprint implementation plan
