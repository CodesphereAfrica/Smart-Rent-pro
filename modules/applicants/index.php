<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('tenants.view');

$pipeline = db_one(
    'SELECT
        SUM(stage = "lead")      AS lead,
        SUM(stage = "applied")   AS applied,
        SUM(stage = "screening") AS screening,
        SUM(stage = "approved")  AS approved,
        SUM(stage = "rejected")  AS rejected,
        SUM(stage = "converted") AS converted
       FROM applicants WHERE company_id = :c',
    [':c' => (int)$USER['company_id']]
);

$PAGE_TITLE   = __('Applicants');
$PAGE_SCRIPTS = ['applicants.js'];
require __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0"><?= e(__('Applicants')) ?></h1>
    <p class="text-muted small mb-0"><?= e(__('Lead → application → screening → approval → lease → move-in pipeline.')) ?></p>
  </div>
  <a class="btn btn-primary" href="<?= APP_URL ?>/modules/applicants/create.php"><i data-lucide="plus"></i> <?= e(__('Add Applicant')) ?></a>
</div>

<div class="kpi-grid mb-3">
  <div class="kpi"><div class="kpi-icon"><i data-lucide="user-plus"></i></div><div class="kpi-label"><?= e(__('Leads')) ?></div><div class="kpi-value"><?= (int)$pipeline['lead'] ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="file-text"></i></div><div class="kpi-label"><?= e(__('Applied')) ?></div><div class="kpi-value"><?= (int)$pipeline['applied'] ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="search-check"></i></div><div class="kpi-label"><?= e(__('Screening')) ?></div><div class="kpi-value"><?= (int)$pipeline['screening'] ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="check-circle-2"></i></div><div class="kpi-label"><?= e(__('Approved')) ?></div><div class="kpi-value"><?= (int)$pipeline['approved'] ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="user-check"></i></div><div class="kpi-label"><?= e(__('Converted')) ?></div><div class="kpi-value"><?= (int)$pipeline['converted'] ?></div></div>
</div>

<div class="srp-card p-3">
  <div class="row g-2 mb-3">
    <div class="col-md-3">
      <select id="fltStage" class="form-select form-select-sm">
        <option value=""><?= e(__('All Stages')) ?></option>
        <option value="lead"><?= e(__('Lead')) ?></option>
        <option value="applied"><?= e(__('Applied')) ?></option>
        <option value="screening"><?= e(__('Screening')) ?></option>
        <option value="approved"><?= e(__('Approved')) ?></option>
        <option value="rejected"><?= e(__('Rejected')) ?></option>
        <option value="converted"><?= e(__('Converted')) ?></option>
      </select>
    </div>
  </div>

  <table id="srpApplicantsTable" class="table table-hover align-middle w-100">
    <thead>
      <tr>
        <th><?= e(__('Applicant')) ?></th>
        <th><?= e(__('Contact')) ?></th>
        <th><?= e(__('Unit of Interest')) ?></th>
        <th><?= e(__('Stage')) ?></th>
        <th><?= e(__('Created')) ?></th>
        <th style="width:80px" data-orderable="false" class="text-end"><?= e(__('Actions')) ?></th>
      </tr>
    </thead>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>