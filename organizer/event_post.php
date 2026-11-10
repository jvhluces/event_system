<?php
session_start();

require_once '../config/db.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Organizer'){
    header("Location: ../login_user.php");
    exit();
}

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $event_name = trim($_POST['event_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $event_date = trim($_POST['event_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $capacity = (int) ($_POST['capacity'] ?? 0);
    $category = trim($_POST['category'] ?? '');
    $status = trim($_POST['status'] ?? 'Upcoming');
    $organizer_id = (int) $_SESSION['user_id'];

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO events
            (event_name, description, event_date, end_date, location, capacity,
             category, status, organizer_id, approval_status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
    );
    if ($stmt) {
        mysqli_stmt_bind_param(
            $stmt,
            'sssssissi',
            $event_name,
            $description,
            $event_date,
            $end_date,
            $location,
            $capacity,
            $category,
            $status,
            $organizer_id
        );
    }

    if (
        $stmt &&
        $event_name !== '' &&
        $event_date !== '' &&
        $end_date !== '' &&
        strtotime($end_date) > strtotime($event_date) &&
        mysqli_stmt_execute($stmt)
    ) {
        mysqli_stmt_close($stmt);
        header("Location: org_dash.php"); 
        exit();
    } else {
        if ($stmt) {
            mysqli_stmt_close($stmt);
        }
        http_response_code(400);
        echo "Unable to submit event. Check required fields and make sure the end time follows the start time.";
    }
}
?>
