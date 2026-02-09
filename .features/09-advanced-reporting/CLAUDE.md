# Feature 09: Advanced Reporting

## Branch
`feature/advanced-reporting`

## Task Prefix
`RPT-` (RPT-001 through RPT-039)

## Migration Date Prefix
`2026_03_09_`

## Execution Phase
Phase 2b (parallel with 08-Resource Scheduling)

## Sprint Summary
| Sprint | Focus | Story Points |
|--------|-------|-------------|
| Sprint 1 | Report builder model, query engine, saved reports | ~33 SP |
| Sprint 2 | Chart visualizations, grouping/filtering, export | ~33 SP |
| Sprint 3 | Dashboard widgets, custom dashboards, scheduling | ~33 SP |
| Sprint 4 | Profitability reports, utilization analytics | ~33 SP |
| Sprint 5 | Cross-feature data (budgets, expenses, PTO) | ~33 SP |
| Sprint 6 | Public sharing, PDF export, E2E tests, polish | ~30 SP |

**Total**: ~195 SP / ~310h across 6 sprints (12 weeks)

## Shared Foundation Dependencies
- **FOUND-006**: `weekly_capacity` on members (for utilization calculations)
- **FOUND-007**: Modular permissions (required for Sprint 1)
- Builds on existing `App\Http\Controllers\Api\V1\ReportController`

## Key Architecture Decisions
- `CustomReport` model extends existing Report system
- Query engine builds Eloquent queries from report configuration JSON
- Chart rendering via Chart.js or similar (frontend)
- Export formats: CSV, XLSX, PDF
- Scheduled reports via Laravel scheduler + email delivery
- Custom dashboards stored as `Dashboard` model with widget layout JSON
- Permissions: `advanced-reports:{action}:{scope}` via `ReportingPermissions::register()`

## New Files to Create
- `app/Models/CustomReport.php`
- `app/Models/Dashboard.php`
- `app/Models/DashboardWidget.php`
- `app/Service/ReportBuilderService.php`
- `app/Service/ReportQueryEngine.php`
- `app/Service/ReportExportService.php`
- `app/Http/Controllers/Api/V1/CustomReportController.php`
- `app/Http/Controllers/Api/V1/DashboardController.php`
- `app/Http/Requests/V1/Report/*.php`
- `app/Permissions/ReportingPermissions.php`
- `app/Console/Commands/SendScheduledReports.php`
- `resources/js/packages/ui/src/Report/*.vue`
- `resources/js/utils/useCustomReport.ts`
- `tests/Unit/Endpoint/Api/V1/CustomReportEndpointTest.php`
- `tests/Unit/Service/ReportBuilderServiceTest.php`

## Files to Modify
- `app/Providers/JetstreamServiceProvider.php` (register permissions)
- `routes/api.php` (add report builder routes)
- `app/Console/Kernel.php` (schedule report delivery)
- `resources/js/Layouts/AppLayout.vue` (extend Reporting sub-items)

## Quality Gates
- [ ] `composer fix && composer analyse` passes
- [ ] `npm run lint:fix && npm run format` passes
- [ ] Query engine handles edge cases (empty data, large datasets)
- [ ] Export formats validated
- [ ] Chart rendering tested with various data shapes
- [ ] E2E tests cover report builder workflow

## Planning Docs
- `PRD.md` — Product requirements
- `task_assignments_20260206.md` — Task breakdown
- `ARCHITECTURE.md` — Technical architecture
- `CODEBASE-ANALYSIS.md` — Integration points
- `SPRINT-PLAN.md` — Sprint-by-sprint implementation plan
