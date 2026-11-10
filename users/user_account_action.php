<?php

session_start();
include 'db.php';


/* =========================================================
   AUTH CHECK
========================================================= */

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


/* =========================================================
   DEACTIVATE ACCOUNT
========================================================= */

if (isset($_POST['deactivate'])) {


    $newStatus = 'Inactive';


    $stmt = mysqli_prepare(
        $conn,
        "UPDATE users
         SET account_status = ?
         WHERE user_id = ?"
    );


    mysqli_stmt_bind_param(
        $stmt,
        "si",
        $newStatus,
        $user_id
    );


    if (mysqli_stmt_execute($stmt)) {

        mysqli_stmt_close($stmt);


        /*
           Destroy session
           because account is now inactive
        */

        $_SESSION = [];


        if (
            ini_get("session.use_cookies")
        ) {

            $params =
                session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );

        }


        session_destroy();


        header(
            "Location: login_user.php?deactivated=1"
        );

        exit();

    }


    mysqli_stmt_close($stmt);


    header(
        "Location: user_settings.php?error=update_failed"
    );

    exit();

}


/* =========================================================
   DELETE ACCOUNT
========================================================= */

if (isset($_POST['delete_account'])) {


    /*
       Start transaction so all deletions
       happen together.
    */

    mysqli_begin_transaction($conn);


    try {


        /* -----------------------------------------------
           DELETE EVENT PARTICIPATION
        ------------------------------------------------ */

        $stmt = mysqli_prepare(
            $conn,
            "DELETE FROM event_participants
             WHERE user_id = ?"
        );


        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $user_id
        );


        if (!mysqli_stmt_execute($stmt)) {

            throw new Exception(
                "Failed to remove event participation."
            );

        }


        mysqli_stmt_close($stmt);


        /* -----------------------------------------------
           DELETE REVIEWS
        ------------------------------------------------ */

        $stmt = mysqli_prepare(
            $conn,
            "DELETE FROM event_reviews
             WHERE user_id = ?"
        );


        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $user_id
        );


        if (!mysqli_stmt_execute($stmt)) {

            throw new Exception(
                "Failed to remove reviews."
            );

        }


        mysqli_stmt_close($stmt);


        /* -----------------------------------------------
           GET PROFILE IMAGE
        ------------------------------------------------ */

        $stmt = mysqli_prepare(
            $conn,
            "SELECT profile_pic
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


        $oldProfilePic = '';


        if (
            $result &&
            mysqli_num_rows($result) > 0
        ) {

            $profileRow =
                mysqli_fetch_assoc($result);

            $oldProfilePic =
                $profileRow['profile_pic'] ?? '';

        }


        mysqli_stmt_close($stmt);


        /* -----------------------------------------------
           DELETE USER
        ------------------------------------------------ */

        $stmt = mysqli_prepare(
            $conn,
            "DELETE FROM users
             WHERE user_id = ?"
        );


        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $user_id
        );


        if (!mysqli_stmt_execute($stmt)) {

            throw new Exception(
                "Failed to delete account."
            );

        }


        mysqli_stmt_close($stmt);


        /* -----------------------------------------------
           COMMIT
        ------------------------------------------------ */

        mysqli_commit($conn);


        /* -----------------------------------------------
           DELETE PROFILE IMAGE
           Only after successful DB deletion
        ------------------------------------------------ */

        if (!empty($oldProfilePic)) {

            $cleanPic =
                str_replace(
                    '\\',
                    '/',
                    $oldProfilePic
                );

            $cleanPic =
                ltrim(
                    $cleanPic,
                    '/'
                );


            while (
                strpos(
                    $cleanPic,
                    '../'
                ) === 0
            ) {

                $cleanPic =
                    substr(
                        $cleanPic,
                        3
                    );

            }


            $allowedFolder =
                realpath(
                    __DIR__ .
                    '/uploads/profiles'
                );


            $possibleFile =
                realpath(
                    __DIR__ .
                    '/' .
                    $cleanPic
                );


            if (
                $allowedFolder &&
                $possibleFile &&
                strpos(
                    $possibleFile,
                    $allowedFolder
                ) === 0 &&
                is_file($possibleFile)
            ) {

                @unlink(
                    $possibleFile
                );

            }

        }


        /* -----------------------------------------------
           DESTROY SESSION
        ------------------------------------------------ */

        $_SESSION = [];


        if (
            ini_get("session.use_cookies")
        ) {

            $params =
                session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );

        }


        session_destroy();


        header(
            "Location: login_user.php?deleted=1"
        );

        exit();


    } catch (Exception $e) {


        mysqli_rollback($conn);


        header(
            "Location: user_settings.php?error=delete_failed"
        );

        exit();

    }

}


header(
    "Location: user_settings.php"
);

exit();

?>