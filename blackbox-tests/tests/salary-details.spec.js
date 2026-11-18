const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const manifestPath = path.join(
    __dirname,
    '..',
    '.salary-details-test-data.json'
);

const helperPath = path.join(
    __dirname,
    '..',
    'prepare-salary-details-data.php'
);

const baseUrl = 'http://localhost/HRIS-Management-System/';
const publicUrl = `${baseUrl}public/index.php`;
const testEntryUrl = `${baseUrl}blackbox-tests/test-entry.php`;

let fixture;

function runHelper(mode) {
    execFileSync('php', [helperPath, mode], { stdio: 'inherit' });
}

function loadManifest() {
    if (!fs.existsSync(manifestPath)) {
        throw new Error('Salary Details fixture manifest is missing.');
    }

    const data = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));

    if (data.database !== 'hris_management_system_test') {
        throw new Error(
            'Safety stop: Salary Details fixture is not in the test database.'
        );
    }

    return data;
}

/*
 * Route application page requests through test-entry.php, exactly like
 * the existing Salary Management browser suite.
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

                const headers = { ...response.headers() };

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

                await route.fulfill({ response, headers });
                return;
            }

            await route.continue({ url: testUrl.toString() });
        }
    );
}

async function verifyTestDatabase(request) {
    const response = await request.get(
        `${baseUrl}blackbox-tests/db-guard.php`
    );

    expect(
        response.ok(),
        'The test database guard must respond successfully.'
    ).toBeTruthy();

    const guard = await response.json();

    expect(guard.ok).toBe(true);
    expect(guard.database).toBe('hris_management_system_test');
}

async function login(page, account) {
    await page.goto(`${publicUrl}?action=login`);

    await page.locator('input[name="username"]').fill(account.username);
    await page.locator('input[name="password"]').fill(account.password);
    await page.locator('button[type="submit"]').click();

    await page.waitForLoadState('networkidle');
}

async function openSalaryDetails(page, salaryId = fixture.salaryId) {
    const response = await page.goto(
        `${publicUrl}?action=salary-details&id=${encodeURIComponent(salaryId)}`
    );

    return response;
}

function money(value) {
    return `৳${Number(value).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })}`;
}

test.describe('Salary Details — Automated Black-Box Tests', () => {
    test.beforeAll(() => {
        runHelper('prepare');
        fixture = loadManifest();
    });

    test.afterAll(() => {
        try {
            runHelper('cleanup');
        } catch (error) {
            console.error(
                'Salary Details fixture cleanup failed:',
                error.message
            );
            throw error;
        }
    });

    test.beforeEach(async ({ page, context, request }) => {
        await verifyTestDatabase(request);
        await installTestRouting(context);

        await page.addInitScript(() => {
            window.__salaryPrintCalls = 0;
            window.print = () => {
                window.__salaryPrintCalls += 1;
            };
        });

        await login(page, fixture.admin);
        await openSalaryDetails(page);

        await expect(page.locator('h1')).toHaveText('Salary Details');
    });

    test(
        'BB-SD-01 — Administrator can open the Salary Details page',
        async ({ page }) => {
            await expect(
                page.getByText('PAYROLL MANAGEMENT', { exact: true })
            ).toBeVisible();

            await expect(
                page.getByText(
                    'Complete salary statement and monthly attendance breakdown.'
                )
            ).toBeVisible();

            await expect(
                page.locator('.salary-details-page')
            ).toBeVisible();
        }
    );

    test(
        'BB-SD-02 — Employee identity and joining information are displayed',
        async ({ page }) => {
            await expect(
                page.locator('.salary-employee-name h2')
            ).toHaveText(fixture.fullName);

            await expect(
                page.locator('.salary-employee-meta')
            ).toContainText(fixture.employeeCode);

            await expect(
                page.locator('.salary-employee-meta')
            ).toContainText(`Joined ${fixture.joiningDateLabel}`);

            await expect(
                page.getByText('EMPLOYEE PAYROLL STATEMENT')
            ).toBeVisible();
        }
    );

    test(
        'BB-SD-03 — Payroll month and provisional status are displayed',
        async ({ page }) => {
            await expect(
                page.locator('.salary-period-info')
            ).toContainText(fixture.salaryMonthLabel);

            await expect(
                page.locator('.salary-period-badge')
            ).toHaveText('PROVISIONAL');

            await expect(
                page.locator('.salary-period-info small')
            ).toContainText(
                `Calculated through ${fixture.calculatedThroughLabel}`
            );
        }
    );

    test(
        'BB-SD-04 — Basic salary, daily salary, deductions and net salary are correct',
        async ({ page }) => {
            const metrics = page.locator('.salary-metric-content strong');

            await expect(metrics).toHaveCount(4);
            await expect(metrics.nth(0)).toHaveText(
                money(fixture.monthlySalary)
            );
            await expect(metrics.nth(1)).toHaveText(
                money(fixture.dailySalary)
            );
            await expect(metrics.nth(2)).toHaveText(
                money(fixture.totalDeductions)
            );
            await expect(metrics.nth(3)).toHaveText(
                money(fixture.finalSalary)
            );
        }
    );

    test(
        'BB-SD-05 — Attendance and leave summary displays all eight metrics',
        async ({ page }) => {
            const stats = page.locator('.salary-attendance-item');

            await expect(stats).toHaveCount(8);

            await expect(stats.nth(0)).toContainText('Calendar Days');
            await expect(stats.nth(0)).toContainText(
                String(fixture.calendarDays)
            );

            await expect(stats.nth(1)).toContainText('Eligible Working Days');
            await expect(stats.nth(1)).toContainText(
                String(fixture.eligibleDays)
            );

            await expect(stats.nth(2)).toContainText('Paid Fridays');
            await expect(stats.nth(3)).toContainText('Paid Holidays');
            await expect(stats.nth(4)).toContainText('Full-Day Leaves');
            await expect(stats.nth(5)).toContainText('Valid Half-Day Leaves');

            await expect(stats.nth(6)).toContainText(
                'Incomplete Half-Day Leaves'
            );
            await expect(stats.nth(6)).toContainText(
                String(fixture.incompleteHalfDayLeaves)
            );

            await expect(stats.nth(7)).toContainText('Absent Days');
            await expect(stats.nth(7)).toContainText(
                String(fixture.absentDays)
            );
        }
    );

    test(
        'BB-SD-06 — Deduction breakdown displays correct occurrence counts and amounts',
        async ({ page }) => {
            const table = page.locator('.salary-deduction-table');

            await expect(table).toContainText('Absence Deduction');
            await expect(table).toContainText('Incomplete Half-Day Leave');
            await expect(table).toContainText('Late Entry & Early Exit');

            await expect(table).toContainText(
                `${fixture.absentDays} days`
            );
            await expect(table).toContainText(
                `${fixture.incompleteHalfDayLeaves} days`
            );
            await expect(table).toContainText(
                `${fixture.lateOccurrences} late +`
            );
            await expect(table).toContainText(
                `${fixture.earlyExitOccurrences} early exits`
            );

            await expect(table).toContainText(
                money(fixture.totalDeductions)
            );

            await expect(
                page.locator('.salary-rule-note')
            ).toContainText(
                'The first three combined late-entry and early-exit occurrences are free.'
            );
        }
    );

    test(
        'BB-SD-07 — Daily Attendance Ledger displays fixture attendance records',
        async ({ page }) => {
            const table = page.locator('.salary-ledger-table');

            await expect(
                page.locator('.salary-record-count')
            ).toHaveText('2 Records');

            await expect(table).toContainText('Present');
            await expect(table).toContainText('Absent');
            await expect(table).toContainText(
                'Black-box fixture: regular attendance'
            );
            await expect(table).toContainText(
                'Black-box fixture: unapproved absence'
            );

            await expect(
                table.locator('tbody tr')
            ).toHaveCount(2);
        }
    );

    test(
        'BB-SD-08 — Download As PDF button invokes the browser print workflow',
        async ({ page }) => {
            const downloadButton = page.getByRole('button', {
                name: /Download As PDF/
            });

            await expect(downloadButton).toBeVisible();
            await downloadButton.click();

            await expect.poll(
                () => page.evaluate(() => window.__salaryPrintCalls)
            ).toBe(1);
        }
    );

    test(
        'BB-SD-09 — Back to Salary Management navigation works',
        async ({ page }) => {
            await page.getByRole('link', {
                name: /Back to Salary Management/
            }).click();

            await expect(page.locator('h1')).toHaveText(
                'Salary Management'
            );

            await expect(
                page.getByRole('heading', {
                    name: 'Monthly Salary Records'
                })
            ).toBeVisible();
        }
    );

    test(
        'BB-SD-10 — Invalid salary ID returns HTTP 404',
        async ({ page }) => {
            const response = await openSalaryDetails(page, 'not-a-number');

            expect(response).not.toBeNull();
            expect(response.status()).toBe(404);

            await expect(page.locator('body')).toContainText(
                'Salary record not found.'
            );
        }
    );

    test(
        'BB-SD-11 — Nonexistent salary ID returns HTTP 404',
        async ({ page }) => {
            const response = await openSalaryDetails(page, '999999999');

            expect(response).not.toBeNull();
            expect(response.status()).toBe(404);

            await expect(page.locator('body')).toContainText(
                'Salary record not found.'
            );
        }
    );

    test(
        'BB-SD-12 — Employee cannot access administrator Salary Details',
        async ({ page }) => {
            await page.goto(`${publicUrl}?action=logout`);
            await login(page, fixture.sharedEmployee);

            const response = await openSalaryDetails(page);

            expect(response).not.toBeNull();
            expect(response.status()).toBe(403);

            await expect(page.locator('h1')).toHaveCount(0);
        }
    );
});
