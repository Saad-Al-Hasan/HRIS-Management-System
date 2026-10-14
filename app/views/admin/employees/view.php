<?php
require_once __DIR__ . '/../../../core/Auth.php';

Auth::startSession();

$pageTitle = 'Employee Details';

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>

<div class="admin-main">

    <?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

    <main class="admin-content">

        <div class="content-header">
            <div>
                <h1>Employee Details</h1>
                <p>View the registered employee information.</p>
            </div>

            <a href="/HRIS-Management-System/public/index.php?action=employees" class="btn btn-secondary">
                Back to Employees
            </a>
        </div>

        <div class="content-card">

            <div class="card-header">
                <div>
                    <h2>
                        <?= htmlspecialchars($employee['full_name']) ?>
                    </h2>
                    <p>
                        Employee Code:
                        <?= htmlspecialchars($employee['employee_code']) ?>
                    </p>
                </div>
            </div>

            <div class="employee-details-grid">

                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" value="<?= htmlspecialchars($employee['full_name']) ?>" readonly>
                </div>

                <div class="form-group">
                    <label>Employee Code</label>
                    <input type="text" value="<?= htmlspecialchars($employee['employee_code']) ?>" readonly>
                </div>

                <div class="form-group">
                    <label>Username</label>
                    <input type="text" value="<?= htmlspecialchars($employee['username']) ?>" readonly>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" value="<?= htmlspecialchars($employee['email']) ?>" readonly>
                </div>

                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" value="<?= htmlspecialchars($employee['phone'] ?? '') ?>" readonly>
                </div>

                <div class="form-group">
                    <label>Department</label>
                    <input type="text" value="<?= htmlspecialchars($employee['department']) ?>" readonly>
                </div>

                <div class="form-group">
                    <label>Designation</label>
                    <input type="text" value="<?= htmlspecialchars($employee['designation']) ?>" readonly>
                </div>

                <div class="form-group">
                    <label>Joining Date</label>
                    <input type="text" value="<?= htmlspecialchars($employee['joining_date']) ?>" readonly>
                </div>

                <div class="form-group">
                    <label>Account Status</label>
                    <input type="text" value="<?= htmlspecialchars(ucfirst($employee['status'])) ?>" readonly>
                </div>

                <div class="form-group">
                    <label>Address</label>
                    <textarea readonly rows="3"><?= htmlspecialchars($employee['address'] ?? '') ?></textarea>
                </div>

            </div>

        </div>

    </main>



    <?php require_once __DIR__ . '/../layouts/footer.php'; ?>
</div>