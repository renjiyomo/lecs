<?php
require 'vendor/autoload.php';
include 'lecs_db.php';
session_start();

use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\Shared\Converter;

if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 't') {
    header("Location: /lecs/Landing/Login/login.php");
    exit;
}

$teacher_id = intval($_SESSION['teacher_id']);
$sy_id = isset($_GET['sy_id']) ? intval($_GET['sy_id']) : 0;

if ($sy_id <= 0) {
    die("Invalid school year ID.");
}

// Custom rounding function: round only if decimal part is >= 0.5 (DepEd policy)
function customRound($number) {
    $floor = floor($number);
    $decimal = $number - $floor;
    return $decimal >= 0.5 ? ceil($number) : $floor;
}

// Function to convert number to Roman numeral
function toRoman($number) {
    $map = [
        1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD',
        100 => 'C', 90 => 'XC', 50 => 'L', 40 => 'XL',
        10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I'
    ];
    $result = '';
    foreach ($map as $value => $roman) {
        while ($number >= $value) {
            $result .= $roman;
            $number -= $value;
        }
    }
    return $result;
}

// Function to convert position name to include Roman numerals
function formatPosition($position_name) {
    // Split the position name (e.g., "Principal II" -> ["Principal", "II"])
    $parts = explode(' ', trim($position_name));
    if (count($parts) > 1 && is_numeric($parts[1])) {
        // Convert numeric part to Roman numeral
        $parts[1] = toRoman(intval($parts[1]));
        return strtoupper(implode(' ', $parts));
    }
    // If no numeric part or already in Roman numerals, return as is
    return strtoupper($position_name);
}

// Get school year
$sy_stmt = $conn->prepare("SELECT school_year FROM school_years WHERE sy_id = ?");
$sy_stmt->bind_param("i", $sy_id);
$sy_stmt->execute();
$sy_result = $sy_stmt->get_result()->fetch_assoc();
$school_year = $sy_result['school_year'] ?? 'Unknown';

// Get teacher name
$teacher_stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name) AS teacher_name FROM teachers WHERE teacher_id = ?");
$teacher_stmt->bind_param("i", $teacher_id);
$teacher_stmt->execute();
$teacher_result = $teacher_stmt->get_result()->fetch_assoc();
$teacher_full_name = strtoupper(htmlspecialchars($teacher_result['teacher_name'] ?? ''));

// Get principal name and position (latest based on start_date)
$principal_stmt = $conn->prepare("
    SELECT CONCAT(t.first_name, ' ', COALESCE(t.middle_name, ''), ' ', t.last_name) AS principal_name, p.position_name AS principal_position
    FROM teachers t
    JOIN teacher_positions tp ON t.teacher_id = tp.teacher_id
    JOIN positions p ON tp.position_id = p.position_id
    WHERE tp.position_id IN (13,14,15,16)
    ORDER BY tp.start_date DESC
    LIMIT 1
");
$principal_stmt->execute();
$principal_result = $principal_stmt->get_result()->fetch_assoc();
$principal_full_name = strtoupper(htmlspecialchars($principal_result['principal_name'] ?? ''));
$principal_position = formatPosition(htmlspecialchars($principal_result['principal_position'] ?? ''));

// Get pupils
$pupil_sql = "SELECT p.pupil_id, p.first_name, p.last_name, p.middle_name, p.age, p.sex,
                     s.section_name, s.grade_level_id, p.lrn
              FROM pupils p
              JOIN sections s ON p.section_id = s.section_id
              WHERE p.teacher_id = ? AND p.sy_id = ?";
$pupil_stmt = $conn->prepare($pupil_sql);
$pupil_stmt->bind_param("ii", $teacher_id, $sy_id);
$pupil_stmt->execute();
$pupils = $pupil_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($pupils)) {
    die("No pupils found for this school year.");
}

// Get subjects
$subjects = [];
$components = [];
$subject_sql = "SELECT subject_id, subject_name, parent_subject_id, start_quarter
                FROM subjects WHERE grade_level_id = ? AND sy_id = ?";
$subject_stmt = $conn->prepare($subject_sql);

foreach ($pupils as $pupil) {
    $subject_stmt->bind_param("ii", $pupil['grade_level_id'], $sy_id);
    $subject_stmt->execute();
    $sub_result = $subject_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($sub_result as $sub) {
        $subjects[$pupil['pupil_id']][$sub['subject_id']] = $sub;
        if ($sub['parent_subject_id']) {
            $components[$sub['parent_subject_id']][] = $sub;
        }
    }
}

// Get grades
$quarters_order = ["Q1" => 1, "Q2" => 2, "Q3" => 3, "Q4" => 4];
$grades_map = [];
$pupil_ids = array_column($pupils, 'pupil_id');
$id_list = implode(",", array_map('intval', $pupil_ids));
$grade_sql = "SELECT pupil_id, subject_id, quarter, grade
              FROM grades WHERE pupil_id IN ($id_list) AND sy_id = ?";
$grade_stmt = $conn->prepare($grade_sql);
$grade_stmt->bind_param("i", $sy_id);
$grade_stmt->execute();
$grade_result = $grade_stmt->get_result();
while ($g = $grade_result->fetch_assoc()) {
    $grades_map[$g['pupil_id']][$g['subject_id']][$g['quarter']] = $g['grade'];
}

// Prepare ZIP for multiple files
$zip = new ZipArchive();
$zipFile = sys_get_temp_dir() . "/sf9_reports_" . date('Ymd') . ".zip";
$temp_files = [];

foreach ($pupils as $pupil) {
    $pupil_id = $pupil['pupil_id'];
    $grade_level_id = $pupil['grade_level_id'];
    $pupil_subjects = $subjects[$pupil_id] ?? [];
    
    // Initialize grades
    $grades = [
        'Filipino' => ['q1' => '', 'q2' => '', 'q3' => '', 'q4' => '', 'final' => '', 'remarks' => ''],
        'English' => ['q1' => '', 'q2' => '', 'q3' => '', 'q4' => '', 'final' => '', 'remarks' => ''],
        'Mathematics' => ['q1' => '', 'q2' => '', 'q3' => '', 'q4' => '', 'final' => '', 'remarks' => ''],
        'Science' => ['q1' => '', 'q2' => '', 'q3' => '', 'q4' => '', 'final' => '', 'remarks' => ''],
        'Araling Panlipunan' => ['q1' => '', 'q2' => '', 'q3' => '', 'q4' => '', 'final' => '', 'remarks' => ''],
        'Edukasyon sa Pagpapakatao' => ['q1' => '', 'q2' => '', 'q3' => '', 'q4' => '', 'final' => '', 'remarks' => ''],
        'Edukasyong Pantahanan at Pangkabuhayan / TLE' => ['q1' => '', 'q2' => '', 'q3' => '', 'q4' => '', 'final' => '', 'remarks' => ''],
        'MAPEH' => ['q1' => '', 'q2' => '', 'q3' => '', 'q4' => '', 'final' => '', 'remarks' => ''],
        'Music' => ['q1' => '', 'q2' => '', 'q3' => '', 'q4' => ''],
        'Arts' => ['q1' => '', 'q2' => '', 'q3' => '', 'q4' => ''],
        'Physical Education' => ['q1' => '', 'q2' => '', 'q3' => '', 'q4' => ''],
        'Health' => ['q1' => '', 'q2' => '', 'q3' => '', 'q4' => '']
    ];
    
    $general_avg = ['final' => '', 'remarks' => ''];
    
    foreach ($pupil_subjects as $subject_id => $sub) {
        $subject_name = $sub['subject_name'];
        $start_q = $sub['start_quarter'] ?? 'Q1';
        $start_num = $quarters_order[$start_q];
        $quarters = $grades_map[$pupil_id][$subject_id] ?? [];
        
        if (isset($components[$subject_id])) {
            // Handle MAPEH composite
            $comp_grades = ['Q1' => [], 'Q2' => [], 'Q3' => [], 'Q4' => []];
            foreach ($components[$subject_id] as $comp) {
                $comp_id = $comp['subject_id'];
                $comp_quarters = $grades_map[$pupil_id][$comp_id] ?? [];
                foreach ($quarters_order as $q => $q_num) {
                    if ($q_num >= $start_num && isset($comp_quarters[$q])) {
                        $comp_grades[$q][] = customRound($comp_quarters[$q]);
                    }
                }
            }
            foreach ($quarters_order as $q => $q_num) {
                if ($q_num >= $start_num && !empty($comp_grades[$q])) {
                    $avg = customRound(array_sum($comp_grades[$q]) / count($comp_grades[$q]));
                    $grades['MAPEH'][strtolower($q)] = $avg;
                }
            }
            // Check if all quarters for MAPEH components are graded
            $all_components_complete = true;
            foreach ($components[$subject_id] as $comp) {
                $comp_id = $comp['subject_id'];
                $comp_quarters = $grades_map[$pupil_id][$comp_id] ?? [];
                foreach ($quarters_order as $q => $q_num) {
                    if ($q_num >= $start_num && !isset($comp_quarters[$q])) {
                        $all_components_complete = false;
                        break;
                    }
                }
                if (!$all_components_complete) break;
            }
            if ($all_components_complete && !empty($comp_grades['Q1']) && !empty($comp_grades['Q2']) && !empty($comp_grades['Q3']) && !empty($comp_grades['Q4'])) {
                $valid_quarters = array_filter($comp_grades, function($q) { return !empty($q); });
                $quarter_avgs = array_map(function($q) { return array_sum($q) / count($q); }, $valid_quarters);
                if ($quarter_avgs) {
                    $final = customRound(array_sum($quarter_avgs) / count($quarter_avgs));
                    $grades['MAPEH']['final'] = $final;
                    $grades['MAPEH']['remarks'] = $final >= 75 ? 'Passed' : 'Failed';
                }
            }
        } else {
            // Handle individual subjects and MAPEH components
            $map = [
                'Filipino' => 'Filipino',
                'English' => 'English',
                'Mathematics' => 'Mathematics',
                'Science' => 'Science',
                'Araling Panlipunan' => 'Araling Panlipunan',
                'Edukasyon sa Pagpapakatao' => 'Edukasyon sa Pagpapakatao',
                'Edukasyong Pantahanan at Pangkabuhayan / TLE' => 'Edukasyong Pantahanan at Pangkabuhayan / TLE',
                'Music' => 'Music',
                'Arts' => 'Arts',
                'Physical Education' => 'Physical Education',
                'Health' => 'Health'
            ];
            $key = $map[$subject_name] ?? null;
            if ($key) {
                foreach ($quarters_order as $q => $q_num) {
                    if ($q_num >= $start_num && isset($quarters[$q])) {
                        $grades[$key][strtolower($q)] = customRound($quarters[$q]);
                    }
                }
                $valid_quarters = array_filter($quarters, function($g, $q) use ($quarters_order, $start_num) {
                    return $quarters_order[$q] >= $start_num;
                }, ARRAY_FILTER_USE_BOTH);
                // Check if all quarters are graded for non-MAPEH components
                $all_quarters_complete = true;
                foreach ($quarters_order as $q => $q_num) {
                    if ($q_num >= $start_num && !isset($quarters[$q])) {
                        $all_quarters_complete = false;
                        break;
                    }
                }
                if ($all_quarters_complete && !in_array($key, ['Music', 'Arts', 'Physical Education', 'Health'])) {
                    $final = customRound(array_sum($valid_quarters) / count($valid_quarters));
                    $grades[$key]['final'] = $final;
                    $grades[$key]['remarks'] = $final >= 75 ? 'Passed' : 'Failed';
                }
            }
        }
    }
    
    // Calculate general average only if all learning areas have final grades
    $final_grades = array_filter(array_column($grades, 'final'), function($g) { return $g !== ''; });
    $core_subjects = [
        'Filipino', 'English', 'Mathematics', 'Science', 'Araling Panlipunan',
        'Edukasyon sa Pagpapakatao', 'Edukasyong Pantahanan at Pangkabuhayan / TLE', 'MAPEH'
    ];
    $all_subjects_complete = true;
    foreach ($core_subjects as $subject) {
        if ($grades[$subject]['final'] === '') {
            $all_subjects_complete = false;
            break;
        }
    }
    if ($all_subjects_complete && count($final_grades) === count($core_subjects)) {
        $avg = customRound(array_sum($final_grades) / count($final_grades));
        $general_avg['final'] = $avg;
        $num_fails = count(array_filter($final_grades, function($g) { return $g < 75; }));
        if ($num_fails >= 3) {
            $general_avg['remarks'] = 'Retained';
        } elseif ($num_fails >= 1) {
            $general_avg['remarks'] = 'Promoted';
        } else {
            $general_avg['remarks'] = 'Promoted';
        }
    }
    
    // Prepare template
    $templateFile = __DIR__ . "/template/report_card_template.docx";
    $tempFile = sys_get_temp_dir() . "/sf9_" . uniqid() . ".docx";
    copy($templateFile, $tempFile);
    $templateProcessor = new TemplateProcessor($tempFile);
    
    // Set pupil information
    $mi = $pupil['middle_name'] ? strtoupper(substr($pupil['middle_name'], 0, 1)) . "." : "";
    $full_name = strtoupper(htmlspecialchars($pupil['last_name'] . ", " . $pupil['first_name'] . " " . $mi));
    $templateProcessor->setValue('school_year', htmlspecialchars($school_year));
    $templateProcessor->setValue('name', $full_name);
    $templateProcessor->setValue('age', htmlspecialchars($pupil['age']));
    $templateProcessor->setValue('sex', htmlspecialchars($pupil['sex']));
    $templateProcessor->setValue('grade_level1', htmlspecialchars($pupil['grade_level_id']));
    $templateProcessor->setValue('section', htmlspecialchars($pupil['section_name']));
    $templateProcessor->setValue('lrn', htmlspecialchars($pupil['lrn']));
    $templateProcessor->setValue('teacher', $teacher_full_name);
    $templateProcessor->setValue('principal', $principal_full_name);
    $templateProcessor->setValue('principal_position', $principal_position);
    $templateProcessor->setValue('grade_level2', toRoman($pupil['grade_level_id']));
    
    // Set grades with font size 11
    $fontSize = 11 * 2; // Word uses half-points
    $fontXml = '<w:r><w:rPr><w:sz w:val="' . $fontSize . '"/></w:rPr><w:t>%s</w:t></w:r>';
    
    $templateProcessor->setValue('f1', sprintf($fontXml, $grades['Filipino']['q1']));
    $templateProcessor->setValue('f2', sprintf($fontXml, $grades['Filipino']['q2']));
    $templateProcessor->setValue('f3', sprintf($fontXml, $grades['Filipino']['q3']));
    $templateProcessor->setValue('f4', sprintf($fontXml, $grades['Filipino']['q4']));
    $templateProcessor->setValue('f_final_rating', sprintf($fontXml, $grades['Filipino']['final']));
    $templateProcessor->setValue('f_remarks', sprintf($fontXml, $grades['Filipino']['remarks']));
    
    $templateProcessor->setValue('e1', sprintf($fontXml, $grades['English']['q1']));
    $templateProcessor->setValue('e2', sprintf($fontXml, $grades['English']['q2']));
    $templateProcessor->setValue('e3', sprintf($fontXml, $grades['English']['q3']));
    $templateProcessor->setValue('e4', sprintf($fontXml, $grades['English']['q4']));
    $templateProcessor->setValue('e_final_rating', sprintf($fontXml, $grades['English']['final']));
    $templateProcessor->setValue('e_remarks', sprintf($fontXml, $grades['English']['remarks']));
    
    $templateProcessor->setValue('m1', sprintf($fontXml, $grades['Mathematics']['q1']));
    $templateProcessor->setValue('m2', sprintf($fontXml, $grades['Mathematics']['q2']));
    $templateProcessor->setValue('m3', sprintf($fontXml, $grades['Mathematics']['q3']));
    $templateProcessor->setValue('m4', sprintf($fontXml, $grades['Mathematics']['q4']));
    $templateProcessor->setValue('m_final_rating', sprintf($fontXml, $grades['Mathematics']['final']));
    $templateProcessor->setValue('m_remarks', sprintf($fontXml, $grades['Mathematics']['remarks']));
    
    $templateProcessor->setValue('s1', sprintf($fontXml, $grades['Science']['q1']));
    $templateProcessor->setValue('s2', sprintf($fontXml, $grades['Science']['q2']));
    $templateProcessor->setValue('s3', sprintf($fontXml, $grades['Science']['q3']));
    $templateProcessor->setValue('s4', sprintf($fontXml, $grades['Science']['q4']));
    $templateProcessor->setValue('s_final_rating', sprintf($fontXml, $grades['Science']['final']));
    $templateProcessor->setValue('s_remarks', sprintf($fontXml, $grades['Science']['remarks']));
    
    $templateProcessor->setValue('ap1', sprintf($fontXml, $grades['Araling Panlipunan']['q1']));
    $templateProcessor->setValue('ap2', sprintf($fontXml, $grades['Araling Panlipunan']['q2']));
    $templateProcessor->setValue('ap3', sprintf($fontXml, $grades['Araling Panlipunan']['q3']));
    $templateProcessor->setValue('ap4', sprintf($fontXml, $grades['Araling Panlipunan']['q4']));
    $templateProcessor->setValue('ap_final_rating', sprintf($fontXml, $grades['Araling Panlipunan']['final']));
    $templateProcessor->setValue('ap_remarks', sprintf($fontXml, $grades['Araling Panlipunan']['remarks']));
    
    $templateProcessor->setValue('ep1', sprintf($fontXml, $grades['Edukasyon sa Pagpapakatao']['q1']));
    $templateProcessor->setValue('ep2', sprintf($fontXml, $grades['Edukasyon sa Pagpapakatao']['q2']));
    $templateProcessor->setValue('ep3', sprintf($fontXml, $grades['Edukasyon sa Pagpapakatao']['q3']));
    $templateProcessor->setValue('ep4', sprintf($fontXml, $grades['Edukasyon sa Pagpapakatao']['q4']));
    $templateProcessor->setValue('ep_final_rating', sprintf($fontXml, $grades['Edukasyon sa Pagpapakatao']['final']));
    $templateProcessor->setValue('ep_remarks', sprintf($fontXml, $grades['Edukasyon sa Pagpapakatao']['remarks']));
    
    $templateProcessor->setValue('tle1', sprintf($fontXml, $grades['Edukasyong Pantahanan at Pangkabuhayan / TLE']['q1']));
    $templateProcessor->setValue('tle2', sprintf($fontXml, $grades['Edukasyong Pantahanan at Pangkabuhayan / TLE']['q2']));
    $templateProcessor->setValue('tle3', sprintf($fontXml, $grades['Edukasyong Pantahanan at Pangkabuhayan / TLE']['q3']));
    $templateProcessor->setValue('tle4', sprintf($fontXml, $grades['Edukasyong Pantahanan at Pangkabuhayan / TLE']['q4']));
    $templateProcessor->setValue('tle_final_rating', sprintf($fontXml, $grades['Edukasyong Pantahanan at Pangkabuhayan / TLE']['final']));
    $templateProcessor->setValue('tle_remarks', sprintf($fontXml, $grades['Edukasyong Pantahanan at Pangkabuhayan / TLE']['remarks']));
    
    $templateProcessor->setValue('ph1', sprintf($fontXml, $grades['MAPEH']['q1']));
    $templateProcessor->setValue('ph2', sprintf($fontXml, $grades['MAPEH']['q2']));
    $templateProcessor->setValue('ph3', sprintf($fontXml, $grades['MAPEH']['q3']));
    $templateProcessor->setValue('ph4', sprintf($fontXml, $grades['MAPEH']['q4']));
    $templateProcessor->setValue('ph_final_rating', sprintf($fontXml, $grades['MAPEH']['final']));
    $templateProcessor->setValue('ph_remarks', sprintf($fontXml, $grades['MAPEH']['remarks']));
    
    $templateProcessor->setValue('ms1', sprintf($fontXml, $grades['Music']['q1']));
    $templateProcessor->setValue('ms2', sprintf($fontXml, $grades['Music']['q2']));
    $templateProcessor->setValue('ms3', sprintf($fontXml, $grades['Music']['q3']));
    $templateProcessor->setValue('ms4', sprintf($fontXml, $grades['Music']['q4']));
    
    $templateProcessor->setValue('art1', sprintf($fontXml, $grades['Arts']['q1']));
    $templateProcessor->setValue('art2', sprintf($fontXml, $grades['Arts']['q2']));
    $templateProcessor->setValue('art3', sprintf($fontXml, $grades['Arts']['q3']));
    $templateProcessor->setValue('art4', sprintf($fontXml, $grades['Arts']['q4']));
    
    $templateProcessor->setValue('pe1', sprintf($fontXml, $grades['Physical Education']['q1']));
    $templateProcessor->setValue('pe2', sprintf($fontXml, $grades['Physical Education']['q2']));
    $templateProcessor->setValue('pe3', sprintf($fontXml, $grades['Physical Education']['q3']));
    $templateProcessor->setValue('pe4', sprintf($fontXml, $grades['Physical Education']['q4']));
    
    $templateProcessor->setValue('hlt1', sprintf($fontXml, $grades['Health']['q1']));
    $templateProcessor->setValue('hlt2', sprintf($fontXml, $grades['Health']['q2']));
    $templateProcessor->setValue('hlt3', sprintf($fontXml, $grades['Health']['q3']));
    $templateProcessor->setValue('hlt4', sprintf($fontXml, $grades['Health']['q4']));
    
    $templateProcessor->setValue('ga_final_rating', sprintf($fontXml, $general_avg['final']));
    $templateProcessor->setValue('ga_remarks', sprintf($fontXml, $general_avg['remarks']));
    
    // Save file
    $filename = "SF9_{$pupil['last_name']}_{$pupil['first_name']}.docx";
    $templateProcessor->saveAs($tempFile);
    $temp_files[] = [$tempFile, $filename];
}

// Output as ZIP if multiple pupils
if (count($temp_files) > 1) {
    if ($zip->open($zipFile, ZipArchive::CREATE) === true) {
        foreach ($temp_files as $file) {
            $zip->addFile($file[0], $file[1]);
        }
        $zip->close();
        
        header("Content-Type: application/zip");
        header("Content-Disposition: attachment; filename=\"SF9_Reports_SY{$school_year}_" . date('Ymd') . ".zip\"");
        readfile($zipFile);
        
        // Clean up
        @unlink($zipFile);
        foreach ($temp_files as $file) {
            @unlink($file[0]);
        }
        exit;
    }
} else {
    // Single file download
    $file = $temp_files[0];
    header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
    header("Content-Disposition: attachment; filename=\"{$file[1]}\"");
    readfile($file[0]);
    @unlink($file[0]);
    exit;
}
?>