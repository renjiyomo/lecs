<?php
require 'vendor/autoload.php';
include 'lecs_db.php';
session_start();

use PhpOffice\PhpWord\TemplateProcessor;

if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 'a') {
    header("Location: /lecs/Landing/Login/login.php");
    exit;
}

$sy_id = isset($_POST['sy_id']) ? intval($_POST['sy_id']) : 0;
$section_id = isset($_POST['section_id']) ? intval($_POST['section_id']) : 0;
$issue_date = isset($_POST['issue_date']) ? $_POST['issue_date'] : date('Y-m-d');

if (!DateTime::createFromFormat('Y-m-d', $issue_date)) {
    die("Invalid date format.");
}

// --- School year ---
$sy_stmt = $conn->prepare("SELECT school_year FROM school_years WHERE sy_id=?");
$sy_stmt->bind_param("i", $sy_id);
$sy_stmt->execute();
$sy_result = $sy_stmt->get_result()->fetch_assoc();
$school_year = $sy_result['school_year'] ?? 'Unknown';

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
$sql = "SELECT p.pupil_id, p.first_name, p.last_name, p.middle_name, p.lrn,
               s.section_name, s.grade_level_id, g.level_name
        FROM pupils p
        JOIN sections s ON p.section_id = s.section_id
        JOIN grade_levels g ON s.grade_level_id = g.grade_level_id
        WHERE p.sy_id = ? AND p.section_id = ? AND p.status = 'enrolled'
        ORDER BY p.last_name ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $sy_id, $section_id);
$stmt->execute();
$pupils = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($pupils)) {
    die("No enrolled pupils found.");
}

$formatted_date = date('jS \d\a\y \o\f F Y', strtotime($issue_date));
$total = count($pupils);
$certificates_per_page = 2;
$files_needed = ceil($total / $certificates_per_page);

// --- Prepare template ---
$templateFile = __DIR__ . "/template/Certificate_of_Enrolment_template.docx";

// --- Generate files ---
$temp_files = [];

// Loop over the number of files needed
for ($file_index = 0; $file_index < $files_needed; $file_index++) {
    $start = $file_index * $certificates_per_page;
    $end = min($start + $certificates_per_page, $total);
    $pupils_in_file = array_slice($pupils, $start, $end - $start);

    // Determine filename based on pupils' last names
    $last_names = array_map(function($p) { return $p['last_name']; }, $pupils_in_file);
    $filename = "COE_" . implode("_", $last_names) . ".docx";
    $tempFile = sys_get_temp_dir() . "/coe_" . uniqid() . ".docx";
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

            $templateProcessor->setValue("name{$slot}", $full);
            $templateProcessor->setValue("lrn{$slot}", htmlspecialchars($p['lrn']));
            $templateProcessor->setValue("grade_level{$slot}", htmlspecialchars($p['level_name']));
            $templateProcessor->setValue("school_year{$slot}", htmlspecialchars($school_year));
            $templateProcessor->setValue("issue_date{$slot}", htmlspecialchars($formatted_date));
            $templateProcessor->setValue("principal{$slot}", $principal_full_name);
        } else {
            // Blank out unused slot
            $templateProcessor->setValue("name{$slot}", "____________________________");
            $templateProcessor->setValue("lrn{$slot}", "_______________");
            $templateProcessor->setValue("grade_level{$slot}", htmlspecialchars($p['level_name']));
            $templateProcessor->setValue("school_year{$slot}", htmlspecialchars($school_year));
            $templateProcessor->setValue("issue_date{$slot}", htmlspecialchars($formatted_date));
            $templateProcessor->setValue("principal{$slot}", $principal_full_name);
        }
    }

    $templateProcessor->saveAs($tempFile);
}

// --- Output files as ZIP if multiple files ---
if ($files_needed > 1) {
    $zipFile = sys_get_temp_dir() . "/coe_" . date('Ymd') . ".zip";
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE) === true) {
        foreach ($temp_files as $file) {
            $zip->addFile($file[0], $file[1]);
        }
        $zip->close();

        header("Content-Type: application/zip");
        header("Content-Disposition: attachment; filename=\"COE_SY{$sy_id}_Section{$section_id}_" . date('Ymd') . ".zip\"");
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