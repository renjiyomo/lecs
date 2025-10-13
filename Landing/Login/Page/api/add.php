<?php
$conn = new PDO("mysql:host=localhost;dbname=lecs_gis", "root", "");
$stmt = $conn->prepare("INSERT INTO event_calendar (title, date, end_date, start_time, event_details) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([
  $_POST['title'],
  $_POST['date'],
  $_POST['end_date'],
  $_POST['start_time'],
  $_POST['event_details']
]);
