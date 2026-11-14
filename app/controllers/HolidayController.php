<?php

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../models/Holiday.php';

class HolidayController extends Controller
{
    private Holiday $holidayModel;

    public function __construct()
    {
        $this->holidayModel = new Holiday();
    }

    public function index(): void
    {
        Middleware::requireAdmin();

        $this->view('admin/holidays/index', [
            'pageTitle' => 'Holiday Management',
            'holidays' => $this->holidayModel->getAll(),
            'success' => $_SESSION['success_message'] ?? null,
            'error' => $_SESSION['error_message'] ?? null,
            'csrfToken' => Auth::csrfToken(),
        ]);

        unset(
            $_SESSION['success_message'],
            $_SESSION['error_message']
        );
    }

    public function store(): void
    {
        Middleware::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }

        if (!Auth::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            exit('Invalid security token. Please refresh and try again.');
        }

        $name = trim($_POST['holiday_name'] ?? '');
        $date = trim($_POST['holiday_date'] ?? '');
        $type = trim($_POST['holiday_type'] ?? '');
        $description = trim($_POST['description'] ?? '');

        $validTypes = ['Organizational', 'Governmental'];

        if (
            $name === '' ||
            !in_array($type, $validTypes, true)
        ) {
            $_SESSION['error_message'] =
                'Please enter a holiday name and select a valid holiday type.';

            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=holidays'
            );
            return;
        }

        $dateObject = DateTime::createFromFormat('!Y-m-d', $date);

        if (
            !$dateObject ||
            $dateObject->format('Y-m-d') !== $date
        ) {
            $_SESSION['error_message'] = 'Please enter a valid holiday date.';

            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=holidays'
            );
            return;
        }

        try {
            $this->holidayModel->create(
                $name,
                $date,
                $type,
                $description !== '' ? $description : null
            );

            $_SESSION['success_message'] = 'Holiday added successfully.';

        } catch (PDOException $e) {
            error_log('Holiday creation failed: ' . $e->getMessage());

            $_SESSION['error_message'] =
                'Holiday already exists. Try with a different date.';
        }

        $this->redirect(
            '/HRIS-Management-System/public/index.php?action=holidays'
        );
    }

    public function delete(): void
    {
        Middleware::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }

        if (!Auth::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            exit('Invalid security token. Please refresh and try again.');
        }

        $id = filter_input(
            INPUT_POST,
            'holiday_id',
            FILTER_VALIDATE_INT
        );

        if (!$id || $id < 1) {
            $_SESSION['error_message'] = 'Invalid holiday selected.';

            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=holidays'
            );
            return;
        }

        if ($this->holidayModel->delete((int) $id)) {
            $_SESSION['success_message'] = 'Holiday deleted successfully.';
        } else {
            $_SESSION['error_message'] =
                'Holiday not found or could not be deleted.';
        }

        $this->redirect(
            '/HRIS-Management-System/public/index.php?action=holidays'
        );
    }
}
