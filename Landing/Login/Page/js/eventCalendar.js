let calendar;
let selectedDate = '';
let editingEvent = null;

document.addEventListener('DOMContentLoaded', () => {
  calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
    initialView: 'dayGridMonth',
    eventColor: '#2563eb',
    eventTextColor: '#fff',

    dateClick: function(info) {
      selectedDate = info.dateStr;
      editingEvent = null;
      document.getElementById('eventTitle').value = '';
      document.getElementById('eventStartTime').value = '';
      document.getElementById('eventEnd').value = info.dateStr;
      document.getElementById('eventDetails').value = '';
      document.getElementById('deleteBtn').classList.add('hidden');
      openModal('Add Event');
    },

    eventClick: function(info) {
      selectedDate = info.event.startStr.split('T')[0];
      editingEvent = info.event;

      document.getElementById('eventTitle').value = info.event.title;
      document.getElementById('eventEnd').value = info.event.extendedProps.end_date;
      document.getElementById('eventStartTime').value = info.event.extendedProps.start_time_raw;
      document.getElementById('eventDetails').value = info.event.extendedProps.event_details;

      document.getElementById('deleteBtn').classList.remove('hidden');
      openModal('Edit Event');
    },

    eventDidMount: function(info) {
      // Tooltip content
      const tooltip = document.createElement('div');
      tooltip.className = 'fc-tooltip';
      tooltip.innerHTML = `
        <strong>${info.event.title}</strong><br>
        🕒 ${info.event.extendedProps.start_time}<br>
        📌 ${info.event.extendedProps.event_details || 'No details'}
      `;
      document.body.appendChild(tooltip);

      // Show tooltip
      info.el.addEventListener('mouseenter', e => {
        tooltip.style.display = 'block';
        tooltip.style.left = e.pageX + 10 + 'px';
        tooltip.style.top = e.pageY + 10 + 'px';
      });

      // Move with mouse
      info.el.addEventListener('mousemove', e => {
        tooltip.style.left = e.pageX + 10 + 'px';
        tooltip.style.top = e.pageY + 10 + 'px';
      });

      // Hide on leave
      info.el.addEventListener('mouseleave', () => {
        tooltip.style.display = 'none';
      });
    },

    events: {
      url: 'api/fetch.php',
      failure: () => alert('Failed to load events!')
    }
  });

  calendar.render();

  document.getElementById('filterDate').addEventListener('change', e => {
    calendar.gotoDate(e.target.value);
  });

  document.getElementById('saveBtn').addEventListener('click', saveEvent);
  document.getElementById('deleteBtn').addEventListener('click', deleteEvent);
  document.getElementById('cancelBtn').addEventListener('click', closeModal);
});

function openModal(title) {
  document.getElementById('modalTitle').innerText = title;
  document.getElementById('eventModal').classList.remove('hidden');
}

function closeModal() {
  document.getElementById('eventModal').classList.add('hidden');
}

function saveEvent() {
  const title = document.getElementById('eventTitle').value;
  const end = document.getElementById('eventEnd').value;
  const start_time = document.getElementById('eventStartTime').value;
  const event_details = document.getElementById('eventDetails').value;

  if (!title || !selectedDate) {
    alert("Please fill in at least the title and date.");
    return;
  }

  const url = editingEvent ? 'api/update.php' : 'api/add.php';
  const data = new FormData();
  data.append('title', title);
  data.append('date', selectedDate);
  data.append('end_date', end);
  data.append('start_time', start_time);
  data.append('event_details', event_details);

  if (editingEvent) data.append('id', editingEvent.id);

  fetch(url, { method: 'POST', body: data })
    .then(() => {
      calendar.refetchEvents();
      closeModal();
    });
}

function deleteEvent() {
  const data = new FormData();
  data.append('id', editingEvent.id);
  fetch('api/delete.php', { method: 'POST', body: data })
    .then(() => {
      calendar.refetchEvents();
      closeModal();
    });
}

function goToToday() {
  calendar.today();
}
