<?php
include 'lecs_db.php';
require 'vendor/autoload.php'; // Ensure FPDI + PhpSpreadsheet are installed

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Border;
use setasign\Fpdi\Tcpdf\Fpdi;

// =============================
// Query & Filter Handling
// =============================
$search = $_GET['search'] ?? '';
$grade_filter = $_GET['grade_filter'] ?? '';
$sy_filter = $_GET['sy_filter'] ?? '';
$format = $_GET['format'] ?? '';

$whereParts = [];
if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $whereParts[] = "s.section_name LIKE '%$search%'";
}
if (!empty($grade_filter)) {
    $whereParts[] = "s.grade_level_id = " . intval($grade_filter);
}
if (!empty($sy_filter)) {
    $whereParts[] = "s.sy_id = " . intval($sy_filter);
}
$where = count($whereParts) ? "WHERE " . implode(" AND ", $whereParts) : "";

$sql = "SELECT s.section_name, g.level_name, sy.school_year, 
               CONCAT(t.first_name, ' ', COALESCE(t.middle_name, ''), ' ', t.last_name) AS teacher_name
        FROM sections s
        LEFT JOIN grade_levels g ON s.grade_level_id = g.grade_level_id
        LEFT JOIN school_years sy ON s.sy_id = sy.sy_id
        LEFT JOIN teachers t ON s.teacher_id = t.teacher_id
        $where
        ORDER BY s.section_name ASC";

$result = $conn->query($sql);

// =============================
// Excel Export
// =============================
if ($format === 'excel') {
    $spreadsheet = IOFactory::load('template/section_template.xlsx');
    $sheet = $spreadsheet->getActiveSheet();

    $row = 4;
    while ($data = $result->fetch_assoc()) {
        $sheet->setCellValue('A' . $row, $data['section_name']);
        $sheet->setCellValue('B' . $row, $data['level_name']);
        $sheet->setCellValue('C' . $row, $data['school_year']);
        $sheet->setCellValue('D' . $row, $data['teacher_name'] ?? '-');
        $row++;
    }

    $styleArray = [
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ];
    $highestRow = $row - 1;
    if ($highestRow >= 4) {
        $sheet->getStyle("A4:D{$highestRow}")->applyFromArray($styleArray);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="class_list.xlsx"');
    header('Cache-Control: max-age=0');
    IOFactory::createWriter($spreadsheet, 'Xlsx')->save('php://output');
    exit;
}

// =============================
// PDF Export with FPDI Header
// =============================
$header_pdf = 'template/header-landscape.pdf';
$pdf = new Fpdi('L', 'mm', 'A4');

$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

$pdf->AddPage();

if (file_exists($header_pdf)) {
    $pageCount = $pdf->setSourceFile($header_pdf);
    $tplIdx = $pdf->importPage(1);
    $pdf->useTemplate($tplIdx, 0, 0, 297);
}
$pdf->Ln(45); // Move below the header

$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Class List', 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFont('helvetica', '', 10);
$html = '<table border="1" cellpadding="4">
<thead>
<tr style="background-color:#f2f2f2;">
    <th width="25%" align="center"><b>SECTION NAME</b></th>
    <th width="25%" align="center"><b>GRADE LEVEL</b></th>
    <th width="25%" align="center"><b>SCHOOL YEAR</b></th>
    <th width="25%" align="center"><b>ADVISER</b></th>
</tr>
</thead><tbody>';

while ($data = $result->fetch_assoc()) {
    $html .= '<tr>
        <td align="center">' . htmlspecialchars($data['section_name']) . '</td>
        <td align="center">' . htmlspecialchars($data['level_name']) . '</td>
        <td align="center">' . htmlspecialchars($data['school_year']) . '</td>
        <td align="center">' . htmlspecialchars($data['teacher_name'] ?? '-') . '</td>
    </tr>';
}
$html .= '</tbody></table>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('class_list.pdf', 'D');
exit;
?>
