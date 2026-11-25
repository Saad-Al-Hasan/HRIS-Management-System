<?php
/**
 * Reports > Leave Report tab (US-11).
 * Included by reports/index.php, which provides $e, $leaveFilters,
 * $leaveRows and $leaveSummary.
 */

$leaveFilters = $leaveFilters ?? [
    'employee_code' => '',
    'status' => '',
    'leave_type' => '',
    'from' => '',
    'to' => '',
];
$leaveRows = $leaveRows ?? [];
$leaveSummary = $leaveSummary ?? [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'approved_days' => 0,
];

require_once __DIR__ . '/../../../models/LeaveReport.php';

$exportQuery = http_build_query(array_filter([
    'action' => 'report-leave-export',
    'employee_code' => $leaveFilters['employee_code'],
    'status' => $leaveFilters['status'],
    'leave_type' => $leaveFilters['leave_type'],
    'from' => $leaveFilters['from'],
    'to' => $leaveFilters['to'],
], static fn($v) => $v !== ''));

$approvedDays = rtrim(
    rtrim(number_format((float) $leaveSummary['approved_days'], 1, '.', ''), '0'),
    '.'
);
?>

<section class="content-card report-card">

    <div class="card-header report-card-header">
        <div>
            <h2>Leave Report</h2>
            <p>Employee leave applications, approval statuses and leave history.</p>
        </div>

        <a href="/HRIS-Management-System/public/index.php?<?= $e($exportQuery) ?>"
            class="btn btn-primary report-export-button">
            Export to Excel
        </a>
    </div>

    <!-- FILTERS -->
    <form method="GET" action="/HRIS-Management-System/public/index.php" class="report-filters">

        <input type="hidden" name="action" value="reports">
        <input type="hidden" name="tab" value="leave">

        <div class="report-filter">
            <label for="leave-employee-code">Employee Code</label>
            <input type="text" id="leave-employee-code" name="employee_code"
                placeholder="e.g. EMP-0003" maxlength="30"
                value="<?= $e($leaveFilters['employee_code']) ?>">
        </div>

        <div class="report-filter">
            <label for="leave-status">Status</label>
            <select id="leave-status" name="status">
                <option value="">All</option>
                <?php foreach (LeaveReport::STATUSES as $status): ?>
                    <option value="<?= $e($status) ?>"
                        <?= $leaveFilters['status'] === $status ? 'selected' : '' ?>>
                        <?= $e(LeaveReport::statusLabel($status)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="report-filter">
            <label for="leave-type">Leave Type</label>
            <select id="leave-type" name="leave_type">
                <option value="">All</option>
                <?php foreach (LeaveReport::LEAVE_TYPES as $type): ?>
                    <option value="<?= $e($type) ?>"
                        <?= $leaveFilters['leave_type'] === $type ? 'selected' : '' ?>>
                        <?= $e($type) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="report-filter">
            <label for="leave-from">From</label>
            <input type="date" id="leave-from" name="from"
                value="<?= $e($leaveFilters['from']) ?>">
        </div>

        <div class="report-filter">
            <label for="leave-to">To</label>
            <input type="date" id="leave-to" name="to"
                value="<?= $e($leaveFilters['to']) ?>">
        </div>

        <div class="report-filter-actions">
            <button type="submit" class="btn btn-primary">Generate</button>
            <a href="/HRIS-Management-System/public/index.php?action=reports&amp;tab=leave"
                class="btn btn-secondary">Reset</a>
        </div>

    </form>

    <!-- SUMMARY -->
    <div class="report-summary">
        <div class="report-stat">
            <span>Total Applications</span>
            <strong><?= (int) $leaveSummary['total'] ?></strong>
        </div>
        <div class="report-stat">
            <span>Pending</span>
            <strong><?= (int) $leaveSummary['pending'] ?></strong>
        </div>
        <div class="report-stat">
            <span>Accepted</span>
            <strong><?= (int) $leaveSummary['approved'] ?></strong>
        </div>
        <div class="report-stat">
            <span>Rejected</span>
            <strong><?= (int) $leaveSummary['rejected'] ?></strong>
        </div>
        <div class="report-stat">
            <span>Accepted Leave Days</span>
            <strong><?= $e($approvedDays) ?></strong>
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
                    <th>Leave Type</th>
                    <th>Period</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Days</th>
                    <th>Reason</th>
                    <th>Applied On</th>
                    <th>Status</th>
                    <th>Reviewed By</th>
                </tr>
            </thead>

            <tbody>
                <?php if (empty($leaveRows)): ?>
                    <tr>
                        <td colspan="12" class="empty-state">
                            No leave records found for the selected filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($leaveRows as $index => $row): ?>
                        <?php $status = strtolower((string) $row['status']); ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= $e($row['employee_code']) ?></td>
                            <td><?= $e($row['full_name']) ?></td>
                            <td><?= $e($row['leave_type']) ?></td>
                            <td><?= $e(LeaveReport::periodLabel($row)) ?></td>
                            <td><?= $e(date('d M Y', strtotime((string) $row['start_date']))) ?></td>
                            <td><?= $e(date('d M Y', strtotime((string) $row['end_date']))) ?></td>
                            <td><?= $e(rtrim(rtrim(number_format((float) $row['leave_days'], 1, '.', ''), '0'), '.')) ?></td>
                            <td class="report-reason"><?= $e($row['reason'] !== null && $row['reason'] !== '' ? $row['reason'] : '—') ?></td>
                            <td>
                                <?= !empty($row['created_at'])
                                    ? $e(date('d M Y', strtotime((string) $row['created_at'])))
                                    : '—' ?>
                            </td>
                            <td>
                                <span class="report-badge report-badge-<?= $e($status) ?>">
                                    <?= $e(LeaveReport::statusLabel($status)) ?>
                                </span>
                            </td>
                            <td><?= $e($row['reviewed_by_name'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>

        </table>
    </div>

</section>
