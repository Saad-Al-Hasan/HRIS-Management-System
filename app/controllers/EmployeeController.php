<?php

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../models/Employee.php';

class EmployeeController extends Controller
{
    private Employee $employeeModel;

    public function __construct()
    {
        $this->employeeModel = new Employee();
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

        $this->view('employee/dashboard', [
            'employee' => $employee,
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

        $this->view('employee/profile', [
            'employee' => $employee
        ]);
    }
}
