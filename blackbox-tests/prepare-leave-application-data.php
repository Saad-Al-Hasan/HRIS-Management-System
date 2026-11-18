<?php
declare(strict_types=1);

/*
 * Run from blackbox-tests:
 *   php prepare-leave-application-data.php prepare
 *   php prepare-leave-application-data.php cleanup
 *
 * Reuses the Holiday Management employee account and only touches uniquely
 * tagged leave_requests rows in hris_management_system_test.
 */
$expectedDb = 'hris_management_system_test';
$accountsPath = __DIR__ . '/.holiday-test-accounts.json';
$manifestPath = __DIR__ . '/.leave-application-test-data.json';

if (!is_file($accountsPath)) {
    fwrite(STDERR, "ABORTED: .holiday-test-accounts.json is missing. Run the Holiday test-account setup first.\n");
    exit(1);
}
$accounts = json_decode((string) file_get_contents($accountsPath), true);
if (!is_array($accounts) || ($accounts['database'] ?? '') !== $expectedDb || empty($accounts['employee']['id'])) {
    fwrite(STDERR, "ABORTED: Holiday employee account manifest is invalid or not for the test database.\n");
    exit(1);
}

$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=' . $expectedDb . ';charset=utf8mb4',
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$activeDb = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if ($activeDb !== $expectedDb) {
    fwrite(STDERR, "ABORTED: connected database is {$activeDb}; expected {$expectedDb}.\n");
    exit(1);
}

$employeeUserId = (int) $accounts['employee']['id'];
$stmt = $pdo->prepare('SELECT id FROM employees WHERE user_id = ? LIMIT 1');
$stmt->execute([$employeeUserId]);
$employeeId = (int) $stmt->fetchColumn();

// The Holiday seeder creates the shared employee login but may not create
// its employee profile. Create that missing profile once, and preserve it
// across Leave Application test cleanup because the login is shared.
if ($employeeId <= 0) {
    $username = (string) $accounts['employee']['username'];
    $employeeCode = 'BBLAEMP_' . strtoupper(substr(hash('sha256', $username . ':' . $employeeUserId), 0, 16));
    $profile = $pdo->prepare(
        'INSERT INTO employees (user_id, employee_code, department) VALUES (?, ?, ?)'
    );
    $profile->execute([$employeeUserId, $employeeCode, 'BLACK BOX TEST']);
    $employeeId = (int) $pdo->lastInsertId();
    echo "Created the missing employee profile for the reused Holiday employee account.\n";
}

function removeTaggedRows(PDO $pdo, int $employeeId, string $prefix): int
{
    $stmt = $pdo->prepare(
        'DELETE FROM leave_requests
         WHERE employee_id = ?
         AND LEFT(reason, CHAR_LENGTH(?)) = ?'
    );
    $stmt->execute([$employeeId, $prefix, $prefix]);
    return $stmt->rowCount();
}

$mode = $argv[1] ?? '';
if ($mode === 'cleanup') {
    if (is_file($manifestPath)) {
        $old = json_decode((string) file_get_contents($manifestPath), true);
        if (
            is_array($old) && ($old['database'] ?? '') === $expectedDb
            && (int) ($old['employeeId'] ?? 0) === $employeeId
            && str_starts_with((string) ($old['reasonPrefix'] ?? ''), 'BBLA_')
        ) {
            $removed = removeTaggedRows($pdo, $employeeId, $old['reasonPrefix']);
            unlink($manifestPath);
            echo "Cleanup complete in {$expectedDb}. Removed {$removed} Leave Application test history row(s).\n";
            exit(0);
        }
    }
    echo "Nothing to clean. No valid Leave Application test manifest was found.\n";
    exit(0);
}

if ($mode !== 'prepare') {
    fwrite(STDERR, "Usage: php prepare-leave-application-data.php prepare|cleanup\n");
    exit(1);
}

// Remove only rows from a previous run of this helper; preserve all other history.
if (is_file($manifestPath)) {
    $old = json_decode((string) file_get_contents($manifestPath), true);
    if (
        is_array($old) && ($old['database'] ?? '') === $expectedDb
        && (int) ($old['employeeId'] ?? 0) === $employeeId
        && str_starts_with((string) ($old['reasonPrefix'] ?? ''), 'BBLA_')
    ) {
        removeTaggedRows($pdo, $employeeId, $old['reasonPrefix']);
    }
}

$tag = date('Ymd_His') . '_' . bin2hex(random_bytes(4));
$reasonPrefix = 'BBLA_' . $tag . '_';
$baseDate = new DateTimeImmutable('2092-01-01');
$offset = (int) (hexdec(substr(hash('sha256', $tag), 0, 6)) % 1500);
$dates = [];
for ($i = 0; $i < 3; $i++) {
    $dates[] = $baseDate->modify('+' . ($offset + $i) . ' days')->format('Y-m-d');
}

$insert = $pdo->prepare(
    "INSERT INTO leave_requests
      (employee_id, leave_type, half_day_period, start_date, end_date, reason, status, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
);
$seed = [
    ['Full Day', null, $dates[0], 'Pending'],
    ['Half Day', 'first_half', $dates[1], 'Accepted'],
    ['Half Day', 'second_half', $dates[2], 'Rejected']
];
foreach ($seed as $i => $row) {
    $status = ['pending', 'approved', 'rejected'][$i];
    $insert->execute([
        $employeeId,
        $row[0],
        $row[1],
        $row[2],
        $row[2],
        $reasonPrefix . $row[3] . ' history fixture',
        $status
    ]);
}

$payload = [
    'database' => $activeDb,
    'employeeUserId' => $employeeUserId,
    'employeeId' => $employeeId,
    'username' => $accounts['employee']['username'],
    'password' => $accounts['employee']['password'],
    'reasonPrefix' => $reasonPrefix,
    'createdAt' => date(DATE_ATOM)
];
file_put_contents($manifestPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
@chmod($manifestPath, 0600);

echo "Prepared Leave Application fixtures in {$activeDb} using the existing Holiday employee account.\n";
echo "Created 3 uniquely tagged history rows (Pending, Accepted, Rejected).\n";
