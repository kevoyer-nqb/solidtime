# Feature 16: PM Tool Integrations (Jira, Asana, Trello)

## Branch
`feature/pm-integrations`

## Task Prefix
`PMI-` (PMI-001 through PMI-044)

## Migration Date Prefix
`2026_02_09` -- 4 new tables (integration_connections, integration_projects, external_task_mappings, integration_sync_logs)

## Execution Phase
Phase 3 -- requires core platform on `main` (Project, Task, TimeEntry models). Should be developed after Feature 00 (Weekly Timesheet Grid) is merged, as PMI-030 touches task selector UI.

## Sprint Summary
| Sprint | Focus | Story Points |
|--------|-------|-------------|
| Sprint 1 | Backend Foundation: Migrations, Models, Enums, Interface, Permissions, Controller Stubs, Validation, IntegrationService (partial) | ~33 SP |
| Sprint 2 | Provider Adapters: JiraAdapter, AsanaAdapter, TrelloAdapter, WebhookController, Route Registration, Controller Wiring | ~45 SP |
| Sprint 3 | Jobs & Frontend Foundation: SyncJob, ExportJob, RefreshJob, Scheduled Commands, OpenAPI, TS Types, Pinia Store, Page Scaffold | ~35 SP |
| Sprint 4 | Frontend Components: IntegrationCard, IntegrationDetail, ProjectSyncPanel, SyncHistoryLog, External Ref Badges, Nav, Backend Tests | ~35 SP |
| Sprint 5 | Testing: Endpoint Tests, Adapter Tests, Job Tests, Component Tests, E2E Tests | ~33 SP |

**Total**: ~192 SP / ~288h across 5 sprints (10 weeks)

## Shared Foundation Dependencies
- Core platform (`main`): Project, Task, TimeEntry models and CRUD
- Permission system: Role-based access control via Jetstream
- Laravel Queue: Job dispatching infrastructure
- Feature 00 (Weekly Timesheet Grid): Should be merged before frontend work begins (Sprint 3+)

## Key Architecture Decisions
- 4 new database tables: `integration_connections`, `integration_projects`, `external_task_mappings`, `integration_sync_logs`
- 3 new Eloquent models + 1 sync log model, all with `HasUuids`, `CustomAuditable`, `HasFactory` traits
- 3 new enums: `IntegrationProvider`, `IntegrationStatus`, `SyncDirection`
- Adapter pattern: `IntegrationAdapterInterface` with `JiraAdapter`, `AsanaAdapter`, `TrelloAdapter` implementations
- `BaseIntegrationAdapter` abstract class with shared HTTP client, rate limiting, token refresh helpers
- `IntegrationService` orchestrates connection lifecycle, project/task sync, time entry export, webhook processing
- OAuth 2.0 consumer for Jira and Asana (new capability -- `league/oauth2-client` package)
- API key authentication for Trello (credentials entered via form, validated via test API call)
- Encrypted token storage using `Crypt::encryptString()` on model attribute accessors (new pattern for codebase)
- 3 new permissions: `integrations:view`, `integrations:manage`, `integrations:sync`
- 3 new background jobs: `SyncIntegrationJob`, `ExportTimeEntriesJob`, `RefreshIntegrationTokenJob`
- Webhook endpoints for Jira and Asana (outside `auth:api` middleware, signature-validated)
- Polling-based sync for Trello (configurable frequency: 5/15/30/60 min)
- Frontend: Pinia store (`useIntegrationStore`), 6 Vue components, Inertia page, TypeScript types
- Navigation: Integrations link in Organization Settings section using `PuzzlePieceIcon`
- External reference badges (`ExternalReferenceBadge.vue`) in task selectors and time entry views

## New Files to Create
### Backend (25 files)
- `app/Enums/IntegrationProvider.php`
- `app/Enums/IntegrationStatus.php`
- `app/Enums/SyncDirection.php`
- `app/Http/Controllers/Api/V1/IntegrationController.php`
- `app/Http/Controllers/Api/V1/IntegrationProjectController.php`
- `app/Http/Controllers/Api/V1/WebhookController.php`
- `app/Http/Requests/V1/Integration/IntegrationConnectRequest.php`
- `app/Http/Requests/V1/Integration/IntegrationUpdateRequest.php`
- `app/Http/Requests/V1/Integration/IntegrationProjectToggleRequest.php`
- `app/Http/Requests/V1/Integration/IntegrationSyncLogIndexRequest.php`
- `app/Http/Requests/V1/Integration/ExternalProjectListRequest.php`
- `app/Jobs/SyncIntegrationJob.php`
- `app/Jobs/ExportTimeEntriesJob.php`
- `app/Jobs/RefreshIntegrationTokenJob.php`
- `app/Models/IntegrationConnection.php`
- `app/Models/IntegrationProject.php`
- `app/Models/ExternalTaskMapping.php`
- `app/Models/IntegrationSyncLog.php`
- `app/Permissions/IntegrationPermissions.php`
- `app/Service/IntegrationService.php`
- `app/Service/Integration/IntegrationAdapterInterface.php`
- `app/Service/Integration/BaseIntegrationAdapter.php`
- `app/Service/Integration/JiraAdapter.php`
- `app/Service/Integration/AsanaAdapter.php`
- `app/Service/Integration/TrelloAdapter.php`

### Config (1 file)
- `config/integrations.php`

### Database (8 files)
- `database/migrations/2026_02_09_000001_create_integration_connections_table.php`
- `database/migrations/2026_02_09_000002_create_integration_projects_table.php`
- `database/migrations/2026_02_09_000003_create_external_task_mappings_table.php`
- `database/migrations/2026_02_09_000004_create_integration_sync_logs_table.php`
- `database/factories/IntegrationConnectionFactory.php`
- `database/factories/IntegrationProjectFactory.php`
- `database/factories/ExternalTaskMappingFactory.php`
- `database/factories/IntegrationSyncLogFactory.php`

### Frontend (8 files)
- `resources/js/Pages/Integrations.vue`
- `resources/js/packages/ui/src/Integration/IntegrationCard.vue`
- `resources/js/packages/ui/src/Integration/IntegrationDetail.vue`
- `resources/js/packages/ui/src/Integration/ProjectSyncPanel.vue`
- `resources/js/packages/ui/src/Integration/SyncHistoryLog.vue`
- `resources/js/packages/ui/src/Integration/OAuthCallbackHandler.vue`
- `resources/js/packages/ui/src/Integration/ExternalReferenceBadge.vue`
- `resources/js/utils/useIntegration.ts`
- `resources/js/types/integration.d.ts`

### Tests (15 files)
- `tests/Unit/Endpoint/Api/V1/IntegrationEndpointTest.php`
- `tests/Unit/Endpoint/Api/V1/IntegrationProjectEndpointTest.php`
- `tests/Unit/Endpoint/Api/V1/WebhookEndpointTest.php`
- `tests/Unit/Service/IntegrationServiceTest.php`
- `tests/Unit/Service/Integration/JiraAdapterTest.php`
- `tests/Unit/Service/Integration/AsanaAdapterTest.php`
- `tests/Unit/Service/Integration/TrelloAdapterTest.php`
- `tests/Unit/Job/SyncIntegrationJobTest.php`
- `tests/Unit/Job/ExportTimeEntriesJobTest.php`
- `tests/Unit/Job/RefreshIntegrationTokenJobTest.php`
- `resources/js/packages/ui/src/Integration/__tests__/IntegrationCard.test.ts`
- `resources/js/packages/ui/src/Integration/__tests__/IntegrationDetail.test.ts`
- `resources/js/packages/ui/src/Integration/__tests__/ProjectSyncPanel.test.ts`
- `resources/js/packages/ui/src/Integration/__tests__/SyncHistoryLog.test.ts`
- `e2e/integrations.spec.ts`

## Files to Modify
- `routes/api.php` (add integration, integration-project, sync-log, and webhook route groups)
- `routes/web.php` (add Inertia page route for Integrations)
- `resources/js/Layouts/AppLayout.vue` (add Integrations nav item in Organization Settings)
- `app/Providers/JetstreamServiceProvider.php` (add `IntegrationPermissions::register()`)
- `app/Console/Kernel.php` (add scheduled commands for sync and token refresh)
- `openapi.json` (add 13 endpoint definitions)
- `resources/js/packages/api/src/openapi.json.client.ts` (regenerate from OpenAPI)
- `composer.json` (add `league/oauth2-client` and provider packages)

## Environment Variables Required
```env
# Jira Integration
JIRA_CLIENT_ID=
JIRA_CLIENT_SECRET=

# Asana Integration
ASANA_CLIENT_ID=
ASANA_CLIENT_SECRET=

# Trello Integration (optional)
TRELLO_APP_KEY=

# Sync Settings
INTEGRATION_SYNC_ENABLED=true
INTEGRATION_SYNC_QUEUE=integrations
INTEGRATION_WEBHOOK_RATE_LIMIT=100
```

## Quality Gates
- [ ] `php artisan migrate` runs all 4 new migrations successfully
- [ ] `php artisan migrate:rollback` rolls back cleanly
- [ ] `composer fix && composer analyse` passes
- [ ] `npm run lint:fix && npm run format` passes
- [ ] `npm run build` succeeds
- [ ] All 13 API endpoints have endpoint tests
- [ ] IntegrationService has unit tests
- [ ] All 3 adapters have unit tests (with mocked HTTP)
- [ ] All 3 jobs have unit tests
- [ ] Frontend components have Vitest tests
- [ ] E2E tests cover core workflow (view integrations, connect, sync, disconnect)
- [ ] Encrypted tokens verified (no plaintext in database)
- [ ] Webhook signature validation verified (Jira + Asana)
- [ ] Permission checks enforced on all endpoints

## API Endpoints (13 total)
| Method | Path | Permission |
|--------|------|------------|
| GET | `/api/v1/organizations/{org}/integrations` | `integrations:view` |
| GET | `/api/v1/organizations/{org}/integrations/{id}` | `integrations:view` |
| POST | `/api/v1/organizations/{org}/integrations/connect` | `integrations:manage` |
| GET | `/api/v1/organizations/{org}/integrations/callback` | `integrations:manage` |
| PUT | `/api/v1/organizations/{org}/integrations/{id}` | `integrations:manage` |
| DELETE | `/api/v1/organizations/{org}/integrations/{id}` | `integrations:manage` |
| POST | `/api/v1/organizations/{org}/integrations/{id}/sync` | `integrations:sync` |
| GET | `/api/v1/organizations/{org}/integrations/{id}/external-projects` | `integrations:manage` |
| GET | `/api/v1/organizations/{org}/integration-projects` | `integrations:view` |
| PUT | `/api/v1/organizations/{org}/integration-projects/{id}/toggle` | `integrations:manage` |
| GET | `/api/v1/organizations/{org}/integration-sync-logs` | `integrations:view` |
| POST | `/api/v1/webhooks/jira/{org}` | None (signature) |
| POST | `/api/v1/webhooks/asana/{org}` | None (signature) |

## Planning Docs
- `PRD.md` -- Product requirements
- `task_assignments_20260209.md` -- Task breakdown
- `ARCHITECTURE.md` -- Technical architecture
- `CODEBASE-ANALYSIS.md` -- Integration points and existing infrastructure analysis
- `SPRINT-PLAN.md` -- Sprint-by-sprint implementation plan
