<?php
session_start();
require_once '../config/db.php';
require_once __DIR__ . '/profile_helpers.php';

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

$profileStmt = mysqli_prepare(
    $conn,
    "SELECT firstname, fullname, profile_pic
     FROM users
     WHERE user_id = ?
     LIMIT 1"
);
if (!$profileStmt) {
    die("Failed to load organizer information.");
}
mysqli_stmt_bind_param($profileStmt, "i", $user_id);
mysqli_stmt_execute($profileStmt);
$profileResult = mysqli_stmt_get_result($profileStmt);
$profileData = mysqli_fetch_assoc($profileResult);
mysqli_stmt_close($profileStmt);

$organizerName = trim((string) ($profileData['fullname'] ?? ''));
if ($organizerName === '') {
    $organizerName = trim((string) ($profileData['firstname'] ?? 'Organizer'));
}
if ($organizerName === '') {
    $organizerName = 'Organizer';
}

$profileImage = organizer_profile_image_url($profileData['profile_pic'] ?? '');

$allowed_categories = ['Wedding', 'Party', 'Meeting', 'Seminar', 'Workshop', 'Community Event'];

$event_id = 0;
if (isset($_GET['id'])) {
    $event_id = intval($_GET['id']);
} elseif (isset($_POST['event_id'])) {
    $event_id = intval($_POST['event_id']);
}

if ($event_id == 0) {
    die("No Event ID provided.");
}

$sql = "SELECT * FROM events WHERE event_id = $event_id AND organizer_id = $user_id";
$result = mysqli_query($conn, $sql);
$event = mysqli_fetch_assoc($result);

if (!$event) {
    echo "Event not found or you do not have permission to edit it.";
    exit();
}

$category = $event['category'];
$event_name = $event['event_name'];
$description = $event['description'];
$event_date = $event['event_date'];
$end_date = $event['end_date'] ?? '';
$location = $event['location'];
$status = $event['status'];
$current_image = $event['event_image']; // Kasalukuyang larawan

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $event_name = mysqli_real_escape_string($conn, trim($_POST['event_name'] ?? ''));
    $description = mysqli_real_escape_string($conn, trim($_POST['description'] ?? ''));
    $event_date = trim($_POST['event_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $location = mysqli_real_escape_string($conn, trim($_POST['location'] ?? ''));
    $status = trim($_POST['status'] ?? '');
    $safe_category = mysqli_real_escape_string($conn, $category);

    // === HANDLE IMAGE UPLOAD ===
    $event_image_path = $current_image; // Panatilihin ang kasalukuyang larawan kung walang bagong in-upload

    if (!empty($_FILES['event_image']['name'])) {
        $upload_dir = __DIR__ . '/../uploads/events/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_ext = strtolower(pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($file_ext, $allowed_exts)) {
            $error = "Tanggap lang ang JPG, PNG, at WebP na larawan.";
        } else {
            $filename = 'event_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
            $target_file = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['event_image']['tmp_name'], $target_file)) {
                $event_image_path = 'uploads/events/' . $filename;

                // Opsyonal: Burahin ang lumang larawan kung mayroon
                if (!empty($current_image) && file_exists(__DIR__ . '/../' . $current_image)) {
                    @unlink(__DIR__ . '/../' . $current_image);
                }
            } else {
                $error = "Hindi ma-upload ang larawan.";
            }
        }
    }

    if (!$error && (
        $event_date === '' ||
        $end_date === '' ||
        strtotime($end_date) <= strtotime($event_date)
    )) {
        $error = 'The event end date and time must be after the start date and time.';
    }

    if (!$error) {
        $update = mysqli_prepare(
            $conn,
            "UPDATE events SET
                event_name = ?, description = ?, category = ?, event_date = ?,
                end_date = ?, location = ?, status = ?, event_image = ?,
                approval_status = 'pending', admin_remarks = NULL
             WHERE event_id = ? AND organizer_id = ?"
        );

        if ($update) {
            mysqli_stmt_bind_param(
                $update,
                'ssssssssii',
                $event_name,
                $description,
                $category,
                $event_date,
                $end_date,
                $location,
                $status,
                $event_image_path,
                $event_id,
                $user_id
            );
        }

        if ($update && mysqli_stmt_execute($update)) {
            mysqli_stmt_close($update);
            header("Location: category_events.php?category=" . urlencode($category));
            exit();
        } else {
            if ($update) {
                mysqli_stmt_close($update);
            }
            $error = "Unable to update the event. Please check the details and try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Event - Event System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    
    
    <link rel="stylesheet" href="../assets/css/organizer/edit.css">
    
</head>
<body class="organizer-layout">
    <?php require __DIR__ . '/sidebar.php'; ?>
    
    <nav class="top-navbar">
        <button type="button" class="menu-btn" onclick="toggleSidebar()" aria-label="Open menu">
            <i class="bi bi-list"></i>
        </button>
        <a href="all_events.php" class="btn-back">
            <i class="bi bi-arrow-left me-1"></i>Back to List
        </a>
        <span class="edit-topbar-title">Edit Event</span>
        <div class="topbar-spacer"></div>
        <?php require __DIR__ . '/notification_menu.php'; ?>
        <div class="topbar-user">
            <div class="topbar-avatar"><?= htmlspecialchars(strtoupper(substr($organizerName, 0, 1)), ENT_QUOTES, 'UTF-8'); ?></div>
            <span><?= htmlspecialchars($organizerName, ENT_QUOTES, 'UTF-8'); ?></span>
            <i class="bi bi-chevron-down"></i>
        </div>
    </nav>

    <main class="main-content edit-page-content">
        <div class="edit-hero">
            <div>
                <h1><i class="bi bi-pencil-square me-2"></i>Edit Event Details</h1>
                <p>Update your event information — leave image blank to keep the current photo.</p>
            </div>
        </div>

        <div class="edit-form-wrap">
            <div class="form-card">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" 
                      action="edit.php?id=<?= $event_id; ?>" 
                      autocomplete="off" 
                      enctype="multipart/form-data">
                    
                    <input type="hidden" name="event_id" value="<?= $event_id; ?>">

                    <div class="form-group">
                        <label for="event_name">Event Name:</label>
                        <input type="text" id="event_name" name="event_name" 
                               value="<?= htmlspecialchars($event_name); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description:</label>
                        <textarea id="description" name="description" rows="4" required><?= htmlspecialchars($description); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="event_date">Date & Time:</label>
                        <input type="datetime-local" id="event_date" name="event_date" 
                               value="<?= date('Y-m-d\TH:i', strtotime($event_date)); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="end_date">End Date & Time:</label>
                        <input type="datetime-local" id="end_date" name="end_date"
                               value="<?= $end_date !== '' ? date('Y-m-d\TH:i', strtotime($end_date)) : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="location">Location:</label>
                        <input type="text" id="location" name="location" 
                               value="<?= htmlspecialchars($location); ?>" autocomplete="nope" required>
                        <div class="suggestions" id="suggestions"></div>
                    </div>

                    <div class="form-group">
                        <label>Category:</label>
                        <div class="category-display">
                            <i class="fa-solid fa-tag me-2"></i>
                            <?= htmlspecialchars($category); ?>
                            <small class="d-block text-muted mt-1">Category cannot be changed</small>
                        </div>
                    </div>

                    <!-- IMAGE UPLOAD SECTION -->
                    <div class="form-group">
                        <label>Event Cover Image:</label>
                        
                        <?php if (!empty($current_image)): ?>
                            <div class="mb-2">
                                <small class="text-muted d-block mb-1">Current Image:</small>
                                <img src="<?= htmlspecialchars($current_image); ?>" 
                                     alt="Current event image" 
                                     class="current-image-preview">
                            </div>
                        <?php endif; ?>
                        
                        <label class="image-upload-box d-block">
                            <input type="file" 
                                   id="event_image" 
                                   name="event_image" 
                                   accept="image/jpeg,image/png,image/webp" 
                                   class="d-none">
                            <i class="fa-solid fa-cloud-arrow-up fa-2x text-muted mb-2"></i>
                            <p class="mb-1">
                                <?php if (!empty($current_image)): ?>
                                    Click to upload <strong>new image</strong> to replace current
                                <?php else: ?>
                                    Click to upload event cover image
                                <?php endif; ?>
                            </p>
                            <small class="text-muted">JPG, PNG, WebP • Leave blank to keep current</small>
                            <img id="new_preview" class="new-image-preview" alt="New preview">
                        </label>
                    </div>

                    <div class="form-group">
                        <label for="status">Status:</label>
                        <select id="status" name="status" required>
                            <option value="Upcoming" <?= $status === 'Upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                            <option value="Ongoing" <?= $status === 'Ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                            <option value="Ended" <?= $status === 'Ended' ? 'selected' : ''; ?>>Ended</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-submit w-100">
                        <i class="fa-solid fa-check me-2"></i>
                        Save Changes
                    </button>
                </form>
            </div>
        </div>
    </main>

<script>
// Preview for new image selection
const fileInput = document.getElementById('event_image');
const newPreview = document.getElementById('new_preview');

fileInput.addEventListener('change', function () {
    if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            newPreview.src = e.target.result;
            newPreview.style.display = 'block';
        };
        reader.readAsDataURL(this.files[0]);
    } else {
        newPreview.style.display = 'none';
    }
});

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
</script>
<script src="../assets/js/organizer/edit.js"></script>
<script src="../assets/js/organizer/notifications.js"></script>
</body>
</html>