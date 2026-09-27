/* assets/js/modules/allocation.js — loads outstanding invoices, supports manual override.
   Runs against modules/payments/create.php. Also usable on allocate.php. */
   (function () {
    'use strict';
    const form = document.getElementById('srpPaymentForm');
    if (!form) return;
  
    const selTenant = document.getElementById('selTenant');
    const amountInput = document.getElementById('amountInput');
    const manualCb = document.getElementById('manualAlloc');
    const list = document.getElementById('invoiceList');
  
    let outstanding = [];
  
    function renderAuto() {
      if (!outstanding.length) {
        list.innerHTML = '<div class="text-muted small">No outstanding invoices.</div>';
        return;
      }
      list.innerHTML = '<div class="small text-muted mb-2">Auto-allocation preview (order defined by your allocation rules).</div>' +
        outstanding.map(inv => (
          '<div class="d-flex justify-content-between border-bottom py-2 small">' +
            '<div>' +
              '<div class="fw-semibold">' + inv.invoice_number + ' — ' + inv.invoice_type + '</div>' +
              '<div class="text-muted">Due ' + inv.due_date + ' · Balance ' + fmtMoney(inv.balance) + '</div>' +
            '</div>' +
            '<div><span class="badge bg-light text-dark">auto</span></div>' +
          '</div>'
        )).join('');
    }
  
    function renderManual() {
      if (!outstanding.length) {
        list.innerHTML = '<div class="text-muted small">No outstanding invoices.</div>';
        return;
      }
      list.innerHTML = outstanding.map(inv => (
        '<div class="d-flex justify-content-between align-items-center border-bottom py-2 small" data-inv="' + inv.id + '">' +
          '<div>' +
            '<div class="fw-semibold">' + inv.invoice_number + ' — ' + inv.invoice_type + '</div>' +
            '<div class="text-muted">Due ' + inv.due_date + ' · Balance ' + fmtMoney(inv.balance) + '</div>' +
          '</div>' +
          '<div style="width:150px">' +
            '<input type="number" step="0.01" min="0" max="' + inv.balance + '" class="form-control form-control-sm" ' +
                   'name="alloc[' + inv.id + ']" value="0" data-balance="' + inv.balance + '">' +
          '</div>' +
        '</div>'
      )).join('') +
      '<div class="d-flex justify-content-between fw-semibold mt-2">' +
        '<span>Total to allocate:</span><span id="allocTotal">0.00</span>' +
      '</div>';
  
      list.querySelectorAll('input[name^="alloc"]').forEach(inp => {
        inp.addEventListener('input', updateAllocTotal);
      });
      updateAllocTotal();
    }
  
    function updateAllocTotal() {
      let total = 0;
      list.querySelectorAll('input[name^="alloc"]').forEach(inp => {
        total += parseFloat(inp.value) || 0;
      });
      const el = document.getElementById('allocTotal');
      if (el) el.textContent = fmtMoney(total);
    }
  
    function fmtMoney(n) {
      return new Intl.NumberFormat('en-RW', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n || 0) + ' RWF';
    }
  
    function loadOutstanding(tenantId) {
      list.innerHTML = '<div class="srp-skeleton-row" style="height:40px"></div>';
      srpFetch(window.SRP.url + '/modules/payments/create.php?meta=outstanding&tenant_id=' + encodeURIComponent(tenantId))
        .then(r => r.json())
        .then(d => {
          outstanding = d.invoices || [];
          (manualCb.checked ? renderManual : renderAuto)();
        })
        .catch(() => { list.innerHTML = '<div class="alert alert-danger small m-0">Failed to load.</div>'; });
    }
  
    selTenant.addEventListener('change', function () {
      if (!this.value) { list.innerHTML = '<div class="text-muted small">Select a tenant to load their outstanding invoices.</div>'; return; }
      loadOutstanding(this.value);
    });
  
    manualCb.addEventListener('change', () => {
      if (!selTenant.value) return;
      (manualCb.checked ? renderManual : renderAuto)();
    });
  
    if (selTenant.value) loadOutstanding(selTenant.value);
  })();