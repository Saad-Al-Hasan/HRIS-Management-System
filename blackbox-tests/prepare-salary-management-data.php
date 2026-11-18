<?php
declare(strict_types=1);

/*
 * Salary Management BB test fixture.
 *
 * Usage:
 * php prepare-salary-management-data.php prepare
 * php prepare-salary-management-data.php cleanup
 *
 * TEST DATABASE ONLY.
 */

$expectedDb = 'hris_management_system_test';
$accountPath = __DIR__ . '/.holiday-test-accounts.json';
$manifestPath = __DIR__ . '/.salary-management-test-data.json';

if (!is_file($accountPath)) {
    fwrite(STDERR, "ABORTED: Holiday test-account manifest is missing.\n");
    exit(1);
}

$accounts = json_decode(
    (string) file_get_contents($accountPath),
    true
);

if (
    !is_array($accounts) ||
    ($accounts['database'] ?? '') !== $expectedDb ||
    empty($accounts['admin']['id']) ||
    empty($accounts['admin']['username']) ||
    empty($accounts['admin']['password']) ||
    empty($accounts['employee']['username']) ||
    empty($accounts['employee']['password'])
) {
    fwrite(STDERR, "ABORTED: Invalid test-account manifest.\n");
    exit(1);
}

$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=' . $expectedDb . ';charset=utf8mb4',
    'root',
    '',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);

$activeDb = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();

if ($activeDb !== $expectedDb) {
    fwrite(
        STDERR,
        "ABORTED: Connected to {$activeDb}; expected {$expectedDb}.\n"
    );
    exit(1);
}

/*
 * Verify the reused administrator and employee accounts.
 */
$accountCheck = $pdo->prepare(
    'SELECT id, role_id, status FROM users WHERE id = ? LIMIT 1'
);

$accountCheck->execute([(int) $accounts['admin']['id']]);
$adminRow = $accountCheck->fetch();

if (
    !$adminRow ||
    (int) $adminRow['role_id'] !== 1 ||
    $adminRow['status'] !== 'active'
) {
    fwrite(STDERR, "ABORTED: Existing test administrator is invalid.\n");
    exit(1);
}

$employeeCheck = $pdo->prepare(
    'SELECT id, role_id, status FROM users WHERE username = ? LIMIT 1'
);

$employeeCheck->execute([$accounts['employee']['username']]);
$sharedEmployee = $employeeCheck->fetch();

if (
    !$sharedEmployee ||
    (int) $sharedEmployee['role_id'] !== 2 ||
    $sharedEmployee['status'] !== 'active'
) {
    fwrite(STDERR, "ABORTED: Existing test employee is invalid.\n");
    exit(1);
}

/*
 * Delete only the uniquely identified fixture from a previous run.
 */
function cleanupSalaryFixture(PDO $pdo, array $data): void
{
    if (
        ($data['database'] ?? '') !== 'hris_management_system_test' ||
        !str_starts_with(
            (string) ($data['tag'] ?? ''),
            'BBSAL_'
        )
    ) {
        throw new RuntimeException('Unsafe salary fixture manifest.');
    }

    $employeeId = (int) ($data['employeeId'] ?? 0);
    $userId = (int) ($data['userId'] ?? 0);
    $scheduleId = (int) ($data['scheduleId'] ?? 0);

    if ($employeeId <= 0 || $userId <= 0 || $scheduleId <= 0) {
        throw new RuntimeException('Invalid salary fixture IDs.');
    }

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'DELETE FROM salary WHERE employee_id = ?'
        );
        $stmt->execute([$employeeId]);

        $stmt = $pdo->prepare(
            'DELETE FROM employee_schedules WHERE employee_id = ? AND schedule_id = ?'
        );
        $stmt->execute([$employeeId, $scheduleId]);

        $stmt = $pdo->prepare(
            'DELETE FROM employees WHERE id = ? AND user_id = ? AND employee_code = ?'
        );
        $stmt->execute([
            $employeeId,
            $userId,
            $data['employeeCode']
        ]);

        $stmt = $pdo->prepare(
            'DELETE FROM work_schedules WHERE id = ? AND name = ?'
        );
        $stmt->execute([
            $scheduleId,
            $data['scheduleName']
        ]);

        $stmt = $pdo->prepare(
            'DELETE FROM users WHERE id = ? AND username = ?'
        );
        $stmt->execute([
            $userId,
            $data['username']
        ]);

        $pdo->commit();

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

$mode = $argv[1] ?? '';

if ($mode === 'cleanup') {
    if (!is_file($manifestPath)) {
        echo "No Salary Management fixture manifest found.\n";
        exit(0);
    }

    $data = json_decode(
        (string) file_get_contents($manifestPath),
        true
    );

    if (!is_array($data)) {
        fwrite(STDERR, "ABORTED: Invalid fixture manifest.\n");
        exit(1);
    }

    cleanupSalaryFixture($pdo, $data);

    unlink($manifestPath);

    echo "Salary Management fixture cleanup completed in {$expectedDb}.\n";
    exit(0);
}

if ($mode !== 'prepare') {
    fwrite(
        STDERR,
        "Usage: php prepare-salary-management-data.php prepare|cleanup\n"
    );
    exit(1);
}

/*
 * Remove only the previous run's own fixture.
 */
if (is_file($manifestPath)) {
    $oldData = json_decode(
        (string) file_get_contents($manifestPath),
        true
    );

    if (is_array($oldData)) {
        cleanupSalaryFixture($pdo, $oldData);
    }

    unlink($manifestPath);
}

$tag = 'BBSAL_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));

$username = $tag . '_employee';
$email = strtolower($username) . '@test.local';
$employeeCode = $tag . '_EMP';
$scheduleName = $tag . '_SHIFT';

$fullName = 'Black Box Salary Test Employee';
$monthlySalary = 30000.00;
$joiningDate = '2020-01-01';
$salaryMonth = date('Y-m') . '-01';

$pdo->beginTransaction();

try {
    /*
     * Create a dedicated employee user.
     * This account is not used for browser login.
     */
    $stmt = $pdo->prepare(
        "INSERT INTO users
            (role_id, username, email, password, full_name, status)
         VALUES
            (2, ?, ?, ?, ?, 'active')"
    );

    $stmt->execute([
        $username,
        $email,
        password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
        $fullName
    ]);

    $userId = (int) $pdo->lastInsertId();

    /*
     * Create employee profile with valid salary information.
     */
    $stmt = $pdo->prepare(
        'INSERT INTO employees
            (user_id, employee_code, department, designation,
             monthly_salary, joining_date)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    $stmt->execute([
        $userId,
        $employeeCode,
        'BLACK BOX TEST',
        'Salary Test Employee',
        $monthlySalary,
        $joiningDate
    ]);

    $employeeId = (int) $pdo->lastInsertId();

    /*
     * Create a dedicated work schedule.
     */
    $stmt = $pdo->prepare(
        'INSERT INTO work_schedules
            (name, expected_entry_time, expected_exit_time, working_hours)
         VALUES (?, ?, ?, ?)'
    );

    $stmt->execute([
        $scheduleName,
        '09:00:00',
        '18:00:00',
        9.00
    ]);

    $scheduleId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare(
        'INSERT INTO employee_schedules (employee_id, schedule_id)
         VALUES (?, ?)'
    );

    $stmt->execute([$employeeId, $scheduleId]);

    /*
     * Seed one uniquely owned salary record for the current month.
     * This allows the summary and filter tests to verify exact totals.
     */
    $calculationDetails = json_encode(
        [
            'test_fixture' => $tag,
            'employee' => [
                'employee_id' => $employeeId,
                'employee_code' => $employeeCode,
                'full_name' => $fullName,
                'joining_date' => $joiningDate
            ],
            'salary_month' => date('Y-m'),
            'daily_records' => []
        ],
        JSON_THROW_ON_ERROR
    );

    $stmt = $pdo->prepare(
        'INSERT INTO salary
            (employee_id, basic_salary, deductions, final_salary,
             salary_month, calculation_details, calculated_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())'
    );

    $stmt->execute([
        $employeeId,
        $monthlySalary,
        1234.56,
        28765.44,
        $salaryMonth,
        $calculationDetails
    ]);

    $salaryId = (int) $pdo->lastInsertId();

    $pdo->commit();

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $e;
}

$manifest = [
    'database' => $activeDb,
    'tag' => $tag,
    'createdAt' => date(DATE_ATOM),

    'userId' => $userId,
    'employeeId' => $employeeId,
    'employeeCode' => $employeeCode,
    'username' => $username,

    'scheduleId' => $scheduleId,
    'scheduleName' => $scheduleName,

    'salaryId' => $salaryId,
    'salaryMonth' => date('Y-m'),
    'monthlySalary' => $monthlySalary,

    'admin' => $accounts['admin'],
    'sharedEmployee' => $accounts['employee']
];

file_put_contents(
    $manifestPath,
    json_encode(
        $manifest,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    ),
    LOCK_EX
);

@chmod($manifestPath, 0600);

echo "Prepared Salary Management fixtures in {$activeDb}.\n";
echo "Created dedicated employee: {$employeeCode}\n";
echo "Created current-month salary fixture: {$salaryId}\n";
echo "Reused existing test administrator account.\n";