/* assets/js/modules/floors.js — server-side DataTable + building picker. */
(function () {
  'use strict';
  const table = document.getElementById('srpFloorsTable');
  if (!table || !window.jQuery) return;

  const selBuilding = document.getElementById('fltBuilding');

  const dt = jQuery(table).DataTable({
    ajax: {
      url: window.SRP.url + '/modules/floors/ajax_list.php',
      dataSrc: 'data',
      data: d => {
        d.building_id = selBuilding ? selBuilding.value : '';
        d.property_id = (document.getElementById('fltProperty') || {}).value || '';
      }
    },
    columns: [
      { data: 'building' },
      { data: 'property' },
      { data: 'floor' },
      { data: 'units' },
      { data: 'actions', orderable: false, className: 'text-end' }
    ],
    order: [[0, 'asc'], [2, 'asc']],
    initComplete: () => window.lucide && lucide.createIcons(),
    drawCallback: () => window.lucide && lucide.createIcons()
  });

  if (selBuilding) {
    selBuilding.addEventListener('change', () => dt.ajax.reload());
    // Populate dropdown if it's currently empty
    if (selBuilding.options.length <= 1) {
      srpFetch(window.SRP.url + '/modules/floors/ajax_list.php?meta=buildings')
        .then(r => r.json())
        .then(d => {
          (d.buildings || []).forEach(b => {
            const opt = document.createElement('option');
            opt.value = b.id;
            opt.textContent = b.property_name + ' · ' + b.name;
            selBuilding.appendChild(opt);
          });
          // Honour ?building_id= preset if present in URL
          const preset = new URLSearchParams(window.location.search).get('building_id');
          if (preset) { selBuilding.value = preset; dt.ajax.reload(); }
        })
        .catch(() => {});
    }
  }
})();