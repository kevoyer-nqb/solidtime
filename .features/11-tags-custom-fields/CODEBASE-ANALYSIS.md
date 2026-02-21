# Codebase Analysis: Feature 11 -- Tags and Custom Fields

**Date**: 2026-02-09
**Branch analyzed**: `main`
**Target feature branch**: `feature/tags-custom-fields`
**PRD reference**: `.features/11-tags-custom-fields/PRD.md`

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Existing Tag Infrastructure](#2-existing-tag-infrastructure)
3. [Existing Time Entry Tag Handling](#3-existing-time-entry-tag-handling)
4. [Controller Patterns](#4-controller-patterns)
5. [Service Layer Patterns](#5-service-layer-patterns)
6. [Request Validation Patterns](#6-request-validation-patterns)
7. [Permission System Patterns](#7-permission-system-patterns)
8. [Frontend Store Patterns](#8-frontend-store-patterns)
9. [Frontend Component Patterns](#9-frontend-component-patterns)
10. [Route Registration Patterns](#10-route-registration-patterns)
11. [Navigation Sidebar](#11-navigation-sidebar)
12. [Data Flow Diagrams](#12-data-flow-diagrams)
13. [File Modification Risk Assessment](#13-file-modification-risk-assessment)
14. [Merge Conflict Assessment](#14-merge-conflict-assessment)

---

## 1. Executive Summary

The Solidtime codebase already has a mature tag system with CRUD endpoints, a Tag model with JSON-based relationships to time entries, frontend components for tag management and selection, and permission controls. This feature builds directly on top of this existing infrastructure rather than creating a parallel system.

**Key findings**:

- **Tag model and controller are straightforward to enhance** -- adding `color` and `description` requires only schema additions, cast changes, and resource field additions
- **Organization model already has multiple boolean settings** -- `mandatory_tags` follows the established pattern (`prevent_overlapping_time_entries`, `employees_can_see_billable_rates`, etc.)
- **Permission system is modular** -- `CorePermissions.php` makes it easy to add new permission groups
- **Custom fields are entirely new** -- no existing custom field infrastructure; models, service, controller, and frontend are all greenfield
- **Time entry request validation is the highest-risk modification** -- `TimeEntryStoreRequest` and `TimeEntryUpdateRequest` are complex files with many validation rules and will need careful additions for mandatory tags and custom field validation
- **Frontend tag components are simple** -- `TagBadge`, `TagDropdown`, `TagCreateModal`, `TagTable`, `TagTableRow` are all concise and easy to enhance
- **Merge conflict risk is LOW to MEDIUM** -- most changes are additive; the main risk area is `TimeEntryStoreRequest`/`TimeEntryUpdateRequest` if other features modify these simultaneously

---

## 2. Existing Tag Infrastructure

### 2.1 Tag Model

**File**: `app/Models/Tag.php`

```php
class Tag extends Model implements AuditableContract
{
    use CustomAuditable;
    use HasFactory;
    use HasJsonRelationships;
    use HasUuids;

    protected $casts = [
        'name' => 'string',
    ];
}
```

Key characteristics:
- Uses `HasUuids` trait (UUID primary keys via `gen_random_uuid()`)
- Uses `CustomAuditable` trait (audit logging on all mutations)
- Uses `HasJsonRelationships` trait from `staudenmeir/eloquent-json-relations` (enables JSON-based relationships)
- Single `belongsTo` relationship to Organization
- `timeEntries()` uses `hasManyJson(TimeEntry::class, 'tags')` -- reverse relationship from the JSONB tags array on time entries
- Properties: `id`, `name`, `organization_id`, `created_at`, `updated_at`

**Enhancement needed**: Add `color` (string, default `#808080`) and `description` (string, nullable) to `$casts`. Add PHPDoc properties.

### 2.2 Tag Database Schema

**File**: `database/migrations/2024_01_20_110452_create_tags_table.php`

```php
Schema::create('tags', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->string('name', 255);
    $table->uuid('organization_id');
    $table->foreign('organization_id')
        ->references('id')
        ->on('organizations')
        ->cascadeOnUpdate()
        ->restrictOnDelete();
    $table->timestamps();
    $table->index('created_at');
});
```

No unique constraint on `(organization_id, name)` at the database level -- uniqueness is enforced via `UniqueEloquent` in request validation. The new `custom_fields` table should follow the same pattern but also add a database-level unique constraint for safety.

### 2.3 Tag Factory

**File**: `database/factories/TagFactory.php`

```php
class TagFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'organization_id' => Organization::factory(),
        ];
    }

    public function forOrganization(Organization $organization): self { /* ... */ }
    public function randomCreatedAt(): self { /* ... */ }
}
```

**Enhancement needed**: Add `color` (random hex or default) and `description` (optional faker sentence) to `definition()`.

### 2.4 TagController

**File**: `app/Http/Controllers/Api/V1/TagController.php`

4 methods: `index`, `store`, `update`, `destroy`.

Key patterns observed:
- Custom `checkPermission` override that also validates tag belongs to organization
- `index()` uses paginated query with `orderBy('created_at', 'desc')`
- `store()` creates tag with `name` only, associates organization
- `update()` sets `name` only
- `destroy()` checks if tag is in use via `TimeEntry::query()->hasTag($tag)` before deletion; throws `EntityStillInUseApiException` if in use

**Enhancement needed**:
- `store()` and `update()`: handle `color` and `description`
- `index()`: support `with_usage_counts` query parameter
- New `bulkDestroy()` method with `TagBulkDeleteRequest`
- Inject `TagService` via constructor (currently no constructor)

### 2.5 Tag Request Validation

**File**: `app/Http/Requests/V1/Tag/TagStoreRequest.php`

```php
public function rules(): array
{
    return [
        'name' => [
            'required', 'string', 'min:1', 'max:255',
            UniqueEloquent::make(Tag::class, 'name', function (Builder $builder): Builder {
                return $builder->whereBelongsTo($this->organization, 'organization');
            })->withCustomTranslation('validation.tag_name_already_exists'),
        ],
    ];
}
```

Uses `$this->organization` from route model binding (inherited from `BaseFormRequest`).
Uses `UniqueEloquent` from `korridor/laravel-model-validation-rules` with org scoping.

**Enhancement needed**: Add `color` (regex validated hex) and `description` (nullable, max:500) rules.

**File**: `app/Http/Requests/V1/Tag/TagUpdateRequest.php`

Same pattern as store but with `->ignore($this->tag?->getKey())` on the unique rule. Uses `$this->tag` from route model binding.

### 2.6 Tag Resource

**File**: `app/Http/Resources/V1/Tag/TagResource.php`

```php
class TagResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'created_at' => $this->formatDateTime($this->resource->created_at),
            'updated_at' => $this->formatDateTime($this->resource->updated_at),
        ];
    }
}
```

Extends `BaseResource` which provides `formatDateTime()`.

**Enhancement needed**: Add `color`, `description`, and conditional `time_entry_count` fields.

### 2.7 Tag Collection

**File**: `app/Http/Resources/V1/Tag/TagCollection.php`

```php
class TagCollection extends ResourceCollection
{
    public $collects = TagResource::class;
}
```

Minimal wrapper. No changes needed.

---

## 3. Existing Time Entry Tag Handling

### 3.1 TimeEntry Model Tag Storage

**File**: `app/Models/TimeEntry.php`

Tags are stored as a JSONB array of UUID strings on the `time_entries` table:

```php
protected $casts = [
    'tags' => 'array',
    // ...
];
```

Relationship for reverse lookup:
```php
public function tagsRelation(): BelongsToJson
{
    return $this->belongsToJson(Tag::class, 'tags');
}
```

Query scope for filtering:
```php
public function scopeHasTag(Builder $builder, Tag $tag): void
{
    $builder->whereJsonContains('tags', $tag->getKey());
}
```

### 3.2 TimeEntryFilter Tag Support

**File**: `app/Service/TimeEntryFilter.php`

```php
public function addTagIdsFilter(?array $tagIds): self
{
    if ($tagIds === null) {
        return $this;
    }
    $this->builder->where(function (Builder $builder) use ($tagIds): void {
        // Filters entries that contain ANY of the given tag IDs
    });
    return $this;
}
```

This filter already supports the `tag_ids` query parameter on time entry list endpoints. The report UI just needs to expose this filter.

### 3.3 TimeEntryAggregationService Tag Grouping

**File**: `app/Enums/TimeEntryAggregationType.php`

Includes `Tag` as an aggregation type. The `TimeEntryAggregationService` uses `jsonb_array_elements_text()` to expand the tags array for grouping. This is already functional -- no backend changes needed for report grouping.

### 3.4 Time Entry Request Validation for Tags

**File**: `app/Http/Requests/V1/TimeEntry/TimeEntryStoreRequest.php`

```php
'tags' => ['nullable', 'array'],
'tags.*' => [
    ExistsEloquent::make(Tag::class, null, function (Builder $builder): Builder {
        return $builder->whereBelongsTo($this->organization, 'organization');
    })->uuid(),
],
```

Tags are validated as an array of UUIDs that must exist in the organization's tags.

**Enhancement needed for mandatory tags**: When `$this->organization->mandatory_tags` is true and the entry is not a running timer, change `'tags'` rule from `'nullable'` to `'required', 'array', 'min:1'`.

**File**: `app/Http/Requests/V1/TimeEntry/TimeEntryUpdateRequest.php`

Same tag validation pattern. Enhancement needed is the same.

### 3.5 TimeEntryResource Tag Output

**File**: `app/Http/Resources/V1/TimeEntry/TimeEntryResource.php`

```php
'tags' => $this->resource->tags ?? [],
```

Returns raw array of tag UUID strings. No tag name resolution in the standard response (names are resolved client-side by matching against the tags store).

**Enhancement needed**: Add `custom_fields` array to the response, which includes field metadata and values.

---

## 4. Controller Patterns

### 4.1 Base Controller

**File**: `app/Http/Controllers/Api/V1/Controller.php`

All API controllers extend this base, which provides:
```php
protected PermissionStore $permissionStore;
protected function checkPermission(Organization $organization, string $permission): void
protected function checkAnyPermission(Organization $organization, array $permissions): void
protected function user(): User
protected function member(Organization $organization): Member
```

### 4.2 Controller Pattern Consistency

The `TagController` follows the standard pattern for entity controllers:
- Override `checkPermission` to add entity-belongs-to-organization validation
- Inject service via constructor (when needed -- TagController currently has no constructor)
- Use `new EntityResource($model)` for responses
- Use `response()->json(null, 204)` for deletes

The new `CustomFieldController` should follow this exact pattern, matching `TagController` structure closely since it manages a similarly-scoped organizational entity.

### 4.3 Organization Injection

Organization is resolved via route model binding from the `{organization}` URL parameter. All tag and custom field endpoints are under `/organizations/{organization}/`.

---

## 5. Service Layer Patterns

### 5.1 Service Conventions in Solidtime

- Location: `app/Service/`
- Stateless classes (no constructor state beyond injected dependencies)
- Methods accept model instances (Organization, Member, etc.) not IDs
- Return plain arrays, models, or collections
- Injected into controllers via constructor type-hints
- No interface abstractions (concrete classes only)

### 5.2 Relevant Existing Services

**TimeEntryAggregationService**: Complex aggregation logic using raw SQL for performance. Good reference for `TagService::getTagsWithUsageCounts()` which needs similar JSONB query patterns.

**BillableRateService**: Service that is called from models (computed attributes). Pattern reference for services accessed from multiple points.

### 5.3 No Existing TagService

There is currently no `TagService`. All tag logic lives directly in `TagController`. The new feature introduces `TagService` for:
- Usage count queries (too complex for a controller method)
- Bulk delete operations (involves multi-step logic with JSONB manipulation)
- Tag removal from time entries (reusable operation)

---

## 6. Request Validation Patterns

### 6.1 BaseFormRequest

**File**: `app/Http/Requests/V1/BaseFormRequest.php`

All request classes extend this base. It provides:
- Access to `$this->organization` via route model binding
- Standard authorization logic

### 6.2 Key Patterns Observed

1. **UniqueEloquent with org scoping**: Used in `TagStoreRequest`, `TagUpdateRequest`. Should be used for `CustomFieldStoreRequest` and `CustomFieldUpdateRequest` for field name uniqueness.

2. **ExistsEloquent with org scoping**: Used in `TimeEntryStoreRequest` for `project_id`, `task_id`, `tags.*`. Should be used for custom field ID validation.

3. **Route model binding in request**: `$this->organization` (from `{organization}` route param), `$this->tag` (from `{tag}` route param). Custom field requests will use `$this->customField` from `{customField}` route param.

4. **Conditional validation**: `TimeEntryStoreRequest` uses `'required_with:task_id'` for `project_id`. Mandatory tags will need similar conditional logic based on `$this->organization->mandatory_tags`.

### 6.3 Time Entry Validation Complexity

`TimeEntryStoreRequest` and `TimeEntryUpdateRequest` are the most complex request classes in the codebase. They validate:
- `member_id` with org-scoped existence check
- `project_id` with org-scoped existence check and permission-based visibility filtering
- `task_id` with both org-scoped existence and project-scoped existence
- `start`/`end` datetime formats
- `billable` boolean
- `description` string
- `tags` array with per-item existence validation

Adding mandatory tags and custom field validation to these files requires careful placement to avoid breaking existing validation logic.

---

## 7. Permission System Patterns

### 7.1 Permission Registration

**File**: `app/Permissions/CorePermissions.php`

Permissions are registered per role using `Jetstream::role()`. Each role gets an explicit array of permission strings.

Current tag permissions:
```
Owner:    tags:view, tags:create, tags:update, tags:delete
Admin:    tags:view, tags:create, tags:update, tags:delete
Manager:  tags:view, tags:create, tags:update, tags:delete
Employee: tags:view
```

Pattern for new custom field permissions:
```
Owner:    custom-fields:view, custom-fields:create, custom-fields:update, custom-fields:delete
Admin:    custom-fields:view, custom-fields:create, custom-fields:update, custom-fields:delete
Manager:  custom-fields:view
Employee: custom-fields:view
```

### 7.2 Permission Checking in Controllers

Controllers call `$this->checkPermission($organization, 'permission-name')` which delegates to `PermissionStore`. The permission store checks if the current user's role in the organization includes the requested permission.

### 7.3 Frontend Permission Helpers

**File**: `resources/js/utils/permissions.ts`

Contains helper functions like `canCreateTags()`, `canDeleteTags()`, `canViewTags()`. New functions needed:
- `canViewCustomFields()`
- `canCreateCustomFields()`
- `canUpdateCustomFields()`
- `canDeleteCustomFields()`
- `canUpdateOrganization()` (already exists -- used for mandatory tags toggle)

---

## 8. Frontend Store Patterns

### 8.1 Existing Tags Store

**File**: `resources/js/utils/useTags.ts`

```typescript
export const useTagsStore = defineStore('tags', () => {
    const tags = ref<Tag[]>([]);
    const { handleApiRequestNotifications } = useNotificationsStore();

    async function fetchTags() { /* GET /tags */ }
    async function deleteTag(tagId: string) { /* DELETE /tags/{tag} */ }
    async function createTag(name: string) { /* POST /tags */ }

    return { tags, fetchTags, createTag, deleteTag };
});
```

Key patterns:
- Uses composition API style (`defineStore` with setup function)
- Uses `ref<T[]>([])` for collections
- Uses `handleApiRequestNotifications()` from `useNotificationsStore` for API calls with success/error toasts
- Uses `getCurrentOrganizationId()` from `useUser` for organization context
- Uses `api.*` methods from the auto-generated OpenAPI client
- Throws errors when organization ID is missing
- Returns response data directly (no transformation)

**Enhancement needed**:
- Add `updateTag(tagId, data)` -- PUT /tags/{tag}
- Add `fetchTagsWithUsageCounts()` -- GET /tags?with_usage_counts=true
- Add `bulkDeleteTags(ids, force)` -- DELETE /tags/bulk
- Enhance `createTag()` to accept color and description

### 8.2 API Client Pattern

**File**: `resources/js/packages/api/src/openapi.json.client.ts`

Auto-generated client from OpenAPI spec. After updating the OpenAPI spec and regenerating, new methods become available:
```typescript
api.getTags({ params: { organization: orgId }, queries: { with_usage_counts: true } })
api.createTag({ name, color, description }, { params: { organization: orgId } })
api.updateTag({ name, color, description }, { params: { organization: orgId, tag: tagId } })
api.bulkDeleteTags({ ids, force }, { params: { organization: orgId } })
api.getCustomFields({ params: { organization: orgId } })
api.createCustomField({ ... }, { params: { organization: orgId } })
api.updateCustomField({ ... }, { params: { organization: orgId, customField: cfId } })
api.archiveCustomField(undefined, { params: { organization: orgId, customField: cfId } })
api.deleteCustomField(undefined, { params: { organization: orgId, customField: cfId } })
```

### 8.3 Store Initialization

**File**: `resources/js/utils/init.ts`

Stores are initialized on app mount. The `useCustomFieldsStore` should be initialized alongside `useTagsStore` since custom field definitions need to be available for time entry forms.

---

## 9. Frontend Component Patterns

### 9.1 Tag Components Inventory

| Component | File | Purpose | Modification Scope |
|-----------|------|---------|-------------------|
| `TagBadge.vue` | `resources/js/packages/ui/src/Tag/TagBadge.vue` | Renders tag name with icon in a badge | Add background color from tag, auto-contrast text |
| `TagDropdown.vue` | `resources/js/packages/ui/src/Tag/TagDropdown.vue` | Multiselect dropdown for tag selection | Add color dot next to each tag name |
| `TagCreateModal.vue` | `resources/js/packages/ui/src/Tag/TagCreateModal.vue` | Modal for creating a new tag | Add color picker and description textarea |
| `TagTable.vue` | `resources/js/Components/Common/Tag/TagTable.vue` | Tag list table on management page | Add search, sort, bulk select, usage counts |
| `TagTableRow.vue` | `resources/js/Components/Common/Tag/TagTableRow.vue` | Single tag row in table | Add color badge, description tooltip, usage count, checkbox |
| `TagMoreOptionsDropdown.vue` | `resources/js/Components/Common/Tag/TagMoreOptionsDropdown.vue` | Context menu for tag actions | Add "Edit" option |
| `TagTableHeading.vue` | `resources/js/Components/Common/Tag/TagTableHeading.vue` | Table header row | Add sortable column headers, select-all checkbox |

### 9.2 TagBadge Analysis

**File**: `resources/js/packages/ui/src/Tag/TagBadge.vue`

```vue
<script setup lang="ts">
const props = withDefaults(defineProps<{
    name: string;
    size?: 'base' | 'large';
    tag?: string;
    class?: string;
    color?: string;
    border?: boolean;
}>(), {
    size: 'base',
    tag: 'div',
    color: 'var(--theme-color-icon-default)',
    border: true,
});
</script>
<template>
    <Badge :name :size :tag :class="props.class" :color :border>
        <TagIcon :class="twMerge(indicatorClasses[size])"></TagIcon>
        <span v-if="name">{{ name }}</span>
    </Badge>
</template>
```

The component already accepts a `color` prop but it is used as the icon/accent color of the generic `Badge` component, not as a background color. The enhancement needs to:
1. Change the `color` prop default to use the tag's hex color
2. Apply the color as a background-color CSS property
3. Add auto-contrast text color calculation
4. Ensure the parent `Badge` component supports background color rendering

### 9.3 TagDropdown Analysis

**File**: `resources/js/packages/ui/src/Tag/TagDropdown.vue`

The dropdown renders tags in a list with `MultiselectDropdownItem` components. Each item shows the tag name and a selection checkbox.

**Enhancement needed**: Add a small color dot (circle) before each tag name in the dropdown list. This requires modifying the template inside the `v-for` loop to add a colored circle element.

### 9.4 TagCreateModal Analysis

**File**: `resources/js/packages/ui/src/Tag/TagCreateModal.vue`

Simple modal with a single `TextInput` for the tag name. Uses `DialogModal` pattern.

**Enhancement needed**: Add a color picker input (native `<input type="color">` or a custom palette component) and a `<textarea>` for the description with a character counter.

### 9.5 TagTableRow Analysis

**File**: `resources/js/Components/Common/Tag/TagTableRow.vue`

Minimal row showing tag name and a `TagMoreOptionsDropdown` (delete only).

**Enhancement needed**: Add colored `TagBadge` instead of plain name, description tooltip, usage count column, and a checkbox for bulk selection.

### 9.6 Time Entry Form Components

The time entry create and edit modals will need a section for custom fields. Existing modal patterns use:
- `DialogModal` for the modal container
- Form fields stacked vertically
- Validation errors shown inline per field
- Save/cancel buttons in footer

Custom fields should be rendered below the standard fields (project, task, description, tags, billable) using the new `CustomFieldFormRenderer` component.

### 9.7 Reporting Components

**File**: `resources/js/Components/Common/Reporting/ReportingOverview.vue`

The reporting page has a filter bar with various filter controls. A `TagDropdown` can be added to this filter bar to enable tag-based filtering. The existing `ReportingGroupBySelect` already includes "Tag" as a group-by option.

---

## 10. Route Registration Patterns

### 10.1 API Routes

**File**: `routes/api.php`

All API routes follow this nesting pattern:
```php
Route::prefix('v1')->name('v1.')->group(static function (): void {
    Route::middleware(['auth:api', 'verified'])->group(static function (): void {
        // Feature route groups
        Route::name('tags.')->prefix('/organizations/{organization}')->group(static function (): void {
            Route::get('/tags', [TagController::class, 'index'])->name('index');
            Route::post('/tags', [TagController::class, 'store'])->name('store')->middleware('check-organization-blocked');
            Route::put('/tags/{tag}', [TagController::class, 'update'])->name('update')->middleware('check-organization-blocked');
            Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->name('destroy');
        });
    });
});
```

Key patterns:
- Route names: `api.v1.{feature}.{action}` (e.g., `api.v1.tags.index`)
- Write endpoints use `->middleware('check-organization-blocked')`
- Read endpoints do not use `check-organization-blocked`
- Delete endpoints sometimes do and sometimes do not use `check-organization-blocked` (inconsistent)

For this feature:
- The tag bulk delete route should be added inside the existing tags group
- The custom field routes should be a new group following the same pattern
- **Route ordering matters**: The `/tags/bulk` delete route must be registered BEFORE `/tags/{tag}` to avoid `bulk` being interpreted as a tag UUID

### 10.2 Web Routes

**File**: `routes/web.php`

Inertia page routes registered inside `auth:web` middleware:
```php
Route::get('/custom-fields', function () {
    return Inertia::render('CustomFields');
})->name('custom-fields');
```

---

## 11. Navigation Sidebar

**File**: `resources/js/Layouts/AppLayout.vue`

The sidebar uses `NavigationSidebarItem` components with heroicons. The Tags page already has a sidebar item:

```vue
<NavigationSidebarItem
    v-if="canViewTags()"
    title="Tags"
    :icon="TagIcon"
    :current="route().current('tags')"
    :href="route('tags')">
</NavigationSidebarItem>
```

The Custom Fields page should be accessible from organization settings rather than the main sidebar, since it is an admin-level configuration page. Options:
1. Add a link in the organization settings page (preferred)
2. Add a sidebar item visible only to Owners/Admins (alternative)

The tag management page already exists in the sidebar -- no changes needed for tag navigation.

---

## 12. Data Flow Diagrams

### 12.1 Tag Creation with Color Flow

```
Admin clicks "Create Tag" button
    -> TagCreateModal opens (MODIFIED: now has color picker + description)
    -> Admin enters name, picks color, adds description
    -> Submits form
    -> useTagsStore.createTag(name, color, description)
        -> POST /api/v1/organizations/{org}/tags
            { name: "Billable", color: "#22C55E", description: "Tag for billable items" }
        -> Backend: TagController.store()
            -> checkPermission($organization, 'tags:create')
            -> TagStoreRequest validates name (unique), color (hex regex), description (max 500)
            -> Create Tag model with name, color, description
            -> Return TagResource { id, name, color, description, created_at, updated_at }
        -> Store: unshift new tag to tags array
    -> Modal closes
    -> TagTable re-renders with new tag (colored badge visible)
```

### 12.2 Mandatory Tags Enforcement Flow

```
Member creates time entry
    -> TimeEntryCreateModal submits
    -> POST /api/v1/organizations/{org}/time-entries
        { member_id, project_id, start, end, billable, tags: [] }
    -> Backend: TimeEntryStoreRequest.rules()
        -> Check $this->organization->mandatory_tags
        -> If true AND end is not null (not a running timer):
            -> tags rule becomes ['required', 'array', 'min:1']
        -> Validation runs:
            -> If tags is empty: FAILS with "At least one tag is required"
            -> Returns 422 Unprocessable Entity
    -> Frontend: shows validation error on tags field
    -> Member selects a tag and re-submits -> success
```

### 12.3 Custom Field Value Storage Flow

```
Member creates time entry with custom fields
    -> TimeEntryCreateModal includes CustomFieldFormRenderer for each active field
    -> Member fills in "Cost Center" = "Engineering", "Ticket #" = "JIRA-1234"
    -> Submits form
    -> POST /api/v1/organizations/{org}/time-entries
        {
            member_id, project_id, start, end, billable, tags: ["tag-1"],
            custom_fields: {
                "cf-uuid-1": "Engineering",
                "cf-uuid-2": "JIRA-1234"
            }
        }
    -> Backend: TimeEntryController.store()
        -> Create time entry (standard flow)
        -> Call CustomFieldService.validateAndStoreValues('time_entry', $timeEntry->id, ...)
            -> Load active custom field definitions for organization
            -> Validate "Engineering" is in dropdown options for cf-uuid-1 -> OK
            -> Validate "JIRA-1234" is a string max 255 for cf-uuid-2 -> OK
            -> Check required fields all have values -> OK
            -> Upsert into custom_field_values:
                { custom_field_id: "cf-uuid-1", entity_type: "time_entry", entity_id: "te-uuid", value: "Engineering" }
                { custom_field_id: "cf-uuid-2", entity_type: "time_entry", entity_id: "te-uuid", value: "JIRA-1234" }
        -> Return TimeEntryResource with custom_fields array
    -> Frontend: shows saved time entry with custom field values
```

### 12.4 Tag Bulk Delete Flow

```
Admin selects 3 tags on management page via checkboxes
    -> Clicks "Delete Selected"
    -> Confirmation dialog shows: "Tag A (5 entries), Tag B (0 entries), Tag C (12 entries)"
    -> Admin checks "Force delete (remove from time entries)" checkbox
    -> Confirms
    -> useTagsStore.bulkDeleteTags(['id-a', 'id-b', 'id-c'], true)
        -> DELETE /api/v1/organizations/{org}/tags/bulk
            { ids: ["id-a", "id-b", "id-c"], force: true }
        -> Backend: TagController.bulkDestroy()
            -> checkPermission($organization, 'tags:delete')
            -> TagService.bulkDeleteTags($organization, $ids, true)
                -> For Tag A (5 entries, force=true):
                    -> Remove "id-a" from all time entries JSONB arrays
                    -> Delete tag -> add to deleted[]
                -> For Tag B (0 entries):
                    -> Delete tag -> add to deleted[]
                -> For Tag C (12 entries, force=true):
                    -> Remove "id-c" from all time entries JSONB arrays
                    -> Delete tag -> add to deleted[]
            -> Return { deleted: ["id-a", "id-b", "id-c"], failed: [] }
        -> Store: refresh tags list
    -> Tags page re-renders without deleted tags
    -> Success toast: "3 tags deleted successfully"
```

---

## 13. File Modification Risk Assessment

### 13.1 Risk Matrix

| File | Change Type | Risk | Rationale |
|------|------------|:----:|-----------|
| `app/Models/Tag.php` | Add properties and casts | Low | Adding 2 new casts, no logic changes |
| `app/Models/Organization.php` | Add cast | Low | Adding 1 boolean cast to existing array |
| `app/Http/Controllers/Api/V1/TagController.php` | Enhance methods, add method, add constructor | Medium | Modifying store/update logic, adding DI; risk of breaking existing tag tests |
| `app/Http/Requests/V1/Tag/TagStoreRequest.php` | Add rules | Low | Appending new rules to existing array |
| `app/Http/Requests/V1/Tag/TagUpdateRequest.php` | Add rules | Low | Appending new rules to existing array |
| `app/Http/Resources/V1/Tag/TagResource.php` | Add fields | Low | Appending new fields to response array |
| `app/Http/Requests/V1/TimeEntry/TimeEntryStoreRequest.php` | Add conditional mandatory tags + custom fields | **High** | Complex conditional logic being added to already-complex validation; risk of breaking existing time entry creation |
| `app/Http/Requests/V1/TimeEntry/TimeEntryUpdateRequest.php` | Add conditional mandatory tags + custom fields | **High** | Same risk as store request |
| `app/Http/Controllers/Api/V1/TimeEntryController.php` | Add custom field value handling | Medium | Adding service calls after entry creation/update; risk if service throws unexpected exceptions |
| `app/Http/Resources/V1/TimeEntry/TimeEntryResource.php` | Add custom_fields array | Low | Appending new field to response array |
| `app/Permissions/CorePermissions.php` | Add permission strings | Low | Appending strings to existing arrays |
| `routes/api.php` | Add routes | Low | Appending new route group, modifying existing tag group order |
| `resources/js/utils/useTags.ts` | Add methods | Low | Appending new functions, no existing logic modified |
| `resources/js/Pages/Tags.vue` | Significant UI changes | Medium | Adding search, sort, bulk operations; risk of breaking existing tag page layout |
| `resources/js/packages/ui/src/Tag/TagBadge.vue` | Change color rendering | Medium | Changing how the color prop is applied; may affect all places TagBadge is used |
| `resources/js/packages/ui/src/Tag/TagDropdown.vue` | Add color dots | Low | Adding visual element, no logic changes |
| `resources/js/packages/ui/src/Tag/TagCreateModal.vue` | Add form fields | Low | Adding color picker and textarea to existing form |
| `resources/js/Components/Common/Reporting/ReportingOverview.vue` | Add tag filter | Medium | Modifying report filter bar; may affect layout |

### 13.2 Highest Risk Areas

1. **`TimeEntryStoreRequest.php`** and **`TimeEntryUpdateRequest.php`**: These are the most critical files to modify carefully. The mandatory tags validation needs to be conditional on `$this->organization->mandatory_tags` and must properly handle exemptions (running timers, timesheet grid). Custom field validation needs to be lightweight in the request class (deep validation in service layer).

2. **`TagController.php`**: Adding a constructor for DI changes the class structure. The existing `checkPermission` override must be preserved. The new `bulkDestroy` method introduces complex multi-step operations.

3. **`TagBadge.vue`**: This component is used throughout the application (tag management page, time entry rows, dropdowns, reports). Changing its color rendering affects all of these contexts.

---

## 14. Merge Conflict Assessment

### 14.1 Cross-Feature Conflict Risk

| File | Other Features That May Modify | Conflict Risk |
|------|-------------------------------|:------------:|
| `routes/api.php` | Feature 00 (Timesheet), any other feature adding routes | Low (additive) |
| `app/Permissions/CorePermissions.php` | Any feature adding new permissions | Low (additive) |
| `app/Http/Requests/V1/TimeEntry/TimeEntryStoreRequest.php` | Feature 00 (if adding timesheet-specific validation) | Medium |
| `app/Http/Requests/V1/TimeEntry/TimeEntryUpdateRequest.php` | Feature 00 (if adding timesheet-specific validation) | Medium |
| `app/Http/Resources/V1/TimeEntry/TimeEntryResource.php` | Feature 00 (if adding timesheet-specific fields) | Low |
| `resources/js/Layouts/AppLayout.vue` | Any feature adding navigation items | Low |
| `resources/js/Pages/Tags.vue` | No other features expected to modify | None |

### 14.2 Branch Strategy

This feature should be branched from `main` AFTER Feature 00 (Weekly Timesheet Grid) has been merged, because:
1. The mandatory tags exemption for timesheet cell updates references the timesheet endpoint
2. `TimeEntryStoreRequest` may have been modified by Feature 00
3. `routes/api.php` will have timesheet routes already present

### 14.3 Safe Modification Pattern

For high-risk files (`TimeEntryStoreRequest`, `TimeEntryUpdateRequest`):
1. Read the file at implementation time to verify current state
2. Add mandatory tags logic as a clearly separated block (with comments)
3. Add custom field rules as additional array entries (not modifying existing rules)
4. Run existing time entry endpoint tests after each modification to verify no regressions
