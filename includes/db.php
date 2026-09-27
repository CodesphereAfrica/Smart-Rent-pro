<?php
// includes/db.php — PDO singleton. Pure infrastructure, no business logic.
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => true,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
    } catch (PDOException $e) {
        error_log('[DB] ' . $e->getMessage());
        http_response_code(500);
        exit(APP_DEBUG ? 'DB error: ' . $e->getMessage() : 'Database unavailable.');
    }
    return $pdo;
}

/**
 * Convenience wrappers — always prepared, never string-concatenated.
 */
function db_query(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function db_one(string $sql, array $params = []): ?array
{
    $row = db_query($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function db_all(string $sql, array $params = []): array
{
    return db_query($sql, $params)->fetchAll();
}

function db_insert(string $table, array $data): int
{
    $cols = array_keys($data);
    $ph   = array_map(fn($c) => ':' . $c, $cols);
    $sql  = sprintf('INSERT INTO `%s` (`%s`) VALUES (%s)',
        $table, implode('`,`', $cols), implode(',', $ph));
    $params = [];
    foreach ($data as $k => $v) { $params[':' . $k] = $v; }
    db_query($sql, $params);
    return (int) db()->lastInsertId();
}

function db_update(string $table, array $data, string $where, array $whereParams = []): int
{
    $set = [];
    $params = [];
    foreach ($data as $k => $v) {
        $set[] = "`{$k}` = :s_{$k}";
        $params[':s_' . $k] = $v;
    }
    foreach ($whereParams as $k => $v) {
        $params[$k] = $v;
    }
    $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $set), $where);
    return db_query($sql, $params)->rowCount();
}