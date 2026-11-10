<?php
include 'db.php';
session_start();

if(isset($_POST['submit'])){
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE username='$username'";
    $result = mysqli_query($conn, $sql);
    $user = mysqli_fetch_assoc($result);

    if($user && password_verify($password, $user['password'])){
    $_SESSION['user'] = $username;
    $_SESSION['login_time'] = time();
    header("Location: index.php");
    exit();
} else {
    $error = "Invalid username or password!";
}


}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="signup.css">
    <title>senpai</title>
</head>
<body>
    <div class="main">
        <div class="form-container">
            <h2>Login</h2><br>
            <form method="POST" action="login.php">
                <div class="grid-form">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required>
                    </div>
                    <button type="submit" name="submit">Enter</button>
                </div><br>
            </form>
            <a href="signup.php" style="display: block; text-align: center; color: blue; text-decoration:none;">Sign-Up</a>
        </div>
    </div>
</body>
</html>