const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const blackboxDir = path.resolve(__dirname, '..');
const manifestPath = path.join(blackboxDir, '.leave-test-data.json');
const accountManifestPath = path.join(blackboxDir, '.holiday-test-accounts.json');
const managerScript = path.join(blackboxDir, 'cleanup-leave-requests.php');
const BASE = 'http://localhost/HRIS-Management-System/';
const GUARD_URL = BASE + 'blackbox-tests/db-guard.php';

function readManifest() {
    if (!fs.existsSync(manifestPath)) {
        throw new Error('Leave test data is missing. Run from blackbox-tests: php cleanup-leave-requests.php prepare');
    }
    const data = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
    if (data.database !== 'hris_management_system_test') {
        throw new Error('SAFETY STOP: Leave test manifest does not identify hris_management_system_test.');
    }
    if (!data.admin || !data.employee || !data.request_ids) {
        throw new Error('Leave test manifest is incomplete.');
    }
    return data;
}

function runPhp(mode) {
    const result = spawnSync('php', [managerScript, mode], {
        cwd: blackboxDir,
        encoding: 'utf8',
        windowsHide: true
    });
    if (result.status !== 0) {
        throw new Error(
            `PHP ${mode} failed.\nSTDOUT:\n${result.stdout}\nSTDERR:\n${result.stderr}`
        );
    }
    if (result.stdout) console.log(result.stdout.trim());
}

async function installTestRouting(context) {
    await context.route('**/HRIS-Management-System/public/index.php**', async route => {
        const url = new URL(route.request().url());
        url.pathname = '/HRIS-Management-System/blackbox-tests/test-entry.php';
        await route.continue({ url: url.toString() });
    });
}

async function verifyTestDatabase(request) {
    const response = await request.get(GUARD_URL);
    expect(response.ok(), 'Database guard must respond successfully').toBeTruthy();
    const body = await response.json();
    expect(body.ok).toBe(true);
    expect(body.database).toBe('hris_management_system_test');
}

async function login(page, account) {
    await page.goto('public/index.php?action=login');
    await page.locator('input[name="username"]').fill(account.username);
    await page.locator('input[name="password"]').fill(account.password);
    await page.locator('button[type="submit"]').click();
    await expect(page.locator('body')).not.toContainText('Invalid username or password');
}

async function openLeaveManagement(page) {
    await page.goto('public/index.php?action=leave-management');
    await expect(page.locator('h1')).toHaveText('Leave Management');
}

async function filterToTestEmployee(page, employeeCode) {
    await page.goto('public/index.php?action=leave-management');
    await expect(page.locator('h1')).toHaveText('Leave Management');
    await page.locator('input.leave-search-input').fill(employeeCode);
    await page.getByRole('button', { name: 'Search' }).click();
    await expect(page.locator('input.leave-search-input')).toHaveValue(employeeCode);
    await expect(page.locator('table.data-table')).toContainText(employeeCode);
}

function rowFor(page, employeeCode, reason) {
    return page.locator('table.data-table tbody tr')
        .filter({ hasText: employeeCode })
        .filter({ hasText: reason });
}

async function readRequestStatus(request, requestId) {
    // Use a read-only SQL endpoint? No application endpoint exposes this.
    // The authoritative status is also asserted through the rendered status
    // label after the browser's POST/redirect cycle.
    return requestId;
}

test.describe('Leave Management — Automated Black-Box Tests', () => {
    let data;

    test.beforeAll(async () => {
        if (!fs.existsSync(accountManifestPath)) {
            throw new Error('Existing Holiday test account manifest is missing: .holiday-test-accounts.json');
        }
        const accounts = JSON.parse(fs.readFileSync(accountManifestPath, 'utf8'));
        if (accounts.database !== 'hris_management_system_test') {
            throw new Error('SAFETY STOP: Holiday test accounts are not assigned to the test database.');
        }
        runPhp('prepare');
        data = readManifest();
    });

    test.afterAll(async () => {
        // This script deletes only the employee and leave rows named in the
        // current .leave-test-data.json manifest. It never deletes the shared
        // Holiday test administrator or employee.
        runPhp('cleanup');
    });

    test.beforeEach(async ({ page, context, request }) => {
        data = readManifest();
        await verifyTestDatabase(request);
        await installTestRouting(context);
        await login(page, data.admin);
        await openLeaveManagement(page);
    });

    test('BB-LEAVE-01 — Administrator can open Leave Management', async ({ page }) => {
        await expect(page.locator('h1')).toHaveText('Leave Management');
        await expect(page.getByRole('heading', { name: 'Employee Leave Requests' })).toBeVisible();
    });

    test('BB-LEAVE-02 — Leave request table displays all expected columns', async ({ page }) => {
        await expect(page.locator('table.data-table thead th')).toHaveText([
            '#', 'Employee Code', 'Employee Name', 'Leave Date',
            'Leave Type', 'Period', 'Reason', 'Applied On', 'Status', 'Action'
        ]);
    });

    test('BB-LEAVE-03 — Pending requests display Accept and Reject actions', async ({ page }) => {
        const row = rowFor(page, data.employee.employee_code, 'Pending full-day approval test.');
        await expect(row).toBeVisible();
        await expect(row.getByRole('button', { name: 'Accept' })).toBeVisible();
        await expect(row.getByRole('button', { name: 'Reject' })).toBeVisible();
        await expect(row.locator('.status-badge')).toHaveText('Pending');
    });

    test('BB-LEAVE-04 — Already accepted requests display Accepted and Reviewed', async ({ page }) => {
        const row = rowFor(page, data.employee.employee_code, 'Already-reviewed display test.');
        await expect(row).toBeVisible();
        await expect(row.locator('.status-badge')).toHaveText('Accepted');
        await expect(row.locator('.leave-reviewed-badge')).toHaveText('Reviewed');
        await expect(row.getByRole('button', { name: 'Accept' })).toHaveCount(0);
        await expect(row.getByRole('button', { name: 'Reject' })).toHaveCount(0);
    });

    test('BB-LEAVE-05 — Rejected requests display Rejected and Reviewed', async ({ page }) => {
        const row = rowFor(page, data.employee.employee_code, 'Already-rejected display test.');
        await expect(row).toBeVisible();
        await expect(row.locator('.status-badge')).toHaveText('Rejected');
        await expect(row.locator('.leave-reviewed-badge')).toHaveText('Reviewed');
        await expect(row.getByRole('button', { name: 'Accept' })).toHaveCount(0);
        await expect(row.getByRole('button', { name: 'Reject' })).toHaveCount(0);
    });

    test('BB-LEAVE-06 — Search by valid employee code returns matching requests', async ({ page }) => {
        await page.locator('input.leave-search-input').fill(data.employee.employee_code.toLowerCase());
        await page.getByRole('button', { name: 'Search' }).click();
        await expect(page.locator('input.leave-search-input')).toHaveValue(data.employee.employee_code);
        await expect(page.locator('table.data-table')).toContainText(data.employee.employee_code);
        const rows = page.locator('table.data-table tbody tr');
        await expect(rows).toHaveCount(7);
    });

    test('BB-LEAVE-07 — Search with unmatched employee code shows empty state', async ({ page }) => {
        await page.locator('input.leave-search-input').fill('NO-MATCH-' + data.run_prefix.slice(-8));
        await page.getByRole('button', { name: 'Search' }).click();
        await expect(page.locator('td.empty-state')).toHaveText('No leave applications found.');
    });

    test('BB-LEAVE-08 — Reset clears the employee-code filter', async ({ page }) => {
        await page.locator('input.leave-search-input').fill(data.employee.employee_code);
        await page.getByRole('button', { name: 'Search' }).click();
        await expect(page.locator('input.leave-search-input')).toHaveValue(data.employee.employee_code);
        await page.locator('a.leave-reset-button').click();
        await expect(page.locator('input.leave-search-input')).toHaveValue('');
        await expect(page.locator('table.data-table')).toContainText(data.employee.employee_code);
    });

    test('BB-LEAVE-09 — Administrator accepts a Full Day leave request', async ({ page }) => {
        const row = rowFor(page, data.employee.employee_code, 'Pending full-day approval test.');
        page.once('dialog', dialog => dialog.accept());
        await row.getByRole('button', { name: 'Accept' }).click();
        await filterToTestEmployee(page, data.employee.employee_code);
        const updatedRow = rowFor(page, data.employee.employee_code, 'Pending full-day approval test.');
        await expect(updatedRow.locator('.status-badge')).toHaveText('Accepted');
        await expect(updatedRow.locator('.leave-reviewed-badge')).toHaveText('Reviewed');
    });

    test('BB-LEAVE-10 — Administrator rejects a Full Day leave request', async ({ page }) => {
        const row = rowFor(page, data.employee.employee_code, 'Pending full-day rejection test.');
        page.once('dialog', dialog => dialog.accept());
        await row.getByRole('button', { name: 'Reject' }).click();
        await filterToTestEmployee(page, data.employee.employee_code);
        const updatedRow = rowFor(page, data.employee.employee_code, 'Pending full-day rejection test.');
        await expect(updatedRow.locator('.status-badge')).toHaveText('Rejected');
        await expect(updatedRow.locator('.leave-reviewed-badge')).toHaveText('Reviewed');
    });

    test('BB-LEAVE-11 — Administrator accepts a Half Day request and preserves First Half', async ({ page }) => {
        const row = rowFor(page, data.employee.employee_code, 'Pending first-half approval test.');
        await expect(row.locator('td').nth(5)).toHaveText('First Half');
        page.once('dialog', dialog => dialog.accept());
        await row.getByRole('button', { name: 'Accept' }).click();
        await filterToTestEmployee(page, data.employee.employee_code);
        const updatedRow = rowFor(page, data.employee.employee_code, 'Pending first-half approval test.');
        await expect(updatedRow.locator('.status-badge')).toHaveText('Accepted');
        await expect(updatedRow.locator('td').nth(5)).toHaveText('First Half');
    });

    test('BB-LEAVE-12 — Administrator rejects a Half Day request and preserves Second Half', async ({ page }) => {
        const row = rowFor(page, data.employee.employee_code, 'Pending second-half rejection test.');
        await expect(row.locator('td').nth(5)).toHaveText('Second Half');
        page.once('dialog', dialog => dialog.accept());
        await row.getByRole('button', { name: 'Reject' }).click();
        await filterToTestEmployee(page, data.employee.employee_code);
        const updatedRow = rowFor(page, data.employee.employee_code, 'Pending second-half rejection test.');
        await expect(updatedRow.locator('.status-badge')).toHaveText('Rejected');
        await expect(updatedRow.locator('td').nth(5)).toHaveText('Second Half');
    });

    test('BB-LEAVE-13 — Review confirmation can be cancelled without changing the request', async ({ page }) => {
        const row = rowFor(page, data.employee.employee_code, 'Pending confirmation test.');
        page.once('dialog', dialog => dialog.dismiss());
        await row.getByRole('button', { name: 'Accept' }).click();
        await expect(row.locator('.status-badge')).toHaveText('Pending');
        await expect(row.getByRole('button', { name: 'Accept' })).toBeVisible();
    });

    test('BB-LEAVE-14 — Reviewed requests cannot be reviewed again from the page', async ({ page }) => {
        const row = rowFor(page, data.employee.employee_code, 'Already-reviewed display test.');
        await expect(row.locator('.status-badge')).toHaveText('Accepted');
        await expect(row.getByRole('button', { name: 'Accept' })).toHaveCount(0);
        await expect(row.getByRole('button', { name: 'Reject' })).toHaveCount(0);
    });

    test('BB-LEAVE-15 — Employee cannot access administrator Leave Management', async ({ page }) => {
        await page.goto('public/index.php?action=logout');
        await login(page, data.employee);
        await page.goto('public/index.php?action=leave-management');
        await expect(page.locator('body')).not.toContainText('Employee Leave Requests');
        await expect(page.locator('form[action*="leave-review"]')).toHaveCount(0);
        // The access-control middleware may deny the request while leaving
        // the requested URL unchanged (for example, by returning HTTP 403).
        // Therefore, do not require a redirect away from leave-management.
    });
});
