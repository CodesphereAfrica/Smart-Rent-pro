<?php
/** @var string $title */
/** @var array $filters */
/** @var array $headers */
/** @var array $rows */
?>
<!doctype html><html><head><meta charset="utf-8">
<style>
  body { font-family: sans-serif; color: #0f172a; font-size: 10px; }
  h1 { font-size: 15px; margin: 0 0 2px 0; }
  .meta { color: #64748b; margin-bottom: 10px; }
  table { width: 100%; border-collapse: collapse; }
  th, td { padding: 5px 6px; border-bottom: 1px solid #e6e8f0; text-align: left; }
  th { background: #f6f7fb; text-transform: uppercase; font-size: 9px; color: #64748b; }
  .right { text-align: right; }
</style>
</head><body>

<h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
<div class="meta">
  Period: <?= htmlspecialchars($filters['from'], ENT_QUOTES, 'UTF-8') ?>
  → <?= htmlspecialchars($filters['to'], ENT_QUOTES, 'UTF-8') ?>
  · Generated: <?= date('Y-m-d H:i') ?>
</div>

<table>
  <thead><tr>
    <?php foreach ($headers as $h): ?>
      <th><?= htmlspecialchars((string)$h, ENT_QUOTES, 'UTF-8') ?></th>
    <?php endforeach; ?>
  </tr></thead>
  <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <?php foreach ($r as $v): ?>
          <td><?= htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') ?></td>
        <?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

</body></html>