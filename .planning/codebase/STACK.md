# Technology Stack

**Analysis Date:** 2026-02-10

## Languages

**Primary:**
- PHP 8.3 - Backend application logic, API endpoints, migrations, commands
- TypeScript 5.7.3 - Frontend Vue components, utilities, type-safe client code
- Vue 3.5.0 - UI framework, reactive components, template rendering

**Secondary:**
- JavaScript - Build tooling, configuration files, legacy support
- SQL - PostgreSQL queries, migrations, database operations

## Runtime

**Environment:**
- Laravel 12.19.3 - PHP web framework, Eloquent ORM, Inertia.js integration
- Node.js - Frontend build tooling (specified via Vite, no .nvmrc found - inferred as LTS)

**Package Manager:**
- Composer 2.x - PHP dependency management
  - Lockfile: `composer.lock` present
- npm - JavaScript dependency management
  - Lockfile: `package-lock.json` present (in `/resources/js/packages/api/`)

## Frameworks

**Core Backend:**
- Laravel 12.19.3 - Full-stack web application framework
- Laravel Jetstream 5.0 - User registration, teams, multi-tenancy scaffolding
- Laravel Octane 2.3 - High-performance PHP application server
- Filament 3.2 - Admin panel framework
- Inertia.js 2.0.3 - Server-side rendered React/Vue via Laravel

**Frontend:**
- Vite 6.0.11 - Frontend build tool and dev server
- @vitejs/plugin-vue 5.2.1 - Vite Vue 3 integration
- Pinia 2.1.7 - Vue state management store
- @inertiajs/vue3 1.0.0 - Inertia.js Vue 3 adapter

**UI Components:**
- TailwindCSS 3.4.13 - Utility-first CSS framework
- @tailwindcss/forms 0.5.9 - Form element styling
- @tailwindcss/typography 0.5.15 - Typography styling
- Radix Vue 1.9.6 - Headless UI component library
- Reka UI 2.2.0 - Vue component library
- @heroicons/vue 2.1.1 - Icon library
- Lucide Vue 0.487.0 - Icon library
- Chroma JS 3.1.2 - Color manipulation
- Class Variance Authority 0.7.1 - Component variant styling

**Data Visualization:**
- ECharts 5.5.0 - Data visualization library
- Vue-ECharts 7.0.3 - Vue wrapper for ECharts
- FullCalendar 6.1.18 - Calendar widget
  - @fullcalendar/core, @fullcalendar/daygrid, @fullcalendar/timegrid, @fullcalendar/vue3

**Data Fetching & State:**
- @tanstack/vue-query 5.56.2 - Server state management and caching
- @tanstack/vue-query-devtools 5.58.0 - Vue Query debugging tools
- @tanstack/vue-table 8.21.2 - Headless table library
- @tanstack/vue-form 1.3.1 - Form state management
- @vueuse/core 12.8.2 - Vue composition utilities
- @vueuse/integrations 12.5.0 - Vue Use integrations

**HTTP Client:**
- Axios 1.6.4 - HTTP client library

**Testing:**
- @playwright/test 1.41.1 - E2E testing framework
- PHPUnit 12 - PHP unit testing framework
- Mockery 1.4.4 - PHP mocking library
- Paratest 7.3 - Parallel test runner for PHPUnit

**Build & Dev Tools:**
- vite-plugin-checker 0.8.0 - Vite plugin for TypeScript/lint checking
- laravel-vite-plugin 1.0.0 - Vite plugin for Laravel
- ESLint 9.19.0 - JavaScript/TypeScript linting
- Prettier - Code formatter (via .prettierrc.json)
- PostCSS 8.4.47 - CSS transformation tool
- Autoprefixer 10.4.20 - CSS vendor prefix tool
- TailwindCSS Animate 1.0.7 - Animation utilities
- vue-tsc 2.2.0 - TypeScript type checking for Vue

**Code Generation:**
- openapi-zod-client 1.16.2 - Generates TypeScript client from OpenAPI spec
- Scramble 0.12.2 - Laravel OpenAPI documentation generator
- ModelTyper 3.0 - Generates TypeScript model types from Eloquent models

**Code Quality:**
- Larastan 3.5.0 - PHPStan extension for Laravel static analysis
- Laravel Pint 1.0 - Laravel code style fixer

**Development Tools:**
- Laravel IDE Helper 3.0 - IDE autocomplete hints
- Laravel Telescope 5.0 - Application debugging and monitoring (disabled in .env)
- Laravel Tinker 2.8 - REPL for Laravel
- Laravel Sail 1.18 - Docker-based development environment

## Key Dependencies

**Critical:**
- laravel/passport 13.0.5 - OAuth 2.0 API authentication for tokens and personal access tokens
- laravel/framework 12.19.3 - Core framework
- inertiajs/inertia-laravel 2.0.3 - Server-side rendering bridge
- filament/filament 3.2 - Admin panel with visual CRUD builders
- brick/money 0.10.0 - Money value object for financial calculations
- gotenberg/gotenberg-php 2.8 - PDF generation via Gotenberg service
- guzzlehttp/guzzle 7.2 - HTTP client for external API calls

**Database & ORM:**
- Eloquent ORM (built-in to Laravel) - Object-relational mapping
- tpetry/laravel-postgresql-enhanced 3.0.0 - PostgreSQL-specific enhancements
- nwidart/laravel-modules 12.0.4 - Modular application structure

**Validation & Models:**
- korridor/laravel-model-validation-rules 3.0 - Validation rules for model relationships
- korridor/laravel-computed-attributes 3.1 - Computed properties on models
- korridor/laravel-has-many-sync 3.1 - Bulk syncing of many-to-many relationships
- staudenmeir/eloquent-json-relations 1.1 - JSON relationship support

**Data Import/Export:**
- maatwebsite/excel 3.1 - Excel file reading/writing (Laravel Excel)
- league/csv 9.16.0 - CSV parsing and writing
- league/iso3166 4.3 - ISO 3166 country codes

**Admin & Security:**
- stechstudio/filament-impersonate 3.8 - User impersonation for admin
- pxlrbt/filament-environment-indicator 2.1.0 - Environment badges in admin
- novadaemon/filament-pretty-json 2.2 - Pretty JSON display in Filament
- owen-it/laravel-auditing 14.0.0 - Model change auditing and logging

**Other:**
- datomatic/laravel-enum-helper 2.0.0 - Enum helper utilities
- flowframe/laravel-trend 0.4.0 - Trend/analytics calculations
- tightenco/ziggy 2.1.0 - Frontend route helper from Laravel routes
- spatie/temporary-directory 2.2 - Temporary directory management
- league/flysystem-aws-s3-v3 3.0 - S3 file storage driver
- wikimedia/composer-merge-plugin 2.1.0 - Composer plugin for modular dependencies

## Configuration

**Environment:**
- .env file controls all configuration (see `.env.example` for keys)
- Database: PostgreSQL 15 (primary), SQLite/MySQL supported
- File storage: S3-compatible (MinIO in local development)
- Mail: SMTP via Mailpit (local), configurable for production
- API tokens: Passport OAuth 2.0 with personal access tokens
- Session: Database-backed or array-based
- Cache: File-based (local), Redis-compatible (if configured)

**Build:**
- `vite.config.js` - Frontend build configuration
  - Type checking enabled (TypeScript, Vue TSC)
  - ESLint checking enabled
  - Sourcemaps for debugging
- `tailwind.config.js` - Tailwind CSS configuration with container-queries plugin
- `postcss.config.js` - PostCSS configuration (Tailwind, nesting)
- `eslint.config.mjs` - JavaScript/TypeScript linting rules
- `.prettierrc.json` - Code formatting rules (4-space indent, 100-char line width)
- `phpstan.neon` - PHP static analysis config (level 7, Octane compatibility checks)

## Platform Requirements

**Development:**
- PHP 8.3 with extensions: zip
- PostgreSQL 15 or compatible
- Node.js (LTS, exact version not pinned)
- Docker & Docker Compose (for Sail development environment)
- Composer installed globally
- npm/yarn for frontend dependencies

**Production:**
- PHP 8.3 application server (Laravel Octane for performance)
- PostgreSQL 15 database
- S3-compatible file storage (AWS S3, MinIO, etc.)
- Gotenberg service for PDF generation
- Nginx or Apache web server with reverse proxy support (Traefik in Docker)
- SMTP mail server or compatible service
- SSL/TLS certificates

**Docker Services (Local):**
- `laravel.test` - PHP 8.3 application container
- `pgsql` - PostgreSQL 15 database
- `pgsql_test` - PostgreSQL 15 test database
- `mailpit` - Local mail testing service
- `playwright` - Playwright browser automation for E2E tests
- `minio` - S3-compatible object storage (local development)
- `gotenberg` - PDF generation service

---

*Stack analysis: 2026-02-10*
