<?php
include 'lecs_db.php';
session_start(); // Make sure session is started to get user ID

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $level_name = trim($_POST['level_name']);
    $created_by = $_SESSION['teacher_id']; 

    if (!empty($level_name)) {
        // Check for duplicate grade level (case-insensitive)
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM grade_levels WHERE LOWER(level_name) = LOWER(?)");
        $stmt->bind_param("s", $level_name);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row['count'] > 0) {
            header("Location: adminGradeLevel.php?error=" . urlencode("Grade level '$level_name' already exists"));
            exit;
        }

        // Insert new grade level with created_by
        $stmt = $conn->prepare("INSERT INTO grade_levels (level_name, created_by) VALUES (?, ?)");
        $stmt->bind_param("si", $level_name, $created_by);

        if ($stmt->execute()) {
            header("Location: adminGradeLevel.php?success=added");
            exit;
        } else {
            header("Location: adminGradeLevel.php?error=" . urlencode($conn->error));
            exit;
        }

        $stmt->close();
    } else {
        header("Location: adminGradeLevel.php?error=" . urlencode("Grade level name cannot be empty"));
        exit;
    }
}

$conn->close();
?>
