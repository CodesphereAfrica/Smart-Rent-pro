(function () {
    const el = document.getElementById('srpLeasesTable');
    if (!el || !window.jQuery) return;
    const s = document.getElementById('fltStatus');
    const p = document.getElementById('fltProperty');
    const e = document.getElementById('fltExpiring');
  
    const dt = jQuery(el).DataTable({
      ajax: {
        url: window.SRP.url + '/modules/leases/ajax_list.php',
        dataSrc: 'data',
        data: d => {
          d.status      = s ? s.value : '';
          d.property_id = p ? p.value : '';
          d.expiring    = e ? e.value : '';
        }
      },
      columns: [
        { data: 'lease' }, { data: 'tenant' }, { data: 'unit' }, { data: 'period' },
        { data: 'rent' }, { data: 'days' }, { data: 'status' },
        { data: 'actions', orderable: false, className: 'text-end' }
      ],
      order: [[3, 'desc']],
      initComplete: () => window.lucide && lucide.createIcons(),
      drawCallback: () => window.lucide && lucide.createIcons()
    });
  
    if (p && p.options.length <= 1) {
      srpFetch(window.SRP.url + '/modules/leases/ajax_list.php?meta=properties')
        .then(r => r.json())
        .then(d => {
          (d.properties || []).forEach(x => {
            const o = document.createElement('option');
            o.value = x.id; o.textContent = x.name;
            p.appendChild(o);
          });
        }).catch(() => {});
    }
    if (s) s.addEventListener('change', () => dt.ajax.reload());
    if (p) p.addEventListener('change', () => dt.ajax.reload());
    if (e) e.addEventListener('change', () => dt.ajax.reload());
  })();