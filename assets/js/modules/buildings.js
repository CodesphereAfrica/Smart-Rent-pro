(function () {
    const el = document.getElementById('srpBuildingsTable');
    if (!el || !window.jQuery) return;
    jQuery(el).DataTable({
      ajax: { url: window.SRP.url + '/modules/buildings/ajax_list.php', dataSrc: 'data' },
      columns: [{data:'name'},{data:'property'},{data:'floors'},{data:'units'},{data:'status'},{data:'actions',orderable:false}],
      order: [[0,'asc']],
      drawCallback: () => window.lucide && lucide.createIcons()
    });
  })();