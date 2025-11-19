<?php
include 'lecs_db.php';
session_start();

// Restrict access to admins only
if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 'a') {
    header("Location: /lecs/Landing/Login/login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $section_name = trim($conn->real_escape_string($_POST['section_name']));
    $created_by = $_SESSION['teacher_id'];

    if (empty($section_name)) {
        header("Location: adminSectionName.php?error=" . urlencode("Section name cannot be empty"));
        exit;
    }

    // Check for duplicate section name (case-insensitive)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM section_name WHERE LOWER(section_name) = LOWER(?)");
    $stmt->bind_param("s", $section_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row['count'] > 0) {
        header("Location: adminSectionName.php?error=" . urlencode("Section name '$section_name' already exists"));
        $stmt->close();
        $conn->close();
        exit;
    }

    // Insert new section name
    $stmt = $conn->prepare("INSERT INTO section_name (section_name, created_by) VALUES (?, ?)");
    $stmt->bind_param("si", $section_name, $created_by);

    if ($stmt->execute()) {
        header("Location: adminSectionName.php?success=added");
    } else {
        header("Location: adminSectionName.php?error=" . urlencode("Failed to add section name: " . $conn->error));
    }
    $stmt->close();
    $conn->close();
}
?>