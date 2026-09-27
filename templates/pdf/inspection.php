<?php /** Inspection report PDF **/ ?>
<!doctype html><html><head><meta charset="utf-8">
<style>
  body { font-family: sans-serif; color:#0f172a; font-size:12px; }
  h1 { font-size:16px; }
  table { width:100%; border-collapse:collapse; margin-top:10px; }
  th, td { padding:6px; border-bottom:1px solid #e6e8f0; text-align:left; }
  th { background:#f6f7fb; }
  .rating { font-weight:700; }
</style></head><body>
<h1>Inspection Report — <?= htmlspecialchars(ucfirst(str_replace('_',' ',$inspection['inspection_type'])), ENT_QUOTES, 'UTF-8') ?></h1>
<div class="meta">
  Unit <?= htmlspecialchars($unit['unit_number'], ENT_QUOTES, 'UTF-8') ?> ·
  <?= htmlspecialchars($property['name'], ENT_QUOTES, 'UTF-8') ?> ·
  <?= htmlspecialchars($inspection['inspection_date'], ENT_QUOTES, 'UTF-8') ?> ·
  Inspector: <?= htmlspecialchars($inspector['first_name'] . ' ' . $inspector['last_name'], ENT_QUOTES, 'UTF-8') ?>
</div>

<table>
  <thead><tr><th>Item</th><th>Condition</th><th>Notes</th></tr></thead>
  <tbody>
    <?php foreach ($results as $r): ?>
      <tr>
        <td><?= htmlspecialchars($r['item_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
        <td class="rating"><?= htmlspecialchars(ucfirst(str_replace('_',' ',$r['condition_rating'])), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)$r['notes'], ENT_QUOTES, 'UTF-8') ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php if (!empty($inspection['meter_readings'])): ?>
<h2 style="font-size:13px;margin-top:16px">Meter Readings</h2>
<pre><?= htmlspecialchars(print_r(json_decode($inspection['meter_readings'], true), true), ENT_QUOTES, 'UTF-8') ?></pre>
<?php endif; ?>

<?php if (!empty($inspection['notes'])): ?>
<h2 style="font-size:13px;margin-top:16px">Notes</h2>
<div><?= nl2br(htmlspecialchars($inspection['notes'], ENT_QUOTES, 'UTF-8')) ?></div>
<?php endif; ?>
</body></html>