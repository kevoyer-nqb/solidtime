# Architecture Research

**Domain:** Enterprise time management SaaS (17 features integrating into existing Laravel + Vue + Inertia.js)
**Researched:** 2026-02-10
**Confidence:** HIGH (based on direct codebase analysis + verified Laravel patterns)

## Existing Architecture Baseline

Before addressing integration points, the existing system must be clearly understood. Solidtime follows a clean layered architecture that the 17 new features must respect and extend -- not replace.

```
+-----------------------------------------------------------------------+
|                        FRONTEND (Vue 3 + Inertia.js)                  |
|  +----------+  +----------+  +----------+  +----------+  +--------+  |
|  |  Pages/  |  | packages |  | Layouts/ |  |Components|  | utils/ |  |
|  | Time.vue |  | /ui/src/ |  | AppLay.  |  | Banner   |  |useFoo  |  |
|  | Dash.vue |  | TimeEntry|  |          |  | OrgSwitch|  |Store.ts|  |
|  +-----+----+  +-----+----+  +----+-----+  +----+-----+  +---+----+  |
|        |              |            |              |             |      |
|        +------+-------+------+-----+------+------+             |      |
|               |              |            |                    |      |
|         +-----v-----+  +----v----+  +----v----------+         |      |
|         | Pinia      |  |Tanstack |  | @inertiajs/   |         |      |
|         | Stores     |  |VueQuery |  | vue3          |         |      |
|         +-----+------+  +----+----+  +-------+-------+         |      |
|               |              |                |                |      |
|         +-----v--------------v----------------v---------+      |      |
|         |   API Client (Zodios, OpenAPI-generated)      |<-----+      |
|         |   resources/js/packages/api/src/               |             |
|         +------------------------+------------------------+            |
+----------------------------------|------------------------------------|
                                   | HTTP (JSON)
+----------------------------------v------------------------------------+
|                        BACKEND (Laravel 12)                           |
|                                                                       |
|  +--------------------+     +--------------------+                    |
|  | routes/api.php     |     | routes/web.php     |                    |
|  | (REST API, Passport|     | (Inertia, Jetstream|                    |
|  |  auth:api)         |     |  auth:web)         |                    |
|  +---------+----------+     +---------+----------+                    |
|            |                          |                               |
|  +---------v--------------------------v----------+                    |
|  |      Controllers (Api/V1/)                    |                    |
|  |  - Permission checks via PermissionStore      |                    |
|  |  - Request validation via FormRequest         |                    |
|  |  - Org-scoped via route model binding         |                    |
|  +---------------------+------------------------+                    |
|                         |                                             |
|  +---------------------v------------------------+                    |
|  |      Service Layer (app/Service/)             |                    |
|  |  - Stateless business logic                   |                    |
|  |  - Injected via DI container                  |                    |
|  |  - Dispatches Jobs/Events                     |                    |
|  +---------------------+------------------------+                    |
|                         |                                             |
|  +---------------------v------------------------+                    |
|  |      Models (app/Models/)                     |                    |
|  |  - Eloquent with UUID primary keys            |                    |
|  |  - Audit trail (OwenIt)                       |                    |
|  |  - Computed attributes                        |                    |
|  |  - Organization-scoped relationships          |                    |
|  +---------------------+------------------------+                    |
|                         |                                             |
|  +---------------------v------------------------+                    |
|  |      PostgreSQL 15                            |                    |
|  |  - Organization-scoped foreign keys           |                    |
|  |  - tpetry/laravel-postgresql-enhanced         |                    |
|  +-----------------------------------------------+                    |
|                                                                       |
|  +-------------------+  +------------------+  +------------------+    |
|  | Jobs (app/Jobs/)  |  |Events (app/      |  |Mail (app/Mail/) |    |
|  | (Queue: sync/db)  |  | Events/)         |  |                 |    |
|  +-------------------+  +------------------+  +------------------+    |
|                                                                       |
|  +-------------------+  +------------------+  +------------------+    |
|  | Filament (Admin)  |  |Gotenberg (PDF)   |  |S3 (File Storage)|    |
|  +-------------------+  +------------------+  +------------------+    |
+-----------------------------------------------------------------------+
```

### Component Responsibilities (Existing)

| Component | Responsibility | Typical Implementation |
|-----------|----------------|------------------------|
| API Controllers | HTTP handling, validation orchestration, permission checks | Extend `Api\V1\Controller`, inject services via params |
| FormRequest Classes | Input validation with org-scoped rules | Extend `BaseFormRequest`, use `ExistsEloquent` |
| API Resources | JSON response shaping, field exposure control | Extend `BaseResource`, use `formatDateTime()` |
| Service Classes | Stateless business logic, aggregations, external calls | Constructor injection, return values (no side effects) |
| Eloquent Models | Data structure, relationships, computed attributes | UUIDs, `CustomAuditable`, `ComputedAttributes` |
| Pinia Stores | Frontend state, API orchestration, cache invalidation | `defineStore('name', () => {...})`, return refs + methods |
| API Client | Type-safe HTTP layer between frontend and backend | Zodios from OpenAPI spec, `api.methodName()` |
| AppLayout | Navigation, sidebar, org context, auth guard | Single layout, permission-gated nav items |

## New Component Architecture (17 Features)

The 17 features introduce six new architectural concerns that do not exist in the current codebase:

1. **Notification Infrastructure** -- cross-cutting, used by 5+ features
2. **Approval/Workflow State Machine** -- used by timesheets, expenses, PTO
3. **File Upload Pipeline** -- used by expenses (receipts), invoicing (logos/attachments)
4. **External Service Integration** -- OAuth calendar sync, accounting sync, PM tool webhooks
5. **Kiosk/Alternate Auth Mode** -- PIN/QR auth on shared devices, separate from Passport/Jetstream
6. **Scheduling/Capacity System** -- resource assignments, capacity planning, utilization tracking

### System Overview (Extended)

```
+-----------------------------------------------------------------------+
|                       FRONTEND                                        |
|                                                                       |
|  Existing:                     New:                                   |
|  +----------+                  +----------------+  +---------------+  |
|  | Time.vue |                  | Approvals.vue  |  | Expenses.vue  |  |
|  | Dash.vue |                  | Invoices.vue   |  | Schedule.vue  |  |
|  | Calendar |                  | Kiosk.vue      |  | PTO.vue       |  |
|  | Report.. |                  | Analytics.vue  |  | Teams.vue     |  |
|  +----------+                  +----------------+  +---------------+  |
|       |                               |                    |          |
|  +----v---------+              +------v--------+    +------v------+  |
|  | Existing     |              | New Pinia     |    | Notification |  |
|  | Pinia Stores |              | Stores        |    | Store (bell  |  |
|  | (timeEntries |              | (useApprovals |    | icon, toast, |  |
|  |  projects,.)|              |  useExpenses  |    | inbox)       |  |
|  +--------------+              |  useInvoices) |    +-------------+  |
|                                +---------------+                     |
|       +--------------------+-------------------+                     |
|       |     API Client (regenerated from extended OpenAPI)     |     |
|       +----------------------------+---------------------------+     |
+------------------------------------|---------------------------------+
                                     | HTTP
+------------------------------------v---------------------------------+
|                       BACKEND (Extended)                              |
|                                                                       |
|  +-- New API Routes -----------------------------------------------+ |
|  | /organizations/{org}/approvals                                   | |
|  | /organizations/{org}/expenses                                    | |
|  | /organizations/{org}/invoices                                    | |
|  | /organizations/{org}/schedules                                   | |
|  | /organizations/{org}/pto                                         | |
|  | /organizations/{org}/budgets                                     | |
|  | /organizations/{org}/teams                                       | |
|  | /kiosk/{org}/...  (separate auth guard)                          | |
|  +------------------------------------------------------------------+ |
|                                                                       |
|  +-- New Service Classes ------------------------------------------+ |
|  | ApprovalService          | ExpenseService                       | |
|  | InvoiceService           | ScheduleService                      | |
|  | PtoService               | BudgetService                        | |
|  | NotificationService      | CalendarSyncService                  | |
|  | KioskAuthService         | AccountingSyncService                | |
|  | WebhookService           | AnalyticsService                     | |
|  +------------------------------------------------------------------+ |
|                                                                       |
|  +-- New Models ------------------------------------------------+    |
|  | Approval        | Expense      | ExpenseCategory              |    |
|  | Invoice         | InvoiceLine  | Schedule                     |    |
|  | PtoPolicy       | PtoRequest   | Budget                       |    |
|  | Team            | Notification | CalendarConnection           |    |
|  | KioskSession    | CustomField  | IntegrationConnection        |    |
|  +---------------------------------------------------------------+    |
|                                                                       |
|  +-- New Infrastructure -----------------------------------------+    |
|  | Laravel Notifications   | (database + mail + broadcast)       |    |
|  | Laravel Queue (Redis)   | (async jobs for sync, PDF, email)  |    |
|  | Laravel Broadcasting    | (Reverb/Pusher for real-time)      |    |
|  | State Machine           | (approval transitions)             |    |
|  | OAuth Token Store       | (Google/Outlook calendar tokens)   |    |
|  +---------------------------------------------------------------+    |
+-----------------------------------------------------------------------+
```

### New Component Responsibilities

| Component | Responsibility | Communicates With |
|-----------|---------------|-------------------|
| NotificationService | Dispatch notifications via mail, database, broadcast | All features that send alerts; User model (Notifiable) |
| ApprovalService | Manage submit/approve/reject transitions, lock entries | TimesheetController, ExpenseController, PtoController |
| ExpenseService | CRUD expenses, receipt handling, billable calculations | FileUpload pipeline, ApprovalService, InvoiceService |
| InvoiceService | Generate invoices from time/expenses, PDF creation | Gotenberg, TimeEntryAggregationService, ExpenseService |
| CalendarSyncService | OAuth token management, event sync, conflict resolution | Google Calendar API, Microsoft Graph API, TimeEntryService |
| KioskAuthService | PIN/QR validation, session management on shared devices | Custom auth guard, MemberService, TimeEntryService |
| ScheduleService | Resource assignments, capacity calculation, utilization | MemberService, ProjectService, TimeEntryAggregationService |
| BudgetService | Budget tracking, threshold alerts, burn rate forecasting | ProjectService, TimeEntryAggregationService, NotificationService |
| PtoService | Leave policies, accruals, balance calculations | MemberService, ApprovalService, NotificationService |
| AccountingSyncService | Map invoices/expenses to Xero/QuickBooks entities | InvoiceService, ExpenseService, OAuth token store |
| WebhookService | Receive/send webhooks for PM tools (Jira, Asana, Trello) | TaskService, ProjectService, TimeEntryService |
| AnalyticsService | Profitability, utilization, capacity analytics | TimeEntryAggregationService, BudgetService, ScheduleService |
| TeamService | Sub-org team management, visibility scoping | MemberService, ProjectService, all org-scoped queries |

## Data Flow Patterns

### Pattern 1: Standard CRUD (Existing, Proven)

Most new features follow this pattern. No architectural change needed.

```
User Action (Vue)
    |
    v
Pinia Store -> api.createFoo() -> HTTP POST
    |
    v
FooController::store()
    |-> FooStoreRequest (validates)
    |-> $this->checkPermission($org, 'foo:create')
    |-> FooService::create($data)
    |-> return new FooResource($model)
    |
    v
Store updates local state, components re-render
```

**Used by:** Expenses, Budgets, Teams, CustomFields, PTO Policies, Schedules

### Pattern 2: Approval Workflow (New)

Three features need approval state transitions: timesheets, expenses, PTO requests.

```
Employee submits      Manager reviews       System locks
    |                     |                     |
    v                     v                     v
[draft] --submit()--> [pending] --approve()--> [approved] --lock()-->  [locked]
                          |
                          +--reject()--> [rejected] --resubmit()--> [pending]
```

**Implementation: Enum-based state machine (not a library).**

Rationale: The approval flow is simple (5 states, 5 transitions). A library like `asantibanez/laravel-eloquent-state-machines` adds dependency weight for a pattern easily expressed with a PHP enum, a transition method on the service, and event dispatching. The existing codebase already uses enums (`App\Enums\Role`) and events -- this stays consistent.

```php
// app/Enums/ApprovalStatus.php
enum ApprovalStatus: string {
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Locked = 'locked';
}

// app/Service/ApprovalService.php
class ApprovalService {
    private const TRANSITIONS = [
        'draft'    => ['pending'],
        'pending'  => ['approved', 'rejected'],
        'rejected' => ['pending'],
        'approved' => ['locked'],
    ];

    public function transition(Approvable $model, ApprovalStatus $to, Member $actor): void
    {
        $from = $model->approval_status;
        if (!in_array($to->value, self::TRANSITIONS[$from->value] ?? [])) {
            throw new InvalidTransitionException($from, $to);
        }
        $model->approval_status = $to;
        $model->save();
        event(new ApprovalTransitioned($model, $from, $to, $actor));
    }
}
```

**Confidence:** HIGH -- enum + service pattern matches existing codebase conventions. No new dependency.

### Pattern 3: Notification Dispatch (New Infrastructure)

Laravel's built-in notification system is the correct tool. It supports database + mail + broadcast channels natively.

```
Feature triggers event
    |
    v
EventListener dispatches Notification
    |
    +---> DatabaseChannel -> notifications table -> Pinia NotificationStore (polling/broadcast)
    |
    +---> MailChannel -> SMTP -> user inbox
    |
    +---> BroadcastChannel -> Reverb/Pusher -> WebSocket -> frontend toast
```

**Implementation details:**
- Add `notifications` table via `php artisan notifications:table` migration
- User model already uses `Notifiable` trait (via Jetstream)
- Create `app/Notifications/{Feature}/` directory per feature
- Each notification class defines `via()` to return channels based on user preferences
- Frontend: new `useNotificationsStore` Pinia store that polls `/api/v1/users/me/notifications` or listens via WebSocket
- Bell icon in `AppLayout.vue` sidebar shows unread count

**Confidence:** HIGH -- Laravel notifications are first-party, well-documented, stable API.

### Pattern 4: File Upload Pipeline (New)

Expense receipts and invoice attachments need file uploads. The existing codebase already has S3 configuration.

```
Frontend: <input type="file">
    |
    v
multipart/form-data POST to /api/v1/organizations/{org}/uploads
    |
    v
UploadController -> validates (mime, size) -> stores to S3 -> returns file reference
    |
    v
Expense/Invoice stores file_path or media_id as relationship
```

**Implementation:** Use Laravel's built-in file upload with the existing S3 disk (`config/filesystems.php` already has `s3` configured). Do NOT use a heavy media library like Spatie MediaLibrary -- the use case is simple (receipt images, PDF attachments). A lightweight `Upload` model tracking `disk`, `path`, `original_name`, `mime_type`, `size`, `uploadable_type`, `uploadable_id` (polymorphic) is sufficient.

**Confidence:** HIGH -- S3 already configured, Laravel file handling is well-established.

### Pattern 5: External OAuth Integration (Calendar Sync, Accounting)

Calendar sync (Google/Outlook) and accounting sync (Xero/QuickBooks) both require OAuth 2.0 token management with refresh logic.

```
User clicks "Connect Google Calendar" in Settings
    |
    v
Backend redirects to Google OAuth consent screen
    |
    v
Google redirects back with auth code
    |
    v
Backend exchanges code for access_token + refresh_token
    |
    v
Stores tokens in CalendarConnection model (encrypted)
    |
    v
Scheduled job (every 5 min) syncs calendar events <-> time entries
    |
    v
Conflict resolution: time entries are source of truth, calendar is read-sync
```

**Token storage model:**
```php
// app/Models/IntegrationConnection.php
class IntegrationConnection extends Model {
    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'settings' => 'array',
    ];
    // Polymorphic: provider (google_calendar, outlook, xero, quickbooks)
    // Belongs to: Member (personal connections) or Organization (org-wide)
}
```

**Confidence:** MEDIUM -- OAuth patterns are well-established, but calendar sync specifics (delta sync tokens, conflict resolution) need phase-specific research. Google Calendar API and Microsoft Graph API have different sync mechanisms.

### Pattern 6: Kiosk Mode (Separate Auth Context)

Kiosk mode requires a fundamentally different auth flow from the standard Passport/Jetstream pattern. A shared device has no individual user session -- instead, the organization configures a kiosk, and employees authenticate per-action via PIN or QR code.

```
Admin configures kiosk in Settings
    |
    v
Kiosk device navigates to /kiosk/{org}/{kiosk-token}
    |
    v
Kiosk shows clock-in screen (no user session)
    |
    v
Employee enters PIN or scans QR code
    |
    v
Backend validates PIN against Member.kiosk_pin (hashed)
    |
    v
Creates time entry for that member
    |
    v
Returns to clock-in screen (no persistent session)
```

**Implementation:** A custom Laravel guard (`kiosk`) that authenticates the organization via a long-lived token in the URL, then authenticates the individual member per-request via PIN. This is separate from both the `api` guard (Passport) and `web` guard (Jetstream).

**Confidence:** MEDIUM -- custom guard pattern is documented in Laravel, but kiosk UX patterns (break tracking, attendance tracking, shift display) need phase-specific research.

### Pattern 7: Async Queue Processing (Infrastructure Upgrade)

The current system uses `QUEUE_CONNECTION=sync` (all jobs execute synchronously). The 17 new features require genuine async processing for:
- Email notification sending (N emails per approval action)
- Calendar sync jobs (external API calls, 5+ second latency)
- PDF generation for invoices (Gotenberg call)
- Accounting sync (external API calls)
- Webhook dispatch to PM tools

**Implementation:** Switch default queue to `database` (simplest, already configured in `config/queue.php`). Redis is available if needed for performance but adds infrastructure complexity for a solo developer. Database queue with `php artisan queue:work` is sufficient for SMB scale.

**Named queues for priority:**
- `default` -- standard processing
- `notifications` -- email/notification dispatch
- `sync` -- external API sync (calendar, accounting, PM tools)
- `pdf` -- Gotenberg PDF generation

**Confidence:** HIGH -- queue infrastructure is already configured, just needs to be activated.

## Recommended Project Structure (New Directories)

```
app/
+-- Enums/
|   +-- ApprovalStatus.php          # Shared approval states
|   +-- ExpenseCategory.php         # Expense categories
|   +-- InvoiceStatus.php           # Invoice lifecycle states
|   +-- PtoRequestStatus.php        # PTO request states
|   +-- BudgetType.php              # hours/cost/fixed
|   +-- IntegrationProvider.php     # google_calendar, outlook, xero, etc.
|
+-- Events/
|   +-- ApprovalTransitioned.php    # Generic approval state change
|   +-- ExpenseCreated.php
|   +-- InvoiceSent.php
|   +-- BudgetThresholdReached.php
|   +-- PtoRequestSubmitted.php
|
+-- Http/
|   +-- Controllers/Api/V1/
|   |   +-- ApprovalController.php
|   |   +-- ExpenseController.php
|   |   +-- InvoiceController.php
|   |   +-- BudgetController.php
|   |   +-- ScheduleController.php
|   |   +-- PtoController.php
|   |   +-- TeamController.php
|   |   +-- NotificationController.php
|   |   +-- IntegrationController.php
|   |   +-- KioskController.php        # Separate guard
|   |   +-- CustomFieldController.php
|   |   +-- AnalyticsController.php
|   |
|   +-- Requests/V1/
|   |   +-- Approval/
|   |   +-- Expense/
|   |   +-- Invoice/
|   |   +-- Budget/
|   |   +-- Schedule/
|   |   +-- Pto/
|   |   +-- Team/
|   |
|   +-- Resources/V1/
|       +-- Approval/
|       +-- Expense/
|       +-- Invoice/
|       +-- Budget/
|       +-- Schedule/
|       +-- Notification/
|
+-- Jobs/
|   +-- SyncCalendarEvents.php
|   +-- SyncAccountingData.php
|   +-- SendWebhook.php
|   +-- GenerateInvoicePdf.php
|   +-- CalculatePtoAccruals.php
|
+-- Listeners/
|   +-- SendApprovalNotification.php
|   +-- SendBudgetAlert.php
|   +-- LockApprovedTimeEntries.php
|
+-- Models/
|   +-- Approval.php
|   +-- Expense.php
|   +-- ExpenseCategory.php
|   +-- Invoice.php
|   +-- InvoiceLine.php
|   +-- Budget.php
|   +-- Schedule.php
|   +-- PtoPolicy.php
|   +-- PtoRequest.php
|   +-- Team.php
|   +-- TeamMember.php
|   +-- IntegrationConnection.php
|   +-- KioskDevice.php
|   +-- CustomField.php
|   +-- CustomFieldValue.php
|
+-- Notifications/
|   +-- ApprovalRequestedNotification.php
|   +-- ApprovalDecisionNotification.php
|   +-- BudgetThresholdNotification.php
|   +-- PtoRequestNotification.php
|   +-- TimesheetReminderNotification.php
|
+-- Service/
    +-- ApprovalService.php
    +-- ExpenseService.php
    +-- InvoiceService.php
    +-- InvoicePdfService.php
    +-- BudgetService.php
    +-- ScheduleService.php
    +-- PtoService.php
    +-- PtoAccrualService.php
    +-- TeamService.php
    +-- CalendarSyncService.php
    +-- AccountingSyncService.php
    +-- WebhookService.php
    +-- AnalyticsService.php
    +-- KioskAuthService.php
    +-- NotificationPreferenceService.php

resources/js/
+-- Pages/
|   +-- Approvals.vue
|   +-- Expenses.vue
|   +-- Invoices.vue
|   +-- InvoiceShow.vue
|   +-- Budgets.vue
|   +-- Schedule.vue
|   +-- PTO.vue
|   +-- Teams.vue
|   +-- Analytics.vue
|   +-- Kiosk.vue                    # Standalone, no AppLayout
|
+-- utils/
|   +-- useApprovals.ts
|   +-- useExpenses.ts
|   +-- useInvoices.ts
|   +-- useBudgets.ts
|   +-- useSchedule.ts
|   +-- usePto.ts
|   +-- useTeams.ts
|   +-- useAnalytics.ts
|   +-- useNotifications.ts          # Bell icon + inbox
|   +-- useIntegrations.ts
|
+-- packages/ui/src/
    +-- Approval/
    +-- Expense/
    +-- Invoice/
    +-- Budget/
    +-- Schedule/
    +-- Notification/
    +-- Kiosk/
    +-- Analytics/
```

### Structure Rationale

- **Per-feature directories in Requests, Resources, UI packages:** Matches existing pattern (see `TimeEntry/`, `Project/`, `Client/` directories). No structural innovation needed.
- **Shared Enums for approval states:** Three features use the same approval flow. One enum, one service, dispatching feature-specific events.
- **Notifications in dedicated `app/Notifications/` directory:** Laravel convention. Each notification class encapsulates its own channel logic, template, and data formatting.
- **Jobs for async work:** External API calls, PDF generation, email sending. Keeps controllers fast.
- **Kiosk.vue outside AppLayout:** Kiosk mode does not use the standard navigation sidebar. It needs its own minimal layout optimized for touch/shared devices.

## Architectural Patterns

### Pattern 1: Organization-Scoped Multi-Tenancy (Existing, Extend)

**What:** Every new model gets an `organization_id` foreign key. Every new controller uses `{organization}` route parameter. Every query scopes by organization.

**When to use:** Every single new feature.

**Trade-offs:** Simple and proven in the codebase. No tenant isolation at database level (rows share tables), so all queries MUST include org scoping. Missing a `whereBelongsTo($organization)` is a data leak bug.

**Example (existing pattern to follow):**
```php
// Route
Route::name('expenses.')->prefix('/organizations/{organization}')->group(function () {
    Route::get('/expenses', [ExpenseController::class, 'index'])->name('index');
    Route::post('/expenses', [ExpenseController::class, 'store'])
        ->name('store')
        ->middleware('check-organization-blocked');
});

// Controller
public function index(Organization $organization, ExpenseIndexRequest $request): JsonResource
{
    $this->checkPermission($organization, 'expenses:view:own');
    $expenses = Expense::query()
        ->whereBelongsTo($organization, 'organization')
        ->whereBelongsTo($this->member($organization), 'member')
        ->get();
    return new ExpenseCollection($expenses);
}
```

### Pattern 2: Trait-Based Approvable Interface (New)

**What:** Models that participate in the approval workflow implement an `Approvable` interface and use an `HasApproval` trait.

**When to use:** Timesheet submissions, expense reports, PTO requests.

**Trade-offs:** Keeps approval logic DRY across three features. Trait adds columns (`approval_status`, `submitted_at`, `reviewed_by`, `reviewed_at`, `rejection_reason`) to each model's migration.

**Example:**
```php
// app/Models/Concerns/HasApproval.php
trait HasApproval {
    public function reviewer(): BelongsTo {
        return $this->belongsTo(Member::class, 'reviewed_by');
    }

    public function isEditable(): bool {
        return in_array($this->approval_status, [
            ApprovalStatus::Draft,
            ApprovalStatus::Rejected,
        ]);
    }

    public function isPending(): bool {
        return $this->approval_status === ApprovalStatus::Pending;
    }
}

// Usage in Expense model
class Expense extends Model implements Approvable {
    use HasApproval;
    use HasUuids;
    use CustomAuditable;
    // ...
}
```

### Pattern 3: Event-Driven Side Effects (Extend Existing)

**What:** Use Laravel events for cross-cutting side effects instead of coupling services directly.

**When to use:** When an action in one feature should trigger behavior in another (approval -> notification, budget threshold -> alert, time entry create -> budget recalculation).

**Trade-offs:** Loose coupling at the cost of implicit flow. The existing codebase already uses events sparingly (`AfterCreateOrganization`, `MemberRemoved`). The 17 features need much heavier use.

**Example:**
```php
// When approval transitions
event(new ApprovalTransitioned($expense, ApprovalStatus::Pending, ApprovalStatus::Approved, $manager));

// Listener 1: Send notification to employee
class SendApprovalDecisionNotification {
    public function handle(ApprovalTransitioned $event): void {
        $event->model->member->user->notify(new ApprovalDecisionNotification($event));
    }
}

// Listener 2: If expense approved and billable, update project budget
class UpdateProjectBudgetOnApproval {
    public function handle(ApprovalTransitioned $event): void {
        if ($event->to === ApprovalStatus::Approved && $event->model instanceof Expense) {
            // recalculate project budget burn
        }
    }
}
```

### Pattern 4: Service Contract for External Integrations (Extend Existing)

**What:** Define interface/contract for external services, bind concrete implementation in service provider. Mirrors existing `BillingContract` pattern.

**When to use:** Calendar sync, accounting sync, payment gateway -- anywhere an external API is involved.

**Trade-offs:** Enables testing with mocks, allows swapping providers (e.g., Xero -> QuickBooks). Adds one layer of indirection.

**Example:**
```php
// app/Service/AccountingSyncContract.php
abstract class AccountingSyncContract {
    abstract public function syncInvoice(Invoice $invoice): void;
    abstract public function syncExpense(Expense $expense): void;
    abstract public function testConnection(IntegrationConnection $connection): bool;
}

// app/Service/Xero/XeroSyncService.php
class XeroSyncService extends AccountingSyncContract {
    public function syncInvoice(Invoice $invoice): void { /* Xero API calls */ }
}

// AppServiceProvider
$this->app->bind(AccountingSyncContract::class, XeroSyncService::class);
```

## Anti-Patterns to Avoid

### Anti-Pattern 1: Fat Controllers

**What people do:** Put approval logic, notification dispatch, budget recalculation all inside the controller method.
**Why it's wrong:** Controllers already trend large in solidtime (TimeEntryController is 874 lines). Adding approval + notification + budget logic makes them untestable monoliths.
**Do this instead:** Controller calls Service. Service dispatches Events. Listeners handle side effects. Controller stays under 30 lines per method.

### Anti-Pattern 2: Direct Cross-Feature Service Calls

**What people do:** `ApprovalService` directly calls `NotificationService` directly calls `BudgetService`.
**Why it's wrong:** Creates circular dependencies and makes it impossible to add/remove features independently.
**Do this instead:** Use Events. `ApprovalService` dispatches `ApprovalTransitioned` event. Separate listeners in Notification and Budget features handle their own concerns.

### Anti-Pattern 3: Shared Mutable Frontend State Across Features

**What people do:** One giant Pinia store that holds approvals, expenses, invoices, notifications.
**Why it's wrong:** Violates single responsibility, causes unnecessary re-renders, makes stores enormous.
**Do this instead:** One store per feature domain (`useApprovalsStore`, `useExpensesStore`, `useInvoicesStore`). Cross-feature coordination through Vue Query cache invalidation, not shared state.

### Anti-Pattern 4: Polling for Everything

**What people do:** Frontend polls every 5 seconds for new notifications, approval updates, budget changes.
**Why it's wrong:** N stores * 5-second interval = expensive for server and battery. Doesn't scale.
**Do this instead:** Use Laravel Broadcasting (WebSocket via Reverb) for real-time updates. Poll as fallback only. Budget threshold alerts dispatch via broadcast channel.

### Anti-Pattern 5: Kiosk Mode as a Feature Flag on Standard Auth

**What people do:** Add `?kiosk=true` parameter to standard routes, try to reuse Passport/Jetstream auth.
**Why it's wrong:** Kiosk mode has fundamentally different auth semantics (org-level token + per-action PIN). Mixing it with user sessions creates security holes.
**Do this instead:** Separate route group (`/kiosk/{org}`), separate guard, separate Vue entry point without AppLayout. Clean separation.

## Integration Points and Dependencies

### Internal Feature Dependencies

```
Shared Foundations (FOUND-001 to FOUND-007)
    |
    +-- Notifications (FOUND-004) -------> Used by: Approvals, Expenses, Budgets, PTO, Invoicing
    |
    +-- Approval Pattern (FOUND-005) ----> Used by: Timesheets, Expenses, PTO
    |
    +-- Modular Permissions (FOUND-008) -> Used by: ALL 17 features
    |
    +-- weekly_capacity schema ----------> Used by: Scheduling, PTO, Overtime

Feature 01: Timesheets ------> Depends on: Approval Pattern, Notifications
Feature 02: Expenses --------> Depends on: Approval Pattern, Notifications, File Upload
Feature 03: Budgets ---------> Depends on: Notifications (alerts), Time Entries (existing)
Feature 04: Invoicing -------> Depends on: Time Entries, Expenses, Gotenberg (PDF), Payments
Feature 05: Calendar Sync ---> Depends on: OAuth infrastructure, Time Entries
Feature 06: Kiosk -----------> Depends on: Custom auth guard, Time Entries
Feature 07: PTO -------------> Depends on: Approval Pattern, Notifications, Scheduling
Feature 08: Scheduling ------> Depends on: Members, Projects, weekly_capacity
Feature 09: Analytics -------> Depends on: ALL data-producing features (read-only aggregation)
Feature 10: Teams -----------> Depends on: Members, modifies org-scoping queries
Feature 11: Tags/Fields -----> Depends on: Polymorphic models, modifies existing query filters
Feature 14: Payments/Acct. --> Depends on: Invoicing, OAuth infrastructure
Feature 16: PM Integrations -> Depends on: Tasks, Projects, webhook infrastructure
```

### External Service Integration Points

| Service | Integration Pattern | Auth Method | Notes |
|---------|---------------------|-------------|-------|
| Google Calendar API | REST via Guzzle, delta sync tokens | OAuth 2.0 (user-level) | Refresh tokens stored encrypted in IntegrationConnection |
| Microsoft Graph API | REST via Guzzle, delta queries | OAuth 2.0 (user-level) | Different sync mechanism than Google |
| Stripe | Laravel Cashier or direct SDK | API key (org-level) | For payment links on invoices, not SaaS billing |
| Xero | REST via xero-php SDK | OAuth 2.0 (org-level) | Invoice/expense sync |
| QuickBooks Online | REST via quickbooks-sdk-php | OAuth 2.0 (org-level) | Invoice/expense sync |
| Jira | REST API + incoming webhooks | API token + webhook secret | Two-way task sync |
| Asana | REST API + webhooks | OAuth 2.0 or PAT | Task sync |
| Trello | REST API + webhooks | API key + token | Task sync |
| Gotenberg | REST (existing) | Basic auth (optional) | Already integrated for PDF export |
| SMTP | Laravel Mail (existing) | Credentials | Already configured |

### Internal Boundaries

| Boundary | Communication | Notes |
|----------|---------------|-------|
| Approval <-> TimeEntry | ApprovalService locks time entries via TimeEntryService | Approval holds reference to date range + member, locks matching entries |
| Expense <-> Invoice | InvoiceService queries approved expenses for billing | Read-only; expense status must be Approved before invoiceable |
| Budget <-> TimeEntry | BudgetService listens to TimeEntry events for burn tracking | Event-driven; RecalculateSpentTimeForProject already exists as precedent |
| Schedule <-> TimeEntry | ScheduleService compares planned vs actual hours | Read-only aggregation; schedule entries are separate from time entries |
| Teams <-> All Queries | TeamService modifies org-scoped queries to add team filtering | Must be additive (team scope narrows org scope, never broadens) |
| Kiosk <-> TimeEntry | KioskController creates time entries via TimeEntryService | Reuses existing service; different auth path, same business logic |
| CalendarSync <-> TimeEntry | SyncCalendarEvents job creates/updates time entries | Must check for overlap, respect org settings |

## Scaling Considerations

| Scale | Architecture Adjustments |
|-------|--------------------------|
| 0-1k users | Monolith is fine. Database queue. Sync notifications. No WebSocket needed -- polling every 30s sufficient. |
| 1k-10k users | Redis queue (switch from database). Add Reverb for WebSocket broadcasting. Calendar sync jobs need rate limiting. |
| 10k-100k users | Read replicas for analytics queries. Separate queue workers per named queue. Cache aggregation results. Consider splitting kiosk into separate deployment. |
| 100k+ users | Out of scope for initial architecture. Would require: tenant-level database sharding, dedicated sync workers per integration, CDN for invoice PDFs. |

### Scaling Priorities

1. **First bottleneck: Queue processing** -- External API sync (calendar, accounting) will be the first thing to slow down. Database queue is fine initially but Redis queue with dedicated workers per channel is the first upgrade.
2. **Second bottleneck: Analytics queries** -- Advanced reporting aggregates across all time entries, expenses, schedules. PostgreSQL handles this well with proper indexes, but may need materialized views or pre-computed aggregation tables at scale.
3. **Third bottleneck: Real-time notifications** -- Broadcasting to many connected clients requires WebSocket infrastructure (Reverb or Pusher). Not needed at launch but should be architecturally planned for.

## Suggested Build Order (Dependencies)

The integration analysis reveals a clear dependency chain that constrains build order:

**Phase 0: Shared Foundations (FOUND-001 to FOUND-007)**
Must be first. Notification infrastructure, approval pattern, modular permissions, and weekly_capacity schema are prerequisites for 80% of features. Everything else is blocked without these.

**Phase 1: Governance (Features 01, 02, 03)**
Timesheet approvals, expenses, and budgets. These three exercise the approval pattern, notification system, and file upload pipeline. Building them validates all shared infrastructure.

**Phase 2: Revenue (Feature 04)**
Invoicing depends on time entries + expenses being solid. Needs PDF generation (Gotenberg already exists), payment links (new Stripe integration).

**Phase 3: Time Capture (Features 05, 06)**
Calendar sync and kiosk mode. Both are architecturally independent from governance/revenue. Calendar sync introduces OAuth integration infrastructure that accounting sync (Phase 5) will reuse.

**Phase 4: Workforce (Features 07, 08)**
PTO and scheduling. Depend on approval pattern (from Phase 1) and weekly_capacity (from Phase 0). Scheduling needs member/project data that's well-established by this point.

**Phase 5: Analytics + Organization (Features 09, 10)**
Analytics aggregates data from all previous features -- build last so there's data to aggregate. Teams modifies org-scoping queries, which is safest to do after all features have their basic queries working.

**Phase 6: Extended Platform (Features 11-16)**
Tags/custom fields, punch mode, audit trail, payments/accounting, attendance, PM integrations. These are additive and can be built in parallel.

**Build order rationale:**
- Shared foundations unblock everything -> must be Phase 0
- Approval pattern is exercised by 3 features in Phase 1 -> validates pattern early
- Invoicing needs time + expense data -> Phase 2 after Phase 1
- Calendar/kiosk are independent but introduce OAuth infra -> Phase 3
- PTO/scheduling need approval + capacity -> Phase 4 after Phase 1
- Analytics reads from everything -> Phase 5 near the end
- Extended features are additive with minimal cross-dependencies -> Phase 6

## Sources

- Existing codebase analysis (direct file reading) -- **HIGH confidence**
- [Laravel 12.x Notifications Documentation](https://laravel.com/docs/12.x/notifications) -- **HIGH confidence**
- [Laravel 12.x Billing/Cashier Documentation](https://laravel.com/docs/12.x/billing) -- **HIGH confidence**
- [Laravel 12.x Authentication Documentation](https://laravel.com/docs/12.x/authentication) -- **HIGH confidence** (custom guards)
- [Laravel Eloquent State Machines](https://github.com/asantibanez/laravel-eloquent-state-machines) -- **MEDIUM confidence** (reviewed but not recommended)
- [Spatie Laravel Google Calendar](https://github.com/spatie/laravel-google-calendar) -- **MEDIUM confidence** (considered, too limited for two-way sync)
- [Laravel QuickBooks packages](https://github.com/myleshyson/laravel-quickbooks) -- **LOW confidence** (needs phase-specific evaluation)
- [Inertia.js 2.0 async features](https://inertiajs.com/) -- **MEDIUM confidence** (polling, deferred props useful for notifications)

---
*Architecture research for: Solidtime Enterprise SaaS Platform (17 features)*
*Researched: 2026-02-10*
