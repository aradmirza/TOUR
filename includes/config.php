<?php
// ============================================================
// TourMate Social — Configuration File
// ============================================================
// EDIT THESE VALUES BEFORE UPLOADING TO HOSTINGER
// ============================================================

// --- Database Settings ---------------------------------------
define('DB_HOST',     'localhost');      // Usually 'localhost' on Hostinger
define('DB_USER',     'your_db_user');   // Your Hostinger DB username
define('DB_PASS',     'your_db_pass');   // Your Hostinger DB password
define('DB_NAME',     'tourmate_social');// Your database name

// --- Site Settings -------------------------------------------
define('SITE_NAME',   'TourMate Social');
define('SITE_URL',    'http://yourdomain.com'); // Change to your domain
define('SITE_EMAIL',  'admin@yourdomain.com');

// --- Upload Settings -----------------------------------------
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_BASE',   __DIR__ . '/../uploads/');

define('UPLOAD_PROFILE',  UPLOAD_BASE . 'profile/');
define('UPLOAD_GROUP',    UPLOAD_BASE . 'group/');
define('UPLOAD_POSTS',    UPLOAD_BASE . 'posts/');
define('UPLOAD_RECEIPTS', UPLOAD_BASE . 'receipts/');

// --- Security ------------------------------------------------
define('SESSION_LIFETIME', 86400 * 7); // 7 days

// --- Timezone ------------------------------------------------
date_default_timezone_set('Asia/Dhaka'); // Change to your timezone
