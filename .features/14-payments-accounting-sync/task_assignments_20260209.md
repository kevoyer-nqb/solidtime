# Task Assignments: Online Payments and Accounting Sync

Generated: 2026-02-09
PRD Reference: `/home/keven/Documents/solidtime-analysis/.features/14-payments-accounting-sync/PRD.md`

---

## Task Assignment Table

| Task ID  | Description                                                          | Type                | Assigned Sub-Agent  | Dependencies                  | Effort   | Status |
|----------|----------------------------------------------------------------------|---------------------|---------------------|-------------------------------|----------|--------|
| PAY-001  | Create database migrations for all new tables                        | Backend             | Backend Dev         | None                          | 8 hours  | To Do  |
| PAY-002  | Create Eloquent models with relations, casts, and traits             | Backend             | Backend Dev         | PAY-001                       | 8 hours  | To Do  |
| PAY-003  | Create PaymentService with core payment business logic               | Backend             | Backend Dev         | PAY-002                       | 12 hours | To Do  |
| PAY-004  | Create PaymentController with CRUD endpoints                         | Backend             | Backend Dev         | PAY-003                       | 8 hours  | To Do  |
| PAY-005  | Create request validation classes for payment endpoints              | Backend             | Backend Dev         | PAY-004                       | 4 hours  | To Do  |
| PAY-006  | Create PaymentIntegrationController with OAuth flow endpoints               | Backend             | Backend Dev         | PAY-002                       | 8 hours  | To Do  |
| PAY-007  | Create StripeService for Connect OAuth and Checkout Sessions         | Backend             | Backend Dev         | PAY-002                       | 12 hours | To Do  |
| PAY-008  | Create PayPalService for OAuth and Order creation                    | Backend             | Backend Dev         | PAY-002                       | 10 hours | To Do  |
| PAY-009  | Create PaymentWebhookController with Stripe and PayPal handlers            | Backend             | Backend Dev         | PAY-007, PAY-008              | 8 hours  | To Do  |
| PAY-010  | Implement webhook signature verification middleware                  | Backend             | Backend Dev         | PAY-009                       | 4 hours  | To Do  |
| PAY-011  | Create ProcessWebhookJob for async webhook processing               | Backend             | Backend Dev         | PAY-009, PAY-003              | 6 hours  | To Do  |
| PAY-012  | Create QuickBooksService for OAuth, invoice/payment push, customers  | Backend             | Backend Dev         | PAY-002                       | 16 hours | To Do  |
| PAY-013  | Create XeroService for OAuth, invoice/payment push, contacts         | Backend             | Backend Dev         | PAY-002                       | 14 hours | To Do  |
| PAY-014  | Create AccountingSyncService for orchestrating sync operations       | Backend             | Backend Dev         | PAY-012, PAY-013              | 8 hours  | To Do  |
| PAY-015  | Create SyncInvoiceJob and SyncPaymentJob queued jobs                 | Backend             | Backend Dev         | PAY-014                       | 6 hours  | To Do  |
| PAY-016  | Create RefreshOAuthTokenJob for proactive token refresh              | Backend             | Backend Dev         | PAY-012, PAY-013              | 4 hours  | To Do  |
| PAY-017  | Register all API routes for payments, integrations, webhooks         | Backend             | Backend Dev         | PAY-004, PAY-006, PAY-009     | 2 hours  | To Do  |
| PAY-018  | Create request validation classes for integration endpoints          | Backend             | Backend Dev         | PAY-006                       | 4 hours  | To Do  |
| PAY-019  | Create public payment link route and redirect logic                  | Backend             | Backend Dev         | PAY-007, PAY-008              | 4 hours  | To Do  |
| PAY-020  | Add new permissions to role definitions                              | Backend             | Backend Dev         | None                          | 2 hours  | To Do  |
| PAY-021  | Update OpenAPI spec and regenerate TypeScript client                 | Backend / Docs      | Backend Dev         | PAY-017                       | 6 hours  | To Do  |
| PAY-022  | Create usePaymentsStore Pinia store with TypeScript types            | Frontend            | Frontend Dev        | PAY-021                       | 8 hours  | To Do  |
| PAY-023  | Create useIntegrationsStore Pinia store with TypeScript types        | Frontend            | Frontend Dev        | PAY-021                       | 6 hours  | To Do  |
| PAY-024  | Create IntegrationSettings.vue page with provider cards              | Frontend            | Frontend Dev        | PAY-023                       | 12 hours | To Do  |
| PAY-025  | Create ClientMappingDialog.vue for accounting client mapping         | Frontend            | Frontend Dev        | PAY-024                       | 8 hours  | To Do  |
| PAY-026  | Create PaymentsList.vue page with filtering and sorting              | Frontend            | Frontend Dev        | PAY-022                       | 8 hours  | To Do  |
| PAY-027  | Extend InvoiceDetail.vue with payment history and Record Payment     | Frontend            | Frontend Dev        | PAY-022                       | 10 hours | To Do  |
| PAY-028  | Create RecordPaymentDialog.vue modal component                       | Frontend            | Frontend Dev        | PAY-027                       | 4 hours  | To Do  |
| PAY-029  | Create PaymentStatusBadge.vue and SyncStatusBadge.vue components     | Frontend            | Frontend Dev        | PAY-022                       | 2 hours  | To Do  |
| PAY-030  | Create SyncLogsPage.vue for viewing sync history                     | Frontend            | Frontend Dev        | PAY-023                       | 6 hours  | To Do  |
| PAY-031  | Add web routes and sidebar navigation for payments and integrations  | Frontend            | Frontend Dev        | PAY-024, PAY-026              | 2 hours  | To Do  |
| PAY-032  | Create payment success/failure public pages                          | Frontend            | Frontend Dev        | PAY-019                       | 4 hours  | To Do  |
| PAY-033  | Backend endpoint tests for PaymentController                         | Testing             | Backend QA          | PAY-004, PAY-005, PAY-017     | 8 hours  | To Do  |
| PAY-034  | Backend endpoint tests for PaymentIntegrationController                     | Testing             | Backend QA          | PAY-006, PAY-018, PAY-017     | 8 hours  | To Do  |
| PAY-035  | Backend unit tests for PaymentService                                | Testing             | Backend QA          | PAY-003                       | 6 hours  | To Do  |
| PAY-036  | Backend unit tests for StripeService                                 | Testing             | Backend QA          | PAY-007                       | 6 hours  | To Do  |
| PAY-037  | Backend unit tests for PayPalService                                 | Testing             | Backend QA          | PAY-008                       | 6 hours  | To Do  |
| PAY-038  | Backend unit tests for QuickBooksService                             | Testing             | Backend QA          | PAY-012                       | 8 hours  | To Do  |
| PAY-039  | Backend unit tests for XeroService                                   | Testing             | Backend QA          | PAY-013                       | 8 hours  | To Do  |
| PAY-040  | Backend unit tests for AccountingSyncService                         | Testing             | Backend QA          | PAY-014                       | 6 hours  | To Do  |
| PAY-041  | Backend tests for webhook handling and signature verification        | Testing             | Backend QA          | PAY-009, PAY-010, PAY-011     | 8 hours  | To Do  |
| PAY-042  | Frontend component tests for IntegrationSettings                     | Testing             | Frontend QA         | PAY-024                       | 6 hours  | To Do  |
| PAY-043  | Frontend component tests for PaymentsList and RecordPayment          | Testing             | Frontend QA         | PAY-026, PAY-028              | 6 hours  | To Do  |
| PAY-044  | E2E Playwright tests for payment and integration flows               | Testing             | QA                  | PAY-031, PAY-027              | 10 hours | To Do  |
| PAY-045  | Add JSDoc comments to Pinia stores                                   | Docs                | Frontend Dev        | PAY-022, PAY-023              | 2 hours  | To Do  |
| PAY-046  | Create environment variable documentation for API keys and secrets   | Docs                | Backend Dev         | PAY-007, PAY-008, PAY-012, PAY-013 | 2 hours | To Do |
| PAY-047  | Implement retry logic for failed accounting syncs (scheduled command)| Backend             | Backend Dev         | PAY-015                       | 4 hours  | To Do  |
| PAY-048  | Add encryption for OAuth tokens in database                          | Backend             | Backend Dev         | PAY-002                       | 4 hours  | To Do  |

---

## Summary

| Metric                    | Value       |
|---------------------------|-------------|
| Total Tasks               | 48          |
| Total Effort              | 320 hours   |
| Total Story Points        | ~213 SP     |
| Estimated Duration        | 5 sprints (10 weeks) |
| Backend Tasks             | 22          |
| Frontend Tasks            | 11          |
| Testing Tasks             | 12          |
| Documentation Tasks       | 3           |

---

## Sprint Allocation

### Sprint 1 (Weeks 1-2): Database, Models, Core Services

| Task ID  | Description                                                         | Assignee         | SP  |
|----------|---------------------------------------------------------------------|------------------|-----|
| PAY-001  | Create database migrations for all new tables                       | Backend Dev      | 5   |
| PAY-002  | Create Eloquent models with relations, casts, and traits            | Backend Dev      | 5   |
| PAY-020  | Add new permissions to role definitions                             | Backend Dev      | 1   |
| PAY-048  | Add encryption for OAuth tokens in database                         | Backend Dev      | 3   |
| PAY-003  | Create PaymentService with core payment business logic              | Backend Dev      | 8   |
| PAY-007  | Create StripeService for Connect OAuth and Checkout Sessions        | Backend Dev      | 8   |
| PAY-008  | Create PayPalService for OAuth and Order creation                   | Backend Dev      | 7   |
| PAY-035  | Backend unit tests for PaymentService                               | Backend QA       | 5   |
| **Total** |                                                                     |                  | **42** |

### Sprint 2 (Weeks 3-4): Controllers, Routes, Webhooks, Payment Provider Tests

| Task ID  | Description                                                         | Assignee         | SP  |
|----------|---------------------------------------------------------------------|------------------|-----|
| PAY-004  | Create PaymentController with CRUD endpoints                        | Backend Dev      | 5   |
| PAY-005  | Create request validation classes for payment endpoints             | Backend Dev      | 3   |
| PAY-006  | Create PaymentIntegrationController with OAuth flow endpoints              | Backend Dev      | 5   |
| PAY-018  | Create request validation classes for integration endpoints         | Backend Dev      | 3   |
| PAY-009  | Create PaymentWebhookController with Stripe and PayPal handlers           | Backend Dev      | 5   |
| PAY-010  | Implement webhook signature verification middleware                 | Backend Dev      | 3   |
| PAY-011  | Create ProcessWebhookJob for async webhook processing              | Backend Dev      | 4   |
| PAY-019  | Create public payment link route and redirect logic                 | Backend Dev      | 3   |
| PAY-017  | Register all API routes                                             | Backend Dev      | 1   |
| PAY-036  | Backend unit tests for StripeService                                | Backend QA       | 5   |
| PAY-037  | Backend unit tests for PayPalService                                | Backend QA       | 5   |
| **Total** |                                                                     |                  | **42** |

### Sprint 3 (Weeks 5-6): Accounting Services, OpenAPI, Frontend Stores

| Task ID  | Description                                                         | Assignee         | SP  |
|----------|---------------------------------------------------------------------|------------------|-----|
| PAY-012  | Create QuickBooksService                                            | Backend Dev      | 10  |
| PAY-013  | Create XeroService                                                  | Backend Dev      | 9   |
| PAY-014  | Create AccountingSyncService                                        | Backend Dev      | 5   |
| PAY-015  | Create SyncInvoiceJob and SyncPaymentJob                           | Backend Dev      | 4   |
| PAY-016  | Create RefreshOAuthTokenJob                                         | Backend Dev      | 3   |
| PAY-021  | Update OpenAPI spec and regenerate TypeScript client                | Backend Dev      | 4   |
| PAY-022  | Create usePaymentsStore Pinia store                                 | Frontend Dev     | 5   |
| PAY-023  | Create useIntegrationsStore Pinia store                             | Frontend Dev     | 4   |
| **Total** |                                                                     |                  | **44** |

### Sprint 4 (Weeks 7-8): Frontend Implementation

| Task ID  | Description                                                         | Assignee         | SP  |
|----------|---------------------------------------------------------------------|------------------|-----|
| PAY-024  | Create IntegrationSettings.vue page                                 | Frontend Dev     | 8   |
| PAY-025  | Create ClientMappingDialog.vue                                      | Frontend Dev     | 5   |
| PAY-026  | Create PaymentsList.vue page                                        | Frontend Dev     | 5   |
| PAY-027  | Extend InvoiceDetail.vue with payment history                       | Frontend Dev     | 7   |
| PAY-028  | Create RecordPaymentDialog.vue                                      | Frontend Dev     | 3   |
| PAY-029  | Create PaymentStatusBadge and SyncStatusBadge                       | Frontend Dev     | 1   |
| PAY-030  | Create SyncLogsPage.vue                                             | Frontend Dev     | 4   |
| PAY-031  | Add web routes and sidebar navigation                               | Frontend Dev     | 1   |
| PAY-032  | Create payment success/failure public pages                         | Frontend Dev     | 3   |
| PAY-047  | Implement retry logic for failed accounting syncs                   | Backend Dev      | 3   |
| **Total** |                                                                     |                  | **40** |

### Sprint 5 (Weeks 9-10): Testing, Documentation, Polish

| Task ID  | Description                                                         | Assignee         | SP  |
|----------|---------------------------------------------------------------------|------------------|-----|
| PAY-033  | Backend endpoint tests for PaymentController                        | Backend QA       | 5   |
| PAY-034  | Backend endpoint tests for PaymentIntegrationController                    | Backend QA       | 5   |
| PAY-038  | Backend unit tests for QuickBooksService                            | Backend QA       | 5   |
| PAY-039  | Backend unit tests for XeroService                                  | Backend QA       | 5   |
| PAY-040  | Backend unit tests for AccountingSyncService                        | Backend QA       | 4   |
| PAY-041  | Backend tests for webhook handling                                  | Backend QA       | 5   |
| PAY-042  | Frontend component tests for IntegrationSettings                    | Frontend QA      | 4   |
| PAY-043  | Frontend component tests for PaymentsList and RecordPayment         | Frontend QA      | 4   |
| PAY-044  | E2E Playwright tests                                                | QA               | 7   |
| PAY-045  | Add JSDoc comments to Pinia stores                                  | Frontend Dev     | 1   |
| PAY-046  | Create environment variable documentation                           | Backend Dev      | 1   |
| **Total** |                                                                     |                  | **46** |

---

## Dependency Graph

```
Wave 1 (No dependencies -- can start immediately):
    PAY-001, PAY-020

Wave 2 (depends on Wave 1):
    PAY-002 (depends on PAY-001)

Wave 3 (depends on Wave 2):
    PAY-003 (depends on PAY-002)
    PAY-006 (depends on PAY-002)
    PAY-007 (depends on PAY-002)
    PAY-008 (depends on PAY-002)
    PAY-012 (depends on PAY-002)
    PAY-013 (depends on PAY-002)
    PAY-048 (depends on PAY-002)

Wave 4 (depends on Wave 3):
    PAY-004 (depends on PAY-003)
    PAY-009 (depends on PAY-007, PAY-008)
    PAY-011 (depends on PAY-009, PAY-003)  [partial -- PAY-009 from this wave]
    PAY-014 (depends on PAY-012, PAY-013)
    PAY-016 (depends on PAY-012, PAY-013)
    PAY-018 (depends on PAY-006)
    PAY-019 (depends on PAY-007, PAY-008)
    PAY-035 (depends on PAY-003)
    PAY-036 (depends on PAY-007)
    PAY-037 (depends on PAY-008)
    PAY-038 (depends on PAY-012)
    PAY-039 (depends on PAY-013)

Wave 5 (depends on Wave 4):
    PAY-005 (depends on PAY-004)
    PAY-010 (depends on PAY-009)
    PAY-015 (depends on PAY-014)
    PAY-017 (depends on PAY-004, PAY-006, PAY-009)
    PAY-040 (depends on PAY-014)
    PAY-046 (depends on PAY-007, PAY-008, PAY-012, PAY-013)

Wave 6 (depends on Wave 5):
    PAY-021 (depends on PAY-017)
    PAY-033 (depends on PAY-004, PAY-005, PAY-017)
    PAY-034 (depends on PAY-006, PAY-018, PAY-017)
    PAY-041 (depends on PAY-009, PAY-010, PAY-011)
    PAY-047 (depends on PAY-015)

Wave 7 (depends on Wave 6):
    PAY-022 (depends on PAY-021)
    PAY-023 (depends on PAY-021)

Wave 8 (depends on Wave 7):
    PAY-024 (depends on PAY-023)
    PAY-026 (depends on PAY-022)
    PAY-027 (depends on PAY-022)
    PAY-029 (depends on PAY-022)
    PAY-030 (depends on PAY-023)
    PAY-045 (depends on PAY-022, PAY-023)

Wave 9 (depends on Wave 8):
    PAY-025 (depends on PAY-024)
    PAY-028 (depends on PAY-027)
    PAY-031 (depends on PAY-024, PAY-026)
    PAY-032 (depends on PAY-019)
    PAY-042 (depends on PAY-024)
    PAY-043 (depends on PAY-026, PAY-028)

Wave 10 (depends on Wave 9):
    PAY-044 (depends on PAY-031, PAY-027)
```

### Critical Path

```
PAY-001 -> PAY-002 -> PAY-003 -> PAY-004 -> PAY-005 -> PAY-017 -> PAY-021 -> PAY-022 -> PAY-027 -> PAY-028
                                                                                                      |
                                                                                          PAY-031 -> PAY-044
```

**Critical path duration**: ~72 hours (8 + 8 + 12 + 8 + 4 + 2 + 6 + 8 + 10 + 4 + 2 + 10)

### Parallelization Opportunities

Several streams can run in parallel once PAY-002 (models) is complete:

- **Stream A (Payments)**: PAY-003 -> PAY-004 -> PAY-005 -> PAY-017
- **Stream B (Stripe)**: PAY-007 -> PAY-009 -> PAY-010 -> PAY-011
- **Stream C (PayPal)**: PAY-008 (merges into PAY-009 with Stream B)
- **Stream D (QuickBooks)**: PAY-012 -> PAY-014 -> PAY-015
- **Stream E (Xero)**: PAY-013 (merges into PAY-014 with Stream D)
- **Stream F (Integration Controller)**: PAY-006 -> PAY-018

Streams B, C, D, E, and F can all run in parallel with Stream A after Wave 2.
