<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

function cron_log(string $script, string $message): void
{
    $line = '[' . date('Y-m-d H:i:s') . "] [$script] $message\n";
    @file_put_contents(SRP_ROOT . '/storage/logs/cron.log', $line, FILE_APPEND);
}

function cron_start(string $script): void { cron_log($script, 'started'); }
function cron_done(string $script, string $summary): void { cron_log($script, "done — $summary"); }

// Iterate companies so all per-company logic is scoped
function cron_companies(): array
{
    return db_all('SELECT id, name FROM companies WHERE is_active = 1');
}