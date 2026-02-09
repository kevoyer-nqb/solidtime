# Feature 01: Timesheet Approvals

## Branch
`feature/timesheet-approvals`

## Task Prefix
`APPR-` (APPR-001 through APPR-030)

## Migration Date Prefix
`2026_03_01_`

## Execution Phase
Phase 1b (parallel with 02-Expenses, 03-Budgets)

## Sprint Summary
| Sprint | Focus | Story Points |
|--------|-------|-------------|
| Sprint 1 | Data model, approval status machine, core service | ~30 SP |
| Sprint 2 | API endpoints, permission checks, bulk operations | ~28 SP |
| Sprint 3 | Frontend approval UI, submission flow, reviewer dashboard | ~30 SP |
| Sprint 4 | Notifications, email digests, reminder automation | ~24 SP |
| Sprint 5 | Reporting integration, E2E tests, polish | ~24 SP |

**Total**: ~136 SP / ~207h across 5 sprints (10 weeks)

## Shared Foundation Dependencies
- **FOUND-001..005**: Notification infrastructure (required for Sprint 4)
- **FOUND-007**: Modular permissions via `CorePermissions` (required for Sprint 2)
- **SF-05**: `ApprovalStatus` enum + `HasApprovalWorkflow` trait (already on main)

## Key Architecture Decisions
- Approval is per-week per-member (not per-entry)
- `TimesheetApproval` model with `HasApprovalWorkflow` trait
- Status machine: Draft → Submitted → Approved/ChangesRequested/Rejected → Withdrawn
- Permissions: `timesheet-approvals:{action}:{scope}` registered via `TimesheetApprovalPermissions::register()`
- Approval locks time entries for the approved period

## New Files to Create
- `app/Models/TimesheetApproval.php`
- `app/Service/TimesheetApprovalService.php`
- `app/Http/Controllers/Api/V1/TimesheetApprovalController.php`
- `app/Http/Requests/V1/TimesheetApproval/*.php`
- `app/Permissions/TimesheetApprovalPermissions.php`
- `app/Notifications/TimesheetApproval*.php`
- `resources/js/packages/ui/src/TimesheetApproval/*.vue`
- `resources/js/utils/useTimesheetApproval.ts`
- `tests/Unit/Endpoint/Api/V1/TimesheetApprovalEndpointTest.php`
- `tests/Unit/Service/TimesheetApprovalServiceTest.php`

## Files to Modify
- `app/Providers/JetstreamServiceProvider.php` (register permissions)
- `routes/api.php` (add approval routes)
- `resources/js/Layouts/AppLayout.vue` (add nav item if needed)

## Quality Gates
- [ ] `composer fix && composer analyse` passes
- [ ] `npm run lint:fix && npm run format` passes
- [ ] All new endpoints have API tests
- [ ] Service layer has unit tests
- [ ] Frontend components have Vitest tests
- [ ] E2E tests cover approval workflow

## Planning Docs
- `PRD.md` — Product requirements
- `task_assignments_20260206.md` — Task breakdown
- `ARCHITECTURE.md` — Technical architecture
- `CODEBASE-ANALYSIS.md` — Integration points
- `SPRINT-PLAN.md` — Sprint-by-sprint implementation plan
