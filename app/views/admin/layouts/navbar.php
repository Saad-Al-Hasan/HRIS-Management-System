<?php

require_once __DIR__ . '/../../../core/Auth.php';

Auth::startSession();

?>

<header class="admin-navbar">

    <div class="navbar-title">

        <div class="navbar-title-main">
            <?= htmlspecialchars($pageTitle ?? 'Dashboard') ?>
        </div>

        <div class="navbar-title-sub">
            HRIS Management System
        </div>

    </div>


    <div class="navbar-right">


        <!-- =================================================
             NOTIFICATION
             ================================================= -->

        <button
            type="button"
            class="navbar-notification"
            title="Notifications"
        >

            <svg viewBox="0 0 24 24" aria-hidden="true">

                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" />

                <path d="M10 21h4" />

            </svg>

        </button>


        <!-- =================================================
             USER MENU
             ================================================= -->

        <div class="navbar-user-menu">


            <button
                type="button"
                class="navbar-user"
                id="userMenuButton"
                aria-expanded="false"
            >

                <div class="navbar-user-avatar">

                    <?= htmlspecialchars(
                        strtoupper(
                            substr(
                                Auth::fullName() ?? 'A',
                                0,
                                1
                            )
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


                <svg
                    class="navbar-user-arrow"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >

                    <path d="M6 9l6 6 6-6" />

                </svg>

            </button>


            <!-- Dropdown -->

            <div
                class="profile-dropdown"
                id="profileDropdown"
            >

                <div class="profile-dropdown-header">

                    <div class="profile-dropdown-avatar">

                        <?= htmlspecialchars(
                            strtoupper(
                                substr(
                                    Auth::fullName() ?? 'A',
                                    0,
                                    1
                                )
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


                <a
                    href="/HRIS-Management-System/public/index.php?action=profile"
                    class="profile-dropdown-link"
                >

                    <svg viewBox="0 0 24 24">

                        <circle cx="12" cy="8" r="3" />

                        <path d="M5 21c.5-4 3-6 7-6s6.5 2 7 6" />

                    </svg>

                    My Profile

                </a>


                <a
                    href="#"
                    class="profile-dropdown-link disabled"
                >

                    <svg viewBox="0 0 24 24">

                        <circle cx="12" cy="12" r="3" />

                        <path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-1.8 1.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V20h-2.5v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1-1.8-1.8.1-.1A1.7 1.7 0 0 0 8.1 15a1.7 1.7 0 0 0-1.5-1H6v-2.5h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1L9 6.7l.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1.9-1.5V5h2.5v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1 1.8 1.8-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.5 1h.1V13h-.1a1.7 1.7 0 0 0-1.5 1z" />

                    </svg>

                    Account Settings

                </a>


                <div class="profile-dropdown-divider">
                </div>


                <a
                    href="/HRIS-Management-System/public/index.php?action=logout"
                    class="profile-dropdown-link profile-logout"
                >

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