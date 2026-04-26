<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/admin-auth.php';
requireAdmin();

// --- Stats ---
$totalUsers     = $db->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
$activeUsers    = $db->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetch_row()[0];
$totalGroups    = $db->query("SELECT COUNT(*) FROM tour_groups")->fetch_row()[0];
$activeGroups   = $db->query("SELECT COUNT(*) FROM tour_groups WHERE status='active'")->fetch_row()[0];
$completedGroups= $db->query("SELECT COUNT(*) FROM tour_groups WHERE status='completed'")->fetch_row()[0];
$totalPosts     = $db->query("SELECT COUNT(*) FROM posts WHERE status='active'")->fetch_row()[0];
$totalComments  = $db->query("SELECT COUNT(*) FROM post_comments WHERE status='active'")->fetch_row()[0];
$totalExpenses  = $db->query("SELECT COALESCE(SUM(amount),0) FROM expenses")->fetch_row()[0];
$todayUsers     = $db->query("SELECT COUNT(*) FROM users WHERE DATE(created_at)=CURDATE()")->fetch_row()[0];
$todayExpenses  = $db->query("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE DATE(created_at)=CURDATE()")->fetch_row()[0];

// --- Recent users ---
$recentUsers = $db->query(
    "SELECT id,name,email,status,created_at FROM users ORDER BY created_at DESC LIMIT 6"
)->fetch_all(MYSQLI_ASSOC);

// --- Recent expenses ---
$recentExpenses = $db->query(
    "SELECT e.*, u.name AS paid_name, tg.name AS group_name
     FROM expenses e
     JOIN users u ON u.id = e.paid_by
     JOIN tour_groups tg ON tg.id = e.group_id
     ORDER BY e.created_at DESC LIMIT 6"
)->fetch_all(MYSQLI_ASSOC);

// --- Expense by category ---
$catData = $db->query(
    "SELECT category, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total
     FROM expenses GROUP BY category ORDER BY total DESC"
)->fetch_all(MYSQLI_ASSOC);

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
include __DIR__ . '/includes/admin-header.php';
?>

<!-- Stats Row 1 -->
<div class="admin-stats-grid">
  <div class="admin-stat-card admin-stat-blue">
    <div class="admin-stat-icon">👥</div>
    <div class="admin-stat-body">
      <div class="admin-stat-value"><?= $totalUsers ?></div>
      <div class="admin-stat-label">Total Users</div>
      <div class="admin-stat-sub">+<?= $todayUsers ?> today</div>
    </div>
  </div>
  <div class="admin-stat-card admin-stat-teal">
    <div class="admin-stat-icon">✈️</div>
    <div class="admin-stat-body">
      <div class="admin-stat-value"><?= $totalGroups ?></div>
      <div class="admin-stat-label">Tour Groups</div>
      <div class="admin-stat-sub"><?= $activeGroups ?> active · <?= $completedGroups ?> done</div>
    </div>
  </div>
  <div class="admin-stat-card admin-stat-orange">
    <div class="admin-stat-icon">💸</div>
    <div class="admin-stat-body">
      <div class="admin-stat-value"><?= formatMoney($totalExpenses) ?></div>
      <div class="admin-stat-label">Total Expenses</div>
      <div class="admin-stat-sub">Today: <?= formatMoney($todayExpenses) ?></div>
    </div>
  </div>
  <div class="admin-stat-card admin-stat-purple">
    <div class="admin-stat-icon">📰</div>
    <div class="admin-stat-body">
      <div class="admin-stat-value"><?= $totalPosts ?></div>
      <div class="admin-stat-label">Posts</div>
      <div class="admin-stat-sub"><?= $totalComments ?> comments</div>
    </div>
  </div>
</div>

<div class="admin-grid-2">

  <!-- Recent Users -->
  <div class="admin-card">
    <div class="admin-card-header">
      <h3 class="admin-card-title">Recent Users</h3>
      <a href="users.php" class="btn-admin btn-admin-sm btn-admin-outline">View All</a>
    </div>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr><th>User</th><th>Email</th><th>Status</th><th>Joined</th></tr>
        </thead>
        <tbody>
          <?php foreach ($recentUsers as $u): ?>
          <tr>
            <td><a href="user-view.php?id=<?= $u['id'] ?>"><?= e($u['name']) ?></a></td>
            <td class="text-muted"><?= e($u['email']) ?></td>
            <td>
              <span class="admin-badge admin-badge-<?= $u['status']==='active'?'success':'danger' ?>">
                <?= ucfirst($u['status']) ?>
              </span>
            </td>
            <td class="text-muted"><?= date('M j', strtotime($u['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$recentUsers): ?>
            <tr><td colspan="4" class="text-center text-muted">No users yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Recent Expenses -->
  <div class="admin-card">
    <div class="admin-card-header">
      <h3 class="admin-card-title">Recent Expenses</h3>
      <a href="expenses.php" class="btn-admin btn-admin-sm btn-admin-outline">View All</a>
    </div>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr><th>Title</th><th>Group</th><th>Amount</th><th>Date</th></tr>
        </thead>
        <tbody>
          <?php foreach ($recentExpenses as $exp): ?>
          <tr>
            <td><?= e($exp['title']) ?></td>
            <td class="text-muted"><?= e($exp['group_name']) ?></td>
            <td><strong><?= formatMoney($exp['amount']) ?></strong></td>
            <td class="text-muted"><?= date('M j', strtotime($exp['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$recentExpenses): ?>
            <tr><td colspan="4" class="text-center text-muted">No expenses yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Expense by Category -->
<?php if ($catData): ?>
<div class="admin-card mt-4">
  <div class="admin-card-header">
    <h3 class="admin-card-title">Expenses by Category</h3>
  </div>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr><th>Category</th><th>Count</th><th>Total Amount</th><th>Share</th></tr>
      </thead>
      <tbody>
        <?php foreach ($catData as $cat):
          $pct = $totalExpenses > 0 ? round($cat['total'] / $totalExpenses * 100, 1) : 0;
        ?>
        <tr>
          <td><?= categoryIcon($cat['category']) ?> <?= ucfirst(e($cat['category'])) ?></td>
          <td><?= $cat['cnt'] ?></td>
          <td><strong><?= formatMoney($cat['total']) ?></strong></td>
          <td>
            <div class="admin-progress-wrap">
              <div class="admin-progress-bar" style="width:<?= $pct ?>%"></div>
              <span><?= $pct ?>%</span>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
