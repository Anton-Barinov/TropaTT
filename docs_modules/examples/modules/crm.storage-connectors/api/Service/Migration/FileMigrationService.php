<?php
declare(strict_types=1);

namespace Module\Crm\StorageConnectors\Service\Migration;

use Module\Crm\StorageConnectors\Service\StorageManagerService;
use PDO;

/**
 * Bounded file migrator copying existing local CRM files to external storage in safe batches.
 */
final class FileMigrationService
{
    private PDO $db;
    private StorageManagerService $storageManager;
    private string $storageRoot;

    public function __construct(PDO $db, StorageManagerService $storageManager, ?string $storageRoot = null)
    {
        $this->db = $db;
        $this->storageManager = $storageManager;
        $this->storageRoot = $storageRoot ?? dirname(__DIR__, 6) . '/upload/storage';
    }

    /**
     * Migrate next batch of local files to configured remote storage.
     *
     * @return array{processed_count: int, synced_count: int, failed_count: int, has_more: bool}
     */
    public function migrateBatch(int $organizationId, int $batchLimit = 15): array
    {
        $adapter = $this->storageManager->getAdapter($organizationId);
        if ($adapter === null) {
            return ['processed_count' => 0, 'synced_count' => 0, 'failed_count' => 0, 'has_more' => false];
        }

        // Find files belonging to this organization not yet mapped to remote storage
        $stmt = $this->db->prepare(
            "SELECT f.id, f.public_id, f.name, f.stored_path, f.size_bytes
             FROM files f
             LEFT JOIN crm_storage_file_mappings m ON m.file_public_id = f.public_id AND m.organization_id = :org_id
             WHERE (f.organization_id = :org_id OR f.organization_id IS NULL)
               AND m.id IS NULL
             ORDER BY f.id ASC
             LIMIT :limit"
        );
        $stmt->bindValue(':org_id', $organizationId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $batchLimit, PDO::PARAM_INT);
        $stmt->execute();
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $synced = 0;
        $failed = 0;

        foreach ($files as $file) {
            $fPubId = (string)$file['public_id'];
            $fullLocalPath = $this->resolveLocalPath((string)$file['stored_path']);
            if ($fullLocalPath === null || !is_file($fullLocalPath) || !is_readable($fullLocalPath) || is_link($fullLocalPath)) {
                $failed++;
                continue;
            }

            $fileSize = filesize($fullLocalPath);
            if ($fileSize === false || $fileSize < 0 || $fileSize > StorageManagerService::MAX_FILE_BYTES) {
                $failed++;
                continue;
            }

            $content = file_get_contents($fullLocalPath);
            if (!is_string($content) || strlen($content) !== $fileSize) {
                $failed++;
                continue;
            }
            try {
                $this->storageManager->storeRemoteFile($organizationId, $fPubId, $content, (string)$file['name']);
                $synced++;
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        // Check if there are more
        $checkStmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM files f
             LEFT JOIN crm_storage_file_mappings m ON m.file_public_id = f.public_id AND m.organization_id = :org_id
             WHERE (f.organization_id = :org_id OR f.organization_id IS NULL)
               AND m.id IS NULL"
        );
        $checkStmt->execute([':org_id' => $organizationId]);
        $remaining = (int)$checkStmt->fetchColumn();

        return [
            'processed_count' => count($files),
            'synced_count' => $synced,
            'failed_count' => $failed,
            'has_more' => $remaining > 0,
        ];
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
