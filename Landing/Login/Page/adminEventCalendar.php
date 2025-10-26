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
  <title>Event Calendar | LECS Online Student Grading System</title>
  <link rel="icon" href="images/lecs-logo no bg.png" type="image/x-icon">
  <link rel="stylesheet" href="css/sidebar.css">
  <link rel="stylesheet" href="css/eventCalendar.css">
</head>
<body class="light">
  <div class="container">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
      <div class="header">
        <h1>Event Calendar</h1>
        <div class="controls">
          <input type="date" id="filterDate">
          <button onclick="goToToday()">Today</button>
        </div>
      </div>

      <div id="calendar"></div>
    </div>
  </div>

  <!-- Modal -->
  <div id="eventModal" class="modal hidden">
    <div class="modal-content">
      <h2 id="modalTitle">Add Event</h2>

      <label for="eventTitle">Event Title</label>
      <input type="text" id="eventTitle" placeholder="Event Title" />

      <label for="eventStartTime">Start Time</label>
      <input type="time" id="eventStartTime" />

      <label for="eventEnd">End Date</label>
      <input type="date" id="eventEnd" />

      <label for="eventDetails">Event Details</label>
      <textarea id="eventDetails" placeholder="Enter details here..."></textarea>

      <div class="buttons">
        <button id="saveBtn">Save</button>
        <button id="deleteBtn" class="hidden">Delete</button>
        <button id="cancelBtn">Cancel</button>
      </div>
    </div>
  </div>

  <script src="js/index.global.min.js"></script>
  <script src="js/eventCalendar.js"></script>
</body>
</html>
