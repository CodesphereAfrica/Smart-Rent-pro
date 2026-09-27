<?php
// modules/public-listings/index.php — public-facing listing grid (no login required).
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$q         = trim((string)($_GET['q'] ?? ''));
$type      = trim((string)($_GET['type'] ?? ''));
$minRent   = (float)($_GET['min'] ?? 0);
$maxRent   = (float)($_GET['max'] ?? 0);

$where = ['l.is_published = 1', 'u.status = "available"'];
$params = [];
if ($q !== '') { $where[] = '(l.title LIKE :q OR l.description LIKE :q OR p.name LIKE :q OR p.city LIKE :q)'; $params[':q'] = '%' . $q . '%'; }
if ($type !== '') { $where[] = 'p.property_type = :t'; $params[':t'] = $type; }
if ($minRent > 0) { $where[] = 'l.rent >= :min'; $params[':min'] = $minRent; }
if ($maxRent > 0) { $where[] = 'l.rent <= :max'; $params[':max'] = $maxRent; }

$listings = db_all(
    'SELECT l.id, l.slug, l.title, l.rent, l.deposit, l.photos, l.availability_date,
            u.unit_number, u.bedrooms, u.bathrooms, u.size_sqm,
            p.name AS property_name, p.city, p.property_type
       FROM listings l
       JOIN units      u ON u.id = l.unit_id
       JOIN properties p ON p.id = u.property_id
      WHERE ' . implode(' AND ', $where) . '
      ORDER BY l.published_at DESC, l.id DESC
      LIMIT 60',
    $params
);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(__('Available Properties')) ?> · <?= e(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/custom.css">
<script src="https://unpkg.com/lucide@0.383.0/dist/umd/lucide.js"></script>
</head>
<body class="srp-public">
  <header class="srp-public-header">
    <div class="container d-flex justify-content-between align-items-center">
      <a class="srp-brand" href="<?= APP_URL ?>/modules/public-listings/index.php">
        <i data-lucide="building-2"></i><span><?= e(APP_NAME) ?></span>
      </a>
      <a class="btn btn-outline-primary btn-sm" href="<?= APP_URL ?>/modules/auth/login.php"><?= e(__('Sign in')) ?></a>
    </div>
  </header>

  <section class="srp-public-hero">
    <div class="container">
      <h1><?= e(__('Find your next home.')) ?></h1>
      <p class="text-muted"><?= e(__('Browse units available now.')) ?></p>
      <form class="row g-2" method="get">
        <div class="col-md-5"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('Location, property or keyword')) ?>"></div>
        <div class="col-md-3">
          <select name="type" class="form-select">
            <option value=""><?= e(__('Any type')) ?></option>
            <?php foreach (['apartment','house','villa','office','retail','warehouse','mixed_use','student_housing'] as $t): ?>
              <option value="<?= e($t) ?>" <?= $type===$t?'selected':'' ?>><?= e(ucfirst(str_replace('_',' ',$t))) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2"><input class="form-control" type="number" name="max" value="<?= $maxRent?:'' ?>" placeholder="<?= e(__('Max rent')) ?>"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100"><?= e(__('Search')) ?></button></div>
      </form>
    </div>
  </section>

  <section class="container py-4">
    <?php if (!$listings): ?>
      <div class="alert alert-info"><?= e(__('No listings match your search.')) ?></div>
    <?php else: ?>
      <div class="row g-3">
        <?php foreach ($listings as $l):
          $photos = json_decode((string)$l['photos'], true) ?: [];
          $cover  = $photos[0] ?? null;
        ?>
          <div class="col-md-6 col-lg-4">
            <div class="srp-listing-card">
              <?php if ($cover): ?>
                <img class="srp-listing-cover" src="<?= e(UPLOAD_URL . '/' . $cover) ?>" alt="">
              <?php else: ?>
                <div class="srp-listing-cover srp-listing-cover-empty"><i data-lucide="image"></i></div>
              <?php endif; ?>
              <div class="p-3">
                <div class="fw-semibold"><?= e($l['title']) ?></div>
                <div class="text-muted small mb-2"><?= e($l['property_name']) ?> · <?= e($l['city'] ?? '') ?></div>
                <div class="d-flex justify-content-between mb-2 small">
                  <span><?= (int)$l['bedrooms'] ?> <?= e(__('beds')) ?> · <?= (int)$l['bathrooms'] ?> <?= e(__('baths')) ?></span>
                  <span><?= e((string)$l['size_sqm']) ?> m²</span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                  <div class="fw-bold"><?= e(money($l['rent'])) ?></div>
                  <a class="btn btn-sm btn-primary" href="<?= APP_URL ?>/modules/public-listings/view.php?slug=<?= e($l['slug']) ?>"><?= e(__('View')) ?></a>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

<script>lucide.createIcons();</script>
</body></html>