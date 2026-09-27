(function () {
    const el = document.getElementById('srpInvoicesTable');
    if (!el || !window.jQuery) return;
    const s = document.getElementById('fltStatus');
    const t = document.getElementById('fltType');
    const p = document.getElementById('fltPeriod');
    const dt = jQuery(el).DataTable({
      ajax: {
        url: window.SRP.url + '/modules/invoices/ajax_list.php',
        dataSrc: 'data',
        data: d => {
          d.status = s ? s.value : '';
          d.type   = t ? t.value : '';
          d.period = p ? p.value : '';
        }
      },
      columns: [
        { data: 'invoice' }, { data: 'tenant' }, { data: 'unit' }, { data: 'type' },
        { data: 'amount' }, { data: 'balance' }, { data: 'due' }, { data: 'status' },
        { data: 'actions', orderable: false, className: 'text-end' }
      ],
      order: [[6, 'desc']],
      initComplete: () => window.lucide && lucide.createIcons(),
      drawCallback: () => window.lucide && lucide.createIcons()
    });
    if (s) s.addEventListener('change', () => dt.ajax.reload());
    if (t) t.addEventListener('change', () => dt.ajax.reload());
    if (p) p.addEventListener('change', () => dt.ajax.reload());
  })();