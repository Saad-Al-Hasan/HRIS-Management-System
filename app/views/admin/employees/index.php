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

            <div class="employee-header-actions">
                <form method="GET" action="/HRIS-Management-System/public/index.php" class="employee-search-form">
                    <input type="hidden" name="action" value="employees">

                    <input type="text" name="search" value="<?= htmlspecialchars($search ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Search employee code or username..."
                        aria-label="Search by employee code or username">

                    <button type="submit" class="btn btn-secondary">
                        Search
                    </button>
                </form>

                <a href="/HRIS-Management-System/public/index.php?action=employee-create" class="btn btn-primary">
                    + Add Employee
                </a>
            </div>
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

                <button type="button" class="btn btn-secondary" onclick="document.getElementById('employee-filter-panel').hidden =
            !document.getElementById('employee-filter-panel').hidden;">
                    Filter Employees
                </button>
            </div>



            <form id="employee-filter-panel" method="GET" action="/HRIS-Management-System/public/index.php" hidden
                style="padding: 16px; border-bottom: 1px solid #e5e7eb;">
                <input type="hidden" name="action" value="employees">

                <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: end;">

                    <div style="flex: 1; min-width: 180px;">
                        <label for="filter-department">Department</label>
                        <select id="filter-department" name="department" style="width: 100%; padding: 10px;">
                            <option value="">All Departments</option>
                            <?php foreach (($departments ?? []) as $department): ?>
                                <option value="<?= htmlspecialchars($department) ?>" <?= (($filters['department'] ?? '') === $department) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($department) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="flex: 1; min-width: 180px;">
                        <label for="filter-designation">Designation</label>
                        <select id="filter-designation" name="designation" style="width: 100%; padding: 10px;">
                            <option value="">All Designations</option>
                            <?php foreach (($designations ?? []) as $designation): ?>
                                <option value="<?= htmlspecialchars($designation) ?>" <?= (($filters['designation'] ?? '') === $designation) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($designation) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="flex: 1; min-width: 150px;">
                        <label for="filter-status">Status</label>
                        <select id="filter-status" name="status" style="width: 100%; padding: 10px;">
                            <option value="">All Statuses</option>
                            <option value="active" <?= (($filters['status'] ?? '') === 'active') ? 'selected' : '' ?>>
                                Active
                            </option>
                            <option value="inactive" <?= (($filters['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>
                                Inactive
                            </option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Apply Filters
                    </button>

                    <a href="/HRIS-Management-System/public/index.php?action=employees" class="btn btn-secondary">
                        Reset
                    </a>

                </div>
            </form>



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


                                            <input type="hidden" name="search"
                                                value="<?= htmlspecialchars($search ?? '', ENT_QUOTES, 'UTF-8') ?>">

                                            <input type="hidden" name="department"
                                                value="<?= htmlspecialchars($filters['department'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

                                            <input type="hidden" name="designation"
                                                value="<?= htmlspecialchars($filters['designation'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

                                            <input type="hidden" name="status"
                                                value="<?= htmlspecialchars($filters['status'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

                                            <input type="hidden" name="employee_id"
                                                value="<?= (int) $employee['employee_id'] ?>">

                                            <input type="hidden" name="new_status"
                                                value="<?= ($employee['status'] ?? '') === 'active' ? 'inactive' : 'active' ?>">

                                            <button type="submit" class="btn btn-secondary employee-status-btn">
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