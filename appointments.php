<?php
// API endpoint for appointments
// This file lives at the project root; require files from the same folder.
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';
require __DIR__ . '/mail.php';

header('Content-Type: application/json; charset=utf-8');

// simple auth helpers
function require_admin() {
    if (!is_admin()) {
        http_response_code(403);
        echo json_encode(['error' => 'admin_required']);
        exit;
    }
}

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// read input for POST/PUT
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

try {
    if ($method === 'GET') {
        // return events; admin gets all, user gets own
        if (is_admin()) {
            $stmt = $pdo->query("SELECT a.*, s.name AS service_name, u.name AS user_name FROM appointments a JOIN services s ON a.service_id = s.id JOIN users u ON a.user_id = u.id ORDER BY a.appointment_datetime");
            $rows = $stmt->fetchAll();
        } else {
            if (!is_logged_in()) { http_response_code(401); echo json_encode([]); exit; }
            $uid = $_SESSION['user_id'];
            $stmt = $pdo->prepare("SELECT a.*, s.name AS service_name FROM appointments a JOIN services s ON a.service_id = s.id WHERE a.user_id = ? ORDER BY a.appointment_datetime");
            $stmt->execute([$uid]);
            $rows = $stmt->fetchAll();
        }

        $events = [];
        foreach ($rows as $r) {
            $events[] = [
                'id' => (int)$r['id'],
                'title' => $r['service_name'] . ' - ' . ($r['guest_name'] ?? $r['user_name'] ?? ''),
                'start' => date('c', strtotime($r['appointment_datetime'])),
                'status' => $r['status'],
                'extendedProps' => [
                    'service_id' => (int)$r['service_id'],
                    'user_id' => (int)$r['user_id'],
                    'guest_contact' => $r['guest_contact'] ?? null,
                    'staff_id' => isset($r['staff_id']) ? (int)$r['staff_id'] : null,
                ]
            ];
        }
        echo json_encode($events);
        exit;
    }

    if ($method === 'POST') {
        // create appointment (user must be logged in)
        if (!is_logged_in()) { http_response_code(401); echo json_encode(['error' => 'login_required']); exit; }
        $user_id = $_SESSION['user_id'];
        $service_id = (int)($input['service_id'] ?? 0);
        $datetime = trim($input['appointment_datetime'] ?? '');
        $guest_name = trim($input['guest_name'] ?? '');
        $guest_contact = trim($input['guest_contact'] ?? '');
        $guest_room = trim($input['guest_room'] ?? '');

        if (!$service_id || !$datetime) { http_response_code(400); echo json_encode(['error'=>'invalid_input']); exit; }
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $datetime) ?: DateTime::createFromFormat('Y-m-d\TH:i', $datetime);
        if (!$dt) { http_response_code(400); echo json_encode(['error'=>'invalid_datetime']); exit; }
        if ($dt < new DateTime()) { http_response_code(400); echo json_encode(['error'=>'past_datetime']); exit; }

        // availability: ensure no other appointment exists for same service at same datetime
        $check = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE service_id = ? AND appointment_datetime = ?");
        $check->execute([$service_id, $dt->format('Y-m-d H:i:s')]);
        if ($check->fetchColumn() > 0) { http_response_code(409); echo json_encode(['error'=>'conflict']); exit; }

        $ins = $pdo->prepare("INSERT INTO appointments (user_id, service_id, appointment_datetime, status, guest_name, guest_contact, guest_room) VALUES (?, ?, ?, 'pending', ?, ?, ?)");
        $ins->execute([$user_id, $service_id, $dt->format('Y-m-d H:i:s'), $guest_name, $guest_contact, $guest_room]);
        $id = $pdo->lastInsertId();

        // send confirmation
        $stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ?"); $stmt->execute([$user_id]); $u = $stmt->fetch();
        if ($u && !empty($u['email'])) {
            sendAppointmentConfirmationEmail($u['email'], $guest_name ?: $u['name'], $dt->format('Y-m-d H:i:s'), 'Service');
        }

        http_response_code(201);
        echo json_encode(['id' => (int)$id]);
        exit;
    }

    if ($method === 'PUT') {
        // update appointment (admin or owner)
        parse_str(file_get_contents('php://input'), $_PUT);
        $payload = $input;
        if (!$id) { http_response_code(400); echo json_encode(['error'=>'missing_id']); exit; }
        // fetch appointment
        $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ?"); $stmt->execute([$id]); $appt = $stmt->fetch();
        if (!$appt) { http_response_code(404); echo json_encode(['error'=>'not_found']); exit; }

        // permission: admin or owner
        if (!is_admin() && (!is_logged_in() || $_SESSION['user_id'] != $appt['user_id'])) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }

        $fields = [];
        $params = [];
        if (isset($payload['appointment_datetime'])) {
            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $payload['appointment_datetime']) ?: DateTime::createFromFormat('Y-m-d\TH:i', $payload['appointment_datetime']);
            if (!$dt) { http_response_code(400); echo json_encode(['error'=>'invalid_datetime']); exit; }
            $fields[] = 'appointment_datetime = ?'; $params[] = $dt->format('Y-m-d H:i:s');
        }
        if (isset($payload['status'])) { $fields[] = 'status = ?'; $params[] = $payload['status']; }
        if (isset($payload['staff_id'])) { $fields[] = 'staff_id = ?'; $params[] = ($payload['staff_id'] === '' ? null : (int)$payload['staff_id']); }

        if (empty($fields)) { echo json_encode(['ok'=>true]); exit; }
        $params[] = $id;
        $sql = "UPDATE appointments SET " . implode(', ', $fields) . " WHERE id = ?";
        $pdo->prepare($sql)->execute($params);

        // if status changed, notify guest
        if (isset($payload['status'])) {
            $stmt = $pdo->prepare("SELECT u.email, u.name, s.name AS service_name, a.appointment_datetime FROM appointments a JOIN users u ON a.user_id=u.id JOIN services s ON a.service_id=s.id WHERE a.id = ?");
            $stmt->execute([$id]); $info = $stmt->fetch();
            if ($info && !empty($info['email'])) {
                sendAppointmentStatusEmail($info['email'], $info['name'], $info['service_name'], $info['appointment_datetime'], $payload['status']);
            }
        }

        echo json_encode(['ok'=>true]); exit;
    }

    if ($method === 'DELETE') {
        // delete appointment (owner or admin)
        if (!$id) { http_response_code(400); echo json_encode(['error'=>'missing_id']); exit; }
        $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ?"); $stmt->execute([$id]); $appt = $stmt->fetch();
        if (!$appt) { http_response_code(404); echo json_encode(['error'=>'not_found']); exit; }
        if (!is_admin() && (!is_logged_in() || $_SESSION['user_id'] != $appt['user_id'])) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }

        $pdo->prepare("DELETE FROM appointments WHERE id = ?")->execute([$id]);
        echo json_encode(['ok'=>true]); exit;
    }

    http_response_code(405);
    echo json_encode(['error'=>'method_not_allowed']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
