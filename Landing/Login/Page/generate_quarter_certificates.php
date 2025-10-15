<?php
require 'vendor/autoload.php';
include 'lecs_db.php';
session_start();

use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\PhpPresentation;

if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 't') {
    header("Location: /lecs/Landing/Login/login.php");
    exit;
}

$teacher_id = intval($_SESSION['teacher_id']);
$sy_id      = isset($_POST['sy_id']) ? intval($_POST['sy_id']) : 0;
$quarter    = isset($_POST['quarter']) ? $_POST['quarter'] : '';
$issue_date = isset($_POST['issue_date']) ? $_POST['issue_date'] : date('Y-m-d');

if (!DateTime::createFromFormat('Y-m-d', $issue_date)) {
    die("Invalid date format.");
}

if (!in_array($quarter, ['Q1', 'Q2', 'Q3', 'Q4'])) {
    die("Invalid quarter.");
}

// --- School year ---
$sy_stmt = $conn->prepare("SELECT school_year FROM school_years WHERE sy_id=?");
$sy_stmt->bind_param("i", $sy_id);
$sy_stmt->execute();
$sy_result = $sy_stmt->get_result()->fetch_assoc();
$school_year = $sy_result['school_year'] ?? 'Unknown';

// --- Teacher name ---
$teacher_stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name) AS teacher_name FROM teachers WHERE teacher_id=?");
$teacher_stmt->bind_param("i", $teacher_id);
$teacher_stmt->execute();
$teacher_result = $teacher_stmt->get_result()->fetch_assoc();
$teacher_full_name = strtoupper(htmlspecialchars($teacher_result['teacher_name'] ?? ''));

// --- Principal name (latest based on start_date) ---
$principal_stmt = $conn->prepare("
    SELECT CONCAT(t.first_name, ' ', COALESCE(t.middle_name, ''), ' ', t.last_name) AS principal_name
    FROM teachers t
    JOIN teacher_positions tp ON t.teacher_id = tp.teacher_id
    WHERE tp.position_id IN (13,14,15,16)
    AND tp.start_date <= ?
    AND (tp.end_date >= ? OR tp.end_date IS NULL)
    ORDER BY tp.start_date DESC
    LIMIT 1
");
$principal_stmt->bind_param("ss", $issue_date, $issue_date);
$principal_stmt->execute();
$principal_result = $principal_stmt->get_result()->fetch_assoc();
$principal_full_name = strtoupper(htmlspecialchars($principal_result['principal_name'] ?? ''));

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

// --- Compute honors for the quarter ---
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
    foreach($grades as $g){ $grades_map[$g['subject_id']][$g['quarter']]=$g['grade']; }

    $pupil_grades=[]; $all_empty=true; $has_incomplete=false;

    foreach($pupil_subjects as $subject_id=>$sub){
        $start_num=$quarters_order[$sub['start_quarter']??'Q1']??1;
        if($quarters_order[$quarter] < $start_num) continue;

        if(isset($components[$subject_id])){
            $comp_finals=[];
            $comp_incomplete=false;
            foreach($components[$subject_id] as $comp){
                $comp_start_num=$quarters_order[$comp['start_quarter']??'Q1']??1;
                if($quarters_order[$quarter] < $comp_start_num) continue;

                $comp_quarters=$grades_map[$comp['subject_id']]??[];
                if(isset($comp_quarters[$quarter])){
                    $comp_finals[]=$comp_quarters[$quarter];
                    $all_empty=false;
                } else {
                    $comp_incomplete=true;
                }
            }
            if(!$comp_incomplete && count($comp_finals)==count($components[$subject_id])){
                $pupil_grades[]=array_sum($comp_finals)/count($comp_finals);
            } else {
                $has_incomplete=true;
            }
        } else {
            $quarters=$grades_map[$subject_id]??[];
            if(isset($quarters[$quarter])){
                $pupil_grades[]=$quarters[$quarter];
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
            $p['remark']=$avg>=98?"With Highest Honors":($avg>=95?"With High Honors":"With Honors");
            $honors_pupils[]=$p;
        }
    }
}

if(!$honors_pupils){ die("No pupils with honors found for this quarter."); }

usort($honors_pupils,function($a,$b){ return (float)$b['average']<=> (float)$a['average']; });

$formatted_date = date('jS \d\a\y \o\f F Y',strtotime($issue_date));

$quarter_number = (int)str_replace('Q', '', $quarter);
$quarter_suffix = ($quarter_number == 1 ? 'st' : ($quarter_number == 2 ? 'nd' : ($quarter_number == 3 ? 'rd' : 'th')));
$quarter_phrase = $quarter_number . $quarter_suffix;

// --- Prepare template ---
$templateFile = __DIR__."/template/Certificate_of_Recognition_per_Quarter_template.pptx";

// Function to replace placeholders
function replaceInPresentation(PhpPresentation $presentation, array $replacements, $quarter_number, $quarter_suffix) {
    foreach ($presentation->getAllSlides() as $slide) {
        foreach ($slide->getShapeCollection() as $shape) {
            if ($shape instanceof \PhpOffice\PhpPresentation\Shape\RichText) {
                foreach ($shape->getParagraphs() as $paragraph) {
                    foreach ($paragraph->getRichTextElements() as $element) {
                        if ($element instanceof \PhpOffice\PhpPresentation\Shape\RichText\TextElement) {
                            $text = $element->getText();

                            // Special replacement for quarter
                            $text = str_replace('${quarter}st', $quarter_number . $quarter_suffix, $text);

                            // Normal replacements
                            foreach ($replacements as $search => $replace) {
                                $text = str_replace('${' . $search . '}', $replace, $text);
                            }

                            $element->setText($text);
                        }
                    }
                }
            }
        }
    }
}

// --- Generate files ---
$temp_files = [];
foreach ($honors_pupils as $p) {
    $presentation = IOFactory::load($templateFile);

    $mi = $p['middle_name'] ? strtoupper(substr($p['middle_name'], 0, 1)) . '.' : '';
    $full_name = strtoupper(htmlspecialchars($p['first_name'] . ' ' . $mi . ' ' . $p['last_name']));

    $replacements = [
        'name' => $full_name,
        'remark' => htmlspecialchars($p['remark']),
        'school_year' => htmlspecialchars($school_year),
        'issue_date' => htmlspecialchars($formatted_date),
        'grade_level' => htmlspecialchars($p['grade_level_id']),
        'section' => htmlspecialchars($p['section_name']),
        'teacher' => $teacher_full_name,
        'principal' => $principal_full_name
    ];

    replaceInPresentation($presentation, $replacements, $quarter_number, $quarter_suffix);

    $filename = "Certificate_{$quarter}_{$p['last_name']}.pptx";
    $tempFile = sys_get_temp_dir() . "/cert_q_" . uniqid() . ".pptx";
    $writer = IOFactory::createWriter($presentation, 'PowerPoint2007');
    $writer->save($tempFile);
    $temp_files[] = [$tempFile, $filename];
}

$total = count($honors_pupils);

// --- Output files as ZIP if multiple files ---
if ($total > 1) {
    $zipFile = sys_get_temp_dir() . "/certificates_{$quarter}_" . date('Ymd') . ".zip";
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE) === true) {
        foreach ($temp_files as $file) {
            $zip->addFile($file[0], $file[1]);
        }
        $zip->close();

        header("Content-Type: application/zip");
        header("Content-Disposition: attachment; filename=\"Certificates_{$quarter}_SY{$sy_id}_" . date('Ymd') . ".zip\"");
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
    header("Content-Type: application/vnd.openxmlformats-officedocument.presentationml.presentation");
    header("Content-Disposition: attachment; filename=\"{$file[1]}\"");
    readfile($file[0]);
    @unlink($file[0]);
    exit;
}
?>