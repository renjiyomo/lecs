<?php
include 'lecs_db.php';
session_start();

if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 't') {
    header("Location: /lecs/Landing/Login/login.php");
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']); 
$formData = $_POST;

if (isset($_POST['submit'])) {
    $teacher_id = intval($_SESSION['teacher_id']);

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

    // Validate date required for certain statuses
    if (in_array($status, ['dropped', 'transferred_in', 'transferred_out']) && empty($status_date)) {
        $error = "Please select a date for the selected status.";
    } else {
        // Generate remarks based on status
        $remarks = '';
        if ($status === 'transferred_in' && $status_date) {
            $remarks = "T/I DATE:" . $status_date;
        } elseif ($status === 'transferred_out' && $status_date) {
            $remarks = "T/O DATE:" . $status_date;
        } elseif ($status === 'dropped' && $status_date) {
            $remarks = "DRPLE DATE:" . $status_date;
        }
        // Enrolled → remarks = empty

        $secCheck = $conn->query("SELECT section_id 
                                  FROM sections 
                                  WHERE section_id=$section_id 
                                    AND teacher_id=$teacher_id 
                                    AND sy_id=$sy_id");
        if ($secCheck->num_rows === 0) {
            $error = "You are not allowed to add pupils to this section.";
        } else {
            $check = $conn->query("SELECT pupil_id 
                                   FROM pupils 
                                   WHERE lrn='$lrn' AND sy_id=$sy_id");
            if ($check->num_rows > 0) {
                $error = "This LRN is already enrolled in the selected School Year.";
            } else {
                $insertPupil = "INSERT INTO pupils 
                    (teacher_id, lrn, last_name, first_name, middle_name, sex, birthdate, age,
                     mother_tongue, ip_ethnicity, religion,
                     house_no_street, barangay, municipality, province,
                     father_name, mother_name, guardian_name, relationship_to_guardian,
                     contact_number, learning_modality, remarks,
                     sy_id, section_id, status)
                    VALUES 
                    ($teacher_id,'$lrn','$last_name','$first_name','$middle_name','$sex','$birthdate',$age,
                     '$mother_tongue','$ip_ethnicity','$religion',
                     '$house_no_street','$barangay','$municipality','$province',
                     '$father_name','$mother_name','$guardian_name','$relationship_to_guardian',
                     '$contact_number','$learning_modality','$remarks',
                     $sy_id,$section_id,'$status')";
                
                if ($conn->query($insertPupil)) {
                    $success = "Pupil enrolled successfully!";
                    $formData = [];
                } else {
                    $error = "Insert failed: " . $conn->error;
                }
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
    <title>Add Pupil | LECS Online Student Grading System</title>
    <link rel="icon" href="images/lecs-logo no bg.png" type="image/x-icon">
    <link href="css/adminAddPupil.css" rel="stylesheet">
    <link rel="stylesheet" href="css/sidebar.css">
    <?php include 'theme-script.php'; ?>
</head>
<body>
<div class="container">
    <?php include 'teacherSidebar.php'; ?>
    <div class="main-content">
        <h1>
            <span class="back-arrow" onclick="window.location.href='teacherPupils.php'">←</span>
            Add New Pupil
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

        <div class="import-btn-container">
            <button type="button" class="import-btn" id="openImportModal">Import SF1</button>
        </div>

        <div class="modal" id="importModal">
            <div class="modal-content">
                <h2>Import Pupils</h2>
                <p class="modal-subtext">Select SF1 files to upload. Use 1 file at a time.</p>
                <form id="importForm" enctype="multipart/form-data" class="import-form">
                    <label class="custom-file-upload">
                        <input class="sf1-file" type="file" name="sf1_files[]" accept=".xls,.xlsx" required>
                    </label>
                    <select name="sy_id" id="import_sy_id" required>
                        <option value="">Select School Year</option>
                        <?php
                        $years = $conn->query("SELECT sy_id, school_year FROM school_years ORDER BY school_year DESC");
                        while ($sy = $years->fetch_assoc()) {
                            echo "<option value='{$sy['sy_id']}'>{$sy['school_year']}</option>";
                        }
                        ?>
                    </select>
                    <select name="section_id" id="import_section_id" required>
                        <option value="">Select Section</option>
                    </select>
                    <div class="modal-buttons">
                        <button type="button" class="btn-cancel" id="closeImportModal">Cancel</button>
                        <button type="submit" class="btn-force">Upload & Import</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal" id="importResultModal">
            <div class="modal-content">
                <h2>Import Results</h2>
                <p id="importMessage"></p>
                <div class="modal-buttons">
                    <button type="button" class="btn-okay" id="closeResultModal">OK</button>
                </div>
            </div>
        </div>

        <form method="POST" id="addPupilForm">
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
                            <option value="Male" <?= (isset($formData['sex']) && $formData['sex']=="Male")?"selected":"" ?>>Male</option>
                            <option value="Female" <?= (isset($formData['sex']) && $formData['sex']=="Female")?"selected":"" ?>>Female</option>
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
                            while ($p = $provinces->fetch_assoc()) {
                                $sel = (isset($formData['province']) && $formData['province'] == $p['province']) ? 'selected' : '';
                                echo "<option value='{$p['province']}' $sel>{$p['province']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Municipality/City</label>
                        <select name="municipality" id="municipality" required>
                            <option value="">Select Municipality/City</option>
                            <?php if(isset($formData['province']) && $formData['province'] != ''): 
                                $municipalities = $conn->query("SELECT DISTINCT municipality FROM ph_addresses WHERE province='".$formData['province']."' ORDER BY municipality ASC");
                                while($m = $municipalities->fetch_assoc()){
                                    $sel = (isset($formData['municipality']) && $formData['municipality'] == $m['municipality']) ? 'selected' : '';
                                    echo "<option value='{$m['municipality']}' $sel>{$m['municipality']}</option>";
                                }
                            endif; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Barangay</label>
                        <select name="barangay" id="barangay" required>
                            <option value="">Select Barangay</option>
                            <?php if(isset($formData['municipality']) && $formData['municipality'] != ''): 
                                $barangays = $conn->query("SELECT barangay FROM ph_addresses WHERE municipality='".$formData['municipality']."' ORDER BY barangay ASC");
                                while($b = $barangays->fetch_assoc()){
                                    $sel = (isset($formData['barangay']) && $formData['barangay'] == $b['barangay']) ? 'selected' : '';
                                    echo "<option value='{$b['barangay']}' $sel>{$b['barangay']}</option>";
                                }
                            endif; ?>
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
                            $years = $conn->query("SELECT sy_id, school_year FROM school_years ORDER BY school_year DESC");
                            while ($sy = $years->fetch_assoc()) {
                                $sel = (isset($formData['sy_id']) && $formData['sy_id']==$sy['sy_id'])?'selected':'';
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
                            <option value="Modular (Print)" <?= (isset($formData['learning_modality']) && $formData['learning_modality'] == "Modular (Print)") ? "selected" : "" ?>>Modular (Print)</option>
                            <option value="Modular Digital" <?= (isset($formData['learning_modality']) && $formData['learning_modality'] == "Modular Digital") ? "selected" : "" ?>>Modular Digital</option>
                            <option value="Online" <?= (isset($formData['learning_modality']) && $formData['learning_modality'] == "Online") ? "selected" : "" ?>>Online</option>
                            <option value="Educational TV" <?= (isset($formData['learning_modality']) && $formData['learning_modality'] == "Educational TV") ? "selected" : "" ?>>Educational TV</option>
                            <option value="Radio-based Instruction" <?= (isset($formData['learning_modality']) && $formData['learning_modality'] == "Radio-based Instruction") ? "selected" : "" ?>>Radio-based Instruction</option>
                            <option value="Homeschooling" <?= (isset($formData['learning_modality']) && $formData['learning_modality'] == "Homeschooling") ? "selected" : "" ?>>Homeschooling</option>
                            <option value="Face to Face" <?= (isset($formData['learning_modality']) && $formData['learning_modality'] == "Face to Face") ? "selected" : "" ?>>Face to Face</option>
                        </select>
                    </div>

                    <!-- Status + Conditional Date -->
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="status" required>
                            <option value="enrolled" <?= (isset($formData['status']) && $formData['status']=="enrolled")?"selected":"" ?>>Enrolled</option>
                            <option value="dropped" <?= (isset($formData['status']) && $formData['status']=="dropped")?"selected":"" ?>>Dropped</option>
                            <option value="promoted" <?= (isset($formData['status']) && $formData['status']=="promoted")?"selected":"" ?>>Promoted</option>
                            <option value="retained" <?= (isset($formData['status']) && $formData['status']=="retained")?"selected":"" ?>>Retained</option>
                            <option value="transferred_in" <?= (isset($formData['status']) && $formData['status']=="transferred_in")?"selected":"" ?>>Transferred In</option>
                            <option value="transferred_out" <?= (isset($formData['status']) && $formData['status']=="transferred_out")?"selected":"" ?>>Transferred Out</option>
                        </select>
                    </div>

                    <!-- Conditional Date Field -->
                    <div class="form-group status-date-group" id="statusDateGroup">
                        <label id="statusDateLabel">Date</label>
                        <input type="date" name="status_date" id="status_date" value="<?= htmlspecialchars($formData['status_date'] ?? '') ?>">
                    </div>
                </div>
            </fieldset>

            <button type="submit" name="submit" class="add-btn">Add Pupil</button>
        </form>
    </div>
</div>

<script>
// Auto-calculate age
document.getElementById("birthdate")?.addEventListener("change", function() {
    const birthdate = new Date(this.value);
    const today = new Date();
    let age = today.getFullYear() - birthdate.getFullYear();
    const m = today.getMonth() - birthdate.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birthdate.getDate())) age--;
    document.getElementById("age").value = age;
});

// Show/hide date field based on status
function toggleStatusDate() {
    const status = document.getElementById('status').value;
    const dateGroup = document.getElementById('statusDateGroup');
    const dateInput = document.getElementById('status_date');
    const label = document.getElementById('statusDateLabel');

    if (['dropped', 'transferred_in', 'transferred_out'].includes(status)) {
        dateGroup.classList.add('show');
        dateInput.required = true;
        label.textContent = status === 'dropped' ? 'Drop Date' : 
                              status === 'transferred_in' ? 'Transfer In Date' : 'Transfer Out Date';
    } else {
        dateGroup.classList.remove('show');
        dateInput.required = false;
        dateInput.value = '';
    }
}

// Initialize on load
document.addEventListener('DOMContentLoaded', toggleStatusDate);
document.getElementById('status').addEventListener('change', toggleStatusDate);

// Existing scripts (SY, Province, etc.)
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

// Import modal scripts (unchanged)
const importBtn = document.getElementById('openImportModal');
const importModal = document.getElementById('importModal');
const closeModal = document.getElementById('closeImportModal');
const importForm = document.getElementById('importForm');
const resultModal = document.getElementById('importResultModal');
const resultMessage = document.getElementById('importMessage');
const closeResultModal = document.getElementById('closeResultModal');

importBtn.addEventListener('click', () => importModal.classList.add('show'));
closeModal.addEventListener('click', () => importModal.classList.remove('show'));

window.addEventListener('click', e => {
    if (e.target === importModal) importModal.classList.remove('show');
    if (e.target === resultModal) resultModal.classList.remove('show');
});

importForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const importBtn = this.querySelector('.btn-force');
    importBtn.disabled = true;
    importBtn.classList.add('loading');
    importBtn.innerHTML = 'Loading... <span class="spinner"></span>';

    const formData = new FormData(this);
    fetch('import_sf1.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        importModal.classList.remove('show');
        importBtn.disabled = false;
        importBtn.classList.remove('loading');
        importBtn.innerHTML = 'Upload & Import';
        resultMessage.innerHTML = `Imported: ${data.imported}<br>Skipped (duplicates/errors/invalid LRN): ${data.skipped}`;
        resultModal.classList.add('show');
    })
    .catch(error => {
        importModal.classList.remove('show');
        importBtn.disabled = false;
        importBtn.classList.remove('loading');
        importBtn.innerHTML = 'Upload & Import';
        resultMessage.innerHTML = `Error: ${error.message}`;
        resultModal.classList.add('show');
    });
});

closeResultModal.addEventListener('click', () => resultModal.classList.remove('show'));

document.getElementById("import_sy_id").addEventListener("change", function(){
    const sy_id = this.value;
    const sectionDropdown = document.getElementById("import_section_id");
    sectionDropdown.innerHTML = "<option>Loading...</option>";

    fetch("get_sections.php?sy_id=" + sy_id)
        .then(response => response.text())
        .then(data => {
            sectionDropdown.innerHTML = data;
        });
});

// Auto-fill pupil info when existing LRN detected
document.querySelector("input[name='lrn']").addEventListener("input", function() {
    const lrn = this.value.trim();

    // Only trigger when exactly 12 digits
    if (/^\d{12}$/.test(lrn)) {
        fetch("get_pupil_info.php?lrn=" + lrn)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Auto-fill all related fields
                    document.querySelector("input[name='last_name']").value = data.last_name || "";
                    document.querySelector("input[name='first_name']").value = data.first_name || "";
                    document.querySelector("input[name='middle_name']").value = data.middle_name || "";
                    document.querySelector("select[name='sex']").value = data.sex || "";
                    document.querySelector("input[name='birthdate']").value = data.birthdate || "";
                    document.querySelector("input[name='age']").value = data.age || "";
                    document.querySelector("input[name='mother_tongue']").value = data.mother_tongue || "";
                    document.querySelector("input[name='ip_ethnicity']").value = data.ip_ethnicity || "";
                    document.querySelector("input[name='religion']").value = data.religion || "";

                    // Address
                    document.querySelector("input[name='house_no_street']").value = data.house_no_street || "";
                    document.querySelector("select[name='province']").value = data.province || "";
                    
                    // Trigger municipality & barangay reload
                    fetch('get_municipalities.php?province=' + encodeURIComponent(data.province))
                        .then(res => res.text())
                        .then(municipalities => {
                            document.getElementById('municipality').innerHTML = municipalities;
                            document.querySelector("select[name='municipality']").value = data.municipality || "";
                            return fetch('get_barangays.php?municipality=' + encodeURIComponent(data.municipality));
                        })
                        .then(res => res.text())
                        .then(barangays => {
                            document.getElementById('barangay').innerHTML = barangays;
                            document.querySelector("select[name='barangay']").value = data.barangay || "";
                        });

                    // Parents & guardian
                    document.querySelector("input[name='father_name']").value = data.father_name || "";
                    document.querySelector("input[name='mother_name']").value = data.mother_name || "";
                    document.querySelector("input[name='guardian_name']").value = data.guardian_name || "";
                    document.querySelector("input[name='relationship_to_guardian']").value = data.relationship_to_guardian || "";
                    document.querySelector("input[name='contact_number']").value = data.contact_number || "";

                    console.log("Auto-filled pupil data from latest record.");
                } else {
                    console.log("No existing LRN found.");
                }
            })
            .catch(err => console.error("Fetch error:", err));
    }
});

</script>
</body>
</html>