# Sprint Plan: Kiosk & Clock Mode

**Feature ID**: 06-kiosk-clock-mode
**Date**: 2026-02-06
**Status**: Sprint Planning
**PRD Reference**: `.features/06-kiosk-clock-mode/PRD.md`
**Architecture Reference**: `.features/06-kiosk-clock-mode/ARCHITECTURE.md`

---

## 1. Executive Summary

The Kiosk & Clock Mode feature enables organizations to deploy Solidtime on shared devices (tablets, wall-mounted screens, reception terminals) where multiple employees can clock in and out using simplified authentication (4-digit PIN or QR code). The feature introduces a standalone full-screen kiosk interface with token-based device authentication, per-punch member identification via PIN/QR, break tracking, and a real-time Attendance Dashboard for managers and admins.

### Key Metrics

| Metric | Value |
|--------|-------|
| **Total Story Points** | ~100 SP (including amendments and shared foundations) |
| **Total Effort Hours** | ~200 hours (152h feature + 12h shared foundations + ~36h amendment tasks) |
| **Number of Sprints** | 4 sprints (8 weeks) |
| **Sprint Duration** | 2 weeks each |
| **Team Size** | 2 developers (1 backend, 1 frontend/fullstack) + 1 QA (part-time, ~50% in Sprints 3-4) |
| **Feature Branch** | `feature/kiosk-clock-mode` |
| **Task ID Prefix** | `KIO-` |
| **Migration Date Prefix** | `2026_03_06_` |

### Scope Summary

- **Backend**: Custom auth guard, 3 new Eloquent models, 2 enums, 4 service classes, 3 controllers, 4 database migrations, 1 artisan command, modular permissions
- **Frontend**: Standalone Vue SPA for kiosk terminals (not Inertia), Inertia-based Attendance Dashboard page, Kiosk Management admin UI, Member PIN Management UI, QR code scanner/generator
- **Testing**: API endpoint tests, service unit tests, Vitest component tests, Playwright E2E tests
- **Infrastructure**: Separate Vite entry point, Blade template, custom auth guard registration

---

## 2. Sprint Overview Table

| Sprint | Name | Duration | Story Points | Key Deliverables |
|:------:|------|----------|:------------:|------------------|
| 0 | Shared Foundations (pre-work) | 1 week (overlaps with prior work) | 5 SP | FOUND-007 modular permissions infrastructure; dependency readiness verification |
| 1 | Database, Models & Admin API | Weeks 1-2 | 23 SP | Database schema, Eloquent models, enums, Kiosk CRUD API, PIN Management API, permissions, TS types, kiosk layout scaffold |
| 2 | Auth Guard, Device API & Kiosk UI | Weeks 3-4 | 36 SP | Custom kiosk auth guard, device API endpoints (PIN/QR auth, clock in/out/break), kiosk full-screen Vue SPA, QR scanner, badge QR tokens, kiosk management frontend |
| 3 | Attendance, Breaks & Polish | Weeks 5-6 | 22 SP | Break tracking refinement, attendance dashboard (backend + frontend), member PIN management UI, stale session cleanup, KioskSessionService unit tests |
| 4 | Testing, Integration & Release | Weeks 7-8 | 19 SP | Backend API tests, frontend component tests, E2E Playwright tests, OpenAPI spec update, TS client regeneration, performance validation, security review |

**Total: ~100 SP across 4 sprints + pre-work**

---

## 3. Dependency Map

### 3.1 Shared Foundation Dependencies

The Kiosk feature requires a subset of the Shared Foundation tasks defined in `SHARED-FOUNDATIONS.md`. The following table maps which FOUND-xxx tasks must complete before which KIO-xxx tasks can begin.

| Foundation Task | Description | Required Before | Rationale |
|-----------------|-------------|-----------------|-----------|
| **FOUND-007** | Modular permissions infrastructure (`app/Permissions/` pattern) | KIO-004 (Permissions Registration) | Kiosk permissions must use the modular `KioskPermissions::register()` pattern per AMD-03, not direct JetstreamServiceProvider modification |
| FOUND-001 | Notification infrastructure migration | Not blocking for MVP | Kiosk v1 does not send notifications; future enhancement for admin alerts on lockouts |
| FOUND-002 | Base notification classes | Not blocking for MVP | Same as above |
| FOUND-003 | Notification bell UI | Not blocking for MVP | Same as above |
| FOUND-004 | Notification API endpoints | Not blocking for MVP | Same as above |
| FOUND-005 | Notification preferences | Not blocking for MVP | Same as above |
| FOUND-006 | Shared weekly_capacity migrations | Not blocking | Kiosk feature does not use weekly_capacity |

**Hard dependency**: Only **FOUND-007** (4 hours, 2 SP) is a hard prerequisite. It must be completed before or during Sprint 1.

### 3.2 Inter-Task Dependencies (Within Feature)

```
FOUND-007 ─────────────────────────────────────────────────┐
                                                           │
KIO-001 (Migrations) ──┬──> KIO-002 (Models) ──┬──> KIO-003 (CRUD API) ──┬──> KIO-013 (Mgmt UI)
                       │                        │                          │
                       │                        ├──> KIO-004 (Permissions)─┘──> KIO-015 (Mgmt Tests)
                       │                        │         │ (requires FOUND-007)
                       │                        │         │
                       │                        └──> KIO-006 (Auth Guard) ──┐
                       │                                                     │
                       └──> KIO-005 (PIN API) ──────────────────────────────┼──> KIO-007a (PIN/QR Auth)
                                  │                                          │
                                  │                                          └──> KIO-007b (Clock In/Out)
                                  │                                                    │
                                  ├──> KIO-014 (PIN Mgmt UI)                          ├──> KIO-008a (Kiosk Idle+PIN)
                                  │                                                    │         │
                                  └──> KIO-021 (Badge QR Token)                       │    KIO-008b (Clock Status)
                                                                                       │         │
                                                                                       │    KIO-009 (QR Scanner)
                                                                                       │
                                                                                       ├──> KIO-010 (Break Logic)
                                                                                       │
                                                                                       ├──> KIO-011 (Attendance API)
                                                                                       │         │
                                                                                       │    KIO-012 (Attendance UI)
                                                                                       │
                                                                                       └──> KIO-020 (Cleanup Command)

Testing Tasks:
KIO-015 (Mgmt Tests) ──────> requires KIO-003, KIO-004, KIO-005
KIO-016 (Device Tests) ────> requires KIO-007a/b, KIO-010
KIO-017 (Frontend Tests) ──> requires KIO-008a/b, KIO-012, KIO-013
KIO-018 (E2E Tests) ───────> requires KIO-008a/b, KIO-012, KIO-013, KIO-014
KIO-019 (OpenAPI) ─────────> requires KIO-003, KIO-005, KIO-007a/b, KIO-011
```

### 3.3 External Feature Dependencies

| External Feature | Dependency Type | Impact |
|------------------|----------------|--------|
| None | -- | Kiosk & Clock Mode is classified as "fully independent" in SF-09 deployment order. It can be developed in parallel with all other features. |
| Feature 10 (Teams & Groups) | Soft/Future | If team scoping is enabled later, kiosk attendance dashboard could filter by team. Not required for MVP. |
| Feature 01 (Timesheet Approvals) | Soft/Future | Kiosk-created time entries could be subject to approval workflows. Not required for MVP. |

### 3.4 Amendment Tasks (PRD Amendments)

The PRD review introduced several amendments that create additional tasks or modify existing ones:

| Amendment | Task Impact | Sprint |
|-----------|------------|--------|
| AMD-04 (Dual-Hash PIN) | Modifies KIO-001 migration and KIO-005 service logic | Sprint 1 |
| AMD-05 (Badge QR Token) | New task KIO-021 (6h / 3 SP) | Sprint 2 |
| AMD-09 (Split KIO-008) | KIO-008 becomes KIO-008a + KIO-008b (8h + 8h) | Sprint 2 |
| AMD-10 (Split KIO-007) | KIO-007 becomes KIO-007a + KIO-007b (6h + 6h) | Sprint 2 |
| AMD-11 (Frontend Sprint 1) | New tasks KIO-022 (2h/1SP) + KIO-023 (3h/2SP) | Sprint 1 |
| AMD-12 (Earlier Testing) | KIO-015 unit tests moved to Sprint 3 alongside KIO-006 | Sprint 3 |
| AMD-13 (HasFactory) | KioskPinAttempt includes HasFactory trait | Sprint 1 (KIO-002) |

---

## 4. Sprint Details

---

### Sprint 0: Shared Foundations (Pre-Work)

**Sprint Goal**: Establish the modular permissions infrastructure so the Kiosk feature can register permissions without merge conflicts in JetstreamServiceProvider.

**Duration**: Completed before Sprint 1 begins (can overlap with prior sprint or be done as pre-work).

| Task ID | Description | Effort | SP | Dependencies | Assignee Role |
|---------|-------------|--------|:--:|-------------|---------------|
| FOUND-007 | Create modular permissions infrastructure: `app/Permissions/` directory, refactor existing permissions into modular pattern, update `JetstreamServiceProvider::configurePermissions()` | 4h | 2 | None | Backend |

**Acceptance Criteria**:
- `app/Permissions/` directory exists with the modular registration pattern
- Existing permissions are refactored into this structure
- `JetstreamServiceProvider::configurePermissions()` calls each permissions class
- `composer fix && composer analyse` passes
- All existing tests continue to pass (no regression)

**Deliverables**:
- `app/Permissions/` directory with base pattern
- Modified `app/Providers/JetstreamServiceProvider.php`

**Risk Factors**:
- Low risk. This is a refactoring task with no functional change.
- Risk: If other features are being developed simultaneously, coordinate the JetstreamServiceProvider merge.

---

### Sprint 1: Database, Models & Admin API (Weeks 1-2)

**Sprint Goal**: Build the entire data foundation and admin-facing API so that kiosk devices can be created, listed, updated, and deleted, and member PINs can be managed, all with proper permission enforcement.

| Task ID | Description | Effort | SP | Dependencies | Assignee Role |
|---------|-------------|--------|:--:|-------------|---------------|
| KIO-001 | Database migrations: `kiosks`, `kiosk_sessions`, `kiosk_pin_attempts` tables + `members` alterations (dual-hash PIN columns per AMD-04, badge token columns per AMD-05) | 4h | 2 | None | Backend |
| KIO-002 | Eloquent models: `Kiosk`, `KioskSession`, `KioskPinAttempt` (with `HasFactory` per AMD-13) + enums (`KioskMode`, `KioskSessionStatus`) + `Member`/`Organization` relationship updates | 6h | 3 | KIO-001 | Backend |
| KIO-003 | Kiosk Management API: CRUD controller, form requests, resources, `KioskService` (token generation, session cleanup on delete) + routes in `api.php` | 10h | 5 | KIO-002 | Backend |
| KIO-004 | Kiosk Permissions: Create `app/Permissions/KioskPermissions.php` with `kiosks:view`, `kiosks:create`, `kiosks:update`, `kiosks:delete` per AMD-03 modular pattern | 2h | 1 | KIO-002, FOUND-007 | Backend |
| KIO-005 | Member PIN Management API: `MemberPinController`, `MemberPinService` (dual-hash per AMD-04), `KioskQrService` (JWT generation), PIN set/update/remove + QR token generation endpoints | 6h | 3 | KIO-001, KIO-004 | Backend |
| KIO-022 | Create TypeScript type definitions for kiosk models (`resources/js/types/kiosk.d.ts`) per AMD-11 | 2h | 1 | None | Frontend |
| KIO-023 | Scaffold kiosk standalone Vue SPA layout: `resources/js/kiosk.ts` entry point, `resources/views/kiosk.blade.php` template, Vite config update, route in `web.php` per AMD-11 | 3h | 2 | None | Frontend |

**Sprint 1 Totals**: 33h effort, 17 SP (Backend: 28h / Frontend: 5h)

**Acceptance Criteria**:
- All 4 database migrations run and rollback cleanly
- `Kiosk`, `KioskSession`, `KioskPinAttempt` models pass PHPStan analysis
- All CRUD endpoints for kiosks respond correctly (201/200/204 with proper payloads)
- Token is generated on kiosk creation and returned only once
- Token regeneration invalidates old token
- Kiosk deletion ends all active sessions for that kiosk
- PIN can be set, updated, and removed for members
- PIN uniqueness is enforced within the organization using dual-hash (SHA-256 for check, bcrypt for auth)
- QR token JWT is generated with 5-minute TTL signed with APP_KEY
- Permission enforcement: Owner/Admin have full CRUD, Manager has view-only, Employee has none
- `check-organization-blocked` middleware on all write endpoints (including delete per AMD-06)
- TypeScript type definitions compile without errors
- Kiosk SPA skeleton renders at `/kiosk/{token}` (blank page with Vue mounted)
- `composer fix && composer analyse` passes
- `npm run lint:fix && npm run format` passes

**Deliverables**:

| Category | Files |
|----------|-------|
| Migrations | `database/migrations/2026_03_06_000001_create_kiosks_table.php` |
| | `database/migrations/2026_03_06_000002_create_kiosk_sessions_table.php` |
| | `database/migrations/2026_03_06_000003_add_kiosk_pin_and_badge_fields_to_members_table.php` |
| | `database/migrations/2026_03_06_000004_create_kiosk_pin_attempts_table.php` |
| Models | `app/Models/Kiosk.php`, `app/Models/KioskSession.php`, `app/Models/KioskPinAttempt.php` |
| Enums | `app/Enums/KioskMode.php`, `app/Enums/KioskSessionStatus.php` |
| Controllers | `app/Http/Controllers/Api/V1/KioskController.php` |
| | `app/Http/Controllers/Api/V1/MemberPinController.php` |
| Requests | `app/Http/Requests/V1/Kiosk/KioskStoreRequest.php`, `KioskUpdateRequest.php` |
| | `app/Http/Requests/V1/Member/MemberPinUpdateRequest.php` |
| Resources | `app/Http/Resources/V1/Kiosk/KioskResource.php`, `KioskCollection.php`, `KioskWithTokenResource.php` |
| Services | `app/Service/KioskService.php`, `app/Service/MemberPinService.php`, `app/Service/KioskQrService.php` |
| Permissions | `app/Permissions/KioskPermissions.php` |
| Factories | `database/factories/KioskFactory.php`, `database/factories/KioskSessionFactory.php` |
| Frontend | `resources/js/types/kiosk.d.ts` |
| | `resources/js/kiosk.ts`, `resources/views/kiosk.blade.php` |
| Modified | `routes/api.php`, `app/Models/Member.php`, `app/Models/Organization.php`, `vite.config.js`, `routes/web.php` |

**Risk Factors**:
- **FOUND-007 not ready**: If the modular permissions infrastructure is not completed before Sprint 1, KIO-004 is blocked. Mitigation: FOUND-007 is only 4 hours; schedule it as Day 1 pre-work.
- **Dual-hash PIN complexity (AMD-04)**: The SHA-256 + bcrypt pattern adds complexity to KIO-005. Mitigation: Architecture doc provides complete code snippets; follow them precisely.
- **Frontend developer underutilized**: Only 5h of frontend work in Sprint 1. Mitigation: AMD-11 added KIO-022 and KIO-023 specifically to address this. Frontend dev can also assist with factory creation and review backend API contracts.

---

### Sprint 2: Auth Guard, Device API & Kiosk UI (Weeks 3-4)

**Sprint Goal**: Deliver a fully functional kiosk experience -- from device authentication through PIN/QR member identification to clock in/out -- with the kiosk management admin UI wired up.

| Task ID | Description | Effort | SP | Dependencies | Assignee Role |
|---------|-------------|--------|:--:|-------------|---------------|
| KIO-006 | Custom kiosk authentication guard: `KioskGuard`, `KioskTokenProvider`, `AuthenticateKiosk` middleware, auth config registration, rate limiting (60 req/min per kiosk) | 8h | 4 | KIO-002 | Backend |
| KIO-007a | Kiosk device API -- authentication: PIN auth endpoint (with rate limiting via `kiosk_pin_attempts`), QR auth endpoint (JWT verification), per AMD-10 | 6h | 3 | KIO-005, KIO-006 | Backend |
| KIO-007b | Kiosk device API -- clock operations: clock-in, clock-out, break start/end, status, attendance endpoints + `KioskSessionService` (state machine, time entry creation, transactions), per AMD-10 | 6h | 3 | KIO-007a | Backend |
| KIO-021 | Badge QR token support per AMD-05: add admin API for generating/revoking badge tokens, configurable TTL (24h/7d/30d/none), badge QR generation | 6h | 3 | KIO-001, KIO-004 | Backend |
| KIO-008a | Kiosk full-screen Vue page -- idle screen + PIN entry: `KioskApp.vue` state machine, `KioskIdleScreen.vue`, `KioskPinPad.vue`, `KioskErrorScreen.vue`, `useKiosk.ts` store, per AMD-09 | 8h | 4 | KIO-007a | Frontend |
| KIO-008b | Kiosk full-screen Vue page -- clock status + actions: `KioskMemberStatus.vue`, `KioskConfirmation.vue`, `KioskBreakScreen.vue`, clock in/out/break buttons, per AMD-09 | 8h | 4 | KIO-008a, KIO-007b | Frontend |
| KIO-009 | QR code scanner component: `KioskQrScanner.vue` (camera-based), `MemberQrCodeModal.vue` (generation + download), integrate with kiosk auth flow | 8h | 4 | KIO-008a | Frontend |
| KIO-013 | Kiosk management frontend: `KioskTable`, `KioskCreateModal`, `KioskEditModal`, `KioskTokenModal`, `KioskMoreOptionsDropdown`, `useKiosks.ts` Pinia store | 10h | 5 | KIO-003, KIO-008a | Frontend |

**Sprint 2 Totals**: 60h effort, 30 SP (Backend: 26h / Frontend: 34h)

**Note on Parallel Execution**:
- **Week 3**: Backend works on KIO-006 then KIO-007a while frontend works on KIO-008a (can mock API responses based on documented contracts) and KIO-013
- **Week 4**: Backend works on KIO-007b and KIO-021 while frontend works on KIO-008b, KIO-009

**Acceptance Criteria**:
- Valid kiosk token authenticates; invalid/expired/inactive returns 401
- `last_activity_at` updated on successful auth (throttled to once per minute)
- Auth guard does not interfere with `auth:api` or `auth:web`
- PIN authentication: valid PIN returns member data + session status; invalid PIN returns 401 with remaining attempts; 5 failures in 15 min triggers 10-min lockout (429)
- QR authentication: valid JWT authenticates member; expired JWT returns 401; wrong-org JWT returns 401
- Clock in: creates `KioskSession` + `TimeEntry` atomically; duplicate returns 409
- Clock out: ends session + time entry atomically; not-clocked-in returns 409
- Break start/end: correct state transitions; clock out during break ends break first
- Debounce: duplicate requests within 5 seconds rejected
- Badge QR tokens: admin can generate with configurable TTL; admin can revoke; kiosk accepts both dynamic and badge tokens
- Kiosk page loads at `/kiosk/{token}` without user login
- PIN pad has large touch-friendly buttons (min 64px), auto-submits on 4th digit
- Clock in/out buttons are prominent; confirmation screen auto-returns to idle after 5 seconds
- QR scanner activates camera; gracefully hides if camera denied
- Kiosk management table shows all kiosks; create/edit/delete modals work; token shown only on create/regenerate
- Kiosk polls `/kiosk/status` every 30 seconds; offline detection overlay shown on failure
- `Cache-Control: no-store` and `X-Robots-Tag: noindex` headers on kiosk page (AMD-08)

**Deliverables**:

| Category | Files |
|----------|-------|
| Auth | `app/Auth/KioskGuard.php`, `app/Auth/KioskTokenProvider.php` |
| Middleware | `app/Http/Middleware/AuthenticateKiosk.php` |
| Controllers | `app/Http/Controllers/Api/V1/Kiosk/KioskDeviceController.php` |
| Requests | `KioskPinAuthRequest.php`, `KioskQrAuthRequest.php`, `KioskClockInRequest.php`, `KioskClockOutRequest.php`, `KioskBreakRequest.php` |
| Resources | `KioskSessionResource.php`, `KioskMemberResource.php`, `KioskAttendanceResource.php` |
| Services | `app/Service/KioskSessionService.php` |
| Vue (Kiosk SPA) | `KioskApp.vue`, `KioskIdleScreen.vue`, `KioskPinPad.vue`, `KioskMemberStatus.vue`, `KioskConfirmation.vue`, `KioskBreakScreen.vue`, `KioskErrorScreen.vue` |
| Vue (UI Components) | `KioskClock.vue`, `KioskButton.vue`, `KioskNumpad.vue`, `KioskQrScanner.vue` |
| Vue (Admin) | `KioskTable.vue`, `KioskCreateModal.vue`, `KioskEditModal.vue`, `KioskTokenModal.vue`, `KioskMoreOptionsDropdown.vue` |
| Vue (Member) | `MemberQrCodeModal.vue` |
| Stores | `resources/js/utils/useKiosk.ts`, `resources/js/utils/useKiosks.ts` |
| Modified | `config/auth.php`, `app/Providers/AuthServiceProvider.php`, `app/Http/Kernel.php`, `routes/api.php` |

**Risk Factors**:
- **KIO-006 (Auth Guard) is the highest-risk task**: No existing custom auth guard in the codebase to reference. Mitigation: Architecture doc provides complete implementation code; test early with Postman/curl before frontend integration.
- **Frontend blocked on backend API (KIO-008a depends on KIO-007a)**: Mitigation: Frontend can begin with mocked API responses (API contracts are fully specified in PRD); integrate when backend is ready.
- **Sprint 2 has the highest story point count (30 SP)**: Mitigation: Tasks are split per AMD-09 and AMD-10; parallel tracks (backend/frontend) enable concurrent execution; KIO-013 can slip to Sprint 3 Week 1 if needed without blocking the critical path.
- **Camera API compatibility for QR scanning (KIO-009)**: Mitigation: QR is P1 not P0; PIN-only mode is fully functional as fallback; research `html5-qrcode` compatibility on target tablets early.
- **NPM package additions**: `html5-qrcode` and `qrcode` packages need to be added. Mitigation: Research and evaluate packages in Sprint 1 during frontend scaffolding.

---

### Sprint 3: Attendance, Breaks & Polish (Weeks 5-6)

**Sprint Goal**: Complete the attendance dashboard (backend + frontend), refine break tracking logic, deliver member PIN management UI, add the stale session cleanup command, and write KioskSessionService unit tests alongside implementation.

| Task ID | Description | Effort | SP | Dependencies | Assignee Role |
|---------|-------------|--------|:--:|-------------|---------------|
| KIO-010 | Break tracking logic refinement in `KioskSessionService`: time entry splitting around breaks, `total_break_seconds` accumulation, clock-out-during-break, break > 4h warning, midnight edge case | 6h | 3 | KIO-007b | Backend |
| KIO-011 | Attendance dashboard backend API: `AttendanceController`, `AttendanceService`, summary + per-member status endpoint, date/status filters, web/API source detection, N+1 optimization | 6h | 3 | KIO-007b | Backend |
| KIO-020 | Stale session cleanup artisan command: `KioskCleanupStaleSessions`, auto-clock-out after 16h, PIN attempt cleanup > 24h, schedule hourly in Kernel, idempotent | 4h | 2 | KIO-007b | Backend |
| KIO-015 | Backend API tests -- Kiosk Management: `KioskEndpointTest`, `MemberPinEndpointTest`, factories, CRUD + permission + uniqueness + org isolation tests (moved per AMD-12) | 8h | 4 | KIO-003, KIO-004, KIO-005 | Backend |
| KIO-012 | Attendance dashboard Vue page: `Attendance.vue` (Inertia page with AppLayout), summary cards, member table with status badges, auto-refresh 30s, filters, search, sidebar nav item | 12h | 5 | KIO-011 | Frontend |
| KIO-014 | Member PIN management frontend: `MemberPinModal.vue`, profile PIN section, admin PIN management in `MemberEditModal`, PIN status indicator, duplicate PIN error display | 6h | 3 | KIO-005, KIO-013 | Frontend |

**Sprint 3 Totals**: 42h effort, 20 SP (Backend: 24h / Frontend: 18h)

**Note on Parallel Execution**:
- **Week 5**: Backend works on KIO-010 and KIO-011 while frontend works on KIO-014 (PIN UI, which depends on Sprint 1's KIO-005 API) and begins KIO-012 layout
- **Week 6**: Backend works on KIO-020 and KIO-015 (tests) while frontend completes KIO-012

**Acceptance Criteria**:
- Break start correctly ends active time entry; break end correctly starts new time entry
- `total_break_seconds` accurately accumulated across multiple breaks
- Clock out during break: break ended first, then session closed, time entry ended
- No gaps or overlaps in time entries around breaks
- Attendance endpoint returns correct summary counts + per-member status
- Web/API running time entries included as "working" members with `source: 'web'` or `source: 'api'`
- Date and status filters work on attendance endpoint
- Query performance acceptable for 500+ members (no N+1)
- Stale session cleanup command identifies sessions > 16h old and auto-closes them
- PIN attempt records > 24h are cleaned up
- Cleanup command is idempotent and scheduled hourly
- Attendance dashboard page accessible at `/attendance`
- Sidebar "Attendance" link visible only for users with `time-entries:view:all` permission
- Summary cards show correct counts; auto-refresh every 30 seconds
- Status badges: green (working), yellow (on break), gray (off)
- Search by member name works
- Empty state shown when no kiosks configured
- Member PIN modal works from profile (self-service) and member edit modal (admin)
- Duplicate PIN error displayed; PIN status indicator shows set/unset
- All KioskEndpointTest and MemberPinEndpointTest tests pass

**Deliverables**:

| Category | Files |
|----------|-------|
| Controllers | `app/Http/Controllers/Api/V1/AttendanceController.php` |
| Requests | `app/Http/Requests/V1/Attendance/AttendanceIndexRequest.php` |
| Resources | `AttendanceResource.php`, `AttendanceSummaryResource.php` |
| Services | `app/Service/AttendanceService.php` (new) |
| Commands | `app/Console/Commands/Kiosk/KioskCleanupStaleSessions.php` |
| Tests | `tests/Unit/Endpoint/Api/V1/KioskEndpointTest.php` |
| | `tests/Unit/Endpoint/Api/V1/MemberPinEndpointTest.php` |
| Vue (Pages) | `resources/js/Pages/Attendance.vue` |
| Vue (Components) | `AttendanceSummaryCards.vue`, `AttendanceTable.vue`, `AttendanceTableRow.vue`, `AttendanceTableHeading.vue`, `AttendanceStatusBadge.vue`, `AttendanceFilterBar.vue` |
| Vue (Member) | `MemberPinModal.vue` |
| Stores | `resources/js/utils/useAttendance.ts` |
| Modified | `routes/api.php`, `routes/web.php`, `app/Console/Kernel.php`, `resources/js/Layouts/AppLayout.vue`, `resources/js/utils/useMembers.ts`, `app/Service/KioskSessionService.php` |

**Risk Factors**:
- **Break logic edge cases**: Midnight-spanning breaks and time entry splitting require careful transaction handling. Mitigation: Write unit tests (KIO-015 tests) alongside implementation per AMD-12.
- **Attendance dashboard performance**: 500+ members query could be slow without proper indexing. Mitigation: Partial indexes already designed in ARCHITECTURE.md; verify with EXPLAIN ANALYZE.
- **Sidebar navigation change**: Modifying `AppLayout.vue` could conflict with other feature branches. Mitigation: Use a single, minimal change (add one `NavigationSidebarItem`); coordinate with other active branches.

---

### Sprint 4: Testing, Integration & Release (Weeks 7-8)

**Sprint Goal**: Achieve comprehensive test coverage across all layers (API, service, component, E2E), update OpenAPI documentation, regenerate the TypeScript client, and validate performance and security requirements.

| Task ID | Description | Effort | SP | Dependencies | Assignee Role |
|---------|-------------|--------|:--:|-------------|---------------|
| KIO-016 | Backend API tests -- Kiosk Device Endpoints: `KioskDeviceEndpointTest` (PIN/QR auth, clock in/out, break, lockout, debounce, concurrent requests) + `KioskSessionServiceTest` (all state transitions) | 12h | 6 | KIO-007a/b, KIO-010 | Backend |
| KIO-019 | OpenAPI specification update: add `@operationId` annotations to all new controllers, run Scramble/generation tool, regenerate TypeScript client, verify types | 4h | 2 | KIO-003, KIO-005, KIO-007a/b, KIO-011 | Backend |
| KIO-017 | Frontend component tests (Vitest): `KioskPinPad.test.ts`, `KioskMemberStatus.test.ts`, `KioskIdleScreen.test.ts`, `AttendanceTable.test.ts`, `KioskTable.test.ts` | 8h | 4 | KIO-008a/b, KIO-012, KIO-013 | Frontend |
| KIO-018 | E2E Playwright tests: `kiosk-clock-in-out.spec.ts`, `kiosk-management.spec.ts`, `attendance-dashboard.spec.ts` | 8h | 4 | KIO-008a/b, KIO-012, KIO-013, KIO-014 | QA / Frontend |

**Sprint 4 Totals**: 32h effort, 16 SP (Backend: 16h / Frontend: 16h)

**Note on Parallel Execution**:
- **Week 7**: Backend works on KIO-016 (device endpoint tests) while frontend works on KIO-017 (component tests)
- **Week 8**: Backend works on KIO-019 (OpenAPI) while QA/frontend works on KIO-018 (E2E tests); both tracks converge for integration testing and bug fixes

**Acceptance Criteria**:
- All PIN authentication flows tested (valid, invalid, lockout, reset)
- All QR authentication flows tested (valid, expired, wrong-org)
- All clock state transitions tested (clock in, out, break start/end, out-during-break)
- Duplicate/concurrent request handling tested
- Service layer unit tested for all `KioskSessionService` state transitions
- >80% code coverage for new PHP code
- PIN pad input handling tested in Vitest
- Kiosk UI state transitions tested
- Attendance table rendering with various states tested
- All Vitest tests pass
- Admin kiosk creation flow tested end-to-end
- Member PIN setup tested end-to-end
- Full clock-in/out flow tested end-to-end on kiosk page
- Break flow tested end-to-end
- Attendance dashboard data visibility tested
- Permission enforcement tested (employee cannot access kiosk management)
- All Playwright tests pass
- OpenAPI spec includes all new endpoints with correct request/response schemas
- TypeScript client regenerated without errors; types match API contracts
- `composer fix && composer analyse` passes
- `npm run lint:fix && npm run format` passes
- Performance benchmark: kiosk API p95 < 300ms
- Security review: token handling, PIN storage, rate limiting verified

**Deliverables**:

| Category | Files |
|----------|-------|
| Tests (Backend) | `tests/Unit/Endpoint/Api/V1/KioskDeviceEndpointTest.php` |
| | `tests/Unit/Service/KioskSessionServiceTest.php` |
| Tests (Frontend) | `resources/js/Pages/Kiosk/__tests__/KioskPinPad.test.ts` |
| | `resources/js/Pages/Kiosk/__tests__/KioskMemberStatus.test.ts` |
| | `resources/js/Pages/Kiosk/__tests__/KioskIdleScreen.test.ts` |
| | `resources/js/Components/Common/Attendance/__tests__/AttendanceTable.test.ts` |
| | `resources/js/Components/Common/Kiosk/__tests__/KioskTable.test.ts` |
| Tests (E2E) | `e2e/kiosk-clock-in-out.spec.ts` |
| | `e2e/kiosk-management.spec.ts` |
| | `e2e/attendance-dashboard.spec.ts` |
| Docs | Updated OpenAPI specification |
| Generated | `resources/js/packages/api/src/openapi.json.client.ts` (regenerated) |

**Risk Factors**:
- **E2E test flakiness**: Kiosk page uses direct API calls (not Inertia), which may require different Playwright setup. Mitigation: Set up proper API mocking or test database seeding; use retry logic for flaky assertions.
- **Camera testing in E2E**: QR scanner cannot be fully tested in headless Playwright. Mitigation: Mock camera access in E2E tests; manual testing on physical tablets covers camera functionality.
- **Bug fix buffer**: Sprint 4 intentionally has lower story points (16 SP) to allow time for bug fixes discovered during testing.

---

## 5. Testing Strategy Per Sprint

### 5.1 Testing Timeline

| Sprint | Test Types | Scope | Tools |
|--------|-----------|-------|-------|
| Sprint 1 | Smoke tests (manual) | Verify migrations run, CRUD API works via Postman/curl, kiosk SPA skeleton renders | Manual + curl |
| Sprint 2 | Integration testing (manual) | Full kiosk flow: create kiosk -> set PIN -> open kiosk URL -> enter PIN -> clock in -> clock out; test on tablet emulator | Manual + Chrome DevTools device mode |
| Sprint 3 | Unit tests (automated) + Integration | KIO-015 API endpoint tests for management + PIN; manual testing of attendance dashboard and break flows | PHPUnit + Manual |
| Sprint 4 | Full test suite (automated) | KIO-016 device API tests, KIO-017 Vitest component tests, KIO-018 E2E Playwright tests, KIO-019 OpenAPI validation | PHPUnit + Vitest + Playwright |

### 5.2 Test Coverage Targets

| Layer | Target | Sprint Achieved |
|-------|--------|----------------|
| Database migrations (up/down) | 100% | Sprint 1 |
| Kiosk Management API endpoints | 100% | Sprint 3 (KIO-015) |
| Member PIN API endpoints | 100% | Sprint 3 (KIO-015) |
| Kiosk Device API endpoints | 100% | Sprint 4 (KIO-016) |
| KioskSessionService unit tests | 90%+ | Sprint 4 (KIO-016) |
| Kiosk Vue components (Vitest) | 80%+ | Sprint 4 (KIO-017) |
| Critical user flows (E2E) | 5 flows | Sprint 4 (KIO-018) |
| Performance benchmarks | p95 < 300ms | Sprint 4 |

### 5.3 Integration Testing Checkpoints

| Checkpoint | Sprint | Description |
|------------|--------|-------------|
| **API Smoke Test** | End of Sprint 1 | All CRUD endpoints respond correctly via curl/Postman |
| **Kiosk Auth Integration** | Mid-Sprint 2 | Kiosk token authenticates, PIN auth returns member data |
| **Full Clock Flow** | End of Sprint 2 | Complete clock-in/out cycle works through kiosk UI |
| **Break + Attendance** | End of Sprint 3 | Break tracking produces correct time entries; attendance dashboard shows accurate data |
| **Regression Suite** | End of Sprint 4 | All automated tests pass; no regression in existing features |

### 5.4 Manual Testing Requirements

| Area | Device | Sprint |
|------|--------|--------|
| Kiosk PIN pad touch targets | iPad (Safari), Android tablet (Chrome) | Sprint 2 |
| Kiosk landscape/portrait | iPad, Android tablet | Sprint 2 |
| QR camera scanning | Physical tablet with camera | Sprint 2 |
| Dark mode | System dark mode enabled | Sprint 3 |
| Offline detection | Network disconnect simulation | Sprint 3 |
| Performance under load | 100 concurrent kiosk sessions | Sprint 4 |

---

## 6. Definition of Done

### 6.1 Per-Task Definition of Done

Every task must satisfy ALL of the following before being marked complete:

- [ ] Code compiles without errors (`composer fix && composer analyse` for PHP; `npm run lint:fix && npm run format` for JS/TS)
- [ ] `declare(strict_types=1)` at top of every PHP file
- [ ] PHPDoc `@property` annotations complete on all models
- [ ] TypeScript types are accurate (no `any` types in new code)
- [ ] Code follows existing codebase patterns (controller structure, service injection, resource formatting)
- [ ] Database migrations include `down()` methods and run cleanly in both directions
- [ ] New API endpoints use `check-organization-blocked` middleware on write operations
- [ ] Permission checks are enforced via `$this->checkPermission()` or `KioskPermissions`
- [ ] Code is self-reviewed (no debug statements, no commented-out code, no TODOs without ticket references)
- [ ] PR created with description of changes and testing instructions

### 6.2 Per-Sprint Definition of Done

Each sprint must satisfy ALL of the following before moving to the next:

- [ ] All sprint tasks are completed per task DoD
- [ ] Sprint acceptance criteria (Section 4) are met
- [ ] No P0 bugs remaining in sprint scope
- [ ] `composer fix && composer analyse` passes on full codebase
- [ ] `npm run lint:fix && npm run format` passes on full codebase
- [ ] All existing tests continue to pass (no regression)
- [ ] Sprint demo completed with stakeholders
- [ ] Sprint retrospective completed and action items logged

### 6.3 Feature-Level Definition of Done

The feature is considered complete when ALL of the following are true:

- [ ] All 4 sprints completed per sprint DoD
- [ ] All code peer reviewed and merged to feature branch
- [ ] Unit test coverage >80% for new PHP code
- [ ] API endpoint test coverage 100% for all new endpoints
- [ ] Frontend component test coverage >80% for new Vue components
- [ ] E2E tests pass for all critical flows (5 scenarios minimum)
- [ ] Database migrations tested on clean database (up and down)
- [ ] OpenAPI spec updated and TypeScript client regenerated
- [ ] Security review completed:
  - Token handling (SHA-256 storage, never log plaintext)
  - PIN storage (bcrypt verification, SHA-256 uniqueness check)
  - Rate limiting (5 PIN attempts / 15 min, 60 API requests / min)
  - HTTPS enforcement for kiosk routes
  - `Cache-Control: no-store` on kiosk pages
- [ ] Performance benchmarks met:
  - Kiosk API p95 < 300ms
  - PIN validation < 200ms
  - QR validation < 150ms
  - Attendance dashboard load < 1s for 500 members
- [ ] Kiosk page tested on physical tablets (iPad + Android) in landscape and portrait
- [ ] Audit logging verified for all kiosk actions (via `CustomAuditable`)
- [ ] Stale session cleanup command tested and scheduled
- [ ] Feature branch ready for merge to `main`
- [ ] Release notes drafted

---

## 7. Risk Register

### 7.1 Technical Risks

| ID | Risk | Probability | Impact | Mitigation | Owner | Sprint |
|----|------|:-----------:|:------:|------------|-------|:------:|
| R-01 | Custom auth guard (`KioskGuard`) conflicts with existing Passport/Jetstream guards | Low | High | Kiosk routes are completely separate (`/api/v1/kiosk/*`); guard uses `kiosks` provider, not `users`; write integration test verifying all three guards work independently | Backend | 2 |
| R-02 | Timer state desynchronization between kiosk sessions and manual time entry edits | Medium | High | MVP approach: kiosk-created time entries are treated as single source of truth; document that manual editing of kiosk entries may cause inconsistencies; future: add read-only flag for kiosk entries | Backend | 2-3 |
| R-03 | PIN brute-force despite rate limiting (only 10,000 combinations for 4-digit PIN) | Low | High | 5-attempt lockout per 15 min; all attempts logged in `kiosk_pin_attempts`; audit trail for forensics; QR alternative for higher security; future: configurable PIN length (6-digit option) | Backend | 2 |
| R-04 | Kiosk token leaked via browser history, bookmarks, or URL sharing | Medium | Medium | HTTPS required; `Cache-Control: no-store` and `X-Robots-Tag: noindex` headers; configurable token expiry (default 30 days); admin can regenerate/revoke at any time | Backend | 2 |
| R-05 | Camera API incompatibility on certain tablet browsers for QR scanning | Medium | Medium | QR is P1 (not P0); PIN-only mode is always available as fallback; test `html5-qrcode` package on major tablet browsers (Chrome, Safari) early in Sprint 2 | Frontend | 2 |
| R-06 | Standalone Vue SPA (non-Inertia) increases frontend architecture complexity | Medium | Low | Keep kiosk SPA minimal; use same tech stack (Vue 3, Pinia, VueQuery); no shared components with main app; document the dual-SPA architecture | Frontend | 2 |
| R-07 | Break tracking edge cases (midnight-spanning breaks, time entry splitting) create data inconsistencies | Medium | Medium | All operations within database transactions; comprehensive unit tests for edge cases; stale session cleanup as safety net | Backend | 3 |
| R-08 | Attendance dashboard performance with 500+ members | Low | Medium | Partial index on `kiosk_sessions(organization_id, status)` designed in architecture; eager loading to prevent N+1; verify with EXPLAIN ANALYZE | Backend | 3 |

### 7.2 Dependency Risks

| ID | Risk | Probability | Impact | Mitigation |
|----|------|:-----------:|:------:|------------|
| D-01 | FOUND-007 (modular permissions) not ready before Sprint 1 | Low | Medium | FOUND-007 is only 4 hours; schedule as Day 1 pre-work; fallback: register permissions directly in JetstreamServiceProvider and refactor later |
| D-02 | Frontend blocked waiting for backend API endpoints (Sprint 2) | Medium | Medium | API contracts are fully specified in PRD; frontend can develop with mocked responses; integrate when backend endpoints are ready |
| D-03 | NPM package (`html5-qrcode`, `qrcode`, `firebase/php-jwt`) compatibility issues | Low | Low | Research and evaluate packages during Sprint 1 scaffolding; have fallback packages identified |
| D-04 | Merge conflicts with other feature branches modifying `routes/api.php`, `AppLayout.vue`, or `Member.php` | Medium | Low | Coordinate with other active branches; use minimal, focused changes; resolve conflicts early |

### 7.3 Capacity Risks

| ID | Risk | Probability | Impact | Mitigation |
|----|------|:-----------:|:------:|------------|
| C-01 | Sprint 2 overloaded (30 SP, highest in plan) | Medium | Medium | KIO-013 (Kiosk Management UI) can slip to Sprint 3 Week 1 without blocking critical path; split tasks per AMD-09/AMD-10 enable finer-grained progress tracking |
| C-02 | Frontend developer underutilized in Sprint 1 (only 5h of work) | Low | Low | AMD-11 added KIO-022 and KIO-023 to Sprint 1; frontend dev can assist with factory creation, API contract review, and package research |
| C-03 | QA part-time availability insufficient for Sprint 4 testing | Medium | Medium | Backend and frontend developers write their own tests (KIO-016, KIO-017); QA focuses on E2E tests (KIO-018) and exploratory testing; extend Sprint 4 by 2-3 days if needed |
| C-04 | Bug fixes from testing consume Sprint 4 capacity | Medium | Medium | Sprint 4 intentionally has lower SP (16 SP vs 30 SP in Sprint 2); 40% buffer for bug fixes and polish |

---

## 8. Milestone Timeline

### 8.1 Visual Timeline

```
Week  1    2    3    4    5    6    7    8
      |----|----|----|----|----|----|----|----|
      [  Sprint 1  ][  Sprint 2  ][  Sprint 3  ][  Sprint 4  ]
      |             |             |             |             |
      M1            M2            M3            M4           M5

Pre-work (FOUND-007) completed before Week 1
```

### 8.2 Milestones

| ID | Milestone | Target Date | Sprint | Description |
|----|-----------|-------------|:------:|-------------|
| M0 | **Foundation Ready** | Before Week 1 | 0 | FOUND-007 complete; modular permissions infrastructure in place |
| M1 | **Data Layer Complete** | End of Week 2 | 1 | All database tables, models, enums, admin CRUD API, PIN API functional; kiosk SPA skeleton renders |
| M2 | **Kiosk MVP Functional** | End of Week 4 | 2 | Complete kiosk flow: create kiosk -> set PIN -> open kiosk URL -> enter PIN -> clock in -> clock out; admin can manage kiosks from UI |
| M3 | **Feature Complete** | End of Week 6 | 3 | Break tracking, attendance dashboard, PIN management UI, stale session cleanup all working; management API tests passing |
| M4 | **Release Candidate** | End of Week 7 | 4 | All automated tests passing; OpenAPI spec updated; TypeScript client regenerated |
| M5 | **Release Ready** | End of Week 8 | 4 | Security review complete; performance benchmarks met; E2E tests passing; feature branch ready for merge |

### 8.3 Go/No-Go Decision Points

| Decision Point | Timing | Criteria | Escalation |
|----------------|--------|----------|------------|
| **Go/No-Go: Sprint 2 Start** | End of Sprint 1 | All migrations run cleanly; CRUD API returns correct responses; at least one kiosk can be created and retrieved via curl | If not met: extend Sprint 1 by 2-3 days; investigate migration or model issues |
| **Go/No-Go: Sprint 3 Start** | End of Sprint 2 | Full clock-in/out cycle works through kiosk UI on a browser; kiosk auth guard validates tokens correctly; no data corruption in time entries | If not met: critical -- investigate auth guard or session service issues; consider simplifying kiosk UI to PIN-only (defer QR) |
| **Go/No-Go: Sprint 4 Start** | End of Sprint 3 | Break tracking produces correct time entries (verified manually); attendance dashboard shows accurate data; management API tests pass | If not met: extend Sprint 3 by 3-5 days; reduce Sprint 4 testing scope to P0 scenarios only |
| **Go/No-Go: Release** | End of Sprint 4 | All automated tests pass; security review findings addressed; performance benchmarks met; no P0 bugs | If not met: create a Sprint 4.5 (1 week) for bug fixes; defer P1 test scenarios to post-release |

### 8.4 Sprint Velocity Tracking

| Sprint | Planned SP | Capacity (hrs) | Load Factor |
|:------:|:----------:|:--------------:|:-----------:|
| 0 (Pre-work) | 2 | 4 | -- |
| 1 | 17 | 33 | 69% (assumes 2 devs x 24h productive/sprint = 48h capacity) |
| 2 | 30 | 60 | 125% (tight -- see C-01 mitigation) |
| 3 | 20 | 42 | 88% |
| 4 | 16 | 32 | 67% (intentional buffer for bug fixes) |

**Note**: Sprint 2 exceeds 100% load factor because the backend and frontend tracks are fully parallelized. Effective capacity with 2 developers is ~48h per sprint; the 60h assumes overlap where frontend begins with mocked APIs before backend is complete. If this proves too aggressive, KIO-013 slips to Sprint 3.

---

## Appendix A: Complete Task Reference

| Task ID | Description | Sprint | Effort | SP | Type |
|---------|-------------|:------:|--------|:--:|------|
| FOUND-007 | Modular permissions infrastructure | 0 | 4h | 2 | Backend |
| KIO-001 | Database migrations | 1 | 4h | 2 | Backend |
| KIO-002 | Eloquent models + enums | 1 | 6h | 3 | Backend |
| KIO-003 | Kiosk management CRUD API | 1 | 10h | 5 | Backend |
| KIO-004 | Kiosk permissions registration | 1 | 2h | 1 | Backend |
| KIO-005 | Member PIN management API | 1 | 6h | 3 | Backend |
| KIO-006 | Custom kiosk authentication guard | 2 | 8h | 4 | Backend |
| KIO-007a | Kiosk device API -- PIN/QR auth | 2 | 6h | 3 | Backend |
| KIO-007b | Kiosk device API -- clock operations | 2 | 6h | 3 | Backend |
| KIO-008a | Kiosk Vue page -- idle + PIN entry | 2 | 8h | 4 | Frontend |
| KIO-008b | Kiosk Vue page -- clock status + actions | 2 | 8h | 4 | Frontend |
| KIO-009 | QR code scanner component | 2 | 8h | 4 | Frontend |
| KIO-010 | Break tracking logic refinement | 3 | 6h | 3 | Backend |
| KIO-011 | Attendance dashboard backend API | 3 | 6h | 3 | Backend |
| KIO-012 | Attendance dashboard Vue page | 3 | 12h | 5 | Frontend |
| KIO-013 | Kiosk management frontend (admin) | 2 | 10h | 5 | Frontend |
| KIO-014 | Member PIN management frontend | 3 | 6h | 3 | Frontend |
| KIO-015 | Backend API tests -- kiosk management | 3 | 8h | 4 | Testing |
| KIO-016 | Backend API tests -- device endpoints | 4 | 12h | 6 | Testing |
| KIO-017 | Frontend component tests (Vitest) | 4 | 8h | 4 | Testing |
| KIO-018 | E2E Playwright tests | 4 | 8h | 4 | Testing |
| KIO-019 | OpenAPI spec update + TS client regen | 4 | 4h | 2 | Backend |
| KIO-020 | Stale session cleanup command | 3 | 4h | 2 | Backend |
| KIO-021 | Badge QR token support (AMD-05) | 2 | 6h | 3 | Backend |
| KIO-022 | TypeScript type definitions (AMD-11) | 1 | 2h | 1 | Frontend |
| KIO-023 | Kiosk SPA layout scaffold (AMD-11) | 1 | 3h | 2 | Frontend |

**Grand Total: 26 tasks, ~200 hours, ~100 SP**

## Appendix B: Critical Path Analysis

The critical path determines the minimum time to deliver the feature, assuming infinite parallelization of non-dependent tasks.

```
FOUND-007 (4h) --> KIO-001 (4h) --> KIO-002 (6h) --> KIO-006 (8h) --> KIO-007a (6h) --> KIO-007b (6h) --> KIO-008a (8h) --> KIO-008b (8h) --> KIO-018 (8h)

Critical path duration: 58 hours
```

Any delay on any task in this chain directly impacts the overall delivery date. All other tasks have float (slack) and can absorb minor delays without impacting the project end date.

### Secondary Critical Paths

1. **Attendance path**: KIO-001 --> KIO-002 --> KIO-006 --> KIO-007b --> KIO-011 --> KIO-012 --> KIO-018 (50h)
2. **Admin UI path**: KIO-001 --> KIO-002 --> KIO-003 --> KIO-013 --> KIO-017 (34h)
3. **PIN management path**: KIO-001 --> KIO-005 --> KIO-014 --> KIO-018 (22h)

---

*Last updated: 2026-02-06*
