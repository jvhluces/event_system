<?php

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/event_helpers.php';

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Audience'
) {
    header("Location: ../auth/login_user.php");
    exit();
}

$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../users/user.php");
    exit();
}

$action = trim($_POST['action'] ?? '');
$eventId = (int) ($_POST['event_id'] ?? 0);


if ($eventId <= 0) {
    header("Location: ../users/user.php?error=event_not_found");
    exit();
}


function getSystemSetting($conn, $settingName, $default = '1')
{
    try {

        $stmt = mysqli_prepare(
            $conn,
            "SELECT setting_value
             FROM system_settings
             WHERE setting_name = ?
             LIMIT 1"
        );

        if (!$stmt) {
            return $default;
        }

        mysqli_stmt_bind_param(
            $stmt,
            "s",
            $settingName
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        if (!$result) {
            mysqli_stmt_close($stmt);
            return $default;
        }

        $row = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);

        if (!$row || !isset($row['setting_value'])) {
            return $default;
        }

        return $row['setting_value'];

    } catch (Throwable $e) {

        return $default;
    }
}


function sendNotification(
    $conn,
    $userId,
    $message
) {

    try {

        $check = mysqli_query(
            $conn,
            "SHOW TABLES LIKE 'notifications'"
        );

        if (!$check || mysqli_num_rows($check) === 0) {
            return;
        }


        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO notifications
            (
                user_id,
                message,
                is_read,
                created_at
            )
            VALUES
            (
                ?,
                ?,
                0,
                NOW()
            )"
        );

        if (!$stmt) {
            return;
        }

        mysqli_stmt_bind_param(
            $stmt,
            "is",
            $userId,
            $message
        );

        mysqli_stmt_execute($stmt);

        mysqli_stmt_close($stmt);

    } catch (Throwable $e) {
    }
}

$stmtEvent = mysqli_prepare(
    $conn,
    "SELECT
        event_id,
        event_name,
        status,
        capacity,
        organizer_id,
        approval_status,
        event_date,
        end_date
     FROM events
     WHERE event_id = ?
     LIMIT 1"
);

if (!$stmtEvent) {

    header(
        "Location: ../users/user.php?error=event_not_found"
    );

    exit();
}


mysqli_stmt_bind_param(
    $stmtEvent,
    "i",
    $eventId
);

mysqli_stmt_execute($stmtEvent);

$resultEvent =
    mysqli_stmt_get_result($stmtEvent);

$event =
    mysqli_fetch_assoc($resultEvent);

mysqli_stmt_close($stmtEvent);


if (!$event) {

    header(
        "Location: ../users/user.php?error=event_not_found"
    );

    exit();
}


$eventName =
    $event['event_name'];

$eventStatus =
    event_lifecycle_status($event['event_date'], $event['end_date']);

if ($event['approval_status'] !== 'approved') {
    header("Location: ../users/user.php?error=event_not_found");
    exit();
}

$capacity =
    (int) $event['capacity'];

$organizerId =
    (int) $event['organizer_id'];




$stmtUser = mysqli_prepare(
    $conn,
    "SELECT fullname
     FROM users
     WHERE user_id = ?
     LIMIT 1"
);

$userName = 'Audience';

if ($stmtUser) {

    mysqli_stmt_bind_param(
        $stmtUser,
        "i",
        $userId
    );

    mysqli_stmt_execute($stmtUser);

    $resultUser =
        mysqli_stmt_get_result($stmtUser);

    $user =
        mysqli_fetch_assoc($resultUser);

    if ($user && !empty($user['fullname'])) {
        $userName = $user['fullname'];
    }

    mysqli_stmt_close($stmtUser);
}


if ($action === 'join') {



    $allowJoin =
        getSystemSetting(
            $conn,
            'allow_audience_join',
            '1'
        );

    if ((string) $allowJoin !== '1') {

        header(
            "Location: ../users/user.php?error=join_disabled"
        );

        exit();
    }


    if ($eventStatus === 'Ended') {

        header(
            "Location: ../users/user.php?error=ended_event"
        );

        exit();
    }


    if ($eventStatus === 'Ongoing') {

        header(
            "Location: ../users/user.php?error=ongoing_event"
        );

        exit();
    }


    if ($eventStatus !== 'Upcoming') {

        header(
            "Location: ../users/user.php?error=join_failed"
        );

        exit();
    }


    $stmtCheck = mysqli_prepare(
        $conn,
        "SELECT participation_id
         FROM event_participants
         WHERE event_id = ?
           AND user_id = ?
         LIMIT 1"
    );

    if (!$stmtCheck) {

        header(
            "Location: ../users/user.php?error=join_failed"
        );

        exit();
    }


    mysqli_stmt_bind_param(
        $stmtCheck,
        "ii",
        $eventId,
        $userId
    );

    mysqli_stmt_execute($stmtCheck);

    $resultCheck =
        mysqli_stmt_get_result($stmtCheck);

    $alreadyJoined =
        mysqli_num_rows($resultCheck) > 0;

    mysqli_stmt_close($stmtCheck);


    if ($alreadyJoined) {

        header(
            "Location: ../users/user.php?error=already_joined"
        );

        exit();
    }
    $stmtCount = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS total
         FROM event_participants
         WHERE event_id = ?"
    );

    if (!$stmtCount) {

        header(
            "Location: ../users/user.php?error=join_failed"
        );

        exit();
    }


    mysqli_stmt_bind_param(
        $stmtCount,
        "i",
        $eventId
    );

    mysqli_stmt_execute($stmtCount);

    $resultCount =
        mysqli_stmt_get_result($stmtCount);

    $countRow =
        mysqli_fetch_assoc($resultCount);

    mysqli_stmt_close($stmtCount);


    $participantCount =
        (int) ($countRow['total'] ?? 0);
    if (
        $capacity > 0 &&
        $participantCount >= $capacity
    ) {

        header(
            "Location: ../users/user.php?error=event_full"
        );

        exit();
    }

    try {

        $stmtJoin = mysqli_prepare(
            $conn,
            "INSERT INTO event_participants
            (
                event_id,
                user_id,
                joined_at
            )
            VALUES
            (
                ?,
                ?,
                NOW()
            )"
        );

        if (!$stmtJoin) {

            header(
                "Location: ../users/user.php?error=join_failed"
            );

            exit();
        }


        mysqli_stmt_bind_param(
            $stmtJoin,
            "ii",
            $eventId,
            $userId
        );


        $insertSuccess =
            mysqli_stmt_execute($stmtJoin);
        $stmtError =
            mysqli_stmt_errno($stmtJoin);

        mysqli_stmt_close($stmtJoin);


        if (!$insertSuccess) {
            if ($stmtError === 1062) {

                header(
                    "Location: ../users/user.php?error=already_joined"
                );

                exit();
            }


            header(
                "Location: ../users/user.php?error=join_failed"
            );

            exit();
        }


    } catch (Throwable $e) {

        header(
            "Location: ../users/user.php?error=join_failed"
        );

        exit();
    }

    if ($organizerId > 0) {

        $message =
            $userName .
            " joined your event: " .
            $eventName;

        sendNotification(
            $conn,
            $organizerId,
            $message
        );
    }

    $adminResult = mysqli_query(
        $conn,
        "SELECT user_id
         FROM users
         WHERE role = 'Admin'"
    );


    if ($adminResult) {

        while (
            $admin =
            mysqli_fetch_assoc($adminResult)
        ) {

            $adminId =
                (int) $admin['user_id'];

            $message =
                $userName .
                " joined event: " .
                $eventName;

            sendNotification(
                $conn,
                $adminId,
                $message
            );
        }
    }


    header(
        "Location: ../users/user.php?status=Upcoming&success=joined"
    );

    exit();
}


if ($action === 'leave') {

    $allowLeave =
        getSystemSetting(
            $conn,
            'allow_audience_leave',
            '1'
        );

    if ((string) $allowLeave !== '1') {

        header(
            "Location: ../users/user.php?error=cannot_leave"
        );

        exit();
    }



    if ($eventStatus !== 'Upcoming') {

        header(
            "Location: ../users/user.php?error=cannot_leave"
        );

        exit();
    }




    $stmtCheck = mysqli_prepare(
        $conn,
        "SELECT participation_id
         FROM event_participants
         WHERE event_id = ?
           AND user_id = ?
         LIMIT 1"
    );

    if (!$stmtCheck) {

        header(
            "Location: ../users/user.php?error=leave_failed"
        );

        exit();
    }


    mysqli_stmt_bind_param(
        $stmtCheck,
        "ii",
        $eventId,
        $userId
    );

    mysqli_stmt_execute($stmtCheck);

    $resultCheck =
        mysqli_stmt_get_result($stmtCheck);

    $participation =
        mysqli_fetch_assoc($resultCheck);

    mysqli_stmt_close($stmtCheck);


    if (!$participation) {

        header(
            "Location: ../users/user.php?error=not_joined"
        );

        exit();
    }


    $participationId =
        (int) $participation['participation_id'];



    $stmtLeave = mysqli_prepare(
        $conn,
        "DELETE FROM event_participants
         WHERE participation_id = ?
           AND event_id = ?
           AND user_id = ?"
    );


    if (!$stmtLeave) {

        header(
            "Location: ../users/user.php?error=leave_failed"
        );

        exit();
    }


    mysqli_stmt_bind_param(
        $stmtLeave,
        "iii",
        $participationId,
        $eventId,
        $userId
    );


    $deleteSuccess =
        mysqli_stmt_execute($stmtLeave);

    mysqli_stmt_close($stmtLeave);


    if (!$deleteSuccess) {

        header(
            "Location: ../users/user.php?error=leave_failed"
        );

        exit();
    }




    if ($organizerId > 0) {

        $message =
            $userName .
            " left your event: " .
            $eventName;

        sendNotification(
            $conn,
            $organizerId,
            $message
        );
    }



    $adminResult = mysqli_query(
        $conn,
        "SELECT user_id
         FROM users
         WHERE role = 'Admin'"
    );


    if ($adminResult) {

        while (
            $admin =
            mysqli_fetch_assoc($adminResult)
        ) {

            $adminId =
                (int) $admin['user_id'];

            $message =
                $userName .
                " left event: " .
                $eventName;

            sendNotification(
                $conn,
                $adminId,
                $message
            );
        }
    }

    header(
        "Location: ../users/user.php?status=Upcoming&success=left"
    );

    exit();
}



header(
    "Location: ../users/user.php?error=join_failed"
);

exit();

?>