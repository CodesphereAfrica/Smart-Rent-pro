<?php
/** @var array $i */
/** @var array $items */
?>
<!doctype html>
<html><head>
<meta charset="utf-8">
<style>
  body { font-family: sans-serif; color: #0f172a; font-size: 12px; }
  .header { border-bottom: 2px solid #4f46e5; padding-bottom: 10px; margin-bottom: 20px; }
  .brand { color: #4f46e5; font-weight: 700; font-size: 20px; }
  h1 { font-size: 18px; margin: 0; }
  table { width: 100%; border-collapse: collapse; margin-top: 10px; }
  th, td { padding: 8px 6px; border-bottom: 1px solid #e6e8f0; text-align: left; }
  th { background: #f6f7fb; font-size: 11px; text-transform: uppercase; color: #64748b; }
  .right { text-align: right; }
  .totals th { background: #eef2ff; }
  .meta { color: #64748b; }
</style>
</head><body>

<div class="header">
  <div class="brand"><?= htmlspecialchars($i['company_name'] ?? 'Smart Rent Pro', ENT_QUOTES, 'UTF-8') ?></div>
  <div class="meta"><?= htmlspecialchars($i['company_address'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
  <div class="meta"><?= htmlspecialchars($i['company_phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>
    <?= $i['company_email'] ? ' · ' . htmlspecialchars($i['company_email'], ENT_QUOTES, 'UTF-8') : '' ?></div>
</div>

<h1>Invoice <?= htmlspecialchars($i['invoice_number'], ENT_QUOTES, 'UTF-8') ?></h1>
<div class="meta">
  Issued: <?= htmlspecialchars($i['created_at'], ENT_QUOTES, 'UTF-8') ?> ·
  Due: <?= htmlspecialchars($i['due_date'], ENT_QUOTES, 'UTF-8') ?> ·
  Status: <?= htmlspecialchars(ucfirst(str_replace('_',' ',$i['status'])), ENT_QUOTES, 'UTF-8') ?>
</div>

<table style="margin-top:20px">
  <tr><td style="width:50%">
    <div class="meta">From</div>
    <strong><?= htmlspecialchars($i['company_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>
  </td>
  <td>
    <div class="meta">Bill To</div>
    <strong><?= htmlspecialchars($i['tenant_name'], ENT_QUOTES, 'UTF-8') ?></strong><br>
    <span class="meta"><?= htmlspecialchars((string)$i['tenant_address'], ENT_QUOTES, 'UTF-8') ?></span><br>
    <span class="meta"><?= htmlspecialchars((string)$i['tenant_phone'], ENT_QUOTES, 'UTF-8') ?></span>
  </td></tr>
</table>

<table>
  <thead><tr><th>Description</th><th class="right">Amount</th></tr></thead>
  <tbody>
  <?php if (!empty($items)): foreach ($items as $it): ?>
    <tr><td><?= htmlspecialchars($it['description'], ENT_QUOTES, 'UTF-8') ?></td>
        <td class="right"><?= number_format((float)$it['amount'], 2) ?></td></tr>
  <?php endforeach; else: ?>
    <tr><td><?= htmlspecialchars(ucfirst(str_replace('_',' ',$i['invoice_type'])), ENT_QUOTES, 'UTF-8') ?><?= $i['period_start'] ? ' (' . htmlspecialchars($i['period_start'], ENT_QUOTES, 'UTF-8') . ' – ' . htmlspecialchars($i['period_end'], ENT_QUOTES, 'UTF-8') . ')' : '' ?></td>
        <td class="right"><?= number_format((float)$i['amount'], 2) ?></td></tr>
  <?php endif; ?>
  </tbody>
  <tfoot class="totals">
    <tr><th>Total</th><th class="right"><?= number_format((float)$i['amount'], 2) ?></th></tr>
    <tr><th>Paid</th><th class="right"><?= number_format((float)$i['amount_paid'], 2) ?></th></tr>
    <tr><th>Balance</th><th class="right"><?= number_format((float)$i['balance'], 2) ?></th></tr>
  </tfoot>
</table>

<p class="meta" style="margin-top:24px">Thank you for your business.</p>

</body></html>