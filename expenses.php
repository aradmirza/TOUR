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

$stmt = $db->prepare("SELECT * FROM tour_groups WHERE id = ?");
$stmt->bind_param("i", $groupId);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();
if (!$group) { flash('Group not found.', 'danger'); header('Location: groups.php'); exit; }

// Fetch members
$stmt = $db->prepare(
    "SELECT gm.user_id, u.name FROM group_members gm JOIN users u ON u.id=gm.user_id WHERE gm.group_id=?"
);
$stmt->bind_param("i", $groupId);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// --- Handle add expense ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_expense') {
        $title       = trim($_POST['title']         ?? '');
        $amount      = (float)($_POST['amount']     ?? 0);
        $category    = $_POST['category']           ?? 'other';
        $paid_by     = (int)($_POST['paid_by']      ?? 0);
        $expense_date= trim($_POST['expense_date']  ?? '') ?: date('Y-m-d');
        $note        = trim($_POST['note']          ?? '');
        $splitWith   = $_POST['split_with']         ?? [];

        $cats = ['transport','food','hotel','ticket','shopping','emergency','other'];
        if (!in_array($category, $cats)) $category = 'other';

        if (!$title || $amount <= 0 || !$paid_by) {
            flash('Title, amount, and paid-by are required.', 'danger');
        } elseif (empty($splitWith)) {
            flash('Select at least one member to split with.', 'danger');
        } else {
            $receiptImage = null;
            if (!empty($_FILES['receipt']['name'])) {
                $res = uploadFile($_FILES['receipt'], UPLOAD_RECEIPTS, 'receipt');
                if ($res['success']) {
                    $receiptImage = $res['filename'];
                }
            }

            $stmt = $db->prepare(
                "INSERT INTO expenses (group_id, title, amount, category, paid_by, expense_date, note, receipt_image)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("isdsisss",
                $groupId, $title, $amount, $category, $paid_by, $expense_date, $note, $receiptImage
            );
            $stmt->execute();
            $expenseId = $db->insert_id;

            // Insert splits (equal split)
            $share = round($amount / count($splitWith), 2);
            $stmtSplit = $db->prepare("INSERT INTO expense_splits (expense_id, user_id, amount) VALUES (?, ?, ?)");
            foreach ($splitWith as $uid) {
                $uid = (int)$uid;
                $stmtSplit->bind_param("iid", $expenseId, $uid, $share);
                $stmtSplit->execute();
            }

            // Notify members
            $cu = currentUser();
            foreach ($members as $m) {
                $msg  = $cu['name'] . ' added expense "' . $title . '" (' . formatMoney($amount) . ')';
                $link = 'expenses.php?id=' . $groupId;
                sendNotification($db, $m['user_id'], $userId, $groupId, 'expense_added', $msg, $link);
            }

            flash('Expense added!', 'success');
        }
    }

    elseif ($action === 'delete_expense' && $isAdmin) {
        $expenseId = (int)($_POST['expense_id'] ?? 0);
        if ($expenseId) {
            // Get receipt to delete
            $s = $db->prepare("SELECT receipt_image FROM expenses WHERE id=? AND group_id=?");
            $s->bind_param("ii", $expenseId, $groupId);
            $s->execute();
            $ri = $s->get_result()->fetch_row()[0] ?? null;
            if ($ri) deleteFile(UPLOAD_RECEIPTS, $ri);

            $stmt = $db->prepare("DELETE FROM expenses WHERE id=? AND group_id=?");
            $stmt->bind_param("ii", $expenseId, $groupId);
            $stmt->execute();
            flash('Expense deleted.', 'success');
        }
    }

    header('Location: expenses.php?id=' . $groupId); exit;
}

// --- Filters ---
$filterCat    = $_GET['category'] ?? '';
$filterMember = (int)($_GET['member'] ?? 0);
$filterDate   = $_GET['date'] ?? '';

$sql    = "SELECT e.*, u.name AS payer_name FROM expenses e JOIN users u ON u.id=e.paid_by WHERE e.group_id=?";
$types  = "i";
$params = [$groupId];

if ($filterCat) {
    $sql .= " AND e.category=?"; $types .= "s"; $params[] = $filterCat;
}
if ($filterMember) {
    $sql .= " AND e.paid_by=?"; $types .= "i"; $params[] = $filterMember;
}
if ($filterDate) {
    $sql .= " AND e.expense_date=?"; $types .= "s"; $params[] = $filterDate;
}
$sql .= " ORDER BY e.expense_date DESC, e.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$expenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Total
$stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE group_id=?");
$stmt->bind_param("i", $groupId);
$stmt->execute();
$totalExpense = $stmt->get_result()->fetch_row()[0];

// My paid & share
$stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE group_id=? AND paid_by=?");
$stmt->bind_param("ii", $groupId, $userId);
$stmt->execute();
$myPaid = $stmt->get_result()->fetch_row()[0];

$stmt = $db->prepare(
    "SELECT COALESCE(SUM(es.amount),0) FROM expense_splits es JOIN expenses e ON e.id=es.expense_id WHERE e.group_id=? AND es.user_id=?"
);
$stmt->bind_param("ii", $groupId, $userId);
$stmt->execute();
$myShare = $stmt->get_result()->fetch_row()[0];

$pageTitle  = 'Expenses — ' . e($group['name']);
$activePage = 'groups';
$groupTab   = 'expenses';
include 'includes/header.php';
?>

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
    <h1>Expenses</h1>
    <p><?= count($expenses) ?> expenses · Total: <?= formatMoney($totalExpense) ?></p>
  </div>
  <button class="btn btn-primary" onclick="openModal('addExpenseModal')">+ Add Expense</button>
</div>

<!-- My balance stats -->
<div class="stats-grid mb-3" style="grid-template-columns:repeat(3,1fr);">
  <div class="stat-card">
    <div class="stat-icon">💸</div>
    <div class="stat-value"><?= formatMoney($totalExpense) ?></div>
    <div class="stat-label">Total Spent</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">💳</div>
    <div class="stat-value"><?= formatMoney($myPaid) ?></div>
    <div class="stat-label">I Paid</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">📊</div>
    <div class="stat-value" style="color:<?= $myPaid>=$myShare?'var(--success)':'var(--danger)' ?>">
      <?= formatMoney($myPaid - $myShare) ?>
    </div>
    <div class="stat-label">My Balance</div>
  </div>
</div>

<!-- Filters -->
<form method="GET" action="expenses.php" class="filter-row mb-3">
  <input type="hidden" name="id" value="<?= $groupId ?>">
  <select name="category" class="filter-select" onchange="this.form.submit()">
    <option value="">All Categories</option>
    <?php foreach (['transport','food','hotel','ticket','shopping','emergency','other'] as $cat): ?>
      <option value="<?= $cat ?>" <?= $filterCat===$cat?'selected':'' ?>><?= categoryIcon($cat) ?> <?= ucfirst($cat) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="member" class="filter-select" onchange="this.form.submit()">
    <option value="">All Members</option>
    <?php foreach ($members as $m): ?>
      <option value="<?= $m['user_id'] ?>" <?= $filterMember===$m['user_id']?'selected':'' ?>><?= e($m['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <input type="date" name="date" class="filter-select" value="<?= e($filterDate) ?>" onchange="this.form.submit()">
  <?php if ($filterCat || $filterMember || $filterDate): ?>
    <a href="expenses.php?id=<?= $groupId ?>" class="btn btn-sm btn-outline">Clear Filters</a>
  <?php endif; ?>
</form>

<!-- Expense list -->
<?php if (!$expenses): ?>
  <div class="card">
    <div class="empty-state">
      <div class="empty-icon">💸</div>
      <div class="empty-title">No expenses yet</div>
      <div class="empty-desc">Track all expenses and let TourMate calculate who owes what.</div>
      <button class="btn btn-primary" onclick="openModal('addExpenseModal')">Add First Expense</button>
    </div>
  </div>
<?php else: ?>
  <?php foreach ($expenses as $exp):
    // Get split info
    $s2 = $db->prepare("SELECT COUNT(*) FROM expense_splits WHERE expense_id=?");
    $s2->bind_param("i", $exp['id']);
    $s2->execute();
    $splitCount = $s2->get_result()->fetch_row()[0];
    $myExpShare = $splitCount > 0 ? round($exp['amount']/$splitCount, 2) : 0;
  ?>
  <div class="expense-card">
    <div class="expense-cat-icon"><?= categoryIcon($exp['category']) ?></div>
    <div class="expense-info">
      <div class="expense-title"><?= e($exp['title']) ?></div>
      <div class="expense-meta">
        💳 Paid by <strong><?= e($exp['payer_name']) ?></strong> ·
        <span class="badge <?= categoryBadge($exp['category']) ?>"><?= ucfirst($exp['category']) ?></span> ·
        👥 Split <?= $splitCount ?> ways
      </div>
      <?php if ($exp['note']): ?>
        <div class="text-muted text-small mt-1"><?= e($exp['note']) ?></div>
      <?php endif; ?>
      <?php if ($exp['receipt_image']): ?>
        <div class="mt-1">
          <a href="uploads/receipts/<?= e($exp['receipt_image']) ?>" target="_blank"
             class="btn btn-xs btn-outline">📎 Receipt</a>
        </div>
      <?php endif; ?>
    </div>
    <div style="text-align:right;">
      <div class="expense-amount"><?= formatMoney($exp['amount']) ?></div>
      <div class="expense-share">Your share: <?= formatMoney($myExpShare) ?></div>
      <div class="text-muted text-small">
        <?= $exp['expense_date'] ? date('M j', strtotime($exp['expense_date'])) : '' ?>
      </div>
      <?php if ($isAdmin): ?>
        <form method="POST" style="margin-top:6px;" onsubmit="return confirm('Delete expense?')">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="delete_expense">
          <input type="hidden" name="expense_id" value="<?= $exp['id'] ?>">
          <button type="submit" class="btn btn-xs btn-danger">🗑</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

<!-- Add Expense Modal -->
<div id="addExpenseModal" class="modal-overlay hidden">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Add Expense</h3>
      <button type="button" class="modal-close" data-close-modal="addExpenseModal">&#x2715;</button>
    </div>
    <form method="POST" action="expenses.php?id=<?= $groupId ?>" enctype="multipart/form-data">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add_expense">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Title <span style="color:var(--danger)">*</span></label>
          <input type="text" name="title" class="form-control" placeholder="e.g. Hotel Booking" required>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Amount (৳) <span style="color:var(--danger)">*</span></label>
            <input type="number" step="0.01" min="0.01" name="amount" class="form-control" placeholder="0.00" required>
          </div>
          <div class="form-group">
            <label class="form-label">Category</label>
            <select name="category" class="form-control">
              <?php foreach (['transport','food','hotel','ticket','shopping','emergency','other'] as $cat): ?>
                <option value="<?= $cat ?>"><?= categoryIcon($cat) ?> <?= ucfirst($cat) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Paid By <span style="color:var(--danger)">*</span></label>
            <select name="paid_by" class="form-control" required>
              <option value="">— Select —</option>
              <?php foreach ($members as $m): ?>
                <option value="<?= $m['user_id'] ?>" <?= $m['user_id']==$userId?'selected':'' ?>><?= e($m['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Date</label>
            <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Split With <span style="color:var(--danger)">*</span>
            <button type="button" class="btn btn-xs btn-outline" onclick="selectAllMembers()" style="margin-left:8px;">All</button>
          </label>
          <div class="members-check-grid">
            <?php foreach ($members as $m): ?>
              <label class="member-check-item">
                <input type="checkbox" name="split_with[]" value="<?= $m['user_id'] ?>" checked>
                <?= avatarHtml($m['name'], null, 24) ?>
                <span style="font-size:13px;"><?= e($m['name']) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Note</label>
          <textarea name="note" class="form-control" rows="2" placeholder="Optional note..."></textarea>
        </div>

        <div class="form-group">
          <label class="form-label">Receipt Photo</label>
          <input type="file" name="receipt" class="form-control" accept="image/*">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-close-modal="addExpenseModal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Expense</button>
      </div>
    </form>
  </div>
</div>

<script>
function selectAllMembers() {
  document.querySelectorAll('input[name="split_with[]"]').forEach(c => c.checked = true);
}
</script>

<?php include 'includes/footer.php'; ?>
