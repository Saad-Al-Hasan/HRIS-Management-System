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

            <form method="POST" action="/HRIS-Management-System/public/index.php?action=profile-update">

                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">

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

</div>


<?php

require_once __DIR__ . '/../layouts/footer.php';

?>