<?php

declare(strict_types=1);

namespace Module\Crm\VkWorkspace\Service;

use PDO;
use RuntimeException;

/** Fail-closed boundary until VK WorkSpace provider APIs and credentials are implemented. */
final class VkWorkspaceService
{
    public function __construct(private readonly PDO $db, private readonly int $workspaceId)
    {
    }

    /** @return array<string, mixed> */
    public function getStatus(): array
    {
        return [
            'status' => 'provider_api_unavailable',
            'configured' => false,
            'connected' => false,
            'features' => ['calls' => false, 'calendar' => false, 'mail' => false, 'disk' => false, 'teams' => false],
            'message' => 'VK WorkSpace integration is not available yet. No provider operation was performed.',
        ];
    }

    /**
     * Meeting creation is disabled until official API calls, tenant settings,
     * and provider response validation are implemented.
     *
     * @return never
     */
    public function scheduleMeeting(array $data): never
    {
        $this->assertWorkspace();
        throw new RuntimeException('VK_WORKSPACE_INTEGRATION_UNAVAILABLE: VK WorkSpace meetings cannot be scheduled until the provider integration is implemented.');
    }

    /** @return list<array<string, mixed>> */
    public function listMeetings(): array
    {
        $this->assertWorkspace();
        $stmt = $this->db->prepare(
            'SELECT public_id, title, description, starts_at, ends_at, status, created_at
             FROM module_vk_workspace_meetings WHERE workspace_id = :workspace_id AND provider_verified = 1
             ORDER BY starts_at DESC LIMIT 50'
        );
        $stmt->execute(['workspace_id' => $this->workspaceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function assertWorkspace(): void
    {
        if ($this->workspaceId <= 0) {
            throw new RuntimeException('ACTIVE_WORKSPACE_REQUIRED: An authorized workspace is required.');
        }
    }
}
