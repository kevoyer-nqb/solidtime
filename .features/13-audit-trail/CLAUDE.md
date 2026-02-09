# Feature 13: Audit Trail / Activity Log

## Branch
`feature/audit-trail`

## Task Prefix
`AUD-` (AUD-001 through AUD-024)

## Migration Date Prefix
`2026_03_13_` (per SF-03 allocation)

## Execution Phase
Phase 3 -- standalone utility feature, no hard dependencies from other features

## Sprint Summary
| Sprint | Focus | Story Points |
|--------|-------|-------------|
| Sprint 1 | Backend: Migration, Backfill Command, Trait Extension, Permissions, Service, Controller, Routes, Validation, API Resources, OpenAPI | ~29 SP |
| Sprint 2 | Frontend: Page, List, Filters, Detail Slide-over, Export, Pinia Store, Navigation, Entity History Links, Backend Tests Complete | ~26 SP |
| Sprint 3 | Testing: Component Tests, E2E Tests | ~10 SP |

**Total**: ~65 SP / ~140h across 3 sprints (6 weeks)

## Shared Foundation Dependencies
- SF-02: Permission naming convention (`audit-logs:view`, `audit-logs:export`)
- SF-03: Migration timestamp allocation (`2026_03_13_`)
- SF-08: Modular permissions pattern (`AuditLogPermissions.php`)
- FOUND-007: Modular permissions infrastructure (`app/Permissions/`). If not available, permissions are added directly to `JetstreamServiceProvider.php`.

## Key Architecture Decisions
- One schema change: add nullable `organization_id` column to existing `audits` table
- Three composite indexes for query performance on the `audits` table
- Backfill artisan command (`audit:backfill-organization-ids`) populates `organization_id` on existing records
- `CustomAuditable` trait extended with `transformAudit()` to auto-populate `organization_id` on new records
- New `AuditLogService` handles querying, entity name resolution (UUID-to-name), change summaries, and export
- New `AuditLogController` with 3 read-only endpoints (index, show, export)
- Cursor-based pagination (not offset-based) for consistent performance on large tables
- Two new permissions: `audit-logs:view` (Owner/Admin/Manager), `audit-logs:export` (Owner/Admin)
- Frontend slide-over panel for detail view (not a separate page)
- Filter state synced to URL query parameters for shareable links
- Export limited to 10,000 records per download (CSV or JSON)
- No new npm or Composer dependencies

## Prerequisite
`AUDITING_ENABLED=true` must be set in the environment for audit data to be generated. The UI shows a warning banner when auditing is disabled.

## New Files to Create
- `database/migrations/2026_03_13_000001_add_organization_id_to_audits_table.php`
- `app/Console/Commands/BackfillAuditOrganizationIds.php`
- `app/Permissions/AuditLogPermissions.php`
- `app/Service/AuditLogService.php`
- `app/Http/Controllers/Api/V1/AuditLogController.php`
- `app/Http/Requests/V1/AuditLog/AuditLogIndexRequest.php`
- `app/Http/Requests/V1/AuditLog/AuditLogExportRequest.php`
- `app/Http/Resources/V1/AuditLog/AuditLogResource.php`
- `app/Http/Resources/V1/AuditLog/AuditLogCollection.php`
- `app/Http/Resources/V1/AuditLog/AuditLogDetailResource.php`
- `resources/js/Pages/AuditLog.vue`
- `resources/js/packages/ui/src/AuditLog/AuditLogList.vue`
- `resources/js/packages/ui/src/AuditLog/AuditLogRow.vue`
- `resources/js/packages/ui/src/AuditLog/AuditLogEventBadge.vue`
- `resources/js/packages/ui/src/AuditLog/AuditLogFilters.vue`
- `resources/js/packages/ui/src/AuditLog/AuditLogDetail.vue`
- `resources/js/packages/ui/src/AuditLog/AuditLogDiffTable.vue`
- `resources/js/packages/ui/src/AuditLog/AuditLogExport.vue`
- `resources/js/utils/useAuditLog.ts`
- `resources/js/types/auditLog.d.ts`
- `tests/Unit/Endpoint/Api/V1/AuditLogEndpointTest.php`
- `tests/Unit/Service/AuditLogServiceTest.php`
- `tests/Unit/Console/BackfillAuditOrganizationIdsTest.php`
- `resources/js/packages/ui/src/AuditLog/__tests__/AuditLogList.test.ts`
- `resources/js/packages/ui/src/AuditLog/__tests__/AuditLogFilters.test.ts`
- `resources/js/packages/ui/src/AuditLog/__tests__/AuditLogDetail.test.ts`
- `resources/js/packages/ui/src/AuditLog/__tests__/AuditLogEventBadge.test.ts`
- `e2e/audit-log.spec.ts`

## Files to Modify
- `app/Models/Concerns/CustomAuditable.php` (add `transformAudit()` and `resolveAuditOrganizationId()` methods)
- `app/Models/Audit.php` (add `organization_id` property docblock)
- `database/factories/AuditFactory.php` (add `forOrganization()` state method)
- `app/Permissions/CorePermissions.php` (add `audit-logs:view` and `audit-logs:export` to role arrays)
- `app/Providers/JetstreamServiceProvider.php` (add `AuditLogPermissions::register()` call)
- `routes/api.php` (add audit-logs route group)
- `routes/web.php` (add Inertia page route)
- `resources/js/Layouts/AppLayout.vue` (add sidebar nav item with permission gate)
- `openapi.json` (add 3 endpoint definitions)
- `resources/js/packages/api/src/openapi.json.client.ts` (regenerate)
- Entity detail pages -- Projects, Tasks, Clients, Members (add "View History" links)

## API Endpoints
| Method | Route | Permission |
|--------|-------|-----------|
| GET | `/api/v1/organizations/{org}/audit-logs` | `audit-logs:view` |
| GET | `/api/v1/organizations/{org}/audit-logs/export` | `audit-logs:export` |
| GET | `/api/v1/organizations/{org}/audit-logs/{audit}` | `audit-logs:view` |

## Permission Matrix
| Permission | Owner | Admin | Manager | Employee | Placeholder |
|------------|:-----:|:-----:|:-------:|:--------:|:-----------:|
| `audit-logs:view` | Yes | Yes | Yes | No | No |
| `audit-logs:export` | Yes | Yes | No | No | No |

## Auditable Models (10 total)
All models using `CustomAuditable` that appear in the audit log:
- `TimeEntry` (filter: `time-entry`, org_id: direct)
- `Project` (filter: `project`, org_id: direct)
- `Task` (filter: `task`, org_id: via project)
- `Client` (filter: `client`, org_id: direct)
- `Tag` (filter: `tag`, org_id: direct)
- `Member` (filter: `member`, org_id: direct)
- `Organization` (filter: `organization`, org_id: self)
- `ProjectMember` (filter: `project-member`, org_id: via project)
- `OrganizationInvitation` (filter: `organization-invitation`, org_id: direct)
- `User` (filter: `user`, org_id: via membership/request context)

## Critical Path
```
AUD-001 -> AUD-003 -> AUD-005 -> AUD-006 -> AUD-008 -> AUD-010 -> AUD-011 -> AUD-012 -> AUD-014
 (4h)       (4h)       (16h)      (8h)       (2h)       (4h)       (4h)       (12h)      (10h)
= 64 hours
```

## Quality Gates
- [ ] `composer fix && composer analyse` passes
- [ ] `npm run lint:fix && npm run format` passes
- [ ] `npm run build` succeeds
- [ ] All 3 API endpoints have endpoint tests (AUD-019)
- [ ] Service layer has unit tests (AUD-020)
- [ ] Backfill command has unit tests (AUD-021)
- [ ] Frontend components have Vitest tests (AUD-022)
- [ ] E2E tests cover core workflow: navigate, view list, filter, view detail, export (AUD-023)
- [ ] OpenAPI spec updated and TS client regenerated (AUD-010)

## Post-Merge Deployment
```bash
php artisan migrate
php artisan audit:backfill-organization-ids --dry-run
php artisan audit:backfill-organization-ids
# Ensure AUDITING_ENABLED=true in .env
```

## Planning Docs
- `PRD.md` -- Product requirements
- `task_assignments_20260209.md` -- Task breakdown
- `ARCHITECTURE.md` -- Technical architecture
- `CODEBASE-ANALYSIS.md` -- Integration points
- `SPRINT-PLAN.md` -- Sprint-by-sprint implementation plan
