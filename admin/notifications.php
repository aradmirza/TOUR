<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/admin-auth.php';
requireAdmin();

$admin = currentAdmin();

// Send notification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyAdminCsrf()) { adminFlash('Invalid request.', 'danger'); header('Location: notifications.php'); exit; }

    $target  = $_POST['target']   ?? 'all'; // all | group | user
    $message = trim($_POST['message'] ?? '');
    $link    = trim($_POST['link']    ?? '');
    $type    = 'admin';

    if (!$message) {
        adminFlash('Message cannot be empty.', 'danger');
        header('Location: notifications.php'); exit;
    }

    $sent = 0;
    $fromId = 0; // sent by system/admin (0)

    if ($target === 'all') {
        $users = $db->query("SELECT id FROM users WHERE status='active'")->fetch_all(MYSQLI_ASSOC);
        foreach ($users as $u) {
            $stmt = $db->prepare("INSERT INTO notifications (user_id,from_user_id,group_id,type,message,link) VALUES (?,NULL,NULL,?,?,?)");
            $stmt->bind_param("isss", $u['id'], $type, $message, $link);
            $stmt->execute();
            $sent++;
        }
    } elseif ($target === 'group') {
        $gId = (int)($_POST['group_id'] ?? 0);
        if (!$gId) { adminFlash('Please select a group.', 'danger'); header('Location: notifications.php'); exit; }
        $members = $db->prepare("SELECT user_id FROM group_members WHERE group_id=?");
        $members->bind_param("i", $gId);
        $members->execute();
        foreach ($members->get_result()->fetch_all(MYSQLI_ASSOC) as $m) {
            $stmt = $db->prepare("INSERT INTO notifications (user_id,from_user_id,group_id,type,message,link) VALUES (?,NULL,?,?,?,?)");
            $stmt->bind_param("iisss", $m['user_id'], $gId, $type, $message, $link);
            $stmt->execute();
            $sent++;
        }
    } elseif ($target === 'user') {
        $uId = (int)($_POST['user_id'] ?? 0);
        if (!$uId) { adminFlash('Please select a user.', 'danger'); header('Location: notifications.php'); exit; }
        $stmt = $db->prepare("INSERT INTO notifications (user_id,from_user_id,group_id,type,message,link) VALUES (?,NULL,NULL,?,?,?)");
        $stmt->bind_param("isss", $uId, $type, $message, $link);
        $stmt->execute();
        $sent = 1;
    }

    adminFlash("Notification sent to {$sent} recipient(s).", 'success');
    header('Location: notifications.php'); exit;
}

// Load dropdowns
$allGroups = $db->query("SELECT id, name FROM tour_groups WHERE status='active' ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$allUsers  = $db->query("SELECT id, name, email FROM users WHERE status='active' ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Recent notifications (type=admin)
$recent = $db->query(
    "SELECT n.*, u.name AS recipient_name
     FROM notifications n JOIN users u ON u.id = n.user_id
     WHERE n.type='admin'
     ORDER BY n.created_at DESC LIMIT 30"
)->fetch_all(MYSQLI_ASSOC);

$pageTitle  = 'Send Notification';
$activePage = 'notifications';
include __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-page-header">
  <h2>Send Notification</h2>
</div>

<div class="admin-grid-sidebar">

  <!-- Send Form -->
  <div class="admin-card">
    <div class="admin-card-header"><h3 class="admin-card-title">Compose Notification</h3></div>
    <div class="admin-card-body">
      <form method="POST" id="notifForm">
        <?= adminCsrfField() ?>

        <div class="admin-form-group">
          <label class="admin-label">Send To</label>
          <select name="target" id="notifTarget" class="admin-select" onchange="toggleTarget()">
            <option value="all">📢 All Active Users</option>
            <option value="group">✈️ Specific Group</option>
            <option value="user">👤 Specific User</option>
          </select>
        </div>

        <div class="admin-form-group" id="groupSelect" style="display:none">
          <label class="admin-label">Select Group</label>
          <select name="group_id" class="admin-select">
            <option value="">— Choose Group —</option>
            <?php foreach ($allGroups as $g): ?>
              <option value="<?= $g['id'] ?>"><?= e($g['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="admin-form-group" id="userSelect" style="display:none">
          <label class="admin-label">Select User</label>
          <select name="user_id" class="admin-select">
            <option value="">— Choose User —</option>
            <?php foreach ($allUsers as $u): ?>
              <option value="<?= $u['id'] ?>"><?= e($u['name']) ?> (<?= e($u['email']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="admin-form-group">
          <label class="admin-label">Message</label>
          <textarea name="message" class="admin-input admin-textarea" rows="4"
                    placeholder="Write your notification message…" required></textarea>
        </div>

        <div class="admin-form-group">
          <label class="admin-label">Link (optional)</label>
          <input type="text" name="link" class="admin-input" placeholder="/group.php?id=1">
        </div>

        <button type="submit" class="btn-admin btn-admin-primary">🔔 Send Notification</button>
      </form>
    </div>
  </div>

  <!-- Recent sent -->
  <div class="admin-card">
    <div class="admin-card-header"><h3 class="admin-card-title">Recently Sent</h3></div>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead><tr><th>Recipient</th><th>Message</th><th>Sent</th></tr></thead>
        <tbody>
          <?php foreach ($recent as $n): ?>
          <tr>
            <td><?= e($n['recipient_name']) ?></td>
            <td><?= e(mb_substr($n['message'], 0, 60)) ?>…</td>
            <td class="text-muted"><?= timeAgo($n['created_at']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$recent): ?><tr><td colspan="3" class="text-center text-muted">No notifications sent yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script>
function toggleTarget() {
  const target = document.getElementById('notifTarget').value;
  document.getElementById('groupSelect').style.display = target === 'group' ? 'block' : 'none';
  document.getElementById('userSelect').style.display  = target === 'user'  ? 'block' : 'none';
}
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
