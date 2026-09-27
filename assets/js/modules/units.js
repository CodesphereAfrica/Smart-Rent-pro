(function () {
    const el = document.getElementById('srpUnitsTable');
    if (!el || !window.jQuery) return;
  
    const table = jQuery(el).DataTable({
      ajax: {
        url: window.SRP.url + '/modules/units/ajax_list.php',
        dataSrc: 'data',
        data: d => {
          d.property_id = document.getElementById('fltProperty')?.value || '';
          d.status = document.getElementById('fltStatus')?.value || '';
        }
      },
      columns: [
        { data: 'unit' }, { data: 'property' }, { data: 'type' },
        { data: 'bedbath' }, { data: 'rent' }, { data: 'tenant' },
        { data: 'status' }, { data: 'actions', orderable: false }
      ],
      order: [[0, 'asc']],
      drawCallback: () => window.lucide && lucide.createIcons()
    });
  
    // Populate properties dropdown from a lightweight side call
    srpFetch(window.SRP.url + '/modules/units/ajax_list.php?meta=properties&length=0')
      .then(() => null).catch(() => null);
  
    // Simplest: server-render property options via inline call. Use a tiny helper URL on same file with `meta=1`.
    srpFetch(window.SRP.url + '/modules/units/ajax_list.php?meta=properties')
      .then(r => r.json()).then(d => {
        const sel = document.getElementById('fltProperty');
        if (!sel || !d.properties) return;
        sel.innerHTML = '<option value="">All Properties</option>' +
          d.properties.map(p => `<option value="${p.id}">${p.name}</option>`).join('');
      }).catch(() => {});
  
    document.getElementById('fltProperty')?.addEventListener('change', () => table.ajax.reload());
    document.getElementById('fltStatus')?.addEventListener('change', () => table.ajax.reload());
  })();