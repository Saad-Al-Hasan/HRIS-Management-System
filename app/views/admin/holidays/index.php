
<?php
require_once __DIR__ . '/../../../core/Auth.php';
Auth::startSession();

$pageTitle = $pageTitle ?? 'Holiday Management';

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>

<div class="admin-main">
    <?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

    <div class="admin-content holiday-page">

        <div class="holiday-heading">
            <h2>Holiday Management</h2>
            <p>Add and manage organization holidays.</p>
        </div>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <section class="holiday-form-card">
            <div class="holiday-card-header">
                <h3>Add Holiday</h3>
                <p>Enter the details of the organization holiday.</p>
            </div>

            <div class="holiday-card-body">
                <form method="POST"
                      action="<?= BASE_URL ?>/index.php?action=holiday-store">

                    <input type="hidden"
                           name="csrf_token"
                           value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

                    <div class="holiday-form-grid">

                        <div class="holiday-field">
                            <label for="holiday_name">Holiday Name</label>
                            <input type="text"
                                   id="holiday_name"
                                   name="holiday_name"
                                   maxlength="100"
                                   required>
                        </div>

                        <div class="holiday-field">
                            <label for="holiday_date">Holiday Date</label>
                            <input type="date"
                                   id="holiday_date"
                                   name="holiday_date"
                                   required>
                        </div>

                        <div class="holiday-field">
                            <label for="holiday_type">Holiday Type</label>
                            <select id="holiday_type"
                                    name="holiday_type"
                                    required>
                                <option value="">Select Holiday Type</option>
                                <option value="Organizational">Organizational</option>
                                <option value="Governmental">Governmental</option>
                            </select>
                        </div>

                        <div class="holiday-field">
                            <label for="description">Description (Optional)</label>
                            <textarea id="description"
                                      name="description"
                                      rows="2"></textarea>
                        </div>

                    </div>

                    <button type="submit" class="holiday-add-button">
                        Add Holiday
                    </button>
                </form>
            </div>
        </section>

        <section class="holiday-list-card">
            <div class="holiday-card-header">
                <h3>Organization Holidays</h3>
                <p>All holidays registered for the organization.</p>
            </div>

            <div class="holiday-table-wrapper">
                <table class="holiday-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Holiday Name</th>
                            <th>Holiday Date</th>
                            <th>Holiday Type</th>
                            <th>Description</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (!empty($holidays)): ?>

                            <?php foreach ($holidays as $index => $holiday): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>

                                    <td>
                                        <?= htmlspecialchars($holiday['holiday_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($holiday['holiday_date'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>

                                    <td>
                                        <span class="holiday-type">
                                            <?= htmlspecialchars($holiday['holiday_type'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($holiday['description'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                    </td>

                                    <td>
                                        <form method="POST"
                                              action="<?= BASE_URL ?>/index.php?action=holiday-delete"
                                              onsubmit="return confirm('Are you sure you want to delete this holiday?');">

                                            <input type="hidden"
                                                   name="csrf_token"
                                                   value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

                                            <input type="hidden"
                                                   name="holiday_id"
                                                   value="<?= (int) $holiday['id'] ?>">

                                            <button type="submit"
                                                    class="holiday-delete-button">
                                                Delete Holiday
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="holiday-empty">
                                    No holidays have been added yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
            </div>
            
        </section>
        
    </div>
<?php require_once __DIR__ . '/../layouts/footer.php'; ?>