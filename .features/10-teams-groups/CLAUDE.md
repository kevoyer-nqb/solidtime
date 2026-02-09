# Feature 10: Teams & Groups

## Branch
`feature/teams-groups`

## Task Prefix
`TEAM-` (TEAM-001 through TEAM-032)

## Migration Date Prefix
`2026_03_10_`

## Execution Phase
Phase 1a (parallel with 05-Calendar, 06-Kiosk) — first to start

## Sprint Summary
| Sprint | Focus | Story Points |
|--------|-------|-------------|
| Sprint 1 | Team model, member assignment, CRUD API, backend tests | ~28 SP |
| Sprint 2 | Hierarchy, team leads, filtering/scoping, permissions | ~26 SP |
| Sprint 3 | Frontend UI, team dashboard, E2E tests, polish | ~26 SP |

**Total**: ~80 SP / ~160h across 3 sprints (6 weeks)

## Shared Foundation Dependencies
- **FOUND-007**: Modular permissions (required for Sprint 1)
- No notification or weekly_capacity dependencies

## Key Architecture Decisions
- `Team` model (not to be confused with Jetstream's Team = Organization)
- Named "Groups" in UI to avoid confusion with Jetstream terminology
- `TeamMember` pivot model (team_id + member_id)
- Optional team hierarchy (parent_team_id self-reference)
- Team leads are members with `is_lead` flag on pivot
- Scoping: filter time entries, reports, etc. by team
- Permissions: `teams:{action}:{scope}` via `TeamGroupPermissions::register()`

## New Files to Create
- `app/Models/Team.php` (careful: distinct from Jetstream's Team)
- `app/Models/TeamMember.php`
- `app/Service/TeamService.php`
- `app/Http/Controllers/Api/V1/TeamController.php`
- `app/Http/Controllers/Api/V1/TeamMemberController.php`
- `app/Http/Requests/V1/Team/*.php`
- `app/Permissions/TeamGroupPermissions.php`
- `resources/js/Pages/Teams.vue`
- `resources/js/packages/ui/src/Team/*.vue`
- `resources/js/utils/useTeam.ts`
- `tests/Unit/Endpoint/Api/V1/TeamEndpointTest.php`
- `tests/Unit/Service/TeamServiceTest.php`

## Files to Modify
- `app/Providers/JetstreamServiceProvider.php` (register permissions)
- `routes/api.php` (add team routes)
- `routes/web.php` (teams page route)
- `resources/js/Layouts/AppLayout.vue` (add nav item)

## Naming Note
The model is `App\Models\Team` but this conflicts conceptually with Jetstream's `Team` (which is `Organization` in Solidtime). Consider naming the model `Group` or `TeamGroup` if naming collisions cause issues. The ARCHITECTURE.md has more details on this decision.

## Quality Gates
- [ ] `composer fix && composer analyse` passes
- [ ] `npm run lint:fix && npm run format` passes
- [ ] No naming conflicts with Jetstream's Team concept
- [ ] Hierarchy queries tested (nested teams)
- [ ] Team scoping filters work correctly
- [ ] E2E tests cover team CRUD and member assignment

## Planning Docs
- `PRD.md` — Product requirements
- `task_assignments_20260206.md` — Task breakdown
- `ARCHITECTURE.md` — Technical architecture
- `CODEBASE-ANALYSIS.md` — Integration points
- `SPRINT-PLAN.md` — Sprint-by-sprint implementation plan
