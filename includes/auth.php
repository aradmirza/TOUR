<?php
// ============================================================
// TourMate Social — Auth & Helper Functions
// ============================================================

// --- Session Auth -------------------------------------------

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . getBaseUrl() . 'login.php');
        exit;
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function currentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function currentUser() {
    return [
        'id'    => $_SESSION['user_id']    ?? null,
        'name'  => $_SESSION['user_name']  ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'photo' => $_SESSION['user_photo'] ?? null,
    ];
}

function setUserSession($user) {
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_photo'] = $user['profile_photo'];
}

// --- CSRF Protection -----------------------------------------

function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

function verifyCsrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        flash('Invalid request. Please try again.', 'danger');
        return false;
    }
    return true;
}

// --- Flash Messages ------------------------------------------

function flash($message, $type = 'success') {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function showFlash() {
    $f = getFlash();
    if (!$f) return '';
    $icons = [
        'success' => '✅',
        'danger'  => '❌',
        'warning' => '⚠️',
        'info'    => 'ℹ️',
    ];
    $icon = $icons[$f['type']] ?? 'ℹ️';
    return '<div class="alert alert-' . e($f['type']) . '">' . $icon . ' ' . e($f['message']) . '</div>';
}

// --- Group Access Checks ------------------------------------

function isGroupMember($db, $groupId, $userId) {
    $stmt = $db->prepare("SELECT id FROM group_members WHERE group_id=? AND user_id=?");
    $stmt->bind_param("ii", $groupId, $userId);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function isGroupAdmin($db, $groupId, $userId) {
    $stmt = $db->prepare("SELECT id FROM group_members WHERE group_id=? AND user_id=? AND role='admin'");
    $stmt->bind_param("ii", $groupId, $userId);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function requireGroupMember($db, $groupId) {
    $userId = currentUserId();
    if (!$userId || !isGroupMember($db, $groupId, $userId)) {
        flash('You are not a member of this group.', 'danger');
        header('Location: dashboard.php');
        exit;
    }
}

// --- File Upload --------------------------------------------

function uploadFile($file, $uploadDir, $prefix = '') {
    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload error.'];
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'File too large. Max 5MB allowed.'];
    }

    // Validate MIME type using finfo
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedMimes)) {
        return ['success' => false, 'error' => 'Only JPG, PNG, GIF, WEBP images allowed.'];
    }

    // Build a safe filename
    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = ($prefix ? $prefix . '_' : '') . uniqid() . '_' . time() . '.' . $ext;

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $destination = rtrim($uploadDir, '/') . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'error' => 'Could not save file.'];
    }

    return ['success' => true, 'filename' => $filename];
}

function deleteFile($uploadDir, $filename) {
    if ($filename && file_exists($uploadDir . $filename)) {
        unlink($uploadDir . $filename);
    }
}

// --- Notifications ------------------------------------------

function sendNotification($db, $userId, $fromUserId, $groupId, $type, $message, $link = '') {
    if ($userId == $fromUserId) return; // don't notify yourself
    $stmt = $db->prepare(
        "INSERT INTO notifications (user_id, from_user_id, group_id, type, message, link)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("iiisss", $userId, $fromUserId, $groupId, $type, $message, $link);
    $stmt->execute();
}

function countUnreadNotifications($db, $userId) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_row()[0];
}

// --- Expense Settlement -------------------------------------

function getSettlementData($db, $groupId) {
    // Get all group members
    $stmt = $db->prepare(
        "SELECT gm.user_id, u.name, u.profile_photo
         FROM group_members gm
         JOIN users u ON u.id = gm.user_id
         WHERE gm.group_id = ?"
    );
    $stmt->bind_param("i", $groupId);
    $stmt->execute();
    $rows    = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $members = [];
    foreach ($rows as $r) {
        $members[$r['user_id']] = [
            'user_id'     => $r['user_id'],
            'name'        => $r['name'],
            'photo'       => $r['profile_photo'],
            'total_paid'  => 0.0,
            'total_share' => 0.0,
            'balance'     => 0.0,
        ];
    }

    // Total paid per member
    $stmt = $db->prepare(
        "SELECT paid_by, SUM(amount) AS total FROM expenses WHERE group_id=? GROUP BY paid_by"
    );
    $stmt->bind_param("i", $groupId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (isset($members[$row['paid_by']])) {
            $members[$row['paid_by']]['total_paid'] = (float)$row['total'];
        }
    }

    // Total share per member
    $stmt = $db->prepare(
        "SELECT es.user_id, SUM(es.amount) AS total
         FROM expense_splits es
         JOIN expenses e ON e.id = es.expense_id
         WHERE e.group_id = ?
         GROUP BY es.user_id"
    );
    $stmt->bind_param("i", $groupId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (isset($members[$row['user_id']])) {
            $members[$row['user_id']]['total_share'] = (float)$row['total'];
        }
    }

    // Calculate balance
    foreach ($members as &$m) {
        $m['balance'] = $m['total_paid'] - $m['total_share'];
    }
    unset($m);

    $balances = array_values($members);

    // Greedy debt settlement
    $creditors = array_values(array_filter($balances, fn($b) => $b['balance'] >  0.01));
    $debtors   = array_values(array_filter($balances, fn($b) => $b['balance'] < -0.01));

    $settlements = [];
    $i = 0; $j = 0;
    while ($i < count($creditors) && $j < count($debtors)) {
        $amount = min($creditors[$i]['balance'], abs($debtors[$j]['balance']));
        if ($amount > 0.01) {
            $settlements[] = [
                'from_id'   => $debtors[$j]['user_id'],
                'from_name' => $debtors[$j]['name'],
                'to_id'     => $creditors[$i]['user_id'],
                'to_name'   => $creditors[$i]['name'],
                'amount'    => round($amount, 2),
            ];
        }
        $creditors[$i]['balance'] -= $amount;
        $debtors[$j]['balance']   += $amount;
        if ($creditors[$i]['balance'] < 0.01) $i++;
        if (abs($debtors[$j]['balance']) < 0.01) $j++;
    }

    return ['members' => $balances, 'settlements' => $settlements];
}

// --- Utility Helpers ----------------------------------------

function e($str) {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

function timeAgo($datetime) {
    if (!$datetime) return '';
    $diff = time() - strtotime($datetime);
    if ($diff < 60)       return 'just now';
    if ($diff < 3600)     return floor($diff / 60) . 'm ago';
    if ($diff < 86400)    return floor($diff / 3600) . 'h ago';
    if ($diff < 604800)   return floor($diff / 86400) . 'd ago';
    return date('M j, Y', strtotime($datetime));
}

function formatMoney($amount) {
    return '৳ ' . number_format((float)$amount, 2);
}

function getTourStatus($startDate, $returnDate) {
    $now    = time();
    $start  = strtotime($startDate);
    $end    = strtotime($returnDate . ' 23:59:59');
    if ($now < $start) return 'upcoming';
    if ($now <= $end)  return 'running';
    return 'completed';
}

function avatarHtml($name, $photo, $size = 40) {
    $styles = "width:{$size}px;height:{$size}px;";
    if ($photo && file_exists(UPLOAD_PROFILE . $photo)) {
        $url = 'uploads/profile/' . e($photo);
        return "<img src=\"{$url}\" alt=\"" . e($name) . "\" class=\"avatar\" style=\"{$styles}\">";
    }
    // Build initials avatar
    $parts    = explode(' ', trim($name));
    $initials = '';
    foreach ($parts as $p) $initials .= strtoupper(substr($p, 0, 1));
    $initials = substr($initials, 0, 2);
    $colors   = ['#3b82f6','#8b5cf6','#ec4899','#f97316','#10b981','#0891b2'];
    $color    = $colors[abs(crc32($name)) % count($colors)];
    $fSize    = round($size * 0.38);
    return "<div class=\"avatar avatar-initials\" style=\"{$styles}background:{$color};font-size:{$fSize}px\">{$initials}</div>";
}

function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host     = $_SERVER['HTTP_HOST'];
    $dir      = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    return $protocol . $host . $dir . '/';
}

function categoryIcon($cat) {
    $map = [
        'transport' => '🚌',
        'food'      => '🍽️',
        'hotel'     => '🏨',
        'ticket'    => '🎟️',
        'shopping'  => '🛍️',
        'emergency' => '🚨',
        'other'     => '📦',
    ];
    return $map[$cat] ?? '📦';
}

function categoryBadge($cat) {
    $map = [
        'transport' => 'badge-blue',
        'food'      => 'badge-orange',
        'hotel'     => 'badge-purple',
        'ticket'    => 'badge-pink',
        'shopping'  => 'badge-yellow',
        'emergency' => 'badge-red',
        'other'     => 'badge-gray',
    ];
    return $map[$cat] ?? 'badge-gray';
}
