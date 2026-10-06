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
            $storedPath = (string)$file['stored_path'];
            $fullLocalPath = str_starts_with($storedPath, '/') ? $storedPath : $this->storageRoot . '/' . $storedPath;

            if (!file_exists($fullLocalPath) || !is_readable($fullLocalPath)) {
                $failed++;
                continue;
            }

            $content = (string)file_get_contents($fullLocalPath);
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
}
