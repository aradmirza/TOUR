<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $login    = trim($_POST['login']    ?? '');
        $password = trim($_POST['password'] ?? '');

        if (!$login || !$password) {
            $errors[] = 'Please enter your email/mobile and password.';
        } else {
            // Search by email OR mobile
            $stmt = $db->prepare(
                "SELECT id, name, email, mobile, password, profile_photo, status
                 FROM users WHERE email = ? OR mobile = ? LIMIT 1"
            );
            $stmt->bind_param("ss", $login, $login);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if (!$user || !password_verify($password, $user['password'])) {
                $errors[] = 'Invalid email/mobile or password.';
            } elseif (isset($user['status']) && $user['status'] === 'inactive') {
                $errors[] = 'Your account has been deactivated. Please contact support.';
            } else {
                setUserSession($user);
                flash('Welcome back, ' . $user['name'] . '!', 'success');
                header('Location: dashboard.php');
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
  <title>Login — TourMate Social</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-page">

  <!-- Left Panel -->
  <div class="auth-left">
    <div class="auth-decor" style="width:360px;height:360px;top:-80px;left:-80px;"></div>
    <div class="auth-decor" style="width:200px;height:200px;bottom:-40px;right:-40px;"></div>
    <div class="auth-left-content">
      <div style="font-size:42px;margin-bottom:16px;">✈️</div>
      <h1>Plan Tours.<br>Split Costs.<br>Share Memories.</h1>
      <p>TourMate Social makes group travel effortless — from planning to settlement.</p>
      <div class="auth-features">
        <div class="auth-feature"><div class="auth-feature-icon">🗓️</div><div class="auth-feature-label">Tour Planning</div></div>
        <div class="auth-feature"><div class="auth-feature-icon">💸</div><div class="auth-feature-label">Expense Splits</div></div>
        <div class="auth-feature"><div class="auth-feature-icon">📰</div><div class="auth-feature-label">Social Feed</div></div>
        <div class="auth-feature"><div class="auth-feature-icon">🖼️</div><div class="auth-feature-label">Photo Gallery</div></div>
      </div>
    </div>
  </div>

  <!-- Right Panel -->
  <div class="auth-right">
    <div class="auth-card">
      <div class="auth-logo">
        <span class="auth-logo-icon">✈️</span>
        <span class="auth-logo-name">TourMate Social</span>
      </div>
      <h2 class="auth-title">Welcome back!</h2>
      <p class="auth-subtitle">Sign in to continue your adventure.</p>

      <?php if ($errors): ?>
        <div class="alert alert-danger">❌ <?= e(implode(' ', $errors)) ?></div>
      <?php endif; ?>

      <form method="POST" action="login.php">
        <?= csrfField() ?>

        <div class="form-group">
          <label class="form-label" for="login">Email or Mobile</label>
          <div class="input-group">
            <span class="input-icon">📧</span>
            <input type="text" id="login" name="login" class="form-control"
                   placeholder="email@example.com or 01XXXXXXXXX"
                   value="<?= e($_POST['login'] ?? '') ?>" autofocus>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <div class="input-group">
            <span class="input-icon">🔒</span>
            <input type="password" id="password" name="password" class="form-control"
                   placeholder="Your password">
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block mt-3">Sign In →</button>
      </form>

      <div class="auth-footer">
        Don't have an account? <a href="register.php">Create one free</a>
      </div>
    </div>

    <p style="text-align:center;font-size:12px;color:var(--text-light);margin-top:20px;">
      Demo: <strong>demo@tourmate.com</strong> / <strong>password123</strong>
    </p>
  </div>
</div>
</body>
</html>
