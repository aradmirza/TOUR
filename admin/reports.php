<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/admin-auth.php';
requireAdmin();

// Users by month (last 6 months)
$usersByMonth = $db->query(
    "SELECT DATE_FORMAT(created_at,'%Y-%m') AS month,
            DATE_FORMAT(created_at,'%b %Y') AS label,
            COUNT(*) AS cnt
     FROM users
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY DATE_FORMAT(created_at,'%Y-%m')
     ORDER BY month ASC"
)->fetch_all(MYSQLI_ASSOC);

// Expenses by month (last 6 months)
$expByMonth = $db->query(
    "SELECT DATE_FORMAT(created_at,'%Y-%m') AS month,
            DATE_FORMAT(created_at,'%b %Y') AS label,
            COUNT(*) AS cnt,
            COALESCE(SUM(amount),0) AS total
     FROM expenses
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY DATE_FORMAT(created_at,'%Y-%m')
     ORDER BY month ASC"
)->fetch_all(MYSQLI_ASSOC);

// Expenses by category
$expByCat = $db->query(
    "SELECT category, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total
     FROM expenses GROUP BY category ORDER BY total DESC"
)->fetch_all(MYSQLI_ASSOC);
$grandTotal = array_sum(array_column($expByCat, 'total'));

// Group status summary
$groupStats = $db->query(
    "SELECT status, COUNT(*) AS cnt FROM tour_groups GROUP BY status"
)->fetch_all(MYSQLI_ASSOC);
$groupStatMap = array_column($groupStats, 'cnt', 'status');

// Top 10 most active groups by expense
$topGroups = $db->query(
    "SELECT tg.name, tg.destination, tg.status,
            COUNT(DISTINCT gm.user_id) AS members,
            COUNT(DISTINCT e.id) AS exp_count,
            COALESCE(SUM(e.amount),0) AS total_exp
     FROM tour_groups tg
     LEFT JOIN group_members gm ON gm.group_id = tg.id
     LEFT JOIN expenses e ON e.group_id = tg.id
     GROUP BY tg.id ORDER BY total_exp DESC LIMIT 10"
)->fetch_all(MYSQLI_ASSOC);

// Top 5 users by expense paid
$topUsers = $db->query(
    "SELECT u.name, u.email, COUNT(e.id) AS exp_count, COALESCE(SUM(e.amount),0) AS total
     FROM users u LEFT JOIN expenses e ON e.paid_by = u.id
     GROUP BY u.id ORDER BY total DESC LIMIT 5"
)->fetch_all(MYSQLI_ASSOC);

$pageTitle  = 'Reports';
$activePage = 'reports';
include __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-page-header">
  <div>
    <h2>Reports</h2>
    <p class="text-muted" style="margin-top:4px;font-size:13px">Generated: <?= date('F j, Y g:i A') ?></p>
  </div>
  <button onclick="window.print()" class="btn-admin btn-admin-outline">🖨 Print Report</button>
</div>

<!-- Monthly User Registrations -->
<div class="admin-card mb-4">
  <div class="admin-card-header"><h3 class="admin-card-title">User Registrations — Last 6 Months</h3></div>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead><tr><th>Month</th><th>New Users</th><th>Bar</th></tr></thead>
      <tbody>
        <?php
        $maxUsers = max(array_column($usersByMonth, 'cnt') ?: [1]);
        foreach ($usersByMonth as $row):
          $pct = round($row['cnt'] / $maxUsers * 100);
        ?>
        <tr>
          <td><?= e($row['label']) ?></td>
          <td><strong><?= $row['cnt'] ?></strong></td>
          <td style="width:60%">
            <div class="admin-progress-wrap">
              <div class="admin-progress-bar admin-progress-blue" style="width:<?= $pct ?>%"></div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$usersByMonth): ?><tr><td colspan="3" class="text-center text-muted">No data.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Monthly Expenses -->
<div class="admin-card mb-4">
  <div class="admin-card-header"><h3 class="admin-card-title">Expense Trend — Last 6 Months</h3></div>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead><tr><th>Month</th><th>Count</th><th>Total Amount</th><th>Bar</th></tr></thead>
      <tbody>
        <?php
        $maxExp = max(array_column($expByMonth, 'total') ?: [1]);
        foreach ($expByMonth as $row):
          $pct = round($row['total'] / $maxExp * 100);
        ?>
        <tr>
          <td><?= e($row['label']) ?></td>
          <td><?= $row['cnt'] ?></td>
          <td><strong><?= formatMoney($row['total']) ?></strong></td>
          <td style="width:40%">
            <div class="admin-progress-wrap">
              <div class="admin-progress-bar admin-progress-orange" style="width:<?= $pct ?>%"></div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$expByMonth): ?><tr><td colspan="4" class="text-center text-muted">No data.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="admin-grid-2">

  <!-- Expense by Category -->
  <div class="admin-card">
    <div class="admin-card-header"><h3 class="admin-card-title">Expenses by Category</h3></div>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead><tr><th>Category</th><th>Count</th><th>Amount</th><th>%</th></tr></thead>
        <tbody>
          <?php foreach ($expByCat as $cat):
            $pct = $grandTotal > 0 ? round($cat['total'] / $grandTotal * 100, 1) : 0;
          ?>
          <tr>
            <td><?= categoryIcon($cat['category']) ?> <?= ucfirst($cat['category']) ?></td>
            <td><?= $cat['cnt'] ?></td>
            <td><?= formatMoney($cat['total']) ?></td>
            <td><?= $pct ?>%</td>
          </tr>
          <?php endforeach; ?>
          <tr style="font-weight:700;background:#f8fafc">
            <td>Total</td>
            <td><?= array_sum(array_column($expByCat,'cnt')) ?></td>
            <td><?= formatMoney($grandTotal) ?></td>
            <td>100%</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Group Status Summary -->
  <div class="admin-card">
    <div class="admin-card-header"><h3 class="admin-card-title">Group Status</h3></div>
    <div class="admin-card-body">
      <div class="admin-report-stat"><span>✅ Active</span><strong><?= $groupStatMap['active'] ?? 0 ?></strong></div>
      <div class="admin-report-stat"><span>🏁 Completed</span><strong><?= $groupStatMap['completed'] ?? 0 ?></strong></div>
      <div class="admin-report-stat"><span>❌ Cancelled</span><strong><?= $groupStatMap['cancelled'] ?? 0 ?></strong></div>

      <h4 style="margin-top:24px;margin-bottom:12px;font-size:13px;font-weight:700;">Top 5 Users by Spending</h4>
      <table class="admin-table">
        <thead><tr><th>User</th><th>Transactions</th><th>Total Paid</th></tr></thead>
        <tbody>
          <?php foreach ($topUsers as $u): ?>
          <tr>
            <td><?= e($u['name']) ?></td>
            <td><?= $u['exp_count'] ?></td>
            <td><strong><?= formatMoney($u['total']) ?></strong></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Top Groups -->
<div class="admin-card mt-4">
  <div class="admin-card-header"><h3 class="admin-card-title">Top Groups by Expenses</h3></div>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead><tr><th>Group</th><th>Destination</th><th>Status</th><th>Members</th><th>Transactions</th><th>Total Expense</th></tr></thead>
      <tbody>
        <?php foreach ($topGroups as $g): ?>
        <tr>
          <td><?= e($g['name']) ?></td>
          <td class="text-muted">📍 <?= e($g['destination']) ?></td>
          <td><span class="admin-badge admin-badge-<?= $g['status']==='active'?'success':($g['status']==='completed'?'blue':'danger') ?>"><?= ucfirst($g['status']) ?></span></td>
          <td>👥 <?= $g['members'] ?></td>
          <td><?= $g['exp_count'] ?></td>
          <td><strong><?= formatMoney($g['total_exp']) ?></strong></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$topGroups): ?><tr><td colspan="6" class="text-center text-muted">No data.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
