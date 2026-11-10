


function isMobile()
{
    return window.innerWidth <= 991;
}


function openSidebar()
{
    if (!isMobile()) {
        return;
    }

    document
        .getElementById("sidebar")
        .classList
        .add("active");

    document
        .getElementById("overlay")
        .classList
        .add("active");

    document.body.style.overflow = "hidden";
}


function closeSidebar()
{
    if (!isMobile()) {
        return;
    }

    document
        .getElementById("sidebar")
        .classList
        .remove("active");

    document
        .getElementById("overlay")
        .classList
        .remove("active");

    document.body.style.overflow = "";
}


function toggleSidebar()
{
    if (!isMobile()) {
        return;
    }

    const sidebar =
        document.getElementById("sidebar");

    if (
        sidebar.classList.contains("active")
    ) {

        closeSidebar();

    } else {

        openSidebar();
    }
}



function showSection(
    sectionId,
    clickedElement
)
{

    const sections =
        document.querySelectorAll(
            ".dashboard-section"
        );


    sections.forEach(
        function(section)
        {

            section.style.display =
                "none";

        }
    );


    const selected =
        document.getElementById(
            sectionId
        );


    if (selected) {

        selected.style.display =
            "block";
    }


    const menuLinks =
        document.querySelectorAll(
            ".sidebar-menu a"
        );


    menuLinks.forEach(
        function(link)
        {

            link.classList.remove(
                "active"
            );

        }
    );


    if (clickedElement) {

        clickedElement.classList.add(
            "active"
        );
    }


    if (isMobile()) {

        closeSidebar();
    }


    window.scrollTo({

        top: 0,

        behavior: "smooth"

    });

}


document.addEventListener(
    "DOMContentLoaded",
    function()
    {

        const sidebarLinks =
            document.querySelectorAll(
                ".sidebar-menu a"
            );

        sidebarLinks.forEach(
            function(link)
            {

                link.addEventListener(
                    "click",
                    function()
                    {

                        if (isMobile()) {

                            closeSidebar();

                        }

                    }
                );

            }
        );

    }
);



window.addEventListener(
    "resize",
    function()
    {

        if (!isMobile()) {

            document
                .getElementById("sidebar")
                .classList
                .remove("active");


            document
                .getElementById("overlay")
                .classList
                .remove("active");


            document.body.style.overflow = "";

        }

    }
);





const eventSearchInput =
    document.getElementById(
        "eventSearchInput"
    );


const searchResults =
    document.getElementById(
        "searchResults"
    );


const clearSearchBtn =
    document.getElementById(
        "clearSearchBtn"
    );



function escapeHtml(text)
{

    const div =
        document.createElement(
            "div"
        );


    div.textContent =
        text ?? "";


    return div.innerHTML;
}



function getStatusClass(status)
{

    if (status === "Upcoming") {

        return "status-upcoming";
    }


    if (status === "Ongoing") {

        return "status-ongoing";
    }


    if (status === "Ended") {

        return "status-ended";
    }


    return "status-ended";
}


function formatEventDate(
    dateString
)
{

    if (!dateString) {

        return "No date";
    }


    const date =
        new Date(dateString);


    if (isNaN(date.getTime())) {

        return dateString;
    }


    return date.toLocaleDateString(
        "en-US",
        {
            year: "numeric",
            month: "long",
            day: "numeric"
        }
    );
}



function performEventSearch()
{

    const keyword =
        eventSearchInput.value
        .trim()
        .toLowerCase();


    /* Empty search */

    if (keyword === "") {

        searchResults.innerHTML = "";

        searchResults.classList.remove(
            "show"
        );

        clearSearchBtn.style.display =
            "none";

        return;
    }


    clearSearchBtn.style.display =
        "block";


    

    const matches =
        organizerEvents.filter(
            function(event)
            {

                const eventName =
                    String(
                        event.event_name || ""
                    ).toLowerCase();


                const category =
                    String(
                        event.category || ""
                    ).toLowerCase();


                const location =
                    String(
                        event.location || ""
                    ).toLowerCase();


                const status =
                    String(
                        event.status || ""
                    ).toLowerCase();


                return (

                    eventName.includes(
                        keyword
                    )

                    ||

                    category.includes(
                        keyword
                    )

                    ||

                    location.includes(
                        keyword
                    )

                    ||

                    status.includes(
                        keyword
                    )

                );

            }
        );



    if (matches.length === 0) {

        searchResults.innerHTML = `

            <div class="search-empty">

                <i class="bi bi-search"></i>

                <strong>
                    No events found
                </strong>

                <div class="small mt-1">

                    No event matches
                    "<strong>
                        ${escapeHtml(
                            eventSearchInput.value
                        )}
                    </strong>"

                </div>

            </div>

        `;


        searchResults.classList.add(
            "show"
        );

        return;
    }



    let html = `

        <div class="search-result-count">

            <i class="bi bi-check-circle text-success"></i>

            Found

            <strong>
                ${matches.length}
            </strong>

            event(s)

        </div>

    `;


    matches.forEach(
        function(event)
        {

            const statusClass =
                getStatusClass(
                    event.status
                );


            html += `

                <div
                    class="search-result-item"
                >

                    <div
                        class="search-result-main"
                    >

                        <div
                            class="search-result-info"
                        >

                            <div
                                class="search-result-title"
                            >

                                <i
                                    class="
                                        bi
                                        bi-calendar-event
                                        text-primary
                                    "
                                ></i>

                                ${escapeHtml(
                                    event.event_name
                                )}

                            </div>


                            <div
                                class="search-result-details"
                            >

                                <span>

                                    <i
                                        class="
                                            bi
                                            bi-tag
                                        "
                                    ></i>

                                    ${escapeHtml(
                                        event.category
                                    )}

                                </span>


                                <span>

                                    <i
                                        class="
                                            bi
                                            bi-calendar3
                                        "
                                    ></i>

                                    ${escapeHtml(
                                        formatEventDate(
                                            event.event_date
                                        )
                                    )}

                                </span>


                                <span>

                                    <i
                                        class="
                                            bi
                                            bi-geo-alt
                                        "
                                    ></i>

                                    ${escapeHtml(
                                        event.location
                                    )}

                                </span>

                            </div>


                            <span
                                class="
                                    search-result-status
                                    ${statusClass}
                                "
                            >

                                ${escapeHtml(
                                    event.status
                                )}

                            </span>

                        </div>


                        <a
                            href="category_events.php?category=${encodeURIComponent(
                                event.category
                            )}"
                            class="
                                btn
                                btn-sm
                                btn-outline-primary
                            "
                        >

                            <i
                                class="bi bi-eye"
                            ></i>

                            View Event

                        </a>


                    </div>

                </div>

            `;

        }
    );


    searchResults.innerHTML =
        html;


    searchResults.classList.add(
        "show"
    );

}


if (eventSearchInput) {

    eventSearchInput.addEventListener(
        "input",
        performEventSearch
    );
}


if (clearSearchBtn) {

    clearSearchBtn.addEventListener(
        "click",
        function()
        {

            eventSearchInput.value = "";

            searchResults.innerHTML = "";

            searchResults.classList.remove(
                "show"
            );

            clearSearchBtn.style.display =
                "none";

            eventSearchInput.focus();

        }
    );

}



document.addEventListener(
    "keydown",
    function(event)
    {

        if (
            event.key === "Escape" &&
            isMobile()
        ) {

            closeSidebar();

        }

    }
);




function showSection(sectionId, clickedLink) {

    /*
    |--------------------------------------------------------------------------
    | HIDE ALL DASHBOARD SECTIONS
    |--------------------------------------------------------------------------
    */

    const sections = document.querySelectorAll('.dashboard-section');

    sections.forEach(function(section) {

        section.style.display = 'none';

    });


    /*
    |--------------------------------------------------------------------------
    | SHOW SELECTED SECTION
    |--------------------------------------------------------------------------
    */

    const selectedSection =
        document.getElementById(sectionId);

    if (selectedSection) {

        selectedSection.style.display = 'block';

    }


    /*
    |--------------------------------------------------------------------------
    | REMOVE ACTIVE FROM ALL SIDEBAR LINKS
    |--------------------------------------------------------------------------
    */

    const sidebarLinks =
        document.querySelectorAll('.sidebar-menu a');

    sidebarLinks.forEach(function(link) {

        link.classList.remove('active');

    });


    /*
    |--------------------------------------------------------------------------
    | ADD ACTIVE TO THE CLICKED LINK
    |--------------------------------------------------------------------------
    */

    if (clickedLink) {

        clickedLink.classList.add('active');

    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE URL HASH
    |--------------------------------------------------------------------------
    */

    history.replaceState(
        null,
        '',
        '#' + sectionId
    );


    /*
    |--------------------------------------------------------------------------
    | SCROLL MAIN CONTENT TO TOP
    |--------------------------------------------------------------------------
    */

    const mainContent =
        document.querySelector('.main-content');

    if (mainContent) {

        mainContent.scrollTo({
            top: 0,
            behavior: 'smooth'
        });

    }


    /*
    |--------------------------------------------------------------------------
    | CLOSE MOBILE SIDEBAR
    |--------------------------------------------------------------------------
    */

    if (window.innerWidth <= 768) {

        if (typeof closeSidebar === 'function') {

            closeSidebar();

        }

    }

}