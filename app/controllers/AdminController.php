<?php

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../models/Admin.php';
require_once __DIR__ . '/../models/LeaveRequest.php';
require_once __DIR__ . '/../models/Employee.php';
require_once __DIR__ . '/../models/Salary.php';
require_once __DIR__ . '/../models/AuditLog.php';

class AdminController extends Controller
{
    private Admin $adminModel;
    private LeaveRequest $leaveRequestModel;
    private Employee $employeeModel;
    private Salary $salaryModel;
    private AuditLog $auditLogModel;

    public function __construct()
    {
        $this->adminModel = new Admin();
        $this->leaveRequestModel = new LeaveRequest();
        $this->employeeModel = new Employee();
        $this->salaryModel = new Salary();
        $this->auditLogModel = new AuditLog();
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

        /*
         * Optional profile picture upload.
         */
        $profilePicture = $_FILES['profile_picture'] ?? null;

        if (
            $profilePicture !== null &&
            $profilePicture['error'] !== UPLOAD_ERR_NO_FILE
        ) {
            if ($profilePicture['error'] !== UPLOAD_ERR_OK) {
                $this->view('admin/administrators/profile', [
                    'error' => 'The profile picture upload failed. Please try again.',
                    'admin' => $admin
                ]);
                return;
            }

            // Maximum upload size: 1 MB.
            if ($profilePicture['size'] > 1024 * 1024) {
                $this->view('admin/administrators/profile', [
                    'error' => 'The profile picture must be 1 MB or smaller.',
                    'admin' => $admin
                ]);
                return;
            }

            // Verify the actual image type, not just the filename.
            $imageInfo = @getimagesize($profilePicture['tmp_name']);

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($profilePicture['tmp_name']);

            $allowedTypes = [
                'image/png' => 'png',
                'image/jpeg' => 'jpg'
            ];

            if (
                $imageInfo === false ||
                !isset($allowedTypes[$mimeType]) ||
                $imageInfo['mime'] !== $mimeType
            ) {
                $this->view('admin/administrators/profile', [
                    'error' => 'Please upload a valid PNG, JPG, or JPEG image.',
                    'admin' => $admin
                ]);
                return;
            }

            $extension = $allowedTypes[$mimeType];

            // Store files under public/uploads/profile_pictures.
            $uploadDirectory = dirname(__DIR__, 2)
                . '/public/uploads/profile_pictures';

            if (
                !is_dir($uploadDirectory) &&
                !mkdir($uploadDirectory, 0755, true) &&
                !is_dir($uploadDirectory)
            ) {
                $this->view('admin/administrators/profile', [
                    'error' => 'Unable to prepare the profile picture directory.',
                    'admin' => $admin
                ]);
                return;
            }

            // The filename is unique to the logged-in user.
            $fileName = 'userid_' . $adminId . '.' . $extension;
            $destination = $uploadDirectory . '/' . $fileName;

            if (!move_uploaded_file($profilePicture['tmp_name'], $destination)) {
                $this->view('admin/administrators/profile', [
                    'error' => 'Unable to save the profile picture.',
                    'admin' => $admin
                ]);
                return;
            }

            // Remove an older image for this same user if its extension changed.
            foreach (glob($uploadDirectory . '/userid_' . $adminId . '.*') ?: [] as $oldFile) {
                if ($oldFile !== $destination && is_file($oldFile)) {
                    unlink($oldFile);
                }
            }
        }

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


    /*
|--------------------------------------------------------------------------
| Salary Management (US-09)
|--------------------------------------------------------------------------
*/

    /**
     * Display and automatically refresh monthly salary records.
     */
    public function salaryManagement(): void
    {
        Middleware::requireAdmin();

        // Default to the current month when no month filter is selected.
        $month = trim($_GET['month'] ?? date('Y-m'));

        $employeeId = null;
        $error = $_SESSION['error_message'] ?? null;
        $autoCalculationWarnings = [];

        if (isset($_GET['employee_id']) && $_GET['employee_id'] !== '') {
            $validatedEmployeeId = filter_var(
                $_GET['employee_id'],
                FILTER_VALIDATE_INT
            );

            if ($validatedEmployeeId === false || $validatedEmployeeId <= 0) {
                $error = 'Invalid employee selection.';
            } else {
                $employeeId = (int) $validatedEmployeeId;
            }
        }

        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $error = 'Please select a valid salary month.';
            $month = date('Y-m');
        }

        /*
         * Automatic calculation runs when the Salary Management
         * page is opened normally.
         *
         * A manual calculation redirect skips this once so that
         * other employees' calculation timestamps remain unchanged.
         */
        $skipAutomaticCalculation =
            !empty($_SESSION['skip_salary_auto_refresh_once']);

        unset($_SESSION['skip_salary_auto_refresh_once']);

        if (!$skipAutomaticCalculation && $error === null) {

            $currentMonth = date('Y-m');

            try {
                $employees = $this->employeeModel->getAll();

                foreach ($employees as $employee) {

                    $currentEmployeeId = (int) $employee['employee_id'];

                    try {
                        $salaryInfo = $this->salaryModel->getEmployeeSalaryInfo(
                            $currentEmployeeId
                        );

                        if (!$salaryInfo) {
                            $autoCalculationWarnings[] =
                                ($employee['employee_code'] ?? 'Unknown employee')
                                . ': Employee information not found.';

                            continue;
                        }

                        if (
                            empty($salaryInfo['monthly_salary']) ||
                            (float) $salaryInfo['monthly_salary'] <= 0
                        ) {
                            $autoCalculationWarnings[] =
                                $salaryInfo['employee_code']
                                . ' - ' . $salaryInfo['full_name']
                                . ': Monthly salary is not configured.';

                            continue;
                        }

                        if (empty($salaryInfo['joining_date'])) {
                            $autoCalculationWarnings[] =
                                $salaryInfo['employee_code']
                                . ' - ' . $salaryInfo['full_name']
                                . ': Joining date is missing.';

                            continue;
                        }

                        // Calculate the current month's salary.
                        $breakdown = $this->salaryModel->calculateMonthlySalary(
                            $currentEmployeeId,
                            $currentMonth
                        );

                        // Create or update the employee's monthly record.
                        $saved = $this->salaryModel->saveMonthlySalary(
                            $breakdown
                        );

                        if (!$saved) {
                            $autoCalculationWarnings[] =
                                $salaryInfo['employee_code']
                                . ' - ' . $salaryInfo['full_name']
                                . ': Salary record could not be saved.';
                        }

                    } catch (Throwable $e) {

                        error_log(
                            'Automatic salary calculation failed for employee '
                            . $currentEmployeeId . ': ' . $e->getMessage()
                        );

                        $autoCalculationWarnings[] =
                            ($employee['employee_code'] ?? 'Unknown employee')
                            . ' - '
                            . ($employee['full_name'] ?? 'Unknown name')
                            . ': Calculation failed. Please check employee configuration.';
                    }
                }

            } catch (Throwable $e) {

                error_log(
                    'Automatic salary processing error: ' . $e->getMessage()
                );

                $error = 'Unable to automatically calculate employee salaries.';
            }
        }

        // Load salary records using the selected filters.
        $salaryRecords = [];

        if ($error === null) {
            try {
                $salaryRecords = $this->salaryModel->getSalaryHistory(
                    $month,
                    $employeeId
                );

            } catch (Throwable $e) {

                error_log('Salary history error: ' . $e->getMessage());

                $error = 'Unable to load salary records. Please try again.';
            }
        }

        $this->view('admin/salary/index', [
            'pageTitle' => 'Salary Management',
            'employees' => $this->employeeModel->getAll(),
            'salaryRecords' => $salaryRecords,
            'selectedMonth' => $month,
            'selectedEmployeeId' => $employeeId,
            'autoCalculationWarnings' => $autoCalculationWarnings,
            'success' => $_SESSION['success_message'] ?? null,
            'error' => $error,
            'csrfToken' => Auth::csrfToken()
        ]);

        unset(
            $_SESSION['success_message'],
            $_SESSION['error_message']
        );
    }




    /**
     * Calculate and save an employee's monthly salary.
     */
    public function calculateSalary(): void
    {
        Middleware::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }

        if (
            !Auth::verifyCsrfToken(
                (string) ($_POST['csrf_token'] ?? '')
            )
        ) {
            $_SESSION['error_message'] =
                'Invalid security token. Please refresh and try again.';

            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=salary-management'
            );
        }

        $employeeId = filter_var(
            $_POST['employee_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $month = trim((string) ($_POST['month'] ?? ''));

        if (
            !$employeeId ||
            $employeeId <= 0 ||
            !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)
        ) {
            $_SESSION['error_message'] =
                'Please select a valid employee and salary month.';

            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=salary-management'
            );
        }

        try {
            $breakdown = $this->salaryModel->calculateMonthlySalary(
                (int) $employeeId,
                $month
            );

            $saved = $this->salaryModel->saveMonthlySalary(
                $breakdown,
                Auth::userId()
            );

            if ($saved) {
                $_SESSION['success_message'] =
                    'Monthly salary calculated and saved successfully.';
                $_SESSION['skip_salary_auto_refresh_once'] = true;
            } else {
                $_SESSION['error_message'] =
                    'Unable to save the salary calculation.';
            }

        } catch (Throwable $e) {
            error_log('Salary calculation error: ' . $e->getMessage());

            $_SESSION['error_message'] =
                'Salary calculation failed. Please check the employee salary information and try again.';
        }

        $this->redirect(
            '/HRIS-Management-System/public/index.php?action=salary-management'
            . '&month=' . urlencode($month)
            . '&employee_id=' . (int) $employeeId
        );
    }


    /**
     * Display the detailed breakdown of a saved salary record.
     */
    public function salaryDetails(): void
    {
        Middleware::requireAdmin();

        $salaryId = filter_var(
            $_GET['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$salaryId || $salaryId <= 0) {
            http_response_code(404);
            die('Salary record not found.');
        }

        try {
            $salaryRecord = $this->salaryModel->getSalaryDetails(
                (int) $salaryId
            );

            if (!$salaryRecord) {
                http_response_code(404);
                die('Salary record not found.');
            }

            $this->view('admin/salary/details', [
                'pageTitle' => 'Salary Details',
                'salaryRecord' => $salaryRecord
            ]);

        } catch (Throwable $e) {
            error_log('Salary details error: ' . $e->getMessage());

            http_response_code(500);
            die('Unable to load salary details. Please try again.');
        }
    }


    /*
|--------------------------------------------------------------------------
| Leave Management (US-10)
|--------------------------------------------------------------------------
*/

    /**
     * Display all employee leave requests.
     * Optionally filter by employee code.
     */
    public function leaveManagement(): void
    {
        Middleware::requireAdmin();

        $employeeCode = strtoupper(
            trim((string) ($_GET['employee_code'] ?? ''))
        );

        $this->view('admin/leaves/index', [
            'pageTitle' => 'Leave Management',
            'leaveRequests' => $this->leaveRequestModel->getAllForAdmin(
                $employeeCode !== '' ? $employeeCode : null
            ),
            'employeeCode' => $employeeCode,
            'success' => $_SESSION['success_message'] ?? null,
            'error' => $_SESSION['error_message'] ?? null,
            'csrfToken' => Auth::csrfToken()
        ]);

        unset($_SESSION['success_message'], $_SESSION['error_message']);
    }


    /**
     * Approve or reject a pending leave request.
     */
    public function reviewLeaveRequest(): void
    {
        Middleware::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }

        if (
            !Auth::verifyCsrfToken(
                (string) ($_POST['csrf_token'] ?? '')
            )
        ) {
            http_response_code(403);
            exit('Invalid security token. Please refresh and try again.');
        }

        $requestId = filter_var(
            $_POST['request_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $status = trim((string) ($_POST['status'] ?? ''));

        if (
            !$requestId ||
            $requestId <= 0 ||
            !in_array($status, ['approved', 'rejected'], true)
        ) {
            $_SESSION['error_message'] = 'Invalid leave review request.';
            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=leave-management'
            );
        }

        $adminId = Auth::userId();

        if (!$adminId) {
            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=login'
            );
        }

        try {
            $updated = $this->leaveRequestModel->updateStatus(
                (int) $requestId,
                $status,
                (int) $adminId
            );

            if ($updated) {
                $_SESSION['success_message'] =
                    $status === 'approved'
                    ? 'Leave request accepted successfully.'
                    : 'Leave request rejected successfully.';
            } else {
                $_SESSION['error_message'] =
                    'This request may already have been reviewed.';
            }

        } catch (PDOException $e) {
            $_SESSION['error_message'] =
                'Unable to review the leave request. Please try again.';
        }

        $this->redirect(
            '/HRIS-Management-System/public/index.php?action=leave-management'
        );
    }



    /**
     * Display audit logs with filtering and pagination.
     */
    public function auditLogs(): void
    {
        Middleware::requireAdmin();

        // Collect filter values.
        $fromDate = trim((string) ($_GET['from_date'] ?? ''));
        $toDate = trim((string) ($_GET['to_date'] ?? ''));
        $userId = trim((string) ($_GET['user_id'] ?? ''));
        $module = trim((string) ($_GET['module'] ?? ''));

        $error = null;

        // Validate selected audit module.
        $allowedModules = [
            'authentication',
            'employee_management',
            'rfid_management',
            'attendance_management',
            'work_schedules',
            'salary_management',
            'leave_management',
            'holiday_management'
        ];

        if ($module !== '' && !in_array($module, $allowedModules, true)) {
            $error = 'Invalid module selection.';
            $module = '';
        }

        // Validate date format.
        foreach ([
            'From Date' => $fromDate,
            'To Date' => $toDate
        ] as $label => $date) {

            if ($date !== '') {
                $parsedDate = DateTime::createFromFormat('!Y-m-d', $date);

                if (
                    !$parsedDate ||
                    $parsedDate->format('Y-m-d') !== $date
                ) {
                    $error = $label . ' is invalid.';
                    break;
                }
            }
        }

        // Ensure the date range is logical.
        if (
            $error === null &&
            $fromDate !== '' &&
            $toDate !== '' &&
            $fromDate > $toDate
        ) {
            $error = 'From Date cannot be later than To Date.';
        }

        // Validate user ID.
        if ($userId !== '') {
            $validatedUserId = filter_var(
                $userId,
                FILTER_VALIDATE_INT
            );

            if ($validatedUserId === false || $validatedUserId <= 0) {
                $error = 'Invalid user selection.';
            } else {
                $userId = (string) $validatedUserId;
            }
        }


        // Build filters.
        $filters = [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'user_id' => $userId,
            'module' => $module
        ];

        // Pagination settings.
        $limit = 20;

        $page = filter_var(
            $_GET['page'] ?? 1,
            FILTER_VALIDATE_INT
        );

        $page = ($page !== false && $page > 0)
            ? $page
            : 1;

        $totalRecords = 0;
        $totalPages = 1;
        $auditLogs = [];
        $categorySummary = [];

        try {

            if ($error === null) {

                $totalRecords = $this->auditLogModel->countFiltered(
                    $filters
                );

                $categorySummary = $this->auditLogModel->getCategorySummary(
                    $filters
                );

                $totalPages = max(
                    1,
                    (int) ceil($totalRecords / $limit)
                );

                $page = min($page, $totalPages);

                $offset = ($page - 1) * $limit;

                $auditLogs = $this->auditLogModel->getFiltered(
                    $filters,
                    $limit,
                    $offset
                );
            }

            $users = $this->auditLogModel->getUsersForFilter();

        } catch (Throwable $e) {

            error_log('Audit logs loading error: ' . $e->getMessage());

            $error = 'Unable to load audit logs. Please try again.';

            $users = [];
            $auditLogs = [];
            $totalRecords = 0;
            $totalPages = 1;
            $page = 1;
            $categorySummary = [];
        }

        $this->view('admin/audit-logs/index', [
            'pageTitle' => 'Audit Logs',
            'auditLogs' => $auditLogs,
            'users' => $users,
            'filters' => $filters,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalRecords' => $totalRecords,
            'categorySummary' => $categorySummary,
            'error' => $error
        ]);
    }

}