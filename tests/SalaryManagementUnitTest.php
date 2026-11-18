<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/models/Salary.php';

/**
 * Salary model unit/integration tests.
 * IMPORTANT: This suite refuses to run unless the active database is
 * hris_management_system_test. It creates uniquely tagged fixtures and
 * deletes only records created by this suite.
 */
class SalaryManagementUnitTest extends TestCase
{
    private PDO $pdo;
    private Salary $salaryModel;

    private array $createdUserIds = [];
    private array $createdEmployeeIds = [];
    private array $createdScheduleIds = [];
    private array $createdHolidayIds = [];
    private array $createdSalaryIds = [];
    private array $createdAttendanceIds = [];
    private array $createdLeaveIds = [];
    private array $createdEmployeeScheduleIds = [];
    private string $tag;

    protected function setUp(): void
    {
        parent::setUp();

        $database = new Database();
        $this->pdo = $database->connect();

        $databaseName = $this->pdo->query('SELECT DATABASE()')->fetchColumn();
        $this->assertSame(
            'hris_management_system_test',
            $databaseName,
            'Salary tests must never run against the live database.'
        );

        $this->salaryModel = new Salary();
        $this->tag = 'SALTEST_' . bin2hex(random_bytes(5));
    }

    protected function tearDown(): void
    {
        // Delete only fixture rows tracked by this test instance.
        foreach ($this->createdSalaryIds as $id) {
            $this->deleteById('salary', $id);
        }
        foreach ($this->createdAttendanceIds as $id) {
            $this->deleteById('attendance', $id);
        }
        foreach ($this->createdLeaveIds as $id) {
            $this->deleteById('leave_requests', $id);
        }
        foreach ($this->createdHolidayIds as $id) {
            $this->deleteById('holidays', $id);
        }
        foreach ($this->createdEmployeeScheduleIds as $id) {
            $this->deleteById('employee_schedules', $id);
        }
        foreach ($this->createdEmployeeIds as $id) {
            $this->deleteById('employees', $id);
        }
        foreach ($this->createdScheduleIds as $id) {
            $this->deleteById('work_schedules', $id);
        }
        foreach ($this->createdUserIds as $id) {
            $this->deleteById('users', $id);
        }

        parent::tearDown();
    }

    private function deleteById(string $table, int $id): void
    {
        $allowed = [
            'salary',
            'attendance',
            'leave_requests',
            'holidays',
            'employee_schedules',
            'employees',
            'work_schedules',
            'users'
        ];
        if (!in_array($table, $allowed, true)) {
            throw new RuntimeException('Unsafe cleanup table.');
        }
        $stmt = $this->pdo->prepare("DELETE FROM {$table} WHERE id = ?");
        $stmt->execute([$id]);
    }

    private function createEmployee(
        float $monthlySalary = 30000.00,
        ?string $joiningDate = '2020-01-01',
        bool $withSchedule = true
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO users (role_id, username, email, password, full_name, status)
             VALUES (2, ?, ?, 'test_hash', ?, 'active')"
        );
        $stmt->execute([
            $this->tag . '_U' . count($this->createdUserIds),
            strtolower($this->tag . '_U' . count($this->createdUserIds)) . '@test.local',
            $this->tag . ' Employee'
        ]);
        $userId = (int) $this->pdo->lastInsertId();
        $this->createdUserIds[] = $userId;

        $stmt = $this->pdo->prepare(
            'INSERT INTO employees (user_id, employee_code, department, monthly_salary, joining_date)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $this->tag . '_E' . count($this->createdEmployeeIds),
            'Salary Test',
            $monthlySalary,
            $joiningDate
        ]);
        $employeeId = (int) $this->pdo->lastInsertId();
        $this->createdEmployeeIds[] = $employeeId;

        if ($withSchedule) {
            $scheduleId = $this->createSchedule();
            $stmt = $this->pdo->prepare(
                'INSERT INTO employee_schedules (employee_id, schedule_id) VALUES (?, ?)'
            );
            $stmt->execute([$employeeId, $scheduleId]);
            $this->createdEmployeeScheduleIds[] = (int) $this->pdo->lastInsertId();
        }

        return $employeeId;
    }

    private function createSchedule(): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO work_schedules (name, expected_entry_time, expected_exit_time, working_hours)
             VALUES (?, '09:00:00', '18:00:00', 9.00)"
        );
        $stmt->execute([$this->tag . '_SHIFT_' . count($this->createdScheduleIds)]);
        $id = (int) $this->pdo->lastInsertId();
        $this->createdScheduleIds[] = $id;
        return $id;
    }

    private function createAttendance(int $employeeId, string $date, array $values = []): int
    {
        $defaults = [
            'entry_time' => $date . ' 09:00:00',
            'entry_status' => 'In Time',
            'exit_time' => $date . ' 18:00:00',
            'exit_status' => 'In Time',
            'status' => 'present',
            'A_E_entry_time' => null,
            'A_E_entry_status' => null,
            'A_E_exit_time' => null,
            'A_E_exit_status' => null,
            'A_E_status' => null
        ];
        $v = array_merge($defaults, $values);
        $stmt = $this->pdo->prepare(
            'INSERT INTO attendance
             (employee_id, attendance_date, entry_time, entry_status, exit_time, exit_status, status,
              A_E_entry_time, A_E_entry_status, A_E_exit_time, A_E_exit_status, A_E_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $employeeId,
            $date,
            $v['entry_time'],
            $v['entry_status'],
            $v['exit_time'],
            $v['exit_status'],
            $v['status'],
            $v['A_E_entry_time'],
            $v['A_E_entry_status'],
            $v['A_E_exit_time'],
            $v['A_E_exit_status'],
            $v['A_E_status']
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $this->createdAttendanceIds[] = $id;
        return $id;
    }

    private function createLeave(
        int $employeeId,
        string $date,
        string $type = 'Full Day',
        ?string $period = null,
        string $status = 'approved'
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO leave_requests
             (employee_id, leave_type, half_day_period, start_date, end_date, reason, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$employeeId, $type, $period, $date, $date, $this->tag, $status]);
        $id = (int) $this->pdo->lastInsertId();
        $this->createdLeaveIds[] = $id;
        return $id;
    }

    private function createHoliday(string $date): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO holidays (holiday_name, holiday_date, holiday_type, description)
             VALUES (?, ?, 'Organizational', ?)"
        );
        $stmt->execute([$this->tag . ' Holiday', $date, $this->tag]);
        $id = (int) $this->pdo->lastInsertId();
        $this->createdHolidayIds[] = $id;
        return $id;
    }

    private function salaryPayload(int $employeeId, string $month, float $basic = 30000, float $deductions = 0, float $final = 30000): array
    {
        return [
            'employee' => ['employee_id' => $employeeId],
            'salary_month' => $month,
            'monthly_salary' => $basic,
            'total_deductions' => $deductions,
            'payable_salary' => $final,
            'test_marker' => $this->tag
        ];
    }

    private function getSalaryRowId(int $employeeId, string $month): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM salary WHERE employee_id = ? AND salary_month = ?'
        );
        $stmt->execute([$employeeId, $month . '-01']);
        $id = (int) $stmt->fetchColumn();
        $this->assertGreaterThan(0, $id);
        if (!in_array($id, $this->createdSalaryIds, true)) {
            $this->createdSalaryIds[] = $id;
        }
        return $id;
    }

    // SAL-M01: employee salary profile is returned for an employee account.
    public function testGetEmployeeSalaryInfoReturnsEmployeeDetails(): void
    {
        $employeeId = $this->createEmployee();
        $result = $this->salaryModel->getEmployeeSalaryInfo($employeeId);
        $this->assertIsArray($result);
        $this->assertSame($employeeId, (int) $result['employee_id']);
        $this->assertSame(30000.0, (float) $result['monthly_salary']);
    }

    // SAL-M02: unknown employee returns null.
    public function testGetEmployeeSalaryInfoReturnsNullForUnknownEmployee(): void
    {
        $this->assertNull($this->salaryModel->getEmployeeSalaryInfo(4294960000));
    }

    // SAL-M03: attendance query returns only the requested employee/date range.
    public function testGetMonthlyAttendanceFiltersEmployeeAndDateRange(): void
    {
        $employeeId = $this->createEmployee();
        $this->createAttendance($employeeId, '2025-01-06');
        $this->createAttendance($employeeId, '2025-02-03');
        $rows = $this->salaryModel->getMonthlyAttendance($employeeId, '2025-01-01', '2025-01-31');
        $this->assertCount(1, $rows);
        $this->assertSame('2025-01-06', $rows[0]['attendance_date']);
    }

    // SAL-M04: admin-edited attendance fields override original fields independently.
    public function testGetMonthlyAttendancePrioritizesAdminEditedValues(): void
    {
        $employeeId = $this->createEmployee();
        $this->createAttendance($employeeId, '2025-01-06', [
            'entry_status' => 'Late',
            'A_E_entry_status' => 'In Time',
            'exit_status' => 'Exit - Before',
            'A_E_exit_status' => 'Exit - in time',
            'status' => 'late',
            'A_E_status' => 'present'
        ]);
        $rows = $this->salaryModel->getMonthlyAttendance($employeeId, '2025-01-01', '2025-01-31');
        $this->assertSame('In Time', $rows[0]['entry_status']);
        $this->assertSame('Exit - in time', $rows[0]['exit_status']);
        $this->assertSame('present', $rows[0]['attendance_status']);
    }

    // SAL-M05: approved leave query excludes pending and rejected requests.
    public function testGetMonthlyApprovedLeavesReturnsOnlyApprovedOverlappingLeaves(): void
    {
        $employeeId = $this->createEmployee();
        $this->createLeave($employeeId, '2025-01-08', 'Full Day', null, 'approved');
        $this->createLeave($employeeId, '2025-01-09', 'Full Day', null, 'pending');
        $rows = $this->salaryModel->getMonthlyApprovedLeaves($employeeId, '2025-01-01', '2025-01-31');
        $this->assertCount(1, $rows);
        $this->assertSame('2025-01-08', $rows[0]['start_date']);
    }

    // SAL-M06: holiday query returns holidays inside the selected range.
    public function testGetMonthlyHolidaysFiltersByDateRange(): void
    {
        $this->createHoliday('2025-01-15');
        $rows = $this->salaryModel->getMonthlyHolidays('2025-01-01', '2025-01-31');
        $this->assertContains('2025-01-15', array_column($rows, 'holiday_date'));
    }

    // SAL-M07: assigned shift is returned for the employee.
    public function testGetEmployeeWorkScheduleReturnsAssignedSchedule(): void
    {
        $employeeId = $this->createEmployee();
        $schedule = $this->salaryModel->getEmployeeWorkSchedule($employeeId);
        $this->assertIsArray($schedule);
        $this->assertSame('09:00:00', $schedule['expected_entry_time']);
        $this->assertSame('18:00:00', $schedule['expected_exit_time']);
    }

    // SAL-M08: invalid month format is rejected.
    public function testCalculateMonthlySalaryRejectsInvalidMonthFormat(): void
    {
        $employeeId = $this->createEmployee();
        $this->expectException(InvalidArgumentException::class);
        $this->salaryModel->calculateMonthlySalary($employeeId, '2025-1');
    }

    // SAL-M09: impossible month is rejected.
    public function testCalculateMonthlySalaryRejectsImpossibleMonth(): void
    {
        $employeeId = $this->createEmployee();
        $this->expectException(InvalidArgumentException::class);
        $this->salaryModel->calculateMonthlySalary($employeeId, '2025-13');
    }

    // SAL-M10: future month is rejected.
    public function testCalculateMonthlySalaryRejectsFutureMonth(): void
    {
        $employeeId = $this->createEmployee();
        $futureMonth = (new DateTimeImmutable('first day of next month'))->format('Y-m');
        $this->expectException(InvalidArgumentException::class);
        $this->salaryModel->calculateMonthlySalary($employeeId, $futureMonth);
    }

    // SAL-M11: nonexistent employee is rejected.
    public function testCalculateMonthlySalaryRejectsUnknownEmployee(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Employee not found');
        $this->salaryModel->calculateMonthlySalary(4294960000, '2025-01');
    }

    // SAL-M12: missing monthly salary is rejected.
    public function testCalculateMonthlySalaryRejectsUnconfiguredSalary(): void
    {
        $employeeId = $this->createEmployee(0);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Monthly salary has not been configured');
        $this->salaryModel->calculateMonthlySalary($employeeId, '2025-01');
    }

    // SAL-M13: missing joining date is rejected.
    public function testCalculateMonthlySalaryRejectsMissingJoiningDate(): void
    {
        $employeeId = $this->createEmployee(30000, null);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('joining date is missing');
        $this->salaryModel->calculateMonthlySalary($employeeId, '2025-01');
    }

    // SAL-M14: no assigned or default schedule is rejected.
    public function testCalculateMonthlySalaryRejectsMissingSchedule(): void
    {
        $employeeId = $this->createEmployee(30000, '2020-01-01', false);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No work schedule is available');
        $this->salaryModel->calculateMonthlySalary($employeeId, '2025-01');
    }

    // SAL-M15: daily salary uses calendar days and Fridays are paid automatically.
    public function testCalculateMonthlySalaryPaysFridaysAndUsesCalendarDayRate(): void
    {
        $employeeId = $this->createEmployee();
        $result = $this->salaryModel->calculateMonthlySalary($employeeId, '2025-01');
        $this->assertSame(31, $result['calendar_days']);
        $this->assertEqualsWithDelta(30000 / 31, $result['daily_salary'], 0.01);
        $this->assertGreaterThan(0, $result['paid_fridays']);
    }

    // SAL-M16: full-day approved leave is paid and is not treated as absence.
    public function testCalculateMonthlySalaryPaysApprovedFullDayLeave(): void
    {
        $employeeId = $this->createEmployee();
        $this->createLeave($employeeId, '2025-01-06', 'Full Day');

        $result = $this->salaryModel->calculateMonthlySalary($employeeId, '2025-01');

        $this->assertSame(1, $result['full_day_leaves']);

        $leaveRecord = array_values(array_filter(
            $result['daily_records'],
            fn($record) => $record['date'] === '2025-01-06'
        ));

        $this->assertCount(1, $leaveRecord);
        $this->assertSame('Full Day Leave', $leaveRecord[0]['type']);
        $this->assertSame('Approved and fully paid', $leaveRecord[0]['note']);
        $this->assertSame(25, $result['absent_days']);
    }

    // SAL-M17: pending leave does not prevent an absence deduction.
    public function testCalculateMonthlySalaryDoesNotPayPendingLeave(): void
    {
        $employeeId = $this->createEmployee();
        $this->createLeave($employeeId, '2025-01-06', 'Full Day', null, 'pending');
        $result = $this->salaryModel->calculateMonthlySalary($employeeId, '2025-01');
        $this->assertSame(0, $result['full_day_leaves']);
        $this->assertGreaterThan(0, $result['absent_days']);
    }

    // SAL-M18: completed required working half makes approved half-day leave fully paid.
    public function testCalculateMonthlySalaryPaysCompletedHalfDayLeave(): void
    {
        $employeeId = $this->createEmployee();
        $this->createLeave($employeeId, '2025-01-06', 'Half Day', 'first_half');
        $this->createAttendance($employeeId, '2025-01-06', [
            'entry_time' => '2025-01-06 09:00:00',
            'exit_time' => '2025-01-06 18:00:00'
        ]);
        $result = $this->salaryModel->calculateMonthlySalary($employeeId, '2025-01');
        $this->assertSame(1, $result['valid_half_day_leaves']);
        $this->assertSame(0, $result['incomplete_half_day_leaves']);
        $this->assertSame(0.0, (float) $result['half_day_deduction']);
    }

    // SAL-M19: incomplete approved half-day leave deducts half a day's salary.
    public function testCalculateMonthlySalaryDeductsIncompleteHalfDayLeave(): void
    {
        $employeeId = $this->createEmployee();
        $this->createLeave($employeeId, '2025-01-06', 'Half Day', 'first_half');
        $result = $this->salaryModel->calculateMonthlySalary($employeeId, '2025-01');
        $this->assertSame(1, $result['incomplete_half_day_leaves']);
        $this->assertEqualsWithDelta(30000 / 31 / 2, $result['half_day_deduction'], 0.02);
    }

    // SAL-M20: organizational holiday is paid.
    public function testCalculateMonthlySalaryPaysOrganizationalHoliday(): void
    {
        $employeeId = $this->createEmployee();
        $this->createHoliday('2025-01-06'); // Monday
        $result = $this->salaryModel->calculateMonthlySalary($employeeId, '2025-01');
        $this->assertSame(1, $result['paid_holidays']);
    }

    // SAL-M21: a completed absent working day incurs one daily salary deduction.
    public function testCalculateMonthlySalaryDeductsFullDayForAbsence(): void
    {
        $employeeId = $this->createEmployee();
        $result = $this->salaryModel->calculateMonthlySalary($employeeId, '2025-01');
        $this->assertGreaterThan(0, $result['absent_days']);
        $this->assertGreaterThan(0, $result['absence_deduction']);
    }

    // SAL-M22: first three late/early occurrences are free; fourth costs half day.
    public function testCalculateMonthlySalaryAppliesFirstThreeFreeOccurrenceRule(): void
    {
        $employeeId = $this->createEmployee();
        $dates = ['2025-01-06', '2025-01-07', '2025-01-08', '2025-01-09'];
        foreach ($dates as $date) {
            $this->createAttendance($employeeId, $date, ['entry_status' => 'Entry - Late']);
        }
        $result = $this->salaryModel->calculateMonthlySalary($employeeId, '2025-01');
        $this->assertSame(4, $result['late_occurrences']);
        $this->assertSame(3, $result['free_occurrences']);
        $this->assertSame(1, $result['deductible_occurrences']);
        $this->assertEqualsWithDelta(30000 / 31 / 2, $result['late_early_deduction'], 0.02);
    }

    // SAL-M23: late entry and early exit are counted as separate occurrences.
    public function testCalculateMonthlySalaryCountsLateAndEarlyExitSeparately(): void
    {
        $employeeId = $this->createEmployee();
        $this->createAttendance($employeeId, '2025-01-06', [
            'entry_status' => 'Entry - Late',
            'exit_status' => 'Exit - Before'
        ]);
        $result = $this->salaryModel->calculateMonthlySalary($employeeId, '2025-01');
        $this->assertSame(1, $result['late_occurrences']);
        $this->assertSame(1, $result['early_exit_occurrences']);
        $this->assertSame(2, $result['free_occurrences']);
        $this->assertSame(0, $result['deductible_occurrences']);
    }

    // SAL-M24: salary cannot fall below zero.
    public function testCalculateMonthlySalaryNeverReturnsNegativePayableSalary(): void
    {
        $employeeId = $this->createEmployee(100);
        $result = $this->salaryModel->calculateMonthlySalary($employeeId, '2025-01');
        $this->assertGreaterThanOrEqual(0, $result['payable_salary']);
    }

    // SAL-M25: saving a valid salary breakdown inserts a record and JSON details.
    public function testSaveMonthlySalaryInsertsRecordAndBreakdown(): void
    {
        $employeeId = $this->createEmployee();
        $payload = $this->salaryPayload($employeeId, '2025-01');
        $this->assertTrue($this->salaryModel->saveMonthlySalary($payload));
        $id = $this->getSalaryRowId($employeeId, '2025-01');
        $details = $this->salaryModel->getSalaryDetails($id);
        $this->assertIsArray($details['calculation_details']);
        $this->assertSame($this->tag, $details['calculation_details']['test_marker']);
        $this->assertNotEmpty($details['calculated_at']);
    }

    // SAL-M26: saving the same employee/month updates the existing record.
    public function testSaveMonthlySalaryUpdatesExistingEmployeeMonthRecord(): void
    {
        $employeeId = $this->createEmployee();
        $this->assertTrue($this->salaryModel->saveMonthlySalary($this->salaryPayload($employeeId, '2025-02', 30000, 100, 29900)));
        $firstId = $this->getSalaryRowId($employeeId, '2025-02');
        $this->assertTrue($this->salaryModel->saveMonthlySalary($this->salaryPayload($employeeId, '2025-02', 32000, 200, 31800)));
        $secondId = $this->getSalaryRowId($employeeId, '2025-02');
        $this->assertSame($firstId, $secondId);
        $details = $this->salaryModel->getSalaryDetails($secondId);
        $this->assertSame(32000.0, (float) $details['basic_salary']);
        $this->assertSame(200.0, (float) $details['deductions']);
        $this->assertSame(31800.0, (float) $details['final_salary']);
    }

    // SAL-M27: missing required payload key is rejected.
    public function testSaveMonthlySalaryRejectsMissingRequiredField(): void
    {
        $employeeId = $this->createEmployee();
        $payload = $this->salaryPayload($employeeId, '2025-03');
        unset($payload['payable_salary']);
        $this->expectException(InvalidArgumentException::class);
        $this->salaryModel->saveMonthlySalary($payload);
    }

    // SAL-M28: invalid employee ID is rejected.
    public function testSaveMonthlySalaryRejectsInvalidEmployeeId(): void
    {
        $payload = $this->salaryPayload(0, '2025-03');
        $this->expectException(InvalidArgumentException::class);
        $this->salaryModel->saveMonthlySalary($payload);
    }

    // SAL-M29: invalid month is rejected.
    public function testSaveMonthlySalaryRejectsInvalidMonth(): void
    {
        $employeeId = $this->createEmployee();
        $this->expectException(InvalidArgumentException::class);
        $this->salaryModel->saveMonthlySalary($this->salaryPayload($employeeId, '2025-13'));
    }

    // SAL-M30: negative salary amounts are rejected.
    public function testSaveMonthlySalaryRejectsNegativeAmounts(): void
    {
        $employeeId = $this->createEmployee();
        $this->expectException(InvalidArgumentException::class);
        $this->salaryModel->saveMonthlySalary($this->salaryPayload($employeeId, '2025-03', 30000, -1, 30000));
    }

    // SAL-M31: salary history can filter by month and employee.
    public function testGetSalaryHistoryFiltersByMonthAndEmployee(): void
    {
        $employeeId = $this->createEmployee();
        $this->salaryModel->saveMonthlySalary($this->salaryPayload($employeeId, '2025-04'));
        $this->getSalaryRowId($employeeId, '2025-04');
        $rows = $this->salaryModel->getSalaryHistory('2025-04', $employeeId);
        $this->assertCount(1, $rows);
        $this->assertSame($employeeId, (int) $rows[0]['employee_id']);
        $this->assertSame('2025-04-01', $rows[0]['salary_month']);
    }

    // SAL-M32: invalid history filters are rejected.
    public function testGetSalaryHistoryRejectsInvalidMonthFilter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->salaryModel->getSalaryHistory('2025-4');
    }

    // SAL-M33: invalid employee filter is rejected.
    public function testGetSalaryHistoryRejectsInvalidEmployeeFilter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->salaryModel->getSalaryHistory(null, 0);
    }

    // SAL-M34: salary details returns null when record does not exist.
    public function testGetSalaryDetailsReturnsNullForMissingRecord(): void
    {
        $this->assertNull($this->salaryModel->getSalaryDetails(4294960000));
    }

    // SAL-M35: salary details rejects non-positive IDs.
    public function testGetSalaryDetailsRejectsInvalidId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->salaryModel->getSalaryDetails(0);
    }
}
