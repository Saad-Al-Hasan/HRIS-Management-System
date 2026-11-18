<?php

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../models/Employee.php';
require_once __DIR__ . '/../models/RfidCard.php';
require_once __DIR__ . '/../models/WorkSchedule.php';
require_once __DIR__ . '/../models/Attendance.php';
require_once __DIR__ . '/../models/LeaveRequest.php';
require_once __DIR__ . '/../models/Salary.php';

class EmployeeController extends Controller
{
    private Employee $employeeModel;
    private RfidCard $rfidCardModel;
    private WorkSchedule $workScheduleModel;
    private Attendance $attendanceModel;
    private LeaveRequest $leaveRequestModel;
    private Salary $salaryModel;

    public function __construct()
    {
        $this->employeeModel = new Employee();
        $this->rfidCardModel = new RfidCard();
        $this->workScheduleModel = new WorkSchedule();
        $this->attendanceModel = new Attendance();
        $this->leaveRequestModel = new LeaveRequest();
        $this->salaryModel = new Salary();
    }

    public function dashboard(): void
    {
        Middleware::requireEmployee();

        $userId = Auth::userId();

        if ($userId === null) {
            $this->redirect(
                '/HRIS-Management-System/public/index.php'
            );
            return;
        }

        $employee = $this->employeeModel->findByUserId($userId);

        if (
            $employee === null ||
            empty($employee['employee_id'])
        ) {
            http_response_code(404);

            $this->view('employee/dashboard', [
                'employee' => $employee,
                'rfidIdentifier' => 'Not assigned',
                'assignedSchedule' => null,
                'attendanceRecords' => [],
                'attendanceFromDate' => '',
                'attendanceToDate' => '',
                'attendanceError' => null,
            ]);

            return;
        }

        $employeeId = (int) $employee['employee_id'];

        // Retrieve the employee's assigned work schedule.
        $assignedSchedule = $this->workScheduleModel
            ->findEffectiveScheduleForEmployee($employeeId);

        // Retrieve the employee's RFID identifier.
        $rfidIdentifier = 'Not assigned';

        $rfidCard = $this->rfidCardModel->findByEmployeeId(
            $employeeId
        );

        if ($rfidCard !== null) {
            $rfidIdentifier = $rfidCard['rfid_identifier'];
        }

        // Read the optional attendance date filters.
        $attendanceFromDate = trim(
            (string) ($_GET['attendance_from'] ?? '')
        );

        $attendanceToDate = trim(
            (string) ($_GET['attendance_to'] ?? '')
        );

        $attendanceError = null;
        $attendanceRecords = [];

        // Validate the date format.
        $isValidDate = static function (string $date): bool {
            if ($date === '') {
                return true;
            }

            $parsedDate = DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $date
            );

            return $parsedDate !== false
                && $parsedDate->format('Y-m-d') === $date;
        };

        if (
            !$isValidDate($attendanceFromDate) ||
            !$isValidDate($attendanceToDate)
        ) {
            $attendanceError =
                'Please enter valid From Date and To Date values.';
        } elseif (
            $attendanceFromDate !== '' &&
            $attendanceToDate !== '' &&
            $attendanceFromDate > $attendanceToDate
        ) {
            $attendanceError =
                'From Date cannot be later than To Date.';
        } else {
            /*
             * Default attendance history to the current calendar month.
             * The month automatically adjusts to 28, 29, 30, or 31 days.
             */
            $today = new DateTimeImmutable('today');

            $fromDate = $attendanceFromDate !== ''
                ? $attendanceFromDate
                : $today->modify('first day of this month')->format('Y-m-d');

            $toDate = $attendanceToDate !== ''
                ? $attendanceToDate
                : $today->format('Y-m-d');

            // Never accept an employee ID from the browser.
            // The ID comes from the authenticated employee profile.
            $attendanceRecords =
                $this->attendanceModel->getEmployeeAttendanceByDateRange(
                    $employeeId,
                    $fromDate,
                    $toDate
                );

            // Attach RFID scan history to each attendance record.
            foreach ($attendanceRecords as &$record) {
                $record['scan_history'] =
                    $this->attendanceModel->getEmployeeScanHistoryForDate(
                        $employeeId,
                        (string) $record['attendance_date']
                    );
            }

            unset($record);
        }

        $this->view('employee/dashboard', [
            'employee' => $employee,
            'rfidIdentifier' => $rfidIdentifier,
            'assignedSchedule' => $assignedSchedule,
            'attendanceRecords' => $attendanceRecords,
            'attendanceFromDate' => $attendanceFromDate,
            'attendanceToDate' => $attendanceToDate,
            'attendanceError' => $attendanceError,
        ]);
    }

    public function profile(): void
    {
        Middleware::requireEmployee();

        $userId = Auth::userId();

        if ($userId === null) {
            $this->redirect(
                '/HRIS-Management-System/public/index.php'
            );
        }

        $employee = $this->employeeModel->findByUserId($userId);

        if ($employee === null) {
            http_response_code(404);

            $this->view('employee/profile', [
                'error' => 'Your employee profile has not been registered yet.'
            ]);

            return;
        }


        $assignedSchedule = null;

        if (!empty($employee['employee_id'])) {
            $assignedSchedule = $this->workScheduleModel
                ->findEffectiveScheduleForEmployee(
                    (int) $employee['employee_id']
                );
        }


        $rfidCard = null;

        if (!empty($employee['employee_id'])) {
            $rfidCard = $this->rfidCardModel->findByEmployeeId(
                (int) $employee['employee_id']
            );
        }

        $rfidIdentifier = $rfidCard['rfid_identifier'] ?? 'Not assigned';

        $this->view('employee/profile', [
            'employee' => $employee,
            'rfidIdentifier' => $rfidIdentifier,
            'assignedSchedule' => $assignedSchedule
        ]);
    }

    public function updateProfilePicture(): void
    {
        Middleware::requireEmployee();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Method Not Allowed');
        }

        $userId = Auth::userId();

        if ($userId === null) {
            $this->redirect('/HRIS-Management-System/public/index.php');
            return;
        }

        if (!Auth::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['employee_profile_picture_error'] =
                'Invalid security token. Please try again.';

            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=employee-profile'
            );
            return;
        }

        $file = $_FILES['profile_picture'] ?? null;

        if (
            $file === null ||
            $file['error'] !== UPLOAD_ERR_OK
        ) {
            $_SESSION['employee_profile_picture_error'] =
                'Please select a profile picture and try again.';

            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=employee-profile'
            );
            return;
        }

        // Maximum upload size: 1 MB.
        if ($file['size'] > 1024 * 1024) {
            $_SESSION['employee_profile_picture_error'] =
                'The profile picture must be 1 MB or smaller.';

            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=employee-profile'
            );
            return;
        }

        $imageInfo = @getimagesize($file['tmp_name']);
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        $allowedTypes = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
        ];

        if (
            $imageInfo === false ||
            !isset($allowedTypes[$mimeType]) ||
            ($imageInfo['mime'] ?? '') !== $mimeType
        ) {
            $_SESSION['employee_profile_picture_error'] =
                'Please upload a valid PNG, JPG, or JPEG image.';

            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=employee-profile'
            );
            return;
        }

        $uploadDirectory = dirname(__DIR__, 2)
            . '/public/uploads/profile_pictures';

        if (
            !is_dir($uploadDirectory) &&
            !mkdir($uploadDirectory, 0755, true) &&
            !is_dir($uploadDirectory)
        ) {
            $_SESSION['employee_profile_picture_error'] =
                'Unable to prepare the profile picture directory.';

            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=employee-profile'
            );
            return;
        }

        $extension = $allowedTypes[$mimeType];
        $fileName = 'userid_' . $userId . '.' . $extension;
        $destination = $uploadDirectory . '/' . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $_SESSION['employee_profile_picture_error'] =
                'Unable to save the profile picture. Please try again.';

            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=employee-profile'
            );
            return;
        }

        // Remove an older image for this user if the extension changed.
        foreach (glob($uploadDirectory . '/userid_' . $userId . '.*') ?: [] as $oldFile) {
            if ($oldFile !== $destination && is_file($oldFile)) {
                unlink($oldFile);
            }
        }

        $_SESSION['employee_profile_picture_success'] =
            'Your profile picture was updated successfully.';

        $this->redirect(
            '/HRIS-Management-System/public/index.php?action=employee-profile'
        );
    }



    /**
     * Display the authenticated employee's current monthly salary details.
     *
     * Automatically recalculates and updates the current month's
     * salary record whenever the employee opens this page.
     *
     * Employees can only access their own salary information.
     */
    public function salaryDetails(): void
    {
        Middleware::requireEmployee();

        $userId = Auth::userId();

        if ($userId === null) {
            $this->redirect(
                '/HRIS-Management-System/public/index.php'
            );
            return;
        }

        // Retrieve the employee associated with the authenticated user.
        $employee = $this->employeeModel->findByUserId($userId);

        if (
            !$employee ||
            empty($employee['employee_id'])
        ) {
            http_response_code(404);

            $this->view('employee/salary-details', [
                'pageTitle' => 'Salary Details',
                'employee' => $employee,
                'salaryRecord' => null,
                'breakdown' => null,
                'error' => 'Your employee record could not be found.'
            ]);

            return;
        }

        $employeeId = (int) $employee['employee_id'];

        try {

            // Determine the current calendar month automatically.
            $currentMonth = (new DateTimeImmutable('today'))
                ->format('Y-m');

            // Calculate salary using the existing approved business rules.
            $breakdown = $this->salaryModel->calculateMonthlySalary(
                $employeeId,
                $currentMonth
            );

            // Save or update the employee's monthly salary record.
            $saved = $this->salaryModel->saveMonthlySalary($breakdown);

            if (!$saved) {
                throw new RuntimeException(
                    'Unable to update the current salary record.'
                );
            }

            // Retrieve the saved record belonging to this employee.
            $salaryHistory = $this->salaryModel->getSalaryHistory(
                $currentMonth,
                $employeeId
            );

            if (empty($salaryHistory)) {
                throw new RuntimeException(
                    'The current salary record could not be retrieved.'
                );
            }

            // Retrieve the complete stored salary statement.
            $salaryRecord = $this->salaryModel->getSalaryDetails(
                (int) $salaryHistory[0]['id']
            );

            if (!$salaryRecord) {
                throw new RuntimeException(
                    'The salary statement could not be loaded.'
                );
            }

            // Use the saved calculation breakdown for the view.
            $breakdown = $salaryRecord['calculation_details'];

            $this->view('employee/salary-details', [
                'pageTitle' => 'Salary Details',
                'employee' => $employee,
                'salaryRecord' => $salaryRecord,
                'breakdown' => $breakdown,
                'error' => null
            ]);

        } catch (Throwable $e) {

            error_log(
                'Employee Salary Details Error: ' . $e->getMessage()
            );

            $this->view('employee/salary-details', [
                'pageTitle' => 'Salary Details',
                'employee' => $employee,
                'salaryRecord' => null,
                'breakdown' => null,
                'error' => 'Unable to load your salary details. Please try again later.'
            ]);
        }
    }




    /**
     * Display the employee's leave application page.
     */
    public function leaveApplication(): void
    {
        Middleware::requireEmployee();

        $userId = Auth::userId();
        $employee = $this->employeeModel->findByUserId($userId);

        if (!$employee || empty($employee['employee_id'])) {
            http_response_code(404);
            exit('Employee record not found.');
        }

        $employeeId = (int) $employee['employee_id'];

        $this->view('employee/leave-application', [
            'pageTitle' => 'Leave Application',
            'employee' => $employee,
            'leaveRequests' => $this->leaveRequestModel->getByEmployeeId($employeeId),
            'success' => $_SESSION['success_message'] ?? null,
            'error' => $_SESSION['error_message'] ?? null,
            'csrfToken' => Auth::csrfToken()
        ]);

        unset($_SESSION['success_message'], $_SESSION['error_message']);
    }

    /**
     * Store a leave application submitted by the authenticated employee.
     */
    public function storeLeaveApplication(): void
    {
        Middleware::requireEmployee();

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

        $userId = Auth::userId();
        $employee = $this->employeeModel->findByUserId($userId);

        if (!$employee || empty($employee['employee_id'])) {
            http_response_code(404);
            exit('Employee record not found.');
        }

        $employeeId = (int) $employee['employee_id'];

        $leaveType = trim((string) ($_POST['leave_type'] ?? ''));
        $leaveDate = trim((string) ($_POST['leave_date'] ?? ''));
        $halfDayPeriod = trim((string) ($_POST['half_day_period'] ?? ''));
        $reason = trim((string) ($_POST['reason'] ?? ''));

        // Validate leave type.
        if (!in_array($leaveType, ['Full Day', 'Half Day'], true)) {
            $_SESSION['error_message'] = 'Please select a valid leave type.';
            $this->redirect('/HRIS-Management-System/public/index.php?action=leave-application');
        }

        // Validate date.
        $dateObject = DateTime::createFromFormat('!Y-m-d', $leaveDate);

        if (!$dateObject || $dateObject->format('Y-m-d') !== $leaveDate) {
            $_SESSION['error_message'] = 'Please select a valid leave date.';
            $this->redirect('/HRIS-Management-System/public/index.php?action=leave-application');
        }

        // Validate half-day period.
        if (
            $leaveType === 'Half Day' &&
            !in_array($halfDayPeriod, ['first_half', 'second_half'], true)
        ) {
            $_SESSION['error_message'] = 'Please select the half-day period.';
            $this->redirect('/HRIS-Management-System/public/index.php?action=leave-application');
        }

        if ($leaveType === 'Full Day') {
            $halfDayPeriod = '';
        }

        // Validate reason.
        if ($reason === '') {
            $_SESSION['error_message'] = 'Please provide a reason for your leave.';
            $this->redirect('/HRIS-Management-System/public/index.php?action=leave-application');
        }

        try {
            $this->leaveRequestModel->create(
                $employeeId,
                $leaveType,
                $halfDayPeriod !== '' ? $halfDayPeriod : null,
                $leaveDate,
                $leaveDate,
                $reason
            );

            $_SESSION['success_message'] = 'Leave application submitted successfully.';

        } catch (PDOException $e) {
            $_SESSION['error_message'] = 'Unable to submit your leave application. Please try again.';
        }

        $this->redirect('/HRIS-Management-System/public/index.php?action=leave-application');
    }
}
