<?php

session_start();

require_once '../config/db.php';

/*
|--------------------------------------------------------------------------
| LOGIN USER
|--------------------------------------------------------------------------
| This login page is ONLY for:
| - Audience
| - Organizer
|
| Admin accounts must use the separate Admin login page.
|
| Login identifier:
| - Email or phone number
|
| Self-deactivated account:
| - account_status = Deactivated
| - User can still log in
| - Successful login automatically reactivates the account
|
| Admin-deactivated account:
| - account_status = Inactive
| - Login is NOT allowed
|--------------------------------------------------------------------------
*/


/* =========================================================
   HELPER FUNCTION
========================================================= */

function e($value)
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================================
   FORM PROCESSING
========================================================= */

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | Get login input
    |--------------------------------------------------------------------------
    */

    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | Validate empty fields
    |--------------------------------------------------------------------------
    */

    if ($identifier === '' || $password === '') {

        $error = "Please enter your email or phone number and password.";

    } else {

        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;
        $phoneDigits = '';
        $phoneCandidates = [];

        if ($isEmail) {
            $email = $identifier;
        } elseif (preg_match('/^\+?[0-9\s().-]+$/', $identifier)) {
            $phoneDigits = preg_replace('/\D+/', '', $identifier);

            if (strlen($phoneDigits) >= 7 && strlen($phoneDigits) <= 15) {
                $phoneCandidates[] = $phoneDigits;

                if (preg_match('/^639\d{9}$/', $phoneDigits)) {
                    $phoneCandidates[] = '0' . substr($phoneDigits, 2);
                } elseif (preg_match('/^09\d{9}$/', $phoneDigits)) {
                    $phoneCandidates[] = '63' . substr($phoneDigits, 1);
                } elseif (preg_match('/^9\d{9}$/', $phoneDigits)) {
                    $phoneCandidates[] = '63' . $phoneDigits;
                }

                $phoneCandidates = array_slice(
                    array_unique($phoneCandidates),
                    0,
                    3
                );
                $phoneCandidates = array_pad($phoneCandidates, 3, '');
            }
        }

        if (!$isEmail && $phoneCandidates === []) {
            $error = "Please enter a valid email address or phone number.";
        } else {

            if ($isEmail) {
                $stmt = mysqli_prepare(
                    $conn,
                    "SELECT
                        user_id,
                        firstname,
                        email,
                        password,
                        role,
                        account_status,
                        deactivation_reason,
                        deactivated_until
                     FROM users
                     WHERE email = ?
                     LIMIT 1"
                );
            } else {
                $stmt = mysqli_prepare(
                    $conn,
                    "SELECT
                        user_id,
                        firstname,
                        email,
                        password,
                        role,
                        account_status,
                        deactivation_reason,
                        deactivated_until
                     FROM users
                     WHERE REGEXP_REPLACE(COALESCE(contact, ''), '[^0-9]', '')
                        IN (?, ?, ?)
                     LIMIT 1"
                );
            }


            if (!$stmt) {

                $error = "Unable to process your login. Please try again.";

            } else {

                if ($isEmail) {
                    mysqli_stmt_bind_param(
                        $stmt,
                        "s",
                        $email
                    );
                } else {
                    mysqli_stmt_bind_param(
                        $stmt,
                        "sss",
                        $phoneCandidates[0],
                        $phoneCandidates[1],
                        $phoneCandidates[2]
                    );
                }

                mysqli_stmt_execute($stmt);

                $result = mysqli_stmt_get_result($stmt);

                $user = $result
                    ? mysqli_fetch_assoc($result)
                    : null;

                mysqli_stmt_close($stmt);


                if (!$user) {

                    $error = "Invalid email/phone number or password.";

                } else {

                /*
                |--------------------------------------------------------------------------
                | Verify password FIRST
                |--------------------------------------------------------------------------
                */

                if (
                    !password_verify(
                        $password,
                        $user['password']
                    )
                ) {

                    $error = "Invalid email/phone number or password.";

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Normalize role and account status
                    |--------------------------------------------------------------------------
                    */

                    $role = trim($user['role'] ?? '');

                    $accountStatus = strtolower(
                        trim($user['account_status'] ?? 'active')
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | ADMIN PROTECTION
                    |--------------------------------------------------------------------------
                    |
                    | Admin accounts MUST NOT be able to login through
                    | login_user.php.
                    |
                    | They must use the separate Admin login page.
                    |--------------------------------------------------------------------------
                    */

                    if ($role === 'Admin') {

                        $error =
                            "This login page is for Audience and Organizer accounts only. " .
                            "Please use the Administrator login page.";

                    }

                    /*
                    |--------------------------------------------------------------------------
                    | SELF-DEACTIVATED ACCOUNT
                    |--------------------------------------------------------------------------
                    |
                    | Deactivated means the USER chose to deactivate
                    | their own account.
                    |
                    | They are still allowed to login.
                    |
                    | After successful password verification:
                    | - Change status to Active
                    | - Clear deactivation reason
                    | - Clear deactivation date
                    |--------------------------------------------------------------------------
                    */

                    elseif ($accountStatus === 'deactivated') {

                        $reactivateStmt = mysqli_prepare(
                            $conn,
                            "UPDATE users
                             SET
                                account_status = 'Active',
                                deactivation_reason = NULL,
                                deactivated_until = NULL
                             WHERE user_id = ?"
                        );


                        if (!$reactivateStmt) {

                            $error =
                                "Unable to reactivate your account. Please try again.";

                        } else {

                            mysqli_stmt_bind_param(
                                $reactivateStmt,
                                "i",
                                $user['user_id']
                            );

                            if (
                                !mysqli_stmt_execute(
                                    $reactivateStmt
                                )
                            ) {

                                mysqli_stmt_close(
                                    $reactivateStmt
                                );

                                $error =
                                    "Unable to reactivate your account. Please try again.";

                            } else {

                                mysqli_stmt_close(
                                    $reactivateStmt
                                );

                                /*
                                |--------------------------------------------------------------------------
                                | Reactivation successful
                                |--------------------------------------------------------------------------
                                */

                                session_regenerate_id(true);

                                $_SESSION['user_id'] =
                                    (int) $user['user_id'];

                                $_SESSION['role'] =
                                    $role;

                                $_SESSION['firstname'] =
                                    $user['firstname'];

                                /*
                                |--------------------------------------------------------------------------
                                | Optional session flag
                                |--------------------------------------------------------------------------
                                */

                                $_SESSION['account_reactivated'] = true;


                                /*
                                |--------------------------------------------------------------------------
                                | Redirect according to role
                                |--------------------------------------------------------------------------
                                */

                                if ($role === 'Organizer') {

                                    header(
                                        "Location: ../organizer/org_dash.php"
                                    );
                                    exit();

                                }

                                if ($role === 'Audience') {

                                    header(
                                        "Location: ../users/user.php"
                                    );
                                    exit();

                                }

                                /*
                                |--------------------------------------------------------------------------
                                | Unknown role
                                |--------------------------------------------------------------------------
                                */

                                session_unset();
                                session_destroy();

                                $error =
                                    "Your account role is not recognized.";
                            }
                        }

                    }

                    /*
                    |--------------------------------------------------------------------------
                    | ACTIVE ACCOUNT
                    |--------------------------------------------------------------------------
                    */

                    elseif ($accountStatus === 'active') {

                        /*
                        |--------------------------------------------------------------------------
                        | Only Audience and Organizer are allowed
                        |--------------------------------------------------------------------------
                        */

                        if (
                            $role !== 'Audience' &&
                            $role !== 'Organizer'
                        ) {

                            $error =
                                "This login page is only for Audience and Organizer accounts.";

                        } else {

                            /*
                            |--------------------------------------------------------------------------
                            | Secure session
                            |--------------------------------------------------------------------------
                            */

                            session_regenerate_id(true);

                            $_SESSION['user_id'] =
                                (int) $user['user_id'];

                            $_SESSION['role'] =
                                $role;

                            $_SESSION['firstname'] =
                                $user['firstname'];


                            /*
                            |--------------------------------------------------------------------------
                            | Redirect
                            |--------------------------------------------------------------------------
                            */

                            if ($role === 'Organizer') {

                                header(
                                    "Location: ../organizer/org_dash.php"
                                );
                                exit();
                            }

                            if ($role === 'Audience') {

                                header(
                                    "Location: ../users/user.php"
                                );
                                exit();
                            }
                        }

                    }

                    /*
                    |--------------------------------------------------------------------------
                    | ADMIN-DEACTIVATED / BLOCKED / SUSPENDED ACCOUNT
                    |--------------------------------------------------------------------------
                    |
                    | IMPORTANT:
                    |
                    | These accounts are NOT automatically reactivated.
                    |
                    | Only the Admin can reactivate them.
                    |--------------------------------------------------------------------------
                    */

                    elseif (
                        $accountStatus === 'inactive' ||
                        $accountStatus === 'blocked' ||
                        $accountStatus === 'suspended'
                    ) {

                        $error =
                            "Your account is currently deactivated or inactive. " .
                            "Please contact the administrator.";

                    }

                    /*
                    |--------------------------------------------------------------------------
                    | UNKNOWN ACCOUNT STATUS
                    |--------------------------------------------------------------------------
                    */

                    else {

                        $error =
                            "Your account cannot be accessed at this time. " .
                            "Please contact the administrator.";
                        }
                    }
                }
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Login | Event System</title>

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Bootstrap Icons -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
    >

    <link rel="stylesheet" href="../assets/css/auth/login_user.css">

</head>

<body>

<div class="login-wrapper">

    <div class="login-card">

        <!-- ICON -->
        <div class="login-icon">
            <i class="bi bi-calendar-event"></i>
        </div>


        <!-- TITLE -->
        <h2 class="login-title">
            Welcome Back
        </h2>

        <p class="login-subtitle">
            Sign in to your Event System account
        </p>


        <!-- =====================================================
             SELF-DEACTIVATED MESSAGE
        ====================================================== -->

        <?php if (isset($_GET['deactivated'])): ?>

            <div class="deactivated-note">

                <i class="bi bi-check-circle-fill me-1"></i>

                Your account has been deactivated successfully.
                You have been logged out.
                You can log in again anytime to reactivate your account.

            </div>

        <?php endif; ?>


        <!-- =====================================================
             DELETED MESSAGE
        ====================================================== -->

        <?php if (isset($_GET['deleted'])): ?>

            <div class="alert alert-success">

                <i class="bi bi-check-circle-fill me-1"></i>

                Your account has been permanently deleted.

            </div>

        <?php endif; ?>


        <!-- =====================================================
             ERROR
        ====================================================== -->

        <?php if (!empty($error)): ?>

            <div class="alert alert-danger">

                <i class="bi bi-exclamation-triangle-fill me-1"></i>

                <?= e($error); ?>

            </div>

        <?php endif; ?>


        <!-- =====================================================
             LOGIN FORM
        ====================================================== -->

        <form
            method="POST"
            action="login_user.php"
        >

            <!-- EMAIL OR PHONE -->

            <div class="mb-3">

                <label
                    for="identifier"
                    class="form-label"
                >
                    Email or Phone Number
                </label>

                <div class="input-group">

                    <span class="input-group-text">
                        <i class="bi bi-person"></i>
                    </span>

                    <input
                        type="text"
                        id="identifier"
                        name="identifier"
                        class="form-control"
                        placeholder="Enter your email or phone number"
                        value="<?= e($_POST['identifier'] ?? ''); ?>"
                        autocomplete="firstname"
                        required
                    >

                </div>

            </div>


            <!-- PASSWORD -->

            <div class="mb-4">

                <label
                    for="password"
                    class="form-label"
                >
                    Password
                </label>

                <div class="input-group">

                    <span class="input-group-text">
                        <i class="bi bi-lock"></i>
                    </span>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >

                </div>

            </div>


            <!-- LOGIN BUTTON -->

            <button
                type="submit"
                class="login-btn"
            >

                <i class="bi bi-box-arrow-in-right me-1"></i>

                Login

            </button>

        </form>


        <!-- REGISTER -->

        <div class="register-link">

            Don't have an account?

            <a href="register.php">
                Register here
            </a>

        </div>


        <!-- INFO -->

        <div class="info-text">

            Audience and Organizer accounts only.

        </div>

    </div>

</div>

</body>
</html>