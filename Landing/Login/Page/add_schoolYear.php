<?php
include 'lecs_db.php';
session_start(); // Start session to get logged-in user

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $school_year = $conn->real_escape_string($_POST['school_year']);
    $start_date  = $conn->real_escape_string($_POST['start_date']);
    $end_date    = $conn->real_escape_string($_POST['end_date']);
    $created_by  = $_SESSION['teacher_id']; // logged-in user

    if ($end_date < $start_date) {
        header("Location: adminSchoolYear.php?error=End date must be after start date");
        exit();
    }

    $sql = "INSERT INTO school_years (school_year, start_date, end_date, created_by) 
            VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssi", $school_year, $start_date, $end_date, $created_by);

    if ($stmt->execute()) {
        header("Location: adminSchoolYear.php?success=added");
    } else {
        header("Location: adminSchoolYear.php?error=Failed to add school year");
    }

    $stmt->close();
    $conn->close();
}
?>
