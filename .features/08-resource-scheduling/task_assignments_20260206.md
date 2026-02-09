# Task Assignments -- Resource Scheduling Feature

Generated: 2026-02-06
PRD Reference: `/home/keven/Documents/solidtime-analysis/.features/08-resource-scheduling/PRD.md`

---

## Task Assignment Table

| Task ID  | Description                                                    | Type                | Assigned Sub-Agent   | Dependencies                      | Effort      | Status |
|----------|----------------------------------------------------------------|---------------------|---------------------|-----------------------------------|-------------|--------|
| TASK-001 | Database migration: Create `assignments` table                 | Backend / Database  | Backend Dev          | None                              | 3 SP (4h)   | To Do  |
| TASK-002 | Database migration: Create `milestones` table                  | Backend / Database  | Backend Dev          | None                              | 2 SP (3h)   | To Do  |
| TASK-003 | Database migration: Add capacity columns to orgs/members       | Backend / Database  | Backend Dev          | None                              | 2 SP (2h)   | To Do  |
| TASK-004 | Eloquent model: `Assignment` with relationships and factory    | Backend             | Backend Dev          | TASK-001                          | 3 SP (4h)   | To Do  |
| TASK-005 | Eloquent model: `Milestone` with relationships and factory     | Backend             | Backend Dev          | TASK-002                          | 2 SP (3h)   | To Do  |
| TASK-006 | Update existing models with new relationships                  | Backend             | Backend Dev          | TASK-004, TASK-005                | 2 SP (2h)   | To Do  |
| TASK-007 | Register new permissions in JetstreamServiceProvider           | Backend             | Backend Dev          | None                              | 2 SP (2h)   | To Do  |
| TASK-008 | `SchedulingService` core business logic                        | Backend             | Backend Dev          | TASK-004, TASK-005, TASK-006, TASK-003 | 8 SP (12h) | To Do  |
| TASK-009 | Form requests: Assignment validation                           | Backend             | Backend Dev          | TASK-004                          | 3 SP (4h)   | To Do  |
| TASK-010 | Form requests: Milestone validation                            | Backend             | Backend Dev          | TASK-005                          | 2 SP (3h)   | To Do  |
| TASK-011 | API resources: Assignment & Milestone serialization            | Backend             | Backend Dev          | TASK-004, TASK-005                | 3 SP (4h)   | To Do  |
| TASK-012 | `AssignmentController` CRUD endpoints                          | Backend             | Backend Dev          | TASK-008, TASK-009, TASK-011, TASK-007 | 5 SP (8h) | To Do  |
| TASK-013 | `MilestoneController` CRUD endpoints                           | Backend             | Backend Dev          | TASK-010, TASK-011, TASK-007      | 5 SP (8h)   | To Do  |
| TASK-014 | `SchedulingController` timeline & capacity endpoints           | Backend             | Backend Dev          | TASK-008, TASK-007                | 5 SP (8h)   | To Do  |
| TASK-015 | Register API routes in `routes/api.php`                        | Backend             | Backend Dev          | TASK-012, TASK-013, TASK-014      | 2 SP (2h)   | To Do  |
| TASK-016 | Register web route for Scheduling page                         | Backend             | Backend Dev          | None                              | 1 SP (1h)   | To Do  |
| TASK-017 | Update OpenAPI spec and regenerate TypeScript client            | Backend / Frontend  | Full-Stack Dev       | TASK-015                          | 3 SP (4h)   | To Do  |
| TASK-018 | TypeScript type definitions (`scheduling.d.ts`)                | Frontend            | Frontend Dev         | TASK-017                          | 2 SP (3h)   | To Do  |
| TASK-019 | Pinia store: `useSchedulingStore`                              | Frontend            | Frontend Dev         | TASK-017, TASK-018                | 8 SP (12h)  | To Do  |
| TASK-020 | Frontend permission helper functions                           | Frontend            | Frontend Dev         | TASK-007                          | 1 SP (1h)   | To Do  |
| TASK-021 | Scheduling page: `Scheduling.vue`                              | Frontend            | Frontend Dev         | TASK-016, TASK-019, TASK-020      | 5 SP (8h)   | To Do  |
| TASK-022 | Add Scheduling to sidebar navigation in `AppLayout.vue`        | Frontend            | Frontend Dev         | TASK-021, TASK-020                | 1 SP (1h)   | To Do  |
| TASK-023 | Timeline component: `ScheduleTimeline.vue` + sub-components    | Frontend            | Frontend Dev         | TASK-019, TASK-018                | 13 SP (20h) | To Do  |
| TASK-024 | Assignment form modal: `AssignmentForm.vue`                    | Frontend            | Frontend Dev         | TASK-019, TASK-020                | 5 SP (8h)   | To Do  |
| TASK-025 | Capacity panel: `CapacityPanel.vue` + `UtilizationBadge.vue`  | Frontend            | Frontend Dev         | TASK-019, TASK-018                | 5 SP (8h)   | To Do  |
| TASK-026 | Milestone section for ProjectShow: `MilestoneSection.vue`      | Frontend            | Frontend Dev         | TASK-019, TASK-020                | 5 SP (8h)   | To Do  |
| TASK-027 | Backend tests: Assignment endpoint tests                       | QA / Backend        | Backend Dev          | TASK-012, TASK-015                | 5 SP (8h)   | To Do  |
| TASK-028 | Backend tests: Milestone endpoint tests                        | QA / Backend        | Backend Dev          | TASK-013, TASK-015                | 3 SP (5h)   | To Do  |
| TASK-029 | Backend tests: SchedulingService unit tests                    | QA / Backend        | Backend Dev          | TASK-008                          | 5 SP (8h)   | To Do  |
| TASK-030 | Backend tests: Scheduling endpoint tests                       | QA / Backend        | Backend Dev          | TASK-014, TASK-015                | 3 SP (5h)   | To Do  |
| TASK-031 | Frontend tests: Vitest component tests                         | QA / Frontend       | Frontend Dev         | TASK-023, TASK-024, TASK-025, TASK-026 | 5 SP (8h) | To Do  |
| TASK-032 | E2E tests: Playwright scheduling tests                         | QA / E2E            | QA Dev               | TASK-021 through TASK-026         | 5 SP (8h)   | To Do  |
| TASK-033 | Cascade deletion logic for assignments                         | Backend             | Backend Dev          | TASK-004, TASK-005, TASK-006      | 2 SP (3h)   | To Do  |
| TASK-034 | Organization/member settings: weekly capacity config           | Backend + Frontend  | Full-Stack Dev       | TASK-003                          | 3 SP (5h)   | To Do  |
| TASK-035 | Documentation: JSDoc, PHPDoc, CLAUDE.md update                 | Documentation       | Full-Stack Dev       | TASK-019, TASK-008                | 2 SP (3h)   | To Do  |

---

## Summary

| Metric                    | Value          |
|---------------------------|----------------|
| Total Tasks               | 35             |
| Total Story Points        | ~148 SP        |
| Total Estimated Effort    | ~225 hours     |
| Planned Sprints           | 5 (10 weeks)   |
| Backend Tasks             | 22             |
| Frontend Tasks            | 12             |
| Documentation Tasks       | 1              |

---

## Execution Order (Dependency-Respecting)

### Wave 1 -- No Dependencies (Can Start Immediately, In Parallel)
- TASK-001: Create `assignments` table
- TASK-002: Create `milestones` table
- TASK-003: Add capacity columns
- TASK-007: Register permissions
- TASK-016: Register web route

### Wave 2 -- Depends on Wave 1
- TASK-004: Assignment model (needs TASK-001)
- TASK-005: Milestone model (needs TASK-002)
- TASK-020: Frontend permission helpers (needs TASK-007)

### Wave 3 -- Depends on Wave 2
- TASK-006: Update existing models (needs TASK-004, TASK-005)
- TASK-009: Assignment form requests (needs TASK-004)
- TASK-010: Milestone form requests (needs TASK-005)
- TASK-011: API resources (needs TASK-004, TASK-005)

### Wave 4 -- Depends on Wave 3
- TASK-008: SchedulingService (needs TASK-006, TASK-003)
- TASK-033: Cascade deletion (needs TASK-004, TASK-005, TASK-006)
- TASK-034: Capacity settings (needs TASK-003)

### Wave 5 -- Depends on Wave 4
- TASK-012: AssignmentController (needs TASK-008, TASK-009, TASK-011, TASK-007)
- TASK-013: MilestoneController (needs TASK-010, TASK-011, TASK-007)
- TASK-014: SchedulingController (needs TASK-008, TASK-007)
- TASK-029: SchedulingService tests (needs TASK-008)

### Wave 6 -- Depends on Wave 5
- TASK-015: API routes (needs TASK-012, TASK-013, TASK-014)
- TASK-027: Assignment endpoint tests (needs TASK-012)
- TASK-028: Milestone endpoint tests (needs TASK-013)
- TASK-030: Scheduling endpoint tests (needs TASK-014)

### Wave 7 -- Depends on Wave 6
- TASK-017: OpenAPI + TS client (needs TASK-015)

### Wave 8 -- Depends on Wave 7
- TASK-018: TypeScript types (needs TASK-017)
- TASK-019: Pinia store (needs TASK-017, TASK-018)

### Wave 9 -- Depends on Wave 8
- TASK-021: Scheduling page (needs TASK-016, TASK-019, TASK-020)
- TASK-023: Timeline component (needs TASK-019, TASK-018)
- TASK-024: Assignment form (needs TASK-019, TASK-020)
- TASK-025: Capacity panel (needs TASK-019, TASK-018)
- TASK-026: Milestone section (needs TASK-019, TASK-020)

### Wave 10 -- Depends on Wave 9
- TASK-022: Sidebar navigation (needs TASK-021, TASK-020)
- TASK-031: Vitest tests (needs TASK-023, TASK-024, TASK-025, TASK-026)
- TASK-032: E2E tests (needs TASK-021 through TASK-026)
- TASK-035: Documentation (needs TASK-019, TASK-008)

---

## Critical Path

```
TASK-001 -> TASK-004 -> TASK-006 -> TASK-008 -> TASK-012 -> TASK-015 -> TASK-017 -> TASK-019 -> TASK-023

Estimated critical path duration: ~80 hours of serial work
```

---

## Parallelization Opportunities

| Parallel Track A (Backend) | Parallel Track B (Backend) | Parallel Track C (Frontend) |
|---|---|---|
| TASK-001 | TASK-002 | TASK-007 |
| TASK-004 | TASK-005 | TASK-016 |
| TASK-009 | TASK-010 | TASK-020 |
| TASK-012 | TASK-013 | -- (waiting for API) |
| TASK-027 | TASK-028 | TASK-018 |
| TASK-029, TASK-030 | TASK-033 | TASK-019 |
| -- | TASK-034 | TASK-021 through TASK-026 |
| -- | -- | TASK-031, TASK-032 |

With 2 backend developers and 1 frontend developer working in parallel, the effective timeline can be reduced from 10 weeks to approximately **7-8 weeks**.

---

## Dependency Risk Assessment

| Risk | Affected Tasks | Mitigation |
|---|---|---|
| TASK-008 (SchedulingService) delays block all controllers | TASK-012, TASK-013, TASK-014 | Start TASK-008 as soon as models are ready; pair-program if needed |
| TASK-017 (OpenAPI/TS client) is single bottleneck between backend and frontend | All frontend tasks | Backend developer completes this immediately after routes; frontend can stub types in parallel |
| TASK-023 (Timeline) is highest-effort frontend task | TASK-031, TASK-032 | Start early in Sprint 3; consider simplified table fallback |
| TASK-015 (Routes) depends on all 3 controllers | TASK-017 onward | Controllers can be routed incrementally (assignments first, then milestones, then scheduling) |
