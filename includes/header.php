<?php
// includes/header.php — shared layout chrome top. Every module page requires this.
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$USER = require_login();
$PAGE_TITLE = $PAGE_TITLE ?? 'Dashboard';
$flash = take_flash();
?>
<!doctype html>
<html lang="<?= e($USER['preferred_language'] ?? DEFAULT_LANGUAGE) ?>" data-theme="<?= e($_COOKIE['srp_theme'] ?? 'light') ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($PAGE_TITLE) ?> · <?= e(APP_NAME) ?></title>
<meta name="theme-color" content="#4f46e5">
<link rel="manifest" href="<?= APP_URL ?>/manifest.json">
<link rel="icon" href="<?= APP_URL ?>/assets/img/favicon.svg" type="image/svg+xml">

<!-- Bootstrap 5 (exact pinned CDN) -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
      integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

<!-- DataTables + Buttons (Bootstrap 5) -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

<!-- Custom design system -->
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/custom.css"><link rel="stylesheet" href="<?= APP_URL ?>/assets/css/custom.css">

<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/dark.css">

<script>
  window.SRP = {
    url: <?= json_encode(APP_URL) ?>,
    csrf: <?= json_encode(csrf_token()) ?>,
    user: <?= json_encode(['id'=>(int)$USER['id'],'name'=>trim($USER['first_name'].' '.$USER['last_name']),'role'=>$USER['role_name']]) ?>
  };
</script>
</head>
<body class="srp-app">
<?php require __DIR__ . '/sidebar.php'; ?>
<div class="srp-main">
  <header class="srp-topbar">
    <button class="btn btn-icon" id="srpToggleSidebar" aria-label="Toggle sidebar">
      <i data-lucide="menu"></i>
    </button>

    <div class="srp-search flex-grow-1">
      <i data-lucide="search"></i>
      <input type="search" id="srpGlobalSearch" class="form-control"
             placeholder="<?= e(__('Search tenants, properties, units, invoices…')) ?>" autocomplete="off">
      <div id="srpGlobalSearchResults" class="srp-search-results d-none"></div>
    </div>

    <div class="srp-topbar-actions">
      <button class="btn btn-icon" id="srpThemeToggle" title="<?= e(__('Toggle theme')) ?>">
        <i data-lucide="sun-moon"></i>
      </button>
      <div class="dropdown">
        <button class="btn btn-icon position-relative" data-bs-toggle="dropdown" id="srpNotifBell" title="<?= e(__('Notifications')) ?>">
          <i data-lucide="bell"></i>
          <span class="srp-badge d-none" id="srpNotifBadge">0</span>
        </button>
        <div class="dropdown-menu dropdown-menu-end srp-notif-menu p-0" id="srpNotifDropdown">
          <div class="p-3 border-bottom fw-semibold d-flex justify-content-between">
            <span><?= e(__('Notifications')) ?></span>
            <a href="<?= APP_URL ?>/modules/notifications/index.php" class="small"><?= e(__('View all')) ?></a>
          </div>
          <div class="srp-notif-list" id="srpNotifList"><div class="p-3 text-muted small">…</div></div>
        </div>
      </div>
      <div class="dropdown">
      <button class="btn-user dropdown-toggle" data-bs-toggle="dropdown">
  <?php if (!empty($USER['profile_photo'])): ?>
    <img src="<?= e(UPLOAD_URL . '/' . $USER['profile_photo']) ?>" class="srp-avatar-img" alt="">
  <?php else: ?>
    <span class="srp-avatar"><?= e(strtoupper(substr($USER['first_name'],0,1).substr($USER['last_name'],0,1))) ?></span>
  <?php endif; ?>
  <span class="d-none d-md-inline ms-2"><?= e($USER['first_name']) ?></span>
</button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="<?= APP_URL ?>/modules/profile/index.php"><i data-lucide="user"></i> <?= e(__('Profile')) ?></a></li>
          <li><a class="dropdown-item" href="<?= APP_URL ?>/modules/profile/preferences.php"><i data-lucide="settings-2"></i> <?= e(__('Preferences')) ?></a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/modules/auth/logout.php"><i data-lucide="log-out"></i> <?= e(__('Sign out')) ?></a></li>
        </ul>
      </div>
    </div>
  </header>

  <main class="srp-content">
    <?php if (!empty($flash['success'])): ?>
      <div class="alert alert-success d-none" data-flash="success"><?= e($flash['success']) ?></div>
    <?php endif; ?>
    <?php if (!empty($flash['error'])): ?>
      <div class="alert alert-danger d-none" data-flash="error"><?= e($flash['error']) ?></div>
    <?php endif; ?>
    <?php if (!empty($flash['info'])): ?>
      <div class="alert alert-info d-none" data-flash="info"><?= e($flash['info']) ?></div>
    <?php endif; ?>