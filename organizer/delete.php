<?php
session_start();

require_once __DIR__ . '/../config/db.php';

function redirectWithEventActionMessage(string $type, string $message): void
{
    $_SESSION['event_action_message'] = [
        'type' => $type,
        'text' => $message,
    ];
    header('Location: all_events.php');
    exit();
}

if (
    !isset($_SESSION['user_id'], $_SESSION['role']) ||
    $_SESSION['role'] !== 'Organizer'
) {
    header('Location: ../auth/login_user.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: all_events.php');
    exit();
}

$eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
$csrfToken = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['delete_event_csrf_token'] ?? '';

if (!$eventId || $eventId <= 0) {
    redirectWithEventActionMessage('danger', 'Invalid event selected.');
}

if (
    !is_string($csrfToken) ||
    !is_string($sessionToken) ||
    $sessionToken === '' ||
    !hash_equals($sessionToken, $csrfToken)
) {
    redirectWithEventActionMessage('danger', 'Your delete request could not be verified. Please try again.');
}

$userId = (int) $_SESSION['user_id'];
$stmt = mysqli_prepare(
    $conn,
    "DELETE FROM events
     WHERE event_id = ?
       AND organizer_id = ?
       AND event_date > NOW()
     LIMIT 1"
);

if (!$stmt) {
    redirectWithEventActionMessage('danger', 'Unable to prepare the delete request. Please try again.');
}

mysqli_stmt_bind_param($stmt, 'ii', $eventId, $userId);
$executed = mysqli_stmt_execute($stmt);
$deleted = $executed && mysqli_stmt_affected_rows($stmt) === 1;
mysqli_stmt_close($stmt);

if (!$deleted) {
    redirectWithEventActionMessage('danger', 'This event could not be deleted. It may no longer be available or belong to your account.');
}

redirectWithEventActionMessage('success', 'Event deleted successfully.');
