
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

/* =========================================
   AUTHENTICATION
========================================= */

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Organizer'
) {
    header('Location: ../auth/login_user.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];

/* =========================================
   FETCH ORGANIZER
========================================= */

$userStatement = mysqli_prepare(
    $conn,
    "SELECT *
     FROM users
     WHERE user_id = ?
     LIMIT 1"
);

if (!$userStatement) {
    http_response_code(500);
    exit('Unable to load your account. Please try again later.');
}

mysqli_stmt_bind_param($userStatement, 'i', $user_id);
mysqli_stmt_execute($userStatement);

$userResult = mysqli_stmt_get_result($userStatement);
$userData = $userResult ? mysqli_fetch_assoc($userResult) : null;

mysqli_stmt_close($userStatement);

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

    header('Location: ../auth/login_user.php');
    exit();
}

/* =========================================
   ACCOUNT STATUS
========================================= */

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

    header('Location: ../auth/login_user.php?account=inactive');
    exit();
}

/* =========================================
   ORGANIZER INFORMATION
========================================= */

$organizerName = trim(
    (string)(
        $userData['firstname']
        ?? $userData['fullname']
        ?? 'Organizer'
    )
);

if ($organizerName === '') {
    $organizerName = 'Organizer';
}

$profileImage = organizer_profile_image_url(
    $userData['profile_pic'] ?? ''
);

$organizerEmail = $userData['email'] ?? '';

/* =========================================
   CSRF TOKENS
========================================= */

if (empty($_SESSION['organizer_settings_csrf_token'])) {
    $_SESSION['organizer_settings_csrf_token'] =
        bin2hex(random_bytes(32));
}

if (empty($_SESSION['organizer_account_action_csrf_token'])) {
    $_SESSION['organizer_account_action_csrf_token'] =
        bin2hex(random_bytes(32));
}

/* =========================================
   FLASH MESSAGES
========================================= */

$settingsError = '';

$settingsSuccess = (
    ($_GET['password'] ?? '') === 'updated'
)
    ? 'Your password has been updated successfully.'
    : '';

$accountActionErrors = [
    'invalid_action' =>
        'The requested account action is invalid.',

    'invalid_request' =>
        'Your request could not be verified. Please try again.',

    'server_error' =>
        'The account action could not be processed.',

    'unauthorized' =>
        'You are not authorized to perform this action.',

    'deactivate_failed' =>
        'Your account could not be deactivated.',

    'delete_failed' =>
        'Your account could not be deleted. No account changes were completed.',
];

$accountActionError = $_GET['account_error'] ?? '';

if (isset($accountActionErrors[$accountActionError])) {
    $settingsError = $accountActionErrors[$accountActionError];
}

/* =========================================
   CHANGE PASSWORD
========================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['settings_action'] ?? '') === 'change_password'
) {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($csrfToken) ||
        !hash_equals(
            $_SESSION['organizer_settings_csrf_token'],
            $csrfToken
        )
    ) {
        $settingsError =
            'Your request could not be verified. Please refresh and try again.';
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (
            !is_string($currentPassword) ||
            !is_string($newPassword) ||
            !is_string($confirmPassword) ||
            $currentPassword === '' ||
            $newPassword === '' ||
            $confirmPassword === ''
        ) {
            $settingsError =
                'Please complete all password fields.';
        } elseif (strlen($newPassword) < 6) {
            $settingsError =
                'Your new password must contain at least 6 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $settingsError =
                'Your new password and confirmation do not match.';
        } elseif ($newPassword === $currentPassword) {
            $settingsError =
                'Your new password must be different from your current password.';
        } else {
            $passwordStatement = mysqli_prepare(
                $conn,
                "SELECT password
                 FROM users
                 WHERE user_id = ?
                 LIMIT 1"
            );

            if (!$passwordStatement) {
                $settingsError =
                    'Unable to verify your current password.';
            } else {
                mysqli_stmt_bind_param(
                    $passwordStatement,
                    'i',
                    $user_id
                );

                $executed = mysqli_stmt_execute($passwordStatement);

                $passwordResult = $executed
                    ? mysqli_stmt_get_result($passwordStatement)
                    : false;

                $passwordRow = $passwordResult
                    ? mysqli_fetch_assoc($passwordResult)
                    : null;

                mysqli_stmt_close($passwordStatement);

                if (!$executed || !$passwordRow) {
                    $settingsError =
                        'Unable to verify your account. Please try again.';
                } elseif (
                    !password_verify(
                        $currentPassword,
                        $passwordRow['password']
                    )
                ) {
                    $settingsError =
                        'Your current password is incorrect.';
                } elseif (
                    password_verify(
                        $newPassword,
                        $passwordRow['password']
                    )
                ) {
                    $settingsError =
                        'Please choose a different password.';
                } else {
                    $hashedPassword = password_hash(
                        $newPassword,
                        PASSWORD_DEFAULT
                    );

                    $updateStatement = mysqli_prepare(
                        $conn,
                        "UPDATE users
                         SET password = ?
                         WHERE user_id = ?
                         LIMIT 1"
                    );

                    if (!$updateStatement) {
                        $settingsError =
                            'Unable to update your password.';
                    } else {
                        mysqli_stmt_bind_param(
                            $updateStatement,
                            'si',
                            $hashedPassword,
                            $user_id
                        );

                        $updated = mysqli_stmt_execute($updateStatement);

                        mysqli_stmt_close($updateStatement);

                        if ($updated) {
                            session_regenerate_id(true);

                            header(
                                'Location: settingss.php?password=updated'
                            );
                            exit();
                        }

                        $settingsError =
                            'Unable to update your password. Please try again.';
                    }
                }
            }
        }
    }
}

/* =========================================
   NOTIFICATIONS
========================================= */

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Settings | Event MS</title>

    <!-- Keep Bootstrap -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
    >

    <!-- Existing Organizer Styles -->
    

    

    

    <link rel="stylesheet" href="../assets/css/organizer/settingss.css">

    
</head>

<body class="settings-page-body">

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">

    <div class="sidebar-header">
        <a href="org_dash.php" class="brand">
            <i class="bi bi-calendar-event"></i>
            <span>Event MS</span>
        </a>

        <button
            type="button"
            class="close-sidebar"
            onclick="closeSidebar()"
            aria-label="Close menu"
        >
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <div class="sidebar-profile">
        <div class="sidebar-avatar">
            <?php if ($profileImage !== ''): ?>
                <img src="<?= e($profileImage); ?>" alt="Profile photo">
            <?php else: ?>
                <span class="profile-initials">
                    <?= e(mb_strtoupper(mb_substr($organizerName, 0, 1))); ?>
                </span>
            <?php endif; ?>
        </div>

        <div class="sidebar-profile-info">
            <div class="sidebar-profile-name">
                <?= e($organizerName); ?>
            </div>
            <div class="sidebar-profile-role">Organizer</div>
        </div>
    </div>

    <div class="sidebar-menu">

        <div class="sidebar-label">Main Menu</div>

        <a href="org_dash.php">
            <i class="bi bi-house-door"></i>
            <span>Home</span>
        </a>

        <a href="all_events.php">
            <i class="bi bi-calendar3"></i>
            <span>Explore Events</span>
        </a>

        <a href="event_reviews.php">
            <i class="bi bi-star"></i>
            <span>Event Reviews</span>
        </a>
        <a href="archived_events.php">
    <i class="bi bi-archive-fill"></i>
    <span>Archived Events</span>
</a>

        <div class="sidebar-label">Account</div>

        <a href="myprofile.php">
            <i class="bi bi-person"></i>
            <span>My Profile</span>
        </a>

        <a href="settingss.php" class="active" aria-current="page">
            <i class="bi bi-gear"></i>
            <span>Settings</span>
        </a>

        <a href="help.php">
            <i class="bi bi-question-circle"></i>
            <span>Help &amp; Support</span>
        </a>

    </div>

    <div class="sidebar-footer">
        <a
            href="../auth/logout_user.php"
            class="logout-link"
            onclick="return confirm('Are you sure you want to logout?');"
        >
            <i class="bi bi-box-arrow-right"></i>
            <span>Logout</span>
        </a>
    </div>

</aside>

<div
    class="overlay"
    id="overlay"
    onclick="closeSidebar()"
></div>

<!-- TOP NAVBAR -->
<nav class="top-navbar">

    <button
        type="button"
        class="menu-btn"
        onclick="toggleSidebar()"
        aria-label="Open menu"
    >
        <i class="bi bi-list"></i>
    </button>

    <div class="nav-heading">
        <span class="nav-title">Settings</span>
        <span class="nav-subtitle">Manage your account security</span>
    </div>

    <?php require __DIR__ . '/notification_menu.php'; ?>

</nav>

<!-- MAIN CONTENT -->
<main class="main-content settings-page">

    <div class="settings-container">

        <!-- PAGE HEADER -->
        <header class="settings-page-header">

            <div class="settings-heading-icon">
                <i class="bi bi-sliders2"></i>
            </div>

            <div>
                <div class="settings-eyebrow">
                    ACCOUNT PREFERENCES
                </div>

                <h1>Settings</h1>

                <p>
                    Manage your password, protect your account,
                    and review your account management options.
                </p>
            </div>

        </header>

        <!-- SUCCESS MESSAGE -->
        <?php if ($settingsSuccess !== ''): ?>
            <div
                class="settings-alert settings-alert-success"
                role="status"
            >
                <i class="bi bi-check-circle-fill"></i>

                <div>
                    <strong>Password updated</strong>
                    <span><?= e($settingsSuccess); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- ERROR MESSAGE -->
        <?php if ($settingsError !== ''): ?>
            <div
                class="settings-alert settings-alert-error"
                role="alert"
            >
                <i class="bi bi-exclamation-circle-fill"></i>

                <div>
                    <strong>Unable to complete the request</strong>
                    <span><?= e($settingsError); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <div class="settings-layout">

            <!-- CHANGE PASSWORD -->
            <section class="settings-card password-card">

                <div class="settings-card-header">

                    <div class="settings-card-icon password-icon">
                        <i class="bi bi-shield-lock"></i>
                    </div>

                    <div>
                        <h2>Change Password</h2>
                        <p>
                            Update your password to help keep your
                            organizer account secure.
                        </p>
                    </div>

                </div>

                <div class="settings-divider"></div>

                <form
                    method="post"
                    action="settingss.php"
                    class="organizer-password-form"
                    id="organizerPasswordForm"
                >

                    <input
                        type="hidden"
                        name="settings_action"
                        value="change_password"
                    >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['organizer_settings_csrf_token']); ?>"
                    >

                    <div class="settings-field">

                        <label for="current_password">
                            Current Password
                        </label>

                        <div class="password-input-wrap">

                            <i class="bi bi-lock input-leading-icon"></i>

                            <input
                                type="password"
                                class="form-control"
                                id="current_password"
                                name="current_password"
                                placeholder="Enter your current password"
                                autocomplete="current-password"
                                required
                            >

                            <button
                                type="button"
                                class="password-toggle"
                                data-target="current_password"
                                aria-label="Show current password"
                                aria-pressed="false"
                            >
                                <i class="bi bi-eye"></i>
                            </button>

                        </div>

                    </div>

                    <div class="settings-field">

                        <label for="new_password">
                            New Password
                        </label>

                        <div class="password-input-wrap">

                            <i class="bi bi-key input-leading-icon"></i>

                            <input
                                type="password"
                                class="form-control"
                                id="new_password"
                                name="new_password"
                                placeholder="Create a new password"
                                minlength="6"
                                autocomplete="new-password"
                                aria-describedby="passwordHint"
                                required
                            >

                            <button
                                type="button"
                                class="password-toggle"
                                data-target="new_password"
                                aria-label="Show new password"
                                aria-pressed="false"
                            >
                                <i class="bi bi-eye"></i>
                            </button>

                        </div>

                        <small class="settings-hint" id="passwordHint">
                            <i class="bi bi-info-circle"></i>
                            Use at least 6 characters. A longer,
                            unique password is recommended.
                        </small>

                    </div>

                    <div class="settings-field">

                        <label for="confirm_password">
                            Confirm New Password
                        </label>

                        <div class="password-input-wrap">

                            <i class="bi bi-shield-check input-leading-icon"></i>

                            <input
                                type="password"
                                class="form-control"
                                id="confirm_password"
                                name="confirm_password"
                                placeholder="Re-enter your new password"
                                minlength="6"
                                autocomplete="new-password"
                                required
                            >

                            <button
                                type="button"
                                class="password-toggle"
                                data-target="confirm_password"
                                aria-label="Show password confirmation"
                                aria-pressed="false"
                            >
                                <i class="bi bi-eye"></i>
                            </button>

                        </div>

                        <small
                            class="settings-hint"
                            id="passwordMatchMessage"
                            aria-live="polite"
                        >
                            <i class="bi bi-info-circle"></i>
                            Make sure both passwords match.
                        </small>

                    </div>

                    <div class="password-security-note">

                        <i class="bi bi-shield-check"></i>

                        <div>
                            <strong>Password security</strong>
                            <p>
                                Your current password must be verified
                                before your new password is saved.
                            </p>
                        </div>

                    </div>

                    <button
                        type="submit"
                        class="settings-primary-btn"
                    >
                        <i class="bi bi-key-fill"></i>
                        Update Password
                        <i class="bi bi-arrow-right ms-auto"></i>
                    </button>

                </form>

            </section>

            <!-- SECURITY AND PROFILE -->
            <aside class="settings-side-column">

                <section class="settings-info-card">

                    <div class="settings-info-icon">
                        <i class="bi bi-shield-check"></i>
                    </div>

                    <h3>Protect your account</h3>

                    <p>
                        Follow these recommendations to protect
                        your organizer account and event information.
                    </p>

                    <div class="settings-tip">
                        <i class="bi bi-check2"></i>
                        <span>Use a strong, unique password.</span>
                    </div>

                    <div class="settings-tip">
                        <i class="bi bi-check2"></i>
                        <span>Never share your login credentials.</span>
                    </div>

                    <div class="settings-tip">
                        <i class="bi bi-check2"></i>
                        <span>
                            Change your password if you suspect
                            unauthorized access.
                        </span>
                    </div>

                </section>

                <section class="settings-mini-card">

                    <div class="settings-mini-icon">
                        <i class="bi bi-person-badge"></i>
                    </div>

                    <div>
                        <h3>Organizer Account</h3>

                        <p>
                            Review your name, email, contact number,
                            organization details, and profile photo.
                        </p>

                        <a href="myprofile.php">
                            View My Profile
                            <i class="bi bi-arrow-up-right"></i>
                        </a>
                    </div>

                </section>

            </aside>

        </div>

        <!-- DANGER ZONE -->
        <section class="settings-danger-card">

            <div class="danger-heading">

                <div class="danger-heading-icon">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>

                <div>
                    <h2>Danger Zone</h2>
                    <p>
                        Review these account actions carefully before proceeding.
                    </p>
                </div>

            </div>

            <div class="settings-divider"></div>

            <!-- DEACTIVATE -->
            <div class="danger-action-row">

                <div class="danger-action-info">

                    <div class="danger-action-icon deactivate-icon">
                        <i class="bi bi-person-slash"></i>
                    </div>

                    <div>
                        <h3>Deactivate Account</h3>

                        <p>
                            Temporarily disable your account.
                            Your ability to log in and manage events
                            may be affected according to the system's
                            account policy.
                        </p>
                    </div>

                </div>

                <form
                    action="../actions/user_account_action.php"
                    method="post"
                    class="danger-action-form"
                    onsubmit="return confirm('Are you sure you want to deactivate your organizer account? You may lose access to organizer features.');"
                >

                    <input
                        type="hidden"
                        name="account_action_csrf_token"
                        value="<?= e($_SESSION['organizer_account_action_csrf_token']); ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="deactivate"
                    >

                    <button
                        type="submit"
                        class="settings-warning-btn"
                    >
                        <i class="bi bi-person-slash"></i>
                        Deactivate
                    </button>

                </form>

            </div>

            <!-- DELETE -->
            <div class="danger-action-row danger-delete-row">

                <div class="danger-action-info">

                    <div class="danger-action-icon delete-icon">
                        <i class="bi bi-trash3"></i>
                    </div>

                    <div>
                        <h3>Delete Account Permanently</h3>

                        <p>
                            Request permanent account deletion.
                            Depending on the system's deletion rules,
                            associated events and participation records
                            may also be affected. This action may not
                            be reversible.
                        </p>
                    </div>

                </div>

                <form
                    action="../actions/user_account_action.php"
                    method="post"
                    class="danger-action-form"
                    onsubmit="return confirm('WARNING: Permanent account deletion may affect your events and related records. This action may not be reversible. Do you want to continue?');"
                >

                    <input
                        type="hidden"
                        name="account_action_csrf_token"
                        value="<?= e($_SESSION['organizer_account_action_csrf_token']); ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="delete"
                    >

                    <button
                        type="submit"
                        class="settings-delete-btn"
                    >
                        <i class="bi bi-trash3"></i>
                        Delete Account
                    </button>

                </form>

            </div>

        </section>

        <!-- FOOTER -->
        <footer class="settings-footer">

            <span>
                <i class="bi bi-calendar-event"></i>
                <strong>Event MS</strong>
                <span>·</span>
                Organizer Portal
            </span>

            <span>Better Events. Stronger Communities.</span>

        </footer>

    </div>

</main>

<script src="../assets/js/organizer/org_dash.js"></script>
<script src="../assets/js/organizer/notifications.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('organizerPasswordForm');
    const currentPassword = document.getElementById('current_password');
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    const matchMessage = document.getElementById('passwordMatchMessage');

    // Show or hide password fields.
    document.querySelectorAll('.password-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            const input = document.getElementById(this.dataset.target);
            const icon = this.querySelector('i');

            if (!input || !icon) {
                return;
            }

            const showPassword = input.type === 'password';

            input.type = showPassword ? 'text' : 'password';

            icon.className = showPassword
                ? 'bi bi-eye-slash'
                : 'bi bi-eye';

            this.setAttribute(
                'aria-label',
                showPassword ? 'Hide password' : 'Show password'
            );

            this.setAttribute(
                'aria-pressed',
                String(showPassword)
            );
        });
    });

    // Check password confirmation.
    function checkPasswordMatch() {
        if (!newPassword.value || !confirmPassword.value) {
            matchMessage.className = 'settings-hint';

            matchMessage.innerHTML =
                '<i class="bi bi-info-circle"></i> Make sure both passwords match.';

            confirmPassword.setCustomValidity('');

            return true;
        }

        const matches = newPassword.value === confirmPassword.value;

        matchMessage.className = matches
            ? 'settings-hint match-success'
            : 'settings-hint match-error';

        matchMessage.innerHTML = matches
            ? '<i class="bi bi-check-circle"></i> Passwords match.'
            : '<i class="bi bi-exclamation-circle"></i> Passwords do not match.';

        confirmPassword.setCustomValidity(
            matches ? '' : 'The passwords do not match.'
        );

        return matches;
    }

    newPassword.addEventListener('input', checkPasswordMatch);
    confirmPassword.addEventListener('input', checkPasswordMatch);

    if (form) {
        form.addEventListener('submit', function (event) {
            if (!checkPasswordMatch()) {
                event.preventDefault();
                confirmPassword.reportValidity();
                confirmPassword.focus();
                return;
            }

            if (newPassword.value === currentPassword.value) {
                event.preventDefault();

                currentPassword.setCustomValidity(
                    'Choose a password different from your current password.'
                );

                currentPassword.reportValidity();
                currentPassword.focus();

                return;
            }

            currentPassword.setCustomValidity('');
        });
    }

    currentPassword.addEventListener('input', function () {
        currentPassword.setCustomValidity('');
    });
});
</script>

</body>
</html>