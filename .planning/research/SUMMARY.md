# Project Research Summary

**Project:** Solidtime Enterprise Features
**Domain:** Time Management SaaS for SMBs/Agencies (5-50 people)
**Researched:** 2026-02-10
**Confidence:** MEDIUM-HIGH

## Executive Summary

Solidtime is adding 17 enterprise features to an existing open-source time tracking platform built with Laravel 12, Vue 3, Inertia.js, and PostgreSQL. Research reveals this is a well-established domain dominated by mature competitors (Harvest, Clockify, Toggl, Everhour) with clear feature expectations and proven architectural patterns. The recommended approach follows standard Laravel conventions with strategic additions: state machine pattern for approval workflows, Laravel's first-party notification and broadcasting systems for real-time updates, official vendor SDKs for external integrations (Google/Microsoft/Stripe/Xero/QuickBooks), and PostgreSQL JSONB for custom fields.

The critical path runs through shared infrastructure first (notifications, approval pattern, permissions, date handling), then governance features (timesheet approvals, expenses, budgets), followed by revenue features (invoicing), time capture (calendar sync, kiosk), and workforce management (PTO, scheduling). This order exercises foundational patterns early, validates them with multiple features, then layers more complex integrations on proven infrastructure. The existing codebase already has 80% of needed infrastructure (audit trail, file storage, PDF generation, API architecture), so new features extend rather than replace.

Key risks center on cross-feature integration complexity: timezone handling across 10+ date-sensitive features, approval workflow race conditions affecting 3 features simultaneously, notification volume destroying UX, query performance degradation as features join more tables, and permission explosion making roles unmanageable. These are all preventable through disciplined shared foundation work in Phase 0, but skipping or rushing foundational patterns will create compounding technical debt across all 17 features. The 2,093-hour estimate is realistic if foundational patterns are built correctly first.

## Key Findings

### Recommended Stack

The existing Laravel 12 + Vue 3 + Inertia.js + PostgreSQL stack is solid and proven. Research identified 12 additional packages needed for enterprise features, all first-party or industry-standard libraries with active maintenance. Most critical additions: `spatie/laravel-model-states` for approval state machines (though the architecture research recommends a simpler enum-based pattern instead), `laravel/reverb` for real-time WebSocket notifications, official vendor SDKs for Google Calendar/Microsoft Graph/Stripe/Xero/QuickBooks integrations, and `spatie/laravel-medialibrary` for expense receipt uploads. No architectural pivots needed -- the stack supports everything required.

**Core technologies:**
- `laravel/reverb` (^1.7): WebSocket server for real-time notifications — Laravel's official solution, uses Pusher protocol so existing Laravel Echo works out-of-box
- `spatie/laravel-model-states` (^2.12): State machine for approval workflows — industry standard, but architecture recommends simpler enum pattern for this use case
- `google/apiclient` (^2.19) + `microsoft/microsoft-graph` (^2.56): Calendar sync — official vendor SDKs, most reliable option for OAuth2 flows and token management
- `laravel/cashier` (^16.2): Stripe integration for invoice payments — first-party Laravel billing package
- `spatie/laravel-medialibrary` (^11.18): File uploads for receipts/attachments — handles S3 storage, polymorphic associations, thumbnail generation
- `spatie/laravel-activitylog` (^4.11): User action logging — complements existing owen-it/laravel-auditing (model changes) with user-action tracking

**Critical PHP version note:** Project pins PHP 8.3, but several recommended packages now require PHP 8.4. Recommendation is to upgrade to PHP 8.4 before starting enterprise features to unlock latest versions and avoid ongoing version-pinning complexity.

### Expected Features

Feature research analyzed 6 major competitors (Harvest, Toggl, Clockify, Everhour, QuickBooks Time, Paymo) and categorized 17 features into table stakes (users expect), differentiators (competitive advantage), and anti-features (commonly requested but problematic).

**Must have (table stakes):**
- Timesheet approvals — every competitor has weekly/monthly approval workflows, agencies need this before billing clients
- Budgets and alerts — project budgets with threshold notifications prevent cost overruns, Harvest/Toggl/Clockify all include this
- Invoicing — turn tracked time into invoices, the #1 monetization bridge for agencies
- Expense management — agencies bill project expenses to clients, needs receipt uploads and approval workflow
- Advanced reporting — utilization rates, profitability analysis, budget-vs-actual, agencies need these insights beyond basic time summaries
- Teams and groups — organize by department, filter reports, assign permissions
- Tags and custom fields — agencies need to track billable categories, cost centers, departments beyond basic project/task
- Audit trail UI — backend already exists (owen-it/laravel-auditing), need user-facing log view
- Payments/accounting sync — Harvest/Toggl/Clockify all integrate with QuickBooks Online and Xero, expected for any invoicing tool

**Should have (competitive):**
- Calendar sync (Google/Outlook) — most competitors have read-only overlay, good bi-directional sync is a differentiator
- PTO/time-off management — knowing who is available is critical for project planning, not all time trackers include this
- Resource scheduling — visual timeline showing member allocations, most time trackers DON'T have this (separate products like Float)
- Kiosk/clock mode — shared-device PIN/QR authentication for office/co-working setups
- Attendance and overtime — daily attendance records with overtime rules, valuable for agencies with hourly workers
- PM integrations — Everhour is built as PM overlay (Asana/Jira/ClickUp), high value for agencies already using these tools
- Punch clock — simple clock-in/clock-out, different from kiosk (personal device vs shared device)

**Defer (v2+):**
- Screenshot monitoring — privacy nightmare, destroys trust, regulatory risk
- GPS tracking — agencies are knowledge workers not field workers, privacy concerns
- Full project management — competing with Asana/Jira is losing battle, integrate WITH them instead
- Payroll processing — heavily regulated, massive liability, export to payroll systems instead
- AI auto-categorization — requires training data and model maintenance, keep human in the loop
- White-label/multi-tenant reseller — enormous complexity for solo developer

### Architecture Approach

The existing architecture is clean layered monolith: Vue 3 pages with Pinia stores, API client (Zodios from OpenAPI), Laravel controllers with permission checks, service layer for business logic, Eloquent models with UUID primary keys and organization-scoped relationships. The 17 features introduce six new architectural concerns not in current codebase: notification infrastructure (cross-cutting), approval state machine (timesheets/expenses/PTO), file upload pipeline (receipts/attachments), external OAuth integrations (calendar/accounting), kiosk alternate auth (PIN/QR on shared devices), and scheduling/capacity system (resource assignments).

Research recommends extending existing patterns rather than introducing new frameworks. Use Laravel's built-in notifications (database + mail + broadcast channels), enum-based state machine instead of a library (5 states, 5 transitions is simple enough), standard Laravel file uploads with S3 (already configured), service contracts for external integrations (mirrors existing BillingContract pattern), custom Laravel guard for kiosk auth, and event-driven side effects to keep features loosely coupled.

**Major components:**
1. **NotificationService** — dispatch via mail/database/broadcast channels, used by 5+ features, needs rate limiting and batching from day 1
2. **ApprovalService with HasApprovalWorkflow trait** — shared state machine (draft→submitted→approved/rejected→locked), used by timesheets/expenses/PTO, must use database locking to prevent race conditions
3. **IntegrationConnection model** — OAuth token storage (encrypted) for Google/Outlook/Xero/QuickBooks, handles token refresh lifecycle
4. **DateBoundaryService** — shared timezone-aware date arithmetic for week boundaries, day boundaries, period containment (prevents timezone bugs across 10+ date-sensitive features)
5. **File upload pipeline** — lightweight Upload model (polymorphic), validates MIME types, stores to S3, used by expenses and invoicing
6. **Kiosk auth guard** — custom Laravel guard authenticating org via long-lived token, individual members via PIN per-action, no persistent sessions

### Critical Pitfalls

Research identified 7 critical pitfalls (data corruption, state machine races, notification avalanche, permission explosion, migration ordering, OAuth lifecycle, query performance) plus 4 technical debt patterns, 6 integration gotchas, 7 performance traps, 7 security mistakes, and 6 UX pitfalls.

1. **Timezone corruption across features** — Time entries, approvals, PTO, overtime, scheduling all do date arithmetic independently; one feature calculates "this week" in user timezone, another in org timezone; DST transitions create 23/25-hour days breaking totals. **Avoid by:** Create shared DateBoundaryService in Phase 0 that all features use, store IANA timezone identifiers not offsets, test with DST transition fixtures.

2. **Approval workflow state machine race conditions** — Two managers approve same timesheet simultaneously or user withdraws while manager approves; without database locking, both reads succeed before writes, resulting in corrupted state. **Avoid by:** Every state transition uses DB transaction with `lockForUpdate()`, validate expected "from" state inside transaction (optimistic concurrency), queue all notification side effects with `afterCommit`.

3. **Notification avalanche destroying UX** — Budget alerts fire for every threshold-crossing entry (50/day), timesheet reminders compound with team size, notifications table grows to millions of rows making queries slow, users disable all notifications. **Avoid by:** Implement rate limiting (budget alert max once per threshold per project per day), batch notifications (aggregate within 5-minute window), queue all notifications, add `(notifiable_type, notifiable_id, read_at)` index, prune notifications older than 90 days.

4. **Permission explosion** — 17 features × 4-8 permissions each = 80-120 permissions; role configuration becomes checkbox grid nobody understands; permission checks scattered across controllers with no audit capability. **Avoid by:** Group permissions into capability bundles for UI ("Financial Management" = invoices:view + expenses:approve + budgets:view), implement permission inheritance (Admin inherits Manager inherits Employee), log every permission denial, add permission matrix test verifying every endpoint is protected.

5. **OAuth token lifecycle management failure** — Access tokens expire after 1 hour, refresh tokens get revoked (Google 50-token-per-user limit), Outlook webhooks expire after 7 days; sync jobs fail silently, users see stale data. **Avoid by:** Build CalendarConnectionHealthService checking connections daily, surface broken connections prominently in UI, renew Microsoft Graph subscriptions every 5 days, implement exponential backoff for sync retries, store token metadata (last_successful_sync_at, consecutive_failures).

## Implications for Roadmap

Based on research, the feature dependencies and architectural requirements dictate a clear phase structure. Shared foundations must come first (notification infrastructure, approval pattern, modular permissions, date handling), then governance features that exercise those patterns (timesheet approvals, expenses, budgets), followed by revenue features (invoicing depends on time+expense data), time capture extensions (calendar sync, kiosk), workforce management (PTO, scheduling depend on approval pattern and capacity data), and finally extended platform features.

### Phase 0: Shared Foundations (FOUND-001 to FOUND-008+)
**Rationale:** 80% of features depend on notification infrastructure, approval pattern, or date arithmetic. Building these in Phase 0 prevents every feature from solving the same problems differently. Architecture research identifies this as the critical path.
**Delivers:** BaseNotification with rate limiting/batching, HasApprovalWorkflow trait with database locking, modular permissions infrastructure, DateBoundaryService for timezone-safe date arithmetic, migration ordering conventions, weekly_capacity schema extension
**Addresses:** Pitfalls #1 (timezone), #2 (approval races), #3 (notification avalanche), #4 (permission explosion), #5 (migration ordering)
**Research flag:** Standard Laravel patterns. No phase-specific research needed — architectural patterns are well-established.

### Phase 1: Governance & Control (Timesheet Approvals, Expenses, Budgets, Teams)
**Rationale:** These four features exercise the shared approval pattern (timesheets, expenses), notification system (budget alerts), and team scoping (used by all). Building them validates foundational infrastructure with real features before layering more complexity. Feature research identifies timesheets and budgets as highest user value.
**Delivers:** Weekly timesheet approval workflow, expense management with receipt uploads, project budget tracking with threshold alerts, team/group organization
**Uses:** HasApprovalWorkflow trait (timesheets, expenses), BaseNotification (budget alerts, approval notifications), DateBoundaryService (timesheet week boundaries), spatie/laravel-medialibrary (expense receipts)
**Avoids:** Pitfalls #1, #2, #3 by exercising shared foundations
**Research flag:** EXPENSE MANAGEMENT needs receipt upload security research (file validation, antivirus scanning, pre-signed URLs). BUDGET ALERTS needs burn rate calculation and forecasting research.

### Phase 2: Revenue Generation (Invoicing, Payments/Accounting Sync)
**Rationale:** Invoicing depends on time entries + expenses being solid. Accounting sync depends on invoicing existing. Feature research identifies this as the "time-to-invoice pipeline" agencies need. Billing/invoicing PRD reviewed as HIGH complexity, needs dedicated focus.
**Delivers:** Invoice generation from approved time + expenses, PDF invoices (using existing Gotenberg), payment links (Stripe via Laravel Cashier), QuickBooks/Xero sync
**Uses:** laravel/cashier (payments), Gotenberg (PDFs), quickbooks/v3-php-sdk + xeroapi/xero-php-oauth2 (accounting sync), IntegrationConnection model (OAuth tokens)
**Implements:** Service contract pattern for accounting integrations (XeroSyncService, QuickBooksSyncService extending AccountingSyncContract)
**Avoids:** Pitfall #6 (OAuth lifecycle) through connection health monitoring
**Research flag:** INVOICING needs tax calculation research (multi-jurisdiction, rounding), invoice numbering sequence patterns (database sequences vs app logic), multi-currency handling if needed. ACCOUNTING SYNC needs QuickBooks/Xero API deep-dive (entity mapping, conflict resolution, webhook handling).

### Phase 3: Time Capture Extensions (Calendar Sync, Kiosk Mode)
**Rationale:** Both are architecturally independent from governance/revenue. Calendar sync introduces OAuth integration infrastructure (reused by accounting sync in Phase 2, so actually should be reconsidered for ordering). Kiosk introduces alternate auth pattern. Feature research shows these as "should have" competitive features.
**Delivers:** Google Calendar + Outlook integration (OAuth, event import as time entry suggestions), kiosk mode (shared device, PIN/QR auth)
**Uses:** google/apiclient + microsoft/microsoft-graph (calendar APIs), custom Laravel guard (kiosk), vite-plugin-pwa (kiosk offline capability), laravel/reverb (kiosk real-time updates)
**Implements:** CalendarSyncService with token refresh, CalendarConnectionHealthService, KioskAuthService with PIN verification
**Avoids:** Pitfall #6 (OAuth lifecycle) through health checks and degradation handling
**Research flag:** CALENDAR SYNC needs deep research (Google Calendar push notifications vs polling, Microsoft Graph delta queries, conflict resolution when calendar event overlaps existing time entry, recurring event handling with simshaun/recurr). KIOSK MODE needs UX research (break tracking patterns, shift display, multi-location handling).

### Phase 4: Workforce Management (PTO, Resource Scheduling, Attendance/Overtime)
**Rationale:** PTO depends on approval pattern (Phase 0) and team structure (Phase 1). Scheduling depends on weekly_capacity (Phase 0), team data (Phase 1), and PTO data. Attendance/overtime depends on time entry data being solid. Architecture research identifies these as high-complexity features requiring stable foundations.
**Delivers:** PTO/time-off with accrual policies, request/approval workflow, balance tracking; resource scheduling visual timeline; attendance tracking with overtime rules
**Uses:** HasApprovalWorkflow (PTO requests), DateBoundaryService (overtime weekly boundaries, accrual calculations), weekly_capacity (scheduling capacity views)
**Implements:** ScheduleService for capacity calculation, PtoAccrualService for balance tracking, AnalyticsService for utilization reporting
**Avoids:** Pitfall #1 (timezone) through DateBoundaryService for overtime weekly resets
**Research flag:** PTO needs accrual calculation research (pro-rated for mid-period hires, carryover rules, policy templates). RESOURCE SCHEDULING needs Gantt/timeline library evaluation (hy-vue-gantt vs custom with @tanstack/vue-table), drag-and-drop patterns, capacity heatmap visualization. ATTENDANCE needs overtime rules engine research (daily >8h, weekly >40h, configurable thresholds).

### Phase 5: Analytics & Organization (Advanced Reporting, Teams Enhancement)
**Rationale:** Analytics aggregates data from all previous features — build last so there's data to aggregate. Teams modifies org-scoping queries, safest to do after all features have basic queries working. Feature research identifies advanced reporting as HIGH user value.
**Delivers:** Profitability reports (time + rates + costs), utilization reports (capacity vs actual), budget-vs-actual, scheduled-vs-actual, custom dashboards
**Uses:** ECharts + vue-echarts (already installed), Maatwebsite Excel + League CSV (export), DateBoundaryService (period boundaries)
**Implements:** Pre-aggregation via daily_time_summaries table (materialized view or triggered table) to prevent query timeout
**Avoids:** Pitfall #7 (query performance) through pre-aggregation, indexed queries, result caching, time range limits
**Research flag:** ADVANCED REPORTING needs deep research (profitability calculation with fallback rate logic, utilization formulas, materialized view vs triggered table trade-offs, query optimization patterns, multi-dimensional aggregation performance).

### Phase 6: Extended Platform (Tags/Custom Fields, Punch Clock, Audit Trail UI, PM Integrations)
**Rationale:** These are additive with minimal cross-dependencies. Tags/custom fields extend existing tag system. Punch clock is simplified time entry. Audit trail backend exists, needs frontend. PM integrations are per-provider, can be built in parallel or prioritized by demand.
**Delivers:** Custom field definitions with JSONB storage, punch clock simplified UI, audit trail searchable UI, Jira/Asana/ClickUp/Trello integrations
**Uses:** PostgreSQL JSONB (custom fields), existing API (punch clock), existing audit data (audit trail), lesstif/php-jira-rest-client + Guzzle (PM integrations)
**Implements:** Custom field schema definitions per org, WebhookService for PM tool webhooks, activity log UI with filtering
**Avoids:** Performance penalty by using JSONB with GIN indexes instead of EAV pattern
**Research flag:** PM INTEGRATIONS need per-provider research (Jira REST API + webhook setup, Asana OAuth + webhook events, Trello API limitations, ClickUp API patterns). AUDIT TRAIL needs redaction research (sensitive data handling in logs).

### Phase Ordering Rationale

- **Foundations first (Phase 0):** Notification infrastructure, approval pattern, permissions, date handling are prerequisites for 80% of features. Every feature that delays these ends up re-implementing them inconsistently.
- **Governance validates foundations (Phase 1):** Timesheet approvals, expenses, budgets all exercise approval pattern, notifications, and date arithmetic. Building 3 features that use shared infrastructure early validates the patterns work and identifies gaps.
- **Revenue requires governance (Phase 2):** Invoicing needs approved time entries and expenses. Building invoicing before approvals creates incomplete invoices. Architecture research confirms this dependency.
- **Time capture is independent (Phase 3):** Calendar sync and kiosk don't depend on governance/revenue, but calendar sync's OAuth infrastructure is needed by accounting sync in Phase 2 — consider reordering Phase 2 and 3, or splitting calendar sync into Phase 2.
- **Workforce needs capacity data (Phase 4):** PTO and scheduling both depend on weekly_capacity from Phase 0 and team structure from Phase 1. Building these early creates rework.
- **Analytics aggregates everything (Phase 5):** Building reporting before features exist means no data to report on. Build near end.
- **Extended features are additive (Phase 6):** Low cross-dependencies, can be prioritized based on user demand, parallelized if needed.

### Research Flags

Phases likely needing deeper research during planning:
- **Phase 1 (Governance):** Expense receipt upload security (file validation, antivirus), budget burn rate forecasting algorithms
- **Phase 2 (Revenue):** Invoice tax calculation (multi-jurisdiction), QuickBooks/Xero API mapping and conflict resolution, payment webhook idempotency
- **Phase 3 (Time Capture):** Google Calendar push notifications vs polling, Microsoft Graph delta queries, calendar conflict resolution, kiosk UX patterns for break tracking
- **Phase 4 (Workforce):** PTO accrual calculation edge cases (pro-rating, carryover), resource scheduling drag-and-drop library evaluation, overtime rules engine
- **Phase 5 (Analytics):** Query optimization for multi-dimensional aggregations, materialized view vs triggered table trade-offs, profitability calculation with rate fallback logic
- **Phase 6 (Extended):** Per-provider PM integration research (Jira, Asana, Trello, ClickUp webhook and OAuth patterns)

Phases with standard patterns (skip research-phase):
- **Phase 0 (Foundations):** Well-documented Laravel patterns (notifications, events, middleware, guards)
- **Phase 1 partial (Teams):** Standard CRUD with relationships, no novel patterns

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | MEDIUM-HIGH | Most packages verified on Packagist/npm with version compatibility confirmed. PHP 8.4 requirement needs validation. hy-vue-gantt (Gantt library) is LOW confidence, needs hands-on evaluation. |
| Features | HIGH | Multi-source competitor analysis with official feature pages verified. Feature categorization (table stakes vs differentiators) grounded in 6 major competitors. |
| Architecture | HIGH | Based on direct codebase analysis plus verified Laravel patterns. Existing architecture is clean and documented. New patterns (approval workflow, notifications, OAuth integration) are standard Laravel. |
| Pitfalls | HIGH | Grounded in codebase analysis (CONCERNS.md identified timezone fragility, N+1 risks, aggregation scaling limits) plus PRD review findings (6 CRITICAL + 7 HIGH issues). Timezone and approval race condition pitfalls are well-documented in domain literature. |

**Overall confidence:** MEDIUM-HIGH

Stack and features have high confidence based on verified sources. Architecture is high confidence based on codebase analysis and standard patterns. The MEDIUM drag comes from: (1) PHP 8.4 upgrade requirement not yet validated, (2) hy-vue-gantt library needs evaluation, (3) OAuth token lifecycle management has known complexity that may surface edge cases, (4) query performance assumptions depend on PostgreSQL behavior at scale (materialized views, GIN indexes on JSONB).

### Gaps to Address

**Gap 1: PHP 8.4 upgrade path and package compatibility**
- spatie/laravel-model-states ^2.12, eluceo/ical ^2.16, simshaun/recurr ^6.0 all require PHP 8.4
- Project currently pins PHP 8.3
- How to handle: Validate PHP 8.4 upgrade in Phase 0 before any package installation. Check Laravel 12 compatibility, run existing test suite, verify hosting/deployment supports PHP 8.4. If upgrade blocked, pin to older package versions (spatie/laravel-model-states 2.7.x, etc.) but this creates technical debt.

**Gap 2: Resource scheduling Gantt library viability**
- hy-vue-gantt is relatively new, low adoption, unclear if it supports resource-per-row view needed for scheduling
- How to handle: Allocate research spike in Phase 4 (2-4 hours) to evaluate hy-vue-gantt with a proof-of-concept. Fallback plan is custom timeline grid using @tanstack/vue-table + @vueuse/core's useDraggable. Existing FullCalendar covers day/week views, Gantt only needed for multi-week planning.

**Gap 3: Calendar sync conflict resolution strategy**
- When imported calendar event overlaps existing time entry, what's the resolution rule?
- Google Calendar and Microsoft Graph have different sync mechanisms (push notifications vs delta queries)
- How to handle: Defer to Phase 3 planning. Likely strategy: time entries are source of truth (never overwrite), calendar events become "suggestions" in a review queue, user must explicitly approve import.

**Gap 4: Multi-jurisdiction invoice tax calculation**
- PRD mentions tax handling but doesn't specify if multi-jurisdiction (US sales tax, EU VAT, etc.) is in scope
- How to handle: Clarify scope during Phase 2 planning. If single-jurisdiction only, use simple tax rate per invoice. If multi-jurisdiction, consider Stripe Tax API or manual tax rate table per client/project.

**Gap 5: Query performance at scale assumptions**
- Pitfall #7 assumes PostgreSQL handles multi-table aggregations well with proper indexes and pre-aggregation
- Actual performance depends on data distribution, query planner behavior, hardware
- How to handle: Add performance benchmarking to Phase 5 acceptance criteria. Seed database with 100K time entries + expenses + schedules, run report queries, verify <3s response time. If queries degrade, implement materialized views as documented in pitfall research.

**Gap 6: Solo developer estimation drift over 84 weeks**
- 2,093 hours across 84 weeks assumes consistent velocity
- Pitfall research warns estimation drift compounds: first 3 features take 20% longer, by feature 10 project is 6 months behind
- How to handle: Track actual vs estimated hours after every 3 features. Recalibrate estimates and adjust scope if ratio exceeds 1.3x. Monthly progress checks against timeline. Build 25-30% buffer into plan, not 10%.

## Sources

### Primary (HIGH confidence)
- Solidtime codebase analysis — direct file reading of /home/keven/Documents/solidtime-analysis, verified existing models/controllers/services/patterns
- [Packagist: spatie/laravel-model-states](https://packagist.org/packages/spatie/laravel-model-states) — v2.12.2, PHP ^8.4, Laravel 12
- [Packagist: laravel/reverb](https://packagist.org/packages/laravel/reverb) — v1.7.1, PHP ^8.2, Laravel 12
- [Laravel 12.x Broadcasting docs](https://laravel.com/docs/12.x/broadcasting) — Reverb setup
- [Laravel 12.x Billing docs](https://laravel.com/docs/12.x/billing) — Cashier Stripe
- [Laravel 12.x Notifications docs](https://laravel.com/docs/12.x/notifications) — notification system patterns
- [Harvest Features](https://www.getharvest.com/features) — verified feature list
- [Toggl Track Features](https://toggl.com/track/features/) — verified feature list
- [Clockify Paid Features](https://clockify.me/paid-features) — tiered feature breakdown
- PRD Review Report: .features/PRD-REVIEW-REPORT.md — 6 CRITICAL + 7 HIGH issues identified

### Secondary (MEDIUM confidence)
- [Packagist: google/apiclient](https://packagist.org/packages/google/apiclient) — v2.19.0, PHP ^8.1
- [Packagist: microsoft/microsoft-graph](https://packagist.org/packages/microsoft/microsoft-graph) — v2.56.0, PHP ^7.4
- [Packagist: xeroapi/xero-php-oauth2](https://packagist.org/packages/xeroapi/xero-php-oauth2) — v10.4.0, PHP >=8.1
- [Packagist: spatie/laravel-medialibrary](https://packagist.org/packages/spatie/laravel-medialibrary) — v11.18.2, PHP ^8.2, Laravel 12
- [Microsoft Graph change notifications](https://learn.microsoft.com/en-us/graph/outlook-change-notifications-overview) — webhook lifecycle
- [Everhour](https://everhour.com), [Paymo](https://www.paymoapp.com/complete-feature-list/), [QuickBooks Time](https://quickbooks.intuit.com/time-tracking/) — feature comparison
- [Larger Laravel Projects: 12 Things to Take Care Of](https://laraveldaily.com/post/larger-laravel-projects-12-things-to-take-care-of) — architectural patterns
- [Laravel migration conflicts](https://shivlab.com/blog/avoid-laravel-migration-failures/) — migration ordering patterns

### Tertiary (LOW confidence)
- [GitHub: Xeyos88/HyVueGantt](https://github.com/Xeyos88/HyVueGantt) — MIT license Vue 3 Gantt, needs evaluation
- [Timezone edge cases](https://www.thedroidsonroids.com/blog/edge-cases-in-app-and-backend-development-dates-and-time) — DST handling patterns
- [PTO accrual complexity](https://www.rippling.com/blog/pto-accrual) — domain background
- Various comparison articles (Everhour vs Harvest, Clockify vs Harvest, best agency time tracking 2026) — feature landscape

---
*Research completed: 2026-02-10*
*Ready for roadmap: yes*
