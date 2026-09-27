(function () {
    const el = document.getElementById('srpContractsTable');
    if (!el || !window.jQuery) return;
    const s = document.getElementById('fltStatus');
    const x = document.getElementById('fltExpiry');
    const dt = jQuery(el).DataTable({
      ajax: { url: window.SRP.url + '/modules/contracts/ajax_list.php', dataSrc: 'data',
        data: d => { d.status = s ? s.value : ''; d.expiry = x ? x.value : ''; } },
      columns: [{data:'title'},{data:'type'},{data:'vendor'},{data:'period'},{data:'amount'},{data:'status'},
                {data:'actions',orderable:false,className:'text-end'}],
      order: [[3, 'desc']],
      initComplete: () => window.lucide && lucide.createIcons(),
      drawCallback: () => window.lucide && lucide.createIcons()
    });
    if (s) s.addEventListener('change', () => dt.ajax.reload());
    if (x) x.addEventListener('change', () => dt.ajax.reload());
  })();