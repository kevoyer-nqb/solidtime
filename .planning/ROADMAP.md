# Roadmap: Solidtime SaaS Platform

## Overview

Transform solidtime from an open-source time tracker into a full SaaS platform for agencies and SMBs. The roadmap moves through shared infrastructure, the prerequisite timesheet grid, governance features (approvals, expenses, budgets, teams), revenue generation (invoicing, payments), time capture extensions (calendar sync, kiosk, punch clock), workforce management (PTO, scheduling, attendance), and finally analytics and platform extensions. Each phase delivers a coherent capability that builds on what came before, with shared foundations ensuring consistency across all 17 features.

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [x] **Phase 1: Shared Foundations** - Cross-cutting infrastructure all features depend on (completed 2026-02-10)
- [ ] **Phase 2: Weekly Timesheet Grid** - The prerequisite time entry interface
- [ ] **Phase 3: Governance & Teams** - Approval workflows, expense management, budgets, team organization
- [ ] **Phase 4: Revenue Generation** - Invoicing and payment/accounting integrations
- [ ] **Phase 5: Time Capture Extensions** - Calendar sync, kiosk mode, punch clock
- [ ] **Phase 6: Workforce Management** - PTO, resource scheduling, attendance and overtime
- [ ] **Phase 7: Analytics & Platform Extensions** - Advanced reporting, custom fields, audit trail UI

## Phase Details

### Phase 1: Shared Foundations
**Goal**: Every downstream feature has the notification infrastructure, approval pattern, permissions system, date handling, and schema extensions it needs -- built once, used everywhere
**Depends on**: Nothing (first phase)
**Requirements**: FOUND-01, FOUND-02, FOUND-03, FOUND-04, FOUND-05, FOUND-06, FOUND-07, FOUND-08, FOUND-09, FOUND-10
**Success Criteria** (what must be TRUE):
  1. A notification sent from any feature appears in the notification bell in the AppLayout header, and the user can mark it as read
  2. A member's notification preferences (per-type email toggles) are configurable in organization settings and respected by the notification system
  3. The modular permissions infrastructure allows a new feature to register its permissions in its own file without modifying JetstreamServiceProvider directly
  4. The DateBoundaryService correctly calculates week boundaries and day boundaries across DST transitions in any IANA timezone
  5. The daily_time_summaries table is populated and queryable for pre-aggregated reporting data
**Plans**: 2 plans

Plans:
- [ ] 01-01-PLAN.md — Notification system: migrations, base classes, API endpoints, bell UI, preferences UI
- [ ] 01-02-PLAN.md — Modular permissions, DateBoundaryService, weekly_capacity schema, CI migration test, daily summaries

### Phase 2: Weekly Timesheet Grid
**Goal**: Users can view and manage their weekly time in a grid interface that replaces the need to create individual time entries one by one
**Depends on**: Phase 1 (DateBoundaryService for week boundary calculations)
**Requirements**: TSG-01, TSG-02, TSG-03, TSG-04, TSG-05, TSG-06
**Success Criteria** (what must be TRUE):
  1. User sees their time entries laid out in a Mon-Sun grid with rows grouped by project and task
  2. User can click an empty cell and create a time entry for that day and project/task without leaving the grid
  3. User can edit a duration directly in a grid cell and the time entry updates
  4. User can navigate to any week and see daily column totals and a weekly total
**Plans**: 2 plans

Plans:
- [ ] 02-01-PLAN.md — Grid data layer: backend API endpoint, Pinia store with TanStack Query, web route, sidebar navigation
- [ ] 02-02-PLAN.md — Grid UI: page layout, grid components, inline cell editing, entry creation, add-row, totals, human verification

### Phase 3: Governance & Teams
**Goal**: Organizations can enforce accountability -- timesheets go through approval, expenses are tracked and approved, project budgets are monitored with alerts, and teams provide organizational structure with optional visibility scoping
**Depends on**: Phase 1 (notification infrastructure, approval pattern, permissions), Phase 2 (timesheet grid for approval context)
**Requirements**: APPR-01, APPR-02, APPR-03, APPR-04, APPR-05, APPR-06, APPR-07, APPR-08, APPR-09, APPR-10, EXP-01, EXP-02, EXP-03, EXP-04, EXP-05, EXP-06, EXP-07, EXP-08, BUD-01, BUD-02, BUD-03, BUD-04, BUD-05, BUD-06, TEAM-01, TEAM-02, TEAM-03, TEAM-04, TEAM-05, TEAM-06
**Success Criteria** (what must be TRUE):
  1. User can submit their weekly timesheet and the assigned manager can approve or request changes, with both parties notified at each transition
  2. Approved timesheets lock the underlying time entries -- editing requires reopening the timesheet first
  3. User can create expenses with receipt uploads, mark them billable, and submit for approval with the same workflow pattern as timesheets
  4. Admin can set project budgets (hours, cost, or fixed-fee) and the project dashboard shows burn rate, remaining budget, and forecast
  5. Users and managers receive threshold notifications (75%, 90%, 100%) when project budgets approach limits
  6. Admin can create teams, assign members and projects to teams, and optionally enable team scoping to restrict visibility
  7. Self-approval is prevented across all approval workflows (timesheets and expenses)
**Plans**: TBD

Plans:
- [ ] 03-01: Timesheet approvals (submission workflow, approval/reject, locking, notifications, reminders)
- [ ] 03-02: Expense management (CRUD, receipt uploads, categories, billable flag, approval workflow)
- [ ] 03-03: Budgets and alerts (project budgets, burn tracking, threshold notifications, forecasting)
- [ ] 03-04: Teams and groups (team CRUD, member/project assignment, scoping, manager visibility)

### Phase 4: Revenue Generation
**Goal**: Organizations can turn tracked and approved time and expenses into invoices, collect payments, and sync financial data with accounting platforms
**Depends on**: Phase 3 (approved time entries and expenses provide invoice line items; expense data for invoice inclusion)
**Requirements**: INV-01, INV-02, INV-03, INV-04, INV-05, INV-06, INV-07, INV-08, PAY-01, PAY-02, PAY-03, PAY-04, PAY-05
**Success Criteria** (what must be TRUE):
  1. User can generate an invoice from approved time entries for a project, with expense line items included
  2. Invoice includes organization branding (logo, colors, footer), tax rates, and discounts
  3. User can generate a PDF of the invoice and email it, or share it via a public link that clients can view without logging in
  4. User can set up recurring invoices on a schedule and track invoice status through draft/sent/viewed/paid/overdue
  5. Admin can connect QuickBooks Online or Xero via OAuth and sync invoices, payment status, and client records bidirectionally
**Plans**: TBD

Plans:
- [ ] 04-01: Invoicing core (invoice generation, line items, branding, tax/discounts, PDF, status tracking)
- [ ] 04-02: Recurring invoices, client-facing view, email delivery
- [ ] 04-03: Accounting sync (QuickBooks/Xero OAuth, invoice sync, payment status sync, client sync)

### Phase 5: Time Capture Extensions
**Goal**: Users have multiple ways to capture time beyond manual entry -- syncing calendar events, using shared kiosk devices, or a simple punch clock interface
**Depends on**: Phase 1 (permissions, notifications for kiosk)
**Requirements**: CAL-01, CAL-02, CAL-03, CAL-04, CAL-05, KIO-01, KIO-02, KIO-03, KIO-04, KIO-05, KIO-06, KIO-07, PCM-01, PCM-02, PCM-03, PCM-04
**Success Criteria** (what must be TRUE):
  1. User can connect their Google or Outlook calendar via OAuth and see calendar events overlaid on the time entry calendar view
  2. User can convert a calendar event into a time entry with one click, and compare planned time (events) vs actual time (entries)
  3. User can drag-resize time entries on the calendar to adjust duration
  4. Admin can create a branded kiosk where team members clock in/out using PIN or QR code, including break tracking
  5. Kiosk displays current status of who is clocked in, and kiosk operates on a shared device without individual user sessions
  6. User can clock in and out from a simplified personal punch clock interface, with breaks tracked and history viewable
**Plans**: TBD

Plans:
- [ ] 05-01: Calendar enhanced (Google/Outlook OAuth, event overlay, event-to-entry conversion, drag-resize)
- [ ] 05-02: Kiosk and clock mode (kiosk CRUD, PIN/QR auth, clock-in/out, breaks, branding, status display)
- [ ] 05-03: Punch clock (simplified clock-in/out UI, break tracking, session history)

### Phase 6: Workforce Management
**Goal**: Organizations can manage time off, plan resource allocation, and track attendance with overtime compliance -- giving managers full visibility into workforce capacity and utilization
**Depends on**: Phase 1 (approval pattern for PTO, weekly_capacity for scheduling), Phase 3 (teams for manager visibility, approval infrastructure validated)
**Requirements**: PTO-01, PTO-02, PTO-03, PTO-04, PTO-05, PTO-06, PTO-07, SCHED-01, SCHED-02, SCHED-03, SCHED-04, SCHED-05, SCHED-06, ATT-01, ATT-02, ATT-03, ATT-04, ATT-05
**Success Criteria** (what must be TRUE):
  1. Admin can create leave policies with accrual rules and holiday calendars, and users can view their current leave balances
  2. User can submit a time-off request and their manager can approve or deny it, with notifications at each step
  3. Approved time off appears on the calendar view and automatically reduces the member's available capacity in scheduling
  4. Manager can assign members to projects with planned hours, view allocations on a visual timeline, and see a capacity heatmap showing over/under-allocated members
  5. Scheduled time vs actual tracked time comparison appears on the project dashboard
  6. Admin can configure overtime rules (daily/weekly thresholds) and users receive notifications when approaching or exceeding limits
  7. Manager can view attendance summary and admin can generate compliance reports for overtime
**Plans**: TBD

Plans:
- [ ] 06-01: PTO and time off (policies, holiday calendars, accruals, request/approval workflow, balances, calendar integration)
- [ ] 06-02: Resource scheduling (assignments, visual timeline, capacity heatmap, scheduled vs actual)
- [ ] 06-03: Attendance and overtime (rules configuration, attendance records, overtime notifications, compliance reports)

### Phase 7: Analytics & Platform Extensions
**Goal**: Organizations get deep insights through advanced reports, can extend the data model with custom fields, and have full visibility into the audit trail -- completing the platform
**Depends on**: Phase 3 (budget/expense data for reports), Phase 4 (invoice/revenue data for profitability), Phase 6 (utilization/capacity data for reports)
**Requirements**: RPT-01, RPT-02, RPT-03, RPT-04, RPT-05, RPT-06, TAG-01, TAG-02, TAG-03, TAG-04, AUD-01, AUD-02, AUD-03, AUD-04
**Success Criteria** (what must be TRUE):
  1. User can view profitability report (revenue vs cost by project) and utilization report (billable vs total hours per member)
  2. User can view budget vs actual report comparing planned vs tracked time and cost
  3. User can save report configurations, export as CSV or PDF, and schedule automated report delivery via email
  4. Admin can define custom fields (text, number, dropdown, boolean) on time entries and users can fill them in when creating/editing entries
  5. User can filter time entries and reports by custom field values
  6. User can browse and search the organization's activity log with filters by entity type, user, and date range, seeing old vs new values for changes
  7. Admin can export audit log data for compliance reporting
**Plans**: TBD

Plans:
- [ ] 07-01: Advanced reporting (profitability, utilization, budget vs actual, saved configs, export, scheduled delivery)
- [ ] 07-02: Tags and custom fields (field definitions, JSONB storage, entry integration, filtering)
- [ ] 07-03: Audit trail UI (searchable activity log, change history detail, compliance export)

## Progress

**Execution Order:**
Phases execute in numeric order: 1 -> 2 -> 3 -> 4 -> 5 -> 6 -> 7

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Shared Foundations | 2/2 | ✓ Complete | 2026-02-10 |
| 2. Weekly Timesheet Grid | 0/2 | Not started | - |
| 3. Governance & Teams | 0/4 | Not started | - |
| 4. Revenue Generation | 0/3 | Not started | - |
| 5. Time Capture Extensions | 0/3 | Not started | - |
| 6. Workforce Management | 0/3 | Not started | - |
| 7. Analytics & Platform Extensions | 0/3 | Not started | - |
