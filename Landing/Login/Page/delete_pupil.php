<?php
include 'lecs_db.php';
session_start();

if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 't') {
    header("Location: /lecs/Landing/Login/login.php");
    exit;
}

$pupil_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($pupil_id <= 0) {
    header("Location: teacherPupils.php?error=Invalid pupil ID");
    exit;
}

$conn->begin_transaction();

try {
    // Check if pupil has grades
    $grade_check = $conn->query("SELECT COUNT(*) as grade_count FROM grades WHERE pupil_id = $pupil_id");
    if (!$grade_check) {
        throw new Exception("Failed to check grades: " . $conn->error);
    }
    $grade_count = $grade_check->fetch_assoc()['grade_count'];

    if ($grade_count > 0) {
        $conn->rollback();
        header("Location: teacherPupils.php?error=Cannot delete pupil because they have recorded grades");
        exit;
    }

    // Attempt to delete the pupil
    $delete_pupil = $conn->query("DELETE FROM pupils WHERE pupil_id = $pupil_id");
    if (!$delete_pupil) {
        throw new Exception("Failed to delete pupil: " . $conn->error);
    }

    $conn->commit();
    header("Location: teacherPupils.php?success=Pupil unenrolled successfully");
} catch (Exception $e) {
    $conn->rollback();
    error_log("Delete error for pupil ID $pupil_id: " . $e->getMessage());
    header("Location: teacherPupils.php?error=" . urlencode($e->getMessage()));
}

$conn->close();
?>