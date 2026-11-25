<?php

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../models/Report.php';

class ReportsController extends Controller
{
    private Report $report;

    public function __construct()
    {
        $this->report = new Report();
    }

    /**
     * Main Attendance Report page.
     */
    public function attendance(): void
    {
        Middleware::requireAdmin();

        $tab = trim((string) ($_GET['tab'] ?? 'attendance'));

        // Leave and Salary reports belong to another team member.
        if (in_array($tab, ['leave', 'salary'], true)) {
            http_response_code(404);
            exit(
                'This report is handled by another module and is not available here.'
            );
        }

        if (in_array($tab, ['employee', 'employees'], true)) {
            $this->employees();
            return;
        }

        $filters = $this->getAttendanceFilters();

        $departments = $this->report->getDepartments();

        // Reject a department that does not exist.
        if (
            $filters['department'] !== '' &&
            !in_array($filters['department'], $departments, true)
        ) {
            $filters['department'] = '';
        }

        // Load all employees.
        // JavaScript filters the dropdown without reloading the page.
        $employees = $this->report->getEmployeeOptions();

        // Validate selected employee against the selected department.
        if ($filters['employee'] !== '') {
            $selectedEmployee = null;

            foreach ($employees as $employee) {
                if (
                    (string) $employee['id'] ===
                    (string) $filters['employee']
                ) {
                    $selectedEmployee = $employee;
                    break;
                }
            }

            if (
                $selectedEmployee === null ||
                (
                    $filters['department'] !== '' &&
                    (string) ($selectedEmployee['department'] ?? '') !==
                    $filters['department']
                )
            ) {
                $filters['employee'] = '';
            }
        }

        $records = $this->report->getAttendanceReport($filters);

        $this->view('admin/reports/attendance', [
            'pageTitle' => 'Reports',
            'filters' => $filters,
            'currentTab' => 'attendance',
            'departments' => $departments,
            'employees' => $employees,
            'records' => $records,
        ]);
    }

    /**
     * Employee Report.
     */
    public function employees(): void
    {
        Middleware::requireAdmin();

        $filters = [
            'department' => trim(
                (string) ($_GET['department'] ?? '')
            ),
            'designation' => trim(
                (string) ($_GET['designation'] ?? '')
            ),
            'status' => trim(
                (string) ($_GET['status'] ?? '')
            ),
            'name' => trim(
                (string) ($_GET['name'] ?? '')
            ),
            'employee_code' => trim(
                (string) ($_GET['employee_code'] ?? '')
            ),
        ];

        if (
            !in_array(
                $filters['status'],
                ['', 'active', 'inactive'],
                true
            )
        ) {
            $filters['status'] = '';
        }

        $this->view('admin/reports/employees', [
            'pageTitle' => 'Reports',
            'filters' => $filters,
            'currentTab' => 'employee',
            'departments' => $this->report->getDepartments(),
            'designations' => $this->report->getDesignations(),
            'records' => $this->report->getEmployeeReport($filters),
        ]);
    }

    /**
     * Export Attendance Report.
     */
    public function exportAttendance(): void
    {
        Middleware::requireAdmin();

        $filters = $this->getAttendanceFilters();

        $departments = $this->report->getDepartments();

        if (
            $filters['department'] !== '' &&
            !in_array($filters['department'], $departments, true)
        ) {
            $filters['department'] = '';
        }

        // Validate employee and department combination for export too.
        if ($filters['employee'] !== '') {
            $employees = $this->report->getEmployeeOptions();
            $selectedEmployee = null;

            foreach ($employees as $employee) {
                if (
                    (string) $employee['id'] ===
                    (string) $filters['employee']
                ) {
                    $selectedEmployee = $employee;
                    break;
                }
            }

            if (
                $selectedEmployee === null ||
                (
                    $filters['department'] !== '' &&
                    (string) ($selectedEmployee['department'] ?? '') !==
                    $filters['department']
                )
            ) {
                $filters['employee'] = '';
            }
        }

        $records = $this->report->getAttendanceReport($filters);

        $this->exportExcel(
            'attendance_report.xls',
            [
                'Employee ID',
                'Name',
                'Department',
                'Date',
                'Entry Time',
                'Entry Status',
                'Exit Time',
                'Exit Status',
                'Attendance Status',
            ],
            $records,
            [
                'employee_id',
                'full_name',
                'department',
                'attendance_date',
                'entry_time',
                'entry_status',
                'exit_time',
                'exit_status',
                'attendance_status',
            ]
        );
    }

    /**
     * Export Employee Report.
     */
    public function exportEmployees(): void
    {
        Middleware::requireAdmin();

        $filters = [
            'department' => trim(
                (string) ($_GET['department'] ?? '')
            ),
            'designation' => trim(
                (string) ($_GET['designation'] ?? '')
            ),
            'status' => trim(
                (string) ($_GET['status'] ?? '')
            ),
            'name' => trim(
                (string) ($_GET['name'] ?? '')
            ),
            'employee_code' => trim(
                (string) ($_GET['employee_code'] ?? '')
            ),
        ];

        if (
            !in_array(
                $filters['status'],
                ['', 'active', 'inactive'],
                true
            )
        ) {
            $filters['status'] = '';
        }

        $records = $this->report->getEmployeeReport($filters);

        $this->exportExcel(
            'employee_report.xls',
            [
                'Employee Code',
                'Full Name',
                'Username',
                'Department',
                'Designation',
                'Phone',
                'Email',
                'Joining Date',
                'Status',
            ],
            $records,
            [
                'employee_code',
                'full_name',
                'username',
                'department',
                'designation',
                'phone',
                'email',
                'joining_date',
                'status',
            ]
        );
    }

    /**
     * Read and validate Attendance Report filters.
     */
    private function getAttendanceFilters(): array
    {
        $filters = [
            'department' => trim(
                (string) ($_GET['department'] ?? '')
            ),
            'employee' => trim(
                (string) ($_GET['employee'] ?? '')
            ),
            'from_date' => trim(
                (string) ($_GET['from_date'] ?? '')
            ),
            'to_date' => trim(
                (string) ($_GET['to_date'] ?? '')
            ),
            'status' => trim(
                (string) ($_GET['status'] ?? '')
            ),
        ];

        if (
            $filters['employee'] !== '' &&
            (
                !ctype_digit($filters['employee']) ||
                (int) $filters['employee'] <= 0
            )
        ) {
            $filters['employee'] = '';
        }

        foreach (['from_date', 'to_date'] as $dateField) {
            if ($filters[$dateField] !== '') {
                $date = DateTime::createFromFormat(
                    '!Y-m-d',
                    $filters[$dateField]
                );

                if (
                    !$date ||
                    $date->format('Y-m-d') !== $filters[$dateField]
                ) {
                    $filters[$dateField] = '';
                }
            }
        }

        // Invalid date range: clear both dates.
        if (
            $filters['from_date'] !== '' &&
            $filters['to_date'] !== '' &&
            $filters['from_date'] > $filters['to_date']
        ) {
            $filters['from_date'] = '';
            $filters['to_date'] = '';
        }

        $allowedStatuses = [
            '',
            'present',
            'late',
            'absent',
            'leave',
            'holiday',
        ];

        if (
            !in_array(
                $filters['status'],
                $allowedStatuses,
                true
            )
        ) {
            $filters['status'] = '';
        }

        return $filters;
    }

    /**
     * Excel-compatible export.
     */
    private function exportExcel(
        string $filename,
        array $headers,
        array $records,
        array $fields
    ): void {
        if (headers_sent()) {
            exit('Unable to export report.');
        }

        header(
            'Content-Type: application/vnd.ms-excel; charset=UTF-8'
        );

        header(
            'Content-Disposition: attachment; filename="' .
            $filename .
            '"'
        );

        header('Cache-Control: max-age=0');

        echo "\xEF\xBB\xBF";
        echo '<table border="1">';
        echo '<thead><tr>';

        foreach ($headers as $header) {
            echo '<th>' . htmlspecialchars(
                $header,
                ENT_QUOTES,
                'UTF-8'
            ) . '</th>';
        }

        echo '</tr></thead><tbody>';

        foreach ($records as $record) {
            echo '<tr>';

            foreach ($fields as $field) {
                $value = (string) ($record[$field] ?? '');

                // Prevent spreadsheet formula injection.
                if (
                    $value !== '' &&
                    in_array(
                        $value[0],
                        ['=', '+', '-', '@'],
                        true
                    )
                ) {
                    $value = "'" . $value;
                }

                echo '<td>' . htmlspecialchars(
                    $value,
                    ENT_QUOTES,
                    'UTF-8'
                ) . '</td>';
            }

            echo '</tr>';
        }

        echo '</tbody></table>';
        exit;
    }
}