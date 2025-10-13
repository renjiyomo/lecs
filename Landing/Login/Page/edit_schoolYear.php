<?php
include 'lecs_db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id          = intval($_POST['sy_id']);
    $school_year = $conn->real_escape_string($_POST['school_year']);
    $start_date  = $conn->real_escape_string($_POST['start_date']);
    $end_date    = $conn->real_escape_string($_POST['end_date']);

    if ($end_date < $start_date) {
        header("Location: adminSchoolYear.php?error=End date must be after start date");
        exit();
    }

    $sql = "UPDATE school_years 
            SET school_year='$school_year', start_date='$start_date', end_date='$end_date' 
            WHERE sy_id=$id";

    if ($conn->query($sql)) {
        header("Location: adminSchoolYear.php?success=updated");
    } else {
        header("Location: adminSchoolYear.php?error=Failed to update school year");
    }
    exit();
}
?>
