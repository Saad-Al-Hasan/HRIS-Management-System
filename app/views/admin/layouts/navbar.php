<?php

require_once __DIR__ . '/../../../core/Auth.php';

Auth::startSession();

$roleId = Auth::roleId();
$isEmployee = ($roleId === 2);

$pageTitle = $pageTitle ?? 'Dashboard';

$displayName = Auth::fullName() ?? 'User';
$displayRole = $isEmployee ? 'EMPLOYEE' : 'ADMINISTRATOR';

$avatarPath = $isEmployee
    ? '/HRIS-Management-System/public/assets/images/employee-avatar.png'
    : '/HRIS-Management-System/public/assets/images/admin-avatar.png';

?>

<header class="admin-topbar">

    <div class="floating-navbar">
        <span class="navbar-border-light" aria-hidden="true"></span>
        <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"
            aria-controls="mainSidebar" aria-expanded="true" title="Toggle sidebar">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>

        <!-- Current Page -->
        <div class="navbar-page-info">
            <h2><?= htmlspecialchars($pageTitle) ?></h2>
            <span>HRIS Management System</span>
        </div>

        <!-- Right Side -->
        <div class="navbar-right">

            <!-- Notifications -->
            <button type="button" class="navbar-icon-btn" aria-label="Notifications" title="Notifications">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" />
                    <path d="M10 21h4" />
                </svg>
            </button>

            <!-- User Dropdown -->
            <div class="navbar-user">

                <button type="button" class="navbar-user-toggle" id="navbarUserToggle" aria-expanded="false"
                    aria-controls="navbarUserDropdown">

                    <img src="<?= htmlspecialchars($avatarPath) ?>" alt="<?= htmlspecialchars($displayRole) ?>"
                        class="navbar-avatar"
                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">

                    <span class="navbar-avatar-fallback">
                        <?= htmlspecialchars(strtoupper(substr($displayName, 0, 1))) ?>
                    </span>

                    <span class="navbar-user-info">
                        <strong>
                            <?= htmlspecialchars($displayName) ?>
                        </strong>

                        <small>
                            <?= htmlspecialchars($displayRole) ?>
                        </small>
                    </span>

                    <svg class="navbar-chevron" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="m7 10 5 5 5-5" />
                    </svg>

                </button>

                <div class="navbar-dropdown" id="navbarUserDropdown" hidden>

                    <div class="navbar-dropdown-header">
                        <strong><?= htmlspecialchars($displayName) ?></strong>
                        <small><?= htmlspecialchars($displayRole) ?></small>
                    </div>

                    <a href="/HRIS-Management-System/public/index.php?action=<?= $isEmployee ? 'employee-profile' : 'profile' ?>"
                        class="navbar-dropdown-link">
                        <span>My Profile</span>
                    </a>

                    <?php if (!$isEmployee): ?>
                        <a href="#" class="navbar-dropdown-link disabled" aria-disabled="true">
                            <span>Account Settings</span>
                        </a>
                    <?php endif; ?>

                    <div class="navbar-dropdown-divider"></div>

                    <a href="/HRIS-Management-System/public/index.php?action=logout"
                        class="navbar-dropdown-link navbar-logout-link">
                        <span>Logout</span>
                    </a>

                </div>

            </div>

        </div>

    </div>

</header>

<script>
    (function () {
        const toggle = document.getElementById('navbarUserToggle');
        const dropdown = document.getElementById('navbarUserDropdown');

        // Existing profile dropdown behavior
        if (toggle && dropdown) {
            toggle.addEventListener('click', function (event) {
                event.stopPropagation();

                const isOpen = !dropdown.hidden;
                dropdown.hidden = isOpen;
                toggle.setAttribute('aria-expanded', String(!isOpen));
            });

            document.addEventListener('click', function (event) {
                if (!event.target.closest('.navbar-user')) {
                    dropdown.hidden = true;
                    toggle.setAttribute('aria-expanded', 'false');
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    dropdown.hidden = true;
                    toggle.setAttribute('aria-expanded', 'false');
                    toggle.focus();
                }
            });
        }

        // Sidebar toggle behavior
        const sidebarToggle = document.getElementById('sidebarToggle');
        const layout = document.querySelector('.admin-layout');

        if (sidebarToggle && layout) {
            const storageKey = 'hris-sidebar-collapsed';

            function updateSidebar(collapsed) {
                layout.classList.toggle('sidebar-collapsed', collapsed);
                sidebarToggle.setAttribute('aria-expanded', String(!collapsed));
                sidebarToggle.setAttribute(
                    'aria-label',
                    collapsed ? 'Show sidebar' : 'Hide sidebar'
                );
                sidebarToggle.title = collapsed ? 'Show sidebar' : 'Hide sidebar';
            }

            try {
                updateSidebar(localStorage.getItem(storageKey) === 'true');
            } catch (error) {
                updateSidebar(false);
            }

            sidebarToggle.addEventListener('click', function () {
                const collapsed = !layout.classList.contains('sidebar-collapsed');
                updateSidebar(collapsed);

                try {
                    localStorage.setItem(storageKey, String(collapsed));
                } catch (error) {
                    // Sidebar still works if browser storage is unavailable.
                }
            });
        }
    })();
</script>