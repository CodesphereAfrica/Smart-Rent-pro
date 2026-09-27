/* assets/js/app.js — global behaviours. No module-specific logic here. */
(function () {
    'use strict';
  
    // ---------- Toast helper (used everywhere instead of alert()) ----------
    window.srpToast = function (type, message, delay) {
      const container = document.getElementById('srpToasts');
      if (!container) return;
      const id = 'toast_' + Math.random().toString(36).slice(2);
      const bg = {
        success: 'text-bg-success',
        error: 'text-bg-danger',
        info: 'text-bg-primary',
        warning: 'text-bg-warning'
      }[type] || 'text-bg-secondary';
      const icon = {
        success: 'check-circle-2',
        error: 'alert-circle',
        info: 'info',
        warning: 'alert-triangle'
      }[type] || 'bell';
      const html =
        '<div id="' + id + '" class="toast align-items-center ' + bg + ' border-0" role="alert">' +
          '<div class="d-flex">' +
            '<div class="toast-body d-flex align-items-center gap-2">' +
              '<i data-lucide="' + icon + '"></i><span>' + message + '</span>' +
            '</div>' +
            '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>' +
          '</div>' +
        '</div>';
      container.insertAdjacentHTML('beforeend', html);
      if (window.lucide) lucide.createIcons();
      const el = document.getElementById(id);
      const t = new bootstrap.Toast(el, { delay: delay || 4000 });
      t.show();
      el.addEventListener('hidden.bs.toast', () => el.remove());
    };
  
    // ---------- Drain server-side flash messages into toasts ----------
    document.querySelectorAll('[data-flash]').forEach(el => {
      srpToast(el.dataset.flash, el.textContent.trim());
      el.remove();
    });
  
    // ---------- Theme toggle (persist per-browser via cookie; user pref wins) ----------
    const root = document.documentElement;
    function applyTheme(t) {
      root.setAttribute('data-theme', t);
      document.cookie = 'srp_theme=' + t + '; path=/; max-age=31536000; samesite=lax';
    }
    const themeBtn = document.getElementById('srpThemeToggle');
    if (themeBtn) {
      themeBtn.addEventListener('click', () => {
        const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        applyTheme(next);
      });
    }
  
    // ---------- Sidebar toggle (mobile) ----------
    const sidebarToggle = document.getElementById('srpToggleSidebar');
    if (sidebarToggle) {
      sidebarToggle.addEventListener('click', () => {
        document.body.classList.toggle('srp-sidebar-collapsed');
      });
    }
  
    // ---------- AJAX helper with CSRF ----------
    window.srpFetch = function (url, options) {
      options = options || {};
      options.headers = Object.assign({
        'X-CSRF-Token': window.SRP.csrf,
        'X-Requested-With': 'XMLHttpRequest'
      }, options.headers || {});
      if (options.body && !(options.body instanceof FormData) && typeof options.body === 'object') {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(options.body);
      }
      return fetch(url, options).then(r => {
        if (r.status === 419) { srpToast('error', 'Session expired. Please reload.'); throw new Error('csrf'); }
        if (r.status === 403) { srpToast('error', 'You do not have permission.'); throw new Error('forbidden'); }
        return r;
      });
    };
  
    // ---------- Notification bell (live badge + dropdown) ----------
    function refreshNotifications() {
      srpFetch(window.SRP.url + '/modules/notifications/ajax_list.php?scope=bell&limit=8', { method: 'GET' })
        .then(r => r.json())
        .then(data => {
          const badge = document.getElementById('srpNotifBadge');
          const list  = document.getElementById('srpNotifList');
          if (!badge || !list) return;
          const unread = data.unread || 0;
          if (unread > 0) { badge.textContent = unread > 99 ? '99+' : unread; badge.classList.remove('d-none'); }
          else { badge.classList.add('d-none'); }
          if (!data.items || data.items.length === 0) {
            list.innerHTML = '<div class="p-3 text-muted small">No notifications</div>';
            return;
          }
          list.innerHTML = data.items.map(n => (
            '<a class="srp-notif-item ' + (n.is_read ? '' : 'unread') + '" href="' + n.link + '">' +
              '<div class="fw-semibold small">' + n.title + '</div>' +
              '<div class="text-muted small">' + n.message + '</div>' +
              '<div class="text-muted" style="font-size:.72rem">' + n.ago + '</div>' +
            '</a>'
          )).join('');
          if (window.lucide) lucide.createIcons();
        }).catch(() => {});
    }
    if (document.getElementById('srpNotifBell')) {
      refreshNotifications();
      setInterval(refreshNotifications, 45000);
    }
  
    // ---------- Global search ----------
    const gsInput = document.getElementById('srpGlobalSearch');
    const gsBox   = document.getElementById('srpGlobalSearchResults');
    if (gsInput && gsBox) {
      let timer = null;
      gsInput.addEventListener('input', () => {
        clearTimeout(timer);
        const q = gsInput.value.trim();
        if (q.length < 2) { gsBox.classList.add('d-none'); return; }
        timer = setTimeout(() => {
          srpFetch(window.SRP.url + '/modules/search/ajax.php?q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
              if (!data.groups || !data.groups.length) {
                gsBox.innerHTML = '<div class="p-3 text-muted small">No results</div>';
                gsBox.classList.remove('d-none'); return;
              }
              gsBox.innerHTML = data.groups.map(g => (
                '<div class="srp-gs-group">' +
                  '<div class="srp-gs-group-title">' + g.label + '</div>' +
                  g.items.map(it => (
                    '<a class="srp-gs-item" href="' + it.link + '">' +
                      '<i data-lucide="' + (it.icon || 'file') + '"></i>' +
                      '<span>' + it.title + '</span>' +
                      (it.subtitle ? '<span class="text-muted ms-2 small">' + it.subtitle + '</span>' : '') +
                    '</a>'
                  )).join('') +
                '</div>'
              )).join('');
              gsBox.classList.remove('d-none');
              if (window.lucide) lucide.createIcons();
            });
        }, 220);
      });
      document.addEventListener('click', e => {
        if (!gsBox.contains(e.target) && e.target !== gsInput) gsBox.classList.add('d-none');
      });
    }
  
    // ---------- DataTables default overrides ----------
    if (window.jQuery && jQuery.fn.dataTable) {
      jQuery.fn.dataTable.ext.errMode = 'throw';
      jQuery.extend(jQuery.fn.dataTable.defaults, {
        processing: true,
        serverSide: true,
        responsive: true,
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
        language: {
          search: '',
          searchPlaceholder: 'Search…',
          lengthMenu: '_MENU_ per page',
          info: 'Showing _START_–_END_ of _TOTAL_',
          infoEmpty: 'No records',
          processing: '<div class="srp-skeleton-row"></div>',
          emptyTable: 'No records found',
          zeroRecords: 'No matching records'
        },
        dom: "<'row mb-2'<'col-sm-6'l><'col-sm-6 text-end'f>>" +
             "<'row'<'col-12'tr>>" +
             "<'row mt-3'<'col-sm-6'i><'col-sm-6'p>>"
      });
    }
  
    // ---------- Expose CSRF to DataTable ajax calls ----------
    if (window.jQuery) {
      jQuery.ajaxSetup({
        headers: { 'X-CSRF-Token': window.SRP.csrf, 'X-Requested-With': 'XMLHttpRequest' }
      });
    }
  })();