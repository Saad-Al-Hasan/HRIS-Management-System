<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/models/Report.php';

/**
 * Sprint 7 Reports & Excel Export unit tests.
 * Uses only hris_management_system_test and removes only fixture IDs created here.
 */
final class ReportsExcelUnitTest extends TestCase
{
    private PDO $pdo;
    private Report $report;
    private string $tag;
    private array $userIds = [];
    private array $employeeIds = [];
    private array $attendanceIds = [];
    private array $leaveIds = [];
    private array $holidayIds = [];
    private array $salaryIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $database = new Database();
        $this->pdo = $database->connect();

        $this->assertSame(
            'hris_management_system_test',
            $this->pdo->query('SELECT DATABASE()')->fetchColumn(),
            'Reports tests must never run against the live database.'
        );

        $this->report = new Report();
        $this->tag = 'RPTUNIT_' . bin2hex(random_bytes(5));
    }

    protected function tearDown(): void
    {
        foreach ($this->salaryIds as $id)
            $this->deleteById('salary', $id);
        foreach ($this->attendanceIds as $id)
            $this->deleteById('attendance', $id);
        foreach ($this->leaveIds as $id)
            $this->deleteById('leave_requests', $id);
        foreach ($this->holidayIds as $id)
            $this->deleteById('holidays', $id);
        foreach ($this->employeeIds as $id)
            $this->deleteById('employees', $id);
        foreach ($this->userIds as $id)
            $this->deleteById('users', $id);
        parent::tearDown();
    }

    private function deleteById(string $table, int $id): void
    {
        $allowed = ['salary', 'attendance', 'leave_requests', 'holidays', 'employees', 'users'];
        if (!in_array($table, $allowed, true)) {
            throw new RuntimeException('Unsafe cleanup table.');
        }
        $stmt = $this->pdo->prepare("DELETE FROM {$table} WHERE id = ?");
        $stmt->execute([$id]);
    }

    private function createEmployee(
        string $department = 'Unit Test Department',
        string $status = 'active',
        string $designation = 'Unit Test Role'
    ): int {
        $n = count($this->userIds);
        $stmt = $this->pdo->prepare(
            "INSERT INTO users (role_id, username, email, password, full_name, status)
             VALUES (2, ?, ?, 'unit_test_hash', ?, ?)"
        );
        $stmt->execute([
            $this->tag . '_U' . $n,
            strtolower($this->tag . '_U' . $n) . '@test.local',
            $this->tag . ' Employee ' . $n,
            $status
        ]);
        $userId = (int) $this->pdo->lastInsertId();
        $this->userIds[] = $userId;

        $stmt = $this->pdo->prepare(
            'INSERT INTO employees (user_id, employee_code, department, designation, monthly_salary, joining_date)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $this->tag . '_E' . count($this->employeeIds),
            $department,
            $designation,
            30000.00,
            '2020-01-01'
        ]);
        $employeeId = (int) $this->pdo->lastInsertId();
        $this->employeeIds[] = $employeeId;
        return $employeeId;
    }

    private function createAttendance(int $employeeId, string $date, string $status): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO attendance
             (employee_id, attendance_date, entry_time, entry_status, exit_time, exit_status, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $employeeId,
            $date,
            $date . ' 09:00:00',
            $status === 'late' ? 'Entry - Late' : 'In Time',
            $date . ' 18:00:00',
            'Exit - in time',
            $status
        ]);
        $this->attendanceIds[] = (int) $this->pdo->lastInsertId();
    }

    private function createLeave(
        int $employeeId,
        string $date,
        string $status = 'approved',
        string $type = 'Full Day',
        ?string $halfDayPeriod = null
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO leave_requests
             (employee_id, leave_type, half_day_period, start_date, end_date, reason, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $employeeId,
            $type,
            $halfDayPeriod,
            $date,
            $date,
            $this->tag . ' test leave',
            $status
        ]);
        $this->leaveIds[] = (int) $this->pdo->lastInsertId();
    }

    private function createHoliday(string $date): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO holidays (holiday_name, holiday_date, holiday_type, description)
             VALUES (?, ?, 'Organizational', ?)"
        );
        $stmt->execute([$this->tag . ' Holiday', $date, $this->tag]);
        $this->holidayIds[] = (int) $this->pdo->lastInsertId();
    }

    private function createSalary(
        int $employeeId,
        string $month,
        float $basic,
        float $deductions,
        float $final
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO salary
             (employee_id, salary_month, basic_salary, deductions, final_salary, calculated_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$employeeId, $month . '-01', $basic, $deductions, $final]);
        $this->salaryIds[] = (int) $this->pdo->lastInsertId();
    }

    // RPT-01: Department and active status filters are applied to employee report.
    public function testEmployeeReportFiltersDepartmentAndStatus(): void
    {
        $active = $this->createEmployee('RPT Admin', 'active');
        $inactive = $this->createEmployee('RPT Admin', 'inactive');
        $other = $this->createEmployee('RPT HR', 'active');

        $rows = $this->report->getEmployeeReport([
            'department' => 'RPT Admin',
            'status' => 'active'
        ], null, 0);
        $ids = array_map('intval', array_column($rows, 'employee_id'));

        $this->assertContains($active, $ids);
        $this->assertNotContains($inactive, $ids);
        $this->assertNotContains($other, $ids);
    }

    // RPT-02: Employee report pagination returns the requested slice.
    public function testEmployeeReportPagination(): void
    {
        $this->createEmployee('RPT Page');
        $this->createEmployee('RPT Page');
        $this->createEmployee('RPT Page');

        $all = $this->report->getEmployeeReport(['department' => 'RPT Page'], null, 0);
        $page = $this->report->getEmployeeReport(['department' => 'RPT Page'], 2, 1);

        $this->assertCount(3, $all);
        $this->assertCount(2, $page);
        $this->assertSame(array_slice(array_column($all, 'employee_id'), 1, 2), array_column($page, 'employee_id'));
    }

    // RPT-03: Employee and department dropdown data includes newly created fixtures.
    public function testEmployeeAndDepartmentOptions(): void
    {
        $employeeId = $this->createEmployee('RPT Unique Department');
        $employeeOptions = $this->report->getEmployeeOptions();
        $departmentOptions = array_column($this->report->getDepartmentOptions(), 'department');

        $this->assertContains($employeeId, array_map('intval', array_column($employeeOptions, 'employee_id')));
        $this->assertContains('RPT Unique Department', $departmentOptions);
    }

    // RPT-04: Calendar attendance summary correctly classifies the selected dates.
    public function testAttendanceReportAndSummaryStatuses(): void
    {
        $employeeId = $this->createEmployee('RPT Attendance');
        // Jan 6-12, 2025: one present, one late, one approved leave,
        // one official holiday, Friday holiday, and two absent weekend dates.
        $this->createAttendance($employeeId, '2025-01-06', 'present');
        $this->createAttendance($employeeId, '2025-01-07', 'late');
        $this->createLeave($employeeId, '2025-01-08', 'approved');
        $this->createHoliday('2025-01-09');

        $filters = [
            'employee_id' => $employeeId,
            'from_date' => '2025-01-06',
            'to_date' => '2025-01-12'
        ];
        $rows = $this->report->getAttendanceReport($filters, null, 0);
        $summary = $this->report->getAttendanceSummary($filters);

        $this->assertCount(7, $rows);
        $this->assertSame(7, (int) $summary['total_records']);
        $this->assertSame(1, (int) $summary['present_count']);
        $this->assertSame(1, (int) $summary['late_count']);
        $this->assertSame(2, (int) $summary['absent_count']);
        $this->assertSame(1, (int) $summary['leave_count']);
        $this->assertSame(2, (int) $summary['holiday_count']);
    }

    // RPT-05: Attendance status filter returns only matching rows.
    public function testAttendanceReportStatusFilter(): void
    {
        $employeeId = $this->createEmployee('RPT Attendance Status');
        $this->createAttendance($employeeId, '2025-01-06', 'late');
        $this->createAttendance($employeeId, '2025-01-07', 'present');

        $rows = $this->report->getAttendanceReport([
            'employee_id' => $employeeId,
            'from_date' => '2025-01-06',
            'to_date' => '2025-01-07',
            'status' => 'late'
        ], null, 0);

        $this->assertCount(1, $rows);
        $this->assertSame('late', $rows[0]['attendance_status']);
    }

    // RPT-06: Leave report filters by employee, status, and overlapping date range.
    public function testLeaveReportFilters(): void
    {
        $employeeId = $this->createEmployee('RPT Leave');
        $this->createLeave($employeeId, '2025-02-03', 'pending');
        $this->createLeave($employeeId, '2025-02-04', 'approved');
        $this->createLeave($employeeId, '2025-03-04', 'rejected');

        $rows = $this->report->getLeaveReport([
            'employee_id' => $employeeId,
            'from_date' => '2025-02-01',
            'to_date' => '2025-02-28',
            'status' => 'approved'
        ], null, 0);

        $this->assertCount(1, $rows);
        $this->assertSame('approved', $rows[0]['status']);
        $this->assertSame('2025-02-04', $rows[0]['start_date']);
    }

    // RPT-07: Leave summary totals request states and accepted working leave days.
    public function testLeaveSummary(): void
    {
        $employeeId = $this->createEmployee('RPT Leave Summary');
        $this->createLeave($employeeId, '2025-02-03', 'pending');
        $this->createLeave($employeeId, '2025-02-04', 'approved', 'Full Day');
        $this->createLeave($employeeId, '2025-02-05', 'rejected');
        $this->createLeave($employeeId, '2025-02-06', 'approved', 'Half Day', 'first_half');

        $summary = $this->report->getLeaveSummary([
            'employee_id' => $employeeId,
            'from_date' => '2025-02-01',
            'to_date' => '2025-02-28'
        ]);

        $this->assertSame(4, (int) $summary['total_requests']);
        $this->assertSame(1, (int) $summary['pending_count']);
        $this->assertSame(2, (int) $summary['accepted_count']);
        $this->assertSame(1, (int) $summary['rejected_count']);
        $this->assertEqualsWithDelta(1.5, (float) $summary['accepted_leave_days'], 0.001);
    }

    // RPT-08: Saved salary rows filter by month, employee, and department.
    public function testSalaryReportDepartmentMonthAndEmployeeFilters(): void
    {
        $admin = $this->createEmployee('RPT Salary Admin');
        $hr = $this->createEmployee('RPT Salary HR');
        $this->createSalary($admin, '2025-04', 30000, 1000, 29000);
        $this->createSalary($admin, '2025-05', 30000, 2000, 28000);
        $this->createSalary($hr, '2025-04', 25000, 500, 24500);

        $rows = $this->report->getSalaryReport([
            'department' => 'RPT Salary Admin',
            'month' => '2025-04',
            'employee_id' => $admin
        ], null, 0);

        $this->assertCount(1, $rows);
        $this->assertSame($admin, (int) $rows[0]['employee_id']);
        $this->assertSame('2025-04-01', $rows[0]['salary_month']);
        $this->assertSame('RPT Salary Admin', $rows[0]['department']);
    }

    // RPT-09: Salary summary uses saved records and the department filter.
    public function testSalarySummaryTotalsDepartmentFilteredSavedRows(): void
    {
        $a = $this->createEmployee('RPT Salary Summary A');
        $b = $this->createEmployee('RPT Salary Summary B');
        $this->createSalary($a, '2025-06', 30000, 2500, 27500);
        $this->createSalary($a, '2025-07', 30000, 1500, 28500);
        $this->createSalary($b, '2025-06', 20000, 1000, 19000);

        $summary = $this->report->getSalarySummary(['department' => 'RPT Salary Summary A']);

        $this->assertSame(2, (int) $summary['total_records']);
        $this->assertEqualsWithDelta(60000, (float) $summary['total_basic_salary'], 0.001);
        $this->assertEqualsWithDelta(4000, (float) $summary['total_deductions'], 0.001);
        $this->assertEqualsWithDelta(56000, (float) $summary['total_final_salary'], 0.001);
    }

    // RPT-10: Salary analytics totals match the same department-filtered saved records.
    public function testSalaryChartDataMatchesFilteredRecords(): void
    {
        $employeeId = $this->createEmployee('RPT Chart Department');
        $this->createSalary($employeeId, '2025-08', 30000, 2000, 28000);
        $this->createSalary($employeeId, '2025-09', 30000, 1000, 29000);

        $chart = $this->report->getSalaryChartData(['department' => 'RPT Chart Department']);

        $this->assertSame(2, (int) $chart['total_records']);
        $this->assertEqualsWithDelta(60000, (float) $chart['totals']['basic_salary'], 0.001);
        $this->assertEqualsWithDelta(3000, (float) $chart['totals']['deductions'], 0.001);
        $this->assertEqualsWithDelta(57000, (float) $chart['totals']['final_salary'], 0.001);
    }
}
