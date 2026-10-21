<?php

declare(strict_types=1);

namespace Module\Crm\YandexDisk\Service;

/**
 * Shared validation helpers for Yandex Disk operations.
 *
 * This example package does not yet implement the remote upload protocol.
 * In particular, it must never report success or persist fabricated metadata.
 */
final class YandexDiskService
{
    public function __construct(private readonly int $workspaceId, private readonly ?string $oauthToken = null)
    {
        if ($workspaceId <= 0) {
            throw new \InvalidArgumentException('A valid workspace is required.');
        }
    }

    public function isConfigured(): bool
    {
        return is_string($this->oauthToken) && trim($this->oauthToken) !== '';
    }

    /** Validate a CRM storage path before any future provider request. */
    public function validateRemotePath(string $path): string
    {
        if (str_contains($path, "\\") || preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            throw new \InvalidArgumentException('Invalid remote path.');
        }
        $path = trim($path);
        if ($path === '' || strlen($path) > 500 || $path[0] !== '/') {
            throw new \InvalidArgumentException('Invalid remote path.');
        }
        // Validate decoded segments too so future URL encoding cannot disguise
        // traversal when the path is later passed to a provider API.
        foreach (explode('/', rawurldecode($path)) as $segment) {
            if ($segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException('Remote path traversal is not allowed.');
            }
        }
        return $path;
    }

    /** The provider connection is intentionally not simulated. */
    public function uploadIsImplemented(): bool
    {
        return false;
    }
}
