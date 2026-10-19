<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../models/Employee.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/RfidCard.php';
require_once __DIR__ . '/../services/RfidRegistrationService.php';
require_once __DIR__ . '/../services/RfidRegistrationProxy.php';

class RfidController extends Controller
{
    private Employee $employeeModel;
    private User $userModel;
    private RfidCard $rfidCardModel;

    public function __construct()
    {
        $this->employeeModel = new Employee();
        $this->userModel = new User();
        $this->rfidCardModel = new RfidCard();
    }

    /**
     * Main RFID Management page.
     */
    public function index(): void
    {
        Middleware::requireAdmin();

        $this->view('admin/rfid/index', [
            'registeredCards' => $this->rfidCardModel->getAllWithEmployeeDetails(),
            'error' => $_SESSION['rfid_error'] ?? null,
            'success' => $_SESSION['rfid_success'] ?? null,
        ]);

        unset($_SESSION['rfid_error'], $_SESSION['rfid_success']);
    }

    /**
     * Separate RFID assignment page.
     */

    public function assignPage(): void
    {
        Middleware::requireAdmin();

        $this->view('admin/rfid/assign', [
            'employees' => $this->getEmployeesSortedByCode(),
            'admins' => $this->userModel->getAllAdministrators(),
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

        if (!$this->validCsrfToken()) {
            $_SESSION['rfid_error'] =
                'Invalid security token. Please try again.';

            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=rfid-assign'
            );
            return;
        }

        $employeeId = filter_var(
            $_POST['employee_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $userId = filter_var(
            $_POST['user_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $employeeId = (
            $employeeId === false ||
            $employeeId === null ||
            $employeeId === ''
        ) ? 0 : (int) $employeeId;

        $userId = (
            $userId === false ||
            $userId === null ||
            $userId === ''
        ) ? null : (int) $userId;

        $identifier = $_POST['rfid_identifier'] ?? '';

        if (!is_string($identifier)) {
            $identifier = '';
        }

        try {
            $service = new RfidRegistrationService();
            $proxy = new RfidRegistrationProxy($service);

            $result = $proxy->register(
                $employeeId,
                $identifier,
                $userId
            );

            $_SESSION['rfid_success'] = $result['message'];

        } catch (PDOException $exception) {
            error_log(
                'RFID registration failed: ' . $exception->getMessage()
            );

            $_SESSION['rfid_error'] =
                'This RFID card or account may already have an assignment.';

        } catch (InvalidArgumentException | RuntimeException $exception) {
            $_SESSION['rfid_error'] = $exception->getMessage();
        }

        $this->redirect(
            '/HRIS-Management-System/public/index.php?action=rfid-assign'
        );
    }


    /**
     * Unassign an RFID card from an employee.
     */
    public function unassign(): void
    {
        Middleware::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo '405 - Method Not Allowed';
            return;
        }

        if (!$this->validCsrfToken()) {
            $_SESSION['rfid_error'] =
                'Invalid security token. Please try again.';

            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=rfid'
            );
            return;
        }

        $cardId = filter_var(
            $_POST['card_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if ($cardId === false || $cardId === null || $cardId <= 0) {
            $_SESSION['rfid_error'] = 'Invalid RFID assignment.';
            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=rfid'
            );
            return;
        }

        try {
            $card = $this->rfidCardModel->findById($cardId);

            if (!$card) {
                $_SESSION['rfid_error'] =
                    'This RFID assignment no longer exists.';
            } elseif ($this->rfidCardModel->delete($cardId)) {
                $_SESSION['rfid_success'] =
                    'RFID card unassigned successfully. The card is available for reuse.';
            } else {
                $_SESSION['rfid_error'] =
                    'The RFID assignment could not be removed.';
            }
        } catch (PDOException $exception) {
            error_log('RFID unassignment failed: ' . $exception->getMessage());
            $_SESSION['rfid_error'] =
                'A database error occurred while unassigning the card.';
        }

        $this->redirect(
            '/HRIS-Management-System/public/index.php?action=rfid'
        );
    }

    private function validCsrfToken(): bool
    {
        $token = $_POST['csrf_token'] ?? '';

        return is_string($token) && Auth::verifyCsrfToken($token);
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
