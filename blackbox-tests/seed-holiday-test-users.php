<?php
declare(strict_types=1);

/*
 * Run from blackbox-tests:
 * php seed-holiday-test-users.php
 *
 * Creates two uniquely named accounts in the TEST database only.
 * Credentials are written to .holiday-test-accounts.json (gitignored).
 */
$expectedDb = 'hris_management_system_test';
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

$roles = $pdo->query("SELECT id, name FROM roles WHERE name IN ('Administrator', 'Employee')")->fetchAll();
$roleIds = [];
foreach ($roles as $role) {
    $roleIds[$role['name']] = (int) $role['id'];
}
if (!isset($roleIds['Administrator'], $roleIds['Employee'])) {
    fwrite(STDERR, "ABORTED: Administrator and Employee roles must exist in the test database.\n");
    exit(1);
}

$tag = date('Ymd_His') . '_' . bin2hex(random_bytes(3));
$prefix = 'BBHOL_' . $tag;
$password = bin2hex(random_bytes(8));

$accounts = [];
$insert = $pdo->prepare(
    'INSERT INTO users (role_id, username, email, password, full_name, status)
     VALUES (:role_id, :username, :email, :password, :full_name, \'active\')'
);

foreach ([
    ['role' => 'Administrator', 'suffix' => 'admin', 'name' => 'Black Box Holiday Admin'],
    ['role' => 'Employee', 'suffix' => 'employee', 'name' => 'Black Box Holiday Employee']
] as $item) {
    $username = $prefix . '_' . $item['suffix'];
    $email = strtolower($username) . '@test.local';

    $insert->execute([
        ':role_id' => $roleIds[$item['role']],
        ':username' => $username,
        ':email' => $email,
        ':password' => password_hash($password, PASSWORD_DEFAULT),
        ':full_name' => $item['name']
    ]);

    $accounts[$item['suffix']] = [
        'id' => (int) $pdo->lastInsertId(),
        'username' => $username,
        'password' => $password
    ];
}

$payload = [
    'database' => $activeDb,
    'runPrefix' => 'BB-HOL-' . $tag,
    'createdAt' => date(DATE_ATOM),
    'admin' => $accounts['admin'],
    'employee' => $accounts['employee']
];

$path = __DIR__ . '/.holiday-test-accounts.json';
file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
@chmod($path, 0600);

echo "Test accounts created in {$activeDb}.\n";
echo "Credentials saved to blackbox-tests/.holiday-test-accounts.json\n";
echo "Run prefix: {$payload['runPrefix']}\n";
