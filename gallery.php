<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

$userId      = currentUserId();
$groupFilter = (int)($_GET['group_id'] ?? 0);

// User's groups
$stmt = $db->prepare(
    "SELECT tg.id, tg.name FROM tour_groups tg
     JOIN group_members gm ON gm.group_id=tg.id AND gm.user_id=?
     ORDER BY tg.name"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$myGroups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$groupIds = array_column($myGroups, 'id');

// Current group for sub-nav
$currentGroup = null;
$groupId      = $groupFilter;
if ($groupFilter) {
    foreach ($myGroups as $g) {
        if ($g['id'] === $groupFilter) { $currentGroup = $g; break; }
    }
}

// Fetch photos
if ($groupFilter && in_array($groupFilter, $groupIds)) {
    $stmt = $db->prepare(
        "SELECT p.id, p.image, p.content, p.created_at, p.group_id,
                u.name AS author_name, u.profile_photo AS author_photo
         FROM posts p JOIN users u ON u.id=p.user_id
         WHERE p.group_id=? AND p.image IS NOT NULL
         ORDER BY p.created_at DESC"
    );
    $stmt->bind_param("i", $groupFilter);
} elseif ($groupIds) {
    $in = implode(',', array_map('intval', $groupIds));
    $stmt = $db->prepare(
        "SELECT p.id, p.image, p.content, p.created_at, p.group_id,
                u.name AS author_name, u.profile_photo AS author_photo,
                tg.name AS group_name
         FROM posts p
         JOIN users u ON u.id=p.user_id
         LEFT JOIN tour_groups tg ON tg.id=p.group_id
         WHERE p.group_id IN ($in) AND p.image IS NOT NULL
         ORDER BY p.created_at DESC"
    );
    $stmt->bind_param("");
    // Rebuild without params since IN is already baked in
    $res = $db->query(
        "SELECT p.id, p.image, p.content, p.created_at, p.group_id,
                u.name AS author_name, u.profile_photo AS author_photo,
                tg.name AS group_name
         FROM posts p
         JOIN users u ON u.id=p.user_id
         LEFT JOIN tour_groups tg ON tg.id=p.group_id
         WHERE p.group_id IN ($in) AND p.image IS NOT NULL
         ORDER BY p.created_at DESC"
    );
    $photos = $res->fetch_all(MYSQLI_ASSOC);
    $stmt   = null;
} else {
    $stmt = null;
}

if (isset($stmt) && $stmt) {
    $stmt->execute();
    $photos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} elseif (!isset($photos)) {
    $photos = [];
}

$pageTitle  = $currentGroup ? e($currentGroup['name']) . ' Gallery' : 'Tour Gallery';
$activePage = 'feed';
$groupTab   = 'gallery';
include 'includes/header.php';
?>

<?php if ($currentGroup): ?>
  <?php
  $stmt2 = $db->prepare("SELECT * FROM tour_groups WHERE id=?");
  $stmt2->bind_param("i", $groupFilter);
  $stmt2->execute();
  $groupFull = $stmt2->get_result()->fetch_assoc();
  ?>
  <div class="group-hero">
    <?php if ($groupFull['cover_photo'] && file_exists(UPLOAD_GROUP . $groupFull['cover_photo'])): ?>
      <img src="uploads/group/<?= e($groupFull['cover_photo']) ?>" alt="">
    <?php else: ?>
      <div class="group-hero-gradient"></div>
    <?php endif; ?>
    <div class="group-hero-overlay">
      <div class="group-hero-title"><?= e($groupFull['name']) ?></div>
      <div class="group-hero-sub">📍 <?= e($groupFull['destination']) ?></div>
    </div>
  </div>
  <?php include 'includes/group_subnav.php'; ?>
<?php else: ?>
  <div class="page-header">
    <div>
      <h1>Tour Gallery</h1>
      <p><?= count($photos) ?> photos from all your tours</p>
    </div>
  </div>
  <?php if ($myGroups): ?>
  <div class="filter-row mb-3" style="overflow-x:auto;flex-wrap:nowrap;">
    <a href="gallery.php" class="btn btn-sm <?= !$groupFilter?'btn-primary':'btn-outline' ?>">All Tours</a>
    <?php foreach ($myGroups as $g): ?>
      <a href="gallery.php?group_id=<?= $g['id'] ?>"
         class="btn btn-sm <?= $groupFilter===$g['id']?'btn-primary':'btn-outline' ?>"
         style="white-space:nowrap;"><?= e($g['name']) ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
<?php endif; ?>

<?php if (!$photos): ?>
  <div class="card">
    <div class="empty-state">
      <div class="empty-icon">🖼️</div>
      <div class="empty-title">No photos yet</div>
      <div class="empty-desc">Share posts with photos in the feed to build your gallery.</div>
      <a href="feed.php<?= $groupFilter?'?group_id='.$groupFilter:'' ?>" class="btn btn-primary">Go to Feed</a>
    </div>
  </div>
<?php else: ?>
  <div class="gallery-grid" id="gallery">
    <?php foreach ($photos as $i => $photo): ?>
    <div class="gallery-item" onclick="openLightbox(<?= $i ?>)">
      <img src="uploads/posts/<?= e($photo['image']) ?>"
           alt="<?= e($photo['author_name']) ?>"
           loading="lazy">
      <div class="gallery-overlay">
        <span class="gallery-zoom">🔍</span>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Lightbox -->
<div class="lightbox-overlay hidden" id="lightbox">
  <div class="lightbox-inner">
    <button class="lightbox-close" id="lbClose">✕</button>
    <button class="lightbox-nav lightbox-prev" id="lbPrev">&#8249;</button>
    <img id="lbImg" src="" alt="">
    <button class="lightbox-nav lightbox-next" id="lbNext">&#8250;</button>
  </div>
  <div style="position:absolute;bottom:20px;left:50%;transform:translateX(-50%);color:rgba(255,255,255,0.7);font-size:13px;text-align:center;pointer-events:none;" id="lbCaption"></div>
</div>

<script>
const photos = <?= json_encode(array_map(fn($p) => [
    'src'     => 'uploads/posts/' . $p['image'],
    'author'  => $p['author_name'],
    'time'    => $p['created_at'],
], $photos)) ?>;

let lbIdx = 0;

function openLightbox(idx) {
  lbIdx = idx;
  renderLightbox();
  document.getElementById('lightbox').classList.remove('hidden');
}

function renderLightbox() {
  const p = photos[lbIdx];
  document.getElementById('lbImg').src = p.src;
  document.getElementById('lbCaption').textContent = p.author + ' · ' + (lbIdx+1) + '/' + photos.length;
}

document.getElementById('lbClose').onclick = () => document.getElementById('lightbox').classList.add('hidden');
document.getElementById('lbPrev').onclick  = (e) => { e.stopPropagation(); lbIdx = (lbIdx - 1 + photos.length) % photos.length; renderLightbox(); };
document.getElementById('lbNext').onclick  = (e) => { e.stopPropagation(); lbIdx = (lbIdx + 1) % photos.length; renderLightbox(); };

document.getElementById('lightbox').onclick = function(e) {
  if (e.target === this) this.classList.add('hidden');
};

document.addEventListener('keydown', e => {
  if (document.getElementById('lightbox').classList.contains('hidden')) return;
  if (e.key === 'Escape') document.getElementById('lightbox').classList.add('hidden');
  if (e.key === 'ArrowLeft')  { lbIdx = (lbIdx-1+photos.length)%photos.length; renderLightbox(); }
  if (e.key === 'ArrowRight') { lbIdx = (lbIdx+1)%photos.length; renderLightbox(); }
});
</script>

<?php include 'includes/footer.php'; ?>
