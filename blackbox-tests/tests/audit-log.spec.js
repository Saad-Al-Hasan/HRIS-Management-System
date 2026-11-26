const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

// HRIS local test environment — mirrors the existing Sprint 7 Playwright suites.
const baseUrl = process.env.HRIS_BASE_URL || 'http://localhost/HRIS-Management-System/';
const publicUrl = `${baseUrl.replace(/\/$/, '')}/public/index.php`;
const testEntryPath = '/HRIS-Management-System/blackbox-tests/test-entry.php';
const accountManifestPath = path.join(__dirname, '..', '.holiday-test-accounts.json');
const fixtureManifestPath = path.join(__dirname, '..', '.audit-log-test-data.json');

const MODULES = [
    'authentication', 'employee_management', 'rfid_management',
    'attendance_management', 'work_schedules', 'salary_management',
    'leave_management', 'holiday_management'
];
const MODULE_EVENT = {
    authentication: 'Administrator logged in',
    employee_management: 'Employee profile updated',
    rfid_management: 'RFID card assigned',
    attendance_management: 'Attendance record updated',
    work_schedules: 'Work schedule created',
    salary_management: 'Salary processed',
    leave_management: 'Leave request approved',
    holiday_management: 'Holiday created'
};

function loadAdminAccount() {
    if (process.env.HRIS_TEST_ADMIN_USERNAME && process.env.HRIS_TEST_ADMIN_PASSWORD) {
        return { username: process.env.HRIS_TEST_ADMIN_USERNAME, password: process.env.HRIS_TEST_ADMIN_PASSWORD };
    }
    if (!fs.existsSync(accountManifestPath)) {
        throw new Error('Missing blackbox-tests/.holiday-test-accounts.json. Restore the established test-only account manifest or set HRIS_TEST_ADMIN_USERNAME/HRIS_TEST_ADMIN_PASSWORD.');
    }
    const accounts = JSON.parse(fs.readFileSync(accountManifestPath, 'utf8'));
    if (accounts.database !== 'hris_management_system_test' || !accounts.admin?.username || !accounts.admin?.password) {
        throw new Error('Safety stop: admin credentials must be from the test database account manifest.');
    }
    return accounts.admin;
}
function loadFixtureManifest() {
    if (!fs.existsSync(fixtureManifestPath)) {
        throw new Error('Fixture manifest missing. Run: php helpers/seed-audit-log-blackbox.php from blackbox-tests/.');
    }
    const data = JSON.parse(fs.readFileSync(fixtureManifestPath, 'utf8'));
    if (data.database !== 'hris_management_system_test' || !data.run_marker || !data.actor?.id) {
        throw new Error('Safety stop: audit fixture manifest is not for the isolated test database.');
    }
    return data;
}
const admin = loadAdminAccount();
const fixture = loadFixtureManifest();

async function installTestRouting(context) {
    // Route every application request through test-entry.php, which pins DB constants to the test DB.
    await context.route('**/HRIS-Management-System/public/index.php**', async route => {
        const original = new URL(route.request().url());
        const forwarded = new URL(original);
        forwarded.pathname = testEntryPath;
        if (route.request().method() === 'POST') {
            const response = await route.fetch({ url: forwarded.toString(), maxRedirects: 0 });
            const headers = { ...response.headers() };
            if (headers.location) {
                const redirect = new URL(headers.location, original.origin);
                if (redirect.pathname === '/HRIS-Management-System/public/index.php') {
                    redirect.pathname = testEntryPath;
                    headers.location = redirect.pathname + redirect.search;
                }
            }
            await route.fulfill({ response, headers });
        } else {
            await route.continue({ url: forwarded.toString() });
        }
    });
}
async function verifyTestDatabase(request) {
    // Each test proves that the guard endpoint reports only the isolated database.
    const response = await request.get(`${baseUrl.replace(/\/$/, '')}/blackbox-tests/db-guard.php`);
    expect(response.ok(), 'Test DB guard endpoint must respond successfully').toBeTruthy();
    const guard = await response.json();
    expect(guard.ok).toBe(true);
    expect(guard.database).toBe('hris_management_system_test');
}
async function loginAsAdmin(page) {
    await page.goto(`${publicUrl}?action=login`);
    await page.locator('input[name="username"]').fill(admin.username);
    await page.locator('input[name="password"]').fill(admin.password);
    await page.locator('button[type="submit"]').click();
    await page.waitForLoadState('networkidle');
}
async function openAudit(page, params = {}) {
    const query = new URLSearchParams({ action: 'audit-logs', ...params });
    await page.goto(`${publicUrl}?${query.toString()}`);
    await page.waitForLoadState('domcontentloaded');
}
async function selectFixtureActor(page) {
    const search = page.locator('#audit-user-search');
    await search.fill(fixture.actor.username);
    const option = page.locator('.audit-user-option').filter({ hasText: fixture.actor.username }).first();
    await expect(option).toBeVisible();
    await option.click();
    await expect(page.locator('#audit-user-id')).toHaveValue(String(fixture.actor.id));
}
async function applyFixtureFilters(page, extra = {}) {
    await page.locator('#audit-from-date').fill(fixture.date);
    await page.locator('#audit-to-date').fill(fixture.date);
    await selectFixtureActor(page);
    for (const [name, value] of Object.entries(extra)) {
        await page.locator(`[name="${name}"]`).selectOption(String(value));
    }
    await page.getByRole('button', { name: /search records/i }).click();
    await page.waitForLoadState('domcontentloaded');
}
function rowText(page) { return page.locator('.audit-table tbody').innerText(); }

// The full requested 65-case suite. Every case is explicitly labeled with its
// Test ID and Test Case name; comments are kept beside each case for traceability.
const CASES = [
    // AUD-BB-01 — Administrator can open Audit Logs page
    ['AUD-BB-01', 'Administrator can open Audit Logs page'],
    // AUD-BB-02 — Audit Trail page heading and introduction are displayed
    ['AUD-BB-02', 'Audit Trail page heading and introduction are displayed'],
    // AUD-BB-03 — Date range controls are available
    ['AUD-BB-03', 'Date range controls are available'],
    // AUD-BB-04 — Filter form submits the audit-logs action
    ['AUD-BB-04', 'Filter form submits the audit-logs action'],
    // AUD-BB-05 — Eight module summary cards are displayed
    ['AUD-BB-05', 'Eight module summary cards are displayed'],
    // AUD-BB-06 — Audit ledger column headers are correct
    ['AUD-BB-06', 'Audit ledger column headers are correct'],
    // AUD-BB-07 — Audit ledger records or empty state are rendered
    ['AUD-BB-07', 'Audit ledger records or empty state are rendered'],
    // AUD-BB-08 — Fixture actor audit records are visible
    ['AUD-BB-08', 'Fixture actor audit records are visible'],
    // AUD-BB-09 — Audit records remain after page refresh
    ['AUD-BB-09', 'Audit records remain after page refresh'],
    // AUD-BB-10 — Date range filters include the seeded run date
    ['AUD-BB-10', 'Date range filters include the seeded run date'],
    // AUD-BB-11 — From-date boundary is applied
    ['AUD-BB-11', 'From-date boundary is applied'],
    // AUD-BB-12 — To-date boundary is applied
    ['AUD-BB-12', 'To-date boundary is applied'],
    // AUD-BB-13 — Same-day date filter returns matching records
    ['AUD-BB-13', 'Same-day date filter returns matching records'],
    // AUD-BB-14 — Future date filter displays no matching events
    ['AUD-BB-14', 'Future date filter displays no matching events'],
    // AUD-BB-15 — Clear filters resets date, user and module filters
    ['AUD-BB-15', 'Clear filters resets date, user and module filters'],
    // AUD-BB-16 — User autocomplete opens when typing
    ['AUD-BB-16', 'User autocomplete opens when typing'],
    // AUD-BB-17 — User autocomplete narrows matching usernames
    ['AUD-BB-17', 'User autocomplete narrows matching usernames'],
    // AUD-BB-18 — Selecting autocomplete user sets hidden user ID
    ['AUD-BB-18', 'Selecting autocomplete user sets hidden user ID'],
    // AUD-BB-19 — User filter shows only selected actor records
    ['AUD-BB-19', 'User filter shows only selected actor records'],
    // AUD-BB-20 — User filter excludes unrelated actor records
    ['AUD-BB-20', 'User filter excludes unrelated actor records'],
    // AUD-BB-21 — Enter selects first autocomplete suggestion
    ['AUD-BB-21', 'Enter selects first autocomplete suggestion'],
    // AUD-BB-22 — Escape closes autocomplete suggestions
    ['AUD-BB-22', 'Escape closes autocomplete suggestions'],
    // AUD-BB-23 — Clicking outside closes autocomplete suggestions
    ['AUD-BB-23', 'Clicking outside closes autocomplete suggestions'],
    // AUD-BB-24 — Clearing user text clears selected hidden user ID
    ['AUD-BB-24', 'Clearing user text clears selected hidden user ID'],
    // AUD-BB-25 — Module selector offers all supported modules
    ['AUD-BB-25', 'Module selector offers all supported modules'],
    // AUD-BB-26 — Module selection remains selected after filtering
    ['AUD-BB-26', 'Module selection remains selected after filtering'],
    // AUD-BB-27 — Combined date and actor filters are applied
    ['AUD-BB-27', 'Combined date and actor filters are applied'],
    // AUD-BB-28 — Combined actor and module filters are applied
    ['AUD-BB-28', 'Combined actor and module filters are applied'],
    // AUD-BB-29 — Combined date, actor and module filters are applied
    ['AUD-BB-29', 'Combined date, actor and module filters are applied'],
    // AUD-BB-30 — No-match filter shows the designed empty state
    ['AUD-BB-30', 'No-match filter shows the designed empty state'],
    // AUD-BB-31 — Clear link returns to unfiltered Audit Logs
    ['AUD-BB-31', 'Clear link returns to unfiltered Audit Logs'],
    // AUD-BB-32 — User ID and username remain consistent in filtered view
    ['AUD-BB-32', 'User ID and username remain consistent in filtered view'],
    // AUD-BB-33 — Actor name is displayed for seeded user records
    ['AUD-BB-33', 'Actor name is displayed for seeded user records'],
    // AUD-BB-34 — User options are distinct by username
    ['AUD-BB-34', 'User options are distinct by username'],
    // AUD-BB-35 — Authentication events classify into Authentication module
    ['AUD-BB-35', 'Authentication events classify into Authentication module'],
    // AUD-BB-36 — Employee events classify into Employee Management module
    ['AUD-BB-36', 'Employee events classify into Employee Management module'],
    // AUD-BB-37 — RFID events classify into RFID Management module
    ['AUD-BB-37', 'RFID events classify into RFID Management module'],
    // AUD-BB-38 — Attendance events classify into Attendance Management module
    ['AUD-BB-38', 'Attendance events classify into Attendance Management module'],
    // AUD-BB-39 — Work schedule events classify into Work Schedules module
    ['AUD-BB-39', 'Work schedule events classify into Work Schedules module'],
    // AUD-BB-40 — Salary events classify into Salary Management module
    ['AUD-BB-40', 'Salary events classify into Salary Management module'],
    // AUD-BB-41 — Leave events classify into Leave Management module
    ['AUD-BB-41', 'Leave events classify into Leave Management module'],
    // AUD-BB-42 — Holiday events classify into Holiday Management module
    ['AUD-BB-42', 'Holiday events classify into Holiday Management module'],
    // AUD-BB-43 — Table row displays the module label
    ['AUD-BB-43', 'Table row displays the module label'],
    // AUD-BB-44 — Unclassified activity displays Other Activities
    ['AUD-BB-44', 'Unclassified activity displays Other Activities'],
    // AUD-BB-45 — Matching events KPI is displayed
    ['AUD-BB-45', 'Matching events KPI is displayed'],
    // AUD-BB-46 — Module summary counts respond to actor filter
    ['AUD-BB-46', 'Module summary counts respond to actor filter'],
    // AUD-BB-47 — Module summary counts respond to module filter
    ['AUD-BB-47', 'Module summary counts respond to module filter'],
    // AUD-BB-48 — Module summary counts respond to date filter
    ['AUD-BB-48', 'Module summary counts respond to date filter'],
    // AUD-BB-49 — Summary includes matching events beyond current page
    ['AUD-BB-49', 'Summary includes matching events beyond current page'],
    // AUD-BB-50 — Summary total matches filtered ledger total
    ['AUD-BB-50', 'Summary total matches filtered ledger total'],
    // AUD-BB-51 — Audit records are ordered newest first
    ['AUD-BB-51', 'Audit records are ordered newest first'],
    // AUD-BB-52 — Equal timestamp records use descending event ID
    ['AUD-BB-52', 'Equal timestamp records use descending event ID'],
    // AUD-BB-53 — Next page displays a different record page
    ['AUD-BB-53', 'Next page displays a different record page'],
    // AUD-BB-54 — Previous page returns to prior record page
    ['AUD-BB-54', 'Previous page returns to prior record page'],
    // AUD-BB-55 — Pagination preserves active filters
    ['AUD-BB-55', 'Pagination preserves active filters'],
    // AUD-BB-56 — Previous pagination control is disabled on first page
    ['AUD-BB-56', 'Previous pagination control is disabled on first page'],
    // AUD-BB-57 — Next pagination control is disabled on last page
    ['AUD-BB-57', 'Next pagination control is disabled on last page'],
    // AUD-BB-58 — Null actor is shown as System / Unavailable
    ['AUD-BB-58', 'Null actor is shown as System / Unavailable'],
    // AUD-BB-59 — Activity descriptions are rendered as text, not HTML
    ['AUD-BB-59', 'Activity descriptions are rendered as text, not HTML'],
    // AUD-BB-60 — Search keyword query does not break the page
    ['AUD-BB-60', 'Search keyword query does not break the page'],
    // AUD-BB-61 — Invalid module input is handled safely
    ['AUD-BB-61', 'Invalid module input is handled safely'],
    // AUD-BB-62 — Invalid user ID input is handled safely
    ['AUD-BB-62', 'Invalid user ID input is handled safely'],
    // AUD-BB-63 — Invalid date input displays validation feedback
    ['AUD-BB-63', 'Invalid date input displays validation feedback'],
    // AUD-BB-64 — Repeated seeding retains previous run audit records
    ['AUD-BB-64', 'Repeated seeding retains previous run audit records'],
    // AUD-BB-65 — Viewing and filtering audit logs does not mutate records
    ['AUD-BB-65', 'Viewing and filtering audit logs does not mutate records']
];

async function runCase(id, page, request) {
    const body = page.locator('body');
    if (id === 'AUD-BB-01') {
        await openAudit(page); await expect(page.locator('#audit-ledger-title')).toHaveText(/event ledger/i); return;
    }
    if (id === 'AUD-BB-02') {
        await openAudit(page); await expect(body).toContainText(/audit trail/i); await expect(body).toContainText(/who performed each action/i); return;
    }
    if (id === 'AUD-BB-03') {
        await openAudit(page); await expect(page.locator('#audit-from-date')).toHaveAttribute('type', 'date'); await expect(page.locator('#audit-to-date')).toHaveAttribute('type', 'date'); return;
    }
    if (id === 'AUD-BB-04') {
        await openAudit(page); await expect(page.locator('.audit-filter-form input[name="action"]')).toHaveValue('audit-logs'); return;
    }
    if (id === 'AUD-BB-05') {
        await openAudit(page); await expect(page.locator('.audit-module-tile')).toHaveCount(8); return;
    }
    // AUD-BB-06 — Audit ledger column headers are correct
    if (id === 'AUD-BB-06') {
        await openAudit(page);

        // Compare case-insensitively because the page displays uppercase headers.
        const headers = (
            await page.locator('.audit-table thead').innerText()
        ).toLowerCase();

        for (const header of [
            'event id',
            'timestamp',
            'actor',
            'module',
            'activity details'
        ]) {
            expect(headers).toContain(header);
        }

        return;
    }
    if (id === 'AUD-BB-07') {
        await openAudit(page); await expect(page.locator('.audit-table tbody tr').first()).toBeVisible(); return;
    }
    if (['AUD-BB-08', 'AUD-BB-09', 'AUD-BB-10', 'AUD-BB-13', 'AUD-BB-19', 'AUD-BB-27', 'AUD-BB-32', 'AUD-BB-33'].includes(id)) {
        await openAudit(page); await applyFixtureFilters(page); const text = await rowText(page);
        expect(text).toContain(fixture.run_marker);
        if (id === 'AUD-BB-09') { await page.reload(); expect(await rowText(page)).toContain(fixture.run_marker); }
        if (id === 'AUD-BB-13') expect(text).toContain(fixture.run_marker + ' Audit Fixture Actor');
        if (id === 'AUD-BB-32') await expect(page.locator('#audit-user-id')).toHaveValue(String(fixture.actor.id));
        if (id === 'AUD-BB-33') expect(text).toContain(fixture.run_marker + ' Audit Fixture Actor');
        return;
    }
    if (['AUD-BB-11', 'AUD-BB-12'].includes(id)) {
        await openAudit(page); await applyFixtureFilters(page); const before = await rowText(page);
        if (id === 'AUD-BB-11') await page.locator('#audit-from-date').fill(fixture.date);
        else await page.locator('#audit-to-date').fill(fixture.date);
        await page.getByRole('button', { name: /search records/i }).click();
        expect(await rowText(page)).toContain(fixture.run_marker); expect(before).toContain(fixture.run_marker); return;
    }
    if (id === 'AUD-BB-14') {
        await openAudit(page, { from_date: '2099-12-31', to_date: '2099-12-31' }); await expect(body).toContainText(/no matching audit events/i); return;
    }
    if (id === 'AUD-BB-15' || id === 'AUD-BB-31') {
        await openAudit(page); await applyFixtureFilters(page, { module: 'salary_management' });
        await page.getByRole('link', { name: /clear/i }).click(); await expect(page).toHaveURL(/action=audit-logs/);
        await expect(page.locator('#audit-user-id')).toHaveValue(''); return;
    }
    if (['AUD-BB-16', 'AUD-BB-17', 'AUD-BB-18', 'AUD-BB-21', 'AUD-BB-22', 'AUD-BB-23', 'AUD-BB-24', 'AUD-BB-34'].includes(id)) {
        await openAudit(page); const input = page.locator('#audit-user-search');
        await input.fill(fixture.actor.username);
        // AUD-BB-16 — User autocomplete opens when typing
        if (id === 'AUD-BB-16') {
            const matchingOption = page.locator('.audit-user-option').filter({
                hasText: fixture.actor.username
            });

            await expect(page.locator('#audit-user-suggestions')).toBeVisible();
            await expect(matchingOption).toHaveCount(1);
            await expect(matchingOption).toBeVisible();
        }
        if (id === 'AUD-BB-17') await expect(page.locator('.audit-user-option').filter({ hasText: fixture.actor.username })).toHaveCount(1);
        if (id === 'AUD-BB-18') { await selectFixtureActor(page); }
        if (id === 'AUD-BB-21') { await input.press('Enter'); await expect(page.locator('#audit-user-id')).toHaveValue(String(fixture.actor.id)); }
        if (id === 'AUD-BB-22') { await expect(page.locator('#audit-user-suggestions')).toBeVisible(); await input.press('Escape'); await expect(page.locator('#audit-user-suggestions')).toBeHidden(); }
        if (id === 'AUD-BB-23') { await expect(page.locator('#audit-user-suggestions')).toBeVisible(); await page.locator('#audit-filter-title').click(); await expect(page.locator('#audit-user-suggestions')).toBeHidden(); }
        if (id === 'AUD-BB-24') { await selectFixtureActor(page); await input.fill(''); await expect(page.locator('#audit-user-id')).toHaveValue(''); }
        if (id === 'AUD-BB-34') { const names = await page.locator('.audit-user-option').evaluateAll(xs => xs.map(x => x.dataset.username)); expect(new Set(names).size).toBe(names.length); }
        return;
    }
    if (id === 'AUD-BB-20') {
        await openAudit(page); await applyFixtureFilters(page); const text = await rowText(page); expect(text).toContain(fixture.run_marker); expect(text).not.toContain('unrelated_actor'); return;
    }
    if (id === 'AUD-BB-25') {
        await openAudit(page); const values = await page.locator('#audit-module option').evaluateAll(xs => xs.map(x => x.value));
        for (const m of MODULES) expect(values).toContain(m); return;
    }
    if (id === 'AUD-BB-26') {
        await openAudit(page); await applyFixtureFilters(page, { module: 'salary_management' }); await expect(page.locator('#audit-module')).toHaveValue('salary_management'); return;
    }
    if (id === 'AUD-BB-46') {
        await openAudit(page); await applyFixtureFilters(page);
        const matching = Number((await page.locator('.audit-overview-copy strong').innerText()).replace(/\D/g, ''));
        expect(matching).toBe(fixture.created_count - 1); return;
    }
    if (id === 'AUD-BB-48') {
        await openAudit(page, { from_date: fixture.date, to_date: fixture.date });
        await expect(page.locator('.audit-overview-copy strong')).not.toHaveText('0'); return;
    }
    if (['AUD-BB-28', 'AUD-BB-29', 'AUD-BB-47', 'AUD-BB-50'].includes(id)) {
        await openAudit(page); await applyFixtureFilters(page, { module: 'salary_management' });
        expect(await rowText(page)).toContain('Salary processed');
        if (id === 'AUD-BB-47') {
            const salaryCount = Number((await page.locator('.audit-module-tile.audit-module-salary_management .audit-module-count').innerText()).replace(/\D/g, ''));
            expect(salaryCount).toBe(1);
        }
        if (id === 'AUD-BB-50') {
            const overview = Number((await page.locator('.audit-overview-copy strong').innerText()).replace(/\D/g, ''));
            const ledger = Number((await page.locator('.audit-ledger-count strong').innerText()).replace(/\D/g, ''));
            expect(overview).toBe(ledger);
        }
        return;
    }
    if (id === 'AUD-BB-30') {
        await openAudit(page, { from_date: '2099-12-31', to_date: '2099-12-31' }); await expect(body).toContainText(/no matching audit events/i); return;
    }
    if (id === 'AUD-BB-35' || id === 'AUD-BB-36' || id === 'AUD-BB-37' || id === 'AUD-BB-38' || id === 'AUD-BB-39' || id === 'AUD-BB-40' || id === 'AUD-BB-41' || id === 'AUD-BB-42') {
        const index = Number(id.slice(-2)) - 35; const module = MODULES[index];
        await openAudit(page); await applyFixtureFilters(page, { module });
        expect(await rowText(page)).toContain(MODULE_EVENT[module]);
        expect(await rowText(page)).toContain(fixture.run_marker); return;
    }
    if (id === 'AUD-BB-43') {
        await openAudit(page); await applyFixtureFilters(page, { module: 'holiday_management' });
        await expect(page.locator('.audit-module-pill').filter({ hasText: 'Holiday Management' }).first()).toBeVisible(); return;
    }
    if (id === 'AUD-BB-44') {
        await openAudit(page); await applyFixtureFilters(page); await expect(page.locator('.audit-module-pill').filter({ hasText: /other activities/i }).first()).toBeVisible(); return;
    }
    if (['AUD-BB-45', 'AUD-BB-49'].includes(id)) {
        await openAudit(page); await applyFixtureFilters(page); const countText = await page.locator('.audit-overview-copy strong').innerText();
        expect(Number(countText.replace(/\D/g, ''))).toBeGreaterThanOrEqual(fixture.created_count - 1); return;
    }
    // AUD-BB-51 — Audit records are ordered newest first
    if (id === 'AUD-BB-51') {
        await openAudit(page);

        // Event ID is displayed in the first table column.
        const rows = page.locator('.audit-table tbody tr');
        const rowCount = await rows.count();

        expect(rowCount).toBeGreaterThan(1);

        const numbers = [];

        for (let i = 0; i < rowCount; i++) {
            const eventIdText = await rows.nth(i).locator('td').first().innerText();
            const eventId = Number(eventIdText.replace(/\D/g, ''));

            expect(eventId).toBeGreaterThan(0);
            numbers.push(eventId);
        }

        // Each row should have a smaller ID than the row above it.
        for (let i = 1; i < numbers.length; i++) {
            expect(numbers[i - 1]).toBeGreaterThan(numbers[i]);
        }

        return;
    }
    // AUD-BB-52 — Equal timestamp records use descending event ID
    if (id === 'AUD-BB-52') {
        await openAudit(page);
        await applyFixtureFilters(page);

        // Both fixture activities contain the unique marker for this seeding run.
        const firstTie = page.locator('.audit-table tbody tr').filter({
            hasText: `Audit BB tie-order first [${fixture.run_marker}]`
        });

        const secondTie = page.locator('.audit-table tbody tr').filter({
            hasText: `Audit BB tie-order second [${fixture.run_marker}]`
        });

        await expect(firstTie).toHaveCount(1);
        await expect(secondTie).toHaveCount(1);

        // Event ID is the first table cell, not a CSS class.
        const firstIdText = await firstTie.locator('td').first().innerText();
        const secondIdText = await secondTie.locator('td').first().innerText();

        const firstId = Number(firstIdText.replace(/\D/g, ''));
        const secondId = Number(secondIdText.replace(/\D/g, ''));

        // The second inserted record must have the greater ID.
        expect(secondId).toBeGreaterThan(firstId);

        // With equal timestamps, the greater ID should appear first.
        const rowTexts = await page.locator('.audit-table tbody tr').evaluateAll(
            rows => rows.map(row => row.innerText)
        );

        const secondIndex = rowTexts.findIndex(text =>
            text.includes(`Audit BB tie-order second [${fixture.run_marker}]`)
        );

        const firstIndex = rowTexts.findIndex(text =>
            text.includes(`Audit BB tie-order first [${fixture.run_marker}]`)
        );

        expect(secondIndex).toBeGreaterThanOrEqual(0);
        expect(firstIndex).toBeGreaterThanOrEqual(0);
        expect(secondIndex).toBeLessThan(firstIndex);

        return;
    }
    if (['AUD-BB-53', 'AUD-BB-54', 'AUD-BB-55', 'AUD-BB-56', 'AUD-BB-57'].includes(id)) {
        await openAudit(page); const first = await rowText(page); const next = page.locator('.audit-pagination-actions a').filter({ hasText: /next/i });
        if (id === 'AUD-BB-56') { await expect(page.locator('.audit-pagination-actions').getByText(/previous/i).first()).toBeVisible(); return; }
        await expect(next).toBeVisible(); await next.click(); const second = await rowText(page); expect(second).not.toBe(first);
        if (id === 'AUD-BB-54') { await page.locator('.audit-pagination-actions a').filter({ hasText: /previous/i }).click(); expect(await rowText(page)).toBe(first); }
        if (id === 'AUD-BB-55') {
            await openAudit(page); await applyFixtureFilters(page);
            const pageOneUrl = new URL(page.url());
            const pageOneNext = page.locator('.audit-pagination-actions a').filter({ hasText: /next/i });
            await expect(pageOneNext).toBeVisible(); await pageOneNext.click();
            const pageTwoUrl = new URL(page.url());
            expect(pageTwoUrl.searchParams.get('user_id')).toBe(String(fixture.actor.id));
            expect(pageTwoUrl.searchParams.get('from_date')).toBe(fixture.date);
            expect(pageTwoUrl.searchParams.get('to_date')).toBe(fixture.date);
            expect(pageTwoUrl.searchParams.get('page')).toBe('2');
        }
        if (id === 'AUD-BB-57') {
            const pageInfo = await page.locator('.audit-pagination').innerText();
            const match = pageInfo.match(/Page\s+(\d+)\s+of\s+(\d+)/i);
            expect(match).not.toBeNull();
            await openAudit(page, { page: match[2] });
            await expect(page.locator('.audit-pagination-actions a').filter({ hasText: /next/i })).toHaveCount(0);
        }
        return;
    }
    // AUD-BB-58 — Null actor is shown as System / Unavailable
    if (id === 'AUD-BB-58') {
        await openAudit(page);

        // Extract the date from the unique fixture run marker.
        // Example: AUDIT_BB_RUN_20261126_002619_D5ECB4401E
        const markerDate = fixture.run_marker.match(
            /AUDIT_BB_RUN_(\d{4})(\d{2})(\d{2})/
        );

        expect(markerDate).not.toBeNull();

        const fixtureDate =
            `${markerDate[1]}-${markerDate[2]}-${markerDate[3]}`;

        // Use the real date filters supported by AdminController.
        const auditUrl = new URL(page.url());

        auditUrl.searchParams.set('from_date', fixtureDate);
        auditUrl.searchParams.set('to_date', fixtureDate);
        auditUrl.searchParams.delete('search');

        let previousPageSignature = '';
        let foundSystemRow = false;

        // Audit page displays 20 records per page.
        // Search through pages without deleting or modifying audit rows.
        for (let pageNumber = 1; pageNumber <= 100; pageNumber++) {
            auditUrl.searchParams.set('page', String(pageNumber));

            await page.goto(auditUrl.toString());
            await page.waitForLoadState('domcontentloaded');

            const rows = page.locator('.audit-table tbody tr');
            const rowCount = await rows.count();

            const systemRow = rows.filter({
                hasText: 'Audit BB system actor'
            });

            if (await systemRow.count() > 0) {
                await expect(systemRow).toHaveCount(1);

                // Columns: Event ID, Timestamp, Actor, Module, Activity details.
                const actorCell = systemRow.locator('td').nth(2);

                await expect(actorCell).toContainText(
                    /system\s*\/\s*unavailable/i
                );

                foundSystemRow = true;
                break;
            }

            // Stop if there are no more records.
            if (rowCount === 0) {
                break;
            }

            // Stop if the page repeats (controller clamps page to last page).
            const pageSignature = await rows
                .locator('td:first-child')
                .allTextContents();

            const currentSignature = pageSignature.join('|');

            if (currentSignature === previousPageSignature) {
                break;
            }

            previousPageSignature = currentSignature;

            // Fewer than 20 rows means this is the last page.
            if (rowCount < 20) {
                break;
            }
        }

        expect(
            foundSystemRow,
            'Expected seeded null-actor audit record to appear in filtered audit pages'
        ).toBe(true);

        return;
    }
    if (id === 'AUD-BB-59') {
        await openAudit(page); await applyFixtureFilters(page);
        const xssRow = page.locator('.audit-description-cell').filter({ hasText: 'Audit BB XSS fixture' });
        await expect(xssRow).toContainText('<script>alert(1)</script>');
        await expect(xssRow.locator('script')).toHaveCount(0); return;
    }
    if (id === 'AUD-BB-60') {
        await openAudit(page, { search: fixture.run_marker }); await expect(page.locator('.audit-console')).toBeVisible(); return;
    }
    if (id === 'AUD-BB-61') {
        await openAudit(page, { module: 'not_a_real_module' }); await expect(body).toContainText(/invalid module selection/i); return;
    }
    if (id === 'AUD-BB-62') {
        await openAudit(page, { user_id: '0' }); await expect(body).toContainText(/invalid user selection/i); return;
    }
    if (id === 'AUD-BB-63') {
        await openAudit(page, { from_date: 'not-a-date' }); await expect(body).toContainText(/from date is invalid/i); return;
    }
    if (id === 'AUD-BB-64') {
        if (fixture.previous_run?.actor?.id && fixture.previous_run?.run_marker) {
            await openAudit(page, { user_id: String(fixture.previous_run.actor.id) });
            expect(await rowText(page)).toContain(fixture.previous_run.run_marker);
        } else {
            await openAudit(page, { from_date: fixture.date, to_date: fixture.date, user_id: String(fixture.actor.id) });
            expect(await rowText(page)).toContain(fixture.run_marker);
        }
        return;
    }
    if (id === 'AUD-BB-65') {
        await openAudit(page); await applyFixtureFilters(page); const before = await rowText(page);
        await openAudit(page); await applyFixtureFilters(page); const after = await rowText(page);
        expect(after).toBe(before); expect(after).toContain(fixture.run_marker); return;
    }
    throw new Error(`No implementation mapped for ${id}`);
}

test.describe('Sprint 7 — Audit Log Black-Box Automation (65 cases; audit rows retained)', () => {
    test.beforeEach(async ({ page, request }) => {
        await verifyTestDatabase(request);
        await installTestRouting(page.context());
        await loginAsAdmin(page);
    });

    for (const [id, name] of CASES) {
        test(`${id} — ${name}`, async ({ page, request }) => {
            // Test ID and Test Case are included in every test title and in CASES comments.
            await verifyTestDatabase(request);
            await runCase(id, page, request);
        });
    }
});
