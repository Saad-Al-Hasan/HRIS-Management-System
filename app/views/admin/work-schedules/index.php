<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/Auth.php';

Auth::startSession();

$pageTitle = 'Work Schedule Management';

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>

<div class="admin-main work-schedule-page">

    <?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

    <main class="admin-content">

        <div class="content-header">
            <div>
                <h1>Work Schedule Management</h1>
                <p>Manage work shifts and employee schedule assignments.</p>
            </div>

            <a href="/HRIS-Management-System/public/index.php?action=work-schedules-create" class="btn btn-primary">
                + Create Schedule
            </a>
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


            <div class="card-header work-schedule-card-header">
                <div>
                    <h2>Available Work Schedules</h2>
                    <p>View and edit the configured work shifts.</p>
                </div>

                <div class="work-schedule-header-actions">
                    <form method="POST"
                        action="/HRIS-Management-System/public/index.php?action=work-schedules-assign-all"
                        class="bulk-shift-form"
                        onsubmit="return confirm('Are you sure you want to change ALL employees to the selected shift? This will replace their current shift assignments.');">
                        <input type="hidden" name="csrf_token"
                            value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                        <label class="sr-only" for="bulk-schedule-id">
                            Select shift for all employees
                        </label>

                        <select id="bulk-schedule-id" name="schedule_id" class="bulk-shift-select" required>
                            <option value="">Change all employees' schedules...</option>

                            <?php foreach ($schedules as $bulkSchedule): ?>
                                <option value="<?= (int) $bulkSchedule['id'] ?>">
                                    <?= htmlspecialchars(
                                        (string) $bulkSchedule['name'] . ' (' .
                                        date('h:i A', strtotime((string) $bulkSchedule['expected_entry_time'])) .
                                        ' - ' .
                                        date('h:i A', strtotime((string) $bulkSchedule['expected_exit_time'])) .
                                        ')',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <button type="submit" class="btn bulk-shift-confirm">
                            Confirm
                        </button>
                    </form>

                    <a href="/HRIS-Management-System/public/index.php?action=work-schedule-assignments"
                        class="btn btn-secondary">
                        Manage Employee Assignments
                    </a>
                </div>
            </div>


            <?php if (empty($schedules)): ?>

                <div class="empty-state">
                    <h3>No work schedules found</h3>
                    <p>Create a work schedule to get started.</p>
                </div>

            <?php else: ?>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Schedule Name</th>
                                <th>Expected Entry</th>
                                <th>Expected Exit</th>
                                <th>Working Hours</th>
                                <th class="employee-count-cell">Total Employee Assigned</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($schedules as $schedule): ?>
                                <tr>
                                    <td>
                                        <?= (int) ($schedule['id'] ?? 0) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            (string) ($schedule['name'] ?? ''),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            date(
                                                'h:i A',
                                                strtotime((string) ($schedule['expected_entry_time'] ?? '00:00:00'))
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            date(
                                                'h:i A',
                                                strtotime((string) ($schedule['expected_exit_time'] ?? '00:00:00'))
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            (string) ($schedule['working_hours'] ?? '—'),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                        hours
                                    </td>


                                    <td class="employee-count-cell">
                                        <?php $assignedCount = (int) ($schedule['total_employee_assigned'] ?? 0); ?>

                                        <span
                                            class="employee-count-badge <?= $assignedCount === 0 ? 'employee-count-zero' : 'employee-count-active' ?>">
                                            <?= $assignedCount ?>
                                        </span>
                                    </td>


                                    <td>
                                        <?php
                                        $scheduleId = (int) ($schedule['id'] ?? 0);
                                        $isDefaultSchedule = ($scheduleId === 1);
                                        ?>

                                        <div class="schedule-actions">
                                            <?php if ($isDefaultSchedule): ?>
                                                <button type="button" class="btn btn-secondary" disabled
                                                    title="The default schedule cannot be edited.">
                                                    Edit
                                                </button>
                                            <?php else: ?>
                                                <a class="btn btn-secondary"
                                                    href="/HRIS-Management-System/public/index.php?action=work-schedules-edit&amp;id=<?= $scheduleId ?>">
                                                    Edit
                                                </a>

                                                <form method="POST"
                                                    action="/HRIS-Management-System/public/index.php?action=work-schedules-delete"
                                                    onsubmit="return confirm('Are you sure you want to delete this work schedule?');">
                                                    <input type="hidden" name="csrf_token"
                                                        value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                                                    <input type="hidden" name="id" value="<?= $scheduleId ?>">

                                                    <button type="submit" class="btn btn-danger">
                                                        Delete
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
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