# Sprint Plan: Resource Scheduling (Feature 08)

**Date**: 2026-02-06
**Feature Branch**: `feature/resource-scheduling`
**PRD Reference**: `/home/keven/Documents/solidtime-analysis/.features/08-resource-scheduling/PRD.md`
**Architecture Reference**: `/home/keven/Documents/solidtime-analysis/.features/08-resource-scheduling/ARCHITECTURE.md`
**Task ID Prefix**: `SCHED-` (per SF-01)
**Migration Date Prefix**: `2026_03_08_` (per SF-03)

---

## 1. Executive Summary

### Feature Overview

Resource Scheduling introduces forward-looking resource management to Solidtime: assigning members to projects with planned weekly hours, creating project milestones, visualizing schedules on a Gantt-style timeline, computing capacity utilization, and comparing planned vs. actual tracked time. The feature adds two new database tables (`assignments`, `milestones`), three API controllers with ten endpoints, a comprehensive service layer for capacity calculation, and five Vue components including a timeline visualization.

### Effort Summary

| Metric                      | Value                    |
|-----------------------------|--------------------------|
| **Total Tasks**             | 35 feature tasks + 3 shared foundation prerequisites |
| **Total Story Points**      | 148 SP (feature) + 5 SP (FOUND-006, FOUND-007) |
| **Total Estimated Effort**  | ~225 hours (feature) + ~6 hours (foundations) |
| **Number of Sprints**       | 5 sprints (10 weeks)     |
| **Sprint Duration**         | 2 weeks each             |
| **Team Size Assumption**    | 3 developers: 1 senior backend, 1 senior frontend, 1 fullstack |
| **Per-Sprint Capacity**     | ~120 hours (3 devs x 8h/day x 10 days x 0.5 productivity factor) |
| **Velocity Target**         | 30-35 SP per sprint      |

### Story Point Calibration

Per SF-10, the standard ratio is **2.0 hours per story point**. The original task assignments file uses a mixed ratio averaging ~1.5h/SP. This sprint plan retains the original hour estimates from the task assignments and recalibrates story points to 2.0h/SP where the discrepancy is significant. The hour estimates remain authoritative.

---

## 2. Sprint Overview Table

| Sprint # | Name                          | Duration       | Story Points | Hours  | Key Deliverables                                                         |
|:--------:|-------------------------------|----------------|:------------:|:------:|--------------------------------------------------------------------------|
| 0        | Shared Foundations (pre-work) | 1 week (prior) | 5 SP         | ~6h    | FOUND-006, FOUND-007 completed                                          |
| 1        | Database, Models & Service    | 2 weeks        | 34 SP        | ~55h   | DB schema, Eloquent models, factories, permissions, SchedulingService    |
| 2        | API Layer & Backend Tests     | 2 weeks        | 34 SP        | ~56h   | 3 controllers, 10 endpoints, API routes, backend test suite, OpenAPI     |
| 3        | Frontend Core & Timeline      | 2 weeks        | 38 SP        | ~61h   | Pinia store, Scheduling page, timeline component, assignment form        |
| 4        | Capacity, Milestones & Polish | 2 weeks        | 27 SP        | ~35h   | Capacity panel, milestone UI, capacity settings, cascade deletion        |
| 5        | Testing, Docs & Integration   | 2 weeks        | 15 SP        | ~24h   | Component tests, E2E tests, documentation, integration testing, UAT      |
|          | **TOTAL**                     | **10 weeks**   | **153 SP**   | **~237h** |                                                                       |

---

## 3. Dependency Map

### 3.1 Shared Foundation Dependencies

The following Shared Foundation tasks **must be completed before** the indicated feature tasks can begin. These are external prerequisites owned by the platform team.

```
FOUND-006 (weekly_capacity migrations)
  |
  +---> SCHED-003 is REMOVED (capacity columns now in FOUND-006)
  +---> SCHED-008 (SchedulingService uses weekly_capacity)
  +---> SCHED-034 (capacity settings UI reads weekly_capacity)

FOUND-007 (modular permissions infrastructure)
  |
  +---> SCHED-007 (register permissions via SchedulingPermissions.php)
```

**FOUND-001 through FOUND-005** (notification infrastructure) are NOT hard dependencies for Resource Scheduling. Notifications are a future enhancement for assignment reminders, not part of the initial scope.

### 3.2 Inter-Task Dependency Graph (Critical Path Highlighted)

The critical path is marked with `***`:

```
Sprint 0 (Pre-work):
  FOUND-006 *** --+
  FOUND-007 ------+
                   |
Sprint 1:          v
  SCHED-001 *** --> SCHED-004 *** --> SCHED-006 *** --> SCHED-008 ***
  SCHED-002 ------> SCHED-005 ------> SCHED-006       SCHED-033
  SCHED-007 (parallel)                                  SCHED-034
  SCHED-016 (parallel)
  SCHED-009 <-- SCHED-004
  SCHED-010 <-- SCHED-005
  SCHED-011 <-- SCHED-004, SCHED-005
                   |
Sprint 2:          v
  SCHED-012 *** <-- SCHED-008, SCHED-009, SCHED-011, SCHED-007
  SCHED-013 <------ SCHED-010, SCHED-011, SCHED-007
  SCHED-014 <------ SCHED-008, SCHED-007
  SCHED-015 *** <-- SCHED-012, SCHED-013, SCHED-014
  SCHED-029 <------ SCHED-008
  SCHED-027 <------ SCHED-012, SCHED-015
  SCHED-028 <------ SCHED-013, SCHED-015
  SCHED-030 <------ SCHED-014, SCHED-015
  SCHED-017 *** <-- SCHED-015
                   |
Sprint 3:          v
  SCHED-018 *** <-- SCHED-017
  SCHED-019 *** <-- SCHED-017, SCHED-018
  SCHED-020 <------ SCHED-007
  SCHED-021 *** <-- SCHED-016, SCHED-019, SCHED-020
  SCHED-023 *** <-- SCHED-019, SCHED-018
  SCHED-024 <------ SCHED-019, SCHED-020
                   |
Sprint 4:          v
  SCHED-022 <------ SCHED-021, SCHED-020
  SCHED-025 <------ SCHED-019, SCHED-018
  SCHED-026 <------ SCHED-019, SCHED-020
                   |
Sprint 5:          v
  SCHED-031 <------ SCHED-023, SCHED-024, SCHED-025, SCHED-026
  SCHED-032 <------ SCHED-021 through SCHED-026
  SCHED-035 <------ SCHED-019, SCHED-008
```

### 3.3 Critical Path

```
FOUND-006 -> SCHED-001 -> SCHED-004 -> SCHED-006 -> SCHED-008 -> SCHED-012 -> SCHED-015 -> SCHED-017 -> SCHED-018 -> SCHED-019 -> SCHED-023

Estimated critical path duration: ~84 hours of serial work
```

This is the longest chain of sequentially dependent tasks. Any delay on a critical-path task delays the entire feature. Sprint planning prioritizes critical-path tasks at the start of each sprint.

### 3.4 External Feature Dependencies

| Dependency | Type | Impact on Resource Scheduling |
|---|---|---|
| Feature 07 (PTO & Time Off) | Soft / Optional | Capacity calculation can optionally subtract approved PTO. If PTO is not deployed, capacity defaults to `weekly_capacity`. Design with `class_exists()` guard. |
| Feature 10 (Teams & Groups) | Soft / Optional | Timeline and capacity endpoints accept `team_ids[]` filter. If Teams is not deployed, the parameter is ignored. |

Neither soft dependency blocks any sprint. Integration hooks are coded defensively in SCHED-008 (SchedulingService).

---

## 4. Sprint Details

---

### Sprint 0: Shared Foundations (Pre-Work)

**Sprint Goal**: Complete the shared foundation prerequisites that Resource Scheduling depends on.

**Duration**: Completed before Sprint 1 begins (estimated 1 week lead time, can overlap with other feature foundation work).

| Task ID   | Description                                      | Effort | SP | Dependencies | Assignee Role |
|-----------|--------------------------------------------------|:------:|:--:|:------------:|:-------------:|
| FOUND-006 | Create shared `weekly_capacity` migrations (members + organizations) | 2h | 1 | None | Backend |
| FOUND-007 | Create modular permissions infrastructure (`app/Permissions/` directory) | 4h | 2 | None | Backend |

**Acceptance Criteria**:
- [ ] `members.weekly_capacity` column exists (unsigned integer, default 144000)
- [ ] `organizations.default_weekly_capacity` column exists (unsigned integer, default 144000)
- [ ] `app/Permissions/` directory structure is in place
- [ ] Existing permissions refactored into modular files
- [ ] `JetstreamServiceProvider` calls modular permission registrars
- [ ] All existing tests pass after foundation changes

**Deliverables**:
- `database/migrations/2026_02_28_000001_add_weekly_capacity_to_members.php`
- `database/migrations/2026_02_28_000002_add_default_weekly_capacity_to_organizations.php`
- `app/Permissions/` directory with base structure

**Risk Factors**:
- FOUND-007 touches `JetstreamServiceProvider`, which is a shared file. Coordinate with any parallel feature work to avoid merge conflicts.

---

### Sprint 1: Database, Models & Service Layer

**Sprint Goal**: Establish the complete backend data layer -- database schema, Eloquent models with relationships and factories, permissions, and the core SchedulingService business logic.

**Duration**: 2 weeks (Days 1-10)

| Task ID    | Description                                          | Effort | SP | Dependencies              | Assignee Role | Priority |
|------------|------------------------------------------------------|:------:|:--:|:-------------------------:|:-------------:|:--------:|
| SCHED-001  | Migration: Create `assignments` table with indexes   | 4h     | 2  | FOUND-006                 | Backend       | Critical Path |
| SCHED-002  | Migration: Create `milestones` table with indexes    | 3h     | 2  | None                      | Backend       | High |
| SCHED-007  | Register scheduling permissions (SchedulingPermissions.php) | 2h | 1 | FOUND-007                | Backend       | High |
| SCHED-016  | Register web route for Scheduling page               | 1h     | 1  | None                      | Backend       | Medium |
| SCHED-004  | Eloquent model: `Assignment` + factory               | 4h     | 2  | SCHED-001                 | Backend       | Critical Path |
| SCHED-005  | Eloquent model: `Milestone` + factory                | 3h     | 2  | SCHED-002                 | Backend       | High |
| SCHED-006  | Update existing models (Project, Member, Organization) with new relationships | 2h | 1 | SCHED-004, SCHED-005 | Backend | Critical Path |
| SCHED-009  | Form requests: Assignment validation (Store, Update, Index) | 4h | 2 | SCHED-004                | Backend       | High |
| SCHED-010  | Form requests: Milestone validation (Store, Update)  | 3h     | 2  | SCHED-005                 | Backend       | High |
| SCHED-011  | API resources: AssignmentResource, MilestoneResource + Collections | 4h | 2 | SCHED-004, SCHED-005  | Backend       | High |
| SCHED-008  | SchedulingService: core business logic (capacity, timeline, comparison) | 12h | 6 | SCHED-004, SCHED-005, SCHED-006, FOUND-006 | Backend | Critical Path |
| SCHED-033  | Cascade deletion logic for assignments/milestones    | 3h     | 2  | SCHED-004, SCHED-005, SCHED-006 | Backend  | Medium |
| SCHED-034  | Organization/member settings: weekly capacity config (backend portion) | 5h | 3 | FOUND-006              | Fullstack     | Medium |
| SCHED-020  | Frontend permission helper functions                 | 1h     | 1  | SCHED-007                 | Frontend      | Medium |
| **Sprint Total** |                                               | **51h**| **29** |                        |               |          |

**Note**: SCHED-003 (capacity column migration) from the original task list is **removed** per AMD-04 -- this work is now covered by FOUND-006. The hours originally allocated to SCHED-003 are absorbed.

**Recommended Execution Order (Week 1)**:
1. **Day 1-2 (Backend)**: SCHED-001, SCHED-002, SCHED-007, SCHED-016 in parallel (all have no inter-dependencies)
2. **Day 2-3 (Backend)**: SCHED-004, SCHED-005 (depend on migrations)
3. **Day 3-4 (Backend)**: SCHED-006, SCHED-009, SCHED-010, SCHED-011 (depend on models)
4. **Day 1-2 (Frontend)**: SCHED-020 (depends only on SCHED-007)

**Recommended Execution Order (Week 2)**:
5. **Day 5-8 (Backend Senior)**: SCHED-008 -- critical path, highest-effort backend task
6. **Day 5-6 (Backend/Fullstack)**: SCHED-033, SCHED-034
7. **Day 9-10**: Buffer for SCHED-008 completion, code review, and refinement

**Acceptance Criteria**:
- [ ] `php artisan migrate` creates both new tables with all indexes and foreign keys
- [ ] `php artisan migrate:rollback` cleanly reverses all schema changes
- [ ] `Assignment::factory()->create()` produces valid records with all relationships
- [ ] `Milestone::factory()->create()` produces valid records with all relationships
- [ ] `$project->assignments`, `$project->milestones`, `$member->assignments` return correct collections
- [ ] `SchedulingService::getMemberWeeklyCapacity()` returns member override or org default
- [ ] `SchedulingService::getTimelineData()` returns correctly structured array
- [ ] `SchedulingService::getCapacitySummary()` returns planned + tracked totals
- [ ] All 12 scheduling permissions registered for appropriate roles
- [ ] Placeholder members validated against in assignment form request
- [ ] `composer analyse` passes (PHPStan level 9)
- [ ] `composer fix` passes (PHP-CS-Fixer)

**Deliverables**:
- `database/migrations/2026_03_08_000001_create_assignments_table.php`
- `database/migrations/2026_03_08_000002_create_milestones_table.php`
- `app/Models/Assignment.php`
- `app/Models/Milestone.php`
- `database/factories/AssignmentFactory.php`
- `database/factories/MilestoneFactory.php`
- `app/Service/SchedulingService.php`
- `app/Http/Requests/V1/Assignment/AssignmentStoreRequest.php`
- `app/Http/Requests/V1/Assignment/AssignmentUpdateRequest.php`
- `app/Http/Requests/V1/Assignment/AssignmentIndexRequest.php`
- `app/Http/Requests/V1/Milestone/MilestoneStoreRequest.php`
- `app/Http/Requests/V1/Milestone/MilestoneUpdateRequest.php`
- `app/Http/Resources/V1/Assignment/AssignmentResource.php`
- `app/Http/Resources/V1/Assignment/AssignmentCollection.php`
- `app/Http/Resources/V1/Milestone/MilestoneResource.php`
- `app/Http/Resources/V1/Milestone/MilestoneCollection.php`
- `app/Permissions/SchedulingPermissions.php`
- Modified: `app/Models/Project.php`, `app/Models/Member.php`, `app/Models/Organization.php`
- Modified: `routes/web.php`
- `resources/js/utils/permissions.ts` (permission helpers)

**Risk Factors**:
- SCHED-008 (SchedulingService, 12h) is the highest-effort backend task and sits on the critical path. If it slips, it delays all controllers in Sprint 2. **Mitigation**: Pair-program on complex capacity calculation logic; break into subtasks if needed; prioritize `getTimelineData()` and `getCapacitySummary()` over `getAssignmentComparison()`.
- FOUND-006 must be complete before Sprint 1 starts. **Mitigation**: Verify foundation migrations are merged to main before sprint kickoff.

---

### Sprint 2: API Layer & Backend Tests

**Sprint Goal**: Build all three API controllers with complete CRUD and aggregation endpoints, register routes, write comprehensive backend tests, and generate the OpenAPI spec and TypeScript client.

**Duration**: 2 weeks (Days 11-20)

| Task ID    | Description                                          | Effort | SP | Dependencies                          | Assignee Role | Priority |
|------------|------------------------------------------------------|:------:|:--:|:-------------------------------------:|:-------------:|:--------:|
| SCHED-012  | AssignmentController: CRUD endpoints (index, store, update, destroy) | 8h | 4 | SCHED-008, SCHED-009, SCHED-011, SCHED-007 | Backend | Critical Path |
| SCHED-013  | MilestoneController: CRUD endpoints (index, store, update, destroy) | 8h | 4 | SCHED-010, SCHED-011, SCHED-007      | Backend       | High |
| SCHED-014  | SchedulingController: timeline & capacity aggregation endpoints | 8h | 4 | SCHED-008, SCHED-007                 | Backend       | High |
| SCHED-015  | Register all API routes in `routes/api.php`          | 2h     | 1  | SCHED-012, SCHED-013, SCHED-014      | Backend       | Critical Path |
| SCHED-029  | Backend tests: SchedulingService unit tests          | 8h     | 4  | SCHED-008                             | Backend       | High |
| SCHED-027  | Backend tests: AssignmentEndpointTest                | 8h     | 4  | SCHED-012, SCHED-015                  | Backend       | High |
| SCHED-028  | Backend tests: MilestoneEndpointTest                 | 5h     | 3  | SCHED-013, SCHED-015                  | Backend       | High |
| SCHED-030  | Backend tests: SchedulingEndpointTest                | 5h     | 3  | SCHED-014, SCHED-015                  | Backend       | High |
| SCHED-017  | Update OpenAPI spec and regenerate TypeScript client  | 4h     | 2  | SCHED-015                             | Fullstack     | Critical Path |
| **Sprint Total** |                                               | **56h**| **29** |                                    |               |          |

**Recommended Execution Order (Week 1)**:
1. **Day 1-3 (Backend Senior)**: SCHED-012 -- AssignmentController (critical path)
2. **Day 1-3 (Backend)**: SCHED-013 -- MilestoneController (parallel with SCHED-012)
3. **Day 1-3 (Fullstack)**: SCHED-014 -- SchedulingController (parallel)
4. **Day 3-4 (Backend)**: SCHED-029 -- SchedulingService unit tests (can start once SCHED-008 is done, independent of controllers)
5. **Day 4 (Backend)**: SCHED-015 -- Register routes (quick, unblocks everything below)

**Recommended Execution Order (Week 2)**:
6. **Day 5-7 (Backend Senior)**: SCHED-027 -- Assignment endpoint tests
7. **Day 5-6 (Backend)**: SCHED-028 -- Milestone endpoint tests
8. **Day 5-6 (Fullstack)**: SCHED-030 -- Scheduling endpoint tests
9. **Day 7-8 (Fullstack)**: SCHED-017 -- OpenAPI update + TS client generation (critical path, unblocks Sprint 3)
10. **Day 9-10**: Code review, test fixes, buffer

**Note per AMD-10**: OpenAPI spec updates should happen incrementally as each controller is completed (SCHED-012, SCHED-013, SCHED-014), not as a single bottleneck. However, the final SCHED-017 task consolidates and regenerates the complete TypeScript client.

**Acceptance Criteria**:
- [ ] `GET /api/v1/organizations/{org}/assignments` returns paginated assignments with filters
- [ ] `POST /api/v1/organizations/{org}/assignments` creates assignment with all validations
- [ ] `PUT /api/v1/organizations/{org}/assignments/{id}` updates assignment (immutable member_id/project_id)
- [ ] `DELETE /api/v1/organizations/{org}/assignments/{id}` returns 204
- [ ] `GET /api/v1/organizations/{org}/projects/{project}/milestones` returns milestones
- [ ] `POST /api/v1/organizations/{org}/projects/{project}/milestones` creates milestone
- [ ] `PUT /api/v1/organizations/{org}/projects/{project}/milestones/{id}` updates milestone (including completion toggle)
- [ ] `DELETE /api/v1/organizations/{org}/projects/{project}/milestones/{id}` returns 204
- [ ] `GET /api/v1/organizations/{org}/scheduling/timeline` returns member rows with assignment bars
- [ ] `GET /api/v1/organizations/{org}/scheduling/capacity` returns capacity summaries
- [ ] All endpoints enforce `check-organization-blocked` middleware on write operations
- [ ] All endpoints enforce appropriate permission checks
- [ ] Employee can only see own assignments via `assignments:view:own`
- [ ] 422 returned for non-ProjectMember assignment attempts
- [ ] 422 returned for archived project assignment attempts
- [ ] 422 returned for placeholder member assignment attempts
- [ ] SchedulingService unit tests cover: capacity calculation, timeline assembly, utilization, zero-capacity edge case
- [ ] All endpoint tests pass with success and error cases
- [ ] OpenAPI spec is complete and accurate
- [ ] TypeScript client generated and compiles without errors
- [ ] `composer analyse` and `composer fix` pass

**Deliverables**:
- `app/Http/Controllers/Api/V1/AssignmentController.php`
- `app/Http/Controllers/Api/V1/MilestoneController.php`
- `app/Http/Controllers/Api/V1/SchedulingController.php`
- Modified: `routes/api.php`
- `tests/Unit/Service/SchedulingServiceTest.php`
- `tests/Unit/Endpoint/Api/V1/AssignmentEndpointTest.php`
- `tests/Unit/Endpoint/Api/V1/MilestoneEndpointTest.php`
- `tests/Unit/Endpoint/Api/V1/SchedulingEndpointTest.php`
- Updated OpenAPI spec
- Regenerated TypeScript API client

**Risk Factors**:
- Three controllers being built in parallel requires clear interface contracts from Sprint 1. **Mitigation**: SCHED-008 (SchedulingService) and SCHED-011 (API resources) define these contracts.
- SCHED-017 (OpenAPI/TS client) is the single bottleneck between backend and frontend. **Mitigation**: Frontend developer can start stubbing TypeScript types in parallel; use AMD-10 incremental approach.
- Backend test effort (26h total) is substantial. **Mitigation**: Two backend developers can write tests in parallel.

---

### Sprint 3: Frontend Core & Timeline

**Sprint Goal**: Build the complete frontend data layer (TypeScript types and Pinia store), the main Scheduling page, the Gantt-style timeline component, and the assignment creation form.

**Duration**: 2 weeks (Days 21-30)

| Task ID    | Description                                          | Effort | SP | Dependencies                    | Assignee Role | Priority |
|------------|------------------------------------------------------|:------:|:--:|:-------------------------------:|:-------------:|:--------:|
| SCHED-018  | TypeScript type definitions (`scheduling.d.ts`)      | 3h     | 2  | SCHED-017                       | Frontend      | Critical Path |
| SCHED-019  | Pinia store: `useSchedulingStore` with TanStack Query | 12h   | 6  | SCHED-017, SCHED-018            | Frontend      | Critical Path |
| SCHED-021  | Scheduling page: `Scheduling.vue` (Inertia page)    | 8h     | 4  | SCHED-016, SCHED-019, SCHED-020 | Frontend      | Critical Path |
| SCHED-022  | Add Scheduling to sidebar navigation in `AppLayout.vue` | 1h  | 1  | SCHED-021, SCHED-020            | Frontend      | Medium |
| SCHED-023  | Timeline component: `ScheduleTimeline.vue` + sub-components | 20h | 10 | SCHED-019, SCHED-018        | Frontend      | Critical Path |
| SCHED-024  | Assignment form modal: `AssignmentForm.vue`          | 8h     | 4  | SCHED-019, SCHED-020            | Frontend      | High |
| SCHED-034  | Organization/member settings: weekly capacity config (frontend portion) | 5h | 3 | FOUND-006, SCHED-019     | Fullstack     | Medium |
| **Sprint Total** |                                               | **57h**| **30** |                              |               |          |

**Note**: SCHED-034 is split across Sprint 1 (backend) and Sprint 3 (frontend). The backend portion (API endpoint, validation) was completed in Sprint 1. The frontend portion (settings UI, store integration) happens here.

**Recommended Execution Order (Week 1)**:
1. **Day 1 (Frontend)**: SCHED-018 -- TypeScript types (fast, unblocks everything)
2. **Day 1-4 (Frontend Senior)**: SCHED-019 -- Pinia store (critical path, 12h)
3. **Day 3-5 (Fullstack)**: SCHED-024 -- Assignment form modal (can start once store basics are ready)
4. **Day 4-5 (Frontend)**: SCHED-021 -- Scheduling page shell

**Recommended Execution Order (Week 2)**:
5. **Day 5-10 (Frontend Senior)**: SCHED-023 -- Timeline component (20h, critical path, highest-effort frontend task)
6. **Day 6 (Frontend)**: SCHED-022 -- Sidebar navigation (1h, quick)
7. **Day 7-8 (Fullstack)**: SCHED-034 -- Capacity settings frontend
8. **Day 9-10**: Timeline refinement, code review, polish

**Acceptance Criteria**:
- [ ] Scheduling page accessible at `/scheduling` route
- [ ] Sidebar navigation shows "Scheduling" item with calendar/clock icon
- [ ] Timeline displays weeks on horizontal axis with configurable zoom (1/2/4/8/12 weeks)
- [ ] Members displayed as rows on vertical axis
- [ ] Assignment bars colored by project color (`Project.color`)
- [ ] Today line (vertical) is visible on the timeline
- [ ] Previous/next navigation shifts the time window
- [ ] Hover tooltip on assignment bars shows project name, planned hours, date range
- [ ] "New Assignment" button opens AssignmentForm modal
- [ ] Form validates: member selection, project selection (filtered to member's projects), planned hours, date range
- [ ] Form submission creates assignment via API and refreshes timeline
- [ ] Pinia store handles CRUD operations with optimistic updates
- [ ] TanStack Query manages server state with automatic cache invalidation
- [ ] Organization and member weekly capacity configurable in settings
- [ ] Loading states shown during data fetches
- [ ] `npm run lint:fix` and `npm run format` pass

**Deliverables**:
- `resources/js/types/scheduling.d.ts`
- `resources/js/utils/useScheduling.ts`
- `resources/js/Pages/Scheduling.vue`
- `resources/js/packages/ui/src/Scheduling/ScheduleTimeline.vue`
- `resources/js/packages/ui/src/Scheduling/AssignmentForm.vue`
- Modified: `resources/js/Layouts/AppLayout.vue` (sidebar)
- Modified: Organization/Member settings pages (capacity config)

**Risk Factors**:
- **SCHED-023 (Timeline component, 20h) is the single highest-risk task in the entire feature.** The Gantt-style timeline is complex: horizontal scrolling, bar positioning, zoom levels, milestone markers, responsive layout. **Mitigation per AMD-07**: Evaluate `vue-gantt` or similar lightweight library before building custom. If library integration exceeds 12h, fall back to a simplified table-based view and iterate in Sprint 4.
- SCHED-019 (Pinia store, 12h) is the second-highest-effort frontend task. **Mitigation**: Break into sub-tasks: (a) assignment CRUD, (b) milestone CRUD, (c) timeline data fetching, (d) capacity data fetching.
- The frontend developer is fully loaded this sprint. **Mitigation**: Fullstack developer handles SCHED-024 and SCHED-034 frontend work.

---

### Sprint 4: Capacity, Milestones & Polish

**Sprint Goal**: Complete the remaining UI components (capacity panel, utilization badges, milestone section), implement cascade deletion, and polish the overall user experience.

**Duration**: 2 weeks (Days 31-40)

| Task ID    | Description                                          | Effort | SP | Dependencies                    | Assignee Role | Priority |
|------------|------------------------------------------------------|:------:|:--:|:-------------------------------:|:-------------:|:--------:|
| SCHED-025  | Capacity panel: `CapacityPanel.vue` + `UtilizationBadge.vue` | 8h | 4 | SCHED-019, SCHED-018         | Frontend      | High |
| SCHED-026  | Milestone section for ProjectShow: `MilestoneSection.vue` | 8h | 4 | SCHED-019, SCHED-020          | Frontend      | High |
|            | Employee view restrictions (own-only filtering)      | 4h     | 2  | SCHED-021, SCHED-019            | Frontend      | High |
|            | Timeline polish: milestone diamond markers, zoom refinement, responsive tweaks | 6h | 3 | SCHED-023 | Frontend | Medium |
|            | Assignment edit/delete modal flows                   | 4h     | 2  | SCHED-024, SCHED-019            | Frontend      | High |
|            | Scheduled vs. tracked comparison detail panel        | 5h     | 3  | SCHED-019, SCHED-025            | Fullstack     | Medium |
|            | Bug fixes and refinements from Sprint 3 code review  | 5h     | 3  | --                              | All           | Medium |
| **Sprint Total** |                                               | **40h**| **21** |                              |               |          |

**Note**: Some tasks in this sprint are refinements and sub-components that derive from the original SCHED-023, SCHED-024, and US-004/US-006/US-007 user stories. They are not separately tracked in the original task list but are necessary for feature completeness.

**Recommended Execution Order (Week 1)**:
1. **Day 1-3 (Frontend)**: SCHED-025 -- Capacity panel + UtilizationBadge
2. **Day 1-3 (Frontend Senior)**: SCHED-026 -- Milestone section on ProjectShow
3. **Day 3-4 (Fullstack)**: Scheduled vs. tracked comparison panel
4. **Day 4-5 (Frontend)**: Employee view restrictions (filter timeline to own data)

**Recommended Execution Order (Week 2)**:
5. **Day 6-7 (Frontend Senior)**: Timeline polish (milestone markers, zoom)
6. **Day 6-7 (Frontend)**: Assignment edit/delete flows
7. **Day 8-10 (All)**: Bug fixes, code review, refinement

**Acceptance Criteria**:
- [ ] Capacity panel shows summary table: Member Name, Weekly Capacity, Total Planned, Total Tracked, Utilization %, Status
- [ ] Utilization badge colors: red (>100%), yellow (80-100%), green (<80%)
- [ ] Members with zero capacity show "N/A" indicator
- [ ] Capacity table sortable by any column
- [ ] Date range picker controls analysis window
- [ ] Milestone section on ProjectShow page shows create/edit/delete/complete actions
- [ ] Completed milestones visually distinguished (checkmark/strikethrough)
- [ ] Milestones appear as diamond markers on scheduling timeline
- [ ] Employee sees only own assignments on scheduling page
- [ ] Employee cannot see other members' capacity data
- [ ] Employee cannot create/edit/delete assignments or milestones
- [ ] Clicking assignment bar opens detail panel with planned vs. actual comparison
- [ ] Variance shown as both absolute hours and percentage
- [ ] Progress bar visualization for actual vs. planned

**Deliverables**:
- `resources/js/packages/ui/src/Scheduling/CapacityPanel.vue`
- `resources/js/packages/ui/src/Scheduling/UtilizationBadge.vue`
- `resources/js/packages/ui/src/Scheduling/MilestoneSection.vue`
- Modified: `resources/js/Pages/ProjectShow.vue` (milestone section integration)
- Modified: `resources/js/packages/ui/src/Scheduling/ScheduleTimeline.vue` (milestone markers, polish)
- Modified: `resources/js/packages/ui/src/Scheduling/AssignmentForm.vue` (edit/delete flows)

**Risk Factors**:
- Scope creep from Sprint 3 timeline work could push tasks into this sprint. **Mitigation**: Sprint 4 has built-in buffer (21 SP vs. 30 SP capacity). Use the slack for timeline polish.
- Employee permission filtering requires careful testing to ensure no data leakage. **Mitigation**: Write specific test cases for employee visibility.

---

### Sprint 5: Testing, Documentation & Integration

**Sprint Goal**: Write comprehensive frontend component tests and E2E tests, complete documentation, perform integration testing, and validate the feature for production readiness.

**Duration**: 2 weeks (Days 41-50)

| Task ID    | Description                                          | Effort | SP | Dependencies                            | Assignee Role | Priority |
|------------|------------------------------------------------------|:------:|:--:|:---------------------------------------:|:-------------:|:--------:|
| SCHED-031  | Frontend tests: Vitest component tests               | 8h     | 4  | SCHED-023, SCHED-024, SCHED-025, SCHED-026 | Frontend  | High |
| SCHED-032  | E2E tests: Playwright scheduling tests               | 8h     | 4  | SCHED-021 through SCHED-026             | QA/Fullstack  | High |
| SCHED-035  | Documentation: JSDoc, PHPDoc, CLAUDE.md update       | 3h     | 2  | SCHED-019, SCHED-008                    | Fullstack     | Medium |
|            | Integration testing: cross-feature validation        | 8h     | 4  | All previous tasks                      | All           | High |
|            | Performance testing: timeline with 50+ members       | 4h     | 2  | SCHED-023, SCHED-014                    | Backend       | High |
|            | Cross-browser testing (Chrome, Firefox, Safari)      | 3h     | 2  | All UI tasks                            | Frontend      | Medium |
|            | Accessibility audit (WCAG 2.1 AA)                    | 3h     | 2  | All UI tasks                            | Frontend      | Medium |
|            | Staging deployment and UAT                           | 4h     | 2  | All tasks                               | All           | High |
| **Sprint Total** |                                               | **41h**| **22** |                                      |               |          |

**Recommended Execution Order (Week 1)**:
1. **Day 1-3 (Frontend)**: SCHED-031 -- Vitest component tests
2. **Day 1-3 (QA/Fullstack)**: SCHED-032 -- E2E Playwright tests
3. **Day 1-2 (Backend)**: Performance testing (seed 50+ members, 200 assignments, measure response times)
4. **Day 2-3 (Fullstack)**: SCHED-035 -- Documentation

**Recommended Execution Order (Week 2)**:
5. **Day 5-7 (All)**: Integration testing
6. **Day 6-7 (Frontend)**: Cross-browser testing, accessibility audit
7. **Day 8-9 (All)**: Staging deployment
8. **Day 9-10 (All)**: UAT, bug fixes, final polish

**Acceptance Criteria**:
- [ ] Vitest component tests cover: UtilizationBadge (color thresholds), AssignmentForm (validation), CapacityPanel (sorting), MilestoneSection (CRUD)
- [ ] E2E tests cover: navigate to scheduling page, create assignment, view timeline, switch to capacity view, create milestone
- [ ] All E2E tests pass against staging environment
- [ ] Timeline endpoint responds in < 500ms for 50 members, 200 assignments
- [ ] Capacity endpoint responds in < 300ms
- [ ] Frontend initial render < 2s
- [ ] No critical/high accessibility issues (WCAG 2.1 AA)
- [ ] Feature works in Chrome, Firefox, Safari (latest versions)
- [ ] All PHPDoc blocks complete on service methods
- [ ] All JSDoc comments complete on Pinia store
- [ ] CLAUDE.md updated with Resource Scheduling section
- [ ] OpenAPI spec complete and accurate
- [ ] Staging deployment successful with migration rollback tested
- [ ] UAT sign-off from product stakeholder

**Deliverables**:
- `resources/js/packages/ui/src/Scheduling/__tests__/UtilizationBadge.test.ts`
- `resources/js/packages/ui/src/Scheduling/__tests__/AssignmentForm.test.ts`
- `resources/js/packages/ui/src/Scheduling/__tests__/CapacityPanel.test.ts`
- `resources/js/packages/ui/src/Scheduling/__tests__/MilestoneSection.test.ts`
- `e2e/scheduling.spec.ts`
- Updated `CLAUDE.md`
- Performance test results report
- Accessibility audit report
- UAT sign-off document

**Risk Factors**:
- E2E tests require a seeded database with test data (members, projects, project members, assignments). **Mitigation**: Create a `SchedulingSeeder` or use existing factory patterns in test setup.
- Performance testing may reveal slow queries. **Mitigation**: Budget 4h for query optimization; database indexes were created in Sprint 1.
- UAT feedback may require changes. **Mitigation**: Sprint 5 has buffer capacity; minor fixes can be addressed immediately.

---

## 5. Testing Strategy Per Sprint

### Test Coverage Timeline

| Sprint | Test Type | Tests Written | Coverage Target |
|:------:|-----------|---------------|:---------------:|
| 0 | Regression | Run existing test suite after foundation changes | 100% pass |
| 1 | Unit (manual) | Model factory validation, relationship smoke tests | Informal |
| 2 | Unit (SchedulingService) | `SchedulingServiceTest.php`: capacity calc, timeline assembly, utilization, edge cases | 100% service methods |
| 2 | Endpoint (API) | `AssignmentEndpointTest.php`, `MilestoneEndpointTest.php`, `SchedulingEndpointTest.php` | All endpoints, success + error |
| 3 | Integration (manual) | Frontend-to-API integration testing during development | Informal |
| 4 | Integration (manual) | Employee permission boundary testing, edit/delete flows | Informal |
| 5 | Component (Vitest) | `UtilizationBadge.test.ts`, `AssignmentForm.test.ts`, `CapacityPanel.test.ts`, `MilestoneSection.test.ts` | All UI components |
| 5 | E2E (Playwright) | `scheduling.spec.ts`: full user workflows | 5+ scenarios |
| 5 | Performance | Timeline + capacity endpoints under load | < 500ms / < 300ms |
| 5 | Accessibility | WCAG 2.1 AA audit | No critical issues |
| 5 | Cross-browser | Chrome, Firefox, Safari | All pass |

### Integration Testing Strategy

Integration testing happens at two levels:

1. **Backend integration** (Sprint 2): Endpoint tests exercise the full stack from HTTP request through controller, service, and database. These tests use `TestCaseWithDatabase` with a real PostgreSQL test database.

2. **Frontend integration** (Sprint 5): E2E Playwright tests exercise the full stack from browser interaction through Inertia/API to database. These run against a seeded staging environment.

### Test Dependencies

```
Sprint 2 (Backend Tests):
  SchedulingServiceTest    -> SCHED-008
  AssignmentEndpointTest   -> SCHED-012, SCHED-015
  MilestoneEndpointTest    -> SCHED-013, SCHED-015
  SchedulingEndpointTest   -> SCHED-014, SCHED-015

Sprint 5 (Frontend Tests):
  Component Tests          -> All UI components from Sprints 3-4
  E2E Tests                -> Complete feature (all sprints)
```

---

## 6. Definition of Done

### 6.1 Per-Task Definition of Done

Every task is considered complete when ALL of the following are true:

- [ ] Code compiles without errors
- [ ] `declare(strict_types=1)` at top of every PHP file
- [ ] `composer fix` passes (PHP-CS-Fixer)
- [ ] `composer analyse` passes (PHPStan level 9)
- [ ] `npm run lint:fix` passes (ESLint)
- [ ] `npm run format` passes (Prettier)
- [ ] PHPDoc/JSDoc comments on all public methods
- [ ] Code reviewed by at least one other developer
- [ ] No known regressions in existing test suite
- [ ] Task-specific acceptance criteria met (as listed in sprint details)

### 6.2 Per-Sprint Definition of Done

Each sprint is considered complete when ALL of the following are true:

- [ ] All tasks in the sprint are individually Done (per 6.1)
- [ ] Sprint acceptance criteria fully met
- [ ] All sprint deliverables created/modified as listed
- [ ] Feature branch is rebased on latest `main`
- [ ] CI pipeline passes (all existing + new tests)
- [ ] Sprint demo completed with stakeholder
- [ ] Any carry-over items documented and added to next sprint

### 6.3 Feature-Level Definition of Done

The Resource Scheduling feature is considered complete when ALL of the following are true:

**Functional Completeness**:
- [ ] All 35 feature tasks completed
- [ ] All 7 user stories' acceptance criteria met (US-001 through US-007)
- [ ] All 6 core requirements satisfied (REQ-001 through REQ-006)

**Code Quality**:
- [ ] PHPStan level 9 passes
- [ ] PHP-CS-Fixer passes
- [ ] ESLint passes
- [ ] All code reviewed

**Test Coverage**:
- [ ] 100% test coverage on `SchedulingService`
- [ ] All 10 API endpoints tested (success + error cases)
- [ ] Vitest component tests for all 5 UI components
- [ ] E2E Playwright tests for core user workflows
- [ ] Performance benchmarks met (timeline < 500ms, capacity < 300ms)

**Documentation**:
- [ ] PHPDoc on all service methods
- [ ] JSDoc on Pinia store
- [ ] CLAUDE.md updated with Resource Scheduling section
- [ ] OpenAPI spec complete and accurate

**Deployment Readiness**:
- [ ] Migrations tested with rollback
- [ ] Database indexes validated with `EXPLAIN ANALYZE`
- [ ] Permissions registered and tested for all roles
- [ ] Staging deployment successful
- [ ] UAT sign-off obtained
- [ ] No critical or high-severity bugs open

---

## 7. Risk Register

### 7.1 Technical Risks

| ID | Risk | Probability | Impact | Severity | Mitigation | Owner |
|:--:|------|:-----------:|:------:|:--------:|------------|:-----:|
| TR-01 | Timeline component (SCHED-023, 20h) exceeds estimate due to Gantt rendering complexity | Medium | High | **High** | Evaluate `vue-gantt` library before custom build (AMD-07). Set 12h checkpoint -- if not 60% complete, fall back to table-based view. Iterate timeline to Gantt in subsequent release. | Frontend |
| TR-02 | SchedulingService capacity calculation has edge cases (partial weeks, timezone boundaries, zero-capacity members) | Medium | Medium | **Medium** | Comprehensive unit tests in SCHED-029. Test with edge cases: single-day assignments, cross-timezone orgs, 0-capacity members. Pair-program on complex logic. | Backend |
| TR-03 | Timeline endpoint slow for large orgs (50+ members, 200+ assignments, 12-week window) | Low | High | **Medium** | Composite indexes created in SCHED-001. Performance test in Sprint 5. Implement 5-minute Redis cache. Consider pagination if needed. Use `EXPLAIN ANALYZE` to validate query plans. | Backend |
| TR-04 | Frontend rendering bottleneck: 1000+ DOM nodes for large timelines | Low | Medium | **Low** | Use `vue-virtual-scroller` for member list. CSS `transform` for bar positioning. Debounce scroll events. Limit visible weeks to viewport. | Frontend |
| TR-05 | PostgreSQL `generate_series` and `extract(epoch)` patterns may not be portable to MySQL | Low | Low | **Low** | Solidtime uses PostgreSQL exclusively. Document PostgreSQL dependency in code comments. No mitigation needed. | Backend |
| TR-06 | OpenAPI spec / TypeScript client generation introduces type mismatches | Low | Medium | **Medium** | Incremental OpenAPI updates per AMD-10. Validate generated types against backend responses. Frontend developer reviews generated client before use. | Fullstack |

### 7.2 Dependency Risks

| ID | Risk | Probability | Impact | Severity | Mitigation | Owner |
|:--:|------|:-----------:|:------:|:--------:|------------|:-----:|
| DR-01 | FOUND-006 (weekly_capacity migration) not ready before Sprint 1 | Low | High | **Medium** | Verify foundation migration is merged before sprint kickoff. If delayed, Sprint 1 can proceed with SCHED-001/002/004/005/007 while awaiting FOUND-006. SCHED-008 is the first task that truly needs it. | PM |
| DR-02 | FOUND-007 (modular permissions) not ready before Sprint 1 | Low | Medium | **Low** | SCHED-007 can alternatively register permissions directly in `JetstreamServiceProvider` (legacy approach) and refactor later. No sprint delay. | Backend |
| DR-03 | PTO feature (Feature 07) changes its data model after Resource Scheduling ships | Medium | Low | **Low** | PTO integration in SchedulingService uses `class_exists()` guard and interface-based contract. Changes to PTO model require only updating the integration point. | Backend |
| DR-04 | Concurrent feature development on `routes/api.php` causes merge conflicts | Medium | Low | **Low** | Resource Scheduling routes are in a distinct group block. Use descriptive comments to mark section boundaries. Resolve conflicts during rebase. | Backend |

### 7.3 Capacity Risks

| ID | Risk | Probability | Impact | Severity | Mitigation | Owner |
|:--:|------|:-----------:|:------:|:--------:|------------|:-----:|
| CR-01 | Frontend developer overloaded in Sprint 3 (57h of frontend work) | Medium | Medium | **Medium** | Fullstack developer picks up SCHED-024 and SCHED-034. Backend developers available for code review but not for primary frontend development. | PM |
| CR-02 | Backend developer availability reduced (illness, vacation, other priorities) | Low | High | **Medium** | Two backend developers (senior + fullstack) provide redundancy. Critical-path tasks assigned to senior backend developer. Fullstack can cover if needed. | PM |
| CR-03 | Sprint 5 buffer consumed by bug fixes from earlier sprints | Medium | Medium | **Medium** | Sprint 4 has intentional slack (21 SP vs 30 SP capacity). Catch bugs early. Sprint 5 has 22 SP of planned work vs 30 SP capacity, leaving room for fixes. | PM |
| CR-04 | Code review bottleneck slows sprint velocity | Low | Medium | **Low** | Establish 24-hour SLA for code reviews. Use pair programming on critical-path tasks to reduce review overhead. | PM |

---

## 8. Milestone Timeline

### 8.1 Visual Sprint Timeline

```
Week:  |  W1  |  W2  |  W3  |  W4  |  W5  |  W6  |  W7  |  W8  |  W9  | W10  |
       |------|------|------|------|------|------|------|------|------|------|
       |<-- Sprint 1 -->|<-- Sprint 2 -->|<-- Sprint 3 -->|<-- Sprint 4 -->|<-- Sprint 5 -->|
       |  DB + Models   |  API + Tests   | Frontend Core  | Cap + Polish   | Test + Deploy  |
       |  + Service     |  + OpenAPI     | + Timeline     | + Milestones   | + Docs + UAT   |
       |                |                |                |                |                |
  M1 --+                |                |                |                |                |
       |           M2 --+                |                |                |                |
       |                |           M3 --+                |                |                |
       |                |                |           M4 --+                |                |
       |                |                |                |           M5 --+                |
       |                |                |                |                |           M6 --+
```

### 8.2 Key Milestones

| Milestone | Name | Sprint | Week | Description | Go/No-Go? |
|:---------:|------|:------:|:----:|-------------|:---------:|
| M0 | Foundations Ready | Pre | W0 | FOUND-006 and FOUND-007 merged to main | **Go/No-Go** |
| M1 | Data Layer Complete | 1 | W2 | DB schema, models, factories, SchedulingService all working | Checkpoint |
| M2 | API Complete | 2 | W4 | All 10 endpoints functional with tests passing. TypeScript client generated. | **Go/No-Go** |
| M3 | Frontend Functional | 3 | W6 | Scheduling page renders timeline with real data. Assignment CRUD works end-to-end. | Checkpoint |
| M4 | Feature Complete | 4 | W8 | All UI components built. All user stories implementable. Employee restrictions enforced. | **Go/No-Go** |
| M5 | Test Complete | 5 | W9 | All component tests, E2E tests, and performance tests pass. | Checkpoint |
| M6 | Production Ready | 5 | W10 | Staging deployed. UAT passed. Documentation complete. Ready for production. | **Go/No-Go** |

### 8.3 Go/No-Go Decision Points

**M0 -- Foundations Ready (before Sprint 1)**:
- **Go criteria**: FOUND-006 and FOUND-007 merged, existing tests passing, no regressions.
- **No-Go action**: Delay Sprint 1 start. Backend can begin SCHED-001 and SCHED-002 (no foundation dependency) while awaiting FOUND-006.

**M2 -- API Complete (end of Sprint 2)**:
- **Go criteria**: All 10 API endpoints functional and tested. OpenAPI spec and TypeScript client generated. Frontend can consume the API.
- **No-Go action**: Extend Sprint 2 by up to 3 days. Identify and resolve blocking issues. Frontend developer can begin stubbing with mock data.

**M4 -- Feature Complete (end of Sprint 4)**:
- **Go criteria**: All UI components rendered and functional. All CRUD operations work end-to-end. Employee restrictions enforced. No critical bugs.
- **No-Go action**: Assess scope -- can non-critical features (workload balancing view, timeline zoom levels) be deferred to a follow-up release? Extend Sprint 4 or reallocate Sprint 5 capacity.

**M6 -- Production Ready (end of Sprint 5)**:
- **Go criteria**: All tests pass. Performance benchmarks met. Staging deployment successful. UAT sign-off obtained. No critical or high-severity bugs.
- **No-Go action**: Extend Sprint 5 by up to 1 week. Fix remaining issues. Re-run UAT.

### 8.4 Sprint Burndown Targets

| Sprint | Start SP | End-of-Week-1 Target | End-of-Sprint Target |
|:------:|:--------:|:--------------------:|:--------------------:|
| 1      | 29       | 16                   | 0                    |
| 2      | 29       | 17                   | 0                    |
| 3      | 30       | 18                   | 0                    |
| 4      | 21       | 12                   | 0                    |
| 5      | 22       | 12                   | 0                    |

---

## Appendix A: Task ID Cross-Reference

The original task assignment document uses `SCHED-xxx` IDs. Per AMD-01 and SF-01, all task IDs are prefixed with `SCHED-`. The mapping is:

| Original ID | Sprint Plan ID | Description |
|:-----------:|:--------------:|-------------|
| SCHED-001 | SCHED-001 | Create `assignments` table |
| SCHED-002 | SCHED-002 | Create `milestones` table |
| SCHED-003 | **REMOVED** | Capacity columns (replaced by FOUND-006) |
| SCHED-004 | SCHED-004 | Assignment model + factory |
| SCHED-005 | SCHED-005 | Milestone model + factory |
| SCHED-006 | SCHED-006 | Update existing model relationships |
| SCHED-007 | SCHED-007 | Register permissions |
| SCHED-008 | SCHED-008 | SchedulingService |
| SCHED-009 | SCHED-009 | Assignment form requests |
| SCHED-010 | SCHED-010 | Milestone form requests |
| SCHED-011 | SCHED-011 | API resources |
| SCHED-012 | SCHED-012 | AssignmentController |
| SCHED-013 | SCHED-013 | MilestoneController |
| SCHED-014 | SCHED-014 | SchedulingController |
| SCHED-015 | SCHED-015 | API routes |
| SCHED-016 | SCHED-016 | Web route |
| SCHED-017 | SCHED-017 | OpenAPI + TS client |
| SCHED-018 | SCHED-018 | TypeScript types |
| SCHED-019 | SCHED-019 | Pinia store |
| SCHED-020 | SCHED-020 | Frontend permission helpers |
| SCHED-021 | SCHED-021 | Scheduling page |
| SCHED-022 | SCHED-022 | Sidebar navigation |
| SCHED-023 | SCHED-023 | Timeline component |
| SCHED-024 | SCHED-024 | Assignment form modal |
| SCHED-025 | SCHED-025 | Capacity panel |
| SCHED-026 | SCHED-026 | Milestone section |
| SCHED-027 | SCHED-027 | Assignment endpoint tests |
| SCHED-028 | SCHED-028 | Milestone endpoint tests |
| SCHED-029 | SCHED-029 | SchedulingService unit tests |
| SCHED-030 | SCHED-030 | Scheduling endpoint tests |
| SCHED-031 | SCHED-031 | Vitest component tests |
| SCHED-032 | SCHED-032 | E2E Playwright tests |
| SCHED-033 | SCHED-033 | Cascade deletion logic |
| SCHED-034 | SCHED-034 | Weekly capacity settings |
| SCHED-035 | SCHED-035 | Documentation |

---

## Appendix B: Amendments Incorporated

This sprint plan incorporates the following PRD amendments:

| Amendment | Summary | Impact on Sprint Plan |
|:---------:|---------|----------------------|
| AMD-01 | Task ID prefix `SCHED-` | All task IDs use SCHED- prefix |
| AMD-02 | Migration date prefix `2026_03_08_` | Sprint 1 migration filenames |
| AMD-03 | Modular permissions via `SchedulingPermissions.php` | SCHED-007 creates modular file |
| AMD-04 | `weekly_capacity` owned by FOUND-006 | SCHED-003 removed; FOUND-006 is prerequisite |
| AMD-05 | Milestone routes nested under projects | SCHED-013 and SCHED-015 route structure |
| AMD-06 | Placeholder member validation | SCHED-009 includes role check |
| AMD-07 | Timeline library evaluation | SCHED-023 risk mitigation |
| AMD-08 | PTO + Teams integration hooks | SCHED-008 optional integration design |
| AMD-09 | Sprint allocation required | This document |
| AMD-10 | Incremental OpenAPI updates | SCHED-017 approach in Sprint 2 |

---

Last updated: 2026-02-06
