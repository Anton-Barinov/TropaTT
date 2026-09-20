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

        $authUser = $this->user();
        $actor = $authUser ? $this->organizationScopedActor((array)($authUser['user'] ?? [])) : [];

        /** @var AiUsageService $service */
        $service = $this->container->get('service.ai_usage');
        $result = $service->usageList($this->request()->allInput(), $actor);

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
        // TROPATTCRM-556: `audit_logs` (backing this "AI audit" view via
        // action_prefix=ai_) has no organization_id column and is genuinely
        // system-wide across tenants. Pending an owner decision on adding
        // per-organization scoping, this view is restricted to root/platform
        // actors only — an org-scoped actor must get a clean 403, not an
        // org-admin permission bypass into cross-tenant data.
        if (!(bool)($auth['user']['is_root'] ?? false)) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403, [
                'permission' => ['is_root'],
            ]);
        }

        $authUser = $this->user();
        $actor = $authUser ? $this->organizationScopedActor((array)($authUser['user'] ?? [])) : [];

        /** @var AiUsageService $service */
        $service = $this->container->get('service.ai_usage');
        try {
            $result = $service->auditList($this->request()->allInput(), $actor);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'FORBIDDEN') {
                return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403, [
                    'permission' => ['is_root'],
                ]);
            }
            throw $e;
        }

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
