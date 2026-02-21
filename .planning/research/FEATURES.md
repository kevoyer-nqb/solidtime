# Feature Research

**Domain:** Time Management SaaS for SMBs/Agencies (5-50 people)
**Researched:** 2026-02-10
**Confidence:** HIGH (multi-source competitor analysis, official feature pages verified)

## Existing Solidtime Baseline

Before categorizing new features, note what solidtime already has:
- Time tracking (timer + manual entry)
- Projects, tasks, clients, tags
- Members & organizations
- Billable rates & billable/non-billable flagging
- Basic reporting (summary + detailed, aggregation, export)
- Dashboard
- Audit trail (via owen-it/laravel-auditing -- already has `Audit` model)
- Import from Toggl, Harvest, Clockify
- Calendar view (Calendar.vue page exists)
- API tokens, Passport auth

**What it lacks** (the 17 features in scope): timesheet approvals, expense management, budgets & alerts, invoicing, calendar sync (Google/Outlook), kiosk/clock mode, PTO/time-off, resource scheduling, advanced reporting, teams & groups, tags/custom fields extensions, punch clock, audit trail UI, payments/accounting sync, attendance/overtime, PM integrations.

---

## Feature Landscape

### Table Stakes (Users Expect These)

Features users assume exist. Missing these = product feels incomplete for the SMB/agency segment. Every major competitor (Harvest, Toggl, Clockify, Everhour) has these.

| # | Feature | Why Expected | Complexity | Notes |
|---|---------|--------------|------------|-------|
| 1 | **Timesheet Approvals** | Every competitor has weekly/monthly approval workflows. Agencies need approved hours before invoicing clients. Clockify Standard, Toggl Premium, Harvest all include this. | MEDIUM | Submit/approve/reject cycle with locking. Needs status on time entries or weekly batches. Solidtime has no approval model yet. |
| 2 | **Budgets & Alerts** | Harvest, Toggl, Everhour, Clockify Pro all offer project budgets with threshold alerts. Agencies track burn rate against retainers. Without this, no way to prevent cost overruns. | MEDIUM | Hours-based and money-based budgets on projects. Alert at configurable thresholds (75%, 90%, 100%). Everhour even blocks time entry on overbudget projects. |
| 3 | **Invoicing** | Harvest, Clockify Standard, Everhour, Paymo all turn tracked time into invoices. This is the #1 monetization bridge for agencies -- time in, invoice out. | HIGH | Line items from time entries + expenses. PDF generation. Client portal or email delivery. Tax/discount handling. Recurring invoices. This is a substantial subsystem. |
| 4 | **Expense Management** | Harvest, Clockify Pro, Everhour, Paymo all have expense tracking alongside time tracking. Agencies need to bill project expenses to clients. | MEDIUM | Expense categories, billable flag, receipt upload (file storage), link to project/client. Feeds into invoicing. |
| 5 | **Advanced Reporting** | Toggl, Harvest, Clockify, Everhour all provide utilization reports, profitability analysis, budget burn, and customizable dashboards. Basic reporting exists but agencies need profitability insights. | HIGH | Utilization rates, billability %, profitability by project/client/member, budget vs actual, custom date ranges, saved report configurations, scheduled email delivery, CSV/PDF export. Solidtime has basic aggregation already. |
| 6 | **Teams & Groups** | Toggl has teams/user groups. Clockify has user groups. Agencies organize by department (design, dev, PM). Needed for filtering reports, assigning permissions, and capacity views. | LOW | Group model with member associations. Used for filtering across reporting, scheduling, permissions. Lightweight data model. |
| 7 | **Tags & Custom Fields** | Clockify Pro has custom fields on time entries. Solidtime already has tags but lacks custom fields. Agencies need to track billable categories, cost centers, departments beyond basic project/task. | MEDIUM | Custom field definitions (text, number, dropdown, boolean) on time entries. Solidtime already has tags (JSON array on TimeEntry). Extend to custom fields with schema definition per org. |
| 8 | **Audit Trail UI** | Solidtime already has `owen-it/laravel-auditing` and an `Audit` model with Filament admin pages. But there is no user-facing audit log. Clockify Enterprise and Replicon have this. For agencies doing client billing, showing change history builds trust. | LOW | Solidtime already captures audit data. This is primarily a frontend feature: a searchable, filterable log view showing who changed what and when. Backend infrastructure exists. |
| 9 | **Payments/Accounting Sync** | Harvest, Toggl, Clockify, Everhour all integrate with QuickBooks Online and Xero. This is expected for any tool that generates invoices. Without it, invoicing is half-built. | HIGH | OAuth integration with QBO and Xero APIs. Sync invoices, payments, clients. Two-way sync for payment status. This is API-heavy and requires maintaining OAuth tokens and handling webhooks. |

### Differentiators (Competitive Advantage)

Features that set the product apart. Not every competitor has all of these. Having them well-implemented gives an edge in the SMB/agency market.

| # | Feature | Value Proposition | Complexity | Notes |
|---|---------|-------------------|------------|-------|
| 10 | **Calendar Sync (Google/Outlook)** | Toggl has Google/Outlook calendar view. TimeCamp auto-imports events as time entries. Most competitors treat this as a read-only overlay. A good bi-directional sync (or even smart one-way import of events as time suggestions) is a differentiator because most tools do it poorly. | MEDIUM | OAuth with Google Calendar API and Microsoft Graph API. Import events as time entry suggestions (not auto-create). One-way sync (calendar to time tracker) is the standard. Bi-directional is rare and risky. |
| 11 | **PTO / Time-Off Management** | Clockify Standard, Hubstaff, Everhour, QuickBooks Time all have leave management. Not all time trackers include this (Harvest does not). For agencies, knowing who is available is critical for project planning. Having this built-in rather than requiring a separate HR tool is valuable. | HIGH | Leave policies (vacation, sick, personal), accrual rules, balance tracking, request/approve workflow, calendar integration, affects capacity planning. This is a mini-HR module. |
| 12 | **Resource Scheduling** | Everhour, Paymo, Float (dedicated tool) have resource planning. Most time trackers do NOT include this -- it is usually a separate product (Float, Runn, Resource Guru). Building a lightweight version directly into a time tracker is a real differentiator for agencies. | HIGH | Visual timeline/calendar showing member allocations to projects. Drag-and-drop assignment. Capacity heatmaps. Requires PTO data and team data to show availability accurately. This is a significant frontend effort. |
| 13 | **Kiosk / Clock Mode** | Clockify Standard, QuickBooks Time, TimeCamp have kiosk mode. Relevant for agencies with office/co-working setups or hybrid teams. Not all competitors have it, but it is increasingly expected. | MEDIUM | Shared-device mode with PIN or QR authentication. Simplified clock-in/clock-out interface. Custom branding. Session management. Separate from the full app -- basically a stripped-down single-purpose UI. |
| 14 | **Attendance & Overtime** | Clockify Standard, QuickBooks Time, Deputy, Replicon have attendance tracking with overtime rules. Valuable for agencies with hourly workers or labor law compliance needs. | MEDIUM | Daily attendance records derived from time entries. Overtime rules (daily >8h, weekly >40h, configurable). Break tracking. Compliance reporting. This extends time entry data with attendance-specific views and rules engine. |
| 15 | **PM Integrations** | Everhour is built as a PM tool overlay (Asana, Jira, ClickUp). Toggl has 100+ integrations. For agencies already using Jira/Asana/ClickUp, being able to track time against their existing tasks without switching tools is highly valuable. | HIGH | Requires building OAuth integrations with Jira, Asana, ClickUp, Monday.com. Task sync (import PM tasks as solidtime tasks). Browser extension for in-app timer. This is a multi-integration effort with ongoing maintenance burden. |
| 16 | **Punch Clock** | QuickBooks Time, Clockify, Hubstaff have simple clock-in/clock-out. Different from kiosk (which is shared-device). Punch clock is personal device clock-in with optional geofencing. | LOW | Simplified UI: big clock-in/clock-out button. Records start/end automatically. Break handling. Can be a mode on the existing mobile/desktop app rather than a separate interface. Geofencing is optional and adds complexity. |

### Anti-Features (Commonly Requested, Often Problematic)

Features that seem good but create problems for a solo-developer team targeting SMB/agencies.

| # | Feature | Why Requested | Why Problematic | Alternative |
|---|---------|---------------|-----------------|-------------|
| A1 | **Screenshot Monitoring** | Hubstaff's marquee feature. Clients want "proof" of work. | Privacy nightmare. Destroys trust with employees. Regulatory risk (GDPR). Agencies do not generally want this -- it signals micromanagement. High storage costs. | Focus on activity-level reporting (hours by project/day) and approval workflows. Trust through transparency, not surveillance. |
| A2 | **GPS Tracking** | QuickBooks Time and Hubstaff offer it for field workers. | Agencies are knowledge workers, not field workers. GPS tracking adds privacy concerns, battery drain, and is irrelevant to the target market. Regulatory complexity (varies by jurisdiction). | If needed for a subset of use cases, geofencing on punch clock is sufficient. Do not build continuous GPS tracking. |
| A3 | **Full Project Management** | ClickUp and Monday.com bundle time tracking into a full PM suite. | Competing with Asana/Jira/ClickUp on PM features is a losing battle. Scope explosion. Solo developer cannot maintain a PM tool. The value is in integrating WITH PM tools, not replacing them. | Build PM integrations (Jira, Asana, ClickUp sync) instead of native PM features. Keep tasks lightweight. |
| A4 | **Payroll Processing** | QuickBooks Time, Deputy, and Hubstaff process payroll. | Payroll is heavily regulated, jurisdiction-specific, and requires banking integrations. Massive liability. Solo developer cannot maintain payroll compliance across jurisdictions. | Export approved timesheets to payroll systems (Gusto, ADP, QuickBooks) via CSV or API integration. Do not handle actual pay disbursement. |
| A5 | **AI Auto-Categorization** | Toggl has AI timeline tracking. TimeCamp auto-tracks apps. | AI features require training data, ongoing model maintenance, and create accuracy expectations that are hard to meet. Desktop activity tracking raises privacy concerns. | Offer smart suggestions (recent tasks, calendar events) rather than auto-categorization. Keep the human in the loop. |
| A6 | **Real-Time Collaboration / Chat** | Slack-like messaging within the time tracker. | Competing with Slack/Teams is futile. Adds massive infrastructure (WebSockets, message storage, notifications). Nobody wants another chat app. | Integrate with Slack/Teams for notifications (approval requests, budget alerts). Do not build a messaging system. |
| A7 | **White-Label / Multi-Tenant Reseller** | Agencies want to give clients their own branded portal. | Multi-tenant white-labeling adds enormous complexity to auth, theming, billing, and data isolation. Solo developer cannot maintain this. | Offer client-facing report sharing (solidtime already has SharedReport) and client portal for invoice viewing. Minimal branding customization (logo upload). |

---

## Feature Dependencies

```
[Tags & Custom Fields]
    (no hard dependencies, but enhances everything)

[Teams & Groups]
    (no hard dependencies, but enhances reporting, scheduling, permissions)

[Timesheet Approvals]
    └──enhances──> [Invoicing] (approved hours feed into invoice generation)
    └──enhances──> [Attendance & Overtime] (approved timesheets = verified attendance)

[Expense Management]
    └──enhances──> [Invoicing] (expenses appear as invoice line items)

[Budgets & Alerts]
    └──requires──> nothing (uses existing project + time entry data)
    └──enhances──> [Advanced Reporting] (budget vs actual reports)
    └──enhances──> [Resource Scheduling] (capacity informed by budget remaining)

[Invoicing]
    └──requires──> [Expense Management] (for complete invoices, not strictly required)
    └──enhances──> [Payments/Accounting Sync] (invoices sync to QBO/Xero)

[Payments/Accounting Sync]
    └──requires──> [Invoicing] (nothing to sync without invoices)

[Calendar Sync]
    └──requires──> nothing (reads from external calendars)
    └──enhances──> [Resource Scheduling] (shows meetings/availability)
    └──enhances──> [PTO/Time-Off] (shows leave on calendar)

[PTO / Time-Off]
    └──requires──> [Teams & Groups] (leave policies per team, manager approval)
    └──enhances──> [Resource Scheduling] (availability calculation)
    └──enhances──> [Attendance & Overtime] (PTO days excluded from attendance)

[Resource Scheduling]
    └──requires──> [Teams & Groups] (must know team structure)
    └──requires──> [PTO / Time-Off] (must know availability)
    └──enhances──> [Advanced Reporting] (utilization & capacity reports)

[Advanced Reporting]
    └──requires──> nothing (uses existing data)
    └──enhanced-by──> [Budgets], [Teams], [Approvals], [Resource Scheduling]

[Kiosk / Clock Mode]
    └──requires──> nothing (uses existing time entry API)
    └──enhances──> [Attendance & Overtime] (clock-in/out feeds attendance)
    └──enhances──> [Punch Clock] (kiosk is the shared-device version of punch clock)

[Punch Clock]
    └──requires──> nothing (simplified time entry creation)
    └──enhances──> [Attendance & Overtime]

[Attendance & Overtime]
    └──requires──> nothing strictly (derives from time entries)
    └──enhanced-by──> [Timesheet Approvals], [Punch Clock], [Kiosk], [PTO]

[PM Integrations]
    └──requires──> nothing (uses existing project/task model)
    └──enhances──> [Budgets] (PM tasks mapped to budget-tracked projects)

[Audit Trail UI]
    └──requires──> nothing (backend audit data already captured by solidtime)
```

### Dependency Notes

- **Invoicing requires Expense Management for completeness:** Agencies bill both time and expenses. Shipping invoicing without expenses means incomplete invoices. Build expenses first or in parallel.
- **Payments/Accounting Sync requires Invoicing:** Without invoices, there is nothing meaningful to sync to QBO/Xero. Build invoicing first.
- **Resource Scheduling requires Teams + PTO:** Scheduling without knowing team structure and availability is useless. Build teams and PTO first.
- **PTO benefits from Teams & Groups:** Leave policies are typically per-team with manager approval chains. Teams should exist first.
- **Timesheet Approvals enhance Invoicing:** The workflow is: track time, approve timesheets, then generate invoices from approved time. Approvals should precede or be concurrent with invoicing.
- **Budgets & Alerts is standalone:** Can be built at any time since it only uses existing project and time entry data.
- **Audit Trail UI is standalone:** Backend data already exists. Pure frontend feature.

---

## MVP Definition

### Launch With (v1 -- Phase 1)

Minimum viable feature set to charge for the product. These features together form the core "time-to-invoice" pipeline that agencies need.

- [x] **Timesheet Approvals** -- The #1 missing feature for any team-based time tracker. Without approvals, managers cannot trust the data enough to bill clients.
- [x] **Budgets & Alerts** -- Low-dependency, high-value. Agencies must know when projects are going over budget. Uses existing data.
- [x] **Teams & Groups** -- Lightweight but foundational. Required for approvals (who approves whom), reporting (filter by team), and later for scheduling.
- [x] **Tags & Custom Fields** -- Extends existing tag system. Enables agencies to slice data by department, cost center, or any custom dimension.
- [x] **Audit Trail UI** -- Backend exists. Low effort to surface. Builds enterprise trust.
- [x] **Punch Clock** -- Low complexity. Simplified clock-in/clock-out mode on existing app.

### Add After Validation (v1.x -- Phase 2)

Features that complete the billing pipeline and add scheduling capability.

- [ ] **Expense Management** -- Trigger: users request expense line items on invoices. Build before or alongside invoicing.
- [ ] **Invoicing** -- Trigger: users are exporting time data to create invoices manually in other tools. This is a substantial build.
- [ ] **Advanced Reporting** -- Trigger: users ask for utilization, profitability, and budget-vs-actual reports beyond basic time summaries.
- [ ] **Attendance & Overtime** -- Trigger: users with hourly employees need overtime rules and attendance views.
- [ ] **Calendar Sync (Google/Outlook)** -- Trigger: users want meeting time auto-suggested as time entries.
- [ ] **Kiosk / Clock Mode** -- Trigger: users with shared office devices need a shared clock-in station.

### Future Consideration (v2+ -- Phase 3)

Features to defer until product-market fit is established and revenue supports the maintenance burden.

- [ ] **PTO / Time-Off** -- Why defer: Mini-HR module with accrual rules, balances, request/approval. Substantial scope. Build after teams & approvals are proven.
- [ ] **Resource Scheduling** -- Why defer: Largest frontend effort. Requires teams + PTO to be useful. Build after core billing pipeline is validated.
- [ ] **Payments/Accounting Sync** -- Why defer: Requires invoicing to exist first. OAuth integration with QBO/Xero is maintenance-heavy. Build after invoicing is stable.
- [ ] **PM Integrations** -- Why defer: Each integration (Jira, Asana, ClickUp) is a separate maintenance burden. Build the most-requested one first after core features stabilize.

---

## Feature Prioritization Matrix

| Feature | User Value | Impl. Cost | Revenue Impact | Priority |
|---------|------------|------------|----------------|----------|
| Timesheet Approvals | HIGH | MEDIUM | HIGH (gate to billing) | **P1** |
| Budgets & Alerts | HIGH | MEDIUM | HIGH (prevents revenue leaks) | **P1** |
| Teams & Groups | MEDIUM | LOW | MEDIUM (enables other features) | **P1** |
| Tags & Custom Fields | MEDIUM | MEDIUM | MEDIUM (data flexibility) | **P1** |
| Audit Trail UI | MEDIUM | LOW | LOW (trust/compliance) | **P1** |
| Punch Clock | MEDIUM | LOW | LOW (convenience) | **P1** |
| Expense Management | HIGH | MEDIUM | HIGH (billing completeness) | **P2** |
| Invoicing | HIGH | HIGH | HIGH (direct revenue feature) | **P2** |
| Advanced Reporting | HIGH | HIGH | HIGH (decision-making) | **P2** |
| Attendance & Overtime | MEDIUM | MEDIUM | MEDIUM (compliance) | **P2** |
| Calendar Sync | MEDIUM | MEDIUM | LOW (convenience) | **P2** |
| Kiosk / Clock Mode | MEDIUM | MEDIUM | LOW (niche use case) | **P2** |
| PTO / Time-Off | HIGH | HIGH | MEDIUM (reduces tool sprawl) | **P3** |
| Resource Scheduling | HIGH | HIGH | HIGH (agency differentiator) | **P3** |
| Payments/Accounting Sync | HIGH | HIGH | MEDIUM (workflow completion) | **P3** |
| PM Integrations | HIGH | HIGH | MEDIUM (reduces friction) | **P3** |

**Priority key:**
- **P1:** Must have for first paid release. Foundation features that enable the billing pipeline and team management.
- **P2:** Should have. Complete the billing cycle and add operational features. Build as fast-follow.
- **P3:** Nice to have. High-value but high-complexity features requiring stable foundations. Future consideration.

---

## Competitor Feature Analysis

| Feature | Harvest | Toggl Track | Clockify | Everhour | QuickBooks Time | Paymo |
|---------|---------|-------------|----------|----------|-----------------|-------|
| **Time Tracking** | Timer + manual | Timer + manual + auto-track | Timer + manual | Timer + manual (PM overlay) | Timer + manual + GPS | Timer + manual + auto-track |
| **Timesheet Approvals** | Yes | Yes (Premium) | Yes (Standard) | No (relies on PM tool) | Yes | Yes (Business) |
| **Expense Management** | Yes | No native | Yes (Pro) | Yes | No native | Yes |
| **Budgets & Alerts** | Yes (hours + money) | Yes (hours + money) | Yes (Pro) | Yes (hours + money, can block) | No | Yes |
| **Invoicing** | Yes (with payments) | Yes (Premium) | Yes (Standard) | Yes (with QBO/Xero export) | No (uses QuickBooks) | Yes (with payments) |
| **Calendar Sync** | No native | Yes (Google + Outlook view) | No native | No native | No native | No native |
| **Kiosk / Clock Mode** | No | No | Yes (Standard) | No | Yes | No |
| **PTO / Time-Off** | No | No | Yes (Standard) | Yes | Yes | Yes (Business) |
| **Resource Scheduling** | No | No (separate Toggl Plan) | Yes (Pro) | Yes | No | Yes |
| **Advanced Reporting** | Yes (good) | Yes (excellent) | Yes (Pro) | Yes (excellent) | Basic | Yes (good) |
| **Teams & Groups** | Basic | Yes | Yes | Via PM tool | Yes | Yes |
| **Custom Fields** | No | No | Yes (Pro) | No | No | No |
| **Punch Clock** | No | No | Yes | No | Yes (with geo) | No |
| **Audit Trail** | Activity log | Member audits | Yes (Enterprise) | No | No | No |
| **Accounting Sync** | QBO + Xero | QBO + Xero | QBO + Xero + more | QBO + Xero | Native (QuickBooks) | No native |
| **Attendance/Overtime** | No | No | Yes (Standard) | No | Yes | No |
| **PM Integrations** | Asana, Trello, Basecamp | 100+ (Jira, Asana, etc.) | 68+ | Deep (Asana, Jira, ClickUp) | Limited | Limited |

### Competitive Positioning Insight

**Harvest** is the gold standard for agencies: time tracking + invoicing + expenses + accounting sync. But it lacks kiosk, PTO, scheduling, custom fields, and audit trail. It is also a mature product with high prices.

**Clockify** is the feature-complete budget option: free tier + paid tiers that add approvals, invoicing, expenses, kiosk, PTO, scheduling, custom fields, audit trail. It is the closest to what solidtime should become. Study its tier structure.

**Toggl Track** excels at UX and reporting but lacks expenses, PTO, kiosk, attendance. It separates scheduling into Toggl Plan (separate product).

**Everhour** is the PM-overlay specialist: deep integrations with Asana/Jira/ClickUp but no standalone approval workflow, kiosk, or attendance.

**Our opportunity:** Solidtime can compete by being the open-source-core alternative to Clockify with Harvest-level billing features. The combination of open-source transparency + full billing pipeline + resource scheduling is unique in the market.

---

## Complexity Estimates (Solo Developer)

Rough effort estimates for each feature, accounting for backend + frontend + tests.

| Feature | Backend | Frontend | Tests | Total | Risk |
|---------|---------|----------|-------|-------|------|
| Timesheet Approvals | 20h | 16h | 8h | 44h | Low -- well-understood pattern |
| Budgets & Alerts | 16h | 12h | 6h | 34h | Low -- straightforward |
| Teams & Groups | 12h | 8h | 4h | 24h | Low -- simple CRUD |
| Tags & Custom Fields | 20h | 16h | 8h | 44h | Medium -- schema flexibility is tricky |
| Audit Trail UI | 4h | 16h | 4h | 24h | Low -- backend exists |
| Punch Clock | 4h | 12h | 4h | 20h | Low -- simplified UI |
| Expense Management | 20h | 20h | 8h | 48h | Medium -- file uploads, categories |
| Invoicing | 40h | 32h | 12h | 84h | High -- PDF generation, line items, tax handling |
| Advanced Reporting | 24h | 32h | 8h | 64h | Medium -- query complexity, chart rendering |
| Attendance & Overtime | 16h | 16h | 8h | 40h | Medium -- rules engine for overtime |
| Calendar Sync | 20h | 12h | 6h | 38h | Medium -- OAuth, API rate limits |
| Kiosk / Clock Mode | 8h | 20h | 6h | 34h | Medium -- separate auth flow, branding |
| PTO / Time-Off | 24h | 24h | 10h | 58h | High -- accrual rules, balance tracking |
| Resource Scheduling | 24h | 40h | 10h | 74h | High -- drag-and-drop calendar, complex UI |
| Payments/Accounting Sync | 40h | 12h | 10h | 62h | High -- external API integration, OAuth maintenance |
| PM Integrations (per tool) | 24h | 8h | 8h | 40h each | High -- per integration, ongoing maintenance |

**Total estimated effort (all 17 features):** ~750-850 hours (~4-5 months full-time solo developer)

---

## Sources

### Competitor Official Feature Pages (MEDIUM-HIGH confidence)
- [Harvest Features](https://www.getharvest.com/features) -- Time tracking, invoicing, expenses, budgets, reporting
- [Toggl Track Features](https://toggl.com/track/features/) -- Time tracking, reporting, budgets, integrations, calendar sync
- [Clockify Paid Features](https://clockify.me/paid-features) -- Tiered feature breakdown: approvals, invoicing, expenses, kiosk, PTO, scheduling, custom fields, audit log
- [Everhour](https://everhour.com) -- Budgets, invoicing, resource scheduling, reporting, PM integrations
- [Paymo Features](https://www.paymoapp.com/complete-feature-list/) -- Invoicing, expenses, resource scheduling, time tracking
- [QuickBooks Time (TSheets)](https://quickbooks.intuit.com/time-tracking/) -- Approvals, PTO, overtime, geofencing, kiosk
- [Hubstaff](https://hubstaff.com/) -- Activity monitoring, screenshots, GPS, invoicing, PTO

### Comparison Articles (MEDIUM confidence)
- [Everhour vs Harvest vs Toggl comparison](https://everhour.com/blog/online-time-tracking-software-everhour-vs-harvest-vs-toggl/) -- Feature comparison matrix
- [Clockify vs Harvest 2026](https://toggl.com/blog/clockify-vs-harvest) -- Invoicing, expenses, approvals comparison
- [Best Agency Time Tracking 2026](https://productive.io/blog/best-agency-time-tracking-software/) -- Agency-specific feature requirements
- [Best Time Tracking with Invoicing 2026](https://thecfoclub.com/tools/best-time-tracking-and-invoicing-software/) -- Invoicing feature comparison

### Industry Analysis (MEDIUM confidence)
- [Connecteam competitor reviews](https://connecteam.com/reviews/) -- Detailed feature breakdowns for Harvest, Toggl, Clockify, Hubstaff, QuickBooks Time, Replicon, Deputy, Paymo
- [Capterra listings](https://www.capterra.com/) -- Feature lists and user reviews for all major competitors
- [Time clock kiosk comparison 2026](https://connecteam.com/best-time-clock-kiosk-apps/) -- Kiosk feature analysis
- [PTO tracking software 2026](https://peoplemanagingpeople.com/tools/best-leave-management-software/) -- Leave management feature comparison

### Codebase Analysis (HIGH confidence)
- Solidtime source code at `/home/keven/Documents/solidtime-analysis/` -- Verified existing models, controllers, services, and pages

---
*Feature research for: Time Management SaaS (SMB/Agency)*
*Researched: 2026-02-10*
