<?php

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../models/User.php';

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

        $this->redirect('/HRIS-Management-System/public/index.php');
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
            $this->view('admin/dashboard');
            return;
        }

        if ($roleId === 2) {
            $this->view('employee/dashboard');
            return;
        }

        http_response_code(403);
        die('403 - Access Denied');
    }
}