<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('accounting.view');

$from = sanitize_string($_GET['from'] ?? date('Y-m-01'), 20);
$to   = sanitize_string($_GET['to']   ?? date('Y-m-d'),  20);
$propertyId = (int)($_GET['property_id'] ?? 0);

$where = ['je.company_id = :c', 'je.entry_date BETWEEN :f AND :t'];
$params = [':c'=>(int)$USER['company_id'], ':f'=>$from, ':t'=>$to];
if ($propertyId) { $where[] = 'jl.property_id = :p'; $params[':p'] = $propertyId; }

$rows = db_all(
    'SELECT coa.account_type, coa.account_code, coa.account_name,
            COALESCE(SUM(jl.debit),0) AS debit,
            COALESCE(SUM(jl.credit),0) AS credit
       FROM chart_of_accounts coa
       LEFT JOIN journal_entry_lines jl ON jl.account_id = coa.id
       LEFT JOIN journal_entries je ON je.id = jl.journal_entry_id
      WHERE coa.company_id = :c AND coa.account_type IN ("revenue","expense")
        AND (je.id IS NULL OR (je.entry_date BETWEEN :f AND :t))
      GROUP BY coa.id
      ORDER BY coa.account_type DESC, coa.account_code',
    [':c'=>(int)$USER['company_id'], ':f'=>$from, ':t'=>$to]
);

$revenue = 0; $expense = 0;
$revenueRows = []; $expenseRows = [];
foreach ($rows as $r) {
    if ($r['account_type'] === 'revenue') { $revenue += ((float)$r['credit'] - (float)$r['debit']); $revenueRows[] = $r; }
    else                                   { $expense += ((float)$r['debit'] - (float)$r['credit']); $expenseRows[] = $r; }
}
$netIncome = $revenue - $expense;

// --------------------------------------------------------------
// PDF export via mPDF — uses templates/pdf/income-statement.php
// --------------------------------------------------------------
if (($_GET['pdf'] ?? '') === '1') {
    $html = (function () use ($from, $to, $revenueRows, $expenseRows, $revenue, $expense, $netIncome) {
        ob_start();
        require SRP_ROOT . '/templates/pdf/income-statement.php';
        return ob_get_clean();
    })();
    $mpdf = new \Mpdf\Mpdf(['format'=>'A4','tempDir'=>SRP_ROOT.'/storage/cache']);
    $mpdf->SetTitle('Income Statement');
    $mpdf->WriteHTML($html);
    $mpdf->Output('income-statement-' . $from . '-' . $to . '.pdf', \Mpdf\Output\Destination::INLINE);
    exit;
}

$PAGE_TITLE = __('Income Statement');
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0"><?= e(__('Income Statement')) ?></h1>
  <a class="btn btn-light" href="?<?= e(http_build_query(array_merge($_GET, ['pdf'=>1]))) ?>"><i data-lucide="file-text"></i> <?= e(__('PDF')) ?></a>
</div>

<form class="srp-card p-3 mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label"><?= e(__('From')) ?></label>
      <input type="date" name="from" class="form-control" value="<?= e($from) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('To')) ?></label>
      <input type="date" name="to" class="form-control" value="<?= e($to) ?>"></div>
    <div class="col-md-2"><button class="btn btn-primary w-100"><?= e(__('Apply')) ?></button></div>
  </div>
</form>

<div class="row g-3 mb-3">
  <div class="col-md-4"><div class="kpi"><div class="kpi-icon"><i data-lucide="trending-up"></i></div>
    <div class="kpi-label"><?= e(__('Revenue')) ?></div>
    <div class="kpi-value text-success"><?= e(money($revenue)) ?></div></div></div>
  <div class="col-md-4"><div class="kpi"><div class="kpi-icon"><i data-lucide="trending-down"></i></div>
    <div class="kpi-label"><?= e(__('Expenses')) ?></div>
    <div class="kpi-value text-danger"><?= e(money($expense)) ?></div></div></div>
  <div class="col-md-4"><div class="kpi"><div class="kpi-icon"><i data-lucide="wallet"></i></div>
    <div class="kpi-label"><?= e(__('Net Income')) ?></div>
    <div class="kpi-value <?= $netIncome >= 0 ? 'text-success' : 'text-danger' ?>"><?= e(money($netIncome)) ?></div></div></div>
</div>

<div class="srp-card p-3">
  <h2 class="h6"><?= e(__('Revenue')) ?></h2>
  <table class="table table-sm">
    <?php foreach ($revenueRows as $r): ?>
      <tr><td><?= e($r['account_code'] . ' — ' . $r['account_name']) ?></td>
          <td class="text-end"><?= e(money((float)$r['credit'] - (float)$r['debit'])) ?></td></tr>
    <?php endforeach; ?>
    <tr class="table-active"><th><?= e(__('Total Revenue')) ?></th><th class="text-end"><?= e(money($revenue)) ?></th></tr>
  </table>

  <h2 class="h6 mt-4"><?= e(__('Expenses')) ?></h2>
  <table class="table table-sm">
    <?php foreach ($expenseRows as $r): ?>
      <tr><td><?= e($r['account_code'] . ' — ' . $r['account_name']) ?></td>
          <td class="text-end"><?= e(money((float)$r['debit'] - (float)$r['credit'])) ?></td></tr>
    <?php endforeach; ?>
    <tr class="table-active"><th><?= e(__('Total Expenses')) ?></th><th class="text-end"><?= e(money($expense)) ?></th></tr>
  </table>

  <div class="d-flex justify-content-end fs-5 fw-bold mt-3">
    <span class="me-3"><?= e(__('Net Income')) ?>:</span>
    <span class="<?= $netIncome >= 0 ? 'text-success' : 'text-danger' ?>"><?= e(money($netIncome)) ?></span>
  </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>