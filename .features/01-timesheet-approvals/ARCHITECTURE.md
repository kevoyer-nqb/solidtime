# Feature 01: Timesheet Approvals — Technical Architecture

**Date**: 2026-02-06  
**Status**: Draft  
**Feature Branch**: `feature/timesheet-approvals` (from `feature/weekly-timesheet-grid`)  
**Task Prefix**: `APPR-` (per SF-01)  
**Migration Date Prefix**: `2026_03_01_` (per SF-03)

---

## Executive Summary

This document provides the complete technical architecture for **Timesheet Approvals**, following Solidtime's established patterns and integrating with the existing weekly timesheet grid. The feature implements a state-machine-based approval workflow where members submit weekly timesheets, managers/admins review and approve/reject them, and approved periods become permanently locked.

**Key Architectural Decisions**:
- Uses **shared `ApprovalStatus` enum** and `HasApprovalWorkflow` trait (SF-05)
- Extends **shared notification infrastructure** via `BaseNotification` (SF-04)
- Follows **modular permissions pattern** with `timesheet-approvals:*` namespace (SF-02, SF-08)
- Integrates seamlessly with **existing `TimesheetService`** via lock checks
- **Frontend**: Pinia store + Vue pages + UI components following established patterns

---

## Table of Contents

1. [Data Model Design](#1-data-model-design)
2. [API Contract](#2-api-contract)
3. [Service Layer](#3-service-layer)
4. [Frontend Architecture](#4-frontend-architecture)
5. [Notification Design](#5-notification-design)
6. [Permission Matrix](#6-permission-matrix)
7. [Migration Strategy](#7-migration-strategy)
8. [Integration Points](#8-integration-points)
9. [File Manifest](#9-file-manifest)

---

## 1. Data Model Design

### 1.1 State Machine

```
                    ┌───────────┐
                    │   draft   │ (default, editable)
                    └─────┬─────┘
                          │ submit (member)
                          ▼
         ┌────────────────────────────┐
         │       submitted            │ (locked for member)
         └────┬──────────────────┬────┘
              │                  │
    approve   │                  │  request_changes
    (reviewer)│                  │  (reviewer)
              ▼                  ▼
        ┌──────────┐      ┌──────────────────┐
        │ approved │      │ changes_requested│ (editable)
        └────┬─────┘      └────────┬──────────┘
             │                     │ resubmit
      reopen │ (admin)             └──────┐
             ▼                            │
        ┌──────────┐                      │
        │ reopened │──────submit──────────┘
        └──────────┘
```

**Locking Rules**:
- `submitted` or `approved`: **locked for member** (cannot edit time entries)
- `approved`: **permanently locked** (admin reopen required)
- `draft`, `changes_requested`, `reopened`, `withdrawn`: **editable**

### 1.2 TimesheetApproval Model

**File**: `/home/keven/Documents/solidtime-analysis/app/Models/TimesheetApproval.php`

**Key Properties**:
- Uses **shared `ApprovalStatus` enum** (SF-05): `draft`, `submitted`, `approved`, `changes_requested`, `withdrawn`, `reopened`
- Uses **shared `HasApprovalWorkflow` trait** (SF-05): provides `isEditable()`, `isApproved()`, `reviewer()` relationship
- Uses **`CustomAuditable`** trait: all state changes logged via OwenIt\Auditing
- Uses **`HasUuids`** trait: UUID primary keys

**Relationships**:
```php
public function member(): BelongsTo  // Submitter
public function reviewer(): BelongsTo  // Approver (from HasApprovalWorkflow trait)
public function organization(): BelongsTo
```

**Scopes**:
```php
scopeForOrganization($organization)
scopeForMember($member)
scopeWithStatus($statuses)  // Single or array of ApprovalStatus
scopePending()  // submitted or changes_requested
```

**Business Logic**:
```php
public function canTransitionTo(ApprovalStatus $newStatus): bool
{
    $allowed = [
        ApprovalStatus::DRAFT => [ApprovalStatus::SUBMITTED],
        ApprovalStatus::SUBMITTED => [
            ApprovalStatus::APPROVED,
            ApprovalStatus::CHANGES_REQUESTED,
            ApprovalStatus::WITHDRAWN,
        ],
        ApprovalStatus::CHANGES_REQUESTED => [ApprovalStatus::SUBMITTED],
        ApprovalStatus::APPROVED => [ApprovalStatus::REOPENED],
        ApprovalStatus::REOPENED => [ApprovalStatus::SUBMITTED],
        ApprovalStatus::WITHDRAWN => [ApprovalStatus::SUBMITTED],
    ];

    return in_array($newStatus, $allowed[$this->status] ?? [], true);
}
```

### 1.3 Migration: timesheet_approvals Table

**File**: `/home/keven/Documents/solidtime-analysis/database/migrations/2026_03_01_000001_create_timesheet_approvals_table.php`

**Schema**:
```sql
CREATE TABLE timesheet_approvals (
    id UUID PRIMARY KEY,
    member_id UUID FK->members(id) CASCADE,
    organization_id UUID FK->organizations(id) CASCADE,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status VARCHAR(50) DEFAULT 'draft',
    submitted_at TIMESTAMP NULL,
    reviewer_id UUID FK->members(id) SET NULL,
    reviewed_at TIMESTAMP NULL,
    reviewer_comment TEXT NULL,
    total_seconds INTEGER DEFAULT 0,
    entry_count INTEGER DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    UNIQUE (member_id, start_date, end_date),
    INDEX (organization_id, status),
    INDEX (member_id, start_date),
    INDEX (reviewer_id)
);
```

**Rationale**:
- **Unique constraint**: One approval per member per week period prevents duplicate submissions
- **Indexes**: Optimize common queries (approval list by org+status, member's weeks, reviewer's queue)
- **`total_seconds` and `entry_count`**: Snapshot values at submission time for historical reporting

### 1.4 Migration: Organization Settings

**File**: `/home/keven/Documents/solidtime-analysis/database/migrations/2026_03_01_000002_add_timesheet_approval_settings_to_organizations.php`

**New Columns**:
```sql
ALTER TABLE organizations ADD (
    timesheet_approval_required BOOLEAN DEFAULT FALSE,
    timesheet_reminder_enabled BOOLEAN DEFAULT FALSE,
    timesheet_reminder_day TINYINT NULL,  -- 0=Sun, 1=Mon, ...6=Sat
    timesheet_expected_hours_per_week DECIMAL(5,2) DEFAULT 40.00
);
```

**Organization Model Enhancement**:
```php
// Add to app/Models/Organization.php $casts
'timesheet_approval_required' => 'boolean',
'timesheet_reminder_enabled' => 'boolean',
'timesheet_reminder_day' => 'integer',
'timesheet_expected_hours_per_week' => 'decimal:2',
```

### 1.5 Shared Enum and Trait (Reference)

**These are created by FOUND tasks, NOT this feature**:

**`app/Enums/ApprovalStatus.php`** (SF-05):
- Values: `DRAFT`, `SUBMITTED`, `APPROVED`, `CHANGES_REQUESTED`, `REJECTED`, `WITHDRAWN`, `REOPENED`
- Timesheet Approvals uses: all except `REJECTED`

**`app/Traits/HasApprovalWorkflow.php`** (SF-05):
```php
public function isEditable(): bool;  // true if draft/changes_requested/withdrawn/reopened
public function isSubmitted(): bool;
public function isApproved(): bool;
public function changesRequested(): bool;
public function reviewer(): BelongsTo;  // Member relationship
```

---

## 2. API Contract

All endpoints follow: `Route::name('v1.timesheet-approvals.')->prefix('/organizations/{organization}')`

### 2.1 Submit Timesheet

**POST** `/api/v1/organizations/{organization}/timesheet-approvals/submit`

**Permission**: `timesheet-approvals:submit:own`  
**Middleware**: `auth:api`, `verified`, `check-organization-blocked`

**Request**:
```json
{
  "week_start": "2026-02-03",
  "week_end": "2026-02-09"
}
```

**Validation** (`TimesheetApprovalSubmitRequest`):
- `week_start`: required, date format `Y-m-d`
- `week_end`: required, date format `Y-m-d`

**Response 201 Created**:
```json
{
  "data": {
    "id": "uuid",
    "member_id": "uuid",
    "organization_id": "uuid",
    "start_date": "2026-02-03",
    "end_date": "2026-02-09",
    "status": "submitted",
    "submitted_at": "2026-02-06T14:32:00Z",
    "reviewer_id": null,
    "reviewed_at": null,
    "reviewer_comment": null,
    "total_seconds": 144000,
    "entry_count": 15,
    "created_at": "2026-02-06T14:32:00Z",
    "updated_at": "2026-02-06T14:32:00Z"
  }
}
```

**Errors**:
- `422 Unprocessable`: No entries, running entries exist, or already submitted
- `409 Conflict`: Approval already exists in non-draft status

**Business Rules Enforced**:
1. At least one completed time entry must exist in the period
2. No running (open-ended) time entries allowed
3. Cannot submit if already submitted/approved

**Side Effects**:
- Creates/updates `TimesheetApproval` record with `status = 'submitted'`
- Snapshots `total_seconds` and `entry_count`
- Sends `TimesheetSubmittedNotification` to approvers
- Audit log entry created

### 2.2 Withdraw Submission

**POST** `/api/v1/organizations/{organization}/timesheet-approvals/{timesheetApproval}/withdraw`

**Permission**: `timesheet-approvals:submit:own` (must be own timesheet)  
**Request**: Empty  
**Response 200 OK**: Returns updated approval with `status: "withdrawn"`

**Errors**:
- `403 Forbidden`: Not your timesheet or cannot withdraw from current state
- `422 Unprocessable`: Status not `submitted`

### 2.3 Approve Timesheet

**POST** `/api/v1/organizations/{organization}/timesheet-approvals/{timesheetApproval}/approve`

**Permission**: `timesheet-approvals:approve` (team scope) or `timesheet-approvals:approve:all`  
**Request**: Empty  
**Response 200 OK**: Returns approval with `status: "approved"`, `reviewer_id`, `reviewed_at`

**Errors**:
- `403 Forbidden`: Cannot approve own timesheet (SF-05 rule)
- `422 Unprocessable`: Status not `submitted`

**Side Effects**:
- Sets `status = 'approved'`, `reviewer_id`, `reviewed_at`
- Sends `TimesheetApprovedNotification` to member
- Time entries in period become **permanently locked**

### 2.4 Request Changes

**POST** `/api/v1/organizations/{organization}/timesheet-approvals/{timesheetApproval}/request-changes`

**Permission**: `timesheet-approvals:approve` or `timesheet-approvals:approve:all`

**Request**:
```json
{
  "reviewer_comment": "Please add descriptions to all Project X entries."
}
```

**Validation** (`TimesheetApprovalRequestChangesRequest`):
- `reviewer_comment`: required, string, max 2000 chars

**Response 200 OK**: Returns approval with `status: "changes_requested"`

**Side Effects**:
- Sets `status = 'changes_requested'`, `reviewer_id`, `reviewed_at`, `reviewer_comment`
- Sends `TimesheetChangesRequestedNotification` to member
- Time entries become **editable again**

### 2.5 Reopen Approved Timesheet

**POST** `/api/v1/organizations/{organization}/timesheet-approvals/{timesheetApproval}/reopen`

**Permission**: `timesheet-approvals:reopen` (admin/owner only)

**Request**:
```json
{
  "reason": "Correcting billing error for client invoice."
}
```

**Validation** (`TimesheetApprovalReopenRequest`):
- `reason`: required, string, max 2000 chars

**Response 200 OK**: Returns approval with `status: "reopened"`

**Side Effects**:
- Sets `status = 'reopened'`, `reviewer_comment = reason`
- Audit log records admin override
- Time entries become **editable**

### 2.6 List Approvals

**GET** `/api/v1/organizations/{organization}/timesheet-approvals`

**Permission**: `timesheet-approvals:view`

**Query Params** (`TimesheetApprovalIndexRequest`):
- `status`: string, comma-separated (e.g. `submitted,changes_requested`)
- `member_id`: UUID, filter by member
- `start_date_from`: date `Y-m-d`
- `start_date_to`: date `Y-m-d`
- `limit`: integer, default 20, max 100
- `offset`: integer, default 0

**Response 200 OK**:
```json
{
  "data": [
    {
      "id": "uuid",
      "member": {
        "id": "uuid",
        "user": {
          "name": "Jane Doe",
          "email": "jane@example.com",
          "profile_photo_url": "https://..."
        }
      },
      "start_date": "2026-02-03",
      "end_date": "2026-02-09",
      "status": "submitted",
      "submitted_at": "2026-02-06T14:32:00Z",
      "total_seconds": 144000,
      "entry_count": 15
    }
  ],
  "meta": {
    "total": 42,
    "limit": 20,
    "offset": 0
  }
}
```

**Team Scoping for Managers**:
- Managers see only timesheets for members on shared projects
- Admins/Owners see all organization timesheets

### 2.7 My Approval Statuses

**GET** `/api/v1/organizations/{organization}/timesheet-approvals/my`

**Permission**: `timesheet-approvals:submit:own`

**Query Params**:
- `limit`: integer, default 8 (recent weeks)

**Response 200 OK**:
```json
{
  "data": [
    {
      "week_start": "2026-02-03",
      "week_end": "2026-02-09",
      "status": "submitted",
      "approval_id": "uuid",
      "submitted_at": "2026-02-06T14:32:00Z",
      "reviewed_at": null,
      "reviewer_comment": null
    },
    {
      "week_start": "2026-01-27",
      "week_end": "2026-02-02",
      "status": null,
      "approval_id": null,
      "submitted_at": null,
      "reviewed_at": null,
      "reviewer_comment": null
    }
  ]
}
```

**Note**: `status: null` indicates no approval record (draft/never submitted).

### 2.8 Bulk Approve

**POST** `/api/v1/organizations/{organization}/timesheet-approvals/bulk-approve`

**Permission**: `timesheet-approvals:approve` or `timesheet-approvals:approve:all`

**Request**:
```json
{
  "approval_ids": ["uuid1", "uuid2", "uuid3"]
}
```

**Validation** (`TimesheetApprovalBulkApproveRequest`):
- `approval_ids`: required, array of UUIDs, max 50 items

**Response 200 OK**:
```json
{
  "data": {
    "approved": ["uuid1", "uuid2"],
    "failed": [
      {
        "id": "uuid3",
        "error": "Cannot approve: status is not submitted"
      }
    ]
  }
}
```

### 2.9 Enhanced Existing Endpoints

#### GET /timesheet/weeks (Enhanced)

**Added Response Fields**:
```json
{
  "data": [
    {
      "week_start": "2026-02-03",
      "week_end": "2026-02-09",
      "label": "This Week",
      "total_seconds": 144000,
      "approval_status": "submitted",  // NEW
      "approval_id": "uuid"            // NEW
    }
  ]
}
```

#### PUT /timesheet/cell (Enhanced)

**New Error Response**:
```json
// HTTP 423 Locked
{
  "error": true,
  "key": "timesheet_period_locked",
  "message": "Cannot edit: timesheet period is submitted or approved",
  "approval_status": "submitted"
}
```

---

## 3. Service Layer

### 3.1 TimesheetApprovalService

**File**: `/home/keven/Documents/solidtime-analysis/app/Service/TimesheetApprovalService.php`

**Pattern**: Stateless service class, all dependencies injected via method parameters

**Core Methods**:

#### submit()
```php
public function submit(
    Organization $organization,
    Member $member,
    string $startDate,
    string $endDate,
    string $timezone
): TimesheetApproval
```

**Business Logic**:
1. Check for existing approval (if exists and not editable, throw exception)
2. Validate: at least one completed entry exists (`getCompletedEntryCount()`)
3. Validate: no running entries (`getRunningEntryCount()`)
4. Calculate totals (`getTotalSeconds()`)
5. Create or update approval with `status = 'submitted'`, snapshot totals
6. Notify approvers via `notifyApprovers()`

**Exceptions**:
- `TimesheetApprovalException('timesheet_already_submitted')`
- `TimesheetApprovalException('no_time_entries_in_period')`
- `TimesheetApprovalException('running_entries_exist')`

#### approve()
```php
public function approve(
    TimesheetApproval $approval,
    Member $reviewer
): TimesheetApproval
```

**Business Logic**:
1. Validate: status is `submitted`
2. **Prevent self-approval** (SF-05 rule): `approval->member_id !== reviewer->getKey()`
3. Set `status = 'approved'`, `reviewer_id`, `reviewed_at`
4. Notify member via `TimesheetApprovedNotification`

**Exceptions**:
- `TimesheetApprovalException('can_only_approve_submitted_timesheet')`
- `TimesheetApprovalException('cannot_approve_own_timesheet')`

#### requestChanges()
```php
public function requestChanges(
    TimesheetApproval $approval,
    Member $reviewer,
    string $comment
): TimesheetApproval
```

**Business Logic**:
1. Validate: status is `submitted`
2. Sanitize comment: `strip_tags($comment)` (XSS prevention)
3. Set `status = 'changes_requested'`, `reviewer_id`, `reviewed_at`, `reviewer_comment`
4. Notify member via `TimesheetChangesRequestedNotification`

#### getBlockingApproval()
```php
public function getBlockingApproval(
    Organization $organization,
    Member $member,
    Carbon $date
): ?TimesheetApproval
```

**Business Logic**:
- Query for approval where `start_date <= date <= end_date` and status is `submitted` or `approved`
- Returns approval if locked, `null` if editable
- **Used by `TimesheetService` and `TimeEntryController`** to enforce lock

**Query**:
```php
TimesheetApproval::query()
    ->where('member_id', $member->getKey())
    ->where('organization_id', $organization->getKey())
    ->where('start_date', '<=', $date->toDateString())
    ->where('end_date', '>=', $date->toDateString())
    ->whereIn('status', [ApprovalStatus::SUBMITTED, ApprovalStatus::APPROVED])
    ->first();
```

#### getApprovalStatuses()
```php
public function getApprovalStatuses(
    Organization $organization,
    Member $member,
    array $weekPeriods
): array
```

**Purpose**: Fetch approval statuses for multiple weeks in one query  
**Returns**: `array<week_start, array{status, approval_id}>`  
**Used by**: `TimesheetService::getWeekList()` to enhance response

#### listPendingApprovals()
```php
public function listPendingApprovals(
    Organization $organization,
    ?Member $filterMember,
    ?string $statusFilter,
    ?string $startDateFrom,
    ?string $startDateTo,
    int $limit,
    int $offset
): LengthAwarePaginator
```

**Business Logic**:
- Base query: all approvals in organization
- Apply filters: member, status, date range
- Eager load: `member.user`, `reviewer.user`
- Order by: `submitted_at DESC`
- Paginate

#### bulkApprove()
```php
public function bulkApprove(
    array $approvalIds,
    Member $reviewer
): array
```

**Business Logic**:
- Iterate through approval IDs
- Try `approve()` for each
- Collect successes and failures
- Return: `['approved' => [...], 'failed' => [...]]`

**Transaction Boundaries**:
- Each state transition method (`submit`, `approve`, etc.) wraps DB changes in `DB::transaction()`

### 3.2 TimesheetService Enhancements

**File**: `/home/keven/Documents/solidtime-analysis/app/Service/TimesheetService.php` (modifications)

#### updateCell() Enhancement

**Add Parameter**: `TimesheetApprovalService $approvalService` (method injection)

**Add Before Existing Logic**:
```php
$dateCarbon = Carbon::parse($date, $timezone);

// NEW: Check for approval lock
$blockingApproval = $approvalService->getBlockingApproval($organization, $member, $dateCarbon);
if ($blockingApproval !== null) {
    throw new TimesheetPeriodLockedException($blockingApproval);
}

// ... existing logic
```

#### getWeekList() Enhancement

**Add Parameter**: `TimesheetApprovalService $approvalService`

**Add After Building Week List**:
```php
// NEW: Fetch approval statuses for all weeks
$weekPeriods = array_map(fn($w) => [
    'week_start' => $w['week_start'],
    'week_end' => $w['week_end']
], $weeks);

$approvalStatuses = $approvalService->getApprovalStatuses($organization, $member, $weekPeriods);

// NEW: Merge approval data into weeks
foreach ($weeks as &$week) {
    $status = $approvalStatuses[$week['week_start']] ?? null;
    $week['approval_status'] = $status['status'] ?? null;
    $week['approval_id'] = $status['approval_id'] ?? null;
}
```

#### getWeekGrid() Enhancement

**Add After Building Grid**:
```php
// NEW: Check if week is locked
$approval = TimesheetApproval::query()
    ->where('member_id', $member->getKey())
    ->where('start_date', $weekStart->toDateString())
    ->where('end_date', $weekEnd->toDateString())
    ->first();

$isLocked = $approval && !$approval->isEditable();

return [
    'week_start' => $weekStart->toDateString(),
    'week_end' => $weekEnd->toDateString(),
    'rows' => $rows,
    'day_totals' => $dayTotals,
    'week_total' => $weekTotal,
    'is_locked' => $isLocked,              // NEW
    'approval_status' => $approval?->status?->value,  // NEW
];
```

### 3.3 Custom Exceptions

**File**: `/home/keven/Documents/solidtime-analysis/app/Exceptions/Api/TimesheetPeriodLockedException.php`

**Pattern**: Extends `ApiException`, renders as **HTTP 423 Locked**

```php
<?php
declare(strict_types=1);

namespace App\Exceptions\Api;

use App\Models\TimesheetApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimesheetPeriodLockedException extends ApiException
{
    public const string KEY = 'timesheet_period_locked';

    public function __construct(
        private readonly TimesheetApproval $approval
    ) {
        parent::__construct();
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => true,
            'key' => $this->getKey(),
            'message' => $this->getTranslatedMessage(),
            'approval_status' => $this->approval->status->value,
        ], 423);  // Locked status
    }
}
```

**Translation Key** (add to `lang/en/exceptions.php`):
```php
'api' => [
    'timesheet_period_locked' => 'Cannot edit: timesheet period is submitted or approved',
    // ... other keys
],
```

**File**: `/home/keven/Documents/solidtime-analysis/app/Exceptions/Api/TimesheetApprovalException.php`

**Pattern**: Dynamic key based on sub-key parameter

```php
<?php
declare(strict_types=1);

namespace App\Exceptions\Api;

class TimesheetApprovalException extends ApiException
{
    public function __construct(
        private readonly string $subKey
    ) {
        parent::__construct();
    }

    public function getKey(): string
    {
        return 'timesheet_approval_' . $this->subKey;
    }
}
```

**Translation Keys**:
```php
'api' => [
    'timesheet_approval_already_submitted' => 'Timesheet for this period is already submitted',
    'timesheet_approval_no_time_entries_in_period' => 'Cannot submit: no time entries found in this period',
    'timesheet_approval_running_entries_exist' => 'Cannot submit: running time entries exist in this period',
    'timesheet_approval_cannot_approve_own_timesheet' => 'You cannot approve your own timesheet',
    'timesheet_approval_can_only_approve_submitted_timesheet' => 'Can only approve submitted timesheets',
    'timesheet_approval_can_only_request_changes_on_submitted_timesheet' => 'Can only request changes on submitted timesheets',
    'timesheet_approval_can_only_reopen_approved_timesheet' => 'Can only reopen approved timesheets',
],
```

---

## 4. Frontend Architecture

### 4.1 Pinia Store: useTimesheetApprovalStore

**File**: `/home/keven/Documents/solidtime-analysis/resources/js/utils/useTimesheetApproval.ts`

**Pattern**: Follows existing `useTimesheetStore` pattern using `@tanstack/vue-query` via `handleApiRequestNotifications`

**State**:
```typescript
const pendingApprovals = ref<TimesheetApproval[]>([]);  // For approvers
const isLoadingApprovals = ref(false);
const totalApprovals = ref(0);

const myApprovals = ref<Map<string, TimesheetApproval>>(new Map());  // For members
const isLoadingMyApprovals = ref(false);

const currentApproval = ref<TimesheetApproval | null>(null);  // Detail view
```

**Actions**:
```typescript
async submitTimesheet(weekStart: string, weekEnd: string): Promise<TimesheetApproval | null>
async withdrawTimesheet(approvalId: string): Promise<boolean>
async approveTimesheet(approvalId: string): Promise<boolean>
async requestChanges(approvalId: string, comment: string): Promise<boolean>
async reopenTimesheet(approvalId: string, reason: string): Promise<boolean>
async loadPendingApprovals(filters: {...}): Promise<void>
async loadMyApprovals(limit = 8): Promise<void>
```

**Getters**:
```typescript
function getApprovalStatus(weekStart: string): ApprovalStatus | null
function isWeekLocked(weekStart: string): boolean  // submitted or approved
function isWeekEditable(weekStart: string): boolean  // draft, changes_requested, reopened, or null
```

**Integration with API**:
- Uses `api` client from `@/packages/api/src`
- Uses `getCurrentOrganizationId()` helper
- Wraps all API calls in `handleApiRequestNotifications()` for unified error handling

**Cache Management**:
- `submitTimesheet()` updates `myApprovals` map immediately
- `approveTimesheet()` updates `pendingApprovals` array and `currentApproval`
- Triggers re-render of UI components via Vue reactivity

### 4.2 TypeScript Types

**File**: `/home/keven/Documents/solidtime-analysis/resources/js/types/timesheet-approval.d.ts`

```typescript
export type ApprovalStatus =
    | 'draft'
    | 'submitted'
    | 'approved'
    | 'changes_requested'
    | 'withdrawn'
    | 'reopened';

export interface TimesheetApproval {
    id: string;
    member_id: string;
    member?: {
        id: string;
        user: {
            name: string;
            email: string;
            profile_photo_url: string;
        };
    };
    organization_id: string;
    start_date: string;
    end_date: string;
    status: ApprovalStatus;
    submitted_at: string | null;
    reviewer_id: string | null;
    reviewer?: {
        id: string;
        user: { name: string };
    } | null;
    reviewed_at: string | null;
    reviewer_comment: string | null;
    total_seconds: number;
    entry_count: number;
    created_at: string;
    updated_at: string;
}

export interface MyApprovalStatus {
    week_start: string;
    week_end: string;
    status: ApprovalStatus | null;
    approval_id: string | null;
    submitted_at: string | null;
    reviewed_at: string | null;
    reviewer_comment: string | null;
}
```

**Update**: `/home/keven/Documents/solidtime-analysis/resources/js/types/timesheet.d.ts`

```typescript
export interface WeekSummary {
    week_start: string;
    week_end: string;
    label: string;
    total_seconds: number;
    approval_status?: ApprovalStatus | null;  // NEW
    approval_id?: string | null;              // NEW
}
```

### 4.3 Vue Page: Approvals.vue

**File**: `/home/keven/Documents/solidtime-analysis/resources/js/Pages/Approvals.vue`

**Layout**: Two-column layout (list + detail) following existing patterns in `Reporting.vue`

**Components Used**:
- `AppLayout`: Existing layout wrapper
- `TimesheetApprovalList`: New component (approval list with filters)
- `TimesheetApprovalDetail`: New component (approval detail with actions)

**State Management**:
- Uses `useTimesheetApprovalStore`
- Loads `pendingApprovals` on mount
- Tracks `selectedApprovalId` for detail view

**Actions**:
- Select approval from list → update `currentApproval`
- Approve → call `approvalStore.approveTimesheet()`
- Request Changes → show dialog, call `approvalStore.requestChanges()`
- Filter by status/member → reload approvals

### 4.4 UI Components to Create

**Directory**: `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/TimesheetApproval/`

#### 1. TimesheetApprovalList.vue

**Props**:
- `approvals`: `TimesheetApproval[]`
- `loading`: `boolean`
- `selectedId`: `string | null`
- `statusFilter`: `string`

**Emits**:
- `select(approvalId: string)`
- `filter-change(newStatus: string)`

**Template**:
- Filterable table/list of approvals
- Shows: member avatar, name, week range, total hours, status badge, submission date
- Click row → emit `select`
- Status filter dropdown → emit `filter-change`

#### 2. TimesheetApprovalDetail.vue

**Props**:
- `approval`: `TimesheetApproval`

**Emits**:
- `approve()`
- `request-changes(comment: string)`

**Template**:
- Header: Member name, week range, status badge
- Body: Read-only week grid (reuse `TimesheetGrid` component with `readonly` prop)
- Footer: Action buttons ("Approve", "Request Changes")
- Request Changes dialog with textarea (max 2000 chars)

#### 3. TimesheetApprovalStatusBadge.vue

**Props**:
- `status`: `ApprovalStatus | null`

**Template**:
- Badge component with color coding:
  - `draft` / `null`: gray "Draft"
  - `submitted`: blue "Submitted"
  - `approved`: green "Approved"
  - `changes_requested`: yellow "Changes Requested"
  - `withdrawn`: gray "Withdrawn"
  - `reopened`: orange "Reopened"

#### 4. TimesheetSubmitDialog.vue

**Props**:
- `weekStart`: `string`
- `weekEnd`: `string`
- `totalHours`: `number`
- `entryCount`: `number`

**Emits**:
- `confirm()`
- `cancel()`

**Template**:
- Confirmation dialog for submitting a week
- Shows summary: total hours, number of entries
- Warning: "You will not be able to edit entries after submission until reviewed."
- Buttons: "Submit", "Cancel"

#### 5. TimesheetRequestChangesDialog.vue

**Props**:
- `approval`: `TimesheetApproval`

**Emits**:
- `confirm(comment: string)`
- `cancel()`

**Template**:
- Dialog with textarea for reviewer comment
- Character count: `0 / 2000`
- Buttons: "Request Changes", "Cancel"

### 4.5 Enhancements to Existing Timesheet Page

**File**: `/home/keven/Documents/solidtime-analysis/resources/js/Pages/Timesheet.vue` (modifications)

**Add to `<script setup>`**:
```typescript
import { useTimesheetApprovalStore } from '@/utils/useTimesheetApproval';

const approvalStore = useTimesheetApprovalStore();

onMounted(async () => {
    await timesheetStore.loadWeekList();
    await approvalStore.loadMyApprovals();  // NEW
});

async function handleSubmitWeek(weekStart: string, weekEnd: string) {
    const result = await approvalStore.submitTimesheet(weekStart, weekEnd);
    if (result) {
        timesheetStore.loadWeekList();  // Refresh to show updated status
    }
}

async function handleWithdrawWeek(approvalId: string) {
    const result = await approvalStore.withdrawTimesheet(approvalId);
    if (result) {
        timesheetStore.loadWeekList();
    }
}
```

**File**: `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Timesheet/TimesheetWeekAccordion.vue` (modifications)

**Add Props**:
```typescript
const props = defineProps<{
    week: WeekSummary;
    approvalStatus?: ApprovalStatus | null;  // NEW
    isLocked?: boolean;                      // NEW
}>();
```

**Add Emits**:
```typescript
const emit = defineEmits<{
    submit: [weekStart: string, weekEnd: string];
    withdraw: [approvalId: string];
}>();
```

**Add to Template**:
- Status badge next to week label
- "Submit Week" button if `!isLocked && hasEntries`
- "Withdraw" button if `approvalStatus === 'submitted'`
- Disable accordion if locked

**File**: `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Timesheet/TimesheetCell.vue` (modifications)

**Add Props**:
```typescript
const props = defineProps<{
    // ... existing props
    isLocked?: boolean;  // NEW
}>();
```

**Add to Template**:
- Disable input if `isLocked`
- Add lock icon overlay on locked cells
- Tooltip: "This week is submitted/approved and cannot be edited"

---

## 5. Notification Design

All notifications extend **shared `BaseNotification`** class (from SF-04) and use Laravel's `database` + `mail` channels.

### 5.1 TimesheetSubmittedNotification

**File**: `/home/keven/Documents/solidtime-analysis/app/Notifications/TimesheetSubmittedNotification.php`

**Sent to**: Managers, admins, owners with `timesheet-approvals:approve` permission  
**Trigger**: `TimesheetApprovalService::submit()` calls `notifyApprovers()`

**Email Subject**: "Timesheet Submitted for Review"  
**Email Body**:
- "{member_name} submitted their timesheet for review."
- "Week: {start_date} - {end_date}"
- "Total hours: {hours}"
- Action button: "Review Timesheet" → `/approvals?id={approval_id}`

**Database Payload**:
```json
{
  "type": "timesheet_submitted",
  "approval_id": "uuid",
  "member_name": "Jane Doe",
  "week_start": "2026-02-03",
  "week_end": "2026-02-09",
  "total_hours": 40.0
}
```

### 5.2 TimesheetApprovedNotification

**File**: `/home/keven/Documents/solidtime-analysis/app/Notifications/TimesheetApprovedNotification.php`

**Sent to**: Member who submitted the timesheet  
**Trigger**: `TimesheetApprovalService::approve()`

**Email Subject**: "Timesheet Approved"  
**Email Body**:
- "Your timesheet has been approved by {reviewer_name}."
- "Week: {start_date} - {end_date}"
- "Your hours for this week are now finalized."
- Action button: "View Timesheet" → `/timesheet`

**Database Payload**:
```json
{
  "type": "timesheet_approved",
  "approval_id": "uuid",
  "reviewer_name": "John Manager",
  "week_start": "2026-02-03",
  "week_end": "2026-02-09"
}
```

### 5.3 TimesheetChangesRequestedNotification

**File**: `/home/keven/Documents/solidtime-analysis/app/Notifications/TimesheetChangesRequestedNotification.php`

**Sent to**: Member who submitted the timesheet  
**Trigger**: `TimesheetApprovalService::requestChanges()`

**Email Subject**: "Changes Requested on Your Timesheet"  
**Email Body**:
- "Your manager has requested changes on your timesheet."
- "Week: {start_date} - {end_date}"
- "Reason: {reviewer_comment}"
- "Please review and resubmit your timesheet after making the requested changes."
- Action button: "Edit Timesheet" → `/timesheet`

**Database Payload**:
```json
{
  "type": "timesheet_changes_requested",
  "approval_id": "uuid",
  "reviewer_name": "John Manager",
  "week_start": "2026-02-03",
  "week_end": "2026-02-09",
  "comment": "Please add descriptions to all entries for Project X."
}
```

### 5.4 TimesheetReminderNotification

**File**: `/home/keven/Documents/solidtime-analysis/app/Notifications/TimesheetReminderNotification.php`

**Sent to**: Members with missing or unsubmitted time  
**Trigger**: Laravel scheduled command `SendTimesheetRemindersCommand`

**Email Variants**:

**A. Missing Time Reminder**:
- Subject: "Reminder: Log Your Hours"
- Body: "You have logged {logged_hours} hours out of the expected {expected_hours} hours for the week of {week_start}."
- Action: "Go to Timesheet"

**B. Unsubmitted Reminder**:
- Subject: "Reminder: Submit Your Timesheet"
- Body: "You have not yet submitted your timesheet for the week of {week_start}."
- Action: "Submit Timesheet"

**Database Payload**:
```json
{
  "type": "timesheet_unsubmitted_reminder",
  "week_start": "2026-02-03",
  "week_end": "2026-02-09",
  "logged_hours": 32.5,
  "expected_hours": 40.0
}
```

### 5.5 Scheduled Command

**File**: `/home/keven/Documents/solidtime-analysis/app/Console/Commands/SendTimesheetRemindersCommand.php`

**Signature**: `timesheet:send-reminders`  
**Schedule**: Daily (registered in `app/Console/Kernel.php`)

**Logic**:
1. Get current day of week (0-6)
2. Find organizations where `timesheet_reminder_enabled = true` AND `timesheet_reminder_day = current_day`
3. For each organization:
   - Get all non-placeholder members
   - For each member:
     - Calculate previous week date range
     - Query total logged seconds
     - Check if timesheet was submitted
     - If logged hours < expected hours OR not submitted:
       - Send `TimesheetReminderNotification`

**Registration** in `app/Console/Kernel.php`:
```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('timesheet:send-reminders')->daily();
}
```

---

## 6. Permission Matrix

Permissions follow **shared convention** from SF-02: `{entity}:{action}:{scope}`

### 6.1 Permissions Defined

| Permission | Description | Scope |
|-----------|-------------|-------|
| `timesheet-approvals:view` | View the approvals queue | - |
| `timesheet-approvals:submit:own` | Submit own weekly timesheet | Own data |
| `timesheet-approvals:approve` | Approve/reject timesheets | Team scope for managers |
| `timesheet-approvals:approve:all` | Approve/reject any timesheet | Org-wide |
| `timesheet-approvals:reopen` | Reopen approved timesheets | Admin only |
| `timesheet-approvals:configure` | Configure reminder settings | Admin only |

### 6.2 Permission Assignment by Role

| Permission | Owner | Admin | Manager | Employee | Placeholder |
|-----------|-------|-------|---------|----------|-------------|
| `timesheet-approvals:view` | ✓ | ✓ | ✓ | ✗ | ✗ |
| `timesheet-approvals:submit:own` | ✓ | ✓ | ✓ | ✓ | ✗ |
| `timesheet-approvals:approve` | ✓ | ✓ | ✓ | ✗ | ✗ |
| `timesheet-approvals:approve:all` | ✓ | ✓ | ✗ | ✗ | ✗ |
| `timesheet-approvals:reopen` | ✓ | ✓ | ✗ | ✗ | ✗ |
| `timesheet-approvals:configure` | ✓ | ✓ | ✗ | ✗ | ✗ |

**Team Scope for Managers**:
- `timesheet-approvals:approve`: Managers can approve timesheets for members on **shared projects**
- Query logic: `ProjectMember` relationship filters which members a manager can review

### 6.3 Modular Permission Registration (SF-08)

**File**: `/home/keven/Documents/solidtime-analysis/app/Permissions/TimesheetApprovalPermissions.php`

```php
<?php
declare(strict_types=1);

namespace App\Permissions;

use Laravel\Jetstream\Jetstream;

class TimesheetApprovalPermissions
{
    public static function register(): void
    {
        // This method is called from JetstreamServiceProvider::configurePermissions()
        // The actual permission arrays are added to each role definition
    }
}
```

**Modified**: `/home/keven/Documents/solidtime-analysis/app/Providers/JetstreamServiceProvider.php`

**Add to `configurePermissions()` method**:
```php
// Owner role
Jetstream::role(Role::Owner->value, 'Owner', [
    // ... existing permissions ...
    'timesheet-approvals:view',
    'timesheet-approvals:submit:own',
    'timesheet-approvals:approve',
    'timesheet-approvals:approve:all',
    'timesheet-approvals:reopen',
    'timesheet-approvals:configure',
]);

// Admin role (same as Owner for this feature)
Jetstream::role(Role::Admin->value, 'Administrator', [
    // ... existing permissions ...
    'timesheet-approvals:view',
    'timesheet-approvals:submit:own',
    'timesheet-approvals:approve',
    'timesheet-approvals:approve:all',
    'timesheet-approvals:reopen',
    'timesheet-approvals:configure',
]);

// Manager role (team-scoped approve)
Jetstream::role(Role::Manager->value, 'Manager', [
    // ... existing permissions ...
    'timesheet-approvals:view',
    'timesheet-approvals:submit:own',
    'timesheet-approvals:approve',  // Team scope enforced in controller
]);

// Employee role (submit only)
Jetstream::role(Role::Employee->value, 'Employee', [
    // ... existing permissions ...
    'timesheet-approvals:submit:own',
]);
```

---

## 7. Migration Strategy

### 7.1 Dependencies

**Shared Foundations (must be implemented first)**:
- **FOUND-001 to FOUND-005**: Notification infrastructure (SF-04)
  - `notifications` table migration
  - `BaseNotification` class
  - Notification API endpoints
  - Notification bell UI component
- **FOUND-007**: Modular permissions pattern (SF-08)
  - `app/Permissions/` directory structure
- **Shared Enum/Trait** (FOUND task for SF-05):
  - `app/Enums/ApprovalStatus.php`
  - `app/Traits/HasApprovalWorkflow.php`

**Existing Infrastructure (already in `feature/weekly-timesheet-grid`)**:
- `TimesheetController`, `TimesheetService`
- `TimeEntryController`, `TimeEntryService`
- `useTimesheetStore` Pinia store
- Weekly timesheet grid UI components

### 7.2 Migration Files

All migrations use date prefix **`2026_03_01_`** (per SF-03).

**Order**:
1. `2026_03_01_000001_create_timesheet_approvals_table.php`
2. `2026_03_01_000002_add_timesheet_approval_settings_to_organizations.php`

### 7.3 Rollback Strategy

All migrations have reversible `down()` methods:
- `down()` for `2026_03_01_000001`: `Schema::dropIfExists('timesheet_approvals')`
- `down()` for `2026_03_01_000002`: `Schema::table('organizations', fn($t) => $t->dropColumn([...]))`

**Testing Rollback**:
```bash
php artisan migrate:rollback --step=2
```

### 7.4 Data Seeding

**Factory**: `/home/keven/Documents/solidtime-analysis/database/factories/TimesheetApprovalFactory.php`

**States**:
```php
public function draft(): static
{
    return $this->state(fn (array $attributes) => [
        'status' => ApprovalStatus::DRAFT,
    ]);
}

public function submitted(): static
{
    return $this->state(fn (array $attributes) => [
        'status' => ApprovalStatus::SUBMITTED,
        'submitted_at' => now(),
        'total_seconds' => 144000,  // 40 hours
        'entry_count' => 15,
    ]);
}

public function approved(): static
{
    return $this->state(fn (array $attributes) => [
        'status' => ApprovalStatus::APPROVED,
        'submitted_at' => now()->subDays(2),
        'reviewer_id' => Member::factory(),
        'reviewed_at' => now(),
        'total_seconds' => 144000,
        'entry_count' => 15,
    ]);
}

public function changesRequested(): static
{
    return $this->state(fn (array $attributes) => [
        'status' => ApprovalStatus::CHANGES_REQUESTED,
        'submitted_at' => now()->subDays(1),
        'reviewer_id' => Member::factory(),
        'reviewed_at' => now(),
        'reviewer_comment' => 'Please add descriptions to all entries.',
        'total_seconds' => 144000,
        'entry_count' => 15,
    ]);
}
```

**Usage in Tests**:
```php
$approval = TimesheetApproval::factory()
    ->for($member)
    ->for($organization)
    ->submitted()
    ->create();
```

---

## 8. Integration Points

### 8.1 Existing Timesheet Grid

**Files Modified**:
- `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/TimesheetController.php`
- `/home/keven/Documents/solidtime-analysis/app/Service/TimesheetService.php`
- `/home/keven/Documents/solidtime-analysis/resources/js/utils/useTimesheet.ts`
- `/home/keven/Documents/solidtime-analysis/resources/js/Pages/Timesheet.vue`
- `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Timesheet/TimesheetWeekAccordion.vue`
- `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Timesheet/TimesheetCell.vue`

**Integration Flow**:
1. User opens Timesheet page
2. `TimesheetController::weeks()` now includes `approval_status` and `approval_id` in response
3. `useTimesheetStore.loadWeekList()` stores approval data
4. `useTimesheetApprovalStore.loadMyApprovals()` fetches detailed approval statuses
5. Week accordion headers show status badges via `TimesheetApprovalStatusBadge`
6. "Submit Week" button appears for editable weeks with completed entries
7. Cell editing is disabled via `:disabled="props.isLocked"` in `TimesheetCell.vue`
8. On submit, `TimesheetApprovalService::submit()` is called, grid refreshes

**Backward Compatibility**:
- Existing timesheet grid works without approvals (all weeks are editable)
- `approval_status: null` indicates no approval record exists
- No breaking changes to existing API responses (only additions)

### 8.2 Time Entry Direct Editing (Time Entries Page)

**Files Modified**:
- `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/TimeEntryController.php`

**Methods Enhanced**:
- `store()`: Check lock before creating entry
- `update()`: Check lock on both old and new dates (if date changes)
- `updateMultiple()`: Check lock for all affected entries
- `destroy()`: Check lock before deleting
- `destroyMultiple()`: Check lock for all entries

**Integration Logic** (example for `store()`):
```php
public function store(
    Organization $organization,
    TimeEntryStoreRequest $request,
    TimesheetApprovalService $approvalService  // NEW INJECTION
): JsonResponse {
    $this->checkPermission($organization, 'time-entries:create:own');

    $member = $this->member($organization);
    $entryStart = Carbon::parse($request->input('start'));

    // NEW: Check for lock
    $blockingApproval = $approvalService->getBlockingApproval($organization, $member, $entryStart);
    if ($blockingApproval !== null) {
        throw new TimesheetPeriodLockedException($blockingApproval);
    }

    // ... existing creation logic
}
```

**Error Handling**:
- Frontend catches `423 Locked` response
- Displays error message: "Cannot edit: timesheet period is submitted or approved"
- User must withdraw or wait for changes to be requested

### 8.3 Navigation Sidebar

**File Modified**: `/home/keven/Documents/solidtime-analysis/resources/js/Layouts/AppLayout.vue`

**Add Navigation Item**:
```vue
<NavigationSidebarItem
    :href="route('approvals')"
    :active="route().current('approvals')"
    v-if="can('timesheet-approvals:view')"
>
    <ClipboardDocumentCheckIcon class="w-5 h-5" />
    <span>Approvals</span>
</NavigationSidebarItem>
```

**Icon**: `@heroicons/vue/20/solid` — `ClipboardDocumentCheckIcon`

**Visibility**: Only shown if user has `timesheet-approvals:view` permission (managers, admins, owners)

### 8.4 Organization Settings Page

**File Modified**: `/home/keven/Documents/solidtime-analysis/resources/js/Pages/OrganizationSettings.vue` (or equivalent)

**Add Section**: "Timesheet Approval Settings"

**Fields**:
- **Enable Timesheet Approval Workflow**: `timesheet_approval_required` (boolean toggle)
- **Enable Automatic Reminders**: `timesheet_reminder_enabled` (boolean toggle)
- **Reminder Day**: `timesheet_reminder_day` (dropdown: Sunday, Monday, ... Saturday)
- **Expected Hours per Week**: `timesheet_expected_hours_per_week` (number input, default 40)

**Permission**: Only editable by users with `timesheet-approvals:configure` (admins, owners)

### 8.5 Dashboard Widget (Future P2)

**File**: `/home/keven/Documents/solidtime-analysis/resources/js/Pages/Dashboard.vue` (enhancement)

**Widget**: "Timesheet Compliance"

**Data Displayed**:
- Percentage of members who submitted timesheets for previous week
- Number of pending approvals
- Average approval turnaround time (days)

**API Endpoint** (future):
- `GET /api/v1/organizations/{organization}/charts/timesheet-compliance`

---

## 9. File Manifest

### 9.1 Backend Files to Create

**Migrations**:
- `/home/keven/Documents/solidtime-analysis/database/migrations/2026_03_01_000001_create_timesheet_approvals_table.php`
- `/home/keven/Documents/solidtime-analysis/database/migrations/2026_03_01_000002_add_timesheet_approval_settings_to_organizations.php`

**Models**:
- `/home/keven/Documents/solidtime-analysis/app/Models/TimesheetApproval.php`
- `/home/keven/Documents/solidtime-analysis/database/factories/TimesheetApprovalFactory.php`

**Services**:
- `/home/keven/Documents/solidtime-analysis/app/Service/TimesheetApprovalService.php`

**Controllers**:
- `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/TimesheetApprovalController.php`

**Request Validation**:
- `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/TimesheetApproval/TimesheetApprovalSubmitRequest.php`
- `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/TimesheetApproval/TimesheetApprovalRequestChangesRequest.php`
- `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/TimesheetApproval/TimesheetApprovalReopenRequest.php`
- `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/TimesheetApproval/TimesheetApprovalIndexRequest.php`
- `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/TimesheetApproval/TimesheetApprovalBulkApproveRequest.php`

**Exceptions**:
- `/home/keven/Documents/solidtime-analysis/app/Exceptions/Api/TimesheetPeriodLockedException.php`
- `/home/keven/Documents/solidtime-analysis/app/Exceptions/Api/TimesheetApprovalException.php`

**Notifications**:
- `/home/keven/Documents/solidtime-analysis/app/Notifications/TimesheetSubmittedNotification.php`
- `/home/keven/Documents/solidtime-analysis/app/Notifications/TimesheetApprovedNotification.php`
- `/home/keven/Documents/solidtime-analysis/app/Notifications/TimesheetChangesRequestedNotification.php`
- `/home/keven/Documents/solidtime-analysis/app/Notifications/TimesheetReminderNotification.php`

**Console Commands**:
- `/home/keven/Documents/solidtime-analysis/app/Console/Commands/SendTimesheetRemindersCommand.php`

**Permissions**:
- `/home/keven/Documents/solidtime-analysis/app/Permissions/TimesheetApprovalPermissions.php`

**Tests (API)**:
- `/home/keven/Documents/solidtime-analysis/tests/Unit/Endpoint/Api/V1/TimesheetApprovalEndpointTest.php`

**Tests (Service)**:
- `/home/keven/Documents/solidtime-analysis/tests/Unit/Service/TimesheetApprovalServiceTest.php`

**Tests (E2E)**:
- `/home/keven/Documents/solidtime-analysis/e2e/timesheet-approvals.spec.ts`

### 9.2 Backend Files to Modify

- `/home/keven/Documents/solidtime-analysis/app/Service/TimesheetService.php`: Add lock checks, approval status
- `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/TimesheetController.php`: Inject `TimesheetApprovalService`
- `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/TimeEntryController.php`: Add lock checks
- `/home/keven/Documents/solidtime-analysis/app/Models/Organization.php`: Add `$casts` for settings
- `/home/keven/Documents/solidtime-analysis/app/Providers/JetstreamServiceProvider.php`: Register permissions
- `/home/keven/Documents/solidtime-analysis/routes/api.php`: Add approval routes
- `/home/keven/Documents/solidtime-analysis/routes/web.php`: Add Approvals page route
- `/home/keven/Documents/solidtime-analysis/app/Console/Kernel.php`: Schedule reminder command
- `/home/keven/Documents/solidtime-analysis/lang/en/exceptions.php`: Add translation keys

### 9.3 Frontend Files to Create

**Pinia Stores**:
- `/home/keven/Documents/solidtime-analysis/resources/js/utils/useTimesheetApproval.ts`

**Type Definitions**:
- `/home/keven/Documents/solidtime-analysis/resources/js/types/timesheet-approval.d.ts`

**Vue Pages**:
- `/home/keven/Documents/solidtime-analysis/resources/js/Pages/Approvals.vue`

**UI Components**:
- `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/TimesheetApproval/TimesheetApprovalList.vue`
- `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/TimesheetApproval/TimesheetApprovalDetail.vue`
- `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/TimesheetApproval/TimesheetApprovalFilters.vue`
- `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/TimesheetApproval/TimesheetApprovalStatusBadge.vue`
- `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/TimesheetApproval/TimesheetSubmitDialog.vue`
- `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/TimesheetApproval/TimesheetRequestChangesDialog.vue`
- `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/TimesheetApproval/TimesheetReopenDialog.vue`

**Component Tests**:
- `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/TimesheetApproval/__tests__/TimesheetApprovalList.test.ts`
- `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/TimesheetApproval/__tests__/TimesheetApprovalDetail.test.ts`
- `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/TimesheetApproval/__tests__/TimesheetApprovalStatusBadge.test.ts`

### 9.4 Frontend Files to Modify

- `/home/keven/Documents/solidtime-analysis/resources/js/Pages/Timesheet.vue`: Add submit/withdraw functionality
- `/home/keven/Documents/solidtime-analysis/resources/js/utils/useTimesheet.ts`: Integrate with approval store
- `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Timesheet/TimesheetWeekAccordion.vue`: Add status badges, action buttons
- `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Timesheet/TimesheetCell.vue`: Disable when locked
- `/home/keven/Documents/solidtime-analysis/resources/js/types/timesheet.d.ts`: Add `approval_status` and `approval_id` to `WeekSummary`
- `/home/keven/Documents/solidtime-analysis/resources/js/Layouts/AppLayout.vue`: Add "Approvals" nav item

### 9.5 Shared Foundation Files (Reference Only)

**These are created by FOUND tasks, NOT by this feature**:
- `app/Enums/ApprovalStatus.php` (FOUND task for SF-05)
- `app/Traits/HasApprovalWorkflow.php` (FOUND task for SF-05)
- `app/Notifications/BaseNotification.php` (FOUND-002 from SF-04)
- `database/migrations/{timestamp}_create_notifications_table.php` (FOUND-001 from SF-04)
- `app/Permissions/` directory (FOUND-007 from SF-08)

---

## Implementation Notes

### Critical Path

**Backend** (sequential):
1. FOUND tasks (shared infrastructure)
2. Migrations + Model + Factory (APPR-001, APPR-002, APPR-003, APPR-004)
3. Permissions (APPR-005)
4. Service layer (APPR-006, APPR-007, APPR-008)
5. Controllers + Routes + Validation (APPR-009, APPR-010, APPR-011)
6. Notifications (APPR-012, APPR-013)

**Frontend** (parallel after backend APIs ready):
1. Pinia store + types (APPR-014, APPR-015)
2. UI components (APPR-016, APPR-017, APPR-018, APPR-019)
3. Approvals page (APPR-020)
4. Timesheet page enhancements (APPR-021, APPR-022)

**Testing**:
- Backend tests (APPR-023, APPR-024)
- Frontend tests (APPR-025, APPR-026)
- E2E tests (APPR-027)

### Code Patterns to Follow

**Controllers**:
- Extend `App\Http\Controllers\Api\V1\Controller`
- Inject `PermissionStore` via parent constructor
- Use `$this->checkPermission($organization, 'permission-name')`
- Use `$this->user()` and `$this->member($organization)` helpers
- Service injection via method parameters

**Services**:
- Stateless classes (no instance properties)
- All dependencies via method parameters or constructor
- Wrap state changes in `DB::transaction()`
- Throw domain-specific exceptions (extend `ApiException`)

**Models**:
- Use `CustomAuditable`, `HasUuids`, `HasFactory` traits
- Define `$casts` for enums, dates
- Scope methods for common queries
- Type-hinted relationships

**Frontend**:
- Pinia stores use `defineStore` with composition API
- API calls wrapped in `handleApiRequestNotifications()`
- Use existing `api` client from `@/packages/api/src`
- Components follow Vue 3 `<script setup>` pattern
- TypeScript strict mode enabled

---

## Summary

This architecture document provides a **complete, actionable blueprint** for implementing Timesheet Approvals. It:

✓ **Follows existing patterns**: Service-oriented MVC, `CustomAuditable` + `HasUuids`, `checkPermission()` in controllers, Pinia stores with `@tanstack/vue-query`  
✓ **Uses shared infrastructure**: `ApprovalStatus` enum, `HasApprovalWorkflow` trait, `BaseNotification`, modular permissions (SF-02, SF-04, SF-05, SF-08)  
✓ **Integrates seamlessly**: Enhances `TimesheetService`, `TimesheetController`, and existing grid UI without breaking changes  
✓ **Complete file manifest**: Every file to create or modify listed with absolute paths  
✓ **Clear data flow**: State machine, locking rules, notification triggers, permission checks fully specified  
✓ **Ready for implementation**: Code snippets as templates, maps directly to PRD tasks

**Next Steps**:
1. Implement FOUND-001 through FOUND-007 (shared foundations)
2. Follow PRD Section 6 task breakdown, referencing this architecture for implementation details
3. Use code examples as templates, adapting exact syntax to match Solidtime conventions during development