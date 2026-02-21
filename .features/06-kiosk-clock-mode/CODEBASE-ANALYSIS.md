# Codebase Analysis: Kiosk & Clock Mode Feature

**Date:** 2026-02-06  
**Feature ID:** 06-kiosk-clock-mode  
**Analyst:** Claude (Sonnet 4.5)

---

## Table of Contents

1. [Authentication System Deep Dive](#1-authentication-system-deep-dive)
2. [Running Timer Pattern Analysis](#2-running-timer-pattern-analysis)
3. [TimeEntryService Analysis](#3-timeentryservice-analysis)
4. [Member Model Structure](#4-member-model-structure)
5. [Vue SPA Entry Point Analysis](#5-vue-spa-entry-point-analysis)
6. [Vite Configuration Analysis](#6-vite-configuration-analysis)
7. [Middleware Patterns](#7-middleware-patterns)
8. [Hash/Encryption Patterns](#8-hashencryption-patterns)
9. [Rate Limiting Infrastructure](#9-rate-limiting-infrastructure)
10. [Risk Assessment](#10-risk-assessment)
11. [Essential Files](#11-essential-files)

---

## 1. Authentication System Deep Dive

### 1.1 Configuration Structure

**File:** `/home/keven/Documents/solidtime-analysis/config/auth.php`

The authentication configuration reveals a clean, Laravel-standard setup:

```php
'defaults' => [
    'guard' => 'web',
    'passwords' => 'users',
],

'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    'api' => [
        'driver' => 'passport',
        'provider' => 'users',
    ],
],

'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => App\Models\User::class,
    ],
],
```

**Key Insights:**

1. **Two Guards Only:** Currently only `web` (session-based) and `api` (Passport) are defined
2. **Single Provider:** All guards use the `users` provider (Eloquent-based on `User` model)
3. **No Custom Guards:** No existing custom authentication guards to reference
4. **Passport Integration:** API guard uses Laravel Passport OAuth2 implementation

**Implementation Path for Kiosk:**

The kiosk feature will require adding a third guard:

```php
'guards' => [
    'web' => [...],
    'api' => [...],
    'kiosk' => [
        'driver' => 'kiosk-token',  // Custom driver
        'provider' => 'kiosks',      // New provider
    ],
],

'providers' => [
    'users' => [...],
    'kiosks' => [
        'driver' => 'eloquent',
        'model' => App\Models\Kiosk::class,
    ],
],
```

### 1.2 Passport Configuration

**File:** `/home/keven/Documents/solidtime-analysis/config/passport.php`

```php
return [
    'guard' => 'web',
    'private_key' => env('PASSPORT_PRIVATE_KEY'),
    'public_key' => env('PASSPORT_PUBLIC_KEY'),
    'connection' => env('PASSPORT_CONNECTION'),
];
```

**File:** `/home/keven/Documents/solidtime-analysis/app/Providers/AuthServiceProvider.php` (lines 33-65)

```php
// define scopes for passport tokens
Passport::tokensCan([
    'create' => 'Create resources',
    'read' => 'Read Resources',
    'update' => 'Update Resources',
    'delete' => 'Delete Resources',
]);

Passport::setDefaultScope(['read']);

Passport::useTokenModel(Token::class);
Passport::useRefreshTokenModel(RefreshToken::class);
Passport::useAuthCodeModel(AuthCode::class);
Passport::useClientModel(Client::class);

Passport::personalAccessTokensExpireIn(now()->addMonths(12));
```

**Insights:**

- Passport is configured but uses custom models in `App\Models\Passport\*`
- Personal access tokens expire after 12 months
- The kiosk token system should NOT use Passport (too heavyweight for device tokens)
- Kiosk tokens should be simpler: random string hashed in database, validated per request

### 1.3 Authentication Flow Patterns

**Auth Middleware:** `/home/keven/Documents/solidtime-analysis/app/Http/Middleware/Authenticate.php`

```php
protected function redirectTo(Request $request): ?string
{
    return $request->expectsJson() ? null : route('login');
}
```

Simple redirect to login for unauthenticated web requests. API requests return null (triggering 401).

**Current Auth Check Pattern (API Controllers):**

**File:** `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/Controller.php`

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

    protected function user(): User
    {
        return Auth::user();
    }

    protected function member(Organization $organization): Member
    {
        $user = $this->user();
        return $organization->members()->whereBelongsTo($user)->firstOrFail();
    }
}
```

**Kiosk Auth Requirements:**

- Kiosk guard must authenticate the **Kiosk device** (not a user)
- The kiosk controller will NOT use `PermissionStore` (device-level, not user permissions)
- Kiosk endpoints must validate the kiosk token on EVERY request
- Member identification happens AFTER kiosk auth (via PIN/QR)

---

## 2. Running Timer Pattern Analysis

### 2.1 Active Time Entry Detection

**File:** `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/UserTimeEntryController.php` (lines 24-48)

```php
public function myActive(): JsonResource
{
    $user = $this->user();

    $activeTimeEntriesOfUser = TimeEntry::query()
        ->whereBelongsTo($user, 'user')
        ->whereNull('end')  // <-- RUNNING TIMER INDICATOR
        ->orderBy('start', 'desc')
        ->get();

    if ($activeTimeEntriesOfUser->count() > 1) {
        Log::warning('User has more than one active time entry.', [
            'user' => $user->getKey(),
        ]);
    }

    $activeTimeEntry = $activeTimeEntriesOfUser->first();

    if ($activeTimeEntry !== null) {
        return new TimeEntryResource($activeTimeEntry);
    } else {
        throw new ModelNotFoundException('No active time entry');
    }
}
```

**Key Pattern:**

- **Running timer = `end IS NULL`**
- System expects only ONE active time entry per user
- Multiple active entries trigger a warning (but are allowed)

### 2.2 Timer Creation (Clock In)

**File:** `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/TimeEntryController.php` (lines 577-617)

```php
public function store(Organization $organization, TimeEntryStoreRequest $request): JsonResource
{
    /** @var Member $member */
    $member = Member::query()->findOrFail($request->input('member_id'));
    
    if ($member->user_id === Auth::id()) {
        $this->checkPermission($organization, 'time-entries:create:own');
    } else {
        $this->checkPermission($organization, 'time-entries:create:all');
    }

    // VALIDATION: Prevent multiple running timers
    if ($request->input('end') === null && 
        TimeEntry::query()->whereBelongsTo($member, 'member')->where('end', null)->exists()) {
        throw new TimeEntryStillRunningApiException;
    }

    // Overlap check (if enabled in organization settings)
    $start = Carbon::parse($request->input('start'));
    $end = $request->input('end') !== null ? Carbon::parse($request->input('end')) : null;
    $this->assertNoOverlap($organization, $member, $start, $end);

    $project = $request->input('project_id') !== null ? 
        Project::findOrFail((string) $request->input('project_id')) : null;
    $client = $project?->client;
    $task = $request->input('task_id') !== null ? 
        $project->tasks()->findOrFail((string) $request->input('task_id')) : null;

    $timeEntry = new TimeEntry;
    $timeEntry->fill($request->validated());
    $timeEntry->client()->associate($client);
    $timeEntry->user_id = $member->user_id;
    $timeEntry->description = $request->input('description') ?? '';
    $timeEntry->organization()->associate($organization);
    $timeEntry->setComputedAttributeValue('billable_rate');
    $timeEntry->save();

    if ($project !== null) {
        RecalculateSpentTimeForProject::dispatch($project);  // ASYNC JOB
    }
    if ($task !== null) {
        RecalculateSpentTimeForTask::dispatch($task);  // ASYNC JOB
    }

    return new TimeEntryResource($timeEntry);
}
```

**Critical Validation Rules:**

1. **One running timer per member:** Enforced via database query before creation
2. **Overlap prevention:** Optional, controlled by `$organization->prevent_overlapping_time_entries`
3. **Jobs dispatched:** Project/Task spent time recalculation happens asynchronously
4. **Billable rate computed:** Uses `BillableRateService` to determine rate at save time

### 2.3 Timer Stop (Clock Out)

**File:** `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/TimeEntryController.php` (lines 626-680)

```php
public function update(Organization $organization, TimeEntry $timeEntry, TimeEntryUpdateRequest $request): JsonResource
{
    // Permission checks...
    
    // VALIDATION: Cannot restart a stopped timer
    if ($timeEntry->end !== null && $request->has('end') && $request->input('end') === null) {
        throw new TimeEntryCanNotBeRestartedApiException;
    }

    // Overlap check for update (exclude current)
    $effectiveMember = $request->has('member_id') ? 
        Member::query()->findOrFail($request->input('member_id')) : $timeEntry->member;
    $effectiveStart = $request->has('start') ? 
        Carbon::parse($request->input('start')) : $timeEntry->start;
    $effectiveEnd = $request->has('end') ? 
        ($request->input('end') !== null ? Carbon::parse($request->input('end')) : null) 
        : $timeEntry->end;
    $this->assertNoOverlap($organization, $effectiveMember, $effectiveStart, $effectiveEnd, $timeEntry);

    $oldProject = $timeEntry->project;
    $oldTask = $timeEntry->task;

    // Update logic...
    $timeEntry->fill($request->validated());
    $timeEntry->description = $request->input('description', $timeEntry->description) ?? '';
    $timeEntry->setComputedAttributeValue('billable_rate');
    $timeEntry->save();

    // Dispatch recalculation jobs for old and new projects/tasks
    if ($oldProject !== null) {
        RecalculateSpentTimeForProject::dispatch($oldProject);
    }
    // ... more dispatches

    return new TimeEntryResource($timeEntry);
}
```

**Stop Pattern:**

- Setting `end` to a datetime stops the timer
- Cannot set `end` back to `null` (no restart allowed via update)
- Jobs dispatched for BOTH old and new projects/tasks when changed

### 2.4 Overlap Detection Logic

**File:** `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/TimeEntryController.php` (lines 61-96)

```php
private function assertNoOverlap(
    Organization $organization, 
    Member $member, 
    Carbon $start, 
    ?Carbon $end, 
    ?TimeEntry $exclude = null
): void {
    if (! $organization->prevent_overlapping_time_entries) {
        return;  // Feature disabled
    }

    $query = TimeEntry::query()
        ->where('organization_id', $organization->getKey())
        ->where('user_id', $member->user_id)
        ->when($exclude !== null, function (Builder $q) use ($exclude): void {
            $q->where('id', '!=', $exclude->getKey());
        })
        ->where(function (Builder $q) use ($start, $end): void {
            // New entry starts during existing entry
            $q2->where('end', '>', $start)
              ->where('start', '<', $start);

            if ($end !== null) {
                // New entry ends during existing entry
                $q4->where('start', '<', $end)
                   ->where('end', '>', $end);
                   
                // New entry completely surrounds existing entry
                $q6->where('start', '>=', $start)
                   ->where('end', '<=', $end);
            }
        });

    if ($query->exists()) {
        throw new OverlappingTimeEntryApiException;
    }
}
```

**Kiosk Implications:**

- Kiosk clock-in/out must call this validation if enabled
- Organization setting controls the feature
- Kiosk sessions should probably IGNORE this check (kiosk is punch-only, overlaps expected for breaks)

---

## 3. TimeEntryService Analysis

**File:** `/home/keven/Documents/solidtime-analysis/app/Service/TimeEntryService.php`

```php
class TimeEntryService
{
    public function getStartSelectRawForRounding(?TimeEntryRoundingType $roundingType, ?int $roundingMinutes): string
    {
        if ($roundingType === null || $roundingMinutes === null) {
            return 'start';
        }
        if ($roundingMinutes < 1) {
            throw new LogicException('Rounding minutes must be greater than 0');
        }

        return 'date_bin(\'1 minutes\', start, TIMESTAMP \'1970-01-01\')';
    }

    public function getEndSelectRawForRounding(?TimeEntryRoundingType $roundingType, ?int $roundingMinutes): string
    {
        if ($roundingType === null || $roundingMinutes === null) {
            return 'coalesce("end", \''.Carbon::now()->toDateTimeString().'\')';
        }
        // ... complex rounding logic using PostgreSQL date_bin
    }
}
```

**Analysis:**

- **Minimal Service:** Only handles rounding logic for time entry display/reports
- **No create/update/stop methods:** All time entry manipulation is in the controller
- **Kiosk can ignore this:** Rounding is a premium/reporting feature

**Important Observation:**

There is NO centralized `TimeEntryService->create()` method. Time entry creation happens directly in controllers. The kiosk feature should follow the same pattern for consistency.

---

## 4. Member Model Structure

**File:** `/home/keven/Documents/solidtime-analysis/app/Models/Member.php`

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

**Database Schema (from migration):**

**File:** `/home/keven/Documents/solidtime-analysis/database/migrations/2020_05_21_200000_create_organization_user_table.php`

```sql
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

Note: This table was later renamed to `members` via migration `2024_05_13_171020_rename_table_organization_user_to_members.php`.

**Current Columns:**

1. `id` (UUID, primary key)
2. `organization_id` (UUID, FK to organizations)
3. `user_id` (UUID, FK to users)
4. `role` (string: 'owner', 'admin', 'manager', 'employee', 'placeholder')
5. `billable_rate` (int, nullable, in cents)
6. `created_at`, `updated_at`

**Kiosk Extensions Needed:**

Per PRD Amendment AMD-04, we need:

```sql
ALTER TABLE members ADD COLUMN kiosk_pin_check VARCHAR(64) NULL UNIQUE;
ALTER TABLE members ADD COLUMN kiosk_pin_hash VARCHAR(255) NULL;
ALTER TABLE members ADD COLUMN kiosk_badge_token VARCHAR(64) NULL;
ALTER TABLE members ADD COLUMN kiosk_badge_token_expires_at TIMESTAMP NULL;
```

**Security Design:**

- `kiosk_pin_check`: SHA-256 hash of `organization_id + ':' + pin_plaintext` for uniqueness enforcement
- `kiosk_pin_hash`: bcrypt hash for actual PIN verification
- Unique constraint on `kiosk_pin_check` prevents duplicate PINs within an org

---

## 5. Vue SPA Entry Point Analysis

**File:** `/home/keven/Documents/solidtime-analysis/resources/js/app.ts`

```typescript
import './bootstrap';
import '../css/app.css';
import { createApp, h } from 'vue';
import { createInertiaApp, usePage } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { createPinia } from 'pinia';
import type { User } from '@/types/models';
import { VueQueryPlugin } from '@tanstack/vue-query';
import { type DefineComponent } from 'vue';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const pinia = createPinia();

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => {
        // Module resolution logic for extensions
        if (name.includes('Invoicing::')) {
            // ...
        } else {
            return resolvePageComponent(
                `./Pages/${name}.vue`,
                import.meta.glob<DefineComponent>('./Pages/**/*.vue')
            );
        }
    },
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });

        // Extension hook
        if (window.vueAppSetupHook) {
            window.vueAppSetupHook(app);
        }

        // Global helpers
        window.getWeekStartSetting = function () {
            const page = usePage<{ auth: { user: User; }; }>();
            return page.props.auth.user.week_start ?? 'monday';
        };
        window.getTimezoneSetting = function () {
            const page = usePage<{ auth: { user: User; }; }>();
            return page.props.auth.user.timezone;
        };

        app.use(plugin).use(pinia).use(ZiggyVue).use(VueQueryPlugin).mount(el);
    },

    progress: {
        color: '#4B5563',
    },
});
```

**Architecture:**

- **Inertia.js:** Pages are server-rendered Vue components (Inertia pages)
- **Pinia:** State management
- **VueQuery:** Data fetching
- **Ziggy:** Laravel route helpers in Vue
- **Single entry point:** All pages are loaded via Inertia's page resolver

**Kiosk Requirements:**

The kiosk interface CANNOT use Inertia.js because:

1. Inertia requires an authenticated web session
2. Kiosk devices have a kiosk token, not a user session
3. Kiosk needs to be a standalone SPA, not part of the AppLayout flow

**Solution:**

Create a separate Vue app entry point: `resources/js/kiosk.ts`

```typescript
// kiosk.ts
import './bootstrap';
import '../css/kiosk.css';  // Separate CSS for kiosk UI
import { createApp } from 'vue';
import KioskApp from './Pages/Kiosk/KioskApp.vue';
import { createPinia } from 'pinia';
import { VueQueryPlugin } from '@tanstack/vue-query';

const pinia = createPinia();
const app = createApp(KioskApp);

app.use(pinia).use(VueQueryPlugin);

app.mount('#kiosk-app');
```

Served via a dedicated Blade template (not app.blade.php).

---

## 6. Vite Configuration Analysis

**File:** `/home/keven/Documents/solidtime-analysis/vite.config.js`

```javascript
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import checker from 'vite-plugin-checker';
import { collectModuleAssetsPaths, collectModulePlugins } from './vite-module-loader.js';

async function getConfig() {
    const paths = [
        'resources/js/app.ts',
        'resources/css/app.css',
        'resources/css/filament/admin/theme.css',
    ];
    const modulePaths = await collectModuleAssetsPaths('extensions');
    const additionalPlugins = await collectModulePlugins('extensions');

    return defineConfig({
        build: {
            sourcemap: true,
        },
        plugins: [
            laravel({
                input: [...paths, ...modulePaths],
                refresh: true,
            }),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
            ...(process.env.SKIP_CHECKER ? [] : [checker({
                typescript: true,
                vueTsc: true,
                lintCommand: 'eslint "./**/*.{ts,vue}"',
            })]),
            ...additionalPlugins,
        ],
        server: {
            host: true,
            hmr: {
                host: process.env.VITE_HOST_NAME,
                clientPort: 80,
            },
        },
    });
}

export default getConfig();
```

**Analysis:**

- **Multiple entry points supported:** The `input` array can have multiple files
- **Module system:** Dynamically loads extension modules
- **TypeScript checking:** Enabled via `vite-plugin-checker`

**Kiosk Integration:**

Add kiosk entry points to the paths array:

```javascript
const paths = [
    'resources/js/app.ts',           // Main Inertia app
    'resources/js/kiosk.ts',         // NEW: Kiosk standalone SPA
    'resources/css/app.css',
    'resources/css/kiosk.css',       // NEW: Kiosk-specific styles
    'resources/css/filament/admin/theme.css',
];
```

Vite will build both bundles:

- `public/build/assets/app-[hash].js` (main app)
- `public/build/assets/kiosk-[hash].js` (kiosk app)

**Blade Template for Kiosk:**

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kiosk - {{ config('app.name') }}</title>
    @vite(['resources/js/kiosk.ts', 'resources/css/kiosk.css'])
</head>
<body class="kiosk-mode">
    <div id="kiosk-app"></div>
</body>
</html>
```

---

## 7. Middleware Patterns

### 7.1 Middleware Registration

**File:** `/home/keven/Documents/solidtime-analysis/app/Http/Kernel.php`

```php
protected $middleware = [
    \App\Http\Middleware\ForceHttps::class,
    \App\Http\Middleware\TrustProxies::class,
    \Illuminate\Http\Middleware\HandleCors::class,
    \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
    \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
    \App\Http\Middleware\TrimStrings::class,
    \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
];

protected $middlewareGroups = [
    'web' => [
        \App\Http\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \App\Http\Middleware\VerifyCsrfToken::class,
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
        \App\Http\Middleware\HandleInertiaRequests::class,
        \App\Http\Middleware\ShareInertiaData::class,
        \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        \Laravel\Passport\Http\Middleware\CreateFreshApiToken::class,
    ],

    'api' => [
        \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ForceJsonResponse::class,
    ],

    'health-check' => [],
];

protected $middlewareAliases = [
    'auth' => \App\Http\Middleware\Authenticate::class,
    'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
    'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,
    'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
    'can' => \Illuminate\Auth\Middleware\Authorize::class,
    'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
    'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
    'precognitive' => \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
    'signed' => \App\Http\Middleware\ValidateSignature::class,
    'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
    'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
    'check-organization-blocked' => CheckOrganizationBlocked::class,
];
```

**Patterns:**

1. **Global Middleware:** HTTPS enforcement, proxy trust, CORS
2. **Middleware Groups:** `web` (session + Inertia), `api` (throttle + JSON)
3. **Middleware Aliases:** Named middleware for route application

### 7.2 Custom Middleware: CheckOrganizationBlocked

**File:** `/home/keven/Documents/solidtime-analysis/app/Http/Middleware/CheckOrganizationBlocked.php`

```php
class CheckOrganizationBlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        $organization = $request->route('organization');

        if (! ($organization instanceof Organization)) {
            throw new \LogicException('The organization must be loaded before this middleware.');
        }

        /** @var BillingContract $billing */
        $billing = app(BillingContract::class);

        if ($billing->isBlocked($organization)) {
            throw new OrganizationHasNoSubscriptionButMultipleMembersException;
        }

        return $next($request);
    }
}
```

**Usage Pattern:**

Applied to write endpoints in routes:

```php
Route::post('/time-entries', [TimeEntryController::class, 'store'])
    ->name('store')
    ->middleware('check-organization-blocked');
```

**Kiosk Implications:**

Per AMD-06, the kiosk delete route MUST include this middleware:

```php
Route::delete('/kiosks/{kiosk}', [KioskController::class, 'destroy'])
    ->middleware('check-organization-blocked');
```

### 7.3 Kiosk Middleware Implementation

**New Middleware:** `app/Http/Middleware/AuthenticateKiosk.php`

```php
namespace App\Http\Middleware;

use App\Models\Kiosk;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateKiosk
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $tokenHash = hash('sha256', $token);

        $kiosk = Kiosk::query()
            ->where('token_hash', $tokenHash)
            ->where('is_active', true)
            ->first();

        if ($kiosk === null) {
            return response()->json(['error' => 'Invalid kiosk token'], 401);
        }

        // Update last activity
        $kiosk->last_activity_at = now();
        $kiosk->save();

        // Store kiosk in request for controllers
        $request->attributes->set('kiosk', $kiosk);

        return $next($request);
    }
}
```

Register as middleware alias:

```php
'auth.kiosk' => \App\Http\Middleware\AuthenticateKiosk::class,
```

---

## 8. Hash/Encryption Patterns

### 8.1 Hash Usage in Codebase

**File:** `/home/keven/Documents/solidtime-analysis/app/Service/UserService.php` (line 42)

```php
$user->password = Hash::make($password);
```

**File:** `/home/keven/Documents/solidtime-analysis/app/Providers/FortifyServiceProvider.php` (line 51)

```php
if ($user !== null && Hash::check($request->password, $user->password)) {
    return $user;
}
```

**Pattern:**

- `Hash::make()` for hashing (bcrypt)
- `Hash::check()` for verification

### 8.2 PIN Hashing Strategy

Per PRD Amendment AMD-04, the kiosk feature needs TWO hash columns:

**1. `kiosk_pin_check` (SHA-256 for uniqueness):**

```php
$pinCheck = hash('sha256', $member->organization_id . ':' . $plainTextPin);
$member->kiosk_pin_check = $pinCheck;
```

- Unique constraint enforces no duplicate PINs in org
- Salted with organization_id to prevent cross-org collision detection

**2. `kiosk_pin_hash` (bcrypt for authentication):**

```php
$member->kiosk_pin_hash = Hash::make($plainTextPin);
```

- Used for actual PIN verification
- Cannot be used for uniqueness (bcrypt is non-deterministic)

**Verification Flow:**

```php
// 1. Find member by organization and PIN check hash
$pinCheck = hash('sha256', $organization->id . ':' . $enteredPin);
$member = Member::query()
    ->where('organization_id', $organization->id)
    ->where('kiosk_pin_check', $pinCheck)
    ->first();

if ($member === null) {
    return 'Invalid PIN';
}

// 2. Verify using bcrypt
if (!Hash::check($enteredPin, $member->kiosk_pin_hash)) {
    return 'Invalid PIN';
}

return $member;  // Authenticated
```

### 8.3 Token Hashing (Kiosk Token)

**Pattern from Passport:**

Passport stores tokens as hashed values. The kiosk token should follow the same pattern:

**On kiosk creation:**

```php
$plainToken = Str::random(64);  // 64-character random string
$tokenHash = hash('sha256', $plainToken);

$kiosk->token_hash = $tokenHash;
$kiosk->save();

// Return $plainToken ONCE (never stored in DB)
return response()->json([
    'token' => $plainToken,
    'kiosk_url' => route('kiosk.show', ['token' => $plainToken]),
]);
```

**On authentication:**

```php
$tokenHash = hash('sha256', $request->bearerToken());
$kiosk = Kiosk::query()->where('token_hash', $tokenHash)->first();
```

---

## 9. Rate Limiting Infrastructure

### 9.1 RateLimiter Configuration

**File:** `/home/keven/Documents/solidtime-analysis/app/Providers/FortifyServiceProvider.php` (lines 58-66)

```php
RateLimiter::for('login', function (Request $request) {
    $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

    return Limit::perMinute(5)->by($throttleKey);
});

RateLimiter::for('two-factor', function (Request $request) {
    return Limit::perMinute(5)->by($request->session()->get('login.id'));
});
```

**File:** `/home/keven/Documents/solidtime-analysis/app/Providers/RouteServiceProvider.php` (lines 30-38)

```php
RateLimiter::for('api', function (Request $request) {
    if (! $this->app->isProduction()) {
        return Limit::none();
    }

    return $request->user()
        ? Limit::perMinute(200)->by($request->user()->id)
        : Limit::perMinute(60)->by($request->ip());
});
```

**Patterns:**

- Named rate limiters: `login`, `two-factor`, `api`
- Key-based throttling: By user ID, IP, or custom key
- Different limits for authenticated vs. guest users

### 9.2 Kiosk Rate Limiting Requirements

Per PRD REQ-002, PIN authentication needs:

- **5 failed attempts per member per kiosk in 15 minutes**
- **10-minute lockout on breach**

**Implementation Approach:**

**Database-backed (preferred for kiosk):**

Use the `kiosk_pin_attempts` table:

```php
// On PIN attempt
$attempt = new KioskPinAttempt;
$attempt->kiosk_id = $kiosk->id;
$attempt->member_id = $member->id;
$attempt->successful = $pinValid;
$attempt->attempted_at = now();
$attempt->save();

// Check lockout
$failedAttempts = KioskPinAttempt::query()
    ->where('kiosk_id', $kiosk->id)
    ->where('member_id', $member->id)
    ->where('successful', false)
    ->where('attempted_at', '>=', now()->subMinutes(15))
    ->count();

if ($failedAttempts >= 5) {
    $lastAttempt = KioskPinAttempt::query()
        ->where('kiosk_id', $kiosk->id)
        ->where('member_id', $member->id)
        ->where('successful', false)
        ->orderBy('attempted_at', 'desc')
        ->first();

    $lockedUntil = $lastAttempt->attempted_at->addMinutes(10);

    if (now()->lt($lockedUntil)) {
        return response()->json([
            'error' => 'locked_out',
            'message' => 'Too many failed attempts',
            'locked_until' => $lockedUntil->toIso8601String(),
        ], 429);
    }
}
```

**Alternative (Laravel RateLimiter):**

```php
RateLimiter::for('kiosk-pin', function (Request $request) {
    $kiosk = $request->attributes->get('kiosk');
    $memberId = $request->input('member_id');

    return Limit::perMinutes(15, 5)
        ->by($kiosk->id . '|' . $memberId)
        ->response(function () {
            return response()->json([
                'error' => 'locked_out',
                'message' => 'Too many failed attempts',
            ], 429);
        });
});
```

**Recommendation:** Use database-backed approach for better observability and admin dashboard integration.

---

## 10. Risk Assessment

### 10.1 Separate SPA Complexity

**Risk Level:** MEDIUM

**Concern:**

Creating a separate Vue SPA outside of Inertia.js increases architecture complexity.

**Mitigation:**

1. Keep kiosk SPA minimal (no shared components with main app)
2. Use the same tech stack (Vue 3, Pinia, VueQuery) for consistency
3. Document the dual-SPA architecture clearly
4. Consider future consolidation (post-MVP) if kiosk pages need to integrate with main app

**Trade-off:**

- **PRO:** Complete isolation from auth:web session requirements
- **CON:** Duplicate some logic (API client, theme management)

### 10.2 Auth Guard Conflicts

**Risk Level:** LOW

**Concern:**

Adding a third auth guard (`auth:kiosk`) could conflict with existing `auth:web` and `auth:api` guards.

**Mitigation:**

1. Kiosk routes are completely separate (`/api/v1/kiosk/*`)
2. Kiosk guard does NOT use the `users` provider (uses `kiosks` model directly)
3. No middleware overlap (kiosk endpoints use `auth.kiosk`, not `auth:web` or `auth:api`)

**Validation:**

Test that a user with an active `auth:web` session can visit the kiosk URL and it authenticates via kiosk token (not session).

### 10.3 Timer State Management on Kiosk

**Risk Level:** HIGH

**Concern:**

Kiosk sessions introduce a new timer state machine separate from normal time entries. Risk of state desynchronization.

**Scenario:**

1. Employee clocks in via kiosk (creates `KioskSession` and `TimeEntry` with `end = null`)
2. Employee opens web app and manually stops the time entry
3. Kiosk session is now out of sync (still shows "clocked in")

**Mitigation:**

**Option A (Recommended):** Kiosk sessions are the SINGLE SOURCE OF TRUTH

- Disable manual time entry editing for kiosk-created entries (add `created_by_kiosk` flag)
- Web app shows kiosk entries as read-only with a "Managed by Kiosk" badge
- Only the kiosk (or admin override) can stop a kiosk session

**Option B:** Bidirectional Sync

- When a time entry is manually stopped, check for active `KioskSession` and auto-close it
- Add observer on `TimeEntry` model:

```php
// app/Observers/TimeEntryObserver.php
public function updated(TimeEntry $timeEntry): void
{
    // If end was just set (timer stopped)
    if ($timeEntry->isDirty('end') && $timeEntry->end !== null) {
        // Close any active kiosk session for this member
        KioskSession::query()
            ->where('member_id', $timeEntry->member_id)
            ->where('status', '!=', 'clocked_out')
            ->update([
                'status' => 'clocked_out',
                'clock_out_at' => $timeEntry->end,
            ]);
    }
}
```

**Recommendation:** Use Option A for MVP. It's simpler and prevents state conflicts.

### 10.4 PIN Security

**Risk Level:** MEDIUM

**Concern:**

4-digit numeric PINs (0000-9999) have only 10,000 combinations. Brute force is feasible.

**Mitigation:**

1. **Rate limiting:** 5 attempts per 15 minutes (already in design)
2. **Lockout:** 10-minute penalty after 5 failed attempts
3. **Audit trail:** All attempts logged in `kiosk_pin_attempts` table
4. **Admin alerts:** Option to notify admin of repeated lockouts (future enhancement)
5. **QR code alternative:** Encourage QR codes for higher security

**Additional Recommendation:**

Add optional PIN complexity requirement in organization settings:

```php
// Organization setting (future)
$organization->kiosk_pin_min_length = 6;  // Require 6 digits instead of 4
```

### 10.5 QR Code Token Security

**Risk Level:** MEDIUM

**Concern:**

QR codes with short TTL (5 minutes) are good for dynamic use, but badge tokens (long-lived) are vulnerable if the QR is photographed.

**Mitigation:**

Per AMD-05, the feature supports TWO modes:

1. **Dynamic QR (5-minute TTL):** For phone-based scanning
2. **Badge QR (configurable TTL):** For printed badges, with revocation capability

**Badge token security:**

- Admin can revoke badge tokens at any time
- Badge tokens are separate from dynamic QR tokens
- Badge QR should display a "Badge ID" watermark (not just QR) to discourage sharing

### 10.6 Kiosk URL Token in Browser History

**Risk Level:** MEDIUM

**Concern:**

The kiosk URL contains the token as a path parameter: `/kiosk/{token}`. This gets logged in browser history and could be leaked.

**Mitigation (per AMD-08):**

1. **HTTPS required:** Enforce via middleware
2. **Token expiry:** Configurable (default 30 days)
3. **Single-device:** Token should be opened once and browser kept open
4. **Cache-Control headers:**

```php
return response()->view('kiosk.app')
    ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
    ->header('X-Robots-Tag', 'noindex, nofollow');
```

5. **Token regeneration:** Admin can regenerate at any time
6. **Future enhancement:** Setup token that establishes a cookie-based session

**Best Practice:**

The kiosk setup flow should show:

> **Important:** Open this URL on the kiosk device and bookmark it. Do not share this URL. If the device is compromised, regenerate the token from the admin panel.

---

## 11. Essential Files

These files are critical to understanding the kiosk feature implementation:

### Authentication & Guards

1. **`/home/keven/Documents/solidtime-analysis/config/auth.php`**
   - Guard configuration
   - Will need to add `kiosk` guard

2. **`/home/keven/Documents/solidtime-analysis/app/Providers/AuthServiceProvider.php`**
   - Passport configuration
   - Register kiosk guard driver here

3. **`/home/keven/Documents/solidtime-analysis/app/Http/Middleware/Authenticate.php`**
   - Base auth middleware pattern

### Time Entry Management

4. **`/home/keven/Documents/solidtime-analysis/app/Models/TimeEntry.php`**
   - Time entry model structure
   - Running timer = `end IS NULL`

5. **`/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/TimeEntryController.php`**
   - Time entry creation/update patterns
   - Overlap validation logic
   - Job dispatch patterns

6. **`/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/UserTimeEntryController.php`**
   - Active time entry detection

7. **`/home/keven/Documents/solidtime-analysis/database/migrations/2024_01_20_110837_create_time_entries_table.php`**
   - Time entry schema

### Member Model

8. **`/home/keven/Documents/solidtime-analysis/app/Models/Member.php`**
   - Member model structure
   - Will need PIN fields

9. **`/home/keven/Documents/solidtime-analysis/database/migrations/2020_05_21_200000_create_organization_user_table.php`**
   - Members table schema (originally organization_user)

### Organization & Permissions

10. **`/home/keven/Documents/solidtime-analysis/app/Models/Organization.php`**
    - Organization model
    - Settings like `prevent_overlapping_time_entries`

11. **`/home/keven/Documents/solidtime-analysis/app/Service/PermissionStore.php`**
    - Permission checking pattern

12. **`/home/keven/Documents/solidtime-analysis/app/Providers/JetstreamServiceProvider.php`**
    - Permission definitions
    - Role configurations

### Middleware & Routing

13. **`/home/keven/Documents/solidtime-analysis/app/Http/Kernel.php`**
    - Middleware registration
    - Middleware groups

14. **`/home/keven/Documents/solidtime-analysis/app/Http/Middleware/CheckOrganizationBlocked.php`**
    - Organization blocking pattern

15. **`/home/keven/Documents/solidtime-analysis/routes/api.php`**
    - API route patterns
    - Middleware application

16. **`/home/keven/Documents/solidtime-analysis/routes/web.php`**
    - Web route patterns

### Frontend Architecture

17. **`/home/keven/Documents/solidtime-analysis/resources/js/app.ts`**
    - Main Vue SPA entry point (Inertia)

18. **`/home/keven/Documents/solidtime-analysis/vite.config.js`**
    - Vite configuration
    - Entry point definitions

19. **`/home/keven/Documents/solidtime-analysis/resources/js/Layouts/AppLayout.vue`**
    - Main app layout structure

20. **`/home/keven/Documents/solidtime-analysis/resources/views/app.blade.php`**
    - Main app Blade template

### Rate Limiting & Security

21. **`/home/keven/Documents/solidtime-analysis/app/Providers/FortifyServiceProvider.php`**
    - Login rate limiting pattern
    - Hash::check usage

22. **`/home/keven/Documents/solidtime-analysis/app/Providers/RouteServiceProvider.php`**
    - API rate limiting

23. **`/home/keven/Documents/solidtime-analysis/app/Service/UserService.php`**
    - Hash::make usage pattern

### Controllers

24. **`/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/Controller.php`**
    - Base controller pattern
    - Permission checking helpers
    - `user()` and `member()` helpers

---

## Summary

The Solidtime codebase provides a solid foundation for implementing the kiosk feature:

**Strengths:**

- Clean separation of web and API authentication
- Well-structured time entry system with clear running timer pattern
- Flexible permission system via Jetstream roles
- Modular architecture (services, controllers, middleware)
- Support for multiple Vite entry points

**Challenges:**

1. **No existing custom auth guard:** Need to implement from scratch
2. **Timer state synchronization:** Risk of kiosk sessions getting out of sync with manual edits
3. **Dual SPA architecture:** Kiosk needs separate Vue app outside Inertia

**Key Recommendations:**

1. Implement kiosk guard as a simple token-based RequestGuard
2. Use database-backed rate limiting for PIN attempts
3. Make kiosk-created time entries read-only in main app (or auto-sync on edit)
4. Create separate Vite entry point for kiosk SPA
5. Follow existing patterns: Hash::make/check, job dispatch, permission checking

**Implementation Complexity:** MEDIUM-HIGH

The feature requires significant new infrastructure (custom guard, new models, separate SPA), but the existing patterns provide clear guidance.

---

**End of Analysis**