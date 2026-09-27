<?php
// modules/meters/readings.php — list + create manual meter readings.
// source = 'manual' here. The same table accepts source = 'iot_device' from the API.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('properties.view');
$meterId = (int)($_GET['meter_id'] ?? 0);
$utilityId = (int)($_GET['utility_id'] ?? 0);

// Handle new reading POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $postMeterId = (int)($_POST['meter_id'] ?? 0);
    $prev = (float)($_POST['previous_reading'] ?? 0);
    $curr = (float)($_POST['current_reading'] ?? 0);
    $date = sanitize_string($_POST['reading_date'] ?? date('Y-m-d'), 20);

    // Lookup last reading to set previous automatically if user left it blank
    if ($prev <= 0) {
        $last = db_one('SELECT current_reading FROM meter_readings WHERE meter_id = :m ORDER BY id DESC LIMIT 1', [':m'=>$postMeterId]);
        if ($last) $prev = (float)$last['current_reading'];
    }

    $photo = null;
    if (!empty($_FILES['meter_photo']['tmp_name'])) {
        $photo = store_upload($_FILES['meter_photo'], 'meters', ['image/jpeg','image/png','image/webp']);
    }

    $id = db_insert('meter_readings', [
        'meter_id' => $postMeterId,
        'previous_reading' => $prev,
        'current_reading' => $curr,
        'reading_date' => $date,
        'meter_photo_path' => $photo,
        'source' => 'manual',
        'recorded_by' => (int)$USER['id'],
    ]);
    log_audit('meter_readings','create','meter_readings',$id,null,['meter_id'=>$postMeterId,'reading'=>$curr]);
    flash_success(__('Reading recorded.'));
    redirect('modules/meters/readings.php?meter_id=' . $postMeterId);
}

// Load readings list
$where = ['ut.company_id = :c']; $params = [':c'=>(int)$USER['company_id']];
if ($meterId)   { $where[] = 'r.meter_id = :m';    $params[':m'] = $meterId; }
if ($utilityId) { $where[] = 'ut.id = :ut';        $params[':ut'] = $utilityId; }

$readings = db_all(
    'SELECT r.*, m.meter_number, ut.utility_type, p.name AS property_name, u.unit_number,
            CONCAT(us.first_name," ",us.last_name) AS recorder
       FROM meter_readings r
       JOIN utility_meters m ON m.id = r.meter_id
       JOIN utilities ut ON ut.id = m.utility_id
       JOIN properties p ON p.id = ut.property_id
       LEFT JOIN units u ON u.id = m.unit_id
       LEFT JOIN users us ON us.id = r.recorded_by
      WHERE ' . implode(' AND ', $where) . '
      ORDER BY r.reading_date DESC, r.id DESC
      LIMIT 300',
    $params
);

$meters = db_all('SELECT m.id, m.meter_number, ut.utility_type FROM utility_meters m JOIN utilities ut ON ut.id = m.utility_id WHERE ut.company_id = :c ORDER BY m.meter_number', [':c'=>(int)$USER['company_id']]);

$PAGE_TITLE = __('Meter Readings');
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h1 class="h4 mb-0"><?= e(__('Meter Readings')) ?></h1>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newReadingModal"><i data-lucide="plus"></i> <?= e(__('Record Reading')) ?></button>
</div>

<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr>
      <th><?= e(__('Date')) ?></th><th><?= e(__('Meter')) ?></th><th><?= e(__('Property / Unit')) ?></th>
      <th><?= e(__('Previous')) ?></th><th><?= e(__('Current')) ?></th><th><?= e(__('Usage')) ?></th>
      <th><?= e(__('Source')) ?></th><th><?= e(__('Photo')) ?></th><th><?= e(__('Recorded By')) ?></th>
    </tr></thead>
    <tbody>
      <?php if (!$readings): ?><tr><td colspan="9" class="text-center text-muted py-4"><?= e(__('No readings yet.')) ?></td></tr>
      <?php else: foreach ($readings as $r): ?>
        <tr>
          <td><?= e($r['reading_date']) ?></td>
          <td><?= e($r['meter_number']) ?> <span class="text-muted">(<?= e($r['utility_type']) ?>)</span></td>
          <td><?= e($r['property_name']) ?><?= $r['unit_number'] ? ' · ' . e($r['unit_number']) : '' ?></td>
          <td><?= number_format((float)$r['previous_reading'], 3) ?></td>
          <td><?= number_format((float)$r['current_reading'], 3) ?></td>
          <td><span class="fw-semibold"><?= number_format((float)$r['usage_calculated'], 3) ?></span></td>
          <td><?= $r['source'] === 'iot_device' ? '<span class="badge bg-info">IoT</span>' : '<span class="badge bg-secondary">Manual</span>' ?></td>
          <td><?= $r['meter_photo_path'] ? '<a href="' . e(UPLOAD_URL . '/' . $r['meter_photo_path']) . '" target="_blank"><i data-lucide="image"></i></a>' : '—' ?></td>
          <td><?= e($r['recorder'] ?? '—') ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<!-- New reading modal -->
<div class="modal fade" id="newReadingModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="modal-header"><h5 class="modal-title"><?= e(__('Record Meter Reading')) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label"><?= e(__('Meter')) ?></label>
          <select name="meter_id" class="form-select" required>
            <option value=""><?= e(__('— select —')) ?></option>
            <?php foreach ($meters as $m): ?>
              <option value="<?= (int)$m['id'] ?>" <?= $meterId===(int)$m['id']?'selected':'' ?>><?= e($m['meter_number']) ?> (<?= e($m['utility_type']) ?>)</option>
            <?php endforeach; ?>
          </select></div>
        <div class="row g-2">
          <div class="col-md-6"><label class="form-label"><?= e(__('Previous Reading')) ?></label>
            <input type="number" step="0.001" name="previous_reading" class="form-control" placeholder="auto"></div>
          <div class="col-md-6"><label class="form-label"><?= e(__('Current Reading')) ?> *</label>
            <input type="number" step="0.001" name="current_reading" class="form-control" required></div>
          <div class="col-md-6"><label class="form-label"><?= e(__('Reading Date')) ?></label>
            <input type="date" name="reading_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
          <div class="col-md-6"><label class="form-label"><?= e(__('Meter Photo')) ?></label>
            <input type="file" name="meter_photo" class="form-control" accept="image/*" capture="environment"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= e(__('Cancel')) ?></button>
        <button class="btn btn-primary"><?= e(__('Save Reading')) ?></button>
      </div>
    </form>
  </div></div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>