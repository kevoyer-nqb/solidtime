# Feature 08: Resource Scheduling

## Branch
`feature/resource-scheduling`

## Task Prefix
`RES-` (RES-001 through RES-035)

## Migration Date Prefix
`2026_03_08_`

## Execution Phase
Phase 2b (after Phase 2a completes)

## Sprint Summary
| Sprint | Focus | Story Points |
|--------|-------|-------------|
| Sprint 1 | Schedule model, availability service, core CRUD | ~30 SP |
| Sprint 2 | Assignment engine, conflict resolution, API | ~30 SP |
| Sprint 3 | Gantt/timeline UI, drag scheduling, team view | ~30 SP |
| Sprint 4 | Utilization metrics, capacity planning, dashboards | ~29 SP |
| Sprint 5 | Notifications, optimization, E2E tests, polish | ~29 SP |

**Total**: ~148 SP / ~237h across 5 sprints (10 weeks)

## Shared Foundation Dependencies
- **FOUND-006**: `weekly_capacity` on members (already on main)
- **FOUND-007**: Modular permissions (required for Sprint 1)
- **Feature 07 (PTO)**: Leave data for availability calculations (soft dependency)

## Key Architecture Decisions
- `Schedule` model for planned resource allocations
- `ScheduleEntry` model for individual allocation blocks
- Availability = weekly_capacity - approved_leave - existing_schedules
- Conflict detection prevents over-allocation
- Gantt/timeline view with horizontal scrolling
- Utilization = actual_hours / scheduled_hours percentage
- Permissions: `scheduling:{action}:{scope}` via `SchedulingPermissions::register()`

## New Files to Create
- `app/Models/Schedule.php`
- `app/Models/ScheduleEntry.php`
- `app/Service/SchedulingService.php`
- `app/Service/AvailabilityService.php`
- `app/Service/UtilizationService.php`
- `app/Http/Controllers/Api/V1/ScheduleController.php`
- `app/Http/Requests/V1/Schedule/*.php`
- `app/Permissions/SchedulingPermissions.php`
- `resources/js/Pages/Scheduling.vue`
- `resources/js/packages/ui/src/Scheduling/*.vue`
- `resources/js/utils/useScheduling.ts`
- `tests/Unit/Endpoint/Api/V1/ScheduleEndpointTest.php`
- `tests/Unit/Service/SchedulingServiceTest.php`

## Files to Modify
- `app/Providers/JetstreamServiceProvider.php` (register permissions)
- `routes/api.php` (add scheduling routes)
- `routes/web.php` (scheduling page route)
- `resources/js/Layouts/AppLayout.vue` (add nav item)

## Quality Gates
- [ ] `composer fix && composer analyse` passes
- [ ] `npm run lint:fix && npm run format` passes
- [ ] Conflict detection thoroughly tested
- [ ] Availability calculations account for PTO (if available)
- [ ] Utilization metrics are accurate
- [ ] E2E tests cover scheduling workflow

## Planning Docs
- `PRD.md` — Product requirements
- `task_assignments_20260206.md` — Task breakdown
- `ARCHITECTURE.md` — Technical architecture
- `CODEBASE-ANALYSIS.md` — Integration points
- `SPRINT-PLAN.md` — Sprint-by-sprint implementation plan
