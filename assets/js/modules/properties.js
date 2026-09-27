/* assets/js/modules/properties.js */
(function () {
    'use strict';
    const table = document.getElementById('srpPropertiesTable');
    if (!table || !window.jQuery) return;
  
    jQuery(table).DataTable({
      ajax: {
        url: window.SRP.url + '/modules/properties/ajax_list.php',
        dataSrc: 'data'
      },
      columns: [
        { data: 'name' },
        { data: 'type' },
        { data: 'location' },
        { data: 'owner' },
        { data: 'manager' },
        { data: 'units' },
        { data: 'status' },
        { data: 'actions', orderable: false }
      ],
      order: [[0, 'asc']],
      initComplete: function () { if (window.lucide) lucide.createIcons(); },
      drawCallback: function () { if (window.lucide) lucide.createIcons(); }
    });
  })();