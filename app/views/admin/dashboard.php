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

    <header class="admin-navbar">

        <div class="navbar-title">

            <div class="navbar-title-main">
                Dashboard
            </div>

            <div class="navbar-title-sub">
                HRIS Management System
            </div>

        </div>


        <div class="navbar-right">


            <!-- =================================================
             NOTIFICATION
             ================================================= -->

            <button type="button" class="navbar-notification" title="Notifications">

                <svg viewBox="0 0 24 24" aria-hidden="true">

                    <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" />

                    <path d="M10 21h4" />

                </svg>

            </button>


            <!-- =================================================
             USER MENU
             ================================================= -->

            <div class="navbar-user-menu">


                <button type="button" class="navbar-user" id="userMenuButton" aria-expanded="false">

                    <div class="navbar-user-avatar">

                        <?= strtoupper(
                            substr(
                                Auth::fullName() ?? 'A',
                                0,
                                1
                            )
                        ) ?>

                    </div>


                    <div class="navbar-user-info">

                        <div class="navbar-user-name">

                            <?= htmlspecialchars(
                                Auth::fullName() ?? 'Administrator'
                            ) ?>

                        </div>

                        <div class="navbar-user-role">

                            <?= htmlspecialchars(
                                Auth::roleName() ?? 'Administrator'
                            ) ?>

                        </div>

                    </div>


                    <svg class="navbar-user-arrow" viewBox="0 0 24 24" aria-hidden="true">

                        <path d="M6 9l6 6 6-6" />

                    </svg>

                </button>


                <!-- Dropdown -->

                <div class="profile-dropdown" id="profileDropdown">

                    <div class="profile-dropdown-header">

                        <div class="profile-dropdown-avatar">

                            <?= strtoupper(
                                substr(
                                    Auth::fullName() ?? 'A',
                                    0,
                                    1
                                )
                            ) ?>

                        </div>

                        <div>

                            <strong>

                                <?= htmlspecialchars(
                                    Auth::fullName() ?? 'Administrator'
                                ) ?>

                            </strong>

                            <span>

                                <?= htmlspecialchars(
                                    Auth::username() ?? 'admin'
                                ) ?>

                            </span>

                        </div>

                    </div>


                    <div class="profile-dropdown-divider">
                    </div>


                    <a href="#" class="profile-dropdown-link disabled">

                        <svg viewBox="0 0 24 24">

                            <circle cx="12" cy="8" r="3" />

                            <path d="M5 21c.5-4 3-6 7-6s6.5 2 7 6" />

                        </svg>

                        My Profile

                    </a>


                    <a href="#" class="profile-dropdown-link disabled">

                        <svg viewBox="0 0 24 24">

                            <circle cx="12" cy="12" r="3" />

                            <path
                                d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-1.8 1.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V20h-2.5v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1-1.8-1.8.1-.1A1.7 1.7 0 0 0 8.1 15a1.7 1.7 0 0 0-1.5-1H6v-2.5h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1L9 6.7l.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.5V5h2.5v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1 1.8 1.8-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.5 1h.1V13h-.1a1.7 1.7 0 0 0-1.5 1z" />

                        </svg>

                        Account Settings

                    </a>


                    <div class="profile-dropdown-divider">
                    </div>


                    <a href="/HRIS-Management-System/public/index.php?action=logout"
                        class="profile-dropdown-link profile-logout">

                        <svg viewBox="0 0 24 24">

                            <path d="M10 4H5v16h5" />

                            <path d="M14 8l4 4-4 4" />

                            <path d="M8 12h10" />

                        </svg>

                        Logout

                    </a>

                </div>

            </div>

        </div>

    </header>


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
                        0
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

                    <div class="rfid-status-indicator">
                    </div>

                    <div>

                        <strong>
                            Not Connected
                        </strong>

                        <p>
                            RFID reader status will appear here.
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

    <?php require_once __DIR__ . '/layouts/footer.php'; ?>