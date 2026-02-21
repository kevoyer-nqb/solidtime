# Solidtime SaaS Platform

## What This Is

A commercial SaaS time management platform for SMBs and agencies, built by extending the open-source solidtime time tracker with 17 enterprise features. The platform enables small teams (5-50 people) to track billable time, manage expenses, generate invoices, handle approvals, and gain workforce insights — replacing fragmented tools with a single integrated solution.

## Core Value

Agencies and small businesses can track time, approve timesheets, invoice clients, and monitor project budgets in one place — eliminating the need for multiple disconnected tools.

## Requirements

### Validated

<!-- Shipped and confirmed valuable — existing solidtime capabilities. -->

- ✓ Time entry creation with start/end, project, task, member, description, tags — existing
- ✓ Project and client management with hierarchical organization — existing
- ✓ Task management linked to projects — existing
- ✓ Organization-scoped multi-tenancy with isolated workspaces — existing
- ✓ Role-based access control (Owner/Admin/Manager/Employee) — existing
- ✓ Multi-level billable rates (organization, project, member) — existing
- ✓ REST API with Passport OAuth2 authentication — existing
- ✓ Import/export of time entries (CSV, Excel) — existing
- ✓ Basic reporting with shareable reports and optional expiry — existing
- ✓ Dashboard with charts and aggregations — existing
- ✓ Basic calendar view (FullCalendar) — existing
- ✓ Audit trail via OwenIt/Auditing — existing
- ✓ Admin panel via Filament — existing
- ✓ User impersonation for admin support — existing

### Active

<!-- Current scope — 17 feature epics to transform solidtime into full SaaS platform. -->

**Tier 0 — Prerequisite:**
- [ ] Feature 00: Weekly timesheet grid with day columns, row grouping, inline editing

**Shared Foundations (Phase 0):**
- [ ] FOUND-001 to FOUND-007: Notification infrastructure, approval pattern, weekly_capacity, modular permissions

**Tier 1 — Governance & Billing Foundation:**
- [ ] Feature 01: Timesheet approvals — submission workflow, approval/reject, locking, reminders
- [ ] Feature 02: Expense management — expense entries, categories, receipt uploads, billable flag, approval
- [ ] Feature 03: Budgets & alerts — project budgets (hours/cost/fixed), burn tracking, threshold alerts, forecasting

**Tier 2 — Revenue & Invoicing:**
- [ ] Feature 04: Invoicing system — invoice from time/expenses, templates, branding, recurring, online payments

**Tier 3 — Time Capture Enhancements:**
- [ ] Feature 05: Calendar view enhanced — drag-resize, Outlook/Google sync, event-to-entry conversion
- [ ] Feature 06: Kiosk & clock mode — shared-device kiosk, PIN/QR auth, break tracking, attendance

**Tier 4 — Workforce Management:**
- [ ] Feature 07: PTO & time off — policies, holiday calendars, accruals, requests, approvals, balances
- [ ] Feature 08: Resource scheduling — assignments, milestones, capacity planning, scheduled vs tracked

**Tier 5 — Analytics & Organization:**
- [ ] Feature 09: Advanced reporting — profitability, capacity/utilization, scheduled delivery, enhanced export
- [ ] Feature 10: Teams & groups — sub-org teams, client/project scoping, manager visibility boundaries

**Tier 6 — Extended Platform:**
- [ ] Feature 11: Tags & custom fields — user-defined tags, custom field definitions, filtering
- [ ] Feature 12: Punch-only / time-clock mode — simplified clock-in/out, no manual entry, shift tracking
- [ ] Feature 13: Audit trail / activity log — comprehensive activity logging, change history, compliance reporting
- [ ] Feature 14: Online payments & accounting sync — payment gateway, accounting software sync
- [ ] Feature 15: Attendance & overtime tracking — attendance records, overtime rules, compliance alerts
- [ ] Feature 16: PM tool integrations — Jira, Asana, Trello sync, task import, two-way status updates

### Out of Scope

- Real-time chat — high complexity, not core to time management value
- Mobile native app — web-first, responsive design serves mobile use cases
- AI-powered auto-categorization — defer to future; manual workflows first
- White-label / reseller features — commercial but single-brand for v1
- Custom workflow builder — predefined approval flows sufficient for target market

## Context

**Existing Codebase:**
- Solidtime is a mature open-source time tracker: Laravel 12 + Vue 3 + TypeScript + Pinia + Inertia.js
- PostgreSQL database, Passport OAuth2 API auth, Jetstream web auth
- Well-structured with service layer, API resources, OpenAPI-generated TypeScript client
- Codebase map available at `.planning/codebase/` (7 documents, 1,755 lines)

**Planning Artifacts:**
- 17 feature PRDs with architecture docs, codebase analyses, and sprint plans in `.features/`
- PRD review identified 6 CRITICAL + 7 HIGH issues, all resolved in SHARED-FOUNDATIONS.md
- Cross-feature decisions documented: task ID namespaces, permission naming, migration timestamps, notification infrastructure, approval pattern, weekly_capacity schema, feature flags, modular permissions
- Estimated total effort: ~2,093 hours / ~1,122 story points across 305 tasks

**Execution Model:**
- Solo developer + Claude Code
- All 17 features to be built
- Phase 0 (shared foundations) → parallel feature development following dependency graph

## Constraints

- **Tech stack**: Must extend solidtime's existing Laravel 12 + Vue 3 + Inertia.js architecture — no framework switches
- **Database**: PostgreSQL only — leverages existing schema and tpetry/laravel-postgresql-enhanced
- **API compatibility**: Existing solidtime REST API must remain backward-compatible
- **Auth**: Laravel Passport (API) + Jetstream (web) — no auth system changes
- **Patterns**: Must follow established conventions (service layer, API resources, Pinia stores, OpenAPI client generation)
- **Migration discipline**: Each feature gets unique date prefix per SF-03 to avoid conflicts

## Key Decisions

<!-- Decisions that constrain future work. Add throughout project lifecycle. -->

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Extend solidtime vs build from scratch | Solidtime has solid foundation (auth, multi-tenancy, time tracking, API) — saves months of base work | — Pending |
| All 17 features in scope | Full platform needed to compete commercially with Harvest, Toggl, Clockify | — Pending |
| Shared approval pattern (SF-05) | Three features need approvals — inconsistent UX unacceptable | — Pending |
| Shared notification infrastructure (SF-04) | Five features send notifications — build once, use everywhere | — Pending |
| Modular permissions (SF-08) | 17 features touching JetstreamServiceProvider guarantees conflicts | — Pending |
| Solo dev + Claude execution model | Maximizes velocity with AI-assisted development, no coordination overhead | — Pending |
| Phase 0 shared foundations first | FOUND-001 to FOUND-007 unblock all downstream features | — Pending |

---
*Last updated: 2026-02-10 after initialization*
