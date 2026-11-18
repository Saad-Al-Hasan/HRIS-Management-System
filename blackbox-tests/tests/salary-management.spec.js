const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const manifestPath = path.join(
    __dirname,
    '..',
    '.salary-management-test-data.json'
);

const helperPath = path.join(
    __dirname,
    '..',
    'prepare-salary-management-data.php'
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
        throw new Error('Salary Management fixture manifest is missing.');
    }

    const data = JSON.parse(
        fs.readFileSync(manifestPath, 'utf8')
    );

    if (data.database !== 'hris_management_system_test') {
        throw new Error(
            'Safety stop: Salary fixture does not belong to the test database.'
        );
    }

    return data;
}

/*
 * Same isolated routing strategy verified by the Leave Application tests.
 *
 * POST:
 * - Forward to test-entry.php.
 * - Preserve the application response.
 * - Rewrite the redirect Location to test-entry.php.
 *
 * GET:
 * - Forward directly to test-entry.php.
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
    await page.goto(
        `${publicUrl}?action=login`
    );

    await page.locator('input[name="username"]').fill(
        account.username
    );

    await page.locator('input[name="password"]').fill(
        account.password
    );

    await page.locator('button[type="submit"]').click();

    await page.waitForLoadState('networkidle');
}

async function openSalaryManagement(page, query = '') {
    await page.goto(
        `${publicUrl}?action=salary-management${query}`
    );

    await expect(page.locator('h1')).toHaveText(
        'Salary Management'
    );

    await expect(
        page.getByRole('heading', { name: 'Payroll Summary' })
    ).toBeVisible();

    await expect(
        page.getByRole('heading', { name: 'Calculate Monthly Salary' })
    ).toBeVisible();

    await expect(
        page.getByRole('heading', { name: 'Monthly Salary Records' })
    ).toBeVisible();
}

async function calculateSalary(page, employeeId, month) {
    await page.locator('#calculate_employee_id').selectOption(
        String(employeeId)
    );

    await page.locator('#calculate_month').fill(month);

    page.once('dialog', async dialog => {
        await dialog.accept();
    });

    const postResponsePromise = page.waitForResponse(response => {
        const request = response.request();
        const url = new URL(response.url());

        return (
            request.method() === 'POST' &&
            url.searchParams.get('action') === 'salary-calculate'
        );
    });

    await page.getByRole('button', {
        name: /Calculate Salary/
    }).click();

    const postResponse = await postResponsePromise;

    expect(postResponse.status()).toBe(302);

    await expect(page.locator('h1')).toHaveText(
        'Salary Management'
    );

    return postResponse;
}

async function expectSuccess(page) {
    await expect(
        page.locator('.salary-feedback-success')
    ).toBeVisible();

    await expect(
        page.locator('.salary-feedback-success')
    ).toContainText(
        'Monthly salary calculated and saved successfully.'
    );
}

async function expectError(page, message) {
    await expect(
        page.locator('.salary-feedback-error')
    ).toBeVisible();

    await expect(
        page.locator('.salary-feedback-error')
    ).toContainText(message);
}

test.describe(
    'Salary Management — Automated Black-Box Tests',
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
                    'Salary fixture cleanup failed:',
                    error.message
                );
                throw error;
            }
        });

        test.beforeEach(async ({ page, context, request }) => {
            /*
             * Fail closed unless the web guard confirms the test DB.
             */
            const guardResponse = await request.get(
                `${baseUrl}blackbox-tests/db-guard.php`
            );

            expect(
                guardResponse.ok(),
                'Test database guard must respond successfully'
            ).toBeTruthy();

            const guard = await guardResponse.json();

            expect(guard.ok).toBe(true);

            expect(guard.database).toBe(
                'hris_management_system_test'
            );

            await installTestRouting(context);

            await login(page, fixture.admin);

            await openSalaryManagement(page);
        });

        test(
            'BB-SAL-01 — Administrator can open Salary Management',
            async ({ page }) => {
                await expect(page.locator('h1')).toHaveText(
                    'Salary Management'
                );

                await expect(
                    page.getByText('PAYROLL MANAGEMENT')
                ).toBeVisible();

                await expect(
                    page.getByText('FINANCIAL OVERVIEW')
                ).toBeVisible();
            }
        );

        test(
            'BB-SAL-02 — Salary calculation form displays required controls',
            async ({ page }) => {
                await expect(
                    page.locator('#calculate_employee_id')
                ).toBeVisible();

                await expect(
                    page.locator('#calculate_employee_id')
                ).toHaveAttribute('required', '');

                await expect(
                    page.locator('#calculate_month')
                ).toBeVisible();

                await expect(
                    page.locator('#calculate_month')
                ).toHaveAttribute('required', '');

                await expect(
                    page.locator('input[name="csrf_token"]')
                ).toHaveValue(/.+/);

                await expect(
                    page.getByRole('button', {
                        name: /Calculate Salary/
                    })
                ).toBeVisible();
            }
        );

        test(
            'BB-SAL-03 — Salary month defaults to the current month',
            async ({ page }) => {
                await expect(
                    page.locator('#calculate_month')
                ).toHaveValue(fixture.salaryMonth);

                await expect(
                    page.locator('#calculate_month')
                ).toHaveAttribute(
                    'max',
                    fixture.salaryMonth
                );
            }
        );

        test(
            'BB-SAL-04 — Employee dropdown displays the salary test employee',
            async ({ page }) => {
                const option = page.locator(
                    `#calculate_employee_id option[value="${fixture.employeeId}"]`
                );

                await expect(option).toHaveCount(1);

                await expect(option).toContainText(
                    fixture.employeeCode
                );

                await expect(
                    page.locator('#calculate_employee_id option').first()
                ).toHaveText('Choose an employee');
            }
        );

        test(
            'BB-SAL-05 — Payroll summary displays correct totals for the selected employee',
            async ({ page }) => {
                await openSalaryManagement(
                    page,
                    `&month=${fixture.salaryMonth}&employee_id=${fixture.employeeId}`
                );

                const values = page.locator(
                    '.salary-summary-value'
                );

                await expect(values.nth(0)).toHaveText('1');

                await expect(values.nth(1)).toHaveText(
                    '৳30,000.00'
                );

                await expect(values.nth(2)).toHaveText(
                    '৳1,234.56'
                );

                await expect(values.nth(3)).toHaveText(
                    '৳28,765.44'
                );
            }
        );

        test(
            'BB-SAL-06 — Filter by employee displays only that employee salary record',
            async ({ page }) => {
                await openSalaryManagement(
                    page,
                    `&month=${fixture.salaryMonth}&employee_id=${fixture.employeeId}`
                );

                await expect(
                    page.locator('#filter_employee_id')
                ).toHaveValue(String(fixture.employeeId));

                const table = page.locator(
                    '.salary-management-table tbody'
                );

                await expect(table).toContainText(
                    fixture.employeeCode
                );

                await expect(table.locator('tr')).toHaveCount(1);
            }
        );

        test(
            'BB-SAL-07 — Month filter displays an empty state when no records match',
            async ({ page }) => {
                await openSalaryManagement(
                    page,
                    '&month=2199-01'
                );

                await expect(
                    page.locator('#filter_month')
                ).toHaveValue('2199-01');

                await expect(
                    page.getByText('No Salary Records Found')
                ).toBeVisible();

                await expect(
                    page.locator('.salary-summary-value').nth(0)
                ).toHaveText('0');
            }
        );

        test(
            'BB-SAL-08 — Clear Filters resets the selected filters',
            async ({ page }) => {
                await openSalaryManagement(
                    page,
                    `&month=2199-01&employee_id=${fixture.employeeId}`
                );

                await page.getByRole('link', {
                    name: 'Clear Filters'
                }).click();

                await expect(page.locator('h1')).toHaveText(
                    'Salary Management'
                );

                await expect(
                    page.locator('#filter_month')
                ).toHaveValue(fixture.salaryMonth);

                await expect(
                    page.locator('#filter_employee_id')
                ).toHaveValue('');

                await expect(
                    page.locator('.salary-management-table tbody')
                ).toContainText(fixture.employeeCode);
            }
        );

        test(
            'BB-SAL-09 — Valid salary calculation succeeds',
            async ({ page }) => {
                await calculateSalary(
                    page,
                    fixture.employeeId,
                    fixture.salaryMonth
                );

                await expectSuccess(page);

                await expect(
                    page.locator('.salary-management-table tbody')
                ).toContainText(fixture.employeeCode);
            }
        );

        test(
            'BB-SAL-10 — Recalculating the same employee and month updates the existing record',
            async ({ page }) => {
                await calculateSalary(
                    page,
                    fixture.employeeId,
                    fixture.salaryMonth
                );

                await expectSuccess(page);

                await calculateSalary(
                    page,
                    fixture.employeeId,
                    fixture.salaryMonth
                );

                await expectSuccess(page);

                await openSalaryManagement(
                    page,
                    `&month=${fixture.salaryMonth}&employee_id=${fixture.employeeId}`
                );

                const rows = page.locator(
                    '.salary-management-table tbody tr'
                );

                await expect(rows).toHaveCount(1);

                await expect(rows.first()).toContainText(
                    fixture.employeeCode
                );
            }
        );

        test(
            'BB-SAL-11 — Invalid employee ID is rejected',
            async ({ page }) => {
                await page.locator(
                    '#calculate_employee_id'
                ).evaluate(select => {
                    select.add(
                        new Option(
                            'Invalid test employee',
                            '999999999'
                        )
                    );
                });

                await page.locator(
                    '#calculate_employee_id'
                ).selectOption('999999999');

                await page.locator('#calculate_month').fill(
                    fixture.salaryMonth
                );

                page.once('dialog', dialog => dialog.accept());

                await page.getByRole('button', {
                    name: /Calculate Salary/
                }).click();

                await expectError(
                    page,
                    'Salary calculation failed.'
                );
            }
        );

        test(
            'BB-SAL-12 — Future salary month is rejected',
            async ({ page }) => {
                await page.locator(
                    '#calculate_employee_id'
                ).selectOption(String(fixture.employeeId));

                const monthInput = page.locator(
                    '#calculate_month'
                );

                await monthInput.evaluate(input => {
                    input.removeAttribute('max');
                });

                await monthInput.fill('2199-01');

                page.once('dialog', dialog => dialog.accept());

                await page.getByRole('button', {
                    name: /Calculate Salary/
                }).click();

                await expectError(
                    page,
                    'Salary calculation failed.'
                );
            }
        );

        test(
            'BB-SAL-13 — Invalid CSRF token is rejected',
            async ({ page }) => {
                await page.locator(
                    '#calculate_employee_id'
                ).selectOption(String(fixture.employeeId));

                await page.locator(
                    'input[name="csrf_token"]'
                ).evaluate(input => {
                    input.value = 'invalid-blackbox-csrf-token';
                });

                page.once('dialog', dialog => dialog.accept());

                await page.getByRole('button', {
                    name: /Calculate Salary/
                }).click();

                await expectError(
                    page,
                    'Invalid security token.'
                );
            }
        );

        test(
            'BB-SAL-14 — Employee cannot access administrator Salary Management',
            async ({ page }) => {
                await page.goto(
                    `${publicUrl}?action=logout`
                );

                await login(page, fixture.sharedEmployee);

                await page.goto(
                    `${publicUrl}?action=salary-management`
                );

                await expect(
                    page.locator('body')
                ).toContainText('403');

                await expect(
                    page.locator('h1')
                ).toHaveCount(0);
            }
        );
    }
);