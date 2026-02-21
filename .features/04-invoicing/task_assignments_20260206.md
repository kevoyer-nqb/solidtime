# Task Assignments -- Invoicing System

Generated: 2026-02-06
PRD Reference: `/home/keven/Documents/solidtime-analysis/.features/04-invoicing/PRD.md`

---

## Task Assignment Table

| Task ID | Description | Type | Assigned Sub-Agent | Dependencies | Effort | Status |
|---------|-------------|------|-------------------|--------------|--------|--------|
| INV-001 | Database Migrations -- Core Invoice Tables (invoices, invoice_lines, invoice_payments, invoice_templates, recurring_invoice_schedules) | Backend | Backend Dev | None | 8 hours (3 SP) | To Do |
| INV-002 | Database Migration -- Extend Clients Table with billing columns (address, tax_id, billing_email, payment_terms_days) | Backend | Backend Dev | None | 4 hours (2 SP) | To Do |
| INV-003 | Database Migration -- Extend Organizations Table with invoice settings (number prefix, tax config, billing address, logo) | Backend | Backend Dev | None | 4 hours (2 SP) | To Do |
| INV-004 | Database Migration -- Add invoice_id FK to time_entries table | Backend | Backend Dev | INV-001 | 2 hours (1 SP) | To Do |
| INV-005 | Eloquent Models -- Invoice, InvoiceLine, InvoicePayment, InvoiceTemplate, RecurringInvoiceSchedule + Enums | Backend | Backend Dev | INV-001 | 8 hours (3 SP) | To Do |
| INV-006 | Model Factories and Seeders for all new Invoice models | Backend | Backend Dev | INV-005 | 6 hours (2 SP) | To Do |
| INV-007 | Client Billing Fields -- Backend API (extend ClientController, request validation, resource) | Backend | Backend Dev | INV-002 | 4 hours (2 SP) | To Do |
| INV-008 | Client Billing Fields -- Frontend UI (ClientBillingForm.vue, country dropdown, email validation) | Frontend | Frontend Dev | INV-007 | 6 hours (2 SP) | To Do |
| INV-009 | InvoiceService -- Core Business Logic (number generation, preview, create, status transitions, totals, snapshots) | Backend | Backend Dev | INV-005, INV-006 | 16 hours (5 SP) | To Do |
| INV-010 | InvoiceController -- API Endpoints (index, show, preview, store, update, send, void, destroy, pdf) | Backend | Backend Dev | INV-009 | 12 hours (5 SP) | To Do |
| INV-011 | Invoice API Routes -- Register routes in routes/api.php | Backend | Backend Dev | INV-010 | 2 hours (1 SP) | To Do |
| INV-012 | Invoice Permissions Registration -- Jetstream roles + frontend permission helpers | Backend | Backend Dev | INV-010 | 4 hours (2 SP) | To Do |
| INV-013 | Invoice PDF Service -- Gotenberg rendering with Blade templates | Backend | Backend Dev | INV-005, INV-009 | 12 hours (5 SP) | To Do |
| INV-014 | Invoice Settings API -- Extend OrganizationController for invoice config (number format, tax, billing info) | Backend | Backend Dev | INV-003, INV-005 | 6 hours (2 SP) | To Do |
| INV-015 | OpenAPI Spec Update and TypeScript Client Regeneration | Backend/Frontend | Backend Dev | INV-010, INV-011 | 4 hours (2 SP) | To Do |
| INV-016 | Invoice Pinia Store -- useInvoices.ts (CRUD, send, void, preview, payment recording) | Frontend | Frontend Dev | INV-015 | 8 hours (3 SP) | To Do |
| INV-017 | Invoices List Page -- Vue page, table, status badges, filters, pagination | Frontend | Frontend Dev | INV-016 | 12 hours (5 SP) | To Do |
| INV-018 | Invoice Detail/Edit Page -- header, line items, summary, actions, payment history | Frontend | Frontend Dev | INV-016 | 16 hours (5 SP) | To Do |
| INV-019 | Invoice Creation Wizard -- multi-step flow (client select, time filter, grouping, preview, create) | Frontend | Frontend Dev | INV-016, INV-018 | 16 hours (5 SP) | To Do |
| INV-020 | Invoice PDF Preview -- Frontend PDF preview/download via Gotenberg | Frontend | Frontend Dev | INV-013, INV-016 | 4 hours (2 SP) | To Do |
| INV-021 | Invoice Settings UI -- organization settings panel (number format, tax, billing, logo upload) | Frontend | Frontend Dev | INV-014 | 8 hours (3 SP) | To Do |
| INV-022 | Invoice Email Service -- Laravel Mailable with PDF attachment, send action | Backend | Backend Dev | INV-013 | 8 hours (3 SP) | To Do |
| INV-023 | Overdue Invoice Detection Command -- artisan command + scheduler registration | Backend | Backend Dev | INV-009 | 4 hours (2 SP) | To Do |
| INV-024 | Invoice Payment Controller -- record payments, list payments, status transitions | Backend | Backend Dev | INV-009, INV-011 | 6 hours (2 SP) | To Do |
| INV-025 | Recurring Invoice Schedule Service -- service, scheduler command, CRUD controller | Backend | Backend Dev | INV-005, INV-009 | 12 hours (5 SP) | To Do |
| INV-026 | Recurring Invoices Frontend -- schedule list, create/edit form, toggle, Pinia store | Frontend | Frontend Dev | INV-025, INV-016 | 10 hours (3 SP) | To Do |
| INV-027 | Stripe Payment Integration -- Checkout session, webhook handler, payment recording | Backend | Backend Dev | INV-024 | 16 hours (5 SP) | To Do |
| INV-028 | Stripe Payment Frontend -- payment settings, payment link UI | Frontend | Frontend Dev | INV-027 | 6 hours (2 SP) | To Do |
| INV-029 | Accounting Export Service -- Generic CSV, QuickBooks IIF, Xero CSV export | Backend | Backend Dev | INV-010 | 10 hours (3 SP) | To Do |
| INV-030 | Accounting Export Frontend -- export button, format selector, date range dialog | Frontend | Frontend Dev | INV-029 | 4 hours (2 SP) | To Do |
| INV-031 | Backend Unit Tests -- InvoiceService (number gen, creation, totals, state transitions, void) | Testing | Backend Dev | INV-009 | 12 hours (5 SP) | To Do |
| INV-032 | Backend Endpoint Tests -- InvoiceController + InvoicePaymentController (permissions, CRUD, validation) | Testing | Backend Dev | INV-010, INV-011, INV-012 | 16 hours (5 SP) | To Do |
| INV-033 | Backend Tests -- RecurringInvoiceService + Payment recording tests | Testing | Backend Dev | INV-025, INV-024 | 8 hours (3 SP) | To Do |
| INV-034 | Frontend Component Tests -- Vitest tests for InvoiceTable, StatusBadge, LineItems, Wizard | Testing | Frontend Dev | INV-017, INV-018, INV-019 | 10 hours (3 SP) | To Do |
| INV-035 | E2E Tests -- Playwright tests for invoice CRUD, wizard, payment, send workflows | Testing | QA | INV-017, INV-018, INV-019 | 12 hours (5 SP) | To Do |

---

## Sprint Allocation

### Sprint 1 (Weeks 1-2) -- Foundation

| Task ID | Description | Assigned | Effort | Status |
|---------|-------------|----------|--------|--------|
| INV-001 | Core Invoice Tables | Backend Dev | 8h | To Do |
| INV-002 | Client Billing Columns | Backend Dev | 4h | To Do |
| INV-003 | Org Invoice Settings | Backend Dev | 4h | To Do |
| INV-004 | TimeEntry invoice_id | Backend Dev | 2h | To Do |
| INV-005 | Eloquent Models + Enums | Backend Dev | 8h | To Do |
| INV-006 | Model Factories | Backend Dev | 6h | To Do |
| INV-007 | Client Billing API | Backend Dev | 4h | To Do |
| INV-008 | Client Billing UI | Frontend Dev | 6h | To Do |

**Sprint 1 Total**: 42 hours, 17 SP

### Sprint 2 (Weeks 3-4) -- Core Invoice CRUD

| Task ID | Description | Assigned | Effort | Status |
|---------|-------------|----------|--------|--------|
| INV-009 | InvoiceService | Backend Dev | 16h | To Do |
| INV-010 | InvoiceController | Backend Dev | 12h | To Do |
| INV-011 | API Routes | Backend Dev | 2h | To Do |
| INV-012 | Permissions | Backend Dev | 4h | To Do |
| INV-013 | PDF Service | Backend Dev | 12h | To Do |
| INV-014 | Settings API | Backend Dev | 6h | To Do |
| INV-015 | OpenAPI + TS Client | Backend Dev | 4h | To Do |

**Sprint 2 Total**: 56 hours, 22 SP

### Sprint 3 (Weeks 5-6) -- Frontend Invoice UI

| Task ID | Description | Assigned | Effort | Status |
|---------|-------------|----------|--------|--------|
| INV-016 | Pinia Store | Frontend Dev | 8h | To Do |
| INV-017 | Invoice List Page | Frontend Dev | 12h | To Do |
| INV-018 | Invoice Detail Page | Frontend Dev | 16h | To Do |
| INV-019 | Creation Wizard | Frontend Dev | 16h | To Do |
| INV-020 | PDF Preview UI | Frontend Dev | 4h | To Do |

**Sprint 3 Total**: 56 hours, 20 SP

### Sprint 4 (Weeks 7-8) -- Settings, Email, Payments

| Task ID | Description | Assigned | Effort | Status |
|---------|-------------|----------|--------|--------|
| INV-021 | Invoice Settings UI | Frontend Dev | 8h | To Do |
| INV-022 | Email Service | Backend Dev | 8h | To Do |
| INV-023 | Overdue Command | Backend Dev | 4h | To Do |
| INV-024 | Payment Controller | Backend Dev | 6h | To Do |
| INV-031 | InvoiceService Tests | Backend Dev | 12h | To Do |

**Sprint 4 Total**: 38 hours, 15 SP

### Sprint 5 (Weeks 9-10) -- Recurring Invoices

| Task ID | Description | Assigned | Effort | Status |
|---------|-------------|----------|--------|--------|
| INV-025 | Recurring Service | Backend Dev | 12h | To Do |
| INV-026 | Recurring UI | Frontend Dev | 10h | To Do |
| INV-032 | Endpoint Tests | Backend Dev | 16h | To Do |

**Sprint 5 Total**: 38 hours, 13 SP

### Sprint 6 (Weeks 11-12) -- Payments & Export

| Task ID | Description | Assigned | Effort | Status |
|---------|-------------|----------|--------|--------|
| INV-027 | Stripe Integration | Backend Dev | 16h | To Do |
| INV-028 | Stripe UI | Frontend Dev | 6h | To Do |
| INV-029 | Accounting Export | Backend Dev | 10h | To Do |
| INV-030 | Export UI | Frontend Dev | 4h | To Do |
| INV-033 | Recurring + Payment Tests | Backend Dev | 8h | To Do |

**Sprint 6 Total**: 44 hours, 15 SP

### Sprint 7 (Weeks 13-14) -- Testing & Polish

| Task ID | Description | Assigned | Effort | Status |
|---------|-------------|----------|--------|--------|
| INV-034 | Frontend Component Tests | Frontend Dev | 10h | To Do |
| INV-035 | E2E Tests | QA | 12h | To Do |
| INV-036 | Register Inertia web routes for invoice pages (AMD-10) | Backend Dev | 1h | To Do |

**Sprint 7 Total**: 23 hours, 9 SP
*Remaining sprint capacity used for bug fixes, polish, and documentation.*

---

## Dependency-Respecting Execution Order

The following is the recommended execution order respecting all dependencies:

**Phase 1 (can run in parallel):**
- INV-001, INV-002, INV-003 (no dependencies, all database migrations)

**Phase 2 (after Phase 1):**
- INV-004 (needs INV-001)
- INV-005 (needs INV-001)
- INV-007 (needs INV-002)

**Phase 3 (after Phase 2):**
- INV-006 (needs INV-005)
- INV-008 (needs INV-007)
- INV-014 (needs INV-003, INV-005)

**Phase 4 (after Phase 3):**
- INV-009 (needs INV-005, INV-006) -- **CRITICAL PATH**

**Phase 5 (after Phase 4):**
- INV-010 (needs INV-009) -- **CRITICAL PATH**
- INV-013 (needs INV-005, INV-009)
- INV-023 (needs INV-009)
- INV-031 (needs INV-009, testing)

**Phase 6 (after Phase 5):**
- INV-011 (needs INV-010)
- INV-012 (needs INV-010)

**Phase 7 (after Phase 6):**
- INV-015 (needs INV-010, INV-011) -- **CRITICAL PATH**
- INV-024 (needs INV-009, INV-011)
- INV-022 (needs INV-013)
- INV-029 (needs INV-010)
- INV-032 (needs INV-010, INV-011, INV-012, testing)

**Phase 8 (after Phase 7):**
- INV-016 (needs INV-015) -- **CRITICAL PATH**
- INV-025 (needs INV-005, INV-009)
- INV-027 (needs INV-024)

**Phase 9 (after Phase 8):**
- INV-017 (needs INV-016)
- INV-018 (needs INV-016) -- **CRITICAL PATH**
- INV-020 (needs INV-013, INV-016)
- INV-021 (needs INV-014)
- INV-026 (needs INV-025, INV-016)
- INV-028 (needs INV-027)
- INV-030 (needs INV-029)
- INV-033 (needs INV-025, INV-024, testing)

**Phase 10 (after Phase 9):**
- INV-019 (needs INV-016, INV-018) -- **CRITICAL PATH**

**Phase 11 (after Phase 10):**
- INV-034 (needs INV-017, INV-018, INV-019, testing)
- INV-035 (needs INV-017, INV-018, INV-019, testing) -- **CRITICAL PATH**

---

## Critical Path Analysis

**Critical path (longest dependent chain):**

```
INV-001 (8h) -> INV-005 (8h) -> INV-009 (16h) -> INV-010 (12h) -> INV-015 (4h) -> INV-016 (8h) -> INV-018 (16h) -> INV-019 (16h) -> INV-035 (12h)
```

**Critical path duration: 100 hours (~12.5 working days)**

**Key risk points on critical path:**
1. **INV-009 (InvoiceService, 16h)**: Largest single task. Delay here cascades to all downstream tasks. Mitigation: start immediately after models are ready; consider pair programming.
2. **INV-018 + INV-019 (Detail + Wizard, 32h combined)**: Large frontend effort. Mitigation: can be parallelized partially if INV-018 scaffolding is done first.
3. **INV-015 (OpenAPI regen, 4h)**: Bridge between backend and frontend. Mitigation: prioritize immediately after controller completion.

**Parallelizable work (not on critical path):**
- INV-002, INV-003 run parallel with INV-001
- INV-007, INV-008 run parallel with INV-005, INV-006
- INV-013 (PDF) runs parallel with INV-010 (Controller)
- INV-022 (Email) runs parallel with frontend sprint
- INV-025-033 (Recurring, Stripe, Export) are all off critical path

---

## Status Assignment Logic

All tasks are currently assigned **To Do** status because:
- No tasks have been started
- All Phase 1 tasks (INV-001, INV-002, INV-003) have no dependencies and can begin immediately
- No external blockers have been identified (Gotenberg is already configured, Stripe is a new but optional dependency)
- Tasks with dependencies are **To Do** (not Blocked) because their dependencies are also To Do, not blocked by external constraints

**Status will transition to:**
- **In Progress**: When a developer begins work on the task
- **Blocked**: Only if an external constraint prevents work (e.g., Stripe account approval delay for INV-027)
- **Completed**: When acceptance criteria are met and code is merged
