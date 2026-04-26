<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/admin-auth.php';
requireAdmin();

// --- Handle POST actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyAdminCsrf()) { adminFlash('Invalid request.', 'danger'); header('Location: users.php'); exit; }

    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);

    if ($action === 'toggle_status' && $userId) {
        $cur = $db->prepare("SELECT status FROM users WHERE id = ? LIMIT 1");
        $cur->bind_param("i", $userId);
        $cur->execute();
        $row = $cur->get_result()->fetch_row();
        if ($row) {
            $newStatus = $row[0] === 'active' ? 'inactive' : 'active';
            $upd = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
            $upd->bind_param("si", $newStatus, $userId);
            $upd->execute();
            adminFlash('User status updated to ' . $newStatus . '.', 'success');
        }
    } elseif ($action === 'delete' && $userId) {
        $del = $db->prepare("DELETE FROM users WHERE id = ?");
        $del->bind_param("i", $userId);
        $del->execute();
        adminFlash('User deleted.', 'success');
    }
    header('Location: users.php'); exit;
}

// --- Search & filter ---
$search  = trim($_GET['q']      ?? '');
$filter  = $_GET['status']      ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = '1=1';
$params = [];
$types  = '';

if ($search) {
    $like = '%' . $search . '%';
    $where .= " AND (name LIKE ? OR email LIKE ? OR mobile LIKE ?)";
    $params = array_merge($params, [$like, $like, $like]);
    $types .= 'sss';
}
if ($filter === 'active' || $filter === 'inactive') {
    $where .= " AND status = ?";
    $params[] = $filter;
    $types .= 's';
}

// Count
$countStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE $where");
if ($params) { $countStmt->bind_param($types, ...$params); }
$countStmt->execute();
$total      = $countStmt->get_result()->fetch_row()[0];
$totalPages = max(1, ceil($total / $perPage));

// Fetch
$stmt = $db->prepare(
    "SELECT id, name, email, mobile, status, created_at,
            (SELECT COUNT(*) FROM group_members gm WHERE gm.user_id = users.id) AS group_count
     FROM users WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?"
);
$allParams = array_merge($params, [$perPage, $offset]);
$allTypes  = $types . 'ii';
$stmt->bind_param($allTypes, ...$allParams);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle  = 'User Management';
$activePage = 'users';
include __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-page-header">
  <div class="admin-page-header-left">
    <h2>Users <span class="admin-count-badge"><?= $total ?></span></h2>
  </div>
</div>

<!-- Search & Filter -->
<form method="GET" class="admin-filter-bar">
  <input type="text" name="q" class="admin-input admin-search-input"
         placeholder="Search name, email, mobile…" value="<?= e($search) ?>">
  <select name="status" class="admin-select">
    <option value="">All Status</option>
    <option value="active"   <?= $filter==='active'?'selected':'' ?>>Active</option>
    <option value="inactive" <?= $filter==='inactive'?'selected':'' ?>>Inactive</option>
  </select>
  <button type="submit" class="btn-admin btn-admin-primary">Search</button>
  <?php if ($search || $filter): ?>
    <a href="users.php" class="btn-admin btn-admin-outline">Clear</a>
  <?php endif; ?>
</form>

<div class="admin-card">
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Email</th>
          <th>Mobile</th>
          <th>Groups</th>
          <th>Status</th>
          <th>Joined</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$users): ?>
          <tr><td colspan="8" class="text-center text-muted" style="padding:40px">No users found.</td></tr>
        <?php endif; ?>
        <?php foreach ($users as $u): ?>
        <tr>
          <td class="text-muted"><?= $u['id'] ?></td>
          <td><a href="user-view.php?id=<?= $u['id'] ?>" class="admin-link"><?= e($u['name']) ?></a></td>
          <td class="text-muted"><?= e($u['email']) ?></td>
          <td class="text-muted"><?= e($u['mobile'] ?: '—') ?></td>
          <td><?= $u['group_count'] ?></td>
          <td>
            <span class="admin-badge admin-badge-<?= $u['status']==='active'?'success':'danger' ?>">
              <?= ucfirst($u['status']) ?>
            </span>
          </td>
          <td class="text-muted"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
          <td>
            <div class="admin-action-btns">
              <a href="user-view.php?id=<?= $u['id'] ?>" class="btn-admin btn-admin-sm btn-admin-outline" title="View">👁</a>

              <form method="POST" style="display:inline">
                <?= adminCsrfField() ?>
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button type="submit" class="btn-admin btn-admin-sm <?= $u['status']==='active'?'btn-admin-warning':'btn-admin-success' ?>"
                        title="<?= $u['status']==='active'?'Deactivate':'Activate' ?>">
                  <?= $u['status']==='active' ? '🔒' : '🔓' ?>
                </button>
              </form>

              <form method="POST" style="display:inline">
                <?= adminCsrfField() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button type="submit" class="btn-admin btn-admin-sm btn-admin-danger"
                        data-confirm="Delete user '<?= e(addslashes($u['name'])) ?>'? This cannot be undone."
                        title="Delete">🗑</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
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
