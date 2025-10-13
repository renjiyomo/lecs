<?php include 'lecs_db.php'; 
session_start();

// Restrict access to teachers only
if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 'a') {
    header("Location: /lecs/Landing/Login/login.php");
    exit;
}

$teacher_id = intval($_SESSION['teacher_id']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Levels</title>
    <link rel="icon" href="images/lecs-logo no bg.png" type="image/x-icon">
    <link rel="stylesheet" href="css/adminGradeLevel.css">
    <link rel="stylesheet" href="css/sidebar.css">
</head>
<body class="light">
<div class="container">
<?php include 'sidebar.php'; ?>

  <div class="main-content">
    <div class="header">
      <h1>Grade Levels</h1>
      
      <!-- Error Alert -->
      <?php if (isset($_GET['error'])): ?>
        <div class="alert error">
          <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
      <?php endif; ?>

      <!-- Success Alert -->
      <?php if (isset($_GET['success'])): ?>
        <div class="alert success">
          <?php if ($_GET['success'] === "added") echo "Grade Level successfully added!";
                elseif ($_GET['success'] === "updated") echo "Grade Level successfully updated!";
                elseif ($_GET['success'] === "deleted") echo "Grade Level successfully deleted!"; ?>
        </div>
      <?php endif; ?>

      <form method="GET" style="margin:0;">
        <input type="text" name="search" placeholder="Search grade level..."
          value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
      </form>
    </div>

    <div class="header-buttons">
  <div class="export-dropdown">
    <button type="button" class="export-btn" onclick="toggleDropdown()">Export ▼</button>
    <div id="exportOptions" class="dropdown-content">
      <a href="export_grade_level.php?format=excel&search=<?php echo urlencode($_GET['search'] ?? ''); ?>&grade_filter=<?php echo urlencode($_GET['grade_filter'] ?? ''); ?>&sy_filter=<?php echo urlencode($_GET['sy_filter'] ?? ''); ?>">Export as Excel</a>
      <a href="export_grade_level.php?format=pdf&search=<?php echo urlencode($_GET['search'] ?? ''); ?>&grade_filter=<?php echo urlencode($_GET['grade_filter'] ?? ''); ?>&sy_filter=<?php echo urlencode($_GET['sy_filter'] ?? ''); ?>">Export as PDF</a>
    </div>
  </div>
      <button type="button" class="add-btn" onclick="openAddModal()">Add Grade Level</button>
    </div>

    <table>
      <thead>
        <tr>
          <th>Grade Level</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $where = "";
        if (!empty($_GET['search'])) {
          $search = $conn->real_escape_string($_GET['search']);
          $where = "WHERE level_name LIKE '%$search%'";
        }

        $sql = "SELECT * FROM grade_levels $where ORDER BY level_name ASC";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
          while ($row = $result->fetch_assoc()) {
            echo "<tr>
                <td>{$row['level_name']}</td>
                <td class='action-btns'>
                  <button class='edit-btn' onclick=\"openEditModal({$row['grade_level_id']}, '{$row['level_name']}')\">Edit</button>
                  <button class='delete-btn' onclick=\"openDeleteModal({$row['grade_level_id']}, '{$row['level_name']}')\">Delete</button>
                </td>
            </tr>";
          }
        } else {
          echo "<tr><td colspan='2'>No grade levels found</td></tr>";
        }
        ?>
      </tbody>
    </table>

    <div class="total-count">
      <?php
      $countQuery = "SELECT COUNT(*) as total FROM grade_levels $where";
      $countResult = $conn->query($countQuery);
      $countRow = $countResult->fetch_assoc();
      echo "Total: " . $countRow['total'];
      ?>
    </div>
  </div>
</div>

<!-- ---------- Add Modal ---------- -->
<div id="addModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>Add Grade Level</h2>
      <button class="close-btn" onclick="closeAddModal()">&times;</button>
    </div>
    <form method="POST" action="add_gradeLevel.php">
      <input type="text" name="level_name" placeholder="Enter grade level..." required>
      <button type="submit" class="save-btn">Save</button>
      <button type="button" class="cancel-btn" onclick="closeAddModal()">Cancel</button>
    </form>
  </div>
</div>

<!-- ---------- Edit Modal ---------- -->
<div id="editModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>Edit Grade Level</h2>
      <button class="close-btn" onclick="closeEditModal()">&times;</button>
    </div>
    <form method="POST" action="edit_gradeLevel.php">
      <input type="hidden" name="grade_level_id" id="editId">
      <input type="text" name="level_name" id="editLevel" required>
      <button type="submit" class="save-btn">Update</button>
      <button type="button" class="cancel-btn" onclick="closeEditModal()">Cancel</button>
    </form>
  </div>
</div>

<!-- ---------- Delete Modal ---------- -->
<div id="deleteModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>Confirm Delete</h2>
      <button class="close-btn" onclick="closeDeleteModal()">&times;</button>
    </div>
    <form method="GET" action="delete_gradeLevel.php">
      <input type="hidden" name="id" id="deleteId">
      <p>Are you sure you want to delete <strong id="deleteName"></strong>?</p>
      <button type="submit" class="delete-btn">Yes, Delete</button>
      <button type="button" class="cancel-btn" onclick="closeDeleteModal()">Cancel</button>
    </form>
  </div>
</div>

<script>
    function toggleDropdown() {
    document.getElementById("exportOptions").classList.toggle("show");
  }

  function openAddModal() {
    document.getElementById("addModal").style.display = "flex";
  }
  function closeAddModal() {
    document.getElementById("addModal").style.display = "none";
  }

  // ---------- Edit Modal ----------
  function openEditModal(id, name) {
    document.getElementById("editId").value = id;
    document.getElementById("editLevel").value = name;
    document.getElementById("editModal").style.display = "flex";
  }
  function closeEditModal() {
    document.getElementById("editModal").style.display = "none";
  }

  // ---------- Delete Modal ----------
  function openDeleteModal(id, name) {
    document.getElementById("deleteId").value = id;
    document.getElementById("deleteName").textContent = name;
    document.getElementById("deleteModal").style.display = "flex";
  }
  function closeDeleteModal() {
    document.getElementById("deleteModal").style.display = "none";
  }

  // Close modals on outside click
  window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
      closeAddModal();
      closeEditModal();
      closeDeleteModal();
    }
  }

  // Auto-hide alerts
  setTimeout(() => {
    document.querySelectorAll('.alert').forEach(alert => {
      alert.style.opacity = "0";
      setTimeout(() => alert.remove(), 500);
    });
  }, 3000);
</script>

</body>
</html>
