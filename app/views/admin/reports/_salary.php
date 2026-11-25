<?php
/**
 * Reports > Salary Report tab (US-11).
 * Included by reports/index.php, which provides $e, $formatMoney,
 * $salaryFilters, $salaryRows and $salarySummary.
 */

$salaryFilters = $salaryFilters ?? ['month' => date('Y-m'), 'employee_code' => ''];
$salaryRows = $salaryRows ?? [];
$salarySummary = $salarySummary ?? [
    'records' => 0,
    'basic_salary' => 0,
    'absence_deduction' => 0,
    'half_day_deduction' => 0,
    'late_early_deduction' => 0,
    'deductions' => 0,
    'final_salary' => 0,
];

// "month" is always sent (even when empty = all months) so the export
// receives exactly the same filter as the table.
$exportQuery = http_build_query([
    'action' => 'report-salary-export',
    'month' => $salaryFilters['month'],
    'employee_code' => $salaryFilters['employee_code'],
]);

$periodLabel = $salaryFilters['month'] !== ''
    ? date('F Y', strtotime($salaryFilters['month'] . '-01'))
    : 'All months';
?>

<section class="content-card report-card">

    <div class="card-header report-card-header">
        <div>
            <h2>Salary Report</h2>
            <p>Monthly salary records, salary breakdowns and applicable deductions.</p>
        </div>

        <a href="/HRIS-Management-System/public/index.php?<?= $e($exportQuery) ?>"
            class="btn btn-primary report-export-button">
            Export to Excel
        </a>
    </div>

    <!-- FILTERS -->
    <form method="GET" action="/HRIS-Management-System/public/index.php" class="report-filters">

        <input type="hidden" name="action" value="reports">
        <input type="hidden" name="tab" value="salary">

        <div class="report-filter">
            <label for="salary-month">Salary Month</label>
            <input type="month" id="salary-month" name="month"
                value="<?= $e($salaryFilters['month']) ?>">
            <small>Clear the field to see all months.</small>
        </div>

        <div class="report-filter">
            <label for="salary-employee-code">Employee Code</label>
            <input type="text" id="salary-employee-code" name="employee_code"
                placeholder="e.g. EMP-0003" maxlength="30"
                value="<?= $e($salaryFilters['employee_code']) ?>">
        </div>

        <div class="report-filter-actions">
            <button type="submit" class="btn btn-primary">Generate</button>
            <a href="/HRIS-Management-System/public/index.php?action=reports&amp;tab=salary"
                class="btn btn-secondary">Reset</a>
        </div>

    </form>

    <!-- SUMMARY -->
    <div class="report-summary">
        <div class="report-stat">
            <span>Period</span>
            <strong class="report-stat-text"><?= $e($periodLabel) ?></strong>
        </div>
        <div class="report-stat">
            <span>Records</span>
            <strong><?= (int) $salarySummary['records'] ?></strong>
        </div>
        <div class="report-stat">
            <span>Total Basic Salary</span>
            <strong><?= $e($formatMoney($salarySummary['basic_salary'])) ?></strong>
        </div>
        <div class="report-stat">
            <span>Total Deductions</span>
            <strong><?= $e($formatMoney($salarySummary['deductions'])) ?></strong>
        </div>
        <div class="report-stat">
            <span>Total Payable</span>
            <strong><?= $e($formatMoney($salarySummary['final_salary'])) ?></strong>
        </div>
    </div>

    <!-- TABLE -->
    <div class="table-responsive">
        <table class="data-table report-table">

            <thead>
                <tr>
                    <th>#</th>
                    <th>Employee Code</th>
                    <th>Employee Name</th>
                    <th>Month</th>
                    <th>Basic Salary</th>
                    <th>Absence</th>
                    <th>Half-Day</th>
                    <th>Late / Early</th>
                    <th>Total Deductions</th>
                    <th>Final Salary</th>
                    <th>Details</th>
                </tr>
            </thead>

            <tbody>
                <?php if (empty($salaryRows)): ?>
                    <tr>
                        <td colspan="11" class="empty-state">
                            No salary records found for the selected filters.
                            Salary records are created from Salary Processing.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($salaryRows as $index => $row): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= $e($row['employee_code']) ?></td>
                            <td><?= $e($row['full_name']) ?></td>
                            <td><?= $e(date('M Y', strtotime((string) $row['salary_month']))) ?></td>
                            <td><?= $e($formatMoney($row['basic_salary'])) ?></td>
                            <td><?= $e($formatMoney($row['absence_deduction'])) ?></td>
                            <td><?= $e($formatMoney($row['half_day_deduction'])) ?></td>
                            <td><?= $e($formatMoney($row['late_early_deduction'])) ?></td>
                            <td><?= $e($formatMoney($row['deductions'])) ?></td>
                            <td class="report-money-strong"><?= $e($formatMoney($row['final_salary'])) ?></td>
                            <td>
                                <a class="table-action"
                                    href="/HRIS-Management-System/public/index.php?action=salary-details&amp;id=<?= (int) $row['id'] ?>">
                                    View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>

            <?php if (!empty($salaryRows)): ?>
                <tfoot>
                    <tr class="report-total-row">
                        <td colspan="4">Total</td>
                        <td><?= $e($formatMoney($salarySummary['basic_salary'])) ?></td>
                        <td><?= $e($formatMoney($salarySummary['absence_deduction'])) ?></td>
                        <td><?= $e($formatMoney($salarySummary['half_day_deduction'])) ?></td>
                        <td><?= $e($formatMoney($salarySummary['late_early_deduction'])) ?></td>
                        <td><?= $e($formatMoney($salarySummary['deductions'])) ?></td>
                        <td class="report-money-strong"><?= $e($formatMoney($salarySummary['final_salary'])) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            <?php endif; ?>

        </table>
    </div>

</section>
