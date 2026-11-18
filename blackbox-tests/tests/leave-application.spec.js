
const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const manifestPath = path.join(
    __dirname,
    '..',
    '.leave-application-test-data.json'
);

const helperPath = path.join(
    __dirname,
    '..',
    'prepare-leave-application-data.php'
);

const appUrl =
    'http://localhost/HRIS-Management-System/public/index.php';

let reasonPrefix = '';

function loadManifest() {
    if (!fs.existsSync(manifestPath)) {
        throw new Error('Leave Application test manifest is missing.');
    }

    const data = JSON.parse(
        fs.readFileSync(manifestPath, 'utf8')
    );

    if (data.database !== 'hris_management_system_test') {
        throw new Error(
            'Safety stop: Leave Application manifest is not for the test database.'
        );
    }

    return data;
}

function runHelper(mode) {
    execFileSync(
        'php',
        [helperPath, mode],
        { stdio: 'inherit' }
    );
}

/**
 * Route application requests through the test-only entrypoint.
 * The browser continues using the original public/index.php URL.
 */
async function installTestRouting(context) {
    await context.route(
        '**/HRIS-Management-System/public/index.php**',
        async route => {
            const originalUrl = new URL(route.request().url());

            const testUrl = new URL(originalUrl);
            testUrl.pathname =
                '/HRIS-Management-System/blackbox-tests/test-entry.php';

            // Forward POST requests to the test-only entrypoint.
            if (route.request().method() === 'POST') {
                const response = await route.fetch({
                    url: testUrl.toString(),
                    maxRedirects: 0
                });

                const headers = {
                    ...response.headers()
                };

                // Rewrite the application's redirect to the test entrypoint.
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

            // Forward normal GET requests to the test-only entrypoint.
            await route.continue({
                url: testUrl.toString()
            });
        }
    );
}

async function loginEmployee(page, employee) {
    await page.goto('public/index.php?action=login');

    await page.locator('input[name="username"]').fill(
        employee.username
    );

    await page.locator('input[name="password"]').fill(
        employee.password
    );

    await page.locator('button[type="submit"]').click();

    await page.waitForLoadState('networkidle');
}

async function openLeaveApplication(page) {
    await page.goto('public/index.php?action=leave-application');

    await expect(page.locator('h1')).toHaveText(
        'Leave Application'
    );

    await expect(
        page.getByRole('heading', { name: 'Apply for Leave' })
    ).toBeVisible();
}

function uniqueReason(label) {
    return (
        `${reasonPrefix}SUBMIT_` +
        `${Date.now()}_` +
        `${Math.random().toString(16).slice(2)}_` +
        `${label}`
    );
}

function futureDate(offset = 0) {
    const seed = Math.floor(Date.now() / 1000) % 1800;

    return new Date(
        Date.UTC(2093, 0, 1) + (seed + offset) * 86400000
    )
        .toISOString()
        .slice(0, 10);
}

async function submitApplication(
    page,
    { date, type, period = '', reason }
) {
    await page.locator('#leave_date').fill(date);

    await page.locator('#leave_type').selectOption(type);

    if (type === 'Half Day') {
        await expect(
            page.locator('#halfDayPeriodGroup')
        ).toBeVisible();

        await page.locator('#half_day_period').selectOption(period);
    }

    await page.locator('#reason').fill(reason);

    // Register the POST response listener before clicking.
    const postResponsePromise = page.waitForResponse(response => {
        const request = response.request();
        const url = new URL(response.url());

        return (
            request.method() === 'POST' &&
            url.searchParams.get('action') === 'leave-application-store'
        );
    });

    page.on('response', async response => {
        const request = response.request();

        if (
            request.method() === 'GET' &&
            response.url().includes('action=leave-application')
        ) {
            console.log('REDIRECT GET STATUS:', response.status());
            console.log('REDIRECT GET URL:', response.url());
            console.log(
                'REDIRECT GET TEST ENTRY:',
                await response.headerValue('x-hris-test-entry')
            );
            console.log(
                'REDIRECT GET SESSION USER:',
                await response.headerValue('x-hris-test-session-user')
            );
            console.log(
                'REDIRECT GET DATABASE:',
                await response.headerValue('x-hris-database-target')
            );
        }
    });

    await page.getByRole('button', { name: 'Submit Application' }).click();

    const postResponse = await postResponsePromise;

    console.log('POST response status:', postResponse.status());
    console.log('POST response URL:', postResponse.url());

    console.log(
        'POST redirect location:',
        postResponse.headers()['location'] ?? 'No redirect location'
    );

    const headers = postResponse.headers();

    // Confirm that the POST reached the test-only entrypoint.
    expect(
        headers['x-hris-test-entry'],
        'POST must reach the test-only entry point'
    ).toBe('active');

    // Diagnostic: identify the authenticated user visible to PHP.
    const sessionUserId = headers['x-hris-test-session-user'];

    console.log(
        'POST session user ID:',
        sessionUserId ?? 'HEADER MISSING'
    );

    const requestHeaders =
        await postResponse.request().allHeaders();

    const cookieHeader = requestHeaders.cookie || '';

    console.log(
        'POST PHP session cookie:',
        cookieHeader.includes('PHPSESSID=') ? 'PRESENT' : 'MISSING'
    );

    console.log('Final page URL:', page.url());

    // Wait for the resulting page content to become available.
    await expect(page.locator('body')).not.toBeEmpty();

    console.log(
        'Final page body:',
        (await page.locator('body').innerText()).trim()
    );

    await expect(page.locator('h1')).toHaveText(
        'Leave Application'
    );

    const successAlert = page.locator('.alert.alert-success');
    const errorAlert = page.locator('.alert.alert-danger');

    await expect(
        successAlert.or(errorAlert)
    ).toBeVisible();

    if (await errorAlert.isVisible()) {
        throw new Error(
            `Leave application failed: ${(await errorAlert.innerText()).trim()
            }`
        );
    }

    await expect(successAlert).toContainText(
        'Leave application submitted successfully.'
    );

    await expect(
        page.locator('table.data-table tbody')
    ).toContainText(reason);
}

test.describe(
    'Employee Leave Application — Automated Black-Box Tests',
    () => {
        let employee;

        test.beforeAll(() => {
            // Reuse the employee account created by Holiday Management.
            runHelper('prepare');

            const data = loadManifest();

            employee = {
                username: data.username,
                password: data.password
            };

            reasonPrefix = data.reasonPrefix;
        });

        test.afterAll(() => {
            // Remove only fixtures and submitted rows carrying this run's
            // unique prefix. The shared employee account is preserved.
            try {
                runHelper('cleanup');
            } catch (error) {
                console.error(
                    'Leave Application cleanup failed:',
                    error.message
                );

                throw error;
            }
        });

        test.beforeEach(async ({ page, context, request }) => {
            // Fail closed unless the web guard confirms the test database.
            const guardResponse = await request.get(
                'http://localhost/HRIS-Management-System/blackbox-tests/db-guard.php'
            );

            expect(
                guardResponse.ok(),
                'Test DB guard must respond successfully'
            ).toBeTruthy();

            const guard = await guardResponse.json();

            expect(guard.ok).toBe(true);

            expect(guard.database).toBe(
                'hris_management_system_test'
            );

            await installTestRouting(context);

            await loginEmployee(page, employee);

            await openLeaveApplication(page);
        });

        // BB-LAPP-01
        test(
            'BB-LAPP-01 — Employee can open Leave Application page',
            async ({ page }) => {
                await expect(page.locator('h1')).toHaveText(
                    'Leave Application'
                );

                await expect(
                    page.getByRole('heading', {
                        name: 'My Leave Requests'
                    })
                ).toBeVisible();
            }
        );

        // BB-LAPP-02
        test(
            'BB-LAPP-02 — Application form displays all required controls',
            async ({ page }) => {
                await expect(
                    page.locator('#leave_date')
                ).toBeVisible();

                await expect(
                    page.locator('#leave_date')
                ).toHaveAttribute('required', '');

                await expect(
                    page.locator('#leave_type')
                ).toBeVisible();

                await expect(
                    page.locator('#leave_type')
                ).toHaveAttribute('required', '');

                await expect(
                    page.locator('#reason')
                ).toBeVisible();

                await expect(
                    page.locator('#reason')
                ).toHaveAttribute('required', '');

                await expect(
                    page.getByRole('button', {
                        name: 'Submit Application'
                    })
                ).toBeVisible();
            }
        );

        // BB-LAPP-03
        test(
            'BB-LAPP-03 — Leave type offers only Full Day and Half Day',
            async ({ page }) => {
                await expect(
                    page.locator('#leave_type option')
                ).toHaveText([
                    'Select Leave Type',
                    'Full Day',
                    'Half Day'
                ]);
            }
        );

        // BB-LAPP-04
        test(
            'BB-LAPP-04 — Half-Day period selector appears only for Half Day',
            async ({ page }) => {
                const group = page.locator('#halfDayPeriodGroup');

                await expect(group).toBeHidden();

                await expect(
                    page.locator('#half_day_period')
                ).not.toHaveAttribute('required', '');

                await page.locator('#leave_type').selectOption(
                    'Half Day'
                );

                await expect(group).toBeVisible();

                await expect(
                    page.locator('#half_day_period')
                ).toHaveAttribute('required', '');

                await expect(
                    page.locator('#half_day_period option')
                ).toHaveText([
                    'Select Period',
                    'First Half',
                    'Second Half'
                ]);

                await page.locator('#leave_type').selectOption(
                    'Full Day'
                );

                await expect(group).toBeHidden();

                await expect(
                    page.locator('#half_day_period')
                ).not.toHaveAttribute('required', '');
            }
        );

        // BB-LAPP-05
        test(
            'BB-LAPP-05 — Existing request history shows Pending, Accepted and Rejected',
            async ({ page }) => {
                const table = page.locator('table.data-table');

                await expect(table).toContainText(
                    `${reasonPrefix}Pending history fixture`
                );

                await expect(table).toContainText(
                    `${reasonPrefix}Accepted history fixture`
                );

                await expect(table).toContainText(
                    `${reasonPrefix}Rejected history fixture`
                );

                await expect(table).toContainText('Pending');
                await expect(table).toContainText('Accepted');
                await expect(table).toContainText('Rejected');
            }
        );

        // BB-LAPP-06
        test(
            'BB-LAPP-06 — Full Day application submits successfully',
            async ({ page }) => {
                const reason = uniqueReason('FULL_DAY');

                await submitApplication(page, {
                    date: futureDate(20),
                    type: 'Full Day',
                    reason
                });

                const row = page
                    .locator('table.data-table tbody tr')
                    .filter({ hasText: reason });

                await expect(row).toContainText('Full Day');
                await expect(row).toContainText('Pending');
                await expect(row).toContainText('—');
            }
        );

        // BB-LAPP-07
        test(
            'BB-LAPP-07 — First Half application submits successfully',
            async ({ page }) => {
                const reason = uniqueReason('FIRST_HALF');

                await submitApplication(page, {
                    date: futureDate(21),
                    type: 'Half Day',
                    period: 'first_half',
                    reason
                });

                const row = page
                    .locator('table.data-table tbody tr')
                    .filter({ hasText: reason });

                await expect(row).toContainText('Half Day');
                await expect(row).toContainText('First Half');
                await expect(row).toContainText('Pending');
            }
        );

        // BB-LAPP-08
        test(
            'BB-LAPP-08 — Second Half application submits successfully',
            async ({ page }) => {
                const reason = uniqueReason('SECOND_HALF');

                await submitApplication(page, {
                    date: futureDate(22),
                    type: 'Half Day',
                    period: 'second_half',
                    reason
                });

                const row = page
                    .locator('table.data-table tbody tr')
                    .filter({ hasText: reason });

                await expect(row).toContainText('Half Day');
                await expect(row).toContainText('Second Half');
                await expect(row).toContainText('Pending');
            }
        );

        // BB-LAPP-09
        test(
            'BB-LAPP-09 — Empty required fields prevent browser submission',
            async ({ page }) => {
                await page.getByRole('button', {
                    name: 'Submit Application'
                }).click();

                await expect(
                    page.locator('#leave_date')
                ).toBeFocused();

                await expect(
                    page.locator('table.data-table')
                ).toContainText(
                    `${reasonPrefix}Pending history fixture`
                );
            }
        );

        // BB-LAPP-10
        test(
            'BB-LAPP-10 — Leave request list shows only the logged-in employee history',
            async ({ page }) => {
                await expect(
                    page.locator('table.data-table')
                ).toContainText(
                    `${reasonPrefix}Pending history fixture`
                );

                await expect(
                    page.locator('table.data-table')
                ).not.toContainText(
                    'Pending full-day approval test.'
                );
            }
        );

        // BB-LAPP-11
        test(
            'BB-LAPP-11 — Successful submission displays confirmation and persists after reload',
            async ({ page }) => {
                const reason = uniqueReason('PERSISTENCE');

                await submitApplication(page, {
                    date: futureDate(23),
                    type: 'Full Day',
                    reason
                });

                await page.reload();

                await expect(
                    page.locator('table.data-table')
                ).toContainText(reason);

                await expect(
                    page
                        .locator('table.data-table tbody tr')
                        .filter({ hasText: reason })
                ).toContainText('Pending');
            }
        );

        // BB-LAPP-12
        test(
            'BB-LAPP-12 — Employee cannot open administrator Leave Management',
            async ({ page }) => {
                await page.goto(
                    'public/index.php?action=leave-management'
                );

                await expect(
                    page.locator('body')
                ).not.toContainText('Employee Leave Requests');

                await expect(
                    page.locator('form[action*="leave-review"]')
                ).toHaveCount(0);
            }
        );
    }
);
