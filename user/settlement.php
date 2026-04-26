<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (!isLoggedIn()) { header('Location: ../login.php'); exit; }

$groupId = (int)($_GET['id'] ?? 0);
if (!$groupId) { header('Location: groups.php'); exit; }

requireGroupMember($db, $groupId);
$userId  = currentUserId();
$isAdmin = isGroupAdmin($db, $groupId, $userId);

$stmt = $db->prepare("SELECT * FROM tour_groups WHERE id=?");
$stmt->bind_param("i", $groupId);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();
if (!$group) { flash('Group not found.', 'danger'); header('Location: groups.php'); exit; }

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'record_payment') {
        $fromId  = (int)($_POST['from_user_id'] ?? 0);
        $toId    = (int)($_POST['to_user_id']   ?? 0);
        $amount  = (float)str_replace(',', '', $_POST['amount'] ?? 0);
        $note    = trim($_POST['note'] ?? '');
        $payDate = trim($_POST['payment_date'] ?? '') ?: date('Y-m-d');

        // Validate both users are group members
        $stmt2 = $db->prepare("SELECT COUNT(*) FROM group_members WHERE group_id=? AND user_id IN (?,?)");
        $stmt2->bind_param("iii", $groupId, $fromId, $toId);
        $stmt2->execute();
        $memberCount = $stmt2->get_result()->fetch_row()[0];

        if ($fromId && $toId && $fromId !== $toId && $amount > 0 && $memberCount == 2) {
            $stmt2 = $db->prepare(
                "INSERT INTO group_payments (group_id, from_user_id, to_user_id, amount, note, payment_date, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt2->bind_param("iiidssi", $groupId, $fromId, $toId, $amount, $note, $payDate, $userId);
            $stmt2->execute();
            flash('Payment recorded successfully.', 'success');
        } else {
            flash('Invalid payment data.', 'danger');
        }
        header('Location: settlement.php?id=' . $groupId); exit;
    }

    if ($action === 'delete_payment') {
        $payId = (int)($_POST['payment_id'] ?? 0);
        if ($payId && $isAdmin) {
            $stmt2 = $db->prepare("DELETE FROM group_payments WHERE id=? AND group_id=?");
            $stmt2->bind_param("ii", $payId, $groupId);
            $stmt2->execute();
            flash('Payment record removed.', 'success');
        }
        header('Location: settlement.php?id=' . $groupId); exit;
    }
}

$data        = getSettlementData($db, $groupId);
$balances    = $data['members'];
$settlements = $data['settlements'];

// Build member id→name map
$memberMap = [];
foreach ($balances as $b) $memberMap[$b['user_id']] = $b['name'];

$stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE group_id=?");
$stmt->bind_param("i", $groupId);
$stmt->execute();
$totalExpense = $stmt->get_result()->fetch_row()[0];

// Fetch recorded payments
$stmt = $db->prepare(
    "SELECT gp.*, u1.name AS from_name, u2.name AS to_name
     FROM group_payments gp
     JOIN users u1 ON u1.id = gp.from_user_id
     JOIN users u2 ON u2.id = gp.to_user_id
     WHERE gp.group_id=?
     ORDER BY gp.payment_date DESC, gp.created_at DESC"
);
$stmt->bind_param("i", $groupId);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Build map: paidMap[from_id][to_id] = total amount paid
$paidMap = [];
foreach ($payments as $p) {
    $f = $p['from_user_id'];
    $t = $p['to_user_id'];
    $paidMap[$f][$t] = ($paidMap[$f][$t] ?? 0) + (float)$p['amount'];
}

$totalPaidPayments = array_sum(array_column($payments, 'amount'));

$pageTitle  = 'Settlement — ' . e($group['name']);
$activePage = 'groups';
include __DIR__ . '/includes/user-header.php';
?>

<!-- Sub Nav -->
<div class="group-subnav">
  <a href="group.php?id=<?= $groupId ?>">🏠 Overview</a>
  <a href="tour-plan.php?id=<?= $groupId ?>">📋 Tour Plan</a>
  <a href="expenses.php?id=<?= $groupId ?>">💸 Expenses</a>
  <a href="settlement.php?id=<?= $groupId ?>" class="active">⚖️ Settlement</a>
  <a href="feed.php?group_id=<?= $groupId ?>">📰 Feed</a>
  <a href="gallery.php?id=<?= $groupId ?>">🖼️ Gallery</a>
  <a href="group-members.php?id=<?= $groupId ?>">👥 Members</a>
</div>

<!-- Summary -->
<div class="card" style="padding:20px;margin-bottom:16px;text-align:center;background:linear-gradient(135deg,var(--primary),var(--teal));color:#fff;">
  <div style="font-size:13px;opacity:0.85;margin-bottom:4px;">Total Group Expense</div>
  <div style="font-size:28px;font-weight:800;"><?= formatMoney($totalExpense) ?></div>
  <div style="display:flex;justify-content:center;gap:20px;margin-top:10px;font-size:12px;opacity:0.8;">
    <span><?= count($balances) ?> Members</span>
    <span><?= count($settlements) ?> Settlement<?= count($settlements)!=1?'s':'' ?></span>
    <?php if ($totalPaidPayments > 0): ?>
    <span>✅ <?= formatMoney($totalPaidPayments) ?> Paid</span>
    <?php endif; ?>
  </div>
</div>

<!-- Member Balances -->
<div class="balance-grid" style="margin-bottom:16px;">
  <?php foreach ($balances as $b): ?>
  <div class="balance-card">
    <div class="b-name"><?= e($b['name']) ?><?= $b['user_id']==$userId?' (you)':'' ?></div>
    <div class="b-paid">Paid: <?= formatMoney($b['total_paid']) ?></div>
    <div class="b-paid" style="margin-top:2px;">Share: <?= formatMoney($b['total_share']) ?></div>
    <div class="b-bal <?= $b['balance']>0.01?'b-pos':($b['balance']<-0.01?'b-neg':'b-zero') ?>">
      <?php if ($b['balance'] > 0.01): ?>
        +<?= formatMoney($b['balance']) ?> owed
      <?php elseif ($b['balance'] < -0.01): ?>
        owes <?= formatMoney(abs($b['balance'])) ?>
      <?php else: ?>
        ✓ Settled
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Settlement Transactions + Mark Paid -->
<div class="card" style="margin-bottom:16px;">
  <div class="card-header">
    <h3 class="card-title">⚖️ Who Pays Whom</h3>
    <button class="btn btn-sm btn-primary" onclick="openModal('recordPaymentModal')">+ Record Payment</button>
  </div>
  <?php if (!$settlements): ?>
  <div class="empty-state" style="padding:32px;">
    <div class="empty-icon">✅</div>
    <div class="empty-title">All settled!</div>
    <div class="empty-desc">Everyone is square — no payments needed.</div>
  </div>
  <?php else: ?>
  <div style="padding:0 16px 8px;">
    <p style="font-size:12px;color:var(--text-muted);margin:12px 0 8px;">Minimum transactions to settle all debts. Record payments as members pay each other:</p>
    <?php foreach ($settlements as $s): ?>
    <?php
      $alreadyPaid = $paidMap[$s['from_id']][$s['to_id']] ?? 0;
      $remaining   = max(0, round($s['amount'] - $alreadyPaid, 2));
      $isFullyPaid = $remaining < 0.01;
    ?>
    <div class="settlement-item" style="<?= $isFullyPaid ? 'opacity:0.55;' : '' ?>">
      <div style="display:flex;align-items:center;gap:8px;flex:1;min-width:0;">
        <?= uAvatar($s['from_name'], null, 36) ?>
        <div style="min-width:0;">
          <div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($s['from_name']) ?></div>
          <div style="font-size:11px;color:var(--text-muted);">sends to <?= e($s['to_name']) ?></div>
        </div>
      </div>
      <div style="text-align:right;flex-shrink:0;">
        <?php if ($isFullyPaid): ?>
          <div style="color:var(--success);font-weight:700;font-size:13px;">✅ Paid</div>
        <?php else: ?>
          <div style="font-weight:700;color:var(--danger);font-size:15px;"><?= formatMoney($remaining) ?></div>
          <?php if ($alreadyPaid > 0.01): ?>
          <div style="font-size:11px;color:var(--text-muted);">of <?= formatMoney($s['amount']) ?></div>
          <?php endif; ?>
          <div style="display:flex;gap:4px;margin-top:6px;justify-content:flex-end;">
            <?php
              $canMarkPaid = ($s['from_id'] == $userId || $s['to_id'] == $userId || $isAdmin);
            ?>
            <?php if ($canMarkPaid): ?>
            <form method="POST" style="margin:0;">
              <?= csrfField() ?>
              <input type="hidden" name="action"       value="record_payment">
              <input type="hidden" name="from_user_id" value="<?= $s['from_id'] ?>">
              <input type="hidden" name="to_user_id"   value="<?= $s['to_id'] ?>">
              <input type="hidden" name="amount"       value="<?= $remaining ?>">
              <input type="hidden" name="payment_date" value="<?= date('Y-m-d') ?>">
              <input type="hidden" name="note"         value="Settlement payment">
              <button type="submit" class="btn btn-sm btn-success"
                      data-confirm="Mark <?= formatMoney($remaining) ?> as paid?">
                ✓ Mark Paid
              </button>
            </form>
            <?php endif; ?>
            <button class="btn btn-sm btn-outline"
                    onclick="openPartialForm(<?= $s['from_id'] ?>, <?= $s['to_id'] ?>, '<?= e($s['from_name']) ?>', '<?= e($s['to_name']) ?>', <?= $remaining ?>)">
              Partial
            </button>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Payment History -->
<?php if ($payments): ?>
<div class="card" style="margin-bottom:16px;">
  <div class="card-header">
    <h3 class="card-title">📜 Payment History</h3>
    <span style="font-size:12px;color:var(--text-muted);"><?= count($payments) ?> record<?= count($payments)!=1?'s':'' ?></span>
  </div>
  <div style="padding:0 16px 8px;">
    <?php foreach ($payments as $p): ?>
    <div style="display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border);">
      <div style="flex:1;min-width:0;">
        <div style="font-size:13px;font-weight:600;">
          <?= e($p['from_name']) ?> → <?= e($p['to_name']) ?>
        </div>
        <?php if ($p['note']): ?>
        <div style="font-size:11px;color:var(--text-muted);"><?= e($p['note']) ?></div>
        <?php endif; ?>
        <div style="font-size:11px;color:var(--text-muted);"><?= date('M j, Y', strtotime($p['payment_date'])) ?></div>
      </div>
      <div style="font-weight:700;color:var(--success);white-space:nowrap;">+<?= formatMoney($p['amount']) ?></div>
      <?php if ($isAdmin): ?>
      <form method="POST" style="margin:0;">
        <?= csrfField() ?>
        <input type="hidden" name="action"     value="delete_payment">
        <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
        <button type="submit" class="btn btn-sm btn-danger" data-confirm="Remove this payment record?">🗑</button>
      </form>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- My Balance -->
<?php
$myBalance = null;
foreach ($balances as $b) {
    if ($b['user_id'] == $userId) { $myBalance = $b; break; }
}
?>
<?php if ($myBalance): ?>
<div class="card" style="margin-bottom:80px;padding:20px;text-align:center;">
  <div style="font-size:13px;color:var(--text-muted);margin-bottom:8px;">⚖️ YOUR BALANCE</div>
  <div style="font-size:24px;font-weight:800;color:<?= $myBalance['balance']>0.01?'var(--success)':($myBalance['balance']<-0.01?'var(--danger)':'var(--text-muted)') ?>;">
    <?php if ($myBalance['balance'] > 0.01): ?>
      You are owed <?= formatMoney($myBalance['balance']) ?>
    <?php elseif ($myBalance['balance'] < -0.01): ?>
      You owe <?= formatMoney(abs($myBalance['balance'])) ?>
    <?php else: ?>
      You are all settled ✓
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- Record Payment Modal -->
<div id="recordPaymentModal" class="modal-overlay hidden">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Record a Payment</h3>
      <button type="button" class="modal-close" data-close-modal="recordPaymentModal">&#x2715;</button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="record_payment">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Who paid?</label>
          <select name="from_user_id" class="form-input" required>
            <option value="">— Select payer —</option>
            <?php foreach ($balances as $b): ?>
            <option value="<?= $b['user_id'] ?>" <?= $b['user_id']==$userId?'selected':'' ?>><?= e($b['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Paid to?</label>
          <select name="to_user_id" class="form-input" required>
            <option value="">— Select recipient —</option>
            <?php foreach ($balances as $b): ?>
            <option value="<?= $b['user_id'] ?>"><?= e($b['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Amount (৳)</label>
          <input type="number" name="amount" class="form-input" min="0.01" step="0.01" placeholder="0.00" required>
        </div>
        <div class="form-group">
          <label class="form-label">Payment Date</label>
          <input type="date" name="payment_date" class="form-input" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Note (optional)</label>
          <input type="text" name="note" class="form-input" placeholder="e.g. bKash transfer">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary btn-block">Save Payment</button>
      </div>
    </form>
  </div>
</div>

<!-- Partial Payment Modal -->
<div id="partialPaymentModal" class="modal-overlay hidden">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Record Partial Payment</h3>
      <button type="button" class="modal-close" data-close-modal="partialPaymentModal">&#x2715;</button>
    </div>
    <form method="POST" id="partialForm">
      <?= csrfField() ?>
      <input type="hidden" name="action"       value="record_payment">
      <input type="hidden" name="from_user_id" id="partialFrom">
      <input type="hidden" name="to_user_id"   id="partialTo">
      <div class="modal-body">
        <div id="partialDesc" style="font-size:13px;color:var(--text-muted);margin-bottom:12px;"></div>
        <div class="form-group">
          <label class="form-label">Amount (৳)</label>
          <input type="number" name="amount" id="partialAmt" class="form-input" min="0.01" step="0.01" required>
        </div>
        <div class="form-group">
          <label class="form-label">Payment Date</label>
          <input type="date" name="payment_date" class="form-input" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Note (optional)</label>
          <input type="text" name="note" class="form-input" placeholder="e.g. bKash, cash…">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary btn-block">Save Partial Payment</button>
      </div>
    </form>
  </div>
</div>

<script>
function openPartialForm(fromId, toId, fromName, toName, maxAmount) {
    document.getElementById('partialFrom').value = fromId;
    document.getElementById('partialTo').value   = toId;
    document.getElementById('partialAmt').value  = maxAmount;
    document.getElementById('partialAmt').max    = maxAmount;
    document.getElementById('partialDesc').textContent =
        fromName + ' → ' + toName + ' (max: ৳' + parseFloat(maxAmount).toFixed(2) + ')';
    openModal('partialPaymentModal');
}
</script>

<?php include __DIR__ . '/includes/user-footer.php'; ?>
