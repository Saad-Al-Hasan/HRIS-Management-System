<?php
require_once __DIR__ . '/../../../core/Auth.php';

Auth::startSession();

$pageTitle = 'Add Employee';

$errors = $errors ?? [];
$old = $old ?? [];

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>

<div class="admin-main">

    <?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

    <main class="admin-content">

        <div class="content-header">
            <div>
                <h1>Add Employee</h1>
                <p>Register a new employee at MY Outsourcing Ltd.</p>
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

        <form action="/HRIS-Management-System/public/index.php?action=employee-store" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
            <div class="employee-create-grid">
                <div class="content-card">

                    <div class="card-header">
                        <div>
                            <h2>Account Information</h2>
                            <p>These details will be used to access the HRIS.</p>
                        </div>
                    </div>

                    <div class="form-grid">

                        <div class="form-group">
                            <label for="full_name">Full Name *</label>
                            <input type="text" id="full_name" name="full_name"
                                value="<?= htmlspecialchars($old['full_name'] ?? '') ?>" maxlength="100" required>
                            <?php if (isset($errors['full_name'])): ?>
                                <small class="field-error"><?= htmlspecialchars($errors['full_name']) ?></small>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="username">Username *</label>
                            <input type="text" id="username" name="username"
                                value="<?= htmlspecialchars($old['username'] ?? '') ?>" maxlength="50" required>
                            <?php if (isset($errors['username'])): ?>
                                <small class="field-error"><?= htmlspecialchars($errors['username']) ?></small>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email"
                                value="<?= htmlspecialchars($old['email'] ?? '') ?>" maxlength="150" required>
                            <?php if (isset($errors['email'])): ?>
                                <small class="field-error"><?= htmlspecialchars($errors['email']) ?></small>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="password">Password *</label>
                            <input type="password" id="password" name="password" minlength="8"
                                autocomplete="new-password" required>
                            <?php if (isset($errors['password'])): ?>
                                <small class="field-error"><?= htmlspecialchars($errors['password']) ?></small>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm Password *</label>
                            <input type="password" id="confirm_password" name="confirm_password" minlength="8"
                                autocomplete="new-password" required>
                            <?php if (isset($errors['confirm_password'])): ?>
                                <small class="field-error"><?= htmlspecialchars($errors['confirm_password']) ?></small>
                            <?php endif; ?>
                        </div>

                    </div>

                </div>

                <div class="content-card">

                    <div class="card-header">
                        <div>
                            <h2>Employee Information</h2>
                            <p>Enter the employee's official company details.</p>
                        </div>
                    </div>

                    <div class="form-grid">

                        <div class="form-group">
                            <label for="employee_code">Employee Code *</label>
                            <input type="text" id="employee_code" name="employee_code" value="<?= htmlspecialchars(
                                $old['employee_code'] ?? $nextEmployeeCode ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>" readonly required>
                            <?php if (isset($errors['employee_code'])): ?>
                                <small class="field-error"><?= htmlspecialchars($errors['employee_code']) ?></small>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone"
                                value="<?= htmlspecialchars($old['phone'] ?? '') ?>" maxlength="30">
                        </div>

                        <div class="form-group">
                            <label for="department">Department *</label>

                            <select id="department" name="department" required>
                                <option value="">Select Department</option>

                                <?php
                                $departments = [
                                    'Owner',
                                    'HR',
                                    'Admin',
                                    'IT',
                                    'Business',
                                    'Employees'
                                ];
                                ?>

                                <?php foreach ($departments as $department): ?>
                                    <option value="<?= htmlspecialchars($department) ?>" <?= ($old['department'] ?? '') === $department ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($department) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <?php if (isset($errors['department'])): ?>
                                <small class="field-error">
                                    <?= htmlspecialchars($errors['department']) ?>
                                </small>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="designation">Designation *</label>

                            <select id="designation" name="designation" required>
                                <option value="">Select Designation</option>

                                <?php
                                $designations = [
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
                                ];
                                ?>

                                <?php foreach ($designations as $designation): ?>
                                    <option value="<?= htmlspecialchars($designation) ?>" <?= ($old['designation'] ?? '') === $designation ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($designation) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <?php if (isset($errors['designation'])): ?>
                                <small class="field-error">
                                    <?= htmlspecialchars($errors['designation']) ?>
                                </small>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="joining_date">Joining Date *</label>
                            <input type="date" id="joining_date" name="joining_date"
                                value="<?= htmlspecialchars($old['joining_date'] ?? '') ?>" required>
                            <?php if (isset($errors['joining_date'])): ?>
                                <small class="field-error"><?= htmlspecialchars($errors['joining_date']) ?></small>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address"
                                rows="3"><?= htmlspecialchars($old['address'] ?? '') ?></textarea>
                        </div>

                    </div>

                </div>
            </div>
            <div class="form-actions">
                <a href="/HRIS-Management-System/public/index.php?action=employees" class="btn btn-secondary">
                    Cancel
                </a>

                <button type="submit" class="btn btn-primary">
                    Create Employee
                </button>
            </div>

        </form>

    </main>



    <?php require_once __DIR__ . '/../layouts/footer.php'; ?>
</div>