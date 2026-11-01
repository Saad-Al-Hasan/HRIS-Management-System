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

            <a href="/HRIS-Management-System/public/index.php?action=employee-dashboard" class="btn btn-secondary">
                Back to Dashboard
            </a>
        </div>

        <?php if (!empty($_SESSION['employee_profile_picture_success'])): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($_SESSION['employee_profile_picture_success']) ?>
            </div>
            <?php unset($_SESSION['employee_profile_picture_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['employee_profile_picture_error'])): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($_SESSION['employee_profile_picture_error']) ?>
            </div>
            <?php unset($_SESSION['employee_profile_picture_error']); ?>
        <?php endif; ?>

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


                <?php
                $employeeUserId = Auth::userId();

                $profilePictureDirectory = dirname(__DIR__, 3)
                    . '/public/uploads/profile_pictures';

                $profilePictureUrl =
                    '/HRIS-Management-System/public/assets/images/employee-avatar.png';

                if ($employeeUserId !== null) {
                    foreach (['png', 'jpg'] as $extension) {
                        $fileName = 'userid_' . $employeeUserId . '.' . $extension;
                        $filePath = $profilePictureDirectory . '/' . $fileName;

                        if (is_file($filePath)) {
                            $profilePictureUrl =
                                '/HRIS-Management-System/public/uploads/profile_pictures/'
                                . $fileName;
                            break;
                        }
                    }
                }
                ?>

                <!-- Employee Profile Picture -->
                <div style="text-align: center; margin: 10px 0 28px;">

                    <img src="<?= htmlspecialchars($profilePictureUrl) ?>" alt="Employee Profile Picture" style="
            width: 130px;
            height: 130px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid var(--border);
            display: block;
            margin: 0 auto 15px;
        ">

                    <form id="employee-picture-form" method="POST"
                        action="/HRIS-Management-System/public/index.php?action=employee-profile-picture-update"
                        enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">

                        <label for="employee_profile_picture" style="
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                padding: 12px 22px;
                border: 2px dashed var(--myol-red-light);
                border-radius: 10px;
                background: var(--bg-card);
                color: var(--text-primary);
                cursor: pointer;
                min-width: 260px;
                max-width: 380px;
            ">
                            <span style="color: var(--myol-red-light); font-size: 20px;">↑</span>
                            <span id="employee-picture-filename">
                                Choose a Profile Picture
                            </span>
                        </label>

                        <input type="file" id="employee_profile_picture" name="profile_picture"
                            accept=".png,.jpg,.jpeg,image/png,image/jpeg" required style="display: none;">

                        <div style="margin-top: 8px; color: var(--text-muted); font-size: 11px;">
                            PNG, JPG or JPEG · Maximum 5 MB
                        </div>

                        <div id="employee-picture-status" style="margin-top: 8px; font-size: 12px;" aria-live="polite">
                        </div>
                    </form>
                </div>



                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        const input = document.getElementById('employee_profile_picture');
                        const form = document.getElementById('employee-picture-form');
                        const filename = document.getElementById('employee-picture-filename');
                        const status = document.getElementById('employee-picture-status');
                        const updateButton = document.getElementById('employee-profile-update');

                        if (!input || !form || !filename || !status || !updateButton) {
                            console.error('Employee profile picture controls were not found.');
                            return;
                        }

                        input.addEventListener('change', function () {
                            const file = input.files && input.files[0];
                            if (!file) return;

                            filename.textContent = file.name;

                            if (!['image/png', 'image/jpeg'].includes(file.type)) {
                                status.textContent = 'Please select a PNG, JPG, or JPEG image.';
                                input.value = '';
                                filename.textContent = 'Choose a Profile Picture';
                                return;
                            }

                            if (file.size > 5 * 1024 * 1024) {
                                status.textContent = 'The image must be 5 MB or smaller.';
                                input.value = '';
                                filename.textContent = 'Choose a Profile Picture';
                                return;
                            }

                            status.textContent = 'Picture selected. Click Update Profile to upload.';
                        });

                        updateButton.addEventListener('click', function () {
                            if (!input.files || !input.files.length) {
                                status.textContent = 'Please choose a profile picture first.';
                                return;
                            }

                            if (!input.checkValidity()) {
                                status.textContent = 'Please select a valid picture.';
                                return;
                            }

                            status.textContent = 'Uploading profile picture...';
                            form.requestSubmit();
                        });
                    });
                </script>




                <div class="employee-details-grid">

                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" value="<?= htmlspecialchars($employee['full_name'] ?? '') ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label>Employee Code</label>
                        <input type="text" value="<?= htmlspecialchars($employee['employee_code'] ?? '') ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" value="<?= htmlspecialchars($employee['username'] ?? '') ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label>RFID Identifier</label>
                        <input type="text" value="<?= htmlspecialchars($rfidIdentifier ?? 'Not assigned') ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="text" value="<?= htmlspecialchars($employee['email'] ?? '') ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" value="<?= htmlspecialchars($employee['phone'] ?? '') ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label>Address</label>
                        <textarea readonly rows="3"><?= htmlspecialchars($employee['address'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" value="<?= htmlspecialchars($employee['department'] ?? '') ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label>Designation</label>
                        <input type="text" value="<?= htmlspecialchars($employee['designation'] ?? '') ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label>Joining Date</label>
                        <input type="text" value="<?= htmlspecialchars($employee['joining_date'] ?? '') ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label>Account Status</label>
                        <input type="text" value="<?= htmlspecialchars(ucfirst($employee['status'] ?? '')) ?>" readonly>
                    </div>


                </div>

                <div class="form-actions" style="display:flex; justify-content:flex-start; gap:10px; margin-top:24px;">
                    <a href="/HRIS-Management-System/public/index.php?action=employee-dashboard" class="btn btn-secondary">
                        Cancel
                    </a>

                    <button type="button" id="employee-profile-update" class="btn btn-primary">
                        Update Profile
                    </button>
                </div>

            </div>

        <?php endif; ?>


    </main>



    <?php

    require_once __DIR__ . '/../admin/layouts/footer.php';

    ?>

</div>