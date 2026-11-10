
<?php
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/profile_helpers.php';

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

/* =========================================================
   AUTHENTICATION
========================================================= */

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Organizer'
) {
    header("Location: ../auth/login_user.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

/* =========================================================
   FETCH ORGANIZER
========================================================= */

$stmt = mysqli_prepare(
    $conn,
    "SELECT * FROM users WHERE user_id = ? LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$userData = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$userData) {
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

    header("Location: ../auth/login_user.php");
    exit();
}

/* =========================================================
   ACCOUNT STATUS
========================================================= */

$accountStatus = $userData['account_status'] ?? 'Active';

if (strcasecmp((string)$accountStatus, 'Active') !== 0) {
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

    header("Location: ../auth/login_user.php?account=inactive");
    exit();
}

/* =========================================================
   ORGANIZER INFORMATION
========================================================= */

// Prefer the complete name when available.
$organizerName = trim(
    (string)($userData['fullname'] ?? '')
);

if ($organizerName === '') {
    $organizerName = trim(
        (string)($userData['firstname'] ?? '') . ' ' .
        (string)($userData['middlename'] ?? '') . ' ' .
        (string)($userData['lastname'] ?? '')
    );
}

if ($organizerName === '') {
    $organizerName = trim(
        (string)($userData['firstname'] ?? 'Organizer')
    );
}

if ($organizerName === '') {
    $organizerName = 'Organizer';
}

$organizerUsername = trim(
    (string)($userData['username'] ?? '')
);

$organizerEmail = trim(
    (string)($userData['email'] ?? '')
);

$organizerContact = trim(
    (string)(
        $userData['contact'] ??
        $userData['contact_number'] ??
        ''
    )
);

$organizerAddress = trim(
    (string)($userData['address'] ?? '')
);

$organizationName = trim(
    (string)($userData['organization_name'] ?? '')
);

$profileImage = organizer_profile_image_url(
    $userData['profile_pic'] ?? ''
);

$initial = 'O';

if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
    $initial = mb_strtoupper(
        mb_substr($organizerName, 0, 1, 'UTF-8'),
        'UTF-8'
    );
} else {
    $initial = strtoupper(substr($organizerName, 0, 1));
}

/* =========================================================
   NOTIFICATIONS
========================================================= */

require_once __DIR__ . '/notification_center.php';

$notificationCenter = organizer_notification_center(
    $conn,
    $user_id
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>My Profile | Event MS</title>

    <!-- Bootstrap -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
    >

    <!-- Bootstrap Icons -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
    >

    <!-- Existing organizer styles -->
    

    

    <link rel="stylesheet" href="../assets/css/organizer/myprofile_page.css">

    <!-- Profile-specific design -->
    
</head>

<body class="profile-page organizer-layout">

<!-- =========================================================
     SIDEBAR
========================================================= -->

<?php require __DIR__ . '/sidebar.php'; ?>



<!-- =========================================================
     TOP NAVBAR
========================================================= -->

<nav class="top-navbar profile-navbar">

    <button
        type="button"
        class="menu-btn"
        onclick="toggleSidebar()"
        aria-label="Open menu"
    >
        <i class="bi bi-list"></i>
    </button>

    <div class="nav-heading">
        <span class="nav-title">My Profile</span>
        <span class="nav-subtitle">
            Manage your organizer account
        </span>
    </div>

    <?php require __DIR__ . '/notification_menu.php'; ?>

</nav>

<!-- =========================================================
     MAIN CONTENT
========================================================= -->

<main class="main-content profile-main">

    <!-- PAGE HEADING -->

    <div class="profile-page-heading">

        <div>
            <div class="profile-eyebrow">
                <i class="bi bi-person-vcard"></i>
                Organizer Account
            </div>

            <h1>My Profile</h1>

            <p>
                Manage your personal details and organization information.
            </p>
        </div>

        <a
            href="../actions/update_profile.php"
            class="profile-btn"
        >
            <i class="bi bi-pencil-square"></i>
            Edit Profile
        </a>

    </div>


    <!-- =====================================================
         PROFILE OVERVIEW
    ====================================================== -->

    <section class="profile-card profile-overview">

        <div class="profile-overview-accent"></div>

        <div class="profile-overview-content">

            <div class="profile-large-avatar">

                <?php if ($profileImage !== ''): ?>

                    <img
                        src="<?= e($profileImage); ?>"
                        alt="Profile photo"
                    >

                <?php else: ?>

                    <?= e($initial); ?>

                <?php endif; ?>

                <span class="profile-avatar-status"></span>

            </div>

            <div class="profile-overview-info">

                <span class="profile-role-badge">
                    <i class="bi bi-patch-check-fill"></i>
                    Verified account role
                </span>

                <h2><?= e($organizerName); ?></h2>

                <p class="profile-overview-email">
                    <i class="bi bi-envelope"></i>

                    <?= e($organizerEmail ?: 'No email provided'); ?>
                </p>

            </div>

            <div class="profile-status-badge">
                <span class="profile-status-dot"></span>
                Active Account
            </div>

        </div>

    </section>


    <!-- =====================================================
         PERSONAL INFORMATION
    ====================================================== -->

    <section class="profile-card">

        <div class="profile-card-body">

            <div class="profile-section-heading">

                <div class="profile-section-icon">
                    <i class="bi bi-person-lines-fill"></i>
                </div>

                <div>
                    <h2>Personal Information</h2>

                    <p>
                        Your account details saved in Event MS.
                    </p>
                </div>

            </div>


            <div class="profile-fields-grid">

                <!-- FULL NAME -->

                <div class="profile-field">

                    <div class="profile-field-icon">
                        <i class="bi bi-person"></i>
                    </div>

                    <div class="profile-field-copy">

                        <label>Full Name</label>

                        <div class="profile-field-value">
                            <?= e($organizerName ?: 'Not provided'); ?>
                        </div>

                    </div>

                </div>


                <!-- USERNAME -->

                <div class="profile-field">

                    <div class="profile-field-icon">
                        <i class="bi bi-at"></i>
                    </div>

                    <div class="profile-field-copy">

                        <label>Username</label>

                        <div class="profile-field-value <?= $organizerUsername === '' ? 'not-provided' : ''; ?>">
                            <?= e($organizerUsername ?: 'Not provided'); ?>
                        </div>

                    </div>

                </div>


                <!-- EMAIL -->

                <div class="profile-field">

                    <div class="profile-field-icon">
                        <i class="bi bi-envelope"></i>
                    </div>

                    <div class="profile-field-copy">

                        <label>Email Address</label>

                        <div class="profile-field-value <?= $organizerEmail === '' ? 'not-provided' : ''; ?>">
                            <?= e($organizerEmail ?: 'Not provided'); ?>
                        </div>

                    </div>

                </div>


                <!-- CONTACT -->

                <div class="profile-field">

                    <div class="profile-field-icon">
                        <i class="bi bi-telephone"></i>
                    </div>

                    <div class="profile-field-copy">

                        <label>Contact Number</label>

                        <div class="profile-field-value <?= $organizerContact === '' ? 'not-provided' : ''; ?>">
                            <?= e($organizerContact ?: 'Not provided'); ?>
                        </div>

                    </div>

                </div>


                <!-- ADDRESS -->

                <div class="profile-field">

                    <div class="profile-field-icon">
                        <i class="bi bi-geo-alt"></i>
                    </div>

                    <div class="profile-field-copy">

                        <label>Address</label>

                        <div class="profile-field-value <?= $organizerAddress === '' ? 'not-provided' : ''; ?>">
                            <?= e($organizerAddress ?: 'Not provided'); ?>
                        </div>

                    </div>

                </div>


                <!-- ORGANIZATION NAME -->

                <div class="profile-field">

                    <div class="profile-field-icon">
                        <i class="bi bi-building"></i>
                    </div>

                    <div class="profile-field-copy">

                        <label>Organization Name</label>

                        <div class="profile-field-value <?= $organizationName === '' ? 'not-provided' : ''; ?>">
                            <?= e($organizationName ?: 'Not provided'); ?>
                        </div>

                    </div>

                </div>

            </div>


            <div class="profile-card-actions">

                <div class="profile-action-note">
                    <i class="bi bi-info-circle"></i>

                    <span>
                        Keep your contact information updated so your
                        organizer account details remain accurate.
                    </span>
                </div>

                <a
                    href="../actions/update_profile.php"
                    class="profile-btn"
                >
                    <i class="bi bi-pencil-square"></i>
                    Edit Information
                    <i class="bi bi-arrow-right"></i>
                </a>

            </div>

        </div>

    </section>


    <!-- =====================================================
         ORGANIZATION INFORMATION
    ====================================================== -->

    <section class="profile-card">

        <div class="profile-card-body">

            <div class="profile-section-heading">

                <div class="profile-section-icon">
                    <i class="bi bi-buildings"></i>
                </div>

                <div>
                    <h2>Organization Information</h2>

                    <p>
                        Details about the organization you represent.
                    </p>
                </div>

            </div>


            <div class="organization-information">

                <div class="organization-information-icon">
                    <i class="bi bi-building-check"></i>
                </div>

                <div>

                    <h3>
                        <?= e($organizationName ?: 'No organization specified'); ?>
                    </h3>

                    <p>
                        <?php if ($organizationName !== ''): ?>

                            This organization is associated with your
                            organizer account.

                        <?php else: ?>

                            No organization name has been added to your
                            account yet. You can add one by editing your
                            profile if the field is available.

                        <?php endif; ?>
                    </p>

                </div>

            </div>


            <div class="profile-card-actions">

                <div class="profile-action-note">
                    <i class="bi bi-info-circle"></i>

                    <span>
                        Organization details help identify the group
                        responsible for managing your events.
                    </span>
                </div>

                <a
                    href="../actions/update_profile.php"
                    class="profile-text-link"
                >
                    Edit Organization Details
                    <i class="bi bi-arrow-right"></i>
                </a>

            </div>

        </div>

    </section>


    <!-- =====================================================
         ACCOUNT MANAGEMENT
    ====================================================== -->

    <section class="profile-card">

        <div class="profile-card-body">

            <div class="profile-section-heading">

                <div class="profile-section-icon">
                    <i class="bi bi-shield-check"></i>
                </div>

                <div>
                    <h2>Account Management</h2>

                    <p>
                        Manage your account security and preferences.
                    </p>
                </div>

            </div>


            <div class="profile-management-row">

                <div class="profile-field-icon">
                    <i class="bi bi-gear"></i>
                </div>

                <div class="profile-management-copy">

                    <h3>Account Settings</h3>

                    <p>
                        Visit Settings to change your password and
                        manage available account preferences.
                    </p>

                </div>

                <a
                    href="settingss.php"
                    class="profile-btn"
                >
                    Open Settings
                    <i class="bi bi-arrow-right"></i>
                </a>

            </div>

        </div>

    </section>


    <!-- =====================================================
         FOOTER
    ====================================================== -->

    <footer class="profile-footer">

        <span>
            <strong>Event MS</strong>
            &nbsp;·&nbsp;
            Organizer Portal
        </span>

        <span>
            Better Events. Stronger Communities.
        </span>

    </footer>

</main>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script src="../assets/js/organizer/org_dash.js"></script>
<script src="../assets/js/organizer/notifications.js"></script>

</body>
</html>
