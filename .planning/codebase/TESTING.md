# Testing Patterns

**Analysis Date:** 2026-02-10

## Test Framework

**Runner:**
- Playwright (E2E) - version 1.41.1
- Config: `playwright.config.ts` in project root
- PHPUnit for PHP backend tests (Laravel 11 standard)

**Assertion Library:**
- Playwright assertions: `expect()` from `@playwright/test`
- PHP: PHPUnit assertions via TestCase methods

**Run Commands:**
```bash
npm run test:e2e                    # Run all E2E tests with Playwright
npm run test:e2e -- --headed       # Run in headed mode (visible browser)
npm run test:e2e -- --debug        # Debug mode with stepping
npm run test:e2e -- --project=chromium  # Run specific browser
composer test                       # Run PHP tests (if available)
```

## Test File Organization

**Location:**
- E2E tests: `e2e/` directory
- PHP API endpoint tests: `tests/Unit/Endpoint/Api/V1/`
- PHP service tests: `tests/Unit/Service/`
- PHP feature tests: `tests/Feature/`

**Naming:**
- E2E: `featureName.spec.ts` (e.g., `time.spec.ts`, `auth.spec.ts`)
- PHP: `ClassName + Test.php` (e.g., `ApiTokenEndpointTest.php`)

**Structure:**
```
e2e/
├── auth.spec.ts
├── time.spec.ts
├── utils/
│   ├── currentTimeEntry.ts
│   └── [other helpers]
└── [feature].spec.ts

tests/
├── Unit/
│   ├── Endpoint/
│   │   ├── Api/V1/
│   │   │   └── [FeatureName]EndpointTest.php
│   │   └── Web/
│   └── Service/
│       └── [ServiceName]Test.php
└── Feature/
    └── [FeatureName]Test.php
```

## Test Structure

**E2E Suite Organization:**
```typescript
import { test } from '../playwright/fixtures';
import { PLAYWRIGHT_BASE_URL } from '../playwright/config';

test('test that user can perform action', async ({ page }) => {
    // Arrange
    await page.goto(PLAYWRIGHT_BASE_URL + '/route');

    // Act
    await page.locator('[data-testid="element"]').click();

    // Assert
    await expect(page.locator('[data-testid="result"]')).toContainText('Expected');
});
```

**PHP Test Suite Organization:**
```php
<?php
declare(strict_types=1);

namespace Tests\Unit\Endpoint\Api\V1;

class ApiTokenEndpointTest extends ApiEndpointTestAbstract
{
    public function test_index_endpoint_returns_list_api_tokens(): void
    {
        // Arrange
        $data = $this->createUserWithPermission([]);
        Passport::actingAs($data->user);

        // Act
        $response = $this->getJson(route('api.v1.api-tokens.index'));

        // Assert
        $this->assertResponseCode($response, 200);
        $response->assertJsonCount(1, 'data');
    }
}
```

**Patterns:**
- Arrange-Act-Assert structure (AAA pattern)
- Setup methods: `createUserWithPermission()` from TestCaseWithDatabase
- Response assertions: `$this->assertResponseCode()` wrapper in ApiEndpointTestAbstract
- Database state: Automatically refreshed via `RefreshDatabase` trait in TestCaseWithDatabase

## Mocking

**Framework:** Mockery for PHP, Playwright built-in mocking

**PHP Patterns:**
```php
// Mock BillingContract in TestCase
$this->mock(BillingContract::class, function (MockInterface $mock): void {
    $mock->shouldReceive('hasSubscription')->andReturn(true);
    $mock->shouldReceive('hasTrial')->andReturn(false);
});

// Call in test
$this->actAsOrganizationWithSubscription();  // Helper method in TestCase
```

**E2E Patterns:**
```typescript
// Wait for API response matching conditions
await page.waitForResponse((response) =>
    response.url().includes('/time-entries') &&
    response.status() === 200
);

// Mock browser behaviors via fixtures
export const test = baseTest.extend<object, { workerStorageState: string }>({
    page: async ({ page }, use) => {
        // Setup: register test user
        await page.goto(PLAYWRIGHT_BASE_URL + '/register');
        await page.getByLabel('Email').fill(`john+${Math.random()}@doe.com`);
        // ... fill other fields
        await page.getByRole('button', { name: 'Register' }).click();
        await page.waitForURL(PLAYWRIGHT_BASE_URL + '/dashboard');

        await use(page);
    },
});
```

**What to Mock:**
- External API clients (HTTP requests via `Http::preventStrayRequests()` in TestCase)
- Billing contract (always mocked, set via `mockBillingContract` property)
- Mail facade (`Mail::fake()` in setUp)
- Storage (filesystem: `Storage::fake()` when testing file operations)

**What NOT to Mock:**
- Database models (use factories instead: `User::factory()->create()`)
- Eloquent relationships (use real model loading)
- Core business logic (test integration not units)
- Application routes and controllers (test full request flow)

## Fixtures and Factories

**Test Data (PHP):**
```php
// Factory usage in tests
$token = Token::factory()->forUser($data->user)->forClient($personalAccessClient)->create();
$user = User::factory()->create();
$organization = Organization::factory()->withOwner($user)->create();

// Helper returns object with test data
$data = $this->createUserWithPermission(['permission-name']);
// Returns: $data->user, $data->organization, $data->member, $data->owner, $data->ownerMember
```

**Location:**
- Factories: `database/factories/` (Laravel standard)
- Test helpers: TestCase base classes in `tests/`
  - `TestCase.php` - Base with mocking setup
  - `TestCaseWithDatabase.php` - Database+helpers
  - `ApiEndpointTestAbstract.php` - API endpoint helpers

**E2E Fixtures:**
```typescript
// playwright/fixtures.ts
export const test = baseTest.extend<object, { workerStorageState: string }>({
    page: async ({ page }, use) => {
        // Authentication setup shared across tests
        await page.goto(PLAYWRIGHT_BASE_URL + '/register');
        // ... create user
        await use(page);
    },
});
```

## Coverage

**Requirements:** Not enforced via coverage thresholds

**View Coverage:** Not configured (PHPUnit coverage available via command line if needed)

## Test Types

**Unit Tests:**
- Scope: Individual services, utilities, calculations
- Location: `tests/Unit/Service/`, `tests/Unit/Console/`, etc.
- Approach: Mock external dependencies, test pure logic
- Example: `TimeEntryAggregationService` math and grouping logic

**Integration Tests:**
- Scope: Service + Database interactions
- Location: `tests/Unit/Endpoint/Api/V1/`
- Approach: Full request cycle with test database
- Setup: `Passport::actingAs()` for authentication
- Example: API endpoint returns correct JSON with permissions checked

**E2E Tests:**
- Framework: Playwright
- Scope: Full user workflows including UI interactions
- Location: `e2e/`
- Approach: Browser automation via registered test user
- Example: User can create time entry from UI, see it in list, update description

**Feature Tests:**
- Location: `tests/Feature/`
- Approach: Test application features without UI
- Setup: Similar to integration but tests specific features

## Common Patterns

**Async Testing (E2E):**
```typescript
// Wait for response with Promise.all
await Promise.all([
    newTimeEntryResponse(page),
    startOrStopTimerWithButton(page),
    assertThatTimerHasStarted(page),
]);

// Wait for response matching condition
await page.waitForResponse((response) =>
    response.status() === 200 &&
    response.url().includes('/time-entries')
);

// Fill form and wait for response
await Promise.all([
    descriptionElement.press('Tab'),
    page.waitForResponse((response) => response.status() === 200),
]);
```

**Async Testing (PHP):**
```php
// Tests use async/await for API calls (no special handling needed)
$response = $this->getJson(route('api.v1.resource.index'));
$this->assertResponseCode($response, 200);
```

**Error Testing:**
```typescript
// E2E: Test error handling in response
await expect(async () => {
    await page.waitForResponse((response) =>
        response.status() === 403 &&
        response.url().includes('/protected')
    );
}).toThrow();

// E2E: Test disabled state after error
await expect(page.locator('[data-testid="button"]')).toBeDisabled();
```

**PHP Error Testing:**
```php
public function test_endpoint_returns_forbidden_without_permission(): void
{
    // Arrange
    $data = $this->createUserWithPermission([]);  // Empty permissions
    Passport::actingAs($data->user);

    // Act
    $response = $this->postJson(route('api.v1.resource.store'), [...]);

    // Assert
    $this->assertResponseCode($response, 403);
}
```

## Database Testing

**Setup:**
- `RefreshDatabase` trait refreshes database before each test
- `TestCaseWithDatabase` extends base TestCase with database helpers
- Query log enabled via `enableQueryLog()` for query count assertions

**Helpers:**
```php
// Create user with specific permissions
$data = $this->createUserWithPermission(['projects:create', 'projects:view']);

// Create user with specific role
$data = $this->createUserWithRole(Role::Admin);

// Assert query count
$this->enableQueryLog();
// ... run code
$this->assertQueryCount(5, 'Expected exactly 5 queries');
```

## Test Authentication

**E2E (Playwright):**
- Each test runs with registered test user (fixture setup)
- Email randomized to avoid conflicts: `john+${Math.random()}@doe.com`
- User registered in page fixture before test runs

**PHP API Tests:**
```php
// Authenticate via Passport
Passport::actingAs($data->user);
$response = $this->getJson(route('...'));

// Verify permissions enforced
$data = $this->createUserWithPermission(['projects:view']);  // Missing create
Passport::actingAs($data->user);
$response = $this->postJson(route('...'), [...]);
$this->assertResponseCode($response, 403);
```

## Configuration

**Playwright Config (`playwright.config.ts`):**
- Test directory: `e2e/`
- Parallel: Fully parallel by default (1 worker on CI)
- Browsers: Chromium, Firefox (Safari commented out)
- Timeout: 20 seconds per test
- Retries: 1 on CI, 0 locally
- Reporter: HTML on local, line on CI
- Trace: On-first-retry

**PHPUnit Config (`phpunit.xml`):**
- Test suite namespaces configured for autoloading
- Database connection for tests (separate DB or in-memory)
- Various test directories configured

---

*Testing analysis: 2026-02-10*
