# Task Assignments -- Teams & Groups Feature

Generated: 2026-02-06
PRD Reference: `/home/keven/Documents/solidtime-analysis/.features/10-teams-groups/PRD.md`

---

## Task Assignment Table

| Task ID | Description | Type | Assigned Sub-Agent | Dependencies | Effort | Status |
|---------|-------------|------|-------------------|--------------|--------|--------|
| TEAM-001 | Database Migrations -- Create teams, team_members, team_projects, team_clients tables | Backend / Database | Backend Dev | None | 5h (2 SP) | To Do |
| TEAM-002 | Data Migration -- Seed default teams for existing organizations | Backend / Database | Backend Dev | TEAM-001 | 4h (2 SP) | To Do |
| TEAM-003 | Eloquent Models -- Team, TeamMember, TeamProject, TeamClient | Backend | Backend Dev | TEAM-001 | 4h (2 SP) | To Do |
| TEAM-004 | Model Factories -- TeamFactory, TeamMemberFactory, TeamProjectFactory, TeamClientFactory | Backend / Testing | Backend Dev | TEAM-003 | 3h (1 SP) | To Do |
| TEAM-005 | Add Relationships to Existing Models -- Organization, Member, Project, Client | Backend | Backend Dev | TEAM-003 | 3h (1 SP) | To Do |
| TEAM-006 | Register Team Permissions in Jetstream Role Definitions | Backend | Backend Dev | TEAM-003 | 3h (1 SP) | To Do |
| TEAM-007 | TeamService -- Business Logic for CRUD and Assignments | Backend | Backend Dev | TEAM-003, TEAM-005 | 6h (3 SP) | To Do |
| TEAM-008 | TeamController -- API Endpoints (CRUD + Assignment) | Backend | Backend Dev | TEAM-006, TEAM-007 | 8h (3 SP) | To Do |
| TEAM-009 | Request Validation Classes -- Team Store/Update/Assignment Requests | Backend | Backend Dev | TEAM-008 | 4h (2 SP) | To Do |
| TEAM-010 | API Resources -- TeamResource, TeamCollection | Backend | Backend Dev | TEAM-003 | 3h (1 SP) | To Do |
| TEAM-011 | API Routes Registration in routes/api.php | Backend | Backend Dev | TEAM-008 | 2h (1 SP) | To Do |
| TEAM-012 | Custom Exception Classes -- MemberMustBelongToAtLeastOneTeamException, etc. | Backend | Backend Dev | None | 1h (1 SP) | To Do |
| TEAM-013 | TeamScope Service -- Query-Level Team Filtering | Backend | Backend Dev | TEAM-005, TEAM-007 | 8h (5 SP) | To Do |
| TEAM-014 | Modify ProjectController for Team Scoping | Backend | Backend Dev | TEAM-013 | 4h (2 SP) | To Do |
| TEAM-015 | Modify ClientController for Team Scoping | Backend | Backend Dev | TEAM-013 | 3h (2 SP) | To Do |
| TEAM-016 | Add team_ids Filter to TimeEntryFilter + TimeEntryController | Backend | Backend Dev | TEAM-013 | 4h (2 SP) | To Do |
| TEAM-017 | Apply Team Scoping to Manager Time Entry Views | Backend | Backend Dev | TEAM-013, TEAM-016 | 4h (2 SP) | To Do |
| TEAM-018 | Apply Team Scoping to Chart Endpoints | Backend | Backend Dev | TEAM-013 | 3h (2 SP) | To Do |
| TEAM-019 | Modify DeletionService for Team Cleanup | Backend | Backend Dev | TEAM-003 | 2h (1 SP) | To Do |
| TEAM-020 | TypeScript Types for Team Models and API Responses | Frontend | Frontend Dev | TEAM-010 | 2h (1 SP) | To Do |
| TEAM-021 | Pinia Store -- useTeams.ts | Frontend | Frontend Dev | TEAM-020 | 5h (3 SP) | To Do |
| TEAM-022 | Frontend Permission Utility Functions for Teams | Frontend | Frontend Dev | TEAM-006 | 1h (1 SP) | To Do |
| TEAM-023 | Teams Page (Vue + Inertia) with Sidebar Navigation | Frontend | Frontend Dev | TEAM-021, TEAM-022 | 8h (5 SP) | To Do |
| TEAM-024 | Team Detail View Components (Tabs, Modals, Badge) | Frontend | Frontend Dev | TEAM-023 | 10h (5 SP) | To Do |
| TEAM-025 | Team Filter Dropdown in Reports UI | Frontend | Frontend Dev | TEAM-021, TEAM-016 | 5h (3 SP) | To Do |
| TEAM-026 | API Endpoint Tests -- TeamEndpointTest (PHPUnit) | Backend / Testing | QA / Backend Dev | TEAM-008, TEAM-011 | 10h (5 SP) | To Do |
| TEAM-027 | Team Scoping Integration Tests | Backend / Testing | QA / Backend Dev | TEAM-014, TEAM-015, TEAM-016, TEAM-017 | 8h (3 SP) | To Do |
| TEAM-028 | TeamService Unit Tests | Backend / Testing | QA / Backend Dev | TEAM-007 | 4h (2 SP) | To Do |
| TEAM-029 | Data Migration Test | Backend / Testing | QA / Backend Dev | TEAM-002 | 3h (1 SP) | To Do |
| TEAM-030 | Frontend Component Tests (Vitest) | Frontend / Testing | QA / Frontend Dev | TEAM-023, TEAM-024 | 6h (3 SP) | To Do |
| TEAM-031 | E2E Tests (Playwright) | Frontend / Testing | QA / Frontend Dev | TEAM-024, TEAM-025 | 6h (3 SP) | To Do |
| TEAM-032 | Update OpenAPI Spec and Regenerate TypeScript Client | Backend + Frontend | Backend Dev | TEAM-008, TEAM-010, TEAM-011 | 3h (1 SP) | To Do |

---

## Summary Statistics

| Metric | Value |
|--------|-------|
| Total Tasks | 32 |
| Total Story Points | 76 SP |
| Total Effort Hours | ~152h |
| Backend Tasks | 24 |
| Frontend Tasks | 9 |
| Critical Path Length | TEAM-001 -> TEAM-003 -> TEAM-005 -> TEAM-013 -> TEAM-017 -> TEAM-027 (16 SP, ~31h) |
| Estimated Duration | 6 weeks (3 sprints) |
| Parallel Tracks | 3 (Backend Track A, Backend Track B, Frontend Track C) |

---

## Execution Order (Dependency-Respecting)

### Wave 1: No Dependencies (Can Start Immediately)
- TEAM-001: DB Migrations
- TEAM-012: Custom Exceptions

### Wave 2: After TEAM-001
- TEAM-002: Data Migration
- TEAM-003: Eloquent Models

### Wave 3: After TEAM-003
- TEAM-004: Model Factories
- TEAM-005: Existing Model Relations
- TEAM-006: Permissions
- TEAM-010: API Resources
- TEAM-019: DeletionService

### Wave 4: After TEAM-003 + TEAM-005
- TEAM-007: TeamService

### Wave 5: After TEAM-006 + TEAM-007
- TEAM-008: TeamController
- TEAM-013: TeamScope Service

### Wave 6: After TEAM-008
- TEAM-009: Request Validation
- TEAM-011: Routes

### Wave 7: After TEAM-013
- TEAM-014: Project Scoping
- TEAM-015: Client Scoping
- TEAM-016: TimeEntry Filter
- TEAM-018: Chart Scoping

### Wave 8: After TEAM-013 + TEAM-016
- TEAM-017: Manager Scoping

### Wave 9: Frontend (After TEAM-010)
- TEAM-020: TS Types

### Wave 10: Frontend (After TEAM-020 + TEAM-006)
- TEAM-021: Pinia Store
- TEAM-022: FE Permissions

### Wave 11: Frontend (After TEAM-021 + TEAM-022)
- TEAM-023: Teams Page

### Wave 12: Frontend (After TEAM-023)
- TEAM-024: Team Detail View

### Wave 13: Frontend (After TEAM-021 + TEAM-016)
- TEAM-025: Report Filter

### Wave 14: Testing (After respective implementation tasks)
- TEAM-026: API Tests (after TEAM-008, TEAM-011)
- TEAM-027: Scoping Tests (after TEAM-014, TEAM-015, TEAM-016, TEAM-017)
- TEAM-028: Service Tests (after TEAM-007)
- TEAM-029: Migration Tests (after TEAM-002)
- TEAM-030: FE Component Tests (after TEAM-023, TEAM-024)
- TEAM-031: E2E Tests (after TEAM-024, TEAM-025)

### Wave 15: Final
- TEAM-032: OpenAPI (after TEAM-008, TEAM-010, TEAM-011)

---

## Status Logic

- **To Do**: Default status. Tasks whose dependencies are To Do, In Progress, or Completed.
- **Blocked**: Tasks where a dependency is blocked by external constraints (e.g., third-party delays). Not applicable if dependency is simply To Do.
- **In Progress**: Actively being worked on.
- **Completed**: Task is done and verified.

All tasks are currently **To Do** as no implementation has started.

---

## Notes

- TEAM-012 (Exceptions) has no dependencies and can be built at any time, but is listed in Wave 1 for convenience.
- Frontend tasks (Wave 9+) can begin in parallel with backend Waves 4-8 as long as the TypeScript types (TEAM-020) are available. If the OpenAPI spec is generated early (TEAM-032), TEAM-020 can be automated.
- Testing tasks are listed last but individual test files can be written incrementally alongside implementation.
- The critical path runs through: TEAM-001 -> TEAM-003 -> TEAM-005 -> TEAM-013 -> TEAM-016/017 -> TEAM-027, totaling approximately 31 hours of sequential work. Parallel tracks reduce the calendar time significantly.
