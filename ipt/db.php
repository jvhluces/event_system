<?php

$conn = mysqli_connect("localhost", "root", "", "ipt2_activity");

if($conn->connect_error){
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>