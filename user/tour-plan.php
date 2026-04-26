<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$groupId = (int)($_GET['id'] ?? 0);
if (!$groupId) { header('Location: groups.php'); exit; }

if (!isLoggedIn()) { header('Location: ../login.php'); exit; }
requireGroupMember($db, $groupId);
$userId  = currentUserId();
$isAdmin = isGroupAdmin($db, $groupId, $userId);

$stmt = $db->prepare("SELECT * FROM tour_groups WHERE id=?");
$stmt->bind_param("i", $groupId);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();
if (!$group) { flash('Group not found.', 'danger'); header('Location: groups.php'); exit; }

// Members for assign dropdown
$stmt = $db->prepare(
    "SELECT gm.user_id, u.name FROM group_members gm JOIN users u ON u.id=gm.user_id WHERE gm.group_id=?"
);
$stmt->bind_param("i", $groupId);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_plan') {
        $title     = trim($_POST['title']          ?? '');
        $desc      = trim($_POST['description']    ?? '');
        $planDate  = trim($_POST['plan_date']       ?? '') ?: null;
        $planTime  = trim($_POST['plan_time']       ?? '') ?: null;
        $location  = trim($_POST['location']        ?? '');
        $assignTo  = (int)($_POST['assigned_to']    ?? 0) ?: null;
        $estCost   = (float)($_POST['estimated_cost']?? 0) ?: null;

        if (!$title) {
            flash('Plan title is required.', 'danger');
        } else {
            $stmt = $db->prepare(
                "INSERT INTO tour_plans (group_id, title, description, plan_date, plan_time, location, assigned_to, estimated_cost, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("isssssidi", $groupId, $title, $desc, $planDate, $planTime, $location, $assignTo, $estCost, $userId);
            $stmt->execute();
            flash('Plan item added!', 'success');
        }
    } elseif ($action === 'toggle_status') {
        $planId = (int)($_POST['plan_id'] ?? 0);
        if ($planId) {
            $stmt = $db->prepare("SELECT status, created_by FROM tour_plans WHERE id=? AND group_id=?");
            $stmt->bind_param("ii", $planId, $groupId);
            $stmt->execute();
            $plan = $stmt->get_result()->fetch_assoc();
            if ($plan && ($isAdmin || $plan['created_by'] == $userId)) {
                $newStatus = $plan['status'] === 'completed' ? 'pending' : 'completed';
                $stmt = $db->prepare("UPDATE tour_plans SET status=? WHERE id=?");
                $stmt->bind_param("si", $newStatus, $planId);
                $stmt->execute();
            }
        }
    } elseif ($action === 'delete_plan') {
        $planId = (int)($_POST['plan_id'] ?? 0);
        if ($planId && $isAdmin) {
            $stmt = $db->prepare("DELETE FROM tour_plans WHERE id=? AND group_id=?");
            $stmt->bind_param("ii", $planId, $groupId);
            $stmt->execute();
            flash('Plan item deleted.', 'success');
        }
    }
    header('Location: tour-plan.php?id=' . $groupId); exit;
}

// Fetch plans
$stmt = $db->prepare(
    "SELECT tp.*, u.name AS assigned_name, uc.name AS creator_name
     FROM tour_plans tp
     LEFT JOIN users u ON u.id=tp.assigned_to
     LEFT JOIN users uc ON uc.id=tp.created_by
     WHERE tp.group_id=? ORDER BY tp.plan_date ASC, tp.plan_time ASC, tp.created_at ASC"
);
$stmt->bind_param("i", $groupId);
$stmt->execute();
$plans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$doneCount  = count(array_filter($plans, fn($p) => $p['status'] === 'completed'));
$totalCount = count($plans);
$pct        = $totalCount > 0 ? round($doneCount / $totalCount * 100) : 0;

$pageTitle  = 'Tour Plan — ' . e($group['name']);
$activePage = 'groups';
include __DIR__ . '/includes/user-header.php';
?>

<!-- Sub Nav -->
<div class="group-subnav">
  <a href="group.php?id=<?= $groupId ?>">🏠 Overview</a>
  <a href="tour-plan.php?id=<?= $groupId ?>" class="active">📋 Tour Plan</a>
  <a href="expenses.php?id=<?= $groupId ?>">💸 Expenses</a>
  <a href="settlement.php?id=<?= $groupId ?>">⚖️ Settlement</a>
  <a href="feed.php?group_id=<?= $groupId ?>">📰 Feed</a>
  <a href="gallery.php?id=<?= $groupId ?>">🖼️ Gallery</a>
  <a href="group-members.php?id=<?= $groupId ?>">👥 Members</a>
</div>

<!-- Progress -->
<?php if ($totalCount > 0): ?>
<div class="card" style="padding:16px;margin-bottom:16px;">
  <div style="display:flex;justify-content:space-between;font-size:13px;font-weight:600;margin-bottom:8px;">
    <span>📋 <?= $doneCount ?>/<?= $totalCount ?> tasks completed</span>
    <span><?= $pct ?>%</span>
  </div>
  <div style="height:8px;background:var(--border);border-radius:4px;overflow:hidden;">
    <div style="height:100%;width:<?= $pct ?>%;background:var(--success);border-radius:4px;transition:width 0.4s;"></div>
  </div>
</div>
<?php endif; ?>

<div class="grid-2">

  <!-- Plans List -->
  <div>
    <div class="card">
      <div class="card-header"><h3 class="card-title">📋 Plan Items</h3></div>
      <?php if (!$plans): ?>
      <div class="empty-state" style="padding:32px;">
        <div class="empty-icon">📋</div>
        <div class="empty-title">No plans yet</div>
        <div class="empty-desc">Add your first tour plan item.</div>
      </div>
      <?php else: ?>
      <div style="padding:0 16px;">
        <?php foreach ($plans as $plan): ?>
        <div class="plan-item <?= $plan['status']==='completed'?'completed':'' ?>">
          <form method="POST" style="display:contents;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
            <button type="submit" class="plan-checkbox" title="Toggle complete">
              <?= $plan['status']==='completed' ? '✓' : '' ?>
            </button>
          </form>
          <div class="plan-body">
            <div class="plan-title"><?= e($plan['title']) ?></div>
            <div class="plan-meta">
              <?php if ($plan['plan_date']): ?><span>📅 <?= date('M j', strtotime($plan['plan_date'])) ?></span><?php endif; ?>
              <?php if ($plan['plan_time']): ?><span>⏰ <?= date('g:i A', strtotime($plan['plan_time'])) ?></span><?php endif; ?>
              <?php if ($plan['location']): ?><span>📍 <?= e($plan['location']) ?></span><?php endif; ?>
              <?php if ($plan['assigned_name']): ?><span>👤 <?= e($plan['assigned_name']) ?></span><?php endif; ?>
              <?php if ($plan['estimated_cost'] > 0): ?><span>💸 <?= formatMoney($plan['estimated_cost']) ?></span><?php endif; ?>
            </div>
            <?php if ($plan['description']): ?>
              <div style="font-size:12px;color:var(--text-muted);margin-top:4px;"><?= e($plan['description']) ?></div>
            <?php endif; ?>
          </div>
          <?php if ($isAdmin): ?>
          <form method="POST" style="display:inline;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete_plan">
            <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
            <button type="submit" class="btn btn-sm btn-danger" data-confirm="Delete this plan item?">🗑</button>
          </form>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Add Plan Form -->
  <div class="card">
    <div class="card-header"><h3 class="card-title">➕ Add Plan Item</h3></div>
    <div style="padding:20px;">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add_plan">

        <div class="form-group">
          <label class="form-label">Title <span style="color:var(--danger)">*</span></label>
          <input type="text" name="title" class="form-input" placeholder="e.g. Check in at hotel" required>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-input" rows="2" placeholder="Optional details…"></textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div class="form-group">
            <label class="form-label">Date</label>
            <input type="date" name="plan_date" class="form-input">
          </div>
          <div class="form-group">
            <label class="form-label">Time</label>
            <input type="time" name="plan_time" class="form-input">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Location</label>
          <input type="text" name="location" class="form-input" placeholder="e.g. Cox's Bazar Beach">
        </div>
        <div class="form-group">
          <label class="form-label">Assign To</label>
          <select name="assigned_to" class="form-input">
            <option value="">No one specific</option>
            <?php foreach ($members as $m): ?>
              <option value="<?= $m['user_id'] ?>"><?= e($m['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Estimated Cost</label>
          <input type="number" name="estimated_cost" class="form-input" step="0.01" min="0" placeholder="0.00">
        </div>
        <button type="submit" class="btn btn-primary btn-block">Add Plan Item</button>
      </form>
    </div>
  </div>

</div>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
