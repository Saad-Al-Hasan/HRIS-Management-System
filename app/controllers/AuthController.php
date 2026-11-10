<?php

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Employee.php';

class AuthController extends Controller
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    public function showLogin(): void
    {
        Auth::startSession();

        if (Auth::check()) {
            $this->redirect('/HRIS-Management-System/public/index.php');
        }

        $this->view('auth/login');
    }

    public function login(): void
    {
        Auth::startSession();

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $csrfToken = $_POST['csrf_token'] ?? '';

        if (!Auth::verifyCsrfToken($csrfToken)) {
            $this->view('auth/login', [
                'error' => 'Invalid security token. Please try again.'
            ]);
            return;
        }

        if ($username === '' || $password === '') {
            $this->view('auth/login', [
                'error' => 'Username and password are required.'
            ]);
            return;
        }

        $user = $this->userModel->findByUsername($username);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->view('auth/login', [
                'error' => 'Invalid username or password.'
            ]);
            return;
        }

        if ($user['status'] !== 'active') {
            $this->view('auth/login', [
                'error' => 'This account is inactive.'
            ]);
            return;
        }

        Auth::login($user);

        if ((int) $user['role_id'] === 1) {
            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=dashboard'
            );
        } elseif ((int) $user['role_id'] === 2) {
            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=employee-dashboard'
            );
        } else {
            Auth::logout();

            http_response_code(403);
            die('403 - Access Denied');
        }
    }

    public function logout(): void
    {
        Auth::logout();

        $this->redirect('/HRIS-Management-System/public/index.php');
    }

    public function dashboard(): void
    {
        Middleware::requireLogin();

        $roleId = Auth::roleId();

        if ($roleId === 1) {
            $employeeModel = new Employee();
            $allEmployees = $employeeModel->getAll();

            $totalEmployees = count(array_filter(
                $allEmployees,
                static fn(array $employee): bool =>
                    ($employee['status'] ?? '') === 'active'
            ));

            require_once __DIR__ . '/../models/Attendance.php';

            $attendanceModel = new Attendance();
            $today = date('Y-m-d');

            $todayActivity = $attendanceModel->getTodayActivity($today, 500);

            $presentToday = $attendanceModel->countTodayByStatus($today, 'present');
            $lateToday = $attendanceModel->countTodayByStatus($today, 'late');

            $employeesWithEntryToday = $attendanceModel->countEmployeesWithEntryToday($today);

            $absentToday = max(0, $totalEmployees - $employeesWithEntryToday);

            $this->view('admin/dashboard', [
                'totalEmployees' => $totalEmployees,
                'presentToday' => $presentToday,
                'lateToday' => $lateToday,
                'absentToday' => $absentToday,
                'todayActivity' => $todayActivity,
            ]);

            return;
        }

        if ($roleId === 2) {
            $userId = Auth::userId();

            $employeeModel = new Employee();
            $employee = $userId !== null
                ? $employeeModel->findByUserId($userId)
                : null;

            $this->view('employee/dashboard', [
                'employee' => $employee,
            ]);

            return;
        }

        http_response_code(403);
        die('403 - Access Denied');
    }


    /**
     * Return live dashboard statistics as JSON.
     */
    public function dashboardStats(): void
    {
        Middleware::requireLogin();

        if (Auth::roleId() !== 1) {
            http_response_code(403);
            exit;
        }

        $employeeModel = new Employee();
        $allEmployees = $employeeModel->getAll();

        $totalEmployees = count(array_filter(
            $allEmployees,
            static fn(array $employee): bool =>
                ($employee['status'] ?? '') === 'active'
        ));

        require_once __DIR__ . '/../models/Attendance.php';

        $attendanceModel = new Attendance();
        $today = date('Y-m-d');

        $presentToday = $attendanceModel->countTodayByStatus(
            $today,
            'present'
        );

        $lateToday = $attendanceModel->countTodayByStatus(
            $today,
            'late'
        );

        $employeesWithEntryToday =
            $attendanceModel->countEmployeesWithEntryToday($today);

        $absentToday = max(
            0,
            $totalEmployees - $employeesWithEntryToday
        );

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        echo json_encode([
            'totalEmployees' => $totalEmployees,
            'presentToday' => $presentToday,
            'lateToday' => $lateToday,
            'absentToday' => $absentToday
        ]);

        exit;
    }
}