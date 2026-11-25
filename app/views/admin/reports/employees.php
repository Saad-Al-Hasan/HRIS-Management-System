
<?php
require_once __DIR__ . '/../../../core/Auth.php';
Auth::startSession();

$pageTitle = 'Employee Report';
$filters = $filters ?? [];
$departments = $departments ?? [];
$designations = $designations ?? [];
$records = $records ?? [];

function employeeReportEscape($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/* Calculate employee report metrics from filtered records. */
$employeeTotal = count($records);
$employeeActive = 0;
$employeeInactive = 0;
$employeesByDepartment = [];

foreach ($records as $record) {
    $employeeStatus = strtolower((string) ($record['status'] ?? ''));

    if ($employeeStatus === 'active') {
        $employeeActive++;
    } elseif ($employeeStatus === 'inactive') {
        $employeeInactive++;
    }

    $departmentName = trim((string) ($record['department'] ?? 'Unassigned'));

    if ($departmentName === '') {
        $departmentName = 'Unassigned';
    }

    if (!isset($employeesByDepartment[$departmentName])) {
        $employeesByDepartment[$departmentName] = 0;
    }

    $employeesByDepartment[$departmentName]++;
}

arsort($employeesByDepartment);

/* Count all departments before limiting the chart to 8 rows. */
$employeeDepartmentTotal = count($employeesByDepartment);
$employeesByDepartment = array_slice($employeesByDepartment, 0, 8, true);

$departmentMax = max(array_merge([1], array_values($employeesByDepartment)));

$activePercent = $employeeTotal > 0
    ? round(($employeeActive / $employeeTotal) * 100, 1)
    : 0;

$inactivePercent = $employeeTotal > 0
    ? round(($employeeInactive / $employeeTotal) * 100, 1)
    : 0;

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>

<div class="admin-main">
    <?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

    <main class="admin-content reports-page reports-employee-page">

        <div class="reports-breadcrumb">
            <span>Home</span>
            <span class="reports-breadcrumb-separator">›</span>
            <span>Reports</span>
            <span class="reports-breadcrumb-separator">›</span>
            <strong>Employee Report</strong>
        </div>

        <div class="reports-heading">
            <div>
                <div class="reports-heading-icon">♙</div>
                <div>
                    <h1>Employee Report</h1>
                    <p>View employee data, analyze distribution, and export reports.</p>
                </div>
            </div>
        </div>

        <nav class="reports-tabs">

            <a class="reports-tab"
               href="/HRIS-Management-System/public/index.php?action=reports&amp;tab=attendance">
                Attendance Report
            </a>

            <a class="reports-tab active"
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
                <div class="reports-kpi-icon reports-icon-blue">♙</div>
                <div class="reports-kpi-content">
                    <span>Total Employees</span>
                    <strong><?= number_format($employeeTotal) ?></strong>
                    <small>Employees matching filters</small>
                </div>
            </article>

            <article class="reports-kpi-card">
                <div class="reports-kpi-icon reports-icon-green">✓</div>
                <div class="reports-kpi-content">
                    <span>Active</span>
                    <strong><?= number_format($employeeActive) ?></strong>
                    <small><?= $activePercent ?>% of employees</small>
                </div>
            </article>

            <article class="reports-kpi-card">
                <div class="reports-kpi-icon reports-icon-red">×</div>
                <div class="reports-kpi-content">
                    <span>Inactive</span>
                    <strong><?= number_format($employeeInactive) ?></strong>
                    <small><?= $inactivePercent ?>% of employees</small>
                </div>
            </article>

            <article class="reports-kpi-card">
                <div class="reports-kpi-icon reports-icon-purple">▦</div>
                <div class="reports-kpi-content">
                    <span>Departments</span>
                    <strong><?= number_format($employeeDepartmentTotal) ?></strong>
                    <small>In filtered results</small>
                </div>
            </article>

        </section>

        <!-- FILTERS -->
        <section class="reports-panel reports-filter-panel">

            <div class="reports-panel-heading">
                <div>
                    <h2>Filters</h2>
                    <p>Find employees by department, designation, status or identity.</p>
                </div>

                <span class="reports-filter-indicator">⌘ Report filters</span>
            </div>

            <form method="GET"
                  action="/HRIS-Management-System/public/index.php"
                  class="reports-filter-form">

                <input type="hidden" name="action" value="reports">
                <input type="hidden" name="tab" value="employee">

                <div class="reports-filter-grid employee-filter-grid">

                    <div class="reports-field">
                        <label for="department">Department</label>

                        <select name="department" id="department">
                            <option value="">All Departments</option>

                            <?php foreach ($departments as $department): ?>
                                <option
                                    value="<?= employeeReportEscape($department) ?>"
                                    <?= ($filters['department'] ?? '') === $department ? 'selected' : '' ?>>
                                    <?= employeeReportEscape($department) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="reports-field">
                        <label for="designation">Designation</label>

                        <select name="designation" id="designation">
                            <option value="">All Designations</option>

                            <?php foreach ($designations as $designation): ?>
                                <option
                                    value="<?= employeeReportEscape($designation) ?>"
                                    <?= ($filters['designation'] ?? '') === $designation ? 'selected' : '' ?>>
                                    <?= employeeReportEscape($designation) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="reports-field">
                        <label for="status">Employee Status</label>

                        <select name="status" id="status">
                            <option value="">All Statuses</option>

                            <option value="active"
                                <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>
                                Active
                            </option>

                            <option value="inactive"
                                <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>
                                Inactive
                            </option>
                        </select>
                    </div>

                    <div class="reports-field">
                        <label for="name">Employee Name</label>

                        <input
                            type="text"
                            name="name"
                            id="name"
                            placeholder="Search by name"
                            value="<?= employeeReportEscape($filters['name'] ?? '') ?>">
                    </div>

                    <div class="reports-field">
                        <label for="employee_code">Employee Code</label>

                        <input
                            type="text"
                            name="employee_code"
                            id="employee_code"
                            placeholder="Search by code"
                            value="<?= employeeReportEscape($filters['employee_code'] ?? '') ?>">
                    </div>

                </div>

                <div class="reports-filter-actions">

                    <a class="reports-button reports-button-light"
                       href="/HRIS-Management-System/public/index.php?action=reports&amp;tab=employee">
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

            <!-- EMPLOYEES BY DEPARTMENT -->
            <article class="reports-panel reports-chart-panel">

                <div class="reports-panel-heading">
                    <div>
                        <h2>Employees by Department</h2>
                        <p>Employee distribution across departments</p>
                    </div>
                </div>

                <?php if (!empty($employeesByDepartment)): ?>

                    <div class="reports-department-chart">

                        <?php foreach ($employeesByDepartment as $departmentName => $count): ?>

                            <?php
                            $barWidth = round(($count / $departmentMax) * 100);
                            ?>

                            <div class="reports-department-row">

                                <span class="reports-department-name">
                                    <?= employeeReportEscape($departmentName) ?>
                                </span>

                                <div class="reports-department-track">
                                    <div class="reports-department-bar"
                                         style="width: <?= $barWidth ?>%">
                                    </div>
                                </div>

                                <strong><?= number_format($count) ?></strong>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="reports-chart-empty">
                        No department data available for this filter.
                    </div>

                <?php endif; ?>

            </article>

            <!-- EMPLOYEE STATUS -->
            <article class="reports-panel reports-chart-panel">

                <div class="reports-panel-heading">
                    <div>
                        <h2>Employee Status Breakdown</h2>
                        <p>Active and inactive employees</p>
                    </div>
                </div>

                <div class="reports-donut-layout">

                    <div class="reports-donut"
                         style="background: conic-gradient(
                            #16a37a 0% <?= $activePercent ?>%,
                            #ed6262 <?= $activePercent ?>% 100%
                         );">

                        <div class="reports-donut-center">
                            <strong><?= number_format($employeeTotal) ?></strong>
                            <span>Employees</span>
                        </div>

                    </div>

                    <div class="reports-donut-legend">

                        <div>
                            <i class="legend-green"></i>
                            <span>Active</span>
                            <strong><?= number_format($employeeActive) ?></strong>
                        </div>

                        <div>
                            <i class="legend-red"></i>
                            <span>Inactive</span>
                            <strong><?= number_format($employeeInactive) ?></strong>
                        </div>

                    </div>

                </div>
            </article>

        </section>

        <!-- EMPLOYEE TABLE -->
        <section class="reports-panel reports-table-panel">

            <div class="reports-table-heading">

                <div>
                    <h2>Employee Records</h2>
                    <p><?= number_format($employeeTotal) ?> employee(s) found</p>
                </div>

                <a class="reports-button reports-button-outline"
                   href="/HRIS-Management-System/public/index.php?action=export-employee-report&amp;<?= employeeReportEscape(http_build_query($filters)) ?>">
                    ⇩ Export to Excel
                </a>

            </div>

            <?php if (empty($records)): ?>

                <div class="reports-empty-state">
                    <div class="reports-empty-icon">♙</div>
                    <h3>No employees found</h3>
                    <p>Try changing your filters to see more results.</p>
                </div>

            <?php else: ?>

                <div class="reports-table-scroll">

                    <table class="reports-data-table">

                        <thead>
                            <tr>
                                <th>Employee Code</th>
                                <th>Full Name</th>
                                <th>Username</th>
                                <th>Department</th>
                                <th>Designation</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Joining Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($records as $record): ?>

                                <?php
                                $rowStatus = strtolower(
                                    (string) ($record['status'] ?? '')
                                );
                                ?>

                                <tr>

                                    <td class="reports-id-cell">
                                        <?= employeeReportEscape($record['employee_code'] ?? '') ?>
                                    </td>

                                    <td class="reports-name-cell">
                                        <?= employeeReportEscape($record['full_name'] ?? '') ?>
                                    </td>

                                    <td>
                                        <?= employeeReportEscape($record['username'] ?? '') ?>
                                    </td>

                                    <td>
                                        <?= employeeReportEscape($record['department'] ?? '') ?>
                                    </td>

                                    <td>
                                        <?= employeeReportEscape($record['designation'] ?? '') ?>
                                    </td>

                                    <td>
                                        <?= employeeReportEscape($record['phone'] ?? '') ?>
                                    </td>

                                    <td>
                                        <?= employeeReportEscape($record['email'] ?? '') ?>
                                    </td>

                                    <td>
                                        <?= employeeReportEscape($record['joining_date'] ?? '') ?>
                                    </td>

                                    <td>
                                        <span class="reports-status-badge status-<?= employeeReportEscape($rowStatus) ?>">
                                            <?= employeeReportEscape(ucfirst($rowStatus ?: 'Unknown')) ?>
                                        </span>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

                <div class="reports-table-footer">
                    <span>Showing <?= number_format($employeeTotal) ?> employee(s)</span>
                </div>

            <?php endif; ?>

        </section>

    </main>

    <?php require_once __DIR__ . '/../layouts/footer.php'; ?>
</div>
