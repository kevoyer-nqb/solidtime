# Stack Research: Enterprise Features for Solidtime

**Domain:** Time management SaaS -- 17 enterprise features on existing Laravel 12 + Vue 3 stack
**Researched:** 2026-02-10
**Confidence:** MEDIUM-HIGH (most packages verified via Packagist/npm; some version constraints need validation at install time)

## Existing Stack (Do Not Replace)

The following are already installed and working. This research focuses exclusively on **additional** libraries.

| Layer | Technology | Version |
|-------|-----------|---------|
| Backend | Laravel | ^12.19.3 |
| Frontend | Vue 3 | ^3.5.0 |
| State | Pinia | ^2.1.7 |
| Routing | Inertia.js | ^1.0.0 (vue3) / ^2.0.3 (laravel) |
| Auth | Laravel Passport | ^13.0.5 |
| Database | PostgreSQL | (via tpetry/laravel-postgresql-enhanced ^3.0) |
| CSS | Tailwind CSS | ^3.4.13 |
| Data Fetching | @tanstack/vue-query | ^5.56.2 |
| Charts | ECharts + vue-echarts | ^5.5.0 / ^7.0.3 |
| Calendar | FullCalendar | ^6.1.18 |
| Tables | @tanstack/vue-table | ^8.21.2 |
| PDF Gen | Gotenberg | ^2.8 (gotenberg-php client) |
| Excel | Maatwebsite Excel | ^3.1 |
| CSV | League CSV | ^9.16.0 |
| Audit | owen-it/laravel-auditing | ^14.0.0 |
| Admin | Filament | ^3.2 |
| Date | dayjs | ^1.11.11 |

## PHP Version Constraint: CRITICAL NOTE

The project pins `"php": "8.3.*"`. Several cutting-edge packages now require PHP 8.4+:

| Package | Latest | Requires | PHP 8.3 Compatible Version |
|---------|--------|----------|---------------------------|
| spatie/laravel-model-states | 2.12.2 | PHP ^8.4 | 2.7.x (last PHP 8.3 release -- verify) |
| eluceo/ical | 2.16.0 | PHP ~8.4.0 | 2.14.x (supports ~8.3.0) |
| simshaun/recurr | 6.0.0 | PHP >=8.4 | ^5.0 (supports PHP 8.1-8.4) |

**Recommendation:** Upgrade to PHP 8.4 before starting enterprise features. Laravel 12 supports PHP 8.2-8.4, and PHP 8.4 unlocks the latest versions of all recommended packages. This is a one-time cost that eliminates ongoing version-pinning headaches.

**Confidence:** HIGH -- version requirements verified via Packagist on 2026-02-10.

---

## Recommended Additional Stack

### 1. Workflow & State Machines (Timesheet Approvals, PTO, Expenses)

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| spatie/laravel-model-states | ^2.12 | State machine for approval workflows | Industry standard for Laravel. Models states as classes with typed transitions. Integrates with Filament admin. Handles Draft->Submitted->Approved->Rejected flows cleanly. Used by the majority of Laravel projects needing state machines. |

**Confidence:** HIGH -- official Spatie docs verified, Packagist confirms Laravel 12 support in v2.12.2.

**What it replaces:** Hand-rolled enum-based state tracking. Using raw enums leads to scattered transition logic and no guard clauses.

**Installation:**
```bash
composer require spatie/laravel-model-states
```

**Applies to features:** Timesheet approvals, expense approvals, PTO request approvals, invoice status tracking.

---

### 2. Real-Time Notifications & Broadcasting (Budget Alerts, Approval Notifications, Kiosk)

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| laravel/reverb | ^1.7 | First-party WebSocket server | Laravel's official WebSocket solution. Uses Pusher protocol so Laravel Echo works out-of-box. Handles thousands of connections. Horizontal scaling via Redis. No third-party dependency (Pusher/Ably). |
| laravel-echo (npm) | ^2.3.0 | Client-side WebSocket listener | Official Laravel client for Reverb. Abstracts channel subscriptions. Required by Reverb. |
| pusher-js (npm) | ^8.4.0 | Pusher protocol client | Required by Laravel Echo when using Reverb (Pusher protocol). Installed automatically by `install:broadcasting`. |

**Confidence:** HIGH -- Laravel 12.x official docs confirm Reverb as first-party solution. Packagist version verified.

**Installation:**
```bash
composer require laravel/reverb
php artisan install:broadcasting
# npm packages installed automatically by install:broadcasting
```

**Applies to features:** Budget threshold alerts, approval request notifications, kiosk mode live updates, punch clock real-time status, team activity feeds.

---

### 3. Calendar Sync (Google Calendar + Outlook Integration)

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| google/apiclient | ^2.19 | Official Google API PHP client | Google's official SDK. Covers Calendar API, OAuth2 flows, token refresh. Most maintained and documented option. |
| microsoft/microsoft-graph | ^2.56 | Official Microsoft Graph SDK | Microsoft's official PHP SDK. Covers Outlook Calendar, mail, and user APIs. Supports OAuth2 delegated and app permissions. |
| eluceo/ical | ^2.14 (PHP 8.3) or ^2.16 (PHP 8.4) | iCal file generation (RFC 5545) | Standard library for generating .ics files. Needed for calendar export/sharing. |
| simshaun/recurr | ^5.0 (PHP 8.3) or ^6.0 (PHP 8.4) | RRULE recurrence rule handling | Handles RFC 5545 recurrence rules. Essential for recurring PTO events, recurring time entries. |

**Confidence:** HIGH for Google/Microsoft SDKs (verified on Packagist). MEDIUM for eluceo/recurr version pinning on PHP 8.3.

**Why not dnsinyukov/sync-calendars:** Low adoption (< 1K downloads), combines Google and Outlook into one abstraction that limits control over OAuth flows and webhook handling. Use the official SDKs directly for reliability and full API coverage.

**Why not spatie/laravel-google-calendar:** It wraps the Google SDK but adds opinions about service account auth that conflict with per-user OAuth2 flows needed here.

**Installation:**
```bash
composer require google/apiclient microsoft/microsoft-graph
composer require eluceo/ical simshaun/recurr
```

**Applies to features:** Enhanced calendar (Google/Outlook sync), PTO/time-off calendar export.

---

### 4. Payments & Invoicing (Invoice Generation, Payment Collection)

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| laravel/cashier | ^16.2 | Stripe billing integration | Laravel's official Stripe integration. Handles subscriptions, one-time charges, invoice PDFs, webhooks, payment methods. First-party = best maintained. |

**Confidence:** HIGH -- Laravel 12.x official docs at laravel.com/docs/12.x/billing. Packagist version verified.

**Note on PDF invoices:** The project already has `gotenberg/gotenberg-php` ^2.8 installed. Use Gotenberg for custom-branded invoice PDF generation from Blade/HTML templates with Tailwind CSS styling. This is superior to DomPDF (poor CSS support) or Browsershot (requires Node.js on server).

**Installation:**
```bash
composer require laravel/cashier
php artisan vendor:publish --tag="cashier-migrations"
php artisan migrate
```

**Applies to features:** Invoicing, payment collection, SaaS subscription billing (if monetizing the platform itself).

---

### 5. Accounting Integration (QuickBooks + Xero Sync)

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| quickbooks/v3-php-sdk | ^4.0 | Official QuickBooks Online API | Intuit's official SDK. Handles OAuth2, CRUD for invoices/payments/customers. Well-documented. |
| xeroapi/xero-php-oauth2 | ^10.4 | Official Xero API SDK | Xero's official OAuth2 SDK. Handles accounting CRUD operations. Actively maintained. |

**Confidence:** MEDIUM -- QuickBooks SDK docs reference v4.0.5; Xero SDK verified on Packagist at 10.4.0. Both are official vendor SDKs but integration complexity is high.

**Why not laravel-specific wrappers (spinen/laravel-quickbooks-client, langleyfoxall/xero-laravel):** These add a thin Laravel wrapper but have lower adoption and lag behind the official SDKs. Use the official SDKs directly and write your own service classes -- you get full API access and can follow updates faster.

**Installation:**
```bash
composer require quickbooks/v3-php-sdk xeroapi/xero-php-oauth2
```

**Applies to features:** Payments/accounting sync, invoice export to accounting systems.

---

### 6. File Storage & Media (Expense Receipts, Attachments)

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| spatie/laravel-medialibrary | ^11.18 | File uploads associated with Eloquent models | Industry standard for Laravel file management. Polymorphic attachment to any model. S3/local disk abstraction. Thumbnail generation. Validation. Already integrates with Filament admin. |

**Confidence:** HIGH -- Packagist verified v11.18.2 supports PHP ^8.2 and Laravel 12.

**Note:** The project already has `league/flysystem-aws-s3-v3` ^3.0, so S3 storage is ready. Spatie Media Library sits on top of Laravel's filesystem and adds model association, collections, conversions.

**Installation:**
```bash
composer require spatie/laravel-medialibrary
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
php artisan migrate
```

**Applies to features:** Expense receipt uploads, invoice attachments, project document storage.

---

### 7. Activity Logging & Audit Trail (Enhanced)

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| spatie/laravel-activitylog | ^4.11 | User activity and action logging | Complements the existing owen-it/laravel-auditing (model-level change tracking) with user-action-level logging (login, export, approval actions). More flexible for non-model events. |

**Confidence:** HIGH -- Packagist verified v4.11.0 supports PHP ^8.1 and Laravel 12.

**Relationship with existing owen-it/laravel-auditing:** Keep both. `owen-it/laravel-auditing` tracks model attribute changes (what changed, old value, new value). `spatie/laravel-activitylog` tracks user actions (who did what, when, on which resource). Together they provide a complete audit trail.

| Use Case | Package |
|----------|---------|
| "Field X changed from A to B" | owen-it/laravel-auditing (already installed) |
| "User approved timesheet #123" | spatie/laravel-activitylog |
| "User exported report as PDF" | spatie/laravel-activitylog |
| "User logged in from IP X" | spatie/laravel-activitylog |

**Installation:**
```bash
composer require spatie/laravel-activitylog
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
php artisan migrate
```

**Applies to features:** Audit trail, compliance logging, admin dashboard activity feeds.

---

### 8. Notification Channels (Slack, Microsoft Teams)

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| laravel/slack-notification-channel | ^3.0 | Slack notifications | Official Laravel first-party package. Supports both webhook and API token modes. |
| laravel-notification-channels/microsoft-teams | ^3.0 | MS Teams notifications | Community standard. Uses Adaptive Cards (v2). Webhook-based. |

**Confidence:** MEDIUM -- Slack channel is first-party. Teams channel is community-maintained but widely used. Version numbers based on search results; verify at install time.

**Note:** Laravel 12 has built-in support for mail, database, broadcast, and Slack notifications. Only the Teams channel needs a separate package.

**Installation:**
```bash
composer require laravel/slack-notification-channel
composer require laravel-notification-channels/microsoft-teams
```

**Applies to features:** Budget alerts, approval notifications, PTO request notifications, overtime warnings.

---

### 9. Kiosk Mode & PWA (Punch Clock, Kiosk)

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| vite-plugin-pwa (npm) | ^1.2.0 | PWA support for Vite | Zero-config PWA generator. Auto-generates service worker and manifest. Handles offline caching. Required for kiosk/tablet deployments. |

**Confidence:** MEDIUM -- npm listing verified. Integration with Inertia.js + Vite needs testing (Inertia does SSR-like routing that may need service worker configuration).

**Kiosk PIN auth:** Build custom -- no library needed. A simple PIN entry component with bcrypt-hashed PINs stored per-member. The Fullscreen API is a browser-native feature (`document.documentElement.requestFullscreen()`).

**Installation:**
```bash
npm install -D vite-plugin-pwa
```

**Applies to features:** Kiosk mode, punch clock, offline time entry.

---

### 10. Resource Scheduling UI (Gantt/Timeline)

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| hy-vue-gantt (npm) | latest | Gantt chart / resource timeline | MIT-licensed, Vue 3 native, TypeScript, virtual scrolling for large datasets. Supports drag-and-drop, milestones, themes. Free alternative to DHTMLX/Bryntum ($$$). |

**Confidence:** LOW -- Relatively new package (evolution of vue-ganttastic). Needs hands-on evaluation for resource scheduling use case. May need customization for resource-per-row view vs task-per-row.

**Alternative if hy-vue-gantt falls short:** Build a custom scheduling grid using the existing @tanstack/vue-table + custom drag-and-drop with @vueuse/core's `useDraggable`. The project already has FullCalendar which handles day/week views -- a Gantt is only needed for multi-week resource planning.

**Installation:**
```bash
npm install hy-vue-gantt
```

**Applies to features:** Resource scheduling, capacity planning.

---

### 11. PM Tool Integrations (Jira, Asana, etc.)

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| lesstif/php-jira-rest-client | ^5.0 | Jira REST API client | Most mature PHP Jira client. Active maintenance, good docs. Handles issues, projects, worklogs. |
| Custom HTTP (Guzzle) | (already installed) | Asana, Trello, ClickUp APIs | No quality Laravel-specific wrappers exist for Asana/Trello/ClickUp. Use Guzzle directly with a service class per integration. These APIs are REST-based and straightforward. |

**Confidence:** MEDIUM for Jira client. HIGH for Guzzle approach (already installed, universally applicable).

**Architecture note:** Build an integration interface/contract (`ProjectManagementIntegration`) and implement per-provider. This makes adding new PM tools a bounded effort.

**Installation:**
```bash
composer require lesstif/php-jira-rest-client
```

**Applies to features:** PM tool integrations (Jira, Asana, Trello, ClickUp sync).

---

### 12. Advanced Reporting & Export

No new libraries needed. The existing stack covers this:

| Existing Tech | Use For |
|--------------|---------|
| ECharts + vue-echarts | Interactive charts, dashboards |
| Maatwebsite Excel | XLSX export |
| League CSV | CSV export |
| Gotenberg | PDF report generation from Blade templates |
| @tanstack/vue-table | Tabular data display with sorting/filtering |

**Confidence:** HIGH -- all already installed and proven in the codebase.

**Applies to features:** Advanced reporting, data export, dashboard.

---

### 13. Custom Fields / Tags System

No new libraries needed. Use PostgreSQL JSONB columns.

**Recommendation:** Use a JSONB `custom_fields` column on relevant models (TimeEntry, Project, etc.) with a `custom_field_definitions` table that stores field schemas per organization.

**Why not EAV:** EAV (Entity-Attribute-Value) is a well-known anti-pattern for performance. PostgreSQL JSONB with GIN indexes provides the flexibility of custom fields with the performance of native column queries.

**Why not a package:** The available EAV packages (rinvex/laravel-attributes is ABANDONED, mralston/laravel-eav is low adoption) are not production-quality. A JSONB approach is ~50 lines of code and leverages PostgreSQL's native strengths.

**Confidence:** HIGH -- PostgreSQL JSONB is a well-documented pattern. The project already uses `tpetry/laravel-postgresql-enhanced` which provides JSONB query helpers.

**Applies to features:** Tags/custom fields, flexible metadata on any entity.

---

### 14. Scheduled Jobs & Queue Infrastructure

No new libraries needed. Use Laravel's built-in scheduler and queue system.

| Feature Need | Laravel Built-in |
|-------------|-----------------|
| Budget threshold checks | `php artisan schedule:run` with hourly job |
| Overtime calculations | Scheduled command, nightly |
| Calendar sync polling | Queued jobs with rate limiting |
| Report generation | Queued jobs (already have queue support) |
| Notification dispatch | ShouldQueue on notification classes |

**Confidence:** HIGH -- standard Laravel patterns.

---

## Development Tools (Additional)

| Tool | Purpose | Notes |
|------|---------|-------|
| Laravel Telescope | Already installed (dev) | Use for debugging API integrations, queue jobs, notifications |
| Filament | Already installed | Use for admin panel: manage organizations, view audit logs, system config |

---

## Complete Installation Summary

### PHP (Composer)

```bash
# State machines & workflows
composer require spatie/laravel-model-states

# Real-time
composer require laravel/reverb
php artisan install:broadcasting

# Calendar sync
composer require google/apiclient microsoft/microsoft-graph
composer require eluceo/ical simshaun/recurr

# Payments & invoicing
composer require laravel/cashier

# Accounting integration
composer require quickbooks/v3-php-sdk xeroapi/xero-php-oauth2

# File management
composer require spatie/laravel-medialibrary

# Activity logging (enhanced audit)
composer require spatie/laravel-activitylog

# Notifications
composer require laravel/slack-notification-channel
composer require laravel-notification-channels/microsoft-teams

# PM integrations
composer require lesstif/php-jira-rest-client
```

### JavaScript (npm)

```bash
# PWA / kiosk
npm install -D vite-plugin-pwa

# Resource scheduling (evaluate first)
npm install hy-vue-gantt

# Real-time (installed by install:broadcasting)
# npm install laravel-echo pusher-js
```

---

## Alternatives Considered

| Category | Recommended | Alternative | Why Not Alternative |
|----------|-------------|-------------|-------------------|
| State machine | spatie/laravel-model-states | ringlesoft/laravel-process-approval | Process-approval is opinionated about UI (Tailwind/Bootstrap views); we need headless logic for Vue frontend |
| State machine | spatie/laravel-model-states | Custom enum + transitions | Scattered logic, no transition guards, no event hooks, reinventing the wheel |
| WebSocket | Laravel Reverb | Pusher (hosted) | Monthly cost, external dependency, Reverb is free + first-party |
| WebSocket | Laravel Reverb | soketi | Third-party, less integrated with Laravel ecosystem than Reverb |
| PDF | Gotenberg (already installed) | barryvdh/laravel-dompdf | Poor CSS3/Tailwind support, no flexbox, limited fonts |
| PDF | Gotenberg (already installed) | spatie/laravel-pdf (Browsershot) | Requires Node.js on server, heavier resource usage |
| Google Calendar | google/apiclient (official) | spatie/laravel-google-calendar | Wraps official SDK but defaults to service account auth; we need per-user OAuth2 |
| Calendar sync | Official SDKs separately | dnsinyukov/sync-calendars | Low adoption (<1K downloads), thin abstraction limits API control |
| Accounting | Official vendor SDKs | langleyfoxall/xero-laravel | Lags behind official SDK, adds unnecessary abstraction layer |
| Custom fields | PostgreSQL JSONB | EAV pattern packages | Performance disaster at scale, abandoned packages, unnecessary complexity |
| Gantt | hy-vue-gantt | DHTMLX Gantt / Bryntum | Commercial license ($$$), hy-vue-gantt is MIT and Vue 3 native |
| File uploads | spatie/laravel-medialibrary | Custom storage logic | Medialibrary handles model association, collections, conversions, S3 -- why rebuild? |
| Activity log | spatie/laravel-activitylog | Extending owen-it/laravel-auditing | Auditing is model-change focused; activitylog handles arbitrary user actions |

---

## What NOT to Use

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| rinvex/laravel-attributes | ABANDONED (marked on GitHub) | PostgreSQL JSONB columns |
| barryvdh/laravel-dompdf | Cannot render Tailwind CSS, poor flexbox support | Gotenberg (already installed) |
| Pusher hosted service | Ongoing cost, external dependency for core feature | Laravel Reverb (free, first-party) |
| Any EAV package | Performance penalty, query complexity, most packages abandoned | JSONB columns with GIN indexes |
| spatie/laravel-google-calendar | Forces service account pattern, incompatible with per-user OAuth | google/apiclient directly |
| Laravel Socialite for calendar OAuth | Socialite is for login flows only, not ongoing API token management | google/apiclient + microsoft/microsoft-graph handle token refresh |
| vue-ganttastic | Predecessor to hy-vue-gantt, no longer maintained | hy-vue-gantt |
| Complex scheduling libraries (DHTMLX) | Commercial license, large bundle, overkill for SMB | hy-vue-gantt (MIT) or custom grid |

---

## Version Compatibility Matrix

| Package | PHP 8.3 | PHP 8.4 | Laravel 12 | Notes |
|---------|---------|---------|------------|-------|
| spatie/laravel-model-states ^2.12 | NO | YES | YES | Upgrade PHP or pin to ~2.7 |
| laravel/reverb ^1.7 | YES | YES | YES | |
| google/apiclient ^2.19 | YES | YES | N/A | |
| microsoft/microsoft-graph ^2.56 | YES | YES | N/A | |
| eluceo/ical ^2.14 | YES (^2.14) | YES (^2.16) | N/A | Version depends on PHP |
| simshaun/recurr | YES (^5.0) | YES (^6.0) | N/A | Version depends on PHP |
| laravel/cashier ^16.2 | YES | YES | YES | |
| quickbooks/v3-php-sdk ^4.0 | YES | YES | N/A | |
| xeroapi/xero-php-oauth2 ^10.4 | YES | YES | N/A | |
| spatie/laravel-medialibrary ^11.18 | YES | YES | YES | |
| spatie/laravel-activitylog ^4.11 | YES | YES | YES | |
| owen-it/laravel-auditing ^14.0 | YES | YES | YES | Already installed |
| vite-plugin-pwa ^1.2 | N/A | N/A | N/A | npm package |
| hy-vue-gantt | N/A | N/A | N/A | npm package |
| laravel-echo ^2.3 | N/A | N/A | N/A | npm package |

---

## Stack Patterns by Feature Cluster

**If building approval workflows (timesheets, expenses, PTO):**
- Use spatie/laravel-model-states for state machine
- Use Laravel notifications + Reverb for real-time approval notifications
- Use spatie/laravel-activitylog for audit trail of approval actions

**If building calendar integrations:**
- Use google/apiclient + microsoft/microsoft-graph for OAuth2 + API
- Use eluceo/ical for .ics file generation/export
- Use simshaun/recurr for recurring event rules
- Use Laravel queue + scheduler for background sync jobs

**If building financial features (invoicing, expenses, payments):**
- Use laravel/cashier for Stripe payments
- Use Gotenberg for PDF invoice generation
- Use spatie/laravel-medialibrary for receipt uploads
- Use quickbooks/v3-php-sdk or xeroapi/xero-php-oauth2 for accounting sync

**If building kiosk/punch clock:**
- Use vite-plugin-pwa for offline capability
- Use Laravel Reverb for real-time punch status
- Use browser Fullscreen API (native, no library)
- Build custom PIN authentication (simple bcrypt check)

---

## Infrastructure Requirements

| Feature Cluster | Infrastructure Need | Notes |
|----------------|--------------------|----|
| Real-time (Reverb) | Redis | For Reverb horizontal scaling + broadcast driver |
| Queue jobs | Redis or database | Budget checks, calendar sync, report generation |
| File storage | S3 or compatible | Expense receipts, invoice PDFs (S3 driver already configured) |
| PDF generation | Gotenberg Docker container | Already in use; ensure it runs in production |
| Calendar sync | Background workers | Long-running sync jobs need dedicated queue worker |
| Accounting sync | Background workers | API calls to QuickBooks/Xero must be queued |

---

## Sources

- [Packagist: spatie/laravel-model-states](https://packagist.org/packages/spatie/laravel-model-states) -- v2.12.2, PHP ^8.4, Laravel 12
- [Packagist: laravel/reverb](https://packagist.org/packages/laravel/reverb) -- v1.7.1, PHP ^8.2, Laravel 12
- [Laravel 12.x Broadcasting docs](https://laravel.com/docs/12.x/broadcasting) -- Reverb setup
- [Laravel 12.x Billing docs](https://laravel.com/docs/12.x/billing) -- Cashier Stripe
- [Packagist: laravel/cashier](https://packagist.org/packages/laravel/cashier) -- v16.2.0, PHP ^8.1, Laravel 12
- [Packagist: google/apiclient](https://packagist.org/packages/google/apiclient) -- v2.19.0, PHP ^8.1
- [Packagist: microsoft/microsoft-graph](https://packagist.org/packages/microsoft/microsoft-graph) -- v2.56.0, PHP ^7.4
- [Packagist: xeroapi/xero-php-oauth2](https://packagist.org/packages/xeroapi/xero-php-oauth2) -- v10.4.0, PHP >=8.1
- [Packagist: spatie/laravel-medialibrary](https://packagist.org/packages/spatie/laravel-medialibrary) -- v11.18.2, PHP ^8.2, Laravel 12
- [Packagist: spatie/laravel-activitylog](https://packagist.org/packages/spatie/laravel-activitylog) -- v4.11.0, PHP ^8.1, Laravel 12
- [Packagist: owen-it/laravel-auditing](https://packagist.org/packages/owen-it/laravel-auditing) -- v14.0.0, PHP >=8.2, Laravel 12
- [Packagist: gotenberg/gotenberg-php](https://packagist.org/packages/gotenberg/gotenberg-php) -- v2.16.0, PHP ^8.1
- [Packagist: eluceo/ical](https://packagist.org/packages/eluceo/ical) -- v2.16.0, PHP ~8.4
- [Packagist: simshaun/recurr](https://packagist.org/packages/simshaun/recurr) -- v6.0.0, PHP >=8.4
- [GitHub: Xeyos88/HyVueGantt](https://github.com/Xeyos88/HyVueGantt) -- MIT license, Vue 3 Gantt
- [GitHub: vite-pwa/vite-plugin-pwa](https://github.com/vite-pwa/vite-plugin-pwa) -- v1.2.0, Vite PWA
- [Spatie Model States docs](https://spatie.be/docs/laravel-model-states/v2/01-introduction) -- state machine patterns
- [Spatie Activity Log docs](https://spatie.be/docs/laravel-activitylog/v4/introduction) -- activity logging patterns
- [Gotenberg docs](https://gotenberg.dev/) -- PDF generation API
- [Laravel Reverb docs](https://laravel.com/docs/12.x/reverb) -- WebSocket server
- [Laravel Notifications docs](https://laravel.com/docs/12.x/notifications) -- Slack + custom channels
- [LaravelDaily: Custom Fields JSON vs EAV](https://laraveldaily.com/post/laravel-custom-fields-json-eav-model-same-table) -- JSONB recommendation

---
*Stack research for: Solidtime Enterprise Features*
*Researched: 2026-02-10*
