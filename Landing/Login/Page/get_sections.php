<?php
include 'lecs_db.php';
session_start();

if (!isset($_SESSION['teacher_id'])) {
    exit;
}

$teacher_id = $_SESSION['teacher_id'];

if (isset($_GET['sy_id'])) {
    $sy_id = intval($_GET['sy_id']);
    $sections = $conn->query("SELECT s.section_id, s.section_name, g.level_name 
                              FROM sections s 
                              JOIN grade_levels g ON s.grade_level_id=g.grade_level_id 
                              WHERE s.sy_id=$sy_id AND s.teacher_id=$teacher_id");
    echo "<option value=''>Select Section</option>";
    while ($sec = $sections->fetch_assoc()) {
        echo "<option value='{$sec['section_id']}'>Grade {$sec['level_name']} - {$sec['section_name']}</option>";
    }
}
?>
