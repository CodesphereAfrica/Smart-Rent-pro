<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('accounting.view');

$asOf = sanitize_string($_GET['as_of'] ?? date('Y-m-d'), 20);

$rows = db_all(
    'SELECT coa.account_type, coa.account_code, coa.account_name,
            COALESCE(SUM(jl.debit),0)  AS debit,
            COALESCE(SUM(jl.credit),0) AS credit
       FROM chart_of_accounts coa
       LEFT JOIN journal_entry_lines jl ON jl.account_id = coa.id
       LEFT JOIN journal_entries je ON je.id = jl.journal_entry_id
      WHERE coa.company_id = :c AND coa.account_type IN ("asset","liability","equity")
        AND (je.id IS NULL OR je.entry_date <= :asof)
      GROUP BY coa.id ORDER BY coa.account_code',
    [':c'=>(int)$USER['company_id'], ':asof'=>$asOf]
);

$assets = []; $liabs = []; $eq = [];
$tA = 0; $tL = 0; $tE = 0;
foreach ($rows as $r) {
    $bal = (float)$r['debit'] - (float)$r['credit'];
    if ($r['account_type'] === 'asset')     { $tA += $bal; $assets[] = ['name'=>$r['account_name'],'code'=>$r['account_code'],'bal'=>$bal]; }
    elseif ($r['account_type'] === 'liability') { $tL += -$bal; $liabs[] = ['name'=>$r['account_name'],'code'=>$r['account_code'],'bal'=>-$bal]; }
    else { $tE += -$bal; $eq[] = ['name'=>$r['account_name'],'code'=>$r['account_code'],'bal'=>-$bal]; }
}

$PAGE_TITLE = __('Balance Sheet');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Balance Sheet')) ?></h1>
<form class="srp-card p-3 mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label"><?= e(__('As of')) ?></label>
      <input type="date" name="as_of" class="form-control" value="<?= e($asOf) ?>"></div>
    <div class="col-md-2"><button class="btn btn-primary w-100"><?= e(__('Apply')) ?></button></div>
  </div>
</form>

<div class="row g-3">
  <div class="col-md-4"><div class="srp-card p-4">
    <h2 class="h6"><?= e(__('Assets')) ?></h2>
    <table class="table table-sm">
      <?php foreach ($assets as $a): ?>
        <tr><td><?= e($a['code'] . ' — ' . $a['name']) ?></td><td class="text-end"><?= e(money($a['bal'])) ?></td></tr>
      <?php endforeach; ?>
      <tr class="table-active fw-bold"><th><?= e(__('Total Assets')) ?></th><th class="text-end"><?= e(money($tA)) ?></th></tr>
    </table>
  </div></div>
  <div class="col-md-4"><div class="srp-card p-4">
    <h2 class="h6"><?= e(__('Liabilities')) ?></h2>
    <table class="table table-sm">
      <?php foreach ($liabs as $l): ?>
        <tr><td><?= e($l['code'] . ' — ' . $l['name']) ?></td><td class="text-end"><?= e(money($l['bal'])) ?></td></tr>
      <?php endforeach; ?>
      <tr class="table-active fw-bold"><th><?= e(__('Total Liabilities')) ?></th><th class="text-end"><?= e(money($tL)) ?></th></tr>
    </table>
  </div></div>
  <div class="col-md-4"><div class="srp-card p-4">
    <h2 class="h6"><?= e(__('Equity')) ?></h2>
    <table class="table table-sm">
      <?php foreach ($eq as $e2): ?>
        <tr><td><?= e($e2['code'] . ' — ' . $e2['name']) ?></td><td class="text-end"><?= e(money($e2['bal'])) ?></td></tr>
      <?php endforeach; ?>
      <tr class="table-active fw-bold"><th><?= e(__('Total Equity')) ?></th><th class="text-end"><?= e(money($tE)) ?></th></tr>
    </table>
  </div></div>
</div>

<div class="alert alert-info small mt-3">
  <?= e(__('Assets')) ?>: <?= e(money($tA)) ?> ·
  <?= e(__('Liabilities + Equity')) ?>: <?= e(money($tL + $tE)) ?>
  <?php if (abs($tA - ($tL + $tE)) > 0.01): ?>
    <br><?= e(__('Out of balance by')) ?> <?= e(money(abs($tA - ($tL + $tE)))) ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>