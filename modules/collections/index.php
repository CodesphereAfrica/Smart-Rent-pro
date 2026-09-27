<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('payments.view');

$buckets = db_one(
    'SELECT
       COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) BETWEEN 1 AND 30 THEN balance ELSE 0 END),0) AS b1,
       COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) BETWEEN 31 AND 60 THEN balance ELSE 0 END),0) AS b2,
       COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) BETWEEN 61 AND 90 THEN balance ELSE 0 END),0) AS b3,
       COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) > 90 THEN balance ELSE 0 END),0) AS b4
       FROM invoices WHERE company_id = :c AND balance > 0 AND status IN ("due","partially_paid","overdue")',
    [':c' => (int)$USER['company_id']]
);

// Collections Center list — one row per tenant with balance summary
$rows = db_all(
    'SELECT t.id AS tenant_id, CONCAT(t.first_name," ",t.last_name) AS tenant_name,
            t.phone, u.unit_number,
            COUNT(i.id) AS invoices_count,
            SUM(i.balance) AS total_balance,
            MIN(i.due_date) AS oldest_due,
            MAX(DATEDIFF(CURDATE(), i.due_date)) AS max_days_overdue
       FROM tenants t
       JOIN invoices i ON i.tenant_id = t.id AND i.balance > 0 AND i.status IN ("due","partially_paid","overdue")
       LEFT JOIN leases l ON l.tenant_id = t.id AND l.status IN ("active","expiring")
       LEFT JOIN units u ON u.id = l.unit_id
      WHERE t.company_id = :c
      GROUP BY t.id
      ORDER BY max_days_overdue DESC',
    [':c' => (int)$USER['company_id']]
);

$stages = db_all('SELECT * FROM collection_workflow_stages WHERE company_id = :c AND is_active = 1 ORDER BY stage_order', [':c'=>(int)$USER['company_id']]);

$PAGE_TITLE = __('Collections Center');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Collections Center')) ?></h1>

<div class="kpi-grid mb-3">
  <div class="kpi"><div class="kpi-icon"><i data-lucide="alarm-clock"></i></div>
    <div class="kpi-label"><?= e(__('1–30 Days')) ?></div>
    <div class="kpi-value"><?= e(money($buckets['b1'])) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="alarm-clock"></i></div>
    <div class="kpi-label"><?= e(__('31–60 Days')) ?></div>
    <div class="kpi-value"><?= e(money($buckets['b2'])) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="alarm-clock"></i></div>
    <div class="kpi-label"><?= e(__('61–90 Days')) ?></div>
    <div class="kpi-value"><?= e(money($buckets['b3'])) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="alert-octagon"></i></div>
    <div class="kpi-label"><?= e(__('90+ Days')) ?></div>
    <div class="kpi-value text-danger"><?= e(money($buckets['b4'])) ?></div></div>
</div>

<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr>
      <th><?= e(__('Tenant')) ?></th><th><?= e(__('Unit')) ?></th>
      <th><?= e(__('Invoices')) ?></th><th><?= e(__('Total Balance')) ?></th>
      <th><?= e(__('Oldest Due')) ?></th><th><?= e(__('Days Overdue')) ?></th><th></th>
    </tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4"><?= e(__('No tenants in arrears.')) ?></td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><a href="<?= APP_URL ?>/modules/tenants/view.php?id=<?= (int)$r['tenant_id'] ?>"><?= e($r['tenant_name']) ?></a>
            <div class="text-muted small"><?= e($r['phone']) ?></div></td>
          <td><?= e($r['unit_number'] ?? '—') ?></td>
          <td><?= (int)$r['invoices_count'] ?></td>
          <td class="text-danger fw-semibold"><?= e(money($r['total_balance'])) ?></td>
          <td><?= e($r['oldest_due']) ?></td>
          <td><?= (int)$r['max_days_overdue'] ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-primary" href="<?= APP_URL ?>/modules/communications/compose.php?related_type=tenant&related_id=<?= (int)$r['tenant_id'] ?>&template=overdue_notice">
              <i data-lucide="send"></i> <?= e(__('Send Notice')) ?>
            </a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>