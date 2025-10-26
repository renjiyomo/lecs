<?php
include 'lecs_db.php';
session_start();

// Restrict access to teachers only
if (!isset($_SESSION['teacher_id']) || $_SESSION['user_type'] !== 'a') {
    header("Location: /lecs/Landing/Login/login.php");
    exit;
}

$teacher_id = intval($_SESSION['teacher_id']);

$sy_res = $conn->query("SELECT * FROM school_years ORDER BY sy_id DESC");
$school_years = $sy_res->fetch_all(MYSQLI_ASSOC);
$current_sy = $_GET['sy_id'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subjects | LECS Online Student Grading System</title>
    <link rel="icon" href="images/lecs-logo no bg.png" type="image/x-icon">
    <link rel="stylesheet" href="css/adminSubjects.css">
    <link rel="stylesheet" href="css/sidebar.css">
</head>
<body class="light">
<div class="container">
<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="header">
      <h1>Subjects</h1>

      <!-- Error Alert -->
      <?php if (isset($_GET['error'])): ?>
        <div class="alert error">
          <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
      <?php endif; ?>

      <!-- Success Alert -->
      <?php if (isset($_GET['success'])): ?>
        <div class="alert success">
            <?php if ($_GET['success'] === "added") echo "Subject successfully added!";
                  elseif ($_GET['success'] === "updated") echo "Subject successfully updated!";
                  elseif ($_GET['success'] === "deleted") echo "Subject successfully deleted!"; ?>
        </div>
      <?php endif; ?>

      <form method="GET" id="searchForm" style="margin:0;">
        <input type="text" name="search" placeholder="Search subject..."
          value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
      </form>
    </div>

<div class="header-buttons">
  <form method="GET" id="filterForm" class="filter-form">
    <select name="sy_id" onchange="this.form.submit()">
      <option value="">All School Years</option>
      <?php foreach ($school_years as $sy): ?>
        <option value="<?= $sy['sy_id'] ?>" <?= ($current_sy == $sy['sy_id']) ? 'selected' : '' ?>>
          <?= htmlspecialchars($sy['school_year']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="grade_id" onchange="this.form.submit()">
      <option value="">All Grades</option>
      <?php
      $grades = $conn->query("SELECT * FROM grade_levels ORDER BY level_name ASC");
      $current_grade = $_GET['grade_id'] ?? '';
      while ($g = $grades->fetch_assoc()):
      ?>
        <option value="<?= $g['grade_level_id'] ?>" <?= ($current_grade == $g['grade_level_id']) ? 'selected' : '' ?>>
          <?= htmlspecialchars($g['level_name']) ?>
        </option>
      <?php endwhile; ?>
    </select>
  </form>

  <div class="export-dropdown">
    <button type="button" class="export-btn" onclick="toggleDropdown()">Export ▼</button>
    <div id="exportOptions" class="dropdown-content">
      <a href="export_subjects.php?format=excel&search=<?php echo urlencode($_GET['search'] ?? ''); ?>&grade_id=<?php echo urlencode($_GET['grade_id'] ?? ''); ?>&sy_id=<?php echo urlencode($_GET['sy_id'] ?? ''); ?>">Export as Excel</a>
      <a href="export_subjects.php?format=pdf&search=<?php echo urlencode($_GET['search'] ?? ''); ?>&grade_id=<?php echo urlencode($_GET['grade_id'] ?? ''); ?>&sy_id=<?php echo urlencode($_GET['sy_id'] ?? ''); ?>">Export as PDF</a>
    </div>
  </div>
  <button type="button" class="add-btn" onclick="openAddModal()">Add Subject</button>
</div>

    <table>
      <thead>
        <tr>
          <th>Subject Name</th>
          <th>Grade Level</th>
          <th>School Year</th>
          <th>Start Quarter</th>
          <th>Parent Subject</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $where = "";
        $params = [];
        if (!empty($_GET['search'])) {
          $search = "%" . $conn->real_escape_string($_GET['search']) . "%";
          $where = "WHERE s.subject_name LIKE ? OR g.level_name LIKE ? OR sy.school_year LIKE ? OR p.subject_name LIKE ?";
          $params = array_merge($params, [$search, $search, $search, $search]);
        }
        if ($current_sy) {
          $where .= ($where ? " AND " : "WHERE ") . "s.sy_id = ?";
          $params[] = $current_sy;
        }
        if (!empty($current_grade)) {
          $where .= ($where ? " AND " : "WHERE ") . "s.grade_level_id = ?";
          $params[] = $current_grade;
        }

        $sql = "SELECT s.subject_id, s.subject_name, s.grade_level_id, s.sy_id, s.parent_subject_id, s.start_quarter,
                       g.level_name, sy.school_year, p.subject_name AS parent_name
                FROM subjects s
                JOIN grade_levels g ON s.grade_level_id = g.grade_level_id
                JOIN school_years sy ON s.sy_id = sy.sy_id
                LEFT JOIN subjects p ON s.parent_subject_id = p.subject_id
                $where
                ORDER BY sy.school_year DESC, g.level_name ASC, s.subject_name ASC";

        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
          $types = str_repeat('s', count($params));
          $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
          while ($row = $result->fetch_assoc()) {
            $parent_id = $row['parent_subject_id'] ? $row['parent_subject_id'] : 'null';
            echo "<tr>
                    <td>" . htmlspecialchars($row['subject_name']) . "</td>
                    <td>" . htmlspecialchars($row['level_name']) . "</td>
                    <td>" . htmlspecialchars($row['school_year']) . "</td>
                    <td>" . htmlspecialchars($row['start_quarter']) . "</td>
                    <td>" . ($row['parent_name'] ? htmlspecialchars($row['parent_name']) : '') . "</td>
                    <td class='action-btns'>
                      <button class='edit-btn' onclick=\"openEditModal({$row['subject_id']}, '" . addslashes($row['subject_name']) . "', {$row['grade_level_id']}, {$row['sy_id']}, {$parent_id}, '" . $row['start_quarter'] . "')\">Edit</button>
                      <button class='delete-btn' onclick=\"openDeleteModal({$row['subject_id']}, '" . addslashes($row['subject_name']) . "')\">Delete</button>
                    </td>
                  </tr>";
          }
        } else {
          echo "<tr><td colspan='6'>No subjects found</td></tr>";
        }
        ?>
      </tbody>
    </table>

    <div class="total-count">
      <?php
      $countWhere = preg_replace('/^WHERE /', '', $where);
      $countQuery = "SELECT COUNT(*) as total FROM subjects s
                     JOIN grade_levels g ON s.grade_level_id = g.grade_level_id
                     JOIN school_years sy ON s.sy_id = sy.sy_id
                     LEFT JOIN subjects p ON s.parent_subject_id = p.subject_id
                     " . ($countWhere ? "WHERE $countWhere" : "");
      $countStmt = $conn->prepare($countQuery);
      if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $countStmt->bind_param($types, ...$params);
      }
      $countStmt->execute();
      $countResult = $countStmt->get_result();
      $countRow = $countResult->fetch_assoc();
      echo "Total: " . $countRow['total'];
      ?>
    </div>
</div>
</div>

<!-- Add Subject Modal -->
<div id="addModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>Add Subject</h2>
      <button class="close-btn" onclick="closeAddModal()">&times;</button>
    </div>
    <form method="POST" action="add_subject.php">
      <div class="subject-name-container">
        <select name="subject_name_select" id="subjectNameSelect" onchange="toggleSubjectInput('add')">
          <option value="">Select Existing Subject Name</option>
          <?php
          $subjects = $conn->query("SELECT DISTINCT subject_name FROM subjects ORDER BY subject_name ASC");
          while ($s = $subjects->fetch_assoc()) {
              echo "<option value='{$s['subject_name']}'>" . htmlspecialchars($s['subject_name']) . "</option>";
          }
          ?>
          <option value="new">Add New Subject Name</option>
        </select>
        <input type="text" name="subject_name" id="subjectNameInputAdd" placeholder="Enter new subject name..." style="display: none;">
      </div>
      <select id="addSy" name="sy_id" required>
        <option value="">-- Select School Year --</option>
        <?php foreach ($school_years as $sy): ?>
          <option value="<?= $sy['sy_id'] ?>"><?= htmlspecialchars($sy['school_year']) ?></option>
        <?php endforeach; ?>
      </select>
      <select id="addGrade" name="grade_level_id" required>
        <option value="">-- Select Grade Level --</option>
        <?php
        $grades = $conn->query("SELECT * FROM grade_levels ORDER BY level_name ASC");
        while ($g = $grades->fetch_assoc()) {
          echo "<option value='{$g['grade_level_id']}'>" . htmlspecialchars($g['level_name']) . "</option>";
        }
        ?>
      </select>
      <select id="addParent" name="parent_subject_id">
        <option value="">-- No Parent --</option>
      </select>
      <select id="addStartQuarter" name="start_quarter" required>
        <option value="Q1">Q1</option>
        <option value="Q2">Q2</option>
        <option value="Q3">Q3</option>
        <option value="Q4">Q4</option>
      </select>
      <button type="submit" class="save-btn">Save</button>
      <button type="button" class="cancel-btn" onclick="closeAddModal()">Cancel</button>
    </form>
  </div>
</div>

<!-- Edit Subject Modal -->
<div id="editModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>Edit Subject</h2>
      <button class="close-btn" onclick="closeEditModal()">&times;</button>
    </div>
    <form method="POST" action="edit_subject.php">
      <input type="hidden" name="subject_id" id="editId">
      <div class="subject-name-container">
        <select name="subject_name_select" id="subjectNameSelectEdit" onchange="toggleSubjectInput('edit')">
          <option value="">Select Existing Subject Name</option>
          <?php
          $subjects = $conn->query("SELECT DISTINCT subject_name FROM subjects ORDER BY subject_name ASC");
          while ($s = $subjects->fetch_assoc()) {
              echo "<option value='{$s['subject_name']}'>" . htmlspecialchars($s['subject_name']) . "</option>";
          }
          ?>
          <option value="new">Add New Subject Name</option>
        </select>
        <input type="text" name="subject_name" id="subjectNameInputEdit" placeholder="Enter new subject name..." style="display: none;">
      </div>
      <select id="editSy" name="sy_id" required>
        <option value="">-- Select School Year --</option>
        <?php foreach ($school_years as $sy): ?>
          <option value="<?= $sy['sy_id'] ?>"><?= htmlspecialchars($sy['school_year']) ?></option>
        <?php endforeach; ?>
      </select>
      <select id="editGrade" name="grade_level_id" required>
        <option value="">-- Select Grade Level --</option>
        <?php
        $grades = $conn->query("SELECT * FROM grade_levels ORDER BY level_name ASC");
        while ($g = $grades->fetch_assoc()) {
          echo "<option value='{$g['grade_level_id']}'>" . htmlspecialchars($g['level_name']) . "</option>";
        }
        ?>
      </select>
      <select id="editParent" name="parent_subject_id">
        <option value="">-- No Parent --</option>
      </select>
      <select id="editStartQuarter" name="start_quarter" required>
        <option value="Q1">Q1</option>
        <option value="Q2">Q2</option>
        <option value="Q3">Q3</option>
        <option value="Q4">Q4</option>
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
    <form method="GET" action="delete_subject.php">
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
    document.getElementById("addStartQuarter").value = "Q1";
    document.getElementById("subjectNameSelect").value = "";
    document.getElementById("subjectNameInputAdd").style.display = "none";
  }
  function closeAddModal() {
    document.getElementById("addModal").style.display = "none";
  }

  function openEditModal(id, name, gradeId, syId, parentId, startQuarter) {
    document.getElementById("editId").value = id;
    document.getElementById("subjectNameSelectEdit").value = name;
    document.getElementById("subjectNameInputEdit").value = name;
    document.getElementById("subjectNameInputEdit").style.display = "none";
    document.getElementById("editSy").value = syId;
    document.getElementById("editGrade").value = gradeId;
    document.getElementById("editStartQuarter").value = startQuarter;
    updateParents('edit')();
    document.getElementById("editParent").value = parentId === 'null' ? '' : parentId;
    document.getElementById("editModal").style.display = "flex";
  }
  function closeEditModal() {
    document.getElementById("editModal").style.display = "none";
  }

  function openDeleteModal(id, name) {
    document.getElementById("deleteId").value = id;
    document.getElementById("deleteName").textContent = name;
    document.getElementById("deleteModal").style.display = "flex";
  }
  function closeDeleteModal() {
    document.getElementById("deleteModal").style.display = "none";
  }

  function toggleSubjectInput(modalType) {
    const selectId = modalType === 'add' ? 'subjectNameSelect' : 'subjectNameSelectEdit';
    const inputId = modalType === 'add' ? 'subjectNameInputAdd' : 'subjectNameInputEdit';
    const select = document.getElementById(selectId);
    const input = document.getElementById(inputId);
    input.style.display = select.value === 'new' ? 'block' : 'none';
  }

  function updateParents(prefix) {
    return function() {
      let sy = document.getElementById(prefix + 'Sy').value;
      let grade = document.getElementById(prefix + 'Grade').value;
      if (sy && grade) {
        fetch(`get_parents.php?sy_id=${sy}&grade_id=${grade}`)
          .then(res => res.json())
          .then(data => {
            let sel = document.getElementById(prefix + 'Parent');
            sel.innerHTML = '<option value="">-- No Parent --</option>';
            data.forEach(sub => {
              sel.innerHTML += `<option value="${sub.subject_id}">${sub.subject_name}</option>`;
            });
          });
      } else {
        document.getElementById(prefix + 'Parent').innerHTML = '<option value="">-- No Parent --</option>';
      }
    }
  }

  document.getElementById('addSy').addEventListener('change', updateParents('add'));
  document.getElementById('addGrade').addEventListener('change', updateParents('add'));
  document.getElementById('editSy').addEventListener('change', updateParents('edit'));
  document.getElementById('editGrade').addEventListener('change', updateParents('edit'));

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