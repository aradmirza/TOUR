<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (!isLoggedIn()) { header('Location: ../login.php'); exit; }

$userId = currentUserId();

// Mark all read
if (isset($_GET['mark_read'])) {
    $stmt = $db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    header('Location: notifications.php'); exit;
}

// Mark single read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_read') {
        $nid = (int)($_POST['notif_id'] ?? 0);
        if ($nid) {
            $stmt = $db->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?");
            $stmt->bind_param("ii", $nid, $userId);
            $stmt->execute();
        }
        header('Location: notifications.php'); exit;
    } elseif ($action === 'delete') {
        $nid = (int)($_POST['notif_id'] ?? 0);
        if ($nid) {
            $stmt = $db->prepare("DELETE FROM notifications WHERE id=? AND user_id=?");
            $stmt->bind_param("ii", $nid, $userId);
            $stmt->execute();
        }
        header('Location: notifications.php'); exit;
    }
}

// Fetch notifications
$stmt = $db->prepare(
    "SELECT n.*, u.name AS from_name FROM notifications n
     LEFT JOIN users u ON u.id=n.from_user_id
     WHERE n.user_id=? ORDER BY n.created_at DESC LIMIT 100"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$unreadCount = count(array_filter($notifications, fn($n) => !$n['is_read']));

$typeIcons = [
    'expense'  => '💸',
    'post'     => '📰',
    'comment'  => '💬',
    'invite'   => '✉️',
    'plan'     => '📋',
    'system'   => '🔔',
    'admin'    => '⚙️',
];

$pageTitle  = 'Notifications';
$activePage = 'notifications';
include __DIR__ . '/includes/user-header.php';
?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:10px;">
  <h2 style="font-size:16px;font-weight:800;">
    🔔 Notifications
    <?php if ($unreadCount > 0): ?>
      <span class="badge-notif" style="position:static;display:inline-flex;margin-left:6px;"><?= $unreadCount ?></span>
    <?php endif; ?>
  </h2>
  <?php if ($unreadCount > 0): ?>
    <a href="notifications.php?mark_read=1" class="btn btn-outline btn-sm">Mark All Read</a>
  <?php endif; ?>
</div>

<?php if (!$notifications): ?>
<div class="card">
  <div class="empty-state" style="padding:40px;">
    <div class="empty-icon">🔔</div>
    <div class="empty-title">No notifications yet</div>
    <div class="empty-desc">Activity from your tour groups will appear here.</div>
  </div>
</div>
<?php else: ?>
<div class="card" style="padding:0;overflow:hidden;">
  <?php foreach ($notifications as $n):
    $icon = $typeIcons[$n['type']] ?? '🔔';
  ?>
  <div class="notif-item <?= !$n['is_read'] ? 'unread' : '' ?>">
    <div style="font-size:22px;flex-shrink:0;width:36px;text-align:center;"><?= $icon ?></div>
    <div class="notif-body" style="flex:1;min-width:0;">
      <div class="notif-msg"><?= e($n['message']) ?></div>
      <?php if ($n['from_name']): ?>
        <div style="font-size:11px;color:var(--primary);margin-top:2px;">from <?= e($n['from_name']) ?></div>
      <?php endif; ?>
      <div class="notif-time"><?= timeAgo($n['created_at']) ?></div>
    </div>
    <div style="display:flex;flex-direction:column;gap:4px;align-items:flex-end;">
      <?php if (!$n['is_read']): ?><span class="notif-dot" style="margin:0;"></span><?php endif; ?>
      <?php if ($n['link']): ?>
        <a href="<?= e($n['link']) ?>" class="btn btn-sm btn-outline" style="font-size:11px;padding:3px 8px;">View</a>
      <?php endif; ?>
      <form method="POST" style="display:inline;">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="notif_id" value="<?= $n['id'] ?>">
        <button type="submit" style="font-size:14px;color:var(--text-muted);background:none;border:none;cursor:pointer;padding:3px;">🗑</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
