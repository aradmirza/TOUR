<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (!isLoggedIn()) { header('Location: ../login.php'); exit; }

$userId      = currentUserId();
$groupFilter = (int)($_GET['group_id'] ?? 0);

// User's groups
$stmt = $db->prepare(
    "SELECT tg.id, tg.name FROM tour_groups tg
     JOIN group_members gm ON gm.group_id=tg.id AND gm.user_id=? ORDER BY tg.name"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$myGroups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$groupIds = array_column($myGroups, 'id');

// Handle create post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action   = $_POST['action']   ?? '';
    if ($action === 'create_post') {
        $content  = trim($_POST['content']  ?? '');
        $postGroup= (int)($_POST['group_id'] ?? 0);

        if (!$content) {
            flash("Post content can't be empty.", 'danger');
        } elseif ($postGroup && !in_array($postGroup, $groupIds)) {
            flash('Invalid group.', 'danger');
        } else {
            $image = null;
            if (!empty($_FILES['image']['name'])) {
                $res = uploadFile($_FILES['image'], UPLOAD_POSTS, 'post');
                if ($res['success']) $image = $res['filename'];
                else { flash($res['error'], 'danger'); header('Location: feed.php' . ($groupFilter?'?group_id='.$groupFilter:'')); exit; }
            }
            $gv = $postGroup ?: null;
            $vis = $gv ? 'group' : 'public';
            $stmt = $db->prepare(
                "INSERT INTO posts (user_id, group_id, content, image, visibility) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("iisss", $userId, $gv, $content, $image, $vis);
            $stmt->execute();
            flash('Post shared!', 'success');
        }
        header('Location: feed.php' . ($groupFilter ? '?group_id='.$groupFilter : '')); exit;
    } elseif ($action === 'delete_post') {
        $postId = (int)($_POST['post_id'] ?? 0);
        if ($postId) {
            $stmt = $db->prepare("SELECT user_id FROM posts WHERE id=?");
            $stmt->bind_param("i", $postId);
            $stmt->execute();
            $p = $stmt->get_result()->fetch_assoc();
            if ($p && $p['user_id'] == $userId) {
                $stmt = $db->prepare("UPDATE posts SET status='deleted' WHERE id=?");
                $stmt->bind_param("i", $postId);
                $stmt->execute();
                flash('Post deleted.', 'success');
            }
        }
        header('Location: feed.php' . ($groupFilter ? '?group_id='.$groupFilter : '')); exit;
    }
}

// Fetch posts
if ($groupFilter && in_array($groupFilter, $groupIds)) {
    $stmt = $db->prepare(
        "SELECT p.*, u.name AS author_name, u.profile_photo AS author_photo,
                tg.name AS group_name,
                (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id=p.id) AS like_count,
                (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id=p.id AND pc.status='active') AS comment_count,
                (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id=p.id AND pl.user_id=?) AS i_liked
         FROM posts p JOIN users u ON u.id=p.user_id
         LEFT JOIN tour_groups tg ON tg.id=p.group_id
         WHERE p.group_id=? AND p.status='active'
         ORDER BY p.created_at DESC LIMIT 50"
    );
    $stmt->bind_param("ii", $userId, $groupFilter);
} elseif ($groupIds) {
    $in   = implode(',', array_map('intval', $groupIds));
    $stmt = $db->prepare(
        "SELECT p.*, u.name AS author_name, u.profile_photo AS author_photo,
                tg.name AS group_name,
                (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id=p.id) AS like_count,
                (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id=p.id AND pc.status='active') AS comment_count,
                (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id=p.id AND pl.user_id=?) AS i_liked
         FROM posts p JOIN users u ON u.id=p.user_id
         LEFT JOIN tour_groups tg ON tg.id=p.group_id
         WHERE (p.group_id IN ($in) OR p.user_id=?) AND p.status='active'
         ORDER BY p.created_at DESC LIMIT 50"
    );
    $stmt->bind_param("ii", $userId, $userId);
} else {
    $stmt = null;
}

$posts = [];
if ($stmt) { $stmt->execute(); $posts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); }

$pageTitle  = 'Social Feed';
$activePage = 'feed';
include __DIR__ . '/includes/user-header.php';
?>

<div style="max-width:640px;margin:0 auto;">

  <!-- Create Post -->
  <div class="card" style="margin-bottom:16px;padding:16px;">
    <form method="POST" enctype="multipart/form-data" id="postForm">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="create_post">
      <div style="display:flex;gap:10px;align-items:flex-start;">
        <?= uAvatar(currentUser()['name'], currentUser()['photo'], 40) ?>
        <div style="flex:1;">
          <textarea name="content" class="form-input" rows="2" placeholder="Share something about your trip…" style="resize:none;" id="postContent"></textarea>

          <div id="imagePreviewWrap" style="display:none;margin-top:8px;"></div>

          <div style="display:flex;align-items:center;gap:8px;margin-top:10px;flex-wrap:wrap;">
            <select name="group_id" class="form-input" style="flex:1;min-width:140px;">
              <option value="">Public Post</option>
              <?php foreach ($myGroups as $g): ?>
                <option value="<?= $g['id'] ?>" <?= $groupFilter==$g['id']?'selected':'' ?>><?= e($g['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <label class="btn btn-outline btn-sm" style="cursor:pointer;">
              📷 Photo
              <input type="file" name="image" accept="image/*" style="display:none;" id="postImageInput">
            </label>
            <button type="submit" class="btn btn-primary btn-sm">Post</button>
          </div>
        </div>
      </div>
    </form>
  </div>

  <!-- Filter by group -->
  <?php if ($myGroups): ?>
  <div style="display:flex;gap:6px;overflow-x:auto;padding-bottom:10px;margin-bottom:10px;scrollbar-width:none;">
    <a href="feed.php" class="btn btn-sm <?= !$groupFilter?'btn-primary':'btn-outline' ?>" style="white-space:nowrap;">All</a>
    <?php foreach ($myGroups as $g): ?>
      <a href="feed.php?group_id=<?= $g['id'] ?>"
         class="btn btn-sm <?= $groupFilter==$g['id']?'btn-primary':'btn-outline' ?>"
         style="white-space:nowrap;"><?= e($g['name']) ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Posts -->
  <?php if (!$posts): ?>
  <div class="card">
    <div class="empty-state" style="padding:40px;">
      <div class="empty-icon">📰</div>
      <div class="empty-title">No posts yet</div>
      <div class="empty-desc">Share your first tour memory!</div>
    </div>
  </div>
  <?php else: ?>
    <?php foreach ($posts as $post): ?>
    <div class="post-card" id="post-<?= $post['id'] ?>">
      <div class="post-header">
        <div class="post-author">
          <?= uAvatar($post['author_name'], $post['author_photo'], 40) ?>
          <div class="post-author-info">
            <div class="post-author-name"><?= e($post['author_name']) ?></div>
            <div class="post-meta">
              <?= timeAgo($post['created_at']) ?>
              <?php if ($post['group_name']): ?> · <?= e($post['group_name']) ?><?php endif; ?>
            </div>
          </div>
        </div>
        <?php if ($post['user_id'] == $userId): ?>
        <form method="POST" style="margin-left:auto;">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="delete_post">
          <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
          <button type="submit" class="btn btn-sm btn-danger" data-confirm="Delete this post?">🗑</button>
        </form>
        <?php endif; ?>
      </div>
      <div class="post-body"><?= nl2br(e($post['content'])) ?></div>
      <?php if ($post['image'] && file_exists(UPLOAD_POSTS . $post['image'])): ?>
        <img src="<?= uUrl('posts', $post['image']) ?>" class="post-image" alt=""
             style="cursor:pointer;" onclick="openImgModal('<?= uUrl('posts', $post['image']) ?>')">
      <?php endif; ?>
      <div class="post-actions">
        <button class="action-btn like-btn" data-post-id="<?= $post['id'] ?>"
                style="<?= $post['i_liked']?'color:var(--danger)':'' ?>">
          <?= $post['i_liked']?'❤️':'🤍' ?> <span class="like-count"><?= $post['like_count'] ?></span>
        </button>
        <button class="action-btn comment-toggle-btn" data-post-id="<?= $post['id'] ?>">
          💬 <span><?= $post['comment_count'] ?></span>
        </button>
      </div>

      <!-- Comments Section -->
      <div class="comment-section" id="comments-<?= $post['id'] ?>" style="display:none;border-top:1px solid var(--border);padding:12px 16px;">
        <div class="comments-list" id="comments-list-<?= $post['id'] ?>"></div>
        <div style="display:flex;gap:8px;margin-top:8px;">
          <?= uAvatar(currentUser()['name'], currentUser()['photo'], 30) ?>
          <input type="text" class="form-input comment-input" placeholder="Write a comment…"
                 data-post-id="<?= $post['id'] ?>" style="font-size:13px;">
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

</div>

<!-- Image Modal -->
<div class="img-modal-overlay" id="imgModal" onclick="this.classList.remove('open')">
  <button type="button" class="img-modal-close" onclick="document.getElementById('imgModal').classList.remove('open')">×</button>
  <img id="imgModalImg" src="" alt="">
</div>

<script>
function openImgModal(src) {
    document.getElementById('imgModalImg').src = src;
    document.getElementById('imgModal').classList.add('open');
}

// Image preview
document.getElementById('postImageInput')?.addEventListener('change', function() {
    const f = this.files[0];
    if (!f) return;
    const r = new FileReader();
    r.onload = e => {
        const w = document.getElementById('imagePreviewWrap');
        w.style.display = 'block';
        w.innerHTML = '<div style="position:relative;display:inline-block;"><img src="' + e.target.result + '" style="max-height:160px;border-radius:8px;">' +
            '<button type="button" onclick="clearImage()" style="position:absolute;top:4px;right:4px;background:rgba(0,0,0,0.6);color:#fff;border:none;border-radius:50%;width:24px;height:24px;cursor:pointer;font-size:14px;">×</button></div>';
    };
    r.readAsDataURL(f);
});

function clearImage() {
    document.getElementById('postImageInput').value = '';
    document.getElementById('imagePreviewWrap').style.display = 'none';
    document.getElementById('imagePreviewWrap').innerHTML = '';
}

// Like
document.querySelectorAll('.like-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const pid = this.dataset.postId;
        fetch('../api/like.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'post_id=' + pid + '&csrf_token=<?= csrfToken() ?>'
        }).then(r => r.json()).then(data => {
            if (data.success) {
                this.querySelector('.like-count').textContent = data.likes;
                this.innerHTML = (data.liked ? '❤️' : '🤍') + ' <span class="like-count">' + data.likes + '</span>';
                this.style.color = data.liked ? 'var(--danger)' : '';
            }
        });
    });
});

// Toggle comments
document.querySelectorAll('.comment-toggle-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const pid  = this.dataset.postId;
        const sec  = document.getElementById('comments-' + pid);
        const list = document.getElementById('comments-list-' + pid);
        if (sec.style.display === 'none') {
            sec.style.display = 'block';
            fetch('../api/comment.php?post_id=' + pid)
                .then(r => r.json())
                .then(data => {
                    list.innerHTML = data.map(c =>
                        '<div style="display:flex;gap:8px;margin-bottom:8px;font-size:13px;">' +
                        '<strong>' + c.author + '</strong> ' + c.content +
                        '<span style="color:var(--text-muted);font-size:11px;margin-left:auto;">' + c.time + '</span>' +
                        '</div>'
                    ).join('');
                });
        } else {
            sec.style.display = 'none';
        }
    });
});

// Add comment
document.querySelectorAll('.comment-input').forEach(input => {
    input.addEventListener('keydown', function(e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const pid     = this.dataset.postId;
        const content = this.value.trim();
        if (!content) return;
        fetch('../api/comment.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'post_id=' + pid + '&content=' + encodeURIComponent(content) + '&csrf_token=<?= csrfToken() ?>'
        }).then(r => r.json()).then(data => {
            if (data.success) {
                const list = document.getElementById('comments-list-' + pid);
                const safe = content.replace(/</g,'&lt;').replace(/>/g,'&gt;');
                list.innerHTML += '<div style="display:flex;gap:8px;margin-bottom:8px;font-size:13px;">' +
                    '<strong>You</strong> <span style="flex:1">' + safe + '</span>' +
                    '<span style="color:var(--text-muted);font-size:11px;white-space:nowrap;">just now</span></div>';
                this.value = '';
            }
        });
    });
});
</script>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
