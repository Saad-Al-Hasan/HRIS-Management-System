<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

require_once __DIR__ . '/../app/models/Report.php';

final class ReportsExcelExportUnitTest extends TestCase
{
    private PDO $pdo;
    private string $tag;
    private int $userId;
    private int $employeeId;
    private int $otherUserId;
    private int $otherEmployeeId;
    private array $salaryIds = [];
    private array $attendanceIds = [];
    private array $leaveIds = [];
    private array $employeeIds = [];
    private array $userIds = [];
    private array $temporaryFiles = [];
    private string $department;

    protected function setUp(): void
    {
        parent::setUp();

        $database = new Database();
        $this->pdo = $database->connect();

        $this->assertSame(
            'hris_management_system_test',
            $this->pdo->query('SELECT DATABASE()')->fetchColumn(),
            'Excel export tests must never run against the live database.'
        );

        $this->tag = 'XLSEXPORT_' . bin2hex(random_bytes(5));
        $this->department = $this->tag . ' Department';

        $this->employeeId = $this->createEmployee($this->department, 0);
        $this->otherEmployeeId = $this->createEmployee($this->tag . ' Other Dept', 1);

        $this->createAttendance($this->employeeId, '2025-01-06', 'present');
        $this->createLeave($this->employeeId, '2025-02-03', 'approved');
        $this->createSalary($this->employeeId, '2025-04', 30000, 1000, 29000);
        $this->createSalary($this->otherEmployeeId, '2025-04', 20000, 500, 19500);
    }

    protected function tearDown(): void
    {
        foreach ($this->salaryIds as $id) {
            $this->deleteById('salary', $id);
        }
        foreach ($this->attendanceIds as $id) {
            $this->deleteById('attendance', $id);
        }
        foreach ($this->leaveIds as $id) {
            $this->deleteById('leave_requests', $id);
        }
        foreach ($this->employeeIds as $id) {
            $this->deleteById('employees', $id);
        }
        foreach ($this->userIds as $id) {
            $this->deleteById('users', $id);
        }
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    // Test ID: XLS-01
    // Test Case: All Four Exports Generate Valid XLSX Workbooks Without Charts
    public function testAllFourExportsGenerateValidXlsxWorkbooksWithoutCharts(): void
    {
        $expectedTitles = [
            'attendance' => 'Attendance Report',
            'employees' => 'Employee Report',
            'leave' => 'Leave Report',
            'salary' => 'Salary Report',
        ];

        foreach ($expectedTitles as $reportType => $title) {
            $filters = $this->filtersFor($reportType);
            $workbook = $this->exportWorkbook($reportType, $filters);

            $this->assertInstanceOf(Spreadsheet::class, $workbook);
            $this->assertSame($title, $workbook->getActiveSheet()->getTitle());
            $this->assertSame($title, $workbook->getProperties()->getTitle());
            $this->assertSame(0, $workbook->getActiveSheet()->getChartCount());
            $this->assertStringContainsString(
                $title . ' - MYOL HRIS',
                (string) $workbook->getActiveSheet()->getCell('A1')->getValue()
            );

            $workbook->disconnectWorksheets();
        }
    }

    // Test ID: XLS-02
    // Test Case: Employee Export Contains Summary, Headers, and Filtered Employee Row
    public function testEmployeeExportContainsSummaryHeadersAndFilteredEmployeeRow(): void
    {
        $workbook = $this->exportWorkbook('employees', [
            'department' => $this->department,
        ]);
        $sheet = $workbook->getActiveSheet();

        $this->assertSame('Total Employees', $sheet->getCell('A6')->getValue());
        $this->assertSame(1, (int) $sheet->getCell('B6')->getCalculatedValue());
        $this->assertSame('Employee ID', $sheet->getCell('A13')->getValue());
        $this->assertSame('Employee Name', $sheet->getCell('D13')->getValue());
        $this->assertSame($this->department, $sheet->getCell('H14')->getValue());
        $this->assertSame('Records: 1', $sheet->getCell('A4')->getValue());

        $workbook->disconnectWorksheets();
    }

    // Test ID: XLS-03
    // Test Case: Attendance Export Omits Unwanted Columns and Exports Filtered Record
    public function testAttendanceExportOmitsPrivateViewColumnsAndExportsFilteredRecord(): void
    {
        $workbook = $this->exportWorkbook('attendance', [
            'employee_id' => $this->employeeId,
            'from_date' => '2025-01-06',
            'to_date' => '2025-01-06',
        ]);
        $sheet = $workbook->getActiveSheet();

        $headers = [];
        for ($column = 1; $column <= 12; $column++) {
            $headers[] = (string) $sheet->getCell([$column, 13])->getValue();
        }

        $this->assertSame([
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
        ], $headers);

        $this->assertNotContains('Leave Type', $headers);
        $this->assertNotContains('Half-Day Period', $headers);
        $this->assertNotContains('Designation', $headers);
        $this->assertSame('present', $sheet->getCell('K14')->getValue());
        $this->assertSame('2025-01-06', $sheet->getCell('F14')->getValue());

        $workbook->disconnectWorksheets();
    }

    // Test ID: XLS-04
    // Test Case: Leave Export Contains Status Summary and Accepted Leave Row
    public function testLeaveExportContainsStatusSummaryAndAcceptedLeaveRow(): void
    {
        $workbook = $this->exportWorkbook('leave', [
            'employee_id' => $this->employeeId,
            'from_date' => '2025-02-03',
            'to_date' => '2025-02-03',
        ]);
        $sheet = $workbook->getActiveSheet();

        $this->assertSame('Total Requests', $sheet->getCell('A6')->getValue());
        $this->assertSame(1, (int) $sheet->getCell('B6')->getCalculatedValue());
        $this->assertSame('Accepted', $sheet->getCell('A8')->getValue());
        $this->assertSame(1, (int) $sheet->getCell('B8')->getCalculatedValue());
        $this->assertSame('Status', $sheet->getCell('K13')->getValue());
        $this->assertSame('Accepted', $sheet->getCell('K14')->getValue());

        $workbook->disconnectWorksheets();
    }

    // Test ID: XLS-05
    // Test Case: Salary Export Applies Department and Month Filters to Rows and Summary
    public function testSalaryExportAppliesDepartmentAndMonthFiltersToRowsAndSummary(): void
    {
        $workbook = $this->exportWorkbook('salary', [
            'department' => $this->department,
            'month' => '2025-04',
        ]);
        $sheet = $workbook->getActiveSheet();

        $this->assertSame('Saved Records', $sheet->getCell('A6')->getValue());
        $this->assertSame(1, (int) $sheet->getCell('B6')->getCalculatedValue());
        $this->assertSame('Total Basic Salary (BDT)', $sheet->getCell('A7')->getValue());
        $this->assertEqualsWithDelta(30000, (float) $sheet->getCell('B7')->getCalculatedValue(), 0.001);
        $this->assertEqualsWithDelta(1000, (float) $sheet->getCell('B8')->getCalculatedValue(), 0.001);
        $this->assertEqualsWithDelta(29000, (float) $sheet->getCell('B9')->getCalculatedValue(), 0.001);

        $this->assertSame('Department', $sheet->getCell('E13')->getValue());
        $this->assertSame($this->department, $sheet->getCell('E14')->getValue());
        $this->assertSame('2025-04-01', $sheet->getCell('F14')->getValue());
        $this->assertSame('Records: 1', $sheet->getCell('A4')->getValue());

        $workbook->disconnectWorksheets();
    }

    private function filtersFor(string $reportType): array
    {
        return match ($reportType) {
            'attendance' => [
                'employee_id' => $this->employeeId,
                'from_date' => '2025-01-06',
                'to_date' => '2025-01-06',
            ],
            'employees' => ['department' => $this->department],
            'leave' => [
                'employee_id' => $this->employeeId,
                'from_date' => '2025-02-03',
                'to_date' => '2025-02-03',
            ],
            'salary' => [
                'department' => $this->department,
                'month' => '2025-04',
            ],
            default => [],
        };
    }

    private function exportWorkbook(string $reportType, array $filters): Spreadsheet
    {
        $projectRoot = dirname(__DIR__);
        $helperPath = tempnam(sys_get_temp_dir(), 'hris_xlsx_runner_');
        $xlsxPath = tempnam(sys_get_temp_dir(), 'hris_xlsx_result_');

        if ($helperPath === false || $xlsxPath === false) {
            $this->fail('Unable to create temporary files for Excel export testing.');
        }

        $this->temporaryFiles[] = $helperPath;
        $this->temporaryFiles[] = $xlsxPath;

        $rootLiteral = var_export($projectRoot, true);
        $helper = <<<PHP
<?php
require {$rootLiteral} . '/tests/bootstrap.php';
require {$rootLiteral} . '/app/controllers/ReportsController.php';
Auth::startSession();
\$_SESSION['user_id'] = 1;
\$_SESSION['role_id'] = 1;
\$_GET = json_decode(base64_decode(\$argv[2]), true) ?: [];
\$_GET['report'] = \$argv[1];
\$controller = new ReportsController();
\$controller->export();
PHP;
        file_put_contents($helperPath, $helper);

        $query = base64_encode(json_encode($filters, JSON_THROW_ON_ERROR));
        $stdout = fopen('php://temp', 'w+b');
        $stderr = fopen('php://temp', 'w+b');

        $process = proc_open(
            [PHP_BINARY, $helperPath, $reportType, $query],
            [
                0 => ['pipe', 'r'],
                1 => $stdout,
                2 => $stderr,
            ],
            $pipes,
            $projectRoot
        );

        if (!is_resource($process)) {
            $this->fail('Could not launch the isolated Excel export process.');
        }

        fclose($pipes[0]);
        $exitCode = proc_close($process);

        rewind($stdout);
        $xlsxBytes = stream_get_contents($stdout);
        fclose($stdout);

        rewind($stderr);
        $errorText = stream_get_contents($stderr);
        fclose($stderr);

        $this->assertSame(
            0,
            $exitCode,
            'Excel export subprocess failed: ' . (string) $errorText
        );
        $this->assertNotEmpty($xlsxBytes, 'Excel export returned an empty file.');
        $this->assertStringStartsWith('PK', (string) $xlsxBytes);

        file_put_contents($xlsxPath, $xlsxBytes);

        $reader = IOFactory::createReader('Xlsx');
        $reader->setIncludeCharts(true);
        $workbook = $reader->load($xlsxPath);

        return $workbook;
    }

    private function createEmployee(string $department, int $index): int
    {
        $username = $this->tag . '_U' . $index;
        $email = strtolower($username) . '@test.local';

        $stmt = $this->pdo->prepare(
            "INSERT INTO users (role_id, username, email, password, full_name, status)
             VALUES (2, ?, ?, 'unit_test_hash', ?, 'active')"
        );
        $stmt->execute([$username, $email, $this->tag . ' Employee ' . $index]);

        $userId = (int) $this->pdo->lastInsertId();
        $this->userIds[] = $userId;

        $stmt = $this->pdo->prepare(
            'INSERT INTO employees
             (user_id, employee_code, department, designation, monthly_salary, joining_date)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $this->tag . '_E' . $index,
            $department,
            'Unit Test Designation',
            30000.00,
            '2020-01-01',
        ]);

        $employeeId = (int) $this->pdo->lastInsertId();
        $this->employeeIds[] = $employeeId;

        return $employeeId;
    }

    private function createAttendance(int $employeeId, string $date, string $status): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO attendance
             (employee_id, attendance_date, entry_time, entry_status, exit_time, exit_status, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $employeeId,
            $date,
            $date . ' 09:00:00',
            $status === 'late' ? 'Entry - Late' : 'In Time',
            $date . ' 18:00:00',
            'Exit - in time',
            $status,
        ]);

        $this->attendanceIds[] = (int) $this->pdo->lastInsertId();
    }

    private function createLeave(int $employeeId, string $date, string $status): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO leave_requests
             (employee_id, leave_type, half_day_period, start_date, end_date, reason, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $employeeId,
            'Full Day',
            null,
            $date,
            $date,
            $this->tag . ' test leave',
            $status,
        ]);

        $this->leaveIds[] = (int) $this->pdo->lastInsertId();
    }

    private function createSalary(
        int $employeeId,
        string $month,
        float $basic,
        float $deductions,
        float $final
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO salary
             (employee_id, salary_month, basic_salary, deductions, final_salary, calculated_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$employeeId, $month . '-01', $basic, $deductions, $final]);

        $this->salaryIds[] = (int) $this->pdo->lastInsertId();
    }

    private function deleteById(string $table, int $id): void
    {
        $allowed = ['salary', 'attendance', 'leave_requests', 'employees', 'users'];

        if (!in_array($table, $allowed, true)) {
            throw new RuntimeException('Unsafe test cleanup table.');
        }

        $stmt = $this->pdo->prepare("DELETE FROM {$table} WHERE id = ?");
        $stmt->execute([$id]);
    }
}
