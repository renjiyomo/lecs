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

// Namespaces for Excel and FPDI
use PhpOffice\PhpSpreadsheet\IOFactory as ExcelIOFactory;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use setasign\Fpdi\Tcpdf\Fpdi;

// =============================
// Helper functions
// =============================
function formatPosition($position) {
    if (preg_match('/(Master Teachers|Master Teacher|Teachers|Teacher|Principal|Administrative Officer|Administrative Assistant|Administrative Aide) (\d+)/i', $position, $matches)) {
        $prefix = $matches[1];
        $number = $matches[2];
        if (strcasecmp($prefix, 'Administrative Assistant') === 0) {
            return 'ADAS-' . numToRoman($number);
        } elseif (strcasecmp($prefix, 'Administrative Aide') === 0) {
            return 'ADA-' . numToRoman($number);
        }
        $words = explode(' ', $prefix);
        $initials = '';
        foreach ($words as $word) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
        return $initials . '-' . numToRoman($number);
    }
    return $position;
}

function numToRoman($num) {
    $map = ['1' => 'I', '2' => 'II', '3' => 'III', '4' => 'IV', '5' => 'V', '6' => 'VI'];
    return isset($map[$num]) ? $map[$num] : $num;
}

// =============================
// Query setup
// =============================
$format = isset($_GET['format']) ? $_GET['format'] : 'excel';
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$sy_id = isset($_GET['sy']) ? intval($_GET['sy']) : 0;

// Get school year dates
$sy_start = $sy_end = null;
if ($sy_id > 0) {
    $stmt_sy = $conn->prepare("SELECT start_date, end_date FROM school_years WHERE sy_id = ?");
    $stmt_sy->bind_param("i", $sy_id);
    $stmt_sy->execute();
    $sy_res = $stmt_sy->get_result();
    if ($sy_row = $sy_res->fetch_assoc()) {
        $sy_start = $sy_row['start_date'];
        $sy_end = $sy_row['end_date'];
    }
    $stmt_sy->close();
}

// Build teacher query
$where = "WHERE t.user_status = 'a'";
$params = [];
$types = "";

if (!empty($search)) {
    $where .= " AND (CONCAT(t.first_name, ' ', COALESCE(t.middle_name, ''), ' ', t.last_name) LIKE ? OR t.employee_no LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ss";
}

if ($sy_id > 0) {
    $where .= " AND EXISTS (SELECT 1 FROM teacher_positions tp WHERE tp.teacher_id = t.teacher_id AND tp.start_date <= ? AND (tp.end_date >= ? OR tp.end_date IS NULL))";
    $params[] = $sy_end;
    $params[] = $sy_start;
    $types .= "ss";
}

$sql = "SELECT t.employee_no, CONCAT(t.first_name, ' ', COALESCE(t.middle_name, ''), ' ', t.last_name) AS teacher_name, t.position 
        FROM teachers t 
        $where 
        ORDER BY CAST(t.employee_no AS UNSIGNED) ASC";

if (!empty($types)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    $result = $conn->query($sql);
}

// =============================
// EXCEL EXPORT
// =============================
if ($format === 'excel') {
    $template_path = 'template/personnel_template.xlsx';
    if (!file_exists($template_path)) {
        die("Template file not found: $template_path");
    }

    $spreadsheet = ExcelIOFactory::load($template_path);
    $sheet = $spreadsheet->getActiveSheet();

    // Center headers (A1:C3)
    $headerStyle = [
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
    ];
    $sheet->getStyle('A1:C3')->applyFromArray($headerStyle);

    // Start writing from row 4
    $row = 4;
    while ($teacher = $result->fetch_assoc()) {
        $sheet->setCellValue('A' . $row, htmlspecialchars($teacher['employee_no']));
        $sheet->setCellValue('B' . $row, htmlspecialchars($teacher['teacher_name']));
        $sheet->setCellValue('C' . $row, htmlspecialchars(formatPosition($teacher['position'])));

        $style = [
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];
        $sheet->getStyle('A' . $row . ':C' . $row)->applyFromArray($style);
        $row++;
    }

    // Output Excel file
    $writer = ExcelIOFactory::createWriter($spreadsheet, 'Xlsx');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="personnel_list.xlsx"');
    header('Cache-Control: max-age=0');
    $writer->save('php://output');
    exit;
}

// =============================
// PDF EXPORT USING HEADER.PDF (FPDI)
// =============================
else {
    $header_pdf = 'template/header.pdf'; // Converted from your header.docx

    $pdf = new Fpdi();

    // ✅ Disable TCPDF's default header and footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    $pdf->AddPage();

    // Import the header design
    if (file_exists($header_pdf)) {
        $pageCount = $pdf->setSourceFile($header_pdf);
        $tplIdx = $pdf->importPage(1);
        $pdf->useTemplate($tplIdx, 0, 0, 210); // Full A4 width
    } else {
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'Header template not found', 0, 1, 'C');
    }

    // Move below the header (adjust spacing)
    $pdf->Ln(45);

    // Title
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'List of Personnel', 0, 1, 'C');
    $pdf->Ln(5);

    // Table
    $pdf->SetFont('helvetica', '', 11);
    $html = '<table border="1" cellpadding="4">
                <thead>
                    <tr style="background-color:#f2f2f2;">
                        <th width="20%" align="center"><b>EMPLOYEE NO.</b></th>
                        <th width="50%" align="center"><b>EMPLOYEE NAME</b></th>
                        <th width="30%" align="center"><b>POSITION TITLE</b></th>
                    </tr>
                </thead>
                <tbody>';

    while ($teacher = $result->fetch_assoc()) {
        $html .= '<tr>
                    <td width="20%" align="center">' . htmlspecialchars($teacher['employee_no']) . '</td>
                    <td width="50%" align="center">' . htmlspecialchars($teacher['teacher_name']) . '</td>
                    <td width="30%" align="center">' . htmlspecialchars(formatPosition($teacher['position'])) . '</td>
                  </tr>';
    }

    $html .= '</tbody></table>';
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('personnel_list.pdf', 'D');
    exit;
}
?>