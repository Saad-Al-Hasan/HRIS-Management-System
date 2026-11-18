<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/Auth.php';

Auth::startSession();

$pageTitle = 'Leave Management';

$leaveRequests = $leaveRequests ?? [];

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>

<div class="admin-main leave-page">

    <?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

    <main class="admin-content">

        <div class="content-header">
            <div>
                <h1>Leave Management</h1>
                <p>Review and respond to employee leave requests.</p>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error" role="alert">
                <?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success" role="status">
                <?= htmlspecialchars((string) $success, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <section class="content-card">

            <div class="card-header leave-card-header">
                <div>
                    <h3>Employee Leave Requests</h3>
                    <p>Pending requests are shown first.</p>
                </div>
            </div>

            <?php if (empty($leaveRequests)): ?>

                <div class="empty-state">
                    <h3>No leave requests found</h3>
                    <p>Employee leave requests will appear here.</p>
                </div>

            <?php else: ?>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Leave Date</th>
                                <th>Leave Type</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($leaveRequests as $request): ?>
                                <?php
                                $status = (string) ($request['status'] ?? 'pending');
                                $statusLabel = match ($status) {
                                    'approved' => 'Accepted',
                                    'rejected' => 'Rejected',
                                    default => 'Pending',
                                };
                                $typeLabel = ($request['leave_type'] ?? '') === 'half_day'
                                    ? 'Half Day'
                                    : 'Full Day';
                                ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <?= htmlspecialchars((string) $request['employee_name'], ENT_QUOTES, 'UTF-8') ?>
                                        </strong>
                                        <br>
                                        <small>
                                            <?= htmlspecialchars((string) $request['employee_code'], ENT_QUOTES, 'UTF-8') ?>
                                        </small>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            date('d M Y', strtotime((string) $request['leave_date'])),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td><?= htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') ?></td>

                                    <td class="leave-reason-cell">
                                        <?= nl2br(htmlspecialchars((string) ($request['reason'] ?? ''), ENT_QUOTES, 'UTF-8')) ?>
                                    </td>

                                    <td>
                                        <span class="leave-status-badge leave-status-<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php if ($status === 'pending'): ?>
                                            <form method="POST"
                                                action="/HRIS-Management-System/public/index.php?action=leave-update-status"
                                                class="leave-action-form">

                                                <input type="hidden" name="csrf_token"
                                                    value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="leave_id"
                                                    value="<?= (int) $request['id'] ?>">

                                                <button type="submit" name="decision" value="accept"
                                                    class="leave-action-btn leave-action-accept"
                                                    onclick="return confirm('Accept this leave request?');">
                                                    Accept
                                                </button>

                                                <button type="submit" name="decision" value="reject"
                                                    class="leave-action-btn leave-action-reject"
                                                    onclick="return confirm('Reject this leave request?');">
                                                    Reject
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <small class="leave-reviewed-by">
                                                <?= !empty($request['reviewer_name'])
                                                    ? 'By ' . htmlspecialchars((string) $request['reviewer_name'], ENT_QUOTES, 'UTF-8')
                                                    : '—' ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>

        </section>

    </main>

    <?php require_once __DIR__ . '/../layouts/footer.php'; ?>

</div>
