---
phase: 01-shared-foundations
plan: 01
subsystem: notifications
tags: [laravel-notifications, vue, tanstack-query, reka-ui-popover, heroicons, polling]

# Dependency graph
requires: []
provides:
  - "BaseNotification abstract class for all downstream feature notifications"
  - "NotificationType enum extensible by feature phases"
  - "NotificationService entry point for sending notifications"
  - "Notification bell UI component in AppLayout header"
  - "NotificationPreference model for per-member per-type email toggles"
  - "6 API endpoints: list, unread-count, mark-read, mark-all-read, preferences-index, preferences-update"
affects: [02-approval-workflows, 03-governance, 04-invoicing, 05-time-capture, 06-resource-management]

# Tech tracking
tech-stack:
  added: [laravel-notifications, tanstack-vue-query-polling, reka-ui-popover]
  patterns: [BaseNotification-abstract-class, preference-aware-email-delivery, composable-polling-pattern, cookie-auth-xsrf-token]

key-files:
  created:
    - app/Notifications/BaseNotification.php
    - app/Notifications/TestNotification.php
    - app/Enums/NotificationType.php
    - app/Enums/NotificationChannel.php
    - app/Models/NotificationPreference.php
    - app/Service/NotificationService.php
    - app/Http/Controllers/Api/V1/NotificationController.php
    - app/Http/Controllers/Api/V1/NotificationPreferenceController.php
    - resources/js/Components/NotificationBell/NotificationBell.vue
    - resources/js/Components/NotificationBell/NotificationDropdown.vue
    - resources/js/Components/NotificationBell/NotificationItem.vue
    - resources/js/utils/useNotificationBell.ts
    - resources/js/Pages/Teams/Partials/NotificationPreferences.vue
    - database/migrations/2026_02_11_000001_create_notifications_table.php
    - database/migrations/2026_02_11_000002_create_notification_preferences_table.php
    - tests/Unit/Endpoint/Api/V1/NotificationEndpointTest.php
    - database/factories/NotificationPreferenceFactory.php
    - resources/views/emails/notification.blade.php
  modified:
    - routes/api.php
    - app/Providers/JetstreamServiceProvider.php
    - app/Providers/AppServiceProvider.php
    - resources/js/Layouts/AppLayout.vue
    - resources/js/Pages/Teams/Show.vue

key-decisions:
  - "Used Schema::hasTable() guard in notifications migration since table may already exist in some environments"
  - "Added ::jsonb cast for PostgreSQL JSON arrow operator queries on notification data column"
  - "Registered NotificationPreference in enforced morph map (AppServiceProvider) for model consistency"
  - "Used direct fetch with X-XSRF-TOKEN header instead of Zodios client for notification API calls (Passport cookie-based auth)"

patterns-established:
  - "BaseNotification pattern: extend BaseNotification, implement getNotificationType/getOrganizationId/getTitle/getBody/getActionUrl"
  - "Preference-aware email delivery: via() checks NotificationPreference before adding mail channel"
  - "useNotificationBell composable: TanStack Query with 30s polling interval for unread count"
  - "Cookie auth API calls: include credentials and X-XSRF-TOKEN header from XSRF-TOKEN cookie"

# Metrics
duration: 2h 41min
completed: 2026-02-10
---

# Phase 01 Plan 01: Notification System Summary

**Full notification infrastructure with preference-aware email delivery, bell UI with 30s polling in AppLayout, and per-member per-type email preference toggles**

## Performance

- **Duration:** 2h 41min
- **Started:** 2026-02-10T18:54:37Z
- **Completed:** 2026-02-10T21:35:30Z
- **Tasks:** 3 (2 auto + 1 checkpoint)
- **Files modified:** 25

## Accomplishments

- Built complete notification backend: migrations (UUID morphs), NotificationType enum, BaseNotification abstract class with preference-aware email delivery, NotificationService, 6 API endpoints with tests
- Built notification bell UI: NotificationBell component with unread badge (capped at 9+), popover dropdown with notification list, click-to-navigate-and-mark-read, mark-all-as-read, 30s polling via TanStack Query
- Built notification preferences UI: per-type email toggles grouped by category in organization member settings
- Integrated NotificationBell into AppLayout header visible on all authenticated pages

## Task Commits

Each task was committed atomically:

1. **Task 1: Notification backend (migrations, models, enums, base class, service, API controller, routes)** - `f7fde11` (feat)
2. **Task 2: Notification frontend (bell UI, preferences UI, AppLayout integration)** - `5638687` (feat)
3. **Task 2 fix: X-XSRF-TOKEN header for cookie auth** - `ff58d7a` (fix)
4. **Task 3: Verify notification system end-to-end** - checkpoint (human-verify, approved)

## Files Created/Modified

**Backend (created):**
- `database/migrations/2026_02_11_000001_create_notifications_table.php` - UUID-based notifications table with composite index
- `database/migrations/2026_02_11_000002_create_notification_preferences_table.php` - Per-member per-type email preferences
- `app/Enums/NotificationType.php` - Backed string enum with isDefaultEnabled(), label(), category()
- `app/Enums/NotificationChannel.php` - Database and Mail channels
- `app/Models/NotificationPreference.php` - Eloquent model with HasUuids, CustomAuditable, member relationship
- `app/Notifications/BaseNotification.php` - Abstract notification with preference-aware via(), toArray(), toMail()
- `app/Notifications/TestNotification.php` - Concrete test notification for infrastructure verification
- `app/Service/NotificationService.php` - Single entry point for sending notifications
- `app/Http/Controllers/Api/V1/NotificationController.php` - List, unread-count, mark-read, mark-all-read
- `app/Http/Controllers/Api/V1/NotificationPreferenceController.php` - Preferences index and update
- `app/Http/Requests/V1/Notification/NotificationIndexRequest.php` - Index request validation
- `app/Http/Requests/V1/Notification/NotificationPreferenceUpdateRequest.php` - Preference update validation
- `database/factories/NotificationPreferenceFactory.php` - Factory for testing
- `tests/Unit/Endpoint/Api/V1/NotificationEndpointTest.php` - 6 endpoint tests
- `resources/views/emails/notification.blade.php` - Email template for notification mail

**Frontend (created):**
- `resources/js/Components/NotificationBell/NotificationBell.vue` - Bell icon with badge and popover trigger
- `resources/js/Components/NotificationBell/NotificationDropdown.vue` - Dropdown panel with notification list
- `resources/js/Components/NotificationBell/NotificationItem.vue` - Individual notification with click-to-read
- `resources/js/utils/useNotificationBell.ts` - TanStack Query composable with 30s polling
- `resources/js/Pages/Teams/Partials/NotificationPreferences.vue` - Email preference toggles per type

**Modified:**
- `routes/api.php` - Added 6 notification routes in organization-scoped group
- `app/Providers/JetstreamServiceProvider.php` - Added notifications:view:own and notification-preferences:manage:own permissions
- `app/Providers/AppServiceProvider.php` - Added NotificationPreference to enforced morph map
- `resources/js/Layouts/AppLayout.vue` - Imported and placed NotificationBell in header
- `resources/js/Pages/Teams/Show.vue` - Added NotificationPreferences section

## Decisions Made

- **Schema::hasTable() guard**: Notifications migration checks if table exists before creating, since some environments may have a prior notifications table
- **PostgreSQL JSON cast**: Used `::jsonb` cast for `data->organization_id` queries to handle PostgreSQL's strict JSON typing
- **Morph map registration**: Added NotificationPreference to AppServiceProvider enforced morph map for consistency with existing model conventions
- **Direct fetch over Zodios**: Used direct fetch with credentials and XSRF token for notification API calls, since Passport cookie-based auth requires the X-XSRF-TOKEN header that the Zodios client did not automatically include

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Notifications table already existed in some migration paths**
- **Found during:** Task 1 (backend migrations)
- **Issue:** The notifications table migration could fail if table already exists
- **Fix:** Added `Schema::hasTable('notifications')` guard to wrap the create statement
- **Files modified:** `database/migrations/2026_02_11_000001_create_notifications_table.php`
- **Verification:** Migration runs without error regardless of prior table state
- **Committed in:** `f7fde11` (Task 1 commit)

**2. [Rule 1 - Bug] PostgreSQL JSON arrow operator needed ::jsonb cast**
- **Found during:** Task 1 (NotificationController queries)
- **Issue:** PostgreSQL `data->organization_id` queries failed without explicit type cast
- **Fix:** Added `::jsonb` cast to JSON column queries
- **Files modified:** `app/Http/Controllers/Api/V1/NotificationController.php`
- **Verification:** Queries return correct results on PostgreSQL
- **Committed in:** `f7fde11` (Task 1 commit)

**3. [Rule 2 - Missing Critical] NotificationPreference morph map registration**
- **Found during:** Task 1 (model creation)
- **Issue:** NotificationPreference model was not in the enforced morph map, which the codebase requires for all models
- **Fix:** Added NotificationPreference to the morph map in AppServiceProvider
- **Files modified:** `app/Providers/AppServiceProvider.php`
- **Verification:** Model resolves correctly through morph map
- **Committed in:** `f7fde11` (Task 1 commit)

**4. [Rule 1 - Bug] Missing X-XSRF-TOKEN header in notification API fetch calls**
- **Found during:** Task 2 verification (frontend testing)
- **Issue:** Passport cookie-based auth requires X-XSRF-TOKEN header from the XSRF-TOKEN cookie; without it, API calls returned 419 (CSRF token mismatch)
- **Fix:** Added XSRF token extraction from cookie and inclusion as X-XSRF-TOKEN header in all fetch calls within useNotificationBell.ts and NotificationPreferences.vue
- **Files modified:** `resources/js/utils/useNotificationBell.ts`, `resources/js/Pages/Teams/Partials/NotificationPreferences.vue`
- **Verification:** API calls succeed with proper authentication
- **Committed in:** `ff58d7a` (separate fix commit)

---

**Total deviations:** 4 auto-fixed (2 bugs, 1 missing critical, 1 blocking)
**Impact on plan:** All auto-fixes necessary for correctness and functionality. No scope creep.

## Issues Encountered

- PostgreSQL JSON queries require explicit type casting -- this is a known PostgreSQL behavior when comparing JSON values. The `::jsonb` cast resolves it cleanly.
- Laravel Passport cookie-based auth (used by solidtime) requires the XSRF token in request headers for non-GET requests and for fetch API calls. This was not documented in the plan but is a well-known Laravel pattern.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Notification infrastructure is complete and ready for downstream features
- To add a new notification type: add case to NotificationType enum, create concrete notification class extending BaseNotification, call NotificationService::send()
- Plan 01-02 (Audit Logging) can proceed independently
- Phase 2+ features (approvals, budgets, PTO) can extend NotificationType and create their own BaseNotification subclasses

## Self-Check: PASSED

- 20/20 created files verified on disk
- 3/3 commit hashes verified in git log
- 0 missing items

---
*Phase: 01-shared-foundations*
*Completed: 2026-02-10*
