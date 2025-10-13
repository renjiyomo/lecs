<?php
include 'lecs_db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $school_year = $conn->real_escape_string($_POST['school_year']);
    $start_date = $conn->real_escape_string($_POST['start_date']);
    $end_date   = $conn->real_escape_string($_POST['end_date']);

    if ($end_date < $start_date) {
        header("Location: adminSchoolYear.php?error=End date must be after start date");
        exit();
    }

    $sql = "INSERT INTO school_years (school_year, start_date, end_date) 
            VALUES ('$school_year', '$start_date', '$end_date')";

    if ($conn->query($sql)) {
        header("Location: adminSchoolYear.php?success=added");
    } else {
        header("Location: adminSchoolYear.php?error=Failed to add school year");
    }
    exit();
}
?>
