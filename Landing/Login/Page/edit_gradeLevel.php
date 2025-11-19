<?php
include 'lecs_db.php';
session_start(); // Make sure session is started to get user ID

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = intval($_POST['grade_level_id']);
    $level_name = trim($_POST['level_name']);
    $updated_by = $_SESSION['teacher_id']; // logged-in user ID

    if ($id > 0 && !empty($level_name)) {
        // Check for duplicates
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM grade_levels WHERE LOWER(level_name) = LOWER(?) AND grade_level_id != ?");
        $stmt->bind_param("si", $level_name, $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row['count'] > 0) {
            header("Location: adminGradeLevel.php?error=" . urlencode("Grade level '$level_name' already exists"));
            exit;
        }

        // Update grade level with updated_by (updated_at is automatic)
        $stmt = $conn->prepare("UPDATE grade_levels SET level_name = ?, updated_by = ? WHERE grade_level_id = ?");
        $stmt->bind_param("sii", $level_name, $updated_by, $id);

        if ($stmt->execute()) {
            header("Location: adminGradeLevel.php?success=updated");
            exit;
        } else {
            header("Location: adminGradeLevel.php?error=" . urlencode($conn->error));
            exit;
        }

        $stmt->close();
    } else {
        header("Location: adminGradeLevel.php?error=" . urlencode("Invalid grade level ID or name"));
        exit;
    }
}

$conn->close();
?>
