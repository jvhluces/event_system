<?php
error_reporting(E_ERROR | E_PARSE);
session_start();
$_SESSION['user_id'] = 1;
$_SERVER['REQUEST_METHOD'] = 'GET';
$page = $argv[1] ?? '';
$role = $argv[2] ?? 'Admin';
$allowedPages = [
    'Admin' => ['admin_events.php', 'event.php', 'reports.php', 'venues.php', 'settings.php', 'admin.php', 'event_audience.php'],
    'Audience' => ['user.php', 'view_event.php', 'user_profile.php', 'user_settings.php', 'help_user.php'],
    'Organizer' => ['org_dash.php', 'all_events.php', 'archived_events.php', 'event_reviews.php', 'category_events.php'],
];
$_SESSION['role'] = $role;
if (!isset($allowedPages[$role])) {
    exit(2);
}
$allowed = $allowedPages[$role];
chdir(dirname(__DIR__) . DIRECTORY_SEPARATOR . (
    $role === 'Audience' ? 'users' : strtolower($role)
));
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'db.php';
$roleStatement = $conn->prepare(
    "SELECT user_id FROM users WHERE role = ? AND account_status = 'Active' ORDER BY user_id LIMIT 1"
);
$roleStatement->bind_param('s', $role);
$roleStatement->execute();
$roleRow = $roleStatement->get_result()->fetch_assoc();
$roleStatement->close();
if ($roleRow) {
    $_SESSION['user_id'] = (int) $roleRow['user_id'];
}
if (!in_array($page, $allowed, true)) {
    exit(2);
}
$_SERVER['PHP_SELF'] = $page;
$_SERVER['REQUEST_URI'] = '/ipt/' . strtolower($role) . '/' . $page;
ob_start();
if ($role === 'Admin') {
    require __DIR__ . '/../admin/' . $page;
} elseif ($role === 'Audience') {
    require __DIR__ . '/../users/' . $page;
} else {
    $_GET['category'] = 'Wedding';
    require __DIR__ . '/../organizer/' . $page;
}
$html = ob_get_clean();
echo $page . ': rendered ' . strlen($html) . " bytes\n";
