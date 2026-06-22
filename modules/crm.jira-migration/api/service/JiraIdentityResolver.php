<?php
declare(strict_types=1);

namespace Module\Crm\JiraMigration\Service;

use Module\Crm\JiraMigration\Repository\JiraMigrationRepository;

/**
 * Resolves Jira identities (users, groups, project roles) to CRM entities.
 * For MVP: returns null (unmapped). Full mapping requires UI and matching logic.
 */
final class JiraIdentityResolver
{
    public function __construct(
        private JiraMigrationRepository $repo,
    ) {
    }

    /**
     * Resolve a Jira accountId to a CRM user public_id.
     * Returns null if no mapping found.
     */
    public function resolveUser(int $connectionId, string $accountId): ?string
    {
        $mappings = $this->repo->listMappings($connectionId);
        foreach ($mappings as $mapping) {
            if ($mapping['jira_subject_type'] === 'user'
                && $mapping['jira_subject_id'] === $accountId
                && $mapping['status'] === 'mapped'
                && $mapping['crm_subject_public_id']) {
                return (string)$mapping['crm_subject_public_id'];
            }
        }
        return null;
    }

    /**
     * Try to auto-resolve a Jira user by matching email.
     * Returns the CRM user public_id if found, null otherwise.
     */
    public function autoResolveByEmail(int $connectionId, string $jiraEmail): ?string
    {
        // For MVP, this is a placeholder
        // In production, would query users table by email
        return null;
    }
}
