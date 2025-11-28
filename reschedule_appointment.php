<?php
require 'db.php';
require 'functions.php';
redirect_if_not_logged_in();

$user_id = $_SESSION['user_id'];
$appointment_id = (int)($_GET['id'] ?? 0);
$errors = [];

if ($appointment_id <= 0) {
    flash('error', 'Invalid appointment.');
    header('Location: dashboard.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ? AND user_id = ?");
$stmt->execute([$appointment_id, $user_id]);
$appointment = $stmt->fetch();

if (!$appointment) {
    flash('error', 'Appointment not found or access denied.');
    header('Location: dashboard.php');
    exit;
}

if (!in_array($appointment['status'], ['pending','confirmed'])) {
    flash('error', 'Only pending or confirmed appointments can be rescheduled.');
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datetime = trim($_POST['appointment_datetime'] ?? '');
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $datetime);
    if (!$dt) {
        $errors[] = 'Invalid date/time format.';
    } elseif ($dt < new DateTime()) {
        $errors[] = 'Appointment date/time must be in the future.';
    }

    if (empty($errors)) {
        $update = $pdo->prepare("UPDATE appointments SET appointment_datetime = ? WHERE id = ?");
        $update->execute([$datetime, $appointment_id]);

        // notify guest
        $stmtU = $pdo->prepare("SELECT u.email, u.name, s.name AS service_name FROM appointments a JOIN users u ON a.user_id = u.id JOIN services s ON a.service_id = s.id WHERE a.id = ?");
        $stmtU->execute([$appointment_id]);
        $info = $stmtU->fetch();
        if ($info && !empty($info['email'])) {
            sendAppointmentConfirmationEmail($info['email'], $info['name'], $datetime, $info['service_name']);
        }

        flash('success', 'Appointment rescheduled successfully. A confirmation was sent.');
        header('Location: dashboard.php');
        exit;
    }
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Reschedule Appointment</title>
  <link rel="stylesheet" href="style.css">
  <style> .container{max-width:520px;margin:56px auto} </style>
</head>
<body class="auth-page">
<div class="container"><div class="card">
  <h1 class="title">Reschedule Appointment</h1>
  <?php if ($errors): ?><div class="error-box"><?php foreach($errors as $e) echo htmlspecialchars($e)."<br>"; ?></div><?php endif; ?>
  <form method="post">
    <div class="form-group">
      <label for="appointment_datetime">New Date & Time</label>
      <input type="datetime-local" id="appointment_datetime" name="appointment_datetime" required value="<?= date('Y-m-d\TH:i', strtotime($appointment['appointment_datetime'])) ?>">
    </div>
    <button class="btn" type="submit">Save</button>
    <a class="small-link" href="dashboard.php">Back</a>
  </form>
  </div></div>
</body>
</html>
