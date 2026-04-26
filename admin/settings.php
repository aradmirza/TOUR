<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/admin-auth.php';
requireAdmin();

// Save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyAdminCsrf()) { adminFlash('Invalid request.', 'danger'); header('Location: settings.php'); exit; }

    $fields = ['site_name','site_email','footer_text','maintenance_mode','primary_color'];
    foreach ($fields as $key) {
        $val = trim($_POST[$key] ?? '');
        // Sanitize color
        if ($key === 'primary_color' && !preg_match('/^#[0-9a-fA-F]{6}$/', $val)) $val = '#2563eb';
        // maintenance_mode: only allow 0 or 1
        if ($key === 'maintenance_mode') $val = $val ? '1' : '0';
        setSetting($db, $key, $val);
    }
    adminFlash('Settings saved successfully.', 'success');
    header('Location: settings.php'); exit;
}

// Load current settings
$settings = [];
$rows = $db->query("SELECT setting_key, setting_value FROM site_settings")->fetch_all(MYSQLI_ASSOC);
foreach ($rows as $row) $settings[$row['setting_key']] = $row['setting_value'];

$s = function($key, $default = '') use ($settings) { return htmlspecialchars($settings[$key] ?? $default, ENT_QUOTES, 'UTF-8'); };

$pageTitle  = 'Settings';
$activePage = 'settings';
include __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-page-header">
  <h2>Site Settings</h2>
</div>

<div class="admin-card" style="max-width:700px">
  <div class="admin-card-header"><h3 class="admin-card-title">General Settings</h3></div>
  <div class="admin-card-body">
    <form method="POST">
      <?= adminCsrfField() ?>

      <div class="admin-form-group">
        <label class="admin-label">Site Name</label>
        <input type="text" name="site_name" class="admin-input"
               value="<?= $s('site_name','TourMate Social') ?>" required>
      </div>

      <div class="admin-form-group">
        <label class="admin-label">Contact Email</label>
        <input type="email" name="site_email" class="admin-input"
               value="<?= $s('site_email','admin@tourmate.com') ?>">
      </div>

      <div class="admin-form-group">
        <label class="admin-label">Footer Text</label>
        <input type="text" name="footer_text" class="admin-input"
               value="<?= $s('footer_text','© 2025 TourMate Social. All rights reserved.') ?>">
      </div>

      <div class="admin-form-group">
        <label class="admin-label">Primary Color</label>
        <div style="display:flex;gap:10px;align-items:center">
          <input type="color" name="primary_color" class="admin-color-picker"
                 value="<?= $s('primary_color','#2563eb') ?>">
          <span class="text-muted" style="font-size:13px">Brand accent color used across the site</span>
        </div>
      </div>

      <div class="admin-form-group">
        <label class="admin-label">Maintenance Mode</label>
        <label class="admin-toggle-wrap">
          <input type="checkbox" name="maintenance_mode" value="1"
                 <?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
          <span class="admin-toggle"></span>
          <span style="margin-left:10px;font-size:13px">Enable maintenance mode (blocks user access)</span>
        </label>
      </div>

      <button type="submit" class="btn-admin btn-admin-primary">💾 Save Settings</button>
    </form>
  </div>
</div>

<!-- Change Admin Password -->
<div class="admin-card mt-4" style="max-width:700px">
  <div class="admin-card-header"><h3 class="admin-card-title">Change Admin Password</h3></div>
  <div class="admin-card-body">
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
        // This runs after page reload, so we handle inline
    }
    ?>
    <form method="POST" action="change-password.php">
      <?= adminCsrfField() ?>
      <div class="admin-form-group">
        <label class="admin-label">Current Password</label>
        <input type="password" name="current_password" class="admin-input" required>
      </div>
      <div class="admin-form-group">
        <label class="admin-label">New Password</label>
        <input type="password" name="new_password" class="admin-input" required minlength="8">
      </div>
      <div class="admin-form-group">
        <label class="admin-label">Confirm New Password</label>
        <input type="password" name="confirm_password" class="admin-input" required minlength="8">
      </div>
      <button type="submit" class="btn-admin btn-admin-warning">🔑 Change Password</button>
    </form>
  </div>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
