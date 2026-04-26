<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/admin-auth.php';
requireAdmin();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyAdminCsrf()) { adminFlash('Invalid request.', 'danger'); header('Location: comments.php'); exit; }

    $action    = $_POST['action']     ?? '';
    $commentId = (int)($_POST['comment_id'] ?? 0);

    if ($action === 'delete' && $commentId) {
        $stmt = $db->prepare("UPDATE post_comments SET status='deleted' WHERE id=?");
        $stmt->bind_param("i", $commentId);
        $stmt->execute();
        adminFlash('Comment deleted.', 'success');
    } elseif ($action === 'restore' && $commentId) {
        $stmt = $db->prepare("UPDATE post_comments SET status='active' WHERE id=?");
        $stmt->bind_param("i", $commentId);
        $stmt->execute();
        adminFlash('Comment restored.', 'success');
    } elseif ($action === 'hard_delete' && $commentId) {
        $stmt = $db->prepare("DELETE FROM post_comments WHERE id=?");
        $stmt->bind_param("i", $commentId);
        $stmt->execute();
        adminFlash('Comment permanently deleted.', 'success');
    }
    header('Location: comments.php' . ($_GET ? '?' . http_build_query($_GET) : '')); exit;
}

// Filters
$search  = trim($_GET['q']      ?? '');
$filter  = $_GET['status']      ?? '';
$postId  = (int)($_GET['post_id'] ?? 0);
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;

$where  = '1=1';
$params = [];
$types  = '';

if ($search) {
    $like = '%' . $search . '%';
    $where .= " AND (pc.content LIKE ? OR u.name LIKE ?)";
    $params = array_merge($params, [$like, $like]);
    $types .= 'ss';
}
if ($filter === 'active' || $filter === 'deleted') {
    $where .= " AND pc.status=?";
    $params[] = $filter; $types .= 's';
}
if ($postId) {
    $where .= " AND pc.post_id=?";
    $params[] = $postId; $types .= 'i';
}

// Count
$countStmt = $db->prepare("SELECT COUNT(*) FROM post_comments pc JOIN users u ON u.id=pc.user_id WHERE $where");
if ($params) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$total      = $countStmt->get_result()->fetch_row()[0];
$totalPages = max(1, ceil($total / $perPage));

// Fetch
$stmt = $db->prepare(
    "SELECT pc.*, u.name AS author_name, u.email AS author_email,
            p.content AS post_content, p.id AS pid
     FROM post_comments pc
     JOIN users u ON u.id=pc.user_id
     JOIN posts p ON p.id=pc.post_id
     WHERE $where
     ORDER BY pc.created_at DESC LIMIT ? OFFSET ?"
);
$allParams = array_merge($params, [$perPage, $offset]);
$allTypes  = $types . 'ii';
$stmt->bind_param($allTypes, ...$allParams);
$stmt->execute();
$comments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle  = 'Comment Management';
$activePage = 'posts';
include __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-page-header">
  <div class="admin-page-header-left">
    <a href="posts.php" class="admin-back-link">← Posts</a>
    <h2>Comments <span class="admin-count-badge"><?= $total ?></span></h2>
  </div>
</div>

<!-- Filter Bar -->
<form method="GET" class="admin-filter-bar">
  <input type="text" name="q" class="admin-input admin-search-input"
         placeholder="Search comment or author…" value="<?= e($search) ?>">
  <select name="status" class="admin-select">
    <option value="">All Status</option>
    <option value="active"  <?= $filter==='active'?'selected':'' ?>>Active</option>
    <option value="deleted" <?= $filter==='deleted'?'selected':'' ?>>Deleted</option>
  </select>
  <?php if ($postId): ?>
    <input type="hidden" name="post_id" value="<?= $postId ?>">
    <span style="font-size:12px;color:var(--a-muted);">Filtered by post #<?= $postId ?></span>
    <a href="comments.php" class="btn-admin btn-admin-sm btn-admin-outline">Clear</a>
  <?php endif; ?>
  <button type="submit" class="btn-admin btn-admin-primary">Search</button>
  <?php if ($search || $filter): ?>
    <a href="comments.php<?= $postId ? '?post_id='.$postId : '' ?>" class="btn-admin btn-admin-outline">Clear</a>
  <?php endif; ?>
</form>

<div class="admin-card">
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Comment</th>
          <th>Author</th>
          <th>On Post</th>
          <th>Status</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$comments): ?>
          <tr><td colspan="7" class="text-center text-muted" style="padding:40px;">No comments found.</td></tr>
        <?php endif; ?>
        <?php foreach ($comments as $c): ?>
        <tr>
          <td class="text-muted"><?= $c['id'] ?></td>
          <td style="max-width:300px;">
            <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px;">
              <?= e(mb_strimwidth($c['content'], 0, 100, '…')) ?>
            </div>
          </td>
          <td>
            <div style="font-weight:600;font-size:13px;"><?= e($c['author_name']) ?></div>
            <div style="font-size:11px;color:var(--a-muted);"><?= e($c['author_email']) ?></div>
          </td>
          <td>
            <a href="posts.php?view=<?= $c['pid'] ?>" class="admin-link" style="font-size:12px;">
              <?= e(mb_strimwidth($c['post_content'], 0, 50, '…')) ?>
            </a>
          </td>
          <td>
            <span class="admin-badge admin-badge-<?= $c['status']==='active'?'success':'danger' ?>">
              <?= ucfirst($c['status']) ?>
            </span>
          </td>
          <td class="text-muted"><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
          <td>
            <div class="admin-action-btns">
              <?php if ($c['status'] === 'active'): ?>
              <form method="POST" style="display:inline;">
                <?= adminCsrfField() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="comment_id" value="<?= $c['id'] ?>">
                <button type="submit" class="btn-admin btn-admin-sm btn-admin-warning"
                        data-confirm="Delete this comment?">🗑 Delete</button>
              </form>
              <?php else: ?>
              <form method="POST" style="display:inline;">
                <?= adminCsrfField() ?>
                <input type="hidden" name="action" value="restore">
                <input type="hidden" name="comment_id" value="<?= $c['id'] ?>">
                <button type="submit" class="btn-admin btn-admin-sm btn-admin-success">↩ Restore</button>
              </form>
              <form method="POST" style="display:inline;">
                <?= adminCsrfField() ?>
                <input type="hidden" name="action" value="hard_delete">
                <input type="hidden" name="comment_id" value="<?= $c['id'] ?>">
                <button type="submit" class="btn-admin btn-admin-sm btn-admin-danger"
                        data-confirm="Permanently delete? Cannot undo.">🗑 Purge</button>
              </form>
              <?php endif; ?>
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
      <a href="?q=<?= urlencode($search) ?>&status=<?= urlencode($filter) ?><?= $postId?'&post_id='.$postId:'' ?>&page=<?= $i ?>"
         class="admin-page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
