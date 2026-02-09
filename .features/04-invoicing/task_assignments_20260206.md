# Task Assignments -- Invoicing System

Generated: 2026-02-06
PRD Reference: `/home/keven/Documents/solidtime-analysis/.features/04-invoicing/PRD.md`

---

## Task Assignment Table

| Task ID | Description | Type | Assigned Sub-Agent | Dependencies | Effort | Status |
|---------|-------------|------|-------------------|--------------|--------|--------|
| TASK-001 | Database Migrations -- Core Invoice Tables (invoices, invoice_lines, invoice_payments, invoice_templates, recurring_invoice_schedules) | Backend | Backend Dev | None | 8 hours (3 SP) | To Do |
| TASK-002 | Database Migration -- Extend Clients Table with billing columns (address, tax_id, billing_email, payment_terms_days) | Backend | Backend Dev | None | 4 hours (2 SP) | To Do |
| TASK-003 | Database Migration -- Extend Organizations Table with invoice settings (number prefix, tax config, billing address, logo) | Backend | Backend Dev | None | 4 hours (2 SP) | To Do |
| TASK-004 | Database Migration -- Add invoice_id FK to time_entries table | Backend | Backend Dev | TASK-001 | 2 hours (1 SP) | To Do |
| TASK-005 | Eloquent Models -- Invoice, InvoiceLine, InvoicePayment, InvoiceTemplate, RecurringInvoiceSchedule + Enums | Backend | Backend Dev | TASK-001 | 8 hours (3 SP) | To Do |
| TASK-006 | Model Factories and Seeders for all new Invoice models | Backend | Backend Dev | TASK-005 | 6 hours (2 SP) | To Do |
| TASK-007 | Client Billing Fields -- Backend API (extend ClientController, request validation, resource) | Backend | Backend Dev | TASK-002 | 4 hours (2 SP) | To Do |
| TASK-008 | Client Billing Fields -- Frontend UI (ClientBillingForm.vue, country dropdown, email validation) | Frontend | Frontend Dev | TASK-007 | 6 hours (2 SP) | To Do |
| TASK-009 | InvoiceService -- Core Business Logic (number generation, preview, create, status transitions, totals, snapshots) | Backend | Backend Dev | TASK-005, TASK-006 | 16 hours (5 SP) | To Do |
| TASK-010 | InvoiceController -- API Endpoints (index, show, preview, store, update, send, void, destroy, pdf) | Backend | Backend Dev | TASK-009 | 12 hours (5 SP) | To Do |
| TASK-011 | Invoice API Routes -- Register routes in routes/api.php | Backend | Backend Dev | TASK-010 | 2 hours (1 SP) | To Do |
| TASK-012 | Invoice Permissions Registration -- Jetstream roles + frontend permission helpers | Backend | Backend Dev | TASK-010 | 4 hours (2 SP) | To Do |
| TASK-013 | Invoice PDF Service -- Gotenberg rendering with Blade templates | Backend | Backend Dev | TASK-005, TASK-009 | 12 hours (5 SP) | To Do |
| TASK-014 | Invoice Settings API -- Extend OrganizationController for invoice config (number format, tax, billing info) | Backend | Backend Dev | TASK-003, TASK-005 | 6 hours (2 SP) | To Do |
| TASK-015 | OpenAPI Spec Update and TypeScript Client Regeneration | Backend/Frontend | Backend Dev | TASK-010, TASK-011 | 4 hours (2 SP) | To Do |
| TASK-016 | Invoice Pinia Store -- useInvoices.ts (CRUD, send, void, preview, payment recording) | Frontend | Frontend Dev | TASK-015 | 8 hours (3 SP) | To Do |
| TASK-017 | Invoices List Page -- Vue page, table, status badges, filters, pagination | Frontend | Frontend Dev | TASK-016 | 12 hours (5 SP) | To Do |
| TASK-018 | Invoice Detail/Edit Page -- header, line items, summary, actions, payment history | Frontend | Frontend Dev | TASK-016 | 16 hours (5 SP) | To Do |
| TASK-019 | Invoice Creation Wizard -- multi-step flow (client select, time filter, grouping, preview, create) | Frontend | Frontend Dev | TASK-016, TASK-018 | 16 hours (5 SP) | To Do |
| TASK-020 | Invoice PDF Preview -- Frontend PDF preview/download via Gotenberg | Frontend | Frontend Dev | TASK-013, TASK-016 | 4 hours (2 SP) | To Do |
| TASK-021 | Invoice Settings UI -- organization settings panel (number format, tax, billing, logo upload) | Frontend | Frontend Dev | TASK-014 | 8 hours (3 SP) | To Do |
| TASK-022 | Invoice Email Service -- Laravel Mailable with PDF attachment, send action | Backend | Backend Dev | TASK-013 | 8 hours (3 SP) | To Do |
| TASK-023 | Overdue Invoice Detection Command -- artisan command + scheduler registration | Backend | Backend Dev | TASK-009 | 4 hours (2 SP) | To Do |
| TASK-024 | Invoice Payment Controller -- record payments, list payments, status transitions | Backend | Backend Dev | TASK-009, TASK-011 | 6 hours (2 SP) | To Do |
| TASK-025 | Recurring Invoice Schedule Service -- service, scheduler command, CRUD controller | Backend | Backend Dev | TASK-005, TASK-009 | 12 hours (5 SP) | To Do |
| TASK-026 | Recurring Invoices Frontend -- schedule list, create/edit form, toggle, Pinia store | Frontend | Frontend Dev | TASK-025, TASK-016 | 10 hours (3 SP) | To Do |
| TASK-027 | Stripe Payment Integration -- Checkout session, webhook handler, payment recording | Backend | Backend Dev | TASK-024 | 16 hours (5 SP) | To Do |
| TASK-028 | Stripe Payment Frontend -- payment settings, payment link UI | Frontend | Frontend Dev | TASK-027 | 6 hours (2 SP) | To Do |
| TASK-029 | Accounting Export Service -- Generic CSV, QuickBooks IIF, Xero CSV export | Backend | Backend Dev | TASK-010 | 10 hours (3 SP) | To Do |
| TASK-030 | Accounting Export Frontend -- export button, format selector, date range dialog | Frontend | Frontend Dev | TASK-029 | 4 hours (2 SP) | To Do |
| TASK-031 | Backend Unit Tests -- InvoiceService (number gen, creation, totals, state transitions, void) | Testing | Backend Dev | TASK-009 | 12 hours (5 SP) | To Do |
| TASK-032 | Backend Endpoint Tests -- InvoiceController + InvoicePaymentController (permissions, CRUD, validation) | Testing | Backend Dev | TASK-010, TASK-011, TASK-012 | 16 hours (5 SP) | To Do |
| TASK-033 | Backend Tests -- RecurringInvoiceService + Payment recording tests | Testing | Backend Dev | TASK-025, TASK-024 | 8 hours (3 SP) | To Do |
| TASK-034 | Frontend Component Tests -- Vitest tests for InvoiceTable, StatusBadge, LineItems, Wizard | Testing | Frontend Dev | TASK-017, TASK-018, TASK-019 | 10 hours (3 SP) | To Do |
| TASK-035 | E2E Tests -- Playwright tests for invoice CRUD, wizard, payment, send workflows | Testing | QA | TASK-017, TASK-018, TASK-019 | 12 hours (5 SP) | To Do |

---

## Sprint Allocation

### Sprint 1 (Weeks 1-2) -- Foundation

| Task ID | Description | Assigned | Effort | Status |
|---------|-------------|----------|--------|--------|
| TASK-001 | Core Invoice Tables | Backend Dev | 8h | To Do |
| TASK-002 | Client Billing Columns | Backend Dev | 4h | To Do |
| TASK-003 | Org Invoice Settings | Backend Dev | 4h | To Do |
| TASK-004 | TimeEntry invoice_id | Backend Dev | 2h | To Do |
| TASK-005 | Eloquent Models + Enums | Backend Dev | 8h | To Do |
| TASK-006 | Model Factories | Backend Dev | 6h | To Do |
| TASK-007 | Client Billing API | Backend Dev | 4h | To Do |
| TASK-008 | Client Billing UI | Frontend Dev | 6h | To Do |

**Sprint 1 Total**: 42 hours, 17 SP

### Sprint 2 (Weeks 3-4) -- Core Invoice CRUD

| Task ID | Description | Assigned | Effort | Status |
|---------|-------------|----------|--------|--------|
| TASK-009 | InvoiceService | Backend Dev | 16h | To Do |
| TASK-010 | InvoiceController | Backend Dev | 12h | To Do |
| TASK-011 | API Routes | Backend Dev | 2h | To Do |
| TASK-012 | Permissions | Backend Dev | 4h | To Do |
| TASK-013 | PDF Service | Backend Dev | 12h | To Do |
| TASK-014 | Settings API | Backend Dev | 6h | To Do |
| TASK-015 | OpenAPI + TS Client | Backend Dev | 4h | To Do |

**Sprint 2 Total**: 56 hours, 22 SP

### Sprint 3 (Weeks 5-6) -- Frontend Invoice UI

| Task ID | Description | Assigned | Effort | Status |
|---------|-------------|----------|--------|--------|
| TASK-016 | Pinia Store | Frontend Dev | 8h | To Do |
| TASK-017 | Invoice List Page | Frontend Dev | 12h | To Do |
| TASK-018 | Invoice Detail Page | Frontend Dev | 16h | To Do |
| TASK-019 | Creation Wizard | Frontend Dev | 16h | To Do |
| TASK-020 | PDF Preview UI | Frontend Dev | 4h | To Do |

**Sprint 3 Total**: 56 hours, 20 SP

### Sprint 4 (Weeks 7-8) -- Settings, Email, Payments

| Task ID | Description | Assigned | Effort | Status |
|---------|-------------|----------|--------|--------|
| TASK-021 | Invoice Settings UI | Frontend Dev | 8h | To Do |
| TASK-022 | Email Service | Backend Dev | 8h | To Do |
| TASK-023 | Overdue Command | Backend Dev | 4h | To Do |
| TASK-024 | Payment Controller | Backend Dev | 6h | To Do |
| TASK-031 | InvoiceService Tests | Backend Dev | 12h | To Do |

**Sprint 4 Total**: 38 hours, 15 SP

### Sprint 5 (Weeks 9-10) -- Recurring Invoices

| Task ID | Description | Assigned | Effort | Status |
|---------|-------------|----------|--------|--------|
| TASK-025 | Recurring Service | Backend Dev | 12h | To Do |
| TASK-026 | Recurring UI | Frontend Dev | 10h | To Do |
| TASK-032 | Endpoint Tests | Backend Dev | 16h | To Do |

**Sprint 5 Total**: 38 hours, 13 SP

### Sprint 6 (Weeks 11-12) -- Payments & Export

| Task ID | Description | Assigned | Effort | Status |
|---------|-------------|----------|--------|--------|
| TASK-027 | Stripe Integration | Backend Dev | 16h | To Do |
| TASK-028 | Stripe UI | Frontend Dev | 6h | To Do |
| TASK-029 | Accounting Export | Backend Dev | 10h | To Do |
| TASK-030 | Export UI | Frontend Dev | 4h | To Do |
| TASK-033 | Recurring + Payment Tests | Backend Dev | 8h | To Do |

**Sprint 6 Total**: 44 hours, 15 SP

### Sprint 7 (Weeks 13-14) -- Testing & Polish

| Task ID | Description | Assigned | Effort | Status |
|---------|-------------|----------|--------|--------|
| TASK-034 | Frontend Component Tests | Frontend Dev | 10h | To Do |
| TASK-035 | E2E Tests | QA | 12h | To Do |

**Sprint 7 Total**: 22 hours, 8 SP
*Remaining sprint capacity used for bug fixes, polish, and documentation.*

---

## Dependency-Respecting Execution Order

The following is the recommended execution order respecting all dependencies:

**Phase 1 (can run in parallel):**
- TASK-001, TASK-002, TASK-003 (no dependencies, all database migrations)

**Phase 2 (after Phase 1):**
- TASK-004 (needs TASK-001)
- TASK-005 (needs TASK-001)
- TASK-007 (needs TASK-002)

**Phase 3 (after Phase 2):**
- TASK-006 (needs TASK-005)
- TASK-008 (needs TASK-007)
- TASK-014 (needs TASK-003, TASK-005)

**Phase 4 (after Phase 3):**
- TASK-009 (needs TASK-005, TASK-006) -- **CRITICAL PATH**

**Phase 5 (after Phase 4):**
- TASK-010 (needs TASK-009) -- **CRITICAL PATH**
- TASK-013 (needs TASK-005, TASK-009)
- TASK-023 (needs TASK-009)
- TASK-031 (needs TASK-009, testing)

**Phase 6 (after Phase 5):**
- TASK-011 (needs TASK-010)
- TASK-012 (needs TASK-010)

**Phase 7 (after Phase 6):**
- TASK-015 (needs TASK-010, TASK-011) -- **CRITICAL PATH**
- TASK-024 (needs TASK-009, TASK-011)
- TASK-022 (needs TASK-013)
- TASK-029 (needs TASK-010)
- TASK-032 (needs TASK-010, TASK-011, TASK-012, testing)

**Phase 8 (after Phase 7):**
- TASK-016 (needs TASK-015) -- **CRITICAL PATH**
- TASK-025 (needs TASK-005, TASK-009)
- TASK-027 (needs TASK-024)

**Phase 9 (after Phase 8):**
- TASK-017 (needs TASK-016)
- TASK-018 (needs TASK-016) -- **CRITICAL PATH**
- TASK-020 (needs TASK-013, TASK-016)
- TASK-021 (needs TASK-014)
- TASK-026 (needs TASK-025, TASK-016)
- TASK-028 (needs TASK-027)
- TASK-030 (needs TASK-029)
- TASK-033 (needs TASK-025, TASK-024, testing)

**Phase 10 (after Phase 9):**
- TASK-019 (needs TASK-016, TASK-018) -- **CRITICAL PATH**

**Phase 11 (after Phase 10):**
- TASK-034 (needs TASK-017, TASK-018, TASK-019, testing)
- TASK-035 (needs TASK-017, TASK-018, TASK-019, testing) -- **CRITICAL PATH**

---

## Critical Path Analysis

**Critical path (longest dependent chain):**

```
TASK-001 (8h) -> TASK-005 (8h) -> TASK-009 (16h) -> TASK-010 (12h) -> TASK-015 (4h) -> TASK-016 (8h) -> TASK-018 (16h) -> TASK-019 (16h) -> TASK-035 (12h)
```

**Critical path duration: 100 hours (~12.5 working days)**

**Key risk points on critical path:**
1. **TASK-009 (InvoiceService, 16h)**: Largest single task. Delay here cascades to all downstream tasks. Mitigation: start immediately after models are ready; consider pair programming.
2. **TASK-018 + TASK-019 (Detail + Wizard, 32h combined)**: Large frontend effort. Mitigation: can be parallelized partially if TASK-018 scaffolding is done first.
3. **TASK-015 (OpenAPI regen, 4h)**: Bridge between backend and frontend. Mitigation: prioritize immediately after controller completion.

**Parallelizable work (not on critical path):**
- TASK-002, TASK-003 run parallel with TASK-001
- TASK-007, TASK-008 run parallel with TASK-005, TASK-006
- TASK-013 (PDF) runs parallel with TASK-010 (Controller)
- TASK-022 (Email) runs parallel with frontend sprint
- TASK-025-033 (Recurring, Stripe, Export) are all off critical path

---

## Status Assignment Logic

All tasks are currently assigned **To Do** status because:
- No tasks have been started
- All Phase 1 tasks (TASK-001, TASK-002, TASK-003) have no dependencies and can begin immediately
- No external blockers have been identified (Gotenberg is already configured, Stripe is a new but optional dependency)
- Tasks with dependencies are **To Do** (not Blocked) because their dependencies are also To Do, not blocked by external constraints

**Status will transition to:**
- **In Progress**: When a developer begins work on the task
- **Blocked**: Only if an external constraint prevents work (e.g., Stripe account approval delay for TASK-027)
- **Completed**: When acceptance criteria are met and code is merged
