<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

$userId = currentUserId();
$errors = [];
$input  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $errors[] = 'Invalid request.';
    } else {
        $input['name']        = trim($_POST['name']        ?? '');
        $input['destination'] = trim($_POST['destination'] ?? '');
        $input['start_date']  = trim($_POST['start_date']  ?? '');
        $input['return_date'] = trim($_POST['return_date'] ?? '');
        $input['description'] = trim($_POST['description'] ?? '');

        if (!$input['name'])        $errors[] = 'Tour name is required.';
        if (!$input['destination']) $errors[] = 'Destination is required.';
        if (!$input['start_date'])  $errors[] = 'Start date is required.';
        if (!$input['return_date']) $errors[] = 'Return date is required.';
        if ($input['start_date'] && $input['return_date'] && $input['return_date'] < $input['start_date']) {
            $errors[] = 'Return date must be after start date.';
        }

        if (!$errors) {
            $coverPhoto = null;
            if (!empty($_FILES['cover_photo']['name'])) {
                $res = uploadFile($_FILES['cover_photo'], UPLOAD_GROUP, 'cover');
                if (!$res['success']) {
                    $errors[] = $res['error'];
                } else {
                    $coverPhoto = $res['filename'];
                }
            }
        }

        if (!$errors) {
            $stmt = $db->prepare(
                "INSERT INTO tour_groups (name, destination, start_date, return_date, cover_photo, description, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "ssssssi",
                $input['name'], $input['destination'],
                $input['start_date'], $input['return_date'],
                $coverPhoto, $input['description'], $userId
            );
            $stmt->execute();
            $groupId = $db->insert_id;

            // Add creator as admin
            $stmt = $db->prepare(
                "INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'admin')"
            );
            $stmt->bind_param("ii", $groupId, $userId);
            $stmt->execute();

            flash('Tour group created! Start adding members.', 'success');
            header('Location: group.php?id=' . $groupId);
            exit;
        }
    }
}

$pageTitle  = 'Create Tour Group';
$activePage = 'groups';
include 'includes/header.php';
?>

<div class="page-header">
  <div>
    <h1>Create Tour Group</h1>
    <p>Set up a new tour and invite your friends</p>
  </div>
  <a href="groups.php" class="btn btn-outline">← Back</a>
</div>

<div style="max-width:680px;">
  <?php if ($errors): ?>
    <div class="alert alert-danger">❌ <?= e(implode(' ', $errors)) ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header">
      <h3 class="card-title">✈️ Tour Details</h3>
    </div>
    <div class="card-body">
      <form method="POST" action="create-group.php" enctype="multipart/form-data">
        <?= csrfField() ?>

        <div class="form-group">
          <label class="form-label">Tour Name <span style="color:var(--danger)">*</span></label>
          <input type="text" name="name" class="form-control"
                 placeholder="e.g. Cox's Bazar Trip 2025"
                 value="<?= e($input['name'] ?? '') ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label">Destination <span style="color:var(--danger)">*</span></label>
          <div class="input-group">
            <span class="input-icon">📍</span>
            <input type="text" name="destination" class="form-control"
                   placeholder="e.g. Cox's Bazar, Bangladesh"
                   value="<?= e($input['destination'] ?? '') ?>" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Start Date <span style="color:var(--danger)">*</span></label>
            <input type="date" name="start_date" class="form-control"
                   value="<?= e($input['start_date'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Return Date <span style="color:var(--danger)">*</span></label>
            <input type="date" name="return_date" class="form-control"
                   value="<?= e($input['return_date'] ?? '') ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Cover Photo</label>
          <input type="file" name="cover_photo" class="form-control" accept="image/*"
                 onchange="previewImg(this,'coverPreview')">
          <div class="form-hint">JPG, PNG, WEBP · Max 5MB</div>
          <div id="coverPreview" class="img-preview" style="display:none;"></div>
        </div>

        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="4"
                    placeholder="What's the plan? Describe this tour..."><?= e($input['description'] ?? '') ?></textarea>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <button type="submit" class="btn btn-primary">✈️ Create Tour Group</button>
          <a href="groups.php" class="btn btn-outline">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
