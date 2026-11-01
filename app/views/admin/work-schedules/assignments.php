<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/Auth.php';

Auth::startSession();

$pageTitle = 'Employee Schedule Assignments';

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>

<div class="admin-main employee-schedule-assignments-page">

    <?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

    <main class="admin-content">

        <div class="content-header">
            <div>
                <h1>Employee Schedule Assignments</h1>
                <p>Assign or update the work schedule for each employee.</p>
            </div>

            <a href="/HRIS-Management-System/public/index.php?action=work-schedules" class="btn btn-secondary">
                Back to Schedules
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

            <div class="card-header">
                <div>
                    <h2>Employee Schedules</h2>
                    <p>
                        Employees without an explicit assignment use the default
                        9 AM - 6 PM schedule in the attendance logic.
                    </p>
                </div>
            </div>

            <?php if (empty($employees)): ?>

                <div class="empty-state">
                    <h3>No employees found</h3>
                    <p>Active employee records will appear here when available.</p>
                </div>

            <?php else: ?>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Employee Code</th>
                                <th>Full Name</th>
                                <th>Department</th>
                                <th>Current Schedule</th>
                                <th>Assign / Update Schedule</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($employees as $employee): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars(
                                            (string) ($employee['employee_code'] ?? ''),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            (string) ($employee['full_name'] ?? ''),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            (string) ($employee['department'] ?? '—'),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?php if (!empty($employee['schedule_name'])): ?>
                                            <?= htmlspecialchars(
                                                (string) $employee['schedule_name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        <?php else: ?>
                                            <span>Default: 9 AM - 6 PM</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <form method="POST"
                                            action="/HRIS-Management-System/public/index.php?action=work-schedule-assign">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(
                                                Auth::csrfToken(),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>">

                                            <input type="hidden" name="employee_id"
                                                value="<?= (int) ($employee['employee_id'] ?? 0) ?>">

                                            <div class="form-group">
                                                <select name="schedule_id" aria-label="Schedule for <?= htmlspecialchars(
                                                    (string) ($employee['full_name'] ?? 'employee'),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>" required>
                                                    <option value="">-- Select schedule --</option>

                                                    <?php foreach ($schedules as $schedule): ?>
                                                        <option value="<?= (int) ($schedule['id'] ?? 0) ?>"
                                                            <?= (isset($employee['schedule_id'])
                                                                && (int) $employee['schedule_id'] === (int) ($schedule['id'] ?? 0))
                                                                ? 'selected'
                                                                : '' ?>>
                                                            <?= htmlspecialchars(
                                                                (string) ($schedule['name'] ?? ''),
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>
                                                            (<?= htmlspecialchars(
                                                                date(
                                                                    'h:i A',
                                                                    strtotime((string) ($schedule['expected_entry_time'] ?? '00:00:00'))
                                                                ),
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?> - <?= htmlspecialchars(
                                                                  date(
                                                                      'h:i A',
                                                                      strtotime((string) ($schedule['expected_exit_time'] ?? '00:00:00'))
                                                                  ),
                                                                  ENT_QUOTES,
                                                                  'UTF-8'
                                                              ) ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <button type="submit" class="btn btn-primary">
                                                Save Assignment
                                            </button>
                                        </form>
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