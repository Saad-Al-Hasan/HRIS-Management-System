
<?php

require_once __DIR__ . '/../../core/Auth.php';

Auth::startSession();

$pageTitle = 'My Profile';

require_once __DIR__ . '/../admin/layouts/header.php';
require_once __DIR__ . '/layouts/sidebar.php';

?>

<div class="admin-main">

    <?php require_once __DIR__ . '/../admin/layouts/navbar.php'; ?>

    <main class="admin-content">

        <div class="content-header">
            <div>
                <h1>My Profile</h1>
                <p>View your registered employee information.</p>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($employee)): ?>

            <div class="content-card">

                <div class="card-header">
                    <div>
                        <h2>Personal Information</h2>
                        <p>Your registered information in the HRIS system.</p>
                    </div>
                </div>

                <div class="form-group">
                    <label>Full Name</label>
                    <input
                        type="text"
                        value="<?= htmlspecialchars($employee['full_name'] ?? '') ?>"
                        readonly
                    >
                </div>

                <div class="form-group">
                    <label>Employee Code</label>
                    <input
                        type="text"
                        value="<?= htmlspecialchars($employee['employee_code'] ?? '') ?>"
                        readonly
                    >
                </div>

                <div class="form-group">
                    <label>Username</label>
                    <input
                        type="text"
                        value="<?= htmlspecialchars($employee['username'] ?? '') ?>"
                        readonly
                    >
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input
                        type="text"
                        value="<?= htmlspecialchars($employee['email'] ?? '') ?>"
                        readonly
                    >
                </div>

                <div class="form-group">
                    <label>Phone</label>
                    <input
                        type="text"
                        value="<?= htmlspecialchars($employee['phone'] ?? '') ?>"
                        readonly
                    >
                </div>

                <div class="form-group">
                    <label>Address</label>
                    <textarea readonly rows="3"><?= htmlspecialchars($employee['address'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label>Department</label>
                    <input
                        type="text"
                        value="<?= htmlspecialchars($employee['department'] ?? '') ?>"
                        readonly
                    >
                </div>

                <div class="form-group">
                    <label>Designation</label>
                    <input
                        type="text"
                        value="<?= htmlspecialchars($employee['designation'] ?? '') ?>"
                        readonly
                    >
                </div>

                <div class="form-group">
                    <label>Joining Date</label>
                    <input
                        type="text"
                        value="<?= htmlspecialchars($employee['joining_date'] ?? '') ?>"
                        readonly
                    >
                </div>

                <div class="form-group">
                    <label>Account Status</label>
                    <input
                        type="text"
                        value="<?= htmlspecialchars(ucfirst($employee['status'] ?? '')) ?>"
                        readonly
                    >
                </div>

                <div class="form-actions">
                    <a
                        href="/HRIS-Management-System/public/index.php?action=employee-dashboard"
                        class="btn btn-secondary"
                    >
                        Back to Dashboard
                    </a>
                </div>

            </div>

        <?php endif; ?>

    </main>

</div>

<?php

require_once __DIR__ . '/../admin/layouts/footer.php';

?>
