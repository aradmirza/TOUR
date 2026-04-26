<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

$userId = currentUserId();

// Mark all as read if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_all_read') {
        $stmt = $db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        flash('All notifications marked as read.', 'success');
    } elseif ($action === 'mark_read') {
        $nid = (int)($_POST['notif_id'] ?? 0);
        $stmt = $db->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $nid, $userId);
        $stmt->execute();
    }
    header('Location: notifications.php'); exit;
}

// Fetch notifications
$stmt = $db->prepare(
    "SELECT n.*, u.name AS from_name, u.profile_photo AS from_photo
     FROM notifications n
     LEFT JOIN users u ON u.id = n.from_user_id
     WHERE n.user_id = ?
     ORDER BY n.created_at DESC LIMIT 60"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$unread = array_filter($notifications, fn($n) => !$n['is_read']);

$typeIcons = [
    'member_added'    => '👥',
    'expense_added'   => '💸',
    'plan_added'      => '📋',
    'plan_completed'  => '✅',
    'post_like'       => '❤️',
    'post_comment'    => '💬',
    'group_invite'    => '✈️',
];

$pageTitle  = 'Notifications';
$activePage = 'notifications';
include 'includes/header.php';
?>

<div class="page-header">
  <div>
    <h1>Notifications</h1>
    <p><?= count($unread) ?> unread of <?= count($notifications) ?> total</p>
  </div>
  <?php if ($unread): ?>
    <form method="POST" action="notifications.php">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="mark_all_read">
      <button type="submit" class="btn btn-outline btn-sm">✓ Mark All Read</button>
    </form>
  <?php endif; ?>
</div>

<div class="card">
  <?php if (!$notifications): ?>
    <div class="empty-state">
      <div class="empty-icon">🔔</div>
      <div class="empty-title">No notifications</div>
      <div class="empty-desc">You're all caught up! Activity from your groups will appear here.</div>
    </div>
  <?php else: ?>
    <?php foreach ($notifications as $n):
      $icon = $typeIcons[$n['type']] ?? '🔔';
    ?>
    <div class="notif-item <?= !$n['is_read'] ? 'unread' : '' ?>">
      <div class="notif-icon-box" style="background:<?= !$n['is_read']?'var(--primary-light)':'var(--bg)' ?>">
        <?= $icon ?>
      </div>
      <div style="flex:1;min-width:0;">
        <div class="notif-message"><?= e($n['message']) ?></div>
        <div class="notif-time"><?= timeAgo($n['created_at']) ?></div>
        <?php if ($n['link']): ?>
          <a href="<?= e($n['link']) ?>" class="btn btn-xs btn-outline" style="margin-top:6px;">View →</a>
        <?php endif; ?>
      </div>
      <?php if (!$n['is_read']): ?>
        <div class="notif-dot"></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
