<?php
declare(strict_types=1);

namespace Api\Controller\Ai;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\AiUsageService;

final class AiUsageController extends BaseController
{
    public function usage(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }
        if (!$this->canViewAiAudit($auth['user'])) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403, [
                'permission' => ['ai.view_audit'],
            ]);
        }

        /** @var AiUsageService $service */
        $service = $this->container->get('service.ai_usage');
        $result = $service->usageList($this->request()->allInput());

        return $this->success('AI_USAGE_LIST', $this->t('ai/messages.action_result'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function audit(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }
        if (!$this->canViewAiAudit($auth['user'])) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403, [
                'permission' => ['ai.view_audit'],
            ]);
        }

        /** @var AiUsageService $service */
        $service = $this->container->get('service.ai_usage');
        $result = $service->auditList($this->request()->allInput());

        return $this->success('AI_AUDIT_LIST', $this->t('ai/messages.action_result'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    /** @param array<string,mixed> $actor */
    private function canViewAiAudit(array $actor): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }

        $roles = is_array($actor['roles'] ?? null) ? (array)$actor['roles'] : [];
        if (in_array('admin', $roles, true)) {
            return true;
        }

        $permissionCodes = is_array($actor['permission_codes'] ?? null) ? (array)$actor['permission_codes'] : [];
        return in_array('ai.admin', $permissionCodes, true) || in_array('ai.view_audit', $permissionCodes, true);
    }
}
