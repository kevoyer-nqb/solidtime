# Feature 14: Online Payments and Accounting Sync -- Technical Architecture

**Date**: 2026-02-09
**Status**: Draft
**Feature Branch**: `feature/payments-accounting-sync` (from `main`)
**Task Prefix**: `PAY-` (per task assignments)

---

## Executive Summary

This document provides the complete technical architecture for the **Online Payments and Accounting Sync** feature. The feature enables organizations to accept online invoice payments via Stripe and PayPal, record manual payments, and synchronize invoice/payment data with QuickBooks Online and Xero accounting systems.

**Key Architectural Decisions**:
- **6 new database tables** -- `payment_integrations`, `accounting_integrations`, `accounting_client_mappings`, `payments`, `accounting_sync_logs`, `webhook_events`
- **6 new Eloquent models** with encrypted token storage, polymorphic sync logging, and organization scoping
- **6 new service classes** -- `PaymentService`, `StripeService`, `PayPalService`, `QuickBooksService`, `XeroService`, `AccountingSyncService`
- **3 new controllers** -- `PaymentController`, `PaymentIntegrationController`, `PaymentWebhookController`
- **15 API endpoints** including 4 payment endpoints, 8 integration endpoints, 2 webhook endpoints, 1 public payment link
- **5 queued jobs** -- `ProcessWebhookJob`, `SyncInvoiceJob`, `SyncPaymentJob`, `RefreshOAuthTokenJob`, `RetryFailedSyncJob`
- **5 new permissions** -- `payments:view:own`, `payments:view:all`, `payments:create:own`, `payments:create:all`, `integrations:manage`
- **PCI SAQ-A compliance** -- no card data touches Solidtime servers; all payment processing delegated to Stripe Checkout / PayPal hosted pages
- **One-way accounting sync** -- Solidtime pushes to QuickBooks/Xero; no inbound sync in v1
- **Hard dependency on Feature 04 (Invoicing)** -- the `Invoice` model must exist before this feature can be built

---

## Table of Contents

1. [Data Model Design](#1-data-model-design)
2. [API Contract](#2-api-contract)
3. [Service Layer](#3-service-layer)
4. [Controller Layer](#4-controller-layer)
5. [Request Validation](#5-request-validation)
6. [Webhook Handling](#6-webhook-handling)
7. [Frontend Architecture](#7-frontend-architecture)
8. [Permission Matrix](#8-permission-matrix)
9. [Security Strategy](#9-security-strategy)
10. [Performance Strategy](#10-performance-strategy)
11. [Integration Points](#11-integration-points)
12. [File Manifest](#12-file-manifest)

---

## 1. Data Model Design

### 1.1 New Models (6)

This feature introduces 6 new Eloquent models, all using UUID primary keys (`HasUuids` trait) and audit logging (`CustomAuditable` trait).

### 1.2 PaymentIntegration Model

**File**: `app/Models/PaymentIntegration.php`

Stores Stripe and PayPal connection credentials per organization. One record per provider per organization.

```php
class PaymentIntegration extends Model
{
    use HasUuids, HasFactory, CustomAuditable;

    protected $fillable = [
        'organization_id',
        'provider',             // 'stripe' | 'paypal'
        'provider_account_id',  // Stripe account ID or PayPal merchant ID
        'access_token',         // Encrypted via cast
        'refresh_token',        // Encrypted via cast
        'token_expires_at',
        'settings',             // JSON: provider-specific config
        'is_active',
        'connected_at',
        'disconnected_at',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'settings' => 'array',
        'is_active' => 'boolean',
        'connected_at' => 'datetime',
        'disconnected_at' => 'datetime',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    // Relationships
    public function organization(): BelongsTo { ... }

    // Scopes
    public function scopeActive(Builder $query): Builder { ... }
    public function scopeForProvider(Builder $query, string $provider): Builder { ... }
}
```

**Key details**:
- `access_token` and `refresh_token` use Laravel's `encrypted` cast, which automatically encrypts on write and decrypts on read using `Crypt::encryptString()` / `Crypt::decryptString()`
- `$hidden` array prevents tokens from leaking into JSON serialization
- Unique constraint on `(organization_id, provider)` enforces one connection per provider per organization

### 1.3 AccountingIntegration Model

**File**: `app/Models/AccountingIntegration.php`

Stores QuickBooks Online and Xero connection credentials per organization.

```php
class AccountingIntegration extends Model
{
    use HasUuids, HasFactory, CustomAuditable;

    protected $fillable = [
        'organization_id',
        'provider',                 // 'quickbooks' | 'xero'
        'provider_tenant_id',       // QBO company ID or Xero tenant ID
        'provider_tenant_name',     // Human-readable company/tenant name
        'access_token',             // Encrypted
        'refresh_token',            // Encrypted
        'token_expires_at',
        'refresh_token_expires_at',
        'settings',                 // JSON: { auto_sync_invoices, auto_sync_payments }
        'is_active',
        'connected_at',
        'disconnected_at',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'refresh_token_expires_at' => 'datetime',
        'settings' => 'array',
        'is_active' => 'boolean',
        'connected_at' => 'datetime',
        'disconnected_at' => 'datetime',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    // Relationships
    public function organization(): BelongsTo { ... }
    public function clientMappings(): HasMany { ... }
    public function syncLogs(): HasMany { ... }

    // Helpers
    public function isTokenExpired(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }

    public function isRefreshTokenExpired(): bool
    {
        return $this->refresh_token_expires_at !== null && $this->refresh_token_expires_at->isPast();
    }
}
```

**Key details**:
- `refresh_token_expires_at` tracks the separate refresh token lifetime (QuickBooks: 100 days, Xero: 60 days)
- `settings` JSON stores per-integration configuration like `auto_sync_invoices` and `auto_sync_payments` booleans
- `isTokenExpired()` and `isRefreshTokenExpired()` helpers used by `RefreshOAuthTokenJob` to determine when to proactively refresh

### 1.4 AccountingClientMapping Model

**File**: `app/Models/AccountingClientMapping.php`

Maps Solidtime `Client` records to external accounting system customers/contacts.

```php
class AccountingClientMapping extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'accounting_integration_id',
        'client_id',
        'external_customer_id',
        'external_customer_name',
    ];

    // Relationships
    public function accountingIntegration(): BelongsTo { ... }
    public function client(): BelongsTo { ... }
}
```

**Key details**:
- Dual unique constraints: `(accounting_integration_id, client_id)` and `(accounting_integration_id, external_customer_id)` prevent duplicate mappings
- `external_customer_name` is a cached display name to avoid API calls when rendering the mapping UI

### 1.5 Payment Model

**File**: `app/Models/Payment.php`

Records all payments (online and manual) against invoices.

```php
class Payment extends Model
{
    use HasUuids, HasFactory, CustomAuditable;

    protected $fillable = [
        'organization_id',
        'invoice_id',
        'amount',                   // Integer, smallest currency unit (cents)
        'currency',                 // ISO 4217 (e.g., 'USD', 'EUR')
        'payment_method',           // 'stripe' | 'paypal' | 'manual'
        'payment_type',             // 'payment' | 'refund'
        'status',                   // 'pending' | 'completed' | 'failed' | 'voided'
        'provider_payment_id',      // Stripe payment_intent ID, PayPal capture ID
        'provider_transaction_id',  // Additional provider reference
        'reference_number',         // Manual payment reference (check #, etc.)
        'notes',
        'paid_at',
        'voided_at',
        'voided_by',
        'recorded_by',
    ];

    protected $casts = [
        'amount' => 'integer',
        'paid_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    // Relationships
    public function organization(): BelongsTo { ... }
    public function invoice(): BelongsTo { ... }
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
    public function voidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
    public function syncLogs(): MorphMany
    {
        return $this->morphMany(AccountingSyncLog::class, 'syncable');
    }

    // Scopes
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }
    public function scopeForInvoice(Builder $query, string $invoiceId): Builder
    {
        return $query->where('invoice_id', $invoiceId);
    }
}
```

**Key details**:
- `amount` stored as integer in smallest currency unit (e.g., cents for USD). This avoids floating-point rounding issues.
- `payment_type` distinguishes between payments and refunds. Refunds have negative semantic (they reduce the total paid) but the `amount` field is always positive. The `payment_type` field indicates direction.
- `provider_payment_id` is used for idempotency checks on webhook processing -- if a payment with this provider ID already exists, the webhook is a duplicate.
- `syncLogs()` is a polymorphic relationship: `AccountingSyncLog` can track sync status for both `Payment` and `Invoice` records.

### 1.6 AccountingSyncLog Model

**File**: `app/Models/AccountingSyncLog.php`

Tracks the sync status of invoices and payments pushed to accounting systems.

```php
class AccountingSyncLog extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'accounting_integration_id',
        'syncable_type',            // 'invoice' | 'payment'
        'syncable_id',              // UUID of the Invoice or Payment
        'external_id',              // External system's ID for the entity
        'sync_action',              // 'create' | 'update'
        'status',                   // 'pending' | 'completed' | 'failed'
        'error_message',
        'attempts',
        'last_attempted_at',
        'completed_at',
        'request_payload',          // JSON, stored for debugging
        'response_payload',         // JSON, stored for debugging
    ];

    protected $casts = [
        'attempts' => 'integer',
        'last_attempted_at' => 'datetime',
        'completed_at' => 'datetime',
        'request_payload' => 'array',
        'response_payload' => 'array',
    ];

    // Relationships
    public function accountingIntegration(): BelongsTo { ... }
    public function syncable(): MorphTo { ... }

    // Scopes
    public function scopeFailed(Builder $query): Builder { ... }
    public function scopePending(Builder $query): Builder { ... }
    public function scopeRetryable(Builder $query): Builder
    {
        return $query->where('status', 'failed')->where('attempts', '<', 3);
    }
}
```

**Key details**:
- `syncable_type` and `syncable_id` form a polymorphic relationship. `syncable_type` is one of `'invoice'` or `'payment'` (not the full class path -- Laravel's morph map is configured).
- `request_payload` and `response_payload` store the raw API request/response for debugging failed syncs. These are nullable and only populated when sync fails or for the most recent attempt.
- `scopeRetryable()` identifies failed syncs that have not exceeded the 3-attempt retry limit.

### 1.7 WebhookEvent Model

**File**: `app/Models/WebhookEvent.php`

Records all received webhook events for idempotent processing and audit trail.

```php
class WebhookEvent extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'provider',         // 'stripe' | 'paypal'
        'event_id',         // Provider's unique event ID
        'event_type',       // e.g., 'checkout.session.completed'
        'payload',          // Full webhook payload (JSON)
        'status',           // 'received' | 'processing' | 'processed' | 'failed'
        'processed_at',
        'error_message',
        'attempts',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
        'attempts' => 'integer',
    ];

    // Unique constraint: (provider, event_id) prevents duplicate event storage
}
```

**Key details**:
- The unique constraint on `(provider, event_id)` is the first line of defense against duplicate webhook processing. Before inserting, the system checks for existing records with the same provider + event_id.
- `status` transitions: `received` -> `processing` -> `processed` (success) or `failed` (error). The `processing` state prevents concurrent workers from processing the same event.

### 1.8 Enum Definitions

**File**: `app/Enums/PaymentMethod.php`
```php
enum PaymentMethod: string
{
    case STRIPE = 'stripe';
    case PAYPAL = 'paypal';
    case MANUAL = 'manual';
}
```

**File**: `app/Enums/PaymentStatus.php`
```php
enum PaymentStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case VOIDED = 'voided';
}
```

**File**: `app/Enums/PaymentType.php`
```php
enum PaymentType: string
{
    case PAYMENT = 'payment';
    case REFUND = 'refund';
}
```

**File**: `app/Enums/PaymentProvider.php`
```php
enum PaymentProvider: string
{
    case STRIPE = 'stripe';
    case PAYPAL = 'paypal';
    case QUICKBOOKS = 'quickbooks';
    case XERO = 'xero';

    public function isPaymentProvider(): bool
    {
        return in_array($this, [self::STRIPE, self::PAYPAL]);
    }

    public function isAccountingProvider(): bool
    {
        return in_array($this, [self::QUICKBOOKS, self::XERO]);
    }
}
```

**File**: `app/Enums/SyncStatus.php`
```php
enum SyncStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
```

### 1.9 Database Migrations

6 migration files, using date prefix `2026_03_14_` (Feature 14 allocation following the pattern from SHARED-FOUNDATIONS.md):

| # | File | Table |
|---|------|-------|
| 1 | `2026_03_14_000001_create_payment_integrations_table.php` | `payment_integrations` |
| 2 | `2026_03_14_000002_create_accounting_integrations_table.php` | `accounting_integrations` |
| 3 | `2026_03_14_000003_create_accounting_client_mappings_table.php` | `accounting_client_mappings` |
| 4 | `2026_03_14_000004_create_payments_table.php` | `payments` |
| 5 | `2026_03_14_000005_create_accounting_sync_logs_table.php` | `accounting_sync_logs` |
| 6 | `2026_03_14_000006_create_webhook_events_table.php` | `webhook_events` |

**Index Strategy**:

The `payments` table has 4 indexes:
- `idx_payments_invoice_id` -- filter payments by invoice
- `idx_payments_organization_id` -- organization-scoped queries
- `idx_payments_provider_payment_id` -- idempotency lookups during webhook processing
- `idx_payments_status` -- status-based filtering

The `accounting_sync_logs` table has 3 indexes:
- `idx_accounting_sync_logs_syncable` -- polymorphic lookup `(syncable_type, syncable_id)`
- `idx_accounting_sync_logs_status` -- retry queue queries
- `idx_accounting_sync_logs_integration` -- per-integration log queries

The `webhook_events` table has 2 indexes:
- `idx_webhook_events_status` -- queue processing queries
- `idx_webhook_events_provider_event` -- idempotency lookups `(provider, event_id)`

### 1.10 Model Relationship to Invoice (Feature 04)

The `Invoice` model (from Feature 04) is modified to add a `payments` relationship:

```php
// In app/Models/Invoice.php (MODIFIED, not new)
public function payments(): HasMany
{
    return $this->hasMany(Payment::class);
}

public function completedPayments(): HasMany
{
    return $this->hasMany(Payment::class)->where('status', 'completed');
}

public function totalPaid(): int
{
    return $this->completedPayments()
        ->where('payment_type', 'payment')
        ->sum('amount');
}

public function totalRefunded(): int
{
    return $this->completedPayments()
        ->where('payment_type', 'refund')
        ->sum('amount');
}

public function amountDue(): int
{
    return max(0, $this->total - $this->totalPaid() + $this->totalRefunded());
}
```

### 1.11 Morph Map Configuration

**File**: `app/Providers/AppServiceProvider.php` (MODIFIED)

```php
use Illuminate\Database\Eloquent\Relations\Relation;

Relation::enforceMorphMap([
    'invoice' => \App\Models\Invoice::class,
    'payment' => \App\Models\Payment::class,
]);
```

This ensures `syncable_type` stores `'invoice'` or `'payment'` rather than the full class path.

---

## 2. API Contract

### 2.1 Route Registration

**File**: `routes/api.php` (inside existing `auth:api` + `verified` middleware group)

```php
// Payment routes (authenticated)
Route::name('payments.')->prefix('/organizations/{organization}')->group(static function (): void {
    Route::get('/payments', [PaymentController::class, 'index'])->name('index');
    Route::post('/payments', [PaymentController::class, 'store'])
        ->name('store')
        ->middleware('check-organization-blocked');
    Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('show');
    Route::post('/payments/{payment}/void', [PaymentController::class, 'void'])
        ->name('void')
        ->middleware('check-organization-blocked');
});

// Integration routes (authenticated)
Route::name('integrations.')->prefix('/organizations/{organization}')->group(static function (): void {
    Route::get('/integrations', [PaymentIntegrationController::class, 'index'])->name('index');
    Route::post('/integrations/{provider}/connect', [PaymentIntegrationController::class, 'connect'])
        ->name('connect')
        ->middleware('check-organization-blocked');
    Route::get('/integrations/{provider}/callback', [PaymentIntegrationController::class, 'callback'])
        ->name('callback');
    Route::delete('/integrations/{provider}', [PaymentIntegrationController::class, 'disconnect'])
        ->name('disconnect')
        ->middleware('check-organization-blocked');
    Route::get('/integrations/{provider}/client-mappings', [PaymentIntegrationController::class, 'clientMappings'])
        ->name('client-mappings');
    Route::put('/integrations/client-mappings/{mapping}', [PaymentIntegrationController::class, 'updateClientMapping'])
        ->name('update-client-mapping')
        ->middleware('check-organization-blocked');
    Route::post('/integrations/sync-invoice', [PaymentIntegrationController::class, 'syncInvoice'])
        ->name('sync-invoice')
        ->middleware('check-organization-blocked');
    Route::get('/integrations/sync-logs', [PaymentIntegrationController::class, 'syncLogs'])
        ->name('sync-logs');
});
```

**File**: `routes/api.php` (outside auth middleware -- no authentication)

```php
// Webhook routes (no auth middleware -- signature verification handled in controller)
Route::name('webhooks.')->prefix('/v1/webhooks')->group(static function (): void {
    Route::post('/stripe', [PaymentWebhookController::class, 'stripe'])->name('stripe');
    Route::post('/paypal', [PaymentWebhookController::class, 'paypal'])->name('paypal');
});
```

**File**: `routes/web.php` (public route)

```php
// Public payment link (signed URL, no auth required)
Route::get('/pay/{token}', [PaymentLinkController::class, 'redirect'])->name('payment.redirect');
```

Route names resolve to:
- `api.v1.payments.index`
- `api.v1.payments.store`
- `api.v1.payments.show`
- `api.v1.payments.void`
- `api.v1.integrations.index`
- `api.v1.integrations.connect`
- `api.v1.integrations.callback`
- `api.v1.integrations.disconnect`
- `api.v1.integrations.client-mappings`
- `api.v1.integrations.update-client-mapping`
- `api.v1.integrations.sync-invoice`
- `api.v1.integrations.sync-logs`
- `api.v1.webhooks.stripe`
- `api.v1.webhooks.paypal`
- `payment.redirect`

### 2.2 Endpoint Signatures

| Method | Path | Controller Method | Request Class | Permission |
|--------|------|-------------------|---------------|------------|
| GET | `/payments` | `PaymentController::index()` | `PaymentIndexRequest` | `payments:view:own` or `payments:view:all` |
| POST | `/payments` | `PaymentController::store()` | `PaymentStoreRequest` | `payments:create:own` or `payments:create:all` |
| GET | `/payments/{payment}` | `PaymentController::show()` | -- | `payments:view:own` or `payments:view:all` |
| POST | `/payments/{payment}/void` | `PaymentController::void()` | `PaymentVoidRequest` | `payments:create:all` |
| GET | `/integrations` | `PaymentIntegrationController::index()` | -- | `integrations:manage` |
| POST | `/integrations/{provider}/connect` | `PaymentIntegrationController::connect()` | `PaymentIntegrationConnectRequest` | `integrations:manage` |
| GET | `/integrations/{provider}/callback` | `PaymentIntegrationController::callback()` | -- | `integrations:manage` (via state token) |
| DELETE | `/integrations/{provider}` | `PaymentIntegrationController::disconnect()` | -- | `integrations:manage` |
| GET | `/integrations/{provider}/client-mappings` | `PaymentIntegrationController::clientMappings()` | -- | `integrations:manage` |
| PUT | `/integrations/client-mappings/{mapping}` | `PaymentIntegrationController::updateClientMapping()` | `IntegrationClientMappingRequest` | `integrations:manage` |
| POST | `/integrations/sync-invoice` | `PaymentIntegrationController::syncInvoice()` | `IntegrationSyncInvoiceRequest` | `integrations:manage` |
| GET | `/integrations/sync-logs` | `PaymentIntegrationController::syncLogs()` | `IntegrationSyncLogRequest` | `integrations:manage` |
| POST | `/webhooks/stripe` | `PaymentWebhookController::stripe()` | -- | None (signature verified) |
| POST | `/webhooks/paypal` | `PaymentWebhookController::paypal()` | -- | None (signature verified) |
| GET | `/pay/{token}` | `PaymentLinkController::redirect()` | -- | None (signed URL) |

### 2.3 Response Shapes

**GET /payments** (paginated list):
```json
{
  "data": [
    {
      "id": "uuid",
      "invoice_id": "uuid",
      "invoice_number": "INV-001",
      "client_name": "Acme Corp",
      "amount": 15000,
      "currency": "USD",
      "payment_method": "stripe",
      "payment_type": "payment",
      "status": "completed",
      "provider_payment_id": "pi_abc123",
      "reference_number": null,
      "notes": null,
      "paid_at": "2026-02-09T10:30:00Z",
      "voided_at": null,
      "recorded_by": null,
      "sync_status": "completed"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 25,
    "total": 72
  }
}
```

**POST /payments** (record manual payment):
```json
{
  "data": {
    "id": "uuid",
    "invoice_id": "uuid",
    "amount": 5000,
    "currency": "USD",
    "payment_method": "manual",
    "payment_type": "payment",
    "status": "completed",
    "reference_number": "CHK-12345",
    "notes": "Received via check",
    "paid_at": "2026-02-09T00:00:00Z",
    "recorded_by": { "id": "uuid", "name": "John Doe" }
  }
}
```

**GET /integrations**:
```json
{
  "data": {
    "payment_integrations": [
      {
        "id": "uuid",
        "provider": "stripe",
        "provider_account_id": "acct_abc123",
        "is_active": true,
        "connected_at": "2026-01-15T08:00:00Z",
        "settings": {}
      }
    ],
    "accounting_integrations": [
      {
        "id": "uuid",
        "provider": "quickbooks",
        "provider_tenant_id": "12345",
        "provider_tenant_name": "Acme Corp LLC",
        "is_active": true,
        "connected_at": "2026-01-20T14:00:00Z",
        "settings": {
          "auto_sync_invoices": true,
          "auto_sync_payments": true
        }
      }
    ]
  }
}
```

**POST /integrations/{provider}/connect**:
```json
{
  "data": {
    "redirect_url": "https://connect.stripe.com/oauth/authorize?client_id=...&state=..."
  }
}
```

**GET /integrations/{provider}/client-mappings**:
```json
{
  "data": {
    "mappings": [
      {
        "id": "uuid",
        "accounting_integration_id": "uuid",
        "client_id": "uuid",
        "client_name": "Acme Corp",
        "external_customer_id": "123",
        "external_customer_name": "Acme Corporation"
      }
    ],
    "unmapped_clients": [
      { "id": "uuid", "name": "Beta Inc" }
    ],
    "external_customers": [
      { "id": "456", "name": "Beta Industries", "email": "ap@beta.com" }
    ]
  }
}
```

**POST /integrations/sync-invoice**:
```json
{
  "data": {
    "message": "Sync queued",
    "sync_log_id": "uuid"
  }
}
```

**Webhook responses** (all providers):
```json
{ "received": true }
```

---

## 3. Service Layer

### 3.1 PaymentService

**File**: `app/Service/PaymentService.php`

Core payment business logic. Stateless service, injected into `PaymentController`.

```php
class PaymentService
{
    /**
     * Record a manual payment against an invoice.
     * Validates invoice status, creates payment record, updates invoice status.
     */
    public function recordPayment(
        Organization $organization,
        Invoice $invoice,
        int $amount,
        string $paymentMethod,
        ?string $referenceNumber,
        ?string $notes,
        Carbon $paidAt,
        User $recordedBy
    ): Payment { ... }

    /**
     * Record a payment from a webhook event (Stripe/PayPal).
     * Uses provider_payment_id for idempotency.
     * Returns null if payment already recorded (duplicate webhook).
     */
    public function recordWebhookPayment(
        string $providerPaymentId,
        string $invoiceId,
        int $amount,
        string $currency,
        string $paymentMethod,
        ?string $providerTransactionId,
        Carbon $paidAt
    ): ?Payment { ... }

    /**
     * Record a refund from a webhook event.
     * Creates a payment record with type='refund'.
     */
    public function recordRefund(
        string $providerPaymentId,
        string $originalProviderPaymentId,
        int $amount,
        string $currency,
        string $paymentMethod
    ): ?Payment { ... }

    /**
     * Void a manually-recorded payment.
     * Online payments (Stripe/PayPal) cannot be voided here -- must use provider dashboard.
     */
    public function voidPayment(
        Payment $payment,
        User $voidedBy,
        ?string $reason
    ): Payment { ... }

    /**
     * Recalculate and update invoice status based on total payments.
     * Called after every payment record/void/refund.
     *
     * Status transitions:
     * - total_paid >= invoice_total -> 'paid'
     * - total_paid > 0 && < invoice_total -> 'partially_paid'
     * - total_paid == 0 -> revert to previous status ('sent' or 'overdue')
     */
    public function updateInvoicePaymentStatus(Invoice $invoice): void { ... }

    /**
     * Get payment summary for an invoice.
     */
    public function getPaymentSummary(Invoice $invoice): array { ... }
}
```

**Key implementation details**:
- `recordPayment()` validates that the invoice is in a payable status (`sent`, `viewed`, `overdue`, `partially_paid`) before creating the payment record
- `recordWebhookPayment()` checks for existing payment with the same `provider_payment_id` before creating. Returns `null` on duplicate (idempotent).
- `updateInvoicePaymentStatus()` uses database-level `SUM()` to calculate total paid/refunded, then updates the invoice status accordingly. Uses `lockForUpdate()` to prevent concurrent updates from corrupting the total.
- After any status change, if an accounting integration is active with `auto_sync_payments`, a `SyncPaymentJob` is dispatched.

### 3.2 StripeService

**File**: `app/Service/StripeService.php`

Handles all Stripe-specific API interactions.

```php
class StripeService
{
    private StripeClient $client;

    public function __construct()
    {
        $this->client = new StripeClient(config('services.stripe.secret'));
    }

    /**
     * Generate the Stripe Connect OAuth authorization URL.
     */
    public function getConnectUrl(Organization $organization, string $state): string { ... }

    /**
     * Exchange OAuth code for access token and connected account ID.
     */
    public function handleOAuthCallback(string $code): array { ... }

    /**
     * Create a Stripe Checkout Session for an invoice payment.
     * Returns the Checkout Session URL.
     */
    public function createCheckoutSession(
        PaymentIntegration $integration,
        Invoice $invoice,
        string $successUrl,
        string $cancelUrl
    ): string { ... }

    /**
     * Verify Stripe webhook signature.
     * Throws exception if invalid.
     */
    public function verifyWebhookSignature(string $payload, string $signature): StripeEvent { ... }

    /**
     * Process a Stripe webhook event.
     * Dispatches to specific handlers based on event type.
     */
    public function handleWebhookEvent(StripeEvent $event): void { ... }

    /**
     * Process a checkout.session.completed event.
     */
    private function handleCheckoutCompleted(StripeEvent $event): void { ... }

    /**
     * Process a charge.refunded event.
     */
    private function handleChargeRefunded(StripeEvent $event): void { ... }

    /**
     * Process an account.application.deauthorized event.
     */
    private function handleDeauthorized(StripeEvent $event): void { ... }
}
```

**Key implementation details**:
- Stripe Connect Standard flow is used. Solidtime acts as the platform; connected accounts are the organizations' Stripe accounts.
- `createCheckoutSession()` creates the session on the connected account using `stripe_account` header. The session amount is set to the invoice's `amount_due` (not total, to support partial payment scenarios in future).
- Checkout Session metadata includes `invoice_id` and `organization_id` for webhook correlation.
- The webhook secret is stored in `STRIPE_WEBHOOK_SECRET` environment variable.

### 3.3 PayPalService

**File**: `app/Service/PayPalService.php`

Handles all PayPal-specific API interactions.

```php
class PayPalService
{
    /**
     * Generate the PayPal OAuth authorization URL.
     */
    public function getAuthorizationUrl(Organization $organization, string $state): string { ... }

    /**
     * Exchange OAuth code for access token and merchant ID.
     */
    public function handleOAuthCallback(string $code): array { ... }

    /**
     * Create a PayPal Order for an invoice payment.
     * Returns the PayPal approval URL.
     */
    public function createOrder(
        PaymentIntegration $integration,
        Invoice $invoice,
        string $returnUrl,
        string $cancelUrl
    ): string { ... }

    /**
     * Verify PayPal webhook signature.
     */
    public function verifyWebhookSignature(
        array $headers,
        string $payload
    ): bool { ... }

    /**
     * Process a PayPal webhook event.
     */
    public function handleWebhookEvent(array $event): void { ... }

    /**
     * Refresh an expired access token.
     */
    public function refreshAccessToken(PaymentIntegration $integration): void { ... }
}
```

**Key implementation details**:
- PayPal uses webhook ID-based verification (different from Stripe's HMAC approach)
- PayPal Orders API v2 is used for payment creation
- PayPal pending payments (eCheck) are handled by creating a payment record with `status='pending'` and updating to `completed` when `PAYMENT.CAPTURE.COMPLETED` arrives

### 3.4 QuickBooksService

**File**: `app/Service/QuickBooksService.php`

Handles all QuickBooks Online API interactions.

```php
class QuickBooksService
{
    /**
     * Generate the Intuit OAuth 2.0 authorization URL.
     */
    public function getAuthorizationUrl(Organization $organization, string $state): string { ... }

    /**
     * Exchange OAuth code for tokens and company info.
     */
    public function handleOAuthCallback(string $code, string $realmId): array { ... }

    /**
     * Refresh the OAuth access token using the refresh token.
     * QBO access tokens expire in 1 hour; refresh tokens in 100 days.
     */
    public function refreshToken(AccountingIntegration $integration): void { ... }

    /**
     * Push an invoice to QuickBooks Online.
     * Creates or updates based on whether external_id exists in sync log.
     */
    public function pushInvoice(
        AccountingIntegration $integration,
        Invoice $invoice,
        AccountingClientMapping $clientMapping
    ): string { ... }  // Returns QBO Invoice ID

    /**
     * Push a payment to QuickBooks Online.
     * Links to the QBO Invoice via external_id from sync log.
     */
    public function pushPayment(
        AccountingIntegration $integration,
        Payment $payment,
        string $qboInvoiceId
    ): string { ... }  // Returns QBO Payment ID

    /**
     * Fetch all customers from QuickBooks Online.
     * Used for client mapping UI.
     */
    public function fetchCustomers(AccountingIntegration $integration): array { ... }

    /**
     * Create a new customer in QuickBooks Online from a Solidtime client.
     */
    public function createCustomer(
        AccountingIntegration $integration,
        Client $client
    ): array { ... }  // Returns { id, name }
}
```

**Key implementation details**:
- Uses the `quickbooks/v3-php-sdk` package for API calls
- Invoice line items are mapped to QBO "SalesItemLineDetail" with "Services" item type
- Tax is pushed as a flat amount (not mapped to QBO tax codes) to avoid tax configuration complexity
- The `pushInvoice()` method checks `AccountingSyncLog` for an existing `external_id` to determine whether to create or update
- Customer creation includes name and email from the Solidtime `Client` model
- All API calls are wrapped in try/catch with structured logging

### 3.5 XeroService

**File**: `app/Service/XeroService.php`

Handles all Xero API interactions. Follows the same interface pattern as `QuickBooksService`.

```php
class XeroService
{
    /**
     * Generate the Xero OAuth 2.0 authorization URL.
     */
    public function getAuthorizationUrl(Organization $organization, string $state): string { ... }

    /**
     * Exchange OAuth code for tokens.
     * Xero requires tenant selection if multiple organizations exist.
     */
    public function handleOAuthCallback(string $code): array { ... }

    /**
     * Get available Xero tenants (organizations) for the connected account.
     */
    public function getTenants(string $accessToken): array { ... }

    /**
     * Refresh the OAuth access token.
     * Xero access tokens expire in 30 minutes; refresh tokens in 60 days.
     */
    public function refreshToken(AccountingIntegration $integration): void { ... }

    /**
     * Push an invoice to Xero.
     */
    public function pushInvoice(
        AccountingIntegration $integration,
        Invoice $invoice,
        AccountingClientMapping $clientMapping
    ): string { ... }  // Returns Xero Invoice ID

    /**
     * Push a payment to Xero.
     */
    public function pushPayment(
        AccountingIntegration $integration,
        Payment $payment,
        string $xeroInvoiceId
    ): string { ... }  // Returns Xero Payment ID

    /**
     * Fetch all contacts from Xero.
     */
    public function fetchContacts(AccountingIntegration $integration): array { ... }

    /**
     * Create a new contact in Xero from a Solidtime client.
     */
    public function createContact(
        AccountingIntegration $integration,
        Client $client
    ): array { ... }  // Returns { id, name }
}
```

**Key implementation details**:
- Uses the `xeroapi/xero-php-oauth2` package
- Xero has a 60 calls/minute rate limit -- the service implements request throttling using Laravel's `RateLimiter`
- Xero uses "Contacts" (not "Customers") for invoice recipients
- Xero invoice numbering: uses the Solidtime invoice number as the `InvoiceNumber` field. If Xero rejects it (duplicate), falls back to letting Xero auto-assign.
- Tenant selection: during OAuth callback, if multiple tenants exist, the first is selected by default. The admin can change the selected tenant in integration settings.

### 3.6 AccountingSyncService

**File**: `app/Service/AccountingSyncService.php`

Orchestrates sync operations between Solidtime and accounting systems. Delegates to `QuickBooksService` or `XeroService` based on the connected provider.

```php
class AccountingSyncService
{
    public function __construct(
        private readonly QuickBooksService $quickBooksService,
        private readonly XeroService $xeroService
    ) {}

    /**
     * Sync an invoice to the connected accounting system.
     * Creates an AccountingSyncLog entry and delegates to the provider service.
     */
    public function syncInvoice(
        AccountingIntegration $integration,
        Invoice $invoice
    ): AccountingSyncLog { ... }

    /**
     * Sync a payment to the connected accounting system.
     * Requires the invoice to already be synced (external_id must exist).
     */
    public function syncPayment(
        AccountingIntegration $integration,
        Payment $payment
    ): AccountingSyncLog { ... }

    /**
     * Auto-match Solidtime clients to external customers by name.
     * Returns array of suggested mappings.
     */
    public function autoMatchClients(
        AccountingIntegration $integration,
        Collection $clients
    ): array { ... }

    /**
     * Fetch customers/contacts from the external system.
     */
    public function fetchExternalCustomers(
        AccountingIntegration $integration
    ): array { ... }

    /**
     * Get the provider-specific service instance.
     */
    private function getProviderService(string $provider): QuickBooksService|XeroService { ... }
}
```

**Key implementation details**:
- `syncInvoice()` creates a sync log with status `pending`, then calls the provider service. On success, updates to `completed` with the `external_id`. On failure, updates to `failed` with `error_message` and increments `attempts`.
- `syncPayment()` first looks up the invoice's sync log to get the external invoice ID. If the invoice has not been synced yet, it throws an exception (the invoice must be synced before its payments can be).
- `autoMatchClients()` performs case-insensitive exact name matching between Solidtime clients and external customers. Returns an array of `{ client_id, external_customer_id, confidence: 'exact' }`.
- Token refresh is handled transparently: before each API call, the service checks `isTokenExpired()` and refreshes if needed.

---

## 4. Controller Layer

### 4.1 PaymentController

**File**: `app/Http/Controllers/Api/V1/PaymentController.php`

Extends `App\Http\Controllers\Api\V1\Controller` (base controller with permission helpers).

```php
class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService
    ) {}

    public function index(Organization $organization, PaymentIndexRequest $request): JsonResponse
    {
        $this->checkAnyPermission($organization, [
            'payments:view:own',
            'payments:view:all',
        ]);

        $payments = Payment::query()
            ->whereBelongsTo($organization, 'organization')
            ->when($request->input('invoice_id'), fn ($q, $id) => $q->where('invoice_id', $id))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('payment_method'), fn ($q, $m) => $q->where('payment_method', $m))
            ->when($request->input('from'), fn ($q, $d) => $q->where('paid_at', '>=', $d))
            ->when($request->input('to'), fn ($q, $d) => $q->where('paid_at', '<=', $d))
            ->with(['invoice', 'recorder'])
            ->orderBy('paid_at', 'desc')
            ->paginate($request->input('per_page', 25));

        return response()->json([
            'data' => $payments->items(),
            'meta' => [ /* pagination meta */ ],
        ]);
    }

    public function store(Organization $organization, PaymentStoreRequest $request): JsonResponse
    {
        $this->checkAnyPermission($organization, [
            'payments:create:own',
            'payments:create:all',
        ]);

        $invoice = Invoice::findOrFail($request->input('invoice_id'));
        $payment = $this->paymentService->recordPayment(
            $organization,
            $invoice,
            $request->input('amount'),
            'manual',
            $request->input('reference_number'),
            $request->input('notes'),
            Carbon::parse($request->input('paid_at')),
            $this->user()
        );

        return response()->json(['data' => $payment], 201);
    }

    public function show(Organization $organization, Payment $payment): JsonResponse
    {
        $this->checkAnyPermission($organization, [
            'payments:view:own',
            'payments:view:all',
        ]);

        return response()->json(['data' => $payment->load(['invoice', 'recorder', 'syncLogs'])]);
    }

    public function void(Organization $organization, Payment $payment, PaymentVoidRequest $request): JsonResponse
    {
        $this->checkPermission($organization, 'payments:create:all');

        $payment = $this->paymentService->voidPayment(
            $payment,
            $this->user(),
            $request->input('reason')
        );

        return response()->json(['data' => $payment]);
    }
}
```

### 4.2 PaymentIntegrationController

**File**: `app/Http/Controllers/Api/V1/PaymentIntegrationController.php`

Handles OAuth flows, client mapping, and sync triggers.

```php
class PaymentIntegrationController extends Controller
{
    public function __construct(
        private readonly StripeService $stripeService,
        private readonly PayPalService $payPalService,
        private readonly QuickBooksService $quickBooksService,
        private readonly XeroService $xeroService,
        private readonly AccountingSyncService $accountingSyncService
    ) {}

    public function index(Organization $organization): JsonResponse
    {
        $this->checkPermission($organization, 'integrations:manage');

        $paymentIntegrations = PaymentIntegration::query()
            ->whereBelongsTo($organization, 'organization')
            ->get();

        $accountingIntegrations = AccountingIntegration::query()
            ->whereBelongsTo($organization, 'organization')
            ->get();

        return response()->json([
            'data' => [
                'payment_integrations' => $paymentIntegrations,
                'accounting_integrations' => $accountingIntegrations,
            ],
        ]);
    }

    public function connect(Organization $organization, string $provider, PaymentIntegrationConnectRequest $request): JsonResponse
    {
        $this->checkPermission($organization, 'integrations:manage');

        $state = Str::random(40);
        session(['oauth_state' => $state, 'oauth_organization' => $organization->id]);

        $providerEnum = PaymentProvider::from($provider);
        $redirectUrl = match ($providerEnum) {
            PaymentProvider::STRIPE => $this->stripeService->getConnectUrl($organization, $state),
            PaymentProvider::PAYPAL => $this->payPalService->getAuthorizationUrl($organization, $state),
            PaymentProvider::QUICKBOOKS => $this->quickBooksService->getAuthorizationUrl($organization, $state),
            PaymentProvider::XERO => $this->xeroService->getAuthorizationUrl($organization, $state),
        };

        return response()->json(['data' => ['redirect_url' => $redirectUrl]]);
    }

    public function callback(Organization $organization, string $provider, Request $request): RedirectResponse
    {
        // Verify state parameter
        // Exchange code for tokens via provider service
        // Create or update integration record
        // Redirect to settings page with success/error status
    }

    public function disconnect(Organization $organization, string $provider): JsonResponse
    {
        $this->checkPermission($organization, 'integrations:manage');
        // Mark integration as inactive, set disconnected_at
    }

    public function clientMappings(Organization $organization, string $provider): JsonResponse
    {
        $this->checkPermission($organization, 'integrations:manage');
        // Fetch mappings, unmapped clients, external customers
    }

    public function updateClientMapping(Organization $organization, AccountingClientMapping $mapping, IntegrationClientMappingRequest $request): JsonResponse
    {
        $this->checkPermission($organization, 'integrations:manage');
        // Update or create mapping
    }

    public function syncInvoice(Organization $organization, IntegrationSyncInvoiceRequest $request): JsonResponse
    {
        $this->checkPermission($organization, 'integrations:manage');

        $integration = AccountingIntegration::query()
            ->whereBelongsTo($organization, 'organization')
            ->active()
            ->firstOrFail();

        $invoice = Invoice::findOrFail($request->input('invoice_id'));

        SyncInvoiceJob::dispatch($integration, $invoice);

        return response()->json([
            'data' => ['message' => 'Sync queued'],
        ], 202);
    }

    public function syncLogs(Organization $organization, IntegrationSyncLogRequest $request): JsonResponse
    {
        $this->checkPermission($organization, 'integrations:manage');
        // Query and return paginated sync logs
    }
}
```

### 4.3 PaymentWebhookController

**File**: `app/Http/Controllers/Api/V1/PaymentWebhookController.php`

Handles incoming webhooks from Stripe and PayPal. This controller does NOT extend the authenticated base controller -- it uses signature verification instead.

```php
class PaymentWebhookController extends BaseController  // Note: Laravel's base Controller, NOT Api\V1\Controller
{
    public function __construct(
        private readonly StripeService $stripeService,
        private readonly PayPalService $payPalService
    ) {}

    public function stripe(Request $request): JsonResponse
    {
        // 1. Verify signature (throws 401 if invalid)
        $event = $this->stripeService->verifyWebhookSignature(
            $request->getContent(),
            $request->header('Stripe-Signature')
        );

        // 2. Check idempotency (duplicate event)
        $existing = WebhookEvent::where('provider', 'stripe')
            ->where('event_id', $event->id)
            ->first();

        if ($existing && $existing->status === 'processed') {
            return response()->json(['received' => true]);
        }

        // 3. Store event record
        $webhookEvent = WebhookEvent::updateOrCreate(
            ['provider' => 'stripe', 'event_id' => $event->id],
            [
                'event_type' => $event->type,
                'payload' => $event->toArray(),
                'status' => 'received',
            ]
        );

        // 4. Dispatch async processing job
        ProcessWebhookJob::dispatch($webhookEvent);

        // 5. Respond immediately
        return response()->json(['received' => true]);
    }

    public function paypal(Request $request): JsonResponse
    {
        // Same pattern as stripe() but with PayPal signature verification
    }
}
```

**Key implementation details**:
- The webhook controller responds with 200 immediately and dispatches processing to a queued job. This ensures Stripe/PayPal receive a timely response (< 200ms) regardless of processing time.
- The `WebhookEvent` record acts as both an idempotency check and an audit log.
- The controller does NOT apply `auth:api` middleware. It is registered outside the authenticated route group.

---

## 5. Request Validation

### 5.1 Request Classes

All extend `App\Http\Requests\V1\BaseFormRequest`.

**PaymentIndexRequest**:
```php
public function rules(): array
{
    return [
        'invoice_id' => ['sometimes', 'string', 'uuid'],
        'status' => ['sometimes', 'string', Rule::in(['pending', 'completed', 'failed', 'voided'])],
        'payment_method' => ['sometimes', 'string', Rule::in(['stripe', 'paypal', 'manual'])],
        'from' => ['sometimes', 'date_format:Y-m-d'],
        'to' => ['sometimes', 'date_format:Y-m-d'],
        'page' => ['sometimes', 'integer', 'min:1'],
        'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
    ];
}
```

**PaymentStoreRequest**:
```php
public function rules(): array
{
    return [
        'invoice_id' => [
            'required', 'string', 'uuid',
            new ExistsEloquent(Invoice::class, null, function ($builder) {
                $builder->whereBelongsTo($this->organization, 'organization');
            }),
        ],
        'amount' => ['required', 'integer', 'min:1'],
        'reference_number' => ['nullable', 'string', 'max:255'],
        'notes' => ['nullable', 'string', 'max:1000'],
        'paid_at' => ['required', 'date_format:Y-m-d'],
    ];
}
```

**PaymentVoidRequest**:
```php
public function rules(): array
{
    return [
        'reason' => ['nullable', 'string', 'max:500'],
    ];
}
```

**PaymentIntegrationConnectRequest**:
```php
public function rules(): array
{
    return [
        // Provider is validated via route parameter
    ];
}

public function authorize(): bool
{
    $provider = $this->route('provider');
    return in_array($provider, ['stripe', 'paypal', 'quickbooks', 'xero']);
}
```

**IntegrationClientMappingRequest**:
```php
public function rules(): array
{
    return [
        'client_id' => [
            'required', 'string', 'uuid',
            new ExistsEloquent(Client::class, null, function ($builder) {
                $builder->whereBelongsTo($this->organization, 'organization');
            }),
        ],
        'external_customer_id' => ['required', 'string', 'max:255'],
    ];
}
```

**IntegrationSyncInvoiceRequest**:
```php
public function rules(): array
{
    return [
        'invoice_id' => [
            'required', 'string', 'uuid',
            new ExistsEloquent(Invoice::class, null, function ($builder) {
                $builder->whereBelongsTo($this->organization, 'organization');
            }),
        ],
    ];
}
```

**IntegrationSyncLogRequest**:
```php
public function rules(): array
{
    return [
        'syncable_type' => ['sometimes', 'string', Rule::in(['invoice', 'payment'])],
        'syncable_id' => ['sometimes', 'string', 'uuid'],
        'status' => ['sometimes', 'string', Rule::in(['pending', 'completed', 'failed'])],
        'page' => ['sometimes', 'integer', 'min:1'],
        'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
    ];
}
```

---

## 6. Webhook Handling

### 6.1 Processing Architecture

```
External Provider
    |
    v  (HTTP POST)
PaymentWebhookController
    |  1. Verify signature (reject 401 if invalid)
    |  2. Check idempotency (return 200 if already processed)
    |  3. Store WebhookEvent record (status: 'received')
    |  4. Dispatch ProcessWebhookJob
    |  5. Return 200 immediately
    v
ProcessWebhookJob (queued)
    |  1. Update WebhookEvent status to 'processing'
    |  2. Determine event type
    |  3. Route to provider service handler
    |  4. On success: update status to 'processed'
    |  5. On failure: update status to 'failed', increment attempts
    |  6. If attempts < 3: release job back to queue with delay
    |  7. If attempts >= 3: mark as dead letter, log alert
    v
PaymentService
    |  Record payment / refund
    |  Update invoice status
    |  Dispatch SyncPaymentJob (if accounting connected)
```

### 6.2 Stripe Webhook Events Handled

| Event Type | Action |
|------------|--------|
| `checkout.session.completed` | Record payment via `PaymentService::recordWebhookPayment()` |
| `charge.refunded` | Record refund via `PaymentService::recordRefund()` |
| `account.application.deauthorized` | Mark `PaymentIntegration` as inactive |

### 6.3 PayPal Webhook Events Handled

| Event Type | Action |
|------------|--------|
| `PAYMENT.CAPTURE.COMPLETED` | Record payment via `PaymentService::recordWebhookPayment()` |
| `PAYMENT.CAPTURE.REFUNDED` | Record refund via `PaymentService::recordRefund()` |
| `CUSTOMER.DISPUTE.CREATED` | Mark payment status as `disputed` (logged, no auto-action) |

### 6.4 Signature Verification

**Stripe**: Uses `Stripe\Webhook::constructEvent()` which validates the HMAC-SHA256 signature using the webhook signing secret. Rejects with 401 if signature is invalid or timestamp is outside tolerance (5 minutes).

**PayPal**: Uses PayPal's webhook verification API endpoint, which validates the transmission signature using the webhook ID, transmission ID, and certificate URL from request headers.

### 6.5 Webhook Middleware

Two optional middleware classes for cleaner signature verification:

**File**: `app/Http/Middleware/VerifyStripeWebhook.php`
```php
class VerifyStripeWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature'),
                config('services.stripe.webhook_secret')
            );
        } catch (SignatureVerificationException $e) {
            Log::channel('webhooks')->warning('Invalid Stripe signature', [
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
            ]);
            abort(401, 'Invalid signature');
        }

        return $next($request);
    }
}
```

**File**: `app/Http/Middleware/VerifyPayPalWebhook.php`
```php
class VerifyPayPalWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        // Verify using PayPal's webhook verification endpoint
        // Reject 401 if invalid
        return $next($request);
    }
}
```

### 6.6 Queued Jobs

**ProcessWebhookJob** (`app/Jobs/ProcessWebhookJob.php`):
- Queue: `webhooks` (dedicated queue for priority processing)
- Retries: 3 attempts with exponential backoff (10s, 60s, 300s)
- Timeout: 30 seconds
- Handles: Stripe and PayPal event processing
- Dead letter: After 3 failures, marks event as `failed` and logs alert

**SyncInvoiceJob** (`app/Jobs/SyncInvoiceJob.php`):
- Queue: `accounting-sync`
- Retries: 3 attempts with exponential backoff
- Timeout: 60 seconds
- Handles: Push invoice to QBO/Xero via `AccountingSyncService`

**SyncPaymentJob** (`app/Jobs/SyncPaymentJob.php`):
- Queue: `accounting-sync`
- Retries: 3 attempts with exponential backoff
- Timeout: 60 seconds
- Handles: Push payment to QBO/Xero via `AccountingSyncService`

**RefreshOAuthTokenJob** (`app/Jobs/RefreshOAuthTokenJob.php`):
- Queue: `default`
- Scheduled: Runs hourly via `app/Console/Kernel.php`
- Handles: Proactively refresh tokens that expire within the next 2 hours
- Covers: All 4 providers (Stripe, PayPal, QuickBooks, Xero)

**RetryFailedSyncJob** (`app/Jobs/RetryFailedSyncJob.php`):
- Queue: `accounting-sync`
- Scheduled: Runs every 15 minutes via `app/Console/Kernel.php`
- Handles: Re-queue `AccountingSyncLog` entries with `status=failed` and `attempts < 3`

---

## 7. Frontend Architecture

### 7.1 Component Hierarchy

```
IntegrationSettings.vue (Page)
+-- AppLayout
    +-- MainContainer
        +-- Section: Payment Providers
        |   +-- IntegrationCard.vue (Stripe)
        |   |   +-- Connect/Disconnect button
        |   |   +-- Connection status badge
        |   |   +-- Provider account details
        |   +-- IntegrationCard.vue (PayPal)
        |       +-- (same structure)
        +-- Section: Accounting Providers
        |   +-- IntegrationCard.vue (QuickBooks)
        |   |   +-- Connect/Disconnect button
        |   |   +-- Connection status / company name
        |   |   +-- Auto-sync toggles
        |   |   +-- "Manage Client Mapping" button
        |   +-- IntegrationCard.vue (Xero)
        |       +-- (same structure)
        +-- ClientMappingDialog.vue (modal)
            +-- ClientMappingTable.vue
            |   +-- Row per Solidtime client
            |   +-- Dropdown to select external customer
            |   +-- "Create in {provider}" button
            +-- Auto-match suggestions section

Payments.vue (Page)
+-- AppLayout
    +-- MainContainer
        +-- PaymentSummaryBar.vue (total received, total pending, total this month)
        +-- PaymentsList.vue
            +-- Filter bar (date range, method, status, client)
            +-- Table header (sortable columns)
            +-- PaymentRow.vue x N
            |   +-- PaymentStatusBadge.vue
            |   +-- SyncStatusBadge.vue
            +-- Pagination

InvoiceDetail.vue (Page -- MODIFIED from Feature 04)
+-- Section: Payment History (NEW)
    +-- Payment summary (total, paid, due)
    +-- Payment list (mini version of PaymentsList)
    +-- RecordPaymentDialog.vue (modal)
    |   +-- Amount input (pre-filled with remaining)
    |   +-- Date picker
    |   +-- Payment method dropdown
    |   +-- Reference number input
    |   +-- Notes textarea
    +-- SyncStatusBadge.vue (invoice sync status)

SyncLogs.vue (Page)
+-- AppLayout
    +-- MainContainer
        +-- SyncLogTable.vue
            +-- Filter bar (entity type, status)
            +-- Table rows with retry button
            +-- Pagination

PaymentSuccess.vue (Public page -- no AppLayout)
+-- Confirmation message
+-- Invoice reference

PaymentFailure.vue (Public page -- no AppLayout)
+-- Error message
+-- Retry link
```

### 7.2 Pinia Stores

**usePaymentsStore** (`resources/js/utils/usePayments.ts`):

```typescript
export const usePaymentsStore = defineStore('payments', () => {
    const payments = ref<Payment[]>([]);
    const paymentSummaries = ref<Map<string, PaymentSummary>>(new Map());
    const isLoading = ref(false);
    const error = ref<string | null>(null);

    async function fetchPayments(filters: PaymentFilters): Promise<void> { ... }
    async function recordPayment(data: RecordPaymentInput): Promise<Payment> { ... }
    async function voidPayment(paymentId: string, reason?: string): Promise<Payment> { ... }
    async function fetchPaymentSummary(invoiceId: string): Promise<PaymentSummary> { ... }

    return { payments, paymentSummaries, isLoading, error, fetchPayments, recordPayment, voidPayment, fetchPaymentSummary };
});
```

**useIntegrationsStore** (`resources/js/utils/useIntegrations.ts`):

```typescript
export const useIntegrationsStore = defineStore('integrations', () => {
    const paymentIntegrations = ref<PaymentIntegration[]>([]);
    const accountingIntegrations = ref<AccountingIntegration[]>([]);
    const clientMappings = ref<AccountingClientMapping[]>([]);
    const externalCustomers = ref<ExternalCustomer[]>([]);
    const syncLogs = ref<AccountingSyncLog[]>([]);
    const isLoading = ref(false);
    const error = ref<string | null>(null);

    async function fetchIntegrations(): Promise<void> { ... }
    async function connectProvider(provider: string): Promise<void> { ... }
    async function disconnectProvider(provider: string): Promise<void> { ... }
    async function fetchClientMappings(provider: string): Promise<void> { ... }
    async function updateClientMapping(mappingId: string, data: ClientMappingInput): Promise<void> { ... }
    async function syncInvoice(invoiceId: string): Promise<void> { ... }
    async function fetchSyncLogs(filters: SyncLogFilters): Promise<void> { ... }

    return { paymentIntegrations, accountingIntegrations, clientMappings, externalCustomers, syncLogs, isLoading, error, fetchIntegrations, connectProvider, disconnectProvider, fetchClientMappings, updateClientMapping, syncInvoice, fetchSyncLogs };
});
```

### 7.3 TypeScript Types

**File**: `resources/js/types/payment.d.ts`

Defines: `Payment`, `PaymentSummary`, `PaymentFilters`, `RecordPaymentInput`, `PaymentMethod`, `PaymentType`, `PaymentStatus`

**File**: `resources/js/types/integration.d.ts`

Defines: `PaymentIntegration`, `AccountingIntegration`, `AccountingClientMapping`, `ExternalCustomer`, `AccountingSyncLog`, `SyncLogFilters`, `ClientMappingInput`, `PaymentProvider`

### 7.4 Page Registration

**File**: `routes/web.php`
```php
Route::get('/payments', function () {
    return Inertia::render('Payments');
})->name('payments');

Route::get('/settings/integrations', function () {
    return Inertia::render('IntegrationSettings');
})->name('integrations.settings');

Route::get('/settings/integrations/sync-logs', function () {
    return Inertia::render('SyncLogs');
})->name('integrations.sync-logs');
```

**File**: `resources/js/Layouts/AppLayout.vue`
```vue
<!-- Under existing Invoices section -->
<NavigationSidebarItem
    title="Payments"
    :icon="CreditCardIcon"
    :current="route().current('payments')"
    :href="route('payments')">
</NavigationSidebarItem>
```

Uses `CreditCardIcon` from `@heroicons/vue/20/solid`.

Integration settings are accessible via Organization Settings (existing settings page), not a separate sidebar item.

---

## 8. Permission Matrix

### 8.1 New Permissions

5 new permissions are added via the modular permission pattern (SF-08):

**File**: `app/Permissions/PaymentPermissions.php`

| Permission | Description |
|------------|-------------|
| `payments:view:own` | View payments on invoices the user created |
| `payments:view:all` | View all payments in the organization |
| `payments:create:own` | Record manual payments on own invoices |
| `payments:create:all` | Record manual payments on any invoice; void payments |
| `integrations:manage` | Connect/disconnect integrations, manage client mappings, trigger syncs |

### 8.2 Role Assignment

| Role | payments:view:own | payments:view:all | payments:create:own | payments:create:all | integrations:manage |
|------|:-:|:-:|:-:|:-:|:-:|
| Owner | Yes | Yes | Yes | Yes | Yes |
| Admin | Yes | Yes | Yes | Yes | Yes |
| Manager | Yes | Yes | Yes | No | No |
| Employee | Yes | No | No | No | No |

### 8.3 Endpoint Permission Mapping

| Endpoint | Required Permission | Notes |
|----------|-------------------|-------|
| GET /payments | `payments:view:own` or `payments:view:all` | `view:own` scopes to user's invoices |
| POST /payments | `payments:create:own` or `payments:create:all` | `create:own` only for user's invoices |
| GET /payments/{id} | `payments:view:own` or `payments:view:all` | Ownership check on invoice |
| POST /payments/{id}/void | `payments:create:all` | Only Admin/Owner can void |
| GET /integrations | `integrations:manage` | Admin/Owner only |
| POST /integrations/{}/connect | `integrations:manage` | Admin/Owner only |
| DELETE /integrations/{} | `integrations:manage` | Admin/Owner only |
| All other integration endpoints | `integrations:manage` | Admin/Owner only |
| POST /webhooks/stripe | None | Signature verified |
| POST /webhooks/paypal | None | Signature verified |
| GET /pay/{token} | None | Signed URL |

---

## 9. Security Strategy

### 9.1 PCI DSS Compliance (SAQ-A)

Solidtime qualifies for PCI SAQ-A (Self-Assessment Questionnaire A) because:
- **No card data storage**: Credit card numbers, CVVs, and expiration dates never touch Solidtime servers
- **No card data processing**: All payment forms are hosted by Stripe Checkout and PayPal
- **No card data transmission**: The payment link redirects to the provider's hosted page; no card data passes through Solidtime's network
- **Hosted payment pages**: Both Stripe Checkout and PayPal use their own PCI-compliant payment forms

### 9.2 Token Encryption at Rest

All OAuth tokens stored in the database are encrypted using Laravel's `encrypted` cast:

```php
// Model cast definition
protected $casts = [
    'access_token' => 'encrypted',
    'refresh_token' => 'encrypted',
];
```

This uses `Crypt::encryptString()` under the hood, which uses AES-256-CBC with the application's `APP_KEY`. Tokens are encrypted on write and decrypted on read.

Additionally, tokens are in the `$hidden` array on all models, preventing accidental exposure in JSON responses or logs.

### 9.3 OAuth State CSRF Protection

All OAuth flows use a signed, time-limited state parameter:

```php
// On connect (generate state)
$state = Str::random(40);
session(['oauth_state' => $state, 'oauth_organization' => $organization->id]);

// On callback (verify state)
if ($request->input('state') !== session('oauth_state')) {
    abort(403, 'Invalid state parameter');
}
session()->forget(['oauth_state', 'oauth_organization']);
```

This prevents CSRF attacks where an attacker could trick an admin into connecting their (the attacker's) Stripe account to the organization.

### 9.4 Payment Link Security

Payment URLs use signed tokens with:
- Invoice ID embedded in token
- Amount hash to prevent tampering
- 24-hour expiration
- One-time use (Checkout Session is single-use)

```php
// Generate payment URL
$token = URL::signedRoute('payment.redirect', [
    'invoice' => $invoice->id,
    'amount' => $invoice->amount_due,
], now()->addHours(24));
```

### 9.5 Webhook Endpoint Protection

- Webhook endpoints do NOT use `auth:api` middleware (they receive requests from external providers, not authenticated users)
- Signature verification is the first operation in the controller method -- no processing occurs before verification
- Invalid signatures return 401 and are logged
- Rate limiting can be applied at the web server level (nginx) to prevent abuse

### 9.6 Organization Scoping

All queries in controllers and services are scoped to the current organization:
```php
->whereBelongsTo($organization, 'organization')
```

This is enforced at the controller level. Cross-organization data access is architecturally impossible through the API.

### 9.7 Write Protection

All mutation endpoints use `check-organization-blocked` middleware, which prevents writes when:
- The organization's subscription has expired
- The organization is in a blocked state

### 9.8 Audit Logging

All payment and integration models use the `CustomAuditable` trait, which automatically logs:
- Create, update, and delete operations
- The user who performed the action
- Old and new values for changed attributes
- Timestamp of the change

Additionally, the `accounting_sync_logs` table provides a dedicated audit trail for all sync operations.

---

## 10. Performance Strategy

### 10.1 Database

- **Indexes**: 9 indexes across 3 tables (payments, accounting_sync_logs, webhook_events) cover all primary query patterns
- **Integer amounts**: Payment amounts stored as integers (cents) for exact arithmetic without floating-point errors
- **Pagination**: All list endpoints paginated (default 25, max 100 per page)
- **Eager loading**: `with(['invoice', 'recorder'])` on payment queries to prevent N+1

### 10.2 Queue-Based Processing

- **Webhooks**: Respond 200 immediately, process asynchronously via `ProcessWebhookJob`
- **Accounting sync**: All sync operations dispatched as queued jobs, not processed inline
- **Token refresh**: Proactive refresh via scheduled job, not on-demand during user requests

### 10.3 API Rate Limiting

- **QuickBooks**: No explicit rate limit, but queue-based sync naturally spreads requests
- **Xero**: 60 calls/minute limit. The `XeroService` uses Laravel's `RateLimiter` to throttle outbound requests:
  ```php
  RateLimiter::attempt('xero-api-' . $integration->id, 60, function () use ($callback) {
      return $callback();
  }, 60);
  ```

### 10.4 Targets

| Metric | Target |
|--------|--------|
| GET /payments (paginated) | < 500ms |
| POST /payments (record manual) | < 500ms |
| POST /webhooks/* (respond to provider) | < 200ms |
| Webhook processing (total) | < 2s |
| OAuth flow completion | < 5s |
| Accounting sync (single invoice) | < 10s |
| Integration settings page load | < 300ms |

---

## 11. Integration Points

### 11.1 Internal Dependencies

| Dependency | Type | Interaction |
|------------|------|-------------|
| Feature 04 (Invoicing) | **Hard** | `Invoice` model, `InvoiceController`, `InvoiceService` must exist |
| `Organization` model | Read | Route model binding, organization scoping, currency |
| `Client` model | Read | Client data for accounting client mapping |
| `User` model | Read | `recorded_by` on payments, OAuth state |
| `Member` model | Read | Permission checks |
| `PermissionStore` | Read | Permission enforcement in controllers |
| `BillingContract` | Read | `canAccessPremiumFeatures()` gating |

### 11.2 External Dependencies (Composer Packages)

| Package | Version | Purpose |
|---------|---------|---------|
| `stripe/stripe-php` | ^14.0 | Stripe API client |
| `paypal/paypal-server-sdk` | ^1.0 | PayPal REST API client |
| `quickbooks/v3-php-sdk` | ^6.0 | QuickBooks Online API client |
| `xeroapi/xero-php-oauth2` | ^5.0 | Xero API client |

### 11.3 External Services

| Service | Purpose | Auth | Rate Limits |
|---------|---------|------|-------------|
| Stripe API | Payments, Checkout Sessions | OAuth 2.0 (Connect) | 100 read/s, 100 write/s |
| PayPal REST API | Payments, Orders | OAuth 2.0 | 30 requests/s |
| QuickBooks Online API | Invoice/payment sync | OAuth 2.0 | 500 requests/minute |
| Xero API | Invoice/payment sync | OAuth 2.0 | 60 requests/minute |

### 11.4 No Conflicts with Existing Features

| Feature | Interaction |
|---------|-------------|
| Time entries | No interaction -- payments are invoice-level, not time-entry-level |
| Timesheet grid | No interaction |
| Budgets | No interaction |
| Expenses | No interaction |
| Reporting | Future: payment data could feed into financial reports |

### 11.5 Downstream Features

This feature enables:
- **Automated payment reminders**: Overdue invoice + payment status enables email reminders
- **Financial dashboards**: Payment data enables revenue/AR reporting
- **Progressive billing**: Partial payment tracking enables milestone-based invoicing
- **Expense reimbursement**: Payment infrastructure could be reused for expense payouts

---

## 12. File Manifest

### 12.1 New Files (68)

| File | Type | Task |
|------|------|------|
| **Backend: Models (6)** | | |
| `app/Models/Payment.php` | Model | PAY-002 |
| `app/Models/PaymentIntegration.php` | Model | PAY-002 |
| `app/Models/AccountingIntegration.php` | Model | PAY-002 |
| `app/Models/AccountingClientMapping.php` | Model | PAY-002 |
| `app/Models/AccountingSyncLog.php` | Model | PAY-002 |
| `app/Models/WebhookEvent.php` | Model | PAY-002 |
| **Backend: Enums (5)** | | |
| `app/Enums/PaymentMethod.php` | Enum | PAY-002 |
| `app/Enums/PaymentStatus.php` | Enum | PAY-002 |
| `app/Enums/PaymentType.php` | Enum | PAY-002 |
| `app/Enums/PaymentProvider.php` | Enum | PAY-002 |
| `app/Enums/SyncStatus.php` | Enum | PAY-002 |
| **Backend: Controllers (3)** | | |
| `app/Http/Controllers/Api/V1/PaymentController.php` | Controller | PAY-004 |
| `app/Http/Controllers/Api/V1/PaymentIntegrationController.php` | Controller | PAY-006 |
| `app/Http/Controllers/Api/V1/PaymentWebhookController.php` | Controller | PAY-009 |
| **Backend: Services (6)** | | |
| `app/Service/PaymentService.php` | Service | PAY-003 |
| `app/Service/StripeService.php` | Service | PAY-007 |
| `app/Service/PayPalService.php` | Service | PAY-008 |
| `app/Service/QuickBooksService.php` | Service | PAY-012 |
| `app/Service/XeroService.php` | Service | PAY-013 |
| `app/Service/AccountingSyncService.php` | Service | PAY-014 |
| **Backend: Request Validation (7)** | | |
| `app/Http/Requests/V1/Payment/PaymentIndexRequest.php` | Request | PAY-005 |
| `app/Http/Requests/V1/Payment/PaymentStoreRequest.php` | Request | PAY-005 |
| `app/Http/Requests/V1/Payment/PaymentVoidRequest.php` | Request | PAY-005 |
| `app/Http/Requests/V1/PaymentIntegration/PaymentIntegrationConnectRequest.php` | Request | PAY-018 |
| `app/Http/Requests/V1/PaymentIntegration/IntegrationClientMappingRequest.php` | Request | PAY-018 |
| `app/Http/Requests/V1/PaymentIntegration/IntegrationSyncInvoiceRequest.php` | Request | PAY-018 |
| `app/Http/Requests/V1/PaymentIntegration/IntegrationSyncLogRequest.php` | Request | PAY-018 |
| **Backend: Middleware (2)** | | |
| `app/Http/Middleware/VerifyStripeWebhook.php` | Middleware | PAY-010 |
| `app/Http/Middleware/VerifyPayPalWebhook.php` | Middleware | PAY-010 |
| **Backend: Jobs (5)** | | |
| `app/Jobs/ProcessWebhookJob.php` | Job | PAY-011 |
| `app/Jobs/SyncInvoiceJob.php` | Job | PAY-015 |
| `app/Jobs/SyncPaymentJob.php` | Job | PAY-015 |
| `app/Jobs/RefreshOAuthTokenJob.php` | Job | PAY-016 |
| `app/Jobs/RetryFailedSyncJob.php` | Job | PAY-047 |
| **Backend: Permissions (1)** | | |
| `app/Permissions/PaymentPermissions.php` | Permissions | PAY-020 |
| **Backend: Migrations (6)** | | |
| `database/migrations/2026_03_14_000001_create_payment_integrations_table.php` | Migration | PAY-001 |
| `database/migrations/2026_03_14_000002_create_accounting_integrations_table.php` | Migration | PAY-001 |
| `database/migrations/2026_03_14_000003_create_accounting_client_mappings_table.php` | Migration | PAY-001 |
| `database/migrations/2026_03_14_000004_create_payments_table.php` | Migration | PAY-001 |
| `database/migrations/2026_03_14_000005_create_accounting_sync_logs_table.php` | Migration | PAY-001 |
| `database/migrations/2026_03_14_000006_create_webhook_events_table.php` | Migration | PAY-001 |
| **Backend: Factories (3)** | | |
| `database/factories/PaymentFactory.php` | Factory | PAY-002 |
| `database/factories/PaymentIntegrationFactory.php` | Factory | PAY-002 |
| `database/factories/AccountingIntegrationFactory.php` | Factory | PAY-002 |
| **Frontend: Pages (5)** | | |
| `resources/js/Pages/Payments.vue` | Page | PAY-026 |
| `resources/js/Pages/IntegrationSettings.vue` | Page | PAY-024 |
| `resources/js/Pages/SyncLogs.vue` | Page | PAY-030 |
| `resources/js/Pages/PaymentSuccess.vue` | Page | PAY-032 |
| `resources/js/Pages/PaymentFailure.vue` | Page | PAY-032 |
| **Frontend: UI Components -- Payment (5)** | | |
| `resources/js/packages/ui/src/Payment/PaymentsList.vue` | Component | PAY-026 |
| `resources/js/packages/ui/src/Payment/PaymentRow.vue` | Component | PAY-026 |
| `resources/js/packages/ui/src/Payment/RecordPaymentDialog.vue` | Component | PAY-028 |
| `resources/js/packages/ui/src/Payment/PaymentStatusBadge.vue` | Component | PAY-029 |
| `resources/js/packages/ui/src/Payment/PaymentSummaryBar.vue` | Component | PAY-026 |
| **Frontend: UI Components -- Integration (5)** | | |
| `resources/js/packages/ui/src/Integration/IntegrationCard.vue` | Component | PAY-024 |
| `resources/js/packages/ui/src/Integration/ClientMappingDialog.vue` | Component | PAY-025 |
| `resources/js/packages/ui/src/Integration/ClientMappingTable.vue` | Component | PAY-025 |
| `resources/js/packages/ui/src/Integration/SyncStatusBadge.vue` | Component | PAY-029 |
| `resources/js/packages/ui/src/Integration/SyncLogTable.vue` | Component | PAY-030 |
| **Frontend: Stores (2)** | | |
| `resources/js/utils/usePayments.ts` | Store | PAY-022 |
| `resources/js/utils/useIntegrations.ts` | Store | PAY-023 |
| **Frontend: Types (2)** | | |
| `resources/js/types/payment.d.ts` | Types | PAY-022 |
| `resources/js/types/integration.d.ts` | Types | PAY-023 |
| **Frontend: Tests (4)** | | |
| `resources/js/packages/ui/src/Payment/__tests__/PaymentsList.test.ts` | Test | PAY-043 |
| `resources/js/packages/ui/src/Payment/__tests__/RecordPaymentDialog.test.ts` | Test | PAY-043 |
| `resources/js/packages/ui/src/Integration/__tests__/IntegrationCard.test.ts` | Test | PAY-042 |
| `resources/js/packages/ui/src/Integration/__tests__/ClientMappingDialog.test.ts` | Test | PAY-042 |
| **Backend: Tests (8)** | | |
| `tests/Unit/Endpoint/Api/V1/PaymentEndpointTest.php` | Test | PAY-033 |
| `tests/Unit/Endpoint/Api/V1/IntegrationEndpointTest.php` | Test | PAY-034 |
| `tests/Unit/Endpoint/Api/V1/WebhookEndpointTest.php` | Test | PAY-041 |
| `tests/Unit/Service/PaymentServiceTest.php` | Test | PAY-035 |
| `tests/Unit/Service/StripeServiceTest.php` | Test | PAY-036 |
| `tests/Unit/Service/PayPalServiceTest.php` | Test | PAY-037 |
| `tests/Unit/Service/QuickBooksServiceTest.php` | Test | PAY-038 |
| `tests/Unit/Service/XeroServiceTest.php` | Test | PAY-039 |
| `tests/Unit/Service/AccountingSyncServiceTest.php` | Test | PAY-040 |
| **E2E Tests (2)** | | |
| `e2e/payments.spec.ts` | Test | PAY-044 |
| `e2e/integrations.spec.ts` | Test | PAY-044 |

### 12.2 Modified Files (6)

| File | Change | Task |
|------|--------|------|
| `routes/api.php` | Add payment, integration, and webhook route groups | PAY-017 |
| `routes/web.php` | Add Inertia page routes, OAuth callback routes, public payment link | PAY-031 |
| `resources/js/Layouts/AppLayout.vue` | Add Payments sidebar nav item | PAY-031 |
| `app/Models/Invoice.php` | Add `payments()`, `completedPayments()`, `totalPaid()`, `totalRefunded()`, `amountDue()` relations/methods | PAY-002 |
| `app/Providers/AppServiceProvider.php` | Add morph map for `invoice` and `payment` | PAY-002 |
| `config/services.php` | Add Stripe, PayPal, QuickBooks, Xero credentials configuration | PAY-007, PAY-008, PAY-012, PAY-013 |
