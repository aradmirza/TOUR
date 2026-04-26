<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

$groupId = (int)($_GET['id'] ?? 0);
if (!$groupId) { header('Location: groups.php'); exit; }

requireGroupMember($db, $groupId);
$userId  = currentUserId();
$isAdmin = isGroupAdmin($db, $groupId, $userId);

// Group info
$stmt = $db->prepare("SELECT * FROM tour_groups WHERE id = ?");
$stmt->bind_param("i", $groupId);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();
if (!$group) { flash('Group not found.', 'danger'); header('Location: groups.php'); exit; }

// Handle add member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    if (!verifyCsrf()) {
        flash('Invalid request.', 'danger');
    } elseif (isset($_POST['action'])) {

        if ($_POST['action'] === 'add_member') {
            $email = trim($_POST['email'] ?? '');
            if (!$email) {
                flash('Email is required.', 'danger');
            } else {
                $stmt = $db->prepare("SELECT id, name FROM users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $newUser = $stmt->get_result()->fetch_assoc();

                if (!$newUser) {
                    flash('No user found with that email.', 'danger');
                } elseif (isGroupMember($db, $groupId, $newUser['id'])) {
                    flash($newUser['name'] . ' is already a member.', 'warning');
                } else {
                    $stmt = $db->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'member')");
                    $stmt->bind_param("ii", $groupId, $newUser['id']);
                    $stmt->execute();

                    // Notify new member
                    $cu = currentUser();
                    $msg  = $cu['name'] . ' added you to "' . $group['name'] . '"';
                    $link = 'group.php?id=' . $groupId;
                    sendNotification($db, $newUser['id'], $userId, $groupId, 'member_added', $msg, $link);

                    flash($newUser['name'] . ' added to group!', 'success');
                }
            }
        }

        elseif ($_POST['action'] === 'remove_member') {
            $removeId = (int)($_POST['member_id'] ?? 0);
            if ($removeId && $removeId !== $userId) {
                $stmt = $db->prepare("DELETE FROM group_members WHERE group_id=? AND user_id=?");
                $stmt->bind_param("ii", $groupId, $removeId);
                $stmt->execute();
                flash('Member removed.', 'success');
            }
        }

        elseif ($_POST['action'] === 'toggle_role') {
            $memberId = (int)($_POST['member_id'] ?? 0);
            if ($memberId && $memberId !== $userId) {
                $stmt = $db->prepare("SELECT role FROM group_members WHERE group_id=? AND user_id=?");
                $stmt->bind_param("ii", $groupId, $memberId);
                $stmt->execute();
                $cur = $stmt->get_result()->fetch_row()[0] ?? 'member';
                $newRole = $cur === 'admin' ? 'member' : 'admin';
                $stmt = $db->prepare("UPDATE group_members SET role=? WHERE group_id=? AND user_id=?");
                $stmt->bind_param("sii", $newRole, $groupId, $memberId);
                $stmt->execute();
                flash('Role updated to ' . $newRole . '.', 'success');
            }
        }
    }
    header('Location: group-members.php?id=' . $groupId); exit;
}

// Fetch members
$stmt = $db->prepare(
    "SELECT gm.*, u.name, u.email, u.mobile, u.profile_photo,
            (SELECT COALESCE(SUM(amount),0) FROM expenses WHERE group_id=? AND paid_by=gm.user_id) AS total_paid
     FROM group_members gm
     JOIN users u ON u.id = gm.user_id
     WHERE gm.group_id = ?
     ORDER BY gm.role DESC, gm.joined_at ASC"
);
$stmt->bind_param("ii", $groupId, $groupId);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle  = 'Members — ' . e($group['name']);
$activePage = 'groups';
$groupTab   = 'members';
include 'includes/header.php';
?>

<!-- Group Hero -->
<div class="group-hero">
  <?php if ($group['cover_photo'] && file_exists(UPLOAD_GROUP . $group['cover_photo'])): ?>
    <img src="uploads/group/<?= e($group['cover_photo']) ?>" alt="">
  <?php else: ?>
    <div class="group-hero-gradient"></div>
  <?php endif; ?>
  <div class="group-hero-overlay">
    <div class="group-hero-title"><?= e($group['name']) ?></div>
    <div class="group-hero-sub">📍 <?= e($group['destination']) ?></div>
  </div>
</div>

<?php include 'includes/group_subnav.php'; ?>

<div class="page-header">
  <div>
    <h1>Group Members</h1>
    <p><?= count($members) ?> member<?= count($members) !== 1 ? 's' : '' ?></p>
  </div>
  <?php if ($isAdmin): ?>
    <button class="btn btn-primary" onclick="openModal('addMemberModal')">+ Add Member</button>
  <?php endif; ?>
</div>

<!-- Members List -->
<?php foreach ($members as $m): ?>
<div class="member-card">
  <?= avatarHtml($m['name'], $m['profile_photo'], 44) ?>
  <div class="member-info">
    <div class="member-name">
      <?= e($m['name']) ?>
      <?php if ($m['user_id'] == $userId): ?>
        <span style="font-size:11px;color:var(--text-muted);font-weight:400;">(You)</span>
      <?php endif; ?>
    </div>
    <div class="member-email"><?= e($m['email']) ?></div>
    <?php if ($m['mobile']): ?>
      <div class="member-email">📱 <?= e($m['mobile']) ?></div>
    <?php endif; ?>
    <div class="text-muted text-small mt-1">Paid: <?= formatMoney($m['total_paid']) ?></div>
  </div>
  <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;">
    <span class="badge <?= $m['role'] === 'admin' ? 'badge-orange' : 'badge-gray' ?>">
      <?= ucfirst($m['role']) ?>
    </span>
    <?php if ($isAdmin && $m['user_id'] != $userId): ?>
      <div style="display:flex;gap:6px;">
        <form method="POST" style="display:inline;">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="toggle_role">
          <input type="hidden" name="member_id" value="<?= $m['user_id'] ?>">
          <button type="submit" class="btn btn-xs btn-outline"
                  title="<?= $m['role'] === 'admin' ? 'Demote to Member' : 'Promote to Admin' ?>">
            <?= $m['role'] === 'admin' ? '↓ Member' : '↑ Admin' ?>
          </button>
        </form>
        <form method="POST" onsubmit="return confirm('Remove <?= e(addslashes($m['name'])) ?> from group?')">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="remove_member">
          <input type="hidden" name="member_id" value="<?= $m['user_id'] ?>">
          <button type="submit" class="btn btn-xs btn-danger">Remove</button>
        </form>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>

<?php if ($isAdmin): ?>
<!-- Add Member Modal -->
<div id="addMemberModal" class="modal-overlay hidden">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Add Member</h3>
      <button type="button" class="modal-close" data-close-modal="addMemberModal">&#x2715;</button>
    </div>
    <form method="POST" action="group-members.php?id=<?= $groupId ?>">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add_member">
      <div class="modal-body">
        <p class="text-muted" style="margin-bottom:16px;">Enter the email address of the user you want to add.</p>
        <div class="form-group">
          <label class="form-label">User Email</label>
          <input type="email" name="email" class="form-control" placeholder="member@example.com" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Add to Group</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
