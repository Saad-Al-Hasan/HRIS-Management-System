<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/Auth.php';

Auth::startSession();

$pageTitle = 'Holiday Management';

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>

<div class="admin-main holiday-management-page">

    <?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

    <main class="admin-content">

        <div class="content-header">
            <div>
                <h1>Holiday Management</h1>
                <p>Manage government and organizational holidays.</p>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error" role="alert">
                <?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success" role="status">
                <?= htmlspecialchars((string) $success, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <!-- Add Holiday -->
        <section class="content-card holiday-form-card">

            <div class="card-header">
                <div>
                    <h2>Add New Holiday</h2>
                    <p>Add a holiday that applies organization-wide.</p>
                </div>
            </div>

            <form method="POST" action="/HRIS-Management-System/public/index.php?action=holiday-store">

                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(
                    (string) ($csrfToken ?? Auth::csrfToken()),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>">

                <div class="form-group">
                    <label for="holiday_name">Holiday Name</label>
                    <input type="text" id="holiday_name" name="holiday_name" maxlength="100"
                        placeholder="e.g. Independence Day" required>
                </div>

                <div class="form-group">
                    <label for="holiday_date">Holiday Date</label>
                    <input type="date" id="holiday_date" name="holiday_date" required>
                </div>

                <div class="form-group">
                    <label for="holiday_type">Holiday Type</label>
                    <select id="holiday_type" name="holiday_type" required>
                        <option value="">Select holiday type</option>
                        <option value="Government">Government</option>
                        <option value="Organizational">Organizational</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="description">Description (Optional)</label>
                    <textarea id="description" name="description" rows="3"
                        placeholder="Enter additional information..."></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        Add Holiday
                    </button>
                </div>

            </form>

        </section>


        <!-- Existing Holidays -->
        <section class="content-card holiday-list-card">

            <div class="card-header holiday-list-header">

                <div>
                    <h2>Organization Holidays</h2>
                    <p>View and manage all configured holidays.</p>
                </div>

                <div class="holiday-search-wrapper">

                    <form method="GET" action="/HRIS-Management-System/public/index.php" class="holiday-search-form">

                        <input type="hidden" name="action" value="holidays">

                        <input type="date" name="holiday_date" class="holiday-search-date" value="<?= htmlspecialchars(
                            (string) ($filterDate ?? ''),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>" required>

                        <button type="submit" class="btn btn-primary holiday-search-button">
                            Search Holiday
                        </button>

                        <a href="/HRIS-Management-System/public/index.php?action=holidays"
                            class="btn btn-secondary holiday-reset-button">
                            Reset
                        </a>

                    </form>

                </div>

            </div>

            <?php if (empty($holidays)): ?>

                <div class="empty-state">
                    <h3>No holidays found</h3>
                    <p>
                        <?= !empty($filterDate)
                            ? 'No holiday is configured for the selected date.'
                            : 'Add a holiday to display it here.' ?>
                    </p>
                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="data-table">

                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Holiday Name</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($holidays as $holiday): ?>

                                <tr>

                                    <td>
                                        <?= (int) ($holiday['id'] ?? 0) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            (string) ($holiday['holiday_name'] ?? ''),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?php
                                        $holidayDate = (string) ($holiday['holiday_date'] ?? '');
                                        $dateObject = DateTime::createFromFormat('!Y-m-d', $holidayDate);
                                        ?>

                                        <?= $dateObject
                                            ? htmlspecialchars($dateObject->format('d M Y'), ENT_QUOTES, 'UTF-8')
                                            : '—' ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            (string) ($holiday['holiday_type'] ?? ''),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            (string) ($holiday['description'] ?? '—'),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td>
                                        <form method="POST"
                                            action="/HRIS-Management-System/public/index.php?action=holiday-delete"
                                            onsubmit="return confirm('Are you sure you want to delete this holiday?');">

                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(
                                                (string) $csrfToken,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>">

                                            <input type="hidden" name="id" value="<?= (int) ($holiday['id'] ?? 0) ?>">

                                            <button type="submit" class="btn btn-danger">
                                                Delete
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