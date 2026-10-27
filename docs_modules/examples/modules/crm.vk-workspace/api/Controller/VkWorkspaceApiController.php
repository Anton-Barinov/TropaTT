<?php

declare(strict_types=1);

namespace Module\Crm\VkWorkspace\Api\Controller;

use Api\Controller\Common\BaseController;
use Api\System\Library\Http\JsonResponse;
use Module\Crm\VkWorkspace\Service\VkWorkspaceService;
use Throwable;

final class VkWorkspaceApiController extends BaseController
{
    private function workspaceId(): int
    {
        $actor = $this->user();
        return (int)($actor['user']['workspace_id'] ?? $actor['user']['organization_id'] ?? 0);
    }

    private function service(): VkWorkspaceService
    {
        return new VkWorkspaceService($this->container->get('db.pdo'), $this->workspaceId());
    }

    public function getStatus(): JsonResponse
    {
        if ($this->workspaceId() <= 0) {
            return $this->error('ACTIVE_WORKSPACE_REQUIRED', 'An active workspace is required.', 409);
        }
        return $this->success('VK_WORKSPACE_STATUS', 'VK WorkSpace integration status retrieved.', $this->service()->getStatus());
    }

    public function listMeetings(): JsonResponse
    {
        if ($this->workspaceId() <= 0) {
            return $this->error('ACTIVE_WORKSPACE_REQUIRED', 'An active workspace is required.', 409);
        }
        try {
            return $this->success('VK_WORKSPACE_MEETINGS_LIST', 'Workspace meetings retrieved.', ['items' => $this->service()->listMeetings()]);
        } catch (Throwable) {
            return $this->error('VK_WORKSPACE_MEETINGS_UNAVAILABLE', 'VK WorkSpace meetings are unavailable.', 503);
        }
    }
}
