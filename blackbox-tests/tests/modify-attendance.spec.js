const { test, expect } = require('@playwright/test');

/*
===============================================================================
PROJECT: HRIS Management System
FEATURE 2: MODIFY ATTENDANCE
TEST TYPE: AUTOMATED BLACK-BOX TESTING
BROWSER: CHROMIUM
EMPLOYEE: EMP-0004 - Samia Esha

TEST CASE SUMMARY — 22 CASES

MOD-01: Verify Modify Attendance page and form fields load.
MOD-02: Verify GET request to the save route redirects to Modify Attendance.
MOD-03: Verify invalid CSRF token is rejected.
MOD-04: Verify invalid employee ID is rejected.
MOD-05: Verify invalid calendar date is rejected.
MOD-06: Verify empty entry and exit times are rejected.
MOD-07: Verify invalid entry time is rejected.
MOD-08: Verify invalid exit time is rejected.
MOD-09: Verify exit time earlier than entry time is rejected.
MOD-10: Verify entry and exit can be created together.
MOD-11: Verify entry can be created without an exit.
MOD-12: Verify duplicate attendance for the same employee/date is rejected.
MOD-13: Verify nonexistent employee is rejected.
MOD-14: Verify employee without an effective work schedule is rejected.
        Intentionally skipped until a safe employee is identified.
MOD-15: Verify an exit can be added to an existing open attendance record.
MOD-16: Verify exit-only submission is rejected when no entry exists.
MOD-17: Verify a second exit is rejected when an exit already exists.
MOD-18: Verify exit earlier than the existing entry is rejected.
MOD-19: Verify complete attendance can be created successfully.
MOD-20: Verify duplicate creation is rejected.
MOD-21: Verify exit can be added after an entry-only record.
MOD-22: Verify exit-only submission without an existing entry is rejected.

IMPORTANT:
- Uses the current form and controller fields: employee_id, attendance_date,
  entry_time, exit_time and csrf_token.
- Uses the existing production database connection.
- Selects dates only after checking the attendance-management results.
- Uses a different unused date for each independent creation test.
- Never deletes attendance or scan-history records.
- Does not modify employee work schedules.
- MOD-14 is intentionally skipped.
- No A_E_ fields are used.
===============================================================================
*/

const BASE_URL =
    'http://localhost/HRIS-Management-System/public/index.php';

const MODIFY_URL = `${BASE_URL}?action=modify-attendance`;
const SAVE_URL = `${BASE_URL}?action=save-modified-attendance`;
const MANAGEMENT_URL = `${BASE_URL}?action=attendance-management`;

const EMPLOYEE_CODE = 'EMP-0004';
const ADMIN_USERNAME = process.env.HRIS_ADMIN_USERNAME;
const ADMIN_PASSWORD = process.env.HRIS_ADMIN_PASSWORD;
const MAX_DATE_SEARCH_DAYS = 180;

// -----------------------------------------------------------------------------
// Login helpers
// -----------------------------------------------------------------------------

async function login(page, username, password) {
    if (!username || !password) {
        throw new Error(
            'Admin credentials are missing. Set HRIS_ADMIN_USERNAME and HRIS_ADMIN_PASSWORD.'
        );
    }

    await page.goto(BASE_URL);
    await page.locator('input[name="username"]').fill(username);
    await page.locator('input[name="password"]').fill(password);
    await page.getByRole('button', { name: /login/i }).click();
    await page.waitForLoadState('domcontentloaded');
}

async function loginAsAdmin(page) {
    await login(page, ADMIN_USERNAME, ADMIN_PASSWORD);
    await page.goto(MODIFY_URL);

    await expect(page.locator('h1')).toHaveText('Modify Attendance');
    await expect(page.locator('input[name="csrf_token"]')).toBeAttached();
}

// -----------------------------------------------------------------------------
// Employee, department and date safety helpers
// -----------------------------------------------------------------------------

async function getSamiaEmployeeId(page) {
    const employeeCodeInput = page.locator('#employee_code_input');
    const employeeIdInput = page.locator('#employee_id');

    await expect(employeeCodeInput).toBeVisible();
    await expect(employeeIdInput).toBeAttached();

    // Read the employee options from the actual datalist.
    const employee = await page.locator(
        '#employee_code_options option'
    ).evaluateAll((options) => {
        const matchingOption = options.find((option) =>
            option.value.trim().toUpperCase().startsWith('EMP-0004')
        );

        if (!matchingOption) {
            return null;
        }

        return {
            value: matchingOption.value,
            employeeId: matchingOption.dataset.employeeId
        };
    });

    if (!employee || !employee.employeeId) {
        throw new Error(
            'EMP-0004 (Samia Esha) was not found in the employee datalist.'
        );
    }

    // Enter the exact employee code and trigger the PHP page's JavaScript.
    await employeeCodeInput.fill(employee.value);

    // Wait for the page JavaScript to populate the hidden employee ID.
    await expect(employeeIdInput).toHaveValue(
        String(employee.employeeId)
    );

    return String(employee.employeeId);
}

async function getCsrfToken(page) {
    return page.locator('input[name="csrf_token"]').inputValue();
}

async function getSamiaDepartment(page, employeeId) {
    await page.goto(MANAGEMENT_URL);

    const departmentSelect = page.locator('#department');
    await expect(departmentSelect).toBeVisible();

    const departments = await departmentSelect.locator('option').evaluateAll(
        (options) =>
            options
                .map((option) => option.value)
                .filter((value) => value !== '')
    );

    for (const department of departments) {
        await departmentSelect.selectOption(department);
        await page.waitForLoadState('domcontentloaded');

        const employeeSelect = page.locator('#employee');
        await expect(employeeSelect).toBeAttached();

        const found = await employeeSelect.locator('option').evaluateAll(
            (options, id) =>
                options.some((option) => option.value === String(id)),
            employeeId
        );

        if (found) {
            return department;
        }
    }

    throw new Error(
        `Could not identify the department for ${EMPLOYEE_CODE} on the attendance-management page.`
    );
}

async function hasAttendanceRecord(
    page,
    department,
    employeeId,
    attendanceDate
) {
    const query = new URLSearchParams({
        action: 'attendance-management',
        department,
        employee: String(employeeId),
        from_date: attendanceDate,
        to_date: attendanceDate,
        view_attendance: '1'
    });

    await page.goto(`${BASE_URL}?${query.toString()}`);
    await page.waitForLoadState('domcontentloaded');

    const table = page.locator('.attendance-management-table');
    await expect(table).toBeVisible();

    const rows = await table.locator('tbody tr').evaluateAll((tableRows) =>
        tableRows.map((row) =>
            Array.from(row.querySelectorAll('td')).map((cell) =>
                cell.innerText.trim()
            )
        )
    );

    return rows.some(
        (cells) =>
            cells.length >= 3 &&
            cells[0].includes(EMPLOYEE_CODE) &&
            cells[2] === attendanceDate
    );
}

function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

async function findUnusedAttendanceDate(page) {
    // Get the employee ID from the Modify Attendance page first.
    await page.goto(MODIFY_URL);

    const employeeId = await getSamiaEmployeeId(page);

    // The management page is used only to find an unused date.
    const department = await getSamiaDepartment(page, employeeId);

    const startDate = new Date();
    startDate.setHours(12, 0, 0, 0);

    for (let offset = 1; offset <= MAX_DATE_SEARCH_DAYS; offset++) {
        const candidate = new Date(startDate);
        candidate.setDate(startDate.getDate() + offset);

        const weekday = candidate.getDay();

        if (weekday === 0 || weekday === 6) {
            continue;
        }

        const candidateDate = formatDate(candidate);

        const occupied = await hasAttendanceRecord(
            page,
            department,
            employeeId,
            candidateDate
        );

        if (!occupied) {
            return candidateDate;
        }
    }

    throw new Error(
        `No unused weekday was found for ${EMPLOYEE_CODE} ` +
        `within ${MAX_DATE_SEARCH_DAYS} days.`
    );
}

// -----------------------------------------------------------------------------
// POST helper
// -----------------------------------------------------------------------------

async function submitAttendance(page, values = {}) {
    await page.goto(MODIFY_URL);

    const csrfToken = await getCsrfToken(page);

    const formData = {
        csrf_token: values.csrf_token ?? csrfToken,
        employee_id: String(values.employee_id ?? ''),
        attendance_date: values.attendance_date ?? '',
        entry_time: values.entry_time ?? '',
        exit_time: values.exit_time ?? ''
    };

    const response = await page.context().request.post(SAVE_URL, {
        form: formData,
        headers: {
            Referer: MODIFY_URL
        },
        maxRedirects: 0
    });

    const status = response.status();

    if (![302, 303].includes(status)) {
        const responseBody = await response.text();
        throw new Error(
            `Expected HTTP 302 or 303 after POST, received HTTP ${status}.\n` +
            `Response body: ${responseBody.substring(0, 2000)}`
        );
    }

    await page.goto(MODIFY_URL);
    await page.waitForLoadState('domcontentloaded');

    return response;
}

async function submitForSamia(page, values = {}) {
    // Always open the Modify Attendance form first.
    await page.goto(MODIFY_URL);
    await page.waitForLoadState('domcontentloaded');

    // Resolve Samia's employee ID using the actual form.
    const employeeId = await getSamiaEmployeeId(page);

    // Submit using the original application field names.
    return submitAttendance(page, {
        ...values,
        employee_id: employeeId
    });
}

// -----------------------------------------------------------------------------
// Assertion helpers
// -----------------------------------------------------------------------------

async function expectError(page, expectedMessage) {
    const error = page.locator('.attendance-validation-error');

    await expect(
        error,
        'Expected the application to display a validation error.'
    ).toBeVisible();

    const actualMessage = await error.innerText();

    expect(
        actualMessage,
        `Expected error "${expectedMessage}", received "${actualMessage}".`
    ).toContain(expectedMessage);
}

async function expectSuccess(
    page,
    expectedMessage = 'Attendance was recorded successfully'
) {
    const success = page.locator('.attendance-success-message');

    await expect(
        success,
        'Expected the application to display a success message.'
    ).toBeVisible();

    const actualMessage = await success.innerText();

    expect(
        actualMessage,
        `Expected success "${expectedMessage}", received "${actualMessage}".`
    ).toContain(expectedMessage);
}

// -----------------------------------------------------------------------------
// Test suite configuration
// -----------------------------------------------------------------------------

test.describe.configure({ mode: 'serial' });

// MOD-01: Page loading

test('MOD-01 - Load Modify Attendance page and current form fields', async ({ page }) => {
    await loginAsAdmin(page);

    await expect(page.locator('h1')).toHaveText('Modify Attendance');

    await expect(page.locator('#employee_code_input')).toBeVisible();
    await expect(page.locator('#employee_id')).toBeAttached();
    await expect(page.locator('#attendance_date')).toBeVisible();
    await expect(page.locator('#entry_time')).toBeVisible();
    await expect(page.locator('#exit_time')).toBeVisible();

    await expect(
        page.getByRole('button', { name: 'Save Attendance' })
    ).toBeVisible();
});

// MOD-02: GET request to save route

test('MOD-02 - Reject non-POST save request by redirecting to Modify Attendance', async ({ page }) => {
    await loginAsAdmin(page);

    const response = await page.context().request.get(SAVE_URL);

    expect(response.status()).toBe(200);
    expect(response.url()).toContain('action=modify-attendance');
});

// MOD-03: Invalid CSRF token

test('MOD-03 - Reject invalid CSRF token', async ({ page }) => {
    await loginAsAdmin(page);

    await submitForSamia(page, {
        csrf_token: 'invalid-test-token',
        attendance_date: '2027-01-05',
        entry_time: '09:00:00'
    });

    await expectError(
        page,
        'Invalid or expired form token. Please refresh and try again.'
    );
});

// MOD-04: Invalid employee ID

test('MOD-04 - Reject invalid employee ID', async ({ page }) => {
    await loginAsAdmin(page);

    await submitAttendance(page, {
        employee_id: 0,
        attendance_date: '2027-01-05',
        entry_time: '09:00:00'
    });

    await expectError(page, 'Please select a valid employee.');
});

// MOD-05: Invalid date

test('MOD-05 - Reject invalid calendar date', async ({ page }) => {
    await loginAsAdmin(page);

    await submitForSamia(page, {
        attendance_date: '2026-02-30',
        entry_time: '09:00:00'
    });

    await expectError(page, 'Please select a valid attendance date.');
});

// MOD-06: Both times empty

test('MOD-06 - Reject empty entry and exit times', async ({ page }) => {
    await loginAsAdmin(page);

    await submitForSamia(page, {
        attendance_date: '2027-01-05',
        entry_time: '',
        exit_time: ''
    });

    await expectError(page, 'Enter an entry time, an exit time, or both.');
});

// MOD-07: Invalid entry time

test('MOD-07 - Reject invalid entry time', async ({ page }) => {
    await loginAsAdmin(page);

    await submitForSamia(page, {
        attendance_date: '2027-01-05',
        entry_time: '25:70'
    });

    await expectError(page, 'Please enter a valid entry time.');
});

// MOD-08: Invalid exit time

test('MOD-08 - Reject invalid exit time', async ({ page }) => {
    await loginAsAdmin(page);

    await submitForSamia(page, {
        attendance_date: '2027-01-05',
        entry_time: '09:00:00',
        exit_time: '27:90'
    });

    await expectError(page, 'Please enter a valid exit time.');
});

// MOD-09: Exit earlier than entry

test('MOD-09 - Reject exit time earlier than entry time', async ({ page }) => {
    await loginAsAdmin(page);

    await submitForSamia(page, {
        attendance_date: '2027-01-05',
        entry_time: '17:00:00',
        exit_time: '09:00:00'
    });

    await expectError(page, 'Exit time must be later than entry time.');
});

// MOD-10: Create entry and exit together

test('MOD-10 - Create attendance with entry and exit together', async ({ page }) => {
    await loginAsAdmin(page);

    const attendanceDate = await findUnusedAttendanceDate(page);

    await submitForSamia(page, {
        attendance_date: attendanceDate,
        entry_time: '09:00:00',
        exit_time: '18:00:00'
    });

    await expectSuccess(page);
});

// MOD-11: Create entry only

test('MOD-11 - Create attendance with entry only', async ({ page }) => {
    await loginAsAdmin(page);

    const attendanceDate = await findUnusedAttendanceDate(page);

    await submitForSamia(page, {
        attendance_date: attendanceDate,
        entry_time: '09:00:00',
        exit_time: ''
    });

    await expectSuccess(page);
});

// MOD-12: Duplicate attendance

test('MOD-12 - Reject duplicate attendance for the same employee and date', async ({ page }) => {
    await loginAsAdmin(page);

    const attendanceDate = await findUnusedAttendanceDate(page);

    await submitForSamia(page, {
        attendance_date: attendanceDate,
        entry_time: '09:00:00'
    });
    await expectSuccess(page);

    await submitForSamia(page, {
        attendance_date: attendanceDate,
        entry_time: '09:15:00'
    });

    await expectError(
        page,
        'Attendance already exists for this employee on the selected date.'
    );
});

// MOD-13: Nonexistent employee

test('MOD-13 - Reject nonexistent employee', async ({ page }) => {
    await loginAsAdmin(page);

    await submitAttendance(page, {
        employee_id: 99999999,
        attendance_date: '2027-01-05',
        entry_time: '09:00:00'
    });

    await expectError(page, 'The selected employee could not be found.');
});

// MOD-14: No effective schedule — intentionally skipped

test.skip(
    'MOD-14 - Reject employee without an effective work schedule',
    async ({ page }) => {
        await loginAsAdmin(page);
        // Enable only after a safe employee without an effective schedule
        // has been identified. Do not change a real employee's schedule.
    }
);

// MOD-15: Add exit to an open attendance record

test('MOD-15 - Add exit after creating an entry-only attendance record', async ({ page }) => {
    await loginAsAdmin(page);

    const attendanceDate = await findUnusedAttendanceDate(page);

    await submitForSamia(page, {
        attendance_date: attendanceDate,
        entry_time: '09:00:00',
        exit_time: ''
    });
    await expectSuccess(page);

    await submitForSamia(page, {
        attendance_date: attendanceDate,
        entry_time: '',
        exit_time: '18:00:00'
    });
    await expectSuccess(page, 'Exit time recorded successfully');
});

// MOD-16: Exit-only submission without an existing entry

test('MOD-16 - Reject exit-only submission when no existing entry exists', async ({ page }) => {
    await loginAsAdmin(page);

    const attendanceDate = await findUnusedAttendanceDate(page);

    await submitForSamia(page, {
        attendance_date: attendanceDate,
        entry_time: '',
        exit_time: '18:00:00'
    });

    await expectError(
        page,
        'No existing entry record was found for this employee and date.'
    );
});

// MOD-17: Reject second exit

test('MOD-17 - Reject exit when an exit has already been recorded', async ({ page }) => {
    await loginAsAdmin(page);

    const attendanceDate = await findUnusedAttendanceDate(page);

    await submitForSamia(page, {
        attendance_date: attendanceDate,
        entry_time: '09:00:00',
        exit_time: '18:00:00'
    });
    await expectSuccess(page);

    await submitForSamia(page, {
        attendance_date: attendanceDate,
        entry_time: '',
        exit_time: '18:30:00'
    });

    await expectError(
        page,
        'An exit time has already been recorded for this employee and date.'
    );
});

// MOD-18: Exit earlier than an existing entry

test('MOD-18 - Reject exit earlier than the recorded entry time', async ({ page }) => {
    await loginAsAdmin(page);

    const attendanceDate = await findUnusedAttendanceDate(page);

    await submitForSamia(page, {
        attendance_date: attendanceDate,
        entry_time: '09:00:00',
        exit_time: ''
    });
    await expectSuccess(page);

    await submitForSamia(page, {
        attendance_date: attendanceDate,
        entry_time: '',
        exit_time: '08:00:00'
    });

    await expectError(
        page,
        'Exit time must be later than the recorded entry time.'
    );
});

// MOD-19: Another complete attendance creation

test('MOD-19 - Verify complete attendance creation succeeds', async ({ page }) => {
    await loginAsAdmin(page);

    const attendanceDate = await findUnusedAttendanceDate(page);

    await submitForSamia(page, {
        attendance_date: attendanceDate,
        entry_time: '09:00:00',
        exit_time: '18:00:00'
    });

    await expectSuccess(page);
});

// MOD-20: Repeated creation is rejected, not updated

test('MOD-20 - Verify repeated creation is rejected as a duplicate', async ({ page }) => {
    await loginAsAdmin(page);

    const attendanceDate = await findUnusedAttendanceDate(page);

    await submitForSamia(page, {
        attendance_date: attendanceDate,
        entry_time: '09:00:00',
        exit_time: ''
    });
    await expectSuccess(page);

    await submitForSamia(page, {
        attendance_date: attendanceDate,
        entry_time: '09:10:00',
        exit_time: '18:00:00'
    });

    await expectError(
        page,
        'Attendance already exists for this employee on the selected date.'
    );
});

// MOD-21: Add exit after entry-only record

test('MOD-21 - Verify exit can be added after an entry-only record', async ({ page }) => {
    await loginAsAdmin(page);

    const attendanceDate = await findUnusedAttendanceDate(page);

    await submitForSamia(page, {
        attendance_date: attendanceDate,
        entry_time: '09:00:00',
        exit_time: ''
    });
    await expectSuccess(page);

    await submitForSamia(page, {
        attendance_date: attendanceDate,
        entry_time: '',
        exit_time: '18:00:00'
    });

    await expectSuccess(page, 'Exit time recorded successfully');
});

// MOD-22: Exit-only submission with no existing entry is rejected

test('MOD-22 - Verify exit-only submission requires an existing entry record', async ({ page }) => {
    await loginAsAdmin(page);

    const attendanceDate = await findUnusedAttendanceDate(page);

    await submitForSamia(page, {
        attendance_date: attendanceDate,
        entry_time: '',
        exit_time: '18:00:00'
    });

    await expectError(
        page,
        'No existing entry record was found for this employee and date.'
    );
});