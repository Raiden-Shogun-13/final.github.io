<?php
session_start();
require 'db.php';
require 'functions.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $contact = sanitize($_POST['contact'] ?? '');
    $room_number = sanitize($_POST['room_number'] ?? '');

    // Validation
    if (!$name || !$email || !$password || !$confirm_password) {
        $errors[] = 'Please fill in all required fields.';
    }

    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    }

    // Check duplicate email
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);

    if ($stmt->fetch()) {
        $errors[] = 'Email already registered.';
    }

    // If no errors → register user
    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            "INSERT INTO users (name, email, password, contact, room_number, role)
             VALUES (?, ?, ?, ?, ?, 'user')"
        );

        $stmt->execute([$name, $email, $hashed, $contact, $room_number]);

        flash('success', 'Registration successful! You may now log in.');
        header('Location: login.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Register</title>

<style>
    /* Import Google Fonts */
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap');

    /* Reset */
    *, *::before, *::after {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        padding: 0;
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(135deg, #2575fc, #6a11cb);
        min-height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 20px;
    }

    .register-container {
        background: rgba(255, 255, 255, 0.95);
        width: 100%;
        max-width: 450px;
        padding: 40px 30px;
        border-radius: 16px;
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.25);
        backdrop-filter: saturate(180%) blur(12px);
        animation: fadeInUp 0.8s ease forwards;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(25px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .title {
        font-size: 2rem;
        font-weight: 600;
        color: #333;
        text-align: center;
        margin-bottom: 30px;
        letter-spacing: 0.05em;
    }

    .error-box {
        background: #ffe0e0;
        border: 1.5px solid #ff4d4d;
        padding: 12px 16px;
        border-radius: 12px;
        color: #b20000;
        font-size: 0.9rem;
        margin-bottom: 20px;
        line-height: 1.4;
        user-select: none;
    }

    .input-group {
        margin-bottom: 20px;
    }

    label {
        display: block;
        font-weight: 600;
        font-size: 0.95rem;
        margin-bottom: 8px;
        color: #444;
    }

    input[type="text"],
    input[type="email"],
    input[type="password"] {
        width: 100%;
        padding: 14px 16px;
        border-radius: 12px;
        border: 1.8px solid #ccc;
        font-size: 1rem;
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
        outline-offset: 2px;
    }

    input[type="text"]:focus,
    input[type="email"]:focus,
    input[type="password"]:focus {
        border-color: #6a11cb;
        box-shadow: 0 0 8px rgba(106, 17, 203, 0.4);
        outline: none;
    }

    button.btn {
        width: 100%;
        padding: 14px 0;
        background: #6a11cb;
        border: none;
        border-radius: 12px;
        color: white;
        font-size: 1.1rem;
        font-weight: 700;
        cursor: pointer;
        transition: background-color 0.3s ease, transform 0.15s ease;
        box-shadow: 0 6px 15px rgba(106, 17, 203, 0.4);
    }

    button.btn:hover,
    button.btn:focus {
        background-color: #5012a3;
        transform: scale(1.04);
        outline: none;
    }

    .link {
        display: block;
        text-align: center;
        margin-top: 25px;
        font-size: 0.9rem;
        color: #2575fc;
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .link:hover,
    .link:focus {
        text-decoration: underline;
        color: #1749b5;
        outline: none;
    }

    /* Responsive */
    @media (max-width: 480px) {
        .register-container {
            padding: 30px 20px;
            width: 100%;
        }

        .title {
            font-size: 1.75rem;
            margin-bottom: 25px;
        }

        button.btn {
            font-size: 1rem;
            padding: 12px 0;
        }
    }
</style>
</head>
<body>

<div class="register-container" role="main" aria-labelledby="registerTitle">
    <h1 id="registerTitle" class="title">Create Account</h1>

    <?php if ($errors): ?>
        <div class="error-box" role="alert" aria-live="assertive">
            <?php foreach ($errors as $e): ?>
                &bull; <?= htmlspecialchars($e) ?><br>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" novalidate>
        <div class="input-group">
            <label for="name">Full Name</label>
            <input id="name" type="text" name="name" placeholder="Enter full name" required autocomplete="name" autofocus>
        </div>

        <div class="input-group">
            <label for="email">Email Address</label>
            <input id="email" type="email" name="email" placeholder="Enter your email" required autocomplete="email">
        </div>

        <div class="input-group">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" placeholder="Create a password" required autocomplete="new-password">
        </div>

        <div class="input-group">
            <label for="confirm_password">Confirm Password</label>
            <input id="confirm_password" type="password" name="confirm_password" placeholder="Confirm your password" required autocomplete="new-password">
        </div>

        <div class="input-group">
            <label for="contact">Contact Number</label>
            <input id="contact" type="text" name="contact" placeholder="Optional" autocomplete="tel">
        </div>

        <div class="input-group">
            <label for="room_number">Room Number (If checked in)</label>
            <input id="room_number" type="text" name="room_number" placeholder="Optional" autocomplete="off">
        </div>

        <button type="submit" class="btn">Register</button>

        <a href="login.php" class="link">Already have an account? Login</a>
    </form>
</div>

</body>
</html>
