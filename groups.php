<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

$userId = currentUserId();
$filter = $_GET['filter'] ?? 'all'; // all, upcoming, running, completed

$stmt = $db->prepare(
    "SELECT tg.*, gm.role,
            (SELECT COUNT(*) FROM group_members gm2 WHERE gm2.group_id = tg.id) AS member_count,
            (SELECT COALESCE(SUM(amount),0) FROM expenses e WHERE e.group_id = tg.id) AS total_expense,
            (SELECT COUNT(*) FROM tour_plans tp WHERE tp.group_id = tg.id AND tp.status != 'completed') AS pending_plans
     FROM tour_groups tg
     JOIN group_members gm ON gm.group_id = tg.id AND gm.user_id = ?
     ORDER BY tg.start_date ASC"
);
$stmt->bind_param("i", $userId);
$stmt->execute();
$allGroups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Tag status and filter
$groups = [];
foreach ($allGroups as $g) {
    $g['status'] = getTourStatus($g['start_date'], $g['return_date']);
    if ($filter === 'all' || $g['status'] === $filter) {
        $groups[] = $g;
    }
}

$pageTitle  = 'My Tours';
$activePage = 'groups';
include 'includes/header.php';
?>

<div class="page-header">
  <div>
    <h1>My Tour Groups</h1>
    <p>All <?= count($allGroups) ?> tour groups you belong to</p>
  </div>
  <a href="create-group.php" class="btn btn-primary">✈️ New Tour</a>
</div>

<!-- Filter tabs -->
<div class="filter-row" style="margin-bottom:20px;">
  <?php
  $tabs = ['all' => 'All', 'upcoming' => 'Upcoming', 'running' => 'Running', 'completed' => 'Completed'];
  foreach ($tabs as $k => $label):
  ?>
    <a href="groups.php?filter=<?= $k ?>"
       class="btn btn-sm <?= $filter === $k ? 'btn-primary' : 'btn-outline' ?>">
      <?= $label ?>
    </a>
  <?php endforeach; ?>
</div>

<?php if (!$groups): ?>
  <div class="card">
    <div class="empty-state">
      <div class="empty-icon">✈️</div>
      <div class="empty-title">No tours found</div>
      <div class="empty-desc">
        <?= $filter !== 'all' ? 'No ' . $filter . ' tours.' : 'Create your first tour group to get started!' ?>
      </div>
      <?php if ($filter === 'all'): ?>
        <a href="create-group.php" class="btn btn-primary">Create Tour Group</a>
      <?php endif; ?>
    </div>
  </div>
<?php else: ?>
  <div class="grid-auto">
    <?php foreach ($groups as $g): ?>
    <a href="group.php?id=<?= $g['id'] ?>" class="group-card" style="text-decoration:none;display:block;">
      <div class="group-card-cover">
        <?php if ($g['cover_photo'] && file_exists(UPLOAD_GROUP . $g['cover_photo'])): ?>
          <img src="uploads/group/<?= e($g['cover_photo']) ?>" alt="">
        <?php else: ?>
          <div class="group-hero-gradient"></div>
        <?php endif; ?>
        <div class="group-card-overlay"></div>
        <div class="group-card-status">
          <span class="badge badge-<?= $g['status'] === 'upcoming' ? 'blue' : ($g['status'] === 'running' ? 'green' : 'gray') ?>">
            <?= ucfirst($g['status']) ?>
          </span>
        </div>
        <?php if ($g['role'] === 'admin'): ?>
          <div style="position:absolute;bottom:10px;left:10px;">
            <span class="badge badge-orange">Admin</span>
          </div>
        <?php endif; ?>
      </div>
      <div class="group-card-body">
        <div class="group-card-name"><?= e($g['name']) ?></div>
        <div class="group-card-dest">📍 <?= e($g['destination']) ?></div>
        <div class="group-card-dates">
          📅 <?= date('M j', strtotime($g['start_date'])) ?> – <?= date('M j, Y', strtotime($g['return_date'])) ?>
        </div>
        <?php if ($g['description']): ?>
          <p style="margin-top:8px;font-size:12.5px;color:var(--text-muted);overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">
            <?= e($g['description']) ?>
          </p>
        <?php endif; ?>
      </div>
      <div class="group-card-footer">
        <span class="text-muted text-small">👥 <?= $g['member_count'] ?> members</span>
        <span class="text-muted text-small">💸 <?= formatMoney($g['total_expense']) ?></span>
        <?php if ($g['pending_plans'] > 0): ?>
          <span class="text-muted text-small">📋 <?= $g['pending_plans'] ?> plans</span>
        <?php endif; ?>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
