<?php
/**
 * Owner statement PDF — rendered by mPDF via modules/owner-statements/view.php.
 *
 * Expected in scope:
 *   $st — the row from owner_statements joined with owners, properties, companies.
 *   $incomeLines, $expenseLines, $cid — passed via require scope from the caller.
 */
if (!isset($incomeLines) || !isset($expenseLines)) {
    // Safety fallback if included directly — no lines.
    $incomeLines = $incomeLines ?? [];
    $expenseLines = $expenseLines ?? [];
}
?>
<!doctype html>
<html><head>
<meta charset="utf-8">
<style>
  body { font-family: sans-serif; color: #0f172a; font-size: 12px; }
  .header { border-bottom: 2px solid #4f46e5; padding-bottom: 10px; margin-bottom: 20px; }
  .brand { color: #4f46e5; font-weight: 700; font-size: 20px; }
  h1 { font-size: 18px; margin: 6px 0 2px 0; }
  .meta { color: #64748b; }
  h2 { font-size: 13px; margin: 20px 0 6px 0; color: #334155; }
  table { width: 100%; border-collapse: collapse; margin-top: 4px; }
  th, td { padding: 6px 4px; border-bottom: 1px solid #e6e8f0; text-align: left; }
  th { background: #f6f7fb; font-size: 10px; text-transform: uppercase; color: #64748b; }
  .right { text-align: right; }
  .tot { background: #eef2ff; font-weight: 700; }
  .net { background: #e0e7ff; font-weight: 700; font-size: 14px; padding: 10px; margin-top: 18px; text-align: right; }
</style>
</head><body>

<div class="header">
  <div class="brand"><?= htmlspecialchars($st['company_name'] ?? 'Smart Rent Pro', ENT_QUOTES, 'UTF-8') ?></div>
  <div class="meta"><?= htmlspecialchars((string)$st['company_address'], ENT_QUOTES, 'UTF-8') ?></div>
  <div class="meta">
    <?= htmlspecialchars((string)$st['company_phone'], ENT_QUOTES, 'UTF-8') ?>
    <?php if (!empty($st['company_email'])): ?>
      · <?= htmlspecialchars((string)$st['company_email'], ENT_QUOTES, 'UTF-8') ?>
    <?php endif; ?>
  </div>
</div>

<h1>Owner Statement</h1>
<div class="meta">
  Owner: <strong><?= htmlspecialchars($st['owner_name'], ENT_QUOTES, 'UTF-8') ?></strong> ·
  Property: <strong><?= htmlspecialchars($st['property_name'], ENT_QUOTES, 'UTF-8') ?></strong><br>
  Period: <strong><?= htmlspecialchars($st['period_start'], ENT_QUOTES, 'UTF-8') ?></strong>
  →
  <strong><?= htmlspecialchars($st['period_end'], ENT_QUOTES, 'UTF-8') ?></strong><br>
  Generated: <?= htmlspecialchars($st['generated_at'], ENT_QUOTES, 'UTF-8') ?>
  <?php if (!empty($st['generator'])): ?>
    by <?= htmlspecialchars($st['generator'], ENT_QUOTES, 'UTF-8') ?>
  <?php endif; ?>
</div>

<h2>Income</h2>
<table>
  <thead><tr><th>Type</th><th class="right">Count</th><th class="right">Amount</th></tr></thead>
  <tbody>
    <?php if (empty($incomeLines)): ?>
      <tr><td colspan="3" class="meta">No income in this period.</td></tr>
    <?php else: foreach ($incomeLines as $l): ?>
      <tr>
        <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $l['invoice_type'])), ENT_QUOTES, 'UTF-8') ?></td>
        <td class="right"><?= (int)$l['cnt'] ?></td>
        <td class="right"><?= number_format((float)$l['total'], 2) ?></td>
      </tr>
    <?php endforeach; endif; ?>
  </tbody>
  <tfoot>
    <tr class="tot"><th colspan="2">Total Income</th><th class="right"><?= number_format((float)$st['total_income'], 2) ?></th></tr>
  </tfoot>
</table>

<h2>Expenses</h2>
<table>
  <thead><tr><th>Category</th><th class="right">Count</th><th class="right">Amount</th></tr></thead>
  <tbody>
    <?php if (empty($expenseLines)): ?>
      <tr><td colspan="3" class="meta">No expenses in this period.</td></tr>
    <?php else: foreach ($expenseLines as $l): ?>
      <tr>
        <td><?= htmlspecialchars($l['category'], ENT_QUOTES, 'UTF-8') ?></td>
        <td class="right"><?= (int)$l['cnt'] ?></td>
        <td class="right"><?= number_format((float)$l['total'], 2) ?></td>
      </tr>
    <?php endforeach; endif; ?>
  </tbody>
  <tfoot>
    <tr class="tot"><th colspan="2">Total Expenses</th><th class="right"><?= number_format((float)$st['total_expenses'], 2) ?></th></tr>
  </tfoot>
</table>

<h2>Summary</h2>
<table>
  <tbody>
    <tr><td>Total Income</td><td class="right"><?= number_format((float)$st['total_income'], 2) ?></td></tr>
    <tr><td>Less: Total Expenses</td><td class="right">− <?= number_format((float)$st['total_expenses'], 2) ?></td></tr>
    <tr><td>Less: Management Fee (<?= number_format((float)$st['management_fee_pct'], 2) ?>%)</td><td class="right">− <?= number_format((float)$st['management_fee_amount'], 2) ?></td></tr>
  </tbody>
</table>

<div class="net">
  Net Owner Income: <?= number_format((float)$st['net_owner_income'], 2) ?>
</div>

</body></html>