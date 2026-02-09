I'll continue from where I left off with the testing section and complete the architecture document.

---

## 13. Testing Strategy (Continued)

### 13.2 Endpoint Tests (Continued)

#### 13.2.1 AssignmentEndpointTest (Continued)

**File**: `tests/Unit/Endpoint/Api/V1/AssignmentEndpointTest.php`

```php
    public function test_destroy_endpoint_deletes_assignment(): void
    {
        // Arrange
        $data = $this->createUserWithPermission(['assignments:delete']);
        $assignment = Assignment::factory()->forOrganization($data->organization)->create();
        Passport::actingAs($data->user);

        // Act
        $response = $this->deleteJson(route('api.v1.assignments.destroy', [
            $data->organization->getKey(),
            $assignment->getKey(),
        ]));

        // Assert
        $response->assertNoContent();
        $this->assertDatabaseMissing('assignments', ['id' => $assignment->getKey()]);
    }
}
```

#### 13.2.2 MilestoneEndpointTest

**File**: `tests/Unit/Endpoint/Api/V1/MilestoneEndpointTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Endpoint\Api\V1;

use App\Http\Controllers\Api\V1\MilestoneController;
use App\Models\Milestone;
use App\Models\Project;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\UsesClass;

#[UsesClass(MilestoneController::class)]
class MilestoneEndpointTest extends ApiEndpointTestAbstract
{
    public function test_index_endpoint_returns_milestones_for_project(): void
    {
        // Arrange
        $data = $this->createUserWithPermission(['milestones:view']);
        $project = Project::factory()->forOrganization($data->organization)->create();
        $milestone = Milestone::factory()->forProject($project)->create();
        Passport::actingAs($data->user);

        // Act
        $response = $this->getJson(route('api.v1.milestones.index', [
            $data->organization->getKey(),
            $project->getKey(),
        ]));

        // Assert
        $response->assertOk();
        $response->assertJsonPath('data.0.id', $milestone->getKey());
    }

    public function test_store_endpoint_creates_milestone(): void
    {
        // Arrange
        $data = $this->createUserWithPermission(['milestones:create']);
        $project = Project::factory()->forOrganization($data->organization)->create();
        Passport::actingAs($data->user);

        // Act
        $response = $this->postJson(route('api.v1.milestones.store', [
            $data->organization->getKey(),
            $project->getKey(),
        ]), [
            'name' => 'Beta Launch',
            'description' => 'Launch beta version',
            'due_date' => '2026-04-15',
        ]);

        // Assert
        $response->assertCreated();
        $this->assertDatabaseHas('milestones', [
            'project_id' => $project->getKey(),
            'name' => 'Beta Launch',
        ]);
    }

    public function test_store_endpoint_fails_for_archived_project(): void
    {
        // Arrange
        $data = $this->createUserWithPermission(['milestones:create']);
        $project = Project::factory()->forOrganization($data->organization)->archived()->create();
        Passport::actingAs($data->user);

        // Act
        $response = $this->postJson(route('api.v1.milestones.store', [
            $data->organization->getKey(),
            $project->getKey(),
        ]), [
            'name' => 'Test Milestone',
            'due_date' => '2026-04-15',
        ]);

        // Assert
        $response->assertForbidden();
    }

    public function test_update_endpoint_can_mark_milestone_complete(): void
    {
        // Arrange
        $data = $this->createUserWithPermission(['milestones:update']);
        $project = Project::factory()->forOrganization($data->organization)->create();
        $milestone = Milestone::factory()->forProject($project)->create();
        Passport::actingAs($data->user);

        // Act
        $response = $this->putJson(route('api.v1.milestones.update', [
            $data->organization->getKey(),
            $project->getKey(),
            $milestone->getKey(),
        ]), [
            'name' => $milestone->name,
            'due_date' => $milestone->due_date->toDateString(),
            'is_completed' => true,
        ]);

        // Assert
        $response->assertOk();
        $this->assertDatabaseHas('milestones', [
            'id' => $milestone->getKey(),
        ]);
        $milestone->refresh();
        $this->assertNotNull($milestone->completed_at);
    }
}
```

#### 13.2.3 SchedulingEndpointTest

**File**: `tests/Unit/Endpoint/Api/V1/SchedulingEndpointTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Endpoint\Api\V1;

use App\Http\Controllers\Api\V1\SchedulingController;
use App\Models\Assignment;
use App\Models\Member;
use App\Models\Project;
use Illuminate\Support\Carbon;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\UsesClass;

#[UsesClass(SchedulingController::class)]
class SchedulingEndpointTest extends ApiEndpointTestAbstract
{
    public function test_timeline_endpoint_returns_timeline_data(): void
    {
        // Arrange
        $data = $this->createUserWithPermission(['scheduling:view:all']);
        $member = Member::factory()->forOrganization($data->organization)->create();
        $project = Project::factory()->forOrganization($data->organization)->create();
        Assignment::factory()->forMember($member)->forProject($project)->create();
        Passport::actingAs($data->user);

        // Act
        $response = $this->getJson(route('api.v1.scheduling.timeline', [
            $data->organization->getKey(),
            'start' => '2026-03-01',
            'end' => '2026-03-31',
        ]));

        // Assert
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'members',
                'milestones',
            ],
        ]);
    }

    public function test_capacity_endpoint_returns_capacity_summary(): void
    {
        // Arrange
        $data = $this->createUserWithPermission(['scheduling:view:all']);
        Passport::actingAs($data->user);

        // Act
        $response = $this->getJson(route('api.v1.scheduling.capacity', [
            $data->organization->getKey(),
            'start' => '2026-03-01',
            'end' => '2026-03-31',
        ]));

        // Assert
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'members',
            ],
        ]);
    }

    public function test_timeline_endpoint_filters_to_own_member_if_only_view_own_permission(): void
    {
        // Arrange
        $data = $this->createUserWithPermission(['scheduling:view']);
        $otherMember = Member::factory()->forOrganization($data->organization)->create();
        Assignment::factory()->forMember($otherMember)->forOrganization($data->organization)->create();
        Assignment::factory()->forMember($data->member)->forOrganization($data->organization)->create();
        Passport::actingAs($data->user);

        // Act
        $response = $this->getJson(route('api.v1.scheduling.timeline', [
            $data->organization->getKey(),
            'start' => '2026-03-01',
            'end' => '2026-03-31',
        ]));

        // Assert
        $response->assertOk();
        $members = $response->json('data.members');
        $this->assertCount(1, $members);
        $this->assertSame($data->member->id, $members[0]['member']['id']);
    }
}
```

### 13.3 Frontend Component Tests

**File**: `resources/js/packages/ui/src/Scheduling/__tests__/UtilizationBadge.test.ts`

```typescript
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import UtilizationBadge from '../UtilizationBadge.vue';

describe('UtilizationBadge', () => {
    it('shows destructive variant for over 100% utilization', () => {
        const wrapper = mount(UtilizationBadge, {
            props: { percentage: 120 },
        });
        expect(wrapper.text()).toContain('120%');
        expect(wrapper.html()).toContain('destructive');
    });

    it('shows warning variant for 80-100% utilization', () => {
        const wrapper = mount(UtilizationBadge, {
            props: { percentage: 85 },
        });
        expect(wrapper.text()).toContain('85%');
        expect(wrapper.html()).toContain('warning');
    });

    it('shows default variant for under 80% utilization', () => {
        const wrapper = mount(UtilizationBadge, {
            props: { percentage: 50 },
        });
        expect(wrapper.text()).toContain('50%');
    });
});
```

### 13.4 E2E Tests

**File**: `e2e/scheduling.spec.ts`

```typescript
import { test, expect } from '@playwright/test';

test.describe('Scheduling', () => {
    test.beforeEach(async ({ page }) => {
        // Login and navigate to scheduling page
        await page.goto('/login');
        await page.fill('[name="email"]', 'manager@example.com');
        await page.fill('[name="password"]', 'password');
        await page.click('button[type="submit"]');
        await page.waitForURL('/dashboard');
        await page.click('a[href*="/scheduling"]');
    });

    test('displays timeline view', async ({ page }) => {
        await expect(page.locator('h1')).toContainText('Resource Scheduling');
        await expect(page.locator('[data-testid="timeline"]')).toBeVisible();
    });

    test('can create an assignment', async ({ page }) => {
        await page.click('button:has-text("New Assignment")');
        await page.fill('[name="member_id"]', 'uuid-here'); // Use test data
        await page.fill('[name="project_id"]', 'uuid-here');
        await page.fill('[name="planned_seconds"]', '28800');
        await page.fill('[name="start_date"]', '2026-03-01');
        await page.fill('[name="end_date"]', '2026-05-31');
        await page.click('button[type="submit"]');
        await expect(page.locator('.notification')).toContainText('Assignment created');
    });

    test('can switch to capacity view', async ({ page }) => {
        await page.click('button:has-text("Capacity")');
        await expect(page.locator('table')).toBeVisible();
        await expect(page.locator('th:has-text("Utilization")')).toBeVisible();
    });
});
```

---

## 14. File Manifest

### 14.1 Backend Files to Create

**Migrations:**
- `/home/keven/Documents/solidtime-analysis/database/migrations/2026_03_08_000001_create_assignments_table.php`
- `/home/keven/Documents/solidtime-analysis/database/migrations/2026_03_08_000002_create_milestones_table.php`

**Models:**
- `/home/keven/Documents/solidtime-analysis/app/Models/Assignment.php`
- `/home/keven/Documents/solidtime-analysis/app/Models/Milestone.php`

**Factories:**
- `/home/keven/Documents/solidtime-analysis/database/factories/AssignmentFactory.php`
- `/home/keven/Documents/solidtime-analysis/database/factories/MilestoneFactory.php`

**Services:**
- `/home/keven/Documents/solidtime-analysis/app/Service/SchedulingService.php`

**Requests:**
- `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/Assignment/AssignmentStoreRequest.php`
- `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/Assignment/AssignmentUpdateRequest.php`
- `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/Milestone/MilestoneStoreRequest.php`
- `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/Milestone/MilestoneUpdateRequest.php`
- `/home/keven/Documents/solidtime-analysis/app/Http/Requests/V1/Scheduling/SchedulingTimelineRequest.php`

**Resources:**
- `/home/keven/Documents/solidtime-analysis/app/Http/Resources/V1/Assignment/AssignmentResource.php`
- `/home/keven/Documents/solidtime-analysis/app/Http/Resources/V1/Assignment/AssignmentCollection.php`
- `/home/keven/Documents/solidtime-analysis/app/Http/Resources/V1/Milestone/MilestoneResource.php`
- `/home/keven/Documents/solidtime-analysis/app/Http/Resources/V1/Milestone/MilestoneCollection.php`

**Controllers:**
- `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/AssignmentController.php`
- `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/MilestoneController.php`
- `/home/keven/Documents/solidtime-analysis/app/Http/Controllers/Api/V1/SchedulingController.php`

**Permissions:**
- `/home/keven/Documents/solidtime-analysis/app/Permissions/SchedulingPermissions.php`

**Tests:**
- `/home/keven/Documents/solidtime-analysis/tests/Unit/Service/SchedulingServiceTest.php`
- `/home/keven/Documents/solidtime-analysis/tests/Unit/Endpoint/Api/V1/AssignmentEndpointTest.php`
- `/home/keven/Documents/solidtime-analysis/tests/Unit/Endpoint/Api/V1/MilestoneEndpointTest.php`
- `/home/keven/Documents/solidtime-analysis/tests/Unit/Endpoint/Api/V1/SchedulingEndpointTest.php`

### 14.2 Backend Files to Modify

- `/home/keven/Documents/solidtime-analysis/app/Models/Project.php` (add relationships)
- `/home/keven/Documents/solidtime-analysis/app/Models/Member.php` (add relationships, cast)
- `/home/keven/Documents/solidtime-analysis/app/Models/Organization.php` (add relationships, cast)
- `/home/keven/Documents/solidtime-analysis/routes/api.php` (add routes)
- `/home/keven/Documents/solidtime-analysis/routes/web.php` (add scheduling page route)
- `/home/keven/Documents/solidtime-analysis/app/Providers/JetstreamServiceProvider.php` (register permissions)

### 14.3 Frontend Files to Create

**Types:**
- `/home/keven/Documents/solidtime-analysis/resources/js/types/scheduling.d.ts`

**Store:**
- `/home/keven/Documents/solidtime-analysis/resources/js/utils/useScheduling.ts`

**Pages:**
- `/home/keven/Documents/solidtime-analysis/resources/js/Pages/Scheduling.vue`

**Components:**
- `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Scheduling/ScheduleTimeline.vue`
- `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Scheduling/UtilizationBadge.vue`
- `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Scheduling/AssignmentForm.vue`
- `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Scheduling/CapacityPanel.vue`
- `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Scheduling/MilestoneSection.vue`

**Tests:**
- `/home/keven/Documents/solidtime-analysis/resources/js/packages/ui/src/Scheduling/__tests__/UtilizationBadge.test.ts`
- `/home/keven/Documents/solidtime-analysis/e2e/scheduling.spec.ts`

### 14.4 Frontend Files to Modify

- `/home/keven/Documents/solidtime-analysis/resources/js/utils/permissions.ts` (add permission helpers)
- `/home/keven/Documents/solidtime-analysis/resources/js/Layouts/AppLayout.vue` (add sidebar navigation)

---

## 15. Implementation Sequence

### Sprint 1 (Wave 1-3): Foundation & Models
**Duration**: 2 weeks | **Effort**: ~40 hours

**Week 1: Database & Models**
- [ ] SCHED-001: Create assignments table migration (4h)
- [ ] SCHED-002: Create milestones table migration (3h)
- [ ] SCHED-004: Assignment model + factory (4h)
- [ ] SCHED-005: Milestone model + factory (3h)
- [ ] SCHED-006: Update existing models with relationships (2h)
- [ ] SCHED-007: Register permissions (2h)

**Week 2: Service Layer**
- [ ] SCHED-008: SchedulingService core business logic (12h)
- [ ] SCHED-009: Assignment form requests (4h)
- [ ] SCHED-010: Milestone form requests (3h)
- [ ] SCHED-011: API resources (4h)

**Deliverable**: Database schema, models, and service layer complete

---

### Sprint 2 (Wave 4-6): API Layer
**Duration**: 2 weeks | **Effort**: ~46 hours

**Week 3: Controllers & Routes**
- [ ] SCHED-012: AssignmentController CRUD (8h)
- [ ] SCHED-013: MilestoneController CRUD (8h)
- [ ] SCHED-014: SchedulingController aggregation endpoints (8h)
- [ ] SCHED-015: Register API routes (2h)
- [ ] SCHED-016: Register web route (1h)

**Week 4: Testing & OpenAPI**
- [ ] SCHED-027: Assignment endpoint tests (8h)
- [ ] SCHED-028: Milestone endpoint tests (5h)
- [ ] SCHED-029: SchedulingService unit tests (8h)
- [ ] SCHED-030: Scheduling endpoint tests (5h)
- [ ] SCHED-017: Update OpenAPI spec + regenerate TS client (4h)

**Deliverable**: Complete API with tests

---

### Sprint 3 (Wave 7-9): Frontend Core
**Duration**: 2 weeks | **Effort**: ~62 hours

**Week 5: Store & Types**
- [ ] SCHED-018: TypeScript type definitions (3h)
- [ ] SCHED-019: Pinia store `useSchedulingStore` (12h)
- [ ] SCHED-020: Frontend permission helpers (1h)
- [ ] SCHED-021: Scheduling page (8h)
- [ ] SCHED-022: Add sidebar navigation (1h)

**Week 6: Timeline Component**
- [ ] SCHED-023: Timeline component + sub-components (20h)
- [ ] SCHED-024: Assignment form modal (8h)
- [ ] SCHED-025: Capacity panel + utilization badge (8h)

**Deliverable**: Functional scheduling UI with timeline

---

### Sprint 4 (Wave 10): Milestones & Polish
**Duration**: 2 weeks | **Effort**: ~42 hours

**Week 7: Milestones & Testing**
- [ ] SCHED-026: Milestone section for ProjectShow (8h)
- [ ] SCHED-031: Vitest component tests (8h)
- [ ] SCHED-032: E2E Playwright tests (8h)
- [ ] SCHED-033: Cascade deletion logic (3h)

**Week 8: Configuration & Documentation**
- [ ] SCHED-034: Organization/member weekly capacity settings (5h)
- [ ] SCHED-035: Documentation (JSDoc, PHPDoc, CLAUDE.md update) (3h)
- [ ] Bug fixes and refinements (7h)

**Deliverable**: Complete feature with tests and documentation

---

### Sprint 5: Integration & Deployment
**Duration**: 2 weeks | **Effort**: ~35 hours (buffer)

**Week 9: Integration Testing**
- [ ] Integration testing with existing features
- [ ] Performance testing (timeline with 50+ members)
- [ ] Cross-browser testing
- [ ] Accessibility audit (WCAG 2.1 AA)

**Week 10: Deployment & Handoff**
- [ ] Staging deployment
- [ ] Production deployment
- [ ] User acceptance testing
- [ ] Documentation handoff
- [ ] Knowledge transfer

**Deliverable**: Production-ready feature

---

## 16. Dependencies & Blockers

### 16.1 Hard Dependencies

| Dependency | Type | Status | Impact |
|---|---|---|---|
| FOUND-006 | Shared migration | Must complete first | Blocks SCHED-008 (capacity calculation) |
| FOUND-007 | Permissions infrastructure | Should complete first | Simplifies SCHED-007 |

### 16.2 Soft Dependencies

| Dependency | Type | Enhancement |
|---|---|---|
| Feature 07 (PTO) | Optional integration | Capacity reduction for approved time off |
| Feature 10 (Teams) | Optional integration | Team-based filtering in timeline |

### 16.3 Risk Mitigation

**Risk**: SCHED-023 (Timeline component) is highest-effort frontend task (20h)
**Mitigation**: Start early in Sprint 3; evaluate timeline library options in architecture phase; implement simplified table fallback if library integration exceeds estimate

**Risk**: SchedulingService complexity may exceed estimate
**Mitigation**: Pair-program on SCHED-008; break into smaller subtasks if needed

**Risk**: Cross-feature integration with PTO/Teams may introduce bugs
**Mitigation**: Feature flags for integration; comprehensive integration tests; graceful degradation if dependencies unavailable

---

## 17. Performance Considerations

### 17.1 Query Optimization

**Timeline endpoint**:
- Eager load relationships: `with(['project', 'member.user'])`
- Use indexed date range queries
- Limit result set with date window
- Expected response time: < 500ms for 50 members, 200 assignments

**Capacity endpoint**:
- Aggregate queries at database level
- Cache results for 5 minutes (configurable)
- Expected response time: < 300ms

### 17.2 Database Indexes

All indexes defined in migrations:
- `idx_assignments_org_member` (organization_id, member_id)
- `idx_assignments_org_project` (organization_id, project_id)
- `idx_assignments_date_range` (start_date, end_date)
- `idx_assignments_member_dates` (member_id, start_date, end_date)
- `idx_milestones_project_date` (project_id, due_date)
- `idx_milestones_org` (organization_id)

### 17.3 Frontend Optimization

- Virtualized scrolling for member lists (50+ members)
- Debounced timeline navigation
- Optimistic UI updates for assignment CRUD
- Lazy load milestone details

---

## 18. Security Considerations

### 18.1 Permission Enforcement

- All endpoints check permissions via `checkPermission()` helper
- Organization scoping on all queries (`whereBelongsTo($organization)`)
- Employee role limited to own data (`assignments:view:own`)
- Placeholder members explicitly blocked from assignments (AMD-06)

### 18.2 Input Validation

- All dates validated as Y-m-d format
- `planned_seconds` capped at 604800 (1 week)
- Member must be ProjectMember of target project
- Project must not be archived

### 18.3 Audit Logging

- All Assignment and Milestone models use `CustomAuditable` trait
- CREATE, UPDATE, DELETE operations logged
- Audit trail includes user, timestamp, and changed values

---

## 19. Documentation Requirements

### 19.1 Code Documentation

- PHPDoc blocks for all service methods
- JSDoc comments for Pinia store (following TASK-20 pattern)
- Inline comments for complex business logic

### 19.2 CLAUDE.md Updates

Add to existing CLAUDE.md:

```markdown
## Resource Scheduling

### Models
- `Assignment`: Resource allocation linking member to project with planned hours
- `Milestone`: Project milestone with due date and completion tracking

### Services
- `SchedulingService`: Capacity calculation, timeline assembly, scheduled vs. tracked comparison

### Permissions
- `assignments:view`, `assignments:view:own`, `assignments:create`, `assignments:update`, `assignments:delete`
- `milestones:view`, `milestones:create`, `milestones:update`, `milestones:delete`
- `scheduling:view`, `scheduling:view:all`

### API Endpoints
- `GET /organizations/{org}/assignments` — List assignments
- `POST /organizations/{org}/assignments` — Create assignment
- `GET /organizations/{org}/projects/{project}/milestones` — List milestones
- `GET /organizations/{org}/scheduling/timeline` — Timeline data
- `GET /organizations/{org}/scheduling/capacity` — Capacity summary

### Frontend
- **Page**: `resources/js/Pages/Scheduling.vue`
- **Store**: `useSchedulingStore` in `resources/js/utils/useScheduling.ts`
- **Components**: `ScheduleTimeline`, `AssignmentForm`, `CapacityPanel`, `MilestoneSection`
```

---

## 20. Success Metrics & Definition of Done

### 20.1 Functional Completeness

- [ ] All 35 tasks completed
- [ ] All acceptance criteria from PRD user stories met
- [ ] 100% test coverage on service layer
- [ ] All API endpoints tested with success and error cases

### 20.2 Quality Metrics

- [ ] PHPStan level 9 passes (`composer analyse`)
- [ ] PHP-CS-Fixer passes (`composer fix`)
- [ ] ESLint passes (`npm run lint:fix`)
- [ ] All E2E tests pass
- [ ] No security vulnerabilities in dependencies

### 20.3 Performance Metrics

- [ ] Timeline endpoint < 500ms for 50 members, 200 assignments
- [ ] Capacity endpoint < 300ms
- [ ] Frontend initial render < 2s

### 20.4 Documentation

- [ ] All code documented with PHPDoc/JSDoc
- [ ] CLAUDE.md updated
- [ ] OpenAPI spec complete and accurate
- [ ] E2E test scenarios documented

### 20.5 Deployment Readiness

- [ ] Migrations tested with rollback
- [ ] Database indexes validated
- [ ] Permissions registered and tested
- [ ] Staging deployment successful
- [ ] Production deployment plan approved

---

## 21. Summary

This architecture document provides a complete blueprint for implementing Feature 08: Resource Scheduling in Solidtime. The implementation follows established codebase patterns, integrates with shared foundations (FOUND-006 for weekly_capacity), and provides hooks for future integration with Features 07 (PTO) and 10 (Teams).

**Key Deliverables**:
- 2 new database tables (assignments, milestones)
- 3 API controllers with 10 endpoints
- 1 comprehensive service class
- 5 Vue components including timeline visualization
- Complete test suite (unit, endpoint, component, E2E)
- Full documentation and permission system

**Implementation Timeline**: 10 weeks across 5 sprints, with clear dependencies and risk mitigation strategies.

**Next Steps**: Begin Sprint 1 with database migrations and model creation after FOUND-006 is confirmed complete.