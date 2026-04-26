<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (!isLoggedIn()) { header('Location: ../login.php'); exit; }

$userId = currentUserId();

// Fetch user data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
if (!$u) { flash('User not found.', 'danger'); header('Location: dashboard.php'); exit; }

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name    = trim($_POST['name']    ?? '');
        $mobile  = trim($_POST['mobile']  ?? '');
        $address = trim($_POST['address'] ?? '');
        $bio     = trim($_POST['bio']     ?? '');

        if (!$name) { flash('Name is required.', 'danger'); header('Location: profile.php'); exit; }

        $photo = $u['profile_photo'];
        if (!empty($_FILES['photo']['name'])) {
            $res = uploadFile($_FILES['photo'], UPLOAD_PROFILE, 'avatar');
            if ($res['success']) {
                if ($photo && file_exists(UPLOAD_PROFILE . $photo)) unlink(UPLOAD_PROFILE . $photo);
                $photo = $res['filename'];
            } else {
                flash($res['error'], 'danger');
                header('Location: profile.php'); exit;
            }
        }

        $stmt = $db->prepare(
            "UPDATE users SET name=?, mobile=?, address=?, bio=?, profile_photo=? WHERE id=?"
        );
        $stmt->bind_param("sssssi", $name, $mobile, $address, $bio, $photo, $userId);
        $stmt->execute();

        // Update session
        $_SESSION['user_name']  = $name;
        $_SESSION['user_photo'] = $photo;

        flash('Profile updated successfully!', 'success');
        header('Location: profile.php'); exit;

    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!$current || !$new || !$confirm) {
            flash('All password fields are required.', 'danger');
        } elseif (strlen($new) < 6) {
            flash('New password must be at least 6 characters.', 'danger');
        } elseif ($new !== $confirm) {
            flash('Passwords do not match.', 'danger');
        } elseif (!password_verify($current, $u['password'])) {
            flash('Current password is incorrect.', 'danger');
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password=? WHERE id=?");
            $stmt->bind_param("si", $hash, $userId);
            $stmt->execute();
            flash('Password changed successfully!', 'success');
        }
        header('Location: profile.php'); exit;
    }
}

// Stats
$stmt = $db->prepare("SELECT COUNT(*) FROM group_members WHERE user_id=?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$groupCount = $stmt->get_result()->fetch_row()[0];

$stmt = $db->prepare("SELECT COUNT(*) FROM posts WHERE user_id=? AND status='active'");
$stmt->bind_param("i", $userId);
$stmt->execute();
$postCount = $stmt->get_result()->fetch_row()[0];

$stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE paid_by=?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$totalPaid = $stmt->get_result()->fetch_row()[0];

$pageTitle  = 'My Profile';
$activePage = 'profile';
include __DIR__ . '/includes/user-header.php';
?>

<!-- Profile Hero -->
<div class="profile-hero">
  <div class="profile-hero-avatar">
    <?= uAvatar($u['name'], $u['profile_photo'], 80) ?>
  </div>
  <div class="profile-hero-name"><?= e($u['name']) ?></div>
  <div class="profile-hero-email">✉️ <?= e($u['email']) ?></div>
  <?php if ($u['bio']): ?>
    <div class="profile-hero-bio">"<?= e($u['bio']) ?>"</div>
  <?php endif; ?>
</div>

<!-- Stats Bar -->
<div class="stats-grid" style="margin-bottom:20px;">
  <div class="stat-card">
    <div class="stat-icon">✈️</div>
    <div class="stat-value"><?= $groupCount ?></div>
    <div class="stat-label">Tours</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">📰</div>
    <div class="stat-value"><?= $postCount ?></div>
    <div class="stat-label">Posts</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">💸</div>
    <div class="stat-value"><?= formatMoney($totalPaid) ?></div>
    <div class="stat-label">Paid</div>
  </div>
</div>

<div class="grid-2">

  <!-- Edit Profile -->
  <div class="card">
    <div class="card-header"><h3 class="card-title">✏️ Edit Profile</h3></div>
    <div style="padding:20px;">
      <form method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="update_profile">

        <div class="form-group">
          <label class="form-label">Profile Photo</label>
          <div style="display:flex;align-items:center;gap:14px;margin-bottom:6px;">
            <div id="photoPreview"><?= uAvatar($u['name'], $u['profile_photo'], 56) ?></div>
            <input type="file" name="photo" accept="image/*" class="form-input" id="photoInput">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Full Name <span style="color:var(--danger)">*</span></label>
          <input type="text" name="name" class="form-input" value="<?= e($u['name']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Email (read-only)</label>
          <input type="email" class="form-input" value="<?= e($u['email']) ?>" disabled>
        </div>
        <div class="form-group">
          <label class="form-label">Mobile Number</label>
          <input type="text" name="mobile" class="form-input" value="<?= e($u['mobile'] ?? '') ?>" placeholder="+880...">
        </div>
        <div class="form-group">
          <label class="form-label">Address</label>
          <input type="text" name="address" class="form-input" value="<?= e($u['address'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Bio</label>
          <textarea name="bio" class="form-input" rows="3" placeholder="Tell something about yourself..."><?= e($u['bio'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Save Profile</button>
      </form>
    </div>
  </div>

  <!-- Change Password -->
  <div class="card">
    <div class="card-header"><h3 class="card-title">🔒 Change Password</h3></div>
    <div style="padding:20px;">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="change_password">
        <div class="form-group">
          <label class="form-label">Current Password</label>
          <input type="password" name="current_password" class="form-input" required>
        </div>
        <div class="form-group">
          <label class="form-label">New Password</label>
          <input type="password" name="new_password" class="form-input" required minlength="6">
        </div>
        <div class="form-group">
          <label class="form-label">Confirm New Password</label>
          <input type="password" name="confirm_password" class="form-input" required>
        </div>
        <button type="submit" class="btn btn-warning btn-block">Change Password</button>
      </form>
    </div>

    <div style="padding:0 20px 20px;">
      <hr style="margin:16px 0;border:none;border-top:1px solid var(--border);">
      <h3 class="card-title" style="margin-bottom:12px;">📋 Account Info</h3>
      <div style="font-size:13px;color:var(--text-muted);display:flex;flex-direction:column;gap:8px;">
        <div>📧 <?= e($u['email']) ?></div>
        <?php if ($u['mobile']): ?><div>📱 <?= e($u['mobile']) ?></div><?php endif; ?>
        <?php if ($u['address']): ?><div>📍 <?= e($u['address']) ?></div><?php endif; ?>
        <div>📅 Member since <?= date('M Y', strtotime($u['created_at'])) ?></div>
      </div>
    </div>
  </div>

</div>

<script>
document.getElementById('photoInput')?.addEventListener('change', function() {
    const f = this.files[0];
    if (!f) return;
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('photoPreview').innerHTML =
            '<img src="' + e.target.result + '" style="width:56px;height:56px;border-radius:50%;object-fit:cover;">';
    };
    reader.readAsDataURL(f);
});
</script>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
