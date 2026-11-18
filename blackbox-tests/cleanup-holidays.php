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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

$prefix = trim((string) ($_POST['prefix'] ?? ''));
// Strictly permit only this run's generated prefix; never accept a broad prefix.
if (!preg_match('/^BBHOL-[0-9]{13}-[a-f0-9]{12}-$/', $prefix)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid test-run prefix']);
    exit;
}

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'hris_management_system_test');
define('DB_USER', 'root');
define('DB_PASS', '');
require_once __DIR__ . '/../app/core/Database.php';

try {
    $pdo = (new Database())->connect();
    $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($database !== 'hris_management_system_test') {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Safety stop: unexpected database']);
        exit;
    }

    $stmt = $pdo->prepare(
        'DELETE FROM holidays
         WHERE LEFT(holiday_name, CHAR_LENGTH(:prefix)) = :prefix_check'
    );
    $stmt->execute([
        ':prefix' => $prefix,
        ':prefix_check' => $prefix
    ]);

    echo json_encode([
        'ok' => true,
        'database' => $database,
        'deleted' => $stmt->rowCount()
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Test cleanup failed']);
}
