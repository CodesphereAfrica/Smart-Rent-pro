(function () {
    const el = document.getElementById('srpVendorsTable');
    if (!el || !window.jQuery) return;
    const s = document.getElementById('fltStatus');
    const c = document.getElementById('fltCategory');
    let tid = null;
    const dt = jQuery(el).DataTable({
      ajax: {
        url: window.SRP.url + '/modules/vendors/ajax_list.php',
        dataSrc: 'data',
        data: d => {
          d.status = s ? s.value : '';
          d.category = c ? c.value : '';
        }
      },
      columns: [
        { data: 'vendor' }, { data: 'category' }, { data: 'contact' }, { data: 'rating' },
        { data: 'jobs' }, { data: 'avg_resp' }, { data: 'paid' }, { data: 'status' },
        { data: 'actions', orderable: false, className: 'text-end' }
      ],
      order: [[0, 'asc']],
      initComplete: () => window.lucide && lucide.createIcons(),
      drawCallback: () => window.lucide && lucide.createIcons()
    });
    if (s) s.addEventListener('change', () => dt.ajax.reload());
    if (c) c.addEventListener('input', () => { clearTimeout(tid); tid = setTimeout(() => dt.ajax.reload(), 250); });
  })();