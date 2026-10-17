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


$action = $_GET['action'] ?? 'login';

$authController = new AuthController();
$adminController = new AdminController();
$adminEmployeeController = new AdminEmployeeController();
$employeeController = new EmployeeController();
$rfidController = new RfidController();


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

    case 'rfid-store':
        $rfidController->store();
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

    case 'employee-toggle-status':

        $adminEmployeeController->toggleStatus();

        break;

    default:

        http_response_code(404);
        echo "404 - Page Not Found";

        break;
}


