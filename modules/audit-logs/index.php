<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('audit_logs.view');

$PAGE_TITLE   = __('Audit Log');
$PAGE_SCRIPTS = ['audit-logs.js'];
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0"><?= e(__('Audit Log')) ?></h1>
</div>

<div class="srp-card p-3">
  <div class="row g-2 mb-3">
    <div class="col-md-3">
      <select id="fltModule" class="form-select form-select-sm">
        <option value=""><?= e(__('All Modules')) ?></option>
      </select>
    </div>
    <div class="col-md-3">
      <select id="fltAction" class="form-select form-select-sm">
        <option value=""><?= e(__('All Actions')) ?></option>
        <option value="create">Create</option>
        <option value="update">Update</option>
        <option value="delete">Delete</option>
        <option value="void">Void</option>
        <option value="reverse">Reverse</option>
        <option value="approve">Approve</option>
        <option value="decide">Decide</option>
      </select>
    </div>
    <div class="col-md-3">
      <input type="date" id="fltFrom" class="form-control form-control-sm" placeholder="<?= e(__('From')) ?>">
    </div>
    <div class="col-md-3">
      <input type="date" id="fltTo" class="form-control form-control-sm" placeholder="<?= e(__('To')) ?>">
    </div>
  </div>

  <table id="srpAuditTable" class="table table-hover align-middle w-100">
    <thead>
      <tr>
        <th><?= e(__('When')) ?></th>
        <th><?= e(__('User')) ?></th>
        <th><?= e(__('Module')) ?></th>
        <th><?= e(__('Action')) ?></th>
        <th><?= e(__('Record')) ?></th>
        <th><?= e(__('IP')) ?></th>
        <th style="width:60px" data-orderable="false" class="text-end"><?= e(__('Details')) ?></th>
      </tr>
    </thead>
  </table>
</div>
<div class="modal fade" id="auditModal" tabindex="-1">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Audit Detail</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <h6>Old Values</h6>
      <pre id="auditOld" class="bg-light p-3 rounded small" style="max-height:260px;overflow:auto"></pre>
      <h6 class="mt-3">New Values</h6>
      <pre id="auditNew" class="bg-light p-3 rounded small" style="max-height:260px;overflow:auto"></pre>
    </div>
  </div></div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>