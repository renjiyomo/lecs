<?php
include 'lecs_db.php';
session_start();

// Restrict access to admins only
if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 'a') {
    header("Location: /lecs/Landing/Login/login.php");
    exit;
}

// Load dependencies
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory as ExcelIOFactory;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use setasign\Fpdi\Tcpdf\Fpdi;

// Handle export format
$format = isset($_GET['format']) ? $_GET['format'] : 'excel';
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

// Query data
if (!empty($search)) {
    $stmt = $conn->prepare("SELECT section_name FROM section_name WHERE section_name LIKE ? ORDER BY section_name ASC");
    $searchParam = "%$search%";
    $stmt->bind_param("s", $searchParam);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $query = "SELECT section_name FROM section_name ORDER BY section_name ASC";
    $result = $conn->query($query);
}

// =============================
// EXCEL EXPORT
// =============================
if ($format === 'excel') {
    $template_path = 'template/section_list_template.xlsx';
    if (!file_exists($template_path)) {
        die("Template file not found: $template_path");
    }

    $spreadsheet = ExcelIOFactory::load($template_path);
    $sheet = $spreadsheet->getActiveSheet();

    // Write data starting from row 4
    $row = 4;
    while ($section = $result->fetch_assoc()) {
        $sheet->setCellValue('A' . $row, $section['section_name']);
        $row++;
    }

    // Style formatting
    $highestRow = $row - 1;
    $styleArray = [
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
    ];

    // Apply style to header (A1)
    $sheet->getStyle('A1')->applyFromArray($styleArray);

    // Apply style to data (A4:A...)
    if ($highestRow >= 4) {
        $sheet->getStyle('A4:A' . $highestRow)->applyFromArray($styleArray);
    }

    // Output Excel file
    $writer = ExcelIOFactory::createWriter($spreadsheet, 'Xlsx');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="section_name_list.xlsx"');
    header('Cache-Control: max-age=0');
    $writer->save('php://output');
    exit;
}

// =============================
// PDF EXPORT USING HEADER TEMPLATE
// =============================
else {
    $header_pdf = 'template/header.pdf';

    $pdf = new Fpdi();
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage();

    // Import DepEd header
    if (file_exists($header_pdf)) {
        $pageCount = $pdf->setSourceFile($header_pdf);
        $tplIdx = $pdf->importPage(1);
        $pdf->useTemplate($tplIdx, 0, 0, 210);
    } else {
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'Header template not found', 0, 1, 'C');
    }

    // Move content down below header
    $pdf->Ln(45);

    // Title
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'List of Section Names', 0, 1, 'C');
    $pdf->Ln(5);

    // Table
    $pdf->SetFont('helvetica', '', 11);
    $html = '<table border="1" cellpadding="4">
                <thead>
                    <tr style="background-color:#f2f2f2;">
                        <th width="100%" align="center"><b>SECTION NAME</b></th>
                    </tr>
                </thead>
                <tbody>';

    while ($section = $result->fetch_assoc()) {
        $html .= '<tr>
                    <td width="100%" align="center">' . htmlspecialchars($section['section_name']) . '</td>
                  </tr>';
    }

    $html .= '</tbody></table>';

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('section_name_list.pdf', 'D');
    exit;
}
?>
