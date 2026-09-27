<?php
/**
 * Generic report PDF — rendered by modules/reports/export-pdf.php.
 *
 * Expected in scope:
 *   $title        string
 *   $headers      array   column titles
 *   $rows         array   row arrays (each row is an array of values in header order)
 *   $filters      array   ['from'=>..,'to'=>..,'property_id'=>..]
 *   $company      array   name/address/phone/email/logo_path
 *   $propertyName string  optional
 *   $USER         array   current user (for the footer)
 */

// Column alignment: numeric-looking headers get right-aligned.
$numericKeywords = ['amount', 'total', 'balance', 'income', 'expense', 'net', 'count',
                    'days', 'paid', 'cost', 'rent', 'fee', 'margin', 'units'];
$alignments = [];
foreach ($headers as $i => $h) {
    $lower = strtolower((string)$h);
    $isNumeric = false;
    foreach ($numericKeywords as $k) {
        if (strpos($lower, $k) !== false) { $isNumeric = true; break; }
    }
    $alignments[$i] = $isNumeric ? 'right' : 'left';
}
?>
<!doctype html>
<html><head><meta charset="utf-8">
<style>
  body        { font-family: dejavusans, sans-serif; color: #0f172a; font-size: 10px; }
  .header     { border-bottom: 2px solid #4f46e5; padding-bottom: 8px; margin-bottom: 14px; }
  .brand      { color: #4f46e5; font-weight: 700; font-size: 16px; }
  .meta       { color: #64748b; font-size: 9px; }
  h1          { font-size: 15px; margin: 10px 0 2px 0; }
  .filter-line{ color: #64748b; font-size: 9px; margin-bottom: 10px; }
  table       { width: 100%; border-collapse: collapse; }
  th, td      { padding: 5px 6px; border-bottom: 1px solid #e6e8f0; vertical-align: top; }
  th          { background: #f6f7fb; font-size: 9px; text-transform: uppercase; color: #64748b; }
  tbody tr:nth-child(even) td { background: #fafbfc; }
  .right      { text-align: right; }
  .left       { text-align: left; }
</style>
</head><body>

<div class="header">
  <?php if (!empty($company['logo_path'])): ?>
    <img src="<?= SRP_ROOT . '/storage/uploads/' . htmlspecialchars($company['logo_path'], ENT_QUOTES, 'UTF-8') ?>" style="max-height:40px;margin-bottom:4px">
  <?php endif; ?>
  <div class="brand"><?= htmlspecialchars($company['name'] ?? APP_NAME, ENT_QUOTES, 'UTF-8') ?></div>
  <div class="meta"><?= htmlspecialchars((string)($company['address'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
  <div class="meta">
    <?= htmlspecialchars((string)($company['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
    <?= !empty($company['email']) ? ' · ' . htmlspecialchars((string)$company['email'], ENT_QUOTES, 'UTF-8') : '' ?>
  </div>
</div>

<h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
<div class="filter-line">
  Period: <strong><?= htmlspecialchars((string)($filters['from'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
  → <strong><?= htmlspecialchars((string)($filters['to'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
  <?= $propertyName ? ' · Property: <strong>' . htmlspecialchars($propertyName, ENT_QUOTES, 'UTF-8') . '</strong>' : ' · All properties' ?>
  · <strong><?= count($rows) ?></strong> rows
</div>

<table>
  <thead>
    <tr>
      <?php foreach ($headers as $i => $h): ?>
        <th class="<?= $alignments[$i] === 'right' ? 'right' : 'left' ?>">
          <?= htmlspecialchars((string)$h, ENT_QUOTES, 'UTF-8') ?>
        </th>
      <?php endforeach; ?>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="<?= count($headers) ?>" style="text-align:center;color:#94a3b8;padding:14px">
        No data for this period.
      </td></tr>
    <?php else: foreach ($rows as $r): ?>
      <tr>
        <?php $i = 0; foreach ((array)$r as $v): ?>
          <td class="<?= $alignments[$i] === 'right' ? 'right' : 'left' ?>">
            <?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?>
          </td>
        <?php $i++; endforeach; ?>
      </tr>
    <?php endforeach; endif; ?>
  </tbody>
</table>

</body></html>