<?php
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/event_helpers.php';
require_once __DIR__ . '/profile_helpers.php';

if (!function_exists('e')) {
    function e($value): string {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'Organizer') {
    header('Location: ../auth/login_user.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$userQuery = mysqli_query($conn, "SELECT * FROM users WHERE user_id = {$user_id} LIMIT 1");

if (!$userQuery || mysqli_num_rows($userQuery) === 0) {
    session_unset();
    session_destroy();
    header('Location: ../auth/login_user.php');
    exit();
}

$userData = mysqli_fetch_assoc($userQuery);
$accountStatus = $userData['account_status'] ?? 'Active';

if (strcasecmp((string)$accountStatus, 'Active') !== 0) {
    session_unset();
    session_destroy();
    header('Location: ../auth/login_user.php?account=inactive');
    exit();
}

$userName = trim((string)($userData['firstname'] ?? $userData['fullname'] ?? 'Organizer'));
if ($userName === '') $userName = 'Organizer';
$profileImage = function_exists('organizer_profile_image_url')
    ? organizer_profile_image_url($userData['profile_pic'] ?? '')
    : '';

$organizerName = $userName;
require_once __DIR__ . '/notification_center.php';
$notificationCenter = organizer_notification_center($conn, $user_id);

$categories = [
    'Wedding' => ['icon' => 'heart', 'tone' => 'rose', 'description' => 'Create and manage elegant weddings and special celebrations.', 'image' => 'photo-1519741497674-611481863552'],
    'Party' => ['icon' => 'balloon', 'tone' => 'violet', 'description' => 'Plan birthdays, anniversaries and other festive events.', 'image' => 'photo-1530103862676-de8c9de выс'],
    'Meeting' => ['icon' => 'people', 'tone' => 'blue', 'description' => 'Organize business meetings, conferences and gatherings.', 'image' => 'photo-1497366754035-f200968a6e72'],
    'Seminar' => ['icon' => 'mortarboard', 'tone' => 'teal', 'description' => 'Host educational seminars and professional talks.', 'image' => 'photo-1475721027785-f74eccf877e2'],
    'Workshop' => ['icon' => 'tools', 'tone' => 'amber', 'description' => 'Conduct hands-on workshops and skill-building sessions.', 'image' => 'photo-1516321318423-f06f85e504b3'],
    'Community Event' => ['icon' => 'people-fill', 'tone' => 'green', 'description' => 'Support local events and community programs.', 'image' => 'photo-1499750310107-5fef28a66643']
];

/* Correct a safe URL slug for the party image. */
$categories['Party']['image'] = 'photo-1530103862676-de8c9debad1d';

function organizerCount($conn, int $uid, string $sqlCondition = ''): int {
    $sql = "SELECT COUNT(*) AS total FROM events WHERE organizer_id = {$uid}";
    if ($sqlCondition !== '') $sql .= " AND {$sqlCondition}";
    $result = mysqli_query($conn, $sql);
    if (!$result) return 0;
    $row = mysqli_fetch_assoc($result);
    return (int)($row['total'] ?? 0);
}

$totalEvents = organizerCount($conn, $user_id);
$upcomingEvents = organizerCount($conn, $user_id, "event_date > NOW()");
$ongoingEvents = organizerCount($conn, $user_id, "event_date <= NOW() AND end_date >= NOW()");

/* Reviews are optional across project versions; don't break the dashboard if the table is absent. */
$totalReviews = 0;
$reviewTableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'event_reviews'");
if ($reviewTableCheck && mysqli_num_rows($reviewTableCheck) > 0) {
    $reviewQuery = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total FROM event_reviews er
         INNER JOIN events ev ON ev.event_id = er.event_id
         WHERE ev.organizer_id = {$user_id}"
    );
    if ($reviewQuery) {
        $reviewRow = mysqli_fetch_assoc($reviewQuery);
        $totalReviews = (int)($reviewRow['total'] ?? 0);
    }
} else {
    $reviewTableCheck2 = mysqli_query($conn, "SHOW TABLES LIKE 'event_participants'");
    if ($reviewTableCheck2 && mysqli_num_rows($reviewTableCheck2) > 0) {
        /* Keep zero when this project version stores reviews in another table. */
        $totalReviews = 0;
    }
}

$searchEvents = [];
$searchQuery = mysqli_query(
    $conn,
    "SELECT event_id, event_name, description, event_date, end_date, location, category,
            status, approval_status, admin_remarks
     FROM events WHERE organizer_id = {$user_id} ORDER BY event_date ASC"
);
if ($searchQuery) {
    while ($event = mysqli_fetch_assoc($searchQuery)) $searchEvents[] = $event;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Organizer Dashboard | Event MS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    

    <link rel="stylesheet" href="../assets/css/organizer/org_dash.css">
</head>
<body class="organizer-layout">
<?php require __DIR__ . '/sidebar.php'; ?>



<nav class="top-navbar">
    <button type="button" class="menu-btn" onclick="toggleSidebar()" aria-label="Open menu"><i class="bi bi-list"></i></button>
    <div class="topbar-spacer"></div>
    <?php require __DIR__ . '/notification_menu.php'; ?>
    <div class="topbar-user">
        <div class="topbar-avatar"><?= e(strtoupper(substr($userName, 0, 1))); ?></div>
        <span><?= e($userName); ?></span>
        <i class="bi bi-chevron-down"></i>
    </div>
</nav>

<main class="main-content">
    <section id="home" class="dashboard-section">
        <div class="welcome-card">
            <div class="welcome-copy">
                <div class="eyebrow">ORGANIZER DASHBOARD</div>
                <h1> Welcome <?= e($userName); ?>! <span class="wave"></span></h1>
                <p>Here's what's happening with your events today.</p>
            </div>
            <div class="welcome-art" aria-hidden="true">
                <div class="art-orb orb-one"></div><div class="art-orb orb-two"></div>
                <div class="calendar-illustration">
                    <div class="calendar-rings"><i></i><i></i></div>
                    <div class="calendar-top"></div>
                    <div class="calendar-grid"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
                    <span class="calendar-check"><i class="bi bi-check2"></i></span>
                </div>
                <div class="plant-pot"><span class="leaf leaf-a"></span><span class="leaf leaf-b"></span><span class="leaf leaf-c"></span><i></i></div>
            </div>
        </div>

        <div class="stats-grid">
            <a class="stat-card" href="../organizer/all_events.php">
                <span class="stat-icon stat-blue"><i class="bi bi-calendar-event"></i></span>
                <span class="stat-copy"><span class="stat-label">Total Events</span><strong><?= $totalEvents; ?></strong><small>All created events</small></span>
                <i class="bi bi-chevron-right stat-chevron"></i>
            </a>
            <a class="stat-card" href="../organizer/all_events.php?status=Upcoming">
                <span class="stat-icon stat-green"><i class="bi bi-clock-history"></i></span>
                <span class="stat-copy"><span class="stat-label">Upcoming</span><strong><?= $upcomingEvents; ?></strong><small>Events coming soon</small></span>
                <i class="bi bi-chevron-right stat-chevron"></i>
            </a>
            <a class="stat-card" href="../organizer/all_events.php?status=Ongoing">
                <span class="stat-icon stat-orange"><i class="bi bi-play"></i></span>
                <span class="stat-copy"><span class="stat-label">Ongoing</span><strong><?= $ongoingEvents; ?></strong><small>Currently happening</small></span>
                <i class="bi bi-chevron-right stat-chevron"></i>
            </a>
            <a class="stat-card" href="../organizer/event_reviews.php">
                <span class="stat-icon stat-purple"><i class="bi bi-star"></i></span>
                <span class="stat-copy"><span class="stat-label">Reviews</span><strong><?= $totalReviews; ?></strong><small>Total event reviews</small></span>
                <i class="bi bi-chevron-right stat-chevron"></i>
            </a>
        </div>

        <section class="dashboard-events card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
                    <div>
                        <h2 class="h5 mb-1">Your event approvals</h2>
                        <p class="text-muted small mb-0">Approval and schedule status for your events.</p>
                    </div>
                    <a class="btn btn-sm btn-outline-primary" href="all_events.php">Manage events</a>
                </div>
                <?php if ($searchEvents === []): ?>
                    <p class="text-muted mb-0">You have not created any events yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th>Event</th><th>Schedule status</th><th>Approval</th><th>Admin remarks</th></tr></thead>
                            <tbody>
                            <?php foreach ($searchEvents as $dashboardEvent): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($dashboardEvent['event_name']); ?></strong>
                                        <div class="small text-muted"><?= e($dashboardEvent['event_date']); ?></div>
                                    </td>
                                    <td><?= event_lifecycle_badge($dashboardEvent['event_date'], $dashboardEvent['end_date']); ?></td>
                                    <td><?= event_approval_badge($dashboardEvent['approval_status']); ?></td>
                                    <td><?= $dashboardEvent['approval_status'] === 'rejected' ? e($dashboardEvent['admin_remarks']) : '<span class="text-muted">—</span>'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <div class="search-row">
            <div class="search-box-wrapper">
                <i class="bi bi-search search-box-icon"></i>
                <input type="search" id="eventSearchInput" class="event-search-input" placeholder="Search events by name, category, or location..." autocomplete="off" aria-label="Search events">
                <button type="button" id="clearSearchBtn" class="clear-search-btn" title="Clear search" aria-label="Clear search"><i class="bi bi-x-circle-fill"></i></button>
            </div>
            <a class="filter-button" href="../organizer/all_events.php"><i class="bi bi-funnel-fill"></i> Filter</a>
        </div>
        <div id="searchResults" class="search-results"></div>

        <section class="category-section">
            <div class="category-section-header">
                <div>
                    <h2><i class="bi bi-calendar-event section-heading-icon"></i> Event Categories</h2>
                    <p>Choose a category to view and manage its events.</p>
                </div>
            </div>

            <div class="category-grid">
                <?php foreach ($categories as $categoryName => $categoryInfo):
                    $safeCategory = rawurlencode($categoryName);
                    $eventTotal = organizerCount($conn, $user_id, "category = '" . mysqli_real_escape_string($conn, $categoryName) . "'");
                ?>
                <a class="category-card tone-<?= e($categoryInfo['tone']); ?>" href="category_events.php?category=<?= $safeCategory; ?>">
                    <span class="category-icon"><i class="bi bi-<?= e($categoryInfo['icon']); ?>"></i></span>
                    <span class="category-copy">
                        <strong><?= e($categoryName); ?></strong>
                        <span><?= e($categoryInfo['description']); ?></span>
                        <small>View events <i class="bi bi-arrow-right"></i></small>
                    </span>
                    <span class="category-photo photo-<?= e($categoryInfo['tone']); ?>" role="img" aria-label="<?= e($categoryName); ?> event photo"></span>
                    <span class="category-arrow"><i class="bi bi-arrow-right"></i></span>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
    </section>
    <footer class="dashboard-footer">
        <span><strong>Event MS</strong><span class="footer-dot">•</span> Organizer Portal</span>
        <span><a href="../organizer/all_events.php">Manage</a><span>•</span><a href="../add.php">Create</a><span>•</span><span>Build Community</span></span>
    </footer>
</main>

<script>
const organizerEvents = <?= json_encode($searchEvents, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_APOS); ?>;

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    if (sidebar) sidebar.classList.toggle('active');
    if (overlay) overlay.classList.toggle('show');
}
function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    if (sidebar) sidebar.classList.remove('active', 'open', 'show');
    if (overlay) overlay.classList.remove('show', 'active');
}
function showSection(id, link) {
    document.querySelectorAll('.sidebar-menu a').forEach(a => a.classList.remove('active'));
    if (link) link.classList.add('active');
    const section = document.getElementById(id);
    if (section) section.scrollIntoView({behavior:'smooth', block:'start'});
}

(function () {
    const input = document.getElementById('eventSearchInput');
    const clear = document.getElementById('clearSearchBtn');
    const results = document.getElementById('searchResults');
    if (!input || !results) return;

    const esc = value => String(value ?? '').replace(/[&<>"']/g, ch => ({
        '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;'
    }[ch]));

    function render() {
        const query = input.value.trim().toLowerCase();
        if (clear) clear.style.display = query ? 'inline-flex' : 'none';
        if (!query) {
            results.classList.remove('show');
            results.innerHTML = '';
            return;
        }
        const matches = (Array.isArray(organizerEvents) ? organizerEvents : []).filter(event =>
            [event.event_name, event.category, event.location, event.status, event.description]
                .some(value => String(value ?? '').toLowerCase().includes(query))
        );
        results.classList.add('show');
        results.innerHTML = `<div class="search-result-count">${matches.length} matching event${matches.length === 1 ? '' : 's'}</div>` +
            (matches.length ? matches.map(event => `
                <a class="search-result-item" href="../organizer/all_events.php">
                    <span class="search-result-main">
                        <span class="search-result-title">${esc(event.event_name)}</span>
                        <span class="search-result-details">
                            <span><i class="bi bi-tag"></i> ${esc(event.category)}</span>
                            <span><i class="bi bi-geo-alt"></i> ${esc(event.location)}</span>
                            <span><i class="bi bi-calendar3"></i> ${esc(event.event_date)}</span>
                        </span>
                    </span>
                    <span class="search-result-status">${esc(event.status)}</span>
                </a>`).join('') : '<div class="search-empty"><i class="bi bi-search"></i>No matching events found.</div>');
    }
    input.addEventListener('input', render);
    if (clear) clear.addEventListener('click', () => { input.value = ''; render(); input.focus(); });
})();
</script>
<script src="../assets/js/organizer/notifications.js"></script>
</body>
</html>
