<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Organizer') {
    header("Location: login_user.php");
    exit();
}

$user_id = $_SESSION['user_id'];

require_once __DIR__ . '/notification_center.php';
$notificationCenter = organizer_notification_center($conn, (int)$user_id);

$allowed_categories = [
    'Wedding',
    'Party',
    'Meeting',
    'Seminar',
    'Workshop',
    'Community Event'
];

$category = $_GET['category'] ?? '';
if (!in_array($category, $allowed_categories, true)) {
    header("Location: dashboard/org_dash.php");
    exit();
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST['notification_action'])) {
    $event_name  = trim($_POST['event_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $event_date  = trim($_POST['event_date'] ?? '');
    $end_date    = trim($_POST['end_date'] ?? '');
    $location    = trim($_POST['location'] ?? '');
    $status      = trim($_POST['status'] ?? 'Upcoming');

    if (
        $event_date === '' ||
        $end_date === '' ||
        strtotime($end_date) <= strtotime($event_date)
    ) {
        $error = 'The event end date and time must be after the start date and time.';
    }

    // === HANDLE IMAGE UPLOAD ===
    $event_image_path = null;

    if (!empty($_FILES['event_image']['name'])) {
        // Tiyakin na umiiral ang upload folder
        $upload_dir = __DIR__ . '/../uploads/events/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Kunin ang uri ng file
        $file_ext = strtolower(pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($file_ext, $allowed_exts)) {
            $error = "Tanggap lang ang JPG, PNG, at WebP na larawan.";
        } else {
            // Gumawa ng natatanging pangalan para hindi magpatungan
            $filename = 'event_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
            
            // I-save ang file sa folder
            if (move_uploaded_file($_FILES['event_image']['tmp_name'], $upload_dir . $filename)) {
                // ✅ Ito ang ilalagay sa database — RELATIVE PATH mula sa root
                $event_image_path = 'uploads/events/' . $filename;
            } else {
                $error = "Hindi ma-upload ang larawan. Suriin ang pahintulot sa folder.";
            }
        }
    }

    // Kung walang pagkakamali, isalaysay sa database
    if (!$error) {
        $sql = "INSERT INTO events
                (event_name, description, category, event_date, end_date, location,
                 status, organizer_id, event_image, approval_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt,
                'sssssssis',
                $event_name,
                $description,
                $category,
                $event_date,
                $end_date,
                $location,
                $status,
                $user_id,
                $event_image_path
            );
        }

        if ($stmt && mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            header("Location: category_events.php?category=" . urlencode($category));
            exit();
        } else {
            if ($stmt) {
                mysqli_stmt_close($stmt);
            }
            $error = "Unable to submit event. Please check the event details and try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create <?= htmlspecialchars($category); ?> Event - Event System</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<link rel="stylesheet" href="../assets/css/organizer/add.css">

</head>
<body>
<div class="navbar">
    <a class="navbar-brand" href="dashboard/org_dash.php">
        <i class="fa-solid fa-calendar-days me-2"></i>
        Event System
    </a>
    <a href="category_events.php?category=<?= urlencode($category); ?>" class="btn-back">
        <i class="fa-solid fa-arrow-left me-1"></i>
        Back to <?= htmlspecialchars($category); ?>
    </a>
    <?php require __DIR__ . '/notification_menu.php'; ?>
</div>

<div class="hero">
    <h1>Create <?= htmlspecialchars($category); ?> Event</h1>
    <p>You are creating an event under the <strong><?= htmlspecialchars($category); ?></strong> category.</p>
</div>

<div class="container">
    <div class="form-card">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" 
              action="add.php?category=<?= urlencode($category); ?>" 
              autocomplete="off" 
              enctype="multipart/form-data">
            
            <div class="form-group">
                <label for="event_name">Event Name:</label>
                <input type="text" id="event_name" name="event_name" placeholder="Enter event name" required>
            </div>

            <div class="form-group">
                <label for="description">Description:</label>
                <textarea id="description" name="description" placeholder="Enter event description..." rows="4" required></textarea>
            </div>

            <div class="form-group">
                <label for="event_date">Date & Time:</label>
                <input type="datetime-local" id="event_date" name="event_date" required>
            </div>

            <div class="form-group">
                <label for="end_date">End Date & Time:</label>
                <input type="datetime-local" id="end_date" name="end_date" required>
            </div>

            <div class="form-group">
                <label for="location">Location:</label>
                <input type="text" id="location" name="location" placeholder="Search location..." autocomplete="off" required>
                <div id="suggestions"></div>
            </div>

            <div class="form-group">
                <label>Category:</label>
                <div class="category-display">
                    <i class="fa-solid fa-tag me-2"></i>
                    <?= htmlspecialchars($category); ?>
                </div>
            </div>

            <!-- IMAGE UPLOAD -->
            <div class="form-group">
                <label for="event_image">
                    Event Cover Image 
                    <small class="text-muted">(Optional — default will be used if left blank)</small>:
                </label>
                <label class="image-upload-box d-block">
                    <input type="file" 
                           id="event_image" 
                           name="event_image" 
                           accept="image/jpeg,image/png,image/webp" 
                           class="d-none">
                    <i class="fa-solid fa-cloud-arrow-up fa-2x text-muted mb-2"></i>
                    <p class="mb-0">Click to upload or drag image here</p>
                    <small class="text-muted">Galing sa gallery ng device mo — ligtas itong i-upload</small>
                    <img id="preview_img" class="image-preview" alt="Preview">
                </label>
            </div>

            <div class="form-group">
                <label for="status">Status:</label>
                <select id="status" name="status" required>
                    <option value="Upcoming">Upcoming</option>
                    <option value="Ongoing">Ongoing</option>
                    <option value="Ended">Ended</option>
                </select>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fa-solid fa-plus me-2"></i>
                Create <?= htmlspecialchars($category); ?> Event
            </button>
        </form>
    </div>
</div>

<script>
// Image preview
const fileInput = document.getElementById('event_image');
const previewImg = document.getElementById('preview_img');

fileInput.addEventListener('change', function () {
    if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            previewImg.src = e.target.result;
            previewImg.style.display = 'block';
        };
        reader.readAsDataURL(this.files[0]);
    } else {
        previewImg.style.display = 'none';
    }
});
</script>
<script src="../assets/js/organizer/add.js"></script>
<script src="../assets/js/organizer/notifications.js"></script>
</body>
</html>