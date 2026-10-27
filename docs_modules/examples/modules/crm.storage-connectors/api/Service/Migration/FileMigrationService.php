<?php
declare(strict_types=1);

namespace Module\Crm\StorageConnectors\Service\Migration;

use Module\Crm\StorageConnectors\Service\FileMigrationStorageManagerInterface;
use Module\Crm\StorageConnectors\Service\StorageManagerService;
use PDO;

/**
 * Bounded file migrator copying existing local CRM files to external storage in safe batches.
 */
final class FileMigrationService
{
    private PDO $db;
    private FileMigrationStorageManagerInterface $storageManager;
    private string $storageRoot;
    private ?object $fileService;
    private ?array $actor;

    public function __construct(PDO $db, FileMigrationStorageManagerInterface $storageManager, ?string $storageRoot = null, ?object $fileService = null, ?array $actor = null)
    {
        $this->db = $db;
        $this->storageManager = $storageManager;
        $this->storageRoot = $storageRoot ?? dirname(__DIR__, 6) . '/upload/storage_api/uploads';
        $this->fileService = $fileService;
        $this->actor = $actor;
    }

    /**
     * Migrate next batch of local files to configured remote storage.
     *
     * @return array{processed_count: int, skipped_count: int, synced_count: int, failed_count: int, failed_remaining_count: int, visible_failure_count_complete: bool, has_more: bool, next_cursor: int|null}
     */
    public function migrateBatch(int $organizationId, int $batchLimit = 15, ?string $onlyFilePublicId = null, int $afterFileId = 0): array
    {
        $empty = ['processed_count' => 0, 'skipped_count' => 0, 'synced_count' => 0, 'failed_count' => 0, 'failed_remaining_count' => 0, 'has_more' => false, 'next_cursor' => null];
        if ($organizationId <= 0 || $afterFileId < 0) return $empty;
        if ($this->fileService === null || $this->actor === null || $this->actor === []) {
            return array_merge($empty, ['failed_count' => 1]);
        }
        if ($onlyFilePublicId !== null && (trim($onlyFilePublicId) === '' || strlen($onlyFilePublicId) > 64)) {
            return array_merge($empty, ['failed_count' => 1]);
        }
        $batchLimit = max(1, min(100, $batchLimit));
        $scanLimit = $onlyFilePublicId !== null ? 1 : min(500, max($batchLimit, $batchLimit * 5));
        $adapter = $this->storageManager->getAdapter($organizationId);
        if ($adapter === null) return $empty;

        $eligibleEntity = $this->eligibleEntitySql();
        $onlyFileClause = $onlyFilePublicId === null ? '' : ' AND f.public_id = :only_file_public_id';
        $cursorClause = $onlyFilePublicId === null ? ' AND f.id > :after_file_id' : '';
        try {
            $stmt = $this->db->prepare(
                "SELECT f.id, f.public_id, f.original_name, f.storage_path, f.size_bytes
                 FROM files f
                 LEFT JOIN crm_storage_file_mappings m ON m.file_public_id = f.public_id AND m.organization_id = :map_org_id
                 LEFT JOIN crm_storage_file_migration_failures mf ON mf.file_public_id = f.public_id AND mf.organization_id = f.organization_id
                 WHERE f.organization_id = :file_org_id
                   AND f.is_deleted = 0 AND f.deleted_at IS NULL
                   AND f.public_id IS NOT NULL AND f.public_id <> ''
                   AND f.storage_path IS NOT NULL AND f.storage_path <> ''
                   AND f.entity_public_id IS NOT NULL AND f.entity_public_id <> ''
                   AND {$eligibleEntity} AND m.id IS NULL
                   AND (:retry_file_public_id IS NOT NULL OR mf.id IS NULL OR mf.error_code IN ('FILE_NOT_AVAILABLE','FILE_DOWNLOAD_DENIED')){$onlyFileClause}{$cursorClause}
                 ORDER BY f.id ASC LIMIT :limit"
            );
            $stmt->bindValue(':map_org_id', $organizationId, PDO::PARAM_INT);
            $stmt->bindValue(':file_org_id', $organizationId, PDO::PARAM_INT);
            if ($onlyFilePublicId !== null) $stmt->bindValue(':only_file_public_id', $onlyFilePublicId, PDO::PARAM_STR);
            $stmt->bindValue(':retry_file_public_id', $onlyFilePublicId, $onlyFilePublicId === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            if ($onlyFilePublicId === null) $stmt->bindValue(':after_file_id', $afterFileId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $scanLimit, PDO::PARAM_INT);
            $stmt->execute();
            $files = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return array_merge($empty, ['failed_count' => 1]);
        }

        $synced = 0;
        $failed = 0;
        $skipped = 0;
        $processed = 0;
        $lastScannedId = $afterFileId;

        foreach ($files as $file) {
            $lastScannedId = (int)$file['id'];
            $fPubId = (string)$file['public_id'];
            $access = $this->authorizedFileAccess($fPubId);
            if ($access === null) {
                // Access loss is actor-specific, not a migration failure. Do not
                // poison the workspace queue; advance only this actor's cursor.
                $skipped++;
                $this->clearAccessDenialFailure($organizationId, $fPubId);
                continue;
            }

            $content = $this->readLocalFile((string)($access['download']['path'] ?? ''), (int)($access['file']['size_bytes'] ?? -1));
            if ($content === null) {
                $failed++;
                $processed++;
                $this->recordFailure($organizationId, $fPubId, 'LOCAL_FILE_UNAVAILABLE');
                if ($processed >= $batchLimit) break;
                continue;
            }

            $processed++;
            try {
                $this->storageManager->storeRemoteFile($organizationId, $fPubId, $content, (string)($file['original_name'] ?? 'file.bin'));
                $this->clearFailure($organizationId, $fPubId);
                $synced++;
            } catch (\Throwable) {
                $failed++;
                $this->recordFailure($organizationId, $fPubId, 'REMOTE_STORAGE_FAILED');
            }
            if ($processed >= $batchLimit) break;
        }

        $remaining = 0;
        if ($onlyFilePublicId === null) {
            try {
                $checkStmt = $this->db->prepare(
                    "SELECT COUNT(*) FROM files f
                     LEFT JOIN crm_storage_file_mappings m ON m.file_public_id = f.public_id AND m.organization_id = :map_org_id
                     LEFT JOIN crm_storage_file_migration_failures mf ON mf.file_public_id = f.public_id AND mf.organization_id = f.organization_id
                     WHERE f.organization_id = :file_org_id AND f.id > :after_file_id
                       AND f.is_deleted = 0 AND f.deleted_at IS NULL
                       AND f.public_id IS NOT NULL AND f.public_id <> ''
                       AND f.storage_path IS NOT NULL AND f.storage_path <> ''
                       AND f.entity_public_id IS NOT NULL AND f.entity_public_id <> ''
                       AND {$eligibleEntity} AND m.id IS NULL AND (mf.id IS NULL OR mf.error_code IN ('FILE_NOT_AVAILABLE','FILE_DOWNLOAD_DENIED'))"
                );
                $checkStmt->execute([':map_org_id' => $organizationId, ':file_org_id' => $organizationId, ':after_file_id' => $lastScannedId]);
                $remaining = (int)$checkStmt->fetchColumn();
            } catch (\Throwable) {
                $failed++;
            }
        }

        try { $visibleFailureCount = $this->countVisibleMigrationFailures($organizationId); }
        catch (\Throwable) { $visibleFailureCount = ['count' => 0, 'complete' => false]; $failed++; }

        $hasMore = $remaining > 0;
        return [
            'processed_count' => $processed,
            'skipped_count' => $skipped,
            'synced_count' => $synced,
            'failed_count' => $failed,
            'failed_remaining_count' => $visibleFailureCount['count'],
            'visible_failure_count_complete' => $visibleFailureCount['complete'],
            'has_more' => $hasMore,
            'next_cursor' => $hasMore ? $lastScannedId : null,
        ];
    }

    /** @return list<array{file_public_id:string,filename:string,error_code:string,attempts:int,updated_at:string}> */
    public function listMigrationFailures(int $organizationId, int $limit = 50): array
    {
        if ($organizationId <= 0 || $this->fileService === null || $this->actor === null || $this->actor === []) return [];
        $limit = max(1, min(100, $limit));
        $scanBudget = min(2000, max($limit, $limit * 20));
        $eligibleEntity = $this->eligibleEntitySql();
        $items = [];
        $afterId = 0;
        $scanned = 0;
        while ($scanned < $scanBudget && count($items) < $limit) {
            $pageSize = min(100, $scanBudget - $scanned);
            $stmt = $this->db->prepare(
                "SELECT mf.id, mf.file_public_id, COALESCE(NULLIF(f.original_name, ''), mf.file_public_id) AS filename,
                        mf.error_code, mf.attempts, mf.updated_at
                 FROM crm_storage_file_migration_failures mf
                 INNER JOIN files f ON f.public_id = mf.file_public_id AND f.organization_id = mf.organization_id
                 LEFT JOIN crm_storage_file_mappings m ON m.file_public_id = f.public_id AND m.organization_id = f.organization_id
                 WHERE mf.organization_id = :organization_id AND mf.id > :after_id AND m.id IS NULL
                   AND mf.error_code NOT IN ('FILE_NOT_AVAILABLE','FILE_DOWNLOAD_DENIED') AND f.is_deleted = 0 AND f.deleted_at IS NULL AND {$eligibleEntity}
                 ORDER BY mf.id ASC LIMIT :limit"
            );
            $stmt->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);
            $stmt->bindValue(':after_id', $afterId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if ($rows === []) break;
            foreach ($rows as $row) {
                $afterId = (int)$row['id'];
                $scanned++;
                if ($this->authorizedFileAccess((string)$row['file_public_id']) === null) continue;
                $items[] = [
                    'file_public_id' => (string)$row['file_public_id'],
                    'filename' => (string)$row['filename'],
                    'error_code' => (string)$row['error_code'],
                    'attempts' => (int)$row['attempts'],
                    'updated_at' => (string)$row['updated_at'],
                ];
                if (count($items) >= $limit || $scanned >= $scanBudget) break;
            }
            if (count($rows) < $pageSize) break;
        }
        return $items;
    }

    /** Retry exactly one failed file after an explicit operator action. */
    public function retryMigrationFailure(int $organizationId, string $filePublicId): array
    {
        if ($organizationId <= 0 || trim($filePublicId) === '' || strlen($filePublicId) > 64) {
            return ['processed_count' => 0, 'synced_count' => 0, 'failed_count' => 1, 'failed_remaining_count' => 0, 'has_more' => false];
        }
        $eligibleEntity = $this->eligibleEntitySql();
        $check = $this->db->prepare(
            "SELECT mf.id FROM crm_storage_file_migration_failures mf
             INNER JOIN files f ON f.public_id = mf.file_public_id AND f.organization_id = mf.organization_id
             LEFT JOIN crm_storage_file_mappings m ON m.file_public_id = f.public_id AND m.organization_id = f.organization_id
             WHERE mf.organization_id = :organization_id AND mf.file_public_id = :file_public_id
               AND m.id IS NULL AND f.is_deleted = 0 AND f.deleted_at IS NULL AND {$eligibleEntity} LIMIT 1"
        );
        $check->execute([':organization_id' => $organizationId, ':file_public_id' => $filePublicId]);
        if ($check->fetchColumn() === false) {
            $visibleFailureCount = $this->countVisibleMigrationFailures($organizationId);
            return ['processed_count' => 0, 'synced_count' => 0, 'failed_count' => 0, 'failed_remaining_count' => $visibleFailureCount['count'], 'visible_failure_count_complete' => $visibleFailureCount['complete'], 'has_more' => false];
        }
        if ($this->authorizedFileAccess($filePublicId) === null) {
            $visibleFailureCount = $this->countVisibleMigrationFailures($organizationId);
            return ['processed_count' => 0, 'skipped_count' => 1, 'synced_count' => 0, 'failed_count' => 0, 'failed_remaining_count' => $visibleFailureCount['count'], 'visible_failure_count_complete' => $visibleFailureCount['complete'], 'has_more' => false, 'next_cursor' => null];
        }
        return $this->migrateBatch($organizationId, 1, $filePublicId);
    }

    /** Return only a file the actor may read in this workspace; denied files are never recorded as migration errors. */
    private function authorizedFileAccess(string $filePublicId): ?array
    {
        if ($this->fileService === null || $this->actor === null || $this->actor === []) return null;
        try {
            $file = $this->fileService->get($filePublicId, $this->actor);
            if (!is_array($file) || !empty($file['is_deleted']) || !empty($file['is_quarantined'])) return null;
            $download = $this->fileService->canDownloadInternal($filePublicId, $this->actor);
            if (!(bool)($download['ok'] ?? false)) return null;
            return ['file' => $file, 'download' => $download];
        } catch (\Throwable) {
            return null;
        }
    }

    private function recordFailure(int $organizationId, string $filePublicId, string $errorCode): void
    {
        $publicId = 'smf_' . bin2hex(random_bytes(12));
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'sqlite'
            ? 'INSERT INTO crm_storage_file_migration_failures (public_id, organization_id, file_public_id, error_code, attempts, created_at, updated_at) VALUES (:public_id, :organization_id, :file_public_id, :error_code, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) ON CONFLICT(organization_id, file_public_id) DO UPDATE SET error_code = excluded.error_code, attempts = crm_storage_file_migration_failures.attempts + 1, updated_at = CURRENT_TIMESTAMP'
            : 'INSERT INTO crm_storage_file_migration_failures (public_id, organization_id, file_public_id, error_code, attempts, created_at, updated_at) VALUES (:public_id, :organization_id, :file_public_id, :error_code, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE error_code = VALUES(error_code), attempts = attempts + 1, updated_at = CURRENT_TIMESTAMP';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':public_id' => $publicId, ':organization_id' => $organizationId, ':file_public_id' => $filePublicId, ':error_code' => $errorCode]);
    }

    /** Access-denial markers from older releases are not transfer failures and must not block the queue. */
    private function clearAccessDenialFailure(int $organizationId, string $filePublicId): void
    {
        $stmt = $this->db->prepare("DELETE FROM crm_storage_file_migration_failures WHERE organization_id = :organization_id AND file_public_id = :file_public_id AND error_code IN ('FILE_NOT_AVAILABLE','FILE_DOWNLOAD_DENIED')");
        $stmt->execute([':organization_id' => $organizationId, ':file_public_id' => $filePublicId]);
    }

    private function clearFailure(int $organizationId, string $filePublicId): void
    {
        $stmt = $this->db->prepare('DELETE FROM crm_storage_file_migration_failures WHERE organization_id = :organization_id AND file_public_id = :file_public_id');
        $stmt->execute([':organization_id' => $organizationId, ':file_public_id' => $filePublicId]);
    }

    /**
     * Count only failures whose files this actor can read. The scan is bounded
     * to avoid unbounded work on shared hosting; `complete=false` means the
     * count is a lower bound after the scan budget was exhausted.
     * @return array{count:int,complete:bool}
     */
    public function countVisibleMigrationFailures(int $organizationId, int $scanBudget = 2000): array
    {
        if ($organizationId <= 0 || $this->fileService === null || $this->actor === null || $this->actor === []) {
            return ['count' => 0, 'complete' => true];
        }
        $scanBudget = max(1, min(10000, $scanBudget));
        $eligibleEntity = $this->eligibleEntitySql();
        $count = 0;
        $afterId = 0;
        $scanned = 0;
        while ($scanned < $scanBudget) {
            $pageSize = min(100, $scanBudget - $scanned);
            $stmt = $this->db->prepare(
                "SELECT mf.id, mf.file_public_id
                 FROM crm_storage_file_migration_failures mf
                 INNER JOIN files f ON f.public_id = mf.file_public_id AND f.organization_id = mf.organization_id
                 LEFT JOIN crm_storage_file_mappings m ON m.file_public_id = f.public_id AND m.organization_id = f.organization_id
                 WHERE mf.organization_id = :organization_id AND mf.id > :after_id AND m.id IS NULL
                   AND mf.error_code NOT IN ('FILE_NOT_AVAILABLE','FILE_DOWNLOAD_DENIED')
                   AND f.is_deleted = 0 AND f.deleted_at IS NULL AND {$eligibleEntity}
                 ORDER BY mf.id ASC LIMIT :limit"
            );
            $stmt->bindValue(':organization_id', $organizationId, PDO::PARAM_INT);
            $stmt->bindValue(':after_id', $afterId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $row) {
                $afterId = (int)$row['id'];
                $scanned++;
                if ($this->authorizedFileAccess((string)$row['file_public_id']) !== null) $count++;
            }
            if (count($rows) < $pageSize) return ['count' => $count, 'complete' => true];
        }
        $more = $this->db->prepare(
            "SELECT 1 FROM crm_storage_file_migration_failures mf
             INNER JOIN files f ON f.public_id = mf.file_public_id AND f.organization_id = mf.organization_id
             LEFT JOIN crm_storage_file_mappings m ON m.file_public_id = f.public_id AND m.organization_id = f.organization_id
             WHERE mf.organization_id = :organization_id AND mf.id > :after_id AND m.id IS NULL
               AND mf.error_code NOT IN ('FILE_NOT_AVAILABLE','FILE_DOWNLOAD_DENIED')
               AND f.is_deleted = 0 AND f.deleted_at IS NULL AND {$eligibleEntity} LIMIT 1"
        );
        $more->execute([':organization_id' => $organizationId, ':after_id' => $afterId]);
        return ['count' => $count, 'complete' => $more->fetchColumn() === false];
    }

    private function eligibleEntitySql(): string
    {
        return "(
            (f.entity_type = 'task' AND EXISTS (
                SELECT 1 FROM tasks e WHERE e.public_id = f.entity_public_id
                  AND e.organization_id = f.organization_id AND e.deleted_at IS NULL
            ))
            OR (f.entity_type = 'project' AND EXISTS (
                SELECT 1 FROM projects e WHERE e.public_id = f.entity_public_id
                  AND e.organization_id = f.organization_id AND e.deleted_at IS NULL
            ))
            OR (f.entity_type = 'knowledge_page' AND EXISTS (
                SELECT 1 FROM knowledge_pages e WHERE e.public_id = f.entity_public_id
                  AND e.organization_id = f.organization_id AND e.deleted_at IS NULL
            ))
            OR (f.entity_type = 'comment' AND EXISTS (
                SELECT 1 FROM comments c
                WHERE c.public_id = f.entity_public_id
                  AND c.organization_id = f.organization_id AND c.deleted_at IS NULL
                  AND ((c.task_id IS NOT NULL AND EXISTS (
                      SELECT 1 FROM tasks e WHERE e.id = c.task_id
                        AND e.organization_id = f.organization_id AND e.deleted_at IS NULL
                  )) OR (c.project_id IS NOT NULL AND EXISTS (
                      SELECT 1 FROM projects e WHERE e.id = c.project_id
                        AND e.organization_id = f.organization_id AND e.deleted_at IS NULL
                  )))
            ))
            OR (f.entity_type = 'chat_message' AND EXISTS (
                SELECT 1 FROM chat_messages e
                JOIN chats c ON c.id = e.chat_id
                WHERE e.public_id = f.entity_public_id
                  AND e.organization_id = f.organization_id AND e.deleted_at IS NULL
                  AND c.organization_id = f.organization_id
            ))
            OR (f.entity_type = 'intake_item' AND EXISTS (
                SELECT 1 FROM intake_items e WHERE e.public_id = f.entity_public_id
                  AND e.organization_id = f.organization_id AND e.deleted_at IS NULL
            ))
        )";
    }

    private function readLocalFile(string $storedPath, int $expectedSize = -1): ?string
    {
        $fullPath = $this->resolveLocalPath($storedPath);
        if ($fullPath === null || !is_readable($fullPath)) {
            return null;
        }

        $before = @lstat($fullPath);
        $stream = @fopen($fullPath, 'rb');
        if (!is_array($before) || !is_resource($stream)) {
            return null;
        }
        try {
            $opened = fstat($stream);
            if (!is_array($opened)
                || (($before['mode'] & 0170000) !== 0100000)
                || (($opened['mode'] & 0170000) !== 0100000)
                || $before['dev'] !== $opened['dev']
                || $before['ino'] !== $opened['ino']
                || $opened['size'] < 0
                || $opened['size'] > StorageManagerService::MAX_FILE_BYTES
                || $opened['size'] !== $expectedSize
                || !$this->hasMemoryBudget((int)$opened['size'])
            ) {
                return null;
            }

            $content = stream_get_contents($stream, StorageManagerService::MAX_FILE_BYTES + 1);
            if (!is_string($content) || strlen($content) !== (int)$opened['size']) {
                return null;
            }
            $afterOpen = fstat($stream);
            $after = @lstat($fullPath);
            if (!is_array($afterOpen) || !is_array($after)
                || $afterOpen['dev'] !== $opened['dev'] || $afterOpen['ino'] !== $opened['ino']
                || $afterOpen['size'] !== $opened['size']
                || $afterOpen['mtime'] !== $opened['mtime'] || $afterOpen['ctime'] !== $opened['ctime']
                || $after['dev'] !== $opened['dev'] || $after['ino'] !== $opened['ino']
                || $after['size'] !== $opened['size']
                || $after['mtime'] !== $opened['mtime'] || $after['ctime'] !== $opened['ctime']) {
                return null;
            }
            return $content;
        } finally {
            fclose($stream);
        }
    }

    private function hasMemoryBudget(int $sizeBytes): bool
    {
        $limit = trim((string)ini_get('memory_limit'));
        if ($limit === '' || $limit === '-1') return true;
        if (!preg_match('/^(\d+)\s*([KMG]?)$/i', $limit, $matches)) return false;
        $unit = strtoupper($matches[2]);
        $limitBytes = (int)$matches[1] * match ($unit) { 'G' => 1024 ** 3, 'M' => 1024 ** 2, 'K' => 1024, default => 1 };
        $required = ($sizeBytes * 3) + (4 * 1024 * 1024);
        return memory_get_usage(true) + $required < $limitBytes;
    }

    private function resolveLocalPath(string $storedPath): ?string
    {
        $root = realpath($this->storageRoot);
        $relative = str_replace('\\', '/', trim($storedPath));
        if ($root === false || $relative === '' || preg_match('/[\x00-\x1F\x7F]/', $relative)) {
            return null;
        }

        if (str_starts_with($relative, '/') || preg_match('/^[A-Za-z]:\//', $relative)) {
            // Validate the original path before realpath() normalizes it. In
            // particular, never accept absolute paths containing traversal
            // segments even when they eventually resolve back under the root.
            $pathToCheck = str_starts_with($relative, '/')
                ? ltrim($relative, '/')
                : substr($relative, 3);
            foreach (explode('/', $pathToCheck) as $segment) {
                if ($segment === '' || $segment === '.' || $segment === '..') {
                    return null;
                }
            }
            $candidate = $relative;
        } else {
            foreach (explode('/', $relative) as $segment) {
                if ($segment === '' || $segment === '.' || $segment === '..') {
                    return null;
                }
            }
            $candidate = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        }

        $resolved = realpath($candidate);
        if ($resolved === false || is_link($candidate)) {
            return null;
        }

        $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($candidate, $rootPrefix)) {
            return null;
        }
        $cursor = rtrim($root, DIRECTORY_SEPARATOR);
        foreach (explode(DIRECTORY_SEPARATOR, substr($candidate, strlen($rootPrefix))) as $segment) {
            $cursor .= DIRECTORY_SEPARATOR . $segment;
            if (is_link($cursor)) {
                return null;
            }
        }
        if (!str_starts_with($resolved, $rootPrefix)) {
            return null;
        }

        return $resolved;
    }
}
