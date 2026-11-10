function toggleSidebar(show) {

    const sidebar =
        document.getElementById('sidebar');

    const overlay =
        document.getElementById('overlay');


    if (show) {

        sidebar.classList.add('show');
        overlay.classList.add('show');

    } else {

        sidebar.classList.remove('show');
        overlay.classList.remove('show');

    }
}


/* =====================================================
   CLOSE MOBILE SIDEBAR AFTER CLICKING LINK
===================================================== */

document.querySelectorAll('.side-link').forEach(function(link) {

    link.addEventListener('click', function() {

        if (window.innerWidth <= 991) {

            toggleSidebar(false);

        }

    });

});
