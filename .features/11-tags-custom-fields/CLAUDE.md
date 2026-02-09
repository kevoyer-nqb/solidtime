# Feature 11: Tags and Custom Fields

## Branch
`feature/tags-custom-fields`

## Task Prefix
`TAG-` (TAG-001 through TAG-057)

## Migration Date Prefix
`2026_02_10` -- 4 migrations:
- `2026_02_10_000001_add_color_description_to_tags_table.php`
- `2026_02_10_000002_add_mandatory_tags_to_organizations_table.php`
- `2026_02_10_000003_create_custom_fields_table.php`
- `2026_02_10_000004_create_custom_field_values_table.php`

## Execution Phase
Phase A -- enhances existing tag system and introduces custom fields on time entries. Phase B (custom fields on projects/tasks, tag hierarchies) is deferred.

## Sprint Summary
| Sprint | Focus | Story Points |
|--------|-------|-------------|
| Sprint 1 | Tag backend enhancements: migrations, models, controller, validation, OpenAPI, TS client; tag frontend start: badge/dropdown/create modal colors | ~38 SP |
| Sprint 2 | Custom fields backend: migrations, models, service, controller, routes, validation; tag frontend polish: edit modal, mandatory tags toggle, tag service | ~52 SP |
| Sprint 3 | CF integration: time entry controller + resource; CF frontend: Pinia store, settings page, form renderer; tag management page polish, report tag filter | ~48 SP |
| Sprint 4 | CF in time entry modals/rows; all backend tests, frontend component tests, E2E tests | ~63 SP |

**Total**: ~155 SP / ~232h across 4 sprints (8 weeks)

## Shared Foundation Dependencies
- Feature 00 (Weekly Timesheet Grid) must be merged to `main` before this feature branches off
  - Mandatory tags exemption for timesheet cell updates references the timesheet endpoint
  - `TimeEntryStoreRequest` may have been modified by Feature 00
- No other feature dependencies

## Key Architecture Decisions
- 4 new database migrations (2 ALTER TABLE, 2 CREATE TABLE)
- 2 new Eloquent models: `CustomField`, `CustomFieldValue`
- 1 new enum: `CustomFieldType` (text, number, dropdown, checkbox)
- 2 new services: `TagService` (usage counts, bulk delete), `CustomFieldService` (validation, CRUD, value storage)
- 1 new controller: `CustomFieldController` (5 endpoints)
- Enhanced existing `TagController` with bulk delete method and constructor DI
- 4 new permission strings: `custom-fields:view`, `custom-fields:create`, `custom-fields:update`, `custom-fields:delete`
- Mandatory tags enforced in `TimeEntryStoreRequest`/`TimeEntryUpdateRequest` with exemptions for:
  - Running timers (end IS NULL)
  - Weekly timesheet grid cell updates
  - Import operations
- Custom field values stored in `custom_field_values` table with polymorphic `entity_type`/`entity_id` (supports future extension to projects/tasks)
- Custom field values validated at the service layer (not in request classes) to avoid loading field definitions in every request
- Maximum 20 active custom fields per organization (application-enforced limit)
- Tag color stored as 7-char hex string (#RRGGBB), default #808080
- Frontend: enhanced `useTagsStore`, new `useCustomFieldsStore`, `CustomFieldFormRenderer` component renders all 4 field types

## New Files to Create

### Backend
- `database/migrations/2026_02_10_000001_add_color_description_to_tags_table.php`
- `database/migrations/2026_02_10_000002_add_mandatory_tags_to_organizations_table.php`
- `database/migrations/2026_02_10_000003_create_custom_fields_table.php`
- `database/migrations/2026_02_10_000004_create_custom_field_values_table.php`
- `app/Enums/CustomFieldType.php`
- `app/Models/CustomField.php`
- `app/Models/CustomFieldValue.php`
- `app/Service/TagService.php`
- `app/Service/CustomFieldService.php`
- `app/Http/Controllers/Api/V1/CustomFieldController.php`
- `app/Http/Requests/V1/Tag/TagBulkDeleteRequest.php`
- `app/Http/Requests/V1/CustomField/CustomFieldStoreRequest.php`
- `app/Http/Requests/V1/CustomField/CustomFieldUpdateRequest.php`
- `app/Http/Resources/V1/CustomField/CustomFieldResource.php`
- `app/Http/Resources/V1/CustomField/CustomFieldCollection.php`
- `database/factories/CustomFieldFactory.php`
- `database/factories/CustomFieldValueFactory.php`

### Frontend
- `resources/js/Pages/CustomFields.vue`
- `resources/js/packages/ui/src/Tag/TagEditModal.vue`
- `resources/js/packages/ui/src/CustomField/CustomFieldFormRenderer.vue`
- `resources/js/packages/ui/src/CustomField/CustomFieldCreateModal.vue`
- `resources/js/packages/ui/src/CustomField/CustomFieldEditModal.vue`
- `resources/js/packages/ui/src/CustomField/CustomFieldListItem.vue`
- `resources/js/utils/useCustomFields.ts`
- `resources/js/types/customFields.d.ts`

### Tests
- `tests/Unit/Endpoint/Api/V1/CustomFieldEndpointTest.php`
- `tests/Unit/Service/TagServiceTest.php`
- `tests/Unit/Service/CustomFieldServiceTest.php`
- `resources/js/packages/ui/src/CustomField/__tests__/CustomFieldFormRenderer.test.ts`
- `e2e/tags-custom-fields.spec.ts`

## Files to Modify

### Backend
- `app/Models/Tag.php` (add color, description casts)
- `app/Models/Organization.php` (add mandatory_tags cast)
- `app/Http/Controllers/Api/V1/TagController.php` (enhanced store/update, new bulkDestroy, inject TagService)
- `app/Http/Requests/V1/Tag/TagStoreRequest.php` (add color, description rules)
- `app/Http/Requests/V1/Tag/TagUpdateRequest.php` (add color, description rules)
- `app/Http/Resources/V1/Tag/TagResource.php` (add color, description, conditional count)
- `app/Http/Requests/V1/TimeEntry/TimeEntryStoreRequest.php` (mandatory tags + custom field rules)
- `app/Http/Requests/V1/TimeEntry/TimeEntryUpdateRequest.php` (mandatory tags + custom field rules)
- `app/Http/Controllers/Api/V1/TimeEntryController.php` (save/load custom field values)
- `app/Http/Resources/V1/TimeEntry/TimeEntryResource.php` (add custom_fields array)
- `app/Permissions/CorePermissions.php` (add custom-field permissions to all roles)
- `routes/api.php` (add custom field routes, tag bulk delete route)
- `database/factories/TagFactory.php` (add color, description to definition)

### Frontend
- `resources/js/utils/useTags.ts` (add updateTag, bulkDeleteTags, fetchTagsWithUsageCounts)
- `resources/js/Pages/Tags.vue` (enhanced with search, sort, bulk operations)
- `resources/js/packages/ui/src/Tag/TagBadge.vue` (render with background color)
- `resources/js/packages/ui/src/Tag/TagDropdown.vue` (show color dots)
- `resources/js/packages/ui/src/Tag/TagCreateModal.vue` (color picker, description field)
- `resources/js/Components/Common/Tag/TagTable.vue` (search, sort, bulk select, usage counts)
- `resources/js/Components/Common/Tag/TagTableRow.vue` (color badge, description, usage count, checkbox)
- `resources/js/Components/Common/Tag/TagMoreOptionsDropdown.vue` (add edit option)
- `resources/js/Components/Common/Reporting/ReportingOverview.vue` (tag filter in filter bar)

### Tests
- `tests/Unit/Endpoint/Api/V1/TagEndpointTest.php` (new test cases for color, description, bulk)

## Quality Gates
- [ ] `composer fix && composer analyse` passes
- [ ] `npm run lint:fix && npm run format` passes
- [ ] `npm run build` succeeds
- [ ] All 4 database migrations tested (up and down)
- [ ] Tag CRUD endpoint tests pass (including color/description)
- [ ] Mandatory tags validation tests pass (including all exemptions)
- [ ] Custom field CRUD endpoint tests pass (all 4 types)
- [ ] Custom field value tests on time entry CRUD pass
- [ ] Tag bulk delete tests pass (with and without force)
- [ ] Frontend component tests pass (TagBadge, TagDropdown, CustomFieldFormRenderer, CustomFields page)
- [ ] E2E tests pass (tag color creation, mandatory tags, custom field value entry)
- [ ] OpenAPI spec updated (2 rounds) and TypeScript client regenerated
- [ ] No regressions in existing TimeEntryEndpointTest

## Planning Docs
- `PRD.md` -- Product requirements
- `task_assignments_20260209.md` -- Task breakdown
- `ARCHITECTURE.md` -- Technical architecture
- `CODEBASE-ANALYSIS.md` -- Integration analysis
- `SPRINT-PLAN.md` -- Sprint-by-sprint implementation plan
