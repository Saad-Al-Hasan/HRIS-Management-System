
const { test, expect } = require('@playwright/test');

const BASE_URL = 'http://localhost/HRIS-Management-System/public/';

const EMPLOYEE_1 = {
    username: process.env.HRIS_EMPLOYEE_USERNAME,
    password: process.env.HRIS_EMPLOYEE_PASSWORD
};

const EMPLOYEE_2 = {
    username: process.env.HRIS_EMPLOYEE2_USERNAME,
    password: process.env.HRIS_EMPLOYEE2_PASSWORD
};

const DASHBOARD_URL = `${BASE_URL}index.php?action=employee-dashboard`;

// -----------------------------------------------------------------------------
// Credential helper
// -----------------------------------------------------------------------------

function requireCredentials(account, accountName) {
    if (!account.username || !account.password) {
        throw new Error(
            `Missing ${accountName} credentials. Set the required environment variables before running the tests.`
        );
    }
}

// -----------------------------------------------------------------------------
// Employee login helper
// -----------------------------------------------------------------------------

async function loginAsEmployee(page, account) {
    await page.goto(BASE_URL);

    await page.locator('input[name="username"]').fill(account.username);
    await page.locator('input[name="password"]').fill(account.password);

    await Promise.all([
        page.waitForURL(
            url => !url.href.includes('action=login'),
            { timeout: 15000 }
        ).catch(() => { }),
        page.locator(
            'button[type="submit"], input[type="submit"]'
        ).first().click()
    ]);

    await page.goto(DASHBOARD_URL);

    await expect(
        page.locator('.employee-attendance-filter')
    ).toBeVisible();

    await expect(
        page.getByRole('heading', {
            name: 'Attendance History'
        })
    ).toBeVisible();
}

// -----------------------------------------------------------------------------
// Date filter helper
// -----------------------------------------------------------------------------

async function applyDateFilter(page, fromDate, toDate) {
    await page.locator('#attendance_from').fill(fromDate);
    await page.locator('#attendance_to').fill(toDate);

    await Promise.all([
        page.waitForLoadState('domcontentloaded'),
        page.locator('.employee-attendance-view-button').click()
    ]);
}

// -----------------------------------------------------------------------------
// Attendance date helpers
// -----------------------------------------------------------------------------

function getDisplayedDates(page) {
    return page.locator(
        '.employee-attendance-table .employee-attendance-date'
    ).allTextContents();
}

function getDisplayedAttendanceRows(page) {
    return page.locator(
        '.employee-attendance-table tbody tr:has(.employee-attendance-date)'
    ).allInnerTexts();
}

// Convert the application's displayed date format, for example
// "10 Nov 2026", into an unambiguous YYYY-MM-DD string.
function parseDisplayedDateToISO(displayedDate) {
    const monthNumbers = {
        Jan: '01',
        Feb: '02',
        Mar: '03',
        Apr: '04',
        May: '05',
        Jun: '06',
        Jul: '07',
        Aug: '08',
        Sep: '09',
        Oct: '10',
        Nov: '11',
        Dec: '12'
    };

    const parts = displayedDate.trim().split(/\s+/);

    if (parts.length !== 3) {
        throw new Error(
            `Unexpected attendance date format: "${displayedDate}"`
        );
    }

    const day = Number(parts[0]);
    const month = monthNumbers[parts[1]];
    const year = Number(parts[2]);

    if (
        !Number.isInteger(day) ||
        !month ||
        !Number.isInteger(year)
    ) {
        throw new Error(
            `Invalid attendance date: "${displayedDate}"`
        );
    }

    const isoDate =
        `${year}-${month}-${String(day).padStart(2, '0')}`;

    // Validate that the date is a real calendar date.
    const parsed = new Date(`${isoDate}T00:00:00Z`);

    if (
        Number.isNaN(parsed.getTime()) ||
        parsed.toISOString().slice(0, 10) !== isoDate
    ) {
        throw new Error(
            `Invalid calendar date: "${displayedDate}"`
        );
    }

    return isoDate;
}

function getLocalDateISO(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

// -----------------------------------------------------------------------------
// Test suite
// -----------------------------------------------------------------------------

test.describe('Feature Group 3 - Employee Attendance History', () => {

    test.beforeEach(async ({ page }) => {
        requireCredentials(EMPLOYEE_1, 'EMPLOYEE 1');
        await loginAsEmployee(page, EMPLOYEE_1);
    });

    // -------------------------------------------------------------------------
    // EMP-H01: Attendance history section loads.
    // -------------------------------------------------------------------------

    test('EMP-H01 - Attendance history section loads', async ({ page }) => {

        await expect(
            page.getByRole('heading', {
                name: 'Attendance History'
            })
        ).toBeVisible();

        await expect(
            page.locator('.employee-attendance-filter')
        ).toBeVisible();

        await expect(
            page.locator('.employee-attendance-table')
        ).toBeVisible();
    });

    // -------------------------------------------------------------------------
    // EMP-H02: Default attendance history uses current month through today.
    // -------------------------------------------------------------------------

    test('EMP-H02 - Default attendance history uses current month through today', async ({ page }) => {

        const today = new Date();

        const firstDayOfMonth = new Date(
            today.getFullYear(),
            today.getMonth(),
            1
        );

        const firstDayISO = getLocalDateISO(firstDayOfMonth);
        const todayISO = getLocalDateISO(today);

        // Initial page load should not contain custom date values.
        await expect(
            page.locator('#attendance_from')
        ).toHaveValue('');

        await expect(
            page.locator('#attendance_to')
        ).toHaveValue('');

        // Verify that no custom date parameters were submitted.
        const currentUrl = new URL(page.url());

        expect(
            currentUrl.searchParams.get('action')
        ).toBe('employee-dashboard');

        expect(
            currentUrl.searchParams.has('attendance_from')
        ).toBe(false);

        expect(
            currentUrl.searchParams.has('attendance_to')
        ).toBe(false);

        // Every displayed attendance record must fall within
        // the current month's first day through today.
        const dates = await getDisplayedDates(page);

        for (const displayedDate of dates) {
            if (displayedDate.trim() === '') {
                continue;
            }

            const attendanceDate =
                parseDisplayedDateToISO(displayedDate);

            expect(attendanceDate >= firstDayISO).toBe(true);
            expect(attendanceDate <= todayISO).toBe(true);
        }
    });

    // -------------------------------------------------------------------------
    // EMP-H03: Custom date range filters attendance records.
    // -------------------------------------------------------------------------

    test('EMP-H03 - Custom date range filters attendance records', async ({ page }) => {

        await applyDateFilter(
            page,
            '2026-01-01',
            '2026-12-31'
        );

        await expect(page).toHaveURL(
            /attendance_from=2026-01-01/
        );

        await expect(page).toHaveURL(
            /attendance_to=2026-12-31/
        );

        const dates = await getDisplayedDates(page);

        for (const displayedDate of dates) {
            if (displayedDate.trim() === '') {
                continue;
            }

            const attendanceDate =
                parseDisplayedDateToISO(displayedDate);

            expect(attendanceDate >= '2026-01-01').toBe(true);
            expect(attendanceDate <= '2026-12-31').toBe(true);
        }
    });

    // -------------------------------------------------------------------------
    // EMP-H04: Invalid calendar date is rejected.
    // -------------------------------------------------------------------------

    test('EMP-H04 - Invalid calendar date displays validation message', async ({ page }) => {

        await page.goto(
            `${DASHBOARD_URL}&attendance_from=2026-02-30&attendance_to=2026-03-01`
        );

        await expect(
            page.locator(
                '.employee-attendance-message.employee-attendance-error'
            )
        ).toContainText(
            'Please enter valid From Date and To Date values.'
        );
    });

    // -------------------------------------------------------------------------
    // EMP-H05: Reversed date range is rejected.
    // -------------------------------------------------------------------------

    test('EMP-H05 - Reversed date range displays validation message', async ({ page }) => {

        await page.goto(
            `${DASHBOARD_URL}&attendance_from=2026-12-31&attendance_to=2026-01-01`
        );

        await expect(
            page.locator(
                '.employee-attendance-message.employee-attendance-error'
            )
        ).toContainText(
            'From Date cannot be later than To Date.'
        );
    });

    // -------------------------------------------------------------------------
    // EMP-H06: Employee can expand RFID scan history.
    // -------------------------------------------------------------------------

    test('EMP-H06 - RFID scan history can be expanded', async ({ page }) => {

        const toggle = page.locator(
            '.employee-attendance-log-toggle'
        ).first();

        const toggleCount = await page.locator(
            '.employee-attendance-log-toggle'
        ).count();

        test.skip(
            toggleCount === 0,
            'No attendance records are available for this employee in the default date range.'
        );

        const controlledId = await toggle.getAttribute(
            'aria-controls'
        );

        expect(controlledId).toBeTruthy();

        const historyRow = page.locator(`#${controlledId}`);

        await expect(toggle).toHaveAttribute(
            'aria-expanded',
            'false'
        );

        await expect(historyRow).toBeHidden();

        await toggle.click();

        await expect(toggle).toHaveAttribute(
            'aria-expanded',
            'true'
        );

        await expect(historyRow).toBeVisible();

        await expect(
            historyRow.locator('.employee-scan-history-panel')
        ).toBeVisible();

        const scanItems = historyRow.locator(
            '.employee-scan-history-item'
        );

        const emptyMessage = historyRow.getByText(
            'No RFID scan history is available for this date.'
        );

        expect(
            (await scanItems.count()) > 0 ||
            await emptyMessage.isVisible()
        ).toBe(true);
    });

    // -------------------------------------------------------------------------
    // EMP-H07: Attendance history must be isolated to the authenticated employee.
    // -------------------------------------------------------------------------

    test('EMP-H07 - Employee cannot view another employee attendance history', async ({ page }) => {

        requireCredentials(EMPLOYEE_2, 'EMPLOYEE 2');

        // Employee 1 is already authenticated by beforeEach.
        // Record the actual attendance rows shown to Employee 1.
        const firstEmployeeRows =
            await getDisplayedAttendanceRows(page);

        // Clear the current session and authenticate as Employee 2.
        await page.context().clearCookies();

        await loginAsEmployee(page, EMPLOYEE_2);

        // Capture Employee 2's own attendance records.
        const secondEmployeeRows =
            await getDisplayedAttendanceRows(page);

        // Attempt to manipulate the URL by supplying an employee ID.
        // The authenticated employee's attendance should remain unchanged.
        await page.goto(
            `${DASHBOARD_URL}&employee_id=1`
        );

        await expect(
            page.locator('.employee-attendance-filter')
        ).toBeVisible();

        await expect(
            page.locator('.employee-attendance-table')
        ).toBeVisible();

        const rowsAfterTampering =
            await getDisplayedAttendanceRows(page);

        // Confirm that the original account had a readable result.
        expect(firstEmployeeRows).toBeDefined();

        // The manipulated URL must not change Employee 2's attendance.
        expect(rowsAfterTampering).toEqual(secondEmployeeRows);

        await expect(page.locator('body')).not.toContainText(
            /access denied/i
        );
    });

    // -------------------------------------------------------------------------
    // EMP-H08: Unauthenticated users cannot access attendance history.
    // -------------------------------------------------------------------------

    test('EMP-H08 - Unauthenticated access redirects to login', async ({ page }) => {

        await page.context().clearCookies();

        await page.goto(DASHBOARD_URL);

        await expect(page).not.toHaveURL(
            /action=employee-dashboard/
        );
    });

});
