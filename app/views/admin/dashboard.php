<?php

require_once __DIR__ . '/../../core/Auth.php';

Auth::startSession();

$pageTitle = 'Dashboard';

require_once __DIR__ . '/layouts/header.php';
require_once __DIR__ . '/layouts/sidebar.php';

?>

<!-- =========================================================
     MAIN ADMIN AREA
     ========================================================= -->

<div class="admin-main">


    <!-- =====================================================
         TOP NAVBAR
         ===================================================== -->

    <?php require_once __DIR__ . '/layouts/navbar.php'; ?>


    <!-- =====================================================
         DASHBOARD CONTENT
         ===================================================== -->

    <main class="admin-content">


        <!-- Welcome -->

        <section class="dashboard-welcome">

            <div>

                <h1>
                    Welcome back,
                    <?= htmlspecialchars(
                        Auth::fullName() ?? 'Administrator'
                    ) ?>.
                </h1>

                <p>
                    Here's an overview of your HRIS system.
                </p>

            </div>

        </section>


        <!-- =================================================
             STAT CARDS
             ================================================= -->

        <section class="dashboard-stats">


            <!-- Employees -->

            <div class="dashboard-stat-card">

                <div class="stat-icon">
                    ♙
                </div>

                <div class="stat-content">

                    <span class="stat-label">
                        Total Employees
                    </span>

                    <strong class="stat-value">
                        <?= (int) ($totalEmployees ?? 0) ?>
                    </strong>

                    <span class="stat-description">
                        Registered employees
                    </span>

                </div>

            </div>


            <!-- Present -->

            <div class="dashboard-stat-card">

                <div class="stat-icon">
                    ✓
                </div>

                <div class="stat-content">

                    <span class="stat-label">
                        In Time Today
                    </span>

                    <strong class="stat-value">
                        <?= (int) ($presentToday ?? 0) ?>
                    </strong>

                    <span class="stat-description">
                        Attendance records
                    </span>

                </div>

            </div>


            <!-- Late -->

            <div class="dashboard-stat-card">

                <div class="stat-icon">
                    ◷
                </div>

                <div class="stat-content">

                    <span class="stat-label">
                        Late Today
                    </span>

                    <strong class="stat-value">
                        <?= (int) ($lateToday ?? 0) ?>
                    </strong>

                    <span class="stat-description">
                        Late arrivals
                    </span>

                </div>

            </div>


            <!-- Absent -->

            <div class="dashboard-stat-card">

                <div class="stat-icon">
                    !
                </div>

                <div class="stat-content">

                    <span class="stat-label">
                        Absent Today
                    </span>

                    <strong class="stat-value">
                        <?= (int) ($absentToday ?? 0) ?>
                    </strong>

                    <span class="stat-description">
                        No attendance recorded
                    </span>

                </div>

            </div>

        </section>


        <!-- =================================================
             MAIN DASHBOARD GRID
             ================================================= -->

        <section class="dashboard-grid">


            <!-- Attendance Overview -->

            <div class="dashboard-card attendance-card">

                <div class="dashboard-card-header">

                    <div>

                        <h2>
                            Attendance Overview
                        </h2>

                        <p>
                            Today's attendance summary
                        </p>

                    </div>

                    <span class="dashboard-card-badge">
                        Today
                    </span>

                </div>


                <div class="attendance-overview">


                    <div class="attendance-item">

                        <span class="attendance-dot present">
                        </span>

                        <div>

                            <strong>
                                Present
                            </strong>

                            <small>
                                Employees who arrived
                            </small>

                        </div>

                        <span class="attendance-number">
                            <?= (int) ($presentToday ?? 0) ?>
                        </span>

                    </div>


                    <div class="attendance-item">

                        <span class="attendance-dot late">
                        </span>

                        <div>

                            <strong>
                                Late
                            </strong>

                            <small>
                                Employees arriving late
                            </small>

                        </div>

                        <span class="attendance-number">
                            <?= (int) ($lateToday ?? 0) ?>
                        </span>

                    </div>


                    <div class="attendance-item">

                        <span class="attendance-dot absent">
                        </span>

                        <div>

                            <strong>
                                Absent
                            </strong>

                            <small>
                                No attendance recorded
                            </small>

                        </div>

                        <span class="attendance-number">
                            <?= (int) ($absentToday ?? 0) ?>
                        </span>

                    </div>

                </div>

            </div>


            <!-- RFID Status -->

            <div class="dashboard-card">

                <div class="dashboard-card-header">

                    <div>

                        <h2>
                            RFID Reader
                        </h2>

                        <p>
                            Identification system status
                        </p>

                    </div>

                </div>


                <div class="rfid-status">

                    <div class="rfid-status-indicator" id="rfid-status-indicator"></div>

                    <div>

                        <strong id="rfid-connection-text">Checking connection...</strong>

                        <p id="rfid-connection-description">
                            Checking JT308 USB reader status.
                        </p>

                    </div>

                </div>


                <div
                    style="height: 105px; display: flex; align-items: center; justify-content: center; overflow: hidden; margin: 8px 0;">

                    <img id="rfid-reader-image"
                        src="/HRIS-Management-System/public/assets/images/Windows%20USB%20RFID%20Reader%20with%20Glowing%20LED.png"
                        data-connected-image="/HRIS-Management-System/public/assets/images/Windows%20USB%20RFID%20Reader%20with%20Glowing%20LED.png"
                        data-disconnected-image="/HRIS-Management-System/public/assets/images/Windows%20USB%20RFID%20Reader%20with%20Cable.png"
                        alt="JT308 USB RFID Reader" style="width: 100%; height: 100%; object-fit: contain;">

                </div>



                <div class="rfid-info">

                    <span>
                        Reader
                    </span>

                    <strong>
                        JT308
                    </strong>

                </div>


                <div class="rfid-info">

                    <span>
                        Frequency
                    </span>

                    <strong>
                        125 kHz
                    </strong>

                </div>


                <script>
                    (() => {
                        const readerImage = document.getElementById('rfid-reader-image');
                        const connectionText = document.getElementById('rfid-connection-text');

                        if (!readerImage || !connectionText) return;

                        const updateReaderImage = () => {
                            const status = connectionText.textContent
                                .trim()
                                .toLowerCase();

                            const connected =
                                status === 'connected' ||
                                status.startsWith('connected ');

                            const nextImage = connected
                                ? readerImage.dataset.connectedImage
                                : readerImage.dataset.disconnectedImage;

                            if (nextImage && !readerImage.src.endsWith(nextImage.split('/').pop())) {
                                readerImage.src = nextImage;
                            }
                        };

                        updateReaderImage();

                        const observer = new MutationObserver(updateReaderImage);

                        observer.observe(connectionText, {
                            childList: true,
                            characterData: true,
                            subtree: true
                        });
                    })();
                </script>


            </div>


        </section>


        <!-- =================================================
             TODAY'S ACTIVITY
             ================================================= -->

        <section class="dashboard-card activity-card">

            <div class="dashboard-card-header">

                <div>

                    <h2>
                        Today's Activity
                    </h2>

                    <p>
                        Latest employee attendance activity
                    </p>

                </div>

                <span id="activity-feed-status" class="dashboard-card-badge activity-live-badge" data-status="checking"
                    aria-live="polite" aria-label="Attendance activity feed status">
                    <span class="activity-live-dot" aria-hidden="true"></span>
                    <span id="activity-feed-status-text">CHECKING</span>
                </span>

            </div>



            <?php
            $groupedActivity = [];

            foreach (($todayActivity ?? []) as $activity) {
                $employeeId = (int) $activity['employee_id'];

                if (!isset($groupedActivity[$employeeId])) {
                    $groupedActivity[$employeeId] = [
                        'full_name' => $activity['full_name'],
                        'employee_code' => $activity['employee_code'],
                        'scans' => [],
                    ];
                }

                $groupedActivity[$employeeId]['scans'][] = $activity;
            }
            ?>

            <?php if (!empty($groupedActivity)): ?>

                <div class="activity-feed">


                    <?php foreach ($groupedActivity as $employee): ?>

                        <div class="activity-item">

                            <div class="activity-item-details">

                                <strong>
                                    <?= htmlspecialchars((string) $employee['full_name'], ENT_QUOTES, 'UTF-8') ?>
                                </strong>

                                <span>
                                    <?= htmlspecialchars((string) $employee['employee_code'], ENT_QUOTES, 'UTF-8') ?>
                                </span>

                                <?php foreach ($employee['scans'] as $scan): ?>

                                    <div class="activity-scan-row">

                                        <small>
                                            <?= ucfirst($scan['scan_type']) ?>:
                                            <?= date('h:i:s A', strtotime($scan['scan_time'])) ?>
                                        </small>

                                        <span class="activity-scan-status">
                                            <?= htmlspecialchars(
                                                (string) ($scan['scan_status'] ?? 'Scan recorded'),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </span>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        </div>

                    <?php endforeach; ?>


                </div>

            <?php else: ?>

                <div class="activity-empty">

                    <div class="activity-empty-icon">◷</div>

                    <h3>No attendance activity yet</h3>

                    <p>Employee RFID scans will appear here.</p>

                </div>

            <?php endif; ?>


        </section>


    </main>


    <script>
        (() => {
            const activityFeed = document.querySelector('.activity-feed');
            if (!activityFeed) return;

            const activityEndpoint =
                '/HRIS-Management-System/public/index.php?action=attendance-activity';

            const escapeHtml = (value) => {
                const element = document.createElement('span');
                element.textContent = String(value ?? '');
                return element.innerHTML;
            };

            const formatTime = (value) => {
                const date = new Date(String(value).replace(' ', 'T'));

                if (Number.isNaN(date.getTime())) return '—';

                return date.toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true
                });
            };

            const renderActivity = (scans) => {
                const employees = new Map();

                scans.forEach((scan) => {
                    const id = String(scan.employee_id);

                    if (!employees.has(id)) {
                        employees.set(id, {
                            fullName: scan.full_name,
                            employeeCode: scan.employee_code,
                            profilePicture: scan.profile_picture,
                            scans: []
                        });
                    }

                    employees.get(id).scans.push(scan);
                });

                const cards = Array.from(employees.values()).map((employee) => {
                    const rows = employee.scans.map((scan) => {
                        const type = String(scan.scan_type || '').toLowerCase();
                        const label = type === 'entry' ? 'Entry' : 'Exit';

                        return `
                    <div class="activity-scan-row">
                        <small>${label}: ${formatTime(scan.scan_time)}</small>
                        <span class="activity-scan-status">
                            ${escapeHtml(scan.scan_status || 'Scan recorded')}
                        </span>
                    </div>
                `;
                    }).join('');



                    return `
    <div class="activity-item activity-item-vertical">

        <div class="activity-employee-header">

            <img
                class="activity-employee-photo"
                src="${escapeHtml(employee.profilePicture || '/HRIS-Management-System/public/assets/images/employee-avatar.png')}"
                alt="${escapeHtml(employee.fullName)}"
                onerror="this.onerror=null;this.src='/HRIS-Management-System/public/assets/images/employee-avatar.png';"
            >

            <div class="activity-employee-identity">
                <strong>${escapeHtml(employee.fullName)}</strong>
                <span>${escapeHtml(employee.employeeCode)} · RFID Attendance</span>
            </div>

        </div>

        <div class="activity-employee-scans">
            ${rows}
        </div>

    </div>
`;


                }).join('');

                activityFeed.innerHTML = cards || `
            <div class="activity-empty">
                <div class="activity-empty-icon">◷</div>
                <h3>No attendance activity yet</h3>
                <p>Employee RFID scans will appear here.</p>
            </div>
        `;
            };

            let isRefreshing = false;

            const refreshActivity = async () => {
                if (isRefreshing) return;

                isRefreshing = true;

                try {
                    const response = await fetch(activityEndpoint, {
                        method: 'GET',
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: { 'Accept': 'application/json' }
                    });

                    if (!response.ok) {
                        throw new Error(`Activity request failed: ${response.status}`);
                    }

                    const scans = await response.json();

                    if (!Array.isArray(scans)) {
                        throw new Error('Invalid activity response.');
                    }

                    scans.sort((a, b) => {
                        const timeDifference =
                            new Date(String(a.scan_time).replace(' ', 'T')) -
                            new Date(String(b.scan_time).replace(' ', 'T'));

                        return timeDifference ||
                            Number(a.scan_id) - Number(b.scan_id);
                    });

                    renderActivity(scans);
                } catch (error) {
                    console.error('Could not refresh attendance activity:', error);
                } finally {
                    isRefreshing = false;
                }
            };

            refreshActivity();
            setInterval(refreshActivity, 3000);
        })();
    </script>



    <!-- =====================================================
         FOOTER
         ===================================================== -->

    <?php require_once __DIR__ . '/../admin/layouts/footer.php'; ?>