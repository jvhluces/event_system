<?php

session_start();
include 'db.php';


if (
    !isset($_SESSION['user_id']) ||
    $_SESSION['role'] !== 'Audience'
) {
    header("Location: login_user.php");
    exit();
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header("Location: user_settings.php");

    exit();

}


$user_id =
    intval($_SESSION['user_id']);



$currentPassword =
    $_POST['current_password'] ?? '';

$newPassword =
    $_POST['new_password'] ?? '';

$confirmPassword =
    $_POST['confirm_password'] ?? '';


if ($newPassword !== $confirmPassword) {

    header(
        "Location: user_settings.php?error=password_mismatch"
    );

    exit();
}

if (strlen($newPassword) < 6) {

    header(
        "Location: user_settings.php?error=password_short"
    );

    exit();
}


$stmt = mysqli_prepare(
    $conn,
    "SELECT password
     FROM users
     WHERE user_id = ?
     LIMIT 1"
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $user_id
);

mysqli_stmt_execute($stmt);

$result =
    mysqli_stmt_get_result($stmt);

if (
    !$result ||
    mysqli_num_rows($result) === 0
) {

    mysqli_stmt_close($stmt);

    header(
        "Location: user_settings.php?error=update_failed"
    );

    exit();
}


$user =
    mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


$currentHash =
    $user['password'];



if (
    !password_verify(
        $currentPassword,
        $currentHash
    )
) {

    header(
        "Location: user_settings.php?error=wrong_password"
    );

    exit();
}


$newHash =
    password_hash(
        $newPassword,
        PASSWORD_DEFAULT
    );

$stmt = mysqli_prepare(
    $conn,
    "UPDATE users
     SET password = ?
     WHERE user_id = ?"
);

mysqli_stmt_bind_param(
    $stmt,
    "si",
    $newHash,
    $user_id
);


if (mysqli_stmt_execute($stmt)) {

    mysqli_stmt_close($stmt);

    header(
        "Location: user_settings.php?success=password"
    );

    exit();

}


mysqli_stmt_close($stmt);


header(
    "Location: user_settings.php?error=update_failed"
);

exit();

?>