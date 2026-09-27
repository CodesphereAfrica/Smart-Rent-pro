/* assets/js/modules/roles.js — the roles list DataTable. */
(function () {
    'use strict';
    const table = document.getElementById('srpRolesTable');
    if (!table || !window.jQuery) return;
  
    jQuery(table).DataTable({
      ajax: {
        url: window.SRP.url + '/modules/roles/ajax_list.php',
        dataSrc: 'data'
      },
      columns: [
        { data: 'role' },
        { data: 'scope' },
        { data: 'perm' },
        { data: 'users' },
        { data: 'status' },
        { data: 'actions', orderable: false, className: 'text-end' }
      ],
      order: [[1, 'asc'], [0, 'asc']],
      initComplete: () => window.lucide && lucide.createIcons(),
      drawCallback: () => window.lucide && lucide.createIcons()
    });
  })();