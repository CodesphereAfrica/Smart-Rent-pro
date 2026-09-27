<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_login();
// Canonical Today screen is the manager dashboard; forward for URL stability.
header('Location: ' . APP_URL . '/modules/dashboard/manager.php');
exit;