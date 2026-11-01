<?php

require_once __DIR__ . '/../../../core/Auth.php';

Auth::startSession();

$pageTitle = 'Assign RFID Card';

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

?>

<div class="admin-main">

    <?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

    <main class="admin-content">

        <div class="content-header">
            <div>
                <h1>Assign RFID Card</h1>
                <p>Select an employee using their unique Employee Code and scan their RFID card.</p>
            </div>
            <a href="/HRIS-Management-System/public/index.php?action=rfid" class="btn btn-secondary">
                Back to RFID Management
            </a>
        </div>

        <section class="content-card rfid-registration-card">

            <div class="card-header">
                <div>
                    <h2>Assign RFID Card</h2>
                    <p>Connect an RFID card to one employee.</p>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error" role="alert">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success" role="status">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form id="rfid-registration-form" method="POST"
                action="/HRIS-Management-System/public/index.php?action=rfid-store">

                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">

                <div class="form-group">
                    <label for="employee_id">Select Employee</label>

                    <select id="employee_id" name="employee_id" required>
                        <option value="">-- Select an employee --</option>

                        <?php foreach ($employees as $employee): ?>
                            <option value="<?= (int) $employee['employee_id'] ?>">
                                <?= htmlspecialchars(
                                    ($employee['employee_code'] ?? '') .
                                    ' - ' .
                                    ($employee['username'] ?? 'Employee')
                                ) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="form-group">
                    <label for="rfid_identifier">RFID Identifier</label>

                    <input type="text" id="rfid_identifier" name="rfid_identifier" autocomplete="off"
                        placeholder="Click here, then scan the card" required>

                    <small>
                        Focus this field and scan the card using the JT308 reader.
                    </small>

                    <p id="rfid-scan-status" role="status" aria-live="polite">
                        Reader ready. Click the RFID field and scan a card.
                    </p>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        Register RFID Card
                    </button>
                </div>

            </form>

        </section>

    </main>
    <?php require_once __DIR__ . '/../layouts/footer.php'; ?>
</div>

<script src="/HRIS-Management-System/public/assets/js/rfid-input-adapter.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const input = document.getElementById('rfid_identifier');
        const status = document.getElementById('rfid-scan-status');

        if (!input || !status || typeof RfidInputAdapter === 'undefined') {
            if (status) {
                status.textContent = 'RFID scanner adapter could not be loaded.';
            }
            return;
        }

        const adapter = new RfidInputAdapter(input, function (identifier) {
            input.value = identifier;
            status.textContent = 'Card detected: ' + identifier;
        });

        adapter.connect();
    });
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>