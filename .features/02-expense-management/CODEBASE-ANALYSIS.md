# Codebase Analysis: Expense Management Feature

**Date**: 2026-02-06  
**Feature**: 02-expense-management  
**Analyst**: Claude (Sonnet 4.5)  
**Purpose**: Comprehensive codebase analysis to inform implementation of Expense Management feature

---

## Executive Summary

This analysis examines Solidtime's existing codebase to identify architectural patterns, service layers, and integration points necessary for implementing the Expense Management feature (PRD 02). The TimeEntry model serves as the primary architectural template, as expenses closely mirror time entries in structure (both are organization-scoped, member-owned, project-linked entities with billable pricing).

**Key Findings**:
- The codebase uses a mature, consistent architecture pattern across all entities
- No existing approval workflow infrastructure exists (must be built from SF-05 shared foundations)
- No notification system exists (must be built from SF-04 shared foundations)
- File upload patterns are minimal (exports only); receipt handling will be new
- Strong audit trail support via CustomAuditable trait
- Comprehensive filter, export, and resource patterns ready to be adapted

---

## 1. TimeEntry Model as Template

**File**: `/home/keven/Documents/solidtime-analysis/app/Models/TimeEntry.php`

### 1.1 Model Structure

```php
class TimeEntry extends Model implements AuditableContract
{
    use ComputedAttributes;     // Line 55 - For calculated fields
    use CustomAuditable;        // Line 56 - Audit trail logging
    use HasFactory;             // Line 59 - Testing factories
    use HasJsonRelationships;   // Line 61 - JSON column relationships
    use HasUuids;               // Line 62 - UUID primary keys
}
```

### 1.2 Key Traits

**HasUuids** (`/home/keven/Documents/solidtime-analysis/app/Models/Concerns/HasUuids.php`):
- Automatically generates UUID primary keys
- Pattern: `$table->uuid('id')->primary()` in migrations

**CustomAuditable** (`/home/keven/Documents/solidtime-analysis/app/Models/Concerns/CustomAuditable.php`):
- Wrapper around `OwenIt\Auditing\Auditable`
- Adds `disableAuditing()` method for performance-critical operations
- Tracks all model changes to `audits` table
- Usage: Define `$auditExclude` array for computed fields (line 116-118 in TimeEntry)

**ComputedAttributes** (from `korridor/laravel-computed-attributes`):
- Used for expensive calculations that should be cached in DB
- Example: `billable_rate` and `client_id` on TimeEntry (line 106-109)
- Artisan commands: `computed-attributes:generate` and `computed-attributes:validate`
- Pattern: Define `$computed` array, implement `get{Field}Computed()` methods

### 1.3 Casts and Attributes

```php
protected $casts = [
    'description' => 'string',
    'start' => 'datetime',
    'end' => 'datetime',
    'billable' => 'bool',
    'tags' => 'array',          // JSONB column, line 74
    'billable_rate' => 'int',   // Stored in cents, line 75
    'is_imported' => 'bool',
    'still_active_email_sent_at' => 'datetime',
];
```

**Expense Model Implications**:
- `amount` should be cast to `int` (cents, matching `billable_rate` pattern)
- `markup_percentage` should be cast to `int`
- `selling_price` should be cast to `int`
- `date` should be cast to `datetime` (or use Carbon accessor)
- `billable` cast to `bool`
- `status` should use enum cast (PHP 8.1+): `'status' => ApprovalStatus::class`

### 1.4 Relationships

```php
// Standard organization-scoped entity pattern:
public function user(): BelongsTo         // Line 179
public function member(): BelongsTo       // Line 187
public function organization(): BelongsTo // Line 195
public function project(): BelongsTo      // Line 203 - nullable
public function task(): BelongsTo         // Line 211 - nullable
public function client(): BelongsTo       // Line 221 - computed, for performance
```

**Expense Model Should Mirror**:
- Same relationships to `user`, `member`, `organization`, `project`, `task`, `client`
- Add: `expenseCategory(): BelongsTo` (nullable)
- Add: `reviewer(): BelongsTo` (to Member, for approval workflow)

### 1.5 Scopes

TimeEntry has no custom scopes beyond computed attribute scopes. The filtering is handled by `TimeEntryFilter` service (see section 3).

**Expense Model Needs**:
- No custom scopes required (filtering via `ExpenseFilter` service)
- Consider adding `scopeEditable()` if implementing shared `HasApprovalWorkflow` trait from SF-05

### 1.6 Computed Attributes

```php
// Line 120-123: Billable rate computation
public function getBillableRateComputed(): ?int
{
    return app(BillableRateService::class)->getBillableRateForTimeEntry($this);
}

// Line 125-128: Client ID denormalization
public function getClientIdComputed(): ?string
{
    return $this->project_id === null || $this->project === null 
        ? null 
        : $this->project->client_id;
}
```

**Expense Model Needs**:
- `getSellingPriceComputed(): ?int` — computes `amount * (1 + markup_percentage / 100)`
- `getClientIdComputed(): ?string` — same pattern as TimeEntry
- Markup cascading logic similar to `BillableRateService` (see section 5)

---

## 2. CRUD Controller Pattern

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/TimeEntryController.php`

### 2.1 Base Controller

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/Controller.php`

```php
class Controller extends \App\Http\Controllers\Controller
{
    public function __construct(
        protected PermissionStore $permissionStore,  // Line 15
    ) {}

    protected function checkPermission(Organization $organization, string $permission): void
    {
        if (! $this->permissionStore->has($organization, $permission)) {
            throw new AuthorizationException;
        }
    }

    protected function checkAnyPermission(Organization $organization, array $permissions): void
    {
        foreach ($permissions as $permission) {
            if ($this->permissionStore->has($organization, $permission)) {
                return;
            }
        }
        throw new AuthorizationException;
    }

    protected function hasPermission(Organization $organization, string $permission): bool
    {
        return $this->permissionStore->has($organization, $permission);
    }

    protected function canAccessPremiumFeatures(Organization $organization): bool
    {
        return app(BillingContract::class)->hasSubscription($organization) 
            || app(BillingContract::class)->hasTrial($organization);
    }
}
```

**Pattern**: All controllers extend this base class and inject `PermissionStore` automatically.

### 2.2 Index Method Pattern

**TimeEntryController::index()** (lines 118-178):

```php
public function index(Organization $organization, TimeEntryIndexRequest $request): JsonResource
{
    /** @var Member|null $member */
    $member = $request->has('member_id') 
        ? Member::query()->findOrFail($request->input('member_id')) 
        : null;
    
    // Permission check: own vs all
    if ($member !== null && $member->user_id === Auth::id()) {
        $this->checkPermission($organization, 'time-entries:view:own');
    } else {
        $this->checkPermission($organization, 'time-entries:view:all');
    }

    $canAccessPremiumFeatures = $this->canAccessPremiumFeatures($organization);
    $timeEntriesQuery = $this->getTimeEntriesQuery($organization, $request, $member, $canAccessPremiumFeatures);

    $totalCount = $timeEntriesQuery->count();

    $limit = $request->getLimit();  // Max 1000
    if ($limit > 1000) {
        $limit = 1000;
    }
    $timeEntriesQuery->limit($limit);
    $timeEntriesQuery->skip($request->getOffset());

    $timeEntries = $timeEntriesQuery->get();

    return (new TimeEntryCollection($timeEntries))
        ->additional([
            'meta' => [
                'total' => $totalCount,
            ],
        ]);
}
```

**Key Patterns**:
- Organization is route-model-bound (injected by Laravel)
- Permission check differentiates `:own` vs `:all` based on `member_id` filter
- Filtering via dedicated service class (`TimeEntryFilter`, see section 3)
- Pagination with `limit` and `offset` query params (max 1000)
- Response wraps collection in `TimeEntryCollection` with `meta.total`

**ExpenseController::index() Should**:
- Follow exact same pattern
- Check `expenses:view:own` vs `expenses:view:all`
- Use `ExpenseFilter` service for filtering
- Return `ExpenseCollection` with `meta.total`

### 2.3 Store Method Pattern

**TimeEntryController::store()** (lines 577-617):

```php
public function store(Organization $organization, TimeEntryStoreRequest $request): JsonResource
{
    /** @var Member $member */
    $member = Member::query()->findOrFail($request->input('member_id'));
    
    // Permission check: own vs all
    if ($member->user_id === Auth::id()) {
        $this->checkPermission($organization, 'time-entries:create:own');
    } else {
        $this->checkPermission($organization, 'time-entries:create:all');
    }

    // Business logic validation (still running check)
    if ($request->input('end') === null && TimeEntry::query()->whereBelongsTo($member, 'member')->where('end', null)->exists()) {
        throw new TimeEntryStillRunningApiException;
    }

    // Overlap check (custom business rule)
    $start = Carbon::parse($request->input('start'));
    $end = $request->input('end') !== null ? Carbon::parse($request->input('end')) : null;
    $this->assertNoOverlap($organization, $member, $start, $end);

    // Load relationships
    $project = $request->input('project_id') !== null 
        ? Project::findOrFail((string) $request->input('project_id')) 
        : null;
    $client = $project?->client;
    $task = $request->input('task_id') !== null 
        ? $project->tasks()->findOrFail((string) $request->input('task_id')) 
        : null;

    // Create model
    $timeEntry = new TimeEntry;
    $timeEntry->fill($request->validated());
    $timeEntry->client()->associate($client);
    $timeEntry->user_id = $member->user_id;
    $timeEntry->description = $request->input('description') ?? '';
    $timeEntry->organization()->associate($organization);
    $timeEntry->setComputedAttributeValue('billable_rate');  // Calculate now
    $timeEntry->save();

    // Side effects
    if ($project !== null) {
        RecalculateSpentTimeForProject::dispatch($project);
    }
    if ($task !== null) {
        RecalculateSpentTimeForTask::dispatch($task);
    }

    return new TimeEntryResource($timeEntry);
}
```

**Key Patterns**:
- Permission check before any DB writes
- Validation via FormRequest (see section 2.5)
- Business rule validation in controller (overlap check)
- Load related entities explicitly (Project, Task, Client)
- Populate computed fields before save: `setComputedAttributeValue('billable_rate')`
- Dispatch background jobs for aggregate updates (spent time)
- Return resource representation

**ExpenseController::store() Should**:
- Check `expenses:create:own` vs `expenses:create:all`
- Load Project, Task, ExpenseCategory, Client relationships
- Compute `client_id` from project (same pattern)
- Compute `selling_price` via `setComputedAttributeValue('selling_price')`
- Initial status: `draft` (unless submitted immediately)
- No background jobs needed (no aggregates to update)
- Return `ExpenseResource`

### 2.4 Update Method Pattern

**TimeEntryController::update()** (lines 626-680):

```php
public function update(Organization $organization, TimeEntry $timeEntry, TimeEntryUpdateRequest $request): JsonResource
{
    /** @var Member|null $member */
    $member = $request->has('member_id') 
        ? Member::query()->findOrFail($request->input('member_id')) 
        : null;
    
    // Permission check: own vs all
    if ($timeEntry->member->user_id === Auth::id() && ($member === null || $member->user_id === Auth::id())) {
        $this->checkPermission($organization, 'time-entries:update:own');
    } else {
        $this->checkPermission($organization, 'time-entries:update:all');
    }

    // Business rule: cannot restart finished entry
    if ($timeEntry->end !== null && $request->has('end') && $request->input('end') === null) {
        throw new TimeEntryCanNotBeRestartedApiException;
    }

    // Overlap check for update (exclude current entry)
    $effectiveMember = $request->has('member_id') 
        ? Member::query()->findOrFail($request->input('member_id')) 
        : $timeEntry->member;
    $effectiveStart = $request->has('start') 
        ? Carbon::parse($request->input('start')) 
        : $timeEntry->start;
    $effectiveEnd = $request->has('end') 
        ? ($request->input('end') !== null ? Carbon::parse($request->input('end')) : null) 
        : $timeEntry->end;
    $this->assertNoOverlap($organization, $effectiveMember, $effectiveStart, $effectiveEnd, $timeEntry);

    $oldProject = $timeEntry->project;
    $oldTask = $timeEntry->task;

    // Update relationships if changed
    $project = null;
    if ($request->has('project_id')) {
        $project = $request->input('project_id') !== null 
            ? Project::findOrFail((string) $request->input('project_id')) 
            : null;
        $client = $project?->client;
        $timeEntry->client()->associate($client);
    }
    $task = null;
    if ($request->has('task_id')) {
        $task = $request->input('task_id') !== null 
            ? Task::findOrFail((string) $request->input('task_id')) 
            : null;
    }

    $timeEntry->fill($request->validated());
    $timeEntry->description = $request->input('description', $timeEntry->description) ?? '';
    $timeEntry->setComputedAttributeValue('billable_rate');
    $timeEntry->save();

    // Recalculate for old and new projects/tasks
    if ($oldProject !== null) {
        RecalculateSpentTimeForProject::dispatch($oldProject);
    }
    if ($oldTask !== null) {
        RecalculateSpentTimeForTask::dispatch($oldTask);
    }
    if ($project !== null && ($oldProject === null || $project->isNot($oldProject))) {
        RecalculateSpentTimeForProject::dispatch($project);
    }
    if ($task !== null && ($oldTask === null || $task->isNot($oldTask))) {
        RecalculateSpentTimeForTask::dispatch($task);
    }

    return new TimeEntryResource($timeEntry);
}
```

**Key Patterns**:
- Route model binding: `TimeEntry $timeEntry` auto-injected
- Permission check compares existing entry owner with current user
- Business rule: prevent state transitions that violate domain logic
- Handle partial updates: only update fields that are present in request
- Recalculate aggregates for both old and new related entities
- Recompute `billable_rate` on every update

**ExpenseController::update() Should**:
- Check `expenses:update:own` vs `expenses:update:all`
- **Business rule: cannot edit approved expenses** (unless admin with force-edit)
- **Business rule: cannot change status via update** (use dedicated approval endpoints)
- Recompute `selling_price` and `client_id` if amount, markup, project, or category changes
- Handle category markup cascade: if category changes, apply new default markup (unless explicit override)
- No aggregate recalculations needed

### 2.5 Request Validation Pattern

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/TimeEntry/TimeEntryStoreRequest.php`

**Base Class**: `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/BaseFormRequest.php`

```php
class BaseFormRequest extends FormRequest
{
    protected function moneyRules(bool $bigInt = false): array
    {
        $rules = [
            'integer',
            'min:0',
        ];
        if ($bigInt) {
            $rules[] = 'max:9223372036854775807';  // PHP_INT_MAX
        } else {
            $rules[] = 'max:2147483647';  // PostgreSQL integer max
        }
        return $rules;
    }
}
```

**TimeEntryStoreRequest::rules()** (lines 29-107):

```php
public function rules(): array
{
    return [
        'member_id' => [
            'required',
            'string',
            ExistsEloquent::make(Member::class, null, function (Builder $builder): Builder {
                return $builder->whereBelongsTo($this->organization, 'organization');
            })->uuid(),
        ],
        'project_id' => [
            'nullable',
            'string',
            'required_with:task_id',  // Task requires project
            ExistsEloquent::make(Project::class, null, function (Builder $builder): Builder {
                $builder = $builder->whereBelongsTo($this->organization, 'organization');
                
                // Visibility check: employees only see public projects or their assigned projects
                $permissionStore = app(PermissionStore::class);
                if (! $permissionStore->has($this->organization, 'time-entries:create:all')
                    && ! $permissionStore->has($this->organization, 'projects:view:all')) {
                    $builder = $builder->visibleByEmployee(Auth::user());
                }
                
                return $builder;
            })->uuid(),
        ],
        'task_id' => [
            'nullable',
            'string',
            // Validate task belongs to organization
            ExistsEloquent::make(Task::class, null, function (Builder $builder): Builder {
                return $builder->whereBelongsTo($this->organization, 'organization');
            })->uuid(),
            // Validate task belongs to specified project
            ExistsEloquent::make(Task::class, null, function (Builder $builder): Builder {
                return $builder->whereBelongsTo($this->organization, 'organization')
                    ->where('project_id', $this->input('project_id'));
            })->uuid()->withMessage(__('validation.task_belongs_to_project')),
        ],
        'start' => [
            'required',
            'date_format:Y-m-d\TH:i:s\Z',  // ISO 8601 UTC
        ],
        'end' => [
            'nullable',
            'date_format:Y-m-d\TH:i:s\Z',
            'after_or_equal:start',
        ],
        'billable' => [
            'required',
            'boolean',
        ],
        'description' => [
            'nullable',
            'string',
            'max:5000',
        ],
        'tags' => [
            'nullable',
            'array',
        ],
        'tags.*' => [
            ExistsEloquent::make(Tag::class, null, function (Builder $builder): Builder {
                return $builder->whereBelongsTo($this->organization, 'organization');
            })->uuid(),
        ],
    ];
}
```

**Key Patterns**:
- Use `ExistsEloquent` from `korridor/laravel-model-validation-rules` for relationship validation
- Scope validation to organization: `whereBelongsTo($this->organization, 'organization')`
- `$this->organization` is available via route model binding in base FormRequest
- Multi-level validation: task must belong to org AND project
- Custom error messages: `withMessage(__('validation.task_belongs_to_project'))`
- ISO 8601 UTC datetime format: `Y-m-d\TH:i:s\Z`
- Money validation via `moneyRules()` helper (integer, min:0, max based on DB type)

**ExpenseStoreRequest::rules() Should Include**:

```php
return [
    'member_id' => [
        'required',
        'string',
        ExistsEloquent::make(Member::class, null, function (Builder $builder): Builder {
            return $builder->whereBelongsTo($this->organization, 'organization');
        })->uuid(),
    ],
    'amount' => array_merge(['required'], $this->moneyRules()),  // integer, min:0, max:2147483647
    'currency' => [
        'nullable',
        'string',
        'size:3',  // ISO 4217
        // Additional validation: must be valid currency code (custom rule)
    ],
    'date' => [
        'required',
        'date_format:Y-m-d',  // Date only, not datetime
    ],
    'description' => [
        'nullable',
        'string',
        'max:5000',
    ],
    'billable' => [
        'required',
        'boolean',
    ],
    'markup_percentage' => [
        'nullable',
        'integer',
        'min:0',
        'max:999',
    ],
    'project_id' => [
        'nullable',
        'string',
        'required_with:task_id',
        ExistsEloquent::make(Project::class, null, function (Builder $builder): Builder {
            $builder = $builder->whereBelongsTo($this->organization, 'organization');
            // Visibility check (same as TimeEntry)
            $permissionStore = app(PermissionStore::class);
            if (! $permissionStore->has($this->organization, 'expenses:create:all')
                && ! $permissionStore->has($this->organization, 'projects:view:all')) {
                $builder = $builder->visibleByEmployee(Auth::user());
            }
            return $builder;
        })->uuid(),
    ],
    'task_id' => [
        'nullable',
        'string',
        ExistsEloquent::make(Task::class, null, function (Builder $builder): Builder {
            return $builder->whereBelongsTo($this->organization, 'organization');
        })->uuid(),
        ExistsEloquent::make(Task::class, null, function (Builder $builder): Builder {
            return $builder->whereBelongsTo($this->organization, 'organization')
                ->where('project_id', $this->input('project_id'));
        })->uuid()->withMessage(__('validation.task_belongs_to_project')),
    ],
    'expense_category_id' => [
        'nullable',
        'string',
        ExistsEloquent::make(ExpenseCategory::class, null, function (Builder $builder): Builder {
            return $builder->whereBelongsTo($this->organization, 'organization')
                ->whereNull('archived_at');  // Only active categories
        })->uuid(),
    ],
];
```

### 2.6 Delete Method Pattern

**TimeEntryController::destroy()** (lines 789-811):

```php
public function destroy(Organization $organization, TimeEntry $timeEntry): JsonResponse
{
    if ($timeEntry->member->user_id === Auth::id()) {
        $this->checkPermission($organization, 'time-entries:delete:own', $timeEntry);
    } else {
        $this->checkPermission($organization, 'time-entries:delete:all', $timeEntry);
    }

    $project = $timeEntry->project;
    $task = $timeEntry->task;

    $timeEntry->delete();

    // Recalculate spent time
    if ($project !== null) {
        RecalculateSpentTimeForProject::dispatch($project);
    }
    if ($task !== null) {
        RecalculateSpentTimeForTask::dispatch($task);
    }

    return response()->json(null, 204);
}
```

**ExpenseController::destroy() Should**:
- Check `expenses:delete:own` vs `expenses:delete:all`
- **Business rule: deleting approved expenses requires `expenses:delete:all`** (not `:own`)
- Delete associated receipt file from storage (if exists)
- No aggregate recalculations needed
- Return 204 No Content

### 2.7 Bulk Operations Pattern

**TimeEntryController::updateMultiple()** (lines 689-780) and **destroyMultiple()** (lines 820-873):

```php
public function updateMultiple(Organization $organization, TimeEntryUpdateMultipleRequest $request): JsonResponse
{
    $this->checkAnyPermission($organization, ['time-entries:update:all', 'time-entries:update:own']);
    $canAccessAll = $this->hasPermission($organization, 'time-entries:update:all');

    $ids = $request->validated('ids');

    $timeEntries = TimeEntry::query()
        ->whereBelongsTo($organization, 'organization')
        ->with(['project', 'task'])
        ->whereIn('id', $ids)
        ->get();

    $changes = $request->validated('changes');

    if ($request->has('changes.description')) {
        $changes['description'] = $request->input('changes.description') ?? '';
    }

    // Reject if trying to change member without all permission
    if (isset($changes['member_id']) && ! $canAccessAll && $this->member($organization)->getKey() !== $changes['member_id']) {
        throw new AuthorizationException;
    }

    // ... (load project, client, task from changes)

    $success = new Collection;
    $error = new Collection;

    foreach ($ids as $id) {
        $timeEntry = $timeEntries->firstWhere('id', $id);
        if ($timeEntry === null) {
            $error->push($id);  // Not found or wrong org
            continue;
        }
        if (! $canAccessAll && $timeEntry->user_id !== Auth::id()) {
            $error->push($id);  // Permission denied
            continue;
        }
        
        // Apply updates
        $oldProject = $timeEntry->project;
        $oldTask = $timeEntry->task;
        $timeEntry->fill($changes);
        // ... (handle project/task reassignment logic)
        $timeEntry->setComputedAttributeValue('billable_rate');
        $timeEntry->save();
        
        // Dispatch recalculation jobs
        // ...
        
        $success->push($id);
    }

    return response()->json([
        'success' => $success->toArray(),
        'error' => $error->toArray(),
    ]);
}
```

**Key Patterns**:
- Bulk endpoints return `{ success: [...ids], error: [...ids] }`
- Check permission globally (`checkAnyPermission`), then per-item in loop
- Load all entities upfront with `whereIn('id', $ids)` and necessary relationships
- Use Laravel Collection `firstWhere()` for O(n) lookup instead of N+1 queries
- Partial success: some items succeed, others fail
- HTTP 200 even if some items failed (errors in response body)

**Expense Bulk Operations**:
- `bulkApprove()` — approve multiple submitted expenses (Manager+)
- `updateMultiple()` — bulk edit draft expenses
- `destroyMultiple()` — bulk delete draft expenses
- Same success/error pattern

---

## 3. Filter Pattern

**File**: `/home/keven/Documents/solidtime-analysis/app/Service/TimeEntryFilter.php`

### 3.1 Filter Service Structure

```php
class TimeEntryFilter
{
    private Builder $builder;  // Line 18

    public function __construct(Builder $builder)
    {
        $this->builder = $builder;
    }

    public function addEndFilter(?string $dateTime): self
    {
        if ($dateTime === null) {
            return $this;
        }
        $this->addEnd(Carbon::createFromFormat('Y-m-d\TH:i:s\Z', $dateTime, 'UTC'));
        return $this;
    }

    public function addEnd(?Carbon $end): self
    {
        if ($end === null) {
            return $this;
        }
        $this->builder->where('start', '<', $end);
        return $this;
    }

    // ... more filter methods ...

    public function get(): Builder
    {
        return $this->builder;
    }
}
```

### 3.2 Filter Methods

All filter methods follow the same pattern:
1. Accept nullable parameter (string or typed value)
2. Return `$this` for method chaining
3. Skip filter if parameter is null
4. Apply query constraint via `$this->builder->where(...)` or `whereIn(...)`

**Available Filters** (lines 28-198):

```php
addEndFilter(?string $dateTime): self         // Line 28
addEnd(?Carbon $end): self                     // Line 38
addStartFilter(?string $dateTime): self        // Line 48
addStart(?Carbon $start): self                 // Line 58
addActiveFilter(?string $active): self         // Line 68 - 'true'|'false' string
addActive(?bool $active): self                 // Line 84
addMemberIdFilter(?Member $member): self       // Line 95
addMemberIdsFilter(?array $memberIds): self    // Line 108
addBillableFilter(?string $billable): self     // Line 118
addBillable(?bool $billable): self             // Line 134
addClientIdsFilter(?array $clientIds): self    // Line 147
addProjectIdsFilter(?array $projectIds): self  // Line 160
addTagIdsFilter(?array $tagIds): self          // Line 173 - JSON contains
addTaskIdsFilter(?array $taskIds): self        // Line 190
```

### 3.3 Usage in Controller

**TimeEntryController::getTimeEntriesQuery()** (lines 183-211):

```php
private function getTimeEntriesQuery(Organization $organization, TimeEntryIndexRequest $request, ?Member $member, bool $canAccessPremiumFeatures): Builder
{
    $timeEntriesQuery = TimeEntry::query()
        ->whereBelongsTo($organization, 'organization')
        ->select(TimeEntry::SELECT_COLUMNS)
        ->orderBy('start', 'desc');

    $filter = new TimeEntryFilter($timeEntriesQuery);
    $filter->addStartFilter($request->input('start'));
    $filter->addEndFilter($request->input('end'));
    $filter->addActiveFilter($request->input('active'));
    $filter->addMemberIdFilter($member);
    $filter->addMemberIdsFilter($request->input('member_ids'));
    $filter->addProjectIdsFilter($request->input('project_ids'));
    $filter->addTagIdsFilter($request->input('tag_ids'));
    $filter->addTaskIdsFilter($request->input('task_ids'));
    $filter->addClientIdsFilter($request->input('client_ids'));
    $filter->addBillableFilter($request->input('billable'));

    return $filter->get();
}
```

**Pattern**:
- Initialize base query with organization scope
- Instantiate filter with builder
- Chain filter methods (order doesn't matter)
- Call `get()` to retrieve modified builder
- Continue chaining (limit, offset, eager loading, etc.)

### 3.4 ExpenseFilter Implementation

**File**: `app/Service/ExpenseFilter.php` (to be created)

```php
class ExpenseFilter
{
    private Builder $builder;

    public function __construct(Builder $builder)
    {
        $this->builder = $builder;
    }

    public function addStartFilter(?string $date): self
    {
        if ($date === null) {
            return $this;
        }
        $this->builder->where('date', '>=', $date);
        return $this;
    }

    public function addEndFilter(?string $date): self
    {
        if ($date === null) {
            return $this;
        }
        $this->builder->where('date', '<=', $date);
        return $this;
    }

    public function addMemberIdFilter(?Member $member): self
    {
        if ($member === null) {
            return $this;
        }
        $this->builder->where('member_id', $member->getKey());
        return $this;
    }

    public function addMemberIdsFilter(?array $memberIds): self
    {
        if ($memberIds === null) {
            return $this;
        }
        $this->builder->whereIn('member_id', $memberIds);
        return $this;
    }

    public function addProjectIdsFilter(?array $projectIds): self
    {
        if ($projectIds === null) {
            return $this;
        }
        $this->builder->whereIn('project_id', $projectIds);
        return $this;
    }

    public function addClientIdsFilter(?array $clientIds): self
    {
        if ($clientIds === null) {
            return $this;
        }
        $this->builder->whereIn('client_id', $clientIds);
        return $this;
    }

    public function addTaskIdsFilter(?array $taskIds): self
    {
        if ($taskIds === null) {
            return $this;
        }
        $this->builder->whereIn('task_id', $taskIds);
        return $this;
    }

    public function addExpenseCategoryIdFilter(?string $categoryId): self
    {
        if ($categoryId === null) {
            return $this;
        }
        $this->builder->where('expense_category_id', $categoryId);
        return $this;
    }

    public function addStatusFilter(?string $status): self
    {
        if ($status === null) {
            return $this;
        }
        // Validate against ApprovalStatus enum
        if (! in_array($status, ['draft', 'submitted', 'approved', 'rejected'])) {
            Log::warning('Invalid status filter value', ['value' => $status]);
            return $this;
        }
        $this->builder->where('status', $status);
        return $this;
    }

    public function addBillableFilter(?string $billable): self
    {
        if ($billable === null) {
            return $this;
        }
        if ($billable === 'true') {
            $this->builder->where('billable', true);
        } elseif ($billable === 'false') {
            $this->builder->where('billable', false);
        } else {
            Log::warning('Invalid billable filter value', ['value' => $billable]);
        }
        return $this;
    }

    public function get(): Builder
    {
        return $this->builder;
    }
}
```

---

## 4. Resource/Response Pattern

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Resources/V1/TimeEntry/TimeEntryResource.php`

### 4.1 Base Resource Class

```php
use App\Http\Resources\V1\BaseResource;

class TimeEntryResource extends BaseResource
{
    /**
     * @property TimeEntry $resource
     */
    
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'start' => $this->formatDateTime($this->resource->start),  // ISO 8601 UTC
            'end' => $this->formatDateTime($this->resource->end),
            'duration' => (int) $this->resource->getDuration()?->totalSeconds,
            'description' => $this->resource->description,
            'task_id' => $this->resource->task_id,
            'project_id' => $this->resource->project_id,
            'organization_id' => $this->resource->organization_id,
            'user_id' => $this->resource->user_id,
            'tags' => $this->resource->tags ?? [],
            'billable' => $this->resource->billable,
        ];
    }
}
```

**BaseResource** must provide `formatDateTime()` helper:

```php
protected function formatDateTime(?Carbon $date): ?string
{
    return $date?->toIso8601ZuluString();  // 2024-02-26T17:17:17Z
}
```

### 4.2 ExpenseResource Implementation

**File**: `app/Http/Resources/V1/Expense/ExpenseResource.php` (to be created)

```php
namespace App\Http\Resources\V1\Expense;

use App\Http\Resources\V1\BaseResource;
use App\Models\Expense;
use Illuminate\Http\Request;

/**
 * @property Expense $resource
 */
class ExpenseResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'amount' => $this->resource->amount,  // integer (cents)
            'currency' => $this->resource->currency,
            'date' => $this->resource->date->format('Y-m-d'),  // Date only
            'description' => $this->resource->description,
            'billable' => $this->resource->billable,
            'markup_percentage' => $this->resource->markup_percentage,
            'selling_price' => $this->resource->selling_price,  // integer (cents)
            'status' => $this->resource->status->value,  // Enum string
            'has_receipt' => $this->resource->receipt_path !== null,
            'receipt_filename' => $this->resource->receipt_filename,
            'reviewer_comment' => $this->resource->reviewer_comment,
            'reviewer_id' => $this->resource->reviewer_id,
            'reviewed_at' => $this->formatDateTime($this->resource->reviewed_at),
            'user_id' => $this->resource->user_id,
            'member_id' => $this->resource->member_id,
            'organization_id' => $this->resource->organization_id,
            'project_id' => $this->resource->project_id,
            'task_id' => $this->resource->task_id,
            'client_id' => $this->resource->client_id,  // Computed
            'expense_category_id' => $this->resource->expense_category_id,
            'created_at' => $this->formatDateTime($this->resource->created_at),
            'updated_at' => $this->formatDateTime($this->resource->updated_at),
        ];
    }
}
```

**Key Patterns**:
- Return all model attributes that API consumers need
- Format dates consistently via `formatDateTime()` helper
- Cast enum to string: `$this->resource->status->value`
- Computed booleans: `has_receipt` instead of exposing internal `receipt_path`
- Return amounts as integers (cents) — frontend handles formatting
- Include both `created_at` and `updated_at` for audit purposes

---

## 5. BillableRateService Pattern (Markup Cascading)

**File**: `/home/keven/Documents/solidtime-analysis/app/Service/BillableRateService.php`

### 5.1 Service Architecture

The `BillableRateService` implements a **cascading priority system** for billable rates:

**Priority Order**:
1. ProjectMember (user-specific rate for a project)
2. Project (project-wide rate)
3. Member (user's default rate)
4. Organization (org-wide default rate)

### 5.2 Runtime Computation

**getBillableRateForTimeEntry()** (lines 102-145):

```php
public function getBillableRateForTimeEntry(TimeEntry $timeEntry): ?int
{
    if (! $timeEntry->billable) {
        return null;  // Non-billable entries have no rate
    }
    
    if ($timeEntry->project_id !== null) {
        // Project member rate
        $projectMember = ProjectMember::query()
            ->where('user_id', '=', $timeEntry->user_id)
            ->where('project_id', '=', $timeEntry->project_id)
            ->first();
        if ($projectMember !== null && $projectMember->billable_rate !== null) {
            return $projectMember->billable_rate;
        }

        // Project rate
        $project = Project::find($timeEntry->project_id);
        if ($project !== null && $project->billable_rate !== null) {
            return $project->billable_rate;
        }
    }
    
    // Member rate
    $member = Member::query()
        ->where('user_id', '=', $timeEntry->user_id)
        ->where('organization_id', '=', $timeEntry->organization_id)
        ->first();
    if ($member !== null && $member->billable_rate !== null) {
        return $member->billable_rate;
    }

    // Organization rate
    $organization = Organization::query()
        ->where('id', '=', $timeEntry->organization_id)
        ->first();
    if ($organization !== null && $organization->billable_rate !== null) {
        return $organization->billable_rate;
    }

    return null;  // No rate configured anywhere
}
```

**Performance Note**: This method is called every time a computed attribute is regenerated. For production performance, rates are cached in the DB via `billable_rate` column on `time_entries` table and only recomputed when cascading sources change.

### 5.3 Bulk Updates on Source Changes

When a rate source changes, all affected time entries must be recalculated:

**updateTimeEntriesBillableRateForProject()** (lines 25-40):

```php
public function updateTimeEntriesBillableRateForProject(Project $project): void
{
    TimeEntry::query()
        ->where('billable', '=', true)
        ->where('organization_id', '=', $project->organization_id)
        ->whereBelongsTo($project, 'project')
        // Exclude entries that have a ProjectMember rate (higher priority)
        ->whereDoesntHave('member', function (Builder $query) use ($project): void {
            $query->whereHas('projectMembers', function (Builder $query) use ($project): void {
                $query->whereBelongsTo($project, 'project')
                    ->whereNotNull('billable_rate');
            });
        })
        ->update(['billable_rate' => $project->billable_rate]);
}
```

**Key Pattern**:
- Use `whereDoesntHave()` to exclude entries with higher-priority rates
- Bulk update via `->update([...])` for performance
- Only update if `billable = true`

**Triggers**:
- When a Project's `billable_rate` changes, call this method
- When a ProjectMember's `billable_rate` changes, call `updateTimeEntriesBillableRateForProjectMember()`
- When a Member's `billable_rate` changes, call `updateTimeEntriesBillableRateForMember()`
- When an Organization's `billable_rate` changes, call `updateTimeEntriesBillableRateForOrganization()`

### 5.4 ExpenseMarkupService (New Service)

**File**: `app/Service/ExpenseMarkupService.php` (to be created)

Similar to `BillableRateService`, but for expense markup percentages:

**Markup Cascade Priority**:
1. Expense-level `markup_percentage` (explicit override)
2. ExpenseCategory `default_markup` (category default)
3. 0% (if no category or no default)

**Implementation**:

```php
namespace App\Service;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Builder;

class ExpenseMarkupService
{
    /**
     * Get the markup percentage for an expense.
     * Priority: expense override > category default > 0%
     */
    public function getMarkupForExpense(Expense $expense): int
    {
        if (! $expense->billable) {
            return 0;  // Non-billable expenses have no markup
        }

        // Explicit expense-level override
        if ($expense->markup_percentage !== null) {
            return $expense->markup_percentage;
        }

        // Category default
        if ($expense->expense_category_id !== null) {
            $category = ExpenseCategory::find($expense->expense_category_id);
            if ($category !== null && $category->default_markup !== null) {
                return $category->default_markup;
            }
        }

        return 0;  // No markup configured
    }

    /**
     * Calculate selling price based on amount and markup.
     * Formula: amount * (1 + markup / 100), rounded to nearest cent
     */
    public function calculateSellingPrice(int $amount, int $markupPercentage): int
    {
        if ($markupPercentage === 0) {
            return $amount;
        }
        
        $multiplier = 1 + ($markupPercentage / 100.0);
        $sellingPrice = $amount * $multiplier;
        
        return (int) round($sellingPrice);
    }

    /**
     * Recompute selling price for an expense.
     * Used in Expense model's getSellingPriceComputed() method.
     */
    public function getSellingPriceForExpense(Expense $expense): ?int
    {
        if (! $expense->billable) {
            return null;  // Non-billable expenses have no selling price
        }

        $markup = $this->getMarkupForExpense($expense);
        return $this->calculateSellingPrice($expense->amount, $markup);
    }

    /**
     * Update selling prices when category default markup changes.
     * Only affects expenses that:
     * 1. Belong to this category
     * 2. Are billable
     * 3. Have no explicit markup override (markup_percentage IS NULL)
     * 4. Are in draft or submitted status (not approved/rejected)
     */
    public function updateSellingPricesForCategoryMarkupChange(ExpenseCategory $category): void
    {
        $expenses = Expense::query()
            ->where('billable', true)
            ->where('expense_category_id', $category->id)
            ->whereNull('markup_percentage')  // No explicit override
            ->whereIn('status', ['draft', 'submitted'])  // Not finalized
            ->get();

        foreach ($expenses as $expense) {
            $expense->setComputedAttributeValue('selling_price');
            $expense->save();
        }
    }

    /**
     * Update a single expense's selling price.
     * Called when amount, markup, or category changes.
     */
    public function recalculateSellingPrice(Expense $expense): void
    {
        $expense->setComputedAttributeValue('selling_price');
        $expense->save();
    }
}
```

**Usage in Expense Model**:

```php
// In Expense.php
public function getSellingPriceComputed(): ?int
{
    return app(ExpenseMarkupService::class)->getSellingPriceForExpense($this);
}
```

**Triggers**:
- When ExpenseCategory `default_markup` changes, call `updateSellingPricesForCategoryMarkupChange()`
- When Expense `amount`, `markup_percentage`, or `expense_category_id` changes, call `recalculateSellingPrice()`
- When Expense `billable` flag changes from `true` to `false`, set `selling_price` to null

---

## 6. Export Pattern

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/TimeEntryController.php`

### 6.1 Export Endpoint

**TimeEntryController::indexExport()** (lines 220-335):

```php
public function indexExport(Organization $organization, TimeEntryIndexExportRequest $request, TimeEntryAggregationService $timeEntryAggregationService): JsonResponse
{
    // Permission check (same as index)
    $member = $request->has('member_id') ? Member::query()->findOrFail($request->input('member_id')) : null;
    if ($member !== null && $member->user_id === Auth::id()) {
        $this->checkPermission($organization, 'time-entries:view:own');
    } else {
        $this->checkPermission($organization, 'time-entries:view:all');
    }

    $canAccessPremiumFeatures = $this->canAccessPremiumFeatures($organization);
    $debug = $request->getDebug();
    $format = $request->getFormatValue();  // ExportFormat enum
    
    // PDF export is premium-only
    if ($format === ExportFormat::PDF && ! $canAccessPremiumFeatures) {
        throw new FeatureIsNotAvailableInFreePlanApiException;
    }

    $user = $this->user();
    $timezone = $user->timezone;
    $showBillableRate = $this->member($organization)->role !== Role::Employee->value 
        || $organization->employees_can_see_billable_rates;
    
    $roundingType = $canAccessPremiumFeatures ? $request->getRoundingType() : null;
    $roundingMinutes = $canAccessPremiumFeatures ? $request->getRoundingMinutes() : null;

    // Get filtered query
    $timeEntriesQuery = $this->getTimeEntriesQuery($organization, $request, $member, $canAccessPremiumFeatures);
    $timeEntriesQuery->with(['task', 'client', 'project', 'user', 'tagsRelation']);
    
    $filename = 'time-entries-export-'.now()->format('Y-m-d_H-i-s').'.'.$format->getFileExtension();
    $folderPath = 'exports';
    $path = $folderPath.'/'.$filename;
    $localizationService = LocalizationService::forOrganization($organization);
    
    if ($format === ExportFormat::CSV) {
        $export = new TimeEntriesDetailedCsvExport(config('filesystems.private'), $folderPath, $filename, $timeEntriesQuery, 1000, $timezone);
        $export->export();
    } elseif ($format === ExportFormat::PDF) {
        // PDF generation via Gotenberg (headless Chrome)
        if (config('services.gotenberg.url') === null && ! $debug) {
            throw new PdfRendererIsNotConfiguredException;
        }
        
        // Load Blade template
        $viewFile = file_get_contents(resource_path('views/reports/time-entry-index/pdf.blade.php'));
        $html = Blade::render($viewFile, [
            'timeEntries' => $timeEntriesQuery->get(),
            'aggregatedData' => $aggregatedData,  // From aggregation service
            'timezone' => $timezone,
            'currency' => $organization->currency,
            'start' => $request->getStart()->timezone($timezone),
            'end' => $request->getEnd()->timezone($timezone),
            'localization' => $localizationService,
            'showBillableRate' => $showBillableRate,
        ]);
        
        // Render footer
        $footerViewFile = file_get_contents(resource_path('views/reports/time-entry-index/pdf-footer.blade.php'));
        $footerHtml = Blade::render($footerViewFile);
        
        if ($debug) {
            return response()->json(['html' => $html, 'footer_html' => $footerHtml]);
        }

        // Send to Gotenberg for PDF conversion
        $client = new Client([
            'auth' => config('services.gotenberg.basic_auth_username') !== null 
                ? [config('services.gotenberg.basic_auth_username'), config('services.gotenberg.basic_auth_password')] 
                : null,
        ]);
        $request = Gotenberg::chromium(config('services.gotenberg.url'))
            ->pdf()
            ->assets(Stream::path(resource_path('pdf/Outfit-VariableFont_wght.ttf'), 'outfit.ttf'))
            ->margins(0.39, 0.78, 0.39, 0.39)
            ->paperSize('8.27', '11.7')  // A4
            ->footer(Stream::string('footer', $footerHtml))
            ->html(Stream::string('body', $html));
        
        $tempFolder = TemporaryDirectory::make();
        $filenameTemp = Gotenberg::save($request, $tempFolder->path(), $client);
        Storage::disk(config('filesystems.private'))
            ->putFileAs($folderPath, new File($tempFolder->path($filenameTemp)), $filename);
    } else {
        // XLSX/ODS via Maatwebsite Excel
        Excel::store(
            new TimeEntriesDetailedExport($timeEntriesQuery, $format, $timezone, $localizationService),
            $path,
            config('filesystems.private'),
            $format->getExportPackageType(),
            ['visibility' => 'private']
        );
    }

    // Return signed temporary URL (5 min expiry)
    return response()->json([
        'download_url' => Storage::disk(config('filesystems.private'))
            ->temporaryUrl($path, now()->addMinutes(5)),
    ]);
}
```

### 6.2 Export Format Enum

**File**: `/home/keven/Documents/solidtime-analysis/app/Enums/ExportFormat.php`

```php
enum ExportFormat: string
{
    case CSV = 'csv';
    case PDF = 'pdf';
    case XLSX = 'xlsx';
    case ODS = 'ods';

    public function getFileExtension(): string
    {
        return match ($this) {
            self::CSV => 'csv',
            self::PDF => 'pdf',
            self::XLSX => 'xlsx',
            self::ODS => 'ods',
        };
    }

    public function getExportPackageType(): string
    {
        return match ($this) {
            self::CSV => Excel::CSV,
            self::PDF => Excel::MPDF,
            self::XLSX => Excel::XLSX,
            self::ODS => Excel::ODS,
        };
    }
}
```

### 6.3 Excel Export Class

**File**: `/home/keven/Documents/solidtime-analysis/app/Service/ReportExport/TimeEntriesDetailedExport.php`

```php
class TimeEntriesDetailedExport implements FromQuery, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    private Builder $builder;
    private ExportFormat $exportFormat;
    private string $timezone;
    private LocalizationService $localizationService;

    public function __construct(Builder $builder, ExportFormat $exportFormat, string $timezone, LocalizationService $localizationService)
    {
        $this->builder = $builder;
        $this->exportFormat = $exportFormat;
        $this->timezone = $timezone;
        $this->localizationService = $localizationService;
    }

    public function query(): Builder
    {
        return $this->builder;
    }

    public function headings(): array
    {
        return [
            'Description',
            'Task',
            'Project',
            'Client',
            'User',
            'Start',
            'End',
            'Duration',
            'Duration (decimal)',
            'Billable',
            'Tags',
        ];
    }

    public function map($model): array
    {
        $duration = $model->getDuration();

        if ($this->exportFormat === ExportFormat::XLSX) {
            return [
                $model->description,
                $model->task?->name,
                $model->project?->name,
                $model->client?->name,
                $model->user->name,
                Date::dateTimeToExcel($model->start->timezone($this->timezone)),  // Excel serial date
                $model->end !== null ? Date::dateTimeToExcel($model->end->timezone($this->timezone)) : null,
                $duration !== null ? $this->localizationService->formatInterval($duration) : null,
                $duration?->totalHours,
                $model->billable ? 'Yes' : 'No',
                $model->tagsRelation->pluck('name')->implode(', '),
            ];
        } elseif ($this->exportFormat === ExportFormat::ODS) {
            // ODS doesn't support Excel date format
            return [
                $model->description,
                $model->task?->name,
                $model->project?->name,
                $model->client?->name,
                $model->user->name,
                $model->start->timezone($this->timezone)->format('Y-m-d H:i:s'),
                $model->end?->timezone($this->timezone)?->format('Y-m-d H:i:s'),
                $duration !== null ? $this->localizationService->formatInterval($duration) : null,
                $duration?->totalHours,
                $model->billable ? 'Yes' : 'No',
                $model->tagsRelation->pluck('name')->implode(', '),
            ];
        }
    }

    public function columnFormats(): array
    {
        if ($this->exportFormat === ExportFormat::XLSX) {
            return [
                'F' => 'yyyy-mm-dd hh:mm:ss',  // Start column
                'G' => 'yyyy-mm-dd hh:mm:ss',  // End column
                'I' => NumberFormat::FORMAT_NUMBER_00,  // Duration decimal
            ];
        } elseif ($this->exportFormat === ExportFormat::ODS) {
            return [
                'I' => NumberFormat::FORMAT_NUMBER_00,
            ];
        }
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],  // Bold header row
        ];
    }
}
```

**Implements**:
- `FromQuery` — provides the query builder
- `WithHeadings` — column headers
- `WithMapping` — map each row to array
- `WithColumnFormatting` — format specific columns (dates, numbers)
- `WithStyles` — apply styles (bold headers)
- `ShouldAutoSize` — auto-adjust column widths

### 6.4 Expense Export Classes

**ExpenseDetailedExport** (to be created):

```php
namespace App\Service\ReportExport;

use App\Enums\ExportFormat;
use App\Models\Expense;
use App\Service\LocalizationService;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExpenseDetailedExport implements FromQuery, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    private Builder $builder;
    private ExportFormat $exportFormat;
    private string $timezone;
    private LocalizationService $localizationService;

    public function __construct(Builder $builder, ExportFormat $exportFormat, string $timezone, LocalizationService $localizationService)
    {
        $this->builder = $builder;
        $this->exportFormat = $exportFormat;
        $this->timezone = $timezone;
        $this->localizationService = $localizationService;
    }

    public function query(): Builder
    {
        return $this->builder;
    }

    public function headings(): array
    {
        return [
            'Date',
            'Member',
            'Project',
            'Client',
            'Category',
            'Description',
            'Amount',
            'Currency',
            'Markup %',
            'Selling Price',
            'Billable',
            'Status',
            'Has Receipt',
        ];
    }

    public function map($expense): array
    {
        return [
            $expense->date->format('Y-m-d'),
            $expense->user->name,
            $expense->project?->name,
            $expense->client?->name,
            $expense->expenseCategory?->name,
            $expense->description,
            $expense->amount / 100,  // Convert cents to currency units
            $expense->currency,
            $expense->markup_percentage,
            $expense->selling_price !== null ? $expense->selling_price / 100 : null,
            $expense->billable ? 'Yes' : 'No',
            ucfirst($expense->status->value),
            $expense->receipt_path !== null ? 'Yes' : 'No',
        ];
    }

    public function columnFormats(): array
    {
        if ($this->exportFormat === ExportFormat::XLSX) {
            return [
                'G' => NumberFormat::FORMAT_NUMBER_00,  // Amount
                'I' => '0',  // Markup percentage (integer)
                'J' => NumberFormat::FORMAT_NUMBER_00,  // Selling price
            ];
        } elseif ($this->exportFormat === ExportFormat::ODS) {
            return [
                'G' => NumberFormat::FORMAT_NUMBER_00,
                'J' => NumberFormat::FORMAT_NUMBER_00,
            ];
        }
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
```

**Key Differences from TimeEntry Export**:
- Date column instead of Start/End (expenses are day-level)
- Amount and Selling Price in currency units (divide by 100)
- Markup percentage column
- Status and Receipt indicator columns
- No duration calculations

---

## 7. File Upload Pattern

**Current State**: Solidtime has minimal file upload infrastructure. Only exports are generated and stored.

**File**: `/home/keven/Documents/solidtime-analysis/app/Service/Export/ExportService.php`

### 7.1 Existing Storage Usage

**ExportService::export()** (lines 367-371):

```php
$filename = 'export_'.$organization->getKey().'_'.$timeStamp->format('Y-m-d_H-i-s').'_'.$exportId.'.zip';
Storage::disk(config('filesystems.private'))->putFileAs(
    'exports',
    new File($tempFolder->path('export.zip')),
    $filename
);
```

**Filesystem Configuration**: `/home/keven/Documents/solidtime-analysis/config/filesystems.php`

```php
'default' => env('FILESYSTEM_DISK', 'local'),
'public' => env('PUBLIC_FILESYSTEM_DISK', 'public'),
'private' => env('FILESYSTEM_DISK', 'local'),

'disks' => [
    'local' => [
        'driver' => 'local',
        'root' => storage_path('app'),
        'serve' => true,
        'throw' => true,
    ],
    's3' => [
        'driver' => 's3',
        'key' => env('S3_ACCESS_KEY_ID'),
        'secret' => env('S3_SECRET_ACCESS_KEY'),
        'region' => env('S3_REGION'),
        'bucket' => env('S3_BUCKET'),
        'url' => env('S3_URL'),
        'temporary_url' => env('S3_URL'),
        'endpoint' => env('S3_ENDPOINT'),
        'use_path_style_endpoint' => env('S3_USE_PATH_STYLE_ENDPOINT', false),
        'throw' => true,
    ],
];
```

**Pattern**:
- `config('filesystems.private')` resolves to `FILESYSTEM_DISK` env var (default: `local`)
- Supports S3 for production deployments
- Temporary URLs via `Storage::temporaryUrl($path, $expiry)`

### 7.2 Receipt Upload Implementation

**ExpenseController::uploadReceipt()** (to be created):

```php
/**
 * Upload receipt for an expense
 * 
 * POST /api/v1/organizations/{organization}/expenses/{expense}/receipt
 */
public function uploadReceipt(Organization $organization, Expense $expense, ReceiptUploadRequest $request): JsonResource
{
    // Permission check
    if ($expense->member->user_id === Auth::id()) {
        $this->checkPermission($organization, 'expenses:update:own', $expense);
    } else {
        $this->checkPermission($organization, 'expenses:update:all', $expense);
    }

    // Business rule: cannot upload to approved expense
    if ($expense->status === ApprovalStatus::APPROVED && ! $this->hasPermission($organization, 'expenses:update:all')) {
        throw new AuthorizationException('Cannot modify approved expense');
    }

    /** @var UploadedFile $file */
    $file = $request->file('receipt');
    
    // Delete old receipt if exists
    if ($expense->receipt_path !== null) {
        Storage::disk(config('filesystems.private'))->delete($expense->receipt_path);
    }

    // Generate secure filename
    $extension = $file->getClientOriginalExtension();
    $filename = $expense->id.'.'.$extension;
    $path = 'receipts/'.$organization->id.'/'.$filename;

    // Store file
    Storage::disk(config('filesystems.private'))->putFileAs(
        'receipts/'.$organization->id,
        $file,
        $filename,
        ['visibility' => 'private']
    );

    // Update expense record
    $expense->receipt_path = $path;
    $expense->receipt_filename = $file->getClientOriginalName();
    $expense->save();

    return new ExpenseResource($expense);
}
```

**ReceiptUploadRequest::rules()** (to be created):

```php
public function rules(): array
{
    return [
        'receipt' => [
            'required',
            'file',
            'mimes:jpeg,jpg,png,pdf,heic,webp',
            'max:10240',  // 10 MB in kilobytes
        ],
    ];
}
```

**MIME Type Validation**:
- Laravel's `mimes` rule checks both extension AND MIME type (secure)
- Supported types: `image/jpeg`, `image/png`, `application/pdf`, `image/heic`, `image/webp`

### 7.3 Receipt Download

**ExpenseController::downloadReceipt()** (to be created):

```php
/**
 * Get signed download URL for receipt
 * 
 * GET /api/v1/organizations/{organization}/expenses/{expense}/receipt
 */
public function downloadReceipt(Organization $organization, Expense $expense): JsonResponse
{
    // Permission check
    if ($expense->member->user_id === Auth::id()) {
        $this->checkPermission($organization, 'expenses:view:own', $expense);
    } else {
        $this->checkPermission($organization, 'expenses:view:all', $expense);
    }

    if ($expense->receipt_path === null) {
        throw new NotFoundHttpException('No receipt attached to this expense');
    }

    // Verify file exists
    if (! Storage::disk(config('filesystems.private'))->exists($expense->receipt_path)) {
        throw new NotFoundHttpException('Receipt file not found');
    }

    // Generate signed temporary URL (5 min expiry)
    $url = Storage::disk(config('filesystems.private'))
        ->temporaryUrl($expense->receipt_path, now()->addMinutes(5));

    return response()->json([
        'download_url' => $url,
        'filename' => $expense->receipt_filename,
    ]);
}
```

**Security Considerations**:
- Receipts stored in private disk (not publicly accessible)
- Organization scoping enforced (path includes `organization_id`)
- Signed URLs expire after 5 minutes
- Permission check ensures user can only access receipts for expenses they can view
- File existence check prevents 404 errors from broken references

### 7.4 Receipt Deletion

**ExpenseController::deleteReceipt()** (to be created):

```php
/**
 * Delete receipt from an expense
 * 
 * DELETE /api/v1/organizations/{organization}/expenses/{expense}/receipt
 */
public function deleteReceipt(Organization $organization, Expense $expense): JsonResource
{
    // Permission check
    if ($expense->member->user_id === Auth::id()) {
        $this->checkPermission($organization, 'expenses:update:own', $expense);
    } else {
        $this->checkPermission($organization, 'expenses:update:all', $expense);
    }

    if ($expense->receipt_path === null) {
        throw new NotFoundHttpException('No receipt attached to this expense');
    }

    // Delete file from storage
    Storage::disk(config('filesystems.private'))->delete($expense->receipt_path);

    // Update expense record
    $expense->receipt_path = null;
    $expense->receipt_filename = null;
    $expense->save();

    return new ExpenseResource($expense);
}
```

---

## 8. Project/Client/Task Relationships

**Files**:
- `/home/keven/Documents/solidtime-analysis/app/Models/Project.php`
- `/home/keven/Documents/solidtime-analysis/app/Models/Client.php`
- `/home/keven/Documents/solidtime-analysis/app/Models/Task.php`

### 8.1 Project Model

```php
class Project extends Model implements AuditableContract
{
    use ComputedAttributes;
    use CustomAuditable;
    use HasFactory;
    use HasUuids;

    // Casts
    protected $casts = [
        'name' => 'string',
        'color' => 'string',
        'archived_at' => 'datetime',
        'estimated_time' => 'integer',
        'spent_time' => 'integer',
    ];

    // Computed attributes
    protected array $computed = ['spent_time'];

    // Relationships
    public function organization(): BelongsTo { ... }
    public function client(): BelongsTo { ... }
    public function members(): HasMany { ... }  // ProjectMember pivot
    public function tasks(): HasMany { ... }
    public function timeEntries(): HasMany { ... }

    // Scopes
    public function scopeVisibleByEmployee(Builder $builder, User $user): void
    {
        $builder->where(function (Builder $builder) use ($user): Builder {
            return $builder->where('is_public', '=', true)
                ->orWhereHas('members', function (Builder $builder) use ($user): Builder {
                    return $builder->whereBelongsTo($user, 'user');
                });
        });
    }

    // Accessors
    protected function isArchived(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => isset($attributes['archived_at']),
        );
    }
}
```

**Key Fields**:
- `id` (UUID)
- `name` (string)
- `color` (string, hex)
- `client_id` (UUID, nullable)
- `organization_id` (UUID)
- `billable_rate` (int, nullable, cents per hour)
- `is_public` (bool) — public projects visible to all employees
- `is_billable` (bool)
- `estimated_time` (int, nullable, seconds)
- `spent_time` (int, computed, seconds)
- `archived_at` (timestamp, nullable)

**Visibility Pattern**:
- Employees see public projects OR projects they're assigned to
- Managers/Admins see all projects
- Enforced via `visibleByEmployee()` scope

### 8.2 Client Model

```php
class Client extends Model implements AuditableContract
{
    use CustomAuditable;
    use HasFactory;
    use HasUuids;

    protected $casts = [
        'name' => 'string',
        'archived_at' => 'datetime',
    ];

    public function organization(): BelongsTo { ... }
    public function projects(): HasMany { ... }

    public function scopeVisibleByEmployee(Builder $builder, User $user): Builder
    {
        return $builder->whereHas('projects', function (Builder $builder) use ($user): Builder {
            return $builder->visibleByEmployee($user);
        });
    }

    protected function isArchived(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => isset($attributes['archived_at']),
        );
    }
}
```

**Key Fields**:
- `id` (UUID)
- `name` (string)
- `organization_id` (UUID)
- `archived_at` (timestamp, nullable)

**Visibility Pattern**:
- Employees see clients only if they have access to at least one of the client's projects

### 8.3 Task Model

```php
class Task extends Model implements AuditableContract
{
    use ComputedAttributes;
    use CustomAuditable;
    use HasFactory;
    use HasUuids;

    protected $casts = [
        'name' => 'string',
        'estimated_time' => 'integer',
        'done_at' => 'datetime',
    ];

    protected array $computed = ['spent_time'];

    public function project(): BelongsTo { ... }
    public function organization(): BelongsTo { ... }
    public function timeEntries(): HasMany { ... }

    public function scopeVisibleByEmployee(Builder $builder, User $user): Builder
    {
        return $builder->whereHas('project', function (Builder $builder) use ($user): Builder {
            return $builder->visibleByEmployee($user);
        });
    }

    public function isDone(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => isset($attributes['done_at']),
        );
    }
}
```

**Key Fields**:
- `id` (UUID)
- `name` (string)
- `project_id` (UUID)
- `organization_id` (UUID)
- `estimated_time` (int, nullable, seconds)
- `spent_time` (int, computed, seconds)
- `done_at` (timestamp, nullable) — marks task as completed

**Visibility Pattern**:
- Tasks inherit visibility from their parent project

### 8.4 Expense Relationships

**Expense Model Should Have**:

```php
public function project(): BelongsTo
{
    return $this->belongsTo(Project::class, 'project_id');
}

public function task(): BelongsTo
{
    return $this->belongsTo(Task::class, 'task_id');
}

public function client(): BelongsTo
{
    // Denormalized for performance (same as TimeEntry)
    return $this->belongsTo(Client::class, 'client_id');
}

public function getClientIdComputed(): ?string
{
    return $this->project_id === null || $this->project === null 
        ? null 
        : $this->project->client_id;
}

public function expenseCategory(): BelongsTo
{
    return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
}
```

**ExpenseCategory Model** (to be created):

```php
class ExpenseCategory extends Model implements AuditableContract
{
    use CustomAuditable;
    use HasFactory;
    use HasUuids;

    protected $casts = [
        'name' => 'string',
        'description' => 'string',
        'color' => 'string',
        'default_markup' => 'integer',
        'archived_at' => 'datetime',
    ];

    public function organization(): BelongsTo { ... }
    
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class, 'parent_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'expense_category_id');
    }

    public function isArchived(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => isset($attributes['archived_at']),
        );
    }
}
```

---

## 9. Soft Delete / Archive Patterns

**Codebase Decision**: Solidtime does NOT use Laravel's `SoftDeletes` trait. Instead, it uses an **archive pattern** via `archived_at` timestamp.

### 9.1 Archive Implementation

**Pattern** (from Project, Client, Task):

```php
protected $casts = [
    'archived_at' => 'datetime',
];

protected function isArchived(): Attribute
{
    return Attribute::make(
        get: fn (mixed $value, array $attributes) => isset($attributes['archived_at']),
    );
}
```

**Usage**:
- Archive: `$project->archived_at = now(); $project->save();`
- Unarchive: `$project->archived_at = null; $project->save();`
- Check: `$project->is_archived` (accessor returns bool)

### 9.2 Querying Archived Entities

**Not automatically excluded** from queries (unlike SoftDeletes). Must explicitly filter:

```php
// Exclude archived
Project::query()->whereNull('archived_at')->get();

// Include archived
Project::query()->get();

// Only archived
Project::query()->whereNotNull('archived_at')->get();
```

**Frontend Pattern**:
- List endpoints: exclude archived by default, accept `include_archived=true` query param
- Show endpoints: include archived (allow viewing archived entity details)
- Create/Update: validate that related entities (project, category) are not archived

### 9.3 Expense Category Archive

**ExpenseCategoryController::destroy()** (to be created):

```php
public function destroy(Organization $organization, ExpenseCategory $expenseCategory): JsonResponse
{
    $this->checkPermission($organization, 'expense-categories:delete');

    // Check if any expenses reference this category
    $expenseCount = Expense::query()
        ->where('expense_category_id', $expenseCategory->id)
        ->count();

    if ($expenseCount > 0) {
        return response()->json([
            'message' => 'Cannot delete category. It is referenced by ' . $expenseCount . ' expense(s).',
            'code' => 'category_in_use',
        ], 422);
    }

    // Safe to delete
    $expenseCategory->delete();

    return response()->json(null, 204);
}
```

**Alternative: Archive Instead of Delete**:

```php
public function archive(Organization $organization, ExpenseCategory $expenseCategory): JsonResponse
{
    $this->checkPermission($organization, 'expense-categories:update');

    $expenseCategory->archived_at = now();
    $expenseCategory->save();

    return response()->json(new ExpenseCategoryResource($expenseCategory));
}

public function unarchive(Organization $organization, ExpenseCategory $expenseCategory): JsonResponse
{
    $this->checkPermission($organization, 'expense-categories:update');

    $expenseCategory->archived_at = null;
    $expenseCategory->save();

    return response()->json(new ExpenseCategoryResource($expenseCategory));
}
```

**Recommendation**: Use archive pattern for categories (prevents accidental data loss), use hard delete only after checking for references.

---

## 10. Existing Approval/Notification Infrastructure

**Finding**: Solidtime currently has **no approval workflow or notification system**.

### 10.1 No Notification System

**Grep Results**:
- Searched for `Notification|notification` in `app/` directory: **No results**
- Searched for `approval|approve|reject` in `app/` directory: **No results**

**Implication**: Must build notification infrastructure as defined in SHARED-FOUNDATIONS.md (SF-04).

### 10.2 Shared Foundations Required (SF-04)

**Tasks** (from `.features/SHARED-FOUNDATIONS.md`):

**FOUND-001**: Create Notification Infrastructure Migration
- Create `notifications` table via `php artisan notifications:table`
- Add `notification_preferences` JSON column to `members` table
- Effort: 2 hours

**FOUND-002**: Create Base Notification Classes
- `App\Notifications\BaseNotification` extending `Illuminate\Notifications\Notification`
- Default channels: `['database', 'mail']`
- Respects per-member `notification_preferences`
- Effort: 4 hours

**FOUND-003**: Create Notification Bell UI Component
- `NotificationBell.vue` in AppLayout header
- Polls `/api/v1/organizations/{org}/notifications` every 60 seconds
- Mark as read on click
- Effort: 8 hours

**FOUND-004**: Create Notification API Endpoints
- `GET /api/v1/organizations/{org}/notifications` — list (paginated)
- `PATCH /api/v1/organizations/{org}/notifications/{id}/read` — mark read
- `PATCH /api/v1/organizations/{org}/notifications/read-all`
- `GET /api/v1/organizations/{org}/notifications/unread-count`
- Effort: 6 hours

**FOUND-005**: Add Notification Preferences to Organization Settings
- Per-member toggle for email notifications
- Effort: 4 hours

**Total**: 24 hours (prerequisite for Expense Management approval notifications)

### 10.3 Shared Approval Pattern (SF-05)

**Standard Approval Status Enum** (to be created):

```php
// App\Enums\ApprovalStatus
namespace App\Enums;

enum ApprovalStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';
    case CHANGES_REQUESTED = 'changes_requested';
    case REJECTED = 'rejected';
    case WITHDRAWN = 'withdrawn';
}
```

**Standard Approval Fields** (add to `expenses` table):

```php
$table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'withdrawn'])->default('draft');
$table->timestamp('submitted_at')->nullable();
$table->uuid('reviewer_id')->nullable();
$table->foreign('reviewer_id')->references('id')->on('members')->cascadeOnUpdate()->setNullOnDelete();
$table->timestamp('reviewed_at')->nullable();
$table->text('reviewer_comment')->nullable();
```

**HasApprovalWorkflow Trait** (to be created):

```php
namespace App\Traits;

use App\Enums\ApprovalStatus;
use App\Models\Member;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasApprovalWorkflow
{
    public function isEditable(): bool
    {
        return in_array($this->status, [
            ApprovalStatus::DRAFT, 
            ApprovalStatus::CHANGES_REQUESTED, 
            ApprovalStatus::WITHDRAWN
        ]);
    }

    public function isSubmitted(): bool
    {
        return $this->status === ApprovalStatus::SUBMITTED;
    }

    public function isApproved(): bool
    {
        return $this->status === ApprovalStatus::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === ApprovalStatus::REJECTED;
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'reviewer_id');
    }
}
```

**Expense Model Usage**:

```php
class Expense extends Model implements AuditableContract
{
    use ComputedAttributes;
    use CustomAuditable;
    use HasFactory;
    use HasUuids;
    use HasApprovalWorkflow;  // Add this trait

    protected $casts = [
        'status' => ApprovalStatus::class,  // Enum cast
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        // ...
    ];
}
```

### 10.4 Expense Approval Endpoints (New)

**ExpenseController::submit()** (to be created):

```php
/**
 * Submit expense for approval
 * POST /api/v1/organizations/{organization}/expenses/{expense}/submit
 */
public function submit(Organization $organization, Expense $expense): JsonResource
{
    // Permission check
    if ($expense->member->user_id === Auth::id()) {
        $this->checkPermission($organization, 'expenses:update:own', $expense);
    } else {
        $this->checkPermission($organization, 'expenses:update:all', $expense);
    }

    // Business rule: can only submit from draft or rejected
    if (! in_array($expense->status, [ApprovalStatus::DRAFT, ApprovalStatus::REJECTED])) {
        return response()->json([
            'message' => 'Expense must be in draft or rejected status to submit',
        ], 422);
    }

    $expense->status = ApprovalStatus::SUBMITTED;
    $expense->submitted_at = now();
    $expense->save();

    // Dispatch notification to approvers (FOUND-002)
    $this->notifyApprovers($organization, $expense);

    return new ExpenseResource($expense);
}

private function notifyApprovers(Organization $organization, Expense $expense): void
{
    // Find all members with expenses:approve permission
    $approvers = Member::query()
        ->whereBelongsTo($organization, 'organization')
        ->whereIn('role', [Role::Manager->value, Role::Admin->value, Role::Owner->value])
        ->get();

    foreach ($approvers as $approver) {
        $approver->user->notify(new ExpenseSubmittedNotification($expense, $organization));
    }
}
```

**ExpenseController::approve()** (to be created):

```php
/**
 * Approve expense
 * POST /api/v1/organizations/{organization}/expenses/{expense}/approve
 */
public function approve(Organization $organization, Expense $expense, ExpenseApproveRequest $request): JsonResource
{
    $this->checkPermission($organization, 'expenses:approve');

    // Business rule: can only approve submitted expenses
    if ($expense->status !== ApprovalStatus::SUBMITTED) {
        return response()->json([
            'message' => 'Only submitted expenses can be approved',
        ], 422);
    }

    // Business rule: no self-approval (SF-05)
    $reviewer = $this->member($organization);
    if ($expense->member_id === $reviewer->id) {
        return response()->json([
            'message' => 'You cannot approve your own expense',
        ], 422);
    }

    $expense->status = ApprovalStatus::APPROVED;
    $expense->reviewer_id = $reviewer->id;
    $expense->reviewed_at = now();
    $expense->reviewer_comment = $request->input('comment');
    $expense->save();

    // Notify submitter (FOUND-002)
    $expense->user->notify(new ExpenseApprovedNotification($expense, $organization, $reviewer));

    return new ExpenseResource($expense);
}
```

**ExpenseController::reject()** (to be created):

```php
/**
 * Reject expense
 * POST /api/v1/organizations/{organization}/expenses/{expense}/reject
 */
public function reject(Organization $organization, Expense $expense, ExpenseRejectRequest $request): JsonResource
{
    $this->checkPermission($organization, 'expenses:approve');

    // Business rule: can only reject submitted expenses
    if ($expense->status !== ApprovalStatus::SUBMITTED) {
        return response()->json([
            'message' => 'Only submitted expenses can be rejected',
        ], 422);
    }

    $reviewer = $this->member($organization);

    $expense->status = ApprovalStatus::REJECTED;
    $expense->reviewer_id = $reviewer->id;
    $expense->reviewed_at = now();
    $expense->reviewer_comment = $request->input('comment');  // Required
    $expense->save();

    // Notify submitter (FOUND-002)
    $expense->user->notify(new ExpenseRejectedNotification($expense, $organization, $reviewer));

    return new ExpenseResource($expense);
}
```

**ExpenseRejectRequest::rules()**:

```php
public function rules(): array
{
    return [
        'comment' => [
            'required',
            'string',
            'max:5000',
        ],
    ];
}
```

---

## 11. Database Migration Patterns

**File**: `/home/keven/Documents/solidtime-analysis/database/migrations/2024_01_20_110837_create_time_entries_table.php`

### 11.1 Migration Structure

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('description', 500);
            $table->dateTime('start');
            $table->dateTime('end')->nullable();
            $table->integer('billable_rate')->unsigned()->nullable();
            $table->boolean('billable')->default(false);
            
            // User/Member/Organization relationships
            $table->uuid('user_id');
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            
            $table->uuid('organization_id');
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            
            // Project/Task relationships
            $table->uuid('project_id')->nullable();
            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            
            $table->uuid('task_id')->nullable();
            $table->foreign('task_id')
                ->references('id')
                ->on('tasks')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            
            $table->jsonb('tags')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('start');
            $table->index('end');
            $table->index('billable');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
```

### 11.2 Foreign Key Patterns

**Cascade on Update, Restrict on Delete**:
- `cascadeOnUpdate()` — if parent ID changes, update children (unlikely with UUIDs)
- `restrictOnDelete()` — prevent deletion of parent if children exist
- `setNullOnDelete()` — for nullable FKs (reviewer, client)

**Why Restrict on Delete**:
- Prevents accidental data loss
- User must explicitly reassign or delete child records first
- UI shows clear error: "Cannot delete project. 5 time entries reference it."

**PRD AMD-10**: ON DELETE RESTRICT for expenses → projects/tasks is intentional. UX must handle this with clear error messages.

### 11.3 Expense Migration

**File**: `database/migrations/2026_03_02_000001_create_expenses_table.php` (to be created)

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
        Schema::create('expenses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            
            // Amount and currency
            $table->integer('amount')->unsigned();  // Cents
            $table->string('currency', 3);  // ISO 4217
            $table->date('date');
            $table->string('description', 5000)->default('');
            
            // Billable and pricing
            $table->boolean('billable')->default(false);
            $table->integer('markup_percentage')->unsigned()->nullable();
            $table->integer('selling_price')->unsigned()->nullable();  // Computed
            
            // Approval workflow (SF-05)
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'withdrawn'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->uuid('reviewer_id')->nullable();
            $table->foreign('reviewer_id')
                ->references('id')
                ->on('members')
                ->cascadeOnUpdate()
                ->setNullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reviewer_comment')->nullable();
            
            // Receipt
            $table->string('receipt_path', 500)->nullable();
            $table->string('receipt_filename', 255)->nullable();
            
            // User/Member/Organization relationships
            $table->uuid('user_id');
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            
            $table->uuid('member_id');
            $table->foreign('member_id')
                ->references('id')
                ->on('members')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            
            $table->uuid('organization_id');
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            
            // Project/Task/Client relationships
            $table->uuid('project_id')->nullable();
            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            
            $table->uuid('task_id')->nullable();
            $table->foreign('task_id')
                ->references('id')
                ->on('tasks')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            
            $table->uuid('client_id')->nullable();  // Computed, denormalized
            $table->foreign('client_id')
                ->references('id')
                ->on('clients')
                ->cascadeOnUpdate()
                ->setNullOnDelete();
            
            // Category relationship
            $table->uuid('expense_category_id')->nullable();
            $table->foreign('expense_category_id')
                ->references('id')
                ->on('expense_categories')
                ->cascadeOnUpdate()
                ->setNullOnDelete();
            
            $table->timestamps();

            // Indexes
            $table->index('organization_id');
            $table->index('member_id');
            $table->index('project_id');
            $table->index('status');
            $table->index('date');
            $table->index('billable');
            $table->index('expense_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
```

### 11.4 ExpenseCategory Migration

**File**: `database/migrations/2026_03_02_000002_create_expense_categories_table.php` (to be created)

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
        Schema::create('expense_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->string('description', 1000)->nullable();
            $table->string('color', 7)->nullable();  // Hex color
            $table->integer('default_markup')->unsigned()->nullable();
            
            // Hierarchical (one level)
            $table->uuid('parent_id')->nullable();
            $table->foreign('parent_id')
                ->references('id')
                ->on('expense_categories')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();  // Delete children when parent is deleted
            
            // Organization scope
            $table->uuid('organization_id');
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            
            // Archive
            $table->timestamp('archived_at')->nullable();
            
            $table->timestamps();

            // Indexes
            $table->index('organization_id');
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
```

**Migration Timestamp**: `2026_03_02_` prefix per SF-03 (Expense Management = Feature 02)

---

## 12. Permission Registration Pattern

**File**: `/home/keven/Documents/solidtime-analysis/app/Providers/JetstreamServiceProvider.php`

### 12.1 Current Permission Structure

**Jetstream Roles** (lines 82-147):

```php
Jetstream::role(Role::Owner->value, 'Owner', [
    'charts:view:own',
    'charts:view:all',
    'projects:view',
    'projects:view:all',
    'projects:create',
    'projects:update',
    'projects:delete',
    'time-entries:view:all',
    'time-entries:create:all',
    'time-entries:update:all',
    'time-entries:delete:all',
    'time-entries:view:own',
    'time-entries:create:own',
    'time-entries:update:own',
    'time-entries:delete:own',
    // ... many more
])->description('Owner users can perform any action. There is only one owner per organization.');
```

**Pattern**:
- `{entity}:{action}:{scope}` (e.g., `time-entries:view:own`)
- `{entity}:{action}` (e.g., `projects:create`)
- Scope `own` = user's own data, `all` = organization-wide

### 12.2 Shared Foundations: Modular Permissions (SF-08)

**Recommended Pattern** (from SHARED-FOUNDATIONS.md):

Create `app/Permissions/ExpensePermissions.php`:

```php
<?php

declare(strict_types=1);

namespace App\Permissions;

use App\Enums\Role;
use Laravel\Jetstream\Jetstream;

class ExpensePermissions
{
    public static function register(): void
    {
        // Get existing role permissions
        $ownerPermissions = Jetstream::$permissions[Role::Owner->value] ?? [];
        $adminPermissions = Jetstream::$permissions[Role::Admin->value] ?? [];
        $managerPermissions = Jetstream::$permissions[Role::Manager->value] ?? [];
        $employeePermissions = Jetstream::$permissions[Role::Employee->value] ?? [];

        // Expense permissions
        $expenseAllPermissions = [
            'expenses:view:all',
            'expenses:create:all',
            'expenses:update:all',
            'expenses:delete:all',
            'expenses:approve',
        ];

        $expenseOwnPermissions = [
            'expenses:view:own',
            'expenses:create:own',
            'expenses:update:own',
            'expenses:delete:own',
        ];

        // Expense category permissions
        $categoryPermissions = [
            'expense-categories:view',
            'expense-categories:create',
            'expense-categories:update',
            'expense-categories:delete',
        ];

        // Owner: all permissions
        Jetstream::role(
            Role::Owner->value,
            'Owner',
            array_merge($ownerPermissions, $expenseAllPermissions, $expenseOwnPermissions, $categoryPermissions)
        );

        // Admin: all permissions except ownership transfer
        Jetstream::role(
            Role::Admin->value,
            'Administrator',
            array_merge($adminPermissions, $expenseAllPermissions, $expenseOwnPermissions, $categoryPermissions)
        );

        // Manager: all permissions except categories
        Jetstream::role(
            Role::Manager->value,
            'Manager',
            array_merge($managerPermissions, $expenseAllPermissions, $expenseOwnPermissions, ['expense-categories:view'])
        );

        // Employee: only own permissions
        Jetstream::role(
            Role::Employee->value,
            'Employee',
            array_merge($employeePermissions, $expenseOwnPermissions, ['expense-categories:view'])
        );
    }
}
```

**JetstreamServiceProvider::configurePermissions()** modification:

```php
protected function configurePermissions(): void
{
    Jetstream::defaultApiTokenPermissions([]);

    // Existing role definitions (Owner, Admin, Manager, Employee)
    // ... (lines 82-250+)

    // Feature permissions (modular pattern per SF-08)
    // ExpensePermissions::register();  // Uncomment when implementing
}
```

**Benefits**:
- One file per feature
- No merge conflicts between features
- Conditional registration (e.g., behind feature flags)
- Clean separation of concerns

### 12.3 Expense Permissions Matrix

| Permission | Owner | Admin | Manager | Employee |
|---|:---:|:---:|:---:|:---:|
| `expenses:view:all` | Y | Y | Y | - |
| `expenses:view:own` | Y | Y | Y | Y |
| `expenses:create:all` | Y | Y | Y | - |
| `expenses:create:own` | Y | Y | Y | Y |
| `expenses:update:all` | Y | Y | Y | - |
| `expenses:update:own` | Y | Y | Y | Y |
| `expenses:delete:all` | Y | Y | Y | - |
| `expenses:delete:own` | Y | Y | Y | Y |
| `expenses:approve` | Y | Y | Y | - |
| `expense-categories:view` | Y | Y | Y | Y |
| `expense-categories:create` | Y | Y | - | - |
| `expense-categories:update` | Y | Y | - | - |
| `expense-categories:delete` | Y | Y | - | - |

**Notes**:
- Employees cannot approve expenses (not even their own, per SF-05 no-self-approval rule)
- Managers can approve expenses but cannot manage categories (admin task)
- Deleting approved expenses requires `:all` permission (prevents employees from deleting after approval)

---

## 13. Risk Assessment

### 13.1 Missing Infrastructure

**RISK**: High dependency on shared foundations (SF-04, SF-05) that don't exist yet.

**Impact**:
- Approval workflow cannot be implemented without `ApprovalStatus` enum and `HasApprovalWorkflow` trait
- Notifications cannot be sent without notification infrastructure
- 24 hours of prerequisite work (FOUND-001 through FOUND-005) before any expense approval features

**Mitigation**:
- Implement FOUND tasks first (Sprint 0 or early Sprint 1)
- Expense CRUD features (EXP-001 through EXP-008) can be built in parallel with FOUND tasks
- Approval features (EXP-009 through EXP-014) depend on FOUND completion

### 13.2 File Upload Security

**RISK**: Receipt uploads introduce new attack surface (malicious files, file type spoofing, path traversal).

**Impact**:
- Potential for RCE via malicious file upload
- Storage exhaustion if file sizes not enforced
- GDPR/privacy issues if receipts leak

**Mitigation**:
- Use Laravel's built-in `mimes` validation (checks MIME type AND extension)
- Store files on private disk with organization-scoped paths
- Use signed temporary URLs (5 min expiry)
- Enforce 10 MB file size limit
- Validate file content beyond MIME (e.g., ImageMagick for images, PDF parser for PDFs)
- Rate limit uploads (20 per minute per user, per PRD)
- Implement virus scanning in production (ClamAV or cloud service)

### 13.3 Computed Attribute Performance

**RISK**: `selling_price` and `client_id` are computed attributes that require DB writes on every change.

**Impact**:
- Performance degradation if bulk operations don't batch updates
- Potential for stale data if computed attribute generation fails

**Mitigation**:
- Use `setComputedAttributeValue()` in controllers (not in model setters to avoid N+1)
- Batch recalculations when category markup changes (chunk queries, use queues)
- Add database indexes on `selling_price` for reporting queries
- Monitor query performance with Laravel Telescope

### 13.4 Approval Workflow Edge Cases

**RISK**: Complex state machine transitions can lead to inconsistent data.

**Impact**:
- Expense stuck in invalid state (e.g., approved but no reviewer_id)
- Race conditions if two approvers approve simultaneously
- Audit trail gaps if status changes are not logged

**Mitigation**:
- Use database transactions for all approval actions
- Validate state transitions in controller (only allow valid transitions)
- Rely on `CustomAuditable` trait for automatic audit logging
- Add database constraints: `CHECK (status = 'approved' IMPLIES reviewer_id IS NOT NULL)`
- Implement pessimistic locking for concurrent approval attempts

### 13.5 Foreign Key Cascades

**RISK**: `ON DELETE RESTRICT` on project/task FKs can frustrate users who want to delete projects.

**Impact**:
- User tries to delete project, gets cryptic error
- Admin must manually reassign or delete expenses first
- Poor UX if error message is unclear

**Mitigation**:
- Frontend: before deleting project, check for associated expenses via API
- Show dialog: "This project has 5 expenses. Reassign them first or delete them."
- Backend: return clear 422 error with count: `{"message": "Cannot delete. 5 expenses reference this project.", "code": "project_in_use", "expense_count": 5}`
- Admin UI: provide "Reassign all expenses" bulk action
- Document behavior in PRD and user docs

### 13.6 Currency Handling

**RISK**: Storing amounts in cents assumes 2-decimal currencies. Not all currencies have 2 decimal places (JPY = 0, BHD = 3).

**Impact**:
- Incorrect calculations for non-2-decimal currencies
- Rounding errors when converting cents to currency units

**Mitigation**:
- Use Money library (e.g., `moneyphp/money`) for currency-aware calculations
- Store amounts as integers (smallest unit, e.g., yen, fils)
- Store decimal places per currency (ISO 4217 lookup)
- Frontend: use Intl.NumberFormat for currency display
- Accept: this is a known limitation in v1, document in PRD

### 13.7 Receipt Storage Costs

**RISK**: Unlimited receipt uploads can lead to high storage costs (especially on S3).

**Impact**:
- Storage costs scale linearly with expense count
- Orphaned receipts if expense deletion fails midway

**Mitigation**:
- Enforce 10 MB file size limit
- Compress images before storage (use Intervention Image)
- Delete receipts in model deletion hook: `Expense::deleting(fn => Storage::delete($receipt_path))`
- Implement storage quota per organization (future feature)
- Monitor storage usage via CloudWatch/S3 metrics

---

## 14. Pattern Differences from PRD Assumptions

### 14.1 No Self-Approval Allowed (SF-05 Override)

**PRD Original**: "Manager tries to approve their own expense (allowed)"

**Codebase Reality**: SF-05 enforces **no self-approval** across all approval workflows.

**Impact**:
- Must reject approval if `reviewer_id === expense.member_id`
- Return 422 error with clear message
- Update PRD AMD-04 to reflect this

### 14.2 Expense Status Vocabulary

**PRD Original**: Uses `draft`, `submitted`, `approved`, `rejected`

**SF-05 Shared Pattern**: Defines `draft`, `submitted`, `approved`, `changes_requested`, `rejected`, `withdrawn`

**Resolution**:
- Expense uses: `draft`, `submitted`, `approved`, `rejected`, `withdrawn`
- Skip `changes_requested` (expense approval is binary, not iterative)
- Use `withdrawn` for user-initiated cancellation of submitted expense

**Updated State Machine**:

```
draft ---[submit]--> submitted ---[approve]--> approved
                          |
                          +---[reject]---> rejected ---[resubmit]--> submitted
                          |
                          +---[withdraw]--> withdrawn

submitted ---[withdraw]--> withdrawn
```

### 14.3 Category Markup vs. Explicit Markup

**PRD Pattern**: Markup cascade similar to `BillableRateService`

**Codebase Reality**: `BillableRateService` uses bulk updates when source changes. Markup service must do the same.

**Implication**:
- When category `default_markup` changes, bulk update all draft/submitted expenses with that category
- Approved/rejected expenses are NOT retroactively updated (financial immutability)
- Requires `ExpenseMarkupService::updateSellingPricesForCategoryMarkupChange()`

### 14.4 Receipt Upload is Two-Step (AMD-07)

**PRD Assumption**: Receipt upload happens during expense creation.

**Codebase Reality**: Expense must exist before receipt can be uploaded.

**Pattern**:
1. `POST /expenses` — create expense (returns expense with `has_receipt: false`)
2. `POST /expenses/{id}/receipt` — upload receipt (updates expense with `receipt_path`)

**Frontend Implication**:
- Show loading state between create and upload
- Handle upload failure gracefully (expense exists without receipt)
- Allow retry mechanism

### 14.5 Export Pattern Uses Gotenberg for PDF

**PRD Assumption**: PDF export via MPDF (Maatwebsite Excel).

**Codebase Reality**: Gotenberg (headless Chrome) for PDF generation.

**Implication**:
- PDF exports require Gotenberg service running (docker container)
- Can render complex HTML/CSS with charts (via echarts.js)
- Debug mode returns HTML instead of PDF for development
- Must create Blade templates for expense export PDF views
- Premium feature only (check `canAccessPremiumFeatures()`)

---

## 15. Essential Files for Implementation

### 15.1 Models (Templates)

- `/home/keven/Documents/solidtime-analysis/app/Models/TimeEntry.php` — Primary template
- `/home/keven/Documents/solidtime-analysis/app/Models/Project.php` — Archive pattern
- `/home/keven/Documents/solidtime-analysis/app/Models/Client.php` — Simple model
- `/home/keven/Documents/solidtime-analysis/app/Models/Task.php` — Hierarchical relationships
- `/home/keven/Documents/solidtime-analysis/app/Models/Organization.php` — Organization model
- `/home/keven/Documents/solidtime-analysis/app/Models/Concerns/CustomAuditable.php` — Audit trait
- `/home/keven/Documents/solidtime-analysis/app/Models/Concerns/HasUuids.php` — UUID trait

### 15.2 Controllers (Templates)

- `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/TimeEntryController.php` — Full CRUD + export
- `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/Controller.php` — Base controller

### 15.3 Requests (Templates)

- `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/TimeEntry/TimeEntryStoreRequest.php` — Validation pattern
- `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/BaseFormRequest.php` — Money rules helper

### 15.4 Services (Templates)

- `/home/keven/Documents/solidtime-analysis/app/Service/TimeEntryFilter.php` — Filter service
- `/home/keven/Documents/solidtime-analysis/app/Service/BillableRateService.php` — Cascade logic template
- `/home/keven/Documents/solidtime-analysis/app/Service/ReportExport/TimeEntriesDetailedExport.php` — Excel export
- `/home/keven/Documents/solidtime-analysis/app/Service/Export/ExportService.php` — File storage pattern

### 15.5 Resources (Templates)

- `/home/keven/Documents/solidtime-analysis/app/Http/Resources/V1/TimeEntry/TimeEntryResource.php` — API resource

### 15.6 Enums (Templates)

- `/home/keven/Documents/solidtime-analysis/app/Enums/ExportFormat.php` — Export format enum

### 15.7 Migrations (Templates)

- `/home/keven/Documents/solidtime-analysis/database/migrations/2024_01_20_110837_create_time_entries_table.php` — Migration pattern

### 15.8 Configuration (Reference)

- `/home/keven/Documents/solidtime-analysis/config/filesystems.php` — Storage configuration
- `/home/keven/Documents/solidtime-analysis/routes/api.php` — Route registration pattern
- `/home/keven/Documents/solidtime-analysis/app/Providers/JetstreamServiceProvider.php` — Permission registration

### 15.9 Shared Foundations (Prerequisites)

- `/home/keven/Documents/solidtime-analysis/.features/SHARED-FOUNDATIONS.md` — Cross-feature decisions
- `/home/keven/Documents/solidtime-analysis/.features/02-expense-management/PRD.md` — Feature requirements

---

## 16. Implementation Checklist

### Phase 0: Shared Foundations (Prerequisites)

- [ ] FOUND-001: Create notification infrastructure migration (2h)
- [ ] FOUND-002: Create base notification classes (4h)
- [ ] FOUND-003: Create notification bell UI component (8h)
- [ ] FOUND-004: Create notification API endpoints (6h)
- [ ] FOUND-005: Add notification preferences to org settings (4h)
- [ ] Create `ApprovalStatus` enum (1h)
- [ ] Create `HasApprovalWorkflow` trait (2h)
- [ ] Create `ExpensePermissions` class (2h)

**Total**: ~29 hours

### Phase 1: Core CRUD (No Approvals)

- [ ] Create Expense model with traits and casts
- [ ] Create ExpenseCategory model
- [ ] Create expense and expense_categories migrations
- [ ] Create ExpenseController with index/store/update/destroy
- [ ] Create ExpenseCategoryController
- [ ] Create ExpenseStoreRequest, ExpenseUpdateRequest validation
- [ ] Create ExpenseFilter service
- [ ] Create ExpenseResource and ExpenseCategoryResource
- [ ] Register routes in routes/api.php
- [ ] Create factories for testing

**Parallel**: Frontend components (Vue pages, Pinia stores)

### Phase 2: Markup & Pricing

- [ ] Create ExpenseMarkupService
- [ ] Implement getMarkupForExpense() cascade logic
- [ ] Implement calculateSellingPrice()
- [ ] Add selling_price computed attribute to Expense model
- [ ] Add updateSellingPricesForCategoryMarkupChange() bulk update
- [ ] Hook into category update controller to trigger recalculations

### Phase 3: Receipt Upload

- [ ] Create ReceiptUploadRequest with MIME validation
- [ ] Implement uploadReceipt() controller method
- [ ] Implement downloadReceipt() with signed URLs
- [ ] Implement deleteReceipt() with storage cleanup
- [ ] Add model deletion hook to clean up orphaned files
- [ ] Test file upload security (MIME spoofing, path traversal)

### Phase 4: Approval Workflow

- [ ] Add approval columns to expenses migration
- [ ] Add HasApprovalWorkflow trait to Expense model
- [ ] Create ExpenseSubmittedNotification
- [ ] Create ExpenseApprovedNotification
- [ ] Create ExpenseRejectedNotification
- [ ] Implement submit() controller method
- [ ] Implement approve() controller method
- [ ] Implement reject() controller method
- [ ] Implement bulkApprove() controller method
- [ ] Add approval endpoints to routes/api.php

### Phase 5: Export

- [ ] Create ExpenseDetailedExport class (XLSX/ODS/CSV)
- [ ] Create Blade templates for PDF export
- [ ] Implement exportExpenses() controller method
- [ ] Test Gotenberg integration for PDF
- [ ] Add export route

### Phase 6: Testing

- [ ] Unit tests for ExpenseMarkupService
- [ ] Endpoint tests for ExpenseController (all CRUD)
- [ ] Endpoint tests for ExpenseCategoryController
- [ ] Endpoint tests for approval endpoints
- [ ] Endpoint tests for receipt upload/download/delete
- [ ] Feature tests for export functionality
- [ ] E2E tests for expense creation → approval flow

---

## 17. Conclusion

Solidtime's codebase provides a robust, consistent architectural foundation for implementing the Expense Management feature. The TimeEntry model serves as an excellent template, demonstrating mature patterns for:

- UUID-based models with audit trails
- Computed attributes for performance
- Filter services for flexible querying
- Resource transformers for API responses
- Export functionality with multiple formats
- Permission-based authorization

**Key Takeaways**:

1. **Follow Existing Patterns**: The codebase is highly consistent. Deviating from established patterns will increase maintenance burden.

2. **Dependencies**: Expense Management has hard dependencies on shared foundations (SF-04 notification infrastructure, SF-05 approval pattern). These must be implemented first.

3. **Security First**: Receipt upload introduces new attack vectors. Use Laravel's built-in validation, private storage, and signed URLs.

4. **Performance**: Computed attributes (`selling_price`, `client_id`) must be carefully managed. Use bulk updates and background jobs where appropriate.

5. **Approval Workflow**: Follow SF-05 strictly. No self-approval, clear state machine, mandatory notifications.

6. **File Structure**: Place all expense-related code in dedicated directories (`app/Models/Expense.php`, `app/Http/Controllers/Api/V1/ExpenseController.php`, etc.) to maintain organization.

7. **Testing**: The codebase has excellent test coverage. Match this standard with comprehensive endpoint, service, and E2E tests.

**Estimated Effort**:
- Shared Foundations: 29 hours
- Expense CRUD: 40 hours
- Markup Service: 8 hours
- Receipt Upload: 12 hours
- Approval Workflow: 24 hours
- Export: 16 hours
- Testing: 30 hours

**Total**: ~159 hours (excluding frontend, which is estimated separately in PRD)

This analysis provides the foundation for confident, pattern-consistent implementation of the Expense Management feature.