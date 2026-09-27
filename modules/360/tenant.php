<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('tenants.view');

$id = (int)($_GET['id'] ?? 0);
// Tenant 360 is identical in shape to tenants/view.php from Part 2; redirect there to keep URL stability.
header('Location: ' . APP_URL . '/modules/tenants/view.php?id=' . $id);
exit;