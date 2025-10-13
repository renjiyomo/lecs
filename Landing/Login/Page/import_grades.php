<?php
session_start();
include 'lecs_db.php';

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$pupil_id = intval($_POST['pupil_id']);
$sy_id = intval($_POST['sy_id']);

$p_sql = "SELECT p.lrn, s.grade_level_id FROM pupils p JOIN sections s ON p.section_id = s.section_id WHERE p.pupil_id = ?";
$p_stmt = $conn->prepare($p_sql);
$p_stmt->bind_param("i", $pupil_id);
$p_stmt->execute();
$pupil = $p_stmt->get_result()->fetch_assoc();
$pupil_lrn = $pupil['lrn'];
$grade_level_id = intval($pupil['grade_level_id']);

$logical_grade_map = [
    1 => 1,
    2 => 2,
    3 => 3,
    4 => 4,
    5 => 5,
    23 => 6
];
$logical_grade = $logical_grade_map[$grade_level_id] ?? null;
if ($logical_grade === null) {
    $_SESSION['import_error'] = "Unsupported grade level.";
    header("Location: edit_grades.php?pupil_id=$pupil_id&sy_id=$sy_id");
    exit;
}

$sy_sql = "SELECT school_year FROM school_years WHERE sy_id = ?";
$sy_stmt = $conn->prepare($sy_sql);
$sy_stmt->bind_param("i", $sy_id);
$sy_stmt->execute();
$sy = $sy_stmt->get_result()->fetch_assoc();
$school_year = $sy['school_year'];

if (isset($_FILES['file']) && $_FILES['file']['error'] === 0) {
    $file = $_FILES['file']['tmp_name'];
    try {
        $spreadsheet = IOFactory::load($file);

        $front_sheet = $spreadsheet->getSheet(0);

        $lrn_narrow = trim((string)$front_sheet->getCell('J10')->getValue());
        if (preg_match('/^\d{12}$/', $lrn_narrow)) {
            $font_type = 'narrow';
            $lrn = $lrn_narrow;
        } else {
            $lrn_arial = trim((string)$front_sheet->getCell('G10')->getValue());
            if (preg_match('/^\d{12}$/', $lrn_arial)) {
                $font_type = 'arial';
                $lrn = $lrn_arial;
            } else {
                throw new Exception("Unable to detect LRN in the file.");
            }
        }

        if ($lrn !== $pupil_lrn) {
            throw new Exception("LRN in the file does not match the pupil's LRN.");
        }

        if ($logical_grade <= 4) {
            $sheet = $spreadsheet->getSheet(0);
        } else {
            $sheet = $spreadsheet->getSheet(1);
        }

        $sy_cells = [
            'narrow' => [
                1 => 'S25',
                2 => 'AU25',
                3 => 'S54',
                4 => 'AU54',
                5 => 'O5',
                6 => 'AG5',
            ],
            'arial' => [
                1 => 'N25',
                2 => 'AC25',
                3 => 'N54',
                4 => 'AC54',
                5 => 'N5',
                6 => 'AC5',
            ],
        ];

        $sy_cell = $sy_cells[$font_type][$logical_grade] ?? null;
        if (!$sy_cell) {
            throw new Exception("Invalid grade level for school year extraction.");
        }

        $file_sy = trim((string)$sheet->getCell($sy_cell)->getValue());
        if ($file_sy !== $school_year) {
            throw new Exception("School year in the file does not match the selected school year.");
        }

        $grade_cells = [
            'narrow' => [
                1 => [
                    'Mother Tongue' => ['Q1'=>'K30', 'Q2'=>'L30', 'Q3'=>'N30', 'Q4'=>'O30'],
                    'Filipino' => ['Q1'=>'K31', 'Q2'=>'L31', 'Q3'=>'N31', 'Q4'=>'O31'],
                    'English' => ['Q1'=>'K32', 'Q2'=>'L32', 'Q3'=>'N32', 'Q4'=>'O32'],
                    'Mathematics' => ['Q1'=>'K33', 'Q2'=>'L33', 'Q3'=>'N33', 'Q4'=>'O33'],
                    'Science' => ['Q1'=>'K34', 'Q2'=>'L34', 'Q3'=>'N34', 'Q4'=>'O34'],
                    'Araling Panlipunan' => ['Q1'=>'K35', 'Q2'=>'L35', 'Q3'=>'N35', 'Q4'=>'O35'],
                    'Edukasyong Pantahanan at Pangkabuhayan / TLE' => ['Q1'=>'K36', 'Q2'=>'L36', 'Q3'=>'N36', 'Q4'=>'O36'],
                    'Music' => ['Q1'=>'K38', 'Q2'=>'L38', 'Q3'=>'N38', 'Q4'=>'O38'],
                    'Arts' => ['Q1'=>'K39', 'Q2'=>'L39', 'Q3'=>'N39', 'Q4'=>'O39'],
                    'Physical Education P.E' => ['Q1'=>'K40', 'Q2'=>'L40', 'Q3'=>'N40', 'Q4'=>'O40'],
                    'Health' => ['Q1'=>'K41', 'Q2'=>'L41', 'Q3'=>'N41', 'Q4'=>'O41'],
                    'Edukasyon sa Pagpapakatao' => ['Q1'=>'K42', 'Q2'=>'L42', 'Q3'=>'N42', 'Q4'=>'O42'],
                ],
                2 => [
                    'Mother Tongue' => ['Q1'=>'AJ30', 'Q2'=>'AM30', 'Q3'=>'AO30', 'Q4'=>'AR30'],
                    'Filipino' => ['Q1'=>'AJ31', 'Q2'=>'AM31', 'Q3'=>'AO31', 'Q4'=>'AR31'],
                    'English' => ['Q1'=>'AJ32', 'Q2'=>'AM32', 'Q3'=>'AO32', 'Q4'=>'AR32'],
                    'Mathematics' => ['Q1'=>'AJ33', 'Q2'=>'AM33', 'Q3'=>'AO33', 'Q4'=>'AR33'],
                    'Science' => ['Q1'=>'AJ34', 'Q2'=>'AM34', 'Q3'=>'AO34', 'Q4'=>'AR34'],
                    'Araling Panlipunan' => ['Q1'=>'AJ35', 'Q2'=>'AM35', 'Q3'=>'AO35', 'Q4'=>'AR35'],
                    'Edukasyong Pantahanan at Pangkabuhayan / TLE' => ['Q1'=>'AJ36', 'Q2'=>'AM36', 'Q3'=>'AO36', 'Q4'=>'AR36'],
                    'Music' => ['Q1'=>'AJ38', 'Q2'=>'AM38', 'Q3'=>'AO38', 'Q4'=>'AR38'],
                    'Arts' => ['Q1'=>'AJ39', 'Q2'=>'AM39', 'Q3'=>'AO39', 'Q4'=>'AR39'],
                    'Physical Education P.E' => ['Q1'=>'AJ40', 'Q2'=>'AM40', 'Q3'=>'AO40', 'Q4'=>'AR40'],
                    'Health' => ['Q1'=>'AJ41', 'Q2'=>'AM41', 'Q3'=>'AO41', 'Q4'=>'AR41'],
                    'Edukasyon sa Pagpapakatao' => ['Q1'=>'AJ42', 'Q2'=>'AM42', 'Q3'=>'AO42', 'Q4'=>'AR42'],
                ],
                3 => [
                    'Mother Tongue' => ['Q1'=>'K60', 'Q2'=>'L60', 'Q3'=>'N60', 'Q4'=>'O60'],
                    'Filipino' => ['Q1'=>'K61', 'Q2'=>'L61', 'Q3'=>'N61', 'Q4'=>'O61'],
                    'English' => ['Q1'=>'K62', 'Q2'=>'L62', 'Q3'=>'N62', 'Q4'=>'O62'],
                    'Mathematics' => ['Q1'=>'K63', 'Q2'=>'L63', 'Q3'=>'N63', 'Q4'=>'O63'],
                    'Science' => ['Q1'=>'K64', 'Q2'=>'L64', 'Q3'=>'N64', 'Q4'=>'O64'],
                    'Araling Panlipunan' => ['Q1'=>'K65', 'Q2'=>'L65', 'Q3'=>'N65', 'Q4'=>'O65'],
                    'Edukasyong Pantahanan at Pangkabuhayan / TLE' => ['Q1'=>'K66', 'Q2'=>'L66', 'Q3'=>'N66', 'Q4'=>'O66'],
                    'Music' => ['Q1'=>'K68', 'Q2'=>'L68', 'Q3'=>'N68', 'Q4'=>'O68'],
                    'Arts' => ['Q1'=>'K69', 'Q2'=>'L69', 'Q3'=>'N69', 'Q4'=>'O69'],
                    'Physical Education P.E' => ['Q1'=>'K70', 'Q2'=>'L70', 'Q3'=>'N70', 'Q4'=>'O70'],
                    'Health' => ['Q1'=>'K71', 'Q2'=>'L71', 'Q3'=>'N71', 'Q4'=>'O71'],
                    'Edukasyon sa Pagpapakatao' => ['Q1'=>'K72', 'Q2'=>'L72', 'Q3'=>'N72', 'Q4'=>'O72'],
                ],
                4 => [
                    'Mother Tongue' => ['Q1'=>'AJ60', 'Q2'=>'AM60', 'Q3'=>'AO60', 'Q4'=>'AR60'],
                    'Filipino' => ['Q1'=>'AJ61', 'Q2'=>'AM61', 'Q3'=>'AO61', 'Q4'=>'AR61'],
                    'English' => ['Q1'=>'AJ62', 'Q2'=>'AM62', 'Q3'=>'AO62', 'Q4'=>'AR62'],
                    'Mathematics' => ['Q1'=>'AJ63', 'Q2'=>'AM63', 'Q3'=>'AO63', 'Q4'=>'AR63'],
                    'Science' => ['Q1'=>'AJ64', 'Q2'=>'AM64', 'Q3'=>'AO64', 'Q4'=>'AR64'],
                    'Araling Panlipunan' => ['Q1'=>'AJ65', 'Q2'=>'AM65', 'Q3'=>'AO65', 'Q4'=>'AR65'],
                    'Edukasyong Pantahanan at Pangkabuhayan / TLE' => ['Q1'=>'AJ66', 'Q2'=>'AM66', 'Q3'=>'AO66', 'Q4'=>'AR66'],
                    'Music' => ['Q1'=>'AJ68', 'Q2'=>'AM68', 'Q3'=>'AO68', 'Q4'=>'AR68'],
                    'Arts' => ['Q1'=>'AJ69', 'Q2'=>'AM69', 'Q3'=>'AO69', 'Q4'=>'AR69'],
                    'Physical Education P.E' => ['Q1'=>'AJ70', 'Q2'=>'AM70', 'Q3'=>'AO70', 'Q4'=>'AR70'],
                    'Health' => ['Q1'=>'AJ71', 'Q2'=>'AM71', 'Q3'=>'AO71', 'Q4'=>'AR71'],
                    'Edukasyon sa Pagpapakatao' => ['Q1'=>'AJ72', 'Q2'=>'AM72', 'Q3'=>'AO72', 'Q4'=>'AR72'],
                ],
                5 => [
                    'Mother Tongue' => ['Q1'=>'H10', 'Q2'=>'I10', 'Q3'=>'J10', 'Q4'=>'K10'],
                    'Filipino' => ['Q1'=>'H11', 'Q2'=>'I11', 'Q3'=>'J11', 'Q4'=>'K11'],
                    'English' => ['Q1'=>'H12', 'Q2'=>'I12', 'Q3'=>'J12', 'Q4'=>'K12'],
                    'Mathematics' => ['Q1'=>'H13', 'Q2'=>'I13', 'Q3'=>'J13', 'Q4'=>'K13'],
                    'Science' => ['Q1'=>'H14', 'Q2'=>'I14', 'Q3'=>'J14', 'Q4'=>'K14'],
                    'Araling Panlipunan' => ['Q1'=>'H15', 'Q2'=>'I15', 'Q3'=>'J15', 'Q4'=>'K15'],
                    'Edukasyong Pantahanan at Pangkabuhayan / TLE' => ['Q1'=>'H16', 'Q2'=>'I16', 'Q3'=>'J16', 'Q4'=>'K16'],
                    'Music' => ['Q1'=>'H18', 'Q2'=>'I18', 'Q3'=>'J18', 'Q4'=>'K18'],
                    'Arts' => ['Q1'=>'H19', 'Q2'=>'I19', 'Q3'=>'J19', 'Q4'=>'K19'],
                    'Physical Education P.E' => ['Q1'=>'H20', 'Q2'=>'I20', 'Q3'=>'J20', 'Q4'=>'K20'],
                    'Health' => ['Q1'=>'H21', 'Q2'=>'I21', 'Q3'=>'I21', 'Q4'=>'J21'],
                    'Edukasyon sa Pagpapakatao' => ['Q1'=>'H22', 'Q2'=>'I22', 'Q3'=>'J22', 'Q4'=>'K22'],
                ],
                6 => [
                    'Mother Tongue' => ['Q1'=>'AB10', 'Q2'=>'AD10', 'Q3'=>'AE10', 'Q4'=>'AF10'],
                    'Filipino' => ['Q1'=>'AB11', 'Q2'=>'AD11', 'Q3'=>'AE11', 'Q4'=>'AF11'],
                    'English' => ['Q1'=>'AB12', 'Q2'=>'AD12', 'Q3'=>'AE12', 'Q4'=>'AF12'],
                    'Mathematics' => ['Q1'=>'AB13', 'Q2'=>'AD13', 'Q3'=>'AE13', 'Q4'=>'AF13'],
                    'Science' => ['Q1'=>'AB14', 'Q2'=>'AD14', 'Q3'=>'AE14', 'Q4'=>'AF14'],
                    'Araling Panlipunan' => ['Q1'=>'AB15', 'Q2'=>'AD15', 'Q3'=>'AE15', 'Q4'=>'AF15'],
                    'Edukasyong Pantahanan at Pangkabuhayan / TLE' => ['Q1'=>'AB16', 'Q2'=>'AD16', 'Q3'=>'AE16', 'Q4'=>'AF16'],
                    'Music' => ['Q1'=>'AB18', 'Q2'=>'AD18', 'Q3'=>'AE18', 'Q4'=>'AF18'],
                    'Arts' => ['Q1'=>'AB19', 'Q2'=>'AD19', 'Q3'=>'AE19', 'Q4'=>'AF19'],
                    'Physical Education P.E' => ['Q1'=>'AB20', 'Q2'=>'AD20', 'Q3'=>'AE20', 'Q4'=>'AF20'],
                    'Health' => ['Q1'=>'AB21', 'Q2'=>'AD21', 'Q3'=>'AE21', 'Q4'=>'AF21'],
                    'Edukasyon sa Pagpapakatao' => ['Q1'=>'AB22', 'Q2'=>'AD22', 'Q3'=>'AE22', 'Q4'=>'AF22'],
                ],
            ],
            'arial' => [
                1 => [
                    'Mother Tongue' => ['Q1'=>'G30', 'Q2'=>'H30', 'Q3'=>'I30', 'Q4'=>'J30'],
                    'Filipino' => ['Q1'=>'G31', 'Q2'=>'H31', 'Q3'=>'I31', 'Q4'=>'J31'],
                    'English' => ['Q1'=>'G32', 'Q2'=>'H32', 'Q3'=>'I32', 'Q4'=>'J32'],
                    'Mathematics' => ['Q1'=>'G33', 'Q2'=>'H33', 'Q3'=>'I33', 'Q4'=>'J33'],
                    'Science' => ['Q1'=>'G34', 'Q2'=>'H34', 'Q3'=>'I34', 'Q4'=>'J34'],
                    'Araling Panlipunan' => ['Q1'=>'G35', 'Q2'=>'H35', 'Q3'=>'I35', 'Q4'=>'J35'],
                    'Edukasyong Pantahanan at Pangkabuhayan / TLE' => ['Q1'=>'G36', 'Q2'=>'H36', 'Q3'=>'I36', 'Q4'=>'J36'],
                    'Music' => ['Q1'=>'G38', 'Q2'=>'H38', 'Q3'=>'I38', 'Q4'=>'J38'],
                    'Arts' => ['Q1'=>'G39', 'Q2'=>'H39', 'Q3'=>'I39', 'Q4'=>'J39'],
                    'Physical Education P.E' => ['Q1'=>'G40', 'Q2'=>'H40', 'Q3'=>'I40', 'Q4'=>'J40'],
                    'Health' => ['Q1'=>'G41', 'Q2'=>'H41', 'Q3'=>'I41', 'Q4'=>'J41'],
                    'Edukasyon sa Pagpapakatao' => ['Q1'=>'G42', 'Q2'=>'H42', 'Q3'=>'I42', 'Q4'=>'J42'],
                ],
                2 => [
                    'Mother Tongue' => ['Q1'=>'X30', 'Q2'=>'Z30', 'Q3'=>'AA30', 'Q4'=>'AB30'],
                    'Filipino' => ['Q1'=>'X31', 'Q2'=>'Z31', 'Q3'=>'AA31', 'Q4'=>'AB31'],
                    'English' => ['Q1'=>'X32', 'Q2'=>'Z32', 'Q3'=>'AA32', 'Q4'=>'AB32'],
                    'Mathematics' => ['Q1'=>'X33', 'Q2'=>'Z33', 'Q3'=>'AA33', 'Q4'=>'AB33'],
                    'Science' => ['Q1'=>'X34', 'Q2'=>'Z34', 'Q3'=>'AA34', 'Q4'=>'AB34'],
                    'Araling Panlipunan' => ['Q1'=>'X35', 'Q2'=>'Z35', 'Q3'=>'AA35', 'Q4'=>'AB35'],
                    'Edukasyong Pantahanan at Pangkabuhayan / TLE' => ['Q1'=>'X36', 'Q2'=>'Z36', 'Q3'=>'AA36', 'Q4'=>'AB36'],
                    'Music' => ['Q1'=>'X38', 'Q2'=>'Z38', 'Q3'=>'AA38', 'Q4'=>'AB38'],
                    'Arts' => ['Q1'=>'X39', 'Q2'=>'Z39', 'Q3'=>'AA39', 'Q4'=>'AB39'],
                    'Physical Education P.E' => ['Q1'=>'X40', 'Q2'=>'Z40', 'Q3'=>'AA40', 'Q4'=>'AB40'],
                    'Health' => ['Q1'=>'X41', 'Q2'=>'Z41', 'Q3'=>'AA41', 'Q4'=>'AB41'],
                    'Edukasyon sa Pagpapakatao' => ['Q1'=>'X42', 'Q2'=>'Z42', 'Q3'=>'AA42', 'Q4'=>'AB42'],
                ],
                3 => [
                    'Mother Tongue' => ['Q1'=>'G60', 'Q2'=>'H60', 'Q3'=>'I60', 'Q4'=>'J60'],
                    'Filipino' => ['Q1'=>'G61', 'Q2'=>'H61', 'Q3'=>'I61', 'Q4'=>'J61'],
                    'English' => ['Q1'=>'G62', 'Q2'=>'H62', 'Q3'=>'I62', 'Q4'=>'J62'],
                    'Mathematics' => ['Q1'=>'G63', 'Q2'=>'H63', 'Q3'=>'I63', 'Q4'=>'J63'],
                    'Science' => ['Q1'=>'G64', 'Q2'=>'H64', 'Q3'=>'I64', 'Q4'=>'J64'],
                    'Araling Panlipunan' => ['Q1'=>'G65', 'Q2'=>'H65', 'Q3'=>'I65', 'Q4'=>'J65'],
                    'Edukasyong Pantahanan at Pangkabuhayan / TLE' => ['Q1'=>'G66', 'Q2'=>'H66', 'Q3'=>'I66', 'Q4'=>'J66'],
                    'Music' => ['Q1'=>'G68', 'Q2'=>'H68', 'Q3'=>'I68', 'Q4'=>'J68'],
                    'Arts' => ['Q1'=>'G69', 'Q2'=>'H69', 'Q3'=>'I69', 'Q4'=>'J69'],
                    'Physical Education P.E' => ['Q1'=>'G70', 'Q2'=>'H70', 'Q3'=>'I70', 'Q4'=>'J70'],
                    'Health' => ['Q1'=>'G71', 'Q2'=>'H71', 'Q3'=>'I71', 'Q4'=>'J71'],
                    'Edukasyon sa Pagpapakatao' => ['Q1'=>'G72', 'Q2'=>'H72', 'Q3'=>'I72', 'Q4'=>'J72'],
                ],
                4 => [
                    'Mother Tongue' => ['Q1'=>'X60', 'Q2'=>'Z60', 'Q3'=>'AA60', 'Q4'=>'AB60'],
                    'Filipino' => ['Q1'=>'X61', 'Q2'=>'Z61', 'Q3'=>'AA61', 'Q4'=>'AB61'],
                    'English' => ['Q1'=>'X62', 'Q2'=>'Z62', 'Q3'=>'AA62', 'Q4'=>'AB62'],
                    'Mathematics' => ['Q1'=>'X63', 'Q2'=>'Z63', 'Q3'=>'AA63', 'Q4'=>'AB63'],
                    'Science' => ['Q1'=>'X64', 'Q2'=>'Z64', 'Q3'=>'AA64', 'Q4'=>'AB64'],
                    'Araling Panlipunan' => ['Q1'=>'X65', 'Q2'=>'Z65', 'Q3'=>'AA65', 'Q4'=>'AB65'],
                    'Edukasyong Pantahanan at Pangkabuhayan / TLE' => ['Q1'=>'X66', 'Q2'=>'Z66', 'Q3'=>'AA66', 'Q4'=>'AB66'],
                    'Music' => ['Q1'=>'X68', 'Q2'=>'Z68', 'Q3'=>'AA68', 'Q4'=>'AB68'],
                    'Arts' => ['Q1'=>'X69', 'Q2'=>'Z69', 'Q3'=>'AA69', 'Q4'=>'AB69'],
                    'Physical Education P.E' => ['Q1'=>'X70', 'Q2'=>'Z70', 'Q3'=>'AA70', 'Q4'=>'AB70'],
                    'Health' => ['Q1'=>'X71', 'Q2'=>'Z71', 'Q3'=>'AA71', 'Q4'=>'AB71'],
                    'Edukasyon sa Pagpapakatao' => ['Q1'=>'X72', 'Q2'=>'Z72', 'Q3'=>'AA72', 'Q4'=>'AB72'],
                ],
                5 => [
                    'Mother Tongue' => ['Q1'=>'G10', 'Q2'=>'H10', 'Q3'=>'I10', 'Q4'=>'J10'],
                    'Filipino' => ['Q1'=>'G11', 'Q2'=>'H11', 'Q3'=>'I11', 'Q4'=>'J11'],
                    'English' => ['Q1'=>'G12', 'Q2'=>'H12', 'Q3'=>'I12', 'Q4'=>'J12'],
                    'Mathematics' => ['Q1'=>'G13', 'Q2'=>'H13', 'Q3'=>'I13', 'Q4'=>'J13'],
                    'Science' => ['Q1'=>'G14', 'Q2'=>'H14', 'Q3'=>'I14', 'Q4'=>'J14'],
                    'Araling Panlipunan' => ['Q1'=>'G15', 'Q2'=>'H15', 'Q3'=>'I15', 'Q4'=>'J15'],
                    'Edukasyong Pantahanan at Pangkabuhayan / TLE' => ['Q1'=>'G16', 'Q2'=>'H16', 'Q3'=>'I16', 'Q4'=>'J16'],
                    'Music' => ['Q1'=>'G18', 'Q2'=>'H18', 'Q3'=>'I18', 'Q4'=>'J18'],
                    'Arts' => ['Q1'=>'G19', 'Q2'=>'H19', 'Q3'=>'I19', 'Q4'=>'J19'],
                    'Physical Education P.E' => ['Q1'=>'G20', 'Q2'=>'H20', 'Q3'=>'I20', 'Q4'=>'J20'],
                    'Health' => ['Q1'=>'G21', 'Q2'=>'H21', 'Q3'=>'I21', 'Q4'=>'J21'],
                    'Edukasyon sa Pagpapakatao' => ['Q1'=>'G22', 'Q2'=>'H22', 'Q3'=>'I22', 'Q4'=>'J22'],
                ],
                6 => [
                    'Mother Tongue' => ['Q1'=>'AB10', 'Q2'=>'AD10', 'Q3'=>'AE10', 'Q4'=>'AF10'],
                    'Filipino' => ['Q1'=>'AB11', 'Q2'=>'AD11', 'Q3'=>'AE11', 'Q4'=>'AF11'],
                    'English' => ['Q1'=>'AB12', 'Q2'=>'AD12', 'Q3'=>'AE12', 'Q4'=>'AF12'],
                    'Mathematics' => ['Q1'=>'AB13', 'Q2'=>'AD13', 'Q3'=>'AE13', 'Q4'=>'AF13'],
                    'Science' => ['Q1'=>'AB14', 'Q2'=>'AD14', 'Q3'=>'AE14', 'Q4'=>'AF14'],
                    'Araling Panlipunan' => ['Q1'=>'AB15', 'Q2'=>'AD15', 'Q3'=>'AE15', 'Q4'=>'AF15'],
                    'Edukasyong Pantahanan at Pangkabuhayan / TLE' => ['Q1'=>'AB16', 'Q2'=>'AD16', 'Q3'=>'AE16', 'Q4'=>'AF16'],
                    'Music' => ['Q1'=>'AB18', 'Q2'=>'AD18', 'Q3'=>'AE18', 'Q4'=>'AF18'],
                    'Arts' => ['Q1'=>'AB19', 'Q2'=>'AD19', 'Q3'=>'AE19', 'Q4'=>'AF19'],
                    'Physical Education P.E' => ['Q1'=>'AB20', 'Q2'=>'AD20', 'Q3'=>'AE20', 'Q4'=>'AF20'],
                    'Health' => ['Q1'=>'AB21', 'Q2'=>'AD21', 'Q3'=>'AE21', 'Q4'=>'AF21'],
                    'Edukasyon sa Pagpapakatao' => ['Q1'=>'AB22', 'Q2'=>'AD22', 'Q3'=>'AE22', 'Q4'=>'AF22'],
                ],
            ],
        ];

        $cells = $grade_cells[$font_type][$logical_grade] ?? null;
        if (!$cells) {
            throw new Exception("No cell mappings for this grade level and font type.");
        }

        // Fetch DB subjects
        $db_subjects = [];
        $sub_sql = "SELECT subject_id, LOWER(subject_name) AS norm_name, parent_subject_id FROM subjects WHERE grade_level_id = ? AND sy_id = ?";
        $sub_stmt = $conn->prepare($sub_sql);
        $sub_stmt->bind_param("ii", $grade_level_id, $sy_id);
        $sub_stmt->execute();
        $sub_res = $sub_stmt->get_result();
        while ($row = $sub_res->fetch_assoc()) {
            $norm = normalize_subject($row['norm_name']);
            $db_subjects[$norm] = ['id' => $row['subject_id'], 'parent' => $row['parent_subject_id']];
        }

        // Debug: Log available subjects
        error_log("DB Subjects: " . print_r($db_subjects, true));

        // Extract grades
        $imported_grades = [];
        foreach ($cells as $file_sub => $quarters) {
            $norm_sub = normalize_subject($file_sub);
            error_log("Processing subject: $file_sub -> Normalized: $norm_sub");
            if (isset($db_subjects[$norm_sub])) {
                $sid = $db_subjects[$norm_sub]['id'];
                foreach (['Q1', 'Q2', 'Q3', 'Q4'] as $q) {
                    $cell = $quarters[$q] ?? null;
                    if ($cell) {
                        $grade_val = trim((string)$sheet->getCell($cell)->getValue());
                        if (ctype_digit($grade_val) && intval($grade_val) >= 60 && intval($grade_val) <= 100) {
                            $imported_grades[$sid][$q] = intval($grade_val);
                        }
                    }
                }
            } else {
                error_log("Subject not found in DB: $norm_sub");
            }
        }

        if (empty($imported_grades)) {
            throw new Exception("No valid grades found in the file.");
        }

        $_SESSION['imported_grades'] = $imported_grades;
        $_SESSION['import_message'] = "Grades imported successfully. Please review before saving.";
    } catch (Exception $e) {
        $_SESSION['import_error'] = $e->getMessage();
        error_log("Import error: " . $e->getMessage());
    }
} else {
    $_SESSION['import_error'] = "File upload error.";
}

header("Location: edit_grades.php?pupil_id=$pupil_id&sy_id=$sy_id");
exit;

function normalize_subject($name) {
    $name = strtolower(trim($name));
    $name = preg_replace('/\s+/', ' ', $name);
    $name = str_replace(' / ', '/', $name);
    $name = str_replace([' p.e', '/ tle', '.', 'eduk. sa pagpapakatao', 'eduk sa pagpapakatao', 'epp/tle', 'esp'], ['', '', '', 'edukasyon sa pagpapakatao', 'edukasyon sa pagpapakatao', 'edukasyong pantahanan at pangkabuhayan / tle', 'edukasyon sa pagpapakatao'], $name);
    return $name;
}