<?php
// modules/applicants/convert.php — turns an approved applicant into a tenant record,
// creates an applicant_activities audit trail entry, and redirects to lease creation.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('tenants.create');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$a = db_one('SELECT * FROM applicants WHERE id = :id AND company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$a) { flash_error(__('Applicant not found.')); redirect('modules/applicants/index.php'); }
if ($a['stage'] !== 'approved') { flash_error(__('Only approved applicants can be converted.')); redirect('modules/applicants/view.php?id=' . $id); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $tenantId = null;
    db()->beginTransaction();
    try {
        $tenantId = db_insert('tenants', [
            'company_id' => (int)$USER['company_id'],
            'first_name' => $a['first_name'],
            'last_name'  => $a['last_name'],
            'phone'      => $a['phone'] ?: '—',
            'email'      => $a['email'],
            'employer'   => $a['employer'],
            'tenant_type'=> 'individual',
            'status'     => 'active',
            'notes'      => 'Converted from applicant #' . $id,
        ]);

        db_update('applicants', [
            'stage' => 'converted',
            'converted_tenant_id' => $tenantId,
        ], 'id = :id', [':id' => $id]);

        db_insert('applicant_activities', [
            'applicant_id' => $id,
            'activity_type' => 'converted',
            'description'   => 'Converted to tenant #' . $tenantId,
            'created_by'    => (int)$USER['id'],
        ]);

        log_audit('applicants', 'convert', 'applicants', $id, $a, ['converted_tenant_id' => $tenantId]);
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        error_log('[convert] ' . $e->getMessage());
        flash_error(__('Conversion failed.'));
        redirect('modules/applicants/view.php?id=' . $id);
    }

    flash_success(__('Applicant converted to tenant.'));
    redirect('modules/tenants/view.php?id=' . (int)$tenantId);
}

$PAGE_TITLE = __('Convert Applicant');
require __DIR__ . '/../../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-6"><div class="srp-card p-4">
  <h1 class="h5 mb-3"><?= e(__('Convert to Tenant')) ?></h1>
  <p><?= e(__('Create a tenant record for:')) ?> <strong><?= e($a['first_name'].' '.$a['last_name']) ?></strong>?</p>
  <p class="text-muted small"><?= e(__('The applicant will be marked as converted. You can then create a lease for them.')) ?></p>
  <form method="post" class="d-flex justify-content-end gap-2">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/applicants/view.php?id=<?= (int)$id ?>"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-success"><i data-lucide="user-check"></i> <?= e(__('Convert')) ?></button>
  </form>
</div></div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>