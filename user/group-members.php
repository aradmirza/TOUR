<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$groupId = (int)($_GET['id'] ?? 0);
if (!$groupId) { header('Location: groups.php'); exit; }

if (!isLoggedIn()) { header('Location: ../login.php'); exit; }
requireGroupMember($db, $groupId);
$userId  = currentUserId();
$isAdmin = isGroupAdmin($db, $groupId, $userId);

$stmt = $db->prepare("SELECT * FROM tour_groups WHERE id=?");
$stmt->bind_param("i", $groupId);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();
if (!$group) { flash('Group not found.', 'danger'); header('Location: groups.php'); exit; }

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action     = $_POST['action']    ?? '';
    $targetUser = (int)($_POST['target_user_id'] ?? 0);

    if ($action === 'invite') {
        $email = trim($_POST['email'] ?? '');
        if (!$email) {
            flash('Email is required.', 'danger');
        } else {
            $stmt = $db->prepare("SELECT id, name, status FROM users WHERE email=? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $invitee = $stmt->get_result()->fetch_assoc();

            if (!$invitee) {
                flash('No user found with that email.', 'danger');
            } elseif ($invitee['status'] === 'inactive') {
                flash('That user account is inactive.', 'danger');
            } else {
                // Check already member
                $stmt = $db->prepare("SELECT id FROM group_members WHERE group_id=? AND user_id=?");
                $stmt->bind_param("ii", $groupId, $invitee['id']);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    flash($invitee['name'] . ' is already a member.', 'warning');
                } else {
                    $stmt = $db->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'member')");
                    $stmt->bind_param("ii", $groupId, $invitee['id']);
                    $stmt->execute();

                    sendNotification($db, $invitee['id'], $userId, $groupId, 'invite',
                        currentUser()['name'] . ' added you to group "' . $group['name'] . '"',
                        '../user/group.php?id=' . $groupId
                    );
                    flash($invitee['name'] . ' has been added to the group!', 'success');
                }
            }
        }
    } elseif ($action === 'remove' && $isAdmin && $targetUser && $targetUser !== $userId) {
        $stmt = $db->prepare("DELETE FROM group_members WHERE group_id=? AND user_id=?");
        $stmt->bind_param("ii", $groupId, $targetUser);
        $stmt->execute();
        flash('Member removed.', 'success');
    } elseif ($action === 'make_admin' && $isAdmin && $targetUser) {
        $stmt = $db->prepare("UPDATE group_members SET role='admin' WHERE group_id=? AND user_id=?");
        $stmt->bind_param("ii", $groupId, $targetUser);
        $stmt->execute();
        flash('Member promoted to admin.', 'success');
    } elseif ($action === 'remove_admin' && $isAdmin && $targetUser && $targetUser !== $userId) {
        $stmt = $db->prepare("UPDATE group_members SET role='member' WHERE group_id=? AND user_id=?");
        $stmt->bind_param("ii", $groupId, $targetUser);
        $stmt->execute();
        flash('Admin role removed.', 'success');
    }
    header('Location: group-members.php?id=' . $groupId); exit;
}

// Fetch members with stats
$stmt = $db->prepare(
    "SELECT gm.user_id, gm.role, gm.joined_at, u.name, u.profile_photo, u.mobile,
            (SELECT COALESCE(SUM(e.amount),0) FROM expenses e WHERE e.group_id=? AND e.paid_by=gm.user_id) AS total_paid
     FROM group_members gm JOIN users u ON u.id=gm.user_id
     WHERE gm.group_id=? ORDER BY gm.role DESC, gm.joined_at ASC"
);
$stmt->bind_param("ii", $groupId, $groupId);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle  = 'Members — ' . e($group['name']);
$activePage = 'groups';
include __DIR__ . '/includes/user-header.php';
?>

<!-- Sub Nav -->
<div class="group-subnav">
  <a href="group.php?id=<?= $groupId ?>">🏠 Overview</a>
  <a href="tour-plan.php?id=<?= $groupId ?>">📋 Tour Plan</a>
  <a href="expenses.php?id=<?= $groupId ?>">💸 Expenses</a>
  <a href="settlement.php?id=<?= $groupId ?>">⚖️ Settlement</a>
  <a href="feed.php?group_id=<?= $groupId ?>">📰 Feed</a>
  <a href="gallery.php?id=<?= $groupId ?>">🖼️ Gallery</a>
  <a href="group-members.php?id=<?= $groupId ?>" class="active">👥 Members</a>
</div>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
  <h2 style="font-size:16px;font-weight:800;">👥 Members <span style="color:var(--text-muted);font-size:13px;">(<?= count($members) ?>)</span></h2>
  <a href="group.php?id=<?= $groupId ?>" class="btn btn-outline btn-sm">← Group</a>
</div>

<?php if ($isAdmin): ?>
<!-- Invite Form -->
<div class="card" style="margin-bottom:16px;">
  <div class="card-header"><h3 class="card-title">➕ Invite by Email</h3></div>
  <div style="padding:16px;">
    <form method="POST" style="display:flex;gap:10px;flex-wrap:wrap;">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="invite">
      <input type="email" name="email" class="form-input" placeholder="Enter user's email address" required
             style="flex:1;min-width:200px;">
      <button type="submit" class="btn btn-primary">Invite</button>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Members List -->
<div class="card">
  <?php if (!$members): ?>
  <div class="empty-state" style="padding:40px;">
    <div class="empty-icon">👥</div>
    <div class="empty-title">No members yet</div>
  </div>
  <?php else: ?>
  <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:13.5px;">
      <thead>
        <tr style="border-bottom:1px solid var(--border);text-align:left;">
          <th style="padding:10px 16px;font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;background:var(--bg);">Member</th>
          <th style="padding:10px 16px;font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;background:var(--bg);">Role</th>
          <th style="padding:10px 16px;font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;background:var(--bg);">Paid</th>
          <th style="padding:10px 16px;font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;background:var(--bg);">Joined</th>
          <?php if ($isAdmin): ?>
          <th style="padding:10px 16px;font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;background:var(--bg);">Actions</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($members as $m): ?>
        <tr style="border-bottom:1px solid var(--border);">
          <td style="padding:12px 16px;">
            <div style="display:flex;align-items:center;gap:10px;">
              <?= uAvatar($m['name'], $m['profile_photo'], 36) ?>
              <div>
                <div style="font-weight:600;"><?= e($m['name']) ?><?= $m['user_id'] == $userId ? ' <span style="color:var(--primary);font-size:11px;">(you)</span>' : '' ?></div>
                <?php if ($m['mobile']): ?><div style="font-size:11px;color:var(--text-muted);"><?= e($m['mobile']) ?></div><?php endif; ?>
              </div>
            </div>
          </td>
          <td style="padding:12px 16px;">
            <span class="badge badge-<?= $m['role']==='admin'?'blue':'gray' ?>"><?= ucfirst($m['role']) ?></span>
          </td>
          <td style="padding:12px 16px;font-weight:600;"><?= formatMoney($m['total_paid']) ?></td>
          <td style="padding:12px 16px;color:var(--text-muted);font-size:12px;"><?= date('M j, Y', strtotime($m['joined_at'])) ?></td>
          <?php if ($isAdmin): ?>
          <td style="padding:12px 16px;">
            <?php if ($m['user_id'] != $userId): ?>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
              <?php if ($m['role'] === 'member'): ?>
              <form method="POST" style="display:inline;">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="make_admin">
                <input type="hidden" name="target_user_id" value="<?= $m['user_id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline" data-confirm="Promote to admin?">↑ Admin</button>
              </form>
              <?php else: ?>
              <form method="POST" style="display:inline;">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="remove_admin">
                <input type="hidden" name="target_user_id" value="<?= $m['user_id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline" data-confirm="Remove admin role?">↓ Demote</button>
              </form>
              <?php endif; ?>
              <form method="POST" style="display:inline;">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="target_user_id" value="<?= $m['user_id'] ?>">
                <button type="submit" class="btn btn-sm btn-danger"
                        data-confirm="Remove <?= e(addslashes($m['name'])) ?> from group?">Remove</button>
              </form>
            </div>
            <?php else: ?>
              <span style="font-size:12px;color:var(--text-muted);">You</span>
            <?php endif; ?>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
