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

}
