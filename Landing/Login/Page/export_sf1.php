<?php
include 'lecs_db.php';
session_start();
date_default_timezone_set('Asia/Manila');

$sy_id = $_POST['sy_id'] ?? 0;
$section_id = $_POST['section_id'] ?? 0;
if ($sy_id == 0 || $section_id == 0) {
    header("Location: teacherPupils.php");
    exit;
}

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Border;

$sec_sql = "SELECT s.section_name, g.level_name, CONCAT(t.first_name, ' ', COALESCE(t.middle_name, ''), ' ', t.last_name) AS adviser
            FROM sections s 
            JOIN grade_levels g ON s.grade_level_id = g.grade_level_id
            JOIN teachers t ON s.teacher_id = t.teacher_id
            WHERE s.section_id = ? AND s.sy_id = ?";
$sec_stmt = $conn->prepare($sec_sql);
$sec_stmt->bind_param("ii", $section_id, $sy_id);
$sec_stmt->execute();
$section = $sec_stmt->get_result()->fetch_assoc();
if (!$section) {
    header("Location: teacherPupils.php");
    exit;
}

$sy_sql = "SELECT school_year, start_date, end_date FROM school_years WHERE sy_id = ?";
$sy_stmt = $conn->prepare($sy_sql);
$sy_stmt->bind_param("i", $sy_id);
$sy_stmt->execute();
$sy_row = $sy_stmt->get_result()->fetch_assoc();
$school_year = $sy_row['school_year'];
$start_date = date('m/d/Y', strtotime($sy_row['start_date']));
$end_date = date('m/d/Y', strtotime($sy_row['end_date']));

$parts = explode('-', $school_year);
$start_year = $parts[0];
$cutoff_date = $start_year . '-10-31';

$males_sql = "SELECT p.* FROM pupils p WHERE p.section_id = ? AND p.sy_id = ? AND p.sex = 'Male' ORDER BY p.last_name, p.first_name";
$males_stmt = $conn->prepare($males_sql);
$males_stmt->bind_param("ii", $section_id, $sy_id);
$males_stmt->execute();
$males = $males_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$females_sql = "SELECT p.* FROM pupils p WHERE p.section_id = ? AND p.sy_id = ? AND p.sex = 'Female' ORDER BY p.last_name, p.first_name";
$females_stmt = $conn->prepare($females_sql);
$females_stmt->bind_param("ii", $section_id, $sy_id);
$females_stmt->execute();
$females = $females_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$total_male = count($males);
$total_female = count($females);
$total = $total_male + $total_female;
$total_pupils = $total_male + $total_female;
$total_lines = $total_pupils + 3; // +3 for totals
$available_lines = 3; // rows 7 to 9
$offset = max(0, $total_lines - $available_lines);

$template_path = 'template/sf1_template1.xlsx';
$spreadsheet = IOFactory::load($template_path);
$sheet = $spreadsheet->getActiveSheet();

// Set headers
$sheet->setCellValue('T4', htmlspecialchars($school_year));
$sheet->setCellValue('AE4', htmlspecialchars($section['level_name']));
$sheet->setCellValue('AM4', htmlspecialchars(strtoupper($section['section_name'])));

// Set region if needed
$sheet->setCellValue('K3', htmlspecialchars('V (BICOL)'));

// Insert rows if needed to push fixed section down
if ($offset > 0) {
    $sheet->insertNewRowBefore(10, $offset);
}

// Define column ranges for merging
$column_ranges = [
    'A-B', // LRN
    'C-F', // NAME
    'G',   // Sex
    'H-I', // BIRTH DATE
    'J-K', // Age
    'L-M', // MOTHER TONGUE
    'N',   // IP
    'O',   // RELIGION
    'P-Q', // House #/Street
    'R-T', // Barangay
    'U-V', // Municipality/City
    'W-AA',// Province
    'AB-AE', // Father's Name
    'AF-AJ', // Mother's Maiden Name
    'AK-AN', // Guardian Name
    'AO',   // Relationship
    'AP-AQ', // Contact Number
    'AR',   // Learning Modality
    'AS-AT' // REMARKS
];

// Function to merge cells for a row
function mergeCellsForRow($sheet, $row, $column_ranges) {
    foreach ($column_ranges as $range) {
        if (strpos($range, '-') !== false) {
            list($start, $end) = explode('-', $range);
            $sheet->mergeCells("$start$row:$end$row");
        }
    }
}

// Fill males
$row_start = 7;
foreach ($males as $index => $pupil) {
    $row = $row_start + $index;

    // Merge cells for this row
    mergeCellsForRow($sheet, $row, $column_ranges);

    // LRN = A-B
    $sheet->getCell("A$row")->setValueExplicit(htmlspecialchars($pupil['lrn'] ?? ''), DataType::TYPE_STRING);

    // NAME(Last Name, First Name, Middle Name) = C-F
    $name = strtoupper($pupil['last_name'] . ', ' . $pupil['first_name']);
    if (!empty($pupil['middle_name'])) {
        $name .= ' ' . strtoupper(substr($pupil['middle_name'], 0, 1) . '.');
    }
    $sheet->setCellValue("C$row", htmlspecialchars($name));

    // Sex (M/F) = G
    $sex = (strtoupper($pupil['sex']) === 'MALE') ? 'M' : 'F';
    $sheet->setCellValue("G$row", htmlspecialchars($sex));

    // BIRTH DATE(mm/dd/yyyy) = H-I
    $birth = !empty($pupil['birthdate']) ? date('m/d/Y', strtotime($pupil['birthdate'])) : '';
    $sheet->setCellValue("H$row", htmlspecialchars($birth));

    // Age as of October 31 = J-K
    $age = !empty($pupil['birthdate']) ? date_diff(date_create($pupil['birthdate']), date_create($cutoff_date))->y : '';
    $sheet->setCellValue("J$row", htmlspecialchars($age));

    // MOTHER TONGUE (Grade 1 to 3 Only) = L-M
    $gl = (int)$section['level_name'];
    $mother_tongue = ($gl <= 3 && !empty($pupil['mother_tongue'])) ? $pupil['mother_tongue'] : '';
    $sheet->setCellValue("L$row", htmlspecialchars($mother_tongue));

    // IP(Ethnic Group) = N
    $sheet->setCellValue("N$row", htmlspecialchars($pupil['ip_ethnicity'] ?? ''));

    // RELIGION = O
    $sheet->setCellValue("O$row", htmlspecialchars($pupil['religion'] ?? ''));

    // House #/ Street/ Sitio/ Purok = P-Q
    $sheet->setCellValue("P$row", htmlspecialchars($pupil['house_no_street'] ?? ''));

    // Barangay = R-T
    $sheet->setCellValue("R$row", htmlspecialchars($pupil['barangay'] ?? ''));

    // Municipality/ City = U-V
    $sheet->setCellValue("U$row", htmlspecialchars($pupil['municipality'] ?? ''));

    // Province = W-AA
    $sheet->setCellValue("W$row", htmlspecialchars($pupil['province'] ?? ''));

    // Father's Name (Last Name, First Name, Middle Name) = AB-AE
    $sheet->setCellValue("AB$row", htmlspecialchars($pupil['father_name'] ?? ''));

    // Mother's Maiden Name (Last Name, First Name, Middle Name)= AF-AJ
    $sheet->setCellValue("AF$row", htmlspecialchars($pupil['mother_name'] ?? ''));

    // Name(Guardian) = AK-AN
    $sheet->setCellValue("AK$row", htmlspecialchars($pupil['guardian_name'] ?? ''));

    // Relationship = AO
    $sheet->setCellValue("AO$row", htmlspecialchars($pupil['relationship_to_guardian'] ?? ''));

    // Contact Number of Parent or Guardian = AP-AQ
    $sheet->setCellValue("AP$row", htmlspecialchars($pupil['contact_number'] ?? ''));

    // Learning Modality = AR
    $sheet->setCellValue("AR$row", htmlspecialchars($pupil['learning_modality'] ?? ''));

    // REMARKS = AS-AT
    $sheet->setCellValue("AS$row", htmlspecialchars($pupil['remarks'] ?? ''));
}

// Total Male
$total_male_row = $row_start + $total_male;
mergeCellsForRow($sheet, $total_male_row, $column_ranges);
$sheet->setCellValue("A$total_male_row", htmlspecialchars($total_male));
$sheet->setCellValue("C$total_male_row", '<=== TOTAL MALE');

// Fill females
$female_start_row = $total_male_row + 1;
foreach ($females as $index => $pupil) {
    $row = $female_start_row + $index;

    // Merge cells for this row
    mergeCellsForRow($sheet, $row, $column_ranges);

    // LRN = A-B
    $sheet->getCell("A$row")->setValueExplicit(htmlspecialchars($pupil['lrn'] ?? ''), DataType::TYPE_STRING);

    // NAME(Last Name, First Name, Middle Name) = C-F
    $name = strtoupper($pupil['last_name'] . ', ' . $pupil['first_name']);
    if (!empty($pupil['middle_name'])) {
        $name .= ' ' . strtoupper(substr($pupil['middle_name'], 0, 1) . '.');
    }
    $sheet->setCellValue("C$row", htmlspecialchars($name));

    // Sex (M/F) = G
    $sex = (strtoupper($pupil['sex']) === 'MALE') ? 'M' : 'F';
    $sheet->setCellValue("G$row", htmlspecialchars($sex));

    // BIRTH DATE(mm/dd/yyyy) = H-I
    $birth = !empty($pupil['birthdate']) ? date('m/d/Y', strtotime($pupil['birthdate'])) : '';
    $sheet->setCellValue("H$row", htmlspecialchars($birth));

    // Age as of October 31 = J-K
    $age = !empty($pupil['birthdate']) ? date_diff(date_create($pupil['birthdate']), date_create($cutoff_date))->y : '';
    $sheet->setCellValue("J$row", htmlspecialchars($age));

    // MOTHER TONGUE (Grade 1 to 3 Only) = L-M
    $gl = (int)$section['level_name'];
    $mother_tongue = ($gl <= 3 && !empty($pupil['mother_tongue'])) ? $pupil['mother_tongue'] : '';
    $sheet->setCellValue("L$row", htmlspecialchars($mother_tongue));

    // IP(Ethnic Group) = N
    $sheet->setCellValue("N$row", htmlspecialchars($pupil['ip_ethnicity'] ?? ''));

    // RELIGION = O
    $sheet->setCellValue("O$row", htmlspecialchars($pupil['religion'] ?? ''));

    // House #/ Street/ Sitio/ Purok = P-Q
    $sheet->setCellValue("P$row", htmlspecialchars($pupil['house_no_street'] ?? ''));

    // Barangay = R-T
    $sheet->setCellValue("R$row", htmlspecialchars($pupil['barangay'] ?? ''));

    // Municipality/ City = U-V
    $sheet->setCellValue("U$row", htmlspecialchars($pupil['municipality'] ?? ''));

    // Province = W-AA
    $sheet->setCellValue("W$row", htmlspecialchars($pupil['province'] ?? ''));

    // Father's Name (Last Name, First Name, Middle Name) = AB-AE
    $sheet->setCellValue("AB$row", htmlspecialchars($pupil['father_name'] ?? ''));

    // Mother's Maiden Name (Last Name, First Name, Middle Name)= AF-AJ
    $sheet->setCellValue("AF$row", htmlspecialchars($pupil['mother_name'] ?? ''));

    // Name(Guardian) = AK-AN
    $sheet->setCellValue("AK$row", htmlspecialchars($pupil['guardian_name'] ?? ''));

    // Relationship = AO
    $sheet->setCellValue("AO$row", htmlspecialchars($pupil['relationship_to_guardian'] ?? ''));

    // Contact Number of Parent or Guardian = AP-AQ
    $sheet->setCellValue("AP$row", htmlspecialchars($pupil['contact_number'] ?? ''));

    // Learning Modality = AR
    $sheet->setCellValue("AR$row", htmlspecialchars($pupil['learning_modality'] ?? ''));

    // REMARKS = AS-AT
    $sheet->setCellValue("AS$row", htmlspecialchars($pupil['remarks'] ?? ''));
}

// Total Female
$total_female_row = $female_start_row + $total_female;
mergeCellsForRow($sheet, $total_female_row, $column_ranges);
$sheet->setCellValue("A$total_female_row", htmlspecialchars($total_female));
$sheet->setCellValue("C$total_female_row", '<=== TOTAL FEMALE');

// Combined Total
$combined_row = $total_female_row + 1;
mergeCellsForRow($sheet, $combined_row, $column_ranges);
$sheet->setCellValue("A$combined_row", htmlspecialchars($total));
$sheet->setCellValue("C$combined_row", '<=== COMBINED');

// Set LRN as text format for all LRN cells
$last_row = $combined_row;
$sheet->getStyle("A7:A$last_row")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

// Add borders to the data table for clean look - including totals and combined
$borderStyleArray = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000'],
        ],
    ],
];

if ($total > 0 || $total_male > 0 || $total_female > 0) {
    $sheet->getStyle("A7:AT$last_row")->applyFromArray($borderStyleArray);
}

// Bottom counts with offset
$sig_row = 12 + $offset;
$f_sig_row = 15 + $offset;
$t_sig_row = 17 + $offset;
$date_row = 17 + $offset;
$gen_row = 21 + $offset;

$sheet->setCellValue("AE$sig_row", htmlspecialchars(strtoupper($section['adviser'])));
$sheet->setCellValue("X$sig_row", htmlspecialchars($total_male));
$sheet->setCellValue("X$f_sig_row", htmlspecialchars($total_female));
$sheet->setCellValue("X$t_sig_row", htmlspecialchars($total));
$sheet->setCellValue("Y$date_row", htmlspecialchars($start_date));
$sheet->setCellValue("Z$date_row", htmlspecialchars($end_date));

$generated_on = 'Generated on: ' . date('l, F j, Y');
$sheet->setCellValue("A$gen_row", htmlspecialchars($generated_on));

// Prepare filename: SF1_YYYY_Grade X - SECTION.xlsx
$grade = $section['level_name'];
$sec_name = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $section['section_name']));
$filename = "SF1_{$start_year}_Grade{$grade}-{$sec_name}.xlsx";

// Output the file for download
$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"{$filename}\"");
header('Cache-Control: max-age=0');
header('Expires: 0');
header('Pragma: public');
$writer->save('php://output');
exit;
?>