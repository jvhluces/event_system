<?php
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/event_helpers.php';

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Audience'
) {
    header("Location: ../auth/login_user.php");
    exit();
}

$userId = (int) $_SESSION['user_id'];
require_once __DIR__ . '/../organizer/notification_center.php';
$notificationCenter = organizer_notification_center($conn, $userId);

/* =========================================================
   SYSTEM SETTING
========================================================= */
function getUserSystemSetting($conn, $settingName, $default = '1')
{
    try {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT setting_value FROM system_settings WHERE setting_name = ? LIMIT 1"
        );

        if (!$stmt) {
            return $default;
        }

        mysqli_stmt_bind_param($stmt, "s", $settingName);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (!$result) {
            mysqli_stmt_close($stmt);
            return $default;
        }

        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$row || !isset($row['setting_value'])) {
            return $default;
        }

        return $row['setting_value'];
    } catch (Throwable $e) {
        return $default;
    }
}

$allowEventReviews = getUserSystemSetting($conn, 'allow_event_reviews', '1');
$reviewsEnabled = ((string) $allowEventReviews === '1');

/* =========================================================
   USER INFO
========================================================= */
$stmtUser = mysqli_prepare(
    $conn,
    "SELECT user_id, firstname, middlename, lastname, fullname, email, contact,
            address, organization_name, profile_pic, account_status
     FROM users
     WHERE user_id = ?
     LIMIT 1"
);

if (!$stmtUser) {
    die("Failed to load user information.");
}

mysqli_stmt_bind_param($stmtUser, "i", $userId);
mysqli_stmt_execute($stmtUser);
$resultUser = mysqli_stmt_get_result($stmtUser);
$user = mysqli_fetch_assoc($resultUser);
mysqli_stmt_close($stmtUser);

if (!$user) {
    session_destroy();
    header("Location: ../auth/login_user.php");
    exit();
}

if (($user['account_status'] ?? 'Active') !== 'Active') {
    session_unset();
    session_destroy();
    header("Location: ../auth/login_user.php?error=account_deactivated");
    exit();
}

$nameParts = array_filter([
    trim((string) ($user['firstname'] ?? '')),
    trim((string) ($user['middlename'] ?? '')),
    trim((string) ($user['lastname'] ?? ''))
], static fn($part) => $part !== '');

$displayName = trim((string) ($user['fullname'] ?? ''));
if ($displayName === '') {
    $displayName = trim(implode(' ', $nameParts));
}
if ($displayName === '') {
    $displayName = 'User';
}

$profilePic = trim((string) ($user['profile_pic'] ?? ''));

if ($profilePic !== '') {
    if (filter_var($profilePic, FILTER_VALIDATE_URL) || str_starts_with($profilePic, 'data:')) {
        $profileImage = $profilePic;
    } else {
        $profilePic = str_replace('\\', '/', $profilePic);
        $profilePic = ltrim($profilePic, '/');

        if (str_starts_with($profilePic, 'uploads/')) {
            $profileImage = '../' . $profilePic;
        } else {
            $profileImage = '../uploads/profiles/' . basename($profilePic);
        }
    }
} else {
    $profileImage = 'https://ui-avatars.com/api/?name=' . urlencode($displayName)
        . '&background=27ae60&color=fff&bold=true';
}

/* =========================================================
   FILTERS
========================================================= */
$categories = [
    'All', 'Wedding', 'Birthday', 'Meeting', 'Seminar', 'Workshop', 'Community Event'
];

$allowedStatuses = ['All', 'Upcoming', 'Ongoing', 'Joined', 'Ended'];

$activeView = $_GET['view'] ?? 'events';
if (!in_array($activeView, ['events', 'reviews'], true)) {
    $activeView = 'events';
}

$activeStatus   = $_GET['status'] ?? 'All';
$activeCategory = $_GET['category'] ?? 'All';
$search         = trim($_GET['search'] ?? '');

if (!in_array($activeStatus, $allowedStatuses, true)) {
    $activeStatus = 'All';
}

if ($activeCategory !== 'All' && !in_array($activeCategory, $categories, true)) {
    $activeCategory = 'All';
}

/* =========================================================
   EVENTS QUERY (Home, Upcoming, Ongoing, Joined / My Registrations, Ended)
========================================================= */
$sql = "
    SELECT
        e.event_id,
        e.event_name,
        e.description,
        e.event_image,
        e.event_date,
        e.end_date,
        e.location,
        e.capacity,
        e.category,
        e.status,
        e.approval_status,
        e.organizer_id,

        (
            SELECT ep5.joined_at
            FROM event_participants ep5
            WHERE ep5.event_id = e.event_id
              AND ep5.user_id = ?
            LIMIT 1
        ) AS joined_at,

        u.fullname AS organizer_name,
        u.organization_name,

        (
            SELECT COUNT(*)
            FROM event_participants ep2
            WHERE ep2.event_id = e.event_id
        ) AS participant_count,

        EXISTS (
            SELECT 1
            FROM event_participants ep3
            WHERE ep3.event_id = e.event_id
              AND ep3.user_id = ?
        ) AS is_joined,

        EXISTS (
            SELECT 1
            FROM event_reviews er
            WHERE er.event_id = e.event_id
              AND er.user_id = ?
        ) AS has_review

    FROM events e
    LEFT JOIN users u ON e.organizer_id = u.user_id
    WHERE e.approval_status = 'approved'
";

$params = [$userId, $userId, $userId];
$types  = "iii";

if ($activeStatus === 'Joined') {
    // My Registrations: lahat ng event na sinalihan ng user
    $sql .= "
        AND EXISTS (
            SELECT 1
            FROM event_participants ep4
            WHERE ep4.event_id = e.event_id
              AND ep4.user_id = ?
        )
    ";
    $params[] = $userId;
    $types   .= "i";

} elseif ($activeStatus !== 'All') {
    $sql .= "
        AND CASE
            WHEN e.event_date > NOW() THEN 'Upcoming'
            WHEN e.end_date >= NOW() THEN 'Ongoing'
            ELSE 'Ended'
        END = ?
    ";
    $params[] = $activeStatus;
    $types   .= "s";
}

if ($activeCategory !== 'All') {
    $sql .= " AND e.category = ? ";
    $params[] = $activeCategory;
    $types   .= "s";
}

if ($search !== '') {
    $searchLike = '%' . $search . '%';

    $sql .= "
        AND (
            e.event_name LIKE ?
            OR e.category LIKE ?
            OR e.location LIKE ?
            OR u.fullname LIKE ?
            OR u.organization_name LIKE ?
            OR CASE
                WHEN e.event_date > NOW() THEN 'Upcoming'
                WHEN e.end_date >= NOW() THEN 'Ongoing'
                ELSE 'Ended'
            END LIKE ?
        )
    ";

    for ($i = 0; $i < 6; $i++) {
        $params[] = $searchLike;
        $types   .= "s";
    }
}

// My Registrations: pinakabagong sumali ang nasa unahan
$sql .= ($activeStatus === 'Joined')
    ? " ORDER BY joined_at DESC, e.event_id DESC"
    : " ORDER BY e.event_id DESC";

$stmtEvents = mysqli_prepare($conn, $sql);

if (!$stmtEvents) {
    die("Event query failed: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmtEvents, $types, ...$params);
mysqli_stmt_execute($stmtEvents);
$resultEvents = mysqli_stmt_get_result($stmtEvents);

$events = [];

if ($resultEvents) {
    while ($row = mysqli_fetch_assoc($resultEvents)) {
        $events[] = $row;
    }
}

mysqli_stmt_close($stmtEvents);

/* =========================================================
   MY REVIEWS QUERY (mga review talaga ni user)
========================================================= */
$userReviews = [];

if ($activeView === 'reviews') {
    $reviewSql = "
        SELECT
            er.rating,
            er.feedback,
            e.event_id,
            e.event_name,
            e.event_image,
            e.event_date,
            e.location,
            e.category,
            u.fullname AS organizer_name,
            u.organization_name
        FROM event_reviews er
        INNER JOIN events e ON e.event_id = er.event_id
        LEFT JOIN users u ON u.user_id = e.organizer_id
        WHERE er.user_id = ?
    ";

    $reviewParams = [$userId];
    $reviewTypes  = "i";

    if ($search !== '') {
        $like = '%' . $search . '%';

        $reviewSql .= "
            AND (
                e.event_name LIKE ?
                OR e.location LIKE ?
                OR e.category LIKE ?
                OR er.feedback LIKE ?
                OR u.fullname LIKE ?
                OR u.organization_name LIKE ?
            )
        ";

        for ($i = 0; $i < 6; $i++) {
            $reviewParams[] = $like;
            $reviewTypes   .= "s";
        }
    }

    $reviewSql .= " ORDER BY e.event_date DESC, e.event_id DESC";

    $reviewStmt = mysqli_prepare($conn, $reviewSql);

    if ($reviewStmt) {
        mysqli_stmt_bind_param($reviewStmt, $reviewTypes, ...$reviewParams);
        mysqli_stmt_execute($reviewStmt);
        $reviewResult = mysqli_stmt_get_result($reviewStmt);

        if ($reviewResult) {
            while ($reviewRow = mysqli_fetch_assoc($reviewResult)) {
                $userReviews[] = $reviewRow;
            }
        }

        mysqli_stmt_close($reviewStmt);
    }
}

/* =========================================================
   HELPERS
========================================================= */
function getCategoryClass($category)
{
    return match ($category) {
        'Wedding'         => 'category-wedding',
        'Birthday'        => 'category-birthday',
        'Meeting'         => 'category-meeting',
        'Seminar'         => 'category-seminar',
        'Workshop'        => 'category-workshop',
        'Community Event' => 'category-community',
        default           => 'category-default'
    };
}

function getStatusClass($status)
{
    return match ($status) {
        'Upcoming' => 'status-upcoming',
        'Ongoing'  => 'status-ongoing',
        'Ended'    => 'status-ended',
        default    => 'status-default'
    };
}

function formatEventDate($date)
{
    if (!$date) {
        return 'Date not available';
    }

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return htmlspecialchars($date);
    }

    return date('F d, Y', $timestamp);
}

function formatEventTime($date)
{
    if (!$date) {
        return 'Time not available';
    }

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return '';
    }

    return date('h:i A', $timestamp);
}

function resolveImagePath($image)
{
    $image = trim((string) $image);

    if ($image === '') {
        return '';
    }

    $image = str_replace('\\', '/', $image);

    if (filter_var($image, FILTER_VALIDATE_URL) || str_starts_with($image, 'data:')) {
        return $image;
    }

    return '../' . ltrim($image, '/');
}

/* =========================================================
   SUCCESS / ERROR MESSAGES
========================================================= */
$successMessage = '';

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'joined':
            $successMessage = '<i class="bi bi-check-circle-fill"></i> You successfully joined the event.';
            break;
        case 'left':
            $successMessage = '<i class="bi bi-check-circle-fill"></i> You successfully left the event.';
            break;
    }
}

$errorMessage = '';

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'already_joined':
            $errorMessage = '<i class="bi bi-info-circle-fill"></i> You have already joined this event.';
            break;
        case 'event_full':
            $errorMessage = '<i class="bi bi-people-fill"></i> This event is already full.';
            break;
        case 'ended_event':
            $errorMessage = '<i class="bi bi-calendar-x-fill"></i> You cannot join an ended event.';
            break;
        case 'ongoing_event':
            $errorMessage = '<i class="bi bi-clock-fill"></i> You cannot join an ongoing event.';
            break;
        case 'not_joined':
            $errorMessage = '<i class="bi bi-info-circle-fill"></i> You are not a participant of this event.';
            break;
        case 'cannot_leave':
            $errorMessage = '<i class="bi bi-exclamation-circle-fill"></i> You cannot leave this event.';
            break;
        case 'review_not_available':
            $errorMessage = '<i class="bi bi-star-slash-fill"></i> Reviews are not available for this event.';
            break;
        case 'reviews_disabled':
            $errorMessage = '<i class="bi bi-star-slash"></i> Event reviews are currently disabled by the administrator.';
            break;
        case 'not_participant':
            $errorMessage = '<i class="bi bi-person-x-fill"></i> You must join the event before leaving a review.';
            break;
        case 'already_reviewed':
            $errorMessage = '<i class="bi bi-check-circle-fill"></i> You have already reviewed this event.';
            break;
        case 'event_not_found':
            $errorMessage = '<i class="bi bi-calendar-x-fill"></i> Event not found.';
            break;
        case 'join_disabled':
            $errorMessage = '<i class="bi bi-slash-circle-fill"></i> Joining events is currently disabled by the administrator. You cannot join any event at this time.';
            break;
        case 'join_failed':
            $errorMessage = '<i class="bi bi-exclamation-triangle-fill"></i> Failed to join the event. Please try again.';
            break;
        case 'leave_failed':
            $errorMessage = '<i class="bi bi-exclamation-triangle-fill"></i> Failed to leave the event. Please try again.';
            break;
        case 'review_failed':
            $errorMessage = '<i class="bi bi-exclamation-triangle-fill"></i> Failed to submit your review.';
            break;
    }
}

/* Page title depende sa kasalukuyang view */
if ($activeView === 'reviews') {
    $pageTitle    = 'My Reviews';
    $pageSubtitle = 'Your ratings and feedback for events';
} elseif ($activeStatus === 'Joined') {
    $pageTitle    = 'My Registrations';
    $pageSubtitle = 'Events you joined and when you registered';
} else {
    $pageTitle    = 'Events';
    $pageSubtitle = 'Discover and join upcoming events';
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> | Event System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/users/user.css">
</head>

<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- =========================================================
     SIDEBAR
========================================================= -->
<aside class="sidebar" id="sidebar">

    <div class="sidebar-header">
        <a href="user.php" class="brand">
            <img src="../assets/images/organizer-sidebar-logo.png" alt="Event MS">
        </a>
        <button type="button" class="close-sidebar" onclick="closeSidebar()" aria-label="Close menu">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <div class="sidebar-profile">
        <div class="sidebar-avatar">
            <img src="<?= htmlspecialchars($profileImage, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile">
        </div>

        <div class="sidebar-profile-info">
            <div class="sidebar-profile-name">
                <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <div class="sidebar-profile-role">Audience</div>
        </div>
        <i class="bi bi-chevron-down profile-chevron"></i>
    </div>

    <nav class="sidebar-menu">

        <div class="sidebar-label">Main</div>

        <a href="user.php?status=All&category=All"
           class="<?= ($activeView === 'events' && $activeStatus !== 'Joined') ? 'active' : ''; ?>">
            <i class="bi bi-house-door"></i>
            <span>Home</span>
        </a>

        <a href="user.php?status=Joined&category=All"
           class="<?= ($activeView === 'events' && $activeStatus === 'Joined') ? 'active' : ''; ?>">
            <i class="bi bi-bookmark-check"></i>
            <span>My Registrations</span>
        </a>

        <a href="user.php?view=reviews"
           class="<?= $activeView === 'reviews' ? 'active' : ''; ?>">
            <i class="bi bi-star"></i>
            <span>My Reviews</span>
        </a>

        <div class="sidebar-label">Account</div>

        <a href="user_profile.php">
            <i class="bi bi-person-circle"></i>
            <span>My Profile</span>
        </a>

        <a href="user_settings.php">
            <i class="bi bi-gear"></i>
            <span>Settings</span>
        </a>

        <a href="help_user.php">
            <i class="bi bi-question-circle"></i>
            <span>Help &amp; Support</span>
        </a>

        <a href="../auth/logout_user.php"
           class="logout-link"
           onclick="return confirm('Are you sure you want to logout?');">
            <i class="bi bi-box-arrow-right"></i>
            <span>Logout</span>
        </a>

    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-footer-line"></div>
        <p>Better Events.<br>Stronger Communities.</p>
    </div>

</aside>

<!-- =========================================================
     MAIN AREA
========================================================= -->
<div class="main-area">

    <!-- TOPBAR -->
    <nav class="top-navbar">

        <div class="topbar-left d-flex align-items-center gap-3">
            <button class="menu-btn" onclick="toggleSidebar()" type="button" aria-label="Open menu">
                <i class="bi bi-list"></i>
            </button>

            <div class="topbar-heading">
                <div class="page-title"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="page-subtitle"><?= htmlspecialchars($pageSubtitle, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
        </div>

        <form method="GET" class="topbar-search">

            <?php if ($activeView === 'reviews'): ?>
                <input type="hidden" name="view" value="reviews">
            <?php else: ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($activeStatus, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="category" value="<?= htmlspecialchars($activeCategory, ENT_QUOTES, 'UTF-8'); ?>">
            <?php endif; ?>

            <div class="input-group">
                <span class="input-group-text">
                    <i class="bi bi-search"></i>
                </span>

                <input
                    type="search"
                    name="search"
                    class="form-control"
                    placeholder="<?= $activeView === 'reviews' ? 'Search your reviews...' : 'Search events, organizers, location...'; ?>"
                    value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
                    aria-label="Search"
                >

                <?php if ($search !== ''): ?>
                    <?php
                    $clearParams = ($activeView === 'reviews')
                        ? ['view' => 'reviews']
                        : ['status' => $activeStatus, 'category' => $activeCategory];
                    ?>
                    <a href="<?= htmlspecialchars('user.php?' . http_build_query($clearParams), ENT_QUOTES, 'UTF-8'); ?>"
                       class="btn topbar-clear"
                       aria-label="Clear search"
                       title="Clear search">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>

                <button type="submit" class="btn topbar-search-btn">Search</button>
            </div>
        </form>

        <?php require __DIR__ . '/../organizer/notification_menu.php'; ?>

        <div class="topbar-user">
            <div class="topbar-avatar">
                <?= htmlspecialchars(strtoupper(substr($displayName, 0, 1)), ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <span><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
            <i class="bi bi-chevron-down"></i>
        </div>

    </nav>

    <!-- CONTENT -->
    <main class="main-content">
    <section class="content-area dashboard-section">

        <?php if ($successMessage !== ''): ?>
            <div class="alert alert-success custom-alert alert-dismissible fade show" role="alert">
                <?= $successMessage; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="alert alert-danger custom-alert alert-dismissible fade show" role="alert">
                <?= $errorMessage; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($activeView === 'reviews'): ?>

            <!-- =================================================
                 MY REVIEWS
            ================================================= -->
            <div class="section-heading mb-4">
                <div>
                    <h2 class="h4 mb-1">My Reviews</h2>
                    <p class="text-muted mb-0">Reviews and ratings you have submitted for events.</p>
                </div>
                <span class="review-count">
                    <?= count($userReviews); ?> review<?= count($userReviews) === 1 ? '' : 's'; ?>
                </span>
            </div>

            <?php if (empty($userReviews)): ?>
                <div class="empty-state">
                    <i class="bi bi-chat-square-heart"></i>
                    <h5>No Reviews Found</h5>
                    <p>
                        <?= $search !== ''
                            ? 'No reviews match your search.'
                            : 'You have not submitted a review for any event.'; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($userReviews as $review): ?>
                        <?php
                        $reviewImagePath = resolveImagePath($review['event_image'] ?? '');
                        $ratingValue = max(0, min(5, (int) ($review['rating'] ?? 0)));
                        $feedbackText = trim((string) ($review['feedback'] ?? ''));
                        ?>
                        <div class="col-xl-4 col-lg-6 col-md-6">
                            <article class="event-card h-100">

                                <?php if ($reviewImagePath !== ''): ?>
                                    <div class="event-image-wrapper">
                                        <img
                                            src="<?= htmlspecialchars($reviewImagePath, ENT_QUOTES, 'UTF-8'); ?>"
                                            alt="<?= htmlspecialchars($review['event_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                            class="event-image"
                                            onerror="this.parentElement.style.display='none';"
                                        >
                                    </div>
                                <?php endif; ?>

                                <div class="event-card-body review-card-body">

                                    <div class="event-top">
                                        <h3 class="event-title">
                                            <?= htmlspecialchars($review['event_name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </h3>
                                        <span class="event-category <?= getCategoryClass($review['category'] ?? ''); ?>">
                                            <?= htmlspecialchars($review['category'] ?? 'Event', ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </div>

                                    <div class="review-rating" aria-label="<?= $ratingValue; ?> out of 5 stars">
                                        <?php for ($star = 1; $star <= 5; $star++): ?>
                                            <i class="bi <?= $star <= $ratingValue ? 'bi-star-fill' : 'bi-star'; ?>"></i>
                                        <?php endfor; ?>
                                        <strong><?= $ratingValue; ?>/5</strong>
                                    </div>

                                    <div class="review-feedback">
                                        <?= nl2br(htmlspecialchars(
                                            $feedbackText !== '' ? $feedbackText : 'No written feedback provided.',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        )); ?>
                                    </div>

                                    <div class="review-event-meta">
                                        <div>
                                            <i class="bi bi-calendar3"></i>
                                            <?= formatEventDate($review['event_date'] ?? null); ?>
                                        </div>
                                        <div>
                                            <i class="bi bi-geo-alt"></i>
                                            <?= htmlspecialchars($review['location'] ?? 'Location not available', ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                        <div>
                                            <i class="bi bi-person-badge"></i>
                                            <?= htmlspecialchars(
                                                $review['organizer_name'] ?: ($review['organization_name'] ?: 'Organizer'),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>
                                        </div>
                                    </div>

                                    <a href="view_event.php?event_id=<?= (int) $review['event_id']; ?>"
                                       class="btn btn-outline-success btn-sm mt-3">
                                        <i class="bi bi-eye"></i> View Event
                                    </a>

                                </div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>

            <!-- =================================================
                 STATUS TABS
            ================================================= -->
            <div class="status-tabs">
                <?php foreach ($allowedStatuses as $statusTab): ?>
                    <?php
                    $tabParams = ['status' => $statusTab, 'category' => $activeCategory];

                    if ($search !== '') {
                        $tabParams['search'] = $search;
                    }

                    $tabUrl = 'user.php?' . http_build_query($tabParams);
                    ?>

                    <a href="<?= htmlspecialchars($tabUrl); ?>"
                       class="status-tab <?= $activeStatus === $statusTab ? 'active' : ''; ?>">

                        <?php if ($statusTab === 'All'): ?>
                            <i class="bi bi-grid"></i>
                        <?php elseif ($statusTab === 'Upcoming'): ?>
                            <i class="bi bi-calendar-event"></i>
                        <?php elseif ($statusTab === 'Ongoing'): ?>
                            <i class="bi bi-clock"></i>
                        <?php elseif ($statusTab === 'Joined'): ?>
                            <i class="bi bi-calendar-check"></i>
                        <?php else: ?>
                            <i class="bi bi-calendar-x"></i>
                        <?php endif; ?>

                        <?= htmlspecialchars($statusTab); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- =================================================
                 CATEGORIES
            ================================================= -->
            <div class="category-scroll">
                <?php foreach ($categories as $category): ?>
                    <?php
                    $categoryParams = ['status' => $activeStatus, 'category' => $category];

                    if ($search !== '') {
                        $categoryParams['search'] = $search;
                    }

                    $categoryUrl = 'user.php?' . http_build_query($categoryParams);
                    ?>

                    <a href="<?= htmlspecialchars($categoryUrl); ?>"
                       class="category-chip <?= $activeCategory === $category ? 'active' : ''; ?>">
                        <?= htmlspecialchars($category); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- =================================================
                 EVENTS / MY REGISTRATIONS
            ================================================= -->
            <?php if (empty($events)): ?>

                <div class="empty-state">
                    <i class="bi <?= $activeStatus === 'Joined' ? 'bi-bookmark-x' : 'bi-calendar2-x'; ?>"></i>
                    <h5><?= $activeStatus === 'Joined' ? 'No Registrations Yet' : 'No Events Found'; ?></h5>
                    <p>
                        <?= $activeStatus === 'Joined'
                            ? 'You have not joined any event yet.'
                            : 'There are no events matching your current filters.'; ?>
                    </p>
                </div>

            <?php else: ?>

                <div class="row g-4" id="events">

                    <?php foreach ($events as $event): ?>
                        <?php
                        $eventId = (int) $event['event_id'];

                        $eventStatus = event_lifecycle_status(
                            $event['event_date'] ?? '',
                            $event['end_date'] ?? null
                        );

                        $isJoined         = (int) $event['is_joined'] === 1;
                        $hasReview        = (int) $event['has_review'] === 1;
                        $participantCount = (int) $event['participant_count'];
                        $capacity         = (int) $event['capacity'];
                        $isFull           = $capacity > 0 && $participantCount >= $capacity;

                        $categoryClass = getCategoryClass($event['category']);
                        $statusClass   = getStatusClass($eventStatus);
                        $eventImagePath = resolveImagePath($event['event_image'] ?? '');
                        ?>

                        <div class="col-xl-4 col-lg-6 col-md-6">
                            <div class="event-card">

                                <?php if ($eventImagePath !== ''): ?>
                                    <div class="event-image-wrapper">
                                        <img
                                            src="<?= htmlspecialchars($eventImagePath, ENT_QUOTES, 'UTF-8'); ?>"
                                            alt="<?= htmlspecialchars($event['event_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                            class="event-image"
                                            onerror="this.parentElement.style.display='none';"
                                        >
                                    </div>
                                <?php endif; ?>

                                <div class="event-card-body">

                                    <div class="event-top">
                                        <h2 class="event-title">
                                            <?= htmlspecialchars($event['event_name']); ?>
                                        </h2>
                                        <span class="event-category <?= $categoryClass; ?>">
                                            <?= htmlspecialchars($event['category']); ?>
                                        </span>
                                    </div>

                                    <div class="event-organizer">
                                        <i class="bi bi-person-badge"></i>
                                        Organized by
                                        <strong>
                                            <?= htmlspecialchars(
                                                $event['organizer_name']
                                                ?: $event['organization_name']
                                                ?: 'Organizer'
                                            ); ?>
                                        </strong>
                                    </div>

                                    <?php if ($activeStatus === 'Joined' && !empty($event['joined_at'])): ?>
                                        <!-- KAILAN SUMALI SI USER -->
                                        <div class="registration-date">
                                            <i class="bi bi-bookmark-check-fill"></i>
                                            <span>
                                                Registered on
                                                <strong><?= date('F d, Y • h:i A', strtotime($event['joined_at'])); ?></strong>
                                            </span>
                                        </div>
                                    <?php endif; ?>

                                    <div class="event-info">

                                        <div class="event-info-item">
                                            <i class="bi bi-calendar3"></i>
                                            <span><?= formatEventDate($event['event_date']); ?></span>
                                        </div>

                                        <div class="event-info-item">
                                            <i class="bi bi-clock"></i>
                                            <span><?= formatEventTime($event['event_date']); ?></span>
                                        </div>

                                        <div class="event-info-item">
                                            <i class="bi bi-geo-alt"></i>
                                            <span><?= htmlspecialchars($event['location']); ?></span>
                                        </div>

                                        <div class="event-info-item">
                                            <i class="bi bi-people"></i>
                                            <span>
                                                <?= $participantCount; ?>
                                                participant<?= $participantCount == 1 ? '' : 's'; ?>
                                                <?php if ($capacity > 0): ?>
                                                    / <?= $capacity; ?>
                                                <?php endif; ?>
                                            </span>
                                        </div>

                                    </div>

                                    <div class="event-status-row">

                                        <span class="event-status <?= $statusClass; ?>">
                                            <?php if ($eventStatus === 'Upcoming'): ?>
                                                <i class="bi bi-calendar-check"></i>
                                            <?php elseif ($eventStatus === 'Ongoing'): ?>
                                                <i class="bi bi-broadcast"></i>
                                            <?php elseif ($eventStatus === 'Ended'): ?>
                                                <i class="bi bi-check-circle"></i>
                                            <?php endif; ?>

                                            <?= htmlspecialchars($eventStatus); ?>
                                        </span>

                                        <?php if ($eventStatus === 'Ongoing'): ?>
                                            <span class="small text-muted ms-2">Happening now</span>
                                        <?php endif; ?>

                                        <?php if ($isJoined): ?>
                                            <span class="small text-success ms-2">
                                                <i class="bi bi-check-circle-fill"></i>
                                                Joined
                                            </span>
                                        <?php endif; ?>

                                    </div>

                                    <div class="event-actions">

                                        <a href="view_event.php?event_id=<?= $eventId; ?>"
                                           class="btn btn-outline-success btn-sm">
                                            <i class="bi bi-eye"></i>
                                            View Details
                                        </a>

                                        <?php if ($eventStatus === 'Upcoming'): ?>

                                            <?php if ($isJoined): ?>

                                                <form method="POST"
                                                      action="../actions/event_action.php"
                                                      onsubmit="return confirm('Are you sure you want to leave this event?');"
                                                      class="d-inline">
                                                    <input type="hidden" name="action" value="leave">
                                                    <input type="hidden" name="event_id" value="<?= $eventId; ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                                        <i class="bi bi-box-arrow-left"></i>
                                                        Leave Event
                                                    </button>
                                                </form>

                                            <?php elseif ($isFull): ?>

                                                <button type="button" class="btn btn-secondary btn-sm" disabled>
                                                    <i class="bi bi-people"></i>
                                                    Event Full
                                                </button>

                                            <?php else: ?>

                                                <form method="POST"
                                                      action="../actions/event_action.php"
                                                      onsubmit="return confirm('Do you want to join this event?');"
                                                      class="d-inline">
                                                    <input type="hidden" name="action" value="join">
                                                    <input type="hidden" name="event_id" value="<?= $eventId; ?>">
                                                    <button type="submit" class="btn btn-success btn-sm">
                                                        <i class="bi bi-calendar-plus"></i>
                                                        Join Event
                                                    </button>
                                                </form>

                                            <?php endif; ?>

                                        <?php elseif ($eventStatus === 'Ongoing'): ?>

                                            <?php if ($isJoined): ?>
                                                <button type="button" class="btn btn-outline-success btn-sm" disabled>
                                                    <i class="bi bi-check-circle"></i>
                                                    You Joined
                                                </button>
                                            <?php endif; ?>

                                        <?php elseif ($eventStatus === 'Ended'): ?>

                                            <?php if ($isJoined): ?>

                                                <?php if (!$reviewsEnabled): ?>

                                                    <button type="button" class="btn btn-outline-secondary btn-sm" disabled>
                                                        <i class="bi bi-star-slash"></i>
                                                        Reviews Disabled
                                                    </button>

                                                <?php elseif (!$hasReview): ?>

                                                    <a href="../actions/review_event.php?event_id=<?= $eventId; ?>"
                                                       class="btn btn-warning btn-sm">
                                                        <i class="bi bi-star"></i>
                                                        Leave Review
                                                    </a>

                                                <?php else: ?>

                                                    <button type="button" class="btn btn-outline-secondary btn-sm" disabled>
                                                        <i class="bi bi-check-circle"></i>
                                                        Reviewed
                                                    </button>

                                                <?php endif; ?>

                                            <?php else: ?>

                                                <div class="event-review-notice"
                                                     title="Only participants who joined this event can leave a review.">
                                                    <i class="bi bi-info-circle"></i>
                                                    <span>
                                                        Review unavailable — you did not participate in this event.
                                                    </span>
                                                </div>

                                            <?php endif; ?>

                                        <?php endif; ?>

                                    </div>

                                </div>
                            </div>
                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        <?php endif; ?>

    </section>
    </main>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/users/user.js"></script>
<script src="../assets/js/organizer/notifications.js"></script>

</body>
</html>