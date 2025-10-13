<?php
include 'lecs_db.php';
session_start();

// Restrict access to admins only
if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 'a') {
    header("Location: /lecs/Landing/Login/login.php");
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: adminGradeLevel.php?error=Invalid grade level ID");
    exit;
}

$grade_level_id = intval($_GET['id']);

// Check for dependencies in sections table
$section_check = $conn->prepare("SELECT COUNT(*) as count FROM sections WHERE grade_level_id = ?");
$section_check->bind_param("i", $grade_level_id);
$section_check->execute();
$section_result = $section_check->get_result();
$section_count = $section_result->fetch_assoc()['count'];

// Check for dependencies in subjects table
$subject_check = $conn->prepare("SELECT COUNT(*) as count FROM subjects WHERE grade_level_id = ?");
$subject_check->bind_param("i", $grade_level_id);
$subject_check->execute();
$subject_result = $subject_check->get_result();
$subject_count = $subject_result->fetch_assoc()['count'];

// Build error message if dependencies exist
if ($section_count > 0 || $subject_count > 0) {
    $error_parts = [];
    if ($section_count > 0) {
        $error_parts[] = "sections";
    }
    if ($subject_count > 0) {
        $error_parts[] = "subjects";
    }
    $error_message = "Cannot delete grade level because it has existing " . implode(" and ", $error_parts) . ".";
    header("Location: adminGradeLevel.php?error=" . urlencode($error_message));
    exit;
}

// If no dependencies, proceed with deletion
$stmt = $conn->prepare("DELETE FROM grade_levels WHERE grade_level_id = ?");
$stmt->bind_param("i", $grade_level_id);

if ($stmt->execute()) {
    header("Location: adminGradeLevel.php?success=deleted");
} else {
    header("Location: adminGradeLevel.php?error=Failed to delete grade level");
}
$stmt->close();
$conn->close();
exit;
?>