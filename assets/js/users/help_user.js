document.addEventListener(
    "DOMContentLoaded",
    function () {


        const sidebar =
            document.getElementById(
                "sidebar"
            );


        const sidebarToggle =
            document.getElementById(
                "sidebarToggle"
            );


        const sidebarOverlay =
            document.getElementById(
                "sidebarOverlay"
            );


        function isMobile() {

            return window.innerWidth <= 1000;

        }


        function openSidebar() {

            if (!isMobile()) {
                return;
            }


            sidebar.classList.add("show");

            sidebarOverlay.classList.add("show");

            document.body.style.overflow =
                "hidden";

        }


        function closeSidebar() {

            if (!isMobile()) {
                return;
            }


            sidebar.classList.remove("show");

            sidebarOverlay.classList.remove("show");

            document.body.style.overflow = "";

        }


        sidebarToggle.addEventListener(
            "click",
            function () {

                if (
                    sidebar.classList.contains("show")
                ) {

                    closeSidebar();

                } else {

                    openSidebar();

                }

            }
        );


        sidebarOverlay.addEventListener(
            "click",
            function () {

                closeSidebar();

            }
        );


        window.addEventListener(
            "resize",
            function () {

                if (!isMobile()) {

                    sidebar.classList.remove(
                        "show"
                    );

                    sidebarOverlay.classList.remove(
                        "show"
                    );

                    document.body.style.overflow =
                        "";

                }

            }
        );


    }
);


document.addEventListener(
    "DOMContentLoaded",
    function () {


        const searchInput =
            document.getElementById(
                "helpSearch"
            );


        const clearButton =
            document.getElementById(
                "clearHelpSearch"
            );


        const resultInfo =
            document.getElementById(
                "searchResultInfo"
            );


        const noResults =
            document.getElementById(
                "noHelpResults"
            );


        const searchableSections =
            document.querySelectorAll(
                ".searchable-section"
            );


        const quickCards =
            document.querySelectorAll(
                ".quick-help-card"
            );


        function normalize(text) {

            return text
                .toLowerCase()
                .replace(/\s+/g, " ")
                .trim();

        }


        function searchHelp() {


            const keyword =
                normalize(
                    searchInput.value
                );


            let visibleSections = 0;


            if (keyword === "") {

                searchableSections.forEach(
                    function (section) {

                        section.style.display =
                            "";

                    }
                );


                noResults.style.display =
                    "none";


                resultInfo.classList.remove(
                    "show"
                );


                resultInfo.innerHTML = "";

                clearButton.style.display =
                    "none";


                return;

            }


            clearButton.style.display =
                "block";


            searchableSections.forEach(
                function (section) {


                    const content =
                        normalize(
                            (
                                section.innerText +
                                " " +
                                (
                                    section.dataset
                                        .searchContent ||
                                    ""
                                )
                            )
                        );


                    if (
                        content.includes(keyword)
                    ) {

                        section.style.display =
                            "";

                        visibleSections++;

                    } else {

                        section.style.display =
                            "none";

                    }

                }
            );


            if (visibleSections === 0) {

                noResults.style.display =
                    "block";

            } else {

                noResults.style.display =
                    "none";

            }


            resultInfo.classList.add(
                "show"
            );


            resultInfo.innerHTML =

                '<i class="bi bi-search"></i> ' +

                'Found <strong>' +

                visibleSections +

                '</strong> help section(s) ' +

                'for <strong>"' +

                escapeHtml(
                    searchInput.value
                ) +

                '"</strong>.';


        }


        function escapeHtml(text) {

            const div =
                document.createElement(
                    "div"
                );

            div.textContent =
                text;

            return div.innerHTML;

        }


        searchInput.addEventListener(
            "input",
            function () {

                searchHelp();

            }
        );


        clearButton.addEventListener(
            "click",
            function () {

                searchInput.value = "";

                searchHelp();

                searchInput.focus();

            }
        );


        /* ======================================================
           QUICK HELP CARDS
        ====================================================== */

        quickCards.forEach(
            function (card) {

                card.addEventListener(
                    "click",
                    function () {


                        const targetId =
                            this.dataset.target;


                        const target =
                            document.getElementById(
                                targetId
                            );


                        if (!target) {
                            return;
                        }


                        /* Clear search */

                        searchInput.value = "";

                        searchHelp();


                        /* Scroll */

                        target.scrollIntoView({

                            behavior: "smooth",

                            block: "start"

                        });


                        /* Highlight */

                        target.style.transition =
                            "box-shadow 0.3s ease";


                        target.style.boxShadow =
                            "0 0 0 3px rgba(13,110,253,0.15)";


                        setTimeout(
                            function () {

                                target.style.boxShadow =
                                    "";

                            },
                            1200
                        );

                    }
                );

            }
        );


    }
);