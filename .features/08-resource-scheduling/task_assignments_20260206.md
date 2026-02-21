# Task Assignments -- Resource Scheduling Feature

Generated: 2026-02-06
PRD Reference: `/home/keven/Documents/solidtime-analysis/.features/08-resource-scheduling/PRD.md`

---

## Task Assignment Table

| Task ID  | Description                                                    | Type                | Assigned Sub-Agent   | Dependencies                      | Effort      | Status |
|----------|----------------------------------------------------------------|---------------------|---------------------|-----------------------------------|-------------|--------|
| SCHED-001 | Database migration: Create `assignments` table                 | Backend / Database  | Backend Dev          | None                              | 3 SP (4h)   | To Do  |
| SCHED-002 | Database migration: Create `milestones` table                  | Backend / Database  | Backend Dev          | None                              | 2 SP (3h)   | To Do  |
| SCHED-003 | Database migration: Add capacity columns to orgs/members       | Backend / Database  | Backend Dev          | None                              | 2 SP (2h)   | To Do  |
| SCHED-004 | Eloquent model: `Assignment` with relationships and factory    | Backend             | Backend Dev          | SCHED-001                          | 3 SP (4h)   | To Do  |
| SCHED-005 | Eloquent model: `Milestone` with relationships and factory     | Backend             | Backend Dev          | SCHED-002                          | 2 SP (3h)   | To Do  |
| SCHED-006 | Update existing models with new relationships                  | Backend             | Backend Dev          | SCHED-004, SCHED-005                | 2 SP (2h)   | To Do  |
| SCHED-007 | Register new permissions in JetstreamServiceProvider           | Backend             | Backend Dev          | None                              | 2 SP (2h)   | To Do  |
| SCHED-008 | `SchedulingService` core business logic                        | Backend             | Backend Dev          | SCHED-004, SCHED-005, SCHED-006, SCHED-003 | 8 SP (12h) | To Do  |
| SCHED-009 | Form requests: Assignment validation                           | Backend             | Backend Dev          | SCHED-004                          | 3 SP (4h)   | To Do  |
| SCHED-010 | Form requests: Milestone validation                            | Backend             | Backend Dev          | SCHED-005                          | 2 SP (3h)   | To Do  |
| SCHED-011 | API resources: Assignment & Milestone serialization            | Backend             | Backend Dev          | SCHED-004, SCHED-005                | 3 SP (4h)   | To Do  |
| SCHED-012 | `AssignmentController` CRUD endpoints                          | Backend             | Backend Dev          | SCHED-008, SCHED-009, SCHED-011, SCHED-007 | 5 SP (8h) | To Do  |
| SCHED-013 | `MilestoneController` CRUD endpoints                           | Backend             | Backend Dev          | SCHED-010, SCHED-011, SCHED-007      | 5 SP (8h)   | To Do  |
| SCHED-014 | `SchedulingController` timeline & capacity endpoints           | Backend             | Backend Dev          | SCHED-008, SCHED-007                | 5 SP (8h)   | To Do  |
| SCHED-015 | Register API routes in `routes/api.php`                        | Backend             | Backend Dev          | SCHED-012, SCHED-013, SCHED-014      | 2 SP (2h)   | To Do  |
| SCHED-016 | Register web route for Scheduling page                         | Backend             | Backend Dev          | None                              | 1 SP (1h)   | To Do  |
| SCHED-017 | Update OpenAPI spec and regenerate TypeScript client            | Backend / Frontend  | Full-Stack Dev       | SCHED-015                          | 3 SP (4h)   | To Do  |
| SCHED-018 | TypeScript type definitions (`scheduling.d.ts`)                | Frontend            | Frontend Dev         | SCHED-017                          | 2 SP (3h)   | To Do  |
| SCHED-019 | Pinia store: `useSchedulingStore`                              | Frontend            | Frontend Dev         | SCHED-017, SCHED-018                | 8 SP (12h)  | To Do  |
| SCHED-020 | Frontend permission helper functions                           | Frontend            | Frontend Dev         | SCHED-007                          | 1 SP (1h)   | To Do  |
| SCHED-021 | Scheduling page: `Scheduling.vue`                              | Frontend            | Frontend Dev         | SCHED-016, SCHED-019, SCHED-020      | 5 SP (8h)   | To Do  |
| SCHED-022 | Add Scheduling to sidebar navigation in `AppLayout.vue`        | Frontend            | Frontend Dev         | SCHED-021, SCHED-020                | 1 SP (1h)   | To Do  |
| SCHED-023 | Timeline component: `ScheduleTimeline.vue` + sub-components    | Frontend            | Frontend Dev         | SCHED-019, SCHED-018                | 13 SP (20h) | To Do  |
| SCHED-024 | Assignment form modal: `AssignmentForm.vue`                    | Frontend            | Frontend Dev         | SCHED-019, SCHED-020                | 5 SP (8h)   | To Do  |
| SCHED-025 | Capacity panel: `CapacityPanel.vue` + `UtilizationBadge.vue`  | Frontend            | Frontend Dev         | SCHED-019, SCHED-018                | 5 SP (8h)   | To Do  |
| SCHED-026 | Milestone section for ProjectShow: `MilestoneSection.vue`      | Frontend            | Frontend Dev         | SCHED-019, SCHED-020                | 5 SP (8h)   | To Do  |
| SCHED-027 | Backend tests: Assignment endpoint tests                       | QA / Backend        | Backend Dev          | SCHED-012, SCHED-015                | 5 SP (8h)   | To Do  |
| SCHED-028 | Backend tests: Milestone endpoint tests                        | QA / Backend        | Backend Dev          | SCHED-013, SCHED-015                | 3 SP (5h)   | To Do  |
| SCHED-029 | Backend tests: SchedulingService unit tests                    | QA / Backend        | Backend Dev          | SCHED-008                          | 5 SP (8h)   | To Do  |
| SCHED-030 | Backend tests: Scheduling endpoint tests                       | QA / Backend        | Backend Dev          | SCHED-014, SCHED-015                | 3 SP (5h)   | To Do  |
| SCHED-031 | Frontend tests: Vitest component tests                         | QA / Frontend       | Frontend Dev         | SCHED-023, SCHED-024, SCHED-025, SCHED-026 | 5 SP (8h) | To Do  |
| SCHED-032 | E2E tests: Playwright scheduling tests                         | QA / E2E            | QA Dev               | SCHED-021 through SCHED-026         | 5 SP (8h)   | To Do  |
| SCHED-033 | Cascade deletion logic for assignments                         | Backend             | Backend Dev          | SCHED-004, SCHED-005, SCHED-006      | 2 SP (3h)   | To Do  |
| SCHED-034 | Organization/member settings: weekly capacity config           | Backend + Frontend  | Full-Stack Dev       | SCHED-003                          | 3 SP (5h)   | To Do  |
| SCHED-035 | Documentation: JSDoc, PHPDoc, CLAUDE.md update                 | Documentation       | Full-Stack Dev       | SCHED-019, SCHED-008                | 2 SP (3h)   | To Do  |

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
- SCHED-001: Create `assignments` table
- SCHED-002: Create `milestones` table
- SCHED-003: Add capacity columns
- SCHED-007: Register permissions
- SCHED-016: Register web route

### Wave 2 -- Depends on Wave 1
- SCHED-004: Assignment model (needs SCHED-001)
- SCHED-005: Milestone model (needs SCHED-002)
- SCHED-020: Frontend permission helpers (needs SCHED-007)

### Wave 3 -- Depends on Wave 2
- SCHED-006: Update existing models (needs SCHED-004, SCHED-005)
- SCHED-009: Assignment form requests (needs SCHED-004)
- SCHED-010: Milestone form requests (needs SCHED-005)
- SCHED-011: API resources (needs SCHED-004, SCHED-005)

### Wave 4 -- Depends on Wave 3
- SCHED-008: SchedulingService (needs SCHED-006, SCHED-003)
- SCHED-033: Cascade deletion (needs SCHED-004, SCHED-005, SCHED-006)
- SCHED-034: Capacity settings (needs SCHED-003)

### Wave 5 -- Depends on Wave 4
- SCHED-012: AssignmentController (needs SCHED-008, SCHED-009, SCHED-011, SCHED-007)
- SCHED-013: MilestoneController (needs SCHED-010, SCHED-011, SCHED-007)
- SCHED-014: SchedulingController (needs SCHED-008, SCHED-007)
- SCHED-029: SchedulingService tests (needs SCHED-008)

### Wave 6 -- Depends on Wave 5
- SCHED-015: API routes (needs SCHED-012, SCHED-013, SCHED-014)
- SCHED-027: Assignment endpoint tests (needs SCHED-012)
- SCHED-028: Milestone endpoint tests (needs SCHED-013)
- SCHED-030: Scheduling endpoint tests (needs SCHED-014)

### Wave 7 -- Depends on Wave 6
- SCHED-017: OpenAPI + TS client (needs SCHED-015)

### Wave 8 -- Depends on Wave 7
- SCHED-018: TypeScript types (needs SCHED-017)
- SCHED-019: Pinia store (needs SCHED-017, SCHED-018)

### Wave 9 -- Depends on Wave 8
- SCHED-021: Scheduling page (needs SCHED-016, SCHED-019, SCHED-020)
- SCHED-023: Timeline component (needs SCHED-019, SCHED-018)
- SCHED-024: Assignment form (needs SCHED-019, SCHED-020)
- SCHED-025: Capacity panel (needs SCHED-019, SCHED-018)
- SCHED-026: Milestone section (needs SCHED-019, SCHED-020)

### Wave 10 -- Depends on Wave 9
- SCHED-022: Sidebar navigation (needs SCHED-021, SCHED-020)
- SCHED-031: Vitest tests (needs SCHED-023, SCHED-024, SCHED-025, SCHED-026)
- SCHED-032: E2E tests (needs SCHED-021 through SCHED-026)
- SCHED-035: Documentation (needs SCHED-019, SCHED-008)

---

## Critical Path

```
SCHED-001 -> SCHED-004 -> SCHED-006 -> SCHED-008 -> SCHED-012 -> SCHED-015 -> SCHED-017 -> SCHED-019 -> SCHED-023

Estimated critical path duration: ~80 hours of serial work
```

---

## Parallelization Opportunities

| Parallel Track A (Backend) | Parallel Track B (Backend) | Parallel Track C (Frontend) |
|---|---|---|
| SCHED-001 | SCHED-002 | SCHED-007 |
| SCHED-004 | SCHED-005 | SCHED-016 |
| SCHED-009 | SCHED-010 | SCHED-020 |
| SCHED-012 | SCHED-013 | -- (waiting for API) |
| SCHED-027 | SCHED-028 | SCHED-018 |
| SCHED-029, SCHED-030 | SCHED-033 | SCHED-019 |
| -- | SCHED-034 | SCHED-021 through SCHED-026 |
| -- | -- | SCHED-031, SCHED-032 |

With 2 backend developers and 1 frontend developer working in parallel, the effective timeline can be reduced from 10 weeks to approximately **7-8 weeks**.

---

## Dependency Risk Assessment

| Risk | Affected Tasks | Mitigation |
|---|---|---|
| SCHED-008 (SchedulingService) delays block all controllers | SCHED-012, SCHED-013, SCHED-014 | Start SCHED-008 as soon as models are ready; pair-program if needed |
| SCHED-017 (OpenAPI/TS client) is single bottleneck between backend and frontend | All frontend tasks | Backend developer completes this immediately after routes; frontend can stub types in parallel |
| SCHED-023 (Timeline) is highest-effort frontend task | SCHED-031, SCHED-032 | Start early in Sprint 3; consider simplified table fallback |
| SCHED-015 (Routes) depends on all 3 controllers | SCHED-017 onward | Controllers can be routed incrementally (assignments first, then milestones, then scheduling) |
