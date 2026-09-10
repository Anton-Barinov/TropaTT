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
 *
 * Per-table backup format (default since this version):
 *   db/schema.sql          — CREATE TABLE statements (all tables)
 *   db/tables/{name}.sql   — INSERT statements for each table individually
 *   db/triggers.sql        — CREATE TRIGGER statements
 *   db/manifest.json       — metadata, per-table stats, SHA-256 hashes
 *
 * Legacy format (single data.sql) is still supported for restore.
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
     * is written. Each table produces its own file under db/tables/, so a
     * huge table does not block the backup of smaller ones, and individual
     * tables can be compressed or verified independently.
     *
     * @param array{stage?:string,table_index?:int,offset?:int,rows_done?:int,views_done?:int,triggers_done?:int}|null $cursor
     * @return array{ok:bool,done:bool,cursor?:array<string,int>,driver?:string,tables?:int,views?:int,triggers?:int,rows?:int,error?:string,skipped?:bool,reason?:string}
     */
    public function backup(string $backupDir, string $jobId, ?array $cursor = null, ?\Updater\Util\WorkBudget $budget = null, int $maxRows = 50000): array
    {
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
        $tablesDir = $dbDir . '/tables';
        if (!is_dir($dbDir) && !@mkdir($dbDir, 0775, true) && !is_dir($dbDir)) {
            return ['ok' => false, 'done' => true, 'error' => 'Unable to create db backup directory: ' . $dbDir];
        }
        if (!is_dir($tablesDir) && !@mkdir($tablesDir, 0775, true) && !is_dir($tablesDir)) {
            return ['ok' => false, 'done' => true, 'error' => 'Unable to create tables backup directory: ' . $tablesDir];
        }

        $schemaFile = $dbDir . '/schema.sql';
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

        // Stage 1: base tables (schema to schema.sql, data to per-table files).
        if ($stage === 'tables') {
            $schema = fopen($schemaFile, $tableIndex === 0 && $offset === 0 ? 'w' : 'a');
            if ($schema === false) {
                return ['ok' => false, 'done' => true, 'error' => 'Unable to open schema dump file.'];
            }
            if ($tableIndex === 0 && $offset === 0) {
                fwrite($schema, "-- TropaTT DB schema backup ({$jobId})\n");
            }

            $tableCount = count($tables);
            $rowsThisRequest = 0;
            while ($tableIndex < $tableCount) {
                $table = $tables[$tableIndex];

                // Schema: always write DROP + CREATE into schema.sql.
                if ($offset === 0) {
                    $create = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM);
                    $createSql = is_array($create) ? (string)($create[1] ?? '') : '';
                    if ($createSql === '') {
                        fclose($schema);
                        return ['ok' => false, 'done' => true, 'error' => 'Unable to read schema for table: ' . $table];
                    }
                    $this->writeStatement($schema, "DROP TABLE IF EXISTS `{$table}`;");
                    $this->writeStatement($schema, $createSql . ';');
                }

                // Data: per-table file under db/tables/{name}.sql
                $tableFile = $tablesDir . '/' . $table . '.sql';
                $isNewTableFile = $tableIndex !== (int)($cursor['table_index'] ?? -1) || $offset === 0;
                $data = fopen($tableFile, ($isNewTableFile && $offset === 0) ? 'w' : 'a');
                if ($data === false) {
                    fclose($schema);
                    return ['ok' => false, 'done' => true, 'error' => 'Unable to open table data file: ' . $tableFile];
                }
                if ($offset === 0) {
                    fwrite($data, "-- TropaTT DB table backup: {$table} ({$jobId})\n");
                }

                $remainingRows = max(1, $maxRows - $rowsThisRequest);
                $result = $this->dumpTableData($pdo, $data, $table, $offset, $remainingRows, $budget);
                fclose($data);

                $rowsThisRequest += $result['fetched'];
                $rowsDone += $result['fetched'];
                $offset += $result['fetched'];

                $cut = !$result['done'] || $rowsThisRequest >= $maxRows;
                if ($cut) {
                    fclose($schema);
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

        // Final stage: record SHA-256 of every dump file in the manifest.
        // Per-table files get individual hashes + row counts.
        $tableManifests = [];
        foreach ($tables as $table) {
            $tableFile = $tablesDir . '/' . $table . '.sql';
            $tableManifests[$table] = [
                'file' => 'db/tables/' . $table . '.sql',
                'sha256' => is_file($tableFile) ? (hash_file('sha256', $tableFile) ?: '') : '',
                'size_bytes' => is_file($tableFile) ? filesize($tableFile) : 0,
            ];
        }

        $manifest = [
            'ok' => true,
            'driver' => 'mysql',
            'job_id' => $jobId,
            'created_at' => gmdate('c'),
            'format' => 'per_table',
            'tables' => count($tables),
            'views' => $viewsDumped,
            'triggers' => $triggersWritten,
            'rows' => $rowsDone,
            'schema_file' => 'db/schema.sql',
            'triggers_file' => 'db/triggers.sql',
            'schema_sha256' => hash_file('sha256', $schemaFile) ?: '',
            'triggers_sha256' => is_file($triggersFile) ? (hash_file('sha256', $triggersFile) ?: '') : '',
            'table_files' => $tableManifests,
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
     * Supports two backup formats:
     * - Per-table (format=per_table): each table in db/tables/{name}.sql
     * - Legacy: all data in db/data.sql (single file)
     *
     * The restore drops every table/view in the connected database and replays
     * the backup — it assumes the CRM owns its MySQL database.
     *
     * @param array{stage?:string,index?:int,pos?:int,table_file?:string}|null $cursor
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
        $triggersFile = $dbDir . '/triggers.sql';
        $isPerTable = (string)($manifest['format'] ?? '') === 'per_table'
            || is_dir($dbDir . '/tables');

        // Per-table format uses db/tables/{name}.sql; legacy uses db/data.sql.
        if ($isPerTable) {
            $dataFile = null; // unused in per-table mode
        } else {
            $dataFile = $dbDir . '/data.sql';
        }

        if (!is_file($schemaFile)) {
            return ['ok' => false, 'done' => true, 'error' => 'Backup schema dump file missing.'];
        }
        if (!$isPerTable && ($dataFile === null || !is_file($dataFile))) {
            return ['ok' => false, 'done' => true, 'error' => 'Backup data dump file missing.'];
        }

        $stage = (string)($cursor['stage'] ?? 'integrity');

        // Integrity gate BEFORE any destructive step.
        if ($stage === 'integrity') {
            $integrityError = $this->verifyDumpIntegrity($dbDir, is_array($manifest) ? $manifest : [], $isPerTable);
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

        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            $index = (int)($cursor['index'] ?? 0);
            $pos = (int)($cursor['pos'] ?? 0);

            // Drop views currently present.
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

            // Drop tables currently present.
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

            // Replay schema (CREATE TABLE + views).
            if ($stage === 'schema') {
                $result = $this->execStatementsFrom($pdo, $schemaFile, $pos, $maxStatements, $budget);
                if (!$result['eof']) {
                    return $this->restoreContinue('schema', $index, $result['pos']);
                }
                $stage = 'data';
                $index = 0;
                $pos = 0;
            }

            // Replay data.
            if ($stage === 'data') {
                if ($isPerTable) {
                    // Per-table: iterate through db/tables/{name}.sql files.
                    $tableFiles = $this->listTableFiles($dbDir);
                    $tableFileIndex = (int)($cursor['table_file_index'] ?? 0);
                    $result = $this->restorePerTable($pdo, $tableFiles, $tableFileIndex, $pos, $maxStatements, $budget);
                    if (!$result['eof']) {
                        $cursorData = [
                            'stage' => 'data',
                            'index' => $index,
                            'table_file_index' => $result['table_file_index'],
                            'pos' => $result['pos'],
                        ];
                        return [
                            'ok' => false,
                            'done' => false,
                            'skipped' => true,
                            'reason' => 'step_budget_exhausted',
                            'stage' => 'data',
                            'cursor' => $cursorData,
                        ];
                    }
                } else {
                    // Legacy: single data.sql.
                    $result = $this->execStatementsFrom($pdo, $dataFile, $pos, $maxStatements, $budget);
                    if (!$result['eof']) {
                        return $this->restoreContinue('data', $index, $result['pos']);
                    }
                }
                $stage = 'triggers';
                $index = 0;
                $pos = 0;
            }

            // Replay triggers.
            if ($stage === 'triggers') {
                if (is_file($triggersFile)) {
                    $result = $this->execStatementsFrom($pdo, $triggersFile, $pos, $maxStatements, $budget);
                    if (!$result['eof']) {
                        return $this->restoreContinue('triggers', $index, $result['pos']);
                    }
                }
                $stage = 'verify';
                $index = 0;
            }

            // Post-restore verification.
            if ($stage === 'verify') {
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
                    'format' => $isPerTable ? 'per_table' : 'legacy',
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
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }

        return ['ok' => false, 'done' => true, 'error' => 'Unexpected restore state: ' . $stage];
    }

    /**
     * Restore data from per-table files, one table at a time.
     *
     * @return array{eof:bool,table_file_index:int,pos:int}
     */
    private function restorePerTable(PDO $pdo, array $tableFiles, int $startTableIndex, int $startPos, int $maxStatements, ?\Updater\Util\WorkBudget $budget): array
    {
        $tableFileIndex = $startTableIndex;
        $pos = $startPos;
        $total = count($tableFiles);

        while ($tableFileIndex < $total) {
            $file = $tableFiles[$tableFileIndex];
            $result = $this->execStatementsFrom($pdo, $file, $pos, $maxStatements, $budget);
            if (!$result['eof']) {
                // Budget exhausted mid-table: resume from this position.
                return ['eof' => false, 'table_file_index' => $tableFileIndex, 'pos' => $result['pos']];
            }
            // This table file fully consumed, move to the next.
            $tableFileIndex++;
            $pos = 0;

            if ($budget !== null && $budget->exhausted()) {
                return ['eof' => false, 'table_file_index' => $tableFileIndex, 'pos' => 0];
            }
        }

        return ['eof' => true, 'table_file_index' => $total, 'pos' => 0];
    }

    /**
     * @return list<string> sorted table SQL file paths
     */
    private function listTableFiles(string $dbDir): array
    {
        $tablesDir = $dbDir . '/tables';
        if (!is_dir($tablesDir)) {
            return [];
        }
        $files = glob($tablesDir . '/*.sql');
        if ($files === false) {
            return [];
        }
        sort($files, SORT_STRING);
        return $files;
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
     * Supports both per-table format (table_files in manifest) and legacy
     * single-file format (data_sha256 in manifest).
     *
     * @return string|null error message when the dump is corrupt, null when OK
     */
    private function verifyDumpIntegrity(string $dbDir, array $manifest, bool $isPerTable): ?string
    {
        $schemaFile = $dbDir . '/schema.sql';
        $triggersFile = $dbDir . '/triggers.sql';

        // Schema + triggers integrity (shared by both formats).
        $schemaSha = (string)($manifest['schema_sha256'] ?? '');
        if ($schemaSha !== '') {
            if (!is_file($schemaFile) || hash_file('sha256', $schemaFile) !== $schemaSha) {
                return 'schema.sql checksum mismatch (corrupt or missing backup)';
            }
        } else {
            // Legacy fallback: structural check.
            if (!is_file($schemaFile) || filesize($schemaFile) === 0) {
                return 'schema.sql is missing or empty';
            }
            if (!str_starts_with($this->firstLine($schemaFile), '-- TropaTT DB schema backup')) {
                return 'schema.sql has an unexpected header (not a TropaTT dump)';
            }
        }

        $triggersSha = (string)($manifest['triggers_sha256'] ?? '');
        if ($triggersSha !== '') {
            if (!is_file($triggersFile)) {
                return 'triggers.sql is missing (corrupt or modified backup)';
            }
            if (hash_file('sha256', $triggersFile) !== $triggersSha) {
                return 'triggers.sql checksum mismatch (corrupt or modified backup)';
            }
        }

        if ($isPerTable) {
            // Per-table format: verify each table file.
            $tableFiles = $manifest['table_files'] ?? [];
            if (!is_array($tableFiles) || $tableFiles === []) {
                // No per-table manifest entries — check if the directory has files.
                $actualFiles = $this->listTableFiles($dbDir);
                if ($actualFiles === []) {
                    return 'No table data files found in db/tables/ (backup is empty or corrupt)';
                }
                // Directory has files but manifest doesn't list them — allow
                // (legacy manifest without per-table hashes).
                return null;
            }
            foreach ($tableFiles as $tableName => $meta) {
                $file = $dbDir . '/tables/' . $tableName . '.sql';
                if (!is_file($file)) {
                    return "Table file missing: tables/{$tableName}.sql (corrupt or incomplete backup)";
                }
                $expectedSha = (string)($meta['sha256'] ?? '');
                if ($expectedSha !== '' && hash_file('sha256', $file) !== $expectedSha) {
                    return "Table file checksum mismatch: tables/{$tableName}.sql (corrupt or modified backup)";
                }
            }
        } else {
            // Legacy format: verify data.sql.
            $dataFile = $dbDir . '/data.sql';
            $dataSha = (string)($manifest['data_sha256'] ?? '');
            if ($dataSha !== '') {
                if (!is_file($dataFile) || hash_file('sha256', $dataFile) !== $dataSha) {
                    return 'data.sql checksum mismatch (corrupt or missing backup)';
                }
            } else {
                if (!is_file($dataFile) || filesize($dataFile) === 0) {
                    return 'data.sql is missing or empty';
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
                if ((int)($manifest['rows'] ?? 0) > 0 && $this->countStatementMarkers($dataFile, 1) < 1) {
                    return 'data.sql is truncated (no INSERT statements despite recorded rows)';
                }
            }
        }

        return null;
    }

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
        $maxBatchBytes = 512 * 1024; // 512 KiB

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

        return ['fetched' => $rows, 'done' => $budget !== null && $budget->exhausted() ? false : true];
    }

    private function stripDefiner(string $sql): string
    {
        return (string)preg_replace('/\s+DEFINER=`[^`]+`@`[^`]+`/i', '', $sql);
    }

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

    private function writeStatement($handle, string $sql): void
    {
        fwrite($handle, "-- @@TROPA_SQL@@\n" . $sql . "\n");
    }

    /**
     * Execute a dump file statement by statement, starting at a byte offset
     * and stopping after $maxStatements statements or when the budget runs
     * out.
     *
     * @return array{executed:int,pos:int,eof:bool}
     */
    private function execStatementsFrom(PDO $pdo, string $file, int $startPos, int $maxStatements, ?\Updater\Util\WorkBudget $budget): array
    {
        $handle = @fopen($file, 'r');
        if ($handle === false) {
            return ['executed' => 0, 'pos' => 0, 'eof' => true];
        }
        if ($startPos > 0) {
            fseek($handle, $startPos);
        }
        $statement = '';
        $executed = 0;
        $pos = $startPos;
        while (($line = fgets($handle, 1048576)) !== false) {
            if (rtrim($line) === '-- @@TROPA_SQL@@') {
                $this->execStatement($pdo, $statement);
                $statement = '';
                $executed++;
                $pos = ftell($handle);
                if ($executed >= $maxStatements || ($budget !== null && $budget->exhausted())) {
                    fclose($handle);
                    return ['executed' => $executed, 'pos' => $pos, 'eof' => false];
                }
                continue;
            }
            $statement .= $line;
        }
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
