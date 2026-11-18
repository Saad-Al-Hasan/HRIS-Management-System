<?php
require_once __DIR__ . '/../../../core/Auth.php';

Auth::startSession();

$leaveRequests = $leaveRequests ?? [];
?>

<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

<div class="admin-main leave-management-page">

    <?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

    <main class="admin-content">

        <div class="content-header">
            <div>
                <h1>Leave Management</h1>
                <p>Review and manage employee leave applications.</p>
            </div>
        </div>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <section class="content-card leave-requests-card">

            <div class="card-header leave-list-header">

                <div>
                    <h2>Employee Leave Requests</h2>
                </div>

                <div class="leave-search-wrapper">

                    <form method="GET" action="/HRIS-Management-System/public/index.php" class="leave-search-form">

                        <input type="hidden" name="action" value="leave-management">

                        <input type="text" name="employee_code" class="leave-search-input"
                            placeholder="Enter Employee Code (e.g. EMP-0003)" value="<?= htmlspecialchars(
                                (string) ($employeeCode ?? ''),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>" maxlength="30" required>

                        <button type="submit" class="btn btn-primary leave-search-button">
                            Search
                        </button>

                        <a href="/HRIS-Management-System/public/index.php?action=leave-management"
                            class="btn btn-secondary leave-reset-button">
                            Reset
                        </a>

                    </form>

                </div>

            </div>

            <div class="table-responsive">

                <table class="data-table">

                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Employee Code</th>
                            <th>Employee Name</th>
                            <th>Leave Date</th>
                            <th>Leave Type</th>
                            <th>Period</th>
                            <th>Reason</th>
                            <th>Applied On</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (empty($leaveRequests)): ?>

                            <tr>
                                <td colspan="10" class="empty-state">
                                    No leave applications found.
                                </td>
                            </tr>

                        <?php else: ?>

                            <?php foreach ($leaveRequests as $index => $request): ?>

                                <?php
                                $status = strtolower($request['status']);

                                $statusLabel = match ($status) {
                                    'approved' => 'Accepted',
                                    'rejected' => 'Rejected',
                                    default => 'Pending'
                                };
                                ?>

                                <tr>

                                    <td>
                                        <?= $index + 1 ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($request['employee_code'] ?? '—') ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($request['full_name'] ?? '—') ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            date('d M Y', strtotime($request['start_date']))
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($request['leave_type']) ?>
                                    </td>

                                    <td>
                                        <?php if ($request['leave_type'] === 'Half Day'): ?>
                                            <?= ($request['half_day_period'] ?? '') === 'first_half'
                                                ? 'First Half'
                                                : 'Second Half' ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($request['reason'] ?? '—') ?>
                                    </td>

                                    <td>
                                        <?= !empty($request['created_at'])
                                            ? htmlspecialchars(date(
                                                'd M Y',
                                                strtotime($request['created_at'])
                                            ))
                                            : '—' ?>
                                    </td>

                                    <td>
                                        <span class="status-badge status-<?= htmlspecialchars($status) ?>">
                                            <?= htmlspecialchars($statusLabel) ?>
                                        </span>
                                    </td>

                                    <td>

                                        <?php if ($status === 'pending'): ?>

                                            <div class="leave-review-actions">

                                                <form method="POST"
                                                    action="/HRIS-Management-System/public/index.php?action=leave-review"
                                                    onsubmit="return confirm('Accept this leave request?');">
                                                    <input type="hidden" name="csrf_token"
                                                        value="<?= htmlspecialchars($csrfToken) ?>">

                                                    <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">

                                                    <input type="hidden" name="status" value="approved">

                                                    <button type="submit" class="btn btn-primary">
                                                        Accept
                                                    </button>
                                                </form>

                                                <form method="POST"
                                                    action="/HRIS-Management-System/public/index.php?action=leave-review"
                                                    onsubmit="return confirm('Reject this leave request?');">
                                                    <input type="hidden" name="csrf_token"
                                                        value="<?= htmlspecialchars($csrfToken) ?>">

                                                    <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">

                                                    <input type="hidden" name="status" value="rejected">

                                                    <button type="submit" class="btn btn-danger">
                                                        Reject
                                                    </button>
                                                </form>

                                            </div>

                                        <?php else: ?>

                                            <span class="leave-reviewed-badge">Reviewed</span>

                                        <?php endif; ?>

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