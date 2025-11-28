<?php
require 'db.php';
require 'functions.php';
redirect_if_not_logged_in();

$user_id = $_SESSION['user_id'];
$appointment_id = (int)($_GET['id'] ?? 0);

if ($appointment_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ? AND user_id = ?");
    $stmt->execute([$appointment_id, $user_id]);
    $appointment = $stmt->fetch();

    if ($appointment) {
        if (in_array($appointment['status'], ['pending', 'confirmed'])) {
            $update = $pdo->prepare("UPDATE appointments SET status = 'canceled' WHERE id = ?");
            $update->execute([$appointment_id]);
            flash('success', 'Appointment canceled successfully.');
        } else {
            flash('error', 'This appointment cannot be canceled.');
        }
    } else {
        flash('error', 'Appointment not found or access denied.');
    }
} else {
    flash('error', 'Invalid appointment ID.');
}

header('Location: dashboard.php');
exit;
