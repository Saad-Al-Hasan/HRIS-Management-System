<?php
require_once __DIR__ . '/../../../core/Auth.php';

Auth::startSession();

$pageTitle = 'Employee Management';

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>

<div class="admin-main">

    <?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

    <main class="admin-content">

        <div class="content-header">
            <div>
                <h1>Employee Management</h1>
                <p>Manage registered employees at MY Outsourcing Ltd.</p>
            </div>

            <a href="/HRIS-Management-System/public/index.php?action=employee-create" class="btn btn-primary">
                + Add Employee
            </a>
        </div>

        <div class="content-card">

            <div class="card-header">
                <div>
                    <h2>All Employees</h2>
                    <p>
                        Total employees:
                        <?= count($employees ?? []) ?>
                    </p>
                </div>
            </div>

            <div class="table-responsive">

                <table class="data-table">

                    <thead>
                        <tr>
                            <th>Employee Code</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Department</th>
                            <th>Designation</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (empty($employees)): ?>

                            <tr>
                                <td colspan="7" class="empty-state">
                                    No employees have been registered yet.
                                </td>
                            </tr>

                        <?php else: ?>

                            <?php foreach ($employees as $employee): ?>

                                <tr>
                                    <td>
                                        <?= htmlspecialchars($employee['employee_code'] ?? '') ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($employee['full_name'] ?? '') ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($employee['username'] ?? '') ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($employee['department'] ?? '') ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($employee['designation'] ?? '') ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(ucfirst($employee['status'] ?? 'unknown')) ?>
                                    </td>

                                    <td>
                                        <a href="/HRIS-Management-System/public/index.php?action=employee-view&id=<?= (int) $employee['employee_id'] ?>"
                                            class="btn btn-secondary">
                                            View Details
                                        </a>

                                        <a href="/HRIS-Management-System/public/index.php?action=employee-edit&id=<?= (int) $employee['employee_id'] ?>"
                                            class="btn btn-primary">
                                            Edit
                                        </a>

                                        <form action="/HRIS-Management-System/public/index.php?action=employee-toggle-status"
                                            method="POST" style="display: inline;"
                                            onsubmit="return confirm('Are you sure you want to change this employee’s account status?');">
                                            <input type="hidden" name="csrf_token"
                                                value="<?= htmlspecialchars(Auth::csrfToken()) ?>">

                                            <input type="hidden" name="employee_id"
                                                value="<?= (int) $employee['employee_id'] ?>">

                                            <input type="hidden" name="new_status"
                                                value="<?= ($employee['status'] ?? '') === 'active' ? 'inactive' : 'active' ?>">

                                            <button type="submit" class="btn btn-secondary">
                                                <?= ($employee['status'] ?? '') === 'active' ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </main>

    <?php require_once __DIR__ . '/../layouts/footer.php'; ?>
</div>