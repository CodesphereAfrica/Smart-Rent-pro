(function () {
    'use strict';
    const list = document.getElementById('srpNotifFullList');
    if (!list) return;
  
    function render(items) {
      if (!items.length) {
        list.innerHTML = '<div class="text-muted small text-center py-4">No notifications yet.</div>';
        return;
      }
      list.innerHTML = items.map(n => (
        '<a href="' + n.link + '" class="d-block border-bottom py-3 text-decoration-none ' + (n.is_read ? '' : 'bg-light') + '" data-id="' + n.id + '">' +
          '<div class="fw-semibold">' + n.title + '</div>' +
          '<div class="text-muted small">' + n.message + '</div>' +
          '<div class="text-muted" style="font-size:.72rem">' + n.ago + '</div>' +
        '</a>'
      )).join('');
  
      list.querySelectorAll('[data-id]').forEach(el => {
        el.addEventListener('click', () => {
          srpFetch(window.SRP.url + '/modules/notifications/mark-read.php', {
            method: 'POST',
            body: { id: el.dataset.id }
          }).catch(() => {});
        });
      });
    }
  
    srpFetch(window.SRP.url + '/modules/notifications/ajax_list.php?scope=full&limit=50')
      .then(r => r.json()).then(d => render(d.items || []))
      .catch(() => { list.innerHTML = '<div class="alert alert-danger m-3">Failed to load.</div>'; });
  
    const allBtn = document.getElementById('markAllRead');
    if (allBtn) {
      allBtn.addEventListener('click', () => {
        srpFetch(window.SRP.url + '/modules/notifications/mark-read.php', { method: 'POST', body: { all: 1 } })
          .then(() => { srpToast('success', 'All marked as read'); location.reload(); });
      });
    }
  })();