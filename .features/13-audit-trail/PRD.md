# PRD: Audit Trail / Activity Log

Generated: 2026-02-09
Version: 1.0
Feature Branch: `feature/audit-trail` (from `main`)

---

## Table of Contents

1. [Source & Context](#1-source--context)
2. [Technical Interpretation](#2-technical-interpretation)
3. [Functional Specifications](#3-functional-specifications)
4. [Technical Requirements & Constraints](#4-technical-requirements--constraints)
5. [User Stories with Acceptance Criteria](#5-user-stories-with-acceptance-criteria)
6. [Task Breakdown Structure](#6-task-breakdown-structure)
7. [Dependencies & Integration Points](#7-dependencies--integration-points)
8. [Risk Assessment & Mitigation](#8-risk-assessment--mitigation)
9. [Testing & Validation Requirements](#9-testing--validation-requirements)
10. [Monitoring & Observability](#10-monitoring--observability)
11. [Success Metrics & Definition of Done](#11-success-metrics--definition-of-done)
12. [Technical Debt & Future Considerations](#12-technical-debt--future-considerations)
13. [Appendices](#13-appendices)

---

## 1. Source & Context

### 1.1 Problem Statement

Solidtime already captures audit data for every model mutation. The `CustomAuditable` trait (wrapping `OwenIt\Auditing\Auditable`) is applied to all 10 core models: `TimeEntry`, `Project`, `Task`, `Client`, `Tag`, `Member`, `Organization`, `User`, `ProjectMember`, and `OrganizationInvitation`. Every create, update, and delete event is written to the `audits` table with before/after values, the acting user, IP address, user agent, and timestamp.

However, there is **no user-facing interface** to access this data. The audit records accumulate silently in the database with no way for managers, admins, or owners to:

- View a chronological log of changes across the organization
- Filter audit records by entity type, user, date range, or event type
- Drill into a specific record to see exactly which fields changed
- Search for changes to a particular resource (e.g., "all changes to project X")
- Export audit data for compliance, billing disputes, or external review

Without this feature:
- **Billing disputes** cannot be resolved by reviewing who changed time entries and when
- **Compliance requirements** (SOC 2, ISO 27001) that mandate accessible audit trails are unmet
- **Anomaly detection** by managers is impossible -- they cannot see if someone retroactively edited old time entries
- **Data governance** is weakened because there is no accountability layer visible to administrators

### 1.2 Competitive Analysis

From **features.txt** Section 4.4 -- "Audit trail / activity log":

> **What**: History of edits and who changed what.
> **Why important**: Governance for billing disputes and compliance.
> **User flow**:
> 1. System logs create/edit/delete actions.
> 2. Manager audits changes for anomalies.
> 3. Admin exports audit data if required.
>
> (Harvest "activity log"; TimeCamp lists "audit log" at enterprise tier.)

Platforms offering this feature:
- **Harvest**: Full activity log showing all changes with user attribution and timestamps
- **TimeCamp**: Audit log available at enterprise tier with filtering and export
- **Clockify**: Audit log for workspace-level changes with detailed diffs
- **Hubstaff**: Activity logs with filtering by user and date range

### 1.3 Existing System State

**Existing infrastructure on `main`:**

- **`audits` table** (migration `2024_09_02_094105_create_audits_table.php`):
  - `id` (bigint, auto-increment)
  - `user_type` (string, nullable) -- morph type of acting user
  - `user_id` (uuid, nullable) -- ID of acting user
  - `event` (string) -- `created`, `updated`, `deleted`, `restored`
  - `auditable_type` (string) -- morph type of affected model
  - `auditable_id` (uuid) -- ID of affected model
  - `old_values` (json, nullable) -- attribute values before change
  - `new_values` (json, nullable) -- attribute values after change
  - `url` (text, nullable) -- request URL that triggered the change
  - `ip_address` (ip, nullable) -- anonymized IP via `CustomIpAddressResolver`
  - `user_agent` (string 1023, nullable)
  - `tags` (string, nullable)
  - `created_at`, `updated_at` (timestamps)
  - Index on `[user_id, user_type]`

- **`App\Models\Audit`** extends `OwenIt\Auditing\Models\Audit` with `HasFactory`
- **`Database\Factories\AuditFactory`** with `auditUser()` and `auditFor()` state methods
- **`App\Models\Concerns\CustomAuditable`** trait used by all 10 models
- **`App\Extensions\Auditing\Resolvers\CustomIpAddressResolver`** anonymizes IPs before storage
- **Config** (`config/audit.php`):
  - `enabled` defaults to `false` (env-controlled via `AUDITING_ENABLED`)
  - Events: `created`, `updated`, `deleted`, `restored`
  - Strict mode: `true`
  - Empty values: `false` (no audit record when both old/new are empty)
  - Array values: `allowed`
  - Timestamps audited: `false`
  - Driver: `database` to `audits` table
  - Queue: disabled (synchronous writes)
- **Permission system**: `{entity}:{action}:{scope}` pattern via `CorePermissions`
- **Roles**: Owner, Admin, Manager, Employee, Placeholder
- **Models using `CustomAuditable`**: TimeEntry, Project, Task, Client, Tag, Member, Organization, User, ProjectMember, OrganizationInvitation

**Key constraint**: The `audits` table has **no `organization_id` column**. Organization scoping must be derived indirectly through the `auditable_type` + `auditable_id` relationship (e.g., a TimeEntry audit record's organization is found via `TimeEntry.organization_id`). This is the primary technical challenge.

---

## 2. Technical Interpretation

### Business to Technical Translation

| Business Requirement | Technical Implementation |
|---------------------|-------------------------|
| View chronological activity log | Paginated API endpoint querying `audits` table with eager-loaded relationships |
| Filter by entity type | WHERE clause on `auditable_type` column (morph class) |
| Filter by user who made change | WHERE clause on `user_id` column |
| Filter by date range | WHERE clause on `audits.created_at` |
| Filter by event type | WHERE clause on `event` column (`created`, `updated`, `deleted`) |
| See what changed (diff view) | Parse `old_values` / `new_values` JSON columns and render side-by-side diff |
| Search for changes to a specific entity | WHERE clause on `auditable_type` + `auditable_id` |
| Organization scoping | Migration to add `organization_id` to `audits` table + backfill from related models |
| Export audit data | CSV/JSON export endpoint with same filter parameters |
| Role-based access | New `audit-logs:view` and `audit-logs:export` permissions for Owner/Admin/Manager |

### Architecture Decision: Adding `organization_id` to `audits`

The existing `audits` table lacks an `organization_id` column. Without it, scoping audit records to an organization requires joining through each auditable model -- an N+1 query problem across 10 different model types that would be prohibitively slow for paginated listing.

**Decision**: Add an `organization_id` column to the `audits` table via migration, with a backfill command for existing data. The `CustomAuditable` trait will be extended to automatically populate `organization_id` on new audit records.

This is the minimal, highest-impact schema change and keeps all filtering/pagination queries efficient with a single indexed column.

### No-Change Boundary

This feature does **NOT**:
- Modify the core `OwenIt\Auditing` package behavior
- Change which events are audited or which models use `CustomAuditable`
- Add audit logging to any models that do not already use it
- Provide real-time streaming or WebSocket-based live log tailing
- Implement log retention policies or automatic purging (future consideration)
- Add write endpoints for audit records (audit logs are immutable)
- Modify the `CustomIpAddressResolver` anonymization behavior

---

## 3. Functional Specifications

### 3.1 Core Requirements

#### REQ-001: Audit Log List View
- **Description**: Display a paginated, chronological list of audit records scoped to the current organization
- **Priority**: P0
- **Default View**: Most recent first, 50 records per page
- **Each Row Displays**:
  - Timestamp (formatted per organization date/time settings)
  - Event type badge (Created / Updated / Deleted / Restored)
  - Entity type label (e.g., "Time Entry", "Project", "Task")
  - Entity name or identifier (e.g., project name, task name, member email)
  - Acting user name and avatar
  - Brief summary of changes (e.g., "Changed description, updated end time")
- **Edge Cases**:
  - Audit record references a deleted entity (show "[Deleted]" with ID)
  - Audit record has no acting user (system/console action -- show "System")
  - Organization has no audit records (show empty state with explanation that auditing may need to be enabled)
  - `AUDITING_ENABLED=false` in environment (show warning banner)
- **Error Scenarios**:
  - API failure loading audit records (show error state with retry)
  - Slow query due to large dataset (loading skeleton, then results)

#### REQ-002: Filtering
- **Description**: Allow users to filter the audit log by multiple criteria simultaneously
- **Priority**: P0
- **Filters**:
  - **Entity Type**: Multi-select dropdown (Time Entry, Project, Task, Client, Tag, Member, Organization, Project Member)
  - **Event Type**: Multi-select (Created, Updated, Deleted, Restored)
  - **User**: Searchable dropdown of organization members
  - **Date Range**: Start date and end date pickers
  - **Entity ID**: Text input for searching changes to a specific resource (advanced filter)
- **Behavior**:
  - Filters are additive (AND logic)
  - Active filters shown as removable chips/badges above the list
  - Filter state persisted in URL query parameters for shareability
  - "Clear All Filters" button resets to default view
  - Filters applied via API query parameters (server-side filtering)

#### REQ-003: Audit Detail / Diff View
- **Description**: Click on any audit row to see the full detail of what changed
- **Priority**: P0
- **Detail Panel Shows**:
  - Full timestamp with timezone
  - Event type
  - Entity type and identifier
  - Acting user with email
  - IP address (anonymized)
  - User agent (browser/client)
  - Request URL that triggered the change
  - **Diff Table**: Side-by-side comparison of old values vs new values
    - Changed fields highlighted
    - Unchanged fields hidden by default (toggle to show all)
    - JSON values pretty-printed
    - UUID fields resolved to human-readable names where possible (e.g., `project_id` shows project name)
- **Implementation**: Slide-over panel or expandable row (not a separate page)
- **Edge Cases**:
  - `created` events have no `old_values` (show only new values)
  - `deleted` events have no `new_values` (show only old values)
  - Values contain sensitive data (billable_rate is excluded by model's `auditExclude`)
  - Large JSON diffs (truncate with "Show More" toggle)

#### REQ-004: Export Audit Logs
- **Description**: Export filtered audit records as CSV or JSON for compliance and external review
- **Priority**: P1
- **Export Formats**: CSV, JSON
- **Behavior**:
  - Export respects current filter state (same filters as list view)
  - Maximum export limit: 10,000 records (with warning if limit reached)
  - Export triggers a file download (not streamed)
  - CSV columns: Timestamp, Event, Entity Type, Entity ID, Entity Name, User, User Email, Changed Fields, Old Values, New Values, IP Address, URL
  - JSON format: Array of full audit record objects with resolved names
- **Access**: Only users with `audit-logs:export` permission

#### REQ-005: Audit Log Navigation
- **Description**: Dedicated page accessible from the sidebar navigation
- **Priority**: P0
- **Location**: Sidebar item under organization management section (after "Members", before "Import"/"Export")
- **Icon**: `ClipboardDocumentListIcon` from `@heroicons/vue/20/solid`
- **Label**: "Audit Log"
- **Visibility**: Only shown to users with `audit-logs:view` permission
- **URL**: `/audit-log` (web route) mapped to `AuditLog.vue` Inertia page

#### REQ-006: Entity-Scoped Audit View
- **Description**: From any entity detail page (project, task, client, member), link to a pre-filtered audit log showing only changes to that entity
- **Priority**: P2
- **Implementation**: Link/button on entity pages that navigates to `/audit-log?auditable_type=time-entry&auditable_id={id}`
- **Display**: Same audit log list view but with entity filter pre-applied

### 3.2 User Workflows

```
Admin/Manager opens Audit Log page
    -> Load paginated audit records (most recent first)
    -> Display list with event badges, entity info, user info, timestamps

User applies filters
    -> Select "Time Entry" from entity type dropdown
    -> Select "Updated" from event type
    -> Set date range to "Last 7 days"
    -> Click "Apply" or filters apply immediately
    -> List refreshes with filtered results
    -> URL updates with query parameters
    -> Active filters shown as chips above list

User clicks an audit row
    -> Slide-over panel opens on the right
    -> Shows full audit detail with diff view
    -> Old values on left, new values on right
    -> Changed fields highlighted in yellow
    -> Click "Close" or click outside to dismiss

User exports audit data
    -> Click "Export" button in toolbar
    -> Select format (CSV or JSON)
    -> Current filters applied to export
    -> File downloads to browser

Manager investigates a billing dispute
    -> Filter by entity type "Time Entry"
    -> Filter by date range covering the disputed period
    -> Filter by the specific user
    -> Review all time entry changes
    -> Click individual records to see before/after values
    -> Export the filtered results as evidence
```

### 3.3 Business Rules

#### Organization Scoping
1. Audit records are scoped to the current organization via `organization_id` on the `audits` table
2. The `Organization` model's own audit records are included (where `auditable_type` is Organization and `auditable_id` matches)
3. Records for models without an `organization_id` column (e.g., `User`) are scoped by checking membership: only show User audit records for users who are members of the current organization

#### Permission Model
1. **`audit-logs:view`**: Can view the audit log list and detail view. Granted to Owner, Admin, Manager.
2. **`audit-logs:export`**: Can export audit data. Granted to Owner, Admin only.
3. Employees and Placeholders have **no access** to the audit log.

#### Display Rules
1. Entity names are resolved where possible (e.g., show project name instead of UUID)
2. If the related entity has been deleted, show "[Deleted] {entity_type} ({id truncated})"
3. `old_values` and `new_values` are stored as JSON -- parse and display as key-value diff
4. Fields listed in a model's `$auditExclude` (e.g., `billable_rate` on TimeEntry) will not appear in audit records
5. Timestamps in audit records are displayed in the organization's configured timezone and date format
6. IP addresses are already anonymized at write time by `CustomIpAddressResolver` -- display as-is

#### Pagination
1. Default page size: 50 records
2. Cursor-based pagination for consistent performance with large datasets
3. "Load More" button at bottom (not traditional page numbers) for simpler UX on large datasets

---

## 4. Technical Requirements & Constraints

### 4.1 System Architecture

```
+----------------------------------------------------------------------+
|                        Frontend (Vue.js 3)                            |
+----------------------------------------------------------------------+
|  +----------------+  +---------------------+  +--------------------+  |
|  | AuditLog.vue   |--| AuditLogList.vue    |--| AuditLogDetail.vue |  |
|  | (Page)         |  | (Table + Filters)   |  | (Slide-over diff)  |  |
|  | - Filter bar   |  | - Paginated rows    |  | - Old/New values   |  |
|  | - Export btn   |  | - Load More         |  | - Field highlighting|  |
|  +-------+--------+  +---------------------+  +--------------------+  |
|          |                                                            |
|          v                                                            |
|  +----------------------------------------------+                    |
|  | useAuditLogStore.ts (Pinia)                   |                    |
|  | - auditRecords: AuditRecord[]                 |                    |
|  | - filters: AuditLogFilters                    |                    |
|  | - pagination: CursorPagination                |                    |
|  | - selectedRecord: AuditRecord | null          |                    |
|  | - loadAuditLogs() / loadMore()                |                    |
|  | - setFilters() / clearFilters()               |                    |
|  | - selectRecord() / exportLogs()               |                    |
|  +--------------------+--------------------------+                    |
|                       | HTTP/JSON                                     |
+----------------------------------------------------------------------+
                        v
+----------------------------------------------------------------------+
|                        Backend (Laravel 11)                           |
+----------------------------------------------------------------------+
|  +----------------------------------------------+                    |
|  | AuditLogController.php                        |                    |
|  | - index()    GET /audit-logs                  |                    |
|  | - show()     GET /audit-logs/{audit}          |                    |
|  | - export()   GET /audit-logs/export           |                    |
|  +--------------------+--------------------------+                    |
|                       |                                               |
|                       v                                               |
|  +----------------------------------------------+                    |
|  | AuditLogService.php                           |                    |
|  | - getAuditLogs(filters, pagination)           |                    |
|  | - getAuditDetail(auditId)                     |                    |
|  | - exportAuditLogs(filters, format)            |                    |
|  | - resolveEntityName(type, id)                 |                    |
|  | - buildOrganizationScopeQuery(orgId)          |                    |
|  +--------------------+--------------------------+                    |
|                       |                                               |
|                       v                                               |
|  +----------------------------------------------+                    |
|  | Audit Model (existing, extended)              |                    |
|  | + organization_id (new column via migration)  |                    |
|  +----------------------------------------------+                    |
+----------------------------------------------------------------------+
```

### 4.2 Data Models

#### Database Migration: Add `organization_id` to `audits`

```sql
-- Migration: 2026_03_13_000001_add_organization_id_to_audits_table.php

ALTER TABLE audits
ADD COLUMN organization_id CHAR(36) NULL AFTER tags;

CREATE INDEX idx_audits_organization_id ON audits(organization_id);
CREATE INDEX idx_audits_org_created ON audits(organization_id, created_at DESC);
CREATE INDEX idx_audits_org_type_created ON audits(organization_id, auditable_type, created_at DESC);
```

#### Backfill Strategy

An artisan command `audit:backfill-organization-ids` will populate `organization_id` for existing records:

1. For models with a direct `organization_id` column (TimeEntry, Project, Task, Client, Tag, Member, ProjectMember, OrganizationInvitation): join on `auditable_type` + `auditable_id` and copy the model's `organization_id`.
2. For `Organization` model audits: set `organization_id` = `auditable_id`.
3. For `User` model audits: resolve via the `members` table -- find the user's memberships and assign the audit to each organization. If a user belongs to multiple organizations, assign to the organization contextually determined by the request URL, or the user's current organization if ambiguous.

#### CustomAuditable Trait Extension

```php
// Extend CustomAuditable to populate organization_id on new audit records
// The trait will set organization_id in the audit's additional data
// using a custom audit driver or the model's organization_id attribute
```

#### Frontend Types (TypeScript)

```typescript
interface AuditRecord {
  id: number;
  event: 'created' | 'updated' | 'deleted' | 'restored';
  auditable_type: string;           // e.g., "time-entry", "project"
  auditable_type_label: string;     // e.g., "Time Entry", "Project"
  auditable_id: string;             // UUID
  auditable_name: string | null;    // Resolved name or null if deleted
  user_id: string | null;
  user_name: string | null;
  user_email: string | null;
  user_profile_photo_url: string | null;
  old_values: Record<string, unknown> | null;
  new_values: Record<string, unknown> | null;
  changed_fields: string[];         // List of fields that changed
  change_summary: string;           // e.g., "Changed description, end"
  ip_address: string | null;
  user_agent: string | null;
  url: string | null;
  created_at: string;               // ISO 8601
}

interface AuditLogFilters {
  auditable_type: string[];         // e.g., ["time-entry", "project"]
  event: string[];                  // e.g., ["created", "updated"]
  user_id: string | null;           // Filter by acting user
  date_from: string | null;         // YYYY-MM-DD
  date_to: string | null;           // YYYY-MM-DD
  auditable_id: string | null;      // Filter by specific entity
}

interface CursorPagination {
  cursor: string | null;            // Opaque cursor for next page
  per_page: number;                 // Default 50
  has_more: boolean;
}

interface AuditLogListResponse {
  data: AuditRecord[];
  meta: {
    cursor: string | null;
    per_page: number;
    has_more: boolean;
    total_count: number;            // Approximate count for display
  };
}

interface AuditLogDetailResponse {
  data: AuditRecord & {
    resolved_old_values: Record<string, { raw: unknown; display: string }>;
    resolved_new_values: Record<string, { raw: unknown; display: string }>;
  };
}

interface AuditableTypeOption {
  value: string;                    // e.g., "time-entry"
  label: string;                    // e.g., "Time Entry"
}

interface MemberOption {
  id: string;
  name: string;
  email: string;
  profile_photo_url: string | null;
}
```

### 4.3 API Contracts

#### GET /api/v1/organizations/{organization}/audit-logs
Fetch paginated, filtered audit log records.

```yaml
Parameters:
  organization: string (path, required)
  cursor: string (query, optional) - Opaque pagination cursor
  per_page: int (query, optional, default: 50, max: 100)
  auditable_type: string[] (query, optional) - Filter by entity type(s)
    Valid values: time-entry, project, task, client, tag, member,
                  organization, project-member, organization-invitation, user
  event: string[] (query, optional) - Filter by event type(s)
    Valid values: created, updated, deleted, restored
  user_id: string (query, optional) - Filter by acting user UUID
  date_from: string (query, optional, YYYY-MM-DD) - Start of date range (inclusive)
  date_to: string (query, optional, YYYY-MM-DD) - End of date range (inclusive)
  auditable_id: string (query, optional) - Filter by specific entity UUID
Request Headers:
  Authorization: Bearer {token}
Response 200:
  data: Array<AuditRecord>
  meta:
    cursor: string|null
    per_page: int
    has_more: boolean
    total_count: int
Response 403:
  message: "This action is unauthorized."
Permission: audit-logs:view
```

#### GET /api/v1/organizations/{organization}/audit-logs/{audit}
Fetch a single audit record with fully resolved detail.

```yaml
Parameters:
  organization: string (path, required)
  audit: int (path, required) - Audit record ID
Request Headers:
  Authorization: Bearer {token}
Response 200:
  data:
    id: int
    event: string
    auditable_type: string
    auditable_type_label: string
    auditable_id: string
    auditable_name: string|null
    user_id: string|null
    user_name: string|null
    user_email: string|null
    user_profile_photo_url: string|null
    old_values: object|null
    new_values: object|null
    resolved_old_values: object|null   # UUIDs resolved to names
    resolved_new_values: object|null   # UUIDs resolved to names
    changed_fields: string[]
    change_summary: string
    ip_address: string|null
    user_agent: string|null
    url: string|null
    created_at: string
Response 404:
  message: "Audit record not found."
Permission: audit-logs:view
```

#### GET /api/v1/organizations/{organization}/audit-logs/export
Export filtered audit records as a downloadable file.

```yaml
Parameters:
  organization: string (path, required)
  format: string (query, required) - "csv" or "json"
  auditable_type: string[] (query, optional) - Same filter as index
  event: string[] (query, optional) - Same filter as index
  user_id: string (query, optional) - Same filter as index
  date_from: string (query, optional) - Same filter as index
  date_to: string (query, optional) - Same filter as index
  auditable_id: string (query, optional) - Same filter as index
Request Headers:
  Authorization: Bearer {token}
Response 200:
  Content-Type: text/csv or application/json
  Content-Disposition: attachment; filename="audit-log-{org_name}-{date}.{ext}"
  Body: File content (max 10,000 records)
Response 403:
  message: "This action is unauthorized."
Response 422:
  message: "Export limit exceeded. Please narrow your filters."
Permission: audit-logs:export
```

### 4.4 Performance Requirements

| Metric | Target | Measurement |
|--------|--------|-------------|
| Audit log page load | < 800ms | Time to first list render (50 records) |
| Filter application | < 500ms | API response time for filtered query |
| Detail panel open | < 300ms | API response time for single record with resolved names |
| Export (1,000 records) | < 5s | Time to generate and begin download |
| Export (10,000 records) | < 30s | Time to generate and begin download |
| Pagination (cursor) | < 500ms | API response time for next page |

### 4.5 Security Requirements

1. **Authorization**: Only users with `audit-logs:view` permission can access the audit log endpoints. Only Owner and Admin roles receive `audit-logs:export`.
2. **Organization Scoping**: All queries strictly scoped to `organization_id` matching the route's organization. No cross-organization data leakage.
3. **Immutability**: No create, update, or delete endpoints for audit records. Audit data is read-only via the API.
4. **IP Anonymization**: IP addresses are already anonymized at write time by `CustomIpAddressResolver`. No additional anonymization needed at read time.
5. **Rate Limiting**: Export endpoint rate-limited to prevent abuse (5 exports per hour per user).
6. **Input Validation**: All filter parameters validated (valid UUIDs, valid date formats, valid enum values).
7. **No Sensitive Data**: Fields excluded by `$auditExclude` on models (e.g., `billable_rate`) never appear in audit records and thus never in the API response.

---

## 5. User Stories with Acceptance Criteria

### USR-001: View Organization Audit Log
**As an** organization admin
**I want to** see a chronological log of all changes made within my organization
**So that** I can monitor activity and ensure accountability

**Priority**: P0 | **Effort**: 8 SP | **Sprint**: 1-2

**Acceptance Criteria**:
- [ ] Audit log page accessible from sidebar navigation
- [ ] Page displays paginated list of audit records, most recent first
- [ ] Each row shows: timestamp, event badge, entity type, entity name, acting user, change summary
- [ ] Loading skeleton shown while data loads
- [ ] Empty state shown when no records exist
- [ ] "Load More" button at bottom fetches next page
- [ ] Page is only visible to users with `audit-logs:view` permission
- [ ] Records are scoped to the current organization only

### USR-002: Filter Audit Records
**As a** manager investigating changes
**I want to** filter the audit log by entity type, user, event type, and date range
**So that** I can quickly find the specific changes I am looking for

**Priority**: P0 | **Effort**: 5 SP | **Sprint**: 2

**Acceptance Criteria**:
- [ ] Filter bar at top of audit log page with dropdowns for each filter
- [ ] Entity type filter: multi-select with all audited model types
- [ ] Event type filter: multi-select (Created, Updated, Deleted, Restored)
- [ ] User filter: searchable dropdown of organization members
- [ ] Date range filter: start and end date pickers
- [ ] Filters applied via URL query parameters (shareable URLs)
- [ ] Active filters displayed as removable chips
- [ ] "Clear All Filters" button resets to default
- [ ] Filters are applied server-side (API query parameters)
- [ ] Result count displayed (e.g., "Showing 50 of 1,234 records")

### USR-003: View Audit Detail with Diff
**As a** manager reviewing a specific change
**I want to** see exactly which fields were modified and what the before/after values are
**So that** I can understand the full context of a change

**Priority**: P0 | **Effort**: 5 SP | **Sprint**: 2

**Acceptance Criteria**:
- [ ] Clicking an audit row opens a slide-over detail panel
- [ ] Panel shows full timestamp, event type, entity info, acting user
- [ ] Panel shows metadata: IP address, user agent, request URL
- [ ] Diff table shows old values vs new values side-by-side
- [ ] Changed fields are visually highlighted
- [ ] UUID values resolved to human-readable names where possible
- [ ] Created events show only new values (no old values)
- [ ] Deleted events show only old values (no new values)
- [ ] Close button and click-outside-to-dismiss behavior
- [ ] Keyboard accessible (Escape to close)

### USR-004: Export Audit Data
**As an** admin preparing for a compliance review
**I want to** export audit log data as CSV or JSON
**So that** I can provide evidence to auditors or resolve billing disputes

**Priority**: P1 | **Effort**: 5 SP | **Sprint**: 3

**Acceptance Criteria**:
- [ ] "Export" button visible in audit log toolbar
- [ ] Clicking opens a dropdown to choose format (CSV or JSON)
- [ ] Export respects all currently active filters
- [ ] CSV includes columns: Timestamp, Event, Entity Type, Entity ID, Entity Name, User, Email, Changed Fields, Old Values, New Values, IP Address, URL
- [ ] JSON includes full audit record objects
- [ ] File downloads automatically with descriptive filename
- [ ] Warning shown if export would exceed 10,000 records
- [ ] Export button only visible to users with `audit-logs:export` permission
- [ ] Loading indicator while export generates

### USR-005: Navigate to Audit Log from Entity
**As a** manager viewing a project's details
**I want to** quickly see all audit history for that specific project
**So that** I can track all changes made to it

**Priority**: P2 | **Effort**: 3 SP | **Sprint**: 3

**Acceptance Criteria**:
- [ ] "View History" link/button on entity detail pages (Project, Task, Client, Member)
- [ ] Clicking navigates to audit log page with entity filter pre-applied
- [ ] URL contains the entity type and ID as query parameters
- [ ] Audit log loads showing only changes to that specific entity
- [ ] User can clear the filter to see full organization log

### USR-006: Audit Log Permissions
**As a** system administrator
**I want** the audit log to be restricted to appropriate roles
**So that** sensitive change history is not visible to regular employees

**Priority**: P0 | **Effort**: 2 SP | **Sprint**: 1

**Acceptance Criteria**:
- [ ] New permission `audit-logs:view` added to Owner, Admin, Manager roles
- [ ] New permission `audit-logs:export` added to Owner, Admin roles only
- [ ] Employees see no "Audit Log" item in sidebar
- [ ] Employees receive 403 if they attempt to access audit log API endpoints
- [ ] Placeholder users have no audit log access

---

## 6. Task Breakdown Structure

See `task_assignments_20260209.md` for the full task table.

| Task ID | Description | Type | Effort | Dependencies |
|---------|-------------|------|--------|--------------|
| AUD-001 | Database migration: add `organization_id` to `audits` table | Backend | 4h | None |
| AUD-002 | Artisan command: backfill `organization_id` on existing audit records | Backend | 8h | AUD-001 |
| AUD-003 | Extend `CustomAuditable` trait to auto-populate `organization_id` | Backend | 4h | AUD-001 |
| AUD-004 | Register `audit-logs:view` and `audit-logs:export` permissions | Backend | 4h | None |
| AUD-005 | Create `AuditLogService` with query, detail, and export logic | Backend | 16h | AUD-001, AUD-003 |
| AUD-006 | Create `AuditLogController` with index, show, export endpoints | Backend | 8h | AUD-005, AUD-004 |
| AUD-007 | Create request validation classes | Backend | 4h | AUD-006 |
| AUD-008 | Register API routes for audit log endpoints | Backend | 2h | AUD-006 |
| AUD-009 | Create `AuditLogResource` and `AuditLogCollection` API resources | Backend | 4h | AUD-005 |
| AUD-010 | Update OpenAPI spec + regenerate TypeScript client | Backend | 4h | AUD-008, AUD-009 |
| AUD-011 | Create `AuditLog.vue` Inertia page | Frontend | 4h | AUD-010 |
| AUD-012 | Create `AuditLogList.vue` component (table with pagination) | Frontend | 12h | AUD-011 |
| AUD-013 | Create `AuditLogFilters.vue` component (filter bar) | Frontend | 8h | AUD-011 |
| AUD-014 | Create `AuditLogDetail.vue` slide-over component (diff view) | Frontend | 10h | AUD-012 |
| AUD-015 | Create `AuditLogExport.vue` component (export dropdown) | Frontend | 4h | AUD-012 |
| AUD-016 | Create `useAuditLogStore.ts` Pinia store + TypeScript types | Frontend | 8h | AUD-010 |
| AUD-017 | Add web route + sidebar navigation item | Frontend | 2h | AUD-011 |
| AUD-018 | Add "View History" links to entity detail pages | Frontend | 4h | AUD-012, AUD-013 |
| AUD-019 | Backend endpoint tests (PHPUnit) | Testing | 10h | AUD-006, AUD-008 |
| AUD-020 | Service layer unit tests (PHPUnit) | Testing | 8h | AUD-005 |
| AUD-021 | Backfill command tests (PHPUnit) | Testing | 4h | AUD-002 |
| AUD-022 | Frontend component tests (Vitest) | Testing | 8h | AUD-012, AUD-013, AUD-014 |
| AUD-023 | E2E Playwright tests | Testing | 8h | AUD-017, AUD-012 |
| AUD-024 | JSDoc comments on Pinia store and key components | Docs | 2h | AUD-016 |

**Total Effort**: 140 hours (~70 SP across 3 sprints)

### Task Details

#### AUD-001: Database Migration -- Add `organization_id` to `audits`

**Type**: Backend
**Effort**: 4h
**Dependencies**: None

**Files to create**:
- `database/migrations/2026_03_13_000001_add_organization_id_to_audits_table.php`

**Implementation Details**:
```php
// Migration adds nullable UUID column organization_id
// Adds composite indexes for efficient querying:
//   - idx_audits_organization_id (organization_id)
//   - idx_audits_org_created (organization_id, created_at DESC)
//   - idx_audits_org_type_created (organization_id, auditable_type, created_at DESC)
// Column is nullable because:
//   1. Existing records need backfilling
//   2. Some audit events (User model) may span organizations
```

**Acceptance Criteria**:
- [ ] Migration runs without errors on existing database
- [ ] Migration rollback drops column and indexes cleanly
- [ ] Column is nullable UUID type
- [ ] Three indexes created for query performance

---

#### AUD-002: Artisan Command -- Backfill Organization IDs

**Type**: Backend
**Effort**: 8h
**Dependencies**: AUD-001

**Files to create**:
- `app/Console/Commands/BackfillAuditOrganizationIds.php`

**Implementation Details**:
```php
// Command: php artisan audit:backfill-organization-ids
// Strategy:
//   1. Process in chunks of 1000 records
//   2. For each auditable_type, join the corresponding model table
//      to resolve organization_id
//   3. Special cases:
//      - Organization model: organization_id = auditable_id
//      - User model: resolve via members table (user's primary organization)
//   4. Progress bar showing completion percentage
//   5. --dry-run flag to preview counts without writing
//   6. Idempotent: only updates records where organization_id IS NULL
```

**Acceptance Criteria**:
- [ ] Command processes all existing audit records
- [ ] Handles all 10 auditable model types correctly
- [ ] Progress bar shows real-time completion status
- [ ] `--dry-run` flag works without modifying data
- [ ] Idempotent -- safe to run multiple times
- [ ] Performance: handles 100K+ records without memory exhaustion

---

#### AUD-003: Extend `CustomAuditable` Trait

**Type**: Backend
**Effort**: 4h
**Dependencies**: AUD-001

**Files to modify**:
- `app/Models/Concerns/CustomAuditable.php`

**Implementation Details**:
```php
// Override the generateTags() or transformAudit() method to inject
// organization_id into new audit records.
//
// For models with organization_id attribute: use $this->organization_id
// For Organization model: use $this->getKey()
// For User model: resolve from current request context
//
// The owen-it/laravel-auditing package supports custom audit morphs
// and the Audit model can be extended to accept additional columns.
```

**Acceptance Criteria**:
- [ ] New audit records automatically have `organization_id` set
- [ ] Works for all 10 models using `CustomAuditable`
- [ ] Organization model audits reference their own ID
- [ ] No regression on existing audit behavior

---

#### AUD-004: Register Audit Log Permissions

**Type**: Backend
**Effort**: 4h
**Dependencies**: None

**Files to create**:
- `app/Permissions/AuditLogPermissions.php`

**Files to modify**:
- `app/Permissions/CorePermissions.php` (add `audit-logs:view` to Owner, Admin, Manager; `audit-logs:export` to Owner, Admin)
- `app/Providers/JetstreamServiceProvider.php` (add `AuditLogPermissions::register()` call)

**Implementation Details**:
```php
// Following the SF-08 modular permissions pattern:
//
// Owner/Admin: audit-logs:view, audit-logs:export
// Manager: audit-logs:view
// Employee: (none)
// Placeholder: (none)
//
// Since CorePermissions already defines all role permission arrays,
// and the SF-08 pattern suggests separate permission files per feature,
// create AuditLogPermissions.php that adds to existing roles.
```

**Acceptance Criteria**:
- [ ] `audit-logs:view` granted to Owner, Admin, Manager
- [ ] `audit-logs:export` granted to Owner, Admin only
- [ ] Employee and Placeholder have no audit log permissions
- [ ] Permissions follow SF-02 naming convention

---

#### AUD-005: Create `AuditLogService`

**Type**: Backend
**Effort**: 16h
**Dependencies**: AUD-001, AUD-003

**Files to create**:
- `app/Service/AuditLogService.php`

**Implementation Details**:
```php
class AuditLogService
{
    /**
     * Get paginated audit logs for an organization with filtering.
     * Uses cursor-based pagination for consistent performance.
     * Eager-loads user relationship for acting user info.
     * Resolves entity names where possible.
     */
    public function getAuditLogs(
        Organization $organization,
        AuditLogFilters $filters,
        ?string $cursor,
        int $perPage
    ): CursorPaginator { }

    /**
     * Get a single audit record with fully resolved detail.
     * Resolves UUID values in old_values/new_values to human-readable names.
     * E.g., project_id -> project name, task_id -> task name.
     */
    public function getAuditDetail(
        Organization $organization,
        int $auditId
    ): Audit { }

    /**
     * Export audit logs matching filters as CSV or JSON.
     * Maximum 10,000 records per export.
     * Returns a file path for download.
     */
    public function exportAuditLogs(
        Organization $organization,
        AuditLogFilters $filters,
        string $format
    ): string { }

    /**
     * Resolve an entity name from its type and ID.
     * Returns null if entity has been deleted.
     * Caches resolutions for the duration of the request.
     */
    private function resolveEntityName(
        string $auditableType,
        string $auditableId
    ): ?string { }

    /**
     * Map morph class names to human-readable labels.
     * E.g., "App\Models\TimeEntry" -> "Time Entry"
     */
    private function getEntityTypeLabel(string $auditableType): string { }

    /**
     * Generate a change summary from old_values and new_values.
     * E.g., "Changed description, end" for an updated time entry.
     */
    private function generateChangeSummary(
        ?array $oldValues,
        ?array $newValues,
        string $event
    ): string { }

    /**
     * Resolve UUID values in old/new values to display names.
     * E.g., project_id UUID -> "Acme Website Redesign"
     */
    private function resolveFieldValues(
        ?array $values,
        string $auditableType
    ): array { }

    /**
     * Map of auditable_type morph names to model classes.
     */
    private const AUDITABLE_TYPE_MAP = [
        'time-entry' => TimeEntry::class,
        'project' => Project::class,
        'task' => Task::class,
        'client' => Client::class,
        'tag' => Tag::class,
        'member' => Member::class,
        'organization' => Organization::class,
        'project-member' => ProjectMember::class,
        'organization-invitation' => OrganizationInvitation::class,
        'user' => User::class,
    ];
}
```

**Acceptance Criteria**:
- [ ] Paginated queries use cursor-based pagination
- [ ] All filters applied correctly (AND logic)
- [ ] Entity names resolved where entity still exists
- [ ] Deleted entities handled gracefully (null name)
- [ ] Export generates valid CSV and JSON formats
- [ ] Export respects 10,000 record limit
- [ ] Change summaries are human-readable
- [ ] UUID fields in diff values resolved to names

---

#### AUD-006: Create `AuditLogController`

**Type**: Backend
**Effort**: 8h
**Dependencies**: AUD-005, AUD-004

**Files to create**:
- `app/Http/Controllers/Api/V1/AuditLogController.php`

**Implementation Details**:
```php
class AuditLogController extends Controller
{
    /**
     * List audit logs for the organization.
     * Permission: audit-logs:view
     *
     * @operationId getAuditLogs
     */
    public function index(
        Organization $organization,
        AuditLogIndexRequest $request,
        AuditLogService $auditLogService
    ): JsonResponse { }

    /**
     * Get a single audit record detail.
     * Permission: audit-logs:view
     *
     * @operationId getAuditLog
     */
    public function show(
        Organization $organization,
        int $audit,
        AuditLogService $auditLogService
    ): JsonResponse { }

    /**
     * Export audit logs as CSV or JSON.
     * Permission: audit-logs:export
     *
     * @operationId exportAuditLogs
     */
    public function export(
        Organization $organization,
        AuditLogExportRequest $request,
        AuditLogService $auditLogService
    ): StreamedResponse|JsonResponse { }
}
```

**Acceptance Criteria**:
- [ ] Controller extends `App\Http\Controllers\Api\V1\Controller`
- [ ] Uses `$this->checkPermission()` for authorization
- [ ] `index()` returns paginated JSON with cursor
- [ ] `show()` returns single record with resolved values
- [ ] `export()` returns streamed file download
- [ ] All methods inject service via parameter type-hints

---

#### AUD-007: Create Request Validation Classes

**Type**: Backend
**Effort**: 4h
**Dependencies**: AUD-006

**Files to create**:
- `app/Http/Requests/V1/AuditLog/AuditLogIndexRequest.php`
- `app/Http/Requests/V1/AuditLog/AuditLogExportRequest.php`

**Implementation Details**:
```php
// AuditLogIndexRequest validates:
//   - cursor: nullable|string
//   - per_page: nullable|integer|min:1|max:100
//   - auditable_type: nullable|array of valid type strings
//   - auditable_type.*: string|in:time-entry,project,task,...
//   - event: nullable|array of valid event strings
//   - event.*: string|in:created,updated,deleted,restored
//   - user_id: nullable|uuid|exists:users,id
//   - date_from: nullable|date_format:Y-m-d
//   - date_to: nullable|date_format:Y-m-d|after_or_equal:date_from
//   - auditable_id: nullable|uuid

// AuditLogExportRequest extends AuditLogIndexRequest and adds:
//   - format: required|string|in:csv,json
```

**Acceptance Criteria**:
- [ ] Both request classes extend `BaseFormRequest`
- [ ] All filter parameters validated with appropriate rules
- [ ] Invalid values return 422 with descriptive error messages
- [ ] Export request requires format parameter
- [ ] Date range validation ensures `date_to >= date_from`

---

#### AUD-008: Register API Routes

**Type**: Backend
**Effort**: 2h
**Dependencies**: AUD-006

**Files to modify**:
- `routes/api.php`

**Implementation Details**:
```php
// Add audit log routes following existing pattern:
Route::name('audit-logs.')->prefix('/organizations/{organization}')->group(static function (): void {
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('index');
    Route::get('/audit-logs/export', [AuditLogController::class, 'export'])->name('export');
    Route::get('/audit-logs/{audit}', [AuditLogController::class, 'show'])->name('show');
});
// Note: export route must be registered before {audit} route to avoid
// "export" being interpreted as an audit ID
```

**Acceptance Criteria**:
- [ ] Three routes registered with correct names
- [ ] Routes scoped under organization prefix
- [ ] Route order prevents "export" being matched as `{audit}` parameter
- [ ] No `check-organization-blocked` middleware (read-only endpoints)

---

#### AUD-009: Create API Resource Classes

**Type**: Backend
**Effort**: 4h
**Dependencies**: AUD-005

**Files to create**:
- `app/Http/Resources/V1/AuditLog/AuditLogResource.php`
- `app/Http/Resources/V1/AuditLog/AuditLogCollection.php`
- `app/Http/Resources/V1/AuditLog/AuditLogDetailResource.php`

**Implementation Details**:
```php
// AuditLogResource transforms Audit model for list view:
//   - id, event, auditable_type (kebab), auditable_type_label,
//     auditable_id, auditable_name, user info, changed_fields,
//     change_summary, created_at

// AuditLogDetailResource extends AuditLogResource and adds:
//   - resolved_old_values, resolved_new_values, ip_address,
//     user_agent, url

// AuditLogCollection wraps cursor paginator with meta info
```

**Acceptance Criteria**:
- [ ] List resource includes summary fields
- [ ] Detail resource includes resolved diff values
- [ ] Collection includes cursor pagination meta
- [ ] Morph class names converted to kebab-case labels

---

#### AUD-010: Update OpenAPI Spec + Regenerate Client

**Type**: Backend
**Effort**: 4h
**Dependencies**: AUD-008, AUD-009

**Files to modify**:
- `openapi.json`
- `resources/js/packages/api/src/openapi.json.client.ts` (regenerated)

**Implementation Details**:
- Add three endpoint definitions to OpenAPI spec
- Define request/response schemas matching API contracts
- Regenerate TypeScript client

**Acceptance Criteria**:
- [ ] OpenAPI spec includes all three audit log endpoints
- [ ] Request parameters and response schemas documented
- [ ] TypeScript client regenerated and compiles without errors
- [ ] Client types match frontend `AuditRecord` interface

---

#### AUD-011: Create `AuditLog.vue` Page

**Type**: Frontend
**Effort**: 4h
**Dependencies**: AUD-010

**Files to create**:
- `resources/js/Pages/AuditLog.vue`

**Implementation Details**:
```vue
<!-- Inertia page using AppLayout -->
<!-- Contains:
  - Page title "Audit Log"
  - AuditLogFilters component (top)
  - AuditLogList component (main content)
  - AuditLogDetail slide-over (conditionally rendered)
  - AuditLogExport dropdown (toolbar)
  - Warning banner when AUDITING_ENABLED is false
-->
```

**Acceptance Criteria**:
- [ ] Page renders within `AppLayout`
- [ ] Initializes Pinia store on mount
- [ ] Loads first page of audit records automatically
- [ ] Responsive layout (works on desktop and tablet)

---

#### AUD-012: Create `AuditLogList.vue` Component

**Type**: Frontend
**Effort**: 12h
**Dependencies**: AUD-011

**Files to create**:
- `resources/js/packages/ui/src/AuditLog/AuditLogList.vue`
- `resources/js/packages/ui/src/AuditLog/AuditLogRow.vue`
- `resources/js/packages/ui/src/AuditLog/AuditLogEventBadge.vue`

**Implementation Details**:
```vue
<!-- AuditLogList.vue:
  - Renders a table/list of AuditLogRow components
  - "Load More" button at bottom (cursor pagination)
  - Loading skeleton during initial load
  - Empty state when no records match
  - Result count display

  AuditLogRow.vue:
  - Clickable row showing: timestamp, event badge, entity type+name,
    user avatar+name, change summary
  - Hover state for interactivity
  - Emits click event to open detail panel

  AuditLogEventBadge.vue:
  - Color-coded badge: Created (green), Updated (blue),
    Deleted (red), Restored (yellow)
-->
```

**Acceptance Criteria**:
- [ ] Table renders 50 records per page
- [ ] "Load More" fetches next cursor page
- [ ] Loading skeleton shown during fetch
- [ ] Empty state with helpful message
- [ ] Rows are clickable to open detail
- [ ] Event badges are color-coded
- [ ] Entity names display with type labels
- [ ] User avatars rendered (or initials fallback)

---

#### AUD-013: Create `AuditLogFilters.vue` Component

**Type**: Frontend
**Effort**: 8h
**Dependencies**: AUD-011

**Files to create**:
- `resources/js/packages/ui/src/AuditLog/AuditLogFilters.vue`

**Implementation Details**:
```vue
<!-- Filter bar with:
  - Entity type multi-select dropdown
  - Event type multi-select dropdown
  - User searchable select (loads org members)
  - Date range picker (from/to)
  - Entity ID text input (advanced, collapsible)
  - Active filter chips with remove buttons
  - "Clear All" button
  - Syncs filters to URL query parameters
  - Debounced filter application (300ms)
-->
```

**Acceptance Criteria**:
- [ ] All five filter types functional
- [ ] Multi-select dropdowns work correctly
- [ ] User dropdown is searchable with member list from API
- [ ] Date pickers validate range (to >= from)
- [ ] Active filters shown as removable chips
- [ ] Filters synced to URL query parameters
- [ ] "Clear All" resets all filters
- [ ] Debounced API calls (not on every keystroke)

---

#### AUD-014: Create `AuditLogDetail.vue` Slide-over

**Type**: Frontend
**Effort**: 10h
**Dependencies**: AUD-012

**Files to create**:
- `resources/js/packages/ui/src/AuditLog/AuditLogDetail.vue`
- `resources/js/packages/ui/src/AuditLog/AuditLogDiffTable.vue`

**Implementation Details**:
```vue
<!-- AuditLogDetail.vue:
  - Slide-over panel from right side of screen
  - Header: entity type + name, event badge, timestamp
  - Section: Acting user (name, email, avatar)
  - Section: Metadata (IP, user agent, URL)
  - Section: Changes (AuditLogDiffTable)
  - Close button (X) and Escape key to dismiss
  - Click-outside to close
  - Loading state while fetching detail

  AuditLogDiffTable.vue:
  - Two-column table: Old Value | New Value
  - Rows for each changed field
  - Changed cells highlighted (yellow/amber background)
  - Toggle to show all fields (including unchanged)
  - Pretty-print JSON values
  - Resolved UUIDs shown as "Name (uuid)" format
  - For 'created' events: only "New Value" column
  - For 'deleted' events: only "Old Value" column
-->
```

**Acceptance Criteria**:
- [ ] Slide-over opens from right with smooth animation
- [ ] All audit metadata displayed
- [ ] Diff table shows old vs new values
- [ ] Changed fields highlighted visually
- [ ] UUID values resolved to human-readable names
- [ ] Created events show new values only
- [ ] Deleted events show old values only
- [ ] Toggle for showing all fields vs changed only
- [ ] Escape key closes panel
- [ ] Click-outside closes panel
- [ ] Loading state during API call

---

#### AUD-015: Create `AuditLogExport.vue` Component

**Type**: Frontend
**Effort**: 4h
**Dependencies**: AUD-012

**Files to create**:
- `resources/js/packages/ui/src/AuditLog/AuditLogExport.vue`

**Implementation Details**:
```vue
<!-- Export dropdown button:
  - "Export" button in toolbar area
  - Dropdown with two options: "Export as CSV", "Export as JSON"
  - Clicking triggers download via API
  - Loading spinner during export generation
  - Warning toast if export exceeds 10,000 records
  - Only rendered when user has audit-logs:export permission
-->
```

**Acceptance Criteria**:
- [ ] Dropdown with CSV and JSON options
- [ ] Download triggers via API with current filters
- [ ] Loading indicator during export
- [ ] Warning for oversized exports
- [ ] Hidden from users without export permission

---

#### AUD-016: Create `useAuditLogStore.ts` Pinia Store

**Type**: Frontend
**Effort**: 8h
**Dependencies**: AUD-010

**Files to create**:
- `resources/js/utils/useAuditLog.ts`
- `resources/js/types/auditLog.d.ts`

**Implementation Details**:
```typescript
// useAuditLogStore (Pinia defineStore)
//
// State:
//   auditRecords: AuditRecord[]
//   selectedRecord: AuditRecord | null
//   selectedRecordDetail: AuditLogDetailResponse | null
//   filters: AuditLogFilters
//   pagination: { cursor: string | null, hasMore: boolean, perPage: number }
//   isLoading: boolean
//   isLoadingDetail: boolean
//   isExporting: boolean
//   error: string | null
//
// Actions:
//   loadAuditLogs()        - Fetch first page with current filters
//   loadMore()             - Fetch next page using cursor
//   setFilters(filters)    - Update filters and reload
//   clearFilters()         - Reset all filters and reload
//   selectRecord(id)       - Fetch detail and set selectedRecord
//   deselectRecord()       - Clear selected record
//   exportLogs(format)     - Trigger export download
//   syncFiltersFromUrl()   - Parse URL query params into filters
//   syncFiltersToUrl()     - Update URL query params from filters
//
// Getters:
//   activeFilterCount      - Number of active filters
//   hasActiveFilters       - Boolean
//   totalDisplayed         - Number of records currently loaded
```

**Acceptance Criteria**:
- [ ] Store manages all audit log state
- [ ] Cursor-based pagination implemented
- [ ] Filter changes trigger reload from first page
- [ ] URL synchronization works bidirectionally
- [ ] Error handling for API failures
- [ ] Loading states managed correctly
- [ ] Export downloads file to browser

---

#### AUD-017: Add Web Route + Sidebar Navigation

**Type**: Frontend
**Effort**: 2h
**Dependencies**: AUD-011

**Files to modify**:
- `routes/web.php` (add `Inertia::render('AuditLog')` route)
- `resources/js/Layouts/AppLayout.vue` (add sidebar item)

**Implementation Details**:
```php
// routes/web.php: Add within auth:web middleware group
// Route::get('/audit-log', fn () => Inertia::render('AuditLog'))->name('audit-log');

// AppLayout.vue: Add NavigationSidebarItem
// - Label: "Audit Log"
// - Icon: ClipboardDocumentListIcon from @heroicons/vue/20/solid
// - Route: route('audit-log')
// - Position: after "Members" section, before Import/Export
// - Visibility: only when user has audit-logs:view permission
```

**Acceptance Criteria**:
- [ ] Web route renders AuditLog page via Inertia
- [ ] Sidebar item appears for users with `audit-logs:view`
- [ ] Sidebar item hidden for employees
- [ ] Correct icon displayed
- [ ] Active state when on audit log page

---

#### AUD-018: Add "View History" Links to Entity Pages

**Type**: Frontend
**Effort**: 4h
**Dependencies**: AUD-012, AUD-013

**Files to modify**:
- Relevant entity detail/list components (Project, Task, Client, Member pages)

**Implementation Details**:
```vue
<!-- Add a "View History" button/link that navigates to:
  /audit-log?auditable_type={type}&auditable_id={id}

  Only visible to users with audit-logs:view permission.
  Uses router-link with query parameters.
-->
```

**Acceptance Criteria**:
- [ ] "View History" link on Project detail page
- [ ] "View History" link on Task detail page
- [ ] "View History" link on Client detail page
- [ ] "View History" link on Member detail page
- [ ] Links navigate to pre-filtered audit log
- [ ] Links hidden from users without permission

---

#### AUD-019: Backend Endpoint Tests (PHPUnit)

**Type**: Testing
**Effort**: 10h
**Dependencies**: AUD-006, AUD-008

**Files to create**:
- `tests/Unit/Endpoint/Api/V1/AuditLogEndpointTest.php`

**Test Cases**:
```php
// Index endpoint tests:
// - test_index_returns_paginated_audit_records
// - test_index_scoped_to_organization
// - test_index_filter_by_auditable_type
// - test_index_filter_by_event
// - test_index_filter_by_user_id
// - test_index_filter_by_date_range
// - test_index_filter_by_auditable_id
// - test_index_combined_filters
// - test_index_cursor_pagination
// - test_index_requires_audit_logs_view_permission
// - test_index_denied_for_employee_role
// - test_index_returns_empty_for_other_organization

// Show endpoint tests:
// - test_show_returns_audit_detail
// - test_show_resolves_entity_names
// - test_show_handles_deleted_entity
// - test_show_returns_404_for_other_org_audit
// - test_show_requires_audit_logs_view_permission

// Export endpoint tests:
// - test_export_csv_format
// - test_export_json_format
// - test_export_respects_filters
// - test_export_requires_audit_logs_export_permission
// - test_export_denied_for_manager_role
// - test_export_limit_10000_records
```

**Acceptance Criteria**:
- [ ] All three endpoints fully tested
- [ ] Permission checks tested for all roles
- [ ] Organization scoping tested
- [ ] All filter combinations tested
- [ ] Edge cases covered (deleted entities, empty results, large datasets)

---

#### AUD-020: Service Layer Unit Tests (PHPUnit)

**Type**: Testing
**Effort**: 8h
**Dependencies**: AUD-005

**Files to create**:
- `tests/Unit/Service/AuditLogServiceTest.php`

**Test Cases**:
```php
// - test_get_audit_logs_returns_paginated_results
// - test_get_audit_logs_filters_by_organization
// - test_get_audit_logs_applies_type_filter
// - test_get_audit_logs_applies_event_filter
// - test_get_audit_logs_applies_date_range_filter
// - test_get_audit_logs_applies_user_filter
// - test_get_audit_detail_resolves_names
// - test_get_audit_detail_handles_deleted_entity
// - test_resolve_entity_name_for_each_model_type
// - test_generate_change_summary_for_created_event
// - test_generate_change_summary_for_updated_event
// - test_generate_change_summary_for_deleted_event
// - test_export_csv_format_correct
// - test_export_json_format_correct
// - test_export_respects_record_limit
```

**Acceptance Criteria**:
- [ ] All service methods tested
- [ ] Entity name resolution tested for all model types
- [ ] Change summary generation tested for all event types
- [ ] Export format validation for CSV and JSON
- [ ] Edge cases covered

---

#### AUD-021: Backfill Command Tests (PHPUnit)

**Type**: Testing
**Effort**: 4h
**Dependencies**: AUD-002

**Files to create**:
- `tests/Unit/Console/BackfillAuditOrganizationIdsTest.php`

**Test Cases**:
```php
// - test_backfills_time_entry_audits
// - test_backfills_project_audits
// - test_backfills_organization_audits_with_self_reference
// - test_backfills_user_audits_via_membership
// - test_skips_already_backfilled_records
// - test_dry_run_does_not_modify_data
// - test_handles_deleted_auditable_gracefully
// - test_processes_large_dataset_in_chunks
```

**Acceptance Criteria**:
- [ ] All model types tested for backfill
- [ ] Idempotency verified
- [ ] Dry run verified
- [ ] Edge cases (deleted models, orphan records) handled

---

#### AUD-022: Frontend Component Tests (Vitest)

**Type**: Testing
**Effort**: 8h
**Dependencies**: AUD-012, AUD-013, AUD-014

**Files to create**:
- `resources/js/packages/ui/src/AuditLog/__tests__/AuditLogList.test.ts`
- `resources/js/packages/ui/src/AuditLog/__tests__/AuditLogFilters.test.ts`
- `resources/js/packages/ui/src/AuditLog/__tests__/AuditLogDetail.test.ts`
- `resources/js/packages/ui/src/AuditLog/__tests__/AuditLogEventBadge.test.ts`

**Test Cases**:
```typescript
// AuditLogList:
// - renders audit records in table format
// - shows loading skeleton during fetch
// - shows empty state when no records
// - "Load More" button triggers pagination
// - clicking row emits select event

// AuditLogFilters:
// - renders all filter controls
// - entity type multi-select works
// - event type multi-select works
// - date range picker validates input
// - active filters shown as chips
// - "Clear All" resets filters

// AuditLogDetail:
// - renders slide-over with audit info
// - shows diff table with old/new values
// - highlights changed fields
// - handles created events (no old values)
// - handles deleted events (no new values)
// - Escape key closes panel

// AuditLogEventBadge:
// - renders correct color for each event type
// - displays correct label text
```

**Acceptance Criteria**:
- [ ] All core components have tests
- [ ] Rendering, interaction, and edge cases covered
- [ ] Tests pass with `npm run test`

---

#### AUD-023: E2E Playwright Tests

**Type**: Testing
**Effort**: 8h
**Dependencies**: AUD-017, AUD-012

**Files to create**:
- `e2e/audit-log.spec.ts`

**Test Scenarios**:
```typescript
// - Navigate to audit log from sidebar
// - Verify audit records are displayed
// - Apply entity type filter and verify results update
// - Apply date range filter and verify results update
// - Click an audit record and verify detail panel opens
// - Verify diff table shows old/new values
// - Close detail panel with Escape key
// - Click "Load More" and verify additional records load
// - Verify audit log not accessible to employee role
// - Export audit log as CSV (verify download triggers)
```

**Acceptance Criteria**:
- [ ] All critical user paths covered
- [ ] Tests pass in CI environment
- [ ] Permission restrictions verified
- [ ] Filter and pagination workflows tested

---

#### AUD-024: JSDoc Comments on Store and Components

**Type**: Docs
**Effort**: 2h
**Dependencies**: AUD-016

**Files to modify**:
- `resources/js/utils/useAuditLog.ts`
- Key Vue components

**Implementation Details**:
- Add JSDoc comments to all exported functions, store actions, and getters
- Document parameter types and return values
- Add usage examples in store file header

**Acceptance Criteria**:
- [ ] All store actions and getters documented
- [ ] TypeScript types have JSDoc descriptions
- [ ] Usage examples included

---

### Dependency Graph

```
AUD-001 (Migration)
  |
  +---> AUD-002 (Backfill Command)
  |       |
  |       +---> AUD-021 (Backfill Tests)
  |
  +---> AUD-003 (Extend CustomAuditable)
  |
  +---> AUD-005 (AuditLogService)
          |
          +---> AUD-009 (API Resources)
          |       |
          |       +---> AUD-010 (OpenAPI + TS Client)
          |               |
          |               +---> AUD-011 (AuditLog.vue Page)
          |               |       |
          |               |       +---> AUD-012 (AuditLogList)
          |               |       |       |
          |               |       |       +---> AUD-014 (AuditLogDetail)
          |               |       |       |
          |               |       |       +---> AUD-015 (AuditLogExport)
          |               |       |       |
          |               |       |       +---> AUD-022 (Component Tests)
          |               |       |       |
          |               |       |       +---> AUD-023 (E2E Tests)
          |               |       |
          |               |       +---> AUD-013 (AuditLogFilters)
          |               |       |       |
          |               |       |       +---> AUD-018 (Entity History Links)
          |               |       |
          |               |       +---> AUD-017 (Web Route + Sidebar)
          |               |
          |               +---> AUD-016 (Pinia Store + Types)
          |                       |
          |                       +---> AUD-024 (JSDoc)
          |
          +---> AUD-006 (Controller)
          |       |
          |       +---> AUD-007 (Validation Classes)
          |       |
          |       +---> AUD-008 (API Routes)
          |       |       |
          |       |       +---> AUD-019 (Endpoint Tests)
          |       |
          |       +---> AUD-010 (OpenAPI + TS Client)
          |
          +---> AUD-020 (Service Tests)

AUD-004 (Permissions) -----> AUD-006 (Controller)
```

### Critical Path

```
AUD-001 -> AUD-005 -> AUD-006 -> AUD-008 -> AUD-010 -> AUD-011 -> AUD-012 -> AUD-014
(4h)       (16h)      (8h)       (2h)       (4h)       (4h)       (12h)      (10h)
= 60 hours on critical path
```

### Parallelization Opportunities

- **AUD-001** and **AUD-004** can run in parallel (no dependency)
- **AUD-002** (backfill) and **AUD-003** (trait extension) can run in parallel after AUD-001
- **AUD-013** (filters) and **AUD-012** (list) can run in parallel after AUD-011
- **AUD-014** (detail) and **AUD-015** (export) can run in parallel after AUD-012
- **AUD-019** (endpoint tests) and **AUD-020** (service tests) can run in parallel
- **AUD-022** (component tests) and **AUD-023** (E2E tests) can run in parallel

---

## 7. Dependencies & Integration Points

### 7.1 Internal Dependencies

| Dependency | Description | Impact |
|------------|-------------|--------|
| `Audit` Model | Existing model for audit data storage | Read-only (+ schema migration for `organization_id`) |
| `CustomAuditable` Trait | Modified to populate `organization_id` | Modified (backward-compatible) |
| `TimeEntry` Model | Referenced for entity name resolution | Read-only |
| `Project` Model | Referenced for entity name resolution | Read-only |
| `Task` Model | Referenced for entity name resolution | Read-only |
| `Client` Model | Referenced for entity name resolution | Read-only |
| `Tag` Model | Referenced for entity name resolution | Read-only |
| `Member` Model | Referenced for entity name resolution and user filtering | Read-only |
| `Organization` Model | Route model binding, scoping | Read-only |
| `User` Model | Referenced for acting user info in audit records | Read-only |
| `PermissionStore` | Permission checking in controller | Read-only |
| `CorePermissions` | Modified to include new audit log permissions | Modified |
| `AuditFactory` | Existing factory used in tests | Read-only |

### 7.2 External Dependencies

| Dependency | Version | Purpose |
|------------|---------|---------|
| owen-it/laravel-auditing | ^13.x | Core audit infrastructure (already in project) |
| dayjs | ^1.11.x | Date formatting (already in project) |
| @heroicons/vue | ^2.x | Icons for UI (already in project) |
| pinia | ^2.x | State management (already in project) |
| TailwindCSS | ^3.x | Styling (already in project) |

No new dependencies required.

### 7.3 Downstream Features

This feature is a **standalone utility** with no hard dependencies from other features. However:

- **Feature 01 (Timesheet Approvals)**: The audit trail becomes significantly more valuable when combined with approval workflows -- managers can review the full change history of time entries that were submitted for approval.
- **Feature 09 (Advanced Reporting)**: Future reporting features could incorporate audit data for compliance reports.
- **Feature 10 (Teams & Groups)**: When team scoping is enabled (SF-07), the audit log could be filtered by team scope in a future enhancement.

### 7.4 Prerequisite: Auditing Must Be Enabled

The `config/audit.php` file sets `'enabled' => env('AUDITING_ENABLED', false)`. This feature requires `AUDITING_ENABLED=true` to produce any data. The UI will show a warning banner when auditing is disabled, but this is ultimately an environment configuration concern, not something the feature itself controls.

---

## 8. Risk Assessment & Mitigation

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| Large `audits` table causes slow queries | High | High | Composite indexes on `(organization_id, created_at DESC)` and `(organization_id, auditable_type, created_at DESC)`. Cursor-based pagination instead of offset pagination. Consider table partitioning for very large deployments. |
| Organization scoping for User model audits is ambiguous | Medium | Medium | Users can belong to multiple organizations. For the backfill command, resolve via membership. For new records, resolve from request context (the organization in the URL path). Document edge cases. |
| `AUDITING_ENABLED=false` in production means no data | Medium | High | Display clear warning banner on audit log page when auditing is disabled. Document in deployment guide that auditing should be enabled for governance features. |
| Entity name resolution for deleted entities | Medium | Low | Return `null` for name and display "[Deleted] {type} ({id})" in the UI. No joins on potentially missing rows. |
| Backfill command performance on large existing datasets | Medium | Medium | Process in chunks of 1,000. Use raw SQL updates with JOIN for efficiency. Progress bar for monitoring. Designed to be run during maintenance window. |
| Export of very large datasets (100K+ records) | Low | Medium | Hard limit of 10,000 records per export. If exceeded, prompt user to narrow filters. Future: queue-based export with download link via notification. |
| Morph class names may change between Laravel versions | Low | High | Use a constant map (`AUDITABLE_TYPE_MAP`) in the service to decouple from internal morph class names. If morph map is defined, use it. |

---

## 9. Testing & Validation Requirements

### 9.1 Test Strategy

| Type | Coverage Target | Tools |
|------|-----------------|-------|
| Backend Unit Tests | All service methods | PHPUnit |
| API Endpoint Tests | All 3 endpoints | PHPUnit (ApiEndpointTestAbstract) |
| Command Tests | Backfill command | PHPUnit |
| Frontend Component Tests | Core components | Vitest + @vue/test-utils |
| E2E Tests | Critical user paths | Playwright |

### 9.2 Key Test Scenarios

**Backend**:
- Audit logs scoped correctly to organization (no cross-org leakage)
- All filter types apply correctly (type, event, user, date range, entity ID)
- Combined filters use AND logic
- Cursor pagination returns correct pages in correct order
- Entity names resolved for existing entities
- Deleted entities handled gracefully (null name)
- Export generates valid CSV with correct columns
- Export generates valid JSON with correct structure
- Export respects 10,000 record limit
- Permission `audit-logs:view` enforced on index and show
- Permission `audit-logs:export` enforced on export
- Employee role denied access to all endpoints
- Owner, Admin, Manager roles can view
- Only Owner and Admin can export
- Backfill command populates `organization_id` correctly for all model types
- Backfill is idempotent (running twice does not duplicate or corrupt)
- Backfill dry-run mode does not write data

**Frontend**:
- Audit log list renders correct number of records
- Pagination ("Load More") works
- Filters render and apply correctly
- Active filter chips display and are removable
- Detail slide-over opens and closes (click, Escape, click-outside)
- Diff table renders old/new values correctly
- Changed fields are highlighted
- Event badges display correct colors
- Export dropdown triggers download
- Empty states display when no data
- Loading states display during fetches
- Error states display on API failure

**E2E**:
- Navigate to audit log from sidebar
- View audit records
- Apply and remove filters
- Open and close audit detail panel
- Verify diff view shows changes
- Trigger export download
- Verify page not accessible to employee role

---

## 10. Monitoring & Observability

### 10.1 Metrics to Track

| Metric | Type | Alert Threshold |
|--------|------|-----------------|
| Audit log page load time | Performance | > 3s |
| Audit log API P95 latency | Performance | > 2s |
| Export generation time | Performance | > 60s |
| Audit log API error rate | Error | > 1% |
| Audit log page daily active users | Business | -- |
| Export requests per day | Business | -- |
| Audits table row count | Infrastructure | -- (monitoring only) |

### 10.2 Logging

The audit log feature itself is read-only and does not generate additional audit records. Logging is limited to:
- Standard Laravel request logging for API endpoints
- Error logging for failed queries or export generation
- Backfill command progress logging via artisan output

### 10.3 Database Monitoring

The `audits` table will grow continuously. Recommended monitoring:
- Table row count (track growth rate)
- Query execution time for the most common filter combinations
- Index utilization (ensure composite indexes are being used)
- Disk space consumed by the `audits` table

---

## 11. Success Metrics & Definition of Done

### 11.1 Success Metrics

| Metric | Target | Measurement |
|--------|--------|-------------|
| Feature adoption | 50% of admins use within 30 days | Page visit analytics |
| Query performance | P95 < 1s for filtered queries | API latency tracking |
| Export usage | 5+ exports per week per active org | Export endpoint tracking |
| Time to resolve billing disputes | 50% reduction | User research / support tickets |

### 11.2 Definition of Done

- [ ] All 6 core requirements (REQ-001 through REQ-006) implemented
- [ ] All 3 API endpoints working with proper validation and permissions
- [ ] Migration adds `organization_id` to `audits` table with indexes
- [ ] Backfill command works for all model types
- [ ] `CustomAuditable` trait extended to populate `organization_id`
- [ ] Permissions registered for all roles
- [ ] Backend endpoint tests passing
- [ ] Service layer unit tests passing
- [ ] Backfill command tests passing
- [ ] Frontend component tests passing
- [ ] E2E tests passing for critical paths
- [ ] `composer fix && composer analyse` passes
- [ ] `npm run lint:fix && npm run format` passes
- [ ] OpenAPI spec updated and TS client regenerated
- [ ] Sidebar navigation item added (permission-gated)
- [ ] Export functionality working for CSV and JSON
- [ ] Loading, error, and empty states handled
- [ ] Diff view resolves UUIDs to human-readable names

---

## 12. Technical Debt & Future Considerations

### 12.1 Known Simplifications

1. **No real-time updates**: The audit log requires manual refresh or pagination to see new records. Future: WebSocket/SSE for live tailing.

2. **No retention policy**: Audit records accumulate indefinitely. Future: configurable retention period with automatic purging (e.g., "keep audit logs for 2 years").

3. **No full-text search**: Searching within `old_values` / `new_values` JSON is not supported. Users can only filter by entity type, event, user, date, and entity ID. Future: Elasticsearch integration for full-text audit search.

4. **User model scoping is approximate**: When a `User` model is audited, the `organization_id` is resolved from request context or membership. If a user belongs to multiple organizations, the assignment may be imprecise. Future: track organization context explicitly in the audit metadata.

5. **No diff for JSON arrays**: If a model field contains a JSON array (e.g., `tags` on `TimeEntry`), the diff shows the full old/new array rather than highlighting individual added/removed items. Future: JSON array diff algorithm.

6. **Export is synchronous**: Exports up to 10,000 records are generated synchronously. For very large exports, this may time out. Future: queue-based export with notification when ready.

### 12.2 Future Enhancements

| Enhancement | Priority | Description |
|-------------|----------|-------------|
| Retention policies | P2 | Configurable auto-purge of old audit records |
| Full-text search | P2 | Search within changed values using Elasticsearch or similar |
| Real-time log tailing | P3 | WebSocket/SSE for live audit stream |
| Audit log webhooks | P3 | Notify external systems of changes via webhooks |
| Advanced diff view | P3 | JSON array diff, rich text diff for description fields |
| Compliance reports | P2 | Pre-built report templates (e.g., "All time entry changes this month") |
| Audit log for API tokens | P3 | Track which API token was used for each change |
| Queue-based export | P2 | Async export for large datasets with download notification |
| Table partitioning | P2 | Partition `audits` table by month for query performance at scale |

---

## 13. Appendices

### 13.1 File Structure Summary

```
solidtime/
+-- app/
|   +-- Console/Commands/
|   |   +-- BackfillAuditOrganizationIds.php               # NEW
|   +-- Http/
|   |   +-- Controllers/Api/V1/
|   |   |   +-- AuditLogController.php                     # NEW
|   |   +-- Requests/V1/AuditLog/
|   |   |   +-- AuditLogIndexRequest.php                   # NEW
|   |   |   +-- AuditLogExportRequest.php                  # NEW
|   |   +-- Resources/V1/AuditLog/
|   |       +-- AuditLogResource.php                       # NEW
|   |       +-- AuditLogCollection.php                     # NEW
|   |       +-- AuditLogDetailResource.php                 # NEW
|   +-- Models/
|   |   +-- Concerns/
|   |       +-- CustomAuditable.php                        # MODIFIED
|   +-- Permissions/
|   |   +-- AuditLogPermissions.php                        # NEW
|   +-- Service/
|       +-- AuditLogService.php                            # NEW
+-- database/
|   +-- migrations/
|       +-- 2026_03_13_000001_add_organization_id_to_audits_table.php  # NEW
+-- routes/
|   +-- api.php                                            # MODIFIED (add audit-log routes)
|   +-- web.php                                            # MODIFIED (add Inertia route)
+-- resources/js/
|   +-- Pages/
|   |   +-- AuditLog.vue                                   # NEW
|   +-- packages/ui/src/AuditLog/
|   |   +-- AuditLogList.vue                               # NEW
|   |   +-- AuditLogRow.vue                                # NEW
|   |   +-- AuditLogEventBadge.vue                         # NEW
|   |   +-- AuditLogFilters.vue                            # NEW
|   |   +-- AuditLogDetail.vue                             # NEW
|   |   +-- AuditLogDiffTable.vue                          # NEW
|   |   +-- AuditLogExport.vue                             # NEW
|   |   +-- __tests__/
|   |       +-- AuditLogList.test.ts                       # NEW
|   |       +-- AuditLogFilters.test.ts                    # NEW
|   |       +-- AuditLogDetail.test.ts                     # NEW
|   |       +-- AuditLogEventBadge.test.ts                 # NEW
|   +-- utils/
|   |   +-- useAuditLog.ts                                 # NEW
|   +-- types/
|   |   +-- auditLog.d.ts                                  # NEW
|   +-- Layouts/
|       +-- AppLayout.vue                                  # MODIFIED (add sidebar nav)
+-- tests/
|   +-- Unit/
|   |   +-- Endpoint/Api/V1/
|   |   |   +-- AuditLogEndpointTest.php                   # NEW
|   |   +-- Service/
|   |   |   +-- AuditLogServiceTest.php                    # NEW
|   |   +-- Console/
|   |       +-- BackfillAuditOrganizationIdsTest.php       # NEW
+-- e2e/
    +-- audit-log.spec.ts                                  # NEW
```

### 13.2 API Endpoint Summary

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/organizations/{org}/audit-logs` | List paginated audit records with filters |
| GET | `/api/v1/organizations/{org}/audit-logs/export` | Export filtered audit records as CSV/JSON |
| GET | `/api/v1/organizations/{org}/audit-logs/{audit}` | Get single audit record with resolved diff |

### 13.3 Permission Matrix

| Permission | Owner | Admin | Manager | Employee | Placeholder |
|------------|:-----:|:-----:|:-------:|:--------:|:-----------:|
| `audit-logs:view` | Yes | Yes | Yes | No | No |
| `audit-logs:export` | Yes | Yes | No | No | No |

### 13.4 Audited Models Reference

All 10 models using `CustomAuditable` that will appear in the audit log:

| Model | `auditable_type` Filter Value | Has `organization_id` | Name Resolution |
|-------|------------------------------|:---------------------:|-----------------|
| TimeEntry | `time-entry` | Yes (direct) | Description or "Time Entry {start}" |
| Project | `project` | Yes (direct) | `name` |
| Task | `task` | Yes (via project) | `name` |
| Client | `client` | Yes (direct) | `name` |
| Tag | `tag` | Yes (direct) | `name` |
| Member | `member` | Yes (direct) | User's `name` via relationship |
| Organization | `organization` | Self (auditable_id) | `name` |
| ProjectMember | `project-member` | Yes (via project) | "{user name} on {project name}" |
| OrganizationInvitation | `organization-invitation` | Yes (direct) | `email` |
| User | `user` | Via membership | `name` |

### 13.5 Existing `audits` Table Schema (Before Migration)

```sql
CREATE TABLE audits (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_type   VARCHAR(255) NULL,
    user_id     CHAR(36) NULL,
    event       VARCHAR(255) NOT NULL,
    auditable_type VARCHAR(255) NOT NULL,
    auditable_id   CHAR(36) NOT NULL,
    old_values  JSON NULL,
    new_values  JSON NULL,
    url         TEXT NULL,
    ip_address  VARCHAR(45) NULL,
    user_agent  VARCHAR(1023) NULL,
    tags        VARCHAR(255) NULL,
    created_at  TIMESTAMP NULL,
    updated_at  TIMESTAMP NULL,
    INDEX idx_user (user_id, user_type),
    INDEX idx_auditable (auditable_type, auditable_id)
);
```

### 13.6 Post-Migration `audits` Table Schema

```sql
-- Added by AUD-001 migration:
ALTER TABLE audits ADD COLUMN organization_id CHAR(36) NULL AFTER tags;
CREATE INDEX idx_audits_organization_id ON audits(organization_id);
CREATE INDEX idx_audits_org_created ON audits(organization_id, created_at DESC);
CREATE INDEX idx_audits_org_type_created ON audits(organization_id, auditable_type, created_at DESC);
```

### 13.7 Competitive Feature Matrix (Section 4.4 context)

| Platform | Audit Log UI | Filtering | Diff View | Export | Entity-Scoped History | Permission-Gated |
|----------|:----------:|:---------:|:---------:|:------:|:--------------------:|:----------------:|
| Harvest | Yes | Basic | No | No | No | Yes |
| TimeCamp | Yes (Enterprise) | Yes | No | Yes | No | Yes |
| Clockify | Yes | Yes | Yes | No | No | Yes |
| Hubstaff | Yes | Basic | No | No | No | Yes |
| **Solidtime (this PRD)** | **Yes** | **Yes** | **Yes** | **Yes** | **Yes** | **Yes** |

### 13.8 Sprint Plan Overview

| Sprint | Focus | Tasks | Story Points |
|--------|-------|-------|-------------|
| Sprint 1 (Week 1-2) | Backend: Migration, Backfill, Trait, Permissions, Service, Controller, Routes, Resources, OpenAPI | AUD-001 through AUD-010 | ~29 SP |
| Sprint 2 (Week 3-4) | Frontend: Page, List, Filters, Detail, Export, Store, Navigation | AUD-011 through AUD-018 | ~26 SP |
| Sprint 3 (Week 5-6) | Testing: Endpoint tests, Service tests, Backfill tests, Component tests, E2E, JSDoc | AUD-019 through AUD-024 | ~15 SP |

**Total**: ~70 SP / ~140h across 3 sprints (6 weeks)

### 13.9 Change Log

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-02-09 | Tech Planning Agent | Initial draft |
