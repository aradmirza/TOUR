<?php
session_start();
require_once __DIR__ . '/includes/admin-auth.php';
session_unset();
session_destroy();
header('Location: ' . adminBaseUrl() . 'login.php');
exit;
