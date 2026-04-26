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

$stmt = $db->prepare(
    "SELECT gm.user_id, u.name FROM group_members gm JOIN users u ON u.id=gm.user_id WHERE gm.group_id=?"
);
$stmt->bind_param("i", $groupId);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle add expense
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
                if ($res['success']) $receiptImage = $res['filename'];
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

            $share = round($amount / count($splitWith), 2);
            $stmtSplit = $db->prepare(
                "INSERT INTO expense_splits (expense_id, user_id, amount) VALUES (?, ?, ?)"
            );
            foreach ($splitWith as $uid) {
                $uid = (int)$uid;
                $stmtSplit->bind_param("iid", $expenseId, $uid, $share);
                $stmtSplit->execute();
            }

            $cu = currentUser();
            foreach ($members as $m) {
                sendNotification($db, $m['user_id'], $userId, $groupId, 'expense',
                    $cu['name'] . ' added expense "' . $title . '" (' . formatMoney($amount) . ')',
                    'expenses.php?id=' . $groupId
                );
            }
            flash('Expense added successfully!', 'success');
        }
        header('Location: expenses.php?id=' . $groupId); exit;

    } elseif ($action === 'delete' && ($isAdmin || true)) {
        $expId = (int)($_POST['expense_id'] ?? 0);
        if ($expId) {
            // Only admin or expense creator can delete
            $stmt = $db->prepare("SELECT paid_by FROM expenses WHERE id=? AND group_id=?");
            $stmt->bind_param("ii", $expId, $groupId);
            $stmt->execute();
            $exp = $stmt->get_result()->fetch_assoc();
            if ($exp && ($isAdmin || $exp['paid_by'] == $userId)) {
                $stmt = $db->prepare("DELETE FROM expenses WHERE id=?");
                $stmt->bind_param("i", $expId);
                $stmt->execute();
                flash('Expense deleted.', 'success');
            }
        }
        header('Location: expenses.php?id=' . $groupId); exit;
    }
}

// Filter
$filterCat  = $_GET['cat']  ?? '';
$filterUser = (int)($_GET['user'] ?? 0);
$cats = ['transport','food','hotel','ticket','shopping','emergency','other'];

$where  = 'e.group_id=?';
$params = [$groupId];
$types  = 'i';

if ($filterCat && in_array($filterCat, $cats)) {
    $where .= ' AND e.category=?';
    $params[] = $filterCat; $types .= 's';
}
if ($filterUser) {
    $where .= ' AND e.paid_by=?';
    $params[] = $filterUser; $types .= 'i';
}

$stmt = $db->prepare(
    "SELECT e.*, u.name AS paid_name FROM expenses e JOIN users u ON u.id=e.paid_by
     WHERE $where ORDER BY e.expense_date DESC, e.created_at DESC"
);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$expenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE group_id=?");
$stmt->bind_param("i", $groupId);
$stmt->execute();
$totalAmount = $stmt->get_result()->fetch_row()[0];

$pageTitle  = 'Expenses — ' . e($group['name']);
$activePage = 'groups';
include __DIR__ . '/includes/user-header.php';
?>

<!-- Sub Nav -->
<div class="group-subnav">
  <a href="group.php?id=<?= $groupId ?>">🏠 Overview</a>
  <a href="tour-plan.php?id=<?= $groupId ?>">📋 Tour Plan</a>
  <a href="expenses.php?id=<?= $groupId ?>" class="active">💸 Expenses</a>
  <a href="settlement.php?id=<?= $groupId ?>">⚖️ Settlement</a>
  <a href="feed.php?group_id=<?= $groupId ?>">📰 Feed</a>
  <a href="gallery.php?id=<?= $groupId ?>">🖼️ Gallery</a>
  <a href="group-members.php?id=<?= $groupId ?>">👥 Members</a>
</div>

<!-- Summary & Filter -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
  <div>
    <div style="font-size:22px;font-weight:800;"><?= formatMoney($totalAmount) ?></div>
    <div style="font-size:12px;color:var(--text-muted);">Total · <?= count($expenses) ?> expense<?= count($expenses)!==1?'s':'' ?></div>
  </div>
  <button class="btn btn-primary" onclick="document.getElementById('addExpenseModal').style.display='flex'">+ Add Expense</button>
</div>

<form method="GET" style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;">
  <input type="hidden" name="id" value="<?= $groupId ?>">
  <select name="cat" class="form-input" style="width:auto;">
    <option value="">All Categories</option>
    <?php foreach ($cats as $c): ?>
      <option value="<?= $c ?>" <?= $filterCat===$c?'selected':'' ?>><?= categoryIcon($c) ?> <?= ucfirst($c) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="user" class="form-input" style="width:auto;">
    <option value="">All Members</option>
    <?php foreach ($members as $m): ?>
      <option value="<?= $m['user_id'] ?>" <?= $filterUser===$m['user_id']?'selected':'' ?>><?= e($m['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-outline">Filter</button>
  <?php if ($filterCat || $filterUser): ?>
    <a href="expenses.php?id=<?= $groupId ?>" class="btn btn-outline">Clear</a>
  <?php endif; ?>
</form>

<div class="card" style="padding:0;">
  <?php if (!$expenses): ?>
  <div class="empty-state" style="padding:40px;">
    <div class="empty-icon">💸</div>
    <div class="empty-title">No expenses yet</div>
    <div class="empty-desc">Add your first group expense.</div>
  </div>
  <?php else: ?>
  <div style="padding:0 16px;">
    <?php foreach ($expenses as $exp): ?>
    <div class="expense-item">
      <div class="expense-cat-icon"><?= categoryIcon($exp['category']) ?></div>
      <div class="expense-body">
        <div class="expense-title"><?= e($exp['title']) ?></div>
        <div class="expense-meta">
          Paid by <?= e($exp['paid_name']) ?> ·
          <?= date('M j, Y', strtotime($exp['expense_date'] ?: $exp['created_at'])) ?>
          <span class="badge badge-gray" style="margin-left:4px;"><?= ucfirst($exp['category']) ?></span>
        </div>
        <?php if ($exp['note']): ?><div style="font-size:11px;color:var(--text-muted);"><?= e($exp['note']) ?></div><?php endif; ?>
        <?php if ($exp['receipt_image']): ?>
          <a href="<?= uUrl('receipts', $exp['receipt_image']) ?>" target="_blank" style="font-size:11px;color:var(--primary);">📎 Receipt</a>
        <?php endif; ?>
      </div>
      <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;">
        <div class="expense-amount"><?= formatMoney($exp['amount']) ?></div>
        <?php if ($isAdmin || $exp['paid_by'] == $userId): ?>
        <form method="POST" style="display:inline;">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="expense_id" value="<?= $exp['id'] ?>">
          <button type="submit" class="btn btn-sm btn-danger" data-confirm="Delete this expense?">🗑</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Add Expense Modal -->
<div id="addExpenseModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:5000;align-items:flex-end;justify-content:center;">
  <div style="background:var(--surface);width:100%;max-width:540px;border-radius:var(--radius) var(--radius) 0 0;padding:24px;max-height:92vh;overflow-y:auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
      <h3 style="font-size:16px;font-weight:800;">💸 Add Expense</h3>
      <button onclick="document.getElementById('addExpenseModal').style.display='none'" style="font-size:22px;color:var(--text-muted);">×</button>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add_expense">

      <div class="form-group">
        <label class="form-label">Title <span style="color:var(--danger)">*</span></label>
        <input type="text" name="title" class="form-input" placeholder="What was it for?" required>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="form-group">
          <label class="form-label">Amount (৳) <span style="color:var(--danger)">*</span></label>
          <input type="number" name="amount" class="form-input" step="0.01" min="0.01" required>
        </div>
        <div class="form-group">
          <label class="form-label">Category</label>
          <select name="category" class="form-input">
            <?php foreach ($cats as $c): ?>
              <option value="<?= $c ?>"><?= categoryIcon($c) ?> <?= ucfirst($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="form-group">
          <label class="form-label">Paid By <span style="color:var(--danger)">*</span></label>
          <select name="paid_by" class="form-input" required>
            <?php foreach ($members as $m): ?>
              <option value="<?= $m['user_id'] ?>" <?= $m['user_id']==$userId?'selected':'' ?>><?= e($m['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Date</label>
          <input type="date" name="expense_date" class="form-input" value="<?= date('Y-m-d') ?>">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Split With <span style="color:var(--danger)">*</span></label>
        <div style="display:flex;gap:10px;flex-wrap:wrap;padding:10px;background:var(--bg);border-radius:var(--radius-sm);">
          <?php foreach ($members as $m): ?>
          <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
            <input type="checkbox" name="split_with[]" value="<?= $m['user_id'] ?>" checked>
            <?= e($m['name']) ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Note</label>
        <input type="text" name="note" class="form-input" placeholder="Optional note…">
      </div>
      <div class="form-group">
        <label class="form-label">Receipt Image</label>
        <input type="file" name="receipt" class="form-input" accept="image/*">
      </div>
      <button type="submit" class="btn btn-primary btn-block">Add Expense</button>
    </form>
  </div>
</div>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
