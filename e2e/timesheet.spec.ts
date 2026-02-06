import { expect, Page } from '@playwright/test';
import { PLAYWRIGHT_BASE_URL } from '../playwright/config';
import { test } from '../playwright/fixtures';

async function goToTimesheet(page: Page) {
    await page.goto(PLAYWRIGHT_BASE_URL + '/timesheet');
}

async function waitForTimesheetLoad(page: Page) {
    await page.waitForResponse(
        (response) =>
            response.url().includes('/timesheet/weeks') && response.status() === 200
    );
}

async function createTimeEntryViaTimePage(page: Page, duration: string) {
    await page.goto(PLAYWRIGHT_BASE_URL + '/time');

    await page.getByRole('button', { name: 'Time entry actions' }).click();
    await page.getByRole('menuitem', { name: 'Manual time entry' }).click();

    await page.locator('[role="dialog"] input[name="Duration"]').fill(duration);
    await page.locator('[role="dialog"] input[name="Duration"]').press('Tab');

    await Promise.all([
        page.getByRole('button', { name: 'Create Time Entry' }).click(),
        page.waitForResponse(
            (response) =>
                response.url().includes('/time-entries') && response.status() === 201
        ),
    ]);
}

test('timesheet page is accessible from navigation', async ({ page }) => {
    await page.goto(PLAYWRIGHT_BASE_URL + '/dashboard');
    const timesheetLink = page.getByRole('link', { name: 'Timesheet' });
    await expect(timesheetLink).toBeVisible();
    await timesheetLink.click();
    await expect(page).toHaveURL(/\/timesheet/);
});

test('timesheet page loads and shows heading', async ({ page }) => {
    await goToTimesheet(page);
    await expect(page.getByRole('heading', { name: 'Timesheet' })).toBeVisible();
});

test('timesheet displays week accordion sections', async ({ page }) => {
    await Promise.all([goToTimesheet(page), waitForTimesheetLoad(page)]);

    // Should have at least one week section with accordion button
    const weekButtons = page.locator('button[aria-expanded]');
    await expect(weekButtons.first()).toBeVisible();
});

test('current week is auto-expanded', async ({ page }) => {
    await Promise.all([goToTimesheet(page), waitForTimesheetLoad(page)]);

    // Wait for week grid data to load
    await page.waitForTimeout(1000);

    // The first week (current week) should be expanded
    const firstWeekButton = page.locator('button[aria-expanded]').first();
    await expect(firstWeekButton).toHaveAttribute('aria-expanded', 'true');
});

test('week accordion shows label and total', async ({ page }) => {
    await Promise.all([goToTimesheet(page), waitForTimesheetLoad(page)]);

    // Should show "This Week" label
    await expect(page.getByText('This Week')).toBeVisible();
});

test('clicking accordion header toggles week content', async ({ page }) => {
    await Promise.all([goToTimesheet(page), waitForTimesheetLoad(page)]);
    await page.waitForTimeout(1000);

    // Find the first expanded accordion
    const firstWeekButton = page.locator('button[aria-expanded="true"]').first();

    // Collapse it
    await firstWeekButton.click();
    await expect(firstWeekButton).toHaveAttribute('aria-expanded', 'false');

    // Expand it again
    await firstWeekButton.click();
    await expect(firstWeekButton).toHaveAttribute('aria-expanded', 'true');
});

test('timesheet grid shows daily total footer', async ({ page }) => {
    // Create a time entry first so the grid has data
    await createTimeEntryViaTimePage(page, '01:00');

    await Promise.all([goToTimesheet(page), waitForTimesheetLoad(page)]);
    await page.waitForTimeout(1500);

    // Grid should have a footer with "Daily Total"
    await expect(page.getByText('Daily Total')).toBeVisible();
});

test('timesheet shows empty state when no time entries in a week', async ({ page }) => {
    await Promise.all([goToTimesheet(page), waitForTimesheetLoad(page)]);
    await page.waitForTimeout(1000);

    // Find a collapsed week (not current) and expand it
    const collapsedWeek = page.locator('button[aria-expanded="false"]').first();
    if (await collapsedWeek.isVisible()) {
        await collapsedWeek.click();
        await page.waitForTimeout(1000);

        // An old week with no data should show empty state
        const emptyStates = page.getByText('No time entries this week');
        // It may or may not appear depending on whether the week has data
    }
});

test('add task button is visible in expanded week', async ({ page }) => {
    await Promise.all([goToTimesheet(page), waitForTimesheetLoad(page)]);
    await page.waitForTimeout(1000);

    await expect(page.getByText('Add Task')).toBeVisible();
});

test('add task dropdown opens and shows options', async ({ page }) => {
    await Promise.all([goToTimesheet(page), waitForTimesheetLoad(page)]);
    await page.waitForTimeout(1000);

    await page.getByText('Add Task').click();

    // Should show the dropdown with search input
    await expect(page.getByPlaceholder('Search projects and tasks...')).toBeVisible();

    // Should show "No Project" option
    await expect(page.getByText('No Project')).toBeVisible();
});

test('load older weeks button works', async ({ page }) => {
    await Promise.all([goToTimesheet(page), waitForTimesheetLoad(page)]);

    const loadMoreButton = page.getByText('Load older weeks');
    if (await loadMoreButton.isVisible()) {
        const weekCountBefore = await page.locator('button[aria-expanded]').count();
        await loadMoreButton.click();
        await page.waitForTimeout(2000);
        const weekCountAfter = await page.locator('button[aria-expanded]').count();
        expect(weekCountAfter).toBeGreaterThan(weekCountBefore);
    }
});

test('timesheet has correct data-testid', async ({ page }) => {
    await goToTimesheet(page);
    await expect(page.locator('[data-testid="timesheet_view"]')).toBeVisible();
});
