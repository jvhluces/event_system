$(document).ready(function () {

    $('#venueTable').DataTable({

        pageLength: 10,

        order: [
            [3, 'desc']
        ],

        language: {

            search: "Search venue/event:",

            lengthMenu: "Show _MENU_ entries",

            info: "Showing _START_ to _END_ of _TOTAL_ venue bookings",

            emptyTable: "No event venues to show yet. Add a location to an event to see it listed here."

        }

    });

});
