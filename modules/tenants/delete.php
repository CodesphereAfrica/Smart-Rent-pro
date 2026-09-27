<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('tenants.delete');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$t = db_one('SELECT * FROM tenants WHERE id = :id AND company_id = :c LIMIT 1',
            [':id' => $id, ':c' => (int)$USER['company_id']]);
if (!$t) { flash_error(__('Tenant not found.')); redirect('modules/tenants/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $hasActive = db_one('SELECT id FROM leases WHERE tenant_id = :t AND status IN ("active","expiring") LIMIT 1', [':t' => $id]);
    $hasMoney  = db_one('SELECT id FROM payments WHERE tenant_id = :t LIMIT 1', [':t' => $id]);

    if ($hasActive) {
        flash_error(__('Cannot archive — tenant has an active lease. Terminate it first.'));
        redirect('modules/tenants/view.php?id=' . $id);
    }

    // Financial records are preserved — we mark the tenant as former rather than
    // deleting any row that is referenced by invoices/payments.
    if ($hasMoney) {
        db_update('tenants', ['status' => 'former'], 'id = :id', [':id' => $id]);
        log_audit('tenants', 'archive', 'tenants', $id, $t, ['status' => 'former']);
        flash_success(__('Tenant archived (financial history preserved).'));
    } else {
        db_update('tenants', ['status' => 'former', 'deleted_at' => date('Y-m-d H:i:s')],
                  'id = :id', [':id' => $id]);
        log_audit('tenants', 'archive_soft_delete', 'tenants', $id, $t, ['status' => 'former']);
        flash_success(__('Tenant archived.'));
    }
    redirect('modules/tenants/index.php');
}

$PAGE_TITLE = __('Archive Tenant');
require __DIR__ . '/../../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-6"><div class="srp-card p-4">
  <h1 class="h5"><?= e(__('Archive Tenant')) ?></h1>
  <p><?= e(__('Archive')) ?> <strong><?= e($t['first_name'].' '.$t['last_name']) ?></strong>?</p>
  <p class="text-muted small"><?= e(__('Tenants with financial history are marked as "Former" and preserved for audit. This action can be reversed by editing the tenant.')) ?></p>
  <form method="post" class="d-flex justify-content-end gap-2">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/tenants/view.php?id=<?= (int)$id ?>"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-danger"><i data-lucide="archive"></i> <?= e(__('Archive')) ?></button>
  </form>
</div></div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>