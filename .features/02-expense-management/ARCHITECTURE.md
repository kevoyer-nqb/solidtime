# Architecture: Expense Management (Feature 02)

**Date**: 2026-02-06
**Feature branch**: `feature/expense-management`
**PRD**: `.features/02-expense-management/PRD.md`
**Shared Foundations**: `.features/SHARED-FOUNDATIONS.md`
**Migration prefix**: `2026_03_02_`

---

## Table of Contents

1. [Data Model Design](#1-data-model-design)
2. [API Contract](#2-api-contract)
3. [Service Layer](#3-service-layer)
4. [Frontend Architecture](#4-frontend-architecture)
5. [Notification Design](#5-notification-design)
6. [File Storage](#6-file-storage)
7. [Export Pipeline](#7-export-pipeline)
8. [Permission Matrix](#8-permission-matrix)
9. [Migration Strategy](#9-migration-strategy)
10. [Integration Points](#10-integration-points)
11. [File Manifest](#11-file-manifest)
12. [Implementation Sequence](#12-implementation-sequence)

---

## 1. Data Model Design

### 1.1 Shared ApprovalStatus Enum (SF-05)

This enum is shared across Features 01, 02, and 07. It lives in the shared `App\Enums` namespace and is created as part of the shared foundations (Phase 0).

```php
<?php

declare(strict_types=1);

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

**Feature 02 uses**: `DRAFT`, `SUBMITTED`, `APPROVED`, `REJECTED`. The `CHANGES_REQUESTED` and `WITHDRAWN` cases exist for Feature 01 (Timesheet Approvals) but are unused by expenses.

### 1.2 HasApprovalWorkflow Trait (SF-05)

Shared trait providing approval state helpers. Created as part of Phase 0.

```php
<?php

declare(strict_types=1);

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
            ApprovalStatus::WITHDRAWN,
            ApprovalStatus::REJECTED,
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

    public function isDraft(): bool
    {
        return $this->status === ApprovalStatus::DRAFT;
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'reviewer_id');
    }
}
```

### 1.3 ExpenseCategory Model

File: `app/Models/ExpenseCategory.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\CustomAuditable;
use App\Models\Concerns\HasUuids;
use Database\Factories\ExpenseCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property string|null $color
 * @property int|null $default_markup
 * @property string|null $parent_id
 * @property string $organization_id
 * @property Carbon|null $archived_at
 * @property-read bool $is_archived
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read ExpenseCategory|null $parent
 * @property-read Collection<int, ExpenseCategory> $children
 * @property-read Collection<int, Expense> $expenses
 *
 * @method static ExpenseCategoryFactory factory()
 */
class ExpenseCategory extends Model implements AuditableContract
{
    use CustomAuditable;

    /** @use HasFactory<ExpenseCategoryFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'name' => 'string',
        'description' => 'string',
        'color' => 'string',
        'default_markup' => 'int',
        'archived_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'parent_id');
    }

    /**
     * @return HasMany<ExpenseCategory, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class, 'parent_id');
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'expense_category_id');
    }

    /**
     * Scope to only root categories (no parent).
     *
     * @param  Builder<ExpenseCategory>  $builder
     */
    public function scopeRoot(Builder $builder): void
    {
        $builder->whereNull('parent_id');
    }

    /**
     * Scope to only active (non-archived) categories.
     *
     * @param  Builder<ExpenseCategory>  $builder
     */
    public function scopeActive(Builder $builder): void
    {
        $builder->whereNull('archived_at');
    }

    /**
     * Matches the Project::isArchived accessor pattern.
     *
     * @return Attribute<bool, never>
     */
    protected function isArchived(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => isset($attributes['archived_at']),
        );
    }
}
```

### 1.4 ExpenseCategoryFactory

File: `database/factories/ExpenseCategoryFactory.php`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExpenseCategory;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseCategory>
 */
class ExpenseCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'description' => $this->faker->optional()->sentence(),
            'color' => $this->faker->optional()->hexColor(),
            'default_markup' => $this->faker->optional()->numberBetween(0, 100),
            'parent_id' => null,
            'organization_id' => Organization::factory(),
            'archived_at' => null,
        ];
    }

    public function forOrganization(Organization $organization): self
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => $organization->getKey(),
        ]);
    }

    public function withParent(ExpenseCategory $parent): self
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->getKey(),
            'organization_id' => $parent->organization_id,
        ]);
    }

    public function archived(): self
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }

    public function withDefaultMarkup(int $markup): self
    {
        return $this->state(fn (array $attributes) => [
            'default_markup' => $markup,
        ]);
    }
}
```

### 1.5 Expense Model

File: `app/Models/Expense.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Models\Concerns\CustomAuditable;
use App\Models\Concerns\HasUuids;
use App\Traits\HasApprovalWorkflow;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Korridor\LaravelComputedAttributes\ComputedAttributes;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property string $id
 * @property int $amount                    Amount in cents
 * @property string $currency               ISO 4217 currency code
 * @property Carbon $date                   Expense date (Y-m-d)
 * @property string $description            Optional description (max 5000)
 * @property bool $billable                 Whether expense is billable
 * @property int|null $markup_percentage    Markup percentage (0-999)
 * @property int|null $selling_price        Computed selling price in cents
 * @property ApprovalStatus $status         Approval status
 * @property Carbon|null $submitted_at      When submitted for approval
 * @property string|null $receipt_path      Storage path to receipt file
 * @property string|null $receipt_filename  Original filename of receipt
 * @property string|null $reviewer_comment  Comment from reviewer
 * @property string|null $reviewer_id       Member ID of reviewer
 * @property Carbon|null $reviewed_at       When the review action occurred
 * @property string $user_id               Owner user ID
 * @property string $member_id             Owner member ID
 * @property string $organization_id       Organization scope
 * @property string|null $project_id       Optional project association
 * @property string|null $task_id          Optional task association
 * @property string|null $client_id        Computed from project
 * @property string|null $expense_category_id  Category association
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Member $member
 * @property-read Organization $organization
 * @property-read Project|null $project
 * @property-read Task|null $task
 * @property-read Client|null $client
 * @property-read ExpenseCategory|null $category
 * @property-read Member|null $reviewer
 *
 * @method static ExpenseFactory factory()
 */
class Expense extends Model implements AuditableContract
{
    use ComputedAttributes;
    use CustomAuditable;

    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    use HasApprovalWorkflow;
    use HasUuids;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'int',
        'currency' => 'string',
        'date' => 'date',
        'description' => 'string',
        'billable' => 'bool',
        'markup_percentage' => 'int',
        'selling_price' => 'int',
        'status' => ApprovalStatus::class,
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public const array SELECT_COLUMNS = [
        'id',
        'amount',
        'currency',
        'date',
        'description',
        'billable',
        'markup_percentage',
        'selling_price',
        'status',
        'submitted_at',
        'receipt_path',
        'receipt_filename',
        'reviewer_comment',
        'reviewer_id',
        'reviewed_at',
        'user_id',
        'member_id',
        'organization_id',
        'project_id',
        'task_id',
        'client_id',
        'expense_category_id',
        'created_at',
        'updated_at',
    ];

    /**
     * Computed attributes stored for query performance.
     *
     * @var string[]
     */
    protected array $computed = [
        'selling_price',
        'client_id',
    ];

    /**
     * @var array<string>
     */
    protected array $auditExclude = [
        'selling_price',
        'receipt_path',
    ];

    /**
     * Compute selling_price.
     * Returns null if not billable, otherwise amount * (1 + markup/100).
     */
    public function getSellingPriceComputed(): ?int
    {
        if (! $this->billable) {
            return null;
        }

        $effectiveMarkup = $this->getEffectiveMarkup();

        return (int) round($this->amount * (1 + ($effectiveMarkup / 100)));
    }

    /**
     * Resolve effective markup: expense-level > category default > 0.
     */
    public function getEffectiveMarkup(): int
    {
        if ($this->markup_percentage !== null) {
            return $this->markup_percentage;
        }

        if ($this->expense_category_id !== null && $this->category !== null && $this->category->default_markup !== null) {
            return $this->category->default_markup;
        }

        return 0;
    }

    /**
     * Compute client_id from project relationship (mirrors TimeEntry pattern).
     */
    public function getClientIdComputed(): ?string
    {
        return $this->project_id === null || $this->project === null ? null : $this->project->client_id;
    }

    /**
     * @param  Builder<Expense>  $builder
     * @param  array<string>  $attributes
     * @return Builder<Expense>
     */
    public function scopeComputedAttributesGenerate(Builder $builder, array $attributes): Builder
    {
        if (in_array('client_id', $attributes, true)) {
            $builder->with([
                'project' => function (Relation $builder): void {
                    /** @var Builder<Project> $builder */
                    $builder->select('id', 'client_id');
                },
            ]);
        }

        if (in_array('selling_price', $attributes, true)) {
            $builder->with([
                'category' => function (Relation $builder): void {
                    /** @var Builder<ExpenseCategory> $builder */
                    $builder->select('id', 'default_markup');
                },
            ]);
        }

        return $builder;
    }

    /**
     * @param  Builder<Expense>  $builder
     * @param  array<string>  $attributes
     * @return Builder<Expense>
     */
    public function scopeComputedAttributesValidate(Builder $builder, array $attributes): Builder
    {
        return $this->scopeComputedAttributesGenerate($builder, $attributes);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    /**
     * Computed from project relationship, stored for performance.
     *
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    // reviewer() is provided by HasApprovalWorkflow trait
}
```

### 1.6 ExpenseFactory

File: `database/factories/ExpenseFactory.php`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ApprovalStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Member;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'amount' => $this->faker->numberBetween(100, 1000000),
            'currency' => 'USD',
            'date' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'description' => $this->faker->optional()->sentence(),
            'billable' => $this->faker->boolean(),
            'markup_percentage' => null,
            'selling_price' => null,
            'status' => ApprovalStatus::DRAFT,
            'submitted_at' => null,
            'receipt_path' => null,
            'receipt_filename' => null,
            'reviewer_comment' => null,
            'reviewer_id' => null,
            'reviewed_at' => null,
            'user_id' => null,
            'member_id' => null,
            'organization_id' => Organization::factory(),
            'project_id' => null,
            'task_id' => null,
            'client_id' => null,
            'expense_category_id' => null,
        ];
    }

    public function forOrganization(Organization $organization): self
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => $organization->getKey(),
            'currency' => $organization->currency,
        ]);
    }

    public function forMember(Member $member): self
    {
        return $this->state(fn (array $attributes) => [
            'member_id' => $member->getKey(),
            'user_id' => $member->user_id,
            'organization_id' => $member->organization_id,
        ]);
    }

    public function forProject(?Project $project): self
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => $project?->getKey(),
            'client_id' => $project?->client_id,
        ]);
    }

    public function forTask(?Task $task): self
    {
        return $this->state(fn (array $attributes) => [
            'task_id' => $task?->getKey(),
            'project_id' => $task?->project?->getKey(),
            'client_id' => $task?->project?->client?->getKey(),
        ]);
    }

    public function forCategory(?ExpenseCategory $category): self
    {
        return $this->state(fn (array $attributes) => [
            'expense_category_id' => $category?->getKey(),
        ]);
    }

    public function billable(): self
    {
        return $this->state(fn (array $attributes) => [
            'billable' => true,
        ]);
    }

    public function notBillable(): self
    {
        return $this->state(fn (array $attributes) => [
            'billable' => false,
            'selling_price' => null,
        ]);
    }

    public function draft(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApprovalStatus::DRAFT,
            'submitted_at' => null,
            'reviewer_id' => null,
            'reviewed_at' => null,
            'reviewer_comment' => null,
        ]);
    }

    public function submitted(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApprovalStatus::SUBMITTED,
            'submitted_at' => now(),
            'reviewer_id' => null,
            'reviewed_at' => null,
            'reviewer_comment' => null,
        ]);
    }

    public function approved(Member $reviewer = null): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApprovalStatus::APPROVED,
            'submitted_at' => now()->subDay(),
            'reviewer_id' => $reviewer?->getKey(),
            'reviewed_at' => now(),
            'reviewer_comment' => 'Approved',
        ]);
    }

    public function rejected(Member $reviewer = null): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApprovalStatus::REJECTED,
            'submitted_at' => now()->subDay(),
            'reviewer_id' => $reviewer?->getKey(),
            'reviewed_at' => now(),
            'reviewer_comment' => $this->faker->sentence(),
        ]);
    }

    public function withReceipt(): self
    {
        return $this->state(fn (array $attributes) => [
            'receipt_path' => 'receipts/test-org/test-expense.pdf',
            'receipt_filename' => 'receipt.pdf',
        ]);
    }

    public function withMarkup(int $percentage): self
    {
        return $this->state(fn (array $attributes) => [
            'markup_percentage' => $percentage,
        ]);
    }
}
```

### 1.7 Migration: expense_categories

File: `database/migrations/2026_03_02_000001_create_expense_categories_table.php`

```sql
-- Equivalent SQL (actual migration is Laravel Blueprint below)
CREATE TABLE expense_categories (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    description VARCHAR(1000) DEFAULT NULL,
    color VARCHAR(7) DEFAULT NULL,
    default_markup INTEGER UNSIGNED DEFAULT NULL,
    parent_id UUID DEFAULT NULL REFERENCES expense_categories(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    organization_id UUID NOT NULL REFERENCES organizations(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    archived_at TIMESTAMP DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_expense_categories_organization_id ON expense_categories(organization_id);
CREATE INDEX idx_expense_categories_parent_id ON expense_categories(parent_id);
```

Laravel migration:

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
            $table->string('color', 7)->nullable();
            $table->integer('default_markup')->unsigned()->nullable();
            $table->uuid('parent_id')->nullable();
            $table->foreign('parent_id')
                ->references('id')
                ->on('expense_categories')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->uuid('organization_id');
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

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

### 1.8 Migration: expenses

File: `database/migrations/2026_03_02_000002_create_expenses_table.php`

```sql
-- Equivalent SQL
CREATE TABLE expenses (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    amount INTEGER NOT NULL,
    currency VARCHAR(3) NOT NULL,
    date DATE NOT NULL,
    description VARCHAR(5000) DEFAULT '',
    billable BOOLEAN NOT NULL DEFAULT false,
    markup_percentage INTEGER UNSIGNED DEFAULT NULL,
    selling_price INTEGER UNSIGNED DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    submitted_at TIMESTAMP DEFAULT NULL,
    receipt_path VARCHAR(500) DEFAULT NULL,
    receipt_filename VARCHAR(255) DEFAULT NULL,
    reviewer_comment VARCHAR(5000) DEFAULT NULL,
    reviewer_id UUID DEFAULT NULL REFERENCES members(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    reviewed_at TIMESTAMP DEFAULT NULL,
    user_id UUID NOT NULL REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    member_id UUID NOT NULL REFERENCES members(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    organization_id UUID NOT NULL REFERENCES organizations(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    project_id UUID DEFAULT NULL REFERENCES projects(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    task_id UUID DEFAULT NULL REFERENCES tasks(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    client_id UUID DEFAULT NULL REFERENCES clients(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    expense_category_id UUID DEFAULT NULL REFERENCES expense_categories(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_expenses_organization_id ON expenses(organization_id);
CREATE INDEX idx_expenses_member_id ON expenses(member_id);
CREATE INDEX idx_expenses_project_id ON expenses(project_id);
CREATE INDEX idx_expenses_status ON expenses(status);
CREATE INDEX idx_expenses_date ON expenses(date);
CREATE INDEX idx_expenses_billable ON expenses(billable);
CREATE INDEX idx_expenses_category_id ON expenses(expense_category_id);
```

Laravel migration:

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
            $table->integer('amount');
            $table->string('currency', 3);
            $table->date('date');
            $table->string('description', 5000)->default('');
            $table->boolean('billable')->default(false);
            $table->integer('markup_percentage')->unsigned()->nullable();
            $table->integer('selling_price')->unsigned()->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->string('receipt_path', 500)->nullable();
            $table->string('receipt_filename', 255)->nullable();
            $table->string('reviewer_comment', 5000)->nullable();
            $table->uuid('reviewer_id')->nullable();
            $table->foreign('reviewer_id')
                ->references('id')
                ->on('members')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
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
            $table->uuid('client_id')->nullable();
            $table->foreign('client_id')
                ->references('id')
                ->on('clients')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->uuid('expense_category_id')->nullable();
            $table->foreign('expense_category_id')
                ->references('id')
                ->on('expense_categories')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->timestamps();

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

**ON DELETE rationale**:
- `project_id` / `task_id`: `RESTRICT` -- prevents deleting a project/task with associated expenses (UX shows error: "expenses must be reassigned or deleted first")
- `expense_category_id` / `client_id`: `SET NULL` -- informational fields, safe to unlink
- `reviewer_id`: `SET NULL` -- reviewer may leave org, review history preserved via audit log
- `user_id` / `member_id` / `organization_id`: `RESTRICT` -- core ownership, never orphan

---

## 2. API Contract

All endpoints are under the `auth:api` + `verified` middleware stack, nested inside the `v1.` route prefix. Organization is injected via route model binding.

### 2.1 Expense Category Endpoints

#### GET /api/v1/organizations/{organization}/expense-categories

**Route name**: `api.v1.expense-categories.index`
**Controller**: `ExpenseCategoryController::index`
**Permission**: `expense-categories:view`
**Middleware**: auth:api, verified

```php
public function index(Organization $organization): ExpenseCategoryCollection
{
    $this->checkPermission($organization, 'expense-categories:view');

    $categories = ExpenseCategory::query()
        ->whereBelongsTo($organization, 'organization')
        ->with('children')
        ->root()
        ->orderBy('name', 'asc')
        ->get();

    return new ExpenseCategoryCollection($categories);
}
```

**Response 200**:
```json
{
    "data": [
        {
            "id": "uuid",
            "name": "Travel",
            "description": "Travel expenses",
            "color": "#3b82f6",
            "default_markup": 15,
            "parent_id": null,
            "is_archived": false,
            "children": [
                {
                    "id": "uuid",
                    "name": "Flights",
                    "description": null,
                    "color": null,
                    "default_markup": null,
                    "parent_id": "parent-uuid",
                    "is_archived": false
                }
            ]
        }
    ]
}
```

#### POST /api/v1/organizations/{organization}/expense-categories

**Route name**: `api.v1.expense-categories.store`
**Controller**: `ExpenseCategoryController::store`
**Permission**: `expense-categories:create`
**Middleware**: auth:api, verified, check-organization-blocked

**Request validation** (`ExpenseCategoryStoreRequest`):
```php
public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        'description' => ['nullable', 'string', 'max:1000'],
        'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        'default_markup' => ['nullable', 'integer', 'min:0', 'max:999'],
        'parent_id' => [
            'nullable',
            'string',
            ExistsEloquent::make(ExpenseCategory::class, null, function (Builder $builder): Builder {
                /** @var Builder<ExpenseCategory> $builder */
                return $builder->whereBelongsTo($this->organization, 'organization')
                    ->whereNull('parent_id'); // Must be a root category
            })->uuid(),
        ],
    ];
}
```

**Response 201**: `ExpenseCategoryResource`

#### PUT /api/v1/organizations/{organization}/expense-categories/{expenseCategory}

**Route name**: `api.v1.expense-categories.update`
**Controller**: `ExpenseCategoryController::update`
**Permission**: `expense-categories:update`
**Middleware**: auth:api, verified, check-organization-blocked

**Request validation** (`ExpenseCategoryUpdateRequest`): Same rules as store, all fields optional.

**Response 200**: `ExpenseCategoryResource`

#### DELETE /api/v1/organizations/{organization}/expense-categories/{expenseCategory}

**Route name**: `api.v1.expense-categories.destroy`
**Controller**: `ExpenseCategoryController::destroy`
**Permission**: `expense-categories:delete`
**Middleware**: auth:api, verified

**Business rule**: Cannot delete if expenses reference this category. Returns 422 via `EntityStillInUseApiException`.

```php
public function destroy(Organization $organization, ExpenseCategory $expenseCategory): JsonResponse
{
    $this->checkPermission($organization, 'expense-categories:delete', $expenseCategory);

    if (Expense::query()->where('expense_category_id', $expenseCategory->getKey())
        ->whereBelongsTo($organization, 'organization')->exists()) {
        throw new EntityStillInUseApiException('expense_category', 'expense');
    }

    $expenseCategory->delete();

    return response()->json(null, 204);
}
```

### 2.2 Expense CRUD Endpoints

#### GET /api/v1/organizations/{organization}/expenses

**Route name**: `api.v1.expenses.index`
**Controller**: `ExpenseController::index`
**Permission**: `expenses:view:own` (own only) | `expenses:view:all`
**Middleware**: auth:api, verified

**Query parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `member_id` | string (uuid) | No | Filter by member |
| `project_ids` | string[] | No | Filter by projects |
| `expense_category_id` | string (uuid) | No | Filter by category |
| `status` | string | No | `draft\|submitted\|approved\|rejected` |
| `billable` | string | No | `true\|false` |
| `start` | string (Y-m-d) | No | Filter date >= start |
| `end` | string (Y-m-d) | No | Filter date <= end |
| `limit` | integer | No | Default 50, max 500 |
| `offset` | integer | No | Default 0 |

```php
public function index(Organization $organization, ExpenseIndexRequest $request): JsonResource
{
    /** @var Member|null $member */
    $member = $request->has('member_id')
        ? Member::query()->findOrFail($request->input('member_id'))
        : null;

    if ($member !== null && $member->user_id === Auth::id()) {
        $this->checkPermission($organization, 'expenses:view:own');
    } else {
        $this->checkPermission($organization, 'expenses:view:all');
    }

    $expensesQuery = Expense::query()
        ->whereBelongsTo($organization, 'organization')
        ->select(Expense::SELECT_COLUMNS)
        ->orderBy('date', 'desc')
        ->orderBy('created_at', 'desc');

    $filter = new ExpenseFilter($expensesQuery);
    $filter->addStartDateFilter($request->input('start'));
    $filter->addEndDateFilter($request->input('end'));
    $filter->addMemberIdFilter($member);
    $filter->addProjectIdsFilter($request->input('project_ids'));
    $filter->addExpenseCategoryIdFilter($request->input('expense_category_id'));
    $filter->addStatusFilter($request->input('status'));
    $filter->addBillableFilter($request->input('billable'));

    $totalCount = $filter->get()->count();

    $limit = min($request->input('limit', 50), 500);
    $offset = $request->input('offset', 0);

    $expenses = $filter->get()
        ->limit($limit)
        ->skip($offset)
        ->get();

    return (new ExpenseCollection($expenses))
        ->additional(['meta' => ['total' => $totalCount]]);
}
```

**Response 200**:
```json
{
    "data": [ /* ExpenseResource[] */ ],
    "meta": { "total": 142 }
}
```

#### POST /api/v1/organizations/{organization}/expenses

**Route name**: `api.v1.expenses.store`
**Controller**: `ExpenseController::store`
**Permission**: `expenses:create:own` | `expenses:create:all`
**Middleware**: auth:api, verified, check-organization-blocked

**Request validation** (`ExpenseStoreRequest`):
```php
public function rules(): array
{
    return [
        'member_id' => [
            'required',
            'string',
            ExistsEloquent::make(Member::class, null, function (Builder $builder): Builder {
                /** @var Builder<Member> $builder */
                return $builder->whereBelongsTo($this->organization, 'organization');
            })->uuid(),
        ],
        'amount' => [
            'required',
            ...$this->moneyRules(),
        ],
        'currency' => [
            'nullable',
            'string',
            'size:3',
        ],
        'date' => [
            'required',
            'date_format:Y-m-d',
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
                /** @var Builder<Project> $builder */
                $builder = $builder->whereBelongsTo($this->organization, 'organization');
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
                /** @var Builder<Task> $builder */
                return $builder->whereBelongsTo($this->organization, 'organization');
            })->uuid(),
            ExistsEloquent::make(Task::class, null, function (Builder $builder): Builder {
                /** @var Builder<Task> $builder */
                return $builder->whereBelongsTo($this->organization, 'organization')
                    ->where('project_id', $this->input('project_id'));
            })->uuid()->withMessage(__('validation.task_belongs_to_project')),
        ],
        'expense_category_id' => [
            'nullable',
            'string',
            ExistsEloquent::make(ExpenseCategory::class, null, function (Builder $builder): Builder {
                /** @var Builder<ExpenseCategory> $builder */
                return $builder->whereBelongsTo($this->organization, 'organization');
            })->uuid(),
        ],
    ];
}
```

**Controller logic**:
```php
public function store(Organization $organization, ExpenseStoreRequest $request, ExpenseService $expenseService): ExpenseResource
{
    /** @var Member $member */
    $member = Member::query()->findOrFail($request->input('member_id'));
    if ($member->user_id === Auth::id()) {
        $this->checkPermission($organization, 'expenses:create:own');
    } else {
        $this->checkPermission($organization, 'expenses:create:all');
    }

    $expense = $expenseService->createExpense(
        organization: $organization,
        member: $member,
        amount: (int) $request->input('amount'),
        currency: $request->input('currency', $organization->currency),
        date: $request->input('date'),
        billable: (bool) $request->input('billable'),
        description: $request->input('description', ''),
        markupPercentage: $request->input('markup_percentage'),
        projectId: $request->input('project_id'),
        taskId: $request->input('task_id'),
        expenseCategoryId: $request->input('expense_category_id'),
    );

    return new ExpenseResource($expense);
}
```

**Response 201**: `ExpenseResource`

#### PUT /api/v1/organizations/{organization}/expenses/{expense}

**Route name**: `api.v1.expenses.update`
**Controller**: `ExpenseController::update`
**Permission**: `expenses:update:own` | `expenses:update:all`
**Middleware**: auth:api, verified, check-organization-blocked

**Business rules**:
- Cannot update expenses in `approved` or `submitted` status unless user has `expenses:update:all` (admin force-edit)
- Recomputes `selling_price` when `amount`, `billable`, or `markup_percentage` changes
- Cannot change `status` via this endpoint (use approval endpoints)

**Response 200**: `ExpenseResource`

#### DELETE /api/v1/organizations/{organization}/expenses/{expense}

**Route name**: `api.v1.expenses.destroy`
**Controller**: `ExpenseController::destroy`
**Permission**: `expenses:delete:own` | `expenses:delete:all`
**Middleware**: auth:api, verified

**Business rule**: Deleting an approved expense requires `expenses:delete:all`.

```php
public function destroy(Organization $organization, Expense $expense, ExpenseService $expenseService): JsonResponse
{
    $this->checkPermission($organization, 'expenses:delete:own', $expense);

    if ($expense->isApproved() && ! $this->hasPermission($organization, 'expenses:delete:all')) {
        throw new AuthorizationException('Cannot delete approved expenses without expenses:delete:all permission');
    }

    $expenseService->deleteExpense($expense);

    return response()->json(null, 204);
}
```

**Response 204**: No content

### 2.3 Receipt Endpoints

#### POST /api/v1/organizations/{organization}/expenses/{expense}/receipt

**Route name**: `api.v1.expenses.upload-receipt`
**Controller**: `ExpenseController::uploadReceipt`
**Permission**: `expenses:update:own` | `expenses:update:all`
**Middleware**: auth:api, verified, check-organization-blocked

**Request validation** (`ExpenseUploadReceiptRequest`):
```php
public function rules(): array
{
    return [
        'receipt' => [
            'required',
            'file',
            'mimes:jpeg,png,pdf,heic,webp',
            'max:10240', // 10 MB
        ],
    ];
}
```

**Controller logic**:
```php
public function uploadReceipt(
    Organization $organization,
    Expense $expense,
    ExpenseUploadReceiptRequest $request,
    ExpenseService $expenseService
): ExpenseResource {
    $this->checkPermission($organization, 'expenses:update:own', $expense);

    $expenseService->uploadReceipt($expense, $request->file('receipt'));

    return new ExpenseResource($expense->fresh());
}
```

**Response 200**: `ExpenseResource` (with `receipt_path` populated)

#### GET /api/v1/organizations/{organization}/expenses/{expense}/receipt

**Route name**: `api.v1.expenses.download-receipt`
**Controller**: `ExpenseController::downloadReceipt`
**Permission**: `expenses:view:own` | `expenses:view:all`

**Response 200**:
```json
{
    "download_url": "https://storage.example.com/signed-url?expiry=5min"
}
```

#### DELETE /api/v1/organizations/{organization}/expenses/{expense}/receipt

**Route name**: `api.v1.expenses.delete-receipt`
**Controller**: `ExpenseController::deleteReceipt`
**Permission**: `expenses:update:own` | `expenses:update:all`

**Response 200**: `ExpenseResource` (with `receipt_path` = null)

### 2.4 Approval Workflow Endpoints

#### POST /api/v1/organizations/{organization}/expenses/{expense}/submit

**Route name**: `api.v1.expenses.submit`
**Permission**: `expenses:update:own` | `expenses:update:all`
**Middleware**: check-organization-blocked
**Valid source statuses**: `draft`, `rejected`

**Response 200**: `ExpenseResource` (status = `submitted`)

#### POST /api/v1/organizations/{organization}/expenses/{expense}/approve

**Route name**: `api.v1.expenses.approve`
**Permission**: `expenses:approve`
**Middleware**: check-organization-blocked
**Valid source status**: `submitted`
**Self-approval**: NOT allowed (SF-05). The reviewer `member_id` must differ from the expense's `member_id`.

**Request body**:
```json
{ "comment": "Looks good (optional)" }
```

**Response 200**: `ExpenseResource` (status = `approved`)

#### POST /api/v1/organizations/{organization}/expenses/{expense}/reject

**Route name**: `api.v1.expenses.reject`
**Permission**: `expenses:approve`
**Middleware**: check-organization-blocked
**Valid source status**: `submitted`
**Self-approval**: NOT allowed.

**Request body**:
```json
{ "comment": "Missing receipt (required)" }
```

**Response 200**: `ExpenseResource` (status = `rejected`)

#### POST /api/v1/organizations/{organization}/expenses/bulk-approve

**Route name**: `api.v1.expenses.bulk-approve`
**Permission**: `expenses:approve`
**Middleware**: check-organization-blocked

**Request body**:
```json
{
    "ids": ["uuid-1", "uuid-2", "uuid-3"],
    "comment": "Batch approved (optional)"
}
```

**Response 200**:
```json
{
    "success": ["uuid-1", "uuid-2"],
    "error": ["uuid-3"]
}
```

#### POST /api/v1/organizations/{organization}/expenses/{expense}/revert

**Route name**: `api.v1.expenses.revert`
**Permission**: `expenses:approve` + Owner/Admin role check
**Middleware**: check-organization-blocked
**Valid source status**: `approved`

**Response 200**: `ExpenseResource` (status = `draft`)

### 2.5 Export Endpoint

#### GET /api/v1/organizations/{organization}/expenses/export

**Route name**: `api.v1.expenses.export`
**Permission**: `expenses:view:all` + `export`
**Query parameters**: Same filters as index + `format` (csv|xlsx|pdf)

**Response 200**:
```json
{
    "download_url": "https://storage.example.com/signed-url?expiry=5min"
}
```

### 2.6 API Resources

**ExpenseResource** (`app/Http/Resources/V1/Expense/ExpenseResource.php`):
```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Expense;

use App\Http\Resources\V1\BaseResource;
use App\Models\Expense;
use Illuminate\Http\Request;

/**
 * @property Expense $resource
 */
class ExpenseResource extends BaseResource
{
    /**
     * @return array<string, string|bool|int|null>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'amount' => $this->resource->amount,
            'currency' => $this->resource->currency,
            'date' => $this->formatDate($this->resource->date),
            'description' => $this->resource->description,
            'billable' => $this->resource->billable,
            'markup_percentage' => $this->resource->markup_percentage,
            'selling_price' => $this->resource->selling_price,
            'status' => $this->resource->status->value,
            'submitted_at' => $this->formatDateTime($this->resource->submitted_at),
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
            'client_id' => $this->resource->client_id,
            'expense_category_id' => $this->resource->expense_category_id,
        ];
    }
}
```

**ExpenseCategoryResource** (`app/Http/Resources/V1/ExpenseCategory/ExpenseCategoryResource.php`):
```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\ExpenseCategory;

use App\Http\Resources\V1\BaseResource;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;

/**
 * @property ExpenseCategory $resource
 */
class ExpenseCategoryResource extends BaseResource
{
    /**
     * @return array<string, string|int|bool|null>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'description' => $this->resource->description,
            'color' => $this->resource->color,
            'default_markup' => $this->resource->default_markup,
            'parent_id' => $this->resource->parent_id,
            'is_archived' => $this->resource->is_archived,
        ];
    }
}
```

### 2.7 Route Registration

Added to `routes/api.php` inside the existing `v1.` + `auth:api` + `verified` group:

```php
// Expense category routes
Route::name('expense-categories.')->prefix('/organizations/{organization}')->group(static function (): void {
    Route::get('/expense-categories', [ExpenseCategoryController::class, 'index'])->name('index');
    Route::post('/expense-categories', [ExpenseCategoryController::class, 'store'])
        ->name('store')->middleware('check-organization-blocked');
    Route::put('/expense-categories/{expenseCategory}', [ExpenseCategoryController::class, 'update'])
        ->name('update')->middleware('check-organization-blocked');
    Route::delete('/expense-categories/{expenseCategory}', [ExpenseCategoryController::class, 'destroy'])
        ->name('destroy');
});

// Expense routes
Route::name('expenses.')->prefix('/organizations/{organization}')->group(static function (): void {
    Route::get('/expenses', [ExpenseController::class, 'index'])->name('index');
    Route::get('/expenses/export', [ExpenseController::class, 'export'])->name('export');
    Route::post('/expenses', [ExpenseController::class, 'store'])
        ->name('store')->middleware('check-organization-blocked');
    Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])
        ->name('update')->middleware('check-organization-blocked');
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('destroy');
    // Receipt
    Route::post('/expenses/{expense}/receipt', [ExpenseController::class, 'uploadReceipt'])
        ->name('upload-receipt')->middleware('check-organization-blocked');
    Route::get('/expenses/{expense}/receipt', [ExpenseController::class, 'downloadReceipt'])
        ->name('download-receipt');
    Route::delete('/expenses/{expense}/receipt', [ExpenseController::class, 'deleteReceipt'])
        ->name('delete-receipt');
    // Approval workflow
    Route::post('/expenses/{expense}/submit', [ExpenseController::class, 'submit'])
        ->name('submit')->middleware('check-organization-blocked');
    Route::post('/expenses/{expense}/approve', [ExpenseController::class, 'approve'])
        ->name('approve')->middleware('check-organization-blocked');
    Route::post('/expenses/{expense}/reject', [ExpenseController::class, 'reject'])
        ->name('reject')->middleware('check-organization-blocked');
    Route::post('/expenses/bulk-approve', [ExpenseController::class, 'bulkApprove'])
        ->name('bulk-approve')->middleware('check-organization-blocked');
    Route::post('/expenses/{expense}/revert', [ExpenseController::class, 'revert'])
        ->name('revert')->middleware('check-organization-blocked');
});
```

---

## 3. Service Layer

### 3.1 ExpenseService

File: `app/Service/ExpenseService.php`

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Enums\ApprovalStatus;
use App\Enums\Role;
use App\Exceptions\Api\ExpenseNotEditableApiException;
use App\Exceptions\Api\InvalidExpenseStatusTransitionApiException;
use App\Exceptions\Api\SelfApprovalNotAllowedApiException;
use App\Models\Expense;
use App\Models\Member;
use App\Models\Organization;
use App\Notifications\ExpenseApprovedNotification;
use App\Notifications\ExpenseRejectedNotification;
use App\Notifications\ExpenseSubmittedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExpenseService
{
    /**
     * Create a new expense with computed fields.
     */
    public function createExpense(
        Organization $organization,
        Member $member,
        int $amount,
        string $currency,
        string $date,
        bool $billable,
        string $description = '',
        ?int $markupPercentage = null,
        ?string $projectId = null,
        ?string $taskId = null,
        ?string $expenseCategoryId = null,
    ): Expense {
        return DB::transaction(function () use (
            $organization, $member, $amount, $currency, $date,
            $billable, $description, $markupPercentage,
            $projectId, $taskId, $expenseCategoryId
        ): Expense {
            $expense = new Expense();
            $expense->amount = $amount;
            $expense->currency = $currency;
            $expense->date = Carbon::createFromFormat('Y-m-d', $date);
            $expense->description = $description;
            $expense->billable = $billable;
            $expense->markup_percentage = $markupPercentage;
            $expense->status = ApprovalStatus::DRAFT;
            $expense->project_id = $projectId;
            $expense->task_id = $taskId;
            $expense->expense_category_id = $expenseCategoryId;
            $expense->member()->associate($member);
            $expense->user_id = $member->user_id;
            $expense->organization()->associate($organization);

            // Compute selling_price and client_id
            $expense->selling_price = $expense->getSellingPriceComputed();
            $expense->client_id = $expense->getClientIdComputed();

            $expense->save();

            Log::info('Expense created', [
                'expense_id' => $expense->id,
                'organization_id' => $expense->organization_id,
                'member_id' => $expense->member_id,
                'amount' => $expense->amount,
                'billable' => $expense->billable,
            ]);

            return $expense;
        });
    }

    /**
     * Update an existing expense. Enforces editability rules.
     */
    public function updateExpense(Expense $expense, array $attributes, bool $forceEdit = false): Expense
    {
        if (! $forceEdit && ! $expense->isEditable()) {
            throw new ExpenseNotEditableApiException();
        }

        return DB::transaction(function () use ($expense, $attributes): Expense {
            foreach ($attributes as $key => $value) {
                if ($key === 'status') {
                    continue; // Status changes go through dedicated methods
                }
                $expense->{$key} = $value;
            }

            // Recompute derived fields
            $expense->selling_price = $expense->getSellingPriceComputed();
            $expense->client_id = $expense->getClientIdComputed();

            $expense->save();

            return $expense;
        });
    }

    /**
     * Delete an expense and its receipt file.
     */
    public function deleteExpense(Expense $expense): void
    {
        DB::transaction(function () use ($expense): void {
            if ($expense->receipt_path !== null) {
                Storage::disk(config('filesystems.private'))->delete($expense->receipt_path);
            }
            $expense->delete();
        });
    }

    /**
     * Submit expense for approval.
     * Valid from: DRAFT, REJECTED
     */
    public function submitExpense(Expense $expense): Expense
    {
        if (! in_array($expense->status, [ApprovalStatus::DRAFT, ApprovalStatus::REJECTED], true)) {
            throw new InvalidExpenseStatusTransitionApiException(
                $expense->status->value,
                ApprovalStatus::SUBMITTED->value
            );
        }

        $expense->status = ApprovalStatus::SUBMITTED;
        $expense->submitted_at = now();
        $expense->reviewer_id = null;
        $expense->reviewed_at = null;
        $expense->reviewer_comment = null;
        $expense->save();

        Log::info('Expense submitted', [
            'expense_id' => $expense->id,
            'old_status' => $expense->getOriginal('status'),
            'new_status' => $expense->status->value,
        ]);

        // Notify approvers (managers/admins/owners in the organization)
        $this->notifyApprovers($expense);

        return $expense;
    }

    /**
     * Approve a submitted expense.
     * Valid from: SUBMITTED
     * Self-approval NOT allowed (SF-05).
     */
    public function approveExpense(Expense $expense, Member $reviewer, ?string $comment = null): Expense
    {
        if ($expense->status !== ApprovalStatus::SUBMITTED) {
            throw new InvalidExpenseStatusTransitionApiException(
                $expense->status->value,
                ApprovalStatus::APPROVED->value
            );
        }

        // SF-05: Self-approval prevention
        if ($expense->member_id === $reviewer->getKey()) {
            throw new SelfApprovalNotAllowedApiException();
        }

        $expense->status = ApprovalStatus::APPROVED;
        $expense->reviewer_id = $reviewer->getKey();
        $expense->reviewed_at = now();
        $expense->reviewer_comment = $comment;
        $expense->save();

        Log::info('Expense approved', [
            'expense_id' => $expense->id,
            'reviewer_id' => $reviewer->getKey(),
        ]);

        // Notify submitter
        $expense->member->user->notify(new ExpenseApprovedNotification($expense));

        return $expense;
    }

    /**
     * Reject a submitted expense. Comment is required.
     * Valid from: SUBMITTED
     * Self-rejection NOT allowed.
     */
    public function rejectExpense(Expense $expense, Member $reviewer, string $comment): Expense
    {
        if ($expense->status !== ApprovalStatus::SUBMITTED) {
            throw new InvalidExpenseStatusTransitionApiException(
                $expense->status->value,
                ApprovalStatus::REJECTED->value
            );
        }

        if ($expense->member_id === $reviewer->getKey()) {
            throw new SelfApprovalNotAllowedApiException();
        }

        $expense->status = ApprovalStatus::REJECTED;
        $expense->reviewer_id = $reviewer->getKey();
        $expense->reviewed_at = now();
        $expense->reviewer_comment = $comment;
        $expense->save();

        Log::info('Expense rejected', [
            'expense_id' => $expense->id,
            'reviewer_id' => $reviewer->getKey(),
            'comment' => $comment,
        ]);

        // Notify submitter
        $expense->member->user->notify(new ExpenseRejectedNotification($expense));

        return $expense;
    }

    /**
     * Revert an approved expense to draft. Admin/Owner only.
     * Valid from: APPROVED
     */
    public function revertExpense(Expense $expense, Member $reviewer): Expense
    {
        if ($expense->status !== ApprovalStatus::APPROVED) {
            throw new InvalidExpenseStatusTransitionApiException(
                $expense->status->value,
                ApprovalStatus::DRAFT->value
            );
        }

        if (! in_array($reviewer->role, [Role::Owner->value, Role::Admin->value], true)) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'Only Owner or Admin can revert approved expenses'
            );
        }

        $expense->status = ApprovalStatus::DRAFT;
        $expense->submitted_at = null;
        $expense->reviewer_id = null;
        $expense->reviewed_at = null;
        $expense->reviewer_comment = null;
        $expense->save();

        Log::info('Expense reverted to draft', [
            'expense_id' => $expense->id,
            'reverted_by' => $reviewer->getKey(),
        ]);

        return $expense;
    }

    /**
     * Upload a receipt file to an expense.
     * Replaces existing receipt if present.
     */
    public function uploadReceipt(Expense $expense, UploadedFile $file): Expense
    {
        return DB::transaction(function () use ($expense, $file): Expense {
            // Delete old receipt if exists
            if ($expense->receipt_path !== null) {
                Storage::disk(config('filesystems.private'))->delete($expense->receipt_path);
            }

            $extension = $file->getClientOriginalExtension();
            $path = 'receipts/'.$expense->organization_id.'/'.$expense->id.'.'.$extension;

            Storage::disk(config('filesystems.private'))->putFileAs(
                'receipts/'.$expense->organization_id,
                $file,
                $expense->id.'.'.$extension
            );

            $expense->receipt_path = $path;
            $expense->receipt_filename = $file->getClientOriginalName();
            $expense->save();

            return $expense;
        });
    }

    /**
     * Generate a signed temporary URL for receipt download.
     */
    public function getReceiptDownloadUrl(Expense $expense): string
    {
        return Storage::disk(config('filesystems.private'))
            ->temporaryUrl($expense->receipt_path, now()->addMinutes(5));
    }

    /**
     * Delete the receipt file from an expense.
     */
    public function deleteReceipt(Expense $expense): Expense
    {
        if ($expense->receipt_path !== null) {
            Storage::disk(config('filesystems.private'))->delete($expense->receipt_path);
        }

        $expense->receipt_path = null;
        $expense->receipt_filename = null;
        $expense->save();

        return $expense;
    }

    /**
     * When a category default_markup changes, update draft/submitted
     * expenses that rely on the category default (no expense-level override).
     */
    public function propagateCategoryMarkupChange(string $categoryId): void
    {
        $expenses = Expense::query()
            ->where('expense_category_id', $categoryId)
            ->whereNull('markup_percentage') // Only those using category default
            ->where('billable', true)
            ->whereIn('status', [ApprovalStatus::DRAFT, ApprovalStatus::SUBMITTED])
            ->get();

        foreach ($expenses as $expense) {
            $expense->selling_price = $expense->getSellingPriceComputed();
            $expense->save();
        }
    }

    /**
     * Notify organization members with expenses:approve permission.
     */
    private function notifyApprovers(Expense $expense): void
    {
        $approvers = Member::query()
            ->where('organization_id', $expense->organization_id)
            ->whereIn('role', [Role::Owner->value, Role::Admin->value, Role::Manager->value])
            ->where('id', '!=', $expense->member_id) // Don't notify submitter
            ->with('user')
            ->get();

        foreach ($approvers as $approver) {
            $approver->user->notify(new ExpenseSubmittedNotification($expense));
        }
    }
}
```

### 3.2 ExpenseFilter

File: `app/Service/ExpenseFilter.php`

Follows the exact `TimeEntryFilter` pattern -- a fluent builder that applies WHERE clauses.

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Enums\ApprovalStatus;
use App\Models\Expense;
use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ExpenseFilter
{
    /**
     * @var Builder<Expense>
     */
    private Builder $builder;

    /**
     * @param  Builder<Expense>  $builder
     */
    public function __construct(Builder $builder)
    {
        $this->builder = $builder;
    }

    public function addStartDateFilter(?string $date): self
    {
        if ($date === null) {
            return $this;
        }
        $this->builder->where('date', '>=', Carbon::createFromFormat('Y-m-d', $date));

        return $this;
    }

    public function addEndDateFilter(?string $date): self
    {
        if ($date === null) {
            return $this;
        }
        $this->builder->where('date', '<=', Carbon::createFromFormat('Y-m-d', $date));

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

    /**
     * @param  array<string>|null  $projectIds
     */
    public function addProjectIdsFilter(?array $projectIds): self
    {
        if ($projectIds === null) {
            return $this;
        }
        $this->builder->whereIn('project_id', $projectIds);

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
        $approvalStatus = ApprovalStatus::tryFrom($status);
        if ($approvalStatus === null) {
            Log::warning('Invalid expense status filter value', ['value' => $status]);

            return $this;
        }
        $this->builder->where('status', $approvalStatus->value);

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

    /**
     * @return Builder<Expense>
     */
    public function get(): Builder
    {
        return $this->builder;
    }
}
```

### 3.3 ExpenseCategoryService

File: `app/Service/ExpenseCategoryService.php`

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Exceptions\Api\EntityStillInUseApiException;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Organization;
use Illuminate\Validation\ValidationException;

class ExpenseCategoryService
{
    /**
     * Create a category. Enforces single-level nesting.
     */
    public function createCategory(
        Organization $organization,
        string $name,
        ?string $description = null,
        ?string $color = null,
        ?int $defaultMarkup = null,
        ?string $parentId = null,
    ): ExpenseCategory {
        if ($parentId !== null) {
            $this->validateParentIsRoot($parentId, $organization);
        }

        $category = new ExpenseCategory();
        $category->name = $name;
        $category->description = $description;
        $category->color = $color;
        $category->default_markup = $defaultMarkup;
        $category->parent_id = $parentId;
        $category->organization()->associate($organization);
        $category->save();

        return $category;
    }

    /**
     * Update a category. Propagate markup changes to expenses.
     */
    public function updateCategory(
        ExpenseCategory $category,
        array $attributes,
        ExpenseService $expenseService
    ): ExpenseCategory {
        $oldMarkup = $category->default_markup;

        foreach ($attributes as $key => $value) {
            $category->{$key} = $value;
        }
        $category->save();

        // Propagate markup change to draft/submitted expenses
        if (array_key_exists('default_markup', $attributes) && $oldMarkup !== $category->default_markup) {
            $expenseService->propagateCategoryMarkupChange($category->getKey());
        }

        return $category;
    }

    /**
     * Delete a category. Fails if expenses reference it.
     */
    public function deleteCategory(ExpenseCategory $category): void
    {
        if (Expense::query()->where('expense_category_id', $category->getKey())->exists()) {
            throw new EntityStillInUseApiException('expense_category', 'expense');
        }

        $category->delete();
    }

    /**
     * Validate that parent_id references a root category (no grandchildren).
     */
    private function validateParentIsRoot(string $parentId, Organization $organization): void
    {
        $parent = ExpenseCategory::query()
            ->where('id', $parentId)
            ->where('organization_id', $organization->getKey())
            ->first();

        if ($parent === null) {
            throw ValidationException::withMessages([
                'parent_id' => ['The selected parent category does not exist.'],
            ]);
        }

        if ($parent->parent_id !== null) {
            throw ValidationException::withMessages([
                'parent_id' => ['Subcategories cannot be nested more than one level deep.'],
            ]);
        }
    }
}
```

### 3.4 State Machine Summary

```
             submit()                 approve()
  DRAFT -----------------> SUBMITTED -----------------> APPROVED
    ^                          |                            |
    |                          | reject()                   | revert() (admin only)
    |                          v                            |
    |                      REJECTED                         |
    |                          |                            |
    |           submit()       |                            |
    +<-------------------------+                            |
    +<------------------------------------------------------+
```

**Transaction boundaries**: All status transitions are atomic (single save). The `createExpense`, `updateExpense`, `deleteExpense`, and `uploadReceipt` methods use `DB::transaction()` for multi-step operations.

---

## 4. Frontend Architecture

### 4.1 TypeScript Type Definitions

File: `resources/js/types/expense.d.ts`

```typescript
export type ExpenseStatus = 'draft' | 'submitted' | 'approved' | 'rejected';

export interface Expense {
    id: string;
    amount: number;
    currency: string;
    date: string; // Y-m-d
    description: string;
    billable: boolean;
    markup_percentage: number | null;
    selling_price: number | null;
    status: ExpenseStatus;
    submitted_at: string | null;
    has_receipt: boolean;
    receipt_filename: string | null;
    reviewer_comment: string | null;
    reviewer_id: string | null;
    reviewed_at: string | null;
    user_id: string;
    member_id: string;
    organization_id: string;
    project_id: string | null;
    task_id: string | null;
    client_id: string | null;
    expense_category_id: string | null;
}

export interface ExpenseCategory {
    id: string;
    name: string;
    description: string | null;
    color: string | null;
    default_markup: number | null;
    parent_id: string | null;
    is_archived: boolean;
    children?: ExpenseCategory[];
}

export interface CreateExpenseBody {
    member_id: string;
    amount: number;
    currency?: string;
    date: string;
    description?: string;
    billable: boolean;
    markup_percentage?: number;
    project_id?: string | null;
    task_id?: string | null;
    expense_category_id?: string | null;
}

export interface UpdateExpenseBody {
    amount?: number;
    currency?: string;
    date?: string;
    description?: string;
    billable?: boolean;
    markup_percentage?: number | null;
    project_id?: string | null;
    task_id?: string | null;
    expense_category_id?: string | null;
}

export interface ExpenseFilters {
    member_id?: string;
    project_ids?: string[];
    expense_category_id?: string;
    status?: ExpenseStatus;
    billable?: string; // 'true' | 'false'
    start?: string; // Y-m-d
    end?: string; // Y-m-d
    limit?: number;
    offset?: number;
}

export interface CreateExpenseCategoryBody {
    name: string;
    description?: string;
    color?: string;
    default_markup?: number;
    parent_id?: string;
}

export interface BulkApproveResponse {
    success: string[];
    error: string[];
}
```

### 4.2 Pinia Store: useExpensesStore

File: `resources/js/utils/useExpenses.ts`

```typescript
import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { api } from '../packages/api/src';
import { getCurrentOrganizationId } from '../utils/useUser';
import { handleApiRequestNotifications } from '../packages/ui/src/utils/notification';
import type {
    Expense,
    CreateExpenseBody,
    UpdateExpenseBody,
    ExpenseFilters,
    BulkApproveResponse,
} from '../types/expense';

export const useExpensesStore = defineStore('expenses', () => {
    // State
    const expenses = ref<Expense[]>([]);
    const totalCount = ref(0);
    const isLoading = ref(false);
    const currentFilters = ref<ExpenseFilters>({});

    // Getters
    const draftExpenses = computed(() =>
        expenses.value.filter((e) => e.status === 'draft')
    );
    const submittedExpenses = computed(() =>
        expenses.value.filter((e) => e.status === 'submitted')
    );
    const approvedExpenses = computed(() =>
        expenses.value.filter((e) => e.status === 'approved')
    );

    // Actions
    async function fetchExpenses(filters?: ExpenseFilters): Promise<void> {
        isLoading.value = true;
        const organizationId = getCurrentOrganizationId();
        try {
            const response = await api.getExpenses({
                params: { path: { organization: organizationId } },
                queries: filters ?? currentFilters.value,
            });
            expenses.value = response.data;
            totalCount.value = response.meta.total;
            if (filters) {
                currentFilters.value = filters;
            }
        } finally {
            isLoading.value = false;
        }
    }

    async function createExpense(body: CreateExpenseBody): Promise<Expense> {
        const organizationId = getCurrentOrganizationId();
        const response = await handleApiRequestNotifications(
            () =>
                api.createExpense({
                    params: { path: { organization: organizationId } },
                    body,
                }),
            'Expense created',
            'Failed to create expense'
        );
        await fetchExpenses();
        return response.data;
    }

    async function updateExpense(
        expenseId: string,
        body: UpdateExpenseBody
    ): Promise<Expense> {
        const organizationId = getCurrentOrganizationId();
        const response = await handleApiRequestNotifications(
            () =>
                api.updateExpense({
                    params: {
                        path: { organization: organizationId, expense: expenseId },
                    },
                    body,
                }),
            'Expense updated',
            'Failed to update expense'
        );
        await fetchExpenses();
        return response.data;
    }

    async function deleteExpense(expenseId: string): Promise<void> {
        const organizationId = getCurrentOrganizationId();
        await handleApiRequestNotifications(
            () =>
                api.deleteExpense({
                    params: {
                        path: { organization: organizationId, expense: expenseId },
                    },
                }),
            'Expense deleted',
            'Failed to delete expense'
        );
        await fetchExpenses();
    }

    async function submitExpense(expenseId: string): Promise<Expense> {
        const organizationId = getCurrentOrganizationId();
        const response = await handleApiRequestNotifications(
            () =>
                api.submitExpense({
                    params: {
                        path: { organization: organizationId, expense: expenseId },
                    },
                }),
            'Expense submitted for approval',
            'Failed to submit expense'
        );
        await fetchExpenses();
        return response.data;
    }

    async function approveExpense(
        expenseId: string,
        comment?: string
    ): Promise<Expense> {
        const organizationId = getCurrentOrganizationId();
        const response = await handleApiRequestNotifications(
            () =>
                api.approveExpense({
                    params: {
                        path: { organization: organizationId, expense: expenseId },
                    },
                    body: { comment },
                }),
            'Expense approved',
            'Failed to approve expense'
        );
        await fetchExpenses();
        return response.data;
    }

    async function rejectExpense(
        expenseId: string,
        comment: string
    ): Promise<Expense> {
        const organizationId = getCurrentOrganizationId();
        const response = await handleApiRequestNotifications(
            () =>
                api.rejectExpense({
                    params: {
                        path: { organization: organizationId, expense: expenseId },
                    },
                    body: { comment },
                }),
            'Expense rejected',
            'Failed to reject expense'
        );
        await fetchExpenses();
        return response.data;
    }

    async function bulkApproveExpenses(
        ids: string[],
        comment?: string
    ): Promise<BulkApproveResponse> {
        const organizationId = getCurrentOrganizationId();
        const response = await handleApiRequestNotifications(
            () =>
                api.bulkApproveExpenses({
                    params: { path: { organization: organizationId } },
                    body: { ids, comment },
                }),
            `${ids.length} expenses processed`,
            'Failed to bulk approve expenses'
        );
        await fetchExpenses();
        return response;
    }

    async function uploadReceipt(
        expenseId: string,
        file: File
    ): Promise<Expense> {
        const organizationId = getCurrentOrganizationId();
        const formData = new FormData();
        formData.append('receipt', file);
        const response = await handleApiRequestNotifications(
            () =>
                api.uploadExpenseReceipt({
                    params: {
                        path: { organization: organizationId, expense: expenseId },
                    },
                    body: formData,
                }),
            'Receipt uploaded',
            'Failed to upload receipt'
        );
        await fetchExpenses();
        return response.data;
    }

    async function downloadReceipt(expenseId: string): Promise<string> {
        const organizationId = getCurrentOrganizationId();
        const response = await api.downloadExpenseReceipt({
            params: {
                path: { organization: organizationId, expense: expenseId },
            },
        });
        return response.download_url;
    }

    async function deleteReceipt(expenseId: string): Promise<Expense> {
        const organizationId = getCurrentOrganizationId();
        const response = await handleApiRequestNotifications(
            () =>
                api.deleteExpenseReceipt({
                    params: {
                        path: { organization: organizationId, expense: expenseId },
                    },
                }),
            'Receipt deleted',
            'Failed to delete receipt'
        );
        await fetchExpenses();
        return response.data;
    }

    return {
        // State
        expenses,
        totalCount,
        isLoading,
        currentFilters,
        // Getters
        draftExpenses,
        submittedExpenses,
        approvedExpenses,
        // Actions
        fetchExpenses,
        createExpense,
        updateExpense,
        deleteExpense,
        submitExpense,
        approveExpense,
        rejectExpense,
        bulkApproveExpenses,
        uploadReceipt,
        downloadReceipt,
        deleteReceipt,
    };
});
```

### 4.3 Pinia Store: useExpenseCategoriesStore

File: `resources/js/utils/useExpenseCategories.ts`

```typescript
import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { api } from '../packages/api/src';
import { getCurrentOrganizationId } from '../utils/useUser';
import { handleApiRequestNotifications } from '../packages/ui/src/utils/notification';
import type { ExpenseCategory, CreateExpenseCategoryBody } from '../types/expense';

export const useExpenseCategoriesStore = defineStore('expenseCategories', () => {
    const categories = ref<ExpenseCategory[]>([]);
    const isLoading = ref(false);

    const activeCategories = computed(() =>
        categories.value.filter((c) => !c.is_archived)
    );

    const flatCategories = computed(() => {
        const flat: ExpenseCategory[] = [];
        for (const cat of categories.value) {
            flat.push(cat);
            if (cat.children) {
                for (const child of cat.children) {
                    flat.push(child);
                }
            }
        }
        return flat;
    });

    async function fetchCategories(): Promise<void> {
        isLoading.value = true;
        const organizationId = getCurrentOrganizationId();
        try {
            const response = await api.getExpenseCategories({
                params: { path: { organization: organizationId } },
            });
            categories.value = response.data;
        } finally {
            isLoading.value = false;
        }
    }

    async function createCategory(body: CreateExpenseCategoryBody): Promise<ExpenseCategory> {
        const organizationId = getCurrentOrganizationId();
        const response = await handleApiRequestNotifications(
            () => api.createExpenseCategory({
                params: { path: { organization: organizationId } },
                body,
            }),
            'Category created',
            'Failed to create category'
        );
        await fetchCategories();
        return response.data;
    }

    async function updateCategory(
        categoryId: string,
        body: Partial<CreateExpenseCategoryBody>
    ): Promise<ExpenseCategory> {
        const organizationId = getCurrentOrganizationId();
        const response = await handleApiRequestNotifications(
            () => api.updateExpenseCategory({
                params: {
                    path: { organization: organizationId, expenseCategory: categoryId },
                },
                body,
            }),
            'Category updated',
            'Failed to update category'
        );
        await fetchCategories();
        return response.data;
    }

    async function deleteCategory(categoryId: string): Promise<void> {
        const organizationId = getCurrentOrganizationId();
        await handleApiRequestNotifications(
            () => api.deleteExpenseCategory({
                params: {
                    path: { organization: organizationId, expenseCategory: categoryId },
                },
            }),
            'Category deleted',
            'Failed to delete category'
        );
        await fetchCategories();
    }

    return {
        categories,
        isLoading,
        activeCategories,
        flatCategories,
        fetchCategories,
        createCategory,
        updateCategory,
        deleteCategory,
    };
});
```

### 4.4 Vue Page and Component Tree

```
resources/js/Pages/Expenses.vue (Inertia page)
  +-- AppLayout (wrapper)
      +-- MainContainer
          +-- ExpenseFilterBar.vue (filters: date range, project, category, status, billable, member)
          +-- ExpenseForm.vue (create/edit form, inline or modal)
          +-- ExpenseTable.vue (paginated table)
          |   +-- ExpenseRow.vue (per-row, with status badge and actions)
          |       +-- ExpenseStatusBadge.vue (color-coded status)
          +-- ExpenseBulkActionBar.vue (shows when rows selected)
          +-- ExpenseApprovalActions.vue (approve/reject buttons)
          +-- ExpenseRejectDialog.vue (modal for rejection comment)
          +-- ExpenseCategoryManager.vue (admin tab/section)
              +-- ExpenseCategoryRow.vue
              +-- ExpenseCategoryForm.vue
```

### 4.5 Navigation Addition

Modify `resources/js/Layouts/AppLayout.vue` to add:

```vue
<NavigationSidebarItem
    v-if="canViewExpenses()"
    :label="$t('Expenses')"
    :href="route('expenses')"
    :active="route().current('expenses')"
>
    <template #icon>
        <BanknotesIcon class="w-5 h-5" />
    </template>
</NavigationSidebarItem>
```

Position: After Tags, before Invoices in the "Manage" section.

Permission check in `resources/js/utils/permissions.ts`:
```typescript
export function canViewExpenses(): boolean {
    return hasPermission('expenses:view:own') || hasPermission('expenses:view:all');
}
```

---

## 5. Notification Design

### 5.1 Notification Classes

All notifications extend `App\Notifications\BaseNotification` (from FOUND-002) and use `database` + `mail` channels.

#### ExpenseSubmittedNotification

File: `app/Notifications/ExpenseSubmittedNotification.php`

```php
<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Expense;
use Illuminate\Notifications\Messages\MailMessage;

class ExpenseSubmittedNotification extends BaseNotification
{
    public function __construct(
        private readonly Expense $expense,
    ) {
        parent::__construct();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'expense_submitted',
            'expense_id' => $this->expense->id,
            'organization_id' => $this->expense->organization_id,
            'submitter_name' => $this->expense->user->name,
            'amount' => $this->expense->amount,
            'currency' => $this->expense->currency,
            'date' => $this->expense->date->format('Y-m-d'),
            'message' => $this->expense->user->name.' submitted an expense for '
                .($this->expense->amount / 100).' '.$this->expense->currency,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Expense Submitted for Approval')
            ->line($this->expense->user->name.' submitted an expense for review.')
            ->line('Amount: '.number_format($this->expense->amount / 100, 2).' '.$this->expense->currency)
            ->line('Date: '.$this->expense->date->format('Y-m-d'))
            ->line('Description: '.($this->expense->description ?: 'N/A'))
            ->action('Review Expense', url('/expenses'));
    }
}
```

#### ExpenseApprovedNotification

File: `app/Notifications/ExpenseApprovedNotification.php`

**Trigger**: Called from `ExpenseService::approveExpense()`
**Recipient**: The expense submitter (`$expense->member->user`)
**Data**: expense_id, reviewer name, amount, optional comment

#### ExpenseRejectedNotification

File: `app/Notifications/ExpenseRejectedNotification.php`

**Trigger**: Called from `ExpenseService::rejectExpense()`
**Recipient**: The expense submitter (`$expense->member->user`)
**Data**: expense_id, reviewer name, amount, rejection comment (required)

### 5.2 Trigger Points

| Event | Notification Class | Recipient(s) |
|-------|-------------------|--------------|
| Expense submitted | `ExpenseSubmittedNotification` | All org members with `expenses:approve` permission (Owner/Admin/Manager), excluding submitter |
| Expense approved | `ExpenseApprovedNotification` | Expense submitter (member.user) |
| Expense rejected | `ExpenseRejectedNotification` | Expense submitter (member.user) |

### 5.3 Dependencies

Requires FOUND-001 through FOUND-005 (shared notification infrastructure) to be completed first. Specifically:
- `notifications` database table (FOUND-001)
- `BaseNotification` class with channel resolution (FOUND-002)
- Notification API endpoints for frontend bell component (FOUND-004)

---

## 6. File Storage

### 6.1 Receipt Upload Flow

```
1. Client creates expense via POST /expenses
   -> Response: { data: { id: "expense-uuid", ... } }

2. Client uploads receipt via POST /expenses/{expense}/receipt
   -> multipart/form-data with 'receipt' field
   -> Server validates MIME type + size
   -> Server stores file to private disk
   -> Path: receipts/{organization_id}/{expense_id}.{ext}
   -> Server updates expense.receipt_path and expense.receipt_filename
   -> Response: { data: { ..., has_receipt: true, receipt_filename: "invoice.pdf" } }
```

### 6.2 Storage Disk Configuration

Uses the existing `config('filesystems.private')` disk. No new configuration needed. This disk is already configured for S3 in production and local in development.

```php
// config/filesystems.php (existing)
'private' => env('FILESYSTEM_PRIVATE_DISK', 'local'),
```

### 6.3 Signed URL Generation

```php
// Download receipt
$url = Storage::disk(config('filesystems.private'))
    ->temporaryUrl($expense->receipt_path, now()->addMinutes(5));
```

Returns a signed URL with 5-minute expiry. Same pattern as existing export downloads.

### 6.4 Cleanup Strategy

1. **On expense delete**: `ExpenseService::deleteExpense()` deletes the receipt file before deleting the expense record, wrapped in a DB transaction.
2. **On receipt replace**: `ExpenseService::uploadReceipt()` deletes the old file before storing the new one.
3. **Orphan cleanup (future)**: A scheduled command could scan `receipts/` for files without matching expense records. Not implemented in v1.

### 6.5 Security

- Files are on a private disk (not publicly accessible)
- Access only via signed temporary URLs (5-minute expiry)
- MIME type validated server-side: `mimes:jpeg,png,pdf,heic,webp`
- Max size: 10MB (`max:10240`)
- Path uses UUIDs, not guessable

---

## 7. Export Pipeline

### 7.1 Export Formats

Reuses the existing `ExportFormat` enum (`csv`, `xlsx`, `pdf`, `ods`). Feature 02 supports `csv`, `xlsx`, and `pdf`.

### 7.2 CSV Export

File: `app/Service/ExpenseExport/ExpensesDetailedCsvExport.php`

```php
<?php

declare(strict_types=1);

namespace App\Service\ExpenseExport;

use App\Models\Expense;
use App\Service\ReportExport\CsvExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends CsvExport<Expense>
 */
class ExpensesDetailedCsvExport extends CsvExport
{
    public const array HEADER = [
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
        'Status',
        'Billable',
        'Has Receipt',
    ];

    /**
     * @param  Expense  $model
     */
    public function mapRow(Model $model): array
    {
        return [
            'Date' => $model->date->format('Y-m-d'),
            'Member' => $model->user->name,
            'Project' => $model->project?->name,
            'Client' => $model->client?->name,
            'Category' => $model->category?->name,
            'Description' => $model->description,
            'Amount' => (string) ($model->amount / 100),
            'Currency' => $model->currency,
            'Markup %' => $model->markup_percentage !== null ? (string) $model->markup_percentage : '',
            'Selling Price' => $model->selling_price !== null ? (string) ($model->selling_price / 100) : '',
            'Status' => $model->status->value,
            'Billable' => $model->billable ? 'Yes' : 'No',
            'Has Receipt' => $model->receipt_path !== null ? 'Yes' : 'No',
        ];
    }
}
```

### 7.3 XLSX Export

File: `app/Service/ExpenseExport/ExpensesDetailedExport.php`

Implements `FromQuery`, `ShouldAutoSize`, `WithColumnFormatting`, `WithHeadings`, `WithMapping`, `WithStyles` (same interface set as `TimeEntriesDetailedExport`).

### 7.4 PDF Export

Uses Gotenberg (existing integration). A Blade template at `resources/views/reports/expense-index/pdf.blade.php` renders HTML, which is sent to Gotenberg for PDF conversion. PDF export is a premium feature (gated by `BillingContract::hasSubscription`).

### 7.5 Export Controller Method

```php
public function export(
    Organization $organization,
    ExpenseExportRequest $request,
): JsonResponse {
    $this->checkPermission($organization, 'expenses:view:all');
    $this->checkPermission($organization, 'export');

    $format = $request->getFormatValue();
    if ($format === ExportFormat::PDF && ! $this->canAccessPremiumFeatures($organization)) {
        throw new FeatureIsNotAvailableInFreePlanApiException();
    }

    // Build filtered query (same as index)
    $query = $this->buildFilteredQuery($organization, $request);
    $query->with(['user', 'project', 'client', 'category']);

    $filename = 'expenses-export-'.now()->format('Y-m-d_H-i-s').'.'.$format->getFileExtension();
    $folderPath = 'exports';
    $path = $folderPath.'/'.$filename;

    if ($format === ExportFormat::CSV) {
        $export = new ExpensesDetailedCsvExport(
            config('filesystems.private'), $folderPath, $filename, $query, 1000
        );
        $export->export();
    } elseif ($format === ExportFormat::PDF) {
        // Gotenberg rendering (follows TimeEntryController::indexExport pattern)
        // ...
    } else {
        Excel::store(
            new ExpensesDetailedExport($query, $format),
            $path,
            config('filesystems.private'),
            $format->getExportPackageType(),
            ['visibility' => 'private']
        );
    }

    return response()->json([
        'download_url' => Storage::disk(config('filesystems.private'))
            ->temporaryUrl($path, now()->addMinutes(5)),
    ]);
}
```

---

## 8. Permission Matrix

### 8.1 Full Role-Permission Table

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

### 8.2 Modular Registration (SF-08)

File: `app/Permissions/ExpensePermissions.php`

```php
<?php

declare(strict_types=1);

namespace App\Permissions;

use App\Enums\Role;
use Laravel\Jetstream\Jetstream;

class ExpensePermissions
{
    /**
     * All expense-related permissions.
     *
     * @var array<string>
     */
    private const array ALL_EXPENSE_PERMISSIONS = [
        'expenses:view:all',
        'expenses:view:own',
        'expenses:create:all',
        'expenses:create:own',
        'expenses:update:all',
        'expenses:update:own',
        'expenses:delete:all',
        'expenses:delete:own',
        'expenses:approve',
    ];

    private const array ALL_CATEGORY_PERMISSIONS = [
        'expense-categories:view',
        'expense-categories:create',
        'expense-categories:update',
        'expense-categories:delete',
    ];

    private const array EMPLOYEE_EXPENSE_PERMISSIONS = [
        'expenses:view:own',
        'expenses:create:own',
        'expenses:update:own',
        'expenses:delete:own',
    ];

    private const array MANAGER_CATEGORY_PERMISSIONS = [
        'expense-categories:view',
    ];

    private const array EMPLOYEE_CATEGORY_PERMISSIONS = [
        'expense-categories:view',
    ];

    /**
     * Returns the permissions to add for each role.
     *
     * @return array<string, array<string>>
     */
    public static function forRole(Role $role): array
    {
        return match ($role) {
            Role::Owner, Role::Admin => [
                ...self::ALL_EXPENSE_PERMISSIONS,
                ...self::ALL_CATEGORY_PERMISSIONS,
            ],
            Role::Manager => [
                ...self::ALL_EXPENSE_PERMISSIONS,
                ...self::MANAGER_CATEGORY_PERMISSIONS,
            ],
            Role::Employee => [
                ...self::EMPLOYEE_EXPENSE_PERMISSIONS,
                ...self::EMPLOYEE_CATEGORY_PERMISSIONS,
            ],
            Role::Placeholder => [],
        };
    }
}
```

This is consumed by `JetstreamServiceProvider::configurePermissions()`. A one-line call `ExpensePermissions::forRole($role)` merges the expense permissions into each role's permission array. The exact integration follows the pattern defined in FOUND-007.

---

## 9. Migration Strategy

### 9.1 Ordered Migration List

All migrations use the `2026_03_02_` date prefix (SF-03). The sequential numbering ensures correct execution order.

| Order | Filename | Description | Dependencies |
|-------|----------|-------------|-------------|
| 1 | `2026_03_02_000001_create_expense_categories_table.php` | Create `expense_categories` table with self-referential FK | `organizations` table |
| 2 | `2026_03_02_000002_create_expenses_table.php` | Create `expenses` table with all FKs | `expense_categories`, `organizations`, `users`, `members`, `projects`, `tasks`, `clients` tables |

### 9.2 Prerequisites

The shared foundation migrations (prefix `2026_02_28_`) must run before Feature 02 migrations:
- `2026_02_28_000001_add_weekly_capacity_to_members.php` (FOUND-006, not directly needed by expenses but runs first by convention)
- The `notifications` table migration (FOUND-001) must exist for notifications to work

### 9.3 Rollback Safety

Both migrations have `down()` methods that drop their respective tables. The `expenses` table must be dropped before `expense_categories` due to the FK dependency. Laravel handles this automatically when rolling back in reverse order.

---

## 10. Integration Points

### 10.1 Shared Foundation Dependencies (FOUND tasks)

| FOUND Task | What It Provides | How Feature 02 Uses It |
|-----------|-----------------|----------------------|
| FOUND-001 | `notifications` table | Required for `database` notification channel |
| FOUND-002 | `BaseNotification` class | All 3 expense notifications extend this |
| FOUND-003 | Notification bell UI | Displays expense approval notifications |
| FOUND-004 | Notification API endpoints | Frontend fetches notification list |
| FOUND-005 | Notification preferences | Users can toggle expense notification emails |
| FOUND-007 | Modular permissions infrastructure | `ExpensePermissions` class follows this pattern |

### 10.2 Hooks into Existing Code

| Existing File | Modification | Reason |
|--------------|-------------|--------|
| `routes/api.php` | Add expense + category route groups | Register all API endpoints |
| `routes/web.php` | Add `/expenses` Inertia route | Render the Expenses page |
| `app/Providers/JetstreamServiceProvider.php` | Add `ExpensePermissions::forRole()` call | Register permissions for each role |
| `resources/js/Layouts/AppLayout.vue` | Add `NavigationSidebarItem` for Expenses | Sidebar navigation |
| `resources/js/utils/permissions.ts` | Add `canViewExpenses()` function | Permission check for UI |
| `openapi.json` | Add all expense endpoint schemas | API documentation + TS client generation |

### 10.3 Existing Services Reused

| Service/Class | Usage in Expenses |
|--------------|------------------|
| `BaseFormRequest::moneyRules()` | Validates `amount` field (integer cents with max) |
| `ExportFormat` enum | Determines export output format |
| `CsvExport` abstract class | Base for `ExpensesDetailedCsvExport` |
| `EntityStillInUseApiException` | Thrown when deleting a category with associated expenses |
| `BillingContract` | Gates PDF export as premium feature |
| `PermissionStore` | Permission checks in controllers and request validation |
| `ExistsEloquent` | FK validation in request classes |
| `CustomAuditable` trait | Audit trail on both models |
| `ComputedAttributes` trait | Selling price and client_id computation |

---

## 11. File Manifest

### 11.1 Files to Create

**Enums** (shared, Phase 0):
- `app/Enums/ApprovalStatus.php`

**Traits** (shared, Phase 0):
- `app/Traits/HasApprovalWorkflow.php`

**Migrations**:
- `database/migrations/2026_03_02_000001_create_expense_categories_table.php`
- `database/migrations/2026_03_02_000002_create_expenses_table.php`

**Models**:
- `app/Models/Expense.php`
- `app/Models/ExpenseCategory.php`

**Factories**:
- `database/factories/ExpenseFactory.php`
- `database/factories/ExpenseCategoryFactory.php`

**Controllers**:
- `app/Http/Controllers/Api/V1/ExpenseController.php`
- `app/Http/Controllers/Api/V1/ExpenseCategoryController.php`

**Request Validation**:
- `app/Http/Requests/V1/Expense/ExpenseIndexRequest.php`
- `app/Http/Requests/V1/Expense/ExpenseStoreRequest.php`
- `app/Http/Requests/V1/Expense/ExpenseUpdateRequest.php`
- `app/Http/Requests/V1/Expense/ExpenseUploadReceiptRequest.php`
- `app/Http/Requests/V1/Expense/ExpenseExportRequest.php`
- `app/Http/Requests/V1/ExpenseCategory/ExpenseCategoryStoreRequest.php`
- `app/Http/Requests/V1/ExpenseCategory/ExpenseCategoryUpdateRequest.php`

**API Resources**:
- `app/Http/Resources/V1/Expense/ExpenseResource.php`
- `app/Http/Resources/V1/Expense/ExpenseCollection.php`
- `app/Http/Resources/V1/ExpenseCategory/ExpenseCategoryResource.php`
- `app/Http/Resources/V1/ExpenseCategory/ExpenseCategoryCollection.php`

**Services**:
- `app/Service/ExpenseService.php`
- `app/Service/ExpenseCategoryService.php`
- `app/Service/ExpenseFilter.php`
- `app/Service/ExpenseExport/ExpensesDetailedCsvExport.php`
- `app/Service/ExpenseExport/ExpensesDetailedExport.php`
- `app/Service/ExpenseExport/ExpensesReportExport.php`

**Permissions**:
- `app/Permissions/ExpensePermissions.php`

**Notifications**:
- `app/Notifications/ExpenseSubmittedNotification.php`
- `app/Notifications/ExpenseApprovedNotification.php`
- `app/Notifications/ExpenseRejectedNotification.php`

**Exceptions**:
- `app/Exceptions/Api/ExpenseNotEditableApiException.php`
- `app/Exceptions/Api/InvalidExpenseStatusTransitionApiException.php`
- `app/Exceptions/Api/SelfApprovalNotAllowedApiException.php`

**Frontend - Pages**:
- `resources/js/Pages/Expenses.vue`

**Frontend - Stores**:
- `resources/js/utils/useExpenses.ts`
- `resources/js/utils/useExpenseCategories.ts`

**Frontend - Types**:
- `resources/js/types/expense.d.ts`

**Frontend - Components**:
- `resources/js/packages/ui/src/Expense/ExpenseForm.vue`
- `resources/js/packages/ui/src/Expense/ExpenseTable.vue`
- `resources/js/packages/ui/src/Expense/ExpenseRow.vue`
- `resources/js/packages/ui/src/Expense/ExpenseFilterBar.vue`
- `resources/js/packages/ui/src/Expense/ExpenseStatusBadge.vue`
- `resources/js/packages/ui/src/Expense/ExpenseApprovalActions.vue`
- `resources/js/packages/ui/src/Expense/ExpenseRejectDialog.vue`
- `resources/js/packages/ui/src/Expense/ExpenseBulkActionBar.vue`
- `resources/js/packages/ui/src/Expense/ExpenseCategoryManager.vue`
- `resources/js/packages/ui/src/Expense/ExpenseCategoryRow.vue`
- `resources/js/packages/ui/src/Expense/ExpenseCategoryForm.vue`

**Frontend - Component Tests**:
- `resources/js/packages/ui/src/Expense/__tests__/ExpenseForm.test.ts`
- `resources/js/packages/ui/src/Expense/__tests__/ExpenseTable.test.ts`
- `resources/js/packages/ui/src/Expense/__tests__/ExpenseStatusBadge.test.ts`
- `resources/js/packages/ui/src/Expense/__tests__/ExpenseApprovalActions.test.ts`

**Backend Tests**:
- `tests/Unit/Endpoint/Api/V1/ExpenseCategoryEndpointTest.php`
- `tests/Unit/Endpoint/Api/V1/ExpenseEndpointTest.php`
- `tests/Unit/Endpoint/Api/V1/ExpenseApprovalEndpointTest.php`
- `tests/Unit/Service/ExpenseServiceTest.php`
- `tests/Unit/Service/ExpenseCategoryServiceTest.php`

**E2E Tests**:
- `e2e/expenses.spec.ts`

**Blade Templates**:
- `resources/views/reports/expense-index/pdf.blade.php`
- `resources/views/reports/expense-index/pdf-footer.blade.php`

### 11.2 Files to Modify

| File | Change |
|------|--------|
| `routes/api.php` | Add expense and category route groups |
| `routes/web.php` | Add `/expenses` Inertia route |
| `app/Providers/JetstreamServiceProvider.php` | Add `ExpensePermissions` call in `configurePermissions()` |
| `resources/js/Layouts/AppLayout.vue` | Add Expenses navigation sidebar item |
| `resources/js/utils/permissions.ts` | Add `canViewExpenses()` helper |
| `openapi.json` | Add all expense endpoint schemas |

**Total**: 57 files to create, 6 files to modify.

---

## 12. Implementation Sequence

### Phase 0: Shared Foundations (Prerequisites)

**Effort**: 30 hours (shared across all features)

| Task ID | Description | Effort |
|---------|-------------|--------|
| FOUND-001 | Notification infrastructure migration | 2h |
| FOUND-002 | Base notification classes | 4h |
| FOUND-003 | Notification bell UI component | 8h |
| FOUND-004 | Notification API endpoints | 6h |
| FOUND-005 | Notification preferences | 4h |
| FOUND-007 | Modular permissions infrastructure | 4h |
| -- | Create `ApprovalStatus` enum | 1h |
| -- | Create `HasApprovalWorkflow` trait | 1h |

### Sprint 1: Database and Backend CRUD (Weeks 1-2)

**Effort**: ~58 hours

| Task ID | Description | Effort | Dependencies |
|---------|-------------|--------|-------------|
| EXP-001 | Create expense_categories migration | 4h | Phase 0 |
| EXP-002 | Create expenses migration | 4h | EXP-001 |
| EXP-003 | ExpenseCategory model, factory, service | 8h | EXP-001 |
| EXP-004 | Expense model and factory | 10h | EXP-002, EXP-003 |
| EXP-006 | ExpenseCategory CRUD controller + requests + resources | 10h | EXP-003 |
| EXP-007 | Expense CRUD controller + requests + resources + ExpenseService + ExpenseFilter | 16h | EXP-004, EXP-006 |
| EXP-008 | Register expense permissions (ExpensePermissions.php) | 4h | Phase 0 |
| EXP-009 | Register API routes | 4h | EXP-006, EXP-007 |

**Parallelization**: EXP-001 + EXP-008 can run in parallel. EXP-003 and EXP-004 can overlap once EXP-001 completes. A frontend developer can begin EXP-015 (TypeScript types) during this sprint.

### Sprint 2: Approval Workflow, Notifications, and Frontend Foundation (Weeks 3-4)

**Effort**: ~70 hours

| Task ID | Description | Effort | Dependencies |
|---------|-------------|--------|-------------|
| EXP-005 | ExpenseFilter (moved per AMD-09) | 4h | EXP-004 |
| EXP-010 | Receipt upload/download/delete endpoints | 8h | EXP-007 |
| EXP-011 | Approval workflow endpoints (submit/approve/reject/bulk/revert) | 12h | EXP-007 |
| EXP-012 | Web routes + Expenses page shell | 4h | EXP-009 |
| EXP-013 | Sidebar navigation addition | 2h | EXP-012 |
| EXP-014 | Pinia stores (useExpenses + useExpenseCategories) | 10h | EXP-009 |
| EXP-015 | TypeScript type definitions | 4h | EXP-004 |
| EXP-016 | Expense form component | 12h | EXP-014, EXP-015 |
| EXP-017 | Expense table + row + filter bar + status badge components | 12h | EXP-014, EXP-015 |
| EXP-029 | Expense notification classes | 4h | EXP-007, FOUND-001 to FOUND-005 |
| EXP-030 | Dispatch notifications from ExpenseService | 2h | EXP-029 |

**Parallelization**: Backend (EXP-010, EXP-011, EXP-029, EXP-030) and frontend (EXP-012 through EXP-017) can run in parallel on separate tracks.

### Sprint 3: Components, Export, and Testing (Weeks 5-6)

**Effort**: ~84 hours

| Task ID | Description | Effort | Dependencies |
|---------|-------------|--------|-------------|
| EXP-018 | Expense approval actions component | 8h | EXP-017 |
| EXP-019 | Expense category management UI | 10h | EXP-014 |
| EXP-020 | Expense export endpoint (CSV/XLSX/PDF) | 12h | EXP-007 |
| EXP-025 | Integrate Expenses page with all components | 10h | EXP-016, EXP-017, EXP-018, EXP-019 |
| EXP-021 | Expense category endpoint tests | 8h | EXP-006, EXP-009 |
| EXP-022 | Expense CRUD endpoint tests | 16h | EXP-007, EXP-010 |
| EXP-023 | Expense approval endpoint tests | 10h | EXP-011 |
| EXP-024 | ExpenseService unit tests | 6h | EXP-007 |
| EXP-026 | Frontend component tests | 8h | EXP-016, EXP-017, EXP-018 |
| EXP-027 | E2E Playwright tests | 8h | EXP-025 |
| EXP-028 | OpenAPI specification update | 4h | EXP-009, EXP-011 |

**Parallelization**: EXP-020 (export) is independent of frontend. All test tasks (EXP-021 through EXP-024) can run in parallel.

### Summary

```
Total tasks: 30 (28 from PRD + EXP-029, EXP-030 from AMD-05)
Total effort: ~212 hours (~106 story points at 2h/SP)
Duration: 6 weeks (3 sprints of 2 weeks)
Team size: 2-3 developers (1 backend, 1 frontend, shared testing)
```

### Critical Path

```
EXP-001 -> EXP-002 -> EXP-004 -> EXP-007 -> EXP-009 -> EXP-014 -> EXP-017 -> EXP-025 -> EXP-027
```

**Minimum critical path duration**: 4 + 4 + 10 + 16 + 4 + 10 + 12 + 10 + 8 = 78 hours

---

## Appendix A: Custom Exception Classes

```php
// app/Exceptions/Api/ExpenseNotEditableApiException.php
<?php

declare(strict_types=1);

namespace App\Exceptions\Api;

class ExpenseNotEditableApiException extends ApiException
{
    public const string KEY = 'expense_not_editable';

    public function __construct()
    {
        parent::__construct(422);
        $this->message = 'Expense cannot be edited in its current status.';
    }
}
```

```php
// app/Exceptions/Api/InvalidExpenseStatusTransitionApiException.php
<?php

declare(strict_types=1);

namespace App\Exceptions\Api;

class InvalidExpenseStatusTransitionApiException extends ApiException
{
    public const string KEY = 'invalid_expense_status_transition';

    public function __construct(string $from, string $to)
    {
        parent::__construct(422);
        $this->message = "Cannot transition expense from '{$from}' to '{$to}'.";
    }
}
```

```php
// app/Exceptions/Api/SelfApprovalNotAllowedApiException.php
<?php

declare(strict_types=1);

namespace App\Exceptions\Api;

class SelfApprovalNotAllowedApiException extends ApiException
{
    public const string KEY = 'self_approval_not_allowed';

    public function __construct()
    {
        parent::__construct(422);
        $this->message = 'You cannot approve or reject your own expense.';
    }
}
```
