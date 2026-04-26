<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/admin-auth.php';
requireAdmin();

// POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyAdminCsrf()) { adminFlash('Invalid request.', 'danger'); header('Location: posts.php'); exit; }

    $action    = $_POST['action']     ?? '';
    $postId    = (int)($_POST['post_id']    ?? 0);
    $commentId = (int)($_POST['comment_id'] ?? 0);

    if ($action === 'delete_post' && $postId) {
        $stmt = $db->prepare("UPDATE posts SET status='deleted' WHERE id=?");
        $stmt->bind_param("i", $postId);
        $stmt->execute();
        adminFlash('Post removed.', 'success');
    } elseif ($action === 'delete_comment' && $commentId) {
        $stmt = $db->prepare("UPDATE post_comments SET status='deleted' WHERE id=?");
        $stmt->bind_param("i", $commentId);
        $stmt->execute();
        adminFlash('Comment removed.', 'success');
    }
    header('Location: posts.php?' . http_build_query($_GET)); exit;
}

// Filters
$search  = trim($_GET['q']       ?? '');
$groupId = (int)($_GET['group_id'] ?? 0);
$tab     = $_GET['tab']          ?? 'posts'; // posts | comments
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

// --- Posts ---
$where  = "p.status = 'active'";
$params = [];
$types  = '';
if ($search) {
    $like = '%' . $search . '%';
    $where .= " AND p.content LIKE ?";
    $params[] = $like; $types .= 's';
}
if ($groupId) {
    $where .= " AND p.group_id = ?";
    $params[] = $groupId; $types .= 'i';
}

$countStmt = $db->prepare("SELECT COUNT(*) FROM posts p WHERE $where");
if ($params) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalPosts = $countStmt->get_result()->fetch_row()[0];
$totalPostPages = max(1, ceil($totalPosts / $perPage));

$stmt = $db->prepare(
    "SELECT p.*, u.name AS author_name, tg.name AS group_name,
            (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id) AS likes,
            (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id = p.id AND pc.status='active') AS comments
     FROM posts p
     JOIN users u ON u.id = p.user_id
     LEFT JOIN tour_groups tg ON tg.id = p.group_id
     WHERE $where ORDER BY p.created_at DESC LIMIT ? OFFSET ?"
);
$allParams = array_merge($params, [$perPage, $offset]);
$stmt->bind_param($types . 'ii', ...$allParams);
$stmt->execute();
$posts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// --- Comments ---
$cWhere  = "pc.status = 'active'";
$cParams = [];
$cTypes  = '';
if ($search) {
    $like = '%' . $search . '%';
    $cWhere .= " AND pc.content LIKE ?";
    $cParams[] = $like; $cTypes .= 's';
}
$cCountStmt = $db->prepare("SELECT COUNT(*) FROM post_comments pc WHERE $cWhere");
if ($cParams) $cCountStmt->bind_param($cTypes, ...$cParams);
$cCountStmt->execute();
$totalComments = $cCountStmt->get_result()->fetch_row()[0];
$totalCommentPages = max(1, ceil($totalComments / $perPage));

$cStmt = $db->prepare(
    "SELECT pc.*, u.name AS author_name, p.content AS post_preview
     FROM post_comments pc
     JOIN users u ON u.id = pc.user_id
     JOIN posts p ON p.id = pc.post_id
     WHERE $cWhere ORDER BY pc.created_at DESC LIMIT ? OFFSET ?"
);
$cAllParams = array_merge($cParams, [$perPage, $offset]);
$cStmt->bind_param($cTypes . 'ii', ...$cAllParams);
$cStmt->execute();
$comments = $cStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$allGroups = $db->query("SELECT id, name FROM tour_groups ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$pageTitle  = 'Posts & Comments';
$activePage = 'posts';
include __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-page-header">
  <h2>Posts & Comments</h2>
</div>

<!-- Tabs -->
<div class="admin-tabs">
  <a href="?tab=posts&q=<?= urlencode($search) ?>&group_id=<?= $groupId ?>"
     class="admin-tab <?= $tab==='posts'?'active':'' ?>">📰 Posts (<?= $totalPosts ?>)</a>
  <a href="?tab=comments&q=<?= urlencode($search) ?>"
     class="admin-tab <?= $tab==='comments'?'active':'' ?>">💬 Comments (<?= $totalComments ?>)</a>
</div>

<!-- Search -->
<form method="GET" class="admin-filter-bar">
  <input type="hidden" name="tab" value="<?= e($tab) ?>">
  <input type="text" name="q" class="admin-input admin-search-input"
         placeholder="Search content…" value="<?= e($search) ?>">
  <?php if ($tab === 'posts'): ?>
  <select name="group_id" class="admin-select">
    <option value="">All Groups</option>
    <?php foreach ($allGroups as $g): ?>
      <option value="<?= $g['id'] ?>" <?= $groupId===$g['id']?'selected':'' ?>><?= e($g['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <?php endif; ?>
  <button type="submit" class="btn-admin btn-admin-primary">Search</button>
  <a href="?tab=<?= $tab ?>" class="btn-admin btn-admin-outline">Clear</a>
</form>

<?php if ($tab === 'posts'): ?>
<!-- Posts Table -->
<div class="admin-card">
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr><th>Author</th><th>Content</th><th>Group</th><th>Image</th><th>Likes</th><th>Comments</th><th>Date</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php if (!$posts): ?>
          <tr><td colspan="8" class="text-center text-muted" style="padding:40px">No posts found.</td></tr>
        <?php endif; ?>
        <?php foreach ($posts as $post): ?>
        <tr>
          <td><?= e($post['author_name']) ?></td>
          <td><?= e(mb_substr($post['content'], 0, 80)) ?><?= mb_strlen($post['content'])>80?'…':'' ?></td>
          <td class="text-muted"><?= e($post['group_name'] ?? 'Public') ?></td>
          <td>
            <?php if ($post['image'] && file_exists(__DIR__ . '/../uploads/posts/' . $post['image'])): ?>
              <a href="<?= uploadUrl('posts', $post['image']) ?>" target="_blank">
                <img src="<?= uploadUrl('posts', $post['image']) ?>" style="width:48px;height:48px;object-fit:cover;border-radius:6px" alt="">
              </a>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td>❤️ <?= $post['likes'] ?></td>
          <td>💬 <?= $post['comments'] ?></td>
          <td class="text-muted"><?= timeAgo($post['created_at']) ?></td>
          <td>
            <form method="POST" style="display:inline">
              <?= adminCsrfField() ?>
              <input type="hidden" name="action"  value="delete_post">
              <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
              <button type="submit" class="btn-admin btn-admin-sm btn-admin-danger"
                      data-confirm="Remove this post?">🗑 Remove</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($totalPostPages > 1): ?>
  <div class="admin-pagination">
    <?php for ($i = 1; $i <= $totalPostPages; $i++): ?>
      <a href="?tab=posts&q=<?= urlencode($search) ?>&group_id=<?= $groupId ?>&page=<?= $i ?>"
         class="admin-page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<?php else: ?>
<!-- Comments Table -->
<div class="admin-card">
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr><th>Author</th><th>Comment</th><th>On Post</th><th>Date</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php if (!$comments): ?>
          <tr><td colspan="5" class="text-center text-muted" style="padding:40px">No comments found.</td></tr>
        <?php endif; ?>
        <?php foreach ($comments as $comment): ?>
        <tr>
          <td><?= e($comment['author_name']) ?></td>
          <td><?= e(mb_substr($comment['content'], 0, 80)) ?>…</td>
          <td class="text-muted"><?= e(mb_substr($comment['post_preview'], 0, 50)) ?>…</td>
          <td class="text-muted"><?= timeAgo($comment['created_at']) ?></td>
          <td>
            <form method="POST" style="display:inline">
              <?= adminCsrfField() ?>
              <input type="hidden" name="action"     value="delete_comment">
              <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
              <button type="submit" class="btn-admin btn-admin-sm btn-admin-danger"
                      data-confirm="Remove this comment?">🗑</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($totalCommentPages > 1): ?>
  <div class="admin-pagination">
    <?php for ($i = 1; $i <= $totalCommentPages; $i++): ?>
      <a href="?tab=comments&q=<?= urlencode($search) ?>&page=<?= $i ?>"
         class="admin-page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
