<?php
session_start();

/* =========================================================
   DATABASE CONNECTION
========================================================= */
require_once __DIR__ . '/../config/db.php';


/* =========================================================
   ADMIN AUTHENTICATION
========================================================= */
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

/* =========================================================
   HELPER
========================================================= */
function e($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================================
   DASHBOARD COUNTS
========================================================= */

/* ---------------------------------------------------------
   TOTAL JOIN RECORDS
--------------------------------------------------------- */
$totalJoined = 0;

$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM event_participants"
);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalJoined = (int)($row['total'] ?? 0);
}


/* ---------------------------------------------------------
   UNIQUE AUDIENCE
--------------------------------------------------------- */
$totalAudience = 0;

$result = mysqli_query(
    $conn,
    "SELECT COUNT(DISTINCT user_id) AS total
     FROM event_participants"
);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalAudience = (int)($row['total'] ?? 0);
}


/* ---------------------------------------------------------
   EVENTS WITH AUDIENCE
--------------------------------------------------------- */
$eventsWithAudience = 0;

$result = mysqli_query(
    $conn,
    "SELECT COUNT(DISTINCT event_id) AS total
     FROM event_participants"
);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    $eventsWithAudience = (int)($row['total'] ?? 0);
}


/* =========================================================
   PARTICIPANT LIST
========================================================= */

$participants = [];

$sql = "
    SELECT
        ep.participation_id,
        ep.event_id,
        ep.user_id,
        ep.joined_at,

        e.event_name,
        e.event_date,
        e.location,
        e.category,
        e.status AS event_status,

        u.firstname,
        u.lastname,
        u.email,
        u.contact,
        u.address

    FROM event_participants ep

    INNER JOIN events e
        ON ep.event_id = e.event_id

    INNER JOIN users u
        ON ep.user_id = u.user_id

    WHERE u.role = 'Audience'

    ORDER BY ep.joined_at DESC
";

$result = mysqli_query($conn, $sql);

if ($result) {

    while ($row = mysqli_fetch_assoc($result)) {

        $participants[] = $row;

    }

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

    <title>Event Audience | Event System</title>

    <link
        rel="stylesheet"href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >


    <!-- =====================================================
         DATATABLES
    ====================================================== -->

    <link
        rel="stylesheet"
        href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css"
    >


    <link rel="stylesheet" href="../assets/css/admin/event_audience.css">

</head>


<body>



<?php require __DIR__ . '/sidebar.php'; ?>


<!-- =========================================================
     MAIN AREA
========================================================= -->

<div class="main-area">


    <!-- =====================================================
         TOPBAR
    ====================================================== -->

    <header class="topbar">


        <div class="d-flex align-items-center gap-3">


            <!-- MOBILE MENU -->

            <button
                type="button"
                class="mobile-menu-btn"
                onclick="toggleSidebar()"
            >

                <i class="bi bi-list"></i>

            </button>


            <div>

                <h1 class="topbar-title">
                    Event Audience
                </h1>

                <div class="topbar-subtitle">
                    Event System Management
                </div>

            </div>

        </div>


        <!-- ADMIN INDICATOR -->

        <div class="admin-indicator">

            <i class="bi bi-shield-check"></i>

            <span>
                Administrator
            </span>

        </div>


    </header>


    <!-- =====================================================
         CONTENT AREA
    ====================================================== -->

    <main class="content-area">


        <!-- PAGE HEADER -->

        <div class="content-heading">

            <div>

                <h1>
                    Event Audience Overview
                </h1>

                <p>
                    Monitor audience participation and event attendance records.
                </p>

            </div>

        </div>


        <!-- =================================================
             STAT CARDS
        ================================================== -->

        <div class="row g-4">


            <!-- TOTAL JOINED -->

            <div class="col-xl-4 col-md-6">

                <div class="stat-card">

                    <div class="stat-card-body">

                        <div>

                            <div class="stat-label">
                                Total Join Records
                            </div>

                            <div
                                class="stat-value"
                                style="color:#2563eb;"
                            >
                                <?= number_format($totalJoined); ?>
                            </div>

                        </div>

                        <div class="stat-icon blue">

                            <i class="bi bi-person-check-fill"></i>

                        </div>

                    </div>

                </div>

            </div>


            <!-- UNIQUE AUDIENCE -->

            <div class="col-xl-4 col-md-6">

                <div class="stat-card">

                    <div class="stat-card-body">

                        <div>

                            <div class="stat-label">
                                Registered Audience
                            </div>

                            <div
                                class="stat-value"
                                style="color:#059669;"
                            >
                                <?= number_format($totalAudience); ?>
                            </div>

                        </div>

                        <div class="stat-icon green">

                            <i class="bi bi-people-fill"></i>

                        </div>

                    </div>

                </div>

            </div>


            <!-- EVENTS WITH AUDIENCE -->

            <div class="col-xl-4 col-md-6">

                <div class="stat-card">

                    <div class="stat-card-body">

                        <div>

                            <div class="stat-label">
                                Events With Audience
                            </div>

                            <div
                                class="stat-value"
                                style="color:#ea580c;"
                            >
                                <?= number_format($eventsWithAudience); ?>
                            </div>

                        </div>

                        <div class="stat-icon orange">

                            <i class="bi bi-calendar2-check-fill"></i>

                        </div>

                    </div>

                </div>

            </div>


        </div>

        <div class="table-card">

            <div class="table-card-header">

                <div>

                    <h2 class="table-title">

                        <i class="bi bi-person-vcard-fill"></i>

                        Event Participants

                    </h2>

                    <p class="table-description">

                        All audience users who have joined an event.

                    </p>

                </div>


                <div class="join-count">

                    <?= number_format($totalJoined); ?>

                    Join Record(s)

                </div>

            </div>


            <!-- TABLE -->

            <div class="table-wrapper">

                <table
                    id="audienceTable"
                    class="table"
                >

                    <thead>

                        <tr>

                            <th>
                                Event
                            </th>

                            <th>
                                Schedule
                            </th>

                            <th>
                                Category
                            </th>

                            <th>
                                Event Status
                            </th>

                            <th>
                                Audience Name
                            </th>

                            <th>
                                Email
                            </th>

                            <th>
                                Contact
                            </th>

                            <th>
                                Address
                            </th>

                            <th>
                                Joined At
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach ($participants as $row): ?>

                        <tr>


                            <!-- EVENT -->

                            <td>

                                <div class="event-name">

                                    <?= e($row['event_name']); ?>

                                </div>

                                <div class="small-muted">

                                    <i class="bi bi-geo-alt me-1"></i>

                                    <?= e($row['location']); ?>

                                </div>

                            </td>


                            <!-- SCHEDULE -->

                            <td>

                                <?php if (!empty($row['event_date'])): ?>

                                    <?= e(
                                        date(
                                            'M d, Y h:i A',
                                            strtotime($row['event_date'])
                                        )
                                    ); ?>

                                <?php else: ?>

                                    N/A

                                <?php endif; ?>

                     
                            <td>

                                <span class="category-badge">

                                    <?= e($row['category']); ?>

                                </span>

                            </td>



                            <td>

                                <?php

                                $status = trim(
                                    (string)($row['event_status'] ?? '')
                                );

                                if ($status === 'Upcoming'):

                                ?>

                                    <span class="status-badge status-upcoming">

                                        <i class="bi bi-circle-fill"></i>

                                        Upcoming

                                    </span>

                                <?php

                                elseif ($status === 'Ongoing'):

                                ?>

                                    <span class="status-badge status-ongoing">

                                        <i class="bi bi-circle-fill"></i>

                                        Ongoing

                                    </span>

                                <?php

                                elseif ($status === 'Ended'):

                                ?>

                                    <span class="status-badge status-ended">

                                        <i class="bi bi-circle-fill"></i>

                                        Ended

                                    </span>

                                <?php else: ?>

                                    <span class="status-badge status-other">

                                        <?= e($status ?: 'N/A'); ?>

                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <div class="event-name">

                                    <?= e($row['fullname']); ?>

                                </div>

                                <?php if (!empty($row['username'])): ?>

                                    <div class="small-muted">

                                        @<?= e($row['username']); ?>

                                    </div>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?= e($row['email']); ?>

                            </td>


                

                            <td>

                                <?= e($row['contact']); ?>

                            </td>



                            <td>

                                <?= e($row['address']); ?>

                            </td>


                        

                            <td>

                                <?php if (!empty($row['joined_at'])): ?>

                                    <?= e(
                                        date(
                                            'M d, Y h:i A',
                                            strtotime($row['joined_at'])
                                        )
                                    ); ?>

                                <?php else: ?>

                                    N/A

                                <?php endif; ?>

                            </td>


                            <td>

                                <a
                                    href="event.php"
                                    class="view-event-btn"
                                    title="View Events"
                                >

                                    <i class="bi bi-calendar-event"></i>

                                </a>

                            </td>


                        </tr>

                    <?php endforeach; ?>


                    <?php if (empty($participants)): ?>

                        <tr>

                            <td>
                                No participants yet.
                            </td>

                            <td>-</td>

                            <td>-</td>

                            <td>-</td>

                            <td>-</td>

                            <td>-</td>

                            <td>-</td>

                            <td>-</td>

                            <td>-</td>

                            <td>-</td>

                        </tr>

                    <?php endif; ?>


                    </tbody>

                </table>

            </div>


        </div>


    </main>


</div>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script
    src="https://code.jquery.com/jquery-3.7.1.min.js"
></script>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

<script
    src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"
></script>

<script
    src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"
></script>


<script>

/* =========================================================
   DATATABLE
========================================================= */

$(document).ready(function () {

    $('#audienceTable').DataTable({

        pageLength: 5,

        lengthMenu: [
            [5, 10, 25, 50, 100],
            [5, 10, 25, 50, 100]
        ],

        order: [
            [8, 'desc']
        ],

        language: {

            search: "",

            searchPlaceholder:
                "Search audience...",

            lengthMenu:
                "Show _MENU_ entries",

            emptyTable:
                "No audience participation records found.",

            zeroRecords:
                "No matching audience records found."

        },

        columnDefs: [

            {
                orderable: false,
                targets: 9
            }

        ]

    });

});



function toggleSidebar()
{
    const sidebar =
        document.getElementById('sidebar');

    const overlay =
        document.getElementById('sidebarOverlay');

    sidebar.classList.toggle('open');

    overlay.classList.toggle('open');
}


function closeSidebar()
{
    const sidebar =
        document.getElementById('sidebar');

    const overlay =
        document.getElementById('sidebarOverlay');

    sidebar.classList.remove('open');

    overlay.classList.remove('open');
}


/* =========================================================
   LOGOUT VALIDATION
========================================================= */

function confirmLogout()
{
    return confirm(
        "Are you sure you want to logout from the Admin Panel?"
    );
}


/* =========================================================
   CLOSE SIDEBAR AFTER CLICKING MENU
========================================================= */

document
    .querySelectorAll('.sidebar-link')
    .forEach(function (link) {

        link.addEventListener(
            'click',
            function () {

                if (window.innerWidth <= 1000) {

                    closeSidebar();

                }

            }
        );

    });


window.addEventListener(
    'resize',
    function () {

        if (window.innerWidth > 1000) {

            closeSidebar();

        }

    }
);

</script>


</body>

</html>