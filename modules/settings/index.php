<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('settings.manage');

$PAGE_TITLE = __('System Settings');
require __DIR__ . '/../../includes/header.php';

$tiles = [
    ['href'=>'general',             'icon'=>'settings',           'label'=>__('General'),            'desc'=>__('Company name, contact, address')],
    ['href'=>'branding',            'icon'=>'palette',            'label'=>__('Branding'),           'desc'=>__('Logo and theme colours')],
    ['href'=>'currency',            'icon'=>'banknote',           'label'=>__('Currency'),           'desc'=>__('Base currency and exchange rates')],
    ['href'=>'language',            'icon'=>'languages',          'label'=>__('Language'),           'desc'=>__('Default language and translations')],
    ['href'=>'smtp',                'icon'=>'mail',               'label'=>__('SMTP / Email'),       'desc'=>__('Outgoing mail server')],
    ['href'=>'sms',                 'icon'=>'message-square',     'label'=>__('SMS Provider'),       'desc'=>__('Gateway credentials and driver')],
    ['href'=>'late-fees',           'icon'=>'alarm-clock',        'label'=>__('Late Fee Defaults'),  'desc'=>__('Company-wide default rules')],
    ['href'=>'approvals',           'icon'=>'check-square',       'label'=>__('Approval Workflows'), 'desc'=>__('Threshold-based approvals')],
    ['href'=>'notifications',       'icon'=>'bell',               'label'=>__('Notifications'),      'desc'=>__('Event code and template bindings')],
    ['href'=>'document-categories', 'icon'=>'folder',             'label'=>__('Document Categories'),'desc'=>__('Organise the document store')],
    ['href'=>'chart-of-accounts',   'icon'=>'book-open',          'label'=>__('Chart of Accounts'),  'desc'=>__('Seed the accounting structure')],
];
?>
<h1 class="h4 mb-3"><?= e(__('System Settings')) ?></h1>
<div class="row g-3">
  <?php foreach ($tiles as $t): ?>
    <div class="col-md-4 col-lg-3">
      <a class="srp-card p-4 d-block text-decoration-none text-reset h-100" href="<?= APP_URL ?>/modules/settings/<?= e($t['href']) ?>.php">
        <div class="kpi-icon mb-2"><i data-lucide="<?= e($t['icon']) ?>"></i></div>
        <div class="fw-semibold"><?= e($t['label']) ?></div>
        <div class="text-muted small"><?= e($t['desc']) ?></div>
      </a>
    </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>