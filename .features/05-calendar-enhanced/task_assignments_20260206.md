# Task Assignments: Enhanced Calendar View

Generated: 2026-02-06
PRD Reference: `/home/keven/Documents/solidtime-analysis/.features/05-calendar-enhanced/PRD.md`

## Task Assignment Table

| Task ID   | Description                                              | Type                 | Assigned Sub-Agent | Dependencies               | Effort   | Status |
|-----------|----------------------------------------------------------|----------------------|--------------------|-----------------------------|----------|--------|
| TASK-001  | Database Migrations for calendar_connections and calendar_events tables | Backend (Database)   | Backend Dev        | None                        | 4 hours  | To Do  |
| TASK-002  | Eloquent Models for CalendarConnection and CalendarEvent with factories | Backend (Models)     | Backend Dev        | TASK-001                    | 4 hours  | To Do  |
| TASK-003  | CalendarIntegrationService -- Core OAuth, sync, and conversion logic | Backend (Service)    | Backend Dev        | TASK-002                    | 16 hours | To Do  |
| TASK-004  | Month View Frontend Enhancement (dayGridMonth in FullCalendar) | Frontend (Vue)       | Frontend Dev       | None                        | 6 hours  | To Do  |
| TASK-005  | Calendar Integration Configuration (config/services.php, .env) | Backend (Config)     | Backend Dev        | None                        | 2 hours  | To Do  |
| TASK-006  | CalendarIntegrationController -- REST API endpoints       | Backend (Controller) | Backend Dev        | TASK-003, TASK-005          | 12 hours | To Do  |
| TASK-007  | Background Sync Job (SyncCalendarEventsJob + scheduled command) | Backend (Job/Queue)  | Backend Dev        | TASK-003                    | 6 hours  | To Do  |
| TASK-008  | OpenAPI Spec Update and TypeScript Client Regeneration    | Backend/Frontend     | Backend Dev        | TASK-006                    | 4 hours  | To Do  |
| TASK-009  | Pinia Store for Calendar Integrations (useCalendarIntegrations.ts) | Frontend (Store)     | Frontend Dev       | TASK-008                    | 6 hours  | To Do  |
| TASK-010  | CalendarSettingsPanel Component (connection management UI) | Frontend (Vue)       | Frontend Dev       | TASK-009                    | 8 hours  | To Do  |
| TASK-011  | External Event Rendering in FullCalendar (overlay events) | Frontend (Vue)       | Frontend Dev       | TASK-009, TASK-004          | 10 hours | To Do  |
| TASK-012  | EventToEntryModal Component (convert external event to time entry) | Frontend (Vue)       | Frontend Dev       | TASK-009, TASK-011          | 8 hours  | To Do  |
| TASK-013  | Planning vs Actual Overlay Mode (planned vs tracked comparison) | Frontend (Vue)       | Frontend Dev       | TASK-011                    | 12 hours | To Do  |
| TASK-014  | Calendar Integration Permissions (register + assign to roles) | Backend (Permissions)| Backend Dev        | TASK-006                    | 3 hours  | To Do  |
| TASK-015  | Backend Unit Tests -- Models and CalendarIntegrationService | QA (Backend)         | Backend Dev        | TASK-002, TASK-003          | 8 hours  | To Do  |
| TASK-016  | Backend Endpoint Tests -- CalendarIntegration and CalendarEvent | QA (Backend)         | Backend Dev        | TASK-006, TASK-014          | 10 hours | To Do  |
| TASK-017  | Frontend Component Tests (Vitest for all new Calendar components) | QA (Frontend)        | Frontend Dev       | TASK-010, TASK-011, TASK-012, TASK-013 | 8 hours  | To Do  |
| TASK-018  | E2E Tests (Playwright for month view, integration, conversion) | QA (E2E)             | Frontend Dev       | TASK-017                    | 8 hours  | To Do  |
| TASK-019  | Update OpenAPI Specification Documentation                | Documentation        | Backend Dev        | TASK-008                    | 3 hours  | To Do  |
| TASK-020  | JSDoc and PHPDoc Inline Documentation                     | Documentation        | Shared             | All implementation tasks    | 3 hours  | To Do  |

## Sprint Allocation

### Sprint 1 (Week 1) -- Foundation

| Task ID  | Assigned To  | Effort  | Status |
|----------|-------------|---------|--------|
| TASK-001 | Backend Dev  | 4 hours | To Do  |
| TASK-002 | Backend Dev  | 4 hours | To Do  |
| TASK-004 | Frontend Dev | 6 hours | To Do  |
| TASK-005 | Backend Dev  | 2 hours | To Do  |

**Sprint 1 Total**: 16 hours (8 SP)

### Sprint 2 (Week 2-3) -- API and Backend

| Task ID  | Assigned To  | Effort   | Status |
|----------|-------------|----------|--------|
| TASK-003 | Backend Dev  | 16 hours | To Do  |
| TASK-006 | Backend Dev  | 12 hours | To Do  |
| TASK-007 | Backend Dev  | 6 hours  | To Do  |
| TASK-008 | Backend Dev  | 4 hours  | To Do  |
| TASK-014 | Backend Dev  | 3 hours  | To Do  |

**Sprint 2 Total**: 41 hours (19 SP)

### Sprint 3 (Week 3-4) -- Frontend Integration

| Task ID  | Assigned To  | Effort   | Status |
|----------|-------------|----------|--------|
| TASK-009 | Frontend Dev | 6 hours  | To Do  |
| TASK-010 | Frontend Dev | 8 hours  | To Do  |
| TASK-011 | Frontend Dev | 10 hours | To Do  |
| TASK-012 | Frontend Dev | 8 hours  | To Do  |

**Sprint 3 Total**: 32 hours (18 SP)

### Sprint 4 (Week 5) -- Planning Overlay and Backend Tests

| Task ID  | Assigned To  | Effort   | Status |
|----------|-------------|----------|--------|
| TASK-013 | Frontend Dev | 12 hours | To Do  |
| TASK-015 | Backend Dev  | 8 hours  | To Do  |
| TASK-016 | Backend Dev  | 10 hours | To Do  |

**Sprint 4 Total**: 30 hours (18 SP)

### Sprint 5 (Week 6) -- Frontend Tests, E2E, and Documentation

| Task ID  | Assigned To  | Effort  | Status |
|----------|-------------|---------|--------|
| TASK-017 | Frontend Dev | 8 hours | To Do  |
| TASK-018 | Frontend Dev | 8 hours | To Do  |
| TASK-019 | Backend Dev  | 3 hours | To Do  |
| TASK-020 | Shared       | 3 hours | To Do  |

**Sprint 5 Total**: 22 hours (14 SP)

## Dependency Graph

```mermaid
graph TD
    TASK-001[TASK-001: DB Migrations] --> TASK-002[TASK-002: Models]
    TASK-002 --> TASK-003[TASK-003: Integration Service]
    TASK-005[TASK-005: Config] --> TASK-006[TASK-006: Controller]
    TASK-003 --> TASK-006
    TASK-003 --> TASK-007[TASK-007: Background Sync]
    TASK-006 --> TASK-008[TASK-008: OpenAPI + TS Client]
    TASK-006 --> TASK-014[TASK-014: Permissions]
    TASK-008 --> TASK-009[TASK-009: Pinia Store]
    TASK-009 --> TASK-010[TASK-010: Settings Panel]
    TASK-004[TASK-004: Month View] --> TASK-011[TASK-011: External Event Rendering]
    TASK-009 --> TASK-011
    TASK-011 --> TASK-012[TASK-012: EventToEntry Modal]
    TASK-009 --> TASK-012
    TASK-011 --> TASK-013[TASK-013: Planning Overlay]
    TASK-002 --> TASK-015[TASK-015: Backend Unit Tests]
    TASK-003 --> TASK-015
    TASK-014 --> TASK-016[TASK-016: Backend Endpoint Tests]
    TASK-006 --> TASK-016
    TASK-010 --> TASK-017[TASK-017: Frontend Tests]
    TASK-011 --> TASK-017
    TASK-012 --> TASK-017
    TASK-013 --> TASK-017
    TASK-017 --> TASK-018[TASK-018: E2E Tests]
    TASK-008 --> TASK-019[TASK-019: OpenAPI Docs]
    TASK-013 --> TASK-020[TASK-020: JSDoc/PHPDoc]
```

## Critical Path

**TASK-001 -> TASK-002 -> TASK-003 -> TASK-006 -> TASK-008 -> TASK-009 -> TASK-011 -> TASK-013 -> TASK-017 -> TASK-018**

Total critical path duration: **84 hours** (approximately 10.5 working days)

## Parallelizable Work Streams

| Stream | Tasks | Duration |
|--------|-------|----------|
| Backend Foundation | TASK-001 -> TASK-002 -> TASK-003 -> TASK-006 -> TASK-007, TASK-008, TASK-014 | 42h |
| Frontend Foundation | TASK-004 (independent, no backend dependency) | 6h |
| Frontend Integration | TASK-009 -> TASK-010, TASK-011, TASK-012 -> TASK-013 | 44h (after TASK-008) |
| Backend Testing | TASK-015, TASK-016 (after TASK-003 and TASK-006) | 18h |
| Frontend Testing | TASK-017 -> TASK-018 (after all frontend impl) | 16h |
| Documentation | TASK-019, TASK-020 (after implementation complete) | 6h |

## Dependency Risk Assessment

| Risk | Affected Tasks | Mitigation |
|------|---------------|------------|
| TASK-003 (Integration Service) is the largest single task and blocks TASK-006, TASK-007, TASK-015 | TASK-006, TASK-007, TASK-015 | Break TASK-003 into sub-tasks (Google provider, Microsoft provider, core service) for parallel development; start with Google first |
| TASK-006 (Controller) blocks multiple downstream tasks (TASK-008, TASK-014, TASK-016) | TASK-008, TASK-014, TASK-016 | Prioritize TASK-006 completion; stub endpoints early to unblock TASK-008 |
| TASK-011 (External Event Rendering) depends on both TASK-009 (store) and TASK-004 (month view) | TASK-012, TASK-013, TASK-017 | TASK-004 is independent and can be completed in Sprint 1; ensure TASK-009 is started early in Sprint 3 |
| TASK-020 (Documentation) depends on all implementation tasks | TASK-020 | Write documentation incrementally as each task completes rather than batching at the end |

## Status Assignment Notes

All tasks are currently assigned **To Do** status because:

- No external constraints are blocking any initial tasks.
- TASK-001, TASK-004, and TASK-005 have no dependencies and can be started immediately.
- Dependent tasks (e.g., TASK-002 depends on TASK-001) are **To Do** (not Blocked) because their dependencies are also To Do and ready to be scheduled.
- No tasks are marked as **Blocked** since there are no external dependencies (such as third-party API credential provisioning) that are unresolved. Google and Microsoft OAuth credentials will be configured as part of TASK-005.

## Resource Allocation Summary

| Role | Tasks | Total Effort |
|------|-------|-------------|
| Backend Dev | TASK-001, TASK-002, TASK-003, TASK-005, TASK-006, TASK-007, TASK-008, TASK-014, TASK-015, TASK-016, TASK-019 | 87 hours |
| Frontend Dev | TASK-004, TASK-009, TASK-010, TASK-011, TASK-012, TASK-013, TASK-017, TASK-018 | 66 hours |
| Shared | TASK-020 | 3 hours |
| **Total** | **20 tasks** | **156 hours** |
