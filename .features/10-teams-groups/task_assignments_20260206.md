# Task Assignments -- Teams & Groups Feature

Generated: 2026-02-06
PRD Reference: `/home/keven/Documents/solidtime-analysis/.features/10-teams-groups/PRD.md`

---

## Task Assignment Table

| Task ID | Description | Type | Assigned Sub-Agent | Dependencies | Effort | Status |
|---------|-------------|------|-------------------|--------------|--------|--------|
| TASK-001 | Database Migrations -- Create teams, team_members, team_projects, team_clients tables | Backend / Database | Backend Dev | None | 5h (2 SP) | To Do |
| TASK-002 | Data Migration -- Seed default teams for existing organizations | Backend / Database | Backend Dev | TASK-001 | 4h (2 SP) | To Do |
| TASK-003 | Eloquent Models -- Team, TeamMember, TeamProject, TeamClient | Backend | Backend Dev | TASK-001 | 4h (2 SP) | To Do |
| TASK-004 | Model Factories -- TeamFactory, TeamMemberFactory, TeamProjectFactory, TeamClientFactory | Backend / Testing | Backend Dev | TASK-003 | 3h (1 SP) | To Do |
| TASK-005 | Add Relationships to Existing Models -- Organization, Member, Project, Client | Backend | Backend Dev | TASK-003 | 3h (1 SP) | To Do |
| TASK-006 | Register Team Permissions in Jetstream Role Definitions | Backend | Backend Dev | TASK-003 | 3h (1 SP) | To Do |
| TASK-007 | TeamService -- Business Logic for CRUD and Assignments | Backend | Backend Dev | TASK-003, TASK-005 | 6h (3 SP) | To Do |
| TASK-008 | TeamController -- API Endpoints (CRUD + Assignment) | Backend | Backend Dev | TASK-006, TASK-007 | 8h (3 SP) | To Do |
| TASK-009 | Request Validation Classes -- Team Store/Update/Assignment Requests | Backend | Backend Dev | TASK-008 | 4h (2 SP) | To Do |
| TASK-010 | API Resources -- TeamResource, TeamCollection | Backend | Backend Dev | TASK-003 | 3h (1 SP) | To Do |
| TASK-011 | API Routes Registration in routes/api.php | Backend | Backend Dev | TASK-008 | 2h (1 SP) | To Do |
| TASK-012 | Custom Exception Classes -- MemberMustBelongToAtLeastOneTeamException, etc. | Backend | Backend Dev | None | 1h (1 SP) | To Do |
| TASK-013 | TeamScope Service -- Query-Level Team Filtering | Backend | Backend Dev | TASK-005, TASK-007 | 8h (5 SP) | To Do |
| TASK-014 | Modify ProjectController for Team Scoping | Backend | Backend Dev | TASK-013 | 4h (2 SP) | To Do |
| TASK-015 | Modify ClientController for Team Scoping | Backend | Backend Dev | TASK-013 | 3h (2 SP) | To Do |
| TASK-016 | Add team_ids Filter to TimeEntryFilter + TimeEntryController | Backend | Backend Dev | TASK-013 | 4h (2 SP) | To Do |
| TASK-017 | Apply Team Scoping to Manager Time Entry Views | Backend | Backend Dev | TASK-013, TASK-016 | 4h (2 SP) | To Do |
| TASK-018 | Apply Team Scoping to Chart Endpoints | Backend | Backend Dev | TASK-013 | 3h (2 SP) | To Do |
| TASK-019 | Modify DeletionService for Team Cleanup | Backend | Backend Dev | TASK-003 | 2h (1 SP) | To Do |
| TASK-020 | TypeScript Types for Team Models and API Responses | Frontend | Frontend Dev | TASK-010 | 2h (1 SP) | To Do |
| TASK-021 | Pinia Store -- useTeams.ts | Frontend | Frontend Dev | TASK-020 | 5h (3 SP) | To Do |
| TASK-022 | Frontend Permission Utility Functions for Teams | Frontend | Frontend Dev | TASK-006 | 1h (1 SP) | To Do |
| TASK-023 | Teams Page (Vue + Inertia) with Sidebar Navigation | Frontend | Frontend Dev | TASK-021, TASK-022 | 8h (5 SP) | To Do |
| TASK-024 | Team Detail View Components (Tabs, Modals, Badge) | Frontend | Frontend Dev | TASK-023 | 10h (5 SP) | To Do |
| TASK-025 | Team Filter Dropdown in Reports UI | Frontend | Frontend Dev | TASK-021, TASK-016 | 5h (3 SP) | To Do |
| TASK-026 | API Endpoint Tests -- TeamEndpointTest (PHPUnit) | Backend / Testing | QA / Backend Dev | TASK-008, TASK-011 | 10h (5 SP) | To Do |
| TASK-027 | Team Scoping Integration Tests | Backend / Testing | QA / Backend Dev | TASK-014, TASK-015, TASK-016, TASK-017 | 8h (3 SP) | To Do |
| TASK-028 | TeamService Unit Tests | Backend / Testing | QA / Backend Dev | TASK-007 | 4h (2 SP) | To Do |
| TASK-029 | Data Migration Test | Backend / Testing | QA / Backend Dev | TASK-002 | 3h (1 SP) | To Do |
| TASK-030 | Frontend Component Tests (Vitest) | Frontend / Testing | QA / Frontend Dev | TASK-023, TASK-024 | 6h (3 SP) | To Do |
| TASK-031 | E2E Tests (Playwright) | Frontend / Testing | QA / Frontend Dev | TASK-024, TASK-025 | 6h (3 SP) | To Do |
| TASK-032 | Update OpenAPI Spec and Regenerate TypeScript Client | Backend + Frontend | Backend Dev | TASK-008, TASK-010, TASK-011 | 3h (1 SP) | To Do |

---

## Summary Statistics

| Metric | Value |
|--------|-------|
| Total Tasks | 32 |
| Total Story Points | 76 SP |
| Total Effort Hours | ~152h |
| Backend Tasks | 24 |
| Frontend Tasks | 9 |
| Critical Path Length | TASK-001 -> TASK-003 -> TASK-005 -> TASK-013 -> TASK-017 -> TASK-027 (16 SP, ~31h) |
| Estimated Duration | 6 weeks (3 sprints) |
| Parallel Tracks | 3 (Backend Track A, Backend Track B, Frontend Track C) |

---

## Execution Order (Dependency-Respecting)

### Wave 1: No Dependencies (Can Start Immediately)
- TASK-001: DB Migrations
- TASK-012: Custom Exceptions

### Wave 2: After TASK-001
- TASK-002: Data Migration
- TASK-003: Eloquent Models

### Wave 3: After TASK-003
- TASK-004: Model Factories
- TASK-005: Existing Model Relations
- TASK-006: Permissions
- TASK-010: API Resources
- TASK-019: DeletionService

### Wave 4: After TASK-003 + TASK-005
- TASK-007: TeamService

### Wave 5: After TASK-006 + TASK-007
- TASK-008: TeamController
- TASK-013: TeamScope Service

### Wave 6: After TASK-008
- TASK-009: Request Validation
- TASK-011: Routes

### Wave 7: After TASK-013
- TASK-014: Project Scoping
- TASK-015: Client Scoping
- TASK-016: TimeEntry Filter
- TASK-018: Chart Scoping

### Wave 8: After TASK-013 + TASK-016
- TASK-017: Manager Scoping

### Wave 9: Frontend (After TASK-010)
- TASK-020: TS Types

### Wave 10: Frontend (After TASK-020 + TASK-006)
- TASK-021: Pinia Store
- TASK-022: FE Permissions

### Wave 11: Frontend (After TASK-021 + TASK-022)
- TASK-023: Teams Page

### Wave 12: Frontend (After TASK-023)
- TASK-024: Team Detail View

### Wave 13: Frontend (After TASK-021 + TASK-016)
- TASK-025: Report Filter

### Wave 14: Testing (After respective implementation tasks)
- TASK-026: API Tests (after TASK-008, TASK-011)
- TASK-027: Scoping Tests (after TASK-014, TASK-015, TASK-016, TASK-017)
- TASK-028: Service Tests (after TASK-007)
- TASK-029: Migration Tests (after TASK-002)
- TASK-030: FE Component Tests (after TASK-023, TASK-024)
- TASK-031: E2E Tests (after TASK-024, TASK-025)

### Wave 15: Final
- TASK-032: OpenAPI (after TASK-008, TASK-010, TASK-011)

---

## Status Logic

- **To Do**: Default status. Tasks whose dependencies are To Do, In Progress, or Completed.
- **Blocked**: Tasks where a dependency is blocked by external constraints (e.g., third-party delays). Not applicable if dependency is simply To Do.
- **In Progress**: Actively being worked on.
- **Completed**: Task is done and verified.

All tasks are currently **To Do** as no implementation has started.

---

## Notes

- TASK-012 (Exceptions) has no dependencies and can be built at any time, but is listed in Wave 1 for convenience.
- Frontend tasks (Wave 9+) can begin in parallel with backend Waves 4-8 as long as the TypeScript types (TASK-020) are available. If the OpenAPI spec is generated early (TASK-032), TASK-020 can be automated.
- Testing tasks are listed last but individual test files can be written incrementally alongside implementation.
- The critical path runs through: TASK-001 -> TASK-003 -> TASK-005 -> TASK-013 -> TASK-016/017 -> TASK-027, totaling approximately 31 hours of sequential work. Parallel tracks reduce the calendar time significantly.
