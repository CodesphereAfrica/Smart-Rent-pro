(function () {
    const el = document.getElementById('srpAuditTable');
    if (!el || !window.jQuery) return;
    const g = id => document.getElementById(id);
    const dt = jQuery(el).DataTable({
      ajax: {
        url: window.SRP.url + '/modules/audit-logs/ajax_list.php',
        dataSrc: 'data',
        data: d => {
          d.module = g('fltModule') ? g('fltModule').value : '';
          d.action = g('fltAction') ? g('fltAction').value : '';
          d.from   = g('fltFrom')   ? g('fltFrom').value   : '';
          d.to     = g('fltTo')     ? g('fltTo').value     : '';
        }
      },
      columns: [
        { data: 'when' }, { data: 'user' }, { data: 'module' }, { data: 'action' },
        { data: 'record' }, { data: 'ip' },
        { data: 'actions', orderable: false, className: 'text-end' }
      ],
      order: [[0, 'desc']],
      initComplete: () => window.lucide && lucide.createIcons(),
      drawCallback: () => window.lucide && lucide.createIcons()
    });
    const mod = g('fltModule');
    if (mod && mod.options.length <= 1) {
      srpFetch(window.SRP.url + '/modules/audit-logs/ajax_list.php?meta=modules')
        .then(r => r.json()).then(d => {
          (d.modules || []).forEach(m => {
            const o = document.createElement('option'); o.value = m; o.textContent = m;
            mod.appendChild(o);
          });
        }).catch(() => {});
    }
    ['fltModule','fltAction','fltFrom','fltTo'].forEach(id => {
      const e = document.getElementById(id);
      if (e) e.addEventListener('change', () => dt.ajax.reload());
    });
  
    // Modal detail handler
    const modal = document.getElementById('auditModal');
    if (modal) {
      modal.addEventListener('show.bs.modal', ev => {
        const b = ev.relatedTarget;
        const pretty = s => { try { return JSON.stringify(JSON.parse(s || 'null'), null, 2); } catch (e) { return s || '—'; } };
        modal.querySelector('#auditOld').textContent = pretty(b.getAttribute('data-old'));
        modal.querySelector('#auditNew').textContent = pretty(b.getAttribute('data-new'));
      });
    }
  })();