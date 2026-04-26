<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (!isLoggedIn()) { header('Location: ../login.php'); exit; }

$userId = currentUserId();

// Handle leave group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action  = $_POST['action']   ?? '';
    $groupId = (int)($_POST['group_id'] ?? 0);

    if ($action === 'leave' && $groupId) {
        // Can't leave if sole admin
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM group_members WHERE group_id=? AND role='admin'"
        );
        $stmt->bind_param("i", $groupId);
        $stmt->execute();
        $adminCount = $stmt->get_result()->fetch_row()[0];

        $stmt = $db->prepare(
            "SELECT role FROM group_members WHERE group_id=? AND user_id=? LIMIT 1"
        );
        $stmt->bind_param("ii", $groupId, $userId);
        $stmt->execute();
        $myRole = $stmt->get_result()->fetch_row()[0] ?? null;

        if ($myRole === 'admin' && $adminCount === 1) {
            flash('You are the only admin. Transfer admin role before leaving.', 'danger');
        } else {
            $stmt = $db->prepare("DELETE FROM group_members WHERE group_id=? AND user_id=?");
            $stmt->bind_param("ii", $groupId, $userId);
            $stmt->execute();
            flash('You have left the group.', 'success');
        }
        header('Location: groups.php'); exit;
    }
}

// Fetch all user groups
$stmt = $db->prepare(
    "SELECT tg.*, gm.role,
            (SELECT COUNT(*) FROM group_members gm2 WHERE gm2.group_id = tg.id) AS member_count,
            (SELECT COALESCE(SUM(amount),0) FROM expenses e WHERE e.group_id = tg.id) AS total_expense
     FROM tour_groups tg
     JOIN group_members gm ON gm.group_id = tg.id AND gm.user_id = ?
     ORDER BY tg.start_date DESC"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$groups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle  = 'My Tours';
$activePage = 'groups';
include __DIR__ . '/includes/user-header.php';
?>

<div class="flex-between mb-4">
  <h2 style="font-size:16px;font-weight:800;">My Tour Groups <span style="color:var(--text-muted);font-size:13px;font-weight:500;">(<?= count($groups) ?>)</span></h2>
  <a href="create-group.php" class="btn btn-primary">+ Create Tour</a>
</div>

<?php if (!$groups): ?>
<div class="card">
  <div class="empty-state">
    <div class="empty-icon">✈️</div>
    <div class="empty-title">No tour groups yet</div>
    <div class="empty-desc">Create your first tour group to start planning adventures with friends!</div>
    <a href="create-group.php" class="btn btn-primary">Create Tour Group</a>
  </div>
</div>

<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
  <?php foreach ($groups as $g):
    $s = getTourStatus($g['start_date'], $g['return_date']); ?>
  <div class="card" style="padding:0;overflow:hidden;">
    <a href="group.php?id=<?= $g['id'] ?>" style="display:block;text-decoration:none;color:inherit;">
      <div style="position:relative;height:120px;overflow:hidden;">
        <?php if ($g['cover_photo'] && file_exists(UPLOAD_GROUP . $g['cover_photo'])): ?>
          <img src="<?= uUrl('group', $g['cover_photo']) ?>" style="width:100%;height:100%;object-fit:cover;">
        <?php else: ?>
          <div class="group-hero-gradient" style="height:100%;"></div>
        <?php endif; ?>
        <div style="position:absolute;inset:0;background:rgba(0,0,0,0.3);"></div>
        <div style="position:absolute;top:10px;right:10px;">
          <span class="badge badge-<?= $s==='upcoming'?'blue':($s==='running'?'green':'gray') ?>"><?= ucfirst($s) ?></span>
        </div>
        <?php if ($g['role'] === 'admin'): ?>
          <div style="position:absolute;top:10px;left:10px;">
            <span class="badge badge-orange">Admin</span>
          </div>
        <?php endif; ?>
      </div>
      <div style="padding:14px;">
        <div style="font-size:15px;font-weight:700;margin-bottom:4px;"><?= e($g['name']) ?></div>
        <div style="font-size:12px;color:var(--text-muted);margin-bottom:6px;">📍 <?= e($g['destination']) ?></div>
        <div style="font-size:12px;color:var(--text-muted);">📅 <?= date('M j', strtotime($g['start_date'])) ?> – <?= date('M j, Y', strtotime($g['return_date'])) ?></div>
      </div>
    </a>
    <div style="padding:10px 14px 12px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
      <div style="display:flex;gap:12px;font-size:12px;color:var(--text-muted);">
        <span>👥 <?= $g['member_count'] ?></span>
        <span>💸 <?= formatMoney($g['total_expense']) ?></span>
      </div>
      <div style="display:flex;gap:6px;">
        <a href="group.php?id=<?= $g['id'] ?>" class="btn btn-sm btn-outline">Open</a>
        <?php if ($g['role'] !== 'admin'): ?>
        <form method="POST" style="display:inline;">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="leave">
          <input type="hidden" name="group_id" value="<?= $g['id'] ?>">
          <button type="submit" class="btn btn-sm btn-danger"
                  data-confirm="Leave '<?= e(addslashes($g['name'])) ?>'?">Leave</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
