<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('properties.view');
$utilityId = (int)($_GET['utility_id'] ?? 0);

$where = ['ut.company_id = :c']; $params = [':c'=>(int)$USER['company_id']];
if ($utilityId) { $where[] = 'm.utility_id = :u'; $params[':u'] = $utilityId; }

$meters = db_all(
    'SELECT m.*, ut.utility_type, p.name AS property_name, u.unit_number,
            (SELECT current_reading FROM meter_readings r WHERE r.meter_id = m.id ORDER BY r.id DESC LIMIT 1) AS last_reading
       FROM utility_meters m
       JOIN utilities ut ON ut.id = m.utility_id
       JOIN properties p ON p.id = ut.property_id
       LEFT JOIN units u ON u.id = m.unit_id
      WHERE ' . implode(' AND ', $where) . '
      ORDER BY p.name, m.meter_number',
    $params
);

$PAGE_TITLE = __('Meters');
$PAGE_SCRIPTS = ['meters.js'];
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0"><?= e(__('Meters')) ?></h1>
    <p class="text-muted small mb-0"><?= e(__('Manual readings today; IoT-ready via the same API endpoint (source = iot_device).')) ?></p>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/meters/readings.php<?= $utilityId ? '?utility_id='.(int)$utilityId : '' ?>"><i data-lucide="list"></i> <?= e(__('All Readings')) ?></a>
    <a class="btn btn-primary" href="<?= APP_URL ?>/modules/meters/create.php<?= $utilityId ? '?utility_id='.(int)$utilityId : '' ?>"><i data-lucide="plus"></i> <?= e(__('Add Meter')) ?></a>
  </div>
</div>

<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr>
      <th><?= e(__('Meter #')) ?></th><th><?= e(__('Type')) ?></th><th><?= e(__('Property / Unit')) ?></th>
      <th><?= e(__('Smart?')) ?></th><th><?= e(__('Last Reading')) ?></th><th><?= e(__('Status')) ?></th><th></th>
    </tr></thead>
    <tbody>
      <?php if (!$meters): ?><tr><td colspan="7" class="text-center text-muted py-4"><?= e(__('No meters yet.')) ?></td></tr>
      <?php else: foreach ($meters as $m): ?>
        <tr>
          <td><?= e($m['meter_number']) ?></td>
          <td><?= e(ucfirst($m['utility_type'])) ?></td>
          <td><?= e($m['property_name']) ?><?= $m['unit_number'] ? ' · ' . e($m['unit_number']) : '' ?></td>
          <td><?= $m['is_smart_meter'] ? '<span class="badge bg-info">IoT</span>' : '<span class="badge bg-secondary">Manual</span>' ?></td>
          <td><?= e((string)($m['last_reading'] ?? '—')) ?></td>
          <td><?= e(ucfirst($m['status'])) ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-primary" href="<?= APP_URL ?>/modules/meters/readings.php?meter_id=<?= (int)$m['id'] ?>"><i data-lucide="gauge"></i> <?= e(__('Readings')) ?></a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>