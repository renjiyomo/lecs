<?php
include 'lecs_db.php'; 
session_start();

// Restrict access to admins only
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
    <title>Sections</title>
    <link rel="icon" href="images/lecs-logo no bg.png" type="image/x-icon">
    <link rel="stylesheet" href="css/adminSections.css">
    <link rel="stylesheet" href="css/sidebar.css">
</head>
<body class="light">
<div class="container">
<?php include 'sidebar.php'; ?>

<div class="main-content">
  <div class="header">
    <h1>Classes</h1>

    <?php if (isset($_GET['error'])): ?>
      <div class="alert error">
        <?php echo htmlspecialchars($_GET['error']); ?>
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
      <div class="alert success">
        <?php 
          if ($_GET['success'] === "added") echo "Section successfully added!";
          elseif ($_GET['success'] === "updated") echo "Section successfully updated!";
          elseif ($_GET['success'] === "deleted") echo "Section successfully deleted!";
        ?>
      </div>
    <?php endif; ?>

    <form method="GET" style="margin:0;">
      <input type="text" name="search" placeholder="Search section..."
        value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
    </form>
  </div>

<div class="header-buttons">
  <form method="GET" class="filter-form">
    <select name="grade_filter" onchange="this.form.submit()">
      <option value="">All Grades</option>
      <?php
      $grades = $conn->query("SELECT * FROM grade_levels ORDER BY level_name ASC");
      while ($g = $grades->fetch_assoc()) {
          $selected = (isset($_GET['grade_filter']) && $_GET['grade_filter'] == $g['grade_level_id']) ? 'selected' : '';
          echo "<option value='{$g['grade_level_id']}' $selected>{$g['level_name']}</option>";
      }
      ?>
    </select>

    <select name="sy_filter" onchange="this.form.submit()">
      <option value="">All School Years</option>
      <?php
      $years = $conn->query("SELECT * FROM school_years ORDER BY school_year ASC");
      while ($y = $years->fetch_assoc()) {
          $selected = (isset($_GET['sy_filter']) && $_GET['sy_filter'] == $y['sy_id']) ? 'selected' : '';
          echo "<option value='{$y['sy_id']}' $selected>{$y['school_year']}</option>";
      }
      ?>
    </select>
  </form>

  <div class="export-dropdown">
    <button type="button" class="export-btn" onclick="toggleDropdown()">Export ▼</button>
    <div id="exportOptions" class="dropdown-content">
      <a href="export_sections.php?format=excel&search=<?php echo urlencode($_GET['search'] ?? ''); ?>&grade_filter=<?php echo urlencode($_GET['grade_filter'] ?? ''); ?>&sy_filter=<?php echo urlencode($_GET['sy_filter'] ?? ''); ?>">Export as Excel</a>
      <a href="export_sections.php?format=pdf&search=<?php echo urlencode($_GET['search'] ?? ''); ?>&grade_filter=<?php echo urlencode($_GET['grade_filter'] ?? ''); ?>&sy_filter=<?php echo urlencode($_GET['sy_filter'] ?? ''); ?>">Export as PDF</a>
    </div>
  </div>
  <button type="button" class="add-btn" onclick="openAddModal()">Add Class</button>
</div>

  <table>
    <thead>
      <tr>
        <th>Section Name</th>
        <th>Grade Level</th>
        <th>School Year</th>
        <th>Teacher</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $whereParts = [];

      if (!empty($_GET['search'])) {
        $search = $conn->real_escape_string($_GET['search']);
        $whereParts[] = "sn.section_name LIKE '%$search%'";
      }

      if (!empty($_GET['grade_filter'])) {
        $grade = (int)$_GET['grade_filter'];
        $whereParts[] = "s.grade_level_id = $grade";
      }

      if (!empty($_GET['sy_filter'])) {
        $sy = (int)$_GET['sy_filter'];
        $whereParts[] = "s.sy_id = $sy";
      }

      $where = count($whereParts) > 0 ? "WHERE " . implode(" AND ", $whereParts) : "";

      $sql = "SELECT s.*, sn.section_name, g.level_name, sy.school_year, CONCAT(t.first_name, ' ', COALESCE(t.middle_name, ''), ' ', t.last_name) AS teacher_name
              FROM sections s
              JOIN section_name sn ON s.section_name = sn.section_name
              LEFT JOIN grade_levels g ON s.grade_level_id = g.grade_level_id
              LEFT JOIN school_years sy ON s.sy_id = sy.sy_id
              LEFT JOIN teachers t ON s.teacher_id = t.teacher_id
              $where
              ORDER BY sn.section_name ASC";

      $result = $conn->query($sql);

      if ($result && $result->num_rows > 0) {
        $i = 1;
        while ($row = $result->fetch_assoc()) {
          $teacher = $row['teacher_name'] ? htmlspecialchars($row['teacher_name']) : '-';
          echo "<tr>
                  <td>" . htmlspecialchars($row['section_name']) . "</td>
                  <td>" . htmlspecialchars($row['level_name']) . "</td>
                  <td>" . htmlspecialchars($row['school_year']) . "</td>
                  <td>$teacher</td>
                  <td class='action-btns'>
                    <button class='edit-btn' onclick=\"openEditModal({$row['section_id']}, '" . htmlspecialchars($row['section_name']) . "', {$row['grade_level_id']}, {$row['sy_id']}, " . ($row['teacher_id'] ?? 'null') . ")\">Edit</button>
                    <button class='delete-btn' onclick=\"openDeleteModal({$row['section_id']}, '" . htmlspecialchars($row['section_name']) . "')\">Delete</button>
                  </td>
                </tr>";
          $i++;
        }
      } else {
        echo "<tr><td colspan='6'>No sections found</td></tr>";
      }
      ?>
    </tbody>
  </table>

  <div class="total-count">
      <?php
      $countQuery = "SELECT COUNT(*) as total 
                    FROM sections s 
                    JOIN section_name sn ON s.section_name = sn.section_name
                    LEFT JOIN grade_levels g ON s.grade_level_id = g.grade_level_id
                    LEFT JOIN school_years sy ON s.sy_id = sy.sy_id
                    LEFT JOIN teachers t ON s.teacher_id = t.teacher_id
                    $where";
      $countResult = $conn->query($countQuery);
      $countRow = $countResult->fetch_assoc();
      echo "Total: " . $countRow['total'];
      ?>
    </div>
</div>
</div>

<div id="addModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>Add Class</h2>
      <button class="close-btn" onclick="closeAddModal()">&times;</button>
    </div>
    <form method="POST" action="add_section.php">
      <select name="section_name" required>
        <option value="">Select Section Name</option>
        <?php
        $sections = $conn->query("SELECT section_name FROM section_name ORDER BY section_name ASC");
        while ($s = $sections->fetch_assoc()) {
            echo "<option value='" . htmlspecialchars($s['section_name']) . "'>" . htmlspecialchars($s['section_name']) . "</option>";
        }
        ?>
      </select>

      <select name="grade_level_id" required>
        <option value="">Select Grade Level</option>
        <?php
        $grades = $conn->query("SELECT * FROM grade_levels ORDER BY level_name ASC");
        while ($g = $grades->fetch_assoc()) {
            echo "<option value='{$g['grade_level_id']}'>" . htmlspecialchars($g['level_name']) . "</option>";
        }
        ?>
      </select>

      <select name="sy_id" required>
        <option value="">Select School Year</option>
        <?php
        $years = $conn->query("SELECT * FROM school_years ORDER BY school_year ASC");
        while ($y = $years->fetch_assoc()) {
            echo "<option value='{$y['sy_id']}'>" . htmlspecialchars($y['school_year']) . "</option>";
        }
        ?>
      </select>

      <select name="teacher_id">
        <option value="">Select Teacher</option>
        <?php
        $teachers = $conn->query("SELECT teacher_id, CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name) AS full_name FROM teachers ORDER BY first_name ASC");
        while ($t = $teachers->fetch_assoc()) {
            echo "<option value='{$t['teacher_id']}'>" . htmlspecialchars($t['full_name']) . "</option>";
        }
        ?>
      </select>

      <button type="submit" class="save-btn">Save</button>
      <button type="button" class="cancel-btn" onclick="closeAddModal()">Cancel</button>
    </form>
  </div>
</div>

<!-- Edit Section Modal -->
<div id="editModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>Edit Class</h2>
      <button class="close-btn" onclick="closeEditModal()">&times;</button>
    </div>
    <form method="POST" action="edit_section.php">
      <input type="hidden" name="section_id" id="editId">
      <select name="section_name" id="editSectionName" required>
        <option value="">Select Section Name</option>
        <?php
        $sections = $conn->query("SELECT section_name FROM section_name ORDER BY section_name ASC");
        while ($s = $sections->fetch_assoc()) {
            echo "<option value='" . htmlspecialchars($s['section_name']) . "'>" . htmlspecialchars($s['section_name']) . "</option>";
        }
        ?>
      </select>

      <select name="grade_level_id" id="editGrade" required>
        <option value="">Select Grade Level</option>
        <?php
        $grades = $conn->query("SELECT * FROM grade_levels ORDER BY level_name ASC");
        while ($g = $grades->fetch_assoc()) {
            echo "<option value='{$g['grade_level_id']}'>" . htmlspecialchars($g['level_name']) . "</option>";
        }
        ?>
      </select>

      <select name="sy_id" id="editSY" required>
        <option value="">Select School Year</option>
        <?php
        $years = $conn->query("SELECT * FROM school_years ORDER BY school_year ASC");
        while ($y = $years->fetch_assoc()) {
            echo "<option value='{$y['sy_id']}'>" . htmlspecialchars($y['school_year']) . "</option>";
        }
        ?>
      </select>

      <select name="teacher_id" id="editTeacher">
        <option value="">Select Teacher</option>
        <?php
        $teachers = $conn->query("SELECT teacher_id, CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name) AS full_name FROM teachers ORDER BY first_name ASC");
        while ($t = $teachers->fetch_assoc()) {
            echo "<option value='{$t['teacher_id']}'>" . htmlspecialchars($t['full_name']) . "</option>";
        }
        ?>
      </select>

      <button type="submit" class="save-btn">Update</button>
      <button type="button" class="cancel-btn" onclick="closeEditModal()">Cancel</button>
    </form>
  </div>
</div>

<!-- Delete Modal -->
<div id="deleteModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>Confirm Delete</h2>
      <button class="close-btn" onclick="closeDeleteModal()">&times;</button>
    </div>
    <form method="GET" action="delete_section.php">
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
  function closeAddModal() { document.getElementById("addModal").style.display = "none"; }

  function openEditModal(id, name, gradeId, syId, teacherId) {
    document.getElementById("editId").value = id;
    document.getElementById("editSectionName").value = name;
    document.getElementById("editGrade").value = gradeId;
    document.getElementById("editSY").value = syId;
    document.getElementById("editTeacher").value = teacherId || "";
    document.getElementById("editModal").style.display = "flex";
  }
  function closeEditModal() { document.getElementById("editModal").style.display = "none"; }

  function openDeleteModal(id, name) {
    document.getElementById("deleteId").value = id;
    document.getElementById("deleteName").textContent = name;
    document.getElementById("deleteModal").style.display = "flex";
  }
  function closeDeleteModal() { document.getElementById("deleteModal").style.display = "none"; }

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
</body>
</html>