<<<<<<< Updated upstream
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
                        Present Today
                    </span>

                    <strong class="stat-value">
                        0
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
                        0
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
                        0
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
                            0
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
                            0
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
                            0
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

                <span class="dashboard-card-badge">
                    Live
                </span>

            </div>


            <div class="activity-empty">

                <div class="activity-empty-icon">
                    ◷
                </div>

                <h3>
                    No attendance activity yet
                </h3>

                <p>
                    Employee RFID scans will appear here.
                </p>

            </div>

        </section>


    </main>


    <!-- =====================================================
         FOOTER
         ===================================================== -->

=======
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
                        Present Today
                    </span>

                    <strong class="stat-value">
                        0
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
                        0
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
                        0
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
                            0
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
                            0
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
                            0
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

                <span class="dashboard-card-badge">
                    Live
                </span>

            </div>


            <div class="activity-empty">

                <div class="activity-empty-icon">
                    ◷
                </div>

                <h3>
                    No attendance activity yet
                </h3>

                <p>
                    Employee RFID scans will appear here.
                </p>

            </div>

        </section>


    </main>


    <!-- =====================================================
         FOOTER
         ===================================================== -->

>>>>>>> Stashed changes
    <?php require_once __DIR__ . '/../admin/layouts/footer.php'; ?>