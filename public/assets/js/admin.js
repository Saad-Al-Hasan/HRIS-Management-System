document.addEventListener('DOMContentLoaded', function () {

    const userMenu = document.querySelector('.navbar-user-menu');
    const userMenuButton = document.getElementById('userMenuButton');

    if (!userMenu || !userMenuButton) {
        return;
    }


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

});