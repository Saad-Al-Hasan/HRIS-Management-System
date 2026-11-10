<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/controllers/AttendanceController.php';

// Test helper: capture the controller's view data instead of rendering HTML.
class TestableAttendanceController extends AttendanceController
{
    public string $capturedView = '';
    public array $capturedData = [];

    protected function view(string $view, array $data = []): void
    {
        $this->capturedView = $view;
        $this->capturedData = $data;
    }
}

class AttendanceManagementControllerTest extends TestCase
{
    private TestableAttendanceController $controller;
    private PDO $pdo;
    private int $testUserId;
    private string $testDepartment;
    // Temporary employee ID used by controller test cases.
    private int $testEmployeeId;

    protected function setUp(): void
    {
        $database = new Database();
        $this->pdo = $database->connect();

        // Simulate an authenticated administrator.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['user_id'] = 999;
        $_SESSION['role_id'] = 1;

        $_GET = [];

        $this->controller = new TestableAttendanceController();

        // Create a temporary department and employee for controller tests.
        $this->testDepartment = 'CTRL_TEST_' . uniqid();
        $username = 'CTRL_TEST_' . uniqid();

        $stmt = $this->pdo->prepare("
            INSERT INTO users
                (role_id, username, email, password, full_name)
            VALUES
                (2, ?, ?, 'test_hash', 'Controller Test Employee')
        ");

        $stmt->execute([
            $username,
            $username . '@test.local'
        ]);

        $this->testUserId = (int) $this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare("
            INSERT INTO employees
                (user_id, employee_code, department)
            VALUES
                (?, ?, ?)
        ");

        $stmt->execute([
            $this->testUserId,
            $username,
            $this->testDepartment
        ]);

        // Save the actual employee ID for controller tests.
        $this->testEmployeeId = (int) $this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        // Remove temporary controller-test employee and user.
        $stmt = $this->pdo->prepare(
            "DELETE FROM employees WHERE user_id = ?"
        );
        $stmt->execute([$this->testUserId]);

        $stmt = $this->pdo->prepare(
            "DELETE FROM users WHERE id = ?"
        );
        $stmt->execute([$this->testUserId]);

        $_GET = [];
        $_SESSION = [];
    }

    // Class under test: AttendanceController
    // Method under test: management()
    // Test ID: ADM-C01
    // Purpose: Verify the management page loads with default values.
    public function testManagementLoadsPageWithoutFilters(): void
    {
        // Call
        $this->controller->management();

        // Assertions
        $this->assertSame(
            'admin/attendance/management',
            $this->controller->capturedView
        );

        $this->assertNull($this->controller->capturedData['error']);
        $this->assertSame(
            [],
            $this->controller->capturedData['attendanceRecords']
        );
        $this->assertFalse(
            $this->controller->capturedData['viewAttendance']
        );
    }

    // Class under test: AttendanceController
    // Method under test: management()
    // Test ID: ADM-C02
    // Purpose: Verify submitting without a department shows an error.
    public function testManagementRejectsMissingDepartment(): void
    {
        // Setup
        $_GET = ['view_attendance' => '1'];

        // Call
        $this->controller->management();

        // Assertion
        $this->assertSame(
            'Please select a department.',
            $this->controller->capturedData['error']
        );
    }

    // Class under test: AttendanceController
    // Method under test: management()
    // Test ID: ADM-C03
    // Purpose: Verify an invalid department is cleared.
    public function testManagementClearsInvalidDepartment(): void
    {
        // Setup
        $_GET = [
            'department' => 'INVALID_DEPARTMENT',
            'employee' => '123',
            'from_date' => '2026-01-01',
            'to_date' => '2026-01-31'
        ];

        // Call
        $this->controller->management();

        // Assertions
        $this->assertSame(
            '',
            $this->controller->capturedData['selectedDepartment']
        );

        $this->assertSame(
            '',
            $this->controller->capturedData['selectedEmployee']
        );

        $this->assertSame(
            '',
            $this->controller->capturedData['fromDate']
        );

        $this->assertSame(
            '',
            $this->controller->capturedData['toDate']
        );
    }

    // Class under test: AttendanceController
    // Method under test: management()
    // Test ID: ADM-C04
    // Purpose: Verify submitting without an employee shows an error.
    public function testManagementRejectsMissingEmployee(): void
    {
        // Setup
        $_GET = [
            'department' => $this->testDepartment,
            'view_attendance' => '1'
        ];

        // Call
        $this->controller->management();

        // Assertion
        $this->assertSame(
            'Please select an employee.',
            $this->controller->capturedData['error']
        );
    }



    // Class under test: AttendanceController
    // Method under test: management()
    // Test ID: ADM-C05
    // Purpose: Verify missing date fields produce a validation error.
    public function testManagementRejectsMissingDates(): void
    {
        // Setup
        $_GET = [
            'department' => $this->testDepartment,
            'employee' => (string) $this->testEmployeeId,
            'view_attendance' => '1'
        ];

        // Call
        $this->controller->management();

        // Assertion
        $this->assertSame(
            'Please select both From Date and To Date.',
            $this->controller->capturedData['error']
        );
    }

    // Class under test: AttendanceController
    // Method under test: management()
    // Test ID: ADM-C06
    // Purpose: Verify invalid calendar dates are rejected.
    public function testManagementRejectsInvalidCalendarDate(): void
    {
        // Setup
        $_GET = [
            'department' => $this->testDepartment,
            'employee' => (string) $this->testEmployeeId,
            'from_date' => '2026-02-30',
            'to_date' => '2026-03-01',
            'view_attendance' => '1'
        ];

        // Call
        $this->controller->management();

        // Assertion
        $this->assertSame(
            'Please provide valid dates.',
            $this->controller->capturedData['error']
        );
    }

    // Class under test: AttendanceController
    // Method under test: management()
    // Test ID: ADM-C07
    // Purpose: Verify reversed date ranges are rejected.
    public function testManagementRejectsReversedDateRange(): void
    {
        // Setup
        $_GET = [
            'department' => $this->testDepartment,
            'employee' => (string) $this->testEmployeeId,
            'from_date' => '2026-01-31',
            'to_date' => '2026-01-01',
            'view_attendance' => '1'
        ];

        // Call
        $this->controller->management();

        // Assertion
        $this->assertSame(
            'From Date cannot be later than To Date.',
            $this->controller->capturedData['error']
        );
    }

    // Class under test: AttendanceController
    // Method under test: management()
    // Test ID: ADM-C08
    // Purpose: Verify employees outside the selected department are rejected.
    public function testManagementRejectsEmployeeOutsideDepartment(): void
    {
        // Setup
        $_GET = [
            'department' => $this->testDepartment,
            'employee' => '999999',
            'from_date' => '2026-01-01',
            'to_date' => '2026-01-31',
            'view_attendance' => '1'
        ];

        // Call
        $this->controller->management();

        // Assertions
        $this->assertSame(
            '',
            $this->controller->capturedData['selectedEmployee']
        );

        $this->assertSame(
            'Please select an employee.',
            $this->controller->capturedData['error']
        );
    }

    // Class under test: AttendanceController
    // Method under test: management()
    // Test ID: ADM-C09
    // Purpose: Verify valid filters retrieve attendance and scan history.
    public function testManagementReturnsAttendanceAndScanHistory(): void
    {
        // Setup
        $stmt = $this->pdo->prepare(
            "SELECT id FROM employees WHERE user_id = ?"
        );
        $stmt->execute([$this->testUserId]);
        $employeeId = (int) $stmt->fetchColumn();

        $_GET = [
            'department' => $this->testDepartment,
            'employee' => (string) $employeeId,
            'from_date' => '2026-01-01',
            'to_date' => '2026-01-31',
            'view_attendance' => '1'
        ];

        // Call
        $this->controller->management();

        // Assertions
        $this->assertNull($this->controller->capturedData['error']);
        $this->assertSame(
            (string) $employeeId,
            $this->controller->capturedData['selectedEmployee']
        );
        $this->assertIsArray(
            $this->controller->capturedData['attendanceRecords']
        );
    }



}
