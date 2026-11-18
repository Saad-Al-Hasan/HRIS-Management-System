<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/controllers/EmployeeController.php';

// Unique redirect exception to avoid conflicts with other test files.
class LeaveApplicationRedirectException extends RuntimeException
{
}

// Test helper: capture views and redirects without terminating PHPUnit.
class TestableLeaveApplicationController extends EmployeeController
{
    public string $capturedView = '';
    public array $capturedData = [];
    public string $redirectUrl = '';

    protected function view(string $view, array $data = []): void
    {
        $this->capturedView = $view;
        $this->capturedData = $data;
    }

    protected function redirect(string $url): void
    {
        $this->redirectUrl = $url;
        throw new LeaveApplicationRedirectException($url);
    }
}

class LeaveApplicationControllerTest extends TestCase
{
    private TestableLeaveApplicationController $controller;
    private ?PDO $pdo = null;

    private int $testUserId;
    private int $testEmployeeId;

    private string $testCsrfToken = 'leave-application-test-token';

    protected function setUp(): void
    {
        // Connect to the separate test database.
        $database = new Database();
        $this->pdo = $database->connect();

        // Safety check: never allow the live database.
        $databaseName = $this->pdo
            ->query('SELECT DATABASE()')
            ->fetchColumn();

        $this->assertSame(
            'hris_management_system_test',
            $databaseName,
            'Leave tests must use the test database only.'
        );

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Create a unique employee account for this test.
        $username = 'LEAVE_TEST_' . bin2hex(random_bytes(5));

        $stmt = $this->pdo->prepare("
            INSERT INTO users
                (role_id, username, email, password, full_name)
            VALUES
                (2, ?, ?, 'test_hash', 'Leave Test Employee')
        ");

        $stmt->execute([
            $username,
            $username . '@test.local'
        ]);

        $this->testUserId = (int) $this->pdo->lastInsertId();

        // Create the employee profile.
        $stmt = $this->pdo->prepare("
            INSERT INTO employees
                (user_id, employee_code, department)
            VALUES
                (?, ?, ?)
        ");

        $stmt->execute([
            $this->testUserId,
            $username,
            'LEAVE_TEST'
        ]);

        $this->testEmployeeId = (int) $this->pdo->lastInsertId();

        // Simulate employee authentication.
        $_SESSION = [
            'user_id' => $this->testUserId,
            'role_id' => 2,
            'csrf_token' => $this->testCsrfToken
        ];

        $_GET = [];
        $_POST = [];

        $_SERVER['REQUEST_METHOD'] = 'POST';

        http_response_code(200);

        // Initialize the controller under test.
        $this->controller = new TestableLeaveApplicationController();
    }

    protected function tearDown(): void
    {
        // Remove only leave requests belonging to this test employee.
        if (isset($this->testEmployeeId)) {
            $stmt = $this->pdo->prepare("
                DELETE FROM leave_requests
                WHERE employee_id = ?
            ");

            $stmt->execute([$this->testEmployeeId]);

            // Remove the temporary employee and user.
            $stmt = $this->pdo->prepare("
                DELETE FROM employees
                WHERE id = ?
            ");

            $stmt->execute([$this->testEmployeeId]);

            $stmt = $this->pdo->prepare("
                DELETE FROM users
                WHERE id = ?
            ");

            $stmt->execute([$this->testUserId]);
        }

        // Release controller model connections before the next test.
        unset($this->controller);

        $_GET = [];
        $_POST = [];
        $_SESSION = [];

        http_response_code(200);

        $this->pdo = null;
        gc_collect_cycles();
    }

    /**
     * Prepare valid leave application data.
     */
    private function validLeavePostData(array $overrides = []): array
    {
        return array_merge([
            'csrf_token' => $this->testCsrfToken,
            'leave_date' => '2095-05-15',
            'leave_type' => 'Full Day',
            'half_day_period' => '',
            'reason' => 'Leave application controller test.'
        ], $overrides);
    }

    /**
     * Execute the controller method and capture its redirect.
     */
    private function invokeStore(): void
    {
        try {
            $this->controller->storeLeaveApplication();
        } catch (LeaveApplicationRedirectException $e) {
            // Simulate the real redirect terminating execution.
        }
    }

    /**
     * Retrieve the latest leave request created by this test employee.
     */
    private function getCreatedLeaveRequest(): array|false
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM leave_requests
            WHERE employee_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->execute([$this->testEmployeeId]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Class under test: EmployeeController
    // Method under test: storeLeaveApplication()
    // Test ID: LEAVE-C01
    // Purpose: Verify successful full-day leave application submission.

    public function testStoreCreatesFullDayLeaveRequest(): void
    {
        $_POST = $this->validLeavePostData();

        $this->invokeStore();

        $record = $this->getCreatedLeaveRequest();

        $this->assertIsArray(
            $record,
            'Leave request was not inserted. Session error: '
            . ($_SESSION['error_message'] ?? 'None')
            . ' | Session success: '
            . ($_SESSION['success_message'] ?? 'None')
        );

        $this->assertSame(
            'Full Day',
            $record['leave_type']
        );

        $this->assertNull($record['half_day_period']);

        $this->assertSame(
            '2095-05-15',
            $record['start_date']
        );

        $this->assertSame(
            $record['start_date'],
            $record['end_date']
        );

        $this->assertSame('pending', $record['status']);

        $this->assertSame(
            'Leave application submitted successfully.',
            $_SESSION['success_message']
        );

        $this->assertSame(
            '/HRIS-Management-System/public/index.php?action=leave-application',
            $this->controller->redirectUrl
        );
    }

    // Class under test: EmployeeController
    // Method under test: storeLeaveApplication()
    // Test ID: LEAVE-C02
    // Purpose: Verify successful first-half leave application submission.

    public function testStoreCreatesFirstHalfLeaveRequest(): void
    {
        $_POST = $this->validLeavePostData([
            'leave_type' => 'Half Day',
            'half_day_period' => 'first_half'
        ]);

        $this->invokeStore();

        $record = $this->getCreatedLeaveRequest();

        $this->assertIsArray(
            $record,
            'Leave request was not inserted. Session error: '
            . ($_SESSION['error_message'] ?? 'None')
            . ' | Session success: '
            . ($_SESSION['success_message'] ?? 'None')
        );

        $this->assertSame('Half Day', $record['leave_type']);
        $this->assertSame('first_half', $record['half_day_period']);
        $this->assertSame('pending', $record['status']);
    }

    // Class under test: EmployeeController
    // Method under test: storeLeaveApplication()
    // Test ID: LEAVE-C03
    // Purpose: Verify successful second-half leave application submission.

    public function testStoreCreatesSecondHalfLeaveRequest(): void
    {
        $_POST = $this->validLeavePostData([
            'leave_type' => 'Half Day',
            'half_day_period' => 'second_half'
        ]);

        $this->invokeStore();

        $record = $this->getCreatedLeaveRequest();

        $this->assertIsArray(
            $record,
            'Leave request was not inserted. Session error: '
            . ($_SESSION['error_message'] ?? 'None')
            . ' | Session success: '
            . ($_SESSION['success_message'] ?? 'None')
        );

        $this->assertSame('Half Day', $record['leave_type']);
        $this->assertSame('second_half', $record['half_day_period']);
        $this->assertSame('pending', $record['status']);
    }

    // Class under test: EmployeeController
    // Method under test: storeLeaveApplication()
    // Test ID: LEAVE-C04
    // Purpose: Verify unsupported leave types are rejected.

    public function testStoreRejectsInvalidLeaveType(): void
    {
        $_POST = $this->validLeavePostData([
            'leave_type' => 'Casual Leave'
        ]);

        $this->invokeStore();

        $this->assertSame(
            'Please select a valid leave type.',
            $_SESSION['error_message']
        );

        $this->assertFalse($this->getCreatedLeaveRequest());

        $this->assertSame(
            '/HRIS-Management-System/public/index.php?action=leave-application',
            $this->controller->redirectUrl
        );
    }

    // Class under test: EmployeeController
    // Method under test: storeLeaveApplication()
    // Test ID: LEAVE-C05
    // Purpose: Verify invalid calendar dates are rejected.

    public function testStoreRejectsInvalidLeaveDate(): void
    {
        $_POST = $this->validLeavePostData([
            'leave_date' => '2095-02-30'
        ]);

        $this->invokeStore();

        $this->assertSame(
            'Please select a valid leave date.',
            $_SESSION['error_message']
        );

        $this->assertFalse($this->getCreatedLeaveRequest());
    }

    // Class under test: EmployeeController
    // Method under test: storeLeaveApplication()
    // Test ID: LEAVE-C06
    // Purpose: Verify a half-day request without a period is rejected.

    public function testStoreRejectsMissingHalfDayPeriod(): void
    {
        $_POST = $this->validLeavePostData([
            'leave_type' => 'Half Day',
            'half_day_period' => ''
        ]);

        $this->invokeStore();

        $this->assertSame(
            'Please select the half-day period.',
            $_SESSION['error_message']
        );

        $this->assertFalse($this->getCreatedLeaveRequest());
    }

    // Class under test: EmployeeController
    // Method under test: storeLeaveApplication()
    // Test ID: LEAVE-C07
    // Purpose: Verify empty leave reasons are rejected.

    public function testStoreRejectsEmptyReason(): void
    {
        $_POST = $this->validLeavePostData([
            'reason' => '   '
        ]);

        $this->invokeStore();

        $this->assertSame(
            'Please provide a reason for your leave.',
            $_SESSION['error_message']
        );

        $this->assertFalse($this->getCreatedLeaveRequest());
    }

    // Class under test: EmployeeController
    // Method under test: storeLeaveApplication()
    // Test ID: LEAVE-C08
    // Purpose: Verify leave submission rejects non-POST requests.

    public function testStoreRejectsNonPostRequest(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->controller->storeLeaveApplication();

        $this->assertSame(405, http_response_code());

        $this->assertSame(
            '',
            $this->controller->redirectUrl
        );

        $this->assertFalse($this->getCreatedLeaveRequest());
    }
}
