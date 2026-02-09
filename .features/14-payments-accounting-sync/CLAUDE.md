# Feature 14: Online Payments and Accounting Sync

## Branch
`feature/payments-accounting-sync`

## Task Prefix
`PAY-` (PAY-001 through PAY-048)

## Migration Date Prefix
`2026_03_14_`

## Execution Phase
Phase 3 -- after Feature 04 (Invoicing) is complete and merged to `main`

## Sprint Summary
| Sprint | Focus | Story Points |
|--------|-------|-------------|
| Sprint 1 | Database: Migrations, Models, Permissions, Token Encryption, PaymentService, StripeService, PayPalService | ~42 SP |
| Sprint 2 | Backend: Controllers, Validation, Routes, Webhooks, ProcessWebhookJob, Public Payment Link, Stripe/PayPal Tests | ~42 SP |
| Sprint 3 | Backend/Frontend Bridge: QuickBooksService, XeroService, AccountingSyncService, Sync Jobs, OpenAPI, Pinia Stores | ~44 SP |
| Sprint 4 | Frontend: IntegrationSettings, ClientMapping, PaymentsList, InvoiceDetail Extension, SyncLogs, Navigation, Public Pages | ~40 SP |
| Sprint 5 | Testing: All Endpoint Tests, Service Unit Tests, Webhook Tests, Component Tests, E2E Tests, JSDoc, Env Docs | ~46 SP |

**Total**: ~214 SP / ~320h across 5 sprints (10 weeks)

## Hard Dependencies
- **Feature 04 (Invoicing)**: `Invoice` model, `InvoiceController`, `InvoiceService` must be on `main` before this feature branches
- **FOUND-007**: Modular permissions infrastructure (for `PaymentPermissions.php`)

## Composer Packages Required
- `stripe/stripe-php` ^14.0
- `paypal/paypal-server-sdk` ^1.0
- `quickbooks/v3-php-sdk` ^6.0
- `xeroapi/xero-php-oauth2` ^5.0

## Key Architecture Decisions
- 6 new database tables: `payment_integrations`, `accounting_integrations`, `accounting_client_mappings`, `payments`, `accounting_sync_logs`, `webhook_events`
- 6 new Eloquent models with UUID primary keys and `CustomAuditable` trait
- OAuth tokens encrypted at rest using Laravel's `encrypted` model cast (AES-256-CBC via `APP_KEY`)
- Tokens hidden from JSON serialization via `$hidden` array
- PCI SAQ-A compliance: no card data touches Solidtime servers; Stripe Checkout and PayPal hosted pages only
- Webhook endpoints outside `auth:api` middleware; signature verification per provider
- Async webhook processing via `ProcessWebhookJob` (respond 200 immediately, process in queue)
- One-way accounting sync in v1 (Solidtime pushes to QuickBooks/Xero; no inbound sync)
- Polymorphic sync logging: `AccountingSyncLog` tracks both Invoice and Payment syncs
- 5 queued jobs on dedicated queues (`webhooks`, `accounting-sync`, `default`)
- 2 scheduled tasks: hourly token refresh, 15-minute sync retry
- 5 new permissions: `payments:view:own`, `payments:view:all`, `payments:create:own`, `payments:create:all`, `integrations:manage`
- Morph map configured in `AppServiceProvider` for `invoice` and `payment`

## New Files to Create

### Backend: Models and Enums (11)
- `app/Models/Payment.php`
- `app/Models/PaymentIntegration.php`
- `app/Models/AccountingIntegration.php`
- `app/Models/AccountingClientMapping.php`
- `app/Models/AccountingSyncLog.php`
- `app/Models/WebhookEvent.php`
- `app/Enums/PaymentMethod.php`
- `app/Enums/PaymentStatus.php`
- `app/Enums/PaymentType.php`
- `app/Enums/IntegrationProvider.php`
- `app/Enums/SyncStatus.php`

### Backend: Controllers (3)
- `app/Http/Controllers/Api/V1/PaymentController.php`
- `app/Http/Controllers/Api/V1/IntegrationController.php`
- `app/Http/Controllers/Api/V1/WebhookController.php`

### Backend: Services (6)
- `app/Service/PaymentService.php`
- `app/Service/StripeService.php`
- `app/Service/PayPalService.php`
- `app/Service/QuickBooksService.php`
- `app/Service/XeroService.php`
- `app/Service/AccountingSyncService.php`

### Backend: Request Validation (7)
- `app/Http/Requests/V1/Payment/PaymentIndexRequest.php`
- `app/Http/Requests/V1/Payment/PaymentStoreRequest.php`
- `app/Http/Requests/V1/Payment/PaymentVoidRequest.php`
- `app/Http/Requests/V1/Integration/IntegrationConnectRequest.php`
- `app/Http/Requests/V1/Integration/IntegrationClientMappingRequest.php`
- `app/Http/Requests/V1/Integration/IntegrationSyncInvoiceRequest.php`
- `app/Http/Requests/V1/Integration/IntegrationSyncLogRequest.php`

### Backend: Middleware (2)
- `app/Http/Middleware/VerifyStripeWebhook.php`
- `app/Http/Middleware/VerifyPayPalWebhook.php`

### Backend: Jobs (5)
- `app/Jobs/ProcessWebhookJob.php`
- `app/Jobs/SyncInvoiceJob.php`
- `app/Jobs/SyncPaymentJob.php`
- `app/Jobs/RefreshOAuthTokenJob.php`
- `app/Jobs/RetryFailedSyncJob.php`

### Backend: Permissions (1)
- `app/Permissions/PaymentPermissions.php`

### Backend: Migrations (6)
- `database/migrations/2026_03_14_000001_create_payment_integrations_table.php`
- `database/migrations/2026_03_14_000002_create_accounting_integrations_table.php`
- `database/migrations/2026_03_14_000003_create_accounting_client_mappings_table.php`
- `database/migrations/2026_03_14_000004_create_payments_table.php`
- `database/migrations/2026_03_14_000005_create_accounting_sync_logs_table.php`
- `database/migrations/2026_03_14_000006_create_webhook_events_table.php`

### Backend: Factories (3)
- `database/factories/PaymentFactory.php`
- `database/factories/PaymentIntegrationFactory.php`
- `database/factories/AccountingIntegrationFactory.php`

### Frontend: Pages (5)
- `resources/js/Pages/Payments.vue`
- `resources/js/Pages/IntegrationSettings.vue`
- `resources/js/Pages/SyncLogs.vue`
- `resources/js/Pages/PaymentSuccess.vue`
- `resources/js/Pages/PaymentFailure.vue`

### Frontend: UI Components -- Payment (5)
- `resources/js/packages/ui/src/Payment/PaymentsList.vue`
- `resources/js/packages/ui/src/Payment/PaymentRow.vue`
- `resources/js/packages/ui/src/Payment/RecordPaymentDialog.vue`
- `resources/js/packages/ui/src/Payment/PaymentStatusBadge.vue`
- `resources/js/packages/ui/src/Payment/PaymentSummaryBar.vue`

### Frontend: UI Components -- Integration (5)
- `resources/js/packages/ui/src/Integration/IntegrationCard.vue`
- `resources/js/packages/ui/src/Integration/ClientMappingDialog.vue`
- `resources/js/packages/ui/src/Integration/ClientMappingTable.vue`
- `resources/js/packages/ui/src/Integration/SyncStatusBadge.vue`
- `resources/js/packages/ui/src/Integration/SyncLogTable.vue`

### Frontend: Stores and Types (4)
- `resources/js/utils/usePayments.ts`
- `resources/js/utils/useIntegrations.ts`
- `resources/js/types/payment.d.ts`
- `resources/js/types/integration.d.ts`

### Frontend: Tests (4)
- `resources/js/packages/ui/src/Payment/__tests__/PaymentsList.test.ts`
- `resources/js/packages/ui/src/Payment/__tests__/RecordPaymentDialog.test.ts`
- `resources/js/packages/ui/src/Integration/__tests__/IntegrationCard.test.ts`
- `resources/js/packages/ui/src/Integration/__tests__/ClientMappingDialog.test.ts`

### Backend: Tests (9)
- `tests/Unit/Endpoint/Api/V1/PaymentEndpointTest.php`
- `tests/Unit/Endpoint/Api/V1/IntegrationEndpointTest.php`
- `tests/Unit/Endpoint/Api/V1/WebhookEndpointTest.php`
- `tests/Unit/Service/PaymentServiceTest.php`
- `tests/Unit/Service/StripeServiceTest.php`
- `tests/Unit/Service/PayPalServiceTest.php`
- `tests/Unit/Service/QuickBooksServiceTest.php`
- `tests/Unit/Service/XeroServiceTest.php`
- `tests/Unit/Service/AccountingSyncServiceTest.php`

### E2E Tests (2)
- `e2e/payments.spec.ts`
- `e2e/integrations.spec.ts`

## Files to Modify
- `routes/api.php` (add payment, integration, and webhook route groups)
- `routes/web.php` (add Inertia page routes, OAuth callback routes, public payment link)
- `resources/js/Layouts/AppLayout.vue` (add Payments sidebar nav item)
- `app/Models/Invoice.php` (add `payments()`, `completedPayments()`, `totalPaid()`, `totalRefunded()`, `amountDue()`)
- `app/Providers/AppServiceProvider.php` (add morph map for `invoice` and `payment`)
- `app/Providers/JetstreamServiceProvider.php` (register `PaymentPermissions::register()`)
- `app/Console/Kernel.php` (add 2 scheduled tasks: RefreshOAuthTokenJob hourly, RetryFailedSyncJob every 15 min)
- `config/services.php` (add Stripe, PayPal, QuickBooks, Xero credential configs)
- `composer.json` (add 4 new packages)
- `openapi.json` (add 15 endpoint definitions)
- `resources/js/packages/api/src/openapi.json.client.ts` (regenerate from OpenAPI spec)

## Environment Variables Required
```env
# Stripe
STRIPE_CLIENT_ID=
STRIPE_SECRET_KEY=
STRIPE_WEBHOOK_SECRET=
STRIPE_CONNECT_RETURN_URL=

# PayPal
PAYPAL_CLIENT_ID=
PAYPAL_CLIENT_SECRET=
PAYPAL_WEBHOOK_ID=
PAYPAL_MODE=sandbox

# QuickBooks Online
QUICKBOOKS_CLIENT_ID=
QUICKBOOKS_CLIENT_SECRET=
QUICKBOOKS_REDIRECT_URI=
QUICKBOOKS_ENVIRONMENT=sandbox

# Xero
XERO_CLIENT_ID=
XERO_CLIENT_SECRET=
XERO_REDIRECT_URI=
```

## Quality Gates
- [ ] `composer fix && composer analyse` passes
- [ ] `npm run lint:fix && npm run format` passes
- [ ] `npm run build` succeeds
- [ ] All 15 API endpoints have endpoint tests
- [ ] All 6 service classes have unit tests
- [ ] Webhook signature verification tests passing
- [ ] Frontend component tests passing (Vitest)
- [ ] E2E tests cover: manual payment recording, payment list, integration connection (mock OAuth), client mapping, sync logs
- [ ] OAuth tokens encrypted at rest (verified with database inspection)
- [ ] Feature gated behind `canAccessPremiumFeatures()`
- [ ] OpenAPI spec updated and TypeScript client regenerated

## Planning Docs
- `PRD.md` -- Product requirements
- `task_assignments_20260209.md` -- Task breakdown
- `ARCHITECTURE.md` -- Technical architecture
- `CODEBASE-ANALYSIS.md` -- Integration points
- `SPRINT-PLAN.md` -- Sprint-by-sprint implementation plan
