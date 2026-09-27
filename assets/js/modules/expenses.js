(function () {
    const el = document.getElementById('srpExpensesTable');
    if (!el || !window.jQuery) return;
    const g = id => document.getElementById(id);
    const dt = jQuery(el).DataTable({
      ajax: {
        url: window.SRP.url + '/modules/expenses/ajax_list.php',
        dataSrc: 'data',
        data: d => {
          d.status      = g('fltStatus') ? g('fltStatus').value : '';
          d.category_id = g('fltCategory') ? g('fltCategory').value : '';
          d.property_id = g('fltProperty') ? g('fltProperty').value : '';
        }
      },
      columns: [
        { data: 'date' }, { data: 'desc' }, { data: 'category' }, { data: 'property' },
        { data: 'vendor' }, { data: 'amount' }, { data: 'status' },
        { data: 'actions', orderable: false, className: 'text-end' }
      ],
      order: [[0, 'desc']],
      initComplete: () => window.lucide && lucide.createIcons(),
      drawCallback: () => window.lucide && lucide.createIcons()
    });
  
    const cat = g('fltCategory');
    const prop = g('fltProperty');
    if ((cat && cat.options.length <= 1) || (prop && prop.options.length <= 1)) {
      srpFetch(window.SRP.url + '/modules/expenses/ajax_list.php?meta=filters')
        .then(r => r.json()).then(d => {
          (d.categories || []).forEach(x => {
            const o = document.createElement('option'); o.value = x.id; o.textContent = x.name;
            if (cat) cat.appendChild(o);
          });
          (d.properties || []).forEach(x => {
            const o = document.createElement('option'); o.value = x.id; o.textContent = x.name;
            if (prop) prop.appendChild(o);
          });
        }).catch(() => {});
    }
    ['fltStatus','fltCategory','fltProperty'].forEach(id => {
      const e = document.getElementById(id);
      if (e) e.addEventListener('change', () => dt.ajax.reload());
    });
  })();