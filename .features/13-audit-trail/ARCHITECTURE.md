# Feature 13: Audit Trail / Activity Log -- Technical Architecture

**Date**: 2026-02-09
**Status**: Draft
**Feature Branch**: `feature/audit-trail` (from `main`)
**Task Prefix**: `AUD-` (per task assignments)

---

## Executive Summary

This document provides the complete technical architecture for the **Audit Trail / Activity Log** feature. The feature exposes Solidtime's existing audit data -- already captured by the `owen-it/laravel-auditing` package across all 10 core models -- through a dedicated, organization-scoped UI with filtering, drill-down diff views, and export capabilities.

**Key Architectural Decisions**:
- **One schema change** -- add nullable `organization_id` column to existing `audits` table with composite indexes
- **Backfill artisan command** populates `organization_id` on existing records via chunked joins
- **`CustomAuditable` trait extended** to auto-populate `organization_id` on new audit records
- **New `AuditLogService`** handles querying, entity name resolution, change summaries, and export
- **New `AuditLogController`** with 3 endpoints (index, show, export)
- **Cursor-based pagination** for consistent performance on large tables
- **2 new permissions** (`audit-logs:view`, `audit-logs:export`) following SF-02 convention
- **Frontend**: Pinia store + 7 Vue components + Inertia.js page with slide-over detail panel
- **No new npm or Composer dependencies** -- uses only existing packages

---

## Table of Contents

1. [Data Model Design](#1-data-model-design)
2. [API Contract](#2-api-contract)
3. [Service Layer](#3-service-layer)
4. [Controller Layer](#4-controller-layer)
5. [Request Validation](#5-request-validation)
6. [Frontend Architecture](#6-frontend-architecture)
7. [Permission Matrix](#7-permission-matrix)
8. [Performance Strategy](#8-performance-strategy)
9. [Integration Points](#9-integration-points)
10. [File Manifest](#10-file-manifest)

---

## 1. Data Model Design

### 1.1 Existing `audits` Table (Before Migration)

The `audits` table was created by migration `2024_09_02_094105_create_audits_table.php` and has the following schema:

```sql
CREATE TABLE audits (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_type       VARCHAR(255) NULL,
    user_id         CHAR(36) NULL,
    event           VARCHAR(255) NOT NULL,
    auditable_type  VARCHAR(255) NOT NULL,
    auditable_id    CHAR(36) NOT NULL,
    old_values      JSON NULL,
    new_values      JSON NULL,
    url             TEXT NULL,
    ip_address      VARCHAR(45) NULL,
    user_agent      VARCHAR(1023) NULL,
    tags            VARCHAR(255) NULL,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    INDEX idx_user (user_id, user_type),
    INDEX idx_auditable (auditable_type, auditable_id)
);
```

**Key problem**: No `organization_id` column. Without it, scoping audit records to an organization requires joining through each of 10 different auditable model types -- prohibitively slow for paginated listing.

### 1.2 Migration: Add `organization_id` (AUD-001)

**File**: `database/migrations/2026_03_13_000001_add_organization_id_to_audits_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->uuid('organization_id')->nullable()->after('tags');

            // Single-column index for basic org scoping
            $table->index('organization_id', 'idx_audits_organization_id');

            // Composite index for default listing: org + created_at DESC
            $table->index(
                ['organization_id', 'created_at'],
                'idx_audits_org_created'
            );

            // Composite index for type-filtered listing: org + type + created_at DESC
            $table->index(
                ['organization_id', 'auditable_type', 'created_at'],
                'idx_audits_org_type_created'
            );
        });
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropIndex('idx_audits_org_type_created');
            $table->dropIndex('idx_audits_org_created');
            $table->dropIndex('idx_audits_organization_id');
            $table->dropColumn('organization_id');
        });
    }
};
```

The column is **nullable** because:
1. Existing records need backfilling (AUD-002)
2. Some edge cases (User model audits for multi-org users) may be ambiguous
3. Console-triggered audits may not have an organization context

### 1.3 Post-Migration Schema

```sql
-- audits table with new column and indexes:
ALTER TABLE audits ADD COLUMN organization_id CHAR(36) NULL AFTER tags;
CREATE INDEX idx_audits_organization_id ON audits(organization_id);
CREATE INDEX idx_audits_org_created ON audits(organization_id, created_at DESC);
CREATE INDEX idx_audits_org_type_created ON audits(organization_id, auditable_type, created_at DESC);
```

### 1.4 Backfill Command (AUD-002)

**File**: `app/Console/Commands/BackfillAuditOrganizationIds.php`

**Command**: `php artisan audit:backfill-organization-ids`

Strategy by auditable type:

| Auditable Type | Resolution Strategy |
|---------------|-------------------|
| `TimeEntry` | Direct: `time_entries.organization_id` |
| `Project` | Direct: `projects.organization_id` |
| `Task` | Indirect: `tasks` JOIN `projects` on `project_id` to get `projects.organization_id` |
| `Client` | Direct: `clients.organization_id` |
| `Tag` | Direct: `tags.organization_id` |
| `Member` | Direct: `members.organization_id` |
| `Organization` | Self: `auditable_id` = organization_id |
| `ProjectMember` | Indirect: `project_members` JOIN `projects` on `project_id` to get `projects.organization_id` |
| `OrganizationInvitation` | Direct: `organization_invitations.organization_id` |
| `User` | Via membership: `members` WHERE `user_id` = `auditable_id`, take first `organization_id` (or derive from request URL if available) |

Implementation details:
- Processes in chunks of 1,000 records per auditable type
- Uses raw SQL `UPDATE ... FROM ... JOIN` for efficiency (no Eloquent hydration)
- Only updates records where `organization_id IS NULL` (idempotent)
- `--dry-run` flag previews counts without writing
- Progress bar per auditable type
- Handles orphaned records (deleted auditable) gracefully -- leaves `organization_id` as NULL

### 1.5 CustomAuditable Trait Extension (AUD-003)

**File**: `app/Models/Concerns/CustomAuditable.php` (modified)

The trait is extended to inject `organization_id` into new audit records using the `transformAudit()` method from `OwenIt\Auditing\Auditable`:

```php
<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Audit;

trait CustomAuditable
{
    use Auditable;

    /**
     * @var array<string>|null
     */
    protected ?array $auditEvents = null;

    public function disableAuditing(): void
    {
        $this->auditEvents = [];
    }

    /**
     * Transform the audit data before it is stored.
     * Injects organization_id into the audit record.
     */
    public function transformAudit(array $data): array
    {
        $data['organization_id'] = $this->resolveAuditOrganizationId();

        return $data;
    }

    /**
     * Resolve the organization_id for this model's audit record.
     */
    protected function resolveAuditOrganizationId(): ?string
    {
        // Models with direct organization_id attribute
        if (isset($this->organization_id)) {
            return $this->organization_id;
        }

        // Organization model: the model IS the organization
        if ($this instanceof \App\Models\Organization) {
            return $this->getKey();
        }

        // Models with indirect organization_id via project relationship
        if (method_exists($this, 'project') && $this->project !== null) {
            return $this->project->organization_id ?? null;
        }

        // User model: resolve from request context (organization in URL)
        if ($this instanceof \App\Models\User) {
            $organization = request()->route('organization');
            if ($organization instanceof \App\Models\Organization) {
                return $organization->getKey();
            }
        }

        return null;
    }
}
```

### 1.6 Audit Model Extension

The existing `App\Models\Audit` model needs the new `organization_id` property documented:

```php
/**
 * @property int $id
 * @property string|null $user_type
 * @property string|null $user_id
 * @property string $event
 * @property string $auditable_type
 * @property string $auditable_id
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $url
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $tags
 * @property string|null $organization_id     // <-- NEW
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Audit extends PackageAuditModel
{
    use HasFactory;
}
```

### 1.7 Existing Model Usage (Read-Only)

All 10 models using `CustomAuditable` are read-only dependencies for entity name resolution:

| Model | Name Resolution Field | Has `organization_id` |
|-------|----------------------|:---------------------:|
| `TimeEntry` | `description` or "Time Entry {start}" | Yes (direct) |
| `Project` | `name` | Yes (direct) |
| `Task` | `name` | Yes (via `project`) |
| `Client` | `name` | Yes (direct) |
| `Tag` | `name` | Yes (direct) |
| `Member` | User's `name` via relationship | Yes (direct) |
| `Organization` | `name` | Self |
| `ProjectMember` | "{user name} on {project name}" | Yes (via `project`) |
| `OrganizationInvitation` | `email` | Yes (direct) |
| `User` | `name` | Via membership |

---

## 2. API Contract

### 2.1 Route Registration

**File**: `routes/api.php` (inside existing `auth:api` + `verified` middleware group)

```php
// Audit log routes (read-only -- no check-organization-blocked middleware)
Route::name('audit-logs.')->prefix('/organizations/{organization}')->group(static function (): void {
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('index');
    Route::get('/audit-logs/export', [AuditLogController::class, 'export'])->name('export');
    Route::get('/audit-logs/{audit}', [AuditLogController::class, 'show'])->name('show');
});
```

**Route order is critical**: The `export` route must be registered before the `{audit}` wildcard route, otherwise "export" would be interpreted as an audit ID.

Route names resolve to:
- `api.v1.audit-logs.index`
- `api.v1.audit-logs.export`
- `api.v1.audit-logs.show`

All endpoints are read-only, so `check-organization-blocked` middleware is not applied.

### 2.2 Endpoint Signatures

| Method | Path | Controller Method | Request Class | Permission |
|--------|------|-------------------|---------------|------------|
| GET | `/audit-logs` | `index()` | `AuditLogIndexRequest` | `audit-logs:view` |
| GET | `/audit-logs/export` | `export()` | `AuditLogExportRequest` | `audit-logs:export` |
| GET | `/audit-logs/{audit}` | `show()` | (none -- audit ID in route) | `audit-logs:view` |

### 2.3 Response Shapes

**GET /audit-logs** (list with cursor pagination):

```json
{
  "data": [
    {
      "id": 12345,
      "event": "updated",
      "auditable_type": "time-entry",
      "auditable_type_label": "Time Entry",
      "auditable_id": "01234567-89ab-cdef-0123-456789abcdef",
      "auditable_name": "Backend API work",
      "user_id": "abcdef01-2345-6789-abcd-ef0123456789",
      "user_name": "John Doe",
      "user_email": "john@example.com",
      "user_profile_photo_url": "https://...",
      "changed_fields": ["description", "end"],
      "change_summary": "Changed description, end",
      "created_at": "2026-02-09T14:30:00Z"
    }
  ],
  "meta": {
    "cursor": "eyJpZCI6MTIzNDUsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0",
    "per_page": 50,
    "has_more": true,
    "total_count": 1234
  }
}
```

**GET /audit-logs/{audit}** (single record with resolved values):

```json
{
  "data": {
    "id": 12345,
    "event": "updated",
    "auditable_type": "time-entry",
    "auditable_type_label": "Time Entry",
    "auditable_id": "01234567-89ab-cdef-0123-456789abcdef",
    "auditable_name": "Backend API work",
    "user_id": "abcdef01-2345-6789-abcd-ef0123456789",
    "user_name": "John Doe",
    "user_email": "john@example.com",
    "user_profile_photo_url": "https://...",
    "old_values": {
      "description": "API work",
      "end": "2026-02-09T17:00:00Z"
    },
    "new_values": {
      "description": "Backend API work",
      "end": "2026-02-09T18:00:00Z"
    },
    "resolved_old_values": {
      "description": { "raw": "API work", "display": "API work" },
      "end": { "raw": "2026-02-09T17:00:00Z", "display": "2026-02-09 17:00:00" }
    },
    "resolved_new_values": {
      "description": { "raw": "Backend API work", "display": "Backend API work" },
      "end": { "raw": "2026-02-09T18:00:00Z", "display": "2026-02-09 18:00:00" }
    },
    "changed_fields": ["description", "end"],
    "change_summary": "Changed description, end",
    "ip_address": "192.168.1.0",
    "user_agent": "Mozilla/5.0 ...",
    "url": "https://app.solidtime.io/api/v1/organizations/.../time-entries/...",
    "created_at": "2026-02-09T14:30:00Z"
  }
}
```

**GET /audit-logs/export** (file download):

```
Content-Type: text/csv (or application/json)
Content-Disposition: attachment; filename="audit-log-acme-corp-2026-02-09.csv"

Timestamp,Event,Entity Type,Entity ID,Entity Name,User,Email,Changed Fields,Old Values,New Values,IP Address,URL
2026-02-09T14:30:00Z,updated,Time Entry,01234567...,Backend API work,John Doe,john@example.com,"description, end","{...}","{...}",192.168.1.0,https://...
```

---

## 3. Service Layer

### 3.1 AuditLogService

**File**: `app/Service/AuditLogService.php`

Stateless service class with 4 public methods and 4 private helper methods.

#### `getAuditLogs(Organization, array $filters, ?string $cursor, int $perPage): CursorPaginator`

1. Build base query: `Audit::where('organization_id', $organization->getKey())`
2. Apply filters (all optional, AND logic):
   - `auditable_type` (array): `whereIn('auditable_type', $morphClasses)`
   - `event` (array): `whereIn('event', $events)`
   - `user_id` (string): `where('user_id', $userId)`
   - `date_from` (string): `where('created_at', '>=', $dateFrom->startOfDay())`
   - `date_to` (string): `where('created_at', '<=', $dateTo->endOfDay())`
   - `auditable_id` (string): `where('auditable_id', $auditableId)`
3. Order by `created_at DESC, id DESC` (consistent cursor ordering)
4. Cursor paginate with `$perPage` limit
5. For each record: resolve entity name, generate change summary, resolve user info
6. Return `CursorPaginator`

**Filter value mapping**: The API accepts kebab-case type values (e.g., `time-entry`) which must be mapped to morph class names (e.g., `App\Models\TimeEntry`) for the database query. The `AUDITABLE_TYPE_MAP` constant handles this.

#### `getAuditDetail(Organization, int $auditId): Audit`

1. Query: `Audit::where('organization_id', $organization->getKey())->findOrFail($auditId)`
2. Resolve entity name via `resolveEntityName()`
3. Resolve UUID fields in `old_values` and `new_values` to human-readable names via `resolveFieldValues()`
4. Generate change summary
5. Load acting user info
6. Return enriched Audit model

**UUID resolution examples**:
- `project_id: "uuid"` becomes `project_id: { raw: "uuid", display: "Website Redesign" }`
- `task_id: "uuid"` becomes `task_id: { raw: "uuid", display: "Frontend Development" }`
- `member_id: "uuid"` becomes `member_id: { raw: "uuid", display: "John Doe" }`

#### `exportAuditLogs(Organization, array $filters, string $format): StreamedResponse`

1. Build query using same filter logic as `getAuditLogs()` (without cursor pagination)
2. Apply hard limit: `->limit(10_000)`
3. Check total count -- if exceeds 10,000, return 422 response
4. For `csv` format:
   - Stream CSV rows with headers: Timestamp, Event, Entity Type, Entity ID, Entity Name, User, Email, Changed Fields, Old Values, New Values, IP Address, URL
   - Use `LazyCollection` for memory efficiency
5. For `json` format:
   - Stream JSON array of full audit record objects
6. Set `Content-Disposition` header with descriptive filename

#### `resolveEntityName(string $auditableType, string $auditableId): ?string`

1. Map morph class name to model class via `AUDITABLE_TYPE_MAP`
2. Query the model: `$modelClass::find($auditableId)`
3. If model exists, return the display name:
   - `TimeEntry`: `$model->description ?: "Time Entry " . $model->start->format('Y-m-d H:i')`
   - `Project`: `$model->name`
   - `Task`: `$model->name`
   - `Client`: `$model->name`
   - `Tag`: `$model->name`
   - `Member`: `$model->user->name`
   - `Organization`: `$model->name`
   - `ProjectMember`: `$model->member->user->name . " on " . $model->project->name`
   - `OrganizationInvitation`: `$model->email`
   - `User`: `$model->name`
4. If model is deleted (soft-delete or hard-delete), return `null`
5. Results cached in a request-scoped array to avoid repeated lookups

### 3.2 Constants

```php
/**
 * Mapping from API filter values (kebab-case) to morph class names.
 */
private const AUDITABLE_TYPE_MAP = [
    'time-entry'              => \App\Models\TimeEntry::class,
    'project'                 => \App\Models\Project::class,
    'task'                    => \App\Models\Task::class,
    'client'                  => \App\Models\Client::class,
    'tag'                     => \App\Models\Tag::class,
    'member'                  => \App\Models\Member::class,
    'organization'            => \App\Models\Organization::class,
    'project-member'          => \App\Models\ProjectMember::class,
    'organization-invitation' => \App\Models\OrganizationInvitation::class,
    'user'                    => \App\Models\User::class,
];

/**
 * Mapping from morph class names to human-readable labels.
 */
private const AUDITABLE_TYPE_LABELS = [
    \App\Models\TimeEntry::class              => 'Time Entry',
    \App\Models\Project::class                => 'Project',
    \App\Models\Task::class                   => 'Task',
    \App\Models\Client::class                 => 'Client',
    \App\Models\Tag::class                    => 'Tag',
    \App\Models\Member::class                 => 'Member',
    \App\Models\Organization::class           => 'Organization',
    \App\Models\ProjectMember::class          => 'Project Member',
    \App\Models\OrganizationInvitation::class => 'Organization Invitation',
    \App\Models\User::class                   => 'User',
];

/**
 * UUID fields that can be resolved to human-readable names.
 */
private const RESOLVABLE_UUID_FIELDS = [
    'project_id'  => \App\Models\Project::class,
    'task_id'     => \App\Models\Task::class,
    'client_id'   => \App\Models\Client::class,
    'member_id'   => \App\Models\Member::class,
    'user_id'     => \App\Models\User::class,
];
```

### 3.3 Helper Methods

#### `getEntityTypeLabel(string $auditableType): string`

Maps morph class name to human-readable label using `AUDITABLE_TYPE_LABELS`. Falls back to class basename if not found.

#### `generateChangeSummary(?array $oldValues, ?array $newValues, string $event): string`

- `created`: "Created"
- `deleted`: "Deleted"
- `restored`: "Restored"
- `updated`: "Changed {field1}, {field2}, ..." (list of keys present in `new_values` but different from `old_values`)

#### `resolveFieldValues(?array $values, string $auditableType): array`

For each key-value pair in the values array:
1. If the key is in `RESOLVABLE_UUID_FIELDS` and the value is a UUID string:
   - Look up the referenced model
   - Return `{ raw: $value, display: $resolvedName }` or `{ raw: $value, display: "[Deleted]" }`
2. Otherwise: return `{ raw: $value, display: (string) $value }`

---

## 4. Controller Layer

### 4.1 AuditLogController

**File**: `app/Http/Controllers/Api/V1/AuditLogController.php`

Extends `App\Http\Controllers\Api\V1\Controller` (which provides `$this->checkPermission()`, `$this->user()`, `$this->member()`).

```php
class AuditLogController extends Controller
{
    public function index(
        Organization $organization,
        AuditLogIndexRequest $request,
        AuditLogService $auditLogService
    ): JsonResponse {
        $this->checkPermission($organization, 'audit-logs:view');

        $paginator = $auditLogService->getAuditLogs(
            $organization,
            $request->validated(),
            $request->input('cursor'),
            $request->input('per_page', 50)
        );

        return new JsonResponse(
            new AuditLogCollection($paginator)
        );
    }

    public function show(
        Organization $organization,
        int $audit,
        AuditLogService $auditLogService
    ): JsonResponse {
        $this->checkPermission($organization, 'audit-logs:view');

        $auditRecord = $auditLogService->getAuditDetail(
            $organization,
            $audit
        );

        return new JsonResponse([
            'data' => new AuditLogDetailResource($auditRecord),
        ]);
    }

    public function export(
        Organization $organization,
        AuditLogExportRequest $request,
        AuditLogService $auditLogService
    ): StreamedResponse|JsonResponse {
        $this->checkPermission($organization, 'audit-logs:export');

        return $auditLogService->exportAuditLogs(
            $organization,
            $request->validated(),
            $request->input('format')
        );
    }
}
```

**Key patterns followed**:
- Service injected via method parameter type-hints (not constructor), following the pattern used by `ChartController` and `TimeEntryController`
- Organization resolved via route model binding
- Permission checks before any business logic
- Returns `JsonResponse` for list/detail, `StreamedResponse` for export

---

## 5. Request Validation

### 5.1 Request Classes

All extend `App\Http\Requests\V1\BaseFormRequest`.

**AuditLogIndexRequest**:

```php
public function rules(): array
{
    return [
        'cursor'          => ['nullable', 'string'],
        'per_page'        => ['nullable', 'integer', 'min:1', 'max:100'],
        'auditable_type'  => ['nullable', 'array'],
        'auditable_type.*' => ['string', 'in:time-entry,project,task,client,tag,member,organization,project-member,organization-invitation,user'],
        'event'           => ['nullable', 'array'],
        'event.*'         => ['string', 'in:created,updated,deleted,restored'],
        'user_id'         => ['nullable', 'uuid'],
        'date_from'       => ['nullable', 'date_format:Y-m-d'],
        'date_to'         => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        'auditable_id'    => ['nullable', 'uuid'],
    ];
}
```

**AuditLogExportRequest** (extends AuditLogIndexRequest):

```php
public function rules(): array
{
    return array_merge(parent::rules(), [
        'format' => ['required', 'string', 'in:csv,json'],
    ]);
}
```

The `user_id` filter validates as UUID format only (not `exists:users,id`) to keep the request validation lightweight. The service layer handles the case where the user ID does not match any records.

---

## 6. Frontend Architecture

### 6.1 Component Hierarchy

```
AuditLog.vue (Page)
├── Warning Banner (v-if auditing disabled, passed via Inertia shared data)
├── Toolbar
│   ├── Page Title "Audit Log"
│   ├── Active filter count badge
│   └── AuditLogExport.vue (v-if canExport)
│       ├── "Export" dropdown button
│       └── Options: "Export as CSV", "Export as JSON"
├── AuditLogFilters.vue
│   ├── Entity Type multi-select dropdown
│   ├── Event Type multi-select dropdown
│   ├── User searchable select (organization members)
│   ├── Date Range picker (from/to)
│   ├── Entity ID text input (advanced, collapsible)
│   ├── Active filter chips with remove buttons
│   └── "Clear All Filters" button
├── AuditLogList.vue
│   ├── Result count display ("Showing X of Y records")
│   ├── Loading skeleton (during fetch)
│   ├── Empty state (when no records match)
│   ├── AuditLogRow.vue x N
│   │   ├── Timestamp (formatted)
│   │   ├── AuditLogEventBadge.vue
│   │   │   └── Color-coded badge (Created=green, Updated=blue, Deleted=red, Restored=yellow)
│   │   ├── Entity type label + entity name
│   │   ├── User avatar + name
│   │   └── Change summary text
│   └── "Load More" button (cursor pagination)
└── AuditLogDetail.vue (slide-over, conditionally rendered)
    ├── Header: entity type + name, event badge, timestamp
    ├── Section: Acting User (name, email, avatar)
    ├── Section: Metadata (IP, user agent, URL)
    ├── Section: Changes
    │   └── AuditLogDiffTable.vue
    │       ├── Two-column layout: Old Value | New Value
    │       ├── Rows for each changed field
    │       ├── Changed cells highlighted (amber background)
    │       ├── Toggle: show all fields vs changed only
    │       ├── Pretty-printed JSON values
    │       ├── Resolved UUIDs shown as "Name (uuid)" format
    │       ├── Created events: only New Value column
    │       └── Deleted events: only Old Value column
    ├── Close button (X)
    ├── Click-outside to close
    └── Escape key to close
```

### 6.2 Pinia Store: `useAuditLogStore`

**File**: `resources/js/utils/useAuditLog.ts`

**State**:

```typescript
const auditRecords = ref<AuditRecord[]>([]);
const selectedRecord = ref<AuditRecord | null>(null);
const selectedRecordDetail = ref<AuditLogDetailResponse | null>(null);
const filters = ref<AuditLogFilters>({
    auditable_type: [],
    event: [],
    user_id: null,
    date_from: null,
    date_to: null,
    auditable_id: null,
});
const pagination = ref<{
    cursor: string | null;
    hasMore: boolean;
    perPage: number;
    totalCount: number;
}>({
    cursor: null,
    hasMore: false,
    perPage: 50,
    totalCount: 0,
});
const isLoading = ref(false);
const isLoadingDetail = ref(false);
const isExporting = ref(false);
const error = ref<string | null>(null);
```

**Key Actions**:

| Action | Description |
|--------|-------------|
| `loadAuditLogs()` | Fetch first page with current filters (GET /audit-logs), reset cursor |
| `loadMore()` | Fetch next page using current cursor, append to `auditRecords` |
| `setFilters(newFilters)` | Update filters, sync to URL, reload from first page |
| `clearFilters()` | Reset all filters, sync to URL, reload from first page |
| `selectRecord(id)` | Set `selectedRecord`, fetch detail (GET /audit-logs/{id}), set `selectedRecordDetail` |
| `deselectRecord()` | Clear `selectedRecord` and `selectedRecordDetail` |
| `exportLogs(format)` | Trigger export download (GET /audit-logs/export?format=csv\|json) with current filters |
| `syncFiltersFromUrl()` | Parse current URL query parameters into `filters` state |
| `syncFiltersToUrl()` | Update URL query parameters from `filters` state (using `router.replace`) |

**Getters**:

| Getter | Returns |
|--------|---------|
| `activeFilterCount` | Number of non-empty/non-null filter values |
| `hasActiveFilters` | Boolean: `activeFilterCount > 0` |
| `totalDisplayed` | `auditRecords.length` |

**URL Synchronization**:

Filter state is bidirectionally synced with URL query parameters:
- On page load: `syncFiltersFromUrl()` reads `?auditable_type=time-entry&event=updated&...`
- On filter change: `syncFiltersToUrl()` updates URL without page reload via `window.history.replaceState()`
- This enables shareable URLs for filtered audit views (REQ-006: entity-scoped links)

### 6.3 TypeScript Types

**File**: `resources/js/types/auditLog.d.ts`

Defines: `AuditRecord`, `AuditLogFilters`, `CursorPagination`, `AuditLogListResponse`, `AuditLogDetailResponse`, `AuditableTypeOption`, `MemberOption`

Full type definitions as specified in PRD Section 4.2.

### 6.4 Page Registration

**File**: `routes/web.php`

```php
Route::get('/audit-log', function () {
    return Inertia::render('AuditLog');
})->name('audit-log');
```

**File**: `resources/js/Layouts/AppLayout.vue`

```vue
<NavigationSidebarItem
    v-if="canViewAuditLogs()"
    title="Audit Log"
    :icon="ClipboardDocumentListIcon"
    :current="route().current('audit-log')"
    :href="route('audit-log')">
</NavigationSidebarItem>
```

Positioned in the second navigation section (management), after "Members" and before "Tags":

```
Members      (existing)
Audit Log    (NEW -- permission-gated)
Tags         (existing)
Invoices     (existing)
```

The `canViewAuditLogs()` helper checks if the current user has `audit-logs:view` permission. Uses `ClipboardDocumentListIcon` from `@heroicons/vue/20/solid`.

---

## 7. Permission Matrix

Two new permissions are registered following the SF-02 naming convention.

### 7.1 Permission Registration

**File**: `app/Permissions/AuditLogPermissions.php` (new)

```php
class AuditLogPermissions
{
    public static function register(): void
    {
        // Permissions are added to existing role definitions
        // via CorePermissions or appended in JetstreamServiceProvider
    }
}
```

Permissions added to `CorePermissions.php` role arrays:

| Permission | Owner | Admin | Manager | Employee | Placeholder |
|------------|:-----:|:-----:|:-------:|:--------:|:-----------:|
| `audit-logs:view` | Yes | Yes | Yes | No | No |
| `audit-logs:export` | Yes | Yes | No | No | No |

### 7.2 Endpoint Permission Mapping

| Endpoint | Required Permission | Notes |
|----------|-------------------|-------|
| GET `/audit-logs` | `audit-logs:view` | List and filter audit records |
| GET `/audit-logs/{audit}` | `audit-logs:view` | View single record detail |
| GET `/audit-logs/export` | `audit-logs:export` | Download audit data |

### 7.3 UI Visibility

| UI Element | Visibility Condition |
|-----------|---------------------|
| Sidebar "Audit Log" item | User has `audit-logs:view` |
| Export button | User has `audit-logs:export` |
| "View History" links on entity pages | User has `audit-logs:view` |

---

## 8. Performance Strategy

### 8.1 Database

- **Composite index** `(organization_id, created_at DESC)` covers the default listing query
- **Composite index** `(organization_id, auditable_type, created_at DESC)` covers type-filtered queries
- **Cursor-based pagination** instead of offset pagination -- consistent O(1) page fetch regardless of offset depth
- **No N+1 on user info**: The `user` relationship on the Audit model is eager-loaded for list queries
- **Request-scoped entity name cache**: `resolveEntityName()` caches results in a local array to avoid repeated lookups when the same entity appears multiple times in a page of results
- **LazyCollection for exports**: Export queries use `cursor()` / `LazyCollection` to stream records without loading all 10,000 into memory

### 8.2 Frontend

- **Cursor pagination**: "Load More" appends to the existing list (no re-fetching)
- **Lazy detail loading**: Detail data fetched only when a record is clicked (not preloaded for all records)
- **Debounced filters**: Filter changes are debounced (300ms) to prevent excessive API calls during rapid selection changes
- **URL state**: Filter state stored in URL parameters, eliminating the need for session-based state persistence

### 8.3 Targets

| Metric | Target |
|--------|--------|
| GET `/audit-logs` (50 records, no filters) | < 800ms |
| GET `/audit-logs` (50 records, type filter) | < 500ms |
| GET `/audit-logs/{audit}` (single record) | < 300ms |
| GET `/audit-logs/export` (1,000 records, CSV) | < 5s |
| GET `/audit-logs/export` (10,000 records, CSV) | < 30s |
| UI: filter change perceived latency | < 500ms |
| UI: detail panel open perceived latency | < 300ms |

### 8.4 Scaling Considerations

The `audits` table grows continuously with every model mutation. For very large deployments:

1. **Table partitioning** by month (future enhancement) would allow efficient pruning and query isolation
2. **Read replicas** can be used for the audit log queries (read-only endpoints)
3. **Retention policies** (future enhancement) can automatically archive or delete audit records older than a configurable period
4. **Approximate total count**: The `total_count` in the pagination meta uses `COUNT(*)` with the same filters. For very large result sets, this can be replaced with an approximate count (e.g., `EXPLAIN` count) to avoid the counting overhead

---

## 9. Integration Points

### 9.1 Existing Features -- No Conflicts

| Feature | Interaction |
|---------|-------------|
| Time page (time entries) | Independent -- reads same `audits` table but via separate UI |
| Filament Admin Panel | Existing `AuditResource` Filament page continues to work independently; it is a system-admin view, while this feature is an organization-scoped user view |
| Existing `CustomAuditable` trait | Modified (backward-compatible) to add `transformAudit()` method |
| Existing `Audit` model | Modified (add `organization_id` property docblock) |
| Existing `AuditFactory` | May need `organization_id` state method for tests |

### 9.2 Existing Filament AuditResource

The existing Filament admin panel has an `AuditResource` at `app/Filament/Resources/AuditResource.php` that provides a system-level view of all audits (not organization-scoped). This is a **separate concern**:

- **Filament AuditResource**: System admin panel, shows all audits across all organizations, no user-facing URL
- **This feature's Audit Log**: Organization-scoped user-facing page, shows only the current organization's audits

The two do not conflict. The Filament resource will continue to work after the migration adds `organization_id`.

### 9.3 Future Features -- Designed for Extension

| Feature | Integration Point |
|---------|-------------------|
| **Feature 01 (Timesheet Approvals)** | Audit trail becomes more valuable when combined with approval workflows -- managers can review change history of submitted time entries |
| **Feature 09 (Advanced Reporting)** | Future reporting features could incorporate audit data for compliance reports |
| **Feature 10 (Teams & Groups)** | When team scoping is enabled (SF-07), the audit log could be filtered by team scope |

### 9.4 Prerequisite: Auditing Must Be Enabled

The `config/audit.php` file sets `'enabled' => env('AUDITING_ENABLED', false)`. This feature requires `AUDITING_ENABLED=true` to produce any data. The UI will show a warning banner when auditing is disabled.

---

## 10. File Manifest

### 10.1 New Files (22)

| File | Type | Task |
|------|------|------|
| `database/migrations/2026_03_13_000001_add_organization_id_to_audits_table.php` | Migration | AUD-001 |
| `app/Console/Commands/BackfillAuditOrganizationIds.php` | Command | AUD-002 |
| `app/Permissions/AuditLogPermissions.php` | Permissions | AUD-004 |
| `app/Service/AuditLogService.php` | Service | AUD-005 |
| `app/Http/Controllers/Api/V1/AuditLogController.php` | Controller | AUD-006 |
| `app/Http/Requests/V1/AuditLog/AuditLogIndexRequest.php` | Request | AUD-007 |
| `app/Http/Requests/V1/AuditLog/AuditLogExportRequest.php` | Request | AUD-007 |
| `app/Http/Resources/V1/AuditLog/AuditLogResource.php` | Resource | AUD-009 |
| `app/Http/Resources/V1/AuditLog/AuditLogCollection.php` | Resource | AUD-009 |
| `app/Http/Resources/V1/AuditLog/AuditLogDetailResource.php` | Resource | AUD-009 |
| `resources/js/Pages/AuditLog.vue` | Page | AUD-011 |
| `resources/js/packages/ui/src/AuditLog/AuditLogList.vue` | Component | AUD-012 |
| `resources/js/packages/ui/src/AuditLog/AuditLogRow.vue` | Component | AUD-012 |
| `resources/js/packages/ui/src/AuditLog/AuditLogEventBadge.vue` | Component | AUD-012 |
| `resources/js/packages/ui/src/AuditLog/AuditLogFilters.vue` | Component | AUD-013 |
| `resources/js/packages/ui/src/AuditLog/AuditLogDetail.vue` | Component | AUD-014 |
| `resources/js/packages/ui/src/AuditLog/AuditLogDiffTable.vue` | Component | AUD-014 |
| `resources/js/packages/ui/src/AuditLog/AuditLogExport.vue` | Component | AUD-015 |
| `resources/js/utils/useAuditLog.ts` | Store | AUD-016 |
| `resources/js/types/auditLog.d.ts` | Types | AUD-016 |
| `tests/Unit/Endpoint/Api/V1/AuditLogEndpointTest.php` | Test | AUD-019 |
| `tests/Unit/Service/AuditLogServiceTest.php` | Test | AUD-020 |
| `tests/Unit/Console/BackfillAuditOrganizationIdsTest.php` | Test | AUD-021 |
| `resources/js/packages/ui/src/AuditLog/__tests__/AuditLogList.test.ts` | Test | AUD-022 |
| `resources/js/packages/ui/src/AuditLog/__tests__/AuditLogFilters.test.ts` | Test | AUD-022 |
| `resources/js/packages/ui/src/AuditLog/__tests__/AuditLogDetail.test.ts` | Test | AUD-022 |
| `resources/js/packages/ui/src/AuditLog/__tests__/AuditLogEventBadge.test.ts` | Test | AUD-022 |
| `e2e/audit-log.spec.ts` | Test | AUD-023 |

### 10.2 Modified Files (7)

| File | Change | Task |
|------|--------|------|
| `app/Models/Concerns/CustomAuditable.php` | Add `transformAudit()` and `resolveAuditOrganizationId()` methods | AUD-003 |
| `app/Models/Audit.php` | Add `organization_id` property docblock | AUD-001 |
| `app/Permissions/CorePermissions.php` | Add `audit-logs:view` and `audit-logs:export` to role arrays | AUD-004 |
| `app/Providers/JetstreamServiceProvider.php` | Add `AuditLogPermissions::register()` call | AUD-004 |
| `routes/api.php` | Add audit-logs route group | AUD-008 |
| `routes/web.php` | Add Inertia page route | AUD-017 |
| `resources/js/Layouts/AppLayout.vue` | Add sidebar nav item (permission-gated) | AUD-017 |
| `openapi.json` | Add 3 endpoint definitions | AUD-010 |
| `resources/js/packages/api/src/openapi.json.client.ts` | Regenerated from OpenAPI | AUD-010 |
| Entity detail pages (Projects, Tasks, Clients, Members) | Add "View History" links | AUD-018 |
