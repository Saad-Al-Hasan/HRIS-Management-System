<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/Auth.php';

Auth::startSession();

$pageTitle = 'Leave Application';

$old = $old ?? [];
$leaveRequests = $leaveRequests ?? [];
$employee = $employee ?? null;

$oldDate = (string) ($old['leave_date'] ?? '');
$oldType = (string) ($old['leave_type'] ?? '');
$oldReason = (string) ($old['reason'] ?? '');

require_once __DIR__ . '/../../admin/layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>

<div class="admin-main leave-page">

    <?php require_once __DIR__ . '/../../admin/layouts/navbar.php'; ?>

    <main class="admin-content">

        <div class="content-header">
            <div>
                <h1>Leave Application</h1>
                <p>Apply for leave and track the status of your requests.</p>
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

        <?php if ($employee !== null): ?>

            <!-- Leave application form -->
            <section class="content-card leave-form-card">

                <div class="card-header">
                    <div>
                        <h3>Apply for Leave</h3>
                        <p>Select the date, leave type and give a reason.</p>
                    </div>
                </div>

                <form method="POST"
                    action="/HRIS-Management-System/public/index.php?action=leave-store"
                    class="leave-form">

                    <input type="hidden" name="csrf_token"
                        value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="leave-form-grid">

                        <div class="form-group">
                            <label for="leave_date">Leave Date</label>
                            <input type="date" id="leave_date" name="leave_date"
                                min="<?= date('Y-m-d') ?>"
                                value="<?= htmlspecialchars($oldDate, ENT_QUOTES, 'UTF-8') ?>"
                                required>
                        </div>

                        <div class="form-group">
                            <label for="leave_type">Leave Type</label>
                            <select id="leave_type" name="leave_type" required>
                                <option value="">Select leave type</option>
                                <option value="full_day" <?= $oldType === 'full_day' ? 'selected' : '' ?>>
                                    Full Day
                                </option>
                                <option value="half_day" <?= $oldType === 'half_day' ? 'selected' : '' ?>>
                                    Half Day
                                </option>
                            </select>
                        </div>

                    </div>

                    <div class="form-group">
                        <label for="reason">Reason for Leave</label>
                        <textarea id="reason" name="reason" rows="4" maxlength="500"
                            placeholder="Write the reason for your leave..."
                            required><?= htmlspecialchars($oldReason, ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div class="leave-form-actions">
                        <button type="submit" class="btn btn-primary">
                            Submit Leave Request
                        </button>
                    </div>

                </form>

            </section>

        <?php endif; ?>

        <!-- My leave requests -->
        <section class="content-card">

            <div class="card-header">
                <div>
                    <h3>My Leave Requests</h3>
                    <p>All the leave requests you have submitted.</p>
                </div>
            </div>

            <?php if (empty($leaveRequests)): ?>

                <div class="empty-state">
                    <h3>No leave requests yet</h3>
                    <p>Your submitted leave requests will appear here.</p>
                </div>

            <?php else: ?>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Leave Date</th>
                                <th>Leave Type</th>
                                <th>Reason</th>
                                <th>Applied On</th>
                                <th>Status</th>
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
                                        <?= htmlspecialchars(
                                            date('d M Y', strtotime((string) $request['created_at'])),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>
                                    <td>
                                        <span class="leave-status-badge leave-status-<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>

        </section>

    </main>

    <?php require_once __DIR__ . '/../../admin/layouts/footer.php'; ?>

</div>
