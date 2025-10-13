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
    header("Location: adminSectionName.php?error=" . urlencode("Invalid section name ID"));
    exit;
}

$section_name_id = (int)$_GET['id'];

// Get the section name for dependency check
$stmt = $conn->prepare("SELECT section_name FROM section_name WHERE section_name_id = ?");
$stmt->bind_param("i", $section_name_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    header("Location: adminSectionName.php?error=" . urlencode("Section name not found"));
    $stmt->close();
    $conn->close();
    exit;
}
$section_name = $result->fetch_assoc()['section_name'];
$stmt->close();

// Check for dependencies in sections table
$section_check = $conn->prepare("SELECT COUNT(*) as count FROM sections WHERE section_name = ?");
$section_check->bind_param("s", $section_name);
$section_check->execute();
$section_result = $section_check->get_result();
$section_count = $section_result->fetch_assoc()['count'];
$section_check->close();

if ($section_count > 0) {
    header("Location: adminSectionName.php?error=" . urlencode("Cannot delete section name '$section_name' because it is used in existing sections"));
    $conn->close();
    exit;
}

// Delete the section name
$stmt = $conn->prepare("DELETE FROM section_name WHERE section_name_id = ?");
$stmt->bind_param("i", $section_name_id);

if ($stmt->execute()) {
    header("Location: adminSectionName.php?success=deleted");
} else {
    header("Location: adminSectionName.php?error=" . urlencode("Failed to delete section name: " . $conn->error));
}
$stmt->close();
$conn->close();
exit;
?>