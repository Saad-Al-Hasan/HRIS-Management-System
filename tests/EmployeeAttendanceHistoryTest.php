<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/controllers/EmployeeController.php';

// Test helper: capture view data without rendering HTML.
class TestableEmployeeHistoryController extends EmployeeController
{
    public string $capturedView = '';
    public array $capturedData = [];

    protected function view(string $view, array $data = []): void
    {
        $this->capturedView = $view;
        $this->capturedData = $data;
    }
}

class EmployeeAttendanceHistoryTest extends TestCase
{
    private PDO $pdo;
    private TestableEmployeeHistoryController $controller;

    private int $testUserId;
    private int $testEmployeeId;

    private int $otherUserId;
    private int $otherEmployeeId;

    private string $testDate;

    protected function setUp(): void
    {
        // Setup: connect to the test database.
        $database = new Database();
        $this->pdo = $database->connect();

        $this->controller = new TestableEmployeeHistoryController();

        // Simulate an authenticated employee.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = [
            'user_id' => 999,
            'role_id' => 2
        ];

        $_GET = [];

        $this->testDate = date('Y-m-d');

        // Create the authenticated employee.
        $username = 'HISTORY_TEST_' . uniqid();

        $stmt = $this->pdo->prepare("
            INSERT INTO users
                (role_id, username, email, password, full_name)
            VALUES
                (2, ?, ?, 'test_hash', 'History Test Employee')
        ");

        $stmt->execute([
            $username,
            $username . '@test.local'
        ]);

        $this->testUserId = (int) $this->pdo->lastInsertId();

        // Class under test: EmployeeController
        // Setup: authenticate using the actual temporary employee account.
        $_SESSION['user_id'] = $this->testUserId;
        $_SESSION['role_id'] = 2;

        $stmt = $this->pdo->prepare("
            INSERT INTO employees
                (user_id, employee_code, department)
            VALUES (?, ?, ?)
        ");

        $stmt->execute([
            $this->testUserId,
            $username,
            'HISTORY_TEST'
        ]);

        $this->testEmployeeId = (int) $this->pdo->lastInsertId();

        // Create another employee to test data isolation.
        $otherUsername = 'OTHER_HISTORY_' . uniqid();

        $stmt = $this->pdo->prepare("
            INSERT INTO users
                (role_id, username, email, password, full_name)
            VALUES
                (2, ?, ?, 'test_hash', 'Other Test Employee')
        ");

        $stmt->execute([
            $otherUsername,
            $otherUsername . '@test.local'
        ]);

        $this->otherUserId = (int) $this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare("
            INSERT INTO employees
                (user_id, employee_code, department)
            VALUES (?, ?, ?)
        ");

        $stmt->execute([
            $this->otherUserId,
            $otherUsername,
            'OTHER_TEST'
        ]);

        $this->otherEmployeeId = (int) $this->pdo->lastInsertId();

        // Create attendance for the authenticated employee.
        $stmt = $this->pdo->prepare("
            INSERT INTO attendance
                (employee_id, attendance_date, entry_time, entry_status, status)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $this->testEmployeeId,
            $this->testDate,
            $this->testDate . ' 09:00:00',
            'Entry - In Time',
            'present'
        ]);

        // Create an RFID entry scan for the authenticated employee.
        $stmt = $this->pdo->prepare("
            INSERT INTO attendance_scan_history
                (employee_id, attendance_date, scan_time, scan_type, scan_status)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $this->testEmployeeId,
            $this->testDate,
            $this->testDate . ' 09:00:00',
            'entry',
            'Entry - In Time'
        ]);

        // Create attendance for the other employee.
        $stmt = $this->pdo->prepare("
            INSERT INTO attendance
                (employee_id, attendance_date, entry_time, entry_status, status)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $this->otherEmployeeId,
            $this->testDate,
            $this->testDate . ' 09:30:00',
            'Entry - In Time',
            'present'
        ]);
    }

    protected function tearDown(): void
    {
        // Cleanup: delete temporary employees and their related records.
        $stmt = $this->pdo->prepare(
            "DELETE FROM employees WHERE id IN (?, ?)"
        );

        $stmt->execute([
            $this->testEmployeeId,
            $this->otherEmployeeId
        ]);

        $stmt = $this->pdo->prepare(
            "DELETE FROM users WHERE id IN (?, ?)"
        );

        $stmt->execute([
            $this->testUserId,
            $this->otherUserId
        ]);

        $_GET = [];
        $_SESSION = [];
    }

    // Class under test: EmployeeController
    // Method under test: dashboard()
    // Test ID: EMP-H01
    // Purpose: Verify employee dashboard loads attendance history.
    public function testDashboardLoadsEmployeeAttendanceHistory(): void
    {
        // Call
        $this->controller->dashboard();

        // Assertions
        $this->assertSame(
            'employee/dashboard',
            $this->controller->capturedView
        );

        $this->assertCount(
            1,
            $this->controller->capturedData['attendanceRecords']
        );

        $this->assertSame(
            $this->testEmployeeId,
            (int) $this->controller->capturedData['employee']['employee_id']
        );
    }

    // Class under test: EmployeeController
    // Method under test: dashboard()
    // Test ID: EMP-H02
    // Purpose: Verify default date range uses the current month through today.
    public function testDashboardUsesDefaultCurrentMonthDateRange(): void
    {
        // Call
        $this->controller->dashboard();

        // Assertions
        $this->assertSame(
            '',
            $this->controller->capturedData['attendanceFromDate']
        );

        $this->assertSame(
            '',
            $this->controller->capturedData['attendanceToDate']
        );

        $this->assertCount(
            1,
            $this->controller->capturedData['attendanceRecords']
        );
    }

    // Class under test: EmployeeController
    // Method under test: dashboard()
    // Test ID: EMP-H03
    // Purpose: Verify custom date filters are applied.
    public function testDashboardAppliesCustomDateFilters(): void
    {
        // Setup
        $_GET = [
            'attendance_from' => $this->testDate,
            'attendance_to' => $this->testDate
        ];

        // Call
        $this->controller->dashboard();

        // Assertions
        $this->assertSame(
            $this->testDate,
            $this->controller->capturedData['attendanceFromDate']
        );

        $this->assertSame(
            $this->testDate,
            $this->controller->capturedData['attendanceToDate']
        );

        $this->assertCount(
            1,
            $this->controller->capturedData['attendanceRecords']
        );
    }

    // Class under test: EmployeeController
    // Method under test: dashboard()
    // Test ID: EMP-H04
    // Purpose: Verify invalid calendar dates produce a validation error.
    public function testDashboardRejectsInvalidCalendarDate(): void
    {
        // Setup
        $_GET = [
            'attendance_from' => '2026-02-30',
            'attendance_to' => '2026-03-01'
        ];

        // Call
        $this->controller->dashboard();

        // Assertions
        $this->assertSame(
            'Please enter valid From Date and To Date values.',
            $this->controller->capturedData['attendanceError']
        );

        $this->assertSame(
            [],
            $this->controller->capturedData['attendanceRecords']
        );
    }

    // Class under test: EmployeeController
    // Method under test: dashboard()
    // Test ID: EMP-H05
    // Purpose: Verify reversed date ranges are rejected.
    public function testDashboardRejectsReversedDateRange(): void
    {
        // Setup
        $_GET = [
            'attendance_from' => '2026-02-20',
            'attendance_to' => '2026-02-01'
        ];

        // Call
        $this->controller->dashboard();

        // Assertions
        $this->assertSame(
            'From Date cannot be later than To Date.',
            $this->controller->capturedData['attendanceError']
        );

        $this->assertSame(
            [],
            $this->controller->capturedData['attendanceRecords']
        );
    }

    // Class under test: EmployeeController
    // Method under test: dashboard()
    // Test ID: EMP-H06
    // Purpose: Verify RFID scan history is attached to attendance records.
    public function testDashboardAttachesScanHistoryToAttendance(): void
    {
        // Call
        $this->controller->dashboard();

        // Assertions
        $records = $this->controller->capturedData['attendanceRecords'];

        $this->assertArrayHasKey('scan_history', $records[0]);
        $this->assertCount(1, $records[0]['scan_history']);

        $this->assertSame(
            'entry',
            $records[0]['scan_history'][0]['scan_type']
        );
    }

    // Class under test: EmployeeController
    // Method under test: dashboard()
    // Test ID: EMP-H07
    // Purpose: Verify one employee cannot see another employee's attendance.
    public function testDashboardDoesNotExposeOtherEmployeesAttendance(): void
    {
        // Call
        $this->controller->dashboard();

        // Assertions
        $records = $this->controller->capturedData['attendanceRecords'];

        $this->assertCount(1, $records);

        $this->assertSame(
            $this->testEmployeeId,
            (int) $records[0]['employee_id']
        );

        $this->assertNotSame(
            $this->otherEmployeeId,
            (int) $records[0]['employee_id']
        );
    }


    // Class under test: EmployeeController
    // Method under test: dashboard()
    // Test ID: EMP-H09
    // Purpose: Verify a user without an employee profile receives HTTP 404.
    public function testDashboardReturns404WhenEmployeeProfileIsMissing(): void
    {
        // Setup: create an authenticated employee-role user without a profile.
        $username = 'NO_PROFILE_' . uniqid();

        $stmt = $this->pdo->prepare("
            INSERT INTO users
                (role_id, username, email, password, full_name)
            VALUES
                (2, ?, ?, 'test_hash', 'User Without Employee Profile')
        ");

        $stmt->execute([
            $username,
            $username . '@test.local'
        ]);

        $orphanUserId = (int) $this->pdo->lastInsertId();

        // Authenticate as the user without an employee profile.
        $_SESSION['user_id'] = $orphanUserId;
        $_SESSION['role_id'] = 2;

        // Call
        try {
            $this->controller->dashboard();

            // Assertions
            $this->assertSame(404, http_response_code());

            $this->assertSame(
                'employee/dashboard',
                $this->controller->capturedView
            );

            $this->assertSame(
                [],
                $this->controller->capturedData['attendanceRecords']
            );
        } finally {
            // Cleanup
            $stmt = $this->pdo->prepare(
                "DELETE FROM users WHERE id = ?"
            );
            $stmt->execute([$orphanUserId]);

            http_response_code(200);
        }
    }

}
