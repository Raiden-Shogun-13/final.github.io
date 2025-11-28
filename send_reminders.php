<?php
// Run this script via cron (e.g., every 15 minutes) to send appointment reminders.
require 'db.php';
require 'functions.php';
require 'mail.php';

// Time windows for reminders (in seconds). Example: 24h and 1h before.
$reminder_windows = [86400, 3600];

foreach ($reminder_windows as $seconds_before) {
    $target_from = date('Y-m-d H:i:s', time() + $seconds_before - 900); // 15min window start
    $target_to = date('Y-m-d H:i:s', time() + $seconds_before + 900);   // 15min window end

    // find appointments that are confirmed and in the window, and not yet reminded for this window
    $stmt = $pdo->prepare(
        "SELECT a.* , u.email, u.name AS user_name, s.name AS service_name
         FROM appointments a
         JOIN users u ON a.user_id = u.id
         JOIN services s ON a.service_id = s.id
         WHERE a.status = 'confirmed' AND a.appointment_datetime BETWEEN ? AND ?"
    );
    $stmt->execute([$target_from, $target_to]);
    $rows = $stmt->fetchAll();

    foreach ($rows as $appt) {
        // Check if a reminder was already sent for this appointment within this exact window
        $check = $pdo->prepare("SELECT COUNT(*) FROM appointment_reminders WHERE appointment_id = ? AND seconds_before = ?");
        $check->execute([$appt['id'], $seconds_before]);
        if ($check->fetchColumn() > 0) continue;

        // send email reminder
        $sent = sendAppointmentConfirmationEmail($appt['email'], $appt['user_name'], $appt['appointment_datetime'], $appt['service_name']);
        if ($sent) {
            $ins = $pdo->prepare("INSERT INTO appointment_reminders (appointment_id, seconds_before, sent_at) VALUES (?, ?, NOW())");
            $ins->execute([$appt['id'], $seconds_before]);
        }
    }
}

echo "Reminders run completed.\n";
