# PRD Review Report — All 10 Features

**Date**: 2026-02-06
**Reviewer**: Claude Opus 4.6 (3 parallel review agents)

---

## Quality Scores

| # | Feature | Score | Tasks | Effort | Duration |
|---|---------|:-----:|:-----:|--------|----------|
| 01 | Timesheet Approvals | 4.5/5 | 30 | 177h / 117 SP | 8 weeks |
| 02 | Expense Management | 4.0/5 | 28 | 218h / 109 SP | 6 weeks |
| 03 | Budgets & Alerts | 4.0/5 | 33 | 164h / 88 SP | 6 weeks |
| 04 | Invoicing System | 4.5/5 | 35 | 310h / 113 SP | 14 weeks |
| 05 | Calendar Enhanced | 4.0/5 | 20 | 145-156h / 73 SP | 6 weeks |
| 06 | Kiosk & Clock Mode | 4.5/5 | 20 | 152h / 76 SP | 8 weeks |
| 07 | PTO & Time Off | 4.5/5 | 33 | 230h / 117 SP | 8 weeks |
| 08 | Resource Scheduling | 4.0/5 | 35 | 225h / 148 SP | 10 weeks |
| 09 | Advanced Reporting | 3.5/5 | 39 | 310h / 195 SP | 12 weeks |
| 10 | Teams & Groups | 4.0/5 | 32 | 152h / 76 SP | 6 weeks |
| | **TOTALS** | **4.1 avg** | **305** | **~2,093h / ~1,122 SP** | **~84 weeks seq** |

---

## CRITICAL Issues (Must Fix Before Architecture)

### CRIT-01: Schema Conflict — `members.weekly_capacity`

**PRDs affected**: 08 (Resource Scheduling) + 09 (Advanced Reporting)

Both PRD 08 TASK-003 and PRD 09 TASK-001 add `weekly_capacity` to the `members` table with different definitions:
- PRD 08: nullable integer
- PRD 09: unsigned integer with default 144000

**Resolution**: Create a single shared migration that both features depend on. Decide on nullable vs. default semantics.

### CRIT-02: Two Incompatible Approval Workflows

**PRDs affected**: 01 (Timesheet Approvals) + 02 (Expense Management)

- PRD 01: Dedicated `TimesheetApproval` model with full state machine, separate Approvals page, mail notifications
- PRD 02: Inline `status` field on `Expense` model, no separate approval UI, no notifications, self-approval explicitly allowed

Users will face two completely different approval UIs and inconsistent self-approval rules.

**Resolution**: Define a shared approval pattern or at minimum align state vocabulary, self-approval policy, and notification strategy.

### CRIT-03: Conflicting Notification Infrastructure

**PRDs affected**: 01, 02, 03

- PRD 01: Explicitly says "no queue-based notification infrastructure" — uses only Mail classes
- PRD 02: Has no notification system at all for approval changes
- PRD 03: Introduces Laravel Notifications with `database` + `mail` channels, assumes in-app notification bell exists

**Resolution**: Decide on unified notification strategy. If in-app notifications are desired (PRD 03's approach), build shared infrastructure first as a Phase 0 dependency.

### CRIT-04: New Member vs. Team Constraint Conflict (PRD 10)

PRD 10 states "new members are NOT auto-assigned to any team" but also "a member must belong to at least one team." These rules directly conflict.

**Resolution**: Either auto-assign to "Default" team or relax the minimum-one-team constraint until team scoping is enabled.

### CRIT-05: PIN Uniqueness Design Problem (PRD 06)

bcrypt produces non-deterministic hashes, making database-level uniqueness enforcement impossible with `pin_hash` alone.

**Resolution**: Add a deterministic hash column (SHA-256 of `org_id + PIN`) with a unique index for collision checking, alongside bcrypt hash for verification.

### CRIT-06: OAuth Callback URL Design (PRD 05)

The callback URL contains `{organization}` placeholder, but OAuth providers require exact redirect URI matches.

**Resolution**: Use a static callback route (e.g., `/api/v1/calendar-integrations/callback`) and pass `organization_id` in the OAuth `state` parameter.

---

## HIGH Priority Issues

### HIGH-01: Missing Cross-Feature Integration Points

No PRD references dependencies on other features. Key missing integrations:

| Feature A | Feature B | Missing Integration |
|-----------|-----------|---------------------|
| 07 (PTO) | 08 (Scheduling) | Approved time-off should reduce scheduling capacity |
| 07 (PTO) | 10 (Teams) | PTO visibility should respect team scoping for managers |
| 08 (Scheduling) | 10 (Teams) | Scheduling views need team-based filtering |
| 09 (Reporting) | 10 (Teams) | New report endpoints need `team_ids` filtering |
| 02 (Expenses) | 03 (Budgets) | Expenses as budget consumption |
| 06 (Kiosk) | 04 (Invoicing) | Kiosk entries with default project could be billable |

### HIGH-02: Missing Feature Flag for Teams (PRD 10)

Team scoping is a breaking behavioral change to query results across projects, clients, and time entries. No feature flag (`enable_team_scoping` on Organization) is defined.

### HIGH-03: Permission Naming Inconsistency Across All PRDs

| PRD | Pattern | Example |
|-----|---------|---------|
| 01 | Hyphenated actions | `timesheets:view-approvals` |
| 02 | Colon scoped | `expenses:view:all` |
| 03 | 3-level colon | `budgets:alerts:manage` |
| 07 | Colon scoped | `time-off-requests:view:own` |
| 08 | Mixed | `assignments:view`, `assignments:view:own` |
| 10 | Hyphenated actions | `teams:update-members` |

**Resolution**: Standardize on `{entity}:{action}:{scope}` pattern across all features.

### HIGH-04: Task ID Collisions

All 10 PRDs use `TASK-001` through `TASK-0XX` with no feature prefix. 305 tasks total with massive ID overlap.

**Resolution**: Prefix with feature code (e.g., `APPR-001`, `EXP-001`, `BUD-001`, `INV-001`, `CAL-001`, `KIO-001`, `PTO-001`, `SCHED-001`, `RPT-001`, `TEAM-001`).

### HIGH-05: Migration Timestamp Conflicts

PRDs 01 and 03 both use `2026_02_07_000001` and `2026_02_07_000002` migration prefixes. All features adding migrations need unique timestamps.

### HIGH-06: JetstreamServiceProvider Merge Contention

All 10 features modify `JetstreamServiceProvider.php` to register permissions. Parallel development guarantees merge conflicts.

**Resolution**: Create a single foundational task to register all permissions, or establish a merge protocol.

### HIGH-07: PRD 09 (Advanced Reporting) Scope Creep

39 tasks, 310 hours, 12 weeks — the largest feature by far. Bundles 7 sub-features. High risk of slippage.

**Resolution**: Split into Phase A (Profitability + Utilization + Cost Rates, Sprints 1-3, ~140h) and Phase B (Schedules + Templates + Expenses + Budget Export, Sprints 4-6, ~170h).

---

## MEDIUM Priority Issues

### Sprint Workload Imbalances

| PRD | Overloaded Sprint | Issue |
|-----|-------------------|-------|
| 01 | Sprint 2 (42 SP) | 2x Sprint 4 (21 SP) |
| 02 | Sprint 1 (70 hours) | Extremely heavy for a 2-week sprint |
| 03 | Sprint 1 (57h / 33-34 SP mismatch) | SP count differs between PRD and task doc |
| 04 | Sprint 2 (56h backend) | Single dev at 140% capacity |
| 05 | Sprint 2 (41h backend) | Single dev overloaded |
| 06 | Sprint 1 | Frontend dev idle (no tasks) |
| 07 | Sprint 4 (46 SP) | 2x Sprint 1-2 (22 SP each) |

### Large Tasks That Should Be Split

| PRD | Task | Hours | Issue |
|-----|------|-------|-------|
| 01 | TASK-006 (ApprovalService) | 16h | 8 downstream dependencies |
| 02 | TASK-007 (ExpenseController) | 16h | Critical path bottleneck |
| 04 | TASK-009 (InvoiceService) | 16h | Underestimated by 4-8h |
| 05 | TASK-003 (CalendarIntegrationService) | 16h | Covers both Google + Microsoft |
| 06 | TASK-008 (Kiosk Full-Screen Page) | 16h | PRD itself suggests splitting |
| 08 | TASK-023 (Timeline/Gantt Component) | 20h | Likely underestimated, no library specified |
| 09 | TASK-009 + TASK-014 | High SP | Identified as bottleneck tasks |

### Testing Deferred to Final Sprints

PRDs 06 (Kiosk), 04 (Invoicing), and 09 (Reporting) defer most testing to the final sprint. Unit tests for service classes should be written alongside implementation.

### Other Medium Issues

- PRD 03: `estimated_time` field overlaps with hours budget — no coexistence guidance
- PRD 05: No data retention/cleanup policy for `calendar_events` table
- PRD 06: QR code 5-minute TTL makes printed badges impractical
- PRD 06: Token-in-URL security concern (appears in browser history, proxy logs)
- PRD 06: Kiosk delete route missing `check-organization-blocked` middleware
- PRD 07: `OvertimeRuleType` enum defined but never used
- PRD 07: Fiscal year support mentioned but no configuration mechanism defined
- PRD 08: Milestone API routes are asymmetric (nested for index/store, flat for update/delete)
- PRD 09: `cost_rate` trigger mechanism on `time_entries` not specified
- PRD 09: Backfill command placed in final sprint but needed earlier for testing
- PRD 09: `ReportTemplate` uses `RESTRICT` on delete vs. codebase convention of `CASCADE`
- PRD 10: Task scoping through parent Project not explicitly documented

---

## Recommendations — Ordered Action Plan

### Phase 0: Cross-Cutting Foundations (Before Architecture)

1. **Standardize permission naming** — single `{entity}:{action}:{scope}` pattern
2. **Assign unique task ID prefixes** per feature
3. **Assign unique migration timestamps** per feature
4. **Design shared notification infrastructure** (database + mail channels, notification bell UI)
5. **Design shared approval abstraction** (or document why timesheet vs. expense differ)
6. **Create shared `weekly_capacity` migration** owned by one feature, depended on by others
7. **Define feature flag mechanism** for Teams scoping
8. **Define deployment order** and inter-feature integration contracts
9. **Establish JetstreamServiceProvider merge protocol**

### Phase 1: Per-PRD Fixes

10. Fix the 6 CRITICAL issues listed above
11. Rebalance overloaded sprints
12. Split tasks >12 hours into sub-tasks
13. Move testing earlier (alongside implementation, not deferred)
14. Split PRD 09 into two delivery phases
15. Reconcile effort estimate discrepancies (PRD body vs. task assignment docs)

### Phase 2: Proceed to Architecture

Once the above are addressed, launch Architecture (code-architect) and Codebase Analysis (code-explorer) agents for Phase 1 features (1, 2, 3, 5, 6, 7, 10).

---

## Appendix: Hour-to-SP Ratio Inconsistency

| PRD | Hours/SP Ratio |
|-----|:--------------:|
| 01 Approvals | 1.5 |
| 02 Expenses | 2.0 |
| 03 Budgets | 1.9 |
| 04 Invoicing | 2.7 |
| 05 Calendar | 2.0 |
| 06 Kiosk | 2.0 |
| 07 PTO | 2.0 |
| 08 Scheduling | 1.5 |
| 09 Reporting | 1.6 |
| 10 Teams | 2.0 |

Ratios range from 1.5 to 2.7. This makes cross-feature velocity comparisons unreliable. Recommend standardizing on 2.0 hours/SP.

Last updated: 2026-02-06
