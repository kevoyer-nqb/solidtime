# Task Assignments: Tags and Custom Fields

Generated: 2026-02-09
Feature Branch: `feature/tags-custom-fields`
PRD Reference: `PRD.md`

---

## Task Assignment Table

| Task ID  | Description                                                           | Type                | Assigned Sub-Agent | Dependencies          | Effort  | Status |
|----------|-----------------------------------------------------------------------|---------------------|--------------------|-----------------------|---------|--------|
| TAG-001  | Migration: add `color` and `description` columns to `tags` table     | Backend / Migration | Backend Dev        | None                  | 2 hours | To Do  |
| TAG-002  | Migration: add `mandatory_tags` column to `organizations` table      | Backend / Migration | Backend Dev        | None                  | 1 hour  | To Do  |
| TAG-003  | Update `Tag` model with new casts and properties                     | Backend / Model     | Backend Dev        | TAG-001               | 2 hours | To Do  |
| TAG-004  | Update `Organization` model with `mandatory_tags` cast               | Backend / Model     | Backend Dev        | TAG-002               | 1 hour  | To Do  |
| TAG-005  | Update `TagStoreRequest` with `color` and `description` rules        | Backend / Validation| Backend Dev        | TAG-003               | 2 hours | To Do  |
| TAG-006  | Update `TagUpdateRequest` with `color` and `description` rules       | Backend / Validation| Backend Dev        | TAG-003               | 2 hours | To Do  |
| TAG-007  | Update `TagController::store()` for color and description            | Backend / Controller| Backend Dev        | TAG-005               | 2 hours | To Do  |
| TAG-008  | Update `TagController::update()` for color and description           | Backend / Controller| Backend Dev        | TAG-006               | 2 hours | To Do  |
| TAG-009  | Update `TagResource` to include `color` and `description`            | Backend / Resource  | Backend Dev        | TAG-003               | 1 hour  | To Do  |
| TAG-010  | Add mandatory tags validation to `TimeEntryStoreRequest`             | Backend / Validation| Backend Dev        | TAG-004               | 4 hours | To Do  |
| TAG-011  | Add mandatory tags validation to `TimeEntryUpdateRequest`            | Backend / Validation| Backend Dev        | TAG-004               | 4 hours | To Do  |
| TAG-012  | Update OpenAPI spec with enhanced tag and organization fields        | Backend / API Spec  | Backend Dev        | TAG-009, TAG-010      | 3 hours | To Do  |
| TAG-013  | Regenerate TypeScript API client from updated OpenAPI spec           | Frontend / API      | Frontend Dev       | TAG-012               | 1 hour  | To Do  |
| TAG-014  | Migration: create `custom_fields` table                              | Backend / Migration | Backend Dev        | None                  | 2 hours | To Do  |
| TAG-015  | Migration: create `custom_field_values` table                        | Backend / Migration | Backend Dev        | TAG-014               | 2 hours | To Do  |
| TAG-016  | Create `CustomField` model with relationships and casts              | Backend / Model     | Backend Dev        | TAG-014               | 3 hours | To Do  |
| TAG-017  | Create `CustomFieldValue` model with relationships                   | Backend / Model     | Backend Dev        | TAG-015, TAG-016      | 2 hours | To Do  |
| TAG-018  | Create `CustomFieldService` with validation and CRUD logic           | Backend / Service   | Backend Dev        | TAG-016, TAG-017      | 8 hours | To Do  |
| TAG-019  | Create `CustomFieldController` with all endpoints                    | Backend / Controller| Backend Dev        | TAG-018               | 6 hours | To Do  |
| TAG-020  | Create request validation classes for custom fields                  | Backend / Validation| Backend Dev        | TAG-016               | 4 hours | To Do  |
| TAG-021  | Register custom field permissions in `CorePermissions`               | Backend / Auth      | Backend Dev        | None                  | 2 hours | To Do  |
| TAG-022  | Register custom field API routes in `routes/api.php`                 | Backend / Routing   | Backend Dev        | TAG-019, TAG-021      | 2 hours | To Do  |
| TAG-023  | Add `CustomFieldResource` and `CustomFieldCollection`                | Backend / Resource  | Backend Dev        | TAG-016               | 2 hours | To Do  |
| TAG-024  | Enhance `TimeEntryStoreRequest` to validate custom field values      | Backend / Validation| Backend Dev        | TAG-018               | 4 hours | To Do  |
| TAG-025  | Enhance `TimeEntryUpdateRequest` to validate custom field values     | Backend / Validation| Backend Dev        | TAG-018               | 3 hours | To Do  |
| TAG-026  | Enhance `TimeEntryController::store()` to save custom field values   | Backend / Controller| Backend Dev        | TAG-024, TAG-018      | 3 hours | To Do  |
| TAG-027  | Enhance `TimeEntryController::update()` to save custom field values  | Backend / Controller| Backend Dev        | TAG-025, TAG-018      | 3 hours | To Do  |
| TAG-028  | Enhance `TimeEntryResource` to include custom field values           | Backend / Resource  | Backend Dev        | TAG-017               | 3 hours | To Do  |
| TAG-029  | Update OpenAPI spec with custom field endpoints and CF on entries     | Backend / API Spec  | Backend Dev        | TAG-022, TAG-028      | 4 hours | To Do  |
| TAG-030  | Update `TagBadge.vue` to render with tag color                       | Frontend / UI       | Frontend Dev       | TAG-013               | 3 hours | To Do  |
| TAG-031  | Update `TagDropdown.vue` to show color dots                          | Frontend / UI       | Frontend Dev       | TAG-013               | 2 hours | To Do  |
| TAG-032  | Update `TagCreateModal.vue` with color picker and description        | Frontend / UI       | Frontend Dev       | TAG-013               | 4 hours | To Do  |
| TAG-033  | Create tag edit modal or inline editing on tag table                 | Frontend / UI       | Frontend Dev       | TAG-013               | 4 hours | To Do  |
| TAG-034  | Add mandatory tags toggle to organization settings page              | Frontend / UI       | Frontend Dev       | TAG-013               | 4 hours | To Do  |
| TAG-035  | Update time entry forms to show "Tags required" indicator            | Frontend / UI       | Frontend Dev       | TAG-034               | 3 hours | To Do  |
| TAG-036  | Create `TagService.ts` for bulk operations and usage counts          | Frontend / Store    | Frontend Dev       | TAG-013               | 3 hours | To Do  |
| TAG-037  | Enhance Tags management page with search, sort, usage counts         | Frontend / Page     | Frontend Dev       | TAG-036               | 6 hours | To Do  |
| TAG-038  | Add bulk select and bulk delete to Tags management page              | Frontend / UI       | Frontend Dev       | TAG-037               | 4 hours | To Do  |
| TAG-039  | Add tag filter to reporting filter bar                               | Frontend / UI       | Frontend Dev       | TAG-031               | 4 hours | To Do  |
| TAG-040  | Regenerate TypeScript API client with custom field endpoints         | Frontend / API      | Frontend Dev       | TAG-029               | 1 hour  | To Do  |
| TAG-041  | Create `useCustomFieldsStore.ts` Pinia store                        | Frontend / Store    | Frontend Dev       | TAG-040               | 4 hours | To Do  |
| TAG-042  | Create Custom Fields settings page (`CustomFields.vue`)              | Frontend / Page     | Frontend Dev       | TAG-041               | 8 hours | To Do  |
| TAG-043  | Create `CustomFieldFormRenderer.vue` component                       | Frontend / UI       | Frontend Dev       | TAG-041               | 6 hours | To Do  |
| TAG-044  | Integrate custom fields into `TimeEntryCreateModal.vue`              | Frontend / UI       | Frontend Dev       | TAG-043               | 4 hours | To Do  |
| TAG-045  | Integrate custom fields into `TimeEntryEditModal.vue`                | Frontend / UI       | Frontend Dev       | TAG-043               | 4 hours | To Do  |
| TAG-046  | Show custom field values in `TimeEntryRow.vue`                       | Frontend / UI       | Frontend Dev       | TAG-043               | 3 hours | To Do  |
| TAG-047  | Add navigation for Custom Fields settings page                       | Frontend / UI       | Frontend Dev       | TAG-042               | 2 hours | To Do  |
| TAG-048  | Backend tests: tag color/description on CRUD endpoints               | Testing / Backend   | QA Dev             | TAG-007, TAG-008      | 4 hours | To Do  |
| TAG-049  | Backend tests: mandatory tags validation on time entries             | Testing / Backend   | QA Dev             | TAG-010, TAG-011      | 6 hours | To Do  |
| TAG-050  | Backend tests: custom field CRUD endpoints                           | Testing / Backend   | QA Dev             | TAG-019               | 8 hours | To Do  |
| TAG-051  | Backend tests: custom field values on time entry CRUD                | Testing / Backend   | QA Dev             | TAG-026, TAG-027      | 6 hours | To Do  |
| TAG-052  | Backend tests: tag bulk delete endpoint                              | Testing / Backend   | QA Dev             | TAG-007               | 4 hours | To Do  |
| TAG-053  | Frontend component tests: TagBadge, TagDropdown with colors          | Testing / Frontend  | QA Dev             | TAG-030, TAG-031      | 4 hours | To Do  |
| TAG-054  | Frontend component tests: CustomFieldFormRenderer                    | Testing / Frontend  | QA Dev             | TAG-043               | 4 hours | To Do  |
| TAG-055  | Frontend component tests: Custom Fields settings page                | Testing / Frontend  | QA Dev             | TAG-042               | 4 hours | To Do  |
| TAG-056  | E2E tests: tag creation with color, mandatory tags policy            | Testing / E2E       | QA Dev             | TAG-034, TAG-035      | 6 hours | To Do  |
| TAG-057  | E2E tests: custom field creation and value entry on time entries     | Testing / E2E       | QA Dev             | TAG-044, TAG-045      | 6 hours | To Do  |

---

## Summary

```
Total Tasks: 57
Total Effort: 232 hours (~155 SP across 4 sprints)
Duration: 8 weeks (4 two-week sprints)
Team Size Required: 2-3 developers

Backend Dev Tasks: TAG-001 through TAG-029 (29 tasks, ~81 hours)
Frontend Dev Tasks: TAG-030 through TAG-047 (18 tasks, ~63 hours)
QA Dev Tasks: TAG-048 through TAG-057 (10 tasks, ~52 hours)
```

---

## Sprint Plan

### Sprint 1 (Weeks 1-2): Tag Enhancements Backend + Frontend Start
**Focus**: Migrations, model updates, tag CRUD enhancements, OpenAPI spec update, start frontend tag color work.

| Task ID | Description | Effort |
|---------|-------------|--------|
| TAG-001 | Migration: tags color + description | 2h |
| TAG-002 | Migration: organizations mandatory_tags | 1h |
| TAG-003 | Update Tag model | 2h |
| TAG-004 | Update Organization model | 1h |
| TAG-005 | Update TagStoreRequest | 2h |
| TAG-006 | Update TagUpdateRequest | 2h |
| TAG-007 | Update TagController::store() | 2h |
| TAG-008 | Update TagController::update() | 2h |
| TAG-009 | Update TagResource | 1h |
| TAG-010 | Mandatory tags on TimeEntryStoreRequest | 4h |
| TAG-011 | Mandatory tags on TimeEntryUpdateRequest | 4h |
| TAG-012 | Update OpenAPI spec | 3h |
| TAG-013 | Regenerate TS API client | 1h |
| TAG-021 | Register custom field permissions | 2h |
| TAG-030 | Update TagBadge.vue with color | 3h |
| TAG-031 | Update TagDropdown.vue with color dots | 2h |
| TAG-032 | Update TagCreateModal.vue | 4h |

**Sprint 1 Total**: ~38 hours

### Sprint 2 (Weeks 3-4): Custom Fields Backend + Tag Frontend Polish
**Focus**: Custom field models, service, controller, routes, validation. Tag management UI enhancements.

| Task ID | Description | Effort |
|---------|-------------|--------|
| TAG-014 | Migration: custom_fields table | 2h |
| TAG-015 | Migration: custom_field_values table | 2h |
| TAG-016 | CustomField model | 3h |
| TAG-017 | CustomFieldValue model | 2h |
| TAG-018 | CustomFieldService | 8h |
| TAG-019 | CustomFieldController | 6h |
| TAG-020 | Custom field request validation | 4h |
| TAG-022 | Custom field API routes | 2h |
| TAG-023 | CustomFieldResource/Collection | 2h |
| TAG-024 | CF validation on TimeEntryStoreRequest | 4h |
| TAG-025 | CF validation on TimeEntryUpdateRequest | 3h |
| TAG-033 | Tag edit modal | 4h |
| TAG-034 | Mandatory tags org settings toggle | 4h |
| TAG-035 | "Tags required" indicator on entry forms | 3h |
| TAG-036 | TagService.ts for bulk ops | 3h |

**Sprint 2 Total**: ~52 hours

### Sprint 3 (Weeks 5-6): Custom Fields Frontend + Integration
**Focus**: Time entry controller CF integration, custom fields frontend, report tag filter.

| Task ID | Description | Effort |
|---------|-------------|--------|
| TAG-026 | TimeEntryController::store() CF values | 3h |
| TAG-027 | TimeEntryController::update() CF values | 3h |
| TAG-028 | TimeEntryResource CF values | 3h |
| TAG-029 | Update OpenAPI spec for CF endpoints | 4h |
| TAG-037 | Enhanced Tags management page | 6h |
| TAG-038 | Bulk select/delete on Tags page | 4h |
| TAG-039 | Tag filter on reporting page | 4h |
| TAG-040 | Regenerate TS client for CF | 1h |
| TAG-041 | useCustomFieldsStore.ts | 4h |
| TAG-042 | CustomFields.vue settings page | 8h |
| TAG-043 | CustomFieldFormRenderer.vue | 6h |
| TAG-047 | Custom Fields navigation | 2h |

**Sprint 3 Total**: ~48 hours

### Sprint 4 (Weeks 7-8): Integration, Polish, Testing
**Focus**: Time entry form integration, testing, polish.

| Task ID | Description | Effort |
|---------|-------------|--------|
| TAG-044 | Custom fields in TimeEntryCreateModal | 4h |
| TAG-045 | Custom fields in TimeEntryEditModal | 4h |
| TAG-046 | Custom fields in TimeEntryRow | 3h |
| TAG-048 | Backend tests: tag color/description | 4h |
| TAG-049 | Backend tests: mandatory tags | 6h |
| TAG-050 | Backend tests: CF CRUD | 8h |
| TAG-051 | Backend tests: CF values on entries | 6h |
| TAG-052 | Backend tests: tag bulk delete | 4h |
| TAG-053 | Frontend tests: TagBadge, TagDropdown | 4h |
| TAG-054 | Frontend tests: CF form renderer | 4h |
| TAG-055 | Frontend tests: CF settings page | 4h |
| TAG-056 | E2E tests: tags + mandatory | 6h |
| TAG-057 | E2E tests: custom fields | 6h |

**Sprint 4 Total**: ~63 hours

---

## Critical Path

The minimum duration is determined by the longest dependency chain:

**Path 1 (Tag Enhancements)**:
TAG-001 (2h) -> TAG-003 (2h) -> TAG-005 (2h) -> TAG-007 (2h) -> TAG-012 (3h) -> TAG-013 (1h) -> TAG-030 (3h) -> TAG-053 (4h)
**Total**: 19 hours

**Path 2 (Custom Fields End-to-End)**:
TAG-014 (2h) -> TAG-016 (3h) -> TAG-018 (8h) -> TAG-019 (6h) -> TAG-022 (2h) -> TAG-029 (4h) -> TAG-040 (1h) -> TAG-041 (4h) -> TAG-043 (6h) -> TAG-044 (4h) -> TAG-057 (6h)
**Total**: 46 hours (critical path)

**Path 3 (Mandatory Tags)**:
TAG-002 (1h) -> TAG-004 (1h) -> TAG-010 (4h) -> TAG-012 (3h) -> TAG-013 (1h) -> TAG-034 (4h) -> TAG-035 (3h) -> TAG-056 (6h)
**Total**: 23 hours

The critical path is **Path 2** at 46 hours of sequential work. Parallelizing Path 1 and Path 3 alongside Path 2 reduces overall calendar time.

---

## Dependency Risk Assessment

1. **TAG-018 (CustomFieldService)** is a high-risk bottleneck at 8 hours of effort with multiple downstream dependents (TAG-019, TAG-024, TAG-025, TAG-026, TAG-027). Delay here blocks the entire custom fields track. **Mitigation**: Prioritize this task; consider splitting into smaller sub-tasks (validation logic, CRUD logic, value storage logic).

2. **TAG-012 (OpenAPI spec update)** blocks all frontend work for tag enhancements. **Mitigation**: Complete this early in Sprint 1; frontend dev can start on non-API-dependent UI work (color picker component, layout) in parallel.

3. **TAG-029 (OpenAPI spec for CF)** blocks all custom field frontend work. **Mitigation**: Backend dev completes this as soon as controller is functional; frontend dev works on tag enhancements (TAG-037, TAG-038, TAG-039) while waiting.

4. **TAG-043 (CustomFieldFormRenderer)** is required by three frontend tasks (TAG-044, TAG-045, TAG-046). **Mitigation**: Prioritize this component early in Sprint 3.
