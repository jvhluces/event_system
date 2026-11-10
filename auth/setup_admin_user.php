<?php
include 'db.php';

$email = "admin@gmail.com";
$password = password_hash("admin123", PASSWORD_DEFAULT);
$role = "Admin";

$sql = "INSERT INTO users (email, password, role) VALUES ('$email', '$password', '$role')";
if(mysqli_query($conn, $sql)){
    echo "Admin user created!";
} else {
    echo "Error: " . mysqli_error($conn);
}
?>
