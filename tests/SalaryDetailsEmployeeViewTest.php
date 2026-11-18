<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

class SalaryDetailsEmployeeViewTest extends TestCase
{
    private string $viewPath;

    protected function setUp(): void
    {
        $this->viewPath = __DIR__
            . '/../app/views/employee/salary-details.php';

        $_GET = [];
        $_POST = [];
        $_SESSION = [];
    }

    /**
     * Render the employee salary details view using sample data.
     */
    private function renderView(
        array $record = [],
        ?string $error = null
    ): string {
        $salaryRecord = $record ?: $this->sampleSalaryRecord();
        $pageTitle = 'Employee Salary Details';

        ob_start();

        include $this->viewPath;

        return ob_get_clean();
    }

    /**
     * Generate sample salary data without using the database.
     */
    private function sampleSalaryRecord(
        array $overrides = []
    ): array {
        $currentMonth = date('Y-m-01');

        $record = [
            'user_id' => 999999,
            'full_name' => 'Test Employee',
            'employee_code' => 'EMP-TEST-001',
            'salary_month' => $currentMonth,
            'basic_salary' => 30000,
            'deductions' => 2250,
            'final_salary' => 27750,
            'calculated_at' => '2026-11-14 14:30:00',

            'calculation_details' => [
                'employee' => [
                    'joining_date' => '2025-01-15',
                ],

                'daily_salary' => 1000,
                'calendar_days' => 30,
                'eligible_days' => 24,
                'paid_fridays' => 4,
                'paid_holidays' => 2,
                'full_day_leaves' => 1,
                'valid_half_day_leaves' => 1,
                'incomplete_half_day_leaves' => 1,
                'absent_days' => 1,

                'absence_deduction' => 1000,
                'half_day_deduction' => 500,
                'late_occurrences' => 4,
                'early_exit_occurrences' => 2,
                'late_early_deduction' => 750,

                'calculated_through' => date('Y-m-d'),

                'daily_records' => [
                    [
                        'date' => '2026-11-01',
                        'type' => 'Present',
                        'entry_time' => '2026-11-01 09:00:00',
                        'entry_status' => 'In Time',
                        'exit_time' => '2026-11-01 18:00:00',
                        'exit_status' => 'In Time',
                        'deduction' => 0,
                        'note' => 'Regular attendance',
                    ],
                ],
            ],
        ];

        $result = array_replace_recursive($record, $overrides);

        // Ensure explicitly supplied empty daily records remain empty.
        if (
            isset($overrides['calculation_details'])
            && array_key_exists(
                'daily_records',
                $overrides['calculation_details']
            )
        ) {
            $result['calculation_details']['daily_records'] =
                $overrides['calculation_details']['daily_records'];
        }

        return $result;
    }

    // ---------------------------------------------------------
    // SAL-EM01
    // Verify Employee Salary Details page renders correctly.
    // ---------------------------------------------------------

    public function testSalaryDetailsPageRendersCorrectly(): void
    {
        $html = $this->renderView();

        $this->assertStringContainsString(
            'Salary Details',
            $html
        );

        $this->assertStringContainsString(
            'EMPLOYEE PAYROLL STATEMENT',
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

    // ---------------------------------------------------------
    // SAL-EM02
    // Verify employee information is displayed.
    // ---------------------------------------------------------

    public function testEmployeeInformationIsDisplayed(): void
    {
        $html = $this->renderView();

        $this->assertStringContainsString(
            'Test Employee',
            $html
        );

        $this->assertStringContainsString(
            'EMP-TEST-001',
            $html
        );

        $this->assertStringContainsString(
            '15 Jan 2025',
            $html
        );
    }

    // ---------------------------------------------------------
    // SAL-EM03
    // Verify monthly salary and net payable amount.
    // ---------------------------------------------------------

    public function testSalarySummaryAmountsAreDisplayed(): void
    {
        $html = $this->renderView();

        $this->assertStringContainsString(
            '৳30,000.00',
            $html
        );

        $this->assertStringContainsString(
            '৳1,000.00',
            $html
        );

        $this->assertStringContainsString(
            '৳2,250.00',
            $html
        );

        $this->assertStringContainsString(
            '৳27,750.00',
            $html
        );
    }

    // ---------------------------------------------------------
    // SAL-EM04
    // Verify deduction breakdown.
    // ---------------------------------------------------------

    public function testDeductionBreakdownIsDisplayed(): void
    {
        $html = $this->renderView();

        $this->assertStringContainsString(
            'Deduction Breakdown',
            $html
        );

        $this->assertStringContainsString(
            'Absence Deduction',
            $html
        );

        $this->assertStringContainsString(
            'Incomplete Half-Day Leave',
            $html
        );

        $this->assertStringContainsString(
            'Late Entry & Early Exit',
            $html
        );

        $this->assertStringContainsString(
            'Total Deductions',
            $html
        );
    }

    // ---------------------------------------------------------
    // SAL-EM05
    // Verify the first three free occurrences rule.
    // ---------------------------------------------------------

    public function testFreeOccurrenceRuleIsDisplayed(): void
    {
        $html = $this->renderView();

        $this->assertStringContainsString(
            'first three combined late-entry and early-exit occurrences',
            $html
        );

        $this->assertStringContainsString(
            'half a day',
            $html
        );
    }

    // ---------------------------------------------------------
    // SAL-EM06
    // Verify all attendance summary metrics.
    // ---------------------------------------------------------

    public function testAttendanceSummaryMetricsAreDisplayed(): void
    {
        $html = $this->renderView();

        $expectedLabels = [
            'Calendar Days',
            'Eligible Working Days',
            'Paid Fridays',
            'Paid Holidays',
            'Full-Day Leaves',
            'Valid Half-Day Leaves',
            'Incomplete Half-Day Leaves',
            'Absent Days',
        ];

        foreach ($expectedLabels as $label) {
            $this->assertStringContainsString(
                $label,
                $html
            );
        }
    }

    // ---------------------------------------------------------
    // SAL-EM07
    // Verify current-month salary is provisional.
    // ---------------------------------------------------------

    public function testCurrentMonthSalaryIsProvisional(): void
    {
        $record = $this->sampleSalaryRecord([
            'salary_month' => date('Y-m-01'),
        ]);

        $html = $this->renderView($record);

        $this->assertStringContainsString(
            'PROVISIONAL',
            $html
        );

        $this->assertStringNotContainsString(
            'FINALIZED',
            $html
        );
    }

    // ---------------------------------------------------------
    // SAL-EM08
    // Verify previous-month salary is finalized.
    // ---------------------------------------------------------

    public function testPreviousMonthSalaryIsFinalized(): void
    {
        $previousMonth = date(
            'Y-m-01',
            strtotime('first day of last month')
        );

        $record = $this->sampleSalaryRecord([
            'salary_month' => $previousMonth,
        ]);

        $html = $this->renderView($record);

        $this->assertStringContainsString(
            'FINALIZED',
            $html
        );

        $this->assertStringNotContainsString(
            'PROVISIONAL',
            $html
        );
    }

    // ---------------------------------------------------------
    // SAL-EM09
    // Verify daily attendance ledger information.
    // ---------------------------------------------------------

    public function testDailyAttendanceLedgerIsDisplayed(): void
    {
        $html = $this->renderView();

        $expectedColumns = [
            'Daily Attendance Ledger',
            'Date',
            'Attendance',
            'Entry Time',
            'Entry Status',
            'Exit Time',
            'Exit Status',
            'Deduction',
            'Remarks',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertStringContainsString(
                $column,
                $html
            );
        }

        $this->assertStringContainsString(
            'Regular attendance',
            $html
        );

        $this->assertStringContainsString(
            'In Time',
            $html
        );
    }

    // ---------------------------------------------------------
    // SAL-EM10
    // Verify empty attendance ledger handling.
    // ---------------------------------------------------------

    public function testEmptyAttendanceLedgerIsHandled(): void
    {
        $record = $this->sampleSalaryRecord([
            'calculation_details' => [
                'daily_records' => [],
            ],
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
    }

    // ---------------------------------------------------------
    // SAL-EM11
    // Verify missing attendance times display a dash.
    // ---------------------------------------------------------

    public function testMissingAttendanceTimesDisplayDash(): void
    {
        $record = $this->sampleSalaryRecord([
            'calculation_details' => [
                'daily_records' => [
                    [
                        'date' => '2026-11-02',
                        'type' => 'Absent',
                        'entry_time' => null,
                        'entry_status' => null,
                        'exit_time' => null,
                        'exit_status' => null,
                        'deduction' => 1000,
                        'note' => null,
                    ],
                ],
            ],
        ]);

        $html = $this->renderView($record);

        $this->assertStringContainsString(
            'Absent',
            $html
        );

        $this->assertStringContainsString(
            '—',
            $html
        );
    }

    // ---------------------------------------------------------
    // SAL-EM12
    // Verify PDF download functionality.
    // ---------------------------------------------------------

    public function testDownloadPdfFunctionalityIsPresent(): void
    {
        $html = $this->renderView();

        $this->assertStringContainsString(
            'Download As PDF',
            $html
        );

        $this->assertStringContainsString(
            'downloadSalaryDetailsPdf()',
            $html
        );

        $this->assertStringContainsString(
            'window.print()',
            $html
        );
    }

    // ---------------------------------------------------------
    // SAL-EM13
    // Verify employee dashboard navigation.
    // ---------------------------------------------------------

    public function testBackToDashboardNavigationIsDisplayed(): void
    {
        $html = $this->renderView();

        $this->assertStringContainsString(
            'action=employee-dashboard',
            $html
        );

        $this->assertStringContainsString(
            'Back to Dashboard',
            $html
        );
    }

    // ---------------------------------------------------------
    // SAL-EM14
    // Verify employee information and remarks are escaped.
    // ---------------------------------------------------------

    public function testEmployeeInformationAndRemarksAreEscaped(): void
    {
        $record = $this->sampleSalaryRecord([
            'full_name' => '<script>alert("XSS")</script>',

            'calculation_details' => [
                'daily_records' => [
                    [
                        'date' => '2026-11-03',
                        'type' => 'Present',
                        'entry_time' => null,
                        'entry_status' => 'In Time',
                        'exit_time' => null,
                        'exit_status' => 'In Time',
                        'deduction' => 0,
                        'note' => '<script>alert("XSS")</script>',
                    ],
                ],
            ],
        ]);

        $html = $this->renderView($record);

        $this->assertStringNotContainsString(
            '<script>alert("XSS")</script>',
            $html
        );

        $this->assertStringContainsString(
            '&lt;script&gt;',
            $html
        );
    }
}
