<?php

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/event_helpers.php';
require_once __DIR__ . '/../organizer/notification_center.php';




if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Admin'
) {
    header("Location: ../auth/login.php");
    exit();
}




$currentPage = basename($_SERVER['PHP_SELF']);
$notificationCenter = organizer_notification_center($conn, (int) $_SESSION['user_id']);




function e($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}


$totalEvents = 0;

$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM events"
);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalEvents = (int)$row['total'];
}



$totalAudience = 0;

$result = mysqli_query(
    $conn,
    "SELECT COUNT(DISTINCT ep.user_id) AS total
     FROM event_participants ep
     INNER JOIN users u
        ON ep.user_id = u.user_id
     WHERE u.role = 'Audience'"
);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalAudience = (int)$row['total'];
}



$totalOrganizers = 0;

$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'Organizer'"
);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalOrganizers = (int)$row['total'];
}




$totalJoins = 0;

$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM event_participants"
);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    $totalJoins = (int)$row['total'];
}




$upcomingEvents = 0;
$ongoingEvents = 0;
$endedEvents = 0;




$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM events
     WHERE event_date > NOW()"
);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    $upcomingEvents = (int)$row['total'];
}



$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM events
     WHERE event_date <= NOW()
       AND end_date >= NOW()"
);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    $ongoingEvents = (int)$row['total'];
}



$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM events
     WHERE end_date < NOW()"
);

if ($result) {
    $row = mysqli_fetch_assoc($result);
    $endedEvents = (int)$row['total'];
}




$eventReports = [];

$sql = "
    SELECT
        e.event_id,
        e.event_name,
        e.category,
        e.event_date,
        e.end_date,
        e.location,
        e.capacity,
        e.status,
        e.approval_status,
        e.organizer_id,

        COALESCE(
            COUNT(ep.participation_id),
            0
        ) AS participant_count,

        u.fullname AS organizer_name

    FROM events e

    LEFT JOIN event_participants ep
        ON e.event_id = ep.event_id

    LEFT JOIN users u
        ON e.organizer_id = u.user_id

    GROUP BY
        e.event_id,
        e.event_name,
        e.category,
        e.event_date,
        e.end_date,
        e.location,
        e.capacity,
        e.status,
        e.approval_status,
        e.organizer_id,
        u.fullname

    ORDER BY e.event_date DESC
";

$result = mysqli_query($conn, $sql);

if ($result) {

    while ($row = mysqli_fetch_assoc($result)) {
        $eventReports[] = $row;
    }
}



$categoryReports = [];

$sql = "
    SELECT
        category,
        COUNT(*) AS total

    FROM events

    WHERE category IS NOT NULL
      AND TRIM(category) <> ''

    GROUP BY category

    ORDER BY total DESC
";

$result = mysqli_query($conn, $sql);

if ($result) {

    while ($row = mysqli_fetch_assoc($result)) {
        $categoryReports[] = $row;
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

<title>Reports & Analytics | Event System</title>


<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
>

<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


<link rel="stylesheet" href="../assets/css/admin/reports.css">
```

</head>

<body>



<nav class="top-navbar">

<div class="navbar-inner">

    <div class="d-flex align-items-center gap-2">

        <button
            type="button"
            class="sidebar-toggle"
            id="sidebarToggle"
            aria-label="Open navigation"
            aria-controls="adminSidebar"
            aria-expanded="false"
        >
            <i class="bi bi-list"></i>
        </button>


    

        <a
            href="admin.php"
            class="navbar-brand-custom"
        >

            <i class="bi bi-calendar2-event"></i>

            <span>
                 Event System
            </span>

        </a>

    </div>


    <div class="navbar-right">
        <a
            href="../auth/logout.php"
            class="btn btn-outline-light btn-sm logout-btn"
            onclick="
                return confirm(
                    'Are you sure you want to logout?'
                );
            "
        >
            <i class="bi bi-box-arrow-right me-1"></i>
            Logout
        </a>

    </div>

</div>

</nav>



<?php require __DIR__ . '/sidebar.php'; ?>


<div class="main-wrapper">



<main class="main-content">


    <div class="page-header">

        <div>

            <h1 class="page-title">
                Reports & Analytics
            </h1>

            <p class="page-subtitle">
                Monitor event activity, participation, and system statistics.
            </p>

        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-outline-success" href="report_export.php?format=csv">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export Excel (CSV)
            </a>
            <a class="btn btn-outline-danger" href="report_export.php?format=pdf">
                <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
            </a>
        </div>

    </div>



    <div class="summary-grid">

        <div class="card report-card">

            <div class="card-body text-center">

                <h6 class="card-title">
                    Total Events
                </h6>

                <p class="display-6 text-primary">
                    <?= $totalEvents; ?>
                </p>

                <small class="text-muted">
                    All events
                </small>

            </div>

        </div>


        <div class="card report-card">

            <div class="card-body text-center">

                <h6 class="card-title">
                    Total Audience
                </h6>

                <p class="display-6 text-success">
                    <?= $totalAudience; ?>
                </p>

                <small class="text-muted">
                    Unique users who joined
                </small>

            </div>

        </div>



        <div class="card report-card">

            <div class="card-body text-center">

                <h6 class="card-title">
                    Total Organizers
                </h6>

                <p class="display-6 text-warning">
                    <?= $totalOrganizers; ?>
                </p>

                <small class="text-muted">
                    Registered organizers
                </small>

            </div>

        </div>


   

        <div class="card report-card">

            <div class="card-body text-center">

                <h6 class="card-title">
                    Total Event Joins
                </h6>

                <p class="display-6 text-danger">
                    <?= $totalJoins; ?>
                </p>

                <small class="text-muted">
                    Participation records
                </small>

            </div>

        </div>


    </div>


  
    <div class="chart-grid">




        <div class="content-card">

            <div class="card-body">

                <h5 class="card-title mb-1">
                    Event Status Breakdown
                </h5>

                <p class="text-muted mb-3">
                    Current number of events by status.
                </p>

                <div class="chart-container">

                    <canvas id="eventStatusChart"></canvas>

                </div>

            </div>

        </div>

        <div class="content-card">

            <div class="card-body">

                <h5 class="card-title mb-1">
                    Events by Category
                </h5>

                <p class="text-muted mb-3">
                    Number of events under each category.
                </p>

                <div class="chart-container">

                    <canvas id="categoryChart"></canvas>

                </div>

            </div>

        </div>


    </div>


    <div class="content-card mb-4">

        <div class="card-body">

            <h5 class="card-title mb-1">
                Event Status Summary
            </h5>

            <p class="text-muted mb-0">
                Overview of current event statuses.
            </p>


            <div class="status-grid">



                <div class="status-box">

                    <div class="d-flex justify-content-between align-items-center">

                        <span>
                            Upcoming Events
                        </span>

                        <span class="badge bg-primary">
                            <?= $upcomingEvents; ?>
                        </span>

                    </div>

                </div>



                <div class="status-box">

                    <div class="d-flex justify-content-between align-items-center">

                        <span>
                            Ongoing Events
                        </span>

                        <span class="badge bg-success">
                            <?= $ongoingEvents; ?>
                        </span>

                    </div>

                </div>



                <div class="status-box">

                    <div class="d-flex justify-content-between align-items-center">

                        <span>
                            Ended Events
                        </span>

                        <span class="badge bg-secondary">
                            <?= $endedEvents; ?>
                        </span>

                    </div>

                </div>


            </div>

        </div>

    </div>


    <div class="table-card">

        <div class="table-card-body">


            <div
                class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2"
            >

                <div>

                    <h5 class="card-title mb-1">
                        Event Participation Report
                    </h5>

                    <p class="text-muted mb-0">
                        Audience participation for every event.
                    </p>

                </div>

                <span class="badge bg-primary">
                    <?= count($eventReports); ?> Event(s)
                </span>

            </div>


            <div class="table-responsive">

                <table
                    id="eventReportTable"
                    class="table table-striped table-hover"
                >

                    <thead class="table-primary">

                        <tr>

                            <th>
                                Event
                            </th>

                            <th>
                                Category
                            </th>

                            <th>
                                Schedule
                            </th>

                            <th>
                                Location
                            </th>

                            <th>
                                Organizer
                            </th>

                            <th>
                                Capacity
                            </th>

                            <th>
                                Participants
                            </th>

                            <th>
                                Available
                            </th>

                            <th>
                                Status
                            </th>
                            <th>Approval</th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php if (!empty($eventReports)): ?>


                            <?php foreach ($eventReports as $row): ?>


                                <?php

                                $capacity = (int)($row['capacity'] ?? 0);

                                $participants =
                                    (int)($row['participant_count'] ?? 0);

                                $available = max(
                                    0,
                                    $capacity - $participants
                                );

                                ?>


                                <tr>


                                    <!-- EVENT -->

                                    <td>

                                        <div class="event-name">

                                            <?= e($row['event_name']); ?>

                                        </div>

                                        <div class="small-muted">

                                            Event ID:
                                            <?= (int)$row['event_id']; ?>

                                        </div>

                                    </td>


                                    <td>

                                        <span class="badge bg-info text-dark">

                                            <?= e($row['category']); ?>

                                        </span>

                                    </td>



                                    <td>

                                        <?php

                                        if (!empty($row['event_date'])) {

                                            echo e(
                                                date(
                                                    'M d, Y h:i A',
                                                    strtotime($row['event_date'])
                                                )
                                            );

                                        } else {

                                            echo 'N/A';

                                        }

                                        ?>

                                    </td>


                          
                                    <td>

                                        <?= e($row['location']); ?>

                                    </td>


                                    <td>

                                        <?= e(
                                            $row['organizer_name']
                                                ?: 'N/A'
                                        ); ?>

                                    </td>


                                    <td>

                                        <?= $capacity; ?>

                                    </td>


                                    <td>

                                        <span class="badge bg-success">

                                            <?= $participants; ?>

                                        </span>

                                    </td>


                           

                                    <td>

                                        <?php if ($available <= 0): ?>

                                            <span class="badge bg-danger">
                                                Full
                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-warning text-dark">

                                                <?= $available; ?>

                                            </span>

                                        <?php endif; ?>

                                    </td>



                                    <td><?= event_lifecycle_badge($row['event_date'], $row['end_date']); ?></td>
                                    <td><?= event_approval_badge($row['approval_status']); ?></td>


                                </tr>


                            <?php endforeach; ?>


                        <?php else: ?>


                            <tr>

                                <td colspan="9" class="text-center py-4">

                                    <i class="bi bi-calendar-x fs-3 d-block mb-2 text-muted"></i>

                                    <span class="text-muted">
                                        No events found.
                                    </span>

                                </td>

                            </tr>


                        <?php endif; ?>


                    </tbody>

                </table>

            </div>

        </div>

    </div>




    <div class="table-card">

        <div class="table-card-body">

            <h5 class="card-title mb-1">
                Category Summary
            </h5>

            <p class="text-muted mb-3">
                Number of events under each category.
            </p>


            <div class="table-responsive">

                <table class="table table-striped table-hover category-table">

                    <thead class="table-primary">

                        <tr>

                            <th>
                                Category
                            </th>

                            <th>
                                Number of Events
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php if (!empty($categoryReports)): ?>


                            <?php foreach ($categoryReports as $row): ?>

                                <tr>

                                    <td>

                                        <?= e($row['category']); ?>

                                    </td>

                                    <td>

                                        <span class="badge bg-primary">

                                            <?= (int)$row['total']; ?>

                                        </span>

                                    </td>

                                </tr>

                            <?php endforeach; ?>


                        <?php else: ?>


                            <tr>

                                <td colspan="2" class="text-center py-4">

                                    <span class="text-muted">
                                        No category data available.
                                    </span>

                                </td>

                            </tr>


                        <?php endif; ?>


                    </tbody>

                </table>

            </div>

        </div>

    </div>


</main>


</div>


<script>

    const upcomingEvents =
        <?= $upcomingEvents; ?>;

    const ongoingEvents =
        <?= $ongoingEvents; ?>;

    const endedEvents =
        <?= $endedEvents; ?>;


    const categoryLabels = [

        <?php

        foreach ($categoryReports as $row) {

            echo json_encode(
                $row['category']
            ) . ',';

        }

        ?>

    ];


    const categoryData = [

        <?php

        foreach ($categoryReports as $row) {

            echo (int)$row['total'] . ',';

        }

        ?>

    ];

</script>



<script src="../assets/js/admin/reports.js"></script>


<script>

    document.addEventListener(
        'DOMContentLoaded',
        function () {

            const sidebar =
                document.getElementById('adminSidebar');

            const toggle =
                document.getElementById('sidebarToggle');

            const closeBtn =
                document.getElementById('sidebarClose');

            const overlay =
                document.getElementById('sidebarOverlay');

            const sidebarLinks =
                document.querySelectorAll(
                    '.admin-sidebar .sidebar-link'
                );



            function isMobile() {

                return window.innerWidth <= 991.98;

            }


        
            function openSidebar() {

                if (!isMobile()) {
                    return;
                }

                sidebar.classList.add('open');

                overlay.classList.add('show');

                toggle.setAttribute(
                    'aria-expanded',
                    'true'
                );

                document.body.style.overflow = 'hidden';

            }


      

            function closeSidebar() {

                sidebar.classList.remove('open');

                overlay.classList.remove('show');

                toggle.setAttribute(
                    'aria-expanded',
                    'false'
                );

                document.body.style.overflow = '';

            }


       
            toggle.addEventListener(
                'click',
                function (event) {

                    event.stopPropagation();

                    if (
                        sidebar.classList.contains('open')
                    ) {

                        closeSidebar();

                    } else {

                        openSidebar();

                    }

                }
            );


        

            closeBtn.addEventListener(
                'click',
                function () {

                    closeSidebar();

                }
            );



            overlay.addEventListener(
                'click',
                function () {

                    closeSidebar();

                }
            );


      

            sidebarLinks.forEach(
                function (link) {

                    link.addEventListener(
                        'click',
                        function () {

                            if (isMobile()) {

                                closeSidebar();

                            }

                        }
                    );

                }
            );


         

            document.addEventListener(
                'click',
                function (event) {

                    if (!isMobile()) {
                        return;
                    }

                    if (
                        !sidebar.classList.contains('open')
                    ) {
                        return;
                    }

                    const clickedInsideSidebar =
                        sidebar.contains(event.target);

                    const clickedToggle =
                        toggle.contains(event.target);

                    if (
                        !clickedInsideSidebar &&
                        !clickedToggle
                    ) {

                        closeSidebar();

                    }

                }
            );


            document.addEventListener(
                'keydown',
                function (event) {

                    if (
                        event.key === 'Escape'
                    ) {

                        closeSidebar();

                    }

                }
            );


           
            window.addEventListener(
                'resize',
                function () {

                    if (!isMobile()) {

                        closeSidebar();

                    }

                }
            );

        }
    );

</script>

</body>

</html>
