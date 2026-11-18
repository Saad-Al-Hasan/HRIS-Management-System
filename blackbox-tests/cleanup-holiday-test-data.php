<?php
declare(strict_types=1);

/*
 * Run after reviewing the test results:
 * php cleanup-holiday-test-data.php
 *
 * Deletes only holidays with this run's unique prefix and the two exact
 * user IDs created by seed-holiday-test-users.php.
 */
$accountsPath = __DIR__ . '/.holiday-test-accounts.json';
if (!is_file($accountsPath)) {
    fwrite(STDERR, "No test-account manifest found. Nothing was deleted.\n");
    exit(1);
}

$manifest = json_decode((string) file_get_contents($accountsPath), true);
if (!is_array($manifest) || ($manifest['database'] ?? '') !== 'hris_management_system_test') {
    fwrite(STDERR, "ABORTED: invalid manifest or wrong database marker.\n");
    exit(1);
}

$expectedDb = 'hris_management_system_test';
$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=' . $expectedDb . ';charset=utf8mb4',
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$activeDb = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if ($activeDb !== $expectedDb) {
    fwrite(STDERR, "ABORTED: connected database is {$activeDb}; expected {$expectedDb}.\n");
    exit(1);
}

$prefix = (string) ($manifest['runPrefix'] ?? '');
if ($prefix === '' || !preg_match('/^BB-HOL-[A-Za-z0-9_]+$/', $prefix)) {
    fwrite(STDERR, "ABORTED: invalid holiday cleanup prefix.\n");
    exit(1);
}

$deleteHolidays = $pdo->prepare('DELETE FROM holidays WHERE holiday_name LIKE :prefix');
$deleteHolidays->execute([':prefix' => $prefix . '%']);
$holidayCount = $deleteHolidays->rowCount();

$deleteUser = $pdo->prepare('DELETE FROM users WHERE id = :id AND username = :username');
$userCount = 0;
foreach (['admin', 'employee'] as $key) {
    $account = $manifest[$key] ?? [];
    if (!isset($account['id'], $account['username'])) {
        continue;
    }
    $deleteUser->execute([
        ':id' => (int) $account['id'],
        ':username' => (string) $account['username']
    ]);
    $userCount += $deleteUser->rowCount();
}

unlink($accountsPath);
echo "Cleanup completed in {$activeDb}.\n";
echo "Deleted {$holidayCount} holiday test record(s) and {$userCount} test account(s).\n";
