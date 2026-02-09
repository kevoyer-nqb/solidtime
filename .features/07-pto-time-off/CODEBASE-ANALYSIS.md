# Codebase Analysis: Feature 07 -- PTO & Time Off

**Date**: 2026-02-06
**Source PRD**: `.features/07-pto-time-off/PRD.md` (with amendments AMD-01 through AMD-11)
**Shared Foundations**: `.features/SHARED-FOUNDATIONS.md`

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Member Model Deep Dive](#2-member-model-deep-dive)
3. [Organization Configuration Patterns](#3-organization-configuration-patterns)
4. [User-Member Multi-Org Relationship](#4-user-member-multi-org-relationship)
5. [Scheduled Command Patterns](#5-scheduled-command-patterns)
6. [Timezone-Aware Date Handling](#6-timezone-aware-date-handling)
7. [CRUD Controller Patterns](#7-crud-controller-patterns)
8. [Approval Workflow Gap Analysis](#8-approval-workflow-gap-analysis)
9. [Frontend Dashboard Widget Patterns](#9-frontend-dashboard-widget-patterns)
10. [Calendar View Integration Strategy](#10-calendar-view-integration-strategy)
11. [Risk Assessment](#11-risk-assessment)
12. [Essential File Reference](#12-essential-file-reference)

---

## 1. Executive Summary

This analysis explores the Solidtime codebase to identify patterns, infrastructure, and architectural decisions that inform the implementation of Feature 07: PTO & Time Off. The codebase is a Laravel 11 + Vue 3 + TypeScript + Pinia + Inertia.js application with strong conventions around organization-scoped data, Jetstream-based permission authorization, and timezone-aware date handling.

**Key findings:**

- The `Member` model is the pivot for user-organization relationships and is the correct FK target for all PTO data (balances, requests).
- Organization settings use column-based configuration on the `organizations` table (no JSON blobs), with enum casts for format preferences.
- Scheduled commands follow a config-gated pattern (`config/scheduling.php`) with `--dry-run` support and idempotency via sentinel columns.
- Date/time handling uses UTC storage with timezone-offset SQL calculations; PTO should use DATE columns to avoid timezone ambiguity entirely.
- No existing approval workflow exists anywhere in the codebase. PTO will establish the first approval pattern using the shared `ApprovalStatus` enum (SF-05) and `HasApprovalWorkflow` trait.
- The Dashboard page uses widget-based Vue components with `@tanstack/vue-query` for data fetching.
- The Calendar page fetches time entries via API for a given date range; PTO days will require a new query merged into the calendar event list.

---

## 2. Member Model Deep Dive

### Database Schema

The `members` table was originally `organization_user`, created by Jetstream's team membership system, then renamed.

**File**: `/home/keven/Documents/solidtime-analysis/database/migrations/2020_05_21_200000_create_organization_user_table.php`

```php
// Lines 16-25
Schema::create('organization_user', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->foreignUuid('organization_id');
    $table->foreignUuid('user_id');
    $table->string('role')->nullable();
    $table->integer('billable_rate')->unsigned()->nullable();
    $table->timestamps();

    $table->unique(['organization_id', 'user_id']);
});
```

**File**: `/home/keven/Documents/solidtime-analysis/database/migrations/2024_05_13_171020_rename_table_organization_user_to_members.php`

```php
// Line 15
Schema::rename('organization_user', 'members');
```

### Current Columns

| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| `id` | UUID | No | Primary key |
| `organization_id` | UUID FK | No | References `organizations(id)` |
| `user_id` | UUID FK | No | References `users(id)` |
| `role` | string | Yes | One of: owner, admin, manager, employee, placeholder |
| `billable_rate` | unsigned int | Yes | Cents per hour |
| `created_at` | timestamp | Yes | |
| `updated_at` | timestamp | Yes | |

**Unique constraint**: `(organization_id, user_id)` -- a user can belong to an organization only once.

### Model

**File**: `/home/keven/Documents/solidtime-analysis/app/Models/Member.php` (lines 1-80)

```php
class Member extends JetstreamMembership implements AuditableContract
{
    use CustomAuditable;
    use HasFactory;
    use HasUuids;

    protected $table = 'members';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class, 'member_id');
    }

    public function projectMembers(): HasMany
    {
        return $this->hasMany(ProjectMember::class, 'member_id');
    }
}
```

**Traits applied:**

- `HasUuids` (`/home/keven/Documents/solidtime-analysis/app/Models/Concerns/HasUuids.php`, line 9) -- wraps Laravel's `HasUuids` trait, generates UUID v4 via `Ramsey\Uuid\Uuid::uuid4()`.
- `CustomAuditable` (`/home/keven/Documents/solidtime-analysis/app/Models/Concerns/CustomAuditable.php`, line 9) -- wraps `OwenIt\Auditing\Auditable` with a `disableAuditing()` helper that sets `$auditEvents = []`.
- `HasFactory` -- standard Laravel factory support.

**Relationships:**

| Relationship | Type | Target | FK |
|-------------|------|--------|-----|
| `user()` | BelongsTo | `User` | `user_id` |
| `organization()` | BelongsTo | `Organization` | `organization_id` |
| `timeEntries()` | HasMany | `TimeEntry` | `member_id` |
| `projectMembers()` | HasMany | `ProjectMember` | `member_id` |

**Extends**: `JetstreamMembership` -- this is a pivot model, not a standard Eloquent model. It is the `members` table serving as the many-to-many join between `users` and `organizations`.

### Factory

**File**: `/home/keven/Documents/solidtime-analysis/database/factories/MemberFactory.php` (lines 1-88)

```php
class MemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'billable_rate' => null,
            'role' => Role::Employee,
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
        ];
    }

    public function role(Role $role): static { /* ... */ }
    public function forOrganization(Organization $organization): static { /* ... */ }
    public function forUser(User $user): static { /* ... */ }
    public function billableRate(?int $billableRate): self { /* ... */ }
    public function withBillableRate(): self { /* ... */ }
}
```

The factory uses the `for{Relation}()` naming convention for state methods.

### PTO Integration Points

New relationships to add to `Member` model:

```php
public function timeOffBalances(): HasMany
{
    return $this->hasMany(TimeOffBalance::class, 'member_id');
}

public function timeOffRequests(): HasMany
{
    return $this->hasMany(TimeOffRequest::class, 'member_id');
}
```

The `weekly_capacity` column will be added via shared foundation migration FOUND-006 (`2026_02_28_000001_add_weekly_capacity_to_members.php`), providing the expected weekly hours for attendance/overtime calculations.

---

## 3. Organization Configuration Patterns

### Database Schema

**File**: `/home/keven/Documents/solidtime-analysis/database/migrations/2020_05_21_100000_create_organizations_table.php` (lines 16-24)

```php
Schema::create('organizations', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id')->index();  // Owner
    $table->string('name');
    $table->boolean('personal_team');
    $table->integer('billable_rate')->unsigned()->nullable();
    $table->string('currency', 3);
    $table->timestamps();
});
```

Subsequent migrations add columns for settings. Key additions:

| Column | Type | Migration | Purpose |
|--------|------|-----------|---------|
| `employees_can_see_billable_rates` | boolean | `2024_10_01_143608_*` | Feature toggle |
| `employees_can_manage_tasks` | boolean | `2025_10_24_120845_*` | Feature toggle |
| `prevent_overlapping_time_entries` | boolean | `2025_10_02_000001_*` | Feature toggle |
| `number_format` | string (enum) | `2025_04_03_101827_*` | Localization |
| `currency_format` | string (enum) | `2025_04_03_101827_*` | Localization |
| `date_format` | string (enum) | `2025_04_03_101827_*` | Localization |
| `interval_format` | string (enum) | `2025_04_03_101827_*` | Localization |
| `time_format` | string (enum) | `2025_04_03_101827_*` | Localization |

### Model Casts

**File**: `/home/keven/Documents/solidtime-analysis/app/Models/Organization.php` (lines 69-81)

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

### Configuration Pattern Summary

- **Column-based settings** -- each org setting is a dedicated column, not stored in a JSON blob.
- **Boolean flags** for feature toggles (e.g., `employees_can_manage_tasks`).
- **Enum casts** for format preferences, using PHP 8.1 backed enums in `app/Enums/`.
- **Nullable integers** for optional monetary amounts (stored in cents).
- **Fillable restriction** -- only `name` and `personal_team` are mass-assignable (line 88-91). All other fields are set explicitly in controller/service code.

### PTO Integration Points

Per AMD-07, add `fiscal_year_start_month` to the organizations table via the shared foundation migration:

```php
$table->unsignedTinyInteger('fiscal_year_start_month')
    ->nullable()
    ->after('default_weekly_capacity');
```

Additionally, per SF-06, `default_weekly_capacity` (unsigned int, default 144000 = 40h in seconds) will be added.

New relationships to add to `Organization` model:

```php
public function timeOffPolicies(): HasMany
{
    return $this->hasMany(TimeOffPolicy::class, 'organization_id');
}

public function holidays(): HasMany
{
    return $this->hasMany(Holiday::class, 'organization_id');
}
```

---

## 4. User-Member Multi-Org Relationship

### User Model

**File**: `/home/keven/Documents/solidtime-analysis/app/Models/User.php` (lines 1-224)

Key properties:

```php
/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string $timezone            // IANA timezone string
 * @property bool $is_placeholder        // Placeholder users cannot log in
 * @property Weekday $week_start         // User's preferred week start day
 * @property string|null $current_team_id // Current organization context
 * @property Collection<int, Organization> $organizations
 */
```

### Multi-Org Pattern

**File**: `/home/keven/Documents/solidtime-analysis/app/Models/User.php` (lines 150-160)

```php
public function organizations(): BelongsToMany
{
    return $this->belongsToMany(Organization::class, Member::class)
        ->withPivot(['id', 'role', 'billable_rate'])
        ->withTimestamps()
        ->as('membership');
}
```

The relationship flows as:

```
User --[members]--> Organization
  |                     |
  +-- current_team_id --+  (active org)
```

A single `User` can belong to many `Organization` entities, each via a unique `Member` record. The `current_team_id` on the users table tracks which organization the user is actively working in.

### Placeholder Users

**File**: `/home/keven/Documents/solidtime-analysis/app/Models/User.php` (lines 205-208)

```php
public function scopeActive(Builder $builder): void
{
    $builder->where('is_placeholder', '=', false);
}
```

Placeholder users (`is_placeholder = true`) are used for data imports. They cannot log in and are excluded from all email/notification operations. The `Placeholder` role in the `Role` enum has an empty permissions array.

### Implications for PTO

1. **All PTO data must key on `member_id`, never `user_id`** -- this ensures strict per-organization isolation.
2. **Placeholder members must be excluded from accrual calculations** -- check `$member->role !== Role::Placeholder->value` or join to user and check `is_placeholder`.
3. **The `member_id` uniquely identifies a user-within-an-organization** -- the `(organization_id, user_id)` unique constraint on `members` guarantees this.
4. **Balance queries must accept `member_id`** for the "my balances" endpoint, obtained via the `$this->member($organization)` base controller helper.

### Base Controller Helpers

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Controller.php` (lines 40-54)

```php
protected function member(Organization $organization): Member
{
    $user = $this->user();
    $member = Member::query()
        ->whereBelongsTo($organization, 'organization')
        ->whereBelongsTo($user, 'user')
        ->first();
    if ($member === null) {
        throw new AuthorizationException;
    }
    return $member;
}
```

This helper resolves the current user's `Member` record for a given organization. PTO controllers will use this extensively.

---

## 5. Scheduled Command Patterns

### Kernel Registration

**File**: `/home/keven/Documents/solidtime-analysis/app/Console/Kernel.php` (lines 15-50)

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('time-entry:send-still-running-mails')
        ->when(fn (): bool => config('scheduling.tasks.time_entry_send_still_running_mails'))
        ->everyTenMinutes();

    $schedule->command('auth:send-mails-expiring-api-tokens')
        ->when(fn (): bool => config('scheduling.tasks.auth_send_mails_expiring_api_tokens'))
        ->everyTenMinutes();

    // Self-hosting commands use deterministic random scheduling based on app key...
    $schedule->command('self-host:database-consistency')
        ->when(fn (): bool => config('scheduling.tasks.self_hosting_database_consistency'))
        ->everySixHours();
}

protected function commands(): void
{
    $this->load(__DIR__.'/Commands');  // Auto-discovers all commands
}
```

### Config Gating

**File**: `/home/keven/Documents/solidtime-analysis/config/scheduling.php` (lines 1-14)

```php
return [
    'tasks' => [
        'time_entry_send_still_running_mails' => (bool) env('SCHEDULING_TASK_TIME_ENTRY_SEND_STILL_RUNNING_MAILS', true),
        'auth_send_mails_expiring_api_tokens' => (bool) env('SCHEDULING_TASK_AUTH_SEND_MAILS_EXPIRING_API_TOKENS', true),
        'self_hosting_check_for_update' => (bool) env('SCHEDULING_TASK_SELF_HOSTING_CHECK_FOR_UPDATE', true),
        'self_hosting_telemetry' => (bool) env('SCHEDULING_TASK_SELF_HOSTING_TELEMETRY', true),
        'self_hosting_database_consistency' => (bool) env('SCHEDULING_TASK_SELF_HOSTING_DATABASE_CONSISTENCY', false),
    ],
];
```

Pattern: Each scheduled task has a corresponding boolean config entry driven by an environment variable.

### Command Implementation Pattern

**File**: `/home/keven/Documents/solidtime-analysis/app/Console/Commands/TimeEntry/TimeEntrySendStillRunningMailsCommand.php` (lines 16-76)

```php
class TimeEntrySendStillRunningMailsCommand extends Command
{
    protected $signature = 'time-entry:send-still-running-mails '
        . '{ --dry-run : Do not actually send emails or save anything to the database, just output what would happen }';

    protected $description = 'Sends emails to users who have running time entries for more than 8 hours.';

    public function handle(): int
    {
        $this->comment('Sending still running time entry emails...');
        $dryRun = (bool) $this->option('dry-run');

        $sentMails = 0;
        TimeEntry::query()
            ->whereNull('end')
            ->where('start', '<', now()->subHours(8))
            ->whereNull('still_active_email_sent_at')    // <-- Idempotency sentinel
            ->whereHas('user', function (Builder $query): void {
                $query->where('is_placeholder', '=', false);  // <-- Exclude placeholders
            })
            ->orderBy('created_at', 'asc')
            ->chunk(500, function (Collection $timeEntries) use ($dryRun, &$sentMails): void {
                foreach ($timeEntries as $timeEntry) {
                    $sentMails++;
                    if (! $dryRun) {
                        Mail::to($timeEntry->user->email)
                            ->queue(new TimeEntryStillRunningMail($timeEntry, $timeEntry->user));
                        $timeEntry->still_active_email_sent_at = Carbon::now();
                        $timeEntry->save();
                    }
                }
            });

        $this->comment('Finished sending '.$sentMails.' still running time entry emails...');
        return self::SUCCESS;
    }
}
```

### Patterns to Follow for Accrual Command

| Pattern | Example | How PTO Accrual Applies |
|---------|---------|------------------------|
| Config gating | `->when(fn () => config('scheduling.tasks.*'))` | Add `time_off_accrue_balances` to `config/scheduling.php` |
| Dry-run option | `--dry-run` flag | Include for safe testing |
| Idempotency | `whereNull('still_active_email_sent_at')` | Use `last_accrual_date` on `TimeOffBalance` |
| Placeholder exclusion | `whereHas('user', fn($q) => $q->where('is_placeholder', false))` | Skip placeholder members in accrual |
| Chunked processing | `->chunk(500, ...)` | Process members in chunks |
| Queue emails | `Mail::to(...)->queue(...)` | Queue notifications |
| Return code | `return self::SUCCESS` | Standard command exit |

### Existing Console Commands

**Location**: `/home/keven/Documents/solidtime-analysis/app/Console/Commands/`

| Directory | Command | Schedule |
|-----------|---------|----------|
| `TimeEntry/` | `TimeEntrySendStillRunningMailsCommand` | Every 10 minutes |
| `Auth/` | `AuthSendReminderForExpiringApiTokensCommand` | Every 10 minutes |
| `Report/` | `ReportSetExpiredToPrivateCommand` | Not scheduled |
| `SelfHost/` | `SelfHostCheckForUpdateCommand` | Twice daily |
| `SelfHost/` | `SelfHostTelemetryCommand` | Twice daily |
| `SelfHost/` | `SelfHostDatabaseConsistency` | Every 6 hours |
| `Admin/` | `OrganizationDeleteCommand` | Not scheduled (manual) |
| `Admin/` | `UserCreateCommand` | Not scheduled (manual) |
| `Admin/` | `Admin/UserVerifyCommand` | Not scheduled (manual) |
| `Test/` | Various test commands | Not scheduled |

The accrual command should live at `app/Console/Commands/TimeOff/AccrueTimeOffBalancesCommand.php` following the directory pattern.

---

## 6. Timezone-Aware Date Handling

### TimezoneService

**File**: `/home/keven/Documents/solidtime-analysis/app/Service/TimezoneService.php` (lines 12-328)

```php
class TimezoneService
{
    // Extensive legacy timezone mapping (275+ entries)
    private const array LEGACY_TIMEZONES_MAP = [ /* ... */ ];

    public function getTimezoneFromUser(User $user): CarbonTimeZone
    {
        try {
            return new CarbonTimeZone($user->timezone);
        } catch (\Exception $e) {
            Log::error('User has a invalid timezone', [
                'user_id' => $user->getKey(),
                'timezone' => $user->timezone,
            ]);
            return new CarbonTimeZone('UTC');
        }
    }

    public function getShiftFromUtc(CarbonTimeZone $timeZone): int
    {
        return $timeZone->getOffset(Carbon::now());
    }
}
```

### DashboardService -- Timezone-Aware SQL Queries

**File**: `/home/keven/Documents/solidtime-analysis/app/Service/DashboardService.php` (lines 138-174)

The codebase uses a raw SQL interval offset approach for timezone handling:

```php
public function getDailyTrackedHours(User $user, Organization $organization, int $days): array
{
    $timezone = $this->timezoneService->getTimezoneFromUser($user);
    $timezoneShift = $this->timezoneService->getShiftFromUtc($timezone);

    if ($timezoneShift > 0) {
        $dateWithTimeZone = 'start + INTERVAL \''.$timezoneShift.' second\'';
    } elseif ($timezoneShift < 0) {
        $dateWithTimeZone = 'start - INTERVAL \''.abs($timezoneShift).' second\'';
    } else {
        $dateWithTimeZone = 'start';
    }

    $query = TimeEntry::query()
        ->select(DB::raw('DATE('.$dateWithTimeZone.') as date, ...'))
        ->groupBy(DB::raw('DATE('.$dateWithTimeZone.')'));
}
```

### Week Boundary Calculations

**File**: `/home/keven/Documents/solidtime-analysis/app/Service/DashboardService.php` (lines 123-129)

```php
private function constrainDateByCurrentWeek(Builder $builder, CarbonTimeZone $timeZone, Weekday $startOfWeek): Builder
{
    return $builder->whereBetween('start', [
        Carbon::now($timeZone)->startOfWeek($startOfWeek->carbonWeekDay())->utc(),
        Carbon::now($timeZone)->endOfWeek($startOfWeek->toEndOfWeek()->carbonWeekDay())->utc(),
    ]);
}
```

### Weekday Enum

**File**: `/home/keven/Documents/solidtime-analysis/app/Enums/Weekday.php` (lines 10-63)

```php
enum Weekday: string
{
    use LaravelEnumHelper;

    case Monday = 'monday';
    case Tuesday = 'tuesday';
    // ...
    case Sunday = 'sunday';

    public function carbonWeekDay(): int
    {
        return match ($this) {
            Weekday::Monday => Carbon::MONDAY,
            // ...
        };
    }

    public function toEndOfWeek(): self
    {
        return match ($this) {
            Weekday::Monday => Weekday::Sunday,
            // ...
        };
    }
}
```

### PTO Date Handling Recommendations

1. **Use DATE columns** for `TimeOffRequest.start_date`, `TimeOffRequest.end_date`, and `Holiday.date`. This avoids timezone confusion entirely since PTO operates on calendar days, not time-of-day.
2. **Do not use TimezoneService for PTO date logic** -- it applies to timestamp-based queries. PTO date comparisons should be simple date equality or range checks.
3. **Working day calculation** should use `Carbon::parse($date)` and check `->isWeekend()` combined with holiday lookup.
4. **Fiscal year boundary** for carryover uses `fiscal_year_start_month` from the organization (defaults to January when null). This is pure date arithmetic, no timezone involved.

---

## 7. CRUD Controller Patterns

### Base Controller Hierarchy

```
App\Http\Controllers\Controller                   (user(), member(), currentOrganization())
  |
  +-- App\Http\Controllers\Api\V1\Controller      (checkPermission(), hasPermission(), canAccessPremiumFeatures())
       |
       +-- TagController, ProjectController, etc.  (feature controllers)
```

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/Controller.php` (lines 1-52)

```php
class Controller extends \App\Http\Controllers\Controller
{
    public function __construct(
        protected PermissionStore $permissionStore,
    ) {}

    protected function checkPermission(Organization $organization, string $permission): void
    {
        if (! $this->permissionStore->has($organization, $permission)) {
            throw new AuthorizationException;
        }
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

### Reference Controller: TagController (Cleanest CRUD)

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/TagController.php` (lines 1-104)

```php
class TagController extends Controller
{
    // Override checkPermission to add entity ownership validation
    protected function checkPermission(Organization $organization, string $permission, ?Tag $tag = null): void
    {
        parent::checkPermission($organization, $permission);
        if ($tag !== null && $tag->organization_id !== $organization->getKey()) {
            throw new AuthorizationException('Tag does not belong to organization');
        }
    }

    // INDEX: List with org scoping and pagination
    public function index(Organization $organization): TagCollection
    {
        $this->checkPermission($organization, 'tags:view');
        $tags = Tag::query()
            ->whereBelongsTo($organization, 'organization')
            ->orderBy('created_at', 'desc')
            ->paginate(config('app.pagination_per_page_default'));
        return new TagCollection($tags);
    }

    // STORE: Create with org association
    public function store(Organization $organization, TagStoreRequest $request): TagResource
    {
        $this->checkPermission($organization, 'tags:create');
        $tag = new Tag;
        $tag->name = $request->input('name');
        $tag->organization()->associate($organization);
        $tag->save();
        return new TagResource($tag);
    }

    // UPDATE: Permission check includes entity ownership
    public function update(Organization $organization, Tag $tag, TagUpdateRequest $request): TagResource
    {
        $this->checkPermission($organization, 'tags:update', $tag);
        $tag->name = $request->input('name');
        $tag->save();
        return new TagResource($tag);
    }

    // DESTROY: Check for dependent entities before deletion
    public function destroy(Organization $organization, Tag $tag): JsonResponse
    {
        $this->checkPermission($organization, 'tags:delete', $tag);
        if (TimeEntry::query()->hasTag($tag)->whereBelongsTo($organization, 'organization')->exists()) {
            throw new EntityStillInUseApiException('tag', 'time_entry');
        }
        $tag->delete();
        return response()->json(null, 204);
    }
}
```

### Reference Controller: ProjectController (Complex CRUD with Services)

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/ProjectController.php` (lines 1-188)

The `ProjectController` shows more complex patterns:

- **Role-based visibility**: `canViewAllProjects` for admin vs employee scoping (line 47-55).
- **Service injection**: `BillableRateService $billableRateService` in the `update` method signature (line 122).
- **Cascading updates**: When billable rate changes, updates propagate to time entries (lines 146-148).
- **DB transactions**: Delete wraps member cleanup + project deletion in a transaction (lines 177-183).
- **Premium feature gating**: `canAccessPremiumFeatures()` check before setting `estimated_time` (line 106).

### Request Validation Pattern

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/Task/TaskStoreRequest.php` (lines 1-62)

```php
/**
 * @property Organization $organization Organization from model binding
 */
class TaskStoreRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'min:1', 'max:255',
                UniqueEloquent::make(Task::class, 'name', function (Builder $builder): Builder {
                    return $builder->where('project_id', '=', $this->input('project_id'));
                })->withCustomTranslation('validation.task_name_already_exists'),
            ],
            'project_id' => [
                'required',
                ExistsEloquent::make(Project::class, null, function (Builder $builder): Builder {
                    return $builder->whereBelongsTo($this->organization, 'organization');
                })->uuid(),
            ],
        ];
    }
}
```

Key patterns:
- `$this->organization` is available from route model binding.
- `ExistsEloquent` validates FK existence with organization scoping.
- `UniqueEloquent` validates uniqueness within a custom scope.
- `->uuid()` enforces UUID format on the input.

### Resource Pattern

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Resources/V1/BaseResource.php` (lines 1-21)

```php
abstract class BaseResource extends JsonResource
{
    protected function formatDateTime(?Carbon $carbon): ?string
    {
        return $carbon?->toIso8601ZuluString();
    }

    protected function formatDate(?Carbon $carbon): ?string
    {
        return $carbon?->format('Y-m-d');
    }
}
```

Example resource:

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Resources/V1/Tag/TagResource.php` (lines 1-34)

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

### Routing Pattern

**File**: `/home/keven/Documents/solidtime-analysis/routes/api.php` (lines 86-93)

```php
Route::name('projects.')->prefix('/organizations/{organization}')->group(static function (): void {
    Route::get('/projects', [ProjectController::class, 'index'])->name('index');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('show');
    Route::post('/projects', [ProjectController::class, 'store'])->name('store')->middleware('check-organization-blocked');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('update')->middleware('check-organization-blocked');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('destroy');
});
```

Key observations:
- Read endpoints (GET) have **no** `check-organization-blocked` middleware.
- Write endpoints (POST, PUT) have `check-organization-blocked` middleware.
- DELETE endpoints may or may not have the middleware (inconsistent -- projects do not, invitations do).
- Route names follow the pattern: `v1.{feature}.{action}`.

### Permission Store

**File**: `/home/keven/Documents/solidtime-analysis/app/Service/PermissionStore.php` (lines 1-102)

```php
class PermissionStore
{
    private array $permissionCache = [];

    public function has(Organization $organization, string $permission): bool
    {
        $user = Auth::user();
        return $this->userHas($organization, $user, $permission);
    }

    private function getPermissionsByUser(Organization $organization, User $user): array
    {
        $role = $organization->users->where('id', $user->getKey())
            ->first()?->membership?->role;

        $roleObj = Jetstream::findRole($role);
        $permissions = $roleObj->permissions ?? [];

        // Dynamic permission augmentation based on org settings
        if ($role === Role::Employee->value && $organization->employees_can_manage_tasks) {
            $permissions = array_merge($permissions, [
                'tasks:create', 'tasks:update', 'tasks:delete',
            ]);
        }

        return $permissions;
    }
}
```

The `PermissionStore` caches permissions per user-org pair and supports dynamic augmentation based on organization settings. PTO permissions will be registered in `JetstreamServiceProvider::configurePermissions()` (or per AMD-03, via `App\Permissions\TimeOffPermissions::register()`).

### Permission Registration

**File**: `/home/keven/Documents/solidtime-analysis/app/Providers/JetstreamServiceProvider.php` (lines 78-280)

```php
protected function configurePermissions(): void
{
    Jetstream::role(Role::Owner->value, 'Owner', [
        'projects:view', 'projects:view:all', 'projects:create', /* ... 50+ permissions ... */
    ]);

    Jetstream::role(Role::Admin->value, 'Administrator', [ /* ... similar list minus billing ... */ ]);
    Jetstream::role(Role::Manager->value, 'Manager', [ /* ... similar list minus org management ... */ ]);
    Jetstream::role(Role::Employee->value, 'Employee', [
        'charts:view:own', 'projects:view', 'tags:view', 'tasks:view', 'clients:view',
        'time-entries:view:own', 'time-entries:create:own', 'time-entries:update:own',
        'time-entries:delete:own', 'organizations:view',
    ]);
    Jetstream::role(Role::Placeholder->value, 'Placeholder', []);
}
```

PTO permissions to add (per AMD-03 via modular registration):

```php
// Owner/Admin: full CRUD on policies, holidays, balances; approve/deny requests
// Manager: approve/deny requests, view all balances
// Employee: view own balances, create/cancel own requests, view active policies, view holidays
```

---

## 8. Approval Workflow Gap Analysis

### Current State

**No approval workflow exists in the codebase.** There are no status enums, no state machine patterns, no reviewer fields on any existing model. PTO will be the first feature to introduce an approval workflow.

### Closest Existing Pattern: Project Archiving

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/ProjectController.php` (lines 128-130)

```php
if ($request->has('is_archived')) {
    $project->archived_at = $request->getIsArchived() ? Carbon::now() : null;
}
```

This is a simple toggle, not a state machine. The `archived_at` timestamp serves as a boolean-like state indicator.

### Existing Enums (No Status Types)

**File**: `/home/keven/Documents/solidtime-analysis/app/Enums/` directory

```
CurrencyFormat.php
DateFormat.php
ExportFormat.php
IntervalFormat.php
NumberFormat.php
Role.php
TimeEntryAggregationType.php
TimeEntryAggregationTypeInterval.php
TimeEntryRoundingType.php
TimeFormat.php
Weekday.php
```

No status-related enums exist. All enums are formatting or type-related.

### Shared Approval Foundation (from SF-05)

**File**: `/home/keven/Documents/solidtime-analysis/.features/SHARED-FOUNDATIONS.md` (lines 160-235)

The shared foundations document defines the approval pattern that PTO must use:

```php
// App\Enums\ApprovalStatus
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

PTO uses a subset: `submitted`, `approved`, `rejected`, `withdrawn` (per AMD-05).

Standard approval columns for the `time_off_requests` table:

```
status           enum (ApprovalStatus)
submitted_at     timestamp nullable
reviewer_id      uuid nullable FK -> members
reviewed_at      timestamp nullable
reviewer_notes   text nullable
```

The `HasApprovalWorkflow` trait provides:

```php
trait HasApprovalWorkflow
{
    public function isEditable(): bool { /* submitted, changes_requested, withdrawn */ }
    public function isSubmitted(): bool { /* === SUBMITTED */ }
    public function isApproved(): bool { /* === APPROVED */ }
    public function reviewer(): BelongsTo { /* -> Member */ }
}
```

### Business Rules (SF-05)

1. **Self-approval is NOT allowed** -- `reviewer_id` must differ from the submitter's `member_id`.
2. **Only Managers, Admins, and Owners** can approve/reject.
3. **Submission locks editing** -- entries in `submitted` or `approved` status cannot be modified without first withdrawing.
4. **Notifications are sent** on every status transition (via SF-04 infrastructure).
5. **Audit logging** captures all status changes via `CustomAuditable`.

### What Must Be Built

| Component | Notes |
|-----------|-------|
| `ApprovalStatus` enum | Shared enum in `app/Enums/` |
| `HasApprovalWorkflow` trait | Shared trait in `app/Traits/` |
| Status transition validation | Service method to enforce valid transitions |
| Self-approval prevention | Check in approve/deny controller methods |
| Notification dispatch | On each transition, via `BaseNotification` |

---

## 9. Frontend Dashboard Widget Patterns

### Dashboard Page

**File**: `/home/keven/Documents/solidtime-analysis/resources/js/Pages/Dashboard.vue` (lines 1-49)

```vue
<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import RecentlyTrackedTasksCard from '@/Components/Dashboard/RecentlyTrackedTasksCard.vue';
import LastSevenDaysCard from '@/Components/Dashboard/LastSevenDaysCard.vue';
import TeamActivityCard from '@/Components/Dashboard/TeamActivityCard.vue';
import ThisWeekOverview from '@/Components/Dashboard/ThisWeekOverview.vue';
import ActivityGraphCard from '@/Components/Dashboard/ActivityGraphCard.vue';
import { useQueryClient } from '@tanstack/vue-query';
import { canViewMembers } from '@/utils/permissions';

const queryClient = useQueryClient();

const refreshDashboardData = () => {
    queryClient.invalidateQueries({ queryKey: ['latestTasks'] });
    queryClient.invalidateQueries({ queryKey: ['lastSevenDays'] });
    queryClient.invalidateQueries({ queryKey: ['dailyTrackedHours'] });
    queryClient.invalidateQueries({ queryKey: ['latestTeamActivity'] });
    queryClient.invalidateQueries({ queryKey: ['weeklyProjectOverview'] });
    queryClient.invalidateQueries({ queryKey: ['totalWeeklyTime'] });
    queryClient.invalidateQueries({ queryKey: ['totalWeeklyBillableTime'] });
    queryClient.invalidateQueries({ queryKey: ['totalWeeklyBillableAmount'] });
    queryClient.invalidateQueries({ queryKey: ['weeklyHistory'] });
    queryClient.invalidateQueries({ queryKey: ['timeEntries'] });
};
</script>

<template>
    <AppLayout title="Dashboard" data-testid="dashboard_view">
        <MainContainer class="pt-5 sm:pt-8 pb-4 sm:pb-6 border-b border-default-background-separator">
            <TimeTracker @change="refreshDashboardData"></TimeTracker>
        </MainContainer>

        <MainContainer class="grid gap-2 sm:gap-4 grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 ...">
            <RecentlyTrackedTasksCard />
            <LastSevenDaysCard />
            <ActivityGraphCard />
            <TeamActivityCard v-if="canViewMembers()" class="flex lg:hidden xl:flex" />
        </MainContainer>

        <MainContainer class="py-5">
            <ThisWeekOverview />
        </MainContainer>
    </AppLayout>
</template>
```

### Dashboard Patterns

| Pattern | Implementation |
|---------|---------------|
| **Widget composition** | Each card is a self-contained component that fetches its own data |
| **Data fetching** | Components use `@tanstack/vue-query` (`useQuery`) internally |
| **Refresh mechanism** | Parent calls `queryClient.invalidateQueries()` for each widget's query key |
| **Permission-gated widgets** | `v-if="canViewMembers()"` conditionally renders team activity card |
| **Layout** | Responsive grid via Tailwind CSS (`grid-cols-1 md:grid-cols-2 lg:grid-cols-3`) |
| **Page wrapper** | All pages use `AppLayout` with a `title` prop and `data-testid` |

### DashboardService (Backend)

**File**: `/home/keven/Documents/solidtime-analysis/app/Service/DashboardService.php` (lines 19-466)

The `DashboardService` provides computed data for all dashboard widgets:

```php
class DashboardService
{
    public function __construct(TimezoneService $timezoneService) { /* ... */ }

    public function getDailyTrackedHours(User $user, Organization $organization, int $days): array { /* ... */ }
    public function getWeeklyHistory(User $user, Organization $organization): array { /* ... */ }
    public function totalWeeklyTime(User $user, Organization $organization): int { /* ... */ }
    public function totalWeeklyBillableTime(User $user, Organization $organization): int { /* ... */ }
    public function totalWeeklyBillableAmount(User $user, Organization $organization): array { /* ... */ }
    public function weeklyProjectOverview(User $user, Organization $organization): array { /* ... */ }
    public function latestTeamActivity(Organization $organization): array { /* ... */ }
    public function latestTasks(User $user, Organization $organization): array { /* ... */ }
    public function lastSevenDays(User $user, Organization $organization): array { /* ... */ }
}
```

### PTO Dashboard Widget Strategy

A `TimeOffBalanceCard` widget can follow the same pattern:

1. Create a Vue component at `resources/js/Components/Dashboard/TimeOffBalanceCard.vue`.
2. Internally use `useQuery` with query key `['timeOffBalances']`.
3. Fetch from the `GET /api/v1/organizations/{org}/time-off-balances/me` endpoint.
4. Display each policy's balance as a progress bar or summary.
5. Add `queryClient.invalidateQueries({ queryKey: ['timeOffBalances'] })` to the parent's refresh function.
6. Gate visibility with a permission check like `v-if="canViewTimeOffBalances()"`.

---

## 10. Calendar View Integration Strategy

### Current Calendar Implementation

**File**: `/home/keven/Documents/solidtime-analysis/resources/js/Pages/Calendar.vue` (lines 1-145)

```typescript
const { data: timeEntryResponse, isLoading: timeEntriesLoading } = useQuery<TimeEntryResponse>({
    queryKey: computed(() => [
        'timeEntry', 'calendar',
        {
            start: expandedDateRange.value.start,
            end: expandedDateRange.value.end,
            organization: getCurrentOrganizationId(),
        },
    ]),
    enabled: enableCalendarQuery,
    queryFn: () =>
        api.getTimeEntries({
            params: { organization: getCurrentOrganizationId() || '' },
            queries: {
                start: expandedDateRange.value.start!,
                end: expandedDateRange.value.end!,
                member_id: getCurrentMembershipId(),
            },
        }),
});
```

The calendar component `TimeEntryCalendar` receives:

```vue
<TimeEntryCalendar
    :time-entries="currentTimeEntries"
    :projects="projects"
    :tasks="tasks"
    :clients="clients"
    :tags="tags"
    :loading="timeEntriesLoading"
    :create-time-entry="createTimeEntry"
    :update-time-entry="updateTimeEntry"
    :delete-time-entry="deleteTimeEntry"
    @dates-change="onDatesChange"
    @refresh="onRefresh" />
```

### Date Range Expansion with Timezone

**File**: `/home/keven/Documents/solidtime-analysis/resources/js/Pages/Calendar.vue` (lines 35-56)

```typescript
const expandedDateRange = computed(() => {
    const dayjs = getDayJsInstance();
    const duration = dayjs(calendarEnd.value).diff(dayjs(calendarStart.value), 'milliseconds');
    const previousStart = dayjs(calendarStart.value).subtract(duration, 'milliseconds');
    const nextEnd = dayjs(calendarEnd.value).add(duration, 'milliseconds');

    // Apply timezone transformations
    const formattedStart = previousStart.utc().tz(getUserTimezone(), true).utc().format();
    const formattedEnd = nextEnd.utc().tz(getUserTimezone(), true).utc().format();

    return { start: formattedStart, end: formattedEnd };
});
```

### Integration Approach: Separate Query + Merge (Recommended)

The cleanest integration is to add a second query for time-off data alongside the existing time entry query:

```typescript
// New query for approved time-off days
const { data: timeOffResponse } = useQuery({
    queryKey: computed(() => [
        'timeOff', 'calendar',
        {
            start: expandedDateRange.value.start,
            end: expandedDateRange.value.end,
            organization: getCurrentOrganizationId(),
        },
    ]),
    enabled: enableCalendarQuery,
    queryFn: () =>
        api.getApprovedTimeOffForCalendar({
            params: { organization: getCurrentOrganizationId() || '' },
            queries: {
                start_date: dayjs(expandedDateRange.value.start).format('YYYY-MM-DD'),
                end_date: dayjs(expandedDateRange.value.end).format('YYYY-MM-DD'),
                member_id: getCurrentMembershipId(),
            },
        }),
});
```

Then merge the data:

```typescript
const calendarEvents = computed(() => {
    const entries = timeEntryResponse.value?.data || [];
    const timeOff = (timeOffResponse.value?.data || []).map(day => ({
        id: `pto-${day.id}`,
        type: 'time-off' as const,
        date: day.date,
        title: day.policy_name,
        color: day.policy_color,
        allDay: true,
    }));
    return [...entries, ...timeOff];
});
```

The `TimeEntryCalendar` component will need to be extended to recognize `type: 'time-off'` events and render them as all-day background blocks rather than time-ranged blocks. Holidays should also appear in the calendar as non-editable all-day events.

### Backend API for Calendar Integration

A new endpoint `GET /api/v1/organizations/{org}/time-off-requests/calendar` should return approved requests and holidays for a date range, flattened to individual days:

```php
public function calendarDays(Organization $organization, CalendarTimeOffRequest $request): JsonResponse
{
    $startDate = Carbon::parse($request->input('start_date'));
    $endDate = Carbon::parse($request->input('end_date'));

    $approvedRequests = TimeOffRequest::query()
        ->whereBelongsTo($organization, 'organization')
        ->where('status', ApprovalStatus::APPROVED)
        ->where('start_date', '<=', $endDate)
        ->where('end_date', '>=', $startDate)
        ->with('policy:id,name,color')
        ->get();

    $holidays = Holiday::query()
        ->whereBelongsTo($organization, 'organization')
        ->forDateRange($startDate, $endDate)  // Custom scope handling recurring
        ->get();

    // Flatten to individual days...
}
```

---

## 11. Risk Assessment

### Risk 1: Accrual Calculation Complexity

**Severity**: Medium
**Probability**: High

**Details**: Prorated accruals for mid-period hires, leap year handling for recurring holidays, per-hour-worked accrual mode requiring TimeEntry aggregation, and idempotent re-execution all add complexity.

**Mitigations**:
- Store all accrual amounts as `DECIMAL(8,2)` hours.
- Use `last_accrual_date` on `TimeOffBalance` as idempotency sentinel (same pattern as `still_active_email_sent_at` on time entries).
- For proration: calculate `days_worked / days_in_period * accrual_amount`.
- For per-hour-worked: aggregate `TimeEntry` durations for the period, then apply `accrual_amount` rate.
- Comprehensive unit test matrix covering: normal accrual, prorated first period, cap exceeded, carryover at year boundary, waiting period not met, placeholder excluded, double-run idempotency.

### Risk 2: Timezone Edge Cases with Date-Based PTO

**Severity**: Medium
**Probability**: Medium

**Details**: A member in UTC-12 requests "Feb 10" which is already "Feb 11" in UTC+12. If PTO dates are stored as timestamps, the same calendar day would resolve to different UTC ranges for different users.

**Mitigations**:
- **Store PTO dates as DATE columns** (not timestamps). The PRD schema already specifies `DATE NOT NULL` for `start_date` and `end_date`.
- All PTO date comparisons use simple `WHERE date BETWEEN ? AND ?` without timezone offset calculations.
- Working day calculation happens server-side using the organization's context, not the user's timezone.
- Calendar display transforms dates client-side using `dayjs` and the user's timezone (same as existing time entry rendering).

### Risk 3: Multi-Org Member Balance Isolation

**Severity**: High
**Probability**: Low

**Details**: If any query accidentally uses `user_id` instead of `member_id`, balances could leak across organizations. A user who is a member of Org A and Org B must have completely separate PTO balances.

**Mitigations**:
- Enforce `member_id` FK on all PTO tables. The `TimeOffBalance` table has `UNIQUE(member_id, policy_id, year)`.
- All API endpoints receive `Organization $organization` via route model binding and filter queries with `->whereBelongsTo($organization, 'organization')`.
- The `$this->member($organization)` helper in the base controller ensures the current user's member record is resolved within the correct organization context.
- Test case: Create a user with memberships in two organizations, add balances in both, verify API only returns the correct organization's data.

### Risk 4: First Approval Pattern -- No Precedent

**Severity**: Medium
**Probability**: Medium

**Details**: Since no approval workflow exists in the codebase, there is no battle-tested pattern to copy. The shared foundations (SF-05) define the pattern theoretically, but it has not been implemented yet.

**Mitigations**:
- Use the shared `ApprovalStatus` enum and `HasApprovalWorkflow` trait exactly as specified in SF-05.
- Self-approval prevention is a simple check in the approve/deny controller methods: `if ($request->member_id === $this->member($organization)->id) throw ...`.
- State transition validation should be explicit in a `TimeOffRequestService`:
  ```
  submitted -> approved (by reviewer)
  submitted -> rejected (by reviewer)
  submitted -> withdrawn (by requester)
  approved  -> withdrawn (by requester, returns hours to balance)
  ```
- Every transition should fire a notification and be audit-logged via `CustomAuditable`.

### Risk 5: Balance Calculation Performance

**Severity**: Low
**Probability**: Medium

**Details**: If `used_hours` and `pending_hours` are computed on every request by summing approved/pending requests, queries could become slow for organizations with many requests.

**Mitigations**:
- The `TimeOffBalance` model stores `used_hours` and `pending_hours` as denormalized columns that are updated whenever a request status changes.
- When a request is approved: increment `used_hours`, decrement `pending_hours`.
- When a request is created: increment `pending_hours`.
- When a request is cancelled/denied: decrement `pending_hours`.
- The `available_hours` is a computed value: `accrued_hours + carryover_hours + manual_adjustment - used_hours - pending_hours`.
- Index the `(member_id, policy_id, year)` composite for fast lookups.

### Risk 6: Year Rollover and Carryover

**Severity**: Medium
**Probability**: High (happens every year)

**Details**: At the fiscal year boundary, unused hours must carry over (up to `max_carryover`) and new balance records must be created for the new year. If the fiscal year starts in a non-January month (per AMD-07), this adds complexity.

**Mitigations**:
- Create a separate `CarryoverTimeOffBalancesCommand` that runs daily and checks if a new fiscal year has started for each organization.
- Use the `fiscal_year_start_month` from the organization (default: January).
- For each member-policy pair, create a new year's balance record with `carryover_hours = min(previous_year.available_hours, policy.max_carryover)`.
- The carryover command must also be idempotent -- check if a balance for the new year already exists before creating one.

---

## 12. Essential File Reference

### Backend -- Models & Infrastructure

| File | Relevance |
|------|-----------|
| `/home/keven/Documents/solidtime-analysis/app/Models/Member.php` | Primary FK target for PTO data |
| `/home/keven/Documents/solidtime-analysis/app/Models/Organization.php` | Org-level settings, new relationships |
| `/home/keven/Documents/solidtime-analysis/app/Models/User.php` | Multi-org, timezone, placeholder flag |
| `/home/keven/Documents/solidtime-analysis/app/Models/Concerns/HasUuids.php` | Trait for UUID primary keys |
| `/home/keven/Documents/solidtime-analysis/app/Models/Concerns/CustomAuditable.php` | Trait for audit trail |
| `/home/keven/Documents/solidtime-analysis/app/Service/TimezoneService.php` | Timezone resolution for users |
| `/home/keven/Documents/solidtime-analysis/app/Service/DashboardService.php` | Dashboard data patterns, timezone SQL |
| `/home/keven/Documents/solidtime-analysis/app/Service/PermissionStore.php` | Permission checking with caching |

### Backend -- Controllers & Requests

| File | Relevance |
|------|-----------|
| `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Controller.php` | Base: `user()`, `member()` helpers |
| `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/Controller.php` | API base: `checkPermission()`, `hasPermission()` |
| `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/TagController.php` | Cleanest CRUD reference |
| `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/ProjectController.php` | Complex CRUD with services |
| `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/MemberController.php` | Member operations, placeholder handling |
| `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/BaseFormRequest.php` | Base request class |
| `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/Task/TaskStoreRequest.php` | Validation with ExistsEloquent/UniqueEloquent |

### Backend -- Resources & Enums

| File | Relevance |
|------|-----------|
| `/home/keven/Documents/solidtime-analysis/app/Http/Resources/V1/BaseResource.php` | `formatDateTime()`, `formatDate()` helpers |
| `/home/keven/Documents/solidtime-analysis/app/Http/Resources/V1/Tag/TagResource.php` | Simple resource reference |
| `/home/keven/Documents/solidtime-analysis/app/Enums/Role.php` | Role enum (Owner, Admin, Manager, Employee, Placeholder) |
| `/home/keven/Documents/solidtime-analysis/app/Enums/Weekday.php` | Enum with helper methods pattern |
| `/home/keven/Documents/solidtime-analysis/app/Exceptions/Api/EntityStillInUseApiException.php` | Error pattern for delete prevention |

### Backend -- Scheduling & Commands

| File | Relevance |
|------|-----------|
| `/home/keven/Documents/solidtime-analysis/app/Console/Kernel.php` | Command scheduling with config gates |
| `/home/keven/Documents/solidtime-analysis/config/scheduling.php` | Config-based scheduling toggles |
| `/home/keven/Documents/solidtime-analysis/app/Console/Commands/TimeEntry/TimeEntrySendStillRunningMailsCommand.php` | Reference for chunked, idempotent, dry-run command |
| `/home/keven/Documents/solidtime-analysis/app/Console/Commands/Auth/AuthSendReminderForExpiringApiTokensCommand.php` | Alternative command pattern reference |

### Backend -- Permissions & Auth

| File | Relevance |
|------|-----------|
| `/home/keven/Documents/solidtime-analysis/app/Providers/JetstreamServiceProvider.php` | Permission registration per role |
| `/home/keven/Documents/solidtime-analysis/routes/api.php` | Route structure and middleware patterns |

### Backend -- Testing

| File | Relevance |
|------|-----------|
| `/home/keven/Documents/solidtime-analysis/tests/TestCaseWithDatabase.php` | `createUserWithPermission()`, `createUserWithRole()` |
| `/home/keven/Documents/solidtime-analysis/tests/Unit/Endpoint/Api/V1/ApiEndpointTestAbstract.php` | Base test class for API endpoints |
| `/home/keven/Documents/solidtime-analysis/tests/Unit/Endpoint/Api/V1/TagEndpointTest.php` | Clean endpoint test reference |
| `/home/keven/Documents/solidtime-analysis/database/factories/MemberFactory.php` | Factory with `for{Relation}()` pattern |
| `/home/keven/Documents/solidtime-analysis/database/factories/OrganizationFactory.php` | Factory with `withOwner()` pattern |

### Frontend

| File | Relevance |
|------|-----------|
| `/home/keven/Documents/solidtime-analysis/resources/js/Pages/Dashboard.vue` | Widget composition, query invalidation |
| `/home/keven/Documents/solidtime-analysis/resources/js/Pages/Calendar.vue` | Calendar data fetching, timezone handling |

### Cross-Feature References

| File | Relevance |
|------|-----------|
| `/home/keven/Documents/solidtime-analysis/.features/SHARED-FOUNDATIONS.md` | SF-04 notifications, SF-05 approval pattern, SF-06 weekly capacity |
| `/home/keven/Documents/solidtime-analysis/.features/07-pto-time-off/PRD.md` | Full PRD with amendments |

---

**End of Codebase Analysis**
