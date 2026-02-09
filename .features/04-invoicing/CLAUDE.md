# Feature 04: Invoicing System

## Branch
`feature/invoicing`

## Task Prefix
`INV-` (INV-001 through INV-040+)

## Migration Date Prefix
`2026_03_04_`

## Execution Phase
Phase 2a (after Phase 1b completes)

## Sprint Summary
| Sprint | Focus | Story Points |
|--------|-------|-------------|
| Sprint 1 | Invoice model, settings, number generation | ~16 SP |
| Sprint 2 | Line items, time entry linking, calculations | ~16 SP |
| Sprint 3 | PDF generation, email delivery | ~16 SP |
| Sprint 4 | Frontend invoice builder, preview | ~16 SP |
| Sprint 5 | Payment tracking, status workflow | ~16 SP |
| Sprint 6 | Recurring invoices, automation | ~16 SP |
| Sprint 7 | Reports, dashboard, E2E tests | ~14 SP |

**Total**: ~110 SP / ~296h across 7 sprints (14 weeks)

## Shared Foundation Dependencies
- **FOUND-001..005**: Notification infrastructure (for invoice emails/reminders)
- **FOUND-007**: Modular permissions (required for Sprint 1)

## Key Architecture Decisions
- `Invoice`, `InvoiceLine`, `InvoiceSettings` models
- Invoice number generation with configurable format (prefix, padding, sequential)
- PDF generation via DomPDF or similar Laravel package
- Time entries linked to invoice lines (many-to-many)
- Status workflow: Draft → Sent → Viewed → Paid → Overdue → Void
- Recurring invoice scheduling via Laravel scheduler
- Permissions: `invoices:{action}:{scope}` via `InvoicePermissions::register()`

## New Files to Create
- `app/Models/Invoice.php`
- `app/Models/InvoiceLine.php`
- `app/Models/InvoiceSettings.php`
- `app/Service/InvoiceService.php`
- `app/Service/InvoiceNumberService.php`
- `app/Service/InvoicePdfService.php`
- `app/Http/Controllers/Api/V1/InvoiceController.php`
- `app/Http/Controllers/Api/V1/InvoiceSettingsController.php`
- `app/Http/Requests/V1/Invoice/*.php`
- `app/Permissions/InvoicePermissions.php`
- `app/Notifications/Invoice*.php`
- `resources/js/packages/ui/src/Invoice/*.vue`
- `resources/js/utils/useInvoice.ts`
- `tests/Unit/Endpoint/Api/V1/InvoiceEndpointTest.php`
- `tests/Unit/Service/InvoiceServiceTest.php`

## Files to Modify
- `app/Providers/JetstreamServiceProvider.php` (register permissions)
- `routes/api.php` (add invoice routes)
- `resources/js/Layouts/AppLayout.vue` (nav already has Invoices item)

## Quality Gates
- [ ] `composer fix && composer analyse` passes
- [ ] `npm run lint:fix && npm run format` passes
- [ ] All new endpoints have API tests
- [ ] PDF generation tested with various configurations
- [ ] Number generation uniqueness tested
- [ ] E2E tests cover full invoice lifecycle

## Planning Docs
- `PRD.md` — Product requirements
- `task_assignments_20260206.md` — Task breakdown
- `ARCHITECTURE.md` — Technical architecture
- `CODEBASE-ANALYSIS.md` — Integration points
- `SPRINT-PLAN.md` — Sprint-by-sprint implementation plan
