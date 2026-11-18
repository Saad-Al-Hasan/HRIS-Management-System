<?php

use PHPUnit\Framework\TestCase;

class EmployeeLeaveApplicationViewTest extends TestCase
{
    private string $viewPath;
    private string $csrfToken = 'employee-leave-view-test-token';

    private array $originalSession = [];
    private array $originalGet = [];

    protected function setUp(): void
    {
        $this->viewPath = __DIR__
            . '/../app/views/employee/leave-application.php';

        $this->originalSession = $_SESSION ?? [];
        $this->originalGet = $_GET ?? [];

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Simulate an authenticated employee.
        $_SESSION = [
            'user_id' => 999991,
            'role_id' => 2,
            'full_name' => 'Leave View Test Employee',
            'csrf_token' => $this->csrfToken
        ];

        $_GET = [
            'action' => 'leave-application'
        ];

        $this->assertFileExists(
            $this->viewPath,
            'Employee leave application view was not found.'
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
     * Render the actual employee leave application view.
     */
    private function renderView(array $leaveRequests = []): string
    {
        $success = 'Leave application test success message.';
        $error = 'Leave application test error message.';
        $csrfToken = $this->csrfToken;
        $pageTitle = 'Leave Application';

        ob_start();

        try {
            include $this->viewPath;
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Class under test: Employee Leave Application View
     * Test ID: LEAVE-V01
     * Purpose: Verify that the main page renders correctly.
     */
    public function testLeaveApplicationPageRendersCorrectly(): void
    {
        $html = $this->renderView();

        $this->assertStringContainsString(
            '<h1>Leave Application</h1>',
            $html
        );

        $this->assertStringContainsString(
            'Submit a leave request and track its status.',
            $html
        );

        $this->assertStringContainsString(
            'Apply for Leave',
            $html
        );

        $this->assertStringContainsString(
            'My Leave Requests',
            $html
        );
    }

    /**
     * Test ID: LEAVE-V02
     * Purpose: Verify required leave application form fields.
     */
    public function testRequiredLeaveApplicationFieldsAreDisplayed(): void
    {
        $html = $this->renderView();

        $this->assertStringContainsString(
            'name="leave_date"',
            $html
        );

        $this->assertStringContainsString(
            'name="leave_type"',
            $html
        );

        $this->assertStringContainsString(
            'name="reason"',
            $html
        );

        $this->assertStringContainsString(
            'type="date"',
            $html
        );

        $this->assertStringContainsString(
            'required',
            $html
        );
    }

    /**
     * Test ID: LEAVE-V03
     * Purpose: Verify Full Day and Half Day options.
     */
    public function testLeaveTypeOptionsAreDisplayed(): void
    {
        $html = $this->renderView();

        $this->assertStringContainsString(
            'value="Full Day">Full Day',
            $html
        );

        $this->assertStringContainsString(
            'value="Half Day">Half Day',
            $html
        );
    }

    /**
     * Test ID: LEAVE-V04
     * Purpose: Verify both half-day period options.
     */
    public function testHalfDayPeriodOptionsAreDisplayed(): void
    {
        $html = $this->renderView();

        $this->assertStringContainsString(
            'value="first_half">First Half',
            $html
        );

        $this->assertStringContainsString(
            'value="second_half">Second Half',
            $html
        );

        $this->assertStringContainsString(
            'id="halfDayPeriodGroup"',
            $html
        );
    }

    /**
     * Test ID: LEAVE-V05
     * Purpose: Verify CSRF token is included in the form.
     */
    public function testCsrfTokenIsIncludedInApplicationForm(): void
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
    }

    /**
     * Test ID: LEAVE-V06
     * Purpose: Verify leave request table and its columns.
     */
    public function testLeaveRequestTableColumnsAreDisplayed(): void
    {
        $html = $this->renderView();

        $expectedColumns = [
            '#',
            'Leave Date',
            'Leave Type',
            'Period',
            'Reason',
            'Applied On',
            'Status'
        ];

        foreach ($expectedColumns as $column) {
            $this->assertStringContainsString(
                '<th>' . $column . '</th>',
                $html,
                "Missing leave request table column: {$column}"
            );
        }
    }

    /**
     * Test ID: LEAVE-V07
     * Purpose: Verify leave request status labels.
     */
    public function testLeaveRequestStatusesAreDisplayedCorrectly(): void
    {
        $html = $this->renderView([
            [
                'start_date' => '2095-05-15',
                'leave_type' => 'Full Day',
                'half_day_period' => null,
                'reason' => 'Pending test request',
                'created_at' => '2095-05-01 10:00:00',
                'status' => 'pending'
            ],
            [
                'start_date' => '2095-05-16',
                'leave_type' => 'Full Day',
                'half_day_period' => null,
                'reason' => 'Approved test request',
                'created_at' => '2095-05-02 10:00:00',
                'status' => 'approved'
            ],
            [
                'start_date' => '2095-05-17',
                'leave_type' => 'Full Day',
                'half_day_period' => null,
                'reason' => 'Rejected test request',
                'created_at' => '2095-05-03 10:00:00',
                'status' => 'rejected'
            ]
        ]);

        $this->assertStringContainsString(
            'status-pending',
            $html
        );

        $this->assertStringContainsString(
            'status-approved',
            $html
        );

        $this->assertStringContainsString(
            'status-rejected',
            $html
        );

        $this->assertStringContainsString(
            'Pending',
            $html
        );

        $this->assertStringContainsString(
            'Accepted',
            $html
        );

        $this->assertStringContainsString(
            'Rejected',
            $html
        );
    }

    /**
     * Test ID: LEAVE-V08
     * Purpose: Verify leave reasons are HTML-escaped.
     */
    public function testLeaveReasonIsHtmlEscaped(): void
    {
        $maliciousReason = '<script>alert("test")</script>';

        $html = $this->renderView([
            [
                'start_date' => '2095-05-15',
                'leave_type' => 'Full Day',
                'half_day_period' => null,
                'reason' => $maliciousReason,
                'created_at' => '2095-05-01 10:00:00',
                'status' => 'pending'
            ]
        ]);

        $this->assertStringContainsString(
            htmlspecialchars($maliciousReason),
            $html
        );

        $this->assertStringNotContainsString(
            $maliciousReason,
            $html
        );
    }

    /**
     * Test ID: LEAVE-V09
     * Purpose: Verify JavaScript half-day field-toggle logic.
     */
    public function testHalfDayJavaScriptLogicIsPresent(): void
    {
        $html = $this->renderView();

        $this->assertStringContainsString(
            "leaveType.value === 'Half Day'",
            $html
        );

        $this->assertStringContainsString(
            "halfDayGroup.style.display = isHalfDay ? 'block' : 'none'",
            $html
        );

        $this->assertStringContainsString(
            'halfDayPeriod.required = isHalfDay',
            $html
        );

        $this->assertStringContainsString(
            "halfDayPeriod.value = ''",
            $html
        );

        $this->assertStringContainsString(
            "leaveType.addEventListener('change', updateHalfDayField)",
            $html
        );
    }

    /**
     * Test ID: LEAVE-V10
     * Purpose: Verify employee sidebar contains Leave Application link.
     */
    public function testEmployeeSidebarContainsLeaveApplicationLink(): void
    {
        $html = $this->renderView();

        $this->assertStringContainsString(
            'action=leave-application',
            $html
        );

        $this->assertStringContainsString(
            'Leave Application',
            $html
        );
    }
}
