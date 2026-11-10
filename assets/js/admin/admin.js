$(document).ready(function () {


    $('#organizerTable').DataTable({
        pageLength: 10,
        order: [[0, 'asc']]
    });



    $('#audienceTable').DataTable({
        pageLength: 10,
        order: [[0, 'asc']]
    });

});
function confirmLogout()
{
    return confirm(
        "Are you sure you want to logout?\n\n" +
        "You will need to sign in again to access the Admin Panel."
    );
}