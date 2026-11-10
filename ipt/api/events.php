<?php
include '../db.php';
header('Content-Type: application/json');

$sql = "SELECT * FROM events ORDER BY event_date DESC";
$result = $conn->query($sql);

$events = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
}

echo json_encode([
    "status" => "success",
    "count" => count($events),
    "data" => $events
]);
?>