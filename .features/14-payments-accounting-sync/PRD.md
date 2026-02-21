# PRD: Online Payments and Accounting Sync

Generated: 2026-02-09
Version: 1.0
Feature Branch: `feature/payments-accounting-sync` (from `main`)

---

## Table of Contents

1. [Source & Context](#1-source--context)
2. [Technical Interpretation](#2-technical-interpretation)
3. [Functional Specifications](#3-functional-specifications)
4. [Technical Requirements & Constraints](#4-technical-requirements--constraints)
5. [User Stories with Acceptance Criteria](#5-user-stories-with-acceptance-criteria)
6. [Task Breakdown Structure](#6-task-breakdown-structure)
7. [Dependencies & Integration Points](#7-dependencies--integration-points)
8. [Risk Assessment & Mitigation](#8-risk-assessment--mitigation)
9. [Testing & Validation Requirements](#9-testing--validation-requirements)
10. [Monitoring & Observability](#10-monitoring--observability)
11. [Success Metrics & Definition of Done](#11-success-metrics--definition-of-done)
12. [Technical Debt & Future Considerations](#12-technical-debt--future-considerations)
13. [Appendices](#13-appendices)

---

## 1. Source & Context

### 1.1 Problem Statement

Solidtime currently has invoicing capabilities (Premium/Enterprise tier) that allow organizations to generate invoices from tracked time. However, there is no mechanism for clients to **pay invoices online** and no way to **synchronize invoice and payment data with external accounting systems**. This creates friction in two critical areas:

- **Payment collection**: After generating and sending an invoice, the organization must manually track payments received via bank transfer, check, or other offline methods. There is no way to embed a "Pay Now" link in an invoice for immediate online payment.
- **Bookkeeping**: Invoice and payment data must be manually re-entered into accounting software (QuickBooks, Xero, FreshBooks, etc.), creating duplicate work, data entry errors, and reconciliation headaches.

Without online payments and accounting sync:
- Organizations experience slower collections because clients cannot pay immediately upon receiving an invoice
- Finance teams spend hours re-entering invoice data into their accounting system
- Payment status tracking is manual and error-prone (was this invoice paid? partially paid?)
- There is no automated reconciliation between the time tracking system and the general ledger
- Solidtime falls behind competitors who offer integrated payment collection and accounting sync as standard features

### 1.2 Competitive Analysis

From **features.txt** Section 7.4 -- "Online payments and accounting sync":

> **What**: Pay invoice via Stripe/PayPal; sync invoices/payments to accounting.
> **Why important**: Faster collections; cleaner bookkeeping.
> **User flow**:
> 1. Admin connects payment provider and/or accounting system.
> 2. Invoice includes payment link or recorded payment.
> 3. Payment status updates; sync pushes invoice/payment to accounting.

Platforms offering this feature:
- **Harvest**: PayPal/Stripe online payments + QuickBooks Online/Xero sync for invoices and payments
- **Everhour**: QuickBooks/Xero/FreshBooks accounting sync; record payments on invoices
- **Nutcache**: PayPal/Stripe/Authorize.net/2Checkout payment providers; recurring invoices with payment tracking
- **Clockify**: Invoicing with record payments (implied); no explicit accounting sync
- **TimeCamp**: Export invoices to QuickBooks/Xero; billable rates sync

### 1.3 Reference Implementations

**Harvest** (primary reference for payment collection):
- Admin connects Stripe or PayPal in workspace settings
- When creating/sending an invoice, a "Pay Online" button is embedded
- Client clicks the link, enters payment details on Stripe/PayPal hosted page
- Payment confirmation webhook updates invoice status to "Paid"
- Partial payments supported (invoice moves to "Partially Paid" status)
- Sync pushes invoice + payment to QuickBooks Online or Xero

**Everhour** (primary reference for accounting sync):
- Admin connects QuickBooks/Xero/FreshBooks via OAuth 2.0
- Invoices can be pushed to the accounting system as draft or finalized invoices
- Payments recorded in Everhour are synced to the accounting system
- Two-way sync: payment recorded in accounting system updates Everhour
- Customer/client mapping between systems

### 1.4 Current System State

**Existing infrastructure on `main`:**
- `Organization` model with `currency`, `billable_rate`, billing-related fields
- `Client` model with `name`, `organization_id` (no billing address or tax fields yet)
- `Project` model with `is_billable`, `billable_rate`, `client_id`
- `TimeEntry` model with `billable`, `billable_rate`
- `BillableRateService` for multi-level rate computation
- `BillingContract` service for subscription/trial management (internal SaaS billing, not client billing)
- `PermissionStore` with role-based access control (Owner/Admin/Manager/Employee)
- Laravel Passport for API authentication
- Existing `check-organization-blocked` middleware for write endpoints
- **No Invoice model, no Payment model, no external integration models exist yet**

**Prerequisite -- Feature 04 (Invoicing) must be implemented first:**
This PRD assumes Feature 04 provides:
- `Invoice` model with `id`, `organization_id`, `client_id`, `number`, `status`, `currency`, `subtotal`, `tax`, `total`, `due_date`, `issued_date`, `notes`, `line_items` (JSON or related table)
- `InvoiceController` with CRUD endpoints
- `InvoiceService` with business logic for creating invoices from time entries
- Invoice statuses: `draft`, `sent`, `viewed`, `overdue`, `paid`, `void`
- PDF generation via Gotenberg
- Email delivery of invoices
- Recurring invoice support

---

## 2. Technical Interpretation

### Business to Technical Translation

| Business Requirement | Technical Implementation |
|---------------------|-------------------------|
| Admin connects Stripe account | OAuth 2.0 flow to Stripe Connect; store credentials in `payment_integrations` table |
| Admin connects PayPal account | OAuth 2.0 flow to PayPal; store credentials in `payment_integrations` table |
| Invoice includes payment link | Generate Stripe Checkout Session or PayPal Order; embed URL in invoice PDF and email |
| Client pays online | Stripe/PayPal hosted payment page; webhook confirms payment |
| Payment status updates | Webhook handlers update `payments` table and `invoices.status` field |
| Admin connects QuickBooks | OAuth 2.0 flow to QuickBooks Online API; store tokens in `accounting_integrations` table |
| Admin connects Xero | OAuth 2.0 flow to Xero API; store tokens in `accounting_integrations` table |
| Sync invoice to accounting | Push invoice data to QuickBooks/Xero API; store external reference ID |
| Sync payment to accounting | Push payment data to QuickBooks/Xero API when payment is recorded |
| Manual payment recording | Admin records offline payment (check, bank transfer) with amount and date |

### New Permissions Required

| Permission | Description |
|------------|-------------|
| `payments:view:own` | View payments on own invoices |
| `payments:view:all` | View all payments in organization |
| `payments:create:own` | Record payments on own invoices |
| `payments:create:all` | Record payments on any invoice |
| `integrations:manage` | Connect/disconnect payment providers and accounting systems |

### No-Change Boundary

This feature does **NOT**:
- Modify the existing `TimeEntry`, `Project`, `Client`, or `Organization` model schemas (except adding optional relations)
- Replace or modify the existing `BillingContract` service (that handles internal SaaS subscriptions)
- Process or store raw credit card numbers (all payment processing delegated to Stripe/PayPal hosted pages)
- Implement a full general ledger or double-entry accounting system
- Support accounting systems beyond QuickBooks Online and Xero in the initial release
- Implement two-way sync from accounting systems back to Solidtime (one-way push only in v1)
- Handle multi-currency conversion (invoices are in the organization's currency; accounting sync preserves that currency)

---

## 3. Functional Specifications

### 3.1 Core Requirements

#### REQ-001: Stripe Payment Integration
- **Description**: Allow organizations to connect a Stripe account and accept invoice payments via Stripe Checkout
- **Priority**: P0
- **Flow**:
  1. Admin navigates to Organization Settings > Integrations > Payments
  2. Clicks "Connect Stripe" button
  3. Redirected to Stripe OAuth flow (Stripe Connect Standard)
  4. On callback, system stores Stripe account ID and access tokens
  5. When an invoice is sent, system generates a Stripe Checkout Session
  6. Payment link is embedded in invoice PDF and email
  7. Client clicks link, completes payment on Stripe-hosted page
  8. Stripe sends `checkout.session.completed` webhook
  9. System records payment and updates invoice status
- **Edge Cases**:
  - Stripe account disconnected while unpaid invoices exist (disable payment links, show warning)
  - Stripe webhook delivery failure (implement retry with idempotency keys)
  - Partial payment via Stripe (not supported in v1; Stripe Checkout enforces full amount)
  - Currency mismatch between invoice and Stripe account (validate on Checkout Session creation)
  - Refunds initiated in Stripe dashboard (handle `charge.refunded` webhook)
- **Error Scenarios**:
  - Stripe API unavailable (queue payment link generation, retry)
  - Invalid Stripe credentials (mark integration as disconnected, notify admin)
  - Duplicate webhook delivery (idempotency check via `payment_intent_id`)

#### REQ-002: PayPal Payment Integration
- **Description**: Allow organizations to connect a PayPal account and accept invoice payments via PayPal
- **Priority**: P1
- **Flow**:
  1. Admin navigates to Organization Settings > Integrations > Payments
  2. Clicks "Connect PayPal" button
  3. Redirected to PayPal OAuth flow
  4. On callback, system stores PayPal merchant ID and access tokens
  5. When an invoice is sent, system creates a PayPal Order
  6. Payment link is embedded in invoice PDF and email
  7. Client clicks link, completes payment on PayPal-hosted page
  8. PayPal sends `PAYMENT.CAPTURE.COMPLETED` webhook
  9. System records payment and updates invoice status
- **Edge Cases**:
  - PayPal account disconnected while unpaid invoices exist (same as Stripe)
  - PayPal disputes/chargebacks (handle `CUSTOMER.DISPUTE.CREATED` webhook, mark payment as disputed)
  - PayPal pending payments (eCheck) -- mark as `pending` until `PAYMENT.CAPTURE.COMPLETED`
- **Error Scenarios**:
  - PayPal API unavailable (queue, retry)
  - OAuth token expired (refresh using stored refresh token)
  - Order creation fails (log error, fall back to invoice without payment link)

#### REQ-003: QuickBooks Online Accounting Sync
- **Description**: Allow organizations to connect QuickBooks Online and sync invoices and payments
- **Priority**: P0
- **Flow**:
  1. Admin navigates to Organization Settings > Integrations > Accounting
  2. Clicks "Connect QuickBooks Online" button
  3. Redirected to Intuit OAuth 2.0 flow
  4. On callback, system stores OAuth tokens and company ID
  5. Admin maps Solidtime clients to QuickBooks customers (auto-match by name, manual override)
  6. When an invoice is finalized/sent, system pushes it to QuickBooks as an Invoice object
  7. When a payment is recorded, system pushes it to QuickBooks as a Payment object
  8. External reference IDs stored for deduplication
- **Sync Behavior**:
  - **Invoice push**: On invoice status change to `sent` or manually triggered
  - **Payment push**: Immediately after payment is recorded (webhook or manual)
  - **Client/Customer sync**: On-demand matching; create new QBO customer if no match found
  - **Line items**: Map invoice line items to QBO invoice line items (use "Services" item type)
- **Edge Cases**:
  - QuickBooks token expiration (auto-refresh using refresh token; 100-day refresh token lifetime)
  - Network failure during sync (mark as `sync_pending`, retry via scheduled job)
  - Invoice already exists in QBO (detect via external reference, update instead of create)
  - Client deleted in QBO (re-create on next sync, log warning)
  - Tax rate differences (push tax amount as flat value, not QBO tax code)
- **Error Scenarios**:
  - QBO API rate limit exceeded (exponential backoff, queue retries)
  - OAuth refresh token expired (mark integration as disconnected, notify admin to reconnect)
  - Invalid data format (log detailed error, mark sync as failed with reason)

#### REQ-004: Xero Accounting Sync
- **Description**: Allow organizations to connect Xero and sync invoices and payments
- **Priority**: P1
- **Flow**:
  1. Admin navigates to Organization Settings > Integrations > Accounting
  2. Clicks "Connect Xero" button
  3. Redirected to Xero OAuth 2.0 flow
  4. On callback, system stores OAuth tokens and tenant ID
  5. Admin maps Solidtime clients to Xero contacts (auto-match by name, manual override)
  6. When an invoice is finalized/sent, system pushes it to Xero as an Invoice object
  7. When a payment is recorded, system pushes it to Xero as a Payment object
  8. External reference IDs stored for deduplication
- **Sync Behavior**:
  - Same as QuickBooks (REQ-003) but using Xero API entities (Contacts, Invoices, Payments)
  - Xero uses 60-day refresh tokens; auto-refresh before expiry
  - Xero has a tenant selection step (organization may have multiple Xero orgs)
- **Edge Cases**:
  - Multiple Xero tenants (admin selects which tenant to sync with during setup)
  - Xero API rate limits (60 calls/minute; implement throttling)
  - Xero invoice numbering conflicts (use Solidtime invoice number, let Xero auto-assign if conflict)
- **Error Scenarios**:
  - Same patterns as QBO (REQ-003)

#### REQ-005: Payment Tracking and Management
- **Description**: Track all payments (online and manual) against invoices with full status management
- **Priority**: P0
- **Flow**:
  1. Payment is recorded via webhook (online) or manually by admin
  2. Payment amount is applied to the invoice
  3. Invoice status updates based on payment state:
     - `paid` if total payments >= invoice total
     - `partially_paid` if total payments < invoice total and > 0
     - Remains `sent`/`overdue` if no payments
  4. Payment history viewable on invoice detail page
  5. Payments can be voided (reverses the applied amount)
- **Payment Types**:
  - `stripe` -- Online payment via Stripe Checkout
  - `paypal` -- Online payment via PayPal
  - `manual` -- Manually recorded payment (check, bank transfer, cash, other)
- **Edge Cases**:
  - Overpayment (allow recording; show credit balance on invoice)
  - Refund processing (create negative payment record; update invoice status accordingly)
  - Currency rounding (store amounts in cents/smallest unit; round only on display)
  - Multiple partial payments (sum all payments; compare against invoice total)
- **Error Scenarios**:
  - Concurrent payment recording (database-level locking on invoice total calculation)
  - Payment recorded on void invoice (reject with validation error)

#### REQ-006: Webhook Handling Infrastructure
- **Description**: Reliable webhook processing for Stripe, PayPal, QuickBooks, and Xero
- **Priority**: P0
- **Requirements**:
  1. Dedicated webhook endpoints per provider (not behind auth middleware)
  2. Signature verification for each provider (Stripe: HMAC-SHA256, PayPal: webhook ID verification, QBO: Intuit verification, Xero: webhook key)
  3. Idempotent processing (store processed event IDs to prevent duplicates)
  4. Async processing via Laravel queue (respond 200 immediately, process in background)
  5. Dead letter handling for failed webhook processing
  6. Webhook event logging for debugging and audit trail
- **Edge Cases**:
  - Out-of-order webhook delivery (check current state before applying)
  - Duplicate delivery (idempotency key check)
  - Webhook endpoint downtime (providers retry; process backlog on recovery)
- **Error Scenarios**:
  - Invalid signature (reject with 401, log attempt)
  - Unknown event type (acknowledge with 200, log for review)
  - Processing failure (move to dead letter queue, alert admin)

### 3.2 User Workflows

```
Admin connects Stripe:
    -> Navigate to Settings > Integrations > Payments
    -> Click "Connect Stripe"
    -> Redirect to Stripe OAuth (Stripe Connect)
    -> Authorize Solidtime to accept payments on their behalf
    -> Redirect back to Solidtime with success message
    -> Stripe integration shows as "Connected" with account details

Admin connects QuickBooks Online:
    -> Navigate to Settings > Integrations > Accounting
    -> Click "Connect QuickBooks Online"
    -> Redirect to Intuit OAuth 2.0
    -> Select QuickBooks company
    -> Authorize access
    -> Redirect back to Solidtime
    -> System auto-matches clients to QBO customers by name
    -> Admin reviews and adjusts client mapping
    -> QuickBooks shows as "Connected" with company name

Client pays invoice online:
    -> Client receives invoice email with "Pay Now" button
    -> Clicks "Pay Now" -> redirected to Stripe Checkout (or PayPal)
    -> Enters payment details
    -> Payment processes successfully
    -> Redirected to success page
    -> Stripe/PayPal sends webhook to Solidtime
    -> Solidtime records payment, updates invoice to "Paid"
    -> If accounting sync enabled, payment pushed to QBO/Xero
    -> Admin sees payment in invoice detail and payments list

Admin records manual payment:
    -> Open invoice detail page
    -> Click "Record Payment"
    -> Enter amount, date, payment method (check/bank transfer/cash/other), reference number
    -> Save payment
    -> Invoice status updates (paid/partially paid)
    -> If accounting sync enabled, payment pushed to QBO/Xero

Admin syncs invoice to accounting:
    -> Invoice is sent to client
    -> System automatically queues sync to connected accounting system
    -> Invoice appears in QBO/Xero as draft or approved invoice
    -> Sync status shown on invoice detail page
    -> If sync fails, admin can retry manually

Admin views payment history:
    -> Navigate to invoices list
    -> Filter by payment status (unpaid/partially paid/paid)
    -> Click invoice to see detail
    -> Payment history section shows all payments with:
       - Date, amount, method, reference, sync status
    -> Totals show: Invoice total, Amount paid, Amount due
```

### 3.3 Business Rules

#### Payment Recording
1. Payments can only be recorded against invoices with status `sent`, `viewed`, `overdue`, or `partially_paid`
2. Payments cannot be recorded against `draft` or `void` invoices
3. Payment amount must be positive (refunds are separate negative records with type `refund`)
4. Invoice status transitions on payment:
   - Total payments >= invoice total -> status = `paid`
   - Total payments > 0 but < invoice total -> status = `partially_paid`
   - Refund reduces total payments -> recalculate status
5. All payment amounts stored in the invoice's currency (no cross-currency payments in v1)
6. Payment amounts stored in smallest currency unit (cents for USD/EUR, etc.)

#### Integration Connection Rules
1. Only users with `integrations:manage` permission can connect/disconnect integrations
2. Only one Stripe account per organization
3. Only one PayPal account per organization
4. Only one QuickBooks connection per organization (one company)
5. Only one Xero connection per organization (one tenant)
6. Disconnecting a payment provider does not void existing payments
7. Disconnecting an accounting integration does not remove synced data from the external system

#### Accounting Sync Rules
1. Invoices are synced when status changes to `sent` (configurable: auto or manual)
2. Payments are synced immediately upon recording
3. Sync is one-way: Solidtime -> Accounting system (v1)
4. Failed syncs are retried up to 3 times with exponential backoff
5. After 3 failures, sync is marked as `failed` and admin is notified
6. Admin can manually trigger sync or re-sync for individual invoices/payments
7. Client/Customer mapping is required before syncing (auto-match offered during setup)

#### Client Mapping for Accounting Sync
1. On initial connection, system fetches all customers/contacts from accounting system
2. Auto-match by exact name comparison (case-insensitive)
3. Admin can manually map unmapped clients
4. Admin can create new customer/contact in accounting system from Solidtime client
5. Mapping is stored in `accounting_client_mappings` table
6. Unmapped clients block invoice sync (with clear error message)

---

## 4. Technical Requirements & Constraints

### 4.1 System Architecture

```
+---------------------------------------------------------------------+
|                        Frontend (Vue.js 3)                           |
+---------------------------------------------------------------------+
|  +------------------+  +--------------------+  +------------------+  |
|  | Settings/        |  | InvoiceDetail.vue  |  | PaymentsList.vue |  |
|  | Integrations.vue |  | (extended)         |  |                  |  |
|  | - Connect Stripe |  | - Payment history  |  | - All payments   |  |
|  | - Connect PayPal |  | - Record payment   |  | - Filter/search  |  |
|  | - Connect QBO    |  | - Pay Now link     |  | - Sync status    |  |
|  | - Connect Xero   |  | - Sync status      |  |                  |  |
|  | - Client mapping |  +--------------------+  +------------------+  |
|  +--------+---------+                                                |
|           |                                                          |
|           v                                                          |
|  +--------------------------------------+                            |
|  | usePaymentsStore.ts (Pinia)          |                            |
|  | useIntegrationsStore.ts (Pinia)      |                            |
|  | - payments, integrations state       |                            |
|  | - recordPayment() / syncInvoice()    |                            |
|  | - connectProvider() / disconnect()   |                            |
|  +------------------+-------------------+                            |
|                     | HTTP/JSON                                      |
+---------------------+------------------------------------------------+
                      v
+---------------------------------------------------------------------+
|                        Backend (Laravel 11)                          |
+---------------------------------------------------------------------+
|  +-----------------------------------+                               |
|  | PaymentController.php             |                               |
|  | - index()     GET /payments       |                               |
|  | - store()     POST /payments      |                               |
|  | - show()      GET /payments/{id}  |                               |
|  | - void()      POST /payments/{id}/void |                          |
|  +----------------+------------------+                               |
|                   |                                                  |
|  +-----------------------------------+                               |
|  | PaymentIntegrationController.php         |                               |
|  | - index()     GET /integrations   |                               |
|  | - connect()   POST /integrations/{provider}/connect |             |
|  | - callback()  GET /integrations/{provider}/callback  |            |
|  | - disconnect() DELETE /integrations/{provider}       |            |
|  | - clientMappings() GET /integrations/{provider}/client-mappings | |
|  | - updateClientMapping() PUT /integrations/client-mappings/{id}  | |
|  | - syncInvoice() POST /integrations/sync-invoice     |            |
|  +----------------+------------------+                               |
|                   |                                                  |
|  +-----------------------------------+                               |
|  | PaymentWebhookController.php             |  (NO auth middleware)         |
|  | - stripe()    POST /webhooks/stripe    |                          |
|  | - paypal()    POST /webhooks/paypal    |                          |
|  +----------------+------------------+                               |
|                   |                                                  |
|                   v                                                  |
|  +-----------------------------------+  +-------------------------+  |
|  | PaymentService.php                |  | AccountingSyncService   |  |
|  | - recordPayment()                 |  | - syncInvoice()         |  |
|  | - recordWebhookPayment()          |  | - syncPayment()         |  |
|  | - voidPayment()                   |  | - mapClient()           |  |
|  | - updateInvoiceStatus()           |  | - fetchExternalClients()|  |
|  +----------------+------------------+  +------------+------------+  |
|                   |                                  |               |
|                   v                                  v               |
|  +-----------------------------------+  +-------------------------+  |
|  | StripeService.php                 |  | QuickBooksService.php   |  |
|  | - createCheckoutSession()         |  | - pushInvoice()         |  |
|  | - handleWebhook()                 |  | - pushPayment()         |  |
|  | - verifySignature()               |  | - fetchCustomers()      |  |
|  | - processRefund()                 |  | - createCustomer()      |  |
|  +-----------------------------------+  | - refreshToken()        |  |
|  +-----------------------------------+  +-------------------------+  |
|  | PayPalService.php                 |  +-------------------------+  |
|  | - createOrder()                   |  | XeroService.php         |  |
|  | - handleWebhook()                 |  | - pushInvoice()         |  |
|  | - verifySignature()               |  | - pushPayment()         |  |
|  | - processRefund()                 |  | - fetchContacts()       |  |
|  +-----------------------------------+  | - createContact()       |  |
|                   |                     | - refreshToken()        |  |
|                   v                     +-------------------------+  |
|  +-----------------------------------+                               |
|  | Models:                           |                               |
|  | - Payment                         |  (NEW)                       |
|  | - PaymentIntegration              |  (NEW)                       |
|  | - AccountingIntegration           |  (NEW)                       |
|  | - AccountingClientMapping         |  (NEW)                       |
|  | - AccountingSyncLog               |  (NEW)                       |
|  | - WebhookEvent                    |  (NEW)                       |
|  | - Invoice (from Feature 04)       |  (MODIFIED - add relations) |
|  +-----------------------------------+                               |
+---------------------------------------------------------------------+
                      |
                      v (Queued Jobs)
+---------------------------------------------------------------------+
|  +-----------------------------------+  +-------------------------+  |
|  | ProcessWebhookJob                 |  | SyncInvoiceJob          |  |
|  | SyncPaymentJob                    |  | RefreshOAuthTokenJob    |  |
|  | RetryFailedSyncJob                |  |                         |  |
|  +-----------------------------------+  +-------------------------+  |
+---------------------------------------------------------------------+
```

### 4.2 Data Models

#### Database Schema (New Tables)

```sql
-- Payment integrations (Stripe, PayPal)
CREATE TABLE payment_integrations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    provider VARCHAR(50) NOT NULL,          -- 'stripe', 'paypal'
    provider_account_id VARCHAR(255),       -- Stripe account ID or PayPal merchant ID
    access_token TEXT,                      -- Encrypted
    refresh_token TEXT,                     -- Encrypted
    token_expires_at TIMESTAMP NULL,
    settings JSONB DEFAULT '{}',            -- Provider-specific settings
    is_active BOOLEAN DEFAULT TRUE,
    connected_at TIMESTAMP NOT NULL,
    disconnected_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (organization_id, provider)
);

-- Accounting integrations (QuickBooks, Xero)
CREATE TABLE accounting_integrations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    provider VARCHAR(50) NOT NULL,          -- 'quickbooks', 'xero'
    provider_tenant_id VARCHAR(255),        -- QBO company ID or Xero tenant ID
    provider_tenant_name VARCHAR(255),      -- Human-readable company name
    access_token TEXT,                      -- Encrypted
    refresh_token TEXT,                     -- Encrypted
    token_expires_at TIMESTAMP NULL,
    refresh_token_expires_at TIMESTAMP NULL,
    settings JSONB DEFAULT '{}',            -- Provider-specific settings (auto_sync, etc.)
    is_active BOOLEAN DEFAULT TRUE,
    connected_at TIMESTAMP NOT NULL,
    disconnected_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (organization_id, provider)
);

-- Client-to-external-customer mapping for accounting sync
CREATE TABLE accounting_client_mappings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    accounting_integration_id UUID NOT NULL REFERENCES accounting_integrations(id) ON DELETE CASCADE,
    client_id UUID NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
    external_customer_id VARCHAR(255) NOT NULL,
    external_customer_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (accounting_integration_id, client_id),
    UNIQUE (accounting_integration_id, external_customer_id)
);

-- Payments recorded against invoices
CREATE TABLE payments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    invoice_id UUID NOT NULL,               -- References invoices(id) from Feature 04
    amount INTEGER NOT NULL,                -- In smallest currency unit (cents)
    currency VARCHAR(3) NOT NULL,           -- ISO 4217 currency code
    payment_method VARCHAR(50) NOT NULL,    -- 'stripe', 'paypal', 'manual'
    payment_type VARCHAR(50) NOT NULL DEFAULT 'payment', -- 'payment', 'refund'
    status VARCHAR(50) NOT NULL DEFAULT 'completed', -- 'pending', 'completed', 'failed', 'voided'
    provider_payment_id VARCHAR(255) NULL,  -- Stripe payment_intent ID, PayPal capture ID
    provider_transaction_id VARCHAR(255) NULL, -- Additional provider reference
    reference_number VARCHAR(255) NULL,     -- Manual payment reference (check number, etc.)
    notes TEXT NULL,
    paid_at TIMESTAMP NOT NULL,
    voided_at TIMESTAMP NULL,
    voided_by UUID NULL,                    -- User who voided the payment
    recorded_by UUID NULL REFERENCES users(id), -- User who recorded manual payment
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_payments_invoice_id ON payments(invoice_id);
CREATE INDEX idx_payments_organization_id ON payments(organization_id);
CREATE INDEX idx_payments_provider_payment_id ON payments(provider_payment_id);
CREATE INDEX idx_payments_status ON payments(status);

-- Accounting sync log for tracking what has been synced
CREATE TABLE accounting_sync_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    accounting_integration_id UUID NOT NULL REFERENCES accounting_integrations(id) ON DELETE CASCADE,
    syncable_type VARCHAR(100) NOT NULL,    -- 'invoice', 'payment'
    syncable_id UUID NOT NULL,              -- Invoice ID or Payment ID
    external_id VARCHAR(255) NULL,          -- External system's ID for the synced entity
    sync_action VARCHAR(50) NOT NULL,       -- 'create', 'update'
    status VARCHAR(50) NOT NULL DEFAULT 'pending', -- 'pending', 'completed', 'failed'
    error_message TEXT NULL,
    attempts INTEGER DEFAULT 0,
    last_attempted_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    request_payload JSONB NULL,             -- Stored for debugging
    response_payload JSONB NULL,            -- Stored for debugging
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_accounting_sync_logs_syncable ON accounting_sync_logs(syncable_type, syncable_id);
CREATE INDEX idx_accounting_sync_logs_status ON accounting_sync_logs(status);
CREATE INDEX idx_accounting_sync_logs_integration ON accounting_sync_logs(accounting_integration_id);

-- Webhook events for idempotent processing
CREATE TABLE webhook_events (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    provider VARCHAR(50) NOT NULL,          -- 'stripe', 'paypal'
    event_id VARCHAR(255) NOT NULL,         -- Provider's event ID
    event_type VARCHAR(255) NOT NULL,       -- e.g., 'checkout.session.completed'
    payload JSONB NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'received', -- 'received', 'processing', 'processed', 'failed'
    processed_at TIMESTAMP NULL,
    error_message TEXT NULL,
    attempts INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (provider, event_id)
);

CREATE INDEX idx_webhook_events_status ON webhook_events(status);
CREATE INDEX idx_webhook_events_provider_event ON webhook_events(provider, event_id);
```

#### Frontend Types (TypeScript)

```typescript
// Payment Integration Types
interface PaymentIntegration {
  id: string;
  provider: 'stripe' | 'paypal';
  provider_account_id: string | null;
  is_active: boolean;
  connected_at: string;           // ISO 8601
  settings: Record<string, unknown>;
}

interface AccountingIntegration {
  id: string;
  provider: 'quickbooks' | 'xero';
  provider_tenant_id: string | null;
  provider_tenant_name: string | null;
  is_active: boolean;
  connected_at: string;           // ISO 8601
  settings: {
    auto_sync_invoices: boolean;
    auto_sync_payments: boolean;
  };
}

interface AccountingClientMapping {
  id: string;
  accounting_integration_id: string;
  client_id: string;
  client_name: string;            // From Solidtime client
  external_customer_id: string;
  external_customer_name: string;
}

interface ExternalCustomer {
  id: string;
  name: string;
  email: string | null;
}

// Payment Types
interface Payment {
  id: string;
  invoice_id: string;
  amount: number;                 // In cents
  currency: string;
  payment_method: 'stripe' | 'paypal' | 'manual';
  payment_type: 'payment' | 'refund';
  status: 'pending' | 'completed' | 'failed' | 'voided';
  provider_payment_id: string | null;
  reference_number: string | null;
  notes: string | null;
  paid_at: string;                // ISO 8601
  voided_at: string | null;
  recorded_by: string | null;
}

interface PaymentSummary {
  invoice_id: string;
  invoice_total: number;
  total_paid: number;
  total_refunded: number;
  amount_due: number;
  payment_count: number;
}

// Sync Types
interface AccountingSyncLog {
  id: string;
  syncable_type: 'invoice' | 'payment';
  syncable_id: string;
  external_id: string | null;
  sync_action: 'create' | 'update';
  status: 'pending' | 'completed' | 'failed';
  error_message: string | null;
  attempts: number;
  last_attempted_at: string | null;
  completed_at: string | null;
}

// Store state
interface IntegrationsState {
  paymentIntegrations: PaymentIntegration[];
  accountingIntegrations: AccountingIntegration[];
  clientMappings: AccountingClientMapping[];
  externalCustomers: ExternalCustomer[];
  isLoading: boolean;
  error: string | null;
}

interface PaymentsState {
  payments: Payment[];
  paymentSummaries: Map<string, PaymentSummary>;
  syncLogs: AccountingSyncLog[];
  isLoading: boolean;
  error: string | null;
}
```

### 4.3 API Contracts

#### Payment Endpoints

##### GET /api/v1/organizations/{organization}/payments
List payments for the organization with filtering.

```yaml
Parameters:
  organization: string (path, required)
  invoice_id: string (query, optional)     # Filter by invoice
  status: string (query, optional)         # Filter by status
  payment_method: string (query, optional) # Filter by method
  from: string (query, optional)           # Date range start (YYYY-MM-DD)
  to: string (query, optional)             # Date range end (YYYY-MM-DD)
  page: int (query, optional, default: 1)
  per_page: int (query, optional, default: 25, max: 100)
Request Headers:
  Authorization: Bearer {token}
Response 200:
  data: Array<{
    id: string,
    invoice_id: string,
    invoice_number: string,
    client_name: string,
    amount: int,
    currency: string,
    payment_method: string,
    payment_type: string,
    status: string,
    provider_payment_id: string | null,
    reference_number: string | null,
    notes: string | null,
    paid_at: string,
    voided_at: string | null,
    recorded_by: { id: string, name: string } | null,
    sync_status: string | null
  }>
  meta: { current_page, last_page, per_page, total }
Permission: payments:view:own OR payments:view:all
```

##### POST /api/v1/organizations/{organization}/payments
Record a manual payment against an invoice.

```yaml
Parameters:
  organization: string (path, required)
Request Body:
  invoice_id: string (required, valid invoice UUID)
  amount: int (required, > 0, in cents)
  payment_method: string (required, one of: 'manual')
  reference_number: string (optional, max: 255)
  notes: string (optional, max: 1000)
  paid_at: string (required, YYYY-MM-DD)
Response 201:
  data: Payment
Response 422:
  errors: { field: [messages] }
Middleware: check-organization-blocked
Permission: payments:create:own OR payments:create:all
```

##### GET /api/v1/organizations/{organization}/payments/{payment}
Get a single payment with details.

```yaml
Parameters:
  organization: string (path, required)
  payment: string (path, required)
Response 200:
  data: Payment
Permission: payments:view:own OR payments:view:all
```

##### POST /api/v1/organizations/{organization}/payments/{payment}/void
Void a recorded payment.

```yaml
Parameters:
  organization: string (path, required)
  payment: string (path, required)
Request Body:
  reason: string (optional, max: 500)
Response 200:
  data: Payment (with status: 'voided')
Response 422:
  errors: { message: "Payment is already voided" }
Middleware: check-organization-blocked
Permission: payments:create:all
```

#### Integration Endpoints

##### GET /api/v1/organizations/{organization}/integrations
List all connected integrations for the organization.

```yaml
Parameters:
  organization: string (path, required)
Response 200:
  data: {
    payment_integrations: Array<PaymentIntegration>,
    accounting_integrations: Array<AccountingIntegration>
  }
Permission: integrations:manage
```

##### POST /api/v1/organizations/{organization}/integrations/{provider}/connect
Initiate OAuth connection flow for a provider.

```yaml
Parameters:
  organization: string (path, required)
  provider: string (path, required, one of: 'stripe', 'paypal', 'quickbooks', 'xero')
Response 200:
  data: {
    redirect_url: string   # OAuth authorization URL
  }
Middleware: check-organization-blocked
Permission: integrations:manage
```

##### GET /api/v1/organizations/{organization}/integrations/{provider}/callback
OAuth callback endpoint (also accessible via web route for browser redirect).

```yaml
Parameters:
  organization: string (path, required)
  provider: string (path, required)
  code: string (query, from OAuth provider)
  state: string (query, CSRF token)
Response 302:
  Redirect to: /settings/integrations?status=connected&provider={provider}
Response 302 (error):
  Redirect to: /settings/integrations?status=error&provider={provider}&message={error}
Permission: integrations:manage (validated via state token)
```

##### DELETE /api/v1/organizations/{organization}/integrations/{provider}
Disconnect an integration.

```yaml
Parameters:
  organization: string (path, required)
  provider: string (path, required)
Response 200:
  data: { message: "Integration disconnected" }
Middleware: check-organization-blocked
Permission: integrations:manage
```

##### GET /api/v1/organizations/{organization}/integrations/{provider}/client-mappings
List client-to-external-customer mappings for an accounting integration.

```yaml
Parameters:
  organization: string (path, required)
  provider: string (path, required, one of: 'quickbooks', 'xero')
Response 200:
  data: {
    mappings: Array<AccountingClientMapping>,
    unmapped_clients: Array<{ id: string, name: string }>,
    external_customers: Array<ExternalCustomer>
  }
Permission: integrations:manage
```

##### PUT /api/v1/organizations/{organization}/integrations/client-mappings/{mapping}
Update or create a client-to-external-customer mapping.

```yaml
Parameters:
  organization: string (path, required)
  mapping: string (path, required, or 'new' for creation)
Request Body:
  client_id: string (required)
  external_customer_id: string (required)
Response 200:
  data: AccountingClientMapping
Middleware: check-organization-blocked
Permission: integrations:manage
```

##### POST /api/v1/organizations/{organization}/integrations/sync-invoice
Manually trigger invoice sync to accounting system.

```yaml
Parameters:
  organization: string (path, required)
Request Body:
  invoice_id: string (required)
Response 202:
  data: { message: "Sync queued", sync_log_id: string }
Response 422:
  errors: { message: "No accounting integration connected" }
Middleware: check-organization-blocked
Permission: integrations:manage
```

##### GET /api/v1/organizations/{organization}/integrations/sync-logs
List sync logs for debugging and status monitoring.

```yaml
Parameters:
  organization: string (path, required)
  syncable_type: string (query, optional, 'invoice' or 'payment')
  syncable_id: string (query, optional)
  status: string (query, optional)
  page: int (query, optional, default: 1)
  per_page: int (query, optional, default: 25)
Response 200:
  data: Array<AccountingSyncLog>
  meta: { current_page, last_page, per_page, total }
Permission: integrations:manage
```

#### Webhook Endpoints (No Auth Middleware)

##### POST /api/v1/webhooks/stripe
Receive Stripe webhook events.

```yaml
Request Headers:
  Stripe-Signature: string (required)
Request Body: Raw JSON (Stripe event payload)
Response 200:
  { received: true }
Response 401:
  { error: "Invalid signature" }
Events handled:
  - checkout.session.completed    -> Record payment
  - charge.refunded               -> Record refund
  - account.application.deauthorized -> Mark integration disconnected
```

##### POST /api/v1/webhooks/paypal
Receive PayPal webhook events.

```yaml
Request Headers:
  PayPal-Transmission-Id: string
  PayPal-Transmission-Time: string
  PayPal-Transmission-Sig: string
  PayPal-Cert-Url: string
  PayPal-Auth-Algo: string
Request Body: Raw JSON (PayPal event payload)
Response 200:
  { received: true }
Response 401:
  { error: "Invalid signature" }
Events handled:
  - PAYMENT.CAPTURE.COMPLETED     -> Record payment
  - PAYMENT.CAPTURE.REFUNDED      -> Record refund
  - CUSTOMER.DISPUTE.CREATED      -> Mark payment as disputed
```

#### Payment Link Endpoint (Public, No Auth)

##### GET /pay/{token}
Public payment page for invoice recipients.

```yaml
Parameters:
  token: string (path, required, signed URL token)
Response 302:
  Redirect to Stripe Checkout URL or PayPal approval URL
Response 404:
  Invoice not found or payment link expired
Response 410:
  Invoice already paid or voided
```

### 4.4 Performance Requirements

| Metric | Target | Measurement |
|--------|--------|-------------|
| Payment recording latency | < 500ms | API response time for manual payment |
| Webhook processing time | < 2s | From receipt to payment record creation |
| Webhook response time | < 200ms | Time to respond 200 to webhook provider |
| OAuth flow completion | < 5s | Redirect to callback processing |
| Accounting sync latency | < 10s | Time to push invoice/payment to external API |
| Payment list page load | < 500ms | API response time for paginated list |
| Integration settings load | < 300ms | API response time for integrations list |

### 4.5 Security Requirements

1. **Token Encryption**: All OAuth access tokens and refresh tokens stored using Laravel's `encrypt()` / `Crypt::encryptString()`. Never logged or exposed in API responses.
2. **Webhook Signature Verification**: Every webhook request verified against provider's signing secret before processing. Invalid signatures rejected with 401.
3. **CSRF Protection on OAuth**: State parameter used in all OAuth flows; validated on callback to prevent CSRF attacks.
4. **PCI Compliance**: No credit card data touches Solidtime servers. All payment processing occurs on Stripe/PayPal hosted pages. System is SAQ-A eligible.
5. **Payment Link Security**: Payment URLs use signed tokens with expiration (24 hours). Token includes invoice ID and amount hash to prevent tampering.
6. **Organization Scoping**: All payment and integration queries scoped to the current organization. No cross-organization data access.
7. **Write Protection**: All mutation endpoints use `check-organization-blocked` middleware.
8. **Audit Logging**: All payment recordings, voiding, integration connections/disconnections, and sync operations logged via `CustomAuditable` trait and `accounting_sync_logs`.
9. **Secret Rotation**: Webhook signing secrets stored in environment variables, rotatable without code deployment.
10. **Permission Enforcement**: `integrations:manage` restricted to Owner and Admin roles. Payment recording follows existing `payments:create:*` permission pattern.

---

## 5. User Stories with Acceptance Criteria

### USR-001: Connect Stripe Payment Provider
**As an** organization Admin
**I want to** connect my Stripe account to Solidtime
**So that** my clients can pay invoices online via credit card

**Priority**: P0 | **Effort**: 8 SP | **Sprint**: 1-2

**Acceptance Criteria**:
- [ ] "Connect Stripe" button visible in Organization Settings > Integrations > Payments
- [ ] Clicking button redirects to Stripe OAuth authorization page
- [ ] After authorization, redirected back to Solidtime with success message
- [ ] Integration shows as "Connected" with Stripe account name/ID
- [ ] "Disconnect" button available with confirmation dialog
- [ ] Disconnecting marks integration as inactive (does not delete payment history)
- [ ] Only users with `integrations:manage` permission can see and use integration settings
- [ ] Error state shown if OAuth flow fails or is cancelled

### USR-002: Connect PayPal Payment Provider
**As an** organization Admin
**I want to** connect my PayPal account to Solidtime
**So that** my clients can pay invoices via PayPal

**Priority**: P1 | **Effort**: 5 SP | **Sprint**: 2

**Acceptance Criteria**:
- [ ] "Connect PayPal" button visible in Integrations > Payments
- [ ] OAuth flow completes successfully with PayPal
- [ ] Integration shows as "Connected" with PayPal merchant info
- [ ] Can disconnect PayPal independently of Stripe
- [ ] Both Stripe and PayPal can be connected simultaneously
- [ ] Error handling for OAuth failures

### USR-003: Client Pays Invoice via Stripe
**As a** client receiving an invoice
**I want to** click a "Pay Now" link and pay immediately
**So that** I can settle my invoice quickly without manual bank transfers

**Priority**: P0 | **Effort**: 8 SP | **Sprint**: 2-3

**Acceptance Criteria**:
- [ ] Invoice email includes a "Pay Now" button/link when Stripe is connected
- [ ] Invoice PDF includes payment URL
- [ ] Clicking link opens Stripe Checkout with correct amount and currency
- [ ] After successful payment, client sees confirmation page
- [ ] Payment is automatically recorded in Solidtime
- [ ] Invoice status updates to "Paid" (or "Partially Paid" if applicable)
- [ ] Admin sees payment in invoice detail with Stripe transaction reference
- [ ] Duplicate payments prevented (Checkout Session is one-time use)

### USR-004: Client Pays Invoice via PayPal
**As a** client receiving an invoice
**I want to** pay using my PayPal account
**So that** I can use my preferred payment method

**Priority**: P1 | **Effort**: 5 SP | **Sprint**: 3

**Acceptance Criteria**:
- [ ] Invoice includes PayPal payment option when PayPal is connected
- [ ] If both Stripe and PayPal connected, client can choose payment method
- [ ] PayPal payment flow completes successfully
- [ ] Payment recorded and invoice status updated via webhook
- [ ] PayPal transaction ID stored with payment record

### USR-005: Record Manual Payment
**As an** organization Admin or Manager
**I want to** record a payment received outside Solidtime (check, bank transfer)
**So that** invoice payment status is accurate

**Priority**: P0 | **Effort**: 3 SP | **Sprint**: 2

**Acceptance Criteria**:
- [ ] "Record Payment" button on invoice detail page
- [ ] Form fields: amount, date, payment method dropdown (Check, Bank Transfer, Cash, Other), reference number, notes
- [ ] Amount pre-filled with remaining balance
- [ ] Validation: amount > 0, date is valid, invoice is in payable status
- [ ] Invoice status updates after saving
- [ ] Payment appears in payment history
- [ ] Can record partial payment (updates status to "Partially Paid")

### USR-006: Void a Payment
**As an** organization Admin
**I want to** void an incorrectly recorded payment
**So that** the invoice payment status is corrected

**Priority**: P1 | **Effort**: 2 SP | **Sprint**: 3

**Acceptance Criteria**:
- [ ] "Void" button on payment record (with confirmation dialog)
- [ ] Voided payments marked with strikethrough in payment history
- [ ] Invoice status recalculated after voiding
- [ ] Reason for voiding stored with payment
- [ ] Only `payments:create:all` permission can void payments
- [ ] Online payments (Stripe/PayPal) cannot be voided in Solidtime (must refund via provider)

### USR-007: Connect QuickBooks Online
**As an** organization Admin
**I want to** connect Solidtime to my QuickBooks Online account
**So that** invoices and payments sync automatically to my accounting system

**Priority**: P0 | **Effort**: 8 SP | **Sprint**: 3-4

**Acceptance Criteria**:
- [ ] "Connect QuickBooks Online" button in Integrations > Accounting
- [ ] OAuth flow redirects to Intuit login and authorization
- [ ] After connection, system displays connected company name
- [ ] Client mapping interface shows:
  - Auto-matched clients (by name)
  - Unmapped Solidtime clients
  - Available QuickBooks customers
- [ ] Admin can manually create mapping between Solidtime client and QBO customer
- [ ] Admin can create new QBO customer from Solidtime client data
- [ ] Settings toggle for auto-sync invoices and auto-sync payments
- [ ] Disconnect button with confirmation

### USR-008: Connect Xero
**As an** organization Admin
**I want to** connect Solidtime to my Xero account
**So that** my invoices and payments sync to Xero

**Priority**: P1 | **Effort**: 5 SP | **Sprint**: 4

**Acceptance Criteria**:
- [ ] "Connect Xero" button in Integrations > Accounting
- [ ] OAuth flow completes with tenant selection (if multiple Xero orgs)
- [ ] Client mapping interface (same UX as QuickBooks)
- [ ] Auto-sync toggles available
- [ ] Can be connected simultaneously with a payment provider

### USR-009: Automatic Invoice Sync to Accounting
**As an** organization Admin
**I want** invoices to automatically sync to my accounting system when sent
**So that** my books stay up to date without manual data entry

**Priority**: P0 | **Effort**: 8 SP | **Sprint**: 4

**Acceptance Criteria**:
- [ ] When auto-sync is enabled, invoice syncs to accounting system when status changes to `sent`
- [ ] Sync status badge shown on invoice detail page: "Synced", "Pending", "Failed"
- [ ] Failed sync shows error message and "Retry" button
- [ ] Manual "Sync Now" button available regardless of auto-sync setting
- [ ] Invoice line items mapped correctly to accounting system line items
- [ ] Client/Customer correctly linked via mapping
- [ ] Invoice number, dates, amounts, tax all transferred accurately
- [ ] Duplicate detection prevents creating duplicate invoices in accounting system

### USR-010: Automatic Payment Sync to Accounting
**As an** organization Admin
**I want** payments to automatically sync to my accounting system when recorded
**So that** my payment records match between systems

**Priority**: P1 | **Effort**: 5 SP | **Sprint**: 4

**Acceptance Criteria**:
- [ ] When auto-sync is enabled, payments sync immediately after recording
- [ ] Payment linked to the correct invoice in accounting system
- [ ] Payment amount and date transferred accurately
- [ ] Sync status shown on payment record
- [ ] Manual payments and online payments both sync
- [ ] Voided payments trigger an update in accounting system (or create adjustment)

### USR-011: View Payment History
**As an** organization Admin or Manager
**I want to** see all payments across invoices in one place
**So that** I can track cash flow and outstanding receivables

**Priority**: P1 | **Effort**: 3 SP | **Sprint**: 3

**Acceptance Criteria**:
- [ ] Payments list page accessible from main navigation (under Invoicing section)
- [ ] Filterable by: date range, payment method, status, client
- [ ] Sortable by: date, amount, client name
- [ ] Each row shows: date, invoice number, client, amount, method, status, sync status
- [ ] Click payment row to navigate to invoice detail
- [ ] Summary bar showing: total received, total pending, total this month

### USR-012: View Sync Status and Logs
**As an** organization Admin
**I want to** see the sync status of all invoices and payments
**So that** I can identify and resolve sync failures

**Priority**: P2 | **Effort**: 3 SP | **Sprint**: 5

**Acceptance Criteria**:
- [ ] Sync log page in Integration settings
- [ ] Filterable by: entity type (invoice/payment), status (pending/completed/failed)
- [ ] Failed syncs show error message
- [ ] "Retry" button for failed syncs
- [ ] "Retry All Failed" bulk action
- [ ] Sync log entries show: timestamp, entity type, entity reference, action, status, error

---

## 6. Task Breakdown Structure

See `task_assignments_20260209.md` for the full task table.

| Task ID | Description | Type | Effort | Dependencies |
|---------|-------------|------|--------|--------------|
| PAY-001 | Create database migrations for all new tables | Backend | 8h | None |
| PAY-002 | Create Eloquent models (Payment, PaymentIntegration, AccountingIntegration, AccountingClientMapping, AccountingSyncLog, WebhookEvent) | Backend | 8h | PAY-001 |
| PAY-003 | Create PaymentService with core payment business logic | Backend | 12h | PAY-002 |
| PAY-004 | Create PaymentController with CRUD endpoints | Backend | 8h | PAY-003 |
| PAY-005 | Create request validation classes for payment endpoints | Backend | 4h | PAY-004 |
| PAY-006 | Create PaymentIntegrationController with OAuth flow endpoints | Backend | 8h | PAY-002 |
| PAY-007 | Create StripeService for Stripe Connect OAuth and Checkout Session creation | Backend | 12h | PAY-002 |
| PAY-008 | Create PayPalService for PayPal OAuth and Order creation | Backend | 10h | PAY-002 |
| PAY-009 | Create PaymentWebhookController with Stripe and PayPal handlers | Backend | 8h | PAY-007, PAY-008 |
| PAY-010 | Implement webhook signature verification for Stripe and PayPal | Backend | 4h | PAY-009 |
| PAY-011 | Create ProcessWebhookJob for async webhook processing | Backend | 6h | PAY-009, PAY-003 |
| PAY-012 | Create QuickBooksService for OAuth, invoice push, payment push, customer fetch | Backend | 16h | PAY-002 |
| PAY-013 | Create XeroService for OAuth, invoice push, payment push, contact fetch | Backend | 14h | PAY-002 |
| PAY-014 | Create AccountingSyncService for orchestrating sync operations | Backend | 8h | PAY-012, PAY-013 |
| PAY-015 | Create SyncInvoiceJob and SyncPaymentJob queued jobs | Backend | 6h | PAY-014 |
| PAY-016 | Create RefreshOAuthTokenJob for proactive token refresh | Backend | 4h | PAY-012, PAY-013 |
| PAY-017 | Register all API routes for payments, integrations, webhooks | Backend | 2h | PAY-004, PAY-006, PAY-009 |
| PAY-018 | Create request validation classes for integration endpoints | Backend | 4h | PAY-006 |
| PAY-019 | Create public payment link route and redirect logic | Backend | 4h | PAY-007, PAY-008 |
| PAY-020 | Add new permissions to role definitions | Backend | 2h | None |
| PAY-021 | Update OpenAPI spec and regenerate TypeScript client | Backend / Docs | 6h | PAY-017 |
| PAY-022 | Create usePaymentsStore Pinia store with TypeScript types | Frontend | 8h | PAY-021 |
| PAY-023 | Create useIntegrationsStore Pinia store with TypeScript types | Frontend | 6h | PAY-021 |
| PAY-024 | Create IntegrationSettings.vue page (payment and accounting providers) | Frontend | 12h | PAY-023 |
| PAY-025 | Create ClientMappingDialog.vue for accounting client mapping | Frontend | 8h | PAY-024 |
| PAY-026 | Create PaymentsList.vue page with filtering and sorting | Frontend | 8h | PAY-022 |
| PAY-027 | Extend InvoiceDetail.vue with payment history and Record Payment form | Frontend | 10h | PAY-022 |
| PAY-028 | Create RecordPaymentDialog.vue modal component | Frontend | 4h | PAY-027 |
| PAY-029 | Create PaymentStatusBadge.vue and SyncStatusBadge.vue components | Frontend | 2h | PAY-022 |
| PAY-030 | Create SyncLogsPage.vue for viewing sync history | Frontend | 6h | PAY-023 |
| PAY-031 | Add web routes and sidebar navigation for payments and integrations | Frontend | 2h | PAY-024, PAY-026 |
| PAY-032 | Create payment success/failure public pages | Frontend | 4h | PAY-019 |
| PAY-033 | Backend endpoint tests for PaymentController | Testing | 8h | PAY-004, PAY-005, PAY-017 |
| PAY-034 | Backend endpoint tests for PaymentIntegrationController | Testing | 8h | PAY-006, PAY-018, PAY-017 |
| PAY-035 | Backend unit tests for PaymentService | Testing | 6h | PAY-003 |
| PAY-036 | Backend unit tests for StripeService | Testing | 6h | PAY-007 |
| PAY-037 | Backend unit tests for PayPalService | Testing | 6h | PAY-008 |
| PAY-038 | Backend unit tests for QuickBooksService | Testing | 8h | PAY-012 |
| PAY-039 | Backend unit tests for XeroService | Testing | 8h | PAY-013 |
| PAY-040 | Backend unit tests for AccountingSyncService | Testing | 6h | PAY-014 |
| PAY-041 | Backend tests for webhook handling and signature verification | Testing | 8h | PAY-009, PAY-010, PAY-011 |
| PAY-042 | Frontend component tests for IntegrationSettings | Testing | 6h | PAY-024 |
| PAY-043 | Frontend component tests for PaymentsList and RecordPayment | Testing | 6h | PAY-026, PAY-028 |
| PAY-044 | E2E Playwright tests for payment and integration flows | Testing | 10h | PAY-031, PAY-027 |
| PAY-045 | Add JSDoc comments to Pinia stores | Docs | 2h | PAY-022, PAY-023 |
| PAY-046 | Create environment variable documentation for API keys and secrets | Docs | 2h | PAY-007, PAY-008, PAY-012, PAY-013 |
| PAY-047 | Implement retry logic for failed accounting syncs (scheduled command) | Backend | 4h | PAY-015 |
| PAY-048 | Add encryption for OAuth tokens in database | Backend | 4h | PAY-002 |

**Total Effort**: 320 hours (~213 SP across 5 sprints)

---

## 7. Dependencies & Integration Points

### 7.1 Internal Dependencies

| Dependency | Description | Impact |
|------------|-------------|--------|
| Feature 04 (Invoicing) | `Invoice` model, `InvoiceController`, `InvoiceService` | **Hard dependency** -- must be implemented first |
| `Organization` Model | Route model binding, scoping, currency | Read-only |
| `Client` Model | Client data for accounting sync customer mapping | Read-only |
| `Member` Model | Permission checks, user scoping | Read-only |
| `User` Model | `recorded_by` on payments, OAuth state | Read-only |
| `PermissionStore` | Permission checks in controllers | Read-only |
| `BillingContract` | `canAccessPremiumFeatures()` check -- payments feature is Premium/Enterprise | Read-only |

### 7.2 External Dependencies

| Dependency | Version | Purpose |
|------------|---------|---------|
| `stripe/stripe-php` | ^14.0 | Stripe API client (Checkout, Connect, Webhooks) |
| `paypal/paypal-server-sdk` | ^1.0 | PayPal REST API client |
| `quickbooks/v3-php-sdk` | ^6.0 | QuickBooks Online API client |
| `xeroapi/xero-php-oauth2` | ^5.0 | Xero API client with OAuth 2.0 |
| Laravel Queue | (built-in) | Async webhook processing, sync jobs |
| Laravel Encryption | (built-in) | Token encryption at rest |
| Laravel HTTP Client | (built-in) | Fallback HTTP calls where SDK not used |

### 7.3 External Services

| Service | Purpose | API Version | Authentication |
|---------|---------|-------------|----------------|
| Stripe API | Payment processing, Checkout Sessions | 2024-12-18.acacia | OAuth 2.0 (Connect) |
| PayPal REST API | Payment processing, Orders | v2 | OAuth 2.0 |
| QuickBooks Online API | Invoice and payment sync | v3 | OAuth 2.0 |
| Xero API | Invoice and payment sync | 2.0 | OAuth 2.0 |

### 7.4 Downstream Features

This feature is a **foundation** for:
- **Progressive/phased billing** (Feature 7.5): Payment tracking enables milestone-based invoicing with partial payment support
- **Expense reimbursement**: Expense payments could use the same payment infrastructure
- **Automated payment reminders**: Overdue invoice detection + payment status enables automated reminder emails
- **Financial dashboards**: Payment data enables revenue/AR reporting

---

## 8. Risk Assessment & Mitigation

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| Stripe/PayPal API breaking changes | Low | High | Pin SDK versions; subscribe to provider changelogs; integration tests against sandbox |
| OAuth token expiration causing silent sync failures | Medium | High | Proactive token refresh via scheduled job (RefreshOAuthTokenJob); monitoring for token refresh failures; admin notification on disconnect |
| Webhook delivery failures causing missing payments | Low | Critical | Idempotent webhook processing; reconciliation job that polls for missing payments; webhook event logging |
| QuickBooks/Xero API rate limiting | Medium | Medium | Request throttling; exponential backoff; queue-based sync (spread over time) |
| PCI compliance concerns | Low | Critical | Never handle card data; use Stripe Checkout and PayPal hosted pages exclusively; document SAQ-A eligibility |
| Data inconsistency between Solidtime and accounting system | Medium | Medium | One-way sync in v1 (Solidtime is source of truth); sync logs for audit; manual re-sync capability |
| Feature 04 (Invoicing) not ready | Medium | Critical | This feature cannot begin development until Invoicing is implemented; plan sprints accordingly |
| Multiple currency handling | Medium | Medium | v1 restricts to organization's configured currency; validate currency match on payment creation |
| Webhook endpoint abuse/DDoS | Low | Medium | Signature verification rejects invalid requests; rate limiting on webhook endpoints; no expensive processing before verification |
| OAuth state parameter hijacking | Low | High | Signed, time-limited state tokens; verify state on every callback |

---

## 9. Testing & Validation Requirements

### 9.1 Test Strategy

| Type | Coverage Target | Tools |
|------|-----------------|-------|
| Backend Unit Tests | All service methods, all sync logic | PHPUnit |
| API Endpoint Tests | All payment and integration endpoints | PHPUnit (ApiEndpointTestAbstract) |
| Webhook Tests | Signature verification, event processing | PHPUnit with mock payloads |
| Frontend Component Tests | Integration settings, payment forms | Vitest + @vue/test-utils |
| E2E Tests | Critical user paths (connect, pay, sync) | Playwright |
| Integration Tests | Real API sandbox calls (Stripe test mode, QBO sandbox) | PHPUnit with sandbox credentials |

### 9.2 Key Test Scenarios

**Backend -- Payment Service**:
- Record manual payment updates invoice status to `paid`
- Record partial payment updates invoice status to `partially_paid`
- Void payment recalculates invoice status
- Cannot record payment on `draft` invoice
- Cannot record payment on `void` invoice
- Payment amount stored in correct currency unit
- Overpayment allowed but flagged
- Concurrent payment recording does not corrupt totals

**Backend -- Stripe Integration**:
- Stripe Connect OAuth flow generates correct redirect URL
- OAuth callback stores encrypted access token
- Checkout Session created with correct amount and currency
- Webhook signature verification rejects invalid signatures
- `checkout.session.completed` event creates payment record
- `charge.refunded` event creates refund record
- Duplicate webhook events are idempotently handled
- Disconnection marks integration as inactive

**Backend -- PayPal Integration**:
- PayPal OAuth flow generates correct redirect URL
- Order creation with correct amount and currency
- Webhook signature verification
- `PAYMENT.CAPTURE.COMPLETED` creates payment record
- `CUSTOMER.DISPUTE.CREATED` marks payment as disputed

**Backend -- QuickBooks Sync**:
- OAuth flow stores company ID and tokens
- Invoice push creates correct QBO Invoice object
- Payment push creates correct QBO Payment object
- Token refresh before expiration
- Failed sync retried up to 3 times
- Duplicate invoice detection by external reference ID
- Client mapping required before sync
- Auto-match clients by name

**Backend -- Xero Sync**:
- Same test scenarios as QuickBooks adapted for Xero API
- Tenant selection during OAuth
- Rate limiting respected (60 calls/minute)

**Backend -- Webhooks**:
- Invalid signature returns 401
- Valid signature returns 200 immediately
- Event queued for async processing
- Duplicate event ID rejected
- Unknown event type acknowledged but not processed
- Failed processing moves to dead letter queue

**Frontend -- Integration Settings**:
- Connect button initiates OAuth redirect
- Connected state shows account details and disconnect button
- Disconnect shows confirmation dialog
- Client mapping table renders correctly
- Auto-match populates suggestions
- Manual mapping selection works

**Frontend -- Payments**:
- Payment list renders with correct columns
- Filtering by status, method, date works
- Record Payment dialog validates input
- Amount pre-fills with remaining balance
- Void button shows confirmation
- Sync status badges render correctly

**E2E**:
- Connect Stripe integration (using Stripe test mode)
- Record manual payment on invoice
- View payment history across invoices
- Connect QuickBooks (mock OAuth callback)
- View sync logs page
- Void a payment and verify invoice status update

---

## 10. Monitoring & Observability

### 10.1 Metrics to Track

| Metric | Type | Alert Threshold |
|--------|------|-----------------|
| Webhook processing latency (P95) | Performance | > 5s |
| Webhook processing failure rate | Error | > 5% |
| OAuth token refresh failure rate | Error | > 0% (any failure) |
| Accounting sync failure rate | Error | > 10% |
| Accounting sync queue depth | Infrastructure | > 100 pending |
| Payment recording latency (P95) | Performance | > 1s |
| Stripe/PayPal API error rate | Error | > 1% |
| QuickBooks/Xero API error rate | Error | > 5% |
| Payment volume (daily count) | Business | -- |
| Payment amount (daily total) | Business | -- |

### 10.2 Logging

```php
// Structured logging for webhook processing
Log::channel('webhooks')->info('Webhook received', [
    'provider' => 'stripe',
    'event_id' => $event->id,
    'event_type' => $event->type,
    'organization_id' => $organizationId,
]);

// Structured logging for accounting sync
Log::channel('accounting-sync')->info('Invoice sync completed', [
    'provider' => 'quickbooks',
    'invoice_id' => $invoice->id,
    'external_id' => $externalId,
    'organization_id' => $invoice->organization_id,
    'duration_ms' => $duration,
]);

// Structured logging for payment recording
Log::channel('payments')->info('Payment recorded', [
    'payment_id' => $payment->id,
    'invoice_id' => $payment->invoice_id,
    'amount' => $payment->amount,
    'method' => $payment->payment_method,
    'organization_id' => $payment->organization_id,
]);
```

All payment-related model mutations are automatically logged by the existing `CustomAuditable` trait. Additional structured logging added for webhook processing, sync operations, and OAuth flows.

### 10.3 Alerting Rules

- Webhook processing failure rate > 5% for 10 minutes
- Any OAuth token refresh failure (immediate alert)
- Accounting sync queue depth > 100 items for 30 minutes
- Stripe/PayPal API returning 5xx errors for 5 minutes
- QuickBooks/Xero API returning 401 (token expired) after auto-refresh attempt
- No webhook events received from connected provider for 24 hours (provider may have deregistered webhook)

---

## 11. Success Metrics & Definition of Done

### 11.1 Success Metrics

| Metric | Target | Measurement |
|--------|--------|-------------|
| Online payment adoption | 40% of sent invoices paid online within 60 days | Payment method distribution |
| Average days to payment | 30% reduction vs pre-feature baseline | Invoice sent date to payment date |
| Accounting sync usage | 50% of organizations with invoicing connect accounting | Integration connection rate |
| Sync reliability | > 99% sync success rate | Sync log success/failure ratio |
| Webhook processing reliability | > 99.9% successful processing | Webhook event processed/received ratio |
| Manual payment recording | Used by 80% of orgs without online payments | Payment creation rate by method |

### 11.2 Definition of Done

- [ ] All 6 core requirements (REQ-001 through REQ-006) implemented
- [ ] All payment CRUD endpoints working with proper validation and permissions
- [ ] All integration OAuth flows working (Stripe, PayPal, QuickBooks, Xero)
- [ ] Webhook endpoints receiving and processing events correctly
- [ ] Accounting sync pushing invoices and payments to QuickBooks and Xero
- [ ] Client mapping interface functional
- [ ] Backend endpoint tests passing for all new endpoints
- [ ] Backend unit tests passing for all service classes
- [ ] Webhook signature verification tests passing
- [ ] Frontend component tests passing
- [ ] E2E tests passing for critical paths
- [ ] `composer fix && composer analyse` passes
- [ ] `npm run lint:fix && npm run format` passes
- [ ] OpenAPI spec updated and TS client regenerated
- [ ] Sidebar navigation items added
- [ ] Loading and error states handled on all pages
- [ ] OAuth tokens encrypted at rest
- [ ] Environment variable documentation created
- [ ] Webhook signing secrets configurable via environment
- [ ] Payment feature gated behind `canAccessPremiumFeatures()`
- [ ] All new database tables have proper indexes

---

## 12. Technical Debt & Future Considerations

### 12.1 Known Simplifications

1. **One-way sync only**: v1 pushes from Solidtime to accounting systems. Future enhancement: two-way sync to detect payments recorded directly in QBO/Xero and update Solidtime.

2. **No partial payments via Stripe Checkout**: Stripe Checkout enforces the full amount. Future: use Stripe Payment Intents directly for partial payment support.

3. **Single currency per invoice**: No cross-currency payment support. Future: currency conversion using exchange rates.

4. **No FreshBooks integration**: Everhour supports FreshBooks. May add as a third accounting provider in future.

5. **No tax code mapping**: Tax amounts pushed as flat values, not mapped to accounting system tax codes. Future: tax code mapping in client mapping interface.

6. **Manual client mapping**: Auto-match by name only. Future: match by email, tax ID, or allow bulk import.

7. **No payment receipt emails**: After online payment, no receipt email sent to client. Future: automated payment receipt with PDF.

### 12.2 Future Enhancements

| Enhancement | Priority | Description |
|-------------|----------|-------------|
| Two-way accounting sync | P1 | Detect changes in QBO/Xero and reflect in Solidtime |
| FreshBooks integration | P2 | Third accounting provider (requested by Everhour users) |
| Partial Stripe payments | P2 | Use Payment Intents API for partial payment support |
| Payment receipt emails | P2 | Automated PDF receipt to client after payment |
| Multi-currency payments | P3 | Accept payments in different currencies with conversion |
| Tax code mapping | P3 | Map Solidtime tax rates to QBO/Xero tax codes |
| Authorize.net / 2Checkout | P3 | Additional payment providers (Nutcache offers these) |
| Automated payment reminders | P2 | Email reminders for overdue invoices with pay link |
| Payment analytics dashboard | P3 | Revenue charts, AR aging, payment trends |
| Batch accounting sync | P3 | Sync all unsynchronized invoices/payments in one action |
| Webhook retry dashboard | P3 | UI for viewing and retrying failed webhook events |

---

## 13. Appendices

### 13.1 File Structure Summary

```
solidtime/
+-- app/
|   +-- Http/
|   |   +-- Controllers/Api/V1/
|   |   |   +-- PaymentController.php                       # NEW
|   |   |   +-- PaymentIntegrationController.php                   # NEW
|   |   |   +-- PaymentWebhookController.php                       # NEW
|   |   +-- Requests/V1/Payment/
|   |   |   +-- PaymentIndexRequest.php                     # NEW
|   |   |   +-- PaymentStoreRequest.php                     # NEW
|   |   |   +-- PaymentVoidRequest.php                      # NEW
|   |   +-- Requests/V1/PaymentIntegration/
|   |   |   +-- PaymentIntegrationConnectRequest.php               # NEW
|   |   |   +-- IntegrationClientMappingRequest.php         # NEW
|   |   |   +-- IntegrationSyncInvoiceRequest.php           # NEW
|   |   |   +-- IntegrationSyncLogRequest.php               # NEW
|   |   +-- Middleware/
|   |       +-- VerifyStripeWebhook.php                     # NEW
|   |       +-- VerifyPayPalWebhook.php                     # NEW
|   +-- Models/
|   |   +-- Payment.php                                     # NEW
|   |   +-- PaymentIntegration.php                          # NEW
|   |   +-- AccountingIntegration.php                       # NEW
|   |   +-- AccountingClientMapping.php                     # NEW
|   |   +-- AccountingSyncLog.php                           # NEW
|   |   +-- WebhookEvent.php                                # NEW
|   |   +-- Invoice.php                                     # MODIFIED (add payments relation)
|   +-- Service/
|   |   +-- PaymentService.php                              # NEW
|   |   +-- StripeService.php                               # NEW
|   |   +-- PayPalService.php                               # NEW
|   |   +-- QuickBooksService.php                           # NEW
|   |   +-- XeroService.php                                 # NEW
|   |   +-- AccountingSyncService.php                       # NEW
|   +-- Jobs/
|   |   +-- ProcessWebhookJob.php                           # NEW
|   |   +-- SyncInvoiceJob.php                              # NEW
|   |   +-- SyncPaymentJob.php                              # NEW
|   |   +-- RefreshOAuthTokenJob.php                        # NEW
|   |   +-- RetryFailedSyncJob.php                          # NEW
|   +-- Enums/
|       +-- PaymentMethod.php                               # NEW
|       +-- PaymentStatus.php                               # NEW
|       +-- PaymentType.php                                 # NEW
|       +-- PaymentProvider.php                         # NEW
|       +-- SyncStatus.php                                  # NEW
+-- database/
|   +-- migrations/
|   |   +-- 2026_XX_XX_000001_create_payment_integrations_table.php  # NEW
|   |   +-- 2026_XX_XX_000002_create_accounting_integrations_table.php # NEW
|   |   +-- 2026_XX_XX_000003_create_accounting_client_mappings_table.php # NEW
|   |   +-- 2026_XX_XX_000004_create_payments_table.php              # NEW
|   |   +-- 2026_XX_XX_000005_create_accounting_sync_logs_table.php  # NEW
|   |   +-- 2026_XX_XX_000006_create_webhook_events_table.php       # NEW
|   +-- factories/
|       +-- PaymentFactory.php                              # NEW
|       +-- PaymentIntegrationFactory.php                   # NEW
|       +-- AccountingIntegrationFactory.php                # NEW
+-- routes/
|   +-- api.php                                             # MODIFIED (add payment, integration, webhook routes)
|   +-- web.php                                             # MODIFIED (add OAuth callback routes, payment pages)
+-- resources/js/
|   +-- Pages/
|   |   +-- Payments.vue                                    # NEW
|   |   +-- IntegrationSettings.vue                         # NEW
|   |   +-- SyncLogs.vue                                    # NEW
|   |   +-- PaymentSuccess.vue                              # NEW (public page)
|   |   +-- PaymentFailure.vue                              # NEW (public page)
|   +-- packages/ui/src/Payment/
|   |   +-- PaymentsList.vue                                # NEW
|   |   +-- PaymentRow.vue                                  # NEW
|   |   +-- RecordPaymentDialog.vue                         # NEW
|   |   +-- PaymentStatusBadge.vue                          # NEW
|   |   +-- PaymentSummaryBar.vue                           # NEW
|   |   +-- __tests__/
|   |       +-- PaymentsList.test.ts                        # NEW
|   |       +-- RecordPaymentDialog.test.ts                 # NEW
|   +-- packages/ui/src/Integration/
|   |   +-- IntegrationCard.vue                             # NEW
|   |   +-- ClientMappingDialog.vue                         # NEW
|   |   +-- ClientMappingTable.vue                          # NEW
|   |   +-- SyncStatusBadge.vue                             # NEW
|   |   +-- SyncLogTable.vue                                # NEW
|   |   +-- __tests__/
|   |       +-- IntegrationCard.test.ts                     # NEW
|   |       +-- ClientMappingDialog.test.ts                 # NEW
|   +-- utils/
|   |   +-- usePayments.ts                                  # NEW
|   |   +-- useIntegrations.ts                              # NEW
|   +-- types/
|   |   +-- payment.d.ts                                    # NEW
|   |   +-- integration.d.ts                                # NEW
|   +-- Layouts/
|       +-- AppLayout.vue                                   # MODIFIED (add sidebar nav items)
+-- tests/
|   +-- Unit/Endpoint/Api/V1/
|   |   +-- PaymentEndpointTest.php                         # NEW
|   |   +-- IntegrationEndpointTest.php                     # NEW
|   |   +-- WebhookEndpointTest.php                         # NEW
|   +-- Unit/Service/
|       +-- PaymentServiceTest.php                          # NEW
|       +-- StripeServiceTest.php                           # NEW
|       +-- PayPalServiceTest.php                           # NEW
|       +-- QuickBooksServiceTest.php                       # NEW
|       +-- XeroServiceTest.php                             # NEW
|       +-- AccountingSyncServiceTest.php                   # NEW
+-- e2e/
|   +-- payments.spec.ts                                    # NEW
|   +-- integrations.spec.ts                                # NEW
+-- config/
    +-- services.php                                        # MODIFIED (add Stripe, PayPal, QBO, Xero credentials)
```

### 13.2 API Endpoint Summary

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/organizations/{org}/payments` | List payments with filters |
| POST | `/api/v1/organizations/{org}/payments` | Record manual payment |
| GET | `/api/v1/organizations/{org}/payments/{payment}` | Get payment detail |
| POST | `/api/v1/organizations/{org}/payments/{payment}/void` | Void a payment |
| GET | `/api/v1/organizations/{org}/integrations` | List connected integrations |
| POST | `/api/v1/organizations/{org}/integrations/{provider}/connect` | Start OAuth flow |
| GET | `/api/v1/organizations/{org}/integrations/{provider}/callback` | OAuth callback |
| DELETE | `/api/v1/organizations/{org}/integrations/{provider}` | Disconnect integration |
| GET | `/api/v1/organizations/{org}/integrations/{provider}/client-mappings` | List client mappings |
| PUT | `/api/v1/organizations/{org}/integrations/client-mappings/{mapping}` | Update client mapping |
| POST | `/api/v1/organizations/{org}/integrations/sync-invoice` | Trigger invoice sync |
| GET | `/api/v1/organizations/{org}/integrations/sync-logs` | List sync logs |
| POST | `/api/v1/webhooks/stripe` | Stripe webhook endpoint |
| POST | `/api/v1/webhooks/paypal` | PayPal webhook endpoint |
| GET | `/pay/{token}` | Public payment redirect |

### 13.3 Environment Variables Required

```env
# Stripe
STRIPE_CLIENT_ID=                     # Stripe Connect platform client ID
STRIPE_SECRET_KEY=                    # Stripe platform secret key
STRIPE_WEBHOOK_SECRET=                # Stripe webhook signing secret
STRIPE_CONNECT_RETURN_URL=            # OAuth callback URL

# PayPal
PAYPAL_CLIENT_ID=                     # PayPal REST API client ID
PAYPAL_CLIENT_SECRET=                 # PayPal REST API client secret
PAYPAL_WEBHOOK_ID=                    # PayPal webhook ID for verification
PAYPAL_MODE=sandbox                   # 'sandbox' or 'live'

# QuickBooks Online
QUICKBOOKS_CLIENT_ID=                 # Intuit OAuth app client ID
QUICKBOOKS_CLIENT_SECRET=             # Intuit OAuth app client secret
QUICKBOOKS_REDIRECT_URI=              # OAuth callback URL
QUICKBOOKS_ENVIRONMENT=sandbox        # 'sandbox' or 'production'

# Xero
XERO_CLIENT_ID=                       # Xero OAuth app client ID
XERO_CLIENT_SECRET=                   # Xero OAuth app client secret
XERO_REDIRECT_URI=                    # OAuth callback URL
```

### 13.4 Competitive Feature Matrix (Section 7.4 context)

| Platform | Stripe Payments | PayPal Payments | QuickBooks Sync | Xero Sync | FreshBooks Sync | Manual Payments |
|----------|:--------------:|:--------------:|:--------------:|:---------:|:--------------:|:--------------:|
| Harvest | Yes | Yes | Yes | Yes | No | Yes |
| Everhour | No | No | Yes | Yes | Yes | Yes |
| Nutcache | Yes | Yes | No | No | No | Yes |
| Clockify | No | No | No | No | No | Yes |
| TimeCamp | No | No | Yes | Yes | No | No |
| **Solidtime (this PRD)** | **Yes** | **Yes** | **Yes** | **Yes** | **No (v2)** | **Yes** |

### 13.5 Glossary

- **Stripe Connect**: Stripe's platform that allows marketplace/platform businesses to accept payments on behalf of connected accounts
- **Stripe Checkout**: Stripe-hosted payment page that handles card entry, validation, and 3D Secure
- **PayPal Order**: PayPal's API object representing a payment request that a payer authorizes and captures
- **QuickBooks Online (QBO)**: Intuit's cloud-based accounting software
- **Xero**: Cloud-based accounting software popular in UK, Australia, and New Zealand
- **OAuth 2.0**: Authorization framework used by all four external services for API access
- **Webhook**: HTTP callback from an external service to notify Solidtime of events (payments, refunds, etc.)
- **Idempotency**: Property ensuring that processing the same webhook event multiple times produces the same result
- **SAQ-A**: PCI DSS Self-Assessment Questionnaire for merchants that fully outsource card processing
- **Dead Letter Queue**: Storage for webhook events that could not be processed after maximum retry attempts

### 13.6 Change Log

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-02-09 | Tech Planning Agent | Initial draft |
