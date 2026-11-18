
const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const manifestPath = path.join(
    __dirname,
    '..',
    '.employee-salary-details-test-data.json'
);

const helperPath = path.join(
    __dirname,
    '..',
    'prepare-employee-salary-details-data.php'
);

const baseUrl = 'http://localhost/HRIS-Management-System/';
const publicUrl = `${baseUrl}public/index.php`;

let fixture;

function runHelper(mode) {
    execFileSync(
        'php',
        [helperPath, mode],
        { stdio: 'inherit' }
    );
}

function loadManifest() {
    if (!fs.existsSync(manifestPath)) {
        throw new Error(
            'Employee Salary Details fixture manifest is missing.'
        );
    }

    const data = JSON.parse(
        fs.readFileSync(manifestPath, 'utf8')
    );

    if (data.database !== 'hris_management_system_test') {
        throw new Error(
            'Safety stop: fixture does not belong to the test database.'
        );
    }

    if (
        !data.employees?.employee ||
        !data.employees?.other ||
        !data.employees?.empty
    ) {
        throw new Error(
            'Employee Salary Details fixture accounts are incomplete.'
        );
    }

    return data;
}

/*
 * Same isolated routing strategy used by the existing
 * Salary Management and Leave Application black-box suites.
 */
async function installTestRouting(context) {
    await context.route(
        '**/HRIS-Management-System/public/index.php**',
        async route => {
            const originalUrl = new URL(route.request().url());

            const testUrl = new URL(originalUrl);
            testUrl.pathname =
                '/HRIS-Management-System/blackbox-tests/test-entry.php';

            if (route.request().method() === 'POST') {
                const response = await route.fetch({
                    url: testUrl.toString(),
                    maxRedirects: 0
                });

                const headers = {
                    ...response.headers()
                };

                if (headers.location) {
                    const redirectUrl = new URL(
                        headers.location,
                        originalUrl.origin
                    );

                    if (
                        redirectUrl.pathname ===
                        '/HRIS-Management-System/public/index.php'
                    ) {
                        redirectUrl.pathname =
                            '/HRIS-Management-System/blackbox-tests/test-entry.php';

                        headers.location =
                            redirectUrl.pathname + redirectUrl.search;
                    }
                }

                await route.fulfill({
                    response,
                    headers
                });

                return;
            }

            await route.continue({
                url: testUrl.toString()
            });
        }
    );
}

async function login(page, account) {
    await page.goto(`${publicUrl}?action=login`);

    await page.locator('input[name="username"]').fill(
        account.username
    );

    await page.locator('input[name="password"]').fill(
        account.password
    );

    await page.locator('button[type="submit"]').click();

    await page.waitForLoadState('networkidle');
}

async function logout(page) {
    await page.goto(`${publicUrl}?action=logout`);
    await page.waitForLoadState('networkidle');
}

async function openSalaryDetails(page, query = '') {
    await page.goto(
        `${publicUrl}?action=employee-salary-details${query}`
    );

    await expect(
        page.locator('h1')
    ).toHaveText('Salary Details');
}

function money(value) {
    return '৳' + Number(value).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

async function expectTestDatabase(request) {
    const response = await request.get(
        `${baseUrl}blackbox-tests/db-guard.php`
    );

    expect(
        response.ok(),
        'Test database guard must respond successfully'
    ).toBeTruthy();

    const guard = await response.json();

    expect(guard.ok).toBe(true);

    expect(guard.database).toBe(
        'hris_management_system_test'
    );
}

test.describe(
    'Employee Salary Details — Automated Black-Box Tests',
    () => {

        test.beforeAll(() => {
            runHelper('prepare');
            fixture = loadManifest();
        });

        test.afterAll(() => {
            try {
                runHelper('cleanup');
            } catch (error) {
                console.error(
                    'Employee Salary Details cleanup failed:',
                    error.message
                );

                throw error;
            }
        });

        test.beforeEach(async ({ page, context, request }) => {
            await expectTestDatabase(request);

            await installTestRouting(context);

            await login(
                page,
                fixture.employees.employee
            );

            await openSalaryDetails(page);
        });

        test(
            'BB-ESD-01 — Employee can open Salary Details',
            async ({ page }) => {
                await expect(
                    page.locator('h1')
                ).toHaveText('Salary Details');

                await expect(
                    page.getByText('EMPLOYEE PAYROLL STATEMENT')
                ).toBeVisible();

                await expect(
                    page.getByRole('heading', {
                        name: 'Salary Summary'
                    })
                ).toBeVisible();

                await expect(
                    page.getByRole('heading', {
                        name: 'Attendance & Leave Summary'
                    })
                ).toBeVisible();

                await expect(
                    page.getByRole('heading', {
                        name: 'Deduction Breakdown'
                    })
                ).toBeVisible();

                await expect(
                    page.getByRole('heading', {
                        name: 'Daily Attendance Ledger'
                    })
                ).toBeVisible();
            }
        );

        test(
            'BB-ESD-02 — Correct employee identity is displayed',
            async ({ page }) => {
                await expect(
                    page.locator('.salary-employee-name h2')
                ).toHaveText(
                    fixture.employees.employee.fullName
                );

                await expect(
                    page.locator('.salary-employee-meta')
                ).toContainText(
                    fixture.employees.employee.employeeCode
                );

                await expect(
                    page.locator('.salary-employee-meta')
                ).toContainText('Joined');
            }
        );

        test(
            'BB-ESD-03 — Current payroll month and provisional status are displayed',
            async ({ page }) => {
                const currentMonth = new Date().toLocaleDateString(
                    'en-US',
                    {
                        month: 'long',
                        year: 'numeric'
                    }
                );

                await expect(
                    page.locator('.salary-period-info')
                ).toContainText(currentMonth);

                await expect(
                    page.locator('.salary-period-badge')
                ).toHaveText('PROVISIONAL');

                await expect(
                    page.locator('.salary-period-info')
                ).toContainText('Calculated through');
            }
        );

        test(
            'BB-ESD-04 — Salary summary displays correct calculated amounts',
            async ({ page }) => {
                const values = page.locator(
                    '.salary-metric-content strong'
                );

                await expect(values).toHaveCount(4);

                await expect(values.nth(0)).toHaveText(
                    money(fixture.employees.employee.monthlySalary)
                );

                await expect(values.nth(1)).toHaveText(
                    money(fixture.dailySalary)
                );

                await expect(values.nth(2)).toHaveText(
                    money(fixture.totalDeductions)
                );

                await expect(values.nth(3)).toHaveText(
                    money(fixture.payableSalary)
                );
            }
        );

        test(
            'BB-ESD-05 — Attendance and leave summary displays correct metrics',
            async ({ page }) => {
                const values = page.locator(
                    '.salary-stat-info strong'
                );

                await expect(values).toHaveCount(8);

                const expectedValues = [
                    fixture.calendarDays,
                    fixture.eligibleDays,
                    fixture.paidFridays,
                    fixture.paidHolidays,
                    fixture.fullDayLeaves,
                    0,
                    fixture.incompleteHalfDayLeaves,
                    fixture.absentDays
                ];

                for (let i = 0; i < expectedValues.length; i++) {
                    await expect(values.nth(i)).toHaveText(
                        String(expectedValues[i])
                    );
                }

                await expect(
                    page.locator('.salary-attendance-group')
                ).toContainText('Calendar Days');

                await expect(
                    page.locator('.salary-attendance-group')
                ).toContainText('Eligible Working Days');

                await expect(
                    page.locator('.salary-attendance-group')
                ).toContainText('Paid Fridays');

                await expect(
                    page.locator('.salary-attendance-group')
                ).toContainText('Paid Holidays');
            }
        );

        test(
            'BB-ESD-06 — Deduction breakdown displays correct deductions',
            async ({ page }) => {
                const table = page.locator(
                    '.salary-deduction-table'
                );

                await expect(table).toContainText(
                    'Absence Deduction'
                );

                await expect(table).toContainText(
                    'Incomplete Half-Day Leave'
                );

                await expect(table).toContainText(
                    'Late Entry & Early Exit'
                );

                await expect(table).toContainText(
                    `${fixture.absentDays} days`
                );

                await expect(table).toContainText(
                    `${fixture.incompleteHalfDayLeaves} days`
                );

                await expect(table).toContainText(
                    `${fixture.lateOccurrences} late`
                );

                await expect(table).toContainText(
                    `${fixture.earlyExitOccurrences} early exits`
                );

                await expect(table).toContainText(
                    money(fixture.absenceDeduction)
                );

                await expect(table).toContainText(
                    money(fixture.halfDayDeduction)
                );

                await expect(table).toContainText(
                    money(fixture.lateEarlyDeduction)
                );

                await expect(table).toContainText(
                    money(fixture.totalDeductions)
                );

                await expect(
                    page.locator('.salary-rule-note')
                ).toContainText(
                    'first three combined late-entry and early-exit occurrences are free'
                );
            }
        );

        test(
            'BB-ESD-07 — Daily Attendance Ledger displays attendance records',
            async ({ page }) => {
                const table = page.locator(
                    '.salary-ledger-table'
                );

                await expect(
                    table.locator('thead')
                ).toContainText('Date');

                await expect(
                    table.locator('thead')
                ).toContainText('Attendance');

                await expect(
                    table.locator('thead')
                ).toContainText('Entry Time');

                await expect(
                    table.locator('thead')
                ).toContainText('Entry Status');

                await expect(
                    table.locator('thead')
                ).toContainText('Exit Time');

                await expect(
                    table.locator('thead')
                ).toContainText('Exit Status');

                await expect(
                    table.locator('thead')
                ).toContainText('Deduction');

                await expect(
                    table.locator('thead')
                ).toContainText('Remarks');

                const rows = table.locator('tbody tr');

                // One ledger row is generated for every date
                // from the beginning of the month through today.
                await expect(rows).toHaveCount(
                    new Date().getDate()
                );

                await expect(table).toContainText('Absent');
                await expect(table).toContainText('Full Day Leave');
                await expect(table).toContainText(
                    'Incomplete Half Day Leave'
                );
                await expect(table).toContainText('Present');
                await expect(table).toContainText('In Progress');
            }
        );

        test(
            'BB-ESD-08 — Empty ledger state is displayed when there are no eligible daily records',
            async ({ page }) => {
                await logout(page);

                await login(
                    page,
                    fixture.employees.empty
                );

                await openSalaryDetails(page);

                await expect(
                    page.locator('.salary-ledger-table')
                ).toContainText(
                    'No daily calculation records available.'
                );

                await expect(
                    page.locator('.salary-record-count')
                ).toContainText('0 Records');
            }
        );

        test(
            'BB-ESD-09 — Download As PDF invokes browser print functionality',
            async ({ page }) => {
                await page.evaluate(() => {
                    window.__salaryPrintCalled = false;

                    window.print = () => {
                        window.__salaryPrintCalled = true;
                    };
                });

                await page.getByRole('button', {
                    name: /Download As PDF/
                }).click();

                await expect.poll(
                    () => page.evaluate(
                        () => window.__salaryPrintCalled
                    )
                ).toBe(true);

                await expect(
                    page.locator('#salary-long-pdf-style')
                ).toBeAttached();
            }
        );

        test(
            'BB-ESD-10 — Back to Dashboard navigates to employee dashboard',
            async ({ page }) => {
                await page.getByRole('link', {
                    name: /Back to Dashboard/
                }).click();

                await expect(page).toHaveURL(
                    /action=employee-dashboard/
                );

                await expect(
                    page.locator('h1')
                ).toContainText('Welcome back');
            }
        );

        test(
            'BB-ESD-11 — Unauthenticated visitor cannot access Salary Details',
            async ({ page }) => {
                await logout(page);

                await page.goto(
                    `${publicUrl}?action=employee-salary-details`
                );

                await expect(
                    page.locator('input[name="username"]')
                ).toBeVisible();

                await expect(
                    page.locator('input[name="password"]')
                ).toBeVisible();

                await expect(
                    page.locator('h1')
                ).not.toHaveText('Salary Details');
            }
        );

        test(
            'BB-ESD-12 — Tampering with employee and salary IDs does not expose another employee salary',
            async ({ page }) => {
                const otherEmployee =
                    fixture.employees.other;

                await openSalaryDetails(
                    page,
                    `&employee_id=${otherEmployee.employeeId}` +
                    `&salary_id=${fixture.otherSalaryId}`
                );

                await expect(
                    page.locator('.salary-employee-name h2')
                ).toHaveText(
                    fixture.employees.employee.fullName
                );

                await expect(
                    page.locator('.salary-employee-name h2')
                ).not.toContainText(
                    otherEmployee.fullName
                );

                await expect(
                    page.locator('.salary-employee-meta')
                ).toContainText(
                    fixture.employees.employee.employeeCode
                );

                await expect(
                    page.locator('body')
                ).not.toContainText(
                    otherEmployee.employeeCode
                );
            }
        );

    }
);
