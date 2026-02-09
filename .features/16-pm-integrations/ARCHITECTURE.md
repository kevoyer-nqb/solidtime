# Feature 16: PM Tool Integrations (Jira, Asana, Trello) -- Technical Architecture

**Date**: 2026-02-09
**Status**: Draft
**Feature Branch**: `feature/pm-integrations` (from `main`)
**Task Prefix**: `PMI-` (per task assignments)

---

## Executive Summary

This document provides the complete technical architecture for the **PM Tool Integrations** feature. The feature adds the ability to connect external project management tools (Jira Cloud, Asana, Trello) to a Solidtime organization, synchronize project/task structures, display external references in the Solidtime UI, and optionally push time entries back to the PM tool.

**Key Architectural Decisions**:
- **4 new database tables** -- `integration_connections`, `integration_projects`, `external_task_mappings`, `integration_sync_logs` (no modifications to existing tables)
- **Adapter pattern** -- `IntegrationAdapterInterface` with provider-specific implementations (`JiraAdapter`, `AsanaAdapter`, `TrelloAdapter`)
- **New `IntegrationService`** orchestrates connection lifecycle, project/task sync, and time entry export
- **New controllers** -- `PmPmIntegrationController` (7 endpoints), `IntegrationProjectController` (3 endpoints), `PmPmWebhookController` (2 endpoints plus 1 callback)
- **3 new background jobs** -- `SyncIntegrationJob`, `ExportTimeEntriesJob`, `RefreshIntegrationTokenJob`
- **New permissions** -- `integrations:view`, `integrations:manage`, `integrations:sync` registered via `IntegrationPermissions`
- **OAuth 2.0 consumer** for Jira and Asana; API key authentication for Trello
- **Webhook receivers** for Jira and Asana real-time sync; polling for Trello
- **Encrypted token storage** using Laravel `Crypt::encryptString()` for all OAuth tokens and API keys
- **Frontend**: Pinia store + 6 Vue components + Inertia.js page + TypeScript types

---

## Table of Contents

1. [Data Model Design](#1-data-model-design)
2. [API Contract](#2-api-contract)
3. [Service Layer](#3-service-layer)
4. [Adapter Layer](#4-adapter-layer)
5. [Controller Layer](#5-controller-layer)
6. [Request Validation](#6-request-validation)
7. [OAuth Flow](#7-oauth-flow)
8. [Webhook Handling](#8-webhook-handling)
9. [Background Jobs](#9-background-jobs)
10. [Frontend Architecture](#10-frontend-architecture)
11. [Permission Matrix](#11-permission-matrix)
12. [Security Strategy](#12-security-strategy)
13. [Performance Strategy](#13-performance-strategy)
14. [Integration Points](#14-integration-points)
15. [File Manifest](#15-file-manifest)

---

## 1. Data Model Design

### 1.1 New Models (4)

This feature introduces 4 new Eloquent models with corresponding database tables. Existing models (`Project`, `Task`, `TimeEntry`) are referenced via foreign keys but their schemas are NOT modified.

### 1.2 IntegrationConnection Model

**File**: `app/Models/IntegrationConnection.php`

Represents an authenticated connection between a Solidtime organization and an external PM tool instance.

```php
// Key columns:
'id'                    // UUID primary key
'organization_id'       // UUID FK to organizations (CASCADE on delete)
'provider'              // string: 'jira', 'asana', 'trello'
'status'                // string: 'connected', 'requires_reauth', 'error', 'disconnected'
'access_token'          // text, encrypted at rest (Laravel Crypt)
'refresh_token'         // text, encrypted at rest (Laravel Crypt)
'token_expires_at'      // timestamp, nullable
'external_account_id'   // string: Jira cloud ID, Asana workspace GID, Trello member ID
'external_account_name' // string: human-readable account/workspace name
'sync_direction'        // string: 'import', 'export', 'bidirectional'
'sync_frequency_minutes'// int: 5, 15, 30, 60
'last_sync_at'          // timestamp, nullable
'last_sync_status'      // string: 'success', 'partial', 'error'
'last_sync_error'       // text, nullable
'webhook_secret'        // string, encrypted at rest
'settings'              // jsonb: provider-specific config (site_url, cloud_id, etc.)
'connected_by'          // UUID FK to users
'refresh_failure_count' // int, default 0
'created_at'            // timestamp
'updated_at'            // timestamp
```

**Relationships**:
- `belongsTo` Organization
- `belongsTo` User (connected_by)
- `hasMany` IntegrationProject
- `hasMany` IntegrationSyncLog

**Unique constraint**: `(organization_id, provider)` -- one connection per provider per organization.

**Encryption accessors**: Custom `get`/`set` mutators on `access_token`, `refresh_token`, and `webhook_secret` that call `Crypt::encryptString()` / `Crypt::decryptString()`. These fields are never exposed in API responses.

```php
protected function accessToken(): Attribute
{
    return Attribute::make(
        get: fn (?string $value) => $value !== null ? Crypt::decryptString($value) : null,
        set: fn (?string $value) => $value !== null ? Crypt::encryptString($value) : null,
    );
}
```

**Migration file**: `database/migrations/2026_03_16_000001_create_integration_connections_table.php`

```sql
CREATE TABLE integration_connections (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    provider VARCHAR(50) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'connected',
    access_token TEXT,
    refresh_token TEXT,
    token_expires_at TIMESTAMP NULL,
    external_account_id VARCHAR(255) NULL,
    external_account_name VARCHAR(255) NULL,
    sync_direction VARCHAR(20) NOT NULL DEFAULT 'import',
    sync_frequency_minutes INT NOT NULL DEFAULT 15,
    last_sync_at TIMESTAMP NULL,
    last_sync_status VARCHAR(50) NULL,
    last_sync_error TEXT NULL,
    webhook_secret VARCHAR(255) NULL,
    settings JSONB NULL,
    connected_by UUID NOT NULL REFERENCES users(id),
    refresh_failure_count INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (organization_id, provider)
);

CREATE INDEX idx_integration_connections_org ON integration_connections(organization_id);
CREATE INDEX idx_integration_connections_status ON integration_connections(organization_id, status);
```

### 1.3 IntegrationProject Model

**File**: `app/Models/IntegrationProject.php`

Maps an external project (Jira project, Asana project, Trello board) to a Solidtime project. Tracks whether the admin has opted-in to sync this particular project.

```php
// Key columns:
'id'                        // UUID primary key
'integration_connection_id' // UUID FK to integration_connections (CASCADE on delete)
'organization_id'           // UUID FK to organizations (CASCADE on delete)
'project_id'                // UUID FK to projects (SET NULL on delete), nullable
'external_project_id'       // string: Jira project ID, Asana project GID, Trello board ID
'external_project_key'      // string, nullable: Jira project key "PROJ"
'external_project_name'     // string
'external_project_url'      // string, nullable
'is_enabled'                // boolean: admin must opt-in, default false
'is_active'                 // boolean: false if external project deleted/archived, default true
'metadata'                  // jsonb, nullable: provider-specific data
'last_synced_at'            // timestamp, nullable
'created_at'                // timestamp
'updated_at'                // timestamp
```

**Relationships**:
- `belongsTo` IntegrationConnection
- `belongsTo` Organization
- `belongsTo` Project (nullable -- created when sync first runs for an enabled project)
- `hasMany` ExternalTaskMapping

**Unique constraint**: `(integration_connection_id, external_project_id)`

**Migration file**: `database/migrations/2026_03_16_000002_create_integration_projects_table.php`

```sql
CREATE TABLE integration_projects (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    integration_connection_id UUID NOT NULL REFERENCES integration_connections(id) ON DELETE CASCADE,
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    project_id UUID NULL REFERENCES projects(id) ON DELETE SET NULL,
    external_project_id VARCHAR(255) NOT NULL,
    external_project_key VARCHAR(100) NULL,
    external_project_name VARCHAR(255) NOT NULL,
    external_project_url VARCHAR(500) NULL,
    is_enabled BOOLEAN NOT NULL DEFAULT false,
    is_active BOOLEAN NOT NULL DEFAULT true,
    metadata JSONB NULL,
    last_synced_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (integration_connection_id, external_project_id)
);

CREATE INDEX idx_integration_projects_connection ON integration_projects(integration_connection_id);
CREATE INDEX idx_integration_projects_project ON integration_projects(project_id);
CREATE INDEX idx_integration_projects_enabled ON integration_projects(integration_connection_id, is_enabled);
```

### 1.4 ExternalTaskMapping Model

**File**: `app/Models/ExternalTaskMapping.php`

Maps an external task (Jira issue, Asana task, Trello card) to a Solidtime task. Stores the human-readable external reference (e.g., "PROJ-123") for display in the Solidtime UI.

```php
// Key columns:
'id'                      // UUID primary key
'integration_project_id'  // UUID FK to integration_projects (CASCADE on delete)
'organization_id'         // UUID FK to organizations (CASCADE on delete)
'task_id'                 // UUID FK to tasks (SET NULL on delete), nullable
'external_task_id'        // string: Jira issue ID, Asana task GID, Trello card ID
'external_reference'      // string: human-readable "PROJ-123", card short link
'external_task_name'      // string
'external_task_url'       // string, nullable
'external_status'         // string, nullable: "In Progress", "Done"
'external_parent_id'      // string, nullable: for Jira epic/parent hierarchy
'is_active'               // boolean: false if external task deleted, default true
'metadata'                // jsonb, nullable: labels, assignee, priority, story points
'last_synced_at'          // timestamp, nullable
'created_at'              // timestamp
'updated_at'              // timestamp
```

**Relationships**:
- `belongsTo` IntegrationProject
- `belongsTo` Organization
- `belongsTo` Task (nullable -- created when sync first runs)

**Unique constraint**: `(integration_project_id, external_task_id)`

**Migration file**: `database/migrations/2026_03_16_000003_create_external_task_mappings_table.php`

```sql
CREATE TABLE external_task_mappings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    integration_project_id UUID NOT NULL REFERENCES integration_projects(id) ON DELETE CASCADE,
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    task_id UUID NULL REFERENCES tasks(id) ON DELETE SET NULL,
    external_task_id VARCHAR(255) NOT NULL,
    external_reference VARCHAR(100) NOT NULL,
    external_task_name VARCHAR(500) NOT NULL,
    external_task_url VARCHAR(500) NULL,
    external_status VARCHAR(100) NULL,
    external_parent_id VARCHAR(255) NULL,
    is_active BOOLEAN NOT NULL DEFAULT true,
    metadata JSONB NULL,
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

### 1.5 IntegrationSyncLog Model

**File**: `app/Models/IntegrationSyncLog.php`

Records the outcome of each sync operation for audit and troubleshooting.

```php
// Key columns:
'id'                        // UUID primary key
'integration_connection_id' // UUID FK to integration_connections (CASCADE on delete)
'organization_id'           // UUID FK to organizations (CASCADE on delete)
'sync_type'                 // string: 'full', 'incremental', 'webhook', 'export'
'direction'                 // string: 'import', 'export'
'status'                    // string: 'started', 'completed', 'partial', 'failed'
'projects_synced'           // int, default 0
'tasks_synced'              // int, default 0
'time_entries_exported'     // int, default 0
'errors_count'              // int, default 0
'error_details'             // jsonb, nullable: array of error messages
'started_at'                // timestamp
'completed_at'              // timestamp, nullable
'duration_ms'               // int, nullable
'created_at'                // timestamp
```

**Relationships**:
- `belongsTo` IntegrationConnection
- `belongsTo` Organization

**Migration file**: `database/migrations/2026_03_16_000004_create_integration_sync_logs_table.php`

```sql
CREATE TABLE integration_sync_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    integration_connection_id UUID NOT NULL REFERENCES integration_connections(id) ON DELETE CASCADE,
    organization_id UUID NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    sync_type VARCHAR(50) NOT NULL,
    direction VARCHAR(20) NOT NULL,
    status VARCHAR(50) NOT NULL,
    projects_synced INT NOT NULL DEFAULT 0,
    tasks_synced INT NOT NULL DEFAULT 0,
    time_entries_exported INT NOT NULL DEFAULT 0,
    errors_count INT NOT NULL DEFAULT 0,
    error_details JSONB NULL,
    started_at TIMESTAMP NOT NULL,
    completed_at TIMESTAMP NULL,
    duration_ms INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_sync_logs_connection ON integration_sync_logs(integration_connection_id);
CREATE INDEX idx_sync_logs_org_date ON integration_sync_logs(organization_id, started_at);
```

### 1.6 Existing Model Usage (Read/Write, No Schema Changes)

**`Project`** (read + write via sync):
- Sync creates new `Project` records for enabled external projects
- Fields written: `name`, `color` (via `ColorService`), `organization_id`, `is_billable` (default false), `is_public` (default true for synced projects)
- Existing projects are never modified by sync (link is via `integration_projects.project_id`)

**`Task`** (read + write via sync):
- Sync creates new `Task` records for external tasks
- Fields written: `name`, `project_id`, `organization_id`, `done_at` (from external status), `estimated_time` (from external if available)
- Existing tasks are updated on re-sync: `name` and `done_at` may change

**`TimeEntry`** (read-only for export):
- Export job reads time entries on mapped tasks (`task_id` matches `external_task_mappings.task_id`)
- No writes to `TimeEntry` -- export tracking is in `integration_sync_logs`

### 1.7 Enums

Three new PHP enums for type safety:

**`app/Enums/PmProvider.php`**:
```php
enum PmProvider: string
{
    case Jira = 'jira';
    case Asana = 'asana';
    case Trello = 'trello';
}
```

**`app/Enums/IntegrationStatus.php`**:
```php
enum IntegrationStatus: string
{
    case Connected = 'connected';
    case RequiresReauth = 'requires_reauth';
    case Error = 'error';
    case Disconnected = 'disconnected';
}
```

**`app/Enums/SyncDirection.php`**:
```php
enum SyncDirection: string
{
    case Import = 'import';
    case Export = 'export';
    case Bidirectional = 'bidirectional';
}
```

### 1.8 Entity Relationship Diagram

```
organizations
    |
    +--< integration_connections (organization_id FK)
    |       |
    |       +--< integration_projects (integration_connection_id FK)
    |       |       |
    |       |       +--< external_task_mappings (integration_project_id FK)
    |       |       |       |
    |       |       |       +---> tasks (task_id FK, nullable)
    |       |       |
    |       |       +---> projects (project_id FK, nullable)
    |       |
    |       +--< integration_sync_logs (integration_connection_id FK)
    |       |
    |       +---> users (connected_by FK)
    |
    +--< projects (existing)
    |       |
    |       +--< tasks (existing)
    |               |
    |               +--< time_entries (existing, read for export)
```

---

## 2. API Contract

### 2.1 Route Registration

**File**: `routes/api.php` (inside existing `auth:api` + `verified` middleware group)

```php
// Integration management routes (authenticated)
Route::name('integrations.')->prefix('/organizations/{organization}')->group(static function (): void {
    Route::get('/integrations', [PmIntegrationController::class, 'index'])->name('index');
    Route::get('/integrations/{integration}', [PmIntegrationController::class, 'show'])->name('show');
    Route::post('/integrations/connect', [PmIntegrationController::class, 'connect'])
        ->name('connect')
        ->middleware('check-organization-blocked');
    Route::get('/integrations/callback', [PmIntegrationController::class, 'callback'])->name('callback');
    Route::put('/integrations/{integration}', [PmIntegrationController::class, 'update'])
        ->name('update')
        ->middleware('check-organization-blocked');
    Route::delete('/integrations/{integration}', [PmIntegrationController::class, 'destroy'])->name('destroy');
    Route::post('/integrations/{integration}/sync', [PmIntegrationController::class, 'syncNow'])
        ->name('sync')
        ->middleware('check-organization-blocked');
    Route::get('/integrations/{integration}/external-projects', [PmIntegrationController::class, 'externalProjects'])
        ->name('external-projects');
});

Route::name('integration-projects.')->prefix('/organizations/{organization}')->group(static function (): void {
    Route::get('/integration-projects', [IntegrationProjectController::class, 'index'])->name('index');
    Route::put('/integration-projects/{integrationProject}/toggle', [IntegrationProjectController::class, 'toggle'])
        ->name('toggle')
        ->middleware('check-organization-blocked');
});

Route::name('integration-sync-logs.')->prefix('/organizations/{organization}')->group(static function (): void {
    Route::get('/integration-sync-logs', [PmIntegrationController::class, 'syncLogs'])->name('index');
});
```

**Webhook routes** (outside `auth:api` middleware -- validated via signatures):

```php
// Webhook routes (no auth middleware -- validated via provider-specific signatures)
Route::prefix('v1/webhooks')->name('v1.webhooks.')->group(static function (): void {
    Route::post('/jira/{organization}', [PmWebhookController::class, 'jira'])->name('jira');
    Route::post('/asana/{organization}', [PmWebhookController::class, 'asana'])->name('asana');
});
```

Route names resolve to:
- `api.v1.integrations.index`
- `api.v1.integrations.show`
- `api.v1.integrations.connect`
- `api.v1.integrations.callback`
- `api.v1.integrations.update`
- `api.v1.integrations.destroy`
- `api.v1.integrations.sync`
- `api.v1.integrations.external-projects`
- `api.v1.integration-projects.index`
- `api.v1.integration-projects.toggle`
- `api.v1.integration-sync-logs.index`
- `api.v1.webhooks.jira`
- `api.v1.webhooks.asana`

Write endpoints (`connect`, `update`, `sync`, `toggle`) use `check-organization-blocked` middleware. Webhook endpoints bypass standard auth and use provider-specific signature validation.

### 2.2 Endpoint Signatures

| Method | Path | Controller Method | Request Class | Permission |
|--------|------|-------------------|---------------|------------|
| GET | `/integrations` | `index()` | -- | `integrations:view` |
| GET | `/integrations/{integration}` | `show()` | -- | `integrations:view` |
| POST | `/integrations/connect` | `connect()` | `PmIntegrationConnectRequest` | `integrations:manage` |
| GET | `/integrations/callback` | `callback()` | -- | `integrations:manage` |
| PUT | `/integrations/{integration}` | `update()` | `IntegrationUpdateRequest` | `integrations:manage` |
| DELETE | `/integrations/{integration}` | `destroy()` | -- | `integrations:manage` |
| POST | `/integrations/{integration}/sync` | `syncNow()` | -- | `integrations:sync` |
| GET | `/integrations/{integration}/external-projects` | `externalProjects()` | -- | `integrations:manage` |
| GET | `/integration-projects` | `index()` | -- | `integrations:view` |
| PUT | `/integration-projects/{integrationProject}/toggle` | `toggle()` | `IntegrationProjectToggleRequest` | `integrations:manage` |
| GET | `/integration-sync-logs` | `syncLogs()` | `IntegrationSyncLogIndexRequest` | `integrations:view` |
| POST | `/webhooks/jira/{organization}` | `jira()` | -- | None (signature validated) |
| POST | `/webhooks/asana/{organization}` | `asana()` | -- | None (signature validated) |

### 2.3 Response Shapes

**GET /integrations** (list connections):
```json
{
  "data": [
    {
      "id": "uuid",
      "provider": "jira",
      "status": "connected",
      "external_account_name": "My Company Jira",
      "sync_direction": "import",
      "sync_frequency_minutes": 15,
      "last_sync_at": "2026-02-09T10:00:00Z",
      "last_sync_status": "success",
      "last_sync_error": null,
      "connected_by": "uuid",
      "created_at": "2026-02-09T08:00:00Z",
      "updated_at": "2026-02-09T10:00:00Z"
    }
  ]
}
```

**GET /integrations/{integration}** (connection detail):
```json
{
  "data": {
    "id": "uuid",
    "provider": "jira",
    "status": "connected",
    "external_account_id": "abc123",
    "external_account_name": "My Company Jira",
    "sync_direction": "import",
    "sync_frequency_minutes": 15,
    "last_sync_at": "2026-02-09T10:00:00Z",
    "last_sync_status": "success",
    "last_sync_error": null,
    "settings": { "site_url": "mycompany.atlassian.net" },
    "connected_by": "uuid",
    "created_at": "2026-02-09T08:00:00Z",
    "updated_at": "2026-02-09T10:00:00Z",
    "projects": [],
    "recent_sync_logs": []
  }
}
```

**POST /integrations/connect** (OAuth providers return redirect URL):
```json
{
  "data": {
    "redirect_url": "https://auth.atlassian.com/authorize?...",
    "state": "random40charstring"
  }
}
```

**POST /integrations/connect** (Trello returns created connection):
```json
{
  "data": {
    "id": "uuid",
    "provider": "trello",
    "status": "connected",
    "external_account_name": "My Trello Account"
  }
}
```

**POST /integrations/{integration}/sync** (queued):
```json
{
  "data": {
    "message": "Sync job queued",
    "sync_log_id": "uuid"
  }
}
```

**GET /integrations/{integration}/external-projects** (from provider API):
```json
{
  "data": [
    {
      "external_project_id": "10001",
      "external_project_key": "PROJ",
      "external_project_name": "Project Alpha",
      "external_project_url": "https://mycompany.atlassian.net/browse/PROJ",
      "is_already_synced": false
    }
  ]
}
```

**POST /webhooks/jira/{organization}**:
```json
{ "received": true }
```

---

## 3. Service Layer

### 3.1 IntegrationService

**File**: `app/Service/IntegrationService.php`

Central orchestration service. Stateless. Injected into controllers and jobs via constructor type-hints.

**Public methods**:

#### `initiateConnection(Organization $organization, User $user, string $provider, array $params): array`

1. Validate no existing active connection for this provider in the organization
2. Resolve the adapter via `getAdapter($provider)`
3. For OAuth providers (Jira, Asana): call `$adapter->getAuthorizationUrl($params)`, store state in session, return `{redirect_url, state}`
4. For API key providers (Trello): call `$adapter->validateCredentials($params)`, create `IntegrationConnection` record, return the connection

#### `completeOAuthConnection(Organization $organization, User $user, string $code, string $state): IntegrationConnection`

1. Validate state parameter against session
2. Determine provider from session state metadata
3. Resolve adapter, call `$adapter->exchangeCode($code)`
4. Create `IntegrationConnection` with encrypted tokens
5. Return the new connection

#### `disconnect(IntegrationConnection $connection): void`

1. Resolve adapter, call `$adapter->revokeAccess($connection)` (best-effort)
2. Set `$connection->status = 'disconnected'`
3. Deactivate all related `IntegrationProject` records (`is_enabled = false`)
4. Deactivate all related `ExternalTaskMapping` records (`is_active = false`)
5. Save connection

#### `updateSettings(IntegrationConnection $connection, array $settings): IntegrationConnection`

1. Update `sync_direction` and/or `sync_frequency_minutes`
2. Save and return

#### `syncProjects(IntegrationConnection $connection): IntegrationSyncLog`

1. Create sync log entry (status = 'started')
2. Resolve adapter, call `$adapter->getProjects($connection)`
3. For each external project:
   - Find or create `IntegrationProject` by `(connection_id, external_project_id)`
   - Update `external_project_name`, `external_project_key`, `external_project_url`
   - If `is_enabled` and `project_id` is null, create a new `Project` via `ColorService` for color assignment
4. Mark external projects not in the API response as `is_active = false`
5. Update sync log (status = 'completed', counts)
6. Return sync log

#### `syncTasks(IntegrationConnection $connection, IntegrationProject $integrationProject): int`

1. Resolve adapter, call `$adapter->getTasks($connection, $integrationProject->external_project_id)`
2. For each external task:
   - Find or create `ExternalTaskMapping` by `(integration_project_id, external_task_id)`
   - Update `external_reference`, `external_task_name`, `external_task_url`, `external_status`, `metadata`
   - If `task_id` is null, create a new `Task` record under the linked project
   - If external status indicates completion, set `done_at` on the Solidtime task
3. Mark external tasks not in the API response as `is_active = false`
4. Return count of tasks synced

#### `exportTimeEntries(IntegrationConnection $connection): int`

1. Find all time entries on mapped tasks for this connection where `end IS NOT NULL`
2. Filter to entries not yet exported (using sync log tracking or a `last_exported_at` marker)
3. Resolve adapter
4. For each entry: call `$adapter->postTimeEntry($connection, $entryData)`
5. Track success/failure counts
6. Return count exported

#### `refreshToken(IntegrationConnection $connection): void`

1. Resolve adapter, call `$adapter->refreshToken($connection)`
2. Update `access_token`, `refresh_token`, `token_expires_at` on the connection
3. Reset `refresh_failure_count` to 0
4. On failure: increment `refresh_failure_count`; if >= 3, set status to `requires_reauth`

#### `getAdapter(string $provider): IntegrationAdapterInterface`

Factory method. Returns the appropriate adapter instance based on provider string.

```php
public function getAdapter(string $provider): IntegrationAdapterInterface
{
    return match ($provider) {
        'jira' => app(JiraAdapter::class),
        'asana' => app(AsanaAdapter::class),
        'trello' => app(TrelloAdapter::class),
        default => throw new \InvalidArgumentException("Unknown provider: {$provider}"),
    };
}
```

#### `processWebhookEvent(IntegrationConnection $connection, string $eventType, array $payload): void`

1. Parse event type to determine action (create/update/delete) and entity (project/task)
2. Route to appropriate handler:
   - Task created: find or create `ExternalTaskMapping`, create `Task`
   - Task updated: update mapping and Solidtime task
   - Task deleted: mark mapping `is_active = false`
   - Project updated: update `IntegrationProject` fields
   - Project deleted/archived: mark `IntegrationProject` `is_active = false`
3. Create sync log entry of type 'webhook'

---

## 4. Adapter Layer

### 4.1 IntegrationAdapterInterface

**File**: `app/Service/Integration/IntegrationAdapterInterface.php`

Defines the contract that all provider adapters must implement. See PRD Appendix 13.4 for the full interface definition (11 methods).

Key methods:
- `getProvider(): string`
- `getAuthorizationUrl(array $params): ?array`
- `exchangeCode(string $code): array`
- `validateCredentials(array $credentials): array`
- `refreshToken(IntegrationConnection $connection): array`
- `getProjects(IntegrationConnection $connection): array`
- `getTasks(IntegrationConnection $connection, string $externalProjectId): array`
- `postTimeEntry(IntegrationConnection $connection, array $entry): array`
- `registerWebhook(IntegrationConnection $connection, string $callbackUrl): array`
- `validateWebhookSignature(IntegrationConnection $connection, string $payload, string $signature): bool`
- `revokeAccess(IntegrationConnection $connection): void`

### 4.2 BaseIntegrationAdapter

**File**: `app/Service/Integration/BaseIntegrationAdapter.php`

Abstract class implementing shared functionality:

- HTTP client setup via `GuzzleHttp\Client`
- Rate limiting helper: `throttle()` with exponential backoff
- Error handling wrapper: `makeApiCall()` that catches `GuzzleException`, logs errors, and returns structured error responses
- Token refresh check: `ensureValidToken()` that checks `token_expires_at` and calls `refreshToken()` proactively
- Pagination helper: `fetchAllPages()` for iterating paginated API responses

```php
abstract class BaseIntegrationAdapter implements IntegrationAdapterInterface
{
    protected Client $httpClient;

    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
        ]);
    }

    protected function makeApiCall(string $method, string $url, array $options = []): array
    {
        try {
            $response = $this->httpClient->request($method, $url, $options);
            return json_decode($response->getBody()->getContents(), true);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 429) {
                $retryAfter = (int) $e->getResponse()->getHeaderLine('Retry-After') ?: 60;
                throw new RateLimitException($retryAfter);
            }
            throw $e;
        }
    }

    protected function ensureValidToken(IntegrationConnection $connection): IntegrationConnection
    {
        if ($connection->token_expires_at !== null
            && $connection->token_expires_at->subMinutes(5)->isPast()
        ) {
            $tokens = $this->refreshToken($connection);
            $connection->access_token = $tokens['access_token'];
            if (isset($tokens['refresh_token'])) {
                $connection->refresh_token = $tokens['refresh_token'];
            }
            if (isset($tokens['expires_in'])) {
                $connection->token_expires_at = now()->addSeconds($tokens['expires_in']);
            }
            $connection->refresh_failure_count = 0;
            $connection->save();
        }
        return $connection;
    }
}
```

### 4.3 JiraAdapter

**File**: `app/Service/Integration/JiraAdapter.php`

Implements Jira Cloud REST API v3 integration via OAuth 2.0 (3LO).

**OAuth configuration** (from `config/integrations.php`):
- Client ID: `JIRA_CLIENT_ID`
- Client Secret: `JIRA_CLIENT_SECRET`
- Redirect URI: Dynamic per organization
- Scopes: `read:jira-work`, `write:jira-work`, `read:jira-user`, `offline_access`
- Authorization URL: `https://auth.atlassian.com/authorize`
- Token URL: `https://auth.atlassian.com/oauth/token`
- API base: `https://api.atlassian.com/ex/jira/{cloudId}/rest/api/3`

**Key method implementations**:

- `getAuthorizationUrl()`: Builds Atlassian OAuth URL with scopes and state parameter; includes `site_url` in state metadata for cloud ID resolution
- `exchangeCode()`: Posts to Atlassian token endpoint; fetches accessible resources to get cloud ID
- `getProjects()`: `GET /rest/api/3/project/search` with pagination (max 50 per page)
- `getTasks()`: `GET /rest/api/3/search` with JQL `project = {key} ORDER BY updated DESC`; maps issue fields to task structure
- `postTimeEntry()`: `POST /rest/api/3/issue/{issueId}/worklog` with `timeSpentSeconds`, `comment`, `started`
- `registerWebhook()`: `POST /rest/api/3/webhook` to register event listeners
- `validateWebhookSignature()`: HMAC-SHA256 validation using stored webhook secret

**Jira-specific field mapping**:
```
Jira Project.id        -> IntegrationProject.external_project_id
Jira Project.key       -> IntegrationProject.external_project_key
Jira Project.name      -> IntegrationProject.external_project_name
Jira Issue.id          -> ExternalTaskMapping.external_task_id
Jira Issue.key         -> ExternalTaskMapping.external_reference ("PROJ-123")
Jira Issue.summary     -> ExternalTaskMapping.external_task_name
Jira Issue.status.name -> ExternalTaskMapping.external_status
Jira Issue.parent.key  -> ExternalTaskMapping.external_parent_id
Jira Issue.fields.*    -> ExternalTaskMapping.metadata (story points, labels, priority)
```

### 4.4 AsanaAdapter

**File**: `app/Service/Integration/AsanaAdapter.php`

Implements Asana REST API v1.0 integration via OAuth 2.0.

**OAuth configuration**:
- Client ID: `ASANA_CLIENT_ID`
- Client Secret: `ASANA_CLIENT_SECRET`
- Authorization URL: `https://app.asana.com/-/oauth_authorize`
- Token URL: `https://app.asana.com/-/oauth_token`
- API base: `https://app.asana.com/api/1.0`
- Rate limit: 150 requests/minute

**Key method implementations**:

- `getProjects()`: `GET /projects?workspace={gid}` with pagination via `offset` token
- `getTasks()`: `GET /tasks?project={gid}&opt_fields=name,completed,completed_at,assignee,parent,custom_fields` with pagination
- `postTimeEntry()`: `POST /tasks/{gid}/stories` with comment body: "Tracked X hours via Solidtime on {date}: {description}"
- `registerWebhook()`: `POST /webhooks` to subscribe to project resource events
- `validateWebhookSignature()`: HMAC-SHA256 using `X-Hook-Secret` header value

**Asana-specific field mapping**:
```
Asana Project.gid      -> IntegrationProject.external_project_id
Asana Project.name     -> IntegrationProject.external_project_name
Asana Task.gid         -> ExternalTaskMapping.external_task_id
Asana Task.name        -> ExternalTaskMapping.external_reference (task name)
Asana Task.name        -> ExternalTaskMapping.external_task_name
Asana Task.completed   -> maps to done_at on Solidtime Task
Asana Section.name     -> ExternalTaskMapping.metadata.section
```

### 4.5 TrelloAdapter

**File**: `app/Service/Integration/TrelloAdapter.php`

Implements Trello REST API v1 integration via API key + token authentication.

**Configuration**:
- API Key + Token provided by admin (stored encrypted)
- API base: `https://api.trello.com/1`
- Rate limit: 300 requests per 10 seconds per token
- No OAuth flow -- direct credential entry

**Key method implementations**:

- `getAuthorizationUrl()`: Returns `null` (Trello uses API key, not OAuth redirect)
- `validateCredentials()`: `GET /members/me?key={key}&token={token}` to verify credentials
- `getProjects()`: `GET /members/me/boards?key={key}&token={token}&filter=open`
- `getTasks()`: `GET /boards/{id}/cards?key={key}&token={token}&filter=open`
- `postTimeEntry()`: `POST /cards/{id}/actions/comments` with time tracking comment
- `registerWebhook()`: Returns empty (polling-based in v1)
- `validateWebhookSignature()`: Returns false (no webhooks in v1)
- `revokeAccess()`: Trello tokens cannot be programmatically revoked; admin must manually revoke from Trello settings

**Trello-specific field mapping**:
```
Trello Board.id        -> IntegrationProject.external_project_id
Trello Board.name      -> IntegrationProject.external_project_name
Trello Board.url       -> IntegrationProject.external_project_url
Trello Card.id         -> ExternalTaskMapping.external_task_id
Trello Card.shortLink  -> ExternalTaskMapping.external_reference
Trello Card.name       -> ExternalTaskMapping.external_task_name
Trello Card.shortUrl   -> ExternalTaskMapping.external_task_url
Trello List.name       -> ExternalTaskMapping.metadata.list (workflow state)
Trello Card.closed     -> maps to is_active = false
```

---

## 5. Controller Layer

### 5.1 PmIntegrationController

**File**: `app/Http/Controllers/Api/V1/PmIntegrationController.php`

Extends `App\Http\Controllers\Api\V1\Controller` (which provides `$this->checkPermission()`, `$this->user()`, `$this->member()`).

**Dependency injection**: `IntegrationService` injected via constructor.

```php
class PmIntegrationController extends Controller
{
    public function __construct(
        private readonly IntegrationService $integrationService
    ) {}
}
```

**Methods**:

| Method | Permission Check | Description |
|--------|-----------------|-------------|
| `index(Organization $organization)` | `integrations:view` | List all connections for org |
| `show(Organization $organization, IntegrationConnection $integration)` | `integrations:view` | Get connection detail with projects and recent logs |
| `connect(Organization $organization, PmIntegrationConnectRequest $request)` | `integrations:manage` | Initiate connection (OAuth redirect or API key validation) |
| `callback(Organization $organization, Request $request)` | `integrations:manage` | Handle OAuth callback, exchange code for tokens |
| `update(Organization $organization, IntegrationConnection $integration, IntegrationUpdateRequest $request)` | `integrations:manage` | Update sync direction and frequency |
| `destroy(Organization $organization, IntegrationConnection $integration)` | `integrations:manage` | Disconnect integration |
| `syncNow(Organization $organization, IntegrationConnection $integration)` | `integrations:sync` | Queue immediate sync job |
| `externalProjects(Organization $organization, IntegrationConnection $integration)` | `integrations:manage` | Fetch available projects from external API |
| `syncLogs(Organization $organization, IntegrationSyncLogIndexRequest $request)` | `integrations:view` | Get sync history |

**Organization scoping**: All queries are scoped to the current organization via route model binding. The `IntegrationConnection` route model binding must verify `$integration->organization_id === $organization->id`.

### 5.2 IntegrationProjectController

**File**: `app/Http/Controllers/Api/V1/IntegrationProjectController.php`

```php
class IntegrationProjectController extends Controller
{
    public function __construct(
        private readonly IntegrationService $integrationService
    ) {}
}
```

| Method | Permission Check | Description |
|--------|-----------------|-------------|
| `index(Organization $organization)` | `integrations:view` | List synced project mappings, optional filter by `integration_id` |
| `toggle(Organization $organization, IntegrationProject $integrationProject, IntegrationProjectToggleRequest $request)` | `integrations:manage` | Enable/disable sync for a project |

### 5.3 PmWebhookController

**File**: `app/Http/Controllers/Api/V1/PmWebhookController.php`

Does NOT extend the standard API controller (no auth middleware). Instead, performs its own signature validation.

```php
class PmWebhookController extends \App\Http\Controllers\Controller
{
    public function __construct(
        private readonly IntegrationService $integrationService
    ) {}
}
```

| Method | Auth | Description |
|--------|------|-------------|
| `jira(Organization $organization, Request $request)` | HMAC signature | Process Jira webhook event |
| `asana(Organization $organization, Request $request)` | X-Hook-Signature / X-Hook-Secret | Process Asana webhook event or handshake |

**Webhook processing flow**:
1. Look up active connection for the organization and provider
2. If no connection found, return 404
3. Validate webhook signature using adapter
4. Parse event payload
5. Dispatch to `IntegrationService::processWebhookEvent()`
6. Return 200 `{ "received": true }`

**Asana handshake**: On initial webhook subscription, Asana sends a request with `X-Hook-Secret` header. The controller must echo this header in the response to complete registration.

---

## 6. Request Validation

### 6.1 Request Classes

All extend `App\Http\Requests\V1\BaseFormRequest`.

**PmIntegrationConnectRequest**:
```php
public function rules(): array
{
    return [
        'provider' => ['required', 'string', Rule::in(['jira', 'asana', 'trello'])],
        'api_key' => ['required_if:provider,trello', 'string', 'max:255'],
        'api_token' => ['required_if:provider,trello', 'string', 'max:255'],
        'site_url' => ['required_if:provider,jira', 'string', 'max:255'],
    ];
}
```

**IntegrationUpdateRequest**:
```php
public function rules(): array
{
    return [
        'sync_direction' => ['sometimes', 'string', Rule::in(['import', 'export', 'bidirectional'])],
        'sync_frequency_minutes' => ['sometimes', 'integer', Rule::in([5, 15, 30, 60])],
    ];
}
```

**IntegrationProjectToggleRequest**:
```php
public function rules(): array
{
    return [
        'is_enabled' => ['required', 'boolean'],
    ];
}
```

**IntegrationSyncLogIndexRequest**:
```php
public function rules(): array
{
    return [
        'integration_id' => ['sometimes', 'string', 'uuid'],
        'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        'offset' => ['sometimes', 'integer', 'min:0'],
    ];
}
```

**ExternalProjectListRequest**:
```php
public function rules(): array
{
    return [];  // No additional params needed beyond route params
}
```

---

## 7. OAuth Flow

### 7.1 Jira OAuth 2.0 (3LO) Flow

```
Step 1: Admin clicks "Connect Jira" and enters site URL
    -> Frontend calls POST /integrations/connect { provider: 'jira', site_url: 'mycompany.atlassian.net' }

Step 2: Backend generates OAuth URL
    -> IntegrationService.initiateConnection()
    -> JiraAdapter.getAuthorizationUrl()
    -> Generates URL: https://auth.atlassian.com/authorize?
         audience=api.atlassian.com
         &client_id={JIRA_CLIENT_ID}
         &scope=read:jira-work write:jira-work read:jira-user offline_access
         &redirect_uri={callback_url}
         &state={random_state}
         &response_type=code
         &prompt=consent
    -> Store state + provider + site_url in session
    -> Return { redirect_url, state }

Step 3: Frontend redirects user to Atlassian
    -> window.location.href = redirect_url

Step 4: User grants access on Atlassian
    -> Atlassian redirects to: /api/v1/organizations/{org}/integrations/callback?code={code}&state={state}

Step 5: Backend exchanges code for tokens
    -> PmIntegrationController.callback()
    -> Validate state parameter
    -> IntegrationService.completeOAuthConnection()
    -> JiraAdapter.exchangeCode(code)
        -> POST https://auth.atlassian.com/oauth/token
           { grant_type: 'authorization_code', client_id, client_secret, code, redirect_uri }
        -> Response: { access_token, refresh_token, expires_in, scope }
    -> GET https://api.atlassian.com/oauth/token/accessible-resources
        -> Returns cloud IDs and site names
    -> Create IntegrationConnection with encrypted tokens
    -> Redirect to /organizations/{org}/settings/integrations?connected=jira

Step 6: Frontend detects redirect param
    -> Show "Successfully connected to Jira" toast
    -> Reload connection list
```

### 7.2 Asana OAuth 2.0 Flow

Similar to Jira but simpler:
- Authorization URL: `https://app.asana.com/-/oauth_authorize`
- Token URL: `https://app.asana.com/-/oauth_token`
- No site URL needed (workspace selected after connection)
- Scopes: `default` (full access)
- After token exchange, `GET /users/me` to get workspace GID and name

### 7.3 Trello API Key Flow

```
Step 1: Admin clicks "Connect Trello"
    -> Frontend shows form with API key and token fields
    -> Link to https://trello.com/app-key for user to generate key

Step 2: Admin submits credentials
    -> POST /integrations/connect { provider: 'trello', api_key: '...', api_token: '...' }

Step 3: Backend validates credentials
    -> IntegrationService.initiateConnection()
    -> TrelloAdapter.validateCredentials({ api_key, api_token })
        -> GET https://api.trello.com/1/members/me?key={key}&token={token}
        -> If 200: credentials valid, returns member info
        -> If 401: credentials invalid, throw exception

Step 4: Create connection
    -> IntegrationConnection created with encrypted api_key (as access_token) and api_token (as refresh_token)
    -> Return connection object with status 201
```

### 7.4 Token Storage Strategy

All tokens are encrypted before storage using Laravel's `Crypt::encryptString()`:

```php
// Writing tokens (in IntegrationConnection model via Attribute mutator):
$connection->access_token = $rawAccessToken;  // Encrypted automatically via set mutator
$connection->refresh_token = $rawRefreshToken;  // Encrypted automatically via set mutator
$connection->save();

// Reading tokens (decrypted automatically via get mutator):
$rawToken = $connection->access_token;  // Decrypted automatically
```

For Trello, the `api_key` is stored in `access_token` and the `api_token` in `refresh_token` fields, using the same encryption.

---

## 8. Webhook Handling

### 8.1 Jira Webhooks

**Endpoint**: `POST /api/v1/webhooks/jira/{organization}`

**Registration**: When a Jira connection is established, `JiraAdapter.registerWebhook()` calls the Jira REST API to register a webhook. The callback URL is the Solidtime webhook endpoint. A shared secret is generated and stored (encrypted) on the connection for signature validation.

**Events handled**:
- `jira:issue_created` -- Create ExternalTaskMapping + Solidtime Task
- `jira:issue_updated` -- Update mapping and task (name, status, done_at)
- `jira:issue_deleted` -- Mark mapping as `is_active = false`
- `project_created` -- Add to IntegrationProject list (not auto-enabled)
- `project_updated` -- Update IntegrationProject name/key
- `project_deleted` -- Mark IntegrationProject as `is_active = false`

**Signature validation**:
```php
public function validateWebhookSignature(
    IntegrationConnection $connection,
    string $payload,
    string $signature
): bool {
    $expected = hash_hmac('sha256', $payload, $connection->webhook_secret);
    return hash_equals($expected, $signature);
}
```

### 8.2 Asana Webhooks

**Endpoint**: `POST /api/v1/webhooks/asana/{organization}`

**Registration**: `AsanaAdapter.subscribeWebhook()` calls `POST /webhooks` with the project resource GID. Asana sends a handshake request with `X-Hook-Secret` header. The controller echoes this header back, and stores it as the webhook secret.

**Handshake flow**:
```php
if ($request->hasHeader('X-Hook-Secret')) {
    // This is a handshake request
    $secret = $request->header('X-Hook-Secret');
    // Store secret on connection
    $connection->webhook_secret = $secret;
    $connection->save();
    return response('', 200)->header('X-Hook-Secret', $secret);
}
```

**Events handled**:
- Task added to project
- Task changed (name, completion, assignee)
- Task removed from project
- Project name changed

**Signature validation**:
```php
$signature = $request->header('X-Hook-Signature');
$expected = hash_hmac('sha256', $payload, $connection->webhook_secret);
return hash_equals($expected, $signature);
```

### 8.3 Rate Limiting

Webhook endpoints are rate-limited at 100 requests/minute per organization using Laravel's built-in rate limiter:

```php
// In route registration or middleware:
RateLimiter::for('webhooks', function (Request $request) {
    return Limit::perMinute(100)->by($request->route('organization'));
});
```

---

## 9. Background Jobs

### 9.1 SyncIntegrationJob

**File**: `app/Jobs/SyncIntegrationJob.php`

Implements `ShouldQueue`, `ShouldDispatchAfterCommit`. Uses `Queueable`, `Dispatchable`, `InteractsWithQueue`, `SerializesModels` traits.

**Queue**: `integrations` (configurable via `INTEGRATION_SYNC_QUEUE` env var)

**Execution flow**:
1. Load `IntegrationConnection` (with organization)
2. Verify connection status is `connected`
3. Refresh token if needed via `IntegrationService::refreshToken()`
4. Call `IntegrationService::syncProjects()` to sync project list
5. For each enabled IntegrationProject: call `IntegrationService::syncTasks()`
6. If sync direction includes export: dispatch `ExportTimeEntriesJob`
7. Update `last_sync_at` and `last_sync_status` on connection

**Error handling**:
- API errors: caught, logged to sync log, job continues with remaining projects
- Token refresh failure: marks connection as `requires_reauth`, stops sync
- Rate limiting: respects `Retry-After` header, re-queues with delay
- Unhandled exceptions: job fails, logged to sync log as 'failed'

**Retry policy**: 3 attempts with exponential backoff (1 min, 5 min, 15 min)

### 9.2 ExportTimeEntriesJob

**File**: `app/Jobs/ExportTimeEntriesJob.php`

Implements `ShouldQueue`. Processes in batches of 50 entries per run.

**Execution flow**:
1. Load connection and verify export is enabled (direction = 'export' or 'bidirectional')
2. Query time entries on mapped tasks that have not been exported since their last update
3. For each entry (up to 50):
   - Resolve the ExternalTaskMapping for the task
   - Call adapter's `postTimeEntry()` with time data
   - On success: log as exported
   - On failure: log error, increment retry count
4. Entries that have failed 3 times are skipped and logged

**Identifying unexported entries**:
The sync log tracks exported entry IDs. The query joins `time_entries` with `external_task_mappings` and checks against `integration_sync_logs` to find entries where `time_entries.updated_at > last_export_time` for that connection.

### 9.3 RefreshIntegrationTokenJob

**File**: `app/Jobs/RefreshIntegrationTokenJob.php`

Implements `ShouldQueue`. Proactively refreshes tokens before they expire.

**Execution flow**:
1. Query all `IntegrationConnection` records where `token_expires_at < now() + 10 minutes` and status = 'connected'
2. For each: call `IntegrationService::refreshToken()`
3. On failure: increment `refresh_failure_count`; if >= 3, set status to `requires_reauth`

### 9.4 Scheduled Job Registration

**File**: `app/Console/Kernel.php`

```php
// In schedule() method:
$schedule->command('integration:sync')
    ->everyFiveMinutes()
    ->when(fn (): bool => config('integrations.sync_enabled'));

$schedule->command('integration:refresh-tokens')
    ->everyFiveMinutes()
    ->when(fn (): bool => config('integrations.sync_enabled'));
```

The `integration:sync` Artisan command queries active connections whose `last_sync_at + sync_frequency_minutes` has passed, and dispatches `SyncIntegrationJob` for each.

The `integration:refresh-tokens` Artisan command dispatches `RefreshIntegrationTokenJob`.

---

## 10. Frontend Architecture

### 10.1 Component Hierarchy

```
Integrations.vue (Page)
+-- IntegrationCard.vue x 3 (one per provider: Jira, Asana, Trello)
|   +-- Provider icon/logo
|   +-- Provider name and description
|   +-- Status badge (Connected / Not Connected / Error)
|   +-- "Connect" button (if not connected)
|   +-- "View" button (if connected)
|
+-- IntegrationDetail.vue (shown when a connected integration is selected)
|   +-- Connection info (provider, account name, status)
|   +-- Settings form (sync direction, frequency)
|   +-- "Sync Now" button
|   +-- "Disconnect" button with confirmation dialog
|   +-- ProjectSyncPanel.vue
|   |   +-- External project list with toggle switches
|   |   +-- Search/filter
|   |   +-- "Select All" / "Deselect All" buttons
|   +-- SyncHistoryLog.vue
|       +-- Paginated list of sync log entries
|       +-- Status, type, counts, timestamps
|       +-- Expandable error details
|
+-- OAuthCallbackHandler.vue (handles redirect from OAuth flow)
    +-- Detects ?connected={provider} query param
    +-- Shows success toast
    +-- Triggers connection list reload

ExternalReferenceBadge.vue (used in existing task selectors and time entry views)
    +-- Provider icon (Jira, Asana, Trello)
    +-- External reference text (e.g., "PROJ-123")
    +-- Clickable link to external task URL
```

### 10.2 Pinia Store: `useIntegrationStore`

**File**: `resources/js/utils/useIntegration.ts`

**State**:
```typescript
const connections = ref<IntegrationConnection[]>([]);
const selectedConnection = ref<IntegrationConnection | null>(null);
const integrationProjects = ref<IntegrationProject[]>([]);
const externalProjects = ref<ExternalProjectListItem[]>([]);
const syncLogs = ref<IntegrationSyncLog[]>([]);
const isLoading = ref(false);
const isSyncing = ref(false);
const error = ref<string | null>(null);
```

**Key Actions**:

| Action | Description |
|--------|-------------|
| `loadConnections()` | GET /integrations -- fetch all connections for the org |
| `loadConnection(id)` | GET /integrations/{id} -- fetch connection detail with projects and logs |
| `initiateConnect(provider, params)` | POST /integrations/connect -- start OAuth flow or validate API key |
| `disconnect(id)` | DELETE /integrations/{id} -- disconnect integration |
| `updateSettings(id, settings)` | PUT /integrations/{id} -- update sync direction/frequency |
| `triggerSync(id)` | POST /integrations/{id}/sync -- queue immediate sync |
| `loadExternalProjects(id)` | GET /integrations/{id}/external-projects -- fetch available projects |
| `toggleProject(projectId, enabled)` | PUT /integration-projects/{id}/toggle -- enable/disable sync |
| `loadSyncLogs(connectionId?)` | GET /integration-sync-logs -- fetch sync history |

### 10.3 TypeScript Types

**File**: `resources/js/types/integration.d.ts`

Defines: `PmProvider`, `ConnectionStatus`, `SyncDirection`, `SyncLogStatus`, `IntegrationConnection`, `IntegrationProject`, `ExternalTaskMapping`, `IntegrationSyncLog`, `ExternalProjectListItem`

See PRD Section 4.2 "Frontend Types (TypeScript)" for the full type definitions.

### 10.4 Page Registration

**File**: `routes/web.php`
```php
Route::get('/organizations/{organization}/settings/integrations', function () {
    return Inertia::render('Integrations');
})->name('integrations');
```

**File**: `resources/js/Layouts/AppLayout.vue`
```vue
<!-- In Organization Settings navigation section -->
<NavigationSidebarItem
    title="Integrations"
    :icon="PuzzlePieceIcon"
    :current="route().current('integrations')"
    :href="route('integrations', { organization: currentOrganization.id })">
</NavigationSidebarItem>
```

Uses `PuzzlePieceIcon` from `@heroicons/vue/20/solid`.

---

## 11. Permission Matrix

### 11.1 New Permissions

Three new permissions registered via `IntegrationPermissions`:

**File**: `app/Permissions/IntegrationPermissions.php`

```php
class IntegrationPermissions
{
    public static function register(): void
    {
        // Add integration permissions to each role
        // Owner: all integration permissions
        // Admin: all integration permissions
        // Manager: view + sync
        // Employee: none
    }
}
```

The permissions are added to the existing role definitions established by `CorePermissions::register()`:

| Permission | Owner | Admin | Manager | Employee |
|------------|:-----:|:-----:|:-------:|:--------:|
| `integrations:view` | Yes | Yes | Yes | No |
| `integrations:manage` | Yes | Yes | No | No |
| `integrations:sync` | Yes | Yes | Yes | No |

**Registration**: Called from `JetstreamServiceProvider::boot()` after `CorePermissions::register()`:

```php
CorePermissions::register();
IntegrationPermissions::register();
```

### 11.2 Endpoint Permission Mapping

| Endpoint | Required Permission | Roles with Access |
|----------|-------------------|-------------------|
| GET /integrations | `integrations:view` | Owner, Admin, Manager |
| GET /integrations/{id} | `integrations:view` | Owner, Admin, Manager |
| POST /integrations/connect | `integrations:manage` | Owner, Admin |
| GET /integrations/callback | `integrations:manage` | Owner, Admin |
| PUT /integrations/{id} | `integrations:manage` | Owner, Admin |
| DELETE /integrations/{id} | `integrations:manage` | Owner, Admin |
| POST /integrations/{id}/sync | `integrations:sync` | Owner, Admin, Manager |
| GET /integrations/{id}/external-projects | `integrations:manage` | Owner, Admin |
| GET /integration-projects | `integrations:view` | Owner, Admin, Manager |
| PUT /integration-projects/{id}/toggle | `integrations:manage` | Owner, Admin |
| GET /integration-sync-logs | `integrations:view` | Owner, Admin, Manager |
| POST /webhooks/jira/{org} | None (signature) | N/A |
| POST /webhooks/asana/{org} | None (signature) | N/A |

---

## 12. Security Strategy

### 12.1 Token Encryption

All sensitive credentials are encrypted at rest using Laravel's `Crypt::encryptString()`:
- `access_token` column on `IntegrationConnection`
- `refresh_token` column on `IntegrationConnection`
- `webhook_secret` column on `IntegrationConnection`

The encryption key is derived from the `APP_KEY` environment variable. Tokens are never returned in API responses.

### 12.2 OAuth State Validation

CSRF protection on the OAuth callback:
1. `Str::random(40)` generates a state parameter on `connect()`
2. State is stored in the session with provider metadata
3. On `callback()`, the state parameter is validated against the session
4. Mismatched state is rejected with 400 response (possible CSRF attack)

### 12.3 Webhook Signature Validation

All inbound webhooks are validated before processing:
- **Jira**: HMAC-SHA256 using the stored `webhook_secret`
- **Asana**: HMAC-SHA256 using the `X-Hook-Secret` established during handshake
- Invalid signatures result in a 400 response and are logged as security events

### 12.4 Organization Scoping

All database queries and API operations are scoped to the current organization:
- Route model binding validates that the `IntegrationConnection` belongs to the `Organization`
- Webhook endpoints look up the connection by `organization_id` from the route parameter
- No cross-organization data leakage is possible

### 12.5 Rate Limiting

- Webhook endpoints: 100 requests/minute per organization
- OAuth callback: 10 requests/minute per user
- External API calls: Respect provider rate limits via adapter-level throttling

### 12.6 Audit Logging

- Connection create/update/delete operations logged via `CustomAuditable` trait on `IntegrationConnection`
- All sync operations logged in `integration_sync_logs` table
- Failed webhook validations logged as security events

---

## 13. Performance Strategy

### 13.1 Database

- **Indexed queries**: All primary lookup patterns are covered by indexes on the new tables
- **Organization scoping**: All queries include `organization_id` to leverage the index
- **Cascade deletes**: Organization deletion cascades to all integration data
- **JSONB for metadata**: Provider-specific data stored in JSONB columns to avoid schema changes per provider

### 13.2 Sync Operations

- **Chunked inserts**: Sync operations process tasks in batches of 100 to limit memory
- **Upsert pattern**: `updateOrCreate()` used for idempotent sync (no duplicates on re-run)
- **Pagination**: External API responses are fetched page-by-page, not loaded entirely into memory
- **Incremental sync**: After initial full sync, subsequent syncs can use `updated_since` parameters where the provider API supports them

### 13.3 Background Processing

- **Dedicated queue**: Integration jobs run on the `integrations` queue, separate from the default queue
- **Job timeout**: 5 minutes per sync job (configurable)
- **Memory limit**: 128 MB per job
- **Rate limit awareness**: Jobs respect `Retry-After` headers from provider APIs

### 13.4 Frontend

- **Lazy loading**: External project list fetched on demand, not on page load
- **Polling for sync status**: After triggering a sync, the frontend polls every 5 seconds for status updates
- **Connection list caching**: Store retains connection list across navigation within the session

### 13.5 Performance Targets

| Metric | Target |
|--------|--------|
| Integration page load | < 500ms |
| OAuth redirect initiation | < 1s |
| External project list fetch | < 3s |
| Initial sync (100 tasks) | < 30s |
| Incremental sync | < 10s |
| Webhook processing | < 500ms |
| Time entry export (50 entries) | < 10s |
| Sync job memory | < 128 MB |

---

## 14. Integration Points

### 14.1 Existing Features -- Interactions

| Feature | Interaction | Risk |
|---------|-------------|------|
| Project CRUD | Sync creates new Projects; existing Project code unchanged | Low |
| Task CRUD | Sync creates new Tasks; existing Task code unchanged | Low |
| Time Entry CRUD | Export reads TimeEntries; no writes to TimeEntry table | None |
| Timer (running entries) | Not affected; sync only creates Tasks, not TimeEntries | None |
| Timesheet Grid (Feature 00) | External reference badges added to task selectors | Low |
| Reporting | Existing reports work with synced Projects/Tasks (same models) | None |
| Permission system | New permissions added via `IntegrationPermissions`; existing permissions unchanged | Low |
| Navigation | New sidebar item in Organization Settings; no existing items modified | None |

### 14.2 Future Features -- Extension Points

| Feature | Integration Point |
|---------|-------------------|
| Browser Extension (future) | Uses `external_task_mappings` to embed timer in PM tool pages |
| Advanced Reporting (Feature 09) | Can filter/group by integration provider and external reference |
| Calendar Enhanced (Feature 05) | Can show external task deadlines from synced metadata |
| Additional Providers (future) | New adapter class implementing `IntegrationAdapterInterface` |

---

## 15. File Manifest

### 15.1 New Files (53)

| File | Type | Task |
|------|------|------|
| `app/Enums/PmProvider.php` | Enum | PMI-002 |
| `app/Enums/IntegrationStatus.php` | Enum | PMI-002 |
| `app/Enums/SyncDirection.php` | Enum | PMI-002 |
| `app/Http/Controllers/Api/V1/PmIntegrationController.php` | Controller | PMI-008, PMI-013 |
| `app/Http/Controllers/Api/V1/IntegrationProjectController.php` | Controller | PMI-009, PMI-014 |
| `app/Http/Controllers/Api/V1/PmWebhookController.php` | Controller | PMI-010, PMI-015 |
| `app/Http/Requests/V1/PmIntegration/PmIntegrationConnectRequest.php` | Request | PMI-011 |
| `app/Http/Requests/V1/PmIntegration/IntegrationUpdateRequest.php` | Request | PMI-011 |
| `app/Http/Requests/V1/PmIntegration/IntegrationProjectToggleRequest.php` | Request | PMI-011 |
| `app/Http/Requests/V1/PmIntegration/IntegrationSyncLogIndexRequest.php` | Request | PMI-011 |
| `app/Http/Requests/V1/PmIntegration/ExternalProjectListRequest.php` | Request | PMI-011 |
| `app/Jobs/SyncIntegrationJob.php` | Job | PMI-017 |
| `app/Jobs/ExportTimeEntriesJob.php` | Job | PMI-018 |
| `app/Jobs/RefreshIntegrationTokenJob.php` | Job | PMI-019 |
| `app/Models/IntegrationConnection.php` | Model | PMI-002 |
| `app/Models/IntegrationProject.php` | Model | PMI-002 |
| `app/Models/ExternalTaskMapping.php` | Model | PMI-002 |
| `app/Models/IntegrationSyncLog.php` | Model | PMI-002 |
| `app/Permissions/IntegrationPermissions.php` | Permission | PMI-016 |
| `app/Service/IntegrationService.php` | Service | PMI-007 |
| `app/Service/Integration/IntegrationAdapterInterface.php` | Interface | PMI-003 |
| `app/Service/Integration/BaseIntegrationAdapter.php` | Abstract | PMI-003 |
| `app/Service/Integration/JiraAdapter.php` | Adapter | PMI-004 |
| `app/Service/Integration/AsanaAdapter.php` | Adapter | PMI-005 |
| `app/Service/Integration/TrelloAdapter.php` | Adapter | PMI-006 |
| `config/integrations.php` | Config | PMI-004, PMI-005, PMI-006 |
| `database/migrations/2026_03_16_000001_create_integration_connections_table.php` | Migration | PMI-001 |
| `database/migrations/2026_03_16_000002_create_integration_projects_table.php` | Migration | PMI-001 |
| `database/migrations/2026_03_16_000003_create_external_task_mappings_table.php` | Migration | PMI-001 |
| `database/migrations/2026_03_16_000004_create_integration_sync_logs_table.php` | Migration | PMI-001 |
| `database/factories/IntegrationConnectionFactory.php` | Factory | PMI-002 |
| `database/factories/IntegrationProjectFactory.php` | Factory | PMI-002 |
| `database/factories/ExternalTaskMappingFactory.php` | Factory | PMI-002 |
| `database/factories/IntegrationSyncLogFactory.php` | Factory | PMI-002 |
| `resources/js/Pages/Integrations.vue` | Page | PMI-024 |
| `resources/js/packages/ui/src/Integration/IntegrationCard.vue` | Component | PMI-025 |
| `resources/js/packages/ui/src/Integration/IntegrationDetail.vue` | Component | PMI-026 |
| `resources/js/packages/ui/src/Integration/ProjectSyncPanel.vue` | Component | PMI-027 |
| `resources/js/packages/ui/src/Integration/SyncHistoryLog.vue` | Component | PMI-028 |
| `resources/js/packages/ui/src/Integration/OAuthCallbackHandler.vue` | Component | PMI-029 |
| `resources/js/packages/ui/src/Integration/ExternalReferenceBadge.vue` | Component | PMI-030 |
| `resources/js/packages/ui/src/Integration/__tests__/IntegrationCard.test.ts` | Test | PMI-041 |
| `resources/js/packages/ui/src/Integration/__tests__/IntegrationDetail.test.ts` | Test | PMI-041 |
| `resources/js/packages/ui/src/Integration/__tests__/ProjectSyncPanel.test.ts` | Test | PMI-041 |
| `resources/js/packages/ui/src/Integration/__tests__/SyncHistoryLog.test.ts` | Test | PMI-041 |
| `resources/js/utils/useIntegration.ts` | Store | PMI-023 |
| `resources/js/types/integration.d.ts` | Types | PMI-022 |
| `tests/Unit/Endpoint/Api/V1/IntegrationEndpointTest.php` | Test | PMI-033 |
| `tests/Unit/Endpoint/Api/V1/IntegrationProjectEndpointTest.php` | Test | PMI-034 |
| `tests/Unit/Endpoint/Api/V1/WebhookEndpointTest.php` | Test | PMI-035 |
| `tests/Unit/Service/IntegrationServiceTest.php` | Test | PMI-036 |
| `tests/Unit/Service/Integration/JiraAdapterTest.php` | Test | PMI-037 |
| `tests/Unit/Service/Integration/AsanaAdapterTest.php` | Test | PMI-038 |
| `tests/Unit/Service/Integration/TrelloAdapterTest.php` | Test | PMI-039 |
| `tests/Unit/Job/SyncIntegrationJobTest.php` | Test | PMI-040 |
| `tests/Unit/Job/ExportTimeEntriesJobTest.php` | Test | PMI-040 |
| `tests/Unit/Job/RefreshIntegrationTokenJobTest.php` | Test | PMI-040 |
| `e2e/integrations.spec.ts` | Test | PMI-042 |

### 15.2 Modified Files (6)

| File | Change | Task |
|------|--------|------|
| `routes/api.php` | Add integration, integration-project, sync-log, and webhook route groups | PMI-012 |
| `routes/web.php` | Add Inertia page route for Integrations | PMI-032 |
| `resources/js/Layouts/AppLayout.vue` | Add sidebar navigation item for Integrations | PMI-031 |
| `app/Providers/JetstreamServiceProvider.php` | Add `IntegrationPermissions::register()` call | PMI-016 |
| `openapi.json` | Add 13 endpoint definitions | PMI-021 |
| `resources/js/packages/api/src/openapi.json.client.ts` | Regenerate from OpenAPI | PMI-021 |
