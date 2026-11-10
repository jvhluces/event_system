<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registered Users</title>
</head>
<body>
    <div class="main">
        <div class="container">
            <h2>User List</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Firstname</th>
                        <th>Lastname</th>
                        <th>Password</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    include 'db.php';
                    $result = mysqli_query($conn, "SELECT * FROM events");
                    while($row = mysqli_fetch_assoc($result)){
                    ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><?= $row['username']; ?></td>
                        <td><?= $row['firstname']; ?></td>
                        <td><?= $row['lastname']; ?></td>
                        <td><?= $row['password']; ?></td>
                        <td class="action-btns">
                            <a href="edit.php?id=<?= $row['id']; ?>" class="btn-edit">Edit</a>
                            <a href="delete.php?id=<?= $row['id']; ?>" class="btn-delete">Delete</a>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    body {
        background-color: black;
        background-size: cover;
        font-family: 'Arial', sans-serif;
    }
    .main {
        background: rgb(255, 255, 255);
        padding: 30px;
        border-radius: 13px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        width: min(1000px, 95%);
        margin: 50px auto;
    }

    h2 {
        text-align: center;
        color: black;
    }

    table {
        width: 100%; 
        border-collapse: collapse; 
        background: white;
        border-radius: 10px;
        margin: auto;
    }

    th, td { 
        padding: 10px; 
        border: 1px solid #222; 
        text-align: center;
    }

    td {
        font-family: 'Arial', sans-serif; 
    }

    th { 
        background: #38489e; 
        color: white; 
    }

    .action-btns { 
        display: flex;
        justify-content: space-evenly;
    }

    .btn-edit {
        background: #38489e; 
        color: white; 
        padding: 5px 10px; 
        text-decoration: none; 
        border-radius: 3px;
    }

    .btn-delete { 
        background: red; 
        color: white; 
        padding: 5px 10px; 
        text-decoration: none; 
        border-radius: 3px; 
    }
</style>
</body>
</html>