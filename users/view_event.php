<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/event_helpers.php';

/* ==========================================================
   ACCESS: Papayagan na ang Audience AT Organizer
========================================================== */
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    !in_array($_SESSION['role'], ['Audience', 'Organizer'])
) {
    header("Location: ../auth/login_user.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
require_once __DIR__ . '/../organizer/notification_center.php';
$notificationCenter = organizer_notification_center($conn, $user_id);

/* ==========================================================
   EVENT ID — gumagamit ng event_id
========================================================== */
$event_id = isset($_GET['event_id'])
    ? (int)$_GET['event_id']
    : 0;

if ($event_id <= 0) {
    header("Location: user.php?error=event_not_found");
    exit();
}

/* ==========================================================
   GET EVENT DETAILS
========================================================== */
$query = mysqli_prepare(
    $conn,
    "SELECT
        e.event_id,
        e.event_name,
        e.description,
        e.event_date,
        e.end_date,
        e.location,
        e.capacity,
        e.category,
        e.status,
        e.approval_status,
        e.organizer_id,
        COALESCE(
            NULLIF(TRIM(viewer.fullname), ''),
            NULLIF(TRIM(viewer.firstname), ''),
            'User'
        ) AS viewer_name,
        viewer.profile_pic AS viewer_profile_pic,
        u.firstname,
        u.lastname,
        u.fullname AS organizer_name,
        u.email AS organizer_email,
        u.organization_name,
        (
            SELECT COUNT(*)
            FROM event_participants ep
            WHERE ep.event_id = e.event_id
        ) AS participant_count,
        CASE
            WHEN EXISTS (
                SELECT 1
                FROM event_participants ep2
                WHERE ep2.event_id = e.event_id
                AND ep2.user_id = ?
            )
            THEN 1
            ELSE 0
        END AS is_joined
     FROM events e
     LEFT JOIN users u
        ON e.organizer_id = u.user_id
     LEFT JOIN users viewer
        ON viewer.user_id = ?
     WHERE e.event_id = ?
     LIMIT 1"
);

if (!$query) {
    header("Location: user.php?error=database");
    exit();
}

mysqli_stmt_bind_param($query, "iii", $user_id, $user_id, $event_id);

if (!mysqli_stmt_execute($query)) {
    mysqli_stmt_close($query);
    header("Location: user.php?error=database");
    exit();
}

$result = mysqli_stmt_get_result($query);
if (!$result || mysqli_num_rows($result) === 0) {
    mysqli_stmt_close($query);
    header("Location: user.php?error=event_not_found");
    exit();
}

$event = mysqli_fetch_assoc($result);
mysqli_stmt_close($query);
if ($_SESSION['role'] === 'Audience' && $event['approval_status'] !== 'approved') {
    header("Location: user.php?error=event_not_found");
    exit();
}

/* ==========================================================
   USER DATA — para sa sidebar
========================================================== */
$isOrganizer = ($_SESSION['role'] === 'Organizer');
if ($isOrganizer) {
    $sidebarName = $event['firstname'] ?? 'Organizer';
} else {
    $sidebarName = $event['viewer_name'] ?? 'User';
}

/* ==========================================================
   EVENT VALUES
========================================================== */
$eventName = $event['event_name'] ?? 'Untitled Event';
$eventDescription = $event['description'] ?? '';
$eventDate = $event['event_date'] ?? '';
$eventLocation = $event['location'] ?? 'Not specified';
$eventCategory = $event['category'] ?? 'Other';
$eventStatus = event_lifecycle_status($event['event_date'] ?? '', $event['end_date'] ?? null);
$organizerName = $event['organizer_name'] ?? 'Unknown Organizer';
$organizerEmail = $event['organizer_email'] ?? 'Not available';
$organizationName = $event['organization_name'] ?? 'Not available';
$userName = trim((string)($event['viewer_name'] ?? 'User'));
$profilePic = trim((string)($event['viewer_profile_pic'] ?? ''));

$profileImage = 'https://ui-avatars.com/api/?name=' . urlencode($userName) . '&background=2c5fb3&color=fff&bold=true';
if ($profilePic !== '') {
    if (filter_var($profilePic, FILTER_VALIDATE_URL) || str_starts_with($profilePic, 'data:')) {
        $profileImage = $profilePic;
    } else {
        $profilePic = str_replace('\\', '/', $profilePic);
        $profileImage = str_starts_with($profilePic, 'uploads/')
            ? '../' . $profilePic
            : '../uploads/profiles/' . basename($profilePic);
    }
}

/* ==========================================================
   PARTICIPANTS
========================================================== */
$participantCount = (int)($event['participant_count'] ?? 0);
$capacity = (int)($event['capacity'] ?? 0);
$isJoined = (int)($event['is_joined'] ?? 0) === 1;
$isFull = $capacity > 0 && $participantCount >= $capacity;

/* ==========================================================
   DATE / TIME
========================================================== */
$formattedDate = 'Not available';
$formattedTime = 'Not available';
if (!empty($eventDate)) {
    $timestamp = strtotime($eventDate);
    if ($timestamp !== false) {
        $formattedDate = date("F j, Y", $timestamp);
        $formattedTime = date("g:i A", $timestamp);
    }
}

/* ==========================================================
   STATUS & CATEGORY CLASS
========================================================== */
$statusClass = match($eventStatus) {
    'Upcoming' => 'status-upcoming',
    'Ongoing'  => 'status-ongoing',
    default    => 'status-ended'
};
$categoryClass = 'category';

function e($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Event - <?= e($eventName); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<link rel="stylesheet" href="../assets/css/users/view_event.css">

</head>
<body class="<?= $isOrganizer ? 'organizer-view' : 'audience-view'; ?>">

<?php if ($isOrganizer): ?>
<!-- ORGANIZER SIDEBAR — pareho sa all_events.php -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="org_dash.php" class="brand">
            <img src="../assets/images/organizer-sidebar-logo.png" alt="Event MS">
        </a>
        <button type="button" class="close-sidebar" onclick="closeSidebar()">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <div class="sidebar-profile">
        <div class="sidebar-avatar">
            <?php if (!empty($profilePic)): ?>
                <img src="<?= e($profileImage); ?>" alt="Profile">
            <?php else: ?>
                <i class="bi bi-person-fill"></i>
            <?php endif; ?>
        </div>
        <div>
            <div class="sidebar-profile-name"><?= e($sidebarName); ?></div>
            <div class="sidebar-profile-role">Organizer</div>
        </div>
    </div>
    <div class="sidebar-menu">
        <div class="sidebar-label">Main Menu</div>
        <a href="../organizer/org_dash.php"><i class="bi bi-house-door"></i> Home</a>
        <a href="all_events.php" class="active"><i class="bi bi-calendar3"></i> All Events</a>
        <a href="event_reviews.php"><i class="bi bi-star"></i> Event Reviews</a>
        <div class="sidebar-label">Account</div>
        <a href="myprofile.php"><i class="bi bi-person"></i> My Profile</a>
        <a href="settingss.php"><i class="bi bi-gear"></i> Settings</a>
        <a href="help.php"><i class="bi bi-question-circle"></i> Help & Support</a>
    </div>
    <div class="sidebar-footer">
        <a href="../auth/logout_user.php" onclick="return confirm('Logout?');">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</aside>
<div class="overlay" id="overlay" onclick="closeSidebar()"></div>
<nav class="top-navbar">
    <button type="button" class="menu-btn" onclick="toggleSidebar()">
        <i class="bi bi-list"></i>
    </button>
    <span class="nav-title">Event Details</span>
    <div class="topbar-spacer"></div>
    <?php require __DIR__ . '/../organizer/notification_menu.php'; ?>
</nav>
<?php else: ?>
<!-- AUDIENCE SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="audience-sidebar-header">
        <a href="user.php" class="audience-sidebar-brand">
            <img src="../assets/images/organizer-sidebar-logo.png" alt="Event MS">
        </a>
        <button type="button" class="close-sidebar" onclick="document.getElementById('sidebarToggle').click()" aria-label="Close menu">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <div class="sidebar-profile audience-sidebar-profile">
        <div class="audience-sidebar-avatar">
            <img src="<?= e($profileImage); ?>" alt="Profile">
        </div>
        <div class="audience-sidebar-info">
            <div class="sidebar-user-name audience-sidebar-name"><?= e($userName); ?></div>
            <div class="sidebar-role audience-sidebar-role">Audience</div>
        </div>
    </div>
    <nav class="sidebar-menu" aria-label="Audience navigation">
        <div class="sidebar-label">Main</div>
        <a href="user.php"><i class="bi bi-house-door"></i><span>Home</span></a>
        <a href="user.php?status=Joined"><i class="bi bi-bookmark-check"></i><span>My Registrations</span></a>
        <a href="user.php?status=Ended&amp;category=All"><i class="bi bi-calendar-x"></i><span>My Reviews</span></a>
        <div class="sidebar-label">Account</div>
        <a href="user_profile.php"><i class="bi bi-person-circle"></i><span>My Profile</span></a>
        <a href="user_settings.php"><i class="bi bi-gear"></i><span>Settings</span></a>
        <a href="help_user.php"><i class="bi bi-question-circle"></i><span>Help &amp; Support</span></a>
        <a href="../auth/logout_user.php" class="logout-link"
           onclick="return confirm('Are you sure you want to logout?');">
            <i class="bi bi-box-arrow-right"></i><span>Logout</span>
        </a>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-footer-line"></div>
        <p>Better Events.<br>Stronger Communities.</p>
    </div>
</aside>
<div id="sidebarOverlay" class="sidebar-overlay"></div>
<nav class="top-navbar">
    <button type="button" class="menu-btn" id="sidebarToggle">
        <i class="bi bi-list"></i>
    </button>
    <div class="top-navbar-title">
        <i class="bi bi-calendar-event"></i>
        <span>Event Details</span>
    </div>
    <div class="topbar-spacer"></div>
    <?php require __DIR__ . '/../organizer/notification_menu.php'; ?>
    <div class="topbar-user">
        <div class="topbar-avatar"><?= e(strtoupper(substr($userName, 0, 1))); ?></div>
        <span><?= e($userName); ?></span>
        <i class="bi bi-chevron-down"></i>
    </div>
</nav>
<?php endif; ?>

<main class="main-content">
    <div class="main-container">
        <div class="details-card">
            <div class="event-title"><?= e($eventName); ?></div>
            <div class="mb-3">
                <span class="category"><?= e($eventCategory); ?></span>
                <span class="badge <?= $statusClass; ?>"><?= e($eventStatus); ?></span>
            </div>

            <div class="info-grid">
                <div class="info-box">
                    <div class="info-label"><i class="bi bi-calendar"></i> Date</div>
                    <div class="info-value"><?= e($formattedDate); ?></div>
                </div>
                <div class="info-box">
                    <div class="info-label"><i class="bi bi-clock"></i> Time</div>
                    <div class="info-value"><?= e($formattedTime); ?></div>
                </div>
                <div class="info-box">
                    <div class="info-label"><i class="bi bi-geo-alt"></i> Location</div>
                    <div class="info-value"><?= e($eventLocation); ?></div>
                </div>
                <div class="info-box">
                    <div class="info-label"><i class="bi bi-people"></i> Participants</div>
                    <div class="info-value">
                        <?= $participantCount; ?>
                        <?php if ($capacity > 0): ?>/ <?= $capacity; ?><?php else: ?> participants<?php endif; ?>
                    </div>
                </div>
                <div class="info-box">
                    <div class="info-label"><i class="bi bi-person"></i> Organizer</div>
                    <div class="info-value"><?= e($organizerName); ?></div>
                </div>
                <div class="info-box">
                    <div class="info-label"><i class="bi bi-building"></i> Organization</div>
                    <div class="info-value"><?= e($organizationName); ?></div>
                </div>
            </div>

            <div class="description-section">
                <h5><i class="bi bi-card-text"></i> Description</h5>
                <div class="description">
                    <?php if (trim($eventDescription) !== ''): ?>
                        <?= nl2br(e($eventDescription)); ?>
                    <?php else: ?>
                        <span class="text-muted">No description available.</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="organizer-section">
                <h5><i class="bi bi-person-badge"></i> Organizer Information</h5>
                <div class="organizer-card">
                    <h5><?= e($organizerName); ?></h5>
                    <p class="mb-0">
                        <i class="bi bi-envelope"></i> <?= e($organizerEmail); ?>
                    </p>
                </div>
            </div>

            <div class="actions">
                <?php if ($isOrganizer): ?>
                    <a href="../organizer/all_events.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to All Events
                    </a>
                <?php else: ?>
                    <a href="../user.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back
                    </a>
                <?php endif; ?>

                <?php if ($eventStatus === 'Upcoming' && !$isOrganizer): ?>
                    <?php if ($isJoined): ?>
                        <form method="POST" action="../actions/event_action.php" onsubmit="return confirm('Are you sure you want to leave this event?');">
                            <input type="hidden" name="event_id" value="<?= $event_id; ?>">
                            <button type="submit" name="leave" value="1" class="btn btn-outline-danger">
                                <i class="bi bi-box-arrow-left"></i> Leave Event
                            </button>
                        </form>
                    <?php elseif ($isFull): ?>
                        <button type="button" class="btn btn-secondary" disabled>
                            <i class="bi bi-people"></i> Event Full
                        </button>
                    <?php else: ?>
                        <form method="POST" action="../actions/event_action.php" onsubmit="return confirm('Do you want to join this event?');">
                            <input type="hidden" name="event_id" value="<?= $event_id; ?>">
                            <button type="submit" name="join" value="1" class="btn btn-success">
                                <i class="bi bi-person-plus"></i> Join Event
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($eventStatus === 'Ongoing' && !$isOrganizer): ?>
                    <span class="ongoing-notice">
                        <i class="bi bi-broadcast"></i> Happening Now
                    </span>
                    <?php if ($isJoined): ?>
                        <span class="joined-notice">
                            <i class="bi bi-check-circle"></i> You joined this event
                        </span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($eventStatus === 'Ended' && $isJoined && !$isOrganizer): ?>
                    <a href="../actions/review_event.php?event_id=<?= $event_id; ?>" class="btn btn-warning">
                        <i class="bi bi-star"></i> Leave Review
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
<?php if ($isOrganizer): ?>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('active');
    document.getElementById('overlay').classList.toggle('show');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('active');
    document.getElementById('overlay').classList.remove('show');
}
<?php else: ?>
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');
sidebarToggle.addEventListener('click', () => {
    sidebar.classList.toggle('show');
    sidebarOverlay.classList.toggle('show');
});
sidebarOverlay.addEventListener('click', () => {
    sidebar.classList.remove('show');
    sidebarOverlay.classList.remove('show');
});
<?php endif; ?>
</script>
<script src="../assets/js/organizer/notifications.js"></script>

</body>
</html>