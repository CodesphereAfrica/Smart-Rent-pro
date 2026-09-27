<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('buildings.view');
$PAGE_TITLE = __('Buildings');
$PAGE_SCRIPTS = ['buildings.js'];
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="h4 mb-0"><?= e(__('Buildings')) ?></h1>
    <p class="text-muted small mb-0"><?= e(__('Structures within your properties.')) ?></p>
  </div>
  <a href="<?= APP_URL ?>/modules/buildings/create.php" class="btn btn-primary">
    <i data-lucide="plus"></i> <?= e(__('Add Building')) ?>
  </a>
</div>

<div class="srp-card p-3">
  <table id="srpBuildingsTable" class="table table-hover align-middle w-100">
    <thead><tr>
      <th><?= e(__('Building')) ?></th>
      <th><?= e(__('Property')) ?></th>
      <th><?= e(__('Floors')) ?></th>
      <th><?= e(__('Units')) ?></th>
      <th><?= e(__('Status')) ?></th>
      <th style="width:110px" data-orderable="false"><?= e(__('Actions')) ?></th>
    </tr></thead>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>