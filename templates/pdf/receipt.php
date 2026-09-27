<?php
/** @var array $p */
/** @var array $allocs */
/** @var string $receiptNumber */
?>
<!doctype html>
<html><head><meta charset="utf-8">
<style>
  body { font-family: sans-serif; color: #0f172a; font-size: 12px; }
  .brand { color: #4f46e5; font-weight: 700; font-size: 18px; }
  h1 { font-size: 16px; margin: 4px 0 0 0; }
  table { width: 100%; border-collapse: collapse; margin-top: 10px; }
  th, td { padding: 6px 4px; border-bottom: 1px solid #e6e8f0; text-align: left; }
  th { background: #f6f7fb; font-size: 10px; text-transform: uppercase; color: #64748b; }
  .right { text-align: right; }
  .meta { color: #64748b; }
  .total { background: #eef2ff; font-size: 14px; font-weight: 700; }
</style>
</head><body>

<div class="brand"><?= htmlspecialchars($p['company_name'] ?? 'Smart Rent Pro', ENT_QUOTES, 'UTF-8') ?></div>
<h1>Payment Receipt <?= htmlspecialchars($receiptNumber, ENT_QUOTES, 'UTF-8') ?></h1>
<div class="meta">
  Date: <?= htmlspecialchars($p['payment_date'], ENT_QUOTES, 'UTF-8') ?> ·
  Method: <?= htmlspecialchars(ucfirst(str_replace('_',' ',$p['payment_method'])), ENT_QUOTES, 'UTF-8') ?>
  <?= $p['payment_reference'] ? ' · Ref: ' . htmlspecialchars($p['payment_reference'], ENT_QUOTES, 'UTF-8') : '' ?>
</div>

<table style="margin-top:16px">
  <tr>
    <td style="width:50%">
      <div class="meta">Received From</div>
      <strong><?= htmlspecialchars($p['tenant_name'], ENT_QUOTES, 'UTF-8') ?></strong><br>
      <span class="meta"><?= htmlspecialchars((string)$p['tenant_phone'], ENT_QUOTES, 'UTF-8') ?></span><br>
      <span class="meta">Unit: <?= htmlspecialchars((string)$p['unit_number'], ENT_QUOTES, 'UTF-8') ?></span>
    </td>
    <td>
      <div class="meta">Amount Received</div>
      <div style="font-size:20px;font-weight:700"><?= number_format((float)$p['amount'], 2) ?> <?= htmlspecialchars((string)($p['base_currency_code'] ?? 'RWF'), ENT_QUOTES, 'UTF-8') ?></div>
      <div class="meta">Unallocated: <?= number_format((float)$p['amount_unallocated'], 2) ?></div>
    </td>
  </tr>
</table>

<?php if (!empty($allocs)): ?>
<table>
  <thead><tr><th>Allocated To</th><th>Type</th><th class="right">Amount</th></tr></thead>
  <tbody>
    <?php foreach ($allocs as $a): ?>
      <tr>
        <td><?= htmlspecialchars($a['invoice_number'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars(ucfirst(str_replace('_',' ',$a['invoice_type'])), ENT_QUOTES, 'UTF-8') ?></td>
        <td class="right"><?= number_format((float)$a['amount_allocated'], 2) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<div class="meta" style="margin-top:20px">Thank you. This is a computer-generated receipt.</div>

</body></html>