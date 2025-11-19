<?php
session_start();
$teacher_id = $_SESSION['teacher_id'];

$conn = new PDO("mysql:host=localhost;dbname=lecs_gis", "root", "");

$stmt = $conn->prepare("
    INSERT INTO event_calendar 
        (title, date, end_date, start_time, event_details, created_by) 
    VALUES 
        (?, ?, ?, ?, ?, ?)
");

$stmt->execute([
    $_POST['title'],
    $_POST['date'],
    $_POST['end_date'],
    $_POST['start_time'],
    $_POST['event_details'],
    $teacher_id
]);
