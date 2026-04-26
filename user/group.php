<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (!isLoggedIn()) { header('Location: ../login.php'); exit; }

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

// Status change (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf() && $isAdmin) {
    $action = $_POST['action'] ?? '';
    if ($action === 'change_status') {
        $newStatus = $_POST['status'] ?? '';
        if (in_array($newStatus, ['active', 'completed', 'cancelled'])) {
            $stmt = $db->prepare("UPDATE tour_groups SET status=? WHERE id=?");
            $stmt->bind_param("si", $newStatus, $groupId);
            $stmt->execute();
            flash('Group status updated to ' . $newStatus . '.', 'success');
        }
        header('Location: group.php?id=' . $groupId); exit;
    }
}

// Stats
$stmt = $db->prepare("SELECT COUNT(*) FROM group_members WHERE group_id=?");
$stmt->bind_param("i", $groupId);
$stmt->execute();
$memberCount = $stmt->get_result()->fetch_row()[0];

$stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE group_id=?");
$stmt->bind_param("i", $groupId);
$stmt->execute();
$totalExpense = $stmt->get_result()->fetch_row()[0];

$stmt = $db->prepare("SELECT COUNT(*) FROM tour_plans WHERE group_id=?");
$stmt->bind_param("i", $groupId);
$stmt->execute();
$planCount = $stmt->get_result()->fetch_row()[0];

$stmt = $db->prepare("SELECT COUNT(*) FROM tour_plans WHERE group_id=? AND status='completed'");
$stmt->bind_param("i", $groupId);
$stmt->execute();
$planDone = $stmt->get_result()->fetch_row()[0];

// Recent expenses
$stmt = $db->prepare(
    "SELECT e.*, u.name AS paid_name FROM expenses e
     JOIN users u ON u.id=e.paid_by
     WHERE e.group_id=? ORDER BY e.expense_date DESC, e.created_at DESC LIMIT 5"
);
$stmt->bind_param("i", $groupId);
$stmt->execute();
$recentExpenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Recent posts
$stmt = $db->prepare(
    "SELECT p.*, u.name AS author_name, u.profile_photo AS author_photo,
            (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id=p.id) AS like_count,
            (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id=p.id) AS comment_count,
            (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id=p.id AND pl.user_id=?) AS i_liked
     FROM posts p JOIN users u ON u.id=p.user_id
     WHERE p.group_id=? AND p.status='active'
     ORDER BY p.created_at DESC LIMIT 5"
);
$stmt->bind_param("ii", $userId, $groupId);
$stmt->execute();
$recentPosts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Top members
$stmt = $db->prepare(
    "SELECT gm.user_id, gm.role, u.name, u.profile_photo FROM group_members gm
     JOIN users u ON u.id=gm.user_id WHERE gm.group_id=? ORDER BY gm.role DESC LIMIT 6"
);
$stmt->bind_param("i", $groupId);
$stmt->execute();
$topMembers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$tourStatus = getTourStatus($group['start_date'], $group['return_date']);

$pageTitle  = e($group['name']);
$activePage = 'groups';
include __DIR__ . '/includes/user-header.php';
?>

<!-- Group Hero -->
<div class="g-hero">
  <?php if ($group['cover_photo'] && file_exists(UPLOAD_GROUP . $group['cover_photo'])): ?>
    <div class="g-hero-bg" style="background-image:url('<?= uUrl('group', $group['cover_photo']) ?>');"></div>
  <?php else: ?>
    <div class="group-hero-gradient g-hero-bg"></div>
  <?php endif; ?>
  <div class="g-hero-overlay"></div>
  <div class="g-hero-content">
    <div class="g-hero-badges">
      <span class="badge badge-<?= $tourStatus==='upcoming'?'blue':($tourStatus==='running'?'green':'gray') ?>"><?= ucfirst($tourStatus) ?></span>
      <span class="badge badge-<?= $group['status']==='active'?'green':($group['status']==='completed'?'gray':'orange') ?>"><?= ucfirst($group['status']) ?></span>
      <?php if ($isAdmin): ?><span class="badge badge-orange">Admin</span><?php endif; ?>
    </div>
    <div class="g-hero-name"><?= e($group['name']) ?></div>
    <div class="g-hero-meta">📍 <?= e($group['destination']) ?></div>
    <div class="g-hero-dates">📅 <?= date('M j', strtotime($group['start_date'])) ?> – <?= date('M j, Y', strtotime($group['return_date'])) ?></div>
    <?php if ($group['description']): ?>
      <div class="g-hero-desc"><?= e($group['description']) ?></div>
    <?php endif; ?>
    <?php if ($isAdmin): ?>
    <div class="g-hero-status">
      <form method="POST" style="display:inline;">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="change_status">
        <select name="status" onchange="this.form.submit()" class="form-input g-status-select">
          <option value="active"     <?= $group['status']==='active'?'selected':'' ?>>Active</option>
          <option value="completed"  <?= $group['status']==='completed'?'selected':'' ?>>Completed</option>
          <option value="cancelled"  <?= $group['status']==='cancelled'?'selected':'' ?>>Cancelled</option>
        </select>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Sub Nav -->
<div class="group-subnav">
  <a href="group.php?id=<?= $groupId ?>" class="active">🏠 Overview</a>
  <a href="tour-plan.php?id=<?= $groupId ?>">📋 Tour Plan</a>
  <a href="expenses.php?id=<?= $groupId ?>">💸 Expenses</a>
  <a href="settlement.php?id=<?= $groupId ?>">⚖️ Settlement</a>
  <a href="feed.php?group_id=<?= $groupId ?>">📰 Feed</a>
  <a href="gallery.php?id=<?= $groupId ?>">🖼️ Gallery</a>
  <a href="group-members.php?id=<?= $groupId ?>">👥 Members</a>
</div>

<!-- Countdown -->
<?php if ($tourStatus === 'upcoming'): ?>
<div class="card" style="margin-bottom:16px;text-align:center;padding:20px;">
  <div style="font-size:13px;color:var(--text-muted);margin-bottom:8px;">⏳ TOUR STARTS IN</div>
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
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid mb-3">
  <div class="stat-card"><div class="stat-icon">👥</div><div class="stat-value"><?= $memberCount ?></div><div class="stat-label">Members</div></div>
  <div class="stat-card"><div class="stat-icon">💸</div><div class="stat-value"><?= formatMoney($totalExpense) ?></div><div class="stat-label">Total Expense</div></div>
  <div class="stat-card"><div class="stat-icon">📋</div><div class="stat-value"><?= $planDone ?>/<?= $planCount ?></div><div class="stat-label">Plans Done</div></div>
  <div class="stat-card"><div class="stat-icon">👤</div><div class="stat-value"><?= count($topMembers) ?></div><div class="stat-label">Online</div></div>
</div>

<div class="grid-2">

  <!-- Recent Expenses -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">💸 Recent Expenses</h3>
      <a href="expenses.php?id=<?= $groupId ?>" class="btn btn-sm btn-outline">View All</a>
    </div>
    <?php if ($recentExpenses): ?>
    <div class="g-card-padded">
      <?php foreach ($recentExpenses as $exp): ?>
      <div class="expense-item">
        <div class="expense-cat-icon"><?= categoryIcon($exp['category']) ?></div>
        <div class="expense-body">
          <div class="expense-title"><?= e($exp['title']) ?></div>
          <div class="expense-meta">Paid by <?= e($exp['paid_name']) ?> · <?= date('M j', strtotime($exp['expense_date'] ?: $exp['created_at'])) ?></div>
        </div>
        <div class="expense-amount"><?= formatMoney($exp['amount']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state" style="padding:24px;">No expenses yet.</div>
    <?php endif; ?>
    <div class="g-card-footer">
      <a href="expenses.php?id=<?= $groupId ?>" class="btn btn-primary btn-block">+ Add Expense</a>
    </div>
  </div>

  <!-- Members -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">👥 Members</h3>
      <a href="group-members.php?id=<?= $groupId ?>" class="btn btn-sm btn-outline">Manage</a>
    </div>
    <div class="g-members-list">
      <?php foreach ($topMembers as $m): ?>
      <div class="g-member-row">
        <?= uAvatar($m['name'], $m['profile_photo'], 36) ?>
        <div class="g-member-info">
          <div class="g-member-name"><?= e($m['name']) ?></div>
        </div>
        <span class="badge badge-<?= $m['role']==='admin'?'blue':'gray' ?>"><?= ucfirst($m['role']) ?></span>
      </div>
      <?php endforeach; ?>
      <?php if ($memberCount > 6): ?>
        <a href="group-members.php?id=<?= $groupId ?>" class="text-small" style="color:var(--primary);">+<?= $memberCount-6 ?> more</a>
      <?php endif; ?>
    </div>
    <?php if ($isAdmin): ?>
    <div class="g-card-bottom">
      <a href="group-members.php?id=<?= $groupId ?>" class="btn btn-outline btn-block">+ Invite Members</a>
    </div>
    <?php endif; ?>
  </div>

</div>

<!-- Recent Posts -->
<?php if ($recentPosts): ?>
<div class="card mt-3">
  <div class="card-header">
    <h3 class="card-title">📰 Recent Posts</h3>
    <a href="feed.php?group_id=<?= $groupId ?>" class="btn btn-sm btn-outline">View Feed</a>
  </div>
  <div class="g-members-list" style="gap:16px;">
    <?php foreach ($recentPosts as $post): ?>
    <div class="post-card" style="margin:0;box-shadow:none;border:1px solid var(--border);">
      <div class="post-header">
        <div class="post-author">
          <?= uAvatar($post['author_name'], $post['author_photo'], 36) ?>
          <div class="post-author-info">
            <div class="post-author-name"><?= e($post['author_name']) ?></div>
            <div class="post-meta"><?= timeAgo($post['created_at']) ?></div>
          </div>
        </div>
      </div>
      <div class="post-body"><?= nl2br(e($post['content'])) ?></div>
      <?php if ($post['image'] && file_exists(UPLOAD_POSTS . $post['image'])): ?>
        <img src="<?= uUrl('posts', $post['image']) ?>" class="post-image" alt="">
      <?php endif; ?>
      <div class="post-actions">
        <span class="action-btn">❤️ <?= $post['like_count'] ?></span>
        <span class="action-btn">💬 <?= $post['comment_count'] ?></span>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
