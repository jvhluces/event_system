<?php

session_start();

/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION
|--------------------------------------------------------------------------
| Current file:
| ipt/admin/venues.php
|
| Database:
| ipt/config/db.php
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/event_helpers.php';


/*
|--------------------------------------------------------------------------
| ADMIN AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Admin'
) {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../organizer/notification_center.php';
$notificationCenter = organizer_notification_center($conn, (int) $_SESSION['user_id']);

/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$totalVenueBookings = 0;
$bookedVenues = 0;
$availableVenues = 0;

$venueRows = [];

$errorMessage = '';

/*
|--------------------------------------------------------------------------
| TOTAL VENUE BOOKINGS
|--------------------------------------------------------------------------
|
| Since the current event system stores venue/location inside events,
| we use events.location instead of depending on the old venues table.
|
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT COUNT(*) AS total
    FROM events
    WHERE location IS NOT NULL
    AND TRIM(location) <> ''
";

$result = mysqli_query($conn, $sql);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $totalVenueBookings = (int) ($row['total'] ?? 0);

}


/*
|--------------------------------------------------------------------------
| TOTAL CURRENTLY BOOKED VENUES
|--------------------------------------------------------------------------
|
| Upcoming and Ongoing events are considered currently booked.
|
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT COUNT(DISTINCT TRIM(location)) AS total
    FROM events
    WHERE location IS NOT NULL
    AND TRIM(location) <> ''
    AND status IN ('Upcoming', 'Ongoing')
";

$result = mysqli_query($conn, $sql);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $bookedVenues = (int) ($row['total'] ?? 0);

}


/*
|--------------------------------------------------------------------------
| TOTAL UNIQUE VENUES
|--------------------------------------------------------------------------
*/

$totalUniqueVenues = 0;

$sql = "
    SELECT COUNT(DISTINCT TRIM(location)) AS total
    FROM events
    WHERE location IS NOT NULL
    AND TRIM(location) <> ''
";

$result = mysqli_query($conn, $sql);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $totalUniqueVenues = (int) ($row['total'] ?? 0);

}


/*
|--------------------------------------------------------------------------
| AVAILABLE VENUES
|--------------------------------------------------------------------------
|
| A venue is considered available if it is not currently being used
| by an Upcoming or Ongoing event.
|
|--------------------------------------------------------------------------
*/

$availableVenues = max(
    0,
    $totalUniqueVenues - $bookedVenues
);


/*
|--------------------------------------------------------------------------
| GET EVENT VENUE BOOKINGS
|--------------------------------------------------------------------------
|
| We use the actual structure from the events table:
|
| event_id
| event_name
| event_date
| location
| capacity
| category
| status
| organizer_id
|
| We also get the organizer name from users.
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        e.event_id,
        e.event_name,
        e.event_date,
        e.end_date,
        e.location,
        e.capacity,
        e.category,
        e.status,
        e.approval_status,
        e.organizer_id,
        u.fullname AS organizer_name
    FROM events e
    LEFT JOIN users u
        ON e.organizer_id = u.user_id
    WHERE e.location IS NOT NULL
    AND TRIM(e.location) <> ''
    ORDER BY e.event_date DESC, e.event_id DESC
";

$result = mysqli_query($conn, $sql);

if ($result) {

    while ($row = mysqli_fetch_assoc($result)) {

        $venueRows[] = $row;

    }

} else {

    $errorMessage = mysqli_error($conn);

}


/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function formatDateTime($dateTime)
{
    if (empty($dateTime)) {
        return 'N/A';
    }

    $timestamp = strtotime($dateTime);

    if ($timestamp === false) {
        return e($dateTime);
    }

    return date('M d, Y h:i A', $timestamp);
}


?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Venues | Admin</title>


    <!-- Bootstrap -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
    >


    <!-- Bootstrap Icons -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
    >


    <!-- DataTables -->
    <link
        rel="stylesheet"
        href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css"
    >
    <link rel="stylesheet" href="../assets/css/admin/venues.css">

</head>


<body class="bg-light admin-venues">


<!-- =========================================================
     TOP NAVBAR
========================================================= -->

<?php require __DIR__ . '/sidebar.php'; ?>

<div class="venue-app">
    <header class="venue-topbar">
        <div class="venue-topbar-left">
            <button type="button" class="sidebar-toggle" id="sidebarToggle"
                    aria-label="Open navigation" aria-controls="adminSidebar"
                    aria-expanded="false">
                <i class="bi bi-list"></i>
            </button>
            <div>
                <div class="venue-topbar-title">Venue Management</div>
                <div class="venue-topbar-subtitle">Event locations and booking activity</div>
            </div>
        </div>
        <div class="venue-admin-badge">
            <i class="bi bi-shield-check" aria-hidden="true"></i>
            <span>Administrator</span>
        </div>
    </header>

    <main class="venue-main">


            <!-- PAGE TITLE -->

            <div class="venue-page-heading">
                <div>
                    <span class="venue-eyebrow">EVENT OPERATIONS</span>
                    <h1>Venues</h1>
                    <p>Review event locations, schedules, and current booking status.</p>
                </div>
            </div>


            <!-- =================================================
                 ERROR MESSAGE
            ================================================== -->

            <?php if (!empty($errorMessage)): ?>

                <div class="alert alert-danger">

                    <i class="bi bi-exclamation-triangle"></i>

                    Unable to load venue records.

                    <br>

                    <small>
                        <?= e($errorMessage); ?>
                    </small>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 DASHBOARD CARDS
            ================================================== -->

            <div class="venue-stat-grid">


                <!-- TOTAL VENUE BOOKINGS -->

                <section class="venue-stat-card">

                    <div class="venue-stat-icon venue-stat-blue"><i class="bi bi-geo-alt"></i></div>
                    <div class="venue-stat-copy">
                        <span>Total event bookings</span>
                        <strong><?= number_format($totalVenueBookings); ?></strong>
                        <small>Events with a listed location</small>
                    </div>
                </section>


                <!-- BOOKED VENUES -->

                <section class="venue-stat-card">
                    <div class="venue-stat-icon venue-stat-green"><i class="bi bi-calendar-check"></i></div>
                    <div class="venue-stat-copy">
                        <span>Currently booked</span>
                        <strong><?= number_format($bookedVenues); ?></strong>
                        <small>Unique locations with upcoming or ongoing events</small>
                    </div>
                </section>


                <!-- AVAILABLE VENUES -->

                <section class="venue-stat-card">
                    <div class="venue-stat-icon venue-stat-orange"><i class="bi bi-building-check"></i></div>
                    <div class="venue-stat-copy">
                        <span>Not currently booked</span>
                        <strong><?= number_format($availableVenues); ?></strong>
                        <small>Previously used locations without active events</small>
                    </div>
                </section>
            </div>


            <!-- =================================================
                 VENUE BOOKING TABLE
            ================================================== -->

            <section class="venue-table-card">

                <div class="venue-table-card-header">

                    <div>
                        <h2>Venue bookings</h2>
                        <p>Locations are taken from events currently listed in the system.</p>
                    </div>
                    <span class="venue-record-count">
                        <i class="bi bi-list-check"></i>
                        <?= number_format(count($venueRows)); ?> records
                    </span>
                </div>

                <div class="table-responsive venue-table-wrap">


                        <table
                            id="venueTable"
                            class="table table-striped table-hover"
                        >


                            <thead>

                                <tr>

                                    <th>
                                        Event
                                    </th>

                                    <th>
                                        Venue / Location
                                    </th>

                                    <th>
                                        Capacity
                                    </th>

                                    <th>
                                        Event Date
                                    </th>

                                    <th>
                                        Category
                                    </th>

                                    <th>
                                        Organizer
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th>
                                        Action
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php if (!empty($venueRows)): ?>


                                    <?php foreach ($venueRows as $row): ?>


                                        <tr>


                                            <!-- EVENT -->

                                            <td>

                                                <strong>

                                                    <?= e(
                                                        $row['event_name'] ?? 'Unnamed Event'
                                                    ); ?>

                                                </strong>

                                            </td>


                                            <!-- VENUE / LOCATION -->

                                            <td>

                                                <i class="bi bi-geo-alt text-danger"></i>

                                                <?= e(
                                                    $row['location'] ?? 'No location'
                                                ); ?>

                                            </td>


                                            <!-- CAPACITY -->

                                            <td>

                                                <span class="badge bg-light text-dark border">

                                                    <i class="bi bi-people"></i>

                                                    <?= e(
                                                        $row['capacity'] ?? 'N/A'
                                                    ); ?>

                                                </span>

                                            </td>


                                            <!-- EVENT DATE -->

                                            <td>

                                                <?= formatDateTime(
                                                    $row['event_date'] ?? ''
                                                ); ?>

                                            </td>


                                            <!-- CATEGORY -->

                                            <td>

                                                <?php if (!empty($row['category'])): ?>

                                                    <span class="badge bg-info text-dark">

                                                        <?= e(
                                                            $row['category']
                                                        ); ?>

                                                    </span>

                                                <?php else: ?>

                                                    <span class="text-muted">
                                                        N/A
                                                    </span>

                                                <?php endif; ?>

                                            </td>


                                            <!-- ORGANIZER -->

                                            <td>

                                                <?php if (!empty($row['organizer_name'])): ?>

                                                    <i class="bi bi-person"></i>

                                                    <?= e(
                                                        $row['organizer_name']
                                                    ); ?>

                                                <?php else: ?>

                                                    <span class="text-muted">

                                                        No organizer

                                                    </span>

                                                <?php endif; ?>

                                            </td>


                                            <!-- STATUS -->

                                            <td>

                                                <?= event_lifecycle_badge($row['event_date'] ?? '', $row['end_date'] ?? null); ?>
                                                <div class="mt-1"><?= event_approval_badge($row['approval_status'] ?? 'pending'); ?></div>

                                            </td>


                                            <!-- ACTION -->

                                            <td>

                                                <a
                                                    href="../organizer/view_event.php?id=<?= (int) $row['event_id']; ?>"
                                                    class="btn btn-sm btn-primary"
                                                    title="View Event"
                                                >

                                                    <i class="bi bi-eye"></i>

                                                </a>

                                            </td>


                                        </tr>


                                    <?php endforeach; ?>


                                <?php else: ?>


                                    <tr>

                                        <td
                                            colspan="8"
                                            class="venue-empty-state"
                                        >

                                            <i
                                                class="bi bi-building"
                                                style="font-size: 2rem;"
                                            ></i>

                                            <div class="mt-2">

                                                No event venues to show yet
                                                <div class="venue-empty-help">Add a location to an event to see it listed here.</div>

                                            </div>

                                        </td>

                                    </tr>


                                <?php endif; ?>


                            </tbody>


                        </table>


                    </div>
            </section>


    </main>
</div>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script
    src="https://code.jquery.com/jquery-3.7.0.min.js">
</script>


<script
    src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js">
</script>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js">
</script>


<script src="../assets/js/admin/venues.js"></script>


</body>

</html>