<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (!isLoggedIn()) { header('Location: ../login.php'); exit; }

$userId      = currentUserId();
$groupFilter = (int)($_GET['id'] ?? 0);

// User's groups
$stmt = $db->prepare(
    "SELECT tg.id, tg.name FROM tour_groups tg
     JOIN group_members gm ON gm.group_id=tg.id AND gm.user_id=? ORDER BY tg.name"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$myGroups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$groupIds = array_column($myGroups, 'id');

// Validate group filter
if ($groupFilter && !in_array($groupFilter, $groupIds)) {
    $groupFilter = 0;
}

// Fetch photos
if ($groupFilter) {
    $stmt = $db->prepare(
        "SELECT p.id, p.image, p.content, p.created_at, u.name AS author_name, tg.name AS group_name
         FROM posts p JOIN users u ON u.id=p.user_id
         LEFT JOIN tour_groups tg ON tg.id=p.group_id
         WHERE p.group_id=? AND p.image IS NOT NULL AND p.status='active'
         ORDER BY p.created_at DESC"
    );
    $stmt->bind_param("i", $groupFilter);
} elseif ($groupIds) {
    $in   = implode(',', array_map('intval', $groupIds));
    $stmt = $db->prepare(
        "SELECT p.id, p.image, p.content, p.created_at, u.name AS author_name, tg.name AS group_name
         FROM posts p JOIN users u ON u.id=p.user_id
         LEFT JOIN tour_groups tg ON tg.id=p.group_id
         WHERE p.group_id IN ($in) AND p.image IS NOT NULL AND p.status='active'
         ORDER BY p.created_at DESC"
    );
} else {
    $stmt = null;
}

$photos = [];
if ($stmt) { $stmt->execute(); $photos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); }

// Fetch groups with cover photos
$stmt = $db->prepare(
    "SELECT tg.id, tg.name, tg.cover_photo FROM tour_groups tg
     JOIN group_members gm ON gm.group_id=tg.id AND gm.user_id=?
     WHERE tg.cover_photo IS NOT NULL ORDER BY tg.name"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$coverGroups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle  = $groupFilter ? 'Gallery' : 'Gallery';
$activePage = 'gallery';
include __DIR__ . '/includes/user-header.php';
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:10px;">
  <h2 style="font-size:16px;font-weight:800;">🖼️ Gallery <span style="color:var(--text-muted);font-size:13px;">(<?= count($photos) ?> photos)</span></h2>
  <a href="feed.php" class="btn btn-primary btn-sm">+ Share Photo</a>
</div>

<!-- Group Filter -->
<?php if ($myGroups): ?>
<div style="display:flex;gap:6px;overflow-x:auto;padding-bottom:10px;margin-bottom:14px;scrollbar-width:none;">
  <a href="gallery.php" class="btn btn-sm <?= !$groupFilter?'btn-primary':'btn-outline' ?>" style="white-space:nowrap;">All</a>
  <?php foreach ($myGroups as $g): ?>
    <a href="gallery.php?id=<?= $g['id'] ?>"
       class="btn btn-sm <?= $groupFilter==$g['id']?'btn-primary':'btn-outline' ?>"
       style="white-space:nowrap;"><?= e($g['name']) ?></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Cover Photos Section -->
<?php if ($coverGroups): ?>
<div style="margin-bottom:20px;">
  <div style="font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">Group Covers</div>
  <div class="gallery-grid">
    <?php foreach ($coverGroups as $g): ?>
      <?php if ($groupFilter && $g['id'] != $groupFilter) continue; ?>
      <?php if (!file_exists(UPLOAD_GROUP . $g['cover_photo'])) continue; ?>
      <div class="gallery-item" onclick="openImgModal('<?= uUrl('group', $g['cover_photo']) ?>', '<?= e(addslashes($g['name'])) ?>')">
        <img src="<?= uUrl('group', $g['cover_photo']) ?>" alt="<?= e($g['name']) ?>" loading="lazy">
        <div style="position:absolute;bottom:0;left:0;right:0;background:linear-gradient(transparent,rgba(0,0,0,0.7));padding:8px 6px 4px;color:#fff;font-size:10px;font-weight:600;">
          <?= e($g['name']) ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Post Photos -->
<?php if (!$photos): ?>
<div class="card">
  <div class="empty-state" style="padding:40px;">
    <div class="empty-icon">🖼️</div>
    <div class="empty-title">No photos yet</div>
    <div class="empty-desc">Share photos in your tour feed to see them here.</div>
    <a href="feed.php" class="btn btn-primary">Share Photo</a>
  </div>
</div>
<?php else: ?>
<div style="margin-bottom:8px;">
  <div style="font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">Post Photos</div>
  <div class="gallery-grid">
    <?php foreach ($photos as $p): ?>
      <?php if (!file_exists(UPLOAD_POSTS . $p['image'])) continue; ?>
      <div class="gallery-item"
           onclick="openImgModal('<?= uUrl('posts', $p['image']) ?>', '<?= e(addslashes($p['content'])) ?>')">
        <img src="<?= uUrl('posts', $p['image']) ?>" alt="" loading="lazy">
        <div style="position:absolute;inset:0;background:rgba(0,0,0,0);transition:background 0.2s;"
             onmouseover="this.style.background='rgba(0,0,0,0.3)'"
             onmouseout="this.style.background='rgba(0,0,0,0)'">
          <div style="position:absolute;bottom:0;left:0;right:0;background:linear-gradient(transparent,rgba(0,0,0,0.6));padding:20px 6px 4px;color:#fff;font-size:10px;">
            <?= e($p['author_name']) ?> · <?= date('M j', strtotime($p['created_at'])) ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Image Modal -->
<div class="img-modal-overlay" id="imgModal">
  <button type="button" class="img-modal-close" onclick="document.getElementById('imgModal').classList.remove('open')">×</button>
  <div style="display:flex;flex-direction:column;align-items:center;gap:12px;max-width:92vw;">
    <img id="imgModalImg" src="" alt="">
    <div id="imgModalCaption" style="color:#fff;font-size:13px;text-align:center;opacity:0.8;max-width:500px;"></div>
  </div>
</div>

<script>
function openImgModal(src, caption) {
    document.getElementById('imgModalImg').src = src;
    document.getElementById('imgModalCaption').textContent = caption || '';
    document.getElementById('imgModal').classList.add('open');
}
document.getElementById('imgModal').addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('open');
});
</script>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
