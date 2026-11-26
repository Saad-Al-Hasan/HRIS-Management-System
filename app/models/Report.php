<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/Model.php';

/**
 * Read-only data access for the administrative reports.
 *
 * This model deliberately does not call salary calculation methods or mutate
 * attendance, leave, employee, or salary records.
 */
class Report extends Model
{
    private const ATTENDANCE_STATUSES = [
        'present',
        'late',
        'absent',
        'leave',
        'holiday'
    ];

    private const LEAVE_STATUSES = [
        'pending',
        'approved',
        'rejected'
    ];

    private const EMPLOYEE_STATUSES = [
        'active',
        'inactive'
    ];

    /**
     * Return a calendar-based attendance report. Missing attendance rows are
     * evaluated against approved leave, official holidays, and Fridays.
     * Existing RFID/admin-edited timestamps are retained.
     */
    public function getAttendanceReport(
        array $filters = [],
        ?int $limit = 50,
        int $offset = 0
    ): array {
        $rows = $this->buildAttendanceRows($filters);

        usort($rows, static function (array $a, array $b): int {
            $dateCompare = strcmp((string) $b['attendance_date'], (string) $a['attendance_date']);
            return $dateCompare !== 0
                ? $dateCompare
                : strcmp((string) $a['employee_code'], (string) $b['employee_code']);
        });

        if ($limit === null) {
            return array_slice($rows, max(0, $offset));
        }

        $limit = max(1, min($limit, 500));
        return array_slice($rows, max(0, $offset), $limit);
    }

    /** Summary counts use the same generated rows and filters as the table. */
    public function getAttendanceSummary(array $filters = []): array
    {
        $summary = $this->emptyAttendanceSummary();
        foreach ($this->buildAttendanceRows($filters) as $row) {
            $summary['total_records']++;
            $key = (string) $row['attendance_status'] . '_count';
            if (isset($summary[$key])) {
                $summary[$key]++;
            }
        }
        return $summary;
    }



    /**
     * Generate chart data using the same calendar-based attendance
     * rows and filters as the attendance report.
     *
     * This method is independent of the table's 500-row display limit.
     */
    public function getAttendanceChartData(array $filters = []): array
    {
        $rows = $this->buildAttendanceRows($filters);

        $statusCounts = [
            'present' => 0,
            'late' => 0,
            'absent' => 0,
            'leave' => 0,
            'holiday' => 0,
        ];

        $dailyCounts = [];

        foreach ($rows as $row) {
            $status = strtolower((string) ($row['attendance_status'] ?? ''));

            if (isset($statusCounts[$status])) {
                $statusCounts[$status]++;
            }

            $date = (string) ($row['attendance_date'] ?? '');

            if ($date === '') {
                continue;
            }

            if (!isset($dailyCounts[$date])) {
                $dailyCounts[$date] = [
                    'date' => $date,
                    'present' => 0,
                    'late' => 0,
                    'absent' => 0,
                ];
            }

            if (isset($dailyCounts[$date][$status])) {
                $dailyCounts[$date][$status]++;
            }
        }

        foreach ($dailyCounts as $date => $counts) {
            if ($counts['present'] > 0) {
                error_log('PRESENT TREND CHECK: ' . $date . ' = ' . $counts['present']);
            }
        }

        ksort($dailyCounts);

        return [
            'status_counts' => $statusCounts,
            'daily_trend' => array_values($dailyCounts),
            'total_records' => count($rows),
        ];
    }



    /**
     * Build one row per employee per calendar date from the reporting start
     * date through today (or the selected date range).
     */
    private function buildAttendanceRows(array $filters): array
    {
        $employeeId = filter_var($filters['employee_id'] ?? null, FILTER_VALIDATE_INT);
        $department = trim((string) ($filters['department'] ?? ''));
        $fromDate = $this->validDate($filters['from_date'] ?? null);
        $toDate = $this->validDate($filters['to_date'] ?? null);
        $statusFilter = strtolower(trim((string) ($filters['status'] ?? '')));
        if (!in_array($statusFilter, self::ATTENDANCE_STATUSES, true)) {
            $statusFilter = '';
        }

        $employeeSql = "
            SELECT e.id AS employee_id, e.employee_code, e.department,
                   e.designation, e.joining_date, u.full_name
            FROM employees e
            INNER JOIN users u ON u.id = e.user_id
            WHERE u.role_id = 2
        ";
        $employeeParams = [];
        if ($employeeId !== false && $employeeId !== null && $employeeId > 0) {
            $employeeSql .= ' AND e.id = :employee_id';
            $employeeParams[':employee_id'] = $employeeId;
        }
        if ($department !== '') {
            $employeeSql .= ' AND e.department = :department';
            $employeeParams[':department'] = $department;
        }
        $employeeSql .= ' ORDER BY e.employee_code ASC';
        $employees = $this->runSelect($employeeSql, $employeeParams);
        if (!$employees) {
            return [];
        }

        // If no date range is supplied, begin at the earliest recorded
        // attendance/leave/holiday activity, avoiding years of pre-system absences.
        $startDate = $fromDate;
        if ($startDate === null) {
            $earliest = $this->runSingle(" 
                SELECT MIN(activity_date) AS first_date FROM (
                    SELECT MIN(attendance_date) AS activity_date FROM attendance
                    UNION ALL SELECT MIN(start_date) FROM leave_requests
                    UNION ALL SELECT MIN(holiday_date) FROM holidays
                ) activity_dates
            ", []);
            $startDate = $earliest['first_date'] ?? null;
        }
        if (!$startDate) {
            $joiningDates = array_values(array_filter(array_column($employees, 'joining_date')));
            $startDate = $joiningDates ? min($joiningDates) : date('Y-m-d');
        }

        $today = date('Y-m-d');
        $endDate = $toDate ?? $today;
        if ($endDate > $today) {
            $endDate = $today; // Future workdays must not be marked absent.
        }
        if ($startDate > $endDate) {
            return [];
        }

        $employeeIds = array_map(static fn(array $e): int => (int) $e['employee_id'], $employees);
        $placeholders = [];
        $params = [];
        foreach ($employeeIds as $index => $id) {
            $key = ':employee_' . $index;
            $placeholders[] = $key;
            $params[$key] = $id;
        }
        $in = implode(',', $placeholders);

        $attendanceSql = "
            SELECT a.id AS attendance_id, a.employee_id, a.attendance_date,
                   COALESCE(a.A_E_entry_time, a.entry_time) AS entry_time,
                   COALESCE(NULLIF(a.A_E_entry_status, ''), a.entry_status) AS entry_status,
                   COALESCE(a.A_E_exit_time, a.exit_time) AS exit_time,
                   COALESCE(NULLIF(a.A_E_exit_status, ''), a.exit_status) AS exit_status,
                   COALESCE(NULLIF(a.A_E_status, ''), a.status) AS attendance_status,
                   CASE WHEN a.A_E_entry_time IS NOT NULL
                          OR a.A_E_entry_status IS NOT NULL
                          OR a.A_E_exit_time IS NOT NULL
                          OR a.A_E_exit_status IS NOT NULL
                          OR a.A_E_status IS NOT NULL
                        THEN 'Administrator Edited'
                        ELSE 'RFID / Original' END AS record_source
            FROM attendance a
            WHERE a.employee_id IN ({$in})
              AND a.attendance_date BETWEEN :attendance_start AND :attendance_end
        ";
        $attendanceParams = $params;
        $attendanceParams[':attendance_start'] = $startDate;
        $attendanceParams[':attendance_end'] = $endDate;
        $attendanceRows = $this->runSelect($attendanceSql, $attendanceParams);
        $attendanceByKey = [];
        foreach ($attendanceRows as $row) {
            $attendanceByKey[$row['employee_id'] . '|' . $row['attendance_date']] = $row;
        }

        $holidayRows = $this->runSelect(
            'SELECT holiday_date, holiday_name, holiday_type FROM holidays WHERE holiday_date BETWEEN :start_date AND :end_date',
            [':start_date' => $startDate, ':end_date' => $endDate]
        );
        $holidays = [];
        foreach ($holidayRows as $holiday) {
            $holidays[$holiday['holiday_date']] = $holiday['holiday_name'];
        }

        $leaveRows = $this->runSelect(
            "SELECT employee_id, start_date, end_date, leave_type, half_day_period
             FROM leave_requests
             WHERE status = 'approved'
               AND employee_id IN ({$in})
               AND end_date >= :leave_start AND start_date <= :leave_end",
            $params + [':leave_start' => $startDate, ':leave_end' => $endDate]
        );
        $approvedLeaves = [];
        foreach ($leaveRows as $leave) {
            $period = new DatePeriod(
                new DateTimeImmutable(max($startDate, $leave['start_date'])),
                new DateInterval('P1D'),
                (new DateTimeImmutable(min($endDate, $leave['end_date'])))->modify('+1 day')
            );

            foreach ($period as $date) {
                $leaveKey = $leave['employee_id'] . '|' . $date->format('Y-m-d');

                // Preserve the approved leave details for the Attendance Report.
                $approvedLeaves[$leaveKey] = [
                    'leave_type' => $leave['leave_type'] ?? null,
                    'half_day_period' => $leave['half_day_period'] ?? null,
                ];
            }
        }

        $results = [];
        $period = new DatePeriod(
            new DateTimeImmutable($startDate),
            new DateInterval('P1D'),
            (new DateTimeImmutable($endDate))->modify('+1 day')
        );

        foreach ($employees as $employee) {
            $joiningDate = $employee['joining_date'] ?: $startDate;
            foreach ($period as $dateObject) {
                $date = $dateObject->format('Y-m-d');
                if ($date < $joiningDate) {
                    continue;
                }

                $key = $employee['employee_id'] . '|' . $date;
                $attendance = $attendanceByKey[$key] ?? null;
                $isFriday = $dateObject->format('N') === '5';
                $isOfficialHoliday = isset($holidays[$date]);
                $leaveDetails = $approvedLeaves[$key] ?? null;
                $hasApprovedLeave = $leaveDetails !== null;


                if ($isFriday || $isOfficialHoliday) {
                    $overallStatus = 'holiday';
                } elseif ($hasApprovedLeave) {
                    // Approved leave takes priority over ordinary attendance status.
                    // Existing attendance timestamps are still retained below.
                    $overallStatus = 'leave';
                } elseif ($attendance !== null) {
                    $overallStatus = strtolower((string) ($attendance['attendance_status'] ?? 'present'));

                    if (!in_array($overallStatus, ['present', 'late', 'absent', 'leave', 'holiday'], true)) {
                        $overallStatus = 'present';
                    }
                } else {
                    $overallStatus = 'absent';
                }


                if ($statusFilter !== '' && $overallStatus !== $statusFilter) {
                    continue;
                }

                $results[] = [
                    'attendance_id' => $attendance['attendance_id'] ?? null,
                    'employee_id' => $employee['employee_id'],
                    'employee_code' => $employee['employee_code'],
                    'full_name' => $employee['full_name'],
                    'department' => $employee['department'],
                    'designation' => $employee['designation'],
                    'attendance_date' => $date,
                    'entry_time' => $attendance['entry_time'] ?? null,
                    'entry_status' => $attendance['entry_status'] ?? null,
                    'exit_time' => $attendance['exit_time'] ?? null,
                    'exit_status' => $attendance['exit_status'] ?? null,
                    'attendance_status' => $overallStatus,
                    'leave_type' => $overallStatus === 'leave' ? ($leaveDetails['leave_type'] ?? null) : null,
                    'half_day_period' => $overallStatus === 'leave' ? ($leaveDetails['half_day_period'] ?? null) : null,
                    'record_source' => $attendance['record_source'] ?? ($isFriday ? 'Weekly Holiday' : ($isOfficialHoliday ? 'Official Holiday' : ($hasApprovedLeave ? 'Approved Leave' : 'System Evaluated'))),
                ];
            }
        }

        return $results;
    }

    /** Return employee details and the currently assigned work schedule. */
    public function getEmployeeReport(
        array $filters = [],
        ?int $limit = 50,
        int $offset = 0
    ): array {
        [$where, $params] = $this->employeeWhere($filters);

        $sql = "
            SELECT
                e.id AS employee_id,
                u.id AS user_id,
                e.employee_code,
                u.full_name,
                u.username,
                u.email,
                e.phone,
                e.department,
                e.designation,
                e.joining_date,
                u.status AS employee_status,
                e.monthly_salary,
                ws.id AS schedule_id,
                ws.name AS schedule_name,
                ws.expected_entry_time,
                ws.expected_exit_time,
                ws.working_hours
            FROM employees e
            INNER JOIN users u ON u.id = e.user_id
            LEFT JOIN employee_schedules es ON es.employee_id = e.id
            LEFT JOIN work_schedules ws ON ws.id = es.schedule_id
            {$where}
            ORDER BY e.employee_code ASC, e.id ASC
        ";

        return $this->runSelect($sql, $params, $limit, $offset);
    }

    /** Return leave requests, including the employee and reviewing admin. */
    public function getLeaveReport(
        array $filters = [],
        ?int $limit = 50,
        int $offset = 0
    ): array {
        [$where, $params] = $this->leaveWhere($filters);

        $sql = "
            SELECT
                lr.id AS leave_request_id,
                lr.employee_id,
                e.employee_code,
                u.full_name,
                e.department,
                lr.leave_type,
                lr.half_day_period,
                lr.start_date,
                lr.end_date,
                lr.reason,
                lr.status,
                reviewer.full_name AS reviewer_name,
                lr.reviewed_at,
                lr.created_at
            FROM leave_requests lr
            INNER JOIN employees e ON e.id = lr.employee_id
            INNER JOIN users u ON u.id = e.user_id
            LEFT JOIN users reviewer ON reviewer.id = lr.reviewed_by
            {$where}
            ORDER BY lr.created_at DESC, lr.id DESC
        ";

        return $this->runSelect($sql, $params, $limit, $offset);
    }

    /** Summary counts for exactly the same leave filters as the table. */
    /** Summary counts and accepted working leave days for the selected filters. */
    public function getLeaveSummary(array $filters = []): array
    {
        [$where, $params] = $this->leaveWhere($filters);

        // Existing request-status summary.
        $sql = "
        SELECT
            COUNT(*) AS total_requests,
            SUM(CASE WHEN lr.status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN lr.status = 'approved' THEN 1 ELSE 0 END) AS accepted_count,
            SUM(CASE WHEN lr.status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count
        FROM leave_requests lr
        INNER JOIN employees e ON e.id = lr.employee_id
        INNER JOIN users u ON u.id = e.user_id
        {$where}
    ";

        $summary = $this->runSingle($sql, $params) ?? [
            'total_requests' => 0,
            'pending_count' => 0,
            'accepted_count' => 0,
            'rejected_count' => 0,
        ];

        // Retrieve approved leave applications using the same filters.
        $leaveSql = "
        SELECT
            lr.start_date,
            lr.end_date,
            lr.leave_type,
            lr.half_day_period
        FROM leave_requests lr
        INNER JOIN employees e ON e.id = lr.employee_id
        INNER JOIN users u ON u.id = e.user_id
        {$where}
        AND lr.status = 'approved'
    ";

        $approvedLeaves = $this->runSelect($leaveSql, $params);

        $fromDate = $this->validDate($filters['from_date'] ?? null);
        $toDate = $this->validDate($filters['to_date'] ?? null);

        $workingDates = [];

        foreach ($approvedLeaves as $leave) {
            $startDate = $leave['start_date'];
            $endDate = $leave['end_date'];

            // Respect the selected report date range.
            if ($fromDate !== null && $startDate < $fromDate) {
                $startDate = $fromDate;
            }

            if ($toDate !== null && $endDate > $toDate) {
                $endDate = $toDate;
            }

            if ($startDate > $endDate) {
                continue;
            }

            $leaveType = strtolower((string) ($leave['leave_type'] ?? ''));
            $halfDayPeriod = trim((string) ($leave['half_day_period'] ?? ''));

            $isHalfDay = str_contains($leaveType, 'half')
                || $halfDayPeriod !== '';

            $period = new DatePeriod(
                new DateTimeImmutable($startDate),
                new DateInterval('P1D'),
                (new DateTimeImmutable($endDate))->modify('+1 day')
            );

            foreach ($period as $date) {
                $dateString = $date->format('Y-m-d');

                // Friday is a weekly holiday.
                if ($date->format('N') === '5') {
                    continue;
                }

                $workingDates[$dateString] =
                    ($workingDates[$dateString] ?? 0) + ($isHalfDay ? 0.5 : 1.0);
            }
        }

        // Exclude Government and Organizational holidays.
        if (!empty($workingDates)) {
            $dates = array_keys($workingDates);
            $firstDate = min($dates);
            $lastDate = max($dates);

            $holidayRows = $this->runSelect(
                "SELECT holiday_date
             FROM holidays
             WHERE holiday_date BETWEEN :start_date AND :end_date",
                [
                    ':start_date' => $firstDate,
                    ':end_date' => $lastDate,
                ]
            );

            foreach ($holidayRows as $holiday) {
                unset($workingDates[$holiday['holiday_date']]);
            }
        }

        $summary['accepted_leave_days'] = array_sum($workingDates);

        return $summary;
    }

    /** Read saved salary records only. This method never recalculates payroll. */
    public function getSalaryReport(
        array $filters = [],
        ?int $limit = 50,
        int $offset = 0
    ): array {
        [$where, $params] = $this->salaryWhere($filters);

        $sql = "
            SELECT
                s.id AS salary_id,
                s.employee_id,
                e.employee_code,
                u.full_name,
                e.department,
                s.salary_month,
                s.basic_salary,
                s.deductions,
                s.final_salary,
                s.calculated_at
            FROM salary s
            INNER JOIN employees e ON e.id = s.employee_id
            INNER JOIN users u ON u.id = e.user_id
            {$where}
            ORDER BY s.salary_month DESC, e.employee_code ASC, s.id DESC
        ";

        return $this->runSelect($sql, $params, $limit, $offset);
    }

    /** Summary totals for exactly the same saved salary filters as the table. */
    public function getSalarySummary(array $filters = []): array
    {
        [$where, $params] = $this->salaryWhere($filters);

        $sql = "
            SELECT
                COUNT(*) AS total_records,
                COALESCE(SUM(s.basic_salary), 0) AS total_basic_salary,
                COALESCE(SUM(s.deductions), 0) AS total_deductions,
                COALESCE(SUM(s.final_salary), 0) AS total_final_salary
            FROM salary s
            INNER JOIN employees e ON e.id = s.employee_id
            INNER JOIN users u ON u.id = e.user_id
            {$where}
        ";

        return $this->runSingle($sql, $params) ?? [
            'total_records' => 0,
            'total_basic_salary' => 0,
            'total_deductions' => 0,
            'total_final_salary' => 0,
        ];
    }



    /**
     * Generate Employee Report analytics from all matching employee records.
     */
    public function getEmployeeChartData(array $filters = []): array
    {
        $rows = $this->getEmployeeReport($filters, null, 0);

        $statusCounts = [
            'active' => 0,
            'inactive' => 0,
        ];

        $departmentCounts = [];
        $scheduleCounts = [
            'assigned' => 0,
            'unassigned' => 0,
        ];

        foreach ($rows as $row) {
            $status = strtolower((string) ($row['employee_status'] ?? ''));

            if (isset($statusCounts[$status])) {
                $statusCounts[$status]++;
            }

            $department = trim((string) ($row['department'] ?? ''));
            $department = $department !== '' ? $department : 'Unspecified';

            $departmentCounts[$department] = ($departmentCounts[$department] ?? 0) + 1;

            if (!empty($row['schedule_id'])) {
                $scheduleCounts['assigned']++;
            } else {
                $scheduleCounts['unassigned']++;
            }
        }

        arsort($departmentCounts);

        return [
            'status_counts' => $statusCounts,
            'department_counts' => $departmentCounts,
            'schedule_counts' => $scheduleCounts,
            'total_employees' => count($rows),
        ];
    }

    /**
     * Generate Leave Report analytics from all matching leave requests.
     */
    public function getLeaveChartData(array $filters = []): array
    {
        $rows = $this->getLeaveReport($filters, null, 0);

        $statusCounts = [
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
        ];

        $typeCounts = [];
        $monthlyCounts = [];

        foreach ($rows as $row) {
            $status = strtolower((string) ($row['status'] ?? ''));

            if (isset($statusCounts[$status])) {
                $statusCounts[$status]++;
            }

            $type = trim((string) ($row['leave_type'] ?? ''));
            $type = $type !== '' ? $type : 'Unspecified';

            $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;

            $date = (string) ($row['start_date'] ?? '');

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
                $month = substr($date, 0, 7);
                $monthlyCounts[$month] = ($monthlyCounts[$month] ?? 0) + 1;
            }
        }

        ksort($monthlyCounts);
        arsort($typeCounts);

        return [
            'status_counts' => $statusCounts,
            'type_counts' => $typeCounts,
            'monthly_trend' => $monthlyCounts,
            'total_requests' => count($rows),
        ];
    }

    /**
     * Generate Salary Report analytics from all matching saved salary records.
     * This method never recalculates or modifies payroll.
     */
    public function getSalaryChartData(array $filters = []): array
    {
        $rows = $this->getSalaryReport($filters, null, 0);

        $totals = [
            'basic_salary' => 0,
            'deductions' => 0,
            'final_salary' => 0,
        ];

        $monthlyTotals = [];
        $departmentTotals = [];

        foreach ($rows as $row) {
            $basic = (float) ($row['basic_salary'] ?? 0);
            $deductions = (float) ($row['deductions'] ?? 0);
            $final = (float) ($row['final_salary'] ?? 0);

            $totals['basic_salary'] += $basic;
            $totals['deductions'] += $deductions;
            $totals['final_salary'] += $final;

            $month = (string) ($row['salary_month'] ?? '');

            if ($month !== '') {
                $monthlyTotals[$month] ??= [
                    'basic_salary' => 0,
                    'deductions' => 0,
                    'final_salary' => 0,
                ];

                $monthlyTotals[$month]['basic_salary'] += $basic;
                $monthlyTotals[$month]['deductions'] += $deductions;
                $monthlyTotals[$month]['final_salary'] += $final;
            }

            $department = trim((string) ($row['department'] ?? ''));
            $department = $department !== '' ? $department : 'Unspecified';

            $departmentTotals[$department] =
                ($departmentTotals[$department] ?? 0) + $final;
        }

        ksort($monthlyTotals);
        arsort($departmentTotals);

        return [
            'totals' => $totals,
            'monthly_totals' => $monthlyTotals,
            'department_totals' => $departmentTotals,
            'total_records' => count($rows),
        ];
    }



    /** Lists used to populate report filter dropdowns. */
    public function getEmployeeOptions(): array
    {
        $sql = "
            SELECT e.id AS employee_id, e.employee_code, u.full_name
            FROM employees e
            INNER JOIN users u ON u.id = e.user_id
            WHERE u.role_id = 2
            ORDER BY e.employee_code ASC
        ";

        return $this->runSelect($sql, []);
    }

    public function getDepartmentOptions(): array
    {
        $sql = "
            SELECT DISTINCT department
            FROM employees
            WHERE department IS NOT NULL AND TRIM(department) <> ''
            ORDER BY department ASC
        ";

        return $this->runSelect($sql, []);
    }

    public function getDesignationOptions(): array
    {
        $sql = "
            SELECT DISTINCT designation
            FROM employees
            WHERE designation IS NOT NULL AND TRIM(designation) <> ''
            ORDER BY designation ASC
        ";

        return $this->runSelect($sql, []);
    }

    private function employeeWhere(array $filters): array
    {
        $conditions = ['u.role_id = 2'];
        $params = [];

        foreach (['department', 'designation'] as $field) {
            $value = trim((string) ($filters[$field] ?? ''));
            if ($value !== '') {
                $conditions[] = "e.{$field} = :{$field}";
                $params[":" . $field] = $value;
            }
        }

        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        if ($status !== '' && in_array($status, self::EMPLOYEE_STATUSES, true)) {
            $conditions[] = 'u.status = :employee_status';
            $params[':employee_status'] = $status;
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $conditions[] = '(e.employee_code LIKE :search_code OR u.full_name LIKE :search_name OR u.email LIKE :search_email)';
            $like = '%' . $search . '%';
            $params[':search_code'] = $like;
            $params[':search_name'] = $like;
            $params[':search_email'] = $like;
        }

        return ['WHERE ' . implode(' AND ', $conditions), $params];
    }

    private function leaveWhere(array $filters): array
    {
        $conditions = ['u.role_id = 2'];
        $params = [];

        // Leave requests overlap the selected date range.
        $fromDate = $this->validDate($filters['from_date'] ?? null);
        $toDate = $this->validDate($filters['to_date'] ?? null);
        if ($fromDate !== null) {
            $conditions[] = 'lr.end_date >= :leave_from_date';
            $params[':leave_from_date'] = $fromDate;
        }
        if ($toDate !== null) {
            $conditions[] = 'lr.start_date <= :leave_to_date';
            $params[':leave_to_date'] = $toDate;
        }

        $employeeId = filter_var($filters['employee_id'] ?? null, FILTER_VALIDATE_INT);
        if ($employeeId !== false && $employeeId !== null && $employeeId > 0) {
            $conditions[] = 'lr.employee_id = :leave_employee_id';
            $params[':leave_employee_id'] = $employeeId;
        }

        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        if ($status !== '' && in_array($status, self::LEAVE_STATUSES, true)) {
            $conditions[] = 'lr.status = :leave_status';
            $params[':leave_status'] = $status;
        }

        return ['WHERE ' . implode(' AND ', $conditions), $params];
    }

    private function salaryWhere(array $filters): array
    {
        $conditions = ['u.role_id = 2'];
        $params = [];

        $employeeId = filter_var($filters['employee_id'] ?? null, FILTER_VALIDATE_INT);
        if ($employeeId !== false && $employeeId !== null && $employeeId > 0) {
            $conditions[] = 's.employee_id = :salary_employee_id';
            $params[':salary_employee_id'] = $employeeId;
        }

        $department = trim((string) ($filters['department'] ?? ''));

        if ($department !== '') {
            $conditions[] = 'e.department = :salary_department';
            $params[':salary_department'] = $department;
        }

        $month = trim((string) ($filters['month'] ?? ''));
        if ($month !== '' && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1) {
            $conditions[] = 's.salary_month = :salary_month';
            $params[':salary_month'] = $month . '-01';
        }

        return ['WHERE ' . implode(' AND ', $conditions), $params];
    }

    private function addDateRange(
        array &$conditions,
        array &$params,
        string $column,
        array $filters
    ): void {
        $fromDate = $this->validDate($filters['from_date'] ?? null);
        $toDate = $this->validDate($filters['to_date'] ?? null);

        if ($fromDate !== null) {
            $conditions[] = "{$column} >= :from_date";
            $params[':from_date'] = $fromDate;
        }
        if ($toDate !== null) {
            $conditions[] = "{$column} <= :to_date";
            $params[':to_date'] = $toDate;
        }
    }

    private function validDate(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    private function runSelect(
        string $sql,
        array $params,
        ?int $limit = null,
        int $offset = 0
    ): array {
        if ($limit !== null) {
            $limit = max(1, min($limit, 500));
            $offset = max(0, $offset);
            $sql .= ' LIMIT :report_limit OFFSET :report_offset';
        }

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        if ($limit !== null) {
            $stmt->bindValue(':report_limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':report_offset', $offset, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function runSingle(string $sql, array $params): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function emptyAttendanceSummary(): array
    {
        return [
            'total_records' => 0,
            'present_count' => 0,
            'late_count' => 0,
            'absent_count' => 0,
            'leave_count' => 0,
            'holiday_count' => 0,
        ];
    }
}
