<?php
include 'lecs_db.php';
session_start();

if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 't') {
    header("Location: /lecs/Landing/Login/login.php");
    exit;
}

$pupil_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$force = isset($_GET['force']) && $_GET['force'] === 'true';

if ($pupil_id <= 0) {
    header("Location: teacherPupils.php?error=Invalid pupil ID");
    exit;
}

$conn->begin_transaction();

try {
    if ($force) {
        // Delete associated grades first
        $delete_grades = $conn->query("DELETE FROM grades WHERE pupil_id = $pupil_id");
        if (!$delete_grades) {
            throw new Exception("Failed to delete grades: " . $conn->error);
        }
    }

    // Attempt to delete the pupil
    $delete_pupil = $conn->query("DELETE FROM pupils WHERE pupil_id = $pupil_id");
    if (!$delete_pupil) {
        throw new Exception("Failed to delete pupil: " . $conn->error);
    }

    $conn->commit();
    header("Location: teacherPupils.php?success=Pupil deleted successfully");
} catch (Exception $e) {
    $conn->rollback();
    error_log("Delete error for pupil ID $pupil_id: " . $e->getMessage()); // Log error
    header("Location: teacherPupils.php?error=" . urlencode($e->getMessage()));
}

$conn->close();
?>