<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/controllers/AdminController.php';

class LeaveReviewRedirectException extends RuntimeException
{
}

class TestableLeaveReviewController extends AdminController
{
    public string $redirectUrl = '';

    protected function view(string $view, array $data = []): void
    {
        // Capture view calls if needed.
    }

    protected function redirect(string $url): void
    {
        $this->redirectUrl = $url;
        throw new LeaveReviewRedirectException($url);
    }
}

class LeaveReviewControllerTest extends TestCase
{
    private ?PDO $pdo = null;
    private TestableLeaveReviewController $controller;

    private int $adminUserId;
    private int $employeeUserId;
    private int $employeeId;
    private int $leaveRequestId;

    private string $csrfToken = 'leave-review-test-token';

    protected function setUp(): void
    {
        $database = new Database();
        $this->pdo = $database->connect();

        $databaseName = $this->pdo
            ->query('SELECT DATABASE()')
            ->fetchColumn();

        $this->assertSame(
            'hris_management_system_test',
            $databaseName,
            'Leave review tests must use the test database only.'
        );

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Create temporary administrator.
        $unique = bin2hex(random_bytes(5));

        $stmt = $this->pdo->prepare("
            INSERT INTO users
                (role_id, username, email, password, full_name)
            VALUES
                (1, ?, ?, 'test_hash', 'Leave Test Admin')
        ");

        $stmt->execute([
            'LEAVE_ADMIN_' . $unique,
            'leave_admin_' . $unique . '@test.local'
        ]);

        $this->adminUserId = (int) $this->pdo->lastInsertId();

        // Create temporary employee user.
        $stmt = $this->pdo->prepare("
            INSERT INTO users
                (role_id, username, email, password, full_name)
            VALUES
                (2, ?, ?, 'test_hash', 'Leave Review Employee')
        ");

        $stmt->execute([
            'LEAVE_EMP_' . $unique,
            'leave_emp_' . $unique . '@test.local'
        ]);

        $this->employeeUserId = (int) $this->pdo->lastInsertId();

        // Create employee profile.
        $stmt = $this->pdo->prepare("
            INSERT INTO employees
                (user_id, employee_code, department)
            VALUES (?, ?, ?)
        ");

        $stmt->execute([
            $this->employeeUserId,
            'LEAVE_EMP_' . $unique,
            'LEAVE_TEST'
        ]);

        $this->employeeId = (int) $this->pdo->lastInsertId();

        // Create a pending leave request.
        $stmt = $this->pdo->prepare("
            INSERT INTO leave_requests
                (employee_id, leave_type, half_day_period,
                 start_date, end_date, reason, status)
            VALUES (?, 'Full Day', NULL, '2095-05-15',
                    '2095-05-15', 'Leave review test', 'pending')
        ");

        $stmt->execute([$this->employeeId]);

        $this->leaveRequestId = (int) $this->pdo->lastInsertId();

        // Simulate administrator authentication.
        $_SESSION = [
            'user_id' => $this->adminUserId,
            'role_id' => 1,
            'csrf_token' => $this->csrfToken
        ];

        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';

        http_response_code(200);

        $this->controller = new TestableLeaveReviewController();
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null && isset($this->employeeId)) {
            $stmt = $this->pdo->prepare("
                DELETE FROM leave_requests WHERE employee_id = ?
            ");
            $stmt->execute([$this->employeeId]);

            $stmt = $this->pdo->prepare("
                DELETE FROM employees WHERE id = ?
            ");
            $stmt->execute([$this->employeeId]);

            $stmt = $this->pdo->prepare("
                DELETE FROM users WHERE id IN (?, ?)
            ");
            $stmt->execute([
                $this->adminUserId,
                $this->employeeUserId
            ]);
        }

        unset($this->controller);

        $_GET = [];
        $_POST = [];
        $_SESSION = [];

        http_response_code(200);

        $this->pdo = null;
        gc_collect_cycles();
    }

    private function submitReview(int|string $requestId, string $status): void
    {
        $_POST = [
            'csrf_token' => $this->csrfToken,
            'request_id' => $requestId,
            'status' => $status
        ];

        try {
            $this->controller->reviewLeaveRequest();
        } catch (LeaveReviewRedirectException $e) {
            // Capture the redirect without terminating PHPUnit.
        }
    }

    private function getLeaveRequest(): array|false
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM leave_requests WHERE id = ?
        ");

        $stmt->execute([$this->leaveRequestId]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // LEAVE-C09: Admin approves a pending leave request.
    public function testAdminApprovesPendingLeaveRequest(): void
    {
        $this->submitReview($this->leaveRequestId, 'approved');

        $record = $this->getLeaveRequest();

        $this->assertIsArray($record);
        $this->assertSame('approved', $record['status']);
        $this->assertSame(
            $this->adminUserId,
            (int) $record['reviewed_by']
        );
        $this->assertNotNull($record['reviewed_at']);

        $this->assertSame(
            'Leave request accepted successfully.',
            $_SESSION['success_message']
        );
    }

    // LEAVE-C10: Admin rejects a pending leave request.
    public function testAdminRejectsPendingLeaveRequest(): void
    {
        $this->submitReview($this->leaveRequestId, 'rejected');

        $record = $this->getLeaveRequest();

        $this->assertIsArray($record);
        $this->assertSame('rejected', $record['status']);
        $this->assertSame(
            $this->adminUserId,
            (int) $record['reviewed_by']
        );
        $this->assertNotNull($record['reviewed_at']);

        $this->assertSame(
            'Leave request rejected successfully.',
            $_SESSION['success_message']
        );
    }

    // LEAVE-C11: Invalid leave request ID.
    public function testRejectsInvalidLeaveRequestId(): void
    {
        $this->submitReview('invalid-id', 'approved');

        $this->assertSame(
            'Invalid leave review request.',
            $_SESSION['error_message']
        );

        $this->assertSame(
            'pending',
            $this->getLeaveRequest()['status']
        );
    }

    // LEAVE-C12: Invalid review status.
    public function testRejectsInvalidReviewStatus(): void
    {
        $this->submitReview($this->leaveRequestId, 'cancelled');

        $this->assertSame(
            'Invalid leave review request.',
            $_SESSION['error_message']
        );

        $this->assertSame(
            'pending',
            $this->getLeaveRequest()['status']
        );
    }

    // LEAVE-C13: Already-reviewed request.
    public function testAlreadyReviewedRequestDoesNotChangeStatus(): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE leave_requests
            SET status = 'approved', reviewed_by = ?, reviewed_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $this->adminUserId,
            $this->leaveRequestId
        ]);

        $this->submitReview($this->leaveRequestId, 'rejected');

        $record = $this->getLeaveRequest();

        // The model updates only records whose status is pending.
        $this->assertSame('approved', $record['status']);

        // Current model returns execute(), so the controller may
        // still display a success message despite no row being changed.
        $this->assertSame(
            'Leave request rejected successfully.',
            $_SESSION['success_message']
        );
    }

    // LEAVE-C14: Non-POST requests are rejected.
    public function testRejectsNonPostReviewRequest(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->controller->reviewLeaveRequest();

        $this->assertSame(405, http_response_code());

        $this->assertSame(
            'pending',
            $this->getLeaveRequest()['status']
        );
    }
}
