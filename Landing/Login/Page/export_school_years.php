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

// Query
if (!empty($search)) {
    $stmt = $conn->prepare("SELECT school_year, start_date, end_date 
                            FROM school_years 
                            WHERE school_year LIKE ? 
                            ORDER BY school_year DESC");
    $searchParam = "%$search%";
    $stmt->bind_param("s", $searchParam);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $query = "SELECT school_year, start_date, end_date 
              FROM school_years 
              ORDER BY school_year DESC";
    $result = $conn->query($query);
}

// =============================
// EXCEL EXPORT
// =============================
if ($format === 'excel') {
    $template_path = 'template/sy_template.xlsx';
    if (!file_exists($template_path)) {
        die("Template file not found: $template_path");
    }

    $spreadsheet = ExcelIOFactory::load($template_path);
    $sheet = $spreadsheet->getActiveSheet();

    // Start from row 4
    $row = 4;
    while ($school_year = $result->fetch_assoc()) {
        $sheet->setCellValue('A' . $row, $school_year['school_year']);
        $sheet->setCellValue('B' . $row, $school_year['start_date']);
        $sheet->setCellValue('C' . $row, $school_year['end_date']);
        $row++;
    }

    // Styling
    $highestRow = $row - 1;
    $headerStyle = [
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
    ];
    $sheet->getStyle('A1:C1')->applyFromArray($headerStyle);
    if ($highestRow >= 4) {
        $sheet->getStyle('A4:C' . $highestRow)->applyFromArray($headerStyle);
    }

    // Output
    $writer = ExcelIOFactory::createWriter($spreadsheet, 'Xlsx');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="school_years_list.xlsx"');
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

    // Import header design
    if (file_exists($header_pdf)) {
        $pageCount = $pdf->setSourceFile($header_pdf);
        $tplIdx = $pdf->importPage(1);
        $pdf->useTemplate($tplIdx, 0, 0, 210);
    } else {
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'Header template not found', 0, 1, 'C');
    }

    // Move below header
    $pdf->Ln(45);

    // Title
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'List of School Years', 0, 1, 'C');
    $pdf->Ln(5);

    // Table
    $pdf->SetFont('helvetica', '', 11);
    $html = '<table border="1" cellpadding="4">
                <thead>
                    <tr style="background-color:#f2f2f2;">
                        <th width="33%" align="center"><b>SCHOOL YEAR</b></th>
                        <th width="33%" align="center"><b>START DATE</b></th>
                        <th width="34%" align="center"><b>END DATE</b></th>
                    </tr>
                </thead>
                <tbody>';

    while ($school_year = $result->fetch_assoc()) {
        $html .= '<tr>
                    <td width="33%" align="center">' . htmlspecialchars($school_year['school_year']) . '</td>
                    <td width="33%" align="center">' . htmlspecialchars($school_year['start_date']) . '</td>
                    <td width="34%" align="center">' . htmlspecialchars($school_year['end_date']) . '</td>
                  </tr>';
    }

    $html .= '</tbody></table>';
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('school_years_list.pdf', 'D');
    exit;
}
?>
