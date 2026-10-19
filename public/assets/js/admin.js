<<<<<<< Updated upstream

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
                } else {
                    rfidIndicator.classList.remove('connected');
                    rfidConnectionText.textContent = 'Not Connected';
                    rfidConnectionDescription.textContent =
                        'Connect the JT308 USB reader.';
                }
            } catch (error) {
                rfidIndicator.classList.remove('connected');
                rfidConnectionText.textContent = 'Status Unavailable';
                rfidConnectionDescription.textContent =
                    'Cannot read reader status. Check the Windows monitor.';

                console.error('RFID status check failed:', error);
            }
        }

        updateRfidStatus();
        setInterval(updateRfidStatus, 2000);
    }
});
=======

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
                } else {
                    rfidIndicator.classList.remove('connected');
                    rfidConnectionText.textContent = 'Not Connected';
                    rfidConnectionDescription.textContent =
                        'Connect the JT308 USB reader.';
                }
            } catch (error) {
                rfidIndicator.classList.remove('connected');
                rfidConnectionText.textContent = 'Status Unavailable';
                rfidConnectionDescription.textContent =
                    'Cannot read reader status. Check the Windows monitor.';

                console.error('RFID status check failed:', error);
            }
        }

        updateRfidStatus();
        setInterval(updateRfidStatus, 2000);
    }
});
>>>>>>> Stashed changes
