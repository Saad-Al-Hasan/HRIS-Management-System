const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const manifestPath = path.join(__dirname, '..', '.holiday-test-accounts.json');
const BASE = 'http://localhost/HRIS-Management-System/';
const GUARD_URL = `${BASE}blackbox-tests/db-guard.php`;
const CLEANUP_URL = `${BASE}blackbox-tests/cleanup-holidays.php`;

function loadAccounts() {
    if (!fs.existsSync(manifestPath)) {
        throw new Error('Test accounts are missing. Run: php seed-holiday-test-users.php');
    }
    const data = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
    if (data.database !== 'hris_management_system_test') {
        throw new Error('Safety stop: account manifest does not identify the test database.');
    }
    if (!data.admin?.username || !data.admin?.password ||
        !data.employee?.username || !data.employee?.password) {
        throw new Error('Test account manifest is incomplete.');
    }
    return data;
}

async function verifyTestDatabase(request) {
    const response = await request.get(GUARD_URL);
    expect(response.ok(), 'Application Database preflight must succeed').toBeTruthy();
    const guard = await response.json();
    expect(guard.ok, 'Application Database must connect successfully').toBe(true);
    expect(guard.database, 'Browser tests may use only the isolated test database')
        .toBe('hris_management_system_test');
}

async function installTestRouting(context) {
    await context.route('**/HRIS-Management-System/public/index.php**', async route => {
        const original = new URL(route.request().url());
        const rewritten = new URL(original);
        rewritten.pathname = '/HRIS-Management-System/blackbox-tests/test-entry.php';
        await route.continue({ url: rewritten.toString() });
    });
}

async function assertTestEntrypoint(response) {
    expect(response, 'Expected an application response').not.toBeNull();
    expect(await response.headerValue('x-hris-test-entry'))
        .toBe('active');
    expect(await response.headerValue('x-hris-database-target'))
        .toBe('hris_management_system_test');
}

async function login(page, account) {
    const response = await page.goto('public/index.php?action=login');
    await assertTestEntrypoint(response);
    await page.locator('input[name="username"]').fill(account.username);
    await page.locator('input[name="password"]').fill(account.password);
    await page.locator('button[type="submit"]').click();
}

async function openHolidayPage(page) {
    const response = await page.goto('public/index.php?action=holidays');
    await assertTestEntrypoint(response);
    await expect(page.locator('h1')).toHaveText('Holiday Management');
}

async function assertHolidayStored(request, name, expectedType) {
    const url = new URL(GUARD_URL);
    url.searchParams.set('holiday_name', name);
    const response = await request.get(url.toString());
    expect(response.ok(), 'Read-only database verification must succeed').toBeTruthy();
    const result = await response.json();
    expect(result.database).toBe('hris_management_system_test');
    expect(result.holiday, `Expected ${name} to exist in the test database`).not.toBeNull();
    expect(result.holiday.holiday_type).toBe(expectedType);
}

// Unique per execution; cleanup is restricted to this exact prefix.
const runPrefix = `BBHOL-${Date.now()}-${crypto.randomBytes(6).toString('hex')}-`;
const dateSeed = Math.floor(Date.now() / 1000) % 1800;
const testDate = (offset = 0) => {
    const base = Date.UTC(2091, 0, 1);
    return new Date(base + (dateSeed + offset) * 86400000).toISOString().slice(0, 10);
};

function testName(suffix) {
    return `${runPrefix}${suffix}`;
}

async function addHoliday(page, name, date, type, description = '') {
    const form = page.locator('form[action*="holiday-store"]');

    await form.locator('#holiday_name').fill(name);
    await form.locator('#holiday_date').fill(date);
    await form.locator('#holiday_type').selectOption(type);

    if (description) {
        await form.locator('#description').fill(description);
    }

    await Promise.all([
        page.waitForURL(/action=holidays/),
        form.getByRole('button', { name: 'Add Holiday' }).click()
    ]);

    await expect(page.getByRole('status'))
        .toContainText('Holiday added successfully.');

    // Explicitly reload through the test-only entrypoint.
    const response = await page.goto('public/index.php?action=holidays');

    await assertTestEntrypoint(response);

    await expect(page.locator('h1'))
        .toHaveText('Holiday Management');
}

test.describe('Holiday Management — Automated Black-Box Tests', () => {
    let accounts;

    test.beforeAll(async () => {
        accounts = loadAccounts();
    });

    test.beforeEach(async ({ page, context, request }) => {
        // Fail closed before any browser navigation or mutation.
        await verifyTestDatabase(request);
        await installTestRouting(context);
        await login(page, accounts.admin);
        await openHolidayPage(page);
    });

    test.afterAll(async () => {
        // Cleanup only rows whose names start with this execution's unique prefix.
        // The endpoint independently verifies the test DB and validates the prefix.
        const api = await require('@playwright/test').request.newContext();
        try {
            const response = await api.post(CLEANUP_URL, {
                form: { prefix: runPrefix }
            });
            if (!response.ok()) {
                throw new Error(`Test-only cleanup failed with HTTP ${response.status()}`);
            }
            const result = await response.json();
            if (!result.ok || result.database !== 'hris_management_system_test') {
                throw new Error('Test-only cleanup safety verification failed.');
            }
            console.log(`Holiday test cleanup: ${result.deleted} record(s) removed from test DB.`);
        } finally {
            await api.dispose();
        }
    });

    test('BB-HOL-01 — Administrator can open Holiday Management', async ({ page }) => {
        await expect(page.locator('h1')).toHaveText('Holiday Management');
        await expect(page.getByRole('heading', { name: 'Add New Holiday' })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Organization Holidays' })).toBeVisible();
    });

    test('BB-HOL-02 — Add form exposes required fields and supported types', async ({ page }) => {
        const form = page.locator('form[action*="holiday-store"]');
        await expect(form.locator('#holiday_name')).toHaveAttribute('required', '');
        await expect(form.locator('#holiday_date')).toHaveAttribute('required', '');
        await expect(form.locator('#holiday_type')).toHaveAttribute('required', '');
        await expect(form.locator('#holiday_name')).toHaveAttribute('maxlength', '100');
        await expect(form.locator('#holiday_type option')).toHaveText([
            'Select holiday type', 'Government', 'Organizational'
        ]);
        await expect(form.locator('#description')).toBeVisible();
    });

    test('BB-HOL-03 — Administrator can add a Government holiday', async ({ page, request }) => {
        const name = testName('Government');
        await addHoliday(page, name, testDate(1), 'Government', 'Automated black-box test record.');
        await assertHolidayStored(request, name, 'Government');
        await expect(page.locator('table.data-table tbody tr').filter({ hasText: name }))
            .toContainText('Government');
    });

    test('BB-HOL-04 — Administrator can add an Organizational holiday', async ({ page, request }) => {
        const name = testName('Organizational');
        await addHoliday(page, name, testDate(2), 'Organizational');
        await assertHolidayStored(request, name, 'Organizational');
        await expect(page.locator('table.data-table tbody tr').filter({ hasText: name }))
            .toContainText('Organizational');
    });

    test('BB-HOL-05 — Description is optional', async ({ page, request }) => {
        const name = testName('NoDescription');
        const form = page.locator('form[action*="holiday-store"]');
        await expect(form.locator('#description')).not.toHaveAttribute('required', '');
        await addHoliday(page, name, testDate(3), 'Government');
        await assertHolidayStored(request, name, 'Government');
        await expect(page.locator('table.data-table tbody tr').filter({ hasText: name })).toBeVisible();
    });

    test('BB-HOL-06 — Required fields prevent an empty form submission', async ({ page }) => {
        const form = page.locator('form[action*="holiday-store"]');
        const validity = await form.evaluate(el => ({
            formValid: el.checkValidity(),
            nameValid: el.querySelector('#holiday_name').checkValidity(),
            dateValid: el.querySelector('#holiday_date').checkValidity(),
            typeValid: el.querySelector('#holiday_type').checkValidity()
        }));
        expect(validity.formValid).toBe(false);
        expect(validity.nameValid).toBe(false);
        expect(validity.dateValid).toBe(false);
        expect(validity.typeValid).toBe(false);
    });

    test('BB-HOL-07 — Holiday name is limited to 100 characters in the form', async ({ page }) => {
        const nameField = page.locator('#holiday_name');
        await expect(nameField).toHaveAttribute('maxlength', '100');
        await nameField.fill('X'.repeat(120));
        await expect(nameField).toHaveValue('X'.repeat(100));
    });

    test('BB-HOL-08 — Duplicate holiday date is rejected', async ({ page, request }) => {
        const name = testName('DuplicateOriginal');
        const date = testDate(4);
        await addHoliday(page, name, date, 'Government');
        await assertHolidayStored(request, name, 'Government');
        await openHolidayPage(page);
        const form = page.locator('form[action*="holiday-store"]');
        await form.locator('#holiday_name').fill(testName('DuplicateAttempt'));
        await form.locator('#holiday_date').fill(date);
        await form.locator('#holiday_type').selectOption('Organizational');
        await form.getByRole('button', { name: 'Add Holiday' }).click();
        await expect(page.getByRole('alert')).toContainText(
            'A holiday already exists for the selected date.'
        );
    });

    test('BB-HOL-09 — Search returns a holiday for the exact date', async ({ page, request }) => {
        const name = testName('Search');
        const date = testDate(5);
        await addHoliday(page, name, date, 'Government');
        await assertHolidayStored(request, name, 'Government');
        await page.locator('input.holiday-search-date').fill(date);
        await page.getByRole('button', { name: 'Search Holiday' }).click();
        await expect(page.locator('table.data-table tbody tr')).toHaveCount(1);
        await expect(page.locator('table.data-table')).toContainText(name);
    });

    test('BB-HOL-10 — Search with no matching date shows the empty state', async ({ page }) => {
        await page.locator('input.holiday-search-date').fill('2098-06-16');
        await page.getByRole('button', { name: 'Search Holiday' }).click();
        await expect(page.getByRole('heading', { name: 'No holidays found' })).toBeVisible();
    });

    test('BB-HOL-11 — Reset clears the date filter', async ({ page }) => {
        await page.locator('input.holiday-search-date').fill('2098-07-17');
        await page.getByRole('button', { name: 'Search Holiday' }).click();
        await expect(page.getByRole('heading', { name: 'No holidays found' })).toBeVisible();
        await page.locator('a.holiday-reset-button').click();
        await expect(page.locator('input.holiday-search-date')).toHaveValue('');
        await expect(page.locator('h1')).toHaveText('Holiday Management');
    });

    test('BB-HOL-12 — Delete requires confirmation and removes the selected holiday', async ({ page, request }) => {
        const name = testName('Delete');
        await addHoliday(page, name, testDate(8), 'Organizational');
        await assertHolidayStored(request, name, 'Organizational');
        const row = page.locator('table.data-table tbody tr').filter({ hasText: name });
        await expect(row).toBeVisible();
        page.once('dialog', async dialog => {
            expect(dialog.message()).toBe('Are you sure you want to delete this holiday?');
            await dialog.accept();
        });
        await row.getByRole('button', { name: 'Delete' }).click();
        await expect(page.getByRole('status')).toContainText('Holiday deleted successfully.');
        await expect(page.locator('table.data-table tbody tr').filter({ hasText: name })).toHaveCount(0);
        const url = new URL(GUARD_URL);
        url.searchParams.set('holiday_name', name);
        const verification = await (await request.get(url.toString())).json();
        expect(verification.holiday).toBeNull();
    });

    test('BB-HOL-13 — Employee cannot access the administrator Holiday Management page', async ({ page }) => {
        await page.goto('public/index.php?action=logout');

        // Navigate explicitly to the login page and verify test routing there.
        const loginResponse = await page.goto('public/index.php?action=login');
        await assertTestEntrypoint(loginResponse);

        await page.locator('input[name="username"]').fill(accounts.employee.username);
        await page.locator('input[name="password"]').fill(accounts.employee.password);
        await page.locator('button[type="submit"]').click();
        await page.goto('public/index.php?action=holidays');
        await expect(page.getByRole('heading', { name: 'Holiday Management' })).toHaveCount(0);
        await expect(page.locator('body')).not.toContainText('Add New Holiday');
    });
});
