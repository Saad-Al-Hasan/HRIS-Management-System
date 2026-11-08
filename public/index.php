<?php

require_once __DIR__ . '/../config/config.php';

require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/core/Controller.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/core/Middleware.php';

require_once __DIR__ . '/../app/models/User.php';
require_once __DIR__ . '/../app/controllers/AuthController.php';
require_once __DIR__ . '/../app/controllers/AdminController.php';
require_once __DIR__ . '/../app/controllers/AdminEmployeeController.php';
require_once __DIR__ . '/../app/controllers/EmployeeController.php';
require_once __DIR__ . '/../app/controllers/RfidController.php';
require_once __DIR__ . '/../app/controllers/AttendanceController.php';
require_once __DIR__ . '/../app/controllers/WorkScheduleController.php';


$action = $_GET['action'] ?? 'login';

$authController = new AuthController();
$adminController = new AdminController();
$adminEmployeeController = new AdminEmployeeController();
$employeeController = new EmployeeController();
$rfidController = new RfidController();
$attendanceController = new AttendanceController();
$workScheduleController = new WorkScheduleController();


switch ($action) {

    case 'login':

        if (Auth::check()) {
            $authController->dashboard();
            break;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $authController->login();
        } else {
            $authController->showLogin();
        }

        break;


    case 'dashboard':

        $authController->dashboard();

        break;


    case 'logout':

        $authController->logout();

        break;



    /*
|--------------------------------------------------------------------------
| Administrator Account & Profile
|--------------------------------------------------------------------------
*/

    case 'administrators':

        $adminController->index();

        break;


    case 'administrator-create':

        $adminController->create();

        break;


    case 'administrator-store':

        $adminController->store();

        break;


    case 'administrator-edit':

        $adminController->edit();

        break;


    case 'administrator-update':

        $adminController->update();

        break;


    case 'profile':

        $adminController->profile();

        break;


    case 'profile-update':

        $adminController->updateProfile();

        break;


    /*
|--------------------------------------------------------------------------
| Employee Management (Administrator)
|--------------------------------------------------------------------------
*/

    case 'employees':

        $adminEmployeeController->index();

        break;

    case 'employee-view':

        $adminEmployeeController->viewDetails();

        break;


    case 'employee-edit':

        $adminEmployeeController->edit();

        break;


    case 'employee-update':

        $adminEmployeeController->update();

        break;


    case 'employee-create':

        $adminEmployeeController->create();

        break;


    case 'employee-store':

        $adminEmployeeController->store();

        break;

    /*
    |--------------------------------------------------------------------------
    | RFID Employee Identification (US-03)
    |--------------------------------------------------------------------------
    */

    case 'rfid':
        $rfidController->index();
        break;

    case 'rfid-assign':
        $rfidController->assignPage();
        break;

    case 'rfid-store':
        $rfidController->store();
        break;

    case 'rfid-unassign':
        $rfidController->unassign();
        break;

    /*
|--------------------------------------------------------------------------
| RFID Attendance Station (US-04, US-05)
|--------------------------------------------------------------------------
*/
    case 'attendance-station':
        $attendanceController->station();
        break;


    case 'attendance-scan':
        $attendanceController->scan();
        break;

    case 'attendance-management':
        $attendanceController->management();
        break;

    case 'modify-attendance':
        $attendanceController->modify();
        break;

    case 'attendance-activity':
        Middleware::requireLogin();

        if (Auth::roleId() !== 1) {
            http_response_code(403);
            exit;
        }

        require_once __DIR__ . '/../app/models/Attendance.php';

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $attendanceModel = new Attendance();
        $today = date('Y-m-d');

        $activity = $attendanceModel->getTodayActivity($today, 500);

        $uploadDirectory = __DIR__ . '/uploads/profile_pictures/';
        $uploadUrl = '/HRIS-Management-System/public/uploads/profile_pictures/';

        foreach ($activity as &$scan) {
            $userId = (int) ($scan['user_id'] ?? 0);
            $profilePicture = null;

            foreach (['png', 'jpg', 'jpeg', 'webp'] as $extension) {
                $filename = 'userid_' . $userId . '.' . $extension;

                if (is_file($uploadDirectory . $filename)) {
                    $profilePicture = $uploadUrl . $filename;
                    break;
                }
            }

            $scan['profile_picture'] = $profilePicture;
        }
        unset($scan);

        echo json_encode(
            $activity,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
        );

        exit;



    /*
    |--------------------------------------------------------------------------
    | Work Schedule Management (US-08)
    |--------------------------------------------------------------------------
    */

    case 'work-schedules':
        $workScheduleController->index();
        break;

    case 'work-schedules-create':
        $workScheduleController->create();
        break;

    case 'work-schedules-store':
        $workScheduleController->store();
        break;

    case 'work-schedules-edit':
        $workScheduleController->edit();
        break;

    case 'work-schedules-update':
        $workScheduleController->update();
        break;

    case 'work-schedule-assignments':
        $workScheduleController->assignments();
        break;

    case 'work-schedule-assign':
        $workScheduleController->assign();
        break;

    case 'work-schedules-delete':
        $workScheduleController->delete();
        break;

    case 'work-schedules-assign-all':
        $workScheduleController->assignAllEmployees();
        break;



    /*
|--------------------------------------------------------------------------
| Employee Account & Profile
|--------------------------------------------------------------------------
*/

    case 'employee-dashboard':

        $employeeController->dashboard();

        break;


    case 'employee-profile':

        $employeeController->profile();

        break;

    case 'employee-profile-picture-update':

        $employeeController->updateProfilePicture();

        break;

    case 'employee-toggle-status':

        $adminEmployeeController->toggleStatus();

        break;

    default:

        http_response_code(404);
        echo "404 - Page Not Found";

        break;
}


