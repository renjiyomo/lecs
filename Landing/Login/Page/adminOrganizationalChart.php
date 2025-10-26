<?php
include 'lecs_db.php';
session_start();

if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 'a') {
    header("Location: /lecs/Landing/Login/login.php");
    exit;
}

$teacher_id = intval($_SESSION['teacher_id']);

// Fetch all school years
$allYearsQuery = $conn->query("
    SELECT school_year, sy_id, start_date, end_date
    FROM school_years
    ORDER BY start_date
");

$allYears = [];
while ($row = $allYearsQuery->fetch_assoc()) {
    $allYears[] = $row['school_year'];
}

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

$orgData = [];
foreach ($allYears as $year) {
    $syRow = $conn->query("SELECT * FROM school_years WHERE school_year = '$year'")->fetch_assoc();
    $syId = $syRow['sy_id'];
    $start = $syRow['start_date'];
    $end = $syRow['end_date'];

    $principalQuery = "
    SELECT t.teacher_id,
           CONCAT(
               t.first_name, ' ',
               CASE WHEN t.middle_name IS NOT NULL AND t.middle_name != ''
                    THEN CONCAT(LEFT(t.middle_name, 1), '. ')
                    ELSE ''
               END,
               t.last_name
           ) AS full_name,
           t.image, pos.position_name
    FROM teachers t 
    JOIN teacher_positions tp ON t.teacher_id = tp.teacher_id
    JOIN positions pos ON tp.position_id = pos.position_id
    WHERE pos.position_name LIKE 'Principal%'
    AND tp.start_date <= '$end'
    AND (tp.end_date >= '$start' OR tp.end_date IS NULL)
    ORDER BY tp.start_date DESC
";

    $principalResult = $conn->query($principalQuery);
    $principals = [];
    while ($row = $principalResult->fetch_assoc()) {
        $row['position_name'] = format_position($row['position_name']);
        $principals[] = $row;
    }

    $gradesTeachers = [];
    for ($gl = 1; $gl <= 6; $gl++) {
        $teachersQuery = "
            SELECT CONCAT(
                t.first_name, ' ',
                CASE WHEN t.middle_name IS NOT NULL AND t.middle_name != ''
                    THEN CONCAT(LEFT(t.middle_name, 1), '. ')
                    ELSE ''
                END,
                t.last_name
            ) AS full_name, t.image, t.position, s.section_name
            FROM sections s
            JOIN grade_levels gl ON s.grade_level_id = gl.grade_level_id
            JOIN teachers t ON s.teacher_id = t.teacher_id
            WHERE gl.level_name = '$gl'
            AND s.sy_id = $syId
            ORDER BY t.first_name
        ";
        $result = $conn->query($teachersQuery);
        $teachers = [];
        while ($row = $result->fetch_assoc()) {
            $row['position'] = format_position($row['position']);
            $teachers[] = $row;
        }
        $gradesTeachers[$gl] = $teachers;
    }

    $ntQuery = "
        SELECT CONCAT(t.first_name, ' ', CASE WHEN t.middle_name IS NOT NULL AND t.middle_name != ''THEN CONCAT(LEFT(t.middle_name, 1), '. ') ELSE '' END, t.last_name) AS full_name, t.image, t.position
        FROM teachers t
        WHERE (t.user_type = 'a' OR t.user_type = 'n')
        AND t.teacher_id NOT IN (
            SELECT DISTINCT tp.teacher_id
            FROM teacher_positions tp
            JOIN positions pos ON tp.position_id = pos.position_id
            WHERE pos.position_name LIKE 'Principal%'
        )
    ";
    $ntResult = $conn->query($ntQuery);
    $nonTeaching = [];
    while ($row = $ntResult->fetch_assoc()) {
        $row['position'] = format_position($row['position']);
        $nonTeaching[] = $row;
    }

    $orgData[$year] = [
        'principals' => $principals,
        'grades' => $gradesTeachers,
        'non_teaching' => $nonTeaching
    ];
}

$latestYear = !empty($allYears) ? end($allYears) : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organizational Chart | LECS Online Student Grading System</title>
    <link rel="icon" href="images/lecs-logo no bg.png" type="image/x-icon">
    <link rel="stylesheet" href="css/organizationalChart.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <?php include 'theme-script.php'; ?>
</head>
<body>
<div class="container">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <h1>Organizational Chart</h1>
        <div class="chart-container org-chart">
            <div class="filter-container">
                <select id="orgYearFilter">
                    <?php foreach ($allYears as $year): ?>
                        <option value="<?= $year; ?>" <?= $year === $latestYear ? 'selected' : ''; ?>><?= $year; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="orgChartContent"></div>
        </div>
    </div>
</div>

<script>
    const orgData = <?php echo json_encode($orgData); ?>;

    document.getElementById('orgYearFilter').addEventListener('change', function() {
        const selected = this.value;
        const content = document.getElementById('orgChartContent');
        if (!selected) {
            content.style.display = 'none';
            return;
        }
        content.style.display = 'block';
        const data = orgData[selected];
        let html = '<div class="org-chart-container">';
        html += '<div class="org-principals">';
        if (data.principals && data.principals.length > 0) {
            html += '<div class="org-principal-cards">';
            for (let p of data.principals) {
                html += '<div class="org-card">';
                html += '<img src="Uploads/teachers/' + p.image + '" alt="' + p.full_name + '">';
                html += '<p class="org-name">' + p.full_name + '</p>';
                html += '<p class="org-position">' + p.position_name + '</p>';
                html += '</div>';
            }
            html += '</div>';
        }
        html += '</div>';
        html += '<div class="org-teaching">';
        html += '<h4 class="org-teaching-title">Teaching Personnel</h4>';
        html += '<div class="org-grades">';
        for (let g = 1; g <= 6; g++) {
            html += '<div class="org-grade">';
            html += '<h4 class="org-grade-title">Grade ' + g + ' Teachers</h4>';
            let ts = data.grades[g] || [];
            for (let t of ts) {
                html += '<div class="org-card">';
                html += '<img src="Uploads/teachers/' + t.image + '" alt="' + t.full_name + '">';
                html += '<p class="org-name">' + t.full_name + '</p>';
                html += '<p class="org-position">' + t.position + '</p>';
                html += '<p class="org-section">' + (t.section_name || 'No section') + '</p>';
                html += '</div>';
            }
            if (ts.length == 0) {
                html += '<p class="org-no-teachers">No teachers</p>';
            }
            html += '</div>';
        }
        html += '</div>';
        html += '</div>';
        html += '<div class="org-non-teaching">';
        html += '<h4 class="org-non-teaching-title">Non-Teaching Personnel</h4>';
        html += '<div class="org-non-teaching-cards">';
        for (let nt of data.non_teaching) {
            html += '<div class="org-card">';
            html += '<img src="Uploads/teachers/' + nt.image + '" alt="' + nt.full_name + '">';
            html += '<p class="org-name">' + nt.full_name + '</p>';
            html += '<p class="org-position">' + nt.position + '</p>';
            html += '</div>';
        }
        html += '</div>';
        html += '</div>';
        html += '</div>';
        content.innerHTML = html;
    });

    // Trigger the organizational chart for the latest school year on page load
    window.addEventListener('DOMContentLoaded', function() {
        const orgYearFilter = document.getElementById('orgYearFilter');
        const latestYear = '<?php echo $latestYear; ?>';
        if (latestYear) {
            orgYearFilter.value = latestYear;
            const event = new Event('change');
            orgYearFilter.dispatchEvent(event);
        }
    });
</script>
</body>
</html>