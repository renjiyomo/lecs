<?php include 'lecs_db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Event Calendar | LECS Online Student Grading System</title>
  <link rel="icon" href="images/lecs-logo no bg.png" type="image/x-icon">
  <link rel="stylesheet" href="css/sidebar.css">
  <link rel="stylesheet" href="css/eventCalendar.css">
  <style>

  </style>
</head>
<body class="light">
  <div class="container">
    <?php include 'teacherSidebar.php'; ?>

    <div class="main-content">
      <div class="header">
        <h1>Event Calendar</h1>
        <div class="controls">
          <input type="date" id="filterDate">
        </div>
      </div>

      <div id="calendar"></div>
    </div>
  </div>

  <div id="eventModal" class="modal hidden">
    <div class="modal-content">
      <h2 id="modalTitle">Event Details</h2>

      <p><strong>Title:</strong> <span id="viewTitle"></span></p>
      <p><strong>Date:</strong> <span id="viewDate"></span></p>
      <p><strong>End Date:</strong> <span id="viewEndDate"></span></p>
      <p><strong>Start Time:</strong> <span id="viewStartTime"></span></p>
      <p><strong>Details:</strong> <span id="viewDetails"></span></p>

      <div class="buttons">
        <button id="closeBtn">Close</button>
      </div>
    </div>
  </div>

  <script src="js/index.global.min.js"></script>
  <script>
    let calendar;

    document.addEventListener('DOMContentLoaded', () => {
      calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
        initialView: 'dayGridMonth',
        events: {
          url: 'api/fetch.php',
          failure: () => alert('Failed to load events!')
        },
        eventClick: function(info) {
          // Fill modal with event details
          document.getElementById('viewTitle').innerText = info.event.title;
          document.getElementById('viewDate').innerText = info.event.startStr.split("T")[0];
          document.getElementById('viewEndDate').innerText = info.event.extendedProps.end_date;
          document.getElementById('viewStartTime').innerText = info.event.extendedProps.start_time;
          document.getElementById('viewDetails').innerText = info.event.extendedProps.event_details || "No details provided";

          openModal();
        },
        eventDidMount: function(info) {
          // Create tooltip content
          let tooltip = document.createElement("div");
          tooltip.className = "tooltip";
          tooltip.innerHTML = `
            <strong>${info.event.title}</strong><br>
            Date: ${info.event.startStr.split("T")[0]}<br>
            End: ${info.event.extendedProps.end_date || "N/A"}<br>
            Time: ${info.event.extendedProps.start_time || "N/A"}<br>
            Details: ${info.event.extendedProps.event_details || "No details"}
          `;
          info.el.appendChild(tooltip);
        }
      });

      calendar.render();

      document.getElementById('filterDate').addEventListener('change', e => {
        calendar.gotoDate(e.target.value);
      });

      document.getElementById('closeBtn').addEventListener('click', closeModal);
    });

    function openModal() {
      document.getElementById('eventModal').classList.remove('hidden');
    }

    function closeModal() {
      document.getElementById('eventModal').classList.add('hidden');
    }

    function goToToday() {
      calendar.today();
    }
  </script>
</body>
</html>
