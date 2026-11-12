<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

/**
 * Thin PDO singleton with helper conveniences.
 * All queries throughout the app use prepared statements (spec §2, §87).
 * Drivers: sqlite (default) and mysql.
 */
final class Database
{
    private static ?PDO $pdo = null;
    private static ?string $driver = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $cfg = require BASE_PATH . '/config/database.php';
            self::$driver = (string) $cfg['driver'];
            if (self::$driver === 'sqlite') {
                $path = (string) $cfg['database'];
                $isAbsolute = str_contains($path, ':') || str_starts_with($path, '/') || str_starts_with($path, '\\');
                if ($path !== '' && $path !== ':memory:' && !$isAbsolute) {
                    $path = BASE_PATH . '/' . ltrim($path, '/\\');
                }
                $dir = dirname($path);
                if (!is_dir($dir)) {
                    @mkdir($dir, 0775, true);
                }
                self::$pdo = new PDO('sqlite:' . $path, null, null, $cfg['options']);
                // Concurrency + integrity pragmas for a small institutional app.
                self::$pdo->exec('PRAGMA journal_mode = WAL');
                self::$pdo->exec('PRAGMA synchronous = NORMAL');
                self::$pdo->exec('PRAGMA foreign_keys = ON');
                self::$pdo->exec('PRAGMA busy_timeout = 5000');
            } else {
                $dsn = sprintf(
                    '%s:host=%s;port=%d;dbname=%s;charset=%s',
                    self::$driver, $cfg['host'], (int) $cfg['port'], $cfg['database'], $cfg['charset']
                );
                self::$pdo = new PDO($dsn, $cfg['username'], $cfg['password'], $cfg['options']);
            }
        }
        return self::$pdo;
    }

    public static function driver(): string
    {
        self::pdo();
        return self::$driver ?? 'sqlite';
    }

    public static function isSqlite(): bool
    {
        return self::driver() === 'sqlite';
    }

    /** Portable "insert if new": INSERT OR IGNORE (sqlite) vs INSERT IGNORE (mysql). */
    public static function insertIgnore(string $table): string
    {
        return self::isSqlite() ? "INSERT OR IGNORE INTO $table" : "INSERT IGNORE INTO $table";
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function scalar(string $sql, array $params = []): mixed
    {
        $v = self::run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    /**
     * Insert-or-update on a unique key, portable across MySQL and SQLite.
     * $updates: column => new value (use the column name itself for "keep the
     * excluded value", e.g. ['score' => 'score'] means score = excluded.score).
     */
    public static function upsert(string $table, array $data, array $uniqueCols, array $updates): int
    {
        $cols = array_keys($data);
        $ph   = implode(', ', array_fill(0, count($cols), '?'));
        $setParts = [];
        foreach ($updates as $col => $val) {
            if (is_string($val) && $val === $col) {
                $setParts[] = self::isSqlite()
                    ? "\"$col\" = excluded.\"$col\""
                    : "`$col` = VALUES(`$col`)";
            } else {
                $setParts[] = self::quoteCol($col) . ' = ?';
                $extraParams[] = $val;
            }
        }
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s) ON CONFLICT (%s) DO UPDATE SET %s',
            self::quoteIdent($table),
            implode(', ', array_map([self::class, 'quoteCol'], $cols)),
            $ph,
            implode(', ', array_map([self::class, 'quoteCol'], $uniqueCols)),
            implode(', ', $setParts)
        );
        $params = array_merge(array_values($data), $extraParams ?? []);
        self::run($sql, $params);
        $id = self::pdo()->lastInsertId();
        return (int) ($id !== '' && $id !== '0' ? $id : 0);
    }

    private static function quoteIdent(string $t): string
    {
        return self::isSqlite() ? "\"$t\"" : "`$t`";
    }

    private static function quoteCol(string $c): string
    {
        return self::isSqlite() ? "\"$c\"" : "`$c`";
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        if (self::isSqlite()) {
            $sql = sprintf('INSERT INTO "%s" ("%s") VALUES (%s)', $table, implode('", "', $cols),
                implode(', ', array_fill(0, count($cols), '?')));
        } else {
            $sql = sprintf('INSERT INTO `%s` (`%s`) VALUES (%s)', $table, implode('`, `', $cols),
                implode(', ', array_fill(0, count($cols), '?')));
        }
        self::run($sql, array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $q   = self::isSqlite() ? '"' : '`';
        $set = implode(', ', array_map(static fn ($c) => "$q$c$q = ?", array_keys($data)));
        $sql = sprintf('UPDATE %s SET %s WHERE %s', self::quoteIdent($table), $set, $where);
        return self::run($sql, [...array_values($data), ...$whereParams])->rowCount();
    }

    public static function begin(): void  { self::pdo()->beginTransaction(); }
    public static function commit(): void { if (self::pdo()->inTransaction()) { self::pdo()->commit(); } }

    public static function rollback(): void
    {
        if (self::pdo()->inTransaction()) {
            self::pdo()->rollBack();
        }
    }

    /**
     * Run a callable inside a transaction; roll back on any throwable.
     * Nesting-aware: only the outermost call begins/commits the real transaction
     * (PDO has no savepoint support here), inner calls become part of it.
     */
    private static int $txDepth = 0;

    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        $outer = self::$txDepth === 0;
        if ($outer) {
            $pdo->beginTransaction();
        }
        self::$txDepth++;
        try {
            $result = $fn();
            self::$txDepth--;
            if ($outer) {
                $pdo->commit();
            }
            return $result;
        } catch (\Throwable $e) {
            self::$txDepth--;
            if ($outer) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}

