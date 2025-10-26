<?php
include 'lecs_db.php';
session_start();

if (!isset($_GET['id'])) {
    header("Location: adminTeachers.php");
    exit();
}
$teacher_id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT first_name, middle_name, last_name, employee_no, email, contact_no, position, image, user_type, gender, birthdate, age, house_no_street, barangay, municipality, province, password FROM teachers WHERE teacher_id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    header("Location: adminTeachers.php");
    exit();
}
$teacher = $result->fetch_assoc();
$stmt->close();

$first_name = $teacher['first_name'];
$middle_name = $teacher['middle_name'];
$last_name = $teacher['last_name'];
$employee_no = $teacher['employee_no'];
$email = $teacher['email'];
$contact_no = $teacher['contact_no'] === 'N/A' ? '' : $teacher['contact_no'];
$gender = $teacher['gender'];
$birthdate = $teacher['birthdate'] === 'N/A' ? '' : $teacher['birthdate'];
$age = $teacher['age'] === 'N/A' ? '' : $teacher['age'];
$house_no_street = $teacher['house_no_street'] === 'N/A' ? '' : $teacher['house_no_street'];
$barangay = $teacher['barangay'] === 'N/A' ? '' : $teacher['barangay'];
$municipality = $teacher['municipality'] === 'N/A' ? '' : $teacher['municipality'];
$province = $teacher['province'] === 'N/A' ? '' : $teacher['province'];

$preview_image = ($teacher['image'] && file_exists("Uploads/teachers/".$teacher['image']))
    ? "Uploads/teachers/".$teacher['image']
    : "Uploads/teachers/teacher.png";

if (isset($_SESSION['preview_image'])) {
    $preview_image = $_SESSION['preview_image'];
}

$position_id = $start_date = $end_date = "";
$is_principal = strpos($teacher['position'], 'Principal') !== false;
$is_non_teaching = ($teacher['user_type'] === 'n');
$with_account = ($is_non_teaching && empty($teacher['email'])) ? 'no' : 'yes';

// Fetch position_id
if (!empty($teacher['position'])) {
    $stmt = $conn->prepare("SELECT position_id FROM positions WHERE position_name = ?");
    $stmt->bind_param("s", $teacher['position']);
    $stmt->execute();
    $pos_res = $stmt->get_result();
    if ($pos_res->num_rows > 0) {
        $pos_row = $pos_res->fetch_assoc();
        $position_id = $pos_row['position_id'];
    }
    $stmt->close();
}

// Fetch term details
if ($position_id) {
    $stmt = $conn->prepare("SELECT start_date, end_date FROM teacher_positions WHERE teacher_id = ? AND position_id = ? LIMIT 1");
    $stmt->bind_param("ii", $teacher_id, $position_id);
    $stmt->execute();
    $term_res = $stmt->get_result();
    if ($term_res->num_rows > 0) {
        $term = $term_res->fetch_assoc();
        $start_date = $term['start_date'];
        $end_date = $term['end_date'] ?: '';
    }
    $stmt->close();
}

$error_message = "";

if (isset($_GET['remove_image'])) {
    if (isset($_SESSION['preview_image']) && file_exists($_SESSION['preview_image'])) {
        unlink($_SESSION['preview_image']);
        unset($_SESSION['preview_image']);
    } else {
        $final_image = "teacher.png";
        if ($teacher['image'] && $teacher['image'] != "teacher.png" && file_exists("Uploads/teachers/".$teacher['image'])) {
            unlink("Uploads/teachers/".$teacher['image']);
        }
        $stmt = $conn->prepare("UPDATE teachers SET image = ? WHERE teacher_id = ?");
        $stmt->bind_param("si", $final_image, $teacher_id);
        $stmt->execute();
        $stmt->close();
    }
    echo json_encode(["status" => "removed"]);
    exit();
}

if (isset($_POST['submit'])) {
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $last_name = trim($_POST['last_name']);
    $full_name = implode(' ', array_filter([$first_name, $middle_name, $last_name]));
    $employee_no = trim($_POST['employee_no']);
    $email = trim($_POST['email'] ?? '');
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

    $position_name = '';
    if ($position_id) {
        $stmt = $conn->prepare("SELECT position_name FROM positions WHERE position_id = ?");
        $stmt->bind_param("i", $position_id);
        $stmt->execute();
        $pos_res = $stmt->get_result();
        if ($pos_res->num_rows > 0) {
            $pos_row = $pos_res->fetch_assoc();
            $position_name = $pos_row['position_name'];
        }
        $stmt->close();
    }

    $new_is_principal = strpos($position_name, 'Principal') !== false;
    $new_is_non_teaching = ($user_type === 'n');
    $show_term = true;

    $password = $teacher['password'];
    if ($with_account === 'no' && $new_is_non_teaching) {
        $email = '';
        $password = null;
    } else {
        if (empty($email)) {
            $error_message = "Email is required!";
        } elseif (!empty($_POST['password']) || !empty($_POST['confirm_password'])) {
            if ($_POST['password'] !== $_POST['confirm_password']) {
                $error_message = "Passwords do not match!";
            } else {
                $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
            }
        }
    }

    if ($show_term && empty($start_date)) {
        $error_message = "Start date is required!";
    }

    if (empty($error_message)) {
        $final_image = $teacher['image'];
        if (isset($_SESSION['preview_image']) && file_exists($_SESSION['preview_image'])) {
            $ext = pathinfo($_SESSION['preview_image'], PATHINFO_EXTENSION);
            $final_image = uniqid() . "." . $ext;
            if ($teacher['image'] && $teacher['image'] != "teacher.png" && file_exists("Uploads/teachers/".$teacher['image'])) {
                unlink("Uploads/teachers/".$teacher['image']);
            }
            rename($_SESSION['preview_image'], "Uploads/teachers/" . $final_image);
            unset($_SESSION['preview_image']);
        }

        $stmt = $conn->prepare("UPDATE teachers SET 
            first_name = ?, middle_name = ?, last_name = ?, employee_no = ?, email = ?, 
            contact_no = ?, position = ?, password = ?, image = ?, user_type = ?, 
            gender = ?, birthdate = ?, age = ?, house_no_street = ?, barangay = ?, 
            municipality = ?, province = ? WHERE teacher_id = ?");
        $stmt->bind_param("sssssssssssssssssi", $first_name, $middle_name, $last_name, 
            $employee_no, $email, $contact_no, $position_name, $password, $final_image, $user_type, 
            $gender, $birthdate, $age, $house_no_street, $barangay, $municipality, $province, $teacher_id);
        $stmt->execute();
        $stmt->close();

        if ($show_term) {
            $end_sql = ($end_date !== 'N/A') ? $end_date : null;

            if ($new_is_principal) {
                $res = $conn->query("SELECT tp.id FROM teacher_positions tp 
                                     JOIN positions p ON tp.position_id = p.position_id 
                                     WHERE p.position_name LIKE 'Principal%' AND tp.end_date IS NULL 
                                     AND tp.teacher_id != $teacher_id");
                if ($res->num_rows > 0) {
                    $row = $res->fetch_assoc();
                    $auto_end = date("Y-m-d", strtotime($start_date . " -1 day"));
                    $stmt = $conn->prepare("UPDATE teacher_positions SET end_date = ? WHERE id = ?");
                    $stmt->bind_param("si", $auto_end, $row['id']);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            $stmt = $conn->prepare("SELECT id FROM teacher_positions WHERE teacher_id = ? AND position_id = ?");
            $stmt->bind_param("ii", $teacher_id, $position_id);
            $stmt->execute();
            $term_res = $stmt->get_result();
            if ($term_res->num_rows > 0) {
                $stmt = $conn->prepare("UPDATE teacher_positions SET start_date = ?, end_date = ? 
                                        WHERE teacher_id = ? AND position_id = ?");
                $stmt->bind_param("ssii", $start_date, $end_sql, $teacher_id, $position_id);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare("INSERT INTO teacher_positions 
                    (teacher_id, position_id, start_date, end_date) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiss", $teacher_id, $position_id, $start_date, $end_sql);
                $stmt->execute();
                $stmt->close();
            }
        }

        header("Location: adminTeachers.php");
        exit();
    }
}

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
<title>Edit Personnel | LECS Online Student Grading System</title>
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
            <h1><a href="adminTeachers.php" style="text-decoration:none;">← Edit Personnel</a></h1>
        </div>

        <form method="POST" enctype="multipart/form-data" class="add-form">
            <div class="form-section account-header">
                <div class="account-header-row">
                    <h2>Account Details</h2>
                    <select name="user_type" id="user_type" class="small-dropdown" required onchange="toggleAccountOptions()">
                        <option value="t" <?= ($teacher['user_type']=="t"?"selected":"") ?>>Teacher</option>
                        <option value="a" <?= ($teacher['user_type']=="a"?"selected":"") ?>>Admin</option>
                        <option value="n" <?= ($teacher['user_type']=="n"?"selected":"") ?>>Non-Teaching</option>
                    </select>
                </div>

                <div id="with-account-section" style="display: <?= ($teacher['user_type'] === 'n' ? 'block' : 'none') ?>;">
                    <label for="with_account">With Account?</label>
                    <select name="with_account" id="with_account" onchange="toggleAccountDetails()">
                        <option value="yes" <?= ($with_account=="yes"?"selected":"") ?>>Yes</option>
                        <option value="no" <?= ($with_account=="no"?"selected":"") ?>>No</option>
                    </select>
                </div>

                <div class="input-group" id="account-details" style="display: <?= ($teacher['user_type'] !== 'n' || $with_account === 'yes' ? 'grid' : 'none') ?>;">
                    <input type="email" name="email" placeholder="Email" value="<?= htmlspecialchars($email) ?>" <?= ($teacher['user_type'] !== 'n' || $with_account === 'yes' ? 'required' : '') ?>>
                    <div class="password-field">
                        <input type="password" name="password" placeholder="Leave blank to keep password" id="password">
                        <i class="fa-solid fa-eye" onclick="togglePassword('password', this)"></i>
                    </div>
                    <div class="password-field">
                        <input type="password" name="confirm_password" placeholder="Confirm password" id="confirm_password">
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
                <div id="term-dates" class="principal-dates-section" style="display: block;">
                    <div class="principal-term-header">
                        <h3 id="term-title"><?= $is_principal ? 'Principal Term Details' : 'Employee Term Details' ?></h3>
                        <i class="fa-solid fa-info-circle info-icon" onclick="showTermModal()"></i>
                    </div>
                    <div class="principal-term-inputs">
                        <div class="input-with-label">
                            <label for="start_date" id="start-label"><?= $is_principal ? 'Start Date of Principal Term' : 'Start Date of Employee Term' ?></label>
                            <input type="date" name="start_date" id="start_date" value="<?= htmlspecialchars($start_date) ?>">
                        </div>
                        <div class="input-with-label">
                            <label for="end_date" id="end-label"><?= $is_principal ? 'End Date of Principal Term (Optional)' : 'End Date of Employee Term (Optional)' ?></label>
                            <input type="date" name="end_date" id="end_date" value="<?= htmlspecialchars($end_date === 'N/A' ? '' : $end_date) ?>">
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
                        $provinces->data_seek(0);
                        while($row = $provinces->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($row['province']) ?>" <?= ($province==$row['province'] ? 'selected' : '') ?>>
                                <?= htmlspecialchars($row['province']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <select name="municipality" id="municipality">
                        <option value="">Select Municipality/City</option>
                        <?php if(!empty($teacher['province'])){ 
                            $stmt = $conn->prepare("SELECT DISTINCT municipality FROM ph_addresses WHERE province = ? ORDER BY municipality ASC");
                            $stmt->bind_param("s", $teacher['province']);
                            $stmt->execute();
                            $res = $stmt->get_result();
                            while($row = $res->fetch_assoc()){
                                $sel = ($municipality == $row['municipality']) ? "selected" : "";
                                echo "<option value='{$row['municipality']}' $sel>{$row['municipality']}</option>";
                            }
                            $stmt->close();
                        } ?>
                    </select>
                    <select name="barangay" id="barangay">
                        <option value="">Select Barangay</option>
                        <?php if(!empty($teacher['municipality'])){ 
                            $stmt = $conn->prepare("SELECT DISTINCT barangay FROM ph_addresses WHERE municipality = ? ORDER BY barangay ASC");
                            $stmt->bind_param("s", $teacher['municipality']);
                            $stmt->execute();
                            $res = $stmt->get_result();
                            while($row = $res->fetch_assoc()){
                                $sel = ($barangay == $row['barangay']) ? "selected" : "";
                                echo "<option value='{$row['barangay']}' $sel>{$row['barangay']}</option>";
                            }
                            $stmt->close();
                        } ?>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" onclick="window.location='adminTeachers.php'">Cancel</button>
                <button type="submit" name="submit">Update Personnel</button>
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
    fetch("edit_teacher.php?id=<?= $teacher_id ?>&remove_image=1")
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

    const emailInput = document.querySelector('[name=email]');
    const passwordInput = document.querySelector('[name=password]');
    const confirmInput = document.querySelector('[name=confirm_password]');
    if (showAccount) {
        emailInput.required = true;
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

window.addEventListener('DOMContentLoaded', function() {
    toggleAccountOptions();
    toggleTermDetails();
    const savedProvince = "<?= $province ?>";
    const savedMunicipality = "<?= $municipality ?>";
    const savedBarangay = "<?= $barangay ?>";

    if(savedProvince) {
        fetch('get_municipalities.php?province=' + encodeURIComponent(savedProvince))
            .then(res => res.text())
            .then(data => {
                document.getElementById('municipality').innerHTML = data;
                if(savedMunicipality) {
                    document.getElementById('municipality').value = savedMunicipality;

                    fetch('get_barangays.php?municipality=' + encodeURIComponent(savedMunicipality))
                        .then(res => res.text())
                        .then(data => {
                            document.getElementById('barangay').innerHTML = data;
                            if(savedBarangay) {
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