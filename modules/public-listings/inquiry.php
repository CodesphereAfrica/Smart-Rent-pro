<?php
// modules/public-listings/inquiry.php — public inquiry handler: creates a `leads` row.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
require_csrf();

$listingId = (int)($_POST['listing_id'] ?? 0);
$unitId    = (int)($_POST['unit_id'] ?? 0);
$name      = sanitize_string($_POST['name'] ?? '', 150);
$phone     = sanitize_phone($_POST['phone'] ?? '');
$email     = sanitize_email($_POST['email'] ?? '');
$message   = sanitize_string($_POST['message'] ?? '', 1000);

$l = db_one('SELECT id, company_id, unit_id FROM listings WHERE id = :i AND is_published = 1', [':i'=>$listingId]);
if (!$l) { http_response_code(400); exit('Invalid listing'); }

db_insert('leads', [
    'company_id' => (int)$l['company_id'],
    'listing_id' => (int)$l['id'],
    'unit_id' => (int)$l['unit_id'],
    'name' => $name ?: 'Anonymous',
    'phone' => $phone,
    'email' => $email,
    'message' => $message,
    'status' => 'new',
]);

flash_success(__('Thank you — your inquiry has been sent. We will contact you soon.'));
header('Location: ' . APP_URL . '/modules/public-listings/view.php?slug=' . urlencode((string)($_POST['slug'] ?? '')));
exit;