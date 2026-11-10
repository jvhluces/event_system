
<?php
session_start();

require_once __DIR__ . '/../config/db.php';

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

/* =========================
   AUTHENTICATION
========================= */
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Audience'
) {
    header('Location: ../auth/login_user.php');
    exit();
}

$userId = (int)$_SESSION['user_id'];
require_once __DIR__ . '/../organizer/notification_center.php';
$notificationCenter = organizer_notification_center($conn, $userId);

/* =========================
   FETCH USER
========================= */
$stmt = mysqli_prepare(
    $conn,
    "SELECT
        firstname,
        middlename,
        lastname,
        fullname,
        email,
        contact,
        address,
        organization_name,
        profile_pic,
        account_status
     FROM users
     WHERE user_id = ?
     LIMIT 1"
);

if (!$stmt) {
    http_response_code(500);
    exit('Unable to load your profile. Please check the database configuration.');
}

mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$user = $result ? mysqli_fetch_assoc($result) : null;

mysqli_stmt_close($stmt);

if (!$user) {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
    header('Location: ../auth/login_user.php?error=account_not_found');
    exit();
}

/* =========================
   USER INFORMATION
========================= */
$fullname = trim((string)($user['fullname'] ?? ''));

if ($fullname === '') {
    $fullname = trim(implode(' ', array_filter([
        $user['firstname'] ?? '',
        $user['middlename'] ?? '',
        $user['lastname'] ?? '',
    ], static fn($part) => trim((string)$part) !== '')));
}

if ($fullname === '') {
    $fullname = 'Audience Member';
}

$email = trim((string)($user['email'] ?? ''));
$contact = trim((string)($user['contact'] ?? ''));
$address = trim((string)($user['address'] ?? ''));
$organization = trim((string)($user['organization_name'] ?? ''));
$profilePic = trim((string)($user['profile_pic'] ?? ''));

/* =========================
   PROFILE IMAGE
========================= */
function getProfileImage(string $profilePic, string $fullname): string
{
    $defaultAvatar = 'https://ui-avatars.com/api/?name='
        . rawurlencode($fullname ?: 'Audience Member')
        . '&background=1d4ed8&color=ffffff&size=240';

    if ($profilePic === '') {
        return $defaultAvatar;
    }

    if (filter_var($profilePic, FILTER_VALIDATE_URL)) {
        return $profilePic;
    }

    $cleanPic = str_replace('\\', '/', $profilePic);
    $cleanPic = preg_replace('/[?#].*$/', '', $cleanPic);
    $filename = basename(ltrim($cleanPic, '/'));

    if ($filename === '' || $filename === '.' || $filename === '..') {
        return $defaultAvatar;
    }

    $physicalPath = __DIR__ . '/../uploads/profiles/' . $filename;

    if (is_file($physicalPath)) {
        return '../uploads/profiles/'
            . rawurlencode($filename)
            . '?v=' . filemtime($physicalPath);
    }

    return $defaultAvatar;
}

$avatar = getProfileImage($profilePic, $fullname);
$initial = function_exists('mb_substr')
    ? mb_strtoupper(mb_substr($fullname, 0, 1))
    : strtoupper(substr($fullname, 0, 1));

/* =========================
   PAGE MODE & MESSAGES
========================= */
$editMode = isset($_GET['edit']) && $_GET['edit'] === '1';

$successMessage = '';
$errorMessage = '';

$errors = [
    'firstname_required' => 'Full name is required.',
    'email_required' => 'Email address is required.',
    'invalid_email' => 'Please enter a valid email address.',
    'email_exists' => 'That email address is already being used.',
    'upload_folder' => 'The upload folder could not be created.',
    'upload_failed' => 'The profile picture could not be uploaded.',
    'file_too_large' => 'The selected image is larger than 5MB.',
    'invalid_image' => 'Please upload a valid image file.',
    'invalid_image_type' => 'Only JPG, PNG, GIF, and WEBP images are allowed.',
    'database' => 'A database error occurred.',
    'update_failed' => 'The profile could not be updated.',
];

if (($_GET['success'] ?? '') === 'updated') {
    $successMessage = 'Your profile information has been updated successfully.';
}

$errorCode = (string)($_GET['error'] ?? '');

if ($errorCode !== '') {
    $errorMessage = $errors[$errorCode] ?? (
        $errorCode === 'account_not_found'
            ? 'Your account could not be found. Please sign in again.'
            : 'Something went wrong. Please try again.'
    );
}

$accountStatus = trim((string)($user['account_status'] ?? 'Active'));
if ($accountStatus === '') {
    $accountStatus = 'Active';
}

/* =========================
   PAGE
========================= */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Profile | Event MS</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
          rel="stylesheet">

    
    
    <link rel="stylesheet" href="../assets/css/users/user_profile.css">
</head>

<body class="audience-profile-page">

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="audience-sidebar-header">
        <a href="user.php" class="audience-sidebar-brand">
            <img src="../assets/images/organizer-sidebar-logo.png" alt="Event MS">
        </a>

        <button type="button" class="profile-mobile-close" id="sidebarClose"
                aria-label="Close navigation">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <div class="sidebar-profile audience-sidebar-profile">
        <div class="audience-sidebar-avatar">
            <img src="<?= e($avatar); ?>" alt="Profile picture"
                 onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=User&background=1d4ed8&color=ffffff&size=240';">
        </div>

        <div class="audience-sidebar-info">
            <div class="sidebar-profile-name audience-sidebar-name">
                <?= e($fullname); ?>
            </div>
            <div class="sidebar-profile-role audience-sidebar-role">Audience</div>
        </div>
    </div>

    <nav class="sidebar-menu" aria-label="Audience navigation">
        <div class="sidebar-label">Main</div>
        <a href="user.php?status=All&amp;category=All">
            <i class="bi bi-house-door"></i><span>Home</span>
        </a>
       
        <a href="user.php?status=Joined">
            <i class="bi bi-bookmark-check"></i><span>My Registrations</span>
        </a>
        <a href="user.php?status=Ended&amp;category=All">
            <i class="bi bi-calendar-x"></i><span>My Reviews</span>
        </a>

        <div class="sidebar-label">Account</div>
        <a href="user_profile.php" class="active" aria-current="page">
            <i class="bi bi-person-circle"></i><span>My Profile</span>
        </a>
        <a href="user_settings.php">
            <i class="bi bi-gear"></i><span>Settings</span>
        </a>
        <a href="help_user.php">
            <i class="bi bi-question-circle"></i><span>Help &amp; Support</span>
        </a>
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

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- TOP NAVIGATION -->
<nav class="profile-topbar">
    <div class="topbar-left">
        <button type="button" class="profile-menu-toggle" id="sidebarToggle"
                aria-label="Open navigation">
            <i class="bi bi-list"></i>
        </button>

        <div>
            <div class="topbar-title">Account Center</div>
            <div class="topbar-subtitle">Manage your personal account</div>
        </div>
    </div>

    <?php require __DIR__ . '/../organizer/notification_menu.php'; ?>
    <div class="topbar-account">
        <img src="<?= e($avatar); ?>" alt=""
             onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=User&background=1d4ed8&color=ffffff&size=240';">
        <div class="topbar-account-copy">
            <strong><?= e($fullname); ?></strong>
            <span>Audience</span>
        </div>
    </div>
</nav>

<!-- MAIN CONTENT -->
<main class="profile-main-content">
    <div class="profile-content-inner">

        <header class="profile-page-heading">
            <div>
                <div class="profile-eyebrow">
                    <i class="bi bi-person-vcard"></i> ACCOUNT CENTER
                </div>
                <h1><?= $editMode ? 'Edit Your Profile' : 'My Profile'; ?></h1>
                <p>
                    <?= $editMode
                        ? 'Update your personal information and profile photo.'
                        : 'View and manage your personal information in Event MS.'; ?>
                </p>
            </div>

            <?php if (!$editMode): ?>
                <a href="user_profile.php?edit=1" class="profile-primary-button">
                    <i class="bi bi-pencil-square"></i> Edit Profile
                </a>
            <?php else: ?>
                <a href="user_profile.php" class="profile-secondary-button">
                    <i class="bi bi-arrow-left"></i> Back to Profile
                </a>
            <?php endif; ?>
        </header>

        <?php if ($successMessage !== ''): ?>
            <div class="profile-alert profile-alert-success" role="status">
                <i class="bi bi-check-circle-fill"></i>
                <span><?= e($successMessage); ?></span>
                <button type="button" class="alert-dismiss" aria-label="Dismiss">&times;</button>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="profile-alert profile-alert-error" role="alert">
                <i class="bi bi-exclamation-circle-fill"></i>
                <span><?= e($errorMessage); ?></span>
                <button type="button" class="alert-dismiss" aria-label="Dismiss">&times;</button>
            </div>
        <?php endif; ?>

        <!-- PROFILE OVERVIEW -->
        <section class="profile-overview-card">
            <div class="profile-overview-topline"></div>

            <div class="profile-overview-inner">
                <div class="profile-avatar-wrap">
                    <img src="<?= e($avatar); ?>" id="profileOverviewImage"
                         alt="Profile picture"
                         onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=User&background=1d4ed8&color=ffffff&size=240';">
                    <span class="profile-avatar-indicator"
                          title="<?= e($accountStatus); ?>"></span>
                </div>

                <div class="profile-overview-details">
                    <span class="profile-account-badge">
                        <i class="bi bi-person-check-fill"></i> Audience Account
                    </span>
                    <h2><?= e($fullname); ?></h2>
                    <p class="profile-overview-email">
                        <i class="bi bi-envelope"></i>
                        <?= e($email ?: 'No email address added'); ?>
                    </p>
                </div>

                <div class="profile-status-pill">
                    <span></span><?= e($accountStatus); ?>
                </div>
            </div>
        </section>

        <?php if (!$editMode): ?>

            <!-- PERSONAL INFORMATION -->
            <section class="profile-panel">
                <div class="profile-panel-heading">
                    <div class="profile-panel-icon">
                        <i class="bi bi-person-lines-fill"></i>
                    </div>
                    <div>
                        <h2>Personal Information</h2>
                        <p>Your personal details saved in your account.</p>
                    </div>
                </div>

                <div class="profile-information-grid">
                    <div class="profile-information-item">
                        <span class="profile-information-icon"><i class="bi bi-person"></i></span>
                        <div>
                            <span class="profile-information-label">Full Name</span>
                            <strong><?= e($fullname ?: 'Not provided'); ?></strong>
                        </div>
                    </div>

                    <div class="profile-information-item">
                        <span class="profile-information-icon"><i class="bi bi-envelope"></i></span>
                        <div>
                            <span class="profile-information-label">Email Address</span>
                            <strong><?= e($email ?: 'Not provided'); ?></strong>
                        </div>
                    </div>

                    <div class="profile-information-item">
                        <span class="profile-information-icon"><i class="bi bi-telephone"></i></span>
                        <div>
                            <span class="profile-information-label">Contact Number</span>
                            <strong><?= e($contact ?: 'Not provided'); ?></strong>
                        </div>
                    </div>

                    <div class="profile-information-item">
                        <span class="profile-information-icon"><i class="bi bi-geo-alt"></i></span>
                        <div>
                            <span class="profile-information-label">Address</span>
                            <strong><?= e($address ?: 'Not provided'); ?></strong>
                        </div>
                    </div>

                    <?php if ($organization !== ''): ?>
                        <div class="profile-information-item profile-information-full">
                            <span class="profile-information-icon"><i class="bi bi-building"></i></span>
                            <div>
                                <span class="profile-information-label">Organization</span>
                                <strong><?= e($organization); ?></strong>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="profile-panel-footer">
                    <p><i class="bi bi-info-circle"></i> Keep your contact information up to date.</p>
                    <a href="user_profile.php?edit=1" class="profile-primary-button">
                        <i class="bi bi-pencil-square"></i> Edit Information
                    </a>
                </div>
            </section>

            <!-- ACCOUNT MANAGEMENT -->
            <section class="profile-panel profile-account-panel">
                <div class="profile-panel-heading">
                    <div class="profile-panel-icon profile-security-icon">
                        <i class="bi bi-shield-lock"></i>
                    </div>
                    <div>
                        <h2>Account Management</h2>
                        <p>Manage your account preferences and security.</p>
                    </div>
                </div>

                <a href="user_settings.php" class="profile-management-link">
                    <span class="management-link-icon"><i class="bi bi-gear"></i></span>
                    <span class="management-link-copy">
                        <strong>Account Settings</strong>
                        <small>Change your password and review account preferences.</small>
                    </span>
                    <i class="bi bi-chevron-right management-link-arrow"></i>
                </a>

                <a href="help_user.php" class="profile-management-link">
                    <span class="management-link-icon help-link-icon"><i class="bi bi-headset"></i></span>
                    <span class="management-link-copy">
                        <strong>Help &amp; Support</strong>
                        <small>Get help with your account or event registrations.</small>
                    </span>
                    <i class="bi bi-chevron-right management-link-arrow"></i>
                </a>
            </section>

        <?php else: ?>

            <!-- EDIT PROFILE -->
            <section class="profile-panel profile-edit-panel">
                <div class="profile-panel-heading">
                    <div class="profile-panel-icon">
                        <i class="bi bi-person-gear"></i>
                    </div>
                    <div>
                        <h2>Edit Profile Information</h2>
                        <p>Make changes below, then save to update your account.</p>
                    </div>
                </div>

                <form action="save_user_profile.php" method="POST"
                      enctype="multipart/form-data" id="editProfileForm">

                    <div class="profile-edit-photo-section">
                        <img src="<?= e($avatar); ?>" id="profilePreview"
                             alt="Profile picture preview">

                        <div class="profile-edit-photo-copy">
                            <strong>Profile Photo</strong>
                            <p>Choose a clear photo to help identify your account.</p>
                            <label for="profilePicInput" class="profile-upload-button">
                                <i class="bi bi-upload"></i> Choose Photo
                            </label>
                            <input type="file" name="profile_pic" id="profilePicInput"
                                   accept="image/jpeg,image/png,image/gif,image/webp"
                                   class="visually-hidden">
                            <small>JPG, PNG, GIF or WEBP · Maximum 5MB</small>
                            <div id="selectedPhotoName" class="selected-photo-name"></div>
                        </div>
                    </div>

                    <div class="profile-edit-divider"></div>

                    <div class="profile-edit-fields">
                        <div class="profile-edit-field">
                            <label for="fullname">Full Name <span>*</span></label>
                            <div class="profile-edit-input-wrap">
                                <i class="bi bi-person"></i>
                                <input type="text" id="fullname" name="fullname"
                                       value="<?= e($fullname); ?>" maxlength="100"
                                       placeholder="Enter your full name" required>
                            </div>
                        </div>

                        <div class="profile-edit-field">
                            <label for="email">Email Address <span>*</span></label>
                            <div class="profile-edit-input-wrap">
                                <i class="bi bi-envelope"></i>
                                <input type="email" id="email" name="email"
                                       value="<?= e($email); ?>" maxlength="150"
                                       placeholder="you@example.com" required>
                            </div>
                        </div>

                        <div class="profile-edit-field">
                            <label for="contact">Contact Number</label>
                            <div class="profile-edit-input-wrap">
                                <i class="bi bi-telephone"></i>
                                <input type="text" id="contact" name="contact"
                                       value="<?= e($contact); ?>" maxlength="30"
                                       placeholder="Enter your contact number">
                            </div>
                        </div>

                        <div class="profile-edit-field">
                            <label for="address">Address</label>
                            <div class="profile-edit-input-wrap profile-textarea-wrap">
                                <i class="bi bi-geo-alt"></i>
                                <textarea id="address" name="address" rows="3"
                                          maxlength="255"
                                          placeholder="Enter your address"><?= e($address); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="profile-edit-note">
                        <i class="bi bi-info-circle"></i>
                        <span>Your email address must be valid and not already used by another account.</span>
                    </div>

                    <div class="profile-edit-actions">
                        <a href="user_profile.php" class="profile-secondary-button">
                            Cancel
                        </a>
                        <button type="submit" class="profile-primary-button">
                            <i class="bi bi-check2-circle"></i> Save Changes
                        </button>
                    </div>
                </form>
            </section>

        <?php endif; ?>

        <footer class="profile-page-footer">
            <span><strong>Event MS</strong><span class="footer-dot">•</span> Audience Portal</span>
            <span>Better Events. Stronger Communities.</span>
        </footer>

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/organizer/notifications.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggle = document.getElementById('sidebarToggle');
    const close = document.getElementById('sidebarClose');

    function openSidebar() {
        sidebar?.classList.add('sidebar-open');
        overlay?.classList.add('show');
        document.body.classList.add('sidebar-is-open');
    }

    function closeSidebar() {
        sidebar?.classList.remove('sidebar-open');
        overlay?.classList.remove('show');
        document.body.classList.remove('sidebar-is-open');
    }

    toggle?.addEventListener('click', openSidebar);
    close?.addEventListener('click', closeSidebar);
    overlay?.addEventListener('click', closeSidebar);

    document.querySelectorAll('.alert-dismiss').forEach(function (button) {
        button.addEventListener('click', function () {
            this.closest('.profile-alert')?.remove();
        });
    });

    const fileInput = document.getElementById('profilePicInput');
    const preview = document.getElementById('profilePreview');
    const overviewImage = document.getElementById('profileOverviewImage');
    const selectedName = document.getElementById('selectedPhotoName');

    fileInput?.addEventListener('change', function () {
        const file = this.files?.[0];

        if (!file) return;

        const allowedTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp'
        ];

        if (!allowedTypes.includes(file.type)) {
            alert('Please select a JPG, PNG, GIF, or WEBP image.');
            this.value = '';
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            alert('The selected image must not exceed 5MB.');
            this.value = '';
            return;
        }

        const imageUrl = URL.createObjectURL(file);

        if (preview) preview.src = imageUrl;
        if (overviewImage) overviewImage.src = imageUrl;
        if (selectedName) selectedName.textContent = file.name;
    });
});
</script>

</body>
</html>
