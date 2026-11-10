 const sidebar =
        document.getElementById('sidebar');

    const overlay =
        document.getElementById('overlay');


    function toggleSidebar()
    {
        sidebar.classList.toggle('open');

        overlay.classList.toggle('show');
    }


    function closeSidebar()
    {
        sidebar.classList.remove('open');

        overlay.classList.remove('show');
    }

 

    document
        .querySelectorAll('.sidebar-menu a')
        .forEach(function(link) {

            link.addEventListener(
                'click',
                function() {

                    if (
                        window.innerWidth <= 991.98
                    ) {

                        closeSidebar();
                    }

                }
            );

        });


    

    window.addEventListener(
        'resize',
        function() {

            if (
                window.innerWidth > 991.98
            ) {

                closeSidebar();
            }

        }
    );