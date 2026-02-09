# Sprint Plan: Feature 07 -- PTO & Time Off

**Generated**: 2026-02-06
**Feature Branch**: `feature/07-pto-time-off`
**PRD Reference**: `.features/07-pto-time-off/PRD.md`
**Architecture Reference**: `.features/07-pto-time-off/ARCHITECTURE.md`
**Shared Foundations Reference**: `.features/SHARED-FOUNDATIONS.md`

---

## 1. Executive Summary

### Feature Overview

The PTO & Time Off feature introduces absence management to Solidtime, enabling organizations to define time-off policies (vacation, sick leave, personal days, etc.), manage public holiday calendars, process automatic balance accruals, handle employee time-off requests through an approval workflow, and provide attendance/overtime visibility. This is the first feature in Solidtime to introduce an approval workflow, establishing patterns (shared `ApprovalStatus` enum, `HasApprovalWorkflow` trait) that will be reused by Features 01 (Timesheet Approvals) and 02 (Expense Management).

### Effort Summary

| Metric | Value |
|--------|-------|
| **Total Tasks** | 33 feature tasks + 7 shared foundation tasks |
| **Total Story Points** | 117 SP (feature) + 15 SP (foundations) = 132 SP |
| **Total Estimated Hours** | ~230 hours (feature) + ~30 hours (foundations) = ~260 hours |
| **Sprint Duration** | 2 weeks per sprint |
| **Number of Sprints** | 4 sprints (8 weeks total) |
| **Story Point Ratio** | 2.0 hours per story point (per SF-10) |

### Team Size Assumptions

| Role | Count | Allocation | Notes |
|------|-------|------------|-------|
| Backend Developer | 1 | 100% dedicated | PHP/Laravel, API, services, commands |
| Frontend Developer | 1 | 100% dedicated | Vue 3, TypeScript, Pinia, UI components |
| QA Engineer | 1 | 50% shared | E2E tests, manual validation (Sprint 3-4) |

**Capacity per sprint (2 weeks)**: ~60-70 productive hours per developer (accounting for meetings, code review, and overhead at ~80% utilization of 80 hours).

---

## 2. Sprint Overview Table

| Sprint # | Name | Duration | Story Points | Key Deliverables |
|----------|------|----------|:------------:|------------------|
| 0 | Shared Foundations | 1 week (pre-sprint) | 15 SP | Notification infrastructure, modular permissions, weekly_capacity migrations, ApprovalStatus enum, HasApprovalWorkflow trait |
| 1 | Database & API Foundation | Weeks 1-2 | 22 SP | 4 migrations, 4 models, 3 enums, 4 factories, permissions, Policy CRUD API, Holiday CRUD API, route registration |
| 2 | Business Logic & Request Lifecycle | Weeks 3-4 | 22 SP | TimeOffService, request lifecycle API, balance API, state machine, policy assignment, balance recalculation |
| 3 | Accrual Engine & Frontend Core | Weeks 5-6 | 33 SP | Accrual command, per-hour-worked mode, carryover command, TypeScript types, Pinia store, web routes, balance dashboard, request form, reminder command tests, frontend component tests |
| 4 | Frontend Completion, Attendance & Testing | Weeks 7-8 | 40 SP | Request list, policy admin, holiday admin, attendance backend + frontend, API endpoint tests, service unit tests, scheduled command tests, E2E tests |
| **Total** | | **8 weeks** | **132 SP** | |

> **Note on Sprint 3 rebalancing (AMD-08)**: PTO-032 (Frontend Component Tests, 4 SP) and PTO-031 (Reminder Command Tests, 3 SP) were moved from Sprint 4 to Sprint 3 to reduce Sprint 4 overload from 46 SP to 40 SP. Sprint 3 absorbs 7 SP (from 27 to 33 SP, adjusted from the original rebalancing estimate).

---

## 3. Dependency Map

### 3.1 Shared Foundation Prerequisites

The following FOUND-xxx tasks from `.features/SHARED-FOUNDATIONS.md` **must be completed before Sprint 1 begins**:

| Foundation Task | Description | Effort | Blocks (PTO Tasks) |
|----------------|-------------|--------|---------------------|
| FOUND-001 | Notification infrastructure migration (`notifications` table, `notification_preferences` on members) | 2h / 1 SP | FOUND-002 |
| FOUND-002 | Base notification classes (`BaseNotification`) | 4h / 2 SP | PTO-010 (notifications on status transitions) |
| FOUND-003 | Notification bell UI component (`NotificationBell.vue`) | 8h / 4 SP | PTO-020 (frontend notification integration) |
| FOUND-004 | Notification API endpoints (list, mark read, unread count) | 6h / 3 SP | FOUND-003 |
| FOUND-005 | Notification preferences in organization settings | 4h / 2 SP | -- (nice-to-have, can follow) |
| FOUND-006 | Shared `weekly_capacity` migration on members + `default_weekly_capacity` + `fiscal_year_start_month` on organizations | 2h / 1 SP | PTO-026 (attendance service uses weekly_capacity) |
| FOUND-007 | Modular permissions infrastructure (`app/Permissions/` directory pattern) | 4h / 2 SP | PTO-005 (permissions registration) |

**Dependency chain**: FOUND-001 --> FOUND-002 --> FOUND-003/FOUND-004 (parallel) --> FOUND-005

**Critical path for PTO**: FOUND-007 must be done before PTO-005. FOUND-001/002 must be done before PTO-010 sends notifications. FOUND-006 must be done before PTO-026.

### 3.2 Intra-Feature Dependency Graph

```
PTO-001 (Migrations) ─────┐
PTO-003 (Enums) ──────────┤
                           ├──> PTO-002 (Models) ──────────────────────────────────┐
PTO-005 (Permissions) ─────┤                                                       │
                           ├──> PTO-006 (Policy CRUD) ──┐                          │
                           ├──> PTO-007 (Holiday CRUD) ──┤                         │
                           │                             ├──> PTO-008 (Routes) ────┤
                           │                             │                         │
                           │                PTO-007 ─────┤                         │
                           │                             │                         │
                           │                             └──> PTO-009 (Service) ───┤
                           │                                       │               │
PTO-004 (Factories) <──────┘                                       │               │
       │                                                           │               │
       │                                    ┌──────────────────────┤               │
       │                                    │                      │               │
       │                              PTO-010 (Request API) ───> PTO-012 (State Machine)
       │                              PTO-011 (Balance API)
       │                              PTO-013 (Policy Assignment)
       │                              PTO-014 (Balance Recalc)
       │                                    │
       │                              PTO-015 (Accrual Cmd) ──> PTO-016 (Per-Hour)
       │                                    │
       │                              PTO-017 (Carryover Cmd)
       │                                    │
       │                              PTO-026 (Attendance Svc)
       │                                    │
       │               PTO-006,007,010,011 ──> PTO-018 (TS Types)
       │                                              │
       │                                        PTO-019 (Pinia Store)
       │                                              │
       │                                   ┌──────────┤──────────┐
       │                                   │          │          │
       │                             PTO-020 (Routes) │    PTO-024 (Policy Admin)
       │                                   │          │    PTO-025 (Holiday Admin)
       │                             PTO-021 (Balance Dashboard)
       │                                   │
       │                             PTO-022 (Request Form)
       │                             PTO-023 (Request List)
       │                             PTO-027 (Attendance Frontend)
       │                                   │
       │                                   ├──> PTO-032 (Component Tests)
       │                                   └──> PTO-033 (E2E Tests)
       │
       ├──> PTO-028 (Policy/Holiday API Tests)
       ├──> PTO-029 (Request/Balance API Tests)
       ├──> PTO-030 (Service Unit Tests)
       └──> PTO-031 (Command Tests)
```

### 3.3 Critical Path

```
PTO-001 ──> PTO-002 ──> PTO-009 ──> PTO-010 ──> PTO-018 ──> PTO-019 ──> PTO-020 ──> PTO-022 ──> PTO-033
  (6h)        (8h)        (12h)       (12h)        (3h)       (10h)        (4h)       (10h)        (8h)

Total critical path: 73 hours
```

This path flows from database schema through backend services, into the frontend layer, and ends with E2E validation. Any delay on PTO-009 (TimeOffService) is the highest-impact bottleneck, as it gates 7 downstream tasks.

### 3.4 External Feature Dependencies

| External Feature | Dependency Type | Impact on PTO | Notes |
|-----------------|----------------|---------------|-------|
| Feature 01 (Timesheet Approvals) | Soft -- shared patterns | PTO establishes `ApprovalStatus` and `HasApprovalWorkflow` first; Feature 01 reuses them | PTO is Phase 2a, Feature 01 is Phase 1b; no blocking dependency |
| Feature 08 (Resource Scheduling) | Soft -- PTO provides data | Feature 08 calls `TimeOffRequest::getApprovedDaysInRange()` for capacity reduction | PTO works standalone; Feature 08 gains PTO awareness later |
| Feature 10 (Teams & Groups) | Soft -- scoping enhancement | `team_ids` filter on request list endpoint; manager sees team requests only | PTO works standalone; team filtering is additive when Feature 10 ships |

---

## 4. Sprint Details

---

### Sprint 0: Shared Foundations (Pre-Sprint, 1 Week)

**Sprint Goal**: Establish the cross-feature infrastructure (notifications, permissions, shared migrations) that PTO and other features depend on.

**Note**: This sprint is a prerequisite shared across all features. It may already be complete or in progress if another feature (e.g., Feature 01 Timesheet Approvals) has started. If foundations are already built, Sprint 1 can begin immediately.

#### Tasks

| Task ID | Description | Effort | SP | Dependencies | Assignee Role |
|---------|-------------|--------|:--:|--------------|---------------|
| FOUND-001 | Notification infrastructure migration (`notifications` table, `notification_preferences` JSON on members) | 2h | 1 | None | Backend |
| FOUND-002 | Base notification classes (`BaseNotification` extending Laravel's `Notification`, respecting member preferences) | 4h | 2 | FOUND-001 | Backend |
| FOUND-003 | Notification bell UI component (`NotificationBell.vue` in AppLayout header, polling, mark-as-read) | 8h | 4 | FOUND-002, FOUND-004 | Frontend |
| FOUND-004 | Notification API endpoints (list paginated, mark read, mark all read, unread count) | 6h | 3 | FOUND-002 | Backend |
| FOUND-005 | Notification preferences in organization settings (per-member email toggle) | 4h | 2 | FOUND-002 | Frontend |
| FOUND-006 | Shared `weekly_capacity` migration on members (default 144000s = 40h), `default_weekly_capacity` on organizations, `fiscal_year_start_month` on organizations (AMD-07) | 2h | 1 | None | Backend |
| FOUND-007 | Modular permissions infrastructure (`app/Permissions/` directory, refactor existing permissions into modular pattern) | 4h | 2 | None | Backend |

**Total**: 30 hours / 15 SP

#### Acceptance Criteria

- [ ] `php artisan migrate` creates the `notifications` table and adds `notification_preferences` to `members`
- [ ] `BaseNotification` class exists and sends via `database` + `mail` channels
- [ ] `NotificationBell.vue` renders in the AppLayout header with unread count badge
- [ ] Notification API endpoints return paginated notifications with mark-read functionality
- [ ] `members.weekly_capacity` column exists with default 144000
- [ ] `organizations.default_weekly_capacity` and `fiscal_year_start_month` columns exist
- [ ] `app/Permissions/` directory exists with at least one example permissions class
- [ ] `JetstreamServiceProvider` calls modular permission registration

#### Deliverables

**Backend files created**:
- `database/migrations/2026_02_28_000001_add_weekly_capacity_to_members.php`
- `database/migrations/2026_02_28_000002_add_default_weekly_capacity_to_organizations.php`
- `database/migrations/2026_02_28_000003_create_notifications_table.php`
- `database/migrations/2026_02_28_000004_add_notification_preferences_to_members.php`
- `app/Notifications/BaseNotification.php`
- `app/Http/Controllers/Api/V1/NotificationController.php`
- `app/Permissions/` directory structure
- `app/Enums/ApprovalStatus.php`
- `app/Traits/HasApprovalWorkflow.php`

**Frontend files created**:
- `resources/js/packages/ui/src/Notification/NotificationBell.vue`
- `resources/js/utils/useNotifications.ts`

#### Risk Factors

- If multiple feature teams are starting concurrently, merge conflicts on `JetstreamServiceProvider.php` are possible. The modular permissions pattern (FOUND-007) mitigates this.
- The shared `ApprovalStatus` enum and `HasApprovalWorkflow` trait are established here for the first time in the codebase; careful design is required since Features 01 and 02 will also depend on them.

---

### Sprint 1: Database & API Foundation (Weeks 1-2)

**Sprint Goal**: Establish the complete database schema, Eloquent models, enums, factories, permissions, and CRUD APIs for policies and holidays -- everything needed for the backend foundation of PTO.

#### Tasks

| Task ID | Description | Effort | SP | Dependencies | Assignee Role |
|---------|-------------|--------|:--:|--------------|---------------|
| PTO-001 | Database migrations for 4 tables: `time_off_policies`, `time_off_requests`, `time_off_balances`, `holidays` (date prefix `2026_03_07_`) | 6h | 3 | None (FOUND-006 for weekly_capacity) | Backend |
| PTO-003 | PHP enums: `TimeOffType`, `AccrualFrequency` (AMD-06 removes `OvertimeRuleType`; `ApprovalStatus` is shared from Sprint 0) | 2h | 1 | None | Backend |
| PTO-005 | Register 18 permissions via `app/Permissions/TimeOffPermissions.php` (modular pattern per AMD-03/SF-08) | 3h | 2 | FOUND-007 | Backend |
| PTO-002 | Eloquent models: `TimeOffPolicy`, `TimeOffRequest`, `TimeOffBalance`, `Holiday` with relationships, casts, PHPDoc, `HasApprovalWorkflow` trait on `TimeOffRequest` | 8h | 4 | PTO-001, PTO-003 | Backend |
| PTO-004 | Model factories for all 4 models with fluent `forOrganization()`, `forMember()`, `forPolicy()` builder methods | 4h | 2 | PTO-002, PTO-003 | Backend |
| PTO-006 | TimeOffPolicy CRUD API: Controller, StoreRequest, UpdateRequest, Resource, Collection. Delete returns 409 if requests/balances exist. | 10h | 5 | PTO-002, PTO-003, PTO-005 | Backend |
| PTO-007 | Holiday CRUD API: Controller, Requests, Resources. Year-based filtering, recurring holiday expansion, leap year handling. | 6h | 3 | PTO-002, PTO-005 | Backend |
| PTO-008 | Register all PTO API routes in `routes/api.php` following naming convention `v1.{feature}.{action}` | 2h | 1 | PTO-006, PTO-007 | Backend |

**Total**: 42 hours / 22 SP (Note: 1 developer; this is backend-heavy)

#### Execution Order

```
Day 1-2:   PTO-001 (migrations) + PTO-003 (enums) + PTO-005 (permissions) [parallel, no deps]
Day 3-4:   PTO-002 (models) [depends on PTO-001, PTO-003]
Day 5:     PTO-004 (factories) [depends on PTO-002]
Day 5-7:   PTO-006 (policy API) [depends on PTO-002, PTO-005]
Day 7-8:   PTO-007 (holiday API) [depends on PTO-002, PTO-005]
Day 9:     PTO-008 (routes) [depends on PTO-006, PTO-007]
Day 10:    Buffer / code review / `composer fix && composer analyse`
```

> **Frontend developer** during Sprint 1: Can work on Sprint 0 frontend tasks (FOUND-003, FOUND-005) if not yet complete, or begin early exploration of the Time Off UI design and component structure.

#### Acceptance Criteria

- [ ] `php artisan migrate` creates all 4 PTO tables with correct columns, indexes, and constraints
- [ ] `php artisan migrate:rollback` cleanly reverses all PTO migrations
- [ ] All 4 Eloquent models have complete relationships, casts, and PHPDoc annotations
- [ ] `composer analyse` passes with no errors on all new PHP files
- [ ] All 4 factories create valid records that satisfy all database constraints
- [ ] `GET /api/v1/organizations/{org}/time-off-policies` returns paginated list scoped to organization
- [ ] `POST/PUT/DELETE` for policies and holidays enforce permission checks and organization scoping
- [ ] `DELETE` policy returns 409 Conflict if balances or requests reference it
- [ ] Recurring holidays expand correctly for the requested year
- [ ] `php artisan route:list` shows all new PTO routes with correct names
- [ ] All 18 permissions registered and assigned to correct roles per the access control matrix

#### Deliverables

**Files created**:
- `database/migrations/2026_03_07_000001_create_time_off_policies_table.php`
- `database/migrations/2026_03_07_000002_create_holidays_table.php`
- `database/migrations/2026_03_07_000003_create_time_off_balances_table.php`
- `database/migrations/2026_03_07_000004_create_time_off_requests_table.php`
- `app/Enums/TimeOffType.php`
- `app/Enums/AccrualFrequency.php`
- `app/Models/TimeOffPolicy.php`
- `app/Models/TimeOffRequest.php`
- `app/Models/TimeOffBalance.php`
- `app/Models/Holiday.php`
- `database/factories/TimeOffPolicyFactory.php`
- `database/factories/TimeOffRequestFactory.php`
- `database/factories/TimeOffBalanceFactory.php`
- `database/factories/HolidayFactory.php`
- `app/Permissions/TimeOffPermissions.php`
- `app/Http/Controllers/Api/V1/TimeOffPolicyController.php`
- `app/Http/Controllers/Api/V1/HolidayController.php`
- `app/Http/Requests/V1/TimeOffPolicy/TimeOffPolicyStoreRequest.php`
- `app/Http/Requests/V1/TimeOffPolicy/TimeOffPolicyUpdateRequest.php`
- `app/Http/Requests/V1/Holiday/HolidayStoreRequest.php`
- `app/Http/Requests/V1/Holiday/HolidayUpdateRequest.php`
- `app/Http/Requests/V1/Holiday/HolidayIndexRequest.php`
- `app/Http/Resources/V1/TimeOffPolicy/TimeOffPolicyResource.php`
- `app/Http/Resources/V1/TimeOffPolicy/TimeOffPolicyCollection.php`
- `app/Http/Resources/V1/Holiday/HolidayResource.php`
- `app/Http/Resources/V1/Holiday/HolidayCollection.php`

**Files modified**:
- `routes/api.php` (add policy and holiday routes)
- `app/Providers/JetstreamServiceProvider.php` (call `TimeOffPermissions::register()`)

#### Risk Factors

- **Schema design lock-in**: Changing table schemas after Sprint 1 requires additional migrations. The schema has been thoroughly reviewed in the architecture document; however, any late requirement changes should be flagged immediately.
- **Backend developer overload**: All 22 SP fall on one backend developer. If the developer is unavailable for more than 2 days, Sprint 1 is at risk. Mitigation: PTO-004 (factories) can be deferred to Sprint 2 if needed, as tests that depend on factories are in Sprint 4.

---

### Sprint 2: Business Logic & Request Lifecycle (Weeks 3-4)

**Sprint Goal**: Implement the core business logic service (TimeOffService), the complete request lifecycle (create, approve, deny, withdraw), balance management, and policy assignment -- enabling end-to-end time-off request processing via the API.

#### Tasks

| Task ID | Description | Effort | SP | Dependencies | Assignee Role |
|---------|-------------|--------|:--:|--------------|---------------|
| PTO-009 | TimeOffService: `calculateRequestHours()`, `hasSufficientBalance()`, `hasOverlappingRequest()`, `getOrCreateBalance()`, `applyApproval()`, `releasePendingHours()`, holiday-aware working day calculation | 12h | 6 | PTO-002, PTO-003, PTO-007 | Backend |
| PTO-010 | TimeOffRequest lifecycle API: Controller (`store`, `withdraw`, `approve`, `deny`, `index`), Requests, Resources, Exception classes. Auto-approve for no-approval policies. Sends notifications via BaseNotification (SF-04). | 12h | 6 | PTO-002, PTO-005, PTO-008, PTO-009 | Backend |
| PTO-011 | TimeOffBalance API: Controller (`me`, `index`, `adjust`), Resources. `me` returns own balances with policy details; `adjust` modifies `manual_adjustment` with audit trail. | 6h | 3 | PTO-002, PTO-005, PTO-008, PTO-009 | Backend |
| PTO-012 | TimeOffRequestStateMachine: Explicit status transition validation. Valid transitions: submitted-->approved, submitted-->rejected, submitted-->withdrawn. Invalid transitions throw exception. | 4h | 2 | PTO-010 | Backend |
| PTO-013 | Policy-to-member assignment: `POST /time-off-policies/{id}/assign` (individual), `POST /time-off-policies/{id}/assign-all` (all active members). Creates `TimeOffBalance` with `default_allowance`. | 6h | 3 | PTO-002, PTO-009 | Backend |
| PTO-014 | Balance recalculation service: Derives `used_hours` and `pending_hours` from actual request records. Admin-triggerable for data consistency repair. | 4h | 2 | PTO-009 | Backend |

**Total**: 44 hours / 22 SP (backend-heavy again)

#### Execution Order

```
Day 1-3:   PTO-009 (TimeOffService -- critical path, most complex) [depends on Sprint 1]
Day 3-5:   PTO-010 (Request API) [depends on PTO-009]
Day 4-5:   PTO-011 (Balance API) [parallel with PTO-010, both depend on PTO-009]
Day 6:     PTO-012 (State Machine) [depends on PTO-010]
Day 7-8:   PTO-013 (Policy Assignment) [depends on PTO-009]
Day 8-9:   PTO-014 (Balance Recalculation) [depends on PTO-009]
Day 10:    Buffer / code review / integration testing
```

> **Frontend developer** during Sprint 2: Begin PTO-018 (TypeScript types) as soon as PTO-006/007/010/011 API contracts are stable (mid-Sprint 2). Start exploring PTO-019 (Pinia store) structure and API client generation.

#### Acceptance Criteria

- [ ] `TimeOffService::calculateRequestHours()` correctly excludes weekends and organization holidays from the date range
- [ ] Creating a time-off request calculates hours, checks balance sufficiency, checks for overlapping requests
- [ ] Policies with `requires_approval = false` result in auto-approved requests with immediate balance deduction
- [ ] Policies with `requires_approval = true` result in `submitted` status with `pending_hours` incremented
- [ ] Approving a request moves `pending_hours` to `used_hours` and sends `TimeOffRequestApprovedNotification`
- [ ] Denying a request releases `pending_hours` and sends `TimeOffRequestDeniedNotification`
- [ ] Self-approval is prevented: `reviewer_id !== member_id` enforced at the service layer
- [ ] Withdrawing a request releases `pending_hours` and only works on `submitted` status requests
- [ ] `GET /time-off-balances/me` returns current user's balances with computed `available_hours`
- [ ] `POST /time-off-balances/{id}/adjust` modifies `manual_adjustment` field with audit logging
- [ ] Policy assignment creates balance records with `default_allowance`; duplicate assignments are no-ops
- [ ] Balance recalculation produces consistent values from actual request data
- [ ] All new routes registered in `routes/api.php` with `check-organization-blocked` on write endpoints
- [ ] `composer fix && composer analyse` passes on all new files

#### Deliverables

**Files created**:
- `app/Service/TimeOffService.php`
- `app/Service/TimeOffRequestStateMachine.php`
- `app/Http/Controllers/Api/V1/TimeOffRequestController.php`
- `app/Http/Controllers/Api/V1/TimeOffBalanceController.php`
- `app/Http/Requests/V1/TimeOffRequest/TimeOffRequestStoreRequest.php`
- `app/Http/Requests/V1/TimeOffRequest/TimeOffRequestApproveRequest.php`
- `app/Http/Requests/V1/TimeOffRequest/TimeOffRequestDenyRequest.php`
- `app/Http/Requests/V1/TimeOffBalance/TimeOffBalanceAdjustRequest.php`
- `app/Http/Requests/V1/TimeOffBalance/TimeOffBalanceIndexRequest.php`
- `app/Http/Resources/V1/TimeOffRequest/TimeOffRequestResource.php`
- `app/Http/Resources/V1/TimeOffRequest/TimeOffRequestCollection.php`
- `app/Http/Resources/V1/TimeOffBalance/TimeOffBalanceResource.php`
- `app/Http/Resources/V1/TimeOffBalance/TimeOffBalanceCollection.php`
- `app/Http/Requests/V1/TimeOffPolicy/TimeOffPolicyAssignRequest.php`
- `app/Exceptions/Api/InsufficientTimeOffBalanceApiException.php`
- `app/Exceptions/Api/OverlappingTimeOffRequestApiException.php`
- `app/Exceptions/Api/InvalidTimeOffRequestStatusTransitionApiException.php`
- `app/Notifications/TimeOffRequestSubmittedNotification.php`
- `app/Notifications/TimeOffRequestApprovedNotification.php`
- `app/Notifications/TimeOffRequestDeniedNotification.php`

**Files modified**:
- `routes/api.php` (add request, balance, and assignment routes)
- `app/Http/Controllers/Api/V1/TimeOffPolicyController.php` (add `assignToMember`, `assignToAll` methods)
- `app/Models/Member.php` (add `timeOffBalances()`, `timeOffRequests()` relationships)
- `app/Models/Organization.php` (add `timeOffPolicies()`, `holidays()` relationships)

#### Risk Factors

- **PTO-009 is the biggest bottleneck**: 12 hours of complex business logic. If this task slips, PTO-010, PTO-011, PTO-013, PTO-014 all slip. Mitigation: Assign the most experienced backend developer; consider splitting into `TimeOffService` (request logic) and `HolidayService` (holiday lookup) if needed.
- **Notification integration**: Requires FOUND-001/002 to be complete. If shared notification infrastructure is delayed, notifications can be stubbed with `// TODO: dispatch notification` and completed in Sprint 3.
- **API contract stability**: Frontend developer needs API contracts finalized by mid-Sprint 2 to start TypeScript types. Backend developer should provide Postman collection or OpenAPI stubs early.

---

### Sprint 3: Accrual Engine & Frontend Core (Weeks 5-6)

**Sprint Goal**: Complete the automated accrual engine (monthly accrual + per-hour-worked mode + year-end carryover), and launch the frontend application with TypeScript types, Pinia store, page routing, balance dashboard, and request form -- enabling the first user-facing interactions.

#### Tasks

| Task ID | Description | Effort | SP | Dependencies | Assignee Role |
|---------|-------------|--------|:--:|--------------|---------------|
| PTO-015 | Accrual scheduled command: `time-off:accrue-balances`. Monthly on 1st, idempotent via `last_accrual_date`, cap-aware, waiting period enforcement, placeholder exclusion, `--dry-run` support, config-gated. | 10h | 5 | PTO-002, PTO-009 | Backend |
| PTO-016 | Per-hour-worked accrual mode: Extend accrual engine to calculate accrual from previous month's `TimeEntry` hours * `accrual_amount`. | 4h | 2 | PTO-015 | Backend |
| PTO-017 | Year-end carryover command: `time-off:year-end-carryover`. Annual on Jan 1, transfers up to `max_carryover` hours, idempotent, config-gated. | 6h | 3 | PTO-015 | Backend |
| PTO-018 | TypeScript type definitions: `resources/js/types/time-off.d.ts` for all PTO API response shapes. | 3h | 1 | PTO-006, PTO-007, PTO-010, PTO-011 | Frontend |
| PTO-019 | Pinia store: `useTimeOffStore` with state (balances, myRequests, pendingRequests, policies, holidays), actions (loadBalances, createRequest, withdrawRequest, approveRequest, denyRequest), `@tanstack/vue-query` integration. | 10h | 5 | PTO-018 | Frontend |
| PTO-020 | Web routes (`routes/web.php`), `TimeOff.vue` page, `TimeOffSettings.vue` page, sidebar navigation item with `CalendarDaysIcon`, permission helper functions in `permissions.ts`. | 4h | 2 | PTO-019 | Frontend |
| PTO-021 | `TimeOffBalanceDashboard` and `TimeOffBalanceCard` components: Color-coded cards per policy showing accrued/used/pending/carryover/available breakdown. | 8h | 4 | PTO-019, PTO-020 | Frontend |
| PTO-022 | `TimeOffRequestForm` and `TimeOffRequestModal`: Policy selector, date range picker, auto-calculated hours preview, notes textarea, balance validation feedback. | 10h | 5 | PTO-019, PTO-021 | Frontend |
| PTO-031 | Scheduled command tests: Accrual command (idempotency, caps, waiting period, per-hour-worked) and carryover command (max carryover, idempotency). | 6h | 3 | PTO-015, PTO-017 | Backend |
| PTO-032 | Frontend component tests (Vitest): `TimeOffBalanceCard`, `TimeOffRequestForm`, `TimeOffRequestList` components. | 8h | 4 | PTO-021, PTO-022 | Frontend |

**Total**: 69 hours / 33 SP (split across backend and frontend)

#### Execution Order

**Backend track**:
```
Day 1-3:   PTO-015 (Accrual Command)
Day 3-4:   PTO-016 (Per-Hour-Worked Mode)
Day 4-6:   PTO-017 (Carryover Command)
Day 6-8:   PTO-031 (Command Tests)
Day 9-10:  Buffer / support frontend integration / code review
```

**Frontend track**:
```
Day 1:     PTO-018 (TypeScript Types)
Day 1-4:   PTO-019 (Pinia Store)
Day 4-5:   PTO-020 (Web Routes & Page Shell)
Day 5-7:   PTO-021 (Balance Dashboard)
Day 7-9:   PTO-022 (Request Form)
Day 9-10:  PTO-032 (Component Tests)
```

#### Acceptance Criteria

- [ ] `php artisan time-off:accrue-balances` processes all active policies and credits eligible members
- [ ] Accrual command is idempotent: running twice in the same month does not double-credit
- [ ] `max_balance` cap is enforced (excess forfeited)
- [ ] `waiting_period_days` is respected (new members skip accrual until waiting period passes)
- [ ] Placeholder members are excluded from accrual
- [ ] Per-hour-worked mode calculates accrual from previous month's TimeEntry hours
- [ ] `php artisan time-off:year-end-carryover` transfers hours up to `max_carryover` to new year balance
- [ ] Carryover command creates new year balance record with `carryover_hours` populated
- [ ] Both commands support `--dry-run` flag and are config-gated via `config/scheduling.php`
- [ ] Both commands registered in `app/Console/Kernel.php` with correct schedules
- [ ] TypeScript types match all API response shapes exactly
- [ ] Pinia store successfully fetches and caches balances, requests, policies, holidays
- [ ] "Time Off" sidebar navigation item appears for users with `time-off-requests:view:own` permission
- [ ] Time Off page renders balance cards per active policy with correct calculations
- [ ] Request form auto-calculates hours based on working days, showing real-time preview
- [ ] Request form validates balance sufficiency and overlapping dates client-side
- [ ] Component tests pass for `TimeOffBalanceCard`, `TimeOffRequestForm`
- [ ] Scheduled command tests validate idempotency, caps, waiting periods, and carryover limits

#### Deliverables

**Backend files created**:
- `app/Console/Commands/TimeOff/AccrueTimeOffBalancesCommand.php`
- `app/Console/Commands/TimeOff/ProcessYearEndCarryoverCommand.php`
- `app/Service/AccrualService.php`
- `app/Service/TimeOffBalanceService.php`
- `tests/Unit/Console/Commands/AccrueTimeOffBalancesCommandTest.php`
- `tests/Unit/Console/Commands/ProcessYearEndCarryoverCommandTest.php`

**Backend files modified**:
- `app/Console/Kernel.php` (register scheduled commands)
- `config/scheduling.php` (add `time_off_accrue_balances`, `time_off_year_end_carryover` flags)

**Frontend files created**:
- `resources/js/types/time-off.d.ts`
- `resources/js/utils/useTimeOff.ts`
- `resources/js/Pages/TimeOff.vue`
- `resources/js/Pages/TimeOffSettings.vue`
- `resources/js/packages/ui/src/TimeOff/TimeOffBalanceCard.vue`
- `resources/js/packages/ui/src/TimeOff/TimeOffBalanceDashboard.vue`
- `resources/js/packages/ui/src/TimeOff/TimeOffRequestForm.vue`
- `resources/js/packages/ui/src/TimeOff/TimeOffRequestModal.vue`
- `resources/js/packages/ui/src/TimeOff/__tests__/TimeOffBalanceCard.test.ts`
- `resources/js/packages/ui/src/TimeOff/__tests__/TimeOffRequestForm.test.ts`
- `resources/js/packages/ui/src/TimeOff/__tests__/TimeOffRequestList.test.ts`

**Frontend files modified**:
- `routes/web.php` (add Time Off page routes)
- `resources/js/Layouts/AppLayout.vue` (add Time Off sidebar navigation)
- `resources/js/utils/permissions.ts` (add `canViewTimeOff()`, `canApproveTimeOffRequests()`)

#### Risk Factors

- **Sprint 3 is the highest-effort sprint at 33 SP** across two developers. Both tracks must progress in parallel without blocking each other.
- **Frontend depends on stable API**: If Sprint 2 API contracts change, PTO-018 (types) and PTO-019 (store) require rework. Mitigation: API contracts should be frozen at Sprint 2 end.
- **Per-hour-worked accrual (PTO-016)** requires querying `TimeEntry` data from the previous month, which introduces a cross-model dependency. Edge cases (no time entries, partial months) need careful testing.
- **Date range picker** in PTO-022 is a complex UI component. Consider using an existing library (e.g., `v-calendar` or `@vuepic/vue-datepicker`) rather than building from scratch.

---

### Sprint 4: Frontend Completion, Attendance & Testing (Weeks 7-8)

**Sprint Goal**: Complete all remaining frontend components (request list, policy admin, holiday admin, attendance), implement the attendance service and page, and achieve full test coverage across API endpoint tests, service unit tests, and E2E tests.

#### Tasks

| Task ID | Description | Effort | SP | Dependencies | Assignee Role |
|---------|-------------|--------|:--:|--------------|---------------|
| PTO-023 | `TimeOffRequestList`, `TimeOffRequestRow`, `TimeOffPendingReviewList` components: Table with date range, policy, hours, status, actions. Expandable rows for notes/comments. | 8h | 4 | PTO-019, PTO-020 | Frontend |
| PTO-024 | `TimeOffPolicyList`, `TimeOffPolicyForm`, `TimeOffPolicyModal` (admin): CRUD interface for policies with all accrual settings. | 8h | 4 | PTO-019 | Frontend |
| PTO-025 | `HolidayList` and `HolidayForm` components: Admin CRUD for holidays, recurring toggle, year display. | 6h | 3 | PTO-019 | Frontend |
| PTO-026 | `AttendanceService` and `AttendanceController` (backend): Compute daily expected vs. actual hours from TimeEntries, holidays, and approved time-off. Uses `weekly_capacity` from FOUND-006. | 8h | 4 | PTO-009, FOUND-006 | Backend |
| PTO-027 | Attendance page, grid components, and `useAttendance` store: Date range grid showing expected/actual/overtime per day, holiday/time-off visual markers, weekly/monthly totals. | 8h | 4 | PTO-026, PTO-019 | Frontend |
| PTO-028 | API endpoint tests: `TimeOffPolicyEndpointTest` and `HolidayEndpointTest`. CRUD operations, permission checks, organization scoping, delete-with-dependencies (409). | 10h | 5 | PTO-006, PTO-007, PTO-004 | Backend |
| PTO-029 | API endpoint tests: `TimeOffRequestEndpointTest` and `TimeOffBalanceEndpointTest`. Request lifecycle, approval workflow, self-approval prevention, balance operations. | 12h | 6 | PTO-010, PTO-011, PTO-004 | Backend |
| PTO-030 | Unit tests: `TimeOffServiceTest`, `TimeOffRequestStateMachineTest`, `AccrualServiceTest`, `TimeOffBalanceServiceTest`. Cover all edge cases. | 8h | 4 | PTO-009, PTO-012, PTO-014 | Backend |
| PTO-033 | E2E Playwright tests: 6 critical user flow scenarios (create policy, request time off, approve request, view balances, manage holidays, attendance view). | 8h | 4 | PTO-020, PTO-021, PTO-022, PTO-023 | Frontend |

**Total**: 78 hours / 40 SP (heaviest sprint, split across both developers)

#### Execution Order

**Backend track**:
```
Day 1-2:   PTO-026 (Attendance Service)
Day 2-4:   PTO-028 (Policy/Holiday API Tests)
Day 4-7:   PTO-029 (Request/Balance API Tests)
Day 7-9:   PTO-030 (Service Unit Tests)
Day 9-10:  Buffer / bug fixes from E2E testing
```

**Frontend track**:
```
Day 1-2:   PTO-023 (Request List Components)
Day 2-4:   PTO-024 (Policy Admin Components)
Day 4-5:   PTO-025 (Holiday Admin Components)
Day 5-7:   PTO-027 (Attendance Frontend)
Day 7-9:   PTO-033 (E2E Tests)
Day 9-10:  Bug fixes / polish / integration verification
```

#### Acceptance Criteria

- [ ] Request list shows all own requests with status, dates, hours, and action buttons
- [ ] Pending approval section visible to Managers/Admins with Approve/Deny buttons
- [ ] Policy admin page allows creating/editing/archiving/deleting policies with all accrual settings
- [ ] Holiday admin page allows CRUD operations on holidays with recurring toggle
- [ ] Attendance endpoint returns daily data: expected hours, actual hours (from TimeEntries), overtime, holiday flag, time-off flag
- [ ] Attendance page renders date-range grid with visual markers for holidays and time-off days
- [ ] Week and month totals are computed correctly on the attendance page
- [ ] Employee role sees only own attendance; Manager+ sees team attendance
- [ ] **API endpoint tests** cover: all CRUD operations, all permission levels (Owner/Admin/Manager/Employee/unauthenticated), organization scoping, edge cases (delete policy with dependencies, overlapping requests, insufficient balance, self-approval prevention)
- [ ] **Service unit tests** cover: `calculateRequestHours` with holidays and weekends, balance sufficiency checks, state machine transitions (all valid and invalid), accrual calculations with caps and waiting periods, carryover with limits
- [ ] **E2E tests** cover 6 critical flows and pass on CI
- [ ] All tests pass: `php artisan test`, `npm run test`, `npx playwright test`
- [ ] `composer fix && composer analyse` passes
- [ ] `npm run lint:fix && npm run format` passes

#### Deliverables

**Backend files created**:
- `app/Service/AttendanceService.php`
- `app/Http/Controllers/Api/V1/AttendanceController.php`
- `app/Service/HolidayService.php`
- `tests/Unit/Endpoint/Api/V1/TimeOffPolicyEndpointTest.php`
- `tests/Unit/Endpoint/Api/V1/HolidayEndpointTest.php`
- `tests/Unit/Endpoint/Api/V1/TimeOffRequestEndpointTest.php`
- `tests/Unit/Endpoint/Api/V1/TimeOffBalanceEndpointTest.php`
- `tests/Unit/Service/TimeOffServiceTest.php`
- `tests/Unit/Service/AccrualServiceTest.php`
- `tests/Unit/Service/TimeOffBalanceServiceTest.php`
- `tests/Unit/Service/HolidayServiceTest.php`

**Frontend files created**:
- `resources/js/packages/ui/src/TimeOff/TimeOffRequestList.vue`
- `resources/js/packages/ui/src/TimeOff/TimeOffRequestRow.vue`
- `resources/js/packages/ui/src/TimeOff/TimeOffPendingReviewList.vue`
- `resources/js/packages/ui/src/TimeOff/TimeOffPolicyList.vue`
- `resources/js/packages/ui/src/TimeOff/TimeOffPolicyForm.vue`
- `resources/js/packages/ui/src/TimeOff/TimeOffPolicyModal.vue`
- `resources/js/packages/ui/src/TimeOff/HolidayList.vue`
- `resources/js/packages/ui/src/TimeOff/HolidayForm.vue`
- `resources/js/packages/ui/src/TimeOff/HolidayCalendar.vue`
- `resources/js/packages/ui/src/Attendance/AttendanceGrid.vue`
- `resources/js/Pages/Attendance.vue`
- `resources/js/utils/useAttendance.ts`
- `resources/js/packages/ui/src/TimeOff/__tests__/HolidayCalendar.test.ts`
- `e2e/time-off.spec.ts`

**Files modified**:
- `routes/api.php` (add attendance route)
- `routes/web.php` (add attendance page route)

#### Risk Factors

- **Sprint 4 is at 40 SP, the heaviest sprint**. Both developers must be fully productive. Any spillover from Sprint 3 will compound here.
- **E2E test environment**: Playwright tests require a running application with seeded test data. Ensure Docker Compose setup and test database seeding work reliably.
- **Test writing is often underestimated**: PTO-028 (5 SP) and PTO-029 (6 SP) together are 22 hours of test writing. These cover complex permission matrices and edge cases. Budget adequate time.
- **Integration bugs**: Sprint 4 is the first time frontend and backend are fully integrated. Expect 1-2 days of integration bug fixing.

---

## 5. Testing Strategy Per Sprint

### Overview

| Sprint | Backend Tests | Frontend Tests | Integration Tests | E2E Tests |
|--------|:------------:|:--------------:|:-----------------:|:---------:|
| 0 | Notification API unit tests | Notification bell component test | -- | -- |
| 1 | Manual API verification (Postman) | -- | -- | -- |
| 2 | Manual API verification; begin writing test stubs | -- | Backend integration smoke tests | -- |
| 3 | PTO-031: Command tests (accrual, carryover) | PTO-032: Component tests (BalanceCard, RequestForm, RequestList) | -- | -- |
| 4 | PTO-028: Policy/Holiday endpoint tests; PTO-029: Request/Balance endpoint tests; PTO-030: Service unit tests | -- | Full backend integration test suite | PTO-033: 6 critical E2E flows |

### Detailed Testing Timeline

**Sprint 1 -- No formal tests written**
- Backend developer manually verifies all CRUD endpoints via Postman/curl
- Factory validity verified by creating records in `php artisan tinker`
- `composer analyse` validates static analysis

**Sprint 2 -- Test stubs prepared**
- Backend developer creates test file stubs with `@test` annotations and `TODO` comments
- Manual API testing validates request lifecycle (create, approve, deny, withdraw)
- Self-approval prevention validated manually

**Sprint 3 -- First formal tests**
- **PTO-031** (Backend, 6h/3SP): Scheduled command tests
  - `AccrueTimeOffBalancesCommandTest`: idempotency, cap enforcement, waiting period, placeholder exclusion, per-hour-worked mode, prorated first accrual
  - `ProcessYearEndCarryoverCommandTest`: max carryover, no carryover policy, idempotency, fiscal year boundary
- **PTO-032** (Frontend, 8h/4SP): Vitest component tests
  - `TimeOffBalanceCard.test.ts`: Renders balance breakdown, color-coded, handles zero balance
  - `TimeOffRequestForm.test.ts`: Date picker interaction, hours auto-calculation, validation messages
  - `TimeOffRequestList.test.ts`: Renders rows, status badges, action buttons based on permissions

**Sprint 4 -- Full test coverage**
- **PTO-028** (Backend, 10h/5SP): Policy and Holiday endpoint tests
  - CRUD operations for all 4 endpoints
  - Permission matrix testing (all 5 roles + unauthenticated)
  - Organization scoping (cannot access another org's data)
  - Delete-with-dependencies returns 409
  - Unique constraint validation (duplicate policy name, duplicate holiday date)
- **PTO-029** (Backend, 12h/6SP): Request and Balance endpoint tests
  - Request creation (happy path, insufficient balance, overlapping dates, inactive policy)
  - Approval workflow (approve, deny, withdraw, self-approval prevention)
  - Auto-approval for no-approval-required policies
  - Balance `me` endpoint returns only own balances
  - Balance adjustment with audit trail
  - Status transition validation (invalid transitions return error)
- **PTO-030** (Backend, 8h/4SP): Service unit tests
  - `TimeOffServiceTest`: calculateRequestHours (weekends, holidays, leap year), hasSufficientBalance, hasOverlappingRequest, applyApproval, releasePendingHours
  - `TimeOffRequestStateMachineTest`: all valid transitions, all invalid transitions
  - `AccrualServiceTest`: monthly accrual, annually prorated, per-hour-worked
  - `TimeOffBalanceServiceTest`: getOrCreateBalance, recalculateBalance, adjustBalance
- **PTO-033** (Frontend, 8h/4SP): E2E Playwright tests
  - Scenario 1: Admin creates a vacation policy with monthly accrual
  - Scenario 2: Admin adds organization holidays
  - Scenario 3: Employee submits a time-off request and sees pending status
  - Scenario 4: Manager approves a time-off request; requester sees approved status and updated balance
  - Scenario 5: Employee views balance dashboard with correct calculations
  - Scenario 6: Manager views attendance grid with time-off days marked

### Test Coverage Targets

| Layer | Target | Measured By |
|-------|--------|-------------|
| API Endpoints | 100% of endpoints covered | All controller methods tested with happy path + error cases |
| Services | 90%+ line coverage | PHPUnit code coverage report |
| Commands | 100% of commands covered | All scheduled commands tested with edge cases |
| Frontend Components | 80%+ of interactive components | Vitest coverage report |
| E2E Flows | 6 critical user journeys | Playwright test count |

---

## 6. Definition of Done

### 6.1 Per-Task Definition of Done

- [ ] Code implements the task description and meets all acceptance criteria listed in the PRD
- [ ] Code follows existing codebase patterns (as documented in CLAUDE.md and CODEBASE-ANALYSIS.md)
- [ ] PHP files include `declare(strict_types=1)` at the top
- [ ] Eloquent models include complete PHPDoc `@property` annotations
- [ ] All database queries are scoped to `organization_id` via `whereBelongsTo($organization, 'organization')`
- [ ] `composer fix && composer analyse` passes with no errors
- [ ] `npm run lint:fix && npm run format` passes with no errors (for frontend tasks)
- [ ] Code reviewed by at least one team member
- [ ] Task branch merged into `feature/07-pto-time-off` via pull request

### 6.2 Per-Sprint Definition of Done

- [ ] All tasks in the sprint are individually Done (per 6.1 checklist)
- [ ] Sprint acceptance criteria (listed in Section 4 per sprint) are verified
- [ ] No regressions: existing test suite continues to pass (`php artisan test`)
- [ ] API endpoints manually tested and producing correct responses
- [ ] `php artisan migrate:fresh --seed` runs cleanly with all new migrations
- [ ] Sprint demo completed with stakeholders
- [ ] Technical debt items documented (if any deferred)

### 6.3 Feature-Level Definition of Done

- [ ] All 33 PTO tasks completed and merged
- [ ] All shared foundation dependencies (FOUND-001 through FOUND-007) satisfied
- [ ] Full test suite passes:
  - `php artisan test` (all backend tests pass)
  - `npm run test` (all Vitest component tests pass)
  - `npx playwright test` (all E2E tests pass)
- [ ] `composer fix && composer analyse` passes on entire codebase
- [ ] `npm run lint:fix && npm run format` passes on entire frontend codebase
- [ ] All 18 permissions correctly assigned and enforced for all 5 roles
- [ ] Self-approval prevention works end-to-end
- [ ] Accrual and carryover commands run successfully in a staging environment
- [ ] Notifications delivered via database and email channels on all status transitions
- [ ] Performance requirements met:
  - Balance queries < 100ms
  - Request list < 200ms
  - Accrual job < 5 minutes for 1,000 members
  - Attendance calculation < 500ms for 31-day range
- [ ] Feature branch `feature/07-pto-time-off` merged to `main` via pull request
- [ ] Release notes written describing the feature for end users

---

## 7. Risk Register

### 7.1 Technical Risks

| # | Risk | Likelihood | Impact | Mitigation |
|---|------|:----------:|:------:|------------|
| T1 | **TimeOffService complexity (PTO-009)** -- The core service handles holiday-aware hour calculation, balance checks, overlap detection, and accrual logic. Bugs here cascade to all downstream features. | High | High | Assign most experienced backend developer. Write comprehensive unit tests (PTO-030) covering all edge cases. Consider splitting into smaller services (TimeOffService, HolidayService, AccrualService) as defined in the architecture document. |
| T2 | **Per-hour-worked accrual mode (PTO-016)** depends on querying `TimeEntry` data, introducing a cross-model dependency with potential performance concerns for large organizations. | Medium | Medium | Index `TimeEntry` on `member_id` + `start` (already exists). Use date-bounded queries. Test with organizations having 1,000+ members and 50,000+ time entries. |
| T3 | **Approval workflow is new to codebase** -- No existing pattern to follow. The shared `ApprovalStatus` enum and `HasApprovalWorkflow` trait are designed in `.features/SHARED-FOUNDATIONS.md` but have never been implemented. | Medium | Medium | Follow the documented shared patterns exactly. Write thorough state machine tests. Have the design reviewed by a senior developer before implementation begins. |
| T4 | **Date handling edge cases** -- Working day calculations must handle: weekends, holidays, leap years (Feb 29 recurring holidays), timezone boundaries, cross-year date ranges. | Medium | Medium | Use DATE columns (not timestamps) for PTO dates to avoid timezone issues entirely. Write explicit test cases for Feb 29, cross-year ranges, and holidays on weekends. |
| T5 | **Frontend date range picker complexity** -- The request form requires a date picker that shows holidays, calculates working days in real time, and validates against existing requests. | Medium | Low | Use an established Vue 3 date picker library (`@vuepic/vue-datepicker` or `v-calendar`). Pre-fetch holidays into the Pinia store so calculations are client-side. |

### 7.2 Dependency Risks

| # | Risk | Likelihood | Impact | Mitigation |
|---|------|:----------:|:------:|------------|
| D1 | **Shared foundations (Sprint 0) delayed** -- If FOUND-001 through FOUND-007 are not complete before Sprint 1 starts, PTO cannot proceed on schedule. | Medium | High | Start Sprint 0 tasks at least 1 week before Sprint 1. Parallelize FOUND-006/007 (no dependencies) with FOUND-001/002/003/004/005 (chain). If delayed, PTO-005 (permissions) and PTO-010 (notifications) are blocked. |
| D2 | **API contract instability between Sprint 2 and Sprint 3** -- Frontend TypeScript types (PTO-018) and Pinia store (PTO-019) depend on finalized API response shapes. | Medium | Medium | Freeze API response shapes at end of Sprint 2. Document API contracts in OpenAPI/Swagger before Sprint 3 begins. If API changes are needed after Sprint 3 starts, communicate immediately. |
| D3 | **FOUND-006 (weekly_capacity) blocks PTO-026 (Attendance Service)** -- If the shared migration is not run, attendance calculations cannot reference `weekly_capacity`. | Low | Medium | FOUND-006 is a 2-hour task with no dependencies. Ensure it is completed in Sprint 0. If delayed, PTO-026 can hard-code 144000 seconds (40h) as default and be updated later. |

### 7.3 Capacity Risks

| # | Risk | Likelihood | Impact | Mitigation |
|---|------|:----------:|:------:|------------|
| C1 | **Sprint 4 overload (40 SP)** -- The heaviest sprint combines remaining frontend, attendance feature, and all formal testing. | High | Medium | Start test stubs early (Sprint 2-3). PTO-031 (command tests) already moved to Sprint 3. If Sprint 4 runs long, deprioritize PTO-027 (attendance frontend, P2 priority) to a follow-up sprint. |
| C2 | **Single backend developer bottleneck** -- Sprints 1-2 are entirely backend. Any absence or slowdown blocks all downstream work. | Medium | High | Identify a backup backend developer who can pick up tasks if needed. Document implementation patterns thoroughly so handoff is possible. |
| C3 | **Frontend developer idle in Sprint 1** -- No frontend tasks are planned for Sprint 1. | Low | Low | Frontend developer works on Sprint 0 frontend tasks (FOUND-003, FOUND-005) or prepares UI mockups/component scaffolding for Sprint 3. |
| C4 | **QA engineer bandwidth** -- E2E tests (PTO-033) require a working end-to-end environment. Environment setup issues can consume significant QA time. | Medium | Medium | Ensure Docker Compose environment is stable before Sprint 4. Create database seeder scripts for PTO test data during Sprint 3. |

---

## 8. Milestone Timeline

### 8.1 Visual Timeline

```
Week:   -1      1       2       3       4       5       6       7       8
        |       |       |       |       |       |       |       |       |
        ├───────┼───────┼───────┼───────┼───────┼───────┼───────┼───────┤
Sprint: |  S0   |    Sprint 1   |    Sprint 2   |    Sprint 3   |    Sprint 4   |
        |       |               |               |               |               |
        |Shared |  DB Schema    | Business      | Accrual +     | Attendance +  |
        |Found- |  Models       | Logic         | Frontend      | Testing       |
        |ations |  CRUD APIs    | Request API   | Core          | Polish        |
        |       |               |               |               |               |
        |       |               |               |               |               |
    [M0]    [M1]            [M2]            [M3]            [M4]        [M5]
```

### 8.2 Key Milestones

| Milestone | Date (Week) | Description | Go/No-Go Criteria |
|-----------|:-----------:|-------------|-------------------|
| **M0: Foundations Complete** | Week 0 (pre-sprint) | All FOUND-xxx tasks done. Notification infrastructure, modular permissions, shared migrations operational. | `php artisan migrate` runs cleanly; `BaseNotification` sends test notification; `NotificationBell.vue` renders. |
| **M1: Schema & CRUD Ready** | End of Week 2 | All 4 tables created, models functional, Policy and Holiday CRUD APIs operational via Postman. | All routes visible in `route:list`; CRUD operations return correct HTTP statuses; `composer analyse` passes. |
| **M2: API Feature-Complete** | End of Week 4 | Full request lifecycle (create/approve/deny/withdraw) working. Balance management operational. Notifications dispatched. | E2E API walkthrough: create policy --> assign to member --> submit request --> approve --> verify balance deduction. Self-approval blocked. |
| **M3: User-Facing MVP** | End of Week 6 | Accrual engine operational. Frontend renders Time Off page with balance cards, request form, and request list. First usable version. | A human tester can: navigate to Time Off page, view balances, submit a request, see it in the list. Accrual command runs in staging. |
| **M4: Feature Complete** | End of Week 7 | All frontend components done. Attendance feature implemented. All backend tests passing. | All acceptance criteria met for all 33 tasks. `php artisan test` passes. Policy admin and holiday admin functional. |
| **M5: Release Ready** | End of Week 8 | All tests passing (unit, component, E2E). Performance validated. Code reviewed. Feature branch ready for merge. | Full test suite green. Performance benchmarks met. Feature demo approved by product owner. PR opened to `main`. |

### 8.3 Go/No-Go Decision Points

| Decision Point | When | Question | If No-Go |
|---------------|------|----------|----------|
| **Sprint 0 Complete?** | Start of Week 1 | Are all FOUND-xxx tasks done? | Delay Sprint 1 start. PTO-005 and PTO-010 cannot proceed without FOUND-007 and FOUND-001/002. |
| **API Contracts Stable?** | End of Week 4 | Are all API response shapes finalized? | Delay PTO-018/019 until contracts stabilize. Frontend Sprint 3 work is blocked. |
| **Accrual Engine Reliable?** | End of Week 6 | Does accrual command pass all tests? Is it idempotent? | Do not register in `Kernel.php` schedule. Keep as manual command until reliability is confirmed. |
| **Test Suite Green?** | End of Week 8 | Do all tests pass? Are there critical bugs? | Do not merge to `main`. Extend Sprint 4 by 2-3 days for bug fixing. Deprioritize attendance (P2) if needed. |

### 8.4 Sprint Velocity Tracking

| Sprint | Planned SP | Capacity (hrs) | SP/Dev Ratio | Risk Level |
|--------|:----------:|:--------------:|:------------:|:----------:|
| 0 | 15 | 40 (1 week, 2 devs) | 7.5/dev | Low |
| 1 | 22 | 65 (1 backend dev) | 22/dev | Medium |
| 2 | 22 | 65 (1 backend dev) | 22/dev | Medium |
| 3 | 33 | 130 (2 devs) | 16.5/dev | Medium-High |
| 4 | 40 | 130 (2 devs) | 20/dev | High |

> Sprint 4's 20 SP per developer is above the typical sustainable velocity of 15-18 SP per developer per 2-week sprint. This is mitigated by the testing tasks being less complex than feature development tasks (writing tests for already-understood code).

---

## Appendix A: Task ID Cross-Reference

The PRD uses `PTO-xxx` identifiers while the shared foundations document mandates `PTO-xxx` prefix (per AMD-01). This plan uses the `PTO-xxx` prefix throughout. The mapping is:

| PRD Task ID | Sprint Plan ID | Description |
|------------|----------------|-------------|
| PTO-001 | PTO-001 | Database Migrations |
| PTO-002 | PTO-002 | Eloquent Models |
| PTO-003 | PTO-003 | PHP Enums |
| PTO-004 | PTO-004 | Model Factories |
| PTO-005 | PTO-005 | Permissions Registration |
| PTO-006 | PTO-006 | TimeOffPolicy CRUD API |
| PTO-007 | PTO-007 | Holiday CRUD API |
| PTO-008 | PTO-008 | API Routes Registration |
| PTO-009 | PTO-009 | TimeOffService Core Logic |
| PTO-010 | PTO-010 | TimeOffRequest Lifecycle API |
| PTO-011 | PTO-011 | TimeOffBalance API |
| PTO-012 | PTO-012 | Approval State Machine |
| PTO-013 | PTO-013 | Policy Assignment |
| PTO-014 | PTO-014 | Balance Recalculation |
| PTO-015 | PTO-015 | Accrual Scheduled Command |
| PTO-016 | PTO-016 | Per-Hour-Worked Accrual |
| PTO-017 | PTO-017 | Year-End Carryover Command |
| PTO-018 | PTO-018 | TypeScript Type Definitions |
| PTO-019 | PTO-019 | Pinia Store |
| PTO-020 | PTO-020 | Web Routes & Page Shell |
| PTO-021 | PTO-021 | Balance Dashboard Components |
| PTO-022 | PTO-022 | Request Form Components |
| PTO-023 | PTO-023 | Request List Components |
| PTO-024 | PTO-024 | Policy Admin Components |
| PTO-025 | PTO-025 | Holiday Admin Components |
| PTO-026 | PTO-026 | Attendance Service (Backend) |
| PTO-027 | PTO-027 | Attendance Frontend |
| PTO-028 | PTO-028 | Policy/Holiday API Tests |
| PTO-029 | PTO-029 | Request/Balance API Tests |
| PTO-030 | PTO-030 | Service Unit Tests |
| PTO-031 | PTO-031 | Scheduled Command Tests |
| PTO-032 | PTO-032 | Frontend Component Tests |
| PTO-033 | PTO-033 | E2E Playwright Tests |

---

## Appendix B: File Manifest Summary

| Category | Count | Location Pattern |
|----------|:-----:|-----------------|
| Migrations | 4 | `database/migrations/2026_03_07_*` |
| Models | 4 | `app/Models/TimeOff*.php`, `app/Models/Holiday.php` |
| Enums | 2 (+1 shared) | `app/Enums/TimeOffType.php`, `app/Enums/AccrualFrequency.php` |
| Services | 5 | `app/Service/TimeOff*.php`, `app/Service/Accrual*.php`, `app/Service/Holiday*.php`, `app/Service/Attendance*.php` |
| Controllers | 5 | `app/Http/Controllers/Api/V1/TimeOff*.php`, `Holiday*.php`, `Attendance*.php` |
| Request validators | 10 | `app/Http/Requests/V1/TimeOff*/**`, `Holiday/**`, `TimeOffBalance/**` |
| Resources | 8 | `app/Http/Resources/V1/TimeOff*/**`, `Holiday/**` |
| Notifications | 3 | `app/Notifications/TimeOffRequest*.php` |
| Commands | 2 | `app/Console/Commands/TimeOff/*.php` |
| Permissions | 1 | `app/Permissions/TimeOffPermissions.php` |
| Factories | 4 | `database/factories/TimeOff*.php`, `HolidayFactory.php` |
| Vue Pages | 3 | `resources/js/Pages/TimeOff*.vue`, `Attendance.vue` |
| Vue Components | ~12 | `resources/js/packages/ui/src/TimeOff/*.vue`, `Attendance/*.vue` |
| Pinia Stores | 2 | `resources/js/utils/useTimeOff.ts`, `useAttendance.ts` |
| TS Types | 1 | `resources/js/types/time-off.d.ts` |
| Backend Tests | ~11 | `tests/Unit/Endpoint/Api/V1/TimeOff*.php`, `tests/Unit/Service/TimeOff*.php`, `tests/Unit/Console/Commands/*.php` |
| Frontend Tests | ~4 | `resources/js/packages/ui/src/TimeOff/__tests__/*.test.ts` |
| E2E Tests | 1 | `e2e/time-off.spec.ts` |
| **Total new files** | **~75** | |
| **Files modified** | **~8** | `routes/api.php`, `routes/web.php`, `Kernel.php`, `scheduling.php`, `JetstreamServiceProvider.php`, `AppLayout.vue`, `permissions.ts`, `Member.php`, `Organization.php` |

---

Last updated: 2026-02-06
