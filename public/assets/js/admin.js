
document.addEventListener('DOMContentLoaded', function () {

    // =========================================
    // PROFILE DROPDOWN
    // =========================================

    const userMenu = document.querySelector('.navbar-user-menu');
    const userMenuButton = document.getElementById('userMenuButton');

    if (userMenu && userMenuButton) {

        userMenuButton.addEventListener('click', function (event) {
            event.stopPropagation();

            const isOpen = userMenu.classList.toggle('open');

            userMenuButton.setAttribute(
                'aria-expanded',
                isOpen ? 'true' : 'false'
            );
        });

        document.addEventListener('click', function (event) {
            if (!userMenu.contains(event.target)) {
                userMenu.classList.remove('open');

                userMenuButton.setAttribute(
                    'aria-expanded',
                    'false'
                );
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                userMenu.classList.remove('open');

                userMenuButton.setAttribute(
                    'aria-expanded',
                    'false'
                );
            }
        });
    }

    // =========================================
    // LIVE JT308 RFID READER STATUS
    // =========================================

    const rfidIndicator = document.getElementById('rfid-status-indicator');
    const rfidConnectionText = document.getElementById('rfid-connection-text');
    const rfidConnectionDescription = document.getElementById('rfid-connection-description');

    const activityStatusBadge =
        document.getElementById('activity-feed-status');

    const activityStatusText =
        document.getElementById('activity-feed-status-text');

    function updateActivityBadge(connected) {
        if (!activityStatusBadge || !activityStatusText) return;

        activityStatusBadge.dataset.status = connected ? 'live' : 'offline';
        activityStatusText.textContent = connected ? 'LIVE' : 'OFFLINE';
    }

    if (
        rfidIndicator &&
        rfidConnectionText &&
        rfidConnectionDescription
    ) {
        async function updateRfidStatus() {
            try {
                const response = await fetch(
                    '/HRIS-Management-System/public/rfid-reader-status.json?t=' + Date.now(),
                    { cache: 'no-store' }
                );

                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                const status = await response.json();

                if (status.connected === true) {
                    rfidIndicator.classList.add('connected');
                    rfidConnectionText.textContent = 'Connected';
                    rfidConnectionDescription.textContent =
                        'JT308 USB reader is detected.';
                    updateActivityBadge(true);
                } else {
                    rfidIndicator.classList.remove('connected');
                    rfidConnectionText.textContent = 'Not Connected';
                    rfidConnectionDescription.textContent =
                        'Connect the JT308 USB reader.';
                    updateActivityBadge(false);
                }
            } catch (error) {
                rfidIndicator.classList.remove('connected');
                rfidConnectionText.textContent = 'Status Unavailable';
                rfidConnectionDescription.textContent =
                    'Cannot read reader status. Check the Windows monitor.';
                updateActivityBadge(false);

                console.error('RFID status check failed:', error);
            }
        }

        updateRfidStatus();
        setInterval(updateRfidStatus, 2000);
    }

    // =========================================
    // ATTENDANCE STATION NAVBAR TOGGLE (ALT + H)
    // =========================================

    const attendanceStation = document.querySelector('.attendance-station-page');

    if (attendanceStation) {
        const topNavbar = attendanceStation.querySelector('.admin-topbar');

        if (topNavbar) {
            document.addEventListener('keydown', function (event) {
                if (
                    event.altKey &&
                    event.key.toLowerCase() === 'h' &&
                    !event.ctrlKey &&
                    !event.metaKey
                ) {
                    event.preventDefault();

                    if (topNavbar.classList.contains('attendance-navbar-hidden')) {
                        // Show navbar: fade in while sliding down
                        topNavbar.classList.remove('attendance-navbar-hidden');
                        topNavbar.classList.add('attendance-navbar-showing');

                        setTimeout(() => {
                            topNavbar.classList.remove('attendance-navbar-showing');
                        }, 350);

                    } else {
                        // Hide navbar: fade out while sliding up
                        topNavbar.classList.add('attendance-navbar-hiding');

                        setTimeout(() => {
                            topNavbar.classList.remove('attendance-navbar-hiding');
                            topNavbar.classList.add('attendance-navbar-hidden');
                        }, 350);
                    }
                }
            });
        }
    }


    // =========================================
    // EMPLOYEE ATTENDANCE RFID SCAN HISTORY
    // =========================================

    window.toggleEmployeeScanHistory = function (button) {
        const panelId = button.getAttribute('aria-controls');
        const panel = document.getElementById(panelId);

        if (!panel) return;

        const isExpanded = button.getAttribute('aria-expanded') === 'true';

        panel.hidden = isExpanded;

        button.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');

        button.title = isExpanded
            ? 'View RFID scan history'
            : 'Hide RFID scan history';

        button.innerHTML = isExpanded
            ? `<svg xmlns="http://www.w3.org/2000/svg" width="17" height="17"
            viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
            aria-hidden="true">
            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/>
            <circle cx="12" cy="12" r="3"/>
        </svg>`
            : `<svg xmlns="http://www.w3.org/2000/svg" width="17" height="17"
            viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
            aria-hidden="true">
            <path d="M3 3l18 18"/>
            <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"/>
            <path d="M9.9 5.2A11 11 0 0 1 12 5c6.5 0 10 7 10 7a16 16 0 0 1-4 4.8"/>
            <path d="M6.6 6.6C3.7 8.5 2 12 2 12s3.5 7 10 7a10.7 10.7 0 0 0 4.2-.9"/>
        </svg>`;

    };


    // =========================================
    // LIVE ADMIN DASHBOARD STATISTICS
    // =========================================

    const totalEmployeesCounter =
        document.getElementById('stat-total-employees');

    const inTimeTodayCounter =
        document.getElementById('stat-in-time-today');

    const lateTodayCounter =
        document.getElementById('stat-late-today');

    const absentTodayCounter =
        document.getElementById('stat-absent-today');

    if (
        totalEmployeesCounter &&
        inTimeTodayCounter &&
        lateTodayCounter &&
        absentTodayCounter
    ) {
        const dashboardStatsEndpoint =
            '/HRIS-Management-System/public/index.php?action=dashboard-stats';

        let isRefreshingDashboardStats = false;

        async function refreshDashboardStats() {

            if (isRefreshingDashboardStats) return;

            isRefreshingDashboardStats = true;

            try {
                const response = await fetch(
                    dashboardStatsEndpoint,
                    {
                        method: 'GET',
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: {
                            'Accept': 'application/json'
                        }
                    }
                );

                if (!response.ok) {
                    throw new Error(
                        'Dashboard statistics request failed: ' +
                        response.status
                    );
                }

                const stats = await response.json();

                totalEmployeesCounter.textContent =
                    Number(stats.totalEmployees ?? 0);

                inTimeTodayCounter.textContent =
                    Number(stats.presentToday ?? 0);

                lateTodayCounter.textContent =
                    Number(stats.lateToday ?? 0);

                absentTodayCounter.textContent =
                    Number(stats.absentToday ?? 0);

                // Update Attendance Overview counts as well.
                const overviewPresent =
                    document.getElementById('overview-present-today');

                const overviewLate =
                    document.getElementById('overview-late-today');

                const overviewAbsent =
                    document.getElementById('overview-absent-today');

                if (overviewPresent) {
                    overviewPresent.textContent =
                        Number(stats.presentToday ?? 0);
                }

                if (overviewLate) {
                    overviewLate.textContent =
                        Number(stats.lateToday ?? 0);
                }

                if (overviewAbsent) {
                    overviewAbsent.textContent =
                        Number(stats.absentToday ?? 0);
                }

            } catch (error) {
                console.error(
                    'Could not refresh dashboard statistics:',
                    error
                );
            } finally {
                isRefreshingDashboardStats = false;
            }
        }

        // Update immediately and repeat every 3 seconds.
        refreshDashboardStats();

        setInterval(refreshDashboardStats, 3000);
    }

});
