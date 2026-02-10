# Requirements: Solidtime SaaS Platform

**Defined:** 2026-02-10
**Core Value:** Agencies and small businesses can track time, approve timesheets, invoice clients, and monitor project budgets in one place

## v1 Requirements

Requirements for initial release. Each maps to roadmap phases.

### Shared Foundations

- [ ] **FOUND-01**: Notification infrastructure migration (notifications table, notification_preferences on members)
- [ ] **FOUND-02**: Base notification classes with database + mail channels respecting member preferences
- [ ] **FOUND-03**: Notification bell UI component in AppLayout header with polling and mark-as-read
- [ ] **FOUND-04**: Notification API endpoints (list, mark read, mark all read, unread count)
- [ ] **FOUND-05**: Notification preferences in organization settings (per-member, per-type toggles)
- [ ] **FOUND-06**: Shared weekly_capacity column on members and default_weekly_capacity on organizations
- [ ] **FOUND-07**: Modular permissions infrastructure (app/Permissions/ directory, per-feature permission files)
- [ ] **FOUND-08**: DateBoundaryService for timezone-safe date arithmetic across all features
- [ ] **FOUND-09**: CI migration ordering test to prevent timestamp conflicts across feature branches
- [ ] **FOUND-10**: Daily time summary pre-aggregation for reporting performance

### Weekly Timesheet Grid (Feature 00)

- [ ] **TSG-01**: User can view time entries in a weekly grid with day columns (Mon-Sun)
- [ ] **TSG-02**: User can see time entries grouped by project/task rows in the grid
- [ ] **TSG-03**: User can inline edit time entry duration directly in grid cells
- [ ] **TSG-04**: User can add new time entries by clicking empty grid cells
- [ ] **TSG-05**: User can navigate between weeks using previous/next controls
- [ ] **TSG-06**: User can see daily and weekly hour totals in the grid

### Timesheet Approvals (Feature 01)

- [ ] **APPR-01**: User can submit a weekly timesheet for approval
- [ ] **APPR-02**: Manager can view submitted timesheets awaiting approval
- [ ] **APPR-03**: Manager can approve a submitted timesheet, locking the time entries
- [ ] **APPR-04**: Manager can reject a timesheet with comments requesting changes
- [ ] **APPR-05**: User can withdraw a submitted timesheet before it is reviewed
- [ ] **APPR-06**: Approved time entries cannot be edited without first reopening the timesheet
- [ ] **APPR-07**: User receives notification when their timesheet is approved or changes requested
- [ ] **APPR-08**: Manager receives notification when a timesheet is submitted for review
- [ ] **APPR-09**: Admin can configure approval reminders for overdue submissions
- [ ] **APPR-10**: Self-approval is not allowed — reviewer must differ from submitter

### Expense Management (Feature 02)

- [ ] **EXP-01**: User can create an expense entry with amount, category, date, and description
- [ ] **EXP-02**: User can upload a receipt image or PDF to an expense entry
- [ ] **EXP-03**: User can mark an expense as billable and link it to a project/client
- [ ] **EXP-04**: User can submit expenses for approval
- [ ] **EXP-05**: Manager can approve or reject submitted expenses
- [ ] **EXP-06**: Admin can create and manage expense categories for the organization
- [ ] **EXP-07**: User can view their expense history with filtering by date, project, and status
- [ ] **EXP-08**: User receives notification when their expense is approved or rejected

### Budgets & Alerts (Feature 03)

- [ ] **BUD-01**: Admin can set a budget on a project (hours-based, cost-based, or fixed-fee)
- [ ] **BUD-02**: User can view budget burn rate and remaining budget on project dashboard
- [ ] **BUD-03**: User receives notification when a project reaches configurable threshold (75%, 90%, 100%)
- [ ] **BUD-04**: Admin can set budget alert recipients (project members, managers, specific users)
- [ ] **BUD-05**: User can view budget forecast based on current burn rate
- [ ] **BUD-06**: Budget data appears in project list view with visual indicators

### Invoicing (Feature 04)

- [ ] **INV-01**: User can generate an invoice from approved time entries for a project/client
- [ ] **INV-02**: User can add expense line items to an invoice
- [ ] **INV-03**: User can customize invoice with organization branding (logo, colors, footer)
- [ ] **INV-04**: User can apply tax rates and discounts to invoice line items
- [ ] **INV-05**: User can generate a PDF of the invoice for download or email delivery
- [ ] **INV-06**: User can create recurring invoices on a configurable schedule
- [ ] **INV-07**: User can track invoice status (draft, sent, viewed, paid, overdue)
- [ ] **INV-08**: Client can view invoice via a shareable link without logging in

### Calendar Enhanced (Feature 05)

- [ ] **CAL-01**: User can connect Google Calendar via OAuth and see events overlaid on time entries
- [ ] **CAL-02**: User can connect Outlook Calendar via OAuth and see events overlaid on time entries
- [ ] **CAL-03**: User can convert a calendar event into a time entry with one click
- [ ] **CAL-04**: User can drag-resize time entries on the calendar view to adjust duration
- [ ] **CAL-05**: User can see planned time (calendar events) vs actual time (entries) comparison

### Kiosk & Clock Mode (Feature 06)

- [ ] **KIO-01**: Admin can create a kiosk for shared-device clock-in/clock-out
- [ ] **KIO-02**: User can authenticate at a kiosk using a PIN code
- [ ] **KIO-03**: User can authenticate at a kiosk by scanning a QR code
- [ ] **KIO-04**: User can clock in and clock out from the kiosk interface
- [ ] **KIO-05**: User can start and end breaks from the kiosk interface
- [ ] **KIO-06**: Admin can configure kiosk branding (logo, colors) per organization
- [ ] **KIO-07**: Kiosk displays current status of clocked-in members

### PTO & Time Off (Feature 07)

- [ ] **PTO-01**: Admin can create leave policies (vacation, sick, personal) with accrual rules
- [ ] **PTO-02**: Admin can configure holiday calendars for the organization
- [ ] **PTO-03**: User can view their current leave balances by policy type
- [ ] **PTO-04**: User can submit a time-off request for a date range and leave type
- [ ] **PTO-05**: Manager can approve or deny a time-off request
- [ ] **PTO-06**: Approved time off appears on the calendar view and reduces capacity calculations
- [ ] **PTO-07**: User receives notification when their time-off request is approved or denied

### Resource Scheduling (Feature 08)

- [ ] **SCHED-01**: Manager can assign members to projects with planned hours per week
- [ ] **SCHED-02**: Manager can view a visual timeline showing member allocations across projects
- [ ] **SCHED-03**: User can see their own schedule of planned assignments
- [ ] **SCHED-04**: Manager can view capacity heatmap showing over/under-allocated members
- [ ] **SCHED-05**: Scheduled time vs actual tracked time comparison appears on project dashboard
- [ ] **SCHED-06**: PTO and holidays automatically reduce available capacity in scheduling view

### Advanced Reporting (Feature 09)

- [ ] **RPT-01**: User can view profitability report by project showing revenue vs cost
- [ ] **RPT-02**: User can view utilization report showing billable vs total hours per member
- [ ] **RPT-03**: User can view budget vs actual report comparing planned vs tracked time/cost
- [ ] **RPT-04**: User can save report configurations for reuse
- [ ] **RPT-05**: User can export reports as CSV or PDF
- [ ] **RPT-06**: User can schedule automated report delivery via email

### Teams & Groups (Feature 10)

- [ ] **TEAM-01**: Admin can create teams within an organization
- [ ] **TEAM-02**: Admin can assign members to one or more teams
- [ ] **TEAM-03**: Admin can assign projects and clients to teams
- [ ] **TEAM-04**: Admin can enable team scoping to restrict member visibility to their team's data
- [ ] **TEAM-05**: Manager can view reports filtered by their managed teams
- [ ] **TEAM-06**: Admins and owners see all data regardless of team scoping

### Tags & Custom Fields (Feature 11)

- [ ] **TAG-01**: Admin can define custom fields on time entries (text, number, dropdown, boolean)
- [ ] **TAG-02**: User can fill in custom field values when creating or editing time entries
- [ ] **TAG-03**: User can filter time entries and reports by custom field values
- [ ] **TAG-04**: Custom field definitions are scoped per organization

### Punch Clock (Feature 12)

- [ ] **PCM-01**: User can clock in with a single button press from a simplified interface
- [ ] **PCM-02**: User can clock out, automatically creating a time entry for the session
- [ ] **PCM-03**: User can start and end breaks during a clock-in session
- [ ] **PCM-04**: User can view their clock-in/clock-out history

### Audit Trail UI (Feature 13)

- [ ] **AUD-01**: User can view a searchable activity log of changes within the organization
- [ ] **AUD-02**: User can filter the activity log by entity type, user, and date range
- [ ] **AUD-03**: User can see detailed change history for any audited entity (old value vs new value)
- [ ] **AUD-04**: Admin can export audit log data for compliance reporting

### Payments & Accounting Sync (Feature 14)

- [ ] **PAY-01**: Admin can connect organization to QuickBooks Online via OAuth
- [ ] **PAY-02**: Admin can connect organization to Xero via OAuth
- [ ] **PAY-03**: User can sync invoices to connected accounting platform
- [ ] **PAY-04**: Payment status syncs back from accounting platform to invoice status
- [ ] **PAY-05**: User can sync client records between solidtime and accounting platform

### Attendance & Overtime (Feature 15)

- [ ] **ATT-01**: Admin can configure overtime rules (daily threshold, weekly threshold)
- [ ] **ATT-02**: User can view daily attendance records derived from time entries
- [ ] **ATT-03**: User receives notification when approaching or exceeding overtime thresholds
- [ ] **ATT-04**: Manager can view attendance summary report for their team
- [ ] **ATT-05**: Admin can generate compliance reports for overtime and attendance

## v2 Requirements

Deferred to future release. Tracked but not in current roadmap.

### PM Integrations (Feature 16)

- **PMI-01**: Admin can connect Jira workspace and import projects/tasks
- **PMI-02**: Admin can connect Asana workspace and import projects/tasks
- **PMI-03**: Admin can connect Trello boards and import lists/cards as tasks
- **PMI-04**: Time entries sync back to connected PM tool for status updates
- **PMI-05**: User can track time against imported PM tasks without switching tools

## Out of Scope

| Feature | Reason |
|---------|--------|
| Screenshot monitoring | Privacy nightmare, destroys employee trust, GDPR risk, misaligned with agency market |
| GPS tracking | Agencies are knowledge workers, irrelevant to target market, regulatory complexity |
| Full project management | Competing with Asana/Jira/ClickUp is a losing battle — integrate instead |
| Payroll processing | Heavily regulated, jurisdiction-specific, massive liability for solo developer |
| AI auto-categorization | Requires training data and ongoing model maintenance, accuracy expectations hard to meet |
| Real-time chat | Competing with Slack/Teams is futile — integrate for notifications instead |
| White-label multi-tenancy | Enormous auth/theming/billing complexity, solo developer cannot maintain |
| Mobile native app | Web-first with responsive design, native apps deferred to v2+ |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| FOUND-01 | Phase 0 | Pending |
| FOUND-02 | Phase 0 | Pending |
| FOUND-03 | Phase 0 | Pending |
| FOUND-04 | Phase 0 | Pending |
| FOUND-05 | Phase 0 | Pending |
| FOUND-06 | Phase 0 | Pending |
| FOUND-07 | Phase 0 | Pending |
| FOUND-08 | Phase 0 | Pending |
| FOUND-09 | Phase 0 | Pending |
| FOUND-10 | Phase 0 | Pending |
| TSG-01 | TBD | Pending |
| TSG-02 | TBD | Pending |
| TSG-03 | TBD | Pending |
| TSG-04 | TBD | Pending |
| TSG-05 | TBD | Pending |
| TSG-06 | TBD | Pending |
| APPR-01 | TBD | Pending |
| APPR-02 | TBD | Pending |
| APPR-03 | TBD | Pending |
| APPR-04 | TBD | Pending |
| APPR-05 | TBD | Pending |
| APPR-06 | TBD | Pending |
| APPR-07 | TBD | Pending |
| APPR-08 | TBD | Pending |
| APPR-09 | TBD | Pending |
| APPR-10 | TBD | Pending |
| EXP-01 | TBD | Pending |
| EXP-02 | TBD | Pending |
| EXP-03 | TBD | Pending |
| EXP-04 | TBD | Pending |
| EXP-05 | TBD | Pending |
| EXP-06 | TBD | Pending |
| EXP-07 | TBD | Pending |
| EXP-08 | TBD | Pending |
| BUD-01 | TBD | Pending |
| BUD-02 | TBD | Pending |
| BUD-03 | TBD | Pending |
| BUD-04 | TBD | Pending |
| BUD-05 | TBD | Pending |
| BUD-06 | TBD | Pending |
| INV-01 | TBD | Pending |
| INV-02 | TBD | Pending |
| INV-03 | TBD | Pending |
| INV-04 | TBD | Pending |
| INV-05 | TBD | Pending |
| INV-06 | TBD | Pending |
| INV-07 | TBD | Pending |
| INV-08 | TBD | Pending |
| CAL-01 | TBD | Pending |
| CAL-02 | TBD | Pending |
| CAL-03 | TBD | Pending |
| CAL-04 | TBD | Pending |
| CAL-05 | TBD | Pending |
| KIO-01 | TBD | Pending |
| KIO-02 | TBD | Pending |
| KIO-03 | TBD | Pending |
| KIO-04 | TBD | Pending |
| KIO-05 | TBD | Pending |
| KIO-06 | TBD | Pending |
| KIO-07 | TBD | Pending |
| PTO-01 | TBD | Pending |
| PTO-02 | TBD | Pending |
| PTO-03 | TBD | Pending |
| PTO-04 | TBD | Pending |
| PTO-05 | TBD | Pending |
| PTO-06 | TBD | Pending |
| PTO-07 | TBD | Pending |
| SCHED-01 | TBD | Pending |
| SCHED-02 | TBD | Pending |
| SCHED-03 | TBD | Pending |
| SCHED-04 | TBD | Pending |
| SCHED-05 | TBD | Pending |
| SCHED-06 | TBD | Pending |
| RPT-01 | TBD | Pending |
| RPT-02 | TBD | Pending |
| RPT-03 | TBD | Pending |
| RPT-04 | TBD | Pending |
| RPT-05 | TBD | Pending |
| RPT-06 | TBD | Pending |
| TEAM-01 | TBD | Pending |
| TEAM-02 | TBD | Pending |
| TEAM-03 | TBD | Pending |
| TEAM-04 | TBD | Pending |
| TEAM-05 | TBD | Pending |
| TEAM-06 | TBD | Pending |
| TAG-01 | TBD | Pending |
| TAG-02 | TBD | Pending |
| TAG-03 | TBD | Pending |
| TAG-04 | TBD | Pending |
| PCM-01 | TBD | Pending |
| PCM-02 | TBD | Pending |
| PCM-03 | TBD | Pending |
| PCM-04 | TBD | Pending |
| AUD-01 | TBD | Pending |
| AUD-02 | TBD | Pending |
| AUD-03 | TBD | Pending |
| AUD-04 | TBD | Pending |
| PAY-01 | TBD | Pending |
| PAY-02 | TBD | Pending |
| PAY-03 | TBD | Pending |
| PAY-04 | TBD | Pending |
| PAY-05 | TBD | Pending |
| ATT-01 | TBD | Pending |
| ATT-02 | TBD | Pending |
| ATT-03 | TBD | Pending |
| ATT-04 | TBD | Pending |
| ATT-05 | TBD | Pending |

**Coverage:**
- v1 requirements: 104 total
- Mapped to phases: 10 (foundations)
- Unmapped: 94 (awaiting roadmap)

---
*Requirements defined: 2026-02-10*
*Last updated: 2026-02-10 after initial definition*
