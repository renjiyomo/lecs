<?php
include 'lecs_db.php';
header('Content-Type: application/json');

$lrn = $_GET['lrn'] ?? '';

if (empty($lrn)) {
    echo json_encode(['success' => false, 'message' => 'No LRN provided.']);
    exit;
}

// Fetch the *latest* pupil record with this LRN
// Uses highest pupil_id as "latest" or latest sy_id if your system supports it
$query = "
    SELECT *
    FROM pupils
    WHERE lrn = '$lrn'
    ORDER BY sy_id DESC, pupil_id DESC
    LIMIT 1
";

$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    $pupil = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'lrn' => $pupil['lrn'],
        'last_name' => $pupil['last_name'],
        'first_name' => $pupil['first_name'],
        'middle_name' => $pupil['middle_name'],
        'sex' => $pupil['sex'],
        'birthdate' => $pupil['birthdate'],
        'age' => $pupil['age'],
        'mother_tongue' => $pupil['mother_tongue'],
        'ip_ethnicity' => $pupil['ip_ethnicity'],
        'religion' => $pupil['religion'],

        'house_no_street' => $pupil['house_no_street'],
        'barangay' => $pupil['barangay'],
        'municipality' => $pupil['municipality'],
        'province' => $pupil['province'],

        'father_name' => $pupil['father_name'],
        'mother_name' => $pupil['mother_name'],
        'guardian_name' => $pupil['guardian_name'],
        'relationship_to_guardian' => $pupil['relationship_to_guardian'],
        'contact_number' => $pupil['contact_number']
    ]);
} else {
    echo json_encode(['success' => false]);
}
$conn->close();
?>
