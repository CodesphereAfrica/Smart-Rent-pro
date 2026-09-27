<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('settings.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name = sanitize_string($_POST['name'] ?? '', 100);
    if ($name !== '') {
        $id = db_insert('document_categories', ['company_id'=>(int)$USER['company_id'], 'name'=>$name]);
        log_audit('document_categories','create','document_categories',$id,null,['name'=>$name]);
    }
    redirect('modules/settings/document-categories.php');
}

$cats = db_all('SELECT * FROM document_categories WHERE company_id = :c ORDER BY name', [':c'=>(int)$USER['company_id']]);
$PAGE_TITLE = __('Document Categories');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Document Categories')) ?></h1>
<div class="row g-3">
  <div class="col-md-6"><div class="srp-card p-4">
    <table class="table table-sm">
      <tbody>
        <?php foreach ($cats as $c): ?>
          <tr><td><?= e($c['name']) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div></div>
  <div class="col-md-6"><div class="srp-card p-4">
    <form method="post">
      <?= csrf_field() ?>
      <div class="mb-3"><label class="form-label"><?= e(__('New Category')) ?></label>
        <input name="name" class="form-control" required></div>
      <button class="btn btn-primary"><i data-lucide="plus"></i> <?= e(__('Add')) ?></button>
    </form>
  </div></div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>