# Codebase Analysis: Feature 16 -- PM Tool Integrations (Jira, Asana, Trello)

**Date**: 2026-02-09
**Branch analyzed**: `main`
**Target feature branch**: `feature/pm-integrations`
**PRD reference**: `.features/16-pm-integrations/PRD.md`

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Existing Project Model Infrastructure](#2-existing-project-model-infrastructure)
3. [Existing Task Model Infrastructure](#3-existing-task-model-infrastructure)
4. [Existing TimeEntry Model Infrastructure](#4-existing-timeentry-model-infrastructure)
5. [Queue and Job Infrastructure](#5-queue-and-job-infrastructure)
6. [Encryption Patterns](#6-encryption-patterns)
7. [OAuth Client Capability](#7-oauth-client-capability)
8. [Permission System](#8-permission-system)
9. [Controller Patterns](#9-controller-patterns)
10. [Service Layer Patterns](#10-service-layer-patterns)
11. [Request Validation Patterns](#11-request-validation-patterns)
12. [Enum Patterns](#12-enum-patterns)
13. [Frontend Patterns](#13-frontend-patterns)
14. [Route Registration Patterns](#14-route-registration-patterns)
15. [Navigation Sidebar](#15-navigation-sidebar)
16. [Data Flow Diagrams](#16-data-flow-diagrams)
17. [File Modification Risk Assessment](#17-file-modification-risk-assessment)

---

## 1. Executive Summary

The Solidtime codebase provides a mature foundation for building the PM Tool Integrations feature, but several capabilities required by this feature do not yet exist and must be built from scratch. Unlike the Weekly Timesheet Grid (Feature 00) which operated entirely on existing models, this feature introduces entirely new infrastructure: new database tables, new models, OAuth consumer capability, webhook handling, background sync jobs, and a provider adapter pattern.

**Key findings**:

- **4 new database tables required** -- `integration_connections`, `integration_projects`, `external_task_mappings`, `integration_sync_logs`
- **No OAuth consumer infrastructure exists** -- Solidtime is an OAuth provider (via Passport) but has no code for consuming OAuth from external services. The `league/oauth2-client` library or equivalent must be added.
- **Queue infrastructure exists but is underutilized** -- Only 2 jobs exist (`RecalculateSpentTimeForProject`, `RecalculateSpentTimeForTask`). The queue system is functional but the `app/Console/Kernel.php` scheduler has no job-dispatching commands.
- **No encryption of model attributes exists** -- The codebase uses cookie encryption and key generation but no model uses `Crypt::encryptString()` for column-level encryption. This is a new pattern for the codebase.
- **No webhook handling exists** -- There are no inbound webhook endpoints. Route-level rate limiting and signature validation patterns must be introduced.
- **Existing models are clean and well-structured** -- `Project`, `Task`, and `TimeEntry` follow consistent patterns with `HasUuids`, `CustomAuditable`, and `HasFactory` traits. Creating new models will follow the same patterns.
- **Permission system supports modular registration** -- `CorePermissions::register()` is called from `JetstreamServiceProvider`. Adding `IntegrationPermissions::register()` is straightforward.
- **Frontend patterns are well-established** -- Pinia stores, Vue components, Inertia pages, and TypeScript types all follow consistent patterns that the integration frontend can mirror.

---

## 2. Existing Project Model Infrastructure

### 2.1 Project Model

**File**: `app/Models/Project.php`

Key characteristics relevant to PM integrations:
- Uses `HasUuids` trait (UUID primary keys -- consistent with new models)
- Uses `CustomAuditable` trait (audit logging -- new models should also use this)
- Uses `HasFactory` trait (factories needed for testing)
- Uses `ComputedAttributes` trait (for `spent_time` computed column)
- `name` (string), `color` (string), `organization_id` (UUID FK), `client_id` (nullable UUID FK)
- `is_billable` (boolean, default false), `is_public` (boolean)
- `archived_at` (nullable datetime) with computed `is_archived` attribute
- `estimated_time` (nullable int), `spent_time` (int, computed)

**Relationships**:
- `belongsTo` Organization, Client
- `hasMany` Task, TimeEntry, ProjectMember

**Sync writes to Project**:
When the integration sync creates a new Solidtime project from an external project, it will:
- Set `name` from the external project name
- Set `color` via `ColorService` (auto-assignment)
- Set `organization_id` from the connection's organization
- Set `is_billable` to `false` (default; admin can change later)
- Set `is_public` to `true` (so all members can see synced projects)
- Leave `client_id` as `null`, `estimated_time` as `null`

**Impact**: The sync creates new `Project` records but does NOT modify existing projects or the Project model class itself. The `IntegrationProject` table links external projects to Solidtime projects via a `project_id` FK.

### 2.2 Project Visibility Scope

```php
public function scopeVisibleByEmployee(Builder $builder, User $user): void
{
    $builder->where(function (Builder $builder) use ($user): Builder {
        return $builder->where('is_public', '=', true)
            ->orWhereHas('members', function (Builder $builder) use ($user): Builder {
                return $builder->whereBelongsTo($user, 'user');
            });
    });
}
```

Synced projects should be set to `is_public = true` so they are visible to all organization members. If more fine-grained control is needed in the future, project members can be assigned during sync.

### 2.3 ColorService

**File**: `app/Service/ColorService.php`

Used to auto-assign colors to new projects. The integration sync should call `ColorService` when creating Solidtime projects from external data to ensure consistent color assignment.

---

## 3. Existing Task Model Infrastructure

### 3.1 Task Model

**File**: `app/Models/Task.php`

Key characteristics relevant to PM integrations:
- Uses `HasUuids`, `CustomAuditable`, `HasFactory`, `ComputedAttributes` traits
- `name` (string), `project_id` (UUID FK -- NOT nullable, every task belongs to a project), `organization_id` (UUID FK)
- `done_at` (nullable datetime) with computed `is_done` attribute
- `estimated_time` (nullable int), `spent_time` (int, computed)

**Relationships**:
- `belongsTo` Project, Organization
- `hasMany` TimeEntry

**Sync writes to Task**:
When the integration sync creates a new Solidtime task from an external task:
- Set `name` from external task name (e.g., Jira issue summary)
- Set `project_id` from the linked Solidtime project (via `IntegrationProject.project_id`)
- Set `organization_id` from the connection's organization
- Set `done_at` based on external task status (e.g., Jira "Done" status, Asana `completed = true`)
- Set `estimated_time` from external data if available (Jira story points x configurable ratio, Asana custom field)

On re-sync, existing tasks are updated:
- `name` updated if external name changed
- `done_at` updated if external status changed (completed or re-opened)

**Impact**: Sync creates new `Task` records and updates existing ones, but does NOT modify the Task model class. The `ExternalTaskMapping` table links external tasks to Solidtime tasks via a `task_id` FK.

### 3.2 Task Visibility

```php
public function scopeVisibleByEmployee(Builder $builder, User $user): Builder
{
    return $builder->whereHas('project', function (Builder $builder) use ($user): Builder {
        return $builder->visibleByEmployee($user);
    });
}
```

Since synced projects are `is_public = true`, synced tasks inherit visibility to all organization members.

---

## 4. Existing TimeEntry Model Infrastructure

### 4.1 TimeEntry Model

**File**: `app/Models/TimeEntry.php`

Key characteristics relevant to PM integrations:
- `start` (Carbon datetime, not null), `end` (Carbon datetime, nullable -- null = running timer)
- `project_id` (nullable UUID FK), `task_id` (nullable UUID FK)
- `user_id` (UUID FK), `member_id` (UUID FK), `organization_id` (UUID FK)
- `billable` (boolean), `description` (string), `tags` (array)
- `client_id` (nullable UUID FK -- computed from project)
- `billable_rate` (nullable int -- computed via `BillableRateService`)

**Export reads from TimeEntry**:
When the integration export pushes time entries to the PM tool:
- Read `start`, `end` to calculate duration in seconds
- Read `description` for worklog comment text
- Read `task_id` to look up the `ExternalTaskMapping` and get the external task ID
- Read `user_id` to attribute the time entry to a user
- The `getDuration()` method returns a `CarbonInterval` useful for calculating seconds

**Impact**: The export job READS `TimeEntry` records but never WRITES to them. The integration feature does NOT add columns to the `time_entries` table. Export tracking (which entries have been exported) is managed via `integration_sync_logs` or a separate tracking mechanism, not by modifying `TimeEntry`.

### 4.2 Existing TimeEntry Query Patterns

The aggregation pattern used in `TimeEntryAggregationService` and `Project.getSpentTimeComputed()` is relevant:

```php
// Standard aggregation pattern (from TimeEntryAggregationService)
TimeEntry::query()
    ->selectRaw('SUM(EXTRACT(EPOCH FROM ("end" - start))) as total_seconds')
    ->whereBelongsTo($organization, 'organization')
    ->whereNotNull('end')
    ->first();
```

The export job will use a similar query pattern to find unexported entries:

```php
TimeEntry::query()
    ->whereNotNull('end')
    ->whereIn('task_id', $mappedTaskIds)
    ->where('updated_at', '>', $lastExportTime)
    ->get();
```

---

## 5. Queue and Job Infrastructure

### 5.1 Existing Jobs

**File**: `app/Jobs/RecalculateSpentTimeForProject.php`

```php
class RecalculateSpentTimeForProject implements ShouldDispatchAfterCommit, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public Project $project;

    public function __construct(Project $project)
    {
        $this->project = $project;
    }

    public function handle(): void
    {
        $this->project->setComputedAttributeValue('spent_time');
        if ($this->project->isDirty()) {
            $this->project->save();
        }
    }
}
```

This establishes the pattern for new jobs:
- Implement `ShouldQueue` (and optionally `ShouldDispatchAfterCommit`)
- Use `Dispatchable`, `InteractsWithQueue`, `Queueable`, `SerializesModels` traits
- Accept model instances via constructor (serialized/deserialized by the queue)
- Business logic in `handle()` method

**File**: `app/Jobs/RecalculateSpentTimeForTask.php` -- Same pattern for Task.

### 5.2 Queue Configuration

The application uses Laravel's queue system. The `QUEUE_CONNECTION` is configurable via `.env`. For local development, `sync` driver is common; production uses `database`, `redis`, or similar.

The integration jobs should use a dedicated queue name (`integrations`) to allow separate worker scaling:

```php
class SyncIntegrationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public IntegrationConnection $connection
    ) {
        $this->onQueue(config('integrations.sync_queue', 'integrations'));
    }
}
```

### 5.3 Console Kernel (Scheduler)

**File**: `app/Console/Kernel.php`

The existing scheduler uses `$schedule->command()` pattern:

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('time-entry:send-still-running-mails')
        ->when(fn (): bool => config('scheduling.tasks.time_entry_send_still_running_mails'))
        ->everyTenMinutes();
    // ... more scheduled commands
}
```

The integration sync and token refresh will follow this pattern:

```php
$schedule->command('integration:sync')
    ->everyFiveMinutes()
    ->when(fn (): bool => config('integrations.sync_enabled', true));

$schedule->command('integration:refresh-tokens')
    ->everyFiveMinutes()
    ->when(fn (): bool => config('integrations.sync_enabled', true));
```

New Artisan commands (`integration:sync`, `integration:refresh-tokens`) need to be created in `app/Console/Commands/` and will dispatch the corresponding jobs.

### 5.4 Assessment

The queue infrastructure is functional but lightly used. Only 2 simple jobs exist today. The integration feature introduces significantly more complex jobs (multi-step sync, error handling, retries, rate limiting). This is new territory for the codebase but follows established Laravel conventions.

**New patterns introduced by this feature**:
- Jobs with retry logic and exponential backoff
- Jobs with configurable queue names
- Artisan commands that dispatch jobs
- Scheduled commands that conditionally dispatch jobs

---

## 6. Encryption Patterns

### 6.1 Existing Encryption Usage

The codebase currently uses encryption in limited contexts:
- **Cookie encryption**: `app/Http/Middleware/EncryptCookies.php` (standard Laravel)
- **Key generation**: `app/Console/Commands/SelfHost/SelfHostGenerateKeysCommand.php` (generates app keys)
- **No model-level encryption**: No existing model uses `Crypt::encryptString()` or `Crypt::decryptString()` for column data

### 6.2 New Encryption Pattern

The `IntegrationConnection` model introduces column-level encryption as a new pattern:

```php
use Illuminate\Support\Facades\Crypt;
use Illuminate\Database\Eloquent\Casts\Attribute;

protected function accessToken(): Attribute
{
    return Attribute::make(
        get: fn (?string $value) => $value !== null ? Crypt::decryptString($value) : null,
        set: fn (?string $value) => $value !== null ? Crypt::encryptString($value) : null,
    );
}
```

This pattern will be used for 3 columns: `access_token`, `refresh_token`, `webhook_secret`.

**Important considerations**:
- Encrypted values are significantly longer than raw values. The columns must be `TEXT` type, not `VARCHAR`.
- The encryption key is derived from `APP_KEY`. Changing `APP_KEY` will make existing tokens unreadable.
- `Crypt::encryptString()` is NOT searchable. These columns cannot appear in `WHERE` clauses.
- The `$hidden` model property should include these columns to prevent accidental exposure in API responses.
- Factories should NOT encrypt values (use raw strings for testing); the model accessors handle encryption automatically.

### 6.3 Assessment

Column-level encryption is a new pattern for this codebase. It is well-supported by Laravel's `Crypt` facade and the Eloquent `Attribute` cast pattern. The implementation is straightforward but developers should be aware of the TEXT column requirement and non-searchability constraint.

---

## 7. OAuth Client Capability

### 7.1 Current State

Solidtime is currently an **OAuth provider** (via Laravel Passport) that issues access tokens to API clients. It does NOT act as an **OAuth consumer** that connects to external services.

**Existing OAuth provider setup**:
- `Laravel\Passport\HasApiTokens` on the `User` model
- `auth:api` middleware uses Passport for token validation
- API tokens have scopes and expiration
- Personal access tokens via `ApiTokenController`

**Missing OAuth consumer infrastructure**:
- No `league/oauth2-client` or equivalent package installed
- No outbound OAuth flow (authorization URL generation, code exchange, token refresh)
- No session-based state parameter management for OAuth callbacks
- No encrypted token storage for external service credentials

### 7.2 Required Additions

The integration feature needs to add OAuth consumer capability:

**Option A: Use `league/oauth2-client`** (recommended):
- Well-maintained, widely-used PHP OAuth 2.0 client
- Provider packages available: `mrjoops/oauth2-jira`, `haydenpierce/oauth2-asana`
- Handles authorization URL generation, code exchange, token refresh
- Requires: `composer require league/oauth2-client`

**Option B: Custom implementation via Guzzle**:
- Build OAuth flows directly using `guzzlehttp/guzzle` (already installed)
- More control, fewer dependencies
- More code to maintain

**Recommendation**: Option A for Jira and Asana (standard OAuth 2.0), with Guzzle used directly for Trello (API key auth, not OAuth).

### 7.3 Callback Route Considerations

The OAuth callback endpoint (`GET /api/v1/organizations/{organization}/integrations/callback`) sits inside the `auth:api` middleware group. This means the user must still be authenticated when the OAuth provider redirects back. This works because:
1. The user initiates the flow from the Solidtime UI (already authenticated)
2. The browser session is maintained during the redirect to the provider and back
3. The API auth token is sent via header on the callback redirect (Inertia handles this)

However, there is a subtlety: the callback URL is an API route, but the redirect from the OAuth provider is a browser navigation (not an AJAX call). The callback handler should redirect (302) to the Inertia page rather than returning JSON.

---

## 8. Permission System

### 8.1 Permission Registration Pattern

**File**: `app/Permissions/CorePermissions.php`

Permissions are registered per-role using `Jetstream::role()`:

```php
class CorePermissions
{
    public static function register(): void
    {
        Jetstream::role(Role::Owner->value, 'Owner', [
            'charts:view:own',
            'charts:view:all',
            'projects:view',
            // ... 50+ permissions
        ])->description('Owner users can perform any action.');

        Jetstream::role(Role::Admin->value, 'Administrator', [
            // Similar but without billing
        ])->description('...');

        Jetstream::role(Role::Manager->value, 'Manager', [
            // Subset of admin permissions
        ])->description('...');

        Jetstream::role(Role::Employee->value, 'Employee', [
            'charts:view:own',
            'projects:view',
            'tags:view',
            'tasks:view',
            'clients:view',
            'time-entries:view:own',
            'time-entries:create:own',
            'time-entries:update:own',
            'time-entries:delete:own',
            'organizations:view',
            'notifications:view',
        ])->description('...');
    }
}
```

**Called from**: `app/Providers/JetstreamServiceProvider.php` in the `boot()` method:
```php
CorePermissions::register();
```

### 8.2 Permission Check Pattern

**File**: `app/Service/PermissionStore.php`

The `PermissionStore` service caches permissions per user+organization and checks them:

```php
public function has(Organization $organization, string $permission): bool
{
    // Resolves user, checks role permissions, returns bool
}
```

**File**: `app/Http/Controllers/Api/V1/Controller.php`

The base controller provides helper methods:

```php
protected function checkPermission(Organization $organization, string $permission): void
{
    if (!$this->permissionStore->has($organization, $permission)) {
        throw new AuthorizationException();
    }
}

protected function checkAnyPermission(Organization $organization, array $permissions): void
{
    foreach ($permissions as $permission) {
        if ($this->permissionStore->has($organization, $permission)) {
            return;
        }
    }
    throw new AuthorizationException();
}
```

### 8.3 Integration Permission Strategy

New permissions follow the existing naming pattern: `{entity}:{action}`:

- `integrations:view` -- See connection status and sync history
- `integrations:manage` -- Connect, disconnect, configure, select projects
- `integrations:sync` -- Trigger manual sync

**Registration approach**: Create `app/Permissions/IntegrationPermissions.php` following the same pattern as `CorePermissions`. The challenge is that `Jetstream::role()` replaces the entire role definition, so `IntegrationPermissions` must append to the existing role arrays rather than redefining them.

**Solution**: Since `CorePermissions::register()` calls `Jetstream::role()` which sets up the role, `IntegrationPermissions` needs to modify the role after it has been created. One approach:

```php
class IntegrationPermissions
{
    public static function register(): void
    {
        // Re-register each role with the integration permissions appended
        // to the existing permission arrays from CorePermissions
        $integrationPermissions = [
            Role::Owner->value => ['integrations:view', 'integrations:manage', 'integrations:sync'],
            Role::Admin->value => ['integrations:view', 'integrations:manage', 'integrations:sync'],
            Role::Manager->value => ['integrations:view', 'integrations:sync'],
            Role::Employee->value => [],
        ];
        // ... logic to merge with existing role permissions
    }
}
```

Alternatively, the integration permissions can be added directly to `CorePermissions.php`. Given that `CorePermissions` already contains 50+ permissions per role, this approach keeps the file focused and avoids merge conflicts with other features.

### 8.4 Assessment

The permission system is well-structured and supports modular extension. The `IntegrationPermissions` class can be created as a new file and registered in `JetstreamServiceProvider` alongside `CorePermissions`. The only modification to existing files is a single line addition in the service provider.

---

## 9. Controller Patterns

### 9.1 Base Controller

**File**: `app/Http/Controllers/Api/V1/Controller.php`

All API controllers extend this base:
```php
protected PermissionStore $permissionStore;  // Injected via constructor

protected function checkPermission(Organization $organization, string $permission): void
protected function checkAnyPermission(Organization $organization, array $permissions): void
protected function user(): User
protected function member(Organization $organization): Member
```

### 9.2 Existing Controller Patterns

**Pattern for resource controllers** (from `ProjectController`):
- Constructor DI of service classes
- Organization injected via route model binding
- Permission checks at the start of each method
- Request validation via typed request classes
- JSON responses with `['data' => $result]` structure
- `check-organization-blocked` middleware on write endpoints

**Pattern for aggregation controllers** (from `ChartController`):
- Similar to resource controllers
- Returns computed data, not model resources directly

**Pattern for the PmIntegrationController**:
The integration controller is closest to a resource controller but with additional methods for OAuth flow and sync. It follows the existing pattern exactly:

```php
class PmIntegrationController extends Controller
{
    public function __construct(
        private readonly IntegrationService $integrationService
    ) {}

    public function index(Organization $organization): JsonResponse
    {
        $this->checkPermission($organization, 'integrations:view');
        $connections = IntegrationConnection::query()
            ->whereBelongsTo($organization, 'organization')
            ->get();
        return response()->json(['data' => $connections]);
    }
}
```

### 9.3 PmWebhookController Special Considerations

The `PmWebhookController` does NOT extend the standard API base controller because:
1. Webhook requests come from external services, not authenticated users
2. There is no Bearer token or session cookie
3. Authentication is via webhook signature validation, not Passport

The controller should extend `\App\Http\Controllers\Controller` (the base Laravel controller) directly, not `Api\V1\Controller`. The webhook routes must be registered OUTSIDE the `auth:api` middleware group.

---

## 10. Service Layer Patterns

### 10.1 Service Conventions

**Established patterns** (from `TimeEntryAggregationService`, `BillableRateService`, `ColorService`):
- Location: `app/Service/`
- Stateless classes (no constructor state -- or constructor DI of other services)
- Methods accept model instances (Organization, Member, etc.), not IDs
- Return plain arrays or scalar values (not Eloquent collections or API resources)
- Injected into controllers via constructor type-hints

### 10.2 IntegrationService Pattern

`IntegrationService` follows the existing pattern but is more complex than current services:
- Orchestrates multiple operations (connect, sync, export, webhook processing)
- Uses an adapter pattern to abstract provider-specific logic
- Manages transactional state (token refresh, sync logs)
- Dispatches background jobs

This is the first service in the codebase that delegates to a strategy/adapter pattern. The adapter resolution is a factory method within the service:

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

Using `app()` for resolution allows adapters to be injected with their own dependencies if needed.

### 10.3 Adapter Subdirectory

Provider-specific adapters live in `app/Service/Integration/` -- a new subdirectory. This is the first subdirectory under `app/Service/`. The convention is established by this feature for future use (e.g., `app/Service/Export/`, `app/Service/Import/`).

---

## 11. Request Validation Patterns

### 11.1 Base Request

**File**: `app/Http/Requests/V1/BaseFormRequest.php`

All request classes extend this. It provides:
- Access to `$this->organization` via route model binding
- Standard authorization logic

### 11.2 Validation Conventions

**From `TimeEntryStoreRequest`** (complex validation):
```php
'project_id' => [
    'nullable', 'string', 'uuid',
    new ExistsEloquent(Project::class, null, function ($builder) {
        $builder->whereBelongsTo($this->organization, 'organization');
    }),
],
```

**From simpler requests** (direct rules):
```php
'limit' => ['sometimes', 'integer', 'min:1', 'max:52'],
'offset' => ['sometimes', 'integer', 'min:0'],
```

### 11.3 Integration Request Approach

The integration request classes use simpler validation:
- `PmIntegrationConnectRequest`: Validates `provider` is one of the allowed values, conditional fields for Trello (api_key, api_token) and Jira (site_url)
- `IntegrationUpdateRequest`: Validates enum values for sync direction and frequency
- `IntegrationProjectToggleRequest`: Simple boolean validation
- `IntegrationSyncLogIndexRequest`: Standard pagination params

No `ExistsEloquent` validation is needed because the integration models are resolved via route model binding, not via request body IDs.

---

## 12. Enum Patterns

### 12.1 Existing Enums

**File**: `app/Enums/Role.php`

```php
enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Manager = 'manager';
    case Employee = 'employee';
    case Placeholder = 'placeholder';
}
```

All existing enums are backed by `string` values and follow the pattern:
- PascalCase enum names
- lowercase string values
- `declare(strict_types=1)` at top
- Namespace: `App\Enums`

### 12.2 New Enums

The integration feature introduces 3 new enums following the same pattern:

```php
// app/Enums/PmProvider.php
enum PmProvider: string
{
    case Jira = 'jira';
    case Asana = 'asana';
    case Trello = 'trello';
}

// app/Enums/IntegrationStatus.php
enum IntegrationStatus: string
{
    case Connected = 'connected';
    case RequiresReauth = 'requires_reauth';
    case Error = 'error';
    case Disconnected = 'disconnected';
}

// app/Enums/SyncDirection.php
enum SyncDirection: string
{
    case Import = 'import';
    case Export = 'export';
    case Bidirectional = 'bidirectional';
}
```

These enums are used in:
- Model casts (`'provider' => PmProvider::class`)
- Request validation (`Rule::in(PmProvider::cases())`)
- Service logic (match expressions)

---

## 13. Frontend Patterns

### 13.1 Pinia Store Pattern

**From `useTimesheet.ts`** (established in Feature 00):
```typescript
export const useTimesheetStore = defineStore('timesheet', () => {
    const weekList = ref<WeekSummary[]>([]);
    const isLoadingList = ref(false);

    async function loadWeekList() {
        isLoadingList.value = true;
        try {
            const response = await api.getTimesheetWeeks({ ... });
            weekList.value = response.data.data;
        } finally {
            isLoadingList.value = false;
        }
    }

    return { weekList, isLoadingList, loadWeekList };
});
```

The `useIntegrationStore` follows this exact pattern with integration-specific state.

### 13.2 API Client

**File**: `resources/js/packages/api/src/openapi.json.client.ts`

Auto-generated from OpenAPI spec. After adding integration endpoints and regenerating:

```typescript
api.getIntegrations({ organization: orgId })
api.getIntegration({ organization: orgId, integration: id })
api.connectIntegration({ organization: orgId, provider: 'jira', site_url: '...' })
api.updateIntegration({ organization: orgId, integration: id, sync_direction: 'bidirectional' })
// etc.
```

### 13.3 Page Component Pattern

**From `Time.vue`**:
```vue
<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import MainContainer from '@/packages/ui/src/MainContainer.vue';

onMounted(async () => {
    await store.fetchData();
});
</script>

<template>
    <AppLayout title="Time" data-testid="time_view">
        <MainContainer>
            <!-- Page content -->
        </MainContainer>
    </AppLayout>
</template>
```

The `Integrations.vue` page follows this pattern with `AppLayout` wrapping the content.

### 13.4 UI Component Location

Feature-specific components live in `resources/js/packages/ui/src/{Feature}/`:
- `resources/js/packages/ui/src/Integration/IntegrationCard.vue`
- `resources/js/packages/ui/src/Integration/IntegrationDetail.vue`
- etc.

Tests go in `__tests__/` subdirectory within the feature component directory.

### 13.5 TypeScript Types Location

**File**: `resources/js/types/integration.d.ts`

Following the pattern of `resources/js/types/timesheet.d.ts`.

### 13.6 Organization Context

**File**: `resources/js/utils/useUser.ts`

`getCurrentOrganizationId()` returns the current organization UUID for API calls. All integration store actions use this for the organization route parameter.

---

## 14. Route Registration Patterns

### 14.1 API Routes

**File**: `routes/api.php`

API routes are registered inside nested middleware groups:

```php
Route::prefix('v1')->name('v1.')->group(static function (): void {
    Route::middleware(['auth:api', 'verified'])->group(static function (): void {
        // Feature route groups here
        Route::name('integrations.')->prefix('/organizations/{organization}')->group(static function (): void {
            // Integration endpoints
        });
    });
});
```

The integration routes follow this pattern exactly. Write endpoints include `check-organization-blocked` middleware.

**Webhook routes** must be outside the `auth:api` middleware because they come from external services without Bearer tokens. They should be registered at the top level of the `v1` prefix group, NOT inside the `auth:api` group.

### 14.2 Web Routes

**File**: `routes/web.php`

Inertia page routes registered inside `auth:web` middleware:

```php
Route::middleware(['auth:web', 'verified'])->group(function () {
    Route::get('/organizations/{organization}/settings/integrations', function () {
        return Inertia::render('Integrations');
    })->name('integrations');
});
```

### 14.3 Route Name Conventions

Existing route name patterns:
- `api.v1.projects.index` -- standard resource
- `api.v1.time-entries.store` -- hyphenated multi-word
- `api.v1.timesheet.weeks` -- custom action

Integration route names:
- `api.v1.integrations.index` -- standard resource
- `api.v1.integrations.connect` -- custom action
- `api.v1.integrations.callback` -- OAuth callback
- `api.v1.integration-projects.toggle` -- hyphenated resource
- `api.v1.webhooks.jira` -- webhook receiver

---

## 15. Navigation Sidebar

**File**: `resources/js/Layouts/AppLayout.vue`

The sidebar uses `NavigationSidebarItem` components. For integrations, the link belongs in the Organization Settings section (not the main navigation), since it is an admin-facing configuration feature.

```vue
<!-- In Organization Settings navigation -->
<NavigationSidebarItem
    title="Integrations"
    :icon="PuzzlePieceIcon"
    :current="route().current('integrations')"
    :href="route('integrations', { organization: currentOrganization.id })">
</NavigationSidebarItem>
```

Uses `PuzzlePieceIcon` from `@heroicons/vue/20/solid`.

---

## 16. Data Flow Diagrams

### 16.1 OAuth Connection Flow

```
Admin clicks "Connect Jira" in Integrations.vue
    -> useIntegrationStore.initiateConnect('jira', { site_url: '...' })
        -> POST /api/v1/organizations/{org}/integrations/connect
        -> PmIntegrationController.connect()
            -> IntegrationService.initiateConnection()
            -> JiraAdapter.getAuthorizationUrl()
            -> Return { redirect_url, state }
        -> Store state in session
    -> Frontend: window.location.href = redirect_url

User grants access on Atlassian consent page
    -> Atlassian redirects to: /api/v1/organizations/{org}/integrations/callback?code=...&state=...

    -> PmIntegrationController.callback()
        -> Validate state parameter against session
        -> IntegrationService.completeOAuthConnection()
            -> JiraAdapter.exchangeCode(code)
                -> POST https://auth.atlassian.com/oauth/token
                -> GET https://api.atlassian.com/oauth/token/accessible-resources
            -> Create IntegrationConnection (encrypted tokens)
            -> Optionally: JiraAdapter.registerWebhook()
        -> Redirect to /organizations/{org}/settings/integrations?connected=jira

    -> Integrations.vue detects ?connected=jira
    -> OAuthCallbackHandler.vue shows success toast
    -> useIntegrationStore.loadConnections() refreshes list
```

### 16.2 Background Sync Flow

```
Laravel Scheduler runs every 5 minutes
    -> Artisan command: integration:sync
    -> Query IntegrationConnection WHERE status = 'connected'
        AND last_sync_at + sync_frequency_minutes < now()
    -> For each connection:
        -> Dispatch SyncIntegrationJob

SyncIntegrationJob::handle()
    -> IntegrationService.refreshToken() (if needed)
    -> IntegrationService.syncProjects(connection)
        -> adapter.getProjects(connection)
        -> For each external project:
            -> Find or create IntegrationProject
            -> If is_enabled and no linked Project:
                -> Create Solidtime Project (via ColorService)
                -> Set IntegrationProject.project_id
    -> For each enabled IntegrationProject:
        -> IntegrationService.syncTasks(connection, integrationProject)
            -> adapter.getTasks(connection, externalProjectId)
            -> For each external task:
                -> Find or create ExternalTaskMapping
                -> If no linked Task:
                    -> Create Solidtime Task
                    -> Set ExternalTaskMapping.task_id
                -> Else: Update Task name, done_at
    -> Update connection.last_sync_at, last_sync_status
    -> Create IntegrationSyncLog entry

    -> If sync_direction includes 'export':
        -> Dispatch ExportTimeEntriesJob
```

### 16.3 Webhook Event Flow

```
Jira sends webhook to POST /api/v1/webhooks/jira/{org}

PmWebhookController.jira()
    -> Look up IntegrationConnection for org + provider='jira'
    -> If no connection: return 404
    -> JiraAdapter.validateWebhookSignature(connection, payload, signature)
    -> If invalid: return 400, log security event
    -> Parse webhookEvent type from payload
    -> IntegrationService.processWebhookEvent(connection, eventType, payload)
        -> If 'jira:issue_created':
            -> Find IntegrationProject by Jira project ID
            -> If not found or not enabled: skip
            -> Create ExternalTaskMapping
            -> Create Solidtime Task
        -> If 'jira:issue_updated':
            -> Find ExternalTaskMapping by Jira issue ID
            -> Update mapping fields
            -> Update Solidtime Task (name, done_at)
        -> If 'jira:issue_deleted':
            -> Find ExternalTaskMapping by Jira issue ID
            -> Set is_active = false
    -> Create IntegrationSyncLog entry (type = 'webhook')
    -> Return 200 { received: true }
```

### 16.4 Time Entry Export Flow

```
ExportTimeEntriesJob::handle()
    -> Verify connection.sync_direction includes 'export'
    -> Query TimeEntry WHERE task_id IN (mapped task IDs)
        AND end IS NOT NULL
        AND updated_at > last_export_timestamp
    -> For each entry (batch of 50):
        -> Look up ExternalTaskMapping for the task
        -> Prepare entry data:
            -> external_task_id from mapping
            -> seconds from (end - start)
            -> description from time entry
            -> started_at from time entry start
        -> adapter.postTimeEntry(connection, entryData)
            -> Jira: POST /issue/{id}/worklog
            -> Asana: POST /tasks/{gid}/stories (comment)
            -> Trello: POST /cards/{id}/actions/comments
        -> On success: mark entry as exported in sync log
        -> On failure: log error, increment retry count
    -> Create IntegrationSyncLog entry (type = 'export')
```

---

## 17. File Modification Risk Assessment

### 17.1 Risk Matrix

| File | Change Type | Risk | Rationale |
|------|------------|:----:|-----------|
| `routes/api.php` | Add 3 route groups + webhook routes | **Medium** | Adding multiple groups; webhook routes outside auth middleware require careful placement |
| `routes/web.php` | Add Inertia route | Low | Appending single route, no existing code modified |
| `resources/js/Layouts/AppLayout.vue` | Add nav item | Low | Adding one `NavigationSidebarItem` in settings section |
| `app/Providers/JetstreamServiceProvider.php` | Add `IntegrationPermissions::register()` | Low | Single line addition after `CorePermissions::register()` |
| `openapi.json` | Add 13 endpoint definitions | Low | Appending new paths, no existing paths modified |
| `resources/js/packages/api/src/openapi.json.client.ts` | Regenerate | Low | Auto-generated file, full replacement |

### 17.2 Merge Conflict Assessment

**Risk: LOW-MEDIUM**

This feature creates mostly new files (53 new files vs. 6 modified files). The primary conflict risk is in `routes/api.php`, which multiple features may modify concurrently. The modifications are additions (new route groups) at the end of the route file, which reduces conflict probability.

**Specific conflict points**:
- `routes/api.php`: Other features adding route groups in the same block. Mitigation: place integration routes in a clearly delimited section.
- `app/Providers/JetstreamServiceProvider.php`: If another feature also adds permission registration. Mitigation: each feature adds a single line, merge resolution is simple.
- `resources/js/Layouts/AppLayout.vue`: If another feature adds navigation items. Mitigation: nav items are independent additions.

### 17.3 Dependency on Feature 00 (Weekly Timesheet Grid)

Feature 16 does NOT depend on Feature 00 being merged first. However, PMI-030 (add external reference badges to task selector and time entry UI) may touch components that were introduced or modified by Feature 00. If Feature 00 is on a separate branch:
- The `TimesheetRowHeader.vue` component (from Feature 00) could show external reference badges
- The task selector components used in the timesheet grid could show external references
- These are additive changes and can be resolved during merge

**Recommendation**: Merge Feature 00 before starting Feature 16 frontend work to avoid rebasing complexity.

### 17.4 New Dependencies (composer/npm)

This feature likely requires new Composer packages:
- `league/oauth2-client` (^2.x) -- OAuth 2.0 client library
- Provider-specific packages (optional): `mrjoops/oauth2-jira`, `haydenpierce/oauth2-asana`

No new npm packages are expected (Guzzle is already available for HTTP requests in PHP; frontend uses the existing API client).

**Risk**: Adding new Composer dependencies requires review for security and license compatibility.
