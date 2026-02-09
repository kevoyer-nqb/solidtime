# Sprint Plan: Calendar Enhanced (Feature 05)

**Document Version**: 1.0
**Date**: 2026-02-06
**Feature Branch**: `feature/calendar-enhanced`
**Task ID Prefix**: `CAL-`
**PRD Reference**: `.features/05-calendar-enhanced/PRD.md`
**Architecture Reference**: `.features/05-calendar-enhanced/ARCHITECTURE.md`

---

## 1. Executive Summary

The Calendar Enhanced feature extends Solidtime's existing FullCalendar-based time tracking interface with external calendar integration (Google Calendar, Microsoft 365), a month view, event-to-entry conversion, and a planning vs. actual overlay mode. The existing calendar page already provides week/day views, drag-to-create, drag-to-move, and drag-to-resize functionality. This feature adds the remaining pieces to turn Solidtime's calendar into a unified scheduling and time-tracking hub.

**Total Effort**: 144 hours (sum of individual task hours from sprint details: 19h + 41h + 32h + 52h)
**Total Story Points**: 74 SP (sum of individual task SPs from sprint details)
**Number of Sprints**: 4 sprints (2-week sprints, 8 weeks total)
**Team Size Assumption**: 2 developers (1 Backend, 1 Frontend), with overlap on integration tasks
**Velocity Assumption**: ~40 SP per sprint (2 devs x 40h/week x 2 weeks = 160h available; ~50% utilization for feature work = 80h = 40 SP)

**Shared Foundations Prerequisite**: FOUND-007 (Modular permissions infrastructure) must be completed before Sprint 2 begins. No other FOUND-xxx tasks are hard dependencies for this feature -- Calendar Enhanced does not require the notification infrastructure (FOUND-001 through FOUND-005) or the weekly_capacity migration (FOUND-006).

---

## 2. Sprint Overview Table

| Sprint | Name | Duration | Story Points | Key Deliverables |
|:------:|------|----------|:------------:|------------------|
| 1 | Foundation & Month View | 2 weeks | 10 SP (19h) | Database schema, Eloquent models with factories, month view in FullCalendar, OAuth config in `services.php`, permissions registration |
| 2 | Backend API & Sync Engine | 2 weeks | 21 SP (41h) | CalendarIntegrationService (Google + Microsoft providers), REST API controller, background sync job, OpenAPI spec + TS client regeneration, cleanup command |
| 3 | Frontend Integration | 2 weeks | 16 SP (32h) | Pinia store, settings panel UI, external event rendering on calendar, event-to-entry conversion modal |
| 4 | Planning Overlay, Tests & Polish | 2 weeks | 27 SP (52h) | Planning vs. actual overlay, all backend tests, all frontend tests, E2E tests, documentation |
| **Total** | | **8 weeks** | **74 SP (144h)** | |

---

## 3. Dependency Map

### 3.1 Shared Foundation Dependencies

| Foundation Task | Description | Required Before | Rationale |
|-----------------|-------------|-----------------|-----------|
| FOUND-007 | Modular permissions infrastructure (`app/Permissions/`) | CAL-014 (Sprint 2) | Calendar permissions must be registered via `CalendarPermissions::register()` per AMD-03 |
| FOUND-001 to FOUND-005 | Notification infrastructure | **Not required** | Calendar Enhanced does not send notifications in its initial scope |
| FOUND-006 | Shared `weekly_capacity` migration | **Not required** | Calendar Enhanced does not use capacity data |

**Action Item**: Ensure FOUND-007 is scheduled and completed during Sprint 1 timeframe (it is a 4-hour task). If FOUND-007 is delayed, CAL-014 can be deferred to Sprint 3 without blocking other work -- permissions can be temporarily hardcoded during development.

### 3.2 Intra-Feature Dependency Graph

```
Sprint 1 (Foundation)
=====================
CAL-001 [DB Migrations]  ──────> CAL-002 [Models + Factories]
CAL-004 [Month View]             (independent, no backend dependency)
CAL-005 [Config/Env]             (independent)
CAL-014 [Permissions]            (depends on FOUND-007 only)

Sprint 2 (Backend API)
======================
CAL-002 ──> CAL-003a [Service: Core + Google Provider]
CAL-002 ──> CAL-003b [Service: Microsoft Provider]    (parallel with CAL-003a)
CAL-003a + CAL-005 ──> CAL-006 [Controller/Routes]
CAL-003a ──> CAL-007 [Background Sync Job]
CAL-006 ──> CAL-008 [OpenAPI + TS Client Regen]

Sprint 3 (Frontend Integration)
===============================
CAL-008 ──> CAL-009 [Pinia Store]
CAL-009 ──> CAL-010 [Settings Panel]
CAL-009 + CAL-004 ──> CAL-011 [External Event Rendering]
CAL-009 + CAL-011 ──> CAL-012 [Event-to-Entry Modal]

Sprint 4 (Overlay, Tests & Polish)
==================================
CAL-011 ──> CAL-013 [Planning vs Actual Overlay]
CAL-002 + CAL-003 ──> CAL-015 [Backend Unit Tests]
CAL-006 + CAL-014 ──> CAL-016 [Backend Endpoint Tests]
CAL-010 + CAL-011 + CAL-012 + CAL-013 ──> CAL-017 [Frontend Component Tests]
CAL-017 ──> CAL-018 [E2E Tests]
CAL-008 ──> CAL-019 [OpenAPI Docs]
All impl tasks ──> CAL-020 [JSDoc/PHPDoc]
CAL-002 ──> CAL-021 [Cleanup Command]
```

### 3.3 Critical Path

```
CAL-001 -> CAL-002 -> CAL-003a -> CAL-006 -> CAL-008 -> CAL-009 -> CAL-011 -> CAL-013 -> CAL-017 -> CAL-018
```

Total critical path duration: **84 hours** (~10.5 working days). This fits within the 8-week plan because backend and frontend work streams run in parallel.

### 3.4 External Dependencies

| Dependency | Type | Impact | Mitigation |
|-----------|------|--------|------------|
| Google Cloud Console: OAuth credentials | External | Cannot test OAuth flow without credentials | CAL-005 creates config stubs; actual credentials provisioned by DevOps during Sprint 1 |
| Microsoft Azure AD: App registration | External | Cannot test Microsoft OAuth without registration | Register Azure AD app during Sprint 1; Microsoft provider (CAL-003b) can be deferred if delayed |
| `google/apiclient` Composer package | Package | New dependency not yet in `composer.json` | Install during CAL-003a; pinned to `^2.15` |
| FullCalendar `dayGridPlugin` | Package | Already installed (`@fullcalendar/daygrid` v6.1.18) | No action needed |

---

## 4. Sprint Details

---

### Sprint 1: Foundation & Month View

**Sprint Goal**: Establish the database schema, Eloquent models, month view UI, OAuth configuration, and permissions so that backend API development can begin immediately in Sprint 2.

**Duration**: 2 weeks (Weeks 1-2)
**Capacity**: 80 developer-hours (2 devs x 40h/week x 2 weeks, ~50% feature allocation)

| Task ID | Description | Effort | SP | Dependencies | Assignee Role | Notes |
|---------|-------------|-------:|:--:|-------------|:-------------:|-------|
| CAL-001 | Database migrations for `calendar_connections` and `calendar_events` | 4h | 2 | None | Backend | Use `2026_03_05_` date prefix per SF-03 |
| CAL-002 | Eloquent models (CalendarConnection, CalendarEvent) with factories | 4h | 2 | CAL-001 | Backend | Include encrypted token mutators, `CustomAuditable` on CalendarConnection, `HasUuids` |
| CAL-005 | OAuth config in `config/services.php` + `.env.example` updates | 2h | 1 | None | Backend | `google_calendar` and `microsoft_calendar` config entries |
| CAL-014 | Calendar integration permissions (`CalendarPermissions::register()`) | 3h | 2 | FOUND-007 | Backend | Register `calendar-integrations:manage` for admin/manager/employee roles |
| CAL-004 | Month view frontend enhancement (`dayGridMonth` in FullCalendar) | 6h | 3 | None | Frontend | Add month to toolbar, compact event rendering, "+N more" handling, localStorage view persistence |
| | **Sprint 1 Buffer**: Credential provisioning, dev environment setup, PR reviews | ~5h | -- | -- | Shared | |

**Sprint 1 Total**: 10 SP (19h planned work)

**Acceptance Criteria**:
- Migrations run and rollback cleanly on PostgreSQL
- `CalendarConnection` and `CalendarEvent` models pass factory creation and relationship tests
- Token encryption/decryption round-trips correctly
- Month view toggle appears in calendar toolbar (Day / Week / Month)
- Time entries render as compact blocks in month view with daily totals
- "+N more" link navigates to day view
- Running entries display with pulsing indicator in month view
- `config('services.google_calendar.client_id')` returns expected values
- `CalendarPermissions::register()` adds `calendar-integrations:manage` to appropriate roles
- All existing calendar functionality (day/week drag/resize/create) continues to work

**Deliverables**:
- `database/migrations/2026_03_05_000001_create_calendar_connections_table.php`
- `database/migrations/2026_03_05_000002_create_calendar_events_table.php`
- `app/Models/CalendarConnection.php`
- `app/Models/CalendarEvent.php`
- `database/factories/CalendarConnectionFactory.php`
- `database/factories/CalendarEventFactory.php`
- `app/Permissions/CalendarPermissions.php`
- Modified `config/services.php`
- Modified `.env.example`
- Modified `resources/js/packages/ui/src/FullCalendar/TimeEntryCalendar.vue` (month view)
- New `resources/js/packages/ui/src/FullCalendar/FullCalendarMonthEventContent.vue`

**Risk Factors**:
- **FOUND-007 not ready**: If the modular permissions infrastructure is not yet created, CAL-014 can use a temporary direct registration in `JetstreamServiceProvider` and refactor later. Low impact.
- **Google/Microsoft credentials not provisioned**: Does not block Sprint 1 work; credentials are only needed for integration testing in Sprint 2.

---

### Sprint 2: Backend API & Sync Engine

**Sprint Goal**: Deliver the complete backend API layer -- OAuth flow, calendar sync service (Google + Microsoft), REST endpoints, background sync job, and regenerated TypeScript client -- so the frontend team can begin integration in Sprint 3.

**Duration**: 2 weeks (Weeks 3-4)
**Capacity**: 80 developer-hours

| Task ID | Description | Effort | SP | Dependencies | Assignee Role | Notes |
|---------|-------------|-------:|:--:|-------------|:-------------:|-------|
| CAL-003a | CalendarIntegrationService: core service + Google Calendar provider | 10h | 5 | CAL-002 | Backend | `CalendarProviderInterface`, `GoogleCalendarProvider`, token refresh, event sync, event-to-entry conversion |
| CAL-003b | Microsoft Outlook/Graph provider | 6h | 3 | CAL-002 | Backend | `MicrosoftCalendarProvider` implementing same interface; can run parallel with CAL-003a per AMD-06 |
| CAL-006 | CalendarIntegrationController + CalendarEventController + routes + request validation + resources | 12h | 6 | CAL-003a, CAL-005 | Backend | 9 endpoints total; premium gating; rate-limited sync; static OAuth callback per AMD-04 |
| CAL-007 | SyncCalendarEventsJob + SyncCalendarEventsCommand (scheduled every 15 min) | 6h | 3 | CAL-003a | Backend | `ShouldQueue`, retry logic, failure counting, `withoutOverlapping` on schedule |
| CAL-008 | OpenAPI spec update + TypeScript client regeneration | 4h | 2 | CAL-006 | Backend | New types: `CalendarConnection`, `CalendarEvent`, `ConvertEventBody` |
| CAL-021 | Calendar events cleanup command (`calendar:cleanup`) | 3h | 2 | CAL-002 | Backend | Delete events older than 90 days; weekly cron; per AMD-09 |

**Sprint 2 Total**: 21 SP (41h planned work; backend-heavy -- frontend dev can begin CAL-009 prep/spike or assist with CAL-008)

**Acceptance Criteria**:
- Google OAuth flow completes end-to-end in test environment (redirect -> consent -> callback -> token stored)
- Microsoft OAuth flow completes end-to-end in test environment
- Token refresh works automatically when tokens are within 5 minutes of expiry
- `GET /calendar-integrations` returns user's connections scoped by organization
- `POST /calendar-integrations/connect` returns a valid OAuth redirect URL
- `GET /calendar-integrations/callback` exchanges code for tokens, creates `CalendarConnection`, and redirects to calendar page
- `GET /calendar-integrations/{id}/calendars` returns available calendars from the provider
- `PUT /calendar-integrations/{id}` updates `selected_calendars` and `is_active`
- `DELETE /calendar-integrations/{id}` removes connection and cascades to events
- `POST /calendar-integrations/{id}/sync` triggers sync and returns event count; returns 429 if synced within 5 minutes
- `GET /calendar-events?start=...&end=...` returns cached events for the date range (limit 200)
- `POST /calendar-events/{id}/convert` creates a time entry, links it to the event, and respects overlap rules
- `SyncCalendarEventsJob` processes without blocking web requests
- Scheduled command dispatches sync jobs for all active connections every 15 minutes
- `calendar:cleanup` command deletes events older than 90 days
- OpenAPI spec validates; TypeScript client compiles; all new endpoints are callable via the `api` client
- Premium feature gate returns 402/403 for free-plan organizations

**Deliverables**:
- `app/Service/CalendarIntegrationService.php`
- `app/Service/Calendar/CalendarProviderInterface.php`
- `app/Service/Calendar/GoogleCalendarProvider.php`
- `app/Service/Calendar/MicrosoftCalendarProvider.php`
- `app/Http/Controllers/Api/V1/CalendarIntegrationController.php`
- `app/Http/Controllers/Api/V1/CalendarEventController.php`
- `app/Http/Requests/V1/CalendarIntegration/CalendarIntegrationConnectRequest.php`
- `app/Http/Requests/V1/CalendarIntegration/CalendarIntegrationUpdateRequest.php`
- `app/Http/Requests/V1/CalendarEvent/CalendarEventIndexRequest.php`
- `app/Http/Requests/V1/CalendarEvent/CalendarEventConvertRequest.php`
- `app/Http/Resources/V1/CalendarIntegration/CalendarConnectionResource.php`
- `app/Http/Resources/V1/CalendarIntegration/CalendarConnectionCollection.php`
- `app/Http/Resources/V1/CalendarIntegration/ExternalCalendarCollection.php`
- `app/Http/Resources/V1/CalendarEvent/CalendarEventResource.php`
- `app/Http/Resources/V1/CalendarEvent/CalendarEventCollection.php`
- `app/Jobs/SyncCalendarEventsJob.php`
- `app/Console/Commands/SyncCalendarEventsCommand.php`
- `app/Console/Commands/CleanupCalendarEventsCommand.php`
- Modified `routes/api.php`
- Modified `openapi.json`
- Regenerated `resources/js/packages/api/src/openapi.json.client.ts`
- Modified `resources/js/packages/api/src/index.ts`

**Risk Factors**:
- **CAL-003a is the largest single task (10h) and blocks CAL-006, CAL-007**: Mitigate by starting CAL-003a on day 1 of the sprint; stub the Google provider first to unblock controller development. Backend dev can work on CAL-003b in parallel during code reviews.
- **OAuth credential misconfiguration**: Test with real Google/Microsoft credentials early in the sprint. Have a fallback mock provider for unit testing.
- **Sprint is backend-heavy (41h on one dev)**: Frontend dev should assist with CAL-008 (OpenAPI/TS client regen) and begin spike/prep work for Sprint 3 Pinia store.

---

### Sprint 3: Frontend Integration

**Sprint Goal**: Deliver the complete frontend experience -- Pinia store for calendar data, settings panel for managing connections, external event overlay rendering on the calendar, and the event-to-entry conversion modal.

**Duration**: 2 weeks (Weeks 5-6)
**Capacity**: 80 developer-hours

| Task ID | Description | Effort | SP | Dependencies | Assignee Role | Notes |
|---------|-------------|-------:|:--:|-------------|:-------------:|-------|
| CAL-009 | Pinia store (`useCalendarIntegrationsStore`) | 6h | 3 | CAL-008 | Frontend | `connections`, `externalEvents`, `isLoading`, `lastSyncedAt`; CRUD methods using `api` client |
| CAL-010 | CalendarSettingsPanel + CalendarConnectionCard + CalendarSelector components | 8h | 4 | CAL-009 | Frontend | Slide-over panel from calendar header; Google/Microsoft connect buttons; calendar multi-select; premium gate UI |
| CAL-011 | External event rendering in FullCalendar (overlay events) | 10h | 5 | CAL-009, CAL-004 | Frontend | Merge external events into FullCalendar event sources; distinct styling (opacity, dashed border, provider icon); read-only; all-day support |
| CAL-012 | EventToEntryModal component (convert external event to time entry) | 8h | 4 | CAL-009, CAL-011 | Frontend | Pre-fill start/end/description; project/task/tag selection; overlap error handling; "converted" checkmark indicator |
| | **Sprint 3 Buffer**: Integration testing, PR reviews, bug fixes from Sprint 2 | ~10h | -- | -- | Shared | Backend dev assists with integration issues, starts writing backend tests |

**Sprint 3 Total**: 16 SP (32h planned frontend + integration buffer)

**Acceptance Criteria**:
- `useCalendarIntegrationsStore` correctly fetches connections and events via API
- Settings panel opens from gear icon in calendar header
- "Connect Google Calendar" and "Connect Microsoft Calendar" buttons initiate OAuth redirect
- After OAuth callback, the new connection appears in the settings panel
- User can select/deselect calendars to sync
- User can toggle connection active/inactive
- User can trigger manual sync with loading indicator
- User can delete a connection with confirmation dialog
- External events render as semi-transparent overlay blocks in day, week, and month views
- External events display title, time range, and provider icon (Google/Microsoft)
- External events use the calendar's color from the provider
- All-day events render in an all-day row (day/week views) or as full-width bars (month view)
- External events are NOT draggable, resizable, or editable
- "Last synced X minutes ago" indicator visible in calendar header
- Clicking an external event opens `EventToEntryModal` with pre-filled data
- Conversion creates a time entry and marks the event as "converted" (checkmark overlay)
- Already-converted events show the checkmark and open the time entry edit modal instead
- Overlap errors display inline with actionable messaging
- Free-plan users see an upgrade prompt instead of connection buttons

**Deliverables**:
- `resources/js/utils/useCalendarIntegrations.ts`
- `resources/js/packages/ui/src/Calendar/CalendarSettingsPanel.vue`
- `resources/js/packages/ui/src/Calendar/CalendarConnectionCard.vue`
- `resources/js/packages/ui/src/Calendar/CalendarSelector.vue`
- `resources/js/packages/ui/src/Calendar/EventToEntryModal.vue`
- `resources/js/packages/ui/src/FullCalendar/ExternalCalendarEventContent.vue`
- Modified `resources/js/packages/ui/src/FullCalendar/TimeEntryCalendar.vue` (external event source, event click handler)
- Modified `resources/js/Pages/Calendar.vue` (external events data fetching, settings panel integration)

**Risk Factors**:
- **CAL-011 (external event rendering) is technically complex**: Requires merging two event sources in FullCalendar, handling all-day slot toggle, and applying distinct CSS. Mitigate by leveraging the proven `idleStatusPlugin` overlay pattern from the codebase.
- **FullCalendar CSS customization depth**: Extensive `::deep()` CSS overrides may conflict with external event styling. Test thoroughly across all three view modes (day/week/month).
- **Timezone edge cases**: External events from different timezones must display correctly after UTC normalization. Test with multi-timezone scenarios.

---

### Sprint 4: Planning Overlay, Tests & Polish

**Sprint Goal**: Complete the planning vs. actual overlay, write all backend and frontend tests, execute E2E tests, finalize documentation, and ensure the feature is production-ready.

**Duration**: 2 weeks (Weeks 7-8)
**Capacity**: 80 developer-hours

| Task ID | Description | Effort | SP | Dependencies | Assignee Role | Notes |
|---------|-------------|-------:|:--:|-------------|:-------------:|-------|
| CAL-013 | Planning vs. actual overlay mode | 12h | 6 | CAL-011 | Frontend | Toggle in toolbar; hatched background for planned events; matched/untracked/unplanned indicators; dual totals in day header; localStorage persistence; premium-gated |
| CAL-015 | Backend unit tests (models + CalendarIntegrationService) | 8h | 4 | CAL-002, CAL-003 | Backend | Model factory tests, token encryption, provider mocking, sync logic, conversion logic |
| CAL-016 | Backend endpoint tests (CalendarIntegration + CalendarEvent controllers) | 10h | 5 | CAL-006, CAL-014 | Backend | Permission tests, premium gate tests, CRUD tests, OAuth callback, rate limiting, overlap handling |
| CAL-017 | Frontend component tests (Vitest) | 8h | 4 | CAL-010, CAL-011, CAL-012, CAL-013 | Frontend | CalendarSettingsPanel, ExternalCalendarEventContent, EventToEntryModal, PlanningOverlayToggle |
| CAL-018 | E2E tests (Playwright) | 8h | 4 | CAL-017 | Frontend | Month view navigation, settings panel flow, external event display (mocked), conversion flow, planning toggle |
| CAL-019 | OpenAPI specification documentation update | 3h | 2 | CAL-008 | Backend | Descriptions, examples, error responses for all new endpoints |
| CAL-020 | JSDoc and PHPDoc inline documentation | 3h | 2 | All impl tasks | Shared | Document all public methods in services, controllers, stores, and components |

**Sprint 4 Total**: 27 SP (52h planned work; tight but achievable -- testing is parallelizable between backend and frontend devs; buffer from earlier sprints absorbs overflow)

**Acceptance Criteria**:
- Planning overlay toggle appears in calendar toolbar (premium-gated)
- External events render with hatched/striped pattern when planning mode is active
- Matched events (calendar event aligns with time entry) show a visual indicator
- Untracked events (planned but no matching entry) are highlighted
- Day header shows "Planned: Xh" and "Actual: Yh" dual totals
- Planning toggle state persists in localStorage
- All backend unit tests pass (models, service, providers)
- All backend endpoint tests pass (14+ test cases per controller)
- All frontend component tests pass (Vitest)
- E2E tests pass (Playwright, 5+ scenarios)
- OpenAPI spec documentation is complete with examples
- All public methods have JSDoc/PHPDoc comments
- `composer fix && composer analyse` passes without errors
- `npm run lint:fix && npm run format` passes without errors

**Deliverables**:
- `resources/js/packages/ui/src/Calendar/PlanningOverlayToggle.vue`
- Modified `resources/js/packages/ui/src/FullCalendar/TimeEntryCalendar.vue` (planning overlay logic)
- Modified `resources/js/packages/ui/src/FullCalendar/FullCalendarDayHeader.vue` (planned vs. actual totals)
- `tests/Unit/Service/CalendarIntegrationServiceTest.php`
- `tests/Unit/Model/CalendarConnectionTest.php`
- `tests/Unit/Model/CalendarEventTest.php`
- `tests/Unit/Endpoint/Api/V1/CalendarIntegrationEndpointTest.php`
- `tests/Unit/Endpoint/Api/V1/CalendarEventEndpointTest.php`
- `resources/js/packages/ui/src/Calendar/__tests__/CalendarSettingsPanel.test.ts`
- `resources/js/packages/ui/src/Calendar/__tests__/EventToEntryModal.test.ts`
- `resources/js/packages/ui/src/FullCalendar/__tests__/ExternalCalendarEventContent.test.ts`
- `e2e/calendar-enhanced.spec.ts`
- Updated `openapi.json` (documentation sections)

**Risk Factors**:
- **Sprint 4 is the densest sprint at 52h**: If Sprint 3 overflows, CAL-019 and CAL-020 (documentation, 6h total) can be deferred to a follow-up PR. Tests must not be cut.
- **E2E tests require mocked external APIs**: Playwright tests cannot hit real Google/Microsoft APIs. Use MSW (Mock Service Worker) or a custom test server to mock OAuth and event responses.
- **Planning overlay (CAL-013) is P2 and the most speculative task**: If the sprint is at risk, CAL-013 can be descoped to a subsequent release. All other tasks are P1.

---

## 5. Testing Strategy Per Sprint

### Sprint 1: Foundation & Month View

| Test Type | Coverage | Details |
|-----------|----------|---------|
| Migration tests | Manual | Run `php artisan migrate` and `php artisan migrate:rollback` on PostgreSQL; verify table structure with `\d calendar_connections` and `\d calendar_events` |
| Model smoke tests | Manual | Create models via factories in `php artisan tinker`; verify relationships and encrypted attributes |
| Month view manual QA | Manual | Verify day/week/month toggle, compact rendering, "+N more", daily totals, running entry indicator |
| Regression | Manual | Confirm existing day/week view drag/resize/create functionality is unaffected |

No automated tests are written in Sprint 1. The focus is on delivering building blocks that enable automated testing in later sprints.

### Sprint 2: Backend API & Sync Engine

| Test Type | Coverage | Details |
|-----------|----------|---------|
| Integration smoke tests | Manual | End-to-end OAuth flow with real Google/Microsoft credentials in staging |
| API smoke tests | Manual via Postman/cURL | Hit all 9 endpoints; verify responses, status codes, and error cases |
| Background job verification | Manual | Dispatch `SyncCalendarEventsJob` via tinker; verify events appear in `calendar_events` table |
| Cleanup command test | Manual | Run `php artisan calendar:cleanup` with seeded old events; verify deletion |

Automated backend tests are deferred to Sprint 4 to avoid context-switching during heavy implementation. However, the backend developer should document test cases as they implement each endpoint.

### Sprint 3: Frontend Integration

| Test Type | Coverage | Details |
|-----------|----------|---------|
| Component smoke tests | Manual | Verify settings panel opens, connections display, OAuth redirects, calendar selector works |
| External event rendering QA | Manual | Verify overlay styling in day/week/month; all-day events; provider icons; converted indicators |
| Conversion flow QA | Manual | Convert event to time entry; verify pre-fill, project/task selection, overlap error handling |
| Cross-browser testing | Manual | Test in Chrome, Firefox, and Safari (especially external event CSS) |

No automated frontend tests in Sprint 3. The frontend developer should keep component structure test-friendly (props-driven, injectable stores).

### Sprint 4: Full Test Coverage

| Test Type | Task | Coverage |
|-----------|------|----------|
| **Backend Unit Tests** (CAL-015) | `CalendarIntegrationServiceTest` | Token refresh, event sync, event-to-entry conversion, provider selection, error handling |
| | `CalendarConnectionTest` | Factory, token encryption/decryption, `needsRefresh()`, `incrementSyncFailure()`, relationships |
| | `CalendarEventTest` | Factory, `isConverted()`, `scopeInDateRange()`, `scopeNotOlderThan()`, relationships |
| **Backend Endpoint Tests** (CAL-016) | `CalendarIntegrationEndpointTest` | All 7 controller methods: list, connect, callback, list calendars, update, destroy, sync |
| | `CalendarEventEndpointTest` | Index (date range filtering, limit 200, org scoping), convert (success, already converted, overlap) |
| | Permission tests | `calendar-integrations:manage` enforcement; premium gating; user-scoping (cannot access other user's connections) |
| **Frontend Component Tests** (CAL-017) | `CalendarSettingsPanel.test.ts` | Renders connections, connect button click, delete confirmation, premium gate |
| | `EventToEntryModal.test.ts` | Pre-fill from event data, form validation, submit action, overlap error display |
| | `ExternalCalendarEventContent.test.ts` | Renders title, time, provider icon, converted indicator |
| **E2E Tests** (CAL-018) | `calendar-enhanced.spec.ts` | Month view navigation, settings panel open/close, external event display (mocked API), conversion flow, planning toggle |

### Test Infrastructure Requirements

- **Backend**: Extend `ApiEndpointTestAbstract` with `Passport::actingAs()` for authenticated tests
- **Frontend**: Vitest with `@vue/test-utils` and mocked Pinia stores
- **E2E**: Playwright with API mocking (intercept external calendar API calls)
- **CI Pipeline**: All tests run on PR; E2E runs in a Docker environment with PostgreSQL

---

## 6. Definition of Done

### 6.1 Per-Task DoD Checklist

- [ ] Code implements the task description and meets all acceptance criteria listed in the PRD
- [ ] Code follows project code style: `declare(strict_types=1)` for PHP, ESLint+Prettier for TS/Vue
- [ ] No TypeScript errors (`npm run type-check` passes)
- [ ] No PHPStan errors (`composer analyse` passes)
- [ ] New files include appropriate PHPDoc/JSDoc comments on public methods
- [ ] Database migrations are reversible (`migrate:rollback` works)
- [ ] No hardcoded secrets or credentials in source code
- [ ] PR is reviewed by at least one team member
- [ ] Feature works in the development environment

### 6.2 Per-Sprint DoD Checklist

- [ ] All tasks assigned to the sprint are complete per the per-task DoD
- [ ] Sprint acceptance criteria (listed in Section 4) are verified
- [ ] `composer fix && composer analyse` passes on the feature branch
- [ ] `npm run lint:fix && npm run format` passes on the feature branch
- [ ] No regressions in existing functionality (calendar day/week views, time entry CRUD)
- [ ] Sprint demo delivered to stakeholders
- [ ] Known issues documented in the sprint retrospective

### 6.3 Feature-Level DoD Checklist

- [ ] All 21 tasks (CAL-001 through CAL-021) are complete
- [ ] All backend unit tests pass (CAL-015)
- [ ] All backend endpoint tests pass (CAL-016)
- [ ] All frontend component tests pass (CAL-017)
- [ ] All E2E tests pass (CAL-018)
- [ ] OpenAPI specification is complete and validates (CAL-019)
- [ ] Inline documentation complete (CAL-020)
- [ ] `composer fix && composer analyse` passes with zero errors
- [ ] `npm run lint:fix && npm run format` passes with zero errors
- [ ] Google Calendar OAuth flow works end-to-end in staging
- [ ] Microsoft 365 OAuth flow works end-to-end in staging
- [ ] Background sync job runs every 15 minutes without errors
- [ ] Cleanup command removes events older than 90 days
- [ ] Premium feature gating works (free-plan users see upgrade prompt; premium users access full functionality)
- [ ] Performance targets met: event fetch < 300ms (p95), overlay render < 100ms, sync < 30s for 1000 events
- [ ] Cross-browser testing complete (Chrome, Firefox, Safari)
- [ ] No security vulnerabilities: tokens encrypted at rest, OAuth state parameter validated, minimal scopes requested
- [ ] Feature branch rebased on `main` and CI pipeline green
- [ ] Product owner sign-off

---

## 7. Risk Register

### 7.1 Technical Risks

| ID | Risk | Probability | Impact | Mitigation | Owner |
|----|------|:-----------:|:------:|------------|:-----:|
| R-01 | OAuth token refresh failures cause silent sync breakdowns | Medium | High | Implement proactive refresh (5 min before expiry); `sync_failure_count` auto-disables after 3 failures; user notification on disabled connection | Backend |
| R-02 | Google/Microsoft API rate limits hit during background sync | Low | Medium | Cache events for 15 min TTL; per-connection rate limit (1 sync/5 min); exponential backoff on 429 responses; max 10 concurrent sync jobs | Backend |
| R-03 | FullCalendar performance degrades with 500+ combined events | Medium | Medium | Limit external events to 200 per date range (per AMD-05/REQ-005); use FullCalendar virtual rendering; debounce overlay rendering (300ms) | Frontend |
| R-04 | Timezone mismatches between external events and time entries | Medium | High | Normalize all external events to UTC on storage; let FullCalendar handle display conversion via `timeZone: getUserTimezone()`; test with multi-timezone edge cases | Fullstack |
| R-05 | Safari CSS rendering differences for external event overlays | Low | Low | Test `::deep()` CSS overrides on Safari early in Sprint 3; use standard CSS properties over vendor-specific ones | Frontend |
| R-06 | `google/apiclient` package conflicts with existing dependencies | Low | Medium | Test `composer require google/apiclient:^2.15` in a fresh branch; verify no version conflicts with existing packages | Backend |
| R-07 | Large `calendar_events` table slows down queries over time | Medium | Medium | Compound index on `(user_id, start, end)` for range queries; CAL-021 cleanup command removes events > 90 days; monitor table size in production | Backend |

### 7.2 Dependency Risks

| ID | Risk | Probability | Impact | Mitigation | Owner |
|----|------|:-----------:|:------:|------------|:-----:|
| R-08 | FOUND-007 (modular permissions) not completed before Sprint 2 | Low | Low | CAL-014 can temporarily register permissions directly in `JetstreamServiceProvider`; refactor when FOUND-007 lands | Backend |
| R-09 | Google Cloud Console OAuth credentials not provisioned in time | Medium | Medium | Create Google Cloud project and OAuth credentials during Sprint 1; use mock provider for unit tests regardless | DevOps |
| R-10 | Microsoft Azure AD app registration delayed by organizational approval | Medium | Medium | CAL-003b (Microsoft provider) can be deferred to a follow-up sprint without blocking Google integration; MVP works with Google alone | DevOps |

### 7.3 Capacity Risks

| ID | Risk | Probability | Impact | Mitigation | Owner |
|----|------|:-----------:|:------:|------------|:-----:|
| R-11 | Sprint 2 is backend-heavy (41h); frontend dev underutilized | Medium | Low | Frontend dev assists with CAL-008 (OpenAPI + TS client); begins CAL-009 spike/prep; writes mock data for Sprint 3 components | PM |
| R-12 | Sprint 4 is dense (52h of testing + overlay); risk of overflow | Medium | Medium | CAL-013 (planning overlay, P2) can be descoped if sprint is at risk; CAL-019/CAL-020 (documentation, 6h) can be deferred to follow-up PR | PM |
| R-13 | Developer unavailability (illness, vacation) during 8-week plan | Medium | High | Cross-train both developers on backend and frontend tasks; document all decisions in PR descriptions; maintain this sprint plan as living document | PM |

---

## 8. Milestone Timeline

```
Week 1         Week 2         Week 3         Week 4         Week 5         Week 6         Week 7         Week 8
|--- Sprint 1 ---|--- Sprint 1 ---|--- Sprint 2 ---|--- Sprint 2 ---|--- Sprint 3 ---|--- Sprint 3 ---|--- Sprint 4 ---|--- Sprint 4 ---|

 [M1]                             [M2]                             [M3]                             [M4]        [M5]
 DB + Config                      API Complete                     UI Complete                      Tests Done  SHIP
```

### Milestones

| ID | Milestone | Target Date | Sprint | Go/No-Go Criteria |
|----|-----------|-------------|:------:|-------------------|
| M1 | **Schema & Month View Ready** | End of Week 2 | S1 | Migrations run; models instantiate; month view renders in dev environment |
| M2 | **Backend API Complete** | End of Week 4 | S2 | All 9 API endpoints respond correctly; OAuth flow works with real credentials; background sync runs; TypeScript client regenerated |
| M3 | **Frontend Integration Complete** | End of Week 6 | S3 | Settings panel manages connections; external events render on calendar; event-to-entry conversion works end-to-end |
| M4 | **Test Suite Green** | Mid Week 8 | S4 | All backend unit tests, endpoint tests, frontend component tests, and E2E tests pass in CI |
| M5 | **Feature Ship-Ready** | End of Week 8 | S4 | Feature-level DoD checklist complete; product owner sign-off; PR ready for merge to `main` |

### Go/No-Go Decision Points

| Decision Point | Timing | Question | Action if "No-Go" |
|----------------|--------|----------|-------------------|
| **OAuth Credentials** | End of Sprint 1 | Are Google and Microsoft OAuth credentials provisioned and tested? | Proceed with Google only; defer Microsoft to follow-up sprint. Mock providers for all tests. |
| **API Stability** | End of Sprint 2 | Are all API endpoints stable and returning correct responses? | Extend Sprint 2 by 2-3 days; delay Sprint 3 start. Communicate timeline impact. |
| **Performance Check** | Mid Sprint 3 | Does the calendar render smoothly with 200+ external events overlaid? | Reduce event limit; add pagination; defer month view external events to follow-up. |
| **Planning Overlay Scope** | Start of Sprint 4 | Is there sufficient capacity to deliver CAL-013 alongside full test coverage? | Descope CAL-013 to follow-up release. Prioritize test coverage and production readiness. |
| **Ship Decision** | End of Sprint 4 | Does the feature meet the feature-level DoD checklist? | Extend by 1 sprint for bug fixes and polish. Do not ship with failing tests or known security issues. |

---

## Appendix A: Task ID Cross-Reference

The task assignments file uses `CAL-xxx` identifiers. Per AMD-01, all task IDs are prefixed with `CAL-`. This table provides the mapping:

| Task Assignments ID | Sprint Plan ID | Description |
|---------------------|----------------|-------------|
| CAL-001 | CAL-001 | Database migrations |
| CAL-002 | CAL-002 | Eloquent models + factories |
| CAL-003 | CAL-003a + CAL-003b | CalendarIntegrationService (split per AMD-06) |
| CAL-004 | CAL-004 | Month view frontend |
| CAL-005 | CAL-005 | Config/env setup |
| CAL-006 | CAL-006 | Controller + routes |
| CAL-007 | CAL-007 | Background sync job |
| CAL-008 | CAL-008 | OpenAPI + TS client |
| CAL-009 | CAL-009 | Pinia store |
| CAL-010 | CAL-010 | Settings panel UI |
| CAL-011 | CAL-011 | External event rendering |
| CAL-012 | CAL-012 | Event-to-entry modal |
| CAL-013 | CAL-013 | Planning vs. actual overlay |
| CAL-014 | CAL-014 | Permissions registration |
| CAL-015 | CAL-015 | Backend unit tests |
| CAL-016 | CAL-016 | Backend endpoint tests |
| CAL-017 | CAL-017 | Frontend component tests |
| CAL-018 | CAL-018 | E2E Playwright tests |
| CAL-019 | CAL-019 | OpenAPI documentation |
| CAL-020 | CAL-020 | JSDoc/PHPDoc documentation |
| (AMD-09) | CAL-021 | Calendar events cleanup command |

## Appendix B: File Manifest (All New Files)

```
app/
  Console/Commands/
    CleanupCalendarEventsCommand.php              (CAL-021)
    SyncCalendarEventsCommand.php                 (CAL-007)
  Http/
    Controllers/Api/V1/
      CalendarIntegrationController.php           (CAL-006)
      CalendarEventController.php                 (CAL-006)
    Requests/V1/
      CalendarIntegration/
        CalendarIntegrationConnectRequest.php      (CAL-006)
        CalendarIntegrationUpdateRequest.php       (CAL-006)
      CalendarEvent/
        CalendarEventIndexRequest.php              (CAL-006)
        CalendarEventConvertRequest.php            (CAL-006)
    Resources/V1/
      CalendarIntegration/
        CalendarConnectionResource.php             (CAL-006)
        CalendarConnectionCollection.php           (CAL-006)
        ExternalCalendarCollection.php             (CAL-006)
      CalendarEvent/
        CalendarEventResource.php                  (CAL-006)
        CalendarEventCollection.php                (CAL-006)
  Jobs/
    SyncCalendarEventsJob.php                     (CAL-007)
  Models/
    CalendarConnection.php                         (CAL-002)
    CalendarEvent.php                              (CAL-002)
  Permissions/
    CalendarPermissions.php                        (CAL-014)
  Service/
    CalendarIntegrationService.php                 (CAL-003a)
    Calendar/
      CalendarProviderInterface.php                (CAL-003a)
      GoogleCalendarProvider.php                   (CAL-003a)
      MicrosoftCalendarProvider.php                (CAL-003b)

database/
  factories/
    CalendarConnectionFactory.php                  (CAL-002)
    CalendarEventFactory.php                       (CAL-002)
  migrations/
    2026_03_05_000001_create_calendar_connections_table.php  (CAL-001)
    2026_03_05_000002_create_calendar_events_table.php       (CAL-001)

resources/js/
  packages/ui/src/
    Calendar/
      CalendarSettingsPanel.vue                    (CAL-010)
      CalendarConnectionCard.vue                   (CAL-010)
      CalendarSelector.vue                         (CAL-010)
      EventToEntryModal.vue                        (CAL-012)
      PlanningOverlayToggle.vue                    (CAL-013)
      __tests__/
        CalendarSettingsPanel.test.ts              (CAL-017)
        EventToEntryModal.test.ts                  (CAL-017)
    FullCalendar/
      FullCalendarMonthEventContent.vue            (CAL-004)
      ExternalCalendarEventContent.vue             (CAL-011)
      __tests__/
        ExternalCalendarEventContent.test.ts       (CAL-017)
  utils/
    useCalendarIntegrations.ts                     (CAL-009)

tests/
  Unit/
    Endpoint/Api/V1/
      CalendarIntegrationEndpointTest.php          (CAL-016)
      CalendarEventEndpointTest.php                (CAL-016)
    Model/
      CalendarConnectionTest.php                   (CAL-015)
      CalendarEventTest.php                        (CAL-015)
    Service/
      CalendarIntegrationServiceTest.php           (CAL-015)

e2e/
  calendar-enhanced.spec.ts                        (CAL-018)
```

## Appendix C: Modified Existing Files

```
config/services.php                                (CAL-005)
.env.example                                       (CAL-005)
routes/api.php                                     (CAL-006)
openapi.json                                       (CAL-008, CAL-019)
resources/js/packages/api/src/openapi.json.client.ts  (CAL-008)
resources/js/packages/api/src/index.ts             (CAL-008)
resources/js/packages/ui/src/FullCalendar/TimeEntryCalendar.vue  (CAL-004, CAL-011, CAL-013)
resources/js/packages/ui/src/FullCalendar/FullCalendarEventContent.vue  (CAL-004)
resources/js/packages/ui/src/FullCalendar/FullCalendarDayHeader.vue  (CAL-013)
resources/js/Pages/Calendar.vue                    (CAL-011)
app/Providers/JetstreamServiceProvider.php         (CAL-014, one-line addition)
```

---

*Last updated: 2026-02-06*
*Generated by: Sprint Planning (Claude Opus 4.6)*
