<?php

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/profile_helpers.php';
require_once __DIR__ . '/../includes/event_helpers.php';


if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Organizer'
) {
    header("Location: ../auth/login_user.php");
    exit();
}

$organizerId = (int) $_SESSION['user_id'];



$stmtUser = mysqli_prepare(
    $conn,
    "SELECT
        user_id,
        firstname,
        lastname,
        email,
        contact,
        profile_pic
     FROM users
     WHERE user_id = ?
     LIMIT 1"
);

if (!$stmtUser) {
    die("Failed to prepare organizer query.");
}

mysqli_stmt_bind_param(
    $stmtUser,
    "i",
    $organizerId
);

mysqli_stmt_execute($stmtUser);

$resultUser = mysqli_stmt_get_result($stmtUser);

$organizer = mysqli_fetch_assoc($resultUser);

mysqli_stmt_close($stmtUser);


if (!$organizer) {

    session_destroy();

    header("Location: ../auth/login_user.php");
    exit();
}



$organizerName =
    !empty($organizer['firstname'])
        ? $organizer['firstname']
        : 'Organizer';



$profileImage = organizer_profile_image_url($organizer['profile_pic'] ?? '');

require_once __DIR__ . '/notification_center.php';
$notificationCenter = organizer_notification_center($conn, $organizerId);


$selectedEvent =
    isset($_GET['event_id'])
        ? (int) $_GET['event_id']
        : 0;

$selectedRating =
    isset($_GET['rating'])
        ? (int) $_GET['rating']
        : 0;



$totalReviews = 0;

$stmtTotal = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total
     FROM event_reviews er
     INNER JOIN events e
        ON er.event_id = e.event_id
     WHERE e.organizer_id = ?"
);

mysqli_stmt_bind_param(
    $stmtTotal,
    "i",
    $organizerId
);

mysqli_stmt_execute($stmtTotal);

$resultTotal = mysqli_stmt_get_result($stmtTotal);

$rowTotal = mysqli_fetch_assoc($resultTotal);

$totalReviews =
    (int) ($rowTotal['total'] ?? 0);

mysqli_stmt_close($stmtTotal);



$averageRating = 0;

$stmtAverage = mysqli_prepare(
    $conn,
    "SELECT AVG(er.rating) AS average_rating
     FROM event_reviews er
     INNER JOIN events e
        ON er.event_id = e.event_id
     WHERE e.organizer_id = ?"
);

mysqli_stmt_bind_param(
    $stmtAverage,
    "i",
    $organizerId
);

mysqli_stmt_execute($stmtAverage);

$resultAverage = mysqli_stmt_get_result($stmtAverage);

$rowAverage = mysqli_fetch_assoc($resultAverage);

$averageRating =
    (float) (
        $rowAverage['average_rating']
        ?? 0
    );

mysqli_stmt_close($stmtAverage);



$ratingCounts = [
    5 => 0,
    4 => 0,
    3 => 0,
    2 => 0,
    1 => 0
];

$stmtRatings = mysqli_prepare(
    $conn,
    "SELECT
        er.rating,
        COUNT(*) AS total
     FROM event_reviews er
     INNER JOIN events e
        ON er.event_id = e.event_id
     WHERE e.organizer_id = ?
     GROUP BY er.rating"
);

mysqli_stmt_bind_param(
    $stmtRatings,
    "i",
    $organizerId
);

mysqli_stmt_execute($stmtRatings);

$resultRatings = mysqli_stmt_get_result($stmtRatings);

while (
    $ratingRow =
    mysqli_fetch_assoc($resultRatings)
) {

    $rating =
        (int) $ratingRow['rating'];

    if (isset($ratingCounts[$rating])) {

        $ratingCounts[$rating] =
            (int) $ratingRow['total'];
    }
}

mysqli_stmt_close($stmtRatings);




$organizerEvents = [];

$stmtEvents = mysqli_prepare(
    $conn,
    "SELECT
        event_id,
        event_name,
        event_date,
        end_date,
        status
     FROM events
     WHERE organizer_id = ?
     ORDER BY event_date DESC"
);

mysqli_stmt_bind_param(
    $stmtEvents,
    "i",
    $organizerId
);

mysqli_stmt_execute($stmtEvents);

$resultEvents = mysqli_stmt_get_result($stmtEvents);

while (
    $event =
    mysqli_fetch_assoc($resultEvents)
) {
    $event['status'] = event_lifecycle_status($event['event_date'], $event['end_date']);
    $organizerEvents[] = $event;
}

mysqli_stmt_close($stmtEvents);



$reviewSql = "

    SELECT

        er.review_id,
        er.event_id,
        er.user_id,
        er.rating,
        er.feedback,
        er.created_at,

        e.event_name,
        e.event_date,
        e.end_date,
        e.status,

        u.fullname AS reviewer_name,
        u.email AS reviewer_email,
        u.profile_pic AS reviewer_profile

    FROM event_reviews er

    INNER JOIN events e
        ON er.event_id = e.event_id

    INNER JOIN users u
        ON er.user_id = u.user_id

    WHERE e.organizer_id = ?

";

$reviewParams = [
    $organizerId
];

$reviewTypes = "i";



if ($selectedEvent > 0) {

    $reviewSql .= "
        AND er.event_id = ?
    ";

    $reviewParams[] = $selectedEvent;

    $reviewTypes .= "i";
}



if (
    $selectedRating >= 1 &&
    $selectedRating <= 5
) {

    $reviewSql .= "
        AND er.rating = ?
    ";

    $reviewParams[] = $selectedRating;

    $reviewTypes .= "i";
}

$reviewSql .= "

    ORDER BY
        er.created_at DESC,
        er.review_id DESC

";


$stmtReviews = mysqli_prepare(
    $conn,
    $reviewSql
);

if (!$stmtReviews) {

    die(
        "Failed to prepare review query."
    );
}



$bindReviewValues = [];

$bindReviewValues[] = $reviewTypes;

foreach (
    $reviewParams
    as $key => $value
) {

    $bindReviewValues[] =
        &$reviewParams[$key];
}

call_user_func_array(
    [
        $stmtReviews,
        'bind_param'
    ],
    $bindReviewValues
);



mysqli_stmt_execute($stmtReviews);

$resultReviews =
    mysqli_stmt_get_result($stmtReviews);

$reviews = [];

if ($resultReviews) {

    while (
        $review =
        mysqli_fetch_assoc($resultReviews)
    ) {
        $review['status'] = event_lifecycle_status($review['event_date'], $review['end_date']);
        $reviews[] = $review;
    }
}

mysqli_stmt_close($stmtReviews);


$eventSummaries = [];

$stmtSummary = mysqli_prepare(
    $conn,
    "SELECT
        e.event_id,
        e.event_name,
        e.event_date,
        e.end_date,
        COUNT(er.review_id) AS review_count,
        AVG(er.rating) AS average_rating

     FROM events e

     LEFT JOIN event_reviews er
        ON e.event_id = er.event_id

     WHERE e.organizer_id = ?

     GROUP BY
        e.event_id,
        e.event_name,
        e.event_date,
        e.end_date

     HAVING review_count > 0

     ORDER BY
        average_rating DESC,
        review_count DESC"
);

mysqli_stmt_bind_param(
    $stmtSummary,
    "i",
    $organizerId
);

mysqli_stmt_execute($stmtSummary);

$resultSummary =
    mysqli_stmt_get_result($stmtSummary);

while (
    $summary =
    mysqli_fetch_assoc($resultSummary)
) {
    $summary['status'] = event_lifecycle_status($summary['event_date'], $summary['end_date']);
    $eventSummaries[] = $summary;
}

mysqli_stmt_close($stmtSummary);



function renderStars($rating)
{
    $rating = (int) $rating;

    $html = '';

    for ($i = 1; $i <= 5; $i++) {

        if ($i <= $rating) {

            $html .=
                '<i class="bi bi-star-fill"></i>';

        } else {

            $html .=
                '<i class="bi bi-star"></i>';
        }
    }

    return $html;
}


function getStatusBadgeClass($status)
{
    return match ($status) {

        'Upcoming'
            => 'bg-success',

        'Ongoing'
            => 'bg-primary',

        'Ended'
            => 'bg-secondary',

        default
            => 'bg-secondary'
    };
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

    <title>
        Event Reviews | Event System
    </title>


    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
    >


    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
    >
    
    


    

    <link rel="stylesheet" href="../assets/css/organizer/event_reviews.css">
    

</head>


<body class="organizer-layout">



<?php require __DIR__ . '/sidebar.php'; ?>







<nav class="top-navbar">

        <button
            type="button"
            class="menu-btn"
            onclick="openSidebar()"
            aria-label="Open menu"
        >
            <i class="bi bi-list"></i>
        </button>

        <div class="page-title">

            <i class="bi bi-star-fill"></i>

            Event Reviews

        </div>

        <div class="topbar-spacer"></div>

        <div class="topbar-user">
            <?php require __DIR__ . '/notification_menu.php'; ?>
            <div class="topbar-avatar">

                <?php if ($profileImage !== ''): ?>

                    <img
                        src="<?= htmlspecialchars($profileImage); ?>"
                        alt="Profile"
                    >

                <?php else: ?>

                    <i class="bi bi-person-fill text-secondary"></i>

                <?php endif; ?>

            </div>


            <span><?= htmlspecialchars($organizerName); ?></span>

        </div>

    </nav>



    <main class="main-content">



        <div class="page-header">

            <h1>

                Event Reviews

            </h1>

            <p>

                Monitor participant ratings and feedback
                for the events you created.

            </p>

        </div>



        <div class="row g-4">


            <!-- TOTAL REVIEWS -->

            <div class="col-xl-4 col-md-6">

                <div class="summary-card">

                    <div
                        class="summary-icon bg-primary bg-opacity-10 text-primary"
                    >

                        <i class="bi bi-chat-left-text"></i>

                    </div>


                    <div class="summary-number">

                        <?= $totalReviews; ?>

                    </div>


                    <div class="summary-label">

                        Total Reviews

                    </div>

                </div>

            </div>


            <div class="col-xl-4 col-md-6">

                <div class="summary-card">

                    <div
                        class="summary-icon bg-warning bg-opacity-10 text-warning"
                    >

                        <i class="bi bi-star-fill"></i>

                    </div>


                    <div class="rating-number">

                        <?= number_format(
                            $averageRating,
                            1
                        ); ?>

                        <span class="stars">

                            <?= renderStars(
                                round($averageRating)
                            ); ?>

                        </span>

                    </div>


                    <div class="summary-label">

                        Average Rating

                    </div>

                </div>

            </div>



            <div class="col-xl-4 col-md-12">

                <div class="summary-card">

                    <div
                        class="summary-icon bg-success bg-opacity-10 text-success"
                    >

                        <i class="bi bi-calendar-check"></i>

                    </div>


                    <div class="summary-number">

                        <?= count($eventSummaries); ?>

                    </div>


                    <div class="summary-label">

                        Events With Reviews

                    </div>

                </div>

            </div>


        </div>


        <div class="content-card">

            <div class="section-title">

                <i class="bi bi-bar-chart me-1"></i>

                Rating Distribution

            </div>


            <?php for (
                $rating = 5;
                $rating >= 1;
                $rating--
            ): ?>

                <?php

                $ratingCount =
                    $ratingCounts[$rating];

                $percentage =
                    $totalReviews > 0
                        ? (
                            $ratingCount /
                            $totalReviews
                        ) * 100
                        : 0;

                ?>


                <div class="rating-row">


                    <div class="rating-label">

                        <?= $rating; ?>

                        <i
                            class="bi bi-star-fill text-warning"
                        ></i>

                    </div>


                    <div class="rating-progress">

                        <div
                            class="rating-progress-bar"
                            style="width: <?= $percentage; ?>%;"
                        ></div>

                    </div>


                    <div class="rating-total">

                        <?= $ratingCount; ?>

                    </div>


                </div>


            <?php endfor; ?>

        </div>



        <div class="content-card">


            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

                <div class="section-title mb-0">

                    <i class="bi bi-bar-chart-line me-1"></i>

                    Event Rating Summary

                </div>


                <span class="small text-muted">

                    <?= count($eventSummaries); ?>

                    event<?= count($eventSummaries) === 1 ? '' : 's'; ?>

                </span>

            </div>


            <hr class="my-4">


            <?php if (empty($eventSummaries)): ?>


                <div class="empty-reviews">

                    <i class="bi bi-star"></i>

                    <h5>

                        No Reviews Yet

                    </h5>

                    <p class="mb-0">

                        Your events have not received any
                        reviews from participants yet.

                    </p>

                </div>


            <?php else: ?>


                <div class="row g-3">


                    <?php foreach (
                        $eventSummaries
                        as $summary
                    ): ?>


                        <div
                            class="col-xl-4 col-md-6"
                        >

                            <div class="event-summary-card">


                                <div class="event-summary-name">

                                    <?= htmlspecialchars(
                                        $summary['event_name']
                                    ); ?>

                                </div>


                                <span
                                    class="badge <?= getStatusBadgeClass(
                                        $summary['status']
                                    ); ?>"
                                >

                                    <?= htmlspecialchars(
                                        $summary['status']
                                    ); ?>

                                </span>


                                <div class="mt-3">

                                    <span class="stars">

                                        <?= renderStars(
                                            round(
                                                (float)
                                                $summary['average_rating']
                                            )
                                        ); ?>

                                    </span>


                                    <strong class="ms-2">

                                        <?= number_format(
                                            (float)
                                            $summary['average_rating'],
                                            1
                                        ); ?>

                                    </strong>

                                </div>


                                <small class="text-muted">

                                    <?= (int)
                                        $summary['review_count']; ?>

                                    review<?=

                                        (int)
                                        $summary['review_count'] === 1
                                            ? ''
                                            : 's';

                                    ?>

                                </small>


                                <div class="mt-3">

                                    <a
                                        href="event_reviews.php?event_id=<?= (int) $summary['event_id']; ?>"
                                        class="btn btn-outline-primary btn-sm"
                                    >

                                        <i class="bi bi-eye"></i>

                                        View Reviews

                                    </a>

                                </div>


                            </div>

                        </div>


                    <?php endforeach; ?>


                </div>


            <?php endif; ?>


        </div>



        <div class="content-card">


            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">

                <div class="section-title mb-0">

                    <i class="bi bi-chat-square-text me-1"></i>

                    Participant Reviews

                </div>


                <span class="text-muted small">

                    <?= count($reviews); ?>

                    review<?= count($reviews) === 1 ? '' : 's'; ?>

                    shown

                </span>

            </div>



            <div class="filter-box">


                <form
                    method="GET"
                    class="row g-3"
                >


                    <div class="col-lg-5 col-md-6">

                        <label
                            class="form-label small fw-bold"
                        >

                            Filter by Event

                        </label>


                        <select
                            name="event_id"
                            class="form-select"
                        >

                            <option value="0">

                                All Events

                            </option>


                            <?php foreach (
                                $organizerEvents
                                as $event
                            ): ?>


                                <option
                                    value="<?= (int) $event['event_id']; ?>"
                                    <?= $selectedEvent === (int) $event['event_id']
                                        ? 'selected'
                                        : ''; ?>
                                >

                                    <?= htmlspecialchars(
                                        $event['event_name']
                                    ); ?>

                                </option>


                            <?php endforeach; ?>


                        </select>

                    </div>


                    <div class="col-lg-4 col-md-6">

                        <label
                            class="form-label small fw-bold"
                        >

                            Filter by Rating

                        </label>


                        <select
                            name="rating"
                            class="form-select"
                        >

                            <option value="0">

                                All Ratings

                            </option>


                            <?php for (
                                $r = 5;
                                $r >= 1;
                                $r--
                            ): ?>


                                <option
                                    value="<?= $r; ?>"
                                    <?= $selectedRating === $r
                                        ? 'selected'
                                        : ''; ?>
                                >

                                    <?= $r; ?>

                                    Star<?= $r > 1 ? 's' : ''; ?>

                                </option>


                            <?php endfor; ?>


                        </select>

                    </div>


                    

                    <div class="col-lg-3 col-md-12 d-flex align-items-end gap-2">

                        <button
                            type="submit"
                            class="btn btn-primary flex-grow-1"
                        >

                            <i class="bi bi-funnel"></i>

                            Filter

                        </button>


                        <?php if (
                            $selectedEvent > 0 ||
                            ($selectedRating >= 1 && $selectedRating <= 5)
                        ): ?>

                            <a
                                href="event_reviews.php"
                                class="btn btn-outline-secondary"
                                title="Clear Filters"
                            >

                                <i class="bi bi-x-lg"></i>

                            </a>

                        <?php endif; ?>

                    </div>


                </form>


            </div>


            <?php if (empty($reviews)): ?>


                <div class="empty-reviews">

                    <i class="bi bi-chat-square-dots"></i>

                    <h5>

                        No Reviews Found

                    </h5>

                    <p class="mb-0">

                        There are no participant reviews
                        matching the selected filters.

                    </p>

                </div>


            <?php else: ?>


                <?php foreach (
                    $reviews
                    as $review
                ): ?>


                    <div class="review-card">



                        <div class="review-header">


                            <div class="reviewer">


                                <div class="reviewer-avatar">


                                    <?php if (
                                        !empty(
                                            $review['reviewer_profile']
                                        )
                                    ): ?>


                                        <img
                                            src="<?= htmlspecialchars(
                                                $review['reviewer_profile']
                                            ); ?>"
                                            alt="Reviewer"
                                        >


                                    <?php else: ?>


                                        <i class="bi bi-person-fill text-secondary"></i>


                                    <?php endif; ?>


                                </div>


                                <div>


                                    <div class="reviewer-name">

                                        <?= htmlspecialchars(
                                            $review['reviewer_name']
                                        ); ?>

                                    </div>


                                    <div class="reviewer-email">

                                        <?= htmlspecialchars(
                                            $review['reviewer_email']
                                        ); ?>

                                    </div>


                                </div>


                            </div>


                            <div class="review-date">

                                <?= date(
                                    'M d, Y h:i A',
                                    strtotime(
                                        $review['created_at']
                                    )
                                ); ?>

                            </div>


                        </div>



                        <div class="review-event">


                            <div class="small text-muted mb-1">

                                Review for

                            </div>


                            <div class="review-event-name">


                                <i
                                    class="bi bi-calendar-event text-primary me-1"
                                ></i>


                                <?= htmlspecialchars(
                                    $review['event_name']
                                ); ?>


                                <span
                                    class="badge <?= getStatusBadgeClass(
                                        $review['status']
                                    ); ?> ms-2"
                                >

                                    <?= htmlspecialchars(
                                        $review['status']
                                    ); ?>

                                </span>


                            </div>


                        </div>



                        <div class="mt-3">


                            <span class="stars">

                                <?= renderStars(
                                    $review['rating']
                                ); ?>

                            </span>


                            <strong class="ms-2">

                                <?= (int)
                                    $review['rating']; ?>

                                / 5

                            </strong>


                        </div>



                        <div class="review-feedback">


                            <?php if (
                                trim(
                                    (string)
                                    $review['feedback']
                                ) !== ''
                            ): ?>


                                <i
                                    class="bi bi-quote text-primary me-1"
                                ></i>


                                <?= nl2br(
                                    htmlspecialchars(
                                        $review['feedback']
                                    )
                                ); ?>


                            <?php else: ?>


                                <span class="review-feedback-empty">

                                    No written feedback was provided.

                                </span>


                            <?php endif; ?>


                        </div>


                    </div>


                <?php endforeach; ?>


            <?php endif; ?>


        </div>


    </main>

<script src="../assets/js/organizer/event_reviews.js"></script>
<script src="../assets/js/organizer/notifications.js"></script>


</body>

</html>