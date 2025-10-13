<?php
include 'lecs_db.php';
require 'vendor/autoload.php'; // FPDI + PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Border;
use setasign\Fpdi\Tcpdf\Fpdi;

// =============================
// Query & Filtering
// =============================
$search = $_GET['search'] ?? '';
$grade_id = $_GET['grade_id'] ?? '';
$sy_id = $_GET['sy_id'] ?? '';
$format = $_GET['format'] ?? '';

$where = [];
$params = [];

if (!empty($search)) {
    $searchTerm = '%' . $conn->real_escape_string($search) . '%';
    $where[] = "(s.subject_name LIKE '$searchTerm' OR g.level_name LIKE '$searchTerm' OR sy.school_year LIKE '$searchTerm')";
}
if (!empty($sy_id)) $where[] = "s.sy_id = " . intval($sy_id);
if (!empty($grade_id)) $where[] = "s.grade_level_id = " . intval($grade_id);

$sql = "SELECT s.subject_name, g.level_name, sy.school_year, s.start_quarter, 
               p.subject_name AS parent_name
        FROM subjects s
        JOIN grade_levels g ON s.grade_level_id = g.grade_level_id
        JOIN school_years sy ON s.sy_id = sy.sy_id
        LEFT JOIN subjects p ON s.parent_subject_id = p.subject_id
        " . (count($where) ? 'WHERE ' . implode(' AND ', $where) : '') . "
        ORDER BY sy.school_year DESC, g.level_name ASC, s.subject_name ASC";

$result = $conn->query($sql);

// =============================
// Excel Export
// =============================
if ($format === 'excel') {
    $spreadsheet = IOFactory::load('template/subject_template.xlsx');
    $sheet = $spreadsheet->getActiveSheet();

    $row = 4;
    while ($data = $result->fetch_assoc()) {
        $subject = $data['subject_name'];
        if ($subject === 'Edukasyong Pantahanan at Pangkabuhayan / TLE' || $subject === 'TLE') $subject = 'EPP / TLE';
        if ($subject === 'Edukasyon sa Pagpapakatao') $subject = 'ESP';

        $sheet->setCellValue('A' . $row, $subject);
        $sheet->setCellValue('B' . $row, $data['level_name']);
        $sheet->setCellValue('C' . $row, $data['school_year']);
        $sheet->setCellValue('D' . $row, $data['start_quarter']);
        $sheet->setCellValue('E' . $row, $data['parent_name'] ?? '');
        $row++;
    }

    $highestRow = $row - 1;
    $style = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];
    if ($highestRow >= 4) $sheet->getStyle("A4:E{$highestRow}")->applyFromArray($style);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="subjects_list.xlsx"');
    header('Cache-Control: max-age=0');
    IOFactory::createWriter($spreadsheet, 'Xlsx')->save('php://output');
    exit;
}

// =============================
// PDF Export with Landscape Header
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
$pdf->Ln(45);

$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Subjects List', 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFont('helvetica', '', 10);
$html = '<table border="1" cellpadding="4">
<thead>
<tr style="background-color:#f2f2f2;">
    <th width="25%" align="center"><b>LEARNING AREA</b></th>
    <th width="20%" align="center"><b>GRADE LEVEL</b></th>
    <th width="20%" align="center"><b>SCHOOL YEAR</b></th>
    <th width="15%" align="center"><b>START QUARTER</b></th>
    <th width="20%" align="center"><b>PARENT SUBJECT</b></th>
</tr>
</thead><tbody>';

while ($data = $result->fetch_assoc()) {
    $subject = $data['subject_name'];
    if ($subject === 'Edukasyong Pantahanan at Pangkabuhayan / TLE' || $subject === 'TLE') $subject = 'EPP / TLE';
    if ($subject === 'Edukasyon sa Pagpapakatao') $subject = 'ESP';

    $html .= '<tr>
        <td width="25%" align="center">' . htmlspecialchars($subject) . '</td>
        <td width="20%" align="center">' . htmlspecialchars($data['level_name']) . '</td>
        <td width="20%" align="center">' . htmlspecialchars($data['school_year']) . '</td>
        <td width="15%" align="center">' . htmlspecialchars($data['start_quarter']) . '</td>
        <td width="20%" align="center">' . htmlspecialchars($data['parent_name'] ?? '') . '</td>
    </tr>';
}
$html .= '</tbody></table>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('subjects_list.pdf', 'D');
exit;
?>
