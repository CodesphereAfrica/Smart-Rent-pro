<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_login();

// Save preferences
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $prefs = $_POST['pref'] ?? [];
    db_query('DELETE FROM user_preferences WHERE user_id = :u AND pref_key LIKE "notif.%"', [':u'=>(int)$USER['id']]);
    foreach ((array)$prefs as $key => $value) {
        db_insert('user_preferences', [
            'user_id' => (int)$USER['id'],
            'pref_key' => 'notif.' . preg_replace('/[^a-z_]/i', '', (string)$key),
            'pref_value' => in_array($value, ['on','off'], true) ? $value : 'off',
        ]);
    }
    log_audit('notifications','update_preferences','user_preferences',(int)$USER['id'],null,['count'=>count((array)$prefs)]);
    flash_success(__('Preferences saved.'));
    redirect('modules/notifications/preferences.php');
}

$existing = [];
$rows = db_all('SELECT pref_key, pref_value FROM user_preferences WHERE user_id = :u AND pref_key LIKE "notif.%"', [':u'=>(int)$USER['id']]);
foreach ($rows as $r) $existing[$r['pref_key']] = $r['pref_value'];

$events = [
    'rent_due' => 'Rent due reminder',
    'rent_overdue' => 'Rent overdue notice',
    'payment_received' => 'Payment received',
    'lease_expiring' => 'Lease expiring reminder',
    'maintenance_assigned' => 'Maintenance assigned',
    'maintenance_completed' => 'Maintenance completed',
    'approval_request' => 'Approval request',
    'document_expiring' => 'Document expiring',
    'announcement' => 'Announcement',
];

$PAGE_TITLE = __('Notification Preferences');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Notification Preferences')) ?></h1>
<form method="post" class="srp-card p-4" style="max-width:640px">
  <?= csrf_field() ?>
  <?php foreach ($events as $key => $label):
    $current = $existing['notif.' . $key] ?? 'on';
  ?>
    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
      <div><?= e(__($label)) ?></div>
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="pref[<?= e($key) ?>]" value="on" id="pref_<?= e($key) ?>" <?= $current === 'on' ? 'checked' : '' ?>>
        <label class="form-check-label" for="pref_<?= e($key) ?>"></label>
      </div>
    </div>
  <?php endforeach; ?>
  <div class="d-flex justify-content-end mt-4">
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Preferences')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>