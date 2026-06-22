<?php
declare(strict_types=1);

namespace Module\Crm\JiraMigration\Service;

use Module\Crm\JiraMigration\Repository\JiraMigrationRepository;

/**
 * Handles user, group, status, priority mappings between Jira and CRM.
 * The actual mapping data is stored in jira_identity_mappings table.
 */
final class JiraMappingService
{
    public function __construct(
        private JiraMigrationRepository $repo,
    ) {
    }

    /**
     * Get all unresolved mappings for a connection.
     */
    public function getUnresolvedMappings(int $connectionId): array
    {
        return $this->repo->listMappings($connectionId);
    }

    /**
     * Update or create a mapping.
     */
    public function upsertMapping(
        int $connectionId,
        string $subjectType,
        string $subjectId,
        string $subjectName,
    ): void {
        $this->repo->upsertMapping($connectionId, $subjectType, $subjectId, $subjectName);
    }

    /**
     * Suggest CRM users for a Jira user based on display name or email.
     */
    public function suggestCrmUsers(string $jiraName, ?string $jiraEmail = null): array
    {
        // This would search CRM users by name/email
        // For MVP, return empty array - mapping is done manually
        return [];
    }
}
