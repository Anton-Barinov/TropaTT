<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\System\Library\Config;
use Api\System\Library\Support\AppLog;
use PDO;

final class LogRotationService
{
    public const DEFAULT_MAX_FILE_SIZE = 10485760; // 10 MB
    public const DEFAULT_MAX_FILES = 5;
    public const DEFAULT_RETENTION_DAYS = 14;

    public function __construct(
        private readonly ?PDO $pdo = null,
        private readonly ?Config $config = null,
        private readonly ?string $logDir = null
    ) {
    }

    /**
     * Run full log rotation pass (both files and database records).
     *
     * @return array{files: array<string, mixed>, database: array<string, int>}
     */
    public function run(): array
    {
        $fileResult = $this->rotateLogFiles();
        $dbResult = $this->pruneDatabaseLogs();

        return [
            'files' => $fileResult,
            'database' => $dbResult,
        ];
    }

    /**
     * Rotate and compress log files in all known log directories.
     *
     * @param string|null $targetDir Specific directory to process (optional)
     * @param array<string, mixed> $options Overrides for max_bytes, max_files, compress, retention_days
     * @return array{scanned_dirs: array<int, string>, rotated: array<int, string>, compressed: array<int, string>, deleted: array<int, string>}
     */
    public function rotateLogFiles(?string $targetDir = null, array $options = []): array
    {
        $maxBytes = (int)($options['max_file_size_bytes'] ?? $this->getMaxFileSizeBytes());
        $maxFiles = (int)($options['max_files'] ?? $this->getMaxFiles());
        $compress = (bool)($options['compress'] ?? $this->shouldCompress());
        $retentionDays = (int)($options['retention_days'] ?? $this->getRetentionDays());

        $dirs = $targetDir !== null ? [$targetDir] : $this->resolveLogDirectories();

        $result = [
            'scanned_dirs' => $dirs,
            'rotated' => [],
            'compressed' => [],
            'deleted' => [],
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir) || !is_readable($dir) || !is_writable($dir)) {
                continue;
            }

            // 1. Rotate active .log files that exceed size limit
            $logFiles = glob($dir . '/*.log') ?: [];
            foreach ($logFiles as $logFile) {
                if (!is_file($logFile)) {
                    continue;
                }
                clearstatcache(true, $logFile);
                $size = (int)@filesize($logFile);
                if ($size >= $maxBytes) {
                    $rotated = self::rotateSingleFile($logFile, $maxBytes, $maxFiles, $compress);
                    if ($rotated) {
                        $result['rotated'][] = basename($logFile) . " ({$size} bytes)";
                    }
                }
            }

            // 2. Compress any uncompressed .log.[0-9]* archives if compression is enabled
            if ($compress && function_exists('gzopen')) {
                $uncompressedArchives = glob($dir . '/*.log.[0-9]*') ?: [];
                foreach ($uncompressedArchives as $archive) {
                    if (is_file($archive) && !str_ends_with($archive, '.gz')) {
                        $gzPath = $archive . '.gz';
                        if (!is_file($gzPath)) {
                            if (self::compressFile($archive, $gzPath)) {
                                @unlink($archive);
                                $result['compressed'][] = basename($archive);
                            }
                        }
                    }
                }
            }

            // 3. Purge rotated files exceeding max_files index or expired by retention days
            $allArchives = glob($dir . '/*.log.*') ?: [];
            foreach ($allArchives as $archive) {
                if (!is_file($archive)) {
                    continue;
                }
                if (preg_match('/\.log\.(\d+)(?:\.gz)?$/', $archive, $m)) {
                    $index = (int)$m[1];
                    if ($index > $maxFiles) {
                        @unlink($archive);
                        $result['deleted'][] = basename($archive) . ' (exceeded max_files)';
                        continue;
                    }
                }

                // 4. Purge rotated files older than retention days
                if ($retentionDays > 0) {
                    clearstatcache(true, $archive);
                    $mtime = (int)@filemtime($archive);
                    if ($mtime > 0 && (time() - $mtime) > ($retentionDays * 86400)) {
                        @unlink($archive);
                        $result['deleted'][] = basename($archive) . ' (expired)';
                    }
                }
            }

            // 5. Clean stale temporary .tmp or .bak log artifacts older than 24h
            $staleTemps = array_merge(glob($dir . '/*.log.*.tmp') ?: [], glob($dir . '/*.log.tmp') ?: []);
            foreach ($staleTemps as $tmp) {
                if (is_file($tmp) && (time() - (int)@filemtime($tmp)) > 86400) {
                    @unlink($tmp);
                }
            }
        }

        return $result;
    }

    /**
     * Rotate a single log file immediately if it exceeds size limit.
     */
    public static function rotateSingleFile(string $logFile, int $maxBytes = self::DEFAULT_MAX_FILE_SIZE, int $maxFiles = self::DEFAULT_MAX_FILES, bool $compress = true): bool
    {
        if (!is_file($logFile)) {
            return false;
        }

        clearstatcache(true, $logFile);
        $size = (int)@filesize($logFile);
        if ($size < $maxBytes && $maxBytes > 0) {
            return false;
        }

        // Shift existing rotated files from max down to 1
        for ($i = $maxFiles; $i >= 1; $i--) {
            $next = $i + 1;
            // Handle gzipped versions
            $currentGz = $logFile . '.' . $i . '.gz';
            $nextGz = $logFile . '.' . $next . '.gz';
            if (is_file($currentGz)) {
                if ($next > $maxFiles) {
                    @unlink($currentGz);
                } else {
                    @rename($currentGz, $nextGz);
                }
            }
            // Handle plain versions
            $currentPlain = $logFile . '.' . $i;
            $nextPlain = $logFile . '.' . $next;
            if (is_file($currentPlain)) {
                if ($next > $maxFiles) {
                    @unlink($currentPlain);
                } else {
                    @rename($currentPlain, $nextPlain);
                }
            }
        }

        // Atomically rename active log to .1
        $target1 = $logFile . '.1';
        if (!@rename($logFile, $target1)) {
            return false;
        }

        // Create new empty active log file immediately
        @touch($logFile);
        @chmod($logFile, 0664);

        // Compress .1 to .1.gz if compression is supported and enabled
        if ($compress && function_exists('gzopen')) {
            $targetGz = $logFile . '.1.gz';
            if (self::compressFile($target1, $targetGz)) {
                @unlink($target1);
            }
        }

        return true;
    }

    /**
     * Compress a file using gzip in 64KB chunks (safe for large files).
     */
    public static function compressFile(string $source, string $destination): bool
    {
        if (!is_file($source) || !function_exists('gzopen')) {
            return false;
        }

        $src = @fopen($source, 'rb');
        if (!$src) {
            return false;
        }

        $tmpDest = $destination . '.tmp.' . bin2hex(random_bytes(4));
        $dst = @gzopen($tmpDest, 'wb9');
        if (!$dst) {
            @fclose($src);
            return false;
        }

        while (!feof($src)) {
            $chunk = fread($src, 65536);
            if ($chunk === false) {
                break;
            }
            gzwrite($dst, $chunk);
        }

        @fclose($src);
        @gzclose($dst);

        if (!@rename($tmpDest, $destination)) {
            @unlink($tmpDest);
            return false;
        }

        @chmod($destination, 0664);
        return true;
    }

    /**
     * Opportunistic probabilistic database log prune for shared hosting where system cron is absent.
     * Runs with small batch and time limits so web requests are never visibly delayed.
     */
    public static function maybeOpportunisticPrune(PDO $pdo, ?Config $config = null): void
    {
        // Sampled execution: 1 in 100 web requests
        if (mt_rand(1, 100) !== 1) {
            return;
        }

        $channels = $config !== null ? (array)$config->get('logging.channels', []) : [];
        $requestLog = (string)($channels['request'] ?? '');
        $storageBase = $requestLog !== '' ? dirname($requestLog) : (string)(getenv('CRM_STORAGE_BASE') ?: dirname(__DIR__, 4) . '/storage_api/logs');
        $markerFile = $storageBase . '/.last_db_prune';

        // Check if pruned in the last 12 hours
        if (@file_exists($markerFile) && (time() - (int)@filemtime($markerFile)) < 43200) {
            return;
        }

        @touch($markerFile);

        try {
            $service = new self($pdo, $config);
            $service->pruneDatabaseLogs(['max_batches' => 4, 'max_seconds' => 2.0]);
        } catch (\Throwable $e) {
            AppLog::warning('[LogRotationService::maybeOpportunisticPrune] ' . $e->getMessage());
        }
    }

    /**
     * Prune database log tables by retention policy.
     *
     * @param array<string, mixed> $options Optional retention day overrides and execution limits
     * @return array{request_logs: int, security_logs: int, audit_logs: int, executions: int}
     */
    public function pruneDatabaseLogs(array $options = []): array
    {
        $result = [
            'request_logs' => 0,
            'security_logs' => 0,
            'audit_logs' => 0,
            'executions' => 0,
        ];

        if ($this->pdo === null) {
            return $result;
        }

        try {
            $retentionConfig = $this->loadRetentionMetadata();
            if (($retentionConfig['enabled'] ?? true) === false) {
                return $result;
            }

            $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

            $tables = [
                'request_logs' => (int)($options['request_logs_days'] ?? ($retentionConfig['request_logs_days'] ?? 14)),
                'security_logs' => (int)($options['security_logs_days'] ?? ($retentionConfig['security_logs_days'] ?? 30)),
                'audit_logs' => (int)($options['audit_logs_days'] ?? ($retentionConfig['audit_logs_days'] ?? 365)),
            ];

            foreach ($tables as $table => $days) {
                if ($days <= 0) {
                    continue;
                }
                $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * 86400));
                $deleted = $this->deleteOlderThan($table, 'created_at', $cutoff, $driver, $options);
                $result[$table] = $deleted;
            }

            // Cap maximum row count to prevent runaway table size on high-volume shared hosting
            $rowCaps = [
                'request_logs' => (int)($options['request_logs_max_rows'] ?? 100000),
                'security_logs' => (int)($options['security_logs_max_rows'] ?? 50000),
            ];
            foreach ($rowCaps as $table => $maxRows) {
                if ($maxRows > 0) {
                    $capped = $this->capTableRows($table, $maxRows, $driver, $options);
                    $result[$table] = ($result[$table] ?? 0) + $capped;
                }
            }

            // Also prune module task executions older than 30 days
            $schedulerExecutionsDays = (int)($options['module_task_executions_days'] ?? 30);
            if ($schedulerExecutionsDays > 0) {
                $cutoff = gmdate('Y-m-d H:i:s', time() - ($schedulerExecutionsDays * 86400));
                $result['executions'] = $this->deleteOlderThan('module_task_executions', 'started_at', $cutoff, $driver, $options);
            }
        } catch (\Throwable $e) {
            AppLog::error('[LogRotationService::pruneDatabaseLogs] ' . $e->getMessage());
        }

        return $result;
    }

    private function deleteOlderThan(string $table, string $column, string $cutoff, string $driver, array $options = []): int
    {
        try {
            if (!$this->tableExists($table)) {
                return 0;
            }

            if ($driver === 'sqlite') {
                $stmt = $this->pdo->prepare("DELETE FROM {$table} WHERE {$column} < :cutoff");
                $stmt->execute(['cutoff' => $cutoff]);
                return $stmt->rowCount();
            }

            $maxBatches = isset($options['max_batches']) ? (int)$options['max_batches'] : null;
            $maxSeconds = isset($options['max_seconds']) ? (float)$options['max_seconds'] : null;
            $startTime = microtime(true);

            // MySQL: batch deletes in chunks of 5000 to avoid table-lock latency
            $total = 0;
            $batch = 0;
            do {
                if ($maxSeconds !== null && (microtime(true) - $startTime) >= $maxSeconds) {
                    break;
                }
                if ($maxBatches !== null && $batch >= $maxBatches) {
                    break;
                }

                $stmt = $this->pdo->prepare("DELETE FROM {$table} WHERE {$column} < :cutoff LIMIT 5000");
                $stmt->execute(['cutoff' => $cutoff]);
                $count = $stmt->rowCount();
                $total += $count;
                $batch++;
            } while ($count >= 5000);

            return $total;
        } catch (\Throwable $e) {
            AppLog::error("[LogRotationService::deleteOlderThan] {$table}.{$column}: " . $e->getMessage());
            return 0;
        }
    }

    private function capTableRows(string $table, int $maxRows, string $driver, array $options = []): int
    {
        try {
            if (!$this->tableExists($table)) {
                return 0;
            }

            $maxBatches = isset($options['max_batches']) ? (int)$options['max_batches'] : null;
            $maxSeconds = isset($options['max_seconds']) ? (float)$options['max_seconds'] : null;
            $startTime = microtime(true);

            // Fast check: find id at offset $maxRows from the newest record
            $stmt = $this->pdo->query("SELECT id FROM {$table} ORDER BY id DESC LIMIT 1 OFFSET {$maxRows}");
            if (!$stmt) {
                return 0;
            }
            $thresholdId = $stmt->fetchColumn();
            if (!$thresholdId) {
                return 0; // Table within cap
            }

            $thresholdId = (int)$thresholdId;

            if ($driver === 'sqlite') {
                $delStmt = $this->pdo->prepare("DELETE FROM {$table} WHERE id <= :threshold_id");
                $delStmt->execute(['threshold_id' => $thresholdId]);
                return $delStmt->rowCount();
            }

            $total = 0;
            $batch = 0;

            do {
                if ($maxSeconds !== null && (microtime(true) - $startTime) >= $maxSeconds) {
                    break;
                }
                if ($maxBatches !== null && $batch >= $maxBatches) {
                    break;
                }

                $delStmt = $this->pdo->prepare("DELETE FROM {$table} WHERE id <= :threshold_id LIMIT 5000");
                $delStmt->execute(['threshold_id' => $thresholdId]);
                $count = $delStmt->rowCount();
                $total += $count;
                $batch++;
            } while ($count >= 5000);

            return $total;
        } catch (\Throwable $e) {
            AppLog::warning("[LogRotationService::capTableRows] {$table}: " . $e->getMessage());
            return 0;
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            $stmt = $this->pdo->prepare("SELECT 1 FROM {$table} LIMIT 1");
            $stmt->execute();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function loadRetentionMetadata(): array
    {
        $defaults = [
            'enabled' => true,
            'request_logs_days' => 14,
            'security_logs_days' => 30,
            'audit_logs_days' => 365,
        ];

        if ($this->pdo === null) {
            return $defaults;
        }

        try {
            if (!$this->tableExists('settings')) {
                return $defaults;
            }

            $stmt = $this->pdo->prepare("SELECT name, value FROM settings WHERE scope = 'retention'");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

            foreach ($defaults as $key => $default) {
                if (isset($rows[$key])) {
                    $raw = $rows[$key];
                    $decoded = is_string($raw) ? json_decode($raw, true) : null;
                    $val = is_array($decoded) && array_key_exists('value', $decoded)
                        ? $decoded['value']
                        : $raw;
                    $defaults[$key] = is_numeric($val) ? (int)$val : (bool)$val;
                }
            }
        } catch (\Throwable) {
            // keep defaults
        }

        return $defaults;
    }

    /**
     * Resolve all existing log directories in the current environment.
     *
     * @return array<int, string>
     */
    public function resolveLogDirectories(): array
    {
        if ($this->logDir !== null && is_dir($this->logDir)) {
            return [realpath($this->logDir) ?: $this->logDir];
        }

        $dirs = [];
        $candidates = [
            getenv('CRM_STORAGE_BASE') ? (string)getenv('CRM_STORAGE_BASE') . '/logs' : null,
            dirname(__DIR__, 3) . '/storage_api/logs',
            dirname(__DIR__, 4) . '/storage_api/logs',
            dirname(__DIR__, 3) . '/storage/logs',
            dirname(__DIR__, 4) . '/storage/logs',
            dirname(__DIR__, 3) . '/logs',
        ];

        // Also check logging channels if configured
        if ($this->config !== null) {
            $channels = (array)$this->config->get('logging.channels', []);
            foreach ($channels as $channelPath) {
                if (is_string($channelPath) && $channelPath !== '') {
                    $candidates[] = dirname($channelPath);
                }
            }
        }

        foreach ($candidates as $candidate) {
            if ($candidate !== null && is_dir($candidate)) {
                $real = realpath($candidate) ?: $candidate;
                if (!in_array($real, $dirs, true)) {
                    $dirs[] = $real;
                }
            }
        }

        return $dirs;
    }

    private function getMaxFileSizeBytes(): int
    {
        $env = getenv('CRM_LOG_MAX_FILE_SIZE');
        if ($env !== false && is_numeric($env)) {
            return (int)$env;
        }

        return (int)($this->config?->get('logging.rotation.max_file_size_bytes', self::DEFAULT_MAX_FILE_SIZE) ?? self::DEFAULT_MAX_FILE_SIZE);
    }

    private function getMaxFiles(): int
    {
        $env = getenv('CRM_LOG_MAX_FILES');
        if ($env !== false && is_numeric($env)) {
            return (int)$env;
        }

        return (int)($this->config?->get('logging.rotation.max_files', self::DEFAULT_MAX_FILES) ?? self::DEFAULT_MAX_FILES);
    }

    private function shouldCompress(): bool
    {
        $env = getenv('CRM_LOG_COMPRESS');
        if ($env !== false) {
            return $env !== 'false' && $env !== '0';
        }

        return (bool)($this->config?->get('logging.rotation.compress', true) ?? true);
    }

    private function getRetentionDays(): int
    {
        $env = getenv('CRM_LOG_RETENTION_DAYS');
        if ($env !== false && is_numeric($env)) {
            return (int)$env;
        }

        return (int)($this->config?->get('logging.rotation.retention_days', self::DEFAULT_RETENTION_DAYS) ?? self::DEFAULT_RETENTION_DAYS);
    }
}
