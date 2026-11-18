const { test, expect } = require('@playwright/test');

/*
===============================================================================
PROJECT: HRIS Management System
ORGANIZATION: MY Outsourcing Ltd. (MYOL)

FEATURE 1: ADMINISTRATIVE ATTENDANCE MANAGEMENT
TEST TYPE: AUTOMATED BLACK-BOX TESTING
FRAMEWORK: PLAYWRIGHT
BROWSER: CHROMIUM

TEST CASE SUMMARY — 12 CASES

BB-ADM-01: Verify administrator access to Attendance Management.
BB-ADM-02: Verify available departments.
BB-ADM-03: Verify HR employee dropdown.
BB-ADM-04: Verify Reset functionality.
BB-ADM-05: Verify department-wide attendance retrieval without selecting
           an individual employee.
BB-ADM-06: Verify missing date validation.
BB-ADM-07: Verify invalid calendar date handling.
BB-ADM-08: Verify date-range validation.
BB-ADM-09: Verify department-based employee filtering.
BB-ADM-10: Verify individual employee attendance retrieval.
BB-ADM-11: Verify RFID scan history.
BB-ADM-12: Verify employee authorization restrictions.

IMPORTANT:
- Administrator credentials are used for BB-ADM-01 to BB-ADM-11.
- Employee credentials are used for BB-ADM-12.
- Uses the existing application database.
- Does not delete attendance or scan-history records.
- BB-ADM-05 tests the updated department-wide attendance workflow.
===============================================================================
*/

// ============================================================================
// APPLICATION CONFIGURATION
// ============================================================================

const BASE_URL =
    'http://localhost/HRIS-Management-System/public/index.php';

const ATTENDANCE_URL =
    BASE_URL + '?action=attendance-management';

// ============================================================================
// LOGIN CREDENTIALS
// ============================================================================

const ADMIN_USERNAME = process.env.HRIS_ADMIN_USERNAME;
const ADMIN_PASSWORD = process.env.HRIS_ADMIN_PASSWORD;

const EMPLOYEE_USERNAME = process.env.HRIS_EMPLOYEE_USERNAME;
const EMPLOYEE_PASSWORD = process.env.HRIS_EMPLOYEE_PASSWORD;

// ============================================================================
// COMMON LOGIN METHOD
// ============================================================================

async function login(page, username, password) {
    if (!username || !password) {
        throw new Error(
            'Login credentials are missing. Set the required environment variables.'
        );
    }

    await page.goto(BASE_URL);

    await page.locator('input[name="username"]').fill(username);
    await page.locator('input[name="password"]').fill(password);

    await page.getByRole('button', { name: /login/i }).click();

    await page.waitForLoadState('domcontentloaded');
}

// ============================================================================
// COMMON ADMINISTRATOR LOGIN METHOD
// ============================================================================

async function loginAsAdmin(page) {
    await login(page, ADMIN_USERNAME, ADMIN_PASSWORD);

    await page.goto(ATTENDANCE_URL);

    await expect(page.locator('h1'))
        .toHaveText('Administrative Attendance Management');
}

// ============================================================================
// COMMON HR DEPARTMENT SELECTION
// ============================================================================

async function selectHR(page) {
    await page.locator('#department').selectOption({
        label: 'HR'
    });

    await page.waitForLoadState('domcontentloaded');
}

// ============================================================================
// BB-ADM-01
// Administrator opens Attendance Management
// ============================================================================

test('BB-ADM-01 - Administrator opens Attendance Management', async ({ page }) => {

    await loginAsAdmin(page);

    await expect(page.locator('h1'))
        .toHaveText('Administrative Attendance Management');

    await expect(page.locator('#department')).toBeVisible();
    await expect(page.locator('#employee')).toBeVisible();

    await expect(page.locator('#from-date')).toBeVisible();
    await expect(page.locator('#to-date')).toBeVisible();
});

// ============================================================================
// BB-ADM-02
// Verify available departments
// ============================================================================

test('BB-ADM-02 - Verify available departments', async ({ page }) => {

    await loginAsAdmin(page);

    const departmentOptions = await page
        .locator('#department option')
        .allTextContents();

    const cleanedDepartmentOptions = departmentOptions.map(option =>
        option.replace('·', '').trim()
    );

    expect(cleanedDepartmentOptions).toContain('Admin');
    expect(cleanedDepartmentOptions).toContain('Business');
    expect(cleanedDepartmentOptions).toContain('HR');
});

// ============================================================================
// BB-ADM-03
// Verify HR employee dropdown
// ============================================================================

test('BB-ADM-03 - Verify HR employee dropdown', async ({ page }) => {

    await loginAsAdmin(page);

    await selectHR(page);

    await expect(page.locator('#employee')).toBeEnabled();

    await expect(page.locator('#employee'))
        .toContainText('EMP-0004 - Samia Esha');
});

// ============================================================================
// BB-ADM-04
// Verify Reset functionality
// ============================================================================

test('BB-ADM-04 - Verify Reset functionality', async ({ page }) => {

    await loginAsAdmin(page);

    await selectHR(page);

    await expect(page.locator('#employee')).toBeEnabled();

    await page.locator('#employee').selectOption({
        label: 'EMP-0004 - Samia Esha'
    });

    await page.locator('a.attendance-reset-button').click();

    await expect(page.locator('#department')).toHaveValue('');

    await expect(page.locator('#employee')).toBeDisabled();

    await expect(page.locator('#from-date')).toBeDisabled();
    await expect(page.locator('#to-date')).toBeDisabled();

    await expect(
        page.locator('button[name="view_attendance"]')
    ).toBeDisabled();
});

// ============================================================================
// BB-ADM-05
// Department-wide attendance retrieval
// Employee selection is optional.
// ============================================================================

test('BB-ADM-05 - Verify department-wide attendance retrieval without selecting an employee', async ({ page }) => {

    await loginAsAdmin(page);

    // Select HR department.
    await selectHR(page);

    // Employee dropdown should be enabled, but no employee is selected.
    await expect(page.locator('#employee')).toBeEnabled();

    await expect(page.locator('#employee')).toHaveValue('');

    // Date fields should be enabled after selecting a department.
    await expect(page.locator('#from-date')).toBeEnabled();
    await expect(page.locator('#to-date')).toBeEnabled();

    // View Attendance should also be enabled.
    const viewButton = page.locator(
        'button[name="view_attendance"]'
    );

    await expect(viewButton).toBeEnabled();

    // Capture employee codes belonging to HR.
    const hrEmployeeOptions = await page
        .locator('#employee option')
        .evaluateAll(options =>
            options
                .filter(option => option.value !== '')
                .map(option => option.textContent.trim())
                .filter(text => text !== '')
        );

    expect(hrEmployeeOptions.length).toBeGreaterThan(0);

    // Leave employee selection blank.
    await page.locator('#employee').selectOption('');

    // Select the attendance date range.
    await page.locator('#from-date').fill('2026-01-01');
    await page.locator('#to-date').fill('2026-12-31');

    // Submit department-wide attendance search.
    await viewButton.click();

    await page.waitForLoadState('domcontentloaded');

    // Verify attendance table is displayed.
    const attendanceRows = page.locator(
        '.attendance-management-table tbody tr:not(.attendance-log-expanded-row)'
    );

    await expect(attendanceRows.first()).toBeVisible();

    // Every displayed employee should belong to the HR dropdown list.
    const hrEmployeeCodes = hrEmployeeOptions.map(option =>
        option.split(' - ')[0].trim()
    );

    const rowCount = await attendanceRows.count();

    for (let i = 0; i < rowCount; i++) {

        const rowText = await attendanceRows.nth(i).innerText();

        const matchingEmployee = hrEmployeeCodes.some(code =>
            rowText.includes(code)
        );

        expect(
            matchingEmployee,
            `Attendance row ${i + 1} does not belong to an employee listed in HR.`
        ).toBeTruthy();
    }
});

// ============================================================================
// BB-ADM-06
// Verify missing date validation
// ============================================================================

test('BB-ADM-06 - Verify missing date validation', async ({ page }) => {

    await loginAsAdmin(page);

    await selectHR(page);

    await page.locator('#employee').selectOption({
        label: 'EMP-0004 - Samia Esha'
    });

    await expect(page.locator('#from-date')).toBeEnabled();

    await page.locator('#from-date').fill('');
    await page.locator('#to-date').fill('');

    await page.locator('button[name="view_attendance"]').click();

    await expect(page.getByRole('alert'))
        .toContainText(
            'Please select both From Date and To Date.'
        );
});

// ============================================================================
// BB-ADM-07
// Verify invalid calendar date handling
// ============================================================================

test('BB-ADM-07 - Verify invalid calendar date handling', async ({ page }) => {

    await loginAsAdmin(page);

    await selectHR(page);

    await page.locator('#employee').selectOption({
        label: 'EMP-0004 - Samia Esha'
    });

    const fromDate = page.locator('#from-date');

    await expect(fromDate).toBeEnabled();

    const rejectedValue = await fromDate.evaluate(element => {

        element.value = '2026-02-30';

        return element.value;
    });

    expect(rejectedValue).toBe('');
});

// ============================================================================
// BB-ADM-08
// Verify date-range validation
// ============================================================================

test('BB-ADM-08 - Verify date range validation', async ({ page }) => {

    await loginAsAdmin(page);

    await selectHR(page);

    await page.locator('#employee').selectOption({
        label: 'EMP-0004 - Samia Esha'
    });

    await page.locator('#from-date').fill('2026-03-10');
    await page.locator('#to-date').fill('2026-03-01');

    await page.locator('button[name="view_attendance"]').click();

    await expect(page.getByRole('alert'))
        .toContainText(
            'From Date cannot be later than To Date.'
        );
});

// ============================================================================
// BB-ADM-09
// Verify department employee filtering
// ============================================================================

test('BB-ADM-09 - Verify department employee filtering', async ({ page }) => {

    await loginAsAdmin(page);

    await selectHR(page);

    await expect(page.locator('#employee'))
        .toContainText('Samia Esha');

    const employeeOptions = await page
        .locator('#employee option')
        .allTextContents();

    const cleanedEmployeeOptions = employeeOptions.map(option =>
        option.replace('·', '').trim()
    );

    expect(
        cleanedEmployeeOptions.some(option =>
            option.includes('EMP-0004') &&
            option.includes('Samia Esha')
        )
    ).toBeTruthy();

    // Verify that a known Business employee is not listed in HR.
    expect(
        cleanedEmployeeOptions.some(option =>
            option.includes('EMP-0003') &&
            option.includes('Pinky Akter')
        )
    ).toBeFalsy();
});

// ============================================================================
// BB-ADM-10
// Verify individual employee attendance retrieval
// ============================================================================

test('BB-ADM-10 - Verify individual employee attendance retrieval', async ({ page }) => {

    await loginAsAdmin(page);

    await selectHR(page);

    await page.locator('#employee').selectOption({
        label: 'EMP-0004 - Samia Esha'
    });

    await page.locator('#from-date').fill('2026-01-01');
    await page.locator('#to-date').fill('2026-12-31');

    await page.locator('button[name="view_attendance"]').click();

    await page.waitForLoadState('domcontentloaded');

    const attendanceRows = page.locator(
        '.attendance-management-table tbody tr:not(.attendance-log-expanded-row)'
    );

    // At least one attendance record must be displayed.
    await expect(attendanceRows.first()).toBeVisible();

    const rowCount = await attendanceRows.count();

    expect(rowCount).toBeGreaterThan(0);

    // All displayed records must belong to EMP-0004.
    for (let i = 0; i < rowCount; i++) {

        await expect(attendanceRows.nth(i))
            .toContainText('EMP-0004');
    }
});

// ============================================================================
// BB-ADM-11
// Verify RFID scan history
// ============================================================================

test('BB-ADM-11 - Verify RFID scan history', async ({ page }) => {

    await loginAsAdmin(page);

    await selectHR(page);

    await page.locator('#employee').selectOption({
        label: 'EMP-0004 - Samia Esha'
    });

    await page.locator('#from-date').fill('2026-01-01');
    await page.locator('#to-date').fill('2026-12-31');

    await page.locator('button[name="view_attendance"]').click();

    await page.waitForLoadState('domcontentloaded');

    const attendanceRows = page.locator(
        '.attendance-management-table tbody tr:not(.attendance-log-expanded-row)'
    );

    await expect(attendanceRows.first()).toBeVisible();

    const viewLogButton = attendanceRows
        .first()
        .locator('button.attendance-log-toggle');

    const historyRowId = await viewLogButton.getAttribute(
        'aria-controls'
    );

    expect(historyRowId).toBeTruthy();

    await viewLogButton.click();

    const historyRow = page.locator(`#${historyRowId}`);

    await expect(historyRow).toBeVisible();

    await expect(
        historyRow.locator('.attendance-log-panel')
    ).toBeVisible();
});

// ============================================================================
// BB-ADM-12
// Verify employee cannot access admin attendance page
// ============================================================================

test('BB-ADM-12 - Verify employee cannot access admin attendance page', async ({ page }) => {

    await login(
        page,
        EMPLOYEE_USERNAME,
        EMPLOYEE_PASSWORD
    );

    const response = await page.goto(ATTENDANCE_URL);

    expect(response.status()).toBe(403);

    await expect(page.locator('body'))
        .toContainText(/access denied/i);
});