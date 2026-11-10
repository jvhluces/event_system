
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

$user_id = (int) $_SESSION['user_id'];
require_once __DIR__ . '/notification_center.php';
$notificationCenter = organizer_notification_center($conn, $user_id);

function e($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

/* Organizer information */
$stmt = mysqli_prepare(
    $conn,
    "SELECT firstname, fullname, profile_pic
     FROM users
     WHERE user_id = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$organizer = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$organizerName = trim((string) ($organizer['fullname'] ?? ''));

if ($organizerName === '') {
    $organizerName = trim((string) ($organizer['firstname'] ?? 'Organizer'));
}

if ($organizerName === '') {
    $organizerName = 'Organizer';
}

$profileImage = organizer_profile_image_url(
    $organizer['profile_pic'] ?? ''
);

/* Flash messages */
$success = $_SESSION['archive_page_success'] ?? '';
$error = $_SESSION['archive_page_error'] ?? '';

unset(
    $_SESSION['archive_page_success'],
    $_SESSION['archive_page_error']
);

/* Fetch only this organizer's archived events */
$stmt = mysqli_prepare(
    $conn,
    "SELECT *
     FROM events
     WHERE organizer_id = ?
       AND is_archived = 1
     ORDER BY event_date DESC"
);

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$eventsResult = mysqli_stmt_get_result($stmt);
$archivedEvents = [];

while ($row = mysqli_fetch_assoc($eventsResult)) {
    $archivedEvents[] = $row;
}

mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Archived Events | Event MS</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
        rel="stylesheet"
    >

    
    <link rel="stylesheet" href="../assets/css/organizer/archived_events.css">

    
</head>

<body class="organizer-layout">
<?php require __DIR__ . '/sidebar.php'; ?>



<nav class="top-navbar">
    <button
        type="button"
        class="menu-btn"
        onclick="toggleSidebar()"
        aria-label="Open menu"
    >
        <i class="bi bi-list"></i>
    </button>

    <span class="category-topbar-title">Archived Events</span>

    <div class="topbar-spacer"></div>
    <?php require __DIR__ . '/notification_menu.php'; ?>

    <div class="topbar-user">
        <div class="topbar-avatar">
            <?= e(strtoupper(substr($organizerName, 0, 1))); ?>
        </div>
        <span><?= e($organizerName); ?></span>
        <i class="bi bi-chevron-down"></i>
    </div>
</nav>

<main class="main-content archive-page">
    <?php if ($success !== ''): ?>
        <div class="alert alert-success archive-alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?= e($success); ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger archive-alert">
            <i class="bi bi-exclamation-circle-fill me-2"></i>
            <?= e($error); ?>
        </div>
    <?php endif; ?>

    <header class="archive-heading">
        <div>
            <h1>
                <span class="archive-heading-icon">
                    <i class="bi bi-archive-fill"></i>
                </span>
                Archived Events
            </h1>

            <p>
                Review the ended events you have archived.
                These events are hidden from your regular event listings.
            </p>
        </div>

        <div class="archive-count">
            <i class="bi bi-archive"></i>
            <?= count($archivedEvents); ?>
            <?= count($archivedEvents) === 1 ? 'Archived Event' : 'Archived Events'; ?>
        </div>
    </header>

    <?php if (empty($archivedEvents)): ?>
        <section class="archive-empty">
            <div class="archive-empty-icon">
                <i class="bi bi-archive"></i>
            </div>

            <h2>No Archived Events Yet</h2>

            <p>
                When you archive an ended event, it will appear here.
                Your archived events will remain saved in the database.
            </p>

            <a href="org_dash.php" class="btn btn-primary mt-4">
                <i class="bi bi-arrow-left me-1"></i>
                Back to Dashboard
            </a>
        </section>
    <?php else: ?>
        <section class="archive-grid" aria-label="Your archived events">
            <?php foreach ($archivedEvents as $event): ?>
                <article class="archive-card">
                    <div class="archive-card-top">
                        <span class="archive-category">
                            <i class="bi bi-calendar-event"></i>
                            <?= e($event['category']); ?>
                        </span>

                        <span class="archive-status">
                            <i class="bi bi-archive-fill"></i>
                            Archived
                        </span>
                        <?= event_lifecycle_badge($event['event_date'], $event['end_date']); ?>
                        <?= event_approval_badge($event['approval_status'] ?? 'pending'); ?>
                    </div>

                    <div class="archive-card-body">
                        <h2><?= e($event['event_name']); ?></h2>

                        <div class="archive-detail">
                            <i class="bi bi-calendar3"></i>
                            <span>
                                <?php
                                $timestamp = strtotime($event['event_date']);
                                echo $timestamp !== false
                                    ? e(date('F j, Y', $timestamp))
                                    : 'Date unavailable';
                                ?>
                            </span>
                        </div>

                        <div class="archive-detail">
                            <i class="bi bi-clock"></i>
                            <span>
                                <?php
                                $timestamp = strtotime($event['event_date']);
                                echo $timestamp !== false
                                    ? e(date('g:i A', $timestamp))
                                    : 'Time unavailable';
                                ?>
                            </span>
                        </div>

                        <div class="archive-detail">
                            <i class="bi bi-geo-alt"></i>
                            <span><?= e($event['location'] ?? 'No location provided'); ?></span>
                        </div>

                        <?php if (!empty($event['description'])): ?>
                            <p class="archive-description">
                                <?= e($event['description']); ?>
                            </p>
                        <?php endif; ?>

                        <div class="archive-card-footer">
                            <a
                                href="category_events.php?category=<?= urlencode($event['category']); ?>"
                                class="btn btn-outline-primary"
                            >
                                <i class="bi bi-folder2-open me-1"></i>
                                View Category
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/organizer/notifications.js"></script>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');

    if (sidebar) sidebar.classList.toggle('active');
    if (overlay) overlay.classList.toggle('show');
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');

    if (sidebar) {
        sidebar.classList.remove('active', 'open', 'show');
    }

    if (overlay) {
        overlay.classList.remove('show', 'active');
    }
}
</script>
</body>
</html>
