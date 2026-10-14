<?php

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../models/Admin.php';

class AdminController extends Controller
{
    private Admin $adminModel;

    public function __construct()
    {
        $this->adminModel = new Admin();
    }


    /*
    |--------------------------------------------------------------------------
    | Administrator Accounts
    |--------------------------------------------------------------------------
    */


    /**
     * Display all administrator accounts.
     */
    public function index(): void
    {
        Middleware::requireAdmin();

        $admins = $this->adminModel->getAll();

        $this->view('admin/administrators/index', [
            'admins' => $admins
        ]);
    }


    /**
     * Show the create administrator form.
     */
    public function create(): void
    {
        Middleware::requireAdmin();

        $this->view('admin/administrators/create');
    }


    /**
     * Store a new administrator account.
     */
    public function store(): void
    {
        Middleware::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=administrators'
            );
        }

        $csrfToken = $_POST['csrf_token'] ?? '';

        if (!Auth::verifyCsrfToken($csrfToken)) {
            $this->view('admin/administrators/create', [
                'error' => 'Invalid security token. Please try again.'
            ]);
            return;
        }


        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $status = $_POST['status'] ?? 'active';


        /*
         * Required fields
         */

        if (
            $username === '' ||
            $email === '' ||
            $fullName === '' ||
            $password === '' ||
            $confirmPassword === ''
        ) {
            $this->view('admin/administrators/create', [
                'error' => 'All required fields must be completed.',
                'old' => $_POST
            ]);
            return;
        }


        /*
         * Email validation
         */

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->view('admin/administrators/create', [
                'error' => 'Please enter a valid email address.',
                'old' => $_POST
            ]);
            return;
        }


        /*
         * Password confirmation
         */

        if ($password !== $confirmPassword) {
            $this->view('admin/administrators/create', [
                'error' => 'Passwords do not match.',
                'old' => $_POST
            ]);
            return;
        }


        /*
         * Password length
         */

        if (strlen($password) < 8) {
            $this->view('admin/administrators/create', [
                'error' => 'Password must be at least 8 characters long.',
                'old' => $_POST
            ]);
            return;
        }


        /*
         * Validate account status
         */

        $allowedStatuses = [
            'active',
            'inactive'
        ];

        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'active';
        }


        /*
         * Unique username
         */

        if ($this->adminModel->findByUsername($username)) {
            $this->view('admin/administrators/create', [
                'error' => 'This username is already in use.',
                'old' => $_POST
            ]);
            return;
        }


        /*
         * Unique email
         */

        if ($this->adminModel->findByEmail($email)) {
            $this->view('admin/administrators/create', [
                'error' => 'This email address is already in use.',
                'old' => $_POST
            ]);
            return;
        }


        /*
         * Create administrator
         */

        try {

            $this->adminModel->create(
                $username,
                $email,
                $fullName,
                $password,
                $status
            );

        } catch (PDOException $e) {

            $this->view('admin/administrators/create', [
                'error' => 'Unable to create the administrator account.',
                'old' => $_POST
            ]);
            return;
        }


        /*
         * Return to administrator list
         */

        $this->redirect(
            '/HRIS-Management-System/public/index.php?action=administrators&success=created'
        );
    }


    /**
     * Show the edit administrator form.
     */
    public function edit(): void
    {
        Middleware::requireAdmin();

        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(404);
            die('Administrator not found.');
        }

        $admin = $this->adminModel->findById($id);

        if (!$admin) {
            http_response_code(404);
            die('Administrator not found.');
        }

        $this->view('admin/administrators/edit', [
            'admin' => $admin
        ]);
    }


    /**
     * Update an administrator account.
     */
    public function update(): void
    {
        Middleware::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=administrators'
            );
        }

        $csrfToken = $_POST['csrf_token'] ?? '';

        if (!Auth::verifyCsrfToken($csrfToken)) {
            $this->view('admin/administrators/edit', [
                'error' => 'Invalid security token. Please try again.'
            ]);
            return;
        }


        $id = (int) ($_POST['id'] ?? 0);

        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $status = $_POST['status'] ?? 'active';


        if ($id <= 0) {
            http_response_code(404);
            die('Administrator not found.');
        }


        $admin = $this->adminModel->findById($id);

        if (!$admin) {
            http_response_code(404);
            die('Administrator not found.');
        }


        /*
         * Required fields
         */

        if (
            $username === '' ||
            $email === '' ||
            $fullName === ''
        ) {
            $this->view('admin/administrators/edit', [
                'error' => 'All required fields must be completed.',
                'admin' => array_merge(
                    $admin,
                    $_POST
                )
            ]);
            return;
        }


        /*
         * Email validation
         */

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->view('admin/administrators/edit', [
                'error' => 'Please enter a valid email address.',
                'admin' => array_merge(
                    $admin,
                    $_POST
                )
            ]);
            return;
        }


        /*
         * Validate status
         */

        $allowedStatuses = [
            'active',
            'inactive'
        ];

        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'active';
        }


        /*
         * Unique username
         */

        $existingUsername =
            $this->adminModel->findByUsername($username);

        if (
            $existingUsername &&
            (int) $existingUsername['id'] !== $id
        ) {
            $this->view('admin/administrators/edit', [
                'error' => 'This username is already in use.',
                'admin' => array_merge(
                    $admin,
                    $_POST
                )
            ]);
            return;
        }


        /*
         * Unique email
         */

        $existingEmail =
            $this->adminModel->findByEmail($email);

        if (
            $existingEmail &&
            (int) $existingEmail['id'] !== $id
        ) {
            $this->view('admin/administrators/edit', [
                'error' => 'This email address is already in use.',
                'admin' => array_merge(
                    $admin,
                    $_POST
                )
            ]);
            return;
        }


        /*
         * Update administrator
         */

        /*
         * Optional password update
         */

        $newPassword = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($newPassword !== '') {

            if ($newPassword !== $confirmPassword) {

                $this->view('admin/administrators/edit', [
                    'error' => 'New passwords do not match.',
                    'admin' => array_merge(
                        $admin,
                        $_POST
                    )
                ]);
                return;
            }

            if (strlen($newPassword) < 8) {

                $this->view('admin/administrators/edit', [
                    'error' => 'New password must be at least 8 characters long.',
                    'admin' => array_merge(
                        $admin,
                        $_POST
                    )
                ]);
                return;
            }
        }


        /*
         * Update administrator account information
         */

        try {

            $this->adminModel->update(
                $id,
                $username,
                $email,
                $fullName,
                $status
            );

        } catch (PDOException $e) {

            $this->view('admin/administrators/edit', [
                'error' => 'Unable to update the administrator account.',
                'admin' => array_merge(
                    $admin,
                    $_POST
                )
            ]);
            return;
        }


        /*
         * Update password after account update
         */

        if ($newPassword !== '') {

            $this->adminModel->updatePassword(
                $id,
                $newPassword
            );
        }


        $this->redirect(
            '/HRIS-Management-System/public/index.php?action=administrators&success=updated'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Administrator Profile
    |--------------------------------------------------------------------------
    */


    /**
     * Display the currently logged-in administrator's profile.
     */
    public function profile(): void
    {
        Middleware::requireAdmin();

        $adminId = Auth::userId();

        if (!$adminId) {
            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=login'
            );
        }

        $admin = $this->adminModel->findById($adminId);

        if (!$admin) {
            http_response_code(404);
            die('Administrator profile not found.');
        }

        $this->view('admin/administrators/profile', [
            'admin' => $admin
        ]);
    }


    /**
     * Update the currently logged-in administrator's profile.
     */
    public function updateProfile(): void
    {
        Middleware::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=profile'
            );
        }

        $csrfToken = $_POST['csrf_token'] ?? '';

        if (!Auth::verifyCsrfToken($csrfToken)) {
            $this->view('admin/administrators/profile', [
                'error' => 'Invalid security token. Please try again.'
            ]);
            return;
        }

        $adminId = Auth::userId();

        if (!$adminId) {
            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=login'
            );
        }


        $admin = $this->adminModel->findById($adminId);

        if (!$admin) {
            http_response_code(404);
            die('Administrator profile not found.');
        }


        $email = trim($_POST['email'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');


        if ($email === '' || $fullName === '') {
            $this->view('admin/administrators/profile', [
                'error' => 'Full name and email are required.',
                'admin' => array_merge(
                    $admin,
                    $_POST
                )
            ]);
            return;
        }


        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->view('admin/administrators/profile', [
                'error' => 'Please enter a valid email address.',
                'admin' => array_merge(
                    $admin,
                    $_POST
                )
            ]);
            return;
        }


        /*
         * Check email uniqueness
         */

        $existingEmail =
            $this->adminModel->findByEmail($email);

        if (
            $existingEmail &&
            (int) $existingEmail['id'] !== $adminId
        ) {
            $this->view('admin/administrators/profile', [
                'error' => 'This email address is already in use.',
                'admin' => array_merge(
                    $admin,
                    $_POST
                )
            ]);
            return;
        }


        /*
         * Optional password change
         */

        $newPassword = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($newPassword !== '') {

            if ($newPassword !== $confirmPassword) {

                $this->view('admin/administrators/profile', [
                    'error' => 'New passwords do not match.',
                    'admin' => array_merge(
                        $admin,
                        $_POST
                    )
                ]);
                return;
            }

            if (strlen($newPassword) < 8) {

                $this->view('admin/administrators/profile', [
                    'error' => 'New password must be at least 8 characters long.',
                    'admin' => array_merge(
                        $admin,
                        $_POST
                    )
                ]);
                return;
            }
        }


        /*
         * Keep the current username and status.
         */

        try {

            $this->adminModel->update(
                $adminId,
                $admin['username'],
                $email,
                $fullName,
                $admin['status']
            );

        } catch (PDOException $e) {

            $this->view('admin/administrators/profile', [
                'error' => 'Unable to update your profile.',
                'admin' => array_merge(
                    $admin,
                    $_POST
                )
            ]);
            return;
        }


        /*
         * Update password after profile update.
         */

        if ($newPassword !== '') {

            $passwordUpdated = $this->adminModel->updatePassword(
                $adminId,
                $newPassword
            );

            if (!$passwordUpdated) {

                $this->view('admin/administrators/profile', [
                    'error' => 'Your profile was updated, but the password could not be changed.',
                    'admin' => array_merge(
                        $admin,
                        $_POST
                    )
                ]);
                return;
            }
        }


        /*
         * Refresh session information.
         */

        $_SESSION['full_name'] = $fullName;


        $this->redirect(
            '/HRIS-Management-System/public/index.php?action=profile&success=updated'
        );


        /*
         * Refresh session information.
         */

        $_SESSION['full_name'] = $fullName;


        $this->redirect(
            '/HRIS-Management-System/public/index.php?action=profile&success=updated'
        );
    }
}