# Feature 12: Punch-Only / Time-Clock Mode

## Branch
`feature/punch-clock-mode`

## Task Prefix
`PCM-` (PCM-001 through PCM-024)

## Migration Date Prefix
`2026_03_12` (3 migrations)

## Execution Phase
Phase 2 -- independent feature. Does not depend on Feature 00 (Weekly Timesheet Grid) being merged first, but if Feature 00 is present, the guard middleware is applied to `PUT /timesheet/cell` and the readonly grid mode activates for restricted members.

## Sprint Summary
| Sprint | Focus | Story Points |
|--------|-------|-------------|
| Sprint 1 | Backend: Migrations, Enum, Models, Service, Controller, Guard, Routes, Validation, Permissions, OpenAPI | ~30 SP |
| Sprint 2 | Frontend: Pinia store, Punch button, Restricted view, Settings UI, Readonly grid, Source badge | ~22 SP |
| Sprint 3 | Testing: Endpoint tests, Service tests, Component tests, E2E tests, Source filter, JSDoc | ~25 SP |

**Total**: ~77 SP / ~119h across 3 sprints (6 weeks)

## Shared Foundation Dependencies
- FOUND-007: Modular permissions infrastructure (`app/Permissions/`). If not available, permissions are added directly to `JetstreamServiceProvider.php`.
- Soft dependency on Feature 00 (Weekly Timesheet Grid) for readonly grid mode. If Feature 00 is not present, the guard on `PUT /timesheet/cell` is not applied and the readonly grid components are not rendered.

## Key Architecture Decisions
- Three database migrations: `organizations.punch_clock_mode_enabled`, `members.is_punch_clock_restricted`, `time_entries.time_entry_source`
- New `TimeEntrySource` backed enum: `manual`, `timer`, `punch_clock`, `kiosk`, `timesheet_grid`
- New `PunchClockService` with `isRestricted()`, `punchIn()`, `punchOut()`, `getStatus()`
- New `PunchClockController` with 3 endpoints: `POST /punch-clock/in`, `POST /punch-clock/out`, `GET /punch-clock`
- New `PunchClockGuard` middleware applied to 6 existing write endpoints (time-entries CRUD + timesheet cell update)
- Guard checks the *acting user's* member record, NOT the entry owner -- managers can still edit restricted members' entries
- Owner and Admin roles are NEVER restricted regardless of the `is_punch_clock_restricted` flag
- Feature is inherently feature-flagged via `organization.punch_clock_mode_enabled` -- zero behavioral change when disabled
- Guard middleware fast-paths with zero additional DB queries when the org-level flag is disabled
- Punch-in creates a standard `TimeEntry` with `end = null` and `time_entry_source = punch_clock`
- Punch-out sets `end = now()` and dispatches recalculation jobs
- New permissions: `punch-clock:configure` (Owner/Admin), `punch-clock:view:all` (Owner/Admin/Manager)
- Reuses existing `time-entries:create:own`, `time-entries:update:own`, `time-entries:view:own` for punch endpoints

## New Files to Create
- `app/Enums/TimeEntrySource.php`
- `app/Http/Controllers/Api/V1/PunchClockController.php`
- `app/Http/Middleware/PunchClockGuard.php`
- `app/Http/Requests/V1/PunchClock/PunchInRequest.php`
- `app/Permissions/PunchClockPermissions.php`
- `app/Service/PunchClockService.php`
- `database/migrations/2026_03_12_000001_add_punch_clock_mode_to_organizations_table.php`
- `database/migrations/2026_03_12_000002_add_punch_clock_restriction_to_members_table.php`
- `database/migrations/2026_03_12_000003_add_source_to_time_entries_table.php`
- `resources/js/packages/ui/src/PunchClock/PunchClockButton.vue`
- `resources/js/packages/ui/src/PunchClock/PunchClockDuration.vue`
- `resources/js/packages/ui/src/PunchClock/PunchClockProjectSelector.vue`
- `resources/js/packages/ui/src/PunchClock/PunchClockRestrictedView.vue`
- `resources/js/packages/ui/src/PunchClock/PunchClockSettings.vue`
- `resources/js/packages/ui/src/PunchClock/PunchClockMemberList.vue`
- `resources/js/packages/ui/src/PunchClock/TimeEntrySourceBadge.vue`
- `resources/js/utils/usePunchClock.ts`
- `resources/js/types/punch-clock.d.ts`
- `tests/Unit/Endpoint/Api/V1/PunchClockEndpointTest.php`
- `tests/Unit/Service/PunchClockServiceTest.php`
- `resources/js/packages/ui/src/PunchClock/__tests__/PunchClockButton.test.ts`
- `resources/js/packages/ui/src/PunchClock/__tests__/PunchClockRestrictedView.test.ts`
- `resources/js/packages/ui/src/PunchClock/__tests__/PunchClockSettings.test.ts`
- `e2e/punch-clock.spec.ts`

## Files to Modify
- `app/Models/Organization.php` (add `punch_clock_mode_enabled` to `$casts`)
- `app/Models/Member.php` (add `is_punch_clock_restricted` to `$casts`)
- `app/Models/TimeEntry.php` (add `time_entry_source` to `$casts` and `SELECT_COLUMNS`)
- `app/Http/Controllers/Api/V1/TimeEntryController.php` (set `time_entry_source` in `store()`)
- `app/Http/Controllers/Api/V1/MemberController.php` (add restriction handling in `update()`)
- `app/Service/TimesheetService.php` (set `time_entry_source` in `updateCell()`)
- `app/Service/TimeEntryFilter.php` (add `addSourceFilter()` method)
- `app/Http/Resources/V1/TimeEntry/TimeEntryResource.php` (add `time_entry_source` to output)
- `app/Http/Requests/V1/Organization/OrganizationUpdateRequest.php` (add `punch_clock_mode_enabled` rule)
- `app/Http/Requests/V1/Member/MemberUpdateRequest.php` (add `is_punch_clock_restricted` rule with validation)
- `routes/api.php` (add punch-clock route group + guard middleware on 6 existing write routes)
- `openapi.json` (add 3 new endpoints + update TimeEntry, Organization, Member schemas)
- `resources/js/packages/api/src/openapi.json.client.ts` (regenerate from OpenAPI)
- `resources/js/packages/ui/src/Timesheet/TimesheetCell.vue` (add `readonly` prop)
- `resources/js/packages/ui/src/Timesheet/TimesheetGrid.vue` (pass `readonly` from store)
- `resources/js/packages/ui/src/Timesheet/TimesheetAddTask.vue` (hide when readonly)
- `resources/js/Pages/Time.vue` (conditional rendering for restricted vs normal view)
- `database/factories/OrganizationFactory.php` (add `punch_clock_mode_enabled` default)
- `database/factories/MemberFactory.php` (add `is_punch_clock_restricted` default)

## Quality Gates
- [ ] `composer fix && composer analyse` passes
- [ ] `npm run lint:fix && npm run format` passes
- [ ] `npm run build` succeeds
- [ ] 3 database migrations run and roll back cleanly
- [ ] All 3 punch-clock API endpoints have endpoint tests
- [ ] Guard middleware tested on all 6 existing write endpoints
- [ ] PunchClockService has unit tests for all methods
- [ ] Frontend components have Vitest tests
- [ ] E2E tests cover admin setup + member punch workflow
- [ ] Source tracking verified on TimeEntry, Timesheet, and PunchClock creation paths
- [ ] OpenAPI spec updated and TS client regenerated
- [ ] JSDoc comments on Pinia store methods and TypeScript types

## Planning Docs
- `PRD.md` -- Product requirements
- `task_assignments_20260209.md` -- Task breakdown
- `ARCHITECTURE.md` -- Technical architecture
- `CODEBASE-ANALYSIS.md` -- Integration points
- `SPRINT-PLAN.md` -- Sprint-by-sprint implementation plan
- `CLAUDE.md` -- This file (feature context)
