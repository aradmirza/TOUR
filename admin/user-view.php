<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/admin-auth.php';
requireAdmin();

$userId = (int)($_GET['id'] ?? 0);
if (!$userId) { header('Location: users.php'); exit; }

// Fetch user
$stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user) { adminFlash('User not found.', 'danger'); header('Location: users.php'); exit; }

// User's groups
$groups = $db->prepare(
    "SELECT tg.*, gm.role,
            (SELECT COUNT(*) FROM group_members gm2 WHERE gm2.group_id = tg.id) AS member_count,
            (SELECT COALESCE(SUM(amount),0) FROM expenses e WHERE e.group_id = tg.id) AS total_exp
     FROM tour_groups tg
     JOIN group_members gm ON gm.group_id = tg.id AND gm.user_id = ?
     ORDER BY tg.start_date DESC"
);
$groups->bind_param("i", $userId);
$groups->execute();
$userGroups = $groups->get_result()->fetch_all(MYSQLI_ASSOC);

// User's expenses
$expStmt = $db->prepare(
    "SELECT e.*, tg.name AS group_name
     FROM expenses e JOIN tour_groups tg ON tg.id = e.group_id
     WHERE e.paid_by = ? ORDER BY e.created_at DESC LIMIT 10"
);
$expStmt->bind_param("i", $userId);
$expStmt->execute();
$userExpenses = $expStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// User's posts
$postStmt = $db->prepare(
    "SELECT p.*, tg.name AS group_name,
            (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id) AS likes,
            (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id = p.id) AS comments
     FROM posts p LEFT JOIN tour_groups tg ON tg.id = p.group_id
     WHERE p.user_id = ? AND p.status = 'active'
     ORDER BY p.created_at DESC LIMIT 10"
);
$postStmt->bind_param("i", $userId);
$postStmt->execute();
$userPosts = $postStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Stats
$totalPaid  = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE paid_by = ?");
$totalPaid->bind_param("i", $userId); $totalPaid->execute();
$paid = $totalPaid->get_result()->fetch_row()[0];

$pageTitle  = 'User: ' . $user['name'];
$activePage = 'users';
include __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-page-header">
  <a href="users.php" class="admin-back-link">← Back to Users</a>
</div>

<div class="admin-grid-sidebar">

  <!-- Left: Profile Card -->
  <div>
    <div class="admin-card admin-profile-card">
      <div class="admin-profile-avatar">
        <?php if ($user['profile_photo'] && file_exists(__DIR__ . '/../uploads/profile/' . $user['profile_photo'])): ?>
          <img src="<?= uploadUrl('profile', $user['profile_photo']) ?>" alt="">
        <?php else: ?>
          <div class="admin-avatar-lg"><?= strtoupper(substr($user['name'], 0, 2)) ?></div>
        <?php endif; ?>
      </div>
      <h3 class="admin-profile-name"><?= e($user['name']) ?></h3>
      <p class="admin-profile-email"><?= e($user['email']) ?></p>
      <div class="admin-profile-meta">
        <span class="admin-badge admin-badge-<?= $user['status']==='active'?'success':'danger' ?>">
          <?= ucfirst($user['status']) ?>
        </span>
      </div>
      <?php if ($user['bio']): ?>
        <p class="admin-profile-bio"><?= e($user['bio']) ?></p>
      <?php endif; ?>
      <div class="admin-profile-stats">
        <div class="admin-pstat"><div class="admin-pstat-v"><?= count($userGroups) ?></div><div class="admin-pstat-l">Groups</div></div>
        <div class="admin-pstat"><div class="admin-pstat-v"><?= count($userPosts) ?></div><div class="admin-pstat-l">Posts</div></div>
        <div class="admin-pstat"><div class="admin-pstat-v"><?= formatMoney($paid) ?></div><div class="admin-pstat-l">Paid</div></div>
      </div>
      <div class="admin-profile-info">
        <?php if ($user['mobile']): ?>
          <div><span>📱</span> <?= e($user['mobile']) ?></div>
        <?php endif; ?>
        <?php if ($user['address']): ?>
          <div><span>📍</span> <?= e($user['address']) ?></div>
        <?php endif; ?>
        <div><span>📅</span> Joined <?= date('F j, Y', strtotime($user['created_at'])) ?></div>
      </div>

      <form method="POST" action="users.php" style="margin-top:16px">
        <?= adminCsrfField() ?>
        <input type="hidden" name="action" value="toggle_status">
        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
        <button type="submit"
                class="btn-admin btn-admin-block <?= $user['status']==='active'?'btn-admin-warning':'btn-admin-success' ?>">
          <?= $user['status']==='active' ? '🔒 Deactivate User' : '🔓 Activate User' ?>
        </button>
      </form>
    </div>
  </div>

  <!-- Right: Tabs -->
  <div>

    <!-- Groups -->
    <div class="admin-card mb-4">
      <div class="admin-card-header">
        <h3 class="admin-card-title">Tour Groups (<?= count($userGroups) ?>)</h3>
      </div>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Group</th><th>Role</th><th>Members</th><th>Expenses</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($userGroups as $g): ?>
            <tr>
              <td><a href="group-view.php?id=<?= $g['id'] ?>" class="admin-link"><?= e($g['name']) ?></a><br>
                <small class="text-muted">📍 <?= e($g['destination']) ?></small></td>
              <td><span class="admin-badge admin-badge-<?= $g['role']==='admin'?'blue':'gray' ?>"><?= ucfirst($g['role']) ?></span></td>
              <td><?= $g['member_count'] ?></td>
              <td><?= formatMoney($g['total_exp']) ?></td>
              <td><span class="admin-badge admin-badge-<?= $g['status']==='active'?'success':($g['status']==='completed'?'blue':'danger') ?>"><?= ucfirst($g['status']) ?></span></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$userGroups): ?><tr><td colspan="5" class="text-center text-muted">No groups.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Expenses -->
    <div class="admin-card mb-4">
      <div class="admin-card-header">
        <h3 class="admin-card-title">Recent Expenses (paid by user)</h3>
      </div>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Title</th><th>Group</th><th>Category</th><th>Amount</th><th>Date</th></tr></thead>
          <tbody>
            <?php foreach ($userExpenses as $exp): ?>
            <tr>
              <td><?= e($exp['title']) ?></td>
              <td class="text-muted"><?= e($exp['group_name']) ?></td>
              <td><?= categoryIcon($exp['category']) ?> <?= ucfirst($exp['category']) ?></td>
              <td><strong><?= formatMoney($exp['amount']) ?></strong></td>
              <td class="text-muted"><?= date('M j, Y', strtotime($exp['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$userExpenses): ?><tr><td colspan="5" class="text-center text-muted">No expenses.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Posts -->
    <div class="admin-card">
      <div class="admin-card-header">
        <h3 class="admin-card-title">Recent Posts (<?= count($userPosts) ?>)</h3>
      </div>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Content</th><th>Group</th><th>Likes</th><th>Comments</th><th>Date</th></tr></thead>
          <tbody>
            <?php foreach ($userPosts as $post): ?>
            <tr>
              <td><?= e(mb_substr($post['content'], 0, 60)) ?>…</td>
              <td class="text-muted"><?= e($post['group_name'] ?? 'Public') ?></td>
              <td>❤️ <?= $post['likes'] ?></td>
              <td>💬 <?= $post['comments'] ?></td>
              <td class="text-muted"><?= date('M j', strtotime($post['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$userPosts): ?><tr><td colspan="5" class="text-center text-muted">No posts.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
