<?php
include 'lecs_db.php';
session_start();

// Restrict access to admins only
if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 'a') {
    header("Location: /lecs/Landing/Login/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $section_name = $conn->real_escape_string($_POST['section_name']);
    $grade_level_id = (int)$_POST['grade_level_id']; // Cast to int for safety
    $sy_id = (int)$_POST['sy_id']; // Cast to int for safety
    $teacher_id = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null; // Cast to int if not null

    // Validate required fields
    if (empty($section_name) || empty($grade_level_id) || empty($sy_id)) {
        header("Location: adminSections.php?error=Please fill all required fields");
        exit;
    }

    // Validate section_name exists in section_name table
    $check_section = $conn->prepare("SELECT section_name FROM section_name WHERE section_name = ?");
    $check_section->bind_param("s", $section_name);
    $check_section->execute();
    $section_result = $check_section->get_result();
    if ($section_result->num_rows === 0) {
        header("Location: adminSections.php?error=Invalid class name");
        $check_section->close();
        $conn->close();
        exit;
    }
    $check_section->close();

    // Check if teacher is already assigned to another section in the same school year
    if ($teacher_id !== null) {
        $check_sql = "SELECT COUNT(*) as count FROM sections WHERE teacher_id = ? AND sy_id = ? AND section_id != ?";
        $stmt = $conn->prepare($check_sql);
        $section_id_exclude = 0; // Placeholder for new section
        $stmt->bind_param("iii", $teacher_id, $sy_id, $section_id_exclude);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row['count'] > 0) {
            header("Location: adminSections.php?error=Teacher is already assigned to another class in this school year");
            $stmt->close();
            $conn->close();
            exit;
        }
        $stmt->close();
    }

    // Insert into sections table
    $sql = "INSERT INTO sections (section_name, grade_level_id, sy_id, teacher_id) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("siii", $section_name, $grade_level_id, $sy_id, $teacher_id);
    
    if ($stmt->execute()) {
        header("Location: adminSections.php?success=added");
    } else {
        header("Location: adminSections.php?error=Failed to add class");
    }
    $stmt->close();
    $conn->close();
}
?>