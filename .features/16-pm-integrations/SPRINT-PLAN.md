# Sprint Plan: PM Tool Integrations (Jira, Asana, Trello)

**Date**: 2026-02-09
**Feature**: 16 - PM Tool Integrations
**Branch**: `feature/pm-integrations` (from `main`)
**Task Prefix**: `PMI-`
**PRD Reference**: `.features/16-pm-integrations/PRD.md`
**Architecture Reference**: `.features/16-pm-integrations/ARCHITECTURE.md`

---

## 1. Executive Summary

The PM Tool Integrations feature connects Solidtime to Jira Cloud, Asana, and Trello, enabling organizations to synchronize project/task structures, display external references in the Solidtime UI, and optionally push time entries back to the PM tool. The feature introduces 4 new database tables, an adapter-based integration framework, OAuth consumer capability, webhook handling, background sync jobs, and a new settings page with Vue components.

**Total effort estimate**: 288 hours

**Total story points**: ~192 SP

**Number of sprints**: **5 sprints** (10 weeks)

**Team size assumptions**:
- 1 Backend Developer (senior, ~30 productive hours/sprint)
- 1 Frontend Developer (senior, ~30 productive hours/sprint)
- 1 QA Engineer (part-time, ~15 productive hours/sprint for dedicated testing sprints)
- Concurrent work where dependency graph allows

**Key constraints**:
- 4 new database tables and associated migrations
- 3 new permissions (`integrations:view`, `integrations:manage`, `integrations:sync`)
- OAuth consumer capability is new to the codebase (requires `league/oauth2-client` package)
- Webhook endpoints require signature validation and sit outside standard auth middleware
- Background jobs require a dedicated queue and scheduled commands
- External API dependencies (Jira, Asana, Trello) require sandbox/test accounts for development
- Feature 00 (Weekly Timesheet Grid) should be merged before frontend work begins (PMI-030 touches task selectors)

---

## 2. Sprint Overview Table

| Sprint | Name | Duration | Story Points | Key Deliverables |
|--------|------|----------|:------------:|------------------|
| **1** | Backend Foundation | 2 weeks | 33 SP | Database migrations, Eloquent models, adapter interface, base adapter, permissions, controller stubs, request validation, IntegrationService (partial) |
| **2** | Provider Adapters & Controller Wiring | 2 weeks | 45 SP | JiraAdapter, AsanaAdapter, TrelloAdapter, PmWebhookController, route registration, controller-to-service wiring |
| **3** | Jobs, OpenAPI & Frontend Foundation | 2 weeks | 35 SP | SyncIntegrationJob, ExportTimeEntriesJob, RefreshIntegrationTokenJob, scheduled commands, OpenAPI spec, TS types, Pinia store, Integrations.vue page, OAuthCallbackHandler, env docs |
| **4** | Frontend Components & Backend Tests | 2 weeks | 35 SP | IntegrationCard, IntegrationDetail, ProjectSyncPanel, SyncHistoryLog, external reference badges, navigation, web route, JSDoc, IntegrationService unit tests, JiraAdapter unit tests |
| **5** | Testing & Polish | 2 weeks | 33 SP | Endpoint tests (3 controllers), AsanaAdapter tests, TrelloAdapter tests, job tests, frontend component tests, E2E Playwright tests |

**Total**: ~181 SP across 10 weeks (remaining ~11 SP is buffer/documentation)

---

## 3. Dependency Map

### 3.1 Task Dependencies

```
Wave 1 (No dependencies -- Sprint 1 start):
    PMI-001 (Database migrations, 8h)
    PMI-003 (Adapter interface + base class, 4h)
    PMI-016 (Integration permissions, 2h)

Wave 2 (after Wave 1):
    PMI-002 (Eloquent models, 8h)          <- PMI-001
    PMI-004 (JiraAdapter, 16h)             <- PMI-003
    PMI-005 (AsanaAdapter, 16h)            <- PMI-003
    PMI-006 (TrelloAdapter, 12h)           <- PMI-003

Wave 3 (after Wave 2):
    PMI-007 (IntegrationService, 16h)      <- PMI-002, PMI-003
    PMI-008 (PmIntegrationController, 4h)    <- PMI-002
    PMI-009 (IntegrationProjectCtrl, 4h)   <- PMI-002
    PMI-010 (PmWebhookController, 8h)        <- PMI-004, PMI-005

Wave 4 (after Wave 3):
    PMI-011 (Request validation, 4h)       <- PMI-008, PMI-009
    PMI-013 (Wire IntegrationCtrl, 8h)     <- PMI-007, PMI-008, PMI-011
    PMI-014 (Wire IntProjectCtrl, 4h)      <- PMI-007, PMI-009, PMI-011
    PMI-015 (Wire WebhookCtrl, 4h)         <- PMI-007, PMI-010
    PMI-017 (SyncIntegrationJob, 8h)       <- PMI-007, PMI-004, PMI-005, PMI-006
    PMI-018 (ExportTimeEntriesJob, 8h)     <- PMI-007, PMI-004, PMI-005, PMI-006
    PMI-019 (RefreshTokenJob, 4h)          <- PMI-007

Wave 5 (after Wave 4):
    PMI-012 (API routes, 2h)               <- PMI-008, PMI-009, PMI-010
    PMI-020 (Schedule jobs, 2h)            <- PMI-017, PMI-018, PMI-019
    PMI-021 (OpenAPI + TS client, 6h)      <- PMI-012, PMI-013, PMI-014
    PMI-036 (IntegrationService tests, 8h) <- PMI-007
    PMI-037 (JiraAdapter tests, 6h)        <- PMI-004
    PMI-038 (AsanaAdapter tests, 6h)       <- PMI-005
    PMI-039 (TrelloAdapter tests, 4h)      <- PMI-006
    PMI-040 (Job tests, 6h)               <- PMI-017, PMI-018, PMI-019
    PMI-044 (Env var docs, 2h)            <- PMI-004, PMI-005, PMI-006

Wave 6 (after Wave 5):
    PMI-022 (TS types, 4h)                <- PMI-021
    PMI-033 (IntegrationCtrl tests, 8h)   <- PMI-013, PMI-012
    PMI-034 (IntProjectCtrl tests, 4h)    <- PMI-014, PMI-012
    PMI-035 (WebhookCtrl tests, 6h)       <- PMI-015, PMI-012

Wave 7 (after Wave 6):
    PMI-023 (Pinia store, 8h)             <- PMI-022
    PMI-030 (External ref badges, 8h)     <- PMI-022

Wave 8 (after Wave 7):
    PMI-024 (Integrations.vue page, 8h)   <- PMI-023
    PMI-029 (OAuthCallbackHandler, 4h)    <- PMI-023
    PMI-043 (JSDoc comments, 3h)          <- PMI-023, PMI-022

Wave 9 (after Wave 8):
    PMI-025 (IntegrationCard, 6h)         <- PMI-024
    PMI-026 (IntegrationDetail, 8h)       <- PMI-024, PMI-023
    PMI-027 (ProjectSyncPanel, 8h)        <- PMI-024, PMI-023
    PMI-031 (Nav link, 2h)                <- PMI-024
    PMI-032 (Web route, 1h)               <- PMI-024

Wave 10 (after Wave 9):
    PMI-028 (SyncHistoryLog, 4h)          <- PMI-026
    PMI-041 (Frontend tests, 8h)          <- PMI-025, PMI-026, PMI-027, PMI-028
    PMI-042 (E2E tests, 8h)              <- PMI-031, PMI-032, PMI-025, PMI-026, PMI-027
```

### 3.2 Critical Path

```
PMI-001 (8h) -> PMI-002 (8h) -> PMI-007 (16h) -> PMI-013 (8h) -> PMI-021 (6h)
    -> PMI-022 (4h) -> PMI-023 (8h) -> PMI-024 (8h) -> PMI-026 (8h)
    -> PMI-028 (4h) -> PMI-042 (8h)
```

**Critical path duration**: ~86 hours of sequential work

### 3.3 Parallelism Opportunities

| Wave | Backend Stream A (Adapters) | Backend Stream B (Core) | Frontend | Testing | Parallel? |
|------|---------------------------|------------------------|----------|---------|:---------:|
| 1 | PMI-003 | PMI-001, PMI-016 | -- | -- | Yes |
| 2 | PMI-004, PMI-005, PMI-006 | PMI-002 | -- | -- | Yes |
| 3 | PMI-010 | PMI-007, PMI-008, PMI-009 | -- | -- | Yes |
| 4 | PMI-015, PMI-017, PMI-018, PMI-019 | PMI-011, PMI-013, PMI-014 | -- | -- | Yes |
| 5 | PMI-012, PMI-020, PMI-044 | PMI-021 | -- | PMI-036, PMI-037 | Yes |
| 6 | -- | -- | PMI-022 | PMI-033, PMI-034, PMI-035 | Yes |
| 7 | -- | -- | PMI-023, PMI-030 | PMI-038, PMI-039, PMI-040 | Yes |
| 8 | -- | -- | PMI-024, PMI-029, PMI-043 | -- | -- |
| 9 | -- | -- | PMI-025, PMI-026, PMI-027, PMI-031, PMI-032 | -- | -- |
| 10 | -- | -- | PMI-028 | PMI-041, PMI-042 | Yes |

Backend Streams A and B can run in parallel during Sprints 1-2. Frontend and testing streams can run in parallel with each other during Sprints 4-5.

---

## 4. Sprint Details

### Sprint 1 (Weeks 1-2): Backend Foundation

**Goal**: Database schema created, Eloquent models functional, adapter interface defined, permissions registered, controller stubs in place, request validation defined, IntegrationService started.

| Task ID | Description | Assignee | SP | Dependencies | Day |
|---------|-------------|----------|:--:|--------------|:---:|
| PMI-001 | Create database migrations for 4 integration tables | Backend | 5 | None | 1-2 |
| PMI-003 | Create IntegrationAdapterInterface and BaseIntegrationAdapter | Backend | 3 | None | 1 |
| PMI-016 | Register integration permissions (IntegrationPermissions) | Backend | 1 | None | 1 |
| PMI-002 | Create Eloquent models (4 models + 3 enums + factories) | Backend | 5 | PMI-001 | 3-4 |
| PMI-008 | Create PmIntegrationController with endpoint stubs | Backend | 3 | PMI-002 | 5 |
| PMI-009 | Create IntegrationProjectController | Backend | 3 | PMI-002 | 5 |
| PMI-011 | Create request validation classes (5 classes) | Backend | 3 | PMI-008, PMI-009 | 6 |
| PMI-007 | Create IntegrationService (connection lifecycle portion) | Backend | 10 | PMI-002, PMI-003 | 6-10 |

**Sprint 1 Total**: 33 SP

**Deliverables**:
- [ ] 4 database migrations creating `integration_connections`, `integration_projects`, `external_task_mappings`, `integration_sync_logs`
- [ ] 4 Eloquent models with relationships, casts, encryption accessors
- [ ] 3 enums (`PmProvider`, `IntegrationStatus`, `SyncDirection`)
- [ ] 4 model factories for testing
- [ ] `IntegrationAdapterInterface` with 11 method signatures
- [ ] `BaseIntegrationAdapter` with shared HTTP client and utility methods
- [ ] `IntegrationPermissions` registered (3 new permissions across 4 roles)
- [ ] `PmIntegrationController` with 9 method stubs
- [ ] `IntegrationProjectController` with 2 method stubs
- [ ] 5 request validation classes
- [ ] `IntegrationService` with connection lifecycle methods (`initiateConnection`, `completeOAuthConnection`, `disconnect`, `updateSettings`, `getAdapter`)

**QA Gate**:
```bash
./vendor/bin/sail exec laravel.test php artisan migrate
./vendor/bin/sail exec laravel.test composer fix
./vendor/bin/sail exec laravel.test composer analyse
```

---

### Sprint 2 (Weeks 3-4): Provider Adapters & Controller Wiring

**Goal**: All 3 provider adapters implemented, PmWebhookController created, API routes registered, controllers wired to service.

| Task ID | Description | Assignee | SP | Dependencies | Day |
|---------|-------------|----------|:--:|--------------|:---:|
| PMI-004 | Implement JiraAdapter (OAuth 2.0, projects, tasks, worklogs, webhooks) | Backend | 10 | PMI-003 | 1-4 |
| PMI-005 | Implement AsanaAdapter (OAuth 2.0, projects, tasks, comments, webhooks) | Backend | 10 | PMI-003 | 1-4 |
| PMI-006 | Implement TrelloAdapter (API key, boards, cards, comments) | Backend | 8 | PMI-003 | 5-7 |
| PMI-010 | Create PmWebhookController for Jira and Asana | Backend | 5 | PMI-004, PMI-005 | 5-6 |
| PMI-012 | Register API routes for all integration endpoints | Backend | 1 | PMI-008, PMI-009, PMI-010 | 7 |
| PMI-013 | Wire PmIntegrationController to IntegrationService and adapters | Backend | 5 | PMI-007, PMI-008, PMI-011 | 7-8 |
| PMI-014 | Wire IntegrationProjectController to IntegrationService | Backend | 3 | PMI-007, PMI-009, PMI-011 | 8 |
| PMI-015 | Wire PmWebhookController to IntegrationService | Backend | 3 | PMI-007, PMI-010 | 9 |

**Sprint 2 Total**: 45 SP

**Deliverables**:
- [ ] `JiraAdapter` with OAuth token exchange, project listing, issue listing, worklog creation, webhook registration and validation
- [ ] `AsanaAdapter` with OAuth token exchange, project listing, task listing, comment posting, webhook subscription and validation
- [ ] `TrelloAdapter` with API key validation, board listing, card listing, comment posting
- [ ] `PmWebhookController` with Jira and Asana handlers (signature validation, event parsing)
- [ ] All 13 API routes registered in `routes/api.php` (11 authenticated + 2 webhook)
- [ ] `PmIntegrationController` fully wired: index, show, connect, callback, update, destroy, syncNow, externalProjects, syncLogs
- [ ] `IntegrationProjectController` fully wired: index, toggle
- [ ] `PmWebhookController` wired to `IntegrationService::processWebhookEvent()`
- [ ] Full connection flow testable manually: Connect Jira/Asana via OAuth, Trello via API key

**QA Gate**:
```bash
./vendor/bin/sail exec laravel.test composer fix
./vendor/bin/sail exec laravel.test composer analyse
# Manual: Test OAuth flow with Jira/Asana sandbox accounts
# Manual: Test Trello API key validation
# Manual: Verify webhook endpoints return 200 for valid signatures
```

---

### Sprint 3 (Weeks 5-6): Jobs, OpenAPI & Frontend Foundation

**Goal**: Background sync/export/refresh jobs functional, scheduled commands registered, OpenAPI spec updated, TypeScript client regenerated, Pinia store created, Integrations page scaffolded.

| Task ID | Description | Assignee | SP | Dependencies | Day |
|---------|-------------|----------|:--:|--------------|:---:|
| PMI-017 | Create SyncIntegrationJob (background sync) | Backend | 5 | PMI-007, PMI-004, PMI-005, PMI-006 | 1-2 |
| PMI-018 | Create ExportTimeEntriesJob (time entry write-back) | Backend | 5 | PMI-007, PMI-004, PMI-005, PMI-006 | 2-3 |
| PMI-019 | Create RefreshIntegrationTokenJob (scheduled token refresh) | Backend | 3 | PMI-007 | 3-4 |
| PMI-020 | Register scheduled jobs in Console Kernel + Artisan commands | Backend | 1 | PMI-017, PMI-018, PMI-019 | 4 |
| PMI-021 | Update OpenAPI spec and regenerate TypeScript client | Backend | 4 | PMI-012, PMI-013, PMI-014 | 4-5 |
| PMI-022 | Create TypeScript types for integration entities | Frontend | 3 | PMI-021 | 6 |
| PMI-023 | Create useIntegrationStore Pinia store | Frontend | 5 | PMI-022 | 6-8 |
| PMI-024 | Create Integrations.vue settings page (scaffold) | Frontend | 5 | PMI-023 | 8-9 |
| PMI-029 | Create OAuthCallbackHandler.vue (redirect handling) | Frontend | 3 | PMI-023 | 9-10 |
| PMI-044 | Create environment variable documentation | Backend | 1 | PMI-004, PMI-005, PMI-006 | 5 |

**Sprint 3 Total**: 35 SP

**Deliverables**:
- [ ] `SyncIntegrationJob` handles full project + task sync for a connection
- [ ] `ExportTimeEntriesJob` pushes time entries to external PM tools
- [ ] `RefreshIntegrationTokenJob` proactively refreshes expiring tokens
- [ ] 2 Artisan commands (`integration:sync`, `integration:refresh-tokens`) registered in Kernel
- [ ] OpenAPI spec updated with all 13 endpoints
- [ ] TypeScript client regenerated from OpenAPI
- [ ] `integration.d.ts` with full TypeScript type definitions
- [ ] `useIntegrationStore` Pinia store with all actions
- [ ] `Integrations.vue` page with AppLayout and basic structure
- [ ] `OAuthCallbackHandler.vue` detecting redirect params and showing toasts
- [ ] Environment variable documentation for `JIRA_CLIENT_ID`, etc.

**QA Gate**:
```bash
./vendor/bin/sail exec laravel.test composer fix
./vendor/bin/sail exec laravel.test composer analyse
# Manual: Dispatch SyncIntegrationJob for a connected account, verify tasks synced
# Manual: Verify Integrations page loads at /organizations/{org}/settings/integrations
npm run lint:fix && npm run format
npm run build
```

---

### Sprint 4 (Weeks 7-8): Frontend Components & Backend Tests

**Goal**: All frontend components implemented, external reference badges in existing UI, navigation and routing complete, backend unit tests for service and Jira adapter.

| Task ID | Description | Assignee | SP | Dependencies | Day |
|---------|-------------|----------|:--:|--------------|:---:|
| PMI-025 | Create IntegrationCard.vue component | Frontend | 4 | PMI-024 | 1-2 |
| PMI-026 | Create IntegrationDetail.vue component (settings, history) | Frontend | 5 | PMI-024, PMI-023 | 2-4 |
| PMI-027 | Create ProjectSyncPanel.vue (project selection, toggle) | Frontend | 5 | PMI-024, PMI-023 | 3-5 |
| PMI-028 | Create SyncHistoryLog.vue component | Frontend | 3 | PMI-026 | 5-6 |
| PMI-030 | Add external reference badges to task selector and time entry UI | Frontend | 5 | PMI-022 | 1-3 |
| PMI-031 | Add Integrations link to Organization Settings navigation | Frontend | 1 | PMI-024 | 6 |
| PMI-032 | Add web route for Integrations page | Frontend | 1 | PMI-024 | 6 |
| PMI-043 | Add JSDoc comments to Pinia store and TypeScript types | Frontend | 2 | PMI-023, PMI-022 | 7 |
| PMI-036 | Create unit tests for IntegrationService | Backend QA | 5 | PMI-007 | 1-4 |
| PMI-037 | Create unit tests for JiraAdapter (mocked HTTP) | Backend QA | 4 | PMI-004 | 5-7 |

**Sprint 4 Total**: 35 SP

**Deliverables**:
- [ ] `IntegrationCard.vue` showing provider icon, name, status badge, connect/view buttons
- [ ] `IntegrationDetail.vue` with connection info, settings form, sync now, disconnect
- [ ] `ProjectSyncPanel.vue` with external project list, toggle switches, search/filter
- [ ] `SyncHistoryLog.vue` with paginated log entries, expandable error details
- [ ] `ExternalReferenceBadge.vue` displaying provider icon + reference text with link
- [ ] External reference badges in task selectors and time entry list views
- [ ] Integrations link in Organization Settings navigation
- [ ] Web route at `/organizations/{org}/settings/integrations`
- [ ] JSDoc documentation on all Pinia store methods and TypeScript types
- [ ] IntegrationService unit tests: connect, disconnect, syncProjects, syncTasks, exportTimeEntries, refreshToken, processWebhookEvent
- [ ] JiraAdapter unit tests: getAuthorizationUrl, exchangeCode, getProjects, getTasks, postTimeEntry, validateWebhookSignature

**QA Gate**:
```bash
npm run lint:fix && npm run format
npm run build
./vendor/bin/sail exec laravel.test php artisan test --filter=IntegrationServiceTest
./vendor/bin/sail exec laravel.test php artisan test --filter=JiraAdapterTest
```

**Manual Testing**:
- Navigate to Organization Settings > Integrations
- View 3 provider cards (Jira, Asana, Trello)
- Connect Trello (API key flow)
- View connected integration detail
- Toggle project sync on/off
- View sync history
- Disconnect integration
- Verify external reference badges appear on synced tasks in task selector

---

### Sprint 5 (Weeks 9-10): Testing & Polish

**Goal**: Comprehensive test coverage across all endpoints, adapters, jobs, frontend components, and E2E flows.

| Task ID | Description | Assignee | SP | Dependencies | Day |
|---------|-------------|----------|:--:|--------------|:---:|
| PMI-033 | Backend endpoint tests for PmIntegrationController (9 endpoints) | Backend QA | 5 | PMI-013, PMI-012 | 1-3 |
| PMI-034 | Backend endpoint tests for IntegrationProjectController | Backend QA | 3 | PMI-014, PMI-012 | 3-4 |
| PMI-035 | Backend endpoint tests for PmWebhookController | Backend QA | 4 | PMI-015, PMI-012 | 4-5 |
| PMI-038 | Create unit tests for AsanaAdapter (mocked HTTP) | Backend QA | 4 | PMI-005 | 5-6 |
| PMI-039 | Create unit tests for TrelloAdapter (mocked HTTP) | Backend QA | 3 | PMI-006 | 6-7 |
| PMI-040 | Create unit tests for sync, export, and refresh jobs | Backend QA | 4 | PMI-017, PMI-018, PMI-019 | 7-8 |
| PMI-041 | Create frontend component tests with Vitest | Frontend QA | 5 | PMI-025, PMI-026, PMI-027, PMI-028 | 1-4 |
| PMI-042 | Create E2E Playwright tests for integration management | QA | 5 | PMI-031, PMI-032, PMI-025, PMI-026, PMI-027 | 5-8 |

**Sprint 5 Total**: 33 SP

**Deliverables**:
- [ ] PmIntegrationController endpoint tests: list, show, connect (Jira OAuth, Asana OAuth, Trello API key), callback, update, destroy, syncNow, externalProjects, syncLogs
- [ ] IntegrationProjectController endpoint tests: index, toggle
- [ ] PmWebhookController endpoint tests: Jira valid/invalid signature, Asana handshake, Asana event valid/invalid
- [ ] AsanaAdapter unit tests: OAuth flow, getProjects, getTasks, postTimeEntry, validateWebhookSignature
- [ ] TrelloAdapter unit tests: validateCredentials, getBoards, getCards, postTimeComment
- [ ] Job tests: SyncIntegrationJob (active/disconnected/error handling), ExportTimeEntriesJob (batch processing, retry), RefreshIntegrationTokenJob (refresh success/failure)
- [ ] Frontend component tests: IntegrationCard (status badges), IntegrationDetail (settings form), ProjectSyncPanel (toggle), SyncHistoryLog (log rendering)
- [ ] E2E tests: Navigate to integrations, view cards, connect Trello, view detail, toggle project, view sync history, disconnect

**QA Gate**:
```bash
./vendor/bin/sail exec laravel.test composer fix
./vendor/bin/sail exec laravel.test composer analyse
./vendor/bin/sail exec laravel.test php artisan test
npm run lint:fix && npm run format
npx vitest run
npx playwright test
```

---

## 5. QA Gates Summary

| Sprint | Gate Command | Pass Criteria |
|--------|-------------|---------------|
| 1 | `php artisan migrate` + `composer fix` + `composer analyse` | Migrations run, 0 PHPStan errors |
| 2 | `composer fix` + `composer analyse` + manual OAuth test | All adapters functional, routes respond |
| 3 | `composer fix` + `composer analyse` + `npm run build` + manual sync test | Jobs dispatch and complete, frontend builds |
| 4 | `npm run lint:fix` + `npm run format` + `php artisan test --filter=Integration` | Frontend lints, backend tests pass |
| 5 | Full suite: PHP tests + Vitest + Playwright + `composer fix` + `npm run lint:fix` | All tests green |

---

## 6. Risk Register

| Risk | Sprint | Probability | Impact | Mitigation |
|------|--------|-------------|--------|------------|
| OAuth provider sandbox accounts not available | 2 | Medium | High | Set up Jira Cloud, Asana, and Trello test accounts in Sprint 1. Use HTTP mocking for unit tests regardless. |
| Provider API rate limiting during development | 2-3 | Medium | Medium | Use HTTP fake/mock in tests. Implement rate limit handling in adapters from the start. |
| OAuth callback URL complexity (API route receiving browser redirect) | 2 | Medium | Medium | Test callback flow manually in Sprint 2. Consider using a web route for the callback if API route causes issues. |
| Large task sync performance (1000+ tasks) | 3 | Medium | Medium | Implement pagination and chunked inserts from the start. Test with large datasets in Sprint 3. |
| Webhook signature validation differences between Jira and Asana | 2 | Low | Medium | Each adapter implements its own validation. Test with real webhook payloads from provider sandboxes. |
| Encryption pattern unfamiliar to team | 1 | Low | Low | Document encryption pattern in architecture doc. Test encrypted values in migration tests. |
| `league/oauth2-client` compatibility issues | 1 | Low | Medium | Evaluate package compatibility in Sprint 1 Day 1. Fall back to custom Guzzle implementation if needed. |
| Feature 00 not merged before frontend work | 3 | Medium | Medium | Frontend stream starts in Sprint 3. Feature 00 should be merged by end of Sprint 2. If not, rebasing may be needed for PMI-030. |
| E2E test environment requires running OAuth flow | 5 | Medium | Medium | E2E tests use Trello API key flow (no redirect needed). Mock OAuth redirect for E2E if needed. |
| Concurrent provider API changes | All | Low | High | Adapter abstraction isolates changes. Monitor provider changelogs. Automated regression tests catch breaking changes. |
| Memory issues in sync jobs with many projects | 3 | Medium | Medium | Process one project at a time. Use cursor/chunk queries. Monitor memory with 128 MB limit. |

---

## 7. Definition of Done (Feature Complete)

- [ ] All 44 tasks (PMI-001 through PMI-044) completed
- [ ] 4 database migrations run successfully (create + rollback)
- [ ] 4 Eloquent models with relationships, casts, encryption
- [ ] 3 provider adapters (Jira, Asana, Trello) implementing full interface
- [ ] `IntegrationService` orchestrating all connection lifecycle and sync operations
- [ ] 13 API endpoints functional with proper validation and permissions
- [ ] OAuth 2.0 flows working for Jira and Asana (with sandbox accounts)
- [ ] Trello API key connection flow working
- [ ] 3 background jobs (sync, export, refresh) dispatching and completing
- [ ] 2 webhook endpoints processing events from Jira and Asana
- [ ] Scheduled commands running sync and token refresh at configured intervals
- [ ] `composer fix && composer analyse` passes with 0 new errors
- [ ] `npm run lint:fix && npm run format` passes
- [ ] `npm run build` succeeds
- [ ] Backend endpoint tests pass (3 controllers)
- [ ] Backend service and adapter unit tests pass (with mocked HTTP)
- [ ] Backend job tests pass
- [ ] Frontend component tests pass (Vitest)
- [ ] E2E Playwright tests pass
- [ ] OpenAPI spec updated with all 13 endpoints
- [ ] TypeScript client regenerated and types match API
- [ ] Integration settings page accessible from Organization Settings navigation
- [ ] External reference badges visible in task selectors and time entry views
- [ ] 3 permissions registered (`integrations:view`, `integrations:manage`, `integrations:sync`)
- [ ] Encrypted token storage verified (no plaintext tokens in database)
- [ ] Webhook signature validation tested for both Jira and Asana
- [ ] Environment variable documentation created
- [ ] JSDoc comments on Pinia store and TypeScript types
- [ ] Feature ready for merge to `main`

---

## 8. Post-Sprint: Merge Strategy

After all 5 sprints complete and QA passes:

1. Ensure Feature 00 (Weekly Timesheet Grid) is already merged to `main`
2. Rebase `feature/pm-integrations` on latest `main`
3. Run full test suite (PHP + JS + E2E)
4. Run `composer fix && composer analyse`
5. Run `npm run lint:fix && npm run format`
6. Run `php artisan migrate` to verify migrations apply cleanly
7. Create PR targeting `main`
8. After merge: Future features (browser extension, additional providers) can begin from `main`
