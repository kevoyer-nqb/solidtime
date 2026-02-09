Perfect! Now I have enough context to create a comprehensive architecture document. Let me create the complete ARCHITECTURE.md document for Feature 09: Advanced Reporting.

# ARCHITECTURE.md — Feature 09: Advanced Reporting for Solidtime

**Generated**: 2026-02-06  
**PRD Reference**: `/home/keven/Documents/solidtime-analysis/.features/09-advanced-reporting/PRD.md`  
**Task Assignments**: `/home/keven/Documents/solidtime-analysis/.features/09-advanced-reporting/task_assignments_20260206.md`  
**Shared Foundations**: `/home/keven/Documents/solidtime-analysis/.features/SHARED-FOUNDATIONS.md`

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Patterns and Conventions Found](#2-patterns-and-conventions-found)
3. [Architecture Decision](#3-architecture-decision)
4. [Data Model Design](#4-data-model-design)
5. [API Contract](#5-api-contract)
6. [Service Layer Architecture](#6-service-layer-architecture)
7. [Frontend Architecture](#7-frontend-architecture)
8. [Permission Matrix](#8-permission-matrix)
9. [Migration Strategy](#9-migration-strategy)
10. [Cross-Feature Integration](#10-cross-feature-integration)
11. [File Manifest](#11-file-manifest)
12. [Phase A/B Split](#12-phase-ab-split)
13. [Implementation Sequence](#13-implementation-sequence)
14. [Testing Strategy](#14-testing-strategy)
15. [Performance and Optimization](#15-performance-and-optimization)

---

## 1. Executive Summary

### Feature Overview

Advanced Reporting extends Solidtime's existing reporting infrastructure with:
- **Profitability analytics**: revenue vs. cost calculations with margin tracking
- **Utilization reporting**: actual hours vs. weekly capacity per member
- **Cost rate management**: hierarchical cost rates (Organization → Member → ProjectMember)
- **Scheduled reports**: automated email delivery on daily/weekly/monthly schedules
- **Report templates**: reusable report configurations
- **Expense tracking**: expense management with receipt uploads
- **Budget reports**: time and monetary budget vs. actual tracking
- **Enhanced export**: custom column selection, grouped exports, styled PDFs

### Key Architectural Decisions

1. **Cost Rate Cascade**: Mirror `BillableRateService` pattern exactly — cost rates cascade from ProjectMember → Member → Organization → null
2. **Synchronous Rate Storage**: `cost_rate` is computed synchronously on time entry creation/update (not a background job), stored on the `time_entries` table as a cached attribute
3. **Extend Existing Aggregation**: Leverage `TimeEntryAggregationService` for grouping logic, add profitability and utilization calculations on top
4. **Phase Split**: Phase A (Sprints 1-3) delivers core analytics; Phase B (Sprints 4-6) adds scheduling, templates, expenses, and budget reports
5. **Shared Foundation Dependency**: Depends on `FOUND-006` (`weekly_capacity` column) from shared foundations
6. **Modular Permissions**: Use SF-08 modular permission registration via `App\Permissions\ReportingPermissions`

---

## 2. Patterns and Conventions Found

### 2.1 Existing Reporting Infrastructure

**Location**: `/home/keven/Documents/solidtime-analysis/app/Service/TimeEntryAggregationService.php:1-300`

The existing `TimeEntryAggregationService` provides:
- Two-level grouping across 11 dimensions (Day, Week, Month, Year, User, Project, Task, Client, Billable, Description, Tag)
- Raw SQL aggregation using `extract(epoch from (end - start))` for duration
- Billable revenue calculation: `sum(duration * billable_rate / 3600)` stored as `cost` field (misnomer)
- Tag expansion via `LATERAL` join to handle entries with multiple tags
- Gap filling for time-based groupings

**Key Pattern**:
```php
public function getAggregatedTimeEntries(
    Builder $timeEntriesQuery,
    ?TimeEntryAggregationType $group1Type,
    ?TimeEntryAggregationType $group2Type,
    string $timezone,
    Weekday $startOfWeek,
    bool $fillGapsInTimeGroups,
    ?Carbon $start,
    ?Carbon $end,
    bool $showBillableRate,
    ?TimeEntryRoundingType $roundingType,
    ?int $roundingMinutes
): array
```

Returns:
```php
[
    'grouped_type' => 'project',
    'grouped_data' => [
        ['key' => 'uuid', 'seconds' => 36000, 'cost' => 50000, 'grouped_data' => null]
    ],
    'seconds' => 36000,
    'cost' => 50000
]
```

### 2.2 Billable Rate Hierarchy Service

**Location**: `/home/keven/Documents/solidtime-analysis/app/Service/BillableRateService.php:1-146`

The `BillableRateService` implements a 4-level hierarchy:
1. `ProjectMember.billable_rate` (highest priority)
2. `Project.billable_rate`
3. `Member.billable_rate`
4. `Organization.billable_rate` (fallback)

Methods:
- `getBillableRateForTimeEntry(TimeEntry $timeEntry): ?int` — retrieves rate from hierarchy
- `getBillableRateForTimeEntryWithGivenRelations(...)` — optimized version with eager-loaded relations
- `updateTimeEntriesBillableRateForProjectMember(ProjectMember $pm): void` — cascades rate changes
- `updateTimeEntriesBillableRateForMember(Member $member): void`
- `updateTimeEntriesBillableRateForOrganization(Organization $org): void`

**Cost Rate Service MUST mirror this pattern exactly**.

### 2.3 Controller Pattern

**Location**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/ReportController.php:1-174`

Controllers extend `App\Http\Controllers\Api\V1\Controller` which provides:
- `checkPermission(Organization $org, string $permission)` helper
- `user()` and `member(Organization $org)` helpers
- Organization injected via route model binding

**Standard CRUD pattern**:
```php
class ReportController extends Controller
{
    public function index(Organization $organization): ReportCollection
    {
        $this->checkPermission($organization, 'reports:view');
        $reports = Report::query()
            ->whereBelongsTo($organization, 'organization')
            ->paginate(config('app.pagination_per_page_default'));
        return new ReportCollection($reports);
    }
}
```

### 2.4 Migration Pattern

**Location**: `/home/keven/Documents/solidtime-analysis/database/migrations/2024_08_01_104840_create_reports_table.php:1-41`

Migrations use:
- Anonymous class extending `Migration`
- `declare(strict_types=1);` at top
- `up()` and `down()` methods
- Foreign keys with `->restrictOnDelete()->cascadeOnUpdate()` by default
- UUIDs via `$table->uuid('id')->primary()`
- JSONB columns via `$table->jsonb('column_name')`

### 2.5 Model Pattern

**Location**: `/home/keven/Documents/solidtime-analysis/app/Models/Report.php:1-64`

Models use:
- `declare(strict_types=1);`
- `HasUuids`, `HasFactory` traits
- Comprehensive PHPDoc with `@property` annotations
- `$casts` array for type safety
- Eloquent relationships with generic type hints
- DTOs for complex JSON columns (e.g., `ReportPropertiesDto`)

**Example**:
```php
/**
 * @property string $id
 * @property string $name
 * @property ReportPropertiesDto $properties
 * @property-read Organization $organization
 */
class Report extends Model
{
    use HasFactory, HasUuids;
    
    protected $casts = [
        'properties' => ReportPropertiesDto::class,
    ];
    
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
```

### 2.6 Computed Attributes Pattern

**Location**: `/home/keven/Documents/solidtime-analysis/app/Models/TimeEntry.php:100-146`

The `TimeEntry` model uses `korridor/laravel-computed-attributes` to cache derived fields:
```php
protected array $computed = ['billable_rate', 'client_id'];

public function getBillableRateComputed(): ?int
{
    return app(BillableRateService::class)->getBillableRateForTimeEntry($this);
}
```

This pattern allows lazy computation on read, with the ability to materialize via artisan command. **Cost rate will follow this exact pattern**.

### 2.7 Test Pattern

**Location**: `/home/keven/Documents/solidtime-analysis/tests/Unit/Endpoint/Api/V1/TimeEntryEndpointTest.php:1-150`

API endpoint tests:
- Extend `ApiEndpointTestAbstract`
- Use `Passport::actingAs($data->user)` for authentication
- Use `$this->createUserWithPermission([...])` helper to set up test data
- Test permission checks first, then happy paths, then edge cases
- Use `$this->assertResponseCode($response, 200)` and `$response->assertJsonPath()`

---

## 3. Architecture Decision

### 3.1 Decision: Mirror Billable Rate Hierarchy for Cost Rates

**Rationale**: The codebase already has a proven pattern for hierarchical rate calculation in `BillableRateService`. Reusing this exact pattern for cost rates:
- Maintains consistency
- Reduces learning curve
- Reuses battle-tested cascade update logic
- Simplifies testing (parallel test structure)

**Trade-offs Considered**:
- Alternative 1: Store cost rate only on Member, not ProjectMember or Organization
  - **Rejected**: Less flexibility. Some organizations need per-project cost allocation.
- Alternative 2: Calculate cost rate on-the-fly from salary data
  - **Rejected**: Adds complexity, privacy concerns, not requested in PRD.

### 3.2 Decision: Synchronous Cost Rate Computation

**Rationale**: AMD-06 from PRD mandates this. Cost rate is computed and stored on `time_entries.cost_rate` when:
- Time entry is created
- Time entry's project or member changes
- ProjectMember/Member/Organization cost rate changes (cascades to existing entries)

This matches the `billable_rate` pattern exactly.

**Trade-offs**:
- Alternative: Background job to compute cost rates
  - **Rejected**: Adds latency, complicates profitability reports (stale data), inconsistent with existing pattern.

### 3.3 Decision: Extend TimeEntryAggregationService, Not Replace

**Rationale**: The existing aggregation service has complex logic for tag expansion, gap filling, and two-level grouping. Rather than duplicate this, we:
1. Reuse `getAggregatedTimeEntries()` for grouping
2. Add new services (`ProfitabilityReportService`, `UtilizationReportService`) that wrap or extend this logic
3. Modify aggregation SQL to include cost calculations alongside billable revenue

**Implementation**:
- `ProfitabilityReportService` uses raw SQL aggregation similar to `TimeEntryAggregationService` but computes:
  ```sql
  round(sum(duration * billable_rate / 3600)) as revenue,  -- only billable entries
  round(sum(duration * cost_rate / 3600)) as cost          -- all entries
  ```
- Returns additional fields: `margin`, `margin_percent`

### 3.4 Decision: Phase A/B Split

**Rationale**: HIGH-07 from PRD review mandates splitting scope to manage risk.

**Phase A (Core Analytics) — Sprints 1-3, ~140h**:
- Cost rate infrastructure
- Profitability and utilization services
- Basic API endpoints
- Frontend report pages
- **Deliverable**: Independently valuable — enables margin analysis and capacity planning

**Phase B (Extended Reporting) — Sprints 4-6, ~170h**:
- Report schedules (email delivery)
- Report templates
- Expense management
- Budget reports
- Enhanced export (custom columns, styled PDFs)
- **Deliverable**: Adds convenience and integration, but not critical for day-1 value

---

## 4. Data Model Design

### 4.1 Schema Modifications to Existing Tables

#### Members Table

```sql
-- Migration: 2026_03_09_000001_add_cost_rate_to_members_table.php
ALTER TABLE members
  ADD COLUMN cost_rate INTEGER UNSIGNED NULL
    COMMENT 'Internal cost rate per hour in cents. Overrides organization default.',
  ADD INDEX idx_members_cost_rate (cost_rate);
```

**Eloquent Model Update** (`app/Models/Member.php`):
```php
/**
 * @property int|null $cost_rate  // Cost rate per hour in cents
 * @property int $weekly_capacity  // From FOUND-006, in seconds (default 144000 = 40h)
 */
class Member extends JetstreamMembership implements AuditableContract
{
    protected $casts = [
        'billable_rate' => 'int',
        'cost_rate' => 'int',          // ADD THIS
        'weekly_capacity' => 'int',    // From FOUND-006
    ];
}
```

#### ProjectMembers Table

```sql
-- Migration: 2026_03_09_000002_add_cost_rate_to_project_members_table.php
ALTER TABLE project_members
  ADD COLUMN cost_rate INTEGER UNSIGNED NULL
    COMMENT 'Project-specific cost rate override in cents. Highest priority in hierarchy.';
```

**Eloquent Model Update** (`app/Models/ProjectMember.php`):
```php
/**
 * @property int|null $cost_rate  // Project-specific cost rate override
 */
class ProjectMember extends Model
{
    protected $casts = [
        'billable_rate' => 'int',
        'cost_rate' => 'int',  // ADD THIS
    ];
}
```

#### Organizations Table

```sql
-- Migration: 2026_03_09_000003_add_default_cost_rate_to_organizations_table.php
ALTER TABLE organizations
  ADD COLUMN default_cost_rate INTEGER UNSIGNED NULL
    COMMENT 'Organization-wide default cost rate per hour in cents.';
```

**Eloquent Model Update** (`app/Models/Organization.php`):
```php
/**
 * @property int|null $default_cost_rate  // Default cost rate for organization
 * @property int $default_weekly_capacity  // From FOUND-006
 */
class Organization extends JetstreamTeam implements AuditableContract
{
    protected $casts = [
        'billable_rate' => 'int',
        'default_cost_rate' => 'int',        // ADD THIS
        'default_weekly_capacity' => 'int',  // From FOUND-006
    ];
}
```

#### TimeEntries Table

```sql
-- Migration: 2026_03_09_000004_add_cost_rate_to_time_entries_table.php
ALTER TABLE time_entries
  ADD COLUMN cost_rate INTEGER UNSIGNED NULL
    COMMENT 'Computed cost rate snapshot at time of entry creation/update.',
  ADD INDEX idx_time_entries_cost_rate (cost_rate);
```

**Eloquent Model Update** (`app/Models/TimeEntry.php`):
```php
/**
 * @property int|null $cost_rate  // Cost rate snapshot in cents
 */
class TimeEntry extends Model implements AuditableContract
{
    protected $casts = [
        'billable_rate' => 'int',
        'cost_rate' => 'int',  // ADD THIS
    ];
    
    protected array $computed = [
        'billable_rate',
        'client_id',
        'cost_rate',  // ADD THIS
    ];
    
    public function getCostRateComputed(): ?int
    {
        return app(CostRateService::class)->getCostRateForTimeEntry($this);
    }
}
```

#### Projects Table

```sql
-- Migration: 2026_03_09_000005_add_budget_amount_to_projects_table.php
ALTER TABLE projects
  ADD COLUMN budget_amount BIGINT UNSIGNED NULL
    COMMENT 'Monetary budget for the project in cents.';
```

**Eloquent Model Update** (`app/Models/Project.php`):
```php
/**
 * @property int|null $budget_amount  // Monetary budget in cents
 */
class Project extends Model
{
    protected $casts = [
        'estimated_time' => 'int',
        'budget_amount' => 'int',  // ADD THIS
    ];
}
```

### 4.2 New Table: report_templates

```sql
-- Migration: 2026_03_09_000006_create_report_templates_table.php
CREATE TABLE report_templates (
    id UUID PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    organization_id UUID NOT NULL,
    created_by_user_id UUID NOT NULL,
    properties JSONB NOT NULL,
    custom_columns JSONB NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    CONSTRAINT fk_report_templates_organization
        FOREIGN KEY (organization_id)
        REFERENCES organizations(id)
        ON DELETE CASCADE  -- AMD-08: CASCADE instead of RESTRICT
        ON UPDATE CASCADE,
    
    CONSTRAINT fk_report_templates_user
        FOREIGN KEY (created_by_user_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);

CREATE INDEX idx_report_templates_org ON report_templates(organization_id);
CREATE INDEX idx_report_templates_created_at ON report_templates(created_at DESC);
```

**Eloquent Model** (`app/Models/ReportTemplate.php`):
```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuids;
use App\Service\Dto\ReportPropertiesDto;
use Database\Factories\ReportTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property string $organization_id
 * @property string $created_by_user_id
 * @property ReportPropertiesDto $properties
 * @property array|null $custom_columns
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read User $createdBy
 *
 * @method static ReportTemplateFactory factory()
 */
class ReportTemplate extends Model
{
    /** @use HasFactory<ReportTemplateFactory> */
    use HasFactory;
    use HasUuids;

    protected $casts = [
        'properties' => ReportPropertiesDto::class,
        'custom_columns' => 'array',
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
```

### 4.3 New Table: report_schedules

```sql
-- Migration: 2026_03_09_000007_create_report_schedules_table.php
CREATE TABLE report_schedules (
    id UUID PRIMARY KEY,
    report_id UUID NOT NULL,
    organization_id UUID NOT NULL,
    created_by_user_id UUID NOT NULL,
    frequency VARCHAR(20) NOT NULL,
    day_of_week SMALLINT NULL,
    day_of_month SMALLINT NULL,
    time_of_day VARCHAR(5) NOT NULL,
    timezone VARCHAR(100) NOT NULL,
    export_format VARCHAR(10) NOT NULL,
    recipients JSONB NOT NULL,
    next_run_at TIMESTAMP NULL,
    last_run_at TIMESTAMP NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    failure_count INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    CONSTRAINT fk_report_schedules_report
        FOREIGN KEY (report_id)
        REFERENCES reports(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    
    CONSTRAINT fk_report_schedules_organization
        FOREIGN KEY (organization_id)
        REFERENCES organizations(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    
    CONSTRAINT fk_report_schedules_user
        FOREIGN KEY (created_by_user_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    
    CONSTRAINT chk_report_schedules_frequency
        CHECK (frequency IN ('daily', 'weekly', 'monthly')),
    
    CONSTRAINT chk_report_schedules_day_of_week
        CHECK (day_of_week IS NULL OR (day_of_week >= 0 AND day_of_week <= 6)),
    
    CONSTRAINT chk_report_schedules_day_of_month
        CHECK (day_of_month IS NULL OR (day_of_month >= 1 AND day_of_month <= 31)),
    
    CONSTRAINT chk_report_schedules_status
        CHECK (status IN ('active', 'paused', 'failed'))
);

CREATE INDEX idx_report_schedules_next_run 
    ON report_schedules(next_run_at) 
    WHERE status = 'active';
    
CREATE INDEX idx_report_schedules_report ON report_schedules(report_id);
CREATE INDEX idx_report_schedules_org ON report_schedules(organization_id);
```

**Eloquent Model** (`app/Models/ReportSchedule.php`):
```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReportScheduleFrequency;
use App\Enums\ReportScheduleStatus;
use App\Models\Concerns\HasUuids;
use Database\Factories\ReportScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $report_id
 * @property string $organization_id
 * @property string $created_by_user_id
 * @property ReportScheduleFrequency $frequency
 * @property int|null $day_of_week
 * @property int|null $day_of_month
 * @property string $time_of_day
 * @property string $timezone
 * @property string $export_format
 * @property array $recipients
 * @property Carbon|null $next_run_at
 * @property Carbon|null $last_run_at
 * @property ReportScheduleStatus $status
 * @property int $failure_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Report $report
 * @property-read Organization $organization
 * @property-read User $createdBy
 *
 * @method static ReportScheduleFactory factory()
 */
class ReportSchedule extends Model
{
    /** @use HasFactory<ReportScheduleFactory> */
    use HasFactory;
    use HasUuids;

    protected $casts = [
        'frequency' => ReportScheduleFrequency::class,
        'day_of_week' => 'int',
        'day_of_month' => 'int',
        'recipients' => 'array',
        'next_run_at' => 'datetime',
        'last_run_at' => 'datetime',
        'status' => ReportScheduleStatus::class,
        'failure_count' => 'int',
    ];

    /**
     * @return BelongsTo<Report, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'report_id');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
```

### 4.4 New Table: expenses

```sql
-- Migration: 2026_03_09_000008_create_expenses_table.php
CREATE TABLE expenses (
    id UUID PRIMARY KEY,
    amount INTEGER NOT NULL,
    currency VARCHAR(3) NOT NULL,
    category VARCHAR(50) NOT NULL,
    date DATE NOT NULL,
    description VARCHAR(500) NOT NULL,
    project_id UUID NULL,
    member_id UUID NOT NULL,
    user_id UUID NOT NULL,
    organization_id UUID NOT NULL,
    billable BOOLEAN NOT NULL DEFAULT FALSE,
    receipt_path VARCHAR(500) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    CONSTRAINT fk_expenses_project
        FOREIGN KEY (project_id)
        REFERENCES projects(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    
    CONSTRAINT fk_expenses_member
        FOREIGN KEY (member_id)
        REFERENCES members(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    
    CONSTRAINT fk_expenses_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    
    CONSTRAINT fk_expenses_organization
        FOREIGN KEY (organization_id)
        REFERENCES organizations(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    
    CONSTRAINT chk_expenses_amount_positive
        CHECK (amount > 0),
    
    CONSTRAINT chk_expenses_category
        CHECK (category IN ('travel', 'meals', 'software', 'hardware', 'office', 'other'))
);

CREATE INDEX idx_expenses_org ON expenses(organization_id);
CREATE INDEX idx_expenses_project ON expenses(project_id);
CREATE INDEX idx_expenses_member ON expenses(member_id);
CREATE INDEX idx_expenses_date ON expenses(date);
CREATE INDEX idx_expenses_category ON expenses(category);
```

**Eloquent Model** (`app/Models/Expense.php`):
```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExpenseCategory;
use App\Models\Concerns\CustomAuditable;
use App\Models\Concerns\HasUuids;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property string $id
 * @property int $amount
 * @property string $currency
 * @property ExpenseCategory $category
 * @property Carbon $date
 * @property string $description
 * @property string|null $project_id
 * @property string $member_id
 * @property string $user_id
 * @property string $organization_id
 * @property bool $billable
 * @property string|null $receipt_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read Member $member
 * @property-read User $user
 * @property-read Project|null $project
 *
 * @method static ExpenseFactory factory()
 */
class Expense extends Model implements AuditableContract
{
    use CustomAuditable;

    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;
    use HasUuids;

    protected $casts = [
        'amount' => 'int',
        'category' => ExpenseCategory::class,
        'date' => 'date',
        'billable' => 'bool',
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
```

### 4.5 Enums

#### ExpenseCategory Enum

**File**: `app/Enums/ExpenseCategory.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

use Korridor\LaravelEnumHelper\LaravelEnumHelper;

enum ExpenseCategory: string
{
    use LaravelEnumHelper;

    case Travel = 'travel';
    case Meals = 'meals';
    case Software = 'software';
    case Hardware = 'hardware';
    case Office = 'office';
    case Other = 'other';
}
```

#### ReportScheduleFrequency Enum

**File**: `app/Enums/ReportScheduleFrequency.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

use Korridor\LaravelEnumHelper\LaravelEnumHelper;

enum ReportScheduleFrequency: string
{
    use LaravelEnumHelper;

    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
}
```

#### ReportScheduleStatus Enum

**File**: `app/Enums/ReportScheduleStatus.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

use Korridor\LaravelEnumHelper\LaravelEnumHelper;

enum ReportScheduleStatus: string
{
    use LaravelEnumHelper;

    case Active = 'active';
    case Paused = 'paused';
    case Failed = 'failed';
}
```

---

## 5. API Contract

### 5.1 Profitability Report Endpoint

**Route**: `GET /api/v1/organizations/{organization}/reports/profitability`

**Controller**: `App\Http\Controllers\Api\V1\ProfitabilityReportController@index`

**Request Validation** (`app/Http/Requests/V1/Report/ProfitabilityReportRequest.php`):

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Report;

use App\Enums\TimeEntryAggregationType;
use App\Http\Requests\V1\BaseFormRequest;
use App\Models\Client;
use App\Models\Member;
use App\Models\Project;
use Illuminate\Validation\Rule;
use Korridor\LaravelModelValidationRules\Rules\ExistsEloquent;

class ProfitabilityReportRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after_or_equal:start'],
            'group' => ['required', Rule::enum(TimeEntryAggregationType::class)],
            'sub_group' => ['nullable', Rule::enum(TimeEntryAggregationType::class)],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['uuid', new ExistsEloquent(Member::class, null, function ($query) {
                $query->whereBelongsTo($this->organization, 'organization');
            })],
            'client_ids' => ['nullable', 'array'],
            'client_ids.*' => ['uuid', new ExistsEloquent(Client::class, null, function ($query) {
                $query->whereBelongsTo($this->organization, 'organization');
            })],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['uuid', new ExistsEloquent(Project::class, null, function ($query) {
                $query->whereBelongsTo($this->organization, 'organization');
            })],
            'billable' => ['nullable', 'boolean'],
            'include_expenses' => ['nullable', 'boolean'],
        ];
    }
}
```

**Response Resource** (`app/Http/Resources/V1/Report/ProfitabilityReportResource.php`):

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Report;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfitabilityReportResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'grouped_type' => $this->resource['grouped_type'],
            'grouped_data' => $this->resource['grouped_data'],
            'total_seconds' => $this->resource['total_seconds'],
            'revenue' => $this->resource['revenue'],
            'cost' => $this->resource['cost'],
            'margin' => $this->resource['margin'],
            'margin_percent' => $this->resource['margin_percent'],
            'currency' => $this->resource['currency'],
        ];
    }
}
```

**Response Structure**:

```json
{
  "data": {
    "grouped_type": "project",
    "grouped_data": [
      {
        "key": "project-uuid",
        "description": "Project Alpha",
        "color": "#3b82f6",
        "total_seconds": 144000,
        "revenue": 100000,
        "cost": 60000,
        "margin": 40000,
        "margin_percent": 40.0,
        "grouped_type": null,
        "grouped_data": null
      }
    ],
    "total_seconds": 144000,
    "revenue": 100000,
    "cost": 60000,
    "margin": 40000,
    "margin_percent": 40.0,
    "currency": "USD"
  }
}
```

**Permissions**: `reports:view`

**Rate Limiting**: AMD-11 — Throttle to 10 requests per minute per organization

```php
// In routes/api.php
Route::middleware(['throttle:10,1'])->group(function () {
    Route::get('/reports/profitability', [ProfitabilityReportController::class, 'index'])
        ->name('reports.profitability.index');
    Route::get('/reports/utilization', [UtilizationReportController::class, 'index'])
        ->name('reports.utilization.index');
});
```

### 5.2 Utilization Report Endpoint

**Route**: `GET /api/v1/organizations/{organization}/reports/utilization`

**Request Validation** (`app/Http/Requests/V1/Report/UtilizationReportRequest.php`):

```php
public function rules(): array
{
    return [
        'start' => ['required', 'date'],
        'end' => ['required', 'date', 'after_or_equal:start'],
        'member_ids' => ['nullable', 'array'],
        'member_ids.*' => ['uuid', new ExistsEloquent(Member::class, null, function ($query) {
            $query->whereBelongsTo($this->organization, 'organization');
        })],
        'project_ids' => ['nullable', 'array'],
        'project_ids.*' => ['uuid', new ExistsEloquent(Project::class)],
        'include_daily_breakdown' => ['nullable', 'boolean'],
    ];
}
```

**Response Structure**:

```json
{
  "data": {
    "members": [
      {
        "member_id": "member-uuid",
        "member_name": "John Doe",
        "capacity_seconds": 288000,
        "actual_seconds": 230400,
        "billable_seconds": 180000,
        "utilization_percent": 80.0,
        "billable_utilization_percent": 62.5,
        "daily_breakdown": [
          {
            "date": "2026-02-01",
            "capacity_seconds": 28800,
            "actual_seconds": 25200
          }
        ]
      }
    ],
    "period": {
      "start": "2026-02-01",
      "end": "2026-02-14",
      "weeks": 2.0
    }
  }
}
```

### 5.3 Report Templates CRUD

**Routes**:

```php
Route::name('report-templates.')->prefix('/organizations/{organization}/report-templates')->group(function () {
    Route::get('/', [ReportTemplateController::class, 'index'])->name('index');
    Route::post('/', [ReportTemplateController::class, 'store'])->name('store');
    Route::get('/{reportTemplate}', [ReportTemplateController::class, 'show'])->name('show');
    Route::put('/{reportTemplate}', [ReportTemplateController::class, 'update'])->name('update');
    Route::delete('/{reportTemplate}', [ReportTemplateController::class, 'destroy'])->name('destroy');
});
```

**Store Request**:

```php
public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        'description' => ['nullable', 'string'],
        'properties' => ['required', 'array'],
        'properties.group' => ['nullable', Rule::enum(TimeEntryAggregationType::class)],
        'properties.sub_group' => ['nullable', Rule::enum(TimeEntryAggregationType::class)],
        // ... (same as Report properties validation)
        'custom_columns' => ['nullable', 'array'],
        'custom_columns.*' => ['string', 'in:date,start,end,duration,description,project,client,task,tags,member,billable,billable_rate,billable_amount,cost_rate,cost_amount'],
    ];
}
```

### 5.4 Report Schedules CRUD

**Routes** (nested under reports):

```php
Route::name('reports.schedules.')->prefix('/organizations/{organization}/reports/{report}/schedules')->group(function () {
    Route::get('/', [ReportScheduleController::class, 'index'])->name('index');
    Route::post('/', [ReportScheduleController::class, 'store'])->name('store');
    Route::put('/{schedule}', [ReportScheduleController::class, 'update'])->name('update');
    Route::delete('/{schedule}', [ReportScheduleController::class, 'destroy'])->name('destroy');
});
```

**Store Request**:

```php
public function rules(): array
{
    return [
        'frequency' => ['required', Rule::enum(ReportScheduleFrequency::class)],
        'day_of_week' => ['nullable', 'integer', 'min:0', 'max:6', 'required_if:frequency,weekly'],
        'day_of_month' => ['nullable', 'integer', 'min:1', 'max:31', 'required_if:frequency,monthly'],
        'time_of_day' => ['required', 'regex:/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/'],
        'timezone' => ['required', 'string', 'timezone'],
        'export_format' => ['required', Rule::enum(ExportFormat::class)],
        'recipients' => ['required', 'array', 'min:1', 'max:10'],
        'recipients.*' => ['email'],
    ];
}
```

**Business Rule Enforcement** (in controller):

```php
public function store(Organization $organization, Report $report, ReportScheduleStoreRequest $request)
{
    $this->checkPermission($organization, 'reports:create');
    
    // Max 5 schedules per report
    if ($report->schedules()->count() >= 5) {
        return response()->json([
            'message' => 'Maximum of 5 schedules per report allowed.'
        ], 422);
    }
    
    // Create schedule...
}
```

### 5.5 Expenses CRUD

**Routes**:

```php
Route::name('expenses.')->prefix('/organizations/{organization}/expenses')->group(function () {
    Route::get('/', [ExpenseController::class, 'index'])->name('index');
    Route::post('/', [ExpenseController::class, 'store'])->name('store');
    Route::get('/{expense}', [ExpenseController::class, 'show'])->name('show');
    Route::put('/{expense}', [ExpenseController::class, 'update'])->name('update');
    Route::delete('/{expense}', [ExpenseController::class, 'destroy'])->name('destroy');
    Route::get('/{expense}/receipt', [ExpenseController::class, 'downloadReceipt'])->name('download-receipt');
});
```

**Store Request**:

```php
public function rules(): array
{
    return [
        'amount' => ['required', 'integer', 'min:1'],
        'currency' => ['nullable', 'string', 'size:3'],
        'category' => ['required', Rule::enum(ExpenseCategory::class)],
        'date' => ['required', 'date'],
        'description' => ['required', 'string', 'max:500'],
        'project_id' => ['nullable', 'uuid', new ExistsEloquent(Project::class, null, function ($query) {
            $query->whereBelongsTo($this->organization, 'organization');
        })],
        'billable' => ['required', 'boolean'],
        'receipt' => ['nullable', 'file', 'max:10240', 'mimes:pdf,png,jpg,jpeg'],
    ];
}
```

**Receipt Upload Implementation** (in controller):

```php
public function store(Organization $organization, ExpenseStoreRequest $request)
{
    $this->checkPermission($organization, 'expenses:create');
    
    $expense = new Expense;
    $expense->amount = $request->input('amount');
    $expense->currency = $request->input('currency', $organization->currency);
    // ... other fields
    
    if ($request->hasFile('receipt')) {
        $path = $request->file('receipt')->store('receipts', 'private');
        $expense->receipt_path = $path;
    }
    
    $expense->organization()->associate($organization);
    $expense->member()->associate($this->member($organization));
    $expense->user()->associate($this->user());
    $expense->save();
    
    return new ExpenseResource($expense);
}

public function downloadReceipt(Organization $organization, Expense $expense)
{
    $this->checkPermission($organization, 'expenses:view:all');
    
    if (!$expense->receipt_path || !Storage::disk('private')->exists($expense->receipt_path)) {
        abort(404, 'Receipt not found');
    }
    
    return Storage::disk('private')->download($expense->receipt_path);
}
```

### 5.6 Budget Report Endpoint

**Route**: `GET /api/v1/organizations/{organization}/reports/budget`

**Response Structure**:

```json
{
  "data": {
    "projects": [
      {
        "project_id": "uuid",
        "project_name": "Project Alpha",
        "time_budget_seconds": 360000,
        "time_spent_seconds": 280000,
        "time_remaining_seconds": 80000,
        "time_percent": 77.8,
        "money_budget": 500000,
        "money_spent": 420000,
        "money_remaining": 80000,
        "money_percent": 84.0,
        "status": "on_track"
      }
    ]
  }
}
```

---

## 6. Service Layer Architecture

### 6.1 CostRateService

**File**: `app/Service/CostRateService.php`

**Purpose**: Mirror `BillableRateService` exactly, but for cost rates.

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Models\Member;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\TimeEntry;
use Illuminate\Database\Eloquent\Builder;

class CostRateService
{
    /**
     * Get cost rate for a time entry using the hierarchy:
     * ProjectMember > Member > Organization
     */
    public function getCostRateForTimeEntry(TimeEntry $timeEntry): ?int
    {
        if ($timeEntry->project_id !== null) {
            // ProjectMember cost rate (highest priority)
            /** @var ProjectMember|null $projectMember */
            $projectMember = ProjectMember::query()
                ->where('user_id', '=', $timeEntry->user_id)
                ->where('project_id', '=', $timeEntry->project_id)
                ->first();
            if ($projectMember !== null && $projectMember->cost_rate !== null) {
                return $projectMember->cost_rate;
            }
        }

        // Member cost rate
        /** @var Member|null $member */
        $member = Member::query()
            ->where('user_id', '=', $timeEntry->user_id)
            ->where('organization_id', '=', $timeEntry->organization_id)
            ->first();
        if ($member !== null && $member->cost_rate !== null) {
            return $member->cost_rate;
        }

        // Organization default cost rate
        /** @var Organization|null $organization */
        $organization = Organization::query()
            ->where('id', '=', $timeEntry->organization_id)
            ->first();
        if ($organization !== null && $organization->default_cost_rate !== null) {
            return $organization->default_cost_rate;
        }

        return null;
    }

    /**
     * Optimized version with preloaded relations
     */
    public function getCostRateForTimeEntryWithGivenRelations(
        TimeEntry $timeEntry,
        ?ProjectMember $projectMember,
        ?Member $member,
        ?Organization $organization
    ): ?int {
        if ($projectMember !== null && $projectMember->cost_rate !== null) {
            return $projectMember->cost_rate;
        }
        if ($member !== null && $member->cost_rate !== null) {
            return $member->cost_rate;
        }
        if ($organization !== null && $organization->default_cost_rate !== null) {
            return $organization->default_cost_rate;
        }

        return null;
    }

    /**
     * Update all time entries for a ProjectMember when their cost rate changes
     */
    public function updateTimeEntriesCostRateForProjectMember(ProjectMember $projectMember): void
    {
        TimeEntry::query()
            ->where('member_id', '=', $projectMember->member_id)
            ->where('project_id', '=', $projectMember->project_id)
            ->update(['cost_rate' => $projectMember->cost_rate]);
    }

    /**
     * Update all time entries for a Member when their cost rate changes
     * Excludes entries where ProjectMember has a cost_rate override
     */
    public function updateTimeEntriesCostRateForMember(Member $member): void
    {
        TimeEntry::query()
            ->where('organization_id', '=', $member->organization_id)
            ->where('member_id', '=', $member->getKey())
            ->whereDoesntHave('project', function (Builder $builder) use ($member): void {
                /** @var Builder<Project> $builder */
                $builder->whereHas('members', function (Builder $builder) use ($member): void {
                    /** @var Builder<ProjectMember> $builder */
                    $builder->whereNotNull('cost_rate')
                        ->where('member_id', '=', $member->getKey());
                });
            })
            ->update(['cost_rate' => $member->cost_rate]);
    }

    /**
     * Update all time entries for an Organization when default cost rate changes
     * Excludes entries where Member or ProjectMember has a cost_rate override
     */
    public function updateTimeEntriesCostRateForOrganization(Organization $organization): void
    {
        TimeEntry::query()
            ->where('organization_id', '=', $organization->getKey())
            ->whereDoesntHave('member', function (Builder $builder): void {
                /** @var Builder<Member> $builder */
                $builder->whereNotNull('cost_rate');
            })
            ->whereDoesntHave('project', function (Builder $builder): void {
                /** @var Builder<Project> $builder */
                $builder->whereHas('members', function (Builder $builder): void {
                    /** @var Builder<ProjectMember> $builder */
                    $builder->whereNotNull('cost_rate')
                        ->whereRaw('member_id = time_entries.member_id');
                });
            })
            ->update(['cost_rate' => $organization->default_cost_rate]);
    }
}
```

**Unit Tests**: `tests/Unit/Service/CostRateServiceTest.php`

Test scenarios:
1. Time entry with ProjectMember cost rate returns ProjectMember rate
2. Time entry without ProjectMember cost rate falls back to Member rate
3. Time entry without Member cost rate falls back to Organization rate
4. Time entry with no cost rates returns null
5. Cascade update on ProjectMember rate change updates only relevant entries
6. Cascade update on Member rate change excludes entries with ProjectMember overrides
7. Cascade update on Organization rate change excludes entries with Member/ProjectMember overrides

### 6.2 ProfitabilityReportService

**File**: `app/Service/ProfitabilityReportService.php`

**Purpose**: Calculate revenue, cost, and margin for time entries with grouping.

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Enums\TimeEntryAggregationType;
use App\Enums\Weekday;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProfitabilityReportService
{
    /**
     * Generate profitability report with revenue, cost, and margin
     *
     * @param  Builder  $timeEntriesQuery  Base query for time entries (already filtered)
     * @param  TimeEntryAggregationType|null  $groupType  Primary grouping
     * @param  TimeEntryAggregationType|null  $subGroupType  Secondary grouping
     * @param  string  $currency  Organization currency code
     * @param  bool  $includeExpenses  Whether to include expenses in cost calculation
     * @return array{
     *     grouped_type: string|null,
     *     grouped_data: array<array{
     *         key: string|null,
     *         description: string|null,
     *         color: string|null,
     *         total_seconds: int,
     *         revenue: int,
     *         cost: int,
     *         margin: int,
     *         margin_percent: float|null,
     *         grouped_type: string|null,
     *         grouped_data: array|null
     *     }>,
     *     total_seconds: int,
     *     revenue: int,
     *     cost: int,
     *     margin: int,
     *     margin_percent: float|null,
     *     currency: string
     * }
     */
    public function getProfitabilityReport(
        Builder $timeEntriesQuery,
        ?TimeEntryAggregationType $groupType,
        ?TimeEntryAggregationType $subGroupType,
        string $currency,
        bool $includeExpenses = false
    ): array {
        $group1Select = null;
        $group2Select = null;
        $groupBy = null;

        if ($groupType !== null) {
            $group1Select = $this->getGroupByQuery($groupType);
            $groupBy = ['group_1'];
            if ($subGroupType !== null) {
                $group2Select = $this->getGroupByQuery($subGroupType);
                $groupBy = ['group_1', 'group_2'];
            }
        }

        // Aggregate time entries
        $timeEntriesQuery->selectRaw(
            ($group1Select !== null ? $group1Select . ' as group_1,' : '') .
            ($group2Select !== null ? $group2Select . ' as group_2,' : '') .
            ' round(sum(extract(epoch from ("end" - start)))) as total_seconds,' .
            ' round(sum(CASE WHEN billable THEN extract(epoch from ("end" - start)) * (coalesce(billable_rate, 0)::float/3600) ELSE 0 END)) as revenue,' .
            ' round(sum(extract(epoch from ("end" - start)) * (coalesce(cost_rate, 0)::float/3600))) as cost'
        );

        if ($groupBy !== null) {
            $timeEntriesQuery->groupBy($groupBy);
            $timeEntriesQuery->orderBy('group_1');
            if ($group2Select !== null) {
                $timeEntriesQuery->orderBy('group_2');
            }
        }

        $aggregates = $timeEntriesQuery->get();

        if ($groupType !== null) {
            $grouped = $aggregates->groupBy($subGroupType !== null ? ['group_1', 'group_2'] : ['group_1']);
            $groupedData = $this->buildGroupedResponse($grouped, $groupType, $subGroupType);
        } else {
            $groupedData = null;
        }

        // Calculate totals
        $totalSeconds = $aggregates->sum('total_seconds');
        $totalRevenue = $aggregates->sum('revenue');
        $totalCost = $aggregates->sum('cost');
        $totalMargin = $totalRevenue - $totalCost;
        $marginPercent = $totalRevenue > 0 ? ($totalMargin / $totalRevenue) * 100 : null;

        return [
            'grouped_type' => $groupType?->value,
            'grouped_data' => $groupedData,
            'total_seconds' => (int) $totalSeconds,
            'revenue' => (int) $totalRevenue,
            'cost' => (int) $totalCost,
            'margin' => (int) $totalMargin,
            'margin_percent' => $marginPercent,
            'currency' => $currency,
        ];
    }

    /**
     * Build SQL fragment for grouping by type
     */
    private function getGroupByQuery(TimeEntryAggregationType $type): string
    {
        return match ($type) {
            TimeEntryAggregationType::Client => 'client_id',
            TimeEntryAggregationType::Project => 'project_id',
            TimeEntryAggregationType::User => 'user_id',
            TimeEntryAggregationType::Task => 'task_id',
            TimeEntryAggregationType::Month => "to_char(start, 'YYYY-MM')",
            TimeEntryAggregationType::Week => "to_char(date_trunc('week', start), 'YYYY-MM-DD')",
            TimeEntryAggregationType::Day => "to_char(start, 'YYYY-MM-DD')",
            default => throw new \InvalidArgumentException("Unsupported grouping type: {$type->value}"),
        };
    }

    /**
     * Build hierarchical grouped response with margin calculations
     */
    private function buildGroupedResponse($grouped, $groupType, $subGroupType): array
    {
        $response = [];
        foreach ($grouped as $group1Key => $group1Data) {
            $group1Revenue = 0;
            $group1Cost = 0;
            $group1Seconds = 0;
            $subGroupData = null;

            if ($subGroupType !== null) {
                $subGroupData = [];
                foreach ($group1Data as $group2Key => $aggregate) {
                    $revenue = (int) $aggregate->first()->revenue;
                    $cost = (int) $aggregate->first()->cost;
                    $margin = $revenue - $cost;
                    $marginPercent = $revenue > 0 ? ($margin / $revenue) * 100 : null;

                    $subGroupData[] = [
                        'key' => $group2Key === '' ? null : (string) $group2Key,
                        'description' => null, // Populated later via descriptor lookup
                        'color' => null,
                        'total_seconds' => (int) $aggregate->first()->total_seconds,
                        'revenue' => $revenue,
                        'cost' => $cost,
                        'margin' => $margin,
                        'margin_percent' => $marginPercent,
                        'grouped_type' => null,
                        'grouped_data' => null,
                    ];

                    $group1Revenue += $revenue;
                    $group1Cost += $cost;
                    $group1Seconds += (int) $aggregate->first()->total_seconds;
                }
            } else {
                $group1Revenue = (int) $group1Data->first()->revenue;
                $group1Cost = (int) $group1Data->first()->cost;
                $group1Seconds = (int) $group1Data->first()->total_seconds;
            }

            $group1Margin = $group1Revenue - $group1Cost;
            $group1MarginPercent = $group1Revenue > 0 ? ($group1Margin / $group1Revenue) * 100 : null;

            $response[] = [
                'key' => $group1Key === '' ? null : (string) $group1Key,
                'description' => null,
                'color' => null,
                'total_seconds' => $group1Seconds,
                'revenue' => $group1Revenue,
                'cost' => $group1Cost,
                'margin' => $group1Margin,
                'margin_percent' => $group1MarginPercent,
                'grouped_type' => $subGroupType?->value,
                'grouped_data' => $subGroupData,
            ];
        }

        return $response;
    }
}
```

**Unit Tests**: `tests/Unit/Service/ProfitabilityReportServiceTest.php`

Test scenarios:
1. Single group profitability (by project)
2. Two-level grouping (client > project)
3. Zero revenue case (margin_percent is null)
4. Negative margin case (cost > revenue)
5. Mixed billable/non-billable entries (revenue only from billable)
6. Entries with null cost rates (treated as zero)
7. Multiple projects with different margins

### 6.3 UtilizationReportService

**File**: `app/Service/UtilizationReportService.php`

**Purpose**: Calculate member utilization against weekly capacity.

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Models\Member;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UtilizationReportService
{
    /**
     * Generate utilization report for members
     *
     * @param  Organization  $organization
     * @param  Carbon  $start
     * @param  Carbon  $end
     * @param  Collection|null  $memberIds  Filter by specific members
     * @param  Collection|null  $projectIds  Filter by specific projects
     * @param  bool  $includeDailyBreakdown
     * @return array{
     *     members: array<array{
     *         member_id: string,
     *         member_name: string,
     *         capacity_seconds: int,
     *         actual_seconds: int,
     *         billable_seconds: int,
     *         utilization_percent: float|null,
     *         billable_utilization_percent: float|null,
     *         daily_breakdown: array|null
     *     }>,
     *     period: array{start: string, end: string, weeks: float}
     * }
     */
    public function getUtilizationReport(
        Organization $organization,
        Carbon $start,
        Carbon $end,
        ?Collection $memberIds = null,
        ?Collection $projectIds = null,
        bool $includeDailyBreakdown = false
    ): array {
        $periodDays = $start->diffInDays($end) + 1;
        $periodWeeks = $periodDays / 7;

        // Get members with their capacities
        $membersQuery = Member::query()
            ->where('organization_id', '=', $organization->getKey())
            ->with('user:id,name');

        if ($memberIds !== null && $memberIds->isNotEmpty()) {
            $membersQuery->whereIn('id', $memberIds);
        }

        $members = $membersQuery->get();

        // Get time entry aggregations per member
        $timeEntriesQuery = DB::table('time_entries')
            ->select(
                'member_id',
                DB::raw('round(sum(extract(epoch from ("end" - start)))) as actual_seconds'),
                DB::raw('round(sum(CASE WHEN billable THEN extract(epoch from ("end" - start)) ELSE 0 END)) as billable_seconds')
            )
            ->where('organization_id', '=', $organization->getKey())
            ->whereBetween('start', [$start, $end])
            ->whereNotNull('end')
            ->groupBy('member_id');

        if ($projectIds !== null && $projectIds->isNotEmpty()) {
            $timeEntriesQuery->whereIn('project_id', $projectIds);
        }

        $timeEntryAggregates = $timeEntriesQuery->get()->keyBy('member_id');

        // Build response
        $membersData = [];
        foreach ($members as $member) {
            $capacitySeconds = (int) ($member->weekly_capacity * $periodWeeks);
            $actual = $timeEntryAggregates->get($member->id);
            $actualSeconds = $actual ? (int) $actual->actual_seconds : 0;
            $billableSeconds = $actual ? (int) $actual->billable_seconds : 0;

            $utilizationPercent = $capacitySeconds > 0 ? ($actualSeconds / $capacitySeconds) * 100 : null;
            $billableUtilizationPercent = $capacitySeconds > 0 ? ($billableSeconds / $capacitySeconds) * 100 : null;

            $dailyBreakdown = null;
            if ($includeDailyBreakdown) {
                $dailyBreakdown = $this->getDailyBreakdown($member, $start, $end);
            }

            $membersData[] = [
                'member_id' => $member->id,
                'member_name' => $member->user->name,
                'capacity_seconds' => $capacitySeconds,
                'actual_seconds' => $actualSeconds,
                'billable_seconds' => $billableSeconds,
                'utilization_percent' => $utilizationPercent,
                'billable_utilization_percent' => $billableUtilizationPercent,
                'daily_breakdown' => $dailyBreakdown,
            ];
        }

        return [
            'members' => $membersData,
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'weeks' => $periodWeeks,
            ],
        ];
    }

    /**
     * Get daily breakdown for a member
     */
    private function getDailyBreakdown(Member $member, Carbon $start, Carbon $end): array
    {
        $dailyCapacity = (int) ($member->weekly_capacity / 7);

        $dailyAggregates = DB::table('time_entries')
            ->select(
                DB::raw("to_char(start, 'YYYY-MM-DD') as date"),
                DB::raw('round(sum(extract(epoch from ("end" - start)))) as actual_seconds')
            )
            ->where('member_id', '=', $member->id)
            ->whereBetween('start', [$start, $end])
            ->whereNotNull('end')
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $breakdown = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateStr = $date->toDateString();
            $daily = $dailyAggregates->get($dateStr);
            $breakdown[] = [
                'date' => $dateStr,
                'capacity_seconds' => $dailyCapacity,
                'actual_seconds' => $daily ? (int) $daily->actual_seconds : 0,
            ];
        }

        return $breakdown;
    }
}
```

**Unit Tests**: `tests/Unit/Service/UtilizationReportServiceTest.php`

Test scenarios:
1. Full week utilization (100%)
2. Partial week utilization (80%)
3. Overtime (>100%)
4. Zero capacity member (returns null for percentages)
5. Member with no time entries (0% utilization)
6. Daily breakdown matches aggregated totals
7. Multi-week period pro-rates capacity correctly

### 6.4 ReportScheduleService

**File**: `app/Service/ReportScheduleService.php`

**Purpose**: Manage schedule lifecycle and next-run calculation.

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Enums\ReportScheduleFrequency;
use App\Enums\ReportScheduleStatus;
use App\Models\ReportSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportScheduleService
{
    /**
     * Calculate next run timestamp based on schedule frequency
     */
    public function calculateNextRunAt(ReportSchedule $schedule): Carbon
    {
        $timezone = $schedule->timezone;
        [$hour, $minute] = explode(':', $schedule->time_of_day);

        $now = Carbon::now($timezone);
        $next = Carbon::now($timezone)->setTime((int) $hour, (int) $minute, 0);

        return match ($schedule->frequency) {
            ReportScheduleFrequency::Daily => $this->getNextDaily($next, $now),
            ReportScheduleFrequency::Weekly => $this->getNextWeekly($next, $now, $schedule->day_of_week),
            ReportScheduleFrequency::Monthly => $this->getNextMonthly($next, $now, $schedule->day_of_month),
        };
    }

    private function getNextDaily(Carbon $next, Carbon $now): Carbon
    {
        if ($next->lte($now)) {
            $next->addDay();
        }
        return $next->setTimezone('UTC');
    }

    private function getNextWeekly(Carbon $next, Carbon $now, int $dayOfWeek): Carbon
    {
        $next->setISODate($next->year, $next->week, $dayOfWeek + 1); // 0=Sun -> 1=Mon in ISO
        if ($next->lte($now)) {
            $next->addWeek();
        }
        return $next->setTimezone('UTC');
    }

    private function getNextMonthly(Carbon $next, Carbon $now, int $dayOfMonth): Carbon
    {
        $next->day = min($dayOfMonth, $next->daysInMonth); // Fallback for short months
        if ($next->lte($now)) {
            $next->addMonth();
            $next->day = min($dayOfMonth, $next->daysInMonth);
        }
        return $next->setTimezone('UTC');
    }

    /**
     * Get all schedules due for execution
     */
    public function getDueSchedules(): Collection
    {
        return ReportSchedule::query()
            ->where('status', '=', ReportScheduleStatus::Active->value)
            ->where('next_run_at', '<=', Carbon::now('UTC'))
            ->with(['report', 'organization'])
            ->get();
    }

    /**
     * Mark schedule as completed and calculate next run
     */
    public function markAsCompleted(ReportSchedule $schedule): void
    {
        $schedule->last_run_at = Carbon::now('UTC');
        $schedule->next_run_at = $this->calculateNextRunAt($schedule);
        $schedule->failure_count = 0;
        $schedule->status = ReportScheduleStatus::Active;
        $schedule->save();
    }

    /**
     * Mark schedule as failed and increment failure count
     */
    public function markAsFailed(ReportSchedule $schedule, string $reason): void
    {
        $schedule->failure_count++;
        if ($schedule->failure_count >= 3) {
            $schedule->status = ReportScheduleStatus::Failed;
        }
        $schedule->save();
        
        \Log::error("Report schedule {$schedule->id} failed", [
            'reason' => $reason,
            'failure_count' => $schedule->failure_count,
        ]);
    }

    public function pause(ReportSchedule $schedule): void
    {
        $schedule->status = ReportScheduleStatus::Paused;
        $schedule->save();
    }

    public function resume(ReportSchedule $schedule): void
    {
        $schedule->status = ReportScheduleStatus::Active;
        $schedule->next_run_at = $this->calculateNextRunAt($schedule);
        $schedule->failure_count = 0;
        $schedule->save();
    }
}
```

### 6.5 ReportExportService

**File**: `app/Service/ReportExportService.php`

**Purpose**: Generate exports with custom columns and grouping.

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Enums\ExportFormat;
use Maatwebsite\Excel\Facades\Excel;
use App\Service\Export\ProfitabilityReportExport;
use App\Service\Export\UtilizationReportExport;

class ReportExportService
{
    /**
     * Export profitability report data
     */
    public function exportProfitabilityReport(
        array $reportData,
        ExportFormat $format,
        ?array $customColumns = null
    ): string {
        $export = new ProfitabilityReportExport($reportData, $customColumns);
        
        $fileName = 'profitability-report-' . now()->format('Y-m-d-His') . '.' . $format->value;
        $filePath = 'exports/' . $fileName;
        
        Excel::store($export, $filePath, 'local', $this->getWriterType($format));
        
        return storage_path('app/' . $filePath);
    }

    /**
     * Export utilization report data
     */
    public function exportUtilizationReport(
        array $reportData,
        ExportFormat $format
    ): string {
        $export = new UtilizationReportExport($reportData);
        
        $fileName = 'utilization-report-' . now()->format('Y-m-d-His') . '.' . $format->value;
        $filePath = 'exports/' . $fileName;
        
        Excel::store($export, $filePath, 'local', $this->getWriterType($format));
        
        return storage_path('app/' . $filePath);
    }

    private function getWriterType(ExportFormat $format): string
    {
        return match ($format) {
            ExportFormat::Csv => \Maatwebsite\Excel\Excel::CSV,
            ExportFormat::Xlsx => \Maatwebsite\Excel\Excel::XLSX,
            ExportFormat::Pdf => \Maatwebsite\Excel\Excel::DOMPDF,
            ExportFormat::Ods => \Maatwebsite\Excel\Excel::ODS,
        };
    }
}
```

**Export Class** (`app/Service/Export/ProfitabilityReportExport.php`):

```php
<?php

declare(strict_types=1);

namespace App\Service\Export;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Collection;

class ProfitabilityReportExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    private array $reportData;
    private ?array $customColumns;

    public function __construct(array $reportData, ?array $customColumns = null)
    {
        $this->reportData = $reportData;
        $this->customColumns = $customColumns;
    }

    public function collection(): Collection
    {
        $rows = [];
        
        foreach ($this->reportData['grouped_data'] ?? [] as $group) {
            $rows[] = [
                $group['description'] ?? $group['key'],
                round($group['total_seconds'] / 3600, 2),
                $group['revenue'] / 100,
                $group['cost'] / 100,
                $group['margin'] / 100,
                $group['margin_percent'] !== null ? round($group['margin_percent'], 1) . '%' : 'N/A',
            ];
        }
        
        // Add total row
        $rows[] = [
            'TOTAL',
            round($this->reportData['total_seconds'] / 3600, 2),
            $this->reportData['revenue'] / 100,
            $this->reportData['cost'] / 100,
            $this->reportData['margin'] / 100,
            $this->reportData['margin_percent'] !== null ? round($this->reportData['margin_percent'], 1) . '%' : 'N/A',
        ];
        
        return collect($rows);
    }

    public function headings(): array
    {
        return [
            'Group',
            'Hours',
            'Revenue',
            'Cost',
            'Margin',
            'Margin %',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
            $sheet->getHighestRow() => ['font' => ['bold' => true]], // Total row
        ];
    }
}
```

---

## 7. Frontend Architecture

### 7.1 Pinia Store

**File**: `resources/js/utils/useReporting.ts`

```typescript
import { defineStore } from 'pinia'
import { useQuery, useMutation } from '@tanstack/vue-query'
import { api } from '@/utils/api'
import { getCurrentOrganizationId } from '@/utils/useUser'

export interface ProfitabilityReportParams {
  start: string
  end: string
  group: string
  sub_group?: string
  member_ids?: string[]
  client_ids?: string[]
  project_ids?: string[]
  billable?: boolean
  include_expenses?: boolean
}

export interface UtilizationReportParams {
  start: string
  end: string
  member_ids?: string[]
  project_ids?: string[]
  include_daily_breakdown?: boolean
}

export const useReportingStore = defineStore('reporting', () => {
  /**
   * Fetch profitability report data
   */
  const fetchProfitabilityReport = (params: ProfitabilityReportParams) => {
    return useQuery({
      queryKey: ['profitability-report', getCurrentOrganizationId(), params],
      queryFn: async () => {
        const response = await api.GET('/api/v1/organizations/{organization}/reports/profitability', {
          params: {
            path: { organization: getCurrentOrganizationId() },
            query: params,
          },
        })
        return response.data
      },
      staleTime: 1000 * 60 * 5, // 5 minutes
    })
  }

  /**
   * Fetch utilization report data
   */
  const fetchUtilizationReport = (params: UtilizationReportParams) => {
    return useQuery({
      queryKey: ['utilization-report', getCurrentOrganizationId(), params],
      queryFn: async () => {
        const response = await api.GET('/api/v1/organizations/{organization}/reports/utilization', {
          params: {
            path: { organization: getCurrentOrganizationId() },
            query: params,
          },
        })
        return response.data
      },
      staleTime: 1000 * 60 * 5,
    })
  }

  /**
   * Create report template
   */
  const createReportTemplate = useMutation({
    mutationFn: async (data: { name: string; description?: string; properties: any; custom_columns?: string[] }) => {
      const response = await api.POST('/api/v1/organizations/{organization}/report-templates', {
        params: {
          path: { organization: getCurrentOrganizationId() },
        },
        body: data,
      })
      return response.data
    },
  })

  /**
   * Create report schedule
   */
  const createReportSchedule = useMutation({
    mutationFn: async (data: { reportId: string; schedule: any }) => {
      const response = await api.POST('/api/v1/organizations/{organization}/reports/{report}/schedules', {
        params: {
          path: {
            organization: getCurrentOrganizationId(),
            report: data.reportId,
          },
        },
        body: data.schedule,
      })
      return response.data
    },
  })

  return {
    fetchProfitabilityReport,
    fetchUtilizationReport,
    createReportTemplate,
    createReportSchedule,
  }
})
```

### 7.2 Vue Pages

#### ProfitabilityReport.vue

**File**: `resources/js/Pages/Reporting/ProfitabilityReport.vue`

```vue
<template>
  <AppLayout title="Profitability Report">
    <div class="py-6">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
          <h1 class="text-2xl font-semibold text-gray-900">Profitability Report</h1>
        </div>

        <!-- Filters -->
        <div class="bg-white shadow rounded-lg p-6 mb-6">
          <ReportFilters
            v-model:start="filters.start"
            v-model:end="filters.end"
            v-model:group="filters.group"
            v-model:sub-group="filters.sub_group"
            v-model:member-ids="filters.member_ids"
            v-model:client-ids="filters.client_ids"
            v-model:project-ids="filters.project_ids"
            v-model:billable="filters.billable"
            @submit="loadReport"
          />
        </div>

        <!-- Summary Cards -->
        <div v-if="reportData" class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
          <SummaryCard
            label="Total Hours"
            :value="formatHours(reportData.total_seconds)"
            icon="ClockIcon"
          />
          <SummaryCard
            label="Revenue"
            :value="formatCurrency(reportData.revenue)"
            icon="CurrencyDollarIcon"
            :color="'green'"
          />
          <SummaryCard
            label="Cost"
            :value="formatCurrency(reportData.cost)"
            icon="CurrencyDollarIcon"
            :color="'red'"
          />
          <SummaryCard
            label="Margin"
            :value="formatCurrency(reportData.margin)"
            :subtitle="reportData.margin_percent ? `${reportData.margin_percent.toFixed(1)}%` : 'N/A'"
            icon="ChartBarIcon"
            :color="reportData.margin >= 0 ? 'green' : 'red'"
          />
        </div>

        <!-- Charts -->
        <div v-if="reportData" class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
          <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-medium mb-4">Revenue vs Cost</h3>
            <BarChart :data="chartData" />
          </div>
          <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-medium mb-4">Margin Trend</h3>
            <LineChart :data="marginTrendData" />
          </div>
        </div>

        <!-- Data Table -->
        <div v-if="reportData" class="bg-white shadow rounded-lg overflow-hidden">
          <ProfitabilityTable :data="reportData.grouped_data" :currency="reportData.currency" />
        </div>

        <!-- Loading State -->
        <div v-if="isLoading" class="flex justify-center py-12">
          <LoadingSpinner />
        </div>

        <!-- Error State -->
        <div v-if="error" class="bg-red-50 p-4 rounded-md">
          <p class="text-red-800">{{ error }}</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useReportingStore } from '@/utils/useReporting'
import AppLayout from '@/Layouts/AppLayout.vue'
import ReportFilters from '@/packages/ui/src/Reporting/ReportFilters.vue'
import SummaryCard from '@/packages/ui/src/Reporting/SummaryCard.vue'
import BarChart from '@/packages/ui/src/Reporting/BarChart.vue'
import LineChart from '@/packages/ui/src/Reporting/LineChart.vue'
import ProfitabilityTable from '@/packages/ui/src/Reporting/ProfitabilityTable.vue'
import LoadingSpinner from '@/packages/ui/src/Common/LoadingSpinner.vue'

const store = useReportingStore()

const filters = ref({
  start: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
  end: new Date().toISOString().split('T')[0],
  group: 'project',
  sub_group: null,
  member_ids: [],
  client_ids: [],
  project_ids: [],
  billable: null,
})

const { data: reportData, isLoading, error, refetch } = store.fetchProfitabilityReport(filters.value)

const loadReport = () => {
  refetch()
}

const chartData = computed(() => {
  if (!reportData.value?.grouped_data) return null
  return {
    labels: reportData.value.grouped_data.map((g) => g.description || g.key),
    datasets: [
      {
        label: 'Revenue',
        data: reportData.value.grouped_data.map((g) => g.revenue / 100),
        backgroundColor: 'rgba(34, 197, 94, 0.5)',
      },
      {
        label: 'Cost',
        data: reportData.value.grouped_data.map((g) => g.cost / 100),
        backgroundColor: 'rgba(239, 68, 68, 0.5)',
      },
    ],
  }
})

const formatHours = (seconds: number) => {
  return (seconds / 3600).toFixed(1) + ' hrs'
}

const formatCurrency = (cents: number) => {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: reportData.value?.currency || 'USD' }).format(cents / 100)
}
</script>
```

#### UtilizationReport.vue

**File**: `resources/js/Pages/Reporting/UtilizationReport.vue`

Similar structure to ProfitabilityReport.vue with:
- Utilization filters (date range, members)
- Summary cards (total capacity, total actual, average utilization)
- Horizontal bar chart per member
- Heatmap calendar view (optional)
- Data table with member breakdown

### 7.3 UI Components

#### ProfitabilityTable.vue

**File**: `resources/js/packages/ui/src/Reporting/ProfitabilityTable.vue`

```vue
<template>
  <table class="min-w-full divide-y divide-gray-200">
    <thead class="bg-gray-50">
      <tr>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Group</th>
        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Hours</th>
        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Cost</th>
        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Margin</th>
        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Margin %</th>
      </tr>
    </thead>
    <tbody class="bg-white divide-y divide-gray-200">
      <tr
        v-for="row in data"
        :key="row.key"
        :class="{ 'bg-red-50': row.margin < 0 }"
      >
        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
          <div class="flex items-center">
            <div
              v-if="row.color"
              class="w-3 h-3 rounded-full mr-2"
              :style="{ backgroundColor: row.color }"
            ></div>
            {{ row.description || row.key }}
          </div>
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-500">
          {{ formatHours(row.total_seconds) }}
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-green-600">
          {{ formatCurrency(row.revenue) }}
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-red-600">
          {{ formatCurrency(row.cost) }}
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-sm text-right" :class="row.margin >= 0 ? 'text-green-600' : 'text-red-600'">
          {{ formatCurrency(row.margin) }}
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium" :class="row.margin >= 0 ? 'text-green-600' : 'text-red-600'">
          {{ row.margin_percent !== null ? row.margin_percent.toFixed(1) + '%' : 'N/A' }}
        </td>
      </tr>
    </tbody>
  </table>
</template>

<script setup lang="ts">
import { defineProps } from 'vue'

defineProps<{
  data: any[]
  currency: string
}>()

const formatHours = (seconds: number) => {
  return (seconds / 3600).toFixed(1) + ' hrs'
}

const formatCurrency = (cents: number) => {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: props.currency }).format(cents / 100)
}
</script>
```

#### BarChart.vue

**File**: `resources/js/packages/ui/src/Reporting/BarChart.vue`

Uses Chart.js (already installed in codebase):

```vue
<template>
  <div>
    <canvas ref="chartCanvas"></canvas>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import {
  Chart,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend,
} from 'chart.js'

Chart.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend)

const props = defineProps<{
  data: {
    labels: string[]
    datasets: Array<{
      label: string
      data: number[]
      backgroundColor: string
    }>
  }
}>()

const chartCanvas = ref<HTMLCanvasElement | null>(null)
let chartInstance: Chart | null = null

const renderChart = () => {
  if (!chartCanvas.value) return
  
  if (chartInstance) {
    chartInstance.destroy()
  }
  
  chartInstance = new Chart(chartCanvas.value, {
    type: 'bar',
    data: props.data,
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'top',
        },
      },
      scales: {
        y: {
          beginAtZero: true,
        },
      },
    },
  })
}

onMounted(() => {
  renderChart()
})

watch(() => props.data, () => {
  renderChart()
}, { deep: true })
</script>
```

### 7.4 TypeScript Types

**File**: `resources/js/types/reporting.d.ts`

```typescript
export interface ProfitabilityReportGroup {
  key: string | null
  description: string | null
  color: string | null
  total_seconds: number
  revenue: number
  cost: number
  margin: number
  margin_percent: number | null
  grouped_type: string | null
  grouped_data: ProfitabilityReportGroup[] | null
}

export interface ProfitabilityReportData {
  grouped_type: string | null
  grouped_data: ProfitabilityReportGroup[]
  total_seconds: number
  revenue: number
  cost: number
  margin: number
  margin_percent: number | null
  currency: string
}

export interface UtilizationReportMember {
  member_id: string
  member_name: string
  capacity_seconds: number
  actual_seconds: number
  billable_seconds: number
  utilization_percent: number | null
  billable_utilization_percent: number | null
  daily_breakdown: Array<{
    date: string
    capacity_seconds: number
    actual_seconds: number
  }> | null
}

export interface UtilizationReportData {
  members: UtilizationReportMember[]
  period: {
    start: string
    end: string
    weeks: number
  }
}

export interface ReportTemplate {
  id: string
  name: string
  description: string | null
  properties: any
  custom_columns: string[] | null
  created_at: string
}

export interface ReportSchedule {
  id: string
  report_id: string
  frequency: 'daily' | 'weekly' | 'monthly'
  day_of_week: number | null
  day_of_month: number | null
  time_of_day: string
  timezone: string
  export_format: string
  recipients: string[]
  next_run_at: string | null
  last_run_at: string | null
  status: 'active' | 'paused' | 'failed'
  failure_count: number
}

export interface Expense {
  id: string
  amount: number
  currency: string
  category: 'travel' | 'meals' | 'software' | 'hardware' | 'office' | 'other'
  date: string
  description: string
  project_id: string | null
  member_id: string
  billable: boolean
  receipt_path: string | null
  created_at: string
}
```

---

## 8. Permission Matrix

### 8.1 Permissions Registration

**File**: `app/Permissions/ReportingPermissions.php`

```php
<?php

declare(strict_types=1);

namespace App\Permissions;

use Laravel\Jetstream\Jetstream;

class ReportingPermissions
{
    public static function register(): void
    {
        // Owner role (all permissions)
        Jetstream::role('owner', 'Owner', [
            // ... existing permissions ...
            'reports:view',
            'reports:create',
            'reports:update',
            'reports:delete',
            'expenses:view:own',
            'expenses:view:all',
            'expenses:create',
            'expenses:update:own',
            'expenses:update:all',
            'expenses:delete:own',
            'expenses:delete:all',
        ])->description('Owner has full access to all resources.');

        // Admin role
        Jetstream::role('admin', 'Administrator', [
            // ... existing permissions ...
            'reports:view',
            'reports:create',
            'reports:update',
            'reports:delete',
            'expenses:view:own',
            'expenses:view:all',
            'expenses:create',
            'expenses:update:own',
            'expenses:update:all',
            'expenses:delete:own',
            'expenses:delete:all',
        ])->description('Administrator has full access except organization deletion.');

        // Manager role
        Jetstream::role('manager', 'Manager', [
            // ... existing permissions ...
            'reports:view',
            'reports:create',
            'reports:update',
            'reports:delete',
            'expenses:view:own',
            'expenses:view:all',
            'expenses:create',
            'expenses:update:own',
            'expenses:update:all',
            'expenses:delete:own',
            'expenses:delete:all',
        ])->description('Manager can view reports and manage expenses.');

        // Employee role
        Jetstream::role('employee', 'Employee', [
            // ... existing permissions ...
            // NO reports permissions
            'expenses:view:own',
            'expenses:create',
            'expenses:update:own',
            'expenses:delete:own',
        ])->description('Employee can track time and manage own expenses.');
    }
}
```

**Integration** (`app/Providers/JetstreamServiceProvider.php`):

```php
protected function configurePermissions(): void
{
    // ... existing permission setup ...

    // Feature permissions (modular approach per SF-08)
    \App\Permissions\ReportingPermissions::register();
}
```

### 8.2 Permission Matrix

| Permission | Owner | Admin | Manager | Employee | Description |
|------------|-------|-------|---------|----------|-------------|
| `reports:view` | ✓ | ✓ | ✓ | ✗ | View all report types (profitability, utilization, budget) |
| `reports:create` | ✓ | ✓ | ✓ | ✗ | Create saved reports, templates, and schedules |
| `reports:update` | ✓ | ✓ | ✓ | ✗ | Update saved reports and templates |
| `reports:delete` | ✓ | ✓ | ✓ | ✗ | Delete saved reports and templates |
| `expenses:view:own` | ✓ | ✓ | ✓ | ✓ | View own expenses |
| `expenses:view:all` | ✓ | ✓ | ✓ | ✗ | View all expenses in organization |
| `expenses:create` | ✓ | ✓ | ✓ | ✓ | Create new expenses |
| `expenses:update:own` | ✓ | ✓ | ✓ | ✓ | Update own expenses |
| `expenses:update:all` | ✓ | ✓ | ✓ | ✗ | Update all expenses |
| `expenses:delete:own` | ✓ | ✓ | ✓ | ✓ | Delete own expenses |
| `expenses:delete:all` | ✓ | ✓ | ✓ | ✗ | Delete all expenses |

### 8.3 Data Visibility Rules

**Cost Rate Data**:
- Visible to: Owner, Admin, Manager
- Hidden from: Employee
- Implementation: Controller checks role and nulls out cost/margin fields in response for Employees

**Example** (in `ProfitabilityReportController`):

```php
public function index(Organization $organization, ProfitabilityReportRequest $request)
{
    $this->checkPermission($organization, 'reports:view');
    
    $member = $this->member($organization);
    $canViewCostData = in_array($member->role, ['owner', 'admin', 'manager']);
    
    $reportData = app(ProfitabilityReportService::class)->getProfitabilityReport(/* ... */);
    
    if (!$canViewCostData) {
        // Hide cost and margin data from employees
        $reportData = $this->hideCostData($reportData);
    }
    
    return new ProfitabilityReportResource($reportData);
}

private function hideCostData(array $reportData): array
{
    $reportData['cost'] = null;
    $reportData['margin'] = null;
    $reportData['margin_percent'] = null;
    
    if (isset($reportData['grouped_data'])) {
        foreach ($reportData['grouped_data'] as &$group) {
            $group['cost'] = null;
            $group['margin'] = null;
            $group['margin_percent'] = null;
        }
    }
    
    return $reportData;
}
```

---

## 9. Migration Strategy

### 9.1 Migration Files

All migrations use date prefix `2026_03_09_` per SF-03 and AMD-02.

| Order | File Name | Description | Dependencies |
|-------|-----------|-------------|--------------|
| 1 | `2026_03_09_000001_add_cost_rate_to_members_table.php` | Add `cost_rate` column to members | FOUND-006 (weekly_capacity) |
| 2 | `2026_03_09_000002_add_cost_rate_to_project_members_table.php` | Add `cost_rate` column to project_members | None |
| 3 | `2026_03_09_000003_add_default_cost_rate_to_organizations_table.php` | Add `default_cost_rate` column to organizations | None |
| 4 | `2026_03_09_000004_add_cost_rate_to_time_entries_table.php` | Add `cost_rate` column to time_entries | None |
| 5 | `2026_03_09_000005_add_budget_amount_to_projects_table.php` | Add `budget_amount` column to projects | None |
| 6 | `2026_03_09_000006_create_report_templates_table.php` | Create `report_templates` table | None |
| 7 | `2026_03_09_000007_create_report_schedules_table.php` | Create `report_schedules` table | Existing `reports` table |
| 8 | `2026_03_09_000008_create_expenses_table.php` | Create `expenses` table | None |

### 9.2 Backfill Command

**File**: `app/Console/Commands/BackfillCostRates.php`

**Purpose**: Populate `time_entries.cost_rate` for existing entries (AMD-07: moved to Sprint 2)

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TimeEntry;
use App\Service\CostRateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillCostRates extends Command
{
    protected $signature = 'reporting:backfill-cost-rates {--chunk=1000}';
    protected $description = 'Backfill cost_rate column on existing time entries';

    public function handle(CostRateService $costRateService): int
    {
        $this->info('Starting cost rate backfill...');
        
        $totalEntries = TimeEntry::query()->whereNull('cost_rate')->count();
        $this->info("Found {$totalEntries} time entries to backfill.");
        
        $chunkSize = (int) $this->option('chunk');
        $bar = $this->output->createProgressBar($totalEntries);
        
        TimeEntry::query()
            ->whereNull('cost_rate')
            ->with(['member', 'project.members', 'organization'])
            ->chunk($chunkSize, function ($entries) use ($costRateService, $bar) {
                foreach ($entries as $entry) {
                    $costRate = $costRateService->getCostRateForTimeEntry($entry);
                    if ($costRate !== null) {
                        DB::table('time_entries')
                            ->where('id', $entry->id)
                            ->update(['cost_rate' => $costRate]);
                    }
                    $bar->advance();
                }
            });
        
        $bar->finish();
        $this->newLine();
        $this->info('Backfill complete!');
        
        return Command::SUCCESS;
    }
}
```

**Execution**:

```bash
php artisan reporting:backfill-cost-rates --chunk=500
```

### 9.3 Migration Rollback Strategy

All migrations implement `down()` methods that cleanly remove added columns/tables. Rollback order is reverse of migration order.

**Critical Note**: Backfilled cost rate data will be lost on rollback. Document this in migration comments and deployment notes.

---

## 10. Cross-Feature Integration

### 10.1 Dependency on Shared Foundations

**FOUND-006** (`2026_02_28_000001_add_weekly_capacity_to_members.php`):

This shared migration adds:
- `members.weekly_capacity` (default 144000 = 40h in seconds)
- `organizations.default_weekly_capacity` (default 144000)

**Impact on RPT-001**:
- AMD-04 clarifies: RPT-001 MUST NOT add `weekly_capacity` to members
- RPT-001 revised scope: Add only `cost_rate` to members and time_entries, `default_cost_rate` to organizations
- Dependency declaration: RPT-001 task description notes "Depends on FOUND-006"

**Implementation Check**:

```php
// Migration: 2026_03_09_000001_add_cost_rate_to_members_table.php
public function up(): void
{
    // Verify that weekly_capacity exists (from FOUND-006)
    if (!Schema::hasColumn('members', 'weekly_capacity')) {
        throw new \Exception('Shared foundation migration FOUND-006 must run first.');
    }
    
    Schema::table('members', function (Blueprint $table): void {
        $table->unsignedInteger('cost_rate')->nullable()
            ->comment('Internal cost rate per hour in cents. Overrides organization default.');
        $table->index('cost_rate', 'idx_members_cost_rate');
    });
}
```

### 10.2 Integration with Feature 02 (Expense Management)

**Scenario**: If Feature 02 is deployed first, it will have its own `Expense` model and API. If Feature 09 is deployed first, it will create the `Expense` model.

**Resolution Strategy**:

1. **Feature 02 ships first (most likely)**:
   - Feature 02 creates `expenses` table with AMD-compliant schema
   - Feature 09 migration `2026_03_09_000008_create_expenses_table.php` becomes a no-op or skips if table exists
   - Feature 09 uses the existing Expense model

2. **Feature 09 ships first**:
   - Feature 09 creates `expenses` table
   - Feature 02's migration becomes a no-op
   - Both features share the same Expense model

**Implementation** (in migration):

```php
public function up(): void
{
    if (Schema::hasTable('expenses')) {
        $this->warn('Expenses table already exists (likely from Feature 02). Skipping creation.');
        return;
    }
    
    Schema::create('expenses', function (Blueprint $table): void {
        // ... table definition
    });
}
```

**Expense Reports in Phase B**:

```php
// In ProfitabilityReportService
public function getProfitabilityReport(/* ... */, bool $includeExpenses = false): array
{
    // ... existing logic
    
    if ($includeExpenses && class_exists(\App\Models\Expense::class)) {
        $expenseCost = $this->getExpenseCostForFilters($filters);
        $reportData['cost'] += $expenseCost;
        $reportData['margin'] -= $expenseCost;
    }
    
    return $reportData;
}
```

### 10.3 Integration with Feature 03 (Budgets & Alerts)

**Scenario**: Budget reports depend on `projects.budget_amount` column.

**Resolution**:

1. **Feature 03 ships first**:
   - Feature 03 adds `budget_amount` column
   - Feature 09 migration `2026_03_09_000005_add_budget_amount_to_projects_table.php` becomes a no-op

2. **Feature 09 ships first**:
   - Feature 09 adds `budget_amount` column
   - Feature 03 migration skips column addition

**Graceful Degradation**:

If neither feature is deployed, budget reports return empty data:

```php
// In BudgetReportController
public function index(Organization $organization)
{
    $this->checkPermission($organization, 'reports:view');
    
    if (!Schema::hasColumn('projects', 'budget_amount')) {
        return response()->json([
            'data' => [
                'projects' => [],
                'message' => 'Budget reporting requires Feature 03 (Budgets & Alerts) to be enabled.',
            ],
        ]);
    }
    
    // ... normal report logic
}
```

### 10.4 Integration with Feature 10 (Teams & Groups)

**Scenario**: All report endpoints should accept `team_ids` filter parameter.

**Implementation**:

```php
// In ProfitabilityReportRequest
public function rules(): array
{
    return [
        // ... existing rules
        'team_ids' => ['nullable', 'array'],
        'team_ids.*' => ['uuid', new ExistsEloquent(Team::class)], // Only if Feature 10 exists
    ];
}

// In ProfitabilityReportController
public function index(Organization $organization, ProfitabilityReportRequest $request)
{
    $query = TimeEntry::query()->whereBelongsTo($organization, 'organization');
    
    // Apply team scoping if Feature 10 is enabled
    if ($request->has('team_ids') && class_exists(\App\Models\Team::class)) {
        $query = app(\App\Service\TeamScopeService::class)
            ->applyTeamScope($query, $request->input('team_ids'));
    }
    
    // ... rest of report logic
}
```

**Conditional Type Definitions**:

```typescript
// resources/js/types/reporting.d.ts
export interface ProfitabilityReportParams {
  start: string
  end: string
  // ... other params
  team_ids?: string[]  // Optional, only used if Teams feature is enabled
}
```

---

## 11. File Manifest

### 11.1 Backend Files

#### Migrations (8 files)

```
database/migrations/
├── 2026_03_09_000001_add_cost_rate_to_members_table.php
├── 2026_03_09_000002_add_cost_rate_to_project_members_table.php
├── 2026_03_09_000003_add_default_cost_rate_to_organizations_table.php
├── 2026_03_09_000004_add_cost_rate_to_time_entries_table.php
├── 2026_03_09_000005_add_budget_amount_to_projects_table.php
├── 2026_03_09_000006_create_report_templates_table.php
├── 2026_03_09_000007_create_report_schedules_table.php
└── 2026_03_09_000008_create_expenses_table.php
```

#### Models (3 new + 5 modified)

**New**:
```
app/Models/
├── ReportTemplate.php
├── ReportSchedule.php
└── Expense.php
```

**Modified**:
```
app/Models/
├── Member.php            (add cost_rate, weekly_capacity casts)
├── ProjectMember.php     (add cost_rate cast)
├── Organization.php      (add default_cost_rate cast)
├── TimeEntry.php         (add cost_rate to $computed array, add getCostRateComputed())
└── Project.php           (add budget_amount cast)
```

#### Enums (3 files)

```
app/Enums/
├── ExpenseCategory.php
├── ReportScheduleFrequency.php
└── ReportScheduleStatus.php
```

#### Services (5 files)

```
app/Service/
├── CostRateService.php
├── ProfitabilityReportService.php
├── UtilizationReportService.php
├── ReportScheduleService.php
└── ReportExportService.php

app/Service/Export/
├── ProfitabilityReportExport.php
├── UtilizationReportExport.php
└── DetailedReportExport.php
```

#### Controllers (6 files)

```
app/Http/Controllers/Api/V1/
├── ProfitabilityReportController.php
├── UtilizationReportController.php
├── BudgetReportController.php
├── ReportTemplateController.php
├── ReportScheduleController.php
└── ExpenseController.php
```

#### Requests (13 files)

```
app/Http/Requests/V1/Report/
├── ProfitabilityReportRequest.php
├── UtilizationReportRequest.php
└── BudgetReportRequest.php

app/Http/Requests/V1/ReportTemplate/
├── ReportTemplateStoreRequest.php
└── ReportTemplateUpdateRequest.php

app/Http/Requests/V1/ReportSchedule/
├── ReportScheduleStoreRequest.php
└── ReportScheduleUpdateRequest.php

app/Http/Requests/V1/Expense/
├── ExpenseStoreRequest.php
└── ExpenseUpdateRequest.php
```

#### Resources (12 files)

```
app/Http/Resources/V1/Report/
├── ProfitabilityReportResource.php
├── UtilizationReportResource.php
└── BudgetReportResource.php

app/Http/Resources/V1/ReportTemplate/
├── ReportTemplateResource.php
└── ReportTemplateCollection.php

app/Http/Resources/V1/ReportSchedule/
├── ReportScheduleResource.php
└── ReportScheduleCollection.php

app/Http/Resources/V1/Expense/
├── ExpenseResource.php
└── ExpenseCollection.php
```

#### Commands & Mail (3 files)

```
app/Console/Commands/
├── SendScheduledReportsCommand.php
└── BackfillCostRates.php

app/Mail/
└── ScheduledReportMail.php
```

#### Permissions (1 file)

```
app/Permissions/
└── ReportingPermissions.php
```

#### Factories (3 files)

```
database/factories/
├── ReportTemplateFactory.php
├── ReportScheduleFactory.php
└── ExpenseFactory.php
```

#### Routes (1 modified file)

```
routes/
└── api.php  (add new route groups for all controllers)
```

### 11.2 Frontend Files

#### Pages (5 files)

```
resources/js/Pages/Reporting/
├── ProfitabilityReport.vue
├── UtilizationReport.vue
├── BudgetReport.vue
├── ReportTemplates.vue
└── Expenses.vue
```

#### UI Components (15 files)

```
resources/js/packages/ui/src/Reporting/
├── ReportFilters.vue
├── SummaryCard.vue
├── ProfitabilityTable.vue
├── UtilizationTable.vue
├── BudgetTable.vue
├── BarChart.vue
├── LineChart.vue
├── HeatmapCalendar.vue
├── ReportTemplateModal.vue
├── ReportScheduleModal.vue
├── ExpenseForm.vue
├── ExpenseTable.vue
├── ReceiptUpload.vue
└── ExportModal.vue
```

#### Pinia Stores (1 file)

```
resources/js/utils/
└── useReporting.ts
```

#### TypeScript Types (1 file)

```
resources/js/types/
└── reporting.d.ts
```

### 11.3 Test Files

#### Unit Tests (8 files)

```
tests/Unit/Service/
├── CostRateServiceTest.php
├── ProfitabilityReportServiceTest.php
├── UtilizationReportServiceTest.php
├── ReportScheduleServiceTest.php
└── ReportExportServiceTest.php

tests/Unit/Console/
└── SendScheduledReportsCommandTest.php
```

#### Endpoint Tests (6 files)

```
tests/Unit/Endpoint/Api/V1/
├── ProfitabilityReportEndpointTest.php
├── UtilizationReportEndpointTest.php
├── BudgetReportEndpointTest.php
├── ReportTemplateEndpointTest.php
├── ReportScheduleEndpointTest.php
└── ExpenseEndpointTest.php
```

#### Component Tests (8 files)

```
resources/js/packages/ui/src/Reporting/__tests__/
├── ProfitabilityTable.test.ts
├── UtilizationTable.test.ts
├── BudgetTable.test.ts
├── BarChart.test.ts
├── ReportFilters.test.ts
├── ExpenseForm.test.ts
└── SummaryCard.test.ts
```

#### E2E Tests (5 files)

```
e2e/
├── profitability-report.spec.ts
├── utilization-report.spec.ts
├── budget-report.spec.ts
├── report-templates.spec.ts
└── expenses.spec.ts
```

### 11.4 Documentation Files (1 file)

```
docs/
└── reporting-feature-guide.md
```

**Total File Count**: ~115 files (70 backend, 27 frontend, 18 tests)

---

## 12. Phase A/B Split

### 12.1 Phase A: Core Analytics (Sprints 1-3, ~140h)

**Goal**: Deliver profitability and utilization reporting with cost rate management.

**Tasks**: RPT-001 through RPT-025 (modified Sprint 3 ending point)

**Deliverables**:

1. **Cost Rate Infrastructure**:
   - Migrations for cost rates on members, project_members, organizations, time_entries
   - CostRateService with hierarchy logic
   - Backfill command (moved to Sprint 2 per AMD-07)

2. **Profitability Reporting**:
   - ProfitabilityReportService
   - API endpoint with grouping support
   - Frontend page with charts and tables
   - Permission-based data hiding

3. **Utilization Reporting**:
   - UtilizationReportService
   - API endpoint with daily breakdown
   - Frontend page with capacity visualization

4. **Frontend UI**:
   - Reporting navigation updates
   - Profitability report page
   - Utilization report page
   - Chart components (Chart.js)

**Success Criteria**:

- ✓ Managers can view profit margins per project/client
- ✓ Cost rates cascade correctly through hierarchy
- ✓ Utilization percentages calculated accurately
- ✓ Employees cannot see cost/margin data
- ✓ Reports render in <500ms for organizations with 100K entries

**Independent Value**: Phase A delivers complete margin analysis and capacity planning capabilities without needing Phase B features.

### 12.2 Phase B: Extended Reporting (Sprints 4-6, ~170h)

**Goal**: Add scheduling, templates, expenses, and budget tracking.

**Tasks**: RPT-026 through RPT-039

**Deliverables**:

1. **Report Schedules**:
   - ReportSchedule model and API
   - ReportScheduleService with next-run calculation
   - SendScheduledReportsCommand (hourly cron)
   - ScheduledReportMail mailable
   - Frontend schedule management UI

2. **Report Templates**:
   - ReportTemplate model and API
   - Template CRUD UI
   - Template application in report builder

3. **Expense Management**:
   - Expense model and API
   - Receipt upload/download
   - Expense CRUD UI
   - Integration into profitability reports

4. **Budget Reports**:
   - Budget vs actual calculations
   - BudgetReportService
   - API endpoint
   - Frontend budget page

5. **Enhanced Export**:
   - ReportExportService
   - Custom column selection
   - Styled PDF exports
   - Grouped export with subtotals

**Success Criteria**:

- ✓ Reports can be scheduled for daily/weekly/monthly delivery
- ✓ Templates save and restore report configurations
- ✓ Expenses tracked with receipt uploads
- ✓ Budget reports show time and money spent vs. budget
- ✓ Exports include custom columns and grouping

**Dependency on Phase A**: Phase B requires all Phase A services to be functional.

---

## 13. Implementation Sequence

### 13.1 Sprint 1 (Weeks 1-2): Database Foundation

**Focus**: Migrations, models, enums, basic services

**Tasks**:
- RPT-001 to RPT-013 (all parallel setup tasks)

**Sequence**:

1. **Day 1-2**: Run all 8 migrations in order
   - Verify FOUND-006 ran successfully
   - Run `php artisan migrate` for RPT migrations
   - Verify rollbacks work

2. **Day 3-4**: Update existing models
   - Add cost_rate and weekly_capacity to Member, ProjectMember, Organization, TimeEntry, Project
   - Add $casts and PHPDoc annotations
   - Run `composer analyse` to verify

3. **Day 5-7**: Create new models
   - ReportTemplate, ReportSchedule, Expense
   - Add relationships
   - Create factories
   - Verify factory generation works

4. **Day 8-9**: Create enums
   - ExpenseCategory, ReportScheduleFrequency, ReportScheduleStatus
   - Use LaravelEnumHelper trait

5. **Day 10**: Create CostRateService
   - Implement all methods following BillableRateService pattern
   - Write unit tests (10+ scenarios)

**Deliverable**: Database schema complete, models updated, CostRateService functional

**Critical Path**: FOUND-006 → RPT-001 → RPT-009 → RPT-011

### 13.2 Sprint 2 (Weeks 3-4): Core Services

**Focus**: Business logic for profitability, utilization, scheduling

**Tasks**:
- RPT-014 to RPT-018 + RPT-035 (backfill command moved here per AMD-07)

**Sequence**:

1. **Days 1-3**: ProfitabilityReportService
   - Raw SQL aggregation logic
   - Grouping and margin calculations
   - Unit tests with 10+ scenarios
   - Performance testing (should complete <500ms for 100K entries)

2. **Days 4-6**: UtilizationReportService
   - Capacity calculation with pro-rating
   - Daily breakdown logic
   - Unit tests covering full week, partial week, overtime, zero capacity
   - Performance testing (<300ms for 500 members)

3. **Days 7-9**: ReportScheduleService
   - Next-run calculation for daily/weekly/monthly
   - Timezone handling
   - Unit tests for all frequency types and edge cases (month-end fallback)

4. **Day 10-11**: SendScheduledReportsCommand
   - Hourly command with withoutOverlapping lock
   - Email generation and sending
   - Failure handling (3 retries, pause after)
   - Unit tests

5. **Day 12-14**: ReportExportService
   - Custom column selection
   - Grouped export with subtotals
   - Styled PDF generation
   - Export class implementations (ProfitabilityReportExport, UtilizationReportExport)

6. **Day 14**: Backfill command (RPT-035 moved here)
   - Implement BackfillCostRates command
   - Test on staging database with 10K+ entries
   - Document usage

**Deliverable**: All core services functional, tested, and performant

**Critical Path**: RPT-014 (profitability service) blocks RPT-017 (scheduled command) and RPT-018 (export service)

### 13.3 Sprint 3 (Weeks 5-6): API Layer

**Focus**: REST API endpoints for all report types

**Tasks**:
- RPT-019 to RPT-025

**Sequence**:

1. **Days 1-2**: ProfitabilityReportController
   - Endpoint implementation
   - Request validation
   - Resource transformation
   - Permission checks (hide cost data from employees)
   - Rate limiting (10 req/min per org)
   - Endpoint tests (5+ scenarios)

2. **Days 3-4**: UtilizationReportController
   - Endpoint implementation
   - Daily breakdown optional inclusion
   - Endpoint tests

3. **Days 5-6**: ReportTemplateController
   - Full CRUD implementation
   - Organization scoping
   - Pagination on index
   - Endpoint tests

4. **Days 7-8**: ReportScheduleController
   - Nested routes under reports
   - Max 5 schedules per report validation
   - Max 10 recipients validation
   - Endpoint tests

5. **Days 9-11**: ExpenseController
   - Full CRUD with receipt upload
   - Private storage for receipts
   - Signed URL for receipt download
   - Permission-based access (own vs. all)
   - Endpoint tests

6. **Day 12**: BudgetReportController
   - Endpoint implementation
   - Graceful degradation if Feature 03 not deployed
   - Endpoint tests

7. **Day 13-14**: Update Organization/Member/Project API endpoints
   - Add cost_rate fields to responses
   - Update request validation to accept new fields
   - Update OpenAPI spec

**Deliverable**: Complete API surface for Phase A features

**Critical Path**: RPT-019/020 must complete before frontend work (RPT-026)

### 13.4 Sprint 4 (Weeks 7-8): Frontend Report Pages

**Focus**: Vue pages for profitability, utilization, budget reports

**Tasks**:
-