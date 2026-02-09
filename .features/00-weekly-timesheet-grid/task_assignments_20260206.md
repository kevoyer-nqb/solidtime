# Task Assignments: Weekly Timesheet Grid

Generated: 2026-02-06
PRD Reference: `/home/keven/Documents/solidtime-analysis/.features/00-weekly-timesheet-grid/PRD.md`

---

## Task Assignment Table

| Task ID  | Description                                               | Type               | Assigned Sub-Agent  | Dependencies            | Effort  | Status |
|----------|-----------------------------------------------------------|---------------------|---------------------|-------------------------|---------|--------|
| TSG-001  | Create TimesheetController with endpoint stubs            | Backend             | Backend Dev         | None                    | 4 hours | To Do  |
| TSG-002  | Create TimesheetService with business logic               | Backend             | Backend Dev         | None                    | 12 hours| To Do  |
| TSG-003  | Register API routes for timesheet endpoints               | Backend             | Backend Dev         | TSG-001                 | 1 hour  | To Do  |
| TSG-004  | Create request validation classes                         | Backend             | Backend Dev         | TSG-001                 | 4 hours | To Do  |
| TSG-005  | Wire up controller to TimesheetService                    | Backend             | Backend Dev         | TSG-001, TSG-002, TSG-004 | 4 hours | To Do  |
| TSG-006  | Update OpenAPI spec and regenerate TS client              | Backend / Docs      | Backend Dev         | TSG-003, TSG-005        | 4 hours | To Do  |
| TSG-007  | Create Timesheet.vue page with accordion container        | Frontend            | Frontend Dev        | TSG-006                 | 4 hours | To Do  |
| TSG-008  | Create Grid, Cell, RowHeader, Accordion, AddTask components | Frontend         | Frontend Dev        | TSG-007                 | 16 hours| To Do  |
| TSG-009  | Create useTimesheetStore and TypeScript types              | Frontend            | Frontend Dev        | TSG-006                 | 8 hours | To Do  |
| TSG-010  | Add web route and sidebar navigation                      | Frontend            | Frontend Dev        | TSG-007                 | 2 hours | To Do  |
| TSG-011  | Add keyboard navigation and ARIA attributes               | Frontend            | Frontend Dev        | TSG-008                 | 6 hours | To Do  |
| TSG-012  | Add composite database indexes for timesheet queries      | Backend / Perf      | Backend Dev         | TSG-002                 | 2 hours | To Do  |
| TSG-013  | Create backend endpoint tests                             | Testing             | Backend QA          | TSG-005, TSG-003        | 8 hours | To Do  |
| TSG-014  | Set up Vitest infrastructure for component tests          | Testing / Infra     | Frontend Dev        | None                    | 4 hours | To Do  |
| TSG-015  | Add frontend component tests with Vitest                  | Testing             | Frontend QA         | TSG-008, TSG-014        | 6 hours | To Do  |
| TSG-016  | Add E2E Playwright tests for timesheet page               | Testing             | QA                  | TSG-010, TSG-008        | 8 hours | To Do  |
| TSG-017  | Add JSDoc comments to Pinia store                         | Docs                | Frontend Dev        | TSG-009                 | 2 hours | To Do  |
| TSG-018  | Add "Add Last Week's Tasks" functionality                 | Frontend            | Frontend Dev        | TSG-008, TSG-009        | 4 hours | To Do  |
| TSG-019  | Implement optimistic cell updates with error rollback     | Frontend            | Frontend Dev        | TSG-009, TSG-008        | 4 hours | To Do  |
| TSG-020  | Add "Load More" week pagination                           | Full-stack          | Full-stack Dev      | TSG-007, TSG-009        | 4 hours | To Do  |

---

## Summary

| Metric                    | Value       |
|---------------------------|-------------|
| Total Tasks               | 20          |
| Total Effort              | 137 hours   |
| Total Story Points        | ~91 SP      |
| Estimated Duration        | 3 sprints (6 weeks) |
| Backend Tasks             | 7           |
| Frontend Tasks            | 8           |
| Full-stack Tasks          | 1           |
| Testing Tasks             | 3           |
| Documentation Tasks       | 1           |

---

## Sprint Allocation

### Sprint 1 (Weeks 1-2): Backend Foundation

| Task ID  | Description                                              | Assignee         | SP  |
|----------|----------------------------------------------------------|------------------|-----|
| TSG-001  | Create TimesheetController with endpoint stubs           | Backend Dev      | 3   |
| TSG-002  | Create TimesheetService with business logic              | Backend Dev      | 8   |
| TSG-003  | Register API routes for timesheet endpoints              | Backend Dev      | 1   |
| TSG-004  | Create request validation classes                        | Backend Dev      | 3   |
| TSG-005  | Wire up controller to TimesheetService                   | Backend Dev      | 3   |
| TSG-006  | Update OpenAPI spec and regenerate TS client             | Backend Dev      | 3   |
| TSG-012  | Add composite database indexes                           | Backend Dev      | 2   |
| TSG-013  | Create backend endpoint tests                            | Backend QA       | 5   |
| **Total** |                                                          |                  | **28** |

### Sprint 2 (Weeks 3-4): Frontend Implementation

| Task ID  | Description                                              | Assignee         | SP  |
|----------|----------------------------------------------------------|------------------|-----|
| TSG-007  | Create Timesheet.vue page with accordion container       | Frontend Dev     | 3   |
| TSG-008  | Create Grid, Cell, RowHeader, Accordion, AddTask         | Frontend Dev     | 10  |
| TSG-009  | Create useTimesheetStore and TypeScript types             | Frontend Dev     | 5   |
| TSG-010  | Add web route and sidebar navigation                     | Frontend Dev     | 1   |
| TSG-014  | Set up Vitest infrastructure                             | Frontend Dev     | 3   |
| TSG-018  | Add "Add Last Week's Tasks" functionality                | Frontend Dev     | 3   |
| TSG-019  | Implement optimistic cell updates with error rollback    | Frontend Dev     | 3   |
| TSG-020  | Add "Load More" week pagination                          | Full-stack Dev   | 3   |
| **Total** |                                                          |                  | **31** |

### Sprint 3 (Weeks 5-6): Polish, Testing, Documentation

| Task ID  | Description                                              | Assignee         | SP  |
|----------|----------------------------------------------------------|------------------|-----|
| TSG-011  | Add keyboard navigation and ARIA attributes              | Frontend Dev     | 5   |
| TSG-015  | Add frontend component tests with Vitest                 | Frontend QA      | 5   |
| TSG-016  | Add E2E Playwright tests for timesheet page              | QA               | 5   |
| TSG-017  | Add JSDoc comments to Pinia store                        | Frontend Dev     | 1   |
| **Total** |                                                          |                  | **16** |

---

## Dependency Graph

```
Wave 1 (No dependencies — can start immediately):
    TSG-001, TSG-002, TSG-014

Wave 2 (depends on Wave 1):
    TSG-003 (depends on TSG-001)
    TSG-004 (depends on TSG-001)

Wave 3:
    TSG-005 (depends on TSG-001, TSG-002, TSG-004)
    TSG-012 (depends on TSG-002)

Wave 4:
    TSG-006 (depends on TSG-003, TSG-005)
    TSG-013 (depends on TSG-005, TSG-003)

Wave 5:
    TSG-007 (depends on TSG-006)
    TSG-009 (depends on TSG-006)

Wave 6:
    TSG-008 (depends on TSG-007)
    TSG-010 (depends on TSG-007)
    TSG-020 (depends on TSG-007, TSG-009)
    TSG-017 (depends on TSG-009)

Wave 7:
    TSG-011 (depends on TSG-008)
    TSG-015 (depends on TSG-008, TSG-014)
    TSG-016 (depends on TSG-010, TSG-008)
    TSG-018 (depends on TSG-008, TSG-009)
    TSG-019 (depends on TSG-009, TSG-008)
```

### Critical Path

```
TSG-001 → TSG-004 → TSG-005 → TSG-006 → TSG-007 → TSG-008 → TSG-011
                                                              → TSG-016
```

**Critical path duration**: ~38 hours (4 + 4 + 4 + 4 + 4 + 16 + 6)
