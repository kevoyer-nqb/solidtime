# Architecture Blueprint: Budgets & Alerts Feature

**Feature ID**: 03  
**Task Prefix**: `BUD-`  
**Date**: 2026-02-06  
**Status**: Implementation Ready  
**PRD Reference**: `/home/keven/Documents/solidtime-analysis/.features/03-budgets-alerts/PRD.md`

---

## 1. Patterns & Conventions Found

### 1.1 Existing Budget Infrastructure
**File**: `app/Models/Project.php` (lines 32, 64)
- `estimated_time` column exists: `int|null`, seconds-based, gated behind premium features
- `spent_time` computed attribute: uses `ComputedAttributes` trait with DB aggregation pattern
- Pattern: `extract(epoch from ("end" - start))` for duration calculation in seconds

**Coexistence Strategy (AMD-07)**:
- When both `estimated_time` and budget exist, **budget takes precedence** in UI
- `EstimatedTimeProgress` component will check for budget first, fall back to `estimated_time`
- Migration path (future technical debt): migrate `estimated_time` values to budget format

### 1.2 Time Aggregation Pattern
**File**: `app/Service/TimeEntryAggregationService.php` (lines 80-82)
```php
// Hours aggregation:
'round(sum(extract(epoch from ('.$endRawSelect.' - '.$startRawSelect.')))) as aggregate'

// Cost aggregation (line 81):
'round(sum(extract(epoch from (...)) * (coalesce(billable_rate, 0)::float/60/60))) as cost'
```
- Uses `coalesce("end", now())` pattern for running entries (line 154, 223)
- Supports timezone-aware date scoping via `TimezoneService`
- N+1 optimization: batch queries via `whereIn()` + `keyBy()` pattern (lines 299-305)

### 1.3 Billable Rate Cascade
**File**: `app/Service/BillableRateService.php`
- Hierarchy: ProjectMember > Project > Member > Organization (lines 86-97)
- Update pattern: recalculate time entry rates when source changes (lines 16-79)
- **Budget service will mirror this pattern**: cost calculation walks same cascade

### 1.4 Dashboard Service Pattern
**File**: `app/Service/DashboardService.php` (lines 285-338)
- `weeklyProjectOverview()`: aggregates time by project, batch-loads project details
- Uses `select(['id', 'name', 'color'])` for minimal payload
- Pattern: aggregate first, then `whereIn()` + `keyBy()` for details (lines 300-305)
- **Budget dashboard widget will follow this exact pattern** (AMD-09)

### 1.5 Controller Base Pattern
**File**: `app/Http/Controllers/Api/V1/Controller.php`
- Injects `PermissionStore` (line 15)
- Helper methods: `checkPermission()`, `hasPermission()`, `canAccessPremiumFeatures()` (lines 21-51)
- All V1 controllers extend this base

**File**: `app/Http/Controllers/Api/V1/ProjectController.php` (lines 27-33, 96-112)
- Override `checkPermission()` to add project-org validation
- Premium feature gating: `if ($this->canAccessPremiumFeatures($organization))` (line 106)
- Budget fields will use same gating pattern

### 1.6 Request Validation Pattern
**File**: `app/Http/Requests/V1/Project/ProjectStoreRequest.php` (lines 30-79)
- Extends `BaseFormRequest`
- Uses `ExistsEloquent` from `korridor/laravel-model-validation-rules` for FK validation (line 68)
- Organization accessible via `$this->organization` (line 44)
- Custom accessor methods: `getBillableRate()`, `getEstimatedTime()` (lines 58, 74)
- Money rules: uses `moneyRules()` helper for billable_rate validation (line 62)

### 1.7 API Route Pattern
**File**: `routes/api.php` (lines 39-93)
```php
Route::name('v1.')->prefix('v1')->group(...);
Route::name('projects.')->prefix('/organizations/{organization}')->group(...);
Route::middleware('check-organization-blocked') // on write endpoints
```
- Budget routes will follow: `Route::name('budgets.')->prefix('/organizations/{organization}')->group(...)`

### 1.8 Frontend Component Pattern
**File**: `resources/js/packages/ui/src/EstimatedTimeProgress.vue`
- Props: `estimated: number`, `current: number` (seconds)
- Computed: `progressBarPercentage`, `isOverEstimate`
- Color coding: red for over-estimate (line 24)
- Format helper: `formatHours()` converts seconds to hours (lines 5-7)
- **Budget progress component will extend this pattern** with more granular color bands

### 1.9 Migration Conventions
- Date prefix: `2026_03_03_` (AMD-03) for this feature
- Use `Schema::table()` for column additions
- PostgreSQL CHECK constraints via `DB::statement()` (Project controller pattern)
- Cascade deletes: `->cascadeOnDelete()` for FK relationships

### 1.10 Permission Naming (SF-02, AMD-02)
- Pattern: `{entity}:{action}` or `{entity}:{action}:{scope}`
- Budget permissions (corrected):
  - `budgets:view`
  - `budgets:update`
  - `budget-alerts:manage` (not `budgets:alerts:manage`)

---

## 2. Architecture Decision

### 2.1 Core Strategy

**Budget as Project Extension**  
Budget columns live directly on `projects` table, not a separate entity. This mirrors the existing `estimated_time` pattern and avoids join overhead for project queries.

**Alerts as Separate Entity**  
`BudgetAlert` is a dedicated model because:
- Multiple thresholds per project
- Independent lifecycle (triggered_at, reset_at timestamps)
- Per-threshold notification preferences

**Synchronous Threshold Check, Async Notification** (AMD-08 clarification)
- **Synchronous** (< 50ms): Alert evaluation with `lockForUpdate()` on `budget_alerts` table
- **Asynchronous** (queued): Notification dispatch via Laravel queue
- Contention is low: alerts are per-project, concurrent time entry writes to same project are rare
- The `lockForUpdate()` acquires row-level PostgreSQL lock on the specific alert record

**Batch Consumption Calculation** (AMD-09)
- `BudgetService::getConsumptionBatch(Collection $projects)` for dashboard widget
- Single aggregation query across all budgeted projects
- Avoids N+1 problem identified in PRD review

**Monthly Reset via Scheduled Command**
- `BudgetAlertResetMonthlyCommand` runs at 00:01 on 1st of each month (org timezone)
- Resets `triggered_at` for all alerts on monthly-period budgets
- Idempotent: safe to run multiple times

### 2.2 Budget Type Handling

| Type | Amount Unit | Consumption Query | Use Case |
|------|------------|------------------|----------|
| `hours` | seconds | `SUM(EXTRACT(epoch FROM (end - start)))` | Time-based projects |
| `cost` | cents | `SUM(EXTRACT(epoch FROM (...)) * billable_rate / 3600)` | Hourly billing |
| `fixed_fee` | cents | Same as cost | Fixed-price contracts |

**Currency Handling**:
- `budget_currency_code` column: nullable, defaults to `organization.currency`
- For multi-currency orgs, per-project override
- Cost display uses `Intl.NumberFormat` in frontend

### 2.3 Alert Trigger Logic

```
Current percentage crosses threshold upward:
  IF current_percentage >= threshold_percentage AND !isTriggered():
    - Set triggered_at = NOW
    - Dispatch notification (queued)

Current percentage crosses threshold downward:
  IF current_percentage < threshold_percentage AND isTriggered():
    - Set triggered_at = NULL
    - Set reset_at = NOW
    - No notification sent
```

**Idempotency**: Alert evaluation can run multiple times safely. `triggered_at` prevents duplicate notifications.

### 2.4 Trade-offs

| Decision | Rationale | Trade-off |
|----------|----------|-----------|
| Budget on projects table | Simpler queries, mirrors `estimated_time` | Schema change on core table |
| Synchronous alert check | Real-time alerting, < 50ms overhead | Adds latency to time entry writes |
| Row-level lock on alerts | Prevents duplicate notifications | Potential lock contention (mitigated by per-project scoping) |
| Monthly reset command | Automates reset, no manual intervention | Requires cron/scheduler setup |
| Batch consumption query | Solves N+1 for dashboard | More complex query logic |

---

## 3. Data Model

### 3.1 Extended Project Model

**Schema Changes (Migration `2026_03_03_000001`)**:
```sql
ALTER TABLE projects
    ADD COLUMN budget_type VARCHAR(20) NULL,
    ADD COLUMN budget_amount BIGINT NULL,
    ADD COLUMN budget_currency_code VARCHAR(3) NULL,
    ADD COLUMN budget_period VARCHAR(10) NULL DEFAULT 'total';

-- Constraints:
ALTER TABLE projects
    ADD CONSTRAINT chk_budget_type 
    CHECK (budget_type IS NULL OR budget_type IN ('hours', 'cost', 'fixed_fee'));

ALTER TABLE projects
    ADD CONSTRAINT chk_budget_period 
    CHECK (budget_period IS NULL OR budget_period IN ('total', 'monthly'));

ALTER TABLE projects
    ADD CONSTRAINT chk_budget_amount 
    CHECK (budget_type IS NULL OR (budget_amount IS NOT NULL AND budget_amount > 0));
```

**Model Extensions (app/Models/Project.php)**:
```php
// Add to docblock:
* @property string|null $budget_type
* @property int|null $budget_amount
* @property string|null $budget_currency_code
* @property string|null $budget_period

// Add to $casts:
'budget_type' => BudgetType::class,
'budget_amount' => 'integer',
'budget_period' => BudgetPeriod::class,

// New methods:
public function hasBudget(): bool
{
    return $this->budget_type !== null && $this->budget_amount !== null;
}

public function budgetAlerts(): HasMany
{
    return $this->hasMany(BudgetAlert::class, 'project_id');
}
```

### 3.2 BudgetAlert Model

**Schema (Migration `2026_03_03_000002`)**:
```sql
CREATE TABLE budget_alerts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    project_id UUID NOT NULL,
    organization_id UUID NOT NULL,  -- AMD-06: Direct FK for query efficiency
    threshold_percentage INTEGER NOT NULL CHECK (threshold_percentage > 0 AND threshold_percentage <= 200),
    notify_email BOOLEAN NOT NULL DEFAULT TRUE,
    triggered_at TIMESTAMP NULL,
    reset_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_budget_alerts_project
        FOREIGN KEY (project_id)
        REFERENCES projects(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_budget_alerts_organization
        FOREIGN KEY (organization_id)
        REFERENCES organizations(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT uq_budget_alerts_project_threshold
        UNIQUE (project_id, threshold_percentage)
);

CREATE INDEX idx_budget_alerts_project_id ON budget_alerts(project_id);
CREATE INDEX idx_budget_alerts_organization_id ON budget_alerts(organization_id);
CREATE INDEX idx_budget_alerts_monthly_reset ON budget_alerts(organization_id, triggered_at) 
    WHERE triggered_at IS NOT NULL;  -- For monthly reset command
```

**Model (app/Models/BudgetAlert.php)**:
```php
class BudgetAlert extends Model implements AuditableContract
{
    use CustomAuditable;
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'project_id',
        'organization_id',
        'threshold_percentage',
        'notify_email',
    ];

    protected $casts = [
        'threshold_percentage' => 'integer',
        'notify_email' => 'boolean',
        'triggered_at' => 'datetime',
        'reset_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isTriggered(): bool
    {
        return $this->triggered_at !== null;
    }

    public function trigger(): void
    {
        $this->triggered_at = Carbon::now();
        $this->reset_at = null;
        $this->save();
    }

    public function reset(): void
    {
        $this->triggered_at = null;
        $this->reset_at = Carbon::now();
        $this->save();
    }
}
```

### 3.3 Budget Enums

**app/Enums/BudgetType.php**:
```php
<?php
declare(strict_types=1);

namespace App\Enums;

enum BudgetType: string
{
    case Hours = 'hours';
    case Cost = 'cost';
    case FixedFee = 'fixed_fee';
}
```

**app/Enums/BudgetPeriod.php**:
```php
<?php
declare(strict_types=1);

namespace App\Enums;

enum BudgetPeriod: string
{
    case Total = 'total';
    case Monthly = 'monthly';
}
```

---

## 4. API Contract

### 4.1 Extended Project Endpoints

**PUT /api/v1/organizations/{org}/projects/{project}** (existing, extended)

Request body additions:
```json
{
  "budget_type": "hours|cost|fixed_fee|null",
  "budget_amount": 360000,  // seconds (hours) or cents (cost/fixed_fee)
  "budget_currency_code": "USD",  // nullable, defaults to org currency
  "budget_period": "total|monthly"  // default: total
}
```

Response: ProjectResource with embedded budget object (see 4.2).

### 4.2 Budget Status Endpoint

**GET /api/v1/organizations/{org}/projects/{project}/budget**

Response 200:
```json
{
  "data": {
    "type": "hours",
    "amount": 360000,
    "currency_code": "USD",
    "period": "total",
    "consumed": 180000,
    "consumed_including_running": 183600,
    "percentage": 50.0,
    "remaining": 180000,
    "is_over_budget": false,
    "forecast": {
      "daily_burn_rate": 14400,
      "estimated_exhaustion_date": "2026-03-15",
      "days_remaining": 37
    },
    "alerts": [
      {
        "id": "uuid",
        "threshold_percentage": 50,
        "notify_email": true,
        "triggered_at": "2026-02-06T14:30:00Z",
        "reset_at": null
      }
    ]
  }
}
```

Response 404 (no budget set):
```json
{
  "error": {
    "message": "No budget configured for this project"
  }
}
```

### 4.3 Budget Alert Management

**PUT /api/v1/organizations/{org}/projects/{project}/budget/alerts**

Request body:
```json
{
  "alerts": [
    {
      "threshold_percentage": 50,
      "notify_email": true
    },
    {
      "threshold_percentage": 80,
      "notify_email": true
    },
    {
      "threshold_percentage": 100,
      "notify_email": false
    }
  ]
}
```

Response 200: Array of BudgetAlertResource

Behavior:
- Replaces all existing alerts with provided list (upsert pattern)
- Deletes alerts not in request
- Preserves `triggered_at` if threshold percentage unchanged

### 4.4 Budget Overview Dashboard

**GET /api/v1/organizations/{org}/charts/budget-overview**

Response 200:
```json
{
  "data": [
    {
      "project_id": "uuid",
      "project_name": "Project Alpha",
      "project_color": "#3b82f6",
      "budget_type": "hours",
      "percentage": 87.5,
      "consumed": 315000,
      "budget_amount": 360000,
      "is_over_budget": false
    }
  ],
  "meta": {
    "total_projects_with_budget": 15,
    "projects_over_budget": 2
  }
}
```

Query behavior:
- Returns top 5 projects by consumption percentage (desc)
- Only projects with budgets
- Excludes archived projects
- Batch consumption calculation (single query)

### 4.5 Budget Report

**GET /api/v1/organizations/{org}/budgets/report**

Query params:
- `group_by`: `project|client` (default: project)
- `filter_client_id`: UUID (optional)
- `filter_project_id`: UUID (optional)
- `filter_budget_type`: `hours|cost|fixed_fee` (optional)
- `filter_over_budget`: boolean (default: false)
- `start`: YYYY-MM-DD (optional)
- `end`: YYYY-MM-DD (optional)

Response 200:
```json
{
  "data": [
    {
      "project_id": "uuid",
      "project_name": "Project Alpha",
      "project_color": "#3b82f6",
      "client_id": "uuid",
      "client_name": "Acme Corp",
      "budget_type": "hours",
      "budget_amount": 360000,
      "consumed": 315000,
      "remaining": 45000,
      "percentage": 87.5,
      "is_over_budget": false,
      "forecast_exhaustion_date": "2026-03-15"
    }
  ],
  "meta": {
    "total_budget": 1800000,
    "total_consumed": 1350000,
    "total_remaining": 450000,
    "projects_over_budget": 2
  }
}
```

---

## 5. Service Layer

### 5.1 BudgetService

**File**: `app/Service/BudgetService.php`

**Responsibilities**:
- Calculate budget consumption (hours or cost)
- Handle monthly period scoping
- Include running entries in separate calculation
- Create default alerts (50%, 80%, 100%)
- Batch consumption calculation for dashboard (AMD-09)

**Key Methods**:

```php
class BudgetService
{
    public function __construct(
        private TimezoneService $timezoneService,
    ) {}

    /**
     * @return array{
     *     consumed: int,
     *     consumed_including_running: int,
     *     percentage: float,
     *     remaining: int,
     *     is_over_budget: bool
     * }|null
     */
    public function getConsumption(Project $project): ?array
    {
        if (!$project->hasBudget()) {
            return null;
        }

        $query = $this->buildConsumptionQuery($project);
        $result = $query->first();

        $consumed = (int) ($result->consumed ?? 0);
        $consumedIncludingRunning = (int) ($result->consumed_including_running ?? 0);
        $percentage = $project->budget_amount > 0 
            ? ($consumed / $project->budget_amount) * 100 
            : 0;
        $remaining = max(0, $project->budget_amount - $consumed);

        return [
            'consumed' => $consumed,
            'consumed_including_running' => $consumedIncludingRunning,
            'percentage' => round($percentage, 2),
            'remaining' => $remaining,
            'is_over_budget' => $consumed > $project->budget_amount,
        ];
    }

    /**
     * Batch calculate consumption for multiple projects (AMD-09).
     * Returns map of project_id => consumption array.
     * 
     * @param Collection<int, Project> $projects
     * @return array<string, array{consumed: int, percentage: float}>
     */
    public function getConsumptionBatch(Collection $projects): array
    {
        // Filter to projects with budgets
        $budgetedProjects = $projects->filter(fn($p) => $p->hasBudget());
        
        if ($budgetedProjects->isEmpty()) {
            return [];
        }

        // Group by budget type for efficient querying
        $hoursBudgets = $budgetedProjects->where('budget_type', BudgetType::Hours);
        $costBudgets = $budgetedProjects->whereIn('budget_type', [
            BudgetType::Cost, 
            BudgetType::FixedFee
        ]);

        $results = [];

        // Hours query (single aggregation across all hours-budgeted projects)
        if ($hoursBudgets->isNotEmpty()) {
            $hoursData = TimeEntry::query()
                ->selectRaw('project_id, round(sum(extract(epoch from ("end" - start)))) as consumed')
                ->whereIn('project_id', $hoursBudgets->pluck('id'))
                ->whereNotNull('end')
                ->groupBy('project_id')
                ->get()
                ->keyBy('project_id');

            foreach ($hoursBudgets as $project) {
                $consumed = (int) ($hoursData->get($project->id)?->consumed ?? 0);
                $results[$project->id] = [
                    'consumed' => $consumed,
                    'percentage' => round(($consumed / $project->budget_amount) * 100, 2),
                ];
            }
        }

        // Cost query (single aggregation across all cost-budgeted projects)
        if ($costBudgets->isNotEmpty()) {
            $costData = TimeEntry::query()
                ->selectRaw('project_id, round(sum(extract(epoch from ("end" - start)) * (coalesce(billable_rate, 0)::float/60/60))) as consumed')
                ->whereIn('project_id', $costBudgets->pluck('id'))
                ->whereNotNull('end')
                ->groupBy('project_id')
                ->get()
                ->keyBy('project_id');

            foreach ($costBudgets as $project) {
                $consumed = (int) ($costData->get($project->id)?->consumed ?? 0);
                $results[$project->id] = [
                    'consumed' => $consumed,
                    'percentage' => round(($consumed / $project->budget_amount) * 100, 2),
                ];
            }
        }

        return $results;
    }

    public function createDefaultAlerts(Project $project): void
    {
        $defaultThresholds = [50, 80, 100];

        foreach ($defaultThresholds as $threshold) {
            BudgetAlert::firstOrCreate([
                'project_id' => $project->id,
                'threshold_percentage' => $threshold,
            ], [
                'organization_id' => $project->organization_id,
                'notify_email' => $threshold >= 80,  // Email for 80% and 100%
            ]);
        }
    }

    private function buildConsumptionQuery(Project $project): Builder
    {
        $query = TimeEntry::query()
            ->where('project_id', $project->id);

        // Monthly period scoping
        if ($project->budget_period === BudgetPeriod::Monthly) {
            $timezone = $this->timezoneService->getTimezoneFromOrganization($project->organization);
            $firstDayOfMonth = Carbon::now($timezone)->startOfMonth()->utc();
            $firstDayOfNextMonth = Carbon::now($timezone)->addMonth()->startOfMonth()->utc();
            $query->whereBetween('start', [$firstDayOfMonth, $firstDayOfNextMonth]);
        }

        // Budget type determines aggregation
        if ($project->budget_type === BudgetType::Hours) {
            $query->selectRaw('
                round(sum(extract(epoch from ("end" - start)))) as consumed,
                round(sum(extract(epoch from (coalesce("end", now()) - start)))) as consumed_including_running
            ');
        } else {
            // Cost or FixedFee (same calculation)
            $query->selectRaw('
                round(sum(extract(epoch from ("end" - start)) * (coalesce(billable_rate, 0)::float/60/60))) as consumed,
                round(sum(extract(epoch from (coalesce("end", now()) - start)) * (coalesce(billable_rate, 0)::float/60/60))) as consumed_including_running
            ');
        }

        return $query;
    }
}
```

**Testing**:
- `tests/Unit/Service/BudgetServiceTest.php`
- Test scenarios: hours/cost/fixed_fee, monthly/total periods, running entries, edge cases

### 5.2 BudgetAlertService

**File**: `app/Service/BudgetAlertService.php`

**Responsibilities**:
- Evaluate alerts after time entry changes (synchronous, < 50ms)
- Trigger/reset alerts based on consumption percentage
- Dispatch notification jobs (asynchronous)
- Reset monthly alerts (scheduled command)

**Key Methods**:

```php
class BudgetAlertService
{
    public function __construct(
        private BudgetService $budgetService,
    ) {}

    /**
     * Evaluate all alerts for a project after time entry change.
     * Called from TimeEntryObserver or TimeEntryService.
     * 
     * Synchronous execution with lockForUpdate() (AMD-08).
     */
    public function evaluateAlerts(Project $project): void
    {
        if (!$project->hasBudget()) {
            return;
        }

        if ($project->is_archived) {
            return;
        }

        $consumption = $this->budgetService->getConsumption($project);
        if ($consumption === null) {
            return;
        }

        $currentPercentage = $consumption['percentage'];

        // Lock alerts to prevent concurrent notification dispatch
        $alerts = $project->budgetAlerts()
            ->lockForUpdate()
            ->get();

        foreach ($alerts as $alert) {
            if ($currentPercentage >= $alert->threshold_percentage && !$alert->isTriggered()) {
                // Threshold crossed upward
                $alert->trigger();
                dispatch(new SendBudgetAlertNotification($project, $alert, $consumption));
            } elseif ($currentPercentage < $alert->threshold_percentage && $alert->isTriggered()) {
                // Threshold crossed downward (editing entries)
                $alert->reset();
            }
        }
    }

    /**
     * Reset alerts for all monthly-period budgets.
     * Called by scheduled command on 1st of each month.
     */
    public function resetMonthlyAlerts(): void
    {
        $monthlyProjects = Project::query()
            ->where('budget_period', BudgetPeriod::Monthly->value)
            ->whereNotNull('budget_type')
            ->get();

        foreach ($monthlyProjects as $project) {
            $project->budgetAlerts()
                ->whereNotNull('triggered_at')
                ->update([
                    'triggered_at' => null,
                    'reset_at' => Carbon::now(),
                ]);
        }
    }
}
```

**Integration Point**:
- `app/Observers/TimeEntryObserver.php` (new file):
  ```php
  class TimeEntryObserver
  {
      public function created(TimeEntry $timeEntry): void
      {
          if ($timeEntry->project_id !== null) {
              app(BudgetAlertService::class)->evaluateAlerts($timeEntry->project);
          }
      }

      public function updated(TimeEntry $timeEntry): void
      {
          if ($timeEntry->project_id !== null) {
              app(BudgetAlertService::class)->evaluateAlerts($timeEntry->project);
          }
      }

      public function deleted(TimeEntry $timeEntry): void
      {
          if ($timeEntry->project_id !== null) {
              app(BudgetAlertService::class)->evaluateAlerts($timeEntry->project);
          }
      }
  }
  ```
- Register in `app/Providers/EventServiceProvider.php`:
  ```php
  protected $observers = [
      TimeEntry::class => [TimeEntryObserver::class],
  ];
  ```

**Testing**:
- `tests/Unit/Service/BudgetAlertServiceTest.php`
- Test scenarios: threshold crossing, reset logic, archived projects, concurrent writes

### 5.3 BudgetForecastService

**File**: `app/Service/BudgetForecastService.php`

**Responsibilities**:
- Calculate daily burn rate
- Project budget exhaustion date
- Handle monthly vs total budgets

**Key Methods**:

```php
class BudgetForecastService
{
    public function __construct(
        private BudgetService $budgetService,
        private TimezoneService $timezoneService,
    ) {}

    /**
     * @return array{
     *     daily_burn_rate: int,
     *     estimated_exhaustion_date: string|null,
     *     days_remaining: int|null
     * }|null
     */
    public function getForecast(Project $project): ?array
    {
        $consumption = $this->budgetService->getConsumption($project);
        if ($consumption === null || $consumption['remaining'] <= 0) {
            return null;
        }

        $timezone = $this->timezoneService->getTimezoneFromOrganization($project->organization);
        $startDate = $this->getStartDate($project, $timezone);

        // Count days with at least one time entry
        $daysWithActivity = TimeEntry::query()
            ->where('project_id', $project->id)
            ->whereNotNull('end')
            ->where('start', '>=', $startDate)
            ->selectRaw('COUNT(DISTINCT DATE(start)) as count')
            ->first()
            ->count;

        if ($daysWithActivity === 0) {
            return null;  // Not enough data
        }

        $dailyBurnRate = (int) round($consumption['consumed'] / $daysWithActivity);
        if ($dailyBurnRate === 0) {
            return null;
        }

        $daysRemaining = (int) ceil($consumption['remaining'] / $dailyBurnRate);
        $exhaustionDate = Carbon::now($timezone)
            ->addDays($daysRemaining)
            ->format('Y-m-d');

        return [
            'daily_burn_rate' => $dailyBurnRate,
            'estimated_exhaustion_date' => $exhaustionDate,
            'days_remaining' => $daysRemaining,
        ];
    }

    private function getStartDate(Project $project, CarbonTimeZone $timezone): Carbon
    {
        if ($project->budget_period === BudgetPeriod::Monthly) {
            return Carbon::now($timezone)->startOfMonth()->utc();
        }

        // For total budget, use earliest time entry date
        $firstEntry = TimeEntry::query()
            ->where('project_id', $project->id)
            ->orderBy('start')
            ->first();

        return $firstEntry?->start ?? Carbon::now($timezone)->utc();
    }
}
```

**Testing**:
- `tests/Unit/Service/BudgetForecastServiceTest.php`

### 5.4 BudgetReportService

**File**: `app/Service/BudgetReportService.php`

**Responsibilities**:
- Generate budget-vs-actual reports
- Support grouping by project or client
- Apply filters (date range, client, project, budget type, over-budget only)

**Key Methods**:

```php
class BudgetReportService
{
    public function __construct(
        private BudgetService $budgetService,
        private BudgetForecastService $forecastService,
    ) {}

    /**
     * @return array{
     *     data: array<array{...}>,
     *     meta: array{total_budget: int, total_consumed: int, ...}
     * }
     */
    public function generateReport(
        Organization $organization,
        string $groupBy = 'project',  // 'project' or 'client'
        ?string $filterClientId = null,
        ?string $filterProjectId = null,
        ?string $filterBudgetType = null,
        bool $filterOverBudget = false,
        ?Carbon $start = null,
        ?Carbon $end = null,
    ): array {
        $query = Project::query()
            ->whereBelongsTo($organization, 'organization')
            ->whereNotNull('budget_type')
            ->whereNull('archived_at');

        // Apply filters
        if ($filterClientId !== null) {
            $query->where('client_id', $filterClientId);
        }
        if ($filterProjectId !== null) {
            $query->where('id', $filterProjectId);
        }
        if ($filterBudgetType !== null) {
            $query->where('budget_type', $filterBudgetType);
        }

        $projects = $query->with(['client'])->get();

        // Batch consumption calculation
        $consumptions = $this->budgetService->getConsumptionBatch($projects);

        // Filter over-budget if requested
        if ($filterOverBudget) {
            $projects = $projects->filter(function ($project) use ($consumptions) {
                return isset($consumptions[$project->id]) 
                    && $consumptions[$project->id]['percentage'] > 100;
            });
        }

        // Build report data
        $data = [];
        $totalBudget = 0;
        $totalConsumed = 0;

        foreach ($projects as $project) {
            $consumption = $consumptions[$project->id] ?? null;
            if ($consumption === null) {
                continue;
            }

            $forecast = $this->forecastService->getForecast($project);

            $data[] = [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'project_color' => $project->color,
                'client_id' => $project->client_id,
                'client_name' => $project->client?->name,
                'budget_type' => $project->budget_type->value,
                'budget_amount' => $project->budget_amount,
                'consumed' => $consumption['consumed'],
                'remaining' => max(0, $project->budget_amount - $consumption['consumed']),
                'percentage' => $consumption['percentage'],
                'is_over_budget' => $consumption['percentage'] > 100,
                'forecast_exhaustion_date' => $forecast['estimated_exhaustion_date'] ?? null,
            ];

            $totalBudget += $project->budget_amount;
            $totalConsumed += $consumption['consumed'];
        }

        // Group by client if requested
        if ($groupBy === 'client') {
            $data = $this->groupByClient($data);
        }

        return [
            'data' => $data,
            'meta' => [
                'total_budget' => $totalBudget,
                'total_consumed' => $totalConsumed,
                'total_remaining' => max(0, $totalBudget - $totalConsumed),
                'projects_over_budget' => count(array_filter($data, fn($d) => $d['is_over_budget'])),
            ],
        ];
    }

    private function groupByClient(array $data): array
    {
        // Implementation: aggregate project data under each client
        // Return array of client-level summaries
    }
}
```

**Testing**:
- `tests/Unit/Service/BudgetReportServiceTest.php`

---

## 6. Alert Engine

### 6.1 Synchronous Alert Evaluation (AMD-08)

**Trigger Point**: `TimeEntryObserver` (created, updated, deleted events)

**Flow**:
1. Time entry saved/deleted
2. Observer calls `BudgetAlertService::evaluateAlerts($project)` **synchronously**
3. Service uses `lockForUpdate()` on `budget_alerts` table (row-level lock)
4. For each alert:
   - Check if threshold crossed
   - Update `triggered_at` or `reset_at` timestamp
5. If triggered, dispatch `SendBudgetAlertNotification` job (queued)
6. Observer completes, HTTP response returns

**Latency**:
- Synchronous portion: < 50ms (DB lock + threshold check)
- Notification dispatch: async via Laravel queue

**Concurrency Handling**:
- `lockForUpdate()` acquires row-level PostgreSQL lock
- Contention is low: alerts are per-project, concurrent writes to same project rare
- If lock wait occurs, second request waits until first completes (< 50ms)

### 6.2 Notification Classes (SF-04)

**Base Class**: `app/Notifications/BaseNotification` (from FOUND-002)

**app/Notifications/BudgetThresholdNotification.php**:
```php
class BudgetThresholdNotification extends BaseNotification
{
    public function __construct(
        private Project $project,
        private BudgetAlert $alert,
        private array $consumption,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if ($this->alert->notify_email && $this->shouldSendEmail($notifiable)) {
            $channels[] = 'mail';
        }
        return $channels;
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'budget_threshold',
            'project_id' => $this->project->id,
            'project_name' => $this->project->name,
            'threshold_percentage' => $this->alert->threshold_percentage,
            'current_percentage' => $this->consumption['percentage'],
            'consumed' => $this->consumption['consumed'],
            'budget_amount' => $this->project->budget_amount,
            'remaining' => $this->consumption['remaining'],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Budget Alert: {$this->project->name} has reached {$this->alert->threshold_percentage}%")
            ->line("Project '{$this->project->name}' has consumed {$this->consumption['percentage']}% of its budget.")
            ->line("Consumed: {$this->formatAmount($this->consumption['consumed'])} of {$this->formatAmount($this->project->budget_amount)}")
            ->line("Remaining: {$this->formatAmount($this->consumption['remaining'])}")
            ->action('View Project', url("/projects/{$this->project->id}"));
    }

    private function formatAmount(int $amount): string
    {
        if ($this->project->budget_type === BudgetType::Hours) {
            return round($amount / 3600, 1) . ' hours';
        } else {
            return '$' . number_format($amount / 100, 2);
        }
    }
}
```

**app/Notifications/BudgetExceededNotification.php**:
- Similar structure to `BudgetThresholdNotification`
- Sent when 100% threshold crossed
- Emphasizes urgency in message copy

**Job**: `app/Jobs/SendBudgetAlertNotification.php`
```php
class SendBudgetAlertNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private Project $project,
        private BudgetAlert $alert,
        private array $consumption,
    ) {}

    public function handle(): void
    {
        // Find all users with budget view permission
        $members = Member::query()
            ->whereBelongsTo($this->project->organization, 'organization')
            ->whereHas('user')
            ->get();

        foreach ($members as $member) {
            if ($this->hasPermission($member, 'budgets:view')) {
                $member->user->notify(new BudgetThresholdNotification(
                    $this->project,
                    $this->alert,
                    $this->consumption
                ));
            }
        }
    }

    private function hasPermission(Member $member, string $permission): bool
    {
        return app(PermissionStore::class)->has($this->project->organization, $permission);
    }
}
```

### 6.3 Monthly Reset Command

**File**: `app/Console/Commands/BudgetAlertResetMonthlyCommand.php`

```php
class BudgetAlertResetMonthlyCommand extends Command
{
    protected $signature = 'budget:reset-monthly-alerts';
    protected $description = 'Reset budget alerts for monthly budgets at the start of each month';

    public function handle(BudgetAlertService $budgetAlertService): int
    {
        $this->info('Resetting monthly budget alerts...');
        $budgetAlertService->resetMonthlyAlerts();
        $this->info('Monthly budget alerts reset successfully.');
        return 0;
    }
}
```

**Scheduler Registration** (app/Console/Kernel.php):
```php
protected function schedule(Schedule $schedule): void
{
    // Run at 00:01 on 1st of each month
    $schedule->command('budget:reset-monthly-alerts')
        ->monthlyOn(1, '00:01')
        ->timezone('UTC');  // Command handles org-specific timezones internally
}
```

**Idempotency**: Safe to run multiple times. Only resets alerts where `triggered_at IS NOT NULL`.

---

## 7. Scheduled Commands

### 7.1 Monthly Alert Reset

**Command**: `BudgetAlertResetMonthlyCommand` (see 6.3)

**Schedule**: Monthly on 1st at 00:01 UTC

**Behavior**:
1. Query all projects with `budget_period = 'monthly'`
2. For each project, reset `triggered_at` to NULL on all alerts
3. Set `reset_at` to current timestamp
4. Idempotent: can run multiple times safely

**Testing**:
- `tests/Unit/Console/Commands/BudgetAlertResetMonthlyCommandTest.php`
- Verify alerts reset correctly
- Verify total budgets unaffected

---

## 8. Frontend Architecture

### 8.1 TypeScript Types

**File**: `resources/js/types/budget.d.ts`

```typescript
export interface Budget {
  type: 'hours' | 'cost' | 'fixed_fee';
  amount: number;
  currency_code: string;
  period: 'total' | 'monthly';
  consumed: number;
  consumed_including_running: number;
  percentage: number;
  remaining: number;
  is_over_budget: boolean;
  forecast?: BudgetForecast;
  alerts?: BudgetAlert[];
}

export interface BudgetForecast {
  daily_burn_rate: number;
  estimated_exhaustion_date: string | null;
  days_remaining: number | null;
}

export interface BudgetAlert {
  id: string;
  threshold_percentage: number;
  notify_email: boolean;
  triggered_at: string | null;
  reset_at: string | null;
}

export interface BudgetOverviewProject {
  project_id: string;
  project_name: string;
  project_color: string;
  budget_type: 'hours' | 'cost' | 'fixed_fee';
  percentage: number;
  consumed: number;
  budget_amount: number;
  is_over_budget: boolean;
}
```

**Extend existing Project type** (`resources/js/types/project.d.ts`):
```typescript
export interface Project {
  // ... existing fields
  budget?: Budget | null;
}
```

### 8.2 Pinia Store

**File**: `resources/js/utils/useBudget.ts`

```typescript
import { defineStore } from 'pinia';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { api } from '@/utils/api';
import { getCurrentOrganizationId } from '@/utils/useUser';
import type { Budget, BudgetAlert, BudgetOverviewProject } from '@/types/budget';

export const useBudgetStore = defineStore('budget', () => {
  const queryClient = useQueryClient();

  /**
   * Fetch budget status for a project
   */
  function useBudgetStatus(projectId: Ref<string | null>) {
    return useQuery({
      queryKey: ['budget', projectId],
      queryFn: async () => {
        if (!projectId.value) return null;
        const orgId = getCurrentOrganizationId();
        const response = await api.get<{ data: Budget }>(
          `/v1/organizations/${orgId}/projects/${projectId.value}/budget`
        );
        return response.data.data;
      },
      enabled: computed(() => projectId.value !== null),
      refetchInterval: 30000,  // Refresh every 30s
    });
  }

  /**
   * Update budget alerts
   */
  function useUpdateBudgetAlerts(projectId: string) {
    return useMutation({
      mutationFn: async (alerts: Omit<BudgetAlert, 'id' | 'triggered_at' | 'reset_at'>[]) => {
        const orgId = getCurrentOrganizationId();
        const response = await api.put<{ data: BudgetAlert[] }>(
          `/v1/organizations/${orgId}/projects/${projectId}/budget/alerts`,
          { alerts }
        );
        return response.data.data;
      },
      onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: ['budget', projectId] });
      },
    });
  }

  /**
   * Fetch budget overview for dashboard
   */
  function useBudgetOverview() {
    return useQuery({
      queryKey: ['budget-overview'],
      queryFn: async () => {
        const orgId = getCurrentOrganizationId();
        const response = await api.get<{ data: BudgetOverviewProject[] }>(
          `/v1/organizations/${orgId}/charts/budget-overview`
        );
        return response.data.data;
      },
      refetchInterval: 60000,  // Refresh every 60s
    });
  }

  return {
    useBudgetStatus,
    useUpdateBudgetAlerts,
    useBudgetOverview,
  };
});
```

### 8.3 Budget Progress Component

**File**: `resources/js/packages/ui/src/Budget/BudgetProgress.vue`

```vue
<script setup lang="ts">
import { computed } from 'vue';
import type { Budget } from '@/types/budget';

const props = defineProps<{ budget: Budget }>();

function formatAmount(value: number): string {
  if (props.budget.type === 'hours') {
    return `${Math.round(value / 3600)}h`;
  } else {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: props.budget.currency_code,
      minimumFractionDigits: 0,
    }).format(value / 100);
  }
}

const progressColor = computed(() => {
  const pct = props.budget.percentage;
  if (pct >= 100) return 'bg-red-500';
  if (pct >= 80) return 'bg-orange-500';
  if (pct >= 50) return 'bg-yellow-500';
  return 'bg-green-500';
});

const textColor = computed(() => {
  const pct = props.budget.percentage;
  if (pct >= 100) return 'text-red-700';
  if (pct >= 80) return 'text-orange-700';
  if (pct >= 50) return 'text-yellow-700';
  return 'text-green-700';
});
</script>

<template>
  <div class="w-full space-y-2">
    <div class="bg-tertiary h-2 rounded-full relative overflow-hidden w-full">
      <div
        class="h-full transition-all duration-300"
        :class="progressColor"
        :style="{ width: Math.min(budget.percentage, 100) + '%' }"
      ></div>
    </div>
    <div class="flex justify-between items-center text-sm">
      <div class="font-semibold" :class="textColor">
        {{ budget.percentage.toFixed(1) }}% of {{ formatAmount(budget.amount) }}
      </div>
      <div class="text-muted-foreground">
        {{ formatAmount(budget.remaining) }} remaining
      </div>
    </div>
    <div v-if="budget.forecast" class="text-xs text-muted-foreground">
      At current rate, budget will be exhausted on
      {{ new Date(budget.forecast.estimated_exhaustion_date).toLocaleDateString() }}
      ({{ budget.forecast.days_remaining }} days)
    </div>
  </div>
</template>
```

### 8.4 Budget Section on Project Detail Page

**File**: `resources/js/Pages/ProjectDetail.vue` (modify existing)

```vue
<template>
  <AppLayout>
    <div class="project-detail">
      <!-- Existing project details -->
      
      <!-- Budget Section -->
      <div v-if="project.budget" class="mt-6 border-t pt-6">
        <h3 class="text-lg font-semibold mb-4">Budget</h3>
        <BudgetProgress :budget="project.budget" />
        
        <!-- Alert Configuration (for admins/managers) -->
        <div v-if="canManageAlerts" class="mt-4">
          <button @click="openAlertModal">Configure Alerts</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import BudgetProgress from '@/packages/ui/src/Budget/BudgetProgress.vue';
// ... existing imports
</script>
```

### 8.5 Budget Overview Dashboard Widget

**File**: `resources/js/packages/ui/src/Dashboard/BudgetOverviewCard.vue`

```vue
<script setup lang="ts">
import { useBudgetStore } from '@/utils/useBudget';

const budgetStore = useBudgetStore();
const { data: projects, isLoading } = budgetStore.useBudgetOverview();
</script>

<template>
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Budget Overview</h3>
    </div>
    <div class="card-body">
      <div v-if="isLoading" class="text-center py-8">Loading...</div>
      <div v-else-if="!projects || projects.length === 0" class="text-center py-8 text-muted-foreground">
        No projects with budgets
      </div>
      <div v-else class="space-y-3">
        <div
          v-for="project in projects"
          :key="project.project_id"
          class="flex items-center space-x-3 cursor-pointer hover:bg-accent/50 p-2 rounded"
          @click="navigateToProject(project.project_id)"
        >
          <div
            class="w-3 h-3 rounded-full flex-shrink-0"
            :style="{ backgroundColor: project.project_color }"
          ></div>
          <div class="flex-1 min-w-0">
            <div class="font-medium truncate">{{ project.project_name }}</div>
            <div class="w-full bg-tertiary h-1 rounded-full mt-1">
              <div
                class="h-full rounded-full"
                :class="{
                  'bg-green-500': project.percentage < 50,
                  'bg-yellow-500': project.percentage >= 50 && project.percentage < 80,
                  'bg-orange-500': project.percentage >= 80 && project.percentage < 100,
                  'bg-red-500': project.percentage >= 100,
                }"
                :style="{ width: Math.min(project.percentage, 100) + '%' }"
              ></div>
            </div>
          </div>
          <div class="text-sm font-semibold flex-shrink-0" :class="{
            'text-green-700': project.percentage < 50,
            'text-yellow-700': project.percentage >= 50 && project.percentage < 80,
            'text-orange-700': project.percentage >= 80 && project.percentage < 100,
            'text-red-700': project.percentage >= 100,
          }">
            {{ project.percentage.toFixed(0) }}%
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
```

**Integration**: Add to `resources/js/Pages/Dashboard.vue`

### 8.6 Budget Report Page

**File**: `resources/js/Pages/BudgetReport.vue`

```vue
<script setup lang="ts">
import { ref } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { api } from '@/utils/api';
import { getCurrentOrganizationId } from '@/utils/useUser';

const filters = ref({
  group_by: 'project',
  filter_client_id: null,
  filter_project_id: null,
  filter_budget_type: null,
  filter_over_budget: false,
  start: null,
  end: null,
});

const { data: report, isLoading } = useQuery({
  queryKey: ['budget-report', filters],
  queryFn: async () => {
    const orgId = getCurrentOrganizationId();
    const params = new URLSearchParams(
      Object.entries(filters.value).filter(([_, v]) => v !== null)
    );
    const response = await api.get(
      `/v1/organizations/${orgId}/budgets/report?${params}`
    );
    return response.data;
  },
});

function exportCSV() {
  // CSV export logic
}
</script>

<template>
  <AppLayout>
    <div class="budget-report">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Budget Report</h1>
        <button @click="exportCSV">Export CSV</button>
      </div>

      <!-- Filters -->
      <div class="filters mb-6">
        <!-- Filter inputs -->
      </div>

      <!-- Report Table -->
      <div v-if="isLoading" class="text-center py-12">Loading...</div>
      <table v-else-if="report && report.data.length > 0" class="report-table">
        <thead>
          <tr>
            <th>Project</th>
            <th>Client</th>
            <th>Budget Type</th>
            <th>Budget</th>
            <th>Consumed</th>
            <th>Remaining</th>
            <th>Progress</th>
            <th>Forecast</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in report.data"
            :key="row.project_id"
            :class="{ 'bg-red-50': row.is_over_budget }"
          >
            <td>{{ row.project_name }}</td>
            <td>{{ row.client_name || '—' }}</td>
            <td>{{ row.budget_type }}</td>
            <td>{{ formatAmount(row.budget_amount, row.budget_type) }}</td>
            <td>{{ formatAmount(row.consumed, row.budget_type) }}</td>
            <td>{{ formatAmount(row.remaining, row.budget_type) }}</td>
            <td>{{ row.percentage.toFixed(1) }}%</td>
            <td>{{ row.forecast_exhaustion_date || '—' }}</td>
          </tr>
        </tbody>
      </table>

      <!-- Summary -->
      <div v-if="report" class="mt-6 bg-accent/30 p-4 rounded">
        <div class="font-semibold mb-2">Summary</div>
        <div>Total Budget: {{ formatAmount(report.meta.total_budget) }}</div>
        <div>Total Consumed: {{ formatAmount(report.meta.total_consumed) }}</div>
        <div>Total Remaining: {{ formatAmount(report.meta.total_remaining) }}</div>
        <div>Projects Over Budget: {{ report.meta.projects_over_budget }}</div>
      </div>
    </div>
  </AppLayout>
</template>
```

**Web Route** (routes/web.php):
```php
Route::get('/budgets/report', function () {
    return Inertia::render('BudgetReport');
})->name('budgets.report');
```

**Navigation** (resources/js/Layouts/AppLayout.vue):
```vue
<NavigationSidebarItem 
  :href="route('budgets.report')" 
  :active="route().current('budgets.report')"
>
  <ChartBarIcon class="h-5 w-5" />
  Budget Report
</NavigationSidebarItem>
```

### 8.7 Budget Settings in Project Form

**File**: Modify existing project create/edit modal

```vue
<template>
  <form @submit.prevent="submit">
    <!-- Existing fields: name, color, client, etc. -->

    <!-- Budget Section (premium gated) -->
    <div v-if="canAccessPremiumFeatures" class="mt-6 border-t pt-6">
      <h3 class="text-lg font-semibold mb-4">Budget</h3>
      
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label>Budget Type</label>
          <select v-model="form.budget_type">
            <option :value="null">No budget</option>
            <option value="hours">Hours</option>
            <option value="cost">Cost</option>
            <option value="fixed_fee">Fixed Fee</option>
          </select>
        </div>

        <div v-if="form.budget_type">
          <label>Budget Amount</label>
          <input
            v-if="form.budget_type === 'hours'"
            v-model.number="form.budget_hours"
            type="number"
            placeholder="Hours"
          />
          <input
            v-else
            v-model.number="form.budget_amount"
            type="number"
            placeholder="Amount (cents)"
          />
        </div>

        <div v-if="form.budget_type">
          <label>Budget Period</label>
          <select v-model="form.budget_period">
            <option value="total">Total</option>
            <option value="monthly">Monthly</option>
          </select>
        </div>

        <div v-if="form.budget_type && (form.budget_type === 'cost' || form.budget_type === 'fixed_fee')">
          <label>Currency</label>
          <select v-model="form.budget_currency_code">
            <option :value="null">Org default ({{ organization.currency }})</option>
            <option value="USD">USD</option>
            <option value="EUR">EUR</option>
            <!-- ... other currencies -->
          </select>
        </div>
      </div>
    </div>

    <button type="submit">Save</button>
  </form>
</template>

<script setup lang="ts">
// Convert hours to seconds before submit if budget_type === 'hours'
// form.budget_amount = form.budget_hours * 3600
</script>
```

---

## 9. estimated_time Coexistence (AMD-07)

### 9.1 UI Behavior

**When both `estimated_time` and budget exist on a project**:

1. **Project Detail Page**:
   - Display budget section (takes precedence)
   - `estimated_time` is not shown in UI
   - Internally, `estimated_time` remains in DB for backward compatibility

2. **Project List/Cards**:
   - Show budget progress if budget exists
   - Show `EstimatedTimeProgress` component only if no budget

3. **API Response**:
   - Both `estimated_time` and `budget` included in `ProjectResource`
   - Frontend decides which to display

### 9.2 Component Logic

**Modified EstimatedTimeProgress.vue** (or create wrapper):
```vue
<script setup lang="ts">
import { computed } from 'vue';
import BudgetProgress from './Budget/BudgetProgress.vue';
import EstimatedTimeProgress from './EstimatedTimeProgress.vue';

const props = defineProps<{
  project: Project;
}>();

const showBudget = computed(() => props.project.budget !== null);
const showEstimatedTime = computed(() => 
  !showBudget.value && props.project.estimated_time !== null
);
</script>

<template>
  <BudgetProgress v-if="showBudget" :budget="project.budget" />
  <EstimatedTimeProgress
    v-else-if="showEstimatedTime"
    :estimated="project.estimated_time"
    :current="project.spent_time"
  />
</template>
```

### 9.3 Migration Path (Future Technical Debt)

**NOT in scope for this feature**, but documented for future:

1. **Task: Migrate `estimated_time` to budget format**
   - Create migration command: `budget:migrate-estimated-time`
   - For each project with `estimated_time` but no budget:
     - Set `budget_type = 'hours'`
     - Set `budget_amount = estimated_time` (already in seconds)
     - Set `budget_period = 'total'`
   - Set `estimated_time = NULL` after migration

2. **Deprecation Timeline**:
   - Phase 1 (current): Coexistence, budget takes precedence in UI
   - Phase 2 (future): Migrate all `estimated_time` values
   - Phase 3 (future): Remove `estimated_time` column entirely

---

## 10. Notification Design (SF-04)

### 10.1 Dependency on Shared Infrastructure

This feature **depends on** FOUND-001 through FOUND-005 (Shared Notification Infrastructure):
- FOUND-001: Notification infrastructure migration (`notifications` table)
- FOUND-002: `BaseNotification` class
- FOUND-003: Notification bell UI component
- FOUND-004: Notification API endpoints
- FOUND-005: Notification preferences in org settings

### 10.2 Budget Notification Classes

**app/Notifications/BudgetThresholdNotification.php** (see 6.2)

**app/Notifications/BudgetExceededNotification.php** (see 6.2)

Both extend `App\Notifications\BaseNotification` and use `database` + `mail` channels.

### 10.3 Notification Recipients

**Rule**: All users with `budgets:view` permission on the organization receive budget alert notifications.

**Implementation** (in `SendBudgetAlertNotification` job):
```php
$members = Member::query()
    ->whereBelongsTo($project->organization, 'organization')
    ->whereHas('user')
    ->get();

foreach ($members as $member) {
    if (app(PermissionStore::class)->has($project->organization, 'budgets:view')) {
        $member->user->notify(new BudgetThresholdNotification(...));
    }
}
```

### 10.4 Notification Preferences

Users can disable email notifications via organization settings (FOUND-005).

Per-alert email toggle: `BudgetAlert.notify_email` field.

---

## 11. Permission Matrix (SF-08 Modular Pattern)

### 11.1 Permissions File

**File**: `app/Permissions/BudgetPermissions.php`

```php
<?php
declare(strict_types=1);

namespace App\Permissions;

use Laravel\Jetstream\Jetstream;

class BudgetPermissions
{
    public static function register(): void
    {
        // Owner, Admin, Manager: full budget access
        $fullBudgetPermissions = [
            'budgets:view',
            'budgets:update',
            'budget-alerts:manage',
        ];

        // Employee: view-only
        $employeeBudgetPermissions = [
            'budgets:view',
        ];

        // Update existing roles
        Jetstream::role('owner', 'Owner', array_merge(
            self::getExistingOwnerPermissions(),
            $fullBudgetPermissions
        ));

        Jetstream::role('admin', 'Administrator', array_merge(
            self::getExistingAdminPermissions(),
            $fullBudgetPermissions
        ));

        Jetstream::role('manager', 'Manager', array_merge(
            self::getExistingManagerPermissions(),
            $fullBudgetPermissions
        ));

        Jetstream::role('employee', 'Employee', array_merge(
            self::getExistingEmployeePermissions(),
            $employeeBudgetPermissions
        ));
    }

    private static function getExistingOwnerPermissions(): array
    {
        // Return existing owner permissions
    }

    // ... similar methods for other roles
}
```

### 11.2 JetstreamServiceProvider Registration

**File**: `app/Providers/JetstreamServiceProvider.php`

```php
protected function configurePermissions(): void
{
    // ... existing permission setup ...

    // Feature permissions (modular)
    BudgetPermissions::register();
    // Future: ExpensePermissions::register();
    // Future: TimesheetApprovalPermissions::register();
}
```

### 11.3 Permission Matrix

| Role | budgets:view | budgets:update | budget-alerts:manage |
|------|-------------|----------------|---------------------|
| Owner | ✅ | ✅ | ✅ |
| Admin | ✅ | ✅ | ✅ |
| Manager | ✅ | ✅ | ✅ |
| Employee | ✅ | ❌ | ❌ |
| Placeholder | ❌ | ❌ | ❌ |

**Scoping Rules**:
- `budgets:view`: Employees can only see budgets on projects they are members of (enforced via `projects:view` permission + `visibleByEmployee()` scope)
- `budgets:update`: Create/edit budgets on any project in org
- `budget-alerts:manage`: Configure alert thresholds and email preferences

---

## 12. Migration Strategy

### 12.1 Migration Sequence

**Date Prefix**: `2026_03_03_` (SF-03, AMD-03)

1. **`2026_03_03_000001_add_budget_columns_to_projects_table.php`**
   - Add budget columns to `projects` table
   - Add CHECK constraints for data integrity
   - Estimated time: 10 minutes

2. **`2026_03_03_000002_create_budget_alerts_table.php`**
   - Create `budget_alerts` table
   - Add foreign keys with cascade delete
   - Add unique constraint on (project_id, threshold_percentage)
   - Add `organization_id` column (AMD-06)
   - Add indexes for query efficiency
   - Estimated time: 10 minutes

**Rollback Strategy**:
- Both migrations have `down()` methods that drop columns/tables
- No data loss on rollback (budget data is new)

### 12.2 Seeder (Optional, for Testing)

**File**: `database/seeders/BudgetSeeder.php`

```php
class BudgetSeeder extends Seeder
{
    public function run(): void
    {
        $projects = Project::factory()->count(10)->create([
            'budget_type' => BudgetType::Hours->value,
            'budget_amount' => 360000,  // 100 hours
            'budget_period' => BudgetPeriod::Total->value,
        ]);

        foreach ($projects as $project) {
            BudgetAlert::factory()->create([
                'project_id' => $project->id,
                'organization_id' => $project->organization_id,
                'threshold_percentage' => 50,
            ]);
            BudgetAlert::factory()->create([
                'project_id' => $project->id,
                'organization_id' => $project->organization_id,
                'threshold_percentage' => 80,
            ]);
            BudgetAlert::factory()->create([
                'project_id' => $project->id,
                'organization_id' => $project->organization_id,
                'threshold_percentage' => 100,
            ]);

            // Create some time entries for testing
            TimeEntry::factory()->count(5)->create([
                'project_id' => $project->id,
                'organization_id' => $project->organization_id,
            ]);
        }
    }
}
```

---

## 13. File Manifest

### 13.1 Backend Files to Create

**Enums**:
- `app/Enums/BudgetType.php`
- `app/Enums/BudgetPeriod.php`

**Models**:
- `app/Models/BudgetAlert.php`

**Services**:
- `app/Service/BudgetService.php`
- `app/Service/BudgetAlertService.php`
- `app/Service/BudgetForecastService.php`
- `app/Service/BudgetReportService.php`

**Controllers**:
- `app/Http/Controllers/Api/V1/BudgetController.php`

**Requests**:
- (Extend existing) `app/Http/Requests/V1/Project/ProjectStoreRequest.php`
- (Extend existing) `app/Http/Requests/V1/Project/ProjectUpdateRequest.php`

**Resources**:
- `app/Http/Resources/V1/Budget/BudgetResource.php`
- `app/Http/Resources/V1/Budget/BudgetAlertResource.php`

**Observers**:
- `app/Observers/TimeEntryObserver.php`

**Notifications**:
- `app/Notifications/BudgetThresholdNotification.php`
- `app/Notifications/BudgetExceededNotification.php`

**Jobs**:
- `app/Jobs/SendBudgetAlertNotification.php`

**Commands**:
- `app/Console/Commands/BudgetAlertResetMonthlyCommand.php`

**Permissions**:
- `app/Permissions/BudgetPermissions.php`

**Migrations**:
- `database/migrations/2026_03_03_000001_add_budget_columns_to_projects_table.php`
- `database/migrations/2026_03_03_000002_create_budget_alerts_table.php`

**Factories**:
- `database/factories/BudgetAlertFactory.php`

**Seeders** (optional):
- `database/seeders/BudgetSeeder.php`

**Tests**:
- `tests/Unit/Service/BudgetServiceTest.php`
- `tests/Unit/Service/BudgetAlertServiceTest.php`
- `tests/Unit/Service/BudgetForecastServiceTest.php`
- `tests/Unit/Service/BudgetReportServiceTest.php`
- `tests/Unit/Endpoint/Api/V1/BudgetEndpointTest.php`
- `tests/Unit/Console/Commands/BudgetAlertResetMonthlyCommandTest.php`

### 13.2 Backend Files to Modify

- `app/Models/Project.php` (add budget fields, casts, relationships)
- `app/Http/Controllers/Api/V1/ProjectController.php` (extend store/update for budget)
- `app/Http/Resources/V1/Project/ProjectResource.php` (add budget object)
- `app/Http/Controllers/Api/V1/ChartController.php` (add budgetOverview method)
- `app/Providers/JetstreamServiceProvider.php` (register BudgetPermissions)
- `app/Providers/EventServiceProvider.php` (register TimeEntryObserver)
- `app/Console/Kernel.php` (register scheduled command)
- `routes/api.php` (add budget routes)
- `routes/web.php` (add budget report route)

### 13.3 Frontend Files to Create

**Types**:
- `resources/js/types/budget.d.ts`

**Stores**:
- `resources/js/utils/useBudget.ts`

**Components**:
- `resources/js/packages/ui/src/Budget/BudgetProgress.vue`
- `resources/js/packages/ui/src/Dashboard/BudgetOverviewCard.vue`

**Pages**:
- `resources/js/Pages/BudgetReport.vue`

### 13.4 Frontend Files to Modify

- `resources/js/types/project.d.ts` (add budget field)
- `resources/js/Pages/ProjectDetail.vue` (add budget section)
- `resources/js/Pages/Dashboard.vue` (add BudgetOverviewCard)
- `resources/js/Layouts/AppLayout.vue` (add budget report nav item)
- Project create/edit modal (add budget fields)

---

## 14. Data Flow

### 14.1 Budget Consumption Calculation Flow

```
User creates/updates/deletes TimeEntry
  ↓
TimeEntryObserver fires (created/updated/deleted)
  ↓
BudgetAlertService::evaluateAlerts(project) [SYNC]
  ↓
BudgetService::getConsumption(project) [SYNC]
  ↓
PostgreSQL aggregation query:
  - Hours: SUM(EXTRACT(epoch FROM (end - start)))
  - Cost: SUM(EXTRACT(epoch FROM (...)) * billable_rate / 3600)
  - Monthly scoping if applicable
  ↓
Return: {consumed, percentage, remaining}
  ↓
BudgetAlertService checks each alert threshold [SYNC]
  ↓
If threshold crossed:
  - Update triggered_at timestamp
  - Dispatch SendBudgetAlertNotification job [ASYNC]
  ↓
Job executes:
  - Find all members with budgets:view permission
  - Send BudgetThresholdNotification via database + mail channels
  ↓
Notification appears in user's notification bell (FOUND-003)
```

### 14.2 Dashboard Budget Overview Flow

```
User loads Dashboard page
  ↓
Frontend calls useBudgetOverview() (Pinia store)
  ↓
API: GET /organizations/{org}/charts/budget-overview
  ↓
ChartController::budgetOverview() [SYNC]
  ↓
Query all projects with budgets (non-archived)
  ↓
BudgetService::getConsumptionBatch(projects) [SYNC, batch query]
  ↓
Two queries (one for hours budgets, one for cost budgets):
  - Group by project_id
  - Single aggregation per budget type
  ↓
Map results to projects, sort by percentage desc, take top 5
  ↓
Return BudgetOverviewProject[] with meta
  ↓
Frontend renders BudgetOverviewCard with progress bars
```

### 14.3 Budget Report Generation Flow

```
User navigates to Budget Report page
  ↓
Frontend calls API with filters (date range, client, project, type)
  ↓
API: GET /organizations/{org}/budgets/report?filters
  ↓
BudgetController::report() [SYNC]
  ↓
BudgetReportService::generateReport(filters) [SYNC]
  ↓
Query projects with budgets (apply filters)
  ↓
BudgetService::getConsumptionBatch(projects) [SYNC]
  ↓
BudgetForecastService::getForecast(project) for each [SYNC]
  ↓
Build report data array with consumed, remaining, percentage, forecast
  ↓
Group by client if requested
  ↓
Calculate meta totals
  ↓
Return report data + meta
  ↓
Frontend renders table, allows CSV export
```

### 14.4 Monthly Alert Reset Flow

```
Scheduler runs at 00:01 UTC on 1st of each month
  ↓
BudgetAlertResetMonthlyCommand executes
  ↓
BudgetAlertService::resetMonthlyAlerts() [SYNC]
  ↓
Query all projects with budget_period = 'monthly'
  ↓
For each project:
  - Update budget_alerts SET triggered_at = NULL, reset_at = NOW()
    WHERE project_id = ? AND triggered_at IS NOT NULL
  ↓
Command completes
  ↓
Next time entry triggers alert evaluation, alerts can re-trigger
```

---

## 15. Build Sequence

### Phase 1: Foundation (Sprint 1, Weeks 1-2)

- [ ] **BUD-001**: Create BudgetType and BudgetPeriod enums (1 SP)
- [ ] **BUD-002**: Database migration - Add budget columns to projects (2 SP)
- [ ] **BUD-003**: Database migration - Create budget_alerts table (2 SP)
- [ ] **BUD-004**: Create BudgetAlert model with factory (2 SP)
- [ ] **BUD-005**: Extend Project model with budget fields (2 SP)
- [ ] **BUD-006**: Create BudgetService with consumption calculation (5 SP)
- [ ] **BUD-007**: Extend ProjectStoreRequest and ProjectUpdateRequest (2 SP)
- [ ] **BUD-008**: Extend ProjectController for budget CRUD (3 SP)
- [ ] **BUD-009**: Extend ProjectResource with budget data (2 SP)
- [ ] **BUD-010**: Register budget permissions (modular pattern) (1 SP)
- [ ] **BUD-011**: Add API routes for budget endpoints (1 SP)
- [ ] **BUD-012**: BudgetService unit tests (5 SP)
- [ ] **BUD-013**: Budget endpoint tests (5 SP)

**Sprint 1 Total**: 33 SP

### Phase 2: Alerts & Dashboard (Sprint 2, Weeks 3-4)

- [ ] **BUD-014**: Create BudgetAlertService (5 SP)
- [ ] **BUD-015**: Create budget notification classes (3 SP)
- [ ] **BUD-016**: Integrate alert evaluation with TimeEntryObserver (3 SP)
- [ ] **BUD-017**: Monthly alert reset scheduled command (2 SP)
- [ ] **BUD-018**: BudgetAlertService unit tests (3 SP)
- [ ] **BUD-019**: Budget dashboard chart endpoint (3 SP)
- [ ] **BUD-020**: TypeScript types for budget API (1 SP)
- [ ] **BUD-021**: Budget Pinia store (3 SP)
- [ ] **BUD-022**: Budget progress bar component (3 SP)
- [ ] **BUD-023**: Budget section on project detail page (2 SP)
- [ ] **BUD-024**: Budget overview dashboard card (3 SP)

**Sprint 2 Total**: 28 SP

### Phase 3: Forecasting & Reporting (Sprint 3, Weeks 5-6)

- [ ] **BUD-025**: Create BudgetForecastService (5 SP)
- [ ] **BUD-026**: BudgetForecastService unit tests (3 SP)
- [ ] **BUD-027**: Budget report service (5 SP)
- [ ] **BUD-028**: Budget report endpoint (3 SP)
- [ ] **BUD-029**: Budget report frontend page (5 SP)
- [ ] **BUD-030**: Budget settings in project create/edit modal (3 SP)
- [ ] **BUD-031**: E2E tests for budget workflows (3 SP)
- [ ] **BUD-032**: OpenAPI spec updates (1 SP)
- [ ] **BUD-033**: Documentation (user guide, changelog) (2 SP)
- [ ] **BUD-034**: Register Inertia web route for budget report page (1 SP, AMD-11)

**Sprint 3 Total**: 27 SP

**Total Effort**: 88 SP (165 hours per sprint task sums)

---

## 16. Critical Implementation Details

### 16.1 N+1 Query Prevention (AMD-09)

**Dashboard Widget**:
```php
// WRONG (N+1):
foreach ($projects as $project) {
    $consumption = $budgetService->getConsumption($project);
}

// RIGHT (batch):
$consumptions = $budgetService->getConsumptionBatch($projects);
foreach ($projects as $project) {
    $consumption = $consumptions[$project->id];
}
```

### 16.2 Synchronous Alert Check Latency (AMD-08)

**Target**: < 50ms added to time entry write

**Optimization strategies**:
1. Use `lockForUpdate()` only on alert rows, not entire table
2. Index on `(project_id, threshold_percentage)` for fast alert lookup
3. Skip archived projects early
4. Dispatch notification job immediately, don't wait for completion

**Measurement**:
```php
$start = microtime(true);
$budgetAlertService->evaluateAlerts($project);
$latency = (microtime(true) - $start) * 1000;
Log::info("Budget alert evaluation latency: {$latency}ms");
```

### 16.3 Monthly Period Timezone Handling

**Always use organization timezone** for month boundaries:
```php
$timezone = $this->timezoneService->getTimezoneFromOrganization($project->organization);
$firstDayOfMonth = Carbon::now($timezone)->startOfMonth()->utc();
```

**Not** user timezone, because budget is org-level concept.

### 16.4 Currency Display

**Backend**: Store amounts in cents (integers)

**Frontend**: Use `Intl.NumberFormat` for locale-aware formatting:
```typescript
new Intl.NumberFormat('en-US', {
  style: 'currency',
  currency: budget.currency_code,
  minimumFractionDigits: 0,
}).format(budget.amount / 100);
```

### 16.5 Billable Rate Changes

**Scenario**: User changes billable rate on project after time entries logged.

**Behavior**:
- Existing `BillableRateService::updateTimeEntriesBillableRateForProject()` recalculates time entry rates
- Next time `BudgetService::getConsumption()` runs, it uses updated rates
- No additional work needed for budgets

### 16.6 Archived Projects

**Behavior**:
- Budget data remains accessible (read-only)
- No new alerts trigger (checked in `BudgetAlertService::evaluateAlerts()`)
- Excluded from dashboard widget and reports

---

## 17. Testing Strategy

### 17.1 Unit Tests

**BudgetService**:
- Hours budget: correct consumption from time entries
- Cost budget: correct consumption using billable rates
- Fixed-fee budget: same as cost calculation
- Monthly period: only counts current month entries
- Running entries: included in `consumed_including_running`
- No time entries: consumed = 0
- Over budget: percentage > 100
- Project with no budget: returns null

**BudgetAlertService**:
- Threshold crossed upward: alert triggered
- Threshold crossed downward: alert reset
- Duplicate trigger prevention: `triggered_at` prevents re-trigger
- Archived project: no alert triggered
- Concurrent writes: `lockForUpdate()` prevents race conditions

**BudgetForecastService**:
- Valid forecast: correct daily burn rate and exhaustion date
- No time entries: returns null
- Zero burn rate: returns null
- Monthly budget: uses current month start date

**BudgetReportService**:
- Filters applied correctly
- Grouping by client works
- Over-budget filter works
- Meta totals calculated correctly

### 17.2 Endpoint Tests

**Budget CRUD**:
- Create project with budget: budget saved, default alerts created
- Update project budget: budget updated, existing alerts preserved
- Remove budget: budget set to null, alerts soft-deactivated
- Get budget status: returns consumption data
- Get budget status with no budget: 404

**Alert Management**:
- Update alerts: alerts replaced with new list
- Duplicate threshold: validation error
- Permission check: employee cannot update alerts

**Budget Report**:
- Generate report: correct data returned
- Apply filters: correct filtering
- Export CSV: valid CSV format

### 17.3 E2E Tests (Playwright)

**Budget Workflow**:
1. Admin creates project with hours budget (100 hours)
2. User logs 50 hours
3. Budget widget shows 50% consumed (green)
4. 50% alert triggered, notification appears in bell
5. User logs 30 more hours
6. Budget widget shows 80% consumed (orange)
7. 80% alert triggered, email sent (if enabled)
8. User logs 20 more hours
9. Budget widget shows 100% consumed (red)
10. 100% alert triggered
11. Admin views budget report, sees project over budget

**Monthly Budget Reset**:
1. Admin creates project with monthly budget
2. User logs time, 80% alert triggered
3. Month rolls over (simulate via date mocking)
4. Scheduled command resets alerts
5. User logs time again, 80% alert re-triggers

---

## 18. Performance Targets

| Operation | Target | Measurement |
|-----------|--------|-------------|
| Budget consumption calculation (single project) | < 100ms | `BudgetService::getConsumption()` |
| Alert evaluation on time entry write | < 50ms | `BudgetAlertService::evaluateAlerts()` |
| Dashboard budget overview (5 projects) | < 200ms (95th percentile) | `ChartController::budgetOverview()` |
| Budget report (100 projects) | < 500ms | `BudgetController::report()` |
| Monthly alert reset command | < 5 seconds (500 projects) | `BudgetAlertResetMonthlyCommand` |

**Optimization strategies if targets not met**:
- Add database indexes on `(project_id, start)` for time entries
- Cache consumption calculations (Redis, 60s TTL)
- Use database-level computed columns for `spent_time` (already exists)

---

## 19. Security Considerations

### 19.1 Permission Checks

**All budget endpoints use existing permission check pattern**:
```php
$this->checkPermission($organization, 'budgets:update', $project);
```

**Scoping**:
- Organization scoping via route model binding (existing pattern)
- Project-organization validation in controller `checkPermission()` override
- Employee scoping via `visibleByEmployee()` query scope

### 19.2 Data Validation

**Input validation**:
- Budget amounts: integer, min 1, max 9223372036854775807 (bigint)
- Budget types: enum validation
- Threshold percentages: integer, 1-200
- Currency codes: exactly 3 characters (ISO 4217)

**SQL Injection**:
- All queries use Eloquent query builder (parameterized)
- Raw queries use parameter binding

### 19.3 Premium Feature Gating

**Consistent with existing patterns**:
```php
if ($this->canAccessPremiumFeatures($organization)) {
    // Budget logic
}
```

### 19.4 Notification Privacy

**Notifications do not include**:
- Other users' personal data
- Detailed time entry descriptions
- Billable rate values (if user lacks permission)

**Notifications include**:
- Project name
- Budget consumption percentage
- Aggregate consumption and remaining amounts

---

## 20. Monitoring & Observability

### 20.1 Logging

**Budget alert evaluation**:
```php
Log::info('Budget alert triggered', [
    'project_id' => $project->id,
    'threshold' => $alert->threshold_percentage,
    'current_percentage' => $currentPercentage,
]);
```

**Monthly reset command**:
```php
Log::info('Monthly budget alerts reset', [
    'projects_count' => $projectsCount,
    'alerts_reset_count' => $alertsResetCount,
]);
```

### 20.2 Metrics

**Application-level metrics** (Laravel Telescope):
- Budget endpoint response times
- Alert evaluation latency
- Batch consumption query performance

**Database-level metrics** (PostgreSQL):
- Query execution times for budget aggregations
- Lock wait times on `budget_alerts` table

---

## 21. Dependencies

### 21.1 Shared Foundations (Must complete first)

- **FOUND-001**: Notification infrastructure migration
- **FOUND-002**: BaseNotification class
- **FOUND-003**: Notification bell UI component
- **FOUND-004**: Notification API endpoints
- **FOUND-005**: Notification preferences
- **FOUND-007**: Modular permissions infrastructure

### 21.2 Internal Feature Dependencies

**BUD-006 depends on**: BUD-001, BUD-002, BUD-005  
**BUD-008 depends on**: BUD-005, BUD-006, BUD-007  
**BUD-014 depends on**: BUD-004, BUD-006  
**BUD-016 depends on**: BUD-014, BUD-015  
**BUD-025 depends on**: BUD-006  

---

## 22. Success Criteria

### 22.1 Functional

- [ ] Projects can have hours, cost, or fixed-fee budgets
- [ ] Budget consumption calculated correctly for all types
- [ ] Monthly budgets reset on 1st of each month
- [ ] Alerts trigger at configurable thresholds (50%, 80%, 100%)
- [ ] Notifications sent via database + email channels
- [ ] Dashboard widget shows top 5 budgets at risk
- [ ] Budget report page shows all budgets with filters
- [ ] Forecast shows estimated exhaustion date

### 22.2 Technical

- [ ] All performance targets met (< 50ms alert eval, < 200ms dashboard)
- [ ] N+1 query prevention (batch consumption calculation)
- [ ] All unit tests passing (100% coverage on services)
- [ ] All endpoint tests passing
- [ ] E2E tests passing (budget workflow, monthly reset)
- [ ] OpenAPI spec updated
- [ ] PHPStan level 9 passes
- [ ] ESLint passes
- [ ] No regressions in existing features

### 22.3 User Experience

- [ ] Budget settings easy to configure in project form
- [ ] Budget progress visually clear (color-coded)
- [ ] Notifications informative and actionable
- [ ] Dashboard widget loads quickly (< 200ms)
- [ ] Report page responsive with large datasets
- [ ] CSV export works correctly

---

## Summary

This architecture blueprint provides a complete, implementation-ready design for the Budgets & Alerts feature based on existing Solidtime patterns. Key decisions:

1. **Budget on projects table** (mirrors `estimated_time`)
2. **Separate BudgetAlert model** (independent lifecycle)
3. **Synchronous alert check, async notification** (< 50ms overhead)
4. **Batch consumption calculation** (solves N+1)
5. **Monthly reset via scheduled command** (automated)
6. **Modular permissions pattern** (SF-08)
7. **Extends existing notification infrastructure** (SF-04)

The design integrates seamlessly with existing services (`TimeEntryAggregationService`, `BillableRateService`, `DashboardService`) and follows established conventions for migrations, controllers, validation, and frontend components.

Total implementation effort: **88 SP (165 hours)** across 3 sprints.