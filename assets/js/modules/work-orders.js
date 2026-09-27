(function () {
    const el = document.getElementById('srpWorkOrdersTable');
    if (!el || !window.jQuery) return;
    const s = document.getElementById('fltStatus');
    const v = document.getElementById('fltVendor');
    const dt = jQuery(el).DataTable({
      ajax: {
        url: window.SRP.url + '/modules/work-orders/ajax_list.php',
        dataSrc: 'data',
        data: d => {
          d.status    = s ? s.value : '';
          d.vendor_id = v ? v.value : '';
        }
      },
      columns: [
        { data: 'wo' }, { data: 'ticket' }, { data: 'vendor' }, { data: 'scheduled' },
        { data: 'cost' }, { data: 'status' },
        { data: 'actions', orderable: false, className: 'text-end' }
      ],
      order: [[3, 'desc']],
      initComplete: () => window.lucide && lucide.createIcons(),
      drawCallback: () => window.lucide && lucide.createIcons()
    });
    if (v && v.options.length <= 1) {
      srpFetch(window.SRP.url + '/modules/work-orders/ajax_list.php?meta=vendors')
        .then(r => r.json()).then(d => {
          (d.vendors || []).forEach(x => {
            const o = document.createElement('option');
            o.value = x.id; o.textContent = x.name;
            v.appendChild(o);
          });
        }).catch(() => {});
    }
    if (s) s.addEventListener('change', () => dt.ajax.reload());
    if (v) v.addEventListener('change', () => dt.ajax.reload());
  })();