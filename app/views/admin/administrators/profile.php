<?php

require_once __DIR__ . '/../../../core/Auth.php';

Auth::startSession();

$pageTitle = 'My Profile';

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

?>

<div class="admin-main">

    <?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

    <main class="admin-content">

        <div class="content-header">

            <div>
                <h1>My Profile</h1>
                <p>
                    View and manage your administrator profile information.
                </p>
            </div>

        </div>

        <?php if (
            (!empty($success)) ||
            (isset($_GET['success']) && $_GET['success'] === 'updated')
        ): ?>

            <div class="alert alert-success">
                Profile updated successfully.
            </div>

        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="content-card form-container">

            <div class="card-header">

                <div>
                    <h2>Profile Information</h2>
                    <p>
                        Update your personal administrator information below.
                    </p>
                </div>

            </div>

            <form method="POST" action="/HRIS-Management-System/public/index.php?action=profile-update"
                enctype="multipart/form-data">

                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">

                <?php
                $adminUserId = Auth::userId();

                $profilePictureDirectory = dirname(__DIR__, 4)
                    . '/public/uploads/profile_pictures';

                $profilePictureUrl = '/HRIS-Management-System/public/assets/images/admin-avatar.png';

                if ($adminUserId !== null) {
                    foreach (['png', 'jpg'] as $extension) {
                        $fileName = 'userid_' . $adminUserId . '.' . $extension;

                        if (is_file($profilePictureDirectory . '/' . $fileName)) {
                            $profilePictureUrl =
                                '/HRIS-Management-System/public/uploads/profile_pictures/'
                                . $fileName;
                            break;
                        }
                    }
                }
                ?>

                <!-- Admin Profile Picture -->
                <div style="text-align: center; margin: 10px 0 28px;">
                    <img src="<?= htmlspecialchars($profilePictureUrl) ?>" alt="Admin Profile Picture" style="
            width: 130px;
            height: 130px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid var(--border);
            display: block;
            margin: 0 auto 15px;
        ">

                    <label for="profile_picture" style="
        display: inline-block;
        margin-bottom: 12px;
        font-weight: 700;
        font-size: 14px;
        color: var(--text-primary);
    ">
                        Choose a Profile Picture
                    </label>

                    <div style="margin-bottom: 12px; text-align: center;">

                        <label for="profile_picture" style="
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
                            <span id="profile-picture-filename">Choose a Profile Picture</span>
                        </label>

                        <input type="file" id="profile_picture" name="profile_picture"
                            accept=".png,.jpg,.jpeg,image/png,image/jpeg" style="display: none;">

                        <div style="margin-top: 8px; color: var(--text-muted); font-size: 11px;">
                            PNG, JPG or JPEG · Maximum 5 MB
                        </div>

                    </div>

                    <script>
                        document.getElementById('profile_picture')?.addEventListener('change', function () {
                            const filename = this.files?.[0]?.name || 'Choose a Profile Picture';
                            document.getElementById('profile-picture-filename').textContent = filename;
                        });
                    </script>

                    <!-- Close the centered profile-picture section -->
                </div>

                <div class="form-group">

                    <label for="full_name">
                        Full Name
                    </label>

                    <input type="text" id="full_name" name="full_name"
                        value="<?= htmlspecialchars($admin['full_name'] ?? '') ?>" required>

                </div>


                <div class="form-group">

                    <label for="username">
                        Username
                    </label>

                    <input type="text" id="username" value="<?= htmlspecialchars($admin['username'] ?? '') ?>" disabled>

                    <small>
                        Username cannot be changed from the profile page.
                    </small>

                </div>


                <div class="form-group">

                    <label for="email">
                        Email Address
                    </label>

                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($admin['email'] ?? '') ?>"
                        required>

                </div>


                <div class="form-group">

                    <label for="password">
                        New Password
                    </label>

                    <input type="password" id="password" name="password"
                        placeholder="Leave blank to keep current password">

                    <small>
                        Leave blank if you do not want to change your password.
                    </small>

                </div>


                <div class="form-group">

                    <label for="confirm_password">
                        Confirm New Password
                    </label>

                    <input type="password" id="confirm_password" name="confirm_password"
                        placeholder="Re-enter new password">

                </div>


                <div class="form-actions">

                    <a href="/HRIS-Management-System/public/index.php?action=dashboard" class="btn btn-secondary">
                        Cancel
                    </a>

                    <button type="submit" class="btn btn-primary">
                        Update Profile
                    </button>

                </div>

            </form>

        </div>

    </main>

    <?php
    require_once __DIR__ . '/../layouts/footer.php';
    ?>

</div>