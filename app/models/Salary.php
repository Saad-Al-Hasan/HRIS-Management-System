<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/AuditLog.php';

class Salary extends Model
{
    /**
     * Retrieve an employee's salary information.
     */
    public function getEmployeeSalaryInfo(int $employeeId): ?array
    {
        $sql = "
            SELECT
                e.id AS employee_id,
                e.employee_code,
                e.monthly_salary,
                e.joining_date,
                u.full_name
            FROM employees e
            INNER JOIN users u
                ON u.id = e.user_id
            WHERE e.id = :employee_id
              AND u.role_id = 2
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':employee_id' => $employeeId
        ]);

        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        return $employee ?: null;
    }


    /**
     * Retrieve attendance records for an employee
     * within a selected calendar month.
     *
     * Admin-edited values take priority independently
     * for each attendance field.
     */
    public function getMonthlyAttendance(
        int $employeeId,
        string $startDate,
        string $endDate
    ): array {
        $sql = "
            SELECT
                attendance_date,

COALESCE(A_E_entry_time, entry_time) AS entry_time,

                COALESCE(
                    NULLIF(A_E_entry_status, ''),
                    entry_status
                ) AS entry_status,

COALESCE(A_E_exit_time, exit_time) AS exit_time,

                COALESCE(
                    NULLIF(A_E_exit_status, ''),
                    exit_status
                ) AS exit_status,

                COALESCE(
                    NULLIF(A_E_status, ''),
                    status
                ) AS attendance_status

            FROM attendance

            WHERE employee_id = :employee_id
              AND attendance_date BETWEEN :start_date AND :end_date

            ORDER BY attendance_date ASC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':employee_id' => $employeeId,
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /**
     * Retrieve approved leave requests overlapping
     * the selected month.
     */
    public function getMonthlyApprovedLeaves(
        int $employeeId,
        string $startDate,
        string $endDate
    ): array {
        $sql = "
            SELECT
                id,
                leave_type,
                half_day_period,
                start_date,
                end_date,
                status

            FROM leave_requests

            WHERE employee_id = :employee_id
              AND status = 'approved'
              AND start_date <= :end_date
              AND end_date >= :start_date

            ORDER BY start_date ASC, id ASC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':employee_id' => $employeeId,
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /**
     * Retrieve organization holidays within a month.
     */
    public function getMonthlyHolidays(
        string $startDate,
        string $endDate
    ): array {
        $sql = "
            SELECT
                holiday_date,
                holiday_name,
                holiday_type

            FROM holidays

            WHERE holiday_date BETWEEN :start_date AND :end_date

            ORDER BY holiday_date ASC
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /**
     * Retrieve the employee's currently assigned work schedule.
     */
    public function getEmployeeWorkSchedule(int $employeeId): ?array
    {
        $sql = "
            SELECT
                ws.id,
                ws.name,
                ws.expected_entry_time,
                ws.expected_exit_time,
                ws.working_hours

            FROM employee_schedules es

            INNER JOIN work_schedules ws
                ON ws.id = es.schedule_id

            WHERE es.employee_id = :employee_id

            ORDER BY es.id DESC
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':employee_id' => $employeeId
        ]);

        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($schedule) {
            return $schedule;
        }

        // Match the existing employee default schedule behavior.
        $defaultSql = "
            SELECT
                id,
                name,
                expected_entry_time,
                expected_exit_time,
                working_hours
            FROM work_schedules
            WHERE name = '9 AM - 6 PM'
            LIMIT 1
        ";

        $defaultStmt = $this->db->prepare($defaultSql);
        $defaultStmt->execute();

        $defaultSchedule = $defaultStmt->fetch(PDO::FETCH_ASSOC);

        return $defaultSchedule ?: null;
    }


    /**
     * Calculate an employee's salary for a selected month.
     *
     * Calculates only up to today for the current month.
     * Past months are calculated through their last calendar day.
     * Future dates are never counted as absences.
     */
    public function calculateMonthlySalary(
        int $employeeId,
        string $month
    ): array {

        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            throw new InvalidArgumentException('Invalid salary month.');
        }

        $monthStart = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $month . '-01'
        );

        if (!$monthStart || $monthStart->format('Y-m') !== $month) {
            throw new InvalidArgumentException('Invalid salary month.');
        }

        $today = new DateTimeImmutable('today');

        if ($monthStart > $today) {
            throw new InvalidArgumentException(
                'Salary cannot be calculated for a future month.'
            );
        }

        $monthEnd = $monthStart->modify('last day of this month');

        $calculationEnd = $monthEnd < $today
            ? $monthEnd
            : $today;

        $employee = $this->getEmployeeSalaryInfo($employeeId);

        if (!$employee) {
            throw new RuntimeException('Employee not found.');
        }

        $monthlySalary = (float) ($employee['monthly_salary'] ?? 0);

        if ($monthlySalary <= 0) {
            throw new RuntimeException(
                'Monthly salary has not been configured for this employee.'
            );
        }

        if (empty($employee['joining_date'])) {
            throw new RuntimeException(
                'Employee joining date is missing.'
            );
        }

        $joiningDate = new DateTimeImmutable(
            (string) $employee['joining_date']
        );

        // Start from the later of joining date or month start.
        $eligibleStart = $joiningDate > $monthStart
            ? $joiningDate
            : $monthStart;

        $calendarDays = (int) $monthStart->format('t');

        $dailySalary = $monthlySalary / $calendarDays;

        $attendance = $this->getMonthlyAttendance(
            $employeeId,
            $monthStart->format('Y-m-d'),
            $calculationEnd->format('Y-m-d')
        );

        $approvedLeaves = $this->getMonthlyApprovedLeaves(
            $employeeId,
            $monthStart->format('Y-m-d'),
            $calculationEnd->format('Y-m-d')
        );

        $holidays = $this->getMonthlyHolidays(
            $monthStart->format('Y-m-d'),
            $calculationEnd->format('Y-m-d')
        );

        $schedule = $this->getEmployeeWorkSchedule($employeeId);

        if (!$schedule) {
            throw new RuntimeException(
                'No work schedule is available for salary calculation.'
            );
        }

        // Index attendance by date.
        $attendanceByDate = [];

        foreach ($attendance as $record) {
            $attendanceByDate[$record['attendance_date']] = $record;
        }

        // Index holidays by date.
        $holidayByDate = [];

        foreach ($holidays as $holiday) {
            $holidayByDate[$holiday['holiday_date']] = $holiday;
        }

        $breakdown = [
            'employee' => [
                'employee_id' => $employeeId,
                'employee_code' => $employee['employee_code'],
                'full_name' => $employee['full_name'],
                'joining_date' => $employee['joining_date']
            ],

            'salary_month' => $month,
            'monthly_salary' => round($monthlySalary, 2),
            'daily_salary' => round($dailySalary, 2),
            'calendar_days' => $calendarDays,

            'eligible_from' => $eligibleStart->format('Y-m-d'),
            'calculated_through' => $calculationEnd->format('Y-m-d'),

            'eligible_days' => 0,
            'paid_fridays' => 0,
            'paid_holidays' => 0,
            'full_day_leaves' => 0,
            'valid_half_day_leaves' => 0,
            'incomplete_half_day_leaves' => 0,
            'absent_days' => 0,

            'late_occurrences' => 0,
            'early_exit_occurrences' => 0,
            'free_occurrences' => 0,
            'deductible_occurrences' => 0,

            'absence_deduction' => 0,
            'half_day_deduction' => 0,
            'late_early_deduction' => 0,

            'total_deductions' => 0,
            'payable_salary' => 0,

            'daily_records' => []
        ];

        $absenceDeduction = 0;
        $halfDayDeduction = 0;
        $lateOccurrences = 0;
        $earlyExitOccurrences = 0;
        $totalOccurrenceCount = 0;

        if ($eligibleStart <= $calculationEnd) {

            for (
                $date = $eligibleStart;
                $date <= $calculationEnd;
                $date = $date->modify('+1 day')
            ) {

                $dateString = $date->format('Y-m-d');

                $record = $attendanceByDate[$dateString] ?? null;

                $dayDetails = [
                    'date' => $dateString,
                    'type' => 'Working Day',
                    'entry_time' => $record['entry_time'] ?? null,
                    'entry_status' => $record['entry_status'] ?? null,
                    'exit_time' => $record['exit_time'] ?? null,
                    'exit_status' => $record['exit_status'] ?? null,
                    'deduction' => 0,
                    'note' => ''
                ];

                // Fridays are paid automatically.
                if ($date->format('N') === '5') {

                    $breakdown['paid_fridays']++;
                    $dayDetails['type'] = 'Friday';
                    $dayDetails['note'] = 'Paid weekly holiday';

                    $breakdown['daily_records'][] = $dayDetails;
                    continue;
                }

                // Government and organizational holidays are paid.
                if (isset($holidayByDate[$dateString])) {

                    $breakdown['paid_holidays']++;
                    $dayDetails['type'] = 'Holiday';
                    $dayDetails['note'] =
                        $holidayByDate[$dateString]['holiday_name'];

                    $breakdown['daily_records'][] = $dayDetails;
                    continue;
                }



                // Find an approved leave covering this date.
                $approvedLeave = null;

                foreach ($approvedLeaves as $leave) {

                    if (
                        $dateString >= $leave['start_date'] &&
                        $dateString <= $leave['end_date']
                    ) {
                        $approvedLeave = $leave;
                        break;
                    }
                }

                // Full-day approved leave is fully paid.
                if (
                    $approvedLeave &&
                    stripos(
                        (string) $approvedLeave['leave_type'],
                        'full'
                    ) !== false
                ) {

                    $breakdown['eligible_days']++;
                    $breakdown['full_day_leaves']++;

                    $dayDetails['type'] = 'Full Day Leave';
                    $dayDetails['note'] = 'Approved and fully paid';

                    $breakdown['daily_records'][] = $dayDetails;
                    continue;
                }

                // Approved half-day leave.
                if (
                    $approvedLeave &&
                    stripos(
                        (string) $approvedLeave['leave_type'],
                        'half'
                    ) !== false
                ) {

                    // Do not evaluate today's half-day leave prematurely.
                    if ($dateString === $today->format('Y-m-d')) {

                        $dayDetails['type'] = 'Half Day Leave - In Progress';
                        $dayDetails['note'] =
                            'Awaiting completion of the required working half';

                        $breakdown['daily_records'][] = $dayDetails;
                        continue;
                    }

                    // Completed half-day leave contributes to paid days.
                    $breakdown['eligible_days']++;

                    $workedRequiredHalf = $this->hasWorkedRequiredHalfDay(
                        $approvedLeave,
                        $record,
                        $schedule
                    );

                    if ($workedRequiredHalf) {

                        $breakdown['valid_half_day_leaves']++;

                        $dayDetails['type'] = 'Half Day Leave';
                        $dayDetails['note'] =
                            'Required working half completed; no deduction';

                    } else {

                        $breakdown['incomplete_half_day_leaves']++;

                        $halfDayDeduction += $dailySalary / 2;

                        $dayDetails['type'] = 'Incomplete Half Day Leave';
                        $dayDetails['note'] =
                            'Remaining working half not completed';

                        $dayDetails['deduction'] =
                            round($dailySalary / 2, 2);
                    }

                    // No late or early-exit penalties on half-day leave dates.
                    $breakdown['daily_records'][] = $dayDetails;
                    continue;
                }

                // Today's date is still in progress. Include any recorded
                // attendance, but never mark today absent before it ends.
                $attendanceStatus = strtolower(
                    trim((string) ($record['attendance_status'] ?? ''))
                );

                $isToday = ($dateString === $today->format('Y-m-d'));

                // Today's working day is incomplete.
                // Do not include its salary or attendance penalties yet.
                if ($isToday) {
                    $dayDetails['type'] = 'In Progress';
                    $dayDetails['note'] =
                        'Today is not complete; salary and attendance evaluation deferred';

                    $breakdown['daily_records'][] = $dayDetails;
                    continue;
                }

                // Count completed eligible working days,
                // including approved full-day and half-day leave.
                $breakdown['eligible_days']++;


                // For completed days, no attendance or an explicit absent
                // status results in one full daily salary deduction.
                if (!$record || $attendanceStatus === 'absent') {

                    $breakdown['absent_days']++;

                    $absenceDeduction += $dailySalary;

                    $dayDetails['type'] = 'Absent';
                    $dayDetails['note'] = 'No approved leave or attendance';
                    $dayDetails['deduction'] = round($dailySalary, 2);

                    $breakdown['daily_records'][] = $dayDetails;
                    continue;
                }

                // Count late entry and early exit independently.
                $entryStatus = strtolower(
                    trim((string) ($record['entry_status'] ?? ''))
                );

                $exitStatus = strtolower(
                    trim((string) ($record['exit_status'] ?? ''))
                );

                // Each late entry is a separate occurrence.
                if (strpos($entryStatus, 'late') !== false) {

                    $lateOccurrences++;
                    $totalOccurrenceCount++;

                    // First three monthly occurrences are free.
                    // Every subsequent occurrence deducts half a day's salary.
                    if ($totalOccurrenceCount > 3) {
                        $dayDetails['deduction'] += $dailySalary / 2;
                    }
                }

                // Each early exit is another independent occurrence.
                if (strpos($exitStatus, 'before') !== false) {

                    $earlyExitOccurrences++;
                    $totalOccurrenceCount++;

                    if ($totalOccurrenceCount > 3) {
                        $dayDetails['deduction'] += $dailySalary / 2;
                    }
                }

                $dayDetails['deduction'] = round(
                    $dayDetails['deduction'],
                    2
                );

                $dayDetails['type'] = 'Present';
                $dayDetails['note'] = 'Attendance recorded';

                $breakdown['daily_records'][] = $dayDetails;
            }
        }

        $totalOccurrences = $lateOccurrences + $earlyExitOccurrences;

        $freeOccurrences = min(3, $totalOccurrences);

        $deductibleOccurrences = max(0, $totalOccurrences - 3);

        // Each occurrence after the first three deducts half a day.
        $lateEarlyDeduction =
            $deductibleOccurrences * ($dailySalary / 2);

        $totalDeductions =
            $absenceDeduction +
            $halfDayDeduction +
            $lateEarlyDeduction;

        $payableSalary = max(
            0,
            $monthlySalary *
            (
                $breakdown['eligible_days'] +
                $breakdown['paid_fridays'] +
                $breakdown['paid_holidays']
            ) / $calendarDays
            - $totalDeductions
        );

        $breakdown['late_occurrences'] = $lateOccurrences;
        $breakdown['early_exit_occurrences'] = $earlyExitOccurrences;
        $breakdown['free_occurrences'] = $freeOccurrences;
        $breakdown['deductible_occurrences'] = $deductibleOccurrences;

        $breakdown['absence_deduction'] = round($absenceDeduction, 2);
        $breakdown['half_day_deduction'] = round($halfDayDeduction, 2);
        $breakdown['late_early_deduction'] =
            round($lateEarlyDeduction, 2);

        $breakdown['total_deductions'] = round($totalDeductions, 2);
        $breakdown['payable_salary'] = round($payableSalary, 2);

        return $breakdown;
    }



    /**
     * Store or update an employee's monthly salary calculation.
     *
     * One salary record is maintained per employee per month.
     */
    public function saveMonthlySalary(
        array $breakdown,
        ?int $auditActorId = null
    ): bool {
        $requiredKeys = [
            'employee',
            'salary_month',
            'monthly_salary',
            'total_deductions',
            'payable_salary'
        ];

        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $breakdown)) {
                throw new InvalidArgumentException(
                    "Missing salary calculation field: {$key}"
                );
            }
        }

        $employeeId = (int) (
            $breakdown['employee']['employee_id'] ?? 0
        );

        if ($employeeId <= 0) {
            throw new InvalidArgumentException(
                'Invalid employee ID.'
            );
        }

        $month = (string) $breakdown['salary_month'];

        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            throw new InvalidArgumentException(
                'Invalid salary month.'
            );
        }

        $monthDate = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $month . '-01'
        );

        if (!$monthDate || $monthDate->format('Y-m') !== $month) {
            throw new InvalidArgumentException(
                'Invalid salary month.'
            );
        }

        $basicSalary = (float) $breakdown['monthly_salary'];
        $deductions = (float) $breakdown['total_deductions'];
        $finalSalary = (float) $breakdown['payable_salary'];

        if (
            $basicSalary < 0 ||
            $deductions < 0 ||
            $finalSalary < 0
        ) {
            throw new InvalidArgumentException(
                'Salary amounts cannot be negative.'
            );
        }

        $calculationDetails = json_encode(
            $breakdown,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
        );

        $sql = "
            INSERT INTO salary (
                employee_id,
                basic_salary,
                deductions,
                final_salary,
                calculation_details,
                calculated_at,
                salary_month
            )
            VALUES (
                :employee_id,
                :basic_salary,
                :deductions,
                :final_salary,
                :calculation_details,
                NOW(),
                :salary_month
            ) AS new
            ON DUPLICATE KEY UPDATE
                basic_salary = new.basic_salary,
                deductions = new.deductions,
                final_salary = new.final_salary,
                calculation_details = new.calculation_details,
                calculated_at = NOW()
        ";

        $stmt = $this->db->prepare($sql);

        $params = [
            ':employee_id' => $employeeId,
            ':basic_salary' => round($basicSalary, 2),
            ':deductions' => round($deductions, 2),
            ':final_salary' => round($finalSalary, 2),
            ':calculation_details' => $calculationDetails,
            ':salary_month' => $monthDate->format('Y-m-d')
        ];

        // Automatic calculations do not generate audit records.
        if ($auditActorId === null) {
            return $stmt->execute($params);
        }

        // Manual administrator calculations are audited transactionally.
        $this->db->beginTransaction();

        try {
            // Check whether a salary record already exists for this employee/month.
            $existingStmt = $this->db->prepare("
        SELECT id
        FROM salary
        WHERE employee_id = :employee_id
          AND salary_month = :salary_month
        LIMIT 1
    ");

            $existingStmt->execute([
                ':employee_id' => $employeeId,
                ':salary_month' => $monthDate->format('Y-m-d')
            ]);

            $isRecalculation = (bool) $existingStmt->fetchColumn();

            $saved = $stmt->execute($params);

            if (!$saved) {
                throw new RuntimeException(
                    'Unable to save the monthly salary record.'
                );
            }

            $employeeCode = (string) (
                $breakdown['employee']['employee_code'] ?? 'Unknown'
            );

            $action = $isRecalculation
                ? 'Monthly salary recalculated for employee '
                : 'Monthly salary processed for employee ';

            $auditLog = new AuditLog($this->db);

            $logged = $auditLog->create(
                $action
                . $employeeCode
                . ' ('
                . $month
                . ')',
                $auditActorId
            );

            if (!$logged) {
                throw new RuntimeException(
                    'Unable to create the salary audit record.'
                );
            }

            $this->db->commit();

            return true;

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }



    /**
     * Retrieve stored salary records with optional filters.
     *
     * @param string|null $month YYYY-MM
     * @param int|null $employeeId
     */
    public function getSalaryHistory(
        ?string $month = null,
        ?int $employeeId = null
    ): array {

        $sql = "
            SELECT
                s.id,
                s.employee_id,
                s.basic_salary,
                s.deductions,
                s.final_salary,
                s.calculated_at,
                s.salary_month,
                e.employee_code,
                u.full_name

            FROM salary s

            INNER JOIN employees e
                ON e.id = s.employee_id

            INNER JOIN users u
                ON u.id = e.user_id

            WHERE u.role_id = 2
        ";

        $params = [];

        if ($month !== null && $month !== '') {

            if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
                throw new InvalidArgumentException(
                    'Invalid salary month filter.'
                );
            }

            $sql .= " AND s.salary_month = :salary_month";

            $params[':salary_month'] = $month . '-01';
        }

        if ($employeeId !== null) {

            if ($employeeId <= 0) {
                throw new InvalidArgumentException(
                    'Invalid employee ID filter.'
                );
            }

            $sql .= " AND s.employee_id = :employee_id";

            $params[':employee_id'] = $employeeId;
        }

        $sql .= "
    ORDER BY
        s.salary_month DESC,
        e.employee_code ASC
";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /**
     * Retrieve one stored salary record with its full breakdown.
     */
    public function getSalaryDetails(int $salaryId): ?array
    {
        if ($salaryId <= 0) {
            throw new InvalidArgumentException(
                'Invalid salary record ID.'
            );
        }

        $sql = "
            SELECT
                s.id,
                s.employee_id,
                s.basic_salary,
                s.deductions,
                s.final_salary,
                s.calculation_details,
                s.calculated_at,
s.salary_month,
e.employee_code,
e.user_id,
u.full_name

            FROM salary s

            INNER JOIN employees e
                ON e.id = s.employee_id

            INNER JOIN users u
                ON u.id = e.user_id

            WHERE s.id = :salary_id
              AND u.role_id = 2

            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':salary_id' => $salaryId
        ]);

        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            return null;
        }

        $record['calculation_details'] = json_decode(
            $record['calculation_details'],
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        return $record;
    }


    /**
     * Check whether the employee completed the required
     * working half of an approved half-day leave.
     *
     * Entry grace period: 30 minutes.
     * Exit grace period: none.
     */
    private function hasWorkedRequiredHalfDay(
        array $leave,
        ?array $attendance,
        array $schedule
    ): bool {

        if (!$attendance) {
            return false;
        }

        $shiftStart = $this->timeToSeconds(
            $schedule['expected_entry_time'] ?? null
        );

        $shiftEnd = $this->timeToSeconds(
            $schedule['expected_exit_time'] ?? null
        );

        $actualEntry = $this->timeToSeconds(
            $attendance['entry_time'] ?? null
        );

        $actualExit = $this->timeToSeconds(
            $attendance['exit_time'] ?? null
        );

        if (
            $shiftStart === null ||
            $shiftEnd === null ||
            $actualEntry === null ||
            $actualExit === null ||
            $shiftEnd <= $shiftStart
        ) {
            return false;
        }

        $midpoint = (int) floor(
            ($shiftStart + $shiftEnd) / 2
        );

        $period = strtolower(
            trim((string) ($leave['half_day_period'] ?? ''))
        );

        if ($period === 'first_half') {

            $latestEntry = $midpoint + (30 * 60);

            return (
                $actualEntry <= $latestEntry &&
                $actualExit >= $shiftEnd
            );
        }

        if ($period === 'second_half') {

            $latestEntry = $shiftStart + (30 * 60);

            return (
                $actualEntry <= $latestEntry &&
                $actualExit >= $midpoint
            );
        }

        return false;
    }


    /**
     * Convert TIME or DATETIME values into seconds
     * without applying timezone conversion.
     */
    private function timeToSeconds(?string $time): ?int
    {
        if ($time === null || trim($time) === '') {
            return null;
        }

        $time = trim($time);

        // Extract the time portion from DATETIME values.
        if (strpos($time, ' ') !== false) {
            $time = substr($time, strrpos($time, ' ') + 1);
        }

        if (
            !preg_match(
                '/^(\d{2}):(\d{2})(?::(\d{2}))?$/',
                $time,
                $matches
            )
        ) {
            return null;
        }

        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];
        $seconds = isset($matches[3])
            ? (int) $matches[3]
            : 0;

        if (
            $hours > 23 ||
            $minutes > 59 ||
            $seconds > 59
        ) {
            return null;
        }

        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }

}
