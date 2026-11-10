<?php
include 'db.php';

if (isset($_GET['q'])) {
    $q = mysqli_real_escape_string($conn, $_GET['q']);
    $sql = "SELECT DISTINCT location FROM events WHERE location LIKE '%$q%' LIMIT 5";
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) > 0) {
        while($row = mysqli_fetch_assoc($result)){
            echo "<div style='padding:8px; cursor:pointer; border-bottom:1px solid #ddd;' 
                     onclick=\"document.getElementById('location').value='" . $row['location'] . "'; 
                               document.getElementById('suggestions').innerHTML='';\">" 
                 . $row['location'] . "</div>";
        }
    } else {
        echo "<div style='padding:8px; color:#888;'>No matches found</div>";
    }
}
?>
