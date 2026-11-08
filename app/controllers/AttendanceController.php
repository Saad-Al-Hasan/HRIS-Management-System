<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../models/WorkSchedule.php';
require_once __DIR__ . '/../models/Attendance.php';
require_once __DIR__ . '/../models/RfidCard.php';
require_once __DIR__ . '/../services/AttendanceService.php';

class AttendanceController extends Controller
{
    public function station(): void
    {
        Middleware::requireAdmin();

        $this->view('admin/attendance/station', [
            'error' => $_SESSION['attendance_error'] ?? null,
            'success' => $_SESSION['attendance_success'] ?? null,
            'attendanceEmployee' => $_SESSION['attendance_employee'] ?? null,
            'attendanceType' => $_SESSION['attendance_type'] ?? null,
        ]);

        unset(
            $_SESSION['attendance_error'],
            $_SESSION['attendance_success'],
            $_SESSION['attendance_employee'],
            $_SESSION['attendance_type']
        );
    }


    public function scan(): void
    {
        Middleware::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=attendance-station'
            );
        }

        $identifier = trim((string) ($_POST['rfid_identifier'] ?? ''));

        if (
            !isset($_SESSION['csrf_token']) ||
            !isset($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], (string) $_POST['csrf_token'])
        ) {
            $_SESSION['attendance_error'] = 'Invalid or expired form token. Refresh the page and try again.';
            $this->redirect(
                '/HRIS-Management-System/public/index.php?action=attendance-station'
            );
        }

        $service = new AttendanceService();
        $result = $service->processScan($identifier);

        if ($result['success']) {
            $_SESSION['attendance_success'] = $result['message'];
            $_SESSION['attendance_employee'] = $result['employee'] ?? null;
            $_SESSION['attendance_type'] = $result['type'] ?? null;

            unset($_SESSION['attendance_error']);
        } else {
            $_SESSION['attendance_error'] = $result['message'];

            unset(
                $_SESSION['attendance_success'],
                $_SESSION['attendance_employee'],
                $_SESSION['attendance_type']
            );
        }

        $this->redirect(
            '/HRIS-Management-System/public/index.php?action=attendance-station'
        );
    }


    /**
     * Display administrative attendance management.
     */
    public function management(): void
    {
        Middleware::requireAdmin();

        $attendanceModel = new Attendance();

        // Read the selected filters.
        $selectedDepartment = trim(
            (string) ($_GET['department'] ?? '')
        );

        $selectedEmployee = trim(
            (string) ($_GET['employee'] ?? '')
        );

        $fromDate = trim(
            (string) ($_GET['from_date'] ?? '')
        );

        $toDate = trim(
            (string) ($_GET['to_date'] ?? '')
        );

        $viewAttendance = isset($_GET['view_attendance']);

        // Initialize page data.
        $departments = $attendanceModel->getAllDepartments();
        $employees = [];
        $attendanceRecords = [];
        $error = null;

        // Validate that the selected department exists.
        $departmentNames = array_column($departments, 'department');

        if (
            $selectedDepartment !== '' &&
            !in_array($selectedDepartment, $departmentNames, true)
        ) {
            $selectedDepartment = '';
            $selectedEmployee = '';
            $fromDate = '';
            $toDate = '';
        }

        // Load employees only for a valid selected department.
        if ($selectedDepartment !== '') {
            $employees = $attendanceModel->getEmployeesByDepartment(
                $selectedDepartment
            );
        }

        // Validate the selected employee against the selected department.
        $validEmployeeIds = array_map(
            'strval',
            array_column($employees, 'id')
        );

        if (
            $selectedEmployee !== '' &&
            (
                !ctype_digit($selectedEmployee) ||
                !in_array($selectedEmployee, $validEmployeeIds, true)
            )
        ) {
            $selectedEmployee = '';
            $fromDate = '';
            $toDate = '';
        }

        // Only search attendance after View Attendance is submitted.
        if ($viewAttendance) {
            if ($selectedDepartment === '') {
                $error = 'Please select a department.';
            } elseif ($selectedEmployee === '') {
                $error = 'Please select an employee.';
            } elseif ($fromDate === '' || $toDate === '') {
                $error = 'Please select both From Date and To Date.';
            } else {
                // Validate date format and actual calendar dates.
                $validateDate = static function (string $date): bool {
                    $parsedDate = DateTimeImmutable::createFromFormat(
                        '!Y-m-d',
                        $date
                    );

                    return $parsedDate !== false
                        && $parsedDate->format('Y-m-d') === $date;
                };

                if (
                    !$validateDate($fromDate) ||
                    !$validateDate($toDate)
                ) {
                    $error = 'Please provide valid dates.';
                } elseif ($fromDate > $toDate) {
                    $error = 'From Date cannot be later than To Date.';
                } else {
                    $attendanceRecords =
                        $attendanceModel->getEmployeeAttendanceByDateRange(
                            (int) $selectedEmployee,
                            $fromDate,
                            $toDate
                        );

                    // Attach the complete RFID scan history to each attendance row.
                    foreach ($attendanceRecords as &$record) {
                        $record['scan_history'] =
                            $attendanceModel->getEmployeeScanHistoryForDate(
                                (int) $record['employee_id'],
                                (string) $record['attendance_date']
                            );
                    }

                    unset($record);
                }
            }
        }

        // Render the page.
        $this->view('admin/attendance/management', [
            'departments' => $departments,
            'employees' => $employees,
            'selectedDepartment' => $selectedDepartment,
            'selectedEmployee' => $selectedEmployee,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'attendanceRecords' => $attendanceRecords,
            'error' => $error,
            'viewAttendance' => $viewAttendance,
        ]);
    }

    /**
     * Display the manual attendance modification form.
     */
    public function modify(): void
    {
        Middleware::requireAdmin();

        $employeeModel = new Employee();

        $employees = $employeeModel->getAll();

        $this->view('admin/attendance/modify', [
            'employees' => $employees,
            'error' => $_SESSION['modify_attendance_error'] ?? null,
            'success' => $_SESSION['modify_attendance_success'] ?? null,
        ]);

        unset(
            $_SESSION['modify_attendance_error'],
            $_SESSION['modify_attendance_success']
        );
    }


    /**
     * Save manually entered attendance.
     */
    public function saveModifiedAttendance(): void
    {
        Middleware::requireAdmin();

        $redirectUrl =
            '/HRIS-Management-System/public/index.php?action=modify-attendance';

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect($redirectUrl);
            return;
        }

        // Validate CSRF token.
        if (
            !isset($_SESSION['csrf_token']) ||
            !isset($_POST['csrf_token']) ||
            !hash_equals(
                $_SESSION['csrf_token'],
                (string) $_POST['csrf_token']
            )
        ) {
            $_SESSION['modify_attendance_error'] =
                'Invalid or expired form token. Please refresh and try again.';

            $this->redirect($redirectUrl);
            return;
        }

        $employeeId = filter_var(
            $_POST['employee_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $attendanceDate = trim(
            (string) ($_POST['attendance_date'] ?? '')
        );

        $entryTimeInput = trim(
            (string) ($_POST['entry_time'] ?? '')
        );

        $exitTimeInput = trim(
            (string) ($_POST['exit_time'] ?? '')
        );

        // Validate employee ID.
        if (!$employeeId || $employeeId < 1) {
            $_SESSION['modify_attendance_error'] =
                'Please select a valid employee.';

            $this->redirect($redirectUrl);
            return;
        }

        // Validate the attendance date.
        $parsedDate = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $attendanceDate
        );

        if (
            $parsedDate === false ||
            $parsedDate->format('Y-m-d') !== $attendanceDate
        ) {
            $_SESSION['modify_attendance_error'] =
                'Please select a valid attendance date.';

            $this->redirect($redirectUrl);
            return;
        }

        // Validate time inputs.
        $validTime = static function (string $time): bool {
            return preg_match(
                '/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/',
                $time
            ) === 1;
        };

        if ($entryTimeInput === '' && $exitTimeInput === '') {
            $_SESSION['modify_attendance_error'] =
                'Enter an entry time, an exit time, or both.';

            $this->redirect($redirectUrl);
            return;
        }

        if ($entryTimeInput !== '' && !$validTime($entryTimeInput)) {
            $_SESSION['modify_attendance_error'] =
                'Please enter a valid entry time.';

            $this->redirect($redirectUrl);
            return;
        }

        if ($exitTimeInput !== '' && !$validTime($exitTimeInput)) {
            $_SESSION['modify_attendance_error'] =
                'Please enter a valid exit time.';

            $this->redirect($redirectUrl);
            return;
        }

        // Convert supplied times to the database datetime format.
        $entryDateTime = null;
        $entryTime = null;
        $exitDateTime = null;
        $exitTime = null;

        if ($entryTimeInput !== '') {
            $entryDateTime = new DateTimeImmutable(
                $attendanceDate . ' ' . $entryTimeInput
            );

            $entryTime = $entryDateTime->format('Y-m-d H:i:s');
        }

        if ($exitTimeInput !== '') {
            $exitDateTime = new DateTimeImmutable(
                $attendanceDate . ' ' . $exitTimeInput
            );

            $exitTime = $exitDateTime->format('Y-m-d H:i:s');
        }

        if (
            $entryDateTime !== null &&
            $exitDateTime !== null &&
            $exitDateTime <= $entryDateTime
        ) {
            $_SESSION['modify_attendance_error'] =
                'Exit time must be later than entry time.';

            $this->redirect($redirectUrl);
            return;
        }

        try {
            // Verify that the selected employee exists.
            $employeeModel = new Employee();

            $employee = $employeeModel->findByEmployeeId(
                (int) $employeeId
            );

            if ($employee === null) {
                $_SESSION['modify_attendance_error'] =
                    'The selected employee could not be found.';

                $this->redirect($redirectUrl);
                return;
            }

            // Retrieve the employee's effective work schedule.
            $workSchedule = new WorkSchedule();

            $schedule = $workSchedule->findEffectiveScheduleForEmployee(
                (int) $employeeId
            );

            if ($schedule === null) {
                $_SESSION['modify_attendance_error'] =
                    'No work schedule is available for this employee.';

                $this->redirect($redirectUrl);
                return;
            }


            // Exit-only modification: update an existing open attendance record.
            if ($entryDateTime === null && $exitDateTime !== null) {
                $attendanceModel = new Attendance();

                $existingRecord = $attendanceModel->findByEmployeeAndDate(
                    (int) $employeeId,
                    $attendanceDate
                );

                if (
                    $existingRecord === null ||
                    empty($existingRecord['entry_time'])
                ) {
                    $_SESSION['modify_attendance_error'] =
                        'No existing entry record was found for this employee and date.';

                    $this->redirect($redirectUrl);
                    return;
                }

                if (!empty($existingRecord['exit_time'])) {
                    $_SESSION['modify_attendance_error'] =
                        'An exit time has already been recorded for this employee and date.';

                    $this->redirect($redirectUrl);
                    return;
                }

                $existingEntryDateTime = new DateTimeImmutable(
                    (string) $existingRecord['entry_time']
                );

                if ($exitDateTime <= $existingEntryDateTime) {
                    $_SESSION['modify_attendance_error'] =
                        'Exit time must be later than the recorded entry time.';

                    $this->redirect($redirectUrl);
                    return;
                }

                $scheduledExit = new DateTimeImmutable(
                    $attendanceDate . ' ' . $schedule['expected_exit_time']
                );

                $exitThreshold = $scheduledExit->modify('-1 hour');

                $exitStatus = ($exitDateTime >= $exitThreshold)
                    ? 'Exit - In Time'
                    : 'Exit - Before';

                $saved = $attendanceModel->recordExitWithHistory(
                    (int) $existingRecord['id'],
                    (int) $employeeId,
                    $attendanceDate,
                    $exitTime,
                    $exitStatus
                );

                if (!$saved) {
                    $_SESSION['modify_attendance_error'] =
                        'Unable to update the existing attendance record.';

                    $this->redirect($redirectUrl);
                    return;
                }

                $_SESSION['modify_attendance_success'] =
                    'Exit time recorded successfully for '
                    . $employee['employee_code']
                    . '. Exit status: '
                    . $exitStatus;

                unset($_SESSION['modify_attendance_error']);

                $this->redirect($redirectUrl);
                return;
            }


            // The remaining path creates a new attendance record,
            // so an entry time must be supplied.
            if ($entryDateTime === null) {
                $_SESSION['modify_attendance_error'] =
                    'An entry time is required when creating a new attendance record.';

                $this->redirect($redirectUrl);
                return;
            }

            // Apply the existing 30-minute entry grace period.
            $scheduledEntry = new DateTimeImmutable(
                $attendanceDate . ' ' . $schedule['expected_entry_time']
            );

            $entryThreshold = $scheduledEntry->modify('+30 minutes');

            $entryStatus = ($entryDateTime > $entryThreshold)
                ? 'Entry - Late'
                : 'Entry - In Time';

            // Preserve the existing legacy attendance status.
            $legacyStatus = ($entryStatus === 'Entry - Late')
                ? 'late'
                : 'present';

            // Apply the existing one-hour exit threshold.
            $exitStatus = null;

            if ($exitTime !== null) {
                $scheduledExit = new DateTimeImmutable(
                    $attendanceDate . ' ' . $schedule['expected_exit_time']
                );

                $exitThreshold = $scheduledExit->modify('-1 hour');

                $exitStatus = ($exitDateTime >= $exitThreshold)
                    ? 'Exit - In Time'
                    : 'Exit - Before';
            }

            // Save attendance and scan history together.
            $attendanceModel = new Attendance();

            $attendanceModel->createManualAttendanceWithHistory(
                (int) $employeeId,
                $attendanceDate,
                $entryTime,
                $entryStatus,
                $legacyStatus,
                $exitTime,
                $exitStatus
            );

            $_SESSION['modify_attendance_success'] =
                'Attendance was recorded successfully for '
                . $employee['employee_code']
                . '. Entry status: '
                . $entryStatus
                . (
                    $exitStatus !== null
                    ? '. Exit status: ' . $exitStatus
                    : '. No exit time was recorded.'
                );

            unset($_SESSION['modify_attendance_error']);

        } catch (InvalidArgumentException $exception) {
            $_SESSION['modify_attendance_error'] =
                $exception->getMessage();

        } catch (Throwable $exception) {
            error_log(
                '[HRIS MANUAL ATTENDANCE ERROR] '
                . $exception->getMessage()
            );

            $_SESSION['modify_attendance_error'] =
                'Unable to save attendance. Please check the details and try again.';
        }

        $this->redirect($redirectUrl);
    }

}
