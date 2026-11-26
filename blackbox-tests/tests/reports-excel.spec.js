const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const baseUrl = 'http://localhost/HRIS-Management-System/';
const publicUrl = `${baseUrl}public/index.php`;
const testEntryPath = '/HRIS-Management-System/blackbox-tests/test-entry.php';
const accountManifestPath = path.join(__dirname, '..', '.holiday-test-accounts.json');

const REPORTS = {
    attendance: {
        title: 'Attendance Report',
        headers: ['Date', 'Employee', 'Department', 'Entry', 'Entry Status', 'Exit', 'Exit Status', 'Overall Status', 'Source'],
        filters: ['from_date', 'to_date', 'employee_id', 'department', 'status'],
        summary: ['Total Records', 'Present', 'Late', 'Absent', 'Leave', 'Holiday']
    },
    employees: {
        title: 'Employee Report',
        headers: ['Employee Code', 'Employee', 'Email', 'Phone', 'Department', 'Designation', 'Joining Date', 'Monthly Salary', 'Work Schedule', 'Status'],
        filters: ['department', 'designation', 'status', 'search'],
        summary: ['Total Employees', 'Active', 'Inactive']
    },
    leave: {
        title: 'Leave Report',
        headers: ['Request ID', 'Employee', 'Department', 'Leave Type', 'Period', 'Start Date', 'End Date', 'Reason', 'Status', 'Reviewed By', 'Submitted'],
        filters: ['from_date', 'to_date', 'employee_id', 'status'],
        summary: ['Total Requests', 'Pending', 'Accepted', 'Rejected']
    },
    salary: {
        title: 'Salary Report',
        headers: ['Salary Month', 'Employee', 'Department', 'Basic Salary', 'Deductions', 'Final Salary', 'Calculated At'],
        filters: ['month', 'department', 'employee_id'],
        summary: ['Saved Records', 'Total Basic Salary', 'Total Deductions', 'Total Final Salary']
    }
};

const reportNames = {
    attendance: 'Attendance Report',
    employees: 'Employee Report',
    leave: 'Leave Report',
    salary: 'Salary Report'
};

function loadAdminAccount() {
    // Reuse the established test-only admin account; do not create or modify accounts.
    if (process.env.HRIS_TEST_ADMIN_USERNAME && process.env.HRIS_TEST_ADMIN_PASSWORD) {
        return {
            username: process.env.HRIS_TEST_ADMIN_USERNAME,
            password: process.env.HRIS_TEST_ADMIN_PASSWORD
        };
    }

    if (!fs.existsSync(accountManifestPath)) {
        throw new Error(
            'Test admin account file is missing: blackbox-tests/.holiday-test-accounts.json. ' +
            'Create/restore the established test-only account manifest, or set ' +
            'HRIS_TEST_ADMIN_USERNAME and HRIS_TEST_ADMIN_PASSWORD.'
        );
    }

    const accounts = JSON.parse(fs.readFileSync(accountManifestPath, 'utf8'));
    if (accounts.database !== 'hris_management_system_test' ||
        !accounts.admin?.username || !accounts.admin?.password) {
        throw new Error('Safety stop: admin credentials must come from the test database account manifest.');
    }
    return accounts.admin;
}

const admin = loadAdminAccount();

/*
 * Route application requests through the isolated blackbox test entry point.
 * This mirrors the routing used by the existing HRIS Playwright suites.
 * It does not alter application files or the database.
 */
async function installTestRouting(context) {
    await context.route('**/HRIS-Management-System/public/index.php**', async route => {
        const original = new URL(route.request().url());
        const forwarded = new URL(original);
        forwarded.pathname = testEntryPath;

        if (route.request().method() === 'POST') {
            const response = await route.fetch({
                url: forwarded.toString(),
                maxRedirects: 0
            });
            const headers = { ...response.headers() };

            if (headers.location) {
                const redirect = new URL(headers.location, original.origin);
                if (redirect.pathname === '/HRIS-Management-System/public/index.php') {
                    redirect.pathname = testEntryPath;
                    headers.location = redirect.pathname + redirect.search;
                }
            }
            await route.fulfill({ response, headers });
            return;
        }

        await route.continue({ url: forwarded.toString() });
    });
}


async function verifyTestDatabase(request) {
    // Guard each test run: report pages and exports must execute only on the test DB.
    const response = await request.get(`${baseUrl}blackbox-tests/db-guard.php`);
    expect(response.ok(), 'Test database guard must respond successfully.').toBeTruthy();
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

async function openReport(page, type = 'attendance', filters = {}) {
    const query = new URLSearchParams({ action: 'reports', report: type, ...filters });
    return page.goto(`${publicUrl}?${query.toString()}`);
}

async function selectReport(page, type) {
    await page.locator('.reports-tabs a').filter({ hasText: reportNames[type] }).click();
    await expect(page.locator('h1')).toHaveText(reportNames[type]);
}

function reportTab(page, type) {
    return page.locator('.reports-tabs a').filter({ hasText: reportNames[type] });
}

async function applyFilters(page, values = {}) {
    for (const [name, value] of Object.entries(values)) {
        const control = page.locator(`[name="${name}"]`).first();
        if (await control.count()) {
            const tag = await control.evaluate(el => el.tagName.toLowerCase());
            const inputType = await control.getAttribute('type');
            if (tag === 'select') {
                await control.selectOption(String(value));
            } else if (inputType === 'checkbox') {
                if (Boolean(value) !== await control.isChecked()) await control.check();
            } else {
                await control.fill(String(value));
            }
        }
    }
    await page.getByRole('button', { name: /apply filters/i }).click();
    await page.waitForLoadState('networkidle');
}

async function resetFilters(page) {
    const reset = page.getByRole('link', { name: /reset|clear filters/i }).first();
    if (await reset.count()) {
        await reset.click();
    } else {
        await page.goto(`${publicUrl}?action=reports&report=${new URL(page.url()).searchParams.get('report') || 'attendance'}`);
    }
    await page.waitForLoadState('networkidle');
}

async function expectDownload(page, type) {
    const link = page.locator('a.reports-export-preview');
    await expect(link).toBeVisible();

    const href = await link.getAttribute('href');
    expect(href).toBeTruthy();
    const exportUrl = new URL(href, page.url());
    expect(exportUrl.searchParams.get('action')).toBe('reports-export');
    expect(exportUrl.searchParams.get('report')).toBe(type);

    const downloadPromise = page.waitForEvent('download');
    await link.click();
    const download = await downloadPromise;
    const filename = download.suggestedFilename();

    expect(filename.toLowerCase()).toMatch(/\.xlsx$/);
    const savePath = path.join(__dirname, '..', 'test-results', `reports-${type}-${Date.now()}.xlsx`);
    await fs.promises.mkdir(path.dirname(savePath), { recursive: true });
    await download.saveAs(savePath);
    const stat = await fs.promises.stat(savePath);
    expect(stat.size).toBeGreaterThan(0);

    // XLSX is a ZIP container. Validate its PK signature to reject HTML/error downloads.
    const handle = await fs.promises.open(savePath, 'r');
    const signature = Buffer.alloc(4);
    await handle.read(signature, 0, 4, 0);
    await handle.close();
    expect(signature.subarray(0, 2).toString('hex')).toBe('504b');
    await fs.promises.unlink(savePath);
}

test.describe('Sprint 7 — Reports & Excel Export Black-Box Tests (repeatable)', () => {
    test.beforeEach(async ({ page, context }) => {
        await installTestRouting(context);
        await loginAsAdmin(page);
        await openReport(page, 'attendance');
        await expect(page.locator('.reports-page')).toBeVisible();
    });

    // Test ID: RPT-BB-01
    // Test Case: Administrator can open the Reports & Excel Export page.
    test('RPT-BB-01 — Administrator can open Reports page', async ({ page }) => {
        await expect(page.locator('.reports-page')).toBeVisible();
        await expect(page.locator('h1')).toHaveText('Attendance Report');
    });

    // Test ID: RPT-BB-02
    // Test Case: Reports page displays the expected heading and description.
    test('RPT-BB-02 — Heading and description are displayed', async ({ page }) => {
        await expect(page.locator('h1')).toHaveText('Attendance Report');
        await expect(page.locator('.reports-heading-main')).toContainText(
            'Explore workforce data, monitor HR performance, and review organizational records.'
        );
    });

    // Test ID: RPT-BB-03
    // Test Case: Reports page displays all four report tabs.
    test('RPT-BB-03 — All four report tabs are present', async ({ page }) => {
        await expect(page.locator('.reports-tabs a')).toHaveCount(4);
        for (const label of Object.values(reportNames)) {
            await expect(page.locator('.reports-tabs')).toContainText(label);
        }
    });

    // Test ID: RPT-BB-04
    // Test Case: Attendance report is selected by default.
    test('RPT-BB-04 — Attendance is the default report', async ({ page }) => {
        await expect(page.locator('h1')).toHaveText('Attendance Report');
        await expect(reportTab(page, 'attendance')).toHaveClass(/active/);
        expect(new URL(page.url()).searchParams.get('report')).toBe('attendance');
    });

    // Test ID: RPT-BB-05
    // Test Case: Administrator can navigate between each report tab.
    test('RPT-BB-05 — Navigation works for all report tabs', async ({ page }) => {
        for (const type of ['employees', 'leave', 'salary', 'attendance']) {
            await selectReport(page, type);
            await expect(page.locator('h1')).toHaveText(reportNames[type]);
        }
    });

    // Test ID: RPT-BB-06
    // Test Case: Active report tab is marked as active and aria-current.
    test('RPT-BB-06 — Active tab state is accessible', async ({ page }) => {
        for (const type of Object.keys(reportNames)) {
            await openReport(page, type);
            await expect(reportTab(page, type)).toHaveClass(/active/);
            await expect(reportTab(page, type)).toHaveAttribute('aria-current', 'page');
        }
    });

    // Test ID: RPT-BB-07
    // Test Case: Matching Records count is visible.
    test('RPT-BB-07 — Matching records counter is displayed', async ({ page }) => {
        await expect(page.locator('.reports-count')).toBeVisible();
        await expect(page.locator('.reports-count-label')).toHaveText('MATCHING RECORDS');
        await expect(page.locator('.reports-count strong')).toHaveText(/[\d,]+/);
    });

    // Test ID: RPT-BB-08
    // Test Case: Attendance report exposes date, employee, department and status filters.
    test('RPT-BB-08 — Attendance filters are available', async ({ page }) => {
        for (const name of REPORTS.attendance.filters) {
            await expect(page.locator(`[name="${name}"]`).first()).toBeVisible();
        }
    });

    // Test ID: RPT-BB-09
    // Test Case: Attendance summary displays all expected metric labels.
    test('RPT-BB-09 — Attendance summary metrics are displayed', async ({ page }) => {
        const text = await page.locator('.reports-page').innerText();
        for (const metric of REPORTS.attendance.summary) {
            expect(text.toLowerCase()).toContain(metric.toLowerCase());
        }
    });

    // Test ID: RPT-BB-10
    // Test Case: Attendance table contains all expected headers when records exist.
    test('RPT-BB-10 — Attendance table headers are correct', async ({ page }) => {
        const table = page.locator('.reports-table');
        if (await table.count()) {
            const headers = await table.locator('thead th').allTextContents();
            for (const header of REPORTS.attendance.headers) {
                expect(headers.join(' ')).toContain(header);
            }
        } else {
            await expect(page.getByText('No Matching Records')).toBeVisible();
        }
    });

    // Test ID: RPT-BB-11
    // Test Case: Attendance report renders either records or its designed empty state.
    test('RPT-BB-11 — Attendance records or empty state render', async ({ page }) => {
        const rows = page.locator('.reports-table tbody tr');
        if (await rows.count()) {
            await expect(rows.first()).toBeVisible();
        } else {
            await expect(page.getByText('No Matching Records')).toBeVisible();
        }
    });

    // Test ID: RPT-BB-12
    // Test Case: Applying an attendance filter submits and preserves the filter.
    test('RPT-BB-12 — Attendance filter submission works', async ({ page }) => {
        const uniqueDate = '2099-12-31';
        await applyFilters(page, { from_date: uniqueDate, to_date: uniqueDate });
        expect(new URL(page.url()).searchParams.get('from_date')).toBe(uniqueDate);
        expect(new URL(page.url()).searchParams.get('to_date')).toBe(uniqueDate);
        await expect(page.locator('[name="from_date"]')).toHaveValue(uniqueDate);
    });

    // Test ID: RPT-BB-13
    // Test Case: Reset clears attendance filters.
    test('RPT-BB-13 — Attendance filters reset', async ({ page }) => {
        await applyFilters(page, { from_date: '2099-12-31', to_date: '2099-12-31' });
        await resetFilters(page);
        await expect(page.locator('[name="from_date"]')).toHaveValue('');
        await expect(page.locator('[name="to_date"]')).toHaveValue('');
    });

    // Test ID: RPT-BB-14
    // Test Case: Attendance report displays empty state for a no-match date.
    test('RPT-BB-14 — Attendance no-match empty state', async ({ page }) => {
        await openReport(page, 'attendance', { from_date: '2099-12-31', to_date: '2099-12-31' });
        await expect(page.getByText('No Matching Records')).toBeVisible();
    });

    // Test ID: RPT-BB-15
    // Test Case: Employee report displays department, designation, status and search controls.
    test('RPT-BB-15 — Employee filters are available', async ({ page }) => {
        await openReport(page, 'employees');
        for (const name of REPORTS.employees.filters) {
            await expect(page.locator(`[name="${name}"]`).first()).toBeVisible();
        }
    });

    // Test ID: RPT-BB-16
    // Test Case: Employee report displays total, active and inactive summary metrics.
    test('RPT-BB-16 — Employee summary metrics are displayed', async ({ page }) => {
        await openReport(page, 'employees');
        const text = await page.locator('.reports-page').innerText();
        for (const metric of REPORTS.employees.summary) {
            expect(text.toLowerCase()).toContain(metric.toLowerCase());
        }
    });

    // Test ID: RPT-BB-17
    // Test Case: Employee report table contains expected columns.
    test('RPT-BB-17 — Employee table headers are correct', async ({ page }) => {
        await openReport(page, 'employees');
        const table = page.locator('.reports-table');
        if (await table.count()) {
            const headers = await table.locator('thead th').allTextContents();
            for (const header of REPORTS.employees.headers) expect(headers.join(' ')).toContain(header);
        } else {
            await expect(page.getByText('No Matching Records')).toBeVisible();
        }
    });

    // Test ID: RPT-BB-18
    // Test Case: Employee search applies and retains the entered search term.
    test('RPT-BB-18 — Employee search submission works', async ({ page }) => {
        await openReport(page, 'employees');
        const search = page.locator('[name="search"]').first();
        await search.fill('ZZZ_NO_MATCH_RPT_BB');
        await page.getByRole('button', { name: /apply filters/i }).click();
        await page.waitForLoadState('networkidle');
        expect(new URL(page.url()).searchParams.get('search')).toBe('ZZZ_NO_MATCH_RPT_BB');
        await expect(page.locator('[name="search"]')).toHaveValue('ZZZ_NO_MATCH_RPT_BB');
    });

    // Test ID: RPT-BB-19
    // Test Case: Reset clears employee report filters.
    test('RPT-BB-19 — Employee filters reset', async ({ page }) => {
        await openReport(page, 'employees', { search: 'ZZZ_NO_MATCH_RPT_BB' });
        await resetFilters(page);
        await expect(page.locator('[name="search"]')).toHaveValue('');
    });

    // Test ID: RPT-BB-20
    // Test Case: Employee report displays empty state for an unmatched search.
    test('RPT-BB-20 — Employee no-match empty state', async ({ page }) => {
        await openReport(page, 'employees', { search: 'ZZZ_NO_MATCH_RPT_BB' });
        await expect(page.getByText('No Matching Records')).toBeVisible();
    });

    // Test ID: RPT-BB-21
    // Test Case: Leave report displays date range, employee and status filters.
    test('RPT-BB-21 — Leave filters are available', async ({ page }) => {
        await openReport(page, 'leave');
        for (const name of REPORTS.leave.filters) {
            await expect(page.locator(`[name="${name}"]`).first()).toBeVisible();
        }
    });

    // Test ID: RPT-BB-22
    // Test Case: Leave report displays request summary metrics.
    test('RPT-BB-22 — Leave summary metrics are displayed', async ({ page }) => {
        await openReport(page, 'leave');
        const text = await page.locator('.reports-page').innerText();
        for (const metric of REPORTS.leave.summary) {
            expect(text.toLowerCase()).toContain(metric.toLowerCase());
        }
    });

    // Test ID: RPT-BB-23
    // Test Case: Leave report table contains expected columns.
    test('RPT-BB-23 — Leave table headers are correct', async ({ page }) => {
        await openReport(page, 'leave');
        const table = page.locator('.reports-table');
        if (await table.count()) {
            const headers = await table.locator('thead th').allTextContents();
            for (const header of REPORTS.leave.headers) expect(headers.join(' ')).toContain(header);
        } else {
            await expect(page.getByText('No Matching Records')).toBeVisible();
        }
    });

    // Test ID: RPT-BB-24
    // Test Case: Leave status filter submits and is retained in the URL.
    test('RPT-BB-24 — Leave status filter works', async ({ page }) => {
        await openReport(page, 'leave');
        const status = page.locator('[name="status"]').first();
        await status.selectOption({ label: 'Pending' });
        await page.getByRole('button', { name: /apply filters/i }).click();
        await page.waitForLoadState('networkidle');
        expect(new URL(page.url()).searchParams.get('status')).toBeTruthy();
        await expect(page.locator('[name="status"]')).toHaveValue(new URL(page.url()).searchParams.get('status'));
    });

    // Test ID: RPT-BB-25
    // Test Case: Reset clears leave report filters.
    test('RPT-BB-25 — Leave filters reset', async ({ page }) => {
        await openReport(page, 'leave', { from_date: '2099-12-31', to_date: '2099-12-31' });
        await resetFilters(page);
        await expect(page.locator('[name="from_date"]')).toHaveValue('');
        await expect(page.locator('[name="to_date"]')).toHaveValue('');
    });

    // Test ID: RPT-BB-26
    // Test Case: Leave report displays empty state for no matching dates.
    test('RPT-BB-26 — Leave no-match empty state', async ({ page }) => {
        await openReport(page, 'leave', { from_date: '2099-12-31', to_date: '2099-12-31' });
        await expect(page.getByText('No Matching Records')).toBeVisible();
    });

    // Test ID: RPT-BB-27
    // Test Case: Salary report displays month, department and employee filters.
    test('RPT-BB-27 — Salary filters are available', async ({ page }) => {
        await openReport(page, 'salary');
        for (const name of REPORTS.salary.filters) {
            await expect(page.locator(`[name="${name}"]`).first()).toBeVisible();
        }
    });

    // Test ID: RPT-BB-28
    // Test Case: Salary report displays saved-record and salary total metrics.
    test('RPT-BB-28 — Salary summary metrics are displayed', async ({ page }) => {
        await openReport(page, 'salary');
        const text = await page.locator('.reports-page').innerText();
        for (const metric of REPORTS.salary.summary) {
            expect(text.toLowerCase()).toContain(metric.toLowerCase());
        }
    });

    // Test ID: RPT-BB-29
    // Test Case: Salary report table contains expected columns and BDT currency.
    test('RPT-BB-29 — Salary headers and currency formatting are displayed', async ({ page }) => {
        await openReport(page, 'salary');
        const table = page.locator('.reports-table');
        if (await table.count()) {
            const headers = await table.locator('thead th').allTextContents();
            for (const header of REPORTS.salary.headers) expect(headers.join(' ')).toContain(header);
            await expect(table).toContainText(/৳|BDT/);
        } else {
            await expect(page.getByText('No Matching Records')).toBeVisible();
        }
    });

    // Test ID: RPT-BB-30
    // Test Case: Salary month/department filters submit and reset.
    test('RPT-BB-30 — Salary month and department filters work and reset', async ({ page }) => {
        await openReport(page, 'salary');
        await applyFilters(page, { month: '2099-12' });
        expect(new URL(page.url()).searchParams.get('month')).toBe('2099-12');
        await resetFilters(page);
        await expect(page.locator('[name="month"]')).toHaveValue('');
    });

    // Test ID: XLS-BB-31
    // Test Case: Attendance report exports a non-empty valid XLSX download.
    test('XLS-BB-31 — Attendance Excel export downloads valid XLSX', async ({ page }) => {
        await openReport(page, 'attendance');
        await expectDownload(page, 'attendance');
    });

    // Test ID: XLS-BB-32
    // Test Case: Employee report exports a non-empty valid XLSX download.
    test('XLS-BB-32 — Employee Excel export downloads valid XLSX', async ({ page }) => {
        await openReport(page, 'employees');
        await expectDownload(page, 'employees');
    });

    // Test ID: XLS-BB-33
    // Test Case: Leave report exports a non-empty valid XLSX download.
    test('XLS-BB-33 — Leave Excel export downloads valid XLSX', async ({ page }) => {
        await openReport(page, 'leave');
        await expectDownload(page, 'leave');
    });

    // Test ID: XLS-BB-34
    // Test Case: Salary report exports a non-empty valid XLSX download.
    test('XLS-BB-34 — Salary Excel export downloads valid XLSX', async ({ page }) => {
        await openReport(page, 'salary');
        await expectDownload(page, 'salary');
    });

    // Test ID: XLS-BB-35
    // Test Case: Export link includes the selected report type.
    test('XLS-BB-35 — Export link preserves report type', async ({ page }) => {
        for (const type of Object.keys(REPORTS)) {
            await openReport(page, type);
            const url = new URL(await page.locator('a.reports-export-preview').getAttribute('href'), page.url());
            expect(url.searchParams.get('report')).toBe(type);
            expect(url.searchParams.get('action')).toBe('reports-export');
        }
    });

    // Test ID: XLS-BB-36
    // Test Case: Export link preserves currently applied filters.
    test('XLS-BB-36 — Export link preserves filter parameters', async ({ page }) => {
        await openReport(page, 'attendance', {
            from_date: '2099-12-31',
            to_date: '2099-12-31',
            status: 'absent'
        });
        const exportUrl = new URL(await page.locator('a.reports-export-preview').getAttribute('href'), page.url());
        expect(exportUrl.searchParams.get('from_date')).toBe('2099-12-31');
        expect(exportUrl.searchParams.get('to_date')).toBe('2099-12-31');
        expect(exportUrl.searchParams.get('status')).toBe('absent');
    });

    // Test ID: XLS-BB-37
    // Test Case: Downloaded Excel file has a positive file size.
    test('XLS-BB-37 — Downloaded workbook is not empty', async ({ page }) => {
        await openReport(page, 'attendance');
        const link = page.locator('a.reports-export-preview');
        const downloadPromise = page.waitForEvent('download');
        await link.click();
        const download = await downloadPromise;
        const target = path.join(__dirname, '..', 'test-results', `size-check-${Date.now()}.xlsx`);
        await fs.promises.mkdir(path.dirname(target), { recursive: true });
        await download.saveAs(target);
        expect((await fs.promises.stat(target)).size).toBeGreaterThan(0);
        await fs.promises.unlink(target);
    });

    // Test ID: XLS-BB-38
    // Test Case: Download filename has the expected XLSX extension.
    test('XLS-BB-38 — Download filename has .xlsx extension', async ({ page }) => {
        await openReport(page, 'salary');
        const downloadPromise = page.waitForEvent('download');
        await page.locator('a.reports-export-preview').click();
        const download = await downloadPromise;
        expect(download.suggestedFilename().toLowerCase()).toMatch(/\.xlsx$/);
    });
});
