<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$errors = [];
$input  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $input['name']    = trim($_POST['name']     ?? '');
        $input['email']   = trim($_POST['email']    ?? '');
        $input['mobile']  = trim($_POST['mobile']   ?? '');
        $input['pass1']   = $_POST['password']      ?? '';
        $input['pass2']   = $_POST['password2']     ?? '';

        if (!$input['name'])                 $errors[] = 'Name is required.';
        if (!$input['email'])                $errors[] = 'Email is required.';
        elseif (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
        if (!$input['mobile'])               $errors[] = 'Mobile number is required.';
        if (strlen($input['pass1']) < 6)     $errors[] = 'Password must be at least 6 characters.';
        if ($input['pass1'] !== $input['pass2']) $errors[] = 'Passwords do not match.';

        if (!$errors) {
            // Check email uniqueness
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $input['email']);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $errors[] = 'This email is already registered.';
            }
        }

        if (!$errors) {
            $hash = password_hash($input['pass1'], PASSWORD_DEFAULT);
            $stmt = $db->prepare(
                "INSERT INTO users (name, email, mobile, password) VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param("ssss", $input['name'], $input['email'], $input['mobile'], $hash);
            $stmt->execute();
            $newId = $db->insert_id;

            $user = [
                'id'            => $newId,
                'name'          => $input['name'],
                'email'         => $input['email'],
                'profile_photo' => null,
            ];
            setUserSession($user);
            flash('Welcome to TourMate Social, ' . $input['name'] . '! 🎉', 'success');
            header('Location: dashboard.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register — TourMate Social</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-page">

  <div class="auth-left">
    <div class="auth-decor" style="width:360px;height:360px;top:-80px;left:-80px;"></div>
    <div class="auth-decor" style="width:200px;height:200px;bottom:-40px;right:-40px;"></div>
    <div class="auth-left-content">
      <div style="font-size:42px;margin-bottom:16px;">✈️</div>
      <h1>Start Your Group Adventure Today.</h1>
      <p>Create an account and start planning unforgettable tours with your friends and family.</p>
      <div class="auth-features">
        <div class="auth-feature"><div class="auth-feature-icon">👥</div><div class="auth-feature-label">Group Tours</div></div>
        <div class="auth-feature"><div class="auth-feature-icon">💰</div><div class="auth-feature-label">Auto Settlement</div></div>
        <div class="auth-feature"><div class="auth-feature-icon">⏱️</div><div class="auth-feature-label">Live Countdown</div></div>
        <div class="auth-feature"><div class="auth-feature-icon">🔔</div><div class="auth-feature-label">Notifications</div></div>
      </div>
    </div>
  </div>

  <div class="auth-right">
    <div class="auth-card">
      <div class="auth-logo">
        <span class="auth-logo-icon">✈️</span>
        <span class="auth-logo-name">TourMate Social</span>
      </div>
      <h2 class="auth-title">Create your account</h2>
      <p class="auth-subtitle">Join thousands of group travelers.</p>

      <?php if ($errors): ?>
        <div class="alert alert-danger">❌ <?= e(implode(' ', $errors)) ?></div>
      <?php endif; ?>

      <form method="POST" action="register.php">
        <?= csrfField() ?>

        <div class="form-group">
          <label class="form-label">Full Name</label>
          <div class="input-group">
            <span class="input-icon">👤</span>
            <input type="text" name="name" class="form-control"
                   placeholder="Your full name"
                   value="<?= e($input['name'] ?? '') ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Email Address</label>
          <div class="input-group">
            <span class="input-icon">📧</span>
            <input type="email" name="email" class="form-control"
                   placeholder="your@email.com"
                   value="<?= e($input['email'] ?? '') ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Mobile Number</label>
          <div class="input-group">
            <span class="input-icon">📱</span>
            <input type="text" name="mobile" class="form-control"
                   placeholder="01XXXXXXXXX"
                   value="<?= e($input['mobile'] ?? '') ?>" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control"
                   placeholder="Min 6 characters" required>
          </div>
          <div class="form-group">
            <label class="form-label">Confirm Password</label>
            <input type="password" name="password2" class="form-control"
                   placeholder="Repeat password" required>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block mt-3">Create Account →</button>
      </form>

      <div class="auth-footer">
        Already have an account? <a href="login.php">Sign in</a>
      </div>
    </div>
  </div>
</div>
</body>
</html>
