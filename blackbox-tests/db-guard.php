<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$remote = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remote, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Local access only']);
    exit;
}

// Use the same Database class as the HRIS application, not a separate DSN.
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'hris_management_system_test');
define('DB_USER', 'root');
define('DB_PASS', '');
require_once __DIR__ . '/../app/core/Database.php';

try {
    $pdo = (new Database())->connect();
    $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $ok = ($database === 'hris_management_system_test');

    $result = [
        'ok' => $ok,
        'database' => $database,
        'expected' => 'hris_management_system_test',
        'connection_class' => 'Application Database'
    ];

    // Optional read-only diagnostic used after a browser form submission.
    if ($ok && isset($_GET['holiday_name'])) {
        $name = trim((string) $_GET['holiday_name']);
        if ($name !== '' && mb_strlen($name) <= 100) {
            $stmt = $pdo->prepare(
                'SELECT id, holiday_name, holiday_date, holiday_type
                 FROM holidays WHERE holiday_name = :name LIMIT 1'
            );
            $stmt->execute([':name' => $name]);
            $result['holiday'] = $stmt->fetch() ?: null;
        }
    }

    http_response_code($ok ? 200 : 500);
    echo json_encode($result, JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    // Do not expose database credentials or internal exception details.
    echo json_encode(['ok' => false, 'error' => 'Application database connection failed']);
}
