<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('accounting.view');

$total = db_one('SELECT COALESCE(SUM(amount),0) AS amt, COUNT(*) AS cnt FROM expenses WHERE company_id = :c AND payment_status <> "paid"', [':c'=>(int)$USER['company_id']]);

$rows = db_all(
    'SELECT e.id, e.description, e.amount, e.expense_date, e.payment_status,
            cat.name AS category_name, v.name AS vendor_name
       FROM expenses e
       LEFT JOIN expense_categories cat ON cat.id = e.category_id
       LEFT JOIN vendors v ON v.id = e.vendor_id
      WHERE e.company_id = :c AND e.payment_status <> "paid"
      ORDER BY e.expense_date ASC',
    [':c' => (int)$USER['company_id']]
);

$PAGE_TITLE = __('Accounts Payable');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Accounts Payable')) ?></h1>
<div class="kpi-grid mb-3">
  <div class="kpi"><div class="kpi-icon"><i data-lucide="banknote"></i></div>
    <div class="kpi-label"><?= e(__('Total AP')) ?></div>
    <div class="kpi-value text-danger"><?= e(money($total['amt'])) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="hash"></i></div>
    <div class="kpi-label"><?= e(__('Unpaid Items')) ?></div>
    <div class="kpi-value"><?= (int)$total['cnt'] ?></div></div>
</div>

<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr>
      <th><?= e(__('Date')) ?></th><th><?= e(__('Description')) ?></th><th><?= e(__('Category')) ?></th>
      <th><?= e(__('Vendor')) ?></th><th class="text-end"><?= e(__('Amount')) ?></th><th><?= e(__('Status')) ?></th>
    </tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4"><?= e(__('No outstanding payables.')) ?></td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><?= e($r['expense_date']) ?></td>
          <td><a href="<?= APP_URL ?>/modules/expenses/view.php?id=<?= (int)$r['id'] ?>"><?= e($r['description']) ?></a></td>
          <td><?= e($r['category_name'] ?? '—') ?></td>
          <td><?= e($r['vendor_name'] ?? '—') ?></td>
          <td class="text-end"><?= e(money($r['amount'])) ?></td>
          <td><?= e(ucfirst(str_replace('_',' ',$r['payment_status']))) ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>