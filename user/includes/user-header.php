<?php
// user-header.php — must be included AFTER session_start(), db.php, auth.php, requireLogin()
// $pageTitle and $activePage must be set by the calling page.

$user        = currentUser();
$unreadCount = isLoggedIn() ? countUnreadNotifications($db, $user['id']) : 0;
$baseUrl     = getBaseUrl(); // e.g. http://host/user/

// Helper: upload URL relative to /user/ pages
function uUrl($folder, $filename) {
    return '../uploads/' . $folder . '/' . htmlspecialchars($filename, ENT_QUOTES, 'UTF-8');
}

// Avatar with correct path from /user/ context
function uAvatar($name, $photo, $size = 40) {
    $st = "width:{$size}px;height:{$size}px;border-radius:50%;object-fit:cover;flex-shrink:0;";
    if ($photo && file_exists(UPLOAD_PROFILE . $photo)) {
        return '<img src="' . uUrl('profile', $photo) . '" alt="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" style="' . $st . '">';
    }
    $parts = explode(' ', trim($name));
    $init  = '';
    foreach ($parts as $p) $init .= strtoupper(substr($p, 0, 1));
    $init  = substr($init, 0, 2) ?: '?';
    $cols  = ['#3b82f6','#8b5cf6','#ec4899','#f97316','#10b981','#0891b2'];
    $col   = $cols[abs(crc32($name)) % count($cols)];
    $fs    = round($size * 0.38);
    return '<div style="' . $st . 'background:' . $col . ';font-size:' . $fs . 'px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;">' . $init . '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? 'TourMate') ?> — TourMate Social</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $baseUrl ?>../assets/css/style.css">
  <link rel="stylesheet" href="<?= $baseUrl ?>../assets/css/user-panel.css">
</head>
<body class="app-body">

<!-- ===== SIDEBAR (desktop) ===== -->
<nav class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon">✈️</div>
    <div class="logo-text">
      <span class="logo-name">TourMate</span>
      <span class="logo-sub">SOCIAL</span>
    </div>
  </div>

  <div class="sidebar-nav">
    <a href="<?= $baseUrl ?>dashboard.php"     class="nav-item <?= $activePage==='dashboard'    ?'active':'' ?>">
      <span class="nav-icon">🏠</span> Dashboard
    </a>
    <a href="<?= $baseUrl ?>groups.php"        class="nav-item <?= $activePage==='groups'       ?'active':'' ?>">
      <span class="nav-icon">✈️</span> My Tours
    </a>
    <a href="<?= $baseUrl ?>feed.php"          class="nav-item <?= $activePage==='feed'         ?'active':'' ?>">
      <span class="nav-icon">📰</span> Social Feed
    </a>
    <a href="<?= $baseUrl ?>gallery.php"       class="nav-item <?= $activePage==='gallery'      ?'active':'' ?>">
      <span class="nav-icon">🖼️</span> Gallery
    </a>
    <a href="<?= $baseUrl ?>notifications.php" class="nav-item <?= $activePage==='notifications'?'active':'' ?>">
      <span class="nav-icon">🔔</span> Notifications
      <?php if ($unreadCount > 0): ?>
        <span class="badge-notif"><?= $unreadCount ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= $baseUrl ?>settings.php"      class="nav-item <?= $activePage==='settings'     ?'active':'' ?>">
      <span class="nav-icon">⚙️</span> Settings
    </a>
  </div>

  <div class="sidebar-user">
    <a href="<?= $baseUrl ?>profile.php" class="sidebar-user-link">
      <?= uAvatar($user['name'], $user['photo'], 36) ?>
      <div class="sidebar-user-info">
        <span class="sidebar-user-name"><?= e($user['name']) ?></span>
        <span class="sidebar-user-email"><?= e($user['email']) ?></span>
      </div>
    </a>
    <a href="<?= $baseUrl ?>../logout.php" class="btn-logout" title="Sign out">⏏</a>
  </div>
</nav>

<!-- ===== MAIN WRAPPER ===== -->
<div class="main-wrapper">

  <!-- TOP BAR -->
  <header class="topbar">
    <div class="topbar-left">
      <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">☰</button>
      <span class="topbar-title"><?= e($pageTitle ?? 'Dashboard') ?></span>
    </div>
    <div class="topbar-right">
      <a href="<?= $baseUrl ?>notifications.php" class="topbar-icon" aria-label="Notifications">
        🔔<?php if ($unreadCount > 0): ?><span class="topbar-badge"><?= $unreadCount ?></span><?php endif; ?>
      </a>
      <a href="<?= $baseUrl ?>profile.php" class="topbar-avatar">
        <?= uAvatar($user['name'], $user['photo'], 34) ?>
      </a>
    </div>
  </header>

  <!-- PAGE CONTENT -->
  <main class="page-content">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <?= showFlash() ?>
