# Feature 15: Attendance & Overtime Tracking

## Branch
`feature/attendance-overtime`

## Task Prefix
`ATT-` (ATT-001 through ATT-054)

## Migration Date Prefix
`2026_03_15_` (per SHARED-FOUNDATIONS.md SF-03 convention)

Migrations:
- `2026_03_15_000001_create_work_schedule_policies_table.php`
- `2026_03_15_000002_create_member_work_schedules_table.php`
- `2026_03_15_000003_create_overtime_rules_table.php`
- `2026_03_15_000004_create_attendance_records_table.php`

## Execution Phase
Phase 3 -- depends on shared foundations (FOUND-007 modular permissions). Soft dependencies on Feature 06 (Kiosk) and Feature 07 (PTO) via `class_exists()` runtime checks.

## Sprint Summary
| Sprint | Focus | Story Points |
|--------|-------|-------------|
| Sprint 1 | Backend: Migrations, Models, Enums, Services, Config CRUD Controllers, Permissions, Routes | ~29 SP |
| Sprint 2 | Backend: AttendanceService (computation, overtime, breaks), Command, Reporting Controller, Export, OpenAPI | ~39 SP |
| Sprint 3 | Frontend: Attendance Page, Config UI (WorkSchedule, OvertimeRule, Assignment), Pinia Store, Nav | ~17 SP |
| Sprint 4 | Frontend: AttendanceGrid, StatusBadge, MemberDetail, Calendar, OvertimeReport, Export, Tooltip | ~24 SP |
| Sprint 5 | Testing: Endpoint tests, Service tests, Command tests, Component tests, E2E tests, JSDoc, Indexes | ~35 SP |

**Total**: ~153 SP / ~305h across 5 sprints (10 weeks)

## Shared Foundation Dependencies
- **FOUND-007**: Modular permissions infrastructure (for `AttendancePermissions.php`). If not available, permissions are added directly to `CorePermissions.php` as a temporary measure.
- **SF-06**: `weekly_capacity` on members / `default_weekly_capacity` on organizations (used as fallback when no work schedule policy is configured)

## Cross-Feature Soft Dependencies
- **Feature 06 (Kiosk)**: Kiosk break events feed into break detection. When absent, breaks are detected via gap analysis only.
- **Feature 07 (PTO)**: Approved PTO marks days as "on leave"; holiday calendar determines holiday days. When absent, all expected work days are treated as work days.
- **Feature 08 (Scheduling)**: Scheduled shifts can override expected hours per day. Not integrated in v1.

## Key Architecture Decisions
- 4 new database tables: `work_schedule_policies`, `member_work_schedules`, `overtime_rules`, `attendance_records`
- 2 new string-backed enums: `AttendanceStatus` (7 values), `OvertimeRuleType` (5 values)
- `AttendanceService` is the core computation engine (computes attendance, overtime, breaks per member per day)
- `WorkScheduleService` resolves effective policy: member assignment -> org default -> system default (8h Mon-Fri)
- Pre-computed `attendance_records` table (one record per member per day) for fast reporting queries
- Stale record marking: TimeEntry create/update/delete sets `is_stale = true` on the affected day's attendance record
- `attendance:compute` Artisan command runs daily (full computation) and every 30 min (stale records only)
- 15 API endpoints across 3 controllers (WorkScheduleController, OvertimeRuleController, AttendanceController)
- 5 new permissions: `attendance:configure`, `attendance:view:all`, `attendance:view:own`, `attendance:export`, `attendance:recompute`
- Soft integration with Feature 06/07 via `class_exists()` checks
- Frontend tab-based page (Attendance, Overtime, Settings) with lazy-loaded data per tab

## New Files to Create

### Backend (30 files)
- `app/Enums/AttendanceStatus.php`
- `app/Enums/OvertimeRuleType.php`
- `app/Models/WorkSchedulePolicy.php`
- `app/Models/MemberWorkSchedule.php`
- `app/Models/OvertimeRule.php`
- `app/Models/AttendanceRecord.php`
- `app/Service/AttendanceService.php`
- `app/Service/WorkScheduleService.php`
- `app/Http/Controllers/Api/V1/WorkScheduleController.php`
- `app/Http/Controllers/Api/V1/OvertimeRuleController.php`
- `app/Http/Controllers/Api/V1/AttendanceController.php`
- `app/Http/Requests/V1/WorkSchedule/WorkScheduleStoreRequest.php`
- `app/Http/Requests/V1/WorkSchedule/WorkScheduleUpdateRequest.php`
- `app/Http/Requests/V1/WorkSchedule/WorkScheduleAssignRequest.php`
- `app/Http/Requests/V1/OvertimeRule/OvertimeRuleStoreRequest.php`
- `app/Http/Requests/V1/OvertimeRule/OvertimeRuleUpdateRequest.php`
- `app/Http/Requests/V1/Attendance/AttendanceDailySummaryRequest.php`
- `app/Http/Requests/V1/Attendance/AttendanceMemberDetailRequest.php`
- `app/Http/Requests/V1/Attendance/AttendanceOvertimeReportRequest.php`
- `app/Http/Requests/V1/Attendance/AttendanceExportRequest.php`
- `app/Http/Requests/V1/Attendance/AttendanceRecomputeRequest.php`
- `app/Permissions/AttendancePermissions.php`
- `app/Console/Commands/ComputeAttendanceCommand.php`
- `database/migrations/2026_03_15_000001_create_work_schedule_policies_table.php`
- `database/migrations/2026_03_15_000002_create_member_work_schedules_table.php`
- `database/migrations/2026_03_15_000003_create_overtime_rules_table.php`
- `database/migrations/2026_03_15_000004_create_attendance_records_table.php`
- `database/factories/WorkSchedulePolicyFactory.php`
- `database/factories/MemberWorkScheduleFactory.php`
- `database/factories/OvertimeRuleFactory.php`
- `database/factories/AttendanceRecordFactory.php`

### Frontend (15 files)
- `resources/js/Pages/Attendance.vue`
- `resources/js/packages/ui/src/Attendance/AttendanceGrid.vue`
- `resources/js/packages/ui/src/Attendance/AttendanceStatusBadge.vue`
- `resources/js/packages/ui/src/Attendance/AttendanceMemberDetail.vue`
- `resources/js/packages/ui/src/Attendance/AttendanceCalendar.vue`
- `resources/js/packages/ui/src/Attendance/AttendanceSummaryBar.vue`
- `resources/js/packages/ui/src/Attendance/AttendanceDayTooltip.vue`
- `resources/js/packages/ui/src/Attendance/AttendanceExportButton.vue`
- `resources/js/packages/ui/src/Attendance/OvertimeReport.vue`
- `resources/js/packages/ui/src/Attendance/WorkScheduleSettings.vue`
- `resources/js/packages/ui/src/Attendance/MemberScheduleAssignment.vue`
- `resources/js/packages/ui/src/Attendance/OvertimeRuleSettings.vue`
- `resources/js/utils/useAttendance.ts`
- `resources/js/types/attendance.d.ts`
- `resources/js/packages/ui/src/Attendance/__tests__/AttendanceGrid.test.ts`
- `resources/js/packages/ui/src/Attendance/__tests__/AttendanceStatusBadge.test.ts`
- `resources/js/packages/ui/src/Attendance/__tests__/OvertimeReport.test.ts`

### Tests (6 files)
- `tests/Unit/Endpoint/Api/V1/WorkScheduleEndpointTest.php`
- `tests/Unit/Endpoint/Api/V1/OvertimeRuleEndpointTest.php`
- `tests/Unit/Endpoint/Api/V1/AttendanceEndpointTest.php`
- `tests/Unit/Service/AttendanceServiceTest.php`
- `tests/Unit/Service/WorkScheduleServiceTest.php`
- `tests/Feature/ComputeAttendanceCommandTest.php`
- `e2e/attendance-config.spec.ts`
- `e2e/attendance-view.spec.ts`

## Files to Modify
- `routes/api.php` (add 3 route groups: work-schedules, overtime-rules, attendance)
- `routes/web.php` (add Inertia page route for /attendance)
- `resources/js/Layouts/AppLayout.vue` (add sidebar nav item for Attendance)
- `app/Console/Kernel.php` (register attendance:compute in scheduler)
- `openapi.json` (add 15 endpoint definitions)
- `resources/js/packages/api/src/openapi.json.client.ts` (regenerate from OpenAPI spec)

## Permission Matrix
| Permission | Owner | Admin | Manager | Employee |
|------------|:-----:|:-----:|:-------:|:--------:|
| `attendance:configure` | Yes | Yes | No | No |
| `attendance:view:all` | Yes | Yes | Yes | No |
| `attendance:view:own` | Yes | Yes | Yes | Yes |
| `attendance:export` | Yes | Yes | Yes | No |
| `attendance:recompute` | Yes | Yes | No | No |

## API Endpoints (15)
| Method | Path | Description |
|--------|------|-------------|
| GET | `/work-schedules` | List work schedule policies |
| POST | `/work-schedules` | Create work schedule policy |
| GET | `/work-schedules/{ws}` | Get single policy |
| PUT | `/work-schedules/{ws}` | Update policy |
| DELETE | `/work-schedules/{ws}` | Delete policy |
| POST | `/work-schedules/assign` | Assign policy to member |
| GET | `/overtime-rules` | List overtime rules |
| POST | `/overtime-rules` | Create overtime rule |
| PUT | `/overtime-rules/{rule}` | Update overtime rule |
| DELETE | `/overtime-rules/{rule}` | Delete overtime rule |
| GET | `/attendance` | Daily attendance summary |
| GET | `/attendance/member/{m}` | Member attendance detail |
| GET | `/attendance/overtime` | Overtime report |
| GET | `/attendance/export` | Export attendance/overtime |
| POST | `/attendance/recompute` | Force recomputation |

All paths prefixed with `/api/v1/organizations/{organization}/`.

## Attendance Computation Algorithm
1. Resolve effective work schedule policy for member+date
2. Get expected seconds for that weekday
3. Check priority: holiday > rest day > on leave > work day
4. Query completed time entries for member+date (end IS NOT NULL)
5. Sum actual seconds worked
6. Determine status: present / absent / late / half_day
7. Compute daily overtime (threshold-based) and double-time
8. Compute breaks (gap analysis between entries + kiosk breaks)
9. Check break compliance against policy requirements
10. Upsert attendance_records (member_id + date unique)

## Overtime Calculation Rules
- Daily overtime: `max(0, actual - daily_threshold)`
- Double-time: `max(0, actual - double_time_threshold)`, adjusts regular overtime
- Weekly overtime: `max(0, (weekly_actual - weekly_daily_OT) - weekly_threshold)` -- prevents double-counting
- Rest day work: entire actual hours at rest day multiplier
- Holiday work: entire actual hours at holiday multiplier
- When multiple multipliers apply: highest wins (not cumulative)

## Quality Gates
- [ ] `composer fix && composer analyse` passes
- [ ] `npm run lint:fix && npm run format` passes
- [ ] `npm run build` succeeds
- [ ] All 15 API endpoints have endpoint tests
- [ ] AttendanceService has comprehensive unit tests (computation, overtime, breaks)
- [ ] WorkScheduleService has unit tests (policy resolution)
- [ ] ComputeAttendanceCommand has feature tests
- [ ] Frontend components have Vitest tests
- [ ] E2E tests cover config workflow and viewing workflow
- [ ] OpenAPI spec updated and TS client regenerated
- [ ] Sidebar navigation item functional
- [ ] CSV and PDF exports functional
- [ ] Stale record detection and recomputation working
- [ ] PTO/Kiosk integration gracefully degrades when absent

## Planning Docs
- `PRD.md` -- Product requirements (6 core requirements, 8 user stories)
- `task_assignments_20260209.md` -- Task breakdown (54 tasks, 305h)
- `ARCHITECTURE.md` -- Technical architecture (data models, API contracts, services, frontend)
- `CODEBASE-ANALYSIS.md` -- Integration analysis (existing patterns, Feature 06/07 integration, risk assessment)
- `SPRINT-PLAN.md` -- Sprint-by-sprint plan (5 sprints, 10 weeks)
