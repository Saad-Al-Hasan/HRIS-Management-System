<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../core/Auth.php';

Auth::startSession();

$pageTitle = 'Administrative Attendance Management';

$attendanceRecords = $attendanceRecords ?? [];
$departments = $departments ?? [];
$employees = $employees ?? [];

$selectedDepartment = $selectedDepartment ?? '';
$selectedEmployee = $selectedEmployee ?? '';
$fromDate = $fromDate ?? '';
$toDate = $toDate ?? '';

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

?>

<div class="admin-main">

    <?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

    <main class="admin-content">

        <div class="attendance-management-page">

            <!-- Page Heading -->
            <section class="attendance-page-heading">

                <div>
                    <h1>Administrative Attendance Management</h1>

                    <p>
                        Monitor employee attendance, working hours, and attendance status.
                    </p>
                </div>

                <div class="attendance-record-count">
                    <?= count($attendanceRecords) ?>
                    <?= count($attendanceRecords) === 1 ? 'Record' : 'Records' ?>
                </div>

            </section>


            <!-- Department, Employee, and Date Filters -->
            <section class="attendance-filter-card">

                <form method="GET" action="/HRIS-Management-System/public/index.php" class="attendance-filter-form">

                    <input type="hidden" name="action" value="attendance-management">


                    <!-- Department Dropdown -->
                    <div class="attendance-filter-field">

                        <label for="department">
                            Select Department
                        </label>

                        <select id="department" name="department" required onchange="this.form.submit()">

                            <option value="">
                                Select Department
                            </option>

                            <?php foreach ($departments as $department): ?>

                                <option value="<?= htmlspecialchars(
                                    (string) $department['department'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>" <?= (string) $selectedDepartment ===
                                     (string) $department['department']
                                     ? 'selected'
                                     : '' ?>>

                                    <?= htmlspecialchars(
                                        (string) $department['department'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- Employee Dropdown -->
                    <div class="attendance-filter-field">

                        <label for="employee">
                            Select Employee
                        </label>

                        <select id="employee" name="employee" <?= $selectedDepartment === '' ? 'disabled' : '' ?> required
                            onchange="this.form.submit()">

                            <option value="">
                                Select Employee
                            </option>

                            <?php foreach ($employees as $employee): ?>

                                <option value="<?= (int) $employee['id'] ?>" <?= (string) $selectedEmployee ===
                                       (string) $employee['id']
                                       ? 'selected'
                                       : '' ?>>

                                    <?= htmlspecialchars(
                                        (string) $employee['employee_code'] .
                                        ' - ' .
                                        (string) $employee['full_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- From Date -->
                    <div class="attendance-filter-field">

                        <label for="from-date">
                            From Date
                        </label>

                        <input type="date" id="from-date" name="from_date" value="<?= htmlspecialchars(
                            $fromDate,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>" <?= $selectedEmployee === '' ? 'disabled' : '' ?>>

                    </div>


                    <!-- To Date -->
                    <div class="attendance-filter-field">

                        <label for="to-date">
                            To Date
                        </label>

                        <input type="date" id="to-date" name="to_date" value="<?= htmlspecialchars(
                            $toDate,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>" <?= $selectedEmployee === '' ? 'disabled' : '' ?>>

                    </div>


                    <!-- Action Buttons -->
                    <div class="attendance-filter-actions">

                        <button type="submit" name="view_attendance" value="1" class="attendance-filter-button"
                            <?= $selectedEmployee === '' ? 'disabled' : '' ?>>
                            View Attendance
                        </button>

                        <a href="/HRIS-Management-System/public/index.php?action=attendance-management"
                            class="attendance-reset-button">
                            Reset
                        </a>

                    </div>

                </form>


                <p class="attendance-filter-help">

                    Select a department and employee, then choose a date range
                    to view the employee's attendance records.

                </p>

            </section>



            <?php if (!empty($error)): ?>
                <div class="attendance-validation-error" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>


            <!-- Employee Attendance Records -->
            <section class="attendance-table-card">

                <div class="attendance-table-heading">

                    <div>

                        <h2>Employee Attendance Records</h2>

                        <p>
                            Recorded entry and exit information for MYOL employees.
                        </p>

                    </div>

                </div>


                <div class="attendance-table-wrapper">

                    <table class="attendance-management-table">

                        <thead>

                            <tr>
                                <th>Employee ID</th>
                                <th>Employee Name</th>
                                <th>Date</th>
                                <th>Entry Time</th>
                                <th>Entry Status</th>
                                <th>Exit Time</th>
                                <th>Exit Status</th>
                                <th>Attendance Status</th>
                                <th class="attendance-log-heading">View Log</th>
                            </tr>

                        </thead>


                        <tbody>

                            <?php if (
                                $selectedEmployee === '' ||
                                empty($attendanceRecords)
                            ): ?>

                                <tr>

                                    <td colspan="9" class="attendance-empty-state">

                                        <strong>
                                            <?php if ($selectedEmployee === ''): ?>

                                                Select an employee to view attendance.

                                            <?php else: ?>

                                                No attendance records found.

                                            <?php endif; ?>
                                        </strong>

                                        <span>
                                            <?php if ($selectedEmployee === ''): ?>

                                                Choose a department and employee,
                                                then select a date range.

                                            <?php else: ?>

                                                Try another date range or select
                                                a different employee.

                                            <?php endif; ?>
                                        </span>

                                    </td>

                                </tr>

                            <?php else: ?>

                                <?php foreach ($attendanceRecords as $record): ?>

                                    <?php

                                    $attendanceStatus = strtolower(
                                        trim(
                                            (string) (
                                                $record['attendance_status']
                                                ?? 'unknown'
                                            )
                                        )
                                    );

                                    $entryStatus = trim(
                                        (string) (
                                            $record['entry_status'] ?? ''
                                        )
                                    );

                                    $exitStatus = trim(
                                        (string) (
                                            $record['exit_status'] ?? ''
                                        )
                                    );

                                    $entryStatusNormalized = strtolower(
                                        $entryStatus
                                    );

                                    $exitStatusNormalized = strtolower(
                                        $exitStatus
                                    );

                                    $statusClass = match ($attendanceStatus) {
                                        'present' => 'status-present',
                                        'late' => 'status-late',
                                        'absent' => 'status-absent',
                                        'leave' => 'status-leave',
                                        'holiday' => 'status-holiday',
                                        default => 'status-unknown',
                                    };

                                    $entryStatusClass =
                                        str_contains(
                                            $entryStatusNormalized,
                                            'late'
                                        )
                                        ? 'status-late'
                                        : 'status-present';

                                    $exitStatusClass =
                                        str_contains(
                                            $exitStatusNormalized,
                                            'before'
                                        )
                                        ? 'status-late'
                                        : 'status-present';

                                    $attendanceDate =
                                        $record['attendance_date'] ?? '';

                                    $entryTime =
                                        $record['entry_time'] ?? null;

                                    $exitTime =
                                        $record['exit_time'] ?? null;

                                    ?>

                                    <tr>

                                        <!-- Employee ID -->
                                        <td>

                                            <span class="attendance-employee-code">

                                                <?= htmlspecialchars(
                                                    (string) (
                                                        $record['employee_code']
                                                        ?? 'N/A'
                                                    ),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                            </span>

                                        </td>


                                        <!-- Employee Name -->
                                        <td>

                                            <span class="attendance-employee-name">

                                                <?= htmlspecialchars(
                                                    (string) (
                                                        $record['full_name']
                                                        ?? 'Unknown Employee'
                                                    ),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                            </span>

                                            <?php
                                            $employeeDepartment = trim(
                                                (string) ($record['department'] ?? '')
                                            );

                                            $employeeDesignation = trim(
                                                (string) ($record['designation'] ?? '')
                                            );

                                            $employeeDetails = array_filter(
                                                [$employeeDepartment, $employeeDesignation],
                                                static fn($value) => $value !== ''
                                            );
                                            ?>

                                            <?php if (!empty($employeeDetails)): ?>
                                                <small class="attendance-employee-department">
                                                    <?= htmlspecialchars(
                                                        implode(' ; ', $employeeDetails),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>
                                                </small>
                                            <?php endif; ?>

                                        </td>


                                        <!-- Attendance Date -->
                                        <td>

                                            <?= htmlspecialchars(
                                                (string) $attendanceDate,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </td>


                                        <!-- Entry Time -->
                                        <td>

                                            <?php if (!empty($entryTime)): ?>

                                                <?= htmlspecialchars(
                                                    date(
                                                        'h:i:s A',
                                                        strtotime(
                                                            (string) $entryTime
                                                        )
                                                    ),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                            <?php else: ?>

                                                <span class="attendance-not-recorded">
                                                    Not recorded
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- Entry Status -->
                                        <td>

                                            <?php if ($entryStatus !== ''): ?>

                                                <span class="attendance-status-badge <?= $entryStatusClass ?>">

                                                    <?= htmlspecialchars(
                                                        $entryStatus,
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>

                                                </span>

                                            <?php else: ?>

                                                <span class="attendance-not-recorded">
                                                    —
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- Exit Time -->
                                        <td>

                                            <?php if (!empty($exitTime)): ?>

                                                <?= htmlspecialchars(
                                                    date(
                                                        'h:i:s A',
                                                        strtotime(
                                                            (string) $exitTime
                                                        )
                                                    ),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                            <?php else: ?>

                                                <span class="attendance-not-recorded">
                                                    Not recorded
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- Exit Status -->
                                        <td>

                                            <?php if ($exitStatus !== ''): ?>

                                                <span class="attendance-status-badge <?= $exitStatusClass ?>">

                                                    <?= htmlspecialchars(
                                                        $exitStatus,
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>

                                                </span>

                                            <?php else: ?>

                                                <span class="attendance-not-recorded">
                                                    —
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- Overall Attendance Status -->
                                        <td>

                                            <span class="attendance-status-badge <?= $statusClass ?>">

                                                <?= htmlspecialchars(
                                                    ucfirst($attendanceStatus),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                            </span>

                                        </td>

                                        <!-- View Log Button -->
                                        <td class="attendance-log-action-cell">

                                            <?php
                                            $logRowId = 'attendance-log-' .
                                                (int) ($record['attendance_id'] ?? 0);
                                            ?>

                                            <button type="button" class="attendance-log-toggle" aria-expanded="false"
                                                aria-controls="<?= htmlspecialchars(
                                                    $logRowId,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>" title="View RFID scan history" aria-label="View RFID scan history for <?= htmlspecialchars(
                                                     (string) ($record['employee_code'] ?? ''),
                                                     ENT_QUOTES,
                                                     'UTF-8'
                                                 ) ?> on <?= htmlspecialchars(
                                                       (string) $attendanceDate,
                                                       ENT_QUOTES,
                                                       'UTF-8'
                                                   ) ?>">
                                                <svg class="attendance-log-eye-icon" xmlns="http://www.w3.org/2000/svg"
                                                    width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                                                    aria-hidden="true">
                                                    <!-- Open eye -->
                                                    <g class="eye-open-icon">
                                                        <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z" />
                                                        <circle cx="12" cy="12" r="3" />
                                                    </g>

                                                    <!-- Closed eye -->
                                                    <g class="eye-closed-icon" style="display: none;">
                                                        <path d="M3 3l18 18" />
                                                        <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8" />
                                                        <path
                                                            d="M9.9 5.2A11.5 11.5 0 0 1 12 5c6.4 0 10 7 10 7a16 16 0 0 1-3.1 3.9" />
                                                        <path d="M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7c1.5 0 2.8-.4 4-1" />
                                                    </g>
                                                </svg>
                                            </button>

                                        </td>

                                    </tr>

                                    <!-- Expandable RFID Scan History -->
                                    <tr id="<?= htmlspecialchars(
                                        $logRowId,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>" class="attendance-log-expanded-row" hidden>
                                        <td colspan="9">

                                            <div class="attendance-log-panel">

                                                <div class="attendance-log-panel-heading">

                                                    <?php
                                                    $profileUserId = (int) ($record['user_id'] ?? 0);

                                                    $profilePictureDirectory = 'C:/laragon/www/HRIS-Management-System/public/uploads/profile_pictures/';
                                                    $profilePictureUrl = '/HRIS-Management-System/public/assets/images/employee-avatar.png';

                                                    if ($profileUserId > 0) {
                                                        foreach (['png', 'jpg', 'jpeg'] as $extension) {
                                                            $filename = "userid_{$profileUserId}.{$extension}";

                                                            if (is_file($profilePictureDirectory . $filename)) {
                                                                $profilePictureUrl = '/HRIS-Management-System/public/uploads/profile_pictures/' . $filename;
                                                                break;
                                                            }
                                                        }
                                                    }
                                                    ?>

                                                    <div class="attendance-log-employee-identity">

                                                        <img class="attendance-log-employee-photo" src="<?= htmlspecialchars(
                                                            $profilePictureUrl,
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>" alt="<?= htmlspecialchars(
                                                             (string) ($record['full_name'] ?? 'Employee'),
                                                             ENT_QUOTES,
                                                             'UTF-8'
                                                         ) ?>"
                                                            onerror="this.onerror=null;this.src='/HRIS-Management-System/public/assets/images/employee-avatar.png';">

                                                        <div class="attendance-log-employee-details">

                                                            <h3>RFID Scan History</h3>

                                                            <p>
                                                                <?= htmlspecialchars(
                                                                    (string) ($record['full_name'] ?? ''),
                                                                    ENT_QUOTES,
                                                                    'UTF-8'
                                                                ) ?>
                                                                &middot;
                                                                <?= htmlspecialchars(
                                                                    (string) ($record['employee_code'] ?? ''),
                                                                    ENT_QUOTES,
                                                                    'UTF-8'
                                                                ) ?>
                                                                &middot;
                                                                <?= htmlspecialchars(
                                                                    (string) $attendanceDate,
                                                                    ENT_QUOTES,
                                                                    'UTF-8'
                                                                ) ?>
                                                            </p>

                                                        </div>

                                                    </div>

                                                    <span class="attendance-log-count">
                                                        <?= count($record['scan_history'] ?? []) ?>
                                                        <?= count($record['scan_history'] ?? []) === 1
                                                            ? 'Scan'
                                                            : 'Scans' ?>
                                                    </span>

                                                </div>

                                                <?php if (!empty($record['scan_history'])): ?>

                                                    <div class="attendance-log-scans">

                                                        <?php foreach ($record['scan_history'] as $scan): ?>

                                                            <?php
                                                            $scanType = strtolower(
                                                                (string) ($scan['scan_type'] ?? '')
                                                            );

                                                            $scanLabel = $scanType === 'entry'
                                                                ? 'Entry'
                                                                : 'Exit';

                                                            $scanClass = $scanType === 'entry'
                                                                ? 'attendance-log-entry'
                                                                : 'attendance-log-exit';

                                                            $scanStatus = trim(
                                                                (string) ($scan['scan_status'] ?? '')
                                                            );
                                                            ?>

                                                            <div class="attendance-log-scan">

                                                                <span class="attendance-log-type <?= $scanClass ?>">
                                                                    <?= htmlspecialchars(
                                                                        $scanLabel,
                                                                        ENT_QUOTES,
                                                                        'UTF-8'
                                                                    ) ?>
                                                                </span>

                                                                <span class="attendance-log-time">
                                                                    <?= htmlspecialchars(
                                                                        date(
                                                                            'h:i:s A',
                                                                            strtotime(
                                                                                (string) $scan['scan_time']
                                                                            )
                                                                        ),
                                                                        ENT_QUOTES,
                                                                        'UTF-8'
                                                                    ) ?>
                                                                </span>

                                                                <span class="attendance-log-status">
                                                                    <?= htmlspecialchars(
                                                                        $scanStatus !== ''
                                                                        ? $scanStatus
                                                                        : 'Scan recorded',
                                                                        ENT_QUOTES,
                                                                        'UTF-8'
                                                                    ) ?>
                                                                </span>

                                                            </div>

                                                        <?php endforeach; ?>

                                                    </div>

                                                <?php else: ?>

                                                    <div class="attendance-log-empty">
                                                        No RFID scans were found for this employee on this date.
                                                    </div>

                                                <?php endif; ?>

                                            </div>

                                        </td>
                                    </tr>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </section>


            <!-- Attendance Rules Notice -->
            <section class="attendance-rules-note">

                <strong>Attendance evaluation</strong>

                <p>
                    Entry and exit statuses are displayed using the recorded
                    attendance data. Attendance evaluation follows each
                    employee's assigned work schedule, including the established
                    30-minute entry grace period and 60-minute exit threshold.
                </p>

            </section>

        </div>

    </main>

    <?php

    require_once __DIR__ . '/../layouts/footer.php';

    ?>
</div>


<script>
    (() => {
        const table = document.querySelector('.attendance-management-table');

        if (!table || table.dataset.logToggleInitialized === 'true') {
            return;
        }

        table.dataset.logToggleInitialized = 'true';

        table.addEventListener('click', (event) => {
            const button = event.target.closest('.attendance-log-toggle');

            if (!button || !table.contains(button)) {
                return;
            }

            const targetId = button.getAttribute('aria-controls');
            const expandedRow = document.getElementById(targetId);

            if (!expandedRow) {
                return;
            }

            const willExpand = button.getAttribute('aria-expanded') !== 'true';

            // Collapse the other open log, if any.
            table.querySelectorAll('.attendance-log-toggle').forEach((otherButton) => {
                const otherId = otherButton.getAttribute('aria-controls');
                const otherRow = document.getElementById(otherId);

                if (otherButton !== button) {
                    otherButton.setAttribute('aria-expanded', 'false');

                    // Reset the icon to the open-eye icon.
                    const openEye = otherButton.querySelector('.eye-open-icon');
                    const closedEye = otherButton.querySelector('.eye-closed-icon');

                    if (openEye && closedEye) {
                        openEye.style.display = '';
                        closedEye.style.display = 'none';
                    }

                    // Collapse the other log.
                    if (otherRow) {
                        otherRow.hidden = true;
                    }
                }
            });

            button.setAttribute('aria-expanded', String(willExpand));
            expandedRow.hidden = !willExpand;

            // Change the eye icon based on the log's state.
            const openEye = button.querySelector('.eye-open-icon');
            const closedEye = button.querySelector('.eye-closed-icon');

            if (openEye && closedEye) {
                openEye.style.display = willExpand ? 'none' : '';
                closedEye.style.display = willExpand ? '' : 'none';
            }
        });
    })();
</script>