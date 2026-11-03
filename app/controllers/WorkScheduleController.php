<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../models/WorkSchedule.php';
require_once __DIR__ . '/../models/Employee.php';

class WorkScheduleController extends Controller
{
    private WorkSchedule $workSchedule;
    private Employee $employee;

    public function __construct()
    {
        $this->workSchedule = new WorkSchedule();
        $this->employee = new Employee();
    }

    public function index(): void
    {
        Middleware::requireAdmin();

        $this->view('admin/work-schedules/index', [
            'pageTitle' => 'Work Schedule Management',
            'schedules' => $this->workSchedule->getAll(),
            'success' => $_SESSION['success_message'] ?? null,
            'error' => $_SESSION['error_message'] ?? null,
            'csrfToken' => Auth::csrfToken(),
        ]);

        unset($_SESSION['success_message'], $_SESSION['error_message']);
    }

    public function create(): void
    {
        Middleware::requireAdmin();

        $this->view('admin/work-schedules/form', [
            'pageTitle' => 'Create Work Schedule',
            'schedule' => null,
            'errors' => [],
            'old' => [],
            'csrfToken' => Auth::csrfToken(),
        ]);
    }

    public function store(): void
    {
        Middleware::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }

        if (!Auth::verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
            http_response_code(403);
            exit('Invalid security token. Please refresh and try again.');
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $entryTime = trim((string) ($_POST['expected_entry_time'] ?? ''));
        $exitTime = trim((string) ($_POST['expected_exit_time'] ?? ''));

        $errors = $this->validateSchedule($name, $entryTime, $exitTime);

        if ($errors !== []) {
            $this->view('admin/work-schedules/form', [
                'pageTitle' => 'Create Work Schedule',
                'schedule' => null,
                'errors' => $errors,
                'old' => [
                    'name' => $name,
                    'expected_entry_time' => $entryTime,
                    'expected_exit_time' => $exitTime,
                ],
                'csrfToken' => Auth::csrfToken(),
            ]);
            return;
        }

        try {
            $this->workSchedule->create($name, $entryTime, $exitTime);
            $_SESSION['success_message'] = 'Work schedule created successfully.';
        } catch (InvalidArgumentException $e) {
            $errors = [
                'expected_exit_time' => $e->getMessage(),
            ];

            $this->view('admin/work-schedules/form', [
                'pageTitle' => 'Create Work Schedule',
                'schedule' => null,
                'errors' => $errors,
                'old' => [
                    'name' => $name,
                    'expected_entry_time' => $entryTime,
                    'expected_exit_time' => $exitTime,
                ],
                'csrfToken' => Auth::csrfToken(),
            ]);
            return;
        } catch (PDOException $e) {
            error_log('Work schedule creation failed: ' . $e->getMessage());
            $_SESSION['error_message'] = 'Unable to create the schedule. Check whether its name already exists.';
        }

        $this->redirect('/HRIS-Management-System/public/index.php?action=work-schedules');
    }

    public function edit(): void
    {
        Middleware::requireAdmin();

        $scheduleId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $schedule = $scheduleId ? $this->workSchedule->findById((int) $scheduleId) : null;

        if ($schedule === null) {
            http_response_code(404);
            echo '404 - Work Schedule Not Found';
            return;
        }

        $this->view('admin/work-schedules/form', [
            'pageTitle' => 'Edit Work Schedule',
            'schedule' => $schedule,
            'errors' => [],
            'old' => [],
            'csrfToken' => Auth::csrfToken(),
        ]);
    }

    public function update(): void
    {
        Middleware::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }

        if (!Auth::verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
            http_response_code(403);
            exit('Invalid security token. Please refresh and try again.');
        }

        $scheduleId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $name = trim((string) ($_POST['name'] ?? ''));
        $entryTime = trim((string) ($_POST['expected_entry_time'] ?? ''));
        $exitTime = trim((string) ($_POST['expected_exit_time'] ?? ''));

        if (!$scheduleId || $this->workSchedule->findById((int) $scheduleId) === null) {
            http_response_code(404);
            echo '404 - Work Schedule Not Found';
            return;
        }

        $errors = $this->validateSchedule($name, $entryTime, $exitTime);

        if ($errors !== []) {
            $schedule = [
                'id' => (int) $scheduleId,
                'name' => $name,
                'expected_entry_time' => $entryTime,
                'expected_exit_time' => $exitTime,
            ];

            $this->view('admin/work-schedules/form', [
                'pageTitle' => 'Edit Work Schedule',
                'schedule' => $schedule,
                'errors' => $errors,
                'old' => [
                    'name' => $name,
                    'expected_entry_time' => $entryTime,
                    'expected_exit_time' => $exitTime,
                ],
                'csrfToken' => Auth::csrfToken(),
            ]);
            return;
        }

        try {
            $this->workSchedule->update(
                (int) $scheduleId,
                $name,
                $entryTime,
                $exitTime
            );
            $_SESSION['success_message'] = 'Work schedule updated successfully.';
        } catch (InvalidArgumentException $e) {
            $this->view('admin/work-schedules/form', [
                'pageTitle' => 'Edit Work Schedule',
                'schedule' => [
                    'id' => (int) $scheduleId,
                    'name' => $name,
                    'expected_entry_time' => $entryTime,
                    'expected_exit_time' => $exitTime,
                ],
                'errors' => [
                    'expected_exit_time' => $e->getMessage(),
                ],
                'old' => [
                    'name' => $name,
                    'expected_entry_time' => $entryTime,
                    'expected_exit_time' => $exitTime,
                ],
                'csrfToken' => Auth::csrfToken(),
            ]);
            return;
        } catch (PDOException $e) {
            error_log('Work schedule update failed: ' . $e->getMessage());
            $_SESSION['error_message'] = 'Unable to update the schedule. Check whether its name already exists.';
        }

        $this->redirect('/HRIS-Management-System/public/index.php?action=work-schedules');
    }

    public function assignments(): void
    {
        Middleware::requireAdmin();

        $this->view('admin/work-schedules/assignments', [
            'pageTitle' => 'Employee Schedule Assignments',
            'employees' => $this->workSchedule->getEmployeesWithSchedules(),
            'schedules' => $this->workSchedule->getAll(),
            'success' => $_SESSION['success_message'] ?? null,
            'error' => $_SESSION['error_message'] ?? null,
            'csrfToken' => Auth::csrfToken(),
        ]);

        unset($_SESSION['success_message'], $_SESSION['error_message']);
    }

    public function assign(): void
    {
        Middleware::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }

        if (!Auth::verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
            http_response_code(403);
            exit('Invalid security token. Please refresh and try again.');
        }

        $employeeId = filter_var($_POST['employee_id'] ?? null, FILTER_VALIDATE_INT);
        $scheduleId = filter_var($_POST['schedule_id'] ?? null, FILTER_VALIDATE_INT);

        try {
            $assigned = $employeeId && $scheduleId
                ? $this->workSchedule->assignToEmployee(
                    (int) $employeeId,
                    (int) $scheduleId
                )
                : false;

            $_SESSION[$assigned ? 'success_message' : 'error_message'] = $assigned
                ? 'Employee schedule assigned successfully.'
                : 'Unable to assign that schedule. Verify the employee and schedule.';
        } catch (PDOException $e) {
            error_log('Employee schedule assignment failed: ' . $e->getMessage());
            $_SESSION['error_message'] = 'Unable to save the employee schedule assignment.';
        }

        $this->redirect('/HRIS-Management-System/public/index.php?action=work-schedule-assignments');
    }


    public function delete(): void
    {
        Middleware::requireAdmin();

        // Deletion must use POST.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }

        // Verify CSRF token.
        if (
            !Auth::verifyCsrfToken(
                (string) ($_POST['csrf_token'] ?? '')
            )
        ) {
            http_response_code(403);
            exit('Invalid security token. Please refresh and try again.');
        }

        $scheduleId = filter_var(
            $_POST['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$scheduleId || $scheduleId < 1) {
            $_SESSION['error_message'] = 'Invalid work schedule.';
            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=work-schedules'
            );
            return;
        }

        try {
            $deleted = $this->workSchedule->delete((int) $scheduleId);

            $_SESSION[$deleted ? 'success_message' : 'error_message'] = $deleted
                ? 'Work schedule deleted successfully.'
                : 'Work schedule not found.';
        } catch (InvalidArgumentException $e) {
            $_SESSION['error_message'] = $e->getMessage();
        } catch (PDOException $e) {
            error_log('Work schedule deletion failed: ' . $e->getMessage());
            $_SESSION['error_message'] =
                'Unable to delete this work schedule because it may be in use.';
        }

        $this->redirect(
            '/HRIS-Management-System/public/index.php?action=work-schedules'
        );
    }



    public function assignAllEmployees(): void
    {
        Middleware::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }

        if (!Auth::verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
            http_response_code(403);
            exit('Invalid security token. Please refresh and try again.');
        }

        $scheduleId = filter_var(
            $_POST['schedule_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (!$scheduleId || $scheduleId < 1) {
            $_SESSION['error_message'] = 'Please select a valid work schedule.';
            $this->redirect('/HRIS-Management-System/public/index.php?action=work-schedules');
            return;
        }

        try {
            $employeeCount = $this->workSchedule->assignAllEmployeesToSchedule((int) $scheduleId);

            $_SESSION['success_message'] = sprintf(
                'Successfully assigned %d employee(s) to the selected shift.',
                $employeeCount
            );
        } catch (InvalidArgumentException $e) {
            $_SESSION['error_message'] = $e->getMessage();
        } catch (Throwable $e) {
            error_log('Bulk employee schedule assignment failed: ' . $e->getMessage());
            $_SESSION['error_message'] =
                'Unable to update all employee schedules. No partial changes were saved.';
        }

        $this->redirect('/HRIS-Management-System/public/index.php?action=work-schedules');
    }



    private function validateSchedule(
        string $name,
        string $entryTime,
        string $exitTime
    ): array {
        $errors = [];

        if ($name === '') {
            $errors['name'] = 'Schedule name is required.';
        } elseif (mb_strlen($name) > 100) {
            $errors['name'] = 'Schedule name must not exceed 100 characters.';
        }

        $validEntry = $this->validTime($entryTime);
        $validExit = $this->validTime($exitTime);

        if (!$validEntry) {
            $errors['expected_entry_time'] = 'Enter a valid entry time.';
        }

        if (!$validExit) {
            $errors['expected_exit_time'] = 'Enter a valid exit time.';
        }

        if ($validEntry && $validExit) {
            $entryParts = array_map('intval', explode(':', $entryTime));
            $exitParts = array_map('intval', explode(':', $exitTime));

            $entrySeconds = ($entryParts[0] * 3600)
                + ($entryParts[1] * 60)
                + ($entryParts[2] ?? 0);

            $exitSeconds = ($exitParts[0] * 3600)
                + ($exitParts[1] * 60)
                + ($exitParts[2] ?? 0);

            if ($exitSeconds <= $entrySeconds) {
                $errors['expected_exit_time'] =
                    'Exit time must be later than entry time. Overnight schedules are not supported.';
            } elseif (($exitSeconds - $entrySeconds) > (9 * 3600)) {
                $errors['expected_exit_time'] =
                    'A work schedule cannot exceed 9 working hours.';
            }
        }

        return $errors;
    }

    private function validTime(string $time): bool
    {
        return preg_match(
            '/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/',
            $time
        ) === 1;
    }
}
