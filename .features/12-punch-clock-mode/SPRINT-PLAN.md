# Sprint Plan: Punch-Only / Time-Clock Mode

**Date**: 2026-02-09
**Feature**: 12 -- Punch-Only / Time-Clock Mode
**Branch**: `feature/punch-clock-mode` (from `main`)
**Task Prefix**: `PCM-`
**PRD Reference**: `.features/12-punch-clock-mode/PRD.md`
**Architecture Reference**: `.features/12-punch-clock-mode/ARCHITECTURE.md`

---

## 1. Executive Summary

The Punch-Only / Time-Clock Mode feature adds server-enforced time entry restrictions for designated members. When enabled by an admin, restricted members can only punch in and punch out -- they cannot create, edit, or delete time entries through any other mechanism. The feature also introduces time entry source tracking across all entry creation paths.

**Total effort estimate**: 119 hours

**Total story points**: ~79 SP

**Number of sprints**: **3 sprints** (6 weeks)

**Team size assumptions**:
- 1 Backend Developer (senior, ~30 productive hours/sprint)
- 1 Frontend Developer (senior, ~30 productive hours/sprint)
- Concurrent work where dependency graph allows

**Key constraints**:
- Three database migrations required (additive columns, no data transformation)
- Guard middleware must be thoroughly tested before deployment (affects 6 existing endpoints)
- Backend must be completed before frontend can consume APIs (PCM-012 is the bridge)
- Feature is inherently feature-flagged via `organization.punch_clock_mode_enabled`

---

## 2. Sprint Overview Table

| Sprint | Name | Duration | Story Points | Key Deliverables |
|--------|------|----------|:------------:|------------------|
| **1** | Backend Foundation | 2 weeks | ~30 SP | Migrations, Enum, Models, Service, Controller, Guard Middleware, Routes, Validation, Permissions, OpenAPI |
| **2** | Frontend Implementation | 2 weeks | ~22 SP | Pinia store, Punch button, Restricted view, Settings UI, Readonly grid, Source badge |
| **3** | Testing & Polish | 2 weeks | ~25 SP | Endpoint tests, Service tests, Component tests, E2E tests, Source filter, JSDoc |

**Total**: ~77 SP across 6 weeks (remaining 2 SP is buffer)

---

## 3. Dependency Map

### 3.1 Task Dependencies

```
Wave 1 (No dependencies -- Sprint 1 start):
    PCM-001 (Migrations, 4h)
    PCM-002 (Enum, 2h)
    PCM-010 (Permissions, 2h)

Wave 2 (after Wave 1):
    PCM-003 (Models, 2h)         <- PCM-001
    PCM-008 (Source tracking, 4h) <- PCM-002
    PCM-011 (Resource, 2h)       <- PCM-002

Wave 3 (after Wave 2):
    PCM-004 (Service, 8h)        <- PCM-001, PCM-002, PCM-003

Wave 4 (after Wave 3):
    PCM-005 (Controller, 6h)     <- PCM-004
    PCM-006 (Guard, 6h)          <- PCM-004

Wave 5 (after Wave 4):
    PCM-007 (Routes, 2h)         <- PCM-005
    PCM-009 (Validation, 3h)     <- PCM-005

Wave 6 (after Wave 5 -- Sprint 1 end / Sprint 2 start):
    PCM-012 (OpenAPI + TS client, 4h) <- PCM-005, PCM-007, PCM-011

Wave 7 (after Wave 6):
    PCM-013 (Pinia store, 6h)    <- PCM-012
    PCM-016 (Settings UI, 6h)    <- PCM-012
    PCM-018 (Source badge, 3h)   <- PCM-012

Wave 8 (after Wave 7):
    PCM-014 (Button component, 8h) <- PCM-013
    PCM-017 (Readonly grid, 4h)    <- PCM-013

Wave 9 (after Wave 8):
    PCM-015 (Restricted view, 6h) <- PCM-013, PCM-014

Wave 10 (after dependencies met -- Sprint 3):
    PCM-019 (Endpoint tests, 10h) <- PCM-005, PCM-006, PCM-007
    PCM-020 (Service tests, 6h)   <- PCM-004
    PCM-021 (Component tests, 6h) <- PCM-014, PCM-015, PCM-016
    PCM-022 (E2E tests, 8h)       <- PCM-015, PCM-016
    PCM-023 (Source filter, 4h)   <- PCM-002, PCM-008
    PCM-024 (JSDoc, 3h)          <- PCM-013, PCM-014
```

### 3.2 Critical Path

```
PCM-001 (4h) -> PCM-003 (2h) -> PCM-004 (8h) -> PCM-005 (6h) -> PCM-007 (2h) -> PCM-012 (4h) -> PCM-013 (6h) -> PCM-014 (8h) -> PCM-015 (6h) -> PCM-022 (8h)
```

**Critical path duration**: 54 hours of sequential work

### 3.3 Parallelism Opportunities

| Wave | Backend | Frontend | Can run in parallel? |
|------|---------|----------|:--------------------:|
| 1 | PCM-001, PCM-002, PCM-010 | -- | Yes (all three independent) |
| 2 | PCM-003, PCM-008, PCM-011 | -- | Yes (all three independent after Wave 1) |
| 3 | PCM-004 | -- | -- |
| 4 | PCM-005, PCM-006 | -- | Yes (both depend only on PCM-004) |
| 5 | PCM-007, PCM-009 | -- | Yes (both depend only on PCM-005) |
| 6 | PCM-012 | -- | -- |
| 7 | -- | PCM-013, PCM-016, PCM-018 | Yes (all depend only on PCM-012) |
| 8 | -- | PCM-014, PCM-017 | Yes (both depend only on PCM-013) |
| 9 | -- | PCM-015 | -- |
| 10 | PCM-019, PCM-020, PCM-023 | PCM-021, PCM-022, PCM-024 | Yes (all largely independent) |

---

## 4. Sprint Details

### Sprint 1 (Weeks 1-2): Backend Foundation

**Goal**: All backend infrastructure complete -- migrations, models, enum, service, controller, guard middleware, routes, validation, permissions, OpenAPI spec, and TS client regenerated.

| Task ID | Description | Assignee | SP | Dependencies | Day |
|---------|-------------|----------|:--:|--------------|:---:|
| PCM-001 | Database migrations (3 migrations) | Backend | 3 | None | 1 |
| PCM-002 | Create TimeEntrySource enum | Backend | 1 | None | 1 |
| PCM-010 | Create PunchClockPermissions class | Backend | 1 | None | 1 |
| PCM-003 | Update Organization/Member models (casts, factories) | Backend | 1 | PCM-001 | 2 |
| PCM-008 | Update existing controllers for source tracking | Backend | 3 | PCM-002 | 2 |
| PCM-011 | Update TimeEntryResource with source field | Backend | 1 | PCM-002 | 2 |
| PCM-004 | Create PunchClockService | Backend | 5 | PCM-001, PCM-002, PCM-003 | 3-4 |
| PCM-005 | Create PunchClockController | Backend | 4 | PCM-004 | 5 |
| PCM-006 | Create PunchClockGuard middleware | Backend | 4 | PCM-004 | 5-6 |
| PCM-007 | Register API routes | Backend | 1 | PCM-005 | 7 |
| PCM-009 | Request validation classes | Backend | 2 | PCM-005 | 7 |
| PCM-012 | Update OpenAPI spec + regenerate TS client | Backend | 3 | PCM-005, PCM-007, PCM-011 | 8 |

**Sprint 1 Total**: 29 SP / 45h

**Deliverables**:
- [ ] 3 database migrations running and rollback-safe
- [ ] `TimeEntrySource` enum with 5 values
- [ ] Organization model casts `punch_clock_mode_enabled`
- [ ] Member model casts `is_punch_clock_restricted`
- [ ] TimeEntry model casts `time_entry_source` with `SELECT_COLUMNS` updated
- [ ] `PunchClockService` with `isRestricted()`, `punchIn()`, `punchOut()`, `getStatus()`
- [ ] `PunchClockController` with 3 endpoints (punchIn, punchOut, status)
- [ ] `PunchClockGuard` middleware applied to 6 existing write routes
- [ ] `PunchInRequest` validation class
- [ ] `OrganizationUpdateRequest` accepts `punch_clock_mode_enabled`
- [ ] `MemberUpdateRequest` accepts `is_punch_clock_restricted`
- [ ] `PunchClockPermissions` registered
- [ ] `TimeEntryResource` includes `time_entry_source`
- [ ] Source tracking in `TimeEntryController::store()` and `TimesheetService::updateCell()`
- [ ] Routes registered with correct middleware
- [ ] OpenAPI spec updated, TypeScript client regenerated

**QA Gate**:
```bash
# Run migrations
php artisan migrate

# Run static analysis and formatting
composer fix && composer analyse

# Verify routes
php artisan route:list --name=punch-clock

# Quick manual API tests via curl or Postman
# - POST /punch-clock/in (as restricted member)
# - POST /punch-clock/out (as restricted member)
# - GET /punch-clock (as any member)
# - POST /time-entries (as restricted member -- expect 403)
```

---

### Sprint 2 (Weeks 3-4): Frontend Implementation

**Goal**: Complete frontend for punch-clock restricted view, organization settings, and readonly timesheet grid.

| Task ID | Description | Assignee | SP | Dependencies | Day |
|---------|-------------|----------|:--:|--------------|:---:|
| PCM-013 | Create usePunchClockStore + TypeScript types | Frontend | 4 | PCM-012 | 1-2 |
| PCM-016 | Create PunchClockSettings + PunchClockMemberList | Frontend | 4 | PCM-012 | 1-2 |
| PCM-018 | Create TimeEntrySourceBadge component | Frontend | 2 | PCM-012 | 1 |
| PCM-014 | Create PunchClockButton + Duration + ProjectSelector | Frontend | 5 | PCM-013 | 3-4 |
| PCM-017 | Add readonly mode to timesheet grid | Frontend | 3 | PCM-013 | 3 |
| PCM-015 | Create PunchClockRestrictedView + modify Time.vue | Frontend | 4 | PCM-013, PCM-014 | 5-6 |

**Sprint 2 Total**: 22 SP / 33h

**Deliverables**:
- [ ] `usePunchClockStore` Pinia store with `fetchStatus()`, `punchIn()`, `punchOut()`
- [ ] TypeScript type definitions (`punch-clock.d.ts`)
- [ ] `PunchClockButton` component with punch-in/punch-out states
- [ ] `PunchClockDuration` component with real-time running clock
- [ ] `PunchClockProjectSelector` component
- [ ] `PunchClockRestrictedView` component with info banner + readonly entry list
- [ ] `PunchClockSettings` section in organization settings
- [ ] `PunchClockMemberList` with per-member restriction toggles
- [ ] `TimeEntrySourceBadge` component with color-coded badges
- [ ] Readonly mode on timesheet grid cells for restricted members
- [ ] Conditional rendering in `Time.vue` (restricted vs normal view)

**QA Gate**:
```bash
# Frontend build check
npm run lint:fix && npm run format
npm run build

# Manual testing checklist:
# 1. Enable punch-clock mode in org settings
# 2. Restrict a member
# 3. Login as restricted member -- see punch UI
# 4. Punch in -- see running duration
# 5. Punch out -- see completed entry
# 6. Verify manual entry form is hidden
# 7. Verify timesheet grid is readonly
# 8. Verify source badge on time entries
# 9. Login as manager -- verify can still edit restricted member's entries
# 10. Disable punch-clock mode -- verify normal UI returns
```

---

### Sprint 3 (Weeks 5-6): Testing & Polish

**Goal**: Comprehensive test coverage, source filter API, and documentation.

| Task ID | Description | Assignee | SP | Dependencies | Day |
|---------|-------------|----------|:--:|--------------|:---:|
| PCM-020 | PunchClockService unit tests | Backend QA | 4 | PCM-004 | 1-2 |
| PCM-019 | Backend endpoint tests | Backend QA | 7 | PCM-005, PCM-006, PCM-007 | 1-4 |
| PCM-023 | Add source filter to GET /time-entries | Backend | 3 | PCM-002, PCM-008 | 1-2 |
| PCM-021 | Frontend component tests | Frontend QA | 4 | PCM-014, PCM-015, PCM-016 | 3-4 |
| PCM-022 | E2E Playwright tests | QA | 5 | PCM-015, PCM-016 | 5-7 |
| PCM-024 | JSDoc comments on store and types | Frontend | 2 | PCM-013, PCM-014 | 3 |

**Sprint 3 Total**: 25 SP / 37h

Note: PCM-020 (service tests) and PCM-023 (source filter) can technically start as soon as their backend dependencies from Sprint 1 are complete, but are scheduled in Sprint 3 alongside other testing tasks for team allocation efficiency.

**Deliverables**:
- [ ] `PunchClockEndpointTest.php` covering all 3 endpoints + 6 guarded endpoints
- [ ] `PunchClockServiceTest.php` covering all service methods
- [ ] Component tests for PunchClockButton, RestrictedView, Settings
- [ ] E2E tests for admin setup + member punch workflow
- [ ] Source filter on `GET /time-entries` endpoint (`?source=punch_clock`)
- [ ] JSDoc comments on all public store methods and TypeScript types

**QA Gate**:
```bash
# Full backend test suite
composer fix && composer analyse
php artisan test --filter=PunchClockEndpointTest
php artisan test --filter=PunchClockServiceTest

# Full frontend test suite
npm run lint:fix && npm run format
npx vitest run
npm run build

# E2E tests
npx playwright test punch-clock
```

---

## 5. Risk Register

| Risk | Sprint | Probability | Impact | Mitigation |
|------|--------|:-----------:|:------:|------------|
| Guard middleware breaks existing API integrations | 1 | Medium | High | Thorough endpoint testing (PCM-019) covering both restricted and unrestricted users. Guard fast-paths when org mode is disabled. Feature-flagged via org toggle. |
| Running timer edge case when restriction applied mid-session | 1 | Medium | Medium | PunchClockService handles this: if member has a running timer when restricted, they can only stop it (punch out). The existing `myActive` endpoint returns the entry. |
| Performance overhead of guard middleware | 1 | Low | Medium | Guard fast-paths with zero DB queries when org mode is disabled. Slow path is one member lookup (~5ms). Benchmark in PCM-006. |
| Source tracking migration on large time_entries tables | 1 | Low | High | Column is nullable with no default transformation. `ALTER TABLE ADD COLUMN` with nullable default is near-instant in PostgreSQL. Index creation runs separately. |
| OpenAPI spec / TS client regeneration breaks frontend | 1 | Low | Medium | Regenerate and verify compilation before declaring Sprint 1 complete. Existing TS types are additive. |
| MemberUpdateRequest validation interferes with existing role changes | 1 | Low | Medium | The `is_punch_clock_restricted` validation is in a separate conditional block, executed independently from the `role` change logic. Test both paths. |
| E2E test flakiness with punch-in/punch-out timing | 3 | Medium | Low | Use deterministic seeding, explicit waits, and avoid sub-second timing assertions. |
| Restricted member confusion (unclear why they cannot edit) | 2 | Medium | Medium | Info banner with clear messaging. API error responses include specific `punch_clock_restricted` type. |

---

## 6. Definition of Done (Feature Complete)

- [ ] All 24 tasks (PCM-001 through PCM-024) completed
- [ ] 3 database migrations running and rollback-safe
- [ ] `PunchClockService` with `isRestricted()`, `punchIn()`, `punchOut()`, `getStatus()`
- [ ] `PunchClockController` with 3 endpoints
- [ ] `PunchClockGuard` middleware blocking 6 write endpoints for restricted members
- [ ] Source tracking on all time entry creation paths
- [ ] Organization settings UI for punch-clock mode
- [ ] Member restriction management UI
- [ ] Punch-in/punch-out frontend component with real-time duration
- [ ] Restricted view for Time page
- [ ] Readonly timesheet grid for restricted members
- [ ] Source badge on time entry list
- [ ] Source filter on time entry API
- [ ] `composer fix && composer analyse` passes with 0 new errors
- [ ] `npm run lint:fix && npm run format` passes
- [ ] `npm run build` succeeds
- [ ] Backend endpoint tests pass (PunchClockEndpointTest)
- [ ] Backend service tests pass (PunchClockServiceTest)
- [ ] Frontend component tests pass
- [ ] E2E Playwright tests pass
- [ ] OpenAPI spec updated and TS client regenerated
- [ ] JSDoc comments on store and types
- [ ] Feature ready for merge to `main`

---

## 7. Post-Sprint: Merge Strategy

After all 3 sprints complete and QA passes:

1. Rebase `feature/punch-clock-mode` on latest `main`
2. Run full test suite (PHP + JS + E2E)
3. Run `composer fix && composer analyse`
4. Run `npm run lint:fix && npm run format`
5. Run `npm run build`
6. Verify migrations run cleanly on a fresh database
7. Verify migrations run cleanly on an existing database (with data)
8. Create PR targeting `main`
9. After merge: Feature 15 (Attendance & Overtime) can leverage `time_entry_source = punch_clock` as its primary data source
