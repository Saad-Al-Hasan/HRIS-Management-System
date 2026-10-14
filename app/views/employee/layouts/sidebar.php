
<aside class="admin-sidebar" id="mainSidebar">

    <div class="sidebar-brand">

        <img
            src="/HRIS-Management-System/public/assets/images/myol-logo.jpg"
            alt="MYOL Logo"
        >

        <div class="sidebar-brand-text">
            <div class="sidebar-brand-title">MYOL HRIS</div>
            <div class="sidebar-brand-subtitle">Employee Portal</div>
        </div>

    </div>

    <nav class="sidebar-nav">

        <div class="sidebar-section">

            <div class="sidebar-section-title">MAIN</div>

            <a
                href="/HRIS-Management-System/public/index.php?action=employee-dashboard"
                class="sidebar-link <?= ($_GET['action'] ?? '') === 'employee-dashboard' ? 'active' : '' ?>"
            >
                <span class="sidebar-link-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <rect x="3" y="3" width="7" height="7" rx="1" />
                        <rect x="14" y="3" width="7" height="7" rx="1" />
                        <rect x="3" y="14" width="7" height="7" rx="1" />
                        <rect x="14" y="14" width="7" height="7" rx="1" />
                    </svg>
                </span>

                <span class="sidebar-link-text">Dashboard</span>
            </a>

            <a
                href="/HRIS-Management-System/public/index.php?action=employee-profile"
                class="sidebar-link <?= ($_GET['action'] ?? '') === 'employee-profile' ? 'active' : '' ?>"
            >
                <span class="sidebar-link-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="12" cy="8" r="3" />
                        <path d="M5 21c.5-4 3-6 7-6s6.5 2 7 6" />
                    </svg>
                </span>

                <span class="sidebar-link-text">My Profile</span>
            </a>

        </div>

        <div class="sidebar-section">

            <div class="sidebar-section-title">ACCOUNT</div>

            <a
                href="/HRIS-Management-System/public/index.php?action=logout"
                class="sidebar-link"
            >
                <span class="sidebar-link-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M10 4H5v16h5" />
                        <path d="M14 8l4 4-4 4" />
                        <path d="M8 12h10" />
                    </svg>
                </span>

                <span class="sidebar-link-text">Logout</span>
            </a>

        </div>

    </nav>

    <div class="sidebar-footer">
        <a
            href="https://www.myolbd.com/"
            target="_blank"
            rel="noopener noreferrer"
        >
            MY Outsourcing Limited
        </a>
    </div>

</aside>
