<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_permission('settings.manage');
header('Location: ' . APP_URL . '/modules/accounting/chart-of-accounts.php'); exit;