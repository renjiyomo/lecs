<?php
include 'lecs_db.php';
session_start();

if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 't') {
    header("Location: /lecs/Landing/Login/login.php");
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']); 

// Validate pupil_id
if (!isset($_GET['id'])) {
    die("No pupil selected!");
}
$pupil_id = intval($_GET['id']);

// Fetch existing pupil data
$result = $conn->query("SELECT * FROM pupils WHERE pupil_id=$pupil_id");
if ($result->num_rows == 0) {
    die("Pupil not found!");
}
$pupil = $result->fetch_assoc();

// Initialize form data
$formData = $pupil;

// Extract date from remarks and handle potential old data override
$status_date = '';
$derived_status = $pupil['status'];
if (!empty($pupil['remarks'])) {
    $remarks = trim($pupil['remarks']);
    if (preg_match('/^(T\/I|DRPLE|T\/O) DATE:(\d{4}-\d{2}-\d{2})$/', $remarks, $matches)) {
        $code = $matches[1];
        $status_date = $matches[2];
        switch ($code) {
            case 'T/I':
                $derived_status = 'transferred_in';
                break;
            case 'DRPLE':
                $derived_status = 'dropped';
                break;
            case 'T/O':
                $derived_status = 'transferred_out';
                break;
        }
    }
}

// Override status for old data where status was 'enrolled' but remarks indicate otherwise
if ($pupil['status'] === 'enrolled' && $derived_status !== 'enrolled') {
    $formData['status'] = $derived_status;
}
$formData['status_date'] = $status_date;

// Handle form submission
if (isset($_POST['update'])) {
    $lrn = $conn->real_escape_string($_POST['lrn']);
    $last_name = $conn->real_escape_string($_POST['last_name']);
    $first_name = $conn->real_escape_string($_POST['first_name']);
    $middle_name = $conn->real_escape_string($_POST['middle_name']);
    $sex = $conn->real_escape_string($_POST['sex']);
    $birthdate = $_POST['birthdate'];
    $age = intval($_POST['age']);
    $mother_tongue = $conn->real_escape_string($_POST['mother_tongue']);
    $ip_ethnicity = $conn->real_escape_string($_POST['ip_ethnicity']);
    $religion = $conn->real_escape_string($_POST['religion']);

    $house_no_street = $conn->real_escape_string($_POST['house_no_street']);
    $barangay = $conn->real_escape_string($_POST['barangay']);
    $municipality = $conn->real_escape_string($_POST['municipality']);
    $province = $conn->real_escape_string($_POST['province']);

    $father_name = $conn->real_escape_string($_POST['father_name']);
    $mother_name = $conn->real_escape_string($_POST['mother_name']);
    $guardian_name = $conn->real_escape_string($_POST['guardian_name']);
    $relationship_to_guardian = $conn->real_escape_string($_POST['relationship_to_guardian']);
    $contact_number = $conn->real_escape_string($_POST['contact_number']);

    $learning_modality = $conn->real_escape_string($_POST['learning_modality']);
    $section_id = intval($_POST['section_id']);
    $sy_id = intval($_POST['sy_id']);
    $status = $conn->real_escape_string($_POST['status']);
    $status_date = !empty($_POST['status_date']) ? $_POST['status_date'] : null;

    // Generate remarks
    $remarks = '';
    if ($status === 'transferred_in' && $status_date) {
        $remarks = "T/I DATE:" . $status_date;
    } elseif ($status === 'transferred_out' && $status_date) {
        $remarks = "T/O DATE:" . $status_date;
    } elseif ($status === 'dropped' && $status_date) {
        $remarks = "DRPLE DATE:" . $status_date;
    }

    // Validate date required
    if (in_array($status, ['dropped', 'transferred_in', 'transferred_out']) && empty($status_date)) {
        $error = "Please select a date for the selected status.";
    } else {
        // Check LRN duplicate (exclude current pupil)
        $check = $conn->query("SELECT pupil_id FROM pupils WHERE lrn='$lrn' AND sy_id=$sy_id AND pupil_id!=$pupil_id");
        if ($check->num_rows > 0) {
            $error = "This LRN is already enrolled in the selected School Year.";
        } else {
            $update = "UPDATE pupils SET 
                lrn='$lrn',
                last_name='$last_name',
                first_name='$first_name',
                middle_name='$middle_name',
                sex='$sex',
                birthdate='$birthdate',
                age=$age,
                mother_tongue='$mother_tongue',
                ip_ethnicity='$ip_ethnicity',
                religion='$religion',
                house_no_street='$house_no_street',
                barangay='$barangay',
                municipality='$municipality',
                province='$province',
                father_name='$father_name',
                mother_name='$mother_name',
                guardian_name='$guardian_name',
                relationship_to_guardian='$relationship_to_guardian',
                contact_number='$contact_number',
                learning_modality='$learning_modality',
                remarks='$remarks',
                sy_id=$sy_id,
                section_id=$section_id,
                status='$status'
                WHERE pupil_id=$pupil_id";

            if ($conn->query($update)) {
                $success = "Pupil updated successfully!";
                // Refresh data
                $result = $conn->query("SELECT * FROM pupils WHERE pupil_id=$pupil_id");
                $pupil = $result->fetch_assoc();
                $formData = $pupil;

                // Re-extract status & date
                $status_date = '';
                $derived_status = $pupil['status'];
                if (!empty($pupil['remarks'])) {
                    if (preg_match('/^(T\/I|DRPLE|T\/O) DATE:(\d{4}-\d{2}-\d{2})$/', $pupil['remarks'], $matches)) {
                        $code = $matches[1];
                        $status_date = $matches[2];
                        switch ($code) {
                            case 'T/I':
                                $derived_status = 'transferred_in';
                                break;
                            case 'DRPLE':
                                $derived_status = 'dropped';
                                break;
                            case 'T/O':
                                $derived_status = 'transferred_out';
                                break;
                        }
                    }
                }
                if ($pupil['status'] === 'enrolled' && $derived_status !== 'enrolled') {
                    $formData['status'] = $derived_status;
                }
                $formData['status_date'] = $status_date;
            } else {
                $error = "Update failed: " . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pupil | LECS Online Student Grading System</title>
    <link rel="icon" href="images/lecs-logo no bg.png" type="image/x-icon">
    <link href="css/adminAddPupil.css" rel="stylesheet">
    <link rel="stylesheet" href="css/sidebar.css">
    <?php include 'theme-script.php'; ?>
<style>
    .status-date-group { display: flex; gap: 10px; align-items: center; }
    .status-date-group input[type="date"] { flex: 1; }
    #status_date_container { display: none; }
</style>
</head>
<body>
<div class="container">
    <?php include 'teacherSidebar.php'; ?>
    <div class="main-content">
        <h1>
            <span class="back-arrow" onclick="window.location.href='teacherPupils.php'">←</span>
            Edit Pupil
        </h1>

        <?php if(isset($success)): ?>
            <div class="modal show" id="successModal">
                <div class="modal-content">
                    <h2 class="success-msg">Success</h2>
                    <p><?= htmlspecialchars($success) ?></p>
                    <div class="modal-buttons">
                        <button type="button" class="btn-cancel" onclick="document.getElementById('successModal').classList.remove('show')">OK</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <?php if(isset($error)): ?>
            <div class="modal show" id="errorModal">
                <div class="modal-content">
                    <h2 class="error-msg">Error</h2>
                    <p><?= htmlspecialchars($error) ?></p>
                    <div class="modal-buttons">
                        <button type="button" class="btn-cancel" onclick="document.getElementById('errorModal').classList.remove('show')">OK</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST">
            <!-- Personal Information -->
            <fieldset>
                <legend>Personal Information</legend>
                <div class="form-grid">
                    <div class="form-group">
                        <label>LRN</label>
                        <input type="text" name="lrn" value="<?= htmlspecialchars($formData['lrn'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" value="<?= htmlspecialchars($formData['last_name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" value="<?= htmlspecialchars($formData['first_name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input type="text" name="middle_name" value="<?= htmlspecialchars($formData['middle_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Sex</label>
                        <select name="sex" required>
                            <option value="">Select Sex</option>
                            <option value="Male" <?= ($formData['sex']=="Male")?"selected":"" ?>>Male</option>
                            <option value="Female" <?= ($formData['sex']=="Female")?"selected":"" ?>>Female</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Birthdate</label>
                        <input type="date" name="birthdate" id="birthdate" value="<?= $formData['birthdate'] ?? '' ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Age</label>
                        <input type="number" name="age" id="age" value="<?= $formData['age'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Mother Tongue</label>
                        <input type="text" name="mother_tongue" value="<?= htmlspecialchars($formData['mother_tongue'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>IP/Ethnicity</label>
                        <input type="text" name="ip_ethnicity" value="<?= htmlspecialchars($formData['ip_ethnicity'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Religion</label>
                        <input type="text" name="religion" value="<?= htmlspecialchars($formData['religion'] ?? '') ?>">
                    </div>
                </div>
            </fieldset>

            <!-- Address -->
            <fieldset>
                <legend>Address</legend>
                <div class="form-grid">
                    <div class="form-group">
                        <label>House No. & Street</label>
                        <input type="text" name="house_no_street" placeholder="e.g. 123 Main St." value="<?= htmlspecialchars($formData['house_no_street'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Province</label>
                        <select name="province" id="province" required>
                            <option value="">Select Province</option>
                            <?php
                            $provinces = $conn->query("SELECT DISTINCT province FROM ph_addresses ORDER BY province ASC");
                            while($row = $provinces->fetch_assoc()){
                                $sel = ($formData['province']==$row['province'])?"selected":""; 
                                echo "<option value='{$row['province']}' $sel>{$row['province']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Municipality/City</label>
                        <select name="municipality" id="municipality" required>
                            <option value="">Select Municipality/City</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Barangay</label>
                        <select name="barangay" id="barangay" required>
                            <option value="">Select Barangay</option>
                        </select>
                    </div>
                </div>
            </fieldset>

            <!-- Parent & Guardian -->
            <fieldset>
                <legend>Parent & Guardian Information</legend>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Father's Name</label>
                        <input type="text" name="father_name" value="<?= htmlspecialchars($formData['father_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Mother's Name</label>
                        <input type="text" name="mother_name" value="<?= htmlspecialchars($formData['mother_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Guardian's Name</label>
                        <input type="text" name="guardian_name" value="<?= htmlspecialchars($formData['guardian_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Relationship to Guardian</label>
                        <input type="text" name="relationship_to_guardian" value="<?= htmlspecialchars($formData['relationship_to_guardian'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="text" name="contact_number" value="<?= htmlspecialchars($formData['contact_number'] ?? '') ?>">
                    </div>
                </div>
            </fieldset>

            <!-- Enrollment Information -->
            <fieldset>
                <legend>Enrollment Information</legend>
                <div class="form-grid">
                    <div class="form-group">
                        <label>School Year</label>
                        <select name="sy_id" id="sy_id" required>
                            <option value="">Select School Year</option>
                            <?php
                            $years = $conn->query("SELECT sy_id, school_year FROM school_years ORDER BY sy_id DESC");
                            while ($sy = $years->fetch_assoc()) {
                                $sel = ($formData['sy_id']==$sy['sy_id'])?'selected':''; 
                                echo "<option value='{$sy['sy_id']}' $sel>{$sy['school_year']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Section</label>
                        <select name="section_id" id="section_id" required>
                            <option value="">Select Section</option>
                            <?php 
                            if(!empty($formData['sy_id'])){
                                $secs = $conn->query("SELECT section_id, section_name FROM sections WHERE sy_id=".$formData['sy_id']);
                                while($s = $secs->fetch_assoc()){
                                    $sel = ($formData['section_id']==$s['section_id'])?'selected':''; 
                                    echo "<option value='{$s['section_id']}' $sel>{$s['section_name']}</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Learning Modality</label>
                        <select name="learning_modality" required>
                            <option value="">Select Modality</option>
                            <option value="Modular (Print)" <?= ($formData['learning_modality'] == "Modular (Print)") ? "selected" : "" ?>>Modular (Print)</option>
                            <option value="Modular Digital" <?= ($formData['learning_modality'] == "Modular Digital") ? "selected" : "" ?>>Modular Digital</option>
                            <option value="Online" <?= ($formData['learning_modality'] == "Online") ? "selected" : "" ?>>Online</option>
                            <option value="Educational TV" <?= ($formData['learning_modality'] == "Educational TV") ? "selected" : "" ?>>Educational TV</option>
                            <option value="Radio-based Instruction" <?= ($formData['learning_modality'] == "Radio-based Instruction") ? "selected" : "" ?>>Radio-based Instruction</option>
                            <option value="Homeschooling" <?= ($formData['learning_modality'] == "Homeschooling") ? "selected" : "" ?>>Homeschooling</option>
                            <option value="Face to Face" <?= ($formData['learning_modality'] == "Face to Face") ? "selected" : "" ?>>Face to Face</option>
                        </select>
                    </div>

                    <!-- Status -->
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="status" required>
                            <option value="enrolled" <?= ($formData['status'] == 'enrolled') ? 'selected' : '' ?>>Enrolled</option>
                            <option value="dropped" <?= ($formData['status'] == 'dropped') ? 'selected' : '' ?>>Dropped</option>
                            <option value="promoted" <?= ($formData['status'] == 'promoted') ? 'selected' : '' ?>>Promoted</option>
                            <option value="retained" <?= ($formData['status'] == 'retained') ? 'selected' : '' ?>>Retained</option>
                            <option value="transferred_in" <?= ($formData['status'] == 'transferred_in') ? 'selected' : '' ?>>Transferred In</option>
                            <option value="transferred_out" <?= ($formData['status'] == 'transferred_out') ? 'selected' : '' ?>>Transferred Out</option>
                        </select>
                    </div>

                    <!-- Date (Conditional) -->
                    <div class="form-group" id="status_date_container">
                        <label>Date <span id="date_required" style="color:red; display:none;">*</span></label>
                        <div class="status-date-group">
                            <input type="date" name="status_date" id="status_date" value="<?= htmlspecialchars($formData['status_date'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </fieldset>

            <button type="submit" name="update" class="add-btn">Update Pupil</button>
        </form>
    </div>
</div>

<script>
// Auto-calc age
document.getElementById("birthdate")?.addEventListener("change", function() {
    const birthdate = new Date(this.value);
    const today = new Date();
    let age = today.getFullYear() - birthdate.getFullYear();
    const m = today.getMonth() - birthdate.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birthdate.getDate())) age--;
    document.getElementById("age").value = age;
});

// Reload sections
document.getElementById("sy_id").addEventListener("change", function(){
    const sy_id = this.value;
    const sectionDropdown = document.getElementById("section_id");
    sectionDropdown.innerHTML = "<option>Loading...</option>";
    fetch("get_sections.php?sy_id=" + sy_id)
        .then(res => res.text())
        .then(data => sectionDropdown.innerHTML = data);
});

// Address cascading
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
        .then(data => document.getElementById('barangay').innerHTML = data);
});

// Preload address on edit
window.addEventListener('DOMContentLoaded', function() {
    const province = "<?= $formData['province'] ?>";
    const savedMunicipality = "<?= $formData['municipality'] ?>";
    const savedBarangay = "<?= $formData['barangay'] ?>";

    if (province) {
        fetch('get_municipalities.php?province=' + encodeURIComponent(province))
            .then(res => res.text())
            .then(data => {
                document.getElementById('municipality').innerHTML = data;
                if (savedMunicipality) {
                    document.getElementById('municipality').value = savedMunicipality;
                    fetch('get_barangays.php?municipality=' + encodeURIComponent(savedMunicipality))
                        .then(res => res.text())
                        .then(data => {
                            document.getElementById('barangay').innerHTML = data;
                            if (savedBarangay) document.getElementById('barangay').value = savedBarangay;
                        });
                }
            });
    }

    // Initialize status date visibility
    toggleStatusDate();
});

// Toggle date field
function toggleStatusDate() {
    const status = document.getElementById('status').value;
    const container = document.getElementById('status_date_container');
    const dateInput = document.getElementById('status_date');
    const requiredMark = document.getElementById('date_required');

    if (['dropped', 'transferred_in', 'transferred_out'].includes(status)) {
        container.style.display = 'block';
        dateInput.required = true;
        requiredMark.style.display = 'inline';
    } else {
        container.style.display = 'none';
        dateInput.required = false;
        requiredMark.style.display = 'none';
    }
}

document.getElementById('status').addEventListener('change', toggleStatusDate);
</script>
</body>
</html>