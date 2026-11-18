<?php
declare(strict_types=1);

/*
 * Employee Salary Details — Black-Box Test Fixture
 *
 * Usage:
 *   php prepare-employee-salary-details-data.php prepare
 *   php prepare-employee-salary-details-data.php cleanup
 *
 * Creates dedicated employee accounts and payroll test data.
 * This fixture is restricted to hris_management_system_test.
 */

$database = 'hris_management_system_test';
$manifestPath = __DIR__ . '/.employee-salary-details-test-data.json';

function stopFixture(string $message): never
{
    fwrite(STDERR, "SAFETY STOP: {$message}\n");
    exit(1);
}

$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=' . $database . ';charset=utf8mb4',
    'root',
    '',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);

if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== $database) {
    stopFixture('Connected database is not the test database.');
}

function cleanupFixture(PDO $pdo, array $data): void
{
    if (
        ($data['database'] ?? '') !== 'hris_management_system_test' ||
        !preg_match('/^BBESD_[A-Za-z0-9_]+$/', (string) ($data['tag'] ?? ''))
    ) {
        throw new RuntimeException('Unsafe fixture manifest.');
    }

    $pdo->beginTransaction();

    try {
        foreach (($data['employees'] ?? []) as $employee) {
            $employeeId = (int) ($employee['employeeId'] ?? 0);
            $userId = (int) ($employee['userId'] ?? 0);
            $username = (string) ($employee['username'] ?? '');
            $employeeCode = (string) ($employee['employeeCode'] ?? '');

            if (
                $employeeId <= 0 ||
                $userId <= 0 ||
                $username === '' ||
                $employeeCode === ''
            ) {
                throw new RuntimeException('Incomplete employee cleanup data.');
            }

            // Delete only rows belonging to this fixture employee.
            foreach ([
                'salary',
                'attendance',
                'attendance_scan_history',
                'leave_requests',
                'rfid_cards',
                'employee_schedules'
            ] as $table) {
                $stmt = $pdo->prepare(
                    "DELETE FROM {$table} WHERE employee_id = ?"
                );
                $stmt->execute([$employeeId]);
            }

            $stmt = $pdo->prepare(
                'DELETE FROM employees
                 WHERE id = ? AND user_id = ? AND employee_code = ?'
            );
            $stmt->execute([
                $employeeId,
                $userId,
                $employeeCode
            ]);

            $stmt = $pdo->prepare(
                'DELETE FROM users WHERE id = ? AND username = ?'
            );
            $stmt->execute([$userId, $username]);
        }

        $scheduleId = (int) ($data['scheduleId'] ?? 0);
        $scheduleName = (string) ($data['scheduleName'] ?? '');

        if ($scheduleId > 0 && $scheduleName !== '') {
            $stmt = $pdo->prepare(
                'DELETE FROM work_schedules WHERE id = ? AND name = ?'
            );
            $stmt->execute([$scheduleId, $scheduleName]);
        }

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
        echo "No employee Salary Details fixture found.\n";
        exit(0);
    }

    $data = json_decode(
        (string) file_get_contents($manifestPath),
        true
    );

    if (!is_array($data)) {
        stopFixture('Fixture manifest is invalid.');
    }

    cleanupFixture($pdo, $data);
    unlink($manifestPath);

    echo "Employee Salary Details fixture cleaned up.\n";
    exit(0);
}

if ($mode !== 'prepare') {
    stopFixture('Usage: php prepare-employee-salary-details-data.php prepare|cleanup');
}

// Clean up an earlier interrupted run of this exact fixture.
if (is_file($manifestPath)) {
    $old = json_decode(
        (string) file_get_contents($manifestPath),
        true
    );

    if (!is_array($old)) {
        stopFixture('Previous manifest is invalid. Inspect it before continuing.');
    }

    cleanupFixture($pdo, $old);
    unlink($manifestPath);
}

$tag = 'BBESD_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));

$fullName = 'Black Box Salary Details Employee';
$otherFullName = 'Other Black Box Payroll Employee';

$employeePassword = 'BBESD_Employee@2026';
$otherPassword = 'BBESD_Other@2026';

$monthlySalary = 30000.00;
$joiningDate = '2020-01-15';

$salaryMonth = date('Y-m');
$monthStart = new DateTimeImmutable(date('Y-m-01'));
$today = new DateTimeImmutable('today');

$calendarDays = (int) $monthStart->format('t');
$dailySalary = $monthlySalary / $calendarDays;

// Existing holidays are respected. This fixture does not modify holidays.
$holidayStmt = $pdo->prepare(
    'SELECT holiday_date
     FROM holidays
     WHERE holiday_date BETWEEN ? AND ?'
);

$holidayStmt->execute([
    $monthStart->format('Y-m-d'),
    $today->format('Y-m-d')
]);

$holidayDates = array_column($holidayStmt->fetchAll(), 'holiday_date');

// Completed eligible working days: exclude Fridays, holidays and today.
$workingDates = [];

for (
    $date = $monthStart;
    $date < $today;
    $date = $date->modify('+1 day')
) {
    $dateString = $date->format('Y-m-d');

    if (
        $date->format('N') !== '5' &&
        !in_array($dateString, $holidayDates, true)
    ) {
        $workingDates[] = $dateString;
    }
}

if (count($workingDates) < 6) {
    stopFixture(
        'There are not enough completed working days this month to prepare the full test scenario. Run the suite later in the month.'
    );
}

// Scenario:
// 1 full-day approved leave.
// 1 incomplete half-day approved leave.
// 4 late entries.
// 2 early exits.
// Remaining completed working days become absences.
$fullLeaveDate = $workingDates[0];
$halfLeaveDate = $workingDates[1];
$attendanceDates = array_slice($workingDates, 2, 4);

$lateCount = count($attendanceDates);
$earlyCount = min(2, $lateCount);

$taggedUsername = strtolower($tag);

$employees = [];

$pdo->beginTransaction();

try {
    // Create the employee work schedule.
    $scheduleName = $tag . '_SHIFT';

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

    // Create the main employee and the second employee used by
    // the URL-tampering / ownership test.
    $accountDefinitions = [
        [
            'suffix' => 'employee',
            'name' => $fullName,
            'password' => $employeePassword,
            'salary' => $monthlySalary
        ],
        [
            'suffix' => 'other',
            'name' => $otherFullName,
            'password' => $otherPassword,
            'salary' => 45000.00
        ],
        [
            'suffix' => 'empty',
            'name' => 'Black Box Empty Ledger Employee',
            'password' => 'BBESD_Empty@2026',
            'salary' => 30000.00,
            'joiningDate' => date('Y-m-d', strtotime('+1 day'))
        ]
    ];

    foreach ($accountDefinitions as $account) {
        $username = $taggedUsername . '_' . $account['suffix'];
        $email = $username . '@test.local';
        $employeeCode = $tag . '_' . strtoupper($account['suffix']);

        $stmt = $pdo->prepare(
            "INSERT INTO users
                (role_id, username, email, password, full_name, status)
             VALUES (2, ?, ?, ?, ?, 'active')"
        );

        $stmt->execute([
            $username,
            $email,
            password_hash($account['password'], PASSWORD_DEFAULT),
            $account['name']
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
            $account['salary'],
            $account['joiningDate'] ?? $joiningDate
        ]);

        $employeeId = (int) $pdo->lastInsertId();


        $stmt = $pdo->prepare(
            'INSERT INTO employee_schedules (employee_id, schedule_id)
             VALUES (?, ?)'
        );

        $stmt->execute([$employeeId, $scheduleId]);

        $employees[$account['suffix']] = [
            'userId' => $userId,
            'employeeId' => $employeeId,
            'username' => $username,
            'password' => $account['password'],
            'employeeCode' => $employeeCode,
            'fullName' => $account['name'],
            'monthlySalary' => $account['salary']
        ];
    }

    $mainEmployeeId = $employees['employee']['employeeId'];
    $otherEmployeeId = $employees['other']['employeeId'];

    // Approved full-day leave.
    $stmt = $pdo->prepare(
        "INSERT INTO leave_requests
            (employee_id, leave_type, start_date, end_date, reason, status)
         VALUES (?, 'Full Day', ?, ?, 'Black-box test fixture', 'approved')"
    );

    $stmt->execute([
        $mainEmployeeId,
        $fullLeaveDate,
        $fullLeaveDate
    ]);

    // Approved half-day leave without attendance, so it is incomplete.
    $stmt = $pdo->prepare(
        "INSERT INTO leave_requests
            (employee_id, leave_type, half_day_period,
             start_date, end_date, reason, status)
         VALUES (?, 'Half Day', 'first_half', ?, ?, ?, 'approved')"
    );

    $stmt->execute([
        $mainEmployeeId,
        $halfLeaveDate,
        $halfLeaveDate,
        'Black-box incomplete half-day fixture'
    ]);

    // Attendance records: each date has a late entry.
    // The first two also have an early exit.
    $stmt = $pdo->prepare(
        'INSERT INTO attendance
            (employee_id, attendance_date, entry_time, entry_status,
             exit_time, exit_status, status)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    foreach ($attendanceDates as $index => $date) {
        $entryTime = $date . ' 09:45:00';
        $exitTime = $index < $earlyCount
            ? $date . ' 17:30:00'
            : $date . ' 18:00:00';

        $stmt->execute([
            $mainEmployeeId,
            $date,
            $entryTime,
            'Entry - Late',
            $exitTime,
            $index < $earlyCount ? 'Exit - Before' : 'Exit - in time',
            'late'
        ]);
    }

    // Add a distinct salary record for the other employee.
    // The main employee's salary is recalculated by the controller.
    $otherDetails = json_encode([
        'employee' => [
            'employee_id' => $otherEmployeeId,
            'employee_code' => $employees['other']['employeeCode'],
            'full_name' => $otherFullName,
            'joining_date' => $joiningDate
        ],
        'salary_month' => $salaryMonth,
        'monthly_salary' => 45000,
        'daily_salary' => round(45000 / $calendarDays, 2),
        'calendar_days' => $calendarDays,
        'calculated_through' => $today->format('Y-m-d'),
        'daily_records' => []
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

    $stmt = $pdo->prepare(
        'INSERT INTO salary
            (employee_id, basic_salary, deductions, final_salary,
             salary_month, calculation_details, calculated_at)
         VALUES (?, 45000, 0, 45000, ?, ?, NOW())'
    );

    $stmt->execute([
        $otherEmployeeId,
        $monthStart->format('Y-m-d'),
        $otherDetails
    ]);

    // Store the actual salary record ID for the ownership test.
    $otherSalaryId = (int) $pdo->lastInsertId();

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $e;
}

// Expected values derived from the fixture dates and the same
// salary rules used by the application.
$paidFridays = 0;

for (
    $date = $monthStart;
    $date <= $today;
    $date = $date->modify('+1 day')
) {
    if ($date->format('N') === '5') {
        $paidFridays++;
    }
}

$paidHolidays = 0;

foreach ($holidayDates as $holidayDate) {
    if (date('N', strtotime($holidayDate)) !== '5') {
        $paidHolidays++;
    }
}

// The salary summary includes today's eligible working day,
// even though today's attendance evaluation is still in progress.
$eligibleDays = count($workingDates);

$absentDays = max(
    0,
    $eligibleDays - 1 - 1 - count($attendanceDates)
);

$absenceDeduction = round($absentDays * $dailySalary, 2);
$halfDayDeduction = round($dailySalary / 2, 2);

$totalOccurrences = $lateCount + $earlyCount;
$deductibleOccurrences = max(0, $totalOccurrences - 3);
$lateEarlyDeduction = round(
    $deductibleOccurrences * ($dailySalary / 2),
    2
);

$totalDeductions = round(
    $absenceDeduction + $halfDayDeduction + $lateEarlyDeduction,
    2
);

$payableSalary = max(
    0,
    round(
        $monthlySalary *
        ($eligibleDays + $paidFridays + $paidHolidays) /
        $calendarDays -
        $totalDeductions,
        2
    )
);

$manifest = [
    'database' => $database,
    'tag' => $tag,
    'createdAt' => date(DATE_ATOM),
    'salaryMonth' => $salaryMonth,
    'calendarDays' => $calendarDays,
    'dailySalary' => round($dailySalary, 2),
    'eligibleDays' => $eligibleDays,
    'paidFridays' => $paidFridays,
    'paidHolidays' => $paidHolidays,
    'fullDayLeaves' => 1,
    'incompleteHalfDayLeaves' => 1,
    'absentDays' => $absentDays,
    'lateOccurrences' => $lateCount,
    'earlyExitOccurrences' => $earlyCount,
    'absenceDeduction' => $absenceDeduction,
    'halfDayDeduction' => $halfDayDeduction,
    'lateEarlyDeduction' => $lateEarlyDeduction,
    'totalDeductions' => $totalDeductions,
    'payableSalary' => $payableSalary,
    'fullLeaveDate' => $fullLeaveDate,
    'halfLeaveDate' => $halfLeaveDate,
    'attendanceDates' => $attendanceDates,
    'scheduleId' => $scheduleId,
    'scheduleName' => $scheduleName,
    'otherSalaryId' => $otherSalaryId,
    'employees' => $employees
];

$manifestJson = json_encode(
    $manifest,
    JSON_PRETTY_PRINT |
    JSON_UNESCAPED_SLASHES |
    JSON_THROW_ON_ERROR
);

if (
    file_put_contents(
        $manifestPath,
        $manifestJson,
        LOCK_EX
    ) === false
) {
    fwrite(
        STDERR,
        "WARNING: Fixture data was created, but the manifest could not be saved.\n"
    );

    fwrite(
        STDERR,
        "Do not rerun prepare until the created fixture records have been cleaned up.\n"
    );

    exit(1);
}

chmod($manifestPath, 0600);

echo "Employee Salary Details fixture prepared.\n";
echo "Database: {$database}\n";
echo "Main employee: {$employees['employee']['employeeCode']}\n";
echo "Other employee: {$employees['other']['employeeCode']}\n";
