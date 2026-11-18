<?php
require_once __DIR__ . '/../../../core/Auth.php';

Auth::startSession();

$salaryRecord = $salaryRecord ?? [];
$breakdown = $salaryRecord['calculation_details'] ?? [];
$employee = $breakdown['employee'] ?? [];
$dailyRecords = $breakdown['daily_records'] ?? [];

$money = static function ($amount): string {
    return '৳' . number_format((float) $amount, 2);
};

$formatTime = static function ($time): string {
    if (empty($time)) {
        return '—';
    }

    $timestamp = strtotime($time);

    return $timestamp !== false
        ? date('h:i:s A', $timestamp)
        : htmlspecialchars((string) $time);
};

$employeeName = $salaryRecord['full_name'] ?? 'Employee';
$employeeInitial = strtoupper(substr($employeeName, 0, 1));



/* Employee Profile Picture */

$employeeProfilePictureDirectory = dirname(__DIR__, 4)
    . '/public/uploads/profile_pictures/';

$employeeDefaultProfilePicture =
    '/HRIS-Management-System/public/assets/images/employee-avatar.png';

$employeeProfilePictureUrl = $employeeDefaultProfilePicture;

$employeeUserId = (int) ($salaryRecord['user_id'] ?? 0);

if ($employeeUserId > 0) {
    $supportedExtensions = ['png', 'jpg', 'jpeg'];

    foreach ($supportedExtensions as $extension) {
        $fileName = 'userid_' . $employeeUserId . '.' . $extension;

        $filePath = $employeeProfilePictureDirectory . $fileName;

        if (is_file($filePath)) {
            $employeeProfilePictureUrl =
                '/HRIS-Management-System/public/uploads/profile_pictures/'
                . rawurlencode($fileName);

            break;
        }
    }
}


$salaryMonth = !empty($salaryRecord['salary_month'])
    ? date('F Y', strtotime($salaryRecord['salary_month']))
    : '—';

$joiningDate = !empty($employee['joining_date'])
    ? date('d M Y', strtotime($employee['joining_date']))
    : '—';

$calculatedThrough = !empty($breakdown['calculated_through'])
    ? date('d M Y', strtotime($breakdown['calculated_through']))
    : '—';

$calculatedAt = !empty($salaryRecord['calculated_at'])
    ? date('d M Y, h:i:s A', strtotime($salaryRecord['calculated_at']))
    : '—';

$attendanceStats = [
    [
        'Calendar Days',
        $breakdown['calendar_days'] ?? 0,
        'calendar',
        'blue'
    ],
    [
        'Eligible Working Days',
        $breakdown['eligible_days'] ?? 0,
        'briefcase-business',
        'cyan'
    ],
    [
        'Paid Fridays',
        $breakdown['paid_fridays'] ?? 0,
        'calendar-check',
        'violet'
    ],
    [
        'Paid Holidays',
        $breakdown['paid_holidays'] ?? 0,
        'gift',
        'gold'
    ],
    [
        'Full-Day Leaves',
        $breakdown['full_day_leaves'] ?? 0,
        'calendar-heart',
        'green'
    ],
    [
        'Valid Half-Day Leaves',
        $breakdown['valid_half_day_leaves'] ?? 0,
        'clock',
        'teal'
    ],
    [
        'Incomplete Half-Day Leaves',
        $breakdown['incomplete_half_day_leaves'] ?? 0,
        'alert-circle',
        'orange'
    ],
    [
        'Absent Days',
        $breakdown['absent_days'] ?? 0,
        'user-x',
        'red'
    ],
];

$statusClass = static function ($type): string {
    $type = strtolower((string) $type);

    if (str_contains($type, 'absent') || str_contains($type, 'incomplete')) {
        return 'salary-status-danger';
    }

    if (str_contains($type, 'late') || str_contains($type, 'before')) {
        return 'salary-status-warning';
    }

    if (str_contains($type, 'present') || str_contains($type, 'half day leave')) {
        return 'salary-status-success';
    }

    if (str_contains($type, 'friday') || str_contains($type, 'holiday')) {
        return 'salary-status-info';
    }

    if (str_contains($type, 'progress')) {
        return 'salary-status-pending';
    }

    return 'salary-status-neutral';
};
?>

<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

<div class="admin-main salary-details-page">

    <?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

    <main class="admin-content">

        <!-- Page Heading -->
        <div class="content-header salary-page-heading">
            <div>
                <span class="salary-eyebrow">PAYROLL MANAGEMENT</span>
                <h1>Salary Details</h1>
                <p>Complete salary statement and monthly attendance breakdown.</p>
            </div>

            <div class="salary-page-actions">

                <button type="button" class="btn salary-download-pdf-btn" onclick="downloadSalaryDetailsPdf()">

                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path d="M12 3v12" />
                        <path d="m7 10 5 5 5-5" />
                        <path d="M5 17v4h14v-4" />
                    </svg>

                    Download As PDF
                </button>

                <a href="/HRIS-Management-System/public/index.php?action=salary-management"
                    class="btn btn-secondary salary-back-btn">
                    ← Back to Salary Management
                </a>

            </div>
        </div>


        <!-- Employee Profile Header -->
        <section class="salary-employee-banner">

            <div class="salary-employee-identity">

                <img src="<?= htmlspecialchars($employeeProfilePictureUrl, ENT_QUOTES, 'UTF-8') ?>"
                    alt="<?= htmlspecialchars($employeeName, ENT_QUOTES, 'UTF-8') ?>" class="salary-employee-avatar"
                    onerror="this.onerror=null; this.src='<?= htmlspecialchars($employeeDefaultProfilePicture, ENT_QUOTES, 'UTF-8') ?>';">

                <div class="salary-employee-name">
                    <span>EMPLOYEE PAYROLL STATEMENT</span>

                    <h2>
                        <?= htmlspecialchars($employeeName) ?>
                    </h2>

                    <div class="salary-employee-meta">
                        <span>
                            <?= htmlspecialchars($salaryRecord['employee_code'] ?? '—') ?>
                        </span>

                        <span>•</span>

                        <span>
                            Joined <?= htmlspecialchars($joiningDate) ?>
                        </span>
                    </div>
                </div>

            </div>

            <div class="salary-period-info">
                <span>PAYROLL PERIOD</span>

                <strong>
                    <?= htmlspecialchars($salaryMonth) ?>

                    <?php
                    $isProvisional = date('Y-m', strtotime($salaryRecord['salary_month']))
                        === date('Y-m');
                    ?>

                    <span class="salary-period-badge <?= $isProvisional ? 'provisional' : 'finalized' ?>">
                        <?= $isProvisional ? 'PROVISIONAL' : 'FINALIZED' ?>
                    </span>
                </strong>

                <small>
                    Calculated through <?= htmlspecialchars($calculatedThrough) ?>
                </small>
            </div>

        </section>


        <!-- Salary Overview -->
        <section class="salary-section salary-group-card salary-summary-group">

            <div class="salary-section-heading">
                <div>
                    <span class="salary-eyebrow">FINANCIAL OVERVIEW</span>
                    <h2>Salary Summary</h2>
                    <p>Monthly salary and final payable amount.</p>
                </div>
            </div>

            <div class="salary-overview-grid">

                <!-- Monthly Basic Salary -->
                <div class="salary-metric-card salary-basic-card">

                    <div class="salary-metric-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                            stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="5" width="18" height="14" rx="2" />
                            <path d="M3 10h18M7 15h3" />
                            <path d="M16 14v3m-1.5-1.5h3" />
                        </svg>
                    </div>

                    <div class="salary-metric-content">
                        <span class="salary-metric-label">Monthly Basic Salary</span>
                        <strong><?= $money($salaryRecord['basic_salary'] ?? 0) ?></strong>
                        <small>Employee's monthly salary</small>
                    </div>

                </div>


                <!-- Daily Salary -->
                <div class="salary-metric-card salary-daily-card">

                    <div class="salary-metric-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                            stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="9" />
                            <path d="M12 7v5l3 2" />
                            <path d="M8 3.8 6.5 2.5M16 3.8l1.5-1.3" />
                        </svg>
                    </div>

                    <div class="salary-metric-content">
                        <span class="salary-metric-label">Daily Salary</span>
                        <strong><?= $money($breakdown['daily_salary'] ?? 0) ?></strong>
                        <small>Calculated per calendar day</small>
                    </div>

                </div>


                <!-- Total Deductions -->
                <div class="salary-metric-card salary-deduction-card">

                    <div class="salary-metric-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                            stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="9" />
                            <path d="M8 12h8" />
                        </svg>
                    </div>

                    <div class="salary-metric-content">
                        <span class="salary-metric-label">Total Deductions</span>
                        <strong><?= $money($salaryRecord['deductions'] ?? 0) ?></strong>
                        <small>All applicable deductions</small>
                    </div>

                </div>


                <!-- Net Payable Salary -->
                <div class="salary-metric-card salary-net-card">

                    <div class="salary-metric-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                            stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="5" width="18" height="14" rx="2" />
                            <path d="M7 12h.01M17 12h.01M10 9h4M10 15h4" />
                            <path d="m9 12 2 2 4-4" />
                        </svg>
                    </div>

                    <div class="salary-metric-content">
                        <span class="salary-metric-label">NET PAYABLE SALARY</span>
                        <strong><?= $money($salaryRecord['final_salary'] ?? 0) ?></strong>
                        <small>Final calculated amount</small>
                    </div>

                </div>

            </div>

        </section>


        <!-- Attendance and Leave Summary -->
        <section class="salary-section salary-group-card salary-attendance-group">

            <div class="salary-section-heading">
                <div>
                    <span class="salary-eyebrow">MONTHLY OVERVIEW</span>
                    <h2>Attendance & Leave Summary</h2>
                    <p>Monthly attendance evaluation and paid days.</p>
                </div>
            </div>

            <div class="salary-attendance-grid">

                <?php foreach ($attendanceStats as [$label, $value, $icon, $tone]): ?>

                    <div class="salary-attendance-item salary-stat-<?= $tone ?>">

                        <div class="salary-stat-icon">

                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">

                                <?php if ($icon === 'calendar'): ?>

                                    <rect x="3" y="5" width="18" height="16" rx="2" />
                                    <path d="M16 3v4M8 3v4M3 10h18M8 14h2M14 14h2M8 17h2" />

                                <?php elseif ($icon === 'briefcase-business'): ?>

                                    <rect x="3" y="7" width="18" height="14" rx="2" />
                                    <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18M10 12v2h4v-2" />

                                <?php elseif ($icon === 'calendar-check'): ?>

                                    <rect x="3" y="5" width="18" height="16" rx="2" />
                                    <path d="M16 3v4M8 3v4M3 10h18M8 15l2 2 5-5" />

                                <?php elseif ($icon === 'gift'): ?>

                                    <rect x="3" y="9" width="18" height="12" rx="1" />
                                    <path
                                        d="M2 9h20v4H2zM12 9v12M12 9H7.5a2.5 2.5 0 1 1 2.5-2.5V9ZM12 9h4.5A2.5 2.5 0 1 0 14 6.5V9Z" />

                                <?php elseif ($icon === 'calendar-heart'): ?>

                                    <rect x="3" y="5" width="18" height="16" rx="2" />
                                    <path d="M16 3v4M8 3v4M3 10h18" />
                                    <path d="M12 18s-4-2.2-4-4.5a2 2 0 0 1 4-0.5 2 2 0 0 1 4 0.5C16 15.8 12 18 12 18Z" />

                                <?php elseif ($icon === 'clock'): ?>

                                    <circle cx="12" cy="12" r="9" />
                                    <path d="M12 7v5l3 2" />

                                <?php elseif ($icon === 'alert-circle'): ?>

                                    <circle cx="12" cy="12" r="9" />
                                    <path d="M12 8v5M12 16.5h.01" />

                                <?php elseif ($icon === 'user-x'): ?>

                                    <circle cx="9" cy="8" r="4" />
                                    <path d="M2 21v-2a7 7 0 0 1 12-4.9M16 17l5 5M21 17l-5 5" />

                                <?php endif; ?>

                            </svg>

                        </div>

                        <div class="salary-stat-info">
                            <span><?= htmlspecialchars($label) ?></span>
                            <strong><?= (int) $value ?></strong>
                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        </section>


        <!-- Deduction Breakdown -->
        <section class="salary-section salary-group-card salary-deduction-group">

            <div class="salary-section-heading">
                <div>
                    <span class="salary-eyebrow">PAYROLL ADJUSTMENTS</span>
                    <h2>Deduction Breakdown</h2>
                    <p>A transparent breakdown of all salary deductions.</p>
                </div>
            </div>

            <div class="salary-panel">

                <div class="table-responsive">

                    <table class="data-table salary-deduction-table">

                        <thead>
                            <tr>
                                <th>Deduction Type</th>
                                <th>Occurrences / Days</th>
                                <th>Deduction Amount</th>
                            </tr>
                        </thead>

                        <tbody>

                            <tr>
                                <td>
                                    <strong>Absence Deduction</strong>
                                    <small>Unapproved working-day absence</small>
                                </td>

                                <td>
                                    <?= (int) ($breakdown['absent_days'] ?? 0) ?> days
                                </td>

                                <td class="salary-amount-negative">
                                    <?= $money($breakdown['absence_deduction'] ?? 0) ?>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <strong>Incomplete Half-Day Leave</strong>
                                    <small>Required working half not completed</small>
                                </td>

                                <td>
                                    <?= (int) ($breakdown['incomplete_half_day_leaves'] ?? 0) ?> days
                                </td>

                                <td class="salary-amount-negative">
                                    <?= $money($breakdown['half_day_deduction'] ?? 0) ?>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <strong>Late Entry & Early Exit</strong>
                                    <small>Occurrences exceeding the free allowance</small>
                                </td>

                                <td>
                                    <?= (int) ($breakdown['late_occurrences'] ?? 0) ?> late +
                                    <?= (int) ($breakdown['early_exit_occurrences'] ?? 0) ?> early exits
                                </td>

                                <td class="salary-amount-negative">
                                    <?= $money($breakdown['late_early_deduction'] ?? 0) ?>
                                </td>
                            </tr>

                        </tbody>

                        <tfoot>
                            <tr>
                                <th colspan="2">Total Deductions</th>
                                <th class="salary-amount-negative">
                                    <?= $money($salaryRecord['deductions'] ?? 0) ?>
                                </th>
                            </tr>
                        </tfoot>

                    </table>

                </div>

                <div class="salary-rule-note">
                    <span class="salary-rule-icon">i</span>

                    <p>
                        The first three combined late-entry and early-exit occurrences
                        are free. Each additional occurrence deducts half a day's salary.
                    </p>
                </div>

            </div>

        </section>


        <!-- Daily Attendance Ledger -->
        <section class="salary-section salary-group-card salary-ledger-group">

            <div class="salary-section-heading">
                <div>
                    <span class="salary-eyebrow">DAILY RECORDS</span>
                    <h2>Daily Attendance Ledger</h2>
                    <p>Day-by-day attendance and salary evaluation.</p>
                </div>

                <span class="salary-record-count">
                    <?= count($dailyRecords) ?> Records
                </span>
            </div>

            <div class="salary-panel">

                <div class="table-responsive">

                    <table class="data-table salary-ledger-table">

                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Attendance</th>
                                <th>Entry Time</th>
                                <th>Entry Status</th>
                                <th>Exit Time</th>
                                <th>Exit Status</th>
                                <th>Deduction</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if (empty($dailyRecords)): ?>

                                <tr>
                                    <td colspan="8" class="empty-state">
                                        No daily calculation records available.
                                    </td>
                                </tr>

                            <?php else: ?>

                                <?php foreach ($dailyRecords as $day): ?>

                                    <tr>

                                        <td class="salary-ledger-date">
                                            <?= htmlspecialchars(
                                                date('d M Y', strtotime($day['date']))
                                            ) ?>
                                        </td>

                                        <td>
                                            <span class="salary-status-badge <?= $statusClass($day['type'] ?? '') ?>">
                                                <?= htmlspecialchars($day['type'] ?? '—') ?>
                                            </span>
                                        </td>

                                        <td><?= $formatTime($day['entry_time'] ?? null) ?></td>

                                        <td>
                                            <?= htmlspecialchars($day['entry_status'] ?? '—') ?>
                                        </td>

                                        <td><?= $formatTime($day['exit_time'] ?? null) ?></td>

                                        <td>
                                            <?= htmlspecialchars($day['exit_status'] ?? '—') ?>
                                        </td>

                                        <td
                                            class="<?= (float) ($day['deduction'] ?? 0) > 0 ? 'salary-amount-negative' : 'salary-amount-neutral' ?>">
                                            <?= $money($day['deduction'] ?? 0) ?>
                                        </td>

                                        <td class="salary-ledger-remarks">
                                            <?= htmlspecialchars($day['note'] ?? '—') ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </section>


        <!-- Calculation Information -->
        <div class="salary-calculation-footer">
            <span>Last calculated: <?= htmlspecialchars($calculatedAt) ?></span>
            <span>Calculated through: <?= htmlspecialchars($calculatedThrough) ?></span>
        </div>

        <script>
            function downloadSalaryDetailsPdf() {
                const page = document.querySelector('.salary-details-page');
                const content = page?.querySelector('.admin-content');

                if (!page || !content) {
                    alert('Unable to prepare the Salary Details PDF.');
                    return;
                }

                // Capture the actual screen dimensions before printing.
                const pageWidth = Math.ceil(content.getBoundingClientRect().width);
                const pageHeight = Math.ceil(content.scrollHeight + 40);

                const previousTitle = document.title;

                document.title = 'Salary Details - ' +
                    (document.querySelector('.salary-employee-name h2')?.textContent.trim() || 'Employee');

                const printStyle = document.createElement('style');
                printStyle.id = 'salary-long-pdf-style';

                printStyle.textContent = `
        @page {
            size: ${pageWidth}px ${pageHeight}px;
            margin: 0;
        }

        @media print {

            html, body {
                width: ${pageWidth}px !important;
                margin: 0 !important;
                padding: 0 !important;
                background: #101214 !important;
                color: #ffffff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .admin-sidebar {
                display: none !important;
            }

            .salary-details-page > :not(.admin-content) {
                display: none !important;
            }

            .salary-page-actions {
                display: none !important;
            }

            /* Preserve original left alignment of page heading */

            .salary-page-heading {
                display: flex !important;
                flex-direction: row !important;
                justify-content: flex-start !important;
                align-items: flex-start !important;
                text-align: left !important;
            }

            .salary-page-heading > div:first-child {
                width: 100% !important;
                text-align: left !important;
                margin-left: 0 !important;
                margin-right: auto !important;
            }

            .salary-page-heading .salary-eyebrow,
            .salary-page-heading h1,
            .salary-page-heading p {
                text-align: left !important;
                margin-left: 0 !important;
            }

            .admin-main.salary-details-page {
                width: ${pageWidth}px !important;
                max-width: none !important;
                min-height: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .admin-content {
                width: ${pageWidth}px !important;
                max-width: none !important;
                margin: 0 !important;
                box-sizing: border-box !important;
            }

            .salary-overview-grid {
                display: grid !important;
                grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
            }

            .salary-attendance-grid {
                display: grid !important;
                grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
            }

            /* No artificial section page breaks */
            .salary-details-page section,
            .salary-group-card,
            .salary-panel {
                break-inside: auto !important;
                page-break-inside: auto !important;
            }

            /* Keep individual cards together */
            .salary-metric-card,
            .salary-attendance-item,
            .salary-employee-banner {
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }

            /* Keep table rows intact */
            .salary-ledger-table,
            .salary-deduction-table {
                width: 100% !important;
                border-collapse: collapse !important;
            }

            .salary-ledger-table thead,
            .salary-deduction-table thead {
                display: table-header-group !important;
            }

            .salary-ledger-table tr,
            .salary-deduction-table tr {
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }

            .table-responsive {
                overflow: visible !important;
                width: 100% !important;
            }

            /* Preserve all colors and gradients */
            .salary-details-page * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            /* Disable animations and hover transformations */
            .salary-details-page * {
                animation: none !important;
                transition: none !important;
            }

            .salary-metric-card,
            .salary-attendance-item {
                transform: none !important;
            }
        }
    `;

                document.head.appendChild(printStyle);

                window.addEventListener('afterprint', function cleanup() {
                    printStyle.remove();
                    document.title = previousTitle;
                }, { once: true });

                window.print();
            }
        </script>

    </main>

    <?php require_once __DIR__ . '/../layouts/footer.php'; ?>
</div>