# Task Assignments: Timesheet Approvals

Generated: 2026-02-06
PRD Reference: `/home/keven/Documents/solidtime-analysis/.features/01-timesheet-approvals/PRD.md`

---

## Task Assignment Table

| Task ID   | Description                                                  | Type                | Assigned Sub-Agent   | Dependencies          | Effort   | Status |
|-----------|--------------------------------------------------------------|---------------------|----------------------|-----------------------|----------|--------|
| TASK-001  | Create TimesheetApprovalStatus Enum                          | Backend             | Backend Dev          | None                  | 1 hour   | To Do  |
| TASK-002  | Create Database Migration for timesheet_approvals Table      | Backend / Database  | Backend Dev          | TASK-001              | 2 hours  | To Do  |
| TASK-003  | Create TimesheetApproval Model + Factory                     | Backend             | Backend Dev          | TASK-001, TASK-002    | 4 hours  | To Do  |
| TASK-004  | Add Organization Reminder Settings Migration                 | Backend / Database  | Backend Dev          | None                  | 1 hour   | To Do  |
| TASK-005  | Register New Permissions in JetstreamServiceProvider         | Backend             | Backend Dev          | None                  | 2 hours  | To Do  |
| TASK-006  | Create TimesheetApprovalService                              | Backend             | Backend Dev          | TASK-001, TASK-002, TASK-003, TASK-005 | 16 hours | To Do  |
| TASK-007  | Enhance TimesheetService with Lock Checks                    | Backend             | Backend Dev          | TASK-006              | 6 hours  | To Do  |
| TASK-008  | Enhance TimeEntryController with Lock Checks                 | Backend             | Backend Dev          | TASK-006, TASK-007    | 4 hours  | To Do  |
| TASK-009  | Create TimesheetApprovalController                           | Backend             | Backend Dev          | TASK-005, TASK-006    | 8 hours  | To Do  |
| TASK-010  | Create Request Validation Classes                            | Backend             | Backend Dev          | TASK-003              | 4 hours  | To Do  |
| TASK-011  | Register API Routes                                          | Backend             | Backend Dev          | TASK-009              | 1 hour   | To Do  |
| TASK-012  | Create Notification Mail Classes                             | Backend             | Backend Dev          | TASK-006              | 6 hours  | To Do  |
| TASK-013  | Dispatch Notifications from TimesheetApprovalService         | Backend             | Backend Dev          | TASK-006, TASK-012    | 3 hours  | To Do  |
| TASK-014  | Create TimesheetReminderCommand                              | Backend             | Backend Dev          | TASK-003, TASK-004, TASK-012 | 8 hours  | To Do  |
| TASK-015  | Add TypeScript Types for Approval                            | Frontend            | Frontend Dev         | None                  | 2 hours  | To Do  |
| TASK-016  | Enhance useTimesheetStore with Approval Actions              | Frontend            | Frontend Dev         | TASK-015              | 8 hours  | To Do  |
| TASK-017  | Add Submit/Withdraw UI to TimesheetWeekAccordion             | Frontend            | Frontend Dev         | TASK-016              | 8 hours  | To Do  |
| TASK-018  | Disable Cell Editing When Week is Locked                     | Frontend            | Frontend Dev         | TASK-016              | 4 hours  | To Do  |
| TASK-019  | Create useApprovalsStore Pinia Store                         | Frontend            | Frontend Dev         | TASK-015              | 6 hours  | To Do  |
| TASK-020  | Create Approvals Vue Page                                    | Frontend            | Frontend Dev         | TASK-019              | 12 hours | To Do  |
| TASK-021  | Add Sidebar Navigation Item for Approvals                    | Frontend            | Frontend Dev         | TASK-020              | 1 hour   | To Do  |
| TASK-022  | Add Approval Settings to Organization Settings Page          | Full-stack          | Full-stack Dev       | TASK-004              | 6 hours  | To Do  |
| TASK-023  | TimesheetApprovalService Unit Tests                          | Testing             | Backend QA           | TASK-006              | 12 hours | To Do  |
| TASK-024  | TimesheetApprovalController API Endpoint Tests               | Testing             | Backend QA           | TASK-009, TASK-011    | 16 hours | To Do  |
| TASK-025  | Enhanced TimesheetEndpointTest (Lock Checks)                 | Testing             | Backend QA           | TASK-007, TASK-008    | 6 hours  | To Do  |
| TASK-026  | Notification Mail Tests                                      | Testing             | Backend QA           | TASK-012, TASK-013    | 4 hours  | To Do  |
| TASK-027  | Reminder Command Test                                        | Testing             | Backend QA           | TASK-014              | 4 hours  | To Do  |
| TASK-028  | Frontend Component Tests                                     | Testing             | Frontend QA          | TASK-017, TASK-020    | 8 hours  | To Do  |
| TASK-029  | E2E Playwright Tests                                         | Testing             | QA                   | TASK-017, TASK-020    | 12 hours | To Do  |
| TASK-030  | Update OpenAPI Specification                                 | Backend / Docs      | Backend Dev          | TASK-009, TASK-011    | 4 hours  | To Do  |

---

## Summary

| Metric                    | Value       |
|---------------------------|-------------|
| Total Tasks               | 30          |
| Total Effort              | 177 hours   |
| Total Story Points        | ~117 SP     |
| Estimated Duration        | 4 sprints (8 weeks) |
| Backend Tasks             | 14          |
| Frontend Tasks            | 7           |
| Full-stack Tasks          | 1           |
| Testing Tasks             | 7           |
| Documentation Tasks       | 1           |

---

## Sprint Allocation

### Sprint 1 (Weeks 1-2): Foundation + Core Backend

| Task ID   | Description                                              | Assignee         | SP  |
|-----------|----------------------------------------------------------|------------------|-----|
| TASK-001  | Create TimesheetApprovalStatus Enum                      | Backend Dev      | 1   |
| TASK-002  | Create Database Migration for timesheet_approvals        | Backend Dev      | 2   |
| TASK-003  | Create TimesheetApproval Model + Factory                 | Backend Dev      | 3   |
| TASK-004  | Add Organization Reminder Settings Migration             | Backend Dev      | 1   |
| TASK-005  | Register New Permissions                                 | Backend Dev      | 2   |
| TASK-006  | Create TimesheetApprovalService                          | Backend Dev      | 8   |
| TASK-007  | Enhance TimesheetService with Lock Checks                | Backend Dev      | 5   |
| TASK-008  | Enhance TimeEntryController with Lock Checks             | Backend Dev      | 3   |
| TASK-015  | Add TypeScript Types for Approval                        | Frontend Dev     | 1   |
| **Total** |                                                          |                  | **26** |

### Sprint 2 (Weeks 3-4): API + Notifications + Timesheet UI

| Task ID   | Description                                              | Assignee         | SP  |
|-----------|----------------------------------------------------------|------------------|-----|
| TASK-009  | Create TimesheetApprovalController                       | Backend Dev      | 5   |
| TASK-010  | Create Request Validation Classes                        | Backend Dev      | 3   |
| TASK-011  | Register API Routes                                      | Backend Dev      | 1   |
| TASK-012  | Create Notification Mail Classes                         | Backend Dev      | 5   |
| TASK-013  | Dispatch Notifications from Service                      | Backend Dev      | 2   |
| TASK-016  | Enhance useTimesheetStore with Approval Actions          | Frontend Dev     | 5   |
| TASK-017  | Add Submit/Withdraw UI to Week Accordion                 | Frontend Dev     | 5   |
| TASK-018  | Disable Cell Editing When Week is Locked                 | Frontend Dev     | 3   |
| TASK-023  | TimesheetApprovalService Unit Tests                      | Backend QA       | 8   |
| TASK-025  | Enhanced TimesheetEndpointTest (Lock Checks)             | Backend QA       | 5   |
| **Total** |                                                          |                  | **42** |

### Sprint 3 (Weeks 5-6): Approvals Page + Settings + Tests

| Task ID   | Description                                              | Assignee         | SP  |
|-----------|----------------------------------------------------------|------------------|-----|
| TASK-014  | Create TimesheetReminderCommand                          | Backend Dev      | 5   |
| TASK-019  | Create useApprovalsStore Pinia Store                     | Frontend Dev     | 5   |
| TASK-020  | Create Approvals Vue Page                                | Frontend Dev     | 8   |
| TASK-021  | Add Sidebar Navigation Item                              | Frontend Dev     | 1   |
| TASK-022  | Add Approval Settings to Org Settings                    | Full-stack Dev   | 5   |
| TASK-024  | TimesheetApprovalController Endpoint Tests               | Backend QA       | 8   |
| TASK-026  | Notification Mail Tests                                  | Backend QA       | 3   |
| TASK-027  | Reminder Command Test                                    | Backend QA       | 3   |
| **Total** |                                                          |                  | **38** |

### Sprint 4 (Weeks 7-8): Frontend Tests + E2E + Polish

| Task ID   | Description                                              | Assignee         | SP  |
|-----------|----------------------------------------------------------|------------------|-----|
| TASK-028  | Frontend Component Tests                                 | Frontend QA      | 5   |
| TASK-029  | E2E Playwright Tests                                     | QA               | 8   |
| TASK-030  | Update OpenAPI Specification                             | Backend Dev      | 3   |
| -         | Bug fixes, polish, integration testing                   | All              | ~5  |
| **Total** |                                                          |                  | **~21** |

---

## Dependency Execution Order

The following is the recommended execution order respecting all task dependencies:

**Wave 1 (No dependencies -- can start immediately in parallel):**
- TASK-001: Create TimesheetApprovalStatus Enum
- TASK-004: Add Organization Reminder Settings Migration
- TASK-005: Register New Permissions
- TASK-015: Add TypeScript Types for Approval

**Wave 2 (Depends on Wave 1):**
- TASK-002: Create Database Migration (depends on TASK-001)
- TASK-016: Enhance useTimesheetStore (depends on TASK-015)
- TASK-019: Create useApprovalsStore (depends on TASK-015)

**Wave 3 (Depends on Wave 2):**
- TASK-003: Create TimesheetApproval Model (depends on TASK-001, TASK-002)
- TASK-017: Add Submit/Withdraw UI (depends on TASK-016)
- TASK-018: Disable Cell Editing (depends on TASK-016)
- TASK-020: Create Approvals Vue Page (depends on TASK-019)

**Wave 4 (Depends on Wave 3):**
- TASK-006: Create TimesheetApprovalService (depends on TASK-001, TASK-002, TASK-003, TASK-005)
- TASK-010: Create Request Validation Classes (depends on TASK-003)
- TASK-022: Org Settings UI (depends on TASK-004)
- TASK-021: Sidebar Nav Item (depends on TASK-020)
- TASK-028: Frontend Component Tests (depends on TASK-017, TASK-020)
- TASK-029: E2E Playwright Tests (depends on TASK-017, TASK-020)

**Wave 5 (Depends on Wave 4):**
- TASK-007: Enhance TimesheetService Lock Checks (depends on TASK-006)
- TASK-009: Create TimesheetApprovalController (depends on TASK-005, TASK-006)
- TASK-012: Create Notification Mail Classes (depends on TASK-006)
- TASK-023: Service Unit Tests (depends on TASK-006)

**Wave 6 (Depends on Wave 5):**
- TASK-008: Enhance TimeEntryController Lock Checks (depends on TASK-006, TASK-007)
- TASK-011: Register API Routes (depends on TASK-009)
- TASK-013: Dispatch Notifications (depends on TASK-006, TASK-012)
- TASK-014: Reminder Command (depends on TASK-003, TASK-004, TASK-012)
- TASK-025: Lock Tests (depends on TASK-007, TASK-008)

**Wave 7 (Depends on Wave 6):**
- TASK-024: Endpoint Tests (depends on TASK-009, TASK-011)
- TASK-026: Mail Tests (depends on TASK-012, TASK-013)
- TASK-027: Reminder Tests (depends on TASK-014)
- TASK-030: Update OpenAPI Spec (depends on TASK-009, TASK-011)

---

## Critical Path

The longest dependency chain determining minimum project duration:

```
TASK-001 (1h) -> TASK-002 (2h) -> TASK-003 (4h) -> TASK-006 (16h) -> TASK-009 (8h) -> TASK-011 (1h) -> TASK-024 (16h)
```

**Critical path total: ~48 hours of sequential work**

Secondary critical paths:
- Backend lock path: TASK-001 -> TASK-002 -> TASK-003 -> TASK-006 -> TASK-007 -> TASK-008 -> TASK-025
- Frontend path: TASK-015 -> TASK-016 -> TASK-017 -> TASK-029
- Notification path: TASK-006 -> TASK-012 -> TASK-013 -> TASK-026

---

## Risk Flags

| Task ID   | Risk | Mitigation |
|-----------|------|------------|
| TASK-006  | Largest single task (16h); complex state machine logic | Break into sub-tasks if needed; write tests concurrently |
| TASK-020  | Largest frontend task (12h); many sub-components | Template from existing Pages (e.g., Members.vue); reuse TimesheetGrid |
| TASK-024  | Largest test task (16h); many endpoint permutations | Use test data builders; parameterize where possible |
| TASK-007  | Modifies existing TimesheetService; risk of regression | Run full existing test suite before and after changes |
| TASK-008  | Modifies existing TimeEntryController; high-traffic endpoint | Ensure lock check is O(1) with proper indexing; load test |
