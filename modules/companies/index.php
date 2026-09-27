<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_login();
if (!is_super_admin($USER)) { http_response_code(403); flash_error(__('Super Admin access only.')); redirect('modules/dashboard/index.php'); }

$PAGE_TITLE   = __('Companies');
$PAGE_SCRIPTS = ['companies.js'];
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0"><?= e(__('Companies')) ?></h1>
    <p class="text-muted small mb-0"><?= e(__('Every tenant company on this platform.')) ?></p>
  </div>
  <a class="btn btn-primary" href="<?= APP_URL ?>/modules/companies/create.php"><i data-lucide="plus"></i> <?= e(__('Add Company')) ?></a>
</div>

<div class="srp-card p-3">
  <table id="srpCompaniesTable" class="table table-hover align-middle w-100">
    <thead><tr>
      <th><?= e(__('Company')) ?></th>
      <th><?= e(__('Slug')) ?></th>
      <th><?= e(__('Currency')) ?></th>
      <th><?= e(__('Language')) ?></th>
      <th><?= e(__('Properties')) ?></th>
      <th><?= e(__('Units')) ?></th>
      <th><?= e(__('Users')) ?></th>
      <th><?= e(__('Status')) ?></th>
      <th style="width:80px" data-orderable="false" class="text-end"><?= e(__('Actions')) ?></th>
    </tr></thead>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>