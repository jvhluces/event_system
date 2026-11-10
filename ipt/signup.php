<?php
include 'db.php';

if(isset($_POST['username'])){
    $username  = $_POST['username'];
    $firstname = $_POST['firstname'];
    $lastname  = $_POST['lastname'];
    $password  = $_POST['password'];

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (username, firstname, lastname, password) 
            VALUES ('$username', '$firstname', '$lastname', '$hashedPassword')";

    if(mysqli_query($conn, $sql)){
        header("Location: login.php");
        exit();
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="signup.css">
    <title>Event Management System</title>
</head>

<body>
    <div class="main">
        <div class="form-container">
            <h2>Register</h2><br>
            <form method="POST" action="signup.php">
    <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" required>
    </div>
    <div class="form-group">
        <label>Firstname</label>
        <input type="text" name="firstname" required>
    </div>
    <div class="form-group">
        <label>Lastname</label>
        <input type="text" name="lastname" required>
    </div>
    <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" required>
    </div>
    <button type="submit" name="submit">Sign-Up</button>
    <p style="text-align: center;">Already have an account?</p>
    <a href="login.php" style="text-decoration:none; align-items: center;"> Login</a>

</form>



        </div>
    </div>
</body>
</html>