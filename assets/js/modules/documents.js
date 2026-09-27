(function () {
    const el = document.getElementById('srpDocsTable');
    if (!el || !window.jQuery) return;
    const g = id => document.getElementById(id);
    const dt = jQuery(el).DataTable({
      ajax: {
        url: window.SRP.url + '/modules/documents/ajax_list.php',
        dataSrc: 'data',
        data: d => {
          d.category_id  = g('fltCategory') ? g('fltCategory').value : '';
          d.related_type = g('fltRelated')  ? g('fltRelated').value  : '';
          d.expiry       = g('fltExpiry')   ? g('fltExpiry').value   : '';
        }
      },
      columns: [
        { data: 'name' }, { data: 'category' }, { data: 'related' }, { data: 'size' },
        { data: 'version' }, { data: 'expiry' }, { data: 'uploaded' },
        { data: 'actions', orderable: false, className: 'text-end' }
      ],
      order: [[6, 'desc']],
      initComplete: () => window.lucide && lucide.createIcons(),
      drawCallback: () => window.lucide && lucide.createIcons()
    });
    const cat = g('fltCategory');
    if (cat && cat.options.length <= 1) {
      srpFetch(window.SRP.url + '/modules/documents/ajax_list.php?meta=categories')
        .then(r => r.json()).then(d => {
          (d.categories || []).forEach(x => {
            const o = document.createElement('option'); o.value = x.id; o.textContent = x.name;
            cat.appendChild(o);
          });
        }).catch(() => {});
    }
    ['fltCategory','fltRelated','fltExpiry'].forEach(id => {
      const e = document.getElementById(id);
      if (e) e.addEventListener('change', () => dt.ajax.reload());
    });
  })();