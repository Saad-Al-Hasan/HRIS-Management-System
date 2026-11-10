<?php

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../models/Employee.php';
require_once __DIR__ . '/../models/RfidCard.php';
require_once __DIR__ . '/../models/WorkSchedule.php';
require_once __DIR__ . '/../models/Attendance.php';

class EmployeeController extends Controller
{
    private Employee $employeeModel;
    private RfidCard $rfidCardModel;
    private WorkSchedule $workScheduleModel;
    private Attendance $attendanceModel;

    public function __construct()
    {
        $this->employeeModel = new Employee();
        $this->rfidCardModel = new RfidCard();
        $this->workScheduleModel = new WorkSchedule();
        $this->attendanceModel = new Attendance();
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
}
