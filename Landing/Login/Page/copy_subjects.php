<?php
include 'lecs_db.php';
session_start();

if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 'a') {
    header("Location: /lecs/Landing/Login/login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $source = intval($_POST['source_sy_id']);
    $target = intval($_POST['target_sy_id']);

    if (!$source || !$target) {
        header("Location: adminSubjects.php?error=Invalid school years");
        exit;
    }

    // Check if target has subjects
    $check = $conn->prepare("SELECT COUNT(*) FROM subjects WHERE sy_id = ?");
    $check->bind_param("i", $target);
    $check->execute();
    $check->bind_result($count);
    $check->fetch();
    $check->close();

    if ($count > 0) {
        header("Location: adminSubjects.php?error=Target school year already has subjects");
        exit;
    }

    // Mapping old id to new id
    $mapping = [];

    // Copy parents
    $sql_parents = "SELECT * FROM subjects WHERE sy_id = ? AND parent_subject_id IS NULL";
    $stmt = $conn->prepare($sql_parents);
    $stmt->bind_param("i", $source);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $insert = "INSERT INTO subjects (subject_name, grade_level_id, sy_id, parent_subject_id, start_quarter, display_order) 
                   VALUES (?, ?, ?, NULL, ?, ?)";
        $istmt = $conn->prepare($insert);
        $istmt->bind_param("siiss", $row['subject_name'], $row['grade_level_id'], $target, $row['start_quarter'], $row['display_order']);
        $istmt->execute();
        $mapping[$row['subject_id']] = $istmt->insert_id;
        $istmt->close();
    }
    $stmt->close();

    // Copy children
    $sql_children = "SELECT * FROM subjects WHERE sy_id = ? AND parent_subject_id IS NOT NULL";
    $stmt = $conn->prepare($sql_children);
    $stmt->bind_param("i", $source);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $new_parent = $mapping[$row['parent_subject_id']] ?? null;
        if ($new_parent) {
            $insert = "INSERT INTO subjects (subject_name, grade_level_id, sy_id, parent_subject_id, start_quarter, display_order) 
                       VALUES (?, ?, ?, ?, ?, ?)";
            $istmt = $conn->prepare($insert);
            $istmt->bind_param("siiiss", $row['subject_name'], $row['grade_level_id'], $target, $new_parent, $row['start_quarter'], $row['display_order']);
            $istmt->execute();
            $mapping[$row['subject_id']] = $istmt->insert_id;
            $istmt->close();
        }
    }
    $stmt->close();

    header("Location: adminSubjects.php?success=copied");
    exit;
}
?>