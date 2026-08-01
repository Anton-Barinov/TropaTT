<?php
declare(strict_types=1);

namespace Updater\Backup;

use PDO;
use Updater\Db\Connection;

/**
 * Portable database backup/restore for the updater.
 *
 * Uses pure PDO (no shell, no mysqldump) so it works on the simplest shared
 * hosting: dumps each base table's CREATE TABLE plus batched INSERTs, recreates
 * views after tables, and recreates triggers after data is loaded (so triggers
 * never fire during restore). Restore drops every table currently present so a
 * table added by the migration is removed too - the exact pre-update schema.
 *
 * MySQL is fully supported. SQLite is supported as a plain file copy (the
 * CRM's own sqlite file), since migrations and the updater already run on
 * sqlite for local/dev installs.
 */
final class DatabaseBackupManager extends BackupManager
{
    public function __construct(private readonly string $basePath)
    {
    }

    /**
     * Create a database backup inside the given backup directory.
     *
     * @return array{ok:bool,driver?:string,tables?:int,views?:int,triggers?:int,rows?:int,error?:string,skipped?:bool,reason?:string}
     */
    public function backup(string $backupDir, string $jobId): array
    {
        // A large database dump can exceed the default max_execution_time on
        // shared hosting; the updater runs inside the web request, so lift the
        // limit for the dump (best-effort, some hosts cap it regardless).
        @set_time_limit(0);

        $conn = Connection::open($this->basePath);
        $pdo = $conn['pdo'];
        $driver = $conn['driver'];

        if ($driver === 'sqlite') {
            return $this->backupSqlite($backupDir, $jobId, $conn['database']);
        }
        if ($driver !== 'mysql') {
            return ['ok' => false, 'skipped' => true, 'reason' => 'Unsupported database driver for backup: ' . $driver];
        }

        $dbDir = $backupDir . '/db';
        if (!is_dir($dbDir) && !@mkdir($dbDir, 0775, true) && !is_dir($dbDir)) {
            return ['ok' => false, 'error' => 'Unable to create db backup directory: ' . $dbDir];
        }

        // Data and schema only for base tables; views are dumped as DDL only
        // (SELECT * works, but INSERT INTO a view would fail on restore).
        $tables = $this->listBaseTables($pdo);
        $views = $this->listViews($pdo);

        $schemaFile = $dbDir . '/schema.sql';
        $dataFile = $dbDir . '/data.sql';
        $triggersFile = $dbDir . '/triggers.sql';

        $schema = fopen($schemaFile, 'w');
        $data = fopen($dataFile, 'w');
        if ($schema === false || $data === false) {
            return ['ok' => false, 'error' => 'Unable to open dump files.'];
        }

        fwrite($schema, "-- TropaTT DB schema backup ({$jobId})\n");
        fwrite($data, "-- TropaTT DB data backup ({$jobId})\n");

        $rows = 0;
        foreach ($tables as $table) {
            $create = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM);
            $createSql = is_array($create) ? (string)($create[1] ?? '') : '';
            if ($createSql === '') {
                fclose($schema);
                fclose($data);
                return ['ok' => false, 'error' => 'Unable to read schema for table: ' . $table];
            }
            $this->writeStatement($schema, "DROP TABLE IF EXISTS `{$table}`;");
            $this->writeStatement($schema, $createSql . ';');

            $rows += $this->dumpTableData($pdo, $data, $table);
        }

        // Views after tables so dependencies resolve.
        foreach ($views as $view) {
            $create = $pdo->query('SHOW CREATE VIEW `' . $view . '`')->fetch(PDO::FETCH_NUM);
            $createSql = is_array($create) ? (string)($create[1] ?? '') : '';
            if ($createSql !== '') {
                $this->writeStatement($schema, "DROP VIEW IF EXISTS `{$view}`;");
                $this->writeStatement($schema, $createSql . ';');
            }
        }

        fclose($schema);
        fclose($data);

        $triggers = $this->dumpTriggers($pdo, $triggersFile);
        if ($triggers === null) {
            return ['ok' => false, 'error' => 'Unable to dump database triggers.'];
        }

        // Record SHA-256 of every dump file in the manifest so restore() can
        // verify the backup is intact BEFORE dropping the live schema. A
        // truncated, zeroed or bit-rotten dump must never be replayed over a
        // healthy database (hash_file() streams internally, so this stays
        // memory-flat even for hundreds-of-MB data dumps).
        $manifest = [
            'ok' => true,
            'driver' => 'mysql',
            'job_id' => $jobId,
            'created_at' => gmdate('c'),
            'tables' => count($tables),
            'views' => count($views),
            'triggers' => $triggers,
            'rows' => $rows,
            'schema_file' => 'db/schema.sql',
            'data_file' => 'db/data.sql',
            'triggers_file' => 'db/triggers.sql',
            'schema_sha256' => hash_file('sha256', $schemaFile) ?: '',
            'data_sha256' => hash_file('sha256', $dataFile) ?: '',
            'triggers_sha256' => is_file($triggersFile) ? (hash_file('sha256', $triggersFile) ?: '') : '',
        ];
        file_put_contents($dbDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $manifest;
    }

    /**
     * Restore the database from a backup directory created by backup().
     *
     * NOTE: this drops every table/view in the connected database and replays
     * the backup - it assumes the CRM owns its MySQL database (the normal
     * shared-hosting layout). Views/triggers carry a DEFINER clause from
     * SHOW CREATE VIEW/TRIGGER; a same-user restore (the common case) is
     * fine, but on hosts where the definer user differs, those DDL statements
     * can fail and are reported in the restore report.
     *
     * @return array{ok:bool,driver?:string,tables?:int,views?:int,triggers?:int,rows?:int,error?:string,skipped?:bool,reason?:string}
     */
    public function restore(string $backupDir): array
    {
        @set_time_limit(0);

        $dbDir = $backupDir . '/db';
        $manifestFile = $dbDir . '/manifest.json';
        if (!is_file($manifestFile)) {
            return ['ok' => false, 'skipped' => true, 'reason' => 'No database backup manifest found.'];
        }
        $manifest = json_decode((string)file_get_contents($manifestFile), true);
        $driver = is_array($manifest) ? (string)($manifest['driver'] ?? 'mysql') : 'mysql';

        if ($driver === 'sqlite') {
            return $this->restoreSqlite($backupDir);
        }
        if ($driver !== 'mysql') {
            return ['ok' => false, 'skipped' => true, 'reason' => 'Unsupported backup driver: ' . $driver];
        }

        $schemaFile = $dbDir . '/schema.sql';
        $dataFile = $dbDir . '/data.sql';
        $triggersFile = $dbDir . '/triggers.sql';
        if (!is_file($schemaFile) || !is_file($dataFile)) {
            return ['ok' => false, 'error' => 'Backup dump files missing.'];
        }

        // Integrity gate BEFORE any destructive step. Verify the dump files
        // match the hashes recorded at backup time (with a structural fallback
        // for legacy backups that predate hashing). A corrupt or truncated
        // dump must never be replayed over the live database - an interrupted
        // restore that has already dropped the schema is unrecoverable.
        $integrityError = $this->verifyDumpIntegrity($dbDir, is_array($manifest) ? $manifest : []);
        if ($integrityError !== null) {
            return [
                'ok' => false,
                'error' => 'Database backup integrity check failed: ' . $integrityError
                    . '. Restore aborted before any change; the live database was left untouched.',
            ];
        }

        $conn = Connection::open($this->basePath);
        if ($conn['driver'] !== 'mysql') {
            return ['ok' => false, 'error' => 'Current DB driver (' . $conn['driver'] . ') does not match backup driver (mysql).'];
        }
        $pdo = $conn['pdo'];

        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            // Drop views and tables currently present so tables created by the
            // migration are removed too - a full rollback must restore the
            // exact pre-update schema.
            foreach ($this->listViews($pdo) as $existingView) {
                $pdo->exec('DROP VIEW IF EXISTS `' . $existingView . '`');
            }
            foreach ($this->listBaseTables($pdo) as $existingTable) {
                $pdo->exec('DROP TABLE IF EXISTS `' . $existingTable . '`');
            }
            $this->execSqlFile($pdo, $schemaFile);
            $this->execSqlFile($pdo, $dataFile);
            // Triggers last so they do not fire during data restore.
            if (is_file($triggersFile)) {
                $this->execSqlFile($pdo, $triggersFile);
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }

        return [
            'ok' => true,
            'driver' => 'mysql',
            'integrity' => 'verified',
            'tables' => is_array($manifest) ? (int)($manifest['tables'] ?? 0) : 0,
            'views' => is_array($manifest) ? (int)($manifest['views'] ?? 0) : 0,
            'triggers' => is_array($manifest) ? (int)($manifest['triggers'] ?? 0) : 0,
            'rows' => is_array($manifest) ? (int)($manifest['rows'] ?? 0) : 0,
        ];
    }

    /**
     * Verify a MySQL dump directory is intact BEFORE any table/view is dropped.
     *
     * Primary check: SHA-256 of schema.sql/data.sql/triggers.sql recorded in
     * manifest.json at backup time. Any mismatch (truncated file, bit rot,
     * manual edit, partial disk write) aborts the restore.
     *
     * Fallback for LEGACY backups created before hashing existed (no *_sha256
     * keys in the manifest): structural sanity - files exist and are non-empty,
     * carry the expected dump header, and the schema file contains at least
     * one statement marker per recorded table (a full truncation to zero bytes
     * is the realistic corruption mode here).
     *
     * @return string|null error message when the dump is corrupt, null when OK
     */
    private function verifyDumpIntegrity(string $dbDir, array $manifest): ?string
    {
        $schemaFile = $dbDir . '/schema.sql';
        $dataFile = $dbDir . '/data.sql';
        $triggersFile = $dbDir . '/triggers.sql';

        $schemaSha = (string)($manifest['schema_sha256'] ?? '');
        $dataSha = (string)($manifest['data_sha256'] ?? '');
        if ($schemaSha !== '' && $dataSha !== '') {
            if (!is_file($schemaFile) || hash_file('sha256', $schemaFile) !== $schemaSha) {
                return 'schema.sql checksum mismatch (corrupt or missing backup)';
            }
            if (!is_file($dataFile) || hash_file('sha256', $dataFile) !== $dataSha) {
                return 'data.sql checksum mismatch (corrupt or missing backup)';
            }
            $triggersSha = (string)($manifest['triggers_sha256'] ?? '');
            if ($triggersSha !== '') {
                // backup() always writes triggers.sql (even when empty), so a
                // recorded hash with a missing file is real corruption: the
                // restore loop would otherwise silently skip triggers.
                if (!is_file($triggersFile)) {
                    return 'triggers.sql is missing (corrupt or modified backup)';
                }
                if (hash_file('sha256', $triggersFile) !== $triggersSha) {
                    return 'triggers.sql checksum mismatch (corrupt or modified backup)';
                }
            }
            return null;
        }

        // Legacy backups without recorded hashes: structural sanity check.
        if (!is_file($schemaFile) || filesize($schemaFile) === 0) {
            return 'schema.sql is missing or empty';
        }
        if (!is_file($dataFile) || filesize($dataFile) === 0) {
            return 'data.sql is missing or empty';
        }
        if (!str_starts_with($this->firstLine($schemaFile), '-- TropaTT DB schema backup')) {
            return 'schema.sql has an unexpected header (not a TropaTT dump)';
        }
        if (!str_starts_with($this->firstLine($dataFile), '-- TropaTT DB data backup')) {
            return 'data.sql has an unexpected header (not a TropaTT dump)';
        }
        $expectedStatements = (int)($manifest['tables'] ?? 0);
        if ($expectedStatements > 0) {
            $found = $this->countStatementMarkers($schemaFile, $expectedStatements);
            if ($found < $expectedStatements) {
                return "schema.sql is truncated (found {$found} statements, expected at least {$expectedStatements} tables)";
            }
        }
        // Legacy data.sql: when the manifest records rows, the dump must carry
        // at least one INSERT marker (early-exit, so a huge data.sql costs a
        // single fgets pass up to its first statement). Catches the realistic
        // "data file stripped to just the header" corruption.
        if ((int)($manifest['rows'] ?? 0) > 0 && $this->countStatementMarkers($dataFile, 1) < 1) {
            return 'data.sql is truncated (no INSERT statements despite recorded rows)';
        }
        return null;
    }

    /**
     * Read the first line of a dump file (used for header validation).
     */
    private function firstLine(string $file): string
    {
        $handle = @fopen($file, 'r');
        if ($handle === false) {
            return '';
        }
        $line = fgets($handle);
        fclose($handle);
        return is_string($line) ? rtrim($line) : '';
    }

    /**
     * Stream-count `-- @@TROPA_SQL@@` markers up to a limit (early exit).
     * Memory-flat: never loads the whole file, so it is safe on large dumps.
     */
    private function countStatementMarkers(string $file, int $limit): int
    {
        $handle = @fopen($file, 'r');
        if ($handle === false) {
            return 0;
        }
        $count = 0;
        while (($line = fgets($handle, 1048576)) !== false) {
            if (rtrim($line) === '-- @@TROPA_SQL@@') {
                $count++;
                if ($count >= $limit) {
                    break;
                }
            }
        }
        fclose($handle);
        return $count;
    }

    /** @return list<string> */
    private function listBaseTables(PDO $pdo): array
    {
        $rows = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        $tables = [];
        if ($rows !== false) {
            foreach ($rows->fetchAll(PDO::FETCH_NUM) as $row) {
                $tables[] = (string)($row[0] ?? '');
            }
        }
        return array_values(array_filter($tables, static fn (string $t): bool => $t !== ''));
    }

    /** @return list<string> */
    private function listViews(PDO $pdo): array
    {
        $rows = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'");
        $views = [];
        if ($rows !== false) {
            foreach ($rows->fetchAll(PDO::FETCH_NUM) as $row) {
                $views[] = (string)($row[0] ?? '');
            }
        }
        return array_values(array_filter($views, static fn (string $v): bool => $v !== ''));
    }

    private function dumpTableData(PDO $pdo, $handle, string $table): int
    {
        $stmt = $pdo->query('SELECT * FROM `' . $table . '`');
        if ($stmt === false) {
            return 0;
        }
        $rows = 0;
        $batch = [];
        $batchSize = 100;

        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $values = [];
            foreach ($row as $value) {
                if ($value === null) {
                    $values[] = 'NULL';
                } elseif (is_int($value) || is_float($value)) {
                    $values[] = (string)$value;
                } else {
                    $values[] = $pdo->quote((string)$value);
                }
            }
            $batch[] = '(' . implode(', ', $values) . ')';
            if (count($batch) >= $batchSize) {
                $this->writeStatement($handle, 'INSERT INTO `' . $table . '` VALUES ' . implode(', ', $batch) . ';');
                $batch = [];
            }
            $rows++;
        }
        if ($batch !== []) {
            $this->writeStatement($handle, 'INSERT INTO `' . $table . '` VALUES ' . implode(', ', $batch) . ';');
        }

        return $rows;
    }

    /**
     * Dump CREATE TRIGGER statements (they are not part of SHOW CREATE TABLE).
     *
     * @return int|null number of triggers dumped, or null on error
     */
    private function dumpTriggers(PDO $pdo, string $triggersFile): ?int
    {
        $handle = fopen($triggersFile, 'w');
        if ($handle === false) {
            return null;
        }
        fwrite($handle, "-- TropaTT DB triggers backup\n");

        $rows = $pdo->query('SHOW TRIGGERS');
        $names = [];
        if ($rows !== false) {
            foreach ($rows->fetchAll(PDO::FETCH_NUM) as $row) {
                $names[] = (string)($row[0] ?? '');
            }
        }
        $written = 0;
        foreach ($names as $name) {
            $stmt = $pdo->query('SHOW CREATE TRIGGER `' . $name . '`');
            if ($stmt === false) {
                continue;
            }
            $row = $stmt->fetch(PDO::FETCH_NUM);
            $sql = is_array($row) ? (string)($row[2] ?? '') : '';
            if ($sql !== '') {
                $this->writeStatement($handle, "DROP TRIGGER IF EXISTS `{$name}`;");
                $this->writeStatement($handle, $sql . ';');
                $written++;
            }
        }
        fclose($handle);

        return $written;
    }

    /**
     * Write one SQL statement preceded by the marker comment the executor
     * splits on. Statements can contain any number of ';' and newlines (e.g.
     * compound trigger bodies), so a naive ";\n" split would corrupt them.
     *
     * NOTE: the marker is an exact full-line match, so a statement body that
     * itself contains a line reading exactly `-- @@TROPA_SQL@@` (a contrived
     * trigger/comment case) would break the split. Practically unreachable
     * for CRM DDL/data; kept simple on purpose.
     */
    private function writeStatement($handle, string $sql): void
    {
        fwrite($handle, "-- @@TROPA_SQL@@\n" . $sql . "\n");
    }

    /**
     * Execute a dump file statement by statement. Full-line comments are
     * skipped so a leading comment line never swallows the following
     * statement during the split.
     *
     * STREAMS the file line by line (fgets) instead of loading it into
     * memory: a real database dump (e.g. 300MB+ of INSERTs for 1M+ rows) must
     * never be read with file_get_contents() + preg_split(), which blows the
     * memory_limit with an UNCATCHABLE fatal error mid-restore - after the
     * tables have already been dropped - leaving the CRM database empty and
     * maintenance mode stuck on. Each statement is preceded by its own marker
     * comment line, so the split is safe for statements containing ';' and
     * newlines inside string literals or compound trigger bodies.
     */
    private function execSqlFile(PDO $pdo, string $file): void
    {
        $handle = @fopen($file, 'r');
        if ($handle === false) {
            return;
        }
        $statement = '';
        // 1MB read buffer: keeps memory flat even for multi-MB INSERT lines
        // (a 100-row batch with TEXT/JSON values can be hundreds of KB). A
        // line longer than the buffer is reassembled across fgets() calls,
        // so correctness does not depend on the buffer size.
        while (($line = fgets($handle, 1048576)) !== false) {
            if (rtrim($line) === '-- @@TROPA_SQL@@') {
                $this->execStatement($pdo, $statement);
                $statement = '';
                continue;
            }
            $statement .= $line;
        }
        $this->execStatement($pdo, $statement);
        fclose($handle);
    }

    private function execStatement(PDO $pdo, string $statement): void
    {
        $statement = trim($statement);
        if ($statement === '' || str_starts_with($statement, '--')) {
            return;
        }
        $pdo->exec($statement);
    }

    private function backupSqlite(string $backupDir, string $jobId, array $dbConfig): array
    {
        $dbDir = $backupDir . '/db';
        if (!is_dir($dbDir) && !@mkdir($dbDir, 0775, true) && !is_dir($dbDir)) {
            return ['ok' => false, 'error' => 'Unable to create db backup directory: ' . $dbDir];
        }
        $source = (string)($dbConfig['database'] ?? '');
        if ($source === '' || !is_file($source)) {
            return ['ok' => false, 'skipped' => true, 'reason' => 'SQLite database file not found.'];
        }
        $target = $dbDir . '/crm.sqlite';
        if (!copy($source, $target)) {
            return ['ok' => false, 'error' => 'Unable to copy SQLite database file.'];
        }
        $manifest = [
            'ok' => true,
            'driver' => 'sqlite',
            'job_id' => $jobId,
            'created_at' => gmdate('c'),
            'tables' => null,
            'views' => null,
            'triggers' => null,
            'rows' => null,
            'schema_file' => 'db/crm.sqlite',
        ];
        file_put_contents($dbDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $manifest;
    }

    private function restoreSqlite(string $backupDir): array
    {
        $dbDir = $backupDir . '/db';
        $source = $dbDir . '/crm.sqlite';
        if (!is_file($source)) {
            return ['ok' => false, 'error' => 'SQLite backup file missing.'];
        }
        $conn = Connection::open($this->basePath);
        $target = (string)($conn['database']['database'] ?? '');
        if ($target === '') {
            return ['ok' => false, 'error' => 'SQLite database path unknown.'];
        }
        if (!copy($source, $target)) {
            return ['ok' => false, 'error' => 'Unable to restore SQLite database file.'];
        }
        return ['ok' => true, 'driver' => 'sqlite', 'tables' => null, 'views' => null, 'triggers' => null, 'rows' => null];
    }
}
