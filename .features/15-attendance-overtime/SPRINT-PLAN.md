# Sprint Plan: Attendance & Overtime Tracking

**Date**: 2026-02-09
**Feature**: 15 - Attendance & Overtime Tracking
**Branch**: `feature/attendance-overtime` (from `main`)
**Task Prefix**: `ATT-`
**PRD Reference**: `.features/15-attendance-overtime/PRD.md`
**Architecture Reference**: `.features/15-attendance-overtime/ARCHITECTURE.md`

---

## 1. Executive Summary

The Attendance & Overtime feature adds daily attendance computation, overtime calculation, break detection, configurable work schedule policies, and reporting/export capabilities to Solidtime. The system computes attendance records from existing time entries via a daily scheduled command and provides a comprehensive frontend for configuration and reporting.

**Total effort estimate**: 305 hours

**Total story points**: ~153 SP

**Number of sprints**: **5 sprints** (10 weeks)

**Team size assumptions**:
- 1 Backend Developer (senior, ~30 productive hours/sprint)
- 1 Frontend Developer (senior, ~30 productive hours/sprint)
- 1 QA Developer (available from Sprint 3, ~30 productive hours/sprint, can start backend tests earlier)
- Concurrent work where dependency graph allows

**Key constraints**:
- 4 new database tables with migrations
- 5 new permissions registered via modular pattern
- Backend computation engine (AttendanceService) is the highest-effort single task (16h)
- Frontend work is fully blocked until backend API and TypeScript client regeneration are complete (ATT-028)
- Feature 06 (Kiosk) and Feature 07 (PTO) are soft dependencies -- not required but enhance functionality
- Scheduled command (`attendance:compute`) must be idempotent and performant for large organizations

---

## 2. Sprint Overview Table

| Sprint | Name | Duration | Story Points | Key Deliverables |
|--------|------|----------|:------------:|------------------|
| **1** | Foundation: Data Models & Configuration APIs | 2 weeks | ~29 SP | 4 migrations, 4 models, 2 enums, 2 services, 2 CRUD controllers, request validation, permissions, API routes, TypeScript types |
| **2** | Core: Computation Engine & Reporting APIs | 2 weeks | ~39 SP | AttendanceService (computation, overtime, breaks), ComputeAttendanceCommand, AttendanceController (reporting), CSV/PDF export, stale record marking, OpenAPI spec, TS client |
| **3** | Frontend: Configuration UI | 2 weeks | ~17 SP | Attendance.vue page, WorkScheduleSettings, MemberScheduleAssignment, OvertimeRuleSettings, Pinia store, web route, sidebar nav |
| **4** | Frontend: Attendance & Overtime Views | 2 weeks | ~24 SP | AttendanceGrid, StatusBadge, MemberDetail, Calendar, OvertimeReport, ExportButton, SummaryBar, DayTooltip |
| **5** | Testing & Polish | 2 weeks | ~35 SP | Backend endpoint tests (3 controllers), service unit tests, command tests, frontend component tests, E2E tests, JSDoc, index optimization |

**Total**: ~144 SP across 10 weeks (remaining ~9 SP is buffer for parallelized testing tasks)

---

## 3. Dependency Map

### 3.1 Task Dependencies

```
Wave 1 (No dependencies -- Sprint 1 start):
    ATT-001 (Migrations, 6h)
    ATT-006 (AttendanceStatus enum, 1h)
    ATT-007 (OvertimeRuleType enum, 1h)
    ATT-013 (Permissions, 3h)          <-- depends on FOUND-007 (external)
    ATT-015 (TypeScript types, 3h)     <-- Frontend can start early

Wave 2 (after ATT-001):
    ATT-002 (WorkSchedulePolicy model, 4h)      <-- ATT-001
    ATT-004 (OvertimeRule model, 3h)             <-- ATT-001
    ATT-005 (AttendanceRecord model, 3h)         <-- ATT-001
    ATT-054 (Index optimization, 2h)             <-- ATT-001

Wave 3 (after ATT-002):
    ATT-003 (MemberWorkSchedule model, 2h)       <-- ATT-001, ATT-002
    ATT-008 (WorkScheduleService, 8h)            <-- ATT-002, ATT-003

Wave 4 (after ATT-008, ATT-004):
    ATT-009 (WorkScheduleController, 8h)         <-- ATT-002, ATT-008
    ATT-011 (OvertimeRuleController, 6h)         <-- ATT-004

Wave 5 (after ATT-009, ATT-011):
    ATT-010 (WorkSchedule validation, 4h)        <-- ATT-009
    ATT-012 (OvertimeRule validation, 3h)        <-- ATT-011
    ATT-014 (Config API routes, 2h)              <-- ATT-009, ATT-011

Wave 6 (after ATT-005, ATT-008 -- Sprint 2 start):
    ATT-016 (AttendanceService core, 16h)        <-- ATT-005, ATT-008
    ATT-026 (Stale record marking, 4h)           <-- ATT-005

Wave 7 (after ATT-016):
    ATT-017 (Overtime computation, 12h)          <-- ATT-016, ATT-004
    ATT-018 (Break detection, 8h)                <-- ATT-016, ATT-002
    ATT-019 (ComputeAttendanceCommand, 6h)       <-- ATT-016

Wave 8 (after ATT-017, ATT-019):
    ATT-020 (Schedule in Kernel, 1h)             <-- ATT-019
    ATT-021 (AttendanceController, 8h)           <-- ATT-016, ATT-017

Wave 9 (after ATT-021):
    ATT-022 (Attendance validation, 4h)          <-- ATT-021
    ATT-023 (CSV export, 4h)                     <-- ATT-021
    ATT-024 (PDF export, 6h)                     <-- ATT-021
    ATT-025 (Attendance API routes, 2h)          <-- ATT-021

Wave 10 (after ATT-014, ATT-025):
    ATT-027 (OpenAPI spec, 4h)                   <-- ATT-014, ATT-025
    ATT-028 (Regenerate TS client, 2h)           <-- ATT-027

Wave 11 (after ATT-028 -- Sprint 3 start):
    ATT-029 (Attendance.vue page, 4h)            <-- ATT-028
    ATT-030 (WorkScheduleSettings.vue, 8h)       <-- ATT-028, ATT-029
    ATT-032 (OvertimeRuleSettings.vue, 6h)       <-- ATT-028, ATT-029
    ATT-033 (useAttendanceStore.ts, 8h)          <-- ATT-028, ATT-015

Wave 12 (after ATT-029, ATT-030, ATT-033):
    ATT-031 (MemberScheduleAssignment.vue, 6h)  <-- ATT-030
    ATT-034 (Web route, 1h)                      <-- ATT-029
    ATT-035 (Sidebar nav, 1h)                    <-- ATT-034

Wave 13 (after ATT-033 -- Sprint 4 start):
    ATT-036 (AttendanceGrid.vue, 12h)            <-- ATT-033
    ATT-037 (AttendanceStatusBadge.vue, 2h)      <-- ATT-015
    ATT-038 (AttendanceMemberDetail.vue, 8h)     <-- ATT-033
    ATT-039 (AttendanceCalendar.vue, 8h)         <-- ATT-033
    ATT-040 (OvertimeReport.vue, 8h)             <-- ATT-033
    ATT-041 (AttendanceExportButton.vue, 3h)     <-- ATT-033
    ATT-042 (AttendanceSummaryBar.vue, 3h)       <-- ATT-033

Wave 14 (after ATT-036):
    ATT-043 (AttendanceDayTooltip.vue, 3h)       <-- ATT-036

Wave 15 (Testing -- Sprint 5, can begin partially in Sprint 3-4):
    ATT-044 (WorkSchedule endpoint tests, 8h)    <-- ATT-009, ATT-014
    ATT-045 (OvertimeRule endpoint tests, 6h)    <-- ATT-011, ATT-014
    ATT-046 (Attendance endpoint tests, 8h)      <-- ATT-021, ATT-025
    ATT-047 (AttendanceService unit tests, 12h)  <-- ATT-016, ATT-017, ATT-018
    ATT-048 (WorkScheduleService unit tests, 6h) <-- ATT-008
    ATT-049 (ComputeAttendanceCommand tests, 4h) <-- ATT-019
    ATT-050 (Frontend component tests, 8h)       <-- ATT-036+
    ATT-051 (E2E config workflow, 6h)            <-- ATT-029+
    ATT-052 (E2E viewing workflow, 6h)           <-- ATT-036+
    ATT-053 (JSDoc, 3h)                          <-- ATT-033
```

### 3.2 Critical Path

```
ATT-001 (6h) -> ATT-002 (4h) -> ATT-003 (2h) -> ATT-008 (8h) -> ATT-016 (16h)
-> ATT-017 (12h) -> ATT-021 (8h) -> ATT-025 (2h) -> ATT-027 (4h) -> ATT-028 (2h)
-> ATT-033 (8h) -> ATT-036 (12h)
```

**Critical path duration**: 84 hours of sequential work

This spans approximately 4.2 sprints of a single developer's 30-productive-hour capacity. Parallelism between backend and frontend developers (and QA from Sprint 3) brings the total down to 5 sprints.

### 3.3 Parallelism Opportunities

| Sprint | Backend Dev | Frontend Dev | QA Dev | Notes |
|--------|------------|--------------|--------|-------|
| 1 | ATT-001 through ATT-014, ATT-054 (57h) | ATT-015 (3h) | -- | Frontend dev has low utilization in Sprint 1; can assist with backend or work on other features |
| 2 | ATT-016 through ATT-028 (77h) | -- (blocked on ATT-028) | ATT-044, ATT-045, ATT-048 (20h) | QA can begin backend tests for Sprint 1 deliverables while Sprint 2 progresses |
| 3 | -- (available for bug fixes) | ATT-029 through ATT-035 (34h) | ATT-046, ATT-047, ATT-049 (24h) | QA tests Sprint 2 deliverables in parallel with frontend work |
| 4 | -- (available for bug fixes) | ATT-036 through ATT-043 (47h) | (continues Sprint 3 tests if needed) | Frontend-heavy sprint |
| 5 | ATT-054 (index optimization, 2h if not done) | ATT-053 (JSDoc, 3h) | ATT-050 through ATT-052 (20h) | Testing sprint; all agents contribute |

---

## 4. Sprint Details

### Sprint 1 (Weeks 1-2): Foundation -- Data Models & Configuration APIs

**Goal**: All 4 database tables created, models functional, enums defined, WorkScheduleService resolves policies, WorkScheduleController and OvertimeRuleController CRUD fully operational, permissions registered, config API routes active.

| Task ID | Description | Assignee | SP | Dependencies | Day |
|---------|-------------|----------|:--:|--------------|:---:|
| ATT-001 | Create 4 database migrations | Backend | 4 | None | 1 |
| ATT-006 | Create AttendanceStatus enum | Backend | 1 | None | 1 |
| ATT-007 | Create OvertimeRuleType enum | Backend | 1 | None | 1 |
| ATT-013 | Register attendance permissions | Backend | 2 | FOUND-007 | 1 |
| ATT-002 | Create WorkSchedulePolicy model | Backend | 3 | ATT-001 | 2 |
| ATT-004 | Create OvertimeRule model | Backend | 2 | ATT-001 | 2 |
| ATT-005 | Create AttendanceRecord model | Backend | 2 | ATT-001 | 2 |
| ATT-003 | Create MemberWorkSchedule model | Backend | 1 | ATT-001, ATT-002 | 3 |
| ATT-008 | Create WorkScheduleService | Backend | 5 | ATT-002, ATT-003 | 3-4 |
| ATT-009 | Create WorkScheduleController | Backend | 5 | ATT-002, ATT-008 | 5-6 |
| ATT-011 | Create OvertimeRuleController | Backend | 4 | ATT-004 | 5-6 |
| ATT-010 | Create WorkSchedule validation classes | Backend | 3 | ATT-009 | 7 |
| ATT-012 | Create OvertimeRule validation classes | Backend | 2 | ATT-011 | 7 |
| ATT-014 | Register API routes (config) | Backend | 1 | ATT-009, ATT-011 | 8 |
| ATT-015 | Create TypeScript type definitions | Frontend | 2 | None | 1 |

**Sprint 1 Total**: ~38 SP (57h backend + 3h frontend = 60h)

Note: Sprint 1 is backend-heavy (57h). The backend developer may extend slightly into Sprint 2's first days if needed. The frontend developer has low utilization and should use the time for ATT-015 plus preparation (studying existing UI patterns, setting up component scaffolds).

**Deliverables**:
- [ ] 4 database tables created with proper indexes and constraints
- [ ] 4 Eloquent models with relationships, casts, and audit traits
- [ ] 2 string-backed enums (AttendanceStatus, OvertimeRuleType)
- [ ] WorkScheduleService with policy resolution logic (member -> org default -> system default)
- [ ] WorkScheduleController with full CRUD (index, store, show, update, destroy, assign)
- [ ] OvertimeRuleController with full CRUD (index, store, update, destroy)
- [ ] Request validation classes for work schedule and overtime rule endpoints
- [ ] 5 attendance permissions registered per role
- [ ] API routes for configuration endpoints
- [ ] TypeScript type definitions for all attendance types

**QA Gate**:
```bash
./vendor/bin/sail exec laravel.test composer fix
./vendor/bin/sail exec laravel.test composer analyse
./vendor/bin/sail exec laravel.test php artisan migrate --force
# Verify all 4 tables created
# Verify work schedule CRUD via curl/Postman
# Verify overtime rule CRUD via curl/Postman
```

---

### Sprint 2 (Weeks 3-4): Core -- Computation Engine & Reporting APIs

**Goal**: AttendanceService computation engine fully functional, overtime and break detection working, scheduled command operational, AttendanceController reporting endpoints active, CSV/PDF export working, stale record marking in place, OpenAPI spec updated, TypeScript client regenerated.

| Task ID | Description | Assignee | SP | Dependencies | Day |
|---------|-------------|----------|:--:|--------------|:---:|
| ATT-016 | Create AttendanceService with computation logic | Backend | 10 | ATT-005, ATT-008 | 1-3 |
| ATT-026 | Implement stale record marking on TimeEntry events | Backend | 3 | ATT-005 | 1 |
| ATT-017 | Implement overtime computation | Backend | 8 | ATT-016, ATT-004 | 3-5 |
| ATT-018 | Implement break detection and compliance | Backend | 5 | ATT-016, ATT-002 | 4-5 |
| ATT-019 | Create ComputeAttendanceCommand | Backend | 4 | ATT-016 | 5-6 |
| ATT-020 | Register scheduled command in Kernel.php | Backend | 1 | ATT-019 | 6 |
| ATT-021 | Create AttendanceController with reporting endpoints | Backend | 5 | ATT-016, ATT-017 | 6-7 |
| ATT-022 | Create attendance reporting validation classes | Backend | 3 | ATT-021 | 7 |
| ATT-023 | Implement attendance export (CSV) | Backend | 3 | ATT-021 | 8 |
| ATT-024 | Implement attendance export (PDF via Gotenberg) | Backend | 4 | ATT-021 | 8-9 |
| ATT-025 | Register API routes for attendance and export | Backend | 1 | ATT-021 | 9 |
| ATT-027 | Update OpenAPI spec with all 15 endpoints | Backend | 3 | ATT-014, ATT-025 | 9-10 |
| ATT-028 | Regenerate TypeScript API client | Backend | 1 | ATT-027 | 10 |

**Sprint 2 Total**: ~51 SP (77h backend)

QA can begin testing Sprint 1 deliverables in parallel:

| Task ID | Description | Assignee | SP | Dependencies | Day |
|---------|-------------|----------|:--:|--------------|:---:|
| ATT-044 | WorkSchedule endpoint tests | QA | 5 | ATT-009, ATT-014 | 1-3 |
| ATT-045 | OvertimeRule endpoint tests | QA | 4 | ATT-011, ATT-014 | 3-5 |
| ATT-048 | WorkScheduleService unit tests | QA | 4 | ATT-008 | 5-7 |

**Sprint 2 QA subtotal**: ~13 SP (20h)

**Deliverables**:
- [ ] AttendanceService computing attendance records from time entries
- [ ] Overtime computation (daily threshold, double-time, rest day, holiday)
- [ ] Break detection (gap analysis, compliance checking)
- [ ] `attendance:compute` Artisan command with `--date`, `--member`, `--recompute` flags
- [ ] Command registered in scheduler (daily at 02:00, recompute every 30 min)
- [ ] Stale record marking on TimeEntry create/update/delete
- [ ] AttendanceController with dailySummary, memberDetail, overtimeReport, export, recompute
- [ ] CSV and PDF export endpoints
- [ ] Attendance reporting validation classes
- [ ] API routes for attendance endpoints
- [ ] OpenAPI spec updated with all 15 endpoints
- [ ] TypeScript API client regenerated

**QA Gate**:
```bash
./vendor/bin/sail exec laravel.test composer fix
./vendor/bin/sail exec laravel.test composer analyse
./vendor/bin/sail exec laravel.test php artisan attendance:compute --date=2026-02-08
# Verify attendance records computed
# Verify reporting endpoints via curl/Postman
# Verify CSV export downloads correctly
# Run Sprint 1 endpoint tests
```

---

### Sprint 3 (Weeks 5-6): Frontend -- Configuration UI

**Goal**: Attendance.vue page with tab navigation, work schedule settings form, member schedule assignment, overtime rule settings form, Pinia store, web route and sidebar navigation.

| Task ID | Description | Assignee | SP | Dependencies | Day |
|---------|-------------|----------|:--:|--------------|:---:|
| ATT-029 | Create Attendance.vue Inertia page with tab navigation | Frontend | 3 | ATT-028 | 1 |
| ATT-033 | Create useAttendanceStore.ts (Pinia store) | Frontend | 5 | ATT-028, ATT-015 | 1-2 |
| ATT-030 | Create WorkScheduleSettings.vue (policy CRUD form) | Frontend | 5 | ATT-028, ATT-029 | 3-5 |
| ATT-032 | Create OvertimeRuleSettings.vue (rule CRUD form) | Frontend | 4 | ATT-028, ATT-029 | 3-5 |
| ATT-031 | Create MemberScheduleAssignment.vue | Frontend | 4 | ATT-030 | 6-7 |
| ATT-034 | Add web route for Attendance page | Frontend | 1 | ATT-029 | 2 |
| ATT-035 | Add sidebar navigation item | Frontend | 1 | ATT-034 | 2 |

**Sprint 3 Frontend subtotal**: ~23 SP (34h)

QA continues testing Sprint 2 deliverables:

| Task ID | Description | Assignee | SP | Dependencies | Day |
|---------|-------------|----------|:--:|--------------|:---:|
| ATT-046 | Attendance endpoint tests | QA | 5 | ATT-021, ATT-025 | 1-3 |
| ATT-047 | AttendanceService unit tests | QA | 8 | ATT-016, ATT-017, ATT-018 | 3-7 |
| ATT-049 | ComputeAttendanceCommand tests | QA | 3 | ATT-019 | 7-8 |

**Sprint 3 QA subtotal**: ~16 SP (24h)

**Deliverables**:
- [ ] `Attendance.vue` page with 3-tab navigation (Attendance, Overtime, Settings)
- [ ] `useAttendanceStore` Pinia store with all actions
- [ ] `WorkScheduleSettings.vue` -- create/edit/delete work schedule policies
- [ ] `MemberScheduleAssignment.vue` -- assign policies to members with effective dates
- [ ] `OvertimeRuleSettings.vue` -- create/edit/delete overtime rules
- [ ] Web route at `/attendance`
- [ ] Sidebar navigation item with `ClipboardDocumentCheckIcon`
- [ ] Backend endpoint tests for all 3 controllers
- [ ] Unit tests for AttendanceService and ComputeAttendanceCommand

**QA Gate**:
```bash
npm run lint:fix && npm run format
npm run build
# Navigate to /attendance via sidebar
# Verify tab navigation works
# Create/edit/delete a work schedule policy
# Assign a policy to a member
# Create/edit/delete an overtime rule
./vendor/bin/sail exec laravel.test php artisan test --filter=WorkScheduleEndpointTest
./vendor/bin/sail exec laravel.test php artisan test --filter=OvertimeRuleEndpointTest
./vendor/bin/sail exec laravel.test php artisan test --filter=AttendanceEndpointTest
./vendor/bin/sail exec laravel.test php artisan test --filter=AttendanceServiceTest
```

---

### Sprint 4 (Weeks 7-8): Frontend -- Attendance & Overtime Views

**Goal**: Full attendance grid with status badges, member detail view, personal calendar, overtime report, export buttons, summary bar, day tooltips.

| Task ID | Description | Assignee | SP | Dependencies | Day |
|---------|-------------|----------|:--:|--------------|:---:|
| ATT-036 | Create AttendanceGrid.vue (daily summary grid) | Frontend | 8 | ATT-033 | 1-3 |
| ATT-037 | Create AttendanceStatusBadge.vue | Frontend | 1 | ATT-015 | 1 |
| ATT-038 | Create AttendanceMemberDetail.vue | Frontend | 5 | ATT-033 | 3-5 |
| ATT-039 | Create AttendanceCalendar.vue (personal monthly view) | Frontend | 5 | ATT-033 | 3-5 |
| ATT-040 | Create OvertimeReport.vue | Frontend | 5 | ATT-033 | 5-7 |
| ATT-041 | Create AttendanceExportButton.vue | Frontend | 2 | ATT-033 | 7 |
| ATT-042 | Create AttendanceSummaryBar.vue | Frontend | 2 | ATT-033 | 7 |
| ATT-043 | Create AttendanceDayTooltip.vue | Frontend | 2 | ATT-036 | 8 |

**Sprint 4 Total**: ~30 SP (47h)

**Deliverables**:
- [ ] `AttendanceGrid.vue` -- members x days grid with color-coded status badges
- [ ] `AttendanceStatusBadge.vue` -- reusable status indicator component
- [ ] `AttendanceMemberDetail.vue` -- day-by-day breakdown for a specific member
- [ ] `AttendanceCalendar.vue` -- personal monthly attendance calendar
- [ ] `OvertimeReport.vue` -- overtime breakdown table with aggregation toggle
- [ ] `AttendanceExportButton.vue` -- CSV and PDF export buttons
- [ ] `AttendanceSummaryBar.vue` -- top-level present/absent/late counters
- [ ] `AttendanceDayTooltip.vue` -- hover tooltip showing hours and overtime

**QA Gate**:
```bash
npm run lint:fix && npm run format
npm run build
# Full manual testing workflow:
# 1. Navigate to Attendance page
# 2. View daily summary grid
# 3. Verify color-coded status badges
# 4. Click a member to see detail view
# 5. Navigate to overtime tab and view report
# 6. Export as CSV and PDF
# 7. View personal attendance as Employee role
```

---

### Sprint 5 (Weeks 9-10): Testing & Polish

**Goal**: Comprehensive test coverage, documentation, database optimization.

| Task ID | Description | Assignee | SP | Dependencies | Day |
|---------|-------------|----------|:--:|--------------|:---:|
| ATT-050 | Frontend component tests (Vitest) | QA | 5 | ATT-036+ | 1-3 |
| ATT-051 | E2E Playwright tests (config workflow) | QA | 4 | ATT-029+ | 3-5 |
| ATT-052 | E2E Playwright tests (viewing workflow) | QA | 4 | ATT-036+ | 5-7 |
| ATT-053 | JSDoc comments on Pinia store and services | Frontend | 2 | ATT-033 | 1-2 |
| ATT-054 | Database index optimization | Backend | 1 | ATT-001 | 1 |

**Sprint 5 Total**: ~16 SP (27h)

Note: If QA has already completed some tests during Sprints 3-4 (ATT-044 through ATT-049), this sprint is lighter. The remaining effort can be used for:
- Bug fixes from Sprint 4 QA
- Performance profiling of `attendance:compute` with large datasets
- Accessibility testing of attendance grid
- Cross-browser testing of export functionality

**Deliverables**:
- [ ] Vitest component tests for AttendanceGrid, StatusBadge, OvertimeReport
- [ ] E2E tests: admin configures work schedule + overtime rules
- [ ] E2E tests: manager views attendance grid + detail + export
- [ ] JSDoc documentation on all store methods and service functions
- [ ] Database indexes reviewed with `EXPLAIN ANALYZE`

**QA Gate (Final)**:
```bash
# Backend
./vendor/bin/sail exec laravel.test composer fix
./vendor/bin/sail exec laravel.test composer analyse
./vendor/bin/sail exec laravel.test php artisan test

# Frontend
npm run lint:fix && npm run format
npm run build
npx vitest run
npx playwright test

# Integration
# Verify attendance:compute runs successfully
# Verify stale records are recomputed
# Verify CSV and PDF exports are correct
```

---

## 5. QA Gates Summary

| Gate | Sprint | Checks |
|------|--------|--------|
| G1: Schema & Config APIs | After Sprint 1 | Migrations run, CRUD endpoints work, permissions enforced |
| G2: Computation Engine | After Sprint 2 | `attendance:compute` produces correct records, overtime/breaks calculated, exports work |
| G3: Configuration UI | After Sprint 3 | Policy CRUD in browser, member assignment works, overtime rules configurable |
| G4: Attendance Views | After Sprint 4 | Grid renders, status badges correct, member detail works, export buttons functional |
| G5: Full Test Suite | After Sprint 5 | All PHPUnit tests pass, all Vitest tests pass, all Playwright tests pass, lint/format clean |

---

## 6. Risk Register

| Risk | Sprint | Probability | Impact | Mitigation |
|------|--------|:-----------:|:------:|------------|
| AttendanceService computation complexity (16h task) | 2 | High | High | Split into sub-methods (computeAttendance, computeOvertime, computeBreaks). Start with simplest path (present/absent) and add complexity incrementally. Write tests alongside implementation. |
| Timezone handling in day boundary calculation | 2 | High | High | Use `TimezoneService` pattern from existing codebase. Policy stores timezone explicitly. All computations convert to policy timezone before determining calendar day. |
| Sprint 2 overruns (77h is heavy for one developer) | 2 | Medium | High | Identify tasks that QA or Frontend dev can assist with (ATT-027 OpenAPI spec, ATT-023 CSV export). Consider starting Sprint 2 tasks that have no Sprint 1 dependencies (ATT-026) on Sprint 1's last days. |
| FOUND-007 (modular permissions) not yet available | 1 | Medium | Medium | If FOUND-007 is not ready, add permissions directly to `CorePermissions.php` as a temporary measure. Create follow-up task to migrate to modular pattern. |
| Feature 07 (PTO) models change before attendance ships | 3-4 | Low | Medium | `class_exists()` checks provide runtime safety. If PTO model signatures change, only the `isOnLeave()` and `isHoliday()` methods need updating. |
| Frontend sprint overruns from UI complexity | 4 | Medium | Medium | Prioritize core grid (ATT-036) and status badge (ATT-037). Defer calendar (ATT-039) and tooltip (ATT-043) if time is tight. These are P1 enhancements that can be added post-launch. |
| `attendance:compute` performance for 1000+ member orgs | 5 | Medium | High | Process members in chunks of 50. Use database-level aggregation. Benchmark with seeded data during Sprint 5. If >120s, add database-level optimizations (ATT-054). |
| E2E test flakiness | 5 | Medium | Low | Seed deterministic test data. Use explicit Playwright waits for API responses. Isolate tests from each other with fresh data per test. |

---

## 7. Definition of Done (Feature Complete)

- [ ] All 54 tasks (ATT-001 through ATT-054) completed
- [ ] 4 database tables created with proper indexes and constraints
- [ ] 4 Eloquent models with relationships and audit traits
- [ ] 2 enums (AttendanceStatus, OvertimeRuleType) defined
- [ ] WorkScheduleService resolving policies correctly
- [ ] AttendanceService computing attendance, overtime, and breaks correctly
- [ ] `attendance:compute` command running successfully on schedule
- [ ] Stale record detection and recomputation working
- [ ] All 15 API endpoints working with proper validation and permissions
- [ ] Backend endpoint tests passing for all 3 controllers
- [ ] Unit tests passing for AttendanceService and WorkScheduleService
- [ ] ComputeAttendanceCommand tests passing
- [ ] Frontend configuration UI (policies, rules, assignments) working
- [ ] Frontend attendance grid, member detail, and calendar working
- [ ] Frontend overtime report and export functional
- [ ] Vitest component tests passing
- [ ] E2E Playwright tests passing
- [ ] `composer fix && composer analyse` passes with 0 new errors
- [ ] `npm run lint:fix && npm run format` passes
- [ ] `npm run build` succeeds
- [ ] OpenAPI spec updated with all endpoints
- [ ] TypeScript client regenerated
- [ ] Sidebar navigation item functional
- [ ] JSDoc documentation on Pinia store
- [ ] PTO/Kiosk integration gracefully degrades when those features are absent
- [ ] Feature ready for merge to `main`

---

## 8. Post-Sprint: Merge Strategy

After all 5 sprints complete and QA passes:

1. Rebase `feature/attendance-overtime` on latest `main`
2. Run full test suite (PHP + JS + E2E)
3. Run `composer fix && composer analyse`
4. Run `npm run lint:fix && npm run format`
5. Run `npm run build`
6. Create PR targeting `main`
7. After merge: downstream features (09 Advanced Reporting, 04 Invoicing) can leverage attendance data
