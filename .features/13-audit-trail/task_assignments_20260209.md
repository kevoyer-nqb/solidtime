# Task Assignments: Audit Trail / Activity Log

Generated: 2026-02-09
Feature Branch: `feature/audit-trail`
PRD Reference: `.features/13-audit-trail/PRD.md`

---

## Task Assignment Table

| Task ID  | Description                                                          | Type                 | Assigned Sub-Agent | Dependencies         | Effort   | Status |
|----------|----------------------------------------------------------------------|----------------------|--------------------|----------------------|----------|--------|
| AUD-001  | Database migration: add `organization_id` to `audits` table         | Backend              | Backend Dev        | None                 | 4 hours  | To Do  |
| AUD-002  | Artisan command: backfill `organization_id` on existing audit records| Backend              | Backend Dev        | AUD-001              | 8 hours  | To Do  |
| AUD-003  | Extend `CustomAuditable` trait to auto-populate `organization_id`   | Backend              | Backend Dev        | AUD-001              | 4 hours  | To Do  |
| AUD-004  | Register `audit-logs:view` and `audit-logs:export` permissions      | Backend              | Backend Dev        | None                 | 4 hours  | To Do  |
| AUD-005  | Create `AuditLogService` with query, detail, and export logic       | Backend              | Backend Dev        | AUD-001, AUD-003     | 16 hours | To Do  |
| AUD-006  | Create `AuditLogController` with index, show, export endpoints      | Backend              | Backend Dev        | AUD-005, AUD-004     | 8 hours  | To Do  |
| AUD-007  | Create request validation classes                                    | Backend              | Backend Dev        | AUD-006              | 4 hours  | To Do  |
| AUD-008  | Register API routes for audit log endpoints                          | Backend              | Backend Dev        | AUD-006              | 2 hours  | To Do  |
| AUD-009  | Create `AuditLogResource` and `AuditLogCollection` API resources    | Backend              | Backend Dev        | AUD-005              | 4 hours  | To Do  |
| AUD-010  | Update OpenAPI spec + regenerate TypeScript client                   | Backend              | Backend Dev        | AUD-008, AUD-009     | 4 hours  | To Do  |
| AUD-011  | Create `AuditLog.vue` Inertia page                                  | Frontend             | Frontend Dev       | AUD-010              | 4 hours  | To Do  |
| AUD-012  | Create `AuditLogList.vue` component (table with pagination)         | Frontend             | Frontend Dev       | AUD-011              | 12 hours | To Do  |
| AUD-013  | Create `AuditLogFilters.vue` component (filter bar)                 | Frontend             | Frontend Dev       | AUD-011              | 8 hours  | To Do  |
| AUD-014  | Create `AuditLogDetail.vue` slide-over component (diff view)        | Frontend             | Frontend Dev       | AUD-012              | 10 hours | To Do  |
| AUD-015  | Create `AuditLogExport.vue` component (export dropdown)             | Frontend             | Frontend Dev       | AUD-012              | 4 hours  | To Do  |
| AUD-016  | Create `useAuditLogStore.ts` Pinia store + TypeScript types         | Frontend             | Frontend Dev       | AUD-010              | 8 hours  | To Do  |
| AUD-017  | Add web route + sidebar navigation item                              | Frontend             | Frontend Dev       | AUD-011              | 2 hours  | To Do  |
| AUD-018  | Add "View History" links to entity detail pages                      | Frontend             | Frontend Dev       | AUD-012, AUD-013     | 4 hours  | To Do  |
| AUD-019  | Backend endpoint tests (PHPUnit)                                     | Testing              | QA / Backend Dev   | AUD-006, AUD-008     | 10 hours | To Do  |
| AUD-020  | Service layer unit tests (PHPUnit)                                   | Testing              | QA / Backend Dev   | AUD-005              | 8 hours  | To Do  |
| AUD-021  | Backfill command tests (PHPUnit)                                     | Testing              | QA / Backend Dev   | AUD-002              | 4 hours  | To Do  |
| AUD-022  | Frontend component tests (Vitest)                                    | Testing              | QA / Frontend Dev  | AUD-012, AUD-013, AUD-014 | 8 hours  | To Do  |
| AUD-023  | E2E Playwright tests                                                 | Testing              | QA / Frontend Dev  | AUD-017, AUD-012     | 8 hours  | To Do  |
| AUD-024  | JSDoc comments on Pinia store and key components                     | Documentation        | Frontend Dev       | AUD-016              | 2 hours  | To Do  |

---

## Summary

| Metric                     | Value        |
|----------------------------|--------------|
| **Total Tasks**            | 24           |
| **Total Effort**           | 140 hours    |
| **Estimated Story Points** | ~70 SP       |
| **Duration**               | 6 weeks (3 sprints) |
| **Backend Tasks**          | AUD-001 through AUD-010 (10 tasks, 58 hours) |
| **Frontend Tasks**         | AUD-011 through AUD-018 (8 tasks, 52 hours) |
| **Testing Tasks**          | AUD-019 through AUD-023 (5 tasks, 38 hours) |
| **Documentation Tasks**    | AUD-024 (1 task, 2 hours) |

---

## Critical Path

```
AUD-001 --> AUD-005 --> AUD-006 --> AUD-008 --> AUD-010 --> AUD-011 --> AUD-012 --> AUD-014
 (4h)       (16h)       (8h)        (2h)        (4h)        (4h)       (12h)       (10h)

Total critical path duration: 60 hours
```

---

## Parallelization Opportunities

| Parallel Group | Tasks                      | Combined Effort | Notes                              |
|----------------|----------------------------|-----------------|------------------------------------|
| Group A        | AUD-001, AUD-004           | 8h              | No shared dependencies             |
| Group B        | AUD-002, AUD-003           | 12h             | Both depend only on AUD-001        |
| Group C        | AUD-012, AUD-013           | 20h             | Both depend only on AUD-011        |
| Group D        | AUD-014, AUD-015           | 14h             | Both depend only on AUD-012        |
| Group E        | AUD-019, AUD-020           | 18h             | Independent test suites            |
| Group F        | AUD-022, AUD-023           | 16h             | Independent test suites            |

---

## Sprint Allocation

### Sprint 1 (Weeks 1-2): Backend Foundation
| Task ID  | Description                                                | Effort  |
|----------|------------------------------------------------------------|---------|
| AUD-001  | Database migration                                          | 4h      |
| AUD-002  | Backfill command                                            | 8h      |
| AUD-003  | Extend CustomAuditable trait                                | 4h      |
| AUD-004  | Register permissions                                        | 4h      |
| AUD-005  | AuditLogService                                             | 16h     |
| AUD-006  | AuditLogController                                          | 8h      |
| AUD-007  | Request validation classes                                  | 4h      |
| AUD-008  | API routes                                                  | 2h      |
| AUD-009  | API resource classes                                        | 4h      |
| AUD-010  | OpenAPI spec + TS client                                    | 4h      |
| **Total** |                                                            | **58h** |

### Sprint 2 (Weeks 3-4): Frontend Implementation
| Task ID  | Description                                                | Effort  |
|----------|------------------------------------------------------------|---------|
| AUD-011  | AuditLog.vue page                                           | 4h      |
| AUD-012  | AuditLogList.vue component                                  | 12h     |
| AUD-013  | AuditLogFilters.vue component                               | 8h      |
| AUD-014  | AuditLogDetail.vue slide-over                               | 10h     |
| AUD-015  | AuditLogExport.vue component                                | 4h      |
| AUD-016  | Pinia store + TypeScript types                              | 8h      |
| AUD-017  | Web route + sidebar navigation                              | 2h      |
| AUD-018  | Entity "View History" links                                 | 4h      |
| **Total** |                                                            | **52h** |

### Sprint 3 (Weeks 5-6): Testing & Documentation
| Task ID  | Description                                                | Effort  |
|----------|------------------------------------------------------------|---------|
| AUD-019  | Backend endpoint tests                                      | 10h     |
| AUD-020  | Service layer unit tests                                    | 8h      |
| AUD-021  | Backfill command tests                                      | 4h      |
| AUD-022  | Frontend component tests                                    | 8h      |
| AUD-023  | E2E Playwright tests                                        | 8h      |
| AUD-024  | JSDoc comments                                              | 2h      |
| **Total** |                                                            | **40h** |

---

## Dependency Risk Assessment

| Risk Area                            | Affected Tasks         | Mitigation                                                   |
|--------------------------------------|------------------------|--------------------------------------------------------------|
| AUD-001 migration delay              | AUD-002, AUD-003, AUD-005 | AUD-001 is trivial (4h); prioritize for day 1                |
| AUD-005 service complexity           | AUD-006, AUD-009, AUD-020 | AUD-005 is the largest backend task; allocate senior dev      |
| AUD-010 OpenAPI regeneration issues  | AUD-011 through AUD-018 | Frontend cannot start without TS client; buffer 0.5 day      |
| AUD-012 list component complexity    | AUD-014, AUD-015, AUD-018, AUD-022, AUD-023 | Central frontend dependency; allocate experienced FE dev |

---

Last updated: 2026-02-09
