# Phase 1: Shared Foundations - Research

**Researched:** 2026-02-10
**Domain:** Laravel 12 + Vue 3 cross-cutting infrastructure (notifications, permissions, date services, pre-aggregation)
**Confidence:** HIGH

<user_constraints>

## User Constraints (from CONTEXT.md)

### Locked Decisions

#### Email notification delivery
- Instant delivery only -- one email per notification event, no batching or digest
- No daily/weekly digest option at this stage

#### Email notification defaults
- New members get "critical only" notifications enabled by default
- Critical = approval-related notifications, budget threshold alerts
- Lower-priority notifications (informational updates) default to off
- Members opt in to additional types themselves

#### Email notification control
- Member self-service only -- each member manages their own notification toggles
- Admins cannot force notifications on or off for members
- Preference UI lives in organization member settings (per-member, per-type toggles)

#### Week start day
- Configurable per organization, not fixed
- Any day of the week allowed (not limited to Monday/Sunday) -- supports retail, healthcare, and non-standard week starts
- Default for new organizations: Monday
- Setting lives in organization general settings (alongside timezone, currency)
- No per-member override -- org-wide setting applies to all members
- DateBoundaryService must accept the org's week start day for all boundary calculations

#### Notification bell UI
- Dropdown panel on bell click (popover, not page navigation or drawer)
- Flat chronological list, most recent first -- no grouping by type
- Unread count badge caps at "9+" (exact number up to 9, then "9+")
- Clicking a notification navigates directly to the related entity (timesheet, budget, etc.) and marks it as read
- "Mark all as read" action available in the dropdown

### Claude's Discretion
- Email template design (branded HTML vs minimal -- pick what fits the codebase and Laravel mail ecosystem)
- Loading states and empty states within the notification dropdown
- Notification dropdown dimensions and scroll behavior
- Exact notification types to define at the infrastructure level (feature-specific types added in their phases)
- Polling interval for notification count refresh

### Deferred Ideas (OUT OF SCOPE)
None -- discussion stayed within phase scope.

</user_constraints>

## Summary

Phase 1 builds the cross-cutting infrastructure that all 17 downstream features depend on. The solidtime codebase is a **Laravel 12 (PHP 8.3) + Inertia.js + Vue 3** application using **PostgreSQL**, **Jetstream** for organization/membership management, **TanStack Query + Zodios** for API client generation, and **reka-ui + Tailwind CSS** for UI components. The existing codebase follows clear patterns: Service classes in `app/Service/`, API controllers in `app/Http/Controllers/Api/V1/` extending a base `Controller` with `PermissionStore` injection, Pinia stores in `resources/js/utils/`, and markdown-based Blade email templates.

The key deliverables split into two categories: (1) the notification system (database schema, base classes, API endpoints, bell UI, preferences UI, email delivery) and (2) structural utilities (modular permissions, DateBoundaryService, weekly_capacity schema, CI migration test, daily time summaries). Laravel's built-in notification system with database + mail channels is the correct foundation -- no external packages needed. The notification preferences system requires a custom `notification_preferences` table (per-member, per-type, per-channel) since Laravel does not provide a built-in preference mechanism. The permissions system needs refactoring away from the monolithic `configurePermissions()` method in `JetstreamServiceProvider` toward an `app/Permissions/` directory pattern where each feature registers its own permissions.

The DateBoundaryService is critical and non-trivial: the existing `TimeEntryAggregationService` already handles timezone offsets with raw PostgreSQL queries using a fixed UTC offset approach, but this is DST-unsafe. The new service must use Carbon's timezone-aware methods and the existing `Weekday` enum (which already has `carbonWeekDay()` and `toEndOfWeek()` helpers). The `daily_time_summaries` pre-aggregation table for reporting performance should be populated via a scheduled command and triggered by time entry mutations.

**Primary recommendation:** Use Laravel's native notification system (database + mail channels) for all notifications, build a custom `notification_preferences` table scoped to member + notification_type, refactor permissions into per-feature files loaded by a PermissionsServiceProvider, and implement DateBoundaryService as a pure service class wrapping Carbon with explicit timezone + week_start parameters on every method.

## Standard Stack

### Core (already in codebase)
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel Framework | ^12.19.3 | Application framework | Already installed, notifications built-in |
| Laravel Jetstream | ^5.0 | Organization/membership/permissions | Already manages roles and permissions |
| Inertia.js (Vue 3) | ^2.0.3 | SPA bridge | Already used for all page rendering |
| Vue 3 | ^3.5.0 | Frontend framework | Already installed |
| TanStack Vue Query | ^5.56.2 | Async state management / polling | Already used for API data fetching |
| reka-ui | ^2.2.0 | Headless UI primitives (Popover, etc.) | Already used for popover, accordion, etc. |
| Pinia | ^2.1.7 | Vue state management | Already used for notification/store patterns |
| dayjs | ^1.11.11 | Frontend date manipulation | Already installed |
| Carbon (via Laravel) | Built-in | PHP date/timezone handling | Standard for all date arithmetic |
| PostgreSQL | 15/16/17 | Database | Already used, CI tests all three versions |

### Supporting (already in codebase)
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| @heroicons/vue | ^2.1.1 | Icon set | Bell icon for notification UI |
| lucide-vue-next | ^0.487.0 | Additional icon set | Alternative icons if heroicons lacks one |
| tailwind-merge | ^2.6.0 | Tailwind class merging | Composing component styles |
| @floating-ui/vue | ^1.0.6 | Floating UI positioning | Already used, reka-ui PopoverContent handles this |
| openapi-zod-client | ^1.16.2 | API client generation from OpenAPI | Regenerated after new endpoints are added |
| Zodios | (via openapi-zod-client) | Type-safe API client | All API calls go through `api` client |

### No New Dependencies Needed
This phase requires zero new npm or composer packages. Everything is achievable with the existing stack:
- **Notifications:** Laravel's built-in `Illuminate\Notifications` system
- **Email templates:** Blade markdown templates (existing pattern in `resources/views/emails/`)
- **Notification bell popover:** reka-ui `Popover` components (already in `resources/js/packages/ui/src/popover/`)
- **Polling:** TanStack Query's `refetchInterval` option
- **Date arithmetic:** Carbon (already in Laravel)
- **Permissions:** Jetstream's existing `Jetstream::role()` API

## Architecture Patterns

### Backend Directory Structure (new additions marked with +)
```
app/
├── Http/Controllers/Api/V1/
│   ├── NotificationController.php          (+)
│   └── NotificationPreferenceController.php (+)
├── Models/
│   ├── Notification.php                    (+ optional, if customizing Laravel's default)
│   └── NotificationPreference.php          (+)
├── Notifications/                          (+)
│   └── BaseNotification.php                (+)
├── Permissions/                            (+)
│   ├── CorePermissions.php                 (+)
│   ├── NotificationPermissions.php         (+)
│   └── PermissionsRegistrar.php            (+)
├── Providers/
│   └── JetstreamServiceProvider.php        (modified)
├── Service/
│   ├── DateBoundaryService.php             (+)
│   ├── DailyTimeSummaryService.php         (+)
│   └── NotificationService.php             (+)
├── Console/Commands/
│   └── AggregateDailyTimeSummaries.php     (+)
└── Enums/
    ├── NotificationType.php                (+)
    └── Weekday.php                         (existing)
```

### Frontend Directory Structure (new additions marked with +)
```
resources/js/
├── Components/
│   └── NotificationBell/                   (+)
│       ├── NotificationBell.vue            (+)
│       ├── NotificationDropdown.vue        (+)
│       └── NotificationItem.vue            (+)
├── Pages/
│   └── Teams/Partials/
│       └── NotificationPreferences.vue     (+)
├── utils/
│   └── useNotificationBell.ts              (+)
└── Layouts/
    └── AppLayout.vue                       (modified - add NotificationBell)
```

### Pattern 1: Laravel Database Notifications with Custom Preferences
**What:** Use Laravel's built-in notification system with database + mail channels, but check a custom `notification_preferences` table before sending emails.
**When to use:** Every notification in the system.
**Key design:**
```php
// Source: Laravel 12 official docs - notifications
// Base notification class checks preferences in via()
abstract class BaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    abstract public function getNotificationType(): NotificationType;
    abstract public function getOrganizationId(): string;

    public function via(object $notifiable): array
    {
        $channels = ['database']; // Always store in DB

        // Check member preference for email channel
        if ($this->shouldSendEmail($notifiable)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    private function shouldSendEmail(object $notifiable): bool
    {
        $preference = NotificationPreference::query()
            ->where('member_id', $this->getMemberIdForUser($notifiable))
            ->where('notification_type', $this->getNotificationType()->value)
            ->first();

        // If no preference record exists, use the type's default
        if ($preference === null) {
            return $this->getNotificationType()->isDefaultEnabled();
        }

        return $preference->email_enabled;
    }
}
```

### Pattern 2: Modular Permissions Registration
**What:** Replace the monolithic `configurePermissions()` method with per-feature permission files.
**When to use:** Every new feature adding permissions to the system.
**Key design:**
```php
// app/Permissions/CorePermissions.php
class CorePermissions implements PermissionsProvider
{
    public function permissions(): array
    {
        return [
            'charts:view:own',
            'charts:view:all',
            'projects:view',
            // ... existing permissions
        ];
    }

    public function roles(): array
    {
        return [
            Role::Owner->value => ['*'], // all from permissions()
            Role::Admin->value => ['*'],
            Role::Manager->value => [...],
            Role::Employee->value => [...],
        ];
    }
}

// app/Permissions/PermissionsRegistrar.php
class PermissionsRegistrar
{
    /** @var array<class-string<PermissionsProvider>> */
    private array $providers = [];

    public function register(string $providerClass): void
    {
        $this->providers[] = $providerClass;
    }

    public function boot(): void
    {
        // Collect all permissions from all providers, build role arrays
        // Call Jetstream::role() once per role with merged permissions
    }
}
```

### Pattern 3: Notification Bell with TanStack Query Polling
**What:** Use TanStack Query's `refetchInterval` for the unread count, full list fetched on popover open.
**When to use:** The notification bell component in AppLayout.
**Key design:**
```typescript
// Composable for notification bell state
function useNotificationBell() {
    // Poll unread count every 30 seconds
    const { data: unreadCount } = useQuery({
        queryKey: ['notifications', 'unread-count'],
        queryFn: () => api.getNotificationUnreadCount({ ... }),
        refetchInterval: 30_000,
    });

    // Fetch full list only when dropdown is open
    const { data: notifications, refetch } = useQuery({
        queryKey: ['notifications', 'list'],
        queryFn: () => api.getNotifications({ ... }),
        enabled: isDropdownOpen,
    });
}
```

### Pattern 4: DateBoundaryService as Pure Service
**What:** Stateless service class with explicit parameters -- no hidden global state.
**When to use:** Any code needing timezone-safe week/day boundary calculations.
**Key design:**
```php
class DateBoundaryService
{
    /**
     * Get the start of the week for a given date in a given timezone.
     * Uses Carbon's timezone-aware startOfWeek which handles DST correctly.
     */
    public function getWeekStart(
        Carbon $date,
        string $timezone,
        Weekday $weekStartDay
    ): Carbon {
        return $date->copy()
            ->setTimezone($timezone)
            ->startOfWeek($weekStartDay->carbonWeekDay())
            ->startOfDay();
    }

    public function getWeekEnd(
        Carbon $date,
        string $timezone,
        Weekday $weekStartDay
    ): Carbon {
        return $this->getWeekStart($date, $timezone, $weekStartDay)
            ->addDays(6)
            ->endOfDay();
    }

    public function getDayBoundaries(
        Carbon $date,
        string $timezone
    ): array {
        $localDate = $date->copy()->setTimezone($timezone);
        return [
            'start' => $localDate->copy()->startOfDay(),
            'end' => $localDate->copy()->endOfDay(),
        ];
    }

    /**
     * Get week boundaries as UTC timestamps for database queries.
     */
    public function getWeekBoundariesUtc(
        Carbon $date,
        string $timezone,
        Weekday $weekStartDay
    ): array {
        $start = $this->getWeekStart($date, $timezone, $weekStartDay);
        $end = $this->getWeekEnd($date, $timezone, $weekStartDay);
        return [
            'start' => $start->copy()->utc(),
            'end' => $end->copy()->utc(),
        ];
    }
}
```

### Pattern 5: Daily Time Summary Pre-aggregation
**What:** Materialized summary table populated by scheduled command and on time entry mutations.
**When to use:** Reporting queries that need daily/weekly/monthly aggregation without scanning time_entries.
**Key design:**
```
daily_time_summaries table:
- id (uuid, PK)
- organization_id (uuid, FK)
- member_id (uuid, FK)
- project_id (uuid, nullable, FK)
- task_id (uuid, nullable, FK)
- date (date) -- the local date based on org timezone
- total_seconds (integer)
- billable_seconds (integer)
- billable_cost (integer, cents)
- created_at, updated_at

UNIQUE INDEX: (organization_id, member_id, project_id, task_id, date)
```

### Anti-Patterns to Avoid
- **Sending notifications on the Member model instead of User:** Laravel's `Notifiable` trait is on `User`, not `Member`. Notifications are sent to User, but preference lookups should use the Member (which ties User to Organization).
- **Using fixed UTC offset for timezone calculations:** The existing `TimeEntryAggregationService.getGroupByQuery()` uses `getShiftFromUtc()` which returns a fixed offset. This is wrong for DST-transitioning timezones. DateBoundaryService must use Carbon's proper timezone-aware methods.
- **Hardcoding notification types in via():** The `via()` method must dynamically check preferences, not use hardcoded channel lists.
- **Running aggregation in request cycle:** Daily time summary updates on time entry save should be queued or deferred, not computed synchronously in the controller.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Notification storage + retrieval | Custom notifications table | Laravel's `notifications` table via `make:notifications-table` | Handles type column, data JSON, read_at, morphable notifiable, indexing |
| Email rendering | Custom HTML email builder | Laravel Blade markdown mail templates (`@component('mail::message')`) | Already used in codebase (4 existing email templates), handles responsive layout |
| Popover positioning | Custom absolute positioning | reka-ui `Popover`/`PopoverContent`/`PopoverTrigger` | Already in codebase, handles portal, focus trap, escape dismiss, scroll lock |
| API client types | Manual TypeScript interfaces | Regenerate via `npm run zod:generate` after adding OpenAPI annotations | Zodios client auto-generates types from OpenAPI spec |
| Timezone list / validation | Custom timezone list | Existing `TimezoneService` class | Already handles IANA identifiers, legacy mapping, validation |
| Week start day enum | New enum | Existing `Weekday` enum in `App\Enums\Weekday` | Already has `carbonWeekDay()`, `toEndOfWeek()`, `toSelectArray()` |
| Permission checking | Custom middleware | Existing `PermissionStore` service | Already injected into base `Controller`, cached per request |
| UUID primary keys | Custom UUID generation | Existing `HasUuids` trait in `App\Models\Concerns\` | Already used by all models in the codebase |
| Auditing | Custom audit trail | Existing `owen-it/laravel-auditing` package | Already integrated via `CustomAuditable` trait on all models |

**Key insight:** The existing codebase has strong conventions. Every new model should use `HasUuids`, `CustomAuditable`. Every new controller should extend the V1 `Controller` base class. Every new email should use Blade markdown templates. Deviating from these patterns creates maintenance burden.

## Common Pitfalls

### Pitfall 1: Notification Preferences Table vs Notification Type Enum Drift
**What goes wrong:** New notification types get added in feature phases but the preferences seeding/defaults don't get updated, leaving new members with no preference record for new types.
**Why it happens:** The NotificationType enum grows across 7 phases but the seeding logic is written in Phase 1.
**How to avoid:** The preference check should fall back to the notification type's `isDefaultEnabled()` method when no preference record exists. Preference records are only created when a member explicitly changes a preference. This "default from enum, override from DB" pattern eliminates the drift problem entirely.
**Warning signs:** A notification type exists in the enum but members never receive emails for it.

### Pitfall 2: Sending Notifications to User vs Member Context
**What goes wrong:** A notification is sent to a User, but the notification needs organization context (e.g., "your timesheet was approved in Organization X"). The User may belong to multiple organizations.
**Why it happens:** Laravel's `Notifiable` trait is on the User model, not the Member model.
**How to avoid:** Every BaseNotification subclass must carry the `organization_id` in its constructor. The `toArray()` and `toMail()` methods use this to provide correct context. The bell UI filters notifications by the user's current organization.
**Warning signs:** Notifications showing data from the wrong organization.

### Pitfall 3: DST Boundary Errors in Week Calculations
**What goes wrong:** A week boundary calculation returns 167 or 169 hours instead of 168, or a date falls into the wrong week during spring-forward/fall-back transitions.
**Why it happens:** Using fixed UTC offsets (`getShiftFromUtc()`) instead of Carbon's timezone-aware methods. The existing `TimeEntryAggregationService` already has this bug.
**How to avoid:** Always perform date boundary calculations in the local timezone first, THEN convert to UTC for database queries. Never add fixed second offsets to UTC timestamps. Use `Carbon::setTimezone()` before `startOfWeek()`/`startOfDay()`.
**Warning signs:** Time entries showing up in the wrong week for users in DST-transitioning timezones (US, Europe, Australia).

### Pitfall 4: Notification UUID Morphs in Migration
**What goes wrong:** The Laravel notifications table migration uses `$table->morphs('notifiable')` which creates integer-based `notifiable_id`. But solidtime uses UUIDs for all primary keys.
**Why it happens:** The default `make:notifications-table` migration assumes integer IDs.
**How to avoid:** Replace `$table->morphs('notifiable')` with `$table->uuidMorphs('notifiable')` in the generated migration before running it. This is explicitly documented in Laravel's notification docs.
**Warning signs:** Foreign key constraint violations or "Data too long for column" errors when sending the first notification.

### Pitfall 5: Polling Interval Causing Excessive API Load
**What goes wrong:** Setting the notification count polling interval too low (e.g., 5 seconds) causes high API load with many concurrent users.
**Why it happens:** Each open browser tab polls independently.
**How to avoid:** Use a 30-second polling interval for the unread count endpoint. The endpoint should be extremely lightweight -- a single `COUNT(*)` query with an index on `(notifiable_id, read_at)`. Consider disabling polling when the browser tab is not focused (TanStack Query does this by default with `refetchOnWindowFocus`).
**Warning signs:** The notification count endpoint appearing as a top API endpoint in performance monitoring.

### Pitfall 6: Migration Timestamp Conflicts Across Feature Branches
**What goes wrong:** Two developers working on different features create migrations with overlapping timestamps, causing ordering issues or migration failures in CI.
**Why it happens:** Laravel migration files use timestamp prefixes. If two migrations are created at similar times on different branches, they can conflict.
**How to avoid:** Implement the CI migration ordering test (FOUND-09) that validates migration filenames are strictly ordered and have no duplicates. The test should scan `database/migrations/` and verify chronological ordering of the timestamp prefixes.
**Warning signs:** `migrate` command running migrations in unexpected order, or "table already exists" errors after branch merges.

### Pitfall 7: Permissions Registrar Loading Order
**What goes wrong:** The modular permissions system doesn't load all permission files before Jetstream tries to resolve roles, causing "permission not found" errors.
**Why it happens:** Service provider boot order matters. If permission providers are registered in a provider that boots after JetstreamServiceProvider, the permissions aren't available when Jetstream needs them.
**How to avoid:** The PermissionsRegistrar should collect permissions from all files in `app/Permissions/` during the `boot()` phase of JetstreamServiceProvider itself (or a provider that boots before it). Use explicit file discovery, not auto-discovery.
**Warning signs:** 403 errors for permissions that definitely exist in the permission files.

## Code Examples

### Notification Table Migration (UUID-safe)
```php
// Source: Laravel 12 docs - Notifications, adapted for UUID primary keys
// database/migrations/xxxx_create_notifications_table.php
Schema::create('notifications', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('type');
    $table->uuidMorphs('notifiable'); // CRITICAL: uuidMorphs, not morphs
    $table->text('data');
    $table->timestamp('read_at')->nullable();
    $table->timestamps();

    // Index for unread count query performance
    $table->index(['notifiable_id', 'notifiable_type', 'read_at']);
});
```

### Notification Preferences Table
```php
// database/migrations/xxxx_create_notification_preferences_table.php
Schema::create('notification_preferences', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('member_id')
        ->constrained('members')
        ->cascadeOnUpdate()
        ->cascadeOnDelete();
    $table->string('notification_type'); // enum value from NotificationType
    $table->boolean('email_enabled')->default(false);
    $table->timestamps();

    $table->unique(['member_id', 'notification_type']);
});
```

### Weekly Capacity Schema Extension
```php
// database/migrations/xxxx_add_weekly_capacity_columns.php
// On organizations table:
$table->integer('default_weekly_capacity')->unsigned()->default(2400); // 40h in minutes
$table->string('week_start_day', 10)->default('monday'); // Weekday enum value

// On members table:
$table->integer('weekly_capacity')->unsigned()->nullable(); // null = use org default
```

### Notification API Controller Pattern
```php
// app/Http/Controllers/Api/V1/NotificationController.php
// Following existing controller patterns (extends base Controller, uses PermissionStore)
class NotificationController extends Controller
{
    public function index(Organization $organization): JsonResponse
    {
        $this->checkPermission($organization, 'notifications:view:own');
        $user = Auth::user();

        $notifications = $user->notifications()
            ->where('data->organization_id', $organization->getKey())
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($notifications);
    }

    public function unreadCount(Organization $organization): JsonResponse
    {
        $this->checkPermission($organization, 'notifications:view:own');
        $user = Auth::user();

        $count = $user->unreadNotifications()
            ->where('data->organization_id', $organization->getKey())
            ->count();

        return response()->json(['count' => $count]);
    }

    public function markAsRead(Organization $organization, string $notificationId): JsonResponse
    {
        $user = Auth::user();
        $notification = $user->notifications()->findOrFail($notificationId);
        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    public function markAllAsRead(Organization $organization): JsonResponse
    {
        $user = Auth::user();
        $user->unreadNotifications()
            ->where('data->organization_id', $organization->getKey())
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }
}
```

### Notification Bell Vue Component Pattern
```vue
<!-- Following existing codebase patterns: reka-ui Popover, @heroicons/vue, TanStack Query -->
<script setup lang="ts">
import { Popover, PopoverTrigger, PopoverContent } from '@/packages/ui/src/popover';
import { BellIcon } from '@heroicons/vue/24/outline';
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { ref, computed } from 'vue';
import { api } from '@/packages/api/src';
import { getCurrentOrganizationId } from '@/utils/useUser';

const isOpen = ref(false);
const queryClient = useQueryClient();

// Poll unread count every 30 seconds
const { data: unreadCount } = useQuery({
    queryKey: ['notifications', 'unread-count', getCurrentOrganizationId()],
    queryFn: () => api.getNotificationUnreadCount({
        params: { organization: getCurrentOrganizationId()! }
    }),
    refetchInterval: 30_000,
});

const displayCount = computed(() => {
    const count = unreadCount.value?.count ?? 0;
    return count > 9 ? '9+' : String(count);
});
</script>
```

### Daily Time Summary Aggregation
```php
// app/Service/DailyTimeSummaryService.php
class DailyTimeSummaryService
{
    public function aggregateForDate(
        Organization $organization,
        Carbon $date
    ): void {
        $timezone = $organization->timezone ?? 'UTC';
        $dayStart = Carbon::parse($date)->setTimezone($timezone)->startOfDay()->utc();
        $dayEnd = Carbon::parse($date)->setTimezone($timezone)->endOfDay()->utc();

        // Delete existing summaries for this org + date
        DailyTimeSummary::where('organization_id', $organization->getKey())
            ->where('date', $date->format('Y-m-d'))
            ->delete();

        // Re-aggregate from time_entries
        $summaries = TimeEntry::query()
            ->where('organization_id', $organization->getKey())
            ->where('end', '>=', $dayStart)
            ->where('start', '<=', $dayEnd)
            ->whereNotNull('end')
            ->selectRaw("
                member_id,
                project_id,
                task_id,
                SUM(EXTRACT(EPOCH FROM (LEAST(\"end\", ?) - GREATEST(start, ?)))) as total_seconds,
                SUM(CASE WHEN billable THEN EXTRACT(EPOCH FROM (LEAST(\"end\", ?) - GREATEST(start, ?))) ELSE 0 END) as billable_seconds,
                SUM(CASE WHEN billable THEN EXTRACT(EPOCH FROM (LEAST(\"end\", ?) - GREATEST(start, ?))) * (COALESCE(billable_rate, 0)::float/3600) ELSE 0 END) as billable_cost
            ", [$dayEnd, $dayStart, $dayEnd, $dayStart, $dayEnd, $dayStart])
            ->groupBy(['member_id', 'project_id', 'task_id'])
            ->get();

        foreach ($summaries as $summary) {
            DailyTimeSummary::create([
                'organization_id' => $organization->getKey(),
                'member_id' => $summary->member_id,
                'project_id' => $summary->project_id,
                'task_id' => $summary->task_id,
                'date' => $date->format('Y-m-d'),
                'total_seconds' => (int) $summary->total_seconds,
                'billable_seconds' => (int) $summary->billable_seconds,
                'billable_cost' => (int) $summary->billable_cost,
            ]);
        }
    }
}
```

### CI Migration Ordering Test
```php
// tests/Database/MigrationOrderingTest.php
class MigrationOrderingTest extends TestCase
{
    public function test_migration_filenames_are_chronologically_ordered(): void
    {
        $migrationPath = database_path('migrations');
        $files = scandir($migrationPath);
        $migrations = array_filter($files, fn($f) => str_ends_with($f, '.php'));
        sort($migrations); // alphabetical = chronological for timestamp-prefixed files

        $timestamps = [];
        foreach ($migrations as $file) {
            // Extract timestamp prefix (e.g., "2024_01_20_110837")
            preg_match('/^(\d{4}_\d{2}_\d{2}_\d{6})/', $file, $matches);
            $this->assertNotEmpty($matches, "Migration {$file} does not have a valid timestamp prefix");
            $timestamp = $matches[1];

            // Check for duplicate timestamps
            $this->assertNotContains(
                $timestamp,
                $timestamps,
                "Duplicate migration timestamp: {$timestamp} in {$file}"
            );
            $timestamps[] = $timestamp;
        }

        // Verify chronological ordering
        $sorted = $timestamps;
        sort($sorted);
        $this->assertEquals($sorted, $timestamps, 'Migrations are not in chronological order');
    }
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Laravel 10/11 notification migration `morphs()` | Laravel 12 supports `uuidMorphs()` | Laravel 10+ | Must use `uuidMorphs()` for UUID-based models |
| `Illuminate\Notifications\Notifiable` on User | Same, unchanged in Laravel 12 | N/A | Trait still on User model, notifications sent to User |
| Blade mail components (`@component('mail::message')`) | Still standard in Laravel 12 | N/A | All 4 existing emails use this pattern |
| Jetstream roles defined in one monolithic method | Custom: modular permission files | Phase 1 (new) | Enables per-feature permission registration |
| Fixed UTC offset timezone math | Carbon timezone-aware methods | Should have been always | Fixes DST bugs in boundary calculations |

**Deprecated/outdated:**
- The `getShiftFromUtc()` approach in `TimeEntryAggregationService` is DST-unsafe and should not be used as a pattern for new code. DateBoundaryService replaces this approach.

## Discretion Recommendations

### Email Template Design
**Recommendation: Minimal Blade markdown templates** (matching existing codebase pattern).
The 4 existing email templates all use `@component('mail::message')` with minimal content. This is the Laravel standard and provides responsive HTML automatically via Laravel's mail theme. No need for branded HTML templates at the infrastructure level -- feature teams can enhance later. This aligns with the existing `resources/views/emails/` pattern.

### Loading/Empty States in Notification Dropdown
**Recommendation:**
- **Loading state:** Simple `LoadingSpinner` component (already exists at `@/packages/ui/src/LoadingSpinner.vue`) centered in the dropdown.
- **Empty state:** Light gray text "No notifications" centered in the dropdown, same pattern as other empty states in the codebase.

### Notification Dropdown Dimensions
**Recommendation:** Fixed width of 384px (w-96), max height of 480px with overflow-y-auto scroll. This accommodates 6-8 notification items visible at once. Position: align end (right-aligned to the bell icon) using reka-ui PopoverContent's `align="end"` prop.

### Infrastructure Notification Types
**Recommendation:** Define the following base types in Phase 1's NotificationType enum. Feature phases will add their own types.
- `test` -- for testing the notification system (development only)

The actual feature notification types (e.g., `timesheet_submitted`, `timesheet_approved`, `budget_threshold_reached`) should be added by their respective feature phases. Phase 1 builds the infrastructure; types are registered when features are built. The enum is designed to be extended.

### Polling Interval
**Recommendation: 30 seconds** for the unread count endpoint. This balances responsiveness with server load. TanStack Query's built-in `refetchOnWindowFocus: true` (default) ensures the count refreshes when the user returns to the tab, covering the common "check my notifications" flow without any additional polling cost.

## Open Questions

1. **Organization timezone column**
   - What we know: The User model has a `timezone` property, and the TimezoneService provides timezone utilities. The organization-level `week_start_day` is being added.
   - What's unclear: Does the organizations table already have a `timezone` column? The daily time summaries need an org timezone for date bucketing. The model does not expose a `timezone` property in the docblock.
   - Recommendation: Check the schema dump (`pgsql_test-schema.sql`) during implementation. If no org timezone exists, add one in the same migration as `week_start_day`. If it does exist, use it. The daily summary aggregation needs it.

2. **Notification scoping by organization in database queries**
   - What we know: Laravel's notifications table stores `data` as JSON. We need to filter by `organization_id` which would be in the JSON data column.
   - What's unclear: PostgreSQL JSON querying on the `data` column may need a GIN index for performance at scale.
   - Recommendation: Store `organization_id` in the JSON `data` field AND consider adding a dedicated indexed column if query profiling shows the JSON approach is too slow. Start with JSON-based filtering and add the column in a follow-up if needed.

3. **Notification preferences seeding for existing members**
   - What we know: New members get "critical only" defaults. But what about existing members when notifications are first deployed?
   - What's unclear: Whether existing members should be treated as "new" (get default preferences) or left with no preferences (which triggers the same defaults via the fallback mechanism).
   - Recommendation: The "no record = use type default" pattern handles this automatically. Existing members will get default behavior without any seeding migration.

## Sources

### Primary (HIGH confidence)
- **Laravel 12 Notifications Documentation** - https://laravel.com/docs/12.x/notifications - Database channel, mail channel, `uuidMorphs`, `via()` method, `toMail()`, `toArray()`
- **Codebase analysis** - Direct reading of solidtime source code in `/home/keven/Documents/solidtime-analysis/`:
  - `app/Providers/JetstreamServiceProvider.php` - Current monolithic permissions, `configurePermissions()`
  - `app/Service/PermissionStore.php` - Permission caching/checking pattern
  - `app/Models/User.php` - `Notifiable` trait already present, UUID primary keys
  - `app/Models/Member.php` - Organization-scoped membership model
  - `app/Models/Organization.php` - Organization model, Jetstream team
  - `app/Enums/Weekday.php` - Existing weekday enum with `carbonWeekDay()`
  - `app/Service/TimezoneService.php` - Timezone handling
  - `app/Service/TimeEntryAggregationService.php` - Existing aggregation patterns (DST-unsafe offset approach)
  - `app/Mail/TimeEntryStillRunningMail.php` - Existing email pattern
  - `resources/views/emails/` - Blade markdown email templates
  - `resources/js/Layouts/AppLayout.vue` - Layout structure for bell placement
  - `resources/js/utils/notification.ts` - Existing toast notification store (different from bell notifications)
  - `resources/js/packages/ui/src/popover/` - Existing reka-ui Popover components
  - `resources/js/packages/api/src/index.ts` - Zodios API client pattern
  - `routes/api.php` - API route structure
  - `.github/workflows/phpunit.yml` - CI test configuration

### Secondary (MEDIUM confidence)
- **Carbon DST handling** - https://laravel-code.tips/use-carbons-settimezone-and-shifttimezone-methods/ - `setTimezone()` vs `shiftTimezone()` for DST-safe arithmetic
- **Laracasts discussion on notification settings** - https://laracasts.com/discuss/channels/laravel/storing-notification-settings-per-user - Community pattern for per-user notification preferences

### Tertiary (LOW confidence)
- None. All findings verified against codebase or official docs.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All libraries verified in composer.json and package.json
- Architecture: HIGH - All patterns derived from existing codebase conventions
- Notifications: HIGH - Laravel's built-in notification system is well-documented, User model already has `Notifiable` trait
- Permissions: HIGH - Current implementation inspected, refactoring path clear
- DateBoundaryService: HIGH - Carbon's timezone handling is well-documented, existing Weekday enum provides foundation
- Daily summaries: MEDIUM - Aggregation query pattern verified, but org timezone column existence needs verification during implementation
- Pitfalls: HIGH - DST issue identified from actual codebase analysis of `TimeEntryAggregationService`

**Research date:** 2026-02-10
**Valid until:** 2026-03-10 (30 days -- stable Laravel 12 ecosystem, no fast-moving dependencies)
