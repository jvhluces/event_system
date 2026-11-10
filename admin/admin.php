<?php

session_start();

require_once __DIR__ . '/../config/db.php';


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
| HELPER
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['admin_csrf_token'];


/*
|--------------------------------------------------------------------------
| FLASH MESSAGES
|--------------------------------------------------------------------------
*/

$successMessage = $_SESSION['admin_success'] ?? '';
$errorMessage   = $_SESSION['admin_error'] ?? '';

unset($_SESSION['admin_success']);
unset($_SESSION['admin_error']);


/*
|--------------------------------------------------------------------------
| ADMIN ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $postedToken = $_POST['csrf_token'] ?? '';

    if (
        !isset($_SESSION['admin_csrf_token']) ||
        !hash_equals(
            $_SESSION['admin_csrf_token'],
            $postedToken
        )
    ) {

        $_SESSION['admin_error'] =
            "Security verification failed. Please try again.";

        header("Location: admin.php");
        exit();
    }


    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);


    if ($userId <= 0) {

        $_SESSION['admin_error'] =
            "Invalid user account.";

        header("Location: admin.php");
        exit();
    }


    /*
    |--------------------------------------------------------------------------
    | GET TARGET USER
    |--------------------------------------------------------------------------
    */

    $userStmt = $conn->prepare("
        SELECT
            user_id,
            firstname,
            middlename,
            lastname,
            fullname,
            email,
            contact,
            address,
            organization_name,
            role,
            account_status
        FROM users
        WHERE user_id = ?
        LIMIT 1
    ");

    if (!$userStmt) {

        $_SESSION['admin_error'] =
            "Database error while checking the account.";

        header("Location: admin.php");
        exit();
    }


    $userStmt->bind_param("i", $userId);
    $userStmt->execute();

    $userResult = $userStmt->get_result();
    $targetUser = $userResult->fetch_assoc();

    $userStmt->close();


    if (!$targetUser) {

        $_SESSION['admin_error'] =
            "The selected user account could not be found.";

        header("Location: admin.php");
        exit();
    }


    /*
    |--------------------------------------------------------------------------
    | PROTECT ADMIN ACCOUNTS
    |--------------------------------------------------------------------------
    */

    if ($targetUser['role'] === 'Admin') {

        $_SESSION['admin_error'] =
            "Admin accounts cannot be modified from this page.";

        header("Location: admin.php");
        exit();
    }


    /*
    |--------------------------------------------------------------------------
    | ACTIVATE / REACTIVATE ACCOUNT
    |--------------------------------------------------------------------------
    */

    if ($action === 'activate') {

        $stmt = $conn->prepare("
            UPDATE users
            SET account_status = 'Active'
            WHERE user_id = ?
              AND role IN ('Organizer', 'Audience')
        ");

        if (!$stmt) {

            $_SESSION['admin_error'] =
                "Database error while activating account.";

            header("Location: admin.php");
            exit();
        }


        $stmt->bind_param("i", $userId);


        if ($stmt->execute()) {

            if ($stmt->affected_rows > 0) {

                $_SESSION['admin_success'] =
                    "The account of " .
                    ($targetUser['fullname'] ?: $targetUser['firstname']) .
                    " has been reactivated successfully.";

            } else {

                /*
                |--------------------------------------------------------------------------
                | Account may already be Active
                |--------------------------------------------------------------------------
                */

                $_SESSION['admin_success'] =
                    "The account is already active.";
            }

        } else {

            $_SESSION['admin_error'] =
                "Failed to reactivate the account.";
        }


        $stmt->close();

        header("Location: admin.php");
        exit();
    }


    /*
    |--------------------------------------------------------------------------
    | DEACTIVATE ACCOUNT
    |--------------------------------------------------------------------------
    */

    if ($action === 'deactivate') {

        $stmt = $conn->prepare("
            UPDATE users
            SET account_status = 'Deactivated'
            WHERE user_id = ?
              AND role IN ('Organizer', 'Audience')
        ");

        if (!$stmt) {

            $_SESSION['admin_error'] =
                "Database error while deactivating account.";

            header("Location: admin.php");
            exit();
        }


        $stmt->bind_param("i", $userId);


        if ($stmt->execute()) {

            if ($stmt->affected_rows > 0) {

                $_SESSION['admin_success'] =
                    "The account of " .
                    ($targetUser['fullname'] ?: $targetUser['firstname']) .
                    " has been deactivated successfully.";

            } else {

                $_SESSION['admin_error'] =
                    "The account could not be deactivated.";
            }

        } else {

            $_SESSION['admin_error'] =
                "Failed to deactivate the account.";
        }


        $stmt->close();

        header("Location: admin.php");
        exit();
    }


    /*
    |--------------------------------------------------------------------------
    | EDIT USER
    |--------------------------------------------------------------------------
    */

    if ($action === 'edit') {

        $fullname         = trim($_POST['fullname'] ?? '');
        $email            = trim($_POST['email'] ?? '');
        $contact          = trim($_POST['contact'] ?? '');
        $address          = trim($_POST['address'] ?? '');
        $organizationName = trim($_POST['organization_name'] ?? '');


        if ($fullname === '') {

            $_SESSION['admin_error'] =
                "Full name is required.";

            header("Location: admin.php");
            exit();
        }


        if ($email === '') {

            $_SESSION['admin_error'] =
                "Email address is required.";

            header("Location: admin.php");
            exit();
        }


        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $_SESSION['admin_error'] =
                "Please enter a valid email address.";

            header("Location: admin.php");
            exit();
        }


        /*
        |--------------------------------------------------------------------------
        | CHECK DUPLICATE EMAIL
        |--------------------------------------------------------------------------
        */

        $emailCheck = $conn->prepare("
            SELECT user_id
            FROM users
            WHERE email = ?
              AND user_id <> ?
            LIMIT 1
        ");

        if (!$emailCheck) {

            $_SESSION['admin_error'] =
                "Database error while checking the email.";

            header("Location: admin.php");
            exit();
        }


        $emailCheck->bind_param(
            "si",
            $email,
            $userId
        );

        $emailCheck->execute();
        $emailCheck->store_result();


        if ($emailCheck->num_rows > 0) {

            $emailCheck->close();

            $_SESSION['admin_error'] =
                "That email address is already being used by another account.";

            header("Location: admin.php");
            exit();
        }


        $emailCheck->close();


        /*
        |--------------------------------------------------------------------------
        | ORGANIZER UPDATE
        |--------------------------------------------------------------------------
        */

        if ($targetUser['role'] === 'Organizer') {

            $stmt = $conn->prepare("
                UPDATE users
                SET
                    fullname = ?,
                    email = ?,
                    contact = ?,
                    address = ?,
                    organization_name = ?
                WHERE user_id = ?
                  AND role = 'Organizer'
            ");

            if ($stmt) {

                $stmt->bind_param(
                    "sssssi",
                    $fullname,
                    $email,
                    $contact,
                    $address,
                    $organizationName,
                    $userId
                );
            }

        }

        /*
        |--------------------------------------------------------------------------
        | AUDIENCE UPDATE
        |--------------------------------------------------------------------------
        */

        else {

            $stmt = $conn->prepare("
                UPDATE users
                SET
                    fullname = ?,
                    email = ?,
                    contact = ?,
                    address = ?
                WHERE user_id = ?
                  AND role = 'Audience'
            ");

            if ($stmt) {

                $stmt->bind_param(
                    "ssssi",
                    $fullname,
                    $email,
                    $contact,
                    $address,
                    $userId
                );
            }
        }


        if (!isset($stmt) || !$stmt) {

            $_SESSION['admin_error'] =
                "Database error while updating the account.";

            header("Location: admin.php");
            exit();
        }


        if ($stmt->execute()) {

            $_SESSION['admin_success'] =
                "The account of " .
                ($targetUser['fullname'] ?: $targetUser['firstname']) .
                " has been updated successfully.";

        } else {

            $_SESSION['admin_error'] =
                "Unable to update the account.";
        }


        $stmt->close();

        header("Location: admin.php");
        exit();
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE ACCOUNT
    |--------------------------------------------------------------------------
    */

    if ($action === 'delete') {

        /*
        |--------------------------------------------------------------------------
        | ORGANIZER DELETE CHECK
        |--------------------------------------------------------------------------
        */

        if ($targetUser['role'] === 'Organizer') {

            $eventCheck = $conn->prepare("
                SELECT COUNT(*) AS total
                FROM events
                WHERE organizer_id = ?
            ");

            if (!$eventCheck) {

                $_SESSION['admin_error'] =
                    "Database error while checking organizer events.";

                header("Location: admin.php");
                exit();
            }


            $eventCheck->bind_param("i", $userId);
            $eventCheck->execute();

            $eventResult = $eventCheck->get_result();
            $eventRow = $eventResult->fetch_assoc();

            $eventCount =
                (int)($eventRow['total'] ?? 0);

            $eventCheck->close();


            if ($eventCount > 0) {

                $_SESSION['admin_error'] =
                    "This organizer cannot be permanently deleted because " .
                    $eventCount .
                    " event(s) are associated with this account. " .
                    "Deactivate the organizer instead to preserve event history.";

                header("Location: admin.php");
                exit();
            }
        }


        /*
        |--------------------------------------------------------------------------
        | AUDIENCE DELETE CHECK
        |--------------------------------------------------------------------------
        */

        if ($targetUser['role'] === 'Audience') {

            $participantCheck = $conn->prepare("
                SELECT COUNT(*) AS total
                FROM event_participants
                WHERE user_id = ?
            ");

            if (!$participantCheck) {

                $_SESSION['admin_error'] =
                    "Database error while checking participation records.";

                header("Location: admin.php");
                exit();
            }


            $participantCheck->bind_param("i", $userId);
            $participantCheck->execute();

            $participantResult = $participantCheck->get_result();
            $participantRow = $participantResult->fetch_assoc();

            $participantCount =
                (int)($participantRow['total'] ?? 0);

            $participantCheck->close();


            $reviewCheck = $conn->prepare("
                SELECT COUNT(*) AS total
                FROM event_reviews
                WHERE user_id = ?
            ");

            if (!$reviewCheck) {

                $_SESSION['admin_error'] =
                    "Database error while checking review records.";

                header("Location: admin.php");
                exit();
            }


            $reviewCheck->bind_param("i", $userId);
            $reviewCheck->execute();

            $reviewResult = $reviewCheck->get_result();
            $reviewRow = $reviewResult->fetch_assoc();

            $reviewCount =
                (int)($reviewRow['total'] ?? 0);

            $reviewCheck->close();


            if (
                $participantCount > 0 ||
                $reviewCount > 0
            ) {

                $_SESSION['admin_error'] =
                    "This audience account cannot be permanently deleted " .
                    "because it has existing participation or review records. " .
                    "Deactivate the account instead to preserve system history.";

                header("Location: admin.php");
                exit();
            }
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE USER
        |--------------------------------------------------------------------------
        */

        $deleteStmt = $conn->prepare("
            DELETE FROM users
            WHERE user_id = ?
              AND role IN ('Organizer', 'Audience')
        ");

        if (!$deleteStmt) {

            $_SESSION['admin_error'] =
                "Database error while deleting the account.";

            header("Location: admin.php");
            exit();
        }


        $deleteStmt->bind_param("i", $userId);


        if ($deleteStmt->execute()) {

            if ($deleteStmt->affected_rows > 0) {

                $_SESSION['admin_success'] =
                    "The account has been permanently deleted.";

            } else {

                $_SESSION['admin_error'] =
                    "The account could not be deleted.";
            }

        } else {

            $_SESSION['admin_error'] =
                "Unable to delete the account.";
        }


        $deleteStmt->close();

        header("Location: admin.php");
        exit();
    }


    /*
    |--------------------------------------------------------------------------
    | INVALID ACTION
    |--------------------------------------------------------------------------
    */

    $_SESSION['admin_error'] =
        "Invalid administrator action.";

    header("Location: admin.php");
    exit();
}


/*
|--------------------------------------------------------------------------
| DASHBOARD STATISTICS
|--------------------------------------------------------------------------
*/

$totalEvents          = 0;
$totalOrganizers      = 0;
$totalAudience        = 0;
$totalRegisteredUsers = 0;
$totalVenues          = 0;


/*
|--------------------------------------------------------------------------
| TOTAL EVENTS
|--------------------------------------------------------------------------
*/

$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM events"
);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $totalEvents =
        (int)($row['total'] ?? 0);
}


/*
|--------------------------------------------------------------------------
| TOTAL ORGANIZERS
|--------------------------------------------------------------------------
*/

$result = mysqli_query(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'Organizer'
    "
);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $totalOrganizers =
        (int)($row['total'] ?? 0);
}


/*
|--------------------------------------------------------------------------
| TOTAL AUDIENCE
|--------------------------------------------------------------------------
*/

$result = mysqli_query(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'Audience'
    "
);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $totalAudience =
        (int)($row['total'] ?? 0);
}


/*
|--------------------------------------------------------------------------
| TOTAL REGISTERED USERS
|--------------------------------------------------------------------------
*/

$result = mysqli_query(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM users
    WHERE role IN ('Organizer', 'Audience')
    "
);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $totalRegisteredUsers =
        (int)($row['total'] ?? 0);
}


/*
|--------------------------------------------------------------------------
| UNIQUE VENUES
|--------------------------------------------------------------------------
*/

$result = mysqli_query(
    $conn,
    "
    SELECT COUNT(DISTINCT location) AS total
    FROM events
    WHERE location IS NOT NULL
      AND TRIM(location) <> ''
    "
);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $totalVenues =
        (int)($row['total'] ?? 0);
}


/*
|--------------------------------------------------------------------------
| GET ORGANIZERS
|--------------------------------------------------------------------------
*/

$organizers = [];

$result = mysqli_query(
    $conn,
    "
    SELECT
        u.user_id,
        u.firstname,
        u.middlename,
        u.lastname,
        u.fullname,
        u.email,
        u.contact,
        u.address,
        u.organization_name,
        u.account_status,

        (
            SELECT COUNT(*)
            FROM events e
            WHERE e.organizer_id = u.user_id
        ) AS event_count

    FROM users u

    WHERE u.role = 'Organizer'

    ORDER BY u.user_id DESC
    "
);

if ($result) {

    while ($row = mysqli_fetch_assoc($result)) {

        $organizers[] = $row;
    }
}


/*
|--------------------------------------------------------------------------
| GET AUDIENCE
|--------------------------------------------------------------------------
*/

$audienceUsers = [];

$result = mysqli_query(
    $conn,
    "
    SELECT
        u.user_id,
        u.firstname,
        u.middlename,
        u.lastname,
        u.fullname,
        u.email,
        u.contact,
        u.address,
        u.account_status,

        (
            SELECT COUNT(*)
            FROM event_participants ep
            WHERE ep.user_id = u.user_id
        ) AS participation_count,

        (
            SELECT COUNT(*)
            FROM event_reviews er
            WHERE er.user_id = u.user_id
        ) AS review_count

    FROM users u

    WHERE u.role = 'Audience'

    ORDER BY u.user_id DESC
    "
);

if ($result) {

    while ($row = mysqli_fetch_assoc($result)) {

        $audienceUsers[] = $row;
    }
}



$upcomingEvents = 0;
$ongoingEvents  = 0;
$endedEvents    = 0;

$result = mysqli_query(
    $conn,
    "
    SELECT
        SUM(event_date > NOW()) AS upcoming,
        SUM(event_date <= NOW() AND end_date >= NOW()) AS ongoing,
        SUM(end_date < NOW()) AS ended
    FROM events
    "
);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $upcomingEvents =
        (int)($row['upcoming'] ?? 0);

    $ongoingEvents =
        (int)($row['ongoing'] ?? 0);

    $endedEvents =
        (int)($row['ended'] ?? 0);
}


$currentPage = basename($_SERVER['PHP_SELF']);

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Admin Dashboard | Event System
    </title>


    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
    >

    <link
        rel="stylesheet"
        href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css"
    >

    <link rel="stylesheet" href="../assets/css/admin/admin.css">

</head>


<body>


<?php require __DIR__ . '/sidebar.php'; ?>


<div class="admin-main">


    <header class="topbar">

        <div class="topbar-left">

            <button
                type="button"
                class="mobile-menu-btn"
                onclick="toggleSidebar()"
            >
                <i class="bi bi-list"></i>
            </button>


            <div>

                <h1 class="topbar-title">
                    Admin Dashboard
                </h1>

                <div class="topbar-subtitle">
                    Event System Management
                </div>

            </div>

        </div>


        <div class="topbar-right">

            <div class="topbar-admin">

                <i class="bi bi-shield-check"></i>

                <span>
                    Administrator
                </span>

            </div>

        </div>

    </header>


    <main class="admin-content">


        <div class="page-heading">

            <div>

                <h1>
                    Dashboard Overview
                </h1>

                <p>
                    Monitor events, organizers, audience users, and system activity.
                </p>

            </div>


            <button
                type="button"
                class="btn btn-outline-primary"
                onclick="window.location.reload();"
            >
                <i class="bi bi-arrow-clockwise"></i>
                Refresh
            </button>

        </div>


        <?php if ($successMessage): ?>

            <div
                class="alert alert-success admin-alert alert-dismissible fade show"
                role="alert"
            >

                <i class="bi bi-check-circle-fill me-2"></i>

                <?= e($successMessage); ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>

            </div>

        <?php endif; ?>


        <?php if ($errorMessage): ?>

            <div
                class="alert alert-danger admin-alert alert-dismissible fade show"
                role="alert"
            >

                <i class="bi bi-exclamation-triangle-fill me-2"></i>

                <?= e($errorMessage); ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>

            </div>

        <?php endif; ?>


        <!-- STATISTICS -->

        <div class="row g-4">

            <div class="col-sm-6 col-xl-3">

                <div class="stat-card">

                    <div class="stat-top">

                        <div>

                            <div class="stat-label">
                                Total Events
                            </div>

                            <h2 class="stat-number text-primary">
                                <?= $totalEvents; ?>
                            </h2>

                        </div>

                        <div class="stat-icon icon-blue">
                            <i class="bi bi-calendar-event"></i>
                        </div>

                    </div>

                </div>

            </div>


            <div class="col-sm-6 col-xl-3">

                <div class="stat-card">

                    <div class="stat-top">

                        <div>

                            <div class="stat-label">
                                Registered Organizers
                            </div>

                            <h2 class="stat-number text-success">
                                <?= $totalOrganizers; ?>
                            </h2>

                        </div>

                        <div class="stat-icon icon-green">
                            <i class="bi bi-person-badge"></i>
                        </div>

                    </div>

                </div>

            </div>


            <div class="col-sm-6 col-xl-3">

                <div class="stat-card">

                    <div class="stat-top">

                        <div>

                            <div class="stat-label">
                                Registered Audience
                            </div>

                            <h2 class="stat-number text-warning">
                                <?= $totalAudience; ?>
                            </h2>

                        </div>

                        <div class="stat-icon icon-orange">
                            <i class="bi bi-people"></i>
                        </div>

                    </div>

                </div>

            </div>


            <div class="col-sm-6 col-xl-3">

                <div class="stat-card">

                    <div class="stat-top">

                        <div>

                            <div class="stat-label">
                                Registered Users
                            </div>

                            <h2 class="stat-number text-purple">
                                <?= $totalRegisteredUsers; ?>
                            </h2>

                        </div>

                        <div class="stat-icon icon-purple">
                            <i class="bi bi-person-lines-fill"></i>
                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- EVENT STATUS -->

        <div class="row g-4 mt-1">

            <div class="col-lg-8">

                <div class="status-card">

                    <div class="status-card-title">

                        <i class="bi bi-activity me-2 text-primary"></i>

                        Event Status Overview

                    </div>


                    <div class="row">

                        <div class="col-md-4">

                            <div class="status-item">

                                <div class="status-left">

                                    <span class="status-dot dot-upcoming"></span>

                                    Upcoming

                                </div>

                                <span class="status-count text-success">
                                    <?= $upcomingEvents; ?>
                                </span>

                            </div>

                        </div>


                        <div class="col-md-4">

                            <div class="status-item">

                                <div class="status-left">

                                    <span class="status-dot dot-ongoing"></span>

                                    Ongoing

                                </div>

                                <span class="status-count text-warning">
                                    <?= $ongoingEvents; ?>
                                </span>

                            </div>

                        </div>


                        <div class="col-md-4">

                            <div class="status-item">

                                <div class="status-left">

                                    <span class="status-dot dot-ended"></span>

                                    Ended

                                </div>

                                <span class="status-count text-secondary">
                                    <?= $endedEvents; ?>
                                </span>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <div class="col-lg-4">

                <div class="status-card">

                    <div class="status-card-title">

                        <i class="bi bi-geo-alt-fill me-2 text-info"></i>

                        Venue Overview

                    </div>


                    <div class="d-flex align-items-center justify-content-between">

                        <div>

                            <div class="text-muted small">
                                Unique event locations
                            </div>

                            <div class="fs-2 fw-bold text-info mt-1">
                                <?= $totalVenues; ?>
                            </div>

                        </div>


                        <div
                            class="stat-icon"
                            style="background:#ecfeff;color:#0891b2;"
                        >

                            <i class="bi bi-geo-alt"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- ORGANIZERS -->

        <section class="section-card">

            <div class="section-header">

                <div>

                    <h2 class="section-title">

                        <i class="bi bi-person-badge-fill text-success"></i>

                        Registered Organizers

                    </h2>

                    <p class="section-description">
                        Manage organizer accounts and their system access.
                    </p>

                </div>


                <span class="badge bg-success">

                    <?= count($organizers); ?>

                    Organizer(s)

                </span>

            </div>


            <div class="section-body">

                <?php if (!empty($organizers)): ?>

                    <div class="table-wrap">

                        <table
                            id="organizerTable"
                            class="table table-hover align-middle data-table"
                        >

                            <thead>

                                <tr>

                                    <th>#</th>
                                    <th>First Name</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Contact</th>
                                    <th>Organization</th>
                                    <th>Address</th>
                                    <th>Status</th>
                                    <th>Actions</th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach ($organizers as $index => $organizer): ?>

                                    <?php

                                    $status = trim(
                                        (string)(
                                            $organizer['account_status'] ?? ''
                                        )
                                    );

                                    if ($status === '') {
                                        $status = 'Active';
                                    }

                                    $statusLower = strtolower($status);

                                    $isInactive = in_array(
                                        $statusLower,
                                        [
                                            'inactive',
                                            'deactivated',
                                            'blocked',
                                            'suspended'
                                        ],
                                        true
                                    );

                                    ?>

                                    <tr>

                                        <td>
                                            <?= $index + 1; ?>
                                        </td>


                                        <td>

                                            <span class="firstname-text">
                                                <?= e($organizer['firstname']); ?>
                                            </span>

                                        </td>


                                        <td>
                                            <?= e(
                                                $organizer['fullname']
                                                ?: 'N/A'
                                            ); ?>
                                        </td>


                                        <td>
                                            <?= e(
                                                $organizer['email']
                                                ?: 'N/A'
                                            ); ?>
                                        </td>


                                        <td>
                                            <?= e(
                                                $organizer['contact']
                                                ?: 'N/A'
                                            ); ?>
                                        </td>


                                        <td>
                                            <?= e(
                                                $organizer['organization_name']
                                                ?: 'N/A'
                                            ); ?>
                                        </td>


                                        <td>
                                            <?= e(
                                                $organizer['address']
                                                ?: 'N/A'
                                            ); ?>
                                        </td>


                                        <td>

                                            <?php if (!$isInactive): ?>

                                                <span class="badge bg-success status-badge">
                                                    Active
                                                </span>

                                            <?php else: ?>

                                                <span class="badge bg-danger status-badge">
                                                    Deactivated
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <td>

                                            <div class="action-buttons">

                                                <!-- VIEW -->

                                                <button
                                                    type="button"
                                                    class="action-btn btn-view-user"
                                                    title="View"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#viewUserModal"

                                                    data-role="Organizer"
                                                    data-username="<?= e($organizer['firstname']); ?>"
                                                    data-fullname="<?= e($organizer['fullname']); ?>"
                                                    data-email="<?= e($organizer['email']); ?>"
                                                    data-contact="<?= e($organizer['contact']); ?>"
                                                    data-address="<?= e($organizer['address']); ?>"
                                                    data-organization="<?= e($organizer['organization_name']); ?>"
                                                    data-status="<?= e($isInactive ? 'Deactivated' : 'Active'); ?>"
                                                    data-events="<?= (int)$organizer['event_count']; ?>"
                                                >

                                                    <i class="bi bi-eye-fill"></i>

                                                </button>


                                                <!-- EDIT -->

                                                <button
                                                    type="button"
                                                    class="action-btn btn-edit-user"
                                                    title="Edit"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editUserModal"

                                                    data-user-id="<?= (int)$organizer['user_id']; ?>"
                                                    data-role="Organizer"
                                                    data-username="<?= e($organizer['firstname']); ?>"
                                                    data-fullname="<?= e($organizer['fullname']); ?>"
                                                    data-email="<?= e($organizer['email']); ?>"
                                                    data-contact="<?= e($organizer['contact']); ?>"
                                                    data-address="<?= e($organizer['address']); ?>"
                                                    data-organization="<?= e($organizer['organization_name']); ?>"
                                                >

                                                    <i class="bi bi-pencil-fill"></i>

                                                </button>


                                                <!-- ACTIVATE / DEACTIVATE -->

                                                <?php if ($isInactive): ?>

                                                    <form
                                                        method="POST"
                                                        class="d-inline"
                                                        onsubmit="return confirm('Reactivate this organizer account?');"
                                                    >

                                                        <input
                                                            type="hidden"
                                                            name="csrf_token"
                                                            value="<?= e($csrfToken); ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="action"
                                                            value="activate"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="user_id"
                                                            value="<?= (int)$organizer['user_id']; ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="action-btn btn-activate-user"
                                                            title="Reactivate Account"
                                                        >

                                                            <i class="bi bi-person-check-fill"></i>

                                                        </button>

                                                    </form>

                                                <?php else: ?>

                                                    <form
                                                        method="POST"
                                                        class="d-inline"
                                                        onsubmit="return confirm('Deactivate this organizer account?\\n\\nThe organizer will no longer be able to log in until the account is reactivated. Existing events will remain preserved.');"
                                                    >

                                                        <input
                                                            type="hidden"
                                                            name="csrf_token"
                                                            value="<?= e($csrfToken); ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="action"
                                                            value="deactivate"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="user_id"
                                                            value="<?= (int)$organizer['user_id']; ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="action-btn btn-deactivate-user"
                                                            title="Deactivate Account"
                                                        >

                                                            <i class="bi bi-person-dash-fill"></i>

                                                        </button>

                                                    </form>

                                                <?php endif; ?>


                                                <!-- DELETE -->

                                                <form
                                                    method="POST"
                                                    class="d-inline"
                                                    onsubmit="return confirmOrganizerDelete(<?= (int)$organizer['event_count']; ?>);"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= e($csrfToken); ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="action"
                                                        value="delete"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="user_id"
                                                        value="<?= (int)$organizer['user_id']; ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="action-btn btn-delete-user"
                                                        title="Delete"
                                                    >

                                                        <i class="bi bi-trash-fill"></i>

                                                    </button>

                                                </form>

                                            </div>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <div class="empty-state">

                        <i class="bi bi-person-x"></i>

                        <p>
                            No registered organizers found.
                        </p>

                    </div>

                <?php endif; ?>

            </div>

        </section>


        <!-- AUDIENCE -->

        <section class="section-card">

            <div class="section-header">

                <div>

                    <h2 class="section-title">

                        <i class="bi bi-people-fill text-warning"></i>

                        Registered Audience Users

                    </h2>

                    <p class="section-description">
                        Manage audience accounts and their system access.
                    </p>

                </div>


                <span class="badge bg-warning text-dark">

                    <?= count($audienceUsers); ?>

                    Audience User(s)

                </span>

            </div>


            <div class="section-body">

                <?php if (!empty($audienceUsers)): ?>

                    <div class="table-wrap">

                        <table
                            id="audienceTable"
                            class="table table-hover align-middle data-table"
                        >

                            <thead>

                                <tr>

                                    <th>#</th>

                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Contact</th>
                                    <th>Address</th>
                                    <th>Status</th>
                                    <th>Actions</th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach ($audienceUsers as $index => $audience): ?>

                                    <?php

                                    $status = trim(
                                        (string)(
                                            $audience['account_status'] ?? ''
                                        )
                                    );

                                    if ($status === '') {
                                        $status = 'Active';
                                    }

                                    $statusLower = strtolower($status);

                                    $isInactive = in_array(
                                        $statusLower,
                                        [
                                            'inactive',
                                            'deactivated',
                                            'blocked',
                                            'suspended'
                                        ],
                                        true
                                    );

                                    ?>

                                    <tr>

                                        <td>
                                            <?= $index + 1; ?>
                                        </td>


                                        <td>

                                            <span class="username-text">
                                                <?= e($audience['fullname'] ?: trim(($audience['firstname'] ?? '') . ' ' . ($audience['lastname'] ?? ''))); ?>
                                            </span>

                                        </td>


                                        <td>
                                            <?= e(
                                                $audience['email']
                                                ?: 'N/A'
                                            ); ?>
                                        </td>


                                        <td>
                                            <?= e(
                                                $audience['contact']
                                                ?: 'N/A'
                                            ); ?>
                                        </td>


                                        <td>
                                            <?= e(
                                                $audience['address']
                                                ?: 'N/A'
                                            ); ?>
                                        </td>


                                        <td>

                                            <?php if (!$isInactive): ?>

                                                <span class="badge bg-success status-badge">
                                                    Active
                                                </span>

                                            <?php else: ?>

                                                <span class="badge bg-danger status-badge">
                                                    Deactivated
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <td>

                                            <div class="action-buttons">

                                                <!-- VIEW -->

                                                <button
                                                    type="button"
                                                    class="action-btn btn-view-user"
                                                    title="View"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#viewUserModal"

                                                    data-role="Audience"
                                                    data-username="<?= e($audience['firstname']); ?>"
                                                    data-fullname="<?= e($audience['fullname']); ?>"
                                                    data-email="<?= e($audience['email']); ?>"
                                                    data-contact="<?= e($audience['contact']); ?>"
                                                    data-address="<?= e($audience['address']); ?>"
                                                    data-organization=""
                                                    data-status="<?= e($isInactive ? 'Deactivated' : 'Active'); ?>"
                                                    data-events="0"
                                                    data-participation="<?= (int)$audience['participation_count']; ?>"
                                                    data-reviews="<?= (int)$audience['review_count']; ?>"
                                                >

                                                    <i class="bi bi-eye-fill"></i>

                                                </button>


                                                <!-- EDIT -->

                                                <button
                                                    type="button"
                                                    class="action-btn btn-edit-user"
                                                    title="Edit"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editUserModal"

                                                    data-user-id="<?= (int)$audience['user_id']; ?>"
                                                    data-role="Audience"
                                                    data-username="<?= e($audience['firstname']); ?>"
                                                    data-fullname="<?= e($audience['fullname']); ?>"
                                                    data-email="<?= e($audience['email']); ?>"
                                                    data-contact="<?= e($audience['contact']); ?>"
                                                    data-address="<?= e($audience['address']); ?>"
                                                    data-organization=""
                                                >

                                                    <i class="bi bi-pencil-fill"></i>

                                                </button>


                                                <!-- ACTIVATE / DEACTIVATE -->

                                                <?php if ($isInactive): ?>

                                                    <form
                                                        method="POST"
                                                        class="d-inline"
                                                        onsubmit="return confirm('Reactivate this audience account?');"
                                                    >

                                                        <input
                                                            type="hidden"
                                                            name="csrf_token"
                                                            value="<?= e($csrfToken); ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="action"
                                                            value="activate"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="user_id"
                                                            value="<?= (int)$audience['user_id']; ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="action-btn btn-activate-user"
                                                            title="Reactivate Account"
                                                        >

                                                            <i class="bi bi-person-check-fill"></i>

                                                        </button>

                                                    </form>

                                                <?php else: ?>

                                                    <form
                                                        method="POST"
                                                        class="d-inline"
                                                        onsubmit="return confirm('Deactivate this audience account?\\n\\nThe audience user will no longer be able to log in until the account is reactivated.');"
                                                    >

                                                        <input
                                                            type="hidden"
                                                            name="csrf_token"
                                                            value="<?= e($csrfToken); ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="action"
                                                            value="deactivate"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="user_id"
                                                            value="<?= (int)$audience['user_id']; ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="action-btn btn-deactivate-user"
                                                            title="Deactivate Account"
                                                        >

                                                            <i class="bi bi-person-dash-fill"></i>

                                                        </button>

                                                    </form>

                                                <?php endif; ?>


                                                <!-- DELETE -->

                                                <form
                                                    method="POST"
                                                    class="d-inline"
                                                    onsubmit="return confirmAudienceDelete(
                                                        <?= (int)$audience['participation_count']; ?>,
                                                        <?= (int)$audience['review_count']; ?>
                                                    );"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= e($csrfToken); ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="action"
                                                        value="delete"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="user_id"
                                                        value="<?= (int)$audience['user_id']; ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="action-btn btn-delete-user"
                                                        title="Delete"
                                                    >

                                                        <i class="bi bi-trash-fill"></i>

                                                    </button>

                                                </form>

                                            </div>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php else: ?>

                    <div class="empty-state">

                        <i class="bi bi-person-x"></i>

                        <p>
                            No registered audience users found.
                        </p>

                    </div>

                <?php endif; ?>

            </div>

        </section>


    </main>

</div>


<!-- =========================================================
     VIEW USER MODAL
========================================================= -->

<div
    class="modal fade"
    id="viewUserModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered modal-lg">

        <div class="modal-content border-0 shadow">

            <div class="modal-header">

                <div class="d-flex align-items-center gap-3">

                    <div class="user-profile-modal-icon">

                        <i
                            id="viewUserIcon"
                            class="bi bi-person-fill"
                        ></i>

                    </div>

                    <div>

                        <h5
                            class="modal-title mb-0"
                            id="viewUserName"
                        >
                            User Details
                        </h5>

                        <small
                            class="text-muted"
                            id="viewUserRole"
                        >
                            Account
                        </small>

                    </div>

                </div>


                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>

            </div>


            <div class="modal-body">

                <div class="row g-4">

                    <div class="col-md-6">

                        <div class="detail-label">
                            First Name
                        </div>

                        <div
                            class="detail-value"
                            id="viewUsername"
                        >
                            —
                        </div>

                    </div>


                    <div class="col-md-6">

                        <div class="detail-label">
                            Full Name
                        </div>

                        <div
                            class="detail-value"
                            id="viewFullname"
                        >
                            —
                        </div>

                    </div>


                    <div class="col-md-6">

                        <div class="detail-label">
                            Email
                        </div>

                        <div
                            class="detail-value"
                            id="viewEmail"
                        >
                            —
                        </div>

                    </div>


                    <div class="col-md-6">

                        <div class="detail-label">
                            Contact
                        </div>

                        <div
                            class="detail-value"
                            id="viewContact"
                        >
                            —
                        </div>

                    </div>


                    <div class="col-12">

                        <div class="detail-label">
                            Address
                        </div>

                        <div
                            class="detail-value"
                            id="viewAddress"
                        >
                            —
                        </div>

                    </div>


                    <div
                        class="col-12"
                        id="viewOrganizationContainer"
                    >

                        <div class="detail-label">
                            Organization
                        </div>

                        <div
                            class="detail-value"
                            id="viewOrganization"
                        >
                            —
                        </div>

                    </div>


                    <div class="col-md-6">

                        <div class="detail-label">
                            Account Status
                        </div>

                        <div id="viewStatus">
                            —
                        </div>

                    </div>


                    <div class="col-md-6">

                        <div class="detail-label">
                            System Records
                        </div>

                        <div
                            class="detail-value"
                            id="viewRecords"
                        >
                            —
                        </div>

                    </div>

                </div>

            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-secondary"
                    data-bs-dismiss="modal"
                >
                    Close
                </button>

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     EDIT USER MODAL
========================================================= -->

<div
    class="modal fade"
    id="editUserModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered modal-lg">

        <div class="modal-content border-0 shadow">

            <form method="POST">

                <div class="modal-header">

                    <div>

                        <h5 class="modal-title">
                            Edit User Account
                        </h5>

                        <small
                            class="text-muted"
                            id="editUserRoleText"
                        >
                            Update account information
                        </small>

                    </div>


                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>

                </div>


                <div class="modal-body">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($csrfToken); ?>"
                    >


                    <input
                        type="hidden"
                        name="action"
                        value="edit"
                    >


                    <input
                        type="hidden"
                        name="user_id"
                        id="editUserId"
                        value=""
                    >


                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            First Name
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="editUsername"
                            readonly
                        >

                        <div class="form-text">
                            First name is displayed from the registered account.
                        </div>

                    </div>


                    <div class="row g-3">

                        <div class="col-md-6">

                            <label class="form-label fw-semibold">
                                Full Name
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                name="fullname"
                                id="editFullname"
                                required
                            >

                        </div>


                        <div class="col-md-6">

                            <label class="form-label fw-semibold">
                                Email
                            </label>

                            <input
                                type="email"
                                class="form-control"
                                name="email"
                                id="editEmail"
                                required
                            >

                        </div>


                        <div class="col-md-6">

                            <label class="form-label fw-semibold">
                                Contact
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                name="contact"
                                id="editContact"
                            >

                        </div>


                        <div class="col-md-6">

                            <label class="form-label fw-semibold">
                                Address
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                name="address"
                                id="editAddress"
                            >

                        </div>


                        <div
                            class="col-12"
                            id="editOrganizationContainer"
                        >

                            <label class="form-label fw-semibold">
                                Organization Name
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                name="organization_name"
                                id="editOrganization"
                            >

                        </div>

                    </div>

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-light"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>


                    <button
                        type="submit"
                        class="btn btn-primary"
                    >

                        <i class="bi bi-check-lg me-1"></i>

                        Save Changes

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>


<script>


/*
|--------------------------------------------------------------------------
| SIDEBAR
|--------------------------------------------------------------------------
*/

function toggleSidebar()
{
    const sidebar =
        document.getElementById('adminSidebar');

    const overlay =
        document.getElementById('sidebarOverlay');

    sidebar.classList.toggle('open');

    overlay.classList.toggle('show');
}


function closeSidebar()
{
    const sidebar =
        document.getElementById('adminSidebar');

    const overlay =
        document.getElementById('sidebarOverlay');

    sidebar.classList.remove('open');

    overlay.classList.remove('show');
}


/*
|--------------------------------------------------------------------------
| CLOSE SIDEBAR ON MOBILE MENU CLICK
|--------------------------------------------------------------------------
*/

document
    .querySelectorAll('.sidebar-link')
    .forEach(function(link) {

        link.addEventListener(
            'click',
            function() {

                if (window.innerWidth <= 991) {
                    closeSidebar();
                }

            }
        );

    });


/*
|--------------------------------------------------------------------------
| ORGANIZER DELETE CONFIRMATION
|--------------------------------------------------------------------------
*/

function confirmOrganizerDelete(eventCount)
{

    if (eventCount > 0) {

        alert(
            "This organizer has " +
            eventCount +
            " existing event(s).\n\n" +
            "The account cannot be permanently deleted " +
            "because doing so could affect event history.\n\n" +
            "Please use Deactivate instead."
        );

        return false;
    }


    return confirm(
        "Are you sure you want to permanently delete this organizer account?\n\n" +
        "This action cannot be undone."
    );
}


/*
|--------------------------------------------------------------------------
| AUDIENCE DELETE CONFIRMATION
|--------------------------------------------------------------------------
*/

function confirmAudienceDelete(
    participationCount,
    reviewCount
)
{

    if (
        participationCount > 0 ||
        reviewCount > 0
    ) {

        alert(
            "This audience account has existing system records.\n\n" +

            "Participation records: " +
            participationCount +

            "\nReview records: " +
            reviewCount +

            "\n\nThe account cannot be permanently deleted " +
            "because these records should be preserved.\n\n" +

            "Please use Deactivate instead."
        );

        return false;
    }


    return confirm(
        "Are you sure you want to permanently delete this audience account?\n\n" +
        "This action cannot be undone."
    );
}


/*
|--------------------------------------------------------------------------
| VIEW USER MODAL
|--------------------------------------------------------------------------
*/

document
    .querySelectorAll('.btn-view-user')
    .forEach(function(button) {

        button.addEventListener(
            'click',
            function() {

                const role =
                    this.dataset.role || 'User';

                const username =
                    this.dataset.username || 'N/A';

                const fullname =
                    this.dataset.fullname || 'N/A';

                const email =
                    this.dataset.email || 'N/A';

                const contact =
                    this.dataset.contact || 'N/A';

                const address =
                    this.dataset.address || 'N/A';

                const organization =
                    this.dataset.organization || '';

                const status =
                    this.dataset.status || 'Active';

                const events =
                    parseInt(
                        this.dataset.events || '0',
                        10
                    );

                const participation =
                    parseInt(
                        this.dataset.participation || '0',
                        10
                    );

                const reviews =
                    parseInt(
                        this.dataset.reviews || '0',
                        10
                    );


                document.getElementById(
                    'viewUserName'
                ).textContent = fullname;


                document.getElementById(
                    'viewUserRole'
                ).textContent =
                    role + " Account";


                document.getElementById(
                    'viewUsername'
                ).textContent = username;


                document.getElementById(
                    'viewFullname'
                ).textContent = fullname;


                document.getElementById(
                    'viewEmail'
                ).textContent = email;


                document.getElementById(
                    'viewContact'
                ).textContent = contact;


                document.getElementById(
                    'viewAddress'
                ).textContent = address;


                document.getElementById(
                    'viewOrganization'
                ).textContent =
                    organization || 'N/A';


                const statusElement =
                    document.getElementById(
                        'viewStatus'
                    );


                const lowerStatus =
                    status.toLowerCase();


                let statusClass =
                    'bg-secondary';


                let displayStatus =
                    status;


                if (
                    lowerStatus === 'active' ||
                    lowerStatus === 'approved'
                ) {

                    statusClass =
                        'bg-success';

                    displayStatus =
                        'Active';

                }
                else if (
                    lowerStatus === 'inactive' ||
                    lowerStatus === 'deactivated' ||
                    lowerStatus === 'blocked' ||
                    lowerStatus === 'suspended'
                ) {

                    statusClass =
                        'bg-danger';

                    displayStatus =
                        'Deactivated';
                }


                statusElement.innerHTML =
                    '<span class="badge ' +
                    statusClass +
                    ' status-badge">' +
                    escapeHtml(displayStatus) +
                    '</span>';


                const recordsElement =
                    document.getElementById(
                        'viewRecords'
                    );


                if (role === 'Organizer') {

                    recordsElement.textContent =
                        events +
                        " event(s)";

                }
                else {

                    recordsElement.textContent =
                        participation +
                        " participation(s), " +
                        reviews +
                        " review(s)";

                }


                const organizationContainer =
                    document.getElementById(
                        'viewOrganizationContainer'
                    );


                if (role === 'Organizer') {

                    organizationContainer.style.display =
                        'block';

                }
                else {

                    organizationContainer.style.display =
                        'none';

                }

            }
        );

    });


/*
|--------------------------------------------------------------------------
| EDIT USER MODAL
|--------------------------------------------------------------------------
*/

document
    .querySelectorAll('.btn-edit-user')
    .forEach(function(button) {

        button.addEventListener(
            'click',
            function() {

                const role =
                    this.dataset.role || 'Audience';


                document.getElementById(
                    'editUserId'
                ).value =
                    this.dataset.userId || '';


                document.getElementById(
                    'editUsername'
                ).value =
                    this.dataset.username || '';


                document.getElementById(
                    'editFullname'
                ).value =
                    this.dataset.fullname || '';


                document.getElementById(
                    'editEmail'
                ).value =
                    this.dataset.email || '';


                document.getElementById(
                    'editContact'
                ).value =
                    this.dataset.contact || '';


                document.getElementById(
                    'editAddress'
                ).value =
                    this.dataset.address || '';


                document.getElementById(
                    'editOrganization'
                ).value =
                    this.dataset.organization || '';


                document.getElementById(
                    'editUserRoleText'
                ).textContent =
                    "Editing " +
                    role +
                    " account";


                const organizationContainer =
                    document.getElementById(
                        'editOrganizationContainer'
                    );


                if (role === 'Organizer') {

                    organizationContainer.style.display =
                        'block';

                }
                else {

                    organizationContainer.style.display =
                        'none';

                }

            }
        );

    });


/*
|--------------------------------------------------------------------------
| ESCAPE HTML
|--------------------------------------------------------------------------
*/

function escapeHtml(value)
{

    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

}


/*
|--------------------------------------------------------------------------
| DATATABLES
|--------------------------------------------------------------------------
*/

$(document).ready(function() {


    $('#organizerTable').DataTable({

        pageLength: 5,

        lengthMenu: [
            [5, 10, 25, 50],
            [5, 10, 25, 50]
        ],

        order: [
            [0, 'asc']
        ],

        language: {

            search: "Search:",

            searchPlaceholder:
                "Search organizers...",

            emptyTable:
                "No organizer data available.",

            zeroRecords:
                "No matching organizers found."

        }

    });


    $('#audienceTable').DataTable({

        pageLength: 5,

        lengthMenu: [
            [5, 10, 25, 50],
            [5, 10, 25, 50]
        ],

        order: [
            [0, 'asc']
        ],

        language: {

            search: "Search:",

            searchPlaceholder:
                "Search audience...",

            emptyTable:
                "No audience data available.",

            zeroRecords:
                "No matching audience users found."

        }

    });

});


/*
|--------------------------------------------------------------------------
| ESC KEY
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'keydown',
    function(event) {

        if (event.key === 'Escape') {
            closeSidebar();
        }

    }
);

</script>


</body>

</html>