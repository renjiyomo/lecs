<?php
include 'lecs_db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = intval($_POST['grade_level_id']);
    $level_name = trim($_POST['level_name']);

    if ($id > 0 && !empty($level_name)) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM grade_levels WHERE LOWER(level_name) = LOWER(?) AND grade_level_id != ?");
        $stmt->bind_param("si", $level_name, $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row['count'] > 0) {
            header("Location: adminGradeLevel.php?error=" . urlencode("Grade level '$level_name' already exists"));
            exit;
        }


        $stmt = $conn->prepare("UPDATE grade_levels SET level_name = ? WHERE grade_level_id = ?");
        $stmt->bind_param("si", $level_name, $id);

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