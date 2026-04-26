<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

$userId = currentUserId();

// Fetch current user
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Stats
$stmt = $db->prepare("SELECT COUNT(*) FROM group_members WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$groupCount = $stmt->get_result()->fetch_row()[0];

$stmt = $db->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$postCount = $stmt->get_result()->fetch_row()[0];

$stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE paid_by = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$totalPaid = $stmt->get_result()->fetch_row()[0];

// --- Handle profile update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    if (!verifyCsrf()) {
        flash('Invalid request.', 'danger');
    } else {
        $name    = trim($_POST['name']    ?? '');
        $mobile  = trim($_POST['mobile']  ?? '');
        $bio     = trim($_POST['bio']     ?? '');
        $address = trim($_POST['address'] ?? '');

        if (!$name) {
            flash('Name cannot be empty.', 'danger');
        } else {
            $photo = $user['profile_photo'];

            // Handle photo upload
            if (!empty($_FILES['photo']['name'])) {
                $res = uploadFile($_FILES['photo'], UPLOAD_PROFILE, 'user' . $userId);
                if (!$res['success']) {
                    flash($res['error'], 'danger');
                    header('Location: profile.php'); exit;
                }
                // Delete old photo
                if ($photo) deleteFile(UPLOAD_PROFILE, $photo);
                $photo = $res['filename'];
            }

            $stmt = $db->prepare(
                "UPDATE users SET name=?, mobile=?, bio=?, address=?, profile_photo=? WHERE id=?"
            );
            $stmt->bind_param("sssssi", $name, $mobile, $bio, $address, $photo, $userId);
            $stmt->execute();

            // Update session
            $_SESSION['user_name']  = $name;
            $_SESSION['user_photo'] = $photo;

            flash('Profile updated successfully!', 'success');
            header('Location: profile.php'); exit;
        }
    }
}

// --- Handle password change ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'password') {
    if (!verifyCsrf()) {
        flash('Invalid request.', 'danger');
    } else {
        $current  = $_POST['current_password'] ?? '';
        $new1     = $_POST['new_password']     ?? '';
        $new2     = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $user['password'])) {
            flash('Current password is incorrect.', 'danger');
        } elseif (strlen($new1) < 6) {
            flash('New password must be at least 6 characters.', 'danger');
        } elseif ($new1 !== $new2) {
            flash('New passwords do not match.', 'danger');
        } else {
            $hash = password_hash($new1, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password=? WHERE id=?");
            $stmt->bind_param("si", $hash, $userId);
            $stmt->execute();
            flash('Password changed successfully!', 'success');
            header('Location: profile.php'); exit;
        }
    }
}

$pageTitle  = 'My Profile';
$activePage = 'profile';
include 'includes/header.php';
?>

<div class="page-header">
  <div>
    <h1>My Profile</h1>
    <p>Manage your personal information</p>
  </div>
</div>

<div class="grid-2">

  <!-- Profile Info Card -->
  <div>
    <div class="card mb-3">
      <div class="card-body" style="text-align:center;padding:28px 20px;">
        <div style="position:relative;display:inline-block;margin-bottom:14px;">
          <?= avatarHtml($user['name'], $user['profile_photo'], 80) ?>
        </div>
        <h3 style="font-size:17px;font-weight:700;margin-bottom:4px;"><?= e($user['name']) ?></h3>
        <p class="text-muted text-small"><?= e($user['email']) ?></p>
        <?php if ($user['mobile']): ?>
          <p class="text-muted text-small">📱 <?= e($user['mobile']) ?></p>
        <?php endif; ?>
        <?php if ($user['bio']): ?>
          <p style="margin-top:10px;font-size:13.5px;color:var(--text);"><?= nl2br(e($user['bio'])) ?></p>
        <?php endif; ?>
        <?php if ($user['address']): ?>
          <p class="text-muted text-small mt-2">📍 <?= e($user['address']) ?></p>
        <?php endif; ?>
      </div>
      <div class="card-footer">
        <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);gap:10px;margin:0;">
          <div style="text-align:center;">
            <div style="font-size:18px;font-weight:700;"><?= $groupCount ?></div>
            <div class="text-muted text-small">Tours</div>
          </div>
          <div style="text-align:center;">
            <div style="font-size:18px;font-weight:700;"><?= $postCount ?></div>
            <div class="text-muted text-small">Posts</div>
          </div>
          <div style="text-align:center;">
            <div style="font-size:18px;font-weight:700;"><?= formatMoney($totalPaid) ?></div>
            <div class="text-muted text-small">Paid</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Edit Form -->
  <div>
    <div class="card mb-3">
      <div class="card-header">
        <h3 class="card-title">Edit Profile</h3>
      </div>
      <div class="card-body">
        <form method="POST" action="profile.php" enctype="multipart/form-data">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="update">

          <div class="form-group">
            <label class="form-label">Profile Photo</label>
            <input type="file" name="photo" class="form-control" accept="image/*"
                   onchange="previewImg(this,'photoPreview')">
            <div id="photoPreview" class="img-preview" style="<?= $user['profile_photo'] ? '' : 'display:none' ?>">
              <?php if ($user['profile_photo']): ?>
                <img src="uploads/profile/<?= e($user['profile_photo']) ?>" alt="">
              <?php endif; ?>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Full Name</label>
            <input type="text" name="name" class="form-control"
                   value="<?= e($user['name']) ?>" required>
          </div>

          <div class="form-group">
            <label class="form-label">Mobile</label>
            <input type="text" name="mobile" class="form-control"
                   value="<?= e($user['mobile']) ?>">
          </div>

          <div class="form-group">
            <label class="form-label">Bio</label>
            <textarea name="bio" class="form-control" rows="3"
                      placeholder="Tell something about yourself..."><?= e($user['bio']) ?></textarea>
          </div>

          <div class="form-group">
            <label class="form-label">Address</label>
            <input type="text" name="address" class="form-control"
                   value="<?= e($user['address']) ?>" placeholder="Your city / address">
          </div>

          <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
      </div>
    </div>

    <!-- Change Password -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Change Password</h3>
      </div>
      <div class="card-body">
        <form method="POST" action="profile.php">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="password">

          <div class="form-group">
            <label class="form-label">Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" required>
          </div>

          <button type="submit" class="btn btn-outline">Change Password</button>
        </form>
      </div>
    </div>
  </div>

</div>

<?php include 'includes/footer.php'; ?>
