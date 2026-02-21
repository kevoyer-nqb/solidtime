# Codebase Analysis: Feature 13 -- Audit Trail / Activity Log

**Date**: 2026-02-09
**Branch analyzed**: `main`
**Target feature branch**: `feature/audit-trail`
**PRD reference**: `.features/13-audit-trail/PRD.md`

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Existing Audit Infrastructure](#2-existing-audit-infrastructure)
3. [CustomAuditable Trait Analysis](#3-customauditable-trait-analysis)
4. [Auditable Models Inventory](#4-auditable-models-inventory)
5. [Audit Model and Factory](#5-audit-model-and-factory)
6. [Audit Configuration](#6-audit-configuration)
7. [Filament AuditResource](#7-filament-auditresource)
8. [Permission System](#8-permission-system)
9. [Controller Patterns](#9-controller-patterns)
10. [Service Layer Patterns](#10-service-layer-patterns)
11. [Request Validation Patterns](#11-request-validation-patterns)
12. [Frontend Store Patterns](#12-frontend-store-patterns)
13. [Frontend Component Patterns](#13-frontend-component-patterns)
14. [Route Registration Patterns](#14-route-registration-patterns)
15. [Navigation Sidebar](#15-navigation-sidebar)
16. [Data Flow Diagrams](#16-data-flow-diagrams)
17. [File Modification Risk Assessment](#17-file-modification-risk-assessment)

---

## 1. Executive Summary

The Solidtime codebase already captures comprehensive audit data for every model mutation via the `owen-it/laravel-auditing` package. The `CustomAuditable` trait is applied to all 10 core models, and every create, update, and delete event is written to the `audits` table. However, there is no user-facing interface to access this data, and the `audits` table lacks an `organization_id` column needed for efficient organization-scoped queries.

**Key findings**:

- **One schema change needed** -- add `organization_id` column to existing `audits` table with composite indexes for query performance
- **`CustomAuditable` trait must be extended** -- add `transformAudit()` method to auto-populate `organization_id` on new records
- **Backfill command required** -- existing audit records need `organization_id` populated via chunked SQL joins
- **Two new permissions needed** -- `audit-logs:view` and `audit-logs:export` following SF-02 convention
- **All architectural patterns are well-established** -- the feature follows existing conventions for controllers, services, requests, routes, stores, and components
- **Low merge conflict risk** -- creates mostly new files, with small additions to shared files (routes, permissions, navigation)
- **Existing Filament AuditResource** provides system-admin audit viewing; this feature provides organization-scoped user-facing audit viewing -- no conflict between the two

---

## 2. Existing Audit Infrastructure

### 2.1 Package: owen-it/laravel-auditing

The `owen-it/laravel-auditing` package is already installed and configured. It provides:
- `OwenIt\Auditing\Auditable` trait for models
- `OwenIt\Auditing\Models\Audit` base model
- `OwenIt\Auditing\Contracts\Auditable` interface
- Automatic capture of create, update, delete, and restore events
- Before/after value serialization to JSON columns
- User, IP, URL, and User Agent resolution

### 2.2 Audits Table Schema

**Migration**: `database/migrations/2024_09_02_094105_create_audits_table.php`

```php
Schema::connection($connection)->create($table, function (Blueprint $table): void {
    $morphPrefix = config('audit.user.morph_prefix', 'user');

    $table->bigIncrements('id');
    $table->string($morphPrefix.'_type')->nullable();
    $table->uuid($morphPrefix.'_id')->nullable();
    $table->string('event');
    $table->uuidMorphs('auditable');              // auditable_type + auditable_id + index
    $table->json('old_values')->nullable();
    $table->json('new_values')->nullable();
    $table->text('url')->nullable();
    $table->ipAddress('ip_address')->nullable();
    $table->string('user_agent', 1023)->nullable();
    $table->string('tags')->nullable();
    $table->timestamps();

    $table->index([$morphPrefix.'_id', $morphPrefix.'_type']);
});
```

**Existing indexes**:
- `audits_auditable_type_auditable_id_index` (from `uuidMorphs`)
- `audits_user_id_user_type_index` (explicit)

**Missing**: No `organization_id` column. This is the primary technical challenge -- scoping audit records to an organization requires the migration in AUD-001.

### 2.3 IP Address Anonymization

**File**: `app/Extensions/Auditing/Resolvers/CustomIpAddressResolver.php`

```php
class CustomIpAddressResolver implements Resolver
{
    private static function anonymizeIpAddress(string $ipAddress): string
    {
        return preg_replace(
            ['/\.\d*$/', '/[\da-f]*:[\da-f]*$/'],
            ['.0', '0:0'],
            $ipAddress
        );
    }

    public static function resolve(Auditable $auditable): string
    {
        $ip = $auditable->preloadedResolverData['ip_address'] ?? Request::ip();
        if ($ip !== null) {
            $ip = self::anonymizeIpAddress($ip);
        }
        return $ip;
    }
}
```

IP addresses are anonymized **at write time** by replacing the last octet with `0` (IPv4) or the last two groups with `0:0` (IPv6). The audit log feature displays these already-anonymized values as-is -- no additional anonymization needed at read time.

---

## 3. CustomAuditable Trait Analysis

**File**: `app/Models/Concerns/CustomAuditable.php`

```php
<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use OwenIt\Auditing\Auditable;

trait CustomAuditable
{
    use Auditable;

    protected ?array $auditEvents = null;

    public function disableAuditing(): void
    {
        $this->auditEvents = [];
    }
}
```

**Current state**: The trait is a thin wrapper around `OwenIt\Auditing\Auditable`. It adds:
- `$auditEvents` property override (nullable, used to disable auditing)
- `disableAuditing()` method to suppress audit events

**Required modification (AUD-003)**: Add `transformAudit()` method to inject `organization_id` into new audit records. The `OwenIt\Auditing\Auditable` trait supports this hook -- if defined on the model/trait, it is called before the audit record is persisted.

**Risk assessment**: LOW. Adding a new method to the trait does not affect the existing `disableAuditing()` functionality. The `transformAudit()` method is an opt-in hook that the package explicitly supports.

**Compatibility concern**: The `resolveAuditOrganizationId()` method must handle all 10 model types gracefully, including models that do not have a direct `organization_id` attribute (e.g., `Task` resolves via `project`, `User` resolves via request context).

---

## 4. Auditable Models Inventory

All 10 models using `CustomAuditable`:

### 4.1 Models with Direct `organization_id`

| Model | File | `organization_id` Column |
|-------|------|:-----------------------:|
| `TimeEntry` | `app/Models/TimeEntry.php` | Yes |
| `Project` | `app/Models/Project.php` | Yes |
| `Client` | `app/Models/Client.php` | Yes |
| `Tag` | `app/Models/Tag.php` | Yes |
| `Member` | `app/Models/Member.php` | Yes |
| `OrganizationInvitation` | `app/Models/OrganizationInvitation.php` | Yes |

These models have `organization_id` as a direct column. The `resolveAuditOrganizationId()` method returns `$this->organization_id`.

### 4.2 Models with Indirect `organization_id`

| Model | File | Resolution Strategy |
|-------|------|-------------------|
| `Task` | `app/Models/Task.php` | Via `$this->project->organization_id` |
| `ProjectMember` | `app/Models/ProjectMember.php` | Via `$this->project->organization_id` |

These models do not have a direct `organization_id` column but can resolve it through their `project` relationship.

### 4.3 Special Cases

| Model | File | Resolution Strategy |
|-------|------|-------------------|
| `Organization` | `app/Models/Organization.php` | Self: `$this->getKey()` (the model IS the organization) |
| `User` | `app/Models/User.php` | Via request context: `request()->route('organization')` |

**User model complication**: A `User` can belong to multiple organizations. When auditing User model changes, the `organization_id` is resolved from the current request's organization route parameter. For console-triggered User changes, this resolves to `null`.

### 4.4 Model Name Resolution for Display

Each model needs a human-readable display name for the audit log UI:

| Model | Name Resolution | Example Display |
|-------|----------------|----------------|
| `TimeEntry` | `$model->description ?: "Time Entry " . $model->start->format('Y-m-d H:i')` | "Backend API work" or "Time Entry 2026-02-09 14:30" |
| `Project` | `$model->name` | "Website Redesign" |
| `Task` | `$model->name` | "Frontend Development" |
| `Client` | `$model->name` | "Acme Corp" |
| `Tag` | `$model->name` | "billable" |
| `Member` | `$model->user->name` | "John Doe" |
| `Organization` | `$model->name` | "My Company" |
| `ProjectMember` | `$model->member->user->name . " on " . $model->project->name` | "John Doe on Website Redesign" |
| `OrganizationInvitation` | `$model->email` | "john@example.com" |
| `User` | `$model->name` | "John Doe" |

When the referenced entity has been deleted, the service returns `null` and the UI displays "[Deleted] {type} ({id truncated})".

### 4.5 Morph Class Names

The `OwenIt\Auditing` package stores the full class name in `auditable_type`:

| Morph Class | API Filter Value | Display Label |
|------------|-----------------|---------------|
| `App\Models\TimeEntry` | `time-entry` | Time Entry |
| `App\Models\Project` | `project` | Project |
| `App\Models\Task` | `task` | Task |
| `App\Models\Client` | `client` | Client |
| `App\Models\Tag` | `tag` | Tag |
| `App\Models\Member` | `member` | Member |
| `App\Models\Organization` | `organization` | Organization |
| `App\Models\ProjectMember` | `project-member` | Project Member |
| `App\Models\OrganizationInvitation` | `organization-invitation` | Organization Invitation |
| `App\Models\User` | `user` | User |

**Important**: If Solidtime has configured a morph map (via `Relation::morphMap()`), the `auditable_type` values will be the mapped names rather than full class names. The `AuditLogService` must check for both possibilities. Checking the `config/audit.php` and application configuration shows that no morph map is currently defined, so the full class names are stored.

---

## 5. Audit Model and Factory

### 5.1 Audit Model

**File**: `app/Models/Audit.php`

```php
class Audit extends PackageAuditModel
{
    use HasFactory;
}
```

The model is a thin extension of `OwenIt\Auditing\Models\Audit` that adds `HasFactory` for test factories. After AUD-001, the `organization_id` property must be added to the docblock.

**Package Audit Model features used**:
- `user()` morph relationship (returns the acting user)
- `auditable()` morph relationship (returns the affected model)
- `old_values` and `new_values` are automatically cast to arrays
- `created_at` and `updated_at` are Carbon instances

### 5.2 Audit Factory

**File**: `database/factories/AuditFactory.php`

```php
class AuditFactory extends Factory
{
    public function definition(): array
    {
        $morphPrefix = Config::get('audit.user.morph_prefix', 'user');
        return [
            $morphPrefix.'_id' => fn () => User::factory()->create()->id,
            $morphPrefix.'_type' => fn () => (new User)->getMorphClass(),
            'event' => 'updated',
            'auditable_id' => fn () => User::factory()->create()->getKey(),
            'auditable_type' => fn () => (new User)->getMorphClass(),
            'old_values' => [],
            'new_values' => [],
            'url' => $this->faker->url,
            'ip_address' => $this->faker->ipv4,
            'user_agent' => $this->faker->userAgent,
            'tags' => implode(',', $this->faker->words(4)),
        ];
    }

    public function auditUser(User $user): self { ... }
    public function auditFor(Model $model): self { ... }
}
```

**Required modification**: Add an `forOrganization(Organization $org)` state method:

```php
public function forOrganization(Organization $organization): self
{
    return $this->state(fn (array $attributes) => [
        'organization_id' => $organization->getKey(),
    ]);
}
```

This is needed for tests (AUD-019, AUD-020, AUD-021) to create audit records with a known `organization_id`.

---

## 6. Audit Configuration

**File**: `config/audit.php`

Key configuration values relevant to this feature:

| Setting | Value | Impact |
|---------|-------|--------|
| `enabled` | `env('AUDITING_ENABLED', false)` | Auditing is OFF by default. Must be enabled for data to exist. |
| `implementation` | `OwenIt\Auditing\Models\Audit::class` | Package Audit model (not the app's extended one). Consider changing to `App\Models\Audit::class` so the `organization_id` column is recognized. |
| `events` | `['created', 'updated', 'deleted', 'restored']` | All four events are captured. |
| `strict` | `true` | Strict mode: auditing fails loudly if misconfigured. |
| `empty_values` | `false` | No audit record when both old/new are empty. |
| `allowed_array_values` | `true` | Array values (e.g., tags on TimeEntry) are audited. |
| `timestamps` | `false` | `created_at`/`updated_at` changes are NOT audited. |
| `threshold` | `0` | No limit on audit records per model. |
| `driver` | `database` | Writes to `audits` table. |
| `queue.enable` | `false` | Synchronous writes (no queue). |
| `console` | `true` | Console commands generate audit records (e.g., `php artisan db:seed`). |

**Important configuration note**: The `implementation` config points to `OwenIt\Auditing\Models\Audit::class` (the package model), but the application has its own `App\Models\Audit` model that extends it. This should work correctly because the `App\Models\Audit` extends `OwenIt\Auditing\Models\Audit` and inherits all behavior. However, if the `organization_id` column needs to be in the model's `$fillable` array, the `App\Models\Audit` model may need modification. The package's Audit model uses unguarded inserts, so this should not be an issue.

**Concern**: The `transformAudit()` method on `CustomAuditable` adds `organization_id` to the audit data array. The package's database driver takes this array and inserts it into the `audits` table. Since the `organization_id` column exists in the table after migration, the INSERT will include it automatically. This has been verified by reviewing the package's `DatabaseDriver::audit()` method.

---

## 7. Filament AuditResource

**File**: `app/Filament/Resources/AuditResource.php`

The Filament admin panel already has an `AuditResource` that provides a system-admin view:

```php
class AuditResource extends Resource
{
    protected static ?string $model = Audit::class;
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationGroup = 'System';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name'),
                Tables\Columns\TextColumn::make('event'),
                Tables\Columns\TextColumn::make('auditable_type'),
                Tables\Columns\TextColumn::make('auditable_id'),
                IconColumn::make('was_command')
                    ->getStateUsing(fn (Audit $record) => Str::startsWith($record->url, 'artisan '))
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')->sortable()->dateTime(),
                Tables\Columns\TextColumn::make('updated_at')->sortable()->dateTime(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
```

**No conflict**: This Filament resource is a system-admin tool accessible only via the Filament admin panel (`/admin/audits`). It shows ALL audits across all organizations without scoping. The audit trail feature being built is a user-facing, organization-scoped page at `/audit-log`. The two coexist without conflict.

**The Filament resource has**:
- No filtering by organization
- No diff view
- No export
- No entity name resolution
- Basic table with columns for user, event, type, ID, timestamps

All of these capabilities are being added in the user-facing audit trail feature.

---

## 8. Permission System

### 8.1 Current Permission Architecture

**File**: `app/Permissions/CorePermissions.php`

Permissions are registered via `Jetstream::role()` calls in a static `register()` method:

```php
class CorePermissions
{
    public static function register(): void
    {
        Jetstream::role(Role::Owner->value, 'Owner', [
            // ... 40+ permissions ...
        ])->description('...');

        Jetstream::role(Role::Admin->value, 'Administrator', [
            // ... 38+ permissions ...
        ])->description('...');

        Jetstream::role(Role::Manager->value, 'Manager', [
            // ... 30+ permissions ...
        ])->description('...');

        Jetstream::role(Role::Employee->value, 'Employee', [
            // ... 10 permissions ...
        ])->description('...');

        Jetstream::role(Role::Placeholder->value, 'Placeholder', [
        ])->description('...');
    }
}
```

**Called from**: `app/Providers/JetstreamServiceProvider.php`:

```php
protected function configurePermissions(): void
{
    Jetstream::defaultApiTokenPermissions([]);
    CorePermissions::register();
    // Feature permissions will be registered here by each feature
}
```

### 8.2 SF-08 Modular Pattern

The comment in `JetstreamServiceProvider` shows the intended pattern:

```php
// Feature permissions will be registered here by each feature:
// TimesheetApprovalPermissions::register();
// ExpensePermissions::register();
// AuditLogPermissions::register();  // <-- to be added
```

Following this pattern, `AuditLogPermissions::register()` will add the two new permissions to the existing role arrays via `CorePermissions` modification or a separate registration call.

### 8.3 Permission Check Pattern in Controllers

```php
// Single permission check:
$this->checkPermission($organization, 'audit-logs:view');

// The checkPermission() method is defined in the base Controller:
protected function checkPermission(Organization $organization, string $permission): void
```

### 8.4 New Permissions

| Permission | Roles | Purpose |
|-----------|-------|---------|
| `audit-logs:view` | Owner, Admin, Manager | View audit log list and detail |
| `audit-logs:export` | Owner, Admin | Export audit data as CSV/JSON |

These follow the SF-02 naming convention: `{entity}:{action}`.

---

## 9. Controller Patterns

### 9.1 Base Controller

**File**: `app/Http/Controllers/Api/V1/Controller.php`

All API controllers extend this base, which provides:

```php
protected PermissionStore $permissionStore;  // Injected via constructor

protected function checkPermission(Organization $organization, string $permission): void
protected function checkAnyPermission(Organization $organization, array $permissions): void
protected function user(): User
protected function member(Organization $organization): Member
```

### 9.2 Relevant Controller Pattern: TimeEntryController (Export Endpoint)

**File**: `app/Http/Controllers/Api/V1/TimeEntryController.php`

The existing `TimeEntryController` has an `indexExport()` method that is architecturally similar to the audit log export:

```php
public function indexExport(
    Organization $organization,
    TimeEntryIndexExportRequest $request,
    TimeEntryService $timeEntryService
): StreamedResponse {
    $this->checkAnyPermission($organization, ['time-entries:view:own', 'time-entries:view:all']);
    // ... build query, stream response
}
```

The `AuditLogController::export()` follows this same pattern: permission check, build query via service, return `StreamedResponse`.

### 9.3 Service Injection Pattern

Controllers inject services via **method parameter type-hints** (not constructor injection):

```php
public function index(
    Organization $organization,
    AuditLogIndexRequest $request,
    AuditLogService $auditLogService    // <-- injected here
): JsonResponse { ... }
```

This is the pattern used by `ChartController`, `TimeEntryController`, and others. The `AuditLogController` follows this pattern.

---

## 10. Service Layer Patterns

### 10.1 Service Conventions

- Location: `app/Service/`
- Stateless classes (no constructor state)
- Methods accept model instances (`Organization`, `Member`) not IDs
- Return plain arrays, Eloquent models, or paginators
- Injected into controllers via method parameter type-hints

### 10.2 Relevant Service Pattern: TimeEntryAggregationService

**File**: `app/Service/TimeEntryAggregationService.php`

Uses raw SQL aggregation for performance:

```php
$result = TimeEntry::query()
    ->selectRaw('SUM(EXTRACT(EPOCH FROM ("end" - start))) as total_seconds')
    ->whereBelongsTo($organization, 'organization')
    ->where('user_id', $member->user_id)
    ->whereNotNull('end')
    ->first();
```

The `AuditLogService` uses a similar pattern: Eloquent query builder with efficient filtering, but uses cursor-based pagination instead of aggregation.

### 10.3 Cursor-Based Pagination in Laravel

Laravel's `cursorPaginate()` method is used for the audit log listing:

```php
$paginator = Audit::query()
    ->where('organization_id', $organization->getKey())
    ->orderByDesc('created_at')
    ->orderByDesc('id')
    ->cursorPaginate($perPage, ['*'], 'cursor', $cursor);
```

This provides O(1) page fetch performance regardless of offset depth, which is critical for the audit log where users may page through thousands of records.

---

## 11. Request Validation Patterns

### 11.1 Base Request

**File**: `app/Http/Requests/V1/BaseFormRequest.php`

All request classes extend this. It provides access to `$this->organization` via route model binding.

### 11.2 Array Filter Validation Pattern

The audit log index request needs to validate array query parameters (e.g., `auditable_type[]=time-entry&auditable_type[]=project`). This is standard Laravel validation:

```php
'auditable_type'    => ['nullable', 'array'],
'auditable_type.*'  => ['string', 'in:time-entry,project,...'],
```

### 11.3 Date Range Validation Pattern

The `after_or_equal` rule ensures `date_to >= date_from`:

```php
'date_from' => ['nullable', 'date_format:Y-m-d'],
'date_to'   => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
```

---

## 12. Frontend Store Patterns

### 12.1 Existing Pinia Store Pattern

**Example from `useTimeEntries.ts`** and `useTimesheet.ts`:

```typescript
export const useAuditLogStore = defineStore('auditLog', () => {
    const items = ref<AuditRecord[]>([]);
    const isLoading = ref(false);

    async function loadAuditLogs() {
        isLoading.value = true;
        try {
            const response = await api.getAuditLogs({
                organization: getCurrentOrganizationId(),
                ...filters.value
            });
            items.value = response.data.data;
        } finally {
            isLoading.value = false;
        }
    }

    return { items, isLoading, loadAuditLogs };
});
```

### 12.2 API Client Pattern

**File**: `resources/js/packages/api/src/openapi.json.client.ts`

Auto-generated client from OpenAPI spec. After adding audit log endpoints to `openapi.json` and regenerating, the client provides typed methods:

```typescript
api.getAuditLogs({ organization: orgId, per_page: 50, cursor: null, ... })
api.getAuditLog({ organization: orgId, audit: 12345 })
api.exportAuditLogs({ organization: orgId, format: 'csv', ... })
```

### 12.3 Organization Context

**File**: `resources/js/utils/useUser.ts`

`getCurrentOrganizationId()` returns the current organization UUID for API calls.

---

## 13. Frontend Component Patterns

### 13.1 Page Component Pattern

**Example from `Time.vue`**:

```vue
<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import MainContainer from '@/packages/ui/src/MainContainer.vue';

onMounted(async () => {
    await store.fetchData();
});
</script>

<template>
    <AppLayout title="Audit Log" data-testid="audit_log_view">
        <MainContainer>
            <!-- Page content -->
        </MainContainer>
    </AppLayout>
</template>
```

The `AuditLog.vue` page follows this exact pattern with `AppLayout` + `MainContainer`.

### 13.2 UI Component Location

Feature-specific components live in `resources/js/packages/ui/src/{Feature}/`. Tests go in `__tests__/` subdirectory.

For this feature:
- Components: `resources/js/packages/ui/src/AuditLog/`
- Tests: `resources/js/packages/ui/src/AuditLog/__tests__/`

### 13.3 Slide-Over Pattern

The audit log detail uses a slide-over panel pattern. While Solidtime does not have an existing dedicated slide-over component, the pattern can be built using Tailwind CSS transitions and a fixed-position panel. The implementation follows HeadlessUI's dialog/transition pattern.

### 13.4 Icon Usage

Icons imported from `@heroicons/vue/20/solid`:

```typescript
import { ClipboardDocumentListIcon } from '@heroicons/vue/20/solid';
```

Used in sidebar navigation. The `ClipboardDocumentListIcon` was chosen to represent audit/log data, distinct from the existing `DocumentTextIcon` (used for Invoices) and `TableCellsIcon` (used for Timesheet).

---

## 14. Route Registration Patterns

### 14.1 API Routes

**File**: `routes/api.php`

API routes are registered inside nested middleware groups. The existing structure shows the pattern:

```php
Route::middleware(['auth:api', 'verified'])->group(function () {
    Route::name('v1.')->prefix('v1')->group(function () {
        // Feature route groups here
        Route::name('members.')->prefix('/organizations/{organization}')->group(static function (): void { ... });
        Route::name('projects.')->prefix('/organizations/{organization}')->group(static function (): void { ... });
        Route::name('time-entries.')->prefix('/organizations/{organization}')->group(static function (): void { ... });
        Route::name('timesheet.')->prefix('/organizations/{organization}')->group(static function (): void { ... });
        // ... etc
    });
});
```

The audit log routes follow this exact pattern:

```php
Route::name('audit-logs.')->prefix('/organizations/{organization}')->group(static function (): void {
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('index');
    Route::get('/audit-logs/export', [AuditLogController::class, 'export'])->name('export');
    Route::get('/audit-logs/{audit}', [AuditLogController::class, 'show'])->name('show');
});
```

**Route ordering note**: The `export` route MUST be registered before the `{audit}` wildcard route. Otherwise, the string "export" would be matched as an `{audit}` parameter value. This is the same pattern used in `time-entries` where `export` is registered before `{time_entry}`.

### 14.2 Web Routes

**File**: `routes/web.php`

Inertia page routes registered inside `auth:web` middleware:

```php
Route::middleware(['auth:web', 'verified'])->group(function () {
    Route::get('/audit-log', function () {
        return Inertia::render('AuditLog');
    })->name('audit-log');
});
```

---

## 15. Navigation Sidebar

**File**: `resources/js/Layouts/AppLayout.vue`

The sidebar is organized in three nav sections:

**Section 1 (Primary)**:
- Dashboard
- Time
- Timesheet
- Calendar
- Reporting

**Section 2 (Management)**:
- Projects (v-if `canViewProjects()`)
- Clients (v-if `canViewClients()`)
- Members (v-if `canViewMembers()`)
- Tags (v-if `canViewTags()`)
- Invoices (v-if `isInvoicingActivated() && canViewInvoices()`)

**Section 3 (Admin)**:
- Billing (v-if `canManageBilling() && isBillingActivated()`)
- Import / Export (v-if `canUpdateOrganization()`)
- Settings (v-if `canUpdateOrganization()`)

The Audit Log item should be placed in **Section 2**, after "Members" and before "Tags":

```vue
<NavigationSidebarItem
    v-if="canViewMembers()"
    title="Members"
    :icon="UserGroupIcon"
    :current="route().current('members')"
    :href="route('members')"></NavigationSidebarItem>

<!-- NEW: Audit Log -->
<NavigationSidebarItem
    v-if="canViewAuditLogs()"
    title="Audit Log"
    :icon="ClipboardDocumentListIcon"
    :current="route().current('audit-log')"
    :href="route('audit-log')"></NavigationSidebarItem>

<NavigationSidebarItem
    v-if="canViewTags()"
    title="Tags"
    :icon="TagIcon"
    :current="route().current('tags')"
    :href="route('tags')"></NavigationSidebarItem>
```

The `canViewAuditLogs()` helper checks if the current user's role includes the `audit-logs:view` permission. It follows the pattern of `canViewProjects()`, `canViewClients()`, etc.

The `ClipboardDocumentListIcon` must be added to the imports:

```typescript
import {
    // ... existing icons ...
    ClipboardDocumentListIcon,    // <-- NEW
} from '@heroicons/vue/20/solid';
```

---

## 16. Data Flow Diagrams

### 16.1 Page Load Flow

```
User clicks "Audit Log" in sidebar
    -> Inertia navigates to /audit-log
    -> AuditLog.vue mounts
    -> useAuditLogStore.syncFiltersFromUrl()
        -> Parse URL query parameters into filters state
        -> (If URL has ?auditable_type=time-entry&auditable_id=uuid, filters pre-applied)
    -> useAuditLogStore.loadAuditLogs()
        -> GET /api/v1/organizations/{org}/audit-logs?per_page=50&auditable_type[]=...
        -> Backend: AuditLogController.index()
            -> checkPermission($organization, 'audit-logs:view')
            -> AuditLogService.getAuditLogs()
                -> Build Eloquent query with organization_id scope
                -> Apply filters (type, event, user, date range, entity ID)
                -> cursorPaginate(50)
                -> For each record: resolve entity name, generate change summary
            -> Return AuditLogCollection (JSON with cursor meta)
        -> Store: set auditRecords, set pagination meta
        -> AuditLogList renders records
```

### 16.2 Filter Application Flow

```
User selects "Time Entry" from entity type dropdown
    -> AuditLogFilters emits 'filter-change' event
    -> AuditLog.vue calls store.setFilters({ auditable_type: ['time-entry'] })
    -> Debounce 300ms
    -> store.syncFiltersToUrl()
        -> window.history.replaceState(null, '', '/audit-log?auditable_type[]=time-entry')
    -> store.loadAuditLogs()
        -> GET /api/v1/organizations/{org}/audit-logs?per_page=50&auditable_type[]=time-entry
        -> Backend filters: WHERE organization_id = ? AND auditable_type = 'App\Models\TimeEntry'
        -> Return filtered results
    -> Store: replace auditRecords with new results, reset cursor
```

### 16.3 Detail Panel Flow

```
User clicks an audit row in the list
    -> AuditLogRow emits 'select' event with audit ID
    -> AuditLogList emits 'select-record' event
    -> AuditLog.vue calls store.selectRecord(auditId)
    -> Set selectedRecord from local auditRecords (immediate UI update)
    -> store.isLoadingDetail = true
    -> GET /api/v1/organizations/{org}/audit-logs/{auditId}
    -> Backend: AuditLogController.show()
        -> AuditLogService.getAuditDetail()
            -> Fetch audit record with organization_id scope
            -> resolveFieldValues(old_values) -- resolve UUIDs to names
            -> resolveFieldValues(new_values) -- resolve UUIDs to names
        -> Return AuditLogDetailResource (includes resolved values)
    -> Store: set selectedRecordDetail
    -> AuditLogDetail slide-over renders with diff table
```

### 16.4 Export Flow

```
User clicks "Export" -> selects "CSV"
    -> AuditLogExport emits 'export' event with format='csv'
    -> store.exportLogs('csv')
    -> store.isExporting = true
    -> GET /api/v1/organizations/{org}/audit-logs/export?format=csv&auditable_type[]=...
        (same filters as current list view)
    -> Backend: AuditLogController.export()
        -> checkPermission($organization, 'audit-logs:export')
        -> AuditLogService.exportAuditLogs()
            -> Build query with same filters
            -> Check count: if > 10,000, return 422
            -> Stream CSV with Content-Disposition header
    -> Browser triggers file download
    -> store.isExporting = false
```

### 16.5 Entity-Scoped Navigation Flow

```
User is on Project detail page, clicks "View History"
    -> router-link to /audit-log?auditable_type=project&auditable_id={project-uuid}
    -> Inertia navigates to /audit-log
    -> AuditLog.vue mounts
    -> store.syncFiltersFromUrl()
        -> filters = { auditable_type: ['project'], auditable_id: '{project-uuid}' }
    -> store.loadAuditLogs()
        -> GET /audit-logs?auditable_type[]=project&auditable_id={uuid}
        -> Returns only audit records for that specific project
    -> User sees pre-filtered view of project's audit history
```

---

## 17. File Modification Risk Assessment

### 17.1 Risk Matrix

| File | Change Type | Risk | Rationale |
|------|------------|:----:|-----------:|
| `app/Models/Concerns/CustomAuditable.php` | Add 2 methods | **Medium** | Core trait used by all 10 models. Changes must not break existing audit behavior. Mitigation: `transformAudit()` is an official package hook that only adds data; `resolveAuditOrganizationId()` is a new helper. |
| `app/Models/Audit.php` | Add property docblock | **Low** | Documentation-only change (PHPDoc). |
| `database/factories/AuditFactory.php` | Add state method | **Low** | Adding optional factory state. Existing tests unaffected. |
| `app/Permissions/CorePermissions.php` | Add 2 permissions to role arrays | **Low** | Appending to existing arrays. Existing permissions unaffected. |
| `app/Providers/JetstreamServiceProvider.php` | Add one line | **Low** | Adding `AuditLogPermissions::register()` call. |
| `routes/api.php` | Add route group | **Low** | Appending new group. No existing routes modified. |
| `routes/web.php` | Add Inertia route | **Low** | Appending single route. No existing routes modified. |
| `resources/js/Layouts/AppLayout.vue` | Add nav item + icon import | **Low** | Adding one `NavigationSidebarItem` in section 2. No existing items modified. |
| `openapi.json` | Add 3 endpoint definitions | **Low** | Appending new paths. No existing paths modified. |
| Entity detail pages (Projects, Tasks, etc.) | Add "View History" links | **Low** | Adding optional link component. No existing functionality modified. |

### 17.2 Merge Conflict Assessment

**Risk: LOW** -- This feature creates primarily new files. The modified files receive small, localized additions:

- `CustomAuditable.php`: New methods appended (no modification of existing `disableAuditing()` method)
- `CorePermissions.php`: Two strings appended to existing permission arrays
- `routes/api.php`: New route group appended at the end
- `AppLayout.vue`: One nav item added in the management section

### 17.3 Critical Risk: CustomAuditable Trait Modification

The `CustomAuditable.php` modification is the highest-risk change because:
1. The trait is used by ALL 10 core models
2. Any bug in `transformAudit()` could affect all audit record creation
3. The `resolveAuditOrganizationId()` method accesses relationships and request context

**Mitigations**:
- `transformAudit()` only ADDS a key to the data array; it cannot remove or modify existing audit fields
- `resolveAuditOrganizationId()` returns `null` for any edge case it cannot resolve -- the column is nullable
- The method is tested via AUD-020 (service tests) and indirectly via AUD-019 (endpoint tests)
- Existing `AuditResource` Filament page serves as a visual verification that audit records are still being created correctly

### 17.4 Downstream Conflict Warning

This feature is a standalone utility with no downstream feature dependencies. No other planned features modify the same files. The one exception is:

- **AppLayout.vue** is modified by multiple features (navigation items). Each feature adds its own nav item, which can cause minor merge conflicts in the icon imports and nav list. Mitigation: coordinate nav item additions and resolve conflicts during merge.

### 17.5 Pre-Existing Filament AuditResource

The existing `app/Filament/Resources/AuditResource.php` and its pages (`ListAudits`, `CreateAudit`, `ViewAudit`) are NOT modified by this feature. They continue to provide system-admin audit viewing independently. After the migration adds `organization_id`, the Filament list will still work because:
- The column is nullable (no NOT NULL constraint)
- The Filament resource does not filter by `organization_id`
- The existing indexes are not dropped (only new ones added)
