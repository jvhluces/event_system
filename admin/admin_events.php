<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/event_helpers.php';
require_once __DIR__ . '/../organizer/notification_center.php';

if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header('Location: ../auth/login.php');
    exit();
}

if (empty($_SESSION['event_review_csrf'])) {
    $_SESSION['event_review_csrf'] = bin2hex(random_bytes(32));
}
$notificationCenter = organizer_notification_center($conn, (int) $_SESSION['user_id']);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['notification_action'])) {
    $token = (string) ($_POST['csrf_token'] ?? '');
    $eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
    $action = (string) ($_POST['review_action'] ?? '');
    $remarks = trim((string) ($_POST['admin_remarks'] ?? ''));

    if (!hash_equals($_SESSION['event_review_csrf'], $token)) {
        $error = 'Request verification failed. Refresh the page and try again.';
    } elseif (!$eventId || $eventId < 1 || !in_array($action, ['approve', 'reject'], true)) {
        $error = 'Invalid event review request.';
    } elseif ($action === 'reject' && ($remarks === '' || strlen($remarks) > 255)) {
        $error = 'A rejection reason of 1–255 characters is required.';
    } else {
        $newStatus = $action === 'approve' ? 'approved' : 'rejected';
        $conn->begin_transaction();
        try {
            $select = $conn->prepare(
                'SELECT event_name, organizer_id, approval_status
                 FROM events WHERE event_id = ? FOR UPDATE'
            );
            if (!$select) {
                throw new RuntimeException('Unable to prepare event lookup.');
            }
            $select->bind_param('i', $eventId);
            $select->execute();
            $event = $select->get_result()->fetch_assoc();
            $select->close();

            if (!$event || $event['approval_status'] !== 'pending') {
                throw new RuntimeException('This event is no longer pending review.');
            }

            $update = $conn->prepare(
                'UPDATE events SET approval_status = ?, admin_remarks = ?
                 WHERE event_id = ? AND approval_status = "pending"'
            );
            if (!$update) {
                throw new RuntimeException('Unable to prepare event update.');
            }
            $savedRemarks = $action === 'reject' ? $remarks : null;
            $update->bind_param('ssi', $newStatus, $savedRemarks, $eventId);
            $update->execute();
            if ($update->affected_rows !== 1) {
                $update->close();
                throw new RuntimeException('The event review could not be saved.');
            }
            $update->close();

            $notice = $action === 'approve'
                ? 'Your event "' . $event['event_name'] . '" has been approved.'
                : 'Your event "' . $event['event_name'] . '" was rejected. Reason: ' . $remarks;
            $noticeLink = '../organizer/org_dash.php';
            $insert = $conn->prepare(
                'INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)'
            );
            if (!$insert) {
                throw new RuntimeException('Unable to prepare organizer notification.');
            }
            $organizerId = (int) $event['organizer_id'];
            $insert->bind_param('iss', $organizerId, $notice, $noticeLink);
            $insert->execute();
            $insert->close();

            $conn->commit();
            $success = $action === 'approve'
                ? 'Event approved and organizer notified.'
                : 'Event rejected and organizer notified.';
        } catch (Throwable $exception) {
            $conn->rollback();
            $error = $exception->getMessage();
        }
    }
}

$events = [];
$query = $conn->prepare(
    'SELECT e.event_id, e.event_name, e.category, e.event_date, e.end_date,
            e.location, e.approval_status, e.admin_remarks, u.fullname AS organizer_name
     FROM events e
     LEFT JOIN users u ON u.user_id = e.organizer_id
     ORDER BY FIELD(e.approval_status, "pending", "rejected", "approved"),
              e.event_date DESC, e.event_id DESC'
);
if (!$query || !$query->execute()) {
    die('Unable to load events for review.');
}
$result = $query->get_result();
while ($row = $result->fetch_assoc()) {
    $events[] = $row;
}
$query->close();

function h($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Event Approval | Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/admin/admin.css">
    <style>
        .review-main{margin-left:262px;padding:32px;min-height:100vh;background:#f5f7fb}
        .review-topbar{display:flex;justify-content:flex-end;align-items:center;margin:-32px -32px 26px;padding:12px 32px;background:#fff;border-bottom:1px solid #e6ecf3}
        .review-heading{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}
        .review-card{border:1px solid #e6ecf3;border-radius:14px;background:#fff;padding:22px;margin-bottom:16px}
        .review-meta{display:flex;flex-wrap:wrap;gap:12px;color:#718096;font-size:13px;margin:12px 0}
        .review-meta span{display:inline-flex;gap:5px;align-items:center}
        .review-form{display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap}
        .review-form textarea{flex:1 1 260px;min-height:40px}
        .review-remarks{border-left:3px solid #dc3545;background:#fff6f6;padding:10px 12px;color:#7a3038}
        @media(max-width:991.98px){.review-main{margin-left:0;padding:22px}}
    </style>
</head>
<body>
<?php require __DIR__ . '/sidebar.php'; ?>
<main class="review-main">
    <div class="review-topbar">
        <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Open navigation" aria-controls="adminSidebar" aria-expanded="false">
            <i class="bi bi-list"></i>
        </button>
    </div>
    <div class="review-heading">
        <div>
            <h1 class="h3 mb-1">Event approval</h1>
            <p class="text-muted mb-0">Review organizer submissions and their current status.</p>
        </div>
        <a class="btn btn-outline-primary" href="event.php"><i class="bi bi-calendar-event me-1"></i>All event management</a>
    </div>
    <?php if ($success !== ''): ?><div class="alert alert-success"><?= h($success); ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= h($error); ?></div><?php endif; ?>
    <?php if ($events === []): ?>
        <div class="review-card text-center text-muted">No events have been submitted.</div>
    <?php endif; ?>
    <?php foreach ($events as $event): ?>
        <article class="review-card">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                    <h2 class="h5 mb-1"><?= h($event['event_name']); ?></h2>
                    <div class="review-meta">
                        <span><i class="bi bi-person"></i><?= h($event['organizer_name'] ?: 'Organizer'); ?></span>
                        <span><i class="bi bi-tag"></i><?= h($event['category']); ?></span>
                        <span><i class="bi bi-geo-alt"></i><?= h($event['location']); ?></span>
                        <span><i class="bi bi-calendar-event"></i><?= h($event['event_date']); ?></span>
                        <?= event_lifecycle_badge($event['event_date'], $event['end_date']); ?>
                    </div>
                    <?= event_approval_badge($event['approval_status']); ?>
                </div>
            </div>
            <?php if ($event['approval_status'] === 'rejected' && $event['admin_remarks']): ?>
                <p class="review-remarks mt-3 mb-0"><?= h($event['admin_remarks']); ?></p>
            <?php endif; ?>
            <?php if ($event['approval_status'] === 'pending'): ?>
                <form method="post" class="review-form mt-3">
                    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['event_review_csrf']); ?>">
                    <input type="hidden" name="event_id" value="<?= (int) $event['event_id']; ?>">
                    <button class="btn btn-success" name="review_action" value="approve" type="submit"
                            onclick="this.form.admin_remarks.required=false; return confirm('Approve this event?');">
                        <i class="bi bi-check-lg me-1"></i>Approve
                    </button>
                    <textarea class="form-control" name="admin_remarks" maxlength="255"
                              placeholder="Required rejection reason (up to 255 characters)" required></textarea>
                    <button class="btn btn-danger" name="review_action" value="reject" type="submit"
                            onclick="this.form.admin_remarks.required=true; return confirm('Reject this event?');">
                        <i class="bi bi-x-lg me-1"></i>Reject
                    </button>
                </form>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</main>
</body>
</html>
