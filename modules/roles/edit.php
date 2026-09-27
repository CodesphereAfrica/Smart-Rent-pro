<?php
// modules/roles/edit.php — view or edit a role's permissions.
//
// Rules:
//   - System role templates (company_id IS NULL) are read-only. User must Clone to make changes.
//   - Custom roles (company_id = caller's company_id) are fully editable.
//   - Every save writes to audit_logs.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('users.manage');
$cid  = (int)$USER['company_id'];

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    flash_error(__('Invalid role id.'));
    redirect('modules/roles/index.php');
}

// Look up the role with a clear distinction between "doesn't exist" and "not visible".
$role = db_one(
    'SELECT * FROM roles WHERE id = :id LIMIT 1',
    [':id' => $id]
);
if (!$role) {
    flash_error(__('Role #:id does not exist.', [':id' => (string)$id]));
    redirect('modules/roles/index.php');
}

$roleCompanyId = $role['company_id'] !== null ? (int)$role['company_id'] : null;
$isSystemTemplate = (int)$role['is_system_role'] === 1 || $roleCompanyId === null;
$isMyCompanyRole  = $roleCompanyId !== null && $roleCompanyId === $cid;

// Guard: cannot view a role belonging to another company.
if (!$isSystemTemplate && !$isMyCompanyRole) {
    http_response_code(404);
    flash_error(__('Role not found.'));
    redirect('modules/roles/index.php');
}

$isEditable = !$isSystemTemplate && $isMyCompanyRole;

// ---------------------------------------------------------------------------
// SAVE (only if editable).
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if (!$isEditable) {
        flash_error(__('System role templates are read-only. Use "Clone to Custom Role" first.'));
        redirect('modules/roles/edit.php?id=' . $id);
    }

    $permIds = array_values(array_filter(
        (array)($_POST['permissions'] ?? []),
        fn($v) => ctype_digit((string)$v)
    ));

    $newName   = sanitize_string($_POST['name'] ?? $role['name'], 80) ?: $role['name'];
    $newDesc   = sanitize_string($_POST['description'] ?? '', 255) ?: null;
    $newActive = !empty($_POST['is_active']) ? 1 : 0;

    // Uniqueness check (excluding self).
    $dup = db_one(
        'SELECT id FROM roles WHERE name = :n AND id <> :id AND (company_id IS NULL OR company_id = :c) LIMIT 1',
        [':n' => $newName, ':id' => $id, ':c' => $cid]
    );
    if ($dup) {
        flash_error(__('Another role is already named ":name".', [':name' => $newName]));
        redirect('modules/roles/edit.php?id=' . $id);
    }

    $before = [
        'name'        => $role['name'],
        'description' => $role['description'],
        'is_active'   => (int)$role['is_active'],
        'permissions' => array_map('intval', array_column(
            db_all('SELECT permission_id FROM role_permissions WHERE role_id = :r', [':r' => $id]),
            'permission_id'
        )),
    ];

    db()->beginTransaction();
    try {
        db_update('roles', [
            'name'        => $newName,
            'description' => $newDesc,
            'is_active'   => $newActive,
        ], 'id = :id AND company_id = :c', [':id' => $id, ':c' => $cid]);

        db_query('DELETE FROM role_permissions WHERE role_id = :r', [':r' => $id]);
        foreach ($permIds as $pid) {
            db_insert('role_permissions', ['role_id' => $id, 'permission_id' => (int)$pid]);
        }

        log_audit('roles', 'update_permissions', 'roles', $id, $before, [
            'name'        => $newName,
            'description' => $newDesc,
            'is_active'   => $newActive,
            'permissions' => array_map('intval', $permIds),
        ]);
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        error_log('[roles.edit] ' . $e->getMessage());
        flash_error(__('Could not save permissions.'));
        redirect('modules/roles/edit.php?id=' . $id);
    }

    flash_success(__('Permissions saved for ":name".', [':name' => $newName]));
    redirect('modules/roles/edit.php?id=' . $id);
}

// ---------------------------------------------------------------------------
// LOAD matrix.
// ---------------------------------------------------------------------------
$permissions = db_all('SELECT * FROM permissions ORDER BY module, action');
$grouped = [];
foreach ($permissions as $p) {
    $grouped[$p['module']][] = $p;
}

$granted = array_map('intval', array_column(
    db_all('SELECT permission_id FROM role_permissions WHERE role_id = :r', [':r' => $id]),
    'permission_id'
));
$grantedLookup = array_flip($granted);
$totalPerms   = count($permissions);
$grantedCount = count($granted);

$userCount = (int)(db_one('SELECT COUNT(*) AS c FROM users WHERE role_id = :r', [':r' => $id])['c'] ?? 0);

$PAGE_TITLE = __('Role') . ' · ' . $role['name'];
require __DIR__ . '/../../includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb small">
  <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/roles/index.php"><?= e(__('Roles')) ?></a></li>
  <li class="breadcrumb-item active"><?= e($role['name']) ?></li>
</ol></nav>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
  <div>
    <h1 class="h4 mb-1">
      <?= e($role['name']) ?>
      <?php if ($isSystemTemplate): ?>
        <span class="badge bg-secondary ms-2"><?= e(__('Template — read-only')) ?></span>
      <?php else: ?>
        <span class="badge bg-primary ms-2"><?= e(__('Custom — editable')) ?></span>
      <?php endif; ?>
      <?php if (!$role['is_active']): ?>
        <span class="badge bg-warning text-dark ms-1"><?= e(__('Inactive')) ?></span>
      <?php endif; ?>
    </h1>
    <div class="text-muted small">
      <?php if (!empty($role['description'])): ?>
        <?= e($role['description']) ?> ·
      <?php endif; ?>
      <strong><?= $userCount ?></strong> <?= e(__('user(s) assigned')) ?> ·
      <span id="permCounterTop"><?= $grantedCount ?></span> / <?= $totalPerms ?> <?= e(__('permissions granted')) ?>
    </div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (!$isEditable): ?>
      <a class="btn btn-primary" href="<?= APP_URL ?>/modules/roles/create.php?clone_from=<?= (int)$id ?>">
        <i data-lucide="copy"></i> <?= e(__('Clone to Custom Role')) ?>
      </a>
    <?php endif; ?>
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/roles/index.php">
      <i data-lucide="arrow-left"></i> <?= e(__('Back')) ?>
    </a>
  </div>
</div>

<?php if (!$isEditable): ?>
  <div class="alert alert-info small d-flex align-items-center gap-2">
    <i data-lucide="info" style="width:16px;height:16px;flex:0 0 16px"></i>
    <div>
      <?= e(__('This is a system role template. It ships with the platform and cannot be modified directly.')) ?>
      <a class="alert-link" href="<?= APP_URL ?>/modules/roles/create.php?clone_from=<?= (int)$id ?>">
        <?= e(__('Clone it to create a fully-editable custom role.')) ?>
      </a>
    </div>
  </div>
<?php endif; ?>

<form method="post" id="roleForm" data-role-id="<?= (int)$id ?>" data-editable="<?= $isEditable ? '1' : '0' ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$id ?>">

  <?php if ($isEditable): ?>
  <div class="srp-card p-4 mb-3">
    <h2 class="h6 mb-3"><?= e(__('Role Details')) ?></h2>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label small"><?= e(__('Name')) ?> *</label>
        <input name="name" class="form-control form-control-sm" value="<?= e($role['name']) ?>" required>
      </div>
      <div class="col-md-6">
        <label class="form-label small"><?= e(__('Description')) ?></label>
        <input name="description" class="form-control form-control-sm" value="<?= e((string)$role['description']) ?>">
      </div>
      <div class="col-12">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive"
                 <?= $role['is_active'] ? 'checked' : '' ?>>
          <label class="form-check-label" for="isActive"><?= e(__('Active')) ?></label>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="srp-card p-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <h2 class="h6 mb-0">
        <?= e(__('Permissions')) ?>
        <span class="text-muted fw-normal small ms-2">
          <span id="permCounter"><?= $grantedCount ?></span> / <?= $totalPerms ?> <?= e(__('selected')) ?>
        </span>
      </h2>
      <?php if ($isEditable): ?>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-sm btn-light" id="grantAll">
            <i data-lucide="check-check"></i> <?= e(__('Grant all')) ?>
          </button>
          <button type="button" class="btn btn-sm btn-light" id="clearAll">
            <i data-lucide="x"></i> <?= e(__('Clear all')) ?>
          </button>
        </div>
      <?php endif; ?>
    </div>

    <div class="row g-3" id="permGrid">
      <?php foreach ($grouped as $module => $perms):
        $modGranted = 0;
        foreach ($perms as $p) if (isset($grantedLookup[(int)$p['id']])) $modGranted++;
      ?>
        <div class="col-md-6 col-lg-4">
          <div class="border rounded p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div>
                <div class="fw-semibold text-capitalize"><?= e(str_replace('_', ' ', $module)) ?></div>
                <div class="text-muted small">
                  <span class="modCount" data-module="<?= e($module) ?>"><?= $modGranted ?></span> / <?= count($perms) ?>
                </div>
              </div>
              <?php if ($isEditable): ?>
                <div class="btn-group btn-group-sm">
                  <button type="button" class="btn btn-light modToggle" data-module="<?= e($module) ?>" data-mode="all" title="<?= e(__('Select all')) ?>">
                    <i data-lucide="check" style="width:14px;height:14px"></i>
                  </button>
                  <button type="button" class="btn btn-light modToggle" data-module="<?= e($module) ?>" data-mode="none" title="<?= e(__('Clear')) ?>">
                    <i data-lucide="x" style="width:14px;height:14px"></i>
                  </button>
                </div>
              <?php endif; ?>
            </div>

            <?php foreach ($perms as $p): ?>
              <div class="form-check">
                <input class="form-check-input permCheck" type="checkbox" name="permissions[]"
                       value="<?= (int)$p['id'] ?>"
                       id="p_<?= (int)$p['id'] ?>"
                       data-module="<?= e($module) ?>"
                       <?= isset($grantedLookup[(int)$p['id']]) ? 'checked' : '' ?>
                       <?= $isEditable ? '' : 'disabled' ?>>
                <label class="form-check-label small" for="p_<?= (int)$p['id'] ?>">
                  <span class="fw-semibold text-capitalize"><?= e($p['action']) ?></span>
                  <?php if (!empty($p['description'])): ?>
                    <span class="text-muted"> — <?= e($p['description']) ?></span>
                  <?php endif; ?>
                </label>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if ($isEditable): ?>
    <div class="d-flex justify-content-end gap-2 mt-4">
      <a class="btn btn-light" href="<?= APP_URL ?>/modules/roles/index.php"><?= e(__('Cancel')) ?></a>
      <button class="btn btn-primary">
        <i data-lucide="save"></i> <?= e(__('Save Permissions')) ?>
      </button>
    </div>
  <?php endif; ?>
</form>

<?php if ($isEditable): ?>
<script>
(function () {
  'use strict';
  const grid = document.getElementById('permGrid');
  const topCounter = document.getElementById('permCounterTop');
  const counter = document.getElementById('permCounter');
  if (!grid) return;

  const all = () => grid.querySelectorAll('.permCheck');

  function updateCounters() {
    const boxes = all();
    let total = 0;
    boxes.forEach(c => { if (c.checked) total++; });
    if (counter) counter.textContent = total;
    if (topCounter) topCounter.textContent = total;

    grid.querySelectorAll('.modCount').forEach(el => {
      const module = el.dataset.module;
      const mods = grid.querySelectorAll('.permCheck[data-module="' + module + '"]');
      let mc = 0;
      mods.forEach(c => { if (c.checked) mc++; });
      el.textContent = mc;
    });
  }

  grid.querySelectorAll('.modToggle').forEach(btn => {
    btn.addEventListener('click', () => {
      const module = btn.dataset.module;
      const mode = btn.dataset.mode;
      grid.querySelectorAll('.permCheck[data-module="' + module + '"]').forEach(c => {
        if (!c.disabled) c.checked = (mode === 'all');
      });
      updateCounters();
    });
  });

  const ga = document.getElementById('grantAll');
  if (ga) ga.addEventListener('click', () => { all().forEach(c => { if (!c.disabled) c.checked = true; }); updateCounters(); });
  const ca = document.getElementById('clearAll');
  if (ca) ca.addEventListener('click', () => { all().forEach(c => { if (!c.disabled) c.checked = false; }); updateCounters(); });

  grid.addEventListener('change', e => {
    if (e.target.classList.contains('permCheck')) updateCounters();
  });
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>