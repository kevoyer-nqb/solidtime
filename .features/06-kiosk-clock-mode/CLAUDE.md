# Feature 06: Kiosk & Clock Mode

## Branch
`feature/kiosk-clock-mode`

## Task Prefix
`KIO-` (KIO-001 through KIO-030+)

## Migration Date Prefix
`2026_03_06_`

## Execution Phase
Phase 1a (parallel with 10-Teams, 05-Calendar)

## Sprint Summary
| Sprint | Focus | Story Points |
|--------|-------|-------------|
| Sprint 1 | Kiosk session model, PIN auth, clock in/out API | ~25 SP |
| Sprint 2 | Kiosk frontend UI, real-time clock, activity feed | ~25 SP |
| Sprint 3 | Admin management, geofencing, device registration | ~25 SP |
| Sprint 4 | Offline mode, analytics, E2E tests, polish | ~25 SP |

**Total**: ~100 SP / ~200h across 4 sprints (8 weeks)

## Shared Foundation Dependencies
- **FOUND-007**: Modular permissions (required for Sprint 1)
- Notification infrastructure optional (admin alerts)

## Key Architecture Decisions
- `KioskSession` model for device sessions with PIN-based auth
- Separate kiosk route group (no standard auth, PIN/device token instead)
- `KioskDevice` model for registered devices
- Real-time clock display with WebSocket or polling
- Clock in/out creates standard `TimeEntry` records
- Geofencing optional via browser Geolocation API
- Permissions: `kiosk:{action}:{scope}` via `KioskPermissions::register()`
- Offline mode with localStorage queue + sync on reconnect

## New Files to Create
- `app/Models/KioskDevice.php`
- `app/Models/KioskSession.php`
- `app/Service/KioskService.php`
- `app/Http/Controllers/Api/V1/KioskController.php`
- `app/Http/Middleware/KioskAuth.php`
- `app/Http/Requests/V1/Kiosk/*.php`
- `app/Permissions/KioskPermissions.php`
- `resources/js/Pages/Kiosk.vue`
- `resources/js/packages/ui/src/Kiosk/*.vue`
- `resources/js/utils/useKiosk.ts`
- `tests/Unit/Endpoint/Api/V1/KioskEndpointTest.php`
- `tests/Unit/Service/KioskServiceTest.php`

## Files to Modify
- `app/Providers/JetstreamServiceProvider.php` (register permissions)
- `routes/api.php` (add kiosk routes, possibly separate middleware group)
- `routes/web.php` (kiosk page route)

## Quality Gates
- [ ] `composer fix && composer analyse` passes
- [ ] `npm run lint:fix && npm run format` passes
- [ ] PIN auth security tested (rate limiting, lockout)
- [ ] Clock in/out creates correct TimeEntry records
- [ ] Offline mode queue tested
- [ ] E2E tests cover kiosk workflow

## Planning Docs
- `PRD.md` — Product requirements
- `task_assignments_20260206.md` — Task breakdown
- `ARCHITECTURE.md` — Technical architecture
- `CODEBASE-ANALYSIS.md` — Integration points
- `SPRINT-PLAN.md` — Sprint-by-sprint implementation plan
