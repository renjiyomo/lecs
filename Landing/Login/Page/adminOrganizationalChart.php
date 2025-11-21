<?php
include 'lecs_db.php';
session_start();

if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 'a') {
    header("Location: /lecs/Landing/Login/login.php");
    exit;
}

$teacher_id = intval($_SESSION['teacher_id']);

// Fetch all school years
$schoolYearsQuery = $conn->query("
    SELECT school_year, sy_id, start_date, end_date
    FROM school_years
    ORDER BY school_year DESC
");

$schoolYears = [];
while ($row = $schoolYearsQuery->fetch_assoc()) {
    $schoolYears[] = $row;
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
$currentDate = '2025-11-19';
$latestYear = '';

foreach ($schoolYears as $sy) {
    if ($currentDate >= $sy['start_date'] && $currentDate <= $sy['end_date']) {
        $latestYear = $sy['school_year'];
        break;
    }
}

if (!$latestYear && !empty($schoolYears)) {
    $latestYear = $schoolYears[0]['school_year'];
}

foreach ($schoolYears as $syRow) {
    $year = $syRow['school_year'];
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

    // Fetch all grade levels
    $gradeLevelsQuery = "
        SELECT grade_level_id, level_name
        FROM grade_levels
        ORDER BY CASE 
            WHEN level_name = 'Kinder' THEN 0 
            ELSE CAST(level_name AS UNSIGNED) 
        END
    ";

    $gradesTeachers = [];
    $gradeLevelsResult = $conn->query($gradeLevelsQuery);
    while ($glRow = $gradeLevelsResult->fetch_assoc()) {
        $glId = $glRow['grade_level_id'];
        $glName = $glRow['level_name'];

        $teachersQuery = "
            SELECT CONCAT(
                t.first_name, ' ',
                CASE WHEN t.middle_name IS NOT NULL AND t.middle_name != ''
                    THEN CONCAT(LEFT(t.middle_name, 1), '. ')
                    ELSE ''
                END,
                t.last_name
            ) AS full_name, t.image, pos.position_name AS position, s.section_name
            FROM sections s
            JOIN grade_levels gl ON s.grade_level_id = gl.grade_level_id
            JOIN teachers t ON s.teacher_id = t.teacher_id
            JOIN teacher_positions tp ON t.teacher_id = tp.teacher_id
            JOIN positions pos ON tp.position_id = pos.position_id
            WHERE s.grade_level_id = $glId
            AND s.sy_id = $syId
            AND tp.start_date <= '$end'
            AND (tp.end_date >= '$start' OR tp.end_date IS NULL)
            ORDER BY t.first_name
        ";
        $teachersResult = $conn->query($teachersQuery);
        $teachers = [];
        while ($row = $teachersResult->fetch_assoc()) {
            $row['position'] = format_position($row['position']);
            $teachers[] = $row;
        }
        $gradesTeachers[] = ['id' => $glId, 'name' => $glName, 'teachers' => $teachers];
    }

    $ntQuery = "
        SELECT DISTINCT CONCAT(t.first_name, ' ', CASE WHEN t.middle_name IS NOT NULL AND t.middle_name != '' THEN CONCAT(LEFT(t.middle_name, 1), '. ') ELSE '' END, t.last_name) AS full_name, 
        t.image, pos.position_name AS position
        FROM teachers t
        JOIN teacher_positions tp ON t.teacher_id = tp.teacher_id
        JOIN positions pos ON tp.position_id = pos.position_id
        WHERE (t.user_type = 'a' OR t.user_type = 'n')
        AND pos.position_name NOT LIKE 'Principal%'
        AND tp.start_date <= '$end'
        AND (tp.end_date >= '$start' OR tp.end_date IS NULL)
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
    <script src="js/html2canvas.min.js"></script>
</head>
<body>
<div class="container">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <h1>Organizational Chart</h1>
        <div class="chart-container org-chart">
            <div class="filter-container">
                <button class="download-button" id="downloadOrgChart">Download as Image</button>
                <select id="orgYearFilter">
                    <?php foreach ($schoolYears as $sy): ?>
                        <option value="<?= $sy['school_year']; ?>" <?= $sy['school_year'] === $latestYear ? 'selected' : ''; ?>><?= $sy['school_year']; ?></option>
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
        let grades = data.grades || [];
        for (let grade of grades) {
            html += '<div class="org-grade">';
            let title = isNaN(parseInt(grade.name)) ? grade.name + ' Teachers' : 'Grade ' + grade.name + ' Teachers';
            html += '<h4 class="org-grade-title">' + title + '</h4>';
            let ts = grade.teachers || [];
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

    document.getElementById('downloadOrgChart').addEventListener('click', function() {
        const content = document.getElementById('orgChartContent');
        const selectedYear = document.getElementById('orgYearFilter').value.replace(/-/g, '_');
        html2canvas(content, {scale: 2}).then(canvas => {
            const link = document.createElement('a');
            link.download = `OrgChart_${selectedYear}.png`;
            link.href = canvas.toDataURL('image/png');
            link.click();
        });
    });
</script>
</body>
</html>