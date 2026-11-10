
<?php
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/event_helpers.php';

/* =========================
   AUTHENTICATION
========================= */
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Organizer'
) {
    header("Location: ../auth/login_user.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

/* =========================
   VALIDATE EVENT ID
========================= */
$event_id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$event_id || $event_id <= 0) {
    $_SESSION['delete_error'] = "Invalid event selected.";
    header("Location: org_dash.php");
    exit();
}

/* =========================
   CHECK EVENT OWNERSHIP
   AND STATUS
========================= */
$checkStmt = mysqli_prepare(
    $conn,
    "SELECT event_id, organizer_id, status, category, is_archived, event_date, end_date
     FROM events
     WHERE event_id = ?
     LIMIT 1"
);

if (!$checkStmt) {
    $_SESSION['delete_error'] =
        "Unable to check the selected event.";

    header("Location: org_dash.php");
    exit();
}

mysqli_stmt_bind_param($checkStmt, "i", $event_id);
mysqli_stmt_execute($checkStmt);

$result = mysqli_stmt_get_result($checkStmt);
$event = mysqli_fetch_assoc($result);

mysqli_stmt_close($checkStmt);

/* =========================
   VALIDATE EVENT
========================= */
if (!$event) {
    $_SESSION['delete_error'] =
        "The selected event does not exist.";

    header("Location: org_dash.php");
    exit();
}

$category = $event['category'];

/* Only the event owner can archive it */
if ((int) $event['organizer_id'] !== $user_id) {
    $_SESSION['delete_error'] =
        "You are not allowed to archive this event.";

    header("Location: org_dash.php");
    exit();
}

/* Only ended events can be archived */
if (event_lifecycle_status($event['event_date'], $event['end_date']) !== 'Ended') {
    $_SESSION['delete_error'] =
        "Only ended events can be archived.";

    header(
        "Location: category_events.php?category=" .
        urlencode($category)
    );
    exit();
}

/* Prevent duplicate archiving */
if ((int) $event['is_archived'] === 1) {
    $_SESSION['delete_success'] =
        "This event is already archived.";

    header(
        "Location: category_events.php?category=" .
        urlencode($category)
    );
    exit();
}

/* =========================
   ARCHIVE EVENT
========================= */
$archiveStmt = mysqli_prepare(
    $conn,
    "UPDATE events
     SET is_archived = 1
     WHERE event_id = ?
       AND organizer_id = ?
       AND end_date < NOW()
       AND is_archived = 0
     LIMIT 1"
);

if (!$archiveStmt) {
    $_SESSION['delete_error'] =
        "Unable to prepare the archive request.";

    header(
        "Location: category_events.php?category=" .
        urlencode($category)
    );
    exit();
}

mysqli_stmt_bind_param(
    $archiveStmt,
    "ii",
    $event_id,
    $user_id
);

if (mysqli_stmt_execute($archiveStmt)) {
    if (mysqli_stmt_affected_rows($archiveStmt) === 1) {
        $_SESSION['delete_success'] =
            "The event was successfully archived.";
    } else {
        $_SESSION['delete_error'] =
            "The event could not be archived. Please try again.";
    }
} else {
    $_SESSION['delete_error'] =
        "Something went wrong while archiving the event.";
}

mysqli_stmt_close($archiveStmt);

/* =========================
   RETURN TO CATEGORY PAGE
========================= */
header(
    "Location: category_events.php?category=" .
    urlencode($category)
);
exit();
?>