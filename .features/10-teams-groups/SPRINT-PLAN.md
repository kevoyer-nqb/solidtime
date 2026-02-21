# Sprint Plan: Teams & Groups Feature

**Feature ID**: 10-teams-groups
**Date**: 2026-02-06
**Status**: Planning Complete
**PRD Reference**: `.features/10-teams-groups/PRD.md`
**Architecture Reference**: `.features/10-teams-groups/ARCHITECTURE.md`
**Shared Foundations**: `.features/SHARED-FOUNDATIONS.md`

---

## 1. Executive Summary

The Teams & Groups feature introduces sub-organizational team scoping to Solidtime, enabling organizations to create teams (departments/working groups), assign members, projects, and clients to those teams, and enforce team-based visibility filtering. This is a foundational, cross-cutting feature that affects query behavior across projects, clients, time entries, and reporting.

### Key Numbers

| Metric | Value |
|--------|-------|
| **Total Tasks** | 32 feature tasks + 2 shared foundation prerequisites |
| **Total Story Points** | 76 SP (feature) + 4 SP (foundations) = 80 SP |
| **Total Effort Hours** | ~152h (feature) + ~8h (foundations) = ~160h |
| **Number of Sprints** | 3 sprints (6 weeks) |
| **Sprint Duration** | 2 weeks each |
| **New Files** | ~40 |
| **Modified Files** | ~28 |
| **Total Blast Radius** | ~68 files |

### Team Size Assumptions

| Role | Count | Allocation | Notes |
|------|-------|------------|-------|
| Backend Developer | 1 | Full-time (80h/sprint) | Handles all PHP/Laravel tasks |
| Frontend Developer | 1 | Full-time (80h/sprint) | Handles all Vue/TS tasks |
| QA / Fullstack | 0.5 | Part-time (~40h/sprint) | Assists with testing tasks in Sprint 3; shares effort with backend/frontend devs in Sprints 1-2 |

With this team composition, the 160 total hours fit within 3 two-week sprints. Backend and frontend tracks run in parallel starting mid-Sprint 1, with testing distributed throughout and concentrated in Sprint 3.

---

## 2. Sprint Overview Table

| Sprint # | Name | Duration | Story Points | Key Deliverables |
|----------|------|----------|:------------:|------------------|
| 0 (Pre-req) | Shared Foundations | 1-2 days (pre-sprint) | 4 SP | FOUND-007 (modular permissions), feature flag migration |
| 1 | Data Layer & API | 2 weeks | 28 SP | Database schema, models, factories, TeamService, TeamController, API routes, exceptions, API resources, permissions, data migration |
| 2 | Scoping & Frontend Core | 2 weeks | 30 SP | TeamScopeService, controller scoping (project/client/time entry/manager/chart), TS types, Pinia store, Teams page, team detail view, OpenAPI spec |
| 3 | Integration, Testing & Polish | 2 weeks | 22 SP | API endpoint tests, scoping integration tests, service unit tests, migration tests, frontend component tests, E2E tests, report filter UI |

**Total**: 80 SP across 3 sprints + foundation pre-work

---

## 3. Dependency Map

### 3.1 Shared Foundation Prerequisites

The following Shared Foundation tasks must be completed before feature work begins.

| Foundation Task | Description | Required Before | Effort |
|----------------|-------------|-----------------|--------|
| **FOUND-007** | Create modular permissions infrastructure (`app/Permissions/` directory, refactor pattern) | TEAM-006 (permissions registration) | 4h (2 SP) |

**Note on other FOUND tasks**: FOUND-001 through FOUND-005 (notification infrastructure) are NOT required for Teams & Groups. This feature does not send notifications. FOUND-006 (weekly_capacity) is also not required. Only FOUND-007 is a hard dependency because TEAM-006 registers permissions via the modular `TeamPermissions.php` pattern defined in SF-08.

Additionally, the feature flag migration from SF-07 (`enable_team_scoping` on `organizations`) is embedded within the feature itself (part of TEAM-001 schema work), using the `2026_03_10_` timestamp prefix per SF-03.

### 3.2 Inter-Task Dependency Chain

```
Wave 1 (No dependencies):
  TEAM-001  DB Migrations
  TEAM-012  Custom Exceptions

Wave 2 (After TEAM-001):
  TEAM-002  Data Migration
  TEAM-003  Eloquent Models

Wave 3 (After TEAM-003):
  TEAM-004  Model Factories
  TEAM-005  Existing Model Relations
  TEAM-006  Permissions (also requires FOUND-007)
  TEAM-010  API Resources
  TEAM-019  DeletionService

Wave 4 (After TEAM-003 + TEAM-005):
  TEAM-007  TeamService

Wave 5 (After TEAM-006 + TEAM-007):
  TEAM-008  TeamController
  TEAM-013  TeamScopeService (after TEAM-005 + TEAM-007)

Wave 6 (After TEAM-008):
  TEAM-009  Request Validation
  TEAM-011  API Routes

Wave 7 (After TEAM-013):
  TEAM-014  Project Scoping
  TEAM-015  Client Scoping
  TEAM-016  TimeEntry Filter
  TEAM-018  Chart Scoping

Wave 8 (After TEAM-013 + TEAM-016):
  TEAM-017  Manager Scoping

Wave 9 (After TEAM-010):
  TEAM-020  TypeScript Types

Wave 10 (After TEAM-020 + TEAM-006):
  TEAM-021  Pinia Store
  TEAM-022  FE Permissions

Wave 11 (After TEAM-021 + TEAM-022):
  TEAM-023  Teams Page

Wave 12 (After TEAM-023):
  TEAM-024  Team Detail View

Wave 13 (After TEAM-021 + TEAM-016):
  TEAM-025  Report Filter

Wave 14 (Testing, after respective impl tasks):
  TEAM-026  API Endpoint Tests (after TEAM-008, TEAM-011)
  TEAM-027  Scoping Integration Tests (after TEAM-014-017)
  TEAM-028  Service Unit Tests (after TEAM-007)
  TEAM-029  Migration Tests (after TEAM-002)
  TEAM-030  FE Component Tests (after TEAM-023, TEAM-024)
  TEAM-031  E2E Tests (after TEAM-024, TEAM-025)

Wave 15 (Final):
  TEAM-032  OpenAPI Spec (after TEAM-008, TEAM-010, TEAM-011)
```

### 3.3 Critical Path

The longest sequential dependency chain determines the minimum calendar time:

```
TEAM-001 (5h) -> TEAM-003 (4h) -> TEAM-005 (3h) -> TEAM-007 (6h) -> TEAM-013 (8h) -> TEAM-016 (4h) -> TEAM-017 (4h) -> TEAM-027 (8h)
                                                                                              |
Total sequential: ~42h                                                                   TEAM-025 (5h) -> TEAM-031 (6h)
```

With parallel tracks (backend + frontend), the calendar time is reduced to approximately 6 weeks.

### 3.4 External Feature Dependencies

| Feature | Dependency Type | Notes |
|---------|----------------|-------|
| Feature 07 (PTO) | Soft / Future | When team scoping is enabled, PTO list endpoints should filter by team. Not required for initial delivery. |
| Feature 08 (Scheduling) | Soft / Future | Scheduling views benefit from team-based filtering. Not required for initial delivery. |
| Feature 09 (Reporting) | Soft / Future | Report endpoints accept `team_ids` filter. Foundation laid by TEAM-016/TEAM-025. |

No hard external dependencies. Teams & Groups is in Phase 1a of the deployment order (SF-09) and can proceed independently.

---

## 4. Sprint Details

---

### Sprint 0: Shared Foundations (Pre-Sprint, 1-2 days)

**Sprint Goal**: Establish the modular permissions infrastructure required for clean permission registration.

| Task ID | Description | Effort | SP | Dependencies | Assignee Role |
|---------|-------------|-------:|:--:|--------------|---------------|
| FOUND-007 | Create `app/Permissions/` directory structure, refactor existing permissions into modular pattern | 4h | 2 | None | Backend |

**Acceptance Criteria**:
- `app/Permissions/` directory exists with at least one example file
- `JetstreamServiceProvider::configurePermissions()` delegates to modular permission classes
- All existing tests pass after refactor
- `composer analyse` passes

**Deliverables**:
- `app/Permissions/` directory structure
- Refactored `JetstreamServiceProvider.php`
- Existing permission classes extracted into modular files

**Risk Factors**:
- Low risk. This is a straightforward refactoring of existing code with no behavioral changes.
- Must be completed before Sprint 1 starts, as TEAM-006 depends on it.

---

### Sprint 1: Data Layer & API (Weeks 1-2)

**Sprint Goal**: Build the complete backend foundation -- database schema, models, services, API endpoints, and routes -- so that team CRUD and assignment operations are fully functional via API.

#### Task Table

| Task ID | Description | Effort | SP | Dependencies | Assignee Role |
|---------|-------------|-------:|:--:|--------------|---------------|
| TEAM-001 | Database Migrations -- Create `teams`, `team_members`, `team_projects`, `team_clients` tables + `enable_team_scoping` column on `organizations` | 5h | 2 | None | Backend |
| TEAM-012 | Custom Exception Classes -- `MemberMustBelongToAtLeastOneTeamException`, `OrganizationMustHaveAtLeastOneTeamException` | 1h | 1 | None | Backend |
| TEAM-002 | Data Migration -- Seed default teams for existing organizations (chunked, idempotent) | 4h | 2 | TEAM-001 | Backend |
| TEAM-003 | Eloquent Models -- `Team`, `TeamMember`, `TeamProject`, `TeamClient` with traits, relationships, PHPDoc | 4h | 2 | TEAM-001 | Backend |
| TEAM-004 | Model Factories -- `TeamFactory`, `TeamMemberFactory`, `TeamProjectFactory`, `TeamClientFactory` | 3h | 1 | TEAM-003 | Backend |
| TEAM-005 | Add Relationships to Existing Models -- `Organization`, `Member`, `Project`, `Client` | 3h | 1 | TEAM-003 | Backend |
| TEAM-006 | Register Team Permissions via `app/Permissions/TeamPermissions.php` (SF-08 pattern) | 3h | 1 | TEAM-003, FOUND-007 | Backend |
| TEAM-010 | API Resources -- `TeamResource`, `TeamCollection` with PHPDoc annotations | 3h | 1 | TEAM-003 | Backend |
| TEAM-007 | TeamService -- Business logic for CRUD, member/project/client assignment, validation | 6h | 3 | TEAM-003, TEAM-005 | Backend |
| TEAM-019 | Modify DeletionService -- Add team cleanup during organization deletion | 2h | 1 | TEAM-003 | Backend |
| TEAM-008 | TeamController -- All CRUD + assignment API endpoints | 8h | 3 | TEAM-006, TEAM-007 | Backend |
| TEAM-009 | Request Validation Classes -- `TeamStoreRequest`, `TeamUpdateRequest`, `TeamAddMembersRequest`, etc. | 4h | 2 | TEAM-008 | Backend |
| TEAM-011 | API Routes Registration in `routes/api.php` | 2h | 1 | TEAM-008 | Backend |
| TEAM-028 | TeamService Unit Tests (start writing alongside service development) | 4h | 2 | TEAM-007 | Backend |
| TEAM-029 | Data Migration Test | 3h | 1 | TEAM-002 | Backend |
| TEAM-020 | TypeScript Types for Team models and API responses | 2h | 1 | TEAM-010 | Frontend |
| TEAM-022 | Frontend Permission Utility Functions for Teams | 1h | 1 | TEAM-006 | Frontend |

**Sprint 1 Totals**: 58h effort, 26 SP (Backend: 55h, Frontend: 3h)

**Acceptance Criteria**:
- All four new database tables exist with proper indexes, foreign keys, and unique constraints
- Data migration creates "Default" team per organization with all members/projects/clients assigned
- All four Eloquent models are complete with relationships, traits, and PHPDoc
- TeamService handles all CRUD and assignment logic with proper exception handling
- TeamController exposes all 13 API endpoints (CRUD + member/project/client assignment)
- All routes registered and resolving correctly
- Request validation enforces all business rules (unique names, org scoping, UUID validation)
- API responses serialize correctly via TeamResource/TeamCollection
- Organization deletion cascades to clean up all team data
- TypeScript types defined for all API request/response shapes
- Frontend permission helper functions exported
- TeamService unit tests pass
- Data migration test passes
- `composer fix && composer analyse` passes
- `npm run lint:fix && npm run format` passes

**Deliverables**:

*New Backend Files (24):*
- `database/migrations/2026_03_10_000001_create_teams_table.php`
- `database/migrations/2026_03_10_000002_create_team_members_table.php`
- `database/migrations/2026_03_10_000003_create_team_projects_table.php`
- `database/migrations/2026_03_10_000004_create_team_clients_table.php`
- `database/migrations/2026_03_10_000005_add_enable_team_scoping_to_organizations_table.php`
- `database/migrations/2026_03_10_100001_seed_default_teams_for_existing_organizations.php`
- `app/Models/Team.php`
- `app/Models/TeamMember.php`
- `app/Models/TeamProject.php`
- `app/Models/TeamClient.php`
- `database/factories/TeamFactory.php`
- `database/factories/TeamMemberFactory.php`
- `database/factories/TeamProjectFactory.php`
- `database/factories/TeamClientFactory.php`
- `app/Permissions/TeamPermissions.php`
- `app/Service/TeamService.php`
- `app/Http/Controllers/Api/V1/TeamController.php`
- `app/Http/Requests/V1/Team/TeamIndexRequest.php`
- `app/Http/Requests/V1/Team/TeamStoreRequest.php`
- `app/Http/Requests/V1/Team/TeamUpdateRequest.php`
- `app/Http/Requests/V1/Team/TeamAddMembersRequest.php`
- `app/Http/Requests/V1/Team/TeamAddProjectsRequest.php`
- `app/Http/Requests/V1/Team/TeamAddClientsRequest.php`
- `app/Http/Resources/V1/Team/TeamResource.php`
- `app/Http/Resources/V1/Team/TeamCollection.php`
- `app/Exceptions/Api/MemberMustBelongToAtLeastOneTeamException.php`
- `app/Exceptions/Api/OrganizationMustHaveAtLeastOneTeamException.php`
- `tests/Unit/Service/TeamServiceTest.php`
- `tests/Unit/Migration/DefaultTeamMigrationTest.php`

*Modified Backend Files (6):*
- `app/Models/Organization.php` -- add `enable_team_scoping` cast, `teams()` relationship
- `app/Models/Member.php` -- add `teamMembers()`, `teams()` relationships
- `app/Models/Project.php` -- add `teamProjects()`, `teams()` relationships
- `app/Models/Client.php` -- add `teamClients()`, `teams()` relationships
- `app/Service/DeletionService.php` -- add team cleanup
- `routes/api.php` -- add team routes

*Frontend Files (1 new, 1 modified):*
- `resources/js/types/team.d.ts` (new)
- `resources/js/utils/permissions.ts` (modified -- add team permission functions)

**Risk Factors**:
- **Jetstream Team naming collision**: Mitigated by using `App\Models\Team` with clear PHPDoc and full namespace imports (AMD-13)
- **Data migration on large datasets**: Use `chunkById(100)` and batch inserts. Test with 1000+ orgs before merging
- **Tight Sprint 1 timeline**: 58h for one backend dev is feasible within 80h sprint capacity, but leaves limited buffer. Frontend dev has light load this sprint and can assist with factory and test writing

---

### Sprint 2: Scoping & Frontend Core (Weeks 3-4)

**Sprint Goal**: Implement team-based query scoping across all affected controllers and build the complete frontend Teams management UI.

#### Task Table

| Task ID | Description | Effort | SP | Dependencies | Assignee Role |
|---------|-------------|-------:|:--:|--------------|---------------|
| TEAM-013 | TeamScopeService -- Query-level team filtering with per-request caching | 8h | 5 | TEAM-005, TEAM-007 | Backend |
| TEAM-014 | Modify ProjectController `index()` for team scoping | 4h | 2 | TEAM-013 | Backend |
| TEAM-015 | Modify ClientController `index()` for team scoping | 3h | 2 | TEAM-013 | Backend |
| TEAM-016 | Add `team_ids` filter to `TimeEntryFilter` + wire into `TimeEntryController` | 4h | 2 | TEAM-013 | Backend |
| TEAM-017 | Apply team scoping to Manager time entry views | 4h | 2 | TEAM-013, TEAM-016 | Backend |
| TEAM-018 | Apply team scoping to Chart endpoints (via `DashboardService`) | 3h | 2 | TEAM-013 | Backend |
| TEAM-032 | Update OpenAPI Spec and regenerate TypeScript client | 3h | 1 | TEAM-008, TEAM-010, TEAM-011 | Backend |
| TEAM-021 | Pinia Store -- `useTeams.ts` with all CRUD + assignment operations | 5h | 3 | TEAM-020 | Frontend |
| TEAM-023 | Teams Page (Vue + Inertia) with sidebar navigation | 8h | 5 | TEAM-021, TEAM-022 | Frontend |
| TEAM-024 | Team Detail View Components (tabs, modals, badge) | 10h | 5 | TEAM-023 | Frontend |
| TEAM-026 | API Endpoint Tests -- `TeamEndpointTest` (begin writing) | 4h | 2 | TEAM-008, TEAM-011 | Backend |

**Sprint 2 Totals**: 56h effort, 31 SP (Backend: 33h, Frontend: 23h)

**Note**: TEAM-026 is started in Sprint 2 (4h of the 10h total) to begin covering the endpoints built in Sprint 1. The remaining 6h carries into Sprint 3.

**Acceptance Criteria**:
- `TeamScopeService` correctly filters projects, clients, and time entries by team membership
- Admin/Owner roles bypass all team scoping
- Employees see only projects/clients from their team(s)
- Managers see only time entries from members in their team(s)
- Projects/clients with no team assignment remain visible (backward compatibility)
- Multi-team members see the union of all their teams' data
- `team_ids` filter parameter works on time entry index, aggregate, and export endpoints
- `latestTeamActivity()` chart is filtered by team for managers
- OpenAPI spec includes all team endpoints with correct schemas
- TypeScript client is regenerated and includes team API methods
- Teams page renders with create/edit/delete functionality
- Team detail view shows members/projects/clients in tabs with add/remove actions
- "Teams" navigation item appears in sidebar for users with `teams:view` permission
- All scoping modifications pass existing test suites (no regressions)

**Deliverables**:

*New Backend Files (2):*
- `app/Service/TeamScopeService.php`
- `tests/Unit/Endpoint/Api/V1/TeamEndpointTest.php` (started)

*Modified Backend Files (11):*
- `app/Http/Controllers/Api/V1/ProjectController.php` -- team scoping in `index()`
- `app/Http/Controllers/Api/V1/ClientController.php` -- team scoping in `index()`
- `app/Http/Controllers/Api/V1/TimeEntryController.php` -- `addTeamIdsFilter()` in 2 methods + manager scoping
- `app/Http/Controllers/Api/V1/OrganizationController.php` -- `enable_team_scoping` in `update()`
- `app/Http/Controllers/Api/V1/ChartController.php` -- team scoping for `latestTeamActivity()`
- `app/Service/TimeEntryFilter.php` -- add `addTeamIdsFilter()` method
- `app/Service/DashboardService.php` -- team scoping in `latestTeamActivity()`
- `app/Http/Requests/V1/TimeEntry/TimeEntryIndexRequest.php` -- add `team_ids` validation
- `app/Http/Requests/V1/TimeEntry/TimeEntryAggregateRequest.php` -- add `team_ids` validation
- `app/Http/Requests/V1/Organization/OrganizationUpdateRequest.php` -- add `enable_team_scoping` rule
- `app/Http/Resources/V1/Organization/OrganizationResource.php` -- add `enable_team_scoping` field
- `openapi.json` -- team endpoints added

*New Frontend Files (9):*
- `resources/js/utils/useTeams.ts`
- `resources/js/Pages/Teams.vue`
- `resources/js/Pages/TeamShow.vue`
- `resources/js/packages/ui/src/Team/TeamCreateModal.vue`
- `resources/js/packages/ui/src/Team/TeamEditModal.vue`
- `resources/js/packages/ui/src/Team/TeamMemberTab.vue`
- `resources/js/packages/ui/src/Team/TeamProjectTab.vue`
- `resources/js/packages/ui/src/Team/TeamClientTab.vue`
- `resources/js/packages/ui/src/Team/TeamBadge.vue`

*Modified Frontend Files (2):*
- `resources/js/Layouts/AppLayout.vue` -- add Teams nav item
- `routes/web.php` -- add `/teams` and `/teams/{team}` routes

**Risk Factors**:
- **TeamScopeService complexity**: This is the highest-risk task (TEAM-013, 8h/5 SP). It modifies query behavior across multiple controllers. AMD-11 in the PRD notes this may take 10-12h. Budget 2h buffer.
- **Scoping correctness**: Incorrect scoping could expose cross-team data. Mitigated by starting TEAM-026 integration tests in this sprint.
- **Frontend parallel dependency**: Frontend tasks (TEAM-023, TEAM-024) depend on the Pinia store (TEAM-021), which depends on TypeScript types (TEAM-020, delivered in Sprint 1) and the OpenAPI spec (TEAM-032). Sequence: TEAM-032 early in the sprint so frontend can begin immediately after.
- **Controller modification breadth**: 5 existing controllers are modified. Each must be carefully tested for regression. Pair with the backend dev on code review.

---

### Sprint 3: Integration, Testing & Polish (Weeks 5-6)

**Sprint Goal**: Achieve full test coverage, deliver the report filter UI, and validate end-to-end feature correctness across all roles and scoping scenarios.

#### Task Table

| Task ID | Description | Effort | SP | Dependencies | Assignee Role |
|---------|-------------|-------:|:--:|--------------|---------------|
| TEAM-026 | API Endpoint Tests -- `TeamEndpointTest` (complete remaining 6h) | 6h | 3 | TEAM-008, TEAM-011 | Backend |
| TEAM-027 | Team Scoping Integration Tests -- visibility boundaries for all roles | 8h | 3 | TEAM-014, TEAM-015, TEAM-016, TEAM-017 | Backend |
| TEAM-025 | Team Filter Dropdown in Reports UI | 5h | 3 | TEAM-021, TEAM-016 | Frontend |
| TEAM-030 | Frontend Component Tests (Vitest) -- modals, tabs, badge, permissions | 6h | 3 | TEAM-023, TEAM-024 | Frontend |
| TEAM-031 | E2E Tests (Playwright) -- full CRUD, assignment, scoping, filtering workflows | 6h | 3 | TEAM-024, TEAM-025 | Frontend |

**Sprint 3 Totals**: 31h effort, 15 SP (Backend: 14h, Frontend: 17h)

**Note**: Sprint 3 has intentionally lower effort (31h vs 80h capacity per dev) to accommodate:
- Bug fixes from Sprint 2 scoping work (estimated 8-12h buffer)
- Performance benchmarking and optimization (estimated 4-6h)
- Code review and polish across all files
- Cross-team QA walkthrough
- Documentation updates

**Acceptance Criteria**:
- All 16+ API endpoint test cases pass (TEAM-026)
- All 8+ scoping integration test cases pass (TEAM-027)
- Team filter dropdown works on Time, Reporting, and Detailed Reporting pages
- Manager's team filter is restricted to their own teams server-side
- Admin can filter by any team
- Vitest component tests cover modal validation, tab rendering, badge display, and permission gating
- E2E Playwright tests cover full CRUD workflow, assignment workflow, visibility scoping, and report filtering
- All existing test suites pass (zero regressions)
- `composer fix && composer analyse` passes on all files
- `npm run lint:fix && npm run format` passes on all files
- Performance target met: team-scoped queries add less than 10% latency vs non-scoped

**Deliverables**:

*New Files (4):*
- `tests/Unit/Endpoint/Api/V1/TeamScopingIntegrationTest.php`
- `resources/js/packages/ui/src/Team/TeamDropdown.vue`
- `resources/js/packages/ui/src/Team/TeamDropdownItem.vue`
- `e2e/teams.spec.ts`

*New Test Files (3):*
- `resources/js/packages/ui/src/Team/__tests__/TeamCreateModal.test.ts`
- `resources/js/packages/ui/src/Team/__tests__/TeamMemberTab.test.ts`
- `resources/js/packages/ui/src/Team/__tests__/TeamBadge.test.ts`

*Modified Files (4):*
- `resources/js/Pages/Time.vue` -- add team filter dropdown
- `resources/js/Pages/Reporting.vue` -- add team filter dropdown
- `resources/js/Pages/ReportingDetailed.vue` -- add team filter dropdown
- `resources/js/utils/useReporting.ts` -- pass `team_ids` to API
- `tests/Unit/Endpoint/Api/V1/TeamEndpointTest.php` (completed)

**Risk Factors**:
- **Integration test failures revealing scoping bugs**: Expected and desired. Budget time for fixes.
- **E2E test environment setup**: Playwright tests require a running backend with seeded data. Ensure Docker environment is configured.
- **Performance benchmarking**: May reveal that `whereHas` subqueries need to be converted to `whereIn` with subquery for better index utilization. Budget optimization time.

---

## 5. Testing Strategy Per Sprint

### 5.1 Testing Timeline

| Sprint | Unit Tests | Integration Tests | Component Tests | E2E Tests |
|--------|-----------|-------------------|-----------------|-----------|
| **Sprint 1** | TEAM-028: TeamService unit tests (4h), TEAM-029: Migration test (3h) | None | None | None |
| **Sprint 2** | TEAM-026: API endpoint tests started (4h of 10h) | Manual verification of scoping behavior | None | None |
| **Sprint 3** | TEAM-026: API endpoint tests completed (6h) | TEAM-027: Scoping integration tests (8h) | TEAM-030: Vitest component tests (6h) | TEAM-031: Playwright E2E tests (6h) |

### 5.2 Test Coverage Targets

| Test Type | Target Coverage | Key Focus Areas |
|-----------|:---------------:|-----------------|
| PHPUnit Unit Tests | 90%+ on new code | TeamService methods, TeamScopeService logic, model relationships |
| API Endpoint Tests | All 13 endpoints, all 5 roles | Permission checks, validation rules, edge cases (duplicate names, last team, cross-org) |
| Scoping Integration Tests | All visibility boundaries | Employee sees team-scoped data, manager sees team time entries, admin bypasses, multi-team union, backward compat |
| Vitest Component Tests | Key UI components | Modal form validation, tab rendering, badge color, permission-gated rendering |
| Playwright E2E Tests | 7 critical workflows | CRUD, assignment, scoping verification, report filtering, delete workflow |

### 5.3 Regression Testing

Every sprint includes running the full existing test suite to catch regressions:

- **Sprint 1**: Run after model relationship additions (TEAM-005) and route registration (TEAM-011)
- **Sprint 2**: Run after every controller modification (TEAM-014, TEAM-015, TEAM-016, TEAM-017, TEAM-018)
- **Sprint 3**: Full regression run before feature merge

### 5.4 Performance Testing

Conducted in Sprint 3 (buffer time):
- Benchmark project list query with team scoping on vs off (target: <10% additional latency)
- Test with synthetic dataset: 100 teams, 1000 projects, 5000 members
- Verify composite indexes on all pivot tables are utilized (check EXPLAIN ANALYZE output)
- Validate per-request team ID caching in TeamScopeService

---

## 6. Definition of Done

### 6.1 Per-Task DoD Checklist

Every task must satisfy ALL of the following before being marked complete:

- [ ] Code implements the task description and meets all acceptance criteria listed in the PRD
- [ ] `declare(strict_types=1)` on all new PHP files
- [ ] PHPDoc annotations on all new classes, properties, and methods
- [ ] `composer fix && composer analyse` passes (PHP lint + PHPStan)
- [ ] `npm run lint:fix && npm run format` passes (ESLint + Prettier for frontend tasks)
- [ ] All existing tests pass (zero regressions)
- [ ] Code reviewed by at least one other team member
- [ ] Task-specific tests written (if applicable -- testing tasks are self-evident)
- [ ] No TODO or FIXME comments left without a linked issue

### 6.2 Per-Sprint DoD Checklist

Each sprint must satisfy ALL of the following before the sprint is closed:

- [ ] All tasks in the sprint are marked complete per the per-task DoD
- [ ] Sprint acceptance criteria (listed in Section 4) are all verified
- [ ] Full test suite passes (PHPUnit + Vitest + any applicable Playwright)
- [ ] No critical or high-severity bugs open
- [ ] Code merged to `feature/teams-groups` branch
- [ ] Demo walkthrough completed with stakeholder
- [ ] Sprint retrospective documented

### 6.3 Feature-Level DoD Checklist

The feature is complete when ALL of the following are true:

- [ ] All 32 TEAM tasks and FOUND-007 are complete
- [ ] All sprints pass their DoD
- [ ] **Functional**: Team CRUD works via API and UI for Admin/Owner roles
- [ ] **Functional**: Member, project, and client assignment works via API and UI
- [ ] **Functional**: Team scoping filters projects, clients, and time entries correctly for Employee and Manager roles
- [ ] **Functional**: Admin/Owner bypass team scoping (see all data)
- [ ] **Functional**: `team_ids` filter works on time entry, aggregate, and chart endpoints
- [ ] **Functional**: Report filter UI allows team-based filtering
- [ ] **Functional**: Data migration creates Default team for all existing orgs (idempotent)
- [ ] **Functional**: `enable_team_scoping` feature flag toggles scoping behavior correctly
- [ ] **Performance**: Team-scoped queries add <10% latency (benchmarked)
- [ ] **Security**: Manager cannot filter by teams they do not belong to
- [ ] **Security**: Team IDs in API filters are validated against organization ownership
- [ ] **Backward Compatible**: Existing API behavior unchanged when `enable_team_scoping` = false
- [ ] **Testing**: 90%+ code coverage on new backend code
- [ ] **Testing**: All API endpoints tested for all roles
- [ ] **Testing**: Integration tests verify all visibility boundaries
- [ ] **Testing**: E2E tests cover critical user workflows
- [ ] **Documentation**: OpenAPI spec updated and TypeScript client regenerated
- [ ] **Code Quality**: `composer fix && composer analyse` passes
- [ ] **Code Quality**: `npm run lint:fix && npm run format` passes
- [ ] **Merge**: Feature branch ready for PR to `main`

---

## 7. Risk Register

### 7.1 Technical Risks

| # | Risk | Probability | Impact | Mitigation Strategy |
|---|------|:-----------:|:------:|---------------------|
| R1 | **Query performance degradation from team scoping JOINs** | Medium | High | Composite indexes on all pivot tables. Per-request caching of team IDs in `TeamScopeService`. Benchmark in Sprint 3 with 100+ teams, 1000+ projects. Consider converting `whereHas` to `whereIn` subquery if needed. |
| R2 | **TeamScopeService not applied consistently across all endpoints** | Medium | Critical | Centralize all scoping logic in `TeamScopeService`. Integration tests (TEAM-027) verify every list endpoint. Code review checklist for any endpoint touching projects/clients/time entries. |
| R3 | **Jetstream Team vs App Team naming confusion** | High (likelihood of dev confusion) | Low (functional impact) | Clear PHPDoc on `Team.php`: "This is solidtime's Team, not Jetstream's Team". Full namespace imports. Never import `Laravel\Jetstream\Team` directly. |
| R4 | **Data migration partial failure on large organizations** | Low | High | `chunkById(100)` with batch inserts. Idempotency check (`whereNotExists`). Consider wrapping each org in DB transaction. Validation query post-migration. Test with 1000+ orgs. |
| R5 | **Cross-organization team ID leakage in filters** | Low | Critical | All `team_ids` filter parameters validated: teams must belong to the current organization. Add `whereHas('team', fn($q) => $q->where('organization_id', $org->id))` to all team filter queries. |
| R6 | **Enabling team scoping breaks existing workflows** | Low | High | Data migration assigns all existing entities to "Default" team. UI warning when toggling feature flag. Validation: ensure all members have at least one team before enabling. Immediate rollback possible via `UPDATE organizations SET enable_team_scoping = false`. |
| R7 | **N+1 queries on team relationships in list endpoints** | Medium | Medium | Use `withCount('teamMembers', 'teamProjects', 'teamClients')` for list views instead of eager-loading full team data. Only load full relationships on detail views. |
| R8 | **ReportPropertiesDto backward compatibility** | High (likelihood) | Medium | Do NOT add `teamIds` to `REQUIRED_PROPERTIES`. Handle as optional in getter with `isset()` check, matching existing pattern for `roundingType`. |

### 7.2 Dependency Risks

| # | Risk | Probability | Impact | Mitigation Strategy |
|---|------|:-----------:|:------:|---------------------|
| D1 | **FOUND-007 not completed before Sprint 1** | Low | Medium | FOUND-007 is a 4h task with no dependencies. Schedule it as a pre-sprint task. Fallback: register permissions directly in `JetstreamServiceProvider` and refactor later. |
| D2 | **OpenAPI spec generation blocks frontend work** | Medium | Medium | TEAM-032 is scheduled early in Sprint 2. Frontend can begin with manually-defined TypeScript types (TEAM-020) from Sprint 1. Regenerate and reconcile after TEAM-032. |
| D3 | **Other feature branches modifying shared controllers** | Low | Medium | Teams & Groups is Phase 1a and developed first. Use feature branch `feature/teams-groups` with regular rebasing from `main`. Minimal conflict expected since other features are not yet in development. |

### 7.3 Capacity Risks

| # | Risk | Probability | Impact | Mitigation Strategy |
|---|------|:-----------:|:------:|---------------------|
| C1 | **Sprint 1 backend overload (58h for one dev)** | Medium | Medium | 58h within 80h capacity leaves 22h buffer. Frontend dev has only 3h of work in Sprint 1; can assist with factories (TEAM-004) or migration tests (TEAM-029). |
| C2 | **Sprint 2 dual-track parallelism coordination** | Medium | Low | Backend and frontend tracks have clear dependency boundaries. Daily standup to sync on API contract readiness. Frontend can mock API calls if backend scoping work slips. |
| C3 | **Sprint 3 bug fix overhead exceeding buffer** | Low | Medium | Sprint 3 has 31h of planned work vs 160h combined capacity, leaving ~129h buffer. Even significant scoping bugs can be addressed. |
| C4 | **TEAM-013 effort underestimated (PRD AMD-11 warns 10-12h)** | Medium | Medium | Budgeted at 8h (5 SP) per original estimate. If it extends, pull TEAM-018 (chart scoping, 3h) to Sprint 3. Sprint 2 has enough capacity to absorb 2-4h overage. |

---

## 8. Milestone Timeline

### 8.1 Timeline Visualization

```
Week 0 (Pre-Sprint)
  |
  |-- [M0] FOUND-007 Complete: Modular permissions infrastructure ready
  |
Week 1-2: Sprint 1 — Data Layer & API
  |
  |-- Day 1-2:  TEAM-001 DB Migrations + TEAM-012 Exceptions (parallel)
  |-- Day 3-4:  TEAM-002 Data Migration + TEAM-003 Models (parallel, after TEAM-001)
  |-- Day 5-6:  TEAM-004 Factories + TEAM-005 Relations + TEAM-006 Permissions + TEAM-010 Resources
  |-- Day 7-8:  TEAM-007 TeamService + TEAM-019 DeletionService
  |-- Day 9:    TEAM-008 TeamController (start)
  |-- Day 10:   TEAM-008 TeamController (finish) + TEAM-009 Requests + TEAM-011 Routes
  |-- Day 7-10: TEAM-020 TS Types + TEAM-022 FE Permissions (frontend, parallel)
  |-- Day 9-10: TEAM-028 Service Tests + TEAM-029 Migration Tests
  |
  |-- [M1] API Complete: All team CRUD + assignment endpoints functional
  |
Week 3-4: Sprint 2 — Scoping & Frontend Core
  |
  |-- Day 1-2:  TEAM-013 TeamScopeService + TEAM-032 OpenAPI Spec (parallel)
  |-- Day 3-4:  TEAM-014 Project Scoping + TEAM-015 Client Scoping (parallel)
  |-- Day 3-5:  TEAM-021 Pinia Store (frontend, after TEAM-032)
  |-- Day 5-6:  TEAM-016 TimeEntry Filter + TEAM-018 Chart Scoping (parallel)
  |-- Day 5-8:  TEAM-023 Teams Page (frontend, after TEAM-021)
  |-- Day 7-8:  TEAM-017 Manager Scoping
  |-- Day 8-10: TEAM-024 Team Detail View (frontend)
  |-- Day 9-10: TEAM-026 API Tests (start)
  |
  |-- [M2] Scoping Complete: All visibility boundaries enforced
  |-- [M3] UI Complete: Teams management page and detail view functional
  |
Week 5-6: Sprint 3 — Testing & Polish
  |
  |-- Day 1-2:  TEAM-026 API Tests (complete) + TEAM-025 Report Filter (frontend, parallel)
  |-- Day 3-5:  TEAM-027 Scoping Integration Tests
  |-- Day 3-5:  TEAM-030 FE Component Tests (frontend, parallel)
  |-- Day 6-7:  TEAM-031 E2E Tests
  |-- Day 8-9:  Bug fixes + performance benchmarking
  |-- Day 10:   Final regression run + demo
  |
  |-- [M4] Test Coverage Complete: All test suites green
  |-- [M5] Feature Complete: Ready for PR to main
```

### 8.2 Key Milestones

| Milestone | Target Date | Gate Criteria |
|-----------|-------------|---------------|
| **M0: Foundations Ready** | End of Week 0 | FOUND-007 complete; modular permissions pattern working |
| **M1: API Complete** | End of Week 2 (Sprint 1) | All 13 API endpoints functional; manual API testing passes; service + migration tests green |
| **M2: Scoping Complete** | Mid Week 4 (Sprint 2) | TeamScopeService implemented; all controllers modified; visibility boundaries correct for all roles |
| **M3: UI Complete** | End of Week 4 (Sprint 2) | Teams page + detail view functional; navigation integrated; all CRUD/assignment operations work via UI |
| **M4: Test Coverage Complete** | Mid Week 6 (Sprint 3) | All test suites pass; 90%+ coverage on new backend code; E2E tests green |
| **M5: Feature Complete** | End of Week 6 (Sprint 3) | All DoD criteria met; performance benchmarked; PR created to `main` |

### 8.3 Go/No-Go Decision Points

| Decision Point | When | Criteria | Action if No-Go |
|----------------|------|----------|-----------------|
| **Sprint 1 Review** | End of Week 2 | API endpoints functional; data migration tested | Extend Sprint 1 by 2-3 days; defer TEAM-026 to Sprint 3 |
| **Scoping Checkpoint** | Mid Week 4 | TeamScopeService passes manual verification for all roles | Descope chart scoping (TEAM-018) to future sprint; focus on core project/client/time entry scoping |
| **Test Coverage Gate** | Mid Week 6 | All integration tests pass; no critical scoping bugs | Extend Sprint 3 by 1 week; defer E2E tests to a follow-up sprint |
| **Performance Gate** | End of Week 6 | Team-scoped queries add <10% latency | Optimize queries (convert `whereHas` to `whereIn` subquery); if still failing, add database-level materialized views as follow-up |

---

## Appendix A: Task-to-Sprint Mapping Quick Reference

| Task ID | Description | Sprint | SP | Track |
|---------|-------------|:------:|:--:|-------|
| FOUND-007 | Modular permissions infrastructure | 0 | 2 | Backend |
| TEAM-001 | Database Migrations | 1 | 2 | Backend |
| TEAM-002 | Data Migration | 1 | 2 | Backend |
| TEAM-003 | Eloquent Models | 1 | 2 | Backend |
| TEAM-004 | Model Factories | 1 | 1 | Backend |
| TEAM-005 | Existing Model Relations | 1 | 1 | Backend |
| TEAM-006 | Permissions | 1 | 1 | Backend |
| TEAM-007 | TeamService | 1 | 3 | Backend |
| TEAM-008 | TeamController | 1 | 3 | Backend |
| TEAM-009 | Request Validation | 1 | 2 | Backend |
| TEAM-010 | API Resources | 1 | 1 | Backend |
| TEAM-011 | API Routes | 1 | 1 | Backend |
| TEAM-012 | Custom Exceptions | 1 | 1 | Backend |
| TEAM-013 | TeamScopeService | 2 | 5 | Backend |
| TEAM-014 | Project Scoping | 2 | 2 | Backend |
| TEAM-015 | Client Scoping | 2 | 2 | Backend |
| TEAM-016 | TimeEntry Filter | 2 | 2 | Backend |
| TEAM-017 | Manager Scoping | 2 | 2 | Backend |
| TEAM-018 | Chart Scoping | 2 | 2 | Backend |
| TEAM-019 | DeletionService | 1 | 1 | Backend |
| TEAM-020 | TypeScript Types | 1 | 1 | Frontend |
| TEAM-021 | Pinia Store | 2 | 3 | Frontend |
| TEAM-022 | FE Permissions | 1 | 1 | Frontend |
| TEAM-023 | Teams Page | 2 | 5 | Frontend |
| TEAM-024 | Team Detail View | 2 | 5 | Frontend |
| TEAM-025 | Report Filter | 3 | 3 | Frontend |
| TEAM-026 | API Endpoint Tests | 2-3 | 5 | Backend |
| TEAM-027 | Scoping Integration Tests | 3 | 3 | Backend |
| TEAM-028 | TeamService Unit Tests | 1 | 2 | Backend |
| TEAM-029 | Data Migration Tests | 1 | 1 | Backend |
| TEAM-030 | FE Component Tests | 3 | 3 | Frontend |
| TEAM-031 | E2E Tests | 3 | 3 | Frontend |
| TEAM-032 | OpenAPI Spec | 2 | 1 | Fullstack |

---

## Appendix B: Rollback Plan

If the feature needs to be rolled back after deployment:

**Immediate (no code change):**
```sql
UPDATE organizations SET enable_team_scoping = false;
```
This instantly disables all team scoping logic. Teams still exist but have no effect on queries.

**Code rollback:**
Revert to the previous commit and redeploy. The `enable_team_scoping` column remains but is unused.

**Full data rollback (if needed):**
```sql
TRUNCATE team_members, team_projects, team_clients CASCADE;
DELETE FROM teams;
ALTER TABLE organizations DROP COLUMN IF EXISTS enable_team_scoping;
```

---

*Sprint Plan Generated: 2026-02-06*
*Total Planned Effort: ~160 hours across 3 sprints (6 weeks)*
*Total Story Points: 80 SP*
