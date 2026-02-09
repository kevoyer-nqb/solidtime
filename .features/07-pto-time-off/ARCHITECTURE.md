Since I cannot use Bash to create the file, I'll provide the complete architecture document as my response. The document is comprehensive and ready to be saved to `/home/keven/Documents/solidtime-analysis/.features/07-pto-time-off/ARCHITECTURE.md`.

---

# Feature 07: PTO & Time Off — Technical Architecture

**Generated**: 2026-02-06  
**Status**: Ready for Implementation  
**Feature Branch**: `feature/07-pto-time-off`  
**PRD Reference**: `.features/07-pto-time-off/PRD.md`  
**Shared Foundations**: `.features/SHARED-FOUNDATIONS.md`

---

## Architecture Summary

This architecture document provides a complete, actionable blueprint for implementing Feature 07: PTO & Time Off in Solidtime. All patterns are derived from the existing codebase (`Member`, `TimeEntry`, `Project` models; `TimesheetService`, `ProjectController`; and Vue 3 + Pinia patterns).

### Key Architectural Decisions

1. **Inline Approval Status**: Time-off requests use the shared `ApprovalStatus` enum (SF-05) with statuses: `SUBMITTED`, `APPROVED`, `REJECTED`, `WITHDRAWN`. The `HasApprovalWorkflow` trait provides common approval behavior.

2. **Balance Ledger Pattern**: Balances are calculated as: `available = accrued + carryover + manual_adjustment - used - pending`. Each component is stored explicitly for audit trail clarity.

3. **Scheduled Accrual Engine**: A monthly scheduled command (`AccrueTimeOffBalancesCommand`) processes accruals for all active policies. Idempotency is ensured via `last_accrual_date` checks.

4. **Holiday Calendar Integration**: Hours calculation for requests excludes weekends and organization-wide holidays (stored in `holidays` table). Recurring holidays are expanded dynamically.

5. **Shared Notification Infrastructure**: Uses SF-04 `BaseNotification` pattern. Three notification types: `TimeOffRequestSubmittedNotification`, `TimeOffRequestApprovedNotification`, `TimeOffRequestDeniedNotification`.

6. **Cross-Feature Integration**: 
   - Feature 08 (Scheduling): `TimeOffRequest::getApprovedDaysInRange()` method exposes approved PTO days for capacity calculations
   - Feature 10 (Teams): `team_ids` filter parameter on request list endpoint

---

## Data Model

### Models Created

**Four New Models** (all extend `Model`, use `HasUuids`, `CustomAuditable`):

1. **TimeOffPolicy**: Defines leave types (vacation, sick, personal, etc.) with accrual rules
   - Key fields: `name`, `type` (enum), `accrual_frequency`, `accrual_amount`, `max_balance`, `max_carryover`, `requires_approval`, `is_active`
   - Relationships: `belongsTo(Organization)`, `hasMany(TimeOffRequest)`, `hasMany(TimeOffBalance)`

2. **TimeOffRequest**: A member's time-off request with approval workflow
   - Key fields: `start_date`, `end_date`, `hours_requested`, `status` (ApprovalStatus enum), `reviewer_id`, `reviewed_at`, `submitted_at`, `notes`, `reviewer_comment`
   - Uses `HasApprovalWorkflow` trait (SF-05)
   - Relationships: `belongsTo(Member)`, `belongsTo(TimeOffPolicy)`, `belongsTo(Member, 'reviewer_id')`
   - Special method: `getApprovedDaysInRange(Carbon $start, Carbon $end): float` for Feature 08 integration

3. **TimeOffBalance**: Per-member/policy/year balance ledger
   - Key fields: `year`, `accrued_hours`, `used_hours`, `pending_hours`, `carryover_hours`, `manual_adjustment`, `last_accrual_date`
   - Computed property: `available_hours` (accrued + carryover + manual - used - pending)
   - Unique constraint: `(member_id, policy_id, year)`

4. **Holiday**: Organization-wide non-working days
   - Key fields: `name`, `date`, `is_recurring`
   - Recurring holidays expand dynamically for each year in query range

### Enums Created

1. **ApprovalStatus** (Shared — SF-05): `DRAFT`, `SUBMITTED`, `APPROVED`, `CHANGES_REQUESTED`, `REJECTED`, `WITHDRAWN` (PTO uses only: SUBMITTED, APPROVED, REJECTED, WITHDRAWN)
2. **TimeOffType**: `VACATION`, `SICK`, `PERSONAL`, `BEREAVEMENT`, `PARENTAL`, `UNPAID`, `OTHER`
3. **AccrualFrequency**: `NONE`, `MONTHLY`, `BIWEEKLY`, `WEEKLY`, `ANNUALLY`, `PER_HOUR_WORKED`

### Traits Created

**HasApprovalWorkflow** (Shared — SF-05): Provides `isEditable()`, `isSubmitted()`, `isApproved()`, `isRejected()`, `isWithdrawn()`, `reviewer()` relationship

---

## Service Layer

### Services Created

1. **TimeOffService**: Request lifecycle management
   - `calculateRequestedHours()`: Working days * daily_expected_hours (excludes weekends + holidays)
   - `createRequest()`: Validate + create request, auto-approve if `requires_approval = false`, send notifications
   - `approveRequest()`: Prevent self-approval, move pending → used hours, send notification
   - `denyRequest()`: Release pending hours, send notification
   - `withdrawRequest()`: Employee-initiated cancel, release pending hours

2. **AccrualService**: Automated balance accruals
   - `processMonthlyAccruals()`: Iterate all active policies with accrual rules, credit eligible members
   - `calculateAccrualAmount()`: Handle frequency types (monthly, annually, per_hour_worked)
   - Idempotency: Check `last_accrual_date` to prevent double-crediting
   - Enforces: `max_balance` cap, `waiting_period_days`, skips Placeholder members

3. **TimeOffBalanceService**: Balance queries and carryover
   - `getOrCreateBalance()`: Lazy balance record creation
   - `processYearEndCarryover()`: Run on Jan 1, transfer up to `max_carryover` hours from previous year to new year balance
   - `adjustBalance()`: Manual admin adjustment with audit log

4. **HolidayService**: Holiday lookups
   - `getHolidaysInRange()`: Get holidays in date range, expand recurring holidays for each year, skip Feb 29 in non-leap years

---

## API Contract

### Endpoints Created

**Time Off Policies**:
- `GET /api/v1/organizations/{org}/time-off-policies` (List)
- `POST /api/v1/organizations/{org}/time-off-policies` (Create, Admin+)
- `PUT /api/v1/organizations/{org}/time-off-policies/{id}` (Update, Admin+)
- `DELETE /api/v1/organizations/{org}/time-off-policies/{id}` (Delete, Admin+)

**Holidays**:
- `GET /api/v1/organizations/{org}/holidays?year=2026` (List)
- `POST /api/v1/organizations/{org}/holidays` (Create, Admin+)
- `PUT /api/v1/organizations/{org}/holidays/{id}` (Update, Admin+)
- `DELETE /api/v1/organizations/{org}/holidays/{id}` (Delete)

**Time Off Requests**:
- `GET /api/v1/organizations/{org}/time-off-requests?member_id=&status=&team_ids=` (List own or all)
- `POST /api/v1/organizations/{org}/time-off-requests` (Create request)
- `POST /api/v1/organizations/{org}/time-off-requests/{id}/withdraw` (Withdraw own request)
- `POST /api/v1/organizations/{org}/time-off-requests/{id}/approve` (Approve, Manager+)
- `POST /api/v1/organizations/{org}/time-off-requests/{id}/deny` (Deny, Manager+)

**Balances**:
- `GET /api/v1/organizations/{org}/time-off-balances/me?year=2026` (Own balances)
- `GET /api/v1/organizations/{org}/time-off-balances?member_id=&policy_id=&year=` (All balances, Manager+)
- `POST /api/v1/organizations/{org}/time-off-balances/{id}/adjust` (Manual adjustment, Admin+)

### Controllers Created

1. **TimeOffPolicyController** (extends `App\Http\Controllers\Api\V1\Controller`)
   - Uses `$this->checkPermission()` for auth
   - CRUD methods: `index()`, `store()`, `update()`, `destroy()`
   - Delete: Returns 409 if policy has existing balances or requests

2. **HolidayController**
   - CRUD methods
   - List accepts `year` query parameter

3. **TimeOffRequestController**
   - `index()`: Filter by member_id, status, date range, team_ids (Feature 10 integration)
   - `store()`: Calls `TimeOffService::createRequest()`
   - `withdraw()`: Calls `TimeOffService::withdrawRequest()`
   - `approve()`: Calls `TimeOffService::approveRequest()`, prevents self-approval
   - `deny()`: Calls `TimeOffService::denyRequest()`

4. **TimeOffBalanceController**
   - `me()`: Current member's balances
   - `index()`: All balances (Manager+)
   - `adjust()`: Manual balance adjustment (Admin+), logs to audit trail

### Request Validation

**10 Request Validation Classes** (extend `BaseFormRequest`, use `ExistsEloquent` from `korridor/laravel-model-validation-rules`):

- `TimeOffPolicyStoreRequest`, `TimeOffPolicyUpdateRequest`
- `HolidayStoreRequest`, `HolidayUpdateRequest`, `HolidayIndexRequest`
- `TimeOffRequestStoreRequest`, `TimeOffRequestApproveRequest`, `TimeOffRequestDenyRequest`
- `TimeOffBalanceAdjustRequest`, `TimeOffBalanceIndexRequest`

**Validation Rules** (examples):
- Policy name: unique per organization
- Accrual amount: required if frequency != 'none'
- Request dates: `start_date <= end_date`, future dates (unless `allow_retroactive = true`)
- Policy/member existence: `ExistsEloquent` with organization scope

### Resources

**4 Resource Classes** (extend `BaseResource`):

- `TimeOffPolicyResource`: Transform policy with all accrual settings
- `HolidayResource`: Transform holiday with `formatDate()`
- `TimeOffRequestResource`: Include member name, policy name, reviewer name
- `TimeOffBalanceResource`: Include computed `available_hours`, policy details

---

## Approval Workflow

### Status Lifecycle

```
SUBMITTED → APPROVED (deduct used_hours from balance)
         ↘ REJECTED (release pending_hours)
         ↘ WITHDRAWN (employee cancel, release pending_hours)
```

### Business Rules

1. **Self-Approval Prevention**: `reviewer_id !== member_id` (enforced in `TimeOffService::approveRequest()` and `denyRequest()`)
2. **Status Locking**: Only SUBMITTED requests can be approved/denied/withdrawn
3. **Balance Synchronization**:
   - Create request: `pending_hours += hours_requested`
   - Approve: `pending_hours -= hours_requested`, `used_hours += hours_requested`
   - Deny/Withdraw: `pending_hours -= hours_requested`
4. **Auto-Approval**: If `policy->requires_approval = false`, request is immediately APPROVED

### Permission Matrix

| Action | Owner | Admin | Manager | Employee |
|--------|-------|-------|---------|----------|
| Create Request | Yes | Yes | Yes | Yes |
| Withdraw Own Request | Yes | Yes | Yes | Yes |
| Approve/Deny Request | Yes | Yes | Yes | No |
| View All Requests | Yes | Yes | Yes | No |

---

## Accrual Engine

### Scheduled Commands

1. **AccrueTimeOffBalancesCommand**
   - Signature: `time-off:accrue-balances {--date= : Reference date (Y-m-d)}`
   - Schedule: `monthlyOn(1, '01:00')` (1st of each month at 1 AM)
   - Config: `config('scheduling.tasks.time_off_accrue_balances')`
   - Process: Iterate all active policies with `accrual_frequency != NONE`, credit eligible members, check `last_accrual_date` for idempotency

2. **ProcessYearEndCarryoverCommand**
   - Signature: `time-off:year-end-carryover {--year= : Year to process}`
   - Schedule: `yearlyOn(1, 1, '02:00')` (Jan 1 at 2 AM)
   - Config: `config('scheduling.tasks.time_off_year_end_carryover')`
   - Process: Transfer `available_hours` (up to `max_carryover` cap) from previous year balance to new year balance's `carryover_hours`

### Accrual Logic

**Frequency Handling**:
- `MONTHLY`: Credit `accrual_amount` hours
- `ANNUALLY`: Credit `accrual_amount / 12` hours per month
- `WEEKLY`: Credit `accrual_amount * 4` hours per month (approximate)
- `BIWEEKLY`: Credit `accrual_amount * 2` hours per month
- `PER_HOUR_WORKED`: Sum previous month's TimeEntry hours * `accrual_amount`

**Constraints**:
- `max_balance`: If `accrued_hours + new_accrual > max_balance`, cap at `max_balance` (excess forfeited)
- `waiting_period_days`: New members must wait N days from `member->created_at` before accruing
- Placeholder members: Skipped (filter out `role = 'placeholder'`)

**Idempotency**: Check `last_accrual_date->isSameMonth($referenceDate)` before crediting

---

## Notification Design

### Notification Classes

All extend `App\Notifications\BaseNotification` (SF-04), use `['database', 'mail']` channels.

1. **TimeOffRequestSubmittedNotification**
   - Recipients: All members with `time-off-requests:approve` permission in the organization
   - Trigger: When request is created with `requires_approval = true`
   - Content: Member name, policy name, dates, hours, link to Time Off page

2. **TimeOffRequestApprovedNotification**
   - Recipient: Requester
   - Trigger: Request approved
   - Content: Policy name, dates, hours, reviewer name, link to Time Off page

3. **TimeOffRequestDeniedNotification**
   - Recipient: Requester
   - Trigger: Request denied
   - Content: Policy name, dates, hours, reviewer name, reviewer comment (if present), link to Time Off page

### Notification Flow

```
Request Created → TimeOffService::createRequest()
                ↓
              foreach(approvers) → notify(TimeOffRequestSubmittedNotification)
                ↓
              Notification Bell UI updates (unread count +1)
                ↓
              Approver clicks "Approve" → TimeOffService::approveRequest()
                ↓
              Requester.notify(TimeOffRequestApprovedNotification)
                ↓
              Requester's Notification Bell updates
```

Notifications respect `member->notification_preferences` (SF-04).

---

## Frontend Architecture

### Pages

1. **TimeOff.vue** (Main employee view)
   - Route: `/organizations/{organization}/time-off`
   - Permission: `time-off-requests:view:own`
   - Sections:
     - Balance Cards (one per active policy, shows accrued/used/pending/available)
     - "Request Time Off" button → opens TimeOffRequestForm modal
     - My Requests table (own requests with Withdraw action)
     - Pending Approvals (Manager+ only, with Approve/Deny actions)
     - Holiday Calendar (read-only monthly view)

2. **TimeOffSettings.vue** (Admin settings)
   - Route: `/organizations/{organization}/settings/time-off`
   - Permission: `time-off-policies:view`
   - Tabs:
     - Policies: List policies with Create/Edit/Archive/Delete actions
     - Holidays: List holidays with Add/Edit/Delete actions

### UI Components

1. **TimeOffBalanceCard.vue**: Displays balance for one policy, color-coded border, shows breakdown (accrued, used, pending, carryover)
2. **TimeOffRequestForm.vue**: Modal with policy selector, date range picker, auto-calculated hours preview, notes textarea
3. **TimeOffRequestList.vue**: Table with columns (Date Range, Policy, Hours, Status, Actions), expandable rows for notes/comments
4. **HolidayCalendar.vue**: Monthly calendar view (consider `@fullcalendar/vue3`), holidays marked with dots
5. **TimeOffPolicyList.vue**: Admin table of policies with inline edit/archive
6. **TimeOffPolicyForm.vue**: Modal for creating/editing policies (all accrual settings)
7. **HolidayList.vue**: Admin table of holidays
8. **HolidayForm.vue**: Modal for adding/editing holidays

### Pinia Store

**useTimeOffStore** (`resources/js/utils/useTimeOff.ts`):

**State**:
- `balances: TimeOffBalance[]`
- `myRequests: TimeOffRequest[]`
- `pendingRequests: TimeOffRequest[]`
- `policies: TimeOffPolicy[]`
- `holidays: Holiday[]`
- `isLoading: boolean`

**Actions**:
- `loadBalances(year?: number)`: Fetch own balances
- `loadMyRequests()`: Fetch own requests
- `loadPendingRequests()`: Fetch pending requests (Manager+)
- `createRequest(data)`: Submit new request, refresh balances and requests
- `withdrawRequest(requestId)`: Withdraw request, refresh
- `approveRequest(requestId, comment?)`: Approve, refresh pending
- `denyRequest(requestId, comment?)`: Deny, refresh pending

Uses `@tanstack/vue-query` for data fetching, `getCurrentOrganizationId()` for org context, `useNotificationsStore().handleApiRequestNotifications()` for error handling.

### Navigation

Add to `AppLayout.vue`:

```vue
<NavigationSidebarItem
    v-if="canViewTimeOff()"
    :href="route('time-off')"
    :active="route().current('time-off')"
    data-testid="navigation_time_off">
    <CalendarDaysIcon class="w-5 h-5"></CalendarDaysIcon>
    Time Off
</NavigationSidebarItem>
```

Add to `permissions.ts`:

```typescript
export function canViewTimeOff(): boolean {
  return hasPermission('time-off-requests:view:own');
}

export function canApproveTimeOffRequests(): boolean {
  return hasPermission('time-off-requests:approve');
}
```

---

## Cross-Feature Integration

### Feature 08: Resource Scheduling

**Integration Point**: Approved time-off days reduce member capacity.

**Method Exposed**: `TimeOffRequest::getApprovedDaysInRange(Carbon $start, Carbon $end): float`

**Example Usage** (in Feature 08's `SchedulingCapacityService`):

```php
public function getMemberCapacity(Member $member, Carbon $start, Carbon $end): float
{
    $baseCapacity = $this->calculateBaseCapacity($member, $start, $end);
    
    $approvedPTODays = TimeOffRequest::query()
        ->where('member_id', $member->id)
        ->where('status', ApprovalStatus::APPROVED)
        ->where(function ($query) use ($start, $end) {
            $query->whereBetween('start_date', [$start, $end])
                ->orWhereBetween('end_date', [$start, $end])
                ->orWhere(function ($q) use ($start, $end) {
                    $q->where('start_date', '<=', $start)
                      ->where('end_date', '>=', $end);
                });
        })
        ->get()
        ->sum(fn ($request) => $request->getApprovedDaysInRange($start, $end));
    
    return max(0, $baseCapacity - ($approvedPTODays * 8)); // 8 hours/day
}
```

### Feature 10: Teams & Groups

**Integration Point**: Filter requests by team membership.

**Implementation**: Add `team_ids` query parameter to `TimeOffRequestController::index()`:

```php
if ($request->has('team_ids')) {
    $teamIds = $request->input('team_ids');
    $query->whereHas('member.teams', function ($q) use ($teamIds) {
        $q->whereIn('teams.id', $teamIds);
    });
}
```

This is a **soft dependency** — PTO works standalone, gains team filtering when Feature 10 is enabled.

---

## Migration Strategy

### Migration Files (Date Prefix: `2026_03_07_`)

1. **2026_03_07_000001_create_time_off_policies_table.php**
   - Table: `time_off_policies`
   - Columns: `id` (uuid), `organization_id` (FK), `name`, `type`, `color`, `is_paid`, `requires_approval`, `is_active`, `allow_retroactive`, `accrual_frequency`, `accrual_amount`, `max_balance`, `max_carryover`, `waiting_period_days`, `default_allowance`, `daily_expected_hours`, `created_at`, `updated_at`
   - Unique: `(organization_id, name)`

2. **2026_03_07_000002_create_holidays_table.php**
   - Table: `holidays`
   - Columns: `id` (uuid), `organization_id` (FK), `name`, `date`, `is_recurring`, `created_at`, `updated_at`
   - Unique: `(organization_id, date)`

3. **2026_03_07_000003_create_time_off_balances_table.php**
   - Table: `time_off_balances`
   - Columns: `id` (uuid), `member_id` (FK), `organization_id` (FK), `policy_id` (FK), `year`, `accrued_hours`, `used_hours`, `pending_hours`, `carryover_hours`, `manual_adjustment`, `last_accrual_date`, `created_at`, `updated_at`
   - Unique: `(member_id, policy_id, year)`

4. **2026_03_07_000004_create_time_off_requests_table.php**
   - Table: `time_off_requests`
   - Columns: `id` (uuid), `member_id` (FK), `organization_id` (FK), `policy_id` (FK), `start_date`, `end_date`, `hours_requested`, `status`, `reviewer_id` (FK nullable), `reviewed_at`, `submitted_at`, `notes`, `reviewer_comment`, `created_at`, `updated_at`
   - Indexes: `member_id`, `organization_id`, `policy_id`, `status`, `(start_date, end_date)`

### Seeders

**TimeOffPolicySeeder**: Seeds one default vacation policy per organization (optional, for demo).

---

## File Manifest

### Backend Files to Create (~45 files)

**Migrations** (4):
- `database/migrations/2026_03_07_000001_create_time_off_policies_table.php`
- `database/migrations/2026_03_07_000002_create_holidays_table.php`
- `database/migrations/2026_03_07_000003_create_time_off_balances_table.php`
- `database/migrations/2026_03_07_000004_create_time_off_requests_table.php`

**Enums** (3):
- `app/Enums/ApprovalStatus.php` (shared, create if not exists)
- `app/Enums/TimeOffType.php`
- `app/Enums/AccrualFrequency.php`

**Traits** (1):
- `app/Traits/HasApprovalWorkflow.php` (shared)

**Models** (4):
- `app/Models/TimeOffPolicy.php`
- `app/Models/TimeOffRequest.php`
- `app/Models/TimeOffBalance.php`
- `app/Models/Holiday.php`

**Services** (4):
- `app/Service/TimeOffService.php`
- `app/Service/AccrualService.php`
- `app/Service/TimeOffBalanceService.php`
- `app/Service/HolidayService.php`

**Controllers** (4):
- `app/Http/Controllers/Api/V1/TimeOffPolicyController.php`
- `app/Http/Controllers/Api/V1/HolidayController.php`
- `app/Http/Controllers/Api/V1/TimeOffRequestController.php`
- `app/Http/Controllers/Api/V1/TimeOffBalanceController.php`

**Requests** (10):
- `app/Http/Requests/V1/TimeOffPolicy/TimeOffPolicyStoreRequest.php`
- `app/Http/Requests/V1/TimeOffPolicy/TimeOffPolicyUpdateRequest.php`
- `app/Http/Requests/V1/Holiday/HolidayStoreRequest.php`
- `app/Http/Requests/V1/Holiday/HolidayUpdateRequest.php`
- `app/Http/Requests/V1/Holiday/HolidayIndexRequest.php`
- `app/Http/Requests/V1/TimeOffRequest/TimeOffRequestStoreRequest.php`
- `app/Http/Requests/V1/TimeOffRequest/TimeOffRequestApproveRequest.php`
- `app/Http/Requests/V1/TimeOffRequest/TimeOffRequestDenyRequest.php`
- `app/Http/Requests/V1/TimeOffBalance/TimeOffBalanceAdjustRequest.php`
- `app/Http/Requests/V1/TimeOffBalance/TimeOffBalanceIndexRequest.php`

**Resources** (4):
- `app/Http/Resources/V1/TimeOffPolicy/TimeOffPolicyResource.php`
- `app/Http/Resources/V1/Holiday/HolidayResource.php`
- `app/Http/Resources/V1/TimeOffRequest/TimeOffRequestResource.php`
- `app/Http/Resources/V1/TimeOffBalance/TimeOffBalanceResource.php`

**Notifications** (3):
- `app/Notifications/TimeOffRequestSubmittedNotification.php`
- `app/Notifications/TimeOffRequestApprovedNotification.php`
- `app/Notifications/TimeOffRequestDeniedNotification.php`

**Commands** (2):
- `app/Console/Commands/AccrueTimeOffBalancesCommand.php`
- `app/Console/Commands/ProcessYearEndCarryoverCommand.php`

**Permissions** (1):
- `app/Permissions/TimeOffPermissions.php`

**Factories** (4):
- `database/factories/TimeOffPolicyFactory.php`
- `database/factories/TimeOffRequestFactory.php`
- `database/factories/TimeOffBalanceFactory.php`
- `database/factories/HolidayFactory.php`

**Seeders** (1):
- `database/seeders/TimeOffPolicySeeder.php`

### Frontend Files to Create (~12 files)

**Pages** (2):
- `resources/js/Pages/TimeOff.vue`
- `resources/js/Pages/TimeOffSettings.vue`

**Components** (8):
- `resources/js/packages/ui/src/TimeOff/TimeOffBalanceCard.vue`
- `resources/js/packages/ui/src/TimeOff/TimeOffRequestForm.vue`
- `resources/js/packages/ui/src/TimeOff/TimeOffRequestList.vue`
- `resources/js/packages/ui/src/TimeOff/HolidayCalendar.vue`
- `resources/js/packages/ui/src/TimeOff/TimeOffPolicyList.vue`
- `resources/js/packages/ui/src/TimeOff/TimeOffPolicyForm.vue`
- `resources/js/packages/ui/src/TimeOff/HolidayList.vue`
- `resources/js/packages/ui/src/TimeOff/HolidayForm.vue`

**Stores** (1):
- `resources/js/utils/useTimeOff.ts`

**Types** (1):
- `resources/js/types/time-off.d.ts`

### Files to Modify (~6 files)

- `routes/api.php` (add PTO routes)
- `routes/web.php` (add PTO pages)
- `app/Console/Kernel.php` (add scheduled commands)
- `config/scheduling.php` (add accrual command flags)
- `app/Providers/JetstreamServiceProvider.php` (call `TimeOffPermissions::register()`)
- `resources/js/Layouts/AppLayout.vue` (add Time Off nav item)
- `resources/js/utils/permissions.ts` (add `canViewTimeOff()`, `canApproveTimeOffRequests()`)

### Test Files to Create (~15 files)

**Endpoint Tests** (4):
- `tests/Unit/Endpoint/Api/V1/TimeOffPolicyEndpointTest.php`
- `tests/Unit/Endpoint/Api/V1/HolidayEndpointTest.php`
- `tests/Unit/Endpoint/Api/V1/TimeOffRequestEndpointTest.php`
- `tests/Unit/Endpoint/Api/V1/TimeOffBalanceEndpointTest.php`

**Service Tests** (4):
- `tests/Unit/Service/TimeOffServiceTest.php`
- `tests/Unit/Service/AccrualServiceTest.php`
- `tests/Unit/Service/TimeOffBalanceServiceTest.php`
- `tests/Unit/Service/HolidayServiceTest.php`

**Command Tests** (2):
- `tests/Unit/Console/Commands/AccrueTimeOffBalancesCommandTest.php`
- `tests/Unit/Console/Commands/ProcessYearEndCarryoverCommandTest.php`

**Component Tests** (4):
- `resources/js/packages/ui/src/TimeOff/__tests__/TimeOffBalanceCard.test.ts`
- `resources/js/packages/ui/src/TimeOff/__tests__/TimeOffRequestForm.test.ts`
- `resources/js/packages/ui/src/TimeOff/__tests__/TimeOffRequestList.test.ts`
- `resources/js/packages/ui/src/TimeOff/__tests__/HolidayCalendar.test.ts`

**E2E Tests** (1):
- `e2e/time-off.spec.ts`

---

## Implementation Phases

### Phase 1: Foundation (Sprint 1, Weeks 1-2)

**Goal**: Database schema, models, enums, services

**Tasks**:
- PTO-001: Create migrations (policies, holidays, balances, requests)
- PTO-002: Create Eloquent models with relationships
- PTO-003: Create enums (TimeOffType, AccrualFrequency, ApprovalStatus)
- PTO-004: Create HasApprovalWorkflow trait
- PTO-005: Create TimeOffService (request lifecycle)
- PTO-006: Create HolidayService (holiday lookups)

**Deliverables**: All backend data structures, no API yet

---

### Phase 2: Core API (Sprint 2, Weeks 3-4)

**Goal**: API endpoints for policies, holidays, requests, balances

**Tasks**:
- PTO-007: TimeOffPolicyController (CRUD)
- PTO-008: HolidayController (CRUD)
- PTO-009: TimeOffRequestController (create, withdraw)
- PTO-010: TimeOffRequestController (approve, deny)
- PTO-011: TimeOffBalanceController (query, adjust)
- PTO-012: Request validation classes (10 files)
- PTO-013: API resources (4 files)
- PTO-014: Permissions setup (TimeOffPermissions.php)
- PTO-015: Routes (api.php)

**Deliverables**: Fully functional API, testable via Postman/curl

---

### Phase 3: Accrual Engine (Sprint 3, Weeks 5-6)

**Goal**: Scheduled commands for accruals and carryover

**Tasks**:
- PTO-016: AccrualService (monthly accrual logic)
- PTO-017: TimeOffBalanceService (carryover logic)
- PTO-018: AccrueTimeOffBalancesCommand
- PTO-019: ProcessYearEndCarryoverCommand
- PTO-020: Update Kernel.php (scheduling)
- PTO-021: Add config flags (scheduling.php)
- PTO-022: Notification classes (3 files)
- PTO-023: Service tests (4 files)
- PTO-024: Command tests (2 files)

**Deliverables**: Automated accrual system, notifications

---

### Phase 4: Frontend (Sprint 4, Weeks 7-8)

**Goal**: UI for employees and managers

**Tasks**:
- PTO-025: Pinia store (useTimeOff.ts)
- PTO-026: TimeOff.vue page (main employee view)
- PTO-027: TimeOffSettings.vue page (admin view)
- PTO-028: TimeOffBalanceCard.vue component
- PTO-029: TimeOffRequestForm.vue component
- PTO-030: TimeOffRequestList.vue component
- PTO-031: HolidayCalendar.vue component
- PTO-032: TimeOffPolicyList.vue component
- PTO-033: TimeOffPolicyForm.vue component
- PTO-034: HolidayList.vue component
- PTO-035: HolidayForm.vue component
- PTO-036: Update AppLayout.vue (navigation)
- PTO-037: Update permissions.ts (helper functions)
- PTO-038: Web routes (web.php)
- PTO-039: TypeScript types (time-off.d.ts)
- PTO-040: Component tests (4 files)
- PTO-041: E2E tests (1 file)

**Deliverables**: Complete UI, end-to-end feature ready for UAT

---

### Critical Paths

```
Phase 1 (Foundations)
    ├─> Phase 2 (API) ─────────┐
    │                           ├─> Phase 4 (Frontend)
    └─> Phase 3 (Accrual) ─────┘
```

Phase 1 blocks everything.  
Phase 2 and 3 can partially overlap (accrual doesn't need API).  
Phase 4 requires Phase 2 (API) to be complete.

---

## Permissions

**Registered via**: `App\Permissions\TimeOffPermissions::register()` (SF-08)

**Permissions**:
- `time-off-policies:view` (all)
- `time-off-policies:create` (Admin+)
- `time-off-policies:update` (Admin+)
- `time-off-policies:delete` (Admin+)
- `holidays:view` (all)
- `holidays:create` (Admin+)
- `holidays:update` (Admin+)
- `holidays:delete` (Admin+)
- `time-off-requests:view:own` (all)
- `time-off-requests:view:all` (Manager+)
- `time-off-requests:create:own` (all)
- `time-off-requests:approve` (Manager+)
- `time-off-balances:view:own` (all)
- `time-off-balances:view:all` (Manager+)
- `time-off-balances:adjust` (Admin+)

**Role Assignments**:

| Permission | Owner | Admin | Manager | Employee |
|-----------|-------|-------|---------|----------|
| `time-off-policies:view` | Y | Y | Y | Y |
| `time-off-policies:create` | Y | Y | N | N |
| `time-off-policies:update` | Y | Y | N | N |
| `time-off-policies:delete` | Y | Y | N | N |
| `holidays:view` | Y | Y | Y | Y |
| `holidays:create` | Y | Y | N | N |
| `holidays:update` | Y | Y | N | N |
| `holidays:delete` | Y | Y | N | N |
| `time-off-requests:view:own` | Y | Y | Y | Y |
| `time-off-requests:view:all` | Y | Y | Y | N |
| `time-off-requests:create:own` | Y | Y | Y | Y |
| `time-off-requests:approve` | Y | Y | Y | N |
| `time-off-balances:view:own` | Y | Y | Y | Y |
| `time-off-balances:view:all` | Y | Y | Y | N |
| `time-off-balances:adjust` | Y | Y | N | N |

---

## Testing Strategy

### Unit Tests

**Endpoint Tests** (extend `ApiEndpointTestAbstract`):
- Policy CRUD: permissions, validation, deletion constraints
- Holiday CRUD: unique date constraint, year filtering
- Request lifecycle: create, approve, deny, withdraw, overlapping validation
- Balance queries: own vs. all, manual adjustments

**Service Tests**:
- TimeOffService: Hours calculation (weekends, holidays), balance validation, self-approval prevention
- AccrualService: Idempotency, max_balance cap, waiting period, per_hour_worked calculation
- TimeOffBalanceService: Carryover logic, max_carryover cap
- HolidayService: Recurring holiday expansion, leap year handling

**Command Tests**:
- AccrueTimeOffBalancesCommand: Multiple runs same month (idempotent), summary output
- ProcessYearEndCarryoverCommand: Correct carryover amounts, max_carryover enforcement

### Component Tests (Vitest)

- TimeOffBalanceCard: Displays correct hours, color coding
- TimeOffRequestForm: Date validation, hours preview
- TimeOffRequestList: Row expand/collapse, status display
- HolidayCalendar: Holiday dots, month navigation

### E2E Tests (Playwright)

- Full request workflow: Employee creates request → Manager approves → Balance updated
- Holiday calendar: Admin adds holiday → Employee sees holiday in calendar → Hours calculation excludes holiday
- Accrual command: Run command → Balances updated → Check idempotency

---

## Performance Considerations

**Database Indexes**:
- `time_off_requests`: `(member_id)`, `(organization_id)`, `(policy_id)`, `(status)`, `(start_date, end_date)`
- `time_off_balances`: `(member_id)`, `(organization_id)`, `(policy_id, year)`
- `holidays`: `(organization_id, date)`

**Query Optimization**:
- Balance calculation: Use computed property `available_hours` (no N+1 queries)
- Request list: Eager load `member`, `policy`, `reviewer` relationships
- Accrual command: Batch process (process all members for a policy before moving to next policy)

**Caching** (optional future enhancement):
- Cache holiday list per organization per year (invalidate on create/update/delete)
- Cache balance summaries (invalidate on request status change, accrual, adjustment)

---

## Security Considerations

1. **Organization Scoping**: All queries use `whereBelongsTo($organization, 'organization')` to prevent cross-organization data leaks
2. **Self-Approval Prevention**: Enforced in service layer (`reviewer_id !== member_id`)
3. **Permission Checks**: All endpoints use `$this->checkPermission()` from base Controller
4. **Input Validation**: All inputs validated via `BaseFormRequest` subclasses with `ExistsEloquent` for FK validation
5. **Audit Trail**: All models use `CustomAuditable` trait for change tracking
6. **SQL Injection**: Eloquent ORM used throughout (no raw SQL), parameterized queries

---

## Monitoring & Observability

**Logs**:
- Accrual command: Log summary (members processed, accruals credited)
- Carryover command: Log summary (balances processed, total hours carried over, forfeited hours)
- Manual balance adjustments: Log to audit trail with reason
- Failed validations: Log to Laravel log (422 responses)

**Metrics to Track** (future):
- Average request approval time
- Accrual command execution time
- PTO utilization rate (used hours / accrued hours)
- Top PTO policies by request volume

---

## Next Steps

1. **Review** this architecture with stakeholders
2. **Create feature branch** `feature/07-pto-time-off` from `main`
3. **Begin Phase 1** implementation (PTO-001 through PTO-006)
4. **Run tests continuously**: `composer test && npm run test`
5. **Submit PR** when Phase 4 is complete

**Reference Files**:
- PRD: `.features/07-pto-time-off/PRD.md`
- Shared Foundations: `.features/SHARED-FOUNDATIONS.md`
- CLAUDE.md: `/home/keven/Documents/solidtime-analysis/CLAUDE.md`

---

## End of Architecture Document

This document provides a complete, actionable blueprint for implementing Feature 07: PTO & Time Off. All patterns are extracted from the existing Solidtime codebase, and all design decisions are made with clear rationale and trade-offs documented.

**Total Files**:
- Create: ~72 files (45 backend + 12 frontend + 15 tests)
- Modify: ~6 files
- Migrations: 4 (date prefix `2026_03_07_`)
- Estimated effort: 8 weeks (4 sprints)