Now I have enough information to compile a comprehensive codebase analysis. Let me create the analysis document.

# Deep Codebase Analysis: Feature 04 - Invoicing System

## Executive Summary

This analysis examines the Solidtime codebase to assess readiness for implementing the Invoicing System (Feature 04). The application uses Laravel 11 + Vue 3 + TypeScript + Pinia + Inertia.js with PostgreSQL. Key findings:

- **Strong foundation**: Billable rate cascade, export infrastructure, and premium feature gating are production-ready
- **Client model is minimal**: No billing fields exist; requires significant extension
- **Notification infrastructure is absent**: All notification tasks (FOUND-001 to FOUND-005) must be built
- **PDF generation is mature**: Gotenberg integration is battle-tested with Blade templates
- **Security patterns are consistent**: Organization-scoped queries, permission-based auth, and audit logging

---

## 1. Client Model - Full Schema & Analysis

### Current State
**File**: `/home/keven/Documents/solidtime-analysis/app/Models/Client.php` (lines 1-88)

**Schema** (from migration `/home/keven/Documents/solidtime-analysis/database/migrations/2024_01_20_110218_create_clients_table.php`):
```php
Schema::create('clients', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->string('name', 255);
    $table->uuid('organization_id');
    $table->foreign('organization_id')->references('id')->on('organizations')
          ->cascadeOnUpdate()->restrictOnDelete();
    $table->timestamps();
});
```

**Additional columns added later** (from `2024_06_21_122754_add_is_archived_columns_to_projects_and_clients_table.php`):
```php
$table->dateTime('archived_at')->nullable();
```

### Relationships
- `belongsTo(Organization::class, 'organization_id')` - line 53-56
- `hasMany(Project::class, 'client_id')` - line 61-64

### Scopes
- `scopeVisibleByEmployee(Builder $builder, User $user)` - line 70-76
  - Filters clients to only those with projects visible to the employee
  - Uses `whereHas('projects', fn => $builder->visibleByEmployee($user))`

### Computed Attributes
- `isArchived()` - line 81-86: Returns `true` if `archived_at` is set

### Traits Used
- `CustomAuditable` - line 33: All changes are audited via `owen-it/laravel-auditing`
- `HasFactory` - line 36: Factory support for testing
- `HasUuids` - line 38: UUID primary keys

### Factory States
**File**: `/home/keven/Documents/solidtime-analysis/database/factories/ClientFactory.php`
- `definition()`: Returns `['name' => faker->company(), 'archived_at' => null]`
- `forOrganization(Organization $org)`: Sets `organization_id`
- `randomCreatedAt()`: Sets `created_at` to random date in last day
- `archived()`: Sets `archived_at` to a random datetime

### Controller Patterns
**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/ClientController.php`
- Permission-based access: `clients:view`, `clients:view:all`, `clients:create`, `clients:update`, `clients:delete`
- Employee users see only clients with projects they are assigned to (via `visibleByEmployee` scope)
- Archive operation uses soft-delete pattern (`archived_at` timestamp)
- Deletion blocked if client has projects (`EntityStillInUseApiException`)

### Critical Gaps for Invoicing
**Missing columns required by PRD** (from Section 4.2):
- `billing_address` (text)
- `billing_city` (string)
- `billing_state` (string)
- `billing_postal_code` (string)
- `billing_country` (string, ISO 3166-1 alpha-2)
- `tax_id` (string, VAT number)
- `billing_email` (string, for invoice delivery)
- `payment_terms_days` (integer, nullable, overrides org default)

**Recommendation**: Create migration `2026_03_04_000002_add_billing_fields_to_clients_table.php` with all above fields. Update `ClientFactory`, `ClientStoreRequest`, `ClientUpdateRequest`, and `ClientResource` accordingly.

---

## 2. Project/TimeEntry Billing - Rate Cascade & Billable Flag

### BillableRateService Deep Dive
**File**: `/home/keven/Documents/solidtime-analysis/app/Service/BillableRateService.php` (lines 1-147)

#### Rate Hierarchy (Cascade Logic)
The service implements a 4-level cascade: **ProjectMember → Project → Member → Organization**

**Line 81-100**: `getBillableRateForTimeEntryWithGivenRelations()`
```php
if (!$timeEntry->billable) return null;
if ($projectMember !== null && $projectMember->billable_rate !== null) return $projectMember->billable_rate;
if ($project !== null && $project->billable_rate !== null) return $project->billable_rate;
if ($member !== null && $member->billable_rate !== null) return $member->billable_rate;
if ($organization !== null && $organization->billable_rate !== null) return $organization->billable_rate;
return null;
```

**Line 102-146**: `getBillableRateForTimeEntry()` - Query-based version that fetches relations if not loaded

#### Update Propagation Methods
When a rate changes at any level, downstream time entries are updated:

1. `updateTimeEntriesBillableRateForProjectMember(ProjectMember $pm)` - line 16-23
   - Updates all billable time entries for specific member+project combination
   - **SQL**: `WHERE billable=true AND member_id={pm.member_id} AND project_id={pm.project_id}`

2. `updateTimeEntriesBillableRateForProject(Project $p)` - line 25-40
   - Updates entries for project EXCEPT where member has a ProjectMember override
   - Uses `whereDoesntHave('member', fn => has projectMembers with billable_rate)`

3. `updateTimeEntriesBillableRateForMember(Member $m)` - line 42-58
   - Updates entries for member EXCEPT where project or projectMember has override
   - Complex nested `whereDoesntHave` to exclude overridden entries

4. `updateTimeEntriesBillableRateForOrganization(Organization $o)` - line 60-79
   - Updates entries with no member rate, no project rate, and no projectMember rate
   - Most complex query with multiple nested exclusions

### TimeEntry Billable Rate Storage
**File**: `/home/keven/Documents/solidtime-analysis/app/Models/TimeEntry.php`

**Computed Attribute** (line 120-123):
```php
public function getBillableRateComputed(): ?int
{
    return app(BillableRateService::class)->getBillableRateForTimeEntry($this);
}
```

**Computed attribute regeneration** (line 106-109):
- `billable_rate` is in the `$computed` array
- Regenerated via artisan command `computed-attributes:generate`
- Stored in database for performance but can be recalculated anytime

**Casts** (line 75): `'billable_rate' => 'int'` - stored as cents per hour

### Project Billable Fields
**File**: `/home/keven/Documents/solidtime-analysis/app/Models/Project.php`

**Schema fields**:
- `billable_rate` (int, nullable) - cents per hour (line 28)
- `is_billable` (boolean, default false) - line 30, 74

**Note**: `is_billable` is a project-level flag but does NOT override the per-entry `billable` flag on TimeEntry. Both must be checked when filtering.

### Key Observations for Invoicing

1. **Rate is pre-calculated**: The `billable_rate` on `time_entries` is stored, not computed at query time
   - **Implication**: Invoice line items can directly use `time_entries.billable_rate` without re-resolving cascade
   - **Risk**: If a rate changes after entries are created, invoiced entries will have "historical" rates (this is correct behavior)

2. **Billable flag filtering**: When querying unbilled time entries, filter:
   ```sql
   WHERE billable = true 
   AND end IS NOT NULL
   AND invoice_id IS NULL
   AND billable_rate IS NOT NULL
   ```

3. **Currency is organization-level**: All rates are stored as integers (cents), currency is defined at `Organization.currency`

4. **No tax rate on TimeEntry**: Tax rates must be applied at invoice line item level, not at time entry level

---

## 3. Export Infrastructure - PDF & Excel

### Gotenberg Configuration
**File**: `/home/keven/Documents/solidtime-analysis/config/services.php` (lines 6-11)
```php
'gotenberg' => [
    'url' => env('GOTENBERG_URL'),
    'basic_auth_username' => env('GOTENBERG_BASIC_AUTH_USERNAME'),
    'basic_auth_password' => env('GOTENBERG_BASIC_AUTH_PASSWORD'),
],
```

**Patterns observed** (from `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/TimeEntryController.php`, lines 256-335):

1. **Client initialization** (lines 300-305):
```php
$client = new Client([
    'auth' => config('services.gotenberg.basic_auth_username') !== null ? [
        config('services.gotenberg.basic_auth_username'),
        config('services.gotenberg.basic_auth_password'),
    ] : null,
]);
```

2. **Blade template rendering** (lines 278-287):
```php
$viewFile = file_get_contents(resource_path('views/reports/time-entry-index/pdf.blade.php'));
$html = Blade::render($viewFile, [
    'timeEntries' => $timeEntriesQuery->get(),
    'aggregatedData' => $aggregatedData,
    'timezone' => $timezone,
    'currency' => $organization->currency,
    'localization' => $localizationService,
    'showBillableRate' => $showBillableRate,
]);
```

3. **Gotenberg request** (lines 306-314):
```php
$request = Gotenberg::chromium(config('services.gotenberg.url'))
    ->pdf()
    ->assets(Stream::path(resource_path('pdf/Outfit-VariableFont_wght.ttf'), 'outfit.ttf'))
    ->margins(0.39, 0.78, 0.39, 0.39)
    ->paperSize('8.27', '11.7') // A4
    ->footer(Stream::string('footer', $footerHtml))
    ->html(Stream::string('body', $html));
```

4. **Temporary storage + signed URL** (lines 315-334):
```php
$tempFolder = TemporaryDirectory::make();
$filenameTemp = Gotenberg::save($request, $tempFolder->path(), $client);
Storage::disk(config('filesystems.private'))
    ->putFileAs($folderPath, new File($tempFolder->path($filenameTemp)), $filename);

return response()->json([
    'download_url' => Storage::disk(config('filesystems.private'))
        ->temporaryUrl($path, now()->addMinutes(5)),
]);
```

5. **Debug mode** (lines 230, 293-297):
```php
$debug = $request->getDebug();
if ($debug) {
    return response()->json([
        'html' => $html,
        'footer_html' => $footerHtml,
    ]);
}
```

### Blade Template Patterns
**File**: `/home/keven/Documents/solidtime-analysis/resources/views/reports/time-entry-index/pdf.blade.php` (lines 1-100 shown)

**Key patterns**:
- `@use('Brick\Money\Money')` - Money library injection (line 2)
- `@inject('interval', 'App\Service\IntervalService')` - Service injection (line 4)
- Custom `@font-face` for Outfit font (lines 58-61)
- Inline CSS reset and styling (lines 12-100+)
- Table structure with `.table-wrapper` for borders (lines 77-86)

**Expected structure for invoice PDF**:
- Organization logo and billing info (header)
- Client billing info
- Invoice metadata (number, dates, terms)
- Line items table (description, qty, rate, amount, tax)
- Subtotal/tax/total footer
- Notes and payment terms

### ExportService Pattern
**File**: `/home/keven/Documents/solidtime-analysis/app/Service/Export/ExportService.php` (lines 30-389)

**Key observations**:
- Uses `League\Csv\Writer` for CSV generation (lines 49-52)
- Creates temporary directory, writes CSVs, zips them, uploads to storage (lines 41, 348-371)
- Returns storage path: `'exports/'.$filename` (line 382)
- Chunks large datasets (1000 records at a time) with `->chunk(1000, ...)` (line 85)

**Pattern for invoice PDF service**:
```php
class InvoicePdfService {
    public function generate(Invoice $invoice): string {
        // 1. Render Blade template
        // 2. Call Gotenberg
        // 3. Store in 'invoices/{organization_id}/{invoice_number}.pdf'
        // 4. Return storage path
    }
}
```

### Maatwebsite Excel Integration
**Usage in TimeEntryController** (lines 320-328):
```php
Excel::store(
    new TimeEntriesDetailedExport($timeEntriesQuery, $format, $timezone, $localizationService),
    $path,
    config('filesystems.private'),
    $format->getExportPackageType(),
    ['visibility' => 'private']
);
```

**Implication for invoicing**: Invoice list can be exported to Excel using the same pattern.

---

## 4. Organization Settings - Configuration Storage & API

### Organization Model Schema
**File**: `/home/keven/Documents/solidtime-analysis/app/Models/Organization.php` (lines 1-190)

**Relevant properties** (lines 31-50):
```php
@property string $name
@property string $currency               // ISO 4217 code (e.g., "USD")
@property int|null $billable_rate        // Organization default rate in cents/hour
@property NumberFormat $number_format    // Enum cast
@property CurrencyFormat $currency_format // Enum cast
@property DateFormat $date_format        // Enum cast
@property IntervalFormat $interval_format // Enum cast
@property TimeFormat $time_format        // Enum cast
```

**Casts** (lines 69-81):
```php
protected $casts = [
    'name' => 'string',
    'personal_team' => 'boolean',
    'currency' => 'string',
    'employees_can_see_billable_rates' => 'boolean',
    'employees_can_manage_tasks' => 'boolean',
    'prevent_overlapping_time_entries' => 'boolean',
    'number_format' => NumberFormat::class,
    'currency_format' => CurrencyFormat::class,
    'date_format' => DateFormat::class,
    'interval_format' => IntervalFormat::class,
    'time_format' => TimeFormat::class,
];
```

### Migration History
**Base schema** (from `/home/keven/Documents/solidtime-analysis/database/migrations/2020_05_21_100000_create_organizations_table.php`, lines 16-24):
```php
$table->uuid('id')->primary();
$table->foreignUuid('user_id')->index();
$table->string('name');
$table->boolean('personal_team');
$table->integer('billable_rate')->unsigned()->nullable();
$table->string('currency', 3);
$table->timestamps();
```

**Additional columns** (from various migrations):
- `employees_can_see_billable_rates` (boolean)
- `employees_can_manage_tasks` (boolean)
- `prevent_overlapping_time_entries` (boolean)
- Localization columns: `number_format`, `currency_format`, `date_format`, `interval_format`, `time_format`

### Required Invoice Settings Columns
**Per PRD Section 4.2** (not yet in database):
```php
$table->string('invoice_number_prefix', 20)->nullable();
$table->string('invoice_number_suffix', 20)->nullable();
$table->unsignedInteger('invoice_next_number')->default(1);
$table->unsignedInteger('default_payment_terms')->default(30); // net days
$table->decimal('default_tax_rate', 5, 2)->nullable();         // e.g., 19.00
$table->string('tax_id', 50)->nullable();                      // VAT number
$table->text('billing_address')->nullable();
$table->string('billing_city', 100)->nullable();
$table->string('billing_state', 100)->nullable();
$table->string('billing_postal_code', 20)->nullable();
$table->string('billing_country', 2)->nullable();              // ISO 3166-1 alpha-2
$table->string('billing_email')->nullable();
$table->string('logo_path')->nullable();                       // Storage path
```

### Controller Update Pattern
**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/OrganizationController.php` (not fully read, but follows pattern)

Expected endpoints:
- `GET /api/v1/organizations/{organization}` - Shows all settings
- `PUT /api/v1/organizations/{organization}` - Updates settings (requires `organizations:update` permission)

**Validation**: Use `App\Http\Requests\V1\BaseFormRequest` pattern with rules like:
```php
'invoice_number_prefix' => 'nullable|string|max:20',
'invoice_next_number' => 'required|integer|min:1',
'default_payment_terms' => 'required|integer|min:0|max:365',
'default_tax_rate' => 'nullable|numeric|min:0|max:100',
```

### LocalizationService Integration
**File**: `/home/keven/Documents/solidtime-analysis/app/Service/LocalizationService.php` (lines 1-156)

**Key methods for invoicing**:
- `formatNumber(BigDecimal|float $number): string` - line 50-65
  - Respects `NumberFormat` enum (decimal separator, thousands separator)
- `formatCurrency(Money $money): string` - line 99-115
  - Respects `CurrencyFormat` enum (symbol position, ISO code)
- `formatDate(CarbonInterface $date): string` - line 126-129

**Usage pattern**:
```php
$localization = LocalizationService::forOrganization($organization);
$formattedAmount = $localization->formatCurrency(Money::ofMinor($amountCents, $organization->currency));
```

**Observation**: Invoice PDFs should use `LocalizationService` for all number/currency/date formatting to respect organization preferences.

---

## 5. Premium Feature Gating - BillingContract & Module System

### BillingContract Interface
**File**: `/home/keven/Documents/solidtime-analysis/app/Service/BillingContract.php` (lines 1-58)

**Core methods**:
- `hasSubscription(Organization $organization): bool` - line 23-26 (always returns `true` in base implementation)
- `hasTrial(Organization $organization): bool` - line 32-35 (always returns `false`)
- `getTrialUntil(Organization $organization): ?Carbon` - line 41-44 (returns `null`)
- `isBlocked(Organization $organization): bool` - line 53-56 (always returns `false`)

**Pattern**: The base class allows all features (self-hosted default). Cloud version would extend this with actual billing logic.

### Controller Integration
**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/Controller.php` (lines 48-51)
```php
protected function canAccessPremiumFeatures(Organization $organization): bool
{
    return app(BillingContract::class)->hasSubscription($organization) 
        || app(BillingContract::class)->hasTrial($organization);
}
```

**Usage in TimeEntryController** (line 128, 229, 378, 427):
```php
$canAccessPremiumFeatures = $this->canAccessPremiumFeatures($organization);
if ($format === ExportFormat::PDF && !$canAccessPremiumFeatures) {
    throw new FeatureIsNotAvailableInFreePlanApiException;
}
```

### Frontend Gating
**File**: `/home/keven/Documents/solidtime-analysis/resources/js/utils/billing.ts` (lines 1-73)

**Key functions**:
- `isInvoicingActivated()` - line 12-18: Checks `page.props.has_invoicing_extension`
- `isBillingActivated()` - line 4-10: Checks `page.props.has_billing_extension`
- `hasActiveSubscription()` - line 56-64: Checks `page.props.billing.has_subscription`
- `isInTrial()` - line 20-28
- `isBlocked()` - line 42-50
- `isAllowedToPerformPremiumAction()` - line 66-72

**Navigation gating** (from `/home/keven/Documents/solidtime-analysis/resources/js/Layouts/AppLayout.vue`, lines 210-214):
```vue
<NavigationSidebarItem
    v-if="isInvoicingActivated() && canViewInvoices()"
    title="Invoices"
    :icon="DocumentTextIcon"
    :current="route().current('invoices')"
    href="/invoices"></NavigationSidebarItem>
```

### Permission System
**File**: `/home/keven/Documents/solidtime-analysis/app/Service/PermissionStore.php` (lines 1-103)

**How it works**:
1. User's role is fetched from `Organization.users->membership->role` (line 61-65)
2. Role is resolved via `Jetstream::findRole($role)` (line 72)
3. Permissions are retrieved from role definition (line 74)
4. Cached per user+organization (lines 38-49)

**Invoice permissions already defined** (from `/home/keven/Documents/solidtime-analysis/app/Providers/JetstreamServiceProvider.php`, lines 140-146, 204-210, 257-263):

**Owner/Admin**:
- `invoices:view`
- `invoices:create`
- `invoices:update`
- `invoices:download`
- `invoices:delete`
- `invoice-settings:view`
- `invoice-settings:update`

**Manager**:
- `invoices:view`
- `invoices:create`
- `invoices:update`
- `invoices:download`
- `invoices:delete`
- `invoice-settings:view`
- `invoice-settings:update`

**Employee/Placeholder**: No invoice permissions

**Note**: These permissions exist but controllers do not. PRD tasks must implement them.

### Check-Organization-Blocked Middleware
**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Middleware/CheckOrganizationBlocked.php` (lines 1-41)

**Logic** (lines 24-36):
```php
$organization = $request->route('organization');
$billing = app(BillingContract::class);
if ($billing->isBlocked($organization)) {
    throw new OrganizationHasNoSubscriptionButMultipleMembersException;
}
```

**Applied to write operations** (from `/home/keven/Documents/solidtime-analysis/routes/api.php`, lines 81-178):
- `POST /invitations`
- `POST /projects`
- `PUT /projects/{project}`
- `POST /time-entries`
- etc.

**Implication for invoicing**: All invoice write operations (create, update, send, record payment) should use this middleware:
```php
Route::post('/invoices', [InvoiceController::class, 'store'])
    ->name('store')
    ->middleware('check-organization-blocked');
```

---

## 6. Report/Aggregation Queries - Patterns for Invoice Grouping

### TimeEntryAggregationService
**File**: `/home/keven/Documents/solidtime-analysis/app/Service/TimeEntryAggregationService.php` (lines 1-552)

#### Core Aggregation Logic
**Method**: `getAggregatedTimeEntries()` - line 47-199

**Key capabilities**:
1. **Two-level grouping** (line 47): `group1Type` and `group2Type` (nullable)
2. **Group types** (enum `TimeEntryAggregationType`):
   - `Day`, `Week`, `Month`, `Year` (time-based)
   - `User`, `Project`, `Task`, `Client`, `Description`, `Tag`, `Billable`

3. **Aggregate calculation** (lines 77-82):
```php
$timeEntriesQuery->selectRaw(
    ($group1Select !== null ? $group1Select.' as group_1,' : '').
    ($group2Select !== null ? $group2Select.' as group_2,' : '').
    ' round(sum(extract(epoch from ('.$endRawSelect.' - '.$startRawSelect.')))) as aggregate,'.
    ' round(sum(extract(epoch from ('.$endRawSelect.' - '.$startRawSelect.')) * (coalesce(billable_rate, 0)::float/60/60))) as cost'
);
```

**Returns**:
- `seconds`: Total duration
- `cost`: Total cost (duration * rate), nullable if `showBillableRate=false`
- `grouped_type`: Type of grouping
- `grouped_data`: Array of groups with nested sub-groups

#### Tag Handling Special Case
**Lines 56-64**: Tags require `LATERAL` join to expand JSON array:
```sql
CROSS JOIN LATERAL (
  SELECT jsonb_array_elements_text(coalesce(tags, '[]'::jsonb)) AS tag
  UNION ALL
  SELECT ''::text AS tag WHERE coalesce(jsonb_array_length(tags), 0) = 0
) AS tag(tag)
```

**Lines 103-120**: Base totals computed separately to avoid double-counting when tag is subgroup

#### Descriptor Loading
**Method**: `loadDescriptorsMap()` - line 295-370

**Loads human-readable names**:
- Client: `Client::whereIn('id', $keys)->select('id', 'name')`
- Project: `Project::whereIn('id', $keys)->select('id', 'name', 'color')`
- Task: Similar pattern
- User: Similar pattern
- Tag: Similar pattern

**Returns**: `['uuid' => ['description' => 'name', 'color' => 'hex']]`

### ReportController Pattern
**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/ReportController.php` (lines 1-175)

**Key observations**:
1. Reports store filter criteria in `properties` (JSONB column) (lines 86-112)
2. Filters include: `memberIds`, `clientIds`, `projectIds`, `tagIds`, `taskIds`, `billable`, `start`, `end`
3. Reports can be made public with `share_secret` and `public_until` (lines 113-119)
4. Public reports accessible without auth via different controller

### TimeEntryFilter Service
**File**: `/home/keven/Documents/solidtime-analysis/app/Service/TimeEntryFilter.php` (lines 1-208)

**Builder pattern for filtering**:
- `addStartFilter(?string $dateTime)` - line 48-56
- `addEndFilter(?string $dateTime)` - line 28-36
- `addActiveFilter(?string $active)` - line 68-83 (filters by `end IS NULL`)
- `addBillableFilter(?string $billable)` - line 118-132
- `addMemberIdsFilter(?array $memberIds)` - line 108-116
- `addClientIdsFilter(?array $clientIds)` - line 147-155
- `addProjectIdsFilter(?array $projectIds)` - line 160-168
- `addTagIdsFilter(?array $tagIds)` - line 173-185 (uses `whereJsonContains`)
- `addTaskIdsFilter(?array $taskIds)` - line 190-198

**Usage pattern** (from TimeEntryController line 198-210):
```php
$filter = new TimeEntryFilter($timeEntriesQuery);
$filter->addStartFilter($request->input('start'));
$filter->addEndFilter($request->input('end'));
$filter->addMemberIdsFilter($request->input('member_ids'));
$filter->addProjectIdsFilter($request->input('project_ids'));
$filter->addClientIdsFilter($request->input('client_ids'));
$filter->addBillableFilter($request->input('billable'));
return $filter->get(); // Returns Builder<TimeEntry>
```

### Application to Invoice Generation

**Invoice preview aggregation**:
```php
// Query unbilled time entries
$query = TimeEntry::query()
    ->whereBelongsTo($organization, 'organization')
    ->where('billable', true)
    ->whereNotNull('end')
    ->whereNull('invoice_id');

// Apply filters
$filter = new TimeEntryFilter($query);
$filter->addClientIdsFilter([$clientId]);
$filter->addStartFilter($request->start);
$filter->addEndFilter($request->end);
$filter->addProjectIdsFilter($request->project_ids);
$filter->addMemberIdsFilter($request->member_ids);
$filter->addTagIdsFilter($request->tag_ids);

// Group by project/task/member
$groupBy = match($request->group_by) {
    'project' => 'project_id',
    'task' => 'task_id',
    'member' => 'member_id',
    'date' => DB::raw('DATE(start)'),
};

$preview = $filter->get()
    ->selectRaw("
        {$groupBy} as grouping_key,
        COUNT(*) as entry_count,
        ROUND(SUM(EXTRACT(epoch FROM (end - start)))) as total_seconds,
        MAX(billable_rate) as unit_rate_cents,
        ROUND(SUM(EXTRACT(epoch FROM (end - start)) * billable_rate / 3600)) as amount_cents
    ")
    ->groupBy($groupBy)
    ->get();
```

**Rounding**: Use `TimeEntryService::getStartSelectRawForRounding()` and `getEndSelectRawForRounding()` (referenced in TimeEntryController line 191).

---

## 7. Currency Handling - Storage & Formatting

### Organization.currency
**Type**: `string` (3-character ISO 4217 code, e.g., "USD", "EUR", "GBP")
**Default**: Set during organization creation, no default in schema
**Usage**: All monetary amounts in the organization use this currency

### Money Storage Pattern
**All monetary values stored as integers in cents** (or smallest currency unit):
- `Organization.billable_rate`: int (cents per hour)
- `Project.billable_rate`: int (cents per hour)
- `Member.billable_rate`: int (cents per hour)
- `ProjectMember.billable_rate`: int (cents per hour)
- `TimeEntry.billable_rate`: int (cents per hour)

**Rationale**: Avoids floating-point precision issues

### CurrencyFormat Enum
**File**: `/home/keven/Documents/solidtime-analysis/app/Enums/CurrencyFormat.php` (lines 1-37)

**Cases**:
- `ISOCodeBeforeWithSpace`: "USD 1,234.56"
- `ISOCodeAfterWithSpace`: "1,234.56 USD"
- `SymbolBefore`: "$1,234.56"
- `SymbolAfter`: "1,234.56$"
- `SymbolBeforeWithSpace`: "$ 1,234.56"
- `SymbolAfterWithSpace`: "1,234.56 $"

### LocalizationService Currency Formatting
**File**: `/home/keven/Documents/solidtime-analysis/app/Service/LocalizationService.php` (lines 99-115)

**Method**: `formatCurrency(Money $money): string`

**Implementation**:
```php
$currencyService = app(CurrencyService::class);
if ($this->currencyFormat === CurrencyFormat::ISOCodeAfterWithSpace) {
    return $this->formatNumber($money->getAmount()).' '.$money->getCurrency()->getCurrencyCode();
} elseif ($this->currencyFormat === CurrencyFormat::SymbolBefore) {
    return $currencyService->getCurrencySymbolForMoney($money).$this->formatNumber($money->getAmount());
}
// ... etc
```

**Dependencies**: Uses `brick/money` library (via `use Brick\Money\Money;`)

### Invoice Amount Fields (Per PRD)
All amounts stored as integers (cents):
- `Invoice.subtotal_cents`: Sum of line amounts before tax
- `Invoice.tax_total_cents`: Sum of tax amounts
- `Invoice.total_cents`: subtotal + tax
- `Invoice.amount_paid_cents`: Total payments received
- `Invoice.amount_due_cents`: **Computed accessor** (NOT a database column): `total_cents - amount_paid_cents`

**Per AMD-11**: `amount_due_cents` is a model accessor, not a DB column.

### Calculation Example
```php
// Line item calculation
$quantityHours = $quantitySeconds / 3600;
$lineAmountCents = round($quantityHours * $unitRateCents);
$taxAmountCents = round($lineAmountCents * $taxRate / 100);

// Invoice totals
$invoice->subtotal_cents = $lineItems->sum('amount_cents');
$invoice->tax_total_cents = $lineItems->sum('tax_amount_cents');
$invoice->total_cents = $invoice->subtotal_cents + $invoice->tax_total_cents;

// Display
$localization = LocalizationService::forOrganization($organization);
$formattedTotal = $localization->formatCurrency(
    Money::ofMinor($invoice->total_cents, $invoice->currency)
);
```

---

## 8. Scheduled Commands - Laravel Scheduler Patterns

### Kernel.php Schedule Definition
**File**: `/home/keven/Documents/solidtime-analysis/app/Console/Kernel.php` (lines 1-60)

**Pattern observed**:
```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('time-entry:send-still-running-mails')
        ->when(fn (): bool => config('scheduling.tasks.time_entry_send_still_running_mails'))
        ->everyTenMinutes();
        
    $schedule->command('auth:send-mails-expiring-api-tokens')
        ->when(fn (): bool => config('scheduling.tasks.auth_send_mails_expiring_api_tokens'))
        ->everyTenMinutes();
}
```

**Key observations**:
1. **Config-gated**: All schedules wrapped in `->when(fn() => config('scheduling.tasks.{task_name}'))`
2. **Frequency methods**: `everyTenMinutes()`, `twiceDailyAt()`, `everySixHours()`
3. **Dynamic scheduling**: Lines 25-45 use app key hash to randomize self-hosting telemetry times

### Command Structure
**Directory**: `/home/keven/Documents/solidtime-analysis/app/Console/Commands/`

**Example command discovered**:
- `TimeEntry/TimeEntrySendStillRunningMailsCommand.php`
- `Auth/AuthSendReminderForExpiringApiTokensCommand.php`
- `Report/ReportSetExpiredToPrivateCommand.php`

**Expected pattern**:
```php
namespace App\Console\Commands\Invoice;

class InvoiceTransitionOverdueCommand extends Command
{
    protected $signature = 'invoice:transition-overdue';
    protected $description = 'Transition sent invoices past due date to overdue status';
    
    public function handle(): int
    {
        $count = Invoice::query()
            ->where('status', 'sent')
            ->where('due_date', '<', now())
            ->update(['status' => 'overdue']);
            
        $this->info("Transitioned {$count} invoices to overdue status.");
        return self::SUCCESS;
    }
}
```

### Recurring Invoice Generation Command
**Per PRD REQ-006**, a scheduled command must:
1. Query `RecurringInvoiceSchedule` where `is_active=true` and `next_run_at <= now()`
2. For each schedule:
   - Query billable time entries if `include_billable_time=true`
   - Create draft invoice
   - Update `last_run_at` and calculate `next_run_at` based on `frequency`
3. Send notifications to organization owners/admins

**Scheduling**:
```php
$schedule->command('invoice:generate-recurring')
    ->when(fn(): bool => config('scheduling.tasks.invoice_generate_recurring'))
    ->daily()
    ->at('00:00'); // Run at midnight UTC
```

---

## 9. Frontend Dashboard/Page Patterns - Inertia + Pinia + Vue Query

### Page Composition Pattern
**File**: `/home/keven/Documents/solidtime-analysis/resources/js/Layouts/AppLayout.vue` (lines 1-106)

**Structure**:
1. **Props**: `{ title: String, mainClass: String }`
2. **State**: Uses `ref()` for local state (e.g., `showSidebarMenu`)
3. **Data fetching**: Uses `@tanstack/vue-query` (lines 46, 62-71):
```vue
const { data: organization, isLoading } = useQuery({
    queryKey: ['organization', getCurrentOrganizationId()],
    queryFn: () => api.getOrganization({
        params: { organization: getCurrentOrganizationId()! }
    }),
    enabled: !!getCurrentOrganizationId(),
});
```
4. **Global state**: Provides organization via `provide()` (lines 73-76)
5. **Initialization**: `onMounted(() => initializeStores())` (line 83)

### Pinia Store Pattern
**File**: `/home/keven/Documents/solidtime-analysis/resources/js/utils/useClients.ts` (lines 1-96)

**Structure**:
```typescript
export const useClientsStore = defineStore('clients', () => {
    const clientResponse = ref<ClientIndexResponse | null>(null);
    const { handleApiRequestNotifications } = useNotificationsStore();
    
    async function fetchClients() {
        const organization = getCurrentOrganizationId();
        clientResponse.value = await handleApiRequestNotifications(
            () => api.getClients({ params: { organization } }),
            undefined,
            'Failed to fetch clients'
        );
    }
    
    async function createClient(clientBody: CreateClientBody): Promise<Client | undefined> {
        const response = await handleApiRequestNotifications(
            () => api.createClient(clientBody, { params: { organization } }),
            'Client created successfully',
            'Failed to create client'
        );
        await fetchClients(); // Refresh list
        return response?.data;
    }
    
    const clients = computed<Client[]>(() => clientResponse.value?.data || []);
    
    return { clients, fetchClients, createClient, deleteClient, updateClient };
});
```

**Key patterns**:
1. Uses `defineStore` with composition API
2. State stored in `ref()`
3. Computed properties for derived state
4. Async functions for API calls
5. Uses `handleApiRequestNotifications` wrapper for success/error toasts
6. Refreshes list after mutations
7. Returns object with state + actions

### API Client Usage
**Generated TypeScript client**: `/home/keven/Documents/solidtime-analysis/resources/js/packages/api/src`

**Usage** (from useClients.ts):
```typescript
import { api } from '@/packages/api/src';

api.getClients({ 
    queries: { archived: 'all' }, 
    params: { organization: organizationId } 
});
api.createClient(body, { params: { organization: organizationId } });
api.updateClient(body, { params: { organization: organizationId, client: clientId } });
api.deleteClient(undefined, { params: { organization: organizationId, client: clientId } });
```

### Navigation & Routing
**Sidebar** (from AppLayout.vue, lines 131-214):
- Uses `NavigationSidebarItem` component
- Route checking: `route().current('dashboard')`
- Permission gating: `v-if="canViewClients()"`
- Feature gating: `v-if="isInvoicingActivated() && canViewInvoices()"`

**Inertia routing**: Uses `href` prop or `route()` helper

### Expected Invoice Store
**File to create**: `resources/js/utils/useInvoices.ts`
```typescript
export const useInvoicesStore = defineStore('invoices', () => {
    const invoiceResponse = ref<InvoiceIndexResponse | null>(null);
    
    async function fetchInvoices(filters?: InvoiceFilters) { ... }
    async function createInvoice(body: CreateInvoiceBody) { ... }
    async function updateInvoice(id: string, body: UpdateInvoiceBody) { ... }
    async function sendInvoice(id: string, body: SendInvoiceBody) { ... }
    async function recordPayment(id: string, body: RecordPaymentBody) { ... }
    async function downloadPdf(id: string) { ... }
    
    const invoices = computed(() => invoiceResponse.value?.data || []);
    const totalCount = computed(() => invoiceResponse.value?.meta?.total || 0);
    
    return { invoices, totalCount, fetchInvoices, createInvoice, ... };
});
```

### Component Structure
**File**: `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/` (UI components are in this directory)

**Expected invoice components**:
- `Invoice/InvoiceList.vue`: Table with status badges, filters, pagination
- `Invoice/InvoiceDetail.vue`: Read-only view with line items table
- `Invoice/InvoiceForm.vue`: Create/edit form with line items editor
- `Invoice/InvoiceLineItemTable.vue`: Editable table for line items
- `Invoice/InvoiceStatusBadge.vue`: Color-coded status display
- `Invoice/PaymentRecordModal.vue`: Modal for recording payments

---

## 10. Notification Infrastructure Gap - What Exists vs. What's Needed

### Current State: NO Notification System

**Search results**: Grepping for "Notification" in `/home/keven/Documents/solidtime-analysis/app` returned NO notification files (only model imports in unrelated files).

**Migration check**: No `notifications` table exists in migrations.

**Verdict**: The notification infrastructure described in SHARED-FOUNDATIONS.md (FOUND-001 to FOUND-005) does NOT exist and must be built from scratch.

### FOUND-001: Notification Table Migration
**Status**: Does NOT exist
**Required**: `php artisan notifications:table` + migration for `notification_preferences` on members table
**Effort**: 2 hours

### FOUND-002: Base Notification Classes
**Status**: Does NOT exist
**Required**: `App\Notifications\BaseNotification` extending Laravel's Notification
**Effort**: 4 hours

### FOUND-003: Notification Bell UI
**Status**: Does NOT exist (no NotificationBell.vue component)
**Required**: Vue component in AppLayout, polling endpoint every 60s
**Effort**: 8 hours

### FOUND-004: Notification API Endpoints
**Status**: Does NOT exist
**Required**: CRUD endpoints for notifications (list, mark read, read all, unread count)
**Effort**: 6 hours

### FOUND-005: Notification Preferences UI
**Status**: Does NOT exist
**Required**: Settings page for per-member notification preferences
**Effort**: 4 hours

### Total Shared Effort: 24 hours
**Critical for invoicing**: Invoice send/payment notifications depend on FOUND-001 to FOUND-005 being complete.

### Expected Notification Classes for Invoicing
Per SHARED-FOUNDATIONS.md SF-04:
- `InvoiceSentNotification` - Sent to organization admins when invoice is sent
- `InvoiceOverdueNotification` - Sent to admins when invoice becomes overdue
- `PaymentReceivedNotification` - Sent to admins when payment is recorded
- `RecurringInvoiceGeneratedNotification` - Sent to admins when recurring invoice is auto-created

**Pattern** (per FOUND-002):
```php
class InvoiceSentNotification extends BaseNotification
{
    public function __construct(public Invoice $invoice) {}
    
    public function via($notifiable): array
    {
        return ['database', 'mail']; // Respects member preferences
    }
    
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Invoice {$this->invoice->invoice_number} sent")
            ->line("Invoice {$this->invoice->invoice_number} for {$this->invoice->client->name} has been sent.")
            ->action('View Invoice', url("/invoices/{$this->invoice->id}"));
    }
    
    public function toArray($notifiable): array
    {
        return [
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'client_name' => $this->invoice->client->name,
            'message' => "Invoice {$this->invoice->invoice_number} sent",
        ];
    }
}
```

---

## 11. Risk Assessment

### Performance Concerns

**Risk 1: Invoice Preview Query Performance**
- **Scenario**: Generating preview for client with 10,000+ unbilled time entries
- **Mitigation**: 
  - Add composite index: `(organization_id, billable, end, invoice_id)` on `time_entries`
  - Limit preview queries to max 1000 entries with warning message
  - Use pagination for entry selection UI
- **Severity**: Medium

**Risk 2: PDF Generation Timeout**
- **Scenario**: Invoice with 500+ line items causes Gotenberg timeout
- **Mitigation**:
  - Set timeout to 30s: `Gotenberg::chromium(...)->timeout(30)`
  - Blade template pagination (page breaks every 50 items)
  - Background job for large invoices
- **Severity**: Low

**Risk 3: Recurring Invoice Schedule Thundering Herd**
- **Scenario**: 1000 organizations all have recurring invoices scheduled for midnight UTC
- **Mitigation**:
  - Stagger execution using organization ID hash: `->at("00:" . ($orgId % 60) . ":00")`
  - Use queue for actual generation: `dispatch(new GenerateRecurringInvoiceJob($schedule))`
- **Severity**: High

### Security Considerations

**Risk 1: Invoice PDF Enumeration**
- **Scenario**: Attacker guesses invoice IDs and downloads PDFs
- **Mitigation**:
  - Use UUIDs (already standard in solidtime)
  - Require authentication + `invoices:view` permission
  - Organization-scoped queries (already enforced)
  - Signed temporary URLs (already used in export system)
- **Severity**: Low (mitigated by existing patterns)

**Risk 2: Invoice Number Collision**
- **Scenario**: Race condition when generating invoice numbers
- **Mitigation**:
  - Use database transaction with row lock:
    ```php
    DB::transaction(function() use ($organization) {
        $org = Organization::lockForUpdate()->find($organization->id);
        $invoiceNumber = $org->invoice_next_number;
        $org->invoice_next_number++;
        $org->save();
        return $invoiceNumber;
    });
    ```
- **Severity**: High (data integrity issue)

**Risk 3: Payment Amount Manipulation**
- **Scenario**: Frontend sends `amount_paid_cents` > `total_cents`
- **Mitigation**:
  - Validate in request: `'amount_cents' => ['required', 'integer', 'min:1', new MaxInvoiceAmountDue($invoice)]`
  - Backend recalculation of `amount_due` as accessor (already planned)
- **Severity**: Medium

### Integration Complexity

**Risk 1: Stripe/PayPal Webhook Reliability**
- **Scenario**: Webhook delivery fails, invoice stuck in "sent" status
- **Mitigation**:
  - Implement idempotency key handling
  - Manual reconciliation tool: `php artisan invoice:reconcile-payments`
  - Webhook retry with exponential backoff
- **Severity**: Medium

**Risk 2: Time Entry Re-linking After Invoice Void**
- **Scenario**: Voiding invoice should unlink time entries, but FK constraint issues arise
- **Mitigation**:
  - Use `onDelete('set null')` for `time_entries.invoice_id` FK
  - Transaction for void operation with explicit unlinking
- **Severity**: Low

**Risk 3: Multi-Currency Handling**
- **Scenario**: Organization changes currency mid-stream
- **Mitigation**:
  - Invoice stores `currency` snapshot (already in PRD)
  - Prevent organization currency change if active invoices exist
  - Validate all time entries in invoice have compatible rates
- **Severity**: Low

---

## 12. Essential File Reference - Critical Implementation Paths

### Absolute Must-Read Files (Core Architecture)

#### Backend Core
1. `/home/keven/Documents/solidtime-analysis/app/Models/Client.php` - Lines 1-88
   - Understand existing model structure and relationships
   - See audit trail pattern (`CustomAuditable` trait)

2. `/home/keven/Documents/solidtime-analysis/app/Models/Organization.php` - Lines 1-190
   - Currency and localization settings storage
   - Jetstream integration patterns

3. `/home/keven/Documents/solidtime-analysis/app/Models/TimeEntry.php` - Lines 1-236
   - Billable rate computed attribute pattern (line 120-123)
   - Client relationship pattern (line 221-224)

4. `/home/keven/Documents/solidtime-analysis/app/Service/BillableRateService.php` - Lines 1-147
   - Rate cascade logic (lines 81-100, 102-146)
   - Update propagation methods (lines 16-79)

5. `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/Controller.php` - Lines 1-53
   - Base controller with permission checking (lines 21-26)
   - Premium feature gating (lines 48-51)

6. `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/TimeEntryController.php` - Lines 256-335
   - Gotenberg PDF generation pattern
   - Temporary storage + signed URL pattern (lines 315-334)

#### Service Layer
7. `/home/keven/Documents/solidtime-analysis/app/Service/PermissionStore.php` - Lines 1-103
   - Permission caching and resolution (lines 36-50)
   - Role-to-permission mapping (lines 55-87)

8. `/home/keven/Documents/solidtime-analysis/app/Service/TimeEntryAggregationService.php` - Lines 47-199
   - Grouping and aggregation logic for invoice preview
   - Tag expansion pattern (lines 56-64)

9. `/home/keven/Documents/solidtime-analysis/app/Service/LocalizationService.php` - Lines 1-156
   - Currency formatting (lines 99-115)
   - Number formatting respecting org settings (lines 50-65)

10. `/home/keven/Documents/solidtime-analysis/app/Service/Export/ExportService.php` - Lines 30-389
    - CSV generation with chunking (lines 99-147)
    - ZIP creation and storage pattern (lines 348-376)

#### Middleware & Routes
11. `/home/keven/Documents/solidtime-analysis/app/Http/Middleware/CheckOrganizationBlocked.php` - Lines 1-41
    - Billing enforcement pattern

12. `/home/keven/Documents/solidtime-analysis/routes/api.php` - Lines 1-100
    - Route organization pattern
    - Middleware application (lines 81-178)

#### Permissions
13. `/home/keven/Documents/solidtime-analysis/app/Providers/JetstreamServiceProvider.php` - Lines 78-336
    - Role definitions (lines 82-281)
    - Invoice permissions (lines 140-146, 204-210, 257-263)

#### Migrations
14. `/home/keven/Documents/solidtime-analysis/database/migrations/2024_01_20_110218_create_clients_table.php` - Lines 1-37
    - FK constraint patterns

15. `/home/keven/Documents/solidtime-analysis/database/migrations/2020_05_21_100000_create_organizations_table.php` - Lines 1-35
    - Base organization schema

### Frontend Core
16. `/home/keven/Documents/solidtime-analysis/resources/js/Layouts/AppLayout.vue` - Lines 1-250
    - Navigation gating (lines 210-214)
    - Organization data fetching (lines 62-71)
    - Store initialization (line 83)

17. `/home/keven/Documents/solidtime-analysis/resources/js/utils/useClients.ts` - Lines 1-96
    - Pinia store pattern
    - API client usage
    - Notification wrapper (lines 20-31)

18. `/home/keven/Documents/solidtime-analysis/resources/js/utils/billing.ts` - Lines 1-73
    - Feature gating functions (lines 4-18)
    - Premium access checks (lines 66-72)

19. `/home/keven/Documents/solidtime-analysis/resources/js/utils/permissions.ts` - Lines 120-131
    - Permission checking helpers

### Blade Templates
20. `/home/keven/Documents/solidtime-analysis/resources/views/reports/time-entry-index/pdf.blade.php` - Lines 1-100
    - PDF styling patterns
    - Font injection (lines 58-61)
    - Table structure (lines 77-100)

### Configuration
21. `/home/keven/Documents/solidtime-analysis/config/services.php` - Lines 1-12
    - Gotenberg configuration

22. `/home/keven/Documents/solidtime-analysis/app/Http/Kernel.php` - Lines 1-81
    - Middleware aliases (line 78)

23. `/home/keven/Documents/solidtime-analysis/app/Console/Kernel.php` - Lines 1-60
    - Scheduling patterns (lines 17-49)

### Factories (Testing)
24. `/home/keven/Documents/solidtime-analysis/database/factories/ClientFactory.php` - Lines 1-55
    - Factory state methods

### Enums
25. `/home/keven/Documents/solidtime-analysis/app/Enums/CurrencyFormat.php` - Lines 1-37
    - Currency display options

26. `/home/keven/Documents/solidtime-analysis/app/Enums/Role.php` - Lines 1-15
    - Role enumeration

---

## Summary: Key Findings for Architecture Phase

### Strong Foundations (Reusable)
1. **Billable Rate Cascade**: Production-ready, no changes needed
2. **PDF Generation**: Mature Gotenberg integration with Blade templates
3. **Permission System**: Invoice permissions already defined in JetstreamServiceProvider
4. **Organization Settings**: Casting and validation patterns are solid
5. **API Patterns**: Consistent controller structure with permission checks
6. **Frontend State**: Pinia + Vue Query patterns are well-established

### Critical Gaps (Must Build)
1. **Notification Infrastructure**: All 5 FOUND tasks (24 hours) must be completed before invoice notifications
2. **Client Billing Fields**: 8 new columns needed on `clients` table
3. **Organization Invoice Settings**: 13 new columns needed on `organizations` table
4. **TimeEntry.invoice_id Column**: FK to link entries to invoices
5. **Web Routes**: No web routes exist; must create for Inertia pages

### High-Priority Risks
1. **Invoice Number Collision**: Requires transaction with row lock
2. **Recurring Invoice Thundering Herd**: Needs job queue + stagger
3. **PDF Performance**: Large invoices may timeout

### Architecture Recommendations
1. **Follow existing patterns religiously**: This codebase has strong conventions
2. **Reuse TimeEntryFilter for unbilled query**: Don't reinvent filtering
3. **Leverage LocalizationService**: All currency formatting should use it
4. **Use BaseFormRequest pattern**: Validation rules follow established structure
5. **Mirror TimeEntryController export patterns**: PDF generation is proven
6. **Implement FOUND-001 to FOUND-005 first**: Notification system is foundational

---

**Files Generated**: This analysis references 26 critical files with specific line numbers. All file paths are absolute and ready for architect agent consumption.