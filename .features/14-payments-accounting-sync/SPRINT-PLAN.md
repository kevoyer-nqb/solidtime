# Sprint Plan: Online Payments and Accounting Sync

**Date**: 2026-02-09
**Feature**: 14 - Online Payments and Accounting Sync
**Branch**: `feature/payments-accounting-sync` (from `main`)
**Task Prefix**: `PAY-`
**PRD Reference**: `.features/14-payments-accounting-sync/PRD.md`
**Architecture Reference**: `.features/14-payments-accounting-sync/ARCHITECTURE.md`

---

## 1. Executive Summary

The Online Payments and Accounting Sync feature enables organizations to accept online invoice payments via Stripe and PayPal, record manual payments, and synchronize invoice/payment data with QuickBooks Online and Xero. The feature builds on top of Feature 04 (Invoicing), which is a hard prerequisite.

**Total effort estimate**: 320 hours

**Total story points**: ~213 SP

**Number of sprints**: **5 sprints** (10 weeks)

**Team size assumptions**:
- 1 Backend Developer (senior, ~30 productive hours/sprint)
- 1 Frontend Developer (senior, ~30 productive hours/sprint)
- 1 QA Engineer (part-time, ~15 productive hours/sprint)
- Concurrent work where dependency graph allows

**Key constraints**:
- **Hard dependency on Feature 04 (Invoicing)**: The `Invoice` model, controller, and service must exist on `main` before development can begin. Feature 04 is estimated at 14 weeks. This feature's Sprint 1 cannot start until Feature 04 is merged.
- Backend must be substantially complete before frontend can consume APIs (PAY-021 is the bridge between backend and frontend)
- 4 external Composer packages must be added (`stripe/stripe-php`, `paypal/paypal-server-sdk`, `quickbooks/v3-php-sdk`, `xeroapi/xero-php-oauth2`)
- Webhook endpoints require testing against provider sandbox environments
- OAuth flows require real provider developer accounts for testing (Stripe test mode, PayPal sandbox, QuickBooks sandbox, Xero demo company)

---

## 2. Sprint Overview Table

| Sprint | Name | Duration | Story Points | Key Deliverables |
|--------|------|----------|:------------:|------------------|
| **1** | Database, Models, Core Services | 2 weeks | 42 SP | Migrations, 6 models, permissions, token encryption, PaymentService, StripeService, PayPalService, PaymentService unit tests |
| **2** | Controllers, Routes, Webhooks | 2 weeks | 42 SP | PaymentController, IntegrationController, WebhookController, request validation, webhook signature verification, ProcessWebhookJob, public payment link, all routes, Stripe/PayPal unit tests |
| **3** | Accounting Services, OpenAPI, Frontend Stores | 2 weeks | 44 SP | QuickBooksService, XeroService, AccountingSyncService, sync jobs, token refresh job, OpenAPI spec, TS client, Pinia stores |
| **4** | Frontend Implementation | 2 weeks | 40 SP | IntegrationSettings page, ClientMapping dialog, PaymentsList page, InvoiceDetail extension, RecordPayment dialog, badges, SyncLogs page, navigation, public pages, retry logic |
| **5** | Testing, Documentation, Polish | 2 weeks | 46 SP | All endpoint tests, all service unit tests, webhook tests, frontend component tests, E2E Playwright tests, JSDoc, env var docs |

**Total**: ~214 SP across 10 weeks

---

## 3. Dependency Map

### 3.1 Task Dependencies

```
Wave 1 (No dependencies -- Sprint 1 start):
    PAY-001 (Migrations, 8h)
    PAY-020 (Permissions, 2h)

Wave 2 (after Wave 1):
    PAY-002 (Models, 8h)              <- PAY-001

Wave 3 (after Wave 2):
    PAY-003 (PaymentService, 12h)     <- PAY-002
    PAY-006 (IntegrationController, 8h) <- PAY-002
    PAY-007 (StripeService, 12h)      <- PAY-002
    PAY-008 (PayPalService, 10h)      <- PAY-002
    PAY-012 (QuickBooksService, 16h)  <- PAY-002
    PAY-013 (XeroService, 14h)        <- PAY-002
    PAY-048 (Token encryption, 4h)    <- PAY-002

Wave 4 (after Wave 3):
    PAY-004 (PaymentController, 8h)   <- PAY-003
    PAY-009 (WebhookController, 8h)   <- PAY-007, PAY-008
    PAY-014 (AccountingSyncService, 8h) <- PAY-012, PAY-013
    PAY-016 (RefreshOAuthTokenJob, 4h) <- PAY-012, PAY-013
    PAY-018 (Integration validation, 4h) <- PAY-006
    PAY-019 (Public payment link, 4h) <- PAY-007, PAY-008
    PAY-035 (PaymentService tests, 6h) <- PAY-003
    PAY-036 (StripeService tests, 6h) <- PAY-007
    PAY-037 (PayPalService tests, 6h) <- PAY-008
    PAY-038 (QuickBooksService tests, 8h) <- PAY-012
    PAY-039 (XeroService tests, 8h)   <- PAY-013

Wave 5 (after Wave 4):
    PAY-005 (Payment validation, 4h)  <- PAY-004
    PAY-010 (Webhook signature, 4h)   <- PAY-009
    PAY-011 (ProcessWebhookJob, 6h)   <- PAY-009, PAY-003
    PAY-015 (Sync jobs, 6h)           <- PAY-014
    PAY-017 (All routes, 2h)          <- PAY-004, PAY-006, PAY-009
    PAY-040 (AccountingSyncService tests, 6h) <- PAY-014
    PAY-046 (Env var docs, 2h)        <- PAY-007, PAY-008, PAY-012, PAY-013

Wave 6 (after Wave 5):
    PAY-021 (OpenAPI + TS client, 6h) <- PAY-017
    PAY-033 (Payment endpoint tests, 8h) <- PAY-004, PAY-005, PAY-017
    PAY-034 (Integration endpoint tests, 8h) <- PAY-006, PAY-018, PAY-017
    PAY-041 (Webhook tests, 8h)       <- PAY-009, PAY-010, PAY-011
    PAY-047 (Retry logic, 4h)         <- PAY-015

Wave 7 (after Wave 6):
    PAY-022 (usePaymentsStore, 8h)    <- PAY-021
    PAY-023 (useIntegrationsStore, 6h) <- PAY-021

Wave 8 (after Wave 7):
    PAY-024 (IntegrationSettings, 12h) <- PAY-023
    PAY-026 (PaymentsList, 8h)        <- PAY-022
    PAY-027 (InvoiceDetail extend, 10h) <- PAY-022
    PAY-029 (Status badges, 2h)       <- PAY-022
    PAY-030 (SyncLogs page, 6h)       <- PAY-023
    PAY-045 (JSDoc, 2h)               <- PAY-022, PAY-023

Wave 9 (after Wave 8):
    PAY-025 (ClientMappingDialog, 8h) <- PAY-024
    PAY-028 (RecordPaymentDialog, 4h) <- PAY-027
    PAY-031 (Web routes + nav, 2h)    <- PAY-024, PAY-026
    PAY-032 (Public pages, 4h)        <- PAY-019
    PAY-042 (Integration UI tests, 6h) <- PAY-024
    PAY-043 (Payment UI tests, 6h)    <- PAY-026, PAY-028

Wave 10 (after Wave 9):
    PAY-044 (E2E tests, 10h)          <- PAY-031, PAY-027
```

### 3.2 Critical Path

```
PAY-001 (8h) -> PAY-002 (8h) -> PAY-003 (12h) -> PAY-004 (8h) -> PAY-005 (4h) -> PAY-017 (2h) -> PAY-021 (6h) -> PAY-022 (8h) -> PAY-027 (10h) -> PAY-028 (4h)
                                                                                                                                                        |
                                                                                                                                        PAY-031 (2h) -> PAY-044 (10h)
```

**Critical path duration**: ~82 hours of sequential work (8 + 8 + 12 + 8 + 4 + 2 + 6 + 8 + 10 + 4 + 2 + 10)

### 3.3 Parallelism Opportunities

After PAY-002 (models) is complete, 6 streams can run in parallel:

| Stream | Tasks | Focus |
|--------|-------|-------|
| **A (Payments)** | PAY-003 -> PAY-004 -> PAY-005 | Core payment logic and controller |
| **B (Stripe)** | PAY-007 -> PAY-009 -> PAY-010 -> PAY-011 | Stripe integration + webhooks |
| **C (PayPal)** | PAY-008 -> (merges into PAY-009 with B) | PayPal integration |
| **D (QuickBooks)** | PAY-012 -> PAY-014 -> PAY-015 | QuickBooks sync |
| **E (Xero)** | PAY-013 -> (merges into PAY-014 with D) | Xero sync |
| **F (Integration UI)** | PAY-006 -> PAY-018 | Integration controller and validation |

Streams A, B, C, D, E, and F can all run concurrently after Wave 2. A single backend developer would serialize these; with 2 backend developers, significant parallelism is possible.

| Wave | Backend Dev 1 | Backend Dev 2 | Frontend Dev | Can run in parallel? |
|------|---------------|---------------|--------------|:--------------------:|
| 1 | PAY-001, PAY-020 | -- | -- | -- |
| 2 | PAY-002, PAY-048 | -- | -- | -- |
| 3 | PAY-003, PAY-007, PAY-008 | PAY-006, PAY-012, PAY-013 | -- | Yes (2 backend devs) |
| 4 | PAY-004, PAY-009, PAY-019 | PAY-014, PAY-016, PAY-018 | -- | Yes |
| 5 | PAY-005, PAY-010, PAY-011, PAY-017 | PAY-015, PAY-047 | -- | Yes |
| 6 | PAY-021 | PAY-033, PAY-034, PAY-041 | -- | Yes (tests parallel to OpenAPI) |
| 7 | -- | Backend tests (PAY-035..040) | PAY-022, PAY-023 | Yes (frontend starts) |
| 8 | -- | -- | PAY-024, PAY-026, PAY-027, PAY-029, PAY-030 | Yes (all frontend) |
| 9 | -- | -- | PAY-025, PAY-028, PAY-031, PAY-032, PAY-042, PAY-043 | Yes (all frontend) |
| 10 | -- | -- | PAY-044, PAY-045 | Yes (E2E + docs) |

---

## 4. Sprint Details

### Sprint 1 (Weeks 1-2): Database, Models, Core Services

**Goal**: Database schema in place, all 6 models created with encryption, permissions registered, core PaymentService functional, Stripe and PayPal services functional with sandbox testing.

| Task ID | Description | Assignee | SP | Dependencies | Day |
|---------|-------------|----------|:--:|--------------|:---:|
| PAY-001 | Create database migrations for all 6 new tables | Backend | 5 | None | 1-2 |
| PAY-020 | Add new permissions to role definitions | Backend | 1 | None | 1 |
| PAY-002 | Create Eloquent models with relations, casts, and traits | Backend | 5 | PAY-001 | 2-3 |
| PAY-048 | Add encryption for OAuth tokens in database | Backend | 3 | PAY-002 | 3 |
| PAY-003 | Create PaymentService with core payment business logic | Backend | 8 | PAY-002 | 3-5 |
| PAY-007 | Create StripeService for Connect OAuth and Checkout Sessions | Backend | 8 | PAY-002 | 5-7 |
| PAY-008 | Create PayPalService for OAuth and Order creation | Backend | 7 | PAY-002 | 7-9 |
| PAY-035 | Backend unit tests for PaymentService | Backend QA | 5 | PAY-003 | 6-7 |

**Sprint 1 Total**: 42 SP

**Deliverables**:
- [ ] 6 database migrations creating `payment_integrations`, `accounting_integrations`, `accounting_client_mappings`, `payments`, `accounting_sync_logs`, `webhook_events`
- [ ] 6 Eloquent models: `Payment`, `PaymentIntegration`, `AccountingIntegration`, `AccountingClientMapping`, `AccountingSyncLog`, `WebhookEvent`
- [ ] 5 enum classes: `PaymentMethod`, `PaymentStatus`, `PaymentType`, `IntegrationProvider`, `SyncStatus`
- [ ] 3 model factories: `PaymentFactory`, `PaymentIntegrationFactory`, `AccountingIntegrationFactory`
- [ ] `PaymentPermissions.php` registered with modular permission pattern
- [ ] `PaymentService` with `recordPayment()`, `recordWebhookPayment()`, `recordRefund()`, `voidPayment()`, `updateInvoicePaymentStatus()`
- [ ] `StripeService` with `getConnectUrl()`, `handleOAuthCallback()`, `createCheckoutSession()`, `verifyWebhookSignature()`, `handleWebhookEvent()`
- [ ] `PayPalService` with `getAuthorizationUrl()`, `handleOAuthCallback()`, `createOrder()`, `verifyWebhookSignature()`, `handleWebhookEvent()`
- [ ] Token encryption via `encrypted` model cast verified working
- [ ] PaymentService unit tests passing

**QA Gate**:
```bash
./vendor/bin/sail exec laravel.test composer fix
./vendor/bin/sail exec laravel.test composer analyse
./vendor/bin/sail exec laravel.test php artisan migrate
./vendor/bin/sail exec laravel.test php artisan test --filter=PaymentServiceTest
```

---

### Sprint 2 (Weeks 3-4): Controllers, Routes, Webhooks, Payment Provider Tests

**Goal**: All API endpoints functional, webhook processing pipeline working end-to-end, public payment link operational, all routes registered.

| Task ID | Description | Assignee | SP | Dependencies | Day |
|---------|-------------|----------|:--:|--------------|:---:|
| PAY-004 | Create PaymentController with CRUD endpoints | Backend | 5 | PAY-003 | 1-2 |
| PAY-005 | Create request validation classes for payment endpoints | Backend | 3 | PAY-004 | 2 |
| PAY-006 | Create IntegrationController with OAuth flow endpoints | Backend | 5 | PAY-002 | 1-2 |
| PAY-018 | Create request validation classes for integration endpoints | Backend | 3 | PAY-006 | 3 |
| PAY-009 | Create WebhookController with Stripe and PayPal handlers | Backend | 5 | PAY-007, PAY-008 | 3-4 |
| PAY-010 | Implement webhook signature verification middleware | Backend | 3 | PAY-009 | 4-5 |
| PAY-011 | Create ProcessWebhookJob for async webhook processing | Backend | 4 | PAY-009, PAY-003 | 5-6 |
| PAY-019 | Create public payment link route and redirect logic | Backend | 3 | PAY-007, PAY-008 | 5 |
| PAY-017 | Register all API routes for payments, integrations, webhooks | Backend | 1 | PAY-004, PAY-006, PAY-009 | 6 |
| PAY-036 | Backend unit tests for StripeService | Backend QA | 5 | PAY-007 | 7-8 |
| PAY-037 | Backend unit tests for PayPalService | Backend QA | 5 | PAY-008 | 8-9 |

**Sprint 2 Total**: 42 SP

**Deliverables**:
- [ ] `PaymentController` with `index()`, `store()`, `show()`, `void()` methods
- [ ] `IntegrationController` with `index()`, `connect()`, `callback()`, `disconnect()`, `clientMappings()`, `updateClientMapping()`, `syncInvoice()`, `syncLogs()` methods
- [ ] `WebhookController` with `stripe()` and `paypal()` handlers
- [ ] 7 request validation classes
- [ ] 2 webhook signature verification middleware classes
- [ ] `ProcessWebhookJob` with retry logic and dead letter handling
- [ ] Public payment link route (`/pay/{token}`) with signed URL verification
- [ ] All routes registered in `routes/api.php` and `routes/web.php`
- [ ] StripeService unit tests passing (with Stripe test mode mocks)
- [ ] PayPalService unit tests passing (with PayPal sandbox mocks)

**QA Gate**:
```bash
./vendor/bin/sail exec laravel.test composer fix
./vendor/bin/sail exec laravel.test composer analyse
./vendor/bin/sail exec laravel.test php artisan test --filter=PaymentServiceTest
./vendor/bin/sail exec laravel.test php artisan test --filter=StripeServiceTest
./vendor/bin/sail exec laravel.test php artisan test --filter=PayPalServiceTest
```

**Manual Testing**:
- Test Stripe Connect OAuth flow with Stripe test mode account
- Test PayPal OAuth flow with PayPal sandbox account
- Test webhook endpoint with Stripe CLI (`stripe listen --forward-to`)
- Test payment link generation and redirect

---

### Sprint 3 (Weeks 5-6): Accounting Services, OpenAPI, Frontend Stores

**Goal**: QuickBooks and Xero integration services complete, accounting sync pipeline functional, OpenAPI spec updated and TypeScript client regenerated, frontend stores ready for UI implementation.

| Task ID | Description | Assignee | SP | Dependencies | Day |
|---------|-------------|----------|:--:|--------------|:---:|
| PAY-012 | Create QuickBooksService for OAuth, invoice/payment push, customers | Backend | 10 | PAY-002 | 1-4 |
| PAY-013 | Create XeroService for OAuth, invoice/payment push, contacts | Backend | 9 | PAY-002 | 1-4 |
| PAY-014 | Create AccountingSyncService for orchestrating sync operations | Backend | 5 | PAY-012, PAY-013 | 5-6 |
| PAY-015 | Create SyncInvoiceJob and SyncPaymentJob queued jobs | Backend | 4 | PAY-014 | 6-7 |
| PAY-016 | Create RefreshOAuthTokenJob for proactive token refresh | Backend | 3 | PAY-012, PAY-013 | 5 |
| PAY-021 | Update OpenAPI spec and regenerate TypeScript client | Backend | 4 | PAY-017 | 7-8 |
| PAY-022 | Create usePaymentsStore Pinia store with TypeScript types | Frontend | 5 | PAY-021 | 8-9 |
| PAY-023 | Create useIntegrationsStore Pinia store with TypeScript types | Frontend | 4 | PAY-021 | 9-10 |

**Sprint 3 Total**: 44 SP

**Deliverables**:
- [ ] `QuickBooksService` with OAuth, `pushInvoice()`, `pushPayment()`, `fetchCustomers()`, `createCustomer()`, `refreshToken()`
- [ ] `XeroService` with OAuth, `pushInvoice()`, `pushPayment()`, `fetchContacts()`, `createContact()`, `refreshToken()`
- [ ] `AccountingSyncService` with `syncInvoice()`, `syncPayment()`, `autoMatchClients()`, `fetchExternalCustomers()`
- [ ] `SyncInvoiceJob` and `SyncPaymentJob` with retry logic
- [ ] `RefreshOAuthTokenJob` (scheduled hourly)
- [ ] OpenAPI spec with all 15 endpoint definitions
- [ ] TypeScript API client regenerated with typed methods
- [ ] `usePaymentsStore` with `fetchPayments()`, `recordPayment()`, `voidPayment()`, `fetchPaymentSummary()`
- [ ] `useIntegrationsStore` with `fetchIntegrations()`, `connectProvider()`, `disconnectProvider()`, `fetchClientMappings()`, `updateClientMapping()`, `syncInvoice()`, `fetchSyncLogs()`
- [ ] TypeScript type definitions for `payment.d.ts` and `integration.d.ts`

**QA Gate**:
```bash
./vendor/bin/sail exec laravel.test composer fix
./vendor/bin/sail exec laravel.test composer analyse
npm run lint:fix && npm run format
npm run build  # Verify no TypeScript errors
```

**Manual Testing**:
- Test QuickBooks OAuth flow with sandbox account
- Test Xero OAuth flow with demo company
- Test invoice push to QuickBooks sandbox
- Test invoice push to Xero demo company
- Test token refresh flow

---

### Sprint 4 (Weeks 7-8): Frontend Implementation

**Goal**: All UI pages and components functional, navigation in place, end-to-end user workflows testable in the browser.

| Task ID | Description | Assignee | SP | Dependencies | Day |
|---------|-------------|----------|:--:|--------------|:---:|
| PAY-024 | Create IntegrationSettings.vue page with provider cards | Frontend | 8 | PAY-023 | 1-3 |
| PAY-025 | Create ClientMappingDialog.vue for accounting client mapping | Frontend | 5 | PAY-024 | 3-4 |
| PAY-026 | Create PaymentsList.vue page with filtering and sorting | Frontend | 5 | PAY-022 | 1-2 |
| PAY-027 | Extend InvoiceDetail.vue with payment history and Record Payment | Frontend | 7 | PAY-022 | 2-4 |
| PAY-028 | Create RecordPaymentDialog.vue modal component | Frontend | 3 | PAY-027 | 4-5 |
| PAY-029 | Create PaymentStatusBadge.vue and SyncStatusBadge.vue components | Frontend | 1 | PAY-022 | 1 |
| PAY-030 | Create SyncLogsPage.vue for viewing sync history | Frontend | 4 | PAY-023 | 5-6 |
| PAY-031 | Add web routes and sidebar navigation for payments and integrations | Frontend | 1 | PAY-024, PAY-026 | 6 |
| PAY-032 | Create payment success/failure public pages | Frontend | 3 | PAY-019 | 6-7 |
| PAY-047 | Implement retry logic for failed accounting syncs (scheduled command) | Backend | 3 | PAY-015 | 1 |

**Sprint 4 Total**: 40 SP

**Deliverables**:
- [ ] `IntegrationSettings.vue` page with Stripe, PayPal, QuickBooks, Xero integration cards
- [ ] `IntegrationCard.vue` component with connect/disconnect buttons and status display
- [ ] `ClientMappingDialog.vue` with mapping table, auto-match, manual mapping
- [ ] `ClientMappingTable.vue` with per-client row and external customer dropdown
- [ ] `Payments.vue` page with `PaymentsList.vue`, filters, sorting, pagination
- [ ] `PaymentRow.vue` component with status and sync badges
- [ ] `PaymentSummaryBar.vue` with total received, total pending, total this month
- [ ] InvoiceDetail payment history section with payment list and summary
- [ ] `RecordPaymentDialog.vue` with amount (pre-filled), date, method, reference, notes
- [ ] `PaymentStatusBadge.vue` and `SyncStatusBadge.vue` components
- [ ] `SyncLogs.vue` page with `SyncLogTable.vue`, filters, retry button
- [ ] Web routes registered and sidebar Payments nav item added
- [ ] `PaymentSuccess.vue` and `PaymentFailure.vue` public pages
- [ ] `RetryFailedSyncJob` running every 15 minutes

**QA Gate**:
```bash
npm run lint:fix && npm run format
npm run build  # Verify no build errors
```

**Manual Testing**:
- Navigate to `/payments` via sidebar
- Navigate to Integration Settings via Organization Settings
- Connect Stripe (test mode) via OAuth flow
- Connect QuickBooks (sandbox) via OAuth flow
- View client mapping interface
- Record manual payment on invoice
- Void a payment
- Trigger manual invoice sync
- View sync logs
- Verify "Pay Now" link on invoice opens Stripe Checkout

---

### Sprint 5 (Weeks 9-10): Testing, Documentation, Polish

**Goal**: Comprehensive test coverage, all QA gates passing, feature ready for review and merge.

| Task ID | Description | Assignee | SP | Dependencies | Day |
|---------|-------------|----------|:--:|--------------|:---:|
| PAY-033 | Backend endpoint tests for PaymentController | Backend QA | 5 | PAY-004, PAY-005, PAY-017 | 1-2 |
| PAY-034 | Backend endpoint tests for IntegrationController | Backend QA | 5 | PAY-006, PAY-018, PAY-017 | 2-4 |
| PAY-038 | Backend unit tests for QuickBooksService | Backend QA | 5 | PAY-012 | 1-2 |
| PAY-039 | Backend unit tests for XeroService | Backend QA | 5 | PAY-013 | 2-3 |
| PAY-040 | Backend unit tests for AccountingSyncService | Backend QA | 4 | PAY-014 | 3-4 |
| PAY-041 | Backend tests for webhook handling and signature verification | Backend QA | 5 | PAY-009, PAY-010, PAY-011 | 4-5 |
| PAY-042 | Frontend component tests for IntegrationSettings | Frontend QA | 4 | PAY-024 | 1-2 |
| PAY-043 | Frontend component tests for PaymentsList and RecordPayment | Frontend QA | 4 | PAY-026, PAY-028 | 2-3 |
| PAY-044 | E2E Playwright tests for payment and integration flows | QA | 7 | PAY-031, PAY-027 | 5-8 |
| PAY-045 | Add JSDoc comments to Pinia stores | Frontend | 1 | PAY-022, PAY-023 | 1 |
| PAY-046 | Create environment variable documentation for API keys and secrets | Backend | 1 | PAY-007, PAY-008, PAY-012, PAY-013 | 1 |

**Sprint 5 Total**: 46 SP

**Deliverables**:
- [ ] `PaymentEndpointTest.php` covering all 4 payment endpoints
- [ ] `IntegrationEndpointTest.php` covering all 8 integration endpoints
- [ ] `WebhookEndpointTest.php` covering Stripe and PayPal webhook handling
- [ ] `QuickBooksServiceTest.php` covering OAuth, invoice push, payment push, customer fetch
- [ ] `XeroServiceTest.php` covering OAuth, invoice push, payment push, contact fetch
- [ ] `AccountingSyncServiceTest.php` covering sync orchestration and client matching
- [ ] `IntegrationCard.test.ts` and `ClientMappingDialog.test.ts` component tests
- [ ] `PaymentsList.test.ts` and `RecordPaymentDialog.test.ts` component tests
- [ ] `payments.spec.ts` E2E test covering manual payment recording, payment list, void
- [ ] `integrations.spec.ts` E2E test covering integration connection (mock OAuth), client mapping
- [ ] JSDoc comments on all store methods
- [ ] Environment variable documentation for all 4 providers

**QA Gate**:
```bash
npm run lint:fix && npm run format
npx vitest run
npx playwright test
./vendor/bin/sail exec laravel.test composer fix
./vendor/bin/sail exec laravel.test composer analyse
./vendor/bin/sail exec laravel.test php artisan test
```

---

## 5. Risk Register

| Risk | Sprint | Probability | Impact | Mitigation |
|------|--------|-------------|--------|------------|
| Feature 04 (Invoicing) not ready | Pre-sprint | Medium | Critical | Cannot start Feature 14 until Feature 04 is merged. Plan sprints accordingly. Monitor Feature 04 progress. |
| Stripe Connect OAuth complexity | 1 | Medium | Medium | Use Stripe test mode account. Follow Stripe Connect Standard documentation exactly. Test with Stripe CLI for webhooks. |
| PayPal SDK compatibility issues | 1 | Low | Medium | Verify `paypal/paypal-server-sdk` supports PHP 8.2 and Laravel 11. Fall back to HTTP client if SDK is problematic. |
| QuickBooks SDK version conflicts | 3 | Medium | Medium | Test `quickbooks/v3-php-sdk` v6 compatibility with Laravel 11. The SDK has known dependency on `guzzlehttp/guzzle` -- verify version alignment. |
| Xero rate limiting (60/min) | 3 | Medium | Low | Implement throttling in XeroService. Queue-based sync naturally spreads requests. Test with bulk sync scenarios. |
| Webhook delivery failures | 2 | Low | High | Idempotent processing via WebhookEvent. Reconciliation job can poll for missing payments. Stripe CLI for local testing. |
| OAuth token expiration in production | 3-5 | Medium | High | Proactive refresh via hourly RefreshOAuthTokenJob. Alert on any refresh failure. Admin notification when token cannot be refreshed. |
| PCI compliance concerns from stakeholders | 1 | Low | Medium | Document SAQ-A eligibility clearly. No card data touches Solidtime servers. All payment forms hosted by providers. |
| E2E test flakiness with OAuth flows | 5 | High | Medium | Mock OAuth callbacks in E2E tests. Do not test real OAuth redirects in CI. Test OAuth flows manually in staging. |
| Invoice model schema mismatch | 1 | Medium | Medium | Review Feature 04's Invoice model schema at Sprint 1 start. Adjust payment model relations if needed. |

---

## 6. Definition of Done (Feature Complete)

- [ ] All 48 tasks (PAY-001 through PAY-048) completed
- [ ] All 6 database migrations run successfully (forward and rollback)
- [ ] All 6 models have proper relations, casts, encrypted fields, and hidden attributes
- [ ] All 15 API endpoints functional with proper validation and permissions
- [ ] Stripe Connect OAuth flow working (test mode)
- [ ] PayPal OAuth flow working (sandbox)
- [ ] QuickBooks Online OAuth flow working (sandbox)
- [ ] Xero OAuth flow working (demo company)
- [ ] Webhook endpoints receiving and processing events (Stripe and PayPal)
- [ ] Webhook signature verification rejecting invalid signatures
- [ ] AccountingSyncService pushing invoices and payments to QuickBooks and Xero
- [ ] Client mapping interface functional (auto-match + manual)
- [ ] Payment recording (manual and webhook) correctly updating invoice status
- [ ] Payment voiding correctly recalculating invoice status
- [ ] Token refresh job proactively refreshing expiring tokens
- [ ] Retry job re-queuing failed syncs
- [ ] `composer fix && composer analyse` passes with 0 new errors
- [ ] `npm run lint:fix && npm run format` passes
- [ ] `npm run build` succeeds
- [ ] All backend endpoint tests passing
- [ ] All backend service unit tests passing
- [ ] Webhook handling tests passing
- [ ] Frontend component tests passing (Vitest)
- [ ] E2E Playwright tests passing
- [ ] OpenAPI spec updated with all 15 endpoints
- [ ] TypeScript client regenerated
- [ ] Sidebar navigation item functional
- [ ] Loading and error states handled on all pages
- [ ] OAuth tokens encrypted at rest (verified)
- [ ] Environment variable documentation created
- [ ] Feature gated behind `canAccessPremiumFeatures()`

---

## 7. Post-Sprint: Merge Strategy

After all 5 sprints complete and QA passes:

1. Rebase `feature/payments-accounting-sync` on latest `main` (which must include Feature 04)
2. Run full test suite (PHP + JS + E2E)
3. Run `composer fix && composer analyse`
4. Run `npm run lint:fix && npm run format`
5. Run `npm run build`
6. Verify all 4 OAuth flows work with provider sandboxes
7. Test webhook processing with Stripe CLI
8. Create PR targeting `main`
9. After merge: downstream features (payment reminders, financial dashboards) can begin from `main`
