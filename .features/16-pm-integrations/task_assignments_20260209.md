# Task Assignments: PM Tool Integrations (Jira, Asana, Trello)

Generated: 2026-02-09
PRD Reference: `/home/keven/Documents/solidtime-analysis/.features/16-pm-integrations/PRD.md`

---

## Task Assignment Table

| Task ID  | Description                                                    | Type               | Assigned Sub-Agent  | Dependencies                      | Effort   | Status |
|----------|----------------------------------------------------------------|---------------------|---------------------|-----------------------------------|----------|--------|
| PMI-001  | Create database migrations for integration tables              | Backend             | Backend Dev         | None                              | 8 hours  | To Do  |
| PMI-002  | Create Eloquent models for integration entities                | Backend             | Backend Dev         | PMI-001                           | 8 hours  | To Do  |
| PMI-003  | Create IntegrationAdapterInterface and base adapter class      | Backend             | Backend Dev         | None                              | 4 hours  | To Do  |
| PMI-004  | Implement JiraAdapter with OAuth 2.0 and API methods           | Backend             | Backend Dev         | PMI-003                           | 16 hours | To Do  |
| PMI-005  | Implement AsanaAdapter with OAuth 2.0 and API methods          | Backend             | Backend Dev         | PMI-003                           | 16 hours | To Do  |
| PMI-006  | Implement TrelloAdapter with API key auth and methods          | Backend             | Backend Dev         | PMI-003                           | 12 hours | To Do  |
| PMI-007  | Create IntegrationService (connection lifecycle, sync)         | Backend             | Backend Dev         | PMI-002, PMI-003                  | 16 hours | To Do  |
| PMI-008  | Create PmIntegrationController with endpoint stubs               | Backend             | Backend Dev         | PMI-002                           | 4 hours  | To Do  |
| PMI-009  | Create IntegrationProjectController                            | Backend             | Backend Dev         | PMI-002                           | 4 hours  | To Do  |
| PMI-010  | Create PmWebhookController for Jira and Asana                    | Backend             | Backend Dev         | PMI-004, PMI-005                  | 8 hours  | To Do  |
| PMI-011  | Create request validation classes                              | Backend             | Backend Dev         | PMI-008, PMI-009                  | 4 hours  | To Do  |
| PMI-012  | Register API routes for integration endpoints                  | Backend             | Backend Dev         | PMI-008, PMI-009, PMI-010        | 2 hours  | To Do  |
| PMI-013  | Wire PmIntegrationController to IntegrationService               | Backend             | Backend Dev         | PMI-007, PMI-008, PMI-011        | 8 hours  | To Do  |
| PMI-014  | Wire IntegrationProjectController to IntegrationService        | Backend             | Backend Dev         | PMI-007, PMI-009, PMI-011        | 4 hours  | To Do  |
| PMI-015  | Wire PmWebhookController to IntegrationService                   | Backend             | Backend Dev         | PMI-007, PMI-010                  | 4 hours  | To Do  |
| PMI-016  | Register integration permissions (IntegrationPermissions)      | Backend             | Backend Dev         | None                              | 2 hours  | To Do  |
| PMI-017  | Create SyncIntegrationJob (background sync)                    | Backend             | Backend Dev         | PMI-007, PMI-004, PMI-005, PMI-006 | 8 hours  | To Do  |
| PMI-018  | Create ExportTimeEntriesJob (time entry write-back)            | Backend             | Backend Dev         | PMI-007, PMI-004, PMI-005, PMI-006 | 8 hours  | To Do  |
| PMI-019  | Create RefreshIntegrationTokenJob (scheduled token refresh)    | Backend             | Backend Dev         | PMI-007                           | 4 hours  | To Do  |
| PMI-020  | Register scheduled jobs in Console Kernel                      | Backend             | Backend Dev         | PMI-017, PMI-018, PMI-019        | 2 hours  | To Do  |
| PMI-021  | Update OpenAPI spec and regenerate TypeScript client            | Backend / Docs      | Backend Dev         | PMI-012, PMI-013, PMI-014        | 6 hours  | To Do  |
| PMI-022  | Create TypeScript types for integration entities               | Frontend            | Frontend Dev        | PMI-021                           | 4 hours  | To Do  |
| PMI-023  | Create useIntegrationStore Pinia store                         | Frontend            | Frontend Dev        | PMI-022                           | 8 hours  | To Do  |
| PMI-024  | Create Integrations.vue settings page                          | Frontend            | Frontend Dev        | PMI-023                           | 8 hours  | To Do  |
| PMI-025  | Create IntegrationCard.vue component                           | Frontend            | Frontend Dev        | PMI-024                           | 6 hours  | To Do  |
| PMI-026  | Create IntegrationDetail.vue component                         | Frontend            | Frontend Dev        | PMI-024, PMI-023                  | 8 hours  | To Do  |
| PMI-027  | Create ProjectSyncPanel.vue (project selection, toggle)        | Frontend            | Frontend Dev        | PMI-024, PMI-023                  | 8 hours  | To Do  |
| PMI-028  | Create SyncHistoryLog.vue component                            | Frontend            | Frontend Dev        | PMI-026                           | 4 hours  | To Do  |
| PMI-029  | Create OAuthCallbackHandler.vue (redirect handling)            | Frontend            | Frontend Dev        | PMI-023                           | 4 hours  | To Do  |
| PMI-030  | Add external reference badges to task selector and time entry  | Frontend            | Frontend Dev        | PMI-022                           | 8 hours  | To Do  |
| PMI-031  | Add Integrations link to Organization Settings navigation      | Frontend            | Frontend Dev        | PMI-024                           | 2 hours  | To Do  |
| PMI-032  | Add web route for Integrations page                            | Frontend            | Frontend Dev        | PMI-024                           | 1 hour   | To Do  |
| PMI-033  | Create backend endpoint tests for PmIntegrationController        | Testing             | Backend QA          | PMI-013, PMI-012                  | 8 hours  | To Do  |
| PMI-034  | Create backend endpoint tests for IntegrationProjectController | Testing             | Backend QA          | PMI-014, PMI-012                  | 4 hours  | To Do  |
| PMI-035  | Create backend endpoint tests for PmWebhookController            | Testing             | Backend QA          | PMI-015, PMI-012                  | 6 hours  | To Do  |
| PMI-036  | Create unit tests for IntegrationService                       | Testing             | Backend QA          | PMI-007                           | 8 hours  | To Do  |
| PMI-037  | Create unit tests for JiraAdapter                              | Testing             | Backend QA          | PMI-004                           | 6 hours  | To Do  |
| PMI-038  | Create unit tests for AsanaAdapter                             | Testing             | Backend QA          | PMI-005                           | 6 hours  | To Do  |
| PMI-039  | Create unit tests for TrelloAdapter                            | Testing             | Backend QA          | PMI-006                           | 4 hours  | To Do  |
| PMI-040  | Create unit tests for sync and export jobs                     | Testing             | Backend QA          | PMI-017, PMI-018, PMI-019        | 6 hours  | To Do  |
| PMI-041  | Create frontend component tests with Vitest                    | Testing             | Frontend QA         | PMI-025, PMI-026, PMI-027, PMI-028 | 8 hours  | To Do  |
| PMI-042  | Create E2E Playwright tests for integration management         | Testing             | QA                  | PMI-031, PMI-032, PMI-025, PMI-026, PMI-027 | 8 hours  | To Do  |
| PMI-043  | Add JSDoc comments to Pinia store and TypeScript types         | Docs                | Frontend Dev        | PMI-023, PMI-022                  | 3 hours  | To Do  |
| PMI-044  | Create environment variable documentation                      | Docs                | Backend Dev         | PMI-004, PMI-005, PMI-006        | 2 hours  | To Do  |

---

## Summary

| Metric                    | Value        |
|---------------------------|--------------|
| Total Tasks               | 44           |
| Total Effort              | 288 hours    |
| Total Story Points        | ~192 SP      |
| Estimated Duration        | 5 sprints (10 weeks) |
| Backend Tasks             | 21           |
| Frontend Tasks            | 11           |
| Testing Tasks             | 10           |
| Documentation Tasks       | 2            |

---

## Sprint Allocation

### Sprint 1 (Weeks 1-2): Backend Foundation -- Database, Models, Framework, Permissions

| Task ID  | Description                                              | Assignee         | SP  |
|----------|----------------------------------------------------------|------------------|-----|
| PMI-001  | Create database migrations for integration tables        | Backend Dev      | 5   |
| PMI-002  | Create Eloquent models for integration entities          | Backend Dev      | 5   |
| PMI-003  | Create IntegrationAdapterInterface and base adapter      | Backend Dev      | 3   |
| PMI-016  | Register integration permissions                         | Backend Dev      | 1   |
| PMI-008  | Create PmIntegrationController with endpoint stubs         | Backend Dev      | 3   |
| PMI-009  | Create IntegrationProjectController                      | Backend Dev      | 3   |
| PMI-011  | Create request validation classes                        | Backend Dev      | 3   |
| PMI-007  | Create IntegrationService (partial: connection lifecycle)| Backend Dev      | 10  |
| **Total** |                                                          |                  | **33** |

### Sprint 2 (Weeks 3-4): Provider Adapters, Controller Wiring, Routes

| Task ID  | Description                                              | Assignee         | SP  |
|----------|----------------------------------------------------------|------------------|-----|
| PMI-004  | Implement JiraAdapter with OAuth 2.0 and API methods     | Backend Dev      | 10  |
| PMI-005  | Implement AsanaAdapter with OAuth 2.0 and API methods    | Backend Dev      | 10  |
| PMI-006  | Implement TrelloAdapter with API key auth and methods    | Backend Dev      | 8   |
| PMI-010  | Create PmWebhookController for Jira and Asana              | Backend Dev      | 5   |
| PMI-012  | Register API routes for integration endpoints            | Backend Dev      | 1   |
| PMI-013  | Wire PmIntegrationController to IntegrationService         | Backend Dev      | 5   |
| PMI-014  | Wire IntegrationProjectController                        | Backend Dev      | 3   |
| PMI-015  | Wire PmWebhookController to IntegrationService             | Backend Dev      | 3   |
| **Total** |                                                          |                  | **45** |

### Sprint 3 (Weeks 5-6): Background Jobs, OpenAPI, Frontend Foundation

| Task ID  | Description                                              | Assignee         | SP  |
|----------|----------------------------------------------------------|------------------|-----|
| PMI-017  | Create SyncIntegrationJob                                | Backend Dev      | 5   |
| PMI-018  | Create ExportTimeEntriesJob                              | Backend Dev      | 5   |
| PMI-019  | Create RefreshIntegrationTokenJob                        | Backend Dev      | 3   |
| PMI-020  | Register scheduled jobs in Console Kernel                | Backend Dev      | 1   |
| PMI-021  | Update OpenAPI spec and regenerate TS client             | Backend Dev      | 4   |
| PMI-022  | Create TypeScript types for integration entities         | Frontend Dev     | 3   |
| PMI-023  | Create useIntegrationStore Pinia store                   | Frontend Dev     | 5   |
| PMI-024  | Create Integrations.vue settings page                    | Frontend Dev     | 5   |
| PMI-029  | Create OAuthCallbackHandler.vue                          | Frontend Dev     | 3   |
| PMI-044  | Create environment variable documentation                | Backend Dev      | 1   |
| **Total** |                                                          |                  | **35** |

### Sprint 4 (Weeks 7-8): Frontend Components, External Reference UI

| Task ID  | Description                                              | Assignee         | SP  |
|----------|----------------------------------------------------------|------------------|-----|
| PMI-025  | Create IntegrationCard.vue component                     | Frontend Dev     | 4   |
| PMI-026  | Create IntegrationDetail.vue component                   | Frontend Dev     | 5   |
| PMI-027  | Create ProjectSyncPanel.vue                              | Frontend Dev     | 5   |
| PMI-028  | Create SyncHistoryLog.vue component                      | Frontend Dev     | 3   |
| PMI-030  | Add external reference badges to task/time entry UI      | Frontend Dev     | 5   |
| PMI-031  | Add Integrations link to Org Settings nav                | Frontend Dev     | 1   |
| PMI-032  | Add web route for Integrations page                      | Frontend Dev     | 1   |
| PMI-043  | Add JSDoc comments to Pinia store and TS types           | Frontend Dev     | 2   |
| PMI-036  | Create unit tests for IntegrationService                 | Backend QA       | 5   |
| PMI-037  | Create unit tests for JiraAdapter                        | Backend QA       | 4   |
| **Total** |                                                          |                  | **35** |

### Sprint 5 (Weeks 9-10): Testing, Polish, Documentation

| Task ID  | Description                                              | Assignee         | SP  |
|----------|----------------------------------------------------------|------------------|-----|
| PMI-033  | Backend endpoint tests for PmIntegrationController         | Backend QA       | 5   |
| PMI-034  | Backend endpoint tests for IntegrationProjectController  | Backend QA       | 3   |
| PMI-035  | Backend endpoint tests for PmWebhookController             | Backend QA       | 4   |
| PMI-038  | Create unit tests for AsanaAdapter                       | Backend QA       | 4   |
| PMI-039  | Create unit tests for TrelloAdapter                      | Backend QA       | 3   |
| PMI-040  | Create unit tests for sync and export jobs               | Backend QA       | 4   |
| PMI-041  | Create frontend component tests with Vitest              | Frontend QA      | 5   |
| PMI-042  | Create E2E Playwright tests                              | QA               | 5   |
| **Total** |                                                          |                  | **33** |

---

## Dependency Graph

```
Wave 1 (No dependencies -- can start immediately):
    PMI-001, PMI-003, PMI-016

Wave 2 (depends on Wave 1):
    PMI-002 (depends on PMI-001)
    PMI-004 (depends on PMI-003)
    PMI-005 (depends on PMI-003)
    PMI-006 (depends on PMI-003)

Wave 3:
    PMI-007 (depends on PMI-002, PMI-003)
    PMI-008 (depends on PMI-002)
    PMI-009 (depends on PMI-002)
    PMI-010 (depends on PMI-004, PMI-005)

Wave 4:
    PMI-011 (depends on PMI-008, PMI-009)
    PMI-013 (depends on PMI-007, PMI-008, PMI-011)
    PMI-014 (depends on PMI-007, PMI-009, PMI-011)
    PMI-015 (depends on PMI-007, PMI-010)
    PMI-017 (depends on PMI-007, PMI-004, PMI-005, PMI-006)
    PMI-018 (depends on PMI-007, PMI-004, PMI-005, PMI-006)
    PMI-019 (depends on PMI-007)

Wave 5:
    PMI-012 (depends on PMI-008, PMI-009, PMI-010)
    PMI-020 (depends on PMI-017, PMI-018, PMI-019)
    PMI-021 (depends on PMI-012, PMI-013, PMI-014)
    PMI-036 (depends on PMI-007)
    PMI-037 (depends on PMI-004)
    PMI-038 (depends on PMI-005)
    PMI-039 (depends on PMI-006)
    PMI-040 (depends on PMI-017, PMI-018, PMI-019)
    PMI-044 (depends on PMI-004, PMI-005, PMI-006)

Wave 6:
    PMI-022 (depends on PMI-021)
    PMI-033 (depends on PMI-013, PMI-012)
    PMI-034 (depends on PMI-014, PMI-012)
    PMI-035 (depends on PMI-015, PMI-012)

Wave 7:
    PMI-023 (depends on PMI-022)
    PMI-030 (depends on PMI-022)

Wave 8:
    PMI-024 (depends on PMI-023)
    PMI-029 (depends on PMI-023)
    PMI-043 (depends on PMI-023, PMI-022)

Wave 9:
    PMI-025 (depends on PMI-024)
    PMI-026 (depends on PMI-024, PMI-023)
    PMI-027 (depends on PMI-024, PMI-023)
    PMI-031 (depends on PMI-024)
    PMI-032 (depends on PMI-024)

Wave 10:
    PMI-028 (depends on PMI-026)
    PMI-041 (depends on PMI-025, PMI-026, PMI-027, PMI-028)
    PMI-042 (depends on PMI-031, PMI-032, PMI-025, PMI-026, PMI-027)
```

### Critical Path

```
PMI-001 -> PMI-002 -> PMI-007 -> PMI-013 -> PMI-021 -> PMI-022 -> PMI-023 -> PMI-024 -> PMI-026 -> PMI-028 -> PMI-042
                                                                                                   -> PMI-041
```

**Critical path duration**: ~80 hours (8 + 8 + 16 + 8 + 6 + 4 + 8 + 8 + 8 + 4 + 8)

### Parallel Work Streams

**Backend Stream A** (Adapters): PMI-003 -> PMI-004, PMI-005, PMI-006 (parallel) -> PMI-010, PMI-017, PMI-018
**Backend Stream B** (Core): PMI-001 -> PMI-002 -> PMI-007, PMI-008, PMI-009 -> PMI-011 -> PMI-013, PMI-014, PMI-015
**Frontend Stream**: PMI-022 -> PMI-023 -> PMI-024 -> PMI-025, PMI-026, PMI-027, PMI-029, PMI-030, PMI-031, PMI-032
**Testing Stream**: PMI-036, PMI-037, PMI-038, PMI-039, PMI-040 (after their dependencies) -> PMI-033, PMI-034, PMI-035 -> PMI-041, PMI-042

Backend Streams A and B can run in parallel from Sprint 1, converging at PMI-013/PMI-017 in Sprint 2-3.

---

## Dependency Risk Assessment

| Risk | Affected Tasks | Mitigation |
|------|---------------|------------|
| PMI-001 delays block all model and service work | PMI-002, PMI-007, PMI-008, PMI-009 | Prioritize PMI-001 first day of Sprint 1; migration is straightforward SQL |
| PMI-003 delays block all adapter work | PMI-004, PMI-005, PMI-006 | PMI-003 is a small interface definition (4h); start in parallel with PMI-001 |
| PMI-004/PMI-005 delays block webhook controller | PMI-010 | Adapters can be developed in parallel by different devs; mock adapter for webhook work |
| PMI-007 is a large task (16h) that blocks many downstream tasks | PMI-013, PMI-014, PMI-015, PMI-017-019 | Split PMI-007 into connection lifecycle (8h) and sync orchestration (8h); allow wiring to start after connection lifecycle is done |
| PMI-021 (OpenAPI) gates all frontend work | PMI-022 through PMI-032 | Can start frontend TypeScript types manually before OpenAPI generation is complete |
| All frontend components depend on PMI-024 (page) | PMI-025-032 | Page scaffold is simple (8h); create minimal layout first, detail later |
