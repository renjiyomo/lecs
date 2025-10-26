<?php 
include 'lecs_db.php'; 
session_start();

// Restrict access to admins only
if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 'a') {
    header("Location: /lecs/Landing/Login/login.php");
    exit;
}

$teacher_id = intval($_SESSION['teacher_id']);

$selected_sy = isset($_GET['sy_id']) ? intval($_GET['sy_id']) : null;
$selected_section = isset($_GET['section_id']) ? intval($_GET['section_id']) : null;
$selected_status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>List of Pupils | LECS Online Student Grading System</title>
    <link rel="icon" href="images/lecs-logo no bg.png" type="image/x-icon">
    <link href="css/adminPupils.css" rel="stylesheet">
    <link rel="stylesheet" href="css/sidebar.css">
    <?php include 'theme-script.php'; ?>
</head>
<body>

<div id="deleteModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Confirm Deletion</h2>
            <p id="modalMessage"></p>
            <button id="confirmDeleteBtn" class="btn-confirm">Delete</button>
            <button id="forceDeleteBtn" class="btn-force" style="display: none;">Force Delete</button>
            <button class="btn-cancel" onclick="closeModal()">Cancel</button>
        </div>
</div>

<div class="container">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="header">
            <h1>List of Pupils</h1>
            <form method="GET" style="margin:0;">
                <input type="text" name="search" placeholder="Search by name or LRN..." 
                       value="<?= htmlspecialchars($search) ?>" />
            </form>
        </div>

        <form method="GET" class="filters">

            <!-- School Year Filter -->
            <label>School Year:
                <select id="sySelect" name="sy_id" onchange="this.form.submit()">
                    <option value="">All Years</option>
                    <?php
                    $syRes = $conn->query("
                        SELECT DISTINCT sy.sy_id, sy.school_year
                        FROM pupils p
                        JOIN school_years sy ON p.sy_id = sy.sy_id
                        ORDER BY sy.sy_id DESC
                    ");
                    while ($sy = $syRes->fetch_assoc()) {
                        $selected = ($selected_sy == $sy['sy_id']) ? "selected" : "";
                        echo "<option value='{$sy['sy_id']}' $selected>{$sy['school_year']}</option>";
                    }
                    ?>
                </select>
            </label>

            <!-- Section Filter -->
            <label>Class:
                <select id="secSelect" name="section_id" onchange="this.form.submit()">
                    <option value="">All Classes</option>
                    <?php
                    $query = "
                        SELECT DISTINCT s.section_id, s.section_name, g.level_name 
                        FROM sections s
                        JOIN grade_levels g ON s.grade_level_id = g.grade_level_id
                        JOIN pupils p ON p.section_id = s.section_id
                    ";
                    if ($selected_sy) {
                        $query .= " WHERE p.sy_id = $selected_sy ";
                    }
                    $query .= " ORDER BY g.level_name, s.section_name";

                    $secRes = $conn->query($query);
                    while ($sec = $secRes->fetch_assoc()) {
                        $selected = ($selected_section == $sec['section_id']) ? "selected" : "";
                        echo "<option value='{$sec['section_id']}' $selected>Grade {$sec['level_name']} - {$sec['section_name']}</option>";
                    }
                    ?>
                </select>
            </label>

            <label>Status:
                <select name="status" onchange="this.form.submit()">
                    <option value="">All</option>
                    <option value="enrolled" <?= ($selected_status=="enrolled") ? "selected" : "" ?>>Enrolled</option>
                    <option value="dropped" <?= ($selected_status=="dropped") ? "selected" : "" ?>>Dropped</option>
                    <option value="promoted" <?= ($selected_status=="promoted") ? "selected" : "" ?>>Promoted</option>
                    <option value="retained" <?= ($selected_status=="retained") ? "selected" : "" ?>>Retained</option>
                    <option value="transferred_in" <?= ($selected_status=="transferred_in") ? "selected" : "" ?>>Transferred In</option>
                    <option value="transferred_out" <?= ($selected_status=="transferred_out") ? "selected" : "" ?>>Transferred Out</option>
                </select>
            </label>

            <div class="header-buttons">
                <button type="button" class="export-btn" <?= ($selected_sy && $selected_section) ? '' : 'disabled' ?>>
                    <?= ($selected_sy && $selected_section) ? 'Export SF1' : 'Select School Year & Class' ?>
                </button>
            </div>
        </form>

        <table>
            <thead>
                <tr>
                    <th>LRN</th>
                    <th>NAME</th>
                    <th>Age</th>
                    <th>Sex</th>
                    <th>Class</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Build conditions
                $conditions = [];

                if ($selected_section) {
                    $conditions[] = "p.section_id = $selected_section";
                }
                if ($selected_sy) {
                    $conditions[] = "p.sy_id = $selected_sy";
                }
                if (!empty($selected_status)) {
                    $statusEsc = $conn->real_escape_string($selected_status);
                    $conditions[] = "p.status = '$statusEsc'";
                }
                if (!empty($search)) {
                    $searchEsc = $conn->real_escape_string($search);
                    $conditions[] = "(p.lrn LIKE '%$searchEsc%' OR p.last_name LIKE '%$searchEsc%' OR p.first_name LIKE '%$searchEsc%')";
                }

                $where = empty($conditions) ? "" : "WHERE " . implode(" AND ", $conditions);

                $sql = "SELECT p.pupil_id, p.lrn, p.last_name, p.first_name, p.middle_name, p.age, p.sex,
                               s.section_name, g.level_name, sy.school_year, p.status
                        FROM pupils p
                        JOIN sections s ON p.section_id = s.section_id
                        JOIN grade_levels g ON s.grade_level_id = g.grade_level_id
                        JOIN school_years sy ON p.sy_id = sy.sy_id
                        $where
                        ORDER BY p.last_name ASC";

                $result = $conn->query($sql);

                if ($result && $result->num_rows > 0) {
                    $status_display = [
                        'enrolled' => 'Enrolled',
                        'dropped' => 'Dropped',
                        'promoted' => 'Promoted',
                        'retained' => 'Retained',
                        'transferred_in' => 'Transferred In',
                        'transferred_out' => 'Transferred Out',
                    ];
                    while ($row = $result->fetch_assoc()) {
                        $fullname = strtoupper($row['last_name'] . ", " . $row['first_name'] . " " . $row['middle_name']);
                        $escaped_fullname = htmlspecialchars($fullname, ENT_QUOTES, 'UTF-8');
                        $display_status = $status_display[$row['status']] ?? ucfirst($row['status']);
                        echo "<tr>
                                <td>{$row['lrn']}</td>
                                <td>{$fullname}</td>
                                <td>{$row['age']}</td>
                                <td>" . substr($row['sex'],0,1) . "</td>
                                <td>Grade {$row['level_name']} - {$row['section_name']}</td>
                                <td>{$display_status}</td>
                                <td class='action-buttons'>
                                    <a href='adminPupilsProfile.php?pupil_id={$row['pupil_id']}' class='btn-action view-btn'>Profile</a>
                                </td>
                              </tr>";
                    }
                } else {
                    echo "<tr><td colspan='7'>No pupils found</td></tr>";
                }
                ?>
            </tbody>
        </table>

        <div class="total-count">
            <?php
            $countQuery = "SELECT COUNT(*) as total
                           FROM pupils p
                           JOIN sections s ON p.section_id = s.section_id
                           JOIN grade_levels g ON s.grade_level_id = g.grade_level_id
                           JOIN school_years sy ON p.sy_id = sy.sy_id
                           $where";
            $countResult = $conn->query($countQuery);
            $countRow = $countResult->fetch_assoc();
            echo "Total: " . $countRow['total'];
            ?>
        </div>
    </div>
</div>

<script>
    let currentPupilId = null;

    function showModal(pupilId, pupilName, hasGrades = false) {
        currentPupilId = pupilId;
        const modal = document.getElementById('deleteModal');
        const modalMessage = document.getElementById('modalMessage');
        const forceDeleteBtn = document.getElementById('forceDeleteBtn');
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

        if (hasGrades) {
            modalMessage.innerHTML = `The pupil <strong>${pupilName}</strong> has grades recorded. Deleting this pupil will also delete their grades. Proceed with caution.`;
            forceDeleteBtn.style.display = 'inline-block';
            confirmDeleteBtn.style.display = 'none';
        } else {
            modalMessage.innerHTML = `Are you sure you want to delete <strong>${pupilName}</strong>? This action cannot be undone.`;
            forceDeleteBtn.style.display = 'none';
            confirmDeleteBtn.style.display = 'inline-block';
        }

        modal.style.display = 'block';
    }

    function closeModal() {
        document.getElementById('deleteModal').style.display = 'none';
    }

    function confirmDelete(pupilId, pupilName) {
        console.log('Fetching grades for pupil ID:', pupilId); // Debug
        fetch('check_grades.php?id=' + pupilId)
            .then(response => {
                console.log('Fetch response:', response); // Debug
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                console.log('Fetch data:', data); // Debug
                showModal(pupilId, pupilName, data.hasGrades);
            })
            .catch(error => {
                console.error('Error checking grades:', error);
                showModal(pupilId, pupilName, false);
            });
        return false; // Prevent default link behavior
    }

    document.getElementById('confirmDeleteBtn').addEventListener('click', () => {
        console.log('Confirm delete clicked for pupil ID:', currentPupilId); // Debug
        window.location.href = 'delete_pupil.php?id=' + currentPupilId;
    });

    document.getElementById('forceDeleteBtn').addEventListener('click', () => {
        console.log('Force delete clicked for pupil ID:', currentPupilId); // Debug
        window.location.href = 'delete_pupil.php?id=' + currentPupilId + '&force=true';
    });

    window.onclick = function(event) {
        const modal = document.getElementById('deleteModal');
        if (event.target == modal) {
            closeModal();
        }
    };

    // Export SF1 functionality
    const exportBtn = document.querySelector('.export-btn');
    exportBtn.addEventListener('click', function(e) {
        if (this.disabled) {
            e.preventDefault();
            return;
        }
        const sySelect = document.getElementById('sySelect');
        const secSelect = document.getElementById('secSelect');
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'export_sf1.php';
        ['sy_id', 'section_id'].forEach(name => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = document.querySelector(`select[name="${name}"]`).value;
            form.appendChild(input);
        });
        document.body.appendChild(form);
        form.submit();
    });
</script>

</body>
</html>