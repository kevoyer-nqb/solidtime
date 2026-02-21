# Task Assignments: Punch-Only / Time-Clock Mode

Generated: 2026-02-09
Feature Branch: `feature/punch-clock-mode`
PRD Reference: `PRD.md`

---

## Task Assignment Table

| Task ID | Description | Type | Assigned Sub-Agent | Dependencies | Effort | Status |
|---------|-------------|------|-------------------|--------------|--------|--------|
| PCM-001 | Database migrations (org, member, time_entries columns) | Backend | Backend Dev | None | 4h | To Do |
| PCM-002 | Create TimeEntrySource enum | Backend | Backend Dev | None | 2h | To Do |
| PCM-003 | Update Organization and Member models (casts, factories) | Backend | Backend Dev | PCM-001 | 2h | To Do |
| PCM-004 | Create PunchClockService (isRestricted, punchIn, punchOut, getStatus) | Backend | Backend Dev | PCM-001, PCM-002, PCM-003 | 8h | To Do |
| PCM-005 | Create PunchClockController (punchIn, punchOut, status) | Backend | Backend Dev | PCM-004 | 6h | To Do |
| PCM-006 | Create PunchClockGuard middleware and apply to write routes | Backend | Backend Dev | PCM-004 | 6h | To Do |
| PCM-007 | Register punch-clock API routes in routes/api.php | Backend | Backend Dev | PCM-005 | 2h | To Do |
| PCM-008 | Update existing controllers for time_entry_source tracking | Backend | Backend Dev | PCM-002 | 4h | To Do |
| PCM-009 | Create PunchInRequest validation + update org/member requests | Backend | Backend Dev | PCM-005 | 3h | To Do |
| PCM-010 | Create PunchClockPermissions class (modular registration) | Backend | Backend Dev | None | 2h | To Do |
| PCM-011 | Update TimeEntryResource to include time_entry_source | Backend | Backend Dev | PCM-002 | 2h | To Do |
| PCM-012 | Update OpenAPI spec + regenerate TypeScript client | Backend | Backend Dev | PCM-005, PCM-007, PCM-011 | 4h | To Do |
| PCM-013 | Create usePunchClockStore Pinia store + TypeScript types | Frontend | Frontend Dev | PCM-012 | 6h | To Do |
| PCM-014 | Create PunchClockButton, PunchClockDuration, ProjectSelector components | Frontend | Frontend Dev | PCM-013 | 8h | To Do |
| PCM-015 | Create PunchClockRestrictedView + modify Time.vue page | Frontend | Frontend Dev | PCM-013, PCM-014 | 6h | To Do |
| PCM-016 | Create PunchClockSettings and PunchClockMemberList for org settings | Frontend | Frontend Dev | PCM-012 | 6h | To Do |
| PCM-017 | Add readonly mode to TimesheetGrid for restricted members | Frontend | Frontend Dev | PCM-013 | 4h | To Do |
| PCM-018 | Create TimeEntrySourceBadge component and integrate in entry list | Frontend | Frontend Dev | PCM-012 | 3h | To Do |
| PCM-019 | Backend endpoint tests for PunchClockController and guard middleware | Testing | QA / Backend Dev | PCM-005, PCM-006, PCM-007 | 10h | To Do |
| PCM-020 | PunchClockService unit tests | Testing | QA / Backend Dev | PCM-004 | 6h | To Do |
| PCM-021 | Frontend component tests (PunchClockButton, RestrictedView, Settings) | Testing | QA / Frontend Dev | PCM-014, PCM-015, PCM-016 | 6h | To Do |
| PCM-022 | E2E Playwright tests for admin setup and member punch workflow | Testing | QA | PCM-015, PCM-016 | 8h | To Do |
| PCM-023 | Add source filter to GET /time-entries endpoint | Backend | Backend Dev | PCM-002, PCM-008 | 4h | To Do |
| PCM-024 | JSDoc comments on Pinia store and TypeScript types | Docs | Frontend Dev | PCM-013, PCM-014 | 3h | To Do |

---

## Summary

| Metric | Value |
|--------|-------|
| Total Tasks | 24 |
| Total Effort | 119 hours |
| Estimated Story Points | ~79 SP |
| Duration | 3 sprints (6 weeks) |
| Backend Tasks | 12 (PCM-001 through PCM-012, PCM-023) |
| Frontend Tasks | 6 (PCM-013 through PCM-018) |
| Testing Tasks | 4 (PCM-019 through PCM-022) |
| Documentation Tasks | 1 (PCM-024) |

---

## Sprint Allocation

### Sprint 1: Backend Foundation (Weeks 1-2)

| Task ID | Description | Effort | Dependencies |
|---------|-------------|--------|--------------|
| PCM-001 | Database migrations | 4h | None |
| PCM-002 | TimeEntrySource enum | 2h | None |
| PCM-010 | Punch-Clock permissions | 2h | None |
| PCM-003 | Update Organization/Member models | 2h | PCM-001 |
| PCM-008 | Source tracking in existing controllers | 4h | PCM-002 |
| PCM-011 | Update TimeEntryResource | 2h | PCM-002 |
| PCM-004 | PunchClockService | 8h | PCM-001, PCM-002, PCM-003 |
| PCM-005 | PunchClockController | 6h | PCM-004 |
| PCM-006 | PunchClockGuard middleware | 6h | PCM-004 |
| PCM-007 | Register API routes | 2h | PCM-005 |
| PCM-009 | Request validation classes | 3h | PCM-005 |
| PCM-012 | OpenAPI spec + TS client | 4h | PCM-005, PCM-007, PCM-011 |

**Sprint 1 Total**: 45h (~30 SP)

### Sprint 2: Frontend Implementation (Weeks 3-4)

| Task ID | Description | Effort | Dependencies |
|---------|-------------|--------|--------------|
| PCM-013 | Pinia store | 6h | PCM-012 |
| PCM-016 | Org settings UI | 6h | PCM-012 |
| PCM-018 | Source badge component | 3h | PCM-012 |
| PCM-014 | PunchClockButton components | 8h | PCM-013 |
| PCM-017 | Readonly timesheet grid | 4h | PCM-013 |
| PCM-015 | Restricted Time page view | 6h | PCM-013, PCM-014 |

**Sprint 2 Total**: 33h (~22 SP)

### Sprint 3: Testing & Polish (Weeks 5-6)

| Task ID | Description | Effort | Dependencies |
|---------|-------------|--------|--------------|
| PCM-020 | Service unit tests | 6h | PCM-004 |
| PCM-019 | Endpoint tests | 10h | PCM-005, PCM-006, PCM-007 |
| PCM-021 | Frontend component tests | 6h | PCM-014, PCM-015, PCM-016 |
| PCM-022 | E2E Playwright tests | 8h | PCM-015, PCM-016 |
| PCM-023 | Source filter on time entries | 4h | PCM-002, PCM-008 |
| PCM-024 | JSDoc documentation | 3h | PCM-013, PCM-014 |

**Sprint 3 Total**: 37h (~25 SP)

Note: PCM-020 (service tests) and PCM-023 (source filter) can technically start earlier once their backend dependencies are complete in Sprint 1, but are scheduled in Sprint 3 alongside other testing tasks for team allocation reasons.

---

## Dependency Risk Assessment

### High-Risk Dependencies

1. **PCM-004 (PunchClockService)** -- Most tasks in the backend depend on this. A delay here cascades to PCM-005, PCM-006, PCM-007, PCM-009, PCM-012, and all frontend tasks.
   - **Mitigation**: Prioritize PCM-004 as the first substantive task after migrations and enum. Consider parallel development of PCM-006 (guard) if the `isRestricted()` interface is defined early.

2. **PCM-012 (OpenAPI spec)** -- All frontend tasks depend on the regenerated TypeScript client.
   - **Mitigation**: Define TypeScript types manually in PCM-013 as a temporary measure, then validate against the regenerated client. This allows frontend work to start before the OpenAPI spec is finalized.

### Low-Risk Dependencies

3. **PCM-002 (Enum)** and **PCM-010 (Permissions)** -- No dependencies; can be completed on day one in parallel with PCM-001.

4. **Sprint 3 tasks** -- Testing tasks are largely independent of each other and can be distributed across multiple developers.

---

## Critical Path

```
PCM-001 (4h) -> PCM-003 (2h) -> PCM-004 (8h) -> PCM-005 (6h) -> PCM-007 (2h) -> PCM-012 (4h) -> PCM-013 (6h) -> PCM-014 (8h) -> PCM-015 (6h) -> PCM-022 (8h)
```

**Total critical path duration**: 54 hours
