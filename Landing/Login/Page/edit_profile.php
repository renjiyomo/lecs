<?php
include 'lecs_db.php';
session_start();

// Restrict access to logged-in teacher only
if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 't') {
    header("Location: /lecs/Landing/Login/login.php");
    exit();
}

$teacher_id = intval($_SESSION['teacher_id']);
$error_message = "";

// Fetch teacher details
$stmt = $conn->prepare("SELECT first_name, middle_name, last_name, employee_no, email, contact_no, position, image, gender, birthdate, age, house_no_street, barangay, municipality, province, password 
                        FROM teachers WHERE teacher_id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    header("Location: teacherProfile.php?error=Teacher not found");
    exit();
}
$teacher = $result->fetch_assoc();
$stmt->close();

$first_name = htmlspecialchars($teacher['first_name']);
$middle_name = htmlspecialchars($teacher['middle_name'] ?? '');
$last_name = htmlspecialchars($teacher['last_name']);
$employee_no = htmlspecialchars($teacher['employee_no']);
$email = htmlspecialchars($teacher['email'] ?? '');
$contact_no = htmlspecialchars($teacher['contact_no'] === 'N/A' ? '' : $teacher['contact_no']);
$gender = htmlspecialchars($teacher['gender'] ?? '');
$birthdate = htmlspecialchars($teacher['birthdate'] === 'N/A' || $teacher['birthdate'] === '0000-00-00' ? '' : $teacher['birthdate']);
$age = htmlspecialchars($teacher['age'] === 'N/A' ? '' : $teacher['age']);
$house_no_street = htmlspecialchars($teacher['house_no_street'] === 'N/A' ? '' : $teacher['house_no_street']);
$barangay = htmlspecialchars($teacher['barangay'] === 'N/A' ? '' : $teacher['barangay']);
$municipality = htmlspecialchars($teacher['municipality'] === 'N/A' ? '' : $teacher['municipality']);
$province = htmlspecialchars($teacher['province'] === 'N/A' ? '' : $teacher['province']);
$position_name = htmlspecialchars($teacher['position'] ?? 'Teacher');
$password_placeholder = $teacher['password'] ? '••••••••' : ''; // Masked placeholder for existing password

$preview_image = ($teacher['image'] && file_exists("Uploads/teachers/" . $teacher['image']))
    ? "Uploads/teachers/" . $teacher['image']
    : "Uploads/teachers/teacher.png";

if (isset($_SESSION['preview_image'])) {
    $preview_image = $_SESSION['preview_image'];
}

// Handle image removal
if (isset($_GET['remove_image'])) {
    if (isset($_SESSION['preview_image']) && file_exists($_SESSION['preview_image'])) {
        unlink($_SESSION['preview_image']);
        unset($_SESSION['preview_image']);
    } else {
        $final_image = "teacher.png";
        if ($teacher['image'] && $teacher['image'] != "teacher.png" && file_exists("Uploads/teachers/" . $teacher['image'])) {
            unlink("Uploads/teachers/" . $teacher['image']);
        }
        $stmt = $conn->prepare("UPDATE teachers SET image = ? WHERE teacher_id = ?");
        $stmt->bind_param("si", $final_image, $teacher_id);
        $stmt->execute();
        $stmt->close();
    }
    echo json_encode(["status" => "removed"]);
    exit();
}

// Handle form submission
if (isset($_POST['submit'])) {
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $last_name = trim($_POST['last_name']);
    $employee_no = trim($_POST['employee_no']);
    $email = trim($_POST['email']);
    $contact_no = trim($_POST['contact_no']) ?: 'N/A';
    $gender = trim($_POST['gender']);
    $birthdate = trim($_POST['birthdate']) ?: 'N/A';
    $age = trim($_POST['age']) ?: 'N/A';
    $house_no_street = trim($_POST['house_no_street']) ?: 'N/A';
    $barangay = trim($_POST['barangay']) ?: 'N/A';
    $municipality = trim($_POST['municipality']) ?: 'N/A';
    $province = trim($_POST['province']) ?: 'N/A';
    $password = $teacher['password'];

    // Validate email
    if (empty($email)) {
        $error_message = "Email is required!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format!";
    }

    // Validate password
    if (!empty($_POST['password']) || !empty($_POST['confirm_password'])) {
        if ($_POST['password'] !== $_POST['confirm_password']) {
            $error_message = "Passwords do not match!";
        } else {
            $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        }
    }

    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $image_name = "temp_" . uniqid() . "." . $ext;
        $upload_path = "Uploads/temp/" . $image_name;

        if (!is_dir("Uploads/temp")) {
            mkdir("Uploads/temp", 0777, true);
        }

        move_uploaded_file($_FILES['image']['tmp_name'], $upload_path);
        $_SESSION['preview_image'] = $upload_path;
        $preview_image = $upload_path;
    }

    // Update teacher data if no errors
    if (empty($error_message)) {
        $final_image = $teacher['image'];
        if (isset($_SESSION['preview_image']) && file_exists($_SESSION['preview_image'])) {
            $ext = pathinfo($_SESSION['preview_image'], PATHINFO_EXTENSION);
            $final_image = uniqid() . "." . $ext;
            if ($teacher['image'] && $teacher['image'] != "teacher.png" && file_exists("Uploads/teachers/" . $teacher['image'])) {
                unlink("Uploads/teachers/" . $teacher['image']);
            }
            rename($_SESSION['preview_image'], "Uploads/teachers/" . $final_image);
            unset($_SESSION['preview_image']);
        }

        $stmt = $conn->prepare("UPDATE teachers SET 
            first_name = ?, middle_name = ?, last_name = ?, employee_no = ?, email = ?, 
            contact_no = ?, password = ?, image = ?, gender = ?, 
            birthdate = ?, age = ?, house_no_street = ?, barangay = ?, 
            municipality = ?, province = ? WHERE teacher_id = ?");
        $stmt->bind_param("sssssssssssssssi", $first_name, $middle_name, $last_name, 
            $employee_no, $email, $contact_no, $password, $final_image, 
            $gender, $birthdate, $age, $house_no_street, $barangay, $municipality, $province, $teacher_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            header("Location: teacherProfile.php?success=Profile updated successfully");
            exit();
        } else {
            $error_message = "Failed to update profile: " . $conn->error;
            $stmt->close();
        }
    }
}

// Fetch provinces for address dropdown
$stmt = $conn->prepare("SELECT DISTINCT province FROM ph_addresses ORDER BY province ASC");
$stmt->execute();
$provinces = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile | LECS Online Student Grading System</title>
    <link rel="icon" href="images/lecs-logo no bg.png" type="image/x-icon">
    <link href="css/sidebar.css" rel="stylesheet">
    <link href="css/adminAddTeacher.css" rel="stylesheet">
    <link href="css/all.min.css" rel="stylesheet">
</head>
<body class="light">
<div class="container">
    <?php include 'teacherSidebar.php'; ?>

    <div class="main-content">
        <div class="header">
            <h1><a href="teacherProfile.php" style="text-decoration:none;">← Edit Profile</a></h1>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="add-form">
            <div class="form-section">
                <h2>Account Details</h2>
                <div class="input-group">
                    <input type="email" name="email" placeholder="Email" value="<?= $email ?>" required>
                    <div class="password-field">
                        <input type="password" name="password" placeholder="Enter new password or leave blank" id="password" value="">
                        <i class="fa-solid fa-eye" onclick="togglePassword('password', this)"></i>
                    </div>
                    <div class="password-field">
                        <input type="password" name="confirm_password" placeholder="Confirm new password" id="confirm_password">
                        <i class="fa-solid fa-eye" onclick="togglePassword('confirm_password', this)"></i>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h2>Personal Information</h2>
                <div class="profile-upload">
                    <img src="<?= $preview_image ?>" id="preview-image" alt="Profile Image">
                    <input type="file" name="image" accept="image/png, image/jpg, image/jpeg, image/svg" onchange="previewImage(event)">
                    <div class="upload-buttons">
                        <button type="button" onclick="document.querySelector('[name=image]').click()">Upload</button>
                        <button type="button" class="btn-remove" onclick="removeImage()">Remove</button>
                    </div>
                </div>
                <div class="input-group">
                    <input type="text" name="employee_no" placeholder="Employee No" required value="<?= $employee_no ?>">
                    <input type="text" name="first_name" placeholder="First Name" required value="<?= $first_name ?>">
                    <input type="text" name="middle_name" placeholder="Middle Name" value="<?= $middle_name ?>">
                    <input type="text" name="last_name" placeholder="Last Name" required value="<?= $last_name ?>">
                </div>
                <div class="input-group">
                    <input type="text" name="contact_no" placeholder="Contact Number" value="<?= $contact_no ?>">
                    <select name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male" <?= ($gender == "Male" ? "selected" : "") ?>>Male</option>
                        <option value="Female" <?= ($gender == "Female" ? "selected" : "") ?>>Female</option>
                        <option value="Other" <?= ($gender == "Other" ? "selected" : "") ?>>Other</option>
                    </select>
                    <input type="date" name="birthdate" id="birthdate" value="<?= $birthdate ?>" onchange="calculateAge()">
                    <input type="number" name="age" id="age" placeholder="Age" value="<?= $age ?>">
                    <input type="text" name="position" id="position" value="<?= $position_name ?>" readonly>
                </div>
            </div>

            <div class="form-section">
                <h2>Address</h2>
                <div class="input-group">
                    <input type="text" name="house_no_street" placeholder="House No. / Street" value="<?= $house_no_street ?>">
                    <select name="province" id="province">
                        <option value="">Select Province</option>
                        <?php
                        $provinces->data_seek(0);
                        while ($row = $provinces->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($row['province']) ?>" <?= ($province == $row['province'] ? 'selected' : '') ?>>
                                <?= htmlspecialchars($row['province']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <select name="municipality" id="municipality">
                        <option value="">Select Municipality/City</option>
                        <?php if (!empty($teacher['province'])) {
                            $stmt = $conn->prepare("SELECT DISTINCT municipality FROM ph_addresses WHERE province = ? ORDER BY municipality ASC");
                            $stmt->bind_param("s", $teacher['province']);
                            $stmt->execute();
                            $res = $stmt->get_result();
                            while ($row = $res->fetch_assoc()) {
                                $sel = ($municipality == $row['municipality']) ? "selected" : "";
                                echo "<option value='{$row['municipality']}' $sel>{$row['municipality']}</option>";
                            }
                            $stmt->close();
                        } ?>
                    </select>
                    <select name="barangay" id="barangay">
                        <option value="">Select Barangay</option>
                        <?php if (!empty($teacher['municipality'])) {
                            $stmt = $conn->prepare("SELECT DISTINCT barangay FROM ph_addresses WHERE municipality = ? ORDER BY barangay ASC");
                            $stmt->bind_param("s", $teacher['municipality']);
                            $stmt->execute();
                            $res = $stmt->get_result();
                            while ($row = $res->fetch_assoc()) {
                                $sel = ($barangay == $row['barangay']) ? "selected" : "";
                                echo "<option value='{$row['barangay']}' $sel>{$row['barangay']}</option>";
                            }
                            $stmt->close();
                        } ?>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" onclick="window.location='teacherProfile.php'">Cancel</button>
                <button type="submit" name="submit">Update Profile</button>
            </div>
        </form>
    </div>
</div>

<div id="errorModal" class="modal">
    <div class="modal-content">
        <h2>Error</h2>
        <p id="errorText"></p>
        <button onclick="closeModal()">OK</button>
    </div>
</div>

<script>
function previewImage(event) {
    const output = document.getElementById('preview-image');
    output.src = URL.createObjectURL(event.target.files[0]);
}

function removeImage() {
    fetch("edit_profile.php?remove_image=1")
        .then(res => res.json())
        .then(data => {
            if (data.status === "removed") {
                document.getElementById('preview-image').src = "Uploads/teachers/teacher.png";
                document.querySelector('[name=image]').value = '';
            }
        });
}

function togglePassword(fieldId, icon) {
    const input = document.getElementById(fieldId);
    if (input.type === "password") {
        input.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    } else {
        input.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
}

function showError(message) {
    document.getElementById("errorText").innerText = message;
    document.getElementById("errorModal").style.display = "flex";
}

function closeModal() {
    document.getElementById("errorModal").style.display = "none";
}

function calculateAge() {
    const birthdate = document.getElementById("birthdate").value;
    if (birthdate) {
        const today = new Date();
        const birthDateObj = new Date(birthdate);
        let age = today.getFullYear() - birthDateObj.getFullYear();
        const m = today.getMonth() - birthDateObj.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthDateObj.getDate())) {
            age--;
        }
        document.getElementById("age").value = age;
    }
}

document.getElementById('province').addEventListener('change', function() {
    const province = this.value;
    fetch('get_municipalities.php?province=' + encodeURIComponent(province))
        .then(res => res.text())
        .then(data => {
            document.getElementById('municipality').innerHTML = data;
            document.getElementById('barangay').innerHTML = '<option value="">Select Barangay</option>';
        });
});

document.getElementById('municipality').addEventListener('change', function() {
    const municipality = this.value;
    fetch('get_barangays.php?municipality=' + encodeURIComponent(municipality))
        .then(res => res.text())
        .then(data => {
            document.getElementById('barangay').innerHTML = data;
        });
});

window.addEventListener('DOMContentLoaded', function() {
    const savedProvince = "<?= $province ?>";
    const savedMunicipality = "<?= $municipality ?>";
    const savedBarangay = "<?= $barangay ?>";

    if (savedProvince) {
        fetch('get_municipalities.php?province=' + encodeURIComponent(savedProvince))
            .then(res => res.text())
            .then(data => {
                document.getElementById('municipality').innerHTML = data;
                if (savedMunicipality) {
                    document.getElementById('municipality').value = savedMunicipality;
                    fetch('get_barangays.php?municipality=' + encodeURIComponent(savedMunicipality))
                        .then(res => res.text())
                        .then(data => {
                            document.getElementById('barangay').innerHTML = data;
                            if (savedBarangay) {
                                document.getElementById('barangay').value = savedBarangay;
                            }
                        });
                }
            });
    }
    <?php if (!empty($error_message)) { ?>
        showError('<?= $error_message ?>');
    <?php } ?>
});
</script>

</body>
</html>