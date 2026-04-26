<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

$userId   = currentUserId();
$groupFilter = (int)($_GET['group_id'] ?? 0);

// User's groups for dropdown
$stmt = $db->prepare(
    "SELECT tg.id, tg.name FROM tour_groups tg
     JOIN group_members gm ON gm.group_id=tg.id AND gm.user_id=?
     ORDER BY tg.name"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$myGroups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$groupIds = array_column($myGroups, 'id');

// --- Handle create post ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action  = $_POST['action'] ?? '';
    if ($action === 'create_post') {
        $content  = trim($_POST['content']  ?? '');
        $postGroup= (int)($_POST['group_id'] ?? 0);

        if (!$content) {
            flash("Post content can't be empty.", 'danger');
        } elseif ($postGroup && !in_array($postGroup, $groupIds)) {
            flash('Invalid group selected.', 'danger');
        } else {
            $image = null;
            if (!empty($_FILES['image']['name'])) {
                $res = uploadFile($_FILES['image'], UPLOAD_POSTS, 'post');
                if ($res['success']) $image = $res['filename'];
            }
            $groupVal = $postGroup ?: null;
            $stmt = $db->prepare(
                "INSERT INTO posts (user_id, group_id, content, image, visibility) VALUES (?, ?, ?, ?, 'group')"
            );
            $stmt->bind_param("iiss", $userId, $groupVal, $content, $image);
            $stmt->execute();
            flash('Post shared!', 'success');
        }
        $redir = 'feed.php' . ($groupFilter ? '?group_id=' . $groupFilter : '');
        header('Location: ' . $redir); exit;
    }
}

// Fetch posts
if ($groupFilter && in_array($groupFilter, $groupIds)) {
    $stmt = $db->prepare(
        "SELECT p.*, u.name AS author_name, u.profile_photo AS author_photo,
                tg.name AS group_name,
                (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id=p.id) AS like_count,
                (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id=p.id) AS comment_count,
                (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id=p.id AND pl.user_id=?) AS i_liked
         FROM posts p
         JOIN users u ON u.id=p.user_id
         LEFT JOIN tour_groups tg ON tg.id=p.group_id
         WHERE p.group_id=?
         ORDER BY p.created_at DESC LIMIT 50"
    );
    $stmt->bind_param("ii", $userId, $groupFilter);
} elseif ($groupIds) {
    $in = implode(',', array_map('intval', $groupIds));
    $stmt = $db->prepare(
        "SELECT p.*, u.name AS author_name, u.profile_photo AS author_photo,
                tg.name AS group_name,
                (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id=p.id) AS like_count,
                (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id=p.id) AS comment_count,
                (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id=p.id AND pl.user_id=?) AS i_liked
         FROM posts p
         JOIN users u ON u.id=p.user_id
         LEFT JOIN tour_groups tg ON tg.id=p.group_id
         WHERE p.group_id IN ($in)
         ORDER BY p.created_at DESC LIMIT 50"
    );
    $stmt->bind_param("i", $userId);
} else {
    $stmt = null;
}

$posts = [];
if ($stmt) {
    $stmt->execute();
    $posts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Fetch current group for hero if filtered
$currentGroup = null;
if ($groupFilter) {
    foreach ($myGroups as $g) {
        if ($g['id'] === $groupFilter) { $currentGroup = $g; break; }
    }
}

$pageTitle  = $currentGroup ? e($currentGroup['name']) . ' Feed' : 'Social Feed';
$activePage = 'feed';
$groupTab   = 'feed';
$groupId    = $groupFilter;  // For group_subnav
include 'includes/header.php';
?>

<?php if ($currentGroup): ?>
  <!-- Group header when in group-feed mode -->
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
      <h1>Social Feed</h1>
      <p>Posts from all your tour groups</p>
    </div>
  </div>
  <!-- Group filter tabs -->
  <?php if ($myGroups): ?>
  <div class="filter-row mb-3" style="overflow-x:auto;flex-wrap:nowrap;">
    <a href="feed.php" class="btn btn-sm <?= !$groupFilter?'btn-primary':'btn-outline' ?>">All Tours</a>
    <?php foreach ($myGroups as $g): ?>
      <a href="feed.php?group_id=<?= $g['id'] ?>"
         class="btn btn-sm <?= $groupFilter===$g['id']?'btn-primary':'btn-outline' ?>"
         style="white-space:nowrap;"><?= e($g['name']) ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
<?php endif; ?>

<div class="feed-layout">

  <!-- Post composer -->
  <div class="post-composer">
    <div class="composer-row">
      <?= avatarHtml(currentUser()['name'], currentUser()['photo'], 40) ?>
      <form method="POST" action="feed.php<?= $groupFilter?'?group_id='.$groupFilter:'' ?>"
            enctype="multipart/form-data" style="flex:1;" id="postForm">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create_post">
        <?php if ($groupFilter): ?>
          <input type="hidden" name="group_id" value="<?= $groupFilter ?>">
        <?php endif; ?>

        <textarea name="content" class="composer-input" id="postContent" rows="1"
                  placeholder="Share something about your tour... 🌍"
                  oninput="this.style.height='auto';this.style.height=this.scrollHeight+'px'"></textarea>

        <div id="postImagePreview" class="img-preview" style="display:none;margin-bottom:8px;"></div>

        <div class="composer-actions">
          <div class="composer-tools">
            <label class="composer-btn" style="cursor:pointer;">
              🖼️ Photo
              <input type="file" name="image" accept="image/*" style="display:none;"
                     onchange="previewImg(this,'postImagePreview')">
            </label>
            <?php if (!$groupFilter && $myGroups): ?>
              <select name="group_id" class="filter-select">
                <option value="">All / Public</option>
                <?php foreach ($myGroups as $g): ?>
                  <option value="<?= $g['id'] ?>"><?= e($g['name']) ?></option>
                <?php endforeach; ?>
              </select>
            <?php endif; ?>
          </div>
          <button type="submit" class="btn btn-primary btn-sm">Post</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Posts -->
  <?php if (!$posts): ?>
    <div class="card">
      <div class="empty-state">
        <div class="empty-icon">📰</div>
        <div class="empty-title">No posts yet</div>
        <div class="empty-desc">Be the first to share something about this tour!</div>
      </div>
    </div>
  <?php else: ?>
    <?php foreach ($posts as $post):
      // Load first 3 comments
      $stmtC = $db->prepare(
          "SELECT pc.*, u.name AS c_name, u.profile_photo AS c_photo
           FROM post_comments pc JOIN users u ON u.id=pc.user_id
           WHERE pc.post_id=? ORDER BY pc.created_at ASC LIMIT 3"
      );
      $stmtC->bind_param("i", $post['id']);
      $stmtC->execute();
      $comments = $stmtC->get_result()->fetch_all(MYSQLI_ASSOC);
    ?>
    <div class="post-card" id="post-<?= $post['id'] ?>">
      <div class="post-header">
        <div class="post-author">
          <?= avatarHtml($post['author_name'], $post['author_photo'], 40) ?>
          <div class="post-author-info">
            <div class="post-author-name"><?= e($post['author_name']) ?></div>
            <div class="post-meta">
              <?= timeAgo($post['created_at']) ?>
              <?php if ($post['group_name']): ?>
                · <a href="feed.php?group_id=<?= $post['group_id'] ?>" style="color:var(--primary);"><?= e($post['group_name']) ?></a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="post-body"><?= nl2br(e($post['content'])) ?></div>

      <?php if ($post['image']): ?>
        <img src="uploads/posts/<?= e($post['image']) ?>" class="post-image" alt=""
             onclick="openLightboxSingle(this.src)">
      <?php endif; ?>

      <!-- Like / Comment buttons -->
      <div class="post-actions">
        <button class="action-btn <?= $post['i_liked']?'liked':'' ?>"
                onclick="toggleLike(<?= $post['id'] ?>, this)">
          <?= $post['i_liked'] ? '❤️' : '🤍' ?>
          <span class="like-count"><?= $post['like_count'] ?> Like<?= $post['like_count']!=1?'s':'' ?></span>
        </button>
        <button class="action-btn" onclick="toggleComments(<?= $post['id'] ?>)">
          💬 <span><?= $post['comment_count'] ?> Comment<?= $post['comment_count']!=1?'s':'' ?></span>
        </button>
      </div>

      <!-- Comments section -->
      <div class="comments-section" id="comments-<?= $post['id'] ?>" style="display:none;">
        <div class="comments-list" id="comments-list-<?= $post['id'] ?>">
          <?php foreach ($comments as $c): ?>
          <div class="comment-item">
            <?= avatarHtml($c['c_name'], $c['c_photo'], 28) ?>
            <div class="comment-bubble">
              <div class="comment-author-name"><?= e($c['c_name']) ?></div>
              <div class="comment-text"><?= nl2br(e($c['content'])) ?></div>
              <div class="comment-time"><?= timeAgo($c['created_at']) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <form class="comment-form" onsubmit="submitComment(event, <?= $post['id'] ?>)">
          <?= avatarHtml(currentUser()['name'], currentUser()['photo'], 28) ?>
          <input type="text" class="comment-input" id="comment-input-<?= $post['id'] ?>"
                 placeholder="Write a comment..." autocomplete="off">
          <button type="submit" class="comment-submit">Send</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

</div>

<!-- Simple lightbox for post images -->
<div class="lightbox-overlay hidden" id="singleLightbox" onclick="this.classList.add('hidden')">
  <div class="lightbox-inner">
    <button class="lightbox-close" onclick="document.getElementById('singleLightbox').classList.add('hidden')">✕</button>
    <img id="singleLightboxImg" src="" alt="">
  </div>
</div>

<script>
function openLightboxSingle(src) {
  document.getElementById('singleLightboxImg').src = src;
  document.getElementById('singleLightbox').classList.remove('hidden');
}

function toggleLike(postId, btn) {
  fetch('api/like.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'post_id=' + postId + '&csrf_token=<?= csrfToken() ?>'
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      btn.classList.toggle('liked', data.liked);
      btn.querySelector('.like-count').textContent =
        data.count + (data.count !== 1 ? ' Likes' : ' Like');
      btn.querySelector('span:not(.like-count)') || (btn.innerHTML =
        (data.liked ? '❤️' : '🤍') + ' <span class="like-count">' + data.count + ' Like' + (data.count!==1?'s':'') + '</span>');
      // Update icon
      btn.innerHTML = (data.liked ? '❤️' : '🤍') +
        ' <span class="like-count">' + data.count + ' Like' + (data.count!==1?'s':'') + '</span>';
    }
  });
}

function toggleComments(postId) {
  const el = document.getElementById('comments-' + postId);
  el.style.display = el.style.display === 'none' ? 'block' : 'none';
  if (el.style.display === 'block') {
    document.getElementById('comment-input-' + postId).focus();
  }
}

function submitComment(e, postId) {
  e.preventDefault();
  const input = document.getElementById('comment-input-' + postId);
  const content = input.value.trim();
  if (!content) return;

  fetch('api/comment.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'post_id=' + postId + '&content=' + encodeURIComponent(content) + '&csrf_token=<?= csrfToken() ?>'
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      const list = document.getElementById('comments-list-' + postId);
      list.insertAdjacentHTML('beforeend', data.html);
      input.value = '';
    }
  });
}
</script>

<?php include 'includes/footer.php'; ?>
