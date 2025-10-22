<?php
require 'vendor/autoload.php';
include 'lecs_db.php';
session_start();

use PhpOffice\PhpWord\TemplateProcessor;

if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 't') {
    header("Location: /lecs/Landing/Login/login.php");
    exit;
}

$teacher_id = intval($_SESSION['teacher_id']);
$sy_id      = isset($_POST['sy_id']) ? intval($_POST['sy_id']) : 0;
$issue_date = isset($_POST['issue_date']) ? $_POST['issue_date'] : date('Y-m-d');

if (!DateTime::createFromFormat('Y-m-d', $issue_date)) {
    die("Invalid date format.");
}

// Custom rounding function: round only if decimal part is >= 0.5 (DepEd policy)
function customRound($number) {
    $floor = floor($number);
    $decimal = $number - $floor;
    return $decimal >= 0.5 ? ceil($number) : $floor;
}

// --- School year ---
$sy_stmt = $conn->prepare("SELECT school_year FROM school_years WHERE sy_id=?");
$sy_stmt->bind_param("i", $sy_id);
$sy_stmt->execute();
$sy_result = $sy_stmt->get_result()->fetch_assoc();
$school_year = $sy_result['school_year'] ?? 'Unknown';

// --- Teacher name ---
$teacher_stmt = $conn->prepare("SELECT first_name, middle_name, last_name FROM teachers WHERE teacher_id=?");
$teacher_stmt->bind_param("i", $teacher_id);
$teacher_stmt->execute();
$teacher_result = $teacher_stmt->get_result()->fetch_assoc();
$teacher_middle_initial = $teacher_result['middle_name'] ? strtoupper(substr($teacher_result['middle_name'], 0, 1)) . "." : "";
$teacher_full_name = strtoupper(htmlspecialchars($teacher_result['first_name'] . ' ' . $teacher_middle_initial . ' ' . $teacher_result['last_name']));

// --- Principal name (latest based on start_date) ---
$principal_stmt = $conn->prepare("
    SELECT t.first_name, t.middle_name, t.last_name
    FROM teachers t
    JOIN teacher_positions tp ON t.teacher_id = tp.teacher_id
    WHERE tp.position_id IN (13,14,15,16)
    ORDER BY tp.start_date DESC
    LIMIT 1
");
$principal_stmt->execute();
$principal_result = $principal_stmt->get_result()->fetch_assoc();
$principal_middle_initial = $principal_result['middle_name'] ? strtoupper(substr($principal_result['middle_name'], 0, 1)) . "." : "";
$principal_full_name = strtoupper(htmlspecialchars($principal_result['first_name'] . ' ' . $principal_middle_initial . ' ' . $principal_result['last_name']));

// --- Pupils ---
$sql = "SELECT p.pupil_id,p.first_name,p.last_name,p.middle_name,
               s.section_name,s.grade_level_id
        FROM pupils p
        JOIN sections s ON p.section_id=s.section_id
        WHERE p.teacher_id=? AND p.sy_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii",$teacher_id,$sy_id);
$stmt->execute();
$pupils = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$quarters_order = ["Q1"=>1,"Q2"=>2,"Q3"=>3,"Q4"=>4];
$honors_pupils  = [];

// --- Compute honors ---
foreach ($pupils as $p) {
    $pupil_id       = $p['pupil_id'];
    $grade_level_id = $p['grade_level_id'];

    $sub_stmt = $conn->prepare("SELECT subject_id,parent_subject_id,start_quarter 
                                FROM subjects WHERE grade_level_id=? AND sy_id=?");
    $sub_stmt->bind_param("ii",$grade_level_id,$sy_id);
    $sub_stmt->execute();
    $subjects = $sub_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $components=[]; $pupil_subjects=[];
    foreach($subjects as $sub){
        if($sub['parent_subject_id']) $components[$sub['parent_subject_id']][]=$sub;
        else $pupil_subjects[$sub['subject_id']]=$sub;
    }

    $gstmt = $conn->prepare("SELECT subject_id,quarter,grade FROM grades WHERE pupil_id=? AND sy_id=?");
    $gstmt->bind_param("ii",$pupil_id,$sy_id);
    $gstmt->execute();
    $grades = $gstmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $grades_map=[];
    foreach($grades as $g){ $grades_map[$g['subject_id']][$g['quarter']]=$g['grade']; }

    $pupil_grades=[]; $all_empty=true; $has_incomplete=false;

    foreach($pupil_subjects as $subject_id=>$sub){
        $start_q = $sub['start_quarter'] ?? 'Q1';
        $start_num = $quarters_order[$start_q];
        $expected_quarters = 4 - $start_num + 1;

        if(isset($components[$subject_id])){
            $all_comp_present = true;
            $comp_finals = [];
            foreach($components[$subject_id] as $comp){
                $comp_id = $comp['subject_id'];
                $comp_start_q = $comp['start_quarter'] ?? 'Q1';
                $comp_start_num = $quarters_order[$comp_start_q];
                $comp_expected = 4 - $comp_start_num + 1;
                $comp_quarters = $grades_map[$comp_id] ?? [];
                $filtered = array_filter($comp_quarters, function($g, $q) use ($quarters_order, $comp_start_num) {
                    return $quarters_order[$q] >= $comp_start_num;
                }, ARRAY_FILTER_USE_BOTH);
                if (count($filtered) == $comp_expected) {
                    $comp_avg = array_sum($filtered) / $comp_expected;
                    $comp_finals[] = customRound($comp_avg);
                } else {
                    $all_comp_present = false;
                    $has_incomplete = true;
                }
            }
            if ($all_comp_present && count($comp_finals) == count($components[$subject_id])) {
                $composite_avg = array_sum($comp_finals) / count($comp_finals);
                $rounded_composite = customRound($composite_avg);
                $pupil_grades[] = $rounded_composite;
                $all_empty = false;
            }
        } else {
            $quarters = $grades_map[$subject_id] ?? [];
            $filtered = array_filter($quarters, function($g, $q) use ($quarters_order, $start_num) {
                return $quarters_order[$q] >= $start_num;
            }, ARRAY_FILTER_USE_BOTH);
            if (count($filtered) == $expected_quarters) {
                $subject_avg = array_sum($filtered) / $expected_quarters;
                $rounded_subject = customRound($subject_avg);
                $pupil_grades[] = $rounded_subject;
                $all_empty = false;
            } else {
                $has_incomplete = true;
            }
        }
    }

    if(!$all_empty && !$has_incomplete && $pupil_grades){
        $avg = array_sum($pupil_grades) / count($pupil_grades);
        $rounded_avg = customRound($avg);
        if($rounded_avg >= 90){
            $p['average'] = number_format($avg, 2);
            $p['remark'] = $rounded_avg >= 98 ? "WITH HIGHEST HONORS" : ($rounded_avg >= 95 ? "WITH HIGH HONORS" : "WITH HONORS");
            $p['avg'] = $avg;
            $p['rounded_avg'] = $rounded_avg;
            $honors_pupils[] = $p;
        }
    }
}

if(!$honors_pupils){ die("No pupils with honors found."); }

usort($honors_pupils, function($a, $b) {
    if ($a['rounded_avg'] == $b['rounded_avg']) {
        return $b['avg'] <=> $a['avg'];
    }
    return $b['rounded_avg'] <=> $a['rounded_avg'];
});

$formatted_date = date('jS \d\a\y \o\f F Y',strtotime($issue_date));
$total = count($honors_pupils);
$files_needed = ($total <= 2) ? 1 : 2;

// --- Prepare template ---
$templateFile = __DIR__."/template/Certificate_of_Recognition_template2.docx";

// --- Generate files ---
$zip = new ZipArchive();
$temp_files = [];
$certificates_per_file = ceil($total / $files_needed);

for ($file_index = 0; $file_index < $files_needed; $file_index++) {
    $start = $file_index * $certificates_per_file;
    $end = min($start + $certificates_per_file, $total);
    $pupils_in_file = array_slice($honors_pupils, $start, $end - $start);

    // Determine filename based on pupils' last names
    $last_names = array_map(function($p) { return $p['last_name']; }, $pupils_in_file);
    $filename = "Certificate_" . implode("_", $last_names) . ".docx";
    $tempFile = sys_get_temp_dir() . "/cert_" . uniqid() . ".docx";
    copy($templateFile, $tempFile);
    $temp_files[] = [$tempFile, $filename];

    // Process template
    $templateProcessor = new TemplateProcessor($tempFile);

    // Fill certificates in this file
    for ($i = 0; $i < 2; $i++) {
        $slot = $i + 1;
        if (isset($pupils_in_file[$i])) {
            $p = $pupils_in_file[$i];
            $mi = $p['middle_name'] ? strtoupper(substr($p['middle_name'], 0, 1)) . ". " : "";
            $full = htmlspecialchars($p['first_name'] . " " . $mi . $p['last_name']);

            // --- Count words in first name only ---
            $first_name_words = str_word_count($p['first_name']);
            if ($first_name_words === 1) {
                $fontSize = 28;
            } elseif ($first_name_words === 2) {
                $fontSize = 24;
            } elseif ($first_name_words === 3) {
                $fontSize = 22;
            } else {
                $fontSize = 26; // fallback default
            }

            // Wrap text with font size XML (keeping template font family)
            $nameXml = '<w:r><w:rPr><w:sz w:val="' . ($fontSize * 2) . '"/></w:rPr><w:t>'
                    . $full .
                    '</w:t></w:r>';

            $templateProcessor->setValue("name{$slot}", $nameXml, 1);

            $templateProcessor->setValue("remark{$slot}", htmlspecialchars($p['remark']));
            $templateProcessor->setValue("grade_level{$slot}", htmlspecialchars($p['grade_level_id']));
            $templateProcessor->setValue("section{$slot}", htmlspecialchars($p['section_name']));
            $templateProcessor->setValue("average{$slot}", htmlspecialchars($p['average']));
            $templateProcessor->setValue("school_year{$slot}", htmlspecialchars($school_year));
            $templateProcessor->setValue("issue_date{$slot}", htmlspecialchars($formatted_date));
            $templateProcessor->setValue("teacher{$slot}", $teacher_full_name);
            $templateProcessor->setValue("principal{$slot}", $principal_full_name);
        } else {
            // Fill unused slot with defaults
            $p = $pupils_in_file[0];
            $templateProcessor->setValue("name{$slot}", "First Name Middle Initial. Last Name");
            $templateProcessor->setValue("remark{$slot}", "WITH HONORS");
            $templateProcessor->setValue("grade_level{$slot}", htmlspecialchars($p['grade_level_id']));
            $templateProcessor->setValue("section{$slot}", htmlspecialchars($p['section_name']));
            $templateProcessor->setValue("average{$slot}", "98.56");
            $templateProcessor->setValue("school_year{$slot}", htmlspecialchars($school_year));
            $templateProcessor->setValue("issue_date{$slot}", htmlspecialchars($formatted_date));
            $templateProcessor->setValue("teacher{$slot}", $teacher_full_name);
            $templateProcessor->setValue("principal{$slot}", $principal_full_name);
        }
    }

    $templateProcessor->saveAs($tempFile);
}

// --- Output files as ZIP if multiple files ---
if ($files_needed > 1) {
    $zipFile = sys_get_temp_dir() . "/certificates_" . date('Ymd') . ".zip";
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE) === true) {
        foreach ($temp_files as $file) {
            $zip->addFile($file[0], $file[1]);
        }
        $zip->close();

        header("Content-Type: application/zip");
        header("Content-Disposition: attachment; filename=\"Certificates_SY{$sy_id}_" . date('Ymd') . ".zip\"");
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