<?php
// Simple CLI test to verify booking insertion works
require __DIR__ . '/../db.php';

echo "Starting booking test...\n";

try {
    // Ensure a test user exists
    $email = 'testuser@example.com';
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        $pwd = password_hash('password123', PASSWORD_DEFAULT);
        $ins = $pdo->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?, 'user')");
        $ins->execute(['Test User', $email, $pwd]);
        $userId = $pdo->lastInsertId();
        echo "Created test user id={$userId}\n";
    } else {
        $userId = $user['id'];
        echo "Test user exists id={$userId}\n";
    }

    // Ensure a test service exists
    $svcName = 'Test Service';
    $stmt = $pdo->prepare("SELECT id FROM services WHERE name = ?");
    $stmt->execute([$svcName]);
    $svc = $stmt->fetch();
    if (!$svc) {
        $ins = $pdo->prepare("INSERT INTO services (name, description, price) VALUES (?, ?, ?)");
        $ins->execute([$svcName, 'Test service description', 10.00]);
        $svcId = $pdo->lastInsertId();
        echo "Created test service id={$svcId}\n";
    } else {
        $svcId = $svc['id'];
        echo "Test service exists id={$svcId}\n";
    }

    // Attempt to insert appointment
    $future = (new DateTime('+1 day'))->format('Y-m-d H:i:s');
    $guest_name = 'Test Guest';
    $guest_contact = '+1234567890';
    $guest_room = '101';

    $stmt = $pdo->prepare("INSERT INTO appointments (user_id, service_id, appointment_datetime, status, guest_name, guest_contact, guest_room) VALUES (?, ?, ?, 'pending', ?, ?, ?)");
    $stmt->execute([$userId, $svcId, $future, $guest_name, $guest_contact, $guest_room]);
    $apptId = $pdo->lastInsertId();
    echo "Inserted appointment id={$apptId} for user {$userId} service {$svcId} at {$future}\n";

    echo "Booking test completed successfully.\n";
} catch (PDOException $e) {
    echo "PDO ERROR: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
