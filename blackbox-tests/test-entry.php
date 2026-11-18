<?php
declare(strict_types=1);

/*
 * TEST-ONLY web entrypoint.
 * Force the application to use the isolated test database.
 */

if (!defined('DB_HOST')) {
    define('DB_HOST', '127.0.0.1');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'hris_management_system_test');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', '');
}

if (DB_NAME !== 'hris_management_system_test') {
    http_response_code(500);
    exit('Safety stop: test entrypoint is not configured for the test database.');
}

// Prove that Playwright reached this test-only entrypoint.
header('X-HRIS-Test-Entry: active');

// Start the existing browser session and inspect its authenticated user.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header(
    'X-HRIS-Test-Session-User: ' .
    ($_SESSION['user_id'] ?? 'NO_SESSION_USER')
);

// Prevent automatic salary recalculation during Salary Management
// page visits in black-box tests. Manual salary submissions still run.
if (
    $_SERVER['REQUEST_METHOD'] === 'GET' &&
    ($_GET['action'] ?? '') === 'salary-management'
) {
    $_SESSION['skip_salary_auto_refresh_once'] = true;
}

header('X-HRIS-Database-Target: hris_management_system_test');

// Suppress only duplicate DB_* constant warnings.
set_error_handler(
    static function (int $severity, string $message): bool {
        if (
            $severity === E_WARNING &&
            str_contains($message, 'already defined') &&
            preg_match('/Constant DB_(HOST|NAME|USER|PASS)/', $message)
        ) {
            return true;
        }

        return false;
    },
    E_WARNING
);

try {
    // Let the application initialize its own session.
    require __DIR__ . '/../public/index.php';
} finally {
    restore_error_handler();
}
