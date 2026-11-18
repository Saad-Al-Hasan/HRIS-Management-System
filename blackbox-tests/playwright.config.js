const { defineConfig } = require('@playwright/test');

// Configuration for HRIS Management System black-box testing.
module.exports = defineConfig({
    testDir: './tests',

    fullyParallel: false,
    workers: 1,

    reporter: [
        ['list'],
        ['html', {
            outputFolder: 'playwright-report',
            open: 'never'
        }]
    ],

    use: {
        baseURL: 'http://localhost/HRIS-Management-System/',
        browserName: 'chromium',
        headless: false,
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure'
    },

    timeout: 30000,
    expect: {
        timeout: 10000
    }
});
