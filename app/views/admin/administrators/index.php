<?php

require_once __DIR__ . '/../../../core/Auth.php';

$pageTitle = 'Administrator Accounts';

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

?>

<div class="admin-main">

    <!-- Top Navigation -->
    <?php require_once __DIR__ . '/../layouts/navbar.php'; ?>


    <!-- Page Content -->
    <main class="admin-content">

        <!-- Page Header -->
        <div class="content-header">

            <div>
                <h2>Administrator Accounts</h2>

                <p>
                    View and manage administrator accounts registered in the HRIS.
                </p>
            </div>

            <a
                href="/HRIS-Management-System/public/index.php?action=administrator-create"
                class="btn btn-primary"
            >
                + Add Administrator
            </a>

        </div>


        <!-- Success Message -->
        <?php if (isset($_GET['success'])): ?>

            <div class="alert alert-success">

                <?php if ($_GET['success'] === 'created'): ?>

                    Administrator account created successfully.

                <?php elseif ($_GET['success'] === 'updated'): ?>

                    Administrator account updated successfully.

                <?php endif; ?>

            </div>

        <?php endif; ?>


        <!-- Administrator Table -->
        <section class="content-card">

            <div class="card-header">

                <div>
                    <h3>Administrator List</h3>

                    <p>
                        <?= count($admins) ?> administrator account(s)
                    </p>
                </div>

            </div>


            <div class="table-wrapper">

                <table class="admin-table">

                    <thead>

                        <tr>
                            <th>#</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (empty($admins)): ?>

                            <tr>

                                <td
                                    colspan="7"
                                    class="empty-state"
                                >
                                    No administrator accounts found.
                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($admins as $index => $admin): ?>

                                <tr>

                                    <td>
                                        <?= $index + 1 ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= htmlspecialchars($admin['full_name']) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($admin['username']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($admin['email']) ?>
                                    </td>

                                    <td>

                                        <?php if ($admin['status'] === 'active'): ?>

                                            <span class="status-badge status-active">
                                                Active
                                            </span>

                                        <?php else: ?>

                                            <span class="status-badge status-inactive">
                                                Inactive
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            date(
                                                'd M Y',
                                                strtotime($admin['created_at'])
                                            )
                                        ) ?>
                                    </td>

                                    <td>

                                        <a
                                            href="/HRIS-Management-System/public/index.php?action=administrator-edit&id=<?= (int) $admin['id'] ?>"
                                            class="table-action"
                                        >
                                            Edit
                                        </a>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>


<?php

require_once __DIR__ . '/../layouts/footer.php';

?>