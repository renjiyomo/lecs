<?php
include 'lecs_db.php';
session_start();

$email = $employee_no = $contact_no = $user_type = "";
$gender = $birthdate = $age = "";
$house_no_street = $barangay = $municipality = $province = "";
$password = $confirm_password = "";
$position_id = $start_date = $end_date = "";
$first_name = $middle_name = $last_name = "";
$with_account = "yes";
$error_message = "";
$preview_image = "Uploads/teachers/teacher.png";

if (isset($_SESSION['preview_image'])) {
    $preview_image = $_SESSION['preview_image'];
}

if (isset($_GET['cancel'])) {
    if (isset($_SESSION['preview_image']) && file_exists($_SESSION['preview_image'])) {
        unlink($_SESSION['preview_image']);
        unset($_SESSION['preview_image']);
    }
    header("Location: adminTeachers.php");
    exit();
}

if (isset($_GET['remove_image'])) {
    if (isset($_SESSION['preview_image']) && file_exists($_SESSION['preview_image'])) {
        unlink($_SESSION['preview_image']);
        unset($_SESSION['preview_image']);
    }
    echo json_encode(["status" => "removed"]);
    exit();
}

$is_principal = false;
$is_non_teaching = false;
if (isset($_POST['submit'])) {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $last_name = trim($_POST['last_name']);
    $full_name = implode(' ', array_filter([$first_name, $middle_name, $last_name]));
    $employee_no = trim($_POST['employee_no']);
    $contact_no = trim($_POST['contact_no']) ?: 'N/A';
    $position_id = trim($_POST['position_id']);
    $user_type = trim($_POST['user_type']);
    $with_account = trim($_POST['with_account'] ?? 'yes');
    $gender = trim($_POST['gender']);
    $birthdate = trim($_POST['birthdate']) ?: 'N/A';
    $age = trim($_POST['age']) ?: 'N/A';
    $house_no_street = trim($_POST['house_no_street']) ?: 'N/A';
    $barangay = trim($_POST['barangay']) ?: 'N/A';
    $municipality = trim($_POST['municipality']) ?: 'N/A';
    $province = trim($_POST['province']) ?: 'N/A';
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '') ?: 'N/A';

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

    // Get position name from positions table
    $position_name = '';
    if ($position_id) {
        $stmt = $conn->prepare("SELECT position_name FROM positions WHERE position_id = ?");
        $stmt->bind_param("i", $position_id);
        $stmt->execute();
        $pos_res = $stmt->get_result();
        if ($pos_res->num_rows > 0) {
            $pos_row = $pos_res->fetch_assoc();
            $position_name = $pos_row['position_name'];
            if (strpos($pos_row['position_name'], 'Principal') !== false) {
                $is_principal = true;
            }
        }
        $stmt->close();
    }

    $is_non_teaching = ($user_type === 'n');
    $show_term = true;
    $hashed_password = null;

    if ($with_account === 'no' && $is_non_teaching) {
        $email = '';
    } else {
        if (empty($email) || empty($password)) {
            $error_message = "Email and password are required!";
        } elseif ($password !== $confirm_password) {
            $error_message = "Passwords do not match!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        }
    }

    if ($show_term && empty($start_date)) {
        $error_message = "Start date is required!";
    }

    if (empty($error_message)) {
        $final_image = 'teacher.png';
        if (isset($_SESSION['preview_image']) && file_exists($_SESSION['preview_image'])) {
            $ext = pathinfo($_SESSION['preview_image'], PATHINFO_EXTENSION);
            $final_image = uniqid() . "." . $ext;
            rename($_SESSION['preview_image'], "Uploads/teachers/" . $final_image);
            unset($_SESSION['preview_image']);
        }

        $stmt = $conn->prepare("INSERT INTO teachers 
            (first_name, middle_name, last_name, employee_no, email, password, contact_no, position, image, user_type, gender, birthdate, age, house_no_street, barangay, municipality, province) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssssssssssss", $first_name, $middle_name, $last_name, $employee_no, $email, $hashed_password, $contact_no, 
            $position_name, $final_image, $user_type, $gender, $birthdate, $age, $house_no_street, $barangay, 
            $municipality, $province);
        $stmt->execute();
        $teacher_id = $conn->insert_id;
        $stmt->close();

        if ($show_term) {
            $end_sql = ($end_date !== 'N/A') ? $end_date : null;

            if ($is_principal) {
                // Auto-end previous principal if exists
                $res = $conn->query("SELECT tp.id FROM teacher_positions tp 
                                     JOIN positions p ON tp.position_id = p.position_id 
                                     WHERE p.position_name LIKE 'Principal%' AND tp.end_date IS NULL");
                if ($res->num_rows > 0) {
                    $row = $res->fetch_assoc();
                    $auto_end = date("Y-m-d", strtotime($start_date . " -1 day"));
                    $stmt = $conn->prepare("UPDATE teacher_positions SET end_date = ? WHERE id = ?");
                    $stmt->bind_param("si", $auto_end, $row['id']);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            $stmt = $conn->prepare("INSERT INTO teacher_positions 
                (teacher_id, position_id, start_date, end_date) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $teacher_id, $position_id, $start_date, $end_sql);
            $stmt->execute();
            $stmt->close();
        }

        header("Location: adminTeachers.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Teacher</title>
<link rel="icon" href="images/lecs-logo no bg.png" type="image/x-icon">
<link href="css/sidebar.css" rel="stylesheet">
<link href="css/adminAddTeacher.css" rel="stylesheet">
<link href="css/all.min.css" rel="stylesheet">
</head>
<body class="light">
<div class="container">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="header">
            <h1><a href="adminTeachers.php" style="text-decoration:none;">← Add Personnel</a></h1>
        </div>

        <form method="POST" enctype="multipart/form-data" class="add-form">
            <div class="form-section account-header">
                <div class="account-header-row">
                    <h2>Account Details</h2>
                    <select name="user_type" id="user_type" class="small-dropdown" required onchange="toggleAccountOptions()">
                        <option value="t" <?= ($user_type=="t"?"selected":"") ?>>Teacher</option>
                        <option value="a" <?= ($user_type=="a"?"selected":"") ?>>Admin</option>
                        <option value="n" <?= ($user_type=="n"?"selected":"") ?>>Non-Teaching</option>
                    </select>
                </div>

                <div id="with-account-section" style="display: <?= ($user_type === 'n' ? 'block' : 'none') ?>;">
                    <label for="with_account">With Account?</label>
                    <select name="with_account" id="with_account" onchange="toggleAccountDetails()">
                        <option value="yes" <?= ($with_account=="yes"?"selected":"") ?>>Yes</option>
                        <option value="no" <?= ($with_account=="no"?"selected":"") ?>>No</option>
                    </select>
                </div>

                <div class="input-group" id="account-details" style="display: <?= ($user_type !== 'n' || $with_account === 'yes' ? 'grid' : 'none') ?>;">
                    <input type="email" name="email" placeholder="Email" value="<?= htmlspecialchars($email) ?>" <?= ($user_type !== 'n' || $with_account === 'yes' ? 'required' : '') ?>>
                    <div class="password-field">
                        <input type="password" name="password" placeholder="Password" id="password" value="<?= htmlspecialchars($password) ?>" <?= ($user_type !== 'n' || $with_account === 'yes' ? 'required' : '') ?>>
                        <i class="fa-solid fa-eye" onclick="togglePassword('password', this)"></i>
                    </div>
                    <div class="password-field">
                        <input type="password" name="confirm_password" placeholder="Confirm Password" id="confirm_password" value="<?= htmlspecialchars($confirm_password) ?>" <?= ($user_type !== 'n' || $with_account === 'yes' ? 'required' : '') ?>>
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
                    <input type="text" name="employee_no" placeholder="Employee No" required value="<?= htmlspecialchars($employee_no) ?>">
                    <input type="text" name="first_name" placeholder="First Name" required value="<?= htmlspecialchars($first_name) ?>">
                    <input type="text" name="middle_name" placeholder="Middle Name" value="<?= htmlspecialchars($middle_name) ?>">
                    <input type="text" name="last_name" placeholder="Last Name" required value="<?= htmlspecialchars($last_name) ?>">
                </div>
                <div class="input-group">
                    <input type="text" name="contact_no" placeholder="Contact Number" value="<?= htmlspecialchars($contact_no) ?>">
                    <select name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male" <?= ($gender=="Male"?"selected":"") ?>>Male</option>
                        <option value="Female" <?= ($gender=="Female"?"selected":"") ?>>Female</option>
                        <option value="Other" <?= ($gender=="Other"?"selected":"") ?>>Other</option>
                    </select>
                    <input type="date" name="birthdate" id="birthdate" value="<?= htmlspecialchars($birthdate) ?>" onchange="calculateAge()">
                    <input type="number" name="age" id="age" placeholder="Age" value="<?= htmlspecialchars($age) ?>">
                    <select name="position_id" id="position" required onchange="toggleTermDetails()">
                        <option value="">Select Position</option>
                        <?php
                        $res = $conn->query("SELECT * FROM positions ORDER BY position_name ASC");
                        while($row = $res->fetch_assoc()){
                            $sel = ($position_id == $row['position_id']) ? "selected" : "";
                            echo "<option value='{$row['position_id']}' $sel>{$row['position_name']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div id="term-dates" class="principal-dates-section" style="display: none;">
                    <div class="principal-term-header">
                        <h3 id="term-title">Term Details</h3>
                        <i class="fa-solid fa-info-circle info-icon" onclick="showTermModal()"></i>
                    </div>
                    <div class="principal-term-inputs">
                        <div class="input-with-label">
                            <label for="start_date" id="start-label">Start Date</label>
                            <input type="date" name="start_date" id="start_date" value="<?= htmlspecialchars($start_date) ?>">
                        </div>
                        <div class="input-with-label">
                            <label for="end_date" id="end-label">End Date (Optional)</label>
                            <input type="date" name="end_date" id="end_date" value="<?= htmlspecialchars($end_date) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h2>Address</h2>
                <div class="input-group">
                    <input type="text" name="house_no_street" placeholder="House No. / Street" value="<?= htmlspecialchars($house_no_street) ?>">
                    <select name="province" id="province">
                        <option value="">Select Province</option>
                        <?php
                        $res = $conn->query("SELECT DISTINCT province FROM ph_addresses ORDER BY province ASC");
                        while($row = $res->fetch_assoc()){
                            $sel = ($province == $row['province']) ? "selected" : "";
                            echo "<option value='{$row['province']}' $sel>{$row['province']}</option>";
                        }
                        ?>
                    </select>
                    <select name="municipality" id="municipality">
                        <option value="">Select Municipality/City</option>
                        <?php if(!empty($province)){ 
                            $res = $conn->query("SELECT DISTINCT municipality FROM ph_addresses WHERE province='$province' ORDER BY municipality ASC");
                            while($row = $res->fetch_assoc()){
                                $sel = ($municipality == $row['municipality']) ? "selected" : "";
                                echo "<option value='{$row['municipality']}' $sel>{$row['municipality']}</option>";
                            }
                        } ?>
                    </select>
                    <select name="barangay" id="barangay">
                        <option value="">Select Barangay</option>
                        <?php if(!empty($municipality)){ 
                            $res = $conn->query("SELECT DISTINCT barangay FROM ph_addresses WHERE municipality='$municipality' ORDER BY barangay ASC");
                            while($row = $res->fetch_assoc()){
                                $sel = ($barangay == $row['barangay']) ? "selected" : "";
                                echo "<option value='{$row['barangay']}' $sel>{$row['barangay']}</option>";
                            }
                        } ?>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" onclick="window.location='add_teacher.php?cancel=1'">Cancel</button>
                <button type="submit" name="submit">Add Personnel</button>
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

<div id="termInfoModal" class="modal">
    <div class="modal-content">
        <h2 id="modal-title">Term Details</h2>
        <p id="modal-description"></p>
        <button onclick="closeTermModal()">Close</button>
    </div>
</div>

<script>
function previewImage(event) {
    const output = document.getElementById('preview-image');
    output.src = URL.createObjectURL(event.target.files[0]);
}

function removeImage() {
    fetch("add_teacher.php?remove_image=1")
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

function toggleAccountOptions() {
    const userType = document.getElementById('user_type').value;
    const withAccountSection = document.getElementById('with-account-section');
    withAccountSection.style.display = (userType === 'n') ? 'flex' : 'none';
    toggleAccountDetails();
    toggleTermDetails();
}

function toggleAccountDetails() {
    const userType = document.getElementById('user_type').value;
    const withAccount = document.getElementById('with_account')?.value || 'yes';
    const accountDetails = document.getElementById('account-details');
    const showAccount = (userType !== 'n') || (withAccount === 'yes');
    accountDetails.style.display = showAccount ? 'grid' : 'none';

    // Toggle required attributes
    const emailInput = document.querySelector('[name=email]');
    const passwordInput = document.querySelector('[name=password]');
    const confirmInput = document.querySelector('[name=confirm_password]');
    if (showAccount) {
        emailInput.required = true;
        passwordInput.required = true;
        confirmInput.required = true;
    } else {
        emailInput.required = false;
        passwordInput.required = false;
        confirmInput.required = false;
    }
}

function toggleTermDetails() {
    const userType = document.getElementById('user_type').value;
    const positionSelect = document.getElementById('position');
    const selectedPositionText = positionSelect.options[positionSelect.selectedIndex]?.text || '';
    const isPrincipal = selectedPositionText.includes('Principal');
    const showTerm = true;
    document.getElementById('term-dates').style.display = showTerm ? 'block' : 'none';

    if (showTerm) {
        const termTitle = document.getElementById('term-title');
        const startLabel = document.getElementById('start-label');
        const endLabel = document.getElementById('end-label');
        if (isPrincipal) {
            termTitle.textContent = 'Principal Term Details';
            startLabel.textContent = 'Start Date of Principal Term';
            endLabel.textContent = 'End Date of Principal Term (Optional)';
        } else {
            termTitle.textContent = 'Employee Term Details';
            startLabel.textContent = 'Start Date of Employee Term';
            endLabel.textContent = 'End Date of Employee Term (Optional)';
        }
    }
}

function showTermModal() {
    const positionSelect = document.getElementById('position');
    const selectedPositionText = positionSelect.options[positionSelect.selectedIndex]?.text || '';
    const isPrincipal = selectedPositionText.includes('Principal');
    const modalTitle = document.getElementById('modal-title');
    const modalDesc = document.getElementById('modal-description');

    if (isPrincipal) {
        modalTitle.textContent = 'Principal Term Details';
        modalDesc.innerHTML = `
            <p>The <strong>Start Date</strong> indicates when the principal's term officially begins. This is a required field for principal positions to ensure accurate tracking of their tenure.</p>
            <p>The <strong>End Date</strong> is optional and specifies when the principal's term is expected to conclude. If an end date is provided, it will be recorded; otherwise, the term is considered ongoing until updated.</p>
            <p>When a new principal is appointed, the system automatically sets the end date of the previous principal to one day before the new principal's start date to maintain a clear record of leadership transitions.</p>
        `;
    } else {
        modalTitle.textContent = 'Employee Term Details';
        modalDesc.innerHTML = `
            <p>The <strong>Start Date</strong> indicates when the employee's term officially begins. This is a required field for all personnel.</p>
            <p>The <strong>End Date</strong> is optional and specifies when the employee's term is expected to conclude. If an end date is provided, it will be recorded; otherwise, the term is considered ongoing until updated.</p>
            <p>No automatic updates are made to previous employees' terms.</p>
        `;
    }
    document.getElementById("termInfoModal").style.display = "flex";
}

function closeTermModal() {
    document.getElementById("termInfoModal").style.display = "none";
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

document.getElementById('province').addEventListener('change', function(){
    const province = this.value;
    fetch('get_municipalities.php?province=' + encodeURIComponent(province))
        .then(res => res.text())
        .then(data => {
            document.getElementById('municipality').innerHTML = data;
            document.getElementById('barangay').innerHTML = '<option value="">Select Barangay</option>';
        });
});

document.getElementById('municipality').addEventListener('change', function(){
    const municipality = this.value;
    fetch('get_barangays.php?municipality=' + encodeURIComponent(municipality))
        .then(res => res.text())
        .then(data => {
            document.getElementById('barangay').innerHTML = data;
        });
});

window.onload = function() {
    toggleAccountOptions();
    toggleTermDetails();
    <?php if (!empty($error_message)) { ?>
        showError('<?= $error_message ?>');
    <?php } ?>
    <?php if ($position_id) { ?>
        document.getElementById('term-dates').style.display = 'block';
    <?php } ?>
};
</script>

</body>
</html>