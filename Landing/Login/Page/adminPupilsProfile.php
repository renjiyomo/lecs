<?php 
include 'lecs_db.php';
session_start();

if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 'a') {
    header("Location: /lecs/Landing/Login/login.php");
    exit;
}

$teacher_id = intval($_SESSION['teacher_id']);

if (isset($_GET['pupil_id'])) {
    $pupil_id = intval($_GET['pupil_id']);
    $stmt = $conn->prepare("SELECT p.*, CONCAT(t.first_name, ' ', COALESCE(t.middle_name, ''), ' ', t.last_name) AS principal_name, t.position 
                           FROM pupils p 
                           LEFT JOIN teachers t ON t.teacher_id = ? 
                           WHERE p.pupil_id = ?");
    $stmt->bind_param("ii", $teacher_id, $pupil_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $pupil = $result->fetch_assoc();

    if (!$pupil) {
        die("Pupil not found.");
    }
} else {
    die("No pupil ID provided.");
}

// Build structured address
$addressParts = [];
$hasValidAddress = false;

if (!empty($pupil['house_no_street']) && $pupil['house_no_street'] !== 'N/A') {
    $addressParts[] = htmlspecialchars($pupil['house_no_street']);
    $hasValidAddress = true;
}
if (!empty($pupil['barangay']) && $pupil['barangay'] !== 'N/A') {
    $addressParts[] = "Brgy. " . htmlspecialchars($pupil['barangay']);
    $hasValidAddress = true;
}
if (!empty($pupil['municipality']) && $pupil['municipality'] !== 'N/A') {
    $addressParts[] = htmlspecialchars($pupil['municipality']);
    $hasValidAddress = true;
}
if (!empty($pupil['province']) && $pupil['province'] !== 'N/A') {
    $addressParts[] = htmlspecialchars($pupil['province']);
    $hasValidAddress = true;
}
$address = $hasValidAddress ? implode(", ", $addressParts) : "No address provided";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pupil Details | LECS Online Student Grading System</title>
    <link rel="icon" href="images/lecs-logo no bg.png" type="image/x-icon">
    <link rel="stylesheet" href="css/pupilsProfile.css">
    <link rel="stylesheet" href="css/all.min.css">
    <?php include 'theme-script.php'; ?>
</head>
<body>
<div class="container">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div id="adminHeader" class="profile-header">
            <a href="adminPupils.php" class="back-arrow">←</a>
            <h1>Pupil Details</h1>
        </div>

        <div class="profile-grid">
            <div class="card left-card">
                <div class="school-info">
                    <div class="school-details">
                        <h2 class="centered-name"><?= htmlspecialchars($pupil['first_name'] . " " . ($pupil['middle_name'] ? $pupil['middle_name'] . " " : "") . $pupil['last_name']) ?></h2>
                    </div>
                </div>

                <div class="divider"></div>

                <h4 class="basic-details">Basic Information</h4>
                <div class="basic-info">
                    <div class="row"><span class="label">LRN</span><span class="value"><?= htmlspecialchars($pupil['lrn']) ?></span></div>
                    <div class="row"><span class="label">Sex</span><span class="value"><?= htmlspecialchars($pupil['sex']) ?></span></div>
                    <div class="row"><span class="label">Date of Birth</span><span class="value"><?= date('F d, Y', strtotime($pupil['birthdate'])) ?></span></div>
                    <div class="row"><span class="label">Age</span><span class="value"><?= htmlspecialchars($pupil['age']) ?></span></div>
                    <div class="row"><span class="label">Religion</span><span class="value"><?= htmlspecialchars($pupil['religion']) ?></span></div>
                    <div class="row"><span class="label">Mother Tongue</span><span class="value"><?= htmlspecialchars($pupil['mother_tongue']) ?></span></div>
                    <div class="row"><span class="label">Learning Modality</span><span class="value"><?= htmlspecialchars($pupil['learning_modality']) ?></span></div>   
                </div>
            </div>

            <!-- RIGHT CARDS -->
            <div class="right-section">
                <div class="card">
                    <div class="acc-details">
                        <h3>Parent Information</h3>
                    </div>
                    <div class="parent-info">
                        <!-- Father -->
                        <div class="parent-box">
                            <div class="parent-left">
                                <div class="parent-name">
                                    <?= !empty($pupil['father_name']) ? htmlspecialchars($pupil['father_name']) : 'N/A'; ?>
                                </div>
                                <div class="parent-role">Father</div>
                            </div>
                        </div>

                        <!-- Mother -->
                        <div class="parent-box">
                            <div class="parent-left">
                                <div class="parent-name">
                                    <?= !empty($pupil['mother_name']) ? htmlspecialchars($pupil['mother_name']) : 'N/A'; ?>
                                </div>
                                <div class="parent-role">Mother</div>
                            </div>
                        </div>

                        <!-- Guardian -->
                        <div class="parent-box">
                            <div class="parent-left">
                                <div class="parent-name">
                                    <?= !empty($pupil['guardian_name']) ? htmlspecialchars($pupil['guardian_name']) : 'N/A'; ?>
                                </div>
                                <div class="parent-role">
                                    Guardian (<?= !empty($pupil['relationship_to_guardian']) ? htmlspecialchars($pupil['relationship_to_guardian']) : 'N/A'; ?>)
                                </div>
                            </div>
                            <div class="parent-right">
                                <div class="contact-label">Contact Number</div>
                                <div class="contact-value">
                                    <?= !empty($pupil['contact_number']) ? htmlspecialchars($pupil['contact_number']) : 'N/A'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="acc-details">
                        <h3>Address</h3>
                    </div>
                    <div class="address-box">
                        <i class="fa-solid fa-location-dot"></i>
                        <span><?= $address ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
</body>
</html>