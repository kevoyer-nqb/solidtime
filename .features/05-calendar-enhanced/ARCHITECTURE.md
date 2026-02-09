# Feature 05: Calendar Enhanced — Technical Architecture

**Version**: 1.0  
**Date**: 2026-02-06  
**Author**: Architecture Team  
**Status**: Ready for Implementation

---

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [Data Model](#2-data-model)
3. [API Contract](#3-api-contract)
4. [Service Layer Architecture](#4-service-layer-architecture)
5. [OAuth Flow Implementation](#5-oauth-flow-implementation)
6. [Frontend Architecture](#6-frontend-architecture)
7. [Background Sync Engine](#7-background-sync-engine)
8. [Premium Feature Gating](#8-premium-feature-gating)
9. [Migration Strategy](#9-migration-strategy)
10. [File Manifest](#10-file-manifest)
11. [Implementation Phases](#11-implementation-phases)
12. [Testing Strategy](#12-testing-strategy)

---

## 1. Architecture Overview

### 1.1 System Context

The Calendar Enhanced feature integrates external calendar providers (Google Calendar, Microsoft 365) with Solidtime's existing FullCalendar-based time tracking interface. The architecture extends the existing `TimeEntryCalendar.vue` component with:

1. **OAuth-based calendar connections** (user-scoped, organization-aware)
2. **External event caching** with background sync
3. **Month view** using existing FullCalendar `dayGridPlugin`
4. **Event-to-entry conversion** workflow
5. **Premium feature gating** via `BillingContract`

### 1.2 Core Architectural Principles

Following existing Solidtime patterns:

- **User-scoped connections, organization-aware routes**: Calendar connections belong to users but are accessed via `/organizations/{organization}` routes for permission checking
- **Stateless services**: `CalendarIntegrationService` is injected per-request
- **Route model binding**: Organization model injected automatically
- **Encrypted credentials**: OAuth tokens use `Crypt::encryptString()` at rest
- **Background sync**: Laravel Jobs for async event fetching
- **Premium gating**: `$this->canAccessPremiumFeatures($organization)` in controllers
- **Audit logging**: `CustomAuditable` trait for `CalendarConnection` model

### 1.3 Technology Stack

- **Backend**: Laravel 11, PostgreSQL, Laravel Passport (existing)
- **Frontend**: Vue 3 + TypeScript + Pinia + Inertia.js
- **Calendar**: FullCalendar v6.1.18 (existing)
- **HTTP Client**: Guzzle (existing)
- **OAuth Libraries**: 
  - Google: `google/apiclient` (new dependency)
  - Microsoft: Direct Graph API with Guzzle

---

## 2. Data Model

### 2.1 CalendarConnection Model

**File**: `/home/keven/Documents/solidtime-analysis/app/Models/CalendarConnection.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\CustomAuditable;
use App\Models\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property string $id
 * @property string $user_id
 * @property string $organization_id
 * @property string $provider
 * @property string $provider_account_id
 * @property string $provider_account_email
 * @property string $access_token (encrypted)
 * @property string $refresh_token (encrypted)
 * @property Carbon $token_expires_at
 * @property array $selected_calendars
 * @property bool $is_active
 * @property Carbon|null $last_synced_at
 * @property int $sync_failure_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Organization $organization
 * @property-read Collection<int, CalendarEvent> $events
 */
class CalendarConnection extends Model implements AuditableContract
{
    use CustomAuditable;
    use HasFactory;
    use HasUuids;

    protected $casts = [
        'token_expires_at' => 'datetime',
        'selected_calendars' => 'array',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
        'sync_failure_count' => 'integer',
    ];

    protected $fillable = [
        'user_id',
        'organization_id',
        'provider',
        'provider_account_id',
        'provider_account_email',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'selected_calendars',
        'is_active',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    public function setAccessTokenAttribute(string $value): void
    {
        $this->attributes['access_token'] = Crypt::encryptString($value);
    }

    public function getAccessTokenAttribute(): string
    {
        return Crypt::decryptString($this->attributes['access_token']);
    }

    public function setRefreshTokenAttribute(string $value): void
    {
        $this->attributes['refresh_token'] = Crypt::encryptString($value);
    }

    public function getRefreshTokenAttribute(): string
    {
        return Crypt::decryptString($this->attributes['refresh_token']);
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at->isPast();
    }

    public function needsRefresh(): bool
    {
        // Refresh if token expires within 5 minutes
        return $this->token_expires_at->subMinutes(5)->isPast();
    }

    public function incrementSyncFailure(): void
    {
        $this->increment('sync_failure_count');
        
        // Disable connection after 3 consecutive failures
        if ($this->sync_failure_count >= 3) {
            $this->update(['is_active' => false]);
        }
    }

    public function resetSyncFailure(): void
    {
        $this->update(['sync_failure_count' => 0]);
    }
}
```

### 2.2 CalendarEvent Model

**File**: `/home/keven/Documents/solidtime-analysis/app/Models/CalendarEvent.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $calendar_connection_id
 * @property string $user_id
 * @property string $external_event_id
 * @property string $calendar_id
 * @property string $title
 * @property string|null $description
 * @property Carbon $start
 * @property Carbon|null $end
 * @property bool $is_all_day
 * @property string|null $location
 * @property string $status
 * @property string|null $color
 * @property string|null $converted_time_entry_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CalendarConnection $connection
 * @property-read User $user
 * @property-read TimeEntry|null $convertedTimeEntry
 */
class CalendarEvent extends Model
{
    // NOTE: No CustomAuditable trait - intentional per AMD-10
    // External events are cached data, not primary business entities
    
    use HasFactory;
    use HasUuids;

    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
        'is_all_day' => 'boolean',
    ];

    protected $fillable = [
        'calendar_connection_id',
        'user_id',
        'external_event_id',
        'calendar_id',
        'title',
        'description',
        'start',
        'end',
        'is_all_day',
        'location',
        'status',
        'color',
        'converted_time_entry_id',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(CalendarConnection::class, 'calendar_connection_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function convertedTimeEntry(): BelongsTo
    {
        return $this->belongsTo(TimeEntry::class, 'converted_time_entry_id');
    }

    public function isConverted(): bool
    {
        return $this->converted_time_entry_id !== null;
    }

    public function scopeInDateRange($query, Carbon $start, Carbon $end)
    {
        return $query->where(function ($q) use ($start, $end) {
            $q->whereBetween('start', [$start, $end])
              ->orWhereBetween('end', [$start, $end])
              ->orWhere(function ($q2) use ($start, $end) {
                  // Events that span the entire range
                  $q2->where('start', '<=', $start)
                     ->where('end', '>=', $end);
              });
        });
    }

    public function scopeNotOlderThan($query, Carbon $date)
    {
        return $query->where('end', '>=', $date);
    }
}
```

### 2.3 Database Schema

#### Migration 1: `2026_03_05_000001_create_calendar_connections_table.php`

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
        Schema::create('calendar_connections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            
            $table->uuid('organization_id');
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            
            $table->string('provider', 20); // 'google' | 'microsoft'
            $table->string('provider_account_id', 255);
            $table->string('provider_account_email', 255);
            $table->text('access_token'); // Encrypted
            $table->text('refresh_token'); // Encrypted
            $table->timestamp('token_expires_at');
            $table->jsonb('selected_calendars')->default('[]');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->integer('sync_failure_count')->unsigned()->default(0);
            $table->timestamps();

            // User can have one connection per provider per organization
            $table->unique(['user_id', 'organization_id', 'provider', 'provider_account_id'], 'unique_user_org_provider_account');
            
            $table->index(['user_id', 'organization_id']);
            $table->index('provider');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_connections');
    }
};
```

#### Migration 2: `2026_03_05_000002_create_calendar_events_table.php`

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
        Schema::create('calendar_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            
            $table->uuid('calendar_connection_id');
            $table->foreign('calendar_connection_id')
                ->references('id')
                ->on('calendar_connections')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            
            $table->uuid('user_id');
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            
            $table->string('external_event_id', 500);
            $table->string('calendar_id', 500);
            $table->string('title', 1000)->default('');
            $table->text('description')->nullable();
            $table->timestamp('start');
            $table->timestamp('end')->nullable();
            $table->boolean('is_all_day')->default(false);
            $table->string('location', 1000)->nullable();
            $table->string('status', 20)->default('confirmed');
            $table->string('color', 20)->nullable();
            
            $table->uuid('converted_time_entry_id')->nullable();
            $table->foreign('converted_time_entry_id')
                ->references('id')
                ->on('time_entries')
                ->cascadeOnUpdate()
                ->setNullOnDelete();
            
            $table->timestamps();

            $table->unique(['calendar_connection_id', 'external_event_id'], 'unique_connection_event');
            $table->index('user_id');
            $table->index(['user_id', 'start', 'end']);
            $table->index('converted_time_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
```

### 2.4 Data Model Relationships

```
User (1) ─────────┬─── (N) CalendarConnection
                  │
Organization (1) ──┘

CalendarConnection (1) ───── (N) CalendarEvent

CalendarEvent (N) ───── (0..1) TimeEntry (converted_time_entry_id)
```

**Key Design Decisions**:

1. **User-scoped + Org-aware**: `calendar_connections` has both `user_id` and `organization_id`. A user can have different connections in different organizations (per AMD-05).
2. **Denormalized user_id in calendar_events**: Improves query performance for date range lookups.
3. **Encrypted tokens**: Access/refresh tokens encrypted via mutators using `Crypt` facade.
4. **Soft failure tracking**: `sync_failure_count` auto-disables connections after 3 failures.
5. **No audit on CalendarEvent**: Per AMD-10, these are cached external data, not primary entities.

---

## 3. API Contract

### 3.1 Route Definitions

**File**: `/home/keven/Documents/solidtime-analysis/routes/api.php` (additions)

```php
// Calendar Integration routes (under organizations scope)
Route::name('calendar-integrations.')->prefix('/organizations/{organization}')->group(static function (): void {
    Route::get('/calendar-integrations', [CalendarIntegrationController::class, 'index'])->name('index');
    Route::post('/calendar-integrations/connect', [CalendarIntegrationController::class, 'connect'])->name('connect')->middleware('check-organization-blocked');
    Route::get('/calendar-integrations/{calendarConnection}/calendars', [CalendarIntegrationController::class, 'listCalendars'])->name('list-calendars');
    Route::put('/calendar-integrations/{calendarConnection}', [CalendarIntegrationController::class, 'update'])->name('update')->middleware('check-organization-blocked');
    Route::delete('/calendar-integrations/{calendarConnection}', [CalendarIntegrationController::class, 'destroy'])->name('destroy');
    Route::post('/calendar-integrations/{calendarConnection}/sync', [CalendarIntegrationController::class, 'sync'])->name('sync')->middleware('check-organization-blocked');
});

// OAuth callback - STATIC URL (no {organization} in path per AMD-04)
Route::name('calendar-integrations.')->group(static function (): void {
    Route::get('/calendar-integrations/callback', [CalendarIntegrationController::class, 'callback'])->name('callback');
});

// Calendar Events routes
Route::name('calendar-events.')->prefix('/organizations/{organization}')->group(static function (): void {
    Route::get('/calendar-events', [CalendarEventController::class, 'index'])->name('index');
    Route::post('/calendar-events/{calendarEvent}/convert', [CalendarEventController::class, 'convert'])->name('convert')->middleware('check-organization-blocked');
});
```

### 3.2 Controller Methods

#### CalendarIntegrationController

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/CalendarIntegrationController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\V1\CalendarIntegration\CalendarIntegrationConnectRequest;
use App\Http\Requests\V1\CalendarIntegration\CalendarIntegrationUpdateRequest;
use App\Http\Resources\V1\CalendarIntegration\CalendarConnectionCollection;
use App\Http\Resources\V1\CalendarIntegration\CalendarConnectionResource;
use App\Http\Resources\V1\CalendarIntegration\ExternalCalendarCollection;
use App\Models\CalendarConnection;
use App\Models\Organization;
use App\Service\CalendarIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarIntegrationController extends Controller
{
    /**
     * List user's calendar connections for this organization
     * 
     * GET /api/v1/organizations/{organization}/calendar-integrations
     */
    public function index(Organization $organization): JsonResponse
    {
        $this->checkPermission($organization, 'calendar-integrations:manage');

        // Premium feature check
        if (!$this->canAccessPremiumFeatures($organization)) {
            throw new \App\Exceptions\Api\FeatureIsNotAvailableInFreePlanApiException();
        }

        $connections = CalendarConnection::query()
            ->where('user_id', $this->user()->id)
            ->where('organization_id', $organization->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return new CalendarConnectionCollection($connections);
    }

    /**
     * Initiate OAuth flow
     * 
     * POST /api/v1/organizations/{organization}/calendar-integrations/connect
     */
    public function connect(
        CalendarIntegrationConnectRequest $request,
        Organization $organization,
        CalendarIntegrationService $service
    ): JsonResponse {
        $this->checkPermission($organization, 'calendar-integrations:manage');

        if (!$this->canAccessPremiumFeatures($organization)) {
            throw new \App\Exceptions\Api\FeatureIsNotAvailableInFreePlanApiException();
        }

        $provider = $request->validated()['provider'];
        
        $redirectUrl = $service->initiateOAuthFlow(
            provider: $provider,
            userId: $this->user()->id,
            organizationId: $organization->id
        );

        return response()->json([
            'redirect_url' => $redirectUrl,
        ]);
    }

    /**
     * OAuth callback handler (STATIC URL - no {organization} in path)
     * 
     * GET /api/v1/calendar-integrations/callback?code=...&state=...
     */
    public function callback(
        Request $request,
        CalendarIntegrationService $service
    ) {
        $code = $request->query('code');
        $state = $request->query('state');
        $error = $request->query('error');

        if ($error) {
            return redirect()->route('calendar')->with('message', [
                'type' => 'error',
                'text' => 'Calendar connection was cancelled or failed.',
            ]);
        }

        try {
            $connection = $service->handleOAuthCallback($code, $state);
            
            return redirect()->route('calendar')->with('message', [
                'type' => 'success',
                'text' => 'Calendar connected successfully!',
            ]);
        } catch (\Exception $e) {
            \Log::error('OAuth callback failed', ['error' => $e->getMessage()]);
            
            return redirect()->route('calendar')->with('message', [
                'type' => 'error',
                'text' => 'Failed to connect calendar. Please try again.',
            ]);
        }
    }

    /**
     * List available calendars for a connection
     * 
     * GET /api/v1/organizations/{organization}/calendar-integrations/{calendarConnection}/calendars
     */
    public function listCalendars(
        Organization $organization,
        CalendarConnection $calendarConnection,
        CalendarIntegrationService $service
    ): JsonResponse {
        $this->checkPermission($organization, 'calendar-integrations:manage');
        
        // Ensure connection belongs to current user in this org
        if ($calendarConnection->user_id !== $this->user()->id || 
            $calendarConnection->organization_id !== $organization->id) {
            throw new \Illuminate\Auth\Access\AuthorizationException();
        }

        $calendars = $service->fetchAvailableCalendars($calendarConnection);

        return new ExternalCalendarCollection($calendars);
    }

    /**
     * Update connection (calendar selection, active status)
     * 
     * PUT /api/v1/organizations/{organization}/calendar-integrations/{calendarConnection}
     */
    public function update(
        CalendarIntegrationUpdateRequest $request,
        Organization $organization,
        CalendarConnection $calendarConnection
    ): JsonResponse {
        $this->checkPermission($organization, 'calendar-integrations:manage');
        
        if ($calendarConnection->user_id !== $this->user()->id || 
            $calendarConnection->organization_id !== $organization->id) {
            throw new \Illuminate\Auth\Access\AuthorizationException();
        }

        $calendarConnection->update($request->validated());

        return new CalendarConnectionResource($calendarConnection);
    }

    /**
     * Delete a connection (cascades to calendar_events)
     * 
     * DELETE /api/v1/organizations/{organization}/calendar-integrations/{calendarConnection}
     */
    public function destroy(
        Organization $organization,
        CalendarConnection $calendarConnection
    ): JsonResponse {
        $this->checkPermission($organization, 'calendar-integrations:manage');
        
        if ($calendarConnection->user_id !== $this->user()->id || 
            $calendarConnection->organization_id !== $organization->id) {
            throw new \Illuminate\Auth\Access\AuthorizationException();
        }

        $calendarConnection->delete();

        return response()->json(null, 204);
    }

    /**
     * Trigger manual sync
     * 
     * POST /api/v1/organizations/{organization}/calendar-integrations/{calendarConnection}/sync
     */
    public function sync(
        Organization $organization,
        CalendarConnection $calendarConnection,
        CalendarIntegrationService $service
    ): JsonResponse {
        $this->checkPermission($organization, 'calendar-integrations:manage');
        
        if ($calendarConnection->user_id !== $this->user()->id || 
            $calendarConnection->organization_id !== $organization->id) {
            throw new \Illuminate\Auth\Access\AuthorizationException();
        }

        // Rate limit: max 1 sync per 5 minutes
        if ($calendarConnection->last_synced_at && 
            $calendarConnection->last_synced_at->gt(now()->subMinutes(5))) {
            return response()->json([
                'error' => 'Sync rate limit exceeded. Please wait before syncing again.',
                'next_sync_available_at' => $calendarConnection->last_synced_at->addMinutes(5)->toIso8601ZuluString(),
            ], 429);
        }

        $eventsSynced = $service->syncConnection($calendarConnection);

        return response()->json([
            'events_synced' => $eventsSynced,
            'last_synced_at' => $calendarConnection->fresh()->last_synced_at->toIso8601ZuluString(),
        ]);
    }
}
```

#### CalendarEventController

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/CalendarEventController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\V1\CalendarEvent\CalendarEventConvertRequest;
use App\Http\Requests\V1\CalendarEvent\CalendarEventIndexRequest;
use App\Http\Resources\V1\CalendarEvent\CalendarEventCollection;
use App\Http\Resources\V1\TimeEntry\TimeEntryResource;
use App\Models\CalendarEvent;
use App\Models\Organization;
use App\Service\CalendarIntegrationService;
use Illuminate\Http\JsonResponse;

class CalendarEventController extends Controller
{
    /**
     * Get external calendar events for date range
     * 
     * GET /api/v1/organizations/{organization}/calendar-events?start=...&end=...
     */
    public function index(
        CalendarEventIndexRequest $request,
        Organization $organization
    ): JsonResponse {
        $this->checkPermission($organization, 'calendar-integrations:manage');

        $start = \Carbon\Carbon::parse($request->validated()['start']);
        $end = \Carbon\Carbon::parse($request->validated()['end']);

        $events = CalendarEvent::query()
            ->where('user_id', $this->user()->id)
            ->whereHas('connection', function ($query) use ($organization) {
                $query->where('organization_id', $organization->id)
                      ->where('is_active', true);
            })
            ->inDateRange($start, $end)
            ->with('connection')
            ->orderBy('start')
            ->limit(200) // Per AMD-05: limit overlay events
            ->get();

        return new CalendarEventCollection($events);
    }

    /**
     * Convert external event to time entry
     * 
     * POST /api/v1/organizations/{organization}/calendar-events/{calendarEvent}/convert
     */
    public function convert(
        CalendarEventConvertRequest $request,
        Organization $organization,
        CalendarEvent $calendarEvent,
        CalendarIntegrationService $service
    ): JsonResponse {
        $this->checkPermission($organization, 'time-entries:create:own');

        if ($calendarEvent->user_id !== $this->user()->id) {
            throw new \Illuminate\Auth\Access\AuthorizationException();
        }

        if ($calendarEvent->isConverted()) {
            return response()->json([
                'error' => 'This event has already been converted to a time entry.',
            ], 409);
        }

        $timeEntry = $service->convertEventToTimeEntry(
            event: $calendarEvent,
            organization: $organization,
            member: $this->member($organization),
            data: $request->validated()
        );

        return new TimeEntryResource($timeEntry);
    }
}
```

### 3.3 Request Validation Classes

#### CalendarIntegrationConnectRequest

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/CalendarIntegration/CalendarIntegrationConnectRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\CalendarIntegration;

use App\Http\Requests\V1\BaseFormRequest;
use Illuminate\Validation\Rule;

class CalendarIntegrationConnectRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::in(['google', 'microsoft'])],
        ];
    }
}
```

#### CalendarIntegrationUpdateRequest

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/CalendarIntegration/CalendarIntegrationUpdateRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\CalendarIntegration;

use App\Http\Requests\V1\BaseFormRequest;

class CalendarIntegrationUpdateRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'selected_calendars' => ['required', 'array'],
            'selected_calendars.*' => ['required', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
```

#### CalendarEventIndexRequest

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/CalendarEvent/CalendarEventIndexRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\CalendarEvent;

use App\Http\Requests\V1\BaseFormRequest;

class CalendarEventIndexRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
        ];
    }
}
```

#### CalendarEventConvertRequest

**File**: `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/CalendarEvent/CalendarEventConvertRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\CalendarEvent;

use App\Http\Requests\V1\BaseFormRequest;
use App\Models\Organization;
use Korridor\LaravelModelValidationRules\Rules\ExistsEloquent;

class CalendarEventConvertRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $organization = $this->route('organization');

        return [
            'project_id' => [
                'nullable',
                'string',
                new ExistsEloquent(\App\Models\Project::class, null, function ($query) use ($organization) {
                    $query->where('organization_id', $organization->id);
                }),
            ],
            'task_id' => [
                'nullable',
                'string',
                new ExistsEloquent(\App\Models\Task::class, null, function ($query) use ($organization) {
                    $query->where('organization_id', $organization->id);
                }),
            ],
            'billable' => ['required', 'boolean'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
```

---

## 4. Service Layer Architecture

### 4.1 CalendarIntegrationService

**File**: `/home/keven/Documents/solidtime-analysis/app/Service/CalendarIntegrationService.php`

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Models\CalendarConnection;
use App\Models\CalendarEvent;
use App\Models\Member;
use App\Models\Organization;
use App\Models\TimeEntry;
use App\Service\CalendarProvider\CalendarProviderInterface;
use App\Service\CalendarProvider\GoogleCalendarProvider;
use App\Service\CalendarProvider\MicrosoftCalendarProvider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CalendarIntegrationService
{
    public function __construct(
        private GoogleCalendarProvider $googleProvider,
        private MicrosoftCalendarProvider $microsoftProvider
    ) {}

    /**
     * Get provider instance based on connection
     */
    private function getProvider(string $provider): CalendarProviderInterface
    {
        return match ($provider) {
            'google' => $this->googleProvider,
            'microsoft' => $this->microsoftProvider,
            default => throw new \InvalidArgumentException("Unknown provider: {$provider}"),
        };
    }

    /**
     * Initiate OAuth flow and return redirect URL
     */
    public function initiateOAuthFlow(
        string $provider,
        string $userId,
        string $organizationId
    ): string {
        $providerInstance = $this->getProvider($provider);

        // Generate state parameter with organization context (per AMD-04)
        $state = base64_encode(json_encode([
            'provider' => $provider,
            'user_id' => $userId,
            'organization_id' => $organizationId,
            'csrf_token' => Str::random(40),
            'timestamp' => now()->timestamp,
        ]));

        return $providerInstance->getAuthorizationUrl($state);
    }

    /**
     * Handle OAuth callback and create connection
     */
    public function handleOAuthCallback(string $code, string $state): CalendarConnection
    {
        // Decode and validate state parameter
        $stateData = json_decode(base64_decode($state), true);
        
        if (!$stateData || !isset($stateData['provider'], $stateData['user_id'], $stateData['organization_id'])) {
            throw new \Exception('Invalid state parameter');
        }

        // Validate timestamp (state valid for 10 minutes)
        if ($stateData['timestamp'] < now()->subMinutes(10)->timestamp) {
            throw new \Exception('OAuth state expired');
        }

        $provider = $this->getProvider($stateData['provider']);
        
        // Exchange code for tokens
        $tokenData = $provider->exchangeCodeForTokens($code);

        // Fetch account info
        $accountInfo = $provider->fetchAccountInfo($tokenData['access_token']);

        // Create or update connection
        return DB::transaction(function () use ($stateData, $tokenData, $accountInfo) {
            return CalendarConnection::updateOrCreate(
                [
                    'user_id' => $stateData['user_id'],
                    'organization_id' => $stateData['organization_id'],
                    'provider' => $stateData['provider'],
                    'provider_account_id' => $accountInfo['id'],
                ],
                [
                    'provider_account_email' => $accountInfo['email'],
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'],
                    'token_expires_at' => now()->addSeconds($tokenData['expires_in']),
                    'is_active' => true,
                    'sync_failure_count' => 0,
                ]
            );
        });
    }

    /**
     * Fetch available calendars from provider
     */
    public function fetchAvailableCalendars(CalendarConnection $connection): array
    {
        $this->ensureValidToken($connection);
        
        $provider = $this->getProvider($connection->provider);
        $calendars = $provider->fetchCalendars($connection->access_token);

        // Mark selected calendars
        return array_map(function ($calendar) use ($connection) {
            $calendar['is_selected'] = in_array($calendar['id'], $connection->selected_calendars);
            return $calendar;
        }, $calendars);
    }

    /**
     * Sync events for a connection
     */
    public function syncConnection(CalendarConnection $connection): int
    {
        if (!$connection->is_active) {
            return 0;
        }

        try {
            $this->ensureValidToken($connection);
            
            $provider = $this->getProvider($connection->provider);
            
            // Fetch events from now - 90 days to now + 365 days (per AMD-09)
            $start = now()->subDays(90);
            $end = now()->addDays(365);
            
            $externalEvents = [];
            foreach ($connection->selected_calendars as $calendarId) {
                $events = $provider->fetchEvents(
                    accessToken: $connection->access_token,
                    calendarId: $calendarId,
                    start: $start,
                    end: $end
                );
                $externalEvents = array_merge($externalEvents, $events);
            }

            // Upsert events
            $eventsSynced = $this->upsertEvents($connection, $externalEvents);

            // Cleanup old events (older than 90 days)
            $this->cleanupOldEvents($connection, $start);

            // Update sync metadata
            $connection->update([
                'last_synced_at' => now(),
            ]);
            $connection->resetSyncFailure();

            return $eventsSynced;

        } catch (\Exception $e) {
            Log::error('Calendar sync failed', [
                'connection_id' => $connection->id,
                'provider' => $connection->provider,
                'error' => $e->getMessage(),
            ]);

            $connection->incrementSyncFailure();
            throw $e;
        }
    }

    /**
     * Upsert events from external provider
     */
    private function upsertEvents(CalendarConnection $connection, array $externalEvents): int
    {
        $count = 0;

        foreach ($externalEvents as $eventData) {
            CalendarEvent::updateOrCreate(
                [
                    'calendar_connection_id' => $connection->id,
                    'external_event_id' => $eventData['id'],
                ],
                [
                    'user_id' => $connection->user_id,
                    'calendar_id' => $eventData['calendar_id'],
                    'title' => $eventData['title'] ?? '',
                    'description' => $eventData['description'] ?? null,
                    'start' => Carbon::parse($eventData['start']),
                    'end' => isset($eventData['end']) ? Carbon::parse($eventData['end']) : null,
                    'is_all_day' => $eventData['is_all_day'] ?? false,
                    'location' => $eventData['location'] ?? null,
                    'status' => $eventData['status'] ?? 'confirmed',
                    'color' => $eventData['color'] ?? null,
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Clean up events older than cutoff date (per AMD-09)
     */
    private function cleanupOldEvents(CalendarConnection $connection, Carbon $cutoffDate): void
    {
        CalendarEvent::query()
            ->where('calendar_connection_id', $connection->id)
            ->where('end', '<', $cutoffDate)
            ->delete();
    }

    /**
     * Ensure access token is valid, refresh if needed
     */
    private function ensureValidToken(CalendarConnection $connection): void
    {
        if (!$connection->needsRefresh()) {
            return;
        }

        try {
            $provider = $this->getProvider($connection->provider);
            $tokenData = $provider->refreshAccessToken($connection->refresh_token);

            $connection->update([
                'access_token' => $tokenData['access_token'],
                'token_expires_at' => now()->addSeconds($tokenData['expires_in']),
            ]);
        } catch (\Exception $e) {
            Log::error('Token refresh failed', [
                'connection_id' => $connection->id,
                'provider' => $connection->provider,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Convert calendar event to time entry
     */
    public function convertEventToTimeEntry(
        CalendarEvent $event,
        Organization $organization,
        Member $member,
        array $data
    ): TimeEntry {
        if ($event->is_all_day) {
            throw new \Exception('Cannot convert all-day events. Please specify start and end times.');
        }

        $timeEntry = DB::transaction(function () use ($event, $organization, $member, $data) {
            $timeEntry = TimeEntry::create([
                'user_id' => $member->user_id,
                'member_id' => $member->id,
                'organization_id' => $organization->id,
                'start' => $event->start,
                'end' => $event->end,
                'description' => $data['description'] ?? $event->title,
                'project_id' => $data['project_id'] ?? null,
                'task_id' => $data['task_id'] ?? null,
                'billable' => $data['billable'],
                'tags' => $data['tags'] ?? [],
            ]);

            // Mark event as converted
            $event->update(['converted_time_entry_id' => $timeEntry->id]);

            return $timeEntry;
        });

        return $timeEntry;
    }
}
```

### 4.2 Provider Interface & Implementations

#### CalendarProviderInterface

**File**: `/home/keven/Documents/solidtime-analysis/app/Service/CalendarProvider/CalendarProviderInterface.php`

```php
<?php

declare(strict_types=1);

namespace App\Service\CalendarProvider;

use Illuminate\Support\Carbon;

interface CalendarProviderInterface
{
    /**
     * Get OAuth authorization URL
     */
    public function getAuthorizationUrl(string $state): string;

    /**
     * Exchange authorization code for tokens
     * 
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     */
    public function exchangeCodeForTokens(string $code): array;

    /**
     * Refresh access token using refresh token
     * 
     * @return array{access_token: string, expires_in: int}
     */
    public function refreshAccessToken(string $refreshToken): array;

    /**
     * Fetch account information
     * 
     * @return array{id: string, email: string}
     */
    public function fetchAccountInfo(string $accessToken): array;

    /**
     * Fetch available calendars
     * 
     * @return array[]
     */
    public function fetchCalendars(string $accessToken): array;

    /**
     * Fetch events from a calendar
     * 
     * @return array[]
     */
    public function fetchEvents(
        string $accessToken,
        string $calendarId,
        Carbon $start,
        Carbon $end
    ): array;
}
```

#### GoogleCalendarProvider

**File**: `/home/keven/Documents/solidtime-analysis/app/Service/CalendarProvider/GoogleCalendarProvider.php`

```php
<?php

declare(strict_types=1);

namespace App\Service\CalendarProvider;

use GuzzleHttp\Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class GoogleCalendarProvider implements CalendarProviderInterface
{
    private Client $client;
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function __construct()
    {
        $this->client = new Client(['base_uri' => 'https://www.googleapis.com']);
        $this->clientId = config('services.google.client_id');
        $this->clientSecret = config('services.google.client_secret');
        $this->redirectUri = config('services.google.redirect_uri');
    }

    public function getAuthorizationUrl(string $state): string
    {
        $params = http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar.readonly https://www.googleapis.com/auth/userinfo.email',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return "https://accounts.google.com/o/oauth2/v2/auth?{$params}";
    }

    public function exchangeCodeForTokens(string $code): array
    {
        $response = $this->client->post('/oauth2/v4/token', [
            'form_params' => [
                'code' => $code,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => $this->redirectUri,
                'grant_type' => 'authorization_code',
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? '',
            'expires_in' => $data['expires_in'],
        ];
    }

    public function refreshAccessToken(string $refreshToken): array
    {
        $response = $this->client->post('/oauth2/v4/token', [
            'form_params' => [
                'refresh_token' => $refreshToken,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'refresh_token',
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return [
            'access_token' => $data['access_token'],
            'expires_in' => $data['expires_in'],
        ];
    }

    public function fetchAccountInfo(string $accessToken): array
    {
        $response = $this->client->get('/oauth2/v2/userinfo', [
            'headers' => ['Authorization' => "Bearer {$accessToken}"],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return [
            'id' => $data['id'],
            'email' => $data['email'],
        ];
    }

    public function fetchCalendars(string $accessToken): array
    {
        $response = $this->client->get('/calendar/v3/users/me/calendarList', [
            'headers' => ['Authorization' => "Bearer {$accessToken}"],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return array_map(function ($item) {
            return [
                'id' => $item['id'],
                'name' => $item['summary'],
                'color' => $item['backgroundColor'] ?? null,
                'is_primary' => $item['primary'] ?? false,
            ];
        }, $data['items'] ?? []);
    }

    public function fetchEvents(
        string $accessToken,
        string $calendarId,
        Carbon $start,
        Carbon $end
    ): array {
        $response = $this->client->get("/calendar/v3/calendars/{$calendarId}/events", [
            'headers' => ['Authorization' => "Bearer {$accessToken}"],
            'query' => [
                'timeMin' => $start->toIso8601String(),
                'timeMax' => $end->toIso8601String(),
                'singleEvents' => 'true',
                'orderBy' => 'startTime',
                'maxResults' => 500,
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return array_map(function ($item) use ($calendarId) {
            $isAllDay = isset($item['start']['date']);
            
            return [
                'id' => $item['id'],
                'calendar_id' => $calendarId,
                'title' => $item['summary'] ?? '(No title)',
                'description' => $item['description'] ?? null,
                'start' => $isAllDay ? $item['start']['date'] : $item['start']['dateTime'],
                'end' => $isAllDay ? ($item['end']['date'] ?? null) : ($item['end']['dateTime'] ?? null),
                'is_all_day' => $isAllDay,
                'location' => $item['location'] ?? null,
                'status' => $item['status'] ?? 'confirmed',
                'color' => $item['colorId'] ?? null,
            ];
        }, $data['items'] ?? []);
    }
}
```

#### MicrosoftCalendarProvider

**File**: `/home/keven/Documents/solidtime-analysis/app/Service/CalendarProvider/MicrosoftCalendarProvider.php`

```php
<?php

declare(strict_types=1);

namespace App\Service\CalendarProvider;

use GuzzleHttp\Client;
use Illuminate\Support\Carbon;

class MicrosoftCalendarProvider implements CalendarProviderInterface
{
    private Client $client;
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private string $tenant;

    public function __construct()
    {
        $this->client = new Client(['base_uri' => 'https://graph.microsoft.com/v1.0']);
        $this->clientId = config('services.microsoft.client_id');
        $this->clientSecret = config('services.microsoft.client_secret');
        $this->redirectUri = config('services.microsoft.redirect_uri');
        $this->tenant = config('services.microsoft.tenant', 'common');
    }

    public function getAuthorizationUrl(string $state): string
    {
        $params = http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'Calendars.Read User.Read offline_access',
            'state' => $state,
        ]);

        return "https://login.microsoftonline.com/{$this->tenant}/oauth2/v2.0/authorize?{$params}";
    }

    public function exchangeCodeForTokens(string $code): array
    {
        $tokenClient = new Client();
        $response = $tokenClient->post("https://login.microsoftonline.com/{$this->tenant}/oauth2/v2.0/token", [
            'form_params' => [
                'code' => $code,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => $this->redirectUri,
                'grant_type' => 'authorization_code',
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? '',
            'expires_in' => $data['expires_in'],
        ];
    }

    public function refreshAccessToken(string $refreshToken): array
    {
        $tokenClient = new Client();
        $response = $tokenClient->post("https://login.microsoftonline.com/{$this->tenant}/oauth2/v2.0/token", [
            'form_params' => [
                'refresh_token' => $refreshToken,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'refresh_token',
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return [
            'access_token' => $data['access_token'],
            'expires_in' => $data['expires_in'],
        ];
    }

    public function fetchAccountInfo(string $accessToken): array
    {
        $response = $this->client->get('/me', [
            'headers' => ['Authorization' => "Bearer {$accessToken}"],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return [
            'id' => $data['id'],
            'email' => $data['mail'] ?? $data['userPrincipalName'],
        ];
    }

    public function fetchCalendars(string $accessToken): array
    {
        $response = $this->client->get('/me/calendars', [
            'headers' => ['Authorization' => "Bearer {$accessToken}"],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return array_map(function ($item) {
            return [
                'id' => $item['id'],
                'name' => $item['name'],
                'color' => $item['color'] ?? null,
                'is_primary' => $item['isDefaultCalendar'] ?? false,
            ];
        }, $data['value'] ?? []);
    }

    public function fetchEvents(
        string $accessToken,
        string $calendarId,
        Carbon $start,
        Carbon $end
    ): array {
        $response = $this->client->get("/me/calendars/{$calendarId}/events", [
            'headers' => ['Authorization' => "Bearer {$accessToken}"],
            'query' => [
                '$filter' => "start/dateTime ge '{$start->toIso8601String()}' and end/dateTime le '{$end->toIso8601String()}'",
                '$orderby' => 'start/dateTime',
                '$top' => 500,
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return array_map(function ($item) use ($calendarId) {
            $isAllDay = $item['isAllDay'] ?? false;
            
            return [
                'id' => $item['id'],
                'calendar_id' => $calendarId,
                'title' => $item['subject'] ?? '(No title)',
                'description' => isset($item['body']['content']) ? strip_tags($item['body']['content']) : null,
                'start' => $item['start']['dateTime'] ?? $item['start']['date'],
                'end' => $item['end']['dateTime'] ?? $item['end']['date'] ?? null,
                'is_all_day' => $isAllDay,
                'location' => isset($item['location']['displayName']) ? $item['location']['displayName'] : null,
                'status' => strtolower($item['responseStatus']['response'] ?? 'none'),
                'color' => null,
            ];
        }, $data['value'] ?? []);
    }
}
```

---

## 5. OAuth Flow Implementation

### 5.1 OAuth Flow Sequence (per AMD-04)

```
┌──────┐                  ┌─────────────┐              ┌──────────────┐              ┌──────────────┐
│ User │                  │  Solidtime  │              │  OAuth Provider│              │  Callback    │
└──┬───┘                  └──────┬──────┘              │  (Google/MS)   │              │  Handler     │
   │                             │                      └───────┬────────┘              └──────┬───────┘
   │ 1. Click "Connect Google"   │                              │                             │
   │─────────────────────────────>                              │                             │
   │                             │                              │                             │
   │                             │ 2. Generate state with       │                             │
   │                             │    org_id + user_id          │                             │
   │                             │────────────┐                 │                             │
   │                             │            │                 │                             │
   │                             │<───────────┘                 │                             │
   │                             │                              │                             │
   │                             │ 3. Redirect to provider      │                             │
   │                             │   with state parameter       │                             │
   │<─────────────────────────────────────────────────────────>│                             │
   │                             │                              │                             │
   │ 4. User grants consent      │                              │                             │
   │─────────────────────────────────────────────────────────> │                             │
   │                             │                              │                             │
   │                             │                              │ 5. Redirect with code+state │
   │<────────────────────────────────────────────────────────────────────────────────────────│
   │                             │                              │                             │
   │                             │ 6. Decode state, get org_id  │                             │
   │                             │<───────────────────────────────────────────────────────────│
   │                             │                              │                             │
   │                             │ 7. Exchange code for tokens  │                             │
   │                             │─────────────────────────────>│                             │
   │                             │                              │                             │
   │                             │ 8. Return access+refresh     │                             │
   │                             │<─────────────────────────────│                             │
   │                             │                              │                             │
   │                             │ 9. Create CalendarConnection │                             │
   │                             │    with user_id + org_id     │                             │
   │                             │────────────┐                 │                             │
   │                             │            │                 │                             │
   │                             │<───────────┘                 │                             │
   │                             │                              │                             │
   │ 10. Redirect to Calendar    │                              │                             │
   │     page with success msg   │                              │                             │
   │<─────────────────────────────                              │                             │
```

### 5.2 Static Callback URL Configuration

**Environment Variables** (`.env`):

```bash
# Google Calendar OAuth
GOOGLE_CALENDAR_CLIENT_ID=your_google_client_id
GOOGLE_CALENDAR_CLIENT_SECRET=your_google_client_secret
GOOGLE_CALENDAR_REDIRECT_URI=${APP_URL}/api/v1/calendar-integrations/callback

# Microsoft Calendar OAuth
MICROSOFT_CALENDAR_CLIENT_ID=your_microsoft_client_id
MICROSOFT_CALENDAR_CLIENT_SECRET=your_microsoft_client_secret
MICROSOFT_CALENDAR_REDIRECT_URI=${APP_URL}/api/v1/calendar-integrations/callback
MICROSOFT_CALENDAR_TENANT=common
```

**Config File** (`config/services.php` additions):

```php
'google' => [
    'client_id' => env('GOOGLE_CALENDAR_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET'),
    'redirect_uri' => env('GOOGLE_CALENDAR_REDIRECT_URI'),
],

'microsoft' => [
    'client_id' => env('MICROSOFT_CALENDAR_CLIENT_ID'),
    'client_secret' => env('MICROSOFT_CALENDAR_CLIENT_SECRET'),
    'redirect_uri' => env('MICROSOFT_CALENDAR_REDIRECT_URI'),
    'tenant' => env('MICROSOFT_CALENDAR_TENANT', 'common'),
],
```

### 5.3 State Parameter Structure

```php
// Encoded state parameter
$state = base64_encode(json_encode([
    'provider' => 'google',                      // or 'microsoft'
    'user_id' => '123e4567-e89b-12d3-a456-426614174000',
    'organization_id' => '987fcdeb-51a2-43f7-b901-432158976543',
    'csrf_token' => Str::random(40),
    'timestamp' => 1675209600,                   // Unix timestamp
]));
```

**Security Validations**:
- Timestamp checked (max 10 minutes old)
- User authenticated during connect initiation
- Organization ID validated after callback
- CSRF token uniqueness (optional additional validation)

---

## 6. Frontend Architecture

### 6.1 Component Hierarchy

```
Calendar.vue (Inertia page)
└── TimeEntryCalendar.vue (enhanced)
    ├── FullCalendar (month view added)
    │   ├── FullCalendarDayHeader.vue (enhanced with planned vs actual)
    │   ├── FullCalendarEventContent.vue (time entries)
    │   └── ExternalCalendarEventContent.vue (NEW - external events)
    ├── CalendarSettingsModal.vue (NEW)
    │   ├── CalendarConnectionCard.vue (NEW)
    │   └── CalendarSelectionList.vue (NEW)
    ├── EventToEntryModal.vue (NEW)
    └── MonthViewControls.vue (NEW)
```

### 6.2 Pinia Store: useCalendarIntegrations

**File**: `/home/keven/Documents/solidtime-analysis/resources/js/utils/useCalendarIntegrations.ts`

```typescript
import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { api, type CalendarConnection, type CalendarEvent } from '@/packages/api/src';
import { getCurrentOrganizationId } from '@/utils/useUser';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';

export const useCalendarIntegrationsStore = defineStore('calendar-integrations', () => {
    const queryClient = useQueryClient();

    // Fetch connections
    const {
        data: connections,
        isLoading: connectionsLoading,
        error: connectionsError,
    } = useQuery({
        queryKey: ['calendar-connections', getCurrentOrganizationId()],
        queryFn: () =>
            api.getCalendarConnections({
                params: {
                    organization: getCurrentOrganizationId() || '',
                },
            }),
        enabled: computed(() => !!getCurrentOrganizationId()),
    });

    // Initiate OAuth connect
    const connectMutation = useMutation({
        mutationFn: (provider: 'google' | 'microsoft') =>
            api.connectCalendar({
                params: {
                    organization: getCurrentOrganizationId() || '',
                },
                body: { provider },
            }),
        onSuccess: (data) => {
            // Redirect to OAuth provider
            window.location.href = data.redirect_url;
        },
    });

    // Fetch external events
    const fetchExternalEvents = (start: string, end: string) => {
        return api.getCalendarEvents({
            params: {
                organization: getCurrentOrganizationId() || '',
            },
            queries: {
                start,
                end,
            },
        });
    };

    // Convert event to time entry
    const convertEventMutation = useMutation({
        mutationFn: ({ eventId, data }: { eventId: string; data: any }) =>
            api.convertCalendarEvent({
                params: {
                    organization: getCurrentOrganizationId() || '',
                    calendarEvent: eventId,
                },
                body: data,
            }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['timeEntry'] });
            queryClient.invalidateQueries({ queryKey: ['calendar-events'] });
        },
    });

    // Update connection
    const updateConnectionMutation = useMutation({
        mutationFn: ({ connectionId, data }: { connectionId: string; data: any }) =>
            api.updateCalendarConnection({
                params: {
                    organization: getCurrentOrganizationId() || '',
                    calendarConnection: connectionId,
                },
                body: data,
            }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['calendar-connections'] });
        },
    });

    // Delete connection
    const deleteConnectionMutation = useMutation({
        mutationFn: (connectionId: string) =>
            api.deleteCalendarConnection({
                params: {
                    organization: getCurrentOrganizationId() || '',
                    calendarConnection: connectionId,
                },
            }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['calendar-connections'] });
            queryClient.invalidateQueries({ queryKey: ['calendar-events'] });
        },
    });

    // Manual sync
    const syncConnectionMutation = useMutation({
        mutationFn: (connectionId: string) =>
            api.syncCalendarConnection({
                params: {
                    organization: getCurrentOrganizationId() || '',
                    calendarConnection: connectionId,
                },
            }),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['calendar-events'] });
        },
    });

    return {
        connections,
        connectionsLoading,
        connectionsError,
        connectProvider: connectMutation.mutate,
        fetchExternalEvents,
        convertEvent: convertEventMutation.mutate,
        updateConnection: updateConnectionMutation.mutate,
        deleteConnection: deleteConnectionMutation.mutate,
        syncConnection: syncConnectionMutation.mutate,
    };
});
```

### 6.3 Enhanced TimeEntryCalendar.vue

**Key Changes**:

```typescript
// Add month view to calendarOptions
const calendarOptions = computed(() => ({
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin, activityStatusPlugin],
    initialView: localStorage.getItem('calendar-view') || 'timeGridWeek',
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay', // Month view added
    },
    // ... existing options
    
    // Handle view changes
    viewDidMount: (info) => {
        localStorage.setItem('calendar-view', info.view.type);
    },
}));

// Merge time entries + external events
const allEvents = computed(() => {
    const timeEntryEvents = events.value; // Existing time entries
    
    if (!showExternalEvents.value || !externalEvents.value) {
        return timeEntryEvents;
    }

    const externalCalendarEvents = externalEvents.value.map((event) => ({
        id: `external-${event.id}`,
        start: event.start,
        end: event.end || event.start,
        title: event.title,
        display: 'background', // Render as background overlay
        backgroundColor: chroma(event.color || '#888').alpha(0.2).css(),
        borderColor: 'transparent',
        classNames: ['external-event', event.is_converted ? 'converted' : ''],
        extendedProps: {
            isExternal: true,
            externalEvent: event,
        },
    }));

    return [...timeEntryEvents, ...externalCalendarEvents];
});
```

### 6.4 New Component: CalendarSettingsModal.vue

**File**: `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Calendar/CalendarSettingsModal.vue`

```vue
<script setup lang="ts">
import { ref } from 'vue';
import { useCalendarIntegrationsStore } from '@/utils/useCalendarIntegrations';
import { Button, Dialog, DialogContent, DialogHeader, DialogTitle } from '@/packages/ui/src';

const props = defineProps<{
    show: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:show', value: boolean): void;
}>();

const store = useCalendarIntegrationsStore();

const handleConnect = (provider: 'google' | 'microsoft') => {
    store.connectProvider(provider);
};

const handleDisconnect = (connectionId: string) => {
    if (confirm('Are you sure you want to disconnect this calendar?')) {
        store.deleteConnection(connectionId);
    }
};

const handleSync = (connectionId: string) => {
    store.syncConnection(connectionId);
};
</script>

<template>
    <Dialog :open="show" @update:open="emit('update:show', $event)">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle>Calendar Integrations</DialogTitle>
            </DialogHeader>

            <div class="space-y-4">
                <!-- Existing Connections -->
                <div v-if="store.connections?.data?.length">
                    <h3 class="text-sm font-semibold mb-2">Connected Calendars</h3>
                    <div class="space-y-2">
                        <div
                            v-for="connection in store.connections.data"
                            :key="connection.id"
                            class="border rounded-lg p-4 flex items-center justify-between">
                            <div>
                                <div class="font-medium">
                                    {{ connection.provider_account_email }}
                                </div>
                                <div class="text-sm text-muted-foreground">
                                    {{ connection.provider === 'google' ? 'Google Calendar' : 'Microsoft 365' }}
                                </div>
                                <div v-if="connection.last_synced_at" class="text-xs text-muted-foreground">
                                    Last synced: {{ new Date(connection.last_synced_at).toLocaleString() }}
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="handleSync(connection.id)">
                                    Sync Now
                                </Button>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    @click="handleDisconnect(connection.id)">
                                    Disconnect
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Connect New -->
                <div>
                    <h3 class="text-sm font-semibold mb-2">Connect a Calendar</h3>
                    <div class="flex gap-2">
                        <Button @click="handleConnect('google')">
                            Connect Google Calendar
                        </Button>
                        <Button @click="handleConnect('microsoft')">
                            Connect Microsoft 365
                        </Button>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
```

### 6.5 New Component: EventToEntryModal.vue

**File**: `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Calendar/EventToEntryModal.vue`

```vue
<script setup lang="ts">
import { ref, computed } from 'vue';
import { useCalendarIntegrationsStore } from '@/utils/useCalendarIntegrations';
import type { CalendarEvent } from '@/packages/api/src';
import {
    Button,
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    Label,
    Select,
    Checkbox,
} from '@/packages/ui/src';

const props = defineProps<{
    show: boolean;
    event: CalendarEvent | null;
    projects: any[];
    tasks: any[];
}>();

const emit = defineEmits<{
    (e: 'update:show', value: boolean): void;
}>();

const store = useCalendarIntegrationsStore();

const selectedProject = ref<string | null>(null);
const selectedTask = ref<string | null>(null);
const isBillable = ref(false);
const description = ref('');

const handleConvert = async () => {
    if (!props.event) return;

    await store.convertEvent({
        eventId: props.event.id,
        data: {
            project_id: selectedProject.value,
            task_id: selectedTask.value,
            billable: isBillable.value,
            description: description.value || props.event.title,
            tags: [],
        },
    });

    emit('update:show', false);
};
</script>

<template>
    <Dialog :open="show" @update:open="emit('update:show', $event)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Convert Event to Time Entry</DialogTitle>
            </DialogHeader>

            <div v-if="event" class="space-y-4">
                <div>
                    <Label>Event</Label>
                    <div class="text-sm">{{ event.title }}</div>
                    <div class="text-xs text-muted-foreground">
                        {{ new Date(event.start).toLocaleString() }} -
                        {{ new Date(event.end!).toLocaleString() }}
                    </div>
                </div>

                <div>
                    <Label for="project">Project</Label>
                    <Select v-model="selectedProject" :options="projects" />
                </div>

                <div>
                    <Label for="task">Task</Label>
                    <Select v-model="selectedTask" :options="tasks" />
                </div>

                <div class="flex items-center space-x-2">
                    <Checkbox v-model="isBillable" id="billable" />
                    <Label for="billable">Billable</Label>
                </div>

                <div class="flex justify-end gap-2">
                    <Button variant="outline" @click="emit('update:show', false)">
                        Cancel
                    </Button>
                    <Button @click="handleConvert">
                        Create Time Entry
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
```

---

## 7. Background Sync Engine

### 7.1 Sync Job

**File**: `/home/keven/Documents/solidtime-analysis/app/Jobs/SyncCalendarConnectionJob.php`

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CalendarConnection;
use App\Service\CalendarIntegrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncCalendarConnectionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        private string $connectionId
    ) {}

    public function handle(CalendarIntegrationService $service): void
    {
        $connection = CalendarConnection::find($this->connectionId);

        if (!$connection || !$connection->is_active) {
            return;
        }

        try {
            $service->syncConnection($connection);
        } catch (\Exception $e) {
            Log::error('Background calendar sync failed', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);
            
            throw $e; // Will retry via Laravel queue
        }
    }
}
```

### 7.2 Scheduled Command

**File**: `/home/keven/Documents/solidtime-analysis/app/Console/Commands/SyncCalendarConnectionsCommand.php`

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncCalendarConnectionJob;
use App\Models\CalendarConnection;
use Illuminate\Console\Command;

class SyncCalendarConnectionsCommand extends Command
{
    protected $signature = 'calendar:sync';
    protected $description = 'Sync all active calendar connections';

    public function handle(): int
    {
        $connections = CalendarConnection::query()
            ->where('is_active', true)
            ->where(function ($query) {
                // Sync if never synced or last sync > 15 minutes ago
                $query->whereNull('last_synced_at')
                      ->orWhere('last_synced_at', '<', now()->subMinutes(15));
            })
            ->get();

        $this->info("Dispatching sync jobs for {$connections->count()} connections...");

        foreach ($connections as $connection) {
            SyncCalendarConnectionJob::dispatch($connection->id);
        }

        $this->info('Sync jobs dispatched successfully.');

        return self::SUCCESS;
    }
}
```

### 7.3 Cleanup Command (per AMD-09)

**File**: `/home/keven/Documents/solidtime-analysis/app/Console/Commands/CleanupCalendarEventsCommand.php`

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CalendarEvent;
use Illuminate\Console\Command;

class CleanupCalendarEventsCommand extends Command
{
    protected $signature = 'calendar:cleanup';
    protected $description = 'Delete calendar events older than 90 days';

    public function handle(): int
    {
        $cutoffDate = now()->subDays(90);

        $deletedCount = CalendarEvent::query()
            ->where('end', '<', $cutoffDate)
            ->delete();

        $this->info("Deleted {$deletedCount} old calendar events.");

        return self::SUCCESS;
    }
}
```

### 7.4 Kernel Scheduling

**File**: `/home/keven/Documents/solidtime-analysis/app/Console/Kernel.php` (additions)

```php
protected function schedule(Schedule $schedule): void
{
    // ... existing schedules

    // Sync active calendar connections every 15 minutes
    $schedule->command('calendar:sync')->everyFifteenMinutes();

    // Clean up old calendar events weekly
    $schedule->command('calendar:cleanup')->weekly()->sundays()->at('02:00');
}
```

---

## 8. Premium Feature Gating

### 8.1 Backend Gating (per AMD-11)

All calendar integration endpoints check:

```php
// In CalendarIntegrationController methods
if (!$this->canAccessPremiumFeatures($organization)) {
    throw new \App\Exceptions\Api\FeatureIsNotAvailableInFreePlanApiException();
}
```

The `canAccessPremiumFeatures()` method exists in `App\Http\Controllers\Api\V1\Controller`:

```php
protected function canAccessPremiumFeatures(Organization $organization): bool
{
    return app(BillingContract::class)->hasSubscription($organization) 
        || app(BillingContract::class)->hasTrial($organization);
}
```

### 8.2 Frontend Gating

**Inertia Shared Prop** (add to `HandleInertiaRequests.php`):

```php
public function share(Request $request): array
{
    // ... existing shares
    
    return array_merge(parent::share($request), [
        'has_calendar_sync' => Module::has('CalendarSync') && Module::isEnabled('CalendarSync'),
        // OR if implemented as a premium flag:
        // 'has_calendar_sync' => $currentOrganization !== null 
        //     ? ($billing->hasSubscription($currentOrganization) || $billing->hasTrial($currentOrganization))
        //     : false,
    ]);
}
```

**Usage in Vue**:

```typescript
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const hasCalendarSync = computed(() => page.props.has_calendar_sync);

// Show/hide calendar settings button
<Button v-if="hasCalendarSync" @click="showSettings = true">
    Calendar Settings
</Button>
```

### 8.3 Permission Registration (per AMD-03)

**File**: `/home/keven/Documents/solidtime-analysis/app/Permissions/CalendarPermissions.php`

```php
<?php

declare(strict_types=1);

namespace App\Permissions;

use Laravel\Jetstream\Jetstream;

class CalendarPermissions
{
    public static function register(): void
    {
        // Calendar integration permissions
        $permissions = [
            'calendar-integrations:manage',
        ];

        // Add to all roles except placeholder
        Jetstream::role('admin', 'Administrator', array_merge(
            Jetstream::role('admin')->permissions ?? [],
            $permissions
        ))->description('Administrator users can perform any action.');

        Jetstream::role('manager', 'Manager', array_merge(
            Jetstream::role('manager')->permissions ?? [],
            $permissions
        ))->description('Manager users can view and manage resources.');

        Jetstream::role('employee', 'Employee', array_merge(
            Jetstream::role('employee')->permissions ?? [],
            $permissions
        ))->description('Employee users have the ability to track time.');
    }
}
```

**Register in `JetstreamServiceProvider.php`**:

```php
protected function configurePermissions(): void
{
    // ... existing permission setup

    // Feature permissions
    \App\Permissions\CalendarPermissions::register();
}
```

---

## 9. Migration Strategy

### 9.1 Migration Sequence

Per **SF-03**, all migrations use date prefix `2026_03_05_`.

1. `2026_03_05_000001_create_calendar_connections_table.php` (defined in section 2.3)
2. `2026_03_05_000002_create_calendar_events_table.php` (defined in section 2.3)

### 9.2 Rollback Strategy

```bash
# Rollback both migrations
php artisan migrate:rollback --step=2

# Re-run
php artisan migrate
```

Data cascades correctly:
- Deleting a `CalendarConnection` cascades to `calendar_events`
- Deleting a `User` cascades to both tables
- Deleting a `TimeEntry` sets `converted_time_entry_id` to NULL

---

## 10. File Manifest

### 10.1 Backend Files

#### Models
- `/app/Models/CalendarConnection.php` (new)
- `/app/Models/CalendarEvent.php` (new)

#### Migrations
- `/database/migrations/2026_03_05_000001_create_calendar_connections_table.php` (new)
- `/database/migrations/2026_03_05_000002_create_calendar_events_table.php` (new)

#### Controllers
- `/app/Http/Controllers/Api/V1/CalendarIntegrationController.php` (new)
- `/app/Http/Controllers/Api/V1/CalendarEventController.php` (new)

#### Services
- `/app/Service/CalendarIntegrationService.php` (new)
- `/app/Service/CalendarProvider/CalendarProviderInterface.php` (new)
- `/app/Service/CalendarProvider/GoogleCalendarProvider.php` (new)
- `/app/Service/CalendarProvider/MicrosoftCalendarProvider.php` (new)

#### Requests
- `/app/Http/Requests/V1/CalendarIntegration/CalendarIntegrationConnectRequest.php` (new)
- `/app/Http/Requests/V1/CalendarIntegration/CalendarIntegrationUpdateRequest.php` (new)
- `/app/Http/Requests/V1/CalendarEvent/CalendarEventIndexRequest.php` (new)
- `/app/Http/Requests/V1/CalendarEvent/CalendarEventConvertRequest.php` (new)

#### Resources
- `/app/Http/Resources/V1/CalendarIntegration/CalendarConnectionResource.php` (new)
- `/app/Http/Resources/V1/CalendarIntegration/CalendarConnectionCollection.php` (new)
- `/app/Http/Resources/V1/CalendarIntegration/ExternalCalendarCollection.php` (new)
- `/app/Http/Resources/V1/CalendarEvent/CalendarEventResource.php` (new)
- `/app/Http/Resources/V1/CalendarEvent/CalendarEventCollection.php` (new)

#### Jobs
- `/app/Jobs/SyncCalendarConnectionJob.php` (new)

#### Commands
- `/app/Console/Commands/SyncCalendarConnectionsCommand.php` (new)
- `/app/Console/Commands/CleanupCalendarEventsCommand.php` (new)

#### Permissions
- `/app/Permissions/CalendarPermissions.php` (new)

#### Config
- `/config/services.php` (modify - add Google/Microsoft OAuth config)

#### Routes
- `/routes/api.php` (modify - add calendar integration routes)

#### Kernel
- `/app/Console/Kernel.php` (modify - add scheduled tasks)

#### Inertia Middleware
- `/app/Http/Middleware/HandleInertiaRequests.php` (modify - add `has_calendar_sync` prop)

#### Service Provider
- `/app/Providers/JetstreamServiceProvider.php` (modify - register CalendarPermissions)

### 10.2 Frontend Files

#### Pinia Stores
- `/resources/js/utils/useCalendarIntegrations.ts` (new)

#### Components
- `/resources/js/packages/ui/src/Calendar/CalendarSettingsModal.vue` (new)
- `/resources/js/packages/ui/src/Calendar/CalendarConnectionCard.vue` (new)
- `/resources/js/packages/ui/src/Calendar/EventToEntryModal.vue` (new)
- `/resources/js/packages/ui/src/Calendar/ExternalCalendarEventContent.vue` (new)
- `/resources/js/packages/ui/src/Calendar/MonthViewControls.vue` (new)

#### Enhanced Components
- `/resources/js/Pages/Calendar.vue` (modify - integrate external events, settings modal)
- `/resources/js/packages/ui/src/FullCalendar/TimeEntryCalendar.vue` (modify - add month view, external event overlay)
- `/resources/js/packages/ui/src/FullCalendar/FullCalendarDayHeader.vue` (modify - planned vs actual totals)

#### Types
- `/resources/js/types/calendar.d.ts` (new - TypeScript types for calendar entities)

### 10.3 Tests

#### Unit Tests
- `/tests/Unit/Model/CalendarConnectionModelTest.php` (new)
- `/tests/Unit/Model/CalendarEventModelTest.php` (new)
- `/tests/Unit/Service/CalendarIntegrationServiceTest.php` (new)
- `/tests/Unit/Service/CalendarProvider/GoogleCalendarProviderTest.php` (new)
- `/tests/Unit/Service/CalendarProvider/MicrosoftCalendarProviderTest.php` (new)

#### API Endpoint Tests
- `/tests/Unit/Endpoint/Api/V1/CalendarIntegrationEndpointTest.php` (new)
- `/tests/Unit/Endpoint/Api/V1/CalendarEventEndpointTest.php` (new)

#### Feature Tests
- `/tests/Feature/CalendarOAuthFlowTest.php` (new)
- `/tests/Feature/CalendarSyncTest.php` (new)

#### E2E Tests
- `/e2e/calendar-integration.spec.ts` (new)

### 10.4 Documentation
- `/docs/calendar-integration-setup.md` (new - OAuth setup guide)

---

## 11. Implementation Phases

### Phase 1: Foundation & Month View (Sprint 1, 40h)

**Tasks**:
- CAL-001: Create CalendarConnection migration & model (4h)
- CAL-002: Create CalendarEvent migration & model (4h)
- CAL-003a: Core CalendarIntegrationService + Google provider (10h)
- CAL-004: Month view in TimeEntryCalendar.vue (6h)
- CAL-005: CalendarIntegrationController with OAuth flow (8h)
- CAL-006: CalendarEventController with index endpoint (4h)
- CAL-014: Premium feature gating (3h) — moved from Sprint 2 per AMD-08
- CAL-007: Request validation classes (3h)

**Deliverable**: Month view works, Google OAuth flow functional, connections persist

### Phase 2: Microsoft Provider & Sync Engine (Sprint 2, 38h)

**Tasks**:
- CAL-003b: Microsoft Outlook/Graph provider (6h)
- CAL-008: SyncCalendarConnectionJob (5h)
- CAL-009: Scheduled sync command (3h)
- CAL-021: Cleanup command for old events (3h) — new per AMD-09
- CAL-010: useCalendarIntegrations Pinia store (6h)
- CAL-011: CalendarSettingsModal.vue (8h)
- CAL-012: External event overlay in calendar (6h)

**Deliverable**: Background sync working, settings UI complete, external events visible

### Phase 3: Event Conversion & Polish (Sprint 3, 40h)

**Tasks**:
- CAL-013: EventToEntryModal.vue (6h)
- CAL-015: Event-to-entry conversion API (5h)
- CAL-016: Token refresh logic (4h)
- CAL-017: Enhanced FullCalendarDayHeader (3h)
- CAL-018: Unit tests (backend) (10h)
- CAL-019: API endpoint tests (8h)
- CAL-020: Component tests (frontend) (6h)

**Deliverable**: Full event-to-entry workflow, comprehensive test coverage

### Phase 4: E2E Testing & Docs (Sprint 4, 38h)

**Tasks**:
- CAL-022: E2E Playwright tests (10h)
- CAL-023: Documentation (OAuth setup guide) (4h)
- CAL-024: OpenAPI spec updates (4h)
- CAL-025: Bug fixes & refinements (10h)
- CAL-026: Accessibility audit (4h)
- CAL-027: Performance optimization (6h)

**Deliverable**: Production-ready feature with docs

**Total Effort**: 144 hours (per sum of individual task hours in SPRINT-PLAN.md detail tables)

---

## 12. Testing Strategy

### 12.1 Unit Tests

**CalendarConnection Model**:
```php
// Test encrypted token accessors
$connection->access_token = 'plain-token';
$this->assertNotEquals('plain-token', $connection->getRawOriginal('access_token'));
$this->assertEquals('plain-token', $connection->access_token);

// Test token expiration
$connection->token_expires_at = now()->subMinute();
$this->assertTrue($connection->isTokenExpired());

// Test sync failure tracking
$connection->sync_failure_count = 2;
$connection->incrementSyncFailure();
$this->assertFalse($connection->is_active); // Disabled after 3 failures
```

**CalendarEvent Model**:
```php
// Test date range scope
$events = CalendarEvent::inDateRange($start, $end)->get();

// Test conversion tracking
$this->assertFalse($event->isConverted());
$event->update(['converted_time_entry_id' => $timeEntry->id]);
$this->assertTrue($event->isConverted());
```

**CalendarIntegrationService**:
```php
// Test OAuth flow
$redirectUrl = $service->initiateOAuthFlow('google', $userId, $orgId);
$this->assertStringContainsString('accounts.google.com', $redirectUrl);

// Test state parameter encoding
$state = parse_url($redirectUrl, PHP_URL_QUERY);
parse_str($state, $params);
$decoded = json_decode(base64_decode($params['state']), true);
$this->assertEquals($userId, $decoded['user_id']);
```

### 12.2 API Endpoint Tests

**CalendarIntegrationEndpointTest**:
```php
public function test_user_can_list_connections()
{
    $data = $this->createUserWithPermission(['calendar-integrations:manage']);
    
    $connection = CalendarConnection::factory()->create([
        'user_id' => $data->user->id,
        'organization_id' => $data->organization->id,
    ]);

    Passport::actingAs($data->user);

    $response = $this->getJson(route('api.v1.calendar-integrations.index', [
        'organization' => $data->organization->id,
    ]));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['provider' => $connection->provider]);
}

public function test_premium_feature_gating()
{
    // Mock BillingContract to return false
    $this->mock(BillingContract::class)
        ->shouldReceive('hasSubscription')->andReturn(false)
        ->shouldReceive('hasTrial')->andReturn(false);

    $data = $this->createUserWithPermission(['calendar-integrations:manage']);
    Passport::actingAs($data->user);

    $response = $this->getJson(route('api.v1.calendar-integrations.index', [
        'organization' => $data->organization->id,
    ]));

    $response->assertStatus(403); // FeatureIsNotAvailableInFreePlanApiException
}
```

### 12.3 E2E Tests

**File**: `/e2e/calendar-integration.spec.ts`

```typescript
test('user can connect Google Calendar', async ({ page }) => {
    await page.goto('/calendar');
    await page.click('button:has-text("Calendar Settings")');
    await page.click('button:has-text("Connect Google Calendar")');
    
    // Should redirect to Google OAuth (mocked in test)
    await expect(page).toHaveURL(/accounts\.google\.com/);
    
    // Simulate OAuth callback
    await page.goto('/api/v1/calendar-integrations/callback?code=test&state=...');
    
    // Should redirect back to calendar with success message
    await expect(page).toHaveURL('/calendar');
    await expect(page.locator('text=Calendar connected successfully')).toBeVisible();
});

test('external events appear as overlays', async ({ page }) => {
    // Setup: create connection and events via API
    await setupCalendarEvents();
    
    await page.goto('/calendar');
    
    // External events should render as background blocks
    await expect(page.locator('.fc-event.external-event')).toHaveCount(3);
});

test('user can convert event to time entry', async ({ page }) => {
    await page.goto('/calendar');
    
    // Click external event
    await page.click('.fc-event.external-event:first-child');
    
    // Convert modal opens
    await expect(page.locator('text=Convert Event to Time Entry')).toBeVisible();
    
    // Fill form and submit
    await page.selectOption('select#project', 'project-id');
    await page.check('input#billable');
    await page.click('button:has-text("Create Time Entry")');
    
    // Time entry created, event marked as converted
    await expect(page.locator('.fc-event.converted')).toBeVisible();
});
```

---

## Summary

This architecture delivers a complete OAuth-based calendar integration feature that:

1. **Follows existing patterns**: Route model binding, service injection, `CustomAuditable`, `BaseFormRequest`, Pinia stores
2. **Implements AMD fixes**: Static callback URL, user-scoped connections with org context, premium gating via `BillingContract`, data retention cleanup
3. **Scales efficiently**: Background sync, token refresh, rate limiting, cleanup jobs
4. **Maintains security**: Encrypted tokens, CSRF protection, permission checks
5. **Provides excellent UX**: Month view, external event overlays, one-click event conversion, settings UI

**Key Decision Summary**:

| Decision | Rationale |
|----------|-----------|
| User-scoped + org-aware connections | User owns connection, org context for permissions |
| Static OAuth callback URL | Required by Google/Microsoft, state param carries org_id |
| Denormalized `user_id` in events | Query performance for date range fetches |
| Encrypted tokens via mutators | Laravel `Crypt` facade, transparent access |
| No audit on CalendarEvent | Cached external data, not primary entity (AMD-10) |
| 90-day retention window | Balance history visibility with storage (AMD-09) |
| Premium gating via BillingContract | Existing pattern, shared `has_calendar_sync` prop |
| Background sync every 15min | Balance freshness with API rate limits |
| Provider abstraction | Easy to add new providers (Outlook, iCloud, etc.) |

**Next Steps**: Proceed with Phase 1 implementation (CAL-001 through CAL-007).