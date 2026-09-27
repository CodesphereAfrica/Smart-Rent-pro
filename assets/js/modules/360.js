/* assets/js/modules/360.js — AJAX loader for the 360 tab panels.
   Used by tenants/view.php, properties/view.php, units/view.php. */
   (function () {
    'use strict';
    const wrap = document.getElementById('srp360TabsContent');
    if (!wrap) return;
  
    const type = wrap.dataset['360Type'];   // tenant | property | unit
    const id   = wrap.dataset['360Id'];
    const url  = window.SRP.url + '/modules/360/ajax_tabs.php';
    const loaded = {};   // tab -> true (cache so we only fetch once)
  
    function skeletonHTML() {
      return '<div class="srp-skeleton-row" style="height:48px"></div>' +
             '<div class="srp-skeleton-row" style="height:48px;width:80%"></div>' +
             '<div class="srp-skeleton-row" style="height:48px;width:60%"></div>';
    }
  
    function tableFromRows(columns, rows) {
      if (!rows || rows.length === 0) {
        return '<div class="text-muted small text-center py-4">' + (window.__srp360NoData || 'No records') + '</div>';
      }
      let html = '<div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr>';
      columns.forEach(c => html += '<th>' + c.label + '</th>');
      html += '</tr></thead><tbody>';
      rows.forEach(r => {
        html += '<tr>';
        columns.forEach(c => html += '<td>' + (c.render ? c.render(r) : (r[c.key] ?? '')) + '</td>');
        html += '</tr>';
      });
      html += '</tbody></table></div>';
      return html;
    }
  
    function money(n) {
      return new Intl.NumberFormat('en-RW', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n) + ' RWF';
    }
  
    function esc(s) {
      return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
  
    const renderers = {
      tenant: {
        leases: rows => tableFromRows([
          { label: 'Lease', key: 'lease_number' },
          { label: 'Unit', key: 'unit_number' },
          { label: 'Property', key: 'property_name' },
          { label: 'Period', render: r => esc(r.start_date) + ' → ' + esc(r.end_date) },
          { label: 'Rent', render: r => money(r.monthly_rent) },
          { label: 'Status', render: r => '<span class="srp-badge-pill badge-status-available"><span class="dot"></span>' + esc(r.status) + '</span>' }
        ], rows),
        invoices: rows => tableFromRows([
          { label: 'Invoice', render: r => '<a href="' + window.SRP.url + '/modules/invoices/view.php?id=' + r.id + '">' + esc(r.invoice_number) + '</a>' },
          { label: 'Type', render: r => esc((r.invoice_type||'').replace(/_/g,' ')) },
          { label: 'Amount', render: r => money(r.amount) },
          { label: 'Paid', render: r => money(r.amount_paid) },
          { label: 'Balance', render: r => '<span class="' + (r.balance > 0 ? 'text-danger fw-semibold' : 'text-muted') + '">' + money(r.balance) + '</span>' },
          { label: 'Due', key: 'due_date' },
          { label: 'Status', render: r => esc((r.status||'').replace(/_/g,' ')) }
        ], rows),
        payments: rows => tableFromRows([
          { label: 'Date', key: 'payment_date' },
          { label: 'Amount', render: r => money(r.amount) },
          { label: 'Method', render: r => esc((r.payment_method||'').replace(/_/g,' ')) },
          { label: 'Reference', key: 'payment_reference' },
          { label: 'Status', key: 'status' }
        ], rows),
        maintenance: rows => tableFromRows([
          { label: 'Ticket', render: r => '<a href="' + window.SRP.url + '/modules/maintenance/view.php?id=' + r.id + '">' + esc(r.ticket_number) + '</a>' },
          { label: 'Priority', key: 'priority' },
          { label: 'Status', render: r => esc((r.status||'').replace(/_/g,' ')) },
          { label: 'Description', render: r => '<span class="text-truncate d-inline-block" style="max-width:340px">' + esc(r.description) + '</span>' },
          { label: 'Reported', render: r => esc((r.created_at||'').substring(0,10)) }
        ], rows),
        documents: rows => tableFromRows([
          { label: 'Name', render: r => '<a href="' + window.SRP.url + '/storage/uploads/' + esc(r.file_path) + '" target="_blank">' + esc(r.document_name) + '</a>' },
          { label: 'Type', key: 'file_type' },
          { label: 'Uploaded', render: r => esc((r.uploaded_at||'').substring(0,10)) }
        ], rows),
        communications: rows => tableFromRows([
          { label: 'Channel', key: 'channel' },
          { label: 'Direction', key: 'direction' },
          { label: 'Subject', key: 'subject' },
          { label: 'Status', key: 'status' },
          { label: 'Date', render: r => esc((r.created_at||'').substring(0,16)) }
        ], rows)
      },
      property: {
        units: rows => tableFromRows([
          { label: 'Unit', render: r => '<a href="' + window.SRP.url + '/modules/units/view.php?id=' + r.id + '">' + esc(r.unit_number) + '</a>' },
          { label: 'Type', key: 'unit_type' },
          { label: 'Rent', render: r => money(r.base_rent) },
          { label: 'Tenant', key: 'tenant_name' },
          { label: 'Status', render: r => esc((r.status||'').replace(/_/g,' ')) }
        ], rows),
        maintenance: rows => tableFromRows([
          { label: 'Ticket', key: 'ticket_number' },
          { label: 'Priority', key: 'priority' },
          { label: 'Status', render: r => esc((r.status||'').replace(/_/g,' ')) },
          { label: 'Description', render: r => esc(r.description) }
        ], rows),
        expenses: rows => tableFromRows([
          { label: 'Description', key: 'description' },
          { label: 'Category', key: 'category_name' },
          { label: 'Amount', render: r => money(r.amount) },
          { label: 'Date', key: 'expense_date' },
          { label: 'Status', key: 'payment_status' }
        ], rows),
        documents: rows => tableFromRows([
          { label: 'Name', key: 'document_name' },
          { label: 'Type', key: 'file_type' },
          { label: 'Uploaded', render: r => esc((r.uploaded_at||'').substring(0,10)) }
        ], rows)
      },
      unit: {
        leases: rows => tableFromRows([
          { label: 'Lease', key: 'lease_number' },
          { label: 'Tenant', key: 'tenant_name' },
          { label: 'Period', render: r => esc(r.start_date) + ' → ' + esc(r.end_date) },
          { label: 'Rent', render: r => money(r.monthly_rent) },
          { label: 'Status', key: 'status' }
        ], rows),
        invoices: rows => tableFromRows([
          { label: 'Invoice', key: 'invoice_number' },
          { label: 'Type', key: 'invoice_type' },
          { label: 'Amount', render: r => money(r.amount) },
          { label: 'Paid', render: r => money(r.amount_paid) },
          { label: 'Balance', render: r => money(r.balance) },
          { label: 'Due', key: 'due_date' },
          { label: 'Status', key: 'status' }
        ], rows),
        maintenance: rows => tableFromRows([
          { label: 'Ticket', key: 'ticket_number' },
          { label: 'Priority', key: 'priority' },
          { label: 'Status', key: 'status' },
          { label: 'Description', key: 'description' },
          { label: 'Reported', render: r => esc((r.created_at||'').substring(0,10)) }
        ], rows),
        inspections: rows => tableFromRows([
          { label: 'Type', key: 'inspection_type' },
          { label: 'Date', key: 'inspection_date' },
          { label: 'Status', key: 'status' }
        ], rows)
      }
    };
  
    function loadTab(tab) {
      if (!tab) return;
      if (loaded[tab]) return;
      const panel = document.querySelector('[data-360-panel="' + tab + '"]');
      if (!panel) return;
  
      panel.innerHTML = skeletonHTML();
  
      srpFetch(url + '?type=' + encodeURIComponent(type) + '&id=' + encodeURIComponent(id) + '&tab=' + encodeURIComponent(tab))
        .then(r => r.json())
        .then(data => {
          if (data.error) {
            panel.innerHTML = '<div class="alert alert-danger m-0">' + esc(data.error) + '</div>';
            return;
          }
          if (tab === 'overview') {
            // Overview handled server-side; nothing to do here.
            loaded[tab] = true;
            return;
          }
          const renderer = (renderers[type] || {})[tab];
          panel.innerHTML = renderer
            ? renderer(data.rows || [])
            : '<div class="text-muted small text-center py-4">No renderer for this tab.</div>';
          loaded[tab] = true;
          if (window.lucide) lucide.createIcons();
        })
        .catch(() => {
          panel.innerHTML = '<div class="alert alert-danger m-0">Failed to load.</div>';
        });
    }
  
    // Bind tab buttons
    document.querySelectorAll('[data-srp-tab]').forEach(btn => {
      btn.addEventListener('shown.bs.tab', () => loadTab(btn.dataset.srpTab));
    });
  
    // Preload the first non-overview tab after a beat to warm the cache
    setTimeout(() => {
      const firstTab = document.querySelector('[data-srp-tab]:not([data-srp-tab="overview"])');
      if (firstTab) loadTab(firstTab.dataset.srpTab);
    }, 400);
  })();