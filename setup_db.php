<?php
/**
 * Run this script once (from browser or CLI) to create missing tables required by the appointment system.
 * Example (CLI): php setup_db.php
 */
require 'db.php';

try {
    // appointments table
    $pdo->exec("CREATE TABLE IF NOT EXISTS appointments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        service_id INT NOT NULL,
        appointment_datetime DATETIME NOT NULL,
        status VARCHAR(32) NOT NULL DEFAULT 'pending',
        staff_id INT DEFAULT NULL,
        guest_name VARCHAR(255) DEFAULT NULL,
        guest_contact VARCHAR(100) DEFAULT NULL,
        guest_room VARCHAR(50) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id),
        INDEX (service_id),
        INDEX (appointment_datetime)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // appointment reminders
    $pdo->exec("CREATE TABLE IF NOT EXISTS appointment_reminders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        appointment_id INT NOT NULL,
        seconds_before INT NOT NULL,
        sent_at DATETIME NOT NULL,
        INDEX (appointment_id),
        UNIQUE KEY unique_appt_reminder (appointment_id, seconds_before)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    echo "Tables created or already exist.\n";
} catch (PDOException $e) {
    echo 'DB error: ' . $e->getMessage();
}
