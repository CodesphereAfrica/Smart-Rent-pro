<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('settings.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    // Update base currency on company
    $new = sanitize_string($_POST['base_currency_code'] ?? '', 3);
    if ($new) {
        db_update('companies', ['base_currency_code' => $new], 'id = :id', [':id' => (int)$USER['company_id']]);
        log_audit('settings','update_currency','companies',(int)$USER['company_id'],null,['currency'=>$new]);
    }
    // Optionally add an exchange rate
    $from = (int)($_POST['from_currency_id'] ?? 0);
    $to   = (int)($_POST['to_currency_id'] ?? 0);
    $rate = (float)($_POST['rate'] ?? 0);
    $date = sanitize_string($_POST['effective_date'] ?? '', 20);
    if ($from && $to && $rate > 0 && $date) {
        db_insert('exchange_rates', [
            'company_id' => (int)$USER['company_id'],
            'from_currency_id' => $from,
            'to_currency_id' => $to,
            'rate' => $rate,
            'effective_date' => $date,
        ]);
        log_audit('settings','create_exchange_rate','exchange_rates',null,null,['from'=>$from,'to'=>$to,'rate'=>$rate]);
    }
    flash_success(__('Currency settings saved.'));
    redirect('modules/settings/currency.php');
}

$company = db_one('SELECT base_currency_code FROM companies WHERE id = :id', [':id'=>(int)$USER['company_id']]);
$currencies = db_all('SELECT id, code, name, symbol FROM currencies WHERE is_active = 1 ORDER BY code');
$rates = db_all(
    'SELECT er.*, fc.code AS from_code, tc.code AS to_code
       FROM exchange_rates er
       JOIN currencies fc ON fc.id = er.from_currency_id
       JOIN currencies tc ON tc.id = er.to_currency_id
      WHERE er.company_id = :c
      ORDER BY er.effective_date DESC LIMIT 50',
    [':c'=>(int)$USER['company_id']]
);

$PAGE_TITLE = __('Currency');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Currency Settings')) ?></h1>

<form method="post" class="srp-card p-4 mb-3">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label"><?= e(__('Base Currency')) ?></label>
      <select name="base_currency_code" class="form-select">
        <?php foreach ($currencies as $cu): ?>
          <option value="<?= e($cu['code']) ?>" <?= $company['base_currency_code']===$cu['code']?'selected':'' ?>><?= e($cu['code'] . ' — ' . $cu['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-2 align-self-end"><button class="btn btn-primary w-100"><?= e(__('Update Base')) ?></button></div>
  </div>
</form>

<form method="post" class="srp-card p-4 mb-3">
  <?= csrf_field() ?>
  <h2 class="h6 mb-3"><?= e(__('Add Exchange Rate')) ?></h2>
  <div class="row g-3">
    <div class="col-md-3"><label class="form-label"><?= e(__('From')) ?></label>
      <select name="from_currency_id" class="form-select">
        <?php foreach ($currencies as $cu): ?><option value="<?= (int)$cu['id'] ?>"><?= e($cu['code']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('To')) ?></label>
      <select name="to_currency_id" class="form-select">
        <?php foreach ($currencies as $cu): ?><option value="<?= (int)$cu['id'] ?>"><?= e($cu['code']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Rate')) ?></label>
      <input type="number" step="0.000001" name="rate" class="form-control"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Effective Date')) ?></label>
      <input type="date" name="effective_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
  </div>
  <div class="d-flex justify-content-end mt-3"><button class="btn btn-primary"><?= e(__('Add Rate')) ?></button></div>
</form>

<div class="srp-card p-3">
  <h2 class="h6 mb-3"><?= e(__('Recent Exchange Rates')) ?></h2>
  <table class="table table-sm">
    <thead><tr><th><?= e(__('Date')) ?></th><th><?= e(__('From')) ?></th><th><?= e(__('To')) ?></th><th><?= e(__('Rate')) ?></th></tr></thead>
    <tbody>
    <?php if (!$rates): ?><tr><td colspan="4" class="text-muted">—</td></tr>
    <?php else: foreach ($rates as $r): ?>
      <tr><td><?= e($r['effective_date']) ?></td><td><?= e($r['from_code']) ?></td><td><?= e($r['to_code']) ?></td>
          <td><?= e((string)$r['rate']) ?></td></tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>