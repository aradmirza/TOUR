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

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'record_payment') {
        $fromId  = (int)($_POST['from_user_id'] ?? 0);
        $toId    = (int)($_POST['to_user_id']   ?? 0);
        $amount  = (float)str_replace(',', '', $_POST['amount'] ?? 0);
        $note    = trim($_POST['note'] ?? '');
        $payDate = trim($_POST['payment_date'] ?? '') ?: date('Y-m-d');

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

    if ($action === 'delete_payment' && $isAdmin) {
        $payId = (int)($_POST['payment_id'] ?? 0);
        if ($payId) {
            $stmt2 = $db->prepare("DELETE FROM group_payments WHERE id=? AND group_id=?");
            $stmt2->bind_param("ii", $payId, $groupId);
            $stmt2->execute();
            flash('Payment record removed.', 'success');
        }
        header('Location: settlement.php?id=' . $groupId); exit;
    }
}

$data        = getSettlementData($db, $groupId);
$members     = $data['members'];
$settlements = $data['settlements'];

// Category breakdown
$stmt = $db->prepare(
    "SELECT category, COALESCE(SUM(amount),0) AS total FROM expenses WHERE group_id=? GROUP BY category ORDER BY total DESC"
);
$stmt->bind_param("i", $groupId);
$stmt->execute();
$categoryBreakdown = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$totalExpense = array_sum(array_column($members, 'total_paid'));

// Recorded payments
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

$paidMap = [];
foreach ($payments as $p) {
    $f = $p['from_user_id'];
    $t = $p['to_user_id'];
    $paidMap[$f][$t] = ($paidMap[$f][$t] ?? 0) + (float)$p['amount'];
}

$pageTitle  = 'Settlement — ' . e($group['name']);
$activePage = 'groups';
$groupTab   = 'settlement';
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
    <h1>Expense Settlement</h1>
    <p>Total group expense: <?= formatMoney($totalExpense) ?></p>
  </div>
  <div style="display:flex;gap:8px;">
    <button class="btn btn-primary" onclick="openModal('recordPaymentModal')">+ Record Payment</button>
    <a href="expenses.php?id=<?= $groupId ?>" class="btn btn-outline">← Expenses</a>
  </div>
</div>

<div class="grid-2">

  <!-- Member Balances + Category -->
  <div>
    <div class="card mb-3">
      <div class="card-header">
        <h3 class="card-title">💳 Member Balances</h3>
      </div>
      <div class="card-body">
        <?php if (!$members): ?>
          <p class="text-muted">No members found.</p>
        <?php else: ?>
          <?php foreach ($members as $m): ?>
          <div class="balance-row">
            <?= avatarHtml($m['name'], $m['photo'], 40) ?>
            <div class="balance-info">
              <div class="balance-name"><?= e($m['name']) ?><?= $m['user_id']==$userId?' (You)':'' ?></div>
              <div class="balance-amounts">
                Paid: <?= formatMoney($m['total_paid']) ?> · Share: <?= formatMoney($m['total_share']) ?>
              </div>
            </div>
            <div class="balance-value <?= $m['balance']>0.01?'balance-positive':($m['balance']<-0.01?'balance-negative':'balance-zero') ?>">
              <?php if ($m['balance'] > 0.01): ?>
                +<?= formatMoney($m['balance']) ?>
              <?php elseif ($m['balance'] < -0.01): ?>
                <?= formatMoney($m['balance']) ?>
              <?php else: ?>
                Settled ✓
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Category Breakdown -->
    <?php if ($categoryBreakdown): ?>
    <div class="card mb-3">
      <div class="card-header">
        <h3 class="card-title">📊 By Category</h3>
      </div>
      <div class="card-body">
        <?php foreach ($categoryBreakdown as $cb): ?>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
          <span style="font-size:18px;"><?= categoryIcon($cb['category']) ?></span>
          <div style="flex:1;">
            <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
              <span style="font-size:13px;font-weight:500;"><?= ucfirst($cb['category']) ?></span>
              <span style="font-size:13px;font-weight:700;"><?= formatMoney($cb['total']) ?></span>
            </div>
            <div style="height:6px;background:var(--bg);border-radius:10px;overflow:hidden;">
              <?php $pct = $totalExpense > 0 ? ($cb['total']/$totalExpense*100) : 0; ?>
              <div style="height:100%;width:<?= round($pct) ?>%;background:var(--primary);border-radius:10px;"></div>
            </div>
          </div>
          <span class="text-muted text-small"><?= round($pct) ?>%</span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Payment History -->
    <?php if ($payments): ?>
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">📜 Payment History</h3>
        <span class="text-muted text-small"><?= count($payments) ?> record<?= count($payments)!=1?'s':'' ?></span>
      </div>
      <div class="card-body" style="padding:0 16px 8px;">
        <?php foreach ($payments as $p): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border);">
          <div style="flex:1;min-width:0;">
            <div style="font-size:13px;font-weight:600;">
              <?= e($p['from_name']) ?> → <?= e($p['to_name']) ?>
            </div>
            <?php if ($p['note']): ?>
            <div class="text-muted text-small"><?= e($p['note']) ?></div>
            <?php endif; ?>
            <div class="text-muted text-small"><?= date('M j, Y', strtotime($p['payment_date'])) ?></div>
          </div>
          <div style="font-weight:700;color:var(--success);white-space:nowrap;">+<?= formatMoney($p['amount']) ?></div>
          <?php if ($isAdmin): ?>
          <form method="POST">
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
  </div>

  <!-- Settlement Transactions -->
  <div>
    <div class="card mb-3">
      <div class="card-header">
        <h3 class="card-title">💰 Who Pays Who</h3>
      </div>
      <div class="card-body">
        <?php if (!$settlements): ?>
          <div class="empty-state" style="padding:30px 20px;">
            <div class="empty-icon">✅</div>
            <div class="empty-title">All settled!</div>
            <div class="empty-desc">Everyone's expenses are balanced. No payments needed.</div>
          </div>
        <?php else: ?>
          <p class="text-muted" style="font-size:12.5px;margin-bottom:14px;">
            Minimum transactions to settle all debts:
          </p>
          <?php foreach ($settlements as $s): ?>
          <?php
            $alreadyPaid = $paidMap[$s['from_id']][$s['to_id']] ?? 0;
            $remaining   = max(0, round($s['amount'] - $alreadyPaid, 2));
            $isFullyPaid = $remaining < 0.01;
            $canMark     = ($s['from_id'] == $userId || $s['to_id'] == $userId || $isAdmin);
          ?>
          <div class="settlement-row" style="<?= $isFullyPaid ? 'opacity:0.5;' : '' ?>">
            <div><?= avatarHtml($s['from_name'], null, 36) ?></div>
            <div class="settlement-names">
              <div class="settlement-from"><?= e($s['from_name']) ?></div>
              <div class="settlement-to">→ <?= e($s['to_name']) ?></div>
            </div>
            <div class="settlement-arrow">→</div>
            <div><?= avatarHtml($s['to_name'], null, 36) ?></div>
            <div style="text-align:right;">
              <?php if ($isFullyPaid): ?>
                <div style="color:var(--success);font-weight:700;">✅ Paid</div>
              <?php else: ?>
                <div class="settlement-amount"><?= formatMoney($remaining) ?></div>
                <?php if ($alreadyPaid > 0.01): ?>
                <div class="text-muted text-small">of <?= formatMoney($s['amount']) ?></div>
                <?php endif; ?>
                <?php if ($canMark): ?>
                <div style="display:flex;gap:4px;margin-top:6px;justify-content:flex-end;">
                  <form method="POST">
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
                  <button class="btn btn-sm btn-outline"
                          onclick="openPartialForm(<?= $s['from_id'] ?>, <?= $s['to_id'] ?>, '<?= e($s['from_name']) ?>', '<?= e($s['to_name']) ?>', <?= $remaining ?>)">
                    Partial
                  </button>
                </div>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Summary Table -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">📋 Summary Table</h3>
      </div>
      <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
          <thead>
            <tr style="background:var(--bg);">
              <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--text-muted);">Member</th>
              <th style="padding:10px 16px;text-align:right;font-weight:600;color:var(--text-muted);">Paid</th>
              <th style="padding:10px 16px;text-align:right;font-weight:600;color:var(--text-muted);">Share</th>
              <th style="padding:10px 16px;text-align:right;font-weight:600;color:var(--text-muted);">Balance</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($members as $m): ?>
            <tr style="border-top:1px solid var(--border);">
              <td style="padding:10px 16px;">
                <div style="display:flex;align-items:center;gap:8px;">
                  <?= avatarHtml($m['name'], $m['photo'], 28) ?>
                  <span><?= e($m['name']) ?></span>
                </div>
              </td>
              <td style="padding:10px 16px;text-align:right;"><?= formatMoney($m['total_paid']) ?></td>
              <td style="padding:10px 16px;text-align:right;"><?= formatMoney($m['total_share']) ?></td>
              <td style="padding:10px 16px;text-align:right;font-weight:700;
                color:<?= $m['balance']>0.01?'var(--success)':($m['balance']<-0.01?'var(--danger)':'var(--text-muted)') ?>">
                <?= $m['balance'] > 0.01 ? '+' : '' ?><?= formatMoney($m['balance']) ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr style="border-top:2px solid var(--border);background:var(--bg);">
              <td style="padding:10px 16px;font-weight:700;">Total</td>
              <td style="padding:10px 16px;text-align:right;font-weight:700;"><?= formatMoney($totalExpense) ?></td>
              <td style="padding:10px 16px;text-align:right;font-weight:700;"><?= formatMoney($totalExpense) ?></td>
              <td style="padding:10px 16px;text-align:right;"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

</div>

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
          <select name="from_user_id" class="form-control" required>
            <option value="">— Select payer —</option>
            <?php foreach ($members as $m): ?>
            <option value="<?= $m['user_id'] ?>" <?= $m['user_id']==$userId?'selected':'' ?>><?= e($m['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Paid to?</label>
          <select name="to_user_id" class="form-control" required>
            <option value="">— Select recipient —</option>
            <?php foreach ($members as $m): ?>
            <option value="<?= $m['user_id'] ?>"><?= e($m['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Amount</label>
          <input type="number" name="amount" class="form-control" min="0.01" step="0.01" placeholder="0.00" required>
        </div>
        <div class="form-group">
          <label class="form-label">Payment Date</label>
          <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Note (optional)</label>
          <input type="text" name="note" class="form-control" placeholder="e.g. bKash transfer, cash">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Save Payment</button>
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
          <label class="form-label">Amount</label>
          <input type="number" name="amount" id="partialAmt" class="form-control" min="0.01" step="0.01" required>
        </div>
        <div class="form-group">
          <label class="form-label">Payment Date</label>
          <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Note (optional)</label>
          <input type="text" name="note" class="form-control" placeholder="e.g. bKash, cash…">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Save Partial Payment</button>
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

<?php include 'includes/footer.php'; ?>
