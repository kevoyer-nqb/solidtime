# Task Assignments: Timesheet Approvals

Generated: 2026-02-06
PRD Reference: `/home/keven/Documents/solidtime-analysis/.features/01-timesheet-approvals/PRD.md`

---

## Task Assignment Table

| Task ID   | Description                                                  | Type                | Assigned Sub-Agent   | Dependencies          | Effort   | Status |
|-----------|--------------------------------------------------------------|---------------------|----------------------|-----------------------|----------|--------|
| APPR-001  | Create TimesheetApprovalStatus Enum                          | Backend             | Backend Dev          | None                  | 1 hour   | To Do  |
| APPR-002  | Create Database Migration for timesheet_approvals Table      | Backend / Database  | Backend Dev          | APPR-001              | 2 hours  | To Do  |
| APPR-003  | Create TimesheetApproval Model + Factory                     | Backend             | Backend Dev          | APPR-001, APPR-002    | 4 hours  | To Do  |
| APPR-004  | Add Organization Reminder Settings Migration                 | Backend / Database  | Backend Dev          | None                  | 1 hour   | To Do  |
| APPR-005  | Register New Permissions in JetstreamServiceProvider         | Backend             | Backend Dev          | None                  | 2 hours  | To Do  |
| APPR-006  | Create TimesheetApprovalService                              | Backend             | Backend Dev          | APPR-001, APPR-002, APPR-003, APPR-005 | 16 hours | To Do  |
| APPR-007  | Enhance TimesheetService with Lock Checks                    | Backend             | Backend Dev          | APPR-006              | 6 hours  | To Do  |
| APPR-008  | Enhance TimeEntryController with Lock Checks                 | Backend             | Backend Dev          | APPR-006, APPR-007    | 4 hours  | To Do  |
| APPR-009  | Create TimesheetApprovalController                           | Backend             | Backend Dev          | APPR-005, APPR-006    | 8 hours  | To Do  |
| APPR-010  | Create Request Validation Classes                            | Backend             | Backend Dev          | APPR-003              | 4 hours  | To Do  |
| APPR-011  | Register API Routes                                          | Backend             | Backend Dev          | APPR-009              | 1 hour   | To Do  |
| APPR-012  | Create Notification Mail Classes                             | Backend             | Backend Dev          | APPR-006              | 6 hours  | To Do  |
| APPR-013  | Dispatch Notifications from TimesheetApprovalService         | Backend             | Backend Dev          | APPR-006, APPR-012    | 3 hours  | To Do  |
| APPR-014  | Create TimesheetReminderCommand                              | Backend             | Backend Dev          | APPR-003, APPR-004, APPR-012 | 8 hours  | To Do  |
| APPR-015  | Add TypeScript Types for Approval                            | Frontend            | Frontend Dev         | None                  | 2 hours  | To Do  |
| APPR-016  | Enhance useTimesheetStore with Approval Actions              | Frontend            | Frontend Dev         | APPR-015              | 8 hours  | To Do  |
| APPR-017  | Add Submit/Withdraw UI to TimesheetWeekAccordion             | Frontend            | Frontend Dev         | APPR-016              | 8 hours  | To Do  |
| APPR-018  | Disable Cell Editing When Week is Locked                     | Frontend            | Frontend Dev         | APPR-016              | 4 hours  | To Do  |
| APPR-019  | Create useApprovalsStore Pinia Store                         | Frontend            | Frontend Dev         | APPR-015              | 6 hours  | To Do  |
| APPR-020  | Create Approvals Vue Page                                    | Frontend            | Frontend Dev         | APPR-019              | 12 hours | To Do  |
| APPR-021  | Add Sidebar Navigation Item for Approvals                    | Frontend            | Frontend Dev         | APPR-020              | 1 hour   | To Do  |
| APPR-022  | Add Approval Settings to Organization Settings Page          | Full-stack          | Full-stack Dev       | APPR-004              | 6 hours  | To Do  |
| APPR-023  | TimesheetApprovalService Unit Tests                          | Testing             | Backend QA           | APPR-006              | 12 hours | To Do  |
| APPR-024  | TimesheetApprovalController API Endpoint Tests               | Testing             | Backend QA           | APPR-009, APPR-011    | 16 hours | To Do  |
| APPR-025  | Enhanced TimesheetEndpointTest (Lock Checks)                 | Testing             | Backend QA           | APPR-007, APPR-008    | 6 hours  | To Do  |
| APPR-026  | Notification Mail Tests                                      | Testing             | Backend QA           | APPR-012, APPR-013    | 4 hours  | To Do  |
| APPR-027  | Reminder Command Test                                        | Testing             | Backend QA           | APPR-014              | 4 hours  | To Do  |
| APPR-028  | Frontend Component Tests                                     | Testing             | Frontend QA          | APPR-017, APPR-020    | 8 hours  | To Do  |
| APPR-029  | E2E Playwright Tests                                         | Testing             | QA                   | APPR-017, APPR-020    | 12 hours | To Do  |
| APPR-030  | Update OpenAPI Specification                                 | Backend / Docs      | Backend Dev          | APPR-009, APPR-011    | 4 hours  | To Do  |

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
| APPR-001  | Create TimesheetApprovalStatus Enum                      | Backend Dev      | 1   |
| APPR-002  | Create Database Migration for timesheet_approvals        | Backend Dev      | 2   |
| APPR-003  | Create TimesheetApproval Model + Factory                 | Backend Dev      | 3   |
| APPR-004  | Add Organization Reminder Settings Migration             | Backend Dev      | 1   |
| APPR-005  | Register New Permissions                                 | Backend Dev      | 2   |
| APPR-006  | Create TimesheetApprovalService                          | Backend Dev      | 8   |
| APPR-007  | Enhance TimesheetService with Lock Checks                | Backend Dev      | 5   |
| APPR-008  | Enhance TimeEntryController with Lock Checks             | Backend Dev      | 3   |
| APPR-015  | Add TypeScript Types for Approval                        | Frontend Dev     | 1   |
| **Total** |                                                          |                  | **26** |

### Sprint 2 (Weeks 3-4): API + Notifications + Timesheet UI

| Task ID   | Description                                              | Assignee         | SP  |
|-----------|----------------------------------------------------------|------------------|-----|
| APPR-009  | Create TimesheetApprovalController                       | Backend Dev      | 5   |
| APPR-010  | Create Request Validation Classes                        | Backend Dev      | 3   |
| APPR-011  | Register API Routes                                      | Backend Dev      | 1   |
| APPR-012  | Create Notification Mail Classes                         | Backend Dev      | 5   |
| APPR-013  | Dispatch Notifications from Service                      | Backend Dev      | 2   |
| APPR-016  | Enhance useTimesheetStore with Approval Actions          | Frontend Dev     | 5   |
| APPR-017  | Add Submit/Withdraw UI to Week Accordion                 | Frontend Dev     | 5   |
| APPR-018  | Disable Cell Editing When Week is Locked                 | Frontend Dev     | 3   |
| APPR-023  | TimesheetApprovalService Unit Tests                      | Backend QA       | 8   |
| APPR-025  | Enhanced TimesheetEndpointTest (Lock Checks)             | Backend QA       | 5   |
| **Total** |                                                          |                  | **42** |

### Sprint 3 (Weeks 5-6): Approvals Page + Settings + Tests

| Task ID   | Description                                              | Assignee         | SP  |
|-----------|----------------------------------------------------------|------------------|-----|
| APPR-014  | Create TimesheetReminderCommand                          | Backend Dev      | 5   |
| APPR-019  | Create useApprovalsStore Pinia Store                     | Frontend Dev     | 5   |
| APPR-020  | Create Approvals Vue Page                                | Frontend Dev     | 8   |
| APPR-021  | Add Sidebar Navigation Item                              | Frontend Dev     | 1   |
| APPR-022  | Add Approval Settings to Org Settings                    | Full-stack Dev   | 5   |
| APPR-024  | TimesheetApprovalController Endpoint Tests               | Backend QA       | 8   |
| APPR-026  | Notification Mail Tests                                  | Backend QA       | 3   |
| APPR-027  | Reminder Command Test                                    | Backend QA       | 3   |
| **Total** |                                                          |                  | **38** |

### Sprint 4 (Weeks 7-8): Frontend Tests + E2E + Polish

| Task ID   | Description                                              | Assignee         | SP  |
|-----------|----------------------------------------------------------|------------------|-----|
| APPR-028  | Frontend Component Tests                                 | Frontend QA      | 5   |
| APPR-029  | E2E Playwright Tests                                     | QA               | 8   |
| APPR-030  | Update OpenAPI Specification                             | Backend Dev      | 3   |
| -         | Bug fixes, polish, integration testing                   | All              | ~5  |
| **Total** |                                                          |                  | **~21** |

---

## Dependency Execution Order

The following is the recommended execution order respecting all task dependencies:

**Wave 1 (No dependencies -- can start immediately in parallel):**
- APPR-001: Create TimesheetApprovalStatus Enum
- APPR-004: Add Organization Reminder Settings Migration
- APPR-005: Register New Permissions
- APPR-015: Add TypeScript Types for Approval

**Wave 2 (Depends on Wave 1):**
- APPR-002: Create Database Migration (depends on APPR-001)
- APPR-016: Enhance useTimesheetStore (depends on APPR-015)
- APPR-019: Create useApprovalsStore (depends on APPR-015)

**Wave 3 (Depends on Wave 2):**
- APPR-003: Create TimesheetApproval Model (depends on APPR-001, APPR-002)
- APPR-017: Add Submit/Withdraw UI (depends on APPR-016)
- APPR-018: Disable Cell Editing (depends on APPR-016)
- APPR-020: Create Approvals Vue Page (depends on APPR-019)

**Wave 4 (Depends on Wave 3):**
- APPR-006: Create TimesheetApprovalService (depends on APPR-001, APPR-002, APPR-003, APPR-005)
- APPR-010: Create Request Validation Classes (depends on APPR-003)
- APPR-022: Org Settings UI (depends on APPR-004)
- APPR-021: Sidebar Nav Item (depends on APPR-020)
- APPR-028: Frontend Component Tests (depends on APPR-017, APPR-020)
- APPR-029: E2E Playwright Tests (depends on APPR-017, APPR-020)

**Wave 5 (Depends on Wave 4):**
- APPR-007: Enhance TimesheetService Lock Checks (depends on APPR-006)
- APPR-009: Create TimesheetApprovalController (depends on APPR-005, APPR-006)
- APPR-012: Create Notification Mail Classes (depends on APPR-006)
- APPR-023: Service Unit Tests (depends on APPR-006)

**Wave 6 (Depends on Wave 5):**
- APPR-008: Enhance TimeEntryController Lock Checks (depends on APPR-006, APPR-007)
- APPR-011: Register API Routes (depends on APPR-009)
- APPR-013: Dispatch Notifications (depends on APPR-006, APPR-012)
- APPR-014: Reminder Command (depends on APPR-003, APPR-004, APPR-012)
- APPR-025: Lock Tests (depends on APPR-007, APPR-008)

**Wave 7 (Depends on Wave 6):**
- APPR-024: Endpoint Tests (depends on APPR-009, APPR-011)
- APPR-026: Mail Tests (depends on APPR-012, APPR-013)
- APPR-027: Reminder Tests (depends on APPR-014)
- APPR-030: Update OpenAPI Spec (depends on APPR-009, APPR-011)

---

## Critical Path

The longest dependency chain determining minimum project duration:

```
APPR-001 (1h) -> APPR-002 (2h) -> APPR-003 (4h) -> APPR-006 (16h) -> APPR-009 (8h) -> APPR-011 (1h) -> APPR-024 (16h)
```

**Critical path total: ~48 hours of sequential work**

Secondary critical paths:
- Backend lock path: APPR-001 -> APPR-002 -> APPR-003 -> APPR-006 -> APPR-007 -> APPR-008 -> APPR-025
- Frontend path: APPR-015 -> APPR-016 -> APPR-017 -> APPR-029
- Notification path: APPR-006 -> APPR-012 -> APPR-013 -> APPR-026

---

## Risk Flags

| Task ID   | Risk | Mitigation |
|-----------|------|------------|
| APPR-006  | Largest single task (16h); complex state machine logic | Break into sub-tasks if needed; write tests concurrently |
| APPR-020  | Largest frontend task (12h); many sub-components | Template from existing Pages (e.g., Members.vue); reuse TimesheetGrid |
| APPR-024  | Largest test task (16h); many endpoint permutations | Use test data builders; parameterize where possible |
| APPR-007  | Modifies existing TimesheetService; risk of regression | Run full existing test suite before and after changes |
| APPR-008  | Modifies existing TimeEntryController; high-traffic endpoint | Ensure lock check is O(1) with proper indexing; load test |
