
    function toggleSidebar() {

        const sidebar =
            document.getElementById('sidebar');

        const overlay =
            document.getElementById('sidebarOverlay');

        sidebar.classList.toggle('open');

        overlay.classList.toggle('show');
    }


    function closeSidebar() {

        const sidebar =
            document.getElementById('sidebar');

        const overlay =
            document.getElementById('sidebarOverlay');

        sidebar.classList.remove('open');

        overlay.classList.remove('show');
    }


    /* Close mobile sidebar when clicking a link */
    document.querySelectorAll('.sidebar-menu a').forEach(function(link) {

        link.addEventListener('click', function() {

            if (window.innerWidth <= 991) {

                closeSidebar();

            }

        });

    });