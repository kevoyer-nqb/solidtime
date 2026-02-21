# Codebase Structure

**Analysis Date:** 2026-02-10

## Directory Layout

```
solidtime-analysis/
├── app/                          # Laravel application code
│   ├── Http/                     # HTTP layer (controllers, requests, resources, middleware)
│   │   ├── Controllers/
│   │   │   ├── Api/V1/           # API endpoint controllers
│   │   │   └── Web/              # Web controllers (deprecated, using Inertia)
│   │   ├── Requests/V1/          # Request validation classes by feature
│   │   ├── Resources/V1/         # API response formatters
│   │   ├── Middleware/           # HTTP middleware
│   │   └── Kernel.php            # Middleware registration
│   ├── Service/                  # Business logic services
│   │   ├── Dto/                  # Data transfer objects
│   │   ├── Export/               # Export format handlers
│   │   ├── Import/               # Import format handlers
│   │   ├── ReportExport/         # Report-specific exports
│   │   ├── IpLookup/             # IP lookup service
│   │   └── *.php                 # Individual service classes
│   ├── Models/                   # Eloquent models
│   │   ├── Concerns/             # Model traits
│   │   ├── Passport/             # OAuth token models
│   │   └── {Model}.php           # Individual entity models
│   ├── Enums/                    # PHP enums (Role, Status, etc.)
│   ├── Exceptions/               # Custom exception classes
│   │   └── Api/                  # API-specific exceptions
│   ├── Actions/                  # Action classes (command pattern)
│   ├── Events/                   # Event classes for broadcasting
│   ├── Listeners/                # Event listeners
│   ├── Jobs/                     # Queued jobs
│   ├── Policies/                 # Authorization policies
│   ├── Rules/                    # Custom validation rules
│   ├── Console/                  # Artisan commands
│   ├── Mail/                     # Mailable classes
│   ├── Extensions/               # Extension/feature modules
│   ├── Filament/                 # Admin panel configuration
│   └── Providers/                # Service providers
├── resources/                    # Frontend assets and views
│   ├── js/                       # Vue 3 TypeScript application
│   │   ├── app.ts                # Vue app entry point
│   │   ├── bootstrap.js          # Frontend initialization
│   │   ├── Pages/                # Inertia page components
│   │   │   ├── Time.vue          # Time tracking page
│   │   │   ├── Calendar.vue      # Calendar view
│   │   │   ├── Projects.vue      # Projects list
│   │   │   ├── Members.vue       # Team members
│   │   │   ├── Tags.vue          # Tag management
│   │   │   ├── Clients.vue       # Client management
│   │   │   ├── Dashboard.vue     # Dashboard
│   │   │   ├── ReportingDetailed.vue # Detailed reports
│   │   │   └── {Feature}.vue     # Feature-specific pages
│   │   ├── Layouts/              # Layout components
│   │   │   └── AppLayout.vue     # Main app layout wrapper
│   │   ├── Components/           # Reusable UI components
│   │   │   ├── Common/           # Common components
│   │   │   ├── Billing/          # Billing-related components
│   │   │   ├── Dashboard/        # Dashboard components
│   │   │   └── ui/               # Basic UI elements
│   │   ├── packages/
│   │   │   ├── api/src/          # OpenAPI-generated client
│   │   │   │   ├── index.ts      # Re-exports and type definitions
│   │   │   │   └── openapi.json.client.ts # Generated from OpenAPI
│   │   │   └── ui/src/           # Feature-specific UI components
│   │   ├── utils/                # Pinia stores and utility functions
│   │   │   ├── use{Feature}Store.ts  # Pinia stores for features
│   │   │   ├── permissions.ts    # Permission checking utilities
│   │   │   ├── notification.ts   # Toast notification system
│   │   │   ├── useUser.ts        # User/organization context
│   │   │   └── {util}.ts         # Other utilities
│   │   ├── types/                # TypeScript type definitions
│   │   ├── lib/                  # Small utility libraries
│   │   └── ziggy.js              # Ziggy routing helper
│   ├── css/                      # TailwindCSS and custom CSS
│   ├── views/                    # Blade templates (for Inertia rendering)
│   ├── markdown/                 # Markdown documentation files
│   └── testfiles/                # Test fixture files
├── routes/                       # Route definitions
│   ├── api.php                   # API routes (v1 namespaced)
│   └── web.php                   # Web routes (Inertia pages)
├── tests/                        # Backend test suite
│   ├── Unit/                     # Unit tests
│   │   ├── Endpoint/Api/V1/      # API endpoint tests
│   │   ├── Service/              # Service layer tests
│   │   ├── Model/                # Model tests
│   │   ├── Jobs/                 # Job tests
│   │   └── {Layer}/              # Other layer tests
│   ├── Feature/                  # Feature/integration tests
│   ├── TestCase.php              # Base test class
│   └── TestCaseWithDatabase.php  # Database test base class
├── e2e/                          # Playwright end-to-end tests
│   ├── {feature}.spec.ts         # Feature E2E tests
│   ├── auth.spec.ts              # Authentication E2E tests
│   ├── utils/                    # E2E test utilities
│   └── playwright.config.ts      # (at root) Playwright configuration
├── database/                     # Database migrations and seeds
│   ├── migrations/               # Schema migrations
│   ├── seeders/                  # Database seeders
│   └── factories/                # Model factories
├── config/                       # Application configuration files
│   ├── app.php                   # Main app configuration
│   ├── database.php              # Database configuration
│   ├── queue.php                 # Queue configuration
│   └── {feature}.php             # Feature-specific config
├── bootstrap/                    # Bootstrap files (cache, env loading)
├── storage/                      # Runtime storage (logs, caches, uploads)
│   ├── logs/                     # Application logs
│   ├── app/                      # App-specific files
│   └── framework/                # Framework cache files
├── public/                       # Web-accessible files
│   ├── build/                    # Compiled frontend assets (Vite output)
│   └── images/                   # Static images
├── docker/                       # Docker configuration files
├── .features/                    # Feature flag directories (one per feature)
│   ├── 00-weekly-timesheet-grid/
│   ├── 01-timesheet-approvals/
│   └── {feature-number}-{feature-name}/
├── .planning/                    # GSD planning and analysis documents
│   └── codebase/                 # This directory
├── docs/                         # Project documentation
├── playwright/                   # Playwright configuration and utilities
├── composer.json                 # PHP dependencies
├── package.json                  # NPM dependencies
├── tailwind.config.js            # TailwindCSS configuration
├── vite.config.js                # Vite build configuration
├── eslint.config.mjs             # ESLint configuration
├── tsconfig.json                 # TypeScript configuration
├── phpunit.xml                   # PHPUnit test configuration
├── phpstan.neon                  # PHPStan static analysis config
├── pint.json                     # Laravel Pint code style config
├── .env                          # Environment variables (git-ignored)
├── .env.example                  # Example env file
├── openapi.json                  # OpenAPI specification (auto-generated)
└── README.md                     # Project documentation
```

## Directory Purposes

**app/Http/Controllers/Api/V1/:**
- Purpose: Handle HTTP requests for API endpoints
- Contains: Controller classes with action methods (store, update, destroy, index, show)
- Key files: `TimeEntryController.php` (36KB, large), `MemberController.php`, `ProjectController.php`

**app/Service/:**
- Purpose: Centralized business logic away from controllers
- Contains: Stateless service classes, filters, aggregators, importers/exporters
- Key files: `TimeEntryAggregationService.php` (24KB), `DashboardService.php` (17KB)

**app/Http/Requests/V1/:**
- Purpose: Request validation and authorization
- Contains: FormRequest classes organized by feature (TimeEntry/, Project/, etc.)
- Pattern: One request class per action type (Store, Update, Index, etc.)

**app/Models/:**
- Purpose: Eloquent ORM models with relationships
- Contains: Entity models (TimeEntry, Project, Organization, etc.)
- Key files: `TimeEntry.php`, `Project.php`, `Organization.php` with relationships and scopes

**resources/js/Pages/:**
- Purpose: Full-page Inertia.js components
- Contains: Feature-specific pages that serve as primary UI entry points
- Key files: `Time.vue`, `Projects.vue`, `Calendar.vue`, `ReportingDetailed.vue`

**resources/js/utils/:**
- Purpose: Pinia stores for state management and shared utilities
- Contains: Store definitions (useTimeEntriesStore, useProjects, etc.) and helper functions
- Key files: `useTimeEntriesStore.ts`, `useProjects.ts`, `useUser.ts` for context

**resources/js/Components/:**
- Purpose: Reusable UI components
- Contains: Feature-agnostic components (buttons, forms) and feature-scoped components
- Organization: Main components at root, organized by feature in subdirectories

**resources/js/packages/api/src/:**
- Purpose: Auto-generated type-safe API client
- Contains: Zodios client and TypeScript types extracted from OpenAPI spec
- Generated from: `openapi.json` (auto-regenerated from Laravel routes)

**tests/Unit/Endpoint/Api/V1/:**
- Purpose: API endpoint integration tests
- Contains: Test classes with HTTP request assertions
- Pattern: One test file per controller (TimeEntryEndpointTest.php, etc.)

**e2e/:**
- Purpose: End-to-end browser automation tests
- Contains: Playwright test files covering full user workflows
- Key files: `time.spec.ts` (time tracking), `projects.spec.ts`, `auth.spec.ts`

**database/migrations/:**
- Purpose: Database schema versioning
- Contains: Migration files with create/alter table operations
- Pattern: Timestamped filenames with descriptive names

## Key File Locations

**Entry Points:**
- `resources/js/app.ts`: Vue 3 application initialization, Pinia setup, Inertia setup
- `routes/api.php`: API route definitions with v1 namespace and organization scoping
- `routes/web.php`: Web route definitions serving Inertia pages
- `app/Providers/AppServiceProvider.php`: Service container bindings and configuration

**Configuration:**
- `.env`: Environment variables (connection strings, API keys, etc.) - git-ignored
- `config/app.php`: Laravel application settings
- `config/database.php`: Database connection configuration
- `composer.json`: PHP dependencies and scripts
- `package.json`: NPM dependencies and scripts

**Core Logic:**
- `app/Http/Controllers/Api/V1/TimeEntryController.php`: Time tracking endpoints
- `app/Service/TimeEntryAggregationService.php`: Time entry grouping and calculations
- `app/Service/TimeEntryFilter.php`: Query filtering for time entries
- `resources/js/utils/useTimeEntriesStore.ts`: Time entry state management

**Testing:**
- `tests/Unit/Endpoint/Api/V1/TimeEntryEndpointTest.php`: API endpoint tests (176KB, comprehensive)
- `tests/TestCaseWithDatabase.php`: Base test class with database setup
- `tests/Unit/Endpoint/Api/V1/ApiEndpointTestAbstract.php`: Common endpoint test utilities
- `e2e/time.spec.ts`: Time tracking E2E tests
- `e2e/projects.spec.ts`: Project management E2E tests

**Models & Database:**
- `app/Models/TimeEntry.php`: Time entry data model
- `app/Models/Organization.php`: Organization (workspace) model
- `app/Models/Member.php`: Organization member model
- `database/migrations/`: All migration files

**Frontend Pages:**
- `resources/js/Pages/Time.vue`: Time tracking UI (22.6KB)
- `resources/js/Pages/Projects.vue`: Project management (6.9KB)
- `resources/js/Layouts/AppLayout.vue`: Main application layout wrapper
- `resources/js/Pages/ReportingDetailed.vue`: Detailed reports (21.3KB)

**API Client:**
- `resources/js/packages/api/src/index.ts`: Type definitions and client exports
- `resources/js/packages/api/src/openapi.json.client.ts`: Auto-generated Zodios client (150KB)

## Naming Conventions

**Files:**
- Controllers: `{Entity}Controller.php` (PascalCase)
- Services: `{Feature}Service.php` (PascalCase)
- Models: `{Entity}.php` (PascalCase, singular)
- Requests: `{Feature}{Action}Request.php` (e.g., `TimeEntryStoreRequest.php`)
- Tests: `{Feature}Test.php` or `{Feature}EndpointTest.php`
- Vue components: `{ComponentName}.vue` (PascalCase)
- Pinia stores: `use{Feature}Store.ts` or `use{Feature}.ts`
- Utility functions: `{utility}.ts` (camelCase)

**Directories:**
- Features organized by resource: `TimeEntry/`, `Project/`, `Member/`, etc.
- Grouped by layer: `Http/`, `Service/`, `Models/`
- Pages organized by feature: `/Pages/{Feature}.vue`
- Components by feature: `/Components/{Feature}/`

## Where to Add New Code

**New Feature API Endpoint:**
- Primary code: `app/Http/Controllers/Api/V1/{Feature}Controller.php`
- Validation: `app/Http/Requests/V1/{Feature}/{Feature}{Action}Request.php`
- Business logic: `app/Service/{Feature}Service.php`
- Response formatting: `app/Http/Resources/V1/{Feature}/{Feature}Resource.php`
- Database model: `app/Models/{Feature}.php` if new entity
- Routes: Add route group to `routes/api.php` with `{organization}` prefix
- Tests: `tests/Unit/Endpoint/Api/V1/{Feature}EndpointTest.php`

**New Frontend Page:**
- Page component: `resources/js/Pages/{PageName}.vue`
- State management: `resources/js/utils/use{Feature}Store.ts` (Pinia store)
- Feature components: `resources/js/Components/{Feature}/` or `resources/js/packages/ui/src/{Feature}/`
- Route handler: Add to `routes/web.php` with Inertia::render()
- E2E tests: `e2e/{feature}.spec.ts`

**New Service/Utility:**
- Business logic: `app/Service/{Feature}Service.php`
- Filters/processors: `app/Service/{Feature}Filter.php` or similar
- Frontend utils: `resources/js/utils/{utility}.ts`
- Frontend stores: `resources/js/utils/use{Feature}.ts`

**New Model/Database Entity:**
- Model: `app/Models/{Entity}.php` with relationships
- Migration: `database/migrations/{timestamp}_create_{table}_table.php`
- Factory: `database/factories/{Entity}Factory.php` for testing
- Tests: `tests/Unit/Model/{Entity}Test.php`

**UI Components:**
- Reusable primitives: `resources/js/packages/ui/src/{ComponentName}.vue`
- Feature-scoped: `resources/js/Components/{Feature}/{ComponentName}.vue`
- Shared utilities: `resources/js/Components/{ComponentName}.vue`

## Special Directories

**api/src/ (Frontend API Client):**
- Purpose: Generated TypeScript client from OpenAPI spec
- Generated: Yes (from `openapi.json` via code generation)
- Committed: Yes (committed to git after generation)
- Regenerated by: `npm run generate-api` command

**build/ (Compiled Assets):**
- Purpose: Contains compiled Vite output (CSS, JS bundles)
- Generated: Yes (from Vite build process)
- Committed: No (.gitignore)
- Generated by: `npm run build` or `npm run dev`

**bootstrap/cache/ (Laravel Cache):**
- Purpose: Laravel runtime caches
- Generated: Yes (during application runtime)
- Committed: No
- Purpose: Performance optimization for autoload, config, etc.

**.features/ (Feature Directories):**
- Purpose: Feature-specific code, organized as standalone modules
- Structure: One directory per feature (e.g., `00-weekly-timesheet-grid/`, `01-timesheet-approvals/`)
- Contents: Feature-specific migrations, views, controllers, or components

**storage/logs/ (Application Logs):**
- Purpose: Runtime application logs
- Generated: Yes (created during application execution)
- Committed: No (.gitignore)

**openapi.json (OpenAPI Spec):**
- Purpose: Auto-generated OpenAPI 3.0 specification from Laravel routes
- Generated: Yes (auto-generated from routes)
- Committed: Yes (to keep frontend client in sync)
- Updated by: `npm run generate-api` command

---

*Structure analysis: 2026-02-10*
