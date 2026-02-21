# PRD: PM Tool Integrations (Jira, Asana, Trello)

Generated: 2026-02-09
Version: 1.0
Feature Branch: `feature/pm-integrations` (from `main`)

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

Solidtime currently operates as a standalone time tracking application with no connections to the project management tools that teams use daily. Users must manually cross-reference Jira issues, Asana tasks, or Trello cards when logging time, and there is no automatic synchronization of project structures or task identifiers between systems. This creates several problems:

- Users must context-switch between their PM tool and Solidtime to look up task names, issue IDs, and project structures
- Project and task hierarchies in Solidtime drift out of sync with the PM tool, leading to stale data
- There is no way to view tracked time from within the PM tool interface, reducing visibility for managers
- Time entries lack external references (e.g., Jira issue keys), making cross-system reporting impossible
- Organizations evaluating Solidtime against Everhour, Toggl, or Clockify find it lacks a critical adoption driver: embedded PM integrations

Without PM tool integrations, Solidtime cannot compete with platforms that embed time tracking directly into the workflow where work is managed.

### 1.2 Competitive Analysis

From **features.txt** Section 11.1 -- "PM tool integrations (Asana/Jira/Trello/etc.)":

> **What**: Track time on tasks where work happens; sync projects/tasks.
> **Why important**: Adoption -- users don't want tab switching.
> **User flow**:
> 1. Admin connects integration.
> 2. System syncs projects/tasks.
> 3. Member starts timer directly on the task card/issue.
> 4. Time entry posts back or maps via identifiers.
>
> (Everhour lists many embedded integrations; Toggl and Clockify cite 100+ / 90+ integrations.)

Platforms offering deep PM integrations:

| Platform | Jira | Asana | Trello | Others | Approach |
|----------|:----:|:-----:|:------:|--------|----------|
| Everhour | Yes | Yes | Yes | ClickUp, Basecamp, Monday, GitHub, Linear, Wrike, Notion | Embedded (browser extension + native API) |
| Toggl Track | Yes | Yes | Yes | 100+ integrations | Browser extension + API |
| Clockify | Yes | Yes | Yes | 90+ integrations | Browser extension + API |
| Harvest | Yes | Yes | Yes | Integrations hub | API-based sync |
| Hubstaff | Yes | Yes | Yes | 30+ integrations | API-based sync |
| TimeCamp | Yes | Yes | Yes | Direct integrations | API + browser plugin |
| **Solidtime** | **No** | **No** | **No** | None | REST API only |

### 1.3 Reference Implementation

Based on **Everhour's integration model** (market leader in embedded PM integrations):

**Connection flow:**
- Admin navigates to Organization Settings > Integrations
- Clicks "Connect" on the desired PM tool
- Completes OAuth authorization flow
- Selects which external projects to sync
- System imports project/task structures

**Sync model:**
- Initial full sync of selected projects and their tasks
- Periodic background sync to pick up new tasks, status changes, and project updates
- Webhook-based real-time sync where the PM tool supports it (Jira, Asana)
- Two-way time posting: time tracked in Solidtime can optionally be posted back to the PM tool's native time tracking fields

**Task mapping:**
- External tasks map to Solidtime tasks via an `external_task_mappings` table
- Time entries reference external task IDs for cross-system reporting
- Users see external issue keys (e.g., "PROJ-123") alongside task names in Solidtime UI

### 1.4 Current System State

**Existing infrastructure on `main`:**
- `TimeEntry` model with `start`, `end`, `project_id`, `task_id`, `member_id`, `user_id`, `organization_id`, `billable`, `description`, `tags`
- `Project` model with `name`, `color`, `organization_id`, `client_id`, `is_billable`, `is_public`
- `Task` model with `name`, `project_id`, `organization_id`, `done_at`, `estimated_time`, `spent_time`
- `Organization` model with membership and role management (Owner, Admin, Manager, Employee)
- Permission system: `entity:action:scope` pattern (e.g., `time-entries:create:own`, `projects:create`)
- Laravel Passport for API authentication, Jetstream for web sessions
- No OAuth client infrastructure for outbound connections (Solidtime is an OAuth provider, not a consumer)
- No background job queue for periodic sync (Laravel queue infrastructure exists but no recurring jobs)
- No webhook handling infrastructure
- No external identifier storage on any existing model

**Key architectural decisions from this PRD:**
- New database tables required: `integration_connections`, `integration_projects`, `external_task_mappings`, `integration_sync_logs`
- New permission set: `integrations:view`, `integrations:manage`, `integrations:sync`
- New service layer: `IntegrationService`, provider-specific adapters (`JiraAdapter`, `AsanaAdapter`, `TrelloAdapter`)
- OAuth 2.0 consumer implementation for Jira and Asana; token-based auth for Trello
- Laravel scheduled jobs for periodic sync
- Webhook endpoints for real-time sync from Jira and Asana

---

## 2. Technical Interpretation

### Business to Technical Translation

| Business Requirement | Technical Implementation |
|---------------------|-------------------------|
| Admin connects a PM tool | OAuth 2.0 authorization code flow (Jira, Asana) or API key entry (Trello); store encrypted tokens in `integration_connections` table |
| System syncs projects/tasks | Background job fetches projects/tasks from external API; maps to Solidtime `Project`/`Task` models via `integration_projects` and `external_task_mappings` tables |
| Member starts timer on external task | Solidtime timer UI shows external task reference (issue key, task URL); `TimeEntry` linked to `Task` which has an `external_task_mapping` |
| Time entry posts back to PM tool | Optional two-way sync: on time entry create/update, push duration to external API's time tracking field (Jira worklogs, Asana custom fields) |
| See external issue keys in Solidtime | UI displays `external_reference` (e.g., "PROJ-123") from `external_task_mappings` alongside task names |
| Real-time sync | Webhook endpoints receive events from Jira/Asana; update local project/task state |
| Select which projects to sync | `integration_projects` table tracks which external projects are enabled for sync; UI allows toggling |

### Scope Boundary

This feature **DOES**:
- Add new database tables and migrations for integration state
- Introduce new permissions (`integrations:view`, `integrations:manage`, `integrations:sync`)
- Create a provider-agnostic integration framework with adapters for Jira, Asana, and Trello
- Add OAuth 2.0 consumer capability (outbound authorization flows)
- Add webhook receiver endpoints
- Add background sync jobs (Laravel scheduled commands)
- Create new frontend pages and components for integration management
- Add external reference display to existing task/time entry UI

This feature does **NOT**:
- Modify the existing `TimeEntry`, `Project`, or `Task` table schemas (mapping is via join tables)
- Replace or modify existing time tracking workflows
- Embed Solidtime UI inside PM tools (browser extension is a separate future feature)
- Implement integrations beyond Jira, Asana, and Trello in this phase
- Add real-time push notifications when sync events occur (future enhancement)

---

## 3. Functional Specifications

### 3.1 Core Requirements

#### REQ-001: Integration Framework (Provider-Agnostic)
- **Description**: A pluggable integration framework that abstracts PM tool specifics behind a common adapter interface. Each provider (Jira, Asana, Trello) implements the adapter contract. The framework handles connection lifecycle, token management, sync orchestration, and error handling.
- **Priority**: P0
- **Edge Cases**:
  - Provider API is unreachable during connection attempt (show error, allow retry)
  - Provider API rate limits exceeded during sync (exponential backoff, partial sync resume)
  - Provider changes API version (adapter versioning, graceful degradation)
- **Error Scenarios**:
  - OAuth token expired and refresh fails (mark connection as "requires reauthorization", notify admin)
  - Webhook signature validation fails (reject event, log for security review)
  - Sync job encounters an unknown entity type from the provider (skip entity, log warning, continue sync)

#### REQ-002: OAuth Connection Flow
- **Description**: Admin initiates connection from the Integrations settings page. For Jira and Asana, this triggers an OAuth 2.0 authorization code flow. For Trello, the admin provides an API key and token (Trello uses a simplified OAuth 1.0a/token model). Encrypted tokens are stored in `integration_connections`. The connection can be tested, refreshed, and revoked.
- **Priority**: P0
- **Interaction Flow**:
  1. Admin navigates to Organization Settings > Integrations
  2. Clicks "Connect" on a PM tool card (Jira, Asana, or Trello)
  3. For Jira/Asana: Redirected to provider's OAuth consent page
  4. Admin grants access and is redirected back to Solidtime with authorization code
  5. Backend exchanges code for access/refresh tokens
  6. Connection record created; status shown as "Connected"
  7. For Trello: Admin enters API key + token in a form; backend validates by making a test API call
- **Edge Cases**:
  - Admin cancels OAuth flow midway (handle callback without code, show "Connection cancelled" message)
  - OAuth state parameter mismatch (reject, possible CSRF attack)
  - Multiple admins attempt to connect simultaneously (last writer wins, previous connection overwritten)
  - Organization already has an active connection for the same provider (prompt to replace or keep)

#### REQ-003: Jira Integration
- **Description**: Full integration with Jira Cloud via Atlassian Connect / OAuth 2.0 (3LO). Syncs Jira projects as Solidtime projects, Jira issues as Solidtime tasks. Supports webhook-driven real-time sync. Optionally posts Solidtime time entries as Jira worklogs.
- **Priority**: P0
- **Sync Mapping**:
  - Jira Project -> Solidtime Project (name, key as external reference)
  - Jira Issue -> Solidtime Task (summary as name, issue key as external reference, status maps to `done_at`)
  - Jira Issue hierarchy: Epic > Story > Sub-task (flattened to Solidtime tasks under the project, with parent reference stored in mapping metadata)
  - Jira Worklog <- Solidtime TimeEntry (optional write-back: description, time spent, author)
- **Webhook Events** (Jira -> Solidtime):
  - `jira:issue_created` -> Create task mapping
  - `jira:issue_updated` -> Update task name/status
  - `jira:issue_deleted` -> Soft-delete task mapping (mark inactive, do not delete Solidtime task)
  - `project_created`, `project_updated`, `project_deleted` -> Update project mappings
- **Edge Cases**:
  - Jira issue moved to a different project (update mapping's project reference)
  - Jira project archived (mark integration project as inactive)
  - Custom issue types (map all issue types as tasks; store type in mapping metadata)
  - Jira Server/Data Center (out of scope for v1; Jira Cloud only)

#### REQ-004: Asana Integration
- **Description**: Integration with Asana via OAuth 2.0. Syncs Asana workspaces/projects as Solidtime projects, Asana tasks as Solidtime tasks. Supports webhook-driven real-time sync. Time posting back to Asana uses the Asana API to add time tracking data (custom fields or comments).
- **Priority**: P0
- **Sync Mapping**:
  - Asana Workspace -> Organization-level scope (one workspace per connection)
  - Asana Project -> Solidtime Project
  - Asana Task -> Solidtime Task (name, completion status maps to `done_at`)
  - Asana Section -> Stored as metadata on task mapping (for reference, not as Solidtime entity)
  - Asana Subtask -> Flattened as additional Solidtime tasks under the same project
- **Webhook Events** (Asana -> Solidtime):
  - Asana webhooks use a subscription model: subscribe to project resources
  - Task added/changed/removed events trigger sync
  - Project name/status changes trigger project mapping update
- **Edge Cases**:
  - Asana task in multiple projects (map to primary project; store all project GIDs in metadata)
  - Asana workspace vs. organization distinction (support both via adapter configuration)
  - Asana rate limits (150 requests/minute; implement request queuing)

#### REQ-005: Trello Integration
- **Description**: Integration with Trello via API key + token. Syncs Trello boards as Solidtime projects, Trello cards as Solidtime tasks. Trello does not support webhooks in the same model, so sync is polling-based with configurable frequency. No native time tracking field in Trello, so write-back is via comments or a Power-Up custom field.
- **Priority**: P1
- **Sync Mapping**:
  - Trello Board -> Solidtime Project (name, board URL as external reference)
  - Trello Card -> Solidtime Task (name, card short link as external reference)
  - Trello List -> Stored as metadata (maps to workflow state; closed lists mark tasks done)
  - Trello Label -> Stored as metadata (not mapped to Solidtime tags in v1)
- **Sync Strategy**: Polling-based (configurable interval: 5, 15, 30 minutes)
  - Trello supports webhooks via model-level callbacks; can be added as enhancement
- **Edge Cases**:
  - Trello card archived (mark task mapping as inactive)
  - Trello board closed (mark integration project as inactive)
  - Trello free tier API limits (300 requests per 10 seconds per token)

#### REQ-006: Task Mapping and External References
- **Description**: A mapping layer that connects external PM tool entities to Solidtime entities. Each external task/project has a unique mapping record that stores the external ID, provider type, and metadata. This mapping enables cross-system reporting and ensures that Solidtime tasks created via sync are properly linked to their external counterparts.
- **Priority**: P0
- **Behavior**:
  - When a synced task appears in the Solidtime UI (timesheet grid, task selector, time entry form), the external reference (e.g., "PROJ-123" for Jira, task URL for Asana) is displayed alongside the task name
  - External reference is clickable, opening the original issue/task in the PM tool (new tab)
  - Time entries against mapped tasks include the external reference in API responses
  - Deleting a mapping does not delete the underlying Solidtime task or time entries (orphan gracefully)
- **Edge Cases**:
  - External entity ID changes (rare but possible in migrations; admin can remap manually)
  - Duplicate external references (unique constraint on provider + external_id per connection)
  - Mapping to a Solidtime task that was manually created (admin can link existing tasks)

#### REQ-007: Two-Way Sync Strategy
- **Description**: A configurable sync direction for each connection. Options: "Import only" (external -> Solidtime), "Export only" (Solidtime -> external), or "Bidirectional". The default is "Import only". Export/bidirectional sync posts Solidtime time entries to the PM tool's native time tracking mechanism.
- **Priority**: P1
- **Sync Directions**:
  - **Import (External -> Solidtime)**: Projects and tasks synced from PM tool to Solidtime. This is always active for connected integrations.
  - **Export (Solidtime -> External)**: Time entries from Solidtime are posted to the PM tool. For Jira, this creates worklogs. For Asana, this updates a custom time tracking field or adds a comment. For Trello, this adds a comment to the card.
  - **Bidirectional**: Both import and export are active.
- **Conflict Resolution**:
  - Import sync: External source of truth for project/task names and structure
  - Export sync: Solidtime source of truth for time entries; external worklogs/time data are additive (no delete sync)
  - If a time entry is modified in Solidtime after being exported, the updated value is re-pushed on next sync cycle
  - If a time entry is deleted in Solidtime, the corresponding external worklog is NOT deleted (safety measure)
- **Edge Cases**:
  - Export fails for a single time entry (mark as "export pending", retry on next cycle)
  - External worklog already exists for the same time period (update if IDs match, skip if manual entry)
  - Rate limit exceeded during export batch (queue remaining entries for next cycle)

### 3.2 User Workflows

```
Admin connects integration:
    -> Navigate to Organization Settings > Integrations
    -> Click "Connect Jira" (or Asana / Trello)
    -> Complete OAuth flow (or enter API key for Trello)
    -> Connection confirmed; "Connected" badge shown
    -> Select projects to sync (checkbox list of external projects)
    -> Click "Start Sync" to trigger initial import
    -> Progress indicator shows sync status
    -> On completion: synced projects and tasks appear in Solidtime

Admin manages integration:
    -> View connection status (Connected / Requires Reauth / Error)
    -> Toggle sync direction (Import Only / Export / Bidirectional)
    -> Configure sync frequency (for polling-based: 5/15/30 min)
    -> View sync history (last sync time, records synced, errors)
    -> Disconnect integration (revoke tokens, deactivate mappings)

Member tracks time on synced task:
    -> Open Time page or Timesheet grid
    -> Search for task by name or external reference (e.g., "PROJ-123")
    -> Task appears with external reference badge (e.g., Jira icon + "PROJ-123")
    -> Start timer or enter hours
    -> Time entry saved with external task reference
    -> If export sync enabled: time entry queued for push to PM tool

System performs background sync:
    -> Scheduled job runs at configured interval
    -> For each active connection:
        -> Check token validity; refresh if needed
        -> Fetch updated projects/tasks from external API
        -> Create/update/deactivate mappings
        -> If export enabled: push pending time entries
        -> Log sync results (success count, error count, duration)

Webhook receives real-time event (Jira/Asana):
    -> External PM tool sends event to webhook endpoint
    -> Validate webhook signature/token
    -> Parse event type and payload
    -> Create/update/deactivate task or project mapping
    -> Log event processing result
```

### 3.3 Business Rules

#### Integration Connection
1. Only users with `integrations:manage` permission can connect/disconnect integrations
2. Only one active connection per provider per organization (e.g., one Jira connection)
3. OAuth tokens are encrypted at rest using Laravel's encryption (`Crypt::encryptString`)
4. Refresh tokens are rotated on each refresh cycle
5. Connections that fail token refresh 3 consecutive times are marked "requires_reauthorization"
6. Disconnecting an integration deactivates all mappings but does not delete Solidtime projects, tasks, or time entries

#### Project Sync
1. External projects are only synced if explicitly selected by the admin (opt-in model)
2. Synced projects create new Solidtime `Project` records (not reused from existing manually-created projects, unless explicitly linked by admin)
3. Project names and metadata update on each sync cycle (external is source of truth)
4. Archived/deleted external projects are marked inactive in `integration_projects` (soft deactivation)
5. Solidtime project `color` is auto-assigned from `ColorService` for synced projects
6. Solidtime project `is_billable` defaults to `false` for synced projects (admin can change)

#### Task Sync
1. All tasks within a synced project are imported (no per-task opt-in in v1)
2. Tasks are created as Solidtime `Task` records linked via `external_task_mappings`
3. Task names and completion status update on each sync cycle
4. Completed/resolved external tasks set `done_at` on the Solidtime task
5. Deleted external tasks mark the mapping as inactive; the Solidtime task remains (with its time entries)
6. Task `estimated_time` is synced from external if available (Jira story points converted to hours via configurable ratio, Asana custom fields)

#### Time Entry Export (Write-Back)
1. Only time entries on mapped tasks are eligible for export
2. Export is batched: accumulated since last sync or since entry creation
3. Jira: Creates/updates worklogs with `timeSpentSeconds`, `comment` (description), and `started` (time entry start)
4. Asana: Posts time as a comment in format "Tracked X hours via Solidtime" (Asana has no native time tracking field)
5. Trello: Posts time as a comment on the card
6. Exported entries are marked with `exported_at` timestamp in a sync log to avoid re-export
7. Failed exports are retried up to 3 times with exponential backoff

---

## 4. Technical Requirements & Constraints

### 4.1 System Architecture

```
+-----------------------------------------------------------------------+
|                        Frontend (Vue.js 3)                             |
+-----------------------------------------------------------------------+
|  +-------------------+  +---------------------+  +-----------------+  |
|  | Integrations.vue  |--| IntegrationCard.vue |--| ProjectSync.vue |  |
|  | (Settings page)   |  | - Provider card     |  | - Project list  |  |
|  | - Connection list  |  | - Status badge      |  | - Toggle sync   |  |
|  | - Connect buttons  |  | - Config panel      |  | - Sync history  |  |
|  +--------+----------+  +---------------------+  +-----------------+  |
|           |                                                            |
|           v                                                            |
|  +----------------------------------------------+                     |
|  | useIntegrationStore.ts (Pinia)               |                     |
|  | - connections: IntegrationConnection[]        |                     |
|  | - syncStatus: Map<string, SyncStatus>        |                     |
|  | - connect() / disconnect() / sync()          |                     |
|  | - getExternalProjects() / toggleProject()     |                     |
|  +---------------------+------------------------+                     |
|                        | HTTP/JSON                                     |
+------------------------|-------------------------------------------------+
                         v
+-----------------------------------------------------------------------+
|                        Backend (Laravel 11)                            |
+-----------------------------------------------------------------------+
|  +----------------------------------------------+                     |
|  | PmIntegrationController.php                    |                     |
|  | - index()        GET  /integrations          |                     |
|  | - show()         GET  /integrations/{id}     |                     |
|  | - connect()      POST /integrations/connect  |                     |
|  | - callback()     GET  /integrations/callback |                     |
|  | - disconnect()   DELETE /integrations/{id}   |                     |
|  | - syncNow()      POST /integrations/{id}/sync|                     |
|  | - update()       PUT  /integrations/{id}     |                     |
|  +---------------------+------------------------+                     |
|                        |                                               |
|  +----------------------------------------------+                     |
|  | IntegrationProjectController.php             |                     |
|  | - index()        GET  /integration-projects  |                     |
|  | - toggle()       PUT  /{id}/toggle           |                     |
|  | - externalList() GET  /{id}/external-projects|                     |
|  +---------------------+------------------------+                     |
|                        |                                               |
|  +----------------------------------------------+                     |
|  | PmWebhookController.php                        |                     |
|  | - jira()         POST /webhooks/jira/{org}   |                     |
|  | - asana()        POST /webhooks/asana/{org}  |                     |
|  +---------------------+------------------------+                     |
|                        |                                               |
|                        v                                               |
|  +----------------------------------------------+                     |
|  | IntegrationService.php                       |                     |
|  | - connect() / disconnect()                   |                     |
|  | - syncProjects() / syncTasks()               |                     |
|  | - exportTimeEntries()                        |                     |
|  | - refreshToken()                             |                     |
|  | - getAdapter(): IntegrationAdapterInterface  |                     |
|  +---------------------+------------------------+                     |
|                        |                                               |
|                        v                                               |
|  +-----------------------+------------------------+                    |
|  |   Adapter Layer (app/Service/Integration/)    |                    |
|  |                                                |                    |
|  |  IntegrationAdapterInterface                   |                    |
|  |  +-- JiraAdapter.php                           |                    |
|  |  |   - authenticate() / refreshToken()         |                    |
|  |  |   - getProjects() / getTasks()              |                    |
|  |  |   - createWorklog() / registerWebhook()     |                    |
|  |  +-- AsanaAdapter.php                          |                    |
|  |  |   - authenticate() / refreshToken()         |                    |
|  |  |   - getProjects() / getTasks()              |                    |
|  |  |   - postTimeComment() / subscribeWebhook()  |                    |
|  |  +-- TrelloAdapter.php                         |                    |
|  |      - authenticate() (API key validation)     |                    |
|  |      - getBoards() / getCards()                |                    |
|  |      - postTimeComment()                       |                    |
|  +-----------------------+------------------------+                    |
|                          |                                             |
|                          v                                             |
|  +----------------------------------------------+                     |
|  | Database Models                              |                     |
|  | - IntegrationConnection (NEW)                |                     |
|  | - IntegrationProject (NEW)                   |                     |
|  | - ExternalTaskMapping (NEW)                  |                     |
|  | - IntegrationSyncLog (NEW)                   |                     |
|  | - Project (existing, read/write)             |                     |
|  | - Task (existing, read/write)                |                     |
|  | - TimeEntry (existing, read-only for export) |                     |
|  +----------------------------------------------+                     |
|                                                                        |
|  +----------------------------------------------+                     |
|  | Background Jobs                              |                     |
|  | - SyncIntegrationJob (queued, per connection) |                     |
|  | - ExportTimeEntriesJob (queued, per connection)|                    |
|  | - RefreshIntegrationTokenJob (scheduled)      |                     |
|  +----------------------------------------------+                     |
+-----------------------------------------------------------------------+
```

### 4.2 Data Models

#### New Database Tables

##### `integration_connections`

```sql
CREATE TABLE integration_connections (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    provider VARCHAR(50) NOT NULL,              -- 'jira', 'asana', 'trello'
    status VARCHAR(50) NOT NULL DEFAULT 'connected',  -- 'connected', 'requires_reauth', 'error', 'disconnected'
    access_token TEXT,                           -- encrypted
    refresh_token TEXT,                          -- encrypted
    token_expires_at TIMESTAMP NULL,
    external_account_id VARCHAR(255) NULL,       -- e.g., Jira cloud ID, Asana workspace GID
    external_account_name VARCHAR(255) NULL,     -- e.g., "My Company Jira", "My Workspace"
    sync_direction VARCHAR(20) NOT NULL DEFAULT 'import',  -- 'import', 'export', 'bidirectional'
    sync_frequency_minutes INT NOT NULL DEFAULT 15,
    last_sync_at TIMESTAMP NULL,
    last_sync_status VARCHAR(50) NULL,           -- 'success', 'partial', 'error'
    last_sync_error TEXT NULL,
    webhook_secret VARCHAR(255) NULL,            -- encrypted, for validating inbound webhooks
    settings JSONB NULL,                         -- provider-specific settings (e.g., Jira cloud ID, site URL)
    connected_by UUID NOT NULL REFERENCES users(id),
    refresh_failure_count INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (organization_id, provider)
);

CREATE INDEX idx_integration_connections_org ON integration_connections(organization_id);
CREATE INDEX idx_integration_connections_status ON integration_connections(organization_id, status);
```

##### `integration_projects`

```sql
CREATE TABLE integration_projects (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    integration_connection_id UUID NOT NULL REFERENCES integration_connections(id) ON DELETE CASCADE,
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    project_id UUID NULL REFERENCES projects(id) ON DELETE SET NULL,  -- linked Solidtime project
    external_project_id VARCHAR(255) NOT NULL,   -- e.g., Jira project ID, Asana project GID
    external_project_key VARCHAR(100) NULL,      -- e.g., Jira project key "PROJ"
    external_project_name VARCHAR(255) NOT NULL,
    external_project_url VARCHAR(500) NULL,
    is_enabled BOOLEAN NOT NULL DEFAULT false,   -- admin must opt-in to sync
    is_active BOOLEAN NOT NULL DEFAULT true,     -- false if external project deleted/archived
    metadata JSONB NULL,                         -- provider-specific data
    last_synced_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (integration_connection_id, external_project_id)
);

CREATE INDEX idx_integration_projects_connection ON integration_projects(integration_connection_id);
CREATE INDEX idx_integration_projects_project ON integration_projects(project_id);
CREATE INDEX idx_integration_projects_enabled ON integration_projects(integration_connection_id, is_enabled);
```

##### `external_task_mappings`

```sql
CREATE TABLE external_task_mappings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    integration_project_id UUID NOT NULL REFERENCES integration_projects(id) ON DELETE CASCADE,
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    task_id UUID NULL REFERENCES tasks(id) ON DELETE SET NULL,  -- linked Solidtime task
    external_task_id VARCHAR(255) NOT NULL,       -- e.g., Jira issue ID, Asana task GID
    external_reference VARCHAR(100) NOT NULL,     -- human-readable: "PROJ-123", card short link
    external_task_name VARCHAR(500) NOT NULL,
    external_task_url VARCHAR(500) NULL,
    external_status VARCHAR(100) NULL,            -- e.g., "In Progress", "Done"
    external_parent_id VARCHAR(255) NULL,         -- for hierarchy (epic/parent task)
    is_active BOOLEAN NOT NULL DEFAULT true,
    metadata JSONB NULL,                          -- labels, assignee, priority, story points, etc.
    last_synced_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (integration_project_id, external_task_id)
);

CREATE INDEX idx_external_task_mappings_project ON external_task_mappings(integration_project_id);
CREATE INDEX idx_external_task_mappings_task ON external_task_mappings(task_id);
CREATE INDEX idx_external_task_mappings_ref ON external_task_mappings(organization_id, external_reference);
CREATE INDEX idx_external_task_mappings_active ON external_task_mappings(integration_project_id, is_active);
```

##### `integration_sync_logs`

```sql
CREATE TABLE integration_sync_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    integration_connection_id UUID NOT NULL REFERENCES integration_connections(id) ON DELETE CASCADE,
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    sync_type VARCHAR(50) NOT NULL,               -- 'full', 'incremental', 'webhook', 'export'
    direction VARCHAR(20) NOT NULL,               -- 'import', 'export'
    status VARCHAR(50) NOT NULL,                  -- 'started', 'completed', 'partial', 'failed'
    projects_synced INT NOT NULL DEFAULT 0,
    tasks_synced INT NOT NULL DEFAULT 0,
    time_entries_exported INT NOT NULL DEFAULT 0,
    errors_count INT NOT NULL DEFAULT 0,
    error_details JSONB NULL,                     -- array of error messages
    started_at TIMESTAMP NOT NULL,
    completed_at TIMESTAMP NULL,
    duration_ms INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_sync_logs_connection ON integration_sync_logs(integration_connection_id);
CREATE INDEX idx_sync_logs_org_date ON integration_sync_logs(organization_id, started_at);
```

#### Frontend Types (TypeScript)

```typescript
type PmProvider = 'jira' | 'asana' | 'trello';
type ConnectionStatus = 'connected' | 'requires_reauth' | 'error' | 'disconnected';
type SyncDirection = 'import' | 'export' | 'bidirectional';
type SyncLogStatus = 'started' | 'completed' | 'partial' | 'failed';

interface IntegrationConnection {
    id: string;
    organization_id: string;
    provider: PmProvider;
    status: ConnectionStatus;
    external_account_id: string | null;
    external_account_name: string | null;
    sync_direction: SyncDirection;
    sync_frequency_minutes: number;
    last_sync_at: string | null;
    last_sync_status: string | null;
    last_sync_error: string | null;
    connected_by: string;
    created_at: string;
    updated_at: string;
}

interface IntegrationProject {
    id: string;
    integration_connection_id: string;
    project_id: string | null;
    external_project_id: string;
    external_project_key: string | null;
    external_project_name: string;
    external_project_url: string | null;
    is_enabled: boolean;
    is_active: boolean;
    last_synced_at: string | null;
}

interface ExternalTaskMapping {
    id: string;
    integration_project_id: string;
    task_id: string | null;
    external_task_id: string;
    external_reference: string;
    external_task_name: string;
    external_task_url: string | null;
    external_status: string | null;
    is_active: boolean;
}

interface IntegrationSyncLog {
    id: string;
    integration_connection_id: string;
    sync_type: string;
    direction: string;
    status: SyncLogStatus;
    projects_synced: number;
    tasks_synced: number;
    time_entries_exported: number;
    errors_count: number;
    error_details: string[] | null;
    started_at: string;
    completed_at: string | null;
    duration_ms: number | null;
}

interface ExternalProjectListItem {
    external_project_id: string;
    external_project_key: string | null;
    external_project_name: string;
    external_project_url: string | null;
    is_already_synced: boolean;
}
```

### 4.3 API Contracts

#### GET /api/v1/organizations/{organization}/integrations
List all integration connections for the organization.

```yaml
Parameters:
  organization: string (path, required)
Request Headers:
  Authorization: Bearer {token}
Response 200:
  data: Array<{
    id: string,
    provider: string,              # 'jira' | 'asana' | 'trello'
    status: string,                # 'connected' | 'requires_reauth' | 'error' | 'disconnected'
    external_account_name: string|null,
    sync_direction: string,
    sync_frequency_minutes: int,
    last_sync_at: string|null,
    last_sync_status: string|null,
    last_sync_error: string|null,
    connected_by: string,
    created_at: string,
    updated_at: string
  }>
Permission: integrations:view
```

#### GET /api/v1/organizations/{organization}/integrations/{integration}
Get details of a specific integration connection.

```yaml
Parameters:
  organization: string (path, required)
  integration: string (path, required)
Request Headers:
  Authorization: Bearer {token}
Response 200:
  data: {
    id: string,
    provider: string,
    status: string,
    external_account_id: string|null,
    external_account_name: string|null,
    sync_direction: string,
    sync_frequency_minutes: int,
    last_sync_at: string|null,
    last_sync_status: string|null,
    last_sync_error: string|null,
    settings: object|null,          # provider-specific (site URL, etc.)
    connected_by: string,
    created_at: string,
    updated_at: string,
    projects: Array<IntegrationProject>,
    recent_sync_logs: Array<IntegrationSyncLog>
  }
Response 404:
  error: { message: "Integration not found" }
Permission: integrations:view
```

#### POST /api/v1/organizations/{organization}/integrations/connect
Initiate a new integration connection. Returns an OAuth redirect URL for Jira/Asana, or validates API key for Trello.

```yaml
Parameters:
  organization: string (path, required)
Request Body:
  provider: string (required, 'jira' | 'asana' | 'trello')
  # For Trello only:
  api_key: string (optional, required for Trello)
  api_token: string (optional, required for Trello)
  # For Jira:
  site_url: string (optional, Jira Cloud site URL e.g., "mycompany.atlassian.net")
Response 200 (Jira/Asana):
  data: {
    redirect_url: string,           # OAuth authorization URL
    state: string                   # CSRF state parameter
  }
Response 201 (Trello):
  data: IntegrationConnection       # Connection created directly
Response 400:
  error: { message: "Validation error", details: {...} }
Response 409:
  error: { message: "An active connection for this provider already exists" }
Middleware: check-organization-blocked
Permission: integrations:manage
```

#### GET /api/v1/organizations/{organization}/integrations/callback
OAuth callback endpoint. Handles authorization code exchange.

```yaml
Parameters:
  organization: string (path, required)
  code: string (query, required)
  state: string (query, required)
Request Headers:
  Authorization: Bearer {token}
Response 302:
  Redirect to: /organizations/{organization}/settings/integrations?connected={provider}
Response 400:
  error: { message: "Invalid state parameter" }
Response 502:
  error: { message: "Failed to exchange authorization code" }
Permission: integrations:manage
```

#### PUT /api/v1/organizations/{organization}/integrations/{integration}
Update integration settings (sync direction, frequency).

```yaml
Parameters:
  organization: string (path, required)
  integration: string (path, required)
Request Body:
  sync_direction: string (optional, 'import' | 'export' | 'bidirectional')
  sync_frequency_minutes: int (optional, 5 | 15 | 30 | 60)
Response 200:
  data: IntegrationConnection
Middleware: check-organization-blocked
Permission: integrations:manage
```

#### DELETE /api/v1/organizations/{organization}/integrations/{integration}
Disconnect an integration. Revokes tokens, deactivates all mappings.

```yaml
Parameters:
  organization: string (path, required)
  integration: string (path, required)
Response 204: No Content
Permission: integrations:manage
```

#### POST /api/v1/organizations/{organization}/integrations/{integration}/sync
Trigger an immediate sync for the connection.

```yaml
Parameters:
  organization: string (path, required)
  integration: string (path, required)
Response 202:
  data: {
    message: "Sync job queued",
    sync_log_id: string
  }
Middleware: check-organization-blocked
Permission: integrations:sync
```

#### GET /api/v1/organizations/{organization}/integrations/{integration}/external-projects
List available projects from the external PM tool (for admin to select which to sync).

```yaml
Parameters:
  organization: string (path, required)
  integration: string (path, required)
Response 200:
  data: Array<{
    external_project_id: string,
    external_project_key: string|null,
    external_project_name: string,
    external_project_url: string|null,
    is_already_synced: boolean
  }>
Permission: integrations:manage
```

#### GET /api/v1/organizations/{organization}/integration-projects
List integration projects (synced project mappings) for the organization.

```yaml
Parameters:
  organization: string (path, required)
  integration_id: string (query, optional, filter by connection)
Response 200:
  data: Array<IntegrationProject>
Permission: integrations:view
```

#### PUT /api/v1/organizations/{organization}/integration-projects/{integrationProject}/toggle
Enable or disable sync for a specific external project.

```yaml
Parameters:
  organization: string (path, required)
  integrationProject: string (path, required)
Request Body:
  is_enabled: boolean (required)
Response 200:
  data: IntegrationProject
Middleware: check-organization-blocked
Permission: integrations:manage
```

#### GET /api/v1/organizations/{organization}/integration-sync-logs
Get sync history for the organization.

```yaml
Parameters:
  organization: string (path, required)
  integration_id: string (query, optional)
  limit: int (query, optional, default: 20, max: 100)
  offset: int (query, optional, default: 0)
Response 200:
  data: Array<IntegrationSyncLog>
Permission: integrations:view
```

#### POST /api/v1/webhooks/jira/{organization}
Jira webhook receiver (no auth middleware -- validated via webhook secret).

```yaml
Parameters:
  organization: string (path, required)
Request Headers:
  X-Atlassian-Webhook-Identifier: string
Request Body:
  webhookEvent: string,
  issue: object|null,
  project: object|null,
  ...
Response 200: { received: true }
Response 400: { error: "Invalid webhook signature" }
Response 404: { error: "No active Jira connection for this organization" }
```

#### POST /api/v1/webhooks/asana/{organization}
Asana webhook receiver. Handles both handshake (X-Hook-Secret) and events.

```yaml
Parameters:
  organization: string (path, required)
Request Headers:
  X-Hook-Secret: string (handshake only)
  X-Hook-Signature: string (events)
Request Body:
  events: Array<{ resource: object, action: string, ... }>
Response 200 (handshake):
  Headers:
    X-Hook-Secret: {echoed secret}
Response 200 (events): { received: true }
Response 400: { error: "Invalid webhook signature" }
```

### 4.4 Performance Requirements

| Metric | Target | Measurement |
|--------|--------|-------------|
| Integration page load | < 500ms | Time to render connection list |
| OAuth redirect | < 1s | Time from click to provider login page |
| External project list fetch | < 3s | API response for available projects |
| Initial sync (100 tasks) | < 30s | Time to import all tasks for a project |
| Incremental sync | < 10s | Time for periodic sync of changed items |
| Webhook processing | < 500ms | Time to process a single webhook event |
| Time entry export (batch of 50) | < 10s | Time to post entries to external API |
| Sync job memory | < 128MB | Peak memory per sync job |

### 4.5 Security Requirements

1. **OAuth Token Security**: Access tokens and refresh tokens encrypted at rest using Laravel's `Crypt::encryptString()`. Never exposed in API responses. Stored in `TEXT` columns (encrypted values are longer than raw tokens).
2. **Webhook Validation**: All inbound webhooks validated via provider-specific signature verification (Jira: shared secret HMAC, Asana: X-Hook-Signature HMAC-SHA256). Invalid signatures rejected with 400 response.
3. **CSRF Protection on OAuth Callback**: State parameter generated with `Str::random(40)`, stored in session, validated on callback.
4. **API Key Security (Trello)**: API keys and tokens encrypted before storage. Validated via test API call before persisting.
5. **Organization Scoping**: All queries scoped to the current organization. Webhook endpoints validate organization ID against the connection's organization.
6. **Permission Model**: New permissions gated at controller level:
   - `integrations:view` -- see connection status, sync history (Admin, Manager)
   - `integrations:manage` -- connect, disconnect, configure, select projects (Admin only)
   - `integrations:sync` -- trigger manual sync (Admin, Manager)
7. **Rate Limiting**: Webhook endpoints rate-limited to 100 requests/minute per organization. OAuth callback limited to 10 requests/minute per user.
8. **Data Isolation**: Integration credentials are never shared between organizations. Webhook secrets are unique per connection.
9. **Audit Logging**: Connection create/delete/update operations logged via `CustomAuditable` trait. Sync operations logged in `integration_sync_logs`.

---

## 5. User Stories with Acceptance Criteria

### USR-001: View Available Integrations
**As an** organization admin
**I want to** see which PM tool integrations are available and their connection status
**So that** I can decide which tools to connect

**Priority**: P0 | **Effort**: 3 SP | **Sprint**: 1

**Acceptance Criteria**:
- [ ] Integrations page accessible from Organization Settings navigation
- [ ] Cards displayed for Jira, Asana, and Trello
- [ ] Each card shows provider name, logo/icon, description, and status badge
- [ ] Connected integrations show "Connected" badge with last sync time
- [ ] Disconnected integrations show "Connect" button
- [ ] Error/reauth state shows warning badge with action button
- [ ] Users without `integrations:view` permission do not see the page

### USR-002: Connect Jira Integration
**As an** organization admin
**I want to** connect my Jira Cloud instance to Solidtime
**So that** my team's Jira projects and issues are available for time tracking

**Priority**: P0 | **Effort**: 8 SP | **Sprint**: 1-2

**Acceptance Criteria**:
- [ ] Clicking "Connect Jira" opens a dialog to enter Jira site URL
- [ ] After entering site URL, user is redirected to Atlassian OAuth consent page
- [ ] After granting access, user is redirected back to Solidtime
- [ ] Connection status changes to "Connected" with Jira site name displayed
- [ ] List of Jira projects is fetched and shown for selection
- [ ] Admin can select/deselect projects to sync
- [ ] Initial sync imports selected projects and their issues as tasks
- [ ] Jira issue keys (e.g., "PROJ-123") stored as external references
- [ ] If OAuth is cancelled, connection is not created and user sees error message
- [ ] Only users with `integrations:manage` permission can connect

### USR-003: Connect Asana Integration
**As an** organization admin
**I want to** connect my Asana workspace to Solidtime
**So that** my team's Asana projects and tasks are available for time tracking

**Priority**: P0 | **Effort**: 8 SP | **Sprint**: 2

**Acceptance Criteria**:
- [ ] Clicking "Connect Asana" redirects to Asana OAuth consent page
- [ ] After granting access, user is redirected back to Solidtime
- [ ] Connection status changes to "Connected" with workspace name displayed
- [ ] List of Asana projects is fetched and shown for selection
- [ ] Admin can select/deselect projects to sync
- [ ] Initial sync imports selected projects and their tasks
- [ ] Asana task names and GIDs stored in mappings
- [ ] Completed Asana tasks set `done_at` on corresponding Solidtime tasks
- [ ] Only users with `integrations:manage` permission can connect

### USR-004: Connect Trello Integration
**As an** organization admin
**I want to** connect Trello to Solidtime using my API credentials
**So that** my team's Trello boards and cards are available for time tracking

**Priority**: P1 | **Effort**: 5 SP | **Sprint**: 3

**Acceptance Criteria**:
- [ ] Clicking "Connect Trello" opens a form for API key and token
- [ ] Link provided to Trello's API key generation page
- [ ] Validation makes a test API call to verify credentials
- [ ] Invalid credentials show clear error message
- [ ] On success, connection created and Trello boards listed for selection
- [ ] Admin can select/deselect boards to sync
- [ ] Initial sync imports selected boards' cards as tasks
- [ ] Card short links stored as external references
- [ ] Only users with `integrations:manage` permission can connect

### USR-005: Select Projects for Sync
**As an** organization admin
**I want to** choose which external projects to sync to Solidtime
**So that** only relevant projects are imported and my workspace stays organized

**Priority**: P0 | **Effort**: 5 SP | **Sprint**: 2

**Acceptance Criteria**:
- [ ] After connecting, a project selection panel shows available external projects
- [ ] Each project shows its name, key (if applicable), and sync status
- [ ] Toggle switch enables/disables sync per project
- [ ] Enabling a project triggers initial task import
- [ ] Disabling a project deactivates mappings but does not delete Solidtime data
- [ ] Search/filter available within the project list
- [ ] "Select All" and "Deselect All" convenience buttons

### USR-006: Track Time on Synced Task
**As a** team member
**I want to** see external task references when selecting tasks for time tracking
**So that** I can easily find and track time against the right task

**Priority**: P0 | **Effort**: 5 SP | **Sprint**: 3

**Acceptance Criteria**:
- [ ] Task selector (dropdown, search) shows external reference alongside task name
- [ ] Format: "[PROJ-123] Task Name" for Jira, "Task Name" with Asana/Trello icon for others
- [ ] External reference is searchable (typing "PROJ-123" finds the task)
- [ ] Clicking the external reference opens the task in the PM tool (new tab)
- [ ] Time entries on synced tasks include the external reference in list views
- [ ] Timesheet grid rows for synced tasks show the external reference badge
- [ ] Users without synced tasks see no change to their experience

### USR-007: Configure Sync Settings
**As an** organization admin
**I want to** configure how sync works for each integration
**So that** I can control data flow direction and frequency

**Priority**: P1 | **Effort**: 3 SP | **Sprint**: 3

**Acceptance Criteria**:
- [ ] Settings panel accessible from integration detail page
- [ ] Sync direction dropdown: Import Only, Export, Bidirectional
- [ ] Sync frequency dropdown: 5 min, 15 min, 30 min, 60 min
- [ ] Changes saved immediately with confirmation message
- [ ] "Sync Now" button triggers an immediate manual sync
- [ ] Sync status indicator shows "Syncing..." during active sync
- [ ] Last sync timestamp and result (success/partial/error) displayed

### USR-008: View Sync History
**As an** organization admin
**I want to** see the history of sync operations
**So that** I can troubleshoot issues and verify data is up to date

**Priority**: P1 | **Effort**: 3 SP | **Sprint**: 4

**Acceptance Criteria**:
- [ ] Sync history log accessible from integration detail page
- [ ] Each log entry shows: timestamp, type (full/incremental/webhook), direction, status
- [ ] Success entries show counts: projects synced, tasks synced, entries exported
- [ ] Error entries show error count and expandable error details
- [ ] Paginated list (20 entries per page)
- [ ] Filter by sync type and status

### USR-009: Disconnect Integration
**As an** organization admin
**I want to** disconnect a PM tool integration
**So that** I can stop syncing when it is no longer needed

**Priority**: P0 | **Effort**: 2 SP | **Sprint**: 2

**Acceptance Criteria**:
- [ ] "Disconnect" button on integration detail page
- [ ] Confirmation dialog: "This will stop syncing. Existing projects and time entries will be preserved."
- [ ] On confirm: OAuth tokens revoked, connection marked disconnected
- [ ] All project mappings deactivated
- [ ] Existing Solidtime projects, tasks, and time entries remain unchanged
- [ ] Integration card returns to "Connect" state

### USR-010: Automatic Background Sync
**As a** team member
**I want** projects and tasks to stay in sync automatically
**So that** I always have up-to-date task lists without manual intervention

**Priority**: P0 | **Effort**: 8 SP | **Sprint**: 2-3

**Acceptance Criteria**:
- [ ] Background sync job runs at the configured frequency
- [ ] New external tasks appear as Solidtime tasks within one sync cycle
- [ ] Updated external task names/statuses reflected in Solidtime
- [ ] Deleted/archived external tasks have their mappings deactivated
- [ ] New external projects (on connected accounts) appear in the project selection list
- [ ] Sync job handles token refresh automatically
- [ ] Failed sync logs the error and retries on next cycle
- [ ] Sync does not create duplicate tasks on repeated runs

### USR-011: Two-Way Time Entry Export
**As an** organization admin
**I want** time tracked in Solidtime to be posted back to the PM tool
**So that** stakeholders who use the PM tool can see time data without logging into Solidtime

**Priority**: P1 | **Effort**: 8 SP | **Sprint**: 4

**Acceptance Criteria**:
- [ ] When sync direction is "Export" or "Bidirectional", time entries on mapped tasks are exported
- [ ] Jira: Creates worklogs with time spent, description, and start time
- [ ] Asana: Posts a comment with time details
- [ ] Trello: Posts a comment with time details on the card
- [ ] Exported entries are tracked to prevent re-export
- [ ] Modified entries are re-exported with updated values
- [ ] Deleted entries are NOT removed from the external tool (safety)
- [ ] Export errors are logged and retried (up to 3 attempts)
- [ ] Batch export processes up to 50 entries per run

---

## 6. Task Breakdown Structure

See `task_assignments_20260209.md` for the full task table.

| Task ID | Description | Type | Effort | Dependencies |
|---------|-------------|------|--------|--------------|
| PMI-001 | Create database migrations for integration tables | Backend | 8h | None |
| PMI-002 | Create Eloquent models (IntegrationConnection, IntegrationProject, ExternalTaskMapping, IntegrationSyncLog) | Backend | 8h | PMI-001 |
| PMI-003 | Create IntegrationAdapterInterface and base adapter class | Backend | 4h | None |
| PMI-004 | Implement JiraAdapter with OAuth 2.0 and API methods | Backend | 16h | PMI-003 |
| PMI-005 | Implement AsanaAdapter with OAuth 2.0 and API methods | Backend | 16h | PMI-003 |
| PMI-006 | Implement TrelloAdapter with API key auth and methods | Backend | 12h | PMI-003 |
| PMI-007 | Create IntegrationService (connection lifecycle, sync orchestration) | Backend | 16h | PMI-002, PMI-003 |
| PMI-008 | Create PmIntegrationController with endpoint stubs | Backend | 4h | PMI-002 |
| PMI-009 | Create IntegrationProjectController | Backend | 4h | PMI-002 |
| PMI-010 | Create PmWebhookController for Jira and Asana | Backend | 8h | PMI-004, PMI-005 |
| PMI-011 | Create request validation classes | Backend | 4h | PMI-008, PMI-009 |
| PMI-012 | Register API routes for integration endpoints | Backend | 2h | PMI-008, PMI-009, PMI-010 |
| PMI-013 | Wire PmIntegrationController to IntegrationService and adapters | Backend | 8h | PMI-007, PMI-008, PMI-011 |
| PMI-014 | Wire IntegrationProjectController to IntegrationService | Backend | 4h | PMI-007, PMI-009, PMI-011 |
| PMI-015 | Wire PmWebhookController to IntegrationService | Backend | 4h | PMI-007, PMI-010 |
| PMI-016 | Register integration permissions in CorePermissions/IntegrationPermissions | Backend | 2h | None |
| PMI-017 | Create SyncIntegrationJob (background sync) | Backend | 8h | PMI-007, PMI-004, PMI-005, PMI-006 |
| PMI-018 | Create ExportTimeEntriesJob (time entry write-back) | Backend | 8h | PMI-007, PMI-004, PMI-005, PMI-006 |
| PMI-019 | Create RefreshIntegrationTokenJob (scheduled token refresh) | Backend | 4h | PMI-007 |
| PMI-020 | Register scheduled jobs in Console Kernel | Backend | 2h | PMI-017, PMI-018, PMI-019 |
| PMI-021 | Update OpenAPI spec and regenerate TypeScript client | Backend / Docs | 6h | PMI-012, PMI-013, PMI-014 |
| PMI-022 | Create TypeScript types for integration entities | Frontend | 4h | PMI-021 |
| PMI-023 | Create useIntegrationStore Pinia store | Frontend | 8h | PMI-022 |
| PMI-024 | Create Integrations.vue settings page | Frontend | 8h | PMI-023 |
| PMI-025 | Create IntegrationCard.vue component | Frontend | 6h | PMI-024 |
| PMI-026 | Create IntegrationDetail.vue component (settings, sync history) | Frontend | 8h | PMI-024, PMI-023 |
| PMI-027 | Create ProjectSyncPanel.vue (project selection, toggle) | Frontend | 8h | PMI-024, PMI-023 |
| PMI-028 | Create SyncHistoryLog.vue component | Frontend | 4h | PMI-026 |
| PMI-029 | Create OAuthCallbackHandler.vue (redirect handling) | Frontend | 4h | PMI-023 |
| PMI-030 | Add external reference badges to task selector and time entry UI | Frontend | 8h | PMI-022 |
| PMI-031 | Add Integrations link to Organization Settings navigation | Frontend | 2h | PMI-024 |
| PMI-032 | Add web route for Integrations page | Frontend | 1h | PMI-024 |
| PMI-033 | Create backend endpoint tests for PmIntegrationController | Testing | 8h | PMI-013, PMI-012 |
| PMI-034 | Create backend endpoint tests for IntegrationProjectController | Testing | 4h | PMI-014, PMI-012 |
| PMI-035 | Create backend endpoint tests for PmWebhookController | Testing | 6h | PMI-015, PMI-012 |
| PMI-036 | Create unit tests for IntegrationService | Testing | 8h | PMI-007 |
| PMI-037 | Create unit tests for JiraAdapter | Testing | 6h | PMI-004 |
| PMI-038 | Create unit tests for AsanaAdapter | Testing | 6h | PMI-005 |
| PMI-039 | Create unit tests for TrelloAdapter | Testing | 4h | PMI-006 |
| PMI-040 | Create unit tests for sync and export jobs | Testing | 6h | PMI-017, PMI-018, PMI-019 |
| PMI-041 | Create frontend component tests with Vitest | Testing | 8h | PMI-025, PMI-026, PMI-027, PMI-028 |
| PMI-042 | Create E2E Playwright tests for integration management | Testing | 8h | PMI-031, PMI-032, PMI-025, PMI-026, PMI-027 |
| PMI-043 | Add JSDoc comments to Pinia store and TypeScript types | Docs | 3h | PMI-023, PMI-022 |
| PMI-044 | Create environment variable documentation for provider credentials | Docs | 2h | PMI-004, PMI-005, PMI-006 |

**Total Effort**: 288 hours (~192 SP across 5 sprints)

---

## 7. Dependencies & Integration Points

### 7.1 Internal Dependencies

| Dependency | Description | Impact |
|------------|-------------|--------|
| `Project` Model | Synced external projects create new Project records | Read + Write |
| `Task` Model | Synced external tasks create new Task records | Read + Write |
| `TimeEntry` Model | Time entries on synced tasks are read for export | Read-only |
| `Organization` Model | Route model binding, scoping, settings | Read-only |
| `Member` Model | Used for permission checks and user scoping | Read-only |
| `PermissionStore` | Permission cache in base Controller | Read-only |
| `ColorService` | Assigns colors to auto-created projects | Read-only |
| `BillableRateService` | Compute billable rate for synced time entries | Read-only |
| `CustomAuditable` Trait | Audit logging for connection state changes | Read + Write |
| Laravel Queue | Background sync and export jobs | Infrastructure |
| Laravel Scheduler | Periodic sync and token refresh | Infrastructure |
| Laravel Encryption (`Crypt`) | Token encryption at rest | Infrastructure |

### 7.2 External Dependencies

| Dependency | Version/API | Purpose | Auth Method |
|------------|------------|---------|-------------|
| Jira Cloud REST API | v3 | Project/issue sync, worklog write-back | OAuth 2.0 (3LO) |
| Atlassian Connect | Latest | Webhook registration | JWT |
| Asana REST API | v1.0 | Project/task sync, webhook subscription | OAuth 2.0 |
| Trello REST API | v1 | Board/card sync | API Key + Token |
| `league/oauth2-client` | ^2.x | OAuth 2.0 client library | N/A |
| `mrjoops/oauth2-jira` (or custom) | Latest | Jira OAuth 2.0 provider | N/A |
| `haydenpierce/oauth2-asana` (or custom) | Latest | Asana OAuth 2.0 provider | N/A |
| `guzzlehttp/guzzle` | ^7.x (already in project) | HTTP client for API calls | N/A |

### 7.3 Environment Variables Required

```env
# Jira Integration
JIRA_CLIENT_ID=
JIRA_CLIENT_SECRET=
JIRA_REDIRECT_URI=${APP_URL}/api/v1/organizations/{organization}/integrations/callback

# Asana Integration
ASANA_CLIENT_ID=
ASANA_CLIENT_SECRET=
ASANA_REDIRECT_URI=${APP_URL}/api/v1/organizations/{organization}/integrations/callback

# Trello Integration (no server-side secrets needed; user provides API key)
# Optional: default API key for guided setup
TRELLO_APP_KEY=

# Sync Settings
INTEGRATION_SYNC_ENABLED=true
INTEGRATION_SYNC_QUEUE=integrations
INTEGRATION_WEBHOOK_RATE_LIMIT=100
```

### 7.4 Upstream Features (This Feature Depends On)

| Feature | Dependency Type | Description |
|---------|----------------|-------------|
| Core platform (main) | Hard | Project, Task, TimeEntry models and CRUD operations |
| Permission system | Hard | Role-based access control infrastructure |
| Laravel Queue | Hard | Job dispatching for background sync |

### 7.5 Downstream Features (Depend on This)

| Feature | Dependency Type | Description |
|---------|----------------|-------------|
| Browser Extension (future) | Soft | Embedded timer in PM tool pages uses integration mappings |
| Advanced Reporting (Feature 09) | Soft | Reports can filter/group by integration provider and external references |
| Calendar Enhanced (Feature 05) | Soft | Calendar view can show external task deadlines from synced data |

---

## 8. Risk Assessment & Mitigation

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| Provider API breaking changes | Medium | High | Adapter versioning; each adapter handles API version negotiation; automated regression tests against provider sandbox |
| OAuth token expiry during long sync | Medium | Medium | Proactive token refresh before each sync batch; retry with fresh token on 401 response |
| Rate limiting by provider APIs | High | Medium | Request queuing with exponential backoff; respect `Retry-After` headers; batch API calls where possible |
| Webhook delivery failures (provider-side) | Medium | Medium | Periodic polling as fallback; sync job catches changes webhooks might have missed |
| Data mapping conflicts (name changes) | Medium | Low | External source of truth for names; Solidtime tasks update to match; audit log tracks changes |
| Large project sync (1000+ tasks) | Medium | Medium | Paginated API calls; chunked database inserts; progress tracking in sync log |
| Security: token theft from database | Low | Critical | AES-256 encryption at rest via Laravel Crypt; database access controls; token rotation on refresh |
| Security: webhook spoofing | Low | Critical | HMAC signature validation on all webhook endpoints; reject unsigned requests |
| OAuth provider outage during connection | Low | Medium | Graceful error handling; "Retry" button; clear user messaging |
| Provider deprecates API version | Low | High | Monitor provider changelogs; adapter abstraction allows isolated updates |
| Memory/performance on sync of many projects | Medium | Medium | Process one project at a time; database transactions per project chunk; configurable batch size |
| Trello API key exposure in frontend | Low | High | API key entered via secure form; never stored in frontend state after submission; backend-only storage |

---

## 9. Testing & Validation Requirements

### 9.1 Test Strategy

| Type | Coverage Target | Tools |
|------|-----------------|-------|
| Backend Unit Tests (Service) | All IntegrationService methods | PHPUnit |
| Backend Unit Tests (Adapters) | All adapter methods (mocked HTTP) | PHPUnit + Mockery/Http Fake |
| API Endpoint Tests | All 11+ endpoints | PHPUnit (ApiEndpointTestAbstract) |
| Webhook Tests | Signature validation, event processing | PHPUnit |
| Job Tests | Sync, export, token refresh jobs | PHPUnit |
| Frontend Component Tests | Core components | Vitest + @vue/test-utils |
| E2E Tests | Integration connection and management flows | Playwright |

### 9.2 Key Test Scenarios

**Backend -- IntegrationService:**
- Connect flow creates connection record with encrypted tokens
- Disconnect flow revokes tokens and deactivates mappings
- Sync creates correct project/task mappings
- Sync updates existing mappings on re-run (idempotent)
- Sync deactivates mappings for deleted external entities
- Export pushes time entries to provider API
- Token refresh updates stored tokens
- Token refresh marks connection as "requires_reauth" after 3 failures

**Backend -- Adapters (with HTTP mocking):**
- JiraAdapter: OAuth token exchange returns valid tokens
- JiraAdapter: getProjects returns paginated Jira projects
- JiraAdapter: getTasks returns issues for a project with correct field mapping
- JiraAdapter: createWorklog posts correct worklog data
- AsanaAdapter: OAuth token exchange returns valid tokens
- AsanaAdapter: getProjects returns workspace projects
- AsanaAdapter: getTasks returns tasks with completion status
- TrelloAdapter: validates API key via test call
- TrelloAdapter: getBoards returns user's boards
- TrelloAdapter: getCards returns board cards

**Backend -- Controllers:**
- GET /integrations returns connections for the organization
- POST /integrations/connect returns OAuth redirect URL (Jira, Asana)
- POST /integrations/connect creates connection directly (Trello)
- GET /integrations/callback exchanges code and creates connection
- PUT /integrations/{id} updates sync settings
- DELETE /integrations/{id} disconnects and deactivates
- POST /integrations/{id}/sync queues sync job
- GET /integrations/{id}/external-projects returns provider projects
- PUT /integration-projects/{id}/toggle enables/disables project sync
- Permission checks enforced on all endpoints
- Organization scoping verified (cannot access other org's connections)

**Backend -- Webhooks:**
- Jira webhook with valid signature is processed
- Jira webhook with invalid signature is rejected (400)
- Jira issue_created event creates task mapping
- Jira issue_updated event updates task mapping
- Jira issue_deleted event deactivates task mapping
- Asana webhook handshake returns echoed secret
- Asana event with valid signature is processed
- Asana task change event updates task mapping

**Backend -- Jobs:**
- SyncIntegrationJob processes active connections
- SyncIntegrationJob skips disconnected connections
- SyncIntegrationJob handles API errors gracefully (logs error, does not crash)
- ExportTimeEntriesJob exports pending entries
- ExportTimeEntriesJob marks exported entries to prevent re-export
- ExportTimeEntriesJob retries failed exports up to 3 times
- RefreshIntegrationTokenJob refreshes expiring tokens
- RefreshIntegrationTokenJob marks connection as requires_reauth on failure

**Frontend:**
- IntegrationCard renders correct status badges
- IntegrationDetail shows settings form and sync history
- ProjectSyncPanel displays project list with toggle switches
- SyncHistoryLog renders log entries with correct formatting
- OAuthCallbackHandler processes URL parameters and updates store
- External reference badges display on task selectors
- Connect button triggers correct API call
- Disconnect confirmation dialog works correctly

**E2E:**
- Navigate to Organization Settings > Integrations
- View available integrations (Jira, Asana, Trello cards)
- Connect Trello (API key flow, no OAuth redirect needed for E2E)
- View connected integration detail page
- Toggle project sync on/off
- View sync history
- Disconnect integration
- Verify existing data preserved after disconnect

---

## 10. Monitoring & Observability

### 10.1 Metrics to Track

| Metric | Type | Alert Threshold |
|--------|------|-----------------|
| Integration page load time | Performance | > 2s |
| OAuth redirect latency | Performance | > 3s |
| Sync job duration (P95) | Performance | > 60s |
| Sync job failure rate | Error | > 5% of runs |
| Webhook processing latency (P95) | Performance | > 1s |
| Webhook rejection rate | Security | > 10% (may indicate spoofing) |
| Token refresh failure rate | Error | > 1% |
| Export job failure rate | Error | > 5% |
| Active integration connections count | Business | -- |
| Daily sync operations count | Business | -- |
| Time entries exported daily | Business | -- |
| Provider API error rate (4xx/5xx) | Error | > 5% |

### 10.2 Logging Strategy

```php
// Structured logging for sync operations
Log::info('integration.sync.started', [
    'connection_id' => $connection->id,
    'organization_id' => $connection->organization_id,
    'provider' => $connection->provider,
    'sync_type' => 'incremental',
    'direction' => 'import',
]);

Log::info('integration.sync.completed', [
    'connection_id' => $connection->id,
    'provider' => $connection->provider,
    'projects_synced' => 5,
    'tasks_synced' => 47,
    'duration_ms' => 3200,
]);

Log::warning('integration.token.refresh_failed', [
    'connection_id' => $connection->id,
    'provider' => $connection->provider,
    'failure_count' => $connection->refresh_failure_count,
    'error' => $exception->getMessage(),
]);

Log::error('integration.webhook.invalid_signature', [
    'organization_id' => $organizationId,
    'provider' => 'jira',
    'ip_address' => $request->ip(),
]);
```

### 10.3 Alerting Rules

- Sync job failed for same connection 3 consecutive times -> Alert admin via Solidtime notification
- Token refresh failure count >= 3 -> Alert admin via Solidtime notification + email
- Webhook rejection rate > 10% in 1 hour -> Alert system admin (potential security issue)
- Provider API returning 5xx for > 5 minutes -> Log warning, pause sync for that provider
- Sync job running > 5 minutes -> Log warning (possible performance issue)

---

## 11. Success Metrics & Definition of Done

### 11.1 Success Metrics

| Metric | Target | Measurement |
|--------|--------|-------------|
| Integration adoption | 25% of organizations connect at least one PM tool within 60 days | Database query on `integration_connections` |
| Sync reliability | > 99% of sync jobs complete successfully | `integration_sync_logs` success rate |
| Time-to-connect | < 5 minutes from start to first successful sync | User research / analytics |
| Task mapping accuracy | Zero orphaned or duplicate mappings after sync | Automated data integrity check |
| Export success rate | > 95% of time entries exported on first attempt | Export sync logs |
| Page load performance | Integration settings page loads in < 500ms | Frontend performance monitoring |

### 11.2 Definition of Done

- [ ] All 7 core requirements (REQ-001 through REQ-007) implemented
- [ ] All API endpoints working with proper validation and permissions
- [ ] OAuth 2.0 flows functional for Jira and Asana
- [ ] Trello API key connection flow functional
- [ ] Background sync job runs on schedule and handles errors
- [ ] Webhook endpoints receive and process events from Jira and Asana
- [ ] Time entry export (write-back) functional for all three providers
- [ ] External reference badges visible in task selectors and time entry views
- [ ] Backend endpoint tests passing
- [ ] Adapter unit tests passing (with mocked HTTP)
- [ ] Service unit tests passing
- [ ] Job tests passing
- [ ] Frontend component tests passing
- [ ] E2E tests passing for core integration management flow
- [ ] `composer fix && composer analyse` passes
- [ ] `npm run lint:fix && npm run format` passes
- [ ] OpenAPI spec updated and TS client regenerated
- [ ] Environment variable documentation created
- [ ] Integration settings accessible from Organization Settings navigation
- [ ] Permission model registered (`integrations:view`, `integrations:manage`, `integrations:sync`)
- [ ] Encrypted token storage verified (no plaintext tokens in database)
- [ ] Webhook signature validation tested for both Jira and Asana

---

## 12. Technical Debt & Future Considerations

### 12.1 Known Simplifications

1. **Jira Cloud only**: Jira Server and Data Center are not supported in v1. The adapter architecture allows adding these variants later via a separate adapter or adapter configuration.

2. **Flat task hierarchy**: Jira's Epic > Story > Sub-task hierarchy is flattened. All issues become top-level Solidtime tasks under a project. Parent references are stored in mapping metadata for future use.

3. **Asana time write-back via comments**: Asana has no native time tracking field. Write-back posts a comment. Future: support Asana custom fields for time tracking if the user configures one.

4. **Trello polling-based sync**: Trello webhooks exist but are model-level and more complex to manage. Polling is simpler for v1. Future: add Trello webhook support.

5. **No per-task sync control**: All tasks within a synced project are imported. Users cannot exclude individual tasks. Future: add task-level filtering rules.

6. **No browser extension**: Time tracking is done within Solidtime UI. There is no embedded timer in the PM tool's interface. Future: browser extension using the integration mappings.

7. **Single connection per provider**: An organization can only have one Jira connection. Organizations with multiple Jira instances need separate Solidtime organizations. Future: support multiple connections per provider.

8. **No real-time sync for Trello**: Trello uses polling. Jira and Asana use webhooks for near-real-time sync. Trello updates appear with a delay equal to the configured poll interval.

### 12.2 Future Enhancements

| Enhancement | Priority | Description |
|-------------|----------|-------------|
| Browser extension | P1 | Embed Solidtime timer in Jira, Asana, Trello web interfaces |
| Additional providers | P1 | ClickUp, Monday.com, GitHub Issues, Linear, Notion |
| Jira Server/Data Center | P2 | Support on-premise Jira via REST API + basic auth |
| Task-level sync filtering | P2 | Allow excluding specific tasks/issues from sync |
| Multiple connections per provider | P2 | Support multiple Jira instances per organization |
| Asana custom field time tracking | P2 | Write time data to a custom field instead of comments |
| Trello webhook support | P3 | Replace polling with webhook-based sync for Trello |
| Sync conflict resolution UI | P3 | Manual resolution for conflicting changes |
| Integration health dashboard | P3 | Admin view showing sync health across all connections |
| Automatic project matching | P3 | AI-assisted matching of external projects to existing Solidtime projects |

---

## 13. Appendices

### 13.1 File Structure Summary

```
solidtime/
+-- app/
|   +-- Enums/
|   |   +-- PmProvider.php                           # NEW
|   |   +-- IntegrationStatus.php                             # NEW
|   |   +-- SyncDirection.php                                 # NEW
|   +-- Http/
|   |   +-- Controllers/Api/V1/
|   |   |   +-- PmIntegrationController.php                     # NEW
|   |   |   +-- IntegrationProjectController.php              # NEW
|   |   |   +-- PmWebhookController.php                         # NEW
|   |   +-- Requests/V1/PmIntegration/
|   |   |   +-- PmIntegrationConnectRequest.php                 # NEW
|   |   |   +-- IntegrationUpdateRequest.php                  # NEW
|   |   |   +-- IntegrationProjectToggleRequest.php           # NEW
|   |   |   +-- IntegrationSyncLogIndexRequest.php            # NEW
|   |   |   +-- ExternalProjectListRequest.php                # NEW
|   +-- Jobs/
|   |   +-- SyncIntegrationJob.php                            # NEW
|   |   +-- ExportTimeEntriesJob.php                          # NEW
|   |   +-- RefreshIntegrationTokenJob.php                    # NEW
|   +-- Models/
|   |   +-- IntegrationConnection.php                         # NEW
|   |   +-- IntegrationProject.php                            # NEW
|   |   +-- ExternalTaskMapping.php                           # NEW
|   |   +-- IntegrationSyncLog.php                            # NEW
|   +-- Permissions/
|   |   +-- IntegrationPermissions.php                        # NEW
|   +-- Service/
|   |   +-- IntegrationService.php                            # NEW
|   |   +-- Integration/
|   |   |   +-- IntegrationAdapterInterface.php               # NEW
|   |   |   +-- BaseIntegrationAdapter.php                    # NEW
|   |   |   +-- JiraAdapter.php                               # NEW
|   |   |   +-- AsanaAdapter.php                              # NEW
|   |   |   +-- TrelloAdapter.php                             # NEW
+-- config/
|   +-- integrations.php                                      # NEW (provider credentials, defaults)
+-- database/
|   +-- migrations/
|   |   +-- 2026_03_16_000001_create_integration_connections_table.php   # NEW
|   |   +-- 2026_03_16_000002_create_integration_projects_table.php      # NEW
|   |   +-- 2026_03_16_000003_create_external_task_mappings_table.php    # NEW
|   |   +-- 2026_03_16_000004_create_integration_sync_logs_table.php     # NEW
|   +-- factories/
|   |   +-- IntegrationConnectionFactory.php                  # NEW
|   |   +-- IntegrationProjectFactory.php                     # NEW
|   |   +-- ExternalTaskMappingFactory.php                    # NEW
|   |   +-- IntegrationSyncLogFactory.php                     # NEW
+-- resources/js/
|   +-- Pages/
|   |   +-- Integrations.vue                                  # NEW
|   +-- packages/ui/src/Integration/
|   |   +-- IntegrationCard.vue                               # NEW
|   |   +-- IntegrationDetail.vue                             # NEW
|   |   +-- ProjectSyncPanel.vue                              # NEW
|   |   +-- SyncHistoryLog.vue                                # NEW
|   |   +-- OAuthCallbackHandler.vue                          # NEW
|   |   +-- ExternalReferenceBadge.vue                        # NEW
|   |   +-- __tests__/
|   |   |   +-- IntegrationCard.test.ts                       # NEW
|   |   |   +-- IntegrationDetail.test.ts                     # NEW
|   |   |   +-- ProjectSyncPanel.test.ts                      # NEW
|   |   |   +-- SyncHistoryLog.test.ts                        # NEW
|   +-- utils/
|   |   +-- useIntegration.ts                                 # NEW
|   +-- types/
|   |   +-- integration.d.ts                                  # NEW
+-- routes/
|   +-- api.php                                               # MODIFIED (add integration + webhook routes)
|   +-- web.php                                               # MODIFIED (add Inertia page route)
+-- tests/
|   +-- Unit/
|   |   +-- Endpoint/Api/V1/
|   |   |   +-- IntegrationEndpointTest.php                   # NEW
|   |   |   +-- IntegrationProjectEndpointTest.php            # NEW
|   |   |   +-- WebhookEndpointTest.php                       # NEW
|   |   +-- Service/
|   |   |   +-- IntegrationServiceTest.php                    # NEW
|   |   |   +-- Integration/
|   |   |   |   +-- JiraAdapterTest.php                       # NEW
|   |   |   |   +-- AsanaAdapterTest.php                      # NEW
|   |   |   |   +-- TrelloAdapterTest.php                     # NEW
|   |   +-- Job/
|   |   |   +-- SyncIntegrationJobTest.php                    # NEW
|   |   |   +-- ExportTimeEntriesJobTest.php                  # NEW
|   |   |   +-- RefreshIntegrationTokenJobTest.php            # NEW
+-- e2e/
|   +-- integrations.spec.ts                                  # NEW
```

### 13.2 API Endpoint Summary

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/organizations/{org}/integrations` | List integration connections |
| GET | `/api/v1/organizations/{org}/integrations/{id}` | Get connection detail |
| POST | `/api/v1/organizations/{org}/integrations/connect` | Initiate connection (OAuth redirect or API key) |
| GET | `/api/v1/organizations/{org}/integrations/callback` | OAuth callback handler |
| PUT | `/api/v1/organizations/{org}/integrations/{id}` | Update connection settings |
| DELETE | `/api/v1/organizations/{org}/integrations/{id}` | Disconnect integration |
| POST | `/api/v1/organizations/{org}/integrations/{id}/sync` | Trigger manual sync |
| GET | `/api/v1/organizations/{org}/integrations/{id}/external-projects` | List external projects |
| GET | `/api/v1/organizations/{org}/integration-projects` | List synced project mappings |
| PUT | `/api/v1/organizations/{org}/integration-projects/{id}/toggle` | Enable/disable project sync |
| GET | `/api/v1/organizations/{org}/integration-sync-logs` | Get sync history |
| POST | `/api/v1/webhooks/jira/{org}` | Jira webhook receiver |
| POST | `/api/v1/webhooks/asana/{org}` | Asana webhook receiver |

### 13.3 Permission Matrix

| Permission | Owner | Admin | Manager | Employee |
|------------|:-----:|:-----:|:-------:|:--------:|
| `integrations:view` | Yes | Yes | Yes | No |
| `integrations:manage` | Yes | Yes | No | No |
| `integrations:sync` | Yes | Yes | Yes | No |

### 13.4 Adapter Interface Contract

```php
<?php

declare(strict_types=1);

namespace App\Service\Integration;

use App\Models\IntegrationConnection;

interface IntegrationAdapterInterface
{
    /**
     * Get the provider name (e.g., 'jira', 'asana', 'trello').
     */
    public function getProvider(): string;

    /**
     * Generate the OAuth authorization URL for the provider.
     * Returns null for providers that use API key auth (Trello).
     *
     * @param  array<string, mixed>  $params  Provider-specific params (e.g., site_url for Jira)
     * @return array{redirect_url: string, state: string}|null
     */
    public function getAuthorizationUrl(array $params = []): ?array;

    /**
     * Exchange an authorization code for access/refresh tokens.
     *
     * @return array{access_token: string, refresh_token: string|null, expires_in: int|null, account_id: string|null, account_name: string|null}
     */
    public function exchangeCode(string $code): array;

    /**
     * Validate API key credentials (for Trello-style auth).
     *
     * @param  array<string, string>  $credentials
     * @return array{account_id: string|null, account_name: string|null}
     */
    public function validateCredentials(array $credentials): array;

    /**
     * Refresh the access token using the refresh token.
     *
     * @return array{access_token: string, refresh_token: string|null, expires_in: int|null}
     */
    public function refreshToken(IntegrationConnection $connection): array;

    /**
     * Fetch available projects from the external provider.
     *
     * @return array<array{id: string, key: string|null, name: string, url: string|null}>
     */
    public function getProjects(IntegrationConnection $connection): array;

    /**
     * Fetch tasks for a specific external project.
     *
     * @return array<array{id: string, reference: string, name: string, url: string|null, status: string|null, parent_id: string|null, metadata: array<string, mixed>}>
     */
    public function getTasks(IntegrationConnection $connection, string $externalProjectId): array;

    /**
     * Post a time entry to the external provider.
     *
     * @param  array{external_task_id: string, seconds: int, description: string, started_at: string}  $entry
     * @return array{external_worklog_id: string|null, success: bool, error: string|null}
     */
    public function postTimeEntry(IntegrationConnection $connection, array $entry): array;

    /**
     * Register a webhook for real-time sync (if supported by the provider).
     *
     * @return array{webhook_id: string|null, webhook_secret: string|null}
     */
    public function registerWebhook(IntegrationConnection $connection, string $callbackUrl): array;

    /**
     * Validate an incoming webhook request signature.
     */
    public function validateWebhookSignature(IntegrationConnection $connection, string $payload, string $signature): bool;

    /**
     * Revoke access tokens and clean up (disconnect).
     */
    public function revokeAccess(IntegrationConnection $connection): void;
}
```

### 13.5 Competitive Feature Matrix (Section 11.1 context)

| Platform | Jira | Asana | Trello | OAuth Flow | Webhook Sync | Time Write-Back | Task Search by External ID |
|----------|:----:|:-----:|:------:|:----------:|:------------:|:---------------:|:--------------------------:|
| Everhour | Yes | Yes | Yes | Yes | Yes | Yes (worklogs) | Yes |
| Toggl Track | Yes | Yes | Yes | Yes | Partial | No | Yes |
| Clockify | Yes | Yes | Yes | Yes | No | No | Yes |
| Harvest | Yes | Yes | Yes | Yes | No | No | Partial |
| **Solidtime (this PRD)** | **Yes** | **Yes** | **Yes** | **Yes** | **Yes (Jira, Asana)** | **Yes** | **Yes** |

### 13.6 Glossary

- **OAuth 2.0 (3LO)**: Three-legged OAuth; the authorization code flow where a user grants access to their account on a third-party service
- **Integration Connection**: A record representing an authenticated link between a Solidtime organization and an external PM tool instance
- **Integration Project**: A mapping between an external project (Jira project, Asana project, Trello board) and a Solidtime project
- **External Task Mapping**: A mapping between an external task (Jira issue, Asana task, Trello card) and a Solidtime task
- **Sync Log**: A record of a sync operation's results (imported counts, errors, duration)
- **Write-Back / Export**: Pushing Solidtime time entry data to the external PM tool
- **Adapter**: A provider-specific implementation of the `IntegrationAdapterInterface` that handles API communication
- **Webhook**: An HTTP callback from the external PM tool notifying Solidtime of changes in real-time
- **External Reference**: A human-readable identifier from the PM tool (e.g., Jira issue key "PROJ-123") displayed in Solidtime UI

### 13.7 Change Log

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-02-09 | Tech Planning Agent | Initial draft |
