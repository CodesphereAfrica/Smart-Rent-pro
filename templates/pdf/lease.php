<?php /** Lease agreement PDF **/ ?>
<!doctype html><html><head><meta charset="utf-8">
<style>
  body { font-family: serif; color: #0f172a; font-size: 12px; }
  h1 { font-size: 18px; text-align: center; }
  h2 { font-size: 13px; margin-top: 18px; }
  table { width: 100%; border-collapse: collapse; margin: 10px 0; }
  td, th { padding: 6px; border: 1px solid #cbd5e1; vertical-align: top; }
  .signature { margin-top: 60px; display: flex; }
</style></head><body>
<h1>RESIDENTIAL LEASE AGREEMENT</h1>
<p style="text-align:center">Lease No. <strong><?= htmlspecialchars($lease['lease_number'], ENT_QUOTES, 'UTF-8') ?></strong></p>

<h2>Parties</h2>
<table>
  <tr><td style="width:30%"><strong>Landlord</strong></td><td><?= htmlspecialchars($company['name'] ?? '', ENT_QUOTES, 'UTF-8') ?><br><?= htmlspecialchars($company['address'] ?? '', ENT_QUOTES, 'UTF-8') ?></td></tr>
  <tr><td><strong>Tenant</strong></td><td><?= htmlspecialchars($tenant['first_name'] . ' ' . $tenant['last_name'], ENT_QUOTES, 'UTF-8') ?><br><?= htmlspecialchars($tenant['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?></td></tr>
</table>

<h2>Premises</h2>
<table>
  <tr><td style="width:30%"><strong>Unit</strong></td><td><?= htmlspecialchars($unit['unit_number'], ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($property['name'], ENT_QUOTES, 'UTF-8') ?></td></tr>
  <tr><td><strong>Address</strong></td><td><?= htmlspecialchars($property['address'] . ', ' . $property['city'], ENT_QUOTES, 'UTF-8') ?></td></tr>
</table>

<h2>Term</h2>
<p>This lease begins on <strong><?= htmlspecialchars($lease['start_date'], ENT_QUOTES, 'UTF-8') ?></strong> and ends on <strong><?= htmlspecialchars($lease['end_date'], ENT_QUOTES, 'UTF-8') ?></strong>.</p>

<h2>Rent</h2>
<p>Monthly rent is <strong><?= number_format((float)$lease['monthly_rent'], 2) ?></strong>, due on the 1st of each month with a grace period of <?= (int)$lease['grace_period_days'] ?> days.</p>

<h2>Security Deposit</h2>
<p>Deposit of <strong><?= number_format((float)$lease['security_deposit'], 2) ?></strong> is held by the Landlord against damage or unpaid rent.</p>

<?php if ($lease['special_clauses']): ?>
<h2>Special Clauses</h2>
<p><?= nl2br(htmlspecialchars($lease['special_clauses'], ENT_QUOTES, 'UTF-8')) ?></p>
<?php endif; ?>

<div class="signature">
  <table>
    <tr>
      <td style="width:50%;padding-top:40px">__________________________<br>Landlord / Manager</td>
      <td style="width:50%;padding-top:40px">__________________________<br>Tenant</td>
    </tr>
  </table>
</div>
</body></html>