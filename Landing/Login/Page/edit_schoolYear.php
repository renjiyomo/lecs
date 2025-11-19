<?php
include 'lecs_db.php';
session_start(); // Start session to get logged-in user

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id          = intval($_POST['sy_id']);
    $school_year = $conn->real_escape_string($_POST['school_year']);
    $start_date  = $conn->real_escape_string($_POST['start_date']);
    $end_date    = $conn->real_escape_string($_POST['end_date']);
    $updated_by  = $_SESSION['teacher_id']; // logged-in user

    if ($end_date < $start_date) {
        header("Location: adminSchoolYear.php?error=End date must be after start date");
        exit();
    }

    $sql = "UPDATE school_years 
            SET school_year = ?, start_date = ?, end_date = ?, updated_by = ? 
            WHERE sy_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssii", $school_year, $start_date, $end_date, $updated_by, $id);

    if ($stmt->execute()) {
        header("Location: adminSchoolYear.php?success=updated");
    } else {
        header("Location: adminSchoolYear.php?error=Failed to update school year");
    }

    $stmt->close();
    $conn->close();
}
?>
