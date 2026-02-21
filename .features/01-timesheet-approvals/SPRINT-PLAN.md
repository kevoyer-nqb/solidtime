# Sprint Plan: Timesheet Approvals

**Date**: 2026-02-06
**Feature**: 01 - Timesheet Approvals
**Branch**: `feature/timesheet-approvals` (from `feature/weekly-timesheet-grid`)
**Task Prefix**: `APPR-` (per SF-01)
**PRD Reference**: `.features/01-timesheet-approvals/PRD.md`
**Architecture Reference**: `.features/01-timesheet-approvals/ARCHITECTURE.md`

---

## 1. Executive Summary

The Timesheet Approvals feature adds a state-machine-based workflow to Solidtime's existing weekly timesheet grid. Members submit weekly timesheets for review, managers/admins approve or request changes, and approved periods become permanently locked. The feature includes notifications on all status transitions, configurable reminders for missing or unsubmitted time, and an admin-facing approval queue page.

**Total effort estimate**: 177 hours (feature tasks) + 30 hours (shared foundations) = **207 hours**

**Total story points**: ~117 SP (feature) + ~15 SP (foundations) = **~132 SP**

**Number of sprints**: **5 sprints** (10 weeks)
- Sprint 0: Shared Foundations (2 weeks, can overlap with Sprint 1 start)
- Sprints 1-4: Feature implementation (8 weeks)

**Team size assumptions**:
- 1 Backend Developer (senior, ~30 productive hours/sprint)
- 1 Frontend Developer (senior, ~30 productive hours/sprint)
- 1 QA/Fullstack Developer (mid-senior, ~25 productive hours/sprint)
- Total team velocity: ~42-45 SP per sprint (at 2h/SP ratio per SF-10)

**Key constraints**:
- Shared Foundations (FOUND-001 through FOUND-007) must be completed before notification tasks (APPR-012, APPR-013) and permissions registration (APPR-005)
- The `feature/weekly-timesheet-grid` branch must be merged to main (or stable) before starting approval work on shared files to mitigate HIGH merge conflict risk on `TimesheetService.php`, `useTimesheet.ts`, and `TimesheetWeekAccordion.vue`
- The critical path through the backend is 48 hours of sequential work: APPR-001 -> APPR-002 -> APPR-003 -> APPR-006 -> APPR-009 -> APPR-011 -> APPR-024

---

## 2. Sprint Overview Table

| Sprint | Name | Duration | Story Points | Key Deliverables |
|--------|------|----------|:------------:|------------------|
| **0** | Shared Foundations | 2 weeks | ~15 SP | Notification infrastructure, modular permissions, shared ApprovalStatus enum, HasApprovalWorkflow trait |
| **1** | Data Model + Core Service | 2 weeks | 26 SP | Database migrations, TimesheetApproval model/factory, ApprovalService state machine, permissions registration, TS types |
| **2** | API Layer + Timesheet Integration | 2 weeks | 36 SP | ApprovalController, API routes, request validation, lock checks in TimesheetService and TimeEntryController, Pinia store enhancements, submission UI |
| **3** | Approvals Page + Notifications + Settings | 2 weeks | 38 SP | Approvals Vue page, notification classes, notification dispatch, reminder command, org settings UI, sidebar nav |
| **4** | Testing + Polish + E2E | 2 weeks | ~21 SP | Service unit tests, endpoint tests, frontend component tests, E2E Playwright tests, OpenAPI spec, bug fixes |

**Total**: ~136 SP across 10 weeks

---

## 3. Dependency Map

### 3.1 Shared Foundation Dependencies

The following FOUND tasks must be completed (Sprint 0) before specific APPR tasks can begin:

```
FOUND-001 (Notification migration, 2h)
    |
    v
FOUND-002 (Base notification classes, 4h)
    |
    +---> FOUND-003 (Notification bell UI, 8h) -----> APPR-012, APPR-013
    |
    +---> FOUND-004 (Notification API endpoints, 6h) -> FOUND-003
    |
    v
FOUND-005 (Notification preferences, 4h)

FOUND-006 (weekly_capacity migrations, 2h) -- NOT required by this feature (used by PRDs 08, 09)

FOUND-007 (Modular permissions infrastructure, 4h) ---> APPR-005 (Register permissions)

SF-05 Shared Enum/Trait (created during FOUND phase):
    - app/Enums/ApprovalStatus.php ---------> APPR-001 (uses shared enum instead of feature-specific)
    - app/Traits/HasApprovalWorkflow.php ---> APPR-003 (model uses trait)
```

**Summary of blocking relationships**:

| Foundation Task | Blocks Feature Tasks | Required By Sprint |
|----------------|---------------------|-------------------|
| FOUND-001 | FOUND-002 | Sprint 0 |
| FOUND-002 | FOUND-003, FOUND-004, APPR-012 | Sprint 0 |
| FOUND-003 | APPR-012, APPR-013 (UI display of notifications) | Sprint 0 |
| FOUND-004 | FOUND-003 | Sprint 0 |
| FOUND-005 | None directly (enhances notifications) | Sprint 0 |
| FOUND-007 | APPR-005 | Sprint 0 |
| SF-05 Enum | APPR-001, APPR-003 | Sprint 0 |
| SF-05 Trait | APPR-003 | Sprint 0 |

### 3.2 Intra-Feature Dependencies

```
Wave 1 (No dependencies):
    APPR-001, APPR-004, APPR-005*, APPR-015
        * APPR-005 depends on FOUND-007

Wave 2:
    APPR-002 (depends on APPR-001)
    APPR-016 (depends on APPR-015)
    APPR-019 (depends on APPR-015)

Wave 3:
    APPR-003 (depends on APPR-001, APPR-002)
    APPR-017 (depends on APPR-016)
    APPR-018 (depends on APPR-016)
    APPR-020 (depends on APPR-019)

Wave 4:
    APPR-006 (depends on APPR-001, APPR-002, APPR-003, APPR-005)
    APPR-010 (depends on APPR-003)
    APPR-022 (depends on APPR-004)
    APPR-021 (depends on APPR-020)
    APPR-028 (depends on APPR-017, APPR-020)
    APPR-029 (depends on APPR-017, APPR-020)

Wave 5:
    APPR-007 (depends on APPR-006)
    APPR-009 (depends on APPR-005, APPR-006)
    APPR-012 (depends on APPR-006, FOUND-002)
    APPR-023 (depends on APPR-006)

Wave 6:
    APPR-008 (depends on APPR-006, APPR-007)
    APPR-011 (depends on APPR-009)
    APPR-013 (depends on APPR-006, APPR-012)
    APPR-014 (depends on APPR-003, APPR-004, APPR-012)
    APPR-025 (depends on APPR-007, APPR-008)

Wave 7:
    APPR-024 (depends on APPR-009, APPR-011)
    APPR-026 (depends on APPR-012, APPR-013)
    APPR-027 (depends on APPR-014)
    APPR-030 (depends on APPR-009, APPR-011)
```

### 3.3 Critical Path

```
FOUND-007 (4h) --> APPR-005 (2h) --+
                                     |
APPR-001 (1h) --> APPR-002 (2h) --> APPR-003 (4h) --> APPR-006 (16h) --> APPR-009 (8h) --> APPR-011 (1h) --> APPR-024 (16h)
                                                                                                                    |
Total critical path: 48h feature + 4h foundation = 52h sequential                                                   v
                                                                                                              Feature Complete
```

### 3.4 External Dependencies

| Dependency | Type | Impact |
|-----------|------|--------|
| `feature/weekly-timesheet-grid` merge | Hard | Must be merged before modifying `TimesheetService.php`, `useTimesheet.ts`, `TimesheetWeekAccordion.vue` |
| Shared Foundations (Phase 0) | Hard | Blocks notification tasks and permissions registration |
| PRD 10 (Teams & Groups) | Soft | Team-scoped approval queries would benefit from `TeamScopeService`, but can use existing `ProjectMember` relationship initially |

---

## 4. Sprint Details

### Sprint 0: Shared Foundations (Weeks 1-2)

**Sprint Goal**: Establish the cross-cutting infrastructure that Timesheet Approvals (and future features) depend on: notification system, modular permissions, and shared approval patterns.

**Note**: This sprint can run in parallel with early Sprint 1 tasks that do not depend on foundations (e.g., APPR-004, APPR-015). In practice, a single backend developer may handle both Sprint 0 and early Sprint 1 work concurrently.

#### Tasks

| Task ID | Description | Effort (h) | SP | Dependencies | Assignee Role |
|---------|-------------|:----------:|:--:|:------------:|:-------------:|
| FOUND-001 | Create notification infrastructure migration (`notifications` table, `notification_preferences` JSON column on `members`) | 2 | 1 | None | Backend |
| FOUND-002 | Create `BaseNotification` class extending `Illuminate\Notifications\Notification` with `database` + `mail` channels, respecting per-member preferences | 4 | 2 | FOUND-001 | Backend |
| FOUND-003 | Create `NotificationBell.vue` UI component in AppLayout header with polling, mark-as-read, and entity links | 8 | 4 | FOUND-002, FOUND-004 | Frontend |
| FOUND-004 | Create notification API endpoints: list, mark-read, mark-all-read, unread-count | 6 | 3 | FOUND-002 | Backend |
| FOUND-005 | Add notification preferences to organization settings (per-member email toggle, per-type preferences) | 4 | 2 | FOUND-002 | Fullstack |
| FOUND-007 | Create `app/Permissions/` directory structure, refactor existing permissions into modular pattern | 4 | 2 | None | Backend |
| SF-05a | Create `app/Enums/ApprovalStatus.php` shared enum | 1 | 1 | None | Backend |
| SF-05b | Create `app/Traits/HasApprovalWorkflow.php` shared trait | 1 | 1 | SF-05a | Backend |
| **Total** | | **30** | **16** | | |

#### Acceptance Criteria

- [ ] `php artisan migrate` creates the `notifications` table successfully
- [ ] `BaseNotification` sends to both `database` and `mail` channels
- [ ] Notification bell component renders in AppLayout, shows unread count, and polls every 60 seconds
- [ ] All four notification API endpoints return correct responses with proper auth
- [ ] `app/Permissions/` directory exists with at least one example permissions class
- [ ] `JetstreamServiceProvider::configurePermissions()` calls modular registration methods
- [ ] `ApprovalStatus` enum has all six values: DRAFT, SUBMITTED, APPROVED, CHANGES_REQUESTED, REJECTED, WITHDRAWN (plus REOPENED for timesheet-specific extension)
- [ ] `HasApprovalWorkflow` trait provides `isEditable()`, `isSubmitted()`, `isApproved()`, `reviewer()` methods
- [ ] Existing tests pass with no regressions

#### Deliverables

**Backend files created**:
- `database/migrations/2026_02_28_000001_create_notifications_table.php`
- `database/migrations/2026_02_28_000002_add_notification_preferences_to_members.php`
- `app/Notifications/BaseNotification.php`
- `app/Http/Controllers/Api/V1/NotificationController.php`
- `app/Enums/ApprovalStatus.php`
- `app/Traits/HasApprovalWorkflow.php`
- `app/Permissions/TimesheetApprovalPermissions.php` (scaffold)

**Frontend files created**:
- `resources/js/packages/ui/src/Notification/NotificationBell.vue`

**Backend files modified**:
- `app/Providers/JetstreamServiceProvider.php` (modular permissions registration)
- `routes/api.php` (notification routes)

#### Risk Factors

- **Notification system complexity**: The `Notifiable` trait may need to be added to the `User` model if not already present; verify before starting FOUND-002
- **FOUND-003 depends on FOUND-004**: Frontend notification bell cannot be completed until API endpoints are ready; start with mock data and integrate once API is done
- **Scope creep**: Keep notification preferences simple (email on/off toggle) for v1; per-type preferences can be deferred

---

### Sprint 1: Data Model + Core Service (Weeks 3-4)

**Sprint Goal**: Build the complete backend data layer and core approval service with full state machine logic, establishing the foundation for all subsequent API and UI work.

#### Tasks

| Task ID | Description | Effort (h) | SP | Dependencies | Assignee Role |
|---------|-------------|:----------:|:--:|:------------:|:-------------:|
| APPR-001 | Create `TimesheetApprovalStatus` enum (or use shared `ApprovalStatus` from SF-05a) -- verify enum values, add `isLockedForMember()` and `isPermanentlyLocked()` helper methods | 1 | 1 | SF-05a (Sprint 0) | Backend |
| APPR-002 | Create database migration `2026_03_01_000001_create_timesheet_approvals_table.php` with UUID PK, FKs, unique constraint, indexes | 2 | 2 | APPR-001 | Backend |
| APPR-003 | Create `TimesheetApproval` model with `CustomAuditable`, `HasUuids`, `HasFactory`, `HasApprovalWorkflow` traits; scopes; `canTransitionTo()` method. Create `TimesheetApprovalFactory` with 5 states | 4 | 3 | APPR-001, APPR-002, SF-05b | Backend |
| APPR-004 | Create migration `2026_03_01_000002_add_timesheet_approval_settings_to_organizations.php` adding `timesheet_approval_required`, `timesheet_reminder_enabled`, `timesheet_reminder_day`, `timesheet_expected_hours_per_week` columns. Update Organization model `$casts` | 1 | 1 | None | Backend |
| APPR-005 | Register new permissions (`timesheet-approvals:view`, `timesheet-approvals:submit:own`, `timesheet-approvals:approve`, `timesheet-approvals:approve:all`, `timesheet-approvals:reopen`, `timesheet-approvals:configure`) via `TimesheetApprovalPermissions::register()` and integrate into `JetstreamServiceProvider` | 2 | 2 | FOUND-007 (Sprint 0) | Backend |
| APPR-006 | Create `TimesheetApprovalService` with all 9 methods: `submit()`, `withdraw()`, `approve()`, `requestChanges()`, `reopen()`, `getBlockingApproval()`, `getApprovalStatuses()`, `listPendingApprovals()`, `bulkApprove()`. All state transitions wrapped in `DB::transaction()` | 16 | 8 | APPR-001, APPR-002, APPR-003, APPR-005 | Backend |
| APPR-007 | Enhance `TimesheetService` with lock checks: add lock check in `updateCell()` via `getBlockingApproval()`; add `approval_status` and `approval_id` to `getWeekList()` response; add `is_locked` and `approval_status` to `getWeekGrid()` response. Create `TimesheetPeriodLockedException` and `TimesheetApprovalException` | 6 | 5 | APPR-006 | Backend |
| APPR-015 | Add TypeScript types: create `resources/js/types/timesheet-approval.d.ts` with `ApprovalStatus`, `TimesheetApproval`, `MyApprovalStatus` interfaces. Update `timesheet.d.ts` to add `approval_status` and `approval_id` to `WeekSummary` | 2 | 1 | None | Frontend |
| | **Sprint 1 Subtotal** | **34** | **23** | | |

*Note: APPR-006 (16h, 8 SP) is the single largest task in the project. If it slips, it affects the entire critical path. Consider breaking it into sub-tasks: (a) submit/withdraw/resubmit, (b) approve/requestChanges/reopen, (c) getBlockingApproval/getApprovalStatuses, (d) listPendingApprovals/bulkApprove.*

#### Acceptance Criteria

- [ ] `php artisan migrate` creates the `timesheet_approvals` table and adds organization setting columns
- [ ] `TimesheetApproval::factory()->submitted()->create()` produces a valid record
- [ ] All six permissions resolve correctly for Owner, Admin, Manager, Employee roles
- [ ] `TimesheetApprovalService::submit()` creates approval record, validates entries exist, rejects if running timers present
- [ ] `TimesheetApprovalService::approve()` prevents self-approval (SF-05 rule)
- [ ] `TimesheetApprovalService::getBlockingApproval()` returns the blocking approval for submitted/approved periods, null otherwise
- [ ] `TimesheetService::updateCell()` throws `TimesheetPeriodLockedException` (HTTP 423) when period is locked
- [ ] `TimesheetService::getWeekList()` returns `approval_status` and `approval_id` per week
- [ ] TypeScript types compile without errors
- [ ] All existing timesheet tests continue to pass

#### Deliverables

**Backend files created**:
- `database/migrations/2026_03_01_000001_create_timesheet_approvals_table.php`
- `database/migrations/2026_03_01_000002_add_timesheet_approval_settings_to_organizations.php`
- `app/Models/TimesheetApproval.php`
- `database/factories/TimesheetApprovalFactory.php`
- `app/Permissions/TimesheetApprovalPermissions.php`
- `app/Service/TimesheetApprovalService.php`
- `app/Exceptions/Api/TimesheetPeriodLockedException.php`
- `app/Exceptions/Api/TimesheetApprovalException.php`

**Backend files modified**:
- `app/Providers/JetstreamServiceProvider.php` (permission registration)
- `app/Service/TimesheetService.php` (lock checks, approval status in responses)
- `app/Models/Organization.php` (`$casts` for new columns)
- `lang/en/exceptions.php` (translation keys for new exceptions)

**Frontend files created**:
- `resources/js/types/timesheet-approval.d.ts`

**Frontend files modified**:
- `resources/js/types/timesheet.d.ts` (WeekSummary additions)

#### Risk Factors

- **APPR-006 size**: At 16 hours, this is the largest single task. If the developer encounters complexity in state transition validation or timezone handling, it could extend by 4-8 hours. **Mitigation**: Write unit tests alongside implementation; split into sub-tasks.
- **Merge conflict on `TimesheetService.php`**: HIGH risk since the weekly grid branch and approval feature both modify the same methods. **Mitigation**: Ensure grid branch is merged to main before starting APPR-007.
- **FOUND-007 dependency**: If Sprint 0 does not complete the modular permissions infrastructure, APPR-005 is blocked. **Mitigation**: APPR-005 can temporarily add permissions directly to `JetstreamServiceProvider` and refactor later.

---

### Sprint 2: API Layer + Timesheet Integration (Weeks 5-6)

**Sprint Goal**: Deliver a fully functional API for all approval actions, integrate lock checks into all time entry mutation points, and build the frontend submission/withdrawal UI on the existing timesheet page.

#### Tasks

| Task ID | Description | Effort (h) | SP | Dependencies | Assignee Role |
|---------|-------------|:----------:|:--:|:------------:|:-------------:|
| APPR-008 | Enhance `TimeEntryController` with lock checks in `store()`, `update()`, `updateMultiple()`, `destroy()`, `destroyMultiple()` -- all 5 mutation points inject `TimesheetApprovalService` and call `getBlockingApproval()` | 4 | 3 | APPR-006, APPR-007 | Backend |
| APPR-009 | Create `TimesheetApprovalController` with 8 methods: `submit()`, `withdraw()`, `approve()`, `requestChanges()`, `reopen()`, `index()`, `my()`, `bulkApprove()`. All follow base controller pattern with `checkPermission()` | 8 | 5 | APPR-005, APPR-006 | Backend |
| APPR-010 | Create request validation classes: `TimesheetApprovalSubmitRequest`, `TimesheetApprovalRequestChangesRequest`, `TimesheetApprovalReopenRequest`, `TimesheetApprovalIndexRequest`, `TimesheetApprovalBulkApproveRequest` | 4 | 3 | APPR-003 | Backend |
| APPR-011 | Register API routes in `routes/api.php` under `v1.timesheet-approvals.*` prefix with proper middleware (`check-organization-blocked` on write endpoints) | 1 | 1 | APPR-009 | Backend |
| APPR-016 | Enhance `useTimesheetStore` with approval actions: `submitWeek()`, `withdrawSubmission()`, `canEditCell()` getter. Integrate with `useTimesheetApprovalStore` for status lookups | 8 | 5 | APPR-015 | Frontend |
| APPR-017 | Add submit/withdraw UI to `TimesheetWeekAccordion.vue`: status badge (via `TimesheetApprovalStatusBadge`), "Submit Week" button, "Withdraw" button, confirmation dialog (via `TimesheetSubmitDialog`) | 8 | 5 | APPR-016 | Frontend |
| APPR-018 | Disable cell editing when week is locked: add `isLocked` prop to `TimesheetCell.vue`, disable input, add lock icon overlay, add tooltip | 4 | 3 | APPR-016 | Frontend |
| APPR-023 | Write `TimesheetApprovalServiceTest` unit tests covering all state transitions, validation rules, self-approval prevention, blocking approval queries | 12 | 8 | APPR-006 | QA |
| APPR-025 | Write enhanced `TimesheetEndpointTest` tests for lock checks: updateCell returns 423 when locked, time entry CRUD returns 423 when in locked period | 6 | 5 | APPR-007, APPR-008 | QA |
| | **Sprint 2 Subtotal** | **55** | **38** | | |

*Note: This sprint has the highest workload (38 SP). Backend and frontend tracks run in parallel. The QA developer handles APPR-023 and APPR-025 concurrently with the frontend work.*

#### Acceptance Criteria

- [ ] All 8 approval API endpoints return correct responses with proper HTTP status codes
- [ ] `POST /api/v1/organizations/{org}/timesheet-approvals/submit` returns 201 on success, 422 on validation failure, 409 on duplicate
- [ ] `POST /api/v1/organizations/{org}/timesheet-approvals/{id}/approve` returns 403 on self-approval attempt
- [ ] All 5 `TimeEntryController` mutation methods return 423 when entries fall in a locked period
- [ ] The "Submit Week" button appears on the timesheet page for editable weeks with entries
- [ ] After submission, cells show as read-only with lock icon overlay
- [ ] "Withdraw" button appears on submitted (not yet approved) weeks
- [ ] Unit tests for `TimesheetApprovalService` achieve >90% branch coverage on state transitions
- [ ] Lock check integration tests verify all 7 mutation points (including `TimesheetService::updateCell()`)

#### Deliverables

**Backend files created**:
- `app/Http/Controllers/Api/V1/TimesheetApprovalController.php`
- `app/Http/Requests/V1/TimesheetApproval/TimesheetApprovalSubmitRequest.php`
- `app/Http/Requests/V1/TimesheetApproval/TimesheetApprovalRequestChangesRequest.php`
- `app/Http/Requests/V1/TimesheetApproval/TimesheetApprovalReopenRequest.php`
- `app/Http/Requests/V1/TimesheetApproval/TimesheetApprovalIndexRequest.php`
- `app/Http/Requests/V1/TimesheetApproval/TimesheetApprovalBulkApproveRequest.php`
- `tests/Unit/Service/TimesheetApprovalServiceTest.php`

**Backend files modified**:
- `app/Http/Controllers/Api/V1/TimeEntryController.php` (lock checks in 5 methods)
- `routes/api.php` (approval route group)
- `tests/Unit/Endpoint/Api/V1/TimesheetEndpointTest.php` (lock check tests)

**Frontend files created**:
- `resources/js/packages/ui/src/TimesheetApproval/TimesheetApprovalStatusBadge.vue`
- `resources/js/packages/ui/src/TimesheetApproval/TimesheetSubmitDialog.vue`

**Frontend files modified**:
- `resources/js/utils/useTimesheet.ts` (approval actions, canEditCell)
- `resources/js/packages/ui/src/Timesheet/TimesheetWeekAccordion.vue` (submit/withdraw UI)
- `resources/js/packages/ui/src/Timesheet/TimesheetCell.vue` (isLocked prop, disabled state)
- `resources/js/Pages/Timesheet.vue` (approval store integration)

#### Risk Factors

- **Sprint load**: At 38 SP across 3 developers, this sprint is near capacity. If APPR-006 from Sprint 1 slipped, APPR-009 and APPR-023 are immediately impacted. **Mitigation**: APPR-010 (request validation) has a lighter dependency (only APPR-003) and can start early.
- **Merge conflicts on `TimesheetWeekAccordion.vue`**: HIGH risk file. **Mitigation**: Frontend developer should rebase frequently against the latest grid branch.
- **Import service lock enforcement**: The 7th mutation point (`ImportService::import()`) is not explicitly tasked. **Mitigation**: Add a sub-task to APPR-008 or create a follow-up card; note that import is lower priority since it is admin-initiated.

---

### Sprint 3: Approvals Page + Notifications + Settings (Weeks 7-8)

**Sprint Goal**: Deliver the manager-facing approvals page, integrate all notification classes, build the reminder command, and add approval configuration to organization settings.

#### Tasks

| Task ID | Description | Effort (h) | SP | Dependencies | Assignee Role |
|---------|-------------|:----------:|:--:|:------------:|:-------------:|
| APPR-012 | Create four notification classes extending `BaseNotification`: `TimesheetSubmittedNotification`, `TimesheetApprovedNotification`, `TimesheetChangesRequestedNotification`, `TimesheetReminderNotification`. Each with `toMail()`, `toDatabase()`, and `toArray()` methods | 6 | 5 | APPR-006, FOUND-002 | Backend |
| APPR-013 | Dispatch notifications from `TimesheetApprovalService`: wire `submit()` to notify approvers, `approve()` to notify member, `requestChanges()` to notify member. Add `notifyApprovers()` private helper | 3 | 2 | APPR-006, APPR-012 | Backend |
| APPR-014 | Create `SendTimesheetRemindersCommand` (`timesheet:send-reminders`): query orgs with reminders enabled, check members for missing time/unsubmitted timesheets, dispatch `TimesheetReminderNotification`. Register in `Kernel.php` as daily schedule | 8 | 5 | APPR-003, APPR-004, APPR-012 | Backend |
| APPR-019 | Create `useTimesheetApprovalStore` Pinia store: `pendingApprovals`, `myApprovals`, `currentApproval` state; `loadPendingApprovals()`, `loadMyApprovals()`, `approveTimesheet()`, `requestChanges()`, `reopenTimesheet()` actions; `getApprovalStatus()`, `isWeekLocked()` getters | 6 | 5 | APPR-015 | Frontend |
| APPR-020 | Create `Approvals.vue` page with two-column layout (list + detail). Components: `TimesheetApprovalList`, `TimesheetApprovalDetail`, `TimesheetApprovalFilters`, `TimesheetRequestChangesDialog`, `TimesheetReopenDialog`. Register Inertia route in `routes/web.php` | 12 | 8 | APPR-019 | Frontend |
| APPR-021 | Add "Approvals" sidebar navigation item in `AppLayout.vue` with `ClipboardDocumentCheckIcon`, visible only for users with `timesheet-approvals:view` permission | 1 | 1 | APPR-020 | Frontend |
| APPR-022 | Add "Timesheet Approval Settings" section to organization settings page: toggle for `timesheet_approval_required`, toggle for `timesheet_reminder_enabled`, dropdown for `timesheet_reminder_day`, number input for `timesheet_expected_hours_per_week`. Backend: create update endpoint or extend existing org settings endpoint | 6 | 5 | APPR-004 | Fullstack |
| APPR-024 | Write `TimesheetApprovalEndpointTest` covering all 8 API endpoints: permission checks (forbidden without correct permission), submit validations (empty week, running timer, duplicate), withdraw (wrong state), approve (self-approval, wrong state), request-changes (missing reason), reopen (admin only), index (filters, pagination, team scope), bulk-approve | 16 | 8 | APPR-009, APPR-011 | QA |
| | **Sprint 3 Subtotal** | **58** | **39** | | |

*Note: Sprint 3 has the highest raw hours (58h) but benefits from three parallel tracks: backend (notifications + reminders), frontend (approvals page), and QA (endpoint tests). The QA developer focuses primarily on APPR-024 while the other developers build features.*

#### Acceptance Criteria

- [ ] Submitting a timesheet sends email + database notification to all users with `timesheet-approvals:approve` permission in the organization
- [ ] Approving a timesheet sends notification to the submitting member
- [ ] Requesting changes sends notification with the reviewer's comment
- [ ] `php artisan timesheet:send-reminders` correctly identifies members with missing time or unsubmitted timesheets and sends reminders
- [ ] Reminder command respects `timesheet_reminder_enabled` and `timesheet_reminder_day` org settings
- [ ] Approvals page loads with filterable, paginated list of submissions
- [ ] Clicking an approval row shows detail view with read-only weekly grid and action buttons
- [ ] Managers see only timesheets for members on shared projects; admins see all
- [ ] Organization settings page shows all four timesheet approval configuration fields
- [ ] "Approvals" nav item appears for managers/admins, hidden for employees
- [ ] All 8 API endpoints have comprehensive test coverage (>90% of acceptance criteria scenarios)

#### Deliverables

**Backend files created**:
- `app/Notifications/TimesheetSubmittedNotification.php`
- `app/Notifications/TimesheetApprovedNotification.php`
- `app/Notifications/TimesheetChangesRequestedNotification.php`
- `app/Notifications/TimesheetReminderNotification.php`
- `app/Console/Commands/SendTimesheetRemindersCommand.php`
- `tests/Unit/Endpoint/Api/V1/TimesheetApprovalEndpointTest.php`

**Backend files modified**:
- `app/Service/TimesheetApprovalService.php` (notification dispatch)
- `app/Console/Kernel.php` (schedule reminder command)
- `routes/web.php` (Approvals page Inertia route)

**Frontend files created**:
- `resources/js/utils/useTimesheetApproval.ts`
- `resources/js/Pages/Approvals.vue`
- `resources/js/packages/ui/src/TimesheetApproval/TimesheetApprovalList.vue`
- `resources/js/packages/ui/src/TimesheetApproval/TimesheetApprovalDetail.vue`
- `resources/js/packages/ui/src/TimesheetApproval/TimesheetApprovalFilters.vue`
- `resources/js/packages/ui/src/TimesheetApproval/TimesheetRequestChangesDialog.vue`
- `resources/js/packages/ui/src/TimesheetApproval/TimesheetReopenDialog.vue`

**Frontend files modified**:
- `resources/js/Layouts/AppLayout.vue` (nav item)

#### Risk Factors

- **APPR-020 size**: At 12 hours, this is the largest frontend task. The two-column layout with filters, list, detail view, and action dialogs is complex. **Mitigation**: Template from existing pages like `Reporting.vue` or `Members.vue`; build list view first, then detail, then dialogs.
- **APPR-024 size**: At 16 hours, this is the largest test task. 8 endpoints with multiple scenarios each (permission, validation, state, team scope). **Mitigation**: Use test data builders and parameterized tests; prioritize P0 scenarios first.
- **Notification testing**: Ensuring notifications are sent to the correct recipients requires careful test setup with multiple users/roles. **Mitigation**: Use Laravel's `Notification::fake()` in tests.
- **Team scope query complexity**: Managers seeing only "shared project" members requires a query through `ProjectMember` relationships. **Mitigation**: Start with admin-sees-all, then layer in team scoping.

---

### Sprint 4: Testing + Polish + E2E (Weeks 9-10)

**Sprint Goal**: Achieve comprehensive test coverage across all layers, update API documentation, fix bugs discovered during testing, and ensure the feature is production-ready.

#### Tasks

| Task ID | Description | Effort (h) | SP | Dependencies | Assignee Role |
|---------|-------------|:----------:|:--:|:------------:|:-------------:|
| APPR-026 | Write notification mail tests: verify email content, recipients, and database payloads for all 4 notification classes. Use `Notification::fake()` and `Mail::fake()` | 4 | 3 | APPR-012, APPR-013 | QA |
| APPR-027 | Write `SendTimesheetRemindersCommand` test: verify correct members identified, exclusions applied (placeholder, already submitted), org settings respected | 4 | 3 | APPR-014 | QA |
| APPR-028 | Write frontend component tests with Vitest: `TimesheetApprovalList`, `TimesheetApprovalDetail`, `TimesheetApprovalStatusBadge`, `TimesheetSubmitDialog`, `TimesheetRequestChangesDialog` | 8 | 5 | APPR-017, APPR-020 | Frontend QA |
| APPR-029 | Write E2E Playwright tests: member submits timesheet, member withdraws, manager approves, manager requests changes, admin reopens, locked cells cannot be edited, approval page filters work | 12 | 8 | APPR-017, APPR-020 | QA |
| APPR-030 | Update OpenAPI specification with all 8 new approval endpoints (request/response schemas, error codes, auth requirements) | 4 | 3 | APPR-009, APPR-011 | Backend |
| -- | Bug fixes, integration testing, code review, polish | ~8 | ~4 | All | All |
| | **Sprint 4 Subtotal** | **~40** | **~26** | | |

#### Acceptance Criteria

- [ ] All notification classes have tests verifying email subject, body content, and database payload structure
- [ ] Reminder command tests verify: members below threshold are notified, members who already submitted are excluded, placeholder members are excluded, disabled orgs are skipped
- [ ] Frontend component tests cover: status badge renders correct colors/text for all 6 statuses, submit dialog shows summary and warning, approval list renders rows with correct data
- [ ] E2E tests pass end-to-end: full submission-to-approval flow, full submission-to-changes-requested-to-resubmit flow, cell locking verification
- [ ] OpenAPI spec is valid and documents all endpoints with examples
- [ ] `composer fix && composer analyse` passes with no errors
- [ ] `npm run lint:fix && npm run format` passes with no errors
- [ ] All existing tests continue to pass (zero regressions)
- [ ] Manual smoke test confirms all user stories (USR-001 through USR-008) are working

#### Deliverables

**Test files created**:
- `tests/Unit/Service/TimesheetApprovalServiceTest.php` (if not completed in Sprint 2)
- Tests for notification classes (within existing test structure)
- `tests/Unit/Console/SendTimesheetRemindersCommandTest.php`
- `resources/js/packages/ui/src/TimesheetApproval/__tests__/TimesheetApprovalList.test.ts`
- `resources/js/packages/ui/src/TimesheetApproval/__tests__/TimesheetApprovalDetail.test.ts`
- `resources/js/packages/ui/src/TimesheetApproval/__tests__/TimesheetApprovalStatusBadge.test.ts`
- `e2e/timesheet-approvals.spec.ts`

**Documentation files modified**:
- OpenAPI specification (updated with approval endpoints)

#### Risk Factors

- **E2E test flakiness**: Playwright tests for approval flows require multi-user scenarios (member submits, manager approves) which can be slow and flaky. **Mitigation**: Use deterministic test data, avoid time-sensitive assertions, use Playwright's `waitForResponse` for API calls.
- **Bug volume**: Testing may reveal bugs in state machine logic, permission checks, or UI edge cases that consume the bug-fix buffer. **Mitigation**: 8 hours of buffer allocated; if exceeded, defer P2 items (bulk approve, reopen, compliance dashboard) to a follow-up sprint.
- **OpenAPI spec alignment**: The spec must match the actual implementation exactly. **Mitigation**: Generate from route definitions where possible; manual review against endpoint tests.

---

## 5. Testing Strategy Per Sprint

### Sprint 0: Shared Foundations

| Test Type | Scope | Files |
|-----------|-------|-------|
| Unit | `BaseNotification` class sends to correct channels | `tests/Unit/Notifications/BaseNotificationTest.php` |
| Integration | Notification API endpoints return correct responses | `tests/Unit/Endpoint/Api/V1/NotificationEndpointTest.php` |
| Component | `NotificationBell.vue` renders, polls, marks as read | `resources/js/packages/ui/src/Notification/__tests__/NotificationBell.test.ts` |

### Sprint 1: Data Model + Core Service

| Test Type | Scope | Files |
|-----------|-------|-------|
| Unit | `TimesheetApproval` model: factory states, canTransitionTo(), scopes | Inline during APPR-003 development |
| Unit | `ApprovalStatus` enum: isLockedForMember(), isPermanentlyLocked() | Inline during APPR-001 |
| Integration | `TimesheetService::updateCell()` returns 423 on locked period | Quick smoke test, formal test in Sprint 2 (APPR-025) |
| Migration | `php artisan migrate` and `migrate:rollback` work cleanly | Manual verification |

### Sprint 2: API Layer + Timesheet Integration

| Test Type | Scope | Files |
|-----------|-------|-------|
| **Unit** | `TimesheetApprovalService` all 9 methods, all state transitions, edge cases (APPR-023, 12h) | `tests/Unit/Service/TimesheetApprovalServiceTest.php` |
| **Integration** | Lock checks on `TimesheetService::updateCell()` and all 5 `TimeEntryController` mutation methods (APPR-025, 6h) | `tests/Unit/Endpoint/Api/V1/TimesheetEndpointTest.php` (enhanced) |
| Manual | Submit/withdraw UI on timesheet page | Developer self-test |

### Sprint 3: Approvals Page + Notifications + Settings

| Test Type | Scope | Files |
|-----------|-------|-------|
| **Integration** | All 8 approval API endpoints with permission checks, validation, state transitions, team scope (APPR-024, 16h) | `tests/Unit/Endpoint/Api/V1/TimesheetApprovalEndpointTest.php` |
| Manual | Approvals page UX, notification emails, settings persistence | Developer + QA self-test |

### Sprint 4: Testing + Polish + E2E

| Test Type | Scope | Files |
|-----------|-------|-------|
| **Unit** | Notification mail/database content (APPR-026, 4h) | Notification test files |
| **Unit** | Reminder command logic (APPR-027, 4h) | `tests/Unit/Console/SendTimesheetRemindersCommandTest.php` |
| **Component** | Frontend component rendering and interactions (APPR-028, 8h) | `__tests__/` directories under TimesheetApproval UI components |
| **E2E** | Full user flows through browser (APPR-029, 12h) | `e2e/timesheet-approvals.spec.ts` |
| **Regression** | Full existing test suite | `php artisan test` + `npm run test` |

### Integration Testing Timeline

| Week | Integration Activity |
|------|---------------------|
| Week 4 (end Sprint 1) | Verify migrations run, model creates correctly, service basic smoke test |
| Week 6 (end Sprint 2) | Full API integration: submit via API, verify lock, verify 423 responses |
| Week 8 (end Sprint 3) | End-to-end manual walkthrough: submission -> notification -> approval page -> approve -> lock verified |
| Week 10 (end Sprint 4) | Full regression suite, E2E automated tests, production readiness check |

---

## 6. Definition of Done

### 6.1 Per-Task DoD Checklist

- [ ] Code follows Solidtime conventions: `declare(strict_types=1)` on PHP files, 4-space indent, LF line endings
- [ ] PHP: `composer fix && composer analyse` passes with zero errors
- [ ] JS/TS: `npm run lint:fix && npm run format` passes with zero errors
- [ ] New PHP classes have proper namespace, type hints on all parameters and return types
- [ ] New Vue components use `<script setup lang="ts">` pattern
- [ ] Database migrations have reversible `down()` methods
- [ ] New API endpoints have proper authentication (`auth:api`, `verified`) and authorization (`checkPermission`)
- [ ] Write endpoints include `check-organization-blocked` middleware
- [ ] Models use `CustomAuditable`, `HasUuids`, `HasFactory` traits
- [ ] Code has been self-reviewed by the author (no debug code, no TODO without tracking)
- [ ] Related existing tests still pass

### 6.2 Per-Sprint DoD Checklist

- [ ] All tasks in the sprint are individually DoD-complete
- [ ] Sprint acceptance criteria are met (verified by QA or code review)
- [ ] No critical or high-severity bugs remain unresolved
- [ ] `php artisan test` passes with zero failures
- [ ] `npm run test` passes with zero failures
- [ ] Feature branch rebased on latest `main` with no conflicts
- [ ] Code reviewed by at least one other developer
- [ ] Sprint demo completed (live walkthrough of new functionality)

### 6.3 Feature-Level DoD Checklist

- [ ] All 30 APPR tasks completed and DoD-verified
- [ ] All 8 user stories (USR-001 through USR-008) have passing acceptance criteria
- [ ] Backend test coverage: `TimesheetApprovalService` >90%, `TimesheetApprovalController` endpoints >85%
- [ ] Frontend component test coverage: all 5 approval-specific components have tests
- [ ] E2E test coverage: 7 core user flows automated in Playwright
- [ ] OpenAPI specification updated and validated
- [ ] No P0 or P1 bugs remain open
- [ ] Performance requirements met:
  - Lock check query < 5ms
  - Approval list page < 200ms
  - Bulk approve (50 items) < 2s
  - Reminder command (10,000 members) < 60s
- [ ] Security requirements verified:
  - Self-approval prevention tested
  - Team scope for managers tested
  - XSS prevention on reviewer comments verified (strip_tags)
  - Rate limiting on write endpoints configured
- [ ] All notification emails render correctly (tested in Mailtrap or equivalent)
- [ ] Audit trail captures all state transitions (verified via `audits` table queries)
- [ ] Feature works end-to-end in staging environment
- [ ] Documentation complete: OpenAPI spec, inline code comments on complex methods
- [ ] PR approved and merged to `main`

---

## 7. Risk Register

### 7.1 Technical Risks

| Risk ID | Risk | Probability | Impact | Mitigation Strategy |
|---------|------|:-----------:|:------:|---------------------|
| TR-01 | **Merge conflicts on shared files** (`TimesheetService.php`, `useTimesheet.ts`, `TimesheetWeekAccordion.vue`) between grid branch and approval branch | High | High | Ensure grid branch is merged to main before starting Sprint 1 APPR-007. Rebase frequently. Separate commits per layer. |
| TR-02 | **APPR-006 complexity exceeds estimate** (16h for state machine + 9 methods) | Medium | High | Break into 4 sub-tasks. Write tests concurrently. Track progress daily. If slipping by day 3, escalate. |
| TR-03 | **Notification infrastructure delay** (FOUND-001 through FOUND-005 not ready when needed) | Medium | Medium | APPR-012/013 are Sprint 3; FOUND tasks are Sprint 0. Two full sprints of buffer. If still blocked, implement with Mail classes first (original PRD ADR-4), migrate to Notifications later. |
| TR-04 | **Performance of lock check query on large datasets** | Low | Medium | Composite index on `(member_id, start_date)` ensures O(log n) lookup. Load test with 10k approval records during Sprint 4. |
| TR-05 | **Timezone edge cases in week boundary calculations** | Medium | Medium | Reuse existing `TimesheetService` timezone handling. Add explicit test cases for DST transitions and UTC offset boundaries. |
| TR-06 | **Team scope query complexity** (managers see only shared-project members) | Medium | Low | Start with admin-sees-all in Sprint 3, add team scoping as a follow-up. Use existing `ProjectMember` relationship. |
| TR-07 | **Import service lock enforcement gap** (7th mutation point) | Low | Low | Not in critical path. Add as a follow-up task after Sprint 4 if not addressed in APPR-008. |

### 7.2 Dependency Risks

| Risk ID | Risk | Probability | Impact | Mitigation Strategy |
|---------|------|:-----------:|:------:|---------------------|
| DR-01 | **Grid branch (`feature/weekly-timesheet-grid`) not merged in time** | Medium | High | Coordinate with grid branch owner. Plan Sprint 1 to work on new files (model, migration, service) that do not conflict. Defer shared-file modifications to Sprint 2. |
| DR-02 | **FOUND-007 (modular permissions) architectural disagreement** | Low | Medium | APPR-005 can add permissions directly to `JetstreamServiceProvider` as a fallback. Refactor to modular pattern later. |
| DR-03 | **Shared `ApprovalStatus` enum scope disagreement with other features** | Low | Low | Timesheet Approvals uses DRAFT, SUBMITTED, APPROVED, CHANGES_REQUESTED, WITHDRAWN, REOPENED. If other features need REJECTED, enum already includes it. No blocking conflict expected. |

### 7.3 Capacity Risks

| Risk ID | Risk | Probability | Impact | Mitigation Strategy |
|---------|------|:-----------:|:------:|---------------------|
| CR-01 | **Sprint 2 overload** (38 SP with 3 parallel tracks) | Medium | Medium | Monitor daily. If backend track slips, defer APPR-010 (request validation, 3 SP) to Sprint 3. Frontend can work with mock API data. |
| CR-02 | **Sprint 3 overload** (39 SP with large APPR-020 and APPR-024) | Medium | Medium | APPR-020 can be split: list view in Sprint 3, detail view + dialogs in Sprint 4. APPR-024 can prioritize P0 scenarios and defer P2 scenarios. |
| CR-03 | **QA bandwidth** (APPR-023 + APPR-025 in Sprint 2, APPR-024 in Sprint 3, APPR-026-029 in Sprint 4) | Medium | High | Consider allocating a second QA resource for Sprint 4. Alternatively, backend/frontend developers write their own unit tests, QA focuses on integration and E2E. |
| CR-04 | **Developer availability** (illness, PTO, competing priorities) | Low | High | Cross-train team members. Maintain clear documentation so any developer can pick up a task. Keep 10% buffer in each sprint. |

---

## 8. Milestone Timeline

```
Week 1          Week 2          Week 3          Week 4          Week 5
|--- Sprint 0 --|--- Sprint 0 --|--- Sprint 1 --|--- Sprint 1 --|--- Sprint 2 --|
|               |               |               |               |               |
| FOUND-001     | FOUND-003     | APPR-001      | APPR-006      | APPR-008      |
| FOUND-002     | FOUND-004     | APPR-002      | APPR-006      | APPR-009      |
| FOUND-007     | FOUND-005     | APPR-003      | APPR-007      | APPR-010      |
| SF-05a/b      |               | APPR-004      | APPR-015      | APPR-016      |
|               |               | APPR-005      |               | APPR-017      |
|               |               |               |               |               |
|  [M0]         |  [M1]         |               |  [M2]         |               |

Week 6          Week 7          Week 8          Week 9          Week 10
|--- Sprint 2 --|--- Sprint 3 --|--- Sprint 3 --|--- Sprint 4 --|--- Sprint 4 --|
|               |               |               |               |               |
| APPR-011      | APPR-012      | APPR-020      | APPR-026      | APPR-029      |
| APPR-018      | APPR-013      | APPR-021      | APPR-027      | APPR-030      |
| APPR-023      | APPR-014      | APPR-022      | APPR-028      | Bug fixes      |
| APPR-025      | APPR-019      | APPR-024      |               | Polish         |
|               |               |               |               |               |
|  [M3]         |               |  [M4]         |               |  [M5]         |
```

### Key Milestones

| Milestone | Week | Description | Go/No-Go Criteria |
|-----------|:----:|-------------|-------------------|
| **M0** | 1 | Shared Foundations kickoff | Grid branch merge plan confirmed; FOUND task assignments finalized |
| **M1** | 2 | Foundations complete | Notification infrastructure works end-to-end; modular permissions scaffold in place; shared enum/trait created |
| **M2** | 4 | Core backend complete | Migrations run; model works; service handles all state transitions; lock checks block cell edits; all existing tests pass |
| **M3** | 6 | API + Timesheet UI complete | All 8 API endpoints functional; timesheet page shows submit/withdraw buttons; cells lock correctly; service unit tests pass |
| **M4** | 8 | Full feature functional | Approvals page works for managers; notifications sent on all transitions; reminder command operational; org settings configurable; endpoint tests pass |
| **M5** | 10 | Feature release-ready | All tests pass (unit, integration, component, E2E); OpenAPI spec updated; zero P0/P1 bugs; performance requirements met; PR ready for merge |

### Go/No-Go Decision Points

| Decision Point | When | Criteria | Action if No-Go |
|----------------|------|----------|-----------------|
| **Sprint 0 -> Sprint 1** | End of Week 2 | FOUND tasks complete; grid branch merged or merge plan confirmed | Delay Sprint 1 by 1 week; de-scope APPR-012/013 to use Mail classes instead of Notifications |
| **Sprint 1 -> Sprint 2** | End of Week 4 | APPR-006 complete and unit-tested; migrations stable | If APPR-006 incomplete: extend Sprint 1 by 3-5 days; shift APPR-009 to start of Sprint 2 with overlap |
| **Sprint 3 -> Sprint 4** | End of Week 8 | Core approval flow works end-to-end (submit -> approve -> lock); Approvals page renders | If Approvals page incomplete: move APPR-020 completion to Sprint 4; reduce E2E test scope |
| **Feature Release** | End of Week 10 | All M5 criteria met | If not met: extend by 1 sprint (2 weeks) for bug fixes and remaining tests only |

---

## Appendix A: Task-to-APPR ID Mapping

The task_assignments document uses `APPR-xxx` IDs. Per PRD AMD-01, all task IDs are namespaced as `APPR-xxx`. The mapping is 1:1:

| TASK ID | APPR ID | Description |
|---------|---------|-------------|
| APPR-001 | APPR-001 | Create TimesheetApprovalStatus Enum |
| APPR-002 | APPR-002 | Create Database Migration for timesheet_approvals Table |
| APPR-003 | APPR-003 | Create TimesheetApproval Model + Factory |
| APPR-004 | APPR-004 | Add Organization Reminder Settings Migration |
| APPR-005 | APPR-005 | Register New Permissions in JetstreamServiceProvider |
| APPR-006 | APPR-006 | Create TimesheetApprovalService |
| APPR-007 | APPR-007 | Enhance TimesheetService with Lock Checks |
| APPR-008 | APPR-008 | Enhance TimeEntryController with Lock Checks |
| APPR-009 | APPR-009 | Create TimesheetApprovalController |
| APPR-010 | APPR-010 | Create Request Validation Classes |
| APPR-011 | APPR-011 | Register API Routes |
| APPR-012 | APPR-012 | Create Notification Mail Classes |
| APPR-013 | APPR-013 | Dispatch Notifications from TimesheetApprovalService |
| APPR-014 | APPR-014 | Create TimesheetReminderCommand |
| APPR-015 | APPR-015 | Add TypeScript Types for Approval |
| APPR-016 | APPR-016 | Enhance useTimesheetStore with Approval Actions |
| APPR-017 | APPR-017 | Add Submit/Withdraw UI to TimesheetWeekAccordion |
| APPR-018 | APPR-018 | Disable Cell Editing When Week is Locked |
| APPR-019 | APPR-019 | Create useApprovalsStore Pinia Store |
| APPR-020 | APPR-020 | Create Approvals Vue Page |
| APPR-021 | APPR-021 | Add Sidebar Navigation Item for Approvals |
| APPR-022 | APPR-022 | Add Approval Settings to Organization Settings Page |
| APPR-023 | APPR-023 | TimesheetApprovalService Unit Tests |
| APPR-024 | APPR-024 | TimesheetApprovalController API Endpoint Tests |
| APPR-025 | APPR-025 | Enhanced TimesheetEndpointTest (Lock Checks) |
| APPR-026 | APPR-026 | Notification Mail Tests |
| APPR-027 | APPR-027 | Reminder Command Test |
| APPR-028 | APPR-028 | Frontend Component Tests |
| APPR-029 | APPR-029 | E2E Playwright Tests |
| APPR-030 | APPR-030 | Update OpenAPI Specification |

## Appendix B: Sprint Assignment Summary by Role

| Sprint | Backend Developer | Frontend Developer | QA/Fullstack Developer |
|--------|------------------|--------------------|----------------------|
| **0** | FOUND-001, FOUND-002, FOUND-004, FOUND-007, SF-05a, SF-05b (17h) | FOUND-003 (8h) | FOUND-005 (4h) |
| **1** | APPR-001, APPR-002, APPR-003, APPR-004, APPR-005, APPR-006, APPR-007 (32h) | APPR-015 (2h) | Available for Sprint 0 overflow or early APPR-023 |
| **2** | APPR-008, APPR-009, APPR-010, APPR-011 (17h) | APPR-016, APPR-017, APPR-018 (20h) | APPR-023, APPR-025 (18h) |
| **3** | APPR-012, APPR-013, APPR-014 (17h) | APPR-019, APPR-020, APPR-021 (19h) | APPR-022, APPR-024 (22h) |
| **4** | APPR-030 (4h) + bug fixes | APPR-028 (8h) + bug fixes | APPR-026, APPR-027, APPR-029 (20h) |

---

*Last updated: 2026-02-06*
