<?php
include 'lecs_db.php';
session_start();

// Restrict access to teachers and admins
if (!isset($_SESSION['teacher_id']) || !in_array($_SESSION['user_type'], ['t', 'a'])) {
    header("Location: /lecs/Landing/Login/login.php");
    exit;
}

// Rest of the script remains unchanged
$pupil_id = $_POST['pupil_id'] ?? 0;
$sy_id = $_POST['sy_id'] ?? 0;
if ($pupil_id == 0 || $sy_id == 0) {
    header("Location: teacherGrades.php");
    exit;
}

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Font;

$p_sql = "SELECT p.*, g.level_name 
          FROM pupils p 
          JOIN sections s ON p.section_id = s.section_id 
          JOIN grade_levels g ON s.grade_level_id = g.grade_level_id 
          WHERE p.pupil_id = ? AND p.sy_id = ?";
$p_stmt = $conn->prepare($p_sql);
$p_stmt->bind_param("ii", $pupil_id, $sy_id);
$p_stmt->execute();
$pupil = $p_stmt->get_result()->fetch_assoc();
if (!$pupil) {
    header("Location: teacherGrades.php");
    exit;
}
$lrn = $pupil['lrn'];
$current_grade_level = (int)$pupil['level_name'];

$history_sql = "SELECT p.pupil_id, p.sy_id, p.section_id, p.teacher_id 
                FROM pupils p WHERE p.lrn = ?";
$history_stmt = $conn->prepare($history_sql);
$history_stmt->bind_param("s", $lrn);
$history_stmt->execute();
$history_res = $history_stmt->get_result();

$history = [];
while ($hist = $history_res->fetch_assoc()) {
    $gl_sql = "SELECT g.grade_level_id, g.level_name FROM grade_levels g 
               JOIN sections s ON s.grade_level_id = g.grade_level_id 
               WHERE s.section_id = ? AND s.sy_id = ?";
    $gl_stmt = $conn->prepare($gl_sql);
    $gl_stmt->bind_param("ii", $hist['section_id'], $hist['sy_id']);
    $gl_stmt->execute();
    $gl = $gl_stmt->get_result()->fetch_assoc();
    $level_num = (int)$gl['level_name'];
    $grade_level_id = $gl['grade_level_id'];

    $sec_sql = "SELECT section_name FROM sections WHERE section_id = ?";
    $sec_stmt = $conn->prepare($sec_sql);
    $sec_stmt->bind_param("i", $hist['section_id']);
    $sec_stmt->execute();
    $section_name = $sec_stmt->get_result()->fetch_assoc()['section_name'];

    $t_sql = "SELECT CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name) AS teacher_name 
              FROM teachers WHERE teacher_id = ?";
    $t_stmt = $conn->prepare($t_sql);
    $t_stmt->bind_param("i", $hist['teacher_id']);
    $t_stmt->execute();
    $teacher_name = $t_stmt->get_result()->fetch_assoc()['teacher_name'];

    $sy_sql = "SELECT school_year FROM school_years WHERE sy_id = ?";
    $sy_stmt = $conn->prepare($sy_sql);
    $sy_stmt->bind_param("i", $hist['sy_id']);
    $sy_stmt->execute();
    $school_year = $sy_stmt->get_result()->fetch_assoc()['school_year'];

    $grades_sql = "SELECT s.subject_id, s.subject_name, s.parent_subject_id, g.quarter, g.grade 
                   FROM grades g JOIN subjects s ON g.subject_id = s.subject_id 
                   WHERE g.pupil_id = ? AND g.sy_id = ?";
    $grades_stmt = $conn->prepare($grades_sql);
    $grades_stmt->bind_param("ii", $hist['pupil_id'], $hist['sy_id']);
    $grades_stmt->execute();
    $grades_res = $grades_stmt->get_result();

    $level_grades = [];
    while ($g = $grades_res->fetch_assoc()) {
        $sub_name = strtoupper(trim($g['subject_name']));
        if ($g['parent_subject_id']) {
            $level_grades[$sub_name][$g['quarter']] = $g['grade'];
        } else {
            $level_grades[$sub_name][$g['quarter']] = $g['grade'];
        }
    }

    $history[$level_num] = [
        'sy_id' => $hist['sy_id'],
        'grade_level_id' => $grade_level_id,
        'section' => $section_name,
        'teacher' => $teacher_name,
        'school_year' => $school_year,
        'grades' => $level_grades
    ];
}

$mappings = [
    1 => [
        'sheet' => 'Front',
        'school' => 'D23',
        'school_id' => 'S23',
        'district' => 'D24',
        'division' => 'I24',
        'region' => 'T24',
        'grade_level' => 'F25',
        'section' => 'J25',
        'school_year' => 'S25',
        'adviser' => 'H26',
        'subjects' => [
            'MOTHER TONGUE' => ['Q1' => 'K30', 'Q2' => 'L30', 'Q3' => 'N30', 'Q4' => 'O30', 'final' => 'P30', 'remarks' => 'S30'],
            'FILIPINO' => ['Q1' => 'K31', 'Q2' => 'L31', 'Q3' => 'N31', 'Q4' => 'O31', 'final' => 'P31', 'remarks' => 'S31'],
            'ENGLISH' => ['Q1' => 'K32', 'Q2' => 'L32', 'Q3' => 'N32', 'Q4' => 'O32', 'final' => 'P32', 'remarks' => 'S32'],
            'MATHEMATICS' => ['Q1' => 'K33', 'Q2' => 'L33', 'Q3' => 'N33', 'Q4' => 'O33', 'final' => 'P33', 'remarks' => 'S33'],
            'SCIENCE' => ['Q1' => 'K34', 'Q2' => 'L34', 'Q3' => 'N34', 'Q4' => 'O34', 'final' => 'P34', 'remarks' => 'S34'],
            'ARALING PANLIPUNAN' => ['Q1' => 'K35', 'Q2' => 'L35', 'Q3' => 'N35', 'Q4' => 'O35', 'final' => 'P35', 'remarks' => 'S35'],
            'EDUKASYONG PANTAHANAN AT PANGKABUHAYAN / TLE' => ['Q1' => 'K36', 'Q2' => 'L36', 'Q3' => 'N36', 'Q4' => 'O36', 'final' => 'P36', 'remarks' => 'S36'],
            'MAPEH' => ['Q1' => 'K37', 'Q2' => 'L37', 'Q3' => 'N37', 'Q4' => 'O37', 'final' => 'P37', 'remarks' => 'S37'],
            'MUSIC' => ['Q1' => 'K38', 'Q2' => 'L38', 'Q3' => 'N38', 'Q4' => 'O38'],
            'ARTS' => ['Q1' => 'K39', 'Q2' => 'L39', 'Q3' => 'N39', 'Q4' => 'O39'],
            'PHYSICAL EDUCATION' => ['Q1' => 'K40', 'Q2' => 'L40', 'Q3' => 'N40', 'Q4' => 'O40'],
            'HEALTH' => ['Q1' => 'K41', 'Q2' => 'L41', 'Q3' => 'N41', 'Q4' => 'O41'],
            'EDUKASYON SA PAGPAPAKATAO' => ['Q1' => 'K42', 'Q2' => 'L42', 'Q3' => 'N42', 'Q4' => 'O42', 'final' => 'P42', 'remarks' => 'S42'],
        ],
        'general' => ['final' => 'P45', 'remarks' => 'S45'],
    ],
    2 => [
        'sheet' => 'Front',
        'school' => 'X23',
        'school_id' => 'AW23',
        'district' => 'X24',
        'division' => 'AD24',
        'region' => 'AX24',
        'grade_level' => 'Z25',
        'section' => 'AE25',
        'school_year' => 'AU25',
        'adviser' => 'AC26',
        'subjects' => [
            'MOTHER TONGUE' => ['Q1' => 'AJ30', 'Q2' => 'AM30', 'Q3' => 'AO30', 'Q4' => 'AR30', 'final' => 'AT30', 'remarks' => 'AW30'],
            'FILIPINO' => ['Q1' => 'AJ31', 'Q2' => 'AM31', 'Q3' => 'AO31', 'Q4' => 'AR31', 'final' => 'AT31', 'remarks' => 'AW31'],
            'ENGLISH' => ['Q1' => 'AJ32', 'Q2' => 'AM32', 'Q3' => 'AO32', 'Q4' => 'AR32', 'final' => 'AT32', 'remarks' => 'AW32'],
            'MATHEMATICS' => ['Q1' => 'AJ33', 'Q2' => 'AM33', 'Q3' => 'AO33', 'Q4' => 'AR33', 'final' => 'AT33', 'remarks' => 'AW33'],
            'SCIENCE' => ['Q1' => 'AJ34', 'Q2' => 'AM34', 'Q3' => 'AO34', 'Q4' => 'AR34', 'final' => 'AT34', 'remarks' => 'AW34'],
            'ARALING PANLIPUNAN' => ['Q1' => 'AJ35', 'Q2' => 'AM35', 'Q3' => 'AO35', 'Q4' => 'AR35', 'final' => 'AT35', 'remarks' => 'AW35'],
            'EDUKASYONG PANTAHANAN AT PANGKABUHAYAN / TLE' => ['Q1' => 'AJ36', 'Q2' => 'AM36', 'Q3' => 'AO36', 'Q4' => 'AR36', 'final' => 'AT36', 'remarks' => 'AW36'],
            'MAPEH' => ['Q1' => 'AJ37', 'Q2' => 'AM37', 'Q3' => 'AO37', 'Q4' => 'AR37', 'final' => 'AT37', 'remarks' => 'AW37'],
            'MUSIC' => ['Q1' => 'AJ38', 'Q2' => 'AM38', 'Q3' => 'AO38', 'Q4' => 'AR38'],
            'ARTS' => ['Q1' => 'AJ39', 'Q2' => 'AM39', 'Q3' => 'AO39', 'Q4' => 'AR39'],
            'PHYSICAL EDUCATION' => ['Q1' => 'AJ40', 'Q2' => 'AM40', 'Q3' => 'AO40', 'Q4' => 'AR40'],
            'HEALTH' => ['Q1' => 'AJ41', 'Q2' => 'AM41', 'Q3' => 'AO41', 'Q4' => 'AR41'],
            'EDUKASYON SA PAGPAPAKATAO' => ['Q1' => 'AJ42', 'Q2' => 'AM42', 'Q3' => 'AO42', 'Q4' => 'AR42', 'final' => 'AT42', 'remarks' => 'AW42'],
        ],
        'general' => ['final' => 'AT45', 'remarks' => 'AW45'],
    ],
    3 => [
        'sheet' => 'Front',
        'school' => 'D52',
        'school_id' => 'S52',
        'district' => 'D53',
        'division' => 'I53',
        'region' => 'T53',
        'grade_level' => 'F54',
        'section' => 'J54',
        'school_year' => 'S54',
        'adviser' => 'H55',
        'subjects' => [
            'MOTHER TONGUE' => ['Q1' => 'K60', 'Q2' => 'L60', 'Q3' => 'N60', 'Q4' => 'O60', 'final' => 'P60', 'remarks' => 'S60'],
            'FILIPINO' => ['Q1' => 'K61', 'Q2' => 'L61', 'Q3' => 'N61', 'Q4' => 'O61', 'final' => 'P61', 'remarks' => 'S61'],
            'ENGLISH' => ['Q1' => 'K62', 'Q2' => 'L62', 'Q3' => 'N62', 'Q4' => 'O62', 'final' => 'P62', 'remarks' => 'S62'],
            'MATHEMATICS' => ['Q1' => 'K63', 'Q2' => 'L63', 'Q3' => 'N63', 'Q4' => 'O63', 'final' => 'P63', 'remarks' => 'S63'],
            'SCIENCE' => ['Q1' => 'K64', 'Q2' => 'L64', 'Q3' => 'N64', 'Q4' => 'O64', 'final' => 'P64', 'remarks' => 'S64'],
            'ARALING PANLIPUNAN' => ['Q1' => 'K65', 'Q2' => 'L65', 'Q3' => 'N65', 'Q4' => 'O65', 'final' => 'P65', 'remarks' => 'S65'],
            'EDUKASYONG PANTAHANAN AT PANGKABUHAYAN / TLE' => ['Q1' => 'K66', 'Q2' => 'L66', 'Q3' => 'N66', 'Q4' => 'O66', 'final' => 'P66', 'remarks' => 'S66'],
            'MAPEH' => ['Q1' => 'K67', 'Q2' => 'L67', 'Q3' => 'N67', 'Q4' => 'O67', 'final' => 'P67', 'remarks' => 'S67'],
            'MUSIC' => ['Q1' => 'K68', 'Q2' => 'L68', 'Q3' => 'N68', 'Q4' => 'O68'],
            'ARTS' => ['Q1' => 'K69', 'Q2' => 'L69', 'Q3' => 'N69', 'Q4' => 'O69'],
            'PHYSICAL EDUCATION' => ['Q1' => 'K70', 'Q2' => 'L70', 'Q3' => 'N70', 'Q4' => 'O70'],
            'HEALTH' => ['Q1' => 'K71', 'Q2' => 'L71', 'Q3' => 'N71', 'Q4' => 'O71'],
            'EDUKASYON SA PAGPAPAKATAO' => ['Q1' => 'K72', 'Q2' => 'L72', 'Q3' => 'N72', 'Q4' => 'O72', 'final' => 'P72', 'remarks' => 'S72'],
        ],
        'general' => ['final' => 'P75', 'remarks' => 'S75'],
    ],
    4 => [
        'sheet' => 'Front',
        'school' => 'X52',
        'school_id' => 'AW52',
        'district' => 'X53',
        'division' => 'AD53',
        'region' => 'AX53',
        'grade_level' => 'Z54',
        'section' => 'AE54',
        'school_year' => 'AU54',
        'adviser' => 'AC55',
        'subjects' => [
            'MOTHER TONGUE' => ['Q1' => 'AJ60', 'Q2' => 'AM60', 'Q3' => 'AO60', 'Q4' => 'AR60', 'final' => 'AT60', 'remarks' => 'AW60'],
            'FILIPINO' => ['Q1' => 'AJ61', 'Q2' => 'AM61', 'Q3' => 'AO61', 'Q4' => 'AR61', 'final' => 'AT61', 'remarks' => 'AW61'],
            'ENGLISH' => ['Q1' => 'AJ62', 'Q2' => 'AM62', 'Q3' => 'AO62', 'Q4' => 'AR62', 'final' => 'AT62', 'remarks' => 'AW62'],
            'MATHEMATICS' => ['Q1' => 'AJ63', 'Q2' => 'AM63', 'Q3' => 'AO63', 'Q4' => 'AR63', 'final' => 'AT63', 'remarks' => 'AW63'],
            'SCIENCE' => ['Q1' => 'AJ64', 'Q2' => 'AM64', 'Q3' => 'AO64', 'Q4' => 'AR64', 'final' => 'AT64', 'remarks' => 'AW64'],
            'ARALING PANLIPUNAN' => ['Q1' => 'AJ65', 'Q2' => 'AM65', 'Q3' => 'AO65', 'Q4' => 'AR65', 'final' => 'AT65', 'remarks' => 'AW65'],
            'EDUKASYONG PANTAHANAN AT PANGKABUHAYAN / TLE' => ['Q1' => 'AJ66', 'Q2' => 'AM66', 'Q3' => 'AO66', 'Q4' => 'AR66', 'final' => 'AT66', 'remarks' => 'AW66'],
            'MAPEH' => ['Q1' => 'AJ67', 'Q2' => 'AM67', 'Q3' => 'AO67', 'Q4' => 'AR67', 'final' => 'AT67', 'remarks' => 'AW67'],
            'MUSIC' => ['Q1' => 'AJ68', 'Q2' => 'AM68', 'Q3' => 'AO68', 'Q4' => 'AR68'],
            'ARTS' => ['Q1' => 'AJ69', 'Q2' => 'AM69', 'Q3' => 'AO69', 'Q4' => 'AR69'],
            'PHYSICAL EDUCATION' => ['Q1' => 'AJ70', 'Q2' => 'AM70', 'Q3' => 'AO70', 'Q4' => 'AR70'],
            'HEALTH' => ['Q1' => 'AJ71', 'Q2' => 'AM71', 'Q3' => 'AO71', 'Q4' => 'AR71'],
            'EDUKASYON SA PAGPAPAKATAO' => ['Q1' => 'AJ72', 'Q2' => 'AM72', 'Q3' => 'AO72', 'Q4' => 'AR72', 'final' => 'AT72', 'remarks' => 'AW72'],
        ],
        'general' => ['final' => 'AT75', 'remarks' => 'AW75'],
    ],
    5 => [
        'sheet' => 'Back',
        'school' => 'C3',
        'school_id' => 'O3',
        'district' => 'C4',
        'division' => 'H4',
        'region' => 'P4',
        'grade_level' => 'E5',
        'section' => 'H5',
        'school_year' => 'O5',
        'adviser' => 'G6',
        'subjects' => [
            'MOTHER TONGUE' => ['Q1' => 'H10', 'Q2' => 'I10', 'Q3' => 'J10', 'Q4' => 'K10', 'final' => 'L10', 'remarks' => 'O10'],
            'FILIPINO' => ['Q1' => 'H11', 'Q2' => 'I11', 'Q3' => 'J11', 'Q4' => 'K11', 'final' => 'L11', 'remarks' => 'O11'],
            'ENGLISH' => ['Q1' => 'H12', 'Q2' => 'I12', 'Q3' => 'J12', 'Q4' => 'K12', 'final' => 'L12', 'remarks' => 'O12'],
            'MATHEMATICS' => ['Q1' => 'H13', 'Q2' => 'I13', 'Q3' => 'J13', 'Q4' => 'K13', 'final' => 'L13', 'remarks' => 'O13'],
            'SCIENCE' => ['Q1' => 'H14', 'Q2' => 'I14', 'Q3' => 'J14', 'Q4' => 'K14', 'final' => 'L14', 'remarks' => 'O14'],
            'ARALING PANLIPUNAN' => ['Q1' => 'H15', 'Q2' => 'I15', 'Q3' => 'J15', 'Q4' => 'K15', 'final' => 'L15', 'remarks' => 'O15'],
            'EDUKASYONG PANTAHANAN AT PANGKABUHAYAN / TLE' => ['Q1' => 'H16', 'Q2' => 'I16', 'Q3' => 'J16', 'Q4' => 'K16', 'final' => 'L16', 'remarks' => 'O16'],
            'MAPEH' => ['Q1' => 'H17', 'Q2' => 'I17', 'Q3' => 'J17', 'Q4' => 'K17', 'final' => 'L17', 'remarks' => 'O17'],
            'MUSIC' => ['Q1' => 'H18', 'Q2' => 'I18', 'Q3' => 'J18', 'Q4' => 'K18'],
            'ARTS' => ['Q1' => 'H19', 'Q2' => 'I19', 'Q3' => 'J19', 'Q4' => 'K19'],
            'PHYSICAL EDUCATION' => ['Q1' => 'H20', 'Q2' => 'I20', 'Q3' => 'J20', 'Q4' => 'K20'],
            'HEALTH' => ['Q1' => 'H21', 'Q2' => 'I21', 'Q3' => 'J21', 'Q4' => 'K21'],
            'EDUKASYON SA PAGPAPAKATAO' => ['Q1' => 'H22', 'Q2' => 'I22', 'Q3' => 'J22', 'Q4' => 'K22', 'final' => 'L22', 'remarks' => 'O22'],
        ],
        'general' => ['final' => 'L25', 'remarks' => 'O25'],
    ],
    6 => [
        'sheet' => 'Back',
        'school' => 'T3',
        'school_id' => 'AG3',
        'district' => 'T4',
        'division' => 'AB4',
        'region' => 'AH4',
        'grade_level' => 'V5',
        'section' => 'AB5',
        'school_year' => 'AG5',
        'adviser' => 'V6',
        'subjects' => [
            'MOTHER TONGUE' => ['Q1' => 'AB10', 'Q2' => 'AD10', 'Q3' => 'AE10', 'Q4' => 'AF10', 'final' => 'AG10', 'remarks' => 'AH10'],
            'FILIPINO' => ['Q1' => 'AB11', 'Q2' => 'AD11', 'Q3' => 'AE11', 'Q4' => 'AF11', 'final' => 'AG11', 'remarks' => 'AH11'],
            'ENGLISH' => ['Q1' => 'AB12', 'Q2' => 'AD12', 'Q3' => 'AE12', 'Q4' => 'AF12', 'final' => 'AG12', 'remarks' => 'AH12'],
            'MATHEMATICS' => ['Q1' => 'AB13', 'Q2' => 'AD13', 'Q3' => 'AE13', 'Q4' => 'AF13', 'final' => 'AG13', 'remarks' => 'AH13'],
            'SCIENCE' => ['Q1' => 'AB14', 'Q2' => 'AD14', 'Q3' => 'AE14', 'Q4' => 'AF14', 'final' => 'AG14', 'remarks' => 'AH14'],
            'ARALING PANLIPUNAN' => ['Q1' => 'AB15', 'Q2' => 'AD15', 'Q3' => 'AE15', 'Q4' => 'AF15', 'final' => 'AG15', 'remarks' => 'AH15'],
            'EDUKASYONG PANTAHANAN AT PANGKABUHAYAN / TLE' => ['Q1' => 'AB16', 'Q2' => 'AD16', 'Q3' => 'AE16', 'Q4' => 'AF16', 'final' => 'AG16', 'remarks' => 'AH16'],
            'MAPEH' => ['Q1' => 'AB17', 'Q2' => 'AD17', 'Q3' => 'AE17', 'Q4' => 'AF17', 'final' => 'AG17', 'remarks' => 'AH17'],
            'MUSIC' => ['Q1' => 'AB18', 'Q2' => 'AD18', 'Q3' => 'AE18', 'Q4' => 'AF18'],
            'ARTS' => ['Q1' => 'AB19', 'Q2' => 'AD19', 'Q3' => 'AE19', 'Q4' => 'AF19'],
            'PHYSICAL EDUCATION' => ['Q1' => 'AB20', 'Q2' => 'AD20', 'Q3' => 'AE20', 'Q4' => 'AF20'],
            'HEALTH' => ['Q1' => 'AB21', 'Q2' => 'AD21', 'Q3' => 'AE21', 'Q4' => 'AF21'],
            'EDUKASYON SA PAGPAPAKATAO' => ['Q1' => 'AB22', 'Q2' => 'AD22', 'Q3' => 'AE22', 'Q4' => 'AF22', 'final' => 'AG22', 'remarks' => 'AH22'],
        ],
        'general' => ['final' => 'AG25', 'remarks' => 'AH25'],
    ],
];

$template_path = 'template/sf10_template.xlsx';
$spreadsheet = IOFactory::load($template_path);

$front_sheet = $spreadsheet->getSheetByName('Front');
$front_sheet->setCellValue('E9', htmlspecialchars(strtoupper($pupil['last_name'])));
$front_sheet->setCellValue('R9', htmlspecialchars(strtoupper($pupil['first_name'])));
$front_sheet->setCellValue('AD9', htmlspecialchars(strtoupper($pupil['name_extension'] ?? '')));
$front_sheet->setCellValue('AQ9', htmlspecialchars(strtoupper($pupil['middle_name'] ?? '')));
$front_sheet->setCellValue('J10', htmlspecialchars($pupil['lrn']));
$front_sheet->setCellValue('V10', date('m/d/Y', strtotime($pupil['birthdate'])));
$sex = (strtoupper($pupil['sex']) === 'MALE') ? 'M' : 'F';
$front_sheet->setCellValue('AT10', htmlspecialchars($sex));

foreach ($history as $level_num => $data) {
    if (!isset($mappings[$level_num]) || empty($data['grades']) || $level_num > $current_grade_level) {
        continue;
    }
    $map = $mappings[$level_num];
    $sheet = $spreadsheet->getSheetByName($map['sheet']);
    $sheet->setCellValue($map['school'], htmlspecialchars('LIBON EAST CENTRAL SCHOOL'));
    $sheet->setCellValue($map['school_id'], htmlspecialchars('111762'));
    $sheet->setCellValue($map['district'], htmlspecialchars('LIBON EAST'));
    $sheet->setCellValue($map['division'], htmlspecialchars('ALBAY'));
    $sheet->setCellValue($map['region'], htmlspecialchars('V (BICOL)'));
    $sheet->setCellValue($map['grade_level'], htmlspecialchars($level_num));
    $sheet->setCellValue($map['section'], htmlspecialchars(strtoupper($data['section'])));
    $sheet->setCellValue($map['school_year'], htmlspecialchars($data['school_year']));
    $sheet->setCellValue($map['adviser'], htmlspecialchars(ucwords(strtolower(trim($data['teacher'])))));

    $top_subjects = [];
    $components = [];
    $sub_res = $conn->query("SELECT subject_id, subject_name, start_quarter, parent_subject_id
                             FROM subjects
                             WHERE grade_level_id = {$data['grade_level_id']}
                             AND sy_id = {$data['sy_id']}
                             ORDER BY display_order, subject_name ASC");
    while ($sub = $sub_res->fetch_assoc()) {
        if ($sub['parent_subject_id']) {
            $components[$sub['parent_subject_id']][] = $sub;
        } else {
            $top_subjects[] = $sub;
        }
    }

    $general_finals = [];
    $all_grades_complete = true;
    $required_subjects = 0;
    $required_quarters = ['Q1', 'Q2', 'Q3', 'Q4'];
    $grades = $data['grades'];

    foreach ($top_subjects as $sub) {
        $sid = $sub['subject_id'];
        $sub_name_upper = strtoupper($sub['subject_name']);
        $has_comp = isset($components[$sid]);
        $start_quarter = $sub['start_quarter'] ?? 'Q1';

        if (($level_num == 1 || $level_num == 2) && $sub_name_upper == "SCIENCE") continue;
        if (($level_num >= 4 && $level_num <= 6) && $sub_name_upper == "MOTHER TONGUE") continue;
        if (($level_num <= 3) && $sub_name_upper == "EDUKASYONG PANTAHANAN AT PANGKABUHAYAN / TLE") continue;
        $required_subjects++;
        $q_grades = ['Q1' => null, 'Q2' => null, 'Q3' => null, 'Q4' => null];
        $final = null;
        $rem = '';
        $subject_required_quarters = array_slice($required_quarters, array_search($start_quarter, $required_quarters) ?? 0);
        $subject_grades_complete = true;

        if ($has_comp) {
            $q_sums = ['Q1' => 0, 'Q2' => 0, 'Q3' => 0, 'Q4' => 0];
            $q_counts = ['Q1' => 0, 'Q2' => 0, 'Q3' => 0, 'Q4' => 0];
            foreach ($components[$sid] as $comp) {
                $cid = $comp['subject_id'];
                $comp_name_upper = strtoupper($comp['subject_name']);
                $comp_start_quarter = $comp['start_quarter'] ?? 'Q1';
                $comp_required_quarters = array_slice($required_quarters, array_search($comp_start_quarter, $required_quarters) ?? 0);
                $cq_grades = $grades[$comp_name_upper] ?? [];
                foreach ($comp_required_quarters as $q) {
                    if (isset($cq_grades[$q]) && $cq_grades[$q] !== null && $cq_grades[$q] !== '') {
                        $q_sums[$q] += (int)$cq_grades[$q];
                        $q_counts[$q]++;
                    } else {
                        $subject_grades_complete = false;
                        $all_grades_complete = false;
                    }
                }

                if (isset($map['subjects'][$comp_name_upper])) {
                    $comp_cells = $map['subjects'][$comp_name_upper];
                    foreach ($comp_required_quarters as $q) {
                        if (isset($cq_grades[$q]) && $cq_grades[$q] !== null && $cq_grades[$q] !== '' && isset($comp_cells[$q])) {
                            $sheet->setCellValue($comp_cells[$q], (int)$cq_grades[$q]);
                        }
                    }
                }
            }
            foreach ($subject_required_quarters as $q) {
                if ($q_counts[$q] > 0) {
                    $q_grades[$q] = round($q_sums[$q] / $q_counts[$q]);
                } else {
                    $q_grades[$q] = null;
                }
            }
            if ($subject_grades_complete && $q_counts[$subject_required_quarters[0]] == count($components[$sid])) {
                $valid_grades = array_filter($q_grades, function($grade) { return $grade !== null; });
                if (count($valid_grades) == count($subject_required_quarters)) {
                    $final = round(array_sum($valid_grades) / count($valid_grades));
                    $rem = $final >= 75 ? 'Passed' : 'Failed';
                    $general_finals[] = $final;
                }
            } else {
                $subject_grades_complete = false;
                $all_grades_complete = false;
            }
        } else {
            $q_grades_from_db = $grades[$sub_name_upper] ?? [];
            foreach ($subject_required_quarters as $q) {
                if (isset($q_grades_from_db[$q]) && $q_grades_from_db[$q] !== null && $q_grades_from_db[$q] !== '') {
                    $q_grades[$q] = (int)$q_grades_from_db[$q];
                } else {
                    $q_grades[$q] = null;
                    $subject_grades_complete = false;
                    $all_grades_complete = false;
                }
            }
            $valid_grades = array_filter($q_grades, function($grade) { return $grade !== null; });
            if (count($valid_grades) == count($subject_required_quarters)) {
                $final = round(array_sum($valid_grades) / count($valid_grades));
                $rem = $final >= 75 ? 'Passed' : 'Failed';
                $general_finals[] = $final;
            }
        }

        if (isset($map['subjects'][$sub_name_upper])) {
            $cells = $map['subjects'][$sub_name_upper];
            foreach ($subject_required_quarters as $q) {
                if (isset($q_grades[$q]) && $q_grades[$q] !== null && isset($cells[$q])) {
                    $sheet->setCellValue($cells[$q], (int)$q_grades[$q]);
                }
            }
            if ($subject_grades_complete && isset($cells['final']) && $final !== null) {
                $sheet->setCellValue($cells['final'], (int)$final);
            }
            if ($subject_grades_complete && isset($cells['remarks'])) {
                $sheet->setCellValue($cells['remarks'], htmlspecialchars($rem));
            }
        }
    }

    $general_avg = '';
    $overall_rem = '';
    if ($all_grades_complete && count($general_finals) == $required_subjects) {
        $general_avg = round(array_sum($general_finals) / count($general_finals));
        $num_fails = 0;
        foreach ($general_finals as $final) {
            if ($final < 75) $num_fails++;
        }
        if ($num_fails >= 3) {
            $overall_rem = 'Retained';
        } elseif ($num_fails >= 1) {
            $overall_rem = 'Promoted';
        } else {
            if ($general_avg >= 98) {
                $overall_rem = 'Promoted';
            } elseif ($general_avg >= 95) {
                $overall_rem = 'Promoted';
            } elseif ($general_avg >= 90) {
                $overall_rem = 'Promoted';
            } else {
                $overall_rem = 'Promoted';
            }
        }
    }
    $display_rem = ($overall_rem === '') ? '' : $overall_rem;
    if (isset($map['general'])) {
        $gen_cells = $map['general'];
        if ($general_avg !== '') {
            $sheet->setCellValue($gen_cells['final'], (int)$general_avg);
        }
        $sheet->setCellValue($gen_cells['remarks'], htmlspecialchars($display_rem));
        if ($display_rem !== '') {
            $sheet->getStyle($gen_cells['remarks'])->getFont()->setBold(true);
        }
    }
}

// Construct full name for filename, handling empty middle_name and name_extension
$full_name_parts = [
    strtoupper($pupil['last_name']),
    strtoupper($pupil['first_name']),
    !empty($pupil['middle_name']) ? strtoupper($pupil['middle_name']) : '',
    !empty($pupil['name_extension']) ? strtoupper($pupil['name_extension']) : ''
];
$full_name_clean = implode('-', array_filter(array_map(function($part) {
    return preg_replace('/[^a-zA-Z0-9]/', '', $part);
}, $full_name_parts)));
$filename = "SF10-{$full_name_clean}.xlsx";

$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"{$filename}\"");
header('Cache-Control: max-age=0');
header('Expires: 0');
header('Pragma: public');
$writer->save('php://output');
exit;
?>