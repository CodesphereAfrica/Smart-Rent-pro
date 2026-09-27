(function () {
    const el = document.getElementById('srpPaymentsTable');
    if (!el || !window.jQuery) return;
    const g = id => document.getElementById(id);
    const dt = jQuery(el).DataTable({
      ajax: {
        url: window.SRP.url + '/modules/payments/ajax_list.php',
        dataSrc: 'data',
        data: d => {
          d.method    = g('fltMethod') ? g('fltMethod').value : '';
          d.status    = g('fltStatus') ? g('fltStatus').value : '';
          d.date_from = g('fltFrom')   ? g('fltFrom').value   : '';
          d.date_to   = g('fltTo')     ? g('fltTo').value     : '';
        }
      },
      columns: [
        { data: 'date' }, { data: 'tenant' }, { data: 'unit' }, { data: 'amount' },
        { data: 'unalloc' }, { data: 'method' }, { data: 'reference' }, { data: 'status' },
        { data: 'actions', orderable: false, className: 'text-end' }
      ],
      order: [[0, 'desc']],
      initComplete: () => window.lucide && lucide.createIcons(),
      drawCallback: () => window.lucide && lucide.createIcons()
    });
    ['fltMethod','fltStatus','fltFrom','fltTo'].forEach(id => {
      const e = document.getElementById(id);
      if (e) e.addEventListener('change', () => dt.ajax.reload());
    });
  })();