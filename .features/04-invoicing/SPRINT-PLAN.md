# Sprint Plan: Invoicing System (Feature 04)

**Date**: 2026-02-06
**Feature Branch**: `feature/invoicing-system`
**PRD Reference**: `.features/04-invoicing/PRD.md`
**Architecture Reference**: `.features/04-invoicing/ARCHITECTURE.md`
**Task Assignments Reference**: `.features/04-invoicing/task_assignments_20260206.md`

---

## 1. Executive Summary

The Invoicing System is a major feature for Solidtime that enables users to generate professional invoices directly from tracked billable time entries, manage the full invoice lifecycle (draft, send, pay, void), set up recurring invoice schedules, accept online payments via Stripe, and export invoices to accounting software formats.

### Key Metrics

| Metric | Value |
|--------|-------|
| **Total Effort** | 297 hours |
| **Total Story Points** | 110 SP |
| **Number of Sprints** | 7 (14 weeks) |
| **Sprint Duration** | 2 weeks each |
| **Critical Path Duration** | 100 hours (~12.5 working days) |
| **Files to Create** | ~71 (backend + frontend) |
| **Files to Modify** | ~15 backend, ~3 frontend |
| **New DB Tables** | 5 (invoices, invoice_lines, invoice_payments, invoice_templates, recurring_invoice_schedules) |
| **Modified DB Tables** | 3 (clients, organizations, time_entries) |

### Team Size Assumptions

| Role | Count | Sprint Capacity (hours) | Notes |
|------|-------|------------------------|-------|
| Backend Developer | 1 | 60h per sprint | Handles Laravel models, services, controllers, migrations, backend tests |
| Frontend Developer | 1 | 60h per sprint | Handles Vue components, Pinia stores, pages, frontend tests |
| QA / Fullstack | 0.5 | 30h per sprint | E2E tests, integration testing, polish (shared resource) |

**Velocity Assumption**: 2.0 hours per story point (per SF-10 from SHARED-FOUNDATIONS.md).

---

## 2. Sprint Overview Table

| Sprint | Name | Duration | Story Points | Effort (hours) | Key Deliverables |
|:------:|------|----------|:------------:|:--------------:|------------------|
| 0 | Shared Foundations | 1 week (pre-sprint) | 15 | 30 | Notification infra, modular permissions (FOUND-001 to FOUND-007) |
| 1 | Database & Models | Weeks 1-2 | 17 | 42 | All migrations, Eloquent models, enums, factories, client billing UI |
| 2 | Core Invoice Backend | Weeks 3-4 | 21 | 56 | InvoiceService, InvoiceController, permissions, settings API, PDF service, OpenAPI regen |
| 3 | Invoice Frontend | Weeks 5-6 | 20 | 56 | Pinia store, invoice list page, detail/edit page, creation wizard, PDF preview |
| 4 | Email, Payments & Settings UI | Weeks 7-8 | 15 | 38 | Invoice email, overdue command, payment controller, settings UI, InvoiceService unit tests |
| 5 | Recurring Invoices & Endpoint Tests | Weeks 9-10 | 13 | 38 | Recurring invoice service/UI, comprehensive endpoint test suite |
| 6 | Stripe, Export & Integration Tests | Weeks 11-12 | 15 | 44 | Stripe integration, accounting export, recurring/payment tests |
| 7 | E2E Testing & Polish | Weeks 13-14 | 9 | 23 | Frontend component tests, Playwright E2E tests, web routes, bug fixes |
| | **TOTAL** | **14 weeks + 1 pre-sprint week** | **125** | **327** | |

> Note: Sprint 0 (Shared Foundations) accounts for 30 hours / 15 SP of prerequisite work from FOUND-001 through FOUND-007. These are cross-feature tasks that unblock the invoicing feature as well as other features. The 297 hours of feature-specific work spans Sprints 1-7.

---

## 3. Dependency Map

### 3.1 Shared Foundation Dependencies

The following FOUND-xxx tasks from SHARED-FOUNDATIONS.md must be completed before specific invoicing tasks can begin.

```
FOUND-007 (Modular Permissions, 4h)
    └── Blocks: INV-012 (Invoice Permissions Registration)

FOUND-001 (Notification Migration, 2h)
    └── FOUND-002 (Base Notification Classes, 4h)
        ├── FOUND-003 (Notification Bell UI, 8h)
        └── FOUND-004 (Notification API Endpoints, 6h)
            └── FOUND-005 (Notification Preferences, 4h)
                └── Blocks: INV-022 (Invoice Email Service - notifications on send)
                └── Blocks: INV-023 (Overdue Command - overdue notifications)
```

**Blocking Relationships Summary**:

| Foundation Task | Invoicing Tasks Blocked | Required By Sprint |
|----------------|------------------------|-------------------|
| FOUND-007 | INV-012 (Permissions) | Sprint 2 |
| FOUND-001 through FOUND-004 | INV-022 (Email notifications), INV-023 (Overdue notifications) | Sprint 4 |
| FOUND-005 | INV-022, INV-023 (preference-aware notifications) | Sprint 4 |
| FOUND-006 | None (not needed for invoicing) | N/A |

**Minimum Required Before Sprint 1**: FOUND-007 must be complete before Sprint 2 starts. FOUND-001 through FOUND-005 must be complete before Sprint 4 starts. This gives 4 weeks of buffer.

### 3.2 Intra-Feature Dependency Graph

```
Phase 1 (Sprint 1): No dependencies -- can run in parallel
├── INV-001 (Core Tables)           ──┐
├── INV-002 (Client Billing Cols)   ──┤
└── INV-003 (Org Settings Cols)     ──┘
                                      │
Phase 2 (Sprint 1, after Phase 1):    │
├── INV-004 (TimeEntry FK)      ◄─── INV-001
├── INV-005 (Models + Enums)    ◄─── INV-001
├── INV-007 (Client Billing API) ◄── INV-002
└── INV-013* (Number Generator)       (no deps, moved from S2 per AMD-08)
                                      │
Phase 3 (Sprint 1, after Phase 2):    │
├── INV-006 (Factories)         ◄─── INV-005
├── INV-008 (Client Billing UI) ◄─── INV-007
└── INV-014 (Settings API)      ◄─── INV-003, INV-005
                                      │
Phase 4 (Sprint 2):                   │
├── INV-009 (InvoiceService)    ◄─── INV-005, INV-006  ** CRITICAL PATH **
└── INV-012 (Permissions)       ◄─── FOUND-007
                                      │
Phase 5 (Sprint 2, after Phase 4):    │
├── INV-010 (Controller+Routes) ◄─── INV-009, INV-012  ** CRITICAL PATH **
├── INV-013 (PDF Service)       ◄─── INV-005, INV-009
└── INV-023 (Overdue Command)   ◄─── INV-009
                                      │
Phase 6 (Sprint 2, after Phase 5):    │
└── INV-015 (OpenAPI + TS)      ◄─── INV-010           ** CRITICAL PATH **
                                      │
Phase 7 (Sprint 3):                   │
├── INV-016 (Pinia Store)       ◄─── INV-015           ** CRITICAL PATH **
├── INV-024 (Payment Controller) ◄── INV-009, INV-010
├── INV-022 (Email Service)     ◄─── INV-013, FOUND-001..005
└── INV-029 (Accounting Export) ◄─── INV-010
                                      │
Phase 8 (Sprint 3, after Phase 7):    │
├── INV-017 (List Page)         ◄─── INV-016
├── INV-018 (Detail Page)       ◄─── INV-016           ** CRITICAL PATH **
├── INV-020 (PDF Preview)       ◄─── INV-013, INV-016
├── INV-021 (Settings UI)       ◄─── INV-014
├── INV-025 (Recurring Service) ◄─── INV-005, INV-009
├── INV-027 (Stripe)            ◄─── INV-024
└── INV-030 (Export UI)         ◄─── INV-029
                                      │
Phase 9 (Sprint 3-4, after Phase 8):  │
├── INV-019 (Creation Wizard)   ◄─── INV-016, INV-018  ** CRITICAL PATH **
├── INV-026 (Recurring UI)      ◄─── INV-025, INV-016
├── INV-028 (Stripe UI)         ◄─── INV-027
├── INV-031 (Service Tests)     ◄─── INV-009
├── INV-033 (Recurring Tests)   ◄─── INV-025, INV-024
└── INV-036 (Web Routes)        ◄─── INV-017
                                      │
Phase 10 (Sprint 5-7, after Phase 9): │
├── INV-032 (Endpoint Tests)    ◄─── INV-010, INV-012
├── INV-034 (Component Tests)   ◄─── INV-017, INV-018, INV-019
└── INV-035 (E2E Tests)         ◄─── INV-017, INV-018, INV-019  ** CRITICAL PATH **
```

### 3.3 Critical Path

The longest dependent chain determines the minimum possible delivery timeline:

```
INV-001 (8h) -> INV-005 (8h) -> INV-009 (16h) -> INV-010 (14h) -> INV-015 (4h) -> INV-016 (8h) -> INV-018 (16h) -> INV-019 (16h) -> INV-035 (12h)

Total: 102 hours = ~12.75 working days
```

### 3.4 External Feature Dependencies

| Dependency | Type | Impact | Mitigation |
|-----------|------|--------|------------|
| Expense Management (Feature 02) | Soft | Invoice line items could include expenses | Design `InvoiceLine.type = 'expense'` now; actual expense linking deferred to V2 |
| Budgets & Alerts (Feature 03) | None | No direct dependency | N/A |
| Teams & Groups (Feature 10) | Soft | Team-scoped invoice access possible | No team scoping in V1; add later via `TeamScopeService` |

---

## 4. Sprint Details

---

### Sprint 0: Shared Foundations (Pre-Sprint, ~1 Week)

**Sprint Goal**: Establish cross-feature infrastructure that the Invoicing System (and other features) depend on.

**Note**: This sprint is shared across all features. The invoicing team does not own these tasks but is blocked by them. Coordinate with the platform team.

#### Tasks

| Task ID | Description | Effort | SP | Dependencies | Assignee Role |
|---------|-------------|--------|:--:|:-------------|:--------------|
| FOUND-001 | Notification infrastructure migration (`notifications` table + `notification_preferences` on members) | 2h | 1 | None | Backend |
| FOUND-002 | Base notification classes (`BaseNotification` with database + mail channels) | 4h | 2 | FOUND-001 | Backend |
| FOUND-003 | Notification bell UI component (`NotificationBell.vue` in AppLayout header) | 8h | 4 | FOUND-002, FOUND-004 | Frontend |
| FOUND-004 | Notification API endpoints (list, mark read, unread count) | 6h | 3 | FOUND-002 | Backend |
| FOUND-005 | Notification preferences in organization settings | 4h | 2 | FOUND-004 | Frontend |
| FOUND-007 | Modular permissions infrastructure (`app/Permissions/` directory pattern) | 4h | 2 | None | Backend |

**Sprint 0 Total**: 28h, 14 SP (subset relevant to invoicing; FOUND-006 not needed)

#### Acceptance Criteria
- [ ] `notifications` table exists and migrations pass
- [ ] `BaseNotification` class sends to database and mail channels
- [ ] Notification bell renders in AppLayout, shows unread count, polls every 60s
- [ ] Notification API returns paginated notifications and supports mark-as-read
- [ ] `app/Permissions/` directory created with modular registration pattern
- [ ] `JetstreamServiceProvider` calls modular permission registrations

#### Deliverables
- `database/migrations/2026_02_28_*` -- shared foundation migrations
- `app/Notifications/BaseNotification.php`
- `resources/js/packages/ui/src/Notification/NotificationBell.vue`
- `app/Http/Controllers/Api/V1/NotificationController.php`
- `app/Permissions/` directory structure
- Modified `app/Providers/JetstreamServiceProvider.php`

#### Risk Factors
- If the platform team is delayed on FOUND tasks, invoicing Sprint 2 (permissions) and Sprint 4 (notifications) are blocked
- Mitigation: FOUND-007 is only 4h and can be done by the invoicing backend dev if needed

---

### Sprint 1: Database & Models (Weeks 1-2)

**Sprint Goal**: Establish the complete database schema, Eloquent models, and client billing fields so that backend services can be built on a stable data layer.

#### Tasks

| Task ID | Description | Effort | SP | Dependencies | Assignee Role |
|---------|-------------|--------|:--:|:-------------|:--------------|
| INV-001 | Database migrations -- Core invoice tables (invoices, invoice_lines, invoice_payments, invoice_templates, recurring_invoice_schedules) | 8h | 3 | None | Backend |
| INV-002 | Database migration -- Extend clients table with billing columns | 4h | 2 | None | Backend |
| INV-003 | Database migration -- Extend organizations table with invoice settings | 4h | 2 | None | Backend |
| INV-004 | Database migration -- Add `invoice_id` FK to `time_entries` table | 2h | 1 | INV-001 | Backend |
| INV-005 | Eloquent models (Invoice, InvoiceLine, InvoicePayment, InvoiceTemplate, RecurringInvoiceSchedule) + Enums (InvoiceStatus, InvoiceLineType, PaymentMethod, RecurringFrequency) | 8h | 3 | INV-001 | Backend |
| INV-006 | Model factories and seeders for all new invoice models | 6h | 2 | INV-005 | Backend |
| INV-007 | Client billing fields -- Backend API (extend ClientController, request validation, resource) | 4h | 2 | INV-002 | Backend |
| INV-008 | Client billing fields -- Frontend UI (ClientBillingForm.vue, country dropdown, email validation) | 6h | 2 | INV-007 | Frontend |
| INV-013* | InvoiceNumberGenerator service (extracted from INV-009 scope; moved from Sprint 2 per AMD-08) | 4h | 2 | None | Backend |

**Sprint 1 Total**: 46h, 19 SP
**Backend**: 40h | **Frontend**: 6h

> *INV-013 in this context refers to the invoice number generation logic that was recommended to move to Sprint 1 per AMD-08. The task ID in the task assignments file is referenced as the InvoiceNumberGenerator portion of INV-009 work, allocated here early since it has no dependencies. The PDF-related INV-013 (Invoice PDF Service) remains in Sprint 2.

**Correction Note**: To avoid task ID confusion with the task assignments document where INV-013 refers to the PDF Service, we will note this as "INV-009a" (number generator extracted as early work). The formal INV-013 (PDF Service, 12h) moves to Sprint 2 as per the original assignments. The 4h effort for the number generator is part of the INV-009 16h budget.

**Revised Sprint 1 Task Table** (clarified):

| Task ID | Description | Effort | SP | Dependencies | Assignee Role |
|---------|-------------|--------|:--:|:-------------|:--------------|
| INV-001 | Database migrations -- Core invoice tables | 8h | 3 | None | Backend |
| INV-002 | Database migration -- Extend clients table | 4h | 2 | None | Backend |
| INV-003 | Database migration -- Extend organizations table | 4h | 2 | None | Backend |
| INV-004 | Database migration -- Add `invoice_id` FK to time_entries | 2h | 1 | INV-001 | Backend |
| INV-005 | Eloquent models + Enums | 8h | 3 | INV-001 | Backend |
| INV-006 | Model factories and seeders | 6h | 2 | INV-005 | Backend |
| INV-007 | Client billing fields -- Backend API | 4h | 2 | INV-002 | Backend |
| INV-008 | Client billing fields -- Frontend UI | 6h | 2 | INV-007 | Frontend |

**Sprint 1 Total**: 42h, 17 SP
**Backend**: 36h | **Frontend**: 6h

#### Execution Order (Within Sprint)

```
Day 1-2:  INV-001 + INV-002 + INV-003 (parallel, no deps)       [16h total]
Day 3:    INV-004 (needs INV-001) + INV-007 (needs INV-002)      [6h total]
Day 3-4:  INV-005 (needs INV-001)                                 [8h]
Day 5-6:  INV-006 (needs INV-005) + INV-008 (needs INV-007)      [12h total]
```

#### Acceptance Criteria
- [ ] All 8 migrations run successfully on PostgreSQL (`php artisan migrate`)
- [ ] All migrations roll back cleanly (`php artisan migrate:rollback --step=8`)
- [ ] Foreign key constraints are correct (cascade/restrict/nullOnDelete per spec)
- [ ] All 5 Eloquent models have proper relationships, casts, and traits (`CustomAuditable`, `HasFactory`, `HasUuids`)
- [ ] All 4 enums created (InvoiceStatus, InvoiceLineType, PaymentMethod, RecurringFrequency)
- [ ] Model factories produce valid instances in all defined states (draft, sent, paid, overdue, void)
- [ ] Client create/update API accepts and returns billing fields
- [ ] Client billing form renders with country dropdown and email validation
- [ ] `composer fix && composer analyse` passes
- [ ] Existing test suite passes (no regressions)

#### Deliverables

**Migrations** (8 files):
- `database/migrations/2026_03_04_000001_create_invoices_table.php`
- `database/migrations/2026_03_04_000002_create_invoice_lines_table.php`
- `database/migrations/2026_03_04_000003_create_invoice_payments_table.php`
- `database/migrations/2026_03_04_000004_create_invoice_templates_table.php`
- `database/migrations/2026_03_04_000005_create_recurring_invoice_schedules_table.php`
- `database/migrations/2026_03_04_000006_add_billing_columns_to_clients_table.php`
- `database/migrations/2026_03_04_000007_add_invoice_settings_to_organizations_table.php`
- `database/migrations/2026_03_04_000008_add_invoice_id_to_time_entries_table.php`

**Models** (5 files):
- `app/Models/Invoice.php`
- `app/Models/InvoiceLine.php`
- `app/Models/InvoicePayment.php`
- `app/Models/InvoiceTemplate.php`
- `app/Models/RecurringInvoiceSchedule.php`

**Enums** (4 files):
- `app/Enums/InvoiceStatus.php`
- `app/Enums/InvoiceLineType.php`
- `app/Enums/PaymentMethod.php`
- `app/Enums/RecurringFrequency.php`

**Factories** (5 files):
- `database/factories/InvoiceFactory.php`
- `database/factories/InvoiceLineFactory.php`
- `database/factories/InvoicePaymentFactory.php`
- `database/factories/InvoiceTemplateFactory.php`
- `database/factories/RecurringInvoiceScheduleFactory.php`

**Modified Files**:
- `app/Models/Client.php` (add casts for billing columns)
- `app/Http/Requests/V1/Client/ClientStoreRequest.php` (billing field validation)
- `app/Http/Requests/V1/Client/ClientUpdateRequest.php` (billing field validation)
- `app/Http/Resources/V1/Client/ClientResource.php` (include billing fields)

**Frontend**:
- `resources/js/packages/ui/src/Client/ClientBillingForm.vue`

#### Risk Factors
- **Migration ordering**: All 8 migrations use `2026_03_04_` prefix; ensure sequential numbering (`000001` through `000008`) is respected
- **Existing client test regressions**: Adding columns to clients table may require updating ClientFactory; run existing client endpoint tests early
- **Large migration set**: If any migration fails, debug before proceeding -- downstream sprints depend on schema stability

---

### Sprint 2: Core Invoice Backend (Weeks 3-4)

**Sprint Goal**: Build the complete backend API for invoice CRUD, PDF generation, and permissions so that the frontend team can begin building against real endpoints.

#### Tasks

| Task ID | Description | Effort | SP | Dependencies | Assignee Role |
|---------|-------------|--------|:--:|:-------------|:--------------|
| INV-009 | InvoiceService -- Core business logic (number generation, preview, create, status transitions, totals, snapshots) | 16h | 5 | INV-005, INV-006 | Backend |
| INV-012 | Invoice permissions registration (Jetstream roles + frontend permission helpers) | 4h | 2 | FOUND-007 | Backend |
| INV-010 | InvoiceController + API routes (index, show, preview, store, update, send, void, destroy, pdf) -- merged with INV-011 per AMD-06 | 14h | 5 | INV-009, INV-012 | Backend |
| INV-014 | Invoice settings API (extend OrganizationController for number format, tax, billing info) | 6h | 2 | INV-003, INV-005 | Backend |
| INV-015 | OpenAPI spec update and TypeScript client regeneration | 4h | 2 | INV-010 | Backend/Frontend |
| INV-013 | Invoice PDF Service (Gotenberg rendering with Blade templates) | 12h | 5 | INV-005, INV-009 | Backend |

**Sprint 2 Total**: 56h, 21 SP
**Backend**: 52h | **Frontend**: 4h (OpenAPI regen)

**Note on AMD-08**: The original task assignments had 56h backend in Sprint 2. Per AMD-08, the InvoiceNumberGenerator (4h) was recommended to move to Sprint 1, and INV-015 (PDF Service, originally listed as INV-015 in the assignments doc at 8h but actually INV-013 at 12h) was recommended to start in Sprint 3. However, since the backend developer has the capacity in Sprint 2 after the number generator extraction, and the PDF service is on the critical path for Sprint 3 frontend work, we keep INV-013 in Sprint 2 to avoid blocking the frontend.

#### Execution Order (Within Sprint)

```
Day 1:      INV-012 (Permissions, no deps except FOUND-007)        [4h]
Day 1-2:    INV-014 (Settings API, needs INV-003, INV-005)         [6h]
Day 1-5:    INV-009 (InvoiceService -- CRITICAL PATH)              [16h]
Day 5-8:    INV-010 (Controller+Routes, needs INV-009, INV-012)    [14h]
Day 6-9:    INV-013 (PDF Service, needs INV-005, INV-009)          [12h] (parallel with INV-010)
Day 9-10:   INV-015 (OpenAPI regen, needs INV-010)                 [4h]
```

#### Acceptance Criteria
- [ ] `InvoiceService.generateInvoiceNumber()` uses DB lock for atomicity, format is `{prefix}{padded_number}{suffix}`
- [ ] `InvoiceService.previewFromTimeEntries()` correctly groups by project/task/member/date, applies rounding
- [ ] `InvoiceService.createInvoice()` creates invoice + line items in a transaction, links time entries, captures snapshots
- [ ] `InvoiceService.sendInvoice()` validates draft status, transitions to sent
- [ ] `InvoiceService.voidInvoice()` unlinks time entries (sets `invoice_id = null`)
- [ ] All API endpoints respond correctly (200/201/204/422 as specified in PRD Section 4.3)
- [ ] Permission checks enforce the access control matrix (Owner/Admin: all; Manager: view/create/update/delete; Employee: none)
- [ ] Write endpoints have `check-organization-blocked` middleware
- [ ] Route names follow `api.v1.invoices.{action}` convention
- [ ] PDF renders with organization logo, client info, line items, totals, notes
- [ ] PDF debug mode returns raw HTML
- [ ] Invoice settings readable/writable via organization API
- [ ] TypeScript API client regenerated with all invoice types and methods
- [ ] `composer fix && composer analyse` passes

#### Deliverables

**Services** (3 files):
- `app/Service/InvoiceService.php`
- `app/Service/InvoicePdfService.php`
- `app/Service/Dto/InvoicePreviewDto.php` + `InvoiceLineItemDto.php`

**Controllers** (1 file):
- `app/Http/Controllers/Api/V1/InvoiceController.php`

**Requests** (6 files):
- `app/Http/Requests/V1/Invoice/InvoiceIndexRequest.php`
- `app/Http/Requests/V1/Invoice/InvoiceStoreRequest.php`
- `app/Http/Requests/V1/Invoice/InvoiceUpdateRequest.php`
- `app/Http/Requests/V1/Invoice/InvoicePreviewRequest.php`
- `app/Http/Requests/V1/Invoice/InvoiceSendRequest.php`
- `app/Http/Requests/V1/Invoice/InvoiceVoidRequest.php`

**Resources** (5 files):
- `app/Http/Resources/V1/Invoice/InvoiceResource.php`
- `app/Http/Resources/V1/Invoice/InvoiceDetailedResource.php`
- `app/Http/Resources/V1/Invoice/InvoiceCollection.php`
- `app/Http/Resources/V1/Invoice/InvoiceLineResource.php`
- `app/Http/Resources/V1/Invoice/InvoicePreviewResource.php`

**Permissions** (1 file):
- `app/Permissions/InvoicePermissions.php`

**Views** (2 files):
- `resources/views/invoices/pdf.blade.php`
- `resources/views/invoices/pdf-footer.blade.php`

**Modified Files**:
- `routes/api.php` (invoice routes)
- `app/Providers/JetstreamServiceProvider.php` (call `InvoicePermissions::register()`)
- `app/Models/Organization.php` (add casts for invoice settings columns)
- `app/Http/Requests/V1/Organization/OrganizationUpdateRequest.php` (add invoice settings validation)
- `app/Http/Resources/V1/Organization/OrganizationResource.php` (include invoice settings)
- `resources/js/utils/permissions.ts` (add invoice permission helpers)

**Generated**:
- `resources/js/packages/api/src` (regenerated TypeScript client)

#### Risk Factors
- **INV-009 is the largest single task (16h)**: If it slips, all downstream tasks cascade. Mitigation: begin INV-009 on Day 1; consider pair programming for the number generation and snapshot logic
- **FOUND-007 dependency for INV-012**: If the modular permissions infrastructure is not ready, INV-012 can be done by directly modifying `JetstreamServiceProvider` as a temporary measure and refactored later
- **Gotenberg availability**: PDF service tests require Gotenberg running in Docker. Ensure `docker-compose.yml` has Gotenberg configured (it already does based on existing export infrastructure)

---

### Sprint 3: Invoice Frontend (Weeks 5-6)

**Sprint Goal**: Build the complete frontend invoice experience -- list page, detail/edit page, creation wizard, and PDF preview -- so that end-to-end invoice workflows are usable.

#### Tasks

| Task ID | Description | Effort | SP | Dependencies | Assignee Role |
|---------|-------------|--------|:--:|:-------------|:--------------|
| INV-016 | Invoice Pinia store (`useInvoices.ts` with CRUD, send, void, preview, payment recording) | 8h | 3 | INV-015 | Frontend |
| INV-017 | Invoices list page (table, status badges, filters, pagination) | 12h | 5 | INV-016 | Frontend |
| INV-018 | Invoice detail/edit page (header, line items, summary, actions, payment history) | 16h | 5 | INV-016 | Frontend |
| INV-019 | Invoice creation wizard (multi-step: client select, time filter, grouping, preview, create) | 16h | 5 | INV-016, INV-018 | Frontend |
| INV-020 | Invoice PDF preview (frontend PDF preview/download via Gotenberg) | 4h | 2 | INV-013, INV-016 | Frontend |

**Sprint 3 Total**: 56h, 20 SP
**Frontend**: 56h | **Backend**: 0h (backend dev works on Sprint 4 backend tasks early or assists)

#### Execution Order (Within Sprint)

```
Day 1-2:    INV-016 (Pinia Store -- CRITICAL PATH)                [8h]
Day 3-4:    INV-017 (List Page, needs INV-016)                    [12h]
Day 3-6:    INV-018 (Detail Page -- CRITICAL PATH, needs INV-016) [16h]
Day 5:      INV-020 (PDF Preview, needs INV-013, INV-016)         [4h]
Day 7-10:   INV-019 (Creation Wizard -- CRITICAL PATH)            [16h]
```

**Note**: INV-017 and INV-018 can start in parallel once INV-016 is complete. INV-019 depends on INV-018 (reuses line item components) but can begin scaffolding in parallel once INV-018's component structure is established. Per AMD-07, INV-018 and INV-019 may be underestimated. The frontend developer should flag early if more time is needed.

#### Acceptance Criteria
- [ ] Invoice Pinia store follows existing pattern (`useClients.ts`) with `handleApiRequestNotifications` wrapper
- [ ] Invoice list page renders at `/invoices` with table, status badges (color-coded), filters (status, client, date range), pagination
- [ ] "New Invoice" button gated by `invoices:create` permission
- [ ] Invoice detail page shows complete invoice info with editable fields in draft mode
- [ ] Line items are add/remove/reorder-able in draft mode with real-time total recalculation
- [ ] Action buttons (Edit, Preview PDF, Send, Record Payment, Void, Delete) respect status and permissions
- [ ] Payment history section visible for sent/paid invoices
- [ ] Creation wizard navigates through all 5 steps with back/forward state preservation
- [ ] Time entry selector shows only unbilled, billable, completed entries for selected client
- [ ] Grouping options (project/task/member/date) update preview dynamically
- [ ] Manual line items can be added alongside time-based items
- [ ] PDF opens in new tab via temporary signed URL; download button works
- [ ] `npm run lint:fix && npm run format` passes

#### Deliverables

**Pinia Store** (1 file):
- `resources/js/utils/useInvoices.ts`

**Pages** (3 files):
- `resources/js/Pages/Invoices.vue`
- `resources/js/Pages/InvoiceShow.vue`
- `resources/js/Pages/InvoiceCreate.vue`

**UI Components** (14 files):
- `resources/js/packages/ui/src/Invoice/InvoiceTable.vue`
- `resources/js/packages/ui/src/Invoice/InvoiceStatusBadge.vue`
- `resources/js/packages/ui/src/Invoice/InvoiceFilters.vue`
- `resources/js/packages/ui/src/Invoice/InvoiceHeader.vue`
- `resources/js/packages/ui/src/Invoice/InvoiceLineItems.vue`
- `resources/js/packages/ui/src/Invoice/InvoiceLineItemRow.vue`
- `resources/js/packages/ui/src/Invoice/InvoiceSummary.vue`
- `resources/js/packages/ui/src/Invoice/InvoiceActions.vue`
- `resources/js/packages/ui/src/Invoice/InvoicePaymentHistory.vue`
- `resources/js/packages/ui/src/Invoice/InvoiceCreateWizard.vue`
- `resources/js/packages/ui/src/Invoice/InvoiceClientSelector.vue`
- `resources/js/packages/ui/src/Invoice/InvoiceTimeEntrySelector.vue`
- `resources/js/packages/ui/src/Invoice/InvoicePreview.vue`
- `resources/js/packages/ui/src/Invoice/InvoiceManualLineItem.vue`

#### Risk Factors
- **INV-018 and INV-019 estimated at 16h each (AMD-07 warning)**: These are the most complex frontend tasks. If they exceed estimates, Sprint 3 will overflow. Mitigation: split INV-018 into detail view (8h) and edit mode (10h); split INV-019 into steps 1-2 (8h) and steps 3-4 (10h). Escalate by Day 5 if behind
- **Backend dev idle in Sprint 3**: The backend developer should pull Sprint 4 backend tasks (INV-022, INV-023, INV-024) forward to maximize throughput
- **Component reuse from INV-018 in INV-019**: The wizard reuses line item components from the detail page. If INV-018 component structure changes late, INV-019 is impacted. Mitigation: agree on component interfaces before Day 3

---

### Sprint 4: Email, Payments & Settings UI (Weeks 7-8)

**Sprint Goal**: Complete the invoice operational features -- email sending, overdue detection, manual payment recording, and settings UI -- and begin backend test coverage.

#### Tasks

| Task ID | Description | Effort | SP | Dependencies | Assignee Role |
|---------|-------------|--------|:--:|:-------------|:--------------|
| INV-021 | Invoice settings UI (organization settings panel for number format, tax, billing, logo upload) | 8h | 3 | INV-014 | Frontend |
| INV-022 | Invoice email service (Laravel Mailable with PDF attachment, send action) | 8h | 3 | INV-013, FOUND-001..005 | Backend |
| INV-023 | Overdue invoice detection command (artisan command + scheduler registration) | 4h | 2 | INV-009, FOUND-001..005 | Backend |
| INV-024 | Invoice payment controller (record payments, list payments, status transitions) | 6h | 2 | INV-009, INV-010 | Backend |
| INV-031 | InvoiceService unit tests (number gen, creation, totals, state transitions, void) | 12h | 5 | INV-009 | Backend |

**Sprint 4 Total**: 38h, 15 SP
**Backend**: 30h | **Frontend**: 8h

#### Execution Order (Within Sprint)

```
Day 1-2:    INV-024 (Payment Controller, needs INV-009, INV-010)      [6h]
Day 1-2:    INV-023 (Overdue Command, needs INV-009, FOUND-*)         [4h]
Day 1-2:    INV-021 (Settings UI, needs INV-014)                      [8h] (Frontend)
Day 3-5:    INV-022 (Email Service, needs INV-013, FOUND-*)           [8h]
Day 3-8:    INV-031 (InvoiceService Tests)                            [12h]
```

#### Acceptance Criteria
- [ ] Settings UI visible in organization settings for users with `invoices:settings` permission
- [ ] Invoice number preview updates live as user changes prefix/suffix/next number
- [ ] Logo upload and preview functional
- [ ] All settings persist correctly via API
- [ ] Email sends with correct from/to addresses, PDF attachment, customizable message
- [ ] Email is queue-able via Laravel jobs
- [ ] Overdue command transitions `sent` invoices past `due_date` to `overdue` status
- [ ] Overdue command registered in `Kernel.php` with config gate
- [ ] Payment recording: full payment marks invoice `paid`; partial marks `partial`
- [ ] Payment validation prevents amount exceeding `amount_due_cents`
- [ ] Payment API returns `InvoicePayment` resource
- [ ] InvoiceService unit tests cover: concurrent number generation, preview grouping, rounding, snapshot capture, time entry linking, total recalculation, all state transitions
- [ ] Test coverage for InvoiceService > 90%

#### Deliverables

**Backend**:
- `app/Mail/InvoiceMail.php`
- `app/Service/InvoiceEmailService.php`
- `resources/views/emails/invoice.blade.php`
- `app/Console/Commands/MarkOverdueInvoicesCommand.php`
- `app/Http/Controllers/Api/V1/InvoicePaymentController.php`
- `app/Http/Requests/V1/Invoice/InvoicePaymentStoreRequest.php`
- `app/Http/Resources/V1/Invoice/InvoicePaymentResource.php`
- `app/Http/Resources/V1/Invoice/InvoicePaymentCollection.php`
- `tests/Unit/Service/InvoiceServiceTest.php`

**Modified**:
- `app/Console/Kernel.php` (schedule overdue command)
- `routes/api.php` (payment routes)

**Frontend**:
- `resources/js/Pages/Teams/Partials/InvoiceSettings.vue`
- `resources/js/packages/ui/src/Invoice/InvoiceNumberPreview.vue`
- `resources/js/packages/ui/src/Invoice/RecordPaymentModal.vue`

**Modified**:
- `resources/js/Pages/Teams/Show.vue` (include InvoiceSettings section)

#### Risk Factors
- **FOUND-001..005 dependency for INV-022 and INV-023**: If notification infrastructure is not complete, invoice email and overdue notifications will not work. Mitigation: implement email sending without notifications first (email works independently), add notification dispatch after FOUND tasks complete
- **INV-031 test scope (12h)**: Comprehensive service tests may reveal bugs in INV-009. Budget 2-4h for bug fixes alongside test writing

---

### Sprint 5: Recurring Invoices & Endpoint Tests (Weeks 9-10)

**Sprint Goal**: Implement recurring invoice scheduling (P1 priority) and build comprehensive API endpoint test coverage.

#### Tasks

| Task ID | Description | Effort | SP | Dependencies | Assignee Role |
|---------|-------------|--------|:--:|:-------------|:--------------|
| INV-025 | Recurring invoice schedule service (service logic, scheduler command, CRUD controller) | 12h | 5 | INV-005, INV-009 | Backend |
| INV-026 | Recurring invoices frontend (schedule list, create/edit form, toggle active/pause, Pinia store) | 10h | 3 | INV-025, INV-016 | Frontend |
| INV-032 | Backend endpoint tests -- InvoiceController + InvoicePaymentController (permissions, CRUD, validation) | 16h | 5 | INV-010, INV-012 | Backend |

**Sprint 5 Total**: 38h, 13 SP
**Backend**: 28h | **Frontend**: 10h

#### Execution Order (Within Sprint)

```
Day 1-4:    INV-025 (Recurring Service + Controller + Command)        [12h]
Day 1-8:    INV-032 (Endpoint Tests, parallel with INV-025)           [16h]
Day 5-8:    INV-026 (Recurring UI, needs INV-025)                     [10h]
```

#### Acceptance Criteria
- [ ] Recurring schedule CRUD API endpoints work (list, create, update, delete, toggle)
- [ ] Scheduler command queries active schedules where `next_run_at <= now()`, generates draft invoices
- [ ] Generated invoices pull billable time from period if `include_billable_time = true`
- [ ] Fixed line items included when specified
- [ ] `next_run_at` correctly calculated based on frequency (weekly/biweekly/monthly/quarterly/annually)
- [ ] Schedule can be paused/resumed via toggle endpoint
- [ ] `last_run_at` updated after each generation
- [ ] Command registered in `Kernel.php` with config gate, runs daily
- [ ] Recurring schedule UI: list with active/paused status, create/edit form, toggle button
- [ ] Endpoint tests cover: permission enforcement (all 4 roles), CRUD validation, status constraint enforcement, filter query parameters, PDF endpoint, payment recording, error scenarios (422s)
- [ ] Endpoint test coverage for InvoiceController > 85%

#### Deliverables

**Backend**:
- `app/Service/RecurringInvoiceService.php`
- `app/Http/Controllers/Api/V1/RecurringInvoiceScheduleController.php`
- `app/Http/Requests/V1/Invoice/RecurringScheduleStoreRequest.php`
- `app/Http/Requests/V1/Invoice/RecurringScheduleUpdateRequest.php`
- `app/Http/Resources/V1/Invoice/RecurringScheduleResource.php`
- `app/Http/Resources/V1/Invoice/RecurringScheduleCollection.php`
- `app/Console/Commands/GenerateRecurringInvoicesCommand.php`
- `tests/Unit/Endpoint/Api/V1/InvoiceEndpointTest.php`
- `tests/Unit/Endpoint/Api/V1/InvoicePaymentEndpointTest.php`

**Modified**:
- `routes/api.php` (recurring schedule routes)
- `app/Console/Kernel.php` (schedule recurring command)

**Frontend**:
- `resources/js/utils/useRecurringInvoiceSchedules.ts`
- `resources/js/packages/ui/src/Invoice/RecurringScheduleList.vue`
- `resources/js/packages/ui/src/Invoice/RecurringScheduleForm.vue`

#### Risk Factors
- **Recurring invoice "thundering herd"**: If many organizations schedule invoices at midnight UTC, concurrent generation can overwhelm the system. Mitigation: use Laravel queue (`dispatch(new GenerateRecurringInvoiceJob($schedule))`) with staggered execution using org ID hash
- **Timezone handling**: Recurring schedules must run in the organization's timezone context, not UTC. Ensure the scheduler command converts timezone correctly
- **INV-032 scope (16h)**: This is the largest test suite. May reveal API bugs that need Sprint 2 task fixes. Budget 2-4h for regression fixes

---

### Sprint 6: Stripe, Export & Integration Tests (Weeks 11-12)

**Sprint Goal**: Implement P2 features (online payments via Stripe, accounting exports) and complete backend test coverage for recurring invoices and payments.

#### Tasks

| Task ID | Description | Effort | SP | Dependencies | Assignee Role |
|---------|-------------|--------|:--:|:-------------|:--------------|
| INV-027 | Stripe payment integration (Checkout session creation, webhook handler, payment recording) | 16h | 5 | INV-024 | Backend |
| INV-028 | Stripe payment frontend (payment settings in org config, payment link UI on invoices) | 6h | 2 | INV-027 | Frontend |
| INV-029 | Accounting export service (generic CSV, QuickBooks IIF, Xero CSV) | 10h | 3 | INV-010 | Backend |
| INV-030 | Accounting export frontend (export button, format selector, date range dialog) | 4h | 2 | INV-029 | Frontend |
| INV-033 | Backend tests -- RecurringInvoiceService + payment recording tests | 8h | 3 | INV-025, INV-024 | Backend |

**Sprint 6 Total**: 44h, 15 SP
**Backend**: 34h | **Frontend**: 10h

#### Execution Order (Within Sprint)

```
Day 1-5:    INV-027 (Stripe Integration)                              [16h]
Day 1-4:    INV-029 (Accounting Export, parallel)                     [10h]
Day 5-6:    INV-028 (Stripe UI, needs INV-027)                       [6h]
Day 5-6:    INV-030 (Export UI, needs INV-029)                        [4h]
Day 7-8:    INV-033 (Recurring + Payment Tests)                       [8h]
```

#### Acceptance Criteria
- [ ] Stripe Checkout session created for invoice payment with correct amount/currency
- [ ] Webhook handler processes `checkout.session.completed` event, records `InvoicePayment`, updates invoice status
- [ ] Idempotency key prevents duplicate payment recording
- [ ] Stripe settings configurable in organization settings (API key, webhook secret)
- [ ] "Pay Now" link visible on sent invoices (when Stripe configured)
- [ ] Gated by `BillingContract::hasSubscription()` (premium feature)
- [ ] CSV export includes invoice header, line items, tax breakdown, payments
- [ ] QuickBooks IIF format correct (validated against IIF spec)
- [ ] Xero CSV format correct (validated against Xero import spec)
- [ ] Export downloads via temporary signed URL
- [ ] Export UI: format dropdown, date range filter, download button
- [ ] RecurringInvoiceService tests cover: schedule execution, time entry pulling, fixed items, `next_run_at` calculation, skip logic
- [ ] Payment tests cover: full payment, partial payment, overpayment rejection, status transitions

#### Deliverables

**Backend**:
- `app/Service/StripePaymentService.php` (or integrated into `InvoicePaymentService`)
- `app/Http/Controllers/Api/V1/StripeWebhookController.php`
- `app/Service/Export/InvoiceExportService.php`
- `app/Http/Controllers/Api/V1/InvoiceExportController.php`
- `tests/Unit/Service/RecurringInvoiceServiceTest.php`
- `tests/Unit/Service/InvoicePaymentServiceTest.php`

**Modified**:
- `routes/api.php` (Stripe webhook route, export routes)
- `config/services.php` (Stripe configuration)

**Frontend**:
- Stripe settings section in organization settings
- Payment link display on invoice detail
- Export button/dialog in invoice list page

#### Risk Factors
- **Stripe account approval**: Stripe Connect setup may require account verification that takes days. Mitigation: use Stripe test mode throughout development; production keys can be added later
- **Webhook endpoint security**: Must verify Stripe webhook signatures. Use `stripe/stripe-php` SDK's built-in verification
- **IIF/Xero format correctness**: These export formats have strict specifications. Mitigation: find sample IIF/Xero files online and write tests that compare output format

---

### Sprint 7: E2E Testing & Polish (Weeks 13-14)

**Sprint Goal**: Achieve comprehensive test coverage through frontend component tests and end-to-end Playwright tests. Fix bugs, polish UI, register web routes, and ensure production readiness.

#### Tasks

| Task ID | Description | Effort | SP | Dependencies | Assignee Role |
|---------|-------------|--------|:--:|:-------------|:--------------|
| INV-034 | Frontend component tests -- Vitest tests for InvoiceTable, StatusBadge, LineItems, Wizard | 10h | 3 | INV-017, INV-018, INV-019 | Frontend |
| INV-035 | E2E tests -- Playwright tests for invoice CRUD, wizard, payment, send workflows | 12h | 5 | INV-017, INV-018, INV-019 | QA |
| INV-036 | Register Inertia web routes for invoice pages (AMD-10) | 1h | 1 | INV-017 | Backend |

**Sprint 7 Total**: 23h, 9 SP
**Frontend**: 10h | **QA**: 12h | **Backend**: 1h

**Remaining Sprint 7 Capacity (~37h)**: Allocated to bug fixes, UI polish, documentation, and performance optimization discovered during E2E testing.

#### Execution Order (Within Sprint)

```
Day 1:      INV-036 (Web Routes, quick task)                         [1h]
Day 1-4:    INV-034 (Frontend Component Tests)                       [10h]
Day 1-5:    INV-035 (E2E Playwright Tests)                           [12h]
Day 5-10:   Bug fixes, polish, documentation                         [~37h capacity]
```

#### Acceptance Criteria
- [ ] Web routes registered: `/invoices`, `/invoices/{invoice}`, `/invoices/create`
- [ ] Navigation sidebar "Invoices" link uses `route('invoices')` instead of hardcoded `/invoices`
- [ ] Vitest component tests cover: InvoiceTable rendering, status badge colors, line item CRUD, wizard step navigation, PDF preview button
- [ ] E2E tests cover: complete invoice CRUD lifecycle (create wizard -> edit -> send -> record payment -> verify paid status)
- [ ] E2E tests cover: void workflow (create -> send -> void -> verify time entries unlinked)
- [ ] E2E tests cover: permission enforcement (employee cannot access invoices)
- [ ] E2E tests cover: filter functionality on invoice list
- [ ] All test suites pass in CI
- [ ] No critical or high-severity bugs remaining
- [ ] `composer fix && composer analyse` passes
- [ ] `npm run lint:fix && npm run format` passes
- [ ] Performance: invoice list < 300ms for 10,000 invoices; preview < 2s for 50,000 time entries; PDF < 5s

#### Deliverables

**Frontend Tests** (4+ files):
- `resources/js/packages/ui/src/Invoice/__tests__/InvoiceTable.test.ts`
- `resources/js/packages/ui/src/Invoice/__tests__/InvoiceStatusBadge.test.ts`
- `resources/js/packages/ui/src/Invoice/__tests__/InvoiceLineItems.test.ts`
- `resources/js/packages/ui/src/Invoice/__tests__/InvoiceCreateWizard.test.ts`

**E2E Tests** (3 files):
- `e2e/invoices/invoice-crud.spec.ts`
- `e2e/invoices/invoice-wizard.spec.ts`
- `e2e/invoices/invoice-payment.spec.ts`

**Modified**:
- `routes/web.php` (invoice Inertia routes)
- `resources/js/Layouts/AppLayout.vue` (update nav href to use `route()`)

#### Risk Factors
- **E2E test environment**: Playwright tests require full stack running (Laravel + Vue + PostgreSQL + Gotenberg). Ensure CI pipeline has these services available
- **Bug triage**: E2E tests may uncover integration bugs that take longer than estimated to fix. The 37h buffer should absorb this, but prioritize critical-path bugs
- **Incomplete coverage**: If Sprints 3-6 delivered with bugs, Sprint 7 becomes more about fixing than testing. Mitigation: continuous testing throughout earlier sprints

---

## 5. Testing Strategy Per Sprint

### Testing Timeline

| Sprint | Tests Written | Test Type | Coverage Target |
|:------:|--------------|-----------|:---------------:|
| 0 | Foundation infrastructure tests | Unit, Integration | FOUND tasks verified |
| 1 | Client billing API tests (extend existing suite) | Endpoint | Existing client tests + billing fields |
| 2 | Smoke tests for all endpoints (manual/exploratory) | Manual | API contract verification |
| 3 | Component rendering tests (manual during development) | Manual | UI renders correctly |
| 4 | **INV-031**: InvoiceService unit tests (12h) | Unit | > 90% service coverage |
| 5 | **INV-032**: InvoiceController + PaymentController endpoint tests (16h) | Endpoint | > 85% controller coverage |
| 6 | **INV-033**: RecurringInvoiceService + payment tests (8h) | Unit | > 80% recurring/payment coverage |
| 7 | **INV-034**: Frontend component tests (10h); **INV-035**: E2E Playwright tests (12h) | Component, E2E | > 80% frontend, critical paths E2E |

### Integration Testing Schedule

| When | What | How |
|------|------|-----|
| End of Sprint 2 | Backend API integration | Run all endpoint tests against real DB; verify Gotenberg PDF generation |
| End of Sprint 3 | Frontend-Backend integration | Manual walkthrough of full wizard flow; verify API calls from Pinia store |
| End of Sprint 4 | Email integration | Send test invoice email; verify PDF attachment; test overdue command |
| End of Sprint 5 | Recurring invoice integration | Run scheduler command; verify draft invoice generation; test timezone handling |
| End of Sprint 6 | Stripe webhook integration | Use Stripe CLI to test webhook; verify payment recording |
| End of Sprint 7 | Full regression | Run all test suites (unit + endpoint + component + E2E); performance benchmarks |

### E2E Test Coverage Timeline

| Sprint | E2E Coverage Added |
|:------:|-------------------|
| 1-5 | None (focus on unit/endpoint tests) |
| 6 | Stripe webhook E2E can begin (optional early start) |
| 7 | Full E2E suite: CRUD lifecycle, wizard flow, payment flow, permission enforcement, filters |

### Test Files Summary

| Category | File | Sprint | Effort |
|----------|------|:------:|:------:|
| Service Unit | `tests/Unit/Service/InvoiceServiceTest.php` | 4 | 12h |
| Service Unit | `tests/Unit/Service/InvoicePaymentServiceTest.php` | 6 | 4h |
| Service Unit | `tests/Unit/Service/RecurringInvoiceServiceTest.php` | 6 | 4h |
| Endpoint | `tests/Unit/Endpoint/Api/V1/InvoiceEndpointTest.php` | 5 | 12h |
| Endpoint | `tests/Unit/Endpoint/Api/V1/InvoicePaymentEndpointTest.php` | 5 | 4h |
| Endpoint | `tests/Unit/Endpoint/Api/V1/RecurringInvoiceScheduleEndpointTest.php` | 5 | 4h* |
| Component | `Invoice/__tests__/InvoiceTable.test.ts` | 7 | 2.5h |
| Component | `Invoice/__tests__/InvoiceStatusBadge.test.ts` | 7 | 2.5h |
| Component | `Invoice/__tests__/InvoiceLineItems.test.ts` | 7 | 2.5h |
| Component | `Invoice/__tests__/InvoiceCreateWizard.test.ts` | 7 | 2.5h |
| E2E | `e2e/invoices/invoice-crud.spec.ts` | 7 | 4h |
| E2E | `e2e/invoices/invoice-wizard.spec.ts` | 7 | 4h |
| E2E | `e2e/invoices/invoice-payment.spec.ts` | 7 | 4h |

*Included in INV-032 scope.

---

## 6. Definition of Done

### 6.1 Per-Task Definition of Done

Every task is considered done when ALL of the following are true:

- [ ] Code follows the project's coding standards:
  - PHP: `declare(strict_types=1)`, 4-space indent, LF endings
  - JS/TS: ESLint + Prettier compliant
- [ ] `composer fix && composer analyse` passes (backend tasks)
- [ ] `npm run lint:fix && npm run format` passes (frontend tasks)
- [ ] Code is committed to the feature branch with a descriptive commit message
- [ ] All acceptance criteria listed in the task description are met
- [ ] No regressions in existing test suite (all pre-existing tests still pass)
- [ ] PHPDoc / JSDoc comments on all public methods and interfaces
- [ ] Organization scoping enforced (all queries scoped by `organization_id`)
- [ ] Permission checks implemented where applicable
- [ ] Code reviewed by at least one other developer (PR approval)

### 6.2 Per-Sprint Definition of Done

A sprint is considered complete when ALL of the following are true:

- [ ] All sprint tasks meet the per-task DoD
- [ ] All sprint acceptance criteria are met
- [ ] Sprint deliverables are merged to the feature branch
- [ ] Demo-able: the sprint's functionality can be demonstrated end-to-end
- [ ] No critical or high-severity bugs remaining from this sprint's scope
- [ ] Documentation: any new API endpoints are documented in OpenAPI spec
- [ ] Performance: no endpoint exceeds 2x the target response time
- [ ] Sprint retrospective conducted and action items recorded

### 6.3 Feature-Level Definition of Done

The Invoicing System feature is ready for production when ALL of the following are true:

- [ ] All 36 tasks (INV-001 through INV-036) are complete and meet per-task DoD
- [ ] All dependent FOUND tasks (FOUND-001, FOUND-002, FOUND-003, FOUND-004, FOUND-005, FOUND-007) are complete
- [ ] Full test suite passes:
  - Unit tests: InvoiceService, InvoicePaymentService, RecurringInvoiceService (> 85% coverage)
  - Endpoint tests: InvoiceController, InvoicePaymentController, RecurringScheduleController (> 85% coverage)
  - Frontend component tests: InvoiceTable, StatusBadge, LineItems, Wizard (> 80% coverage)
  - E2E tests: CRUD lifecycle, wizard flow, payment flow (all passing)
- [ ] Performance benchmarks met:
  - Invoice list: < 300ms (95th percentile) for 10,000 invoices
  - Invoice preview: < 2s for 50,000 time entries
  - PDF generation: < 5s per invoice
- [ ] Security review:
  - All endpoints require authentication
  - Organization data isolation verified
  - Permission matrix enforced for all roles
  - Invoice number generation is atomic (no race conditions)
  - Payment amounts validated server-side
  - No raw credit card data stored
- [ ] Accessibility: invoicing UI is keyboard-navigable and screen-reader compatible
- [ ] Browser compatibility: tested in Chrome, Firefox, Safari (latest versions)
- [ ] Mobile responsiveness: invoice list and detail pages render correctly on mobile
- [ ] `composer fix && composer analyse` passes
- [ ] `npm run lint:fix && npm run format` passes
- [ ] All code reviewed and merged via pull requests
- [ ] Feature flag / extension gate working (`isInvoicingActivated()`)
- [ ] Deployment documentation updated (new env vars: Gotenberg config, Stripe keys)

---

## 7. Risk Register

### 7.1 Technical Risks

| ID | Risk | Probability | Impact | Mitigation | Owner |
|:--:|------|:-----------:|:------:|------------|:-----:|
| TR-01 | Invoice number race condition -- concurrent requests generate duplicate numbers | Medium | High | Use `DB::transaction()` with `lockForUpdate()` on organization row; see CODEBASE-ANALYSIS risk section | Backend |
| TR-02 | PDF generation timeout for large invoices (500+ lines) | Low | Medium | Set Gotenberg timeout to 30s; add page breaks every 50 items in Blade template; queue large PDFs | Backend |
| TR-03 | Recurring invoice thundering herd at midnight UTC | Medium | High | Dispatch generation as queued jobs; stagger by org ID hash (`$orgId % 60` minutes); rate limit | Backend |
| TR-04 | INV-018/INV-019 frontend underestimation (AMD-07) | High | Medium | Split each into two sub-tasks; flag early (Day 5 of Sprint 3) if behind; pull from Sprint 7 buffer | Frontend |
| TR-05 | Stripe webhook delivery failure | Medium | Medium | Implement idempotency keys; build manual reconciliation command (`php artisan invoice:reconcile-payments`) | Backend |
| TR-06 | Multi-currency handling if org changes currency | Low | High | Invoice stores `currency` snapshot; prevent org currency change if active (non-void, non-paid) invoices exist | Backend |
| TR-07 | Tax calculation rounding errors | Medium | Medium | Use integer-cent arithmetic throughout; round only at display time via `LocalizationService`; add rounding tests | Backend |

### 7.2 Dependency Risks

| ID | Risk | Probability | Impact | Mitigation | Owner |
|:--:|------|:-----------:|:------:|------------|:-----:|
| DR-01 | FOUND-007 not ready before Sprint 2 | Low | Medium | Invoicing backend dev can implement the 4h modular permissions task if platform team is delayed | Backend |
| DR-02 | FOUND-001..005 not ready before Sprint 4 | Medium | Medium | Implement email sending without notification system first (email is independent); add notifications later | Backend |
| DR-03 | OpenAPI/TS client regeneration breaks frontend types | Low | Medium | Lock API contract before Sprint 3; frontend dev validates generated types on Day 1 of Sprint 3 | Fullstack |
| DR-04 | Gotenberg Docker image not available in CI | Low | High | Add Gotenberg to CI docker-compose; skip PDF tests with `@requires gotenberg` annotation as fallback | DevOps |

### 7.3 Capacity Risks

| ID | Risk | Probability | Impact | Mitigation | Owner |
|:--:|------|:-----------:|:------:|------------|:-----:|
| CR-01 | Backend developer unavailable (illness, vacation) | Low | High | Document critical path tasks thoroughly so another dev can pick up; pair program on INV-009 | PM |
| CR-02 | Frontend Sprint 3 overflows (56h in 2 weeks) | Medium | Medium | Backend dev assists with simpler components (StatusBadge, Filters); split INV-018/INV-019 per AMD-07 | PM |
| CR-03 | QA resource shared across features, may not be available for Sprint 7 | Medium | Medium | Frontend dev writes component tests (INV-034) independently; E2E tests can slip 1 week if needed | PM |
| CR-04 | Sprint 2 backend overload (56h for one dev) | Medium | High | INV-014 (Settings API, 6h) can slip to Sprint 3 start without blocking anything; INV-013 (PDF, 12h) can run parallel | PM |

---

## 8. Milestone Timeline

### Visual Timeline

```
Week:  0    1    2    3    4    5    6    7    8    9   10   11   12   13   14
       |    |    |    |    |    |    |    |    |    |    |    |    |    |    |
       |====|====|====|====|====|====|====|====|====|====|====|====|====|====|
       |S-0 | Sprint 1    | Sprint 2    | Sprint 3    | Sprint 4    | Sprint 5
       |    | DB & Models | Core Backend| Frontend UI | Email/Pay/  | Recurring &
       |    |             |             |             | Settings    | Endpoint Tests
       |    |    |    |    |    |    |    |    |    |    |    |    |    |    |
       |    |    |    |    |    |    |    |    |    |    |    | Sprint 6    |
       |    |    |    |    |    |    |    |    |    |    |    | Stripe &    |
       |    |    |    |    |    |    |    |    |    |    |    | Export      |
       |    |    |    |    |    |    |    |    |    |    |    |    | Sprint 7
       |    |    |    |    |    |    |    |    |    |    |    |    | E2E &
       |    |    |    |    |    |    |    |    |    |    |    |    | Polish
       |    |    |    |    |    |    |    |    |    |    |    |    |    |    |
       M1   |    M2   |    M3   |    M4   |    M5   |    M6   |    M7   |  M8
```

### Key Milestones

| Milestone | Week | Sprint | Description | Go/No-Go Criteria |
|:---------:|:----:|:------:|-------------|-------------------|
| **M1** | 0 | S-0 | Foundations Complete | FOUND-007 done; FOUND-001..005 in progress |
| **M2** | 2 | S-1 | Schema Stable | All migrations pass; models created; client billing UI works |
| **M3** | 4 | S-2 | API Functional | All CRUD endpoints return correct responses; PDF generates; **GO/NO-GO: API contract freeze for frontend** |
| **M4** | 6 | S-3 | MVP UI Complete | Full invoice workflow usable via UI (create wizard, view, edit, send); **GO/NO-GO: demo to stakeholders** |
| **M5** | 8 | S-4 | Core Feature Complete | Email, payments, settings, overdue detection all working; unit tests pass; **P0 scope complete** |
| **M6** | 10 | S-5 | Recurring & Tested | Recurring invoices functional; endpoint test suite > 85% coverage; **P1 scope complete** |
| **M7** | 12 | S-6 | Payments & Export | Stripe integration working; accounting export functional; **P2 scope complete** |
| **M8** | 14 | S-7 | Production Ready | All tests pass; performance benchmarks met; no critical bugs; **RELEASE CANDIDATE** |

### Go/No-Go Decision Points

| Decision Point | Week | Question | If NO |
|:-------------:|:----:|---------|-------|
| **M3 (API Freeze)** | 4 | Is the API contract stable enough for frontend to build against? | Extend Sprint 2 by 3-5 days; delay Sprint 3 start. Frontend dev assists with backend. |
| **M4 (Stakeholder Demo)** | 6 | Does the MVP UI meet stakeholder expectations? | Allocate Sprint 4 buffer time to UI fixes. Delay P1/P2 features by 1 sprint. |
| **M5 (P0 Scope Complete)** | 8 | Are all P0 features working and tested? | Extend Sprint 4 into Week 9. Compress P1/P2 into 2 sprints. Consider deferring P2 (Stripe/Export). |
| **M8 (Release Candidate)** | 14 | All tests pass? Performance OK? No critical bugs? | Add 1-week hardening sprint (Week 15). Ship P0+P1 if P2 has issues. |

### Priority-Based Scope Management

If timeline pressure requires scope reduction:

| Priority | Features | Can Ship Without | Impact |
|:--------:|----------|:----------------:|--------|
| **P0** | Invoice CRUD, PDF, Email, Payments, Settings (Sprints 1-4) | No | Core feature |
| **P1** | Recurring Invoices (Sprint 5) | Yes | Manual workaround exists |
| **P2** | Stripe Integration (Sprint 6) | Yes | Manual payments still work |
| **P2** | Accounting Export (Sprint 6) | Yes | CSV export from list page as workaround |

**Minimum Viable Release**: Sprints 1-4 + Sprint 7 tests = 10 weeks for P0 features only.

---

## Appendix A: Task ID Cross-Reference

The task assignments document uses `INV-xxx` numbering. Per AMD-01, all tasks in this sprint plan use the `INV-` prefix. The mapping is:

| Task Assignments ID | Sprint Plan ID | Description |
|:------------------:|:--------------:|-------------|
| INV-001 | INV-001 | Core invoice table migrations |
| INV-002 | INV-002 | Client billing columns |
| INV-003 | INV-003 | Org invoice settings columns |
| INV-004 | INV-004 | TimeEntry invoice_id FK |
| INV-005 | INV-005 | Eloquent models + enums |
| INV-006 | INV-006 | Model factories |
| INV-007 | INV-007 | Client billing API |
| INV-008 | INV-008 | Client billing UI |
| INV-009 | INV-009 | InvoiceService core logic |
| INV-010 | INV-010 | InvoiceController + routes (merged with INV-011 per AMD-06) |
| INV-011 | (merged into INV-010) | API routes (merged per AMD-06) |
| INV-012 | INV-012 | Invoice permissions |
| INV-013 | INV-013 | Invoice PDF Service |
| INV-014 | INV-014 | Invoice settings API |
| INV-015 | INV-015 | OpenAPI + TS client regen |
| INV-016 | INV-016 | Invoice Pinia store |
| INV-017 | INV-017 | Invoice list page |
| INV-018 | INV-018 | Invoice detail/edit page |
| INV-019 | INV-019 | Invoice creation wizard |
| INV-020 | INV-020 | PDF preview UI |
| INV-021 | INV-021 | Invoice settings UI |
| INV-022 | INV-022 | Invoice email service |
| INV-023 | INV-023 | Overdue detection command |
| INV-024 | INV-024 | Payment controller |
| INV-025 | INV-025 | Recurring invoice service |
| INV-026 | INV-026 | Recurring invoices UI |
| INV-027 | INV-027 | Stripe integration |
| INV-028 | INV-028 | Stripe frontend |
| INV-029 | INV-029 | Accounting export service |
| INV-030 | INV-030 | Accounting export UI |
| INV-031 | INV-031 | InvoiceService unit tests |
| INV-032 | INV-032 | Endpoint tests |
| INV-033 | INV-033 | Recurring + payment tests |
| INV-034 | INV-034 | Frontend component tests |
| INV-035 | INV-035 | E2E Playwright tests |
| (AMD-10) | INV-036 | Web route registration |

---

## Appendix B: Environment Configuration

### Required Services

| Service | Purpose | Configuration |
|---------|---------|--------------|
| PostgreSQL | Primary database | Existing; no changes needed |
| Gotenberg | PDF rendering | Existing `docker-compose.yml`; `GOTENBERG_URL` env var |
| Stripe (Sprint 6) | Online payments | New: `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` |
| Mail Server | Invoice emails | Existing Laravel mail config |
| Queue Worker | Async jobs (email, recurring invoices) | Existing Laravel queue; add `invoice` queue |

### New Environment Variables

```env
# Gotenberg (existing, verify configured)
GOTENBERG_URL=http://gotenberg:3000
GOTENBERG_BASIC_AUTH_USERNAME=
GOTENBERG_BASIC_AUTH_PASSWORD=

# Stripe (Sprint 6, P2)
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Scheduler Flags (new)
SCHEDULING_INVOICE_MARK_OVERDUE=true
SCHEDULING_INVOICE_GENERATE_RECURRING=true
```

---

**Last Updated**: 2026-02-06
**Author**: Sprint Planning Agent
**Status**: Draft -- pending team review and stakeholder approval
