<?php
session_start();
require_once '../config/db.php';
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

$user_id = (int)$_SESSION['user_id'];

if (empty($_SESSION['delete_event_csrf_token'])) {
    $_SESSION['delete_event_csrf_token'] = bin2hex(random_bytes(32));
}

$userData = null;
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
if ($stmtUser) {
    mysqli_stmt_bind_param($stmtUser, "i", $user_id);
    mysqli_stmt_execute($stmtUser);
    $resultUser = mysqli_stmt_get_result($stmtUser);
    $userData = mysqli_fetch_assoc($resultUser);
    mysqli_stmt_close($stmtUser);
}
if (!$userData) {
    session_destroy();
    header("Location: ../auth/login_user.php");
    exit();
}

$organizerName = $userData['firstname'] ?? 'Organizer';
$profileImage = organizer_profile_image_url($userData['profile_pic'] ?? '');
require_once __DIR__ . '/notification_center.php';
$notificationCenter = organizer_notification_center($conn, $user_id);

function e($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function get_event_image_src($row) {
    if (!empty($row['event_image'])) {
        $path = $row['event_image'];
        if (strpos($path, 'http') !== 0 && strpos($path, '/') !== 0) {
            return '../' . $path;
        }
        return $path;
    }
    $category_map = [
        'Wedding'          => '../assets/images/categories/Wedding.jpg',
        'Party'            => '../assets/images/categories/Party.jpg',
        'Meeting'          => '../assets/images/categories/Meeting.jpg',
        'Seminar'          => '../assets/images/categories/Seminar.jpg',
        'Workshop'         => '../assets/images/categories/Workshop.jpg',
        'Community Event'  => '../assets/images/categories/Community Event.jpg',
    ];
    $cat = $row['category'] ?? '';
    return $category_map[$cat] ?? '../assets/images/categories/default.jpg';
}

$allEvents = mysqli_query(
    $conn,
    "SELECT
        events.*,
        users.firstname AS organizer_name
     FROM events
     LEFT JOIN users
        ON events.organizer_id = users.user_id
     ORDER BY events.event_date ASC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Events - Event System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    
    
    <link rel="stylesheet" href="../assets/css/organizer/all_events.css">
</head>
<body class="organizer-layout">
<?php require __DIR__ . '/sidebar.php'; ?>

<nav class="top-navbar">
    <button type="button" class="menu-btn" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
    <span class="nav-title">Event System</span>
    <?php require __DIR__ . '/notification_menu.php'; ?>
</nav>

<main class="main-content">
    <div class="main-container">
        <div class="page-header">
            <div>
                <h2><i class="bi bi-calendar3"></i> All Events</h2>
                <p>View all events created by organizers</p>
            </div>
            <a href="add.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Create Event</a>
        </div>

        <?php if (!empty($_SESSION['event_action_message'])): ?>
            <div class="alert alert-<?= e($_SESSION['event_action_message']['type']); ?>" role="alert">
                <?= e($_SESSION['event_action_message']['text']); ?>
            </div>
            <?php unset($_SESSION['event_action_message']); ?>
        <?php endif; ?>

        <?php if (!$allEvents || mysqli_num_rows($allEvents) === 0): ?>
            <div class="empty-state">
                <i class="bi bi-calendar-x fs-1"></i>
                <h4 class="mt-3">No Events Yet</h4>
                <p>Be the first to create an event!</p>
                <a href="add.php" class="btn btn-primary mt-2">Create Event</a>
            </div>
        <?php else: ?>
            <div class="events-grid">
            <?php while ($row = mysqli_fetch_assoc($allEvents)): ?>
                <?php
                $status = event_lifecycle_status($row['event_date'] ?? '', $row['end_date'] ?? null);
                $badgeClass = match($status) {
                    'Upcoming' => 'badge-upcoming',
                    'Ongoing' => 'badge-ongoing',
                    'Ended' => 'badge-ended',
                    default => 'badge-ended'
                };
                $eventTimestamp = !empty($row['event_date']) ? strtotime($row['event_date']) : false;
                $isOwner = (int)($row['organizer_id'] ?? 0) === $user_id;
                $img_src = get_event_image_src($row);
                $event_id = (int)$row['event_id'];
                
                // ✅ TAMA NA: event_id ang ginagamit, hindi id
                $viewLink = '../users/view_event.php?event_id=' . $event_id;
                ?>
                <div class="event-card">
                    <img src="<?= e($img_src); ?>" alt="<?= e($row['event_name']); ?>" class="event-img" loading="lazy" onerror="this.src='../assets/images/categories/default.jpg'">
                    <div class="event-body">
                        <div class="event-top-row">
                            <h5 class="event-name"><?= e($row['event_name']); ?></h5>
                            <span class="badge-sm <?= $badgeClass; ?>"><?= e($status); ?></span>
                        </div>
                        <div class="event-meta-top">
                            <span class="cat-tag"><?= e($row['category'] ?: 'General'); ?></span>
                            <?php if ($isOwner): ?><span class="badge-sm badge-you">You</span><?php endif; ?>
                        </div>
                        <?php if ($isOwner): ?>
                            <div class="my-2"><?= event_approval_badge($row['approval_status'] ?? 'pending'); ?></div>
                            <?php if (($row['approval_status'] ?? '') === 'rejected' && !empty($row['admin_remarks'])): ?>
                                <div class="alert alert-danger py-2 px-3 small"><?= e($row['admin_remarks']); ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <div class="event-details">
                            <span><i class="bi bi-person"></i> <?= e($row['organizer_name'] ?? 'Unknown Organizer'); ?></span>
                            <span><i class="bi bi-calendar-event"></i> <?= $eventTimestamp ? date('F j, Y', $eventTimestamp) : 'No date'; ?></span>
                            <span><i class="bi bi-clock"></i> <?= $eventTimestamp ? date('g:i A', $eventTimestamp) : 'No time'; ?></span>
                            <span><i class="bi bi-geo-alt"></i> <?= e($row['location'] ?: 'No location'); ?></span>
                            <?php if (!empty($row['capacity'])): ?><span><i class="bi bi-people"></i> Capacity: <?= e($row['capacity']); ?></span><?php endif; ?>
                        </div>
                        <div class="event-actions">
                            <?php if ($isOwner): ?>
                                <?php if ($status === 'Upcoming' || $status === 'Ongoing'): ?>
                                    <a href="edit.php?id=<?= $event_id; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                <?php endif; ?>
                                <?php if ($status === 'Upcoming'): ?>
                                    <form method="POST" action="delete.php" onsubmit="return confirm('Delete this event?');">
                                        <input type="hidden" name="event_id" value="<?= $event_id; ?>">
                                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['delete_event_csrf_token']); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($status === 'Ended'): ?>
                                    <a href="archive.php?id=<?= $event_id; ?>" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Archive this event?');">
                                        <i class="bi bi-archive"></i> Archive
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                            <a href="<?= e($viewLink); ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> View Details
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
</main>
<script>
function toggleSidebar() { document.getElementById('sidebar').classList.toggle('active'); document.getElementById('overlay').classList.toggle('show'); }
function closeSidebar() { document.getElementById('sidebar').classList.remove('active'); document.getElementById('overlay').classList.remove('show'); }
</script>
<script src="../assets/js/organizer/all_events.js"></script>
<script src="../assets/js/organizer/notifications.js"></script>
</body>
</html>