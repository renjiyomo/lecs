<?php
include 'lecs_db.php';
session_start();

// Restrict access to teachers only
if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 't') {
    header("Location: /lecs/Landing/Login/login.php");
    exit;
}

$teacher_id = intval($_SESSION['teacher_id']);
$success_message = '';
$error_message = '';

// Handle form submission for updating email and password
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $new_password = $_POST['password'];

    if (!empty($new_email)) {
        // Update email
        $stmt = $conn->prepare("UPDATE teachers SET email = ? WHERE teacher_id = ?");
        $stmt->bind_param("si", $new_email, $teacher_id);
        if ($stmt->execute()) {
            $success_message = "Email updated successfully.";
        } else {
            $error_message = "Failed to update email.";
        }
        $stmt->close();
    }

    if (!empty($new_password)) {
        // Update password (assuming password is hashed)
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE teachers SET password = ? WHERE teacher_id = ?");
        $stmt->bind_param("si", $hashed_password, $teacher_id);
        if ($stmt->execute()) {
            $success_message = !empty($success_message) ? $success_message . " Password updated successfully." : "Password updated successfully.";
        } else {
            $error_message = !empty($error_message) ? $error_message . " Failed to update password." : "Failed to update password.";
        }
        $stmt->close();
    }
}

// Fetch teacher details
$stmt = $conn->prepare("SELECT CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name) AS full_name, employee_no, email, gender, birthdate, age, contact_no, 
                               house_no_street, barangay, municipality, province, position, image 
                        FROM teachers WHERE teacher_id = ? LIMIT 1");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();

$teacherName = htmlspecialchars($teacher['full_name'] ?? "Unknown Teacher");
$teacherPos = htmlspecialchars($teacher['position'] ?? "Teacher");
$employeeNo = htmlspecialchars($teacher['employee_no'] ?? "N/A");
$email = htmlspecialchars($teacher['email'] ?? "");
$contactNo = htmlspecialchars($teacher['contact_no'] === 'N/A' || empty($teacher['contact_no']) ? 'N/A' : $teacher['contact_no']);
$gender = htmlspecialchars($teacher['gender'] ?? "N/A");
$birthdate = htmlspecialchars($teacher['birthdate'] === 'N/A' || empty($teacher['birthdate']) || $teacher['birthdate'] === '0000-00-00' ? 'N/A' : $teacher['birthdate']);
$age = htmlspecialchars($teacher['age'] === 'N/A' || empty($teacher['age']) ? 'N/A' : $teacher['age']);

// Build structured address
$addressParts = [];
$hasValidAddress = false;

if ($teacher['house_no_street'] !== 'N/A' && !empty($teacher['house_no_street'])) {
    $addressParts[] = $teacher['house_no_street'];
    $hasValidAddress = true;
}
if ($teacher['barangay'] !== 'N/A' && !empty($teacher['barangay'])) {
    $addressParts[] = "Brgy. " . $teacher['barangay'];
    $hasValidAddress = true;
}
if ($teacher['municipality'] !== 'N/A' && !empty($teacher['municipality'])) {
    $addressParts[] = $teacher['municipality'];
    $hasValidAddress = true;
}
if ($teacher['province'] !== 'N/A' && !empty($teacher['province'])) {
    $addressParts[] = $teacher['province'];
    $hasValidAddress = true;
}

$address = $hasValidAddress ? htmlspecialchars(implode(", ", $addressParts)) : "No address provided";

$imagePath = "Uploads/teachers/" . ($teacher['image'] ?? "teacher.png");
if (!file_exists(__DIR__ . "/" . $imagePath)) {
    $imagePath = "Uploads/teachers/teacher.png";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile | LECS Online Student Grading System</title>
    <link rel="icon" href="images/lecs-logo no bg.png" type="image/x-icon">
    <link rel="stylesheet" href="css/profile.css">
    <link rel="stylesheet" href="css/all.min.css">
    <?php include 'theme-script.php'; ?>
</head>
<body>
<div class="container">
    <?php include 'teacherSidebar.php'; ?>
    
    <div class="main-content">
        <div class="profile-header">
            <a href="teacherDashboard.php" class="back-arrow">←</a>
            <h1>Profile</h1>
        </div>

        <?php if ($success_message): ?>
            <div class="success-message"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <div class="btn-row">
            <a href="edit_profile.php" class="edit-btn">Edit Profile</a>
        </div>

        <div class="profile-grid">
            <!-- LEFT CARD -->
            <div class="card left-card">
                <div class="profile-header-flex">
                    <img src="<?= htmlspecialchars($imagePath); ?>" alt="Profile Picture" class="profile-img">
                    <div class="profile-info">
                        <h2><?= $teacherName ?></h2>
                        <p class="teacher-position"><?= $teacherPos ?></p>
                    </div>
                </div>

                <div class="divider"></div>

                <h4 class="basic-details">Basic Information</h4>
                <div class="basic-info">
                    <div class="row"><span class="label">Employee Number</span><span class="value"><?= $employeeNo ?></span></div>
                    <div class="row"><span class="label">Contact Number</span><span class="value"><?= $contactNo ?></span></div>
                    <div class="row"><span class="label">Gender</span><span class="value"><?= $gender ?></span></div>
                    <div class="row"><span class="label">Birthdate</span><span class="value"><?= $birthdate ?></span></div>
                    <div class="row"><span class="label">Age</span><span class="value"><?= $age ?></span></div>
                </div>
            </div>

            <!-- RIGHT CARDS -->
            <div class="right-section">
                <div class="card">
                    <div class="acc-details">
                        <h3>Account Details</h3>
                    </div>
            
                    <form method="POST" action="">
                        <div class="input-row">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?= $email ?>">
                        </div>
                        <div class="input-row">
                            <label for="password">Password</label>
                            <div class="password-wrapper">
                                <input type="password" id="password" name="password" placeholder="Enter new password">
                                <button type="button" class="toggle-pass">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="button-row">
                            <button type="submit" class="save-btn">Save Changes</button>
                        </div>
                    </form>
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
<script>
    document.querySelectorAll('.toggle-pass').forEach(button => {
        button.addEventListener('click', () => {
            const input = button.previousElementSibling;
            const icon = button.querySelector("i");

            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        });
    });
</script>

</body>
</html>