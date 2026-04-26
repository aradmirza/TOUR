<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/admin-auth.php';
requireAdmin();

$groupId = (int)($_GET['id'] ?? 0);
if (!$groupId) { header('Location: groups.php'); exit; }

$stmt = $db->prepare("SELECT tg.*, u.name AS creator_name FROM tour_groups tg JOIN users u ON u.id = tg.created_by WHERE tg.id = ? LIMIT 1");
$stmt->bind_param("i", $groupId);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();
if (!$group) { adminFlash('Group not found.', 'danger'); header('Location: groups.php'); exit; }

// Members
$members = $db->prepare(
    "SELECT gm.*, u.name, u.email, u.profile_photo, u.status AS user_status
     FROM group_members gm JOIN users u ON u.id = gm.user_id
     WHERE gm.group_id = ? ORDER BY gm.role ASC, gm.joined_at ASC"
);
$members->bind_param("i", $groupId);
$members->execute();
$memberList = $members->get_result()->fetch_all(MYSQLI_ASSOC);

// Expenses
$expStmt = $db->prepare(
    "SELECT e.*, u.name AS paid_name FROM expenses e
     JOIN users u ON u.id = e.paid_by
     WHERE e.group_id = ? ORDER BY e.created_at DESC LIMIT 20"
);
$expStmt->bind_param("i", $groupId);
$expStmt->execute();
$expenses = $expStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$totalExp = array_sum(array_column($expenses, 'amount'));

// Tour plans
$planStmt = $db->prepare(
    "SELECT tp.*, u.name AS assigned_name FROM tour_plans tp
     LEFT JOIN users u ON u.id = tp.assigned_to
     WHERE tp.group_id = ? ORDER BY tp.plan_date ASC, tp.plan_time ASC"
);
$planStmt->bind_param("i", $groupId);
$planStmt->execute();
$plans = $planStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Posts
$postStmt = $db->prepare(
    "SELECT p.*, u.name AS author_name,
            (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id) AS likes,
            (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id = p.id) AS comments
     FROM posts p JOIN users u ON u.id = p.user_id
     WHERE p.group_id = ? AND p.status = 'active'
     ORDER BY p.created_at DESC LIMIT 10"
);
$postStmt->bind_param("i", $groupId);
$postStmt->execute();
$posts = $postStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$tourStatus = getTourStatus($group['start_date'], $group['return_date']);

$pageTitle  = 'Group: ' . $group['name'];
$activePage = 'groups';
include __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-page-header">
  <a href="groups.php" class="admin-back-link">← Back to Groups</a>
</div>

<!-- Group Header Card -->
<div class="admin-card admin-group-hero">
  <?php if ($group['cover_photo'] && file_exists(__DIR__ . '/../uploads/group/' . $group['cover_photo'])): ?>
    <div class="admin-group-hero-img" style="background-image:url('<?= uploadUrl('group', $group['cover_photo']) ?>')"></div>
    <div class="admin-group-hero-overlay"></div>
  <?php endif; ?>
  <div class="admin-group-hero-body">
    <h2 class="admin-group-hero-title"><?= e($group['name']) ?></h2>
    <p>📍 <?= e($group['destination']) ?></p>
    <p>📅 <?= date('M j, Y', strtotime($group['start_date'])) ?> – <?= date('M j, Y', strtotime($group['return_date'])) ?></p>
    <p>👤 Created by <?= e($group['creator_name']) ?></p>
    <div style="margin-top:12px;">
      <span class="admin-badge admin-badge-lg admin-badge-<?= $group['status']==='active'?'success':($group['status']==='completed'?'blue':'danger') ?>">
        <?= ucfirst($group['status']) ?>
      </span>
      <span class="admin-badge admin-badge-lg admin-badge-gray" style="margin-left:8px">
        <?= ucfirst($tourStatus) ?>
      </span>
    </div>
  </div>
  <!-- Quick status change -->
  <div class="admin-group-hero-actions">
    <form method="POST" action="groups.php">
      <?= adminCsrfField() ?>
      <input type="hidden" name="action"   value="set_status">
      <input type="hidden" name="group_id" value="<?= $groupId ?>">
      <select name="status" class="admin-select" onchange="this.form.submit()">
        <option value="active"    <?= $group['status']==='active'?'selected':'' ?>>✅ Active</option>
        <option value="completed" <?= $group['status']==='completed'?'selected':'' ?>>🏁 Completed</option>
        <option value="cancelled" <?= $group['status']==='cancelled'?'selected':'' ?>>❌ Cancelled</option>
      </select>
    </form>
  </div>
</div>

<!-- Stats Row -->
<div class="admin-stats-grid admin-stats-4">
  <div class="admin-stat-card admin-stat-blue">
    <div class="admin-stat-icon">👥</div>
    <div class="admin-stat-body">
      <div class="admin-stat-value"><?= count($memberList) ?></div>
      <div class="admin-stat-label">Members</div>
    </div>
  </div>
  <div class="admin-stat-card admin-stat-orange">
    <div class="admin-stat-icon">💸</div>
    <div class="admin-stat-body">
      <div class="admin-stat-value"><?= formatMoney($totalExp) ?></div>
      <div class="admin-stat-label">Total Expense</div>
    </div>
  </div>
  <div class="admin-stat-card admin-stat-teal">
    <div class="admin-stat-icon">📋</div>
    <div class="admin-stat-body">
      <div class="admin-stat-value"><?= count($plans) ?></div>
      <div class="admin-stat-label">Tour Plans</div>
    </div>
  </div>
  <div class="admin-stat-card admin-stat-purple">
    <div class="admin-stat-icon">📰</div>
    <div class="admin-stat-body">
      <div class="admin-stat-value"><?= count($posts) ?></div>
      <div class="admin-stat-label">Posts</div>
    </div>
  </div>
</div>

<div class="admin-grid-2">

  <!-- Members -->
  <div class="admin-card">
    <div class="admin-card-header"><h3 class="admin-card-title">Members</h3></div>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Joined</th></tr></thead>
        <tbody>
          <?php foreach ($memberList as $m): ?>
          <tr>
            <td><a href="user-view.php?id=<?= $m['user_id'] ?>" class="admin-link"><?= e($m['name']) ?></a></td>
            <td class="text-muted"><?= e($m['email']) ?></td>
            <td><span class="admin-badge admin-badge-<?= $m['role']==='admin'?'blue':'gray' ?>"><?= ucfirst($m['role']) ?></span></td>
            <td class="text-muted"><?= date('M j', strtotime($m['joined_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Tour Plans -->
  <div class="admin-card">
    <div class="admin-card-header"><h3 class="admin-card-title">Tour Plans</h3></div>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead><tr><th>Title</th><th>Date</th><th>Status</th><th>Cost</th></tr></thead>
        <tbody>
          <?php foreach ($plans as $plan): ?>
          <tr>
            <td><?= e($plan['title']) ?></td>
            <td class="text-muted"><?= $plan['plan_date'] ? date('M j', strtotime($plan['plan_date'])) : '—' ?></td>
            <td><span class="admin-badge admin-badge-<?= $plan['status']==='completed'?'success':($plan['status']==='running'?'blue':'gray') ?>"><?= ucfirst($plan['status']) ?></span></td>
            <td><?= $plan['estimated_cost'] ? formatMoney($plan['estimated_cost']) : '—' ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$plans): ?><tr><td colspan="4" class="text-center text-muted">No plans.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Expenses -->
<div class="admin-card mt-4">
  <div class="admin-card-header">
    <h3 class="admin-card-title">Expenses — Total: <?= formatMoney($totalExp) ?></h3>
  </div>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead><tr><th>Title</th><th>Category</th><th>Paid By</th><th>Amount</th><th>Date</th></tr></thead>
      <tbody>
        <?php foreach ($expenses as $exp): ?>
        <tr>
          <td><?= e($exp['title']) ?></td>
          <td><?= categoryIcon($exp['category']) ?> <?= ucfirst($exp['category']) ?></td>
          <td class="text-muted"><?= e($exp['paid_name']) ?></td>
          <td><strong><?= formatMoney($exp['amount']) ?></strong></td>
          <td class="text-muted"><?= date('M j, Y', strtotime($exp['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$expenses): ?><tr><td colspan="5" class="text-center text-muted">No expenses.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Posts -->
<div class="admin-card mt-4">
  <div class="admin-card-header"><h3 class="admin-card-title">Posts</h3></div>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead><tr><th>Author</th><th>Content</th><th>Likes</th><th>Comments</th><th>Date</th></tr></thead>
      <tbody>
        <?php foreach ($posts as $post): ?>
        <tr>
          <td><?= e($post['author_name']) ?></td>
          <td><?= e(mb_substr($post['content'], 0, 80)) ?>…</td>
          <td>❤️ <?= $post['likes'] ?></td>
          <td>💬 <?= $post['comments'] ?></td>
          <td class="text-muted"><?= date('M j', strtotime($post['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$posts): ?><tr><td colspan="5" class="text-center text-muted">No posts.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
