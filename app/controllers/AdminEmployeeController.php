<?php

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../models/Employee.php';

class AdminEmployeeController extends Controller
{
    private Employee $employeeModel;

    public function __construct()
    {
        $this->employeeModel = new Employee();
    }

    public function index(): void
    {
        Middleware::requireAdmin();

        $this->view('admin/employees/index', [
            'pageTitle' => 'Employee Management',
            'employees' => $this->employeeModel->getAll()
        ]);
    }

    public function viewDetails(): void
    {
        Middleware::requireAdmin();

        $employeeId = filter_input(
            INPUT_GET,
            'id',
            FILTER_VALIDATE_INT
        );

        if (!$employeeId || $employeeId < 1) {
            http_response_code(400);
            echo '400 - Invalid Employee ID';
            return;
        }

        $employee = $this->employeeModel->findByEmployeeId($employeeId);

        if ($employee === null) {
            http_response_code(404);
            echo '404 - Employee Not Found';
            return;
        }

        $this->view('admin/employees/view', [
            'pageTitle' => 'Employee Details',
            'employee' => $employee
        ]);
    }

    public function edit(): void
    {
        Middleware::requireAdmin();

        $employeeId = filter_input(
            INPUT_GET,
            'id',
            FILTER_VALIDATE_INT
        );

        if (!$employeeId || $employeeId < 1) {
            http_response_code(400);
            echo '400 - Invalid Employee ID';
            return;
        }

        $employee = $this->employeeModel->findByEmployeeId($employeeId);

        if ($employee === null) {
            http_response_code(404);
            echo '404 - Employee Not Found';
            return;
        }

        $this->view('admin/employees/edit', [
            'pageTitle' => 'Edit Employee',
            'employee' => $employee,
            'errors' => [],
            'old' => []
        ]);
    }

    public function update(): void
    {
        Middleware::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo '405 - Method Not Allowed';
            return;
        }

        if (!Auth::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo '403 - Invalid security token. Please refresh and try again.';
            return;
        }

        $employeeId = filter_var(
            $_POST['employee_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$employeeId || $employeeId < 1) {
            http_response_code(400);
            echo '400 - Invalid Employee ID';
            return;
        }

        $employee = $this->employeeModel->findByEmployeeId($employeeId);

        if ($employee === null) {
            http_response_code(404);
            echo '404 - Employee Not Found';
            return;
        }

        $old = [
            'full_name' => trim($_POST['full_name'] ?? ''),
            'username' => trim($_POST['username'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'employee_code' => trim($_POST['employee_code'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'department' => trim($_POST['department'] ?? ''),
            'designation' => trim($_POST['designation'] ?? ''),
            'joining_date' => trim($_POST['joining_date'] ?? ''),
        ];

        $errors = [];
        $newPassword = $_POST['new_password'] ?? '';
        $confirmNewPassword = $_POST['confirm_new_password'] ?? '';

        if ($newPassword !== '' || $confirmNewPassword !== '') {
            if ($newPassword === '') {
                $errors['new_password'] = 'Enter a new password.';
            } elseif (strlen($newPassword) < 8) {
                $errors['new_password'] = 'Password must be at least 8 characters.';
            }

            if ($confirmNewPassword === '') {
                $errors['confirm_new_password'] = 'Please confirm the new password.';
            } elseif ($newPassword !== $confirmNewPassword) {
                $errors['confirm_new_password'] = 'Passwords do not match.';
            }
        }

        foreach (
            [
                'full_name',
                'username',
                'email',
                'employee_code',
                'department',
                'designation',
                'joining_date'
            ] as $field
        ) {
            if ($old[$field] === '') {
                $errors[$field] = 'This field is required.';
            }
        }

        if (
            $old['email'] !== '' &&
            !filter_var($old['email'], FILTER_VALIDATE_EMAIL)
        ) {
            $errors['email'] = 'Enter a valid email address.';
        }

        if (!preg_match('/^[A-Za-z0-9._-]+$/', $old['username'])) {
            $errors['username'] =
                'Use only letters, numbers, dots, underscores, and hyphens.';
        }

        if (
            $old['username'] !== $employee['username'] &&
            $this->employeeModel->usernameExists($old['username'])
        ) {
            $errors['username'] = 'This username is already in use.';
        }

        if (
            $old['email'] !== '' &&
            filter_var($old['email'], FILTER_VALIDATE_EMAIL) &&
            strcasecmp($old['email'], $employee['email']) !== 0 &&
            $this->employeeModel->emailExists($old['email'])
        ) {
            $errors['email'] = 'This email address is already in use.';
        }

        if (
            $old['employee_code'] !== $employee['employee_code'] &&
            $this->employeeModel->employeeCodeExists($old['employee_code'])
        ) {
            $errors['employee_code'] = 'This employee code is already in use.';
        }

        $date = DateTime::createFromFormat('!Y-m-d', $old['joining_date']);

        if (
            $old['joining_date'] !== '' &&
            (!$date || $date->format('Y-m-d') !== $old['joining_date'])
        ) {
            $errors['joining_date'] = 'Enter a valid joining date.';
        }

        if (!empty($errors)) {
            $this->view('admin/employees/edit', [
                'pageTitle' => 'Edit Employee',
                'employee' => $employee,
                'errors' => $errors,
                'old' => $old
            ]);
            return;
        }

        try {
            $this->employeeModel->update(
                (int) $employee['user_id'],
                [
                    'full_name' => $old['full_name'],
                    'username' => $old['username'],
                    'email' => $old['email'],
                    'password' => $newPassword,
                ],
                [
                    'employee_code' => $old['employee_code'],
                    'phone' => $old['phone'],
                    'address' => $old['address'],
                    'department' => $old['department'],
                    'designation' => $old['designation'],
                    'joining_date' => $old['joining_date'],
                ]
            );

            $_SESSION['success_message'] = 'Employee updated successfully.';

            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=employees'
            );
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errors['general'] =
                    'A username, email, or employee code may already exist.';
            } else {
                error_log('Employee update failed: ' . $e->getMessage());
                $errors['general'] =
                    'Unable to update the employee. Please try again.';
            }

            $this->view('admin/employees/edit', [
                'pageTitle' => 'Edit Employee',
                'employee' => $employee,
                'errors' => $errors,
                'old' => $old
            ]);
        }
    }

    public function create(): void
    {
        Middleware::requireAdmin();

        $this->view('admin/employees/create', [
            'pageTitle' => 'Add Employee',
            'errors' => [],
            'old' => [],
            'nextEmployeeCode' => $this->employeeModel->getNextEmployeeCode()
        ]);
    }

    public function store(): void
    {
        Middleware::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo '405 - Method Not Allowed';
            return;
        }

        if (!Auth::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo '403 - Invalid security token. Please refresh and try again.';
            return;
        }

        $old = [
            'full_name' => trim($_POST['full_name'] ?? ''),
            'username' => trim($_POST['username'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'employee_code' => trim($_POST['employee_code'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'department' => trim($_POST['department'] ?? ''),
            'designation' => trim($_POST['designation'] ?? ''),
            'joining_date' => trim($_POST['joining_date'] ?? ''),
        ];

        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $errors = [];

        foreach (
            [
                'full_name',
                'username',
                'email',
                'employee_code',
                'department',
                'designation',
                'joining_date'
            ] as $field
        ) {
            if ($old[$field] === '') {
                $errors[$field] = 'This field is required.';
            }
        }

        if (
            $old['email'] !== '' &&
            !filter_var($old['email'], FILTER_VALIDATE_EMAIL)
        ) {
            $errors['email'] = 'Enter a valid email address.';
        }

        if ($password === '') {
            $errors['password'] = 'Password is required.';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }

        if ($password !== $confirmPassword) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        if (
            $old['username'] !== '' &&
            $this->employeeModel->usernameExists($old['username'])
        ) {
            $errors['username'] = 'This username is already in use.';
        }

        if (
            $old['email'] !== '' &&
            filter_var($old['email'], FILTER_VALIDATE_EMAIL) &&
            $this->employeeModel->emailExists($old['email'])
        ) {
            $errors['email'] = 'This email address is already in use.';
        }

        if (!preg_match('/^[A-Za-z0-9._-]+$/', $old['username'])) {
            $errors['username'] =
                'Use only letters, numbers, dots, underscores, and hyphens.';
        }

        if (
            $old['employee_code'] !== '' &&
            $this->employeeModel->employeeCodeExists($old['employee_code'])
        ) {
            $errors['employee_code'] = 'This employee code is already in use.';
        }

        $date = DateTime::createFromFormat('!Y-m-d', $old['joining_date']);

        if (
            $old['joining_date'] !== '' &&
            (!$date || $date->format('Y-m-d') !== $old['joining_date'])
        ) {
            $errors['joining_date'] = 'Enter a valid joining date.';
        }

        if (!empty($errors)) {
            $this->view('admin/employees/create', [
                'pageTitle' => 'Add Employee',
                'errors' => $errors,
                'old' => $old
            ]);
            return;
        }

        try {
            $this->employeeModel->create(
                [
                    'full_name' => $old['full_name'],
                    'username' => $old['username'],
                    'email' => $old['email'],
                    'password' => $password,
                    'status' => 'active',
                ],
                [
                    'employee_code' => $old['employee_code'],
                    'phone' => $old['phone'],
                    'address' => $old['address'],
                    'department' => $old['department'],
                    'designation' => $old['designation'],
                    'joining_date' => $old['joining_date'],
                ]
            );

            $_SESSION['success_message'] = 'Employee created successfully.';

            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=employees'
            );
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errors['general'] =
                    'A username, email, or employee code may already exist.';
            } else {
                error_log('Employee creation failed: ' . $e->getMessage());
                $errors['general'] =
                    'Unable to create the employee. Please try again.';
            }

            $this->view('admin/employees/create', [
                'pageTitle' => 'Add Employee',
                'errors' => $errors,
                'old' => $old
            ]);
        }
    }

    public function toggleStatus(): void
    {
        Middleware::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo '405 - Method Not Allowed';
            return;
        }

        if (!Auth::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo '403 - Invalid security token. Please refresh and try again.';
            return;
        }

        $employeeId = filter_var(
            $_POST['employee_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $newStatus = $_POST['new_status'] ?? '';

        if (
            !$employeeId ||
            $employeeId < 1 ||
            !in_array($newStatus, ['active', 'inactive'], true)
        ) {
            http_response_code(400);
            echo '400 - Invalid employee status request';
            return;
        }

        $employee = $this->employeeModel->findByEmployeeId($employeeId);

        if ($employee === null) {
            http_response_code(404);
            echo '404 - Employee Not Found';
            return;
        }

        if ($employee['status'] === $newStatus) {
            $_SESSION['success_message'] = 'Employee status is already up to date.';
        } else {
            $this->employeeModel->updateStatus($employeeId, $newStatus);

            $_SESSION['success_message'] = $newStatus === 'active'
                ? 'Employee activated successfully.'
                : 'Employee deactivated successfully.';
        }

        $this->redirect(
            '/HRIS-Management-System/public/index.php?action=employees'
        );
    }
}