<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('accounting.view');

$from = sanitize_string($_GET['from'] ?? date('Y-01-01'), 20);
$to   = sanitize_string($_GET['to']   ?? date('Y-m-d'),  20);

$rows = db_all(
    'SELECT coa.account_code, coa.account_name, coa.account_type,
            COALESCE(SUM(jl.debit),0)  AS debit,
            COALESCE(SUM(jl.credit),0) AS credit
       FROM chart_of_accounts coa
       LEFT JOIN journal_entry_lines jl ON jl.account_id = coa.id
       LEFT JOIN journal_entries je ON je.id = jl.journal_entry_id
      WHERE coa.company_id = :c AND (je.id IS NULL OR (je.entry_date BETWEEN :f AND :t))
      GROUP BY coa.id
      ORDER BY coa.account_code',
    [':c'=>(int)$USER['company_id'], ':f'=>$from, ':t'=>$to]
);

$PAGE_TITLE = __('Trial Balance');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Trial Balance')) ?></h1>

<form class="srp-card p-3 mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label"><?= e(__('From')) ?></label>
      <input type="date" name="from" class="form-control" value="<?= e($from) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('To')) ?></label>
      <input type="date" name="to" class="form-control" value="<?= e($to) ?>"></div>
    <div class="col-md-2"><button class="btn btn-primary w-100"><?= e(__('Apply')) ?></button></div>
  </div>
</form>

<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr><th><?= e(__('Code')) ?></th><th><?= e(__('Account')) ?></th><th><?= e(__('Type')) ?></th>
      <th class="text-end"><?= e(__('Debit')) ?></th><th class="text-end"><?= e(__('Credit')) ?></th></tr></thead>
    <tbody>
      <?php $td = 0; $tc = 0; foreach ($rows as $r): $td += (float)$r['debit']; $tc += (float)$r['credit']; ?>
        <tr>
          <td><?= e($r['account_code']) ?></td>
          <td><?= e($r['account_name']) ?></td>
          <td><?= e(ucfirst($r['account_type'])) ?></td>
          <td class="text-end"><?= e(money($r['debit'])) ?></td>
          <td class="text-end"><?= e(money($r['credit'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr class="table-active fw-bold">
        <th colspan="3"><?= e(__('Total')) ?></th>
        <th class="text-end"><?= e(money($td)) ?></th>
        <th class="text-end"><?= e(money($tc)) ?></th>
      </tr>
    </tfoot>
  </table>
  <?php if (abs($td - $tc) > 0.01): ?>
    <div class="alert alert-warning small mb-0"><?= e(__('Trial balance is out of balance by')) ?> <?= e(money(abs($td - $tc))) ?></div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>