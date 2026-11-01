<?php

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../models/Employee.php';
require_once __DIR__ . '/../models/RfidCard.php';

class EmployeeController extends Controller
{
    private Employee $employeeModel;
    private RfidCard $rfidCardModel;

    public function __construct()
    {
        $this->employeeModel = new Employee();
        $this->rfidCardModel = new RfidCard();
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

        $rfidIdentifier = 'Not assigned';

        if (
            $employee !== null &&
            !empty($employee['employee_id'])
        ) {
            $rfidCard = $this->rfidCardModel->findByEmployeeId(
                (int) $employee['employee_id']
            );

            if ($rfidCard !== null) {
                $rfidIdentifier = $rfidCard['rfid_identifier'];
            }
        }

        $this->view('employee/dashboard', [
            'employee' => $employee,
            'rfidIdentifier' => $rfidIdentifier,
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

        $rfidCard = null;

        if (!empty($employee['employee_id'])) {
            $rfidCard = $this->rfidCardModel->findByEmployeeId(
                (int) $employee['employee_id']
            );
        }

        $rfidIdentifier = $rfidCard['rfid_identifier'] ?? 'Not assigned';

        $this->view('employee/profile', [
            'employee' => $employee,
            'rfidIdentifier' => $rfidIdentifier
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

        if ($file['size'] > 5 * 1024 * 1024) {
            $_SESSION['employee_profile_picture_error'] =
                'The profile picture must be 5 MB or smaller.';

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
