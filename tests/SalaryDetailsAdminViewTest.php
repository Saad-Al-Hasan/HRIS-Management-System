<?php

use PHPUnit\Framework\TestCase;

class SalaryDetailsAdminViewTest extends TestCase
{
    private string $viewPath;

    private array $originalSession = [];
    private array $originalGet = [];

    protected function setUp(): void
    {
        $this->viewPath = __DIR__
            . '/../app/views/admin/salary/details.php';

        $this->originalSession = $_SESSION ?? [];
        $this->originalGet = $_GET ?? [];

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Simulate an authenticated administrator.
        $_SESSION = [
            'user_id' => 999994,
            'role_id' => 1,
            'full_name' => 'Salary Details Test Admin'
        ];

        $_GET = [
            'action' => 'salary-details',
            'id' => 910001
        ];

        $this->assertFileExists(
            $this->viewPath,
            'Admin salary details view was not found.'
        );
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->originalSession;
        $_GET = $this->originalGet;

        http_response_code(200);

        gc_collect_cycles();
    }

    /**
     * Prepare reusable sample salary data.
     */
    private function sampleSalaryRecord(
        array $overrides = []
    ): array {
        $record = [
            'id' => 910001,
            'user_id' => 900001,
            'employee_code' => 'EMP-SAL-001',
            'full_name' => 'Salary Details Test Employee',
            'salary_month' => '2095-05-01',
            'basic_salary' => 30000,
            'deductions' => 2250,
            'final_salary' => 27750,
            'calculated_at' => '2095-05-25 14:30:00',

            'calculation_details' => [
                'employee' => [
                    'joining_date' => '2024-01-15'
                ],

                'calculated_through' => '2095-05-25',

                'daily_salary' => 967.74,

                'calendar_days' => 31,
                'eligible_days' => 25,
                'paid_fridays' => 4,
                'paid_holidays' => 2,
                'full_day_leaves' => 1,
                'valid_half_day_leaves' => 1,
                'incomplete_half_day_leaves' => 1,
                'absent_days' => 1,

                'absence_deduction' => 967.74,
                'half_day_deduction' => 483.87,
                'late_occurrences' => 5,
                'early_exit_occurrences' => 3,
                'late_early_deduction' => 798.39,

                'daily_records' => [
                    [
                        'date' => '2095-05-01',
                        'type' => 'Present',
                        'entry_time' => '08:55:00',
                        'entry_status' => 'In Time',
                        'exit_time' => '18:00:00',
                        'exit_status' => 'In Time',
                        'deduction' => 0,
                        'note' => 'Regular attendance'
                    ],
                    [
                        'date' => '2095-05-02',
                        'type' => 'Entry - Late',
                        'entry_time' => '09:45:00',
                        'entry_status' => 'Late',
                        'exit_time' => '18:00:00',
                        'exit_status' => 'In Time',
                        'deduction' => 0,
                        'note' => 'Late entry recorded'
                    ],
                    [
                        'date' => '2095-05-03',
                        'type' => 'Absent',
                        'entry_time' => null,
                        'entry_status' => null,
                        'exit_time' => null,
                        'exit_status' => null,
                        'deduction' => 967.74,
                        'note' => 'No attendance recorded'
                    ]
                ]
            ]
        ];

        $result = array_replace_recursive(
            $record,
            $overrides
        );

        // Explicitly replace daily records, including an empty array.
        if (
            isset($overrides['calculation_details'])
            && array_key_exists('daily_records', $overrides['calculation_details'])
        ) {

            $result['calculation_details']['daily_records'] =
                $overrides['calculation_details']['daily_records'];
        }

        return $result;
    }

    /**
     * Render the actual admin salary details view.
     */
    private function renderView(
        array $salaryRecord = []
    ): string {
        if (empty($salaryRecord)) {
            $salaryRecord = $this->sampleSalaryRecord();
        }

        ob_start();

        try {
            include $this->viewPath;

            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Test ID: SAL-AD01
     * Verify Salary Details page renders correctly.
     */
    public function testSalaryDetailsPageRendersCorrectly(): void
    {
        $html = $this->renderView();

        $this->assertStringContainsString(
            'PAYROLL MANAGEMENT',
            $html
        );

        $this->assertStringContainsString(
            '<h1>Salary Details</h1>',
            $html
        );

        $this->assertStringContainsString(
            'Complete salary statement and monthly attendance breakdown.',
            $html
        );

        $this->assertStringContainsString(
            'Salary Summary',
            $html
        );

        $this->assertStringContainsString(
            'Attendance & Leave Summary',
            $html
        );
    }

    /**
     * Test ID: SAL-AD02
     * Verify employee profile and employee information.
     */
    public function testEmployeeProfileAndInformationAreDisplayed(): void
    {
        $html = $this->renderView();

        $this->assertStringContainsString(
            'Salary Details Test Employee',
            $html
        );

        $this->assertStringContainsString(
            'EMP-SAL-001',
            $html
        );

        $this->assertStringContainsString(
            'Joined 15 Jan 2024',
            $html
        );

        $this->assertStringContainsString(
            'EMPLOYEE PAYROLL STATEMENT',
            $html
        );

        $this->assertStringContainsString(
            'salary-employee-avatar',
            $html
        );
    }

    /**
     * Test ID: SAL-AD03
     * Verify payroll month and Provisional/Finalized status.
     */
    public function testPayrollPeriodAndFinalizedStatusAreDisplayed(): void
    {
        $html = $this->renderView();

        $this->assertStringContainsString(
            'PAYROLL PERIOD',
            $html
        );

        $this->assertStringContainsString(
            'May 2095',
            $html
        );

        $this->assertStringContainsString(
            'salary-period-badge finalized',
            $html
        );

        $this->assertStringContainsString(
            'FINALIZED',
            $html
        );
    }

    /**
     * Test ID: SAL-AD04
     * Verify current-month salary is marked provisional.
     */
    public function testCurrentMonthSalaryIsMarkedProvisional(): void
    {
        $currentMonth = date('Y-m-01');

        $record = $this->sampleSalaryRecord([
            'salary_month' => $currentMonth
        ]);

        $html = $this->renderView($record);

        $this->assertStringContainsString(
            'salary-period-badge provisional',
            $html
        );

        $this->assertStringContainsString(
            'PROVISIONAL',
            $html
        );
    }

    /**
     * Test ID: SAL-AD05
     * Verify basic salary, daily salary, deductions and net salary.
     */
    public function testSalarySummaryAmountsAreDisplayed(): void
    {
        $html = $this->renderView();

        $this->assertStringContainsString(
            'Monthly Basic Salary',
            $html
        );

        $this->assertStringContainsString(
            '৳30,000.00',
            $html
        );

        $this->assertStringContainsString(
            'Daily Salary',
            $html
        );

        $this->assertStringContainsString(
            '৳967.74',
            $html
        );

        $this->assertStringContainsString(
            'Total Deductions',
            $html
        );

        $this->assertStringContainsString(
            '৳2,250.00',
            $html
        );

        $this->assertStringContainsString(
            'NET PAYABLE SALARY',
            $html
        );

        $this->assertStringContainsString(
            '৳27,750.00',
            $html
        );
    }

    /**
     * Test ID: SAL-AD06
     * Verify all eight attendance summary metrics.
     */
    public function testAllAttendanceSummaryMetricsAreDisplayed(): void
    {
        $html = $this->renderView();

        $expectedMetrics = [
            'Calendar Days',
            'Eligible Working Days',
            'Paid Fridays',
            'Paid Holidays',
            'Full-Day Leaves',
            'Valid Half-Day Leaves',
            'Incomplete Half-Day Leaves',
            'Absent Days'
        ];

        foreach ($expectedMetrics as $metric) {
            $this->assertStringContainsString(
                $metric,
                $html,
                "Missing attendance metric: {$metric}"
            );
        }

        $this->assertStringContainsString(
            'salary-stat-blue',
            $html
        );

        $this->assertStringContainsString(
            'salary-stat-red',
            $html
        );
    }

    /**
     * Test ID: SAL-AD07
     * Verify absence deduction breakdown.
     */
    public function testAbsenceDeductionBreakdownIsDisplayed(): void
    {
        $html = $this->renderView();

        $this->assertStringContainsString(
            'Absence Deduction',
            $html
        );

        $this->assertStringContainsString(
            'Unapproved working-day absence',
            $html
        );

        $this->assertStringContainsString(
            '1 days',
            $html
        );

        $this->assertStringContainsString(
            '৳967.74',
            $html
        );
    }

    /**
     * Test ID: SAL-AD08
     * Verify incomplete half-day deduction.
     */
    public function testIncompleteHalfDayDeductionIsDisplayed(): void
    {
        $html = $this->renderView();

        $this->assertStringContainsString(
            'Incomplete Half-Day Leave',
            $html
        );

        $this->assertStringContainsString(
            'Required working half not completed',
            $html
        );

        $this->assertStringContainsString(
            '৳483.87',
            $html
        );
    }

    /**
     * Test ID: SAL-AD09
     * Verify late-entry and early-exit deductions.
     */
    public function testLateAndEarlyExitDeductionsAreDisplayed(): void
    {
        $html = $this->renderView();

        $this->assertStringContainsString(
            'Late Entry & Early Exit',
            $html
        );

        $this->assertStringContainsString(
            '5 late +',
            $html
        );

        $this->assertStringContainsString(
            '3 early exits',
            $html
        );

        $this->assertStringContainsString(
            '৳798.39',
            $html
        );
    }

    /**
     * Test ID: SAL-AD10
     * Verify the first three free occurrences notice.
     */
    public function testFreeLateAndEarlyExitAllowanceNoticeIsDisplayed(): void
    {
        $html = $this->renderView();

        $this->assertStringContainsString(
            'The first three combined late-entry and early-exit occurrences',
            $html
        );

        $this->assertStringContainsString(
            'are free.',
            $html
        );

        $this->assertStringContainsString(
            'Each additional occurrence deducts half a day',
            $html
        );
    }

    /**
     * Test ID: SAL-AD11
     * Verify all eight daily attendance ledger columns.
     */
    public function testDailyAttendanceLedgerColumnsAreDisplayed(): void
    {
        $html = $this->renderView();

        $expectedColumns = [
            'Date',
            'Attendance',
            'Entry Time',
            'Entry Status',
            'Exit Time',
            'Exit Status',
            'Deduction',
            'Remarks'
        ];

        foreach ($expectedColumns as $column) {
            $this->assertStringContainsString(
                '<th>' . $column . '</th>',
                $html,
                "Missing daily ledger column: {$column}"
            );
        }
    }

    /**
     * Test ID: SAL-AD12
     * Verify daily attendance records and status labels.
     */
    public function testDailyAttendanceRecordsAreDisplayed(): void
    {
        $html = $this->renderView();

        $this->assertStringContainsString(
            '01 May 2095',
            $html
        );

        $this->assertStringContainsString(
            '02 May 2095',
            $html
        );

        $this->assertStringContainsString(
            '03 May 2095',
            $html
        );

        $this->assertStringContainsString(
            'Regular attendance',
            $html
        );

        $this->assertStringContainsString(
            'Late entry recorded',
            $html
        );

        $this->assertStringContainsString(
            'No attendance recorded',
            $html
        );

        $this->assertStringContainsString(
            'salary-status-success',
            $html
        );

        $this->assertStringContainsString(
            'salary-status-warning',
            $html
        );

        $this->assertStringContainsString(
            'salary-status-danger',
            $html
        );
    }

    /**
     * Test ID: SAL-AD13
     * Verify empty daily attendance ledger message.
     */
    public function testEmptyDailyAttendanceLedgerMessageIsDisplayed(): void
    {
        $record = $this->sampleSalaryRecord([
            'calculation_details' => [
                'daily_records' => []
            ]
        ]);

        $html = $this->renderView($record);

        $this->assertStringContainsString(
            'No daily calculation records available.',
            $html
        );

        $this->assertStringContainsString(
            '0 Records',
            $html
        );

        $this->assertStringContainsString(
            'colspan="8"',
            $html
        );
    }

    /**
     * Test ID: SAL-AD14
     * Verify missing entry and exit times display correctly.
     */
    public function testMissingAttendanceTimesDisplayDash(): void
    {
        $record = $this->sampleSalaryRecord([
            'calculation_details' => [
                'daily_records' => [
                    [
                        'date' => '2095-05-04',
                        'type' => 'Absent',
                        'entry_time' => null,
                        'entry_status' => null,
                        'exit_time' => null,
                        'exit_status' => null,
                        'deduction' => 967.74,
                        'note' => 'Absent employee'
                    ]
                ]
            ]
        ]);

        $html = $this->renderView($record);

        $this->assertStringContainsString(
            'Absent employee',
            $html
        );

        $this->assertStringContainsString(
            '—',
            $html
        );

        $this->assertStringContainsString('—', $html);

        // Empty entry and exit times must not produce PHP warnings.
        $this->assertStringNotContainsString(
            'Warning:',
            $html
        );
    }

    /**
     * Test ID: SAL-AD15
     * Verify BDT currency formatting.
     */
    public function testSalaryAmountsAreFormattedInBDT(): void
    {
        $record = $this->sampleSalaryRecord([
            'basic_salary' => 30000.50,
            'deductions' => 1250.75,
            'final_salary' => 28749.75,
            'calculation_details' => [
                'daily_salary' => 1000.25
            ]
        ]);

        $html = $this->renderView($record);

        $this->assertStringContainsString(
            '৳30,000.50',
            $html
        );

        $this->assertStringContainsString(
            '৳1,250.75',
            $html
        );

        $this->assertStringContainsString(
            '৳28,749.75',
            $html
        );

        $this->assertStringContainsString(
            '৳1,000.25',
            $html
        );
    }

    /**
     * Test ID: SAL-AD16
     * Verify employee information and remarks are HTML-escaped.
     */
    public function testEmployeeInformationAndRemarksAreHtmlEscaped(): void
    {
        $maliciousName = '<script>alert("name")</script>';
        $maliciousCode = '<img src=x onerror=alert(1)>';

        $maliciousRemark = '<script>alert("remark")</script>';

        $record = $this->sampleSalaryRecord([
            'full_name' => $maliciousName,
            'employee_code' => $maliciousCode,
            'calculation_details' => [
                'daily_records' => [
                    [
                        'date' => '2095-05-05',
                        'type' => 'Present',
                        'entry_time' => null,
                        'entry_status' => 'In Time',
                        'exit_time' => null,
                        'exit_status' => 'In Time',
                        'deduction' => 0,
                        'note' => $maliciousRemark
                    ]
                ]
            ]
        ]);

        $html = $this->renderView($record);

        $this->assertStringContainsString(
            htmlspecialchars($maliciousName),
            $html
        );

        $this->assertStringContainsString(
            htmlspecialchars($maliciousCode),
            $html
        );

        $this->assertStringContainsString(
            htmlspecialchars($maliciousRemark),
            $html
        );

        $this->assertStringNotContainsString(
            $maliciousName,
            $html
        );

        $this->assertStringNotContainsString(
            $maliciousCode,
            $html
        );

        $this->assertStringNotContainsString(
            $maliciousRemark,
            $html
        );
    }

    /**
     * Test ID: SAL-AD17
     * Verify Download PDF functionality is present.
     */
    public function testDownloadPdfFunctionalityIsPresent(): void
    {
        $html = $this->renderView();

        $this->assertStringContainsString(
            'salary-download-pdf-btn',
            $html
        );

        $this->assertStringContainsString(
            'Download As PDF',
            $html
        );

        $this->assertStringContainsString(
            'function downloadSalaryDetailsPdf()',
            $html
        );

        $this->assertStringContainsString(
            'window.print()',
            $html
        );

        $this->assertStringContainsString(
            'afterprint',
            $html
        );
    }

    /**
     * Test ID: SAL-AD18
     * Verify Back navigation and calculation dates.
     */
    public function testBackNavigationAndCalculationDatesAreDisplayed(): void
    {
        $html = $this->renderView();

        $this->assertStringContainsString(
            'action=salary-management',
            $html
        );

        $this->assertStringContainsString(
            'Back to Salary Management',
            $html
        );

        $this->assertStringContainsString(
            'Calculated through',
            $html
        );

        $this->assertStringContainsString(
            '25 May 2095',
            $html
        );

        $this->assertStringContainsString(
            'Last calculated:',
            $html
        );

        $this->assertStringContainsString(
            '25 May 2095, 02:30:00 PM',
            $html
        );
    }
}
