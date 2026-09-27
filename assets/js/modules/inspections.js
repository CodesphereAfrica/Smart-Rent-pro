(function () {
    const el = document.getElementById('srpInspectionsTable');
    if (!el || !window.jQuery) return;
    const t = document.getElementById('fltType');
    const s = document.getElementById('fltStatus');
    const dt = jQuery(el).DataTable({
      ajax: {
        url: window.SRP.url + '/modules/inspections/ajax_list.php',
        dataSrc: 'data',
        data: d => { d.type = t ? t.value : ''; d.status = s ? s.value : ''; }
      },
      columns: [
        { data: 'date' }, { data: 'type' }, { data: 'unit' },
        { data: 'inspector' }, { data: 'status' },
        { data: 'actions', orderable: false, className: 'text-end' }
      ],
      order: [[0, 'desc']],
      initComplete: () => window.lucide && lucide.createIcons(),
      drawCallback: () => window.lucide && lucide.createIcons()
    });
    if (t) t.addEventListener('change', () => dt.ajax.reload());
    if (s) s.addEventListener('change', () => dt.ajax.reload());
  })();