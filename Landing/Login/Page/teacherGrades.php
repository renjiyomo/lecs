<?php
include 'lecs_db.php';
session_start();

// Restrict access to teachers only
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

// Custom rounding function: round only if decimal part is >= 0.5 (DepEd policy)
function customRound($number) {
    $floor = floor($number);
    $decimal = $number - $floor;
    return $decimal >= 0.5 ? ceil($number) : $floor;
}

// Get school years where the teacher has pupils
$sy_sql = "SELECT DISTINCT sy.sy_id, sy.school_year, sy.start_date, sy.end_date
           FROM school_years sy
           JOIN sections s ON sy.sy_id = s.sy_id
           JOIN pupils p ON s.section_id = p.section_id
           WHERE p.teacher_id = ? AND s.teacher_id = ?
           ORDER BY sy.start_date DESC";
$sy_stmt = $conn->prepare($sy_sql);
$sy_stmt->bind_param("ii", $teacher_id, $teacher_id);
$sy_stmt->execute();
$school_years = $sy_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Check if there are any school years available
if (empty($school_years)) {
    $no_school_years_message = "No school years found for this teacher.";
    $current_sy = 0; // Set to invalid value to prevent further queries
} else {
    // Set default to most recent school year if not provided
    $current_sy = $_GET['sy_id'] ?? null;
    if ($current_sy === null) {
        foreach ($school_years as $sy) {
            $start = new DateTime($sy['start_date']);
            $end = new DateTime($sy['end_date']);
            $now = new DateTime();
            if ($now >= $start && $now <= $end) {
                $current_sy = $sy['sy_id'];
                break;
            }
        }
        if ($current_sy === null) {
            $current_sy = $school_years[0]['sy_id'] ?? null;
        }
    }
}

// Set default quarter
$current_quarter = $_GET['quarter'] ?? 'all'; // Default to 'all' for quarter

// Quarter suffix for display
$quarter_num = substr($current_quarter, 1);
$suffix = ($quarter_num == '1') ? 'st' : (($quarter_num == '2') ? 'nd' : (($quarter_num == '3') ? 'rd' : 'th'));
$quarter_display = $current_quarter !== 'all' ? $quarter_num . $suffix : '';

// Fetch pupils of this teacher for the selected SY (only if a valid SY exists)
$pupils = [];
if (!empty($school_years) && $current_sy > 0) {
    $sql = "SELECT p.pupil_id, p.first_name, p.last_name, p.middle_name, p.sy_id, s.grade_level_id, s.section_name, 
                   CONCAT(t.first_name, ' ', COALESCE(t.middle_name, ''), ' ', t.last_name) AS teacher_name
            FROM pupils p
            JOIN sections s ON p.section_id = s.section_id
            JOIN teachers t ON p.teacher_id = t.teacher_id
            WHERE p.teacher_id = ? AND p.sy_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $teacher_id, $current_sy);
    $stmt->execute();
    $pupils = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Fetch subjects for the selected school year
$all_subjects = [];
$subject_lookup = []; 
$components = []; 
$pupil_subjects = []; // Store subjects per pupil's sy_id and grade_level_id

foreach ($pupils as $pupil) {
    $pupil_sy_id = $pupil['sy_id'];
    $grade_level_id = $pupil['grade_level_id'];
    
    // Fetch subjects for this pupil's sy_id and grade_level_id
    $sub_sql = "SELECT * FROM subjects WHERE grade_level_id = ? AND sy_id = ? ORDER BY subject_name ASC";
    $sub_stmt = $conn->prepare($sub_sql);
    $sub_stmt->bind_param("ii", $grade_level_id, $pupil_sy_id);
    $sub_stmt->execute();
    $sub_res = $sub_stmt->get_result();
    
    while ($sub = $sub_res->fetch_assoc()) {
        $all_subjects[$sub['subject_id']] = $sub;
        if ($sub['parent_subject_id']) {
            $components[$sub['parent_subject_id']][] = $sub;
        } else {
            $name = $sub['subject_name'];
            if (!isset($subject_lookup[$pupil_sy_id][$name])) {
                $subject_lookup[$pupil_sy_id][$name] = [
                    'grade_to_id' => [],
                    'grade_levels' => [],
                    'start_quarter' => []
                ];
            }
            $subject_lookup[$pupil_sy_id][$name]['grade_to_id'][$sub['grade_level_id']] = $sub['subject_id'];
            $subject_lookup[$pupil_sy_id][$name]['grade_levels'][] = $sub['grade_level_id'];
            $subject_lookup[$pupil_sy_id][$name]['start_quarter'][$sub['grade_level_id']] = $sub['start_quarter'];
        }
        $pupil_subjects[$pupil['pupil_id']][$sub['subject_id']] = $sub;
    }
}

$quarters_order = ["Q1" => 1, "Q2" => 2, "Q3" => 3, "Q4" => 4];

$grades_map = [];
if (!empty($pupils)) {
    $ids = array_column($pupils, 'pupil_id');
    $id_list = implode(",", array_map('intval', $ids)); // Sanitize IDs
    $gsql = "SELECT pupil_id, subject_id, quarter, grade, sy_id 
             FROM grades WHERE pupil_id IN ($id_list) AND sy_id = ?";
    $stmt = $conn->prepare($gsql);
    $stmt->bind_param("i", $current_sy);
    if ($current_quarter !== 'all') {
        $gsql .= " AND quarter = ?";
        $stmt = $conn->prepare($gsql);
        $stmt->bind_param("is", $current_sy, $current_quarter);
    }
    $stmt->execute();
    $gres = $stmt->get_result();
    while ($g = $gres->fetch_assoc()) {
        $grades_map[$g['pupil_id']][$g['subject_id']][$g['quarter']] = $g['grade'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Grades of Pupils</title>
    <link rel="icon" href="images/lecs-logo no bg.png" type="image/x-icon">
    <link rel="stylesheet" href="css/adminGrades.css">
    <link rel="stylesheet" href="css/sidebar.css">
</head>
<body class="light">
<div class="container">
    <?php include 'teacherSidebar.php'; ?>

    <div class="main-content">
        <h1>Grades of Pupils</h1>

        <?php if (isset($no_school_years_message)): ?>
            <div class="error-message"><?= htmlspecialchars($no_school_years_message) ?></div>
        <?php else: ?>
            <div class="top-bar">
                <label for="schoolYear">School Year:</label>
                <select id="schoolYear" name="sy_id">
                    <?php foreach ($school_years as $sy): ?>
                        <option value="<?= htmlspecialchars($sy['sy_id']) ?>" <?= $sy['sy_id'] == $current_sy ? "selected" : "" ?>>
                            <?= htmlspecialchars($sy['school_year']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label for="quarter">Quarter:</label>
                <select id="quarter" name="quarter">
                    <option value="all" <?= $current_quarter === 'all' ? "selected" : "" ?>>All</option>
                    <option value="Q1" <?= $current_quarter === 'Q1' ? "selected" : "" ?>>Q1</option>
                    <option value="Q2" <?= $current_quarter === 'Q2' ? "selected" : "" ?>>Q2</option>
                    <option value="Q3" <?= $current_quarter === 'Q3' ? "selected" : "" ?>>Q3</option>
                    <option value="Q4" <?= $current_quarter === 'Q4' ? "selected" : "" ?>>Q4</option>
                </select>
                <a class="sf9-btn" href="generate_sf9.php?sy_id=<?= htmlspecialchars($current_sy) ?>" class="export-btn">Generate SF9</a>

                <div class="export-dropdown">
                    <button type="button" class="export-btn" onclick="toggleOverallDropdown()">Certificates of Recognition ▼</button>
                    <div id="overallOptions" class="dropdown-content">
                        <a href="#" onclick="openOverallDateModal('docx')">Generate as DOCX</a>
                        <a href="#" onclick="openOverallDateModal('pdf')">Generate as PDF</a>
                    </div>
                </div>

                <?php if ($current_quarter !== 'all'): ?>
                    <div class="export-dropdown">
                        <button type="button" class="export-btn" onclick="toggleQuarterlyDropdown()">Certificate for <?= $quarter_display ?> Quarter ▼</button>
                        <div id="quarterlyOptions" class="dropdown-content">
                            <a href="#" onclick="openQuarterlyDateModal('docx')">Generate as DOCX</a>
                            <a href="#" onclick="openQuarterlyDateModal('pdf')">Generate as PDF</a>
                        </div>
                    </div>
                <?php else: ?>
                    <button type="button" class="exports-btn" disabled>Select a Quarter</button>
                <?php endif; ?>
            </div>

            <div id="dateModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeDateModal()">&times;</span>
                    <h2>Select Certificate Issuance Date</h2>
                    <form action="generate_certificates.php" method="post">
                        <input type="hidden" name="sy_id" value="<?= htmlspecialchars($current_sy) ?>">
                        <input type="hidden" name="format" id="overall_format" value="docx">
                        <label for="issue_date">Issuance Date:</label>
                        <input class="given-date" type="date" id="issue_date" name="issue_date" value="<?= date('Y-m-d') ?>" required>
                        <button class="generate-certi" type="submit">Generate</button>
                    </form>
                </div>
            </div>

            <div id="quarterDateModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeQuarterDateModal()">&times;</span>
                    <h2>Select Certificate Issuance Date for Quarter</h2>
                    <form action="generate_quarter_certificates.php" method="post">
                        <input type="hidden" name="sy_id" value="<?= htmlspecialchars($current_sy) ?>">
                        <input type="hidden" name="quarter" value="<?= htmlspecialchars($current_quarter) ?>">
                        <input type="hidden" name="format" id="quarter_format" value="docx">
                        <label for="issue_date_quarter">Issuance Date:</label>
                        <input class="given-date" type="date" id="issue_date_quarter" name="issue_date" value="<?= date('Y-m-d') ?>" required>
                        <button class="generate-certi" type="submit">Generate</button>
                    </form>
                </div>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                    <tr>
                        <th>NAME</th>
                        <?php
                        // Display subjects for the current school year
                        $display_subjects = $subject_lookup[$current_sy] ?? [];
                        ksort($display_subjects); // Sort subjects alphabetically
                        ?>
                        <?php foreach ($display_subjects as $name => $sub): ?>
                            <?php
                            $words = explode(' ', $name);
                            $display_name = (count($words) >= 2) ? strtoupper(implode('', array_map(fn($word) => $word[0], $words))) : htmlspecialchars($name);
                            ?>
                            <th><?= htmlspecialchars($display_name) ?></th>
                        <?php endforeach; ?>
                        <th>Remarks</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pupils as $p): ?>
                        <?php
                        $fullname = strtoupper($p['last_name'] . ", " . $p['first_name'] . ($p['middle_name'] ? " " . $p['middle_name'] : ""));
                        $pupil_grades = []; 
                        $all_empty = true;
                        $has_incomplete = false;
                        $required_subjects = 0;
                        $all_grades_complete = true;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($fullname) ?></td>
                            <?php foreach ($display_subjects as $name => $sub): ?>
                                <?php
                                $isApplicable = isset($sub['grade_to_id'][$p['grade_level_id']]) && isset($pupil_subjects[$p['pupil_id']][$sub['grade_to_id'][$p['grade_level_id']]]);
                                $val = "";
                                if ($isApplicable) {
                                    $subject_id = $sub['grade_to_id'][$p['grade_level_id']];
                                    $start_q = $sub['start_quarter'][$p['grade_level_id']] ?? "Q1";
                                    $start_num = $quarters_order[$start_q];
                                    $required_quarters = array_slice(array_keys($quarters_order), $start_num - 1);
                                    $subject_grades_complete = true;

                                    if (isset($components[$subject_id])) {
                                        // Composite subject (MAPEH)
                                        $comp_finals = [];
                                        $all_comp_present = true;
                                        foreach ($components[$subject_id] as $comp) {
                                            $comp_id = $comp['subject_id'];
                                            $comp_quarters = $grades_map[$p['pupil_id']][$comp_id] ?? [];
                                            $comp_start_q = $comp['start_quarter'] ?? "Q1";
                                            $comp_start_num = $quarters_order[$comp_start_q];

                                            if ($current_quarter !== 'all') {
                                                if ($quarters_order[$current_quarter] >= $comp_start_num) {
                                                    if (isset($comp_quarters[$current_quarter])) {
                                                        $comp_finals[] = customRound($comp_quarters[$current_quarter]); // Round component grade
                                                        $all_empty = false;
                                                    } else {
                                                        $all_comp_present = false;
                                                    }
                                                }
                                            } else {
                                                $filtered = array_filter($comp_quarters, fn($g, $q) => ($quarters_order[$q] >= $comp_start_num), ARRAY_FILTER_USE_BOTH);
                                                if (count($filtered) == (5 - $comp_start_num)) { // 4 - start +1 ?
                                                    $comp_finals[] = customRound(array_sum($filtered) / count($filtered)); // Round component average
                                                    $all_empty = false;
                                                } else {
                                                    $all_comp_present = false;
                                                }
                                            }
                                        }
                                        if ($all_comp_present) {
                                            $val = customRound(array_sum($comp_finals) / count($comp_finals)); // Round composite subject average
                                        } else {
                                            $val = "";
                                            $has_incomplete = true;
                                            $all_grades_complete = false;
                                        }
                                    } else {
                                        $quarters = $grades_map[$p['pupil_id']][$subject_id] ?? [];
                                        if ($current_quarter !== 'all') {
                                            if ($quarters_order[$current_quarter] >= $start_num) {
                                                if (isset($quarters[$current_quarter])) {
                                                    $val = customRound($quarters[$current_quarter]); // Round single quarter grade
                                                    $all_empty = false;
                                                } else {
                                                    $has_incomplete = true;
                                                    $all_grades_complete = false;
                                                }
                                            }
                                        } else {
                                            $filtered = array_filter($quarters, fn($g, $q) => ($quarters_order[$q] >= $start_num), ARRAY_FILTER_USE_BOTH);
                                            if (count($filtered) == count($required_quarters)) {
                                                $val = customRound(array_sum($filtered) / count($filtered)); // Round subject average
                                                $all_empty = false;
                                                $required_subjects++;
                                                $pupil_grades[] = $val;
                                            } else {
                                                $subject_grades_complete = false;
                                                $has_incomplete = true;
                                                $all_grades_complete = false;
                                            }
                                        }
                                    }
                                }
                                $boxClass = $val === "" ? "grade-box empty" : "grade-box";
                                if (!$isApplicable) $boxClass = "grade-box not-applicable";
                                ?>
                                <td><div class="<?= htmlspecialchars($boxClass) ?>"><?= $val !== "" ? $val : "" ?></div></td>
                            <?php endforeach; ?>
                            <td>
                                <?php
                                $remark = "";
                                if ($current_quarter === 'all') {
                                    if ($all_empty) {
                                        $remark = "<span class='none'>None</span>";
                                    } elseif ($has_incomplete) {
                                        $remark = "<span class='incomplete'>Incomplete</span>";
                                    } else {
                                        $num_fails = 0;
                                        foreach ($pupil_grades as $grade) {
                                            if ($grade < 75) $num_fails++;
                                        }
                                        if (count($pupil_grades) > 0 && $all_grades_complete && count($pupil_grades) == $required_subjects) {
                                            $avg = customRound(array_sum($pupil_grades) / count($pupil_grades)); // Round general average
                                            if ($num_fails >= 3) {
                                                $remark = "<span class='retained'>RETAINED</span>";
                                            } elseif ($num_fails >= 1) {
                                                $remark = "<span class='conditionally-promoted'>CONDITIONALLY PROMOTED</span>";
                                            } else {
                                                if ($avg >= 98) {
                                                    $remark = "<span class='highest-honors'>PROMOTED WITH HIGHEST HONORS</span>";
                                                } elseif ($avg >= 95) {
                                                    $remark = "<span class='high-honors'>PROMOTED WITH HIGH HONORS</span>";
                                                } elseif ($avg >= 90) {
                                                    $remark = "<span class='honors'>PROMOTED WITH HONORS</span>";
                                                } else {
                                                    $remark = "<span class='promoted'>PROMOTED</span>";
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    // Quarter-specific remark
                                    $pupil_grades = [];
                                    $has_incomplete = false;
                                    $required_subjects = 0;
                                    foreach ($display_subjects as $name => $sub) {
                                        $isApplicable = isset($sub['grade_to_id'][$p['grade_level_id']]);
                                        if ($isApplicable) {
                                            $subject_id = $sub['grade_to_id'][$p['grade_level_id']];
                                            $start_q = $sub['start_quarter'][$p['grade_level_id']] ?? 'Q1';
                                            $start_num = $quarters_order[$start_q];
                                            if ($quarters_order[$current_quarter] >= $start_num) {
                                                $required_subjects++;
                                                if (isset($components[$subject_id])) {
                                                    $comp_grades = [];
                                                    $all_comp_present = true;
                                                    foreach ($components[$subject_id] as $comp) {
                                                        $comp_id = $comp['subject_id'];
                                                        $comp_start_q = $comp['start_quarter'] ?? 'Q1';
                                                        $comp_start_num = $quarters_order[$comp_start_q];
                                                        if ($quarters_order[$current_quarter] >= $comp_start_num) {
                                                            $grade = $grades_map[$p['pupil_id']][$comp_id][$current_quarter] ?? null;
                                                            if ($grade !== null) {
                                                                $comp_grades[] = customRound($grade);
                                                            } else {
                                                                $all_comp_present = false;
                                                                $has_incomplete = true;
                                                            }
                                                        }
                                                    }
                                                    if ($all_comp_present && count($comp_grades) === count($components[$subject_id])) {
                                                        $subject_grade = customRound(array_sum($comp_grades) / count($comp_grades));
                                                        $pupil_grades[] = $subject_grade;
                                                    } else {
                                                        $has_incomplete = true;
                                                    }
                                                } else {
                                                    $grade = $grades_map[$p['pupil_id']][$subject_id][$current_quarter] ?? null;
                                                    if ($grade !== null) {
                                                        $pupil_grades[] = customRound($grade);
                                                    } else {
                                                        $has_incomplete = true;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    if ($has_incomplete || $required_subjects === 0) {
                                        $remark = "<span class='incomplete'>Incomplete</span>";
                                    } else {
                                        $num_fails = 0;
                                        foreach ($pupil_grades as $g) {
                                            if ($g < 75) $num_fails++;
                                        }
                                        if ($num_fails > 0) {
                                            $remark = "<span class='below'>Needs Improvement</span>";
                                        } else {
                                            $avg = array_sum($pupil_grades) / count($pupil_grades);
                                            if ($avg >= 98) {
                                                $remark = "<span class='highest-honors'>With Highest Honors</span>";
                                            } elseif ($avg >= 95) {
                                                $remark = "<span class='high-honors'>With High Honors</span>";
                                            } elseif ($avg >= 90) {
                                                $remark = "<span class='honors'>With Honors</span>";
                                            } else {
                                                $remark = "";
                                            }
                                        }
                                    }
                                }
                                echo $remark;
                                ?>
                            </td>
                            <td>
                                <a href="edit_grades.php?pupil_id=<?= htmlspecialchars($p['pupil_id']) ?>&sy_id=<?= htmlspecialchars($current_sy) ?>&quarter=<?= htmlspecialchars($current_quarter) ?>" class="edit-btn">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<script>
    <?php if (!empty($school_years)): ?>
        document.getElementById('schoolYear').addEventListener('change', function() {
            const quarter = document.getElementById('quarter').value;
            window.location.href = "?sy_id=" + encodeURIComponent(this.value) + "&quarter=" + encodeURIComponent(quarter);
        });
        document.getElementById('quarter').addEventListener('change', function() {
            const sy_id = document.getElementById('schoolYear').value;
            window.location.href = "?sy_id=" + encodeURIComponent(sy_id) + "&quarter=" + encodeURIComponent(this.value);
        });

        function toggleOverallDropdown() {
            document.getElementById("overallOptions").classList.toggle("show");
        }

        function toggleQuarterlyDropdown() {
            document.getElementById("quarterlyOptions").classList.toggle("show");
        }

        function openOverallDateModal(format) {
            document.getElementById('overall_format').value = format;
            document.getElementById('dateModal').style.display = 'block';
            toggleOverallDropdown(); // Close dropdown after selection
        }

        function openQuarterlyDateModal(format) {
            document.getElementById('quarter_format').value = format;
            document.getElementById('quarterDateModal').style.display = 'block';
            toggleQuarterlyDropdown(); // Close dropdown after selection
        }

        function closeDateModal() {
            document.getElementById('dateModal').style.display = 'none';
        }

        function closeQuarterDateModal() {
            document.getElementById('quarterDateModal').style.display = 'none';
        }

        // Close modal if clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('dateModal');
            const quarterModal = document.getElementById('quarterDateModal');
            if (event.target == modal) {
                closeDateModal();
            } else if (event.target == quarterModal) {
                closeQuarterDateModal();
            }
        }
    <?php endif; ?>
</script>
</body>
</html>