<?php
declare(strict_types=1);

namespace Module\Crm\StorageConnectors\Service\Adapter;

/**
 * Common interface for storage backend adapters (Nextcloud, WebDAV, S3).
 */
interface StorageAdapterInterface
{
    /**
     * @return array{ok: bool, message: string, details?: array<string, mixed>}
     */
    public function testConnection(): array;

    /**
     * Upload stream or raw content to remote backend.
     *
     * @return array{ok: bool, object_key: string, sha256: string, size_bytes: int}
     */
    public function putObject(string $objectKey, string $content): array;

    /**
     * Download object content from remote backend.
     */
    public function getObject(string $objectKey): ?string;

    /**
     * Delete object from remote backend.
     */
    public function deleteObject(string $objectKey): bool;

    /**
     * Generate short-lived signed read URL if provider supports it.
     */
    public function createSignedUrl(string $objectKey, int $expiresInSeconds = 300): ?string;
}
