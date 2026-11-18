<?php
require_once __DIR__ . '/../../core/Auth.php';

Auth::startSession();

$leaveRequests = $leaveRequests ?? [];
?>

<?php require_once __DIR__ . '/../admin/layouts/header.php'; ?>

<div class="admin-main">
    <?php require_once __DIR__ . '/layouts/sidebar.php'; ?>

    <main class="admin-content">
        <?php require_once __DIR__ . '/../admin/layouts/navbar.php'; ?>

        <div class="content-wrapper">
            <div class="page-header">
                <div>
                    <h1>Leave Application</h1>
                    <p>Submit a leave request and track its status.</p>
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

            <!-- Apply for Leave -->
            <section class="content-card employee-leave-form-card">
                <div class="card-header">
                    <h2>Apply for Leave</h2>
                </div>

                <form method="POST" action="/HRIS-Management-System/public/index.php?action=leave-application-store">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                    <div class="form-group">
                        <label for="leave_date">Leave Date</label>
                        <input type="date" id="leave_date" name="leave_date" required>
                    </div>

                    <div class="form-group">
                        <label for="leave_type">Leave Type</label>
                        <select id="leave_type" name="leave_type" required>
                            <option value="">Select Leave Type</option>
                            <option value="Full Day">Full Day</option>
                            <option value="Half Day">Half Day</option>
                        </select>
                    </div>

                    <div class="form-group" id="halfDayPeriodGroup" style="display: none;">
                        <label for="half_day_period">Half-Day Period</label>
                        <select id="half_day_period" name="half_day_period">
                            <option value="">Select Period</option>
                            <option value="first_half">First Half</option>
                            <option value="second_half">Second Half</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="reason">Reason for Leave</label>
                        <textarea id="reason" name="reason" rows="4" placeholder="Explain why you need leave..."
                            required></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            Submit Application
                        </button>
                    </div>
                </form>
            </section>

            <!-- My Leave Requests -->
            <section class="content-card employee-leave-list-card">
                <div class="card-header">
                    <h2>My Leave Requests</h2>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Leave Date</th>
                                <th>Leave Type</th>
                                <th>Period</th>
                                <th>Reason</th>
                                <th>Applied On</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (empty($leaveRequests)): ?>
                                <tr>
                                    <td colspan="7" class="empty-state">
                                        You have not submitted any leave applications yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($leaveRequests as $index => $request): ?>
                                    <tr>
                                        <td>
                                            <?= $index + 1 ?>
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
                                            <?php
                                            if ($request['leave_type'] === 'Half Day') {
                                                echo htmlspecialchars(
                                                    $request['half_day_period'] === 'first_half'
                                                    ? 'First Half'
                                                    : 'Second Half'
                                                );
                                            } else {
                                                echo '—';
                                            }
                                            ?>
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
                                            <?php
                                            $status = strtolower($request['status']);

                                            $statusLabel = match ($status) {
                                                'approved' => 'Accepted',
                                                'rejected' => 'Rejected',
                                                default => 'Pending'
                                            };
                                            ?>

                                            <span class="status-badge status-<?= htmlspecialchars($status) ?>">
                                                <?= htmlspecialchars($statusLabel) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>
    <?php require_once __DIR__ . '/../admin/layouts/footer.php'; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const leaveType = document.getElementById('leave_type');
        const halfDayGroup = document.getElementById('halfDayPeriodGroup');
        const halfDayPeriod = document.getElementById('half_day_period');

        function updateHalfDayField() {
            const isHalfDay = leaveType.value === 'Half Day';

            halfDayGroup.style.display = isHalfDay ? 'block' : 'none';
            halfDayPeriod.required = isHalfDay;

            if (!isHalfDay) {
                halfDayPeriod.value = '';
            }
        }

        leaveType.addEventListener('change', updateHalfDayField);
        updateHalfDayField();
    });
</script>

