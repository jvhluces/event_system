<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin'){
    header("Location: login.php");
    exit();
}
include 'db.php';

if(isset($_GET['id'])){
    $id = intval($_GET['id']);
    $sql = "DELETE FROM event_categories WHERE id = $id";
    if(mysqli_query($conn, $sql)){
        
        header("Location: settings.php");
        exit();
    } else {
        echo "Error deleting category: " . mysqli_error($conn);
    }
} else {
    echo "Invalid request.";
}
?>
