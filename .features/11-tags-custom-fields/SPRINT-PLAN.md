# Sprint Plan: Tags and Custom Fields

**Date**: 2026-02-09
**Feature**: 11 - Tags and Custom Fields
**Branch**: `feature/tags-custom-fields` (from `main`)
**Task Prefix**: `TAG-`
**PRD Reference**: `.features/11-tags-custom-fields/PRD.md`
**Architecture Reference**: `.features/11-tags-custom-fields/ARCHITECTURE.md`

---

## 1. Executive Summary

The Tags and Custom Fields feature enhances Solidtime's existing tag system with colors, descriptions, a mandatory tags policy, and bulk operations, while introducing custom field definitions with typed values on time entries. The work spans tag backend enhancements, custom field backend infrastructure, tag frontend polish, custom field frontend components, and comprehensive testing across all layers.

**Total effort estimate**: 232 hours

**Total story points**: ~137 SP

**Number of sprints**: **4 sprints** (8 weeks)

**Team size assumptions**:
- 1 Backend Developer (senior, ~30 productive hours/sprint)
- 1 Frontend Developer (senior, ~30 productive hours/sprint)
- QA work shared between both developers or a dedicated QA engineer
- Concurrent work where dependency graph allows

**Key constraints**:
- Feature 00 (Weekly Timesheet Grid) must be merged to `main` before this feature branches off (mandatory tags exemption for timesheet cells)
- Backend tag enhancements and OpenAPI spec update must complete before frontend tag work can begin
- Custom field backend must complete and OpenAPI spec must be regenerated before custom field frontend work begins
- Testing spans the final 1.5 sprints to allow feature stabilization

---

## 2. Sprint Overview Table

| Sprint | Name | Duration | Story Points | Key Deliverables |
|--------|------|----------|:------------:|------------------|
| **1** | Tag Enhancements Backend + Frontend Start | 2 weeks | ~26 SP | Tag migrations, model updates, tag CRUD with color/description, mandatory tags validation, OpenAPI spec, TS client regen, permissions, tag badge/dropdown/create modal color support |
| **2** | Custom Fields Backend + Tag Frontend Polish | 2 weeks | ~34 SP | Custom field migrations, models, service, controller, routes, validation, CF on time entries, tag edit modal, mandatory tags UI toggle, tag management enhancements start |
| **3** | Custom Fields Frontend + Integration | 2 weeks | ~33 SP | CF on time entry controller, CF resource, OpenAPI regen, CF Pinia store, CF settings page, CF form renderer, tag management page polish, report tag filter, CF navigation |
| **4** | Integration, Polish, Testing | 2 weeks | ~44 SP | CF in time entry modals, CF in time entry rows, all backend tests, all frontend component tests, all E2E tests |

**Total**: ~137 SP / ~232h across 4 sprints (8 weeks)

---

## 3. Dependency Map

### 3.1 Task Dependencies

```
Wave 1 (No dependencies -- Sprint 1 start):
    TAG-001 (Tags migration: color + description, 2h)
    TAG-002 (Org migration: mandatory_tags, 1h)
    TAG-014 (CF migration: custom_fields table, 2h)
    TAG-021 (Register CF permissions, 2h)

Wave 2 (after Wave 1):
    TAG-003 (Tag model update, 2h)         <- TAG-001
    TAG-004 (Org model update, 1h)         <- TAG-002
    TAG-015 (CF values migration, 2h)      <- TAG-014
    TAG-016 (CustomField model, 3h)        <- TAG-014

Wave 3 (after Wave 2):
    TAG-005 (TagStoreRequest, 2h)          <- TAG-003
    TAG-006 (TagUpdateRequest, 2h)         <- TAG-003
    TAG-009 (TagResource update, 1h)       <- TAG-003
    TAG-010 (Mandatory tags store, 4h)     <- TAG-004
    TAG-011 (Mandatory tags update, 4h)    <- TAG-004
    TAG-017 (CustomFieldValue model, 2h)   <- TAG-015, TAG-016
    TAG-020 (CF request validation, 4h)    <- TAG-016
    TAG-023 (CF Resource/Collection, 2h)   <- TAG-016

Wave 4 (after Wave 3):
    TAG-007 (TagController store, 2h)      <- TAG-005
    TAG-008 (TagController update, 2h)     <- TAG-006
    TAG-012 (OpenAPI spec v1, 3h)          <- TAG-009, TAG-010
    TAG-018 (CustomFieldService, 8h)       <- TAG-016, TAG-017

Wave 5 (after Wave 4):
    TAG-013 (TS client regen v1, 1h)       <- TAG-012
    TAG-019 (CustomFieldController, 6h)    <- TAG-018
    TAG-024 (CF on TimeEntryStoreReq, 4h)  <- TAG-018
    TAG-025 (CF on TimeEntryUpdateReq, 3h) <- TAG-018

Wave 6 (after Wave 5):
    TAG-022 (CF routes, 2h)               <- TAG-019, TAG-021
    TAG-026 (TE store + CF values, 3h)    <- TAG-024, TAG-018
    TAG-027 (TE update + CF values, 3h)   <- TAG-025, TAG-018
    TAG-030 (TagBadge color, 3h)          <- TAG-013
    TAG-031 (TagDropdown color dots, 2h)  <- TAG-013
    TAG-032 (TagCreateModal enhanced, 4h) <- TAG-013
    TAG-033 (Tag edit modal, 4h)          <- TAG-013
    TAG-034 (Mandatory tags toggle, 4h)   <- TAG-013
    TAG-036 (TagService.ts, 3h)           <- TAG-013

Wave 7 (after Wave 6):
    TAG-028 (TE Resource + CF, 3h)        <- TAG-017
    TAG-035 (Tags required indicator, 3h) <- TAG-034
    TAG-037 (Tags mgmt page, 6h)         <- TAG-036
    TAG-029 (OpenAPI spec v2, 4h)         <- TAG-022, TAG-028

Wave 8 (after Wave 7):
    TAG-038 (Bulk select/delete, 4h)      <- TAG-037
    TAG-039 (Report tag filter, 4h)       <- TAG-031
    TAG-040 (TS client regen v2, 1h)      <- TAG-029

Wave 9 (after Wave 8):
    TAG-041 (CF Pinia store, 4h)          <- TAG-040

Wave 10 (after Wave 9):
    TAG-042 (CF settings page, 8h)        <- TAG-041
    TAG-043 (CF form renderer, 6h)        <- TAG-041

Wave 11 (after Wave 10):
    TAG-044 (CF in TE create modal, 4h)   <- TAG-043
    TAG-045 (CF in TE edit modal, 4h)     <- TAG-043
    TAG-046 (CF in TE row, 3h)            <- TAG-043
    TAG-047 (CF navigation, 2h)           <- TAG-042

Wave 12 (Testing -- after relevant feature tasks):
    TAG-048 (BE tests: tag CRUD, 4h)      <- TAG-007, TAG-008
    TAG-049 (BE tests: mandatory tags, 6h) <- TAG-010, TAG-011
    TAG-050 (BE tests: CF CRUD, 8h)       <- TAG-019
    TAG-051 (BE tests: CF values, 6h)     <- TAG-026, TAG-027
    TAG-052 (BE tests: tag bulk delete, 4h) <- TAG-007
    TAG-053 (FE tests: TagBadge/DD, 4h)   <- TAG-030, TAG-031
    TAG-054 (FE tests: CF renderer, 4h)   <- TAG-043
    TAG-055 (FE tests: CF page, 4h)       <- TAG-042
    TAG-056 (E2E: tags + mandatory, 6h)   <- TAG-034, TAG-035
    TAG-057 (E2E: custom fields, 6h)      <- TAG-044, TAG-045
```

### 3.2 Critical Path

```
TAG-014 (2h) -> TAG-016 (3h) -> TAG-018 (8h) -> TAG-019 (6h) -> TAG-022 (2h)
    -> TAG-029 (4h) -> TAG-040 (1h) -> TAG-041 (4h) -> TAG-043 (6h) -> TAG-044 (4h) -> TAG-057 (6h)
```

**Critical path duration**: 46 hours of sequential work

This is the custom fields end-to-end path. It is the longest dependency chain and determines the minimum project duration.

### 3.3 Parallelism Opportunities

| Wave | Backend | Frontend | Can run in parallel? |
|------|---------|----------|:--------------------:|
| 1 | TAG-001, TAG-002, TAG-014, TAG-021 | -- | Backend only (migrations) |
| 2-4 | TAG-003 through TAG-012 (tag backend) | -- | Backend only |
| 5 | TAG-013 (regen TS client) | -- | Bridge task |
| 6 | TAG-018, TAG-019 (CF backend) | TAG-030-034, TAG-036 (tag frontend) | Yes -- backend does CF while frontend does tags |
| 7-8 | TAG-022, TAG-026-029 (CF integration) | TAG-035, TAG-037-039 (tag mgmt) | Yes -- parallel tracks |
| 9-10 | -- | TAG-040-043 (CF frontend) | Frontend only |
| 11 | -- | TAG-044-047 (CF integration) | Frontend only |
| 12 | TAG-048-052 (backend tests) | TAG-053-057 (frontend + E2E tests) | Yes -- full parallel |

---

## 4. Sprint Details

### Sprint 1 (Weeks 1-2): Tag Enhancements Backend + Frontend Start

**Goal**: All tag enhancement backend work complete (migrations, models, controller, validation, OpenAPI), TS client regenerated, and initial frontend tag color work started.

| Task ID | Description | Assignee | SP | Dependencies | Day |
|---------|-------------|----------|:--:|--------------|:---:|
| TAG-001 | Migration: add color + description to tags | Backend | 1 | None | 1 |
| TAG-002 | Migration: add mandatory_tags to organizations | Backend | 1 | None | 1 |
| TAG-003 | Update Tag model with casts | Backend | 1 | TAG-001 | 1 |
| TAG-004 | Update Organization model with mandatory_tags | Backend | 1 | TAG-002 | 1 |
| TAG-005 | Update TagStoreRequest with color/description rules | Backend | 1 | TAG-003 | 2 |
| TAG-006 | Update TagUpdateRequest with color/description rules | Backend | 1 | TAG-003 | 2 |
| TAG-007 | Update TagController::store() for color/description | Backend | 1 | TAG-005 | 2 |
| TAG-008 | Update TagController::update() for color/description | Backend | 1 | TAG-006 | 3 |
| TAG-009 | Update TagResource with color, description, count | Backend | 1 | TAG-003 | 2 |
| TAG-010 | Mandatory tags on TimeEntryStoreRequest | Backend | 3 | TAG-004 | 3-4 |
| TAG-011 | Mandatory tags on TimeEntryUpdateRequest | Backend | 3 | TAG-004 | 4-5 |
| TAG-012 | Update OpenAPI spec (tag enhancements) | Backend | 2 | TAG-009, TAG-010 | 5-6 |
| TAG-013 | Regenerate TypeScript API client | Frontend | 1 | TAG-012 | 6 |
| TAG-021 | Register custom field permissions | Backend | 1 | None | 1 |
| TAG-030 | Update TagBadge.vue with color rendering | Frontend | 2 | TAG-013 | 7-8 |
| TAG-031 | Update TagDropdown.vue with color dots | Frontend | 1 | TAG-013 | 8 |
| TAG-032 | Update TagCreateModal.vue with color picker + description | Frontend | 3 | TAG-013 | 8-10 |

**Sprint 1 Total**: ~26 SP / ~38 hours

**Deliverables**:
- [ ] 2 database migrations (tags, organizations) created and tested
- [ ] Tag and Organization models updated with new casts
- [ ] Tag CRUD endpoints handle color and description
- [ ] TagResource returns color, description, and conditional usage count
- [ ] Mandatory tags validation on time entry store/update
- [ ] OpenAPI spec updated for tag enhancements
- [ ] TypeScript client regenerated
- [ ] Custom field permissions registered
- [ ] TagBadge renders with background color
- [ ] TagDropdown shows color dots
- [ ] TagCreateModal has color picker and description

**QA Gate**:
```bash
./vendor/bin/sail exec laravel.test composer fix
./vendor/bin/sail exec laravel.test composer analyse
./vendor/bin/sail exec laravel.test php artisan test --filter=TagEndpointTest
npm run lint:fix && npm run format
npm run build
```

---

### Sprint 2 (Weeks 3-4): Custom Fields Backend + Tag Frontend Polish

**Goal**: Complete custom field backend infrastructure (models, service, controller, routes, validation). Tag frontend polish (edit modal, mandatory tags toggle, tag service).

| Task ID | Description | Assignee | SP | Dependencies | Day |
|---------|-------------|----------|:--:|--------------|:---:|
| TAG-014 | Migration: create custom_fields table | Backend | 1 | None | 1 |
| TAG-015 | Migration: create custom_field_values table | Backend | 1 | TAG-014 | 1 |
| TAG-016 | Create CustomField model | Backend | 2 | TAG-014 | 2 |
| TAG-017 | Create CustomFieldValue model | Backend | 1 | TAG-015, TAG-016 | 2-3 |
| TAG-018 | Create CustomFieldService | Backend | 5 | TAG-016, TAG-017 | 3-5 |
| TAG-019 | Create CustomFieldController | Backend | 4 | TAG-018 | 5-7 |
| TAG-020 | CF request validation classes | Backend | 3 | TAG-016 | 3-4 |
| TAG-022 | Register CF API routes | Backend | 1 | TAG-019, TAG-021 | 7 |
| TAG-023 | CustomFieldResource/Collection | Backend | 1 | TAG-016 | 3 |
| TAG-024 | CF validation on TimeEntryStoreRequest | Backend | 3 | TAG-018 | 6-7 |
| TAG-025 | CF validation on TimeEntryUpdateRequest | Backend | 2 | TAG-018 | 7-8 |
| TAG-033 | Tag edit modal (new component) | Frontend | 3 | TAG-013 | 1-2 |
| TAG-034 | Mandatory tags toggle in org settings | Frontend | 3 | TAG-013 | 2-3 |
| TAG-035 | "Tags required" indicator on entry forms | Frontend | 2 | TAG-034 | 4 |
| TAG-036 | TagService.ts for bulk ops/usage counts | Frontend | 2 | TAG-013 | 1 |

**Sprint 2 Total**: ~34 SP / ~52 hours

**Deliverables**:
- [ ] 2 database migrations (custom_fields, custom_field_values) created and tested
- [ ] CustomField and CustomFieldValue models with relationships
- [ ] CustomFieldService with all validation and CRUD logic
- [ ] CustomFieldController with 5 endpoints
- [ ] CF request validation classes
- [ ] CF routes registered in api.php
- [ ] CustomFieldResource and CustomFieldCollection
- [ ] CF validation integrated into TimeEntryStoreRequest/UpdateRequest
- [ ] Tag edit modal component
- [ ] Mandatory tags toggle in organization settings
- [ ] "Tags required" indicator on time entry forms
- [ ] TagService.ts client utility for bulk operations

**QA Gate**:
```bash
./vendor/bin/sail exec laravel.test composer fix
./vendor/bin/sail exec laravel.test composer analyse
./vendor/bin/sail exec laravel.test php artisan test --filter=TagEndpointTest
./vendor/bin/sail exec laravel.test php artisan test --filter=TimeEntryEndpointTest
npm run lint:fix && npm run format
npm run build
```

**Manual Testing**:
- Create custom field via Postman/curl (each type)
- Update custom field configuration
- Archive and restore custom field
- Verify mandatory tags blocks time entry creation without tags
- Verify running timer is exempt from mandatory tags
- Verify tag edit modal saves color and description

---

### Sprint 3 (Weeks 5-6): Custom Fields Frontend + Integration

**Goal**: Complete time entry controller CF integration, custom fields frontend (Pinia store, settings page, form renderer), tag management page enhancements, report tag filter.

| Task ID | Description | Assignee | SP | Dependencies | Day |
|---------|-------------|----------|:--:|--------------|:---:|
| TAG-026 | TimeEntryController::store() CF values | Backend | 2 | TAG-024, TAG-018 | 1 |
| TAG-027 | TimeEntryController::update() CF values | Backend | 2 | TAG-025, TAG-018 | 1-2 |
| TAG-028 | TimeEntryResource CF values | Backend | 2 | TAG-017 | 2 |
| TAG-029 | Update OpenAPI spec for CF endpoints | Backend | 3 | TAG-022, TAG-028 | 3-4 |
| TAG-037 | Enhanced Tags management page | Frontend | 4 | TAG-036 | 1-3 |
| TAG-038 | Bulk select/delete on Tags page | Frontend | 3 | TAG-037 | 3-4 |
| TAG-039 | Tag filter on reporting page | Frontend | 3 | TAG-031 | 4-5 |
| TAG-040 | Regenerate TS client for CF | Frontend | 1 | TAG-029 | 5 |
| TAG-041 | useCustomFieldsStore.ts | Frontend | 3 | TAG-040 | 5-6 |
| TAG-042 | CustomFields.vue settings page | Frontend | 5 | TAG-041 | 6-8 |
| TAG-043 | CustomFieldFormRenderer.vue | Frontend | 4 | TAG-041 | 6-8 |
| TAG-047 | Custom Fields navigation | Frontend | 1 | TAG-042 | 9 |

**Sprint 3 Total**: ~33 SP / ~48 hours

**Deliverables**:
- [ ] Time entry create/update saves and returns custom field values
- [ ] TimeEntryResource includes `custom_fields` array
- [ ] OpenAPI spec updated for custom field endpoints and time entry CF fields
- [ ] Tags management page with search, sort, usage counts
- [ ] Bulk select and delete on Tags page
- [ ] Tag filter in report filter bar
- [ ] TypeScript client regenerated for CF endpoints
- [ ] useCustomFieldsStore Pinia store
- [ ] Custom Fields settings page with create/edit/archive
- [ ] CustomFieldFormRenderer renders all 4 field types
- [ ] Navigation link to Custom Fields page

**QA Gate**:
```bash
./vendor/bin/sail exec laravel.test composer fix
./vendor/bin/sail exec laravel.test composer analyse
npm run lint:fix && npm run format
npm run build
```

**Manual Testing**:
- Create time entry with custom field values via API
- Verify custom field values returned in time entry response
- Navigate to Custom Fields settings page
- Create custom field of each type (text, number, dropdown, checkbox)
- Verify form renderer shows correct input types
- Search and sort tags on management page
- Bulk delete tags with force option
- Filter reports by tag

---

### Sprint 4 (Weeks 7-8): Integration, Polish, Testing

**Goal**: Complete custom field integration in time entry modals, comprehensive test coverage across all layers, final polish.

| Task ID | Description | Assignee | SP | Dependencies | Day |
|---------|-------------|----------|:--:|--------------|:---:|
| TAG-044 | Custom fields in TimeEntryCreateModal | Frontend | 3 | TAG-043 | 1-2 |
| TAG-045 | Custom fields in TimeEntryEditModal | Frontend | 3 | TAG-043 | 2-3 |
| TAG-046 | Custom fields in TimeEntryRow | Frontend | 2 | TAG-043 | 3 |
| TAG-048 | BE tests: tag color/description CRUD | QA/Backend | 3 | TAG-007, TAG-008 | 1-2 |
| TAG-049 | BE tests: mandatory tags validation | QA/Backend | 4 | TAG-010, TAG-011 | 2-4 |
| TAG-050 | BE tests: CF CRUD endpoints | QA/Backend | 5 | TAG-019 | 3-5 |
| TAG-051 | BE tests: CF values on time entries | QA/Backend | 4 | TAG-026, TAG-027 | 5-6 |
| TAG-052 | BE tests: tag bulk delete | QA/Backend | 3 | TAG-007 | 2 |
| TAG-053 | FE tests: TagBadge, TagDropdown colors | QA/Frontend | 3 | TAG-030, TAG-031 | 4-5 |
| TAG-054 | FE tests: CustomFieldFormRenderer | QA/Frontend | 3 | TAG-043 | 5-6 |
| TAG-055 | FE tests: Custom Fields settings page | QA/Frontend | 3 | TAG-042 | 6-7 |
| TAG-056 | E2E tests: tags + mandatory tags | QA | 4 | TAG-034, TAG-035 | 7-8 |
| TAG-057 | E2E tests: custom fields | QA | 4 | TAG-044, TAG-045 | 8-10 |

**Sprint 4 Total**: ~44 SP / ~63 hours

**Deliverables**:
- [ ] Custom fields integrated into TimeEntryCreateModal
- [ ] Custom fields integrated into TimeEntryEditModal
- [ ] Custom field values visible in TimeEntryRow
- [ ] Backend endpoint tests for tag color/description CRUD
- [ ] Backend endpoint tests for mandatory tags validation (all exemptions)
- [ ] Backend endpoint tests for custom field CRUD endpoints
- [ ] Backend endpoint tests for custom field values on time entries
- [ ] Backend endpoint tests for tag bulk delete
- [ ] Frontend component tests for TagBadge and TagDropdown with colors
- [ ] Frontend component tests for CustomFieldFormRenderer (all 4 types)
- [ ] Frontend component tests for Custom Fields settings page
- [ ] E2E tests for tag creation with color and mandatory tags policy
- [ ] E2E tests for custom field creation and value entry

**QA Gate (Final)**:
```bash
./vendor/bin/sail exec laravel.test composer fix
./vendor/bin/sail exec laravel.test composer analyse
./vendor/bin/sail exec laravel.test php artisan test
npm run lint:fix && npm run format
npm run build
npx vitest run
npx playwright test
```

---

## 5. Risk Register

| Risk | Sprint | Probability | Impact | Mitigation |
|------|--------|:-----------:|:------:|------------|
| JSONB tag removal slow on force-delete (large orgs) | 2-3 | Medium | High | Batch updates in chunks of 1000; add progress feedback; consider background job for > 5000 entries |
| Mandatory tags breaks existing time entry creation flows | 1-2 | Medium | High | Exempt running timers and imports; clear error messages; admin warning when enabling with no tags |
| CustomFieldService complexity (8h task) causes bottleneck | 2 | Medium | High | Split into sub-tasks if needed (validation, CRUD, value storage); prioritize early in sprint |
| TagBadge color change affects all UI contexts | 1 | Medium | Medium | Test all contexts where TagBadge is used (management page, dropdown, time entry rows, reports) |
| TimeEntryStoreRequest modifications break existing tests | 1-2 | Medium | High | Run existing TimeEntryEndpointTest after each modification; add mandatory tags validation as a clearly separated block |
| Custom field type immutability frustrates users | 3-4 | Medium | Low | Clear UX messaging in CustomFieldCreateModal; archive + recreate flow documented |
| OpenAPI spec regeneration produces unexpected TS client changes | 1, 3 | Low | Medium | Diff the generated client carefully; run frontend build after each regeneration |
| E2E test flakiness | 4 | Medium | Low | Seed predictable test data; use explicit waits; isolate test state |

---

## 6. Definition of Done (Feature Complete)

- [ ] All 57 tasks (TAG-001 through TAG-057) completed
- [ ] 4 database migrations created and tested (up and down)
- [ ] Enhanced tag CRUD endpoints working with color and description
- [ ] Tag bulk delete endpoint working with force option
- [ ] Mandatory tags validation enforced on time entry create/update
- [ ] Mandatory tags exemptions working (running timers, timesheet cells, imports)
- [ ] Custom field CRUD endpoints working with all 4 field types
- [ ] Custom field values integrated into time entry create/update/read
- [ ] 4 new permissions registered (custom-fields:view/create/update/delete)
- [ ] TagBadge renders with colors throughout the application
- [ ] TagDropdown shows color dots
- [ ] Tag management page enhanced with search, sort, usage counts, bulk delete
- [ ] Organization settings page has mandatory tags toggle
- [ ] Custom Fields settings page functional with create/edit/archive/delete
- [ ] CustomFieldFormRenderer renders all 4 field types correctly
- [ ] Time entry forms show custom fields and validate required fields
- [ ] Report filter bar includes tag filter dropdown
- [ ] OpenAPI spec updated (2 rounds) and TypeScript client regenerated
- [ ] Backend endpoint tests passing for all new/modified endpoints
- [ ] Frontend component tests passing for core new components
- [ ] E2E tests passing for critical user paths
- [ ] `composer fix && composer analyse` passes with 0 new errors
- [ ] `npm run lint:fix && npm run format` passes
- [ ] `npm run build` succeeds

---

## 7. Post-Sprint: Merge Strategy

After all 4 sprints complete and QA passes:

1. Rebase `feature/tags-custom-fields` on latest `main`
2. Resolve any conflicts (highest risk: `TimeEntryStoreRequest`, `TimeEntryUpdateRequest`, `routes/api.php`)
3. Run full test suite:
   ```bash
   ./vendor/bin/sail exec laravel.test composer fix
   ./vendor/bin/sail exec laravel.test composer analyse
   ./vendor/bin/sail exec laravel.test php artisan test
   npm run lint:fix && npm run format
   npm run build
   npx vitest run
   npx playwright test
   ```
4. Create PR targeting `main`
5. After merge: Phase B features (custom fields on projects/tasks, tag hierarchies) can begin
