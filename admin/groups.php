<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/admin-auth.php';
requireAdmin();

// --- POST actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyAdminCsrf()) { adminFlash('Invalid request.', 'danger'); header('Location: groups.php'); exit; }

    $action  = $_POST['action']   ?? '';
    $groupId = (int)($_POST['group_id'] ?? 0);

    if ($action === 'set_status' && $groupId) {
        $status = $_POST['status'] ?? 'active';
        if (!in_array($status, ['active','completed','cancelled'])) $status = 'active';
        $stmt = $db->prepare("UPDATE tour_groups SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $groupId);
        $stmt->execute();
        adminFlash('Group status updated.', 'success');
    } elseif ($action === 'delete' && $groupId) {
        $stmt = $db->prepare("DELETE FROM tour_groups WHERE id = ?");
        $stmt->bind_param("i", $groupId);
        $stmt->execute();
        adminFlash('Group deleted.', 'success');
    }
    header('Location: groups.php'); exit;
}

// --- Filter ---
$search = trim($_GET['q']      ?? '');
$filter = $_GET['status']      ?? '';
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where  = '1=1';
$params = [];
$types  = '';

if ($search) {
    $like = '%' . $search . '%';
    $where .= " AND (tg.name LIKE ? OR tg.destination LIKE ?)";
    $params = array_merge($params, [$like, $like]);
    $types .= 'ss';
}
if (in_array($filter, ['active','completed','cancelled'])) {
    $where .= " AND tg.status = ?";
    $params[] = $filter;
    $types .= 's';
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM tour_groups tg WHERE $where");
if ($params) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$total      = $countStmt->get_result()->fetch_row()[0];
$totalPages = max(1, ceil($total / $perPage));

$stmt = $db->prepare(
    "SELECT tg.*, u.name AS creator_name,
            (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = tg.id) AS member_count,
            (SELECT COALESCE(SUM(amount),0) FROM expenses e WHERE e.group_id = tg.id) AS total_exp
     FROM tour_groups tg
     JOIN users u ON u.id = tg.created_by
     WHERE $where ORDER BY tg.created_at DESC LIMIT ? OFFSET ?"
);
$allParams = array_merge($params, [$perPage, $offset]);
$stmt->bind_param($types . 'ii', ...$allParams);
$stmt->execute();
$groups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle  = 'Tour Groups';
$activePage = 'groups';
include __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-page-header">
  <h2>Tour Groups <span class="admin-count-badge"><?= $total ?></span></h2>
</div>

<form method="GET" class="admin-filter-bar">
  <input type="text" name="q" class="admin-input admin-search-input"
         placeholder="Search name or destination…" value="<?= e($search) ?>">
  <select name="status" class="admin-select">
    <option value="">All Status</option>
    <option value="active"    <?= $filter==='active'?'selected':'' ?>>Active</option>
    <option value="completed" <?= $filter==='completed'?'selected':'' ?>>Completed</option>
    <option value="cancelled" <?= $filter==='cancelled'?'selected':'' ?>>Cancelled</option>
  </select>
  <button type="submit" class="btn-admin btn-admin-primary">Search</button>
  <?php if ($search || $filter): ?>
    <a href="groups.php" class="btn-admin btn-admin-outline">Clear</a>
  <?php endif; ?>
</form>

<div class="admin-card">
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>#</th><th>Group</th><th>Creator</th><th>Dates</th>
          <th>Members</th><th>Expenses</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$groups): ?>
          <tr><td colspan="8" class="text-center text-muted" style="padding:40px">No groups found.</td></tr>
        <?php endif; ?>
        <?php foreach ($groups as $g): ?>
        <tr>
          <td class="text-muted"><?= $g['id'] ?></td>
          <td>
            <a href="group-view.php?id=<?= $g['id'] ?>" class="admin-link"><?= e($g['name']) ?></a><br>
            <small class="text-muted">📍 <?= e($g['destination']) ?></small>
          </td>
          <td class="text-muted"><?= e($g['creator_name']) ?></td>
          <td class="text-muted" style="white-space:nowrap">
            <?= date('M j', strtotime($g['start_date'])) ?> –<br>
            <?= date('M j, Y', strtotime($g['return_date'])) ?>
          </td>
          <td>👥 <?= $g['member_count'] ?></td>
          <td><strong><?= formatMoney($g['total_exp']) ?></strong></td>
          <td>
            <span class="admin-badge admin-badge-<?= $g['status']==='active'?'success':($g['status']==='completed'?'blue':'danger') ?>">
              <?= ucfirst($g['status']) ?>
            </span>
          </td>
          <td>
            <div class="admin-action-btns">
              <a href="group-view.php?id=<?= $g['id'] ?>" class="btn-admin btn-admin-sm btn-admin-outline">👁</a>

              <!-- Change status -->
              <form method="POST" style="display:inline">
                <?= adminCsrfField() ?>
                <input type="hidden" name="action"   value="set_status">
                <input type="hidden" name="group_id" value="<?= $g['id'] ?>">
                <select name="status" class="admin-select admin-select-sm"
                        onchange="this.form.submit()" title="Change status">
                  <option value="active"    <?= $g['status']==='active'?'selected':'' ?>>Active</option>
                  <option value="completed" <?= $g['status']==='completed'?'selected':'' ?>>Completed</option>
                  <option value="cancelled" <?= $g['status']==='cancelled'?'selected':'' ?>>Cancelled</option>
                </select>
              </form>

              <form method="POST" style="display:inline">
                <?= adminCsrfField() ?>
                <input type="hidden" name="action"   value="delete">
                <input type="hidden" name="group_id" value="<?= $g['id'] ?>">
                <button type="submit" class="btn-admin btn-admin-sm btn-admin-danger"
                        data-confirm="Delete group '<?= e(addslashes($g['name'])) ?>'? All data will be lost.">🗑</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
  <div class="admin-pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <a href="?q=<?= urlencode($search) ?>&status=<?= urlencode($filter) ?>&page=<?= $i ?>"
         class="admin-page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
