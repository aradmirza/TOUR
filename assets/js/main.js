/* TourMate Social — Main JavaScript */

/* ============================================================
   SIDEBAR TOGGLE
   ============================================================ */
(function() {
  var sidebar = document.getElementById('sidebar');
  var toggle  = document.getElementById('sidebarToggle');
  var overlay = document.getElementById('sidebarOverlay');

  if (!sidebar || !toggle) return;

  toggle.addEventListener('click', function() {
    sidebar.classList.toggle('open');
    if (overlay) overlay.classList.toggle('open');
  });

  if (overlay) {
    overlay.addEventListener('click', function() {
      sidebar.classList.remove('open');
      overlay.classList.remove('open');
    });
  }
})();

/* ============================================================
   COUNTDOWN TIMER
   ============================================================ */
(function() {
  var el = document.getElementById('countdown');
  if (!el) return;

  var startDate = el.dataset.start;
  if (!startDate) return;

  var target  = new Date(startDate + 'T00:00:00').getTime();
  var daysEl  = document.getElementById('cd-days');
  var hoursEl = document.getElementById('cd-hours');
  var minsEl  = document.getElementById('cd-mins');
  var secsEl  = document.getElementById('cd-secs');

  function pad(n) { return String(n).padStart(2, '0'); }

  function tick() {
    var now  = Date.now();
    var diff = target - now;

    if (diff <= 0) {
      if (daysEl)  daysEl.textContent  = '00';
      if (hoursEl) hoursEl.textContent = '00';
      if (minsEl)  minsEl.textContent  = '00';
      if (secsEl)  secsEl.textContent  = '00';
      return;
    }

    var days  = Math.floor(diff / 86400000);
    var hours = Math.floor((diff % 86400000) / 3600000);
    var mins  = Math.floor((diff % 3600000)  / 60000);
    var secs  = Math.floor((diff % 60000)    / 1000);

    if (daysEl)  daysEl.textContent  = pad(days);
    if (hoursEl) hoursEl.textContent = pad(hours);
    if (minsEl)  minsEl.textContent  = pad(mins);
    if (secsEl)  secsEl.textContent  = pad(secs);
  }

  tick();
  setInterval(tick, 1000);
})();

/* ============================================================
   MODAL SYSTEM
   Uses .active class to show, .hidden to force-hide.
   openModal / closeModal are global so inline onclick="" still works.
   data-close-modal="id" on any button is the preferred pattern.
   ============================================================ */

function openModal(id) {
  var el = document.getElementById(id);
  if (!el) return;
  el.classList.remove('hidden');
  el.classList.add('active');
  el.style.display = 'flex';
  document.body.style.overflow = 'hidden';
  // Focus first interactive field (helps mobile keyboards)
  setTimeout(function() {
    var first = el.querySelector('input:not([type=hidden]),textarea,select');
    if (first) first.focus();
  }, 100);
}

function closeModal(id) {
  var el = document.getElementById(id);
  if (!el) return;
  el.classList.remove('active');
  el.classList.add('hidden');
  el.style.display = 'none';
  // Restore scroll only if no other modal is open
  if (!document.querySelector('.modal-overlay.active')) {
    document.body.style.overflow = '';
  }
}

function closeAllModals() {
  document.querySelectorAll('.modal-overlay').forEach(function(m) {
    m.classList.remove('active');
    m.classList.add('hidden');
    m.style.display = 'none';
  });
  document.body.style.overflow = '';
}

document.addEventListener('DOMContentLoaded', function() {

  /* ── 1. data-close-modal buttons (preferred, no inline onclick needed) ── */
  document.addEventListener('click', function(e) {
    var btn = e.target.closest('[data-close-modal]');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    closeModal(btn.getAttribute('data-close-modal'));
  });

  /* ── 2. .modal-close buttons (fallback for any without data attribute) ── */
  document.addEventListener('click', function(e) {
    var btn = e.target.closest('.modal-close:not([data-close-modal])');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    var overlay = btn.closest('.modal-overlay');
    if (overlay && overlay.id) closeModal(overlay.id);
  });

  /* ── 3. Per-overlay setup ── */
  document.querySelectorAll('.modal-overlay').forEach(function(overlay) {

    // Stop inner modal box clicks from bubbling to overlay backdrop
    var box = overlay.querySelector('.modal');
    if (box) {
      box.addEventListener('click', function(e) { e.stopPropagation(); });
    }

    // Click on dark backdrop (outside .modal) closes it
    overlay.addEventListener('click', function(e) {
      if (e.target === overlay) closeModal(overlay.id);
    });
  });

  /* ── 4. ESC key ── */
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeAllModals();
  });

  /* ── 5. data-confirm dialogs ── */
  document.addEventListener('click', function(e) {
    var el = e.target.closest('[data-confirm]');
    if (!el) return;
    if (!confirm(el.getAttribute('data-confirm'))) {
      e.preventDefault();
      e.stopPropagation();
    }
  });

}); // end DOMContentLoaded

/* ============================================================
   IMAGE PREVIEW
   ============================================================ */
function previewImg(input, previewId) {
  var preview = document.getElementById(previewId);
  if (!preview) return;

  if (input.files && input.files[0]) {
    var reader = new FileReader();
    reader.onload = function(e) {
      preview.style.display = 'block';
      preview.innerHTML     = '<img src="' + e.target.result + '" alt="Preview">';
    };
    reader.readAsDataURL(input.files[0]);
  }
}

/* ============================================================
   AUTO-GROW TEXTAREA
   ============================================================ */
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('textarea.composer-input').forEach(function(ta) {
    ta.addEventListener('input', function() {
      this.style.height = 'auto';
      this.style.height = (this.scrollHeight) + 'px';
    });
  });
});

/* ============================================================
   FLASH MESSAGE AUTO-DISMISS
   ============================================================ */
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.alert').forEach(function(alert) {
    setTimeout(function() {
      alert.style.transition = 'opacity 0.4s ease';
      alert.style.opacity    = '0';
      setTimeout(function() { alert.remove(); }, 450);
    }, 4000);
  });
});

/* ============================================================
   ACTIVE NAV HIGHLIGHT (bottom nav)
   ============================================================ */
(function() {
  var path = window.location.pathname.split('/').pop() || 'index.php';
  document.querySelectorAll('.bnav-item').forEach(function(item) {
    var href = (item.getAttribute('href') || '').split('/').pop().split('?')[0];
    if (href === path) item.classList.add('active');
  });
})();
