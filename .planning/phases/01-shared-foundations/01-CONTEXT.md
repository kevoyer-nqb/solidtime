# Phase 1: Shared Foundations - Context

**Gathered:** 2026-02-10
**Status:** Ready for planning

<domain>
## Phase Boundary

Cross-cutting infrastructure that every downstream feature depends on. Delivers: notification system (bell UI, email delivery, preferences), reusable approval pattern, modular permissions infrastructure, timezone-safe DateBoundaryService, weekly_capacity schema, CI migration test, and daily time summary pre-aggregation. Built once, used everywhere.

</domain>

<decisions>
## Implementation Decisions

### Email notification delivery
- Instant delivery only — one email per notification event, no batching or digest
- No daily/weekly digest option at this stage

### Email notification defaults
- New members get "critical only" notifications enabled by default
- Critical = approval-related notifications, budget threshold alerts
- Lower-priority notifications (informational updates) default to off
- Members opt in to additional types themselves

### Email notification control
- Member self-service only — each member manages their own notification toggles
- Admins cannot force notifications on or off for members
- Preference UI lives in organization member settings (per-member, per-type toggles)

### Week start day
- Configurable per organization, not fixed
- Any day of the week allowed (not limited to Monday/Sunday) — supports retail, healthcare, and non-standard week starts
- Default for new organizations: Monday
- Setting lives in organization general settings (alongside timezone, currency)
- No per-member override — org-wide setting applies to all members
- DateBoundaryService must accept the org's week start day for all boundary calculations

### Notification bell UI
- Dropdown panel on bell click (popover, not page navigation or drawer)
- Flat chronological list, most recent first — no grouping by type
- Unread count badge caps at "9+" (exact number up to 9, then "9+")
- Clicking a notification navigates directly to the related entity (timesheet, budget, etc.) and marks it as read
- "Mark all as read" action available in the dropdown

### Claude's Discretion
- Email template design (branded HTML vs minimal — pick what fits the codebase and Laravel mail ecosystem)
- Loading states and empty states within the notification dropdown
- Notification dropdown dimensions and scroll behavior
- Exact notification types to define at the infrastructure level (feature-specific types added in their phases)
- Polling interval for notification count refresh

</decisions>

<specifics>
## Specific Ideas

No specific references or "I want it like X" requirements — open to standard approaches that fit the existing solidtime codebase patterns.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 01-shared-foundations*
*Context gathered: 2026-02-10*
