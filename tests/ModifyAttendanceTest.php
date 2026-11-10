<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../app/controllers/AttendanceController.php';
require_once __DIR__ . '/../app/models/Attendance.php';

// Test helper: capture view data and redirects without terminating PHPUnit.
class TestableModifyAttendanceController extends AttendanceController
{
    public string $capturedView = '';
    public array $capturedData = [];
    public string $redirectUrl = '';

    protected function view(string $view, array $data = []): void
    {
        $this->capturedView = $view;
        $this->capturedData = $data;
    }

    protected function redirect(string $url): void
    {
        $this->redirectUrl = $url;
    }
}

class ModifyAttendanceTest extends TestCase
{
    private TestableModifyAttendanceController $controller;
    // Database and temporary data for attendance modification tests.
    private PDO $pdo;
    private int $testUserId;
    private int $testEmployeeId;
    private ?int $createdScheduleId = null;
    private string $testDateWithExit = '2026-02-10';
    private string $testDateEntryOnly = '2026-02-11';

    // Temporary employee without an assigned or default schedule.
    private ?int $noScheduleEmployeeId = null;
    private ?int $noScheduleUserId = null;

    // Model under test: Attendance
    private Attendance $attendanceModel;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Simulate administrator login.
        $_SESSION = [
            'user_id' => 999,
            'role_id' => 1,
            'csrf_token' => 'test-csrf-token'
        ];

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [];

        $this->controller = new TestableModifyAttendanceController();

        // Class under test: AttendanceController
        // Supporting setup: create isolated test database records.
        $database = new Database();
        $this->pdo = $database->connect();

        // Create a temporary employee account.
        $username = 'MOD_TEST_' . uniqid();

        $stmt = $this->pdo->prepare("
        INSERT INTO users
            (role_id, username, email, password, full_name)
        VALUES
            (2, ?, ?, 'test_hash', 'Modify Attendance Test Employee')
        ");

        $stmt->execute([
            $username,
            $username . '@test.local'
        ]);

        $this->testUserId = (int) $this->pdo->lastInsertId();

        // Create the employee profile.
        $stmt = $this->pdo->prepare("
        INSERT INTO employees
            (user_id, employee_code, department)
            VALUES
        (?, ?, ?)
        ");

        $stmt->execute([
            $this->testUserId,
            $username,
            'MOD_TEST'
        ]);

        $this->testEmployeeId = (int) $this->pdo->lastInsertId();

        // Ensure the default 9 AM - 6 PM schedule exists.
        $stmt = $this->pdo->prepare("
        SELECT id
        FROM work_schedules
        WHERE name = ?
        LIMIT 1
        ");

        $stmt->execute(['9 AM - 6 PM']);
        $scheduleId = $stmt->fetchColumn();

        if (!$scheduleId) {
            $stmt = $this->pdo->prepare("
        INSERT INTO work_schedules
            (name, expected_entry_time, expected_exit_time, working_hours)
        VALUES
            ('9 AM - 6 PM', '09:00:00', '18:00:00', 9)
        ");

            $stmt->execute();
            $this->createdScheduleId = (int) $this->pdo->lastInsertId();
        }

        // Initialize the Attendance model for transaction tests.
        $this->attendanceModel = new Attendance();
    }

    protected function tearDown(): void
    {

        // Class under test: Attendance
        // Cleanup: remove temporary attendance and scan-history test data.
        $stmt = $this->pdo->prepare(
            "DELETE FROM employees WHERE id = ?"
        );
        $stmt->execute([$this->testEmployeeId]);

        $stmt = $this->pdo->prepare(
            "DELETE FROM users WHERE id = ?"
        );
        $stmt->execute([$this->testUserId]);

        // Remove the schedule only if this test created it.
        if ($this->createdScheduleId !== null) {
            $stmt = $this->pdo->prepare(
                "DELETE FROM work_schedules WHERE id = ?"
            );
            $stmt->execute([$this->createdScheduleId]);
        }

        $_POST = [];
        $_GET = [];
        $_SESSION = [];
    }

    // Class under test: AttendanceController
    // Method under test: modify()
    // Test ID: MOD-01
    // Purpose: Verify the Modify Attendance page loads for an administrator.
    public function testModifyLoadsPageForAdministrator(): void
    {
        // Call
        $this->controller->modify();

        // Assertions
        $this->assertSame(
            'admin/attendance/modify',
            $this->controller->capturedView
        );

        $this->assertArrayHasKey(
            'employees',
            $this->controller->capturedData
        );
    }

    // Class under test: AttendanceController
    // Method under test: saveModifiedAttendance()
    // Test ID: MOD-02
    // Purpose: Reject requests that are not POST requests.
    public function testSaveRejectsNonPostRequest(): void
    {
        // Setup
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // Call
        $this->controller->saveModifiedAttendance();

        // Assertion
        $this->assertSame(
            '/HRIS-Management-System/public/index.php?action=modify-attendance',
            $this->controller->redirectUrl
        );
    }

    // Class under test: AttendanceController
    // Method under test: saveModifiedAttendance()
    // Test ID: MOD-03
    // Purpose: Reject an invalid CSRF token.
    public function testSaveRejectsInvalidCsrfToken(): void
    {
        // Setup
        $_POST = ['csrf_token' => 'wrong-token'];

        // Call
        $this->controller->saveModifiedAttendance();

        // Assertion
        $this->assertSame(
            'Invalid or expired form token. Please refresh and try again.',
            $_SESSION['modify_attendance_error']
        );
    }

    // Class under test: AttendanceController
    // Method under test: saveModifiedAttendance()
    // Test ID: MOD-04
    // Purpose: Reject an invalid employee ID.
    public function testSaveRejectsInvalidEmployeeId(): void
    {
        // Setup
        $_POST = [
            'csrf_token' => 'test-csrf-token',
            'employee_id' => '0',
            'attendance_date' => '2026-01-15',
            'entry_time' => '09:00',
            'exit_time' => ''
        ];

        // Call
        $this->controller->saveModifiedAttendance();

        // Assertion
        $this->assertSame(
            'Please select a valid employee.',
            $_SESSION['modify_attendance_error']
        );
    }

    // Class under test: AttendanceController
    // Method under test: saveModifiedAttendance()
    // Test ID: MOD-05
    // Purpose: Reject an invalid calendar date.
    public function testSaveRejectsInvalidAttendanceDate(): void
    {
        // Setup
        $_POST = [
            'csrf_token' => 'test-csrf-token',
            'employee_id' => '1',
            'attendance_date' => '2026-02-30',
            'entry_time' => '09:00',
            'exit_time' => ''
        ];

        // Call
        $this->controller->saveModifiedAttendance();

        // Assertion
        $this->assertSame(
            'Please select a valid attendance date.',
            $_SESSION['modify_attendance_error']
        );
    }

    // Class under test: AttendanceController
    // Method under test: saveModifiedAttendance()
    // Test ID: MOD-06
    // Purpose: Reject requests with both entry and exit times empty.
    public function testSaveRejectsEmptyEntryAndExitTimes(): void
    {
        // Setup
        $_POST = [
            'csrf_token' => 'test-csrf-token',
            'employee_id' => '1',
            'attendance_date' => '2026-01-15',
            'entry_time' => '',
            'exit_time' => ''
        ];

        // Call
        $this->controller->saveModifiedAttendance();

        // Assertion
        $this->assertSame(
            'Enter an entry time, an exit time, or both.',
            $_SESSION['modify_attendance_error']
        );
    }

    // Class under test: AttendanceController
    // Method under test: saveModifiedAttendance()
    // Test ID: MOD-07
    // Purpose: Reject an invalid entry time format.
    public function testSaveRejectsInvalidEntryTime(): void
    {
        // Setup
        $_POST = [
            'csrf_token' => 'test-csrf-token',
            'employee_id' => '1',
            'attendance_date' => '2026-01-15',
            'entry_time' => '25:90',
            'exit_time' => ''
        ];

        // Call
        $this->controller->saveModifiedAttendance();

        // Assertion
        $this->assertSame(
            'Please enter a valid entry time.',
            $_SESSION['modify_attendance_error']
        );
    }

    // Class under test: AttendanceController
    // Method under test: saveModifiedAttendance()
    // Test ID: MOD-08
    // Purpose: Reject an invalid exit time format.
    public function testSaveRejectsInvalidExitTime(): void
    {
        // Setup
        $_POST = [
            'csrf_token' => 'test-csrf-token',
            'employee_id' => '1',
            'attendance_date' => '2026-01-15',
            'entry_time' => '09:00',
            'exit_time' => '27:00'
        ];

        // Call
        $this->controller->saveModifiedAttendance();

        // Assertion
        $this->assertSame(
            'Please enter a valid exit time.',
            $_SESSION['modify_attendance_error']
        );
    }

    // Class under test: AttendanceController
    // Method under test: saveModifiedAttendance()
    // Test ID: MOD-09
    // Purpose: Reject an exit time earlier than or equal to entry time.
    public function testSaveRejectsExitTimeBeforeEntryTime(): void
    {
        // Setup
        $_POST = [
            'csrf_token' => 'test-csrf-token',
            'employee_id' => '1',
            'attendance_date' => '2026-01-15',
            'entry_time' => '17:00',
            'exit_time' => '09:00'
        ];

        // Call
        $this->controller->saveModifiedAttendance();

        // Assertion
        $this->assertSame(
            'Exit time must be later than entry time.',
            $_SESSION['modify_attendance_error']
        );
    }



    // Class under test: AttendanceController
    // Method under test: saveModifiedAttendance()
    // Test ID: MOD-10
    // Purpose: Verify entry and exit times are saved with two scan-history records.
    public function testSaveCreatesAttendanceWithEntryAndExit(): void
    {
        // Setup
        $_POST = [
            'csrf_token' => 'test-csrf-token',
            'employee_id' => (string) $this->testEmployeeId,
            'attendance_date' => $this->testDateWithExit,
            'entry_time' => '09:00',
            'exit_time' => '18:00'
        ];

        // Call
        $this->controller->saveModifiedAttendance();

        // Verify attendance record.
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM attendance
            WHERE employee_id = ?
              AND attendance_date = ?
        ");

        $stmt->execute([
            $this->testEmployeeId,
            $this->testDateWithExit
        ]);

        $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

        // Assertions
        $this->assertIsArray($attendance);
        $this->assertSame('09:00:00', substr($attendance['entry_time'], 11));
        $this->assertSame('18:00:00', substr($attendance['exit_time'], 11));
        $this->assertSame('present', $attendance['status']);

        // Verify both RFID scan-history records.
        $stmt = $this->pdo->prepare("
            SELECT scan_type
            FROM attendance_scan_history
            WHERE employee_id = ?
              AND attendance_date = ?
            ORDER BY scan_time ASC
        ");

        $stmt->execute([
            $this->testEmployeeId,
            $this->testDateWithExit
        ]);

        $scanTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $this->assertSame(['entry', 'exit'], $scanTypes);

        // Verify controller success message.
        $this->assertArrayHasKey(
            'modify_attendance_success',
            $_SESSION
        );
    }

    // Class under test: AttendanceController
    // Method under test: saveModifiedAttendance()
    // Test ID: MOD-11
    // Purpose: Verify entry-only attendance is saved without an exit scan.
    public function testSaveCreatesAttendanceWithEntryOnly(): void
    {
        // Setup
        $_POST = [
            'csrf_token' => 'test-csrf-token',
            'employee_id' => (string) $this->testEmployeeId,
            'attendance_date' => $this->testDateEntryOnly,
            'entry_time' => '09:15',
            'exit_time' => ''
        ];

        // Call
        $this->controller->saveModifiedAttendance();

        // Verify attendance record.
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM attendance
            WHERE employee_id = ?
              AND attendance_date = ?
        ");

        $stmt->execute([
            $this->testEmployeeId,
            $this->testDateEntryOnly
        ]);

        $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

        // Assertions
        $this->assertIsArray($attendance);
        $this->assertSame('09:15:00', substr($attendance['entry_time'], 11));
        $this->assertNull($attendance['exit_time']);
        $this->assertSame('present', $attendance['status']);

        // Verify only one entry scan was recorded.
        $stmt = $this->pdo->prepare("
            SELECT scan_type
            FROM attendance_scan_history
            WHERE employee_id = ?
              AND attendance_date = ?
        ");

        $stmt->execute([
            $this->testEmployeeId,
            $this->testDateEntryOnly
        ]);

        $scanTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $this->assertSame(['entry'], $scanTypes);

        // Verify controller success message.
        $this->assertArrayHasKey(
            'modify_attendance_success',
            $_SESSION
        );
    }


    // Class under test: AttendanceController
    // Method under test: saveModifiedAttendance()
    // Test ID: MOD-12
    // Purpose: Verify duplicate attendance is rejected.
    public function testSaveRejectsDuplicateAttendance(): void
    {
        // Setup: create an existing attendance record.
        $stmt = $this->pdo->prepare("
            INSERT INTO attendance
                (employee_id, attendance_date, entry_time, entry_status, status)
            VALUES
                (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $this->testEmployeeId,
            $this->testDateWithExit,
            $this->testDateWithExit . ' 09:00:00',
            'Entry - In Time',
            'present'
        ]);

        // Setup: submit another attendance record for the same date.
        $_POST = [
            'csrf_token' => 'test-csrf-token',
            'employee_id' => (string) $this->testEmployeeId,
            'attendance_date' => $this->testDateWithExit,
            'entry_time' => '09:15',
            'exit_time' => ''
        ];

        // Call
        $this->controller->saveModifiedAttendance();

        // Assertion
        $this->assertSame(
            'Attendance already exists for this employee on the selected date.',
            $_SESSION['modify_attendance_error']
        );

        // Verify the original attendance record was not changed.
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM attendance
            WHERE employee_id = ?
              AND attendance_date = ?
        ");

        $stmt->execute([
            $this->testEmployeeId,
            $this->testDateWithExit
        ]);

        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    // Class under test: AttendanceController
    // Method under test: saveModifiedAttendance()
    // Test ID: MOD-13
    // Purpose: Verify attendance cannot be saved for a nonexistent employee.
    public function testSaveRejectsNonexistentEmployee(): void
    {
        // Setup
        $_POST = [
            'csrf_token' => 'test-csrf-token',
            'employee_id' => '999999999',
            'attendance_date' => '2026-02-12',
            'entry_time' => '09:00',
            'exit_time' => ''
        ];

        // Call
        $this->controller->saveModifiedAttendance();

        // Assertion
        $this->assertSame(
            'The selected employee could not be found.',
            $_SESSION['modify_attendance_error']
        );
    }



    // Class under test: AttendanceController
    // Supporting class: WorkSchedule
    // Method under test: saveModifiedAttendance()
    // Test ID: MOD-14
    // Purpose: Verify attendance is rejected when no effective schedule exists.
    public function testSaveRejectsEmployeeWithoutSchedule(): void
    {
        // Setup: locate the default schedule.
        $stmt = $this->pdo->prepare("
            SELECT id, name
            FROM work_schedules
            WHERE name = '9 AM - 6 PM'
            LIMIT 1
        ");
        $stmt->execute();

        $defaultSchedule = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse(
            $defaultSchedule,
            'The default schedule must exist before this test.'
        );

        $originalName = $defaultSchedule['name'];
        $temporaryName = 'TEMP_DISABLED_' . uniqid();

        try {
            // Temporarily disable the default fallback schedule.
            $stmt = $this->pdo->prepare("
                UPDATE work_schedules
                SET name = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $temporaryName,
                $defaultSchedule['id']
            ]);

            // Setup: submit attendance for the employee without an assigned schedule.
            $_POST = [
                'csrf_token' => 'test-csrf-token',
                'employee_id' => (string) $this->testEmployeeId,
                'attendance_date' => '2026-02-12',
                'entry_time' => '09:00',
                'exit_time' => ''
            ];

            // Call
            $this->controller->saveModifiedAttendance();

            // Assertion
            $this->assertSame(
                'No work schedule is available for this employee.',
                $_SESSION['modify_attendance_error']
            );
        } finally {
            // Always restore the original default schedule name.
            $stmt = $this->pdo->prepare("
                UPDATE work_schedules
                SET name = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $originalName,
                $defaultSchedule['id']
            ]);
        }
    }



    // Class under test: AttendanceController
    // Method under test: saveModifiedAttendance()
    // Test ID: MOD-15
    // Purpose: Verify an existing attendance entry can receive an exit time.
    public function testSaveRecordsExitForExistingEntry(): void
    {
        // Setup: create an open attendance record.
        $date = '2026-02-13';

        $stmt = $this->pdo->prepare("
            INSERT INTO attendance
                (employee_id, attendance_date, entry_time, entry_status, status)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $this->testEmployeeId,
            $date,
            $date . ' 09:00:00',
            'Entry - In Time',
            'present'
        ]);

        $_POST = [
            'csrf_token' => 'test-csrf-token',
            'employee_id' => (string) $this->testEmployeeId,
            'attendance_date' => $date,
            'entry_time' => '',
            'exit_time' => '18:30'
        ];

        // Call
        $this->controller->saveModifiedAttendance();

        // Verify the updated attendance record.
        $stmt = $this->pdo->prepare("
            SELECT exit_time, exit_status
            FROM attendance
            WHERE employee_id = ? AND attendance_date = ?
        ");

        $stmt->execute([$this->testEmployeeId, $date]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        // Assertions
        $this->assertSame('18:30:00', substr($record['exit_time'], 11));
        $this->assertSame('Exit - In Time', $record['exit_status']);

        // Verify the exit scan history.
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM attendance_scan_history
            WHERE employee_id = ?
              AND attendance_date = ?
              AND scan_type = 'exit'
        ");

        $stmt->execute([$this->testEmployeeId, $date]);

        $this->assertSame(1, (int) $stmt->fetchColumn());
    }



    // Class under test: AttendanceController
    // Method under test: saveModifiedAttendance()
    // Test ID: MOD-16
    // Purpose: Reject exit-only modification without an existing entry.
    public function testSaveRejectsExitWithoutExistingEntry(): void
    {
        // Setup
        $_POST = [
            'csrf_token' => 'test-csrf-token',
            'employee_id' => (string) $this->testEmployeeId,
            'attendance_date' => '2026-02-14',
            'entry_time' => '',
            'exit_time' => '18:00'
        ];

        // Call
        $this->controller->saveModifiedAttendance();

        // Assertion
        $this->assertSame(
            'No existing entry record was found for this employee and date.',
            $_SESSION['modify_attendance_error']
        );
    }



    // Class under test: AttendanceController
    // Method under test: saveModifiedAttendance()
    // Test ID: MOD-17
    // Purpose: Reject an exit-only modification when an exit already exists.
    public function testSaveRejectsDuplicateExit(): void
    {
        // Setup
        $date = '2026-02-15';

        $stmt = $this->pdo->prepare("
            INSERT INTO attendance
                (employee_id, attendance_date, entry_time, entry_status,
                 exit_time, exit_status, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $this->testEmployeeId,
            $date,
            $date . ' 09:00:00',
            'Entry - In Time',
            $date . ' 18:00:00',
            'Exit - In Time',
            'present'
        ]);

        $_POST = [
            'csrf_token' => 'test-csrf-token',
            'employee_id' => (string) $this->testEmployeeId,
            'attendance_date' => $date,
            'entry_time' => '',
            'exit_time' => '19:00'
        ];

        // Call
        $this->controller->saveModifiedAttendance();

        // Assertion
        $this->assertSame(
            'An exit time has already been recorded for this employee and date.',
            $_SESSION['modify_attendance_error']
        );
    }



    // Class under test: AttendanceController
    // Method under test: saveModifiedAttendance()
    // Test ID: MOD-18
    // Purpose: Reject an exit time earlier than the existing entry time.
    public function testSaveRejectsExitEarlierThanExistingEntry(): void
    {
        // Setup
        $date = '2026-02-16';

        $stmt = $this->pdo->prepare("
            INSERT INTO attendance
                (employee_id, attendance_date, entry_time, entry_status, status)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $this->testEmployeeId,
            $date,
            $date . ' 17:00:00',
            'Entry - Late',
            'late'
        ]);

        $_POST = [
            'csrf_token' => 'test-csrf-token',
            'employee_id' => (string) $this->testEmployeeId,
            'attendance_date' => $date,
            'entry_time' => '',
            'exit_time' => '09:00'
        ];

        // Call
        $this->controller->saveModifiedAttendance();

        // Assertion
        $this->assertSame(
            'Exit time must be later than the recorded entry time.',
            $_SESSION['modify_attendance_error']
        );
    }



    // Class under test: Attendance
    // Method under test: createManualAttendanceWithHistory()
    // Test ID: MOD-19
    // Purpose: Verify manual attendance and both scan records are committed.
    public function testCreateManualAttendanceWithHistoryCommitsAllRecords(): void
    {
        // Setup
        $date = '2026-02-20';

        // Call
        $attendanceId = $this->attendanceModel->createManualAttendanceWithHistory(
            $this->testEmployeeId,
            $date,
            $date . ' 09:00:00',
            'Entry - In Time',
            'present',
            $date . ' 18:00:00',
            'Exit - In Time'
        );

        // Assertions: attendance was created.
        $this->assertGreaterThan(0, $attendanceId);

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM attendance
            WHERE id = ? AND employee_id = ? AND attendance_date = ?
        ");
        $stmt->execute([$attendanceId, $this->testEmployeeId, $date]);

        $this->assertSame(1, (int) $stmt->fetchColumn());

        // Assertions: both scan-history records were committed.
        $stmt = $this->pdo->prepare("
            SELECT scan_type
            FROM attendance_scan_history
            WHERE employee_id = ? AND attendance_date = ?
            ORDER BY scan_time ASC
        ");
        $stmt->execute([$this->testEmployeeId, $date]);

        $this->assertSame(
            ['entry', 'exit'],
            $stmt->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    // Class under test: Attendance
    // Method under test: createManualAttendanceWithHistory()
    // Test ID: MOD-20
    // Purpose: Verify duplicate attendance is rejected without extra records.
    public function testCreateManualAttendanceWithHistoryRejectsDuplicate(): void
    {
        // Setup
        $date = '2026-02-21';

        $this->attendanceModel->createManualAttendanceWithHistory(
            $this->testEmployeeId,
            $date,
            $date . ' 09:00:00',
            'Entry - In Time',
            'present',
            null,
            null
        );

        // Call and assertion
        try {
            $this->attendanceModel->createManualAttendanceWithHistory(
                $this->testEmployeeId,
                $date,
                $date . ' 09:15:00',
                'Entry - Late',
                'late',
                null,
                null
            );

            $this->fail('Expected duplicate attendance to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'Attendance already exists for this employee on the selected date.',
                $exception->getMessage()
            );
        }

        // Verify only one attendance and one entry scan remain.
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM attendance
            WHERE employee_id = ? AND attendance_date = ?
        ");
        $stmt->execute([$this->testEmployeeId, $date]);
        $this->assertSame(1, (int) $stmt->fetchColumn());

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM attendance_scan_history
            WHERE employee_id = ? AND attendance_date = ?
        ");
        $stmt->execute([$this->testEmployeeId, $date]);
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    // Class under test: Attendance
    // Method under test: recordExitWithHistory()
    // Test ID: MOD-21
    // Purpose: Verify an existing attendance record and exit scan are committed.
    public function testRecordExitWithHistoryCommitsExitAndScan(): void
    {
        // Setup
        $date = '2026-02-22';

        $stmt = $this->pdo->prepare("
            INSERT INTO attendance
                (employee_id, attendance_date, entry_time, entry_status, status)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $this->testEmployeeId,
            $date,
            $date . ' 09:00:00',
            'Entry - In Time',
            'present'
        ]);

        $attendanceId = (int) $this->pdo->lastInsertId();

        // Call
        $result = $this->attendanceModel->recordExitWithHistory(
            $attendanceId,
            $this->testEmployeeId,
            $date,
            $date . ' 18:00:00',
            'Exit - In Time'
        );

        // Assertions
        $this->assertTrue($result);

        $stmt = $this->pdo->prepare("
            SELECT exit_time, exit_status
            FROM attendance
            WHERE id = ?
        ");
        $stmt->execute([$attendanceId]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('18:00:00', substr($record['exit_time'], 11));
        $this->assertSame('Exit - In Time', $record['exit_status']);

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM attendance_scan_history
            WHERE employee_id = ?
              AND attendance_date = ?
              AND scan_type = 'exit'
        ");
        $stmt->execute([$this->testEmployeeId, $date]);

        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    // Class under test: Attendance
    // Method under test: recordExitWithHistory()
    // Test ID: MOD-22
    // Purpose: Verify a missing attendance record returns false and adds no exit scan.
    public function testRecordExitWithHistoryRollsBackWhenAttendanceIsMissing(): void
    {
        // Setup
        $date = '2026-02-23';
        $missingAttendanceId = 999999999;

        // Call
        $result = $this->attendanceModel->recordExitWithHistory(
            $missingAttendanceId,
            $this->testEmployeeId,
            $date,
            $date . ' 18:00:00',
            'Exit - In Time'
        );

        // Assertions
        $this->assertFalse($result);

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM attendance_scan_history
            WHERE employee_id = ?
              AND attendance_date = ?
              AND scan_type = 'exit'
        ");
        $stmt->execute([$this->testEmployeeId, $date]);

        $this->assertSame(0, (int) $stmt->fetchColumn());
    }


}
