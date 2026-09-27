(function () {
    const el = document.getElementById('srpCommsTable');
    if (!el || !window.jQuery) return;
    const g = id => document.getElementById(id);
    const dt = jQuery(el).DataTable({
      ajax: {
        url: window.SRP.url + '/modules/communications/ajax_list.php',
        dataSrc: 'data',
        data: d => {
          d.channel   = g('fltChannel')   ? g('fltChannel').value   : '';
          d.status    = g('fltStatus')    ? g('fltStatus').value    : '';
          d.direction = g('fltDirection') ? g('fltDirection').value : '';
        }
      },
      columns: [
        { data: 'date' }, { data: 'channel' }, { data: 'recipient' }, { data: 'subject' },
        { data: 'related' }, { data: 'status' },
        { data: 'actions', orderable: false, className: 'text-end' }
      ],
      order: [[0, 'desc']],
      initComplete: () => window.lucide && lucide.createIcons(),
      drawCallback: () => window.lucide && lucide.createIcons()
    });
    ['fltChannel','fltStatus','fltDirection'].forEach(id => {
      const e = document.getElementById(id);
      if (e) e.addEventListener('change', () => dt.ajax.reload());
    });
  })();