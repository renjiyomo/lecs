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
    <title>School Years | LECS Online Student Grading System</title>
    <link rel="icon" href="images/lecs-logo no bg.png" type="image/x-icon">
    <link rel="stylesheet" href="css/adminSchoolYear.css">
    <link rel="stylesheet" href="css/sidebar.css">
</head>
<body class="light">
<div class="container">
<?php include 'sidebar.php'; ?>

  <div class="main-content">
    <div class="header">
      <h1>School Years</h1>

      <!-- Alerts -->
      <?php if (isset($_GET['success'])): ?>
        <div class="alert success">
          <?php if ($_GET['success'] === "added") echo "School Year successfully added!";
                elseif ($_GET['success'] === "updated") echo "School Year successfully updated!";
                elseif ($_GET['success'] === "deleted") echo "School Year successfully deleted!"; ?>
        </div>
      <?php endif; ?>

      <?php if (isset($_GET['error'])): ?>
        <div class="alert error">
          <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
      <?php endif; ?>

      <form method="GET" style="margin:0;">
        <input type="text" name="search" placeholder="Search school year..."
          value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
      </form>
    </div>

    <div class="header-buttons">
      <div class="export-dropdown">
        <button type="button" class="export-btn" onclick="toggleDropdown()">Export ▼</button>
        <div id="exportOptions" class="dropdown-content">
          <a href="export_school_years.php?format=excel&search=<?php echo urlencode($_GET['search'] ?? ''); ?>">Export as Excel</a>
          <a href="export_school_years.php?format=pdf&search=<?php echo urlencode($_GET['search'] ?? ''); ?>">Export as PDF</a>
        </div>
      </div>
      <button type="button" class="add-btn" onclick="openAddModal()">Add School Year</button>
    </div>

    <table>
      <thead>
        <tr>
          <th>School Year</th>
          <th>Start Date</th>
          <th>End Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $where = "";
        if (!empty($_GET['search'])) {
          $search = $conn->real_escape_string($_GET['search']);
          $where = "WHERE school_year LIKE '%$search%'";
        }

        $sql = "SELECT * FROM school_years $where ORDER BY school_year DESC";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
          $i = 1;
          while ($row = $result->fetch_assoc()) {
            echo "<tr>
                    <td>{$row['school_year']}</td>
                    <td>{$row['start_date']}</td>
                    <td>{$row['end_date']}</td>
                    <td class='action-btns'>
                      <button class='edit-btn' onclick=\"openEditModal({$row['sy_id']}, '{$row['school_year']}', '{$row['start_date']}', '{$row['end_date']}')\">Edit</button>
                      <button class='delete-btn' onclick=\"openDeleteModal({$row['sy_id']}, '{$row['school_year']}')\">Delete</button>
                    </td>
                  </tr>";
            $i++;
          }
        } else {
          echo "<tr><td colspan='5'>No school years found</td></tr>";
        }
        ?>
      </tbody>
    </table>

    <div class="total-count">
      <?php
      $countQuery = "SELECT COUNT(*) as total FROM school_years $where";
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
      <h2>Add School Year</h2>
      <button class="close-btn" onclick="closeAddModal()">&times;</button>
    </div>
    <form method="POST" action="add_schoolYear.php">
      <input type="text" name="school_year" placeholder="e.g. 2024-2025" required>
      <input type="date" name="start_date" required>
      <input type="date" name="end_date" required>
      <button type="submit" class="save-btn">Save</button>
      <button type="button" class="cancel-btn" onclick="closeAddModal()">Cancel</button>
    </form>
  </div>
</div>

<!-- ---------- Edit Modal ---------- -->
<div id="editModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>Edit School Year</h2>
      <button class="close-btn" onclick="closeEditModal()">&times;</button>
    </div>
    <form method="POST" action="edit_schoolYear.php">
      <input type="hidden" name="sy_id" id="editId">
      <input type="text" name="school_year" id="editSY" required>
      <input type="date" name="start_date" id="editStart" required>
      <input type="date" name="end_date" id="editEnd" required>
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
    <form method="GET" action="delete_schoolYear.php">
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

  // Add Modal
  function openAddModal() { document.getElementById("addModal").style.display = "flex"; }
  function closeAddModal() { document.getElementById("addModal").style.display = "none"; }

  // Edit Modal
  function openEditModal(id, sy, start, end) {
    document.getElementById("editId").value = id;
    document.getElementById("editSY").value = sy;
    document.getElementById("editStart").value = start;
    document.getElementById("editEnd").value = end;
    document.getElementById("editModal").style.display = "flex";
  }
  function closeEditModal() { document.getElementById("editModal").style.display = "none"; }

  // Delete Modal
  function openDeleteModal(id, sy) {
    document.getElementById("deleteId").value = id;
    document.getElementById("deleteName").textContent = sy;
    document.getElementById("deleteModal").style.display = "flex";
  }
  function closeDeleteModal() { document.getElementById("deleteModal").style.display = "none"; }

  // Close modal when clicking outside
  window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
      closeAddModal();
      closeEditModal();
      closeDeleteModal();
    }
    if (!event.target.matches('.export-btn')) {
      var dropdowns = document.getElementsByClassName("dropdown-content");
      for (var i = 0; i < dropdowns.length; i++) {
        var openDropdown = dropdowns[i];
        if (openDropdown.classList.contains('show')) {
          openDropdown.classList.remove('show');
        }
      }
    }
  }

    setTimeout(() => {
    document.querySelectorAll('.alert').forEach(alert => {
      alert.style.opacity = "0";
      setTimeout(() => alert.remove(), 500);
    });
  }, 3000);
</script>
</script>

</body>
</html>