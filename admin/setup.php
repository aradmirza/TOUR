<?php
// ============================================================
// Admin Setup — Create First Admin Account
// DELETE THIS FILE AFTER CREATING YOUR ADMIN ACCOUNT!
// ============================================================
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/admin-auth.php';

// Block if an admin already exists
$count = $db->query("SELECT COUNT(*) FROM admins")->fetch_row()[0];
if ($count > 0) {
    die('<div style="font-family:sans-serif;padding:40px;text-align:center;">
        <h2>⚠️ Setup already complete</h2>
        <p>An admin account already exists. Delete this file from your server.</p>
        <a href="login.php">→ Go to Admin Login</a>
    </div>');
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm']  ?? '');

    if (!$name || !$email || !$password)     $errors[] = 'All fields are required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
    if (strlen($password) < 8)               $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm)              $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO admins (name, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $email, $hash);
        if ($stmt->execute()) {
            $success = true;
        } else {
            $errors[] = 'Could not create admin. Email may already exist.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Setup — TourMate</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= adminBaseUrl() ?>assets/css/admin.css">
</head>
<body class="admin-login-body">
<div class="admin-login-wrap">
  <div class="admin-login-card">
    <div class="admin-login-logo">
      <div class="admin-login-icon">⚙️</div>
      <div class="admin-login-brand">TourMate <span>Setup</span></div>
    </div>

    <?php if ($success): ?>
      <div class="admin-alert admin-alert-success">
        ✅ Admin account created successfully!<br>
        <strong>Delete this file (setup.php) from your server immediately.</strong>
      </div>
      <a href="login.php" class="btn-admin btn-admin-primary btn-admin-block">→ Go to Login</a>
    <?php else: ?>
      <h2 class="admin-login-title">Create Admin Account</h2>
      <p class="admin-login-sub">This page is only for initial setup.</p>

      <?php if ($errors): ?>
        <div class="admin-alert admin-alert-danger">❌ <?= e(implode('<br>', $errors)) ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="admin-form-group">
          <label class="admin-label">Full Name</label>
          <input type="text" name="name" class="admin-input" placeholder="Super Admin"
                 value="<?= e($_POST['name'] ?? '') ?>" required>
        </div>
        <div class="admin-form-group">
          <label class="admin-label">Email Address</label>
          <input type="email" name="email" class="admin-input" placeholder="admin@example.com"
                 value="<?= e($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="admin-form-group">
          <label class="admin-label">Password (min 8 chars)</label>
          <input type="password" name="password" class="admin-input" required>
        </div>
        <div class="admin-form-group">
          <label class="admin-label">Confirm Password</label>
          <input type="password" name="confirm" class="admin-input" required>
        </div>
        <button type="submit" class="btn-admin btn-admin-primary btn-admin-block mt-4">
          Create Admin Account
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
