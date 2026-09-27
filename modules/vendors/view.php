<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('vendors.view');
$id = (int)($_GET['id'] ?? 0);
$v = db_one('SELECT * FROM vendors WHERE id = :id AND company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$v) { flash_error(__('Vendor not found.')); redirect('modules/vendors/index.php'); }

// Work order history + performance metrics
$workOrders = db_all(
    'SELECT wo.id, wo.work_order_number, wo.status, wo.estimated_cost, wo.actual_cost,
            wo.scheduled_date, wo.completed_date,
            mr.ticket_number, mr.priority
       FROM work_orders wo
       JOIN maintenance_requests mr ON mr.id = wo.maintenance_request_id
      WHERE wo.vendor_id = :v
      ORDER BY wo.id DESC LIMIT 50',
    [':v' => $id]
);
$feedback = db_all(
    'SELECT f.rating, f.comments, f.created_at FROM vendor_feedback f
      WHERE f.vendor_id = :v ORDER BY f.id DESC LIMIT 20',
    [':v' => $id]
);
$expenses = db_one(
    'SELECT COALESCE(SUM(amount),0) AS total, COUNT(*) AS cnt
       FROM expenses WHERE vendor_id = :v',
    [':v' => $id]
);
$avgRating = db_one('SELECT AVG(rating) AS avg FROM vendor_feedback WHERE vendor_id = :v', [':v' => $id]);

$PAGE_TITLE = $v['name'];
require __DIR__ . '/../../includes/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb small">
  <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/vendors/index.php"><?= e(__('Vendors')) ?></a></li>
  <li class="breadcrumb-item active"><?= e($v['name']) ?></li>
</ol></nav>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
  <div>
    <h1 class="h4 mb-1"><?= e($v['name']) ?></h1>
    <div class="text-muted small"><?= e($v['category']) ?>
      <?php if ($v['phone']): ?> · <?= e($v['phone']) ?><?php endif; ?>
      <?php if ($v['email']): ?> · <?= e($v['email']) ?><?php endif; ?>
    </div>
  </div>
  <?php if (can('vendors.edit')): ?>
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/vendors/edit.php?id=<?= (int)$id ?>"><i data-lucide="pencil"></i> <?= e(__('Edit')) ?></a>
  <?php endif; ?>
</div>

<div class="kpi-grid mb-3">
  <div class="kpi"><div class="kpi-icon"><i data-lucide="star"></i></div>
    <div class="kpi-label"><?= e(__('Rating')) ?></div>
    <div class="kpi-value"><?= number_format((float)($avgRating['avg'] ?? $v['rating'] ?? 0), 1) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="clipboard-check"></i></div>
    <div class="kpi-label"><?= e(__('Jobs Completed')) ?></div>
    <div class="kpi-value"><?= (int)$v['jobs_completed'] ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="clock"></i></div>
    <div class="kpi-label"><?= e(__('Avg Response')) ?></div>
    <div class="kpi-value"><?= $v['avg_response_minutes'] ? (int)$v['avg_response_minutes'] . 'm' : '—' ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="banknote"></i></div>
    <div class="kpi-label"><?= e(__('Total Paid')) ?></div>
    <div class="kpi-value"><?= e(money($expenses['total'])) ?></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="srp-card p-3">
      <h2 class="h6 mb-3"><?= e(__('Work Order History')) ?></h2>
      <table class="table table-hover align-middle mb-0">
        <thead><tr>
          <th><?= e(__('WO #')) ?></th><th><?= e(__('Ticket')) ?></th><th><?= e(__('Status')) ?></th>
          <th><?= e(__('Cost')) ?></th><th><?= e(__('Scheduled')) ?></th><th><?= e(__('Completed')) ?></th>
        </tr></thead>
        <tbody>
          <?php if (!$workOrders): ?><tr><td colspan="6" class="text-muted text-center py-4"><?= e(__('No work orders yet.')) ?></td></tr>
          <?php else: foreach ($workOrders as $w): ?>
            <tr>
              <td><a href="<?= APP_URL ?>/modules/work-orders/view.php?id=<?= (int)$w['id'] ?>"><?= e($w['work_order_number']) ?></a></td>
              <td><?= e($w['ticket_number']) ?></td>
              <td><?= e(ucfirst(str_replace('_',' ',$w['status']))) ?></td>
              <td><?= e(money($w['actual_cost'] ?: $w['estimated_cost'] ?: 0)) ?></td>
              <td><?= e($w['scheduled_date'] ?: '—') ?></td>
              <td><?= e($w['completed_date'] ?: '—') ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="srp-card p-3 h-100">
      <h2 class="h6 mb-3"><?= e(__('Latest Feedback')) ?></h2>
      <?php if (!$feedback): ?><div class="text-muted small"><?= e(__('No feedback recorded yet.')) ?></div>
      <?php else: ?>
        <?php foreach ($feedback as $f): ?>
          <div class="border-bottom pb-2 mb-2">
            <div class="d-flex align-items-center gap-1 text-warning">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <i data-lucide="star" style="width:13px;height:13px;<?= $i <= (float)$f['rating'] ? 'fill:#f59e0b' : 'color:#94a3b8' ?>"></i>
              <?php endfor; ?>
              <span class="text-muted ms-2 small"><?= e(date('Y-m-d', strtotime($f['created_at']))) ?></span>
            </div>
            <?php if ($f['comments']): ?><div class="small"><?= e($f['comments']) ?></div><?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>