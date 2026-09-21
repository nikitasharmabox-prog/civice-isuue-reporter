<?php
define('BASE_URL', 'http://localhost/civic-reporter');
define('BASE_PATH', dirname(__DIR__));

// Clustering settings
define('CLUSTER_RADIUS_METERS', 300);       // Group complaints within 300m
define('CLUSTER_THRESHOLD', 3);             // Notify officer when >= 3 complaints in cluster
define('NOTIFICATION_COOLDOWN_HOURS', 6);   // Re-notify every 6 hours max

// Upload settings
define('UPLOAD_DIR', BASE_PATH . '/uploads/complaints/');
define('UPLOAD_URL', BASE_URL . '/uploads/complaints/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);  // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);

// App info
define('APP_NAME', 'Civic Issue Reporter');
define('APP_VERSION', '1.0.0');

// Session
define('SESSION_LIFETIME', 3600 * 8); // 8 hours

// Mailer — set MAIL_USE_SMTP to true and fill credentials to enable Gmail SMTP
define('MAIL_USE_SMTP',     true);
define('MAIL_FROM',         'adityamadhabborah.adtu@gmail.com');   // Your Gmail address
define('MAIL_FROM_NAME',    'Civic Issue Reporter');
define('MAIL_SMTP_HOST',    'smtp.gmail.com');
define('MAIL_SMTP_PORT',    587);
define('MAIL_SMTP_USER',    'adityamadhabborah.adtu@gmail.com');   // Your Gmail address
define('MAIL_SMTP_PASS',    'bcir nahj kqgt fwhv'); // Gmail App Password (NOT your login password)
define('MAIL_SMTP_SECURE',  'tls');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
