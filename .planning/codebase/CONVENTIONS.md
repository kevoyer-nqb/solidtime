# Coding Conventions

**Analysis Date:** 2026-02-10

## Naming Patterns

**Files:**
- Vue components: PascalCase (e.g., `AppLayout.vue`, `TimeTracker.vue`)
- TypeScript utilities/stores: camelCase (e.g., `useTimeEntries.ts`, `notification.ts`)
- Laravel classes: PascalCase (e.g., `ApiTokenController.php`, `TimeEntryAggregationService.php`)
- Test files: descriptive name + `Test.php` (e.g., `ApiTokenEndpointTest.php`)

**Functions:**
- camelCase for all functions (both PHP and TypeScript/JavaScript)
- Private/protected methods prefixed with underscore: `_privateMethod()`
- Store methods follow Pinia convention: `useFunctionName`

**Variables:**
- camelCase for all variables
- Constants in UPPER_SNAKE_CASE (limited use)
- Vue refs: `const count = ref(0)` (camelCase with type explicit)
- Store state: `ref<Type>()` with TypeScript types

**Types:**
- PascalCase for TypeScript interfaces and types
- Generic types in generics position, e.g., `Ref<TimeEntry[]>`
- Request/Response types suffixed with `Body` or `Response` (e.g., `CreateTimeEntryBody`, `TimeEntriesQueryParams`)

## Code Style

**Formatting:**
- ESLint + Prettier for JavaScript/TypeScript
- Run `npm run lint:fix && npm run format` before committing
- Prettier config: Defaults (2-space indentation for JS/TS)

**Linting:**
- ESLint config: `eslint.config.mjs` in project root
- Rules enforce unused imports detection via `eslint-plugin-unused-imports`
- Variables matching `^_` pattern ignored (allows intentional unused for clarity)
- Vue multi-word components disabled (`vue/multi-word-component-names: 'off'`)
- TypeScript ESLint extended config recommended

**PHP:**
- `declare(strict_types=1);` at top of every PHP file
- 4-space indentation, LF line endings
- Run `composer fix && composer analyse` before committing (Laravel 11 conventions)

## Import Organization

**Order (TypeScript/JavaScript):**
1. Vue/Framework imports (e.g., `import { ref } from 'vue'`)
2. Third-party imports (e.g., `import axios from 'axios'`, `import { defineStore } from 'pinia'`)
3. Path alias imports (e.g., `import { api } from '@/packages/api/src'`)
4. Relative imports (e.g., `import AppLayout from '@/Layouts/AppLayout.vue'`)
5. Type imports (e.g., `import type { User } from '@/types/models'`)

**Path Aliases:**
- `@/` = `resources/js/` (project root JavaScript directory)
- `@/packages/` = `resources/js/packages/`
- `@/Components/` = `resources/js/Components/`
- `@/Layouts/` = `resources/js/Layouts/`
- `@/Pages/` = `resources/js/Pages/`
- `@/utils/` = `resources/js/utils/`
- `@/types/` = `resources/js/types/`

**PHP imports:**
- Use full namespace paths with `use` statements at top of file
- Order: framework classes, then domain classes, then application classes

## Error Handling

**Patterns:**
- TypeScript/Vue: Try-catch with axios error checking pattern:
  ```typescript
  try {
    const response = await apiRequest();
    return response;
  } catch (error) {
    if (axios.isAxiosError(error)) {
      // Handle axios error
      if (error?.response?.status === 403) {
        // Handle forbidden
      }
    }
    throw new Error('Descriptive error message');
  }
  ```
- Store methods wrap API calls in `handleApiRequestNotifications()` for consistent error UI
- Throw descriptive `Error` with message, not bare throws
- PHP: Use custom exception classes (e.g., `AuthorizationException`, `EntityStillInUseApiException`)
- Controllers extend base Controller and use `$this->checkPermission()` for auth checks

## Logging

**Framework:** `console` for JavaScript (limited use), `Log` facade for PHP

**Patterns:**
- Minimal console logging in production code (some `console.warn()` for timezone issues)
- PHP: Use `Log::` facade in services for business logic issues
- Error notifications handled through `useNotificationsStore().handleApiRequestNotifications()` not console
- Debug logs in comments only where helpful (e.g., timezone edge cases)

## Comments

**When to Comment:**
- JSDoc for store methods: See `useTimeEntries.ts` for detailed return type documentation
- PHP: DocBlocks on public methods with param/return types
- Vue: Comments explaining complex logic or non-obvious Vue patterns

**JSDoc/TSDoc:**
- Store methods documented with full return types:
  ```typescript
  /**
   * @returns {Ref<TimeEntry[]>} Array of loaded time entries
   */
  ```
- PHP methods use standard PHPDoc with `@param`, `@return`, `@throws`

## Function Design

**Size:**
- Functions should be focused on single responsibility
- Pinia store methods often 10-50 lines (includes API call + state update)
- Service methods typically 5-30 lines

**Parameters:**
- Named parameters preferred in TypeScript (use destructuring)
- PHP: Type-hint all parameters (strict types enabled)
- Optional params with defaults documented in JSDoc

**Return Values:**
- TypeScript: Explicit return types always specified
- PHP: Strict return type declarations required
- Promises/async returns typed as `Promise<T>`

## Module Design

**Exports:**
- Named exports preferred in TypeScript utilities
- Default export for Vue components (standard Vue pattern)
- Store exports: `export const useStoreNameStore = defineStore(...)`

**Barrel Files:**
- Index files export multiple components from package: `export { default as Popover } from './Popover.vue'`
- Located in `resources/js/packages/ui/src/*/index.ts`
- Used for cleaner imports in Pages and Components

**Pinia Store Pattern:**
- Define store with `defineStore('storeName', () => { ... })`
- Return object with state (refs) and methods
- State methods call `useNotificationsStore().handleApiRequestNotifications()` for API error handling
- Stores use `getCurrentOrganizationId()` and `getCurrentMembershipId()` for context

## Component Design

**Vue Components (SFC - Single File Components):**
- `<script setup lang="ts">` always used
- Props defined via `defineProps<Type>()`
- Emits defined via `defineEmits<Emits>()`
- Computed properties via `computed()`
- Lifecycle via `onMounted()`, etc.
- Template uses data-testid attributes for E2E testing

**Props Pattern:**
```typescript
defineProps<{
  title: string;
  saving?: boolean;
}>();
```

**Composables:**
- Stored in `resources/js/utils/useXxx.ts`
- Return object with methods and state
- Use Pinia stores for shared state
- Use `useQuery` from TanStack Query for data fetching

---

*Convention analysis: 2026-02-10*
