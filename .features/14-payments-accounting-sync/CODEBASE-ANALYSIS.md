# Codebase Analysis: Feature 14 -- Online Payments and Accounting Sync

**Date**: 2026-02-09
**Branch analyzed**: `main`
**Target feature branch**: `feature/payments-accounting-sync`
**PRD reference**: `.features/14-payments-accounting-sync/PRD.md`

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Feature 04 (Invoicing) Dependency Analysis](#2-feature-04-invoicing-dependency-analysis)
3. [Existing Client Model](#3-existing-client-model)
4. [Existing Organization Model](#4-existing-organization-model)
5. [Controller Patterns](#5-controller-patterns)
6. [Service Layer Patterns](#6-service-layer-patterns)
7. [Request Validation Patterns](#7-request-validation-patterns)
8. [Permission System](#8-permission-system)
9. [Queue and Job Infrastructure](#9-queue-and-job-infrastructure)
10. [Encryption Patterns](#10-encryption-patterns)
11. [Frontend Store Patterns](#11-frontend-store-patterns)
12. [Frontend Component Patterns](#12-frontend-component-patterns)
13. [Route Registration Patterns](#13-route-registration-patterns)
14. [Navigation Sidebar](#14-navigation-sidebar)
15. [Data Flow Diagrams](#15-data-flow-diagrams)
16. [File Modification Risk Assessment](#16-file-modification-risk-assessment)

---

## 1. Executive Summary

The Solidtime codebase provides a mature, well-structured foundation for the Online Payments and Accounting Sync feature. The established patterns for controllers, services, request validation, Pinia stores, and Vue components are directly applicable.

**Key findings**:

- **Hard dependency on Feature 04 (Invoicing)**: The `Invoice` model, `InvoiceController`, and `InvoiceService` must exist before this feature can begin. Feature 04 is estimated at 14 weeks (7 sprints). This feature cannot start until Feature 04 is complete and merged to `main`.
- **6 new database tables required**: `payment_integrations`, `accounting_integrations`, `accounting_client_mappings`, `payments`, `accounting_sync_logs`, `webhook_events`. No existing tables are modified beyond adding relations to the `Invoice` model.
- **Queue infrastructure exists but needs configuration**: Laravel's queue system is built-in and configured. Dedicated queue names (`webhooks`, `accounting-sync`) need to be configured in the queue worker setup.
- **Encryption infrastructure is ready**: Laravel's `Crypt` facade and `encrypted` model cast are available out of the box. The application's `APP_KEY` is already configured. No additional encryption setup is needed.
- **4 new Composer packages required**: `stripe/stripe-php`, `paypal/paypal-server-sdk`, `quickbooks/v3-php-sdk`, `xeroapi/xero-php-oauth2`. These are all well-maintained, widely-used packages with no known conflicts.
- **Webhook endpoints require special routing**: Webhook endpoints must be registered outside the `auth:api` middleware group. This is a departure from the standard API routing pattern but is a well-understood Laravel pattern.
- **Modular permission pattern (SF-08)** is available for registering new permissions without modifying `JetstreamServiceProvider.php` directly.

---

## 2. Feature 04 (Invoicing) Dependency Analysis

### 2.1 Required from Feature 04

This feature has a **hard dependency** on Feature 04 (Invoicing). The following entities must exist on `main` before development can begin:

**Invoice Model** (`app/Models/Invoice.php`):
- Expected fields: `id`, `organization_id`, `client_id`, `number`, `status`, `currency`, `subtotal`, `tax`, `total`, `due_date`, `issued_date`, `notes`
- Expected statuses: `draft`, `sent`, `viewed`, `overdue`, `paid`, `void`
- This feature extends the status enum to include `partially_paid`
- This feature adds relations: `payments()`, `completedPayments()`, `totalPaid()`, `totalRefunded()`, `amountDue()`

**InvoiceController** (`app/Http/Controllers/Api/V1/InvoiceController.php`):
- Expected endpoints: CRUD for invoices
- This feature does NOT modify the controller, but references invoice IDs in payment endpoints

**InvoiceService** (`app/Service/InvoiceService.php`):
- Expected methods: creating invoices from time entries, status management
- This feature calls invoice status update methods from `PaymentService` after recording payments

**InvoiceLine Model** (if separate table):
- Expected: line item data for mapping to accounting system invoice lines
- Used by `QuickBooksService::pushInvoice()` and `XeroService::pushInvoice()` to map line items

**Invoice PDF Generation**:
- Expected: Gotenberg or DomPDF-based PDF generation
- This feature modifies the PDF template to include a "Pay Now" link/button when a payment integration is active

**Invoice Email Delivery**:
- Expected: email template for sending invoices
- This feature modifies the email template to include a "Pay Now" button linking to the Stripe Checkout or PayPal approval URL

### 2.2 Modifications to Invoice (Planned)

The `Invoice` model will be modified (not created) by this feature:

```php
// Added to Invoice model by Feature 14:

// New relationship methods
public function payments(): HasMany { ... }
public function completedPayments(): HasMany { ... }

// New computed methods
public function totalPaid(): int { ... }
public function totalRefunded(): int { ... }
public function amountDue(): int { ... }

// Status enum extension: add 'partially_paid'
```

### 2.3 Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| Feature 04 not complete before Feature 14 starts | Critical -- blocks all work | Plan Feature 14 sprints to begin after Feature 04 sprint 5 (payment tracking) at minimum |
| Invoice model schema changes after Feature 14 design | Medium -- may require migration updates | Review Invoice model schema during Feature 14 sprint 1 and adjust if needed |
| Invoice status enum not extensible | Low -- enum can be extended | Feature 04's status implementation should use a string column (not PHP enum) for extensibility |

---

## 3. Existing Client Model

### 3.1 Client Model

**File**: `app/Models/Client.php`

Key characteristics:
- Uses `HasUuids` trait (UUID primary keys)
- Uses `CustomAuditable` trait (audit logging)
- Relationships: `belongsTo` Organization, `hasMany` Projects
- Fields: `id`, `name`, `organization_id`, `is_archived`
- **No email, address, or tax fields** on the current `Client` model

### 3.2 Impact on Accounting Sync

The `Client` model is used for accounting client mapping. The `AccountingClientMapping` table maps Solidtime `client_id` to external customer IDs.

**Auto-matching**: The `AccountingSyncService::autoMatchClients()` method matches by `name` only (case-insensitive). Since the Client model has no email or tax ID, name is the only matching field available.

**Customer creation in accounting system**: When creating a new customer in QuickBooks/Xero from a Solidtime client, only the `name` is available. If Feature 04 adds billing address or email fields to `Client`, those should also be included in customer creation.

**No modification to Client model**: This feature reads Client data but does not modify the schema. The `accounting_client_mappings` table stores the mapping externally.

---

## 4. Existing Organization Model

### 4.1 Organization Model

**File**: `app/Models/Organization.php`

Key characteristics relevant to this feature:
- `currency` field -- used to validate payment currency matches
- Route model binding via `{organization}` parameter -- all controllers use this
- `check-organization-blocked` middleware prevents writes when subscription expired
- `BillingContract` service provides `canAccessPremiumFeatures()` for feature gating

### 4.2 Impact

- **Currency**: The organization's currency is used as the default for payment recording and Checkout Session creation. Invoices are created in this currency (from Feature 04), and payments must match.
- **Feature gating**: Payment integrations and accounting sync are Premium/Enterprise features. The `BillingContract::canAccessPremiumFeatures()` check should be applied before allowing integration connections.
- **No modification to Organization model**: This feature reads Organization data but does not modify the schema.

---

## 5. Controller Patterns

### 5.1 Base Controller

**File**: `app/Http/Controllers/Api/V1/Controller.php`

All authenticated API controllers extend this base, which provides:
```php
protected PermissionStore $permissionStore;  // Injected via constructor

protected function checkPermission(Organization $organization, string $permission): void
protected function checkAnyPermission(Organization $organization, array $permissions): void
protected function user(): User
protected function member(Organization $organization): Member
```

### 5.2 Controller Pattern Alignment

The `PaymentController` and `PaymentIntegrationController` follow the established pattern:
- Extend `Api\V1\Controller`
- Constructor DI of service classes
- Permission checks at the start of each method
- Organization resolved via route model binding
- JSON responses with `data` key

The `PaymentWebhookController` is a **departure from the standard pattern**:
- Does NOT extend `Api\V1\Controller` (no authentication)
- Does NOT use `auth:api` middleware
- Uses signature verification instead of Passport token authentication
- Extends Laravel's base `Controller` class directly

This is the correct approach for webhook endpoints. Stripe and PayPal send requests directly -- they do not have Solidtime API credentials.

### 5.3 Organization Injection

Organization is automatically resolved via route model binding from the `{organization}` route parameter. This works for all authenticated endpoints. Webhook endpoints do NOT have organization in the URL -- the organization is resolved from the webhook payload (via metadata or connected account ID).

---

## 6. Service Layer Patterns

### 6.1 Service Conventions

- Location: `app/Service/`
- Stateless classes (no constructor state beyond injected dependencies)
- Methods accept model instances (Organization, Member, Invoice) not IDs
- Return plain arrays or model instances
- Injected into controllers via constructor type-hints

### 6.2 Existing Service Examples

**BillableRateService** (`app/Service/BillableRateService.php`):
```php
class BillableRateService
{
    public function getBillableRateForTimeEntry(TimeEntry $timeEntry): ?int
    {
        // Multi-level rate lookup logic
    }
}
```

**TimeEntryAggregationService** (`app/Service/TimeEntryAggregationService.php`):
```php
class TimeEntryAggregationService
{
    public function getAggregatedTimeEntries(
        Organization $organization,
        Member $member,
        Carbon $start,
        Carbon $end,
        string $groupBy
    ): array { ... }
}
```

### 6.3 Service Pattern for This Feature

This feature introduces 6 service classes. They follow the established pattern but with two additions:

1. **External API clients**: `StripeService`, `PayPalService`, `QuickBooksService`, and `XeroService` each initialize an SDK client in the constructor. This is acceptable because the SDK clients are stateless (configured once, used for all calls).

2. **Orchestration service**: `AccountingSyncService` depends on both `QuickBooksService` and `XeroService`, injected via constructor DI. This is a new pattern in the codebase (a service depending on other services) but is standard Laravel practice.

---

## 7. Request Validation Patterns

### 7.1 Base Request

**File**: `app/Http/Requests/V1/BaseFormRequest.php`

All request classes extend this. It provides:
- Access to `$this->organization` via route model binding
- Standard authorization logic

### 7.2 Existing Validation Pattern

**Example from TimeEntryStoreRequest**:
```php
class TimeEntryStoreRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'project_id' => [
                'nullable', 'string', 'uuid',
                new ExistsEloquent(Project::class, null, function ($builder) {
                    $builder->whereBelongsTo($this->organization, 'organization');
                }),
            ],
            // ...
        ];
    }
}
```

### 7.3 Pattern Application

The `PaymentStoreRequest` uses `ExistsEloquent` to validate that the `invoice_id` belongs to the current organization:
```php
'invoice_id' => [
    'required', 'string', 'uuid',
    new ExistsEloquent(Invoice::class, null, function ($builder) {
        $builder->whereBelongsTo($this->organization, 'organization');
    }),
],
```

The `IntegrationClientMappingRequest` uses `ExistsEloquent` for `client_id` validation:
```php
'client_id' => [
    'required', 'string', 'uuid',
    new ExistsEloquent(Client::class, null, function ($builder) {
        $builder->whereBelongsTo($this->organization, 'organization');
    }),
],
```

---

## 8. Permission System

### 8.1 Permission Registration

**File**: `app/Providers/JetstreamServiceProvider.php`

Permissions are registered per-role in `configurePermissions()`. Per SF-08 (Shared Foundations), this feature uses the modular permission pattern:

**File**: `app/Permissions/PaymentPermissions.php`
```php
class PaymentPermissions
{
    public static function register(): void
    {
        // Add payment and integration permissions to each role
    }
}
```

### 8.2 Existing Permission Pattern

All roles (Employee, Manager, Admin, Owner) have increasing permission levels:
- **Employee**: `view:own`, `create:own` for most entities
- **Manager**: `view:all`, `create:own` + some `approve` permissions
- **Admin**: Full `view:all`, `create:all`, `manage` permissions
- **Owner**: Same as Admin

### 8.3 New Permission Mapping

| Permission | Employee | Manager | Admin | Owner |
|------------|:--------:|:-------:|:-----:|:-----:|
| `payments:view:own` | Yes | Yes | Yes | Yes |
| `payments:view:all` | No | Yes | Yes | Yes |
| `payments:create:own` | No | Yes | Yes | Yes |
| `payments:create:all` | No | No | Yes | Yes |
| `integrations:manage` | No | No | Yes | Yes |

Note: `integrations:manage` is restricted to Admin/Owner because it involves connecting financial accounts and managing sensitive credentials.

---

## 9. Queue and Job Infrastructure

### 9.1 Existing Queue Configuration

**File**: `config/queue.php`

Laravel's queue system is configured and available. The default driver is set via the `QUEUE_CONNECTION` environment variable (typically `redis` or `database` in production).

### 9.2 Existing Job Pattern

The codebase does not have many existing queued jobs (most operations are synchronous). However, Laravel's job infrastructure is fully available:

```php
// Standard job pattern
class ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 60, 300];
    public int $timeout = 30;

    public function __construct(
        private readonly WebhookEvent $webhookEvent
    ) {}

    public function handle(PaymentService $paymentService): void
    {
        // Process the webhook event
    }

    public function failed(Throwable $exception): void
    {
        // Mark event as failed, log alert
    }
}
```

### 9.3 Queue Configuration for This Feature

This feature introduces 3 dedicated queue names:
- `webhooks` -- for `ProcessWebhookJob` (priority processing)
- `accounting-sync` -- for `SyncInvoiceJob`, `SyncPaymentJob`, `RetryFailedSyncJob`
- `default` -- for `RefreshOAuthTokenJob`

Queue workers should be configured to process these queues with appropriate priorities:
```bash
php artisan queue:work --queue=webhooks,accounting-sync,default
```

### 9.4 Scheduled Tasks

**File**: `app/Console/Kernel.php` (or `routes/console.php` in newer Laravel)

Two scheduled tasks are added:
```php
// Refresh OAuth tokens that expire within 2 hours
$schedule->job(new RefreshOAuthTokenJob)->hourly();

// Retry failed accounting syncs (attempts < 3)
$schedule->job(new RetryFailedSyncJob)->everyFifteenMinutes();
```

---

## 10. Encryption Patterns

### 10.1 Laravel Encryption

**Available infrastructure**:
- `Crypt::encryptString()` / `Crypt::decryptString()` -- for encrypting arbitrary strings
- `encrypted` model cast -- automatically encrypts on write and decrypts on read
- `APP_KEY` environment variable -- the encryption key (AES-256-CBC)

### 10.2 Application in This Feature

All OAuth tokens (access tokens, refresh tokens) are stored encrypted using the `encrypted` model cast:

```php
protected $casts = [
    'access_token' => 'encrypted',
    'refresh_token' => 'encrypted',
];
```

This is transparent to the service layer. When a service reads `$integration->access_token`, it receives the decrypted value. When it writes `$integration->access_token = $newToken`, the value is automatically encrypted before storage.

### 10.3 Token Exposure Prevention

In addition to encryption at rest, tokens are hidden from JSON serialization:

```php
protected $hidden = ['access_token', 'refresh_token'];
```

This prevents tokens from appearing in:
- API responses (even if a developer accidentally returns the full model)
- Log entries (Laravel's model serialization for logging)
- Debug output

### 10.4 Key Rotation

If the `APP_KEY` is rotated, all encrypted tokens become unreadable. This is a known Laravel limitation. Mitigation: after key rotation, all integrations must be reconnected (OAuth flows re-run). This is documented in the environment variable documentation (PAY-046).

---

## 11. Frontend Store Patterns

### 11.1 Existing Pinia Store Pattern

**Example from `useTimeEntries.ts`**:
```typescript
export const useTimeEntriesStore = defineStore('timeEntries', () => {
    const items = ref<TimeEntry[]>([]);
    const isLoading = ref(false);

    async function fetchEntries() {
        isLoading.value = true;
        try {
            const response = await api.get('/time-entries', { params: { ... } });
            items.value = response.data.data;
        } finally {
            isLoading.value = false;
        }
    }

    return { items, isLoading, fetchEntries };
});
```

### 11.2 Pattern Application

The `usePaymentsStore` and `useIntegrationsStore` follow this pattern. Key differences:

1. **Multiple data collections**: `useIntegrationsStore` manages `paymentIntegrations`, `accountingIntegrations`, `clientMappings`, and `externalCustomers` -- multiple arrays in a single store. This is more complex than existing stores but follows the same ref-based pattern.

2. **Window redirect for OAuth**: The `connectProvider()` action triggers `window.location.href = redirectUrl` instead of a standard API call. This is necessary because OAuth flows require a full browser redirect to the provider's authorization page.

3. **Polling for sync status**: The sync logs page may need periodic polling to update sync status. This can use `setInterval` with `fetchSyncLogs()` or leverage `@tanstack/vue-query`'s `refetchInterval` if available.

### 11.3 API Client Pattern

**File**: `resources/js/packages/api/src/openapi.json.client.ts`

Auto-generated client from OpenAPI spec. After PAY-021 (OpenAPI update), the client provides typed methods for all 15 endpoints.

### 11.4 Organization Context

**File**: `resources/js/utils/useUser.ts`

`getCurrentOrganizationId()` returns the current organization UUID for API calls. All store actions use this for organization scoping.

---

## 12. Frontend Component Patterns

### 12.1 Page Component Pattern

**Example from `Time.vue`**:
```vue
<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import MainContainer from '@/packages/ui/src/MainContainer.vue';

onMounted(async () => {
    await store.fetchData();
});
</script>

<template>
    <AppLayout title="Time" data-testid="time_view">
        <MainContainer>
            <!-- Page content -->
        </MainContainer>
    </AppLayout>
</template>
```

The `Payments.vue`, `IntegrationSettings.vue`, and `SyncLogs.vue` pages follow this exact pattern.

The `PaymentSuccess.vue` and `PaymentFailure.vue` pages are **public pages** that do NOT use `AppLayout`. They use a minimal layout appropriate for external clients viewing payment confirmation.

### 12.2 UI Component Location

Feature-specific components live in:
- `resources/js/packages/ui/src/Payment/` -- payment-related components
- `resources/js/packages/ui/src/Integration/` -- integration-related components

Tests go in `__tests__/` subdirectory within each component directory.

### 12.3 Dialog/Modal Pattern

Dialog components (like `RecordPaymentDialog.vue` and `ClientMappingDialog.vue`) should follow any existing modal pattern in the codebase. Solidtime uses headless UI or a custom dialog component for modals. The dialog receives `open` and `@close` props/events.

### 12.4 Badge Components

`PaymentStatusBadge.vue` and `SyncStatusBadge.vue` are small display components that render colored badges based on status strings. These follow the pattern of existing status display components in the codebase.

### 12.5 Icon Usage

Icons imported from `@heroicons/vue/20/solid`:
```typescript
import { CreditCardIcon } from '@heroicons/vue/20/solid';
```

Used for the Payments sidebar navigation item.

---

## 13. Route Registration Patterns

### 13.1 API Routes

**File**: `routes/api.php`

API routes are registered inside nested middleware groups:
```php
Route::middleware(['auth:api', 'verified'])->group(function () {
    Route::name('v1.')->prefix('v1')->group(function () {
        // Feature route groups here
    });
});
```

Payment and integration routes follow this pattern (inside the auth group).

Webhook routes are **outside** the auth group:
```php
// After the auth group
Route::name('v1.webhooks.')->prefix('v1/webhooks')->group(static function (): void {
    Route::post('/stripe', [PaymentWebhookController::class, 'stripe'])->name('stripe');
    Route::post('/paypal', [PaymentWebhookController::class, 'paypal'])->name('paypal');
});
```

### 13.2 Web Routes

**File**: `routes/web.php`

Inertia page routes registered inside `auth:web` middleware:
```php
Route::middleware(['auth:web', 'verified'])->group(function () {
    Route::get('/payments', function () {
        return Inertia::render('Payments');
    })->name('payments');

    Route::get('/settings/integrations', function () {
        return Inertia::render('IntegrationSettings');
    })->name('integrations.settings');
});
```

The public payment link is registered outside the auth middleware:
```php
Route::get('/pay/{token}', [PaymentLinkController::class, 'redirect'])->name('payment.redirect');
```

---

## 14. Navigation Sidebar

**File**: `resources/js/Layouts/AppLayout.vue`

The sidebar uses `NavigationSidebarItem` components. The Payments item should be placed in the Invoicing section:

```vue
<!-- Existing Invoices item (from Feature 04) -->
<NavigationSidebarItem
    title="Invoices"
    :icon="DocumentTextIcon"
    :current="route().current('invoices')"
    :href="route('invoices')">
</NavigationSidebarItem>

<!-- NEW: Payments item (Feature 14) -->
<NavigationSidebarItem
    title="Payments"
    :icon="CreditCardIcon"
    :current="route().current('payments')"
    :href="route('payments')">
</NavigationSidebarItem>
```

Integration settings are accessible via Organization Settings (existing settings page). They do NOT get a separate top-level sidebar item.

---

## 15. Data Flow Diagrams

### 15.1 Stripe Payment Flow

```
Admin connects Stripe:
    -> IntegrationSettings.vue: Click "Connect Stripe"
    -> useIntegrationsStore.connectProvider('stripe')
        -> POST /api/v1/organizations/{org}/integrations/stripe/connect
        -> PaymentIntegrationController.connect()
            -> StripeService.getConnectUrl()
            -> Store state in session
            -> Return redirect_url
        -> Frontend: window.location.href = redirect_url
    -> Stripe OAuth authorization page
    -> User authorizes
    -> Stripe redirects to callback URL
        -> GET /api/v1/organizations/{org}/integrations/stripe/callback?code=xxx&state=xxx
        -> PaymentIntegrationController.callback()
            -> Verify state parameter
            -> StripeService.handleOAuthCallback(code)
                -> Exchange code for access token and account ID
            -> Create PaymentIntegration record (encrypted tokens)
            -> Redirect to /settings/integrations?status=connected

Client pays invoice:
    -> Client receives invoice email with "Pay Now" button
    -> Clicks button -> GET /pay/{signed_token}
    -> PaymentLinkController.redirect()
        -> Verify signed URL (valid, not expired)
        -> Look up invoice, check status (must be payable)
        -> Look up active payment integration for organization
        -> StripeService.createCheckoutSession(integration, invoice, successUrl, cancelUrl)
            -> Create Stripe Checkout Session on connected account
            -> Session metadata includes invoice_id, organization_id
        -> Redirect to Stripe Checkout URL
    -> Client completes payment on Stripe
    -> Stripe sends webhook:
        -> POST /api/v1/webhooks/stripe
        -> PaymentWebhookController.stripe()
            -> StripeService.verifyWebhookSignature() -> validates HMAC
            -> Check WebhookEvent for duplicate event_id
            -> Store WebhookEvent (status: 'received')
            -> Dispatch ProcessWebhookJob
            -> Return { received: true }
        -> ProcessWebhookJob runs:
            -> Update WebhookEvent status to 'processing'
            -> StripeService.handleWebhookEvent()
                -> handleCheckoutCompleted()
                    -> Extract invoice_id from metadata
                    -> PaymentService.recordWebhookPayment()
                        -> Check idempotency (provider_payment_id)
                        -> Create Payment record
                        -> updateInvoicePaymentStatus() -> invoice becomes 'paid'
                        -> If accounting sync active: dispatch SyncPaymentJob
            -> Update WebhookEvent status to 'processed'
```

### 15.2 Accounting Sync Flow

```
Invoice is sent (status changes to 'sent'):
    -> InvoiceService (Feature 04) updates status
    -> Event listener / observer checks for active accounting integration
    -> If auto_sync_invoices is enabled:
        -> Dispatch SyncInvoiceJob(integration, invoice)
    -> SyncInvoiceJob runs:
        -> AccountingSyncService.syncInvoice(integration, invoice)
            -> Create AccountingSyncLog (status: 'pending')
            -> Look up AccountingClientMapping for invoice's client
            -> If no mapping: mark sync as 'failed' with "Client not mapped" error
            -> If mapping exists:
                -> QuickBooksService.pushInvoice() or XeroService.pushInvoice()
                    -> Refresh token if expired
                    -> Map invoice data to provider format
                    -> POST to provider API
                    -> Return external invoice ID
                -> Update AccountingSyncLog (status: 'completed', external_id: ...)
            -> On API error:
                -> Update AccountingSyncLog (status: 'failed', error_message: ...)
                -> If attempts < 3: release job back to queue with backoff

Payment is recorded:
    -> PaymentService.recordPayment() or recordWebhookPayment()
    -> If accounting sync active and auto_sync_payments enabled:
        -> Look up invoice's AccountingSyncLog for external_id
        -> If invoice not synced yet: skip payment sync (will be caught by retry)
        -> If invoice synced:
            -> Dispatch SyncPaymentJob(integration, payment)
            -> AccountingSyncService.syncPayment()
                -> QuickBooksService.pushPayment() or XeroService.pushPayment()
                -> Link payment to external invoice
```

### 15.3 Manual Payment Recording Flow

```
Admin records manual payment:
    -> InvoiceDetail.vue: Click "Record Payment"
    -> RecordPaymentDialog.vue opens
        -> Amount pre-filled with invoice.amountDue
        -> Admin enters: amount, date, method (Check/Bank Transfer/Cash/Other), reference, notes
        -> Click "Save"
    -> usePaymentsStore.recordPayment(data)
        -> POST /api/v1/organizations/{org}/payments
        -> PaymentController.store()
            -> checkAnyPermission('payments:create:own' or 'payments:create:all')
            -> PaymentStoreRequest validates input
            -> PaymentService.recordPayment()
                -> Validate invoice is payable (sent/viewed/overdue/partially_paid)
                -> Create Payment record (method: 'manual', status: 'completed')
                -> updateInvoicePaymentStatus()
                    -> SUM completed payments
                    -> Update invoice status (paid/partially_paid)
                -> If accounting sync active: dispatch SyncPaymentJob
            -> Return Payment JSON (201)
        -> Store: add payment to list, update summary
    -> Dialog closes, payment appears in payment history
```

---

## 16. File Modification Risk Assessment

### 16.1 Risk Matrix

| File | Change Type | Risk | Rationale |
|------|------------|:----:|-----------|
| `routes/api.php` | Add 3 route groups (payments, integrations, webhooks) | Low | Appending new groups, no existing code modified |
| `routes/web.php` | Add 3 Inertia routes + 1 public route | Low | Appending routes, no existing routes modified |
| `resources/js/Layouts/AppLayout.vue` | Add 1 nav item | Low | Adding one `NavigationSidebarItem`, no existing items modified |
| `app/Models/Invoice.php` | Add 5 methods/relations | Medium | Modifying Feature 04's model. Methods are additive (new relations/computed methods), not modifying existing logic. Risk is coordination with Feature 04 developer. |
| `app/Providers/AppServiceProvider.php` | Add morph map entries | Low | Adding 2 entries to morph map array. No existing entries modified. |
| `config/services.php` | Add 4 provider config blocks | Low | Appending new config keys. No existing keys modified. |
| `app/Providers/JetstreamServiceProvider.php` | Add 1 line to call `PaymentPermissions::register()` | Low | Single line addition per SF-08 modular pattern. |
| `app/Console/Kernel.php` | Add 2 scheduled tasks | Low | Appending schedule entries. No existing schedules modified. |
| `openapi.json` | Add 15 endpoint definitions | Low | Appending new paths and schemas. No existing paths modified. |
| `composer.json` | Add 4 packages | Low | Adding dependencies, no existing deps modified |

### 16.2 Merge Conflict Assessment

**Risk: LOW** -- This feature creates 68 new files and modifies 6 existing files. All modifications are additive (appending new route groups, new methods, new config entries). The only medium-risk file is `Invoice.php` (Feature 04), which requires coordination.

**Coordination required with Feature 04**:
- The `Invoice` model must be on `main` before this feature branches
- The `payments()` relation and computed methods must be added carefully to avoid conflicts with any ongoing Feature 04 work
- Recommended: branch `feature/payments-accounting-sync` from `main` only after Feature 04 is fully merged

### 16.3 Downstream Conflict Warning

This feature does not create significant downstream conflict risk. The new models, services, and controllers are self-contained. Future features that may touch these files:

- **Automated payment reminders** (future): May modify `PaymentService` to add reminder scheduling
- **Two-way accounting sync** (future): May significantly modify `AccountingSyncService`, `QuickBooksService`, `XeroService`
- **FreshBooks integration** (future): Would add a new service class but not modify existing ones

### 16.4 External Package Compatibility

| Package | Laravel 11 Compatible | PHP 8.2+ Compatible | Conflict Risk |
|---------|:---------------------:|:-------------------:|:-------------:|
| `stripe/stripe-php` ^14.0 | Yes | Yes | None |
| `paypal/paypal-server-sdk` ^1.0 | Yes | Yes | None |
| `quickbooks/v3-php-sdk` ^6.0 | Yes | Yes | Low (verify OAuth2 library compatibility) |
| `xeroapi/xero-php-oauth2` ^5.0 | Yes | Yes | None |

All four packages are actively maintained and widely used in the Laravel ecosystem. No known conflicts with existing Solidtime dependencies.
