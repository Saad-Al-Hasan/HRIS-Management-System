<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/Auth.php';

Auth::startSession();

$pageTitle = (string) ($pageTitle ?? 'Reports');
$reportType = (string) ($reportType ?? 'attendance');
$filters = is_array($filters ?? null) ? $filters : [];
$rows = is_array($rows ?? null) ? $rows : [];
$summary = is_array($summary ?? null) ? $summary : [];
$employees = is_array($employees ?? null) ? $employees : [];
$departments = is_array($departments ?? null) ? $departments : [];
$designations = is_array($designations ?? null) ? $designations : [];
$attendanceCharts = is_array($attendanceCharts ?? null)
    ? $attendanceCharts
    : [];

$employeeCharts = is_array($employeeCharts ?? null)
    ? $employeeCharts
    : [];

$leaveCharts = is_array($leaveCharts ?? null)
    ? $leaveCharts
    : [];

$salaryCharts = is_array($salaryCharts ?? null)
    ? $salaryCharts
    : [];

$h = static fn($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$money = static fn($value): string => number_format((float) ($value ?? 0), 2);
$selected = static fn(string $key, string $value): string =>
    (string) ($filters[$key] ?? '') === $value ? 'selected' : '';

$reportTabs = [
    'attendance' => 'Attendance Report',
    'employees' => 'Employee Report',
    'leave' => 'Leave Report',
    'salary' => 'Salary Report',
];

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>

<div class="admin-main reports-page">
    <?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

    <main class="admin-content">
<section class="reports-heading">
    <div class="reports-heading-main">
        <span class="reports-eyebrow">
            MYOL HRIS <span></span> ANALYTICS & INSIGHTS
        </span>

        <h1><?= $h($pageTitle) ?></h1>

        <p>
            Explore workforce data, monitor HR performance,
            and review organizational records.
        </p>
    </div>

    <div class="reports-heading-side">
        <div class="reports-count">
            <span class="reports-count-label">MATCHING RECORDS</span>
            <strong><?= number_format(count($rows)) ?></strong>
            <span class="reports-count-caption">Records in this view</span>
        </div>
    </div>
</section>

<nav class="reports-tabs" aria-label="Report types">
    <?php foreach ($reportTabs as $key => $label): ?>
        <a href="/HRIS-Management-System/public/index.php?action=reports&amp;report=<?= $h($key) ?>"
           class="<?= $reportType === $key ? 'active' : '' ?>"
           <?= $reportType === $key ? 'aria-current="page"' : '' ?>>

            <span class="reports-tab-indicator"></span>
            <span><?= $h($label) ?></span>

            <span class="reports-tab-arrow">↗</span>
        </a>
    <?php endforeach; ?>
</nav>

        <section class="reports-filter-card">
            <div class="reports-card-heading reports-filter-heading">
                <div class="reports-heading-icon" aria-hidden="true">⌕</div>
                <div class="reports-filter-title">
                    <span class="reports-section-kicker">REPORT CONTROLS</span>
                    <h2>Refine Your Report</h2>
                    <p>Adjust the criteria below to find exactly the records you need.</p>
                </div>
                <div class="reports-filter-note">
                    <span class="reports-live-dot"></span>
                    FILTERS AVAILABLE
                </div>
            </div>

            <form method="GET" action="/HRIS-Management-System/public/index.php" class="reports-filter-form">
                <input type="hidden" name="action" value="reports">
                <input type="hidden" name="report" value="<?= $h($reportType) ?>">

                <?php if (in_array($reportType, ['attendance', 'leave'], true)): ?>
                    <div class="reports-field">
                        <label for="from_date">From Date</label>
                        <input id="from_date" type="date" name="from_date" value="<?= $h($filters['from_date'] ?? '') ?>">
                    </div>
                    <div class="reports-field">
                        <label for="to_date">To Date</label>
                        <input id="to_date" type="date" name="to_date" value="<?= $h($filters['to_date'] ?? '') ?>">
                    </div>
                    <div class="reports-field">
                        <label for="employee_id">Employee</label>
                        <select id="employee_id" name="employee_id">
                            <option value="">All Employees</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?= (int) ($employee['employee_id'] ?? 0) ?>"
                                    <?= (string) ($filters['employee_id'] ?? '') === (string) ($employee['employee_id'] ?? '') ? 'selected' : '' ?>>
                                    <?= $h(($employee['employee_code'] ?? '') . ' - ' . ($employee['full_name'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <?php if ($reportType === 'attendance'): ?>
                    <div class="reports-field">
                        <label for="department">Department</label>
                        <select id="department" name="department">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $department): ?>
                                <option value="<?= $h($department['department'] ?? '') ?>" <?= $selected('department', (string) ($department['department'] ?? '')) ?>>
                                    <?= $h($department['department'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="reports-field">
                        <label for="status">Attendance Status</label>
                        <select id="status" name="status">
                            <option value="">All Statuses</option>
                            <?php foreach (['present' => 'Present', 'late' => 'Late', 'absent' => 'Absent', 'leave' => 'Leave', 'holiday' => 'Holiday'] as $value => $label): ?>
                                <option value="<?= $h($value) ?>" <?= $selected('status', $value) ?>><?= $h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php elseif ($reportType === 'employees'): ?>
                    <div class="reports-field">
                        <label for="department">Department</label>
                        <select id="department" name="department">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $department): ?>
                                <option value="<?= $h($department['department'] ?? '') ?>" <?= $selected('department', (string) ($department['department'] ?? '')) ?>>
                                    <?= $h($department['department'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="reports-field">
                        <label for="designation">Designation</label>
                        <select id="designation" name="designation">
                            <option value="">All Designations</option>
                            <?php foreach ($designations as $designation): ?>
                                <option value="<?= $h($designation['designation'] ?? '') ?>" <?= $selected('designation', (string) ($designation['designation'] ?? '')) ?>>
                                    <?= $h($designation['designation'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="reports-field">
                        <label for="status">Account Status</label>
                        <select id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="active" <?= $selected('status', 'active') ?>>Active</option>
                            <option value="inactive" <?= $selected('status', 'inactive') ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="reports-field">
                        <label for="search">Search Employee</label>
                        <input id="search" type="search" name="search" placeholder="Name, code, email..." value="<?= $h($filters['search'] ?? '') ?>">
                    </div>
                <?php elseif ($reportType === 'leave'): ?>
                    <div class="reports-field">
                        <label for="status">Leave Status</label>
                        <select id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="pending" <?= $selected('status', 'pending') ?>>Pending</option>
                            <option value="approved" <?= $selected('status', 'approved') ?>>Accepted</option>
                            <option value="rejected" <?= $selected('status', 'rejected') ?>>Rejected</option>
                        </select>
                    </div>
                <?php elseif ($reportType === 'salary'): ?>
                    <div class="reports-field">
                        <label for="month">Salary Month</label>
                        <input id="month" type="month" name="month" value="<?= $h($filters['month'] ?? '') ?>">
                    </div>
                    <div class="reports-field">
    <label for="salary_department">Department</label>
    <select id="salary_department" name="department">
        <option value="">All Departments</option>

        <?php foreach ($departments as $department): ?>
            <option
                value="<?= $h($department['department'] ?? '') ?>"
                <?= $selected('department', (string) ($department['department'] ?? '')) ?>>
                <?= $h($department['department'] ?? '') ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
                    <div class="reports-field">
                        <label for="employee_id">Employee</label>
                        <select id="employee_id" name="employee_id">
                            <option value="">All Employees</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?= (int) ($employee['employee_id'] ?? 0) ?>"
                                    <?= (string) ($filters['employee_id'] ?? '') === (string) ($employee['employee_id'] ?? '') ? 'selected' : '' ?>>
                                    <?= $h(($employee['employee_code'] ?? '') . ' - ' . ($employee['full_name'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="reports-filter-actions">
                    <button type="submit" class="reports-apply-button">Apply Filters</button>
                    <a class="reports-reset-button" href="/HRIS-Management-System/public/index.php?action=reports&amp;report=<?= $h($reportType) ?>">Reset</a>
                </div>
            </form>
        </section>

        <?php if ($reportType === 'attendance'): ?>
            <section class="reports-summary-grid">
                <article class="metric-total"><span><svg class="reports-metric-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M4 9h16M9 4v16"/></svg>Total Records</span><strong><?= number_format((int) ($summary['total_records'] ?? 0)) ?></strong></article>
                <article class="metric-present"><span><svg class="reports-metric-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>Present</span><strong><?= number_format((int) ($summary['present_count'] ?? 0)) ?></strong></article>
                <article class="metric-late"><span><svg class="reports-metric-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>Late</span><strong><?= number_format((int) ($summary['late_count'] ?? 0)) ?></strong></article>
                <article class="metric-absent"><span><svg class="reports-metric-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/></svg>Absent</span><strong><?= number_format((int) ($summary['absent_count'] ?? 0)) ?></strong></article>
                <article class="metric-leave"><span><svg class="reports-metric-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4m8-4v4M4 10h16"/></svg>Leave</span><strong><?= number_format((int) ($summary['leave_count'] ?? 0)) ?></strong></article>
                <article class="metric-holiday"><span><svg class="reports-metric-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4m8-4v4M4 10h16m4 4-2 2-1-1"/></svg>Holiday</span><strong><?= number_format((int) ($summary['holiday_count'] ?? 0)) ?></strong></article>
            </section>
        <?php elseif ($reportType === 'employees'): ?>
            <section class="reports-summary-grid reports-summary-three">
                <article class="metric-total"><span><svg class="reports-metric-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M20 8v6m-3-3h6"/></svg>Total Employees</span><strong><?= number_format((int) ($summary['total_employees'] ?? 0)) ?></strong></article>
                <article class="metric-present"><span><svg class="reports-metric-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="7" r="4"/><path d="M2 21v-2a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v2"/><path d="m16 11 2 2 4-4"/></svg>Active</span><strong><?= number_format((int) ($summary['active_employees'] ?? 0)) ?></strong></article>
                <article class="metric-absent"><span><svg class="reports-metric-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="7" r="4"/><path d="M2 21v-2a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v2"/><path d="m17 9 5 5m0-5-5 5"/></svg>Inactive</span><strong><?= number_format((int) ($summary['inactive_employees'] ?? 0)) ?></strong></article>
            </section>
        <?php elseif ($reportType === 'leave'): ?>
            <section class="reports-summary-grid reports-summary-four">
                <article class="metric-total"><span><svg class="reports-metric-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 7h8M8 11h8M8 15h5"/></svg>Total Requests</span><strong><?= number_format((int) ($summary['total_requests'] ?? 0)) ?></strong></article>
                <article class="metric-late"><span><svg class="reports-metric-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>Pending</span><strong><?= number_format((int) ($summary['pending_count'] ?? 0)) ?></strong></article>
                <article class="metric-present"><span><svg class="reports-metric-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg>Accepted</span><strong><?= number_format((int) ($summary['accepted_count'] ?? 0)) ?></strong></article>
                <article class="metric-absent"><span><svg class="reports-metric-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/></svg>Rejected</span><strong><?= number_format((int) ($summary['rejected_count'] ?? 0)) ?></strong></article>
            </section>
        <?php elseif ($reportType === 'salary'): ?>
            <section class="reports-summary-grid reports-summary-four">
                <article class="metric-total"><span><svg class="reports-metric-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h8"/></svg>Saved Records</span><strong><?= number_format((int) ($summary['total_records'] ?? 0)) ?></strong></article>
                <article class="metric-present"><span><svg class="reports-metric-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M6 9h.01M18 15h.01"/></svg>Total Basic Salary</span><strong>৳<?= $money($summary['total_basic_salary'] ?? 0) ?></strong></article>
                <article class="metric-absent"><span><svg class="reports-metric-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 12h8"/></svg>Total Deductions</span><strong>৳<?= $money($summary['total_deductions'] ?? 0) ?></strong></article>
                <article class="metric-holiday"><span><svg class="reports-metric-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M7 15h3m4 0h3"/></svg>Total Final Salary</span><strong>৳<?= $money($summary['total_final_salary'] ?? 0) ?></strong></article>
            </section>
        <?php endif; ?>

        <?php if ($reportType === 'attendance' && !empty($attendanceCharts)): ?>
    <section class="attendance-charts-section">
        <div class="reports-card-heading">
            <div>
                <span class="reports-section-kicker">ATTENDANCE INSIGHTS</span>
                <h2>Attendance Analytics</h2>
                <p>Visual overview of attendance patterns based on your selected filters.</p>
            </div>
        </div>

        <div class="attendance-charts-grid">
            <article class="attendance-chart-card">
                <h3>Attendance Distribution</h3>
                <div class="attendance-chart-canvas">
    <canvas id="attendanceDistributionChart"></canvas>
</div>
            </article>

            <article class="attendance-chart-card">
                <h3>Attendance Status Comparison</h3>
                <div class="attendance-chart-canvas">
    <canvas id="attendanceComparisonChart"></canvas>
</div>
            </article>

        </div>
    </section>
<?php endif; ?>


<?php if ($reportType === 'employees' && !empty($employeeCharts)): ?>
    <section class="attendance-charts-section">
        <div class="reports-card-heading">
            <div>
                <span class="reports-section-kicker">EMPLOYEE INSIGHTS</span>
                <h2>Employee Analytics</h2>
                <p>Overview of employee status, departments, and work schedule assignments.</p>
            </div>
        </div>

        <div class="attendance-charts-grid employee-charts-grid">
            <article class="attendance-chart-card">
                <h3>Employee Status Distribution</h3>
                <div class="attendance-chart-canvas">
                    <canvas id="employeeStatusChart"></canvas>
                </div>
            </article>

<article class="attendance-chart-card employee-department-chart-card">
    <h3>Employees by Department</h3>
    <div class="attendance-chart-canvas">
        <canvas id="employeeDepartmentChart"></canvas>
    </div>
</article>

            <article class="attendance-chart-card">
                <h3>Work Schedule Assignment</h3>
                <div class="attendance-chart-canvas">
                    <canvas id="employeeScheduleChart"></canvas>
                </div>
            </article>
        </div>
    </section>
<?php endif; ?>


<?php if ($reportType === 'leave' && !empty($leaveCharts)): ?>
    <section class="attendance-charts-section">
        <div class="reports-card-heading">
            <div>
                <span class="reports-section-kicker">LEAVE INSIGHTS</span>
                <h2>Leave Analytics</h2>
                <p>Overview of leave request statuses, leave types, and monthly request trends.</p>
            </div>
        </div>

        <div class="attendance-charts-grid">
            <article class="attendance-chart-card">
                <h3>Leave Request Status</h3>
                <div class="attendance-chart-canvas">
                    <canvas id="leaveStatusChart"></canvas>
                </div>
            </article>

            <article class="attendance-chart-card">
                <h3>Leave Type Distribution</h3>
                <div class="attendance-chart-canvas">
                    <canvas id="leaveTypeChart"></canvas>
                </div>
            </article>

        </div>
    </section>
<?php endif; ?>


<?php if ($reportType === 'salary' && !empty($salaryCharts)): ?>
    <section class="attendance-charts-section">
        <div class="reports-card-heading">
            <div>
                <span class="reports-section-kicker">PAYROLL INSIGHTS</span>
                <h2>Salary Analytics</h2>
                <p>Visual overview of saved salary records, deductions, and department-wise payroll.</p>
            </div>
        </div>

        <div class="attendance-charts-grid salary-charts-grid">
            <article class="attendance-chart-card">
                <h3>Salary Overview</h3>
                <div class="attendance-chart-canvas">
                    <canvas id="salaryOverviewChart"></canvas>
                </div>
            </article>

<article class="attendance-chart-card salary-monthly-chart-card">
    <h3>Monthly Salary Comparison</h3>
    <div class="attendance-chart-canvas">
        <canvas id="salaryMonthlyChart"></canvas>
    </div>
</article>

            <article class="attendance-chart-card">
                <h3>Final Salary by Department</h3>
                <div class="attendance-chart-canvas">
                    <canvas id="salaryDepartmentChart"></canvas>
                </div>
            </article>
        </div>
    </section>
<?php endif; ?>


        <section class="reports-table-card">
            <div class="reports-card-heading reports-table-heading">
                <div>
                    <span class="reports-section-kicker">DATA OVERVIEW</span>
                    <h2><?= $h($pageTitle) ?> Records</h2>
                    <p>Detailed information based on your selected report criteria. Salary values are read from saved payroll records.</p>
                </div>

<?php
    $exportParams = array_merge(
        [
            'action' => 'reports-export',
            'report' => $reportType,
        ],
        $filters
    );

    $exportUrl = '/HRIS-Management-System/public/index.php?'
        . http_build_query($exportParams);
?>

<a
    href="<?= $h($exportUrl) ?>"
    class="reports-export-preview"
    aria-label="Export <?= $h($pageTitle) ?> to Excel"
    style="text-decoration: none; cursor: pointer;"
>
    <span class="reports-export-icon" aria-hidden="true">↓</span>
    <span class="reports-export-text">
        <strong>Excel Export</strong>
        <small>Download .xlsx file</small>
    </span>
</a>

            </div>

            <?php if (!$rows): ?>
                <div class="reports-empty-state">
                    <div class="reports-empty-icon" aria-hidden="true">⌕</div>
                    <h3>No Matching Records</h3>
                    <p>We couldn't find any records matching your selected criteria. Try adjusting your filters.</p>
                </div>
            <?php else: ?>
                <div class="reports-table-wrap">
                    <table class="reports-table">
                        <thead><tr>
                            <?php if ($reportType === 'attendance'): ?>
                                <th>Date</th><th>Employee</th><th>Department</th><th>Entry</th><th>Entry Status</th><th>Exit</th><th>Exit Status</th><th>Overall Status</th><th>Source</th>
                            <?php elseif ($reportType === 'employees'): ?>
                                <th>Employee Code</th><th>Employee</th><th>Email</th><th>Phone</th><th>Department</th><th>Designation</th><th>Joining Date</th><th>Monthly Salary</th><th>Work Schedule</th><th>Status</th>
                            <?php elseif ($reportType === 'leave'): ?>
                                <th>Request ID</th><th>Employee</th><th>Department</th><th>Leave Type</th><th>Period</th><th>Start Date</th><th>End Date</th><th>Reason</th><th>Status</th><th>Reviewed By</th><th>Submitted</th>
                            <?php else: ?>
                                <th>Salary Month</th><th>Employee</th><th>Department</th><th>Basic Salary</th><th>Deductions</th><th>Final Salary</th><th>Calculated At</th>
                            <?php endif; ?>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                            <?php if ($reportType === 'attendance'): ?>
                                <td><?= $h($row['attendance_date'] ?? '') ?></td>
                                <td><strong><?= $h($row['full_name'] ?? '') ?></strong><small><?= $h($row['employee_code'] ?? '') ?></small></td>
                                <td><?= $h($row['department'] ?? '—') ?></td>
                                <td><?= $h($row['entry_time'] ?? '—') ?></td>
                                <td><?= $h($row['entry_status'] ?? '—') ?></td>
                                <td><?= $h($row['exit_time'] ?? '—') ?></td>
                                <td><?= $h($row['exit_status'] ?? '—') ?></td>
                                <?php
                                    $attendanceStatus = strtolower((string) ($row['attendance_status'] ?? ''));
                                    $overallStatusLabel = ucfirst($attendanceStatus ?: '—');

                                    if ($attendanceStatus === 'leave') {
                                        $leaveType = trim((string) ($row['leave_type'] ?? ''));
                                        $halfDayPeriod = trim((string) ($row['half_day_period'] ?? ''));

                                        if ($leaveType !== '') {
                                            $overallStatusLabel = $leaveType;
                                        } else {
                                            $overallStatusLabel = 'Leave';
                                        }

                                        if (strtolower($leaveType) === 'half day') {
                                            $overallStatusLabel = 'Half Day';
                                        } elseif (strtolower($leaveType) === 'full day') {
                                            $overallStatusLabel = 'Full Day';
                                        }

                                        if ($halfDayPeriod !== '') {
                                            $periodLabel = ucwords(str_replace('_', ' ', $halfDayPeriod));
                                            $overallStatusLabel .= ' · ' . $periodLabel;
                                        }
                                    }
                                ?>
                                <td><span class="reports-status"><?= $h($overallStatusLabel) ?></span></td>
                                <td><?= $h($row['record_source'] ?? '') ?></td>
                            <?php elseif ($reportType === 'employees'): ?>
                                <td><?= $h($row['employee_code'] ?? '') ?></td>
                                <td><strong><?= $h($row['full_name'] ?? '') ?></strong><small><?= $h($row['username'] ?? '') ?></small></td>
                                <td><?= $h($row['email'] ?? '') ?></td>
                                <td><?= $h($row['phone'] ?? '—') ?></td>
                                <td><?= $h($row['department'] ?? '—') ?></td>
                                <td><?= $h($row['designation'] ?? '—') ?></td>
                                <td><?= $h($row['joining_date'] ?? '—') ?></td>
                                <td>৳<?= $money($row['monthly_salary'] ?? 0) ?></td>
                                <td><?= $h($row['schedule_name'] ?? 'Not Assigned') ?></td>
                                <td><span class="reports-status"><?= $h(ucfirst((string) ($row['employee_status'] ?? ''))) ?></span></td>
                            <?php elseif ($reportType === 'leave'): ?>
                                <td>#<?= (int) ($row['leave_request_id'] ?? 0) ?></td>
                                <td><strong><?= $h($row['full_name'] ?? '') ?></strong><small><?= $h($row['employee_code'] ?? '') ?></small></td>
                                <td><?= $h($row['department'] ?? '—') ?></td>
                                <td><?= $h($row['leave_type'] ?? '—') ?></td>
                                <td><?= $h($row['half_day_period'] ?? 'Full Day') ?></td>
                                <td><?= $h($row['start_date'] ?? '') ?></td>
                                <td><?= $h($row['end_date'] ?? '') ?></td>
                                <td class="reports-reason"><?= $h($row['reason'] ?? '—') ?></td>
                                <td><span class="reports-status status-<?= $h($row['status'] ?? '') ?>"><?= $h(($row['status'] ?? '') === 'approved' ? 'Accepted' : ucfirst((string) ($row['status'] ?? ''))) ?></span></td>
                                <td><?= $h($row['reviewer_name'] ?? '—') ?></td>
                                <td><?= $h($row['created_at'] ?? '') ?></td>
                            <?php else: ?>
                                <td><?= $h($row['salary_month'] ?? '') ?></td>
                                <td><strong><?= $h($row['full_name'] ?? '') ?></strong><small><?= $h($row['employee_code'] ?? '') ?></small></td>
                                <td><?= $h($row['department'] ?? '—') ?></td>
                                <td>৳<?= $money($row['basic_salary'] ?? 0) ?></td>
                                <td>৳<?= $money($row['deductions'] ?? 0) ?></td>
                                <td><strong>৳<?= $money($row['final_salary'] ?? 0) ?></strong></td>
                                <td><?= $h($row['calculated_at'] ?? '') ?></td>
                            <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>

    
<?php if (
    ($reportType === 'attendance' && !empty($attendanceCharts)) ||
    ($reportType === 'employees' && !empty($employeeCharts)) ||
    ($reportType === 'leave' && !empty($leaveCharts)) ||
    ($reportType === 'salary' && !empty($salaryCharts))
): ?>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const chartData = <?= json_encode(
            $attendanceCharts,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
        ) ?>;

        const employeeData = <?= json_encode(
    $employeeCharts,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
) ?>;

const leaveData = <?= json_encode(
    $leaveCharts,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
) ?>;

const salaryData = <?= json_encode(
    $salaryCharts,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
) ?>;

        if (typeof Chart === 'undefined') {
            console.error('Chart.js could not be loaded.');
            return;
        }

        const statusCounts = chartData.status_counts || {};
        const dailyTrend = chartData.daily_trend || [];

        const statusLabels = ['Present', 'Late', 'Absent', 'Leave', 'Holiday'];
        const statusKeys = ['present', 'late', 'absent', 'leave', 'holiday'];

        const statusValues = statusKeys.map(
            key => Number(statusCounts[key] || 0)
        );

        const chartColors = [
            '#22c55e',
            '#f59e0b',
            '#ef4444',
            '#3b82f6',
            '#a78bfa'
        ];

const commonOptions = {
    responsive: true,
    maintainAspectRatio: false,
    devicePixelRatio: Math.min(window.devicePixelRatio || 1, 2),
            plugins: {
                legend: {
                    labels: {
                        color: '#cbd5e1',
                        padding: 18
                    }
                }
            }
        };

        // 1. Attendance Distribution
        const distributionCanvas = document.getElementById(
            'attendanceDistributionChart'
        );

        if (distributionCanvas) {
            new Chart(distributionCanvas, {
                type: 'doughnut',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        data: statusValues,
                        backgroundColor: chartColors,
                        borderWidth: 0
                    }]
                },
                options: {
                    ...commonOptions,
                    cutout: '65%'
                }
            });
        }

        // 2. Attendance Status Comparison
        const comparisonCanvas = document.getElementById(
            'attendanceComparisonChart'
        );

        if (comparisonCanvas) {
            new Chart(comparisonCanvas, {
                type: 'bar',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        label: 'Records',
                        data: statusValues,
                        backgroundColor: chartColors,
                        borderRadius: 6
                    }]
                },
options: {
    ...commonOptions,
    plugins: {
        legend: {
            display: false
        }
    },
    scales: {
                        x: {
                            ticks: { color: '#cbd5e1' },
                            grid: { display: false }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: '#cbd5e1',
                                precision: 0
                            },
                            grid: {
                                color: 'rgba(148,163,184,0.12)'
                            }
                        }
                    }
                }
            });
        }

        // Shared chart options for the new analytics.
        const analyticsOptions = {
            responsive: true,
            maintainAspectRatio: false,
            devicePixelRatio: Math.min(window.devicePixelRatio || 1, 2),
            plugins: {
                legend: {
                    labels: {
                        color: '#cbd5e1',
                        padding: 18
                    }
                }
            }
        };

        const createDoughnutChart = (id, labels, values, colors) => {
            const canvas = document.getElementById(id);

            if (!canvas) return;

            new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{
                        data: values,
                        backgroundColor: colors,
                        borderWidth: 0
                    }]
                },
                options: {
                    ...analyticsOptions,
                    cutout: '65%'
                }
            });
        };

        const createBarChart = (id, labels, datasets) => {
            const canvas = document.getElementById(id);

            if (!canvas) return;

            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels,
                    datasets: datasets.map(dataset => ({
                        ...dataset,
                        borderRadius: 6
                    }))
                },
                options: {
                    ...analyticsOptions,
                    scales: {
                        x: {
                            ticks: { color: '#cbd5e1' },
                            grid: { display: false }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: '#cbd5e1',
                                precision: 0
                            },
                            grid: {
                                color: 'rgba(148,163,184,0.12)'
                            }
                        }
                    }
                }
            });
        };

        // EMPLOYEE ANALYTICS
        if (document.getElementById('employeeStatusChart')) {
            const status = employeeData.status_counts || {};

            createDoughnutChart(
                'employeeStatusChart',
                ['Active', 'Inactive'],
                [Number(status.active || 0), Number(status.inactive || 0)],
                ['#22c55e', '#ef4444']
            );

            const departments = employeeData.department_counts || {};

            createBarChart(
                'employeeDepartmentChart',
                Object.keys(departments),
                [{
                    label: 'Employees',
                    data: Object.values(departments),
                    backgroundColor: '#3b82f6'
                }]
            );

            const schedules = employeeData.schedule_counts || {};

            createDoughnutChart(
                'employeeScheduleChart',
                ['Assigned', 'Unassigned'],
                [
                    Number(schedules.assigned || 0),
                    Number(schedules.unassigned || 0)
                ],
                ['#22c55e', '#f59e0b']
            );
        }

        // LEAVE ANALYTICS
        if (document.getElementById('leaveStatusChart')) {
            const status = leaveData.status_counts || {};

            createDoughnutChart(
                'leaveStatusChart',
                ['Pending', 'Accepted', 'Rejected'],
                [
                    Number(status.pending || 0),
                    Number(status.approved || 0),
                    Number(status.rejected || 0)
                ],
                ['#f59e0b', '#22c55e', '#ef4444']
            );

            const types = leaveData.type_counts || {};

            createDoughnutChart(
                'leaveTypeChart',
                Object.keys(types),
                Object.values(types),
                ['#3b82f6', '#a78bfa', '#f59e0b', '#22c55e', '#ef4444']
            );

        }

        // SALARY ANALYTICS
        if (document.getElementById('salaryOverviewChart')) {
            const totals = salaryData.totals || {};

            createBarChart(
                'salaryOverviewChart',
                ['Basic Salary', 'Deductions', 'Final Salary'],
                [{
                    label: 'Amount (BDT)',
                    data: [
                        Number(totals.basic_salary || 0),
                        Number(totals.deductions || 0),
                        Number(totals.final_salary || 0)
                    ],
                    backgroundColor: ['#3b82f6', '#ef4444', '#22c55e']
                }]
            );

            const monthly = salaryData.monthly_totals || {};
            const months = Object.keys(monthly);

            createBarChart(
                'salaryMonthlyChart',
                months,
                [
                    {
                        label: 'Basic Salary',
                        data: months.map(month =>
                            Number(monthly[month].basic_salary || 0)
                        ),
                        backgroundColor: '#3b82f6'
                    },
                    {
                        label: 'Deductions',
                        data: months.map(month =>
                            Number(monthly[month].deductions || 0)
                        ),
                        backgroundColor: '#ef4444'
                    },
                    {
                        label: 'Final Salary',
                        data: months.map(month =>
                            Number(monthly[month].final_salary || 0)
                        ),
                        backgroundColor: '#22c55e'
                    }
                ]
            );

            const departments = salaryData.department_totals || {};

            createBarChart(
                'salaryDepartmentChart',
                Object.keys(departments),
                [{
                    label: 'Final Salary (BDT)',
                    data: Object.values(departments),
                    backgroundColor: '#a78bfa'
                }]
            );
        }


    });
    </script>

<?php endif; ?>


    <?php require_once __DIR__ . '/../layouts/footer.php'; ?>
</div>

