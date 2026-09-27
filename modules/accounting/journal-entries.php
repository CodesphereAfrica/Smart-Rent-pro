<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('accounting.view');

$entries = db_all(
    'SELECT je.id, je.entry_number, je.entry_date, je.description, je.reference_type, je.reference_id,
            (SELECT COALESCE(SUM(debit),0) FROM journal_entry_lines WHERE journal_entry_id = je.id) AS total_debit,
            (SELECT COALESCE(SUM(credit),0) FROM journal_entry_lines WHERE journal_entry_id = je.id) AS total_credit
       FROM journal_entries je WHERE je.company_id = :c
      ORDER BY je.entry_date DESC, je.id DESC LIMIT 200',
    [':c' => (int)$USER['company_id']]
);

$PAGE_TITLE = __('Journal Entries');
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0"><?= e(__('Journal Entries')) ?></h1>
</div>
<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr>
      <th><?= e(__('Entry #')) ?></th><th><?= e(__('Date')) ?></th><th><?= e(__('Description')) ?></th>
      <th><?= e(__('Reference')) ?></th><th class="text-end"><?= e(__('Debit')) ?></th><th class="text-end"><?= e(__('Credit')) ?></th><th></th>
    </tr></thead>
    <tbody>
      <?php if (!$entries): ?><tr><td colspan="7" class="text-center text-muted py-4"><?= e(__('No entries yet.')) ?></td></tr>
      <?php else: foreach ($entries as $e): ?>
        <tr>
          <td><?= e($e['entry_number']) ?></td>
          <td><?= e($e['entry_date']) ?></td>
          <td><?= e($e['description'] ?? '—') ?></td>
          <td><?= e(($e['reference_type'] ?? '') . ($e['reference_id'] ? ' #' . $e['reference_id'] : '')) ?></td>
          <td class="text-end"><?= e(money($e['total_debit'])) ?></td>
          <td class="text-end"><?= e(money($e['total_credit'])) ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-light" href="<?= APP_URL ?>/modules/accounting/general-ledger.php?entry_id=<?= (int)$e['id'] ?>"><i data-lucide="eye"></i></a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>