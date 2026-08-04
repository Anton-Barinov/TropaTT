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
     * Create a database backup inside the given backup directory, optionally
     * resuming from $cursor with a bounded amount of work per call.
     *
     * A real database dump (hundreds of MB / millions of rows) can never fit
     * in one shared-hosting request, so the dump runs as a step machine:
     * every call dumps at most $maxRows rows (LIMIT/OFFSET per table, so each
     * chunk is memory-flat) and returns 'done' => false until the whole dump
     * is written. The final manifest with integrity hashes is produced once
     * the last chunk finishes.
     *
     * @param array{stage?:string,table_index?:int,offset?:int,rows_done?:int,views_done?:int,triggers_done?:int}|null $cursor
     * @return array{ok:bool,done:bool,cursor?:array<string,int>,driver?:string,tables?:int,views?:int,triggers?:int,rows?:int,error?:string,skipped?:bool,reason?:string}
     */
    public function backup(string $backupDir, string $jobId, ?array $cursor = null, ?\Updater\Util\WorkBudget $budget = null, int $maxRows = 50000): array
    {
        // A large database dump can exceed the default max_execution_time on
        // shared hosting; the updater runs inside the web request, so lift the
        // limit for the dump (best-effort, some hosts cap it regardless). The
        // step budget below keeps each REQUEST short even when PHP allows more.
        @set_time_limit(0);

        $conn = Connection::open($this->basePath);
        $pdo = $conn['pdo'];
        $driver = $conn['driver'];

        if ($driver === 'sqlite') {
            $report = $this->backupSqlite($backupDir, $jobId, $conn['database']);
            $report['done'] = true;
            return $report;
        }
        if ($driver !== 'mysql') {
            return ['ok' => false, 'done' => true, 'skipped' => true, 'reason' => 'Unsupported database driver for backup: ' . $driver];
        }

        $dbDir = $backupDir . '/db';
        if (!is_dir($dbDir) && !@mkdir($dbDir, 0775, true) && !is_dir($dbDir)) {
            return ['ok' => false, 'done' => true, 'error' => 'Unable to create db backup directory: ' . $dbDir];
        }

        $schemaFile = $dbDir . '/schema.sql';
        $dataFile = $dbDir . '/data.sql';
        $triggersFile = $dbDir . '/triggers.sql';

        $stage = (string)($cursor['stage'] ?? 'tables');
        $tableIndex = (int)($cursor['table_index'] ?? 0);
        $offset = (int)($cursor['offset'] ?? 0);
        $rowsDone = (int)($cursor['rows_done'] ?? 0);
        $viewsDone = (int)($cursor['views_done'] ?? 0);
        $viewsDumped = (int)($cursor['views_dumped'] ?? 0);
        $triggersDone = (int)($cursor['triggers_done'] ?? 0);
        $triggersWritten = (int)($cursor['triggers_written'] ?? 0);

        $tables = $this->listBaseTables($pdo);
        $views = $this->listViews($pdo);

        // Stage 1: base tables (schema + data in row-chunks).
        if ($stage === 'tables') {
            $schema = fopen($schemaFile, $tableIndex === 0 && $offset === 0 ? 'w' : 'a');
            $data = fopen($dataFile, $tableIndex === 0 && $offset === 0 ? 'w' : 'a');
            if ($schema === false || $data === false) {
                return ['ok' => false, 'done' => true, 'error' => 'Unable to open dump files.'];
            }
            if ($tableIndex === 0 && $offset === 0) {
                fwrite($schema, "-- TropaTT DB schema backup ({$jobId})\n");
                fwrite($data, "-- TropaTT DB data backup ({$jobId})\n");
            }

            $tableCount = count($tables);
            $rowsThisRequest = 0;
            while ($tableIndex < $tableCount) {
                $table = $tables[$tableIndex];
                if ($offset === 0) {
                    $create = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM);
                    $createSql = is_array($create) ? (string)($create[1] ?? '') : '';
                    if ($createSql === '') {
                        fclose($schema);
                        fclose($data);
                        return ['ok' => false, 'done' => true, 'error' => 'Unable to read schema for table: ' . $table];
                    }
                    $this->writeStatement($schema, "DROP TABLE IF EXISTS `{$table}`;");
                    $this->writeStatement($schema, $createSql . ';');
                }
                $remainingRows = max(1, $maxRows - $rowsThisRequest);
                $result = $this->dumpTableData($pdo, $data, $table, $offset, $remainingRows, $budget);
                $rowsThisRequest += $result['fetched'];
                $rowsDone += $result['fetched'];
                $offset += $result['fetched'];
                // A SELECT with LIMIT returns exactly rowLimit rows when the
                // table has more, so hitting the row budget is indistinguishable
                // from the end of the table: resume with an offset cut. The
                // next request then finds 0 rows and advances (harmless extra
                // step when the table really ended exactly at the limit).
                $cut = !$result['done'] || $rowsThisRequest >= $maxRows;
                if ($cut) {
                    fclose($schema);
                    fclose($data);
                    return $this->backupContinue('tables', [
                        'table_index' => $tableIndex, 'offset' => $offset, 'rows_done' => $rowsDone,
                        'views_done' => $viewsDone, 'views_dumped' => $viewsDumped,
                        'triggers_done' => $triggersDone, 'triggers_written' => $triggersWritten,
                    ], $tableCount, $views, $rowsDone);
                }
                $tableIndex++;
                $offset = 0;
            }
            fclose($schema);
            fclose($data);
            $stage = 'views';
        }

        // Stage 2: views after tables so dependencies resolve.
        if ($stage === 'views') {
            $schema = fopen($schemaFile, 'a');
            if ($schema === false) {
                return ['ok' => false, 'done' => true, 'error' => 'Unable to open schema dump for views.'];
            }
            $viewCount = count($views);
            while ($viewsDone < $viewCount) {
                if ($budget !== null && $budget->exhausted()) {
                    break;
                }
                $view = $views[$viewsDone];
                $create = $pdo->query('SHOW CREATE VIEW `' . $view . '`')->fetch(PDO::FETCH_NUM);
                $createSql = is_array($create) ? (string)($create[1] ?? '') : '';
                if ($createSql !== '') {
                    $this->writeStatement($schema, "DROP VIEW IF EXISTS `{$view}`;");
                    $this->writeStatement($schema, $this->stripDefiner($createSql) . ';');
                    $viewsDumped++;
                }
                $viewsDone++;
            }
            fclose($schema);
            if ($viewsDone < $viewCount) {
                return $this->backupContinue('views', [
                    'table_index' => $tableIndex, 'offset' => $offset, 'rows_done' => $rowsDone,
                    'views_done' => $viewsDone, 'views_dumped' => $viewsDumped,
                    'triggers_done' => $triggersDone, 'triggers_written' => $triggersWritten,
                ], count($tables), $views, $rowsDone);
            }
            $stage = 'triggers';
        }

        // Stage 3: triggers (last, and only after data is dumped so they are
        // not present during the dump; restore replays them after data too).
        if ($stage === 'triggers') {
            $result = $this->dumpTriggers($pdo, $triggersFile, $triggersDone, $budget, $triggersWritten);
            if ($result === null) {
                return ['ok' => false, 'done' => true, 'error' => 'Unable to dump database triggers.'];
            }
            $triggersDone = $result['processed'];
            $triggersWritten = $result['written'];
            if (!$result['done']) {
                return $this->backupContinue('triggers', [
                    'table_index' => $tableIndex, 'offset' => $offset, 'rows_done' => $rowsDone,
                    'views_done' => $viewsDone, 'views_dumped' => $viewsDumped,
                    'triggers_done' => $triggersDone, 'triggers_written' => $triggersWritten,
                ], count($tables), $views, $rowsDone);
            }
        }

        // Final stage: record SHA-256 of every dump file in the manifest so
        // restore() can verify the backup is intact BEFORE dropping the live
        // schema (hash_file() streams internally, so this stays memory-flat
        // even for hundreds-of-MB data dumps).
        $manifest = [
            'ok' => true,
            'driver' => 'mysql',
            'job_id' => $jobId,
            'created_at' => gmdate('c'),
            'tables' => count($tables),
            // Count only views/triggers actually dumped (DDL written): the
            // restore verification compares these against the replayed schema.
            'views' => $viewsDumped,
            'triggers' => $triggersWritten,
            'rows' => $rowsDone,
            'schema_file' => 'db/schema.sql',
            'data_file' => 'db/data.sql',
            'triggers_file' => 'db/triggers.sql',
            'schema_sha256' => hash_file('sha256', $schemaFile) ?: '',
            'data_sha256' => hash_file('sha256', $dataFile) ?: '',
            'triggers_sha256' => is_file($triggersFile) ? (hash_file('sha256', $triggersFile) ?: '') : '',
        ];
        file_put_contents($dbDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return ['ok' => true, 'done' => true] + $manifest;
    }

    /**
     * @param array<string,int> $cursor
     */
    private function backupContinue(string $stage, array $cursor, int $tables, array $views, int $rowsDone): array
    {
        return [
            'ok' => false,
            'done' => false,
            'skipped' => true,
            'reason' => 'step_budget_exhausted',
            'stage' => $stage,
            'cursor' => $cursor + ['stage' => $stage],
            'tables_done' => (int)($cursor['table_index'] ?? 0),
            'tables_total' => $tables,
            'views_total' => count($views),
            'rows_done' => $rowsDone,
        ];
    }

    /**
     * Restore the database from a backup directory created by backup(),
     * optionally resuming from $cursor with a bounded amount of work per call.
     *
     * Restoring a large database cannot fit in one shared-hosting request, so
     * restore runs as a step machine: the integrity gate runs once on the
     * first call (before anything destructive), then views/tables are dropped
     * and schema/data/triggers replayed in bounded chunks. Statement chunks
     * resume by BYTE OFFSET inside each dump file (see execStatementsFrom), so
     * the total work stays linear no matter how many requests it takes.
     *
     * NOTE: this drops every table/view in the connected database and replays
     * the backup - it assumes the CRM owns its MySQL database (the normal
     * shared-hosting layout). Views/triggers carry a DEFINER clause from
     * SHOW CREATE VIEW/TRIGGER; a same-user restore (the common case) is
     * fine, but on hosts where the definer user differs, those DDL statements
     * can fail and are reported in the restore report.
     *
     * @param array{stage?:string,index?:int,pos?:int}|null $cursor
     * @return array{ok:bool,done:bool,cursor?:array<string,int>,driver?:string,tables?:int,views?:int,triggers?:int,rows?:int,error?:string,skipped?:bool,reason?:string}
     */
    public function restore(string $backupDir, ?array $cursor = null, ?\Updater\Util\WorkBudget $budget = null, int $maxStatements = 500): array
    {
        @set_time_limit(0);

        $dbDir = $backupDir . '/db';
        $manifestFile = $dbDir . '/manifest.json';
        if (!is_file($manifestFile)) {
            return ['ok' => false, 'done' => true, 'skipped' => true, 'reason' => 'No database backup manifest found.'];
        }
        $manifest = json_decode((string)file_get_contents($manifestFile), true);
        $driver = is_array($manifest) ? (string)($manifest['driver'] ?? 'mysql') : 'mysql';

        if ($driver === 'sqlite') {
            $report = $this->restoreSqlite($backupDir);
            $report['done'] = true;
            return $report;
        }
        if ($driver !== 'mysql') {
            return ['ok' => false, 'done' => true, 'skipped' => true, 'reason' => 'Unsupported backup driver: ' . $driver];
        }

        $schemaFile = $dbDir . '/schema.sql';
        $dataFile = $dbDir . '/data.sql';
        $triggersFile = $dbDir . '/triggers.sql';
        if (!is_file($schemaFile) || !is_file($dataFile)) {
            return ['ok' => false, 'done' => true, 'error' => 'Backup dump files missing.'];
        }

        $stage = (string)($cursor['stage'] ?? 'integrity');

        // Integrity gate BEFORE any destructive step, on the FIRST call only
        // (subsequent steps already passed it). Verify the dump files match
        // the hashes recorded at backup time (with a structural fallback for
        // legacy backups that predate hashing). A corrupt or truncated dump
        // must never be replayed over the live database - an interrupted
        // restore that has already dropped the schema is unrecoverable.
        if ($stage === 'integrity') {
            $integrityError = $this->verifyDumpIntegrity($dbDir, is_array($manifest) ? $manifest : []);
            if ($integrityError !== null) {
                return [
                    'ok' => false,
                    'done' => true,
                    'error' => 'Database backup integrity check failed: ' . $integrityError
                        . '. Restore aborted before any change; the live database was left untouched.',
                ];
            }
            $stage = 'drop_views';
        }

        $conn = Connection::open($this->basePath);
        if ($conn['driver'] !== 'mysql') {
            return ['ok' => false, 'done' => true, 'error' => 'Current DB driver (' . $conn['driver'] . ') does not match backup driver (mysql).'];
        }
        $pdo = $conn['pdo'];

        // FOREIGN_KEY_CHECKS is per-connection; every step sets it off before
        // dropping/replaying and only the final verify stage turns it back on.
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            $index = (int)($cursor['index'] ?? 0);
            $pos = (int)($cursor['pos'] ?? 0);

            // Drop views and tables currently present so tables created by the
            // migration are removed too - a full rollback must restore the
            // exact pre-update schema.
            if ($stage === 'drop_views') {
                $views = $this->listViews($pdo);
                while ($index < count($views) && ($budget === null || !$budget->exhausted())) {
                    $pdo->exec('DROP VIEW IF EXISTS `' . $views[$index] . '`');
                    $index++;
                }
                if ($index < count($views)) {
                    return $this->restoreContinue('drop_views', $index, 0);
                }
                $stage = 'drop_tables';
                $index = 0;
            }

            if ($stage === 'drop_tables') {
                $tables = $this->listBaseTables($pdo);
                while ($index < count($tables) && ($budget === null || !$budget->exhausted())) {
                    $pdo->exec('DROP TABLE IF EXISTS `' . $tables[$index] . '`');
                    $index++;
                }
                if ($index < count($tables)) {
                    return $this->restoreContinue('drop_tables', $index, 0);
                }
                $stage = 'schema';
                $index = 0;
                $pos = 0;
            }

            if ($stage === 'schema') {
                $result = $this->execStatementsFrom($pdo, $schemaFile, $pos, $maxStatements, $budget);
                if (!$result['eof']) {
                    return $this->restoreContinue('schema', $index, $result['pos']);
                }
                $stage = 'data';
                $index = 0;
                $pos = 0;
            }

            if ($stage === 'data') {
                $result = $this->execStatementsFrom($pdo, $dataFile, $pos, $maxStatements, $budget);
                if (!$result['eof']) {
                    return $this->restoreContinue('data', $index, $result['pos']);
                }
                $stage = 'triggers';
                $index = 0;
                $pos = 0;
            }

            if ($stage === 'triggers') {
                // Triggers last so they do not fire during data restore.
                if (is_file($triggersFile)) {
                    $result = $this->execStatementsFrom($pdo, $triggersFile, $pos, $maxStatements, $budget);
                    if (!$result['eof']) {
                        return $this->restoreContinue('triggers', $index, $result['pos']);
                    }
                }
                $stage = 'verify';
                $index = 0;
            }

            if ($stage === 'verify') {
                // Post-restore verification: the replayed schema must match the
                // manifest. A partial restore (a statement the host silently
                // skipped, a definer mismatch, a packet limit) must NEVER be
                // reported as success - the admin would otherwise be told the
                // rollback worked while the CRM was left half-restored.
                //
                // >= is used instead of === on purpose: restore drops every
                // view/table first and replays only what the dump contains, so
                // the restored count can never EXCEED the manifest. A legacy
                // backup made by an older build recorded manifest['views'] as
                // the raw SHOW FULL TABLES list count (which could include a
                // view whose SHOW CREATE VIEW returned empty and was skipped in
                // the dump); >= stays correct under both old and new semantics
                // while still catching any partial restore (fewer than expected).
                $restoredTables = count($this->listBaseTables($pdo));
                $restoredViews = count($this->listViews($pdo));
                $expectedTables = (int)($manifest['tables'] ?? 0);
                $expectedViews = (int)($manifest['views'] ?? 0);
                if ($restoredTables < $expectedTables || $restoredViews < $expectedViews) {
                    return [
                        'ok' => false,
                        'done' => true,
                        'error' => "Post-restore verification failed: expected at least {$expectedTables} tables / {$expectedViews} views, "
                            . "found {$restoredTables} / {$restoredViews}. The database may be partially restored; "
                            . 'retry the rollback or restore from the backup.',
                    ];
                }
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
                return [
                    'ok' => true,
                    'done' => true,
                    'driver' => 'mysql',
                    'integrity' => 'verified',
                    'verified_tables' => $restoredTables,
                    'verified_views' => $restoredViews,
                    'tables' => (int)($manifest['tables'] ?? 0),
                    'views' => (int)($manifest['views'] ?? 0),
                    'triggers' => (int)($manifest['triggers'] ?? 0),
                    'rows' => (int)($manifest['rows'] ?? 0),
                ];
            }
        } finally {
            // Safety net: if we are leaving without reaching verify (an error
            // propagated above), re-enable FK checks on this connection anyway.
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }

        return ['ok' => false, 'done' => true, 'error' => 'Unexpected restore state: ' . $stage];
    }

    /**
     * @return array{ok:bool,done:bool,stage:string,cursor:array{stage:string,index:int,pos:int}}
     */
    private function restoreContinue(string $stage, int $index, int $pos): array
    {
        return [
            'ok' => false,
            'done' => false,
            'skipped' => true,
            'reason' => 'step_budget_exhausted',
            'stage' => $stage,
            'cursor' => ['stage' => $stage, 'index' => $index, 'pos' => $pos],
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

    private function dumpTableData(PDO $pdo, $handle, string $table, int $offset, int $rowLimit, ?\Updater\Util\WorkBudget $budget): array
    {
        $stmt = $pdo->query('SELECT * FROM `' . $table . '` LIMIT ' . max(1, $rowLimit) . ' OFFSET ' . max(0, $offset));
        if ($stmt === false) {
            return ['fetched' => 0, 'done' => true];
        }
        $rows = 0;
        $batch = [];
        $batchBytes = 0;
        $batchSize = 100;
        // Shared hosting often sets a small max_allowed_packet (as low as 1MB
        // on some hosts). A 100-row batch with TEXT/JSON values can exceed it,
        // and restore would then fail with "packet too large" AFTER the tables
        // have been dropped. Flush by BOTH row count and accumulated bytes so
        // every INSERT statement stays well under common packet limits.
        $maxBatchBytes = 512 * 1024; // 512 KiB, safely under 1MB+ limits

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
            $line = '(' . implode(', ', $values) . ')';
            $batch[] = $line;
            // A single row larger than max_allowed_packet is not split here
            // on purpose: the dump is restored to the same MySQL server that
            // accepted the row originally, so the packet limit was already
            // large enough to store it. Byte-flushing only guards the
            // multi-row batch case (100 x KB-sized TEXT/JSON values).
            $batchBytes += strlen($line) + 2;
            if (count($batch) >= $batchSize || $batchBytes >= $maxBatchBytes) {
                $this->writeStatement($handle, 'INSERT INTO `' . $table . '` VALUES ' . implode(', ', $batch) . ';');
                $batch = [];
                $batchBytes = 0;
            }
            $rows++;
            if ($budget !== null && $budget->exhausted()) {
                break;
            }
        }
        if ($batch !== []) {
            $this->writeStatement($handle, 'INSERT INTO `' . $table . '` VALUES ' . implode(', ', $batch) . ';');
        }

        // done means this table's rows are fully dumped in this request. A
        // budget cut leaves rows unread, so the next request resumes with
        // LIMIT/OFFSET from the current position.
        return ['fetched' => $rows, 'done' => $budget !== null && $budget->exhausted() ? false : true];
    }

    /**
     * Strip the DEFINER clause from a SHOW CREATE VIEW/TRIGGER statement so
     * the dump can be restored by any MySQL user (mysqldump --skip-definer
     * equivalent). Without this, a view/trigger whose definer does not match
     * the restore account requires SUPER privilege and the restore fails
     * mid-way - after the schema was already dropped.
     */
    private function stripDefiner(string $sql): string
    {
        return (string)preg_replace('/\s+DEFINER=`[^`]+`@`[^`]+`/i', '', $sql);
    }

    /**
     * Dump CREATE TRIGGER statements (they are not part of SHOW CREATE TABLE),
     * optionally resuming from $cursorDone with a time budget.
     *
     * @return array{written:int,done:bool}|null null on error
     */
    private function dumpTriggers(PDO $pdo, string $triggersFile, int $cursorDone = 0, ?\Updater\Util\WorkBudget $budget = null, int $cursorWritten = 0): ?array
    {
        $handle = fopen($triggersFile, $cursorDone > 0 ? 'a' : 'w');
        if ($handle === false) {
            return null;
        }
        if ($cursorDone === 0) {
            fwrite($handle, "-- TropaTT DB triggers backup\n");
        }

        $rows = $pdo->query('SHOW TRIGGERS');
        $names = [];
        if ($rows !== false) {
            foreach ($rows->fetchAll(PDO::FETCH_NUM) as $row) {
                $names[] = (string)($row[0] ?? '');
            }
        }
        $processed = $cursorDone;
        $written = (int)($cursorWritten ?? 0);
        $total = count($names);
        while ($processed < $total) {
            if ($budget !== null && $budget->exhausted()) {
                break;
            }
            $name = $names[$processed];
            $stmt = $pdo->query('SHOW CREATE TRIGGER `' . $name . '`');
            if ($stmt === false) {
                $processed++;
                continue;
            }
            $row = $stmt->fetch(PDO::FETCH_NUM);
            $sql = is_array($row) ? (string)($row[2] ?? '') : '';
            if ($sql !== '') {
                $this->writeStatement($handle, "DROP TRIGGER IF EXISTS `{$name}`;");
                $this->writeStatement($handle, $this->stripDefiner($sql) . ';');
                $written++;
            }
            $processed++;
        }
        fclose($handle);

        return ['written' => $written, 'processed' => $processed, 'done' => $processed >= $total];
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
     * Execute a dump file statement by statement, starting at a byte offset
     * and stopping after $maxStatements statements or when the budget runs
     * out. Returns the byte offset to resume from, so a huge data.sql (300MB+
     * of INSERTs for 1M+ rows) is replayed across many requests in linear
     * total time.
     *
     * STREAMS the file line by line (fgets) instead of loading it into
     * memory: the dump must never be read with file_get_contents() +
     * preg_split(), which blows the memory_limit with an UNCATCHABLE fatal
     * error mid-restore - after the tables have already been dropped - leaving
     * the CRM database empty and maintenance mode stuck on. Each statement is
     * preceded by its own marker comment line, so the split is safe for
     * statements containing ';' and newlines inside string literals or
     * compound trigger bodies.
     *
     * @return array{executed:int,pos:int,eof:bool}
     */
    private function execStatementsFrom(PDO $pdo, string $file, int $startPos, int $maxStatements, ?\Updater\Util\WorkBudget $budget): array
    {
        $handle = @fopen($file, 'r');
        if ($handle === false) {
            // Treat an unreadable file as fully consumed; the integrity gate
            // already rejected a MISSING file before the first destructive
            // step, so this can only happen mid-restore and must not loop.
            return ['executed' => 0, 'pos' => 0, 'eof' => true];
        }
        if ($startPos > 0) {
            fseek($handle, $startPos);
        }
        $statement = '';
        $executed = 0;
        $pos = $startPos;
        // 1MB read buffer: keeps memory flat even for multi-MB INSERT lines
        // (a 100-row batch with TEXT/JSON values can be hundreds of KB). A
        // line longer than the buffer is reassembled across fgets() calls,
        // so correctness does not depend on the buffer size.
        while (($line = fgets($handle, 1048576)) !== false) {
            if (rtrim($line) === '-- @@TROPA_SQL@@') {
                $this->execStatement($pdo, $statement);
                $statement = '';
                $executed++;
                // Record the resume position AFTER the marker line: this is
                // exactly where the next statement begins. If we cut here the
                // accumulated buffer is empty, so no statement is lost.
                $pos = ftell($handle);
                if ($executed >= $maxStatements || ($budget !== null && $budget->exhausted())) {
                    fclose($handle);
                    return ['executed' => $executed, 'pos' => $pos, 'eof' => false];
                }
                continue;
            }
            $statement .= $line;
        }
        // Last statement (its terminating marker is EOF).
        $this->execStatement($pdo, $statement);
        $executed++;
        $pos = ftell($handle);
        fclose($handle);
        return ['executed' => $executed, 'pos' => $pos, 'eof' => true];
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
            'file_sha256' => hash_file('sha256', $target) ?: '',
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
        // Verify the copied sqlite file matches the hash recorded at backup
        // time before overwriting the live database file.
        $manifestFile = $dbDir . '/manifest.json';
        $manifest = is_file($manifestFile) ? json_decode((string)file_get_contents($manifestFile), true) : null;
        $expected = is_array($manifest) ? (string)($manifest['file_sha256'] ?? '') : '';
        if ($expected !== '' && hash_file('sha256', $source) !== $expected) {
            return ['ok' => false, 'error' => 'SQLite backup integrity check failed: crm.sqlite checksum mismatch.'];
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
