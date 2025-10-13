<?php
include 'lecs_db.php';

$sy_id = $_GET['sy_id'] ?? 0;
$grade_id = $_GET['grade_id'] ?? 0;
$subjects = [];

if ($sy_id && $grade_id) {
    $sql = "SELECT subject_id, subject_name FROM subjects WHERE sy_id = ? AND grade_level_id = ? AND parent_subject_id IS NULL ORDER BY subject_name ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $sy_id, $grade_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode($subjects);
?>