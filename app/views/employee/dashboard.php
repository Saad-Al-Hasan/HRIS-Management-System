<?php

require_once __DIR__ . '/../../core/Auth.php';

Auth::startSession();

$pageTitle = 'Employee Dashboard';

$employee = $employee ?? [];

$employeeCode = $employee['employee_code'] ?? 'Not assigned';
$rfidIdentifier = $rfidIdentifier ?? 'Not assigned';
$assignedSchedule = $assignedSchedule ?? null;
$scheduleName = $assignedSchedule['name'] ?? 'Not available';
$department = $employee['department'] ?? 'Not assigned';
$designation = $employee['designation'] ?? 'Not assigned';

$joiningDate = 'Not available';

if (!empty($employee['joining_date'])) {
    $timestamp = strtotime($employee['joining_date']);

    if ($timestamp !== false) {
        $joiningDate = date('d M Y', $timestamp);
    }
}

require_once __DIR__ . '/../admin/layouts/header.php';
require_once __DIR__ . '/layouts/sidebar.php';

?>

<div class="admin-main">

    <?php require_once __DIR__ . '/../admin/layouts/navbar.php'; ?>

    <main class="admin-content">

        <div class="content-header">
            <div>
                <h1>
                    Welcome back,
                    <?= htmlspecialchars(Auth::fullName() ?? 'Employee') ?>!
                </h1>

                <p>
                    Welcome to your MYOL HRIS employee dashboard.
                </p>
            </div>
        </div>

        <!-- Employee Identification Cards -->
        <div class="employee-dashboard-stats">

            <!-- Employee ID -->
            <div class="employee-dashboard-stat-card">
                <div class="employee-dashboard-stat-icon">#</div>

                <div class="employee-dashboard-stat-content">
                    <span class="employee-dashboard-stat-label">
                        Employee ID
                    </span>

                    <strong class="employee-dashboard-stat-value">
                        <?= htmlspecialchars($employeeCode) ?>
                    </strong>

                    <small>Your unique employee code</small>
                </div>
            </div>

            <!-- RFID Identifier -->
            <div class="employee-dashboard-stat-card">
                <div class="employee-dashboard-stat-icon">▣</div>

                <div class="employee-dashboard-stat-content">
                    <span class="employee-dashboard-stat-label">
                        RFID Identifier
                    </span>

                    <strong class="employee-dashboard-stat-value">
                        <?= htmlspecialchars($rfidIdentifier) ?>
                    </strong>

                    <small>Your assigned RFID card number</small>
                </div>
            </div>


            <!-- Assigned Schedule -->
            <div class="employee-dashboard-stat-card">
                <div class="employee-dashboard-stat-icon">◷</div>

                <div class="employee-dashboard-stat-content">
                    <span class="employee-dashboard-stat-label">
                        Assigned Schedule
                    </span>

                    <strong class="employee-dashboard-stat-value">
                        <?= htmlspecialchars($scheduleName, ENT_QUOTES, 'UTF-8') ?>
                    </strong>

                    <small>Your assigned working shift</small>
                </div>
            </div>


        </div>

        <!-- Employee Details -->
        <div class="employee-dashboard-stats">

            <!-- Department -->
            <div class="employee-dashboard-stat-card">
                <div class="employee-dashboard-stat-icon">⌂</div>

                <div class="employee-dashboard-stat-content">
                    <span class="employee-dashboard-stat-label">
                        Department
                    </span>

                    <strong class="employee-dashboard-stat-value">
                        <?= htmlspecialchars($department) ?>
                    </strong>

                    <small>Your current department</small>
                </div>
            </div>

            <!-- Designation -->
            <div class="employee-dashboard-stat-card">
                <div class="employee-dashboard-stat-icon">★</div>

                <div class="employee-dashboard-stat-content">
                    <span class="employee-dashboard-stat-label">
                        Designation
                    </span>

                    <strong class="employee-dashboard-stat-value">
                        <?= htmlspecialchars($designation) ?>
                    </strong>

                    <small>Your current position</small>
                </div>
            </div>

            <!-- Joining Date -->
            <div class="employee-dashboard-stat-card">
                <div class="employee-dashboard-stat-icon">◷</div>

                <div class="employee-dashboard-stat-content">
                    <span class="employee-dashboard-stat-label">
                        Joining Date
                    </span>

                    <strong class="employee-dashboard-stat-value">
                        <?= htmlspecialchars($joiningDate) ?>
                    </strong>

                    <small>Your date of joining</small>
                </div>
            </div>

        </div>


        <!-- Employee Attendance History - US-06 -->
        <div class="content-card employee-attendance-card">

            <div class="employee-attendance-header">
                <div class="employee-attendance-heading">
                    <h2>Attendance History</h2>
                    <p>Review your previous entry and exit records.</p>
                </div>

                <form method="GET" action="/HRIS-Management-System/public/index.php" class="employee-attendance-filter">
                    <input type="hidden" name="action" value="employee-dashboard">

                    <div class="employee-attendance-filter-field">
                        <label for="attendance_from">From Date</label>
                        <input type="date" id="attendance_from" name="attendance_from" value="<?= htmlspecialchars(
                            $attendanceFromDate ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>">
                    </div>

                    <div class="employee-attendance-filter-field">
                        <label for="attendance_to">To Date</label>
                        <input type="date" id="attendance_to" name="attendance_to" value="<?= htmlspecialchars(
                            $attendanceToDate ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>">
                    </div>

                    <button type="submit" class="employee-attendance-view-button">
                        View Attendance
                    </button>

                    <a href="/HRIS-Management-System/public/index.php?action=employee-dashboard"
                        class="employee-attendance-reset-button">
                        Reset
                    </a>
                </form>
            </div>

            <?php if (!empty($attendanceError)): ?>

                <div class="employee-attendance-message employee-attendance-error" role="alert">
                    <?= htmlspecialchars(
                        $attendanceError,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>

            <?php else: ?>

                <div class="employee-attendance-table-wrapper">
                    <table class="employee-attendance-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Entry Time</th>
                                <th>Entry Status</th>
                                <th>Exit Time</th>
                                <th>Exit Status</th>
                                <th>Attendance Status</th>
                                <th>View Log</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (!empty($attendanceRecords)): ?>

                                <?php foreach ($attendanceRecords as $record): ?>
                                    <?php
                                    $entryStatus = (string) ($record['entry_status'] ?? '');
                                    $exitStatus = (string) ($record['exit_status'] ?? '');
                                    $attendanceStatus = (string) ($record['attendance_status'] ?? '');

                                    $entryStatusClass = match (strtolower($entryStatus)) {
                                        'entry - in time' => 'status-present',
                                        'entry - late' => 'status-late',
                                        default => 'status-unknown',
                                    };

                                    $exitStatusClass = match (strtolower($exitStatus)) {
                                        'exit - in time' => 'status-present',
                                        'exit - before' => 'status-late',
                                        default => 'status-unknown',
                                    };

                                    $attendanceStatusClass = match (strtolower($attendanceStatus)) {
                                        'present' => 'status-present',
                                        'late' => 'status-late',
                                        'absent' => 'status-absent',
                                        'leave' => 'status-leave',
                                        'holiday' => 'status-holiday',
                                        default => 'status-unknown',
                                    };

                                    $recordDate = !empty($record['attendance_date'])
                                        ? date('d M Y', strtotime($record['attendance_date']))
                                        : '—';

                                    $entryTime = !empty($record['entry_time'])
                                        ? date('h:i:s A', strtotime($record['entry_time']))
                                        : '—';

                                    $exitTime = !empty($record['exit_time'])
                                        ? date('h:i:s A', strtotime($record['exit_time']))
                                        : '—';
                                    ?>

                                    <tr>
                                        <td class="employee-attendance-date">
                                            <?= htmlspecialchars($recordDate, ENT_QUOTES, 'UTF-8') ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($entryTime, ENT_QUOTES, 'UTF-8') ?>
                                        </td>

                                        <td>
                                            <?php if ($entryStatus !== ''): ?>
                                                <span class="employee-attendance-badge <?= $entryStatusClass ?>">
                                                    <?= htmlspecialchars($entryStatus, ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="employee-attendance-not-recorded">—</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($exitTime, ENT_QUOTES, 'UTF-8') ?>
                                        </td>

                                        <td>
                                            <?php if ($exitStatus !== ''): ?>
                                                <span class="employee-attendance-badge <?= $exitStatusClass ?>">
                                                    <?= htmlspecialchars($exitStatus, ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="employee-attendance-not-recorded">Not recorded</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?php if ($attendanceStatus !== ''): ?>
                                                <span class="employee-attendance-badge <?= $attendanceStatusClass ?>">
                                                    <?= htmlspecialchars($attendanceStatus, ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="employee-attendance-not-recorded">—</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- View Log -->

                                        <td class="employee-attendance-log-action">
                                            <button type="button" class="employee-attendance-log-toggle" aria-expanded="false"
                                                aria-controls="employee-scan-history-<?= (int) $record['attendance_id'] ?>"
                                                onclick="toggleEmployeeScanHistory(this)" title="View RFID scan history">

                                                <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24"
                                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                    stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z" />
                                                    <circle cx="12" cy="12" r="3" />
                                                </svg>

                                            </button>

                                        </td>
                                    </tr>

                                    <!-- Expandable RFID Scan History -->
                                    <tr id="employee-scan-history-<?= (int) $record['attendance_id'] ?>"
                                        class="employee-scan-history-row" hidden>
                                        <td colspan="7">
                                            <div class="employee-scan-history-panel">

                                                <div class="employee-scan-history-header">
                                                    <div>
                                                        <h3>RFID Scan History</h3>
                                                        <p>
                                                            <?= htmlspecialchars(
                                                                (string) ($employee['full_name'] ?? Auth::fullName() ?? 'Employee'),
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>
                                                            ·
                                                            <?= htmlspecialchars(
                                                                (string) ($employee['employee_code'] ?? ''),
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>
                                                            ·
                                                            <?= htmlspecialchars($recordDate, ENT_QUOTES, 'UTF-8') ?>
                                                        </p>
                                                    </div>

                                                    <span class="employee-scan-history-count">
                                                        <?= count($record['scan_history'] ?? []) ?>
                                                        <?= count($record['scan_history'] ?? []) === 1 ? 'Scan' : 'Scans' ?>
                                                    </span>
                                                </div>

                                                <?php if (!empty($record['scan_history'])): ?>

                                                    <div class="employee-scan-history-list">

                                                        <?php foreach ($record['scan_history'] as $scan): ?>

                                                            <?php
                                                            $scanType = strtolower(
                                                                (string) ($scan['scan_type'] ?? '')
                                                            );

                                                            $scanStatus = (string) (
                                                                $scan['scan_status'] ?? 'Unknown'
                                                            );

                                                            $scanTime = !empty($scan['scan_time'])
                                                                ? date('h:i:s A', strtotime($scan['scan_time']))
                                                                : '—';
                                                            ?>

                                                            <div class="employee-scan-history-item">

                                                                <span
                                                                    class="employee-scan-type <?= $scanType === 'entry' ? 'scan-entry' : 'scan-exit' ?>">
                                                                    <?= htmlspecialchars(
                                                                        ucfirst($scanType),
                                                                        ENT_QUOTES,
                                                                        'UTF-8'
                                                                    ) ?>
                                                                </span>

                                                                <strong class="employee-scan-time">
                                                                    <?= htmlspecialchars(
                                                                        $scanTime,
                                                                        ENT_QUOTES,
                                                                        'UTF-8'
                                                                    ) ?>
                                                                </strong>

                                                                <span class="employee-scan-status">
                                                                    <?= htmlspecialchars(
                                                                        $scanStatus,
                                                                        ENT_QUOTES,
                                                                        'UTF-8'
                                                                    ) ?>
                                                                </span>

                                                            </div>

                                                        <?php endforeach; ?>

                                                    </div>

                                                <?php else: ?>

                                                    <p class="employee-scan-history-empty">
                                                        No RFID scan history is available for this date.
                                                    </p>

                                                <?php endif; ?>

                                            </div>
                                        </td>
                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>
                                    <td colspan="7" class="employee-attendance-no-records">
                                        <div class="employee-attendance-empty-icon">
                                            <span>◷</span>
                                        </div>

                                        <strong>No attendance records found</strong>

                                        <p>
                                            No attendance records match the selected date range.
                                        </p>
                                    </td>
                                </tr>

                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>

            <div class="employee-attendance-footer">
                Your attendance history displays records associated with your employee account.
            </div>

        </div>

    </main>

    <?php require_once __DIR__ . '/../admin/layouts/footer.php'; ?>

</div>