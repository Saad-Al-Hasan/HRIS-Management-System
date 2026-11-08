<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../core/Auth.php';

Auth::startSession();

$pageTitle = 'Modify Attendance';

$employees = $employees ?? [];
$error = $error ?? null;
$success = $success ?? null;

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

?>

<div class="admin-main">

    <?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

    <main class="admin-content">

        <div class="attendance-management-page modify-attendance-page">

            <!-- Page Heading -->
            <section class="attendance-page-heading">
                <div>
                    <h1>Modify Attendance</h1>
                    <p>
                        Manually record an employee's entry and exit times.
                    </p>
                </div>

                <a href="/HRIS-Management-System/public/index.php?action=attendance-management"
                    class="attendance-reset-button">
                    Back to Attendance
                </a>
            </section>

            <!-- Feedback Messages -->
            <?php if (!empty($error)): ?>
                <div class="attendance-validation-error" role="alert">
                    <?= htmlspecialchars(
                        (string) $error,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="attendance-success-message" role="status">
                    <?= htmlspecialchars(
                        (string) $success,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>
            <?php endif; ?>

            <!-- Manual Attendance Form -->
            <section class="attendance-filter-card">

                <form method="POST" action="/HRIS-Management-System/public/index.php?action=save-modified-attendance"
                    class="attendance-filter-form">

                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(
                        (string) ($_SESSION['csrf_token'] ?? ''),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>">

                    <!-- Employee -->
                    <div class="attendance-filter-field">
                        <label for="employee_id">
                            Select Employee
                        </label>

                        <select id="employee_id" name="employee_id" required>
                            <option value="">Select Employee</option>

                            <?php foreach ($employees as $employee): ?>

                                <option value="<?= (int) $employee['employee_id'] ?>">
                                    <?= htmlspecialchars(
                                        (string) $employee['employee_code']
                                        . '_'
                                        . (string) $employee['username'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>
                    </div>

                    <!-- Attendance Date -->
                    <div class="attendance-filter-field">
                        <label for="attendance_date">
                            Attendance Date
                        </label>

                        <input type="date" id="attendance_date" name="attendance_date" required value="<?= htmlspecialchars(
                            date('Y-m-d'),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>">
                    </div>

                    <!-- Entry Time -->
                    <div class="attendance-filter-field">
                        <label for="entry_time">
                            Entry Time
                        </label>

                        <input type="time" id="entry_time" name="entry_time" step="1">
                        <small>Leave blank to record only the exit time for an existing attendance record.</small>
                    </div>

                    <!-- Exit Time -->
                    <div class="attendance-filter-field">
                        <label for="exit_time">
                            Exit Time
                        </label>

                        <input type="time" id="exit_time" name="exit_time" step="1">

                        <small>
                            Leave blank if the employee has no recorded exit.
                        </small>
                    </div>

                    <!-- Submit -->
                    <div class="attendance-filter-actions">
                        <button type="submit" class="attendance-filter-button">
                            Save Attendance
                        </button>

                        <a href="/HRIS-Management-System/public/index.php?action=modify-attendance"
                            class="attendance-reset-button">
                            Reset
                        </a>
                    </div>

                </form>

                <p class="attendance-filter-help">
                    Select an employee, date, and entry time. An exit time
                    is optional. The system will evaluate attendance against
                    the employee's assigned work schedule.
                </p>

            </section>

        </div>

    </main>

    <?php require_once __DIR__ . '/../layouts/footer.php'; ?>

</div>