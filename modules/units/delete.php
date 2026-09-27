<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('units.delete');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$u = db_one('SELECT * FROM units WHERE id = :id AND company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$u) { flash_error(__('Unit not found.')); redirect('modules/units/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    // Guard against deleting units with active leases or financial history.
    $activeLease = db_one('SELECT id FROM leases WHERE unit_id = :u AND status IN ("active","expiring") LIMIT 1', [':u'=>$id]);
    $hasFinancials = db_one('SELECT id FROM invoices WHERE unit_id = :u LIMIT 1', [':u'=>$id]);
    if ($activeLease || $hasFinancials) {
        flash_error(__('Cannot delete — unit has an active lease or financial history.'));
        redirect('modules/units/view.php?id=' . $id);
    }
    db_query('DELETE FROM units WHERE id = :id', [':id'=>$id]);
    db_query('UPDATE properties SET num_units = (SELECT COUNT(*) FROM units WHERE property_id = :p) WHERE id = :p',
             [':p'=>(int)$u['property_id']]);
    log_audit('units','delete','units',$id,$u,null);
    flash_success(__('Unit deleted.'));
    redirect('modules/units/index.php');
}
$PAGE_TITLE = __('Delete Unit');
require __DIR__ . '/../../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-6"><div class="srp-card p-4">
  <h1 class="h5"><?= e(__('Delete Unit')) ?> <?= e($u['unit_number']) ?></h1>
  <p><?= e(__('This action cannot be undone.')) ?></p>
  <form method="post" class="d-flex justify-content-end gap-2">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/units/view.php?id=<?= (int)$id ?>"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-danger"><i data-lucide="trash-2"></i> <?= e(__('Delete')) ?></button>
  </form>
</div></div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>