<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('invoices.view');
$id = (int)($_GET['id'] ?? 0);
$i = db_one(
    'SELECT i.*,
            CONCAT(t.first_name," ",t.last_name) AS tenant_name,
            t.phone AS tenant_phone, t.email AS tenant_email, t.address AS tenant_address,
            u.unit_number, u.id AS unit_id,
            p.name AS property_name, p.address AS property_address,
            c.name AS company_name, c.address AS company_address, c.phone AS company_phone,
            c.email AS company_email, c.logo_path AS company_logo
       FROM invoices i
       JOIN tenants t ON t.id = i.tenant_id
       JOIN units   u ON u.id = i.unit_id
       JOIN properties p ON p.id = u.property_id
       JOIN companies c ON c.id = i.company_id
      WHERE i.id = :id AND i.company_id = :c LIMIT 1',
    [':id'=>$id, ':c'=>(int)$USER['company_id']]
);
if (!$i) { flash_error(__('Invoice not found.')); redirect('modules/invoices/index.php'); }

$items = db_all('SELECT description, amount FROM invoice_items WHERE invoice_id = :i', [':i' => $id]);
$allocs = db_all(
    'SELECT pa.id, pa.amount_allocated, pa.allocation_type, p.id AS payment_id, p.payment_date, p.payment_method, p.payment_reference
       FROM payment_allocations pa
       JOIN payments p ON p.id = pa.payment_id
      WHERE pa.invoice_id = :i AND p.status = "completed"
      ORDER BY p.payment_date DESC',
    [':i' => $id]
);

// ----------------------------------------------------------------
// PDF rendering — mPDF via the template at templates/pdf/invoice.php
// ----------------------------------------------------------------
if (($_GET['pdf'] ?? '') === '1') {
    $html = (function () use ($i, $items) {
        ob_start();
        require SRP_ROOT . '/templates/pdf/invoice.php';
        return ob_get_clean();
    })();

    $mpdf = new \Mpdf\Mpdf([
        'format' => 'A4',
        'margin_top' => 20, 'margin_bottom' => 20, 'margin_left' => 15, 'margin_right' => 15,
        'tempDir' => SRP_ROOT . '/storage/cache',
    ]);
    $mpdf->SetTitle('Invoice ' . $i['invoice_number']);
    $mpdf->WriteHTML($html);
    $mpdf->Output('invoice-' . $i['invoice_number'] . '.pdf', \Mpdf\Output\Destination::INLINE);
    exit;
}

$PAGE_TITLE = $i['invoice_number'];
require __DIR__ . '/../../includes/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb small">
  <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/invoices/index.php"><?= e(__('Invoices')) ?></a></li>
  <li class="breadcrumb-item active"><?= e($i['invoice_number']) ?></li>
</ol></nav>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
  <div>
    <h1 class="h4 mb-1"><?= e(__('Invoice')) ?> <?= e($i['invoice_number']) ?></h1>
    <div class="text-muted small">
      <a href="<?= APP_URL ?>/modules/tenants/view.php?id=<?= (int)$i['tenant_id'] ?>"><?= e($i['tenant_name']) ?></a> ·
      <?= e($i['unit_number']) ?> · <?= e($i['property_name']) ?>
    </div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/invoices/view.php?id=<?= (int)$id ?>&pdf=1" target="_blank">
      <i data-lucide="file-text"></i> <?= e(__('PDF')) ?>
    </a>
    <?php if (can('payments.create') && (float)$i['balance'] > 0): ?>
      <a class="btn btn-primary" href="<?= APP_URL ?>/modules/payments/create.php?invoice_id=<?= (int)$id ?>">
        <i data-lucide="banknote"></i> <?= e(__('Record Payment')) ?>
      </a>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="srp-card p-4">
      <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
          <div class="text-muted small"><?= e(__('From')) ?></div>
          <div class="fw-semibold"><?= e($i['company_name']) ?></div>
          <div class="small text-muted"><?= e($i['company_address']) ?></div>
          <div class="small text-muted"><?= e($i['company_phone']) ?></div>
        </div>
        <div class="text-end">
          <div class="text-muted small"><?= e(__('Bill To')) ?></div>
          <div class="fw-semibold"><?= e($i['tenant_name']) ?></div>
          <div class="small text-muted"><?= e($i['tenant_address']) ?></div>
          <div class="small text-muted"><?= e($i['tenant_phone']) ?></div>
        </div>
      </div>

      <div class="row small mb-3">
        <div class="col-3"><span class="text-muted"><?= e(__('Type')) ?>:</span> <?= e(ucfirst(str_replace('_',' ',$i['invoice_type']))) ?></div>
        <div class="col-3"><span class="text-muted"><?= e(__('Issued')) ?>:</span> <?= e($i['created_at']) ?></div>
        <div class="col-3"><span class="text-muted"><?= e(__('Due')) ?>:</span> <?= e($i['due_date']) ?></div>
        <div class="col-3"><span class="text-muted"><?= e(__('Status')) ?>:</span> <?= e(ucfirst(str_replace('_',' ',$i['status']))) ?></div>
        <?php if ($i['period_start'] && $i['period_end']): ?>
          <div class="col-12 mt-1"><span class="text-muted"><?= e(__('Period')) ?>:</span> <?= e($i['period_start']) ?> → <?= e($i['period_end']) ?></div>
        <?php endif; ?>
      </div>

      <table class="table align-middle">
        <thead><tr><th><?= e(__('Description')) ?></th><th class="text-end"><?= e(__('Amount')) ?></th></tr></thead>
        <tbody>
          <?php if ($items): foreach ($items as $it): ?>
            <tr><td><?= e($it['description']) ?></td><td class="text-end"><?= e(money($it['amount'])) ?></td></tr>
          <?php endforeach; else: ?>
            <tr><td><?= e(ucfirst(str_replace('_',' ',$i['invoice_type']))) ?><?= $i['period_start'] ? ' (' . $i['period_start'] . ' – ' . $i['period_end'] . ')' : '' ?></td>
                <td class="text-end"><?= e(money($i['amount'])) ?></td></tr>
          <?php endif; ?>
        </tbody>
        <tfoot>
          <tr><th><?= e(__('Total')) ?></th><th class="text-end"><?= e(money($i['amount'])) ?></th></tr>
          <tr><th><?= e(__('Paid')) ?></th><th class="text-end"><?= e(money($i['amount_paid'])) ?></th></tr>
          <tr class="table-active"><th><?= e(__('Balance')) ?></th><th class="text-end"><?= e(money($i['balance'])) ?></th></tr>
        </tfoot>
      </table>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="srp-card p-4">
      <h2 class="h6 mb-3"><?= e(__('Payment History')) ?></h2>
      <?php if (!$allocs): ?>
        <div class="text-muted small"><?= e(__('No payments allocated yet.')) ?></div>
      <?php else: ?>
        <ul class="list-unstyled small">
          <?php foreach ($allocs as $a): ?>
            <li class="border-bottom pb-2 mb-2">
              <div><a href="<?= APP_URL ?>/modules/payments/view.php?id=<?= (int)$a['payment_id'] ?>"><?= e(__('Payment')) ?> #<?= (int)$a['payment_id'] ?></a></div>
              <div class="text-muted"><?= e($a['payment_date']) ?> · <?= e(ucfirst(str_replace('_',' ',$a['payment_method']))) ?></div>
              <div class="fw-semibold"><?= e(money($a['amount_allocated'])) ?> <span class="text-muted fw-normal small">(<?= e($a['allocation_type']) ?>)</span></div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>