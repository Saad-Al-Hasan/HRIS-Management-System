<?php
require_once __DIR__ . '/../../../core/Auth.php';

Auth::startSession();

$pageTitle = 'Edit Employee';

$errors = $errors ?? [];
$old = $old ?? [];

$value = static function (string $field) use ($old, $employee): string {
    return htmlspecialchars(
        (string) ($old[$field] ?? $employee[$field] ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
};

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>

<div class="admin-main">

    <?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

    <main class="admin-content">

        <div class="content-header">
            <div>
                <h1>Edit Employee</h1>
                <p>Update the employee's account and company information.</p>
            </div>

            <a href="/HRIS-Management-System/public/index.php?action=employees" class="btn btn-secondary">
                Back to Employees
            </a>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($errors['general']) ?>
            </div>
        <?php endif; ?>

        <form action="/HRIS-Management-System/public/index.php?action=employee-update" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">

            <input type="hidden" name="employee_id" value="<?= (int) $employee['employee_id'] ?>">
            <div class="employee-create-grid">

                <div class="content-card">

                    <div class="card-header">
                        <div>
                            <h2>Account Information</h2>
                            <p>Update the employee's login and contact details.</p>
                        </div>
                    </div>

                    <div class="form-grid">

                        <div class="form-group">
                            <label for="full_name">Full Name *</label>
                            <input type="text" id="full_name" name="full_name" value="<?= $value('full_name') ?>"
                                maxlength="100" required>
                        </div>

                        <div class="form-group">
                            <label for="username">Username *</label>
                            <input type="text" id="username" name="username" value="<?= $value('username') ?>"
                                maxlength="50" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" value="<?= $value('email') ?>" maxlength="150"
                                required>
                        </div>

                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" minlength="8"
                                autocomplete="new-password">
                            <?php if (isset($errors['new_password'])): ?>
                                <small class="field-error">
                                    <?= htmlspecialchars($errors['new_password']) ?>
                                </small>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="confirm_new_password">Confirm New Password</label>
                            <input type="password" id="confirm_new_password" name="confirm_new_password" minlength="8"
                                autocomplete="new-password">
                            <?php if (isset($errors['confirm_new_password'])): ?>
                                <small class="field-error">
                                    <?= htmlspecialchars($errors['confirm_new_password']) ?>
                                </small>
                            <?php endif; ?>
                        </div>

                    </div>

                    <p class="form-help">
                        Leave both password fields blank to keep the current password.
                        Enter a new password in both fields to change it.
                    </p>

                </div>

                <div class="content-card">

                    <div class="card-header">
                        <div>
                            <h2>Employee Information</h2>
                            <p>Update the employee's official company details.</p>
                        </div>
                    </div>

                    <div class="form-grid">

                        <div class="form-group">
                            <label for="employee_code">Employee Code *</label>
                            <input type="text" id="employee_code" name="employee_code"
                                value="<?= $value('employee_code') ?>" maxlength="50" readonly required>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" value="<?= $value('phone') ?>" maxlength="30">
                        </div>

                        <div class="form-group">
                            <label for="department">Department *</label>

                            <select id="department" name="department" required>
                                <option value="">Select Department</option>

                                <?php foreach ([
                                    'Owner',
                                    'HR',
                                    'Admin',
                                    'IT',
                                    'Business',
                                    'Employees'
                                ] as $department): ?>
                                    <option value="<?= htmlspecialchars($department) ?>" <?= ($old['department'] ?? $employee['department'] ?? '') === $department ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($department) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="designation">Designation *</label>

                            <select id="designation" name="designation" required>
                                <option value="">Select Designation</option>

                                <?php foreach ([
                                    'CEO',
                                    'MD',
                                    'Business',
                                    'Business Head',
                                    'HR',
                                    'HR Head',
                                    'CTO',
                                    'Network Engineer',
                                    'System Engineer',
                                    'Developer',
                                    'Manager',
                                    'S. Manager',
                                    'Agent - Nestle',
                                    'Agent - CocaCola',
                                    'Agent - Suzuki',
                                    'Agent - Square',
                                    'Agent - Incepta',
                                    'Agent - Brac',
                                    'Agent - BYD',
                                    'Agent - Hyundai',
                                    'Agent - Huawei'
                                ] as $designation): ?>
                                    <option value="<?= htmlspecialchars($designation) ?>" <?= ($old['designation'] ?? $employee['designation'] ?? '') === $designation ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($designation) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="joining_date">Joining Date *</label>
                            <input type="date" id="joining_date" name="joining_date"
                                value="<?= $value('joining_date') ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" rows="3"><?= $value('address') ?></textarea>
                        </div>

                    </div>

                </div>
            </div>
            <div class="form-actions">
                <a href="/HRIS-Management-System/public/index.php?action=employees" class="btn btn-secondary">
                    Cancel
                </a>

                <button type="submit" class="btn btn-primary">
                    Save Changes
                </button>
            </div>

        </form>

    </main>



    <?php require_once __DIR__ . '/../layouts/footer.php'; ?>

</div>