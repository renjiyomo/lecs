<?php
include 'lecs_db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $subject_id = (int)$_POST['subject_id'];
    $subject_name_select = $_POST['subject_name_select'];
    $subject_name = $subject_name_select === 'new' ? $conn->real_escape_string($_POST['subject_name']) : $conn->real_escape_string($subject_name_select);
    $sy_id = (int)$_POST['sy_id'];
    $grade_level_id = (int)$_POST['grade_level_id'];
    $parent_subject_id = !empty($_POST['parent_subject_id']) ? (int)$_POST['parent_subject_id'] : NULL;
    $start_quarter = $_POST['start_quarter'];

    if (empty($subject_id) || empty($subject_name) || empty($sy_id) || empty($grade_level_id) || empty($start_quarter)) {
        header("Location: adminSubjects.php?error=Please fill all required fields");
        exit;
    }

    // Check for duplicate subject in the same school year and grade level (excluding current subject)
    $check_sql = "SELECT COUNT(*) as count FROM subjects WHERE subject_name = ? AND sy_id = ? AND grade_level_id = ? AND subject_id != ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("siii", $subject_name, $sy_id, $grade_level_id, $subject_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row['count'] > 0) {
        header("Location: adminSubjects.php?error=Subject already exists for this school year and grade level");
        $stmt->close();
        $conn->close();
        exit;
    }
    $stmt->close();

    // Update the subject
    $sql = "UPDATE subjects SET subject_name = ?, grade_level_id = ?, sy_id = ?, parent_subject_id = ?, start_quarter = ? WHERE subject_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("siiisi", $subject_name, $grade_level_id, $sy_id, $parent_subject_id, $start_quarter, $subject_id);

    if ($stmt->execute()) {
        header("Location: adminSubjects.php?success=updated");
    } else {
        header("Location: adminSubjects.php?error=" . urlencode($stmt->error));
    }

    $stmt->close();
    $conn->close();
}
?>