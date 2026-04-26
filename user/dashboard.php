<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (!isLoggedIn()) { header('Location: ../login.php'); exit; }

$userId = currentUserId();

// My tour groups
$stmt = $db->prepare(
    "SELECT tg.*, gm.role,
            (SELECT COUNT(*) FROM group_members gm2 WHERE gm2.group_id = tg.id) AS member_count,
            (SELECT COALESCE(SUM(amount),0) FROM expenses e WHERE e.group_id = tg.id) AS total_expense
     FROM tour_groups tg
     JOIN group_members gm ON gm.group_id = tg.id AND gm.user_id = ?
     ORDER BY tg.start_date ASC"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$groups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Next upcoming / running tour
$nextTour = null;
foreach ($groups as $g) {
    $s = getTourStatus($g['start_date'], $g['return_date']);
    if ($s === 'upcoming' || $s === 'running') { $nextTour = $g; break; }
}

// User stats
$stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE paid_by = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$totalPaid = $stmt->get_result()->fetch_row()[0];

$stmt = $db->prepare(
    "SELECT COALESCE(SUM(es.amount),0) FROM expense_splits es
     JOIN expenses e ON e.id = es.expense_id WHERE es.user_id = ?"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$totalShare = $stmt->get_result()->fetch_row()[0];
$totalDue   = max(0, $totalShare - $totalPaid);

// Recent posts
$groupIds    = array_column($groups, 'id');
$recentPosts = [];
if ($groupIds) {
    $in     = implode(',', array_map('intval', $groupIds));
    $result = $db->query(
        "SELECT p.*, u.name AS author_name, u.profile_photo AS author_photo, tg.name AS group_name,
                (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id=p.id) AS like_count,
                (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id=p.id) AS comment_count
         FROM posts p
         JOIN users u ON u.id=p.user_id
         LEFT JOIN tour_groups tg ON tg.id=p.group_id
         WHERE p.group_id IN ($in) AND p.status='active'
         ORDER BY p.created_at DESC LIMIT 5"
    );
    $recentPosts = $result->fetch_all(MYSQLI_ASSOC);
}

// Recent notifications
$stmt = $db->prepare(
    "SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 4"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$recentNotifs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
include __DIR__ . '/includes/user-header.php';
?>

<!-- Hero Countdown -->
<?php if ($nextTour): ?>
<?php $status = getTourStatus($nextTour['start_date'], $nextTour['return_date']); ?>
<div class="hero-countdown">
  <?php if ($nextTour['cover_photo'] && file_exists(UPLOAD_GROUP . $nextTour['cover_photo'])): ?>
    <div class="hero-countdown-img" style="background-image:url('<?= uUrl('group', $nextTour['cover_photo']) ?>')"></div>
    <div class="hero-countdown-overlay"></div>
  <?php else: ?>
    <div class="hero-countdown-bg"></div>
    <div class="hero-countdown-overlay"></div>
  <?php endif; ?>
  <div class="hero-countdown-content">
    <div class="hero-badge"><?= $status === 'running' ? '🟢 TOUR IS RUNNING' : '⏳ UPCOMING TOUR' ?></div>
    <div class="hero-title"><?= e($nextTour['name']) ?></div>
    <div class="hero-dest">📍 <?= e($nextTour['destination']) ?></div>
    <?php if ($status === 'upcoming'): ?>
      <div class="countdown-widget" id="countdown" data-start="<?= e($nextTour['start_date']) ?>">
        <div class="cd-box"><span class="cd-value" id="cd-days">--</span><span class="cd-label">Days</span></div>
        <div class="cd-sep">:</div>
        <div class="cd-box"><span class="cd-value" id="cd-hours">--</span><span class="cd-label">Hrs</span></div>
        <div class="cd-sep">:</div>
        <div class="cd-box"><span class="cd-value" id="cd-mins">--</span><span class="cd-label">Min</span></div>
        <div class="cd-sep">:</div>
        <div class="cd-box"><span class="cd-value" id="cd-secs">--</span><span class="cd-label">Sec</span></div>
      </div>
    <?php else: ?>
      <div class="countdown-status countdown-running">🟢 Tour in progress</div>
    <?php endif; ?>
    <div style="margin-top:16px;">
      <a href="group.php?id=<?= $nextTour['id'] ?>" class="btn btn-outline" style="color:#fff;border-color:rgba(255,255,255,0.5)">View Group →</a>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Stats Row -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon">✈️</div>
    <div class="stat-value"><?= count($groups) ?></div>
    <div class="stat-label">My Tours</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">💸</div>
    <div class="stat-value"><?= formatMoney($totalPaid) ?></div>
    <div class="stat-label">Total Paid</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">📊</div>
    <div class="stat-value"><?= formatMoney($totalShare) ?></div>
    <div class="stat-label">My Share</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">⚖️</div>
    <div class="stat-value" style="color:<?= $totalDue > 0 ? 'var(--danger)' : 'var(--success)' ?>">
      <?= formatMoney($totalDue) ?>
    </div>
    <div class="stat-label">Amount Due</div>
  </div>
</div>

<div class="grid-2">

  <!-- My Tour Groups -->
  <div>
    <div class="flex-between mb-3">
      <h2 style="font-size:15px;font-weight:700;">My Tour Groups</h2>
      <a href="create-group.php" class="btn btn-primary btn-sm">+ New Tour</a>
    </div>

    <?php if (!$groups): ?>
    <div class="card">
      <div class="empty-state">
        <div class="empty-icon">✈️</div>
        <div class="empty-title">No tours yet</div>
        <div class="empty-desc">Create your first tour group and start planning!</div>
        <a href="create-group.php" class="btn btn-primary">Create Tour Group</a>
      </div>
    </div>
    <?php else: ?>
      <?php foreach ($groups as $g):
        $s = getTourStatus($g['start_date'], $g['return_date']); ?>
      <a href="group.php?id=<?= $g['id'] ?>" class="group-card" style="display:block;margin-bottom:12px;text-decoration:none;">
        <div class="group-card-cover">
          <?php if ($g['cover_photo'] && file_exists(UPLOAD_GROUP . $g['cover_photo'])): ?>
            <img src="<?= uUrl('group', $g['cover_photo']) ?>" alt="">
          <?php else: ?>
            <div class="group-hero-gradient"></div>
          <?php endif; ?>
          <div class="group-card-overlay"></div>
          <div class="group-card-status">
            <span class="badge badge-<?= $s==='upcoming'?'blue':($s==='running'?'green':'gray') ?>"><?= ucfirst($s) ?></span>
          </div>
        </div>
        <div class="group-card-body">
          <div class="group-card-name"><?= e($g['name']) ?></div>
          <div class="group-card-dest">📍 <?= e($g['destination']) ?></div>
          <div class="group-card-dates">📅 <?= date('M j', strtotime($g['start_date'])) ?> – <?= date('M j, Y', strtotime($g['return_date'])) ?></div>
        </div>
        <div class="group-card-footer">
          <span class="text-muted text-small">👥 <?= $g['member_count'] ?> members</span>
          <span class="text-muted text-small">💸 <?= formatMoney($g['total_expense']) ?></span>
        </div>
      </a>
      <?php endforeach; ?>
      <a href="groups.php" class="btn btn-outline btn-block">View All Tours</a>
    <?php endif; ?>
  </div>

  <!-- Recent Feed & Notifications -->
  <div>
    <div class="flex-between mb-3">
      <h2 style="font-size:15px;font-weight:700;">Recent Posts</h2>
      <a href="feed.php" class="btn btn-outline btn-sm">View Feed</a>
    </div>

    <?php if (!$recentPosts): ?>
    <div class="card">
      <div class="empty-state">
        <div class="empty-icon">📰</div>
        <div class="empty-title">No posts yet</div>
        <div class="empty-desc">Posts from your tour groups will appear here.</div>
      </div>
    </div>
    <?php else: ?>
      <?php foreach ($recentPosts as $post): ?>
      <div class="post-card">
        <div class="post-header">
          <div class="post-author">
            <?= uAvatar($post['author_name'], $post['author_photo'], 36) ?>
            <div class="post-author-info">
              <div class="post-author-name"><?= e($post['author_name']) ?></div>
              <div class="post-meta"><?= timeAgo($post['created_at']) ?><?= $post['group_name'] ? ' · ' . e($post['group_name']) : '' ?></div>
            </div>
          </div>
        </div>
        <div class="post-body"><?= nl2br(e($post['content'])) ?></div>
        <?php if ($post['image'] && file_exists(UPLOAD_POSTS . $post['image'])): ?>
          <img src="<?= uUrl('posts', $post['image']) ?>" class="post-image" alt="">
        <?php endif; ?>
        <div class="post-actions">
          <span class="action-btn">❤️ <?= $post['like_count'] ?></span>
          <span class="action-btn">💬 <?= $post['comment_count'] ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($recentNotifs): ?>
    <div style="margin-top:20px;">
      <div class="flex-between mb-2">
        <h2 style="font-size:15px;font-weight:700;">Recent Alerts</h2>
        <a href="notifications.php" class="btn btn-outline btn-sm">All</a>
      </div>
      <div class="card" style="padding:0;overflow:hidden;">
        <?php foreach ($recentNotifs as $n): ?>
        <a href="<?= e($n['link'] ?: '#') ?>" class="notif-item <?= !$n['is_read'] ? 'unread' : '' ?>">
          <?php if (!$n['is_read']): ?><span class="notif-dot"></span><?php endif; ?>
          <div class="notif-body">
            <div class="notif-msg"><?= e($n['message']) ?></div>
            <div class="notif-time"><?= timeAgo($n['created_at']) ?></div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

</div>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
