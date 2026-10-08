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
        Auth::startSession();

        if (!Auth::check()) {
            $this->redirect('/HRIS-Management-System/public/index.php');
        }

        $this->view('admin/dashboard');
    }
}