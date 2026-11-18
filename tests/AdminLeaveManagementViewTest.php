<?php

use PHPUnit\Framework\TestCase;

class AdminLeaveManagementViewTest extends TestCase
{
    private string $viewPath;
    private string $sidebarPath;

    private string $csrfToken = 'admin-leave-view-test-token';

    private array $originalSession = [];
    private array $originalGet = [];

    protected function setUp(): void
    {
        $this->viewPath = __DIR__
            . '/../app/views/admin/leaves/index.php';

        $this->sidebarPath = __DIR__
            . '/../app/views/admin/layouts/sidebar.php';

        $this->originalSession = $_SESSION ?? [];
        $this->originalGet = $_GET ?? [];

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Simulate an authenticated administrator.
        $_SESSION = [
            'user_id' => 999992,
            'role_id' => 1,
            'full_name' => 'Leave View Test Admin',
            'csrf_token' => $this->csrfToken
        ];

        $_GET = [
            'action' => 'leave-management'
        ];

        $this->assertFileExists(
            $this->viewPath,
            'Admin leave management view was not found.'
        );

        $this->assertFileExists(
            $this->sidebarPath,
            'Admin sidebar view was not found.'
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
     * Render the actual admin leave management view.
     */
    private function renderView(
        array $leaveRequests = [],
        string $employeeCode = '',
        string $success = '',
        string $error = ''
    ): string {
        $csrfToken = $this->csrfToken;
        $pageTitle = 'Leave Management';

        ob_start();

        try {
            include $this->viewPath;

            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Prepare sample leave request data.
     */
    private function sampleLeaveRequests(): array
    {
        return [
            [
                'id' => 900001,
                'employee_code' => 'EMP-TEST-001',
                'full_name' => 'Test Employee One',
                'start_date' => '2095-05-15',
                'leave_type' => 'Full Day',
                'half_day_period' => null,
                'reason' => 'Full day leave test.',
                'created_at' => '2095-05-01 10:00:00',
                'status' => 'pending'
            ],
            [
                'id' => 900002,
                'employee_code' => 'EMP-TEST-002',
                'full_name' => 'Test Employee Two',
                'start_date' => '2095-05-16',
                'leave_type' => 'Half Day',
                'half_day_period' => 'first_half',
                'reason' => 'First half leave test.',
                'created_at' => '2095-05-02 10:00:00',
                'status' => 'approved'
            ],
            [
                'id' => 900003,
                'employee_code' => 'EMP-TEST-003',
                'full_name' => 'Test Employee Three',
                'start_date' => '2095-05-17',
                'leave_type' => 'Half Day',
                'half_day_period' => 'second_half',
                'reason' => 'Second half leave test.',
                'created_at' => '2095-05-03 10:00:00',
                'status' => 'rejected'
            ]
        ];
    }

    /**
     * Test ID: LEAVE-AV01
     * Purpose: Verify that the admin leave management page renders.
     */
    public function testLeaveManagementPageRendersCorrectly(): void
    {
        $html = $this->renderView();

        $this->assertStringContainsString(
            '<h1>Leave Management</h1>',
            $html
        );

        $this->assertStringContainsString(
            'Review and manage employee leave applications.',
            $html
        );

        $this->assertStringContainsString(
            'Employee Leave Requests',
            $html
        );
    }

    /**
     * Test ID: LEAVE-AV02
     * Purpose: Verify employee-code search and reset controls.
     */
    public function testEmployeeCodeSearchFormIsDisplayed(): void
    {
        $html = $this->renderView(
            [],
            'EMP-TEST-001'
        );

        $this->assertStringContainsString(
            'name="employee_code"',
            $html
        );

        $this->assertStringContainsString(
            'name="action" value="leave-management"',
            $html
        );

        $this->assertStringContainsString(
            'EMP-TEST-001',
            $html
        );

        $this->assertStringContainsString(
            'Enter Employee Code (e.g. EMP-0003)',
            $html
        );

        $this->assertStringContainsString(
            'Search',
            $html
        );

        $this->assertStringContainsString(
            'leave-search-button',
            $html
        );

        $this->assertStringContainsString(
            'Reset',
            $html
        );

        $this->assertStringContainsString(
            'leave-reset-button',
            $html
        );
    }

    /**
     * Test ID: LEAVE-AV03
     * Purpose: Verify all ten table columns.
     */
    public function testAllLeaveRequestTableColumnsAreDisplayed(): void
    {
        $html = $this->renderView();

        $expectedColumns = [
            '#',
            'Employee Code',
            'Employee Name',
            'Leave Date',
            'Leave Type',
            'Period',
            'Reason',
            'Applied On',
            'Status',
            'Action'
        ];

        foreach ($expectedColumns as $column) {
            $this->assertStringContainsString(
                '<th>' . $column . '</th>',
                $html,
                "Missing table column: {$column}"
            );
        }
    }

    /**
     * Test ID: LEAVE-AV04
     * Purpose: Verify the empty-state message.
     */
    public function testEmptyLeaveRequestMessageIsDisplayed(): void
    {
        $html = $this->renderView([]);

        $this->assertStringContainsString(
            'No leave applications found.',
            $html
        );

        $this->assertStringContainsString(
            'colspan="10"',
            $html
        );
    }

    /**
     * Test ID: LEAVE-AV05
     * Purpose: Verify employee leave request details.
     */
    public function testEmployeeLeaveRequestDetailsAreDisplayed(): void
    {
        $html = $this->renderView(
            $this->sampleLeaveRequests()
        );

        $this->assertStringContainsString(
            'EMP-TEST-001',
            $html
        );

        $this->assertStringContainsString(
            'Test Employee One',
            $html
        );

        $this->assertStringContainsString(
            'Full day leave test.',
            $html
        );

        $this->assertStringContainsString(
            '15 May 2095',
            $html
        );

        $this->assertStringContainsString(
            '01 May 2095',
            $html
        );
    }

    /**
     * Test ID: LEAVE-AV06
     * Purpose: Verify Full Day and Half Day period displays.
     */
    public function testLeaveTypesAndHalfDayPeriodsAreDisplayed(): void
    {
        $html = $this->renderView(
            $this->sampleLeaveRequests()
        );

        $this->assertStringContainsString(
            'Full Day',
            $html
        );

        $this->assertStringContainsString(
            'Half Day',
            $html
        );

        $this->assertStringContainsString(
            'First Half',
            $html
        );

        $this->assertStringContainsString(
            'Second Half',
            $html
        );
    }

    /**
     * Test ID: LEAVE-AV07
     * Purpose: Verify Pending, Accepted and Rejected status labels.
     */
    public function testLeaveRequestStatusesAreDisplayedCorrectly(): void
    {
        $html = $this->renderView(
            $this->sampleLeaveRequests()
        );

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
     * Test ID: LEAVE-AV08
     * Purpose: Verify Accept and Reject forms appear for pending requests.
     */
    public function testPendingRequestsDisplayAcceptAndRejectForms(): void
    {
        $html = $this->renderView(
            [$this->sampleLeaveRequests()[0]]
        );

        $this->assertStringContainsString(
            'action=leave-review',
            $html
        );

        $this->assertStringContainsString(
            'name="request_id" value="900001"',
            $html
        );

        $this->assertStringContainsString(
            'name="status" value="approved"',
            $html
        );

        $this->assertStringContainsString(
            'name="status" value="rejected"',
            $html
        );

        $this->assertStringContainsString(
            'Accept',
            $html
        );

        $this->assertStringContainsString(
            'Reject',
            $html
        );

        $this->assertStringContainsString(
            'Accept this leave request?',
            $html
        );

        $this->assertStringContainsString(
            'Reject this leave request?',
            $html
        );
    }

    /**
     * Test ID: LEAVE-AV09
     * Purpose: Verify reviewed requests do not display review forms.
     */
    public function testReviewedRequestsDoNotDisplayReviewForms(): void
    {
        $reviewedRequests = [
            $this->sampleLeaveRequests()[1],
            $this->sampleLeaveRequests()[2]
        ];

        $html = $this->renderView($reviewedRequests);

        $this->assertStringContainsString(
            'Reviewed',
            $html
        );

        $this->assertStringNotContainsString(
            'action=leave-review',
            $html
        );

        $this->assertStringNotContainsString(
            '>Accept</button>',
            $html
        );

        $this->assertStringNotContainsString(
            '>Reject</button>',
            $html
        );
    }

    /**
     * Test ID: LEAVE-AV10
     * Purpose: Verify CSRF tokens are included in both review forms.
     */
    public function testCsrfTokensAreIncludedInReviewForms(): void
    {
        $html = $this->renderView(
            [$this->sampleLeaveRequests()[0]]
        );

        $this->assertSame(
            2,
            substr_count($html, 'name="csrf_token"')
        );

        $this->assertSame(
            2,
            substr_count(
                $html,
                htmlspecialchars($this->csrfToken)
            )
        );
    }

    /**
     * Test ID: LEAVE-AV11
     * Purpose: Verify employee information and reasons are HTML-escaped.
     */
    public function testEmployeeDataAndLeaveReasonAreHtmlEscaped(): void
    {
        $maliciousCode = '<script>alert("code")</script>';
        $maliciousName = '<img src=x onerror=alert(1)>';

        $maliciousReason = '<script>alert("reason")</script>';

        $request = [
            [
                'id' => 900004,
                'employee_code' => $maliciousCode,
                'full_name' => $maliciousName,
                'start_date' => '2095-05-18',
                'leave_type' => 'Full Day',
                'half_day_period' => null,
                'reason' => $maliciousReason,
                'created_at' => '2095-05-04 10:00:00',
                'status' => 'pending'
            ]
        ];

        $html = $this->renderView($request);

        $this->assertStringContainsString(
            htmlspecialchars($maliciousCode),
            $html
        );

        $this->assertStringContainsString(
            htmlspecialchars($maliciousName),
            $html
        );

        $this->assertStringContainsString(
            htmlspecialchars($maliciousReason),
            $html
        );

        $this->assertStringNotContainsString(
            $maliciousCode,
            $html
        );

        $this->assertStringNotContainsString(
            $maliciousName,
            $html
        );

        $this->assertStringNotContainsString(
            $maliciousReason,
            $html
        );
    }

    /**
     * Test ID: LEAVE-AV12
     * Purpose: Verify admin sidebar Leave Management navigation.
     */
    public function testAdminSidebarContainsLeaveManagementLink(): void
    {
        $sidebar = file_get_contents($this->sidebarPath);

        $this->assertIsString($sidebar);

        $this->assertStringContainsString(
            'action=leave-management',
            $sidebar
        );

        $this->assertStringContainsString(
            "'leave-review'",
            $sidebar
        );

        $this->assertStringContainsString(
            'Leave Management',
            $sidebar
        );

        $this->assertStringContainsString(
            'title="Leave Management"',
            $sidebar
        );
    }
}
