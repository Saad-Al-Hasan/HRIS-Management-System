<?php

use PHPUnit\Framework\TestCase;

class SalaryProcessingAdminViewTest extends TestCase
{
    private string $viewPath;

    private string $csrfToken = 'salary-admin-view-test-token';

    private array $originalSession = [];
    private array $originalGet = [];

    protected function setUp(): void
    {
        $this->viewPath = __DIR__
            . '/../app/views/admin/salary/index.php';

        $this->originalSession = $_SESSION ?? [];
        $this->originalGet = $_GET ?? [];

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Simulate an authenticated administrator.
        $_SESSION = [
            'user_id' => 999993,
            'role_id' => 1,
            'full_name' => 'Salary View Test Admin',
            'csrf_token' => $this->csrfToken
        ];

        $_GET = [
            'action' => 'salary-management'
        ];

        $this->assertFileExists(
            $this->viewPath,
            'Admin salary management view was not found.'
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
     * Render the actual salary management view.
     */
    private function renderView(
        array $employees = [],
        array $salaryRecords = [],
        string $selectedMonth = '2095-05',
        ?int $selectedEmployeeId = null,
        array $autoCalculationWarnings = [],
        string $success = '',
        string $error = ''
    ): string {
        $csrfToken = $this->csrfToken;

        ob_start();

        try {
            include $this->viewPath;

            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Prepare sample employee data.
     */
    private function sampleEmployees(): array
    {
        return [
            [
                'employee_id' => 900001,
                'employee_code' => 'EMP-SAL-001',
                'full_name' => 'Salary Test Employee One'
            ],
            [
                'employee_id' => 900002,
                'employee_code' => 'EMP-SAL-002',
                'full_name' => 'Salary Test Employee Two'
            ]
        ];
    }

    /**
     * Prepare sample monthly salary records.
     */
    private function sampleSalaryRecords(): array
    {
        return [
            [
                'id' => 910001,
                'employee_id' => 900001,
                'employee_code' => 'EMP-SAL-001',
                'full_name' => 'Salary Test Employee One',
                'salary_month' => '2095-05-01',
                'basic_salary' => 30000,
                'deductions' => 1500,
                'final_salary' => 28500,
                'calculated_at' => '2095-05-25 14:30:00'
            ],
            [
                'id' => 910002,
                'employee_id' => 900002,
                'employee_code' => 'EMP-SAL-002',
                'full_name' => 'Salary Test Employee Two',
                'salary_month' => '2095-05-01',
                'basic_salary' => 25000,
                'deductions' => 1000,
                'final_salary' => 24000,
                'calculated_at' => '2095-05-26 15:45:00'
            ]
        ];
    }

    /**
     * Test ID: SAL-AV01
     * Purpose: Verify Salary Management page rendering.
     */
    public function testSalaryManagementPageRendersCorrectly(): void
    {
        $html = $this->renderView();

        $this->assertStringContainsString(
            'PAYROLL MANAGEMENT',
            $html
        );

        $this->assertStringContainsString(
            '<h1>Salary Management</h1>',
            $html
        );

        $this->assertStringContainsString(
            'Calculate, manage and review employee monthly salaries.',
            $html
        );

        $this->assertStringContainsString(
            'Payroll Summary',
            $html
        );

        $this->assertStringContainsString(
            'Calculate Monthly Salary',
            $html
        );

        $this->assertStringContainsString(
            'Monthly Salary Records',
            $html
        );
    }

    /**
     * Test ID: SAL-AV02
     * Purpose: Verify success message display.
     */
    public function testSuccessMessageIsDisplayed(): void
    {
        $html = $this->renderView(
            success: 'Monthly salary calculated successfully.'
        );

        $this->assertStringContainsString(
            'salary-feedback-success',
            $html
        );

        $this->assertStringContainsString(
            'Operation Successful',
            $html
        );

        $this->assertStringContainsString(
            'Monthly salary calculated successfully.',
            $html
        );
    }

    /**
     * Test ID: SAL-AV03
     * Purpose: Verify error message display.
     */
    public function testErrorMessageIsDisplayed(): void
    {
        $html = $this->renderView(
            error: 'Unable to calculate employee salary.'
        );

        $this->assertStringContainsString(
            'salary-feedback-error',
            $html
        );

        $this->assertStringContainsString(
            'Something Needs Attention',
            $html
        );

        $this->assertStringContainsString(
            'Unable to calculate employee salary.',
            $html
        );
    }

    /**
     * Test ID: SAL-AV04
     * Purpose: Verify salary configuration warnings.
     */
    public function testSalaryConfigurationWarningsAreDisplayed(): void
    {
        $warnings = [
            'EMP-SAL-001: Monthly salary is not configured.',
            'EMP-SAL-002: Joining date is missing.'
        ];

        $html = $this->renderView(
            autoCalculationWarnings: $warnings
        );

        $this->assertStringContainsString(
            'Salary Configuration Warnings',
            $html
        );

        $this->assertStringContainsString(
            'salary-warning-count',
            $html
        );

        $this->assertStringContainsString(
            '2',
            $html
        );

        $this->assertStringContainsString(
            'Warnings',
            $html
        );

        foreach ($warnings as $warning) {
            $this->assertStringContainsString(
                $warning,
                $html
            );
        }
    }

    /**
     * Test ID: SAL-AV05
     * Purpose: Verify payroll summary calculations.
     */
    public function testPayrollSummaryCalculatesCorrectTotals(): void
    {
        $html = $this->renderView(
            salaryRecords: $this->sampleSalaryRecords()
        );

        // Two salary records.
        $this->assertStringContainsString(
            '>2</strong>',
            $html
        );

        // Basic salary: 30,000 + 25,000 = 55,000.
        $this->assertStringContainsString(
            '৳55,000.00',
            $html
        );

        // Deductions: 1,500 + 1,000 = 2,500.
        $this->assertStringContainsString(
            '৳2,500.00',
            $html
        );

        // Net salary: 28,500 + 24,000 = 52,500.
        $this->assertStringContainsString(
            '৳52,500.00',
            $html
        );
    }

    /**
     * Test ID: SAL-AV06
     * Purpose: Verify employee selection options.
     */
    public function testEmployeeSelectionOptionsAreDisplayed(): void
    {
        $html = $this->renderView(
            employees: $this->sampleEmployees()
        );

        $this->assertStringContainsString(
            'name="employee_id"',
            $html
        );

        $this->assertStringContainsString(
            'Choose an employee',
            $html
        );

        $this->assertStringContainsString(
            'value="900001"',
            $html
        );

        $this->assertStringContainsString(
            'value="900002"',
            $html
        );

        $this->assertStringContainsString(
            'EMP-SAL-001',
            $html
        );

        $this->assertStringContainsString(
            'EMP-SAL-002',
            $html
        );
    }

    /**
     * Test ID: SAL-AV07
     * Purpose: Verify salary month input and current-month restriction.
     */
    public function testSalaryCalculationMonthInputIsDisplayed(): void
    {
        $html = $this->renderView();

        $currentMonth = date('Y-m');

        $this->assertStringContainsString(
            'type="month"',
            $html
        );

        $this->assertStringContainsString(
            'id="calculate_month"',
            $html
        );

        $this->assertStringContainsString(
            'name="month"',
            $html
        );

        $this->assertStringContainsString(
            'value="' . $currentMonth . '"',
            $html
        );

        $this->assertStringContainsString(
            'max="' . $currentMonth . '"',
            $html
        );
    }

    /**
     * Test ID: SAL-AV08
     * Purpose: Verify CSRF token in salary calculation form.
     */
    public function testCsrfTokenIsIncludedInCalculationForm(): void
    {
        $html = $this->renderView();

        $this->assertStringContainsString(
            'name="csrf_token"',
            $html
        );

        $this->assertStringContainsString(
            htmlspecialchars($this->csrfToken),
            $html
        );

        $this->assertStringContainsString(
            'action=salary-calculate',
            $html
        );
    }

    /**
     * Test ID: SAL-AV09
     * Purpose: Verify monthly salary filter form.
     */
    public function testMonthlySalaryFilterFormIsDisplayed(): void
    {
        $html = $this->renderView(
            employees: $this->sampleEmployees(),
            selectedMonth: '2095-05',
            selectedEmployeeId: 900001
        );

        $this->assertStringContainsString(
            'name="action"',
            $html
        );

        $this->assertStringContainsString(
            'value="salary-management"',
            $html
        );

        $this->assertStringContainsString(
            'id="filter_month"',
            $html
        );

        $this->assertStringContainsString(
            'value="2095-05"',
            $html
        );

        $this->assertStringContainsString(
            'id="filter_employee_id"',
            $html
        );

        $this->assertStringContainsString(
            'value="900001"',
            $html
        );

        $this->assertStringContainsString(
            'selected',
            $html
        );

        $this->assertStringContainsString(
            'Apply Filters',
            $html
        );

        $this->assertStringContainsString(
            'Clear Filters',
            $html
        );
    }

    /**
     * Test ID: SAL-AV10
     * Purpose: Verify all eight salary table columns.
     */
    public function testAllSalaryTableColumnsAreDisplayed(): void
    {
        $html = $this->renderView();

        $expectedColumns = [
            '#',
            'Employee',
            'Salary Month',
            'Basic Salary',
            'Total Deductions',
            'Net Salary',
            'Calculated At',
            'Action'
        ];

        foreach ($expectedColumns as $column) {
            $this->assertStringContainsString(
                '<th',
                $html
            );

            $this->assertStringContainsString(
                $column,
                $html,
                "Missing salary table column: {$column}"
            );
        }
    }

    /**
     * Test ID: SAL-AV11
     * Purpose: Verify empty salary records message.
     */
    public function testEmptySalaryRecordsMessageIsDisplayed(): void
    {
        $html = $this->renderView(
            salaryRecords: []
        );

        $this->assertStringContainsString(
            'No Salary Records Found',
            $html
        );

        $this->assertStringContainsString(
            'No saved salary records match your current filters.',
            $html
        );

        $this->assertStringContainsString(
            'colspan="8"',
            $html
        );
    }

    /**
     * Test ID: SAL-AV12
     * Purpose: Verify saved salary records and detail links.
     */
    public function testSavedSalaryRecordsAreDisplayed(): void
    {
        $html = $this->renderView(
            salaryRecords: $this->sampleSalaryRecords()
        );

        $this->assertStringContainsString(
            'Salary Test Employee One',
            $html
        );

        $this->assertStringContainsString(
            'Salary Test Employee Two',
            $html
        );

        $this->assertStringContainsString(
            'EMP-SAL-001',
            $html
        );

        $this->assertStringContainsString(
            'EMP-SAL-002',
            $html
        );

        $this->assertStringContainsString(
            'May 2095',
            $html
        );

        $this->assertStringContainsString(
            'View Details',
            $html
        );

        $this->assertStringContainsString(
            'action=salary-details&id=910001',
            $html
        );

        $this->assertStringContainsString(
            'action=salary-details&id=910002',
            $html
        );
    }

    /**
     * Test ID: SAL-AV13
     * Purpose: Verify BDT salary amount formatting.
     */
    public function testSalaryAmountsAreFormattedInBDT(): void
    {
        $html = $this->renderView(
            salaryRecords: [
                [
                    'id' => 910003,
                    'full_name' => 'Formatting Test Employee',
                    'employee_code' => 'EMP-SAL-003',
                    'salary_month' => '2095-05-01',
                    'basic_salary' => 30000,
                    'deductions' => 1250.50,
                    'final_salary' => 28749.50,
                    'calculated_at' => null
                ]
            ]
        );

        $this->assertStringContainsString(
            '৳30,000.00',
            $html
        );

        $this->assertStringContainsString(
            '৳1,250.50',
            $html
        );

        $this->assertStringContainsString(
            '৳28,749.50',
            $html
        );
    }

    /**
     * Test ID: SAL-AV14
     * Purpose: Verify employee information is HTML-escaped.
     */
    public function testEmployeeInformationIsHtmlEscaped(): void
    {
        $maliciousName = '<script>alert("name")</script>';
        $maliciousCode = '<img src=x onerror=alert(1)>';

        $employees = [
            [
                'employee_id' => 900004,
                'employee_code' => $maliciousCode,
                'full_name' => $maliciousName
            ]
        ];

        $records = [
            [
                'id' => 910004,
                'full_name' => $maliciousName,
                'employee_code' => $maliciousCode,
                'salary_month' => '2095-05-01',
                'basic_salary' => 30000,
                'deductions' => 0,
                'final_salary' => 30000,
                'calculated_at' => null
            ]
        ];

        $html = $this->renderView(
            employees: $employees,
            salaryRecords: $records
        );

        $this->assertStringContainsString(
            htmlspecialchars($maliciousName),
            $html
        );

        $this->assertStringContainsString(
            htmlspecialchars($maliciousCode),
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
    }
}
