# Holiday Management — Playwright Black-Box Tests

These files are intended to be copied into the existing project's `blackbox-tests/`
folder. They do not modify application controllers, models, views, config, or the
live database.

## Safety design

- `test-entry.php` defines `DB_NAME=hris_management_system_test` before it loads
  the unchanged `public/index.php`.
- Playwright rewrites requests for `/public/index.php` to the test-only entrypoint,
  including form posts and redirects.
- `db-guard.php` reports the database reached by the test database guard.
- The test suite stops unless the guard confirms `hris_management_system_test`.
- The seed and cleanup scripts both verify the exact database name before writing.
- Cleanup deletes only the two exact user IDs recorded by the seed script and
  holiday names beginning with this run's unique prefix.

## Setup

1. Copy the supplied files into your existing project:
   - `test-entry.php`, `db-guard.php`, `seed-holiday-test-users.php`,
     `cleanup-holiday-test-data.php`, `README-HOLIDAY-BLACKBOX.md`
     into `blackbox-tests/`.
   - `playwright.config.js` into `blackbox-tests/` (replace the existing test config).
   - `tests/holiday-management.spec.js` into `blackbox-tests/tests/`.
   - Append the contents of the supplied `.gitignore` to your existing `blackbox-tests/.gitignore` (do not overwrite existing ignore rules).
2. Ensure Laragon Apache and MySQL are running.
3. From a terminal in `C:\laragon\www\HRIS-Management-System\blackbox-tests`, run:
   `php seed-holiday-test-users.php`
4. Open this URL in the browser:
   `http://localhost/HRIS-Management-System/blackbox-tests/db-guard.php`
   It must return JSON with `"ok":true` and database
   `"hris_management_system_test"`.
5. Install dependencies if needed:
   `npm install`
6. Run:
   `npx playwright test`
7. View the report:
   `npx playwright show-report`

## Cleanup

After reviewing the test results, run from `blackbox-tests/`:

`php cleanup-holiday-test-data.php`

This removes only this run's generated holiday records and the exact two
test accounts created by the seed script. Do not manually change the manifest
unless you understand the cleanup scope.

## Important

The PHP test entrypoint depends on the project's existing route URLs and Laragon
virtual path. Do not browse to the normal `/public/index.php` directly for the
automated suite; Playwright intercepts it and routes it through `test-entry.php`.
Do not run the seed or cleanup scripts if the test database name is not exactly
`hris_management_system_test`.
