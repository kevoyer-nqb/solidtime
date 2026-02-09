# Feature 11: Tags and Custom Fields -- Technical Architecture

**Date**: 2026-02-09
**Status**: Draft
**Feature Branch**: `feature/tags-custom-fields` (from `main`)
**Task Prefix**: `TAG-` (per task assignments)

---

## Executive Summary

This document provides the complete technical architecture for the **Tags and Custom Fields** feature. The feature enhances the existing tag system with colors, descriptions, and a mandatory tags policy, and introduces custom field definitions with typed values on time entries.

**Key Architectural Decisions**:
- **4 database migrations** -- adds `color`/`description` to `tags`, `mandatory_tags` to `organizations`, and creates `custom_fields` + `custom_field_values` tables
- **2 new models** -- `CustomField` and `CustomFieldValue` with polymorphic entity support
- **2 new services** -- `TagService` (bulk operations, usage counts) and `CustomFieldService` (validation, CRUD, value storage)
- **1 new controller** -- `CustomFieldController` with 5 endpoints; existing `TagController` enhanced with bulk delete
- **Enhanced existing models** -- `Tag` gains `color`/`description`, `Organization` gains `mandatory_tags`
- **New permissions** -- `custom-fields:view/create/update/delete` added to `CorePermissions`
- **Frontend** -- enhanced Pinia tags store, new `useCustomFieldsStore`, new CustomFields settings page, enhanced tag management page, `CustomFieldFormRenderer` component for time entry forms
- **Mandatory tags validation** -- enforced in `TimeEntryStoreRequest`/`TimeEntryUpdateRequest` with exemptions for running timers, timesheet grid, and imports

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

### 1.1 Migration 1: Add Columns to `tags` Table

**File**: `database/migrations/2026_03_11_000001_add_color_description_to_tags_table.php`
**Task**: TAG-001

```php
Schema::table('tags', function (Blueprint $table): void {
    $table->string('color', 7)->default('#808080');
    $table->string('description', 500)->nullable();
});
```

Rollback:
```php
Schema::table('tags', function (Blueprint $table): void {
    $table->dropColumn(['color', 'description']);
});
```

**Impact**: Additive only. Existing tags receive default color `#808080` and null description. No data migration needed.

### 1.2 Migration 2: Add `mandatory_tags` to `organizations` Table

**File**: `database/migrations/2026_03_11_000002_add_mandatory_tags_to_organizations_table.php`
**Task**: TAG-002

```php
Schema::table('organizations', function (Blueprint $table): void {
    $table->boolean('mandatory_tags')->default(false);
});
```

Rollback:
```php
Schema::table('organizations', function (Blueprint $table): void {
    $table->dropColumn('mandatory_tags');
});
```

**Impact**: Additive only. All existing organizations default to `false` (no mandatory tags).

### 1.3 Migration 3: Create `custom_fields` Table

**File**: `database/migrations/2026_03_11_000003_create_custom_fields_table.php`
**Task**: TAG-014

```php
Schema::create('custom_fields', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->string('name', 255);
    $table->string('type', 20);          // 'text', 'number', 'dropdown', 'checkbox'
    $table->jsonb('config')->default('{}');
    $table->boolean('is_required')->default(false);
    $table->boolean('is_archived')->default(false);
    $table->integer('sort_order')->default(0);
    $table->uuid('organization_id');
    $table->foreign('organization_id')
        ->references('id')
        ->on('organizations')
        ->cascadeOnUpdate()
        ->restrictOnDelete();
    $table->timestamps();

    $table->unique(['organization_id', 'name']);
    $table->index('organization_id');
    $table->index('created_at');
});
```

### 1.4 Migration 4: Create `custom_field_values` Table

**File**: `database/migrations/2026_03_11_000004_create_custom_field_values_table.php`
**Task**: TAG-015

```php
Schema::create('custom_field_values', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->uuid('custom_field_id');
    $table->foreign('custom_field_id')
        ->references('id')
        ->on('custom_fields')
        ->cascadeOnUpdate()
        ->cascadeOnDelete();
    $table->string('entity_type', 50);   // 'time_entry' (future: 'project', 'task')
    $table->uuid('entity_id');
    $table->jsonb('value');
    $table->timestamps();

    $table->unique(['custom_field_id', 'entity_type', 'entity_id']);
    $table->index(['entity_type', 'entity_id']);
    $table->index('custom_field_id');
});
```

### 1.5 Existing Model Enhancements

#### Tag Model (Modified)

**File**: `app/Models/Tag.php`
**Task**: TAG-003

```php
/**
 * @property string $id
 * @property string $name
 * @property string $color            Hex color code (#RRGGBB)
 * @property string|null $description  Optional admin description
 * @property string $organization_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Tag extends Model implements AuditableContract
{
    // Existing traits unchanged

    protected $casts = [
        'name' => 'string',
        'color' => 'string',        // NEW
        'description' => 'string',   // NEW
    ];

    // Existing relationships unchanged
}
```

#### Organization Model (Modified)

**File**: `app/Models/Organization.php`
**Task**: TAG-004

Add to existing `$casts` array:
```php
protected $casts = [
    // ... existing casts
    'mandatory_tags' => 'boolean',  // NEW
];
```

Add to PHPDoc:
```php
/**
 * @property bool $mandatory_tags
 */
```

### 1.6 New Models

#### CustomField Model

**File**: `app/Models/CustomField.php`
**Task**: TAG-016

```php
/**
 * @property string $id
 * @property string $name
 * @property string $type              'text'|'number'|'dropdown'|'checkbox'
 * @property array $config             {options?: string[], min?: float, max?: float, placeholder?: string}
 * @property bool $is_required
 * @property bool $is_archived
 * @property int $sort_order
 * @property string $organization_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read Collection<int, CustomFieldValue> $values
 */
class CustomField extends Model implements AuditableContract
{
    use CustomAuditable;
    use HasFactory;
    use HasUuids;

    protected $casts = [
        'name' => 'string',
        'type' => 'string',
        'config' => 'array',
        'is_required' => 'boolean',
        'is_archived' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'custom_field_id');
    }
}
```

#### CustomFieldValue Model

**File**: `app/Models/CustomFieldValue.php`
**Task**: TAG-017

```php
/**
 * @property string $id
 * @property string $custom_field_id
 * @property string $entity_type
 * @property string $entity_id
 * @property mixed $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CustomField $customField
 */
class CustomFieldValue extends Model implements AuditableContract
{
    use CustomAuditable;
    use HasFactory;
    use HasUuids;

    protected $casts = [
        'custom_field_id' => 'string',
        'entity_type' => 'string',
        'entity_id' => 'string',
        'value' => 'json',
    ];

    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class, 'custom_field_id');
    }
}
```

#### CustomFieldType Enum

**File**: `app/Enums/CustomFieldType.php`
**Task**: TAG-016

```php
enum CustomFieldType: string
{
    case Text = 'text';
    case Number = 'number';
    case Dropdown = 'dropdown';
    case Checkbox = 'checkbox';
}
```

### 1.7 Entity Relationship Diagram

```
organizations (1) ----< (N) tags
    |                          |
    | mandatory_tags (bool)    | + color, description (NEW)
    |                          |
    +----< (N) custom_fields   +---->< (N) time_entries.tags (JSONB)
                |
                +----< (N) custom_field_values
                              |
                              | entity_type = 'time_entry'
                              | entity_id = time_entry.id
                              |
                              +-----> (1) time_entries
```

---

## 2. API Contract

### 2.1 Route Registration

**File**: `routes/api.php` (modifications to existing tag group + new custom field group)

#### Enhanced Tag Routes

```php
// Existing tag route group -- add bulk delete
Route::name('tags.')->prefix('/organizations/{organization}')->group(static function (): void {
    Route::get('/tags', [TagController::class, 'index'])->name('index');
    Route::post('/tags', [TagController::class, 'store'])->name('store')->middleware('check-organization-blocked');
    Route::put('/tags/{tag}', [TagController::class, 'update'])->name('update')->middleware('check-organization-blocked');
    Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->name('destroy');
    Route::delete('/tags/bulk', [TagController::class, 'bulkDestroy'])->name('bulk-destroy')->middleware('check-organization-blocked');  // NEW
});
```

#### New Custom Field Routes

```php
Route::name('custom-fields.')->prefix('/organizations/{organization}')->group(static function (): void {
    Route::get('/custom-fields', [CustomFieldController::class, 'index'])->name('index');
    Route::post('/custom-fields', [CustomFieldController::class, 'store'])->name('store')->middleware('check-organization-blocked');
    Route::put('/custom-fields/{customField}', [CustomFieldController::class, 'update'])->name('update')->middleware('check-organization-blocked');
    Route::put('/custom-fields/{customField}/archive', [CustomFieldController::class, 'archive'])->name('archive')->middleware('check-organization-blocked');
    Route::delete('/custom-fields/{customField}', [CustomFieldController::class, 'destroy'])->name('destroy')->middleware('check-organization-blocked');
});
```

Route names resolve to:
- `api.v1.tags.bulk-destroy` (NEW)
- `api.v1.custom-fields.index`
- `api.v1.custom-fields.store`
- `api.v1.custom-fields.update`
- `api.v1.custom-fields.archive`
- `api.v1.custom-fields.destroy`

### 2.2 Endpoint Signatures

#### Enhanced Tag Endpoints

| Method | Path | Controller Method | Request Class | Permission |
|--------|------|-------------------|---------------|------------|
| GET | `/tags` | `index()` | -- | `tags:view` |
| POST | `/tags` | `store()` | `TagStoreRequest` | `tags:create` |
| PUT | `/tags/{tag}` | `update()` | `TagUpdateRequest` | `tags:update` |
| DELETE | `/tags/{tag}` | `destroy()` | -- | `tags:delete` |
| DELETE | `/tags/bulk` | `bulkDestroy()` | `TagBulkDeleteRequest` | `tags:delete` |

#### Custom Field Endpoints

| Method | Path | Controller Method | Request Class | Permission |
|--------|------|-------------------|---------------|------------|
| GET | `/custom-fields` | `index()` | -- | `custom-fields:view` |
| POST | `/custom-fields` | `store()` | `CustomFieldStoreRequest` | `custom-fields:create` |
| PUT | `/custom-fields/{customField}` | `update()` | `CustomFieldUpdateRequest` | `custom-fields:update` |
| PUT | `/custom-fields/{customField}/archive` | `archive()` | -- | `custom-fields:update` |
| DELETE | `/custom-fields/{customField}` | `destroy()` | -- | `custom-fields:delete` |

### 2.3 Response Shapes

**GET /tags** (enhanced):
```json
{
  "data": [
    {
      "id": "uuid",
      "name": "Billable",
      "color": "#22C55E",
      "description": "Tag for billable work items",
      "time_entry_count": 142,
      "created_at": "2026-01-15T10:30:00Z",
      "updated_at": "2026-02-01T14:20:00Z"
    }
  ]
}
```

Note: `time_entry_count` is only included when `?with_usage_counts=true` query parameter is set.

**POST /tags** (enhanced):
```json
{
  "data": {
    "id": "uuid",
    "name": "Internal",
    "color": "#808080",
    "description": null,
    "created_at": "2026-02-09T12:00:00Z",
    "updated_at": "2026-02-09T12:00:00Z"
  }
}
```

**DELETE /tags/bulk** (new):
```json
{
  "data": {
    "deleted": ["uuid-1", "uuid-2"],
    "failed": [
      { "id": "uuid-3", "reason": "tag_in_use" }
    ]
  }
}
```

**GET /custom-fields**:
```json
{
  "data": [
    {
      "id": "uuid",
      "name": "Cost Center",
      "type": "dropdown",
      "config": {
        "options": ["Engineering", "Marketing", "Sales"]
      },
      "is_required": true,
      "is_archived": false,
      "sort_order": 0,
      "created_at": "2026-02-09T12:00:00Z",
      "updated_at": "2026-02-09T12:00:00Z"
    }
  ]
}
```

**POST /custom-fields**:
```json
{
  "data": {
    "id": "uuid",
    "name": "Ticket Number",
    "type": "text",
    "config": { "placeholder": "JIRA-1234" },
    "is_required": false,
    "is_archived": false,
    "sort_order": 1,
    "created_at": "2026-02-09T12:00:00Z",
    "updated_at": "2026-02-09T12:00:00Z"
  }
}
```

**Time Entry Response (enhanced)**:
```json
{
  "data": {
    "id": "uuid",
    "start": "2026-02-09T09:00:00Z",
    "end": "2026-02-09T17:00:00Z",
    "duration": 28800,
    "description": "Feature work",
    "task_id": "uuid",
    "project_id": "uuid",
    "organization_id": "uuid",
    "user_id": "uuid",
    "tags": ["tag-uuid-1", "tag-uuid-2"],
    "billable": true,
    "custom_fields": [
      {
        "custom_field_id": "cf-uuid-1",
        "custom_field_name": "Cost Center",
        "custom_field_type": "dropdown",
        "value": "Engineering"
      },
      {
        "custom_field_id": "cf-uuid-2",
        "custom_field_name": "Ticket Number",
        "custom_field_type": "text",
        "value": "JIRA-1234"
      }
    ]
  }
}
```

---

## 3. Service Layer

### 3.1 TagService

**File**: `app/Service/TagService.php`
**Task**: TAG-007 (bulk operations added as part of controller enhancements)

Stateless service class with 3 public methods.

#### `getTagsWithUsageCounts(Organization $organization): Collection`

1. Query all tags for the organization
2. For each tag, count time entries using `whereJsonContains('tags', $tag->getKey())`
3. Alternatively, use a single query with a subquery for counts:
```php
Tag::query()
    ->whereBelongsTo($organization, 'organization')
    ->withCount(['timeEntries as time_entry_count'])
    ->orderBy('created_at', 'desc')
    ->get();
```

Note: The `withCount` on `timeEntries` (a `HasManyJson` relation) may not work with standard `withCount`. Fallback approach uses a raw subquery:
```php
Tag::query()
    ->whereBelongsTo($organization, 'organization')
    ->addSelect([
        'time_entry_count' => TimeEntry::query()
            ->selectRaw('COUNT(*)')
            ->whereRaw("time_entries.tags @> to_jsonb(tags.id::text)")
            ->whereBelongsTo($organization, 'organization'),
    ])
    ->orderBy('created_at', 'desc')
    ->get();
```

#### `bulkDeleteTags(Organization $organization, array $ids, bool $force): array`

1. Validate all tag IDs belong to the organization
2. For each tag ID:
   a. Check if tag is in use (any time entry contains this tag ID in JSONB)
   b. If in use and `force` is false: add to `failed` array with reason `tag_in_use`
   c. If in use and `force` is true: remove tag ID from all time entries' JSONB arrays, then delete
   d. If not in use: delete directly
3. Return `{ deleted: string[], failed: Array<{ id: string, reason: string }> }`

#### `removeTagFromAllEntries(Organization $organization, Tag $tag): int`

1. Update all time entries that contain this tag ID:
```php
TimeEntry::query()
    ->whereBelongsTo($organization, 'organization')
    ->whereJsonContains('tags', $tag->getKey())
    ->update([
        'tags' => DB::raw("tags - '{$tag->getKey()}'"),
    ]);
```
2. Return count of affected rows

### 3.2 CustomFieldService

**File**: `app/Service/CustomFieldService.php`
**Task**: TAG-018

Stateless service class with 6 public methods.

#### `getFieldsForOrganization(Organization $organization, bool $includeArchived = false): Collection`

1. Query `CustomField` where `organization_id` matches
2. If `!$includeArchived`: filter `is_archived = false`
3. Order by `sort_order ASC, created_at ASC`
4. Return collection

#### `createField(Organization $organization, array $data): CustomField`

1. Count active (non-archived) fields for organization
2. If count >= 20: throw `CustomFieldLimitExceededException`
3. Create `CustomField` with validated data
4. Return created model

#### `updateField(CustomField $field, array $data): CustomField`

1. Validate type is not being changed (compare `$field->type` with `$data['type']` if present)
2. For dropdown type: validate that existing in-use options are not removed
3. Update field attributes
4. Return updated model

#### `archiveField(CustomField $field): CustomField`

1. Set `is_archived = true`
2. Save and return

#### `restoreField(CustomField $field): CustomField`

1. Check active field count (must be < 20)
2. Set `is_archived = false`
3. Save and return

#### `validateAndStoreValues(string $entityType, string $entityId, Organization $organization, array $customFieldData): void`

1. Load active custom field definitions for organization
2. For each provided custom field value:
   a. Find matching definition (skip unknown IDs silently)
   b. Validate value against field type:
      - `text`: string, max 255 chars
      - `number`: numeric, check min/max from config
      - `dropdown`: value must be in `config.options`
      - `checkbox`: boolean
   c. Upsert into `custom_field_values` table
3. Check required fields: for each required active field, verify a value was provided
4. Throw `ValidationException` if any validation fails

#### `getValuesForEntity(string $entityType, string $entityId): Collection`

1. Query `CustomFieldValue` where `entity_type` and `entity_id` match
2. Eager load `customField` relationship
3. Return collection

---

## 4. Controller Layer

### 4.1 TagController (Enhanced)

**File**: `app/Http/Controllers/Api/V1/TagController.php`
**Tasks**: TAG-007, TAG-008

Existing controller gains:
- Enhanced `index()` with optional `with_usage_counts` parameter
- Enhanced `store()` to handle `color` and `description`
- Enhanced `update()` to handle `color` and `description`
- New `bulkDestroy()` method

```php
class TagController extends Controller
{
    public function __construct(
        private readonly TagService $tagService  // NEW: inject TagService
    ) {}

    public function index(Organization $organization, Request $request): TagCollection
    {
        $this->checkPermission($organization, 'tags:view');

        if ($request->boolean('with_usage_counts', false)) {
            $tags = $this->tagService->getTagsWithUsageCounts($organization);
            return new TagCollection($tags);
        }

        $tags = Tag::query()
            ->whereBelongsTo($organization, 'organization')
            ->orderBy('created_at', 'desc')
            ->paginate(config('app.pagination_per_page_default'));

        return new TagCollection($tags);
    }

    public function store(Organization $organization, TagStoreRequest $request): TagResource
    {
        $this->checkPermission($organization, 'tags:create');

        $tag = new Tag;
        $tag->name = $request->input('name');
        $tag->color = $request->input('color', '#808080');           // NEW
        $tag->description = $request->input('description');           // NEW
        $tag->organization()->associate($organization);
        $tag->save();

        return new TagResource($tag);
    }

    public function update(Organization $organization, Tag $tag, TagUpdateRequest $request): TagResource
    {
        $this->checkPermission($organization, 'tags:update', $tag);

        $tag->name = $request->input('name');
        if ($request->has('color')) {
            $tag->color = $request->input('color');                   // NEW
        }
        if ($request->has('description')) {
            $tag->description = $request->input('description');       // NEW
        }
        $tag->save();

        return new TagResource($tag);
    }

    // Existing destroy() unchanged

    public function bulkDestroy(Organization $organization, TagBulkDeleteRequest $request): JsonResponse  // NEW
    {
        $this->checkPermission($organization, 'tags:delete');

        $result = $this->tagService->bulkDeleteTags(
            $organization,
            $request->input('ids'),
            $request->boolean('force', false)
        );

        return response()->json(['data' => $result]);
    }
}
```

### 4.2 CustomFieldController (New)

**File**: `app/Http/Controllers/Api/V1/CustomFieldController.php`
**Task**: TAG-019

```php
class CustomFieldController extends Controller
{
    public function __construct(
        private readonly CustomFieldService $customFieldService
    ) {}

    protected function checkPermission(Organization $organization, string $permission, ?CustomField $customField = null): void
    {
        parent::checkPermission($organization, $permission);
        if ($customField !== null && $customField->organization_id !== $organization->getKey()) {
            throw new AuthorizationException('Custom field does not belong to organization');
        }
    }

    public function index(Organization $organization, Request $request): CustomFieldCollection
    {
        $this->checkPermission($organization, 'custom-fields:view');
        $includeArchived = $request->boolean('include_archived', false);
        $fields = $this->customFieldService->getFieldsForOrganization($organization, $includeArchived);
        return new CustomFieldCollection($fields);
    }

    public function store(Organization $organization, CustomFieldStoreRequest $request): CustomFieldResource
    {
        $this->checkPermission($organization, 'custom-fields:create');
        $field = $this->customFieldService->createField($organization, $request->validated());
        return new CustomFieldResource($field);
    }

    public function update(Organization $organization, CustomField $customField, CustomFieldUpdateRequest $request): CustomFieldResource
    {
        $this->checkPermission($organization, 'custom-fields:update', $customField);
        $field = $this->customFieldService->updateField($customField, $request->validated());
        return new CustomFieldResource($field);
    }

    public function archive(Organization $organization, CustomField $customField): CustomFieldResource
    {
        $this->checkPermission($organization, 'custom-fields:update', $customField);
        $field = $this->customFieldService->archiveField($customField);
        return new CustomFieldResource($field);
    }

    public function destroy(Organization $organization, CustomField $customField): JsonResponse
    {
        $this->checkPermission($organization, 'custom-fields:delete', $customField);

        if (! $customField->is_archived) {
            return response()->json(['message' => 'Custom field must be archived before deletion'], 422);
        }
        if ($customField->values()->exists()) {
            return response()->json(['message' => 'Custom field has existing values'], 422);
        }

        $customField->delete();
        return response()->json(null, 204);
    }
}
```

### 4.3 TimeEntryController Enhancements

**File**: `app/Http/Controllers/Api/V1/TimeEntryController.php`
**Tasks**: TAG-026, TAG-027

The `store()` method gains custom field value handling after the time entry is created:

```php
// After $timeEntry->save() in store():
if ($request->has('custom_fields')) {
    $customFieldService->validateAndStoreValues(
        'time_entry',
        $timeEntry->getKey(),
        $organization,
        $request->input('custom_fields')
    );
}
```

The `update()` method follows the same pattern for updating custom field values.

---

## 5. Request Validation

### 5.1 Enhanced Tag Request Classes

**TagStoreRequest** (modified):
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
        'color' => [
            'sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/',
        ],
        'description' => [
            'nullable', 'string', 'max:500',
        ],
    ];
}
```

**TagUpdateRequest** (modified): Same additions with `ignore($this->tag?->getKey())` on unique rule.

**TagBulkDeleteRequest** (new):
```php
public function rules(): array
{
    return [
        'ids' => ['required', 'array', 'min:1', 'max:50'],
        'ids.*' => ['string', 'uuid'],
        'force' => ['sometimes', 'boolean'],
    ];
}
```

### 5.2 New Custom Field Request Classes

**CustomFieldStoreRequest**:
```php
public function rules(): array
{
    return [
        'name' => [
            'required', 'string', 'min:1', 'max:255',
            UniqueEloquent::make(CustomField::class, 'name', function (Builder $builder): Builder {
                return $builder->whereBelongsTo($this->organization, 'organization');
            }),
        ],
        'type' => ['required', 'string', 'in:text,number,dropdown,checkbox'],
        'config' => ['sometimes', 'array'],
        'config.options' => ['required_if:type,dropdown', 'array', 'min:1', 'max:50'],
        'config.options.*' => ['string', 'max:255'],
        'config.min' => ['nullable', 'numeric'],
        'config.max' => ['nullable', 'numeric', 'gte:config.min'],
        'config.placeholder' => ['nullable', 'string', 'max:255'],
        'is_required' => ['sometimes', 'boolean'],
        'sort_order' => ['sometimes', 'integer', 'min:0'],
    ];
}
```

**CustomFieldUpdateRequest**:
```php
public function rules(): array
{
    return [
        'name' => [
            'sometimes', 'string', 'min:1', 'max:255',
            UniqueEloquent::make(CustomField::class, 'name', function (Builder $builder): Builder {
                return $builder->whereBelongsTo($this->organization, 'organization');
            })->ignore($this->customField?->getKey()),
        ],
        'config' => ['sometimes', 'array'],
        'config.options' => ['sometimes', 'array', 'min:1', 'max:50'],
        'config.options.*' => ['string', 'max:255'],
        'config.min' => ['nullable', 'numeric'],
        'config.max' => ['nullable', 'numeric'],
        'config.placeholder' => ['nullable', 'string', 'max:255'],
        'is_required' => ['sometimes', 'boolean'],
        'sort_order' => ['sometimes', 'integer', 'min:0'],
    ];
}
```

### 5.3 Enhanced Time Entry Request Classes

**TimeEntryStoreRequest** -- mandatory tags validation (TAG-010):
```php
public function rules(): array
{
    $rules = [
        // ... existing rules ...
    ];

    // Mandatory tags validation
    if ($this->organization->mandatory_tags) {
        $isRunningTimer = $this->input('end') === null;
        $isTimesheetCell = false; // detected via route name or header
        if (! $isRunningTimer && ! $isTimesheetCell) {
            $rules['tags'] = ['required', 'array', 'min:1'];
        }
    }

    return $rules;
}
```

**TimeEntryStoreRequest** -- custom field validation (TAG-024):
```php
// Additional rules for custom fields
'custom_fields' => ['sometimes', 'array'],
'custom_fields.*' => ['nullable'],
```

Deep validation of custom field values is handled by `CustomFieldService::validateAndStoreValues()` at the controller level rather than in the request class, to avoid loading custom field definitions in every request.

---

## 6. Frontend Architecture

### 6.1 Component Hierarchy

```
Tags.vue (Page -- MODIFIED)
+-- PageTitle (existing)
+-- SearchInput (NEW inline)
+-- SortControls (NEW inline)
+-- TagCreateModal.vue (MODIFIED -- color picker, description)
+-- TagTable.vue (MODIFIED -- search, sort, bulk select, usage counts)
|   +-- TagTableHeading.vue (MODIFIED -- checkbox, sort headers)
|   +-- TagTableRow.vue (MODIFIED -- color badge, description tooltip, usage count, checkbox)
|   |   +-- TagBadge.vue (MODIFIED -- renders with background color)
|   |   +-- TagMoreOptionsDropdown.vue (MODIFIED -- edit option)
|   +-- BulkDeleteBar (NEW inline component)
+-- TagEditModal.vue (NEW)

CustomFields.vue (Page -- NEW)
+-- PageTitle
+-- CustomFieldCreateModal.vue (NEW)
+-- CustomFieldList
|   +-- CustomFieldListItem.vue (NEW)
|   |   +-- CustomFieldEditModal.vue (NEW)
|   +-- EmptyState (when no fields)

TimeEntryCreateModal.vue (MODIFIED)
+-- ... existing fields ...
+-- CustomFieldFormRenderer.vue (NEW) x N (one per active custom field)
|   +-- TextInput (for text type)
|   +-- NumberInput (for number type)
|   +-- SelectInput (for dropdown type)
|   +-- ToggleSwitch (for checkbox type)
+-- "Tags required" indicator (when mandatory_tags is active)

TimeEntryEditModal.vue (MODIFIED)
+-- ... same custom field integration as create modal ...

ReportingOverview.vue (MODIFIED)
+-- ... existing filter bar ...
+-- TagDropdown (MODIFIED -- color dots) for tag filter (NEW)

TagDropdown.vue (MODIFIED -- existing component)
+-- Color dot next to each tag name in dropdown list
```

### 6.2 Enhanced Pinia Store: `useTagsStore`

**File**: `resources/js/utils/useTags.ts` (MODIFIED)

Enhanced with:

```typescript
export const useTagsStore = defineStore('tags', () => {
    const tags = ref<Tag[]>([]);
    const { handleApiRequestNotifications } = useNotificationsStore();

    // EXISTING
    async function fetchTags() { /* ... unchanged ... */ }
    async function createTag(name: string) { /* ... enhanced with color, description ... */ }
    async function deleteTag(tagId: string) { /* ... unchanged ... */ }

    // NEW: Update tag (color, description, name)
    async function updateTag(tagId: string, data: { name?: string; color?: string; description?: string | null }) {
        const organizationId = getCurrentOrganizationId();
        if (organizationId) {
            const response = await handleApiRequestNotifications(
                () => api.updateTag(data, { params: { organization: organizationId, tag: tagId } }),
                'Tag updated successfully',
                'Failed to update tag'
            );
            if (response?.data) {
                const index = tags.value.findIndex(t => t.id === tagId);
                if (index !== -1) tags.value[index] = response.data;
            }
        }
    }

    // NEW: Fetch tags with usage counts
    async function fetchTagsWithUsageCounts() {
        const organizationId = getCurrentOrganizationId();
        if (organizationId) {
            const response = await handleApiRequestNotifications(
                () => api.getTags({ params: { organization: organizationId }, queries: { with_usage_counts: true } }),
                undefined,
                'Failed to fetch tags'
            );
            if (response?.data) {
                tags.value = response.data;
            }
        }
    }

    // NEW: Bulk delete tags
    async function bulkDeleteTags(ids: string[], force: boolean = false) {
        const organizationId = getCurrentOrganizationId();
        if (organizationId) {
            const response = await handleApiRequestNotifications(
                () => api.bulkDeleteTags({ ids, force }, { params: { organization: organizationId } }),
                'Tags deleted successfully',
                'Failed to delete tags'
            );
            if (response?.data) {
                await fetchTagsWithUsageCounts();
            }
            return response?.data;
        }
    }

    return { tags, fetchTags, createTag, updateTag, deleteTag, fetchTagsWithUsageCounts, bulkDeleteTags };
});
```

### 6.3 New Pinia Store: `useCustomFieldsStore`

**File**: `resources/js/utils/useCustomFields.ts` (NEW)

```typescript
export const useCustomFieldsStore = defineStore('customFields', () => {
    const customFields = ref<CustomFieldDefinition[]>([]);
    const { handleApiRequestNotifications } = useNotificationsStore();

    async function fetchCustomFields(includeArchived: boolean = false) { /* ... */ }
    async function createCustomField(data: CreateCustomFieldBody) { /* ... */ }
    async function updateCustomField(id: string, data: UpdateCustomFieldBody) { /* ... */ }
    async function archiveCustomField(id: string) { /* ... */ }
    async function restoreCustomField(id: string) { /* ... */ }
    async function deleteCustomField(id: string) { /* ... */ }

    // Computed: active (non-archived) fields only
    const activeCustomFields = computed(() =>
        customFields.value.filter(f => !f.is_archived).sort((a, b) => a.sort_order - b.sort_order)
    );

    // Computed: required fields
    const requiredCustomFields = computed(() =>
        activeCustomFields.value.filter(f => f.is_required)
    );

    return {
        customFields, activeCustomFields, requiredCustomFields,
        fetchCustomFields, createCustomField, updateCustomField,
        archiveCustomField, restoreCustomField, deleteCustomField,
    };
});
```

### 6.4 TypeScript Types

**File**: `resources/js/types/customFields.d.ts` (NEW)

```typescript
export interface CustomFieldDefinition {
    id: string;
    name: string;
    type: 'text' | 'number' | 'dropdown' | 'checkbox';
    config: CustomFieldConfig;
    is_required: boolean;
    is_archived: boolean;
    sort_order: number;
    created_at: string;
    updated_at: string;
}

export interface CustomFieldConfig {
    options?: string[];
    min?: number;
    max?: number;
    placeholder?: string;
}

export interface CustomFieldValue {
    custom_field_id: string;
    custom_field_name: string;
    custom_field_type: string;
    value: string | number | boolean | null;
}

export interface TimeEntryCustomFields {
    [customFieldId: string]: string | number | boolean | null;
}

export interface TagWithUsage {
    id: string;
    name: string;
    color: string;
    description: string | null;
    time_entry_count: number;
    created_at: string;
    updated_at: string;
}

export interface BulkDeleteResult {
    deleted: string[];
    failed: Array<{ id: string; reason: string }>;
}
```

### 6.5 Color Contrast Utility

**File**: Inline utility used by `TagBadge.vue`

```typescript
function getContrastTextColor(hexColor: string): '#FFFFFF' | '#000000' {
    const r = parseInt(hexColor.slice(1, 3), 16);
    const g = parseInt(hexColor.slice(3, 5), 16);
    const b = parseInt(hexColor.slice(5, 7), 16);
    const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
    return luminance > 0.5 ? '#000000' : '#FFFFFF';
}
```

### 6.6 Page Registration

**Custom Fields Page** -- `routes/web.php`:
```php
Route::get('/custom-fields', function () {
    return Inertia::render('CustomFields');
})->name('custom-fields');
```

**Navigation** -- `resources/js/Layouts/AppLayout.vue`:
The Custom Fields page is accessible from Organization Settings or as a sub-page of Settings. A link should be added in the organization settings area.

---

## 7. Permission Matrix

### 7.1 Existing Permissions (Unchanged)

| Permission | Owner | Admin | Manager | Employee |
|------------|:-----:|:-----:|:-------:|:--------:|
| `tags:view` | Yes | Yes | Yes | Yes |
| `tags:create` | Yes | Yes | Yes | No |
| `tags:update` | Yes | Yes | Yes | No |
| `tags:delete` | Yes | Yes | Yes | No |

### 7.2 New Permissions

| Permission | Owner | Admin | Manager | Employee |
|------------|:-----:|:-----:|:-------:|:--------:|
| `custom-fields:view` | Yes | Yes | Yes | Yes |
| `custom-fields:create` | Yes | Yes | No | No |
| `custom-fields:update` | Yes | Yes | No | No |
| `custom-fields:delete` | Yes | Yes | No | No |

**Registration** in `CorePermissions::register()` (TAG-021):
- Add `custom-fields:view`, `custom-fields:create`, `custom-fields:update`, `custom-fields:delete` to Owner role
- Add `custom-fields:view`, `custom-fields:create`, `custom-fields:update`, `custom-fields:delete` to Admin role
- Add `custom-fields:view` to Manager role
- Add `custom-fields:view` to Employee role

### 7.3 Organization Settings Access

The `mandatory_tags` toggle in organization settings requires `organizations:update` permission (Owner and Admin only).

---

## 8. Performance Strategy

### 8.1 Database

- **Tag usage counts**: Opt-in via `?with_usage_counts=true` to avoid expensive JSONB containment queries on every tag list request
- **JSONB containment**: PostgreSQL's `@>` operator on `time_entries.tags` is GIN-indexed (existing index on `tags` JSONB column)
- **Bulk tag removal**: Batched updates in chunks of 1000 rows to avoid long-running transactions
- **Custom field values**: Unique composite index on `(custom_field_id, entity_type, entity_id)` ensures fast lookups and prevents duplicates
- **Custom field definitions**: Max 20 per organization keeps the field list small and cacheable

### 8.2 Frontend

- **Tag management page**: Client-side search and sort on pre-fetched tag list (no API calls for search)
- **Custom field definitions**: Fetched once on store initialization, cached in Pinia
- **Custom field form rendering**: Computed field list derived from store, no re-fetch on modal open
- **Tag colors**: CSS background-color applied directly, no additional API calls

### 8.3 Targets

| Metric | Target |
|--------|--------|
| GET /tags (without usage counts) | < 200ms |
| GET /tags?with_usage_counts=true (200 tags) | < 500ms |
| GET /custom-fields | < 200ms |
| POST /time-entries with custom fields | < 500ms |
| DELETE /tags/bulk (force, 50 tags) | < 5s |
| Tag management page render (200 tags) | < 1s |

---

## 9. Integration Points

### 9.1 Existing Features -- Interactions

| Feature | Interaction | Impact |
|---------|-------------|--------|
| Time Entry CRUD | Enhanced with mandatory tags validation and custom field values | Modified (TimeEntryStoreRequest, TimeEntryUpdateRequest, TimeEntryController, TimeEntryResource) |
| Weekly Timesheet Grid (Feature 00) | Timesheet cell updates exempt from mandatory tags | Read (checks `mandatory_tags` setting, but exempts) |
| Reporting | Tag filter added to report UI, uses existing `tag_ids` filter and `Tag` aggregation type | Modified (ReportingOverview.vue) |
| Import | Import operations exempt from mandatory tags validation | Read (no changes to import logic) |
| Organization Settings | Mandatory tags toggle added | Modified (OrganizationController update, settings page) |

### 9.2 Future Features -- Designed for Extension

| Feature | Integration Point |
|---------|-------------------|
| Feature 01 (Timesheet Approvals) | May enforce mandatory tags on submission |
| Feature 09 (Advanced Reporting) | Custom field aggregation and filtering |
| Phase B (Custom Fields on Projects/Tasks) | `entity_type` column in `custom_field_values` already supports polymorphism |

### 9.3 No Breaking Changes

- `TagResource` response adds `color` and `description` fields (additive only)
- `TimeEntryResource` response adds `custom_fields` array (additive only)
- No existing fields removed or renamed
- No existing API endpoints removed
- All new endpoints are entirely new paths

---

## 10. File Manifest

### 10.1 New Files (29)

| File | Type | Task |
|------|------|------|
| `database/migrations/2026_03_11_000001_add_color_description_to_tags_table.php` | Migration | TAG-001 |
| `database/migrations/2026_03_11_000002_add_mandatory_tags_to_organizations_table.php` | Migration | TAG-002 |
| `database/migrations/2026_03_11_000003_create_custom_fields_table.php` | Migration | TAG-014 |
| `database/migrations/2026_03_11_000004_create_custom_field_values_table.php` | Migration | TAG-015 |
| `app/Enums/CustomFieldType.php` | Enum | TAG-016 |
| `app/Models/CustomField.php` | Model | TAG-016 |
| `app/Models/CustomFieldValue.php` | Model | TAG-017 |
| `app/Service/TagService.php` | Service | TAG-007 |
| `app/Service/CustomFieldService.php` | Service | TAG-018 |
| `app/Http/Controllers/Api/V1/CustomFieldController.php` | Controller | TAG-019 |
| `app/Http/Requests/V1/Tag/TagBulkDeleteRequest.php` | Request | TAG-007 |
| `app/Http/Requests/V1/CustomField/CustomFieldStoreRequest.php` | Request | TAG-020 |
| `app/Http/Requests/V1/CustomField/CustomFieldUpdateRequest.php` | Request | TAG-020 |
| `app/Http/Resources/V1/CustomField/CustomFieldResource.php` | Resource | TAG-023 |
| `app/Http/Resources/V1/CustomField/CustomFieldCollection.php` | Resource | TAG-023 |
| `database/factories/CustomFieldFactory.php` | Factory | TAG-016 |
| `database/factories/CustomFieldValueFactory.php` | Factory | TAG-017 |
| `resources/js/Pages/CustomFields.vue` | Page | TAG-042 |
| `resources/js/packages/ui/src/Tag/TagEditModal.vue` | Component | TAG-033 |
| `resources/js/packages/ui/src/CustomField/CustomFieldFormRenderer.vue` | Component | TAG-043 |
| `resources/js/packages/ui/src/CustomField/CustomFieldCreateModal.vue` | Component | TAG-042 |
| `resources/js/packages/ui/src/CustomField/CustomFieldEditModal.vue` | Component | TAG-042 |
| `resources/js/packages/ui/src/CustomField/CustomFieldListItem.vue` | Component | TAG-042 |
| `resources/js/packages/ui/src/CustomField/__tests__/CustomFieldFormRenderer.test.ts` | Test | TAG-054 |
| `resources/js/utils/useCustomFields.ts` | Store | TAG-041 |
| `resources/js/types/customFields.d.ts` | Types | TAG-041 |
| `tests/Unit/Endpoint/Api/V1/CustomFieldEndpointTest.php` | Test | TAG-050 |
| `tests/Unit/Service/TagServiceTest.php` | Test | TAG-052 |
| `tests/Unit/Service/CustomFieldServiceTest.php` | Test | TAG-050 |
| `e2e/tags-custom-fields.spec.ts` | Test | TAG-056, TAG-057 |

### 10.2 Modified Files (18)

| File | Change | Task |
|------|--------|------|
| `app/Models/Tag.php` | Add `color`, `description` properties and casts | TAG-003 |
| `app/Models/Organization.php` | Add `mandatory_tags` cast | TAG-004 |
| `app/Http/Requests/V1/Tag/TagStoreRequest.php` | Add `color`, `description` validation rules | TAG-005 |
| `app/Http/Requests/V1/Tag/TagUpdateRequest.php` | Add `color`, `description` validation rules | TAG-006 |
| `app/Http/Controllers/Api/V1/TagController.php` | Enhanced store/update, new bulkDestroy, inject TagService | TAG-007, TAG-008 |
| `app/Http/Resources/V1/Tag/TagResource.php` | Add `color`, `description`, conditional `time_entry_count` | TAG-009 |
| `app/Http/Requests/V1/TimeEntry/TimeEntryStoreRequest.php` | Mandatory tags + custom field validation | TAG-010, TAG-024 |
| `app/Http/Requests/V1/TimeEntry/TimeEntryUpdateRequest.php` | Mandatory tags + custom field validation | TAG-011, TAG-025 |
| `app/Http/Controllers/Api/V1/TimeEntryController.php` | Save/load custom field values | TAG-026, TAG-027 |
| `app/Http/Resources/V1/TimeEntry/TimeEntryResource.php` | Add `custom_fields` array to response | TAG-028 |
| `app/Permissions/CorePermissions.php` | Add custom-field permissions to all roles | TAG-021 |
| `routes/api.php` | Add custom field routes, tag bulk delete route | TAG-022 |
| `resources/js/utils/useTags.ts` | Add updateTag, bulkDeleteTags, fetchTagsWithUsageCounts | TAG-036 |
| `resources/js/Pages/Tags.vue` | Enhanced with search, sort, bulk operations | TAG-037, TAG-038 |
| `resources/js/packages/ui/src/Tag/TagBadge.vue` | Render with background color | TAG-030 |
| `resources/js/packages/ui/src/Tag/TagDropdown.vue` | Show color dots | TAG-031 |
| `resources/js/packages/ui/src/Tag/TagCreateModal.vue` | Color picker, description field | TAG-032 |
| `resources/js/Components/Common/Reporting/ReportingOverview.vue` | Tag filter in filter bar | TAG-039 |
