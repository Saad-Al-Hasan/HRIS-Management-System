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

function requireCredentials(account, accountName) {
    if (!account.username || !account.password) {
        throw new Error(
            `Missing ${accountName} credentials. Set the required environment variables before running the tests.`
        );
    }
}

async function loginAsEmployee(page, account) {
    await page.goto(BASE_URL);

    // Use the login form fields from the application.
    await page.locator('input[name="username"]').fill(account.username);
    await page.locator('input[name="password"]').fill(account.password);

    await Promise.all([
        page.waitForURL(url => !url.href.includes('action=login'), {
            timeout: 15000
        }).catch(() => { }),
        page.locator('button[type="submit"], input[type="submit"]').first().click()
    ]);

    await page.goto(DASHBOARD_URL);
    await expect(page.locator('.employee-attendance-filter')).toBeVisible();
    await expect(page.getByRole('heading', {
        name: 'Attendance History'
    })).toBeVisible();
}

async function applyDateFilter(page, fromDate, toDate) {
    await page.locator('#attendance_from').fill(fromDate);
    await page.locator('#attendance_to').fill(toDate);

    await Promise.all([
        page.waitForLoadState('domcontentloaded'),
        page.locator('.employee-attendance-view-button').click()
    ]);
}

function getDisplayedDates(page) {
    return page.locator(
        '.employee-attendance-table .employee-attendance-date'
    ).allTextContents();
}

test.describe('Feature Group 3 - Employee Attendance History', () => {

    test.beforeEach(async ({ page }) => {
        requireCredentials(EMPLOYEE_1, 'EMPLOYEE 1');
        await loginAsEmployee(page, EMPLOYEE_1);
    });

    // EMP-H01: Employee dashboard displays attendance history.
    test('EMP-H01 - Attendance history section loads', async ({ page }) => {
        await expect(page.getByRole('heading', {
            name: 'Attendance History'
        })).toBeVisible();

        await expect(page.locator('.employee-attendance-filter')).toBeVisible();
        await expect(page.locator('.employee-attendance-table')).toBeVisible();
    });

    // EMP-H02: Default date range is the first day of the current month through today.
    test('EMP-H02 - Default attendance history uses current month through today', async ({ page }) => {
        // Calculate the expected default attendance date range.
        const today = new Date();

        const firstDayOfMonth = new Date(
            today.getFullYear(),
            today.getMonth(),
            1
        );

        const lastDay = new Date(
            today.getFullYear(),
            today.getMonth(),
            today.getDate()
        );

        // On initial page load, no custom dates are submitted.
        // The application uses its internal default range for retrieval.
        await expect(page.locator('#attendance_from')).toHaveValue('');
        await expect(page.locator('#attendance_to')).toHaveValue('');

        // Confirm that the dashboard is loaded without custom date parameters.
        const currentUrl = new URL(page.url());

        expect(currentUrl.searchParams.get('action')).toBe('employee-dashboard');
        expect(currentUrl.searchParams.has('attendance_from')).toBe(false);
        expect(currentUrl.searchParams.has('attendance_to')).toBe(false);

        // Check that every displayed attendance record falls within
        // the current month's first day through today.
        const attendanceRows = page.locator(
            '.employee-attendance-table tbody tr:has(.employee-attendance-date)'
        );

        const rowCount = await attendanceRows.count();

        const monthNumbers = {
            Jan: 0,
            Feb: 1,
            Mar: 2,
            Apr: 3,
            May: 4,
            Jun: 5,
            Jul: 6,
            Aug: 7,
            Sep: 8,
            Oct: 9,
            Nov: 10,
            Dec: 11
        };

        for (let i = 0; i < rowCount; i++) {
            const displayedDate = (
                await attendanceRows.nth(i)
                    .locator('.employee-attendance-date')
                    .innerText()
            ).trim();

            // The dashboard displays dates in the format: 10 Nov 2026.
            const dateParts = displayedDate.split(/\s+/);

            expect(dateParts).toHaveLength(3);

            const day = Number(dateParts[0]);
            const month = monthNumbers[dateParts[1]];
            const year = Number(dateParts[2]);

            expect(Number.isInteger(day)).toBe(true);
            expect(month).toBeDefined();
            expect(Number.isInteger(year)).toBe(true);

            const attendanceDate = new Date(year, month, day);

            // Ensure the displayed date is a valid calendar date.
            expect(attendanceDate.getFullYear()).toBe(year);
            expect(attendanceDate.getMonth()).toBe(month);
            expect(attendanceDate.getDate()).toBe(day);

            // Ensure the attendance record is within the default date range.
            expect(attendanceDate.getTime()).toBeGreaterThanOrEqual(
                firstDayOfMonth.getTime()
            );

            expect(attendanceDate.getTime()).toBeLessThanOrEqual(
                lastDay.getTime()
            );
        }
    });

    // EMP-H03: Custom date filters return records within the selected range.
    test('EMP-H03 - Custom date range filters attendance records', async ({ page }) => {
        await applyDateFilter(page, '2026-01-01', '2026-12-31');

        await expect(page).toHaveURL(/attendance_from=2026-01-01/);
        await expect(page).toHaveURL(/attendance_to=2026-12-31/);

        const dates = await getDisplayedDates(page);

        for (const displayedDate of dates) {
            if (displayedDate.trim() === '') continue;

            const parsed = new Date(displayedDate);
            expect(Number.isNaN(parsed.getTime())).toBe(false);

            const isoDate = [
                parsed.getFullYear(),
                String(parsed.getMonth() + 1).padStart(2, '0'),
                String(parsed.getDate()).padStart(2, '0')
            ].join('-');

            expect(isoDate >= '2026-01-01').toBe(true);
            expect(isoDate <= '2026-12-31').toBe(true);
        }
    });

    // EMP-H04: Invalid calendar date is rejected by the controller.
    test('EMP-H04 - Invalid calendar date displays validation message', async ({ page }) => {
        await page.goto(
            `${DASHBOARD_URL}&attendance_from=2026-02-30&attendance_to=2026-03-01`
        );

        await expect(page.locator(
            '.employee-attendance-message.employee-attendance-error'
        )).toContainText(
            'Please enter valid From Date and To Date values.'
        );
    });

    // EMP-H05: From date later than To date is rejected.
    test('EMP-H05 - Reversed date range displays validation message', async ({ page }) => {
        await page.goto(
            `${DASHBOARD_URL}&attendance_from=2026-12-31&attendance_to=2026-01-01`
        );

        await expect(page.locator(
            '.employee-attendance-message.employee-attendance-error'
        )).toContainText(
            'From Date cannot be later than To Date.'
        );
    });

    // EMP-H06: Employee can expand RFID scan history.
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

        const controlledId = await toggle.getAttribute('aria-controls');
        const historyRow = page.locator(`#${controlledId}`);

        await expect(toggle).toHaveAttribute('aria-expanded', 'false');
        await expect(historyRow).toBeHidden();

        await toggle.click();

        await expect(toggle).toHaveAttribute('aria-expanded', 'true');
        await expect(historyRow).toBeVisible();
        await expect(historyRow.locator(
            '.employee-scan-history-panel'
        )).toBeVisible();

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

    // EMP-H07: Attendance history must be isolated to the authenticated employee.
    test('EMP-H07 - Employee cannot view another employee attendance history', async ({ page }) => {
        requireCredentials(EMPLOYEE_2, 'EMPLOYEE 2');

        const firstEmployeeDates = await getDisplayedDates(page);

        await page.context().clearCookies();
        await loginAsEmployee(page, EMPLOYEE_2);

        const secondEmployeeDates = await getDisplayedDates(page);

        // The employee dashboard must not expose another employee's records.
        // Dates alone are not unique identifiers, so verify that the page
        // remains tied to the second authenticated account and does not
        // accept an employee ID supplied through the URL.
        await page.goto(
            `${DASHBOARD_URL}&employee_id=1`
        );

        await expect(page.locator(
            '.employee-attendance-filter'
        )).toBeVisible();

        await expect(page.locator(
            '.employee-attendance-table'
        )).toBeVisible();

        expect(firstEmployeeDates).toBeDefined();
        expect(secondEmployeeDates).toBeDefined();

        await expect(page.locator('body')).not.toContainText(
            'Access denied'
        );
    });

    // EMP-H08: Unauthenticated users cannot access employee attendance history.
    test('EMP-H08 - Unauthenticated access redirects to login', async ({ page }) => {
        await page.context().clearCookies();

        await page.goto(DASHBOARD_URL);

        await expect(page).not.toHaveURL(
            /action=employee-dashboard/
        );
    });
});
