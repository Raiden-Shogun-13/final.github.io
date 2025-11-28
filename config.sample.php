<?php
/**
 * Copy this file to `config.php` and fill in your SMTP settings.
 * Keep `config.php` out of version control (it's ignored by .gitignore).
 * You can also set environment variables instead of creating `config.php`:
 *  - SMTP_HOST
 *  - SMTP_USER
 *  - SMTP_PASS
 *  - SMTP_PORT
 *  - SMTP_SECURE  (tls or ssl)
 *  - MAIL_FROM
 *  - MAIL_FROM_NAME
 */

return [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_user' => 'you@example.com',
    'smtp_pass' => 'your_smtp_password_or_app_password',
    'smtp_port' => 587,
    'smtp_secure' => 'tls', // or 'ssl'
    'mail_from' => 'you@example.com',
    'mail_from_name' => 'Hotel Admin',
];
