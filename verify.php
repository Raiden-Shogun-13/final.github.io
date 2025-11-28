<?php
session_start();
require 'functions.php';
require 'mail.php';

$errors = [];
$messages = [];

if (!isset($_SESSION['pending_user'])) {
    header('Location: login.php');
    exit;
}

$pending = $_SESSION['pending_user'];

// Resend code if requested
if (isset($_GET['resend'])) {
    $code = generate_verification_code(6);
    $_SESSION['verification'] = [
        'code' => $code,
        'expires' => time() + 300
    ];
    if (sendVerificationEmail($pending['email'], $pending['name'], $code)) {
        $messages[] = 'A new verification code has been sent to your email.';
    } else {
        $errors[] = 'Failed to resend verification code. Please try again later.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputCode = trim($_POST['code'] ?? '');
    if (empty($inputCode)) {
        $errors[] = 'Please enter the verification code.';
    } else {
        if (!isset($_SESSION['verification'])) {
            $errors[] = 'No verification code found. Please request a new code.';
        } else {
            $stored = $_SESSION['verification'];
            if (time() > $stored['expires']) {
                $errors[] = 'Verification code has expired. Please resend a new code.';
            } elseif (hash_equals((string)$stored['code'], (string)$inputCode)) {
                // success — complete login
                $_SESSION['user_id'] = $pending['id'];
                $_SESSION['role'] = $pending['role'];
                $_SESSION['user_name'] = $pending['name'];

                // clear temporary session data
                unset($_SESSION['pending_user']);
                unset($_SESSION['verification']);

                if ($_SESSION['role'] === 'admin') {
                    header('Location: admin_dashboard.php');
                } else {
                    header('Location: dashboard.php');
                }
                exit;
            } else {
                $errors[] = 'Invalid verification code.';
            }
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">
<div class="container" role="main">
    <div class="card" role="region" aria-labelledby="verifyTitle">
        <h1 id="verifyTitle" class="title">Enter Verification Code</h1>
        <p class="note">A 6-digit code was sent to <strong><?php echo htmlspecialchars($pending['email']); ?></strong>. It expires in 5 minutes.</p>

        <?php if ($errors): ?>
            <div class="error-box" role="alert">
                <?php foreach ($errors as $e): ?>
                    &bull; <?php echo htmlspecialchars($e); ?><br>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($messages): ?>
            <div class="message-box">
                <?php foreach ($messages as $m): ?>
                    <?php echo htmlspecialchars($m); ?><br>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <div class="form-group">
                <label for="code">Verification Code</label>
                <input id="code" name="code" placeholder="Enter 6-digit code" autofocus>
            </div>
            <button class="btn" type="submit">Verify</button>
        </form>

        <div class="resend-row">
            <a href="verify.php?resend=1">Resend code</a>
            <a class="cancel-link" href="logout.php">Cancel</a>
        </div>
    </div>
</div>
</body>
</html>
