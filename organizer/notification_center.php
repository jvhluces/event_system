<?php

/* =========================================================
   NOTIFICATION HELPERS
========================================================= */

/**
 * Tinatanggap lang ang LOCAL na link (relative / same-site).
 * Bawal ang http://, https://, javascript:, //domain, at backslash.
 */
if (!function_exists('notification_safe_link')) {
    function notification_safe_link($link): string
    {
        $link = trim((string) $link);

        if ($link === '' || strlen($link) > 500) {
            return '';
        }

        if (preg_match('/[\x00-\x1F\\\\]/', $link) === 1) {
            return '';
        }

        if (strpos($link, '//') === 0) {
            return '';
        }

        if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $link) === 1) {
            return '';
        }

        return $link;
    }
}

/**
 * "Just now", "5 mins ago", "2 hrs ago", "3 days ago", o petsa.
 */
if (!function_exists('notification_time_label')) {
    function notification_time_label($createdAt): string
    {
        $timestamp = $createdAt ? strtotime((string) $createdAt) : false;

        if (!$timestamp) {
            return '';
        }

        $diff = time() - $timestamp;

        if ($diff < 60) {
            return 'Just now';
        }

        if ($diff < 3600) {
            $minutes = (int) floor($diff / 60);
            return $minutes . ' min' . ($minutes > 1 ? 's' : '') . ' ago';
        }

        if ($diff < 86400) {
            $hours = (int) floor($diff / 3600);
            return $hours . ' hr' . ($hours > 1 ? 's' : '') . ' ago';
        }

        if ($diff < 604800) {
            $days = (int) floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        }

        return date('M j, Y', $timestamp);
    }
}

/* =========================================================
   NOTIFICATION CENTER
========================================================= */

function organizer_notification_center($conn, int $userId): array
{
    if (empty($_SESSION['notification_csrf_token'])) {
        $_SESSION['notification_csrf_token'] = bin2hex(random_bytes(32));
    }

    $error  = '';
    $action = $_POST['notification_action'] ?? null;

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $action !== null) {
        $csrfToken = $_POST['notification_csrf_token'] ?? '';

        if (
            !is_string($csrfToken) ||
            !hash_equals($_SESSION['notification_csrf_token'], $csrfToken)
        ) {
            $error = 'Hindi na-verify ang request. Pakisubukan muli.';

        } elseif ($action === 'mark_read') {

            $notificationId = filter_input(
                INPUT_POST,
                'notification_id',
                FILTER_VALIDATE_INT
            );

            // "stay" = mark as read lang, huwag pumunta sa link
            $stay = (($_POST['notification_stay'] ?? '') === '1');

            if (!$notificationId || $notificationId < 1) {
                $error = 'Hindi valid ang notification.';
            } else {
                $statement = mysqli_prepare(
                    $conn,
                    "UPDATE notifications
                     SET is_read = 1
                     WHERE id = ?
                     AND user_id = ?"
                );

                if (!$statement) {
                    $error = 'Hindi ma-update ang notification. Pakisubukan muli.';
                } else {
                    mysqli_stmt_bind_param($statement, 'ii', $notificationId, $userId);

                    if (mysqli_stmt_execute($statement)) {
                        mysqli_stmt_close($statement);

                        if (!$stay) {
                            // Kunin ang link sa DATABASE (hindi sa form)
                            // para hindi magamit sa open-redirect.
                            $linkStatement = mysqli_prepare(
                                $conn,
                                "SELECT link
                                 FROM notifications
                                 WHERE id = ?
                                 AND user_id = ?
                                 LIMIT 1"
                            );

                            if ($linkStatement) {
                                mysqli_stmt_bind_param($linkStatement, 'ii', $notificationId, $userId);

                                if (mysqli_stmt_execute($linkStatement)) {
                                    $linkResult = mysqli_stmt_get_result($linkStatement);
                                    $linkRow    = $linkResult ? mysqli_fetch_assoc($linkResult) : null;
                                    $safeLink   = notification_safe_link($linkRow['link'] ?? '');

                                    mysqli_stmt_close($linkStatement);

                                    if ($safeLink !== '') {
                                        header('Location: ' . $safeLink);
                                        exit();
                                    }
                                } else {
                                    mysqli_stmt_close($linkStatement);
                                }
                            }
                        }

                        organizer_notification_redirect();
                    } else {
                        mysqli_stmt_close($statement);
                        $error = 'Hindi ma-update ang notification. Pakisubukan muli.';
                    }
                }
            }

        } elseif ($action === 'mark_all_read') {

            $statement = mysqli_prepare(
                $conn,
                "UPDATE notifications
                 SET is_read = 1
                 WHERE user_id = ?
                 AND is_read = 0"
            );

            if (!$statement) {
                $error = 'Hindi ma-update ang mga notification. Pakisubukan muli.';
            } else {
                mysqli_stmt_bind_param($statement, 'i', $userId);

                if (mysqli_stmt_execute($statement)) {
                    mysqli_stmt_close($statement);
                    organizer_notification_redirect();
                } else {
                    mysqli_stmt_close($statement);
                    $error = 'Hindi ma-update ang mga notification. Pakisubukan muli.';
                }
            }

        } else {
            $error = 'Hindi valid ang notification action.';
        }
    }

    $unreadCount   = 0;
    $notifications = [];

    $statement = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS unread_count
         FROM notifications
         WHERE user_id = ?
         AND is_read = 0"
    );

    if ($statement) {
        mysqli_stmt_bind_param($statement, 'i', $userId);

        if (mysqli_stmt_execute($statement)) {
            $result      = mysqli_stmt_get_result($statement);
            $row         = $result ? mysqli_fetch_assoc($result) : null;
            $unreadCount = (int) ($row['unread_count'] ?? 0);
        } else {
            $error = $error ?: 'Hindi makuha ang mga notification sa ngayon.';
        }

        mysqli_stmt_close($statement);
    } else {
        $error = $error ?: 'Hindi makuha ang mga notification sa ngayon.';
    }

    $statement = mysqli_prepare(
        $conn,
        "SELECT id, message, link, is_read, created_at
         FROM notifications
         WHERE user_id = ?
         ORDER BY created_at DESC, id DESC
         LIMIT 10"
    );

    if ($statement) {
        mysqli_stmt_bind_param($statement, 'i', $userId);

        if (mysqli_stmt_execute($statement)) {
            $result = mysqli_stmt_get_result($statement);

            while ($result && ($notification = mysqli_fetch_assoc($result))) {
                $notifications[] = $notification;
            }
        } else {
            $error = $error ?: 'Hindi makuha ang mga notification sa ngayon.';
        }

        mysqli_stmt_close($statement);
    } else {
        $error = $error ?: 'Hindi makuha ang mga notification sa ngayon.';
    }

    return [
        'csrf_token'    => $_SESSION['notification_csrf_token'],
        'error'         => $error,
        'unread_count'  => $unreadCount,
        'notifications' => $notifications,
    ];
}

function organizer_notification_redirect(): void
{
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $request    = parse_url($requestUri);

    if (!is_array($request)) {
        $request = [];
    }

    $path = $request['path'] ?? ($_SERVER['PHP_SELF'] ?? 'org_dash.php');

    if (
        !is_string($path) ||
        $path === '' ||
        $path[0] !== '/' ||
        strpos($path, '//') === 0
    ) {
        $path = 'org_dash.php';
    }

    $query = isset($request['query']) ? '?' . $request['query'] : '';

    header('Location: ' . $path . $query);
    exit();
}