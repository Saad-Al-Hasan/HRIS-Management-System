<?php

require_once __DIR__ . '/../config/config.php';

require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/core/Controller.php';
require_once __DIR__ . '/../app/core/Auth.php';
require_once __DIR__ . '/../app/core/Middleware.php';

require_once __DIR__ . '/../app/models/User.php';
require_once __DIR__ . '/../app/controllers/AuthController.php';


$action = $_GET['action'] ?? 'login';

$authController = new AuthController();


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


    default:

        http_response_code(404);
        echo "404 - Page Not Found";

        break;
}