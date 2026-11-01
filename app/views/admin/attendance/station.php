<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/Auth.php';

Auth::startSession();

$pageTitle = 'MYOL Smart Attendance';

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<link rel="stylesheet" href="/HRIS-Management-System/public/assets/css/attendance-station.css">

<div class="admin-main attendance-terminal-page">
    <?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

    <main class="admin-content attendance-terminal">

        <header class="terminal-heading">
            <div>
                <span class="terminal-eyebrow">MYOL · HUMAN RESOURCES</span>
                <h1>Smart Attendance Terminal</h1>
                <p>Seamless employee identification and attendance recording.</p>
            </div>

            <div class="terminal-online reader-status-offline" id="terminalSystemStatus" role="status"
                aria-live="polite">
                <span class="online-dot"></span>
                <span class="status-label">READER OFFLINE</span>
            </div>
        </header>

        <section class="terminal-shell">

            <div class="terminal-topbar">
                <div class="terminal-brand">
                    <span class="terminal-brand-icon">
                        <img src="/HRIS-Management-System/public/assets/images/myol-logo.jpg" alt="MYOL Company Logo">
                    </span>
                    <div>
                        <strong>MYOL ACCESS</strong>
                        <span>RFID ATTENDANCE SYSTEM</span>
                    </div>
                </div>

                <div class="terminal-clock">
                    <strong id="terminalClock">--:--:--</strong>
                    <span id="terminalDate">Loading date...</span>
                </div>
            </div>

            <div class="terminal-main">

                <div class="terminal-instructions">
                    <span class="instruction-line"></span>
                    <span>EMPLOYEE AUTHENTICATION</span>
                    <span class="instruction-line"></span>
                </div>

                <div class="scanner-area">
                    <div class="scanner-orbit orbit-one"></div>
                    <div class="scanner-orbit orbit-two"></div>
                    <div class="scanner-orbit orbit-three"></div>

                    <div class="scanner-core">
                        <div class="scanner-inner">
                            <svg class="scanner-icon" viewBox="0 0 64 64" aria-hidden="true">
                                <path d="M20 18a20 20 0 0 0 0 28" />
                                <path d="M27 24a11 11 0 0 0 0 16" />
                                <path d="M34 29a4 4 0 0 0 0 6" />
                                <path d="M44 18a20 20 0 0 1 0 28" />
                                <path d="M37 24a11 11 0 0 1 0 16" />
                            </svg>

                            <span class="scanner-label">RFID</span>
                        </div>
                    </div>

                    <div class="scanner-sweep"></div>
                </div>

                <div class="scanner-copy">
                    <span class="scanner-state reader-status-offline" id="terminalScanStatus" role="status"
                        aria-live="polite">
                        <span class="state-dot"></span>
                        <span class="status-label">READER DISCONNECTED</span>
                    </span>

                    <h2>SCAN YOUR RFID CARD HERE</h2>
                    <p>Place your registered employee card near the RFID reader.</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="terminal-message terminal-error" role="alert">
                        <strong>SCAN UNSUCCESSFUL</strong>
                        <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <?php
                    $employee = is_array($attendanceEmployee ?? null)
                        ? $attendanceEmployee
                        : [];

                    $employeeName = trim((string) ($employee['full_name'] ?? ''));
                    $rfidNumber = trim((string) ($employee['identifier'] ?? ''));

                    $employeeUserId = (int) ($employee['user_id'] ?? 0);

                    $profilePictureDirectory = dirname(__DIR__, 4)
                        . '/public/uploads/profile_pictures';

                    $profilePictureUrl =
                        '/HRIS-Management-System/public/assets/images/employee-avatar.png';

                    if ($employeeUserId > 0) {
                        foreach (['png', 'jpg', 'jpeg'] as $extension) {
                            $fileName = 'userid_' . $employeeUserId . '.' . $extension;
                            $filePath = $profilePictureDirectory . '/' . $fileName;

                            if (is_file($filePath)) {
                                $profilePictureUrl =
                                    '/HRIS-Management-System/public/uploads/profile_pictures/'
                                    . rawurlencode($fileName);
                                break;
                            }
                        }
                    }

                    $attendanceMessage = (string) $success;
                    $attendanceStatus = '';

                    if (preg_match('/Status:\s*(Entry - In Time|Entry - Late|Exit - Before|Exit - In Time)\.?/i', $attendanceMessage, $matches)) {
                        $attendanceStatus = $matches[1];
                    } elseif (($attendanceType ?? '') === 'ENTRY') {
                        $attendanceStatus = 'Entry';
                    } elseif (($attendanceType ?? '') === 'EXIT') {
                        $attendanceStatus = 'Exit';
                    }
                    ?>

                    <div class="terminal-message terminal-success attendance-result-card" role="status">
                        <div class="attendance-result-heading">
                            <span class="attendance-result-check" aria-hidden="true">✓</span>
                            <strong>ATTENDANCE RECORDED</strong>
                        </div>

                        <div class="attendance-result-content">
                            <div class="attendance-result-photo-wrap">
                                <img class="attendance-result-photo"
                                    src="<?= htmlspecialchars($profilePictureUrl, ENT_QUOTES, 'UTF-8') ?>"
                                    alt="<?= htmlspecialchars($employeeName !== '' ? $employeeName : 'Employee', ENT_QUOTES, 'UTF-8') ?>"
                                    onerror="this.onerror=null;this.src='/HRIS-Management-System/public/assets/images/employee-avatar.png';">
                            </div>

                            <div class="attendance-result-details">
                                <div class="attendance-result-identity">
                                    <div class="attendance-result-rfid">
                                        <span>RFID Card:</span>
                                        <strong><?= htmlspecialchars($rfidNumber !== '' ? $rfidNumber : 'Unavailable', ENT_QUOTES, 'UTF-8') ?></strong>
                                    </div>

                                    <div class="attendance-result-name">
                                        <?= htmlspecialchars($employeeName !== '' ? $employeeName : 'Employee details unavailable', ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                </div>

                                <div class="attendance-result-outcome">
                                    <strong class="attendance-result-status">
                                        <?= htmlspecialchars($attendanceStatus !== '' ? $attendanceStatus : 'Attendance Recorded', ENT_QUOTES, 'UTF-8') ?>
                                    </strong>

                                    <span class="attendance-result-time">
                                        <?= htmlspecialchars($attendanceMessage, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/HRIS-Management-System/public/index.php?action=attendance-scan"
                    id="attendanceScanForm" class="terminal-scan-form" autocomplete="off">
                    <input type="hidden" name="csrf_token"
                        value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                    <label for="rfid_identifier">RFID CARD IDENTIFIER</label>

                    <div class="terminal-input-wrap">
                        <span class="input-indicator"></span>
                        <input type="text" id="rfid_identifier" name="rfid_identifier"
                            placeholder="Waiting for card scan..." autocomplete="off" autocapitalize="off"
                            spellcheck="false" required autofocus>
                        <span class="input-tag">RFID INPUT</span>
                    </div>

                    <button type="submit" class="terminal-submit">
                        <span>RECORD ATTENDANCE</span>
                        <span aria-hidden="true">→</span>
                    </button>

                    <p class="terminal-helper">
                        The reader sends the card identifier automatically.
                    </p>
                </form>

            </div>

            <footer class="terminal-footer">
                <span class="terminal-footer-connection reader-status-offline" id="terminalConnectionStatus"
                    role="status" aria-live="polite">
                    <span class="footer-green-dot"></span>
                    <span class="status-label">CONNECTION LOST</span>
                </span>
                <span>MY OUTSOURCING LIMITED</span>
                <span>SMART ATTENDANCE · v1.0</span>
            </footer>

        </section>

    </main>

    <?php

    require_once __DIR__ . '/../layouts/footer.php';

    ?>
</div>

<script>
    (function () {
        const clock = document.getElementById('terminalClock');
        const date = document.getElementById('terminalDate');
        const input = document.getElementById('rfid_identifier');

        function updateClock() {
            const now = new Date();

            clock.textContent = now.toLocaleTimeString('en-GB', {
                hour12: false
            });

            date.textContent = now.toLocaleDateString('en-GB', {
                weekday: 'short',
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
        }

        updateClock();
        window.setInterval(updateClock, 1000);

        window.addEventListener('load', function () {
            if (input) {
                input.focus();
            }
        });

        document.addEventListener('click', function (event) {
            if (
                input &&
                !event.target.closest('button, a, input, label')
            ) {
                input.focus();
            }
        });


        const systemStatus = document.getElementById('terminalSystemStatus');
        const scanStatus = document.getElementById('terminalScanStatus');
        const connectionStatus = document.getElementById('terminalConnectionStatus');
        const terminalPage = document.querySelector('.attendance-terminal-page');

        const statusUrl =
            '/HRIS-Management-System/public/rfid-reader-status.json';

        async function updateReaderStatus() {
            try {
                const response = await fetch(statusUrl + '?_=' + Date.now(), {
                    cache: 'no-store',
                    headers: { 'Accept': 'application/json' }
                });

                if (!response.ok) {
                    throw new Error('Reader status unavailable');
                }

                const data = await response.json();

                // Accept only a fresh status from the existing monitor.
                const checkedAt = Date.parse(data.checked_at);
                const statusIsFresh =
                    Number.isFinite(checkedAt) &&
                    Date.now() - checkedAt >= -5000 &&
                    Date.now() - checkedAt <= 10000;

                const connected =
                    data.connected === true &&
                    data.reader === 'JT308' &&
                    statusIsFresh;

                setReaderStatus(connected);
            } catch (error) {
                setReaderStatus(false);
            }
        }

        function setReaderStatus(connected) {
            const indicators = [
                {
                    element: systemStatus,
                    connectedText: 'SYSTEM ONLINE',
                    disconnectedText: 'SYSTEM OFFLINE'
                },
                {
                    element: scanStatus,
                    connectedText: 'READY TO SCAN',
                    disconnectedText: 'READER DISCONNECTED'
                },
                {
                    element: connectionStatus,
                    connectedText: 'SECURE CONNECTION',
                    disconnectedText: 'CONNECTION LOST'
                }
            ];

            indicators.forEach(function (indicator) {
                if (!indicator.element) return;

                indicator.element.classList.toggle(
                    'reader-status-connected',
                    connected
                );

                indicator.element.classList.toggle(
                    'reader-status-offline',
                    !connected
                );

                const label = indicator.element.querySelector('.status-label');

                if (label) {
                    label.textContent = connected
                        ? indicator.connectedText
                        : indicator.disconnectedText;
                }
            });

            if (terminalPage) {
                terminalPage.classList.toggle('reader-connected', connected);
                terminalPage.classList.toggle('reader-disconnected', !connected);
            }
        }

        // Start red until the real reader status is confirmed.
        setReaderStatus(false);
        updateReaderStatus();
        window.setInterval(updateReaderStatus, 2000);

    })();
</script>