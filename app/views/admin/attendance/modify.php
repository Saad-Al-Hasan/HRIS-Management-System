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
                        Edit an employee's attendance times without changing original RFID records.
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

                    <!-- Employee Code Input with Dropdown Suggestions -->

                    <div class="attendance-filter-field">
                        <label for="employee_code_input">
                            Select Employee
                        </label>

                        <input type="text" id="employee_code_input" list="employee_code_options"
                            placeholder="Type or select an employee code" autocomplete="off" required>

                        <small class="field-hint">
                            Enter an employee code (e.g., EMP-0001) or select an employee from the list.
                        </small>

                        <!-- Employee suggestions -->
                        <datalist id="employee_code_options">
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?= htmlspecialchars(
                                    (string) $employee['employee_code'] . ' - ' . (string) $employee['username'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>" data-employee-id="<?= (int) $employee['employee_id'] ?>"></option>
                            <?php endforeach; ?>
                        </datalist>

                        <!-- Preserve the numeric employee ID expected by the controller -->
                        <input type="hidden" id="employee_id" name="employee_id" value="">

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

                        <small class="field-hint">
                            Select the date for which you want to record or modify attendance.
                        </small>
                    </div>

                    <!-- Admin Edited Entry Time -->
                    <div class="attendance-filter-field">

                        <label for="A_E_entry_time">
                            Admin Edited Entry Time
                        </label>

                        <input type="time" id="A_E_entry_time" name="A_E_entry_time" step="1">

                        <small>
                            Leave blank to preserve the previously saved admin-edited entry time.
                        </small>

                    </div>

                    <!-- Admin Edited Exit Time -->
                    <div class="attendance-filter-field">

                        <label for="A_E_exit_time">
                            Admin Edited Exit Time
                        </label>

                        <input type="time" id="A_E_exit_time" name="A_E_exit_time" step="1">

                        <small>
                            Leave blank to preserve the previously saved admin-edited exit time.
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
                    Select an employee and attendance date. Enter the admin-edited
                    entry time, exit time, or both. Empty fields preserve previously
                    saved admin-edited values. Attendance statuses are calculated
                    automatically according to the employee's assigned work schedule.
                </p>

            </section>

        </div>

    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const employeeCodeInput = document.getElementById('employee_code_input');
            const employeeIdInput = document.getElementById('employee_id');
            const employeeOptions = document.querySelectorAll(
                '#employee_code_options option'
            );

            const attendanceForm = employeeCodeInput.closest('form');

            function resolveEmployee() {
                const enteredCode = employeeCodeInput.value
                    .trim()
                    .split(' - ')[0]
                    .trim()
                    .toUpperCase();

                employeeIdInput.value = '';

                if (enteredCode === '') {
                    return false;
                }

                for (const option of employeeOptions) {
                    const optionEmployeeCode = option.value
                        .trim()
                        .split(' - ')[0]
                        .trim()
                        .toUpperCase();

                    if (optionEmployeeCode === enteredCode) {
                        employeeIdInput.value = option.dataset.employeeId;
                        return true;
                    }
                }

                return false;
            }

            employeeCodeInput.addEventListener('input', resolveEmployee);
            employeeCodeInput.addEventListener('change', resolveEmployee);

            attendanceForm.addEventListener('submit', function (event) {
                if (!resolveEmployee()) {
                    event.preventDefault();

                    employeeCodeInput.setCustomValidity(
                        'Please enter a valid employee code or select an employee from the suggestions.'
                    );

                    employeeCodeInput.reportValidity();
                } else {
                    employeeCodeInput.setCustomValidity('');
                }
            });

            employeeCodeInput.addEventListener('input', function () {
                employeeCodeInput.setCustomValidity('');
            });
        });
    </script>

    <?php require_once __DIR__ . '/../layouts/footer.php'; ?>

</div>