<?php

session_start();

require_once __DIR__ . '/../config/db.php';


/*
|--------------------------------------------------------------------------
| ONLY POST REQUESTS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header("Location: ../auth/login_user.php");
    exit();
}


/*
|--------------------------------------------------------------------------
| CHECK LOGIN
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role'])
) {

    header("Location: ../auth/login_user.php");
    exit();
}


$user_id = intval($_SESSION['user_id']);

$sessionRole = $_SESSION['role'];


/*
|--------------------------------------------------------------------------
| GET ACTION
|--------------------------------------------------------------------------
*/

$action = trim(
    $_POST['action'] ?? ''
);


/*
|--------------------------------------------------------------------------
| ALLOWED ACTIONS
|--------------------------------------------------------------------------
*/

if (!in_array($action, ['deactivate', 'delete'], true)) {

    header(
        "Location: ../organizer/settingss.php?account_error=invalid_action"
    );

    exit();
}

if (
    empty($_SESSION['organizer_account_action_csrf_token']) ||
    !isset($_POST['account_action_csrf_token']) ||
    !is_string($_POST['account_action_csrf_token']) ||
    !hash_equals(
        $_SESSION['organizer_account_action_csrf_token'],
        $_POST['account_action_csrf_token']
    )
) {
    header(
        "Location: ../organizer/settingss.php?account_error=invalid_request"
    );
    exit();
}


/*
|--------------------------------------------------------------------------
| GET CURRENT USER
|--------------------------------------------------------------------------
*/

$stmt = mysqli_prepare(
    $conn,
    "SELECT
        user_id,
        fullname,
        email,
        role,
        account_status
     FROM users
     WHERE user_id = ?
     LIMIT 1"
);


if (!$stmt) {

    header(
        "Location: ../organizer/settingss.php?account_error=server_error"
    );

    exit();
}


mysqli_stmt_bind_param(
    $stmt,
    "i",
    $user_id
);


mysqli_stmt_execute($stmt);


$result = mysqli_stmt_get_result($stmt);


if (!$result || mysqli_num_rows($result) === 0) {

    mysqli_stmt_close($stmt);

    $_SESSION = [];

    if (ini_get("session.use_cookies")) {

        $params = session_get_cookie_params();

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

    header("Location: ../auth/login_user.php");
    exit();
}


$user = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


/*
|--------------------------------------------------------------------------
| VERIFY ROLE
|--------------------------------------------------------------------------
|
| This prevents an Organizer session from modifying an account
| whose database role does not match.
|
*/

if (
    !isset($user['role']) ||
    $user['role'] !== $sessionRole
) {

    header(
        "Location: ../organizer/settingss.php?account_error=unauthorized"
    );

    exit();
}


/*
|--------------------------------------------------------------------------
| ONLY ORGANIZER
|--------------------------------------------------------------------------
*/

if ($user['role'] !== 'Organizer') {

    header(
        "Location: ../auth/login_user.php"
    );

    exit();
}


/*
|--------------------------------------------------------------------------
| DEACTIVATE ACCOUNT
|--------------------------------------------------------------------------
*/

if ($action === 'deactivate') {


    $updateStmt = mysqli_prepare(
        $conn,
        "UPDATE users
         SET account_status = 'Deactivated'
         WHERE user_id = ?
         LIMIT 1"
    );


    if (!$updateStmt) {

        header(
            "Location: ../organizer/settingss.php?account_error=deactivate_failed"
        );

        exit();
    }


    mysqli_stmt_bind_param(
        $updateStmt,
        "i",
        $user_id
    );


    $updated = mysqli_stmt_execute($updateStmt);


    mysqli_stmt_close($updateStmt);


    if (!$updated) {

        header(
            "Location: ../organizer/settingss.php?account_error=deactivate_failed"
        );

        exit();
    }


    /*
    |--------------------------------------------------------------------------
    | DESTROY SESSION
    |--------------------------------------------------------------------------
    */

    $_SESSION = [];


    if (ini_get("session.use_cookies")) {

        $params = session_get_cookie_params();

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


    /*
    |--------------------------------------------------------------------------
    | REDIRECT TO LOGIN
    |--------------------------------------------------------------------------
    */

    header(
        "Location: ../auth/login_user.php?account=deactivated"
    );

    exit();
}


/*
|--------------------------------------------------------------------------
| DELETE ACCOUNT
|--------------------------------------------------------------------------
|
| Before deleting the organizer, delete related records that may
| reference the organizer's events or the organizer as a participant.
|
*/

if ($action === 'delete') {


    /*
    |--------------------------------------------------------------------------
    | START TRANSACTION
    |--------------------------------------------------------------------------
    */

    mysqli_begin_transaction($conn);


    try {


        /*
        |--------------------------------------------------------------------------
        | DELETE EVENT PARTICIPANTS FOR ORGANIZER'S EVENTS
        |--------------------------------------------------------------------------
        */

        $deleteEventParticipants = mysqli_prepare(
            $conn,
            "DELETE ep
             FROM event_participants ep
             INNER JOIN events e
                ON ep.event_id = e.event_id
             WHERE e.organizer_id = ?"
        );


        if (!$deleteEventParticipants) {
            throw new Exception(
                "Unable to prepare event participants deletion."
            );
        }


        mysqli_stmt_bind_param(
            $deleteEventParticipants,
            "i",
            $user_id
        );


        if (!mysqli_stmt_execute($deleteEventParticipants)) {

            mysqli_stmt_close($deleteEventParticipants);

            throw new Exception(
                "Unable to delete event participants."
            );
        }


        mysqli_stmt_close($deleteEventParticipants);


        /*
        |--------------------------------------------------------------------------
        | DELETE REVIEWS FOR ORGANIZER'S EVENTS
        |--------------------------------------------------------------------------
        */

        $deleteEventReviews = mysqli_prepare(
            $conn,
            "DELETE er
             FROM event_reviews er
             INNER JOIN events e
                ON er.event_id = e.event_id
             WHERE e.organizer_id = ?"
        );


        if (!$deleteEventReviews) {
            throw new Exception(
                "Unable to prepare event reviews deletion."
            );
        }


        mysqli_stmt_bind_param(
            $deleteEventReviews,
            "i",
            $user_id
        );


        if (!mysqli_stmt_execute($deleteEventReviews)) {

            mysqli_stmt_close($deleteEventReviews);

            throw new Exception(
                "Unable to delete event reviews."
            );
        }


        mysqli_stmt_close($deleteEventReviews);


        /*
        |--------------------------------------------------------------------------
        | DELETE EVENTS CREATED BY ORGANIZER
        |--------------------------------------------------------------------------
        */

        $deleteEvents = mysqli_prepare(
            $conn,
            "DELETE FROM events
             WHERE organizer_id = ?"
        );


        if (!$deleteEvents) {
            throw new Exception(
                "Unable to prepare events deletion."
            );
        }


        mysqli_stmt_bind_param(
            $deleteEvents,
            "i",
            $user_id
        );


        if (!mysqli_stmt_execute($deleteEvents)) {

            mysqli_stmt_close($deleteEvents);

            throw new Exception(
                "Unable to delete organizer events."
            );
        }


        mysqli_stmt_close($deleteEvents);


        /*
        |--------------------------------------------------------------------------
        | DELETE PARTICIPATION RECORDS OF THE USER
        |--------------------------------------------------------------------------
        |
        | This handles events created by other organizers where this
        | organizer may have joined as a participant.
        |
        */

        $deleteOwnParticipation = mysqli_prepare(
            $conn,
            "DELETE FROM event_participants
             WHERE user_id = ?"
        );


        if (!$deleteOwnParticipation) {
            throw new Exception(
                "Unable to prepare user participation deletion."
            );
        }


        mysqli_stmt_bind_param(
            $deleteOwnParticipation,
            "i",
            $user_id
        );


        if (!mysqli_stmt_execute($deleteOwnParticipation)) {

            mysqli_stmt_close($deleteOwnParticipation);

            throw new Exception(
                "Unable to delete user participation."
            );
        }


        mysqli_stmt_close($deleteOwnParticipation);


        /*
        |--------------------------------------------------------------------------
        | DELETE USER REVIEWS
        |--------------------------------------------------------------------------
        |
        | Handles reviews written by this organizer as a participant.
        |
        */

        $deleteOwnReviews = mysqli_prepare(
            $conn,
            "DELETE FROM event_reviews
             WHERE user_id = ?"
        );


        if (!$deleteOwnReviews) {
            throw new Exception(
                "Unable to prepare user reviews deletion."
            );
        }


        mysqli_stmt_bind_param(
            $deleteOwnReviews,
            "i",
            $user_id
        );


        if (!mysqli_stmt_execute($deleteOwnReviews)) {

            mysqli_stmt_close($deleteOwnReviews);

            throw new Exception(
                "Unable to delete user reviews."
            );
        }


        mysqli_stmt_close($deleteOwnReviews);

        $deleteNotifications = mysqli_prepare(
            $conn,
            "DELETE FROM notifications
             WHERE user_id = ?"
        );

        if (!$deleteNotifications) {
            throw new Exception(
                "Unable to prepare notification deletion."
            );
        }

        mysqli_stmt_bind_param(
            $deleteNotifications,
            "i",
            $user_id
        );

        if (!mysqli_stmt_execute($deleteNotifications)) {
            mysqli_stmt_close($deleteNotifications);

            throw new Exception(
                "Unable to delete user notifications."
            );
        }

        mysqli_stmt_close($deleteNotifications);


        /*
        |--------------------------------------------------------------------------
        | DELETE USER ACCOUNT
        |--------------------------------------------------------------------------
        */

        $deleteUser = mysqli_prepare(
            $conn,
            "DELETE FROM users
             WHERE user_id = ?
             LIMIT 1"
        );


        if (!$deleteUser) {
            throw new Exception(
                "Unable to prepare user deletion."
            );
        }


        mysqli_stmt_bind_param(
            $deleteUser,
            "i",
            $user_id
        );


        if (!mysqli_stmt_execute($deleteUser)) {

            mysqli_stmt_close($deleteUser);

            throw new Exception(
                "Unable to delete user account."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | CHECK WHETHER USER WAS ACTUALLY DELETED
        |--------------------------------------------------------------------------
        */

        if (mysqli_stmt_affected_rows($deleteUser) !== 1) {

            mysqli_stmt_close($deleteUser);

            throw new Exception(
                "User account was not deleted."
            );
        }


        mysqli_stmt_close($deleteUser);


        /*
        |--------------------------------------------------------------------------
        | COMMIT
        |--------------------------------------------------------------------------
        */

        mysqli_commit($conn);


        /*
        |--------------------------------------------------------------------------
        | DESTROY SESSION
        |--------------------------------------------------------------------------
        */

        $_SESSION = [];


        if (ini_get("session.use_cookies")) {

            $params = session_get_cookie_params();

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


        /*
        |--------------------------------------------------------------------------
        | REDIRECT TO LOGIN
        |--------------------------------------------------------------------------
        */

        header(
            "Location: ../auth/login_user.php?account=deleted"
        );

        exit();


    } catch (Throwable $e) {


        /*
        |--------------------------------------------------------------------------
        | ROLLBACK IF SOMETHING FAILED
        |--------------------------------------------------------------------------
        */

        mysqli_rollback($conn);


        header(
            "Location: ../organizer/settingss.php?account_error=delete_failed"
        );

        exit();
    }
}


header("Location: ../auth/login_user.php");
exit();

?>