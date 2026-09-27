/* assets/js/modules/tenants.js — server-side DataTable + filters. */
(function () {
    'use strict';
    const table = document.getElementById('srpTenantsTable');
    if (!table || !window.jQuery) return;
  
    const selStatus  = document.getElementById('fltStatus');
    const selType    = document.getElementById('fltType');
    const selArrears = document.getElementById('fltArrears');
  
    const dt = jQuery(table).DataTable({
      ajax: {
        url: window.SRP.url + '/modules/tenants/ajax_list.php',
        dataSrc: 'data',
        data: d => {
          d.status      = selStatus  ? selStatus.value  : '';
          d.tenant_type = selType    ? selType.value    : '';
          d.arrears     = selArrears ? selArrears.value : '';
        }
      },
      columns: [
        { data: 'tenant' },
        { data: 'contact' },
        { data: 'unit' },
        { data: 'balance' },
        { data: 'status' },
        { data: 'actions', orderable: false, className: 'text-end' }
      ],
      order: [[0, 'asc']],
      initComplete: () => window.lucide && lucide.createIcons(),
      drawCallback: () => window.lucide && lucide.createIcons()
    });
  
    if (selStatus)  selStatus.addEventListener('change',  () => dt.ajax.reload());
    if (selType)    selType.addEventListener('change',    () => dt.ajax.reload());
    if (selArrears) selArrears.addEventListener('change', () => dt.ajax.reload());
  })();