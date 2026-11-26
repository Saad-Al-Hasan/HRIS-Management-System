# HRIS Audit Log — 65 Playwright Black-Box Tests

This package contains:

- `tests/audit-log.spec.js` — 65 named Playwright black-box cases (`AUD-BB-01` through `AUD-BB-65`). Each case has a nearby comment with its test ID and test-case name.
- `helpers/seed-audit-log-blackbox.php` — creates unique, inspectable test records and writes `.audit-log-test-data.json`.

## Important database safety and audit retention

- The seeder refuses to run unless the configured database name is exactly `hris_management_system_test` and then verifies `SELECT DATABASE()`.
- It inserts a uniquely named fixture actor and audit rows only in that database.
- **It does not delete audit logs or the fixture actor.** This is intentional so you can inspect all test-generated audit rows in phpMyAdmin.
- Each seed run uses a new run marker, so rerunning creates a separate batch instead of overwriting or depending on fixed total counts.
- The Playwright suite reads the latest run marker from `.audit-log-test-data.json`; do not edit that file manually.

## Install

Extract the ZIP into your project’s `blackbox-tests` folder so the files land at:

```text
blackbox-tests/
  tests/audit-log.spec.js
  helpers/seed-audit-log-blackbox.php
```

This suite reuses the project's existing test-only infrastructure:

- `blackbox-tests/test-entry.php`
- `blackbox-tests/db-guard.php`
- `blackbox-tests/.holiday-test-accounts.json` (or the environment variables below)
- Playwright installed in the project / `blackbox-tests` folder

The admin account must be a **test-database account**. The suite checks the account manifest's database field before using credentials.

## Seed a fresh run

From `C:\laragon\www\HRIS-Management-System\blackbox-tests`:

```bash
php helpers/seed-audit-log-blackbox.php
```

If your local test DB connection differs from the default Laragon root/no-password connection, set these environment variables before running the PHP command:

```text
HRIS_TEST_DB_HOST
HRIS_TEST_DB_NAME=hris_management_system_test
HRIS_TEST_DB_USER
HRIS_TEST_DB_PASS
```

The script prints the marker, actor username/ID and number of inserted rows. Open phpMyAdmin and inspect `audit_logs` by searching the `activity` column for the printed `AUDIT_BB_RUN_...` marker. The manifest stores the fixture actor and inserted IDs.

## Run the 65 tests

```bash
npx playwright test tests/audit-log.spec.js
```

Run a specific case by ID:

```bash
npx playwright test tests/audit-log.spec.js --grep "AUD-BB-52"
```

Use one worker if you want the page navigation and fixture filtering to be easier to inspect:

```bash
npx playwright test tests/audit-log.spec.js --workers=1
```

## Notes on expected findings

- The suite is aligned to the supplied Audit Log controller/view snapshot and uses the selectors present there (`#audit-from-date`, `#audit-to-date`, `#audit-user-search`, `#audit-user-id`, `#audit-module`, `.audit-table`, `.audit-module-tile`).
- Some cases intentionally test edge behavior such as invalid query values, keyword query parameters, and escaped activity text. If the current implementation does not support a described behavior, the test will report that as a failure rather than silently skipping it.
- The 65 tests are packaged and syntax-checked, but they have **not** been run against your Laragon instance from this environment.
- Existing application actions are not executed to create every domain event; the fixture seeder creates controlled audit event phrases for all eight module classifiers, pagination, system actor and HTML escaping. This avoids changing HR records during audit-log testing.

## Optional `.gitignore` entries

Add these to `blackbox-tests/.gitignore` so generated local test credentials/data are not committed:

```gitignore
.holiday-test-accounts.json
.audit-log-test-data.json
```
