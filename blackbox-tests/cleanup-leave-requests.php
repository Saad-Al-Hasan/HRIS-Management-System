<?php
declare(strict_types=1);

/**
 * Leave Management black-box test data manager.
 *
 * Usage from blackbox-tests:
 *   php cleanup-leave-requests.php prepare
 *   php cleanup-leave-requests.php cleanup
 *
 * This script refuses to run unless the connected database is exactly
 * hris_management_system_test.
 */

const EXPECTED_DB = 'hris_management_system_test';
const DB_HOST = '127.0.0.1';
const DB_USER = 'root';
const DB_PASS = '';

$mode = $argv[1] ?? '';
if (!in_array($mode, ['prepare', 'cleanup'], true)) {
    fwrite(STDERR, "Usage: php cleanup-leave-requests.php prepare|cleanup\n");
    exit(2);
}

$baseDir = __DIR__;
$accountManifest = $baseDir . DIRECTORY_SEPARATOR . '.holiday-test-accounts.json';
$leaveManifest = $baseDir . DIRECTORY_SEPARATOR . '.leave-test-data.json';

function connectTestDatabase(): PDO
{
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . EXPECTED_DB . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($database !== EXPECTED_DB) {
        throw new RuntimeException(
            "SAFETY STOP: connected to {$database}; expected " . EXPECTED_DB
        );
    }

    return $pdo;
}

function readJsonFile(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException("Required manifest not found: {$path}");
    }

    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data)) {
        throw new RuntimeException("Invalid JSON manifest: {$path}");
    }

    return $data;
}

try {
    $pdo = connectTestDatabase();

    if ($mode === 'prepare') {
        if (is_file($leaveManifest)) {
            // A previous interrupted run may have left data. Clean only the
            // records named in its manifest before starting a new run.
            $old = readJsonFile($leaveManifest);
            if (($old['database'] ?? '') !== EXPECTED_DB) {
                throw new RuntimeException('Old leave manifest does not identify the test database.');
            }

            $oldEmployeeId = (int) ($old['employee_id'] ?? 0);
            $oldUserId = (int) ($old['employee_user_id'] ?? 0);
            if ($oldEmployeeId > 0) {
                $stmt = $pdo->prepare('DELETE FROM leave_requests WHERE employee_id = ?');
                $stmt->execute([$oldEmployeeId]);
                $stmt = $pdo->prepare('DELETE FROM employees WHERE id = ? AND user_id = ?');
                $stmt->execute([$oldEmployeeId, $oldUserId]);
            }
            if ($oldUserId > 0) {
                $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
                $stmt->execute([$oldUserId]);
            }
            @unlink($leaveManifest);
        }

        $accounts = readJsonFile($accountManifest);
        if (($accounts['database'] ?? '') !== EXPECTED_DB) {
            throw new RuntimeException('Holiday account manifest is not for the test database.');
        }
        $adminId = (int) ($accounts['admin']['id'] ?? 0);
        if ($adminId <= 0) {
            throw new RuntimeException('Existing test administrator ID is missing.');
        }

        $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE id = 2 AND name = 'Employee'");
        $roleStmt->execute();
        $employeeRoleId = (int) $roleStmt->fetchColumn();
        if ($employeeRoleId !== 2) {
            throw new RuntimeException('Employee role (id 2) was not found in the test database.');
        }

        $tag = date('Ymd_His') . '_' . bin2hex(random_bytes(4));
        $username = 'BBLEAVE_' . $tag . '_emp';
        $employeeCode = 'BBL' . strtoupper(substr(bin2hex(random_bytes(8)), 0, 12));
        $email = strtolower($username) . '@test.local';
        $password = bin2hex(random_bytes(10));

        $pdo->beginTransaction();

        $insertUser = $pdo->prepare(
            'INSERT INTO users (role_id, username, email, password, full_name, status)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $insertUser->execute([
            $employeeRoleId,
            $username,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            'Black Box Leave Employee',
            'active'
        ]);
        $employeeUserId = (int) $pdo->lastInsertId();

        $insertEmployee = $pdo->prepare(
            'INSERT INTO employees (user_id, employee_code, department)
             VALUES (?, ?, ?)'
        );
        $insertEmployee->execute([$employeeUserId, $employeeCode, 'BLACK_BOX_TEST']);
        $employeeId = (int) $pdo->lastInsertId();

        $insertLeave = $pdo->prepare(
            'INSERT INTO leave_requests
                (employee_id, leave_type, half_day_period, start_date, end_date, reason, status, reviewed_by, reviewed_at)
             VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $futureBase = new DateTimeImmutable('2095-05-15');
        $cases = [
            'accept_full' => ['Full Day', null, 'Pending full-day approval test.', 'pending', null, null],
            'reject_full' => ['Full Day', null, 'Pending full-day rejection test.', 'pending', null, null],
            'accept_half' => ['Half Day', 'first_half', 'Pending first-half approval test.', 'pending', null, null],
            'reject_half' => ['Half Day', 'second_half', 'Pending second-half rejection test.', 'pending', null, null],
            'confirm_pending' => ['Full Day', null, 'Pending confirmation test.', 'pending', null, null],
            'already_reviewed' => ['Full Day', null, 'Already-reviewed display test.', 'approved', $adminId, date('Y-m-d H:i:s')],
            'already_rejected' => ['Full Day', null, 'Already-rejected display test.', 'rejected', $adminId, date('Y-m-d H:i:s')],
        ];

        $requestIds = [];
        $offset = 0;
        foreach ($cases as $key => $case) {
            $date = $futureBase->modify('+' . $offset . ' days')->format('Y-m-d');
            $insertLeave->execute([
                $employeeId,
                $case[0],
                $case[1],
                $date,
                $date,
                $case[2],
                $case[3],
                $case[4],
                $case[5]
            ]);
            $requestIds[$key] = (int) $pdo->lastInsertId();
            $offset++;
        }

        $pdo->commit();

        $manifest = [
            'database' => EXPECTED_DB,
            'run_prefix' => 'BB-LEAVE-' . $tag,
            'created_at' => date(DATE_ATOM),
            'admin' => $accounts['admin'],
            'employee_user_id' => $employeeUserId,
            'employee' => [
                'id' => $employeeId,
                'username' => $username,
                'password' => $password,
                'employee_code' => $employeeCode
            ],
            'request_ids' => $requestIds
        ];

        file_put_contents(
            $leaveManifest,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
        @chmod($leaveManifest, 0600);

        echo "Prepared Leave Management test data in " . EXPECTED_DB . ".\n";
        echo "Employee code: {$employeeCode}\n";
        echo "Created " . count($requestIds) . " uniquely identified leave requests.\n";
        echo "Test administrator reused from .holiday-test-accounts.json.\n";
        exit(0);
    }

    // cleanup mode
    if (!is_file($leaveManifest)) {
        echo "No Leave test manifest found; nothing to clean.\n";
        exit(0);
    }

    $manifest = readJsonFile($leaveManifest);
    if (($manifest['database'] ?? '') !== EXPECTED_DB) {
        throw new RuntimeException('Cleanup refused: manifest database is not the test database.');
    }

    $employeeId = (int) ($manifest['employee']['id'] ?? 0);
    $employeeUserId = (int) ($manifest['employee_user_id'] ?? 0);

    if ($employeeId > 0 && $employeeUserId > 0) {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('DELETE FROM leave_requests WHERE employee_id = ?');
        $stmt->execute([$employeeId]);
        $deletedRequests = $stmt->rowCount();

        $stmt = $pdo->prepare('DELETE FROM employees WHERE id = ? AND user_id = ?');
        $stmt->execute([$employeeId, $employeeUserId]);

        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$employeeUserId]);

        $pdo->commit();
        @unlink($leaveManifest);

        echo "Cleanup complete in " . EXPECTED_DB . ". Removed {$deletedRequests} Leave test request(s), the test employee profile, and its test user.\n";
    } else {
        throw new RuntimeException('Cleanup refused: test employee identifiers are incomplete.');
    }
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
