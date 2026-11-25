<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../core/SimpleXlsx.php';
require_once __DIR__ . '/../models/LeaveReport.php';
require_once __DIR__ . '/../models/SalaryReport.php';

/**
 * Reports & Excel Export (US-11)
 *
 * Tabs: attendance | employee | leave | salary
 * Leave and Salary tabs are implemented here.
 * Attendance and Employee tabs are placeholders for the teammates'
 * partials (views/admin/reports/_attendance.php and _employee.php).
 */
class ReportController extends Controller
{
    private const TABS = ['attendance', 'employee', 'leave', 'salary'];

    private LeaveReport $leaveReportModel;
    private SalaryReport $salaryReportModel;

    public function __construct()
    {
        $this->leaveReportModel = new LeaveReport();
        $this->salaryReportModel = new SalaryReport();
    }

    /**
     * Reports page with its four tabs.
     */
    public function index(): void
    {
        Middleware::requireAdmin();

        $tab = (string) ($_GET['tab'] ?? 'attendance');

        if (!in_array($tab, self::TABS, true)) {
            $tab = 'attendance';
        }

        $data = [
            'pageTitle' => 'Reports',
            'activeTab' => $tab,
        ];

        try {
            if ($tab === 'leave') {
                $filters = LeaveReport::normalizeFilters($_GET);
                $rows = $this->leaveReportModel->getReport($filters);

                $data['leaveFilters'] = $filters;
                $data['leaveRows'] = $rows;
                $data['leaveSummary'] = LeaveReport::summarize($rows);
                $data['error'] = $filters['error'];
            }

            if ($tab === 'salary') {
                $filters = SalaryReport::normalizeFilters($_GET);
                $rows = $this->salaryReportModel->getReport($filters);

                $data['salaryFilters'] = $filters;
                $data['salaryRows'] = $rows;
                $data['salarySummary'] = SalaryReport::summarize($rows);
                $data['error'] = $filters['error'];
            }
        } catch (Throwable $e) {
            error_log('Report error: ' . $e->getMessage());
            $data['error'] = 'Unable to load the report. Please try again.';
        }

        $this->view('admin/reports/index', $data);
    }

    /**
     * Download the Leave Report (same filters as the tab) as Excel.
     */
    public function exportLeave(): void
    {
        Middleware::requireAdmin();

        $filters = LeaveReport::normalizeFilters($_GET);

        try {
            $rows = $this->leaveReportModel->getReport($filters);
        } catch (Throwable $e) {
            error_log('Leave report export error: ' . $e->getMessage());
            http_response_code(500);
            die('Unable to export the leave report.');
        }

        $summary = LeaveReport::summarize($rows);

        $filterParts = [];

        if ($filters['employee_code'] !== '') {
            $filterParts[] = 'Employee: ' . $filters['employee_code'];
        }
        if ($filters['status'] !== '') {
            $filterParts[] = 'Status: ' . LeaveReport::statusLabel($filters['status']);
        }
        if ($filters['leave_type'] !== '') {
            $filterParts[] = 'Type: ' . $filters['leave_type'];
        }
        if ($filters['from'] !== '') {
            $filterParts[] = 'From: ' . date('d M Y', strtotime($filters['from']));
        }
        if ($filters['to'] !== '') {
            $filterParts[] = 'To: ' . date('d M Y', strtotime($filters['to']));
        }

        $meta = [
            'Leave Report - MYOL HRIS',
            'Generated: ' . date('d M Y, h:i A'),
            'Filters: ' . ($filterParts ? implode(' | ', $filterParts) : 'None (all leave applications)'),
            sprintf(
                'Total: %d | Pending: %d | Accepted: %d | Rejected: %d | Accepted leave days: %s',
                $summary['total'],
                $summary['pending'],
                $summary['approved'],
                $summary['rejected'],
                rtrim(rtrim(number_format($summary['approved_days'], 1, '.', ''), '0'), '.')
            ),
        ];

        $headers = [
            '#', 'Employee Code', 'Employee Name', 'Department', 'Leave Type',
            'Period', 'Start Date', 'End Date', 'Days', 'Reason', 'Status',
            'Applied On', 'Reviewed By', 'Reviewed On',
        ];

        $data = [];

        foreach ($rows as $i => $row) {
            $data[] = [
                $i + 1,
                (string) $row['employee_code'],
                (string) $row['full_name'],
                (string) ($row['department'] ?? ''),
                (string) $row['leave_type'],
                LeaveReport::periodLabel($row),
                date('d M Y', strtotime((string) $row['start_date'])),
                date('d M Y', strtotime((string) $row['end_date'])),
                (float) $row['leave_days'],
                (string) ($row['reason'] ?? ''),
                LeaveReport::statusLabel((string) $row['status']),
                !empty($row['created_at']) ? date('d M Y', strtotime((string) $row['created_at'])) : '',
                (string) ($row['reviewed_by_name'] ?? ''),
                !empty($row['reviewed_at']) ? date('d M Y, h:i A', strtotime((string) $row['reviewed_at'])) : '',
            ];
        }

        SimpleXlsx::download(
            'leave_report_' . date('Y-m-d'),
            'Leave Report',
            $meta,
            $headers,
            $data,
            [],
            null,
            [5, 16, 24, 18, 12, 12, 14, 14, 8, 36, 12, 14, 22, 20]
        );
    }

    /**
     * Download the Salary Report (same filters as the tab) as Excel.
     */
    public function exportSalary(): void
    {
        Middleware::requireAdmin();

        $filters = SalaryReport::normalizeFilters($_GET);

        try {
            $rows = $this->salaryReportModel->getReport($filters);
        } catch (Throwable $e) {
            error_log('Salary report export error: ' . $e->getMessage());
            http_response_code(500);
            die('Unable to export the salary report.');
        }

        $summary = SalaryReport::summarize($rows);

        $filterParts = [];

        $filterParts[] = $filters['month'] !== ''
            ? 'Month: ' . date('F Y', strtotime($filters['month'] . '-01'))
            : 'Month: All months';

        if ($filters['employee_code'] !== '') {
            $filterParts[] = 'Employee: ' . $filters['employee_code'];
        }

        $meta = [
            'Salary Report - MYOL HRIS',
            'Generated: ' . date('d M Y, h:i A'),
            'Filters: ' . implode(' | ', $filterParts),
            'Records: ' . $summary['records'],
        ];

        $headers = [
            '#', 'Employee Code', 'Employee Name', 'Department', 'Salary Month',
            'Basic Salary', 'Absence Deduction', 'Half-Day Deduction',
            'Late/Early Deduction', 'Total Deductions', 'Final Salary',
            'Calculated On',
        ];

        $data = [];

        foreach ($rows as $i => $row) {
            $data[] = [
                $i + 1,
                (string) $row['employee_code'],
                (string) $row['full_name'],
                (string) ($row['department'] ?? ''),
                date('F Y', strtotime((string) $row['salary_month'])),
                (float) $row['basic_salary'],
                (float) $row['absence_deduction'],
                (float) $row['half_day_deduction'],
                (float) $row['late_early_deduction'],
                (float) $row['deductions'],
                (float) $row['final_salary'],
                !empty($row['calculated_at']) ? date('d M Y, h:i A', strtotime((string) $row['calculated_at'])) : '',
            ];
        }

        $totals = [
            '', '', '', '', 'TOTAL',
            (float) $summary['basic_salary'],
            (float) $summary['absence_deduction'],
            (float) $summary['half_day_deduction'],
            (float) $summary['late_early_deduction'],
            (float) $summary['deductions'],
            (float) $summary['final_salary'],
            '',
        ];

        SimpleXlsx::download(
            'salary_report_' . ($filters['month'] !== '' ? $filters['month'] : 'all') . '_' . date('Ymd'),
            'Salary Report',
            $meta,
            $headers,
            $data,
            [5, 6, 7, 8, 9, 10],
            $rows ? $totals : null,
            [5, 16, 24, 18, 16, 16, 18, 18, 20, 18, 16, 20]
        );
    }
}
