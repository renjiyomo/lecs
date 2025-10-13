<?php 
include 'lecs_db.php';
session_start();

// ✅ Restrict access to teachers only
if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 't') {
    header("Location: /lecs/Landing/Login/login.php");
    exit;
}

$teacher_id = intval($_SESSION['teacher_id']); // logged-in teacher's ID

// ✅ Stats for cards (only pupils by this teacher)
$studentCount = $conn->query("SELECT COUNT(*) AS total FROM pupils WHERE teacher_id = $teacher_id")->fetch_assoc()['total'];
$maleCount = $conn->query("SELECT COUNT(*) AS total FROM pupils WHERE teacher_id = $teacher_id AND sex = 'Male'")->fetch_assoc()['total'];
$femaleCount = $conn->query("SELECT COUNT(*) AS total FROM pupils WHERE teacher_id = $teacher_id AND sex = 'Female'")->fetch_assoc()['total'];

// ✅ Get school years
$sy_res = $conn->query("SELECT * FROM school_years ORDER BY sy_id DESC");
$school_years = $sy_res->fetch_all(MYSQLI_ASSOC);
$current_sy = $_GET['sy_id'] ?? null;

if ($current_sy === null) {
    $current_sy = $conn->query("SELECT sy_id FROM school_years ORDER BY sy_id DESC LIMIT 1")->fetch_assoc()['sy_id'] ?? null;
}

// ✅ Fetch pupils for this teacher & school year
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * 10;

$pupils_sql = "
    SELECT p.pupil_id, p.lrn, p.first_name, p.last_name, p.middle_name, p.age, p.sex, 
           s.section_name, s.grade_level_id, p.status
    FROM pupils p 
    JOIN sections s ON p.section_id = s.section_id 
    WHERE p.teacher_id = ? AND p.sy_id = ?
    ORDER BY p.last_name ASC
";
$stmt = $conn->prepare($pupils_sql);
$stmt->bind_param("ii", $teacher_id, $current_sy);
$stmt->execute();
$pupils = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$total_pupils = count($pupils);
$pupils = array_slice($pupils, $offset, 10);
$has_more = $total_pupils > ($page * 10);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>LECS SIS - Teacher Dashboard</title>
    <link rel="icon" href="images/lecs-logo no bg.png" type="image/x-icon">
    <link rel="stylesheet" href="css/teacherDashboard.css">
    <link rel="stylesheet" href="css/sidebar.css">
</head>
<body class="light">

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
                    <?php foreach ($school_years as $sy): ?>
                        <option value="<?= $sy['sy_id'] ?>" <?= $sy['sy_id'] == $current_sy ? "selected" : "" ?>>
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
                                        <a href="edit_grades.php?pupil_id=<?= $pupil['pupil_id'] ?>&sy_id=<?= $current_sy ?>" class="view-btn">View Grades</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7">No pupils found for the selected school year.</td></tr>
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
