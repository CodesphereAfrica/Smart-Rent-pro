<?php
// modules/communications/index.php — DataTable list of all communications.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('communications.view');

$stats = db_one(
    'SELECT
        COUNT(*) AS total,
        SUM(status = "queued")    AS queued,
        SUM(status = "sent")      AS sent,
        SUM(status = "delivered") AS delivered,
        SUM(status = "failed")    AS failed,
        SUM(channel = "email")    AS by_email,
        SUM(channel = "sms")      AS by_sms,
        SUM(channel = "in_app")   AS by_in_app
       FROM communications WHERE company_id = :c',
    [':c' => (int)$USER['company_id']]
);

$PAGE_TITLE   = __('Communications');
$PAGE_SCRIPTS = ['communications.js'];
require __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0"><?= e(__('Communications')) ?></h1>
    <p class="text-muted small mb-0"><?= e(__('Every email, SMS, in-app message and (future) WhatsApp message, linked to its record.')) ?></p>
  </div>
  <a class="btn btn-primary" href="<?= APP_URL ?>/modules/communications/compose.php"><i data-lucide="send"></i> <?= e(__('Compose')) ?></a>
</div>

<div class="kpi-grid mb-3">
  <div class="kpi"><div class="kpi-icon"><i data-lucide="mail"></i></div>
    <div class="kpi-label"><?= e(__('Total')) ?></div>
    <div class="kpi-value"><?= (int)$stats['total'] ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="clock"></i></div>
    <div class="kpi-label"><?= e(__('Queued')) ?></div>
    <div class="kpi-value"><?= (int)$stats['queued'] ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="check-circle-2"></i></div>
    <div class="kpi-label"><?= e(__('Sent / Delivered')) ?></div>
    <div class="kpi-value"><?= (int)$stats['sent'] + (int)$stats['delivered'] ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="alert-triangle"></i></div>
    <div class="kpi-label"><?= e(__('Failed')) ?></div>
    <div class="kpi-value text-danger"><?= (int)$stats['failed'] ?></div></div>
</div>

<div class="srp-card p-3">
  <div class="row g-2 mb-3">
    <div class="col-md-2">
      <select id="fltChannel" class="form-select form-select-sm">
        <option value=""><?= e(__('All Channels')) ?></option>
        <option value="email"><?= e(__('Email')) ?></option>
        <option value="sms"><?= e(__('SMS')) ?></option>
        <option value="in_app"><?= e(__('In-App')) ?></option>
        <option value="whatsapp"><?= e(__('WhatsApp')) ?></option>
      </select>
    </div>
    <div class="col-md-2">
      <select id="fltStatus" class="form-select form-select-sm">
        <option value=""><?= e(__('All Statuses')) ?></option>
        <?php foreach (['queued','sent','delivered','failed','read'] as $s): ?>
          <option value="<?= e($s) ?>"><?= e(ucfirst($s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <select id="fltDirection" class="form-select form-select-sm">
        <option value=""><?= e(__('Both Directions')) ?></option>
        <option value="outbound"><?= e(__('Outbound')) ?></option>
        <option value="inbound"><?= e(__('Inbound')) ?></option>
      </select>
    </div>
  </div>

  <table id="srpCommsTable" class="table table-hover align-middle w-100">
    <thead>
      <tr>
        <th><?= e(__('Date')) ?></th>
        <th><?= e(__('Channel')) ?></th>
        <th><?= e(__('Recipient')) ?></th>
        <th><?= e(__('Subject')) ?></th>
        <th><?= e(__('Related To')) ?></th>
        <th><?= e(__('Status')) ?></th>
        <th style="width:80px" data-orderable="false" class="text-end"><?= e(__('Actions')) ?></th>
      </tr>
    </thead>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>