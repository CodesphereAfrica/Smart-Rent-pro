<?php
// modules/amenity-bookings/index.php — DataTables list of amenity bookings.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('properties.view');

$PAGE_TITLE   = __('Amenity Bookings');
$PAGE_SCRIPTS = ['amenity-bookings.js'];
require __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0"><?= e(__('Amenity Bookings')) ?></h1>
    <p class="text-muted small mb-0"><?= e(__('Every booking of a shared facility, linked to its tenant.')) ?></p>
  </div>
  <a class="btn btn-primary" href="<?= APP_URL ?>/modules/amenity-bookings/create.php">
    <i data-lucide="plus"></i> <?= e(__('New Booking')) ?>
  </a>
</div>

<div class="srp-card p-3">
  <div class="row g-2 mb-3">
    <div class="col-md-4">
      <select id="fltAmenity" class="form-select form-select-sm">
        <option value=""><?= e(__('All Amenities')) ?></option>
      </select>
    </div>
    <div class="col-md-3">
      <select id="fltTenant" class="form-select form-select-sm">
        <option value=""><?= e(__('All Tenants')) ?></option>
      </select>
    </div>
    <div class="col-md-3">
      <select id="fltStatus" class="form-select form-select-sm">
        <option value=""><?= e(__('All Statuses')) ?></option>
        <option value="pending"><?= e(__('Pending')) ?></option>
        <option value="confirmed"><?= e(__('Confirmed')) ?></option>
        <option value="cancelled"><?= e(__('Cancelled')) ?></option>
        <option value="completed"><?= e(__('Completed')) ?></option>
      </select>
    </div>
  </div>

  <table id="srpAmenityBookingsTable" class="table table-hover align-middle w-100">
    <thead>
      <tr>
        <th><?= e(__('Amenity')) ?></th>
        <th><?= e(__('Tenant')) ?></th>
        <th><?= e(__('Date')) ?></th>
        <th><?= e(__('Time')) ?></th>
        <th><?= e(__('Fee Paid')) ?></th>
        <th><?= e(__('Status')) ?></th>
        <th style="width:80px" data-orderable="false" class="text-end"><?= e(__('Actions')) ?></th>
      </tr>
    </thead>
  </table>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>