<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../models/Employee.php';
require_once __DIR__ . '/../services/RfidRegistrationService.php';
require_once __DIR__ . '/../services/RfidRegistrationProxy.php';

class RfidController extends Controller
{
    private Employee $employeeModel;

    public function __construct()
    {
        $this->employeeModel = new Employee();
    }

    /**
     * Display the RFID registration page.
     */
    public function index(): void
    {
        Middleware::requireAdmin();

        $this->view('admin/rfid/index', [
            'employees' => $this->getEmployeesSortedByCode(),
            'error' => $_SESSION['rfid_error'] ?? null,
            'success' => $_SESSION['rfid_success'] ?? null,
        ]);

        unset($_SESSION['rfid_error'], $_SESSION['rfid_success']);
    }

    /**
     * Process RFID registration.
     */
    public function store(): void
    {
        Middleware::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo '405 - Method Not Allowed';
            return;
        }

        $csrfToken = $_POST['csrf_token'] ?? '';

        if (
            !is_string($csrfToken) ||
            !Auth::verifyCsrfToken($csrfToken)
        ) {
            $_SESSION['rfid_error'] =
                'Invalid security token. Please try again.';

            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=rfid'
            );
            return;
        }

        $employeeId = filter_var(
            $_POST['employee_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $identifier = $_POST['rfid_identifier'] ?? '';

        if (!is_string($identifier)) {
            $identifier = '';
        }

        try {
            $service = new RfidRegistrationService();
            $proxy = new RfidRegistrationProxy($service);

            $result = $proxy->register(
                $employeeId === false || $employeeId === null
                ? 0
                : $employeeId,
                $identifier
            );

            $_SESSION['rfid_success'] = $result['message'];
        } catch (PDOException $exception) {
            $_SESSION['rfid_error'] =
                'This RFID card or employee may already have an assignment.';
        } catch (InvalidArgumentException $exception) {
            $_SESSION['rfid_error'] = $exception->getMessage();
        } catch (RuntimeException $exception) {
            $_SESSION['rfid_error'] = $exception->getMessage();
        }

        $this->redirect(
            '/HRIS-Management-System/public/index.php?action=rfid'
        );
    }

    private function getEmployeesSortedByCode(): array
    {
        $employees = $this->employeeModel->getAll();

        usort($employees, function (array $a, array $b): int {
            return strnatcasecmp(
                $a['employee_code'] ?? '',
                $b['employee_code'] ?? ''
            );
        });

        return $employees;
    }
}