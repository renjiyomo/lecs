<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'lecs_db.php';

$current_page = basename($_SERVER['PHP_SELF']); 

$classInfoPages = ['adminGradeLevel.php', 'adminSections.php', 'adminSubjects.php', 'adminSchoolYear.php', 'adminSectionName.php', 'adminOrganizationalChart'];
$isClassInfoActive = in_array($current_page, $classInfoPages);

if (!function_exists('arabic_to_roman')) {
    function arabic_to_roman($num) {
        $roman_numerals = [
            1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD',
            100 => 'C', 90 => 'XC', 50 => 'L', 40 => 'XL',
            10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I'
        ];
        $roman = '';
        foreach ($roman_numerals as $value => $symbol) {
            while ($num >= $value) {
                $roman .= $symbol;
                $num -= $value;
            }
        }
        return $roman;
    }
}


if (!function_exists('format_position')) {
    function format_position($pos) {
        $is_principal = stripos($pos, 'principal') !== false;
        if (preg_match('/(.*) (\d+)$/', $pos, $matches)) {
            $base = $matches[1];
            $num = intval($matches[2]);
            $roman = arabic_to_roman($num);
            $new_pos = $base . ' ' . $roman;
        } else {
            $new_pos = $pos;
        }
        if ($is_principal) {
            $new_pos = 'School ' . $new_pos;
        }
        return $new_pos;
    }
}

$admin_id = isset($_SESSION['teacher_id']) ? intval($_SESSION['teacher_id']) : 0;
$adminData = [
    'first_name' => 'Admin',
    'middle_name' => '',
    'last_name' => 'User',
    'position' => 'Administrator',
    'image' => 'teacher.png'
];

if ($admin_id > 0 && $_SESSION['user_type'] === 'a') {
    $stmt = $conn->prepare("SELECT first_name, middle_name, last_name, position, image FROM teachers WHERE teacher_id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $adminData = $res->fetch_assoc();
        if (empty($adminData['image'])) {
            $adminData['image'] = "teacher.png"; 
        }
    }
    $stmt->close();
}

$middle_initial = !empty($adminData['middle_name']) ? substr($adminData['middle_name'], 0, 1) . '.' : '';
$full_name = trim($adminData['first_name'] . ' ' . $middle_initial . ' ' . $adminData['last_name']);
?>
<link rel="stylesheet" href="css/all.min.css">
<link rel="stylesheet" href="css/sidebar.css">

<div class="sidebar">
    <div class="sidebar-top">
        <img class="logo" src="images/lecs-logo no bg.png" alt="School Logo">
        <h2>Libon East Central School</h2>
        <small class="east">Libon East District</small>

        <div class="nav-links">
            <a href="adminDashboard.php" class="<?= ($current_page == 'adminDashboard.php') ? 'active' : '' ?>">Dashboard</a>
            <a href="adminPupils.php" class="<?= ($current_page == 'adminPupils.php' || $current_page == 'add_pupil.php' || $current_page == 'adminPupilsProfile.php') ? 'active' : '' ?>">Pupils</a>
            <a href="adminTeachers.php" class="<?= ($current_page == 'adminTeachers.php' || $current_page == 'add_teacher.php' || $current_page == 'edit_teacher.php') ? 'active' : '' ?>">Personnel</a>
            <a href="adminGrades.php" class="<?= ($current_page == 'adminGrades.php' || $current_page == 'adminViewGrades.php') ? 'active' : '' ?>">Grades</a>
            <a href="adminEventCalendar.php" class="<?= ($current_page == 'adminEventCalendar.php') ? 'active' : '' ?>">Calendar</a>
            <a href="adminOrganizationalChart.php" class="<?= ($current_page == 'adminOrganizationalChart.php') ? 'active' : '' ?>">Organizational Chart</a>
            
            <a href="#" id="classInfoLink" class="class-info <?= $isClassInfoActive ? 'active' : '' ?>">
                <span>Class Details</span>
                <i id="classInfoIcon" class="fa-solid <?= $isClassInfoActive ? 'fa-angle-down' : 'fa-angle-right' ?>"></i>
            </a>
            <div id="classInfoSubmenu" class="submenu <?= $isClassInfoActive ? 'show' : '' ?>">
                <a href="adminGradeLevel.php" class="<?= ($current_page == 'adminGradeLevel.php') ? 'active' : '' ?>">Grade Level</a>
                <a href="adminSectionName.php" class="<?= ($current_page == 'adminSectionName.php') ? 'active' : '' ?>">Sections</a>
                <a href="adminSections.php" class="<?= ($current_page == 'adminSections.php') ? 'active' : '' ?>">Class</a>
                <a href="adminSubjects.php" class="<?= ($current_page == 'adminSubjects.php') ? 'active' : '' ?>">Subjects</a>
                <a href="adminSchoolYear.php" class="<?= ($current_page == 'adminSchoolYear.php') ? 'active' : '' ?>">School Years</a>
            </div>
        </div>
    </div>

    <div class="sidebar-bottom">
        <div class="toggle-mode">
            <button class="toggle-btn light-mode" onclick="setMode('light')">☀ Light</button>
            <button class="toggle-btn dark-mode" onclick="setMode('dark')">🌙 Dark</button>
        </div>
        <div class="profile-menu">
            <div class="profile">
                <img src="Uploads/teachers/<?= htmlspecialchars($adminData['image']) ?>" alt="Profile Picture">
                <div class="profile-info">
                    <strong><?= htmlspecialchars($full_name) ?></strong>
                    <small><?= htmlspecialchars(format_position($adminData['position'])) ?></small>
                </div>
            </div>
            <div class="burger" role="button" aria-label="Open menu" tabindex="0">&#9776;</div>

            <div id="topMenu" class="top-menu" aria-hidden="true">
                <a class="line" href="adminProfile.php">Profile</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </div>
</div>

<script>
function setMode(mode) {
    document.documentElement.classList.remove('light', 'dark');
    document.documentElement.classList.add(mode);
    document.querySelectorAll('.toggle-btn').forEach(btn => btn.classList.remove('active'));
    if (mode === 'light') {
        document.querySelector('.light-mode').classList.add('active');
    } else {
        document.querySelector('.dark-mode').classList.add('active');
    }
    localStorage.setItem('theme', mode);
    // Update charts if they exist (for dashboard page)
    if (typeof updateChartsTheme === 'function') {
        updateChartsTheme();
    }
}

// Sync toggle buttons with current theme on page load
document.addEventListener('DOMContentLoaded', () => {
    const savedMode = localStorage.getItem('theme') || 
                     (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    setMode(savedMode);
});

// Sidebar functionality (unchanged)
const classInfoLink = document.getElementById("classInfoLink");
const classInfoSubmenu = document.getElementById("classInfoSubmenu");
const classInfoIcon = document.getElementById("classInfoIcon");

classInfoLink.addEventListener("click", function(e) {
    e.preventDefault();
    classInfoSubmenu.classList.toggle("show");
    classInfoIcon.classList.toggle("fa-angle-right");
    classInfoIcon.classList.toggle("fa-angle-down");
    if (classInfoSubmenu.classList.contains("show")) {
        classInfoSubmenu.style.maxHeight = classInfoSubmenu.scrollHeight + "px";
    } else {
        classInfoSubmenu.style.maxHeight = "0";
    }
});

<?php if ($isClassInfoActive): ?>
document.addEventListener("DOMContentLoaded", function() {
    classInfoSubmenu.scrollIntoView({ behavior: "smooth", block: "nearest" });
    classInfoSubmenu.style.maxHeight = classInfoSubmenu.scrollHeight + "px";
});
<?php endif; ?>

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