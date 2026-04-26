  </main>
  <!-- END PAGE CONTENT -->

</div>
<!-- END MAIN WRAPPER -->

<!-- ========== BOTTOM NAVIGATION (mobile) ========== -->
<nav class="bottom-nav">
  <a href="<?= $baseUrl ?>dashboard.php"    class="bnav-item <?= ($activePage==='dashboard')?'active':'' ?>">
    <span class="bnav-icon">🏠</span>
    <span class="bnav-label">Home</span>
  </a>
  <a href="<?= $baseUrl ?>groups.php"       class="bnav-item <?= ($activePage==='groups')?'active':'' ?>">
    <span class="bnav-icon">✈️</span>
    <span class="bnav-label">Tours</span>
  </a>
  <a href="<?= $baseUrl ?>feed.php"         class="bnav-item <?= ($activePage==='feed')?'active':'' ?>">
    <span class="bnav-icon">📰</span>
    <span class="bnav-label">Feed</span>
  </a>
  <a href="<?= $baseUrl ?>notifications.php" class="bnav-item <?= ($activePage==='notifications')?'active':'' ?>" style="position:relative">
    <span class="bnav-icon">🔔</span>
    <?php if ($unreadCount > 0): ?>
      <span class="bnav-badge"><?= $unreadCount ?></span>
    <?php endif; ?>
    <span class="bnav-label">Alerts</span>
  </a>
  <a href="<?= $baseUrl ?>profile.php"      class="bnav-item <?= ($activePage==='profile')?'active':'' ?>">
    <span class="bnav-icon">👤</span>
    <span class="bnav-label">Profile</span>
  </a>
</nav>
<!-- END BOTTOM NAV -->

<script src="<?= $baseUrl ?>assets/js/main.js"></script>
</body>
</html>
