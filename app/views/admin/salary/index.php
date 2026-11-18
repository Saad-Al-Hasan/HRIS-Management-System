
<?php
require_once __DIR__ . '/../../../core/Auth.php';

Auth::startSession();

$employees = $employees ?? [];
$salaryRecords = $salaryRecords ?? [];
$selectedMonth = $selectedMonth ?? '';
$selectedEmployeeId = $selectedEmployeeId ?? null;
$autoCalculationWarnings = $autoCalculationWarnings ?? [];
$success = $success ?? '';
$error = $error ?? '';

$formatMoney = static function ($amount): string {
    return '৳' . number_format((float) ($amount ?? 0), 2);
};

$totalBasicSalary = 0;
$totalDeductions = 0;
$totalFinalSalary = 0;

foreach ($salaryRecords as $record) {
    $totalBasicSalary += (float) ($record['basic_salary'] ?? 0);
    $totalDeductions += (float) ($record['deductions'] ?? 0);
    $totalFinalSalary += (float) ($record['final_salary'] ?? 0);
}

$recordCount = count($salaryRecords);
?>

<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

<div class="admin-main salary-management-page">

    <?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

    <main class="admin-content">

        <!-- PAGE HEADING -->
        <div class="salary-management-heading">
            <div>
                <span class="salary-management-eyebrow">PAYROLL MANAGEMENT</span>
                <h1>Salary Management</h1>
                <p>Calculate, manage and review employee monthly salaries.</p>
            </div>

            <div class="salary-heading-period">
                <span>PAYROLL PERIOD</span>
                <strong><?= htmlspecialchars(date('F Y')) ?></strong>
                <small>Current calculation month</small>
            </div>
        </div>


        <!-- SUCCESS / ERROR MESSAGES -->
        <?php if (!empty($success)): ?>
            <div class="salary-feedback salary-feedback-success">
                <span class="salary-feedback-icon">✓</span>
                <div>
                    <strong>Operation Successful</strong>
                    <p><?= htmlspecialchars($success) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="salary-feedback salary-feedback-error">
                <span class="salary-feedback-icon">!</span>
                <div>
                    <strong>Something Needs Attention</strong>
                    <p><?= htmlspecialchars($error) ?></p>
                </div>
            </div>
        <?php endif; ?>


        <!-- SALARY CONFIGURATION WARNINGS -->
        <?php if (!empty($autoCalculationWarnings)): ?>
            <section class="salary-warning-panel">

                <div class="salary-warning-heading">
                    <div class="salary-warning-icon">!</div>

                    <div>
                        <h2>Salary Configuration Warnings</h2>
                        <p>
                            Some employees need their monthly salary configured
                            before salary calculation can be completed.
                        </p>
                    </div>

                    <span class="salary-warning-count">
                        <?= count($autoCalculationWarnings) ?>
                        <?= count($autoCalculationWarnings) === 1 ? 'Warning' : 'Warnings' ?>
                    </span>
                </div>

                <div class="salary-warning-list">
                    <?php foreach ($autoCalculationWarnings as $warning): ?>
                        <div class="salary-warning-item">
                            <span class="salary-warning-dot"></span>
                            <span><?= htmlspecialchars($warning) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

            </section>
        <?php endif; ?>


        <!-- PAYROLL OVERVIEW -->
        <section class="salary-dashboard-section">

            <div class="salary-section-heading">
                <div>
                    <span class="salary-section-eyebrow">FINANCIAL OVERVIEW</span>
                    <h2>Payroll Summary</h2>
                    <p>
                        Overview of the salary records currently displayed below.
                    </p>
                </div>

                <span class="salary-records-indicator">
                    <?= $recordCount ?>
                    <?= $recordCount === 1 ? 'Record' : 'Records' ?>
                </span>
            </div>


            <div class="salary-summary-grid">

                <article class="salary-summary-card">
                    <div class="salary-summary-top">
                        <span class="salary-summary-label">Records Shown</span>
                        <span class="salary-summary-icon">▤</span>
                    </div>

                    <strong class="salary-summary-value">
                        <?= number_format($recordCount) ?>
                    </strong>

                    <small>Saved monthly salary records</small>
                </article>


                <article class="salary-summary-card">
                    <div class="salary-summary-top">
                        <span class="salary-summary-label">Total Basic Salary</span>
                        <span class="salary-summary-icon">৳</span>
                    </div>

                    <strong class="salary-summary-value">
                        <?= $formatMoney($totalBasicSalary) ?>
                    </strong>

                    <small>Combined basic salary amount</small>
                </article>


                <article class="salary-summary-card salary-summary-deduction">
                    <div class="salary-summary-top">
                        <span class="salary-summary-label">Total Deductions</span>
                        <span class="salary-summary-icon">−</span>
                    </div>

                    <strong class="salary-summary-value">
                        <?= $formatMoney($totalDeductions) ?>
                    </strong>

                    <small>Combined salary deductions</small>
                </article>


                <article class="salary-summary-card salary-summary-net">
                    <div class="salary-summary-top">
                        <span class="salary-summary-label">Net Payroll</span>
                        <span class="salary-summary-icon">✓</span>
                    </div>

                    <strong class="salary-summary-value">
                        <?= $formatMoney($totalFinalSalary) ?>
                    </strong>

                    <small>Combined final salary amount</small>
                </article>

            </div>

        </section>


        <!-- CALCULATE MONTHLY SALARY -->
        <section class="salary-calculation-panel">

            <div class="salary-panel-heading">
                <div class="salary-panel-icon">৳</div>

                <div>
                    <span class="salary-section-eyebrow">SALARY PROCESSING</span>
                    <h2>Calculate Monthly Salary</h2>
                    <p>
                        Generate or update an employee's monthly salary record.
                    </p>
                </div>
            </div>


            <form method="POST"
                action="/HRIS-Management-System/public/index.php?action=salary-calculate"
                class="salary-calculation-form">

                <input type="hidden" name="csrf_token"
                    value="<?= htmlspecialchars($csrfToken ?? '') ?>">

                <div class="salary-calculation-fields">

                    <div class="salary-field-group">
                        <label for="calculate_employee_id">
                            Select Employee
                            <span>*</span>
                        </label>

                        <select id="calculate_employee_id"
                            name="employee_id"
                            required>

                            <option value="">Choose an employee</option>

                            <?php foreach ($employees as $employee): ?>
                                <option value="<?= (int) $employee['employee_id'] ?>">
                                    <?= htmlspecialchars(
                                        $employee['employee_code'] . ' — ' . $employee['full_name']
                                    ) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>

                        <small>Select the employee whose salary you want to calculate.</small>
                    </div>


                    <div class="salary-field-group">
                        <label for="calculate_month">
                            Salary Month
                            <span>*</span>
                        </label>

                        <input type="month"
                            id="calculate_month"
                            name="month"
                            value="<?= htmlspecialchars(date('Y-m')) ?>"
                            max="<?= htmlspecialchars(date('Y-m')) ?>"
                            required>

                        <small>Select the month for salary processing.</small>
                    </div>

                </div>


                <div class="salary-calculation-footer">
                    <p>
                        <span class="salary-info-dot">i</span>
                        Calculating an existing employee/month record will update
                        that saved salary record.
                    </p>

                    <button type="submit"
                        class="salary-calculate-button"
                        onclick="return confirm('Calculate and save salary for this employee and month?');">

                        Calculate Salary
                        <span>→</span>

                    </button>
                </div>

            </form>

        </section>


        <!-- MONTHLY SALARY RECORDS -->
        <section class="salary-records-panel">

            <div class="salary-records-heading">
                <div>
                    <span class="salary-section-eyebrow">PAYROLL HISTORY</span>
                    <h2>Monthly Salary Records</h2>
                    <p>
                        Browse previously calculated and saved employee salary records.
                    </p>
                </div>

                <span class="salary-records-indicator">
                    <?= $recordCount ?>
                    <?= $recordCount === 1 ? 'Record' : 'Records' ?>
                </span>
            </div>


            <!-- FILTERS -->
            <form method="GET"
                action="/HRIS-Management-System/public/index.php"
                class="salary-record-filter">

                <input type="hidden"
                    name="action"
                    value="salary-management">


                <div class="salary-filter-fields">

                    <div class="salary-field-group">
                        <label for="filter_month">Filter by Month</label>

                        <input type="month"
                            id="filter_month"
                            name="month"
                            value="<?= htmlspecialchars($selectedMonth) ?>">
                    </div>


                    <div class="salary-field-group">
                        <label for="filter_employee_id">Filter by Employee</label>

                        <select id="filter_employee_id" name="employee_id">

                            <option value="">All Employees</option>

                            <?php foreach ($employees as $employee): ?>

                                <option value="<?= (int) $employee['employee_id'] ?>"
                                    <?= (string) $selectedEmployeeId === (string) $employee['employee_id']
                                        ? 'selected'
                                        : '' ?>>

                                    <?= htmlspecialchars(
                                        $employee['employee_code'] . ' — ' . $employee['full_name']
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>
                    </div>


                    <div class="salary-filter-actions">

                        <button type="submit" class="salary-filter-button">
                            Apply Filters
                            <span>→</span>
                        </button>

                        <a href="/HRIS-Management-System/public/index.php?action=salary-management"
                            class="salary-clear-button">
                            Clear Filters
                        </a>

                    </div>

                </div>

            </form>


            <!-- RECORD COUNT -->
            <div class="salary-table-toolbar">
                <span>
                    Showing
                    <strong><?= number_format($recordCount) ?></strong>
                    <?= $recordCount === 1 ? 'salary record' : 'salary records' ?>
                </span>

                <span class="salary-table-month">
                    <?= !empty($selectedMonth)
                        ? htmlspecialchars(date('F Y', strtotime($selectedMonth . '-01')))
                        : 'All available months' ?>
                </span>
            </div>


            <!-- SALARY TABLE -->
            <div class="salary-table-wrapper">

                <table class="salary-management-table">

                    <thead>
                        <tr>
                            <th class="salary-column-number">#</th>
                            <th>Employee</th>
                            <th>Salary Month</th>
                            <th>Basic Salary</th>
                            <th>Total Deductions</th>
                            <th>Net Salary</th>
                            <th>Calculated At</th>
                            <th class="salary-column-action">Action</th>
                        </tr>
                    </thead>


                    <tbody>

                        <?php if (empty($salaryRecords)): ?>

                            <tr>
                                <td colspan="8">
                                    <div class="salary-empty-state">
                                        <div class="salary-empty-icon">৳</div>
                                        <strong>No Salary Records Found</strong>
                                        <p>
                                            No saved salary records match your current filters.
                                            Try changing the month or employee selection.
                                        </p>
                                    </div>
                                </td>
                            </tr>

                        <?php else: ?>

                            <?php foreach ($salaryRecords as $index => $record): ?>

                                <tr>

                                    <td class="salary-row-number">
                                        <?= $index + 1 ?>
                                    </td>


                                    <td>
                                        <div class="salary-employee-cell">
                                            <strong>
                                                <?= htmlspecialchars($record['full_name'] ?? '—') ?>
                                            </strong>

                                            <small>
                                                <?= htmlspecialchars($record['employee_code'] ?? '—') ?>
                                            </small>
                                        </div>
                                    </td>


                                    <td>
                                        <span class="salary-month-label">
                                            <?= htmlspecialchars(
                                                date('F Y', strtotime($record['salary_month']))
                                            ) ?>
                                        </span>
                                    </td>


                                    <td class="salary-money-cell">
                                        <?= $formatMoney($record['basic_salary'] ?? 0) ?>
                                    </td>


                                    <td class="salary-deduction-cell">
                                        <?= $formatMoney($record['deductions'] ?? 0) ?>
                                    </td>


                                    <td>
                                        <strong class="salary-final-amount">
                                            <?= $formatMoney($record['final_salary'] ?? 0) ?>
                                        </strong>
                                    </td>


                                    <td>
                                        <span class="salary-calculated-date">
                                            <?= !empty($record['calculated_at'])
                                                ? htmlspecialchars(
                                                    date('d M Y, h:i:s A', strtotime($record['calculated_at']))
                                                )
                                                : '—' ?>
                                        </span>
                                    </td>


                                    <td class="salary-action-cell">
                                        <a href="/HRIS-Management-System/public/index.php?action=salary-details&id=<?= (int) $record['id'] ?>"
                                            class="salary-view-button">

                                            View Details
                                            <span>↗</span>

                                        </a>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

    <?php require_once __DIR__ . '/../layouts/footer.php'; ?>
</div>
