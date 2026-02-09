# Feature 05: Calendar Enhanced

## Branch
`feature/calendar-enhanced`

## Task Prefix
`CAL-` (CAL-001 through CAL-021)

## Migration Date Prefix
`2026_03_05_`

## Execution Phase
Phase 1a (parallel with 10-Teams, 06-Kiosk)

## Sprint Summary
| Sprint | Focus | Story Points |
|--------|-------|-------------|
| Sprint 1 | Enhanced week/day views, drag-and-drop, resize | ~22 SP |
| Sprint 2 | Multi-day view, permissions, recurring entries | ~20 SP |
| Sprint 3 | Month view, conflict detection, color coding | ~20 SP |
| Sprint 4 | Keyboard nav, export, E2E tests, polish | ~19 SP |

**Total**: ~74 SP / ~144h across 4 sprints (8 weeks)

## Shared Foundation Dependencies
- **FOUND-007**: Modular permissions (required for Sprint 2, CAL-014)
- No notification infrastructure dependency

## Key Architecture Decisions
- Builds on existing `resources/js/Pages/Calendar.vue` page
- Enhanced views extend current time entry display
- Drag-and-drop uses native HTML5 drag API or vue-draggable
- Resize handles on calendar entries for duration adjustment
- Conflict detection queries overlapping time entries via API
- Recurring entry templates stored as `CalendarTemplate` model
- Permissions: `calendar:{action}:{scope}` via `CalendarPermissions::register()`

## New Files to Create
- `app/Models/CalendarTemplate.php` (if recurring entries need templates)
- `app/Service/CalendarService.php`
- `app/Http/Controllers/Api/V1/CalendarController.php`
- `app/Permissions/CalendarPermissions.php`
- `resources/js/packages/ui/src/Calendar/*.vue` (enhanced view components)
- `resources/js/utils/useCalendar.ts`
- `tests/Unit/Endpoint/Api/V1/CalendarEndpointTest.php`

## Files to Modify
- `resources/js/Pages/Calendar.vue` (enhance existing page)
- `app/Providers/JetstreamServiceProvider.php` (register permissions)
- `routes/api.php` (add calendar-specific routes if needed)

## Quality Gates
- [ ] `composer fix && composer analyse` passes
- [ ] `npm run lint:fix && npm run format` passes
- [ ] Drag-and-drop has Vitest tests
- [ ] Calendar views tested across viewport sizes
- [ ] E2E tests cover week/day/month views
- [ ] Conflict detection tested with edge cases

## Planning Docs
- `PRD.md` — Product requirements
- `task_assignments_20260206.md` — Task breakdown
- `ARCHITECTURE.md` — Technical architecture
- `CODEBASE-ANALYSIS.md` — Integration points
- `SPRINT-PLAN.md` — Sprint-by-sprint implementation plan
