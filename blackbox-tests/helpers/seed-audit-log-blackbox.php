<?php
declare(strict_types=1);

/**
 * AUD-BB test fixture seeder.
 * IMPORTANT: inserts only into hris_management_system_test and NEVER deletes
 * audit_logs rows. Each execution creates a unique marker and a new actor.
 * Run from blackbox-tests: php helpers/seed-audit-log-blackbox.php
 */

$dbHost = getenv('HRIS_TEST_DB_HOST') ?: '127.0.0.1';
$dbName = getenv('HRIS_TEST_DB_NAME') ?: 'hris_management_system_test';
$dbUser = getenv('HRIS_TEST_DB_USER') ?: 'root';
$dbPass = getenv('HRIS_TEST_DB_PASS') ?: '';

if ($dbName !== 'hris_management_system_test') {
    fwrite(STDERR, "SAFETY STOP: DB name must be hris_management_system_test.\n");
    exit(2);
}

$pdo = new PDO(
    "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
    $dbUser,
    $dbPass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$actualDb = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if ($actualDb !== 'hris_management_system_test') {
    fwrite(STDERR, "SAFETY STOP: connected database is {$actualDb}.\n");
    exit(3);
}

$manifestPath = dirname(__DIR__) . '/.audit-log-test-data.json';
$previousRun = null;
if (is_file($manifestPath)) {
    $oldManifest = json_decode((string) file_get_contents($manifestPath), true);
    if (is_array($oldManifest) && ($oldManifest['database'] ?? '') === 'hris_management_system_test' && !empty($oldManifest['run_marker'])) {
        $previousRun = [
            'run_marker' => $oldManifest['run_marker'],
            'actor' => $oldManifest['actor'] ?? null,
            'date' => $oldManifest['date'] ?? null
        ];
    }
}

$run = 'AUDIT_BB_RUN_' . gmdate('Ymd_His') . '_' . strtoupper(bin2hex(random_bytes(5)));
$actorUsername = strtolower($run) . '_actor';
$actorEmail = $actorUsername . '@test.local';
$roleId = $pdo->query('SELECT id FROM roles ORDER BY id ASC LIMIT 1')->fetchColumn();
if (!$roleId) {
    fwrite(STDERR, "SAFETY STOP: test database has no role row.\n");
    exit(4);
}

// Create a unique, non-login fixture actor solely for audit filtering.
$userStmt = $pdo->prepare(
    'INSERT INTO users (role_id, username, email, password, full_name, status)
     VALUES (:role_id, :username, :email, :password, :full_name, :status)'
);
$userStmt->execute([
    ':role_id' => (int) $roleId,
    ':username' => $actorUsername,
    ':email' => $actorEmail,
    ':password' => 'blackbox_fixture_not_a_login_password',
    ':full_name' => $run . ' Audit Fixture Actor',
    ':status' => 'active'
]);
$actorId = (int) $pdo->lastInsertId();

$moduleEvents = [
    'authentication' => 'Administrator logged in',
    'employee_management' => 'Employee profile updated',
    'rfid_management' => 'RFID card assigned',
    'attendance_management' => 'Attendance record updated',
    'work_schedules' => 'Work schedule created',
    'salary_management' => 'Salary processed',
    'leave_management' => 'Leave request approved',
    'holiday_management' => 'Holiday created'
];
$activities = [];
foreach ($moduleEvents as $module => $phrase) {
    $activities[] = ['module' => $module, 'activity' => $phrase . ' [' . $run . ']'];
}
// Seed 25 extra events for pagination, newest-first ordering and marker search.
for ($i = 1; $i <= 25; $i++) {
    $activities[] = [
        'module' => 'other',
        'activity' => sprintf('Audit BB pagination event %02d [%s]', $i, $run)
    ];
}
// A system/null actor record supports the SYSTEM / UNAVAILABLE actor check.
$activities[] = ['module' => 'other', 'activity' => 'Audit BB system actor [' . $run . ']'];
$activities[] = ['module' => 'other', 'activity' => 'Audit BB XSS fixture <script>alert(1)</script> [' . $run . ']'];

$insert = $pdo->prepare(
    'INSERT INTO audit_logs (user_id, activity, activity_timestamp)
     VALUES (:user_id, :activity, :activity_timestamp)'
);
$now = new DateTimeImmutable('now');
$ids = [];
foreach ($activities as $index => $event) {
    // Small deterministic offsets keep a stable timestamp ordering within this run.
    $timestamp = $now->modify('+' . $index . ' seconds')->format('Y-m-d H:i:s');
    $userId = $event['activity'] === 'Audit BB system actor [' . $run . ']' ? null : $actorId;
    $insert->bindValue(':user_id', $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $insert->bindValue(':activity', $event['activity'], PDO::PARAM_STR);
    $insert->bindValue(':activity_timestamp', $timestamp, PDO::PARAM_STR);
    $insert->execute();
    $ids[] = (int) $pdo->lastInsertId();
}

// Two equal-timestamp records exercise the model's timestamp DESC, id DESC tie-break.
$tieTimestamp = $now->modify('+90 seconds')->format('Y-m-d H:i:s');
$tieIds = [];
foreach (['Audit BB tie-order first', 'Audit BB tie-order second'] as $tieActivity) {
    $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, activity, activity_timestamp) VALUES (:user_id, :activity, :activity_timestamp)');
    $stmt->execute([':user_id' => $actorId, ':activity' => $tieActivity . ' [' . $run . ']', ':activity_timestamp' => $tieTimestamp]);
    $tieIds[] = (int) $pdo->lastInsertId();
    $ids[] = (int) $pdo->lastInsertId();
}

$manifest = [
    'database' => $actualDb,
    'run_marker' => $run,
    'actor' => ['id' => $actorId, 'username' => $actorUsername],
    'date' => $now->format('Y-m-d'),
    'created_count' => count($ids),
    'ids' => $ids,
    'tie_ids' => $tieIds,
    'created_at' => $now->format(DateTimeInterface::ATOM),
    'note' => 'Audit rows and fixture actor are intentionally retained for manual inspection.'
];
$manifest['previous_run'] = $previousRun;
file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Seeded {$manifest['created_count']} audit records.\nRun marker: {$run}\nActor: {$actorUsername} (ID {$actorId})\nManifest: {$manifestPath}\nNo audit logs were deleted.\n";
