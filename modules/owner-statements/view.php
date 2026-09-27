<?php
// modules/owner-statements/view.php — view a statement online or download as PDF.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('properties.view');
$cid  = (int)$USER['company_id'];

$id = (int)($_GET['id'] ?? 0);
$st = db_one(
    'SELECT os.*,
            o.full_name  AS owner_name, o.email AS owner_email, o.phone AS owner_phone,
            o.address    AS owner_address, o.tax_id AS owner_tax_id,
            p.name       AS property_name, p.address AS property_address,
            c.name       AS company_name, c.address AS company_address,
            c.phone      AS company_phone, c.email AS company_email, c.logo_path AS company_logo,
            CONCAT(u.first_name, " ", u.last_name) AS generator
       FROM owner_statements os
       JOIN owners     o ON o.id = os.owner_id
       JOIN properties p ON p.id = os.property_id
       JOIN companies  c ON c.id = os.company_id
       LEFT JOIN users u ON u.id = os.generated_by
      WHERE os.id = :id AND os.company_id = :c
      LIMIT 1',
    [':id' => $id, ':c' => $cid]
);
if (!$st) { flash_error(__('Statement not found.')); redirect('modules/owner-statements/index.php'); }

// ---------------------------------------------------------------------------
// PDF export via mPDF.
// ---------------------------------------------------------------------------
if (($_GET['pdf'] ?? '') === '1') {
    $html = (function () use ($st) {
        ob_start();
        require SRP_ROOT . '/templates/pdf/owner-statement.php';
        return ob_get_clean();
    })();

    $mpdf = new \Mpdf\Mpdf([
        'format' => 'A4',
        'margin_top' => 20, 'margin_bottom' => 20, 'margin_left' => 15, 'margin_right' => 15,
        'tempDir' => SRP_ROOT . '/storage/cache',
    ]);
    $mpdf->SetTitle('Owner Statement ' . $st['id']);
    $mpdf->WriteHTML($html);
    $mpdf->Output('owner-statement-' . $st['id'] . '.pdf', \Mpdf\Output\Destination::INLINE);
    exit;
}

// ---------------------------------------------------------------------------
// Line-item detail for the on-screen breakdown.
// Income lines: all paid invoice types in the period.
// Expense lines: categorized expenses in the period.
// ---------------------------------------------------------------------------
$incomeLines = db_all(
    'SELECT i.invoice_type, COUNT(*) AS cnt, COALESCE(SUM(i.amount_paid), 0) AS total
       FROM invoices i
       JOIN units u ON u.id = i.unit_id
      WHERE u.property_id = :p AND i.company_id = :c
        AND i.created_at >= :from AND i.created_at <= :to
        AND i.status IN ("paid","partially_paid")
        AND i.invoice_type IN ("rent","utility","other","deposit")
      GROUP BY i.invoice_type
      ORDER BY total DESC',
    [
        ':p' => (int)$st['property_id'], ':c' => $cid,
        ':from' => $st['period_start'] . ' 00:00:00',
        ':to'   => $st['period_end']   . ' 23:59:59',
    ]
);

$expenseLines = db_all(
    'SELECT COALESCE(ec.name, "Uncategorized") AS category, COUNT(*) AS cnt, COALESCE(SUM(e.amount), 0) AS total
       FROM expenses e
       LEFT JOIN expense_categories ec ON ec.id = e.category_id
      WHERE e.property_id = :p AND e.company_id = :c
        AND e.expense_date BETWEEN :from AND :to
      GROUP BY e.category_id
      ORDER BY total DESC',
    [
        ':p' => (int)$st['property_id'], ':c' => $cid,
        ':from' => $st['period_start'], ':to' => $st['period_end'],
    ]
);

$PAGE_TITLE = __('Owner Statement') . ' #' . $id;
require __DIR__ . '/../../includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb small">
  <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/owner-statements/index.php"><?= e(__('Owner Statements')) ?></a></li>
  <li class="breadcrumb-item active">#<?= (int)$id ?></li>
</ol></nav>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
  <div>
    <h1 class="h4 mb-1"><?= e(__('Owner Statement')) ?></h1>
    <div class="text-muted small">
      <?= e($st['owner_name']) ?> ·
      <?= e($st['property_name']) ?> ·
      <?= e($st['period_start']) ?> → <?= e($st['period_end']) ?>
    </div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/owner-statements/view.php?id=<?= (int)$id ?>&pdf=1" target="_blank">
      <i data-lucide="file-text"></i> <?= e(__('PDF')) ?>
    </a>
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/owner-statements/index.php">
      <i data-lucide="arrow-left"></i> <?= e(__('Back')) ?>
    </a>
  </div>
</div>

<div class="kpi-grid mb-4">
  <div class="kpi"><div class="kpi-icon"><i data-lucide="trending-up"></i></div>
    <div class="kpi-label"><?= e(__('Income')) ?></div>
    <div class="kpi-value text-success"><?= e(money($st['total_income'])) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="trending-down"></i></div>
    <div class="kpi-label"><?= e(__('Expenses')) ?></div>
    <div class="kpi-value text-danger"><?= e(money($st['total_expenses'])) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="percent"></i></div>
    <div class="kpi-label"><?= e(__('Mgmt Fee')) ?> (<?= e(number_format((float)$st['management_fee_pct'], 2)) ?>%)</div>
    <div class="kpi-value"><?= e(money($st['management_fee_amount'])) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="wallet"></i></div>
    <div class="kpi-label"><?= e(__('Net Owner Income')) ?></div>
    <div class="kpi-value <?= (float)$st['net_owner_income'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= e(money($st['net_owner_income'])) ?></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="srp-card p-4 h-100">
      <h2 class="h6 mb-3"><?= e(__('Income Breakdown')) ?></h2>
      <table class="table table-sm mb-0">
        <thead><tr><th><?= e(__('Type')) ?></th><th class="text-end"><?= e(__('Count')) ?></th><th class="text-end"><?= e(__('Amount')) ?></th></tr></thead>
        <tbody>
          <?php if (!$incomeLines): ?>
            <tr><td colspan="3" class="text-muted text-center py-3"><?= e(__('No income in this period.')) ?></td></tr>
          <?php else: foreach ($incomeLines as $l): ?>
            <tr>
              <td><?= e(ucfirst(str_replace('_', ' ', $l['invoice_type']))) ?></td>
              <td class="text-end"><?= (int)$l['cnt'] ?></td>
              <td class="text-end"><?= e(money($l['total'])) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
        <tfoot><tr class="table-active fw-bold"><th colspan="2"><?= e(__('Total Income')) ?></th><th class="text-end"><?= e(money($st['total_income'])) ?></th></tr></tfoot>
      </table>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="srp-card p-4 h-100">
      <h2 class="h6 mb-3"><?= e(__('Expense Breakdown')) ?></h2>
      <table class="table table-sm mb-0">
        <thead><tr><th><?= e(__('Category')) ?></th><th class="text-end"><?= e(__('Count')) ?></th><th class="text-end"><?= e(__('Amount')) ?></th></tr></thead>
        <tbody>
          <?php if (!$expenseLines): ?>
            <tr><td colspan="3" class="text-muted text-center py-3"><?= e(__('No expenses in this period.')) ?></td></tr>
          <?php else: foreach ($expenseLines as $l): ?>
            <tr>
              <td><?= e($l['category']) ?></td>
              <td class="text-end"><?= (int)$l['cnt'] ?></td>
              <td class="text-end"><?= e(money($l['total'])) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
        <tfoot><tr class="table-active fw-bold"><th colspan="2"><?= e(__('Total Expenses')) ?></th><th class="text-end"><?= e(money($st['total_expenses'])) ?></th></tr></tfoot>
      </table>
    </div>
  </div>

  <div class="col-12">
    <div class="srp-card p-4">
      <h2 class="h6 mb-3"><?= e(__('Net Owner Income')) ?></h2>
      <table class="table table-sm mb-0">
        <tbody>
          <tr><td><?= e(__('Total Income')) ?></td><td class="text-end"><?= e(money($st['total_income'])) ?></td></tr>
          <tr><td><?= e(__('Less: Total Expenses')) ?></td><td class="text-end">− <?= e(money($st['total_expenses'])) ?></td></tr>
          <tr><td><?= e(__('Less: Management Fee')) ?> (<?= e(number_format((float)$st['management_fee_pct'], 2)) ?>%)</td><td class="text-end">− <?= e(money($st['management_fee_amount'])) ?></td></tr>
          <tr class="table-active fw-bold"><td><?= e(__('Net Owner Income')) ?></td><td class="text-end"><?= e(money($st['net_owner_income'])) ?></td></tr>
        </tbody>
      </table>
      <div class="text-muted small mt-3">
        <?= e(__('Generated by')) ?> <?= e($st['generator'] ?? '—') ?> · <?= e($st['generated_at']) ?>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>