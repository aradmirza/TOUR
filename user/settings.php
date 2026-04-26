<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (!isLoggedIn()) { header('Location: ../login.php'); exit; }

$userId = currentUserId();

$stmt = $db->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_account') {
        $password = $_POST['confirm_password'] ?? '';
        if (!password_verify($password, $u['password'])) {
            flash('Incorrect password. Account not deleted.', 'danger');
        } else {
            // Delete profile photo
            if ($u['profile_photo'] && file_exists(UPLOAD_PROFILE . $u['profile_photo'])) {
                unlink(UPLOAD_PROFILE . $u['profile_photo']);
            }
            $stmt = $db->prepare("DELETE FROM users WHERE id=?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            session_destroy();
            header('Location: ../login.php?deleted=1'); exit;
        }
    }
    header('Location: settings.php'); exit;
}

$pageTitle  = 'Settings';
$activePage = 'settings';
include __DIR__ . '/includes/user-header.php';
?>

<div style="max-width:560px;margin:0 auto;">

  <!-- Account Settings -->
  <div class="settings-section">
    <div class="settings-section-title">Account</div>
    <div class="card" style="overflow:hidden;">
      <a href="profile.php" style="display:flex;align-items:center;justify-content:space-between;padding:16px;text-decoration:none;color:inherit;border-bottom:1px solid var(--border);">
        <div style="display:flex;align-items:center;gap:12px;">
          <span style="font-size:20px;">👤</span>
          <div>
            <div style="font-size:14px;font-weight:600;">Edit Profile</div>
            <div style="font-size:12px;color:var(--text-muted);">Name, photo, bio, mobile</div>
          </div>
        </div>
        <span style="color:var(--text-muted);">›</span>
      </a>
      <a href="profile.php#password" style="display:flex;align-items:center;justify-content:space-between;padding:16px;text-decoration:none;color:inherit;">
        <div style="display:flex;align-items:center;gap:12px;">
          <span style="font-size:20px;">🔒</span>
          <div>
            <div style="font-size:14px;font-weight:600;">Change Password</div>
            <div style="font-size:12px;color:var(--text-muted);">Update your login password</div>
          </div>
        </div>
        <span style="color:var(--text-muted);">›</span>
      </a>
    </div>
  </div>

  <!-- Tour Settings -->
  <div class="settings-section">
    <div class="settings-section-title">Tours & Groups</div>
    <div class="card" style="overflow:hidden;">
      <a href="groups.php" style="display:flex;align-items:center;justify-content:space-between;padding:16px;text-decoration:none;color:inherit;border-bottom:1px solid var(--border);">
        <div style="display:flex;align-items:center;gap:12px;">
          <span style="font-size:20px;">✈️</span>
          <div>
            <div style="font-size:14px;font-weight:600;">My Tour Groups</div>
            <div style="font-size:12px;color:var(--text-muted);">Manage your tour groups</div>
          </div>
        </div>
        <span style="color:var(--text-muted);">›</span>
      </a>
      <a href="create-group.php" style="display:flex;align-items:center;justify-content:space-between;padding:16px;text-decoration:none;color:inherit;">
        <div style="display:flex;align-items:center;gap:12px;">
          <span style="font-size:20px;">➕</span>
          <div>
            <div style="font-size:14px;font-weight:600;">Create New Tour</div>
            <div style="font-size:12px;color:var(--text-muted);">Plan a new adventure</div>
          </div>
        </div>
        <span style="color:var(--text-muted);">›</span>
      </a>
    </div>
  </div>

  <!-- Account Info -->
  <div class="settings-section">
    <div class="settings-section-title">Account Info</div>
    <div class="card" style="padding:16px;">
      <div style="display:flex;flex-direction:column;gap:10px;font-size:13.5px;">
        <div style="display:flex;justify-content:space-between;">
          <span style="color:var(--text-muted);">Email</span>
          <span style="font-weight:600;"><?= e($u['email']) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;">
          <span style="color:var(--text-muted);">Member Since</span>
          <span style="font-weight:600;"><?= date('M Y', strtotime($u['created_at'])) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;">
          <span style="color:var(--text-muted);">Account Status</span>
          <span class="badge badge-green">Active</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Sign Out -->
  <div class="settings-section">
    <div class="settings-section-title">Session</div>
    <div class="card" style="overflow:hidden;">
      <a href="../logout.php" style="display:flex;align-items:center;gap:12px;padding:16px;text-decoration:none;color:var(--danger);">
        <span style="font-size:20px;">⏏</span>
        <div style="font-size:14px;font-weight:600;">Sign Out</div>
      </a>
    </div>
  </div>

  <!-- Danger Zone -->
  <div class="settings-section">
    <div class="settings-section-title" style="color:var(--danger);">Danger Zone</div>
    <div class="card" style="border-color:rgba(220,38,38,0.2);overflow:hidden;">
      <div style="padding:16px;">
        <div style="font-size:14px;font-weight:600;color:var(--danger);margin-bottom:4px;">Delete Account</div>
        <div style="font-size:12px;color:var(--text-muted);margin-bottom:14px;">
          This will permanently delete your account and all your data. This action cannot be undone.
        </div>
        <button onclick="document.getElementById('deleteModal').style.display='flex'" class="btn btn-danger btn-sm">
          Delete My Account
        </button>
      </div>
    </div>
  </div>

</div>

<!-- Delete Account Modal -->
<div id="deleteModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:5000;align-items:center;justify-content:center;padding:20px;">
  <div style="background:var(--surface);border-radius:var(--radius);padding:28px;max-width:380px;width:100%;">
    <h3 style="font-size:16px;font-weight:800;color:var(--danger);margin-bottom:8px;">⚠️ Delete Account</h3>
    <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">
      Enter your password to confirm permanent account deletion.
    </p>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="delete_account">
      <div class="form-group">
        <label class="form-label">Current Password</label>
        <input type="password" name="confirm_password" class="form-input" required placeholder="Your password">
      </div>
      <div style="display:flex;gap:10px;margin-top:4px;">
        <button type="button" onclick="document.getElementById('deleteModal').style.display='none'"
                class="btn btn-outline" style="flex:1;">Cancel</button>
        <button type="submit" class="btn btn-danger" style="flex:1;">Delete</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
