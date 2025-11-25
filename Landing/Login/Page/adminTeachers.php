<?php 
include 'lecs_db.php'; 
session_start();

// Restrict access to admins only
if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 'a') {
    header("Location: /lecs/Landing/Login/login.php");
    exit;
}

$teacher_id = intval($_SESSION['teacher_id']);

// Helper functions
function format_position_short($position) {
    if (preg_match('/(Master Teachers|Master Teacher|Teachers|Teacher|Principal|Administrative Officer|Administrative Assistant|Administrative Aide) (\d+)/i', $position, $matches)) {
        $prefix = $matches[1];
        $number = $matches[2];
        if (strcasecmp($prefix, 'Administrative Assistant') === 0) {
            return 'ADAS-' . numToRoman($number);
        } elseif (strcasecmp($prefix, 'Administrative Aide') === 0) {
            return 'ADA-' . numToRoman($number);
        }
        $words = explode(' ', $prefix);
        $initials = '';
        foreach ($words as $word) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
        return $initials . '-' . numToRoman($number);
    }
    return $position;
}

function numToRoman($num) {
    $map = ['1' => 'I', '2' => 'II', '3' => 'III', '4' => 'IV', '5' => 'V', '6' => 'VI'];
    return isset($map[$num]) ? $map[$num] : $num;
}

// Handle Actions (Activate/Deactivate/Delete)
if (isset($_POST['action']) && isset($_POST['teacher_id'])) {
    $teacher_id = intval($_POST['teacher_id']);

    if ($_POST['action'] === 'status_toggle') {
        $stmt = $conn->prepare("UPDATE teachers SET user_status = IF(user_status='a','i','a') WHERE teacher_id = ?");
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $stmt->close();
    }

    if ($_POST['action'] === 'delete_confirmed') {
        $check = $conn->query("
            SELECT 1 FROM sections WHERE teacher_id = $teacher_id
            UNION
            SELECT 1 FROM pupils p
            JOIN sections s ON p.section_id = s.section_id 
            WHERE s.teacher_id = $teacher_id
            LIMIT 1
        ");
        if ($check && $check->num_rows > 0) {
            header("Location: adminTeachers.php?error=Teacher+cannot+be+removed+because+they+are+assigned+to+a+section+or+pupil.+Consider+deactivating+instead.");
            exit();
        } else {
            $stmt = $conn->prepare("DELETE FROM teacher_positions WHERE teacher_id = ?");
            $stmt->bind_param("i", $teacher_id);
            $stmt->execute();
            $stmt->close();

            $getImg = $conn->query("SELECT image FROM teachers WHERE teacher_id = $teacher_id");
            if ($getImg && $getImg->num_rows > 0) {
                $imgRow = $getImg->fetch_assoc();
                $imageFile = $imgRow['image'];
                $uploadDir = __DIR__ . "/Uploads/teachers/";
                if ($imageFile && $imageFile !== "teacher.png") {
                    $filePath = $uploadDir . $imageFile;
                    if (file_exists($filePath)) unlink($filePath);
                }
            }

            $stmt = $conn->prepare("DELETE FROM teachers WHERE teacher_id = ?");
            $stmt->bind_param("i", $teacher_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    header("Location: adminTeachers.php");
    exit();
}

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sy_id = isset($_GET['sy']) ? intval($_GET['sy']) : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

$sy_start = $sy_end = null;
if ($sy_id > 0) {
    $stmt_sy = $conn->prepare("SELECT start_date, end_date FROM school_years WHERE sy_id = ?");
    $stmt_sy->bind_param("i", $sy_id);
    $stmt_sy->execute();
    $sy_res = $stmt_sy->get_result();
    if ($sy_row = $sy_res->fetch_assoc()) {
        $sy_start = $sy_row['start_date'];
        $sy_end = $sy_row['end_date'];
    }
    $stmt_sy->close();
}

// Build query dynamically
$where = "WHERE 1";
$params = [];
$types = "";

if ($status_filter === 'active') {
    $where .= " AND t.user_status = 'a'";
} elseif ($status_filter === 'inactive') {
    $where .= " AND t.user_status = 'i'";
}

if (!empty($search)) {
    $where .= " AND (t.first_name LIKE ? OR t.middle_name LIKE ? OR t.last_name LIKE ? OR t.employee_no LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
    $types .= "ssss";
}

if ($sy_id > 0) {
    $where .= " AND EXISTS (SELECT 1 FROM teacher_positions tp WHERE tp.teacher_id = t.teacher_id AND tp.start_date <= ? AND (tp.end_date >= ? OR tp.end_date IS NULL))";
    $params[] = $sy_end;
    $params[] = $sy_start;
    $types .= "ss";
}

$sql = "SELECT t.teacher_id, t.first_name, t.middle_name, t.last_name, t.employee_no, t.position, t.user_status 
        FROM teachers t 
        $where 
        ORDER BY CAST(t.employee_no AS UNSIGNED) ASC";

if (!empty($types)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    $result = $conn->query($sql);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>List of Personnel | LECS Online Student Grading System</title>
  <link rel="icon" href="images/lecs-logo no bg.png" type="image/x-icon">
  <link href="css/sidebar.css" rel="stylesheet">
  <link href="css/adminTeachers.css" rel="stylesheet">
  <?php include 'theme-script.php'; ?>
</head>
<body>
  <div class="container">
    <?php include 'sidebar.php'; ?>
    <div class="overlay" onclick="closeSidebar()"></div>
    <div class="main-content">
      <div class="mobile-header">
            <button class="mobile-burger" onclick="openSidebar()">&#9776;</button>
            <h2>List of Personnel</h2>
      </div>

      <div class="header">
        <h1>List of Personnel</h1>
        <form method="GET" style="margin:0;">
          <input type="text" name="search" placeholder="Search by name or ID..." value="<?php echo htmlspecialchars($search); ?>">
          <input type="hidden" name="sy" value="<?php echo $sy_id; ?>">
        </form>
      </div>

      <div class="header-buttons">
        <form method="GET" class="filters" style="display:inline-block;">
          <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
         <label><b>School Year:</b> 
          <select name="sy" onchange="this.form.submit()">
            <option value="0" <?php if($sy_id==0) echo 'selected'; ?>>All Years</option>
            <?php
            $sy_result = $conn->query("SELECT sy_id, school_year FROM school_years ORDER BY school_year DESC");
            while($sy_row = $sy_result->fetch_assoc()){
                $selected = ($sy_id == $sy_row['sy_id']) ? 'selected' : '';
                echo "<option value='{$sy_row['sy_id']}' $selected>{$sy_row['school_year']}</option>";
            }
            ?>
          </select>
          </label>

          <label><b>Status:</b>
          <select name="status" onchange="this.form.submit()">
            <option value="all" <?php if($status_filter=='all') echo 'selected'; ?>>All</option>
            <option value="active" <?php if($status_filter=='active') echo 'selected'; ?>>Active</option>
            <option value="inactive" <?php if($status_filter=='inactive') echo 'selected'; ?>>Inactive</option>
          </select>
        </label>
        </form>
          

        <div class="export-dropdown">
          <button type="button" class="export-btn" onclick="toggleDropdown()">Export ▼</button>
          <div id="exportOptions" class="dropdown-content">
            <a href="export_teachers.php?format=excel&search=<?php echo urlencode($search); ?>&sy=<?php echo $sy_id; ?>">Export as Excel</a>
            <a href="export_teachers.php?format=pdf&search=<?php echo urlencode($search); ?>&sy=<?php echo $sy_id; ?>">Export as PDF</a>
          </div>
        </div>
        <a href="add_teacher.php"><button type="button" class="add-btn">Add Personnel</button></a>
      </div>

      <?php if (isset($_GET['error'])): ?>
        <div class="error-message"><?php echo htmlspecialchars($_GET['error']); ?></div>
      <?php endif; ?>

      <table class="table-wrapper">
        <thead>
          <tr>
            <th>Employee No.</th>
            <th>Name</th>
            <th>Position</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php
          if ($result && $result->num_rows > 0) {
              while ($row = $result->fetch_assoc()) {
                  $status = ($row['user_status'] == 'a') ? "Active" : "Inactive";
                  $middle_initial = !empty($row['middle_name']) ? substr($row['middle_name'], 0, 1) . '.' : '';
                  $full_name = trim($row['first_name'] . ' ' . $middle_initial . ' ' . $row['last_name']);
                  ?>
                  <tr>
                    <td data-label="Employee No."><?= htmlspecialchars($row['employee_no']) ?></td>
                    <td data-label="Name"><?= htmlspecialchars($full_name) ?></td>
                    <td data-label="Position"><?= htmlspecialchars(format_position_short($row['position'])) ?></td>
                    <td data-label="Status"><?= htmlspecialchars($status) ?></td>
                    <td data-label="Action" class="action-cell">
                      <a href="edit_teacher.php?id=<?php echo $row['teacher_id']; ?>" class="btn edit-btn">Edit</a>
                      <form method="POST" style="display:inline;">
                        <input type="hidden" name="teacher_id" value="<?php echo $row['teacher_id']; ?>">
                        <button type="submit" name="action" value="status_toggle" 
                                class="btn status-btn <?php echo ($status == 'Active') ? 'deactivate' : 'activate'; ?>">
                            <?php echo ($status == "Active") ? "Deactivate" : "Activate"; ?>
                        </button>
                      </form>
                      <button type="button" class="btn delete-btn" 
                              onclick="openDeleteModal(<?php echo $row['teacher_id']; ?>, '<?php echo addslashes($full_name); ?>')">
                          Remove
                      </button>
                    </td>
                  </tr>
                  <?php
              }
          } else {
              echo "<tr><td colspan='5' style='text-align:center;'>No personnel found</td></tr>";
          }
          ?>
        </tbody>
      </table>

      <div class="total-count">
        Total: <?php echo $result ? $result->num_rows : 0; ?>
      </div>
    </div>
  </div>

  <div id="deleteModal" class="modal">
    <div class="modal-content">
      <h3 id="modalTitle">Confirm Removal</h3>
      <p id="modalText">Are you sure you want to remove this personnel?</p>
      <form method="POST" id="deleteForm">
        <input type="hidden" name="teacher_id" id="deleteTeacherId">
        <input type="hidden" name="action" value="delete_confirmed">
        <div class="modal-buttons">
          <button type="button" class="btn-modal btn-cancel" onclick="closeModal()">Cancel</button>
          <button type="submit" class="btn-modal btn-delete">Remove</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function toggleDropdown() {
      document.getElementById("exportOptions").classList.toggle("show");
    }
    function openDeleteModal(id, name) {
      document.getElementById('deleteTeacherId').value = id;
      document.getElementById('modalTitle').innerText = "Remove Personnel";
      document.getElementById('modalText').innerText = "Are you sure you want to remove " + name + "?";
      document.getElementById('deleteModal').style.display = 'flex';
    }
    function closeModal() {
      document.getElementById('deleteModal').style.display = 'none';
    }
     // Mobile sidebar functions
    function openSidebar() {
        document.querySelector('.sidebar').classList.add('open');
        document.querySelector('.overlay').classList.add('show');
    }
    function closeSidebar() {
        document.querySelector('.sidebar').classList.remove('open');
        document.querySelector('.overlay').classList.remove('show');
    }
  </script>
</body>
</html>
