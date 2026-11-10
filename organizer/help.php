<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/profile_helpers.php';

if (
    !isset($_SESSION['user_id']) ||
    empty($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    strcasecmp(trim($_SESSION['role']), 'Organizer') !== 0
) {
    header("Location: ../auth/login_user.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
require_once __DIR__ . '/notification_center.php';
$notificationCenter = organizer_notification_center($conn, $user_id);

$firstname = 'Organizer';
$profile_pic = '';
$stmt = $conn->prepare("
    SELECT firstname, profile_pic
    FROM users
    WHERE user_id = ?
    LIMIT 1
");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($db_firstname, $db_profile_pic);
    if ($stmt->fetch()) {
        if (!empty($db_firstname)) $firstname = $db_firstname;
        if (!empty($db_profile_pic)) $profile_pic = $db_profile_pic;
    }
    $stmt->close();
}

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$avatar = organizer_profile_image_url($profile_pic);
if (empty($avatar)) {
    $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($firstname) . '&background=2563eb&color=fff&size=128';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help & Support | Event System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/organizer/help_page.css">
    
</head>
<body class="organizer-layout">



<?php require __DIR__ . '/sidebar.php'; ?>

<nav class="top-navbar">
    <button type="button" class="menu-btn" onclick="toggleSidebar(true)" aria-label="Open menu">
        <i class="bi bi-list"></i>
    </button>
    <h1 class="page-title">
        <i class="bi bi-question-circle"></i>
        Help & Support
    </h1>
    <div class="topbar-spacer"></div>
    <?php require __DIR__ . '/notification_menu.php'; ?>
    <div class="topbar-user">
        <div class="topbar-avatar"><?= e(strtoupper(substr($firstname, 0, 1))); ?></div>
        <span><?= e($firstname) ?></span>
        <i class="bi bi-chevron-down"></i>
    </div>
</nav>

<main class="main-content">
    <div class="main-container">
        <!-- ✅ HERO SECTION — Inayos na ang layout ng text at larawan -->
        <div class="help-hero">
            <div class="hero-text">
                <h1>
                    <i class="bi bi-question-circle"></i>
                    How can we help?
                </h1>
                <p>
                    Welcome to the Event System Help & Support page.
                    Here you can find information about using the system,
                    managing events, and your organizer account.
                </p>
            </div>
            <div class="hero-image-wrapper">
                <img 
        src="../assets/images/helpchar.png" 
        alt="Support Illustration" 
        class="hero-support-icon"
        style="width: 100%; height: auto;"
    >
            </div>
        </div>

        <!-- Getting Started -->
        <div class="help-card">
            <h4><i class="bi bi-play-circle"></i> Getting Started</h4>
            <div class="step">
                <div class="step-number">1</div>
                <div class="step-content">
                    <h6>Open Your Dashboard</h6>
                    <p>Use the Organizer Dashboard to view your events and event information.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-content">
                    <h6>Create an Event</h6>
                    <p>Use the event management section to create and publish your event.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <h6>Manage Your Events</h6>
                    <p>You can view and manage events according to their current status.</p>
                </div>
            </div>
        </div>

        <!-- FAQ -->
        <div class="help-card">
            <h4><i class="bi bi-patch-question"></i> Frequently Asked Questions</h4>
            <div class="accordion" id="faqAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faqOne">
                            Why can't I access the Help page when logged out?
                        </button>
                    </h2>
                    <div id="faqOne" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            The Help page is protected by the system's authentication rule. You must be logged in as an organizer before accessing the page.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqTwo">
                            Why am I redirected to the login page?
                        </button>
                    </h2>
                    <div id="faqTwo" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            If your login session has expired or you are not currently logged in as an organizer, the system will redirect you to the login page.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqThree">
                            What happens when I logout?
                        </button>
                    </h2>
                    <div id="faqThree" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Your current session is ended. Protected organizer pages will require you to log in again.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqFour">
                            How do I return to my dashboard?
                        </button>
                    </h2>
                    <div id="faqFour" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Click the <strong>Home</strong> option in the sidebar to go back to your dashboard.
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqFive">
                            How can I logout safely?
                        </button>
                    </h2>
                    <div id="faqFive" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Click the <strong>Logout</strong> button in the sidebar and confirm the action to securely end your session.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Account Help -->
        <div class="help-card">
            <h4><i class="bi bi-person-gear"></i> Account Help</h4>
            <p class="text-muted mb-4">Keep your organizer account information updated and secure.</p>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="contact-box">
                        <div class="contact-icon"><i class="bi bi-person"></i></div>
                        <div>
                            <strong>Profile</strong>
                            <span>Update your personal information from your organizer profile page.</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="contact-box">
                        <div class="contact-icon"><i class="bi bi-shield-lock"></i></div>
                        <div>
                            <strong>Account Security</strong>
                            <span>Keep your login credentials secure and never share your password.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Support -->
        <div class="help-card">
            <h4><i class="bi bi-headset"></i> Need More Help?</h4>
            <p class="text-muted mb-4">If you encounter an issue while using the IPT Event System, contact your system administrator.</p>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="contact-box">
                        <div class="contact-icon"><i class="bi bi-envelope"></i></div>
                        <div>
                            <strong>Email Support</strong>
                            <span>Contact your system administrator for account or system concerns.</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="contact-box">
                        <div class="contact-icon"><i class="bi bi-info-circle"></i></div>
                        <div>
                            <strong>System Support</strong>
                            <span>Report technical problems or unexpected system behavior.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSidebar(open) {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    if (open) {
        sidebar.classList.add('active');
        overlay.classList.add('show');
    } else {
        sidebar.classList.remove('active', 'open', 'show');
        overlay.classList.remove('show', 'active');
    }
}

function closeSidebar() {
    toggleSidebar(false);
}
</script>
<script src="../assets/js/organizer/help.js"></script>
<script src="../assets/js/organizer/notifications.js"></script>
</body>
</html>