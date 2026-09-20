<?php
declare(strict_types=1);

namespace Api\Controller\Audit;

use Api\Controller\Common\BaseController;
use Api\System\Library\Service\LogsService;

final class AuditController extends BaseController
{
    /**
     * TROPATTCRM-556: `audit_logs` has no organization_id column and no
     * per-tenant ownership model — it is a genuinely system-wide log, unlike
     * every other entity in this app. Pending an owner decision on adding
     * per-organization scoping (option a) or documenting it as-is (option
     * c), the applied default (option b) restricts this view to
     * root/platform actors only, so an org-scoped actor gets a clean 403
     * rather than cross-tenant rows (or empty results that could be
     * mistaken for "no rows in your org").
     */
    private function requireRoot(): ?\Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }
        if (!(bool)($auth['user']['is_root'] ?? false)) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403, [
                'permission' => ['is_root'],
            ]);
        }
        return null;
    }

    public function list(): \Api\System\Library\Http\JsonResponse
    {
        if ($forbidden = $this->requireRoot()) {
            return $forbidden;
        }

        /** @var LogsService $service */
        $service = $this->container->get('service.logs');
        $result = $service->auditList($this->request()->allInput());

        return $this->success('AUDIT_LIST', $this->t('audit/messages.list'), [
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function byUser(array $params): \Api\System\Library\Http\JsonResponse
    {
        if ($forbidden = $this->requireRoot()) {
            return $forbidden;
        }

        $filters = $this->request()->allInput();
        $filters['actor_public_id'] = (string)$params['public_id'];

        /** @var LogsService $service */
        $service = $this->container->get('service.logs');
        $result = $service->auditList($filters);

        return $this->success('AUDIT_USER', $this->t('audit/messages.user'), [
            'actor_public_id' => (string)$params['public_id'],
            'items' => $result['items'],
        ], meta: $result['meta']);
    }

    public function byEntity(array $params): \Api\System\Library\Http\JsonResponse
    {
        if ($forbidden = $this->requireRoot()) {
            return $forbidden;
        }

        $filters = $this->request()->allInput();
        $filters['entity_type'] = (string)$params['entity_type'];
        $filters['entity_public_id'] = (string)$params['public_id'];

        /** @var LogsService $service */
        $service = $this->container->get('service.logs');
        $result = $service->auditList($filters);

        return $this->success('AUDIT_ENTITY', $this->t('audit/messages.entity'), [
            'entity_type' => (string)$params['entity_type'],
            'entity_public_id' => (string)$params['public_id'],
            'items' => $result['items'],
        ], meta: $result['meta']);
    }
}
