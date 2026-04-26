<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/admin-auth.php';

if (isAdminLoggedIn()) { header('Location: ' . adminBaseUrl() . 'dashboard.php'); exit; }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyAdminCsrf()) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');

        if (!$email || !$password) {
            $errors[] = 'Please enter email and password.';
        } else {
            $stmt = $db->prepare("SELECT id, name, email, password FROM admins WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $admin = $stmt->get_result()->fetch_assoc();

            if (!$admin || !password_verify($password, $admin['password'])) {
                $errors[] = 'Invalid email or password.';
            } else {
                setAdminSession($admin);
                header('Location: ' . adminBaseUrl() . 'dashboard.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login — TourMate</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= adminBaseUrl() ?>assets/css/admin.css">
</head>
<body class="admin-login-body">

<div class="admin-login-wrap">
  <div class="admin-login-card">

    <div class="admin-login-logo">
      <div class="admin-login-icon">⚙️</div>
      <div class="admin-login-brand">TourMate <span>Admin</span></div>
    </div>

    <h2 class="admin-login-title">Welcome back</h2>
    <p class="admin-login-sub">Sign in to your admin panel</p>

    <?php if ($errors): ?>
      <div class="admin-alert admin-alert-danger">❌ <?= e(implode(' ', $errors)) ?></div>
    <?php endif; ?>

    <form method="POST">
      <?= adminCsrfField() ?>

      <div class="admin-form-group">
        <label class="admin-label">Email Address</label>
        <input type="email" name="email" class="admin-input"
               placeholder="admin@example.com"
               value="<?= e($_POST['email'] ?? '') ?>" autofocus required>
      </div>

      <div class="admin-form-group">
        <label class="admin-label">Password</label>
        <input type="password" name="password" class="admin-input"
               placeholder="Your password" required>
      </div>

      <button type="submit" class="btn-admin btn-admin-primary btn-admin-block mt-4">
        Sign In →
      </button>
    </form>

    <div class="admin-login-footer">
      <a href="<?= rootBaseUrl() ?>">← Back to Site</a>
    </div>
  </div>
</div>

</body>
</html>
