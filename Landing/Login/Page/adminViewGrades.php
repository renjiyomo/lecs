<?php
include 'lecs_db.php';
session_start();

// Restrict access to admins only
if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 'a') {
    header("Location: /lecs/Landing/Login/login.php");
    exit;
}

$teacher_id = intval($_SESSION['teacher_id']);

$pupil_id = $_GET['pupil_id'] ?? 0;
$sy_id = $_GET['sy_id'] ?? 0;

if ($pupil_id == 0 || $sy_id == 0) {
    header("Location: adminGrades.php");
    exit;
}

$p_sql = "SELECT p.*, s.grade_level_id 
          FROM pupils p 
          JOIN sections s ON p.section_id = s.section_id 
          WHERE p.pupil_id = ? AND p.sy_id = ?";
$p_stmt = $conn->prepare($p_sql);
$p_stmt->bind_param("ii", $pupil_id, $sy_id);
$p_stmt->execute();
$selected_pupil = $p_stmt->get_result()->fetch_assoc();
if (!$selected_pupil) {
    header("Location: adminGrades.php");
    exit;
}

$grade_level = intval($selected_pupil['grade_level_id']);
$fullname = strtoupper($selected_pupil['last_name'] . ", " . $selected_pupil['first_name'] . " " . $selected_pupil['middle_name']);

$classmates_sql = "SELECT pupil_id, last_name, first_name, middle_name 
                   FROM pupils 
                   WHERE section_id = ? AND sy_id = ? 
                   ORDER BY last_name, first_name";
$classmates_stmt = $conn->prepare($classmates_sql);
$classmates_stmt->bind_param("ii", $selected_pupil['section_id'], $sy_id);
$classmates_stmt->execute();
$classmates = $classmates_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$top_subjects = [];
$components = [];
$sub_res = $conn->query("SELECT * FROM subjects 
                         WHERE grade_level_id = {$selected_pupil['grade_level_id']} 
                         AND sy_id = $sy_id ORDER BY subject_name ASC");
while ($sub = $sub_res->fetch_assoc()) {
    if ($sub['parent_subject_id']) {
        $components[$sub['parent_subject_id']][] = $sub;
    } else {
        $top_subjects[] = $sub;
    }
}

$grades = [];
$g_res = $conn->query("SELECT * FROM grades 
                       WHERE pupil_id = $pupil_id AND sy_id = $sy_id");
while ($g = $g_res->fetch_assoc()) {
    $grades[$g['subject_id']][$g['quarter']] = intval($g['grade']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Grades</title>
    <link rel="icon" href="images/lecs-logo no bg.png" type="image/x-icon">
    <link rel="stylesheet" href="css/editGrades.css">
    <link rel="stylesheet" href="css/sidebar.css">
</head>
<body class="light">
<div class="container">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <h1>
            <span class="back-arrow" onclick="window.location.href='adminGrades.php'">←</span>
            Grades of Pupil
        </h1>

        <div class="top-row">
            <form method="get" id="pupilSelectForm">
                <input type="hidden" name="sy_id" value="<?= $sy_id ?>">
                <select class="sy-selection" name="pupil_id" onchange="this.form.submit()">
                    <?php foreach ($classmates as $classmate): ?>
                        <option value="<?= $classmate['pupil_id'] ?>" <?= $classmate['pupil_id'] == $pupil_id ? "selected" : "" ?>>
                            <?= strtoupper($classmate['last_name'] . ", " . $classmate['first_name'] . " " . $classmate['middle_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <div class="button-group">
                <form id="exportForm" method="post" action="export_sf10.php" style="display: inline;">
                    <input type="hidden" name="pupil_id" value="<?= $pupil_id ?>">
                    <input type="hidden" name="sy_id" value="<?= $sy_id ?>">
                    <button class="export-btn" type="submit">Export SF10</button>
                </form>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                <tr>
                    <th>Subjects</th>
                    <th>1st Quarter</th>
                    <th>2nd Quarter</th>
                    <th>3rd Quarter</th>
                    <th>4th Quarter</th>
                    <th>Final Grade</th>
                    <th>Remarks</th>
                </tr>
                </thead>
                <tbody>
                <?php 
                $general_finals = [];
                $all_grades_complete = true;
                $required_subjects = 0;
                $quarters = ['Q1', 'Q2', 'Q3', 'Q4'];

                foreach ($top_subjects as $sub): 
                    $sid = $sub['subject_id'];
                    $sub_name = strtolower($sub['subject_name']); 
                    $has_comp = isset($components[$sid]);

                    // Skip based on grade level rules
                    if (($grade_level == 1 || $grade_level == 2) && $sub_name == "science") continue;
                    if (($grade_level >= 4 && $grade_level <= 6) && $sub_name == "mother tongue") continue;
                    if (($grade_level <= 3) && $sub_name == "edukasyong pantahanan at pangkabuhayan / tle") continue;

                    $required_subjects++;
                    $q_grades = ['Q1' => '', 'Q2' => '', 'Q3' => '', 'Q4' => ''];
                    $final = '';
                    $rem = '';
                    $subject_grades_complete = true;

                    if ($has_comp) {
                        $q_sums = ['Q1' => 0, 'Q2' => 0, 'Q3' => 0, 'Q4' => 0];
                        $q_counts = ['Q1' => 0, 'Q2' => 0, 'Q3' => 0, 'Q4' => 0];
                        foreach ($components[$sid] as $comp) {
                            foreach ($quarters as $q) {
                                if (isset($grades[$comp['subject_id']][$q])) {
                                    $q_sums[$q] += $grades[$comp['subject_id']][$q];
                                    $q_counts[$q]++;
                                }
                            }
                        }
                        foreach ($quarters as $q) {
                            if ($q_counts[$q] == count($components[$sid])) {
                                $q_grades[$q] = round($q_sums[$q] / $q_counts[$q]);
                            } else {
                                $q_grades[$q] = '';
                                $subject_grades_complete = false;
                                $all_grades_complete = false;
                            }
                        }
                        if ($subject_grades_complete) {
                            $valid_grades = array_filter($q_grades, function($grade) { return $grade !== ''; });
                            if (count($valid_grades) == count($quarters)) {
                                $final = round(array_sum($valid_grades) / count($valid_grades));
                                $rem = $final >= 75 ? 'Passed' : 'Failed';
                                $general_finals[] = $final;
                            }
                        }
                    } else {
                        foreach ($quarters as $q) {
                            $q_grades[$q] = $grades[$sid][$q] ?? '';
                            if ($q_grades[$q] === '') {
                                $subject_grades_complete = false;
                                $all_grades_complete = false;
                            }
                        }
                        $valid_grades = array_filter($q_grades, function($grade) { return $grade !== ''; });
                        if (count($valid_grades) == count($quarters)) {
                            $final = round(array_sum($valid_grades) / count($valid_grades));
                            $rem = $final >= 75 ? 'Passed' : 'Failed';
                            $general_finals[] = $final;
                        }
                    }
                ?>
                    <tr>
                        <td><?= htmlspecialchars($sub['subject_name']) ?></td>
                        <?php foreach ($quarters as $q): ?>
                            <td>
                                <?php if ($q_grades[$q] !== ''): ?>
                                    <span class="grade-box"><?= intval($q_grades[$q]) ?></span>
                                <?php else: ?>
                                    <span class="grade-box empty"></span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        <td>
                            <?php if ($subject_grades_complete && $final !== ''): ?>
                                <span class="grade-box"><?= intval($final) ?></span>
                            <?php else: ?>
                                <span class="grade-box empty"></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($subject_grades_complete && $rem == "Passed"): ?>
                                <span class="promoted">Passed</span>
                            <?php elseif ($subject_grades_complete && $rem == "Failed"): ?>
                                <span class="retained">Failed</span>
                            <?php else: ?>
                                <span class="none">---</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($has_comp): ?>
                        <?php foreach ($components[$sid] as $comp): 
                            $cid = $comp['subject_id'];
                            $cq_grades = [
                                'Q1' => $grades[$cid]['Q1'] ?? '',
                                'Q2' => $grades[$cid]['Q2'] ?? '',
                                'Q3' => $grades[$cid]['Q3'] ?? '',
                                'Q4' => $grades[$cid]['Q4'] ?? ''
                            ];
                            $cgs = array_filter($cq_grades);
                            $comp_grades_complete = count($cgs) == count($quarters);
                            $cfinal = $comp_grades_complete ? round(array_sum($cgs) / count($cgs)) : '';
                            $crem = $comp_grades_complete ? ($cfinal >= 75 ? 'Passed' : 'Failed') : '';
                        ?>
                            <tr>
                                <td style="padding-left: 20px;"><?= htmlspecialchars($comp['subject_name']) ?></td>
                                <?php foreach (['Q1', 'Q2', 'Q3', 'Q4'] as $q): ?>
                                    <td>
                                        <?php if ($cq_grades[$q] !== ''): ?>
                                            <span class="grade-box"><?= intval($cq_grades[$q]) ?></span>
                                        <?php else: ?>
                                            <span class="grade-box empty"></span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td>
                                    <?php if ($comp_grades_complete && $cfinal !== ''): ?>
                                        <span class="grade-box"><?= intval($cfinal) ?></span>
                                    <?php else: ?>
                                        <span class="grade-box empty"></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($comp_grades_complete && $crem == "Passed"): ?>
                                        <span class="promoted">Passed</span>
                                    <?php elseif ($comp_grades_complete && $crem == "Failed"): ?>
                                        <span class="retained">Failed</span>
                                    <?php else: ?>
                                        <span class="none">---</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php 
                $general_avg = '';
                $overall_rem = '';
                if ($all_grades_complete && count($general_finals) == $required_subjects && count($general_finals) > 0) {
                    $general_avg = round(array_sum($general_finals) / count($general_finals));
                    $num_fails = count(array_filter($general_finals, fn($final) => $final < 75));
                    if ($num_fails >= 3) {
                        $overall_rem = 'RETAINED';
                    } elseif ($num_fails >= 1) {
                        $overall_rem = 'CONDITIONALLY PROMOTED';
                    } else {
                        if ($general_avg >= 98) {
                            $overall_rem = 'PROMOTED WITH HIGHEST HONORS';
                        } elseif ($general_avg >= 95) {
                            $overall_rem = 'PROMOTED WITH HIGH HONORS';
                        } elseif ($general_avg >= 90) {
                            $overall_rem = 'PROMOTED WITH HONORS';
                        } else {
                            $overall_rem = 'PROMOTED';
                        }
                    }
                } else {
                    $overall_rem = 'INCOMPLETE';
                }
                ?>
                <tr>
                    <td>General Average</td>
                    <td colspan="4"></td>
                    <td>
                        <?php if ($general_avg !== ''): ?>
                            <span class="grade-box"><?= intval($general_avg) ?></span>
                        <?php else: ?>
                            <span class="grade-box empty"></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (str_contains($overall_rem, "HONORS")): ?>
                            <span class="honors"><?= $overall_rem ?></span>
                        <?php elseif ($overall_rem == "PROMOTED"): ?>
                            <span class="promoted">PROMOTED</span>
                        <?php elseif ($overall_rem == "RETAINED"): ?>
                            <span class="retained">RETAINED</span>
                        <?php elseif ($overall_rem == "CONDITIONALLY PROMOTED"): ?>
                            <span class="retained">CONDITIONALLY PROMOTED</span>
                        <?php elseif ($overall_rem == "INCOMPLETE"): ?>
                            <span class="incomplete">INCOMPLETE</span>
                        <?php endif; ?>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
document.getElementById('exportForm').addEventListener('submit', function() {
    const exportBtn = this.querySelector('.export-btn');
    exportBtn.disabled = true;
    exportBtn.classList.add('loading');
    exportBtn.innerHTML = 'Loading... <span class="spinner"></span>';

    setTimeout(() => {
        exportBtn.disabled = false;
        exportBtn.classList.remove('loading');
        exportBtn.innerHTML = 'Export SF10';
    }, 1000); 
});
</script>
</body>
</html>