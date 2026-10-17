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

    public function __construct(PDO $db, FileMigrationStorageManagerInterface $storageManager, ?string $storageRoot = null)
    {
        $this->db = $db;
        $this->storageManager = $storageManager;
        $this->storageRoot = $storageRoot ?? dirname(__DIR__, 6) . '/upload/storage_api/uploads';
    }

    /**
     * Migrate next batch of local files to configured remote storage.
     *
     * @return array{processed_count: int, synced_count: int, failed_count: int, has_more: bool}
     */
    public function migrateBatch(int $organizationId, int $batchLimit = 15): array
    {
        if ($organizationId <= 0) {
            return ['processed_count' => 0, 'synced_count' => 0, 'failed_count' => 0, 'has_more' => false];
        }
        $batchLimit = max(1, min(100, $batchLimit));
        $adapter = $this->storageManager->getAdapter($organizationId);
        if ($adapter === null) {
            return ['processed_count' => 0, 'synced_count' => 0, 'failed_count' => 0, 'has_more' => false];
        }

        // Only migrate active files whose parent object is still active in the
        // exact same workspace. Unknown entity types fail closed until they get
        // an explicit, tenant-scoped branch here.
        $eligibleEntity = $this->eligibleEntitySql();
        try {
            $stmt = $this->db->prepare(
            "SELECT f.id, f.public_id, f.original_name, f.storage_path, f.size_bytes
             FROM files f
             LEFT JOIN crm_storage_file_mappings m ON m.file_public_id = f.public_id AND m.organization_id = :map_org_id
             WHERE f.organization_id = :file_org_id
               AND f.is_deleted = 0
               AND f.deleted_at IS NULL
               AND f.public_id IS NOT NULL AND f.public_id <> ''
               AND f.storage_path IS NOT NULL AND f.storage_path <> ''
               AND f.entity_public_id IS NOT NULL AND f.entity_public_id <> ''
               AND {$eligibleEntity}
               AND m.id IS NULL
             ORDER BY f.id ASC
             LIMIT :limit"
            );
            $stmt->bindValue(':map_org_id', $organizationId, PDO::PARAM_INT);
            $stmt->bindValue(':file_org_id', $organizationId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $batchLimit, PDO::PARAM_INT);
            $stmt->execute();
            $files = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return ['processed_count' => 0, 'synced_count' => 0, 'failed_count' => 1, 'has_more' => false];
        }

        $synced = 0;
        $failed = 0;

        foreach ($files as $file) {
            $fPubId = (string)$file['public_id'];
            $content = $this->readLocalFile((string)$file['storage_path']);
            if ($content === null) {
                $failed++;
                continue;
            }
            try {
                $this->storageManager->storeRemoteFile($organizationId, $fPubId, $content, (string)($file['original_name'] ?? 'file.bin'));
                $synced++;
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        // Check if there are more using the same strict eligibility predicate.
        try {
            $checkStmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM files f
             LEFT JOIN crm_storage_file_mappings m ON m.file_public_id = f.public_id AND m.organization_id = :map_org_id
             WHERE f.organization_id = :file_org_id
               AND f.is_deleted = 0
               AND f.deleted_at IS NULL
               AND f.public_id IS NOT NULL AND f.public_id <> ''
               AND f.storage_path IS NOT NULL AND f.storage_path <> ''
               AND f.entity_public_id IS NOT NULL AND f.entity_public_id <> ''
               AND {$eligibleEntity}
               AND m.id IS NULL"
            );
            $checkStmt->execute([':map_org_id' => $organizationId, ':file_org_id' => $organizationId]);
            $remaining = (int)$checkStmt->fetchColumn();
        } catch (\Throwable) {
            return ['processed_count' => count($files), 'synced_count' => $synced, 'failed_count' => $failed + 1, 'has_more' => false];
        }

        return [
            'processed_count' => count($files),
            'synced_count' => $synced,
            'failed_count' => $failed,
            'has_more' => $remaining > 0,
        ];
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

    private function readLocalFile(string $storedPath): ?string
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
            ) {
                return null;
            }

            $content = stream_get_contents($stream, StorageManagerService::MAX_FILE_BYTES + 1);
            if (!is_string($content) || strlen($content) !== (int)$opened['size']) {
                return null;
            }
            $after = @lstat($fullPath);
            if (!is_array($after) || $after['dev'] !== $opened['dev'] || $after['ino'] !== $opened['ino']) {
                return null;
            }
            return $content;
        } finally {
            fclose($stream);
        }
    }

    private function resolveLocalPath(string $storedPath): ?string
    {
        $root = realpath($this->storageRoot);
        $relative = str_replace('\\', '/', trim($storedPath));
        if ($root === false || $relative === '' || preg_match('/[\x00-\x1F\x7F]/', $relative)) {
            return null;
        }

        if (str_starts_with($relative, '/') || preg_match('/^[A-Za-z]:\//', $relative)) {
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
