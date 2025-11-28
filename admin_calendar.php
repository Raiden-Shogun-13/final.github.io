<?php
require __DIR__ . '/functions.php';
require __DIR__ . '/db.php';
if (!is_admin()) { header('Location: login.php'); exit; }
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Calendar</title>
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css" rel="stylesheet">
    <link rel="stylesheet" href="admin-style.css">
    <style>
        #calendar { max-width: 1100px; margin: 40px auto; }
    </style>
</head>
<body>
<?php include 'admin_header.php'; ?>
<main class="container">
    <h2>Appointments Calendar</h2>
    <div id="calendar"></div>
</main>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        editable: true,
        selectable: true,
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
        events: {
            url: 'api/appointments.php',
            method: 'GET'
        },
        eventDrop: function(info) {
            var id = info.event.id;
            var start = info.event.start;
            var datetime = new Date(start.getTime() - (start.getTimezoneOffset()*60000)).toISOString().slice(0,19).replace('T',' ');
            fetch('api/appointments.php?id=' + id, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ appointment_datetime: datetime })
            }).then(r=>r.json()).then(console.log).catch(function(){ info.revert(); });
        },
        eventClick: function(info) {
            var id = info.event.id;
            var newStatus = prompt('Set appointment status (confirmed/cancelled/pending):', info.event.extendedProps.status || 'confirmed');
            if (newStatus !== null) {
                fetch('api/appointments.php?id=' + id, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ status: newStatus })
                }).then(r=>r.json()).then(function(){ info.event.setProp('backgroundColor', newStatus === 'cancelled' ? '#999' : '#28a745'); });
            }
        }
    });
    calendar.render();
});
</script>
</body>
</html>
