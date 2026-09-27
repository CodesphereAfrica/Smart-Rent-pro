<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('accounting.view');

$buckets = db_one(
    'SELECT
       COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) BETWEEN 0 AND 30 THEN balance ELSE 0 END),0)  AS b1,
       COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) BETWEEN 31 AND 60 THEN balance ELSE 0 END),0) AS b2,
       COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) BETWEEN 61 AND 90 THEN balance ELSE 0 END),0) AS b3,
       COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) > 90 THEN balance ELSE 0 END),0) AS b4,
       COALESCE(SUM(balance),0) AS total
       FROM invoices
      WHERE company_id = :c AND balance > 0 AND status IN ("due","partially_paid","overdue")',
    [':c' => (int)$USER['company_id']]
);

$rows = db_all(
    'SELECT i.id, i.invoice_number, i.amount, i.amount_paid, i.balance, i.due_date, i.status,
            CONCAT(t.first_name," ",t.last_name) AS tenant_name,
            u.unit_number,
            DATEDIFF(CURDATE(), i.due_date) AS days_overdue
       FROM invoices i
       JOIN tenants t ON t.id = i.tenant_id
       JOIN units u ON u.id = i.unit_id
      WHERE i.company_id = :c AND i.balance > 0 AND i.status IN ("due","partially_paid","overdue")
      ORDER BY i.due_date ASC',
    [':c' => (int)$USER['company_id']]
);

$PAGE_TITLE = __('Accounts Receivable');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Accounts Receivable Aging')) ?></h1>

<div class="kpi-grid mb-3">
  <div class="kpi"><div class="kpi-icon"><i data-lucide="calendar"></i></div>
    <div class="kpi-label"><?= e(__('1–30 Days')) ?></div>
    <div class="kpi-value"><?= e(money($buckets['b1'])) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="calendar"></i></div>
    <div class="kpi-label"><?= e(__('31–60 Days')) ?></div>
    <div class="kpi-value"><?= e(money($buckets['b2'])) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="calendar"></i></div>
    <div class="kpi-label"><?= e(__('61–90 Days')) ?></div>
    <div class="kpi-value"><?= e(money($buckets['b3'])) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="alert-octagon"></i></div>
    <div class="kpi-label"><?= e(__('90+ Days')) ?></div>
    <div class="kpi-value text-danger"><?= e(money($buckets['b4'])) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="receipt"></i></div>
    <div class="kpi-label"><?= e(__('Total AR')) ?></div>
    <div class="kpi-value"><?= e(money($buckets['total'])) ?></div></div>
</div>

<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr>
      <th><?= e(__('Invoice')) ?></th><th><?= e(__('Tenant')) ?></th><th><?= e(__('Unit')) ?></th>
      <th><?= e(__('Due')) ?></th><th><?= e(__('Days Overdue')) ?></th>
      <th class="text-end"><?= e(__('Balance')) ?></th>
    </tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4"><?= e(__('No outstanding receivables.')) ?></td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><a href="<?= APP_URL ?>/modules/invoices/view.php?id=<?= (int)$r['id'] ?>"><?= e($r['invoice_number']) ?></a></td>
          <td><?= e($r['tenant_name']) ?></td>
          <td><?= e($r['unit_number']) ?></td>
          <td><?= e($r['due_date']) ?></td>
          <td><?= (int)$r['days_overdue'] > 0 ? '<span class="text-danger">' . (int)$r['days_overdue'] . '</span>' : '—' ?></td>
          <td class="text-end fw-semibold"><?= e(money($r['balance'])) ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>