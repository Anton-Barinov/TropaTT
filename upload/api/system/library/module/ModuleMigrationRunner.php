<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use Api\System\Library\Support\AppLog;
use PDO;
use Api\System\Library\Database\IndexHelper;
use RuntimeException;

final class ModuleMigrationRunner
{
    private PDO $pdo;
    private string $tableName = 'module_migrations';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Run all pending migrations for a module.
     * @param string $moduleName Module name (vendor.name)
     * @param string $migrationDir Absolute path to migrations directory
     * @param string|null $targetVersion Optional target version for versioned migrations
     * @return array{applied: array<string>, errors: array<string>}
     */
    public function migrate(string $moduleName, string $migrationDir, ?string $targetVersion = null): array
    {
        $result = ['applied' => [], 'errors' => []];

        if (!is_dir($migrationDir)) {
            return $result;
        }

        $applied = $this->getAppliedMigrations($moduleName);

        $files = $this->scanMigrationFiles($migrationDir, $applied);

        foreach ($files as $file) {
            if (!$this->isUpFile($file)) {
                continue;
            }

            $migrationName = basename($file);

            try {
                $sql = file_get_contents($file);
                if ($sql === false || trim((string)$sql) === '') {
                    continue;
                }

                $this->pdo->beginTransaction();
                $this->execSqlScript($sql);

                if (!$this->pdo->inTransaction()) {
                    $this->pdo->beginTransaction();
                }

                $this->recordMigration($moduleName, $migrationName);
                $this->pdo->commit();

                $result['applied'][] = $migrationName;
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                $result['errors'][] = "{$migrationName}: " . $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Rollback last N migrations for a module.
     * @return array{rolled_back: array<string>, errors: array<string>}
     */
    public function rollback(string $moduleName, string $migrationDir, int $steps = 1): array
    {
        $result = ['rolled_back' => [], 'errors' => []];

        $applied = $this->getAppliedMigrations($moduleName);
        $toRollback = array_slice($applied, -$steps);

        foreach (array_reverse($toRollback) as $migration) {
            $rollbackFile = $migrationDir . '/' . str_replace('.sql', '_rollback.sql', $migration);
            if (!is_file($rollbackFile)) {
                $rollbackFile = $migrationDir . '/' . str_replace('up.sql', 'down.sql', $migration);
            }

            if (!is_file($rollbackFile)) {
                $result['errors'][] = "No rollback file for: {$migration}";
                continue;
            }

            try {
                $sql = file_get_contents($rollbackFile);
                if ($sql === false || trim((string)$sql) === '') {
                    continue;
                }

                    $this->pdo->beginTransaction();
                    $this->execSqlScript($sql);

                    if (!$this->pdo->inTransaction()) {
                        $this->pdo->beginTransaction();
                    }

                    $this->removeMigrationRecord($moduleName, $migration);
                    $this->pdo->commit();

                $result['rolled_back'][] = $migration;
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                $result['errors'][] = "{$migration}: " . $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Rollback ALL migrations for a module.
     * @return array{rolled_back: array<string>, errors: array<string>}
     */
    public function rollbackAll(string $moduleName, string $migrationDir): array
    {
        $applied = $this->getAppliedMigrations($moduleName);
        return $this->rollback($moduleName, $migrationDir, count($applied));
    }

    /**
     * Get migration status for a module.
     * @return array{migrations: array<int, array{name: string, applied: bool, applied_at: string|null}>}
     */
    public function getStatus(string $moduleName, string $migrationDir): array
    {
        $applied = $this->getAppliedMigrations($moduleName);
        $files = $this->scanMigrationFiles($migrationDir, []);
        $upFiles = array_filter($files, fn($f) => $this->isUpFile($f));

        $migrations = [];
        foreach ($upFiles as $file) {
            $name = basename($file);
            $migrations[] = [
                'name' => $name,
                'applied' => in_array($name, $applied, true),
                'applied_at' => $this->findMigrationAppliedAt($moduleName, $name),
            ];
        }

        return ['migrations' => $migrations];
    }

    /**
     * Run version-based migrations from a versioned directory structure.
     * Structure: migrations/{version}/*.sql
     *
     * @return array{applied: array<string>, errors: array<string>}
     */
    public function migrateVersion(string $moduleName, string $migrationDir, string $targetVersion): array
    {
        $result = ['applied' => [], 'errors' => []];

        if (!is_dir($migrationDir)) {
            return $result;
        }

        $versions = [];
        $items = scandir($migrationDir);
        if ($items === false) {
            return $result;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item[0] === '.') {
                continue;
            }
            $versionPath = $migrationDir . '/' . $item;
            if (is_dir($versionPath)) {
                $versions[] = $item;
            }
        }

        usort($versions, 'version_compare');
        $applied = $this->getAppliedMigrations($moduleName);

        foreach ($versions as $version) {
            if (version_compare($version, $targetVersion, '>')) {
                break;
            }

            $versionDir = $migrationDir . '/' . $version;
            $files = glob($versionDir . '/*.sql');
            if ($files === false) {
                continue;
            }

            foreach ($files as $file) {
                $migrationName = $version . '/' . basename($file);
                if (str_ends_with($file, '_rollback.sql') || str_ends_with($file, 'down.sql')) {
                    continue;
                }
                if (in_array($migrationName, $applied, true)) {
                    continue;
                }

                try {
                    $sql = file_get_contents($file);
                    if ($sql === false || trim((string)$sql) === '') {
                        continue;
                    }

                    $this->pdo->beginTransaction();
                    $this->execSqlScript($sql);

                    if (!$this->pdo->inTransaction()) {
                        $this->pdo->beginTransaction();
                    }

                    $this->recordMigration($moduleName, $migrationName);
                    $this->pdo->commit();

                    $result['applied'][] = $migrationName;
                } catch (\Throwable $e) {
                    if ($this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                    $result['errors'][] = "{$migrationName}: " . $e->getMessage();
                }
            }
        }

        return $result;
    }

    /**
     * Execute every statement of a migration script, one exec() per statement.
     *
     * Migration files are whole scripts, and rollback files in particular often
     * hold several statements ("SET FOREIGN_KEY_CHECKS = 0; DROP TABLE a; DROP
     * TABLE b; SET FOREIGN_KEY_CHECKS = 1;" — that is exactly what the published
     * crm.wip-limit rollback contains). Passing such a string to a single
     * PDO::exec() executes only the first statement on MySQL and leaves the
     * remaining result sets pending, so the very next query on that connection
     * dies with "SQLSTATE[HY000] General error: 2014 Cannot execute queries while
     * other unbuffered queries are active" — which is what made every module
     * uninstall fail with UNINSTALL_FAILED (HTTP 500) and leave a registry row
     * behind after the files were already gone.
     */
    private function execSqlScript(string $sql): void
    {
        foreach (self::splitStatements($sql) as $statement) {
            try {
                $stmt = $this->pdo->query($statement);
                if ($stmt !== false) {
                    $this->drainStatement($stmt);
                }
            } catch (\Throwable $e) {
                if (!$this->isAlreadyAppliedError($statement, $e)) {
                    throw $e;
                }

                // The statement (or part of it) is already applied to this
                // database. That is the normal case when a module is installed
                // again over the schema a previous install left behind: module
                // uninstall intentionally keeps the tables (user data survives),
                // so the migration log and the schema disagree.
                //
                // A multi-clause ALTER TABLE fails as a whole, so one missing
                // clause would keep the others from being applied — run the
                // clauses one by one and tolerate the ones already in place.
                $clauses = self::splitAlterClauses($statement);
                if ($clauses === null) {
                    AppLog::warning(
                        '[ModuleMigrationRunner] statement already applied, skipped: '
                        . self::oneLine($statement) . ' — ' . $e->getMessage()
                    );
                    continue;
                }

                foreach ($clauses as $clause) {
                    try {
                        $stmt = $this->pdo->query($clause);
                        if ($stmt !== false) {
                            $this->drainStatement($stmt);
                        }
                    } catch (\Throwable $inner) {
                        if (!$this->isAlreadyAppliedError($clause, $inner)) {
                            throw $inner;
                        }
                        AppLog::warning(
                            '[ModuleMigrationRunner] clause already applied, skipped: '
                            . self::oneLine($clause) . ' — ' . $inner->getMessage()
                        );
                    }
                }
            }
        }
    }

    /**
     * Is the failure the schema telling us this migration is already applied?
     *
     * Only schema statements are judged — a failing `INSERT` or `SET` is a real
     * error. Duplicate-object failures are always benign (the object exists, so
     * the migration's intent is satisfied), while "there is nothing to drop"
     * is tolerated for DROP statements only: an ALTER against a table that does
     * not exist is a broken migration and must still fail loudly, otherwise the
     * migration would be recorded as applied over a schema that was never built.
     *
     * @param int $code driver error code (MySQL error number)
     */
    private function isAlreadyAppliedError(string $statement, \Throwable $e): bool
    {
        if (preg_match('/^\s*(CREATE|ALTER|DROP|INSERT|RENAME)\b/i', $statement) !== 1) {
            return false;
        }

        $code = $e instanceof \PDOException ? (int)($e->errorInfo[1] ?? 0) : 0;
        $message = strtolower($e->getMessage());

        // ER_DUP_KEY(1022), ER_TABLE_EXISTS_ERROR(1050), ER_DUP_FIELDNAME(1060),
        // ER_DUP_KEYNAME(1061), ER_DUP_ENTRY(1062), ER_FK_DUP_NAME(1826).
        if (in_array($code, [1022, 1050, 1060, 1061, 1062, 1826], true)) {
            return true;
        }

        foreach ([
            'duplicate column name',
            'duplicate table',
            'duplicate_table',
            'duplicate_object',
            'duplicate_column',
            'duplicate key name',
            'duplicate foreign key constraint name',
            'duplicate entry',
            'unique constraint failed',
            'already exists',
        ] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        if (preg_match('/^\s*DROP\b/i', $statement) === 1) {
            // ER_BAD_TABLE_ERROR(1051), ER_CANT_DROP_FIELD_OR_KEY(1091).
            if (in_array($code, [1051, 1091], true)) {
                return true;
            }
            foreach (['no such table', 'no such column', 'no such index', 'unknown table', 'unknown column', 'does not exist', "can't drop", 'cannot drop'] as $needle) {
                if (str_contains($message, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Split a multi-clause `ALTER TABLE t ...` into one statement per clause.
     *
     * Returns null when the statement is not an ALTER TABLE with a comma-separated
     * clause list; a single-clause ALTER, an ALTER of one INDEX over several
     * columns (`ADD INDEX i (a, b)`) and every non-ALTER statement must be left
     * exactly as written.
     *
     * @return array<int,string>|null
     */
    private static function splitAlterClauses(string $statement): ?array
    {
        if (preg_match('/^\s*ALTER\s+TABLE\s+(`[^`]+`|"[^"]+"|`?[A-Za-z0-9_.$]+`?)\s+(.*)$/is', $statement, $m) !== 1) {
            return null;
        }

        $parts = self::splitTopLevelCommas($m[2]);
        if (count($parts) < 2) {
            return null;
        }

        $clauses = [];
        foreach ($parts as $part) {
            $part = trim($part);
            // Each comma-separated part of a multi-clause ALTER starts with its
            // own action; the comma inside `ADD INDEX i (a, b)` is nested in
            // parentheses and is therefore not a separator.
            if (preg_match('/^(ADD|DROP|MODIFY|CHANGE|ALTER|RENAME|CONVERT|COMMENT|ENGINE)\b/i', $part) !== 1) {
                return null;
            }
            $clauses[] = 'ALTER TABLE ' . $m[1] . ' ' . $part;
        }

        return $clauses;
    }

    /**
     * Split on commas that are not inside parentheses, string literals or
     * quoted identifiers.
     *
     * @return array<int,string>
     */
    private static function splitTopLevelCommas(string $sql): array
    {
        $parts = [];
        $buffer = '';
        $depth = 0;
        $quote = '';
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($quote !== '') {
                $buffer .= $char;
                if ($char === '\\' && $quote !== '`' && $next !== '') {
                    $buffer .= $next;
                    $i++;
                    continue;
                }
                if ($char === $quote) {
                    if ($next === $quote) {
                        $buffer .= $next;
                        $i++;
                        continue;
                    }
                    $quote = '';
                }
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')' && $depth > 0) {
                $depth--;
            } elseif ($char === ',' && $depth === 0) {
                $parts[] = $buffer;
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $parts[] = $buffer;

        return $parts;
    }

    /**
     * Keep log lines readable: a migration statement can span many lines.
     */
    private static function oneLine(string $sql, int $limit = 220): string
    {
        $flat = trim((string)preg_replace('/\s+/', ' ', $sql));
        return mb_strlen($flat) > $limit ? mb_substr($flat, 0, $limit) . '…' : $flat;
    }

    /**
     * Consume and close whatever a statement produced.
     *
     * A row-returning statement executed through PDO::exec() leaves its result
     * set pending on the connection, and the next statement then fails with
     * "SQLSTATE[HY000] General error: 2014 Cannot execute queries while other
     * unbuffered queries are active" — even for a harmless trailing `SELECT 1;`,
     * which is exactly how the published crm.activecollab-migration rollback
     * ends ("the migration is intentionally irreversible" + `SELECT 1;`).
     * Going through a PDOStatement and draining it keeps the connection usable.
     */
    private function drainStatement(\PDOStatement $stmt): void
    {
        try {
            if ($stmt->columnCount() > 0) {
                $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            $driver = '';
            try {
                $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            } catch (\Throwable $e) {
                // Attribute support is driver-specific; the loop below then only
                // runs its first iteration.
            }

            // Several statements in one call (or a procedure) can queue more
            // result sets; SQLite has no such concept and would throw.
            if ($driver !== 'sqlite') {
                while ($stmt->nextRowset()) {
                    if ($stmt->columnCount() > 0) {
                        $stmt->fetchAll(PDO::FETCH_ASSOC);
                    }
                }
            }
        } catch (\Throwable $e) {
            // Draining is best-effort: a statement that cannot report rows must
            // not fail the migration that ran it.
        } finally {
            try {
                $stmt->closeCursor();
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    /**
     * Split a SQL script into individual statements.
     *
     * Semicolons inside string literals and inside comments are ignored, and both
     * `--` line comments and slash-star block comments are dropped; identifiers
     * quoted with backticks, single or double quotes are kept verbatim.
     *
     * @return array<int,string> Statements without their trailing semicolon
     */
    public static function splitStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $length = strlen($sql);
        $quote = '';
        $inLineComment = false;
        $inBlockComment = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($inLineComment) {
                if ($char === "\n") {
                    $inLineComment = false;
                    $buffer .= $char;
                }
                continue;
            }

            if ($inBlockComment) {
                if ($char === '*' && $next === '/') {
                    $inBlockComment = false;
                    $i++;
                }
                continue;
            }

            if ($quote !== '') {
                $buffer .= $char;
                // Backslash escapes are MySQL-specific and are not honoured inside
                // backtick-quoted identifiers.
                if ($char === '\\' && $quote !== '`' && $next !== '') {
                    $buffer .= $next;
                    $i++;
                    continue;
                }
                if ($char === $quote) {
                    if ($next === $quote) {
                        // A doubled quote is an escaped quote, not the end.
                        $buffer .= $next;
                        $i++;
                        continue;
                    }
                    $quote = '';
                }
                continue;
            }

            if ($char === '-' && $next === '-') {
                $inLineComment = true;
                $i++;
                continue;
            }

            if ($char === '/' && $next === '*') {
                $inBlockComment = true;
                $i++;
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }

            if ($char === ';') {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $tail = trim($buffer);
        if ($tail !== '') {
            $statements[] = $tail;
        }

        return $statements;
    }

    /**
     * Ensure module_migrations table exists.
     */
    public function ensureTable(string $driver): void
    {
        $id = match ($driver) {
            'mysql' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'pgsql' => 'SERIAL PRIMARY KEY',
            'sqlsrv' => 'INT IDENTITY(1,1) PRIMARY KEY',
            default => 'INTEGER PRIMARY KEY AUTOINCREMENT',
        };

        $dt = $driver === 'sqlsrv' ? 'DATETIME2' : 'DATETIME';
        $nowDefault = $driver === 'sqlite' ? "DEFAULT (datetime('now'))" : 'DEFAULT CURRENT_TIMESTAMP';
        $keyType = $driver === 'mysql' ? 'VARCHAR(190)' : 'TEXT';

        $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} (id {$id}, module_name {$keyType} NOT NULL, migration_name {$keyType} NOT NULL, applied_at {$dt} NOT NULL {$nowDefault}, batch INTEGER NOT NULL DEFAULT 1)";
        $this->pdo->exec($sql);

        try {
            IndexHelper::createIndexIfNotExists($this->pdo, $this->tableName, 'idx_module_migrations_unique', 'module_name, migration_name', true);
        } catch (\Throwable $e) {
            AppLog::error('[ModuleMigrationRunner::ensureTable] UNIQUE INDEX failed: ' . $e->getMessage());
        }

        try {
            IndexHelper::createIndexIfNotExists($this->pdo, $this->tableName, 'idx_module_migrations_module', 'module_name');
        } catch (\Throwable $e) {
            AppLog::error('[ModuleMigrationRunner::ensureTable] INDEX failed: ' . $e->getMessage());
        }
    }

    /**
     * @return array<int, string>
     */
    private function getAppliedMigrations(string $moduleName): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT migration_name FROM {$this->tableName} WHERE module_name = :module ORDER BY id ASC");
            $stmt->execute(['module' => $moduleName]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (\Throwable $e) {
            AppLog::error('[ModuleMigrationRunner::getAppliedMigrations] ' . $e->getMessage());
            return [];
        }
    }

    private function recordMigration(string $moduleName, string $migrationName): void
    {
        try {
            $maxBatch = $this->getMaxBatch($moduleName);
            $now = date('Y-m-d H:i:s');
            $stmt = $this->pdo->prepare("INSERT INTO {$this->tableName} (module_name, migration_name, applied_at, batch) VALUES (:module, :migration, :now, :batch)");
            $stmt->execute([
                'module' => $moduleName,
                'migration' => $migrationName,
                'now' => $now,
                'batch' => $maxBatch + 1,
            ]);
        } catch (\Throwable $e) {
            throw new RuntimeException("Failed to record migration: " . $e->getMessage(), 0, $e);
        }
    }

    private function removeMigrationRecord(string $moduleName, string $migrationName): void
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE module_name = :module AND migration_name = :migration");
            $stmt->execute([
                'module' => $moduleName,
                'migration' => $migrationName,
            ]);
        } catch (\Throwable $e) {
            throw new RuntimeException("Failed to remove migration record: " . $e->getMessage(), 0, $e);
        }
    }

    private function getMaxBatch(string $moduleName): int
    {
        try {
            $stmt = $this->pdo->prepare("SELECT COALESCE(MAX(batch), 0) FROM {$this->tableName} WHERE module_name = :module");
            $stmt->execute(['module' => $moduleName]);
            return (int)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            AppLog::error('[ModuleMigrationRunner::getMaxBatch] ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * @param array<int, string> $applied
     * @return array<int, string>
     */
    private function scanMigrationFiles(string $dir, array $applied): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir . '/*.sql');
        if ($files === false) {
            return [];
        }

        $pending = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (!in_array($name, $applied, true)) {
                $pending[] = $file;
            }
        }

        usort($pending, function ($a, $b) {
            return strnatcmp(basename($a), basename($b));
        });
        return $pending;
    }

    private function isUpFile(string $file): bool
    {
        $name = basename($file);
        return !str_ends_with($name, '_rollback.sql') && !str_ends_with($name, 'down.sql');
    }

    private function findMigrationAppliedAt(string $moduleName, string $migrationName): ?string
    {
        try {
            $stmt = $this->pdo->prepare("SELECT applied_at FROM {$this->tableName} WHERE module_name = :module AND migration_name = :migration");
            $stmt->execute(['module' => $moduleName, 'migration' => $migrationName]);
            $result = $stmt->fetchColumn();
            return $result ? (string)$result : null;
        } catch (\Throwable $e) {
            AppLog::error('[ModuleMigrationRunner::findMigrationAppliedAt] ' . $e->getMessage());
            return null;
        }
    }
}
