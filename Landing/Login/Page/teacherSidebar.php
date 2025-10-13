<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'lecs_db.php';

if (!isset($_SESSION['teacher_id']) || !in_array($_SESSION['user_type'], ['t', 'a'])) {
    header("Location: /lecs/Landing/Login/login.php");
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']); 

$teacher_id = $_SESSION['teacher_id'];
$stmt = $conn->prepare("SELECT first_name, middle_name, last_name, position, image FROM teachers WHERE teacher_id = ? LIMIT 1");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$teacher = $result->fetch_assoc();
$stmt->close();

// Convert middle name to initial (e.g., "Tapia" -> "T.")
$middleInitial = '';
if (!empty($teacher['middle_name'])) {
    $middleInitial = strtoupper(substr(trim($teacher['middle_name']), 0, 1)) . '.';
}

// Convert number to Roman numeral
function toRoman($num) {
    $map = [
        'M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400,
        'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40,
        'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1
    ];
    $returnValue = '';
    while ($num > 0) {
        foreach ($map as $roman => $int) {
            if ($num >= $int) {
                $num -= $int;
                $returnValue .= $roman;
                break;
            }
        }
    }
    return $returnValue;
}

// Build full name with middle initial
$teacherName = htmlspecialchars(trim(($teacher['first_name'] ?? '') . ' ' . $middleInitial . ' ' . ($teacher['last_name'] ?? '')));

// Format position properly
$teacherPos = $teacher['position'] ?? "Teacher";

// Extract the number part (e.g., from "Teacher 3" → 3)
if (preg_match('/(\d+)/', $teacherPos, $matches)) {
    $num = (int)$matches[1];
    $roman = toRoman($num);
    $teacherPos = preg_replace('/\d+/', $roman, $teacherPos);
}

$teacherImg = !empty($teacher['image']) ? $teacher['image'] : "teacher.png";
$imagePath = "Uploads/teachers/" . $teacherImg;
if (!file_exists(__DIR__ . "/" . $imagePath)) {
    $imagePath = "Uploads/teachers/teacher.png";
}
?>


<link rel="stylesheet" href="css/all.min.css">
<link rel="stylesheet" href="css/sidebar.css">

<div class="sidebar">
    <div class="sidebar-top">
        <img class="logo" src="images/lecs-logo no bg.png" alt="School Logo">
        <h2>Libon East Central School</h2>
        <small class="east">Libon East District</small>

        <div class="nav-links">
            <a href="teacherDashboard.php" class="<?= ($current_page == 'teacherDashboard.php') ? 'active' : '' ?>">Dashboard</a>
            <a href="teacherPupils.php" class="<?= ($current_page == 'teacherPupils.php' || $current_page == 'add_pupil.php' || $current_page == 'edit_pupil.php' || $current_page == 'pupilsProfile.php') ? 'active' : '' ?>">Pupils</a>
            <a href="teacherGrades.php" class="<?= ($current_page == 'teacherGrades.php' || $current_page == 'edit_grades.php') ? 'active' : '' ?>">Grades</a>
            <a href="teacherEventCalendar.php" class="<?= ($current_page == 'teacherEventCalendar.php') ? 'active' : '' ?>">Calendar</a>
        </div>
    </div>

    <div class="sidebar-bottom">
        <div class="toggle-mode">
            <button class="toggle-btn light-mode active" onclick="setMode('light')">☀ Light</button>
            <button class="toggle-btn dark-mode" onclick="setMode('dark')">🌙 Dark</button>
        </div>
        <div class="profile-menu">
            <div class="profile">
                <img src="<?= htmlspecialchars($imagePath); ?>" alt="Profile Picture">
                <div class="profile-info">
                    <strong><?= htmlspecialchars($teacherName); ?></strong>
                    <small><?= htmlspecialchars($teacherPos); ?></small>
                </div>
            </div>
            <div class="burger" role="button" aria-label="Open menu" tabindex="0">&#9776;</div>

            <div id="topMenu" class="top-menu" aria-hidden="true">
                <a class="line" href="teacherProfile.php">Profile</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </div>
</div>

<script>
function setMode(mode) {
    document.body.classList.remove('light', 'dark');
    document.body.classList.add(mode);
    document.querySelectorAll('.toggle-btn').forEach(btn => btn.classList.remove('active'));
    if (mode === 'light') {
        document.querySelector('.light-mode').classList.add('active');
    } else {
        document.querySelector('.dark-mode').classList.add('active');
    }
    localStorage.setItem('theme', mode);
}

document.addEventListener('DOMContentLoaded', () => {
    const savedMode = localStorage.getItem('theme') || 'light';
    setMode(savedMode);
});

const burger = document.querySelector('.burger');
const topMenu = document.getElementById('topMenu');

burger.addEventListener('click', (e) => {
    e.stopPropagation();
    topMenu.classList.toggle('show');
    const isShown = topMenu.classList.contains('show');
    topMenu.setAttribute('aria-hidden', isShown ? 'false' : 'true');
});

document.addEventListener('click', (e) => {
    if (!topMenu.contains(e.target) && !burger.contains(e.target)) {
        topMenu.classList.remove('show');
        topMenu.setAttribute('aria-hidden', 'true');
    }
});
</script>