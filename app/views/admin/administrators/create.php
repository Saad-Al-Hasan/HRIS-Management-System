<?php

require_once __DIR__ . '/../../../core/Auth.php';

$pageTitle = 'Add Administrator';

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

?>

<div class="admin-main">

    <?php require_once __DIR__ . '/../layouts/navbar.php'; ?>


    <main class="admin-content">

        <!-- Page Header -->

        <div class="content-header">

            <div>

                <h2>Add Administrator</h2>

                <p>
                    Create a new administrator account for the HRIS.
                </p>

            </div>

        </div>


        <!-- Form Card -->

        <section class="content-card">

            <div class="card-header">

                <div>

                    <h3>Administrator Information</h3>

                    <p>
                        Enter the required information for the new administrator.
                    </p>

                </div>

            </div>


            <div class="form-container">

                <?php if (!empty($error)): ?>

                    <div class="alert alert-error">
                        <?= htmlspecialchars($error) ?>
                    </div>

                <?php endif; ?>


                <form
                    method="POST"
                    action="/HRIS-Management-System/public/index.php?action=administrator-store"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(Auth::csrfToken()) ?>"
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
                            value="<?= htmlspecialchars(
                                $old['full_name'] ?? ''
                            ) ?>"
                            placeholder="Enter full name"
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
                            value="<?= htmlspecialchars(
                                $old['username'] ?? ''
                            ) ?>"
                            placeholder="Enter username"
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
                            value="<?= htmlspecialchars(
                                $old['email'] ?? ''
                            ) ?>"
                            placeholder="Enter email address"
                            required
                        >

                    </div>


                    <!-- Password -->

                    <div class="form-group">

                        <label for="password">
                            Password
                        </label>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Minimum 8 characters"
                            minlength="8"
                            required
                        >

                    </div>


                    <!-- Confirm Password -->

                    <div class="form-group">

                        <label for="confirm_password">
                            Confirm Password
                        </label>

                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            placeholder="Re-enter password"
                            minlength="8"
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
                        >

                            <option value="active">
                                Active
                            </option>

                            <option value="inactive">
                                Inactive
                            </option>

                        </select>

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
                            Create Administrator
                        </button>

                    </div>

                </form>

            </div>

        </section>

    </main>


    <?php require_once __DIR__ . '/../layouts/footer.php'; ?>

</div>