  </main><!-- /.admin-content -->
</div><!-- /.admin-main-wrapper -->

<script>
// Sidebar toggle (mobile)
const sidebar  = document.getElementById('adminSidebar');
const overlay  = document.getElementById('adminSidebarOverlay');
const toggle   = document.getElementById('adminSidebarToggle');

if (toggle && sidebar && overlay) {
  toggle.addEventListener('click', () => {
    sidebar.classList.toggle('open');
    overlay.classList.toggle('open');
  });
  overlay.addEventListener('click', () => {
    sidebar.classList.remove('open');
    overlay.classList.remove('open');
  });
}

// Auto-dismiss alerts after 4 s
document.querySelectorAll('.admin-alert').forEach(el => {
  setTimeout(() => {
    el.style.transition = 'opacity 0.5s';
    el.style.opacity = '0';
    setTimeout(() => el.remove(), 500);
  }, 4000);
});

// Confirm delete
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', function(e) {
    if (!confirm(this.dataset.confirm || 'Are you sure?')) e.preventDefault();
  });
});
</script>
</body>
</html>
