<?php 
include 'lecs_db.php';
session_start();

// ✅ Restrict access to teachers only
if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 't') {
    header("Location: /lecs/Landing/Login/login.php");
    exit;
}

$teacher_id = intval($_SESSION['teacher_id']); // logged-in teacher's ID

// Fetch teacher details to get the name
$stmt = $conn->prepare("SELECT first_name, middle_name, last_name FROM teachers WHERE teacher_id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();
$teacherName = implode(' ', array_filter([$teacher['first_name'], $teacher['middle_name'], $teacher['last_name']]));

// ✅ Get school years
$sy_res = $conn->query("SELECT * FROM school_years ORDER BY start_date DESC");
$school_years = $sy_res->fetch_all(MYSQLI_ASSOC);

$current_sy = $_GET['sy_id'] ?? 'all';
$sy_id = ($current_sy == 'all') ? null : intval($current_sy);

// ✅ Stats for cards (pupils by this teacher for selected sy or all)
$studentCount = 0;
$maleCount = 0;
$femaleCount = 0;
if ($sy_id === null) {
    // All school years
    $studentCount = $conn->query("SELECT COUNT(*) AS total FROM pupils WHERE teacher_id = $teacher_id")->fetch_assoc()['total'];
    $maleCount = $conn->query("SELECT COUNT(*) AS total FROM pupils WHERE teacher_id = $teacher_id AND sex = 'Male'")->fetch_assoc()['total'];
    $femaleCount = $conn->query("SELECT COUNT(*) AS total FROM pupils WHERE teacher_id = $teacher_id AND sex = 'Female'")->fetch_assoc()['total'];
} else {
    // Specific school year
    $studentCount = $conn->query("SELECT COUNT(*) AS total FROM pupils WHERE teacher_id = $teacher_id AND sy_id = $sy_id")->fetch_assoc()['total'];
    $maleCount = $conn->query("SELECT COUNT(*) AS total FROM pupils WHERE teacher_id = $teacher_id AND sy_id = $sy_id AND sex = 'Male'")->fetch_assoc()['total'];
    $femaleCount = $conn->query("SELECT COUNT(*) AS total FROM pupils WHERE teacher_id = $teacher_id AND sy_id = $sy_id AND sex = 'Female'")->fetch_assoc()['total'];
}

// ✅ Pagination setup
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * 10;

// Count total pupils for pagination
$count_where = ($sy_id === null) ? "WHERE teacher_id = ?" : "WHERE teacher_id = ? AND sy_id = ?";
$count_sql = "SELECT COUNT(*) AS total FROM pupils " . $count_where;
$count_stmt = $conn->prepare($count_sql);
$count_params = [$teacher_id];
$count_types = 'i';
if ($sy_id !== null) {
    $count_params[] = $sy_id;
    $count_types .= 'i';
}
$count_stmt->bind_param($count_types, ...$count_params);
$count_stmt->execute();
$total_pupils = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

// ✅ Fetch pupils for this teacher & selected sy or all
$pupils_where = ($sy_id === null) ? "WHERE p.teacher_id = ?" : "WHERE p.teacher_id = ? AND p.sy_id = ?";
$pupils_sql = "
    SELECT p.pupil_id, p.lrn, p.first_name, p.last_name, p.middle_name, p.age, p.sex, p.sy_id,
           s.section_name, s.grade_level_id, p.status
    FROM pupils p 
    JOIN sections s ON p.section_id = s.section_id 
    $pupils_where
    ORDER BY p.last_name ASC
    LIMIT 10 OFFSET ?
";
$stmt = $conn->prepare($pupils_sql);
$params = [$teacher_id];
$types = 'i';
if ($sy_id !== null) {
    $params[] = $sy_id;
    $types .= 'i';
}
$params[] = $offset;
$types .= 'i';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$pupils = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$has_more = $total_pupils > ($page * 10);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LECS Online Student Grading System</title>
    <link rel="icon" href="images/lecs-logo no bg.png" type="image/x-icon">
    <link rel="stylesheet" href="css/teacherDashboard.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <?php include 'theme-script.php'; ?>
</head>
<body>

<div class="container">
    <?php include 'teacherSidebar.php'; ?>

    <div class="main-content">
        <h1>Teacher Dashboard</h1>
        <p>Welcome back, <?php echo htmlspecialchars($teacherName); ?>!</p>

        <div class="stats">
            <div class="card orange">
                <h3><?= $studentCount; ?></h3>
                <p>Total Pupils</p>
            </div>
            <div class="card purple">
                <h3><?= $maleCount; ?></h3>
                <p>Male</p>
            </div>
            <div class="card blue">
                <h3><?= $femaleCount; ?></h3>
                <p>Female</p>
            </div>
        </div>

        <div class="pupil-section">
            <div class="sy-select">
                <label for="schoolYear">School Year:</label>
                <select id="schoolYear" name="sy_id">
                    <option value="all" <?= $current_sy == 'all' ? "selected" : "" ?>>All School Years</option>
                    <?php foreach ($school_years as $sy): ?>
                        <option value="<?= $sy['sy_id'] ?>" <?= ($current_sy != 'all' && intval($current_sy) == $sy['sy_id']) ? "selected" : "" ?>>
                            <?= htmlspecialchars($sy['school_year']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pupil-table-container">
                <table class="pupil-table">
                    <thead>
                        <tr>
                            <th>LRN</th>
                            <th>Name</th>
                            <th>Age</th>
                            <th>Sex</th>
                            <th>Class</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($pupils)): ?>
                            <?php foreach ($pupils as $pupil): 
                                $fullname = strtoupper($pupil['last_name'] . ", " . $pupil['first_name'] . " " . $pupil['middle_name']);
                                $class = "Grade " . htmlspecialchars($pupil['grade_level_id']) . " - " . htmlspecialchars($pupil['section_name']);
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($pupil['lrn']); ?></td>
                                    <td><?= htmlspecialchars($fullname); ?></td>
                                    <td><?= htmlspecialchars($pupil['age']); ?></td>
                                    <td><?= htmlspecialchars($pupil['sex']); ?></td>
                                    <td><?= $class; ?></td>
                                    <td><?= htmlspecialchars($pupil['status']); ?></td>
                                    <td>
                                        <a href="edit_grades.php?pupil_id=<?= $pupil['pupil_id'] ?>&sy_id=<?= $pupil['sy_id'] ?>" class="view-btn">View Grades</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7">No pupils found<?= ($current_sy == 'all') ? '.' : ' for the selected school year.'; ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
                            
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?sy_id=<?= $current_sy ?>&page=<?= $page - 1 ?>" class="prev-btn">← Previous</a>
                <?php else: ?>
                    <span class="prev-btn disabled">← Previous</span>
                <?php endif; ?>

                <?php if ($has_more): ?>
                    <a href="?sy_id=<?= $current_sy ?>&page=<?= $page + 1 ?>" class="next-btn">Next →</a>
                 <?php else: ?>
                    <span class="next-btn disabled">Next →</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('schoolYear').addEventListener('change', function() {
        window.location.href = '?sy_id=' + this.value + '&page=1';
    });
</script>

</body>
</html>