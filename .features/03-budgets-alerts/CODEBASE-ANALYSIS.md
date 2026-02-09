# Codebase Analysis: Feature 03 -- Budgets & Alerts

**Date**: 2026-02-06
**Analyst**: Claude (Opus 4.6)
**Branch**: `feature/weekly-timesheet-grid`
**Status**: Complete

---

## Executive Summary

This analysis examines the solidtime codebase to map every integration point the Budgets & Alerts feature requires. Key findings:

1. **Project Model** uses a computed-attribute pattern (`spent_time`) backed by `Korridor\LaravelComputedAttributes`. Budget consumption tracking can follow the same pattern or use real-time aggregation.
2. **Time Entry Save Pipeline** dispatches `RecalculateSpentTimeForProject` jobs after create/update/delete -- these are the primary hook points for budget alert evaluation.
3. **No notification infrastructure exists** -- no `notifications` table, no `App\Notifications\` directory, no notification bell UI. The shared foundation tasks FOUND-001 through FOUND-005 are hard prerequisites.
4. **BillableRateService** implements a four-level cascade (ProjectMember > Project > Member > Organization) that the cost-based budget calculation depends on. The `billable_rate` is already stored on each `TimeEntry` as a computed attribute.
5. **DashboardService** aggregates time data with raw PostgreSQL queries using `coalesce("end", now())` for running entries. The budget dashboard widget will follow this same pattern.
6. **TimeEntryAggregationService** already supports grouping by Project and Client with cost calculation -- directly usable for budget reports.
7. **EstimatedTimeProgress** is a simple Vue component (35 lines) used in project table rows. It will be extended/replaced by the budget progress bar.
8. **Scheduled commands** use config-gated `$schedule->command()` in `Kernel.php`. The monthly budget reset command (BUD-017) will follow this pattern.

---

## 1. Project Model Deep Dive

**File**: `/home/keven/Documents/solidtime-analysis/app/Models/Project.php` (201 lines)

### Schema (All Columns)

The `projects` table was created across multiple migrations:

| Column | Type | Source Migration |
|--------|------|-----------------|
| `id` | UUID (PK) | `2024_01_20_110439_create_projects_table.php` |
| `name` | string(255) | `2024_01_20_110439` |
| `color` | string(16) | `2024_01_20_110439` |
| `billable_rate` | unsigned integer, nullable | `2024_01_20_110439` |
| `is_public` | boolean, default false | `2024_01_20_110439` |
| `client_id` | UUID FK, nullable | `2024_01_20_110439` |
| `organization_id` | UUID FK | `2024_01_20_110439` |
| `is_billable` | boolean | `2024_03_26_171253` |
| `estimated_time` | unsigned integer, nullable | `2024_07_02_134307` |
| `spent_time` | unsigned integer, default 0 | `2024_09_18_120203` |
| `archived_at` | timestamp, nullable | (project archival migration) |
| `created_at` | timestamp | `2024_01_20_110439` |
| `updated_at` | timestamp | `2024_01_20_110439` |

**Critical columns for budgets**:
- `estimated_time` -- nullable integer, stores **seconds**. This is the legacy hours-only estimate that the budget feature coexists with (see AMD-07).
- `spent_time` -- computed attribute, stores **seconds** of completed time entries. Recalculated asynchronously via job dispatch.
- `billable_rate` -- nullable integer, stores **cents per hour**. Used for project-level rate in the BillableRateService cascade.
- `is_billable` -- boolean default. Determines whether new time entries on this project are billable by default.

### PHPDoc Property Annotations (lines 22-44)

```php
// /home/keven/Documents/solidtime-analysis/app/Models/Project.php, lines 22-44
/**
 * @property string $id
 * @property string $name
 * @property string $color
 * @property string $organization_id
 * @property string $client_id
 * @property int|null $billable_rate
 * @property bool $is_public
 * @property bool $is_billable
 * @property-read bool $is_archived
 * @property int|null $estimated_time
 * @property int $spent_time
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read Client|null $client
 * @property-read Collection<int, Task> $tasks
 * @property-read Collection<int, ProjectMember> $members
 */
```

### Traits (lines 47-53)

```php
// /home/keven/Documents/solidtime-analysis/app/Models/Project.php, lines 45-53
class Project extends Model implements AuditableContract
{
    use ComputedAttributes;   // Korridor\LaravelComputedAttributes
    use CustomAuditable;      // OwenIt\Auditing (all changes audited except computed attrs)
    use HasFactory;           // Database\Factories\ProjectFactory
    use HasUuids;             // UUID primary keys
```

### Casts (lines 60-66)

```php
// /home/keven/Documents/solidtime-analysis/app/Models/Project.php, lines 60-66
protected $casts = [
    'name' => 'string',
    'color' => 'string',
    'archived_at' => 'datetime',
    'estimated_time' => 'integer',
    'spent_time' => 'integer',
];
```

**Budget additions needed**: `'budget_type' => BudgetType::class`, `'budget_amount' => 'integer'`, `'budget_currency_code' => 'string'`, `'budget_period' => BudgetPeriod::class`.

### Computed Attributes Configuration (lines 83-94)

```php
// /home/keven/Documents/solidtime-analysis/app/Models/Project.php, lines 83-94
protected array $computed = [
    'spent_time',
];

protected array $auditExclude = [
    'spent_time',
];
```

`spent_time` is declared as a computed attribute, meaning it can be regenerated via `php artisan computed-attributes:generate` and is excluded from audit logs. Budget consumption will **not** use this pattern -- it will be calculated on demand by `BudgetService` rather than stored as a column, since consumption depends on budget type (hours vs cost) and period (total vs monthly).

### Computed Attribute Getter (lines 96-109)

```php
// /home/keven/Documents/solidtime-analysis/app/Models/Project.php, lines 96-109
public function getSpentTimeComputed(): ?int
{
    if ($this->hasAttribute('spent_time_computed')) {
        return $this->attributes['spent_time_computed'] === null ? 0 : (int) $this->attributes['spent_time_computed'];
    } else {
        $result = $this->timeEntries()
            ->whereNotNull('end')
            ->selectRaw('sum(extract(epoch from ("end" - start))) as spent_time')
            ->first();
        return (int) $result->spent_time;
    }
}
```

**Pattern**: Try precomputed value first, fall back to real-time aggregation. The `BudgetService` consumption calculation will always use real-time aggregation since it needs to respect `budget_period` (monthly scoping) and `budget_type` (hours vs cost).

### Batch Computation Scope (lines 118-125)

```php
// /home/keven/Documents/solidtime-analysis/app/Models/Project.php, lines 118-125
public function scopeComputedAttributesGenerate(Builder $builder, array $attributes): Builder
{
    if (in_array('spent_time', $attributes, true)) {
        $builder->withAggregate('timeEntries as spent_time_computed', DB::raw('extract(epoch from ("end" - start))'), 'sum');
    }
    return $builder;
}
```

### Relationships (lines 142-177)

```php
// /home/keven/Documents/solidtime-analysis/app/Models/Project.php, lines 142-177
organization() -> BelongsTo<Organization>
client()       -> BelongsTo<Client>
members()      -> HasMany<ProjectMember>
tasks()        -> HasMany<Task>
timeEntries()  -> HasMany<TimeEntry>
```

**Missing**: `budgetAlerts()` relationship -- will be added in BUD-005.

### Scopes (lines 182-190)

```php
// /home/keven/Documents/solidtime-analysis/app/Models/Project.php, lines 182-190
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

**Budget visibility**: Employees will only see budgets on projects they are members of (or public projects). This scope will be reused in the budget report endpoint.

### isArchived Accessor (lines 195-200)

```php
// /home/keven/Documents/solidtime-analysis/app/Models/Project.php, lines 195-200
protected function isArchived(): Attribute
{
    return Attribute::make(
        get: fn (mixed $value, array $attributes) => isset($attributes['archived_at']),
    );
}
```

**Budget logic**: Archived projects (`archived_at IS NOT NULL`) should have read-only budgets. No new alerts should trigger. The `BudgetAlertService` must check `$project->is_archived` before dispatching notifications.

---

## 2. TimeEntryAggregationService Analysis

**File**: `/home/keven/Documents/solidtime-analysis/app/Service/TimeEntryAggregationService.php` (551 lines)

### Purpose

Central service for grouped time entry aggregations with cost calculations. Used by the reporting system and can be leveraged by the budget report feature.

### Core Method Signature (line 47)

```php
// /home/keven/Documents/solidtime-analysis/app/Service/TimeEntryAggregationService.php, line 47
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

Returns structure:
```php
[
    'seconds' => int,           // Total duration
    'cost' => int|null,         // Total cost in cents
    'grouped_type' => string|null,
    'grouped_data' => null|array
]
```

### SQL Aggregation Pattern (lines 77-82)

```php
// /home/keven/Documents/solidtime-analysis/app/Service/TimeEntryAggregationService.php, lines 77-82
$timeEntriesQuery->selectRaw(
    ($group1Select !== null ? $group1Select.' as group_1,' : '').
    ($group2Select !== null ? $group2Select.' as group_2,' : '').
    ' round(sum(extract(epoch from ('.$endRawSelect.' - '.$startRawSelect.')))) as aggregate,'.
    ' round(sum(extract(epoch from ('.$endRawSelect.' - '.$startRawSelect.')) * (coalesce(billable_rate, 0)::float/60/60))) as cost'
);
```

**Key insight**: Cost calculation uses `duration_seconds * (billable_rate_cents / 3600)`. This formula produces cost in **cents**. The budget service's cost-based consumption calculation will use the same formula.

### Grouping Types (lines 478-512)

```php
// /home/keven/Documents/solidtime-analysis/app/Service/TimeEntryAggregationService.php, lines 478-512
private function getGroupByQuery(TimeEntryAggregationType $group, string $timezone, Weekday $startOfWeek): string
{
    // Supports grouping by:
    // Day, Week, Month, Year (with timezone shift)
    // User (user_id)
    // Project (project_id)   <-- Budget reports use this
    // Task (task_id)
    // Client (client_id)     <-- Client-grouped budget reports use this
    // Billable (boolean)
    // Description
    // Tag (via LATERAL join)
}
```

The `TimeEntryAggregationType` enum (`/home/keven/Documents/solidtime-analysis/app/Enums/TimeEntryAggregationType.php`):

```php
enum TimeEntryAggregationType: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';
    case User = 'user';
    case Project = 'project';
    case Task = 'task';
    case Client = 'client';
    case Billable = 'billable';
    case Description = 'description';
    case Tag = 'tag';
}
```

### Tag Handling -- Anti-Double-Counting (lines 56-64, 166-181)

```php
// /home/keven/Documents/solidtime-analysis/app/Service/TimeEntryAggregationService.php, lines 56-64
if (($group1Type === TimeEntryAggregationType::Tag) || ($group2Type === TimeEntryAggregationType::Tag)) {
    $timeEntriesQuery->crossJoin(DB::raw(
        "LATERAL (\n".
        "  SELECT jsonb_array_elements_text(coalesce(tags, '[]'::jsonb)) AS tag\n".
        "  UNION ALL\n".
        "  SELECT ''::text AS tag WHERE coalesce(jsonb_array_length(tags), 0) = 0\n".
        ') AS tag(tag)'
    ));
}
```

When grouping by tag, entries with multiple tags appear in multiple groups. The service computes base totals from a separate non-expanded query (lines 166-181) to avoid double-counting in the overall sum. The budget service will not need tag grouping, so this complexity does not apply.

### Performance Characteristics

- **All queries use raw SQL** optimized for PostgreSQL.
- **Timezone shifts** are computed once as a seconds offset (line 480-486), then applied via SQL interval arithmetic.
- **Gap filling** is optional -- only used for time-series chart data (lines 400-476).
- **Running entries** handled via `coalesce("end", now())` in both start/end select expressions.

**Budget consumption queries will achieve similar performance** (< 100ms for 100k entries per project) since they follow the same raw SQL aggregation pattern.

### Relevance to Budget Reports

The `BudgetReportService` (BUD-026) can either:
1. **Extend** `TimeEntryAggregationService` by adding a budget-aware wrapper method, or
2. **Call** `getAggregatedTimeEntries()` with `group1Type = Project` and compare results against budget amounts.

Option 2 is cleaner since it reuses existing tested code. The `BudgetService::getConsumptionBatch()` (per AMD-09) should implement a single aggregation query similar to this service's pattern.

---

## 3. Time Entry Save Pipeline

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/TimeEntryController.php`

This is the most critical section for understanding where budget alert evaluation hooks in. Every time entry write operation dispatches `RecalculateSpentTimeForProject`.

### Create Flow (lines 600-616)

```php
// /home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/TimeEntryController.php, lines 600-616
$timeEntry = new TimeEntry;
$timeEntry->fill($request->validated());
$timeEntry->client()->associate($client);
$timeEntry->user_id = $member->user_id;
$timeEntry->description = $request->input('description') ?? '';
$timeEntry->organization()->associate($organization);
$timeEntry->setComputedAttributeValue('billable_rate');  // Uses BillableRateService cascade
$timeEntry->save();

if ($project !== null) {
    RecalculateSpentTimeForProject::dispatch($project);  // <-- HOOK POINT
}
if ($task !== null) {
    RecalculateSpentTimeForTask::dispatch($task);
}

return new TimeEntryResource($timeEntry);
```

### Update Flow (lines 626-679)

```php
// /home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/TimeEntryController.php, lines 647-677
$oldProject = $timeEntry->project;  // Track old project
$oldTask = $timeEntry->task;

// ... update logic ...
$timeEntry->fill($request->validated());
$timeEntry->setComputedAttributeValue('billable_rate');
$timeEntry->save();

if ($oldProject !== null) {
    RecalculateSpentTimeForProject::dispatch($oldProject);  // <-- HOOK POINT #1 (old project)
}
if ($oldTask !== null) {
    RecalculateSpentTimeForTask::dispatch($oldTask);
}
if ($project !== null && ($oldProject === null || $project->isNot($oldProject))) {
    RecalculateSpentTimeForProject::dispatch($project);     // <-- HOOK POINT #2 (new project)
}
```

**Important**: When a time entry moves between projects, **both** the old and new project are dispatched for recalculation. The budget alert service must evaluate both projects.

### Delete Flow (lines 789-811)

```php
// /home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/TimeEntryController.php, lines 789-811
public function destroy(Organization $organization, TimeEntry $timeEntry): JsonResponse
{
    // ... authorization checks ...
    $project = $timeEntry->project;
    $task = $timeEntry->task;

    $timeEntry->delete();

    if ($project !== null) {
        RecalculateSpentTimeForProject::dispatch($project);  // <-- HOOK POINT
    }
    if ($task !== null) {
        RecalculateSpentTimeForTask::dispatch($task);
    }

    return response()->json(null, 204);
}
```

**Deletion matters for budgets**: Deleting a time entry can bring consumption below a previously triggered threshold, requiring the alert to be reset so it can re-trigger later.

### Bulk Update Flow (lines 689-780)

```php
// /home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/TimeEntryController.php, lines 729-774
// Note: dispatches PER entry in loop, does NOT deduplicate
foreach ($ids as $id) {
    // ... update logic per entry ...
    if ($oldTask !== null) {
        RecalculateSpentTimeForTask::dispatch($oldTask);
    }
    if ($oldProject !== null) {
        RecalculateSpentTimeForProject::dispatch($oldProject);
    }
    if ($project !== null && ($oldProject === null || $project->isNot($oldProject))) {
        RecalculateSpentTimeForProject::dispatch($project);
    }
    // ...
}
```

**Observation**: The `updateMultiple` method dispatches jobs **per entry** inside the loop, unlike the agent's earlier analysis which stated deduplication happens. This means if 10 entries are updated for the same project, `RecalculateSpentTimeForProject` is dispatched 10 times. The queue worker will handle these sequentially, but the budget alert evaluation should be idempotent -- processing the same project multiple times must not send duplicate notifications. The `lockForUpdate()` pattern on the `budget_alerts` row (per AMD-08) handles this correctly.

### RecalculateSpentTimeForProject Job

**File**: `/home/keven/Documents/solidtime-analysis/app/Jobs/RecalculateSpentTimeForProject.php` (45 lines)

```php
// /home/keven/Documents/solidtime-analysis/app/Jobs/RecalculateSpentTimeForProject.php, lines 16-44
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

**Key implementation details**:
- `ShouldDispatchAfterCommit` -- only dispatched if the HTTP transaction commits successfully.
- `ShouldQueue` -- runs asynchronously via the queue worker.
- `SerializesModels` -- the `$project` Eloquent model is serialized by ID and re-fetched when the job runs.

**Budget alert evaluation will extend this job** (BUD-013):

```php
// Proposed modification to handle()
public function handle(BudgetAlertService $alertService): void
{
    $this->project->setComputedAttributeValue('spent_time');
    if ($this->project->isDirty()) {
        $this->project->save();
    }

    // NEW: Evaluate budget thresholds after spent_time update
    if ($this->project->budget_type !== null) {
        $alertService->evaluateProjectBudget($this->project);
    }
}
```

The alert evaluation is **synchronous within the queued job**. Only the notification dispatch is asynchronous (queued separately). This satisfies the "< 50ms added latency" target from AMD-08 since:
1. One DB query: `SELECT * FROM budget_alerts WHERE project_id = ? FOR UPDATE`
2. Consumption calculation: one aggregation query
3. Comparison: in-memory loop over threshold rows

### RecalculateSpentTimeForTask Job

**File**: `/home/keven/Documents/solidtime-analysis/app/Jobs/RecalculateSpentTimeForTask.php` (45 lines)

Identical pattern to the project job. No budget logic needed here -- budgets are per-project, not per-task.

---

## 4. DashboardService Patterns

**File**: `/home/keven/Documents/solidtime-analysis/app/Service/DashboardService.php` (466 lines)

### Architecture

The `DashboardService` is a stateless service injected via constructor DI. It depends on `TimezoneService` for user timezone resolution. All methods take `User` and `Organization` parameters and return plain arrays.

### Weekly Project Overview (lines 285-338)

This is the pattern most relevant to the budget dashboard widget:

```php
// /home/keven/Documents/solidtime-analysis/app/Service/DashboardService.php, lines 285-338
public function weeklyProjectOverview(User $user, Organization $organization): array
{
    $timezone = $this->timezoneService->getTimezoneFromUser($user);

    $query = TimeEntry::query()
        ->select(DB::raw('project_id, round(sum(extract(epoch from (coalesce("end", now()) - start)))) as aggregate'))
        ->where('user_id', '=', $user->getKey())
        ->where('organization_id', '=', $organization->getKey())
        ->groupBy('project_id');

    $query = $this->constrainDateByCurrentWeek($query, $timezone, $user->week_start);
    $entries = $query->get();

    // Load project metadata in batch (avoids N+1)
    $projectIds = $entries->pluck('project_id')->whereNotNull()->all();
    $projectsMap = Project::query()
        ->select(['id', 'name', 'color'])
        ->whereBelongsTo($organization, 'organization')
        ->whereIn('id', $projectIds)
        ->get()
        ->keyBy('id');

    // Map to response array
    $response = [];
    foreach ($entries as $entry) {
        $project = $projectsMap->get($entry->project_id);
        if ($project === null) {
            $aggregateOther += (int) $entry->aggregate;
            continue;
        }
        $response[] = [
            'value' => (int) $entry->aggregate,
            'id' => $entry->project_id,
            'name' => $project->name,
            'color' => $project->color,
        ];
    }
    return $response;
}
```

**Pattern for budget overview widget** (BUD-019): Same 2-query approach:
1. Aggregate consumption per project in a single SQL query
2. Load project metadata in batch via `whereIn`

### Total Weekly Billable Amount (lines 255-280)

```php
// /home/keven/Documents/solidtime-analysis/app/Service/DashboardService.php, lines 260-266
$query = TimeEntry::query()
    ->select(DB::raw('
       round(
            sum(
                extract(epoch from (coalesce("end", now()) - start)) * (billable_rate::float/60/60)
            )
       ) as aggregate'))
    ->where('billable', '=', true)
    ->whereNotNull('billable_rate')
```

**This is the exact SQL pattern for cost-based budget consumption**:
```sql
round(sum(
    extract(epoch from (coalesce("end", now()) - start))
    * (billable_rate::float/60/60)
))
```

This produces total cost in **cents**. For cost-based and fixed-fee budgets, this query (scoped to the project and optionally to the current month) gives the consumed amount.

### Chart Controller / API Endpoints

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/ChartController.php` (190 lines)

```php
// /home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/ChartController.php, lines 25-33
public function weeklyProjectOverview(Organization $organization, DashboardService $dashboardService): JsonResponse
{
    $this->checkPermission($organization, 'charts:view:own');
    $user = $this->user();
    $weeklyProjectOverview = $dashboardService->weeklyProjectOverview($user, $organization);
    return response()->json($weeklyProjectOverview);
}
```

**Chart routes** (`/home/keven/Documents/solidtime-analysis/routes/api.php`, lines 138-149):

```php
Route::name('charts.')->prefix('/organizations/{organization}/charts')->group(static function (): void {
    Route::get('/weekly-project-overview', [ChartController::class, 'weeklyProjectOverview'])->name('weekly-project-overview');
    Route::get('/latest-tasks', [ChartController::class, 'latestTasks'])->name('latest-tasks');
    Route::get('/last-seven-days', [ChartController::class, 'lastSevenDays'])->name('last-seven-days');
    Route::get('/latest-team-activity', [ChartController::class, 'latestTeamActivity'])->name('latest-team-activity');
    Route::get('/daily-tracked-hours', [ChartController::class, 'dailyTrackedHours'])->name('daily-tracked-hours');
    Route::get('/total-weekly-time', [ChartController::class, 'totalWeeklyTime'])->name('total-weekly-time');
    Route::get('/total-weekly-billable-time', [ChartController::class, 'totalWeeklyBillableTime'])->name('total-weekly-billable-time');
    Route::get('/total-weekly-billable-amount', [ChartController::class, 'totalWeeklyBillableAmount'])->name('total-weekly-billable-amount');
    Route::get('/weekly-history', [ChartController::class, 'weeklyHistory'])->name('weekly-history');
});
```

The budget overview chart (BUD-019) should be registered in this same route group as `charts/budget-overview`, or in a new `budgets` route group depending on whether it lives in `ChartController` or a new `BudgetChartController`.

### Dashboard Vue Page

**File**: `/home/keven/Documents/solidtime-analysis/resources/js/Pages/Dashboard.vue` (50 lines)

```html
<!-- /home/keven/Documents/solidtime-analysis/resources/js/Pages/Dashboard.vue, lines 37-47 -->
<MainContainer
    class="grid gap-2 sm:gap-4 grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 ...">
    <RecentlyTrackedTasksCard></RecentlyTrackedTasksCard>
    <LastSevenDaysCard></LastSevenDaysCard>
    <ActivityGraphCard></ActivityGraphCard>
    <TeamActivityCard v-if="canViewMembers()" class="flex lg:hidden xl:flex">
    </TeamActivityCard>
</MainContainer>
```

The `BudgetOverviewCard` component (BUD-024) will be added to this grid, conditionally rendered based on `canViewAllProjects()` permission check. It will use `@tanstack/vue-query` for data fetching (same pattern as existing cards) and invalidate on `queryClient.invalidateQueries({ queryKey: ['budgetOverview'] })`.

---

## 5. BillableRateService -- Cascade Pattern

**File**: `/home/keven/Documents/solidtime-analysis/app/Service/BillableRateService.php` (146 lines)

### Rate Resolution Cascade (lines 81-100)

```php
// /home/keven/Documents/solidtime-analysis/app/Service/BillableRateService.php, lines 81-100
public function getBillableRateForTimeEntryWithGivenRelations(
    TimeEntry $timeEntry,
    ?ProjectMember $projectMember,
    ?Project $project,
    ?Member $member,
    ?Organization $organization
): ?int {
    if (! $timeEntry->billable) {
        return null;
    }
    if ($projectMember !== null && $projectMember->billable_rate !== null) {
        return $projectMember->billable_rate;  // Level 1: ProjectMember rate
    }
    if ($project !== null && $project->billable_rate !== null) {
        return $project->billable_rate;        // Level 2: Project rate
    }
    if ($member !== null && $member->billable_rate !== null) {
        return $member->billable_rate;         // Level 3: Member rate
    }
    if ($organization !== null && $organization->billable_rate !== null) {
        return $organization->billable_rate;   // Level 4: Organization rate
    }
    return null;
}
```

**Four-level cascade**: ProjectMember > Project > Member > Organization. The first non-null `billable_rate` wins.

### How Rates Are Stored on TimeEntries

The `billable_rate` is a **computed attribute** on `TimeEntry`:

```php
// /home/keven/Documents/solidtime-analysis/app/Models/TimeEntry.php, lines 106-108
protected array $computed = [
    'billable_rate',
    'client_id',
];
```

```php
// /home/keven/Documents/solidtime-analysis/app/Models/TimeEntry.php, lines 120-123
public function getBillableRateComputed(): ?int
{
    return app(BillableRateService::class)->getBillableRateForTimeEntry($this);
}
```

When a time entry is created/updated, `$timeEntry->setComputedAttributeValue('billable_rate')` resolves the rate via the cascade and stores it directly on the time entry record. This means **the `billable_rate` column on `time_entries` already contains the resolved rate** -- the budget service does not need to re-resolve the cascade.

### Batch Rate Update Methods (lines 16-78)

The service also provides batch update methods that propagate rate changes:

```php
// Methods that bulk-update time entries when a rate changes
updateTimeEntriesBillableRateForProjectMember(ProjectMember $pm)  // Level 1 change
updateTimeEntriesBillableRateForProject(Project $project)          // Level 2 change
updateTimeEntriesBillableRateForMember(Member $member)             // Level 3 change
updateTimeEntriesBillableRateForOrganization(Organization $org)    // Level 4 change
```

Each method uses `whereDoesntHave()` clauses to only update entries that don't have a more specific rate. For example, `updateTimeEntriesBillableRateForProject` skips entries whose member has a `ProjectMember` rate:

```php
// /home/keven/Documents/solidtime-analysis/app/Service/BillableRateService.php, lines 25-40
public function updateTimeEntriesBillableRateForProject(Project $project): void
{
    TimeEntry::query()
        ->where('billable', '=', true)
        ->where('organization_id', '=', $project->organization_id)
        ->whereBelongsTo($project, 'project')
        ->whereDoesntHave('member', function (Builder $query) use ($project): void {
            $query->whereHas('projectMembers', function (Builder $query) use ($project): void {
                $query->whereBelongsTo($project, 'project')
                    ->whereNotNull('billable_rate');
            });
        })
        ->update(['billable_rate' => $project->billable_rate]);
}
```

**Impact on budget feature**: When a rate changes, existing time entry `billable_rate` values are bulk-updated. This changes the cost-based budget consumption retroactively. The `RecalculateSpentTimeForProject` job should also re-evaluate budget alerts when rates change. However, the current rate-change flow in `ProjectController::update` (line 146-148) calls `billableRateService->updateTimeEntriesBillableRateForProject()` but does **not** dispatch `RecalculateSpentTimeForProject`. This is a potential gap -- a rate change can alter cost-based budget consumption without triggering alert re-evaluation. Consider dispatching the recalculation job after rate updates.

---

## 6. Scheduled Command Patterns

**File**: `/home/keven/Documents/solidtime-analysis/app/Console/Kernel.php` (59 lines)

### Current Schedule (lines 15-50)

```php
// /home/keven/Documents/solidtime-analysis/app/Console/Kernel.php, lines 15-50
protected function schedule(Schedule $schedule): void
{
    $schedule->command('time-entry:send-still-running-mails')
        ->when(fn (): bool => config('scheduling.tasks.time_entry_send_still_running_mails'))
        ->everyTenMinutes();

    $schedule->command('auth:send-mails-expiring-api-tokens')
        ->when(fn (): bool => config('scheduling.tasks.auth_send_mails_expiring_api_tokens'))
        ->everyTenMinutes();

    if (config('app.key') && (config('scheduling.tasks.self_hosting_check_for_update') || config('scheduling.tasks.self_hosting_telemetry'))) {
        // Deterministic random offset based on app key (avoids all instances hitting API at once)
        $seed = hexdec(substr(hash('md5', config('app.key')), 0, 8));
        // ... compute firstHour, secondHour, minuteOffset ...

        $schedule->command('self-host:check-for-update')
            ->twiceDailyAt($firstHour, $secondHour, $minuteOffset);

        $schedule->command('self-host:telemetry')
            ->twiceDailyAt($firstHour, $secondHour, $minuteOffset);
    }

    $schedule->command('self-host:database-consistency')
        ->when(fn (): bool => config('scheduling.tasks.self_hosting_database_consistency'))
        ->everySixHours();
}
```

**Pattern observations**:
1. Each command is **config-gated** via `->when(fn() => config('scheduling.tasks.xxx'))`.
2. Commands are registered by artisan signature string (`'time-entry:send-still-running-mails'`).
3. All commands live in `/home/keven/Documents/solidtime-analysis/app/Console/Commands/` subdirectories.

### Existing Commands

```
app/Console/Commands/
  Auth/AuthSendReminderForExpiringApiTokensCommand.php
  TimeEntry/TimeEntrySendStillRunningMailsCommand.php
  SelfHost/SelfHostCheckForUpdateCommand.php
  SelfHost/SelfHostTelemetryCommand.php
  SelfHost/SelfHostDatabaseConsistency.php
  SelfHost/SelfHostGenerateKeysCommand.php
  Report/ReportSetExpiredToPrivateCommand.php
  Correction/CorrectionPlaceholderMembersCommand.php
  Admin/UserCreateCommand.php
  Admin/UserVerifyCommand.php
  Admin/OrganizationDeleteCommand.php
  Test/TestOutputCommand.php
  Test/TestJobCommand.php
  Test/TestEmailCommand.php
```

### Example Command: TimeEntrySendStillRunningMailsCommand

**File**: `/home/keven/Documents/solidtime-analysis/app/Console/Commands/TimeEntry/TimeEntrySendStillRunningMailsCommand.php` (76 lines)

```php
// /home/keven/Documents/solidtime-analysis/app/Console/Commands/TimeEntry/TimeEntrySendStillRunningMailsCommand.php
class TimeEntrySendStillRunningMailsCommand extends Command
{
    protected $signature = 'time-entry:send-still-running-mails '.
        ' { --dry-run : Do not actually send emails or save anything to the database, just output what would happen }';

    protected $description = 'Sends emails to users who have running time entries for more than 8 hours.';

    public function handle(): int
    {
        $this->comment('Sending still running time entry emails...');
        $dryRun = (bool) $this->option('dry-run');

        TimeEntry::query()
            ->whereNull('end')
            ->where('start', '<', now()->subHours(8))
            ->whereNull('still_active_email_sent_at')
            ->with(['user'])
            ->chunk(500, function (Collection $timeEntries) use ($dryRun, &$sentMails): void {
                foreach ($timeEntries as $timeEntry) {
                    if (! $dryRun) {
                        Mail::to($user->email)
                            ->queue(new TimeEntryStillRunningMail($timeEntry, $user));
                        $timeEntry->still_active_email_sent_at = Carbon::now();
                        $timeEntry->save();
                    }
                }
            });

        return self::SUCCESS;
    }
}
```

**Pattern for BUD-017 (MonthlyBudgetResetCommand)**:

```php
// Proposed: app/Console/Commands/Budget/BudgetMonthlyResetCommand.php
class BudgetMonthlyResetCommand extends Command
{
    protected $signature = 'budget:monthly-reset { --dry-run }';

    public function handle(): int
    {
        // Reset all triggered alerts on projects with monthly budgets
        BudgetAlert::query()
            ->whereHas('project', fn ($q) => $q->where('budget_period', 'monthly'))
            ->whereNotNull('triggered_at')
            ->chunk(500, function ($alerts) { ... });

        return self::SUCCESS;
    }
}
```

Schedule registration in Kernel.php:
```php
$schedule->command('budget:monthly-reset')
    ->when(fn (): bool => config('scheduling.tasks.budget_monthly_reset'))
    ->monthlyOn(1, '00:00');  // First of every month at midnight
```

---

## 7. EstimatedTimeProgress Component

### EstimatedTimeProgress.vue

**File**: `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/EstimatedTimeProgress.vue` (35 lines)

```vue
<!-- /home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/EstimatedTimeProgress.vue -->
<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ estimated: number; current: number }>();
function formatHours(seconds: number) {
    return Math.round(seconds / 60 / 60) + 'h';
}

const isOverEstimate = computed(() => props.current > props.estimated);
const progressBarPercentage = computed(() => {
    return (props.current / props.estimated) * 100;
});
const formattedProgressBarPercentage = computed(() => {
    return progressBarPercentage.value.toFixed(1) + '%';
});
</script>
<template>
    <div class="w-full">
        <div class="bg-tertiary h-1 rounded relative overflow-hidden w-full">
            <div
                class="h-full"
                :class="{
                    'bg-accent-200': !isOverEstimate,
                    'bg-red-500': isOverEstimate,
                }"
                :style="{ width: progressBarPercentage + '%' }"></div>
        </div>
        <div class="text-xs font-semibold pt-1.5">
            {{ formattedProgressBarPercentage }} of
            {{ formatHours(estimated) }}
        </div>
    </div>
</template>
```

**Analysis**:
- Takes `estimated` (seconds) and `current` (seconds) as props.
- Computes percentage and formats as `X.X%`.
- Binary color coding: `bg-accent-200` (not over) or `bg-red-500` (over).
- Formats estimated as hours only (`Xh`).
- **No yellow/orange intermediate states** -- budget UI requires 4 color bands (green/yellow/orange/red per PRD section 3.4).

### Usage in ProjectTableRow.vue

**File**: `/home/keven/Documents/solidtime-analysis/resources/js/Components/Common/Project/ProjectTableRow.vue` (lines 106-111)

```vue
<!-- /home/keven/Documents/solidtime-analysis/resources/js/Components/Common/Project/ProjectTableRow.vue, lines 105-112 -->
<div class="whitespace-nowrap px-3 flex items-center text-sm text-text-secondary">
    <UpgradeBadge v-if="!isAllowedToPerformPremiumAction()"></UpgradeBadge>
    <EstimatedTimeProgress
        v-else-if="project.estimated_time"
        :estimated="project.estimated_time"
        :current="project.spent_time"></EstimatedTimeProgress>
    <span v-else> -- </span>
</div>
```

**Coexistence strategy (AMD-07)**: When a project has both `estimated_time` and a `budget_type = 'hours'` budget:
- The `BudgetProgressBar` component replaces `EstimatedTimeProgress` in this slot.
- `estimated_time` becomes a secondary field, not displayed when a budget exists.
- The conditional rendering will change from `v-else-if="project.estimated_time"` to a check for `project.budget?.type` first.

### EstimatedTimeSection.vue (Budget Input)

**File**: `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/EstimatedTimeSection.vue` (23 lines)

```vue
<!-- /home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/EstimatedTimeSection.vue -->
<script setup lang="ts">
import DurationInput from '@/packages/ui/src/Input/DurationInput.vue';
import { ClockIcon } from '@heroicons/vue/20/solid';
import InputLabel from '@/packages/ui/src/Input/InputLabel.vue';

const model = defineModel<number | null>();
const emit = defineEmits(['submit']);
</script>

<template>
    <div class="pt-6">
        <div class="flex items-center space-x-1 mb-2">
            <ClockIcon class="text-text-quaternary w-4"></ClockIcon>
            <InputLabel for="billable" value="Time Estimated" />
        </div>
        <DurationInput
            v-model="model"
            class="max-w-[150px]"
            @submit="emit('submit')"></DurationInput>
    </div>
</template>
```

Used in `ProjectEditModal.vue` (line 137-140) and `ProjectCreateModal.vue` (line 131-134), gated behind `isAllowedToPerformPremiumAction()`. The budget section (BUD-022) will be placed in the same area of the project edit form, potentially replacing or appearing alongside this section.

---

## 8. Project Edit UI

### ProjectEditModal.vue

**File**: `/home/keven/Documents/solidtime-analysis/resources/js/Components/Common/Project/ProjectEditModal.vue` (164 lines)

**Structure**:
```vue
<DialogModal>
  <template #title>Edit Project {{ name }}</template>
  <template #content>
    <!-- Row 1: Color selector + Project name + Client dropdown -->
    <!-- Row 2: Billable section (is_billable toggle + billable_rate) -->
    <!-- Row 3: EstimatedTimeSection (v-if premium) -->
  </template>
  <template #footer>Cancel + Update Project buttons</template>
</DialogModal>
<ProjectBillableRateModal>  <!-- Confirmation modal when rate changes -->
```

**Form data model** (lines 36-43):
```typescript
const project = ref<CreateProjectBody>({
    name: props.originalProject.name,
    color: props.originalProject.color,
    client_id: props.originalProject.client_id,
    billable_rate: props.originalProject.billable_rate,
    is_billable: props.originalProject.is_billable,
    estimated_time: props.originalProject.estimated_time,
});
```

**Budget fields will extend this model** with:
```typescript
budget_type: props.originalProject.budget?.type ?? null,
budget_amount: props.originalProject.budget?.amount ?? null,
budget_currency_code: props.originalProject.budget?.currency_code ?? null,
budget_period: props.originalProject.budget?.period ?? null,
```

**Submit flow** (lines 45-55): Calls `useProjectsStore().updateProject(id, body)` which hits the `PUT /api/v1/organizations/{org}/projects/{project}` endpoint.

**Estimated time gating** (lines 137-140):
```vue
<EstimatedTimeSection
    v-if="isAllowedToPerformPremiumAction()"
    v-model="project.estimated_time"
    @submit="submit()"></EstimatedTimeSection>
```

The budget section will be placed after this, also gated behind `isAllowedToPerformPremiumAction()`.

### ProjectController -- Store/Update

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/ProjectController.php`

**Premium feature gating pattern** (lines 106-108, 134-136):
```php
// /home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/ProjectController.php, lines 106-108
if ($this->canAccessPremiumFeatures($organization) && $request->has('estimated_time')) {
    $project->estimated_time = $request->getEstimatedTime();
}
```

**`canAccessPremiumFeatures` implementation** (`/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/Controller.php`, lines 48-51):
```php
protected function canAccessPremiumFeatures(Organization $organization): bool
{
    return app(BillingContract::class)->hasSubscription($organization) || app(BillingContract::class)->hasTrial($organization);
}
```

Budget fields will follow the same gating pattern:
```php
if ($this->canAccessPremiumFeatures($organization) && $request->has('budget_type')) {
    $project->budget_type = $request->input('budget_type');
    $project->budget_amount = $request->input('budget_amount');
    // ...
}
```

### ProjectResource -- API Response

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Resources/V1/Project/ProjectResource.php` (55 lines)

```php
// /home/keven/Documents/solidtime-analysis/app/Http/Resources/V1/Project/ProjectResource.php, lines 30-54
public function toArray(Request $request): array
{
    return [
        'id' => $this->resource->id,
        'name' => $this->resource->name,
        'color' => $this->resource->color,
        'client_id' => $this->resource->client_id,
        'is_archived' => $this->resource->is_archived,
        'billable_rate' => $this->showBillableRate ? $this->resource->billable_rate : null,
        'is_billable' => $this->resource->is_billable,
        'estimated_time' => $this->resource->estimated_time,
        'spent_time' => $this->resource->spent_time,
        'is_public' => $this->resource->is_public,
    ];
}
```

BUD-008 will extend this resource with a `budget` nested object:
```php
'budget' => $this->resource->hasBudget() ? [
    'type' => $this->resource->budget_type->value,
    'amount' => $this->resource->budget_amount,
    'currency_code' => $this->resource->budget_currency_code ?? $this->resource->organization->currency,
    'period' => $this->resource->budget_period->value,
    'consumed' => $budgetData['consumed'],
    'consumed_including_running' => $budgetData['consumed_including_running'],
    'percentage' => $budgetData['percentage'],
    'remaining' => $budgetData['remaining'],
    'is_over_budget' => $budgetData['percentage'] >= 100,
] : null,
```

---

## 9. Notification Infrastructure Gap

### Current State: No Notification System

**Confirmed gaps**:

1. **No `App\Notifications\` directory** -- Zero notification classes exist in the application.
2. **No `notifications` table migration** -- The database has no `notifications` table. Searched all migrations in `/home/keven/Documents/solidtime-analysis/database/migrations/` with no results.
3. **No notification bell UI** -- The `Dashboard.vue` and `AppLayout.vue` have no notification component.
4. **No notification API endpoints** -- No routes for listing, reading, or counting notifications.
5. **No `BaseNotification` class** -- Must be created as part of FOUND-002.

### What Does Exist

- **`User` model uses `Notifiable` trait** (`/home/keven/Documents/solidtime-analysis/app/Models/User.php`, line 74):
  ```php
  use Notifiable;
  ```
  This means `$user->notify(new SomeNotification())` will work once the `notifications` table is created and notification classes are built.

- **`SendEmailVerificationNotification`** is registered in `EventServiceProvider` for the `Registered` event (line 22). This is Laravel's built-in email verification, not a custom notification.

- **Mail system is operational** -- The `TimeEntrySendStillRunningMailsCommand` uses `Mail::to()->queue()` (line 64-65). Email sending via queued mailables works. The notification system will use the `mail` channel which internally uses the same mail infrastructure.

### Required Foundation Tasks (from SF-04 in SHARED-FOUNDATIONS.md)

| Task | Description | Effort |
|------|-------------|--------|
| FOUND-001 | Create `notifications` table migration + `notification_preferences` on members | 2h |
| FOUND-002 | Create `App\Notifications\BaseNotification` class | 4h |
| FOUND-003 | Create `NotificationBell.vue` in AppLayout header | 8h |
| FOUND-004 | Create notification API endpoints (list, mark read, unread count) | 6h |
| FOUND-005 | Add notification preferences to organization settings | 4h |

**Total: 24 hours of shared foundation work** before any feature can use in-app notifications.

### Budget-Specific Notifications

After FOUND-001 through FOUND-005 are complete, the budget feature creates:

- `App\Notifications\BudgetThresholdNotification` -- sent when consumption crosses a configured threshold (50%, 80%, etc.)
- `App\Notifications\BudgetExceededNotification` -- sent when consumption reaches 100%

Both extend `App\Notifications\BaseNotification` and use `database` + `mail` channels. Per AMD-04, the in-app notification bell UI is provided by FOUND-003, not built within this feature.

---

## 10. Permission System

### Current Permission Registration

**File**: `/home/keven/Documents/solidtime-analysis/app/Providers/JetstreamServiceProvider.php` (lines 78-277)

Permissions are registered via `Jetstream::role()` calls. Each role gets an explicit array of permission strings.

**Role hierarchy for budget-relevant permissions**:

| Role | `projects:view` | `projects:view:all` | `projects:update` |
|------|:---:|:---:|:---:|
| Owner | Yes | Yes | Yes |
| Admin | Yes | Yes | Yes |
| Manager | Yes | Yes | Yes |
| Employee | Yes | No | No |
| Placeholder | No | No | No |

**New permissions needed** (per AMD-02, AMD-05):
- `budgets:view` -- view budget data on projects the user can see
- `budgets:update` -- create/edit/remove budgets
- `budget-alerts:manage` -- configure alert thresholds

Per AMD-05, permissions are registered via `App\Permissions\BudgetPermissions::register()` instead of modifying `JetstreamServiceProvider` directly. However, the current codebase has no `App\Permissions\` directory -- this is a new pattern introduced by the feature.

### Permission Check Pattern

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/Controller.php` (52 lines)

```php
// /home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/Controller.php, lines 21-26
protected function checkPermission(Organization $organization, string $permission): void
{
    if (! $this->permissionStore->has($organization, $permission)) {
        throw new AuthorizationException;
    }
}
```

Budget controllers will use the same pattern:
```php
$this->checkPermission($organization, 'budgets:view');
$this->checkPermission($organization, 'budgets:update');
$this->checkPermission($organization, 'budget-alerts:manage');
```

---

## 11. Risk Assessment

### Risk 1: `estimated_time` Coexistence

**Severity**: Medium
**Mitigation**: AMD-07

The `estimated_time` field (integer seconds, nullable) will coexist with `budget_type = 'hours'` budgets. Both represent "how much time is allocated" but in different formats:

- `estimated_time`: simple seconds value, no period, no alerts
- Budget `hours` type: seconds value with period (total/monthly), alerts, forecasting

**UI confusion risk**: When both exist, the `EstimatedTimeProgress` component shows `estimated_time` while the budget system shows `budget_amount`. The AMD-07 strategy is:
1. When a budget exists, display budget data (hide `estimated_time`).
2. When no budget exists, fall back to `estimated_time` display.
3. Future migration task to migrate `estimated_time` values to budget format.

**Implementation note**: The `ProjectTableRow.vue` rendering logic (lines 105-112) must change from:
```vue
<EstimatedTimeProgress v-else-if="project.estimated_time" ... />
```
To:
```vue
<BudgetProgressBar v-else-if="project.budget" ... />
<EstimatedTimeProgress v-else-if="project.estimated_time" ... />
```

### Risk 2: Alert Evaluation Performance on Time Entry Save

**Severity**: Low
**Mitigation**: AMD-08

Alert evaluation runs **inside the queued `RecalculateSpentTimeForProject` job**, not in the HTTP request. The synchronous portion of the HTTP request is unaffected. The job adds:
1. One `SELECT ... FOR UPDATE` query on `budget_alerts` (~1-5ms)
2. One aggregation query for consumption (~5-50ms depending on entry count)
3. In-memory comparison loop (~< 1ms)

Total added latency to the queued job: **< 50ms** for projects with up to 100k entries.

### Risk 3: Duplicate Alert Notifications from Bulk Operations

**Severity**: Medium
**Mitigation**: `lockForUpdate()` + idempotent check

The `updateMultiple` endpoint dispatches `RecalculateSpentTimeForProject` per entry (not deduplicated). For 10 entries on the same project, the job runs 10 times. The `lockForUpdate()` on the alert row prevents concurrent processing, and the `triggered_at IS NULL` check prevents re-notification for already-triggered thresholds. However, the queue may process these sequentially, and the first job triggers the alert. Subsequent jobs see `triggered_at IS NOT NULL` and skip. This is correct behavior but generates unnecessary job executions.

**Potential optimization**: Add a `ShouldBeUnique` interface to the recalculation job keyed by `project_id`, so only one instance runs per project regardless of how many times it's dispatched.

### Risk 4: Rate Changes Without Budget Re-evaluation

**Severity**: Medium
**Mitigation**: Needs new dispatch point

When a project's `billable_rate` changes (`ProjectController::update`, line 146-148):
```php
if ($oldBillableRate !== $request->getBillableRate()) {
    $billableRateService->updateTimeEntriesBillableRateForProject($project);
}
```

This bulk-updates `billable_rate` on all affected time entries but does **not** dispatch `RecalculateSpentTimeForProject`. For cost-based budgets, the consumption changes retroactively. An alert that was at 78% might now be at 82% after a rate increase, but no alert evaluation runs.

**Recommendation**: Dispatch `RecalculateSpentTimeForProject` after rate updates when the project has a cost-based or fixed-fee budget.

### Risk 5: No Notification Infrastructure

**Severity**: High (blocking)
**Mitigation**: FOUND-001 through FOUND-005 must complete first

The budget alert notifications (BUD-014, BUD-015) cannot be implemented until the shared notification infrastructure is built. This is a hard dependency for Sprint 2 tasks.

---

## 12. File Reference Lists

### Files to Modify

| File | Modification |
|------|-------------|
| `/home/keven/Documents/solidtime-analysis/app/Models/Project.php` | Add budget properties, casts, `budgetAlerts()` relationship, `hasBudget()` accessor |
| `/home/keven/Documents/solidtime-analysis/app/Http/Resources/V1/Project/ProjectResource.php` | Add `budget` nested object to response |
| `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/ProjectController.php` | Add budget field handling in `store()` and `update()` |
| `/home/keven/Documents/solidtime-analysis/app/Jobs/RecalculateSpentTimeForProject.php` | Add budget alert evaluation call |
| `/home/keven/Documents/solidtime-analysis/app/Console/Kernel.php` | Register monthly budget reset command |
| `/home/keven/Documents/solidtime-analysis/app/Providers/JetstreamServiceProvider.php` | Add budget permissions to roles (or use modular registration per AMD-05) |
| `/home/keven/Documents/solidtime-analysis/resources/js/Components/Common/Project/ProjectEditModal.vue` | Add budget section fields |
| `/home/keven/Documents/solidtime-analysis/resources/js/Components/Common/Project/ProjectTableRow.vue` | Replace EstimatedTimeProgress with BudgetProgressBar when budget exists |
| `/home/keven/Documents/solidtime-analysis/resources/js/Pages/Dashboard.vue` | Add BudgetOverviewCard |
| `/home/keven/Documents/solidtime-analysis/routes/api.php` | Add budget endpoints |
| `/home/keven/Documents/solidtime-analysis/routes/web.php` | Add budget report Inertia route (AMD-11) |
| `/home/keven/Documents/solidtime-analysis/resources/js/Layouts/AppLayout.vue` | Add budget report navigation item |

### Files to Create

| File | Purpose |
|------|---------|
| `app/Enums/BudgetType.php` | Budget type enum (hours, cost, fixed_fee) |
| `app/Enums/BudgetPeriod.php` | Budget period enum (total, monthly) |
| `database/migrations/2026_03_03_000001_add_budget_columns_to_projects_table.php` | Add budget columns to projects |
| `database/migrations/2026_03_03_000002_create_budget_alerts_table.php` | Create budget_alerts table |
| `app/Models/BudgetAlert.php` | BudgetAlert Eloquent model |
| `database/factories/BudgetAlertFactory.php` | Factory for BudgetAlert |
| `app/Service/BudgetService.php` | Consumption calculation, batch queries |
| `app/Service/BudgetAlertService.php` | Threshold evaluation, trigger/reset logic |
| `app/Service/BudgetForecastService.php` | Burn rate and exhaustion date calculation |
| `app/Http/Controllers/Api/V1/BudgetController.php` | Budget status, alerts CRUD |
| `app/Http/Controllers/Api/V1/BudgetChartController.php` | Budget overview chart endpoint |
| `app/Http/Controllers/Api/V1/BudgetReportController.php` | Budget report endpoint |
| `app/Http/Requests/V1/Budget/BudgetAlertUpdateRequest.php` | Validation for alert updates |
| `app/Http/Requests/V1/Budget/BudgetReportRequest.php` | Validation for report filters |
| `app/Notifications/BudgetThresholdNotification.php` | Threshold crossing notification |
| `app/Notifications/BudgetExceededNotification.php` | 100% exceeded notification |
| `app/Console/Commands/Budget/BudgetMonthlyResetCommand.php` | Monthly alert reset command |
| `app/Permissions/BudgetPermissions.php` | Modular permission registration (AMD-05) |
| `resources/js/packages/ui/src/Budget/BudgetProgressBar.vue` | 4-color budget progress bar |
| `resources/js/packages/ui/src/Budget/BudgetSection.vue` | Budget config section for project edit |
| `resources/js/packages/ui/src/Budget/BudgetOverviewCard.vue` | Dashboard budget overview card |
| `resources/js/packages/ui/src/Budget/BudgetForecast.vue` | Forecast display component |
| `resources/js/Pages/BudgetReport.vue` | Budget report page |
| `resources/js/utils/useBudgets.ts` | Pinia store for budget data fetching |
| `tests/Unit/Service/BudgetServiceTest.php` | Service unit tests |
| `tests/Unit/Service/BudgetAlertServiceTest.php` | Alert service unit tests |
| `tests/Unit/Service/BudgetForecastServiceTest.php` | Forecast service unit tests |
| `tests/Unit/Endpoint/Api/V1/BudgetEndpointTest.php` | API endpoint tests |

### Files to Read (Reference Only)

| File | Why |
|------|-----|
| `/home/keven/Documents/solidtime-analysis/app/Service/TimeEntryAggregationService.php` | SQL aggregation patterns to mirror |
| `/home/keven/Documents/solidtime-analysis/app/Service/DashboardService.php` | Dashboard widget patterns |
| `/home/keven/Documents/solidtime-analysis/app/Service/BillableRateService.php` | Rate cascade to understand cost computation |
| `/home/keven/Documents/solidtime-analysis/app/Models/TimeEntry.php` | Computed attribute pattern, `billable_rate` storage |
| `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/TimeEntryController.php` | Job dispatch points for alert hooks |
| `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/ChartController.php` | Chart endpoint patterns |
| `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/Controller.php` | Permission check and premium gating |
| `/home/keven/Documents/solidtime-analysis/app/Console/Kernel.php` | Scheduling pattern for monthly reset |
| `/home/keven/Documents/solidtime-analysis/app/Console/Commands/TimeEntry/TimeEntrySendStillRunningMailsCommand.php` | Command structure pattern |
| `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/EstimatedTimeProgress.vue` | Current progress bar to extend/replace |
| `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/EstimatedTimeSection.vue` | Estimated time input pattern |
| `/home/keven/Documents/solidtime-analysis/resources/js/Components/Common/Project/ProjectTableRow.vue` | Project list row where budget bar appears |
| `/home/keven/Documents/solidtime-analysis/resources/js/Components/Common/Project/ProjectEditModal.vue` | Project edit form to extend |
| `/home/keven/Documents/solidtime-analysis/resources/js/Pages/Dashboard.vue` | Dashboard layout for widget placement |
| `/home/keven/Documents/solidtime-analysis/database/migrations/2024_07_02_134307_add_estimated_time_to_projects_and_tasks_table.php` | estimated_time migration pattern |
| `/home/keven/Documents/solidtime-analysis/database/migrations/2024_09_18_120203_add_spent_time_to_projects_and_tasks_table.php` | spent_time migration pattern |
| `/home/keven/Documents/solidtime-analysis/database/migrations/2024_01_20_110439_create_projects_table.php` | Original projects schema |
| `/home/keven/Documents/solidtime-analysis/app/Providers/JetstreamServiceProvider.php` | Permission registration patterns |
| `/home/keven/Documents/solidtime-analysis/.features/SHARED-FOUNDATIONS.md` | Cross-feature decisions (notifications, permissions, migrations) |
