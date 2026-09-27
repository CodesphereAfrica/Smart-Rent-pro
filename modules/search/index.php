<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_login();

$PAGE_TITLE = __('Search');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Search')) ?></h1>
<div class="srp-card p-4">
  <input type="search" id="srpPageSearch" class="form-control form-control-lg mb-3"
         placeholder="<?= e(__('Search tenants, properties, units, invoices, payments, leases, maintenance, vendors, documents…')) ?>">
  <div id="srpPageSearchResults"></div>
</div>

<script>
(function () {
  const input = document.getElementById('srpPageSearch');
  const out = document.getElementById('srpPageSearchResults');
  let t = null;
  input.addEventListener('input', () => {
    clearTimeout(t);
    const q = input.value.trim();
    if (q.length < 2) { out.innerHTML = ''; return; }
    t = setTimeout(() => {
      srpFetch(window.SRP.url + '/modules/search/ajax.php?q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(d => {
          if (!d.groups || !d.groups.length) { out.innerHTML = '<div class="text-muted small">No results.</div>'; return; }
          out.innerHTML = d.groups.map(g => (
            '<div class="mb-3"><div class="text-muted text-uppercase small mb-2">' + g.label + '</div>' +
            g.items.map(it => (
              '<a class="d-flex align-items-center gap-2 border-bottom py-2 text-decoration-none text-reset" href="' + it.link + '">' +
                '<i data-lucide="' + (it.icon || 'file') + '"></i>' +
                '<div><div class="fw-semibold">' + it.title + '</div>' +
                (it.subtitle ? '<div class="text-muted small">' + it.subtitle + '</div>' : '') + '</div>' +
              '</a>'
            )).join('') + '</div>'
          )).join('');
          if (window.lucide) lucide.createIcons();
        });
    }, 220);
  });
})();
</script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>