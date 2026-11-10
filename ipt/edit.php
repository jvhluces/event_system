<?php 
include 'db.php'; 

if (isset($_GET['id'])) { 
    $event_id = $_GET['id']; 
    $sql = "SELECT * FROM events WHERE event_id = $event_id"; 
    $result = mysqli_query($conn, $sql); 
    $event = mysqli_fetch_assoc($result); 
    if (!$event) { 
        echo "Event not found."; 
        exit(); 
    } 
} 
if ($_SERVER["REQUEST_METHOD"] == "POST") { 
    $event_name = $_POST['event_name']; 
    $description = $_POST['description'];
    $category = $_POST['category'];
    $event_date = $_POST['event_date']; 
    $location = $_POST['location']; 
    $status = $_POST['status']; 
    $sql = "UPDATE events SET event_name='$event_name', description='$description', category='$category', event_date='$event_date', location='$location', status='$status' WHERE event_id=$event_id"; 
    if (mysqli_query($conn, $sql)) { 
        header("Location: index.php"); 
        exit(); 
    } else { 
        echo "Error updating record: " . mysqli_error($conn); 
    } 
} 
?>
<!DOCTYPE html>
<html lang="en">
<head> 
    <meta charset="UTF-8"> 
    <title>Edit Event</title> 
</head>
<style>
    body {
    font-family: 'Segoe UI', Arial, sans-serif;
    margin: 40px auto;
    max-width: 600px;
    background-color: #f9fafc;
    color: #333;
}

h2 {
    color: #2c3e50;
    margin-bottom: 20px;
    text-align: center;
}

form {
    background: #fff;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

label {
    font-weight: 600;
    display: block;
    margin-bottom: 6px;
    color: #444;
}

input, textarea, select {
    width: 100%;
    padding: 10px;
    margin-bottom: 15px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.3s ease;
}

input:focus, textarea:focus, select:focus {
    border-color: #007BFF;
    outline: none;
}

button {
    background-color: #007BFF;
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 15px;
    font-weight: 600;
    transition: background-color 0.3s ease;
    width: 100%;
}

button:hover {
    background-color: #0056b3;
}

a {
    display: inline-block;
    margin-top: 15px;
    color: #007BFF;
    text-decoration: none;
    font-weight: bold;
    transition: color 0.3s ease;
}

a:hover {
    color: #0056b3;
    text-decoration: underline;
}
.suggestions {
    position: absolute;
    background: #fff;
    border: 1px solid #ccc;
    border-top: none;
    width: 580px;
    max-height: 150px;
    overflow-y: auto;
    z-index: 1000;
}
.suggestions div:hover {
    background-color: #f0f0f0;
}


</style>
<body>
    <h2>Edit Event</h2>
    <form method="POST" action="edit.php?id=<?php echo $event_id; ?>">
        <label for="event_name">Event Name:</label>
        <input type="text" id="event_name" name="event_name" 
           value="<?php echo $event['event_name']; ?>" required><br><br>

        <label for="description">Description:</label>
        <textarea id="description" name="description" rows="4" cols="30" required><?php echo $event['description']; ?></textarea>

        <label for="event_date">Date & Time:</label>
        <input type="datetime-local" id="event_date" name="event_date" 
            value="<?php echo date('Y-m-d\TH:i', strtotime($event['event_date'])); ?>" required><br><br>

        <label for="location">Location:</label>
        <input type="text" id="location" name="location" 
           value="<?php echo $event['location']; ?>" required><br><br>
           <div class="suggestions"  id="suggestions"></div>

        <label for="category">Category:</label>
        <select id="category" name="category" required>
            <option value="Seminar" <?php if($event['category']=="Seminar") echo "selected"; ?>>Seminar</option>
            <option value="Meeting" <?php if($event['category']=="Meeting") echo "selected"; ?>>Meeting</option>
            <option value="Workshop" <?php if($event['category']=="Workshop") echo "selected"; ?>>Workshop</option>
            <option value="Party" <?php if($event['category']=="Party") echo "selected"; ?>>Party</option>
            <option value="Community Event" <?php if($event['category']=="Community Event") echo "selected"; ?>>Community Event</option>
        </select><br><br>
        
        <label for="status">Status:</label>
        <select id="status" name="status" required>
            <option value="Upcoming" <?php if($event['status']=="Upcoming") echo "selected"; ?>>Upcoming</option>
            <option value="Ongoing" <?php if($event['status']=="Ongoing") echo "selected"; ?>>Ongoing</option>
            <option value="Completed" <?php if($event['status']=="Completed") echo "selected"; ?>>Completed</option>
        </select><br><br>
        
        <button type="submit">Update</button>
    </form>
<br>
<a href="index.php">Back to List</a>


<script>
let timer;
const locationInput = document.getElementById("location");
const suggestionsBox = document.getElementById("suggestions");

locationInput.addEventListener("input", function(){
    clearTimeout(timer);
    let query = this.value;
    if(query.length > 2){
        timer = setTimeout(() => {
            fetch("https://photon.komoot.io/api/?q=" + encodeURIComponent(query) + "&limit=5")
            .then(response => response.json())
            .then(data => {
                let suggestions = "";
                if(data.features && data.features.length > 0){
                    data.features.forEach(place => {
                        let display = place.properties.name;
                        if(place.properties.city) display += ", " + place.properties.city;
                        if(place.properties.country) display += ", " + place.properties.country;

                        suggestions += "<div style='padding:8px; cursor:pointer; border-bottom:1px solid #ddd;' " +
                                       "onclick=\"locationInput.value='" + display + "'; suggestionsBox.innerHTML='';\">" 
                                       + display + "</div>";
                    });
                } else {
                    suggestions = "<div style='padding:8px; color:#888;'>No matches found</div>";
                }
                suggestionsBox.innerHTML = suggestions;
            })
            .catch(err => {
                suggestionsBox.innerHTML = "<div style='padding:8px; color:red;'>Error loading suggestions</div>";
            });
        }, 400); 
    } else {
        suggestionsBox.innerHTML = "";
    }
});


document.addEventListener("click", function(e){
    if(e.target !== locationInput && !suggestionsBox.contains(e.target)){
        suggestionsBox.innerHTML = "";
    }
});
</script>


</body>
</html>