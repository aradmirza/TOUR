<?php
// Variables expected: $pageTitle (string), $activePage (string)
$admin = currentAdmin();
$aBase = adminBaseUrl();
$rBase = rootBaseUrl();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? 'Dashboard') ?> — TourMate Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $aBase ?>assets/css/admin.css">
</head>
<body class="admin-body">

<!-- ========== SIDEBAR ========== -->
<aside class="admin-sidebar" id="adminSidebar">

  <div class="admin-sidebar-logo">
    <div class="admin-logo-icon">✈️</div>
    <div class="admin-logo-text">
      <span class="admin-logo-name">TourMate</span>
      <span class="admin-logo-sub">ADMIN</span>
    </div>
  </div>

  <nav class="admin-nav">
    <a href="<?= $aBase ?>dashboard.php"      class="admin-nav-item <?= $activePage==='dashboard'?'active':'' ?>">
      <span class="admin-nav-icon">📊</span> Dashboard
    </a>
    <a href="<?= $aBase ?>users.php"          class="admin-nav-item <?= $activePage==='users'?'active':'' ?>">
      <span class="admin-nav-icon">👥</span> Users
    </a>
    <a href="<?= $aBase ?>groups.php"         class="admin-nav-item <?= $activePage==='groups'?'active':'' ?>">
      <span class="admin-nav-icon">✈️</span> Tour Groups
    </a>
    <a href="<?= $aBase ?>expenses.php"       class="admin-nav-item <?= $activePage==='expenses'?'active':'' ?>">
      <span class="admin-nav-icon">💸</span> Expenses
    </a>
    <a href="<?= $aBase ?>posts.php"          class="admin-nav-item <?= $activePage==='posts'?'active':'' ?>">
      <span class="admin-nav-icon">📰</span> Posts
    </a>
    <a href="<?= $aBase ?>comments.php"       class="admin-nav-item <?= $activePage==='comments'?'active':'' ?>">
      <span class="admin-nav-icon">💬</span> Comments
    </a>
    <a href="<?= $aBase ?>notifications.php"  class="admin-nav-item <?= $activePage==='notifications'?'active':'' ?>">
      <span class="admin-nav-icon">🔔</span> Send Notification
    </a>
    <a href="<?= $aBase ?>reports.php"        class="admin-nav-item <?= $activePage==='reports'?'active':'' ?>">
      <span class="admin-nav-icon">📈</span> Reports
    </a>
    <a href="<?= $aBase ?>settings.php"       class="admin-nav-item <?= $activePage==='settings'?'active':'' ?>">
      <span class="admin-nav-icon">⚙️</span> Settings
    </a>
  </nav>

  <div class="admin-sidebar-footer">
    <div class="admin-sidebar-user">
      <div class="admin-avatar-initials"><?= strtoupper(substr($admin['name'], 0, 2)) ?></div>
      <div class="admin-sidebar-user-info">
        <div class="admin-sidebar-user-name"><?= e($admin['name']) ?></div>
        <div class="admin-sidebar-user-role">Administrator</div>
      </div>
    </div>
    <a href="<?= $aBase ?>logout.php" class="admin-logout-btn" title="Sign Out">⏏</a>
  </div>

</aside>
<!-- ========== END SIDEBAR ========== -->

<div class="admin-sidebar-overlay" id="adminSidebarOverlay"></div>

<!-- ========== MAIN ========== -->
<div class="admin-main-wrapper">

  <header class="admin-topbar">
    <div class="admin-topbar-left">
      <button class="admin-sidebar-toggle" id="adminSidebarToggle" aria-label="Toggle menu">☰</button>
      <span class="admin-topbar-title"><?= e($pageTitle ?? 'Dashboard') ?></span>
    </div>
    <div class="admin-topbar-right">
      <a href="<?= $rBase ?>" target="_blank" class="admin-topbar-link">🌐 View Site</a>
      <a href="<?= $aBase ?>logout.php" class="admin-topbar-logout">Sign Out</a>
    </div>
  </header>

  <main class="admin-content">
    <?= showAdminFlash() ?>
