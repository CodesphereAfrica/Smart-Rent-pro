<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $theme = in_array($_POST['theme'] ?? '', ['light','dark'], true) ? $_POST['theme'] : 'light';
    $lang  = sanitize_string($_POST['language'] ?? 'en', 5);

    db_update('users', ['preferred_language' => $lang], 'id = :id', [':id' => (int)$USER['id']]);

    // Upsert theme preference
    $existing = db_one('SELECT id FROM user_preferences WHERE user_id = :u AND pref_key = "ui.theme"', [':u' => (int)$USER['id']]);
    if ($existing) {
        db_update('user_preferences', ['pref_value' => $theme], 'id = :id', [':id' => (int)$existing['id']]);
    } else {
        db_insert('user_preferences', ['user_id' => (int)$USER['id'], 'pref_key' => 'ui.theme', 'pref_value' => $theme]);
    }
    setcookie('srp_theme', $theme, time() + 31536000, '/');

    flash_success(__('Preferences saved.'));
    redirect('modules/profile/preferences.php');
}

$prefs = db_all('SELECT pref_key, pref_value FROM user_preferences WHERE user_id = :u', [':u' => (int)$USER['id']]);
$prefMap = [];
foreach ($prefs as $p) $prefMap[$p['pref_key']] = $p['pref_value'];

$PAGE_TITLE = __('Preferences');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Preferences')) ?></h1>
<form method="post" class="srp-card p-4" style="max-width:640px">
  <?= csrf_field() ?>
  <div class="mb-3"><label class="form-label"><?= e(__('Theme')) ?></label>
    <select name="theme" class="form-select">
      <option value="light" <?= ($prefMap['ui.theme'] ?? 'light')==='light'?'selected':'' ?>><?= e(__('Light')) ?></option>
      <option value="dark"  <?= ($prefMap['ui.theme'] ?? '')==='dark'?'selected':'' ?>><?= e(__('Dark')) ?></option>
    </select></div>
  <div class="mb-3"><label class="form-label"><?= e(__('Language')) ?></label>
    <select name="language" class="form-select">
      <?php foreach (['en'=>'English','rw'=>'Kinyarwanda','fr'=>'Français'] as $code => $label): ?>
        <option value="<?= e($code) ?>" <?= ($USER['preferred_language'] ?? 'en')===$code?'selected':'' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select></div>
  <div class="d-flex justify-content-end">
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Preferences')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>