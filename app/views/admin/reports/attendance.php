<?php
require_once __DIR__ . '/../../../core/Auth.php';
Auth::startSession();

$pageTitle = 'Attendance Report';
$filters = $filters ?? [];
$departments = $departments ?? [];
$employees = $employees ?? [];
$records = $records ?? [];

function attendanceReportEscape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

/* Calculate report metrics from the filtered attendance records. */
$attendanceTotal = count($records);

$attendanceCounts = [
    'present' => 0,
    'late' => 0,
    'absent' => 0,
    'leave' => 0,
    'holiday' => 0
];

$attendanceByDate = [];

foreach ($records as $record) {
    $status = strtolower(
        (string) ($record['attendance_status'] ?? '')
    );

    if (isset($attendanceCounts[$status])) {
        $attendanceCounts[$status]++;
    }

    $date = (string) ($record['attendance_date'] ?? '');

    if ($date !== '') {
        if (!isset($attendanceByDate[$date])) {
            $attendanceByDate[$date] = 0;
        }

        $attendanceByDate[$date]++;
    }
}

ksort($attendanceByDate);

$attendanceTrend = array_slice(
    $attendanceByDate,
    -10,
    null,
    true
);

$attendanceMax = max(
    array_merge([1], array_values($attendanceTrend))
);

$attendanceTrendPoints = [];

$trendIndex = 0;
$trendCount = max(1, count($attendanceTrend));

foreach ($attendanceTrend as $date => $count) {
    $x = $trendCount === 1
        ? 50
        : 20 + ($trendIndex * (560 / ($trendCount - 1)));

    $y = 165 - (($count / $attendanceMax) * 125);

    $attendanceTrendPoints[] =
        round($x, 1) . ',' . round($y, 1);

    $trendIndex++;
}

$attendanceTrendLine = implode(
    ' ',
    $attendanceTrendPoints
);

$presentPercent = $attendanceTotal > 0
    ? round(
        ($attendanceCounts['present'] / $attendanceTotal) * 100,
        1
    )
    : 0;

$latePercent = $attendanceTotal > 0
    ? round(
        ($attendanceCounts['late'] / $attendanceTotal) * 100,
        1
    )
    : 0;

$absentPercent = $attendanceTotal > 0
    ? round(
        ($attendanceCounts['absent'] / $attendanceTotal) * 100,
        1
    )
    : 0;

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>

<div class="admin-main">

    <?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

    <main class="admin-content reports-page reports-attendance-page">

        <div class="reports-breadcrumb">
            <span>Home</span>
            <span class="reports-breadcrumb-separator">›</span>
            <span>Reports</span>
            <span class="reports-breadcrumb-separator">›</span>
            <strong>Attendance Report</strong>
        </div>

        <div class="reports-heading">
            <div>
                <div class="reports-heading-icon">▥</div>

                <div>
                    <h1>Attendance Report</h1>
                    <p>
                        Analyze attendance records, track trends,
                        and export data.
                    </p>
                </div>
            </div>

            <div class="reports-heading-actions">
                <span class="reports-period-label">
                    Filtered report
                </span>
            </div>
        </div>

        <nav class="reports-tabs">

            <a class="reports-tab active"
               href="/HRIS-Management-System/public/index.php?action=reports&amp;tab=attendance">
                Attendance Report
            </a>

            <a class="reports-tab"
               href="/HRIS-Management-System/public/index.php?action=reports&amp;tab=employee">
                Employee Report
            </a>

            <span class="reports-tab reports-tab-disabled"
                  title="Handled by another team member">
                Leave Report
            </span>

            <span class="reports-tab reports-tab-disabled"
                  title="Handled by another team member">
                Salary Report
            </span>

        </nav>

        <!-- KPI CARDS -->
        <section class="reports-kpi-grid">

            <article class="reports-kpi-card">
                <div class="reports-kpi-icon reports-icon-blue">
                    ♙
                </div>

                <div class="reports-kpi-content">
                    <span>Total Records</span>
                    <strong>
                        <?= number_format($attendanceTotal) ?>
                    </strong>
                    <small>Records matching filters</small>
                </div>
            </article>

            <article class="reports-kpi-card">
                <div class="reports-kpi-icon reports-icon-green">
                    ✓
                </div>

                <div class="reports-kpi-content">
                    <span>Present</span>
                    <strong>
                        <?= number_format($attendanceCounts['present']) ?>
                    </strong>
                    <small><?= $presentPercent ?>% of records</small>
                </div>
            </article>

            <article class="reports-kpi-card">
                <div class="reports-kpi-icon reports-icon-orange">
                    ◷
                </div>

                <div class="reports-kpi-content">
                    <span>Late</span>
                    <strong>
                        <?= number_format($attendanceCounts['late']) ?>
                    </strong>
                    <small><?= $latePercent ?>% of records</small>
                </div>
            </article>

            <article class="reports-kpi-card">
                <div class="reports-kpi-icon reports-icon-red">
                    ×
                </div>

                <div class="reports-kpi-content">
                    <span>Absent</span>
                    <strong>
                        <?= number_format($attendanceCounts['absent']) ?>
                    </strong>
                    <small><?= $absentPercent ?>% of records</small>
                </div>
            </article>

        </section>

        <!-- FILTERS -->
        <section class="reports-panel reports-filter-panel">

            <div class="reports-panel-heading">
                <div>
                    <h2>Filters</h2>
                    <p>Refine the report using the options below.</p>
                </div>

                <span class="reports-filter-indicator">
                    ⌘ Report filters
                </span>
            </div>

            <form method="GET"
                  action="/HRIS-Management-System/public/index.php"
                  class="reports-filter-form">

                <input type="hidden"
                       name="action"
                       value="reports">

                <input type="hidden"
                       name="tab"
                       value="attendance">

                <div class="reports-filter-grid attendance-filter-grid">

                    <!-- DEPARTMENT -->
                    <div class="reports-field">
                        <label for="department">
                            Department
                        </label>

                        <select name="department"
                                id="department">

                            <option value="">
                                All Departments
                            </option>

                            <?php foreach ($departments as $department): ?>
                                <option
                                    value="<?= attendanceReportEscape($department) ?>"
                                    <?= ($filters['department'] ?? '') === $department
                                        ? 'selected'
                                        : '' ?>>

                                    <?= attendanceReportEscape($department) ?>

                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                    <!-- EMPLOYEE -->
                    <div class="reports-field">
                        <label for="employee">
                            Employee
                        </label>

                        <select name="employee"
                                id="employee">

                            <option value="">
                                All Employees
                            </option>

                            <?php foreach ($employees as $employee): ?>

                                <option
                                    value="<?= (int) $employee['id'] ?>"
                                    data-department="<?= attendanceReportEscape($employee['department'] ?? '') ?>"
                                    <?= (string) ($filters['employee'] ?? '') ===
                                        (string) $employee['id']
                                        ? 'selected'
                                        : '' ?>>

                                    <?= attendanceReportEscape($employee['full_name']) ?>
                                    (<?= attendanceReportEscape($employee['employee_code']) ?>)

                                </option>

                            <?php endforeach; ?>

                        </select>
                    </div>

                    <!-- FROM DATE -->
                    <div class="reports-field">
                        <label for="from_date">
                            From Date
                        </label>

                        <input
                            type="date"
                            name="from_date"
                            id="from_date"
                            value="<?= attendanceReportEscape($filters['from_date'] ?? '') ?>">
                    </div>

                    <!-- TO DATE -->
                    <div class="reports-field">
                        <label for="to_date">
                            To Date
                        </label>

                        <input
                            type="date"
                            name="to_date"
                            id="to_date"
                            value="<?= attendanceReportEscape($filters['to_date'] ?? '') ?>">
                    </div>

                    <!-- ATTENDANCE STATUS -->
                    <div class="reports-field">
                        <label for="status">
                            Attendance Status
                        </label>

                        <select name="status"
                                id="status">

                            <option value="">
                                All Statuses
                            </option>

                            <?php foreach (
                                ['present', 'late', 'absent', 'leave', 'holiday']
                                as $status
                            ): ?>

                                <option
                                    value="<?= attendanceReportEscape($status) ?>"
                                    <?= ($filters['status'] ?? '') === $status
                                        ? 'selected'
                                        : '' ?>>

                                    <?= ucfirst($status) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>
                    </div>

                </div>

                <div class="reports-filter-actions">

                    <a class="reports-button reports-button-light"
                       href="/HRIS-Management-System/public/index.php?action=reports&amp;tab=attendance">
                        Reset
                    </a>

                    <button type="submit"
                            class="reports-button reports-button-primary">
                        Apply Filters
                    </button>

                </div>

            </form>

        </section>

        <!-- CHARTS -->
        <section class="reports-chart-grid">

            <!-- ATTENDANCE TREND -->
            <article class="reports-panel reports-chart-panel">

                <div class="reports-panel-heading">
                    <div>
                        <h2>Attendance Trend</h2>
                        <p>Records by attendance date</p>
                    </div>

                    <span class="reports-chart-tag">
                        Daily
                    </span>
                </div>

                <?php if (!empty($attendanceTrend)): ?>

                    <div class="reports-line-chart">

                        <div class="reports-chart-y-labels">
                            <span><?= $attendanceMax ?></span>
                            <span><?= round($attendanceMax * .66) ?></span>
                            <span><?= round($attendanceMax * .33) ?></span>
                            <span>0</span>
                        </div>

                        <div class="reports-line-chart-content">

                            <svg viewBox="0 0 600 190"
                                 role="img"
                                 aria-label="Attendance records trend chart">

                                <defs>
                                    <linearGradient
                                        id="attendanceAreaGradient"
                                        x1="0"
                                        x2="0"
                                        y1="0"
                                        y2="1">

                                        <stop
                                            offset="0%"
                                            stop-color="#16a37a"
                                            stop-opacity=".22"/>

                                        <stop
                                            offset="100%"
                                            stop-color="#16a37a"
                                            stop-opacity=".01"/>

                                    </linearGradient>
                                </defs>

                                <line
                                    x1="20"
                                    y1="40"
                                    x2="580"
                                    y2="40"
                                    class="reports-chart-gridline"/>

                                <line
                                    x1="20"
                                    y1="82"
                                    x2="580"
                                    y2="82"
                                    class="reports-chart-gridline"/>

                                <line
                                    x1="20"
                                    y1="124"
                                    x2="580"
                                    y2="124"
                                    class="reports-chart-gridline"/>

                                <line
                                    x1="20"
                                    y1="165"
                                    x2="580"
                                    y2="165"
                                    class="reports-chart-gridline"/>

                                <?php if (count($attendanceTrend) > 1): ?>

                                    <polygon
                                        points="20,165 <?= attendanceReportEscape($attendanceTrendLine) ?> 580,165"
                                        fill="url(#attendanceAreaGradient)"/>

                                <?php endif; ?>

                                <polyline
                                    points="<?= attendanceReportEscape($attendanceTrendLine) ?>"
                                    class="reports-chart-line"/>

                                <?php foreach ($attendanceTrendPoints as $point): ?>

                                    <?php
                                    [$cx, $cy] = array_map(
                                        'floatval',
                                        explode(',', $point)
                                    );
                                    ?>

                                    <circle
                                        cx="<?= $cx ?>"
                                        cy="<?= $cy ?>"
                                        r="4"
                                        class="reports-chart-point"/>

                                <?php endforeach; ?>

                            </svg>

                            <div class="reports-chart-x-labels">

                                <?php foreach ($attendanceTrend as $date => $count): ?>

                                    <span>
                                        <?= attendanceReportEscape(
                                            date('M j', strtotime($date))
                                        ) ?>
                                    </span>

                                <?php endforeach; ?>

                            </div>

                        </div>
                    </div>

                <?php else: ?>

                    <div class="reports-chart-empty">
                        No date-based attendance data available for this filter.
                    </div>

                <?php endif; ?>

            </article>

            <!-- STATUS BREAKDOWN -->
            <article class="reports-panel reports-chart-panel">

                <div class="reports-panel-heading">
                    <div>
                        <h2>Attendance Status Breakdown</h2>
                        <p>Distribution of attendance statuses</p>
                    </div>
                </div>

                <div class="reports-donut-layout">

                    <div
                        class="reports-donut"
                        style="background: conic-gradient(
                            #16a37a 0% <?= $presentPercent ?>%,
                            #f2a93b <?= $presentPercent ?>% <?= $presentPercent + $latePercent ?>%,
                            #ed6262 <?= $presentPercent + $latePercent ?>% 100%
                        );">

                        <div class="reports-donut-center">
                            <strong>
                                <?= number_format($attendanceTotal) ?>
                            </strong>
                            <span>Records</span>
                        </div>

                    </div>

                    <div class="reports-donut-legend">

                        <div>
                            <i class="legend-green"></i>
                            <span>Present</span>
                            <strong>
                                <?= number_format($attendanceCounts['present']) ?>
                            </strong>
                        </div>

                        <div>
                            <i class="legend-orange"></i>
                            <span>Late</span>
                            <strong>
                                <?= number_format($attendanceCounts['late']) ?>
                            </strong>
                        </div>

                        <div>
                            <i class="legend-red"></i>
                            <span>Absent</span>
                            <strong>
                                <?= number_format($attendanceCounts['absent']) ?>
                            </strong>
                        </div>

                        <div>
                            <i class="legend-gray"></i>
                            <span>Leave / Holiday</span>
                            <strong>
                                <?= number_format(
                                    $attendanceCounts['leave'] +
                                    $attendanceCounts['holiday']
                                ) ?>
                            </strong>
                        </div>

                    </div>

                </div>

            </article>

        </section>

        <!-- ATTENDANCE TABLE -->
        <section class="reports-panel reports-table-panel">

            <div class="reports-table-heading">
                <div>
                    <h2>Attendance Records</h2>
                    <p>
                        <?= number_format($attendanceTotal) ?>
                        record(s) found
                    </p>
                </div>

                <a
                    class="reports-button reports-button-outline"
                    href="/HRIS-Management-System/public/index.php?action=export-attendance-report&amp;<?= attendanceReportEscape(http_build_query($filters)) ?>">

                    ⇩ Export to Excel

                </a>
            </div>

            <?php if (empty($records)): ?>

                <div class="reports-empty-state">
                    <div class="reports-empty-icon">▤</div>

                    <h3>No attendance records found</h3>

                    <p>
                        Try changing your filters to see more results.
                    </p>
                </div>

            <?php else: ?>

                <div class="reports-table-scroll">

                    <table class="reports-data-table">

                        <thead>
                            <tr>
                                <th>Employee ID</th>
                                <th>Name</th>
                                <th>Department</th>
                                <th>Date</th>
                                <th>Entry Time</th>
                                <th>Entry Status</th>
                                <th>Exit Time</th>
                                <th>Exit Status</th>
                                <th>Attendance Status</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($records as $record): ?>

                                <?php
                                $rowStatus = strtolower(
                                    (string) ($record['attendance_status'] ?? '')
                                );
                                ?>

                                <tr>

                                    <td class="reports-id-cell">
                                        <?= attendanceReportEscape(
                                            $record['employee_id'] ?? ''
                                        ) ?>
                                    </td>

                                    <td class="reports-name-cell">
                                        <?= attendanceReportEscape(
                                            $record['full_name'] ?? ''
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= attendanceReportEscape(
                                            $record['department'] ?? ''
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= attendanceReportEscape(
                                            $record['attendance_date'] ?? ''
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= attendanceReportEscape(
                                            $record['entry_time'] ?? '-'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= attendanceReportEscape(
                                            $record['entry_status'] ?? '-'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= attendanceReportEscape(
                                            $record['exit_time'] ?? '-'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= attendanceReportEscape(
                                            $record['exit_status'] ?? '-'
                                        ) ?>
                                    </td>

                                    <td>
                                        <span class="reports-status-badge status-<?= attendanceReportEscape($rowStatus) ?>">
                                            <?= attendanceReportEscape(
                                                ucfirst($rowStatus ?: 'Unknown')
                                            ) ?>
                                        </span>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

                <div class="reports-table-footer">
                    <span>
                        Showing <?= number_format($attendanceTotal) ?>
                        record(s)
                    </span>
                </div>

            <?php endif; ?>

        </section>

    </main>

    <!-- INSTANT DEPARTMENT / EMPLOYEE FILTER -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const departmentSelect =
            document.getElementById('department');

        const employeeSelect =
            document.getElementById('employee');

        if (!departmentSelect || !employeeSelect) {
            return;
        }

        function updateEmployeeOptions() {
            const selectedDepartment = departmentSelect.value;
            const currentEmployee = employeeSelect.value;

            let currentEmployeeIsValid = false;

            Array.from(employeeSelect.options).forEach(function (option, index) {
                // Keep the "All Employees" option visible.
                if (index === 0) {
                    option.hidden = false;
                    option.disabled = false;
                    return;
                }

                const employeeDepartment =
                    option.dataset.department || '';

                const shouldShow =
                    selectedDepartment === '' ||
                    employeeDepartment === selectedDepartment;

                option.hidden = !shouldShow;
                option.disabled = !shouldShow;

                if (
                    shouldShow &&
                    option.value === currentEmployee
                ) {
                    currentEmployeeIsValid = true;
                }
            });

            // Clear the selected employee if it belongs
            // to a different department.
            if (!currentEmployeeIsValid) {
                employeeSelect.value = '';
            }
        }

        // Update immediately when department changes.
        departmentSelect.addEventListener(
            'change',
            updateEmployeeOptions
        );

        // Also filter correctly when the page first loads.
        updateEmployeeOptions();
    });
    </script>

    <?php require_once __DIR__ . '/../layouts/footer.php'; ?>

</div>