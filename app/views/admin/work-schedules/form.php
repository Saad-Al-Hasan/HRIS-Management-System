<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/Auth.php';

Auth::startSession();

$isEditing = is_array($schedule ?? null);
$formData = $old ?? [];

$name = (string) ($formData['name'] ?? ($schedule['name'] ?? ''));
$entryTime = (string) ($formData['expected_entry_time'] ?? ($schedule['expected_entry_time'] ?? ''));
$exitTime = (string) ($formData['expected_exit_time'] ?? ($schedule['expected_exit_time'] ?? ''));

$pageTitle = $isEditing ? 'Edit Work Schedule' : 'Create Work Schedule';

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>

<div class="admin-main work-schedule-form-page">

    <?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

    <main class="admin-content">

        <div class="content-header">
            <div>
                <h1><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                <p>
                    <?= $isEditing
                        ? 'Update the selected work schedule.'
                        : 'Enter the details for a work schedule.' ?>
                </p>
            </div>

            <a
                href="/HRIS-Management-System/public/index.php?action=work-schedules"
                class="btn btn-secondary"
            >
                Back to Schedules
            </a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error" role="alert">
                <p>Please correct the following:</p>
                <ul>
                    <?php foreach ($errors as $message): ?>
                        <li><?= htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <section class="content-card">

            <div class="card-header">
                <div>
                    <h2>Schedule Details</h2>
                    <p>Configure the shift name and expected entry and exit times.</p>
                </div>
            </div>

            <form
                method="POST"
                action="/HRIS-Management-System/public/index.php?action=<?= $isEditing ? 'work-schedules-update' : 'work-schedules-store' ?>"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        (string) ($csrfToken ?? Auth::csrfToken()),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

                <?php if ($isEditing): ?>
                    <input
                        type="hidden"
                        name="id"
                        value="<?= (int) $schedule['id'] ?>"
                    >
                <?php endif; ?>

                <div class="form-group">
                    <label for="name">Schedule Name</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        maxlength="100"
                        value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="e.g. 9 AM - 6 PM"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="expected_entry_time">Expected Entry Time</label>
                    <input
                        type="time"
                        id="expected_entry_time"
                        name="expected_entry_time"
                        value="<?= htmlspecialchars($entryTime, ENT_QUOTES, 'UTF-8') ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="expected_exit_time">Expected Exit Time</label>
                    <input
                        type="time"
                        id="expected_exit_time"
                        name="expected_exit_time"
                        value="<?= htmlspecialchars($exitTime, ENT_QUOTES, 'UTF-8') ?>"
                        required
                    >
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <?= $isEditing ? 'Save Changes' : 'Create Schedule' ?>
                    </button>

                    <a
                        href="/HRIS-Management-System/public/index.php?action=work-schedules"
                        class="btn btn-secondary"
                    >
                        Cancel
                    </a>
                </div>

            </form>

        </section>

    </main>

    <?php require_once __DIR__ . '/../layouts/footer.php'; ?>

</div>
