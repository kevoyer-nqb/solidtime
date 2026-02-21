# Task Assignments: Attendance & Overtime Tracking

Generated: 2026-02-09
Feature: 15-attendance-overtime
Task Prefix: `ATT-`
Total Tasks: 54
Total Effort: 305 hours (~153 SP)
Duration: 5 sprints (~10 weeks)

---

## Task Assignment Table

| Task ID  | Description                                                          | Type                 | Assigned Sub-Agent | Dependencies          | Effort  | Status |
|----------|----------------------------------------------------------------------|----------------------|--------------------|-----------------------|---------|--------|
| ATT-001  | Create database migrations for all 4 new tables                      | Backend / Database   | Backend Dev        | None                  | 6 hours | To Do  |
| ATT-002  | Create WorkSchedulePolicy model with relationships                   | Backend / Model      | Backend Dev        | ATT-001               | 4 hours | To Do  |
| ATT-003  | Create MemberWorkSchedule pivot model                                | Backend / Model      | Backend Dev        | ATT-001, ATT-002      | 2 hours | To Do  |
| ATT-004  | Create OvertimeRule model                                            | Backend / Model      | Backend Dev        | ATT-001               | 3 hours | To Do  |
| ATT-005  | Create AttendanceRecord model                                        | Backend / Model      | Backend Dev        | ATT-001               | 3 hours | To Do  |
| ATT-006  | Create AttendanceStatus enum                                         | Backend / Enum       | Backend Dev        | None                  | 1 hour  | To Do  |
| ATT-007  | Create OvertimeRuleType enum                                         | Backend / Enum       | Backend Dev        | None                  | 1 hour  | To Do  |
| ATT-008  | Create WorkScheduleService with policy resolution logic              | Backend / Service    | Backend Dev        | ATT-002, ATT-003      | 8 hours | To Do  |
| ATT-009  | Create WorkScheduleController (CRUD endpoints)                       | Backend / Controller | Backend Dev        | ATT-002, ATT-008      | 8 hours | To Do  |
| ATT-010  | Create WorkSchedule request validation classes                       | Backend / Validation | Backend Dev        | ATT-009               | 4 hours | To Do  |
| ATT-011  | Create OvertimeRuleController (CRUD endpoints)                       | Backend / Controller | Backend Dev        | ATT-004               | 6 hours | To Do  |
| ATT-012  | Create OvertimeRule request validation classes                       | Backend / Validation | Backend Dev        | ATT-011               | 3 hours | To Do  |
| ATT-013  | Register attendance permissions in modular pattern                   | Backend / Config     | Backend Dev        | FOUND-007             | 3 hours | To Do  |
| ATT-014  | Register API routes for work schedules and overtime rules            | Backend / Routing    | Backend Dev        | ATT-009, ATT-011      | 2 hours | To Do  |
| ATT-015  | Create TypeScript type definitions (attendance.d.ts)                 | Frontend / Types     | Frontend Dev       | None                  | 3 hours | To Do  |
| ATT-016  | Create AttendanceService with computation logic                      | Backend / Service    | Backend Dev        | ATT-005, ATT-008      | 16 hours| To Do  |
| ATT-017  | Implement overtime computation in AttendanceService                  | Backend / Service    | Backend Dev        | ATT-016, ATT-004      | 12 hours| To Do  |
| ATT-018  | Implement break detection and compliance in AttendanceService        | Backend / Service    | Backend Dev        | ATT-016, ATT-002      | 8 hours | To Do  |
| ATT-019  | Create ComputeAttendanceCommand (artisan command)                    | Backend / Command    | Backend Dev        | ATT-016               | 6 hours | To Do  |
| ATT-020  | Register scheduled command in Kernel.php                             | Backend / Config     | Backend Dev        | ATT-019               | 1 hour  | To Do  |
| ATT-021  | Create AttendanceController with reporting endpoints                 | Backend / Controller | Backend Dev        | ATT-016, ATT-017      | 8 hours | To Do  |
| ATT-022  | Create attendance reporting request validation classes               | Backend / Validation | Backend Dev        | ATT-021               | 4 hours | To Do  |
| ATT-023  | Implement attendance export (CSV)                                    | Backend / Export     | Backend Dev        | ATT-021               | 4 hours | To Do  |
| ATT-024  | Implement attendance export (PDF via Gotenberg)                      | Backend / Export     | Backend Dev        | ATT-021               | 6 hours | To Do  |
| ATT-025  | Register API routes for attendance and export                        | Backend / Routing    | Backend Dev        | ATT-021               | 2 hours | To Do  |
| ATT-026  | Implement stale record marking on TimeEntry create/update/delete     | Backend / Events     | Backend Dev        | ATT-005               | 4 hours | To Do  |
| ATT-027  | Update OpenAPI spec with all attendance endpoints                    | Backend / Docs       | Backend Dev        | ATT-014, ATT-025      | 4 hours | To Do  |
| ATT-028  | Regenerate TypeScript API client from OpenAPI spec                   | Backend / Codegen    | Backend Dev        | ATT-027               | 2 hours | To Do  |
| ATT-029  | Create Attendance.vue Inertia page with tab navigation               | Frontend / Page      | Frontend Dev       | ATT-028               | 4 hours | To Do  |
| ATT-030  | Create WorkScheduleSettings.vue (policy CRUD form)                   | Frontend / Component | Frontend Dev       | ATT-028, ATT-029      | 8 hours | To Do  |
| ATT-031  | Create MemberScheduleAssignment.vue (assign policy to members)       | Frontend / Component | Frontend Dev       | ATT-030               | 6 hours | To Do  |
| ATT-032  | Create OvertimeRuleSettings.vue (rule CRUD form)                     | Frontend / Component | Frontend Dev       | ATT-028, ATT-029      | 6 hours | To Do  |
| ATT-033  | Create useAttendanceStore.ts (Pinia store)                           | Frontend / Store     | Frontend Dev       | ATT-028, ATT-015      | 8 hours | To Do  |
| ATT-034  | Add web route for Attendance page                                    | Frontend / Routing   | Frontend Dev       | ATT-029               | 1 hour  | To Do  |
| ATT-035  | Add sidebar navigation item for Attendance                           | Frontend / UI        | Frontend Dev       | ATT-034               | 1 hour  | To Do  |
| ATT-036  | Create AttendanceGrid.vue (daily summary grid)                       | Frontend / Component | Frontend Dev       | ATT-033               | 12 hours| To Do  |
| ATT-037  | Create AttendanceStatusBadge.vue (color-coded status)                | Frontend / Component | Frontend Dev       | ATT-015               | 2 hours | To Do  |
| ATT-038  | Create AttendanceMemberDetail.vue (member day-by-day view)           | Frontend / Component | Frontend Dev       | ATT-033               | 8 hours | To Do  |
| ATT-039  | Create AttendanceCalendar.vue (personal monthly view)                | Frontend / Component | Frontend Dev       | ATT-033               | 8 hours | To Do  |
| ATT-040  | Create OvertimeReport.vue (overtime table)                           | Frontend / Component | Frontend Dev       | ATT-033               | 8 hours | To Do  |
| ATT-041  | Create AttendanceExportButton.vue (CSV/PDF export UI)                | Frontend / Component | Frontend Dev       | ATT-033               | 3 hours | To Do  |
| ATT-042  | Create AttendanceSummaryBar.vue (top-level counters)                 | Frontend / Component | Frontend Dev       | ATT-033               | 3 hours | To Do  |
| ATT-043  | Create AttendanceDayTooltip.vue (cell tooltip with details)          | Frontend / Component | Frontend Dev       | ATT-036               | 3 hours | To Do  |
| ATT-044  | Backend endpoint tests for WorkScheduleController                    | Testing / Backend    | QA Dev             | ATT-009, ATT-014      | 8 hours | To Do  |
| ATT-045  | Backend endpoint tests for OvertimeRuleController                    | Testing / Backend    | QA Dev             | ATT-011, ATT-014      | 6 hours | To Do  |
| ATT-046  | Backend endpoint tests for AttendanceController                      | Testing / Backend    | QA Dev             | ATT-021, ATT-025      | 8 hours | To Do  |
| ATT-047  | Unit tests for AttendanceService (computation logic)                 | Testing / Backend    | QA Dev             | ATT-016, ATT-017, ATT-018 | 12 hours| To Do  |
| ATT-048  | Unit tests for WorkScheduleService (policy resolution)               | Testing / Backend    | QA Dev             | ATT-008               | 6 hours | To Do  |
| ATT-049  | Unit tests for ComputeAttendanceCommand                              | Testing / Backend    | QA Dev             | ATT-019               | 4 hours | To Do  |
| ATT-050  | Frontend component tests (Vitest) for attendance components          | Testing / Frontend   | QA Dev             | ATT-036, ATT-037, ATT-038, ATT-039, ATT-040, ATT-041, ATT-042, ATT-043 | 8 hours | To Do  |
| ATT-051  | E2E Playwright tests for attendance configuration workflow           | Testing / E2E        | QA Dev             | ATT-029, ATT-030, ATT-031, ATT-032, ATT-034, ATT-035 | 6 hours | To Do  |
| ATT-052  | E2E Playwright tests for attendance viewing workflow                 | Testing / E2E        | QA Dev             | ATT-036, ATT-038, ATT-039, ATT-040 | 6 hours | To Do  |
| ATT-053  | JSDoc comments on Pinia store and services                           | Documentation        | Frontend Dev       | ATT-033               | 3 hours | To Do  |
| ATT-054  | Database index optimization for attendance queries                   | Backend / Database   | Backend Dev        | ATT-001               | 2 hours | To Do  |

---

## Sprint Allocation

### Sprint 1: Foundation -- Data Models & Configuration (Weeks 1-2)
**Focus**: Database schema, models, enums, work schedule service, configuration CRUD APIs

| Task ID | Description | Effort | Sub-Agent |
|---------|-------------|--------|-----------|
| ATT-001 | Database migrations (4 tables) | 6h | Backend Dev |
| ATT-002 | WorkSchedulePolicy model | 4h | Backend Dev |
| ATT-003 | MemberWorkSchedule model | 2h | Backend Dev |
| ATT-004 | OvertimeRule model | 3h | Backend Dev |
| ATT-005 | AttendanceRecord model | 3h | Backend Dev |
| ATT-006 | AttendanceStatus enum | 1h | Backend Dev |
| ATT-007 | OvertimeRuleType enum | 1h | Backend Dev |
| ATT-008 | WorkScheduleService | 8h | Backend Dev |
| ATT-009 | WorkScheduleController | 8h | Backend Dev |
| ATT-010 | WorkSchedule validation | 4h | Backend Dev |
| ATT-011 | OvertimeRuleController | 6h | Backend Dev |
| ATT-012 | OvertimeRule validation | 3h | Backend Dev |
| ATT-013 | Attendance permissions | 3h | Backend Dev |
| ATT-014 | API routes (config) | 2h | Backend Dev |
| ATT-015 | TypeScript types | 3h | Frontend Dev |

**Sprint 1 Total**: 57h / ~29 SP

### Sprint 2: Core Computation & Reporting APIs (Weeks 3-4)
**Focus**: Attendance computation engine, overtime logic, break detection, reporting endpoints, scheduled command

| Task ID | Description | Effort | Sub-Agent |
|---------|-------------|--------|-----------|
| ATT-016 | AttendanceService computation | 16h | Backend Dev |
| ATT-017 | Overtime computation | 12h | Backend Dev |
| ATT-018 | Break detection/compliance | 8h | Backend Dev |
| ATT-019 | ComputeAttendanceCommand | 6h | Backend Dev |
| ATT-020 | Schedule command in Kernel | 1h | Backend Dev |
| ATT-021 | AttendanceController | 8h | Backend Dev |
| ATT-022 | Attendance validation | 4h | Backend Dev |
| ATT-023 | CSV export | 4h | Backend Dev |
| ATT-024 | PDF export | 6h | Backend Dev |
| ATT-025 | API routes (attendance) | 2h | Backend Dev |
| ATT-026 | Stale record marking | 4h | Backend Dev |
| ATT-027 | OpenAPI spec update | 4h | Backend Dev |
| ATT-028 | Regenerate TS client | 2h | Backend Dev |

**Sprint 2 Total**: 77h / ~39 SP

### Sprint 3: Frontend -- Configuration UI (Weeks 5-6)
**Focus**: Attendance page, work schedule settings, overtime rule settings, Pinia store

| Task ID | Description | Effort | Sub-Agent |
|---------|-------------|--------|-----------|
| ATT-029 | Attendance.vue page | 4h | Frontend Dev |
| ATT-030 | WorkScheduleSettings.vue | 8h | Frontend Dev |
| ATT-031 | MemberScheduleAssignment.vue | 6h | Frontend Dev |
| ATT-032 | OvertimeRuleSettings.vue | 6h | Frontend Dev |
| ATT-033 | useAttendanceStore.ts | 8h | Frontend Dev |
| ATT-034 | Web route | 1h | Frontend Dev |
| ATT-035 | Sidebar nav item | 1h | Frontend Dev |

**Sprint 3 Total**: 34h / ~17 SP

### Sprint 4: Frontend -- Attendance & Overtime Views (Weeks 7-8)
**Focus**: Attendance grid, member detail, personal calendar, overtime report, export UI

| Task ID | Description | Effort | Sub-Agent |
|---------|-------------|--------|-----------|
| ATT-036 | AttendanceGrid.vue | 12h | Frontend Dev |
| ATT-037 | AttendanceStatusBadge.vue | 2h | Frontend Dev |
| ATT-038 | AttendanceMemberDetail.vue | 8h | Frontend Dev |
| ATT-039 | AttendanceCalendar.vue | 8h | Frontend Dev |
| ATT-040 | OvertimeReport.vue | 8h | Frontend Dev |
| ATT-041 | AttendanceExportButton.vue | 3h | Frontend Dev |
| ATT-042 | AttendanceSummaryBar.vue | 3h | Frontend Dev |
| ATT-043 | AttendanceDayTooltip.vue | 3h | Frontend Dev |

**Sprint 4 Total**: 47h / ~24 SP

### Sprint 5: Testing & Polish (Weeks 9-10)
**Focus**: Backend tests, frontend tests, E2E tests, documentation, optimization

| Task ID | Description | Effort | Sub-Agent |
|---------|-------------|--------|-----------|
| ATT-044 | WorkSchedule endpoint tests | 8h | QA Dev |
| ATT-045 | OvertimeRule endpoint tests | 6h | QA Dev |
| ATT-046 | Attendance endpoint tests | 8h | QA Dev |
| ATT-047 | AttendanceService unit tests | 12h | QA Dev |
| ATT-048 | WorkScheduleService unit tests | 6h | QA Dev |
| ATT-049 | ComputeAttendanceCommand tests | 4h | QA Dev |
| ATT-050 | Frontend component tests | 8h | QA Dev |
| ATT-051 | E2E tests (config workflow) | 6h | QA Dev |
| ATT-052 | E2E tests (viewing workflow) | 6h | QA Dev |
| ATT-053 | JSDoc comments | 3h | Frontend Dev |
| ATT-054 | Database index optimization | 2h | Backend Dev |

**Sprint 5 Total**: 69h / ~35 SP (front-loaded with testing; can be parallelized)

---

## Effort Summary by Sub-Agent

| Sub-Agent    | Tasks | Total Hours |
|-------------|-------|-------------|
| Backend Dev  | ATT-001 through ATT-014, ATT-016 through ATT-028, ATT-026, ATT-054 | 189h |
| Frontend Dev | ATT-015, ATT-029 through ATT-043, ATT-053 | 84h |
| QA Dev       | ATT-044 through ATT-052 | 70h (Note: some tests can begin in Sprint 3-4) |

---

## Dependency Risk Notes

1. **ATT-016 (AttendanceService) is the highest-risk task** at 16h effort and sits on the critical path. Consider splitting into sub-tasks (attendance status computation, time entry aggregation, PTO/holiday integration) if a single developer finds it overwhelming.

2. **ATT-017 (Overtime computation) depends on ATT-016 and ATT-004**. Both must be complete before overtime logic can be implemented. ATT-004 (OvertimeRule model) should be prioritized early in Sprint 1 to unblock.

3. **Frontend work (Sprint 3-4) is fully blocked by ATT-028 (TS client regeneration)**. The critical path through the backend must complete before frontend development can begin in earnest. ATT-015 (TypeScript types) can be done in parallel as a head start.

4. **Testing sprint (Sprint 5) can be partially parallelized with Sprint 3-4**. Backend endpoint tests (ATT-044 through ATT-046) and service unit tests (ATT-047, ATT-048) can begin as soon as their dependencies in Sprint 2 are complete, without waiting for frontend work.

5. **FOUND-007 (modular permissions) is an external dependency** for ATT-013. If not yet implemented, ATT-013 should register permissions directly in `JetstreamServiceProvider` as a temporary measure, with a follow-up task to migrate to the modular pattern.
