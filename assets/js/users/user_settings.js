/* =========================================================
   LOGOUT VALIDATION
========================================================= */

function confirmLogout() {

    return confirm(
        "Are you sure you want to logout from QC Event System?"
    );

}


/* =========================================================
   PASSWORD VALIDATION
========================================================= */

function validatePassword() {

    const currentPassword =
        document.getElementById('current_password').value;

    const newPassword =
        document.getElementById('new_password').value;

    const confirmPassword =
        document.getElementById('confirm_password').value;


    if (
        currentPassword === '' ||
        newPassword === '' ||
        confirmPassword === ''
    ) {

        alert(
            "Please complete all password fields."
        );

        return false;
    }


    if (newPassword.length < 6) {

        alert(
            "New password must be at least 6 characters."
        );

        return false;
    }


    if (newPassword !== confirmPassword) {

        alert(
            "New passwords do not match."
        );

        return false;
    }


    if (currentPassword === newPassword) {

        alert(
            "Your new password must be different from your current password."
        );

        return false;
    }


    return true;
}


/* =========================================================
   DEACTIVATE VALIDATION
========================================================= */

function confirmDeactivate() {

    return confirm(
        "Are you sure you want to deactivate your account?\n\n" +
        "Your account will be set to Inactive and you will be logged out.\n\n" +
        "You will not be able to login until your account is reactivated."
    );

}


/* =========================================================
   DELETE VALIDATION
========================================================= */

function confirmDelete() {

    return confirm(
        "WARNING!\n\n" +
        "This will permanently delete your account.\n\n" +
        "Your joined events and reviews will also be removed.\n\n" +
        "This action cannot be undone.\n\n" +
        "Are you sure you want to continue?"
    );

}


/* =========================================================
   SIDEBAR ELEMENTS
========================================================= */

const sidebar =
    document.getElementById('sidebar');

const sidebarToggle =
    document.getElementById('sidebarToggle');

const sidebarOverlay =
    document.getElementById('sidebarOverlay');


/* =========================================================
   OPEN SIDEBAR
========================================================= */

function openSidebar() {

    if (!sidebar) return;

    sidebar.classList.add('show');


    if (sidebarOverlay) {

        sidebarOverlay.classList.add('show');
    }


    if (sidebarToggle) {

        sidebarToggle.setAttribute(
            'aria-expanded',
            'true'
        );
    }


    document.body.style.overflow = 'hidden';
}


/* =========================================================
   CLOSE SIDEBAR
========================================================= */

function closeSidebar() {

    if (!sidebar) return;

    sidebar.classList.remove('show');


    if (sidebarOverlay) {

        sidebarOverlay.classList.remove('show');
    }


    if (sidebarToggle) {

        sidebarToggle.setAttribute(
            'aria-expanded',
            'false'
        );
    }


    document.body.style.overflow = '';
}


/* =========================================================
   BURGER BUTTON
========================================================= */

if (sidebarToggle) {

    sidebarToggle.addEventListener(
        'click',
        function () {

            if (
                sidebar &&
                sidebar.classList.contains('show')
            ) {

                closeSidebar();

            } else {

                openSidebar();

            }

        }
    );

}


/* =========================================================
   CLICK OVERLAY
========================================================= */

if (sidebarOverlay) {

    sidebarOverlay.addEventListener(
        'click',
        function () {

            closeSidebar();

        }
    );

}


/* =========================================================
   CLOSE WHEN CLICKING SIDEBAR LINK ON MOBILE
========================================================= */

const sidebarLinks =
    document.querySelectorAll(
        '.sidebar-menu a, .logout-section a'
    );


sidebarLinks.forEach(
    function(link) {

        link.addEventListener(
            'click',
            function() {

                if (
                    window.innerWidth <= 900
                ) {

                    closeSidebar();

                }

            }
        );

    }
);


/* =========================================================
   ESC KEY
========================================================= */

document.addEventListener(
    'keydown',
    function(event) {

        if (
            event.key === 'Escape'
        ) {

            closeSidebar();

        }

    }
);


/* =========================================================
   SIDEBAR SCROLL DIVIDER
========================================================= */

let sidebarScrollTimer = null;


if (sidebar) {

    sidebar.addEventListener(
        'scroll',
        function() {

            sidebar.classList.add(
                'is-scrolling'
            );


            clearTimeout(
                sidebarScrollTimer
            );


            sidebarScrollTimer =
                setTimeout(
                    function() {

                        sidebar.classList.remove(
                            'is-scrolling'
                        );

                    },
                    600
                );

        }
    );

}


/* =========================================================
   RESET SIDEBAR WHEN RETURNING TO DESKTOP
========================================================= */

window.addEventListener(
    'resize',
    function() {

        if (
            window.innerWidth > 900
        ) {

            closeSidebar();

        }

    }
);


/* =========================================================
   PREVENT BODY LOCK
========================================================= */

window.addEventListener(
    'load',
    function() {

        if (
            window.innerWidth > 900
        ) {

            document.body.style.overflow = '';

        }

    }
);
