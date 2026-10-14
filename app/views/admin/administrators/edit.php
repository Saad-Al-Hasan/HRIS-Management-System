<?php

require_once __DIR__ . '/../../../core/Auth.php';

Auth::startSession();

$pageTitle = 'Edit Administrator';

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

?>

<div class="admin-main">

    <?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

    <main class="admin-content">

    <div class="content-header">

        <div>
            <h1>Edit Administrator</h1>

            <p>
                Update administrator account information and credentials.
            </p>
        </div>

    </div>


    <?php if (!empty($error)): ?>

        <div class="alert alert-error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <div class="content-card form-container">

        <div class="card-header">

            <div>

                <h2>Administrator Information</h2>

                <p>
                    Update the account details below.
                </p>

            </div>

        </div>


        <form
            method="POST"
            action="/HRIS-Management-System/public/index.php?action=administrator-update"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(Auth::csrfToken()) ?>"
            >

            <input
                type="hidden"
                name="id"
                value="<?= htmlspecialchars($admin['id']) ?>"
            >


            <!-- Full Name -->

            <div class="form-group">

                <label for="full_name">
                    Full Name
                </label>

                <input
                    type="text"
                    id="full_name"
                    name="full_name"
                    value="<?= htmlspecialchars($admin['full_name'] ?? '') ?>"
                    required
                >

            </div>


            <!-- Username -->

            <div class="form-group">

                <label for="username">
                    Username
                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    value="<?= htmlspecialchars($admin['username'] ?? '') ?>"
                    required
                >

            </div>


            <!-- Email -->

            <div class="form-group">

                <label for="email">
                    Email Address
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= htmlspecialchars($admin['email'] ?? '') ?>"
                    required
                >

            </div>


            <!-- Status -->

            <div class="form-group">

                <label for="status">
                    Account Status
                </label>

                <select
                    id="status"
                    name="status"
                    required
                >

                    <option
                        value="active"
                        <?= ($admin['status'] ?? '') === 'active' ? 'selected' : '' ?>
                    >
                        Active
                    </option>

                    <option
                        value="inactive"
                        <?= ($admin['status'] ?? '') === 'inactive' ? 'selected' : '' ?>
                    >
                        Inactive
                    </option>

                </select>

            </div>


            <!-- Password -->

            <div class="form-group">

                <label for="password">
                    New Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Leave blank to keep current password"
                >

                <small>
                    Leave blank if you do not want to change the password.
                </small>

            </div>


            <!-- Confirm Password -->

            <div class="form-group">

                <label for="confirm_password">
                    Confirm New Password
                </label>

                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    placeholder="Re-enter new password"
                >

            </div>


            <!-- Actions -->

            <div class="form-actions">

                <a
                    href="/HRIS-Management-System/public/index.php?action=administrators"
                    class="btn btn-secondary"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Update Administrator
                </button>

            </div>

        </form>

    </div>

    </main>

</div>


<?php

require_once __DIR__ . '/../layouts/footer.php';

?>