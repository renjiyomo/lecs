<?php
include 'lecs_db.php';
session_start();

// Restrict access to admins only
if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 'a') {
    header("Location: /lecs/Landing/Login/login.php");
    exit;
}

$teacher_id = intval($_SESSION['teacher_id']);

// Custom rounding function: round only if decimal part is >= 0.5 (DepEd policy)
function customRound($number) {
    $floor = floor($number);
    $decimal = $number - $floor;
    return $decimal >= 0.5 ? ceil($number) : $floor;
}

$current_sy = isset($_GET['sy_id']) ? intval($_GET['sy_id']) : null;
$current_section = isset($_GET['section_id']) ? intval($_GET['section_id']) : null;
$current_grade_level = isset($_GET['grade_level_id']) ? intval($_GET['grade_level_id']) : null;
$current_quarter = $_GET['quarter'] ?? 'all';

$sy_res = $conn->query("SELECT * FROM school_years ORDER BY start_date DESC");
$school_years = $sy_res->fetch_all(MYSQLI_ASSOC);

if ($current_sy === null) {
    // Try to find current sy
    $stmt = $conn->prepare("SELECT sy_id FROM school_years WHERE CURDATE() BETWEEN start_date AND end_date ORDER BY start_date DESC LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $current_sy = $row['sy_id'];
    } else {
        // If no current, get the latest by start_date
        $stmt = $conn->prepare("SELECT sy_id FROM school_years ORDER BY start_date DESC LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        $current_sy = $result->fetch_assoc()['sy_id'] ?? null;
    }
    $stmt->close();
}

// Handle conflict between section and grade level
if ($current_section) {
    $stmt = $conn->prepare("SELECT grade_level_id FROM sections WHERE section_id = ?");
    $stmt->bind_param("i", $current_section);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $section_grade = $result['grade_level_id'] ?? 0;
    $stmt->close();

    if ($current_grade_level && $current_grade_level != $section_grade) {
        $current_section = null;
    } else {
        $current_grade_level = $section_grade;
    }
}

$sql = "SELECT p.pupil_id, p.first_name, p.last_name, p.middle_name, p.sy_id, s.grade_level_id
        FROM pupils p
        JOIN sections s ON p.section_id = s.section_id
        WHERE p.sy_id = ?";
$params = [$current_sy];
$types = "i";
if ($current_section) {
    $sql .= " AND p.section_id = ?";
    $params[] = $current_section;
    $types .= "i";
}
if ($current_grade_level && !$current_section) {
    $sql .= " AND s.grade_level_id = ?";
    $params[] = $current_grade_level;
    $types .= "i";
}
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$pupils = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$all_subjects = [];
$subject_lookup = []; 
$components = []; 
$pupil_subjects = [];

foreach ($pupils as $pupil) {
    $pupil_sy_id = $pupil['sy_id'];
    $grade_level_id = $pupil['grade_level_id'];
    
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
$ids = array_column($pupils, 'pupil_id');
if (count($ids) > 0) {
    $id_placeholders = implode(",", array_fill(0, count($ids), "?"));
    $gsql = "SELECT pupil_id, subject_id, quarter, grade, sy_id 
             FROM grades WHERE pupil_id IN ($id_placeholders) AND sy_id = ?";
    $params = array_merge($ids, [$current_sy]);
    $types = str_repeat("i", count($ids)) . "i";
    if ($current_quarter !== 'all') {
        $gsql .= " AND quarter = ?";
        $params[] = $current_quarter;
        $types .= "s";
    }
    $stmt = $conn->prepare($gsql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $gres = $stmt->get_result();
    while ($g = $gres->fetch_assoc()) {
        $grades_map[$g['pupil_id']][$g['subject_id']][$g['quarter']] = $g['grade'];
    }
}

// Precompute averages and ranks
$pupil_averages = [];
$pupil_rounded_averages = [];
$display_subjects = $subject_lookup[$current_sy] ?? [];
ksort($display_subjects);
foreach ($pupils as $p) {
    $pupil_grades = [];
    $all_empty = true;
    $has_incomplete = false;
    $required_subjects = 0;
    foreach ($display_subjects as $name => $sub) {
        $isApplicable = isset($sub['grade_to_id'][$p['grade_level_id']]) && isset($pupil_subjects[$p['pupil_id']][$sub['grade_to_id'][$p['grade_level_id']]]);
        if ($isApplicable) {
            $subject_id = $sub['grade_to_id'][$p['grade_level_id']];
            $start_q = $sub['start_quarter'][$p['grade_level_id']] ?? "Q1";
            $start_num = $quarters_order[$start_q];
            $required_quarters = array_slice(array_keys($quarters_order), $start_num - 1);

            if (isset($components[$subject_id])) {
                $comp_finals = [];
                $all_comp_present = true;
                foreach ($components[$subject_id] as $comp) {
                    $comp_id = $comp['subject_id'];
                    $comp_quarters = $grades_map[$p['pupil_id']][$comp_id] ?? [];
                    $comp_start_q = $comp['start_quarter'] ?? "Q1";
                    $comp_start_num = $quarters_order[$comp_start_q];
                    $comp_required_quarters = array_slice(array_keys($quarters_order), $comp_start_num - 1);

                    if ($current_quarter !== 'all') {
                        if ($quarters_order[$current_quarter] >= $comp_start_num) {
                            $grade = $comp_quarters[$current_quarter] ?? null;
                            if ($grade !== null) {
                                $comp_finals[] = customRound($grade);
                                $all_empty = false;
                            } else {
                                $all_comp_present = false;
                            }
                        }
                    } else {
                        $filtered = array_filter($comp_quarters, fn($g, $q) => in_array($q, $comp_required_quarters), ARRAY_FILTER_USE_BOTH);
                        if (count($filtered) == count($comp_required_quarters)) {
                            $comp_finals[] = customRound(array_sum($filtered) / count($filtered));
                            $all_empty = false;
                        } else {
                            $all_comp_present = false;
                            $has_incomplete = true;
                        }
                    }
                }
                if ($all_comp_present) {
                    $val = customRound(array_sum($comp_finals) / count($comp_finals));
                    $pupil_grades[] = $val;
                    $required_subjects++;
                } else {
                    $has_incomplete = true;
                }
            } else {
                $quarters = $grades_map[$p['pupil_id']][$subject_id] ?? [];
                if ($current_quarter !== 'all') {
                    if ($quarters_order[$current_quarter] >= $start_num) {
                        $grade = $quarters[$current_quarter] ?? null;
                        if ($grade !== null) {
                            $val = customRound($grade);
                            $pupil_grades[] = $val;
                            $all_empty = false;
                            $required_subjects++;
                        } else {
                            $has_incomplete = true;
                        }
                    }
                } else {
                    $filtered = array_filter($quarters, fn($g, $q) => in_array($q, $required_quarters), ARRAY_FILTER_USE_BOTH);
                    if (count($filtered) == count($required_quarters)) {
                        $val = customRound(array_sum($filtered) / count($filtered));
                        $pupil_grades[] = $val;
                        $all_empty = false;
                        $required_subjects++;
                    } else {
                        $has_incomplete = true;
                    }
                }
            }
        }
    }
    if (!$has_incomplete && count($pupil_grades) > 0 && count($pupil_grades) == $required_subjects) {
        $avg = array_sum($pupil_grades) / count($pupil_grades);
        $pupil_averages[$p['pupil_id']] = $avg;
        $pupil_rounded_averages[$p['pupil_id']] = customRound($avg);
    } else {
        $pupil_averages[$p['pupil_id']] = -1;
        $pupil_rounded_averages[$p['pupil_id']] = -1;
    }
}

// Assign ranks (only for complete grades)
$complete_ids = array_keys(array_filter($pupil_averages, fn($avg) => $avg >= 0));
usort($complete_ids, function($a_id, $b_id) use ($pupil_rounded_averages, $pupil_averages) {
    $a_rounded = $pupil_rounded_averages[$a_id];
    $b_rounded = $pupil_rounded_averages[$b_id];
    $comp = $b_rounded <=> $a_rounded;
    if ($comp !== 0) return $comp;
    return $pupil_averages[$b_id] <=> $pupil_averages[$a_id];
});

$ranks = [];
$rank = 1;
$prev_rounded = null;
foreach ($complete_ids as $id) {
    $rounded = $pupil_rounded_averages[$id];
    if ($prev_rounded !== null && $rounded < $prev_rounded) {
        $rank++;
    }
    $ranks[$id] = $rank;
    $prev_rounded = $rounded;
}

// Add avg, rounded_avg and rank to pupils and sort by rounded_avg desc, then avg desc
foreach ($pupils as &$pupil) {
    $pid = $pupil['pupil_id'];
    $pupil['avg'] = $pupil_averages[$pid] ?? -1;
    $pupil['rounded_avg'] = $pupil_rounded_averages[$pid] ?? -1;
    $pupil['rank'] = $ranks[$pid] ?? '-';
}
usort($pupils, function($a, $b) {
    if ($a['rounded_avg'] == -1 && $b['rounded_avg'] == -1) return 0;
    if ($a['rounded_avg'] == -1) return 1;
    if ($b['rounded_avg'] == -1) return -1;
    $comp = $b['rounded_avg'] <=> $a['rounded_avg'];
    if ($comp !== 0) return $comp;
    return $b['avg'] <=> $a['avg'];
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Grades of Pupils | LECS Online Student Grading System</title>
    <link rel="icon" href="images/lecs-logo no bg.png" type="image/x-icon">
    <link rel="stylesheet" href="css/adminGrades.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <?php include 'theme-script.php'; ?>
</head>
<body>
<div class="container">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <h1>Grades of Pupils</h1>

        <form method="GET" class="top-bar">
            <label for="schoolYear">School Year:</label>
            <select id="schoolYear" name="sy_id" onchange="this.form.submit()">
                <?php foreach ($school_years as $sy): ?>
                    <option value="<?= htmlspecialchars($sy['sy_id']) ?>" <?= $sy['sy_id'] == $current_sy ? "selected" : "" ?>>
                        <?= htmlspecialchars($sy['school_year']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label for="grade_level">Grade Level:</label>
            <select id="grade_level" name="grade_level_id" onchange="this.form.submit()">
                <option value="">All Grades</option>
                <?php
                $grade_query = "
                    SELECT DISTINCT g.grade_level_id, g.level_name 
                    FROM grade_levels g
                    JOIN sections s ON g.grade_level_id = s.grade_level_id
                    JOIN pupils p ON s.section_id = p.section_id
                    WHERE p.sy_id = ?
                    ORDER BY g.level_name
                ";
                $grade_stmt = $conn->prepare($grade_query);
                $grade_stmt->bind_param("i", $current_sy);
                $grade_stmt->execute();
                $grade_res = $grade_stmt->get_result();
                while ($grade = $grade_res->fetch_assoc()) {
                    $selected = ($current_grade_level == $grade['grade_level_id']) ? "selected" : "";
                    echo "<option value='{$grade['grade_level_id']}' $selected>Grade {$grade['level_name']}</option>";
                }
                ?>
            </select>
            <label for="section">Class:</label>
            <select id="section" name="section_id" onchange="this.form.submit()">
                <option value="">All Classes</option>
                <?php
                $section_query = "
                    SELECT DISTINCT s.section_id, s.section_name, g.level_name 
                    FROM sections s
                    JOIN grade_levels g ON s.grade_level_id = g.grade_level_id
                    JOIN pupils p ON p.section_id = s.section_id
                    WHERE p.sy_id = ?
                ";
                $section_params = [$current_sy];
                $section_types = "i";
                if ($current_grade_level) {
                    $section_query .= " AND g.grade_level_id = ?";
                    $section_params[] = $current_grade_level;
                    $section_types .= "i";
                }
                $section_query .= " ORDER BY g.level_name, s.section_name";
                $section_stmt = $conn->prepare($section_query);
                $section_stmt->bind_param($section_types, ...$section_params);
                $section_stmt->execute();
                $sec_res = $section_stmt->get_result();
                while ($sec = $sec_res->fetch_assoc()) {
                    $selected = ($current_section == $sec['section_id']) ? "selected" : "";
                    echo "<option value='{$sec['section_id']}' $selected>Grade {$sec['level_name']} - {$sec['section_name']}</option>";
                }
                ?>
            </select>
            <label for="quarter">Quarter:</label>
            <select id="quarter" name="quarter" onchange="this.form.submit()">
                <option value="all" <?= $current_quarter === 'all' ? "selected" : "" ?>>All</option>
                <option value="Q1" <?= $current_quarter === 'Q1' ? "selected" : "" ?>>Q1</option>
                <option value="Q2" <?= $current_quarter === 'Q2' ? "selected" : "" ?>>Q2</option>
                <option value="Q3" <?= $current_quarter === 'Q3' ? "selected" : "" ?>>Q3</option>
                <option value="Q4" <?= $current_quarter === 'Q4' ? "selected" : "" ?>>Q4</option>
            </select>
        </form>

        <div class="table-container">
            <table>
                <thead>
                <tr>
                    <th>Rank</th>
                    <th>NAME</th>
                    <?php foreach ($display_subjects as $name => $sub): ?>
                            <?php
                            if ($name === "Edukasyong Pantahanan at Pangkabuhayan / TLE") {
                                $display_name = "EPP/TLE";
                            } else {
                                $words = explode(' ', $name);
                                $display_name = (count($words) >= 2) ? strtoupper(implode('', array_map(fn($word) => $word[0], $words))) : htmlspecialchars($name);
                            }
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
                        <td><?= $p['rank'] ?></td>
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
                                            $comp_required_quarters = array_slice(array_keys($quarters_order), $comp_start_num - 1);
                                            $filtered = array_filter($comp_quarters, fn($g, $q) => in_array($q, $comp_required_quarters), ARRAY_FILTER_USE_BOTH);
                                            if (count($filtered) == count($comp_required_quarters)) {
                                                $comp_finals[] = customRound(array_sum($filtered) / count($filtered)); // Round component average
                                                $all_empty = false;
                                            } else {
                                                $all_comp_present = false;
                                                $subject_grades_complete = false;
                                                $has_incomplete = true;
                                                $all_grades_complete = false;
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
                                        $filtered = array_filter($quarters, fn($g, $q) => in_array($q, $required_quarters), ARRAY_FILTER_USE_BOTH);
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
                                        $avg = array_sum($pupil_grades) / count($pupil_grades);
                                        $rounded_avg = customRound($avg);
                                        if ($num_fails >= 3) {
                                            $remark = "<span class='retained'>RETAINED</span>";
                                        } elseif ($num_fails >= 1) {
                                            $remark = "<span class='conditionally-promoted'>CONDITIONALLY PROMOTED</span>";
                                        } else {
                                            if ($rounded_avg >= 98) {
                                                $remark = "<span class='highest-honors'>PROMOTED WITH HIGHEST HONORS</span>";
                                            } elseif ($rounded_avg >= 95) {
                                                $remark = "<span class='high-honors'>PROMOTED WITH HIGH HONORS</span>";
                                            } elseif ($rounded_avg >= 90) {
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
                                        $rounded_avg = customRound($avg);
                                        if ($rounded_avg >= 98) {
                                            $remark = "<span class='highest-honors'>With Highest Honors</span>";
                                        } elseif ($rounded_avg >= 95) {
                                            $remark = "<span class='high-honors'>With High Honors</span>";
                                        } elseif ($rounded_avg >= 90) {
                                            $remark = "<span class='honors'>With Honors</span>";
                                        } else {
                                            $remark = "NONE";
                                        }
                                    }
                                }
                            }
                            echo $remark;
                            ?>
                        </td>
                        <td>
                            <a href="adminViewGrades.php?pupil_id=<?= htmlspecialchars($p['pupil_id']) ?>&sy_id=<?= htmlspecialchars($current_sy) ?>&quarter=<?= htmlspecialchars($current_quarter) ?>" class="edit-btn">View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>