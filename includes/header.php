<?php
// header.php — HTML <head> + Topbar + Sidebar open
// Variables expected:  $pageTitle (string)  $activePage (string)
$user          = currentUser();
$unreadCount   = isLoggedIn() ? countUnreadNotifications($db, $user['id']) : 0;
$baseUrl       = getBaseUrl();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="TourMate Social — Plan tours, split expenses, share memories.">
  <title><?= e($pageTitle ?? 'TourMate Social') ?> — <?= SITE_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $baseUrl ?>assets/css/style.css">
</head>
<body class="app-body">

<!-- ========== SIDEBAR (desktop) ========== -->
<nav class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon">✈️</div>
    <div class="logo-text">
      <span class="logo-name">TourMate</span>
      <span class="logo-sub">SOCIAL</span>
    </div>
  </div>

  <div class="sidebar-nav">
    <a href="<?= $baseUrl ?>dashboard.php"       class="nav-item <?= ($activePage==='dashboard')?'active':'' ?>">
      <span class="nav-icon">🏠</span> Dashboard
    </a>
    <a href="<?= $baseUrl ?>groups.php"           class="nav-item <?= ($activePage==='groups')?'active':'' ?>">
      <span class="nav-icon">✈️</span> My Tours
    </a>
    <a href="<?= $baseUrl ?>feed.php"             class="nav-item <?= ($activePage==='feed')?'active':'' ?>">
      <span class="nav-icon">📰</span> Social Feed
    </a>
    <a href="<?= $baseUrl ?>notifications.php"    class="nav-item <?= ($activePage==='notifications')?'active':'' ?>">
      <span class="nav-icon">🔔</span> Notifications
      <?php if ($unreadCount > 0): ?>
        <span class="badge-notif"><?= $unreadCount ?></span>
      <?php endif; ?>
    </a>
  </div>

  <div class="sidebar-user">
    <a href="<?= $baseUrl ?>profile.php" class="sidebar-user-link">
      <?= avatarHtml($user['name'], $user['photo'], 36) ?>
      <div class="sidebar-user-info">
        <span class="sidebar-user-name"><?= e($user['name']) ?></span>
        <span class="sidebar-user-email"><?= e($user['email']) ?></span>
      </div>
    </a>
    <a href="<?= $baseUrl ?>logout.php" class="btn-logout" title="Sign out">⏏</a>
  </div>
</nav>
<!-- ========== END SIDEBAR ========== -->

<!-- ========== MAIN WRAPPER ========== -->
<div class="main-wrapper">

  <!-- TOP BAR -->
  <header class="topbar">
    <div class="topbar-left">
      <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">☰</button>
      <span class="topbar-title"><?= e($pageTitle ?? 'Dashboard') ?></span>
    </div>
    <div class="topbar-right">
      <a href="<?= $baseUrl ?>notifications.php" class="topbar-icon" aria-label="Notifications">
        🔔
        <?php if ($unreadCount > 0): ?>
          <span class="topbar-badge"><?= $unreadCount ?></span>
        <?php endif; ?>
      </a>
      <a href="<?= $baseUrl ?>profile.php" class="topbar-avatar">
        <?= avatarHtml($user['name'], $user['photo'], 34) ?>
      </a>
    </div>
  </header>
  <!-- END TOP BAR -->

  <!-- PAGE CONTENT -->
  <main class="page-content">
    <!-- Overlay for mobile sidebar -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Flash messages -->
    <?= showFlash() ?>
