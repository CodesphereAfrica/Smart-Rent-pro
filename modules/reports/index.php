<?php
// modules/reports/index.php — reporting hub (list of every report).
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('reports.view');

$PAGE_TITLE = __('Reports');
require __DIR__ . '/../../includes/header.php';

$tiles = [
    ['href' => 'income',            'icon' => 'trending-up',    'label' => __('Income Statement'),     'desc' => __('Revenue, expenses and net income for a period.')],
    ['href' => 'expenses',          'icon' => 'trending-down',  'label' => __('Expense Breakdown'),    'desc' => __('Expenses by category, property and vendor.')],
    ['href' => 'occupancy',         'icon' => 'pie-chart',      'label' => __('Occupancy'),            'desc' => __('Occupied, vacant, reserved and out-of-service units.')],
    ['href' => 'arrears',           'icon' => 'alert-triangle', 'label' => __('Arrears Aging'),        'desc' => __('Outstanding balances by aging bucket.')],
    ['href' => 'payments',          'icon' => 'banknote',       'label' => __('Payments Summary'),     'desc' => __('Collections by method and period.')],
    ['href' => 'leases',            'icon' => 'file-signature', 'label' => __('Lease Expirations'),    'desc' => __('Leases ending in the next 30/60/90 days.')],
    ['href' => 'maintenance',       'icon' => 'hammer',         'label' => __('Maintenance'),          'desc' => __('Requests by status, priority and property.')],
    ['href' => 'profitability',     'icon' => 'wallet',         'label' => __('Property Profitability'), 'desc' => __('Per-property income vs expenses.')],
    ['href' => 'tax',               'icon' => 'landmark',       'label' => __('Tax & Compliance'),     'desc' => __('Tax records by status and property.')],
    ['href' => 'owner-statements',  'icon' => 'scroll-text',    'label' => __('Owner Statements'),     'desc' => __('Statements issued per owner and period.')],
];
?>
<h1 class="h4 mb-3"><?= e(__('Reports')) ?></h1>
<p class="text-muted small mb-3"><?= e(__('Every report exports to PDF, Excel and CSV.')) ?></p>
<div class="row g-3">
  <?php foreach ($tiles as $t): ?>
    <div class="col-md-4 col-lg-3">
      <a class="srp-card p-4 d-block text-decoration-none text-reset h-100" href="<?= APP_URL ?>/modules/reports/<?= e($t['href']) ?>.php">
        <div class="kpi-icon mb-2"><i data-lucide="<?= e($t['icon']) ?>"></i></div>
        <div class="fw-semibold"><?= e($t['label']) ?></div>
        <div class="text-muted small"><?= e($t['desc']) ?></div>
      </a>
    </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>