<?php
include 'lecs_db.php';
$current_page = basename($_SERVER['PHP_SELF']); 

// ✅ Validate pupil_id
if (!isset($_GET['id'])) {
    die("No pupil selected!");
}
$pupil_id = intval($_GET['id']);

// ✅ Fetch existing pupil data
$result = $conn->query("SELECT * FROM pupils WHERE pupil_id=$pupil_id");
if ($result->num_rows == 0) {
    die("Pupil not found!");
}
$pupil = $result->fetch_assoc();

// Default form data = existing values
$formData = $pupil;

// ✅ Handle form submission
if (isset($_POST['update'])) {
    // Personal Information
    $lrn = $conn->real_escape_string($_POST['lrn']);
    $last_name = $conn->real_escape_string($_POST['last_name']);
    $first_name = $conn->real_escape_string($_POST['first_name']);
    $middle_name = $conn->real_escape_string($_POST['middle_name']);
    $sex = $conn->real_escape_string($_POST['sex']);
    $birthdate = $_POST['birthdate'];
    $age = $_POST['age'];
    $mother_tongue = $conn->real_escape_string($_POST['mother_tongue']);
    $ip_ethnicity = $conn->real_escape_string($_POST['ip_ethnicity']);
    $religion = $conn->real_escape_string($_POST['religion']);

    // ✅ Address (merged field)
    $house_no_street = $conn->real_escape_string($_POST['house_no_street']);
    $barangay = $conn->real_escape_string($_POST['barangay']);
    $municipality = $conn->real_escape_string($_POST['municipality']);
    $province = $conn->real_escape_string($_POST['province']);

    // Parent & Guardian
    $father_name = $conn->real_escape_string($_POST['father_name']);
    $mother_name = $conn->real_escape_string($_POST['mother_name']);
    $guardian_name = $conn->real_escape_string($_POST['guardian_name']);
    $relationship_to_guardian = $conn->real_escape_string($_POST['relationship_to_guardian']);
    $contact_number = $conn->real_escape_string($_POST['contact_number']);

    // Enrollment Info
    $learning_modality = $conn->real_escape_string($_POST['learning_modality']);
    $remarks = $conn->real_escape_string($_POST['remarks']);
    $section_id = intval($_POST['section_id']);
    $sy_id = intval($_POST['sy_id']);
    $status = $conn->real_escape_string($_POST['status']);

    // ✅ Prevent duplicate LRN in same School Year (except this record)
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
            // Refresh form data
            $result = $conn->query("SELECT * FROM pupils WHERE pupil_id=$pupil_id");
            $formData = $result->fetch_assoc();
        } else {
            $error = "Update failed: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Pupil</title>
<link rel="icon" href="images/lecs-logo no bg.png" type="image/x-icon">
<link href="css/adminAddPupil.css" rel="stylesheet">
<link rel="stylesheet" href="css/sidebar.css">
</head>
<body class="light">
<div class="container">
    <?php include 'teacherSidebar.php'; ?>
    <div class="main-content">
        <h1>
            <span class="back-arrow" onclick="window.location.href='teacherPupils.php'">←</span>
            Edit Pupil
        </h1>

        <?php if(isset($success)) echo "<p class='success'>$success</p>"; ?>
        <?php if(isset($error)) echo "<p class='error'>$error</p>"; ?>

        <form method="POST">
            <!-- ✅ Same form fields as add_pupil.php -->
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
                        <input type="text" name="learning_modality" value="<?= htmlspecialchars($formData['learning_modality'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Remarks</label>
                        <input type="text" name="remarks" value="<?= htmlspecialchars($formData['remarks'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" required>
                            <option value="enrolled" <?= ($formData['status']=="enrolled")?"selected":"" ?>>Enrolled</option>
                            <option value="dropped" <?= ($formData['status']=="dropped")?"selected":"" ?>>Dropped</option>
                        </select>
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

// Reload sections when school year changes
document.getElementById("sy_id").addEventListener("change", function(){
    const sy_id = this.value;
    const sectionDropdown = document.getElementById("section_id");
    sectionDropdown.innerHTML = "<option>Loading...</option>";

    fetch("get_sections.php?sy_id=" + sy_id)
        .then(response => response.text())
        .then(data => {
            sectionDropdown.innerHTML = data;
        });
});

// ✅ Dynamic Province → Municipality → Barangay
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

// ✅ Preselect saved Municipality + Barangay when editing
window.addEventListener('DOMContentLoaded', function() {
    const province = document.getElementById('province').value;
    const savedMunicipality = "<?= $formData['municipality'] ?? '' ?>";
    const savedBarangay = "<?= $formData['barangay'] ?? '' ?>";

    if(province){
        fetch('get_municipalities.php?province=' + encodeURIComponent(province))
            .then(res => res.text())
            .then(data => {
                document.getElementById('municipality').innerHTML = data;
                if(savedMunicipality){
                    document.getElementById('municipality').value = savedMunicipality;

                    fetch('get_barangays.php?municipality=' + encodeURIComponent(savedMunicipality))
                        .then(res => res.text())
                        .then(data => {
                            document.getElementById('barangay').innerHTML = data;
                            if(savedBarangay){
                                document.getElementById('barangay').value = savedBarangay;
                            }
                        });
                }
            });
    }
});
</script>
</body>
</html>
