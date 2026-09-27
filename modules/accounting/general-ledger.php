<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('accounting.view');

$accountId = (int)($_GET['account_id'] ?? 0);
$from = sanitize_string($_GET['from'] ?? date('Y-01-01'), 20);
$to   = sanitize_string($_GET['to']   ?? date('Y-m-d'),  20);

$where = ['je.company_id = :c', 'je.entry_date BETWEEN :f AND :t'];
$params = [':c'=>(int)$USER['company_id'], ':f'=>$from, ':t'=>$to];
if ($accountId) { $where[] = 'jl.account_id = :a'; $params[':a'] = $accountId; }

$lines = db_all(
    'SELECT jl.id, jl.debit, jl.credit, jl.description, jl.property_id,
            je.entry_number, je.entry_date, je.reference_type, je.reference_id,
            coa.account_code, coa.account_name, coa.account_type
       FROM journal_entry_lines jl
       JOIN journal_entries je ON je.id = jl.journal_entry_id
       JOIN chart_of_accounts coa ON coa.id = jl.account_id
      WHERE ' . implode(' AND ', $where) . '
      ORDER BY je.entry_date DESC, je.id DESC LIMIT 1000',
    $params
);

$accounts = db_all('SELECT id, account_code, account_name FROM chart_of_accounts WHERE company_id = :c ORDER BY account_code', [':c'=>(int)$USER['company_id']]);

$PAGE_TITLE = __('General Ledger');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('General Ledger')) ?></h1>
<form class="srp-card p-3 mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label"><?= e(__('Account')) ?></label>
      <select name="account_id" class="form-select">
        <option value=""><?= e(__('All Accounts')) ?></option>
        <?php foreach ($accounts as $a): ?>
          <option value="<?= (int)$a['id'] ?>" <?= $accountId===(int)$a['id']?'selected':'' ?>><?= e($a['account_code'] . ' — ' . $a['account_name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('From')) ?></label>
      <input type="date" name="from" class="form-control" value="<?= e($from) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('To')) ?></label>
      <input type="date" name="to" class="form-control" value="<?= e($to) ?>"></div>
    <div class="col-md-2"><button class="btn btn-primary w-100"><?= e(__('Apply')) ?></button></div>
  </div>
</form>

<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr>
      <th><?= e(__('Date')) ?></th><th><?= e(__('Entry')) ?></th><th><?= e(__('Account')) ?></th>
      <th><?= e(__('Reference')) ?></th><th><?= e(__('Description')) ?></th>
      <th class="text-end"><?= e(__('Debit')) ?></th><th class="text-end"><?= e(__('Credit')) ?></th>
    </tr></thead>
    <tbody>
      <?php $td=0;$tc=0; foreach ($lines as $l): $td+=(float)$l['debit']; $tc+=(float)$l['credit']; ?>
        <tr>
          <td><?= e($l['entry_date']) ?></td>
          <td><?= e($l['entry_number']) ?></td>
          <td><?= e($l['account_code'] . ' — ' . $l['account_name']) ?></td>
          <td><?= e(($l['reference_type'] ?? '') . ($l['reference_id'] ? ' #' . $l['reference_id'] : '')) ?></td>
          <td><?= e($l['description'] ?? '—') ?></td>
          <td class="text-end"><?= (float)$l['debit'] ? e(money($l['debit'])) : '' ?></td>
          <td class="text-end"><?= (float)$l['credit'] ? e(money($l['credit'])) : '' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr class="table-active fw-bold">
        <th colspan="5"><?= e(__('Total')) ?></th>
        <th class="text-end"><?= e(money($td)) ?></th>
        <th class="text-end"><?= e(money($tc)) ?></th>
      </tr>
    </tfoot>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>