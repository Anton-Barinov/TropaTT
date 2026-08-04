<?php
declare(strict_types=1);

namespace Api\System\Library\Database;

use PDO;
use Throwable;

/**
 * Driver-agnostic schema helpers.
 *
 * Vanilla MySQL does not support "IF NOT EXISTS" on CREATE INDEX or
 * ADD COLUMN (MariaDB, PostgreSQL, SQLite and SQL Server accept that
 * syntax), so guarded DDL must check the catalog first and only then run
 * the plain statement. The driver is auto-detected from the PDO connection
 * unless explicitly provided.
 */
final class IndexHelper
{
    /** @var array<string, true> Indexes already ensured this request: driver|table|index */
    private static array $ensuredIndexes = [];

    /** @var array<string, true> Columns already ensured this request: driver|table|column */
    private static array $ensuredColumns = [];

    /** SQLSTATE/MySQL codes that mean "already exists" under a concurrent create (normal race). */
    private const DUPLICATE_INDEX_CODE = '1061';
    private const DUPLICATE_COLUMN_CODE = '1060';

    public static function createIndexIfNotExists(PDO $pdo, string $table, string $index, string $columns, bool $unique = false, ?string $driver = null): void
    {
        try {
            $driver ??= (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $cacheKey = $driver . '|' . $table . '|' . $index;
            if (isset(self::$ensuredIndexes[$cacheKey]) || self::indexExists($pdo, $driver, $table, $index)) {
                self::$ensuredIndexes[$cacheKey] = true;
                return;
            }
            $prefix = $unique ? 'CREATE UNIQUE INDEX' : 'CREATE INDEX';
            $pdo->exec(sprintf('%s %s ON %s(%s)', $prefix, $index, $table, $columns));
            self::$ensuredIndexes[$cacheKey] = true;
        } catch (Throwable $e) {
            if (!self::isDuplicateCode($e)) {
                error_log('[IndexHelper::createIndexIfNotExists] ' . $e->getMessage());
            }
            // Ignore races/duplicate-index errors; the index is a best-effort optimization.
        }
    }

    public static function indexExists(PDO $pdo, string $driver, string $table, string $index): bool
    {
        try {
            if ($driver === 'sqlite') {
                $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = :table AND name = :index LIMIT 1");
                $stmt->execute(['table' => $table, 'index' => $index]);
                return (bool)$stmt->fetchColumn();
            }
            if ($driver === 'pgsql') {
                $stmt = $pdo->prepare('SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND tablename = :table AND indexname = :index LIMIT 1');
                $stmt->execute(['table' => $table, 'index' => $index]);
                return (bool)$stmt->fetchColumn();
            }
            if ($driver === 'sqlsrv') {
                $stmt = $pdo->prepare('SELECT TOP 1 i.name FROM sys.indexes i INNER JOIN sys.objects o ON o.object_id = i.object_id WHERE o.name = :table AND i.name = :index');
                $stmt->execute(['table' => $table, 'index' => $index]);
                return (bool)$stmt->fetchColumn();
            }
            $stmt = $pdo->prepare('SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index LIMIT 1');
            $stmt->execute(['table' => $table, 'index' => $index]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('[IndexHelper::indexExists] ' . $e->getMessage());
            return false;
        }
    }

    public static function addColumnIfNotExists(PDO $pdo, string $table, string $column, string $definition, ?string $driver = null): void
    {
        try {
            $driver ??= (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $cacheKey = $driver . '|' . $table . '|' . $column;
            if (isset(self::$ensuredColumns[$cacheKey]) || self::columnExists($pdo, $driver, $table, $column)) {
                self::$ensuredColumns[$cacheKey] = true;
                return;
            }
            $pdo->exec(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition));
            self::$ensuredColumns[$cacheKey] = true;
        } catch (Throwable $e) {
            if (!self::isDuplicateCode($e)) {
                error_log('[IndexHelper::addColumnIfNotExists] ' . $e->getMessage());
            }
            // Best effort: a missing optional column must not break the request.
        }
    }

    private static function isDuplicateCode(Throwable $e): bool
    {
        $code = (string)($e->getCode() ?? '');
        // MySQL returns 1061/1060; SQLSTATE 42S21 (column) / 42S11 (index) for some drivers.
        return $code === self::DUPLICATE_INDEX_CODE
            || $code === self::DUPLICATE_COLUMN_CODE
            || str_contains($code, '42S21')
            || str_contains($code, '42S11');
    }

    public static function columnExists(PDO $pdo, string $driver, string $table, string $column): bool
    {
        try {
            if ($driver === 'sqlite') {
                $rows = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll() ?: [];
                foreach ($rows as $row) {
                    if ((string)($row['name'] ?? '') === $column) {
                        return true;
                    }
                }
                return false;
            }
            if ($driver === 'pgsql') {
                $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = :table AND column_name = :column LIMIT 1');
                $stmt->execute(['table' => $table, 'column' => $column]);
                return (bool)$stmt->fetchColumn();
            }
            if ($driver === 'sqlsrv') {
                $stmt = $pdo->prepare('SELECT 1 FROM sys.columns c INNER JOIN sys.objects o ON o.object_id = c.object_id WHERE o.name = :table AND c.name = :column');
                $stmt->execute(['table' => $table, 'column' => $column]);
                return (bool)$stmt->fetchColumn();
            }
            $stmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column LIMIT 1');
            $stmt->execute(['table' => $table, 'column' => $column]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('[IndexHelper::columnExists] ' . $e->getMessage());
            return false;
        }
    }
}
