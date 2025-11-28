<?php
require 'db.php';
require 'functions.php';
require 'mail.php';

redirect_if_not_logged_in();
redirect_if_not_admin();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['update_appointment'])) {
    $appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
    $status = $_POST['status'] ?? '';
    $staff_id = isset($_POST['staff_id']) && $_POST['staff_id'] !== '' ? (int)$_POST['staff_id'] : null;

    $valid_statuses = ['pending', 'confirmed', 'completed', 'canceled'];

    if ($appointment_id && in_array($status, $valid_statuses, true)) {
      try {
        if ($staff_id !== null) {
          $stmt = $pdo->prepare("UPDATE appointments SET status = ?, staff_id = ? WHERE id = ?");
          $stmt->execute([$status, $staff_id, $appointment_id]);
        } else {
          $stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ?");
          $stmt->execute([$status, $appointment_id]);
        }

                $stmtInfo = $pdo->prepare("
                    SELECT u.email, u.name AS guest_name, s.name AS service_name, a.appointment_datetime
                    FROM appointments a
                    JOIN users u ON a.user_id = u.id
                    JOIN services s ON a.service_id = s.id
                    WHERE a.id = ?
                ");
                $stmtInfo->execute([$appointment_id]);
                $apptInfo = $stmtInfo->fetch();

                if ($apptInfo && !empty($apptInfo['email'])) {
                  $emailSent = sendAppointmentStatusEmail(
                    $apptInfo['email'],
                    $apptInfo['guest_name'],
                    $apptInfo['service_name'],
                    $apptInfo['appointment_datetime'],
                    $status
                  );
                    if ($emailSent) {
                        $success = "Appointment updated and notification sent to guest.";
                    } else {
                        $success = "Appointment updated, but failed to send notification email.";
                    }
                } else {
                    $success = "Appointment updated, but guest email not found.";
                }
            } catch (PDOException $e) {
                $errors[] = "Failed to update appointment: " . $e->getMessage();
            }
        } else {
            $errors[] = "Invalid appointment update data.";
        }
    }

    if (isset($_POST['save_service'])) {
        $service_id = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = filter_var($_POST['price'] ?? 0, FILTER_VALIDATE_FLOAT);

        if ($name !== '' && $price !== false && $price >= 0) {
            try {
                if ($service_id && $service_id > 0) {
                    $stmt = $pdo->prepare("UPDATE services SET name = ?, description = ?, price = ? WHERE id = ?");
                    $stmt->execute([$name, $description, $price, $service_id]);
                    $success = "Service updated successfully.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO services (name, description, price) VALUES (?, ?, ?)");
                    $stmt->execute([$name, $description, $price]);
                    $success = "New service added.";
                }
            } catch (PDOException $e) {
                $errors[] = "Failed to save service: " . $e->getMessage();
            }
        } else {
            $errors[] = "Please provide a valid service name and price.";
        }
    }

    if (isset($_POST['delete_service'])) {
        $service_id = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
        if ($service_id && $service_id > 0) {
            try {
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE service_id = ?");
                $stmtCheck->execute([$service_id]);
                $count = $stmtCheck->fetchColumn();
                if ($count > 0) {
                    $errors[] = "Cannot delete service because it has existing appointments.";
                } else {
                    $stmtDel = $pdo->prepare("DELETE FROM services WHERE id = ?");
                    $stmtDel->execute([$service_id]);
                    $success = "Service deleted successfully.";
                }
            } catch (PDOException $e) {
                $errors[] = "Failed to delete service: " . $e->getMessage();
            }
        } else {
            $errors[] = "Invalid service ID for deletion.";
        }
    }
}

// Fetch appointments
try {
    $appointments = $pdo->query(
        "SELECT a.*, s.name AS service_name, u.name AS user_name, u.email
         FROM appointments a
         JOIN services s ON a.service_id = s.id
         JOIN users u ON a.user_id = u.id
         ORDER BY a.appointment_datetime DESC"
    )->fetchAll();
} catch (PDOException $e) {
    $errors[] = "Failed to fetch appointments: " . $e->getMessage();
    $appointments = [];
}

// Fetch services list
try {
    $services = $pdo->query("SELECT * FROM services ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    $errors[] = "Failed to fetch services: " . $e->getMessage();
    $services = [];
}

// Fetch staff (users with role staff or admin)
try {
  $staff = $pdo->prepare("SELECT id, name, email, role FROM users WHERE role IN ('staff','admin') ORDER BY name");
  $staff->execute();
  $staffList = $staff->fetchAll();
} catch (PDOException $e) {
  $staffList = [];
}
?>
<!DOCTYPE html>
<html lang="en" >
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Hotel Admin Dashboard</title>
  <link rel="stylesheet" href="admin-style.css">
</head>
<body>
<header>
  <div class="brand">
    <div class="logo-mark" aria-hidden="true">
      <!-- simple SVG mark -->
      <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 2L15 8H9L12 2zM4 10h16v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V10z"/></svg>
    </div>
    <div>
      <div class="brand-text">Hotel Admin</div>
      <div style="font-size:0.82rem;color:var(--text-muted);">Manage bookings & services</div>
    </div>
  </div>
  <div class="header-meta">
    <div class="user-pill">Hello, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></div>
    <div class="top-actions">
      <a href="admin_calendar.php" class="button" aria-label="Open calendar">
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M7 10h5v5H7z" fill="currentColor"/></svg>
        Calendar
      </a>
      <a href="logout.php" class="logout-btn" aria-label="Logout">Logout</a>
    </div>
  </div>
</header>
<main>
  <?php if ($errors): ?>
    <div class="alert error" role="alert" aria-live="assertive">
      <ul>
        <?php foreach ($errors as $error): ?>
          <li><?= htmlspecialchars($error) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert success" role="alert" aria-live="polite">
      <?= htmlspecialchars($success) ?>
    </div>
  <?php endif; ?>

  <div class="tabs" role="tablist" aria-label="Admin dashboard tabs">
    <button class="tab active" role="tab" aria-selected="true" aria-controls="appointments" id="tab-appointments" data-tab="appointments">Appointments</button>
    <button class="tab" role="tab" aria-selected="false" aria-controls="services" id="tab-services" data-tab="services">Services</button>
  </div>
  <div class="admin-grid">
    <section id="appointments" class="tab-content panel" role="tabpanel" aria-labelledby="tab-appointments" style="display:block;">
      <div class="table-wrapper">
        <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Guest</th>
            <th>Email</th>
            <th>Service</th>
            <th>Date &amp; Time</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($appointments) === 0): ?>
            <tr><td colspan="7" class="center">No appointments found.</td></tr>
          <?php else: ?>
            <?php foreach ($appointments as $appt): ?>
              <tr>
                <td><?= (int)$appt['id'] ?></td>
                <td><?= htmlspecialchars($appt['user_name']) ?></td>
                <td><a href="mailto:<?= htmlspecialchars($appt['email']) ?>"><?= htmlspecialchars($appt['email']) ?></a></td>
                <td><?= htmlspecialchars($appt['service_name']) ?></td>
                <td><?= htmlspecialchars($appt['appointment_datetime']) ?></td>
                <td class="status-<?= htmlspecialchars($appt['status']) ?>"><?= ucfirst(htmlspecialchars($appt['status'])) ?></td>
                <td>
                  <form method="post" class="inline" aria-label="Update appointment <?= (int)$appt['id'] ?>">
                    <input type="hidden" name="appointment_id" value="<?= (int)$appt['id'] ?>" />
                    <select name="status" aria-label="Status for appointment <?= (int)$appt['id'] ?>" required>
                      <option value="pending" <?= $appt['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                      <option value="confirmed" <?= $appt['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                      <option value="completed" <?= $appt['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                      <option value="canceled" <?= $appt['status'] === 'canceled' ? 'selected' : '' ?>>Canceled</option>
                    </select>
                    <select name="staff_id" aria-label="Assign staff for appointment <?= (int)$appt['id'] ?>">
                      <option value="">-- Assign Staff --</option>
                      <?php foreach ($staffList as $st): ?>
                        <option value="<?= (int)$st['id'] ?>" <?= (isset($appt['staff_id']) && $appt['staff_id'] == $st['id']) ? 'selected' : '' ?>><?= htmlspecialchars($st['name']) ?> (<?= htmlspecialchars($st['role']) ?>)</option>
                      <?php endforeach; ?>
                    </select>
                    <button type="submit" name="update_appointment" aria-label="Update appointment <?= (int)$appt['id'] ?>">Save</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    </section>

    <aside class="panel" style="min-width:260px;">
      <h3>Quick Actions</h3>
      <p style="margin:0.4rem 0 1rem;color:var(--color-text-light);">Use these to manage services and navigate.</p>
      <p><a href="admin_calendar.php" class="button">Open Calendar</a></p>
      <hr style="margin:1rem 0;border:none;border-top:1px solid #eee;">
      <h3>Services</h3>
      <p style="color:var(--color-text-light);">Add or edit services below. Click a service's Edit button to populate the form.</p>
      <div style="margin-top:1rem;">
        <button type="button" onclick="document.querySelector('button[data-tab=services]').click();" class="button">Edit Services</button>
      </div>
    </aside>
  </div>

  <section id="services" class="tab-content" role="tabpanel" aria-labelledby="tab-services" style="display:none;">
    <h2 id="serviceFormTitle">Add New Service</h2>
    <form method="post" aria-label="Add or edit service">
      <input type="hidden" name="service_id" id="service_id" value="" />
      <div>
        <label for="name">Name*</label>
        <input type="text" name="name" id="name" required autocomplete="off" />
      </div>
      <div>
        <label for="description">Description</label>
        <textarea name="description" id="description" rows="3" placeholder="Optional description"></textarea>
      </div>
      <div>
        <label for="service_price">Price</label>
        <input type="number" step="0.01" min="0" name="price" id="service_price" required>
      </div>
      <div style="margin-top:0.75rem;">
        <button type="submit" name="save_service" aria-label="Save service">Save Service</button>
        <button type="button" id="cancelEdit" onclick="resetServiceForm()" style="display:none; margin-left:0.5rem;">Cancel</button>
      </div>
    </form>

    <div class="table-wrapper" style="margin-top:1.5rem;">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Description</th>
            <th>Price (PHP)</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($services) === 0): ?>
            <tr><td colspan="5" class="center">No services found.</td></tr>
          <?php else: ?>
            <?php foreach ($services as $service): ?>
              <tr>
                <td><?= (int)$service['id'] ?></td>
                <td><?= htmlspecialchars($service['name']) ?></td>
                <td><?= nl2br(htmlspecialchars($service['description'])) ?></td>
                <td><?= number_format($service['price'], 2) ?></td>
                <td>
                  <button type="button" onclick="editService(<?= (int)$service['id'] ?>)" aria-label="Edit service <?= htmlspecialchars($service['name']) ?>">Edit</button>
                  <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this service?');" aria-label="Delete service <?= htmlspecialchars($service['name']) ?>">
                    <input type="hidden" name="service_id" value="<?= (int)$service['id'] ?>" />
                    <button type="submit" name="delete_service" aria-label="Delete service <?= htmlspecialchars($service['name']) ?>">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
<script>
  // Tab switching logic
  document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
      const target = tab.getAttribute('data-tab');
      document.querySelectorAll('.tab').forEach(t => {
        const isActive = t === tab;
        t.classList.toggle('active', isActive);
        t.setAttribute('aria-selected', isActive ? 'true' : 'false');
      });
      document.querySelectorAll('.tab-content').forEach(tc => {
        tc.style.display = (tc.id === target) ? 'block' : 'none';
      });
    });
  });

  // Service edit form handling
  const services = <?= json_encode($services) ?>;
  function editService(id) {
    const service = services.find(s => s.id == id);
    if (!service) return;

    document.getElementById('serviceFormTitle').textContent = 'Edit Service: ' + service.name;
    document.getElementById('service_id').value = service.id;
    document.getElementById('name').value = service.name;
    document.getElementById('description').value = service.description;
    document.getElementById('service_price').value = service.price;
    document.getElementById('cancelEdit').style.display = 'inline-block';

    // Switch to services tab
    document.querySelector('button[data-tab="services"]').click();
  }

  function resetServiceForm() {
    document.getElementById('serviceFormTitle').textContent = 'Add New Service';
    document.getElementById('service_id').value = '';
    document.getElementById('name').value = '';
    document.getElementById('description').value = '';
    document.getElementById('service_price').value = '';
    document.getElementById('cancelEdit').style.display = 'none';
  }
</script>
</body>
</html>
