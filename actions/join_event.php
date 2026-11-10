<?php
session_start();
include 'db.php';
require_once __DIR__ . '/../includes/event_helpers.php';


if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Audience') {
    header("Location: login_user.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);

if (!isset($_GET['event_id']) || !is_numeric($_GET['event_id'])) {
    header("Location: dashboard/user.php");
    exit();
}

$event_id = intval($_GET['event_id']);

$stmt = mysqli_prepare($conn, "
    SELECT event_id, event_name, capacity, status, approval_status, event_date, end_date
    FROM events
    WHERE event_id = ?
    LIMIT 1
");

mysqli_stmt_bind_param($stmt, "i", $event_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$event = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$event) {
    header("Location: dashboard/user.php?error=event_not_found");
    exit();
}

if ($event['approval_status'] !== 'approved') {
    header("Location: ../users/user.php?error=event_not_found");
    exit();
}

if (event_lifecycle_status($event['event_date'], $event['end_date']) !== 'Upcoming') {
    header("Location: dashboard/user.php?error=not_joinable");
    exit();
}


$stmt = mysqli_prepare($conn, "
    SELECT participation_id
    FROM event_participants
    WHERE event_id = ? AND user_id = ?
    LIMIT 1
");

mysqli_stmt_bind_param($stmt, "ii", $event_id, $user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$alreadyJoined = mysqli_num_rows($result) > 0;

mysqli_stmt_close($stmt);

if ($alreadyJoined) {
    header("Location: dashboard/user.php?error=already_joined");
    exit();
}


$stmt = mysqli_prepare($conn, "
    SELECT COUNT(*) AS total_participants
    FROM event_participants
    WHERE event_id = ?
");

mysqli_stmt_bind_param($stmt, "i", $event_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$countData = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

$totalParticipants = intval($countData['total_participants']);
$capacity = intval($event['capacity']);

if ($capacity > 0 && $totalParticipants >= $capacity) {
    header("Location: dashboard/user.php?error=event_full");
    exit();
}


$stmt = mysqli_prepare($conn, "
    INSERT INTO event_participants (event_id, user_id)
    VALUES (?, ?)
");

mysqli_stmt_bind_param($stmt, "ii", $event_id, $user_id);

if (mysqli_stmt_execute($stmt)) {

    mysqli_stmt_close($stmt);

    header("Location: dashboard/user.php?joined=1");
    exit();

} else {

    mysqli_stmt_close($stmt);

    header("Location: dashboard/user.php?error=join_failed");
    exit();
}
?>