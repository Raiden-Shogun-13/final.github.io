<?php
session_start();
require 'db.php';
require 'functions.php';
require 'mail.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $errors[] = 'Please fill in both email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // If user is admin, log in automatically without verification
            if (isset($user['role']) && $user['role'] === 'admin') {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['user_name'] = $user['name'];

                header('Location: admin_dashboard.php');
                exit;
            }

            // For non-admin users, require verification code
            $_SESSION['pending_user'] = [
                'id' => $user['id'],
                'role' => $user['role'],
                'name' => $user['name'],
                'email' => $user['email']
            ];

            // generate verification code and store with expiry (5 minutes)
            $code = generate_verification_code(6);
            $_SESSION['verification'] = [
                'code' => $code,
                'expires' => time() + 300 // 5 minutes
            ];

            // send code by email
            $sent = sendVerificationEmail($user['email'], $user['name'], $code);
            if (!$sent) {
                $errors[] = 'Failed to send verification code. Please try again later.';
            } else {
                header('Location: verify.php');
                exit;
            }
        } else {
            $errors[] = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Login</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">

<div class="container" role="main">
    <div class="card" role="region" aria-labelledby="loginTitle">
    <h1 id="loginTitle" class="title">Log In</h1>

    <?php if ($errors): ?>
        <div class="error-box" role="alert" aria-live="assertive">
            <?php foreach ($errors as $e): ?>
                &bull; <?= htmlspecialchars($e) ?><br>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" novalidate>
        <div class="form-group">
            <label for="email">Email Address</label>
            <input id="email" type="email" name="email" placeholder="you@example.com" required autocomplete="email" autofocus>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" placeholder="••••••••" required autocomplete="current-password">
        </div>

        <button type="submit" class="btn">Login</button>

        <a href="register.php" class="small-link">Don't have an account? Register</a>
    </form>
    </div>
</div>

</body>
</html>
