<?php
declare(strict_types=1);

namespace Api\System\Library\Connector;

/**
 * Contract for workspace-isolated, encrypted module credential management.
 */
interface ConnectorCredentialStoreInterface
{
    /**
     * Store or replace an encrypted credential for a module within a specific workspace.
     */
    public function setSecret(string $moduleName, int $organizationId, string $keyName, string $secret): void;

    /**
     * Retrieve and decrypt a credential for a module within a specific workspace.
     */
    public function getSecret(string $moduleName, int $organizationId, string $keyName): ?string;

    /**
     * Check if a credential exists for a module within a workspace.
     */
    public function hasSecret(string $moduleName, int $organizationId, string $keyName): bool;

    /**
     * Remove a credential for a module within a workspace.
     */
    public function deleteSecret(string $moduleName, int $organizationId, string $keyName): bool;

    /**
     * Mask a sensitive secret string for display in UI or logs without leaking its value.
     */
    public function redact(string $secret, int $visibleChars = 4): string;
}
