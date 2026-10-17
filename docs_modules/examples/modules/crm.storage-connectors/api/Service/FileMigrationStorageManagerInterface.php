<?php
declare(strict_types=1);

namespace Module\Crm\StorageConnectors\Service;

use Module\Crm\StorageConnectors\Service\Adapter\StorageAdapterInterface;

interface FileMigrationStorageManagerInterface
{
    public function getAdapter(int $organizationId): ?StorageAdapterInterface;

    /** @return array{ok: bool, remote_key: string, sha256: string} */
    public function storeRemoteFile(int $organizationId, string $filePublicId, string $content, string $filename): array;
}
