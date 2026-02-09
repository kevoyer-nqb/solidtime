# Sprint Plan: Expense Management (Feature 02)

**Date**: 2026-02-06
**Feature branch**: `feature/expense-management`
**PRD**: `.features/02-expense-management/PRD.md`
**Architecture**: `.features/02-expense-management/ARCHITECTURE.md`
**Shared Foundations**: `.features/SHARED-FOUNDATIONS.md`

---

## 1. Executive Summary

The Expense Management feature adds full expense tracking and management capabilities to Solidtime, complementing the existing time tracking functionality. Organizations will be able to record expenses against projects, attach receipts, apply markup percentages for client billing, route expenses through an approval workflow, and export data for invoicing.

### Scope

- **Expense CRUD**: Create, read, update, delete expenses with amount (cents), currency, date, project/task association, categories, billable flag, and markup
- **Receipt Management**: Upload, download, and delete receipt files (JPEG, PNG, PDF, HEIC, WebP up to 10MB) stored on private filesystem
- **Expense Categories**: Admin-defined categories with one level of nesting, optional default markup, and archival support
- **Approval Workflow**: Draft -> Submitted -> Approved/Rejected state machine with self-approval prevention, bulk approval, and admin revert
- **Notifications**: Email and in-app notifications on expense submission, approval, and rejection (requires Shared Foundations notification infrastructure)
- **Export**: CSV, XLSX, and PDF export with filtering, matching existing time entry export patterns

### Effort Summary

| Metric | Value |
|--------|-------|
| Total feature tasks | 30 (EXP-001 through EXP-030) |
| Total feature effort | 224 hours |
| Total feature story points | 112 SP |
| Shared Foundation prerequisites | 7 tasks (FOUND-001 through FOUND-007) |
| Shared Foundation effort | 30 hours |
| **Grand total effort** | **254 hours** |
| Number of sprints | 4 (Sprint 0 + Sprints 1-3) |
| Sprint duration | 2 weeks each |
| Total calendar time | 8 weeks |
| Team size assumption | 3 developers (1 Backend, 1 Frontend, 1 Fullstack/QA) |
| Story point ratio | 2.0 hours per SP (per SF-10) |

### Team Roles

| Role | Abbreviation | Responsibilities |
|------|-------------|-----------------|
| Backend Developer | BE | Migrations, models, services, controllers, API endpoints, export pipeline |
| Frontend Developer | FE | Pinia stores, Vue components, TypeScript types, page integration |
| Fullstack / QA | FS/QA | Permissions, route registration, tests (unit, endpoint, component, E2E), OpenAPI docs |

---

## 2. Sprint Overview Table

| Sprint | Name | Duration | Story Points | Effort (hours) | Key Deliverables |
|:------:|------|:--------:|:------------:|:--------------:|-----------------|
| 0 | Shared Foundations | 2 weeks | 15 SP | 30h | Notification infrastructure, notification API, notification bell UI, notification preferences, ApprovalStatus enum, HasApprovalWorkflow trait, modular permissions |
| 1 | Backend Foundation | 2 weeks | 32 SP | 64h | Database migrations, models, factories, services, category CRUD controller, expense CRUD controller, filter, API resources, route registration, permissions |
| 2 | Approval + Frontend Core | 2 weeks | 33 SP | 66h | Approval workflow endpoints, receipt endpoints, notification classes, web routes, page shell, sidebar nav, Pinia stores, TS types, expense form, expense table, approval UI |
| 3 | Export, Category UI + Testing | 2 weeks | 32 SP | 64h | Export endpoint, category management UI, page integration, OpenAPI spec, all backend tests, frontend component tests, E2E tests |
| **Total** | | **8 weeks** | **112 SP** | **224h** | Complete Expense Management feature |

> **Note**: Sprint 0 (Shared Foundations) is shared across features 01, 02, and 07. If another feature has already completed Sprint 0, this feature can begin at Sprint 1 immediately. The 30h for Sprint 0 is not double-counted if already delivered.

---

## 3. Dependency Map

### 3.1 Shared Foundation Dependencies

The following Shared Foundation tasks must be completed before specific feature tasks can begin.

```
FOUND-001 (Notification migration, 2h)
    |
    v
FOUND-002 (Base notification classes, 4h)
    |
    +---> FOUND-003 (Notification bell UI, 8h)
    |         |
    |         v
    |     EXP-029 (Expense notification classes)
    |     EXP-030 (Dispatch notifications from ExpenseService)
    |
    +---> FOUND-004 (Notification API endpoints, 6h)
              |
              v
          FOUND-003 (bell UI depends on API)

FOUND-005 (Notification preferences, 4h) -- independent, parallel with FOUND-003/004

FOUND-007 (Modular permissions infrastructure, 4h)
    |
    v
EXP-008 (Register expense permissions)
```

**Foundation tasks NOT required by Expense Management**:
- FOUND-006 (weekly_capacity migrations) -- only needed by Features 08 and 09

### 3.2 Intra-Feature Dependencies

```
EXP-001 (Category Migration)
    |
    +---> EXP-002 (Expense Migration)
    |         |
    |         +---> EXP-005 (Expense Model) --+
    |                                          |
    +---> EXP-003 (Category Model) -------+   |
                                          |   |
                                          v   v
                                    EXP-006 (Category Controller)
                                          |
                                          v
EXP-004 (Status Enum) ----+        EXP-007 (Expense Controller + Service + Filter)
    |                      |              |
    +---> EXP-005          |              +---> EXP-009 (API Routes) --------+-----+-----+
    |                      |              |                                  |     |     |
    +---> EXP-008          |              +---> EXP-010 (Receipt Endpoints)  |     |     |
         (Permissions)     |              |                                  |     |     |
                           |              +---> EXP-011 (Approval Workflow)  |     |     |
                           |              |                                  |     |     |
                           |              +---> EXP-020 (Export Endpoint)    |     |     |
                           |                                                |     |     |
                           +---> EXP-015 (TS Types)                         |     |     |
                                     |                                      |     |     |
                                     v                                      v     |     |
                               EXP-014 (Pinia Stores) <-------- EXP-012 (Web Routes + Page)
                                     |                                |           |
                                     +---> EXP-016 (Expense Form)    |           |
                                     |                                |           v
                                     +---> EXP-017 (Expense Table)   |     EXP-013 (Sidebar Nav)
                                     |         |                      |
                                     |         v                      |
                                     |   EXP-018 (Approval Actions UI)|
                                     |                                |
                                     +---> EXP-019 (Category Mgmt UI)|
                                                                      |
                                     All UI tasks -----> EXP-025 (Page Integration)
                                                              |
                                                              v
                                                        EXP-027 (E2E Tests)

EXP-029 (Notification Classes) ---> EXP-030 (Dispatch Notifications)
    ^
    |
FOUND-001 through FOUND-005 (Notification infrastructure)
```

### 3.3 Critical Path

The longest sequential chain determines the minimum calendar time:

```
FOUND-007 --> EXP-001 --> EXP-002 --> EXP-005 --> EXP-007 --> EXP-009 --> EXP-014 --> EXP-017 --> EXP-025 --> EXP-027
   4h          4h          4h          10h          16h          4h          10h          12h         10h          8h
                                                                                                    Total: 82h
```

With parallel work streams, this fits within the 4-sprint (8-week) plan.

### 3.4 External Feature Dependencies

| Dependency | Type | Impact |
|-----------|------|--------|
| Feature 04 (Invoicing) | Soft, outbound | Invoicing can pull approved expenses as line items. Expense Management does NOT depend on Invoicing. |
| Feature 09 (Advanced Reporting) | Soft, outbound | Reporting will consume expense data for cost analysis. Expense Management does NOT depend on Reporting. |
| Feature 01 (Timesheet Approvals) | Shared pattern | Both use ApprovalStatus enum and HasApprovalWorkflow trait. No runtime dependency. |
| Feature 07 (PTO & Time Off) | Shared pattern | Same shared approval infrastructure. No runtime dependency. |

---

## 4. Sprint Details

---

### Sprint 0: Shared Foundations (Weeks 1-2)

**Sprint Goal**: Establish cross-cutting infrastructure (notifications, permissions, approval patterns) required by Expense Management and other approval-based features.

> This sprint may be executed by a dedicated foundations team or shared across feature teams. If already completed by another feature team, skip to Sprint 1.

#### Tasks

| Task ID | Description | Effort | SP | Dependencies | Assignee Role | Day Target |
|---------|-------------|:------:|:--:|:------------:|:-------------:|:----------:|
| FOUND-007 | Create modular permissions infrastructure (`app/Permissions/` directory, refactor existing permissions) | 4h | 2 | None | BE | Day 1 |
| FOUND-001 | Create notification infrastructure migration (`notifications` table, `notification_preferences` column on `members`) | 2h | 1 | None | BE | Day 1 |
| FOUND-002 | Create `BaseNotification` class (database + mail channels, respects per-member preferences) | 4h | 2 | FOUND-001 | BE | Day 2 |
| FOUND-004 | Create notification API endpoints (list, mark read, mark all read, unread count) | 6h | 3 | FOUND-002 | BE | Day 3-4 |
| FOUND-003 | Create `NotificationBell.vue` UI component (polling, mark-as-read, badge count) | 8h | 4 | FOUND-004 | FE | Day 5-7 |
| FOUND-005 | Add notification preferences to organization settings UI | 4h | 2 | FOUND-002 | FE | Day 4-5 |
| SF-05a | Create `App\Enums\ApprovalStatus` shared enum | 1h | 0.5 | None | BE | Day 1 |
| SF-05b | Create `App\Traits\HasApprovalWorkflow` shared trait | 1h | 0.5 | SF-05a | BE | Day 1 |

**Sprint 0 Total**: 30 hours / 15 SP

#### Acceptance Criteria

- [ ] `notifications` table exists and migration runs cleanly
- [ ] `members.notification_preferences` JSON column exists
- [ ] `BaseNotification` class sends to database + mail channels
- [ ] Notification API endpoints return correct responses (list, read, read-all, unread-count)
- [ ] `NotificationBell.vue` renders in AppLayout header, polls every 60s, shows unread count badge
- [ ] Notification preferences toggle works in organization settings
- [ ] `App\Permissions\` directory structure exists with modular registration pattern
- [ ] `JetstreamServiceProvider` calls `ExpensePermissions::register()` (and others as needed)
- [ ] `ApprovalStatus` enum and `HasApprovalWorkflow` trait are available in `App\Enums` and `App\Traits`

#### Deliverables

| Type | Files |
|------|-------|
| Migrations | `2026_02_28_000003_create_notifications_table.php`, `2026_02_28_000004_add_notification_preferences_to_members.php` |
| PHP Classes | `App\Notifications\BaseNotification`, `App\Enums\ApprovalStatus`, `App\Traits\HasApprovalWorkflow` |
| Controllers | `App\Http\Controllers\Api\V1\NotificationController` |
| Vue Components | `resources/js/packages/ui/src/Notification/NotificationBell.vue` |
| Permissions | `app/Permissions/` directory with modular pattern |

#### Risk Factors

- **Risk**: Notification infrastructure design decisions may conflict with existing email configuration.
  **Mitigation**: Review existing mail driver config in `.env.example` and `config/mail.php` before implementation.
- **Risk**: Polling-based notification bell may cause performance issues at scale.
  **Mitigation**: Use 60s polling interval; consider WebSocket upgrade in future phase.

---

### Sprint 1: Backend Foundation (Weeks 3-4)

**Sprint Goal**: Deliver all backend data infrastructure, models, services, controllers, and API routes for expenses and expense categories so that frontend development can begin in Sprint 2.

#### Tasks

| Task ID | Description | Effort | SP | Dependencies | Assignee Role | Day Target |
|---------|-------------|:------:|:--:|:------------:|:-------------:|:----------:|
| EXP-001 | Database migration for `expense_categories` table (UUID PK, name, description, color, default_markup, parent_id FK, organization_id FK, archived_at, timestamps, indexes) | 4h | 2 | FOUND-007 | BE | Day 1 |
| EXP-004 | Create `ExpenseStatus` enum (`draft`, `submitted`, `approved`, `rejected`) -- note: may use shared `ApprovalStatus` per AMD-04 | 2h | 1 | SF-05a | BE | Day 1 |
| EXP-002 | Database migration for `expenses` table (all columns per ARCHITECTURE.md Section 1.8, 7 indexes, 8 foreign keys) | 4h | 2 | EXP-001 | BE | Day 2 |
| EXP-003 | `ExpenseCategory` model, `ExpenseCategoryFactory`, `ExpenseCategoryService` (CRUD logic, nesting validation, delete-with-expenses guard) | 8h | 4 | EXP-001 | BE | Day 2-3 |
| EXP-008 | Register expense permissions in `App\Permissions\ExpensePermissions` and wire into `JetstreamServiceProvider` | 4h | 2 | EXP-004, FOUND-007 | FS/QA | Day 3 |
| EXP-005 | `Expense` model (HasUuids, CustomAuditable, HasApprovalWorkflow, ComputedAttributes for selling_price + client_id, all relationships, casts) and `ExpenseFactory` (10 state methods) | 10h | 5 | EXP-002, EXP-003, EXP-004 | BE | Day 3-5 |
| EXP-006 | `ExpenseCategoryController` (index/store/update/destroy), `ExpenseCategoryStoreRequest`, `ExpenseCategoryUpdateRequest`, `ExpenseCategoryResource`, `ExpenseCategoryCollection` | 10h | 5 | EXP-003 | BE | Day 5-6 |
| EXP-007 | `ExpenseController` (index/store/update/destroy), `ExpenseStoreRequest`, `ExpenseUpdateRequest`, `ExpenseIndexRequest`, `ExpenseResource`, `ExpenseCollection`, `ExpenseService` (create/update/delete), `ExpenseFilter` | 16h | 8 | EXP-005, EXP-006 | BE | Day 6-9 |
| EXP-009 | Register all API routes in `routes/api.php` for expenses and expense-categories (CRUD + receipt + approval + export routes) | 4h | 2 | EXP-006, EXP-007 | BE | Day 9 |
| EXP-015 | TypeScript type definitions for `Expense`, `ExpenseCategory`, `ApprovalStatus`, API request/response types | 4h | 2 | EXP-004, EXP-005 | FE | Day 8-9 |

**Sprint 1 Total**: 66 hours / 33 SP (adjusted from original 70h per AMD-09 by shifting EXP-005 filter into EXP-007)

> **Parallel execution note**: EXP-001 and EXP-004 start on Day 1 in parallel. EXP-003 and EXP-002 execute in parallel on Day 2. EXP-015 (frontend) can begin on Day 8 using the PRD data model specs, even before EXP-009 is complete. The Backend Developer carries the primary load in this sprint.

#### Acceptance Criteria

- [ ] Both database migrations run and roll back cleanly on a fresh database
- [ ] `ExpenseCategory` model: all relationships work, `isArchived` accessor matches `Project` pattern, factory produces valid data in all states
- [ ] `Expense` model: all 8 relationships defined, `selling_price` computed correctly (billable: amount * (1 + markup/100); non-billable: null), `client_id` computed from project, factory produces valid data in 10 states
- [ ] `ExpenseCategoryController`: all CRUD operations pass manual API testing via cURL/Postman
- [ ] `ExpenseController`: index with filtering (7 filter types), store with validation, update with editability guard, destroy with approved-expense guard
- [ ] `ExpenseService`: create/update/delete methods with DB transactions, selling_price recomputation
- [ ] `ExpenseFilter`: all 8 filter methods chain correctly
- [ ] All API routes registered and accessible (verified via `php artisan route:list | grep expense`)
- [ ] Permissions registered: 12 expense permissions + 4 category permissions across all 4 roles
- [ ] TypeScript types match the API resource structure

#### Deliverables

| Type | Files |
|------|-------|
| Migrations | `database/migrations/2026_03_02_000001_create_expense_categories_table.php`, `database/migrations/2026_03_02_000002_create_expenses_table.php` |
| Models | `app/Models/ExpenseCategory.php`, `app/Models/Expense.php` |
| Factories | `database/factories/ExpenseCategoryFactory.php`, `database/factories/ExpenseFactory.php` |
| Enums | `app/Enums/ExpenseStatus.php` (or shared `ApprovalStatus` per AMD-04) |
| Services | `app/Service/ExpenseService.php`, `app/Service/ExpenseCategoryService.php`, `app/Service/ExpenseFilter.php` |
| Controllers | `app/Http/Controllers/Api/V1/ExpenseController.php`, `app/Http/Controllers/Api/V1/ExpenseCategoryController.php` |
| Requests | `app/Http/Requests/V1/Expense/ExpenseIndexRequest.php`, `ExpenseStoreRequest.php`, `ExpenseUpdateRequest.php`; `app/Http/Requests/V1/ExpenseCategory/ExpenseCategoryStoreRequest.php`, `ExpenseCategoryUpdateRequest.php` |
| Resources | `app/Http/Resources/V1/Expense/ExpenseResource.php`, `ExpenseCollection.php`; `app/Http/Resources/V1/ExpenseCategory/ExpenseCategoryResource.php`, `ExpenseCategoryCollection.php` |
| Routes | Updated `routes/api.php` |
| Permissions | `app/Permissions/ExpensePermissions.php` |
| TypeScript | `resources/js/types/expense.d.ts` |

#### Risk Factors

- **Risk**: EXP-007 (Expense Controller) is the highest-effort task (16h/8 SP) and blocks 6 downstream tasks.
  **Mitigation**: If falling behind by Day 7, split EXP-007 into sub-tasks (index+filter, store, update, destroy) and assign the Fullstack/QA developer to help.
- **Risk**: EXP-005 (Expense Model) blocks both backend and frontend streams.
  **Mitigation**: Frontend can start EXP-015 (TS Types) from PRD specifications before the PHP model is finalized.
- **Risk**: Foreign key constraint complexity (8 FKs on expenses table) may cause migration order issues.
  **Mitigation**: Use the assigned `2026_03_02_` prefix to ensure correct ordering. Test on fresh database early in the sprint.

---

### Sprint 2: Approval Workflow + Frontend Core (Weeks 5-6)

**Sprint Goal**: Deliver the complete approval workflow backend, receipt handling, notification integration, and all core frontend components (stores, form, table, approval actions) so that the expense feature is functionally usable end-to-end.

#### Tasks

| Task ID | Description | Effort | SP | Dependencies | Assignee Role | Day Target |
|---------|-------------|:------:|:--:|:------------:|:-------------:|:----------:|
| EXP-010 | Receipt upload, download, and delete endpoints (`uploadReceipt`, `downloadReceipt`, `deleteReceipt` on ExpenseController, `ExpenseUploadReceiptRequest`, signed URL generation) | 8h | 4 | EXP-007 | BE | Day 1-2 |
| EXP-011 | Approval workflow endpoints (submit, approve, reject, bulkApprove, revert) with status transition validation, self-approval prevention, admin role check for revert | 12h | 6 | EXP-007 | BE | Day 2-4 |
| EXP-029 | Create expense notification classes (`ExpenseSubmittedNotification`, `ExpenseApprovedNotification`, `ExpenseRejectedNotification`) extending `BaseNotification` | 4h | 2 | EXP-007, FOUND-001 through FOUND-005 | BE | Day 4 |
| EXP-030 | Dispatch notifications from `ExpenseService` on submit/approve/reject status transitions | 2h | 1 | EXP-029 | BE | Day 5 |
| EXP-012 | Register web routes for Expenses page (`Inertia::render('Expenses')`) and create `Expenses.vue` page shell | 4h | 2 | EXP-009 | FE | Day 1 |
| EXP-013 | Add "Expenses" item to sidebar navigation in `AppLayout.vue` with appropriate heroicon | 2h | 1 | EXP-012 | FE | Day 1 |
| EXP-014 | Create Pinia stores: `useExpensesStore` and `useExpenseCategoriesStore` with `@tanstack/vue-query` for data fetching, mutations for CRUD, approval actions, receipt operations | 10h | 5 | EXP-009, EXP-015 | FE | Day 2-3 |
| EXP-016 | `ExpenseForm.vue` component (create/edit mode, all fields, project/task/category selectors, receipt upload with two-step UX per AMD-07, markup preview) | 12h | 6 | EXP-014, EXP-015 | FE | Day 4-6 |
| EXP-017 | `ExpenseTable.vue`, `ExpenseRow.vue`, `ExpenseFilterBar.vue`, `ExpenseStatusBadge.vue` components (sortable columns, filter controls, status indicators, receipt icon) | 12h | 6 | EXP-014, EXP-015 | FE | Day 6-8 |

**Sprint 2 Total**: 66 hours / 33 SP

> **Parallel execution note**: Backend (EXP-010, EXP-011, EXP-029, EXP-030) and Frontend (EXP-012 through EXP-017) work streams execute in parallel. The BE developer focuses on approval + receipts + notifications while the FE developer builds stores and components. The FS/QA developer can assist with either stream as needed.

#### Acceptance Criteria

- [ ] Receipt upload: JPEG/PNG/PDF/HEIC/WebP accepted, max 10MB enforced, file stored at `receipts/{org_id}/{expense_id}.{ext}`, old receipt replaced on re-upload
- [ ] Receipt download: returns signed temporary URL with 5-minute expiry
- [ ] Receipt delete: removes file from storage, clears `receipt_path` and `receipt_filename` on model
- [ ] Submit: transitions draft/rejected -> submitted, sets `submitted_at`, clears reviewer fields
- [ ] Approve: transitions submitted -> approved, validates self-approval prevention (422 if reviewer == expense owner), records reviewer_id/reviewed_at/comment
- [ ] Reject: transitions submitted -> rejected, requires comment, validates self-approval prevention
- [ ] Bulk approve: processes array of IDs, returns `{ success: [...], error: [...] }`, skips self-owned expenses
- [ ] Revert: transitions approved -> draft, requires Owner/Admin role, clears approval fields
- [ ] Invalid status transitions return 422 with descriptive error message
- [ ] Notifications: `ExpenseSubmittedNotification` sent to org managers/admins/owners; `ExpenseApprovedNotification` and `ExpenseRejectedNotification` sent to expense submitter
- [ ] Expenses page accessible via sidebar navigation
- [ ] Pinia stores: fetch expenses/categories via API, support all filter parameters, cache with vue-query
- [ ] Expense form: creates new expense, edits existing draft expense, two-step receipt upload flow works, markup preview shows computed selling price
- [ ] Expense table: displays all fields, filter bar filters by date/project/member/category/status/billable, status badges show correct colors, receipt icon indicates attachment

#### Deliverables

| Type | Files |
|------|-------|
| Request Validation | `app/Http/Requests/V1/Expense/ExpenseUploadReceiptRequest.php`, `ExpenseApproveRequest.php`, `ExpenseRejectRequest.php`, `ExpenseBulkApproveRequest.php` |
| Notifications | `app/Notifications/ExpenseSubmittedNotification.php`, `ExpenseApprovedNotification.php`, `ExpenseRejectedNotification.php` |
| Exceptions | `app/Exceptions/Api/ExpenseNotEditableApiException.php`, `InvalidExpenseStatusTransitionApiException.php`, `SelfApprovalNotAllowedApiException.php` |
| Vue Page | `resources/js/Pages/Expenses.vue` |
| Pinia Stores | `resources/js/utils/useExpenses.ts`, `resources/js/utils/useExpenseCategories.ts` |
| Vue Components | `resources/js/packages/ui/src/Expense/ExpenseForm.vue`, `ExpenseTable.vue`, `ExpenseRow.vue`, `ExpenseFilterBar.vue`, `ExpenseStatusBadge.vue` |
| Navigation | Updated `resources/js/Layouts/AppLayout.vue` |
| Routes | Updated `routes/web.php` |

#### Risk Factors

- **Risk**: Receipt upload file storage configuration may differ between local dev and production (S3).
  **Mitigation**: Use `Storage::disk(config('filesystems.private'))` consistently; test with both local and S3 drivers. Document required `.env` variables.
- **Risk**: Two-step expense creation + receipt upload UX (AMD-07) may confuse users if the loading state between steps is not clear.
  **Mitigation**: FE developer should implement optimistic UI with clear progress indicator. Consider disabling the receipt upload button until the expense is saved.
- **Risk**: Notification infrastructure (Sprint 0) may not be complete when Sprint 2 starts.
  **Mitigation**: EXP-029 and EXP-030 can be deferred to Sprint 3 if notifications are not ready. Gate notification dispatch behind a feature flag or null check.

---

### Sprint 3: Export, Category UI + Comprehensive Testing (Weeks 7-8)

**Sprint Goal**: Complete the remaining UI (category management, page integration), deliver the export pipeline, write comprehensive tests across all layers (unit, endpoint, component, E2E), and finalize OpenAPI documentation. The feature is production-ready at sprint end.

#### Tasks

| Task ID | Description | Effort | SP | Dependencies | Assignee Role | Day Target |
|---------|-------------|:------:|:--:|:------------:|:-------------:|:----------:|
| EXP-020 | Expense export endpoint (`ExpenseExportService` with CSV/XLSX/PDF output, reusing `ExportFormat` enum and Gotenberg PDF pipeline, signed download URL) | 12h | 6 | EXP-007 | BE | Day 1-2 |
| EXP-019 | Expense category management UI (admin-only page section: create/edit/archive categories, subcategory management, color picker, markup defaults) | 10h | 5 | EXP-014 | FE | Day 1-2 |
| EXP-018 | Expense approval actions UI (`ApproveButton.vue`, `RejectDialog.vue` with comment field, `BulkActionBar.vue` for multi-select approval) | 8h | 4 | EXP-017 | FE | Day 1-2 |
| EXP-025 | Integrate Expenses page with all components and stores (wire form, table, filter bar, approval actions, category manager, export button into `Expenses.vue`) | 10h | 5 | EXP-012, EXP-014, EXP-016, EXP-017, EXP-018, EXP-019 | FE | Day 3-4 |
| EXP-021 | Expense category API endpoint tests (CRUD operations, permission checks, nesting validation, delete-with-expenses guard) | 8h | 4 | EXP-006, EXP-009 | FS/QA | Day 1-2 |
| EXP-024 | `ExpenseService` unit tests (create, update, delete, selling_price computation, markup cascading, editability guard) | 6h | 3 | EXP-005, EXP-007 | FS/QA | Day 2-3 |
| EXP-022 | Expense CRUD API endpoint tests (index with all 7 filters, store validation, update editability, destroy approved-expense guard, receipt upload/download/delete, permission scoping own vs all) | 16h | 8 | EXP-007, EXP-009, EXP-010 | FS/QA | Day 3-5 |
| EXP-023 | Expense approval workflow API endpoint tests (submit, approve, reject, bulk-approve, revert; invalid transitions; self-approval prevention; admin-only revert) | 10h | 5 | EXP-011 | FS/QA | Day 5-6 |
| EXP-028 | OpenAPI specification update for all expense and expense-category endpoints (request/response schemas, error codes, permission requirements) | 4h | 2 | EXP-009, EXP-011 | BE | Day 5 |
| EXP-026 | Frontend Vitest component tests (`ExpenseForm`, `ExpenseTable`, `ExpenseFilterBar`, `ExpenseStatusBadge`, `ExpenseCategoryManager`, `ApproveButton`, `RejectDialog`) | 8h | 4 | EXP-016, EXP-017, EXP-018, EXP-019 | FE | Day 6-7 |
| EXP-027 | E2E Playwright tests (create expense, upload receipt, submit for approval, approve/reject, filter expenses, export, category management) | 8h | 4 | EXP-025 | FS/QA | Day 7-8 |

**Sprint 3 Total**: 100 hours / 50 SP (requires parallel execution across all 3 team members)

> **Parallel execution note**: This is the heaviest sprint due to the testing load. Backend (EXP-020, EXP-028), Frontend (EXP-018, EXP-019, EXP-025, EXP-026), and QA (EXP-021 through EXP-024, EXP-027) all execute in parallel. The 100 total hours are distributed across 3 developers at approximately 33 hours each over 2 weeks (3.3 hours/day), which is achievable.

#### Acceptance Criteria

- [ ] Export: CSV export of 50,000 expenses completes in < 30s; XLSX in < 60s; PDF in < 120s
- [ ] Export: respects all index filters; returns signed download URL with 5-minute expiry
- [ ] Export: PDF uses Gotenberg renderer matching existing time entry export pattern
- [ ] Category management UI: admin can create, edit, archive, and manage subcategories
- [ ] Approval actions UI: approve/reject buttons appear for submitted expenses when user has `expenses:approve` permission; reject dialog requires comment
- [ ] Bulk action bar: select multiple submitted expenses and approve in batch
- [ ] Page integration: `Expenses.vue` is fully functional with all sub-components wired together
- [ ] Backend endpoint tests: >= 90% code coverage for `ExpenseController`, `ExpenseCategoryController`, `ExpenseService`
- [ ] Service unit tests: all `ExpenseService` methods tested including edge cases (zero amount, no project, currency override, markup cascading)
- [ ] Approval tests: all valid/invalid status transitions tested, self-approval returns 403, admin revert works
- [ ] Frontend component tests: all 7 components have Vitest tests covering key interactions
- [ ] E2E tests: complete expense lifecycle (create -> upload receipt -> submit -> approve -> export) covered
- [ ] OpenAPI spec is valid and documents all 16 API endpoints
- [ ] `composer fix && composer analyse` passes without errors
- [ ] `npm run lint:fix && npm run format` passes without errors

#### Deliverables

| Type | Files |
|------|-------|
| Export Service | `app/Service/ExpenseExportService.php` |
| Vue Components | `resources/js/packages/ui/src/Expense/ApproveButton.vue`, `RejectDialog.vue`, `BulkActionBar.vue`, `ExpenseCategoryManager.vue` |
| Backend Tests | `tests/Unit/Endpoint/Api/V1/ExpenseCategoryEndpointTest.php`, `tests/Unit/Endpoint/Api/V1/ExpenseEndpointTest.php`, `tests/Unit/Service/ExpenseServiceTest.php` |
| Frontend Tests | `resources/js/packages/ui/src/Expense/__tests__/ExpenseForm.test.ts`, `ExpenseTable.test.ts`, `ExpenseStatusBadge.test.ts`, `ExpenseCategoryManager.test.ts` etc. |
| E2E Tests | `e2e/expense-management.spec.ts` |
| OpenAPI | Updated `openapi.yaml` or equivalent spec file |

#### Risk Factors

- **Risk**: Sprint 3 has 100 hours of work, the highest of any sprint. Testing tasks may take longer than estimated.
  **Mitigation**: Start backend tests (EXP-021, EXP-024) on Day 1 in parallel with remaining UI work. The FS/QA developer should be fully allocated to testing. If EXP-022 (16h) runs over, split into CRUD tests and receipt tests.
- **Risk**: PDF export via Gotenberg requires a running Gotenberg service in the dev/CI environment.
  **Mitigation**: Ensure Gotenberg is configured in `docker-compose.yml`. Add a conditional skip to PDF export tests if Gotenberg is unavailable.
- **Risk**: E2E tests (EXP-027) depend on all components being integrated (EXP-025). Late integration issues could delay E2E testing.
  **Mitigation**: Run E2E tests on Day 7-8 with 2 days of buffer. Prioritize happy-path E2E tests first; add edge-case scenarios if time permits.

---

## 5. Testing Strategy Per Sprint

### Sprint 0: Shared Foundations

| Test Type | Scope | Task |
|-----------|-------|------|
| Unit | `BaseNotification` channel routing, preference checking | Part of FOUND-002 |
| Endpoint | Notification API endpoints (list, read, read-all, unread-count) | Part of FOUND-004 |
| Component | `NotificationBell.vue` renders badge, handles click | Part of FOUND-003 |

### Sprint 1: Backend Foundation

| Test Type | Scope | Task |
|-----------|-------|------|
| Manual API Testing | Verify all CRUD endpoints via cURL/Postman during development | Inline with EXP-006, EXP-007, EXP-009 |
| Migration Testing | Run `php artisan migrate:fresh` on each migration PR | Inline with EXP-001, EXP-002 |
| Factory Testing | Verify all factory states produce valid model instances | Inline with EXP-003, EXP-005 |

> Formal automated tests are deferred to Sprint 3 (EXP-021, EXP-022, EXP-024) to avoid blocking frontend development.

### Sprint 2: Approval + Frontend Core

| Test Type | Scope | Task |
|-----------|-------|------|
| Manual Testing | Approval workflow status transitions, self-approval prevention, receipt upload/download | Inline with EXP-010, EXP-011 |
| Manual Testing | Frontend components render correctly, form validation, filter bar filters | Inline with EXP-016, EXP-017 |
| Notification Testing | Verify notifications dispatched on submit/approve/reject | Inline with EXP-029, EXP-030 |

> Formal automated tests deferred to Sprint 3.

### Sprint 3: Comprehensive Testing

| Test Type | Scope | Task | Coverage Target |
|-----------|-------|------|:-:|
| **API Endpoint Tests** | ExpenseCategory CRUD (create, read, update, delete, permission checks, nesting rules) | EXP-021 | 90%+ |
| **API Endpoint Tests** | Expense CRUD (index + 7 filters, store validation, update editability, destroy approved guard, receipt endpoints, permission own/all scoping) | EXP-022 | 90%+ |
| **API Endpoint Tests** | Approval workflow (submit valid/invalid, approve valid/self/invalid, reject valid/no-comment, bulk approve, revert admin/non-admin) | EXP-023 | 95%+ |
| **Service Unit Tests** | `ExpenseService` (createExpense, updateExpense, deleteExpense, submitExpense, approveExpense, rejectExpense, revertExpense, uploadReceipt, deleteReceipt, selling_price computation, markup cascading) | EXP-024 | 95%+ |
| **Frontend Component Tests** | `ExpenseForm`, `ExpenseTable`, `ExpenseFilterBar`, `ExpenseStatusBadge`, `ExpenseCategoryManager`, `ApproveButton`, `RejectDialog` | EXP-026 | Key interactions |
| **E2E Playwright Tests** | Complete lifecycle: create expense -> upload receipt -> submit -> approve -> filter -> export; Category management; Rejection flow | EXP-027 | Happy paths + key edge cases |

### Integration Testing Timeline

```
Sprint 0:  Foundation components tested in isolation
Sprint 1:  Backend API tested manually; migrations verified on fresh DB
Sprint 2:  Frontend <-> Backend integration tested manually (full stack running)
Sprint 3:  |----- Automated API endpoint tests (Day 1-6) ------|
           |----- Service unit tests (Day 2-3) -------|
           |----------- Frontend component tests (Day 6-7) -------|
           |-------------------------------- E2E integration tests (Day 7-8) ---|
```

---

## 6. Definition of Done

### Per-Task DoD Checklist

- [ ] Code is written and compiles without errors
- [ ] `declare(strict_types=1)` at top of every PHP file
- [ ] PHP code passes `composer fix` (code style) and `composer analyse` (static analysis)
- [ ] TypeScript/Vue code passes `npm run lint:fix` and `npm run format`
- [ ] All new code has appropriate inline documentation (PHPDoc blocks for PHP, JSDoc for TypeScript)
- [ ] No hardcoded values; configuration uses `.env` or `config/` files
- [ ] Organization scoping enforced at query level (`whereBelongsTo($organization, 'organization')`)
- [ ] Permission checks use the corrected naming convention from SF-02
- [ ] Code follows existing codebase patterns (TimeEntry model for Expense, TagController for Category Controller, etc.)
- [ ] Pull request created and passing CI checks
- [ ] Code reviewed by at least one other team member

### Per-Sprint DoD Checklist

- [ ] All sprint tasks marked as "Completed"
- [ ] All acceptance criteria for the sprint are met
- [ ] No critical or high-severity bugs open
- [ ] `php artisan migrate:fresh` runs successfully with all sprint migrations
- [ ] Backend API endpoints return correct responses (verified via manual or automated testing)
- [ ] Frontend components render correctly in the browser (verified via manual or automated testing)
- [ ] Sprint demo completed with stakeholder sign-off
- [ ] Retrospective conducted and improvement actions documented

### Feature-Level DoD Checklist

- [ ] All 30 tasks (EXP-001 through EXP-030) completed and merged
- [ ] All 7 shared foundation tasks (FOUND-001 through FOUND-007) completed (or confirmed completed by another feature team)
- [ ] Database migrations run cleanly on a fresh database in correct order
- [ ] All 16 API endpoints return correct responses with proper error handling
- [ ] All 12 expense permissions and 4 category permissions enforced correctly across all 4 roles
- [ ] Approval workflow state machine covers all valid transitions and rejects all invalid transitions
- [ ] Self-approval prevention works (SF-05 compliance)
- [ ] Notifications dispatched on all 3 status transitions (submitted, approved, rejected)
- [ ] Receipt upload/download/delete works with all 5 supported file types
- [ ] Export works for CSV, XLSX, and PDF formats with correct data
- [ ] API endpoint test coverage >= 90% for all controllers
- [ ] Service unit test coverage >= 95% for `ExpenseService`
- [ ] Frontend component tests cover all 7 key components
- [ ] E2E tests cover the complete expense lifecycle
- [ ] OpenAPI specification is complete and valid
- [ ] `composer fix && composer analyse` passes with zero errors
- [ ] `npm run lint:fix && npm run format` passes with zero errors
- [ ] No P0 or P1 bugs open
- [ ] Performance meets targets: list < 200ms (95th percentile), export CSV < 30s for 50K records, receipt upload < 2s
- [ ] Security review completed: receipt files on private disk, signed URLs only, MIME validation server-side, rate limiting on uploads
- [ ] Feature branch merged to `main` (or release branch) after final approval

---

## 7. Risk Register

### Technical Risks

| # | Risk | Probability | Impact | Mitigation | Owner |
|:-:|------|:----------:|:------:|------------|:-----:|
| T1 | EXP-007 (Expense Controller, 16h) is the highest-effort single task and blocks 6 downstream tasks. Delays here cascade through the entire plan. | Medium | High | Split into sub-tasks if behind by Day 7 of Sprint 1. Assign Fullstack dev to assist. Register routes with placeholder controllers early to unblock frontend. | BE |
| T2 | Receipt file storage configuration differs between local (disk) and production (S3). Tests may pass locally but fail in CI/staging. | Medium | Medium | Use `Storage::disk(config('filesystems.private'))` consistently. Add S3-compatible test in CI. Document `.env` variables: `FILESYSTEM_PRIVATE_DISK`, `AWS_*`. | BE |
| T3 | PDF export via Gotenberg requires external service. Service unavailability blocks export testing. | Low | Medium | Add Gotenberg to `docker-compose.yml`. Skip PDF-specific tests with `@requires` annotation if Gotenberg unavailable. Test CSV/XLSX independently. | BE |
| T4 | Computed `selling_price` accuracy with floating-point rounding in PHP. Markup of 33% on 100 cents should yield 133, not 132 or 134. | Low | High | Use `(int) round()` for all computations. Add unit tests for edge cases: 0% markup, 100% markup, 33.33% (not supported -- integer only), max 999% markup. | BE |
| T5 | Large expense datasets (50K+ records) cause slow export or list endpoint responses. | Low | Medium | Ensure indexes are used (verify with `EXPLAIN ANALYZE`). Use chunked processing in export service. Set reasonable `max` limits on pagination (500 for list, 50K for export). | BE |

### Dependency Risks

| # | Risk | Probability | Impact | Mitigation | Owner |
|:-:|------|:----------:|:------:|------------|:-----:|
| D1 | Shared Foundations (Sprint 0) not completed before Sprint 2 needs notification infrastructure. | Medium | Medium | EXP-029 and EXP-030 can be deferred to Sprint 3. Gate notification dispatch behind null check. Approval workflow works without notifications. | PM |
| D2 | Shared `ApprovalStatus` enum or `HasApprovalWorkflow` trait has breaking changes if Feature 01 (Timesheet Approvals) modifies them concurrently. | Low | Medium | Pin the trait/enum to a known-good commit. Both features use the same trait without modification. Communicate changes via shared Slack channel. | PM |
| D3 | FOUND-007 (modular permissions) requires refactoring existing permission registration. If existing tests break, Sprint 1 EXP-008 is blocked. | Low | High | Implement FOUND-007 as backwards-compatible (existing permissions still work, new pattern is additive). Run full existing test suite after refactoring. | BE |

### Capacity Risks

| # | Risk | Probability | Impact | Mitigation | Owner |
|:-:|------|:----------:|:------:|------------|:-----:|
| C1 | Sprint 3 has 100 hours of work across 3 developers (33h each over 10 working days). Developer illness or PTO could put the sprint at risk. | Medium | Medium | Identify 2-3 lower-priority tasks that can be deferred to a follow-up sprint (EXP-028 OpenAPI, part of EXP-026 component tests). Cross-train team members on each other's tasks. | PM |
| C2 | Single Backend Developer carries most of Sprint 1 (56h of 66h). Bus factor is 1. | Medium | High | Document all architectural decisions in ARCHITECTURE.md (already done). Pair-program on EXP-007 for knowledge sharing. Frontend Dev can start EXP-015 (TS Types) from PRD specs. | PM |
| C3 | Frontend Developer may be blocked in Sprint 1 until API routes (EXP-009) are available at end of sprint. | High | Low | FE can work on EXP-015 (TS Types) and prepare component scaffolding using mock data. Register API routes with placeholder controllers by Day 5 to unblock store development. | BE/FE |

---

## 8. Milestone Timeline

```
Week 1          Week 2          Week 3          Week 4          Week 5          Week 6          Week 7          Week 8
|--- Sprint 0 ---|--- Sprint 0 ---|--- Sprint 1 ---|--- Sprint 1 ---|--- Sprint 2 ---|--- Sprint 2 ---|--- Sprint 3 ---|--- Sprint 3 ---|

|==============|  |==============|  |==============|  |==============|  |==============|  |==============|  |==============|  |==============|
     FOUND-*          FOUND-*          EXP-001-004      EXP-005-009      EXP-010-013      EXP-014-017      EXP-018-025      EXP-021-027
  Notif infra      Notif bell UI    Migrations+Models  Ctrlrs+Routes   Approval+Receipt  Stores+Components  UI+Export        Testing+E2E

Milestones:
  [M0]              [M1]              [M2]              [M3]              [M4]              [M5]              [M6]              [M7]

M0  (End Week 1):  Notification migration + base classes done
M1  (End Week 2):  CHECKPOINT -- Shared Foundations complete. Go/No-Go for Sprint 1.
M2  (End Week 3):  Database tables exist. Models and factories operational.
M3  (End Week 4):  CHECKPOINT -- All API endpoints registered and manually testable. Go/No-Go for frontend start.
M4  (End Week 5):  Approval workflow and receipt handling complete on backend.
M5  (End Week 6):  CHECKPOINT -- Feature is end-to-end usable (manual testing). Go/No-Go for testing sprint.
M6  (End Week 7):  All UI integrated. Export pipeline functional. Backend tests written.
M7  (End Week 8):  CHECKPOINT -- Feature complete. All tests passing. Ready for final review and merge.
```

### Go/No-Go Decision Points

| Checkpoint | Week | Decision Criteria | Fallback if No-Go |
|:----------:|:----:|-------------------|-------------------|
| M1 | 2 | All FOUND-* tasks complete. Notification API returns data. Permission pattern validated. | Defer notification-dependent tasks (EXP-029, EXP-030) to Sprint 3. Proceed with Sprint 1 (no notification dependency). |
| M3 | 4 | All EXP-001 through EXP-009 complete. `php artisan route:list` shows all expense routes. Manual API test passes for basic CRUD. | Extend Sprint 1 by 2-3 days. Compress Sprint 2 by deferring EXP-017 (table) to Sprint 3. Frontend starts with mock API data. |
| M5 | 6 | Approval workflow tested manually (all 5 transitions). Receipt upload/download works. Frontend form creates and displays expenses. | Defer EXP-019 (Category UI) and EXP-018 (Approval Actions UI) to Sprint 3. Extend Sprint 3 by 3 days if needed. |
| M7 | 8 | All 30 EXP tasks and 7 FOUND tasks complete. Test coverage targets met. No P0/P1 bugs. `composer fix && analyse` clean. `npm run lint:fix && format` clean. | Extend with a 1-week hardening sprint. Prioritize fixing any test failures and P0 bugs. Defer P2 items (PDF export polish, edge-case E2E tests). |

### Key Dates (Assuming Start: 2026-02-09)

| Date | Event |
|------|-------|
| 2026-02-09 | Sprint 0 starts (Shared Foundations) |
| 2026-02-20 | **M1**: Shared Foundations complete |
| 2026-02-23 | Sprint 1 starts (Backend Foundation) |
| 2026-03-06 | **M3**: All API endpoints registered |
| 2026-03-09 | Sprint 2 starts (Approval + Frontend Core) |
| 2026-03-20 | **M5**: Feature end-to-end usable |
| 2026-03-23 | Sprint 3 starts (Export, Category UI + Testing) |
| 2026-04-03 | **M7**: Feature complete. Ready for final review. |
| 2026-04-06 | Target merge to `main` |

---

## Appendix A: Task ID Cross-Reference

The task assignments file uses `EXP-XXX` naming internally. Per AMD-01 (SF-01), all task IDs use the `EXP-` prefix. This cross-reference maps between the two.

| EXP ID | Original TASK ID | Description |
|--------|-----------------|-------------|
| EXP-001 | EXP-001 | Database migration for expense_categories table |
| EXP-002 | EXP-002 | Database migration for expenses table |
| EXP-003 | EXP-003 | ExpenseCategory model, factory, and service |
| EXP-004 | EXP-004 | ExpenseStatus enum (or use shared ApprovalStatus per AMD-04) |
| EXP-005 | EXP-005 | Expense model and factory |
| EXP-006 | EXP-006 | ExpenseCategory CRUD controller and requests |
| EXP-007 | EXP-007 | Expense CRUD controller, requests, service, and filter |
| EXP-008 | EXP-008 | Register expense permissions (modular pattern per SF-08) |
| EXP-009 | EXP-009 | Register API routes for expenses and categories |
| EXP-010 | EXP-010 | Receipt upload, download, and delete endpoints |
| EXP-011 | EXP-011 | Approval workflow endpoints (submit/approve/reject/bulk/revert) |
| EXP-012 | EXP-012 | Register web routes and Expenses page shell |
| EXP-013 | EXP-013 | Add Expenses to sidebar navigation |
| EXP-014 | EXP-014 | Pinia stores for expenses and expense categories |
| EXP-015 | EXP-015 | TypeScript type definitions for expense models |
| EXP-016 | EXP-016 | Expense form component (create/edit) |
| EXP-017 | EXP-017 | Expense table, row, filter bar, and status badge components |
| EXP-018 | EXP-018 | Expense approval actions, reject dialog, bulk action bar |
| EXP-019 | EXP-019 | Expense category management UI (admin) |
| EXP-020 | EXP-020 | Expense export endpoint (CSV/XLSX/PDF) |
| EXP-021 | EXP-021 | Expense category API endpoint tests |
| EXP-022 | EXP-022 | Expense CRUD API endpoint tests |
| EXP-023 | EXP-023 | Expense approval workflow API endpoint tests |
| EXP-024 | EXP-024 | ExpenseService unit tests |
| EXP-025 | EXP-025 | Integrate Expenses page with all components and stores |
| EXP-026 | EXP-026 | Frontend Vitest component tests |
| EXP-027 | EXP-027 | E2E Playwright tests for expense feature |
| EXP-028 | EXP-028 | OpenAPI specification update for expense endpoints |
| EXP-029 | (AMD-05) | Create expense notification classes |
| EXP-030 | (AMD-05) | Dispatch notifications from ExpenseService |

## Appendix B: Effort Summary by Role

| Role | Sprint 0 | Sprint 1 | Sprint 2 | Sprint 3 | Total |
|------|:--------:|:--------:|:--------:|:--------:|:-----:|
| Backend Developer (BE) | 12h | 52h | 26h | 16h | 106h |
| Frontend Developer (FE) | 12h | 4h | 40h | 36h | 92h |
| Fullstack / QA (FS/QA) | 6h | 10h | 0h | 48h | 64h |
| **Sprint Total** | **30h** | **66h** | **66h** | **100h** | **262h** |

> Per-person load per sprint (10 working days, 8h/day = 80h capacity):
> - Sprint 0: ~10h/person (light -- shared with other features)
> - Sprint 1: ~22h/person (moderate -- BE-heavy)
> - Sprint 2: ~22h/person (moderate -- parallel BE/FE)
> - Sprint 3: ~33h/person (heavy -- testing crunch)

---

*Last updated: 2026-02-06*
