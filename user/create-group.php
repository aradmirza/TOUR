<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (!isLoggedIn()) { header('Location: ../login.php'); exit; }

$userId = currentUserId();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $name        = trim($_POST['name']        ?? '');
    $destination = trim($_POST['destination'] ?? '');
    $start_date  = trim($_POST['start_date']  ?? '');
    $return_date = trim($_POST['return_date'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (!$name)        $errors[] = 'Group name is required.';
    if (!$destination) $errors[] = 'Destination is required.';
    if (!$start_date)  $errors[] = 'Start date is required.';
    if (!$return_date) $errors[] = 'Return date is required.';
    if ($start_date && $return_date && $return_date < $start_date)
        $errors[] = 'Return date must be after start date.';

    if (!$errors) {
        $coverPhoto = null;
        if (!empty($_FILES['cover_photo']['name'])) {
            $res = uploadFile($_FILES['cover_photo'], UPLOAD_GROUP, 'cover');
            if ($res['success']) {
                $coverPhoto = $res['filename'];
            } else {
                $errors[] = $res['error'];
            }
        }

        if (!$errors) {
            $stmt = $db->prepare(
                "INSERT INTO tour_groups (name, destination, start_date, return_date, cover_photo, description, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("ssssssi",
                $name, $destination, $start_date, $return_date, $coverPhoto, $description, $userId
            );
            $stmt->execute();
            $groupId = $db->insert_id;

            // Add creator as admin
            $stmt = $db->prepare(
                "INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'admin')"
            );
            $stmt->bind_param("ii", $groupId, $userId);
            $stmt->execute();

            flash('Tour group "' . $name . '" created successfully!', 'success');
            header('Location: group.php?id=' . $groupId); exit;
        }
    }
    if ($errors) {
        flash(implode(' ', $errors), 'danger');
    }
}

$pageTitle  = 'Create Tour Group';
$activePage = 'groups';
include __DIR__ . '/includes/user-header.php';
?>

<div style="max-width:600px;margin:0 auto;">
  <div class="flex-between mb-4">
    <a href="groups.php" class="btn btn-outline">← Back</a>
    <h2 style="font-size:16px;font-weight:800;">Create New Tour</h2>
    <div></div>
  </div>

  <div class="card">
    <div class="card-header"><h3 class="card-title">✈️ Tour Details</h3></div>
    <div style="padding:24px;">
      <form method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>

        <div class="form-group">
          <label class="form-label">Cover Photo</label>
          <div id="coverPreviewWrap" style="width:100%;height:140px;border-radius:var(--radius-sm);overflow:hidden;margin-bottom:8px;background:var(--bg);display:flex;align-items:center;justify-content:center;font-size:36px;">🏔️</div>
          <input type="file" name="cover_photo" accept="image/*" class="form-input" id="coverInput">
        </div>

        <div class="form-group">
          <label class="form-label">Tour Name <span style="color:var(--danger)">*</span></label>
          <input type="text" name="name" class="form-input" placeholder="e.g. Cox's Bazar Adventure 2025"
                 value="<?= e($_POST['name'] ?? '') ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label">Destination <span style="color:var(--danger)">*</span></label>
          <input type="text" name="destination" class="form-input" placeholder="e.g. Cox's Bazar, Bangladesh"
                 value="<?= e($_POST['destination'] ?? '') ?>" required>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
          <div class="form-group">
            <label class="form-label">Start Date <span style="color:var(--danger)">*</span></label>
            <input type="date" name="start_date" class="form-input"
                   value="<?= e($_POST['start_date'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Return Date <span style="color:var(--danger)">*</span></label>
            <input type="date" name="return_date" class="form-input"
                   value="<?= e($_POST['return_date'] ?? '') ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-input" rows="3"
                    placeholder="Tour details, notes, highlights…"><?= e($_POST['description'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary btn-block" style="margin-top:8px;">
          🚀 Create Tour Group
        </button>
      </form>
    </div>
  </div>
</div>

<script>
document.getElementById('coverInput')?.addEventListener('change', function() {
    const f = this.files[0];
    if (!f) return;
    const r = new FileReader();
    r.onload = e => {
        const w = document.getElementById('coverPreviewWrap');
        w.innerHTML = '<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover;">';
    };
    r.readAsDataURL(f);
});
</script>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
