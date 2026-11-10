<?php
include 'db.php';

if (isset($_GET['id'])) {
    $event_id = $_GET['id'];

    $sql = "DELETE FROM events WHERE event_id = $event_id";

    if (mysqli_query($conn, $sql)) {
        header("Location: index.php");
        exit();
    } else {
        echo "Error deleting record: " . mysqli_error($conn);
    }
} else {
    echo "No ID provided.";
}
?>
