<?php
// includes/sidebar.php — grouped navigation. Icons are Lucide only.
if (!isset($USER)) { require_once __DIR__ . '/auth.php'; $USER = current_user(); }
$__current = $_SERVER['REQUEST_URI'] ?? '';
$is = function (string $needle) use ($__current): string {
    return strpos($__current, $needle) !== false ? ' active' : '';
};
?>
<aside class="srp-sidebar" id="srpSidebar">
  <a class="srp-brand" href="<?= APP_URL ?>/modules/dashboard/index.php">
    <i data-lucide="building-2"></i>
    <span>Smart Rent Pro</span>
  </a>

  <nav class="srp-nav">
    <div class="srp-nav-group">
      <div class="srp-nav-label"><?= e(__('Overview')) ?></div>
      <a class="srp-nav-link<?= $is('/modules/dashboard/') ?>" href="<?= APP_URL ?>/modules/dashboard/index.php"><i data-lucide="layout-dashboard"></i><span><?= e(__('Dashboard')) ?></span></a>
      <a class="srp-nav-link<?= $is('/modules/today/') ?>" href="<?= APP_URL ?>/modules/today/index.php"><i data-lucide="sun"></i><span><?= e(__('Today')) ?></span></a>
    </div>

    <?php if (can('properties.view')): ?>
    <div class="srp-nav-group">
      <div class="srp-nav-label"><?= e(__('Portfolio')) ?></div>
      <a class="srp-nav-link<?= $is('/modules/properties/') ?>" href="<?= APP_URL ?>/modules/properties/index.php"><i data-lucide="building"></i><span><?= e(__('Properties')) ?></span></a>
      <a class="srp-nav-link<?= $is('/modules/buildings/') ?>"  href="<?= APP_URL ?>/modules/buildings/index.php"><i data-lucide="hotel"></i><span><?= e(__('Buildings')) ?></span></a>
      <a class="srp-nav-link<?= $is('/modules/units/') ?>"      href="<?= APP_URL ?>/modules/units/index.php"><i data-lucide="door-open"></i><span><?= e(__('Units')) ?></span></a>
      <a class="srp-nav-link<?= $is('/modules/amenities/') ?>"  href="<?= APP_URL ?>/modules/amenities/index.php"><i data-lucide="waves"></i><span><?= e(__('Amenities')) ?></span></a>
      <a class="srp-nav-link<?= $is('/modules/parking/') ?>"    href="<?= APP_URL ?>/modules/parking/index.php"><i data-lucide="car"></i><span><?= e(__('Parking')) ?></span></a>
    </div>
    <?php endif; ?>

    <?php if (can('tenants.view')): ?>
    <div class="srp-nav-group">
      <div class="srp-nav-label"><?= e(__('People')) ?></div>
      <a class="srp-nav-link<?= $is('/modules/tenants/') ?>"    href="<?= APP_URL ?>/modules/tenants/index.php"><i data-lucide="users"></i><span><?= e(__('Tenants')) ?></span></a>
      <a class="srp-nav-link<?= $is('/modules/owners/') ?>"     href="<?= APP_URL ?>/modules/owners/index.php"><i data-lucide="user-square"></i><span><?= e(__('Owners')) ?></span></a>
      <a class="srp-nav-link<?= $is('/modules/applicants/') ?>" href="<?= APP_URL ?>/modules/applicants/index.php"><i data-lucide="user-plus"></i><span><?= e(__('Applicants')) ?></span></a>
      <a class="srp-nav-link<?= $is('/modules/vendors/') ?>"    href="<?= APP_URL ?>/modules/vendors/index.php"><i data-lucide="wrench"></i><span><?= e(__('Vendors')) ?></span></a>
    </div>
    <?php endif; ?>

    <?php if (can('leases.view')): ?>
    <div class="srp-nav-group">
      <div class="srp-nav-label"><?= e(__('Leasing')) ?></div>
      <a class="srp-nav-link<?= $is('/modules/leases/') ?>"    href="<?= APP_URL ?>/modules/leases/index.php"><i data-lucide="file-signature"></i><span><?= e(__('Leases')) ?></span></a>
      <a class="srp-nav-link<?= $is('/modules/listings/') ?>"  href="<?= APP_URL ?>/modules/listings/index.php"><i data-lucide="megaphone"></i><span><?= e(__('Listings')) ?></span></a>
      <a class="srp-nav-link<?= $is('/modules/leads/') ?>"     href="<?= APP_URL ?>/modules/leads/index.php"><i data-lucide="target"></i><span><?= e(__('Leads')) ?></span></a>
    </div>
    <?php endif; ?>

    <?php if (can('payments.view')): ?>
    <div class="srp-nav-group">
      <div class="srp-nav-label"><?= e(__('Finance')) ?></div>
      <a class="srp-nav-link<?= $is('/modules/invoices/') ?>"     href="<?= APP_URL ?>/modules/invoices/index.php"><i data-lucide="receipt"></i><span><?= e(__('Invoices')) ?></span></a>
      <a class="srp-nav-link<?= $is('/modules/payments/') ?>"     href="<?= APP_URL ?>/modules/payments/index.php"><i data-lucide="banknote"></i><span><?= e(__('Payments')) ?></span></a>
      <a class="srp-nav-link<?= $is('/modules/collections/') ?>"  href="<?= APP_URL ?>/modules/collections/index.php"><i data-lucide="alarm-clock"></i><span><?= e(__('Collections')) ?></span></a>
      <a class="srp-nav-link<?= $is('/modules/expenses/') ?>"     href="<?= APP_URL ?>/modules/expenses/index.php"><i data-lucide="trending-down"></i><span><?= e(__('Expenses')) ?></span></a>
      <a class="srp-nav-link<?= $is('/modules/accounting/') ?>"   href="<?= APP_URL ?>/modules/accounting/chart-of-accounts.php"><i data-lucide="book-open"></i><span><?= e(__('Accounting')) ?></span></a>
      <a class="srp-nav-link<?= $is('/modules/owner-statements/') ?>" href="<?= APP_URL ?>/modules/owner-statements/index.php"><i data-lucide="scroll-text"></i><span><?= e(__('Owner Statements')) ?></span></a>
    </div>
    <?php endif; ?>

    <?php if (can('maintenance.view')): ?>
    <div class="srp-nav-group">
      <div class="srp-nav-label"><?= e(__('Operations')) ?></div>
      <a class="srp-nav-link<?= $is('/modules/maintenance/') ?>" href="<?= APP_URL ?>/modules/maintenance/index.php"><i data-lucide="hammer"></i><span><?= e(__('Maintenance')) ?></span></a>
      <a class="srp-nav-link<?= $is('/modules/work-orders/') ?>" href="<?= APP_URL ?>/modules/work-orders/index.php"><i data-lucide="clipboard-list"></i><span><?= e(__('Work Orders')) ?></span></a>
      <a class="srp-nav-link<?= $is('/modules/inspections/') ?>" href="<?= APP_URL ?>/modules/inspections/index.php"><i data-lucide="clipboard-check"></i><span><?= e(__('Inspections')) ?></span></a>
      <a class="srp-nav-link<?= $is('/modules/assets/') ?>"      href="<?= APP_URL ?>/modules/assets/index.php"><i data-lucide="package"></i><span><?= e(__('Assets')) ?></span></a>
      <a class="srp-nav-link<?= $is('/modules/utilities/') ?>"   href="<?= APP_URL ?>/modules/utilities/index.php"><i data-lucide="plug-zap"></i><span><?= e(__('Utilities')) ?></span></a>
      <a class="srp-nav-link<?= $is('/modules/meters/') ?>"      href="<?= APP_URL ?>/modules/meters/index.php"><i data-lucide="gauge"></i><span><?= e(__('Meters')) ?></span></a>
    </div>
    <?php endif; ?>

    <div class="srp-nav-group">
      <div class="srp-nav-label"><?= e(__('Communication')) ?></div>
      <a class="srp-nav-link<?= $is('/modules/communications/') ?>" href="<?= APP_URL ?>/modules/communications/index.php"><i data-lucide="mail"></i><span><?= e(__('Communications')) ?></span></a>
      <a class="srp-nav-link<?= $is('/modules/announcements/') ?>"  href="<?= APP_URL ?>/modules/announcements/index.php"><i data-lucide="megaphone"></i><span><?= e(__('Announcements')) ?></span></a>
      <a class="srp-nav-link<?= $is('/modules/notifications/') ?>"  href="<?= APP_URL ?>/modules/notifications/index.php"><i data-lucide="bell"></i><span><?= e(__('Notifications')) ?></span></a>
    </div>

    <?php if (can('reports.view')): ?>
    <div class="srp-nav-group">
      <div class="srp-nav-label"><?= e(__('Reports')) ?></div>
      <a class="srp-nav-link<?= $is('/modules/reports/') ?>"   href="<?= APP_URL ?>/modules/reports/index.php"><i data-lucide="bar-chart-3"></i><span><?= e(__('Reports')) ?></span></a>
      <a class="srp-nav-link<?= $is('/modules/analytics/') ?>" href="<?= APP_URL ?>/modules/analytics/index.php"><i data-lucide="line-chart"></i><span><?= e(__('Analytics')) ?></span></a>
    </div>
    <?php endif; ?>

    <div class="srp-nav-group">
      <div class="srp-nav-label"><?= e(__('Documents')) ?></div>
      <a class="srp-nav-link<?= $is('/modules/documents/') ?>" href="<?= APP_URL ?>/modules/documents/index.php"><i data-lucide="folder"></i><span><?= e(__('Documents')) ?></span></a>
      <a class="srp-nav-link<?= $is('/modules/contracts/') ?>" href="<?= APP_URL ?>/modules/contracts/index.php"><i data-lucide="file-text"></i><span><?= e(__('Contracts')) ?></span></a>
      <a class="srp-nav-link<?= $is('/modules/insurance/') ?>" href="<?= APP_URL ?>/modules/insurance/index.php"><i data-lucide="shield"></i><span><?= e(__('Insurance')) ?></span></a>
      <a class="srp-nav-link<?= $is('/modules/tax/') ?>"       href="<?= APP_URL ?>/modules/tax/index.php"><i data-lucide="landmark"></i><span><?= e(__('Tax & Compliance')) ?></span></a>
    </div>

    <?php if (can('settings.manage') || can('users.manage')): ?>
    <div class="srp-nav-group">
      <div class="srp-nav-label"><?= e(__('Settings')) ?></div>
      <?php if (can('users.manage')): ?>
        <a class="srp-nav-link<?= $is('/modules/users/') ?>" href="<?= APP_URL ?>/modules/users/index.php"><i data-lucide="user-cog"></i><span><?= e(__('Users')) ?></span></a>
        <a class="srp-nav-link<?= $is('/modules/roles/') ?>" href="<?= APP_URL ?>/modules/roles/index.php"><i data-lucide="key-round"></i><span><?= e(__('Roles & Permissions')) ?></span></a>
      <?php endif; ?>
      <?php if (can('settings.manage')): ?>
        <a class="srp-nav-link<?= $is('/modules/approvals/') ?>" href="<?= APP_URL ?>/modules/approvals/workflows.php"><i data-lucide="check-square"></i><span><?= e(__('Approvals')) ?></span></a>
        <a class="srp-nav-link<?= $is('/modules/settings/') ?>"   href="<?= APP_URL ?>/modules/settings/index.php"><i data-lucide="settings"></i><span><?= e(__('System Settings')) ?></span></a>
        <a class="srp-nav-link<?= $is('/modules/audit-logs/') ?>" href="<?= APP_URL ?>/modules/audit-logs/index.php"><i data-lucide="history"></i><span><?= e(__('Audit Log')) ?></span></a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </nav>
</aside>