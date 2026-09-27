(function () {
    const el = document.getElementById('srpCompaniesTable');
    if (!el || !window.jQuery) return;
    jQuery(el).DataTable({
      ajax: { url: window.SRP.url + '/modules/companies/ajax_list.php', dataSrc: 'data' },
      columns: [
        { data: 'name' }, { data: 'slug' }, { data: 'currency' }, { data: 'language' },
        { data: 'props' }, { data: 'units' }, { data: 'users' }, { data: 'status' },
        { data: 'actions', orderable: false, className: 'text-end' }
      ],
      order: [[0, 'asc']],
      initComplete: () => window.lucide && lucide.createIcons(),
      drawCallback: () => window.lucide && lucide.createIcons()
    });
  })();