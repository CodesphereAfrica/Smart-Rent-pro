(function () {
    const el = document.getElementById('srpOwnersTable');
    if (!el || !window.jQuery) return;
    const s = document.getElementById('fltStatus');
    const t = document.getElementById('fltType');
    const dt = jQuery(el).DataTable({
      ajax: {
        url: window.SRP.url + '/modules/owners/ajax_list.php',
        dataSrc: 'data',
        data: d => {
          d.status = s ? s.value : '';
          d.owner_type = t ? t.value : '';
        }
      },
      columns: [
        { data: 'owner' }, { data: 'contact' }, { data: 'properties' },
        { data: 'ownership' }, { data: 'portal' }, { data: 'status' },
        { data: 'actions', orderable: false, className: 'text-end' }
      ],
      order: [[0, 'asc']],
      initComplete: () => window.lucide && lucide.createIcons(),
      drawCallback: () => window.lucide && lucide.createIcons()
    });
    if (s) s.addEventListener('change', () => dt.ajax.reload());
    if (t) t.addEventListener('change', () => dt.ajax.reload());
  })();