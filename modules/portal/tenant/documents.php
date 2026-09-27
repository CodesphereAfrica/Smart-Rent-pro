<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
$USER = require_role('Tenant');
$tenant = db_one('SELECT id FROM tenants WHERE user_id = :u LIMIT 1', [':u'=>(int)$USER['id']]);
if (!$tenant) { redirect('modules/dashboard/index.php'); }
$tid = (int)$tenant['id'];

$docs = db_all(
    'SELECT d.*, dc.name AS category_name FROM documents d
       LEFT JOIN document_categories dc ON dc.id = d.category_id
      WHERE d.company_id = :c AND (
        (d.related_type = "tenant" AND d.related_id = :t)
        OR (d.related_type IN ("lease","unit","property") AND d.related_id IN (
              SELECT id FROM leases WHERE tenant_id = :t
              UNION SELECT unit_id FROM leases WHERE tenant_id = :t
              UNION SELECT property_id FROM leases WHERE tenant_id = :t
        ))
      )
      ORDER BY d.uploaded_at DESC',
    [':c'=>(int)$USER['company_id'], ':t'=>$tid]
);

$PAGE_TITLE = __('My Documents');
require __DIR__ . '/../../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('My Documents')) ?></h1>
<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr><th><?= e(__('Document')) ?></th><th><?= e(__('Category')) ?></th><th><?= e(__('Uploaded')) ?></th><th></th></tr></thead>
    <tbody>
      <?php if (!$docs): ?><tr><td colspan="4" class="text-center text-muted py-4"><?= e(__('No documents.')) ?></td></tr>
      <?php else: foreach ($docs as $d): ?>
        <tr>
          <td><?= e($d['document_name']) ?></td>
          <td><?= e($d['category_name'] ?? '—') ?></td>
          <td><?= e(date('Y-m-d', strtotime($d['uploaded_at']))) ?></td>
          <td class="text-end"><a class="btn btn-sm btn-light" href="<?= e(UPLOAD_URL . '/' . $d['file_path']) ?>" target="_blank"><i data-lucide="download"></i></a></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../../includes/footer.php'; ?>