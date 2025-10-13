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
    header("Location: adminSubjects.php?error=Invalid subject ID");
    exit;
}

$subject_id = intval($_GET['id']);

// Check for dependencies in grades table
$grade_check = $conn->prepare("SELECT COUNT(*) as count FROM grades WHERE subject_id = ?");
$grade_check->bind_param("i", $subject_id);
$grade_check->execute();
$grade_result = $grade_check->get_result();
$grade_count = $grade_result->fetch_assoc()['count'];

// Check for dependencies in subjects table (as parent subject)
$subject_check = $conn->prepare("SELECT COUNT(*) as count FROM subjects WHERE parent_subject_id = ?");
$subject_check->bind_param("i", $subject_id);
$subject_check->execute();
$subject_result = $subject_check->get_result();
$subject_count = $subject_result->fetch_assoc()['count'];

// Build error message if dependencies exist
if ($grade_count > 0 || $subject_count > 0) {
    $error_parts = [];
    if ($grade_count > 0) {
        $error_parts[] = "grades";
    }
    if ($subject_count > 0) {
        $error_parts[] = "dependent subjects";
    }
    $error_message = "Cannot delete subject because it has existing " . implode(" and ", $error_parts) . ".";
    header("Location: adminSubjects.php?error=" . urlencode($error_message));
    exit;
}

// If no dependencies, proceed with deletion
$stmt = $conn->prepare("DELETE FROM subjects WHERE subject_id = ?");
$stmt->bind_param("i", $subject_id);

if ($stmt->execute()) {
    header("Location: adminSubjects.php?success=deleted");
} else {
    header("Location: adminSubjects.php?error=Failed to delete subject");
}
$stmt->close();
$grade_check->close();
$subject_check->close();
$conn->close();
exit;
?>