<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../models/Holiday.php';

class HolidayController extends Controller
{
    private Holiday $holiday;

    public function __construct()
    {
        $this->holiday = new Holiday();
    }

    /**
     * Display all organizational holidays or filter by date.
     */
    public function index(): void
    {
        Middleware::requireAdmin();

        $filterDate = trim((string) ($_GET['holiday_date'] ?? ''));

        // Validate the selected date.
        if ($filterDate !== '') {
            $dateObject = DateTime::createFromFormat(
                '!Y-m-d',
                $filterDate
            );

            if (
                !$dateObject ||
                $dateObject->format('Y-m-d') !== $filterDate
            ) {
                $filterDate = '';

                $_SESSION['error_message'] =
                    'Please select a valid holiday date.';
            }
        }

        $this->view('admin/holidays/index', [
            'pageTitle' => 'Holiday Management',
            'holidays' => $this->holiday->getAll(
                $filterDate !== '' ? $filterDate : null
            ),
            'filterDate' => $filterDate,
            'success' => $_SESSION['success_message'] ?? null,
            'error' => $_SESSION['error_message'] ?? null,
            'csrfToken' => Auth::csrfToken(),
        ]);

        unset(
            $_SESSION['success_message'],
            $_SESSION['error_message']
        );
    }

    /**
     * Store a new holiday.
     */
    public function store(): void
    {
        Middleware::requireAdmin();

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

        $holidayName = trim((string) ($_POST['holiday_name'] ?? ''));
        $holidayDate = trim((string) ($_POST['holiday_date'] ?? ''));
        $holidayType = trim((string) ($_POST['holiday_type'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));

        // Validate holiday name.
        if (
            $holidayName === '' ||
            mb_strlen($holidayName) > 100
        ) {
            $_SESSION['error_message'] =
                'Holiday name is required and must not exceed 100 characters.';

            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=holidays'
            );
        }

        // Validate date format.
        $dateObject = DateTime::createFromFormat('!Y-m-d', $holidayDate);

        if (
            !$dateObject ||
            $dateObject->format('Y-m-d') !== $holidayDate
        ) {
            $_SESSION['error_message'] = 'Please select a valid holiday date.';

            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=holidays'
            );
        }

        // Validate holiday type.
        $allowedTypes = [
            'Government',
            'Organizational'
        ];

        if (!in_array($holidayType, $allowedTypes, true)) {
            $_SESSION['error_message'] = 'Please select a valid holiday type.';

            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=holidays'
            );
        }

        // Validate description length.
        if (mb_strlen($description) > 65535) {
            $_SESSION['error_message'] = 'The description is too long.';

            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=holidays'
            );
        }

        // Prevent duplicate holiday dates.
        if ($this->holiday->existsOnDate($holidayDate)) {
            $_SESSION['error_message'] =
                'A holiday already exists for the selected date.';

            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=holidays'
            );
        }

        try {
            $this->holiday->create(
                $holidayName,
                $holidayDate,
                $holidayType,
                $description !== '' ? $description : null
            );

            $_SESSION['success_message'] =
                'Holiday added successfully.';

        } catch (PDOException $e) {

            // Also handles a duplicate date inserted concurrently.
            if ($e->getCode() === '23000') {
                $_SESSION['error_message'] =
                    'A holiday already exists for the selected date.';
            } else {
                $_SESSION['error_message'] =
                    'Unable to add the holiday. Please try again.';
            }
        }

        $this->redirect(
            '/HRIS-Management-System/public/index.php?action=holidays'
        );
    }

    /**
     * Delete an existing holiday.
     */
    public function delete(): void
    {
        Middleware::requireAdmin();

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

        $holidayId = filter_var(
            $_POST['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$holidayId || $holidayId <= 0) {
            $_SESSION['error_message'] = 'Invalid holiday selected.';

            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=holidays'
            );
        }

        try {
            $deleted = $this->holiday->delete((int) $holidayId);

            $_SESSION[$deleted ? 'success_message' : 'error_message'] =
                $deleted
                ? 'Holiday deleted successfully.'
                : 'Unable to delete the selected holiday.';

        } catch (PDOException $e) {
            $_SESSION['error_message'] =
                'Unable to delete the holiday. Please try again.';
        }

        $this->redirect(
            '/HRIS-Management-System/public/index.php?action=holidays'
        );
    }
}
