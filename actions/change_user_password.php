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

$user_id = intval($_SESSION['user_id']);

$currentPassword = $_POST['current_password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';


if (
    $currentPassword === '' ||
    $newPassword === '' ||
    $confirmPassword === ''
) {
    header(
        "Location: dashboard/user_settings.php?error=update_failed"
    );
    exit();
}




$query = mysqli_query(
    $conn,
    "SELECT password
     FROM users
     WHERE user_id = $user_id
     LIMIT 1"
);

if (!$query || mysqli_num_rows($query) === 0) {

    header(
        "Location: dashboard/user_settings.php?error=update_failed"
    );

    exit();
}

$user = mysqli_fetch_assoc($query);




if (!password_verify($currentPassword, $user['password'])) {

    header(
        "Location: dashboard/user_settings.php?error=wrong_password"
    );

    exit();
}




if (strlen($newPassword) < 6) {

    header(
        "Location: dashboard/user_settings.php?error=password_short"
    );

    exit();
}



if ($newPassword !== $confirmPassword) {

    header(
        "Location: dashboard/user_settings.php?error=password_mismatch"
    );

    exit();
}



$hashedPassword = password_hash(
    $newPassword,
    PASSWORD_DEFAULT
);

$hashedPasswordEscaped = mysqli_real_escape_string(
    $conn,
    $hashedPassword
);




$update = mysqli_query(
    $conn,
    "UPDATE users
     SET password = '$hashedPasswordEscaped'
     WHERE user_id = $user_id
     LIMIT 1"
);

if ($update) {

    header(
        "Location: dashboard/user_settings.php?success=password"
    );

    exit();

}

header(
    "Location: dashboard/user_settings.php?error=update_failed"
);

exit();
?>