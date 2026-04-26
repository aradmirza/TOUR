  </main><!-- /.page-content -->
</div><!-- /.main-wrapper -->

<!-- ===== MOBILE BOTTOM NAV ===== -->
<nav class="bottom-nav">
  <a href="<?= $baseUrl ?>dashboard.php"     class="bnav-item <?= $activePage==='dashboard'    ?'active':'' ?>">
    <span class="bnav-icon">🏠</span>
    <span class="bnav-label">Home</span>
  </a>
  <a href="<?= $baseUrl ?>groups.php"        class="bnav-item <?= $activePage==='groups'       ?'active':'' ?>">
    <span class="bnav-icon">✈️</span>
    <span class="bnav-label">Tours</span>
  </a>
  <a href="<?= $baseUrl ?>feed.php"          class="bnav-item <?= $activePage==='feed'         ?'active':'' ?>">
    <span class="bnav-icon">📰</span>
    <span class="bnav-label">Feed</span>
  </a>
  <a href="<?= $baseUrl ?>notifications.php" class="bnav-item <?= $activePage==='notifications'?'active':'' ?>">
    <span class="bnav-icon">🔔</span>
    <span class="bnav-label">Alerts</span>
    <?php if ($unreadCount > 0): ?><span class="bnav-badge"><?= $unreadCount ?></span><?php endif; ?>
  </a>
  <a href="<?= $baseUrl ?>profile.php"       class="bnav-item <?= $activePage==='profile'      ?'active':'' ?>">
    <span class="bnav-icon">👤</span>
    <span class="bnav-label">Me</span>
  </a>
</nav>

<!-- ===== TOAST CONTAINER ===== -->
<div id="toastContainer" style="position:fixed;bottom:80px;left:50%;transform:translateX(-50%);z-index:9999;display:flex;flex-direction:column;gap:8px;align-items:center;pointer-events:none;"></div>

<script src="<?= $baseUrl ?>../assets/js/main.js"></script>
<script>
// Show toast notification
function showToast(msg, type) {
    const t = document.createElement('div');
    t.className = 'toast toast-' + (type || 'success');
    t.textContent = msg;
    document.getElementById('toastContainer').appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 400); }, 3000);
}

// Confirm dialog for delete/danger actions
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', function(e) {
        if (!confirm(this.dataset.confirm || 'Are you sure?')) e.preventDefault();
    });
});

// Auto dismiss alerts
document.querySelectorAll('.alert').forEach(el => {
    setTimeout(() => {
        el.style.transition = 'opacity 0.5s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 500);
    }, 4500);
});
</script>
</body>
</html>
