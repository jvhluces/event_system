<?php 
include 'db.php'; 
session_start();

if(!isset($_SESSION['user'])){
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head> 
    <meta charset="UTF-8"> 
    <title>Event List</title> 
</head>
<style>
   body {
    font-family: 'Segoe UI', Arial, sans-serif;
    margin: 20px;
    background-color: white;
    color: black;
}

    h2 {
    color: #2c3e50;
    margin-bottom: 10px;
    text-align: center;
}

    a {
    text-decoration: none;
    color: black;
    font-weight: bold;
    transition: color 0.3s ease;
    text-align: center; 
}

    a:hover {
    color: red;
    text-decoration: underline;
}

    table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    box-shadow: 0 2px 8px rgba(255,255,0,0.1);
    background-color: white;
    border-radius: 25px;
    overflow: hidden;
}

    th, td {
    padding: 10px 8px;
    text-align: center;
}

    th {
    background-color: black;
    color: white;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

    tr:nth-child(even) {
    background-color: #f2f6fc;
}

    tr:hover {
    background-color: #eaf2ff;
    transition: background-color 0.3s ease;
}

    td a {
    margin: 0 5px;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 0.9em;
}

    td a:first-child {
    background-color: #28a745;
    color: #fff;
}

    td a:first-child:hover {
    background-color: #218838;
}

    td a:last-child {
    background-color: #dc3545;
    color: #fff;
}

    td a:last-child:hover {
    background-color: #c82333;
}

</style>

<body>
    <h2>Events</h2>
    <a href="add.php">Add New Event</a>
    
    <table border="1" cellpadding="8">
        <tr>
            <th>ID</th>
            <th>Event Name</th>
            <th>Description</th>
            <th>Date</th>
            <th>Location</th>
            <th>Category</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
        <?php
       $sql = "SELECT * FROM events ORDER BY event_id ASC";
        $result = mysqli_query($conn, $sql);
        
        if (mysqli_num_rows($result) > 0) {
            while($row = mysqli_fetch_assoc($result)) {
                echo "<tr>";
                echo "<td>" . $row["event_id"] . "</td>";
                echo "<td>" . $row["event_name"] . "</td>";
                echo "<td>" . $row["description"] . "</td>";
                echo "<td>" . date('M d, Y h:i A', strtotime($row["event_date"])) . "</td>";
                echo "<td>" . $row["location"] . "</td>";
                echo "<td>" . $row["category"] . "</td>";
                echo "<td>" . $row["status"] . "</td>";
                echo "<td><a href='edit.php?id=" . $row["event_id"] . "'>Edit</a> | 
                <a href='delete.php?id=" . $row["event_id"] . "' 
                onclick=\"return confirm('Are you sure you want to delete this event?');\">Delete</a></td>";

                echo "</tr>";
                }
                } else {
                    echo "<tr><td colspan='7'>No events found</td></tr>";
                    }
                    ?>
    </table>
    
    <a href="logout.php" style="text-decoration: none;">Logout</a>


    <script>
        fetch('api/events.php')
        .then(res => res.json())
        .then(result => {
            console.log(result.data) 
        })
    </script>

</body>
</html>