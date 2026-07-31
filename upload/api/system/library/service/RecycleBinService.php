<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\File\FileRepository;
use Api\Model\Recycle_bin\RecycleBinRepository;
use Api\System\Library\Logger\JsonLogger;

final class RecycleBinService
{
    public function __construct(
        private readonly RecycleBinRepository $recycleBin,
        private readonly FileRepository $files,
        private readonly JsonLogger $logger
    ) {
    }

    public function list(array $filters): array
    {
        [$items, $total, $page, $limit] = $this->recycleBin->list($filters);

        return [
            'items' => array_map([$this, 'normalizeItem'], $items),
            'meta' => [
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => (int)ceil($total / max(1, $limit)),
                ],
            ],
        ];
    }

    public function restore(string $publicId, array $actor): array
    {
        $item = $this->recycleBin->findByPublicId($publicId);
        if (!$item) {
            return ['ok' => false, 'code' => 'RECYCLE_BIN_ITEM_NOT_FOUND'];
        }
        if (!empty($item['restored_at'])) {
            return ['ok' => false, 'code' => 'RECYCLE_BIN_ALREADY_RESTORED'];
        }

        if ((string)$item['entity_type'] !== 'file') {
            return ['ok' => false, 'code' => 'RECYCLE_BIN_ENTITY_UNSUPPORTED'];
        }

        $file = $this->files->findByPublicId((string)$item['entity_public_id']);
        if (!$file) {
            return ['ok' => false, 'code' => 'RECYCLE_BIN_ENTITY_NOT_FOUND'];
        }

        $storagePath = (string)($file['storage_path'] ?? '');
        $deletedPath = $storagePath !== '' ? $storagePath . '.deleted' : '';
        if ($deletedPath !== '' && is_file($deletedPath) && !is_file($storagePath)) {
            @rename($deletedPath, $storagePath);
        }

        $this->files->restore((string)$file['public_id']);
        $this->recycleBin->markRestoredByPublicId($publicId, gmdate('Y-m-d H:i:s'));

        $this->logger->audit([
            'action' => 'recycle_bin_restore',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'recycle_bin',
            'entity_public_id' => $publicId,
            'restored_entity_type' => 'file',
            'restored_entity_public_id' => (string)$file['public_id'],
        ]);

        $updated = $this->recycleBin->findByPublicId($publicId);

        return [
            'ok' => true,
            'item' => $updated ? $this->normalizeItem($updated) : ['public_id' => $publicId],
        ];
    }

    public function purge(string $publicId, array $actor): array
    {
        $item = $this->recycleBin->findByPublicId($publicId);
        if (!$item) {
            return ['ok' => false, 'code' => 'RECYCLE_BIN_ITEM_NOT_FOUND'];
        }
        if (!empty($item['restored_at'])) {
            return ['ok' => false, 'code' => 'RECYCLE_BIN_ALREADY_RESTORED'];
        }

        if ((string)$item['entity_type'] !== 'file') {
            return ['ok' => false, 'code' => 'RECYCLE_BIN_ENTITY_UNSUPPORTED'];
        }

        $file = $this->files->findByPublicId((string)$item['entity_public_id']);
        if ($file) {
            $storagePath = (string)($file['storage_path'] ?? '');
            $deletedPath = $storagePath !== '' ? $storagePath . '.deleted' : '';
            if ($deletedPath !== '' && is_file($deletedPath)) {
                @unlink($deletedPath);
            }
            if ($storagePath !== '' && is_file($storagePath)) {
                @unlink($storagePath);
            }

            $this->files->hardDelete((string)$file['public_id']);
        }

        $this->recycleBin->deleteByPublicId($publicId);

        $this->logger->audit([
            'action' => 'recycle_bin_purge',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'recycle_bin',
            'entity_public_id' => $publicId,
            'purged_entity_type' => 'file',
            'purged_entity_public_id' => (string)($item['entity_public_id'] ?? ''),
        ]);

        return [
            'ok' => true,
            'item' => $this->normalizeItem($item),
        ];
    }

    /** @param array<string,mixed> $item */
    private function normalizeItem(array $item): array
    {
        $payload = [];
        $rawPayload = $item['payload'] ?? null;
        if (is_string($rawPayload) && $rawPayload !== '') {
            $decoded = json_decode($rawPayload, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        return [
            'public_id' => (string)($item['public_id'] ?? ''),
            'entity_type' => (string)($item['entity_type'] ?? ''),
            'entity_public_id' => (string)($item['entity_public_id'] ?? ''),
            'payload' => $payload,
            'deleted_by' => [
                'public_id' => (string)($item['deleted_by_user_public_id'] ?? ''),
                'login' => (string)($item['deleted_by_login'] ?? ''),
                'full_name' => (string)($item['deleted_by_full_name'] ?? ''),
            ],
            'deleted_at' => (string)($item['deleted_at'] ?? ''),
            'restored_at' => (string)($item['restored_at'] ?? ''),
        ];
    }
}
