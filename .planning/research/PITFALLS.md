# Pitfalls Research

**Domain:** Time management SaaS -- 17 enterprise features added to existing open-source time tracker
**Researched:** 2026-02-10
**Confidence:** HIGH (grounded in codebase analysis, PRD review findings, and domain research)

---

## Critical Pitfalls

### Pitfall 1: Timezone Corruption Across Features

**What goes wrong:**
Time entries, timesheet approvals, PTO balances, overtime calculations, scheduling, and reporting all operate on dates and times. Each feature independently converts between UTC storage and user-display timezones. One feature calculates "this week" as Monday-Sunday in the user's timezone while another uses the organization's timezone. DST transitions create 23-hour or 25-hour days that break daily totals, weekly capacity checks, and overtime thresholds. A user in `America/New_York` submits a timesheet for a week that contains a DST transition -- the total shows 39 hours instead of 40 because one day was 23 hours, triggering an incorrect "under-hours" flag.

**Why it happens:**
Solidtime already has fragile timezone handling. The `TimeEntryAggregationService` performs timezone conversions in complex group-by queries (identified in CONCERNS.md as a fragile area with no DST transition tests). When 17 features each perform their own date arithmetic -- PTO accrual calculations, overtime daily/weekly thresholds, approval period boundaries, scheduling capacity per day, budget burn calculations -- the probability of at least one feature getting timezone math wrong approaches certainty. The existing codebase stores times in UTC but has PostgreSQL-specific `date_bin` expressions that handle timezone conversion in raw SQL, and no shared utility exists for "get the date boundaries for this user in their timezone."

**How to avoid:**
1. Create a shared `DateBoundaryService` in Phase 0 that all features use for: week start/end calculation, day boundaries, period containment checks. This service must accept a timezone identifier (never an offset) and handle DST transitions.
2. Store timezone as IANA identifier (`America/New_York`, not `+05:00`) on both `members` and `organizations` tables. The codebase already uses timezone parameters but inconsistently.
3. Every feature's date arithmetic must go through this service -- no raw `Carbon::startOfWeek()` calls in individual services.
4. Add DST transition test fixtures: create time entries spanning the March "spring forward" and November "fall back" dates in `America/New_York`, then verify every feature produces correct results.

**Warning signs:**
- Any feature doing `Carbon::parse()->startOfDay()` without explicitly setting timezone first
- Tests passing with UTC-only fixtures but no timezone-diverse test data
- Weekly totals that differ by exactly 1 hour from expected values
- Approval period boundaries that exclude or double-count entries near midnight in user's timezone

**Phase to address:**
Phase 0 (Shared Foundations) -- before any feature development begins. Add `FOUND-008: Shared DateBoundaryService` to the foundation tasks.

---

### Pitfall 2: Approval Workflow State Machine Race Conditions

**What goes wrong:**
Two managers approve the same timesheet simultaneously. Or a user withdraws their submission at the exact moment a manager clicks "approve." Or a budget alert fires while an expense is mid-approval, and the approval callback double-counts the expense against the budget. The shared `HasApprovalWorkflow` trait defines `isEditable()` and `isSubmitted()` checks, but these are read-then-write operations without database-level locking. In concurrent scenarios, both reads succeed before either write lands, resulting in corrupted state: a timesheet that's both "approved" and "withdrawn," or an expense counted twice toward a budget.

**Why it happens:**
Three features use the shared approval pattern (Timesheet Approvals, Expense Management, PTO). The state transitions are: draft -> submitted -> approved/changes_requested/rejected, plus withdrawal. Each transition triggers notifications, potentially budget recalculations, and audit log entries. Without pessimistic locking (`SELECT ... FOR UPDATE`) on the approvable entity during state transitions, concurrent requests can corrupt the state machine. Additionally, the notification side effects (email sends, database notification inserts) happen synchronously in the same request, making the "approval" operation slow and more vulnerable to timeout-related partial completions.

**How to avoid:**
1. Every approval state transition must use `DB::transaction()` with a `lockForUpdate()` on the approvable entity as the first query inside the transaction.
2. Validate the expected "from" state inside the transaction (optimistic concurrency): `->where('status', $expectedCurrentStatus)->lockForUpdate()->firstOrFail()`. If the status has already changed, throw a conflict exception (HTTP 409).
3. Queue all notification side effects -- never send emails or create database notifications inside the approval transaction. Use Laravel's `afterCommit` on queued jobs.
4. Add a `version` column or use `updated_at` as an optimistic lock token in the API request, so the frontend detects stale data.

**Warning signs:**
- Approval service methods that don't wrap state changes in transactions
- Any `->update(['status' => ...])` without first checking/locking the current status
- Notifications dispatched synchronously inside approval methods
- E2E tests that only test single-user approval flows

**Phase to address:**
Phase 0 (Shared Foundations) when implementing `HasApprovalWorkflow` trait. The trait itself should enforce the locking pattern so individual features cannot bypass it.

---

### Pitfall 3: Notification Avalanche Destroying UX and Performance

**What goes wrong:**
Budget threshold alerts fire for every time entry that crosses a threshold, generating 50 notifications per day for an active project. Timesheet reminders go out to 30 team members every Monday, and each reminder triggers 30 database inserts and 30 emails. The `notifications` table grows to millions of rows within months, making the unread-count badge query slow. Users disable all notifications because they receive too many, making the entire notification system worthless. The database `notifications` table becomes the performance bottleneck for the entire application.

**Why it happens:**
Five features create notifications (Approvals, Expenses, Budgets, PTO, Attendance/Overtime). Each feature's developers build their notifications in isolation. Budget alerts trigger on threshold crossing, but if a project is near threshold and multiple entries are logged daily, each crossing/re-crossing generates alerts. Timesheet reminders compound with team size. There is no rate limiting, deduplication, or batching strategy in the FOUND-002 base notification design. The `database` channel writes one row per notification per user, which is O(users * events). Laravel's default `notifications` table uses a JSON `data` column that is expensive to query for filtering.

**How to avoid:**
1. Implement notification rate limiting using `jamesmills/laravel-notification-rate-limit` or a custom `ShouldRateLimit` interface on `BaseNotification`. Budget alerts should fire at most once per threshold per project per day.
2. Batch notifications: instead of "User A submitted timesheet" + "User B submitted timesheet" as separate notifications to a manager, batch into "3 timesheets awaiting your review" with a 5-minute aggregation window.
3. Add a `notifications` table index on `(notifiable_type, notifiable_id, read_at)` for the unread-count query. Add a scheduled job to prune notifications older than 90 days.
4. Queue all notifications (`implements ShouldQueue` on every notification class). Never send notifications synchronously.
5. Use `shouldSend()` on notification classes to check user preferences before any work is done, not after the notification is constructed.

**Warning signs:**
- `notifications` table exceeding 100K rows within the first month of usage
- Unread-count API endpoint taking >200ms
- Users reporting "too many emails" in the first week
- Budget alert notifications appearing in rapid succession (multiple per hour for same project)
- Email delivery queue backing up during Monday morning timesheet reminders

**Phase to address:**
Phase 0 (Shared Foundations, FOUND-002). Rate limiting and batching must be built into `BaseNotification` before any feature ships notifications. Pruning job should be part of FOUND-001.

---

### Pitfall 4: Permission Explosion Making Roles Unmanageable

**What goes wrong:**
With 17 features, each adding 4-8 permissions, the total permission count reaches 80-120 permissions. The Jetstream role definitions become walls of permission strings. Admins creating custom roles face a checkbox grid with 100+ items that nobody can understand. A single misconfigured permission silently grants or denies access to critical financial data (invoice viewing, expense approval, payment processing). Worse, permission checks are scattered across controllers with no centralized audit capability -- when someone reports "I can't see invoices," debugging requires tracing through multiple controllers and middleware.

**Why it happens:**
SF-08 (modular permissions) solves the merge conflict problem but not the complexity problem. Each feature registers its permissions independently. With scoped variants (`view:own` vs `view:all` for 15+ entities), permission count explodes. Solidtime's existing `PermissionStore` does simple `has()` checks without hierarchy or inheritance. The base `Controller.php` has `checkPermission()` but no logging of denials. The PRD review already flagged (HIGH-03) that permission naming was inconsistent across PRDs -- even after standardization, the sheer volume creates cognitive overload.

**How to avoid:**
1. Group permissions into capability bundles (not individual permissions) for the role configuration UI: "Financial Management" = invoices:view + invoices:create + expenses:approve + budgets:view. Individual permissions still exist in code, but the UI presents grouped toggles.
2. Implement permission inheritance: Admin inherits all Manager permissions, Manager inherits all Employee permissions. Only define deltas per role, not complete permission sets.
3. Log every permission denial with `(user_id, organization_id, permission, endpoint, timestamp)` -- this is essential for debugging and for the audit trail feature (Feature 13).
4. Add a permission matrix test that programmatically verifies every endpoint is protected and that each role has expected access. This prevents the "forgot to add permission check" bug.
5. Cap custom role creation at 5-10 roles per organization to prevent combinatorial explosion.

**Warning signs:**
- Role definition files exceeding 100 lines of permission strings
- Support tickets about "I can't access X" that take >30 minutes to debug
- Features shipping without permission checks on new endpoints (the matrix test catches this)
- Organization admins creating 10+ custom roles trying to model their exact hierarchy

**Phase to address:**
Phase 0 (FOUND-007, modular permissions infrastructure). The permission grouping UI and inheritance model must be designed before features start registering permissions. The permission matrix test should be established in Phase 0 and extended by every feature.

---

### Pitfall 5: Database Migration Ordering Breaks Production Deploys

**What goes wrong:**
Feature 08 (Scheduling) adds a foreign key from `assignments.member_id` to `members`, but Feature 10 (Teams) has already added a `team_id` to `members` in a migration that runs first. Feature 15 (Attendance) depends on `members.weekly_capacity` from FOUND-006 but the deploy script runs Feature 15's migrations before the shared foundation. In production, `php artisan migrate` fails mid-way, leaving the database in a partially-migrated state. Rolling back is impossible because some migrations succeeded and others depend on them.

**Why it happens:**
SF-03 assigns date prefixes per feature (2026_03_01 through 2026_03_16), but this only prevents filename collisions -- it does not guarantee that prerequisites run first. Laravel runs migrations in timestamp order. If Feature 08's migration (2026_03_08) references `members.weekly_capacity` (added by FOUND-006 at 2026_02_28), the order works. But if a feature adds a column to a table that another feature's migration also modifies, the second migration may find the table in an unexpected state. With 17 features creating 30-50 migrations total, the dependency graph is non-trivial.

**How to avoid:**
1. Enforce the convention that shared foundation migrations (2026_02_28_*) always run first by keeping their timestamps before any feature timestamp.
2. Every migration must be idempotent where possible: use `Schema::hasColumn()` checks before adding columns, `Schema::hasTable()` before creating tables.
3. Never modify a migration file after it has been run in any environment (including staging). Create new migrations for corrections.
4. Create a CI test that runs `php artisan migrate:fresh` followed by `php artisan migrate:rollback --step=999` to verify all migrations are reversible.
5. Document foreign key dependencies in each migration file as a comment header: `// Depends on: 2026_02_28_000001 (members.weekly_capacity)`.
6. Run all migrations in a staging environment before production. Never deploy migrations directly to production.

**Warning signs:**
- `php artisan migrate` failing in CI with "column not found" or "table doesn't exist" errors
- Migration files being modified after initial creation
- Features adding columns to the same table (especially `members`, `organizations`, `time_entries`) without coordinating
- Missing `down()` methods in migrations

**Phase to address:**
Phase 0 (SF-03 already partially addresses this). Add a CI migration test as FOUND-009. Every subsequent feature's PR must pass this test before merge.

---

### Pitfall 6: Calendar OAuth Token Lifecycle Management Failure

**What goes wrong:**
A user connects their Google Calendar. The OAuth access token expires after 1 hour. The refresh token works for the first few months, then Google revokes it because the app exceeded the 50-token-per-user limit (each re-authorization creates a new refresh token). The background sync job starts failing silently. The user's calendar events stop syncing but they see no error -- stale events persist in the UI. Outlook webhooks expire after 7 days maximum. The subscription renewal job runs but fails because the Azure AD token expired first. Now push notifications stop and the app falls back to polling, which burns API quota.

**Why it happens:**
OAuth with Google and Microsoft is a multi-layer token lifecycle: access tokens (1 hour), refresh tokens (months, but revocable), webhook subscriptions (7 days for Microsoft), and consent grants (can be revoked by user at any time). Each layer has different failure modes. The PRD review already flagged CRIT-06 (OAuth callback URL design), but token lifecycle management is a deeper problem. Most implementations handle the happy path (initial connect, first sync) but not degradation (token revoked, subscription expired, API rate limited, user removed calendar permission from Google settings).

**How to avoid:**
1. Build a `CalendarConnectionHealthService` that checks each connection daily: attempt a lightweight API call (e.g., list 1 event), catch auth failures, and mark the connection as `needs_reauth` if the refresh token fails.
2. Surface broken connections prominently in the UI -- a yellow warning banner on the calendar page, not buried in a settings page.
3. For Microsoft Graph webhooks: implement a scheduled job that renews subscriptions every 5 days (well before the 7-day expiry). Handle `lifecycleNotification` events for `reauthorizationRequired`.
4. For Google: always request `access_type=offline` and `prompt=consent` only on initial connection. Use incremental authorization if adding scopes later.
5. Implement exponential backoff for sync retries: if a sync fails 3 times, mark the connection as degraded and notify the user.
6. Store token metadata: `last_successful_sync_at`, `consecutive_failures`, `expires_at` for both access and refresh tokens.

**Warning signs:**
- Calendar sync jobs running but producing no new/updated events
- `consecutive_failures` column > 0 for connections (once this metric exists)
- Users re-connecting their calendar repeatedly (sign their token keeps dying)
- Microsoft Graph returning 401/403 on webhook deliveries

**Phase to address:**
Feature 05 (Calendar Enhanced). This pitfall should be addressed in the architecture phase of Feature 05 before implementation begins. Add specific health-check and degradation-handling tasks.

---

### Pitfall 7: Reporting Query Performance Degradation at Scale

**What goes wrong:**
Advanced reporting (Feature 09) builds on the existing `TimeEntryAggregationService` which already has known performance issues (CONCERNS.md: "Query generation + execution breaks with multiple grouping dimensions, large date ranges, 1000+ tags, 50K+ time entries"). Adding profitability reports (joining time entries with billable rates, cost rates, and project budgets), utilization reports (joining with weekly_capacity and PTO data), and scheduled-vs-actual comparisons (joining with assignments from Feature 08) creates queries that join 5-7 tables with multiple GROUP BY dimensions. A single report request for "profitability by client by month for the last year" takes 30+ seconds, times out, or exhausts PostgreSQL `work_mem`.

**Why it happens:**
Each report type layers additional JOINs and aggregations on top of time_entries. The existing JSON tag expansion (LATERAL cross join) already creates row multiplication. Adding cost rates (per member, per project, per org -- fallback logic), PTO deductions (LEFT JOIN on time_off_requests), and budget data (JOIN on project budgets) means the query planner faces exponential options. PostgreSQL is excellent at this, but without proper indexes, materialized views, or pre-aggregation, the queries become untenable at 50K+ time entries -- a threshold reached within 6-12 months by a team of 20 people tracking daily.

**How to avoid:**
1. Pre-aggregate daily totals: create a `daily_time_summaries` materialized view (or table updated by trigger/job) containing `(member_id, project_id, task_id, date, total_seconds, billable_seconds, cost)`. Reports query this instead of raw time_entries.
2. Add composite indexes on time_entries: `(organization_id, member_id, start)` and `(organization_id, project_id, start)` for the most common report filters.
3. Implement report result caching: cache report results for 5 minutes with cache key based on parameters. Invalidate on time entry changes using a simple `last_modified` timestamp per organization.
4. Enforce time range limits: no report can query more than 12 months in a single request. For longer ranges, require explicit "export" which runs as a background job.
5. Split PRD 09 into two phases (already recommended in PRD review, HIGH-07): core reports first, complex cross-feature reports second.

**Warning signs:**
- Report API endpoints taking >5 seconds in development with seed data
- PostgreSQL EXPLAIN plans showing sequential scans on time_entries
- Dashboard page load time increasing as data grows (dashboard uses similar aggregation)
- Users reporting timeouts on the reporting page

**Phase to address:**
Feature 09 (Advanced Reporting), but the `daily_time_summaries` pre-aggregation should be designed during Phase 0 or Phase 1 since it benefits multiple features (budgets burn rate, scheduling utilization, overtime calculations). Consider adding FOUND-010: daily summary pre-aggregation infrastructure.

---

## Technical Debt Patterns

Shortcuts that seem reasonable but create long-term problems.

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| Inline SQL in service methods | Fast to write, PostgreSQL-specific optimizations | Untestable without database, impossible to port, hard to debug | Only for performance-critical aggregations that cannot be expressed in Eloquent. Wrap in a dedicated repository method. |
| Skipping API versioning for new endpoints | Simpler routes, fewer files | Breaking changes affect all API consumers when schema evolves | Never -- all new endpoints must be under `/api/v1/`. The existing pattern already does this. |
| Synchronous email sending | Simpler code, no queue setup needed | Blocks HTTP requests for 2-5 seconds, cascading timeouts under load | Never -- always queue emails. The queue infrastructure (database driver at minimum) must be operational from Phase 0. |
| Shared mutable state in Pinia stores | Quick data sharing between components | Memory leaks in SSR, stale data across page navigations, hard-to-trace bugs | Only for genuinely global state (current user, current org). Feature-specific data should use TanStack Query with proper cache keys. |
| Testing only happy paths | Faster test writing, higher "coverage" number | Approval race conditions, timezone bugs, and permission gaps all hide in unhappy paths | Never for financial features (invoicing, expenses, budgets). Acceptable for UI polish features in early phases. |
| Deferring tests to final sprint | More "velocity" in early sprints | Bugs found late are 10x more expensive. Final sprint becomes testing hell with no time buffer. | Never. Each task should have acceptance tests written alongside implementation. |

## Integration Gotchas

Common mistakes when connecting to external services.

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|------------------|
| Google Calendar API | Requesting `https://www.googleapis.com/auth/calendar` (full access) when only read is needed initially | Start with `calendar.readonly` scope. Use incremental authorization to add write scope only when event creation is needed. Full scope triggers Google's extended review process, delaying launch by weeks. |
| Microsoft Graph (Outlook) | Using the deprecated Outlook REST API v2.0 instead of Microsoft Graph | Always use Microsoft Graph API (`graph.microsoft.com`). The Outlook-specific endpoints are deprecated. Webhook subscriptions max 7 days -- must implement renewal job. |
| Stripe/Payment Gateway | Storing payment state in your database and trusting it as source of truth | Payment gateway is the source of truth. Always verify payment status via webhook or API call before marking invoice as paid. Handle webhook idempotency (same event delivered multiple times). |
| QuickBooks/Xero (Accounting Sync) | Assuming real-time sync when APIs have rate limits and batch expectations | Design sync as periodic batch job (every 15-30 minutes), not per-transaction push. Handle conflicts with "last write wins" plus user-visible conflict log. |
| Jira/Asana/Trello (PM Tools) | Building tight coupling where task creation in PM tool immediately creates task in solidtime | Use event-driven loose coupling. Webhook from PM tool creates a "pending import" record, background job processes it, user can review/approve imported tasks. Two-way sync is extraordinarily complex -- start with one-way (PM tool -> solidtime). |
| S3 (Receipt Uploads) | Storing uploaded files with user-provided filenames | Always generate UUIDs for stored filenames. Validate file type (not just extension -- check magic bytes). Enforce size limits (10MB for receipts). Generate pre-signed URLs for downloads, never serve directly. |

## Performance Traps

Patterns that work at small scale but fail as usage grows.

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| N+1 queries in approval lists | Approval list page loads slowly, 50+ queries per page | Eager load `member`, `reviewer`, `timeEntries` relationships in approval queries. Use `->with()` consistently. | 50+ approval records per page (a team of 20 generating weekly approvals = 80+ records/month) |
| Unindexed `notifications` table queries | Notification bell API takes 500ms+ | Add composite index on `(notifiable_id, notifiable_type, read_at)`. Prune old notifications on schedule. | 10K+ notification records per organization (reached within 2-3 months with 5 notification-generating features) |
| Full table scan on time_entries for reports | Report generation takes 10-30 seconds | Add indexes on `(organization_id, start, end)`, `(organization_id, member_id, start)`, `(organization_id, project_id, start)` | 50K+ time entries per organization (20-person team * 8 entries/day * 300 days = 48K/year) |
| Synchronous PDF generation for invoices | Invoice download/email takes 10-20 seconds, HTTP timeout | Generate PDFs asynchronously via queued job. Store generated PDF in S3. Serve from S3 on subsequent requests. | Any load -- Gotenberg is a separate service with its own resource constraints |
| Calendar sync polling instead of webhooks | Excessive API calls, hitting rate limits, stale data | Use webhooks (Google Calendar push notifications, Microsoft Graph subscriptions) as primary, polling as fallback only | 50+ connected calendars (each polling every 5 minutes = 600 API calls/hour) |
| PTO accrual recalculation on every request | Balance check API slow, database load spikes | Calculate and cache accrual balances. Recalculate only on: time entry change, policy change, new pay period. Store `balance_as_of` snapshot. | 50+ employees with monthly accrual policies |
| Audit trail logging every model change | `activity_log` table grows to millions of rows, slowing writes | Log only meaningful changes (status transitions, financial data). Exclude: timestamp-only updates, read operations. Partition table by month. | 100K+ audit records (reached within 1-2 months with full audit logging) |

## Security Mistakes

Domain-specific security issues beyond general web security.

| Mistake | Risk | Prevention |
|---------|------|------------|
| Kiosk PIN stored as plaintext or weak hash | PIN brute-force on shared device exposes all employee data | Use bcrypt for verification. Add rate limiting: 5 failed attempts = 15-minute lockout. Log all failed attempts. The PRD review flagged CRIT-05 about PIN uniqueness -- use SHA-256 of org_id+PIN for uniqueness checking alongside bcrypt for verification. |
| Kiosk session token in URL | Token visible in browser history, proxy logs, screenshots | Use httpOnly cookies for kiosk sessions. Never pass authentication tokens in URL query parameters. Implement short-lived tokens (15 minutes) that auto-refresh. |
| Invoice PDF containing customer PII accessible without auth | Data breach via shared/leaked invoice links | Generate time-limited pre-signed URLs for PDF downloads (1 hour expiry). Never serve PDFs from a public bucket. Log all invoice PDF access for audit trail. |
| Expense receipt uploads not validated | Malicious file upload (e.g., PHP shell disguised as image) | Validate MIME type from file content (not extension). Restrict to image/pdf types. Store in S3 (not local filesystem). Process uploads through antivirus scan. Set `Content-Disposition: attachment` on download to prevent browser execution. |
| Cross-organization data leaks via team scoping bugs | User in Organization A sees Organization B's data | Every query must include `organization_id` in WHERE clause. The existing pattern does this, but team scoping (Feature 10) adds a new dimension. Test explicitly: create identical team names in two orgs, verify queries are isolated. |
| Payment webhook endpoints without signature verification | Attacker can spoof "invoice paid" webhooks, marking invoices as paid without actual payment | Always verify webhook signatures (Stripe: `Stripe-Signature` header, etc.). Use webhook secret from environment config. Reject any webhook that fails verification. |
| OAuth tokens stored in reversible encryption rather than encrypted at rest | Database breach exposes all connected calendar accounts | Use Laravel's encrypted casting (`'access_token' => 'encrypted'`) for all OAuth tokens. Rotate encryption keys periodically. Never log token values. |

## UX Pitfalls

Common user experience mistakes in this domain.

| Pitfall | User Impact | Better Approach |
|---------|-------------|-----------------|
| Requiring manual timesheet submission when entries already exist | Users fill in the grid, then must click "Submit" on a separate approvals page. They forget, managers see nothing to approve. | Auto-detect when a week is "complete" (total hours >= capacity). Show a prominent "Submit Week" button directly in the timesheet grid. Send reminder notifications if week is complete but not submitted. |
| Showing approval status without action context | Manager sees "5 pending approvals" but must navigate to each one individually, load the timesheet, review, approve. | Batch approval: show summary of all pending timesheets in one view with "Approve All" and individual approve/reject buttons. Show key metrics inline (total hours, variance from expected). |
| Complex PTO policy setup requiring HR expertise | Admin spends 2 hours configuring accrual rates, carryover caps, and pro-ration rules. Gets it wrong. Employees see incorrect balances. | Provide policy templates ("Standard 15 days/year", "Unlimited PTO", "Accrual-based") that pre-fill all fields. Show a "preview" of what an employee's balance would look like over 12 months before saving. |
| Budget alerts that only fire at thresholds, not trend | Project goes from 50% to 95% budget consumed in one week, but the 75% alert was the last notification. Manager had no warning of accelerating burn. | Show burn rate trend on budget dashboard (projected exhaustion date). Send alerts for both threshold AND rate-of-change ("Budget burn rate 3x normal this week"). |
| Reporting page that requires too many clicks before showing data | User must select report type, date range, grouping, filters before seeing anything. Most users want "last month, by project." | Show the most common report (this month, by project, for current user's org) immediately on page load. Provide quick-switch tabs for common variants. Save user's last report configuration. |
| Kiosk interface with too many options | Employee faces a complex screen with project, task, break type, and description fields on a shared device with a queue behind them. | Clock in = one tap + PIN. Clock out = one tap + PIN. Break = one tap. Everything else (project assignment, notes) is handled by managers on their own devices after the fact. |

## "Looks Done But Isn't" Checklist

Things that appear complete but are missing critical pieces.

- [ ] **Timesheet Approvals:** Often missing locked-entry enforcement -- verify that approved timesheet entries cannot be edited/deleted through the regular time entry API, not just through the approval UI
- [ ] **Expense Management:** Often missing receipt deletion on expense rejection -- verify that if an expense is rejected and deleted, the receipt file in S3 is also cleaned up
- [ ] **Budget Alerts:** Often missing alert reset logic -- verify that if budget consumption drops below threshold (e.g., time entry deleted), the alert state resets so it fires again when re-crossed
- [ ] **Invoicing:** Often missing tax calculation edge cases -- verify multi-line invoices with different tax rates produce correct totals (rounding errors accumulate)
- [ ] **Invoicing:** Often missing invoice numbering sequence uniqueness -- verify that concurrent invoice creation never produces duplicate numbers (use database sequence, not application logic)
- [ ] **Calendar Sync:** Often missing conflict resolution for overlapping imported events -- verify that two overlapping calendar events do not create overlapping time entries that violate the existing overlap detection
- [ ] **PTO Accruals:** Often missing mid-period hire handling -- verify that an employee hired on the 15th of the month gets half the monthly accrual, not full or zero
- [ ] **Resource Scheduling:** Often missing capacity update propagation -- verify that when `weekly_capacity` changes, future assignments are recalculated or flagged as over-allocated
- [ ] **Teams Scoping:** Often missing cascade on team deletion -- verify that when a team is deleted, member-team associations are cleaned up and query scoping does not break (return empty results vs. all results)
- [ ] **Audit Trail:** Often missing redaction of sensitive data -- verify that audit logs do not contain raw OAuth tokens, PINs, or payment details
- [ ] **Payment Processing:** Often missing idempotency on webhook handlers -- verify that receiving the same Stripe webhook event twice does not double-mark an invoice as paid or double-credit a balance
- [ ] **Overtime Tracking:** Often missing weekly reset boundary -- verify that overtime calculations reset correctly at week boundary and do not carry over erroneously when an employee's timezone differs from the organization's

## Recovery Strategies

When pitfalls occur despite prevention, how to recover.

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| Timezone corruption in stored data | HIGH | Run a data audit query comparing time entry `start`/`end` with expected timezone offsets. Write a correction migration. Recalculate all affected aggregations (approval totals, budget burn, PTO balances). Notify affected users. |
| Approval state corruption (race condition) | MEDIUM | Query for records in impossible states (approved + withdrawn, two different reviewers on same approval). Write a cleanup script. Add database CHECK constraints to prevent recurrence. |
| Notification table bloat | LOW | Run `DELETE FROM notifications WHERE created_at < NOW() - INTERVAL '90 days'`. Add the pruning job. Consider partitioning the table by month for future. |
| Permission misconfiguration | MEDIUM | Export current role-permission matrix. Compare against intended matrix. Write migration to correct. Add the matrix test to prevent recurrence. |
| Migration ordering failure in production | HIGH | Do NOT attempt to manually fix the database. Restore from backup. Fix migration ordering. Re-deploy. Add CI migration test. |
| OAuth token expiration cascade | LOW | Run a health check job on all calendar connections. Mark expired connections as `needs_reauth`. Send batch email to affected users asking them to reconnect. |
| Report query timeout at scale | MEDIUM | Add the pre-aggregation table/materialized view. Write a backfill job to populate historical data. Switch report queries to use the new source. Drop the temporary query timeout increase. |

## Pitfall-to-Phase Mapping

How roadmap phases should address these pitfalls.

| Pitfall | Prevention Phase | Verification |
|---------|------------------|--------------|
| Timezone corruption | Phase 0 (new FOUND-008: DateBoundaryService) | Unit tests with DST transitions pass for every feature's date arithmetic |
| Approval race conditions | Phase 0 (SF-05 trait implementation) | Concurrent approval tests pass (two simultaneous approve requests, only one succeeds) |
| Notification avalanche | Phase 0 (FOUND-002: BaseNotification with rate limiting) | Load test: 100 budget threshold crossings in 1 hour generate <= 5 notifications per project |
| Permission explosion | Phase 0 (FOUND-007: modular permissions with grouping) | Permission matrix test covers all endpoints, role configuration UI shows grouped permissions |
| Migration ordering | Phase 0 (CI test as FOUND-009) | `migrate:fresh` + `migrate:rollback` passes in CI for every PR |
| OAuth token lifecycle | Feature 05 (Calendar Enhanced architecture phase) | Degraded connection detection test: revoke token, verify UI shows warning within 24 hours |
| Report query performance | Phase 1 (early, as FOUND-010 or Feature 09 pre-work) | Profitability report returns in <3 seconds with 100K time entries |
| Solo developer scope overwhelm | All phases (process discipline) | Each phase has clear exit criteria. Monthly progress check against 84-week timeline. No feature starts until previous feature's tests pass. |

## Solo Developer / AI-Assisted Development Pitfalls

These pitfalls are specific to the execution model (solo developer + Claude, 305 tasks, 2,093 hours).

### Context Switching Tax

**What goes wrong:** Working on 17 features means constantly switching mental context between different domain areas (financial compliance for invoicing, OAuth flows for calendar, shift management for kiosk). Each switch costs 15-30 minutes of re-orientation. With Claude assistance, context windows also fragment -- each conversation starts fresh.

**How to avoid:** Complete one feature fully (including tests) before starting the next. Never have more than 2 features "in progress" simultaneously. Keep `.features/{feature}/CLAUDE.md` files updated so Claude can re-orient quickly. Use the phased deployment order from SF-09 strictly.

### Regression Blindness

**What goes wrong:** Feature 8 subtly breaks Feature 1's behavior because both modify time entry queries. With no second developer to catch regressions during code review, broken behavior persists until a user reports it weeks later.

**How to avoid:** Maintain a comprehensive API integration test suite that runs on every commit. The existing test infrastructure (PHPUnit + Paratest) supports this. Add a "cross-feature regression" test suite that specifically tests Feature A's behavior after Feature B's code is merged. Run full test suite before every feature merge, not just the new feature's tests.

### Estimation Drift Over 84 Weeks

**What goes wrong:** The first 3 features take 20% longer than estimated. This 20% compounds: by feature 10, the project is 6 months behind schedule. Solo developer burns out trying to maintain the original timeline.

**How to avoid:** Track actual hours per task vs. estimated hours. Recalibrate the estimate-to-actual ratio after every 3 features. If ratio exceeds 1.3x, re-estimate remaining features and adjust scope. The 10% contingency buffer in original estimates is insufficient for a project this large -- plan for 25-30% buffer across the full project.

## Sources

- Solidtime codebase analysis: `.planning/codebase/CONCERNS.md` (identified timezone fragility, N+1 risks, aggregation scaling limits) -- HIGH confidence
- PRD Review Report: `.features/PRD-REVIEW-REPORT.md` (6 CRITICAL + 7 HIGH issues) -- HIGH confidence
- SHARED-FOUNDATIONS.md: `.features/SHARED-FOUNDATIONS.md` (cross-feature decisions, migration allocation, approval pattern) -- HIGH confidence
- [Laravel migration conflicts and parallel development](https://shivlab.com/blog/avoid-laravel-migration-failures/) -- MEDIUM confidence
- [Larger Laravel Projects: 12 Things to Take Care Of](https://laraveldaily.com/post/larger-laravel-projects-12-things-to-take-care-of) -- MEDIUM confidence
- [Timezone edge cases in application development](https://www.thedroidsonroids.com/blog/edge-cases-in-app-and-backend-development-dates-and-time) -- MEDIUM confidence
- [Beware the Edge Cases of Time](https://codeofmatt.com/beware-the-edge-cases-of-time/) -- MEDIUM confidence
- [Microsoft Graph change notifications for Outlook](https://learn.microsoft.com/en-us/graph/outlook-change-notifications-overview) -- HIGH confidence (official docs)
- [Laravel Notification Rate Limiter](https://github.com/jamesmills/laravel-notification-rate-limit) -- MEDIUM confidence
- [Laravel 12 Notifications documentation](https://laravel.com/docs/12.x/notifications) -- HIGH confidence (official docs)
- [PostgreSQL aggregation optimization with TimescaleDB](https://www.tigerdata.com/blog/how-we-made-data-aggregation-better-and-faster-on-postgresql-with-timescaledb-2-7) -- MEDIUM confidence
- [PTO accrual calculation complexity](https://www.rippling.com/blog/pto-accrual) -- MEDIUM confidence
- [Kiosk security best practices](https://www.hexnode.com/blogs/android-kiosk-mode-security-should-i-be-concerned/) -- MEDIUM confidence
- [SAP timesheet approval workflow edge cases](https://userapps.support.sap.com/sap/support/knowledge/en/2738965) -- MEDIUM confidence
- [Invoice payment mismatch causes and fixes](https://ordwaylabs.com/blog/reasons-payments-dont-match-invoices-causes-examples-and-fixes/) -- MEDIUM confidence
- [Inertia.js SSR memory leak](https://github.com/inertiajs/inertia/issues/1602) -- HIGH confidence (official issue tracker)
- [Feature creep in SaaS product development](https://wearepresta.com/why-just-one-more-feature-is-killing-your-product-roadmap/) -- LOW confidence (opinion piece)

---
*Pitfalls research for: Solidtime SaaS Platform -- 17 Enterprise Features*
*Researched: 2026-02-10*
