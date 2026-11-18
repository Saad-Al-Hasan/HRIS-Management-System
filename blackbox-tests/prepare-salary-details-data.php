<?php
declare(strict_types=1);

/*
 * Salary Details Black-Box test fixture.
 *
 * Usage:
 *   php prepare-salary-details-data.php prepare
 *   php prepare-salary-details-data.php cleanup
 *
 * Creates one isolated employee + salary record in
 * hris_management_system_test. Reuses the existing test admin and
 * employee accounts from .holiday-test-accounts.json.
 */

$expectedDb = 'hris_management_system_test';
$accountPath = __DIR__ . '/.holiday-test-accounts.json';
$manifestPath = __DIR__ . '/.salary-details-test-data.json';

function abortFixture(string $message): never
{
    fwrite(STDERR, "ABORTED: {$message}\n");
    exit(1);
}

if (!is_file($accountPath)) {
    abortFixture('Existing .holiday-test-accounts.json is missing. Run the existing test-account setup first.');
}

$accounts = json_decode((string) file_get_contents($accountPath), true);

if (
    !is_array($accounts) ||
    ($accounts['database'] ?? '') !== $expectedDb ||
    empty($accounts['admin']['id']) ||
    empty($accounts['admin']['username']) ||
    empty($accounts['admin']['password']) ||
    empty($accounts['employee']['id']) ||
    empty($accounts['employee']['username']) ||
    empty($accounts['employee']['password'])
) {
    abortFixture('The existing test-account manifest is invalid or belongs to another database.');
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
    abortFixture("Connected to {$activeDb}; expected {$expectedDb}.");
}

/* Validate the reused accounts before creating any fixture data. */
$accountCheck = $pdo->prepare(
    'SELECT id, role_id, status FROM users WHERE id = ? AND username = ? LIMIT 1'
);

$accountCheck->execute([
    (int) $accounts['admin']['id'],
    $accounts['admin']['username']
]);
$adminRow = $accountCheck->fetch();

if (
    !$adminRow ||
    (int) $adminRow['role_id'] !== 1 ||
    $adminRow['status'] !== 'active'
) {
    abortFixture('The existing test administrator is not active or does not have administrator role.');
}

$accountCheck->execute([
    (int) $accounts['employee']['id'],
    $accounts['employee']['username']
]);
$employeeAccountRow = $accountCheck->fetch();

if (
    !$employeeAccountRow ||
    (int) $employeeAccountRow['role_id'] !== 2 ||
    $employeeAccountRow['status'] !== 'active'
) {
    abortFixture('The existing test employee is not active or does not have employee role.');
}

/*
 * Delete only the fixture whose unique tag and IDs are recorded in
 * this suite's own manifest. Never delete the shared login accounts.
 */
function cleanupSalaryDetailsFixture(PDO $pdo, array $data): void
{
    if (
        ($data['database'] ?? '') !== 'hris_management_system_test' ||
        !str_starts_with((string) ($data['tag'] ?? ''), 'BBSD_')
    ) {
        throw new RuntimeException('Unsafe Salary Details fixture manifest.');
    }

    $salaryId = (int) ($data['salaryId'] ?? 0);
    $employeeId = (int) ($data['employeeId'] ?? 0);
    $userId = (int) ($data['userId'] ?? 0);
    $scheduleId = (int) ($data['scheduleId'] ?? 0);

    if (
        $salaryId <= 0 ||
        $employeeId <= 0 ||
        $userId <= 0 ||
        $scheduleId <= 0 ||
        empty($data['employeeCode']) ||
        empty($data['username']) ||
        empty($data['scheduleName'])
    ) {
        throw new RuntimeException('Salary Details fixture manifest is incomplete.');
    }

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'DELETE FROM salary WHERE id = ? AND employee_id = ?'
        );
        $stmt->execute([$salaryId, $employeeId]);

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
        echo "No Salary Details fixture manifest found.\n";
        exit(0);
    }

    $data = json_decode((string) file_get_contents($manifestPath), true);

    if (!is_array($data)) {
        abortFixture('Salary Details fixture manifest cannot be decoded.');
    }

    cleanupSalaryDetailsFixture($pdo, $data);
    unlink($manifestPath);

    echo "Salary Details fixture cleanup completed in {$expectedDb}.\n";
    exit(0);
}

if ($mode !== 'prepare') {
    abortFixture('Usage: php prepare-salary-details-data.php prepare|cleanup');
}

/* Remove only this suite's previous fixture, if a prior run was interrupted. */
if (is_file($manifestPath)) {
    $oldData = json_decode((string) file_get_contents($manifestPath), true);

    if (!is_array($oldData)) {
        abortFixture('Previous Salary Details manifest is invalid; inspect it before continuing.');
    }

    cleanupSalaryDetailsFixture($pdo, $oldData);
    unlink($manifestPath);
}

$tag = 'BBSD_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
$username = $tag . '_employee';
$email = strtolower($username) . '@test.local';
$employeeCode = $tag . '_EMP';
$scheduleName = $tag . '_SHIFT';
$fullName = 'Black Box Salary Details Employee';

$monthlySalary = 30000.00;
$joiningDate = '2020-01-15';
$salaryMonth = date('Y-m') . '-01';
$daysInMonth = (int) date('t', strtotime($salaryMonth));
$dailySalary = round($monthlySalary / $daysInMonth, 2);
$halfDaySalary = round($dailySalary / 2, 2);

$absentDays = 1;
$incompleteHalfDayLeaves = 1;
$lateOccurrences = 4;
$earlyExitOccurrences = 2;
$chargeableOccurrences = max(
    0,
    $lateOccurrences + $earlyExitOccurrences - 3
);

$absenceDeduction = round($dailySalary * $absentDays, 2);
$halfDayDeduction = $halfDaySalary;
$lateEarlyDeduction = round($halfDaySalary * $chargeableOccurrences, 2);
$totalDeductions = round(
    $absenceDeduction + $halfDayDeduction + $lateEarlyDeduction,
    2
);
$finalSalary = round($monthlySalary - $totalDeductions, 2);

$today = date('Y-m-d');
$firstDay = date('Y-m-01');
$secondDay = date('Y-m-02');

$calculationDetails = [
    'test_fixture' => $tag,
    'employee' => [
        'employee_id' => 0,
        'employee_code' => $employeeCode,
        'full_name' => $fullName,
        'joining_date' => $joiningDate
    ],
    'calculated_through' => $today,
    'daily_salary' => $dailySalary,
    'calendar_days' => $daysInMonth,
    'eligible_days' => max(0, $daysInMonth - 8),
    'paid_fridays' => 4,
    'paid_holidays' => 1,
    'full_day_leaves' => 1,
    'valid_half_day_leaves' => 1,
    'incomplete_half_day_leaves' => $incompleteHalfDayLeaves,
    'absent_days' => $absentDays,
    'absence_deduction' => $absenceDeduction,
    'half_day_deduction' => $halfDayDeduction,
    'late_occurrences' => $lateOccurrences,
    'early_exit_occurrences' => $earlyExitOccurrences,
    'late_early_deduction' => $lateEarlyDeduction,
    'daily_records' => [
        [
            'date' => $firstDay,
            'type' => 'Present',
            'entry_time' => '08:55:00',
            'entry_status' => 'In Time',
            'exit_time' => '18:00:00',
            'exit_status' => 'In Time',
            'deduction' => 0,
            'note' => 'Black-box fixture: regular attendance'
        ],
        [
            'date' => $secondDay,
            'type' => 'Absent',
            'entry_time' => null,
            'entry_status' => null,
            'exit_time' => null,
            'exit_status' => null,
            'deduction' => $absenceDeduction,
            'note' => 'Black-box fixture: unapproved absence'
        ]
    ]
];

$pdo->beginTransaction();

try {
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
        'Salary Details Test Employee',
        $monthlySalary,
        $joiningDate
    ]);
    $employeeId = (int) $pdo->lastInsertId();

    $calculationDetails['employee']['employee_id'] = $employeeId;

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

    $stmt = $pdo->prepare(
        'INSERT INTO salary
            (employee_id, basic_salary, deductions, final_salary,
             salary_month, calculation_details, calculated_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        $employeeId,
        $monthlySalary,
        $totalDeductions,
        $finalSalary,
        $salaryMonth,
        json_encode(
            $calculationDetails,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
        )
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
    'salaryMonthLabel' => date('F Y', strtotime($salaryMonth)),
    'fullName' => $fullName,
    'joiningDateLabel' => date('d M Y', strtotime($joiningDate)),
    'calculatedThroughLabel' => date('d M Y', strtotime($today)),
    'monthlySalary' => $monthlySalary,
    'dailySalary' => $dailySalary,
    'totalDeductions' => $totalDeductions,
    'finalSalary' => $finalSalary,
    'calendarDays' => $daysInMonth,
    'eligibleDays' => max(0, $daysInMonth - 8),
    'absentDays' => $absentDays,
    'incompleteHalfDayLeaves' => $incompleteHalfDayLeaves,
    'lateOccurrences' => $lateOccurrences,
    'earlyExitOccurrences' => $earlyExitOccurrences,
    'lateEarlyDeduction' => $lateEarlyDeduction,
    'admin' => $accounts['admin'],
    'sharedEmployee' => $accounts['employee']
];

file_put_contents(
    $manifestPath,
    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
    LOCK_EX
);
@chmod($manifestPath, 0600);

echo "Prepared Salary Details fixture in {$activeDb}.\n";
echo "Created dedicated salary employee: {$employeeCode}\n";
echo "Created salary record: {$salaryId}\n";
echo "Reused existing administrator and employee login accounts.\n";
