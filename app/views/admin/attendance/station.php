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

<div class="admin-main attendance-terminal-page attendance-station-page">
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
                    <div id="attendanceErrorCard" class="terminal-message terminal-error" role="alert">
                        <strong>SCAN UNSUCCESSFUL</strong>
                        <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>
                <div id="attendanceResultHost" aria-live="polite"></div>
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

<script src="/HRIS-Management-System/public/assets/js/rfid-input-adapter.js"></script>
<script>
    (function () {
        const clock = document.getElementById('terminalClock');
        const date = document.getElementById('terminalDate');
        const input = document.getElementById('rfid_identifier');
        const scanForm = document.getElementById('attendanceScanForm');
        const resultHost = document.getElementById('attendanceResultHost');
        const errorCard = document.getElementById('attendanceErrorCard');

        let resultHideTimer = null;
        const scanQueue = [];
        let processingQueue = false;

        function focusScanInput() {
            if (input) {
                input.focus({ preventScroll: true });
            }
        }

        function hideAttendanceResult() {
            if (resultHideTimer !== null) {
                window.clearTimeout(resultHideTimer);
                resultHideTimer = null;
            }

            if (resultHost) {
                resultHost.replaceChildren();
            }
        }

        function scheduleResultDismissal() {
            if (resultHideTimer !== null) {
                window.clearTimeout(resultHideTimer);
            }

            resultHideTimer = window.setTimeout(function () {
                hideAttendanceResult();
            }, 5000);
        }

        function makeElement(tag, className, text) {
            const element = document.createElement(tag);

            if (className) element.className = className;
            if (text !== undefined && text !== null) {
                element.textContent = String(text);
            }

            return element;
        }

        function showAttendanceResult(data) {
            if (!resultHost) return;

            hideAttendanceResult();

            if (errorCard) {
                errorCard.remove();
            }

            const employee = data.employee || {};
            const employeeName = employee.full_name || 'Employee details unavailable';
            const rfidNumber = employee.identifier || 'Unavailable';
            const attendanceMessage = data.message || 'Attendance recorded.';
            const attendanceType = data.type || '';
            const statusMatch = attendanceMessage.match(
                /Status:\s*(Entry - In Time|Entry - Late|Exit - Before|Exit - In Time)\.?/i
            );

            const attendanceStatus = statusMatch
                ? statusMatch[1]
                : (attendanceType === 'ENTRY' ? 'Entry' : 'Exit');

            const card = makeElement(
                'div',
                'terminal-message terminal-success attendance-result-card'
            );
            card.id = 'attendanceResultCard';
            card.setAttribute('role', 'status');

            const heading = makeElement('div', 'attendance-result-heading');
            const check = makeElement('span', 'attendance-result-check', '✓');
            check.setAttribute('aria-hidden', 'true');
            heading.append(
                check,
                makeElement('strong', '', 'ATTENDANCE RECORDED')
            );

            const content = makeElement('div', 'attendance-result-content');
            const photoWrap = makeElement('div', 'attendance-result-photo-wrap');
            const photo = makeElement('img', 'attendance-result-photo');
            photo.alt = employeeName;
            photo.src = '/HRIS-Management-System/public/assets/images/employee-avatar.png';

            const userId = Number(employee.user_id || 0);
            if (userId > 0) {
                // The profile image is resolved by checking the known uploaded formats.
                photo.src = '/HRIS-Management-System/public/uploads/profile_pictures/userid_'
                    + userId + '.png';
                photo.onerror = function () {
                    if (photo.dataset.fallbackTried !== 'jpg') {
                        photo.dataset.fallbackTried = 'jpg';
                        photo.src = '/HRIS-Management-System/public/uploads/profile_pictures/userid_'
                            + userId + '.jpg';
                    } else if (photo.dataset.fallbackTried !== 'jpeg') {
                        photo.dataset.fallbackTried = 'jpeg';
                        photo.src = '/HRIS-Management-System/public/uploads/profile_pictures/userid_'
                            + userId + '.jpeg';
                    } else {
                        photo.onerror = null;
                        photo.src = '/HRIS-Management-System/public/assets/images/employee-avatar.png';
                    }
                };
            }

            photoWrap.appendChild(photo);

            const details = makeElement('div', 'attendance-result-details');
            const identity = makeElement('div', 'attendance-result-identity');
            const rfid = makeElement('div', 'attendance-result-rfid');
            rfid.append(
                makeElement('span', '', 'RFID Card:'),
                makeElement('strong', '', rfidNumber)
            );

            const name = makeElement('div', 'attendance-result-name', employeeName);
            identity.append(rfid, name);

            const outcome = makeElement('div', 'attendance-result-outcome');
            outcome.append(
                makeElement('strong', 'attendance-result-status', attendanceStatus),
                makeElement('span', 'attendance-result-time', attendanceMessage)
            );

            details.append(identity, outcome);
            content.append(photoWrap, details);
            card.append(heading, content);
            resultHost.replaceChildren(card);

            scheduleResultDismissal();
        }

        function showAttendanceError(message) {
            hideAttendanceResult();

            if (errorCard) {
                errorCard.remove();
            }

            const card = makeElement('div', 'terminal-message terminal-error');
            card.id = 'attendanceErrorCard';
            card.setAttribute('role', 'alert');
            card.append(
                makeElement('strong', '', 'SCAN UNSUCCESSFUL'),
                makeElement('span', '', message || 'Unable to record attendance.')
            );

            resultHost.replaceChildren(card);

            if (resultHideTimer !== null) {
                window.clearTimeout(resultHideTimer);
            }

            resultHideTimer = window.setTimeout(function () {
                hideAttendanceResult();
            }, 5000);
        }

        async function sendAttendanceScan(identifier) {
            const formData = new FormData(scanForm);
            formData.set('rfid_identifier', identifier);

            try {
                const response = await fetch(scanForm.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    cache: 'no-store'
                });

                let data;
                try {
                    data = await response.json();
                } catch (parseError) {
                    throw new Error('The server returned an invalid response. Please refresh the station.');
                }

                if (!response.ok || !data) {
                    throw new Error(data && data.message
                        ? data.message
                        : 'Unable to process this RFID scan.');
                }

                if (data.success) {
                    showAttendanceResult(data);
                } else {
                    showAttendanceError(data.message);
                }
            } catch (error) {
                showAttendanceError(error.message || 'A connection error occurred. Please try again.');
            } finally {
                focusScanInput();
            }
        }

        async function processScanQueue() {
            if (processingQueue) return;

            processingQueue = true;

            try {
                while (scanQueue.length > 0) {
                    const identifier = scanQueue.shift();
                    await sendAttendanceScan(identifier);
                }
            } finally {
                processingQueue = false;
                focusScanInput();
            }
        }

        function queueAttendanceScan(identifier) {
            const normalizedIdentifier = String(identifier || '').trim();

            if (!normalizedIdentifier) {
                focusScanInput();
                return;
            }

            scanQueue.push(normalizedIdentifier);
            focusScanInput();
            processScanQueue();
        }

        if (scanForm && input && typeof RfidInputAdapter !== 'undefined') {
            const rfidAdapter = new RfidInputAdapter(input, queueAttendanceScan);
            rfidAdapter.connect();

            scanForm.addEventListener('submit', function (event) {
                event.preventDefault();

                const identifier = input.value.trim();
                input.value = '';

                if (identifier) {
                    queueAttendanceScan(identifier);
                } else {
                    focusScanInput();
                }
            });
        }

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

        if (errorCard) {
            window.setTimeout(function () {
                if (errorCard.isConnected) errorCard.remove();
            }, 5000);
        }

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