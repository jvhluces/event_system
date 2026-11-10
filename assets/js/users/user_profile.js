/* =========================================================
   MOBILE SIDEBAR
========================================================= */

const sidebar =
    document.getElementById("sidebar");

const sidebarToggle =
    document.getElementById("sidebarToggle");

const sidebarOverlay =
    document.getElementById("sidebarOverlay");


if (sidebarToggle) {

    sidebarToggle.addEventListener(
        "click",
        function () {

            sidebar.classList.toggle("show");

            sidebarOverlay.classList.toggle("show");

        }
    );

}


if (sidebarOverlay) {

    sidebarOverlay.addEventListener(
        "click",
        function () {

            sidebar.classList.remove("show");

            sidebarOverlay.classList.remove("show");

        }
    );

}


/* =========================================================
   IMAGE PREVIEW
========================================================= */

const profilePicInput =
    document.getElementById(
        "profilePicInput"
    );

const profilePreview =
    document.getElementById(
        "profilePreview"
    );


if (
    profilePicInput &&
    profilePreview
) {

    profilePicInput.addEventListener(
        "change",
        function () {

            const file =
                this.files[0];

            if (!file) {
                return;
            }

            const allowedTypes = [

                "image/jpeg",
                "image/png",
                "image/gif",
                "image/webp"

            ];


            if (
                !allowedTypes.includes(
                    file.type
                )
            ) {

                alert(
                    "Please select a JPG, PNG, GIF, or WEBP image."
                );

                this.value = "";

                return;

            }


            if (
                file.size >
                5 * 1024 * 1024
            ) {

                alert(
                    "The selected image is larger than 5MB."
                );

                this.value = "";

                return;

            }


            const reader =
                new FileReader();


            reader.onload =
                function (event) {

                    profilePreview.src =
                        event.target.result;

                };


            reader.readAsDataURL(file);

        }
    );

}