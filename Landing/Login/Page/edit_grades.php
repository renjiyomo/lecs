<?php
include 'lecs_db.php';
session_start();

if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 't') {
    header("Location: /lecs/Landing/Login/login.php");
    exit;
}
$teacher_id = intval($_SESSION['teacher_id']);
$pupil_id = $_GET['pupil_id'] ?? 0;
$sy_id = $_GET['sy_id'] ?? 0;
if ($pupil_id == 0 || $sy_id == 0) {
    header("Location: teacherGrades.php");
    exit;
}

$p_sql = "SELECT p.*, s.grade_level_id FROM pupils p
          JOIN sections s ON p.section_id = s.section_id
          WHERE p.pupil_id = ? AND p.sy_id = ? AND p.teacher_id = ?";
$p_stmt = $conn->prepare($p_sql);
$p_stmt->bind_param("iii", $pupil_id, $sy_id, $teacher_id);
$p_stmt->execute();
$initial_pupil = $p_stmt->get_result()->fetch_assoc();
if (!$initial_pupil) {
    header("Location: teacherGrades.php");
    exit;
}
$grade_level = intval($initial_pupil['grade_level_id']);

$classmates_sql = "SELECT pupil_id, last_name, first_name, middle_name
                   FROM pupils
                   WHERE section_id = ? AND sy_id = ? AND teacher_id = ?
                   ORDER BY last_name, first_name";
$classmates_stmt = $conn->prepare($classmates_sql);
$classmates_stmt->bind_param("iii", $initial_pupil['section_id'], $sy_id, $teacher_id);
$classmates_stmt->execute();
$classmates = $classmates_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$selected_pupil_id = $pupil_id;
$selected_pupil = $initial_pupil;
if (isset($_POST['selected_pupil_id']) && $_POST['selected_pupil_id'] != $pupil_id) {
    unset($_SESSION['imported_grades']);
    $selected_pupil_id = intval($_POST['selected_pupil_id']);
    $p_sql = "SELECT p.*, s.grade_level_id FROM pupils p
              JOIN sections s ON p.section_id = s.section_id
              WHERE p.pupil_id = ? AND p.sy_id = ? AND p.teacher_id = ?";
    $p_stmt = $conn->prepare($p_sql);
    $p_stmt->bind_param("iii", $selected_pupil_id, $sy_id, $teacher_id);
    $p_stmt->execute();
    $selected_pupil = $p_stmt->get_result()->fetch_assoc();
    $grade_level = intval($selected_pupil['grade_level_id']);
    header("Location: edit_grades.php?pupil_id=$selected_pupil_id&sy_id=$sy_id");
    exit;
}
$fullname = strtoupper($selected_pupil['last_name'] . ", " . $selected_pupil['first_name'] . " " . $selected_pupil['middle_name']);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['selected_pupil_id'])) {
    foreach ($_POST['grades'] ?? [] as $sid => $qs) {
        foreach ($qs as $q => $grade) {
            $grade = trim($grade);
            if ($grade !== "") {
                if (ctype_digit($grade) && $grade >= 60 && $grade <= 100) {
                    $grade = intval($grade);
                    $stmt = $conn->prepare("INSERT INTO grades (pupil_id, subject_id, quarter, grade, sy_id)
                                            VALUES (?, ?, ?, ?, ?)
                                            ON DUPLICATE KEY UPDATE grade = ?");
                    $stmt->bind_param("iisiii", $selected_pupil_id, $sid, $q, $grade, $sy_id, $grade);
                    $stmt->execute();
                }
            } else {
                $stmt = $conn->prepare("DELETE FROM grades
                                        WHERE pupil_id = ? AND subject_id = ? AND quarter = ? AND sy_id = ?");
                $stmt->bind_param("iisi", $selected_pupil_id, $sid, $q, $sy_id);
                $stmt->execute();
            }
        }
    }

    // Recalculate grades and status to update pupil status
    $top_subjects = [];
    $components = [];
    $sub_res = $conn->query("SELECT subject_id, subject_name, start_quarter, parent_subject_id
                             FROM subjects
                             WHERE grade_level_id = {$selected_pupil['grade_level_id']}
                             AND sy_id = $sy_id
                             ORDER BY display_order, subject_name ASC");
    while ($sub = $sub_res->fetch_assoc()) {
        if ($sub['parent_subject_id']) {
            $components[$sub['parent_subject_id']][] = $sub;
        } else {
            $top_subjects[] = $sub;
        }
    }

    $grades = [];
    $g_res = $conn->query("SELECT subject_id, quarter, grade
                           FROM grades
                           WHERE pupil_id = $selected_pupil_id AND sy_id = $sy_id");
    while ($g = $g_res->fetch_assoc()) {
        $grades[$g['subject_id']][$g['quarter']] = intval($g['grade']);
    }

    $general_finals = [];
    $all_grades_complete = true;
    $required_subjects = 0;
    $quarter_map = ['Q1' => 1, 'Q2' => 2, 'Q3' => 3, 'Q4' => 4];
    $required_quarters = ['Q1', 'Q2', 'Q3', 'Q4'];
    foreach ($top_subjects as $sub) {
        $sid = $sub['subject_id'];
        $sub_name = strtolower($sub['subject_name']);
        $has_comp = isset($components[$sid]);
        $start_quarter = $sub['start_quarter'] ?? 'Q1';
        if (($grade_level == 1 || $grade_level == 2) && $sub_name == "science") continue;
        if (($grade_level >= 4 && $grade_level <= 6) && $sub_name == "mother tongue") continue;
        if (($grade_level <= 3) && $sub_name == "edukasyong pantahanan at pangkabuhayan / tle") continue;
        $required_subjects++;
        if ($has_comp && $sub_name === 'mapeh') {
            $mapeh_order = [
                'Music' => 1,
                'Arts' => 2,
                'Physical Education' => 3,
                'Health' => 4
            ];
            usort($components[$sid], function($a, $b) use ($mapeh_order) {
                $orderA = $mapeh_order[$a['subject_name']] ?? 99;
                $orderB = $mapeh_order[$b['subject_name']] ?? 99;
                return $orderA <=> $orderB;
            });
        }
        $q_grades = ['Q1' => '', 'Q2' => '', 'Q3' => '', 'Q4' => ''];
        $subject_required_quarters = array_slice($required_quarters, array_search($start_quarter, $required_quarters));
        if ($has_comp) {
            $q_sums = ['Q1' => 0, 'Q2' => 0, 'Q3' => 0, 'Q4' => 0];
            $q_counts = ['Q1' => 0, 'Q2' => 0, 'Q3' => 0, 'Q4' => 0];
            foreach ($components[$sid] as $comp) {
                $cid = $comp['subject_id'];
                $comp_start_quarter = $comp['start_quarter'] ?? 'Q1';
                $comp_required_quarters = array_slice($required_quarters, array_search($comp_start_quarter, $required_quarters));
                foreach ($comp_required_quarters as $q) {
                    if (isset($grades[$cid][$q])) {
                        $q_sums[$q] += $grades[$cid][$q];
                        $q_counts[$q]++;
                    } else {
                        $all_grades_complete = false;
                    }
                }
                foreach ($required_quarters as $q) {
                    if (!in_array($q, $comp_required_quarters) && isset($grades[$cid][$q])) {
                        $all_grades_complete = false;
                    }
                }
            }
            $final_sum = 0;
            $final_count = 0;
            foreach ($subject_required_quarters as $q) {
                if ($q_counts[$q] > 0) {
                    $q_grades[$q] = round($q_sums[$q] / $q_counts[$q]);
                    $final_sum += $q_grades[$q];
                    $final_count++;
                }
            }
            if ($final_count > 0) $general_finals[] = round($final_sum / $final_count);
            if ($final_count < count($subject_required_quarters) ||
                $q_counts[$subject_required_quarters[0]] < count($components[$sid])) {
                $all_grades_complete = false;
            }
        } else {
            foreach ($subject_required_quarters as $q) {
                $q_grades[$q] = isset($grades[$sid][$q]) ? intval($grades[$sid][$q]) : '';
                if ($q_grades[$q] === '') {
                    $all_grades_complete = false;
                }
            }
            foreach ($required_quarters as $q) {
                if (!in_array($q, $subject_required_quarters) && isset($grades[$sid][$q])) {
                    $all_grades_complete = false;
                }
            }
            $gs = array_filter($q_grades);
            if (count($gs) > 0) $general_finals[] = round(array_sum($gs) / count($gs));
        }
    }
    $num_fails = 0;
    $overall_rem = '';
    if ($all_grades_complete && count($general_finals) > 0 && count($general_finals) == $required_subjects) {
        $general_avg = round(array_sum($general_finals) / count($general_finals));
        foreach ($general_finals as $final) {
            if ($final < 75) $num_fails++;
        }
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

    // Update pupil status based on overall remarks
    $new_status = 'enrolled';
    if ($overall_rem === 'RETAINED') {
        $new_status = 'retained';
    } elseif (in_array($overall_rem, ['PROMOTED', 'CONDITIONALLY PROMOTED', 'PROMOTED WITH HONORS', 'PROMOTED WITH HIGH HONORS', 'PROMOTED WITH HIGHEST HONORS'])) {
        $new_status = 'promoted';
    }
    $status_stmt = $conn->prepare("UPDATE pupils SET status = ? WHERE pupil_id = ? AND sy_id = ?");
    $status_stmt->bind_param("sii", $new_status, $selected_pupil_id, $sy_id);
    $status_stmt->execute();

    unset($_SESSION['imported_grades']);
    header("Location: edit_grades.php?pupil_id=$selected_pupil_id&sy_id=$sy_id");
    exit;
}

$top_subjects = [];
$components = [];
$sub_res = $conn->query("SELECT subject_id, subject_name, start_quarter, parent_subject_id
                         FROM subjects
                         WHERE grade_level_id = {$selected_pupil['grade_level_id']}
                         AND sy_id = $sy_id
                         ORDER BY display_order, subject_name ASC");
while ($sub = $sub_res->fetch_assoc()) {
    if ($sub['parent_subject_id']) {
        $components[$sub['parent_subject_id']][] = $sub;
    } else {
        $top_subjects[] = $sub;
    }
}

$grades = [];
$g_res = $conn->query("SELECT subject_id, quarter, grade
                       FROM grades
                       WHERE pupil_id = $selected_pupil_id AND sy_id = $sy_id");
while ($g = $g_res->fetch_assoc()) {
    $grades[$g['subject_id']][$g['quarter']] = intval($g['grade']);
}

if (isset($_SESSION['imported_grades'])) {
    $grades = $_SESSION['imported_grades'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Grades of Pupil | LECS Online Student Grading System</title>
    <link rel="icon" href="images/lecs-logo no bg.png" type="image/x-icon">
    <link rel="stylesheet" href="css/editGrades.css">
    <link rel="stylesheet" href="css/sidebar.css">
</head>
<body class="light">
<div class="container">
    <?php include 'teacherSidebar.php'; ?>
    <div class="main-content">
        <?php
            if (isset($_SESSION['import_message'])) {
                echo '<div class="success-message">' . $_SESSION['import_message'] . '</div>';
                unset($_SESSION['import_message']);
            }
            if (isset($_SESSION['import_error'])) {
                echo '<div class="error-message">' . $_SESSION['import_error'] . '</div>';
                unset($_SESSION['import_error']);
            }
        ?>
        <h1>
            <span class="back-arrow" onclick="window.location.href='teacherGrades.php'">←</span>
            Grades of Pupil
        </h1>
        <div class="top-row">
            <form method="post" id="pupilSelectForm">
                <input type="hidden" name="sy_id" value="<?= $sy_id ?>">
                <select class="sy-selection" name="selected_pupil_id" onchange="this.form.submit()">
                    <?php foreach ($classmates as $classmate): ?>
                        <option value="<?= $classmate['pupil_id'] ?>" <?= $classmate['pupil_id'] == $selected_pupil_id ? "selected" : "" ?>>
                            <?= strtoupper($classmate['last_name'] . ", " . $classmate['first_name'] . " " . $classmate['middle_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <div class="button-group">
                <button class="import-btn" onclick="openModal()">Import Grades</button>
                <form id="exportForm" method="post" action="export_sf10.php" style="display: inline;">
                    <input type="hidden" name="pupil_id" value="<?= $selected_pupil_id ?>">
                    <input type="hidden" name="sy_id" value="<?= $sy_id ?>">
                    <button class="export-btn" type="submit">Export SF10</button>
                </form>
            </div>
        </div>

        <form method="post" id="gradesForm">
            <input type="hidden" name="pupil_id" value="<?= $selected_pupil_id ?>">
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
                $quarter_map = ['Q1' => 1, 'Q2' => 2, 'Q3' => 3, 'Q4' => 4];
                $required_quarters = ['Q1', 'Q2', 'Q3', 'Q4'];
                foreach ($top_subjects as $sub):
                    $sid = $sub['subject_id'];
                    $sub_name = strtolower($sub['subject_name']);
                    $has_comp = isset($components[$sid]);
                    $start_quarter = $sub['start_quarter'] ?? 'Q1';
                    if (($grade_level == 1 || $grade_level == 2) && $sub_name == "science") continue;
                    if (($grade_level >= 4 && $grade_level <= 6) && $sub_name == "mother tongue") continue;
                    if (($grade_level <= 3) && $sub_name == "edukasyong pantahanan at pangkabuhayan / tle") continue;
                    $required_subjects++;
                    if ($has_comp && $sub_name === 'mapeh') {
                        $mapeh_order = [
                            'Music' => 1,
                            'Arts' => 2,
                            'Physical Education' => 3,
                            'Health' => 4
                        ];
                        usort($components[$sid], function($a, $b) use ($mapeh_order) {
                            $orderA = $mapeh_order[$a['subject_name']] ?? 99;
                            $orderB = $mapeh_order[$b['subject_name']] ?? 99;
                            return $orderA <=> $orderB;
                        });
                    }
                    $q_grades = ['Q1' => '', 'Q2' => '', 'Q3' => '', 'Q4' => ''];
                    $final = '';
                    $rem = '';
                    $subject_required_quarters = array_slice($required_quarters, array_search($start_quarter, $required_quarters));
                    if ($has_comp) {
                        $q_sums = ['Q1' => 0, 'Q2' => 0, 'Q3' => 0, 'Q4' => 0];
                        $q_counts = ['Q1' => 0, 'Q2' => 0, 'Q3' => 0, 'Q4' => 0];
                        foreach ($components[$sid] as $comp) {
                            $cid = $comp['subject_id'];
                            $comp_start_quarter = $comp['start_quarter'] ?? 'Q1';
                            $comp_required_quarters = array_slice($required_quarters, array_search($comp_start_quarter, $required_quarters));
                            foreach ($comp_required_quarters as $q) {
                                if (isset($grades[$cid][$q])) {
                                    $q_sums[$q] += $grades[$cid][$q];
                                    $q_counts[$q]++;
                                } else {
                                    $all_grades_complete = false;
                                }
                            }
                            foreach ($required_quarters as $q) {
                                if (!in_array($q, $comp_required_quarters) && isset($grades[$cid][$q])) {
                                    $all_grades_complete = false;
                                }
                            }
                        }
                        $final_sum = 0;
                        $final_count = 0;
                        foreach ($subject_required_quarters as $q) {
                            if ($q_counts[$q] > 0) {
                                $q_grades[$q] = round($q_sums[$q] / $q_counts[$q]);
                                $final_sum += $q_grades[$q];
                                $final_count++;
                            }
                        }
                        if ($final_count > 0) $final = round($final_sum / $final_count);
                        if ($final_count < count($subject_required_quarters) ||
                            $q_counts[$subject_required_quarters[0]] < count($components[$sid])) {
                            $all_grades_complete = false;
                        }
                    } else {
                        foreach ($subject_required_quarters as $q) {
                            $q_grades[$q] = isset($grades[$sid][$q]) ? intval($grades[$sid][$q]) : '';
                            if ($q_grades[$q] === '') {
                                $all_grades_complete = false;
                            }
                        }
                        foreach ($required_quarters as $q) {
                            if (!in_array($q, $subject_required_quarters) && isset($grades[$sid][$q])) {
                                $all_grades_complete = false;
                            }
                        }
                        $gs = array_filter($q_grades);
                        if (count($gs) > 0) $final = round(array_sum($gs) / count($gs));
                    }
                    if ($final !== '') {
                        $rem = $final >= 75 ? 'Passed' : 'Failed';
                        $general_finals[] = $final;
                    }
                ?>
                    <tr>
                        <td><?= htmlspecialchars($sub['subject_name']) ?></td>
                        <?php foreach (['Q1','Q2','Q3','Q4'] as $q): ?>
                            <td>
                                <?php if (!in_array($q, $subject_required_quarters)): ?>
                                    <div class="grade-box not-applicable"><?= $q_grades[$q] ?: 'N/A' ?></div>
                                <?php else: ?>
                                    <?php if ($has_comp): ?>
                                        <?= $q_grades[$q] ? intval($q_grades[$q]) : '' ?>
                                    <?php else: ?>
                                        <input type="number" name="grades[<?= $sid ?>][<?= $q ?>]"
                                               value="<?= $q_grades[$q] ?>" min="60" max="100" step="1">
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        <td><?= $final ? intval($final) : '' ?></td>
                        <td><?= $rem ?></td>
                    </tr>
                    <?php if ($has_comp): ?>
                        <?php foreach ($components[$sid] as $comp):
                            $cid = $comp['subject_id'];
                            $comp_start_quarter = $comp['start_quarter'] ?? 'Q1';
                            $comp_required_quarters = array_slice($required_quarters, array_search($comp_start_quarter, $required_quarters));
                            $cq_grades = [
                                'Q1' => $grades[$cid]['Q1'] ?? '',
                                'Q2' => $grades[$cid]['Q2'] ?? '',
                                'Q3' => $grades[$cid]['Q3'] ?? '',
                                'Q4' => $grades[$cid]['Q4'] ?? ''
                            ];
                            $cgs = array_filter($cq_grades);
                            $cfinal = count($cgs) > 0 ? round(array_sum($cgs) / count($cgs)) : '';
                            $crem = $cfinal !== '' ? ($cfinal >= 75 ? 'Passed' : 'Failed') : '';
                        ?>
                            <tr>
                                <td style="padding-left: 20px;"><?= htmlspecialchars($comp['subject_name']) ?></td>
                                <?php foreach (['Q1','Q2','Q3','Q4'] as $q): ?>
                                    <td>
                                        <?php if (in_array($q, $comp_required_quarters)): ?>
                                            <input type="number" name="grades[<?= $cid ?>][<?= $q ?>]"
                                                   value="<?= $cq_grades[$q] ?>" min="60" max="100" step="1">
                                        <?php else: ?>
                                            <div class="grade-box not-applicable"><?= $cq_grades[$q] ?: 'N/A' ?></div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td><?= $cfinal ? intval($cfinal) : '' ?></td>
                                <td><?= $crem ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php
                $num_fails = 0;
                $general_avg = '';
                $overall_rem = '';
                if ($all_grades_complete && count($general_finals) > 0 && count($general_finals) == $required_subjects) {
                    $general_avg = round(array_sum($general_finals) / count($general_finals));
                    foreach ($general_finals as $final) {
                        if ($final < 75) $num_fails++;
                    }
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
                    <td><?= $general_avg ? intval($general_avg) : '' ?></td>
                    <td><?= $overall_rem ?></td>
                </tr>
                </tbody>
            </table>
            <div class="button-container">
                <button class="save-btn" type="submit">Save</button>
            </div>
        </form>

        <div id="importModal" class="modal">
            <div class="modal-content">
                <h2>Import Grades</h2>
                <form id="importForm" action="import_grades.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="pupil_id" value="<?= $selected_pupil_id ?>">
                    <input type="hidden" name="sy_id" value="<?= $sy_id ?>">
                    <label class="custom-file-upload">
                        <input type="file" name="file" accept=".xlsx,.xls" required>
                    </label>
                    <div>
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
    function openModal() {
        document.getElementById('importModal').style.display = 'flex';
    }
    function closeModal() {
        document.getElementById('importModal').style.display = 'none';
    }
    window.onclick = function(event) {
        const modal = document.getElementById('importModal');
        if (modal && event.target === modal) {
            closeModal();
        }
    }

    document.getElementById('importForm').addEventListener('submit', function() {
        const uploadBtn = this.querySelector('.btn-primary');
        uploadBtn.disabled = true;
        uploadBtn.classList.add('loading');
        uploadBtn.innerHTML = 'Loading... <span class="spinner"></span>';
    });

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