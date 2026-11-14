<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../models/Employee.php';
require_once __DIR__ . '/../models/LeaveRequest.php';

class LeaveController extends Controller
{
    private const EMPLOYEE_PAGE = '/HRIS-Management-System/public/index.php?action=leave-application';
    private const ADMIN_PAGE = '/HRIS-Management-System/public/index.php?action=leave-management';
    private const MAX_REASON_LENGTH = 500;

    private Employee $employeeModel;
    private LeaveRequest $leaveModel;

    public function __construct()
    {
        $this->employeeModel = new Employee();
        $this->leaveModel = new LeaveRequest();
    }

    /* =====================================================
     * EMPLOYEE: Leave Application page
     * ===================================================== */

    public function employeeIndex(): void
    {
        Middleware::requireEmployee();

        $employee = $this->currentEmployee();

        $leaveRequests = [];

        if ($employee !== null) {
            $leaveRequests = $this->leaveModel->findByEmployeeId(
                (int) $employee['employee_id']
            );
        }

        $data = [
            'pageTitle' => 'Leave Application',
            'employee' => $employee,
            'leaveRequests' => $leaveRequests,
            'success' => $_SESSION['leave_success'] ?? null,
            'error' => $_SESSION['leave_error'] ?? null,
            'old' => $_SESSION['leave_old'] ?? [],
            'csrfToken' => Auth::csrfToken(),
        ];

        unset(
            $_SESSION['leave_success'],
            $_SESSION['leave_error'],
            $_SESSION['leave_old']
        );

        $this->view('employee/leave/index', $data);
    }

    /* =====================================================
     * EMPLOYEE: Submit a leave request
     * ===================================================== */

    public function employeeStore(): void
    {
        Middleware::requireEmployee();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Method not allowed.');
        }

        $this->verifyCsrf();

        $employee = $this->currentEmployee();

        if ($employee === null) {
            $_SESSION['leave_error'] = 'Your employee record could not be found.';
            $this->redirect(self::EMPLOYEE_PAGE);
            return;
        }

        $employeeId = (int) $employee['employee_id'];

        $leaveDate = trim((string) ($_POST['leave_date'] ?? ''));
        $leaveType = trim((string) ($_POST['leave_type'] ?? ''));
        $reason = trim((string) ($_POST['reason'] ?? ''));

        $_SESSION['leave_old'] = [
            'leave_date' => $leaveDate,
            'leave_type' => $leaveType,
            'reason' => $reason,
        ];

        $error = $this->validateRequest($employeeId, $leaveDate, $leaveType, $reason);

        if ($error !== null) {
            $_SESSION['leave_error'] = $error;
            $this->redirect(self::EMPLOYEE_PAGE);
            return;
        }

        try {
            $this->leaveModel->create($employeeId, $leaveDate, $leaveType, $reason);
        } catch (Throwable $exception) {
            $_SESSION['leave_error'] = 'Unable to submit your leave request. Please try again.';
            $this->redirect(self::EMPLOYEE_PAGE);
            return;
        }

        unset($_SESSION['leave_old']);
        $_SESSION['leave_success'] = 'Your leave request has been submitted and is pending approval.';

        $this->redirect(self::EMPLOYEE_PAGE);
    }

    /* =====================================================
     * ADMIN: Leave Management page
     * ===================================================== */

    public function adminIndex(): void
    {
        Middleware::requireAdmin();

        $data = [
            'pageTitle' => 'Leave Management',
            'leaveRequests' => $this->leaveModel->getAll(),
            'success' => $_SESSION['leave_success'] ?? null,
            'error' => $_SESSION['leave_error'] ?? null,
            'csrfToken' => Auth::csrfToken(),
        ];

        unset($_SESSION['leave_success'], $_SESSION['leave_error']);

        $this->view('admin/leave/index', $data);
    }

    /* =====================================================
     * ADMIN: Accept / Reject a leave request
     * ===================================================== */

    public function adminUpdateStatus(): void
    {
        Middleware::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Method not allowed.');
        }

        $this->verifyCsrf();

        $leaveId = (int) ($_POST['leave_id'] ?? 0);
        $decision = trim((string) ($_POST['decision'] ?? ''));

        $redirectUrl = self::ADMIN_PAGE;

        $newStatus = match ($decision) {
            'accept' => LeaveRequest::STATUS_APPROVED,
            'reject' => LeaveRequest::STATUS_REJECTED,
            default => null,
        };

        if ($leaveId <= 0 || $newStatus === null) {
            $_SESSION['leave_error'] = 'Invalid leave request action.';
            $this->redirect($redirectUrl);
            return;
        }

        $reviewerId = Auth::userId();

        if ($reviewerId === null) {
            $this->redirect('/HRIS-Management-System/public/index.php');
            return;
        }

        try {
            $updated = $this->leaveModel->updateStatus($leaveId, $newStatus, $reviewerId);
        } catch (Throwable $exception) {
            $_SESSION['leave_error'] = 'Unable to update the leave request. Please try again.';
            $this->redirect($redirectUrl);
            return;
        }

        if ($updated) {
            $_SESSION['leave_success'] = $newStatus === LeaveRequest::STATUS_APPROVED
                ? 'Leave request accepted.'
                : 'Leave request rejected.';
        } else {
            $_SESSION['leave_error'] = 'This leave request was not found or has already been reviewed.';
        }

        $this->redirect($redirectUrl);
    }

    /* =====================================================
     * Helpers
     * ===================================================== */

    private function currentEmployee(): ?array
    {
        $userId = Auth::userId();

        if ($userId === null) {
            return null;
        }

        $employee = $this->employeeModel->findByUserId($userId);

        if ($employee === null || empty($employee['employee_id'])) {
            return null;
        }

        return $employee;
    }

    private function verifyCsrf(): void
    {
        if (!Auth::verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
            http_response_code(403);
            exit('Invalid security token. Please refresh and try again.');
        }
    }

    private function validateRequest(
        int $employeeId,
        string $leaveDate,
        string $leaveType,
        string $reason
    ): ?string {
        $date = DateTime::createFromFormat('Y-m-d', $leaveDate);

        if ($date === false || $date->format('Y-m-d') !== $leaveDate) {
            return 'Please select a valid leave date.';
        }

        if ($leaveDate < date('Y-m-d')) {
            return 'Leave date cannot be in the past.';
        }

        if (
            !in_array($leaveType, [
                LeaveRequest::TYPE_FULL_DAY,
                LeaveRequest::TYPE_HALF_DAY,
            ], true)
        ) {
            return 'Please select a valid leave type.';
        }

        if ($reason === '') {
            return 'Reason for leave is required.';
        }

        if (mb_strlen($reason) > self::MAX_REASON_LENGTH) {
            return 'Reason for leave must not exceed ' . self::MAX_REASON_LENGTH . ' characters.';
        }

        if ($this->leaveModel->existsForDate($employeeId, $leaveDate)) {
            return 'You already have a pending or approved leave request for this date.';
        }

        return null;
    }
}
