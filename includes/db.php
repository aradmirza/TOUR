<?php
require_once __DIR__ . '/config.php';

// Create MySQLi connection
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($db->connect_error) {
    // Don't expose DB details in production
    error_log('Database connection failed: ' . $db->connect_error);
    die('<div style="font-family:sans-serif;padding:40px;text-align:center;color:#dc2626;">
        <h2>⚠️ Database Connection Error</h2>
        <p>Could not connect to database. Please check your <code>includes/config.php</code> settings.</p>
    </div>');
}

// Set charset to utf8mb4 for emoji + multi-language support
$db->set_charset('utf8mb4');
