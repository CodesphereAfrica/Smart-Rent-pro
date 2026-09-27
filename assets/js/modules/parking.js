/* assets/js/modules/parking.js — server-side DataTable + filters. */
(function () {
    'use strict';
    const table = document.getElementById('srpParkingTable');
    if (!table || !window.jQuery) return;
  
    const selProperty = document.getElementById('fltProperty');
    const selStatus   = document.getElementById('fltStatus');
  
    const dt = jQuery(table).DataTable({
      ajax: {
        url: window.SRP.url + '/modules/parking/ajax_list.php',
        dataSrc: 'data',
        data: d => {
          d.property_id = selProperty ? selProperty.value : '';
          d.status      = selStatus   ? selStatus.value   : '';
        }
      },
      columns: [
        { data: 'space' },
        { data: 'property' },
        { data: 'type' },
        { data: 'fee' },
        { data: 'assignments' },
        { data: 'status' },
        { data: 'actions', orderable: false, className: 'text-end' }
      ],
      order: [[1, 'asc'], [0, 'asc']],
      initComplete: () => window.lucide && lucide.createIcons(),
      drawCallback: () => window.lucide && lucide.createIcons()
    });
  
    if (selProperty && selProperty.options.length <= 1) {
      srpFetch(window.SRP.url + '/modules/parking/ajax_list.php?meta=properties')
        .then(r => r.json())
        .then(d => {
          (d.properties || []).forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = p.name;
            selProperty.appendChild(opt);
          });
        })
        .catch(() => {});
    }
  
    if (selProperty) selProperty.addEventListener('change', () => dt.ajax.reload());
    if (selStatus)   selStatus.addEventListener('change',   () => dt.ajax.reload());
  })();