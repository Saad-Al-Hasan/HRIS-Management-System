<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../core/Auth.php';

Auth::startSession();

$pageTitle = 'RFID Management';

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

?>

<div class="admin-main">

    <?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

    <main class="admin-content">

        <div class="content-header">
            <div>
                <h1>RFID Management</h1>
                <p>Manage employee RFID card assignments.</p>
            </div>

            <a href="/HRIS-Management-System/public/index.php?action=rfid-assign" class="btn btn-primary">
                + Assign RFID Card
            </a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error" role="alert">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success" role="status">
                <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <section class="content-card">

            <div class="card-header">
                <div>
                    <h2>Registered RFID Cards</h2>
                    <p>
                        Employees who currently have an RFID card assigned.
                    </p>
                </div>
            </div>

            <?php if (empty($registeredCards)): ?>

                <div class="empty-state">
                    <h3>No RFID cards registered yet</h3>
                    <p>
                        Assign an RFID card to an employee to see the
                        assignment listed here.
                    </p>
                </div>

            <?php else: ?>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Employee Code</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>RFID Identifier</th>
                                <th>Account Status</th>
                                <th>Assigned At</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($registeredCards as $card): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars(
                                            $card['employee_code'] ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $card['username'] ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $card['full_name'] ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $card['email'] ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td>
                                        <code><?= htmlspecialchars(
                                            $card['rfid_identifier'] ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?></code>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            ucfirst($card['status'] ?? ''),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $card['created_at'] ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td>
                                        <form method="POST"
                                            action="/HRIS-Management-System/public/index.php?action=rfid-unassign"
                                            onsubmit="return confirm('Unassign the RFID card from employee <?= htmlspecialchars($card['employee_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>? The card will become available for reuse.');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(
                                                Auth::csrfToken(),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>">

                                            <input type="hidden" name="card_id" value="<?= (int) $card['id'] ?>">

                                            <button type="submit" class="btn btn-danger">
                                                Unassign Card
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>

        </section>

    </main>

    <?php require_once __DIR__ . '/../layouts/footer.php'; ?>

</div>