<?php

session_start();


require_once '../config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';


    if ($email === '' || $password === '') {

        $error = "Please enter your email and password.";

    } else {


        $stmt = mysqli_prepare(
            $conn,
            "SELECT
                user_id,
                email,
                password,
                role,
                account_status
             FROM users
             WHERE email = ?
             LIMIT 1"
        );


        if (!$stmt) {

            $error =
                "Something went wrong while processing your login.";

        } else {

            mysqli_stmt_bind_param(
                $stmt,
                "s",
                $email
            );


            mysqli_stmt_execute($stmt);

            mysqli_stmt_store_result($stmt);



            if (mysqli_stmt_num_rows($stmt) === 1) {

                mysqli_stmt_bind_result(
                    $stmt,
                    $userId,
                    $dbemail,
                    $hashedPassword,
                    $role,
                    $accountStatus
                );


                mysqli_stmt_fetch($stmt);



                $accountStatus = strtolower(
                    trim($accountStatus ?? 'active')
                );


                if ($accountStatus !== 'active') {

                    $error =
                        "Your admin account is inactive. Please contact the administrator.";

                }


                elseif ($role !== 'Admin') {

                    $error =
                        "Access denied. This login is for administrators only.";

                }

                elseif (
                    password_verify(
                        $password,
                        $hashedPassword
                    )
                ) {


                    session_regenerate_id(true);



                    $_SESSION['user_id'] = $userId;
                    $_SESSION['role'] = $role;
                    $_SESSION['email'] = $dbemail;


                    header(
                        "Location: ../admin/admin.php"
                    );

                    exit();

                } else {

                    $error =
                        "Invalid email or password.";

                }

            } else {

                $error =
                    "Invalid email or password.";

            }


            mysqli_stmt_close($stmt);
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

    <title>Admin Login | Event System</title>


    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
    >


    <link rel="stylesheet" href="../assets/css/auth/login.css">

</head>


<body>


<div class="login-wrapper">

    <div class="card shadow login-card">



        <div class="login-header">

            <div class="admin-icon">
                🔐
            </div>

            <h3>
                Admin Login
            </h3>

            <p>
                 Event System
            </p>

        </div>


        <div class="login-body">


            <?php if ($error !== ''): ?>

                <div
                    class="alert alert-danger"
                    role="alert"
                >
                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </div>

            <?php endif; ?>


            <form
                method="POST"
                action="login.php"
            >


                <div class="mb-3">

                    <label
                        for="email"
                        class="form-label"
                    >
                        Admin Email
                    </label>

                    <input
                        type="text"
                        id="email"
                        name="email"
                        class="form-control"
                        placeholder="Enter admin email"
                        value="<?= htmlspecialchars(
                            $_POST['email'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                        autocomplete="email"
                        required
                    >

                </div>


                <div class="mb-3">

                    <label
                        for="password"
                        class="form-label"
                    >
                        Password
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="Enter admin password"
                        autocomplete="current-password"
                        required
                    >

                </div>


                <button
                    type="submit"
                    class="btn btn-primary login-btn w-100"
                >
                    Login as Admin
                </button>


                <div class="admin-note">

                    This login is restricted to
                    authorized administrators.

                </div>


            </form>


        </div>

    </div>

</div>


</body>

</html>