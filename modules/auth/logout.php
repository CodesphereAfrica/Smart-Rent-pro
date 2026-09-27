<?php
// modules/auth/logout.php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_name('SRPSESSID'); session_start(); }

$uid = $_SESSION['user_id'] ?? null;
if ($uid) {
    log_audit('auth', 'logout', 'users', (int)$uid);
    db_update('users', ['remember_token' => null], 'id = :id', [':id' => (int)$uid]);
}
setcookie('srp_remember', '', time() - 3600, '/');
session_unset(); session_destroy();
header('Location: ' . APP_URL . '/modules/auth/login.php');
exit;