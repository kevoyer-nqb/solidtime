Perfect! Now I have a comprehensive understanding of the existing reporting infrastructure. Let me compile the deep analysis report.

## Deep Codebase Analysis: Feature 09 - Advanced Reporting

Based on my comprehensive trace through the Solidtime codebase, here's my deep analysis of the existing infrastructure and how it relates to the Advanced Reporting feature.

---

## 1. ReportController — Store/Show/Destroy Methods & ReportPropertiesDto

### Entry Points & Files
- **Controller**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/ReportController.php` (lines 1-174)
- **Model**: `/home/keven/Documents/solidtime-analysis/app/Models/Report.php` (lines 1-64)
- **DTO**: `/home/keven/Documents/solidtime-analysis/app/Service/Dto/ReportPropertiesDto.php` (lines 1-225)
- **Service**: `/home/keven/Documents/solidtime-analysis/app/Service/ReportService.php` (lines 1-15)

### Store Method Flow (lines 76-123)
```php
ReportController::store()
├─ checkPermission($organization, 'reports:create')  // Line 78
├─ Build Report model with name, description, is_public
├─ Create ReportPropertiesDto from request properties:
│  ├─ group/subGroup: TimeEntryAggregationType enum
│  ├─ historyGroup: TimeEntryAggregationTypeInterval enum  
│  ├─ start/end: Carbon dates in ISO 8601 Zulu format
│  ├─ active, billable: nullable booleans
│  ├─ memberIds, clientIds, projectIds, tagIds, taskIds: Collections of UUIDs
│  ├─ weekStart: Weekday enum (defaults to user preference)
│  ├─ timezone: validated string (with legacy timezone mapping)
│  └─ roundingType/roundingMinutes: nullable for time rounding
├─ Generate share_secret if is_public (via ReportService::generateSecret())
└─ Associate with organization and save
```

### ReportPropertiesDto Structure
The DTO uses a custom `Castable` interface (lines 18-164) with:
- **Required fields**: All 12 filter/grouping properties
- **Storage**: JSON serialization to `properties` JSONB column
- **Casting**: Bidirectional conversion between DTO and JSON
- **Validation**: `idArrayToCollection()` ensures UUIDs are valid (lines 170-183)

### Show/Destroy Methods
- **Show** (lines 62-66): Simple permission check + DetailedReportResource response
- **Destroy** (lines 166-173): Permission check + cascade delete (will cascade to schedules when added)

### Current Limitations for Advanced Reporting
1. **No cost_rate tracking**: Properties only include billable_rate, no cost data
2. **No template support**: Reports are tied to specific data/dates, not reusable configurations
3. **No schedule support**: No recurring execution or email delivery
4. **No custom column selection**: Export includes all fields

---

## 2. TimeEntryAggregationService — Core Engine

### Location & Structure
**File**: `/home/keven/Documents/solidtime-analysis/app/Service/TimeEntryAggregationService.php` (lines 1-551)

### Primary Method: `getAggregatedTimeEntries()` (lines 47-199)

#### SQL Aggregation Pattern (lines 77-82)
```sql
SELECT 
  {group1_expression} as group_1,
  {group2_expression} as group_2,
  round(sum(extract(epoch from (end - start)))) as aggregate,  -- Total seconds
  round(sum(extract(epoch from (end - start)) * (coalesce(billable_rate, 0)::float/60/60))) as cost  -- Cents
GROUP BY group_1, group_2
ORDER BY group_1, group_2
```

**Key Insight**: The `cost` field is computed as `duration_hours * billable_rate_cents_per_hour`, already in cents. This is the **revenue** (not internal cost) for billable entries.

#### Supported Grouping Types (enum at lines 9-23)
11 aggregation types from `/home/keven/Documents/solidtime-analysis/app/Enums/TimeEntryAggregationType.php`:
- **Time-based**: Day, Week, Month, Year (with timezone shifting)
- **Entity-based**: User, Project, Task, Client, Description, Billable, Tag

#### Tag Handling — Cross-Join Pattern (lines 56-63)
```sql
LATERAL (
  SELECT jsonb_array_elements_text(coalesce(tags, '[]'::jsonb)) AS tag
  UNION ALL
  SELECT ''::text AS tag WHERE coalesce(jsonb_array_length(tags), 0) = 0
) AS tag(tag)
```
This expands each time entry into multiple rows (one per tag), plus a NULL row for tagless entries. To avoid double-counting, base totals are recalculated from the non-expanded query (lines 102-120, 166-181).

#### Gap Filling (lines 183-184, 400-476)
When `fillGapsInTimeGroups=true` and date range provided:
- Generates time slots using `timeSlotsBetween()` (lines 517-550)
- Fills missing periods with zero-value entries
- Ensures continuous time series for charting

### Performance Characteristics
- **Indexes**: Relies on `time_entries` indexes for `organization_id`, `start`, `member_id`, `project_id`, `billable` (added in migration 2026_02_06_000001_add_timesheet_indexes)
- **Chunking**: No explicit chunking; entire result set loaded into memory
- **N+1 Prevention**: `loadDescriptorsMap()` (lines 295-370) batch-loads entity names/colors after aggregation

---

## 3. Export Pipeline — ExportService, Maatwebsite Excel, Gotenberg PDF

### Full Organization Export
**File**: `/home/keven/Documents/solidtime-analysis/app/Service/Export/ExportService.php` (lines 1-389)

**Flow**:
1. Creates temporary directory
2. Exports 9 CSV files using `League\Csv\Writer`:
   - organizations, members, time_entries, clients, projects, project_members, tasks, tags, organization_invitations
3. Creates `meta.json` with export metadata
4. Zips all files using `ZipArchive`
5. Uploads to private storage (filesystems.private disk)
6. Returns storage path: `exports/export_{org}_{timestamp}_{uuid}.zip`

**Pattern**: Chunked writes (1000 records at a time) to avoid memory exhaustion

### Report-Specific Export
**Files**:
- **Detailed**: `/home/keven/Documents/solidtime-analysis/app/Service/ReportExport/TimeEntriesDetailedExport.php` (lines 1-153)
- **Aggregated**: `/home/keven/Documents/solidtime-analysis/app/Service/ReportExport/TimeEntriesReportExport.php` (lines 1-104)

#### TimeEntriesDetailedExport (Maatwebsite Excel)
Implements `FromQuery`, `WithHeadings`, `WithMapping`, `ShouldAutoSize`, `WithColumnFormatting`, `WithStyles`:
- **Columns** (lines 98-110): Description, Task, Project, Client, User, Start, End, Duration, Duration (decimal), Billable, Tags
- **Format Handling**: XLSX uses Excel date format (line 128), ODS uses string dates (line 142)
- **Query-Based**: Streams results directly from Eloquent builder (no eager loading all rows)

#### TimeEntriesReportExport (Blade Template)
Uses view: `/home/keven/Documents/solidtime-analysis/resources/views/reports/time-entry-aggregate/spreadsheet.blade.php`
- Renders two-level grouped data with subtotals
- Cost column shows `BigDecimal::ofUnscaledValue($cost, 2)` (divides cents by 100)
- Supports CSV, XLSX, ODS via blade conditionals

### Gotenberg PDF Integration
**Config**: `/home/keven/Documents/solidtime-analysis/config/services.php` (lines 6-10)
```php
'gotenberg' => [
    'url' => env('GOTENBERG_URL'),
    'basic_auth_username' => env('GOTENBERG_BASIC_AUTH_USERNAME'),
    'basic_auth_password' => env('GOTENBERG_BASIC_AUTH_PASSWORD'),
],
```
**Usage**: ExportFormat::PDF maps to `Excel::MPDF` (line 30 in ExportFormat enum), which likely uses Gotenberg or mPDF under the hood via Maatwebsite Excel.

**PDF Templates**: Found at `resources/views/reports/time-entry-aggregate/pdf.blade.php` and `pdf-footer.blade.php` (from grep results).

### Signed URL Pattern
Currently uses private storage (`Storage::disk(config('filesystems.private'))`) but no explicit signed URL generation found in Export code. Premium gating not observed.

---

## 4. SharedReport — Public Token & Expiration

### Public Report Controller
**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/Public/ReportController.php` (lines 1-96)

**Authentication**: No Laravel auth; uses `X-Api-Key` header with `share_secret` (lines 32-34)

**Access Control** (lines 37-48):
```php
Report::query()
    ->where('share_secret', '=', $shareSecret)
    ->where('is_public', '=', true)
    ->where(function (Builder $builder): void {
        $builder->whereNull('public_until')
            ->orWhere('public_until', '>', now());
    })
    ->firstOrFail();
```

**Expiration Handling**:
- `public_until` is nullable timestamp (migration at line 23 in reports table)
- Automated cleanup via `ReportSetExpiredToPrivateCommand` (found in Glob results)

**Data Generation**: Calls `TimeEntryAggregationService` twice:
1. Main grouping (`$report->properties->group` + `subGroup`)
2. History series (`historyGroup` converted to interval, e.g., Day/Week/Month)

**Share Link Format** (Report model line 48-54):
```php
route('shared-report').'#'.$this->share_secret
// Example: https://app.com/shared-report#abc123...xyz
```
Uses anchor fragment (not URL param) for client-side handling.

---

## 5. BillableRateService — Rate Cascade Logic

**File**: `/home/keven/Documents/solidtime-analysis/app/Service/BillableRateService.php` (lines 1-146)

### Rate Hierarchy (lines 81-100)
```
ProjectMember.billable_rate (highest priority)
  ↓ (if null)
Project.billable_rate
  ↓ (if null)
Member.billable_rate
  ↓ (if null)
Organization.billable_rate
  ↓ (if null)
NULL (no billable rate)
```

### Computed Attribute Pattern (TimeEntry model)
```php
protected array $computed = [
    'billable_rate',
    'client_id',
];
```
Uses `korridor/laravel-computed-attributes` package. The `billable_rate` field is **stored in the database** (not computed on-read) but recalculated when entities change.

### Update Methods (lines 16-79)
Each entity (ProjectMember, Project, Member, Organization) has a corresponding `updateTimeEntriesBillableRateFor{Entity}()` method:
- Updates only **billable** time entries (`where('billable', '=', true)`)
- Uses `whereDoesntHave()` to exclude entries with higher-priority overrides
- Example: When Organization rate changes, skip entries where:
  - Member has a billable_rate, OR
  - Project has a billable_rate, OR
  - ProjectMember has a billable_rate

**This same pattern must be replicated for cost_rate** in the new `CostRateService`.

---

## 6. DashboardService — Chart Widgets & SQL Patterns

**File**: `/home/keven/Documents/solidtime-analysis/app/Service/DashboardService.php` (lines 1-466)

### Key Methods & SQL Patterns

#### Weekly Billable Amount (lines 255-280)
```sql
SELECT round(
    sum(
        extract(epoch from (coalesce("end", now()) - start)) 
        * (billable_rate::float/60/60)
    )
) as aggregate
WHERE billable = true AND billable_rate IS NOT NULL
```
**Pattern**: Multiplies duration (in seconds) by `billable_rate` (cents/hour) divided by 3600, then rounds.

#### Timezone Handling (lines 143-149)
```php
if ($timezoneShift > 0) {
    $dateWithTimeZone = 'start + INTERVAL \''.$timezoneShift.' second\'';
} elseif ($timezoneShift < 0) {
    $dateWithTimeZone = 'start - INTERVAL \''.abs($timezoneShift).' second\'';
}
```
All date-based grouping applies timezone offset to `start` timestamp before `DATE()` extraction.

#### Last Seven Days Sparkline (lines 423-465)
Uses `generate_series()` with 3-hour windows, then joins time entries overlapping each window:
```sql
SELECT time_ranges.start, EXTRACT(epoch FROM sum(
    LEAST(time_ranges.end, coalesce(time_entries.end, now())) 
    - GREATEST(time_ranges.start, time_entries.start)
)) AS aggregate
FROM (
   SELECT start, start + interval '3 hours' AS end
   FROM generate_series(:start, :end + interval '3 hours', interval '3 hours') as starts(start)
) time_ranges
JOIN time_entries ON time_entries.start < time_ranges.end
                  AND coalesce(time_entries.end, now()) > time_ranges.start
GROUP BY time_ranges.start
```
**Insight**: Uses interval overlap logic to handle entries spanning multiple windows.

---

## 7. Chart.js Frontend — Rendering & ChartController

### ChartController Endpoints
**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/ChartController.php` (lines 1-190)

**Routes** (from grep in `routes/api.php`):
- `GET /charts/weekly-project-overview`
- `GET /charts/latest-tasks`
- `GET /charts/last-seven-days`
- `GET /charts/latest-team-activity`
- `GET /charts/daily-tracked-hours`
- `GET /charts/total-weekly-time`
- `GET /charts/total-weekly-billable-time`
- `GET /charts/total-weekly-billable-amount`
- `GET /charts/weekly-history`

All delegate to `DashboardService` and return JSON arrays.

### Frontend Chart Component Example
**File**: `/home/keven/Documents/solidtime-analysis/resources/js/Components/Dashboard/ProjectsChartCard.vue` (lines 1-80)

**Library**: `vue-echarts` (Apache ECharts wrapper)
```vue
<script setup lang="ts">
import VChart, { THEME_KEY } from 'vue-echarts';
import { use } from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';
import { PieChart } from 'echarts/charts';
```

**Chart Type**: Donut (pie with inner radius)
- Data format: `{ value: number, name: string, color: string }[]`
- Tooltip: Formats values using `formatHumanReadableDuration()`
- Colors: Passed from backend as hex codes

**Pattern**: ECharts (not Chart.js). Each widget is a standalone Vue component with inline EChart options.

---

## 8. Scheduled Commands — Kernel.php Patterns

**File**: `/home/keven/Documents/solidtime-analysis/app/Console/Kernel.php` (lines 1-59)

### Current Schedule (lines 16-49)
```php
$schedule->command('time-entry:send-still-running-mails')
    ->when(fn (): bool => config('scheduling.tasks.time_entry_send_still_running_mails'))
    ->everyTenMinutes();

$schedule->command('auth:send-mails-expiring-api-tokens')
    ->when(fn (): bool => config('scheduling.tasks.auth_send_mails_expiring_api_tokens'))
    ->everyTenMinutes();

// Self-hosting checks with randomized time (seeded from APP_KEY)
$schedule->command('self-host:check-for-update')
    ->twiceDailyAt($firstHour, $secondHour, $minuteOffset);

$schedule->command('self-host:database-consistency')
    ->when(fn (): bool => config('scheduling.tasks.self_hosting_database_consistency'))
    ->everySixHours();
```

### Patterns for Scheduled Reports
1. **Config-gated**: All tasks use `->when(fn() => config('...'))` for toggleable execution
2. **Frequency helpers**: `everyTenMinutes()`, `twiceDailyAt()`, `everySixHours()`
3. **Random scheduling**: Seeded randomization prevents thundering herd (lines 26-34)

**Recommended pattern for scheduled reports**:
```php
$schedule->command('report:send-scheduled')
    ->when(fn (): bool => config('scheduling.tasks.send_scheduled_reports'))
    ->hourly();  // Check every hour for due schedules
```

---

## 9. Currency/Money Handling — Storage & Formatting

### Backend Storage
**Convention**: All monetary amounts stored as **integers in cents** (not decimal).
- `billable_rate`: cents per hour (e.g., 5000 = $50.00/hr)
- Aggregated `cost` in SQL: `round(sum(hours * rate_cents))` (already in cents)

### Frontend Formatting
**File**: `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/utils/money.ts` (lines 1-56)

#### Key Function: `formatCents()` (lines 36-43)
```typescript
export function formatCents(
    amount: number,
    currency?: string,
    format?: CurrencyFormat,
    currencySymbol?: string,
    numberFormat?: NumberFormat
) {
    return formatMoney(amount / 100, currency, format, currencySymbol, numberFormat);
}
```

#### CurrencyFormat Enum (lines 3-9)
- `iso-code-before-with-space`: "USD 1,234.56"
- `iso-code-after-with-space`: "1,234.56 USD"
- `symbol-before`: "$1,234.56"
- `symbol-after`: "1,234.56$"
- `symbol-before-with-space`: "$ 1,234.56"
- `symbol-after-with-space`: "1,234.56 $"

#### Organization Settings (Organization model lines 76-80)
```php
'currency_format' => CurrencyFormat::class,
'number_format' => NumberFormat::class,
```
These enums control display format per organization.

### Rounding
- **SQL**: `round()` function used consistently (no arbitrary precision)
- **Display**: `BigDecimal::ofUnscaledValue($cents, 2)` for precise division by 100 in exports (spreadsheet template line 67)

---

## 10. Member Model — weekly_capacity Field (FOUND-006)

**File**: `/home/keven/Documents/solidtime-analysis/app/Models/Member.php` (lines 1-80)

### Current Schema
```php
/**
 * @property string $id
 * @property string $role
 * @property int|null $billable_rate
 * @property string $organization_id
 * @property string $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
```

**Missing**: `weekly_capacity` field (will be added by FOUND-006)

### Shared Foundation Migration (SF-06)
Per the PRD amendments, `weekly_capacity` is **not** in this feature's migrations. It's owned by:
```
2026_02_28_000001_add_weekly_capacity_to_members.php
```
- Default: 144000 seconds (40 hours)
- Type: `unsigned integer`
- Purpose: Planned working hours per week for utilization calculations

### Utilization Calculation (PRD formula)
```
actual_seconds   = SUM(time_entry duration) for member in period
capacity_seconds = weekly_capacity * weeks_in_period
utilization_pct  = (actual_seconds / capacity_seconds) * 100
```

### Role Enum (line 16)
```php
use App\Enums\Role;
// Values: Owner, Admin, Manager, Employee
```
Used for permission checks (e.g., only Managers+ can view profitability).

---

## 11. Risk Assessment — Query Performance, Backfill, Storage

### Query Performance for Large Datasets

#### Time Entry Aggregation (Worst Case: 100K entries)
**Risk Level**: **MEDIUM**

**Current Optimization**:
- Indexes on `organization_id`, `start`, `member_id`, `project_id`, `billable` (migration 2026_02_06_000001)
- Single-pass aggregation (no subqueries per group)

**Potential Issues**:
1. **Tag cross-join**: `LATERAL` join explodes rows when filtering by tags. For 100K entries with avg 3 tags = 300K rows before aggregation.
2. **No pagination**: All groups returned in one response (could be 1000s of clients/projects)
3. **Gap filling**: CPU-bound post-processing in PHP (lines 183-184, 400-476)

**Mitigation for Phase A**:
- Add index on `(organization_id, member_id, start, billable)` for profitability queries
- Consider `JSONB` GIN index on `tags` column for tag filtering (not currently present)
- Implement cursor-based pagination for grouped results (return `next_cursor` param)

#### Profitability Report (New Query)
**Risk Level**: **MEDIUM-HIGH**

**Projected Query**:
```sql
SELECT 
  project_id,
  SUM(CASE WHEN billable THEN extract(epoch from (end - start)) * (billable_rate::float/60/60) ELSE 0 END) as revenue,
  SUM(extract(epoch from (end - start)) * (cost_rate::float/60/60)) as cost,
  COUNT(*) as entry_count
FROM time_entries
WHERE organization_id = ? AND start >= ? AND end <= ?
GROUP BY project_id
```

**Concern**: Adding `cost_rate` column and recalculating on every group will double the aggregation load. With 100K entries × 2 rate columns × epoch extraction = ~200K math operations.

**Mitigation**:
- Create composite index: `(organization_id, start, end, project_id, billable, cost_rate, billable_rate)`
- Use materialized view for monthly rollups (optional, Phase B optimization)

### Backfill Complexity (RPT-035)

**Scenario**: Backfill `cost_rate` for 500K existing time entries across 20 organizations.

**Command Pattern** (from existing `BillableRateService`):
```php
TimeEntry::query()
    ->whereNull('cost_rate')  // Only entries without cost_rate
    ->chunk(500, function ($entries) {
        foreach ($entries as $entry) {
            $entry->cost_rate = CostRateService::getCostRate($entry);
            $entry->save();
        }
    });
```

**Risk Level**: **MEDIUM**

**Issues**:
1. **Query count**: 500K entries = 1000 chunks × 1000 queries (1 per entry for rate lookup) = 1M+ queries
2. **Lock contention**: Writing 500 entries per chunk could lock the table
3. **Time**: At 100 entries/sec = 83 minutes

**Mitigation** (RPT-035 Task):
- Use `upsert()` or raw SQL `UPDATE ... FROM` to join rate hierarchy in single query per chunk
- Run as background job with progress tracking (Laravel Horizon)
- Add `--organization` flag to backfill one org at a time
- Estimated effort: 4 hours (already allocated in Sprint 2)

### Report Template Storage

**Risk Level**: **LOW**

**Schema** (PRD lines 375-387):
```sql
CREATE TABLE report_templates (
    id UUID PRIMARY KEY,
    name VARCHAR(255),
    properties JSONB,  -- Reuses ReportPropertiesDto
    custom_columns JSONB,
    organization_id UUID
);
```

**Storage Size**:
- Typical properties JSON: ~1-2 KB (filters, date ranges, grouping config)
- 1000 templates per org × 2 KB = 2 MB (negligible)

**Query Pattern**: Simple `WHERE organization_id = ?` with no joins. JSONB index not needed unless filtering by property values (unlikely).

---

## 12. Essential File Reference — Critical Files for Advanced Reporting

### Models (5 files)
1. `/home/keven/Documents/solidtime-analysis/app/Models/Report.php` (lines 1-64)
   - **Why**: Existing report model, will add relations to schedules/templates
   
2. `/home/keven/Documents/solidtime-analysis/app/Models/TimeEntry.php` (lines 1-100+)
   - **Why**: Core entity for all aggregations; will add `cost_rate` column
   
3. `/home/keven/Documents/solidtime-analysis/app/Models/Member.php` (lines 1-80)
   - **Why**: Will receive `cost_rate` field; contains `billable_rate` pattern
   
4. `/home/keven/Documents/solidtime-analysis/app/Models/Organization.php` (lines 1-189)
   - **Why**: Will add `default_cost_rate` field; currency settings
   
5. `/home/keven/Documents/solidtime-analysis/app/Models/ProjectMember.php` (lines 1-85)
   - **Why**: Will add `cost_rate` field for project-specific overrides

### Services (6 files)
6. `/home/keven/Documents/solidtime-analysis/app/Service/TimeEntryAggregationService.php` (lines 1-551)
   - **Why**: Core aggregation engine; lines 77-82 show SQL pattern for cost calculation
   
7. `/home/keven/Documents/solidtime-analysis/app/Service/BillableRateService.php` (lines 1-146)
   - **Why**: Blueprint for `CostRateService`; rate cascade logic at lines 81-100
   
8. `/home/keven/Documents/solidtime-analysis/app/Service/DashboardService.php` (lines 1-466)
   - **Why**: SQL patterns for chart data; timezone handling at lines 143-149
   
9. `/home/keven/Documents/solidtime-analysis/app/Service/ReportService.php` (lines 1-15)
   - **Why**: Minimal service; will expand for template/schedule management
   
10. `/home/keven/Documents/solidtime-analysis/app/Service/Export/ExportService.php` (lines 1-389)
    - **Why**: Full export pattern; chunking strategy at lines 122-147
    
11. `/home/keven/Documents/solidtime-analysis/app/Service/TimeEntryService.php` (lines 1-47)
    - **Why**: Rounding SQL helpers used in aggregations

### Export Classes (2 files)
12. `/home/keven/Documents/solidtime-analysis/app/Service/ReportExport/TimeEntriesDetailedExport.php` (lines 1-153)
    - **Why**: Maatwebsite Excel implementation; column mapping at lines 96-110
    
13. `/home/keven/Documents/solidtime-analysis/app/Service/ReportExport/TimeEntriesReportExport.php` (lines 1-104)
    - **Why**: Blade-based export; ties to spreadsheet template

### Controllers (3 files)
14. `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/ReportController.php` (lines 1-174)
    - **Why**: CRUD endpoints; store method at lines 76-123 shows DTO assembly
    
15. `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/ChartController.php` (lines 1-190)
    - **Why**: Chart data endpoints; permission pattern for billable rates at lines 162-165
    
16. `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/Public/ReportController.php` (lines 1-96)
    - **Why**: Shared report access; authentication via X-Api-Key at lines 32-34

### Request Validation (1 file)
17. `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/Report/ReportStoreRequest.php` (lines 1-239)
    - **Why**: Validation rules for report properties; getter pattern at lines 147-238

### DTOs & Enums (3 files)
18. `/home/keven/Documents/solidtime-analysis/app/Service/Dto/ReportPropertiesDto.php` (lines 1-225)
    - **Why**: Core DTO for report config; JSONB casting at lines 73-163
    
19. `/home/keven/Documents/solidtime-analysis/app/Enums/TimeEntryAggregationType.php` (lines 1-45)
    - **Why**: 11 grouping types; used throughout aggregation
    
20. `/home/keven/Documents/solidtime-analysis/app/Enums/ExportFormat.php` (lines 1-35)
    - **Why**: CSV/PDF/XLSX/ODS format enum; Maatwebsite Excel mapping at lines 26-33

### Views (1 file)
21. `/home/keven/Documents/solidtime-analysis/resources/views/reports/time-entry-aggregate/spreadsheet.blade.php` (lines 1-162)
    - **Why**: Export template; cost formatting at lines 67, 100, 131

### Configuration & Scheduling (2 files)
22. `/home/keven/Documents/solidtime-analysis/app/Console/Kernel.php` (lines 1-59)
    - **Why**: Scheduled command patterns; config-gated execution at lines 17-18
    
23. `/home/keven/Documents/solidtime-analysis/app/Providers/JetstreamServiceProvider.php` (lines 1-150+)
    - **Why**: Permission configuration; reports permissions at lines 136-139

### Frontend (2 files)
24. `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/utils/money.ts` (lines 1-56)
    - **Why**: Currency formatting; `formatCents()` at lines 36-43
    
25. `/home/keven/Documents/solidtime-analysis/resources/js/Components/Dashboard/ProjectsChartCard.vue` (lines 1-80)
    - **Why**: ECharts integration pattern for profitability charts

### Migrations (2 files)
26. `/home/keven/Documents/solidtime-analysis/database/migrations/2024_08_01_104840_create_reports_table.php` (lines 1-41)
    - **Why**: Existing report schema; JSONB properties at line 22
    
27. `/home/keven/Documents/solidtime-analysis/database/migrations/2026_02_06_000001_add_timesheet_indexes_to_time_entries_table.php` (filename from glob)
    - **Why**: Recent index additions for query performance

### Tests (1 file)
28. `/home/keven/Documents/solidtime-analysis/tests/Unit/Endpoint/Api/V1/ReportEndpointTest.php` (lines 1-100+)
    - **Why**: Test patterns for report CRUD; permission checks, DTO assembly

---

## Key Architectural Insights for Implementation

### 1. Cost vs. Revenue Disambiguation
The existing `cost` field in aggregations is **revenue** (billable_rate × hours). For profitability:
- Add parallel aggregation: `SUM(cost_rate * hours) as internal_cost`
- Rename response fields: `revenue`, `internal_cost`, `margin`, `margin_percent`
- Update SQL in `TimeEntryAggregationService` lines 77-82

### 2. CostRateService Must Mirror BillableRateService Exactly
- Same 4-level hierarchy: ProjectMember → Project → Member → Organization
- Same update methods with `whereDoesntHave()` exclusions
- Same computed attribute pattern on `TimeEntry`
- Trigger on same events (time entry save, entity rate change)

### 3. Scheduled Reports Architecture
- **Cron pattern**: Store `cron_expression` instead of separate `frequency`/`day_of_week`/`day_of_month` fields
- **Next run calculation**: Use `mtdowling/cron-expression` package (already in Laravel)
- **Queue jobs**: Dispatch `SendScheduledReportJob` for each due schedule, not inline generation
- **Retry logic**: Use Laravel's job retry mechanism, not custom `failure_count`

### 4. Export Column Selection Strategy
- **Backend**: Accept `columns` array in export request, filter `SELECT` clause
- **Frontend**: Store selection in localStorage, not session
- **Template integration**: Include `custom_columns` in `ReportTemplate.properties`

### 5. Utilization Calculation Edge Cases
- **Partial weeks**: Use `Carbon::diffInDays() / 7 * weekly_capacity` for pro-rating
- **Zero capacity**: Return `null` for `utilization_percent`, not division error
- **Overtime**: Allow >100% utilization without clamping

### 6. Frontend Chart Library Consistency
Use **ECharts** (not Chart.js) for all new charts:
- Profitability: Stacked bar chart (revenue, cost, margin) + line chart (margin %)
- Utilization: Horizontal bar chart (capacity vs. actual) + heatmap calendar
- Budget: Progress bar component (custom Vue component, not EChart)

### 7. Premium Gating for Scheduled Reports
Add middleware check in `ReportScheduleController`:
```php
if (!$organization->subscription?->premium) {
    throw new AuthorizationException('Scheduled reports require premium plan');
}
```

---

## Summary

The existing Solidtime reporting infrastructure provides a **solid foundation** for advanced reporting:

**Strengths**:
- Robust aggregation engine with 11 grouping types
- Computed attribute pattern for rate cascade
- JSONB storage for flexible report configurations
- Maatwebsite Excel integration for multiple export formats
- Timezone-aware date grouping

**Gaps to Address**:
- No internal cost tracking (core deliverable)
- No utilization/capacity tracking (depends on FOUND-006)
- No scheduled execution (new `ReportSchedule` model + command)
- No report templates (new `ReportTemplate` model + CRUD)
- Limited export customization (add column selection)

**Critical Path Dependencies**:
1. FOUND-006: `weekly_capacity` migration (blocks utilization reports)
2. CostRateService: Must complete before profitability reports functional
3. Backfill command (RPT-035): Must run in Sprint 2 to populate cost_rate for testing

**Performance Bottlenecks**:
- Tag cross-join for large datasets (consider separate `time_entry_tags` junction table in future)
- Lack of pagination in aggregated results (implement cursor pagination)
- Backfill command query volume (use batch UPDATE with rate hierarchy join)

This analysis provides the technical depth needed to architect Phase A (Core Analytics) with confidence that the implementation will integrate seamlessly with the existing codebase patterns.