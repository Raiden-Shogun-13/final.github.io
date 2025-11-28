<?php
require 'functions.php';
redirect_if_not_logged_in();

$raw = flash('booking_info');
$info = $raw ? json_decode($raw, true) : null;
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Booking Success</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">
<nav style="background:#1565c0;padding:1rem;color:#fff;">
  <div style="max-width:1100px;margin:0 auto;display:flex;justify-content:space-between;align-items:center;">
    <div class="logo">Hotel Appointments</div>
    <div><a href="dashboard.php" style="color:#fff;text-decoration:none;margin-right:1rem;">Dashboard</a></div>
  </div>
</nav>
<main style="max-width:900px;margin:3rem auto;padding:1rem;">
  <section class="booking-success">
    <h2>Booking Confirmed</h2>
    <?php if (!$info): ?>
      <p>No booking information available.</p>
    <?php else: ?>
      <p>Your appointment for <strong><?= htmlspecialchars($info['service']) ?></strong> has been created.</p>
      <p><strong>Date & Time:</strong> <?= htmlspecialchars(date('F j, Y, g:i A', strtotime($info['datetime']))) ?></p>
      <p><strong>Booking ID:</strong> <?= htmlspecialchars($info['id']) ?></p>
      <p><strong>Email Sent:</strong> <?= $info['email_sent'] ? 'Yes' : 'No' ?></p>
      <p style="margin-top:1.5rem;">You will receive a confirmation email shortly. You can manage or reschedule this appointment from your <a href="dashboard.php">dashboard</a>.</p>
    <?php endif; ?>
  </section>
</main>
</body>
</html>
