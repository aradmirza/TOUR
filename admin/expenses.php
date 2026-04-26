<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/admin-auth.php';
requireAdmin();

// DELETE action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyAdminCsrf()) { adminFlash('Invalid request.', 'danger'); header('Location: expenses.php'); exit; }
    $expId = (int)($_POST['expense_id'] ?? 0);
    if ($expId) {
        $db->prepare("DELETE FROM expenses WHERE id = ?")->execute([$expId]);
        // bind_param style
        $stmt = $db->prepare("DELETE FROM expenses WHERE id = ?");
        $stmt->bind_param("i", $expId);
        $stmt->execute();
        adminFlash('Expense deleted.', 'success');
    }
    header('Location: expenses.php?' . http_build_query($_GET)); exit;
}

// Filters
$search   = trim($_GET['q']        ?? '');
$category = $_GET['category']      ?? '';
$groupId  = (int)($_GET['group_id'] ?? 0);
$dateFrom = $_GET['date_from']     ?? '';
$dateTo   = $_GET['date_to']       ?? '';
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 25;
$offset   = ($page - 1) * $perPage;

$where  = '1=1';
$params = [];
$types  = '';

if ($search) {
    $like = '%' . $search . '%';
    $where .= " AND e.title LIKE ?";
    $params[] = $like; $types .= 's';
}
$validCats = ['transport','food','hotel','ticket','shopping','emergency','other'];
if ($category && in_array($category, $validCats)) {
    $where .= " AND e.category = ?";
    $params[] = $category; $types .= 's';
}
if ($groupId) {
    $where .= " AND e.group_id = ?";
    $params[] = $groupId; $types .= 'i';
}
if ($dateFrom) {
    $where .= " AND DATE(e.created_at) >= ?";
    $params[] = $dateFrom; $types .= 's';
}
if ($dateTo) {
    $where .= " AND DATE(e.created_at) <= ?";
    $params[] = $dateTo; $types .= 's';
}

// Count + Sum
$countStmt = $db->prepare("SELECT COUNT(*), COALESCE(SUM(e.amount),0) FROM expenses e WHERE $where");
if ($params) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$countRow   = $countStmt->get_result()->fetch_row();
$total      = $countRow[0];
$totalSum   = $countRow[1];
$totalPages = max(1, ceil($total / $perPage));

// Fetch
$stmt = $db->prepare(
    "SELECT e.*, u.name AS paid_name, tg.name AS group_name
     FROM expenses e
     JOIN users u ON u.id = e.paid_by
     JOIN tour_groups tg ON tg.id = e.group_id
     WHERE $where ORDER BY e.created_at DESC LIMIT ? OFFSET ?"
);
$allParams = array_merge($params, [$perPage, $offset]);
$stmt->bind_param($types . 'ii', ...$allParams);
$stmt->execute();
$expenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// All groups for dropdown
$allGroups = $db->query("SELECT id, name FROM tour_groups ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

$pageTitle  = 'Expenses';
$activePage = 'expenses';
include __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-page-header">
  <div>
    <h2>Expenses <span class="admin-count-badge"><?= $total ?></span></h2>
    <p class="text-muted" style="margin-top:4px">Total filtered: <strong><?= formatMoney($totalSum) ?></strong></p>
  </div>
</div>

<!-- Filters -->
<form method="GET" class="admin-filter-bar admin-filter-bar-wrap">
  <input type="text" name="q" class="admin-input admin-search-input"
         placeholder="Search title…" value="<?= e($search) ?>">

  <select name="category" class="admin-select">
    <option value="">All Categories</option>
    <?php foreach ($validCats as $cat): ?>
      <option value="<?= $cat ?>" <?= $category===$cat?'selected':'' ?>>
        <?= categoryIcon($cat) ?> <?= ucfirst($cat) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <select name="group_id" class="admin-select">
    <option value="">All Groups</option>
    <?php foreach ($allGroups as $g): ?>
      <option value="<?= $g['id'] ?>" <?= $groupId===$g['id']?'selected':'' ?>><?= e($g['name']) ?></option>
    <?php endforeach; ?>
  </select>

  <input type="date" name="date_from" class="admin-input" style="max-width:160px" value="<?= e($dateFrom) ?>" title="From date">
  <input type="date" name="date_to"   class="admin-input" style="max-width:160px" value="<?= e($dateTo) ?>"   title="To date">

  <button type="submit" class="btn-admin btn-admin-primary">Filter</button>
  <a href="expenses.php" class="btn-admin btn-admin-outline">Clear</a>
</form>

<div class="admin-card">
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr><th>#</th><th>Title</th><th>Group</th><th>Category</th><th>Paid By</th><th>Amount</th><th>Date</th><th>Receipt</th><th>Del</th></tr>
      </thead>
      <tbody>
        <?php if (!$expenses): ?>
          <tr><td colspan="9" class="text-center text-muted" style="padding:40px">No expenses found.</td></tr>
        <?php endif; ?>
        <?php foreach ($expenses as $exp): ?>
        <tr>
          <td class="text-muted"><?= $exp['id'] ?></td>
          <td><?= e($exp['title']) ?><?php if ($exp['note']): ?><br><small class="text-muted"><?= e(mb_substr($exp['note'],0,50)) ?></small><?php endif; ?></td>
          <td class="text-muted"><?= e($exp['group_name']) ?></td>
          <td><?= categoryIcon($exp['category']) ?> <?= ucfirst($exp['category']) ?></td>
          <td class="text-muted"><?= e($exp['paid_name']) ?></td>
          <td><strong><?= formatMoney($exp['amount']) ?></strong></td>
          <td class="text-muted" style="white-space:nowrap"><?= date('M j, Y', strtotime($exp['created_at'])) ?></td>
          <td>
            <?php if ($exp['receipt_image'] && file_exists(__DIR__ . '/../uploads/receipts/' . $exp['receipt_image'])): ?>
              <a href="<?= uploadUrl('receipts', $exp['receipt_image']) ?>" target="_blank" class="btn-admin btn-admin-sm btn-admin-outline">🧾</a>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td>
            <form method="POST" style="display:inline">
              <?= adminCsrfField() ?>
              <input type="hidden" name="expense_id" value="<?= $exp['id'] ?>">
              <button type="submit" class="btn-admin btn-admin-sm btn-admin-danger"
                      data-confirm="Delete this expense? This cannot be undone.">🗑</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
  <div class="admin-pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <?php $q = http_build_query(array_merge($_GET, ['page' => $i])); ?>
      <a href="?<?= $q ?>" class="admin-page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
