<?php 
// adminDashboard.php
include 'lecs_db.php';
session_start();

// Restrict access to admin only
if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 'a') {
    header("Location: /lecs/Landing/Login/login.php");
    exit;
}

$teacher_id = intval($_SESSION['teacher_id']);

// Fetch teacher details to get the name
$stmt = $conn->prepare("SELECT first_name, middle_name, last_name FROM teachers WHERE teacher_id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();
$teacherName = implode(' ', array_filter([$teacher['first_name'], $teacher['middle_name'], $teacher['last_name']]));

// Basic counts
$studentCount = (int)$conn->query("SELECT COUNT(*) AS total FROM pupils")->fetch_assoc()['total'];
$teacherCount = (int)$conn->query("SELECT COUNT(*) AS total FROM teachers WHERE user_type = 't'")->fetch_assoc()['total'];
$nonTeachingCount = (int)$conn->query("SELECT COUNT(*) AS total FROM teachers WHERE user_type = 'a'")->fetch_assoc()['total'];

// All school years (ordered by start_date if you have that column in school_years)
$allYearsQuery = $conn->query("
    SELECT school_year, start_date, end_date
    FROM school_years
    ORDER BY start_date
");
$allYears = [];
$schoolYears = [];
while ($row = $allYearsQuery->fetch_assoc()) {
    $allYears[] = $row['school_year'];
    $schoolYears[$row['school_year']] = [
        'start_date' => $row['start_date'],
        'end_date' => $row['end_date']
    ];
}

// Enrolled per year
$enrollmentData = $conn->query("
    SELECT sy.school_year, COUNT(p.pupil_id) AS total
    FROM pupils p
    JOIN school_years sy ON p.sy_id = sy.sy_id
    WHERE p.status = 'enrolled'
    GROUP BY sy.school_year
    ORDER BY sy.start_date
");

// Dropped per year
$droppedData = $conn->query("
    SELECT sy.school_year, COUNT(p.pupil_id) AS total
    FROM pupils p
    JOIN school_years sy ON p.sy_id = sy.sy_id
    WHERE p.status = 'dropped'
    GROUP BY sy.school_year
    ORDER BY sy.start_date
");

// Gender counts per year (ensure we cast to ints)
$genderPerYearQuery = $conn->query("
    SELECT sy.school_year, p.sex, COUNT(*) AS total
    FROM pupils p
    JOIN school_years sy ON p.sy_id = sy.sy_id
    GROUP BY sy.school_year, p.sex
    ORDER BY sy.start_date, p.sex
");
$genderPerYear = [];
while ($row = $genderPerYearQuery->fetch_assoc()) {
    $year = $row['school_year'];
    $sexRaw = strtolower(trim($row['sex'] ?? ''));
    // Normalize sex to male/female
    if (strpos($sexRaw, 'm') === 0) {
        $sex = 'male';
    } elseif (strpos($sexRaw, 'f') === 0) {
        $sex = 'female';
    } else {
        // skip unknown
        continue;
    }
    if (!isset($genderPerYear[$year])) {
        $genderPerYear[$year] = ['male' => 0, 'female' => 0];
    }
    $genderPerYear[$year][$sex] = (int)$row['total'];
}

// Overall gender totals
$genderData = $conn->query("
    SELECT sex, COUNT(*) AS total
    FROM pupils
    GROUP BY sex
");
$maleCount = 0;
$femaleCount = 0;
while ($row = $genderData->fetch_assoc()) {
    $sexRaw = strtolower(trim($row['sex'] ?? ''));
    if (strpos($sexRaw, 'm') === 0) {
        $maleCount = (int)$row['total'];
    } elseif (strpos($sexRaw, 'f') === 0) {
        $femaleCount = (int)$row['total'];
    }
}

// Build arrays for enrolled/dropped charts (ensures integer values and consistent order with $allYears)
$studentsByYear = array_fill(0, count($allYears), 0);
$enrollmentMap = [];
while ($row = $enrollmentData->fetch_assoc()) {
    $enrollmentMap[$row['school_year']] = (int)$row['total'];
}
foreach ($allYears as $i => $year) {
    $studentsByYear[$i] = $enrollmentMap[$year] ?? 0;
}

$droppedByYear = array_fill(0, count($allYears), 0);
$droppedMap = [];
while ($row = $droppedData->fetch_assoc()) {
    $droppedMap[$row['school_year']] = (int)$row['total'];
}
foreach ($allYears as $i => $year) {
    $droppedByYear[$i] = $droppedMap[$year] ?? 0;
}

// Compute dropout rates (DEPED Simple Dropout Rate: dropped / total_enrolled * 100)
$dropoutRatesByYear = array_fill(0, count($allYears), 0);
$dropoutRatesMap = [];
foreach ($allYears as $i => $year) {
    $enrolled = $enrollmentMap[$year] ?? 0;
    $dropped = $droppedMap[$year] ?? 0;
    $total = $enrolled + $dropped;
    $rate = $total > 0 ? round(($dropped / $total) * 100, 2) : 0;
    $dropoutRatesByYear[$i] = $rate;
    $dropoutRatesMap[$year] = $rate;
}

// Transfer in and out per year
$transferQuery = $conn->query("SELECT remarks FROM pupils WHERE remarks <> ''");
$transferInMap = array_fill_keys($allYears, 0);
$transferOutMap = array_fill_keys($allYears, 0);

while ($row = $transferQuery->fetch_assoc()) {
    $remarks = $row['remarks'];
    preg_match_all("/(T\/[IO])\s*DATE:(\d{4}[\/-]\d{2}[\/-]\d{2})/i", $remarks, $matches);
    for ($i = 0; $i < count($matches[0]); $i++) {
        $type = strtoupper($matches[1][$i]);
        $dateStr = preg_replace('#/#', '-', $matches[2][$i]);
        $transferDate = DateTime::createFromFormat('Y-m-d', $dateStr);
        if ($transferDate) {
            $tDate = $transferDate->format('Y-m-d');
            foreach ($schoolYears as $year => $sy) {
                if ($tDate >= $sy['start_date'] && $tDate <= $sy['end_date']) {
                    if ($type == 'T/I') {
                        $transferInMap[$year]++;
                    } elseif ($type == 'T/O') {
                        $transferOutMap[$year]++;
                    }
                    break;
                }
            }
        }
    }
}

$transferInByYear = [];
$transferOutByYear = [];
$transferPerYear = [];
foreach ($allYears as $year) {
    $transferInByYear[] = $transferInMap[$year];
    $transferOutByYear[] = $transferOutMap[$year];
    $transferPerYear[$year] = ['in' => $transferInMap[$year], 'out' => $transferOutMap[$year]];
}

$dropoutRatesPerYearJson = json_encode($dropoutRatesMap, JSON_NUMERIC_CHECK);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LECS GIS</title>
    <link rel="icon" href="images/lecs-logo no bg.png" type="image/x-icon">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <script src="js/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/dom-to-image/2.6.0/dom-to-image.min.js"></script>
    <style>
        body.dark .female-text, body.dark .male-text {
            color: white;
        }
        .download-container {
            position: absolute;
            top: 10px;
            left: 10px;
            display: flex;
            align-items: center;
            margin-top: 0;
        }
        .download-select {
            padding: 3px;
            border-radius: 6px;
            font-size: 14px;
        }
        body.light .download-select {
            background-color: var(--card-bg-light);
            color: var(--text-light);
            border: 1px solid #ccc;
        }
        body.dark .download-select {
            background-color: var(--card-bg-dark);
            color: var(--text-dark);
            border: 1px solid #555;
        }
    </style>
</head>
<body class="light">

<div class="container">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <h1>Admin Dashboard</h1>
        <p>Welcome back, <?php echo htmlspecialchars($teacherName); ?>!</p>

        <div class="stats">
            <div class="card orange">
                <h3><?php echo $studentCount; ?></h3>
                <p>Pupils</p>
            </div>
            <div class="card purple">
                <h3><?php echo $teacherCount; ?></h3>
                <p>Teaching Personnel</p>
            </div>
            <div class="card blue">
                <h3><?php echo $nonTeachingCount; ?></h3>
                <p>Non-teaching Personnel</p>
            </div>
        </div>

        <div class="charts">
            <div class="chart-container enrolled-chart">
                <h3>Enrolled Pupils Per Year</h3>
                <div class="download-container">
                    <select class="download-select">
                        <option value="">Download...</option>
                        <option value="png">PNG</option>
                        <option value="jpeg">JPG</option>
                        <option value="svg">SVG</option>
                    </select>
                </div>
                <canvas id="enrollmentChart"></canvas>
            </div>
            <div class="chart-container gender-chart">
                <div class="filter-container">
                    <select id="genderYearFilter">
                        <option value="All">All</option>
                        <?php foreach ($allYears as $year): ?>
                            <option value="<?php echo htmlspecialchars($year); ?>"><?php echo htmlspecialchars($year); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="download-container">
                    <select class="download-select">
                        <option value="">Download...</option>
                        <option value="png">PNG</option>
                        <option value="jpeg">JPG</option>
                        <option value="svg">SVG</option>
                    </select>
                </div>
                <h3>Sex Distribution</h3>
                <div class="small-chart">
                    <canvas id="genderChart"></canvas>
                </div>
                <div class="gender-legend">
                    <span class="female-text">Female: <?php echo $femaleCount; ?></span>
                    <span class="male-text">Male: <?php echo $maleCount; ?></span>
                </div>
            </div>
        </div>

        <div class="charts" style="margin-top:18px;">
            <div class="chart-container transferred-chart">
                <div class="filter-container">
                    <select id="transferYearFilter">
                        <option value="All">All</option>
                        <?php foreach ($allYears as $year): ?>
                            <option value="<?php echo htmlspecialchars($year); ?>"><?php echo htmlspecialchars($year); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="download-container">
                    <select class="download-select">
                        <option value="">Download...</option>
                        <option value="png">PNG</option>
                        <option value="jpeg">JPG</option>
                        <option value="svg">SVG</option>
                    </select>
                </div>
                <h3>Transferred Pupils Per Year</h3>
                <canvas id="transferChart"></canvas>
            </div>
            
            <div class="chart-container dropped-chart">
                <div class="filter-container">
                    <select id="droppedYearFilter">
                        <option value="All">All</option>
                        <?php foreach ($allYears as $year): ?>
                            <option value="<?php echo htmlspecialchars($year); ?>"><?php echo htmlspecialchars($year); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="download-container">
                    <select class="download-select">
                        <option value="">Download...</option>
                        <option value="png">PNG</option>
                        <option value="jpeg">JPG</option>
                        <option value="svg">SVG</option>
                    </select>
                </div>
                <h3>Dropout Rate Per Year</h3>
                <canvas id="droppedChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
    // enrollment chart
    const ctx1 = document.getElementById('enrollmentChart').getContext('2d');
    const enrollmentChart = new Chart(ctx1, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($allYears); ?>,
            datasets: [{
                label: 'Enrolled Pupils',
                data: <?php echo json_encode($studentsByYear); ?>,
                backgroundColor: ['#03DAC5', '#BB86FC'],
                borderRadius: 0
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                x: {
                    title: { display: true, text: 'School Year' },
                    ticks: { callback: function(value, index, values) { return this.getLabelForValue(value); } }
                },
                y: {
                    title: { display: true, text: 'Total Pupils' },
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            },
            barPercentage: 1.0,
            categoryPercentage: 1.0,
            layout: {
                padding: {
                    right: 30
                }
            }
        }
    });

    // gender chart
    const ctx2 = document.getElementById('genderChart').getContext('2d');

    const allGenders = { male: <?php echo (int)$maleCount; ?>, female: <?php echo (int)$femaleCount; ?> };

    const genderPerYear = <?php echo json_encode($genderPerYear, JSON_NUMERIC_CHECK); ?>;

    const genderChart = new Chart(ctx2, {
        type: 'doughnut',
        data: {

            labels: ['Male', 'Female'],
            datasets: [{
                data: [allGenders.male, allGenders.female],
                backgroundColor: ['#03DAC5', '#BB86FC'],
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { display: false }, 
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = Number(context.raw) || 0;

                            const dataset = context.chart.data.datasets[0].data.map(Number);
                            const total = dataset.reduce((acc, v) => acc + v, 0);
                            const percentage = total ? ((value / total) * 100).toFixed(0) : 0;
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });

    // dropped chart
    const ctx3 = document.getElementById('droppedChart').getContext('2d');
    const dropoutRatesPerYear = <?php echo $dropoutRatesPerYearJson; ?>;
    const droppedChart = new Chart(ctx3, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($allYears); ?>,
            datasets: [{
                label: 'Dropout Rate (%)',
                data: <?php echo json_encode($dropoutRatesByYear); ?>,
                backgroundColor: ['#EF4444', '#F87171', '#FCA5A5', '#FDBA74', '#FBBF24'],
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                x: {
                    title: { display: true, text: 'School Year' },
                    ticks: { callback: function(value, index, values) { return this.getLabelForValue(value); } }
                },
                y: {
                    title: { display: true, text: 'Rate (%)' },
                    beginAtZero: true,
                    ticks: { 
                        precision: 2,
                        callback: function(value) { 
                            return value.toFixed(2) + '%'; 
                        }
                    },
                    max: 100
                }
            },
            barPercentage: 1.0,
            categoryPercentage: 1.0,
            layout: {
                padding: {
                    right: 30
                }
            }
        }
    });

    // transfer chart
    const ctx4 = document.getElementById('transferChart').getContext('2d');
    const transferPerYear = <?php echo json_encode($transferPerYear, JSON_NUMERIC_CHECK); ?>;
    const transferChart = new Chart(ctx4, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($allYears); ?>,
            datasets: [{
                label: 'Transfer In',
                data: <?php echo json_encode($transferInByYear); ?>,
                backgroundColor: '#03DAC5',
                borderRadius: 6
            }, {
                label: 'Transfer Out',
                data: <?php echo json_encode($transferOutByYear); ?>,
                backgroundColor: '#BB86FC',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: true, position: 'bottom' } },
            scales: {
                x: {
                    title: { display: true, text: 'School Year' },
                    ticks: { callback: function(value, index, values) { return this.getLabelForValue(value); } }
                },
                y: {
                    title: { display: true, text: 'Total Pupils' },
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            },
            barPercentage: 1,
            categoryPercentage: 0.8,
            layout: {
                padding: {
                    right: 30
                }
            }
        }
    });

    function updateChartsTheme() {
        const isDark = document.body.classList.contains('dark');
        const textColor = isDark ? '#ffffff' : '#000000';
        const gridColor = isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)';
        const tooltipBg = isDark ? 'rgba(0,0,0,0.8)' : 'rgba(255,255,255,0.8)';

        // Enrollment Chart
        enrollmentChart.options.scales.x.ticks.color = textColor;
        enrollmentChart.options.scales.x.title.color = textColor;
        enrollmentChart.options.scales.x.grid.color = gridColor;
        enrollmentChart.options.scales.y.ticks.color = textColor;
        enrollmentChart.options.scales.y.title.color = textColor;
        enrollmentChart.options.scales.y.grid.color = gridColor;
        enrollmentChart.options.plugins.tooltip.backgroundColor = tooltipBg;
        enrollmentChart.options.plugins.tooltip.titleColor = textColor;
        enrollmentChart.options.plugins.tooltip.bodyColor = textColor;
        enrollmentChart.update();

        // Gender Chart
        genderChart.options.plugins.tooltip.backgroundColor = tooltipBg;
        genderChart.options.plugins.tooltip.titleColor = textColor;
        genderChart.options.plugins.tooltip.bodyColor = textColor;
        genderChart.update();

        // Dropped Chart
        droppedChart.options.scales.x.ticks.color = textColor;
        droppedChart.options.scales.x.title.color = textColor;
        droppedChart.options.scales.x.grid.color = gridColor;
        droppedChart.options.scales.y.ticks.color = textColor;
        droppedChart.options.scales.y.title.color = textColor;
        droppedChart.options.scales.y.grid.color = gridColor;
        droppedChart.options.plugins.tooltip.backgroundColor = tooltipBg;
        droppedChart.options.plugins.tooltip.titleColor = textColor;
        droppedChart.options.plugins.tooltip.bodyColor = textColor;
        droppedChart.update();

        // Transfer Chart
        transferChart.options.scales.x.ticks.color = textColor;
        transferChart.options.scales.x.title.color = textColor;
        transferChart.options.scales.x.grid.color = gridColor;
        transferChart.options.scales.y.ticks.color = textColor;
        transferChart.options.scales.y.title.color = textColor;
        transferChart.options.scales.y.grid.color = gridColor;
        transferChart.options.plugins.legend.labels.color = textColor;
        transferChart.options.plugins.tooltip.backgroundColor = tooltipBg;
        transferChart.options.plugins.tooltip.titleColor = textColor;
        transferChart.options.plugins.tooltip.bodyColor = textColor;
        transferChart.update();
    }

    // Initial update
    updateChartsTheme();

    // Observe body class changes
    const observer = new MutationObserver(updateChartsTheme);
    observer.observe(document.body, { attributes: true, attributeFilter: ['class'] });

    document.getElementById('genderYearFilter').addEventListener('change', function() {
        const selected = this.value;
        let male = 0, female = 0;

        if (selected === 'All') {
            male = Number(allGenders.male) || 0;
            female = Number(allGenders.female) || 0;
        } else {
            const data = genderPerYear[selected] || { male: 0, female: 0 };
            male = Number(data.male) || 0;
            female = Number(data.female) || 0;
        }

        genderChart.data.datasets[0].data = [male, female];
        genderChart.update();

        const femaleTextEl = document.querySelector('.female-text');
        const maleTextEl = document.querySelector('.male-text');
        const totalEl = document.querySelector('.gender-total');

        if (femaleTextEl) femaleTextEl.textContent = `Female: ${female}`;
        if (maleTextEl) maleTextEl.textContent = `Male: ${male}`;
        if (totalEl) totalEl.textContent = `Total: ${male + female}`;
    });

    document.getElementById('transferYearFilter').addEventListener('change', function() {
        const selected = this.value;
        if (selected === 'All') {
            transferChart.data.labels = <?php echo json_encode($allYears); ?>;
            transferChart.data.datasets[0].data = <?php echo json_encode($transferInByYear); ?>;
            transferChart.data.datasets[1].data = <?php echo json_encode($transferOutByYear); ?>;
        } else {
            transferChart.data.labels = [selected];
            transferChart.data.datasets[0].data = [transferPerYear[selected].in];
            transferChart.data.datasets[1].data = [transferPerYear[selected].out];
        }
        transferChart.update();
    });

    document.getElementById('droppedYearFilter').addEventListener('change', function() {
        const selected = this.value;
        if (selected === 'All') {
            droppedChart.data.labels = <?php echo json_encode($allYears); ?>;
            droppedChart.data.datasets[0].data = <?php echo json_encode($dropoutRatesByYear); ?>;
        } else {
            droppedChart.data.labels = [selected];
            droppedChart.data.datasets[0].data = [dropoutRatesPerYear[selected] || 0];
        }
        droppedChart.update();
    });

    document.getElementById('genderYearFilter').dispatchEvent(new Event('change'));

    // Download functionality
    document.querySelectorAll('.download-select').forEach(select => {
        select.addEventListener('change', () => {
            const format = select.value;
            if (!format) return;
            const container = select.closest('.chart-container');
            const containerClass = Array.from(container.classList).find(cls => cls.endsWith('-chart'));
            const ext = format === 'jpeg' ? 'jpg' : format;
            const options = {
                filter: function(node) {
                    if (node.classList) {
                        return !node.classList.contains('filter-container') && !node.classList.contains('download-container');
                    }
                    return true;
                }
            };
            let toFunc;
            if (format === 'png') {
                toFunc = domtoimage.toPng;
            } else if (format === 'jpeg') {
                toFunc = domtoimage.toJpeg;
            } else if (format === 'svg') {
                toFunc = domtoimage.toSvg;
            }
            toFunc(container, options).then(dataUrl => {
                const a = document.createElement('a');
                a.href = dataUrl;
                a.download = `${containerClass}.${ext}`;
                a.click();
                select.value = '';
            }).catch(err => {
                console.error('Error generating image:', err);
                select.value = '';
            });
        });
    });
</script>

</body>
</html>