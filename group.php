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

// Group info
$stmt = $db->prepare("SELECT * FROM tour_groups WHERE id = ?");
$stmt->bind_param("i", $groupId);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();
if (!$group) { flash('Group not found.', 'danger'); header('Location: groups.php'); exit; }

$tourStatus = getTourStatus($group['start_date'], $group['return_date']);

// Handle group edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_group' && $isAdmin) {
    if (!verifyCsrf()) {
        flash('Invalid request.', 'danger');
    } else {
        $name        = trim($_POST['name']        ?? '');
        $destination = trim($_POST['destination'] ?? '');
        $start_date  = trim($_POST['start_date']  ?? '');
        $return_date = trim($_POST['return_date'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $coverPhoto  = $group['cover_photo'];

        if (!empty($_FILES['cover_photo']['name'])) {
            $res = uploadFile($_FILES['cover_photo'], UPLOAD_GROUP, 'cover');
            if ($res['success']) {
                if ($coverPhoto) deleteFile(UPLOAD_GROUP, $coverPhoto);
                $coverPhoto = $res['filename'];
            }
        }

        $stmt = $db->prepare(
            "UPDATE tour_groups SET name=?, destination=?, start_date=?, return_date=?, cover_photo=?, description=? WHERE id=?"
        );
        $stmt->bind_param("ssssssi", $name, $destination, $start_date, $return_date, $coverPhoto, $description, $groupId);
        $stmt->execute();
        flash('Group updated.', 'success');
        header('Location: group.php?id=' . $groupId); exit;
    }
}

// Stats
$stmt = $db->prepare("SELECT COUNT(*) FROM group_members WHERE group_id = ?");
$stmt->bind_param("i", $groupId);
$stmt->execute();
$memberCount = $stmt->get_result()->fetch_row()[0];

$stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE group_id = ?");
$stmt->bind_param("i", $groupId);
$stmt->execute();
$totalExpense = $stmt->get_result()->fetch_row()[0];

$stmt = $db->prepare("SELECT COUNT(*) FROM tour_plans WHERE group_id = ? AND status = 'pending'");
$stmt->bind_param("i", $groupId);
$stmt->execute();
$pendingPlans = $stmt->get_result()->fetch_row()[0];

$stmt = $db->prepare("SELECT COUNT(*) FROM tour_plans WHERE group_id = ? AND status = 'completed'");
$stmt->bind_param("i", $groupId);
$stmt->execute();
$completedPlans = $stmt->get_result()->fetch_row()[0];

// My balance in this group
$stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE group_id=? AND paid_by=?");
$stmt->bind_param("ii", $groupId, $userId);
$stmt->execute();
$myPaid = $stmt->get_result()->fetch_row()[0];

$stmt = $db->prepare(
    "SELECT COALESCE(SUM(es.amount),0) FROM expense_splits es
     JOIN expenses e ON e.id = es.expense_id WHERE e.group_id=? AND es.user_id=?"
);
$stmt->bind_param("ii", $groupId, $userId);
$stmt->execute();
$myShare = $stmt->get_result()->fetch_row()[0];
$myBalance = $myPaid - $myShare;

// Recent expenses (5)
$stmt = $db->prepare(
    "SELECT e.*, u.name AS payer_name FROM expenses e
     JOIN users u ON u.id = e.paid_by
     WHERE e.group_id = ? ORDER BY e.created_at DESC LIMIT 5"
);
$stmt->bind_param("i", $groupId);
$stmt->execute();
$recentExpenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Quick settlement
$settlement = getSettlementData($db, $groupId);

$pageTitle  = e($group['name']);
$activePage = 'groups';
$groupTab   = 'overview';
include 'includes/header.php';
?>

<!-- Group Hero -->
<div class="group-hero">
  <?php if ($group['cover_photo'] && file_exists(UPLOAD_GROUP . $group['cover_photo'])): ?>
    <img src="uploads/group/<?= e($group['cover_photo']) ?>" alt="">
  <?php else: ?>
    <div class="group-hero-gradient"></div>
  <?php endif; ?>
  <div class="group-hero-overlay">
    <span class="badge badge-<?= $tourStatus === 'upcoming' ? 'blue' : ($tourStatus === 'running' ? 'green' : 'gray') ?>" style="margin-bottom:8px;">
      <?= ucfirst($tourStatus) ?>
    </span>
    <div class="group-hero-title"><?= e($group['name']) ?></div>
    <div class="group-hero-sub">📍 <?= e($group['destination']) ?> &nbsp;·&nbsp;
      📅 <?= date('M j', strtotime($group['start_date'])) ?> – <?= date('M j, Y', strtotime($group['return_date'])) ?>
    </div>
  </div>
  <?php if ($isAdmin): ?>
    <button class="btn btn-sm btn-outline" onclick="openModal('editGroupModal')"
            style="position:absolute;top:14px;right:14px;color:#fff;border-color:rgba(255,255,255,0.5);">
      ✏️ Edit
    </button>
  <?php endif; ?>
</div>

<!-- Sub Nav -->
<?php include 'includes/group_subnav.php'; ?>

<!-- Countdown -->
<?php if ($tourStatus === 'upcoming'): ?>
<div class="card mb-4" style="background:linear-gradient(135deg,#1d4ed8,#0891b2);border:none;">
  <div class="card-body" style="padding:20px 24px;">
    <p style="color:rgba(255,255,255,0.8);font-size:12px;font-weight:600;letter-spacing:1px;text-transform:uppercase;margin-bottom:12px;">
      ⏳ Tour Starts In
    </p>
    <div class="countdown-widget" id="countdown" data-start="<?= e($group['start_date']) ?>">
      <div class="cd-box"><span class="cd-value" id="cd-days">--</span><span class="cd-label">Days</span></div>
      <div class="cd-sep">:</div>
      <div class="cd-box"><span class="cd-value" id="cd-hours">--</span><span class="cd-label">Hrs</span></div>
      <div class="cd-sep">:</div>
      <div class="cd-box"><span class="cd-value" id="cd-mins">--</span><span class="cd-label">Min</span></div>
      <div class="cd-sep">:</div>
      <div class="cd-box"><span class="cd-value" id="cd-secs">--</span><span class="cd-label">Sec</span></div>
    </div>
  </div>
</div>
<?php elseif ($tourStatus === 'running'): ?>
<div class="card mb-4" style="background:linear-gradient(135deg,#059669,#0891b2);border:none;">
  <div class="card-body">
    <div class="countdown-status countdown-running" style="font-size:16px;">
      🟢 Tour is currently running!
    </div>
  </div>
</div>
<?php else: ?>
<div class="card mb-4" style="background:var(--bg);border:1px solid var(--border);">
  <div class="card-body">
    <div class="countdown-status countdown-completed" style="color:var(--text-muted);background:transparent;">
      ✅ Tour completed on <?= date('M j, Y', strtotime($group['return_date'])) ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid mb-4">
  <a href="group-members.php?id=<?= $groupId ?>" class="stat-card" style="text-decoration:none;">
    <div class="stat-icon">👥</div>
    <div class="stat-value"><?= $memberCount ?></div>
    <div class="stat-label">Members</div>
  </a>
  <a href="expenses.php?id=<?= $groupId ?>" class="stat-card" style="text-decoration:none;">
    <div class="stat-icon">💸</div>
    <div class="stat-value"><?= formatMoney($totalExpense) ?></div>
    <div class="stat-label">Total Expenses</div>
  </a>
  <a href="tour-plan.php?id=<?= $groupId ?>" class="stat-card" style="text-decoration:none;">
    <div class="stat-icon">📋</div>
    <div class="stat-value"><?= $pendingPlans ?></div>
    <div class="stat-label">Pending Plans</div>
  </a>
  <a href="tour-plan.php?id=<?= $groupId ?>" class="stat-card" style="text-decoration:none;">
    <div class="stat-icon">✅</div>
    <div class="stat-value"><?= $completedPlans ?></div>
    <div class="stat-label">Completed Plans</div>
  </a>
</div>

<div class="grid-2">

  <!-- My Balance -->
  <div>
    <div class="card mb-3">
      <div class="card-header">
        <h3 class="card-title">My Balance</h3>
        <a href="settlement.php?id=<?= $groupId ?>" class="btn btn-sm btn-outline">Full Report</a>
      </div>
      <div class="card-body">
        <div style="display:flex;gap:16px;flex-wrap:wrap;">
          <div style="flex:1;text-align:center;padding:14px;background:var(--success-light);border-radius:var(--radius-sm);">
            <div style="font-size:11px;color:var(--success);font-weight:600;text-transform:uppercase;letter-spacing:1px;">I Paid</div>
            <div style="font-size:20px;font-weight:800;color:var(--success);margin-top:4px;"><?= formatMoney($myPaid) ?></div>
          </div>
          <div style="flex:1;text-align:center;padding:14px;background:var(--primary-light);border-radius:var(--radius-sm);">
            <div style="font-size:11px;color:var(--primary);font-weight:600;text-transform:uppercase;letter-spacing:1px;">My Share</div>
            <div style="font-size:20px;font-weight:800;color:var(--primary);margin-top:4px;"><?= formatMoney($myShare) ?></div>
          </div>
          <div style="flex:1;text-align:center;padding:14px;background:<?= $myBalance >= 0 ? 'var(--success-light)' : 'var(--danger-light)' ?>;border-radius:var(--radius-sm);">
            <div style="font-size:11px;color:<?= $myBalance >= 0 ? 'var(--success)' : 'var(--danger)' ?>;font-weight:600;text-transform:uppercase;letter-spacing:1px;">
              <?= $myBalance >= 0 ? 'I Get Back' : 'I Owe' ?>
            </div>
            <div style="font-size:20px;font-weight:800;color:<?= $myBalance >= 0 ? 'var(--success)' : 'var(--danger)' ?>;margin-top:4px;">
              <?= formatMoney(abs($myBalance)) ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Settlement Summary -->
    <?php if ($settlement['settlements']): ?>
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Who Owes Who</h3>
        <a href="settlement.php?id=<?= $groupId ?>" class="btn btn-sm btn-outline">Details</a>
      </div>
      <div class="card-body" style="padding:12px 16px;">
        <?php foreach (array_slice($settlement['settlements'], 0, 3) as $s): ?>
        <div class="settlement-row" style="margin-bottom:8px;">
          <span class="settlement-arrow">→</span>
          <div class="settlement-names">
            <div class="settlement-from"><?= e($s['from_name']) ?> pays <?= e($s['to_name']) ?></div>
          </div>
          <div class="settlement-amount"><?= formatMoney($s['amount']) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Recent Expenses -->
  <div>
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Recent Expenses</h3>
        <a href="expenses.php?id=<?= $groupId ?>" class="btn btn-sm btn-outline">View All</a>
      </div>
      <div class="card-body" style="padding:12px 16px;">
        <?php if (!$recentExpenses): ?>
          <p class="text-muted" style="text-align:center;padding:20px 0;">No expenses yet.</p>
        <?php else: ?>
          <?php foreach ($recentExpenses as $exp): ?>
          <div class="expense-card" style="margin-bottom:8px;">
            <div class="expense-cat-icon"><?= categoryIcon($exp['category']) ?></div>
            <div class="expense-info">
              <div class="expense-title"><?= e($exp['title']) ?></div>
              <div class="expense-meta">
                Paid by <?= e($exp['payer_name']) ?> ·
                <span class="badge <?= categoryBadge($exp['category']) ?>" style="font-size:11px;"><?= ucfirst($exp['category']) ?></span>
              </div>
            </div>
            <div>
              <div class="expense-amount"><?= formatMoney($exp['amount']) ?></div>
              <div class="expense-share"><?= date('M j', strtotime($exp['expense_date'] ?: $exp['created_at'])) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if ($isAdmin): ?>
<!-- Edit Group Modal -->
<div id="editGroupModal" class="modal-overlay hidden">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Edit Tour Group</h3>
      <button type="button" class="modal-close" data-close-modal="editGroupModal">&#x2715;</button>
    </div>
    <form method="POST" action="group.php?id=<?= $groupId ?>" enctype="multipart/form-data">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="edit_group">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Tour Name</label>
          <input type="text" name="name" class="form-control" value="<?= e($group['name']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Destination</label>
          <input type="text" name="destination" class="form-control" value="<?= e($group['destination']) ?>" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Start Date</label>
            <input type="date" name="start_date" class="form-control" value="<?= e($group['start_date']) ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Return Date</label>
            <input type="date" name="return_date" class="form-control" value="<?= e($group['return_date']) ?>">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Cover Photo</label>
          <input type="file" name="cover_photo" class="form-control" accept="image/*">
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="3"><?= e($group['description']) ?></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
