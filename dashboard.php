<?php
require 'db.php';
require 'functions.php';
require 'mail.php';
redirect_if_not_logged_in();

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';

// Load current user details
try {
  $stmtUser = $pdo->prepare("SELECT name, email, contact, room_number FROM users WHERE id = ?");
  $stmtUser->execute([$user_id]);
  $currentUser = $stmtUser->fetch();
} catch (PDOException $e) {
  $currentUser = ['name' => $user_name, 'email' => '', 'contact' => '', 'room_number' => ''];
}

$errors = [];
$success = '';

// Compute next upcoming appointment for this user
$next_appointment = null;
$now = new DateTime();


// Fetch services (exclude obvious test/demo placeholder entries)
try {
  $services = $pdo->query("SELECT * FROM services WHERE LOWER(name) NOT LIKE '%test%' AND LOWER(name) NOT LIKE '%demo%' AND LOWER(name) NOT LIKE '%sample%' ORDER BY name")->fetchAll();
} catch (PDOException $e) {
  $services = [];
  $errors[] = "Failed to load services: " . htmlspecialchars($e->getMessage());
}

// Handle booking POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book'])) {
    $service_id = (int)($_POST['service_id'] ?? 0);
    $datetime = trim($_POST['appointment_datetime'] ?? '');

    if (!$service_id || !$datetime) {
        $errors[] = "Please select a service and a valid date/time.";
    } else {
        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $datetime);
        if (!$dt) {
            $errors[] = "Invalid date/time format.";
        } elseif ($dt < new DateTime()) {
            $errors[] = "Appointment date/time must be in the future.";
        }
    }

    if (empty($errors)) {
      // fetch service name
      $stmtS = $pdo->prepare("SELECT name FROM services WHERE id = ?");
      $stmtS->execute([$service_id]);
      $svc = $stmtS->fetch();
      $serviceName = $svc['name'] ?? '';

      // guest info from form (prefill with current user if available)
      $guest_name = trim($_POST['guest_name'] ?? $currentUser['name'] ?? '');
      $guest_contact = trim($_POST['guest_contact'] ?? $currentUser['contact'] ?? '');
      $guest_room = trim($_POST['guest_room'] ?? $currentUser['room_number'] ?? '');

      try {
        $stmt = $pdo->prepare(
          "INSERT INTO appointments (user_id, service_id, appointment_datetime, status, guest_name, guest_contact, guest_room) VALUES (?, ?, ?, 'pending', ?, ?, ?)"
        );
        $stmt->execute([$user_id, $service_id, $datetime, $guest_name, $guest_contact, $guest_room]);

        // after insert redirect to success page with flash info
        $apptId = $pdo->lastInsertId();
        $emailSent = false;
        if (!empty($currentUser['email'])) {
          $emailSent = sendAppointmentConfirmationEmail($currentUser['email'], $guest_name ?: $currentUser['name'], $datetime, $serviceName, null, $guest_room ?: null, $guest_contact ?: null);
        }
        $info = ['id' => (int)$apptId, 'datetime' => $datetime, 'service' => $serviceName, 'email_sent' => (bool)$emailSent];
        flash('booking_info', json_encode($info));
        header('Location: booking_success.php');
        exit;
      } catch (PDOException $e) {
        $errors[] = "Failed to book appointment: " . htmlspecialchars($e->getMessage());
      }
    }
}

// Fetch appointments
try {
    $stmt = $pdo->prepare("SELECT a.*, s.name AS service_name FROM appointments a JOIN services s ON a.service_id = s.id WHERE a.user_id = ? ORDER BY a.appointment_datetime DESC");
    $stmt->execute([$user_id]);
    $appointments = $stmt->fetchAll();
} catch (PDOException $e) {
    $appointments = [];
    $errors[] = "Failed to load appointments: " . htmlspecialchars($e->getMessage());
}

// Find next upcoming appointment (closest future datetime)
try {
  $next_appointment = null;
  foreach ($appointments as $a) {
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $a['appointment_datetime']) ?: new DateTime($a['appointment_datetime']);
    if ($dt > new DateTime()) {
      if ($next_appointment === null || $dt < DateTime::createFromFormat('Y-m-d H:i:s', $next_appointment['appointment_datetime'])) {
        $next_appointment = $a;
      }
    }
  }
} catch (Exception $e) {
  // ignore
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Home - Welcome <?= htmlspecialchars($user_name) ?></title>
<link rel="stylesheet" href="dashboard.css" />
<!-- FullCalendar stylesheet placed in head to avoid layout flash -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css" rel="stylesheet" />
</head>
<body>
<header class="site-header" role="banner">
  <div class="nav-container">
    <div class="logo">Hotel Appointments</div>
    <button class="nav-toggle" aria-controls="nav-menu" aria-expanded="false" aria-label="Toggle menu">&#9776;</button>
    <div id="nav-menu" class="nav-menu">
      <div class="user-info" aria-live="polite">Hello, <?= htmlspecialchars($user_name) ?></div>
      <form method="post" action="logout.php" style="margin:0;">
        <button type="submit" class="logout-btn" aria-label="Logout">Logout</button>
      </form>
    </div>
  </div>
</header>

<section class="hero" role="banner" aria-label="Welcome Banner">
  <div class="hero-content">
    <h1>Welcome to Our Hotel Appointment System</h1>
    <p>Book your favorite services easily and enjoy a seamless experience.</p>
    <a href="#booking" class="cta-btn">Book Now</a>
  </div>
</section>

<!-- Services Showcase Section -->
<section class="services-showcase" style="background:#fff; padding:3rem 1rem;">
  <div class="page-container">
    <h2 class="section-title" style="text-align:center; margin-bottom:2.5rem;">Our Premium Services</h2>
    <div class="services-grid">
      <?php foreach ($services as $service):
        $images = [
          'spa' => 'spa.jpg',
          'massage' => 'massage.jpg',
          'restaurant table' => 'Restaurant.jpg',
          'gym' => 'gym.jpg',
          'conference room booking' => 'Workspace.jpg'
        ];
        $img = $images[strtolower($service['name'])] ?? 'images/default.svg';
      ?>
      <article class="service-card" role="article" aria-label="<?= htmlspecialchars($service['name']) ?> service costing <?= '$' . number_format($service['price'], 2) ?>">
        <div style="width:100%; height:160px; border-radius:12px 12px 0 0; overflow:hidden; background:#f0f0f0;">
          <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($service['name']) ?>" style="width:100%; height:100%; object-fit:cover;" loading="lazy">
        </div>
        <h3><?= htmlspecialchars($service['name']) ?></h3>
        <p><?= htmlspecialchars($service['description'] ?? 'Premium service offering.') ?></p>
        <div class="service-footer">
          <span class="service-price"><?= '$' . number_format($service['price'], 2) ?></span>
          <button type="button" class="service-link" data-service-id="<?= $service['id'] ?>" aria-label="Book <?= htmlspecialchars($service['name']) ?>">Book</button>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Calendar + Booking Form area -->
<main class="page-container">

  <!-- Calendar -->
  <section aria-labelledby="calendar-title" style="margin-top:2rem;">
    <h2 id="calendar-title" class="section-title">Your Calendar</h2>
    <div id="user-calendar" style="background:#fff; border-radius:12px; padding:0.75rem; box-shadow:var(--shadow);"></div>
  </section>

  <!-- Booking Form & Appointments Combined Section -->
  <section id="booking" aria-labelledby="booking-title" style="margin-top:1.75rem;">
  <h2 id="booking-title" class="section-title">Book a Service & Manage Appointments</h2>

  <div class="flex-two">
    <!-- Left: Booking Form -->
    <div>
      <!-- Next appointment card -->
      <?php if ($next_appointment):
        $na_dt = date('F j, Y, g:i A', strtotime($next_appointment['appointment_datetime']));
      ?>
      <div class="booking-panel" style="margin-bottom:1rem; border-left:4px solid var(--brand-1);">
        <h3 style="margin-top:0;">Next Appointment</h3>
        <p style="margin:0.25rem 0 0; font-weight:700; color:var(--brand-2);"><?= htmlspecialchars($next_appointment['service_name']) ?></p>
        <p style="margin:0.25rem 0; color:var(--muted);"><?= htmlspecialchars($na_dt) ?></p>
        <p style="margin:0.25rem 0 1rem;"><strong>Guest:</strong> <?= htmlspecialchars($next_appointment['guest_name'] ?? ($currentUser['name'] ?? '')) ?></p>
        <div style="display:flex;gap:0.5rem;">
          <a href="reschedule_appointment.php?id=<?= $next_appointment['id'] ?>" class="action-btn" style="background:#ffb300;color:#08254c;">Reschedule</a>
          <a href="cancel_appointment.php?id=<?= $next_appointment['id'] ?>" class="action-btn danger" onclick="return confirm('Cancel this appointment?')">Cancel</a>
        </div>
      </div>
      <?php endif; ?>
      <?php if (!empty($errors)): ?>
        <div class="message error" role="alert">
          <strong>Error:</strong>
          <ul style="padding-left: 1.3rem; margin: 0.5rem 0 0;">
            <?php foreach ($errors as $e): ?>
              <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="message success" role="alert"><strong>Success!</strong> <?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <form class="booking-panel" method="POST" action="" novalidate>
        <div class="form-group">
          <label for="service_id">Select Service</label>
          <select name="service_id" id="service_id" required aria-required="true">
            <option value="" disabled selected hidden>-- Select Service --</option>
            <?php foreach ($services as $s): ?>
              <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="appointment_datetime">Select Date & Time</label>
          <input
            type="datetime-local"
            id="appointment_datetime"
            name="appointment_datetime"
            required
            aria-required="true"
            min="<?= date('Y-m-d\TH:i') ?>"
            placeholder="Select Date & Time"
          >
        </div>

        <div class="form-group">
          <label for="guest_name">Your Name</label>
          <input id="guest_name" type="text" name="guest_name" value="<?= htmlspecialchars($currentUser['name'] ?? '') ?>" placeholder="Full name" required>
        </div>

        <div class="form-group">
          <label for="guest_contact">Contact Number</label>
          <input id="guest_contact" type="text" name="guest_contact" value="<?= htmlspecialchars($currentUser['contact'] ?? '') ?>" placeholder="Phone or mobile">
        </div>

        <div class="form-group">
          <label for="guest_room">Room Number (if checked in)</label>
          <input id="guest_room" type="text" name="guest_room" value="<?= htmlspecialchars($currentUser['room_number'] ?? '') ?>" placeholder="Room number (optional)">
        </div>

        <button type="submit" name="book" aria-label="Book appointment" class="btn">Book Appointment</button>
      </form>
    </div>

    <!-- Right: Quick Appointments List -->
    <aside>
      <div class="booking-panel" style="background: linear-gradient(180deg, #f8fbff, #ffffff);">
        <h3 style="margin-top:0; color: var(--brand-2);">Your Appointments</h3>
        <?php if (empty($appointments)): ?>
          <p style="text-align:center; font-weight:600; color:var(--muted); margin:1rem 0;">No appointments yet.</p>
        <?php else: ?>
          <div class="appointments-list" style="max-height: 400px; overflow-y: auto;">
            <table style="font-size:0.95rem;">
              <tbody>
                <?php foreach (array_slice($appointments, 0, 5) as $app): ?>
                <tr style="margin-bottom:0.5rem; padding: 0.6rem 0; border-bottom: 1px solid #e1e4e8;">
                  <td style="padding:0.5rem 0;">
                    <strong><?= htmlspecialchars($app['service_name']) ?></strong><br>
                    <small style="color:var(--muted);"><?= htmlspecialchars(date('M j, g:i A', strtotime($app['appointment_datetime']))) ?></small><br>
                    <small style="color:var(--brand-1);">Status: <?= ucfirst($app['status']) ?></small>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if (count($appointments) > 5): ?>
            <p style="text-align:center; font-size:0.9rem; margin-top:0.75rem;">
              <a href="#all-appointments" style="color: var(--brand-1);">View all (<?= count($appointments) ?>)</a>
            </p>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </aside>
  </div>
</section>

</main>

<!-- Full Appointments Table Section -->
<section id="all-appointments" style="background:#f8fbff; padding: 3rem 1rem; margin-top: 2rem;">
  <div class="page-container">
    <h2 class="section-title">All Your Appointments</h2>
    <div class="table-controls">
      <div>
        <label for="filter-status" class="sr-only">Filter by status</label>
        <select id="filter-status" aria-label="Filter appointments by status">
          <option value="">All statuses</option>
          <option value="pending">Pending</option>
          <option value="confirmed">Confirmed</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>
      <div style="margin-left:auto;">
        <label for="search-appointments" class="sr-only">Search appointments</label>
        <input id="search-appointments" type="search" placeholder="Search service or guest..." />
      </div>
    </div>
    
    <?php if (empty($appointments)): ?>
      <div class="empty-state">
        <p style="margin:0;">You have no appointments booked yet. <a href="#booking" style="color: var(--brand-1);">Book now</a></p>
      </div>
    <?php else: ?>
      <div class="appointments-list" style="overflow-x: auto;">
        <table role="grid" aria-describedby="appointments-info">
          <caption id="appointments-info" class="sr-only">Complete list of your booked appointments</caption>
          <thead>
            <tr>
              <th scope="col">Service</th>
              <th scope="col">Date & Time</th>
              <th scope="col">Guest Name</th>
              <th scope="col">Contact</th>
              <th scope="col">Room</th>
              <th scope="col">Status</th>
              <th scope="col">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($appointments as $app): ?>
            <?php $status_l = strtolower($app['status'] ?? ''); ?>
            <tr data-status="<?= htmlspecialchars($status_l) ?>">
              <td data-label="Service"><?= htmlspecialchars($app['service_name']) ?></td>
              <td data-label="Date & Time"><?= htmlspecialchars(date('M j, Y, g:i A', strtotime($app['appointment_datetime']))) ?></td>
              <td data-label="Guest Name"><?= htmlspecialchars($app['guest_name'] ?? '—') ?></td>
              <td data-label="Contact"><?= htmlspecialchars($app['guest_contact'] ?? '—') ?></td>
              <td data-label="Room"><?= htmlspecialchars($app['guest_room'] ?? '—') ?></td>
              <td data-label="Status">
                <?php $badgeClass = $status_l === 'pending' ? 'status-pending' : ($status_l === 'confirmed' ? 'status-confirmed' : ($status_l === 'cancelled' || $status_l === 'canceled' ? 'status-cancelled' : 'status-default')); ?>
                <span class="status-badge <?= $badgeClass ?>"><?= ucfirst($app['status']) ?></span>
              </td>
              <td data-label="Action" class="action-link">
                <?php if (in_array($status_l, ['pending', 'confirmed'])): ?>
                  <a class="action-btn reschedule" href="reschedule_appointment.php?id=<?= $app['id'] ?>" aria-label="Reschedule appointment for <?= htmlspecialchars($app['service_name']) ?>">Reschedule</a>
                  &nbsp;
                  <a class="action-btn cancel" href="cancel_appointment.php?id=<?= $app['id'] ?>" aria-label="Cancel appointment for <?= htmlspecialchars($app['service_name']) ?>">Cancel</a>
                <?php else: ?>
                  &mdash;
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- Footer -->
<footer class="site-footer">
  <div class="container" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
    <div style="flex:1;">
      <p style="margin:0; font-weight:700;">Hotel Appointment System</p>
      <p style="margin:0.3rem 0 0; opacity:0.85; font-size:0.9rem;">Book services, manage your stay with ease.</p>
    </div>
    <nav style="display:flex; gap:1.5rem; flex-wrap:wrap;">
      <a href="#booking" style="color:#cfe1ff; text-decoration:none; font-size:0.95rem;">Quick Book</a>
      <a href="#all-appointments" style="color:#cfe1ff; text-decoration:none; font-size:0.95rem;">My Appointments</a>
      <form method="post" action="logout.php" style="margin:0; display:inline;">
        <button type="submit" style="background:none; border:none; color:#cfe1ff; cursor:pointer; font-size:0.95rem; text-decoration:underline;">Logout</button>
      </form>
    </nav>
  </div>
  <p style="text-align:center; margin:1rem 0 0; padding-top:1rem; border-top:1px solid rgba(255,255,255,0.1); opacity:0.75; font-size:0.85rem;">© 2025 Hotel System. All rights reserved.</p>
</footer>
<!-- FullCalendar script -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.js"></script>

<script>
  // Mobile navigation toggle
  const navToggle = document.querySelector('.nav-toggle');
  const navMenu = document.getElementById('nav-menu');

  if (navToggle && navMenu) {
    navToggle.addEventListener('click', () => {
      const expanded = navToggle.getAttribute('aria-expanded') === 'true';
      navToggle.setAttribute('aria-expanded', !expanded);
      navMenu.classList.toggle('active');
    });

    // Close menu when clicking on a link
    navMenu.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        navToggle.setAttribute('aria-expanded', 'false');
        navMenu.classList.remove('active');
      });
    });
  }

  // Close menu when clicking outside
  document.addEventListener('click', (e) => {
    if (navToggle && navMenu && !e.target.closest('.nav-container')) {
      navToggle.setAttribute('aria-expanded', 'false');
      navMenu.classList.remove('active');
    }
  });

  // Auto-select service when clicking "Book" button
  document.querySelectorAll('.service-link').forEach(btn => {
    btn.addEventListener('click', function(e) {
      const serviceId = this.getAttribute('data-service-id');
      if (serviceId) {
        document.getElementById('service_id').value = serviceId;
        // Scroll to booking section smoothly
        setTimeout(() => {
          document.querySelector('#booking').scrollIntoView({behavior:'smooth'});
        }, 100);
      }
    });
  });

  // Appointment table filtering and search
  document.addEventListener('DOMContentLoaded', function() {
    const filter = document.getElementById('filter-status');
    const search = document.getElementById('search-appointments');
    const tableRows = document.querySelectorAll('#all-appointments .appointments-list tbody tr');

    function applyFilters() {
      const status = (filter && filter.value) ? filter.value.toLowerCase() : '';
      const q = (search && search.value) ? search.value.toLowerCase().trim() : '';
      tableRows.forEach(r => {
        const rStatus = (r.getAttribute('data-status') || '').toLowerCase();
        const text = r.innerText.toLowerCase();
        const statusMatch = !status || rStatus === status;
        const queryMatch = !q || text.indexOf(q) !== -1;
        r.style.display = (statusMatch && queryMatch) ? '' : 'none';
      });
    }

    if (filter) filter.addEventListener('change', applyFilters);
    if (search) search.addEventListener('input', debounce(applyFilters, 160));

    function debounce(fn, ms) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; }

    // Confirmation for cancel buttons (delegated)
    document.querySelector('#all-appointments').addEventListener('click', function(e) {
      const el = e.target.closest('.action-btn.cancel');
      if (!el) return;
      e.preventDefault();
      if (confirm('Are you sure you want to cancel this appointment?')) {
        // follow the link
        window.location.href = el.getAttribute('href');
      }
    });
  });

  // Prepare FullCalendar events from PHP appointments
  const fcEvents = [
    <?php foreach ($appointments as $a):
        $start = date('c', strtotime($a['appointment_datetime']));
        $title = addslashes($a['service_name']);
        $id = (int)$a['id'];
        $status = htmlspecialchars($a['status']);
        echo "{ id: {$id}, title: '{$title}', start: '{$start}', extendedProps: { status: '{$status}' } },\n";
    endforeach; ?>
  ];

  document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('user-calendar');
    if (!calendarEl) return;
    const calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: 'dayGridMonth',
      height: 600,
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay'
      },
      events: fcEvents,
      eventClick: function(info) {
        // on event click show options
        const id = info.event.id;
        const choice = confirm('Open appointment actions? OK = Reschedule, Cancel = Cancel appointment');
        if (choice) {
          window.location.href = 'reschedule_appointment.php?id=' + id;
        } else {
          if (confirm('Are you sure you want to cancel this appointment?')) {
            window.location.href = 'cancel_appointment.php?id=' + id;
          }
        }
      }
    });
    calendar.render();
  });
</script>
</body>
</html>
