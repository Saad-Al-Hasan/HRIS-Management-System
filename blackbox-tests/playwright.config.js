const { defineConfig } = require('@playwright/test');

// Configuration for HRIS Management System black-box browser testing.
module.exports = defineConfig({

    // Location of all Playwright test files.
    testDir: './tests',

    // Run tests sequentially to avoid browser session conflicts.
    fullyParallel: false,

    // Display test results in the terminal and generate an HTML report.
    reporter: [
        ['list'],
        ['html', {
            outputFolder: 'playwright-report',
            open: 'never'
        }]
    ],

    // Browser and application settings.
    use: {
        baseURL: 'http://localhost/HRIS-Management-System/public/',
        browserName: 'chromium',
        headless: false,
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure'
    },

    // Maximum time allowed for each test.
    timeout: 30000

});
