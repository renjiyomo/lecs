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
$quarter    = isset($_POST['quarter']) ? $_POST['quarter'] : '';
$issue_date = isset($_POST['issue_date']) ? $_POST['issue_date'] : date('Y-m-d');

if (!in_array($quarter, ['Q1', 'Q2', 'Q3', 'Q4'])) {
    die("Invalid quarter.");
}

if (!DateTime::createFromFormat('Y-m-d', $issue_date)) {
    die("Invalid date format.");
}

// --- School year ---
$sy_stmt = $conn->prepare("SELECT school_year FROM school_years WHERE sy_id=?");
$sy_stmt->bind_param("i", $sy_id);
$sy_stmt->execute();
$sy_result = $sy_stmt->get_result()->fetch_assoc();
$school_year = $sy_result['school_year'] ?? 'Unknown';

// --- Quarter display ---
$quarter_num = substr($quarter, 1);
$suffix = ($quarter_num == '1') ? 'st' : (($quarter_num == '2') ? 'nd' : (($quarter_num == '3') ? 'rd' : 'th'));
$quarter_display = $quarter_num . $suffix;

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

// --- Compute honors for quarter ---
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

    $gstmt = $conn->prepare("SELECT subject_id,quarter,grade FROM grades WHERE pupil_id=? AND sy_id=? AND quarter=?");
    $gstmt->bind_param("iis",$pupil_id,$sy_id,$quarter);
    $gstmt->execute();
    $grades = $gstmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $grades_map=[];
    foreach($grades as $g){ $grades_map[$g['subject_id']]=$g['grade']; }

    $pupil_grades=[]; $all_empty=true; $has_incomplete=false;

    foreach($pupil_subjects as $subject_id=>$sub){
        $start_num=$quarters_order[$sub['start_quarter']??'Q1']??1;
        if(($quarters_order[$quarter]??0) < $start_num) continue;

        if(isset($components[$subject_id])){
            $comp_finals=[];
            $comp_count=count($components[$subject_id]);
            $comp_present=0;
            $comp_incomplete=false;
            foreach($components[$subject_id] as $comp){
                $comp_start_num=$quarters_order[$comp['start_quarter']??'Q1']??1;
                if(($quarters_order[$quarter]??0) < $comp_start_num){
                    $comp_incomplete=true;
                    break;
                }
                $comp_grade=$grades_map[$comp['subject_id']]??null;
                if($comp_grade!==null){
                    $comp_finals[]=$comp_grade;
                    $comp_present++;
                    $all_empty=false;
                } else {
                    $comp_incomplete=true;
                    break;
                }
            }
            if($comp_incomplete || $comp_present < $comp_count){
                $has_incomplete=true;
            } elseif($comp_finals){
                $pupil_grades[]=array_sum($comp_finals)/count($comp_finals);
            }
        } else {
            $grade=$grades_map[$subject_id]??null;
            if($grade!==null){
                $pupil_grades[]=$grade;
                $all_empty=false;
            } else {
                $has_incomplete=true;
            }
        }
    }

    if(!$all_empty && !$has_incomplete && $pupil_grades){
        $avg=array_sum($pupil_grades)/count($pupil_grades);
        if($avg>=90){
            $p['average']=number_format($avg,2);
            $p['remark']=$avg>=98?"WITH HIGHEST HONORS":($avg>=95?"WITH HIGH HONORS":"WITH HONORS");
            $honors_pupils[]=$p;
        }
    }
}

if(!$honors_pupils){ 
    $_SESSION['message'] = "No pupils with honors found for this quarter.";
    header("Location: teacherGrades.php?sy_id=$sy_id&quarter=$quarter");
    exit;
}

usort($honors_pupils,function($a,$b){ return (float)$b['average']<=> (float)$a['average']; });

$formatted_date = date('jS \d\a\y \o\f F Y',strtotime($issue_date));
$total = count($honors_pupils);
$files_needed = ceil($total / 2); // Exactly 2 pupils per file

// --- Prepare template ---
$templateFile = __DIR__."/template/Certificate_of_Recognition_per_Quarter_template.docx";

// --- Generate files ---
$zip = new ZipArchive();
$temp_files = [];

for ($file_index = 0; $file_index < $files_needed; $file_index++) {
    $start = $file_index * 2;
    $pupils_in_file = array_slice($honors_pupils, $start, 2); // Take exactly 2 pupils

    // Determine filename based on pupils' last names
    $last_names = array_map(function($p) { return $p['last_name']; }, $pupils_in_file);
    $filename = "Certificate_{$quarter}_" . implode("_&_", $last_names) . ".docx";
    $tempFile = sys_get_temp_dir() . "/cert_q_" . uniqid() . ".docx";
    copy($templateFile, $tempFile);
    $temp_files[] = [$tempFile, $filename];

    // Process template
    $templateProcessor = new TemplateProcessor($tempFile);

    // Fill certificates in this file (exactly 2 slots)
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
            $templateProcessor->setValue("quarter{$slot}", $quarter_display);
            $templateProcessor->setValue("school_year{$slot}", htmlspecialchars($school_year));
            $templateProcessor->setValue("issue_date{$slot}", htmlspecialchars($formatted_date));
            $templateProcessor->setValue("teacher{$slot}", $teacher_full_name);
            $templateProcessor->setValue("principal{$slot}", $principal_full_name);
        } else {
            // Leave unused slot empty
            $templateProcessor->setValue("name{$slot}", "______________________________");
            $templateProcessor->setValue("remark{$slot}", "_______________________");
            $templateProcessor->setValue("grade_level{$slot}", htmlspecialchars($p['grade_level_id']));
            $templateProcessor->setValue("section{$slot}", htmlspecialchars($p['section_name']));
            $templateProcessor->setValue("quarter{$slot}", $quarter_display);
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
    $zipFile = sys_get_temp_dir() . "/certificates_q_{$quarter}_" . date('Ymd') . ".zip";
    if ($zip->open($zipFile, ZipArchive::CREATE) === true) {
        foreach ($temp_files as $file) {
            $zip->addFile($file[0], $file[1]);
        }
        $zip->close();

        header("Content-Type: application/zip");
        header("Content-Disposition: attachment; filename=\"Certificates_SY{$sy_id}_{$quarter}_" . date('Ymd') . ".zip\"");
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