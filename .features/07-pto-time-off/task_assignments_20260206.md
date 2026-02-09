# Task Assignments: PTO & Time Off Feature

Generated: 2026-02-06
PRD Reference: `/home/keven/Documents/solidtime-analysis/.features/07-pto-time-off/PRD.md`

## Summary

- **Total Tasks**: 33
- **Total Story Points**: 117
- **Total Estimated Hours**: ~230
- **Duration**: 8 weeks (4 sprints)
- **Team Composition**: 1 Backend Dev, 1 Frontend Dev, 1 QA (shared)

## Task Assignment Table

| Task ID | Description | Type | Assigned Sub-Agent | Dependencies | Effort | Status |
|---------|-------------|------|-------------------|--------------|--------|--------|
| TASK-001 | Database Migrations for PTO Feature (4 tables: policies, requests, balances, holidays) | Backend / Database | Backend Dev | None | 6 hours / 3 SP | To Do |
| TASK-002 | Eloquent Models (TimeOffPolicy, TimeOffRequest, TimeOffBalance, Holiday) with relationships and casts | Backend | Backend Dev | TASK-001 | 8 hours / 4 SP | To Do |
| TASK-003 | PHP Enums (TimeOffType, TimeOffRequestStatus, AccrualFrequency, OvertimeRuleType) | Backend | Backend Dev | None | 2 hours / 1 SP | To Do |
| TASK-004 | Model Factories for all 4 new models with fluent builder methods | Backend / Testing | Backend Dev | TASK-002, TASK-003 | 4 hours / 2 SP | To Do |
| TASK-005 | Register 18 new permissions in JetstreamServiceProvider for all roles | Backend | Backend Dev | None | 3 hours / 2 SP | To Do |
| TASK-006 | TimeOffPolicy CRUD API (Controller, StoreRequest, UpdateRequest, Resource, Collection) | Backend | Backend Dev | TASK-002, TASK-003, TASK-005 | 10 hours / 5 SP | To Do |
| TASK-007 | Holiday CRUD API (Controller, Requests, Resources) with recurring holiday logic | Backend | Backend Dev | TASK-002, TASK-005 | 6 hours / 3 SP | To Do |
| TASK-008 | Register all PTO API routes in routes/api.php | Backend | Backend Dev | TASK-006, TASK-007 | 2 hours / 1 SP | To Do |
| TASK-009 | TimeOffService -- core business logic (hour calc, balance check, overlap, accrual, carryover) | Backend | Backend Dev | TASK-002, TASK-003, TASK-007 | 12 hours / 6 SP | To Do |
| TASK-010 | TimeOffRequest lifecycle API (create, cancel, approve, deny, list) with exceptions | Backend | Backend Dev | TASK-002, TASK-005, TASK-008, TASK-009 | 12 hours / 6 SP | To Do |
| TASK-011 | TimeOffBalance API (me endpoint, index with filters, manual adjustment) | Backend | Backend Dev | TASK-002, TASK-005, TASK-008, TASK-009 | 6 hours / 3 SP | To Do |
| TASK-012 | TimeOffRequestStateMachine -- explicit status transition validation | Backend | Backend Dev | TASK-010 | 4 hours / 2 SP | To Do |
| TASK-013 | Policy-to-Member assignment endpoints (assign individual, assign all) | Backend | Backend Dev | TASK-002, TASK-009 | 6 hours / 3 SP | To Do |
| TASK-014 | Balance Recalculation Service -- derive used/pending from actual requests | Backend | Backend Dev | TASK-009 | 4 hours / 2 SP | To Do |
| TASK-015 | Accrual Scheduled Command (monthly, idempotent, cap-aware, waiting period) | Backend | Backend Dev | TASK-002, TASK-009 | 10 hours / 5 SP | To Do |
| TASK-016 | Per-Hour-Worked Accrual Mode -- extend accrual engine for TimeEntry-based accrual | Backend | Backend Dev | TASK-015 | 4 hours / 2 SP | To Do |
| TASK-017 | Year-End Carryover Command (annual, idempotent, max_carryover enforcement) | Backend | Backend Dev | TASK-015 | 6 hours / 3 SP | To Do |
| TASK-018 | TypeScript type definitions for all PTO API responses | Frontend | Frontend Dev | TASK-006, TASK-007, TASK-010, TASK-011 | 3 hours / 1 SP | To Do |
| TASK-019 | Pinia Store (useTimeOff.ts) -- state management for policies, balances, requests, holidays | Frontend | Frontend Dev | TASK-018 | 10 hours / 5 SP | To Do |
| TASK-020 | Web Routes, TimeOff Page, Sidebar Navigation, Permission Helper | Frontend | Frontend Dev | TASK-019 | 4 hours / 2 SP | To Do |
| TASK-021 | TimeOffBalanceDashboard and TimeOffBalanceCard components | Frontend | Frontend Dev | TASK-019, TASK-020 | 8 hours / 4 SP | To Do |
| TASK-022 | TimeOffRequestForm and TimeOffRequestModal components | Frontend | Frontend Dev | TASK-019, TASK-021 | 10 hours / 5 SP | To Do |
| TASK-023 | TimeOffRequestList, TimeOffRequestRow, TimeOffPendingReviewList components | Frontend | Frontend Dev | TASK-019, TASK-020 | 8 hours / 4 SP | To Do |
| TASK-024 | TimeOffPolicyList, TimeOffPolicyForm, TimeOffPolicyModal (admin) components | Frontend | Frontend Dev | TASK-019 | 8 hours / 4 SP | To Do |
| TASK-025 | HolidayList and HolidayForm components | Frontend | Frontend Dev | TASK-019 | 6 hours / 3 SP | To Do |
| TASK-026 | AttendanceService and AttendanceController (backend) | Backend | Backend Dev | TASK-009 | 8 hours / 4 SP | To Do |
| TASK-027 | Attendance page, grid components, and useAttendance store | Frontend | Frontend Dev | TASK-026, TASK-019 | 8 hours / 4 SP | To Do |
| TASK-028 | API Endpoint Tests -- TimeOffPolicy and Holiday CRUD | Testing | Backend Dev | TASK-006, TASK-007, TASK-004 | 10 hours / 5 SP | To Do |
| TASK-029 | API Endpoint Tests -- TimeOffRequest lifecycle and TimeOffBalance | Testing | Backend Dev | TASK-010, TASK-011, TASK-004 | 12 hours / 6 SP | To Do |
| TASK-030 | Unit Tests -- TimeOffService, StateMachine, AttendanceService | Testing | Backend Dev | TASK-009, TASK-012, TASK-014 | 8 hours / 4 SP | To Do |
| TASK-031 | Scheduled Command Tests -- Accrual and Carryover commands | Testing | Backend Dev | TASK-015, TASK-017 | 6 hours / 3 SP | To Do |
| TASK-032 | Frontend Component Tests (Vitest) -- BalanceCard, RequestForm, RequestList | Testing | Frontend Dev | TASK-021, TASK-022, TASK-023 | 8 hours / 4 SP | To Do |
| TASK-033 | E2E Playwright Tests -- 6 critical user flow scenarios | Testing | Frontend Dev | TASK-020, TASK-021, TASK-022, TASK-023 | 8 hours / 4 SP | To Do |

## Sprint Allocation

### Sprint 1 (Weeks 1-2): Foundation

| Task ID | Description | Agent | Points |
|---------|-------------|-------|--------|
| TASK-001 | Database Migrations | Backend Dev | 3 |
| TASK-002 | Eloquent Models | Backend Dev | 4 |
| TASK-003 | PHP Enums | Backend Dev | 1 |
| TASK-004 | Model Factories | Backend Dev | 2 |
| TASK-005 | Permissions Registration | Backend Dev | 2 |
| TASK-006 | TimeOffPolicy CRUD API | Backend Dev | 5 |
| TASK-007 | Holiday CRUD API | Backend Dev | 3 |
| TASK-008 | API Routes Registration | Backend Dev | 1 |
| **Sprint 1 Total** | | | **22 SP** |

### Sprint 2 (Weeks 3-4): Business Logic & APIs

| Task ID | Description | Agent | Points |
|---------|-------------|-------|--------|
| TASK-009 | TimeOffService (core logic) | Backend Dev | 6 |
| TASK-010 | TimeOffRequest API | Backend Dev | 6 |
| TASK-011 | TimeOffBalance API | Backend Dev | 3 |
| TASK-012 | Approval State Machine | Backend Dev | 2 |
| TASK-013 | Policy Assignment | Backend Dev | 3 |
| TASK-014 | Balance Recalculation | Backend Dev | 2 |
| **Sprint 2 Total** | | | **22 SP** |

### Sprint 3 (Weeks 5-6): Accrual Engine + Frontend Core

| Task ID | Description | Agent | Points |
|---------|-------------|-------|--------|
| TASK-015 | Accrual Command | Backend Dev | 5 |
| TASK-016 | Per-Hour-Worked Mode | Backend Dev | 2 |
| TASK-017 | Carryover Command | Backend Dev | 3 |
| TASK-018 | TypeScript Types | Frontend Dev | 1 |
| TASK-019 | Pinia Store | Frontend Dev | 5 |
| TASK-020 | Web Routes & Page | Frontend Dev | 2 |
| TASK-021 | Balance Dashboard | Frontend Dev | 4 |
| TASK-022 | Request Form | Frontend Dev | 5 |
| **Sprint 3 Total** | | | **27 SP** |

### Sprint 4 (Weeks 7-8): Frontend Completion + Testing

| Task ID | Description | Agent | Points |
|---------|-------------|-------|--------|
| TASK-023 | Request List Components | Frontend Dev | 4 |
| TASK-024 | Policy Admin Components | Frontend Dev | 4 |
| TASK-025 | Holiday Admin Components | Frontend Dev | 3 |
| TASK-026 | Attendance Service | Backend Dev | 4 |
| TASK-027 | Attendance Frontend | Frontend Dev | 4 |
| TASK-028 | Policy/Holiday API Tests | Backend Dev | 5 |
| TASK-029 | Request/Balance API Tests | Backend Dev | 6 |
| TASK-030 | Service Unit Tests | Backend Dev | 4 |
| TASK-031 | Scheduled Command Tests | Backend Dev | 3 |
| TASK-032 | Frontend Component Tests | Frontend Dev | 4 |
| TASK-033 | E2E Tests | Frontend Dev | 4 |
| **Sprint 4 Total** | | | **46 SP** |

## Execution Order (Dependency-Respecting)

### Parallelization Opportunities

The following task groups can be executed in parallel:

**Parallel Group A (no dependencies)**:
- TASK-001 (Migrations)
- TASK-003 (Enums)
- TASK-005 (Permissions)

**Parallel Group B (after TASK-001)**:
- TASK-002 (Models) -- depends on TASK-001 + TASK-003

**Parallel Group C (after TASK-002 + TASK-005)**:
- TASK-006 (Policy API) -- depends on TASK-002, TASK-003, TASK-005
- TASK-007 (Holiday API) -- depends on TASK-002, TASK-005

**Parallel Group D (after TASK-009)**:
- TASK-010 (Request API)
- TASK-011 (Balance API)
- TASK-013 (Policy Assignment)
- TASK-014 (Balance Recalculation)
- TASK-015 (Accrual Command)
- TASK-026 (Attendance Service)

**Parallel Group E (Frontend, after TASK-018)**:
- TASK-019 (Pinia Store) -- then all frontend components can branch

### Critical Path

```
TASK-001 -> TASK-002 -> TASK-009 -> TASK-010 -> TASK-018 -> TASK-019 -> TASK-020 -> TASK-022 -> TASK-033
(6h)       (8h)       (12h)       (12h)       (3h)        (10h)       (4h)        (10h)       (8h)
Total: 73 hours on critical path
```

## Dependency Risk Assessment

| Risk | Affected Tasks | Mitigation |
|------|---------------|------------|
| TASK-001 (Migrations) delayed | Blocks TASK-002, TASK-004, and all downstream | Prioritize as first task; simple schema with no external dependencies |
| TASK-009 (TimeOffService) delayed | Blocks TASK-010, TASK-011, TASK-013-017, TASK-026 | Largest bottleneck; assign most experienced backend dev; consider splitting into smaller services |
| TASK-019 (Pinia Store) delayed | Blocks all frontend components (TASK-020 through TASK-027) | Can stub API calls initially; build store incrementally per feature area |
| Sprint 4 overloaded (46 SP) | Testing tasks concentrated in final sprint | Start writing tests alongside feature development; move TASK-028-031 earlier if backend completes ahead |

## Status Assignment Logic

All tasks are assigned **To Do** status because:
- No tasks have been started
- No external dependencies are blocking any task
- Dependencies between tasks are internal and follow sequential execution order
- A task whose dependency is "To Do" is itself "To Do" (not "Blocked"), since the dependency will be completed before the dependent task starts per the sprint plan
