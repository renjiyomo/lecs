<?php
include 'lecs_db.php';

header('Content-Type: application/json');

$pupil_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($pupil_id <= 0) {
    echo json_encode(['hasGrades' => false]);
    exit;
}

$result = $conn->query("SELECT COUNT(*) as count FROM grades WHERE pupil_id = $pupil_id");
if (!$result) {
    error_log("Error in check_grades.php: " . $conn->error); // Log error
    echo json_encode(['hasGrades' => false]);
    exit;
}

$row = $result->fetch_assoc();
echo json_encode(['hasGrades' => $row['count'] > 0]);
$conn->close();
?>