let timer;

const locationInput =
    document.getElementById("location");

const suggestionsBox =
    document.getElementById("suggestions");


locationInput.addEventListener(
    "input",
    function() {

        clearTimeout(timer);

        let query = this.value;

        if (query.length > 2) {

            timer = setTimeout(() => {

                fetch(
                    "https://photon.komoot.io/api/?q="
                    + encodeURIComponent(query)
                    + "&limit=5"
                )

                .then(response =>
                    response.json()
                )

                .then(data => {

                    let suggestions = "";

                    if (
                        data.features &&
                        data.features.length > 0
                    ) {

                        data.features.forEach(place => {

                            let props =
                                place.properties;

                            let display =
                                props.name || "";

                            if (props.city) {
                                display +=
                                    ", " +
                                    props.city;
                            }

                            if (props.country) {
                                display +=
                                    ", " +
                                    props.country;
                            }

                            const escaped =
                                display
                                .replace(/'/g, "\\'");

                            suggestions +=
                                "<div style='padding:8px;cursor:pointer;border-bottom:1px solid #ddd;' " +
                                "onclick=\"locationInput.value='" +
                                escaped +
                                "'; suggestionsBox.innerHTML='';\">" +
                                display +
                                "</div>";
                        });

                    } else {

                        suggestions =
                            "<div style='padding:8px;color:#888;'>" +
                            "No matches found" +
                            "</div>";
                    }

                    suggestionsBox.innerHTML =
                        suggestions;

                })

                .catch(() => {

                    suggestionsBox.innerHTML =
                        "<div style='padding:8px;color:red;'>" +
                        "Error loading suggestions" +
                        "</div>";

                });

            }, 400);

        } else {

            suggestionsBox.innerHTML = "";

        }

    }
);


document.addEventListener(
    "click",
    function(e) {

        if (
            e.target !== locationInput &&
            !suggestionsBox.contains(e.target)
        ) {

            suggestionsBox.innerHTML = "";

        }

    }
);
