# Architecture

**Analysis Date:** 2026-02-10

## Pattern Overview

**Overall:** Hybrid MVC (Laravel backend) + Inertia.js with Vue 3 SPA (frontend)

**Key Characteristics:**
- RESTful API-first design with Laravel 11 backend serving as API provider
- Inertia.js bridge connecting Laravel server-side routing to Vue 3 frontend
- Pinia stores for frontend state management
- Tanstack Vue Query for server-state synchronization
- Organization-scoped multi-tenancy with permission-based authorization
- Feature-driven modular structure in backend service layer

## Layers

**HTTP/API Layer:**
- Purpose: Handle HTTP requests and responses, validation, and routing
- Location: `app/Http/Controllers/Api/V1/`, `routes/api.php`
- Contains: API controllers implementing CRUD operations, request validation classes, API resources (JSON responses)
- Depends on: Service layer for business logic, Models for database access
- Used by: Frontend via OpenAPI-generated TypeScript client (`resources/js/packages/api/src/`)

**Service Layer:**
- Purpose: Encapsulate business logic, data transformations, and complex operations
- Location: `app/Service/`
- Contains: Stateless classes handling domain logic, aggregations, filters, exports, imports
- Depends on: Models, database queries, external services
- Used by: Controllers, jobs, event listeners

**Model Layer:**
- Purpose: Define data models and relationships
- Location: `app/Models/`
- Contains: Eloquent models with relationships, scopes, mutators
- Depends on: Database (via Eloquent ORM)
- Used by: Services, controllers for queries and persistence

**Request Validation Layer:**
- Purpose: Validate incoming HTTP request data
- Location: `app/Http/Requests/V1/{Feature}/`
- Contains: FormRequest classes extending `BaseFormRequest` with validation rules
- Depends on: Laravel validation rules, custom rules in `app/Rules/`
- Used by: Controllers for automatic request validation

**Frontend - Page Layer:**
- Purpose: Page-level Inertia components that represent full page views
- Location: `resources/js/Pages/`
- Contains: Vue 3 components rendered server-side initially via Inertia
- Depends on: Components, Pinia stores, utility functions
- Used by: Web routing (via `routes/web.php`)

**Frontend - Store Layer (Pinia):**
- Purpose: Manage frontend application state with async data fetching
- Location: `resources/js/utils/use{Feature}.ts`
- Contains: Pinia stores with methods for API calls and state mutations
- Depends on: Tanstack Vue Query, API client, notification utilities
- Used by: Page components for data access and state updates

**Frontend - Component Layer:**
- Purpose: Reusable UI components
- Location: `resources/js/Components/`, `resources/js/packages/ui/src/`
- Contains: Feature-scoped Vue 3 components with composition API
- Depends on: HeroIcons, utility functions, child components
- Used by: Page components and other components

**Frontend - API Client Layer:**
- Purpose: Type-safe API communication
- Location: `resources/js/packages/api/src/`
- Contains: OpenAPI-generated Zodios client and TypeScript types
- Depends on: OpenAPI specification, Zodios library
- Used by: Pinia stores for all API calls

## Data Flow

**Create/Update Time Entry Flow:**

1. User interacts with UI component (e.g., time entry form in `resources/js/Pages/Time.vue`)
2. Component calls Pinia store method (e.g., `useTimeEntriesStore().createTimeEntry()`)
3. Store calls API client (`api.createTimeEntry()`) with organization and member context
4. API client sends HTTP request to backend with `Authorization: Bearer {passport_token}`
5. Route matched in `routes/api.php` → dispatched to `app/Http/Controllers/Api/V1/TimeEntryController::store()`
6. Controller validates request via `TimeEntryStoreRequest` class
7. Controller checks permissions via `$this->checkPermission($organization, 'permission-name')`
8. Controller calls service (`TimeEntryService`, `TimeEntryAggregationService`)
9. Service performs business logic (overlap checking, time calculations, event dispatching)
10. Service/Controller persists to database via `TimeEntry` model
11. Controller returns API resource (`TimeEntryResource`) as JSON
12. Frontend receives JSON, store updates local state, components re-render via Vue reactivity

**Fetch Time Entries Flow:**

1. Component mounts or user navigates
2. Pinia store method `fetchTimeEntries()` called with query params
3. Store calls `api.getTimeEntries({ params: { organization }, queries: { ... } })`
4. API client sends GET request to `/api/v1/organizations/{organization}/time-entries`
5. Route dispatched to `TimeEntryController::index()`
6. Controller queries via `TimeEntryFilter` service with user permissions
7. Database returns filtered results based on user's organization and member scope
8. Controller returns paginated `TimeEntryCollection` resource
9. Store receives response and updates `timeEntries` ref
10. Components subscribed to store reactively update

**State Management:**
- Server-side: Organization, Member, User context passed via Inertia props on initial page load
- Frontend: Pinia stores cache data, Tanstack Vue Query manages cache invalidation
- Query key patterns: `['organization', orgId]`, `['timeEntry']` for cache busting

## Key Abstractions

**Organization (Multi-Tenancy):**
- Purpose: Represents isolated workspace/team with independent data
- Examples: `app/Models/Organization`, `routes/api.php` uses `{organization}` route parameter
- Pattern: Route model binding injects Organization instance; middleware checks access; permission checks scoped to org

**Permission Store:**
- Purpose: Centralized permission checking for current user in organization context
- Examples: `app/Service/PermissionStore`, used in `app/Http/Controllers/Api/V1/Controller::checkPermission()`
- Pattern: Injected into controllers; checks cached permissions for current member

**Service Classes (Business Logic):**
- Purpose: Encapsulate complex operations outside models/controllers
- Examples: `app/Service/TimeEntryAggregationService`, `app/Service/TimeEntryFilter`, `app/Service/MemberService`
- Pattern: Stateless, injected via Laravel service container, focus on domain operations

**API Resources (Response Formatting):**
- Purpose: Transform models to JSON with controlled field exposure
- Examples: `app/Http/Resources/V1/TimeEntry/TimeEntryResource`, `TimeEntryCollection`
- Pattern: Extend JsonResource, define toArray() method, used in controller returns

**Pinia Stores (Frontend):**
- Purpose: Centralized reactive state with async logic
- Examples: `resources/js/utils/useTimeEntriesStore`, `useProjectsStore`
- Pattern: `defineStore('featureName', () => {...})` composition API, returns reactive refs and methods

**OpenAPI Client (Type Safety):**
- Purpose: Auto-generated type-safe API client from OpenAPI spec
- Examples: `resources/js/packages/api/src/index.ts` and generated `openapi.json.client.ts`
- Pattern: Zodios library generates methods from spec; all requests and responses fully typed

## Entry Points

**API Entry Point:**
- Location: `routes/api.php`
- Triggers: HTTP requests to `/api/v1/...`
- Responsibilities: Route definitions, organization binding, middleware application, auth verification

**Web Entry Point:**
- Location: `routes/web.php`
- Triggers: Browser navigation to authenticated routes
- Responsibilities: Inertia page rendering, passing initial props, session verification

**Backend Application Boot:**
- Location: `app/Providers/` (service providers)
- Triggers: Laravel application initialization
- Responsibilities: Service container bindings, event listeners, configuration loading

**Frontend Application Boot:**
- Location: `resources/js/app.ts`
- Triggers: Script loaded in browser
- Responsibilities: Vue app creation, Pinia initialization, Tanstack Vue Query setup, Inertia hydration

**Time Entry Feature Entry:**
- Location: `resources/js/Pages/Time.vue` (UI entry)
- Triggers: User navigates to `/time` route
- Responsibilities: Display time tracking interface, manage time entry operations

## Error Handling

**Strategy:** Layered exception handling with custom API exception classes

**Patterns:**
- Controllers throw custom exceptions (e.g., `OverlappingTimeEntryApiException` in `app/Exceptions/Api/`)
- Exceptions are caught by Laravel exception handler and transformed to JSON responses
- Frontend stores use `handleApiRequestNotifications()` utility to show error toasts
- Validation errors caught by `FormRequest` and returned as 422 responses with field errors
- Authorization failures throw `AuthorizationException` caught globally and returned as 403
- Critical business logic errors with clear exception messages for user feedback

## Cross-Cutting Concerns

**Logging:** Laravel default logging via `Illuminate\Support\Facades\Log` in controllers/services; frontend uses browser console

**Validation:**
- Backend: `FormRequest` classes with Laravel's validation rules
- Frontend: Real-time validation in Vue components, optional client-side libraries for complex rules
- Models: Custom validation rules in `app/Rules/`

**Authentication:**
- Backend: Laravel Passport (OAuth2) for API authentication via bearer tokens
- Web: Laravel Jetstream for session-based authentication
- Frontend: Token stored in cookies; `fetchToken()` utility refreshes on window focus

**Authorization:**
- Backend: Permission-based checks via `PermissionStore::has()` method
- Pattern: Roles defined in `app/Enums/Role`, permissions stored in database
- Scope: All checks include organization context to prevent cross-organization data access

**Multi-Tenancy:**
- Implementation: Organization scoping via `{organization}` route parameter
- Database: Foreign keys to `organizations` table on all data tables
- Middleware: Organization access verified before controller execution
- Query Builders: Scoped queries filter by current organization automatically

---

*Architecture analysis: 2026-02-10*
