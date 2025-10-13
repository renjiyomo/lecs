<?php
include 'lecs_db.php';
session_start();

// Restrict access to admins only
if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 'a') {
    header("Location: /lecs/Landing/Login/login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $section_name_id = (int)$_POST['section_name_id'];
    $section_name = trim($conn->real_escape_string($_POST['section_name']));

    // Validate required fields
    if (empty($section_name_id) || empty($section_name)) {
        header("Location: adminSectionName.php?error=" . urlencode("Section name cannot be empty"));
        exit;
    }

    // Check for duplicate section name (case-insensitive, excluding current ID)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM section_name WHERE LOWER(section_name) = LOWER(?) AND section_name_id != ?");
    $stmt->bind_param("si", $section_name, $section_name_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row['count'] > 0) {
        header("Location: adminSectionName.php?error=" . urlencode("Section name '$section_name' already exists"));
        $stmt->close();
        $conn->close();
        exit;
    }

    // Update section name
    $stmt = $conn->prepare("UPDATE section_name SET section_name = ? WHERE section_name_id = ?");
    $stmt->bind_param("si", $section_name, $section_name_id);

    if ($stmt->execute()) {
        header("Location: adminSectionName.php?success=updated");
    } else {
        header("Location: adminSectionName.php?error=" . urlencode("Failed to update section name: " . $conn->error));
    }
    $stmt->close();
    $conn->close();
}
?>