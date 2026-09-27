<?php
// modules/public-listings/view.php — single listing page.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$l = db_one(
    'SELECT l.*, u.unit_number, u.bedrooms, u.bathrooms, u.size_sqm, u.furnished_status,
            p.name AS property_name, p.address, p.city, p.country, p.property_type
       FROM listings l
       JOIN units u ON u.id = l.unit_id
       JOIN properties p ON p.id = u.property_id
      WHERE l.slug = :s AND l.is_published = 1 LIMIT 1',
    [':s' => $slug]
);
if (!$l) { http_response_code(404); exit('Listing not found'); }

// Increment view
db_query('UPDATE listings SET views_count = views_count + 1 WHERE id = :id', [':id' => (int)$l['id']]);

$photos = json_decode((string)$l['photos'], true) ?: [];
$amenities = json_decode((string)$l['amenities_json'], true) ?: [];
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($l['title']) ?> · <?= e(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/custom.css">
<script src="https://unpkg.com/lucide@0.383.0/dist/umd/lucide.js"></script>
</head><body class="srp-public">
<header class="srp-public-header"><div class="container d-flex justify-content-between align-items-center">
  <a class="srp-brand" href="<?= APP_URL ?>/modules/public-listings/index.php"><i data-lucide="building-2"></i><span><?= e(APP_NAME) ?></span></a>
  <a class="btn btn-outline-primary btn-sm" href="<?= APP_URL ?>/modules/auth/login.php"><?= e(__('Sign in')) ?></a>
</div></header>

<section class="container py-4">
  <h1 class="h3 mb-1"><?= e($l['title']) ?></h1>
  <div class="text-muted mb-3"><?= e($l['property_name']) ?> · <?= e(trim(($l['city'] ?? '') . ', ' . ($l['address'] ?? ''), ', ')) ?></div>

  <?php if ($photos): ?>
    <div class="row g-2 mb-3">
      <?php foreach (array_slice($photos, 0, 4) as $ph): ?>
        <div class="col-md-3"><img src="<?= e(UPLOAD_URL . '/' . $ph) ?>" class="w-100 rounded" style="height:150px;object-fit:cover"></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="srp-card p-4">
        <div class="row small g-2 mb-3">
          <div class="col-6"><span class="text-muted"><?= e(__('Unit')) ?>:</span> <?= e($l['unit_number']) ?></div>
          <div class="col-6"><span class="text-muted"><?= e(__('Type')) ?>:</span> <?= e(ucfirst(str_replace('_',' ',$l['property_type']))) ?></div>
          <div class="col-6"><span class="text-muted"><?= e(__('Beds')) ?>:</span> <?= (int)$l['bedrooms'] ?></div>
          <div class="col-6"><span class="text-muted"><?= e(__('Baths')) ?>:</span> <?= (int)$l['bathrooms'] ?></div>
          <div class="col-6"><span class="text-muted"><?= e(__('Size')) ?>:</span> <?= e((string)$l['size_sqm']) ?> m²</div>
          <div class="col-6"><span class="text-muted"><?= e(__('Furnishing')) ?>:</span> <?= e(ucfirst(str_replace('_',' ',$l['furnished_status']))) ?></div>
          <?php if ($l['availability_date']): ?><div class="col-6"><span class="text-muted"><?= e(__('Available from')) ?>:</span> <?= e($l['availability_date']) ?></div><?php endif; ?>
        </div>
        <p><?= nl2br(e($l['description'] ?? '')) ?></p>
        <?php if ($amenities): ?>
          <h2 class="h6 mt-3"><?= e(__('Amenities')) ?></h2>
          <div class="d-flex flex-wrap gap-2">
            <?php foreach ($amenities as $a): ?><span class="badge bg-light text-dark"><?= e($a) ?></span><?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="srp-card p-4">
        <div class="kpi-label"><?= e(__('Rent')) ?></div>
        <div class="fs-4 fw-bold mb-1"><?= e(money($l['rent'])) ?></div>
        <div class="text-muted small mb-3"><?= e(__('Deposit')) ?> <?= e(money($l['deposit'] ?? 0)) ?></div>

        <h2 class="h6"><?= e(__('Enquire')) ?></h2>
        <form method="post" action="<?= APP_URL ?>/modules/public-listings/inquiry.php">
          <?= csrf_field() ?>
          <input type="hidden" name="listing_id" value="<?= (int)$l['id'] ?>">
          <input type="hidden" name="unit_id" value="<?= (int)$l['unit_id'] ?>">
          <div class="mb-2"><input name="name" class="form-control" placeholder="<?= e(__('Your name')) ?>" required></div>
          <div class="mb-2"><input name="phone" class="form-control" placeholder="<?= e(__('Phone')) ?>" required></div>
          <div class="mb-2"><input name="email" type="email" class="form-control" placeholder="<?= e(__('Email (optional)')) ?>"></div>
          <div class="mb-2"><textarea name="message" class="form-control" rows="3" placeholder="<?= e(__('Message')) ?>"></textarea></div>
          <button class="btn btn-primary w-100"><?= e(__('Send Inquiry')) ?></button>
        </form>
      </div>
    </div>
  </div>
</section>
<script>lucide.createIcons();</script>
</body></html>