<?php /** Income Statement PDF **/ ?>
<!doctype html><html><head><meta charset="utf-8">
<style>
  body { font-family: sans-serif; color:#0f172a; font-size:12px; }
  h1 { font-size:18px; margin:0 0 4px 0; }
  .meta { color:#64748b; margin-bottom:14px; }
  table { width:100%; border-collapse:collapse; margin-bottom:14px; }
  th, td { padding:6px 6px; border-bottom:1px solid #e6e8f0; }
  th { background:#f6f7fb; }
  .right { text-align:right; }
  .tot { background:#eef2ff; font-weight:700; }
  .net { background:#e0e7ff; font-weight:700; font-size:14px; padding:10px; margin-top:10px; text-align:right; }
</style></head><body>

<h1>Income Statement</h1>
<div class="meta"><?= htmlspecialchars($from, ENT_QUOTES, 'UTF-8') ?> → <?= htmlspecialchars($to, ENT_QUOTES, 'UTF-8') ?></div>

<table>
  <thead><tr><th>Revenue Account</th><th class="right">Amount</th></tr></thead>
  <tbody>
  <?php foreach ($revenueRows as $r): ?>
    <tr><td><?= htmlspecialchars($r['account_code'] . ' — ' . $r['account_name'], ENT_QUOTES, 'UTF-8') ?></td>
        <td class="right"><?= number_format((float)$r['credit'] - (float)$r['debit'], 2) ?></td></tr>
  <?php endforeach; ?>
  <tr class="tot"><td>Total Revenue</td><td class="right"><?= number_format($revenue, 2) ?></td></tr>
  </tbody>
</table>

<table>
  <thead><tr><th>Expense Account</th><th class="right">Amount</th></tr></thead>
  <tbody>
  <?php foreach ($expenseRows as $r): ?>
    <tr><td><?= htmlspecialchars($r['account_code'] . ' — ' . $r['account_name'], ENT_QUOTES, 'UTF-8') ?></td>
        <td class="right"><?= number_format((float)$r['debit'] - (float)$r['credit'], 2) ?></td></tr>
  <?php endforeach; ?>
  <tr class="tot"><td>Total Expenses</td><td class="right"><?= number_format($expense, 2) ?></td></tr>
  </tbody>
</table>

<div class="net">Net Income: <?= number_format($netIncome, 2) ?></div>

</body></html>