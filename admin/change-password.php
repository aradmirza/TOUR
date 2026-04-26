<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/admin-auth.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: settings.php'); exit; }
if (!verifyAdminCsrf()) { adminFlash('Invalid request.', 'danger'); header('Location: settings.php'); exit; }

$adminId  = currentAdmin()['id'];
$current  = $_POST['current_password']  ?? '';
$new      = $_POST['new_password']      ?? '';
$confirm  = $_POST['confirm_password']  ?? '';

$errors = [];
if (!$current || !$new || !$confirm) $errors[] = 'All fields are required.';
if ($new !== $confirm) $errors[] = 'New passwords do not match.';
if (strlen($new) < 8)  $errors[] = 'Password must be at least 8 characters.';

if (!$errors) {
    $stmt = $db->prepare("SELECT password FROM admins WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row || !password_verify($current, $row['password'])) {
        $errors[] = 'Current password is incorrect.';
    }
}

if ($errors) {
    adminFlash(implode(' ', $errors), 'danger');
} else {
    $hash = password_hash($new, PASSWORD_DEFAULT);
    $upd  = $db->prepare("UPDATE admins SET password = ? WHERE id = ?");
    $upd->bind_param("si", $hash, $adminId);
    $upd->execute();
    adminFlash('Password changed successfully.', 'success');
}

header('Location: settings.php'); exit;
