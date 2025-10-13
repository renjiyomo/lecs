<?php
require 'vendor/autoload.php';
include 'lecs_db.php';
session_start();

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

header('Content-Type: application/json');

if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 't') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$teacher_id = intval($_SESSION['teacher_id']);
$sy_id = intval($_POST['sy_id'] ?? 0);
$section_id = intval($_POST['section_id'] ?? 0);

if (!isset($_FILES['sf1_files']) || $sy_id === 0 || $section_id === 0) {
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

$allowed_ext = ['xls', 'xlsx'];
$imported = 0;
$skipped = 0;

// Function to normalize address names properly
function normalize_address($str) {
    $str = trim($str);
    if ($str === '') return $str;

    // Convert to Proper Case first
    $str = ucwords(strtolower($str));

    // Standardize "(Pob.)" and variations → "(Poblacion)"
    $str = preg_replace('/\(\s*pob\.?\s*\)/i', '(Poblacion)', $str);

    // Fix Roman numerals in Zone names (e.g., Zone Vii → Zone VII)
    $str = preg_replace_callback('/\b(Zone\s+)([ivxlcdm]+)\b/i', function ($matches) {
        return $matches[1] . strtoupper($matches[2]);
    }, $str);

    return $str;
}

try {
    foreach ($_FILES['sf1_files']['tmp_name'] as $index => $tmpName) {
        $filename = $_FILES['sf1_files']['name'][$index];
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        if (!in_array($ext, $allowed_ext)) {
            $skipped++;
            continue;
        }

        $spreadsheet = IOFactory::load($tmpName);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        foreach ($rows as $i => $row) {
            // Start at row 7 (index 6 since array is 0-based)
            if ($i < 6) continue;

            $lrn = preg_replace('/\D/', '', trim($row[0] ?? '')); // Column A

            // Skip rows without a valid 12-digit LRN
            if ($lrn === "" || strlen($lrn) != 12) {
                $skipped++;
                continue;
            }

            // Full Name (Column C)
            $full_name = trim($row[2] ?? '');
            $last_name = $first_name = $middle_name = "";

            if (!empty($full_name)) {
                $name_parts = array_map('trim', explode(",", $full_name));
                if (count($name_parts) >= 2) {
                    $last_name = $name_parts[0];
                    $first_name = $name_parts[1];
                    $middle_name = $name_parts[2] ?? "";
                }
            }

            // Sex (G)
            $sex = strtoupper(trim($row[6] ?? ''));
            $sex = ($sex == "M" || $sex == "MALE") ? "Male" : "Female";

            // Birthdate (H)
            $birth_raw = trim($row[7] ?? '');
            $birthdate = null;

            if ($birth_raw !== '') {
                if (is_numeric($birth_raw)) {
                    // Excel serial date → convert properly
                    try {
                        $birthdate = ExcelDate::excelToDateTimeObject($birth_raw)->format('Y-m-d');
                    } catch (Exception $e) {
                        $birthdate = null;
                    }
                } else {
                    // Normalize separators to "/" for consistency
                    $normalized = str_replace(['-', '.'], '/', $birth_raw);

                    // Try multiple formats
                    $formats = [
                        'm/d/Y', 'd/m/Y', 'Y-m-d', 'Y/m/d',
                        'm-d-Y', 'd-m-Y', 'd-M-Y', 'M d, Y'
                    ];
                    $parsed = false;

                    foreach ($formats as $fmt) {
                        $dt = DateTime::createFromFormat($fmt, $normalized);
                        if ($dt !== false) {
                            $birthdate = $dt->format('Y-m-d');
                            $parsed = true;
                            break;
                        }
                    }

                    // Last resort: strtotime
                    if (!$parsed) {
                        $ts = strtotime($birth_raw);
                        if ($ts && $ts > 0) {
                            $birthdate = date("Y-m-d", $ts);
                        }
                    }
                }

                // Extra validation → reject impossible/future dates
                if ($birthdate) {
                    $year = (int)date("Y", strtotime($birthdate));
                    if ($year < 1900 || $year > date("Y")) {
                        $birthdate = null;
                    }
                }
            }

            // Age (J)
            $age = intval($row[9] ?? 0);

            // Other details
            $mother_tongue = trim($row[11] ?? '');
            $ip_ethnicity = trim($row[13] ?? '');
            $religion = trim($row[14] ?? '');
            $house_no_street = trim($row[15] ?? '');

            // Normalize address values
            $barangay = normalize_address($row[17] ?? '');
            $municipality = normalize_address($row[20] ?? '');
            $province = normalize_address($row[22] ?? '');

            $father_name = trim($row[27] ?? '');
            $mother_name = trim($row[31] ?? '');
            $guardian_name = trim($row[36] ?? '');
            $relationship = trim($row[40] ?? '');
            $contact_number = trim($row[41] ?? '');
            $learning_modality = trim($row[43] ?? '');
            $remarks = trim($row[44] ?? '');

            // Check if LRN already exists for this SY
            $check = $conn->query("SELECT pupil_id FROM pupils WHERE lrn='$lrn' AND sy_id=$sy_id");
            if ($check->num_rows > 0) {
                $skipped++;
                continue;
            }

            $sql = "INSERT INTO pupils 
                (teacher_id, lrn, last_name, first_name, middle_name, sex, birthdate, age,
                 mother_tongue, ip_ethnicity, religion,
                 house_no_street, barangay, municipality, province,
                 father_name, mother_name, guardian_name, relationship_to_guardian,
                 contact_number, learning_modality, remarks,
                 sy_id, section_id, status)
                VALUES 
                ($teacher_id,'$lrn','$last_name','$first_name','$middle_name','$sex',
                 " . ($birthdate ? "'$birthdate'" : "NULL") . ",
                 $age,
                 '$mother_tongue','$ip_ethnicity','$religion',
                 '$house_no_street','$barangay','$municipality','$province',
                 '$father_name','$mother_name','$guardian_name','$relationship',
                 '$contact_number','$learning_modality','$remarks',
                 $sy_id,$section_id,'enrolled')";

            if ($conn->query($sql)) {
                $imported++;
            } else {
                $skipped++;
            }
        }
    }
    echo json_encode(['imported' => $imported, 'skipped' => $skipped]);
} catch (Exception $e) {
    echo json_encode(['error' => "Error processing file: " . $e->getMessage()]);
}
?>