<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/models/Employee.php';

class AttendanceManagementTest extends TestCase
{
    // Properties for Attendance test cases.
    private PDO $pdo;
    private Attendance $attendance;
    private array $createdUserIds = [];
    private string $prefix;

    // Test data for getEmployeeAttendanceByDateRange().
    private int $testEmployeeId;
    private string $testAttendanceDate = '2026-01-15';

    protected function setUp(): void
    {
        $database = new Database();
        $this->pdo = $database->connect();

        $this->attendance = new Attendance();
        $this->prefix = 'TEST_' . uniqid();

        // Create temporary employee records.
        $departments = [
            $this->prefix . '_HR',
            $this->prefix . '_IT',
            $this->prefix . '_Finance',
            '',
            null
        ];

        foreach ($departments as $index => $department) {
            $username = $this->prefix . '_' . $index;

            $stmt = $this->pdo->prepare("
                INSERT INTO users
                    (role_id, username, email, password, full_name)
                VALUES
                    (2, ?, ?, 'test_hash', ?)
            ");

            $stmt->execute([
                $username,
                $username . '@test.local',
                'Test Employee ' . $index
            ]);


            // Class under test: Attendance
            // Method under test: getEmployeeScanHistoryForDate()
            // Purpose: Create temporary RFID scan records for ADM-09.




            $userId = (int) $this->pdo->lastInsertId();
            $this->createdUserIds[] = $userId;

            $stmt = $this->pdo->prepare("
                INSERT INTO employees
                    (user_id, employee_code, department)
                VALUES
                    (?, ?, ?)
            ");

            $stmt->execute([
                $userId,
                $username,
                $department
            ]);
            // Save the IT employee ID for attendance tests.
            if ($index === 1) {
                $this->testEmployeeId = (int) $this->pdo->lastInsertId();
            }
        }


        // Class under test: Attendance
        // Method under test: getEmployeeAttendanceByDateRange()
        // Purpose: Insert a temporary attendance record for ADM-05.
        $stmt = $this->pdo->prepare("
        INSERT INTO attendance
        (employee_id, attendance_date, entry_time, status)
        VALUES
        (?, ?, ?, ?)
        ");

        $stmt->execute([
            $this->testEmployeeId,
            $this->testAttendanceDate,
            $this->testAttendanceDate . ' 09:00:00',
            'present'
        ]);

        // Class under test: Attendance
        // Method under test: getEmployeeScanHistoryForDate()
        // Test ID: ADM-09
        // Purpose: Create temporary RFID scans for chronological-order testing.

        $stmt = $this->pdo->prepare("
        INSERT INTO attendance_scan_history
        (employee_id, attendance_date, scan_time, scan_type, scan_status)
        VALUES
        (?, ?, ?, ?, ?)
        ");

        // Insert exit first to verify chronological sorting.
        $stmt->execute([
            $this->testEmployeeId,
            $this->testAttendanceDate,
            $this->testAttendanceDate . ' 18:00:00',
            'exit',
            'on_time'
        ]);

        $stmt->execute([
            $this->testEmployeeId,
            $this->testAttendanceDate,
            $this->testAttendanceDate . ' 09:00:00',
            'entry',
            'on_time'
        ]);
    }

    protected function tearDown(): void
    {
        // Remove temporary employees and their user accounts.
        foreach ($this->createdUserIds as $userId) {
            $stmt = $this->pdo->prepare(
                "DELETE FROM employees WHERE user_id = ?"
            );
            $stmt->execute([$userId]);

            $stmt = $this->pdo->prepare(
                "DELETE FROM users WHERE id = ?"
            );
            $stmt->execute([$userId]);
        }
    }

    // ADM-01: Normal input - retrieve distinct departments.
    public function testGetAllDepartmentsReturnsDistinctDepartments(): void
    {
        // Call
        $result = $this->attendance->getAllDepartments();

        // Extract department names from the result.
        $names = array_column($result, 'department');

        // Assertion
        $this->assertContains($this->prefix . '_HR', $names);
        $this->assertContains($this->prefix . '_IT', $names);
        $this->assertContains($this->prefix . '_Finance', $names);

        $this->assertSame(
            count($names),
            count(array_unique($names))
        );
    }

    // ADM-02: Abnormal input - exclude NULL and empty departments.
    public function testGetAllDepartmentsExcludesEmptyDepartments(): void
    {
        // Call
        $result = $this->attendance->getAllDepartments();

        // Extract department names.
        $names = array_column($result, 'department');

        // Assertion
        $this->assertNotContains('', $names);
        $this->assertNotContains(null, $names);
    }


    // ADM-03: Normal input - retrieve employees by department.
    public function testGetEmployeesByDepartmentReturnsMatchingEmployees(): void
    {
        // Call
        $department = $this->prefix . '_IT';
        $result = $this->attendance->getEmployeesByDepartment($department);

        // Assertion
        $this->assertNotEmpty($result);

        foreach ($result as $employee) {
            $this->assertSame($department, $employee['department']);
        }
    }

    // ADM-04: Abnormal input - empty department name.
    public function testGetEmployeesByDepartmentReturnsEmptyForBlankDepartment(): void
    {
        // Call
        $result = $this->attendance->getEmployeesByDepartment('');

        // Assertion
        $this->assertSame([], $result);
    }



    // Class under test: Attendance
    // Method under test: getEmployeeAttendanceByDateRange()
    // Test ID: ADM-05
    // Purpose: Verify attendance is returned for a valid employee and date range.
    public function testGetEmployeeAttendanceByDateRangeReturnsMatchingRecords(): void
    {
        // Call
        $result = $this->attendance->getEmployeeAttendanceByDateRange(
            $this->testEmployeeId,
            '2026-01-01',
            '2026-01-31'
        );

        // Assertions
        $this->assertCount(1, $result);
        $this->assertSame(
            $this->testEmployeeId,
            (int) $result[0]['employee_id']
        );
        $this->assertSame(
            $this->testAttendanceDate,
            $result[0]['attendance_date']
        );
    }

    // Class under test: Attendance
    // Method under test: getEmployeeAttendanceByDateRange()
    // Test ID: ADM-06
    // Purpose: Verify an invalid employee ID returns an empty array.
    public function testGetEmployeeAttendanceByDateRangeRejectsInvalidEmployeeId(): void
    {
        // Call
        $result = $this->attendance->getEmployeeAttendanceByDateRange(
            0,
            '2026-01-01',
            '2026-01-31'
        );

        // Assertion
        $this->assertSame([], $result);
    }

    // Class under test: Attendance
    // Method under test: getEmployeeAttendanceByDateRange()
    // Test ID: ADM-07
    // Purpose: Verify a reversed date range returns an empty array.
    public function testGetEmployeeAttendanceByDateRangeRejectsReversedDates(): void
    {
        // Call
        $result = $this->attendance->getEmployeeAttendanceByDateRange(
            $this->testEmployeeId,
            '2026-01-31',
            '2026-01-01'
        );

        // Assertion
        $this->assertSame([], $result);
    }

    // Class under test: Attendance
    // Method under test: getEmployeeAttendanceByDateRange()
    // Test ID: ADM-08
    // Purpose: Verify an empty date input returns an empty array.
    public function testGetEmployeeAttendanceByDateRangeRejectsEmptyDates(): void
    {
        // Call
        $result = $this->attendance->getEmployeeAttendanceByDateRange(
            $this->testEmployeeId,
            '',
            '2026-01-31'
        );

        // Assertion
        $this->assertSame([], $result);
    }



    // Class under test: Attendance
    // Method under test: getEmployeeScanHistoryForDate()
    // Test ID: ADM-09
    // Purpose: Verify scans are returned in chronological order.
    public function testGetEmployeeScanHistoryForDateReturnsChronologicalScans(): void
    {
        // Call
        $result = $this->attendance->getEmployeeScanHistoryForDate(
            $this->testEmployeeId,
            $this->testAttendanceDate
        );

        // Assertions
        $this->assertCount(2, $result);

        $this->assertSame(
            'entry',
            $result[0]['scan_type']
        );

        $this->assertSame(
            'exit',
            $result[1]['scan_type']
        );

        $this->assertSame(
            '2026-01-15 09:00:00',
            $result[0]['scan_time']
        );

        $this->assertSame(
            '2026-01-15 18:00:00',
            $result[1]['scan_time']
        );
    }

    // Class under test: Attendance
    // Method under test: getEmployeeScanHistoryForDate()
    // Test ID: ADM-10
    // Purpose: Verify an invalid employee ID returns an empty array.
    public function testGetEmployeeScanHistoryForDateRejectsInvalidEmployeeId(): void
    {
        // Call
        $result = $this->attendance->getEmployeeScanHistoryForDate(
            0,
            $this->testAttendanceDate
        );

        // Assertion
        $this->assertSame([], $result);
    }

    // Class under test: Attendance
    // Method under test: getEmployeeScanHistoryForDate()
    // Test ID: ADM-11
    // Purpose: Verify an empty attendance date returns an empty array.
    public function testGetEmployeeScanHistoryForDateRejectsEmptyDate(): void
    {
        // Call
        $result = $this->attendance->getEmployeeScanHistoryForDate(
            $this->testEmployeeId,
            ''
        );

        // Assertion
        $this->assertSame([], $result);
    }

}
