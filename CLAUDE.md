# Solidtime - Weekly Timesheet Feature

## Project
Solidtime is an open-source time tracking application built with Laravel 11 + Vue 3 + TypeScript + Pinia + Inertia.js.

**Feature branch**: `feature/weekly-timesheet-grid`
**Task reference**: `/home/keven/Documents/kimai-platform/docs/tasks/CONSOLIDATED-TASKS.md`

## Code Style

### PHP
- `declare(strict_types=1)` at top of every file
- 4-space indent, LF line endings
- Run `composer fix && composer analyse` before committing

### JS/TS
- ESLint + Prettier
- Run `npm run lint:fix && npm run format` before committing

## Architecture Patterns

### Routing
- **API**: `routes/api.php` — `Route::name('v1.{feature}.')->prefix('/organizations/{organization}')->group(...)` with `check-organization-blocked` middleware on write endpoints
- **Web**: `routes/web.php` — `Inertia::render('PageName')` inside `auth:web` middleware group

### Controllers
- Location: `app/Http/Controllers/Api/V1/`
- Extend `App\Http\Controllers\Api\V1\Controller` (which injects `PermissionStore`)
- Use `$this->checkPermission($organization, 'permission-name')` for auth
- Use `$this->user()` and `$this->member($organization)` helpers from base Controller
- Organization model is injected via route model binding

### Services
- Location: `app/Service/`
- Stateless business logic classes
- Injected into controller methods via parameter type-hints

### Request Validation
- Location: `app/Http/Requests/V1/{Feature}/`
- Extend `App\Http\Requests\V1\BaseFormRequest`
- Use `ExistsEloquent` from `korridor/laravel-model-validation-rules` for relationship validation
- Organization accessible via `$this->organization` (route model binding)

### Pinia Stores
- Location: `resources/js/utils/use{Feature}.ts`
- Export `use{Feature}Store` using `defineStore`
- Use `@tanstack/vue-query` for data fetching
- Use `api` client and `getCurrentOrganizationId()` for API calls

### Vue Pages
- Location: `resources/js/Pages/`
- Inertia pages using `AppLayout`

### UI Components
- Location: `resources/js/packages/ui/src/{Feature}/`
- Reusable, feature-scoped components

### Navigation
- File: `resources/js/Layouts/AppLayout.vue`
- Use `NavigationSidebarItem` with heroicons from `@heroicons/vue/20/solid`

## Testing

### API Endpoint Tests
- Location: `tests/Unit/Endpoint/Api/V1/`
- Extend `ApiEndpointTestAbstract` (which extends `TestCaseWithDatabase`)
- Use `Passport::actingAs($data->user)` for authentication
- Use `$this->createUserWithPermission([...])` for test setup
- Route names: `api.v1.{feature}.{action}`

### Service Tests
- Location: `tests/Unit/Service/`

### Feature Tests
- Location: `tests/Feature/`

### E2E Tests
- Location: `e2e/`
- Playwright

## Auth
- Laravel Passport for API authentication
- Jetstream for web sessions

## Key Models
- `TimeEntry`: has `start`, `end`, `project_id`, `task_id`, `member_id`, `user_id`, `organization_id`, `billable`, `description`, `tags`
- `Organization`, `Member`, `User`, `Project`, `Task`, `Client`, `Tag`
