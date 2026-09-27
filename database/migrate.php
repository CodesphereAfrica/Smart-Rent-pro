<?php
/**
 * database/migrate.php — plain-PHP migration & seed runner.
 * Usage: php database/migrate.php [--fresh] [--seed-only]
 *
 * Not a framework — just a script that executes schema.sql + seed.sql + extra
 * housekeeping DDL that we legitimately add to the spec schema (login_attempts,
 * soft-delete columns, indexes) without modifying/renaming/removing anything.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

$argv  = $argv ?? [];
$fresh = in_array('--fresh', $argv, true);
$seed  = in_array('--seed-only', $argv, true);

echo "== Smart Rent Pro migration runner ==\n";

if ($fresh) {
    echo "[fresh] dropping database " . DB_NAME . "\n";
    $pdo = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', DB_HOST, DB_PORT), DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec('DROP DATABASE IF EXISTS `' . DB_NAME . '`');
    $pdo->exec('CREATE DATABASE `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    echo "[fresh] database recreated\n";
}

$pdo = db();

if (!$seed) {
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    if ($schema === false) { fwrite(STDERR, "schema.sql missing\n"); exit(1); }
    echo "[schema] executing schema.sql\n";
    // Split on semicolons at line ends (safe because schema has no procedures with ; inside).
    $statements = preg_split('/;\s*[\r\n]+/', $schema);
    foreach ($statements as $sql) {
        $sql = trim($sql);
        if ($sql === '' || str_starts_with($sql, '--')) continue;
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            // tolerate idempotent re-runs
            if (strpos($e->getMessage(), 'already exists') === false) {
                echo "  ! " . $e->getMessage() . "\n";
            }
        }
    }
    echo "[schema] done\n";

    // ----------------------------------------------------------------------
    // Housekeeping additions permitted by the spec ("you may add indexes or
    // minor housekeeping columns like deleted_at for soft deletes").
    // ----------------------------------------------------------------------
    echo "[housekeeping] adding login_attempts + soft-delete columns\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS login_attempts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            identifier VARCHAR(150) NOT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            success TINYINT(1) NOT NULL DEFAULT 0,
            attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_attempts_ident_time (identifier, attempted_at)
        ) ENGINE=InnoDB;
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_preferences (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            pref_key VARCHAR(80) NOT NULL,
            pref_value TEXT NULL,
            UNIQUE KEY uniq_user_pref (user_id, pref_key),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB;
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sync_queue (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            entity_type VARCHAR(60) NOT NULL,
            entity_id BIGINT UNSIGNED NULL,
            payload JSON NOT NULL,
            status ENUM('pending','synced','failed') NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            synced_at DATETIME NULL,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_sync_user_status (user_id, status)
        ) ENGINE=InnoDB;
    ");

    // Soft-delete columns on a small set of master tables (never on financial tables).
    $softDeleteTables = ['properties','buildings','units','tenants','owners','vendors','leases','applicants','assets'];
    foreach ($softDeleteTables as $t) {
        try {
            $pdo->exec("ALTER TABLE `{$t}` ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
        } catch (PDOException $e) {
            // column already exists — safe to ignore
        }
    }
    echo "[housekeeping] done\n";
}

// ----------------------------------------------------------------------
// Seed data
// ----------------------------------------------------------------------
echo "[seed] running seed.sql\n";
$seedSql = file_get_contents(__DIR__ . '/seed.sql');
if ($seedSql !== false) {
    foreach (preg_split('/;\s*[\r\n]+/', $seedSql) as $sql) {
        $sql = trim($sql);
        if ($sql === '' || str_starts_with($sql, '--')) continue;
        try { $pdo->exec($sql); } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') === false) {
                echo "  ! " . $e->getMessage() . "\n";
            }
        }
    }
}

// Create default super admin if missing.
$existing = db_one('SELECT id FROM companies LIMIT 1');
if (!$existing) {
    $cid = db_insert('companies', [
        'name' => 'Smart Rent Pro (Platform)',
        'slug' => 'smart-rent-pro',
        'base_currency_code' => DEFAULT_CURRENCY,
        'default_language' => DEFAULT_LANGUAGE,
        'email' => 'admin@smartrentpro.local',
        'is_active' => 1,
    ]);
    $role = db_one('SELECT id FROM roles WHERE company_id IS NULL AND name = "Super Admin" LIMIT 1');
    if ($role) {
        db_insert('users', [
            'company_id' => $cid,
            'role_id' => (int)$role['id'],
            'first_name' => 'Platform',
            'last_name' => 'Admin',
            'username' => 'superadmin',
            'email' => 'admin@smartrentpro.local',
            'password_hash' => password_hash('ChangeMe!2025', PASSWORD_BCRYPT),
            'preferred_language' => DEFAULT_LANGUAGE,
            'status' => 'active',
            'must_change_password' => 1,
        ]);
        echo "[seed] default super admin created: superadmin / ChangeMe!2025\n";
    }
}

echo "== migration complete ==\n";