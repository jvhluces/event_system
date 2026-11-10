$(document).ready(function () {

    $('#audienceTable').DataTable({

        pageLength: 10,

        order: [
            [8, 'desc']
        ],

        responsive: true,

        language: {

            search: "Search audience:",

            lengthMenu: "Show _MENU_ participants",

            info: "Showing _START_ to _END_ of _TOTAL_ participants",

            infoEmpty: "No participants found",

            emptyTable: "No audience has joined any event yet."

        }

    });

});