<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // adjust path if needed
// Load configuration from environment variables or local `config.php` (not committed)
$configFile = __DIR__ . '/config.php';
$fileConfig = [];
if (file_exists($configFile)) {
    $fileConfig = include $configFile;
    if (!is_array($fileConfig)) {
        $fileConfig = [];
    }
}

function cfg($key, $default = null) {
    $envKey = strtoupper($key);
    $env = getenv($envKey);
    global $fileConfig;
    if ($env !== false && $env !== '') {
        return $env;
    }
    return $fileConfig[$key] ?? $default;
}

$smtpHost = cfg('smtp_host', 'smtp.gmail.com');
$smtpUser = cfg('smtp_user', 'verdinshanel@gmail.com');
$smtpPass = cfg('smtp_pass', 'ywdghlviybgizpnx');
$smtpPort = (int) cfg('smtp_port', 587);
$smtpSecure = cfg('smtp_secure', 'tls'); // 'tls' or 'ssl'
$mailFrom = cfg('mail_from', $smtpUser);
$mailFromName = cfg('mail_from_name', 'Hotel Admin');

if (strtolower($smtpSecure) === 'ssl') {
    $smtpSecureConst = PHPMailer::ENCRYPTION_SMTPS;
} else {
    $smtpSecureConst = PHPMailer::ENCRYPTION_STARTTLS;
}

function sendAppointmentConfirmationEmail($toEmail, $toName, $appointmentDateTime, $serviceName, $staffName = null, $roomName = null, $toPhone = null) {
    global $smtpHost, $smtpUser, $smtpPass, $smtpPort, $smtpSecureConst, $mailFrom, $mailFromName;
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = $smtpSecureConst;
        $mail->Port       = $smtpPort;

        $mail->setFrom($mailFrom, $mailFromName);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'Appointment Confirmation';

        $body = "<p>Dear " . htmlspecialchars($toName) . ",</p>";
        $body .= "<p>Your appointment has been confirmed with the following details:</p>";
        $body .= "<ul>";
        $body .= "<li><strong>Service:</strong> " . htmlspecialchars($serviceName) . "</li>";
        $body .= "<li><strong>Date & Time:</strong> " . htmlspecialchars($appointmentDateTime) . "</li>";
        if ($staffName) {
            $body .= "<li><strong>Staff:</strong> " . htmlspecialchars($staffName) . "</li>";
        }
        if ($roomName) {
            $body .= "<li><strong>Room:</strong> " . htmlspecialchars($roomName) . "</li>";
        }
        $body .= "</ul>";
        $body .= "<p>Thank you for choosing our hotel services!</p>";
        $body .= "<p>Best regards,<br>Hotel Admin Team</p>";

        $mail->Body = $body;

        $mail->send();
        $emailOk = true;
        // optionally send SMS if phone provided
        if ($toPhone) {
            try {
                sendSMS($toPhone, "Reminder: your appointment for $serviceName is scheduled at $appointmentDateTime.");
            } catch (Exception $e) {
                // log but don't fail the email send
                error_log("SMS error: " . $e->getMessage());
            }
        }
        return $emailOk;
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

// Optional SMS sending helper (uses Twilio if configured via env or config.php)
function sendSMS($toPhone, $message) {
    // Check for Twilio config
    $accountSid = cfg('twilio_account_sid', null);
    $authToken = cfg('twilio_auth_token', null);
    $from = cfg('twilio_from', null);

    if ($accountSid && $authToken && $from) {
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";
        $data = http_build_query(['From' => $from, 'To' => $toPhone, 'Body' => $message]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_USERPWD, $accountSid . ':' . $authToken);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) {
            error_log("Twilio SMS error: " . $err);
            return false;
        }
        return true;
    }

    // No SMS provider configured — log and return false
    error_log("SMS not sent: Twilio not configured. Message to {$toPhone}: {$message}");
    return false;
}

// Send a simple verification code email (for login OTP)
function sendVerificationEmail($toEmail, $toName, $code) {
    global $smtpHost, $smtpUser, $smtpPass, $smtpPort, $smtpSecureConst, $mailFrom, $mailFromName;
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = $smtpSecureConst;
        $mail->Port       = $smtpPort;

        $mail->setFrom($mailFrom, $mailFromName);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'Your verification code';

        $body = "<p>Dear " . htmlspecialchars($toName) . ",</p>";
        $body .= "<p>Your login verification code is: <strong>" . htmlspecialchars($code) . "</strong></p>";
        $body .= "<p>This code will expire in 5 minutes. If you did not request this, please ignore this email.</p>";
        $body .= "<p>Best regards,<br>Hotel Admin Team</p>";

        $mail->Body = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Verification Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

// Send appointment status update email (uses configured SMTP settings)
function sendAppointmentStatusEmail($toEmail, $toName, $serviceName, $appointmentDateTime, $status) {
    global $smtpHost, $smtpUser, $smtpPass, $smtpPort, $smtpSecureConst, $mailFrom, $mailFromName;
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = $smtpSecureConst;
        $mail->Port       = $smtpPort;

        $mail->setFrom($mailFrom, $mailFromName);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'Your Appointment Status Has Been Updated';

        $statusFormatted = htmlspecialchars(ucfirst($status));
        $body = "<p>Dear " . htmlspecialchars($toName) . ",</p>";
        $body .= "<p>Your appointment for <strong>" . htmlspecialchars($serviceName) . "</strong> scheduled on <strong>" . htmlspecialchars($appointmentDateTime) . "</strong> has been updated to the status: <strong>" . $statusFormatted . "</strong>.</p>";
        $body .= "<p>If you have any questions, please contact us.</p>";
        $body .= "<p>Thank you,<br/>Hotel Team</p>";

        $mail->Body = $body;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Appointment Status Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}
