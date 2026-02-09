# Shared Foundations — Cross-Feature Decisions

**Date**: 2026-02-06
**Status**: Active
**Applies to**: All 17 feature PRDs

This document defines cross-cutting architectural decisions that all feature PRDs must conform to. It was created to resolve inconsistencies identified during the PRD review phase.

---

## SF-01: Task ID Namespace

Each feature uses a unique task ID prefix to avoid collisions across the 400+ tasks.

| # | Feature | Prefix | Example |
|---|---------|--------|---------|
| 00 | Weekly Timesheet Grid | `TSG-` | TSG-001, TSG-002, ... |
| 01 | Timesheet Approvals | `APPR-` | APPR-001, APPR-002, ... |
| 02 | Expense Management | `EXP-` | EXP-001, EXP-002, ... |
| 03 | Budgets & Alerts | `BUD-` | BUD-001, BUD-002, ... |
| 04 | Invoicing System | `INV-` | INV-001, INV-002, ... |
| 05 | Calendar Enhanced | `CAL-` | CAL-001, CAL-002, ... |
| 06 | Kiosk & Clock Mode | `KIO-` | KIO-001, KIO-002, ... |
| 07 | PTO & Time Off | `PTO-` | PTO-001, PTO-002, ... |
| 08 | Resource Scheduling | `SCHED-` | SCHED-001, SCHED-002, ... |
| 09 | Advanced Reporting | `RPT-` | RPT-001, RPT-002, ... |
| 10 | Teams & Groups | `TEAM-` | TEAM-001, TEAM-002, ... |
| 11 | Tags & Custom Fields | `TAG-` | TAG-001, TAG-002, ... |
| 12 | Punch-Only / Time-Clock Mode | `PCM-` | PCM-001, PCM-002, ... |
| 13 | Audit Trail / Activity Log | `AUD-` | AUD-001, AUD-002, ... |
| 14 | Online Payments & Accounting Sync | `PAY-` | PAY-001, PAY-002, ... |
| 15 | Attendance & Overtime Tracking | `ATT-` | ATT-001, ATT-002, ... |
| 16 | PM Tool Integrations | `PMI-` | PMI-001, PMI-002, ... |

---

## SF-02: Permission Naming Convention

All features MUST use the pattern: `{entity}:{action}` or `{entity}:{action}:{scope}`

### Rules

1. **Entity names** use kebab-case: `time-entries`, `timesheet-approvals`, `expense-categories`
2. **Actions** are single verbs: `view`, `create`, `update`, `delete`, `approve`, `submit`, `manage`, `configure`
3. **Scope** (optional): `own` (user's own data), `all` (org-wide data)
4. **No hyphenated actions**: Use `{entity}:{action}:{target}` NOT `{entity}:{action-target}`
5. Separate admin/config permissions: `{entity}:configure`

### Corrected Permission Map

**Feature 01 — Timesheet Approvals**:
| Old | New |
|-----|-----|
| `timesheets:view-approvals` | `timesheet-approvals:view` |
| `timesheets:submit:own` | `timesheet-approvals:submit:own` |
| `timesheets:approve` | `timesheet-approvals:approve` |
| `timesheets:approve:all` | `timesheet-approvals:approve:all` |
| `timesheets:reopen` | `timesheet-approvals:reopen` |
| `timesheets:configure` | `timesheet-approvals:configure` |

**Feature 02 — Expense Management**:
| Old | New |
|-----|-----|
| `expenses:view:own` | `expenses:view:own` (unchanged) |
| `expenses:view:all` | `expenses:view:all` (unchanged) |
| `expenses:create:own` | `expenses:create:own` (unchanged) |
| `expenses:approve` | `expenses:approve` |
| `expense-categories:view` | `expense-categories:view` (unchanged) |

**Feature 03 — Budgets & Alerts**:
| Old | New |
|-----|-----|
| `budgets:view` | `budgets:view` (unchanged) |
| `budgets:update` | `budgets:update` (unchanged) |
| `budgets:alerts:manage` | `budget-alerts:manage` |

**Feature 07 — PTO & Time Off**:
| Old | New |
|-----|-----|
| `time-off-requests:view:own` | `time-off-requests:view:own` (unchanged) |
| `time-off-requests:view:all` | `time-off-requests:view:all` (unchanged) |

**Feature 10 — Teams & Groups**:
| Old | New |
|-----|-----|
| `teams:update-members` | `teams:update:members` |
| `teams:update-projects` | `teams:update:projects` |
| `teams:update-clients` | `teams:update:clients` |

---

## SF-03: Migration Timestamp Allocation

Each feature is assigned a unique date prefix for migrations to avoid conflicts.

| # | Feature | Date Prefix | Example |
|---|---------|-------------|---------|
| 01 | Timesheet Approvals | `2026_03_01_` | `2026_03_01_000001_create_timesheet_approvals_table.php` |
| 02 | Expense Management | `2026_03_02_` | `2026_03_02_000001_create_expenses_table.php` |
| 03 | Budgets & Alerts | `2026_03_03_` | `2026_03_03_000001_add_budget_columns_to_projects_table.php` |
| 04 | Invoicing System | `2026_03_04_` | `2026_03_04_000001_create_invoices_table.php` |
| 05 | Calendar Enhanced | `2026_03_05_` | `2026_03_05_000001_create_calendar_connections_table.php` |
| 06 | Kiosk & Clock Mode | `2026_03_06_` | `2026_03_06_000001_create_kiosks_table.php` |
| 07 | PTO & Time Off | `2026_03_07_` | `2026_03_07_000001_create_time_off_policies_table.php` |
| 08 | Resource Scheduling | `2026_03_08_` | `2026_03_08_000001_create_assignments_table.php` |
| 09 | Advanced Reporting | `2026_03_09_` | `2026_03_09_000001_add_cost_rate_to_time_entries.php` |
| 10 | Teams & Groups | `2026_03_10_` | `2026_03_10_000001_create_teams_table.php` |
| 11 | Tags & Custom Fields | `2026_03_11_` | `2026_03_11_000001_create_custom_fields_table.php` |
| 12 | Punch-Only / Time-Clock Mode | `2026_03_12_` | `2026_03_12_000001_create_punch_records_table.php` |
| 13 | Audit Trail / Activity Log | `2026_03_13_` | `2026_03_13_000001_create_activity_log_table.php` |
| 14 | Online Payments & Accounting Sync | `2026_03_14_` | `2026_03_14_000001_create_payments_table.php` |
| 15 | Attendance & Overtime Tracking | `2026_03_15_` | `2026_03_15_000001_create_attendance_records_table.php` |
| 16 | PM Tool Integrations | `2026_03_16_` | `2026_03_16_000001_create_pm_connections_table.php` |
| 00 | Weekly Timesheet Grid | None | No migrations (uses existing schema) |
| **Shared** | Cross-Feature Foundations | `2026_02_28_` | `2026_02_28_000001_add_weekly_capacity_to_members.php` |

Shared migrations run **before** any feature migration.

---

## SF-04: Shared Notification Infrastructure

### Decision

All features use Laravel's built-in Notification system with `database` + `mail` channels. This is a shared prerequisite that must be implemented before any feature.

### Shared Tasks (prefix: `FOUND-`)

**FOUND-001: Create Notification Infrastructure Migration**
- Create the `notifications` table using `php artisan notifications:table`
- Add `notification_preferences` JSON column to `members` table
- Estimated effort: 2 hours

**FOUND-002: Create Base Notification Classes**
- `App\Notifications\BaseNotification` extending `Illuminate\Notifications\Notification`
- Default channels: `['database', 'mail']`
- Respects per-member `notification_preferences`
- Estimated effort: 4 hours

**FOUND-003: Create Notification Bell UI Component**
- `NotificationBell.vue` in AppLayout header (next to user menu)
- Polls `/api/v1/organizations/{org}/notifications` every 60 seconds
- Mark as read on click
- Links to relevant entity
- Estimated effort: 8 hours

**FOUND-004: Create Notification API Endpoints**
- `GET /api/v1/organizations/{org}/notifications` — list (paginated)
- `PATCH /api/v1/organizations/{org}/notifications/{id}/read` — mark read
- `PATCH /api/v1/organizations/{org}/notifications/read-all` — mark all read
- `GET /api/v1/organizations/{org}/notifications/unread-count` — badge count
- Estimated effort: 6 hours

**FOUND-005: Add Notification Preferences to Organization Settings**
- Per-member toggle for email notifications
- Per-notification-type preferences (future extensibility)
- Estimated effort: 4 hours

**Total shared effort: 24 hours**

### Per-Feature Notification Usage

Each feature creates specific Notification classes extending `BaseNotification`:

| Feature | Notifications |
|---------|--------------|
| 01 Approvals | `TimesheetSubmittedNotification`, `TimesheetApprovedNotification`, `TimesheetChangesRequestedNotification`, `TimesheetReminderNotification` |
| 02 Expenses | `ExpenseSubmittedNotification`, `ExpenseApprovedNotification`, `ExpenseRejectedNotification` |
| 03 Budgets | `BudgetThresholdNotification`, `BudgetExceededNotification` |
| 07 PTO | `TimeOffRequestSubmittedNotification`, `TimeOffRequestApprovedNotification`, `TimeOffRequestDeniedNotification` |
| 15 Attendance & Overtime | `AttendanceAlertNotification`, `OvertimeThresholdNotification`, `OvertimeExceededNotification` |

---

## SF-05: Shared Approval Pattern

### Decision

Features with approval workflows use a **consistent inline status pattern** with the following standard:

### Standard Approval Status Enum

```php
// App\Enums\ApprovalStatus
enum ApprovalStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';
    case CHANGES_REQUESTED = 'changes_requested';
    case REJECTED = 'rejected';
    case WITHDRAWN = 'withdrawn';
}
```

Not every feature uses every status. Each feature declares which statuses apply:

| Feature | Statuses Used |
|---------|--------------|
| 01 Timesheet Approvals | draft, submitted, approved, changes_requested, withdrawn |
| 02 Expense Management | draft, submitted, approved, rejected, withdrawn |
| 07 PTO Requests | submitted, approved, rejected, withdrawn |

### Standard Approval Fields

Every approvable entity includes these columns:

```
status          enum (from ApprovalStatus)
submitted_at    timestamp nullable
reviewer_id     uuid nullable FK -> members
reviewed_at     timestamp nullable
reviewer_comment text nullable
```

### Standard Business Rules

1. **Self-approval is NOT allowed** in any feature. The `reviewer_id` must differ from the submitter's `member_id`.
2. **Only Managers, Admins, and Owners** can approve/reject (configurable per feature).
3. **Submission locks editing** — entries in `submitted` or `approved` status cannot be modified without first withdrawing or having changes requested.
4. **Notifications are sent** on every status transition (via SF-04 notification infrastructure).
5. **Audit logging** captures all status changes via `CustomAuditable`.

### Shared Approval Trait

```php
// App\Traits\HasApprovalWorkflow
trait HasApprovalWorkflow
{
    public function isEditable(): bool
    {
        return in_array($this->status, [ApprovalStatus::DRAFT, ApprovalStatus::CHANGES_REQUESTED, ApprovalStatus::WITHDRAWN]);
    }

    public function isSubmitted(): bool
    {
        return $this->status === ApprovalStatus::SUBMITTED;
    }

    public function isApproved(): bool
    {
        return $this->status === ApprovalStatus::APPROVED;
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'reviewer_id');
    }
}
```

### Impact on PRD 01 (Timesheet Approvals)

The separate `TimesheetApproval` model is **retained** because timesheet approvals are per-week-period (not per-entity). The `TimesheetApproval` model uses the shared `ApprovalStatus` enum and `HasApprovalWorkflow` trait but remains a dedicated model that wraps a member+date range.

### Impact on PRD 02 (Expense Management)

- Replace inline status strings with shared `ApprovalStatus` enum
- Add `HasApprovalWorkflow` trait
- **Remove self-approval allowance** — align with global no-self-approval rule
- **Add notifications** on status transitions using SF-04 infrastructure

---

## SF-06: Shared `weekly_capacity` Column

### Decision

The `weekly_capacity` column on the `members` table is owned by a **shared foundation migration**, not by any individual feature.

### Shared Migration: `2026_02_28_000001_add_weekly_capacity_to_members.php`

```php
Schema::table('members', function (Blueprint $table) {
    $table->unsignedInteger('weekly_capacity')
        ->default(144000)  // 40 hours in seconds (matching solidtime's seconds-based duration convention)
        ->after('billable_rate')
        ->comment('Weekly work capacity in seconds. Default 40h = 144000s.');
});
```

### Shared Migration: `2026_02_28_000002_add_default_weekly_capacity_to_organizations.php`

```php
Schema::table('organizations', function (Blueprint $table) {
    $table->unsignedInteger('default_weekly_capacity')
        ->default(144000)
        ->after('billable_rate')
        ->comment('Default weekly capacity for new members in seconds.');
});
```

### Task

**FOUND-006: Create shared weekly_capacity migrations**
- Migration for `members.weekly_capacity` (unsigned integer, default 144000)
- Migration for `organizations.default_weekly_capacity` (unsigned integer, default 144000)
- Estimated effort: 2 hours

### Impact on PRDs

- **PRD 08**: Remove TASK where `weekly_capacity` is added to members. Reference FOUND-006 as prerequisite.
- **PRD 09**: Remove TASK where `weekly_capacity` is added to members. Reference FOUND-006 as prerequisite.
- **PRD 15**: Overtime calculations depend on `weekly_capacity` to determine when a member exceeds their scheduled hours. Reference FOUND-006 as prerequisite.

---

## SF-07: Feature Flag for Teams Scoping (PRD 10)

### Decision

Add `enable_team_scoping` boolean to `organizations` table. When `false` (default), all queries behave as today. When `true`, the `TeamScopeService` filters apply.

### Shared Migration: `2026_03_10_000000_add_team_scoping_flag_to_organizations.php`

```php
Schema::table('organizations', function (Blueprint $table) {
    $table->boolean('enable_team_scoping')
        ->default(false)
        ->after('default_weekly_capacity')
        ->comment('When enabled, projects/clients/time entries are filtered by team membership.');
});
```

### Behavior

- **Flag OFF (default)**: All existing queries remain unchanged. Teams can be created and members assigned, but scoping is not enforced. This allows gradual setup.
- **Flag ON**: `TeamScopeService` applies team-based WHERE clauses to project, client, and time entry queries. Members only see data for their assigned teams.
- **Admin/Owner override**: Admins and Owners always see all data regardless of team scoping.

---

## SF-08: JetstreamServiceProvider Merge Protocol

### Decision

Rather than having all 17 features modify `JetstreamServiceProvider.php` independently, permissions are registered via a **modular pattern**.

### Approach

Each feature creates its own permissions file:

```
app/Permissions/
├── TimesheetApprovalPermissions.php
├── ExpensePermissions.php
├── BudgetPermissions.php
├── InvoicePermissions.php
├── CalendarPermissions.php
├── KioskPermissions.php
├── TimeOffPermissions.php
├── SchedulingPermissions.php
├── ReportingPermissions.php
└── TeamPermissions.php
```

Each file exports a static method:

```php
// app/Permissions/TimesheetApprovalPermissions.php
class TimesheetApprovalPermissions
{
    public static function register(): void
    {
        Jetstream::role('admin', 'Administrator', [
            // ... existing permissions + new ones
            'timesheet-approvals:view',
            'timesheet-approvals:submit:own',
            'timesheet-approvals:approve',
            'timesheet-approvals:approve:all',
            'timesheet-approvals:reopen',
            'timesheet-approvals:configure',
        ])->description('...');
    }
}
```

`JetstreamServiceProvider::configurePermissions()` then calls each:

```php
protected function configurePermissions(): void
{
    // ... existing permission setup ...

    // Feature permissions (each file is self-contained)
    TimesheetApprovalPermissions::register();
    ExpensePermissions::register();
    // etc.
}
```

### Benefits

- Each feature only touches its own permissions file — no merge conflicts
- `JetstreamServiceProvider` has one-line additions per feature — minimal conflict risk
- Permissions can be conditionally loaded (e.g., behind feature flags)

### Task

**FOUND-007: Create modular permissions infrastructure**
- Create `app/Permissions/` directory structure
- Refactor existing permissions into this pattern
- Estimated effort: 4 hours

---

## SF-09: Feature Deployment Order

### Recommended Implementation Order

```
Phase 0: Shared Foundations (FOUND-001 through FOUND-007)       ~36 hours
    │
    ├── Phase 1a (parallel, independent):
    │   ├── 10 Teams & Groups          (6 weeks)   ← enables scoping for all later features
    │   ├── 06 Kiosk & Clock Mode      (8 weeks)   ← fully independent
    │   └── 05 Calendar Enhanced        (6 weeks)   ← fully independent
    │
    ├── Phase 1b (parallel, soft deps on each other):
    │   ├── 01 Timesheet Approvals     (8 weeks)   ← uses shared approval pattern
    │   ├── 02 Expense Management      (6 weeks)   ← uses shared approval pattern
    │   └── 03 Budgets & Alerts        (6 weeks)   ← uses shared notification infra
    │
    ├── Phase 2a (parallel, after Phase 1b):
    │   ├── 07 PTO & Time Off          (8 weeks)   ← uses shared approval pattern
    │   └── 04 Invoicing System        (14 weeks)  ← benefits from expenses
    │
    └── Phase 2b (after Phase 2a):
    │   ├── 08 Resource Scheduling     (10 weeks)  ← benefits from PTO
    │   └── 09 Advanced Reporting      (12 weeks)  ← benefits from expenses + budgets
    │
    └── Phase 3 (after Phase 0, all independent):
        ├── 11 Tags & Custom Fields            ← fully independent
        ├── 12 Punch-Only / Time-Clock Mode    ← fully independent
        ├── 13 Audit Trail / Activity Log      ← fully independent
        ├── 14 Online Payments & Acct Sync     ← benefits from invoicing (soft)
        ├── 15 Attendance & Overtime Tracking   ← uses FOUND-006 (weekly_capacity)
        └── 16 PM Tool Integrations            ← fully independent
```

### Hard Dependencies

| Feature | Must Come After |
|---------|----------------|
| 01 Timesheet Approvals | Phase 0 (shared foundations) |
| 08 Resource Scheduling | Phase 0 + FOUND-006 (weekly_capacity) |
| 09 Advanced Reporting | Phase 0 + FOUND-006 (weekly_capacity) |
| 15 Attendance & Overtime | Phase 0 + FOUND-006 (weekly_capacity) |

### Soft Dependencies (feature works standalone, enhanced when dependency ships)

| Feature | Enhanced By |
|---------|------------|
| 04 Invoicing | 02 Expenses (expense line items on invoices) |
| 08 Scheduling | 07 PTO (capacity reduction for time off) |
| 09 Reporting | 02 Expenses + 03 Budgets (expense/budget reports) |
| 14 Online Payments | 04 Invoicing (payment collection on invoices) |
| All features | 10 Teams (team-scoped queries) |

---

## SF-10: Effort-to-Story-Point Ratio

### Decision

All features standardize on **2.0 hours per story point**.

| Story Points | Hours | Meaning |
|:---:|:---:|---------|
| 1 | 2h | Trivial — config change, single-file edit |
| 2 | 4h | Small — single model, migration, or component |
| 3 | 6h | Medium — service method, API endpoint, or page section |
| 5 | 10h | Large — full service class, complex UI component |
| 8 | 16h | Very large — should consider splitting |
| 13 | 26h | Epic-sized — must be split |

PRDs whose ratios deviate significantly (01 at 1.5, 04 at 2.7, 08 at 1.5, 09 at 1.6) should be recalibrated during the Architecture phase.

---

## Summary of Shared Foundation Tasks

| Task ID | Description | Effort | Blocks |
|---------|-------------|--------|--------|
| FOUND-001 | Notification infrastructure migration | 2h | FOUND-002 |
| FOUND-002 | Base notification classes | 4h | FOUND-003, FOUND-004 |
| FOUND-003 | Notification bell UI component | 8h | All features with notifications |
| FOUND-004 | Notification API endpoints | 6h | FOUND-003 |
| FOUND-005 | Notification preferences in org settings | 4h | — |
| FOUND-006 | Shared weekly_capacity migrations | 2h | PRDs 08, 09, 15 |
| FOUND-007 | Modular permissions infrastructure | 4h | All features |
| **Total** | | **30h** | |

---

Last updated: 2026-02-06
