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
    header("Location: adminSections.php?error=Invalid section ID");
    exit;
}

$section_id = (int)$_GET['id'];

// Check for dependencies in pupils table
$pupil_check = $conn->prepare("SELECT COUNT(*) as count FROM pupils WHERE section_id = ?");
$pupil_check->bind_param("i", $section_id);
$pupil_check->execute();
$pupil_result = $pupil_check->get_result();
$pupil_count = $pupil_result->fetch_assoc()['count'];
$pupil_check->close();

// Check for dependencies in grades table (via pupils associated with the section)
$grade_check = $conn->prepare("SELECT COUNT(*) as count FROM grades g 
                               JOIN pupils p ON g.pupil_id = p.pupil_id 
                               WHERE p.section_id = ?");
$grade_check->bind_param("i", $section_id);
$grade_check->execute();
$grade_result = $grade_check->get_result();
$grade_count = $grade_result->fetch_assoc()['count'];
$grade_check->close();

// Build error message if dependencies exist
if ($pupil_count > 0 || $grade_count > 0) {
    $error_parts = [];
    if ($pupil_count > 0) {
        $error_parts[] = "pupils";
    }
    if ($grade_count > 0) {
        $error_parts[] = "grades";
    }
    $error_message = "Cannot delete class because it has existing " . implode(" and ", $error_parts) . ".";
    header("Location: adminSections.php?error=" . urlencode($error_message));
    $conn->close();
    exit;
}

// Delete the section
$stmt = $conn->prepare("DELETE FROM sections WHERE section_id = ?");
$stmt->bind_param("i", $section_id);

if ($stmt->execute()) {
    header("Location: adminSections.php?success=deleted");
} else {
    header("Location: adminSections.php?error=Failed to delete class");
}
$stmt->close();
$conn->close();
exit;
?>