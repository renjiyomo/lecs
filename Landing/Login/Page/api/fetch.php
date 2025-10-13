<?php
$conn = new PDO("mysql:host=localhost;dbname=lecs_gis", "root", "");
$stmt = $conn->query("SELECT 
    id, 
    title, 
    date as start, 
    DATE_ADD(end_date, INTERVAL 1 DAY) as end,
    end_date,
    DATE_FORMAT(start_time, '%H:%i:%s') as start_time_raw,
    DATE_FORMAT(start_time, '%h:%i %p') as start_time,
    event_details
  FROM event_calendar");

$events = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $events[] = [
    'id' => $row['id'],
    'title' => $row['title'],
    'start' => $row['start'], // ISO format
    'end' => $row['end'],
    'end_date' => $row['end_date'],
    'start_time_raw' => $row['start_time_raw'], // for modal
    'start_time' => $row['start_time'],         // for tooltip
    'event_details' => $row['event_details']
  ];
}

echo json_encode($events);
