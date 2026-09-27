<?php
// modules/dashboard/index.php — routes to the appropriate dashboard by role. Not a Router
// in the MVC sense: it's a single-page dispatcher, which is allowed.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_login();

switch ($USER['role_name']) {
    case 'Super Admin':
        require __DIR__ . '/super-admin.php';       return;
    case 'Property Owner':
        header('Location: ' . APP_URL . '/modules/portal/owner/index.php'); exit;
    case 'Tenant':
        header('Location: ' . APP_URL . '/modules/portal/tenant/index.php'); exit;
    case 'Accountant':
        require __DIR__ . '/accountant.php';        return;
    case 'Property Manager':
    case 'Maintenance Manager':
    case 'Receptionist':
    case 'Collection Officer':
    default:
        require __DIR__ . '/manager.php';           return;
}