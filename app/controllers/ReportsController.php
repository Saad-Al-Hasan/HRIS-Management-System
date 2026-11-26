<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/Middleware.php';
require_once __DIR__ . '/../models/Report.php';

class ReportsController extends Controller
{
    private Report $report;

    private const REPORT_TYPES = [
        'attendance',
        'employees',
        'leave',
        'salary',
    ];

    public function __construct()
    {
        $this->report = new Report();
    }

    /**
     * Display one of the four administrative reports.
     *
     * This controller is read-only. It does not create or recalculate
     * attendance, leave, employee, or salary records.
     */
    public function index(): void
    {
        Middleware::requireAdmin();

        $reportType = strtolower(trim((string) ($_GET['report'] ?? 'attendance')));

        if (!in_array($reportType, self::REPORT_TYPES, true)) {
            $reportType = 'attendance';
        }

        $filters = $this->readFilters($reportType);
        $rows = [];
        $summary = [];

        $attendanceCharts = [];
        $employeeCharts = [];
        $leaveCharts = [];
        $salaryCharts = [];

        switch ($reportType) {
            case 'attendance':
                $rows = $this->report->getAttendanceReport($filters, 500, 0);
                $summary = $this->report->getAttendanceSummary($filters);
                $attendanceCharts = $this->report->getAttendanceChartData($filters);
                $pageTitle = 'Attendance Report';
                break;

            case 'employees':
                $rows = $this->report->getEmployeeReport($filters, 500, 0);
                $summary = [
                    'total_employees' => count($rows),
                    'active_employees' => count(array_filter(
                        $rows,
                        static fn(array $row): bool => ($row['employee_status'] ?? '') === 'active'
                    )),
                    'inactive_employees' => count(array_filter(
                        $rows,
                        static fn(array $row): bool => ($row['employee_status'] ?? '') === 'inactive'
                    )),
                ];
                $employeeCharts = $this->report->getEmployeeChartData($filters);
                $pageTitle = 'Employee Report';
                break;

            case 'leave':
                $rows = $this->report->getLeaveReport($filters, 500, 0);
                $summary = $this->report->getLeaveSummary($filters);
                $leaveCharts = $this->report->getLeaveChartData($filters);
                $pageTitle = 'Leave Report';
                break;

            case 'salary':
                $rows = $this->report->getSalaryReport($filters, 500, 0);
                $summary = $this->report->getSalarySummary($filters);
                $salaryCharts = $this->report->getSalaryChartData($filters);
                $pageTitle = 'Salary Report';
                break;

            default:
                // The report type is validated above; this is defensive.
                $pageTitle = 'Reports';
                break;
        }

        $this->view('admin/reports/index', [
            'pageTitle' => $pageTitle,
            'reportType' => $reportType,
            'filters' => $filters,
            'rows' => $rows,
            'summary' => $summary,
            'attendanceCharts' => $attendanceCharts,
            'employeeCharts' => $employeeCharts,
            'leaveCharts' => $leaveCharts,
            'salaryCharts' => $salaryCharts,
            'employees' => $this->report->getEmployeeOptions(),
            'departments' => $this->report->getDepartmentOptions(),
            'designations' => $this->report->getDesignationOptions(),
        ]);
    }




    /**
     * Export all matching records from the selected administrative report.
     */
    public function export(): void
    {
        Middleware::requireAdmin();

        $reportType = strtolower(trim((string) ($_GET['report'] ?? '')));

        if (!in_array($reportType, self::REPORT_TYPES, true)) {
            http_response_code(400);
            exit('Invalid export type.');
        }

        require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

        $filters = $this->readFilters($reportType);

        // Analytics data for the Excel report.
        $chartData = [];

        // Summary information displayed at the top of the workbook.
        $reportSummary = [];

        // Retrieve every matching record for the selected report.
        switch ($reportType) {
            case 'attendance':
                $chartData = $this->report->getAttendanceChartData($filters);
                $reportSummary = $this->report->getAttendanceSummary($filters);
                $rows = $this->report->getAttendanceReport($filters, null, 0);
                $sheetTitle = 'Attendance Report';
                $filenamePrefix = 'attendance_report';

                $headers = [
                    'Attendance ID',
                    'Employee ID',
                    'Employee Code',
                    'Employee Name',
                    'Department',
                    'Attendance Date',
                    'Entry Time',
                    'Entry Status',
                    'Exit Time',
                    'Exit Status',
                    'Overall Status',
                    'Record Source',
                ];

                $statusColumn = 'K';
                $numericColumns = [];

                $data = array_map(static fn(array $r): array => [
                    $r['attendance_id'] ?? '',
                    $r['employee_id'] ?? '',
                    $r['employee_code'] ?? '',
                    $r['full_name'] ?? '',
                    $r['department'] ?? '',
                    $r['attendance_date'] ?? '',
                    $r['entry_time'] ?? '',
                    $r['entry_status'] ?? '',
                    $r['exit_time'] ?? '',
                    $r['exit_status'] ?? '',
                    $r['attendance_status'] ?? '',
                    $r['record_source'] ?? '',
                ], $rows);
                break;

            case 'employees':
                $chartData = $this->report->getEmployeeChartData($filters);

                $reportSummary = [
                    'total_employees' => $chartData['total_employees'] ?? 0,
                    'active_employees' => $chartData['status_counts']['active'] ?? 0,
                    'inactive_employees' => $chartData['status_counts']['inactive'] ?? 0,
                ];
                $rows = $this->report->getEmployeeReport($filters, null, 0);
                $sheetTitle = 'Employee Report';
                $filenamePrefix = 'employee_report';

                $headers = [
                    'Employee ID',
                    'User ID',
                    'Employee Code',
                    'Employee Name',
                    'Username',
                    'Email',
                    'Phone',
                    'Department',
                    'Designation',
                    'Joining Date',
                    'Account Status',
                    'Monthly Salary',
                    'Work Schedule',
                    'Expected Entry',
                    'Expected Exit',
                    'Working Hours',
                ];

                $statusColumn = 'K';
                $numericColumns = ['L'];

                $data = array_map(static fn(array $r): array => [
                    $r['employee_id'] ?? '',
                    $r['user_id'] ?? '',
                    $r['employee_code'] ?? '',
                    $r['full_name'] ?? '',
                    $r['username'] ?? '',
                    $r['email'] ?? '',
                    $r['phone'] ?? '',
                    $r['department'] ?? '',
                    $r['designation'] ?? '',
                    $r['joining_date'] ?? '',
                    ucfirst((string) ($r['employee_status'] ?? '')),
                    $r['monthly_salary'] ?? 0,
                    $r['schedule_name'] ?? 'Not Assigned',
                    $r['expected_entry_time'] ?? '',
                    $r['expected_exit_time'] ?? '',
                    $r['working_hours'] ?? '',
                ], $rows);
                break;

            case 'leave':
                $chartData = $this->report->getLeaveChartData($filters);
                $reportSummary = $this->report->getLeaveSummary($filters);
                $rows = $this->report->getLeaveReport($filters, null, 0);
                $sheetTitle = 'Leave Report';
                $filenamePrefix = 'leave_report';

                $headers = [
                    'Request ID',
                    'Employee ID',
                    'Employee Code',
                    'Employee Name',
                    'Department',
                    'Leave Type',
                    'Half-Day Period',
                    'Start Date',
                    'End Date',
                    'Reason',
                    'Status',
                    'Reviewed By',
                    'Reviewed At',
                    'Submitted At',
                ];

                $statusColumn = 'K';
                $numericColumns = [];

                $data = array_map(static fn(array $r): array => [
                    $r['leave_request_id'] ?? '',
                    $r['employee_id'] ?? '',
                    $r['employee_code'] ?? '',
                    $r['full_name'] ?? '',
                    $r['department'] ?? '',
                    $r['leave_type'] ?? '',
                    $r['half_day_period'] ?? 'Full Day',
                    $r['start_date'] ?? '',
                    $r['end_date'] ?? '',
                    $r['reason'] ?? '',
                    ($r['status'] ?? '') === 'approved'
                    ? 'Accepted'
                    : ucfirst((string) ($r['status'] ?? '')),
                    $r['reviewer_name'] ?? '',
                    $r['reviewed_at'] ?? '',
                    $r['created_at'] ?? '',
                ], $rows);
                break;

            case 'salary':
                $chartData = $this->report->getSalaryChartData($filters);
                $reportSummary = $this->report->getSalarySummary($filters);
                $rows = $this->report->getSalaryReport($filters, null, 0);
                $sheetTitle = 'Salary Report';
                $filenamePrefix = 'salary_report';

                $headers = [
                    'Salary ID',
                    'Employee ID',
                    'Employee Code',
                    'Employee Name',
                    'Department',
                    'Salary Month',
                    'Basic Salary (BDT)',
                    'Deductions (BDT)',
                    'Final Salary (BDT)',
                    'Calculated At',
                ];

                $statusColumn = null;
                $numericColumns = ['G', 'H', 'I'];

                $data = array_map(static fn(array $r): array => [
                    $r['salary_id'] ?? '',
                    $r['employee_id'] ?? '',
                    $r['employee_code'] ?? '',
                    $r['full_name'] ?? '',
                    $r['department'] ?? '',
                    $r['salary_month'] ?? '',
                    $r['basic_salary'] ?? 0,
                    $r['deductions'] ?? 0,
                    $r['final_salary'] ?? 0,
                    $r['calculated_at'] ?? '',
                ], $rows);
                break;
        }


        // Add total record counts to the report summaries.
        if ($reportType === 'attendance') {
            $reportSummary['total_records'] = $chartData['total_records'] ?? count($rows);
        } elseif ($reportType === 'employees') {
            $reportSummary['total_employees'] = $chartData['total_employees'] ?? count($rows);
        } elseif ($reportType === 'leave') {
            $reportSummary['total_requests'] = $chartData['total_requests'] ?? count($rows);
        } elseif ($reportType === 'salary') {
            $reportSummary['saved_records'] = $chartData['total_records'] ?? count($rows);
        }


        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetTitle);

        $spreadsheet->getProperties()
            ->setCreator('MYOL HRIS')
            ->setTitle($sheetTitle)
            ->setSubject($sheetTitle)
            ->setDescription($sheetTitle . ' generated from MYOL HRIS.');


        // =========================================
// REPORT INFORMATION
// =========================================

        $filterParts = [];

        $filterLabels = [
            'from_date' => 'From',
            'to_date' => 'To',
            'employee_id' => 'Employee ID',
            'department' => 'Department',
            'designation' => 'Designation',
            'status' => 'Status',
            'month' => 'Month',
            'search' => 'Search',
        ];

        foreach ($filterLabels as $key => $label) {
            if (!isset($filters[$key]) || $filters[$key] === '') {
                continue;
            }

            $value = (string) $filters[$key];

            if ($key === 'month') {
                $value = date('F Y', strtotime($value . '-01'));
            } elseif ($key === 'status' && $value === 'approved') {
                $value = 'Accepted';
            } else {
                $value = ucfirst($value);
            }

            $filterParts[] = $label . ': ' . $value;
        }

        $filterText = $filterParts
            ? implode(' | ', $filterParts)
            : 'None (all records)';


        // Report-specific summary labels.
        $summaryLabels = [];

        switch ($reportType) {
            case 'attendance':
                $summaryLabels = [
                    'total_records' => 'Total Records',
                    'present_count' => 'Present',
                    'late_count' => 'Late',
                    'absent_count' => 'Absent',
                    'leave_count' => 'Leave',
                    'holiday_count' => 'Holiday',
                ];
                break;

            case 'employees':
                $summaryLabels = [
                    'total_employees' => 'Total Employees',
                    'active_employees' => 'Active',
                    'inactive_employees' => 'Inactive',
                ];
                break;

            case 'leave':
                $summaryLabels = [
                    'total_requests' => 'Total Requests',
                    'pending_count' => 'Pending',
                    'accepted_count' => 'Accepted',
                    'rejected_count' => 'Rejected',
                ];
                break;

            case 'salary':
                $summaryLabels = [
                    'saved_records' => 'Saved Records',
                    'total_basic_salary' => 'Total Basic Salary (BDT)',
                    'total_deductions' => 'Total Deductions (BDT)',
                    'total_final_salary' => 'Total Final Salary (BDT)',
                ];
                break;
        }


        // Write report information.
        $sheet->setCellValue('A1', $sheetTitle . ' - MYOL HRIS');
        $sheet->setCellValue('A2', 'Generated: ' . date('d M Y, h:i A'));
        $sheet->setCellValue('A3', 'Filters: ' . $filterText);
        $sheet->setCellValue('A4', 'Records: ' . count($rows));
        $sheet->setCellValue('A5', 'REPORT SUMMARY');

        $summaryRow = 6;

        foreach ($summaryLabels as $key => $label) {
            $value = $reportSummary[$key] ?? 0;

            if (str_contains(strtolower($key), 'salary')) {
                $value = (float) $value;
                $sheet->setCellValue('A' . $summaryRow, $label);
                $sheet->setCellValue('B' . $summaryRow, $value);

                $sheet->getStyle('B' . $summaryRow)
                    ->getNumberFormat()
                    ->setFormatCode('"৳" #,##0.00');
            } else {
                $sheet->setCellValue(
                    'A' . $summaryRow,
                    $label
                );

                $sheet->setCellValue(
                    'B' . $summaryRow,
                    (float) $value
                );
            }

            $summaryRow++;
        }

        // Style report information.
        $sheet->mergeCells('A1:D1');

        $sheet->getStyle('A1:D1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 18,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '8F294F'],
            ],
            'alignment' => [
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(34);

        $sheet->getStyle('A2:A4')->applyFromArray([
            'font' => [
                'size' => 11,
                'color' => ['rgb' => '343A40'],
            ],
        ]);

        $sheet->getStyle('A5:B5')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '8F294F'],
            ],
        ]);

        if ($summaryRow > 6) {
            $sheet->getStyle('A6:B' . ($summaryRow - 1))->applyFromArray([
                'font' => [
                    'size' => 10,
                    'color' => ['rgb' => '343A40'],
                ],
                'borders' => [
                    'bottom' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_HAIR,
                        'color' => ['rgb' => 'DDE1E7'],
                    ],
                ],
            ]);

            $sheet->getStyle('A6:A' . ($summaryRow - 1))
                ->getFont()->setBold(true);
        }

        $sheet->getColumnDimension('A')->setWidth(32);
        $sheet->getColumnDimension('B')->setWidth(25);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(20);


        // =========================================
// DETAILED REPORT TABLE
// =========================================

        $tableHeaderRow = 13;
        $dataStartRow = 14;

        // Write the column headings.
        $sheet->fromArray(
            $headers,
            null,
            'A' . $tableHeaderRow
        );

        // Write the report records.
        $rowNumber = $dataStartRow;

        foreach ($data as $record) {
            foreach ($record as $index => $value) {
                $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(
                    $index + 1
                );

                if (in_array($column, $numericColumns, true)) {
                    $sheet->setCellValue(
                        $column . $rowNumber,
                        is_numeric($value) ? (float) $value : 0
                    );
                } else {
                    $sheet->setCellValueExplicit(
                        $column . $rowNumber,
                        (string) $value,
                        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                    );
                }
            }

            $rowNumber++;
        }

        // Shared professional styling.
        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(
            count($headers)
        );
        $lastRow = max($tableHeaderRow, $rowNumber - 1);

        // Magenta header with white bold text.
        $sheet->getStyle("A{$tableHeaderRow}:{$lastColumn}{$tableHeaderRow}")->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 11,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '8F294F'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'bottom' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM,
                    'color' => ['rgb' => '70203D'],
                ],
            ],
        ]);

        $sheet->getRowDimension($tableHeaderRow)->setRowHeight(45);

        if ($lastRow >= $dataStartRow) {
            $sheet->getStyle("A{$dataStartRow}:{$lastColumn}{$lastRow}")->applyFromArray([
                'font' => [
                    'name' => 'Aptos',
                    'size' => 10,
                    'color' => ['rgb' => '343A40'],
                ],
                'alignment' => [
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'bottom' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_HAIR,
                        'color' => ['rgb' => 'DDE1E7'],
                    ],
                    'right' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_HAIR,
                        'color' => ['rgb' => 'E6E8EC'],
                    ],
                ],
            ]);

            // Alternating row backgrounds.
            for ($row = $dataStartRow; $row <= $lastRow; $row++) {
                if ($row % 2 === 0) {
                    $sheet->getStyle("A{$row}:{$lastColumn}{$row}")
                        ->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);

                    $sheet->getStyle("A{$row}:{$lastColumn}{$row}")
                        ->getFill()
                        ->getStartColor()
                        ->setRGB('F5F1F4');
                }

                $sheet->getRowDimension($row)->setRowHeight(22);
            }

            // Center ID and status columns.
            $centerColumns = ['A', 'B'];

            if ($statusColumn !== null) {
                $centerColumns[] = $statusColumn;
            }

            foreach ($centerColumns as $column) {
                $sheet->getStyle("{$column}{$dataStartRow}:{$column}{$lastRow}")
                    ->getAlignment()
                    ->setHorizontal(
                        \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
                    );
            }

            // Numeric salary fields: BDT currency formatting.
            foreach ($numericColumns as $column) {
                $sheet->getStyle("{$column}{$dataStartRow}:{$column}{$lastRow}")
                    ->getNumberFormat()
                    ->setFormatCode('"৳" #,##0.00');
            }

            // Highlight report status values.
            if ($statusColumn !== null) {
                for ($row = $dataStartRow; $row <= $lastRow; $row++) {
                    $status = strtolower(trim(
                        (string) $sheet->getCell("{$statusColumn}{$row}")->getValue()
                    ));

                    $statusColors = [
                        'present' => 'E2F3E9',
                        'active' => 'E2F3E9',
                        'accepted' => 'E2F3E9',
                        'late' => 'FFF1D6',
                        'pending' => 'FFF1D6',
                        'absent' => 'FBE5E7',
                        'inactive' => 'FBE5E7',
                        'rejected' => 'FBE5E7',
                        'leave' => 'EEE8FA',
                        'holiday' => 'E2F0F8',
                    ];

                    if (isset($statusColors[$status])) {
                        $sheet->getStyle("{$statusColumn}{$row}")
                            ->getFill()
                            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);

                        $sheet->getStyle("{$statusColumn}{$row}")
                            ->getFill()
                            ->getStartColor()
                            ->setRGB($statusColors[$status]);

                        $sheet->getStyle("{$statusColumn}{$row}")
                            ->getFont()
                            ->setBold(true);
                    }
                }
            }
        }

        // Set report-specific column widths.
        $widths = [
            'attendance' => [
                16,
                14,
                18,
                27,
                21,
                22,
                19,
                23,
                19,
                23,
                19,
                20,
            ],
            'employees' => [
                15,
                13,
                18,
                27,
                20,
                30,
                17,
                20,
                22,
                17,
                18,
                21,
                24,
                18,
                18,
                16,
            ],
            'leave' => [
                15,
                14,
                18,
                26,
                20,
                18,
                20,
                17,
                17,
                35,
                17,
                25,
                22,
                22,
            ],
            'salary' => [
                15,
                14,
                18,
                26,
                20,
                18,
                22,
                22,
                22,
                23,
            ],
        ];

        foreach ($widths[$reportType] as $index => $width) {
            $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(
                $index + 1
            );

            $sheet->getColumnDimension($column)->setWidth($width);
        }

        // Wrap longer text columns.
        $wrapColumns = [
            'attendance' => ['D', 'E', 'H', 'J', 'K', 'L'],
            'employees' => ['D', 'F', 'H', 'I', 'M'],
            'leave' => ['D', 'E', 'F', 'G', 'J', 'L'],
            'salary' => ['D', 'E'],
        ];

        foreach ($wrapColumns[$reportType] as $column) {
            $sheet->getStyle("{$column}{$dataStartRow}:{$column}{$lastRow}")
                ->getAlignment()
                ->setWrapText(true);
        }

        // Enable filters without freezing the chart area.
        $sheet->setAutoFilter("A{$tableHeaderRow}:{$lastColumn}{$lastRow}");

        // Landscape print layout.
        $sheet->getPageSetup()
            ->setOrientation(
                \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE
            )
            ->setPaperSize(
                \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A3
            )
            ->setFitToWidth(1)
            ->setFitToHeight(0);

        $sheet->getPageSetup()->setFitToPage(true);
        $sheet->getPageMargins()->setTop(0.4);
        $sheet->getPageMargins()->setBottom(0.4);
        $sheet->getPageMargins()->setLeft(0.25);
        $sheet->getPageMargins()->setRight(0.25);

        $filename = $filenamePrefix . '_' . date('Ymd_His') . '.xlsx';

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        // Export the workbook without charts.
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');

        $spreadsheet->disconnectWorksheets();
        exit;
    }


    /**
     * Read only the filter fields supported by the selected report.
     */
    private function readFilters(string $reportType): array
    {
        $filters = [];

        if (in_array($reportType, ['attendance', 'leave'], true)) {
            $fromDate = $this->validDate($_GET['from_date'] ?? null);
            $toDate = $this->validDate($_GET['to_date'] ?? null);

            if ($fromDate !== null) {
                $filters['from_date'] = $fromDate;
            }

            if ($toDate !== null) {
                $filters['to_date'] = $toDate;
            }

            if (
                isset($filters['from_date'], $filters['to_date'])
                && $filters['from_date'] > $filters['to_date']
            ) {
                // Avoid silently running a reversed date range.
                unset($filters['from_date'], $filters['to_date']);
                $_SESSION['error_message'] = 'The start date must not be later than the end date.';
            }

            $employeeId = filter_var(
                $_GET['employee_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if ($employeeId !== false && $employeeId !== null && $employeeId > 0) {
                $filters['employee_id'] = $employeeId;
            }
        }

        if ($reportType === 'attendance') {
            $department = trim((string) ($_GET['department'] ?? ''));
            if ($department !== '') {
                $filters['department'] = $department;
            }

            $status = strtolower(trim((string) ($_GET['status'] ?? '')));
            if (in_array($status, ['present', 'late', 'absent', 'leave', 'holiday'], true)) {
                $filters['status'] = $status;
            }
        }

        if ($reportType === 'employees') {
            foreach (['department', 'designation', 'search'] as $field) {
                $value = trim((string) ($_GET[$field] ?? ''));
                if ($value !== '') {
                    $filters[$field] = $value;
                }
            }

            $status = strtolower(trim((string) ($_GET['status'] ?? '')));
            if (in_array($status, ['active', 'inactive'], true)) {
                $filters['status'] = $status;
            }
        }

        if ($reportType === 'leave') {
            $status = strtolower(trim((string) ($_GET['status'] ?? '')));
            if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
                $filters['status'] = $status;
            }
        }

        if ($reportType === 'salary') {
            $department = trim((string) ($_GET['department'] ?? ''));

            if ($department !== '') {
                $filters['department'] = $department;
            }
            $employeeId = filter_var(
                $_GET['employee_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if ($employeeId !== false && $employeeId !== null && $employeeId > 0) {
                $filters['employee_id'] = $employeeId;
            }

            $month = trim((string) ($_GET['month'] ?? ''));
            if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1) {
                $filters['month'] = $month;
            }
        }

        return $filters;
    }

    private function validDate(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (!$date || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $value;
    }
}
