<?php
$conn = new PDO("mysql:host=localhost;dbname=lecs_gis", "root", "");
$stmt = $conn->prepare("DELETE FROM event_calendar WHERE id = ?");
$stmt->execute([$_POST['id']]);