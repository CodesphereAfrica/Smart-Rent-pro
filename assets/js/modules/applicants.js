(function () {
    const el = document.getElementById('srpApplicantsTable');
    if (!el || !window.jQuery) return;
    const s = document.getElementById('fltStage');
    const dt = jQuery(el).DataTable({
      ajax: {
        url: window.SRP.url + '/modules/applicants/ajax_list.php',
        dataSrc: 'data',
        data: d => { d.stage = s ? s.value : ''; }
      },
      columns: [
        { data: 'applicant' }, { data: 'contact' }, { data: 'unit' },
        { data: 'stage' }, { data: 'created' },
        { data: 'actions', orderable: false, className: 'text-end' }
      ],
      order: [[4, 'desc']],
      initComplete: () => window.lucide && lucide.createIcons(),
      drawCallback: () => window.lucide && lucide.createIcons()
    });
    if (s) s.addEventListener('change', () => dt.ajax.reload());
  })();