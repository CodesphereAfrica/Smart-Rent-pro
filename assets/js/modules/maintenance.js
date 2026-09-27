(function () {
    const el = document.getElementById('srpMaintenanceTable');
    if (!el || !window.jQuery) return;
    const g = id => document.getElementById(id);
  
    const dt = jQuery(el).DataTable({
      ajax: {
        url: window.SRP.url + '/modules/maintenance/ajax_list.php',
        dataSrc: 'data',
        data: d => {
          d.status      = g('fltStatus') ? g('fltStatus').value : '';
          d.priority    = g('fltPriority') ? g('fltPriority').value : '';
          d.property_id = g('fltProperty') ? g('fltProperty').value : '';
          d.open_only   = g('fltOpenOnly') ? g('fltOpenOnly').value : '';
        }
      },
      columns: [
        { data: 'ticket' }, { data: 'unit' }, { data: 'category' },
        { data: 'priority' }, { data: 'status' }, { data: 'assigned' },
        { data: 'reported' }, { data: 'actions', orderable: false, className: 'text-end' }
      ],
      order: [[6, 'desc']],
      initComplete: () => window.lucide && lucide.createIcons(),
      drawCallback: () => window.lucide && lucide.createIcons()
    });
  
    const propSel = g('fltProperty');
    if (propSel && propSel.options.length <= 1) {
      srpFetch(window.SRP.url + '/modules/maintenance/ajax_list.php?meta=properties')
        .then(r => r.json()).then(d => {
          (d.properties || []).forEach(p => {
            const o = document.createElement('option');
            o.value = p.id; o.textContent = p.name;
            propSel.appendChild(o);
          });
        }).catch(() => {});
    }
  
    ['fltStatus','fltPriority','fltProperty','fltOpenOnly'].forEach(id => {
      const e = document.getElementById(id);
      if (e) e.addEventListener('change', () => dt.ajax.reload());
    });
  })();