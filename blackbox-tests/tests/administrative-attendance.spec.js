/*
===============================================================================
PROJECT: HRIS Management System
ORGANIZATION: MY Outsourcing Ltd. (MYOL)

FEATURE 1: ADMINISTRATIVE ATTENDANCE MANAGEMENT
TEST TYPE: AUTOMATED BLACK-BOX TESTING
FRAMEWORK: PLAYWRIGHT
BROWSER: CHROMIUM

===============================================================================
BLACK-BOX TEST CASE SUMMARY
===============================================================================

BB-ADM-01
Purpose: Verify administrator access to Attendance Management.
Expected Output: Administrative Attendance Management page is displayed.

BB-ADM-02
Purpose: Verify the department dropdown.
Expected Output: Admin, Business, and HR are available.

BB-ADM-03
Purpose: Verify employee filtering for the HR department.
Expected Output: EMP-0004 - Samia Esha is displayed.

BB-ADM-04
Purpose: Verify the Reset function.
Expected Output: Department and employee selections are cleared.
               Employee and date controls return to their initial states.

BB-ADM-05
Purpose: Verify employee selection is required.
Expected Output: Date fields and View Attendance remain disabled
                 when no employee is selected.

BB-ADM-06
Purpose: Verify missing date validation.
Expected Output: "Please select both From Date and To Date."

BB-ADM-07
Purpose: Verify invalid calendar date handling.
Expected Output: The browser rejects an impossible date such as
                 2026-02-30.

BB-ADM-08
Purpose: Verify date-range validation.
Expected Output: "From Date cannot be later than To Date."

BB-ADM-09
Purpose: Verify department-based employee filtering.
Expected Output: Only employees belonging to HR are listed.
                 EMP-0004 - Samia Esha is displayed.

BB-ADM-10
Purpose: Verify attendance record retrieval.
Expected Output: Two attendance records are displayed for the
                 selected employee and date range.

BB-ADM-11
Purpose: Verify RFID scan history.
Expected Output: RFID scan history is expanded for the selected
                 attendance record.

BB-ADM-12
Purpose: Verify employee authorization restrictions.
Expected Output: An employee receives HTTP 403 Access Denied when
                 directly accessing Administrative Attendance Management.

===============================================================================
PASS / FAIL RULE
===============================================================================

PASS: Actual browser output satisfies the expected output.
FAIL: Actual browser output does not satisfy the expected output.

NOTE:
Each test uses a fresh browser context.
Administrator credentials are used for BB-ADM-01 to BB-ADM-11.
Employee credentials are used for BB-ADM-12.

===============================================================================
*/


const { test, expect } = require('@playwright/test');


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

    // Verify that the required login credentials are available.
    if (!username || !password) {
        throw new Error(
            'Login credentials are missing. Set the required environment variables.'
        );
    }

    // Open the HRIS login page.
    await page.goto(BASE_URL);

    // Enter the username.
    await page.locator('input[name="username"]').fill(username);

    // Enter the password.
    await page.locator('input[name="password"]').fill(password);

    // Submit the login form.
    await page.getByRole('button', { name: /login/i }).click();

    // Wait for the application to finish navigating.
    await page.waitForLoadState('domcontentloaded');
}


// ============================================================================
// COMMON ADMINISTRATOR LOGIN METHOD
// ============================================================================

async function loginAsAdmin(page) {

    // Authenticate using the administrator account.
    await login(page, ADMIN_USERNAME, ADMIN_PASSWORD);

    // Open Administrative Attendance Management.
    await page.goto(ATTENDANCE_URL);

    // BB-ADM-01 to BB-ADM-11:
    // Verify the main page heading, not the duplicate header heading.
    await expect(
        page.locator('h1')
    ).toHaveText('Administrative Attendance Management');
}


// ============================================================================
// BB-ADM-01
// METHOD / CLASS: AttendanceController::management()
// EXPECTED OUTPUT:
// Administrative Attendance Management page is displayed successfully.
// ============================================================================

test('BB-ADM-01 - Administrator opens Attendance Management', async ({ page }) => {

    // Authenticate as administrator.
    await loginAsAdmin(page);

    // Verify the main page heading, avoiding the duplicate header heading.
    await expect(page.locator('h1'))
        .toHaveText('Administrative Attendance Management');

    // Verify the department dropdown.
    await expect(page.locator('#department')).toBeVisible();

    // Verify the employee dropdown.
    await expect(page.locator('#employee')).toBeVisible();

    // Verify the date fields.
    await expect(page.locator('#from-date')).toBeVisible();
    await expect(page.locator('#to-date')).toBeVisible();

});


// ============================================================================
// BB-ADM-02
// METHOD / CLASS: AttendanceController::management()
// EXPECTED OUTPUT:
// Department dropdown contains Admin, Business, and HR.
// ============================================================================

test('BB-ADM-02 - Verify available departments', async ({ page }) => {

    // Authenticate as administrator.
    await loginAsAdmin(page);

    // Get all department options.
    const departmentOptions = await page
        .locator('#department option')
        .allTextContents();

    const cleanedDepartmentOptions = departmentOptions.map(option =>
        option.replace('·', '').trim()
    );

    // Verify the expected departments.
    expect(cleanedDepartmentOptions).toContain('Admin');
    expect(cleanedDepartmentOptions).toContain('Business');
    expect(cleanedDepartmentOptions).toContain('HR');

});


// ============================================================================
// BB-ADM-03
// METHOD / CLASS: AttendanceController::management()
// EXPECTED OUTPUT:
// Selecting HR displays EMP-0004 - Samia Esha.
// ============================================================================

test('BB-ADM-03 - Verify HR employee dropdown', async ({ page }) => {

    // Authenticate as administrator.
    await loginAsAdmin(page);

    // Select the HR department.
    await page.locator('#department').selectOption({
        label: 'HR'
    });

    // Wait for the employee dropdown to become available.
    await expect(page.locator('#employee')).toBeEnabled();

    // Verify that the expected employee is listed.
    await expect(
        page.locator('#employee')
    ).toContainText('EMP-0004 - Samia Esha');

});


// ============================================================================
// BB-ADM-04
// METHOD / CLASS: AttendanceController::management()
// EXPECTED OUTPUT:
// Reset clears the selected department and employee.
// ============================================================================

test('BB-ADM-04 - Verify Reset functionality', async ({ page }) => {

    // Authenticate as administrator.
    await loginAsAdmin(page);

    // Select HR.
    await page.locator('#department').selectOption({
        label: 'HR'
    });

    // Wait until the employee dropdown is enabled.
    await expect(page.locator('#employee')).toBeEnabled();

    // Select the HR employee.
    await page.locator('#employee').selectOption({
        label: 'EMP-0004 - Samia Esha'
    });

    // Click Reset.
    await page.locator('a.attendance-reset-button').click();

    // Verify that the department selection is cleared.
    await expect(page.locator('#department')).toHaveValue('');

    // Verify that the employee dropdown is disabled.
    await expect(page.locator('#employee')).toBeDisabled();

    // Verify that the date fields are disabled.
    await expect(page.locator('#from-date')).toBeDisabled();
    await expect(page.locator('#to-date')).toBeDisabled();

    // Verify that View Attendance is disabled.
    await expect(
        page.locator('button[name="view_attendance"]')
    ).toBeDisabled();

});


// ============================================================================
// BB-ADM-05
// METHOD / CLASS: AttendanceController::management()
// EXPECTED OUTPUT:
// Without employee selection, date fields and View Attendance are disabled.
// ============================================================================

test('BB-ADM-05 - Verify employee selection requirement', async ({ page }) => {

    // Authenticate as administrator.
    await loginAsAdmin(page);

    // Select HR but do not select an employee.
    await page.locator('#department').selectOption({
        label: 'HR'
    });

    // Verify employee dropdown is enabled.
    await expect(page.locator('#employee')).toBeEnabled();

    // Verify date fields remain disabled.
    await expect(page.locator('#from-date')).toBeDisabled();
    await expect(page.locator('#to-date')).toBeDisabled();

    // Verify View Attendance remains disabled.
    await expect(
        page.locator('button[name="view_attendance"]')
    ).toBeDisabled();

});


// ============================================================================
// BB-ADM-06
// METHOD / CLASS: AttendanceController::management()
// EXPECTED OUTPUT:
// "Please select both From Date and To Date."
// ============================================================================

test('BB-ADM-06 - Verify missing date validation', async ({ page }) => {

    // Authenticate as administrator.
    await loginAsAdmin(page);

    // Select HR.
    await page.locator('#department').selectOption({
        label: 'HR'
    });

    // Select Samia Esha.
    await page.locator('#employee').selectOption({
        label: 'EMP-0004 - Samia Esha'
    });

    // Wait for the date fields to become enabled.
    await expect(page.locator('#from-date')).toBeEnabled();

    // Leave both date fields empty.
    await page.locator('#from-date').fill('');
    await page.locator('#to-date').fill('');

    // Click View Attendance.
    await page.locator('button[name="view_attendance"]').click();

    // Verify the expected validation message.
    await expect(
        page.getByRole('alert')
    ).toContainText(
        'Please select both From Date and To Date.'
    );

});


// ============================================================================
// BB-ADM-07
// METHOD / CLASS: Browser HTML date input validation
// EXPECTED OUTPUT:
// Browser rejects an impossible date such as 2026-02-30.
// ============================================================================

test('BB-ADM-07 - Verify invalid calendar date handling', async ({ page }) => {

    // Authenticate as administrator.
    await loginAsAdmin(page);

    // Select HR.
    await page.locator('#department').selectOption({
        label: 'HR'
    });

    // Select Samia Esha.
    await page.locator('#employee').selectOption({
        label: 'EMP-0004 - Samia Esha'
    });

    // Wait for the date field to become enabled.
    const fromDate = page.locator('#from-date');

    await expect(fromDate).toBeEnabled();

    // Attempt to assign an impossible calendar date.
    const rejectedValue = await fromDate.evaluate((element) => {

        // HTML date inputs sanitize invalid calendar values.
        element.value = '2026-02-30';

        return element.value;

    });

    // Verify that the browser rejected the impossible date.
    expect(rejectedValue).toBe('');

});


// ============================================================================
// BB-ADM-08
// METHOD / CLASS: AttendanceController::management()
// EXPECTED OUTPUT:
// "From Date cannot be later than To Date."
// ============================================================================

test('BB-ADM-08 - Verify date range validation', async ({ page }) => {

    // Authenticate as administrator.
    await loginAsAdmin(page);

    // Select HR.
    await page.locator('#department').selectOption({
        label: 'HR'
    });

    // Select Samia Esha.
    await page.locator('#employee').selectOption({
        label: 'EMP-0004 - Samia Esha'
    });

    // Enter a later From Date.
    await page.locator('#from-date').fill('2026-03-10');

    // Enter an earlier To Date.
    await page.locator('#to-date').fill('2026-03-01');

    // Submit the attendance search.
    await page.locator('button[name="view_attendance"]').click();

    // Verify the expected validation message.
    await expect(
        page.getByRole('alert')
    ).toContainText(
        'From Date cannot be later than To Date.'
    );

});


// ============================================================================
// BB-ADM-09
// METHOD / CLASS: Attendance::getEmployeesByDepartment()
// EXPECTED OUTPUT:
// Only employees belonging to HR are displayed.
// Expected HR employee: EMP-0004 - Samia Esha.
// ============================================================================

test('BB-ADM-09 - Verify department employee filtering', async ({ page }) => {

    // Authenticate as administrator.
    await loginAsAdmin(page);

    // Select HR.
    await page.locator('#department').selectOption({
        label: 'HR'
    });

    // Wait for the department-change form submission to finish.
    await page.waitForLoadState('domcontentloaded');

    // Wait until the employee dropdown contains the expected HR employee.
    await expect(page.locator('#employee'))
        .toContainText('Samia Esha');

    // Get all employee options.
    const employeeOptions = await page
        .locator('#employee option')
        .allTextContents();

    // Remove extra whitespace and the bullet character.
    const cleanedEmployeeOptions = employeeOptions.map(option =>
        option.replace('·', '').trim()
    );

    // Verify the expected HR employee is available.
    expect(
        cleanedEmployeeOptions.some(option =>
            option.includes('EMP-0004') && option.includes('Samia Esha')
        )
    ).toBeTruthy();

    // Verify that an employee from Business is not displayed.
    expect(
        cleanedEmployeeOptions.some(option =>
            option.includes('EMP-0003') && option.includes('Pinky Akter')
        )
    ).toBeFalsy();

});


// ============================================================================
// BB-ADM-10
// METHOD / CLASS: Attendance::getEmployeeAttendanceByDateRange()
// EXPECTED OUTPUT:
// Two attendance records are displayed for EMP-0004
// between 2026-01-01 and 2026-12-31.
// ============================================================================

test('BB-ADM-10 - Verify attendance record retrieval', async ({ page }) => {

    // Authenticate as administrator.
    await loginAsAdmin(page);

    // Select HR.
    await page.locator('#department').selectOption({
        label: 'HR'
    });

    // Select Samia Esha.
    await page.locator('#employee').selectOption({
        label: 'EMP-0004 - Samia Esha'
    });

    // Enter the attendance date range.
    await page.locator('#from-date').fill('2026-01-01');
    await page.locator('#to-date').fill('2026-12-31');

    // Submit the attendance search.
    await page.locator('button[name="view_attendance"]').click();

    // Locate actual attendance rows, excluding expandable RFID rows.
    const attendanceRows = page.locator(
        '.attendance-management-table tbody tr:not(.attendance-log-expanded-row)'
    );

    // Verify that two attendance records are displayed.
    await expect(attendanceRows).toHaveCount(2);

});


// ============================================================================
// BB-ADM-11
// METHOD / CLASS: Attendance::getEmployeeScanHistoryForDate()
// EXPECTED OUTPUT:
// Clicking View Log expands the RFID scan history panel.
// ============================================================================

test('BB-ADM-11 - Verify RFID scan history', async ({ page }) => {

    // Authenticate as administrator.
    await loginAsAdmin(page);

    // Select HR.
    await page.locator('#department').selectOption({
        label: 'HR'
    });

    // Select Samia Esha.
    await page.locator('#employee').selectOption({
        label: 'EMP-0004 - Samia Esha'
    });

    // Enter the attendance date range.
    await page.locator('#from-date').fill('2026-01-01');
    await page.locator('#to-date').fill('2026-12-31');

    // Submit the attendance search.
    await page.locator('button[name="view_attendance"]').click();

    // Verify attendance records are available.
    const attendanceRows = page.locator(
        '.attendance-management-table tbody tr:not(.attendance-log-expanded-row)'
    );

    await expect(attendanceRows).toHaveCount(2);

    // Locate the View RFID Scan History button in the first attendance row.
    const viewLogButton = attendanceRows
        .first()
        .locator('button.attendance-log-toggle');

    // Read the expandable RFID history row ID.
    const historyRowId = await viewLogButton.getAttribute(
        'aria-controls'
    );

    // Click View Log.
    await viewLogButton.click();

    // Verify the RFID history row becomes visible.
    await expect(
        page.locator('#' + historyRowId)
    ).toBeVisible();

    // Verify the RFID history panel is displayed.
    await expect(
        page.locator('#' + historyRowId)
            .locator('.attendance-log-panel')
    ).toBeVisible();

});


// ============================================================================
// BB-ADM-12
// METHOD / CLASS: Middleware::requireAdmin()
// EXPECTED OUTPUT:
// Employee receives HTTP 403 Access Denied when accessing the admin page.
// ============================================================================

test('BB-ADM-12 - Verify employee cannot access admin attendance page', async ({ page }) => {

    // Authenticate using an employee account.
    await login(
        page,
        EMPLOYEE_USERNAME,
        EMPLOYEE_PASSWORD
    );

    // Directly request the administrator attendance management URL.
    const response = await page.goto(ATTENDANCE_URL);

    // Verify that the server responds with HTTP 403.
    expect(response.status()).toBe(403);

    // Verify the access-denied response.
    await expect(page.locator('body')).toContainText(
        /access denied/i
    );

});