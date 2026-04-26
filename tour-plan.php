<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

$groupId = (int)($_GET['id'] ?? 0);
if (!$groupId) { header('Location: groups.php'); exit; }

requireGroupMember($db, $groupId);
$userId  = currentUserId();
$isAdmin = isGroupAdmin($db, $groupId, $userId);

$stmt = $db->prepare("SELECT * FROM tour_groups WHERE id = ?");
$stmt->bind_param("i", $groupId);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();
if (!$group) { flash('Group not found.', 'danger'); header('Location: groups.php'); exit; }

// Fetch members for assignment dropdown
$stmt = $db->prepare(
    "SELECT gm.user_id, u.name FROM group_members gm JOIN users u ON u.id = gm.user_id WHERE gm.group_id = ?"
);
$stmt->bind_param("i", $groupId);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// --- Handle actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_plan') {
        $title         = trim($_POST['title']          ?? '');
        $description   = trim($_POST['description']    ?? '');
        $plan_date     = trim($_POST['plan_date']       ?? '') ?: null;
        $plan_time     = trim($_POST['plan_time']       ?? '') ?: null;
        $location      = trim($_POST['location']        ?? '');
        $assigned_to   = (int)($_POST['assigned_to']   ?? 0) ?: null;
        $estimated_cost= trim($_POST['estimated_cost']  ?? '') ?: null;

        if ($title) {
            $stmt = $db->prepare(
                "INSERT INTO tour_plans (group_id, title, description, plan_date, plan_time, location, assigned_to, estimated_cost, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("isssssidi",
                $groupId, $title, $description, $plan_date, $plan_time,
                $location, $assigned_to, $estimated_cost, $userId
            );
            $stmt->execute();

            // Notify all members about new plan
            foreach ($members as $m) {
                $msg  = currentUser()['name'] . ' added a new plan: "' . $title . '"';
                $link = 'tour-plan.php?id=' . $groupId;
                sendNotification($db, $m['user_id'], $userId, $groupId, 'plan_added', $msg, $link);
            }

            flash('Plan item added!', 'success');
        } else {
            flash('Title is required.', 'danger');
        }
    }

    elseif ($action === 'update_status') {
        $planId = (int)($_POST['plan_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $allowed = ['pending', 'running', 'completed'];
        if ($planId && in_array($status, $allowed)) {
            $stmt = $db->prepare("UPDATE tour_plans SET status=? WHERE id=? AND group_id=?");
            $stmt->bind_param("sii", $status, $planId, $groupId);
            $stmt->execute();
            if ($status === 'completed') {
                foreach ($members as $m) {
                    // Get plan title
                    $pr = $db->prepare("SELECT title FROM tour_plans WHERE id=?");
                    $pr->bind_param("i", $planId);
                    $pr->execute();
                    $pt = $pr->get_result()->fetch_row()[0] ?? 'a plan';
                    $msg  = currentUser()['name'] . ' completed: "' . $pt . '"';
                    $link = 'tour-plan.php?id=' . $groupId;
                    sendNotification($db, $m['user_id'], $userId, $groupId, 'plan_completed', $msg, $link);
                }
            }
            flash('Status updated.', 'success');
        }
    }

    elseif ($action === 'delete_plan' && $isAdmin) {
        $planId = (int)($_POST['plan_id'] ?? 0);
        if ($planId) {
            $stmt = $db->prepare("DELETE FROM tour_plans WHERE id=? AND group_id=?");
            $stmt->bind_param("ii", $planId, $groupId);
            $stmt->execute();
            flash('Plan deleted.', 'success');
        }
    }

    header('Location: tour-plan.php?id=' . $groupId); exit;
}

// Fetch plans
$statusFilter = $_GET['status'] ?? 'all';
$sql = "SELECT tp.*, u.name AS creator_name, a.name AS assigned_name
        FROM tour_plans tp
        JOIN users u ON u.id = tp.created_by
        LEFT JOIN users a ON a.id = tp.assigned_to
        WHERE tp.group_id = ?";
$params = [$groupId];
$types  = "i";
if ($statusFilter !== 'all') {
    $sql .= " AND tp.status = ?";
    $params[] = $statusFilter;
    $types   .= "s";
}
$sql .= " ORDER BY tp.plan_date ASC, tp.plan_time ASC, tp.created_at ASC";
$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$plans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Count by status
$stmt = $db->prepare("SELECT status, COUNT(*) FROM tour_plans WHERE group_id=? GROUP BY status");
$stmt->bind_param("i", $groupId);
$stmt->execute();
$counts = [];
$res = $stmt->get_result();
while ($r = $res->fetch_row()) $counts[$r[0]] = $r[1];

$pageTitle  = 'Tour Plan — ' . e($group['name']);
$activePage = 'groups';
$groupTab   = 'plan';
include 'includes/header.php';
?>

<div class="group-hero">
  <?php if ($group['cover_photo'] && file_exists(UPLOAD_GROUP . $group['cover_photo'])): ?>
    <img src="uploads/group/<?= e($group['cover_photo']) ?>" alt="">
  <?php else: ?>
    <div class="group-hero-gradient"></div>
  <?php endif; ?>
  <div class="group-hero-overlay">
    <div class="group-hero-title"><?= e($group['name']) ?></div>
    <div class="group-hero-sub">📍 <?= e($group['destination']) ?></div>
  </div>
</div>

<?php include 'includes/group_subnav.php'; ?>

<div class="page-header">
  <div>
    <h1>Tour Plan</h1>
    <p>Manage your tour checklist</p>
  </div>
  <button class="btn btn-primary" onclick="openModal('addPlanModal')">+ Add Plan</button>
</div>

<!-- Stats row -->
<div class="stats-grid mb-3" style="grid-template-columns:repeat(3,1fr);">
  <div class="stat-card">
    <div class="stat-icon">⏳</div>
    <div class="stat-value"><?= $counts['pending'] ?? 0 ?></div>
    <div class="stat-label">Pending</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">🔄</div>
    <div class="stat-value"><?= $counts['running'] ?? 0 ?></div>
    <div class="stat-label">Running</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">✅</div>
    <div class="stat-value"><?= $counts['completed'] ?? 0 ?></div>
    <div class="stat-label">Completed</div>
  </div>
</div>

<!-- Filter -->
<div class="filter-row mb-3">
  <?php foreach (['all'=>'All','pending'=>'Pending','running'=>'Running','completed'=>'Completed'] as $k=>$v): ?>
    <a href="tour-plan.php?id=<?= $groupId ?>&status=<?= $k ?>"
       class="btn btn-sm <?= $statusFilter===$k?'btn-primary':'btn-outline' ?>"><?= $v ?></a>
  <?php endforeach; ?>
</div>

<!-- Plans -->
<?php if (!$plans): ?>
  <div class="card">
    <div class="empty-state">
      <div class="empty-icon">📋</div>
      <div class="empty-title">No plans yet</div>
      <div class="empty-desc">Add plan items to organize your tour activities.</div>
      <button class="btn btn-primary" onclick="openModal('addPlanModal')">Add First Plan</button>
    </div>
  </div>
<?php else: ?>
  <?php foreach ($plans as $plan): ?>
  <div class="plan-item <?= $plan['status'] === 'completed' ? 'completed' : '' ?>">
    <div class="plan-dot plan-dot-<?= $plan['status'] ?>"></div>
    <div style="flex:1;min-width:0;">
      <div class="plan-title <?= $plan['status'] === 'completed' ? 'done' : '' ?>"><?= e($plan['title']) ?></div>
      <div class="plan-details">
        <?php if ($plan['plan_date']): ?>
          <span class="plan-detail">📅 <?= date('M j, Y', strtotime($plan['plan_date'])) ?></span>
        <?php endif; ?>
        <?php if ($plan['plan_time']): ?>
          <span class="plan-detail">🕐 <?= date('g:i A', strtotime($plan['plan_time'])) ?></span>
        <?php endif; ?>
        <?php if ($plan['location']): ?>
          <span class="plan-detail">📍 <?= e($plan['location']) ?></span>
        <?php endif; ?>
        <?php if ($plan['assigned_name']): ?>
          <span class="plan-detail">👤 <?= e($plan['assigned_name']) ?></span>
        <?php endif; ?>
        <?php if ($plan['estimated_cost']): ?>
          <span class="plan-detail">💸 <?= formatMoney($plan['estimated_cost']) ?></span>
        <?php endif; ?>
      </div>
      <?php if ($plan['description']): ?>
        <p style="font-size:12.5px;color:var(--text-muted);margin-top:4px;"><?= nl2br(e($plan['description'])) ?></p>
      <?php endif; ?>
    </div>
    <div class="plan-actions">
      <!-- Status dropdown -->
      <form method="POST" action="tour-plan.php?id=<?= $groupId ?>">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
        <select name="status" class="filter-select" onchange="this.form.submit()">
          <option value="pending"   <?= $plan['status']==='pending'?'selected':'' ?>>Pending</option>
          <option value="running"   <?= $plan['status']==='running'?'selected':'' ?>>Running</option>
          <option value="completed" <?= $plan['status']==='completed'?'selected':'' ?>>Completed</option>
        </select>
      </form>
      <?php if ($isAdmin): ?>
        <form method="POST" action="tour-plan.php?id=<?= $groupId ?>"
              onsubmit="return confirm('Delete this plan?')">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="delete_plan">
          <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
          <button type="submit" class="btn btn-xs btn-danger">🗑</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

<!-- Add Plan Modal -->
<div id="addPlanModal" class="modal-overlay hidden">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Add Plan Item</h3>
      <button type="button" class="modal-close" data-close-modal="addPlanModal">&#x2715;</button>
    </div>
    <form method="POST" action="tour-plan.php?id=<?= $groupId ?>">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add_plan">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Title <span style="color:var(--danger)">*</span></label>
          <input type="text" name="title" class="form-control" placeholder="e.g. Check in to hotel" required>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Date</label>
            <input type="date" name="plan_date" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Time</label>
            <input type="time" name="plan_time" class="form-control">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Location</label>
          <input type="text" name="location" class="form-control" placeholder="Place name">
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Assigned To</label>
            <select name="assigned_to" class="form-control">
              <option value="">— None —</option>
              <?php foreach ($members as $m): ?>
                <option value="<?= $m['user_id'] ?>"><?= e($m['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Estimated Cost</label>
            <input type="number" step="0.01" name="estimated_cost" class="form-control" placeholder="0.00">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="3" placeholder="Optional details..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-close-modal="addPlanModal">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Plan</button>
      </div>
    </form>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
