<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
$USER = require_role('Tenant');
$tenant = db_one('SELECT id FROM tenants WHERE user_id = :u LIMIT 1', [':u'=>(int)$USER['id']]);
if (!$tenant) { redirect('modules/dashboard/index.php'); }
$tid = (int)$tenant['id'];

$invoices = db_all(
    'SELECT id, invoice_number, invoice_type, amount, amount_paid, balance, due_date, status
       FROM invoices WHERE tenant_id = :t ORDER BY due_date DESC LIMIT 200',
    [':t' => $tid]
);
$payments = db_all(
    'SELECT id, payment_date, amount, payment_method, payment_reference, status
       FROM payments WHERE tenant_id = :t ORDER BY payment_date DESC LIMIT 200',
    [':t' => $tid]
);

$PAGE_TITLE = __('Rent & Invoices');
require __DIR__ . '/../../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Rent & Invoices')) ?></h1>

<ul class="nav nav-tabs mb-3" role="tablist">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#t-invoices"><?= e(__('Invoices')) ?></button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-payments"><?= e(__('Payments')) ?></button></li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="t-invoices">
    <div class="srp-card p-3">
      <table class="table table-hover align-middle">
        <thead><tr><th><?= e(__('Invoice')) ?></th><th><?= e(__('Type')) ?></th><th class="text-end"><?= e(__('Amount')) ?></th>
          <th class="text-end"><?= e(__('Balance')) ?></th><th><?= e(__('Due')) ?></th><th><?= e(__('Status')) ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach ($invoices as $i): ?>
          <tr>
            <td><?= e($i['invoice_number']) ?></td>
            <td><?= e(ucfirst(str_replace('_',' ',$i['invoice_type']))) ?></td>
            <td class="text-end"><?= e(money($i['amount'])) ?></td>
            <td class="text-end <?= (float)$i['balance'] > 0 ? 'text-danger' : 'text-muted' ?>"><?= e(money($i['balance'])) ?></td>
            <td><?= e($i['due_date']) ?></td>
            <td><?= e(ucfirst(str_replace('_',' ',$i['status']))) ?></td>
            <td class="text-end"><a class="btn btn-sm btn-light" href="<?= APP_URL ?>/modules/invoices/view.php?id=<?= (int)$i['id'] ?>" target="_blank"><i data-lucide="eye"></i></a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="tab-pane fade" id="t-payments">
    <div class="srp-card p-3">
      <table class="table table-hover align-middle">
        <thead><tr><th><?= e(__('Date')) ?></th><th class="text-end"><?= e(__('Amount')) ?></th><th><?= e(__('Method')) ?></th>
          <th><?= e(__('Reference')) ?></th><th><?= e(__('Status')) ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach ($payments as $p): ?>
          <tr>
            <td><?= e($p['payment_date']) ?></td>
            <td class="text-end"><?= e(money($p['amount'])) ?></td>
            <td><?= e(ucfirst(str_replace('_',' ',$p['payment_method']))) ?></td>
            <td><?= e($p['payment_reference'] ?? '—') ?></td>
            <td><?= e(ucfirst($p['status'])) ?></td>
            <td class="text-end"><a class="btn btn-sm btn-light" href="<?= APP_URL ?>/modules/payments/receipt.php?id=<?= (int)$p['id'] ?>" target="_blank"><i data-lucide="file-text"></i></a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../../../includes/footer.php'; ?>