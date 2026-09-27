<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('units.view');

$id = (int)($_GET['id'] ?? 0);
// Unit 360 is identical to units/view.php from Part 1; redirect there.
header('Location: ' . APP_URL . '/modules/units/view.php?id=' . $id);
exit;